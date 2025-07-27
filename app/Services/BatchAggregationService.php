<?php
// app/Services/BatchAggregationService.php

namespace App\Services;

use App\Models\Achat;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use App\Models\SystemPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

class BatchAggregationService
{
    protected CumulManagementService $cumulService;

    public function __construct(CumulManagementService $cumulService)
    {
        $this->cumulService = $cumulService;
    }

    /**
     * Exécute l'agrégation batch pour une période
     */
    public function executeBatchAggregation(string $period, array $options = []): array
    {
        $startTime = microtime(true);
        $batchSize = $options['batch_size'] ?? 100;
        $dryRun = $options['dry_run'] ?? false;

        Log::info("Début de l'agrégation batch", [
            'period' => $period,
            'options' => $options
        ]);

        // Vérifier que la période est valide
        $systemPeriod = SystemPeriod::where('period', $period)->first();
        if (!$systemPeriod || $systemPeriod->status === 'closed') {
            return [
                'success' => false,
                'message' => 'Période invalide ou déjà clôturée'
            ];
        }

        DB::beginTransaction();
        try {
            // 1. Agréger les achats par distributeur
            $aggregatedPurchases = $this->aggregatePurchases($period);

            // 2. Mettre à jour les level_currents en batch
            $updateResult = $this->updateLevelCurrentsBatch($period, $aggregatedPurchases, $batchSize);

            // 3. Propager les cumuls dans la hiérarchie
            if (!$dryRun) {
                $propagationResult = $this->propagateAllCumuls($period, $aggregatedPurchases);
            } else {
                $propagationResult = ['skipped' => true, 'message' => 'Dry run - propagation skipped'];
            }

            if ($dryRun) {
                DB::rollBack();
                $message = "Simulation terminée - Aucune modification appliquée";
            } else {
                DB::commit();
                $message = "Agrégation batch terminée avec succès";
            }

            $duration = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'message' => $message,
                'duration' => $duration,
                'stats' => [
                    'distributors_processed' => $aggregatedPurchases->count(),
                    'updates' => $updateResult['updates'],
                    'inserts' => $updateResult['inserts'],
                    'propagation' => $propagationResult
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de l'agrégation batch", [
                'period' => $period,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Agrège les achats par distributeur pour la période
     */
    protected function aggregatePurchases(string $period): Collection
    {
        return Achat::where('period', $period)
                   ->where('statut', 'validé') // Seulement les achats validés
                   ->groupBy('distributeur_id')
                   ->selectRaw('distributeur_id, SUM(points_unitaire_achat * qt) as total_points, SUM(montant_total_ligne) as total_montant, COUNT(*) as nb_achats')
                   ->get()
                   ->keyBy('distributeur_id');
    }

    /**
     * Met à jour les level_currents en batch
     */
    protected function updateLevelCurrentsBatch(string $period, Collection $aggregatedPurchases, int $batchSize): array
    {
        $updates = 0;
        $inserts = 0;

        // Récupérer les level_currents existants
        $existingLevels = LevelCurrent::where('period', $period)
                                     ->whereIn('distributeur_id', $aggregatedPurchases->keys())
                                     ->get()
                                     ->keyBy('distributeur_id');

        // Préparer les données pour mise à jour/insertion
        $toUpdate = [];
        $toInsert = [];

        foreach ($aggregatedPurchases as $distributeurId => $aggregate) {
            if ($existingLevels->has($distributeurId)) {
                // Mise à jour
                $toUpdate[] = [
                    'id' => $existingLevels[$distributeurId]->id,
                    'new_cumul' => $aggregate->total_points,
                    'cumul_individuel' => DB::raw("cumul_individuel + {$aggregate->total_points}"),
                    'cumul_total' => $aggregate->total_points,
                    'cumul_collectif' => DB::raw("cumul_collectif + {$aggregate->total_points}")
                ];
            } else {
                // Insertion
                $distributeur = Distributeur::find($distributeurId);
                if ($distributeur) {
                    $toInsert[] = [
                        'distributeur_id' => $distributeurId,
                        'period' => $period,
                        'rang' => $distributeur->rang ?? 0,
                        'etoiles' => $distributeur->etoiles_id ?? 1,
                        'cumul_individuel' => $aggregate->total_points,
                        'new_cumul' => $aggregate->total_points,
                        'cumul_total' => $aggregate->total_points,
                        'cumul_collectif' => $aggregate->total_points,
                        'id_distrib_parent' => $distributeur->id_distrib_parent,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        // Exécuter les mises à jour par batch
        foreach (array_chunk($toUpdate, $batchSize) as $batch) {
            foreach ($batch as $update) {
                LevelCurrent::where('id', $update['id'])
                          ->update(Arr::except($update, ['id']));  // Utiliser Arr::except
                $updates++;
            }
        }

        // Exécuter les insertions par batch
        foreach (array_chunk($toInsert, $batchSize) as $batch) {
            LevelCurrent::insert($batch);
            $inserts += count($batch);
        }

        return [
            'updates' => $updates,
            'inserts' => $inserts
        ];
    }

    /**
     * Propage tous les cumuls dans la hiérarchie
     */
    protected function propagateAllCumuls(string $period, Collection $aggregatedPurchases): array
    {
        $propagated = 0;
        $errors = 0;

        foreach ($aggregatedPurchases as $distributeurId => $aggregate) {
            try {
                $this->cumulService->propagateToParents(
                    $distributeurId,
                    $aggregate->total_points,
                    $period
                );
                $propagated++;
            } catch (\Exception $e) {
                Log::error("Erreur propagation pour distributeur {$distributeurId}", [
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        return [
            'propagated' => $propagated,
            'errors' => $errors
        ];
    }

    /**
     * Génère un rapport d'agrégation
     */
    public function generateAggregationReport(string $period): array
    {
        $report = [
            'period' => $period,
            'generated_at' => now()->toISOString(),
            'summary' => [],
            'top_performers' => [],
            'statistics' => []
        ];

        // Résumé général
        $report['summary'] = [
            'total_distributeurs_actifs' => LevelCurrent::where('period', $period)
                                                        ->where('new_cumul', '>', 0)
                                                        ->count(),
            'total_points_distribues' => LevelCurrent::where('period', $period)
                                                     ->sum('new_cumul'),
            'cumul_collectif_total' => LevelCurrent::where('period', $period)
                                                   ->sum('cumul_collectif')
        ];

        // Top performers
        $report['top_performers'] = LevelCurrent::where('period', $period)
                                               ->where('new_cumul', '>', 0)
                                               ->orderBy('new_cumul', 'desc')
                                               ->take(10)
                                               ->with('distributeur')
                                               ->get()
                                               ->map(function ($level) {
                                                   return [
                                                       'matricule' => $level->distributeur->distributeur_id,
                                                       'nom' => $level->distributeur->nom_distributeur,
                                                       'points_periode' => $level->new_cumul,
                                                       'cumul_individuel' => $level->cumul_individuel,
                                                       'grade' => $level->etoiles
                                                   ];
                                               });

        // Statistiques par grade
        $report['statistics']['by_grade'] = LevelCurrent::where('period', $period)
                                                        ->selectRaw('etoiles, COUNT(*) as count, AVG(new_cumul) as avg_points')
                                                        ->groupBy('etoiles')
                                                        ->orderBy('etoiles')
                                                        ->get();

        return $report;
    }
}
