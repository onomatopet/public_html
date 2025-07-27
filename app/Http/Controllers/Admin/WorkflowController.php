<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPeriod;
use App\Models\Achat;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\WorkflowLog;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowController extends Controller
{
    protected $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Affiche le tableau de bord du workflow
     */
    public function index(Request $request)
    {
        // Récupérer la période depuis la requête ou prendre la période active
        $requestedPeriod = $request->get('period');

        // Toutes les périodes pour le sélecteur
        $allPeriods = SystemPeriod::orderBy('period', 'desc')
            ->pluck('period')
            ->toArray();

        // Déterminer la période à afficher
        if ($requestedPeriod && in_array($requestedPeriod, $allPeriods)) {
            $period = $requestedPeriod;
            $systemPeriod = SystemPeriod::where('period', $period)->first();
        } else {
            // Prendre la période active ou la plus récente
            $systemPeriod = SystemPeriod::where('status', 'active')->first()
                ?? SystemPeriod::orderBy('period', 'desc')->first();
            $period = $systemPeriod ? $systemPeriod->period : null;
        }

        // Si aucune période n'existe
        if (!$systemPeriod) {
            return redirect()->route('admin.periods.index')
                ->with('error', 'Aucune période trouvée. Veuillez créer une période.');
        }

        // Statistiques pour chaque étape
        $stats = [
            'validation' => $this->getValidationStats($systemPeriod),
            'aggregation' => $this->getAggregationStats($systemPeriod),
            'advancement' => $this->getAdvancementStats($systemPeriod),
            'snapshot' => $this->getSnapshotStats($systemPeriod),
        ];

        // Logs récents
        $recentLogs = WorkflowLog::where('period', $period)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.workflow.index', compact(
            'systemPeriod',
            'period',
            'allPeriods',
            'stats',
            'recentLogs'
        ));
    }

    /**
     * Affiche l'historique complet du workflow
     */
    public function history(Request $request, $period)
    {
        $logs = WorkflowLog::where('period', $period)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin.workflow.history', compact('logs', 'period'));
    }

    /**
     * Valide les achats d'une période
     */
    public function validatePurchases(Request $request)
    {
        $period = $request->input('period');
        $systemPeriod = SystemPeriod::where('period', $period)->firstOrFail();

        // Log de début
        $log = $this->createWorkflowLog($systemPeriod, 'validation', 'start');

        try {
            // Vérifier les prérequis
            if (!$systemPeriod->canValidatePurchases()) {
                throw new \Exception('Les achats ne peuvent pas être validés dans l\'état actuel.');
            }

            DB::beginTransaction();

            // Récupérer les achats à valider
            $achatsToValidate = Achat::where('period', $systemPeriod->period)
                ->where('status', 'pending')
                ->get();

            $validated = 0;
            $rejected = 0;
            $errors = [];

            foreach ($achatsToValidate as $achat) {
                $validation = $this->validateSinglePurchase($achat);

                if ($validation['valid']) {
                    $achat->status = 'validated';
                    $achat->validated_at = now();
                    $validated++;
                } else {
                    $achat->status = 'rejected';
                    $achat->validated_at = now();
                    $achat->validation_errors = json_encode($validation['errors']);
                    $rejected++;
                    $errors[] = "Achat #{$achat->id}: " . implode(', ', $validation['errors']);
                }

                $achat->save();
            }

            // Mettre à jour le statut du workflow
            $systemPeriod->purchases_validated = true;
            $systemPeriod->purchases_validated_at = now();
            $systemPeriod->purchases_validated_by = Auth::id();
            $systemPeriod->save();

            DB::commit();

            // Log de succès
            $this->updateWorkflowLog($log, 'completed', [
                'validated' => $validated,
                'rejected' => $rejected,
                'total' => $achatsToValidate->count()
            ]);

            $message = "Validation terminée: {$validated} validés, {$rejected} rejetés.";

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();

            // Log d'erreur
            $this->updateWorkflowLog($log, 'failed', null, $e->getMessage());

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

            // Agrégation logic here...
            // [Code d'agrégation similaire à celui fourni précédemment]

            $systemPeriod->purchases_aggregated = true;
            $systemPeriod->purchases_aggregated_at = now();
            $systemPeriod->purchases_aggregated_by = Auth::id();
            $systemPeriod->save();

            DB::commit();

            $this->updateWorkflowLog($log, 'completed');

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', 'Agrégation des achats terminée avec succès.');

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

            // Exécuter la commande ProcessAdvancements
            \Artisan::call('app:process-advancements', [
                'period' => $systemPeriod->period,
                '--type' => 'validated_only',
                '--force' => true
            ]);

            $systemPeriod->advancements_calculated = true;
            $systemPeriod->advancements_calculated_at = now();
            $systemPeriod->advancements_calculated_by = Auth::id();
            $systemPeriod->save();

            $this->updateWorkflowLog($log, 'completed');

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', 'Calcul des avancements terminé.');

        } catch (\Exception $e) {
            $this->updateWorkflowLog($log, 'failed', null, $e->getMessage());

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

            // Créer le snapshot
            // [Code de création de snapshot]

            $systemPeriod->snapshot_created = true;
            $systemPeriod->snapshot_created_at = now();
            $systemPeriod->snapshot_created_by = Auth::id();
            $systemPeriod->save();

            DB::commit();

            $this->updateWorkflowLog($log, 'completed');

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', 'Snapshot créé avec succès.');

        } catch (\Exception $e) {
            DB::rollback();
            $this->updateWorkflowLog($log, 'failed', null, $e->getMessage());

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

        $log = $this->createWorkflowLog($systemPeriod, 'closing', 'start');

        try {
            if (!$systemPeriod->canClose()) {
                throw new \Exception('La période ne peut pas être clôturée dans l\'état actuel.');
            }

            $systemPeriod->status = 'closed';
            $systemPeriod->closed_at = now();
            $systemPeriod->closed_by = Auth::id();
            $systemPeriod->save();

            $this->updateWorkflowLog($log, 'completed');

            return redirect()->route('admin.workflow.index')
                ->with('success', 'Période clôturée avec succès.');

        } catch (\Exception $e) {
            $this->updateWorkflowLog($log, 'failed', null, $e->getMessage());

            return redirect()->back()
                ->with('error', 'Erreur lors de la clôture: ' . $e->getMessage());
        }
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
        return [
            'ready' => $systemPeriod->advancements_calculated ? 'Oui' : 'Non'
        ];
    }

    /**
     * Crée un log de workflow
     */
    protected function createWorkflowLog(SystemPeriod $systemPeriod, $step, $action)
    {
        return WorkflowLog::logStart(
            $systemPeriod->period,
            $step,
            $action,
            Auth::id()
        );
    }

    /**
     * Met à jour un log de workflow
     */
    protected function updateWorkflowLog($log, $status, $details = null, $errorMessage = null)
    {
        if ($status === 'completed') {
            $log->complete($details ?: []);
        } elseif ($status === 'failed') {
            $log->fail($errorMessage, $details ?: []);
        }
    }
}
