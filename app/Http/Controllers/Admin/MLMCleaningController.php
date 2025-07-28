<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MLM\MLMDataCleaningService;
use App\Services\MLM\MLMCleaningReportService;
use App\Models\MLMCleaningSession;
use App\Models\MLMCleaningAnomaly;
use App\Models\MLMCleaningProgress;
use App\Models\SystemPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MLMCleaningController extends Controller
{
    protected MLMDataCleaningService $cleaningService;

    public function __construct()
    {
        $this->cleaningService = new MLMDataCleaningService();
    }

    /**
     * Dashboard principal
     */
    public function index()
    {
        $sessions = MLMCleaningSession::with('creator')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $stats = [
            'total_sessions' => MLMCleaningSession::count(),
            'active_sessions' => MLMCleaningSession::active()->count(),
            'completed_sessions' => MLMCleaningSession::completed()->count(),
            'total_anomalies_fixed' => MLMCleaningAnomaly::where('is_fixed', true)->count()
        ];

        // Périodes disponibles
        $availablePeriods = DB::table('level_currents')
            ->select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period');

        return view('admin.mlm-cleaning.index', compact('sessions', 'stats', 'availablePeriods'));
    }

    /**
     * Lancer une analyse
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'type' => 'required|in:full,period,hierarchy,cumuls,grades',
            'period_start' => 'nullable|date_format:Y-m',
            'period_end' => 'nullable|date_format:Y-m|after_or_equal:period_start'
        ]);

        try {
            $options = [
                'type' => $request->type,
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'user_id' => Auth::id()
            ];

            // Créer la session
            $session = $this->cleaningService->startCleaningSession($options);

            // Si mode async
            if (config('mlm-cleaning.queue.enabled')) {
                dispatch(new \App\Jobs\MLMDataAnalysisJob($session->id));

                return response()->json([
                    'success' => true,
                    'session_id' => $session->id,
                    'message' => 'Analyse lancée en arrière-plan'
                ]);
            }

            // Mode synchrone
            $result = $this->cleaningService->execute($session, [
                'preview_only' => true
            ]);

            return response()->json([
                'success' => true,
                'session_id' => $session->id,
                'redirect' => route('admin.mlm-cleaning.preview', $session->id)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher le preview
     */
    public function preview(MLMCleaningSession $session)
    {
        if ($session->status !== MLMCleaningSession::STATUS_PREVIEW) {
            return redirect()->route('admin.mlm-cleaning.index')
                ->with('error', 'Cette session n\'est pas en mode preview');
        }

        $reportService = new MLMCleaningReportService($session);
        $preview = $reportService->generatePreviewReport();

        // Anomalies par page
        $anomalies = MLMCleaningAnomaly::where('session_id', $session->id)
            ->with('distributeur')
            ->paginate(20);

        return view('admin.mlm-cleaning.preview', compact('session', 'preview', 'anomalies'));
    }

    /**
     * Lancer le processus de nettoyage
     */
    public function process(Request $request, MLMCleaningSession $session)
    {
        if (!$session->canBeProcessed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette session ne peut pas être traitée'
            ], 400);
        }

        $request->validate([
            'fix_types' => 'nullable|array',
            'fix_types.*' => 'string',
            'confirm' => 'required|accepted'
        ]);

        try {
            $options = [
                'fix_types' => $request->fix_types ?? [],
                'manual_only' => $request->boolean('manual_only')
            ];

            // Si mode async
            if (config('mlm-cleaning.queue.enabled')) {
                dispatch(new \App\Jobs\MLMDataCleaningJob($session->id, $options));

                return response()->json([
                    'success' => true,
                    'message' => 'Nettoyage lancé en arrière-plan'
                ]);
            }

            // Mode synchrone
            $result = $this->cleaningService->execute($session, $options);

            return response()->json([
                'success' => true,
                'redirect' => route('admin.mlm-cleaning.report', $session->id)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher le rapport
     */
    public function report(MLMCleaningSession $session)
    {
        if (!in_array($session->status, [
            MLMCleaningSession::STATUS_COMPLETED,
            MLMCleaningSession::STATUS_FAILED
        ])) {
            return redirect()->route('admin.mlm-cleaning.index')
                ->with('error', 'Le rapport n\'est pas encore disponible');
        }

        $reportService = new MLMCleaningReportService($session);
        $report = $reportService->generateFinalReport();

        return view('admin.mlm-cleaning.report', compact('session', 'report'));
    }

    /**
     * Rollback d'une session
     */
    public function rollback(Request $request, MLMCleaningSession $session)
    {
        if (!$session->canBeRolledBack()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette session ne peut pas être annulée'
            ], 400);
        }

        $request->validate([
            'confirm' => 'required|accepted',
            'reason' => 'required|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $this->cleaningService->rollbackSession($session);

            // Logger l'action
            Log::info('MLM cleaning session rolled back', [
                'session_id' => $session->id,
                'session_code' => $session->session_code,
                'user_id' => Auth::id(),
                'reason' => $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Session annulée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir la progression en temps réel
     */
    public function progress(MLMCleaningSession $session)
    {
        $currentProgress = $session->currentProgress;

        if (!$currentProgress) {
            return response()->json([
                'status' => $session->status,
                'progress' => 0,
                'message' => 'En attente...'
            ]);
        }

        return response()->json([
            'status' => $session->status,
            'step' => $currentProgress->step,
            'step_label' => $currentProgress->getStepLabel(),
            'progress' => $currentProgress->percentage,
            'message' => $currentProgress->message,
            'current_item' => $currentProgress->current_item,
            'processed' => $currentProgress->processed_items,
            'total' => $currentProgress->total_items
        ]);
    }

    /**
     * Télécharger le rapport Excel
     */
    public function downloadReport(MLMCleaningSession $session, string $format = 'excel')
    {
        if ($session->status !== MLMCleaningSession::STATUS_COMPLETED) {
            return redirect()->back()
                ->with('error', 'Le rapport n\'est disponible que pour les sessions terminées');
        }

        $reportService = new MLMCleaningReportService($session);

        switch ($format) {
            case 'excel':
                return $reportService->exportToExcel();
            case 'pdf':
                return $reportService->exportToPdf();
            default:
                return redirect()->back()
                    ->with('error', 'Format non supporté');
        }
    }

    /**
     * Détails d'une anomalie
     */
    public function anomalyDetails(MLMCleaningAnomaly $anomaly)
    {
        $anomaly->load(['session', 'distributeur']);

        return response()->json([
            'anomaly' => $anomaly,
            'distributeur' => $anomaly->distributeur,
            'suggested_fix' => $anomaly->getSuggestedFix(),
            'can_fix' => $anomaly->can_auto_fix && !$anomaly->is_fixed
        ]);
    }

    /**
     * Corriger manuellement une anomalie
     */
    public function fixAnomaly(Request $request, MLMCleaningAnomaly $anomaly)
    {
        if ($anomaly->is_fixed) {
            return response()->json([
                'success' => false,
                'message' => 'Cette anomalie a déjà été corrigée'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Utiliser le service pour corriger
            $cleaningService = new MLMDataCleaningService();
            $fixed = $cleaningService->fixSingleAnomaly($anomaly);

            if ($fixed) {
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Anomalie corrigée avec succès'
                ]);
            }

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Impossible de corriger cette anomalie automatiquement'
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
