<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PeriodManagementService;
use App\Models\SystemPeriod;
use App\Models\BonusThreshold;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class PeriodController extends Controller
{
    protected PeriodManagementService $periodService;

    public function __construct(PeriodManagementService $periodService)
    {
        $this->periodService = $periodService;
    }

    /**
     * Affiche la page de gestion des périodes
     */
    public function index()
    {
        $currentPeriod = SystemPeriod::getCurrentPeriod();
        $recentPeriods = SystemPeriod::orderBy('period', 'desc')->take(12)->get();
        $bonusThresholds = BonusThreshold::where('is_active', true)->orderBy('grade')->get();

        return view('admin.periods.index', compact('currentPeriod', 'recentPeriods', 'bonusThresholds'));
    }

    /**
     * Démarre la phase de validation
     */
    public function startValidation(Request $request)
    {
        $request->validate([
            'period' => 'required|string|size:7'
        ]);

        $period = $request->input('period');
        $systemPeriod = SystemPeriod::where('period', $period)->first();

        if (!$systemPeriod) {
            return redirect()->back()
                ->with('error', 'Période non trouvée.');
        }

        // Vérifier que la période est ouverte
        if ($systemPeriod->status !== 'open') {
            return redirect()->back()
                ->with('error', 'La période doit être ouverte pour démarrer la validation.');
        }

        DB::beginTransaction();

        try {
            // Mettre à jour le statut de la période
            $systemPeriod->update([
                'status' => 'validation',
                'validation_started_at' => now(),
                'validation_started_by' => Auth::id()
            ]);

            // Créer un log si la table existe
            if (DB::getSchemaBuilder()->hasTable('workflow_logs')) {
                DB::table('workflow_logs')->insert([
                    'period' => $period,
                    'step' => 'validation',
                    'action' => 'start_validation',
                    'status' => 'completed',
                    'user_id' => Auth::id(),
                    'details' => json_encode([
                        'previous_status' => 'open',
                        'new_status' => 'validation'
                    ]),
                    'started_at' => now(),
                    'completed_at' => now(),
                    'created_at' => now()
                ]);
            }

            DB::commit();

            Log::info("Phase de validation démarrée pour la période {$period}", [
                'user' => Auth::user()->name,
                'period' => $period
            ]);

            return redirect()->route('admin.workflow.index', ['period' => $period])
                ->with('success', "La phase de validation a été démarrée pour la période {$period}.");

        } catch (\Exception $e) {
            DB::rollback();

            Log::error("Erreur lors du démarrage de la validation pour la période {$period}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors du démarrage de la validation : ' . $e->getMessage());
        }
    }

    /**
     * Clôture la période courante
     */
    public function closePeriod(Request $request)
    {
        $request->validate([
            'period' => 'required|string',
            'confirm' => 'required|accepted'
        ]);

        try {
            $result = $this->periodService->closePeriod(
                $request->input('period'),
                Auth::id()
            );

            if ($result['success']) {
                return redirect()->route('admin.periods.index')
                    ->with('success', $result['message'])
                    ->with('closure_summary', $result['summary'] ?? null);
            }

            return redirect()->back()->with('error', $result['message']);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la clôture de période', [
                'error' => $e->getMessage(),
                'period' => $request->input('period')
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors de la clôture : ' . $e->getMessage());
        }
    }

    /**
     * Met à jour les seuils de bonus
     */
    public function updateThresholds(Request $request)
    {
        $request->validate([
            'thresholds' => 'required|array',
            'thresholds.*.grade' => 'required|integer|min:1|max:10',
            'thresholds.*.minimum_pv' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();

        try {
            foreach ($request->input('thresholds') as $threshold) {
                BonusThreshold::updateOrCreate(
                    ['grade' => $threshold['grade']],
                    [
                        'minimum_pv' => $threshold['minimum_pv'],
                        'is_active' => true
                    ]
                );
            }

            DB::commit();

            Log::info('Seuils de bonus mis à jour', [
                'user' => Auth::user()->name ?? 'Système',
                'thresholds' => $request->input('thresholds')
            ]);

            return redirect()->route('admin.periods.index')
                ->with('success', 'Les seuils de bonus ont été mis à jour avec succès.');

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Erreur lors de la mise à jour des seuils', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour des seuils : ' . $e->getMessage());
        }
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
            // Vérifier si la commande existe
            if (Artisan::all() && array_key_exists('mlm:aggregate-batch', Artisan::all())) {
                Artisan::call('mlm:aggregate-batch', [
                    'period' => $period,
                    '--batch-size' => 100
                ]);

                $output = Artisan::output();

                return redirect()->route('admin.periods.index')
                    ->with('success', 'Agrégation batch exécutée')
                    ->with('command_output', $output);
            } else {
                // Si la commande n'existe pas, utiliser le service directement
                if ($this->periodService && method_exists($this->periodService, 'runManualAggregation')) {
                    $result = $this->periodService->runManualAggregation($period);

                    return redirect()->route('admin.periods.index')
                        ->with('success', $result['message'] ?? 'Agrégation exécutée');
                }

                return redirect()->back()
                    ->with('error', 'La commande d\'agrégation n\'est pas disponible');
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
