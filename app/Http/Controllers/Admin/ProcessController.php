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

        return view('admin.processes.index', compact('availablePeriods', 'currentPeriod', 'stats'));
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
            'audit_only' => 'nullable|boolean'
        ]);

        $period = $request->input('period');
        $auditOnly = $request->boolean('audit_only');

        try {
            // Capturer la sortie de la commande
            $output = new BufferedOutput();

            // Exécuter la commande
            $exitCode = Artisan::call('app:regularize-grades', [
                'period' => $period,
                '--audit-only' => $auditOnly,
                '--force' => true
            ], $output);

            $commandOutput = $output->fetch();

            if ($exitCode === 0) {
                $message = $auditOnly
                    ? "Audit des grades terminé pour {$period}"
                    : "Régularisation des grades appliquée pour {$period}";

                Log::info("Processus de régularisation exécuté via interface web", [
                    'period' => $period,
                    'audit_only' => $auditOnly,
                    'exit_code' => $exitCode,
                    'user_id' => Auth::id()
                ]);

                return redirect()->route('admin.processes.index')
                    ->with('success', $message)
                    ->with('command_output', $commandOutput);
            } else {
                Log::error("Erreur lors de la régularisation", [
                    'period' => $period,
                    'audit_only' => $auditOnly,
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
            // Cette logique dépend du format de sortie de votre commande ProcessAdvancements
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
                        'command_output_extract' => $avancement['details'] ?? null
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
     */
    private function parseAdvancementsFromOutput(string $output): array
    {
        $avancements = [];

        // Cette méthode doit être adaptée selon le format exact de sortie de votre commande
        // Exemple de parsing basique - à adapter selon le format réel
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Exemple: chercher des lignes contenant des promotions
            if (preg_match('/Promotion.*ID:(\d+).*Grade:(\d+)->(\d+)/', $line, $matches)) {
                $avancements[] = [
                    'distributeur_id' => (int)$matches[1],
                    'ancien_grade' => (int)$matches[2],
                    'nouveau_grade' => (int)$matches[3],
                    'details' => trim($line)
                ];
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
                'total_distributeurs' => \App\Models\Distributeur::count(),
                'distributeurs_actifs_mois' => \App\Models\Distributeur::whereHas('achats', function($query) {
                    $query->where('period', date('Y-m'));
                })->count(),
                'total_achats_mois' => Achat::where('period', date('Y-m'))->count(),
                'total_bonus_mois' => \App\Models\Bonus::where('period', date('Y-m'))->count(),
                'periodes_disponibles' => Achat::select('period')->distinct()->count(),
                'dernier_traitement' => \App\Models\LevelCurrent::latest('updated_at')->value('updated_at')
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
                'dernier_traitement' => null
            ];
        }
    }

    /**
     * API endpoint pour obtenir les stats via AJAX
     */
    public function apiStats()
    {
        return response()->json($this->getSystemStats());
    }
}
