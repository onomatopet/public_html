<?php
// app/Services/PeriodManagementService.php

namespace App\Services;

use App\Models\SystemPeriod;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use App\Models\Achat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

class PeriodManagementService
{
    protected PurchaseAggregationService $purchaseAggregation;
    protected CumulManagementService $cumulManagement;

    public function __construct(
        PurchaseAggregationService $purchaseAggregation,
        CumulManagementService $cumulManagement
    ) {
        $this->purchaseAggregation = $purchaseAggregation;
        $this->cumulManagement = $cumulManagement;
    }

    /**
     * Lance l'agrégation manuelle pour une période
     *
     * @param string $period
     * @return array
     */
    public function runManualAggregation(string $period): array
    {
        try {
            DB::beginTransaction();

            // Vérifier que la période existe
            $systemPeriod = SystemPeriod::where('period', $period)->first();
            if (!$systemPeriod) {
                return [
                    'success' => false,
                    'message' => "La période {$period} n'existe pas"
                ];
            }

            // Récupérer tous les achats validés de la période
            $achats = Achat::where('period', $period)
                ->where('status', 'validated')
                ->get();

            if ($achats->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "Aucun achat validé trouvé pour la période {$period}"
                ];
            }

            // Grouper par distributeur
            $achatsByDistributeur = $achats->groupBy('distributeur_id');

            $totalDistributeurs = 0;
            $totalPoints = 0;

            foreach ($achatsByDistributeur as $distributeurId => $achatsDistributeur) {
                $pointsIndividuels = $achatsDistributeur->sum('points_unitaire_achat');
                $montantTotal = $achatsDistributeur->sum('montant_total_ligne');

                // Mettre à jour ou créer l'enregistrement level_current
                $levelCurrent = LevelCurrent::updateOrCreate(
                    [
                        'distributeur_id' => $distributeurId,
                        'period' => $period
                    ],
                    [
                        'cumul_individuel_mensuel' => $pointsIndividuels,
                        'updated_at' => now()
                    ]
                );

                $totalDistributeurs++;
                $totalPoints += $pointsIndividuels;

                Log::info("Agrégation pour distributeur {$distributeurId}", [
                    'period' => $period,
                    'points' => $pointsIndividuels,
                    'montant' => $montantTotal
                ]);
            }

            // Propager dans la hiérarchie si nécessaire
            if (class_exists(\App\Services\CumulManagementService::class)) {
                $cumulService = app(\App\Services\CumulManagementService::class);
                foreach ($achatsByDistributeur->keys() as $distributeurId) {
                    $cumulService->propagateToParents($distributeurId, $period);
                }
            }

            // Marquer l'agrégation comme effectuée
            $systemPeriod->update([
                'purchases_aggregated' => true,
                'purchases_aggregated_at' => now(),
                'purchases_aggregated_by' => auth()->id()
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => "Agrégation terminée avec succès",
                'stats' => [
                    'distributeurs_traites' => $totalDistributeurs,
                    'total_points' => $totalPoints,
                    'achats_traites' => $achats->count()
                ]
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Erreur lors de l\'agrégation manuelle', [
                'period' => $period,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de l\'agrégation : ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clôture la période courante et initialise la suivante
     * MODIFIÉ : Ne fait plus l'agrégation ni la propagation (déjà fait dans le workflow)
     */
    public function closePeriod(string $currentPeriod, int $userId): array
    {
        $period = SystemPeriod::where('period', $currentPeriod)
                             ->where('is_current', true)
                             ->first();

        if (!$period || !$period->canBeClosed()) {
            return [
                'success' => false,
                'message' => 'La période ne peut pas être clôturée. Assurez-vous que toutes les étapes du workflow sont complétées.'
            ];
        }

        DB::beginTransaction();
        try {
            Log::info("Début de la clôture de période: {$currentPeriod}");

            // 1. Créer le résumé de clôture
            $closureSummary = $this->generateClosureSummary($currentPeriod);

            // 2. Marquer la période comme clôturée
            $period->update([
                'status' => SystemPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $userId,
                'closure_summary' => $closureSummary,
                'is_current' => false
            ]);

            // 3. Créer et initialiser la nouvelle période
            $nextPeriod = $this->initializeNextPeriod($currentPeriod);

            DB::commit();

            return [
                'success' => true,
                'message' => "Période {$currentPeriod} clôturée avec succès. Nouvelle période: {$nextPeriod}",
                'summary' => $closureSummary,
                'next_period' => $nextPeriod
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la clôture de période", [
                'period' => $currentPeriod,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la clôture: ' . $e->getMessage()
            ];
        }
    }

    /**
     * SUPPRIMÉ : Cette méthode n'est plus nécessaire car la propagation
     * est faite lors de l'étape d'agrégation dans le workflow
     */
    /*
    protected function propagateCumulsInHierarchy(string $period): void
    {
        // Méthode supprimée - la propagation se fait maintenant dans WorkflowController::aggregatePurchases()
    }
    */

    /**
     * Génère le résumé de clôture
     */
    protected function generateClosureSummary(string $period): array
    {
        return [
            'total_distributeurs_actifs' => Achat::where('period', $period)
                                                ->distinct('distributeur_id')
                                                ->count(),
            'total_achats' => Achat::where('period', $period)->count(),
            'total_points' => Achat::where('period', $period)->sum(DB::raw('points_unitaire_achat * qt')),
            'total_montant' => Achat::where('period', $period)->sum('montant_total_ligne'),
            'nouveaux_grades' => DB::table('avancement_history')
                                  ->where('period', $period)
                                  ->count(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Initialise la période suivante avec report des cumuls
     */
    protected function initializeNextPeriod(string $currentPeriod): string
    {
        $current = Carbon::createFromFormat('Y-m', $currentPeriod);
        $next = $current->addMonth();
        $nextPeriod = $next->format('Y-m');

        // 1. Créer la nouvelle période système
        SystemPeriod::create([
            'period' => $nextPeriod,
            'status' => SystemPeriod::STATUS_OPEN,
            'opened_at' => $next->startOfMonth(),
            'is_current' => true
        ]);

        // 2. Reporter les cumuls de tous les distributeurs actifs
        $this->carryOverCumuls($currentPeriod, $nextPeriod);

        return $nextPeriod;
    }

    /**
     * Reporte les cumuls de la période précédente
     */
    protected function carryOverCumuls(string $fromPeriod, string $toPeriod): void
    {
        // Récupérer tous les level_currents de la période clôturée
        $levelCurrents = LevelCurrent::where('period', $fromPeriod)->get();

        $insertData = [];
        foreach ($levelCurrents as $level) {
            $insertData[] = [
                'distributeur_id' => $level->distributeur_id,
                'period' => $toPeriod,
                'rang' => $level->rang,
                'etoiles' => $level->etoiles,
                'cumul_individuel' => $level->cumul_individuel, // Report du cumul
                'new_cumul' => 0, // Remis à zéro pour la nouvelle période
                'cumul_total' => 0, // Remis à zéro pour la nouvelle période
                'cumul_collectif' => $level->cumul_collectif, // Report du cumul historique
                'id_distrib_parent' => $level->id_distrib_parent,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Insertion en batch
        foreach (array_chunk($insertData, 1000) as $chunk) {
            LevelCurrent::insert($chunk);
        }

        Log::info("Report des cumuls effectué pour " . count($insertData) . " distributeurs vers la période {$toPeriod}");
    }

    /**
     * Passe une période en mode validation
     */
    public function startValidationPhase(string $period): array
    {
        $systemPeriod = SystemPeriod::where('period', $period)
                                   ->where('is_current', true)
                                   ->first();

        if (!$systemPeriod || $systemPeriod->status !== SystemPeriod::STATUS_OPEN) {
            return [
                'success' => false,
                'message' => 'La période doit être ouverte pour passer en validation'
            ];
        }

        $systemPeriod->update([
            'status' => SystemPeriod::STATUS_VALIDATION,
            'validation_started_at' => now()
        ]);

        return [
            'success' => true,
            'message' => "Période {$period} passée en phase de validation"
        ];
    }

    /**
     * Lance l'agrégation batch manuellement
     */
    public function runAggregation(Request $request)
    {
        $period = $request->input('period', SystemPeriod::getCurrentPeriod()?->period);

        if (!$period) {
            return redirect()->back()->with('error', 'Aucune période spécifiée');
        }

        try {
            // Option 1: Utiliser directement le service d'agrégation s'il existe
            if (class_exists(\App\Services\PurchaseAggregationService::class)) {
                $aggregationService = app(\App\Services\PurchaseAggregationService::class);
                $result = $aggregationService->aggregateAndApplyPurchases($period);

                return redirect()->route('admin.periods.index')
                    ->with('success', 'Agrégation exécutée avec succès')
                    ->with('details', $result);
            }

            // Option 2: Utiliser la commande Artisan si elle existe
            if (Artisan::all() && array_key_exists('mlm:aggregate-batch', Artisan::all())) {
                Artisan::call('mlm:aggregate-batch', [
                    'period' => $period,
                    '--batch-size' => 100
                ]);

                $output = Artisan::output();

                return redirect()->route('admin.periods.index')
                    ->with('success', 'Agrégation batch exécutée')
                    ->with('command_output', $output);
            }

            // Option 3: Implémentation directe simple
            DB::beginTransaction();
            try {
                // Récupérer tous les achats de la période
                $achats = \App\Models\Achat::where('period', $period)
                    ->where('status', 'validated')
                    ->get();

                // Grouper par distributeur
                $achatsByDistributeur = $achats->groupBy('distributeur_id');

                $count = 0;
                foreach ($achatsByDistributeur as $distributeurId => $achatsDistributeur) {
                    $totalPoints = $achatsDistributeur->sum('points_unitaire_achat');

                    // Mettre à jour le cumul du distributeur
                    \App\Models\LevelCurrent::updateOrCreate(
                        [
                            'distributeur_id' => $distributeurId,
                            'period' => $period
                        ],
                        [
                            'cumul_individuel_mensuel' => $totalPoints
                        ]
                    );
                    $count++;
                }

                DB::commit();

                return redirect()->route('admin.periods.index')
                    ->with('success', "Agrégation terminée : {$count} distributeurs traités");

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'agrégation', [
                'error' => $e->getMessage(),
                'period' => $period
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors de l\'agrégation : ' . $e->getMessage());
        }
    }
}
