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
use Illuminate\Support\Facades\Schema;

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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Agrège les achats par distributeur pour la période
     * CORRECTION: Utilise le bon nom de colonne 'statut' et adapte le filtre
     */
    protected function aggregatePurchases(string $period): Collection
    {
        // IMPORTANT: Vérifier d'abord quelle colonne utiliser pour les points
        $pointColumn = Schema::hasColumn('achats', 'points_unitaire_achat')
            ? 'points_unitaire_achat * qt'
            : 'pointvaleur';

        // Construction de la requête de base
        $query = Achat::where('period', $period)
                      ->groupBy('distributeur_id')
                      ->selectRaw("distributeur_id, SUM($pointColumn) as total_points, SUM(montant_total_ligne) as total_montant, COUNT(*) as nb_achats");

        // CORRECTION: Vérifier si la colonne 'statut' existe et appliquer le filtre approprié
        if (Schema::hasColumn('achats', 'statut')) {
            // Option 1: Filtrer uniquement les achats validés
            // $query->where('statut', 'validé');

            // Option 2: Filtrer tous les achats sauf ceux en attente
            // $query->where('statut', '!=', 'pending');

            // Option 3: Pas de filtre sur le statut (recommandé si les achats sont déjà validés avant l'agrégation)
            // Ne rien faire ici

            Log::info("Agrégation des achats sans filtre sur le statut pour inclure tous les achats de la période");
        }

        $result = $query->get()->keyBy('distributeur_id');

        Log::info("Achats agrégés", [
            'period' => $period,
            'nb_distributeurs' => $result->count(),
            'total_points' => $result->sum('total_points')
        ]);

        return $result;
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

        // Traiter chaque distributeur avec des achats
        foreach ($aggregatedPurchases->chunk($batchSize) as $chunk) {
            $updateData = [];
            $insertData = [];

            foreach ($chunk as $distributeurId => $aggregate) {
                $points = (float) $aggregate->total_points;

                if ($existingLevels->has($distributeurId)) {
                    // Mise à jour d'un enregistrement existant
                    $updateData[] = [
                        'distributeur_id' => $distributeurId,
                        'period' => $period,
                        'points' => $points
                    ];
                } else {
                    // Nouvelle insertion - récupérer les infos du distributeur
                    $distributeur = Distributeur::find($distributeurId);
                    if ($distributeur) {
                        $insertData[] = [
                            'distributeur_id' => $distributeurId,
                            'period' => $period,
                            'rang' => $distributeur->rang ?? 0,
                            'etoiles' => $distributeur->etoiles_id ?? 1,
                            'cumul_individuel' => $points,
                            'new_cumul' => $points,
                            'cumul_total' => $points,
                            'cumul_collectif' => $points,
                            'id_distrib_parent' => $distributeur->id_distrib_parent,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }
            }

            // Effectuer les mises à jour en batch
            foreach ($updateData as $data) {
                $existingLevel = $existingLevels[$data['distributeur_id']];

                // IMPORTANT: new_cumul et cumul_total sont les valeurs du MOIS (remplacent)
                $existingLevel->new_cumul = $data['points'];
                $existingLevel->cumul_total = $data['points'];

                // Les cumuls historiques sont incrémentés
                $existingLevel->cumul_individuel += $data['points'];
                $existingLevel->cumul_collectif += $data['points'];

                $existingLevel->save();
                $updates++;

                Log::debug("Level current mis à jour", [
                    'distributeur_id' => $data['distributeur_id'],
                    'new_cumul' => $data['points'],
                    'cumul_individuel' => $existingLevel->cumul_individuel,
                    'cumul_collectif' => $existingLevel->cumul_collectif
                ]);
            }

            // Effectuer les insertions en batch
            if (!empty($insertData)) {
                LevelCurrent::insert($insertData);
                $inserts += count($insertData);

                Log::debug("Nouveaux level currents insérés", [
                    'count' => count($insertData)
                ]);
            }
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

        Log::info("Début de la propagation des cumuls dans la hiérarchie", [
            'period' => $period,
            'nb_distributeurs' => $aggregatedPurchases->count()
        ]);

        foreach ($aggregatedPurchases as $distributeurId => $aggregate) {
            try {
                // Propager les points dans la chaîne parentale
                $this->cumulService->propagateToParents(
                    $distributeurId,
                    $aggregate->total_points,
                    $period
                );
                $propagated++;

                Log::debug("Cumul propagé pour distributeur", [
                    'distributeur_id' => $distributeurId,
                    'montant' => $aggregate->total_points
                ]);
            } catch (\Exception $e) {
                Log::error("Erreur propagation pour distributeur {$distributeurId}", [
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        Log::info("Propagation terminée", [
            'propagated' => $propagated,
            'errors' => $errors
        ]);

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

    /**
     * Vérifie l'intégrité des données après agrégation
     */
    public function verifyAggregationIntegrity(string $period): array
    {
        $issues = [];

        // 1. Vérifier que tous les achats ont été agrégés
        $achatsTotal = Achat::where('period', $period)
                           ->sum(Schema::hasColumn('achats', 'points_unitaire_achat')
                                 ? DB::raw('points_unitaire_achat * qt')
                                 : 'pointvaleur');

        $levelCurrentTotal = LevelCurrent::where('period', $period)
                                        ->sum('new_cumul');

        if (abs($achatsTotal - $levelCurrentTotal) > 0.01) {
            $issues[] = [
                'type' => 'total_mismatch',
                'message' => "Différence entre total achats ({$achatsTotal}) et total level_currents ({$levelCurrentTotal})",
                'severity' => 'high'
            ];
        }

        // 2. Vérifier la cohérence des cumuls
        $inconsistentCumuls = LevelCurrent::where('period', $period)
                                         ->whereRaw('cumul_individuel < new_cumul')
                                         ->count();

        if ($inconsistentCumuls > 0) {
            $issues[] = [
                'type' => 'cumul_inconsistency',
                'message' => "{$inconsistentCumuls} distributeurs ont un cumul_individuel inférieur à new_cumul",
                'severity' => 'medium'
            ];
        }

        // 3. Vérifier les distributeurs sans level_current malgré des achats
        $distributeursWithoutLevel = Achat::where('period', $period)
                                         ->whereNotIn('distributeur_id', function($query) use ($period) {
                                             $query->select('distributeur_id')
                                                   ->from('level_currents')
                                                   ->where('period', $period);
                                         })
                                         ->distinct('distributeur_id')
                                         ->count('distributeur_id');

        if ($distributeursWithoutLevel > 0) {
            $issues[] = [
                'type' => 'missing_level_current',
                'message' => "{$distributeursWithoutLevel} distributeurs ont des achats mais pas de level_current",
                'severity' => 'high'
            ];
        }

        return [
            'period' => $period,
            'integrity_check' => empty($issues) ? 'passed' : 'failed',
            'issues' => $issues,
            'checked_at' => now()->toISOString()
        ];
    }
}
