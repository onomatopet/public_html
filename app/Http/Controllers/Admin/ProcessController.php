<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Achat;
use App\Models\AvancementHistory;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Bonus;
use Symfony\Component\Console\Output\BufferedOutput;

class ProcessController extends Controller
{
    /**
     * Affiche la page de gestion des processus métier
     */
    public function index(): View
    {
        // Récupérer les périodes disponibles
        $availablePeriods = Achat::select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period');

        // Période actuelle par défaut
        $currentPeriod = date('Y-m');

        // Statistiques du système
        $stats = $this->getSystemStats();

        // Historique récent des exécutions
        $recentExecutions = $this->getRecentExecutions();

        return view('admin.processes.index', compact('availablePeriods', 'currentPeriod', 'stats', 'recentExecutions'));
    }

    /**
     * Affiche l'historique des processus
     */
    public function history(Request $request): View
    {
        $query = AvancementHistory::with('distributeur')
            ->orderBy('date_avancement', 'desc');

        // Filtres
        if ($request->filled('period')) {
            $query->where('period', $request->input('period'));
        }

        if ($request->filled('type_calcul')) {
            $query->where('type_calcul', $request->input('type_calcul'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_avancement', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_avancement', '<=', $request->input('date_to'));
        }

        $avancements = $query->paginate(50);

        // Statistiques pour la période
        $stats = $this->getHistoryStats($request);

        // Périodes disponibles pour le filtre
        $availablePeriods = AvancementHistory::distinct()
            ->orderBy('period', 'desc')
            ->pluck('period');

        return view('admin.processes.history', compact('avancements', 'stats', 'availablePeriods'));
    }

    /**
     * Exécute le processus d'avancement des grades
     */
    public function processAdvancements(Request $request): RedirectResponse
    {
        $request->validate([
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'dry_run' => 'nullable|boolean',
            'validated_only' => 'nullable|boolean'
        ]);

        $period = $request->input('period');
        $dryRun = $request->boolean('dry_run');
        $validatedOnly = $request->boolean('validated_only');

        try {
            // Capturer la sortie de la commande
            $output = new BufferedOutput();

            // Exécuter la commande
            $exitCode = Artisan::call('app:process-advancements', [
                'period' => $period,
                '--dry-run' => $dryRun,
                '--validated-only' => $validatedOnly,
                '--force' => true
            ], $output);

            $commandOutput = $output->fetch();

            if ($exitCode === 0) {
                $modeText = $dryRun ? 'Simulation d\'avancement' : 'Avancements appliqués';
                $scopeText = $validatedOnly ? ' (distributeurs avec achats validés uniquement)' : '';
                $message = "{$modeText} terminée pour {$period}{$scopeText}";

                // Si ce n'est pas un dry-run, enregistrer les avancements dans l'historique
                if (!$dryRun) {
                    $this->saveAdvancementsToHistory($period, $validatedOnly ? 'validated_only' : 'normal', $commandOutput);
                }

                // Enregistrer l'exécution
                $this->logExecution('advancement', $period, 'success', [
                    'dry_run' => $dryRun,
                    'validated_only' => $validatedOnly,
                    'output' => $commandOutput
                ]);

                Log::info("Processus d'avancement exécuté via interface web", [
                    'period' => $period,
                    'dry_run' => $dryRun,
                    'validated_only' => $validatedOnly,
                    'exit_code' => $exitCode,
                    'user_id' => Auth::id()
                ]);

                return redirect()->route('admin.processes.index')
                    ->with('success', $message)
                    ->with('command_output', $commandOutput);
            } else {
                // Enregistrer l'échec
                $this->logExecution('advancement', $period, 'failed', [
                    'dry_run' => $dryRun,
                    'validated_only' => $validatedOnly,
                    'output' => $commandOutput
                ]);

                Log::error("Erreur lors de l'exécution du processus d'avancement", [
                    'period' => $period,
                    'dry_run' => $dryRun,
                    'validated_only' => $validatedOnly,
                    'exit_code' => $exitCode,
                    'output' => $commandOutput
                ]);

                return redirect()->route('admin.processes.index')
                    ->with('error', 'Erreur lors du processus d\'avancement')
                    ->with('command_output', $commandOutput);
            }

        } catch (\Exception $e) {
            Log::error("Exception lors du processus d'avancement", [
                'period' => $period,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('admin.processes.index')
                ->with('error', 'Erreur inattendue: ' . $e->getMessage());
        }
    }

    /**
     * Exécute le processus de régularisation des grades
     */
    public function regularizeGrades(Request $request): RedirectResponse
    {
        $request->validate([
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'dry_run' => 'nullable|boolean'  // ✅ Nom correct
        ]);

        $period = $request->input('period');
        $dryRun = $request->boolean('dry_run');  // ✅ Nom correct

        try {
            // Capturer la sortie de la commande
            $output = new BufferedOutput();

            // Préparer les paramètres de la commande
            $params = [
                'period' => $period,
                '--force' => true
            ];

            // Ajouter l'option dry-run seulement si elle est cochée
            if ($dryRun) {
                $params['--dry-run'] = true;  // ✅ Utiliser le bon nom d'option
            }

            // Exécuter la commande
            $exitCode = Artisan::call('app:regularize-grades', $params, $output);

            $commandOutput = $output->fetch();

            if ($exitCode === 0) {
                $message = $dryRun
                    ? "Audit des grades terminé pour {$period} (mode test)"
                    : "Régularisation des grades appliquée pour {$period}";

                // Enregistrer l'exécution
                $this->logExecution('regularization', $period, 'success', [
                    'dry_run' => $dryRun,
                    'output' => $commandOutput
                ]);

                Log::info("Processus de régularisation exécuté via interface web", [
                    'period' => $period,
                    'dry_run' => $dryRun,
                    'exit_code' => $exitCode,
                    'user_id' => Auth::id()
                ]);

                return redirect()->route('admin.processes.index')
                    ->with('success', $message)
                    ->with('command_output', $commandOutput);
            } else {
                // Enregistrer l'échec
                $this->logExecution('regularization', $period, 'failed', [
                    'dry_run' => $dryRun,
                    'output' => $commandOutput
                ]);

                Log::error("Erreur lors de la régularisation", [
                    'period' => $period,
                    'dry_run' => $dryRun,
                    'exit_code' => $exitCode,
                    'output' => $commandOutput
                ]);

                return redirect()->route('admin.processes.index')
                    ->with('error', 'Erreur lors de la régularisation')
                    ->with('command_output', $commandOutput);
            }

        } catch (\Exception $e) {
            Log::error("Exception lors de la régularisation", [
                'period' => $period,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('admin.processes.index')
                ->with('error', 'Erreur inattendue: ' . $e->getMessage());
        }
    }

    /**
     * Enregistre les avancements dans l'historique
     */
    private function saveAdvancementsToHistory(string $period, string $typeCalcul, string $commandOutput): void
    {
        try {
            // Parser la sortie de la commande pour extraire les avancements
            $avancements = $this->parseAdvancementsFromOutput($commandOutput);

            foreach ($avancements as $avancement) {
                AvancementHistory::createAdvancement(
                    $avancement['distributeur_id'],
                    $period,
                    $avancement['ancien_grade'],
                    $avancement['nouveau_grade'],
                    $typeCalcul,
                    [
                        'source' => 'interface_web',
                        'user_id' => Auth::id(),
                        'timestamp' => now()->toISOString(),
                        'command_output_extract' => $avancement['details'] ?? null,
                        'matricule' => $avancement['matricule'] ?? null
                    ]
                );
            }

            Log::info("Avancements enregistrés dans l'historique", [
                'period' => $period,
                'type_calcul' => $typeCalcul,
                'nombre_avancements' => count($avancements)
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur lors de l'enregistrement des avancements dans l'historique", [
                'period' => $period,
                'type_calcul' => $typeCalcul,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Parse la sortie de la commande pour extraire les informations d'avancement
     * Version améliorée qui s'adapte au format réel de ProcessAllDistributorAdvancements
     */
    private function parseAdvancementsFromOutput(string $output): array
    {
        $avancements = [];

        // Patterns pour détecter les promotions dans différents formats possibles
        $patterns = [
            // Format: "Promotion for ID:12345 (MAT001): Grade 2 -> 3"
            '/Promotion for ID:(\d+) \(([A-Z0-9]+)\): Grade (\d+) -> (\d+)/i',

            // Format: "Applying promotion: MAT001 from grade 2 to 3"
            '/Applying promotion: ([A-Z0-9]+) from grade (\d+) to (\d+)/i',

            // Format: "[PROMO] Distributeur #12345 (MAT001): 2 => 3"
            '/\[PROMO\] Distributeur #(\d+) \(([A-Z0-9]+)\): (\d+) => (\d+)/i',

            // Format générique pour détecter les changements de grade
            '/(?:ID|#)(\d+).*?(?:grade|Grade|niveau).*?(\d+).*?(?:->|=>|to|vers).*?(\d+)/i'
        ];

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    // Adapter selon le format détecté
                    if (count($matches) === 5) { // Format avec ID et matricule
                        $avancements[] = [
                            'distributeur_id' => (int)$matches[1],
                            'matricule' => $matches[2],
                            'ancien_grade' => (int)$matches[3],
                            'nouveau_grade' => (int)$matches[4],
                            'details' => trim($line)
                        ];
                    } elseif (count($matches) === 4) { // Format sans ID ou sans matricule
                        // Essayer de récupérer l'ID depuis le matricule si possible
                        $matricule = is_numeric($matches[1]) ? null : $matches[1];
                        $distributeur = null;

                        if ($matricule) {
                            $distributeur = Distributeur::where('distributeur_id', $matricule)->first();
                        }

                        if ($distributeur || is_numeric($matches[1])) {
                            $avancements[] = [
                                'distributeur_id' => $distributeur ? $distributeur->id : (int)$matches[1],
                                'matricule' => $matricule,
                                'ancien_grade' => (int)$matches[2],
                                'nouveau_grade' => (int)$matches[3],
                                'details' => trim($line)
                            ];
                        }
                    }
                    break; // Passer à la ligne suivante après avoir trouvé une correspondance
                }
            }
        }

        return $avancements;
    }

    /**
     * Récupère les statistiques du système
     */
    private function getSystemStats(): array
    {
        try {
            return [
                'total_distributeurs' => Distributeur::count(),
                'distributeurs_actifs_mois' => Distributeur::whereHas('achats', function($query) {
                    $query->where('period', date('Y-m'));
                })->count(),
                'total_achats_mois' => Achat::where('period', date('Y-m'))->count(),
                'total_bonus_mois' => Bonus::where('period', date('Y-m'))->count(),
                'periodes_disponibles' => Achat::select('period')->distinct()->count(),
                'dernier_traitement' => LevelCurrent::latest('updated_at')->value('updated_at'),

                // Statistiques additionnelles
                'avancements_mois' => AvancementHistory::where('period', date('Y-m'))->count(),
                'total_points_mois' => LevelCurrent::where('period', date('Y-m'))->sum('cumul_individuel')
            ];
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des statistiques", [
                'error' => $e->getMessage()
            ]);

            return [
                'total_distributeurs' => 0,
                'distributeurs_actifs_mois' => 0,
                'total_achats_mois' => 0,
                'total_bonus_mois' => 0,
                'periodes_disponibles' => 0,
                'dernier_traitement' => null,
                'avancements_mois' => 0,
                'total_points_mois' => 0
            ];
        }
    }

    /**
     * Récupère les statistiques de l'historique
     */
    private function getHistoryStats(Request $request): array
    {
        $query = AvancementHistory::query();

        // Appliquer les mêmes filtres que pour l'historique
        if ($request->filled('period')) {
            $query->where('period', $request->input('period'));
        }

        if ($request->filled('type_calcul')) {
            $query->where('type_calcul', $request->input('type_calcul'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_avancement', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_avancement', '<=', $request->input('date_to'));
        }

        return [
            'total' => $query->count(),
            'promotions' => (clone $query)->whereColumn('nouveau_grade', '>', 'ancien_grade')->count(),
            'demotions' => (clone $query)->whereColumn('nouveau_grade', '<', 'ancien_grade')->count(),
            'par_type' => [
                'normal' => (clone $query)->where('type_calcul', 'normal')->count(),
                'validated_only' => (clone $query)->where('type_calcul', 'validated_only')->count()
            ]
        ];
    }

    /**
     * Récupère les exécutions récentes
     */
    private function getRecentExecutions(): \Illuminate\Support\Collection
    {
        // Cette méthode pourrait lire depuis une table de logs ou depuis les logs Laravel
        // Pour l'instant, on simule avec les données d'AvancementHistory
        return AvancementHistory::select('period', 'type_calcul', 'created_at')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('period', 'type_calcul', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return (object)[
                    'type' => 'advancement',
                    'period' => $item->period,
                    'details' => "Type: {$item->type_calcul}, {$item->count} changements",
                    'status' => 'success',
                    'user' => Auth::user()->name ?? 'Système',
                    'created_at' => $item->created_at
                ];
            });
    }

    /**
     * Enregistre une exécution de processus
     */
    private function logExecution(string $type, string $period, string $status, array $details = []): void
    {
        // Cette méthode pourrait enregistrer dans une table dédiée
        // Pour l'instant, on utilise les logs Laravel
        Log::info("Process execution logged", [
            'type' => $type,
            'period' => $period,
            'status' => $status,
            'user_id' => Auth::id(),
            'details' => $details
        ]);
    }

    /**
     * API endpoint pour obtenir les stats via AJAX
     */
    public function apiStats()
    {
        return response()->json($this->getSystemStats());
    }
}
