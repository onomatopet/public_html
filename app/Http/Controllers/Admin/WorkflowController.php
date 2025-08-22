<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPeriod;
use App\Models\WorkflowLog;
use App\Models\Bonus;
use App\Models\Achat;
use App\Models\LevelCurrent;
use App\Services\WorkflowService;
use App\Services\PurchaseAggregationService;
use App\Services\CumulManagementService;
use App\Services\LegacyBonusCalculationService;
use App\Services\BatchAggregationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class WorkflowController extends Controller
{
    protected WorkflowService $workflowService;
    protected PurchaseAggregationService $purchaseAggregationService;
    protected CumulManagementService $cumulManagementService;

    public function __construct(
        WorkflowService $workflowService,
        PurchaseAggregationService $purchaseAggregationService,
        CumulManagementService $cumulManagementService
    ) {
        $this->workflowService = $workflowService;
        $this->purchaseAggregationService = $purchaseAggregationService;
        $this->cumulManagementService = $cumulManagementService;
    }

    /**
     * Affiche la page principale du workflow
     */
    public function index(Request $request)
    {
        $period = $request->get('period', date('Y-m'));

        // Récupérer ou créer la période système
        $systemPeriod = SystemPeriod::firstOrCreate(
            ['period' => $period],
            [
                'status' => 'open',
                'is_current' => true,
                'opened_at' => now(),
                'opened_by' => Auth::id()
            ]
        );

        // Obtenir le statut du workflow
        $workflowStatus = $this->workflowService->getWorkflowStatus($systemPeriod);

        // Statistiques pour chaque étape
        $stats = [
            'validation' => $this->getValidationStats($systemPeriod),
            'aggregation' => $this->getAggregationStats($systemPeriod),
            'advancement' => $this->getAdvancementStats($systemPeriod),
            'snapshot' => $this->getSnapshotStats($systemPeriod)
        ];

        // Logs récents
        $recentLogs = WorkflowLog::where('period', $period)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Toutes les périodes disponibles
        $allPeriods = SystemPeriod::orderBy('period', 'desc')
            ->pluck('period');

        return view('admin.workflow.index', compact(
            'systemPeriod',
            'workflowStatus',
            'stats',
            'recentLogs',
            'allPeriods',
            'period'
        ));
    }

    /**
     * Valide les achats
     */
    public function validatePurchases(Request $request)
    {
        $period = $request->input('period');
        $systemPeriod = SystemPeriod::where('period', $period)->firstOrFail();

        $log = $this->createWorkflowLog($systemPeriod, 'validation', 'start');

        try {
            if (!$systemPeriod->canValidatePurchases()) {
                throw new \Exception('La validation des achats ne peut pas être effectuée dans l\'état actuel.');
            }

            DB::beginTransaction();

            // Valider tous les achats de la période
            $achats = Achat::where('period', $period)
                          ->where('status', 'pending')
                          ->get();

            $validated = 0;
            $rejected = 0;

            foreach ($achats as $achat) {
                $validationResult = $this->validateSinglePurchase($achat);

                if ($validationResult['valid']) {
                    $achat->status = 'validated';
                    $validated++;
                } else {
                    $achat->status = 'rejected';
                    $achat->rejection_reason = implode(', ', $validationResult['errors']);
                    $rejected++;
                }

                $achat->save();
            }

            $systemPeriod->purchases_validated = true;
            $systemPeriod->purchases_validated_at = now();
            $systemPeriod->purchases_validated_by = Auth::id();
            $systemPeriod->save();

            DB::commit();

            $this->updateWorkflowLog($log, WorkflowLog::STATUS_COMPLETED, [
                'validated' => $validated,
                'rejected' => $rejected,
                'total' => $achats->count()
            ]);

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', "Validation terminée : {$validated} validés, {$rejected} rejetés.");

        } catch (\Exception $e) {
            DB::rollback();
            $this->updateWorkflowLog($log, WorkflowLog::STATUS_FAILED, null, $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erreur lors de la validation: ' . $e->getMessage());
        }
    }

    /**
     * Agrège les achats
     */
    public function aggregatePurchases(Request $request)
    {
        $period = $request->input('period');
        $systemPeriod = SystemPeriod::where('period', $period)->firstOrFail();

        $log = $this->createWorkflowLog($systemPeriod, 'aggregation', 'start');

        try {
            if (!$systemPeriod->canAggregatePurchases()) {
                throw new \Exception('L\'agrégation ne peut pas être effectuée dans l\'état actuel.');
            }

            DB::beginTransaction();

            // Utiliser le service d'agrégation batch
            $batchService = app(BatchAggregationService::class);

            // Exécuter l'agrégation
            $result = $batchService->executeBatchAggregation($period, [
                'batch_size' => 100,
                'dry_run' => false
            ]);

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // Mettre à jour le statut de la période
            $systemPeriod->purchases_aggregated = true;
            $systemPeriod->purchases_aggregated_at = now();
            $systemPeriod->purchases_aggregated_by = Auth::id();
            $systemPeriod->save();

            DB::commit();

            // Log de succès avec les statistiques
            $this->updateWorkflowLog($log, 'completed', [
                'distributors_processed' => $result['stats']['distributors_processed'],
                'updates' => $result['stats']['updates'],
                'inserts' => $result['stats']['inserts'],
                'duration' => $result['duration']
            ]);

            $message = sprintf(
                'Agrégation terminée: %d distributeurs traités, %d mises à jour, %d insertions en %s secondes',
                $result['stats']['distributors_processed'],
                $result['stats']['updates'],
                $result['stats']['inserts'],
                $result['duration']
            );

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            $this->updateWorkflowLog($log, 'failed', null, $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erreur lors de l\'agrégation: ' . $e->getMessage());
        }
    }

    /**
     * Calcule les avancements
     */
    public function calculateAdvancements(Request $request)
    {
        $period = $request->input('period');
        $systemPeriod = SystemPeriod::where('period', $period)->firstOrFail();

        $log = $this->createWorkflowLog($systemPeriod, 'advancement', 'start');

        try {
            if (!$systemPeriod->canCalculateAdvancements()) {
                throw new \Exception('Le calcul des avancements ne peut pas être effectué dans l\'état actuel.');
            }

            // Utiliser --validated-only au lieu de --type
            Artisan::call('app:process-advancements', [
                'period' => $systemPeriod->period,
                '--include-non-validated' => true
            ]);

            $systemPeriod->advancements_calculated = true;
            $systemPeriod->advancements_calculated_at = now();
            $systemPeriod->advancements_calculated_by = Auth::id();
            $systemPeriod->save();

            $this->updateWorkflowLog($log, WorkflowLog::STATUS_COMPLETED);

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', 'Calcul des avancements terminé.');

        } catch (\Exception $e) {
            $this->updateWorkflowLog($log, WorkflowLog::STATUS_FAILED, null, $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erreur lors du calcul: ' . $e->getMessage());
        }
    }

    /**
     * Crée un snapshot
     */
    public function createSnapshot(Request $request)
    {
        $period = $request->input('period');
        $systemPeriod = SystemPeriod::where('period', $period)->firstOrFail();

        $log = $this->createWorkflowLog($systemPeriod, 'snapshot', 'start');

        try {
            if (!$systemPeriod->canCreateSnapshot()) {
                throw new \Exception('Le snapshot ne peut pas être créé dans l\'état actuel.');
            }

            DB::beginTransaction();

            // Créer le snapshot en copiant les données de level_currents vers level_current_histories
            $levelCurrents = LevelCurrent::where('period', $period)->get();

            $count = 0;
            foreach ($levelCurrents->chunk(1000) as $chunk) {
                foreach ($chunk as $levelCurrent) {
                    DB::table('level_current_histories')->insert([
                        'distributeur_id' => $levelCurrent->distributeur_id,
                        'period' => $levelCurrent->period,
                        'etoiles' => $levelCurrent->etoiles,
                        'new_cumul' => $levelCurrent->new_cumul,
                        'cumul_individuel' => $levelCurrent->cumul_individuel,
                        'cumul_collectif' => $levelCurrent->cumul_collectif,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $count++;
                }
            }

            $systemPeriod->snapshot_created = true;
            $systemPeriod->snapshot_created_at = now();
            $systemPeriod->snapshot_created_by = Auth::id();
            $systemPeriod->save();

            DB::commit();

            $this->updateWorkflowLog($log, WorkflowLog::STATUS_COMPLETED, ['records' => $count]);

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', "Snapshot créé avec succès. {$count} enregistrements archivés.");

        } catch (\Exception $e) {
            DB::rollback();
            $this->updateWorkflowLog($log, WorkflowLog::STATUS_FAILED, null, $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erreur lors de la création du snapshot: ' . $e->getMessage());
        }
    }

    /**
     * Clôture la période
     */
    public function closePeriod(Request $request)
    {
        $period = $request->input('period');
        $systemPeriod = SystemPeriod::where('period', $period)->firstOrFail();

        $log = $this->createWorkflowLog($systemPeriod, 'closure', 'start');

        try {
            if (!$systemPeriod->canClose()) {
                throw new \Exception('La période ne peut pas être clôturée dans l\'état actuel.');
            }

            DB::beginTransaction();

            // Clôturer la période
            $systemPeriod->status = SystemPeriod::STATUS_CLOSED;
            $systemPeriod->closed_at = now();
            $systemPeriod->closed_by = Auth::id();
            $systemPeriod->is_current = false;
            $systemPeriod->save();

            // Créer la nouvelle période
            $nextPeriod = date('Y-m', strtotime($period . ' +1 month'));
            $newSystemPeriod = SystemPeriod::create([
                'period' => $nextPeriod,
                'status' => SystemPeriod::STATUS_OPEN,
                'is_current' => true,
                'opened_at' => now(),
                'opened_by' => Auth::id()
            ]);

            // Reporter les cumuls de la période clôturée vers la nouvelle
            $this->carryOverCumuls($period, $nextPeriod);

            DB::commit();

            $this->updateWorkflowLog($log, WorkflowLog::STATUS_COMPLETED, [
                'closed_period' => $period,
                'new_period' => $nextPeriod
            ]);

            return redirect()->route('admin.workflow.index', ['period' => $nextPeriod])
                ->with('success', "Période {$period} clôturée. Nouvelle période {$nextPeriod} créée.");

        } catch (\Exception $e) {
            DB::rollback();
            $this->updateWorkflowLog($log, WorkflowLog::STATUS_FAILED, null, $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erreur lors de la clôture: ' . $e->getMessage());
        }
    }

    /**
     * Affiche l'historique des actions
     */
    public function history($period)
    {
        $logs = WorkflowLog::where('period', $period)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.workflow.history', compact('logs', 'period'));
    }

    /**
     * Valide un achat individuel
     */
    protected function validateSinglePurchase(Achat $achat): array
    {
        $errors = [];

        if (!$achat->distributeur) {
            $errors[] = 'Distributeur introuvable';
        }

        if (!$achat->product) {
            $errors[] = 'Produit introuvable';
        }

        if ($achat->product) {
            $expectedTotal = $achat->qt * $achat->prix_unitaire_achat;
            if (abs($expectedTotal - $achat->montant_total_ligne) > 0.01) {
                $errors[] = 'Incohérence dans le calcul du montant total';
            }
        }

        if ($achat->purchase_date && $achat->purchase_date > now()) {
            $errors[] = 'Date d\'achat dans le futur';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Obtient les statistiques de validation
     */
    protected function getValidationStats(SystemPeriod $systemPeriod): array
    {
        $query = Achat::where('period', $systemPeriod->period);

        return [
            'total' => $query->count(),
            'validated' => (clone $query)->where('status', 'validated')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];
    }

    /**
     * Obtient les statistiques d'agrégation
     */
    protected function getAggregationStats(SystemPeriod $systemPeriod): array
    {
        $levelCurrents = LevelCurrent::where('period', $systemPeriod->period)->count();
        $totalNewCumul = LevelCurrent::where('period', $systemPeriod->period)->sum('new_cumul');

        return [
            'distributeurs_impactes' => $levelCurrents,
            'total_points' => number_format($totalNewCumul, 0, ',', ' ')
        ];
    }

    /**
     * Obtient les statistiques d'avancement
     */
    protected function getAdvancementStats(SystemPeriod $systemPeriod): array
    {
        $avancements = DB::table('avancement_history')
            ->where('period', $systemPeriod->period)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN nouveau_grade > ancien_grade THEN 1 ELSE 0 END) as promotions,
                SUM(CASE WHEN nouveau_grade < ancien_grade THEN 1 ELSE 0 END) as demotions
            ')
            ->first();

        return [
            'total' => $avancements->total ?? 0,
            'promotions' => $avancements->promotions ?? 0,
            'demotions' => $avancements->demotions ?? 0
        ];
    }

    /**
     * Obtient les statistiques de snapshot
     */
    protected function getSnapshotStats(SystemPeriod $systemPeriod): array
    {
        $historyCount = DB::table('level_current_histories')
            ->where('period', $systemPeriod->period)
            ->count();

        return [
            'ready' => $systemPeriod->advancements_calculated ? 'Oui' : 'Non',
            'archived' => $historyCount
        ];
    }

    /**
     * Crée un log de workflow
     */
    protected function createWorkflowLog(SystemPeriod $systemPeriod, $step, $action)
    {
        return WorkflowLog::create([
            'period' => $systemPeriod->period,
            'step' => $step,
            'action' => $action,
            'status' => WorkflowLog::STATUS_STARTED, // CORRECTION ICI : Utiliser la constante au lieu de 'processing'
            'user_id' => Auth::id(),
            'started_at' => now()
        ]);
    }

    /**
     * Met à jour un log de workflow
     */
    protected function updateWorkflowLog($log, $status, $details = null, $errorMessage = null)
    {
        $log->update([
            'status' => $status,
            'completed_at' => now(),
            'details' => $details,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Report des cumuls de la période clôturée vers la nouvelle période
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

        Log::info("Report des cumuls effectué", [
            'from_period' => $fromPeriod,
            'to_period' => $toPeriod,
            'count' => count($insertData)
        ]);
    }

    /**
     * Calcule les bonus
     */
    public function calculateBonus(Request $request)
    {
        $period = $request->input('period');
        $systemPeriod = SystemPeriod::where('period', $period)->firstOrFail();

        $log = $this->createWorkflowLog($systemPeriod, 'bonus', 'start');

        try {
            if (!$systemPeriod->canCalculateBonus()) {
                throw new \Exception('Le calcul des bonus ne peut pas être effectué dans l\'état actuel.');
            }

            DB::beginTransaction();

            // Utiliser le service de calcul avec les anciennes règles
            $bonusService = app(LegacyBonusCalculationService::class);

            // Exécuter le calcul
            $result = $bonusService->calculateBonusesForPeriod($period, [
                'dry_run' => false,
                'batch_size' => 100
            ]);

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // Mettre à jour le statut de la période
            $systemPeriod->markBonusCalculated(Auth::id());

            DB::commit();

            // Log de succès avec les statistiques
            $this->updateWorkflowLog($log, 'completed', [
                'total_distributeurs' => $result['stats']['total_distributeurs'],
                'eligibles' => $result['stats']['eligibles'],
                'bonuses_calculated' => $result['stats']['bonuses_calculated'],
                'total_amount' => $result['stats']['total_amount'],
                'duration' => $result['duration']
            ]);

            $message = sprintf(
                'Calcul des bonus terminé: %d éligibles sur %d, %d bonus calculés pour un total de %s FCFA en %s secondes',
                $result['stats']['eligibles'],
                $result['stats']['total_distributeurs'],
                $result['stats']['bonuses_calculated'],
                number_format($result['stats']['total_amount'], 0, ',', ' '),
                $result['duration']
            );

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            $this->updateWorkflowLog($log, 'failed', null, $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erreur lors du calcul des bonus: ' . $e->getMessage());
        }
    }

    /**
     * Ajouter dans getAdvancementStats() pour afficher les stats de bonus
     */
    protected function getBonusStats(SystemPeriod $systemPeriod): array
    {
        if (!$systemPeriod->bonus_calculated) {
            return [
                'ready' => $systemPeriod->advancements_calculated ? 'Oui' : 'Non',
                'message' => 'En attente du calcul des avancements'
            ];
        }

        $stats = Bonus::where('period', $systemPeriod->period)
            ->selectRaw('
                COUNT(*) as total,
                SUM(montant_direct) as total_direct,
                SUM(montant_indirect) as total_indirect,
                SUM(montant_total) as total_bonus,
                SUM(epargne) as total_epargne
            ')
            ->first();

        return [
            'count' => $stats->total ?? 0,
            'total_direct' => number_format($stats->total_direct ?? 0, 0, ',', ' ') . ' FCFA',
            'total_indirect' => number_format($stats->total_indirect ?? 0, 0, ',', ' ') . ' FCFA',
            'total_bonus' => number_format($stats->total_bonus ?? 0, 0, ',', ' ') . ' FCFA',
            'total_epargne' => number_format($stats->total_epargne ?? 0, 0, ',', ' ') . ' FCFA'
        ];
    }

    /**
     * Réinitialise une étape spécifique du workflow
     */
    public function resetStep(Request $request)
    {
        $request->validate([
            'period' => 'required|string',
            'step' => 'required|string|in:purchases_validated,purchases_aggregated,advancements_calculated,bonus_calculated,snapshot_created'
        ]);

        $period = $request->input('period');
        $step = $request->input('step');

        $systemPeriod = SystemPeriod::where('period', $period)->firstOrFail();

        // Vérifier que la période n'est pas fermée
        if ($systemPeriod->status === SystemPeriod::STATUS_CLOSED) {
            return redirect()->back()
                ->with('error', 'Impossible de réinitialiser une étape sur une période fermée.');
        }

        DB::beginTransaction();
        try {
            // Logger l'action
            WorkflowLog::create([
                'period' => $period,
                'step' => $step,
                'action' => 'reset',
                'status' => 'started',
                'user_id' => Auth::id(),
                'started_at' => now(),
                'details' => ['reason' => $request->input('reason', 'Réinitialisation manuelle')]
            ]);

            // Réinitialiser selon l'étape
            switch ($step) {
                case 'purchases_validated':
                    $this->resetPurchasesValidation($systemPeriod);
                    break;

                case 'purchases_aggregated':
                    $this->resetPurchasesAggregation($systemPeriod);
                    break;

                case 'advancements_calculated':
                    $this->resetAdvancements($systemPeriod);
                    break;

                case 'bonus_calculated':
                    $this->resetBonus($systemPeriod);
                    break;

                case 'snapshot_created':
                    $this->resetSnapshot($systemPeriod);
                    break;
            }

            // Réinitialiser toutes les étapes suivantes
            $this->resetSubsequentSteps($systemPeriod, $step);

            DB::commit();

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', "L'étape a été réinitialisée avec succès.");

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Erreur lors de la réinitialisation de l'étape", [
                'period' => $period,
                'step' => $step,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors de la réinitialisation: ' . $e->getMessage());
        }
    }

    /**
     * Réinitialise la validation des achats
     */
    private function resetPurchasesValidation(SystemPeriod $systemPeriod): void
    {
        // Remettre tous les achats en statut pending
        Achat::where('period', $systemPeriod->period)
            ->update(['status' => 'pending']);

        // Réinitialiser les flags
        $systemPeriod->update([
            'purchases_validated' => false,
            'purchases_validated_at' => null,
            'purchases_validated_by' => null
        ]);

        Log::info("Validation des achats réinitialisée", ['period' => $systemPeriod->period]);
    }

    /**
     * Réinitialise l'agrégation des achats
     */
    private function resetPurchasesAggregation(SystemPeriod $systemPeriod): void
    {
        // Réinitialiser les cumuls dans level_currents
        LevelCurrent::where('period', $systemPeriod->period)
            ->update([
                'new_cumul' => 0,
                'cumul_total' => 0
            ]);

        // Note: On ne touche pas à cumul_individuel et cumul_collectif car ils sont historiques

        $systemPeriod->update([
            'purchases_aggregated' => false,
            'purchases_aggregated_at' => null,
            'purchases_aggregated_by' => null
        ]);

        Log::info("Agrégation des achats réinitialisée", ['period' => $systemPeriod->period]);
    }

    /**
     * Réinitialise les avancements
     */
    private function resetAdvancements(SystemPeriod $systemPeriod): void
    {
        // Supprimer l'historique des avancements
        if (Schema::hasTable('avancement_history')) {
            DB::table('avancement_history')
                ->where('period', $systemPeriod->period)
                ->delete();
        }

        // Réinitialiser les grades dans level_currents à leur valeur d'origine
        DB::statement("
            UPDATE level_currents lc
            JOIN distributeurs d ON lc.distributeur_id = d.id
            SET lc.etoiles = d.etoiles_id
            WHERE lc.period = ?
        ", [$systemPeriod->period]);

        $systemPeriod->update([
            'advancements_calculated' => false,
            'advancements_calculated_at' => null,
            'advancements_calculated_by' => null
        ]);

        Log::info("Avancements réinitialisés", ['period' => $systemPeriod->period]);
    }

    /**
     * Réinitialise les bonus
     */
    private function resetBonus(SystemPeriod $systemPeriod): void
    {
        // Supprimer tous les bonus de la période
        Bonus::where('period', $systemPeriod->period)->delete();

        $systemPeriod->update([
            'bonus_calculated' => false,
            'bonus_calculated_at' => null,
            'bonus_calculated_by' => null
        ]);

        Log::info("Bonus réinitialisés", ['period' => $systemPeriod->period]);
    }

    /**
     * Réinitialise le snapshot
     */
    private function resetSnapshot(SystemPeriod $systemPeriod): void
    {
        // Supprimer les snapshots si ils existent
        // À adapter selon votre implémentation des snapshots

        $systemPeriod->update([
            'snapshot_created' => false,
            'snapshot_created_at' => null,
            'snapshot_created_by' => null
        ]);

        Log::info("Snapshot réinitialisé", ['period' => $systemPeriod->period]);
    }

    /**
     * Réinitialise toutes les étapes suivant l'étape donnée
     */
    private function resetSubsequentSteps(SystemPeriod $systemPeriod, string $fromStep): void
    {
        $stepsOrder = [
            'purchases_validated' => 1,
            'purchases_aggregated' => 2,
            'advancements_calculated' => 3,
            'bonus_calculated' => 4,
            'snapshot_created' => 5
        ];

        $fromOrder = $stepsOrder[$fromStep];

        // Réinitialiser toutes les étapes après celle spécifiée
        foreach ($stepsOrder as $step => $order) {
            if ($order > $fromOrder) {
                switch ($step) {
                    case 'purchases_aggregated':
                        if ($systemPeriod->purchases_aggregated) {
                            $this->resetPurchasesAggregation($systemPeriod);
                        }
                        break;
                    case 'advancements_calculated':
                        if ($systemPeriod->advancements_calculated) {
                            $this->resetAdvancements($systemPeriod);
                        }
                        break;
                    case 'bonus_calculated':
                        if ($systemPeriod->bonus_calculated) {
                            $this->resetBonus($systemPeriod);
                        }
                        break;
                    case 'snapshot_created':
                        if ($systemPeriod->snapshot_created) {
                            $this->resetSnapshot($systemPeriod);
                        }
                        break;
                }
            }
        }
    }
}
