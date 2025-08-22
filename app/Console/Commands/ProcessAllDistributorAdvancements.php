<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use App\Models\AvancementHistory;
use App\Models\TempGradeCalculation;
use App\Services\EternalHelperLegacyMatriculeDB;
use App\Services\DistributorLineageServiceAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessAllDistributorAdvancements extends Command
{
    protected $signature = 'app:process-advancements {period? : The period to process in YYYY-MM format. Defaults to current month.}
                                                    {--matricule= : Process only a single distributor for debugging (no DB updates will be made).}
                                                    {--force : Skip confirmation in batch mode.}
                                                    {--max-iterations=10 : Maximum number of calculation passes.}
                                                    {--batch-size=1000 : Process distributors in batches}
                                                    {--dry-run : Show what would be changed without applying}
                                                    {--validated-only : Process only distributors who have made purchases in the period}
                                                    {--export= : Export promotions to CSV file}
                                                    {--show-details : Show detailed eligibility information}
                                                    {--limit= : Limit the number of distributors to process (for testing)}
                                                    {--min-grade= : Process only distributors with minimum grade}
                                                    {--max-grade= : Process only distributors with maximum grade}
                                                    {--keep-temp : Keep temporary calculation table after processing}';

    protected $description = 'Calculates and applies grade advancements for all distributors iteratively using new business rules with temporary calculation table.';

    private EternalHelperLegacyMatriculeDB $branchQualifier;
    private DistributorLineageServiceAdapter $lineageService;
    private string $calculationSessionId;

    public function __construct(
        EternalHelperLegacyMatriculeDB $branchQualifier,
        DistributorLineageServiceAdapter $lineageService
    )
    {
        parent::__construct();
        $this->branchQualifier = $branchQualifier;
        $this->lineageService = $lineageService;
    }

    public function handle(): int
    {
        $matriculeToDebug = $this->option('matricule');

        if ($matriculeToDebug) {
            return $this->handleSingleDistributorDebug($matriculeToDebug);
        } else {
            return $this->handleBatchProcessingWithTempTable();
        }
    }

    /**
     * Gère le flux d'analyse pour un seul distributeur (mode debug)
     */
    private function handleSingleDistributorDebug(string $matricule): int
    {
        $period = $this->argument('period') ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Invalid period format. Use YYYY-MM format.");
            return self::FAILURE;
        }

        $this->info("--- DEBUG MODE for Matricule <fg=yellow>{$matricule}</> for Period <fg=yellow>{$period}</> ---");

        try {
            // Utiliser le service sans table temporaire pour le debug
            $eligibility = $this->lineageService->checkGradeEligibility($matricule, $period, [
                'include_details' => true,
                'stop_on_first_failure' => false,
                'check_all_possible' => true
            ]);

            if (isset($eligibility['error'])) {
                $this->error($eligibility['error']);
                return self::FAILURE;
            }

            // Afficher les informations actuelles
            $this->info("Distributor found: <fg=cyan>{$eligibility['distributor']['nom']} {$eligibility['distributor']['prenom']}</>");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Matricule', $eligibility['distributor']['matricule']],
                    ['Internal ID', $eligibility['distributor']['id']],
                    ['Current Grade', $eligibility['distributor']['current_grade']],
                    ['Cumul Individuel', number_format($eligibility['distributor']['cumul_individuel'], 2)],
                    ['Cumul Collectif', number_format($eligibility['distributor']['cumul_collectif'], 2)],
                ]
            );

            // Afficher l'analyse d'éligibilité
            $this->line("\n--- ELIGIBILITY ANALYSIS ---");

            if ($eligibility['can_advance']) {
                $this->info("✓ Eligible for advancement to grade <fg=green>{$eligibility['max_achievable_grade']}</>");

                // Afficher les détails de qualification
                foreach ($eligibility['eligibilities'] as $grade => $details) {
                    if (isset($details['skipped'])) {
                        $this->line("\nGrade {$grade}: <fg=gray>SKIPPED</> (previous grade failed)");
                        continue;
                    }

                    if ($details['eligible']) {
                        $this->line("\nGrade {$grade}: <fg=green>ELIGIBLE</>");
                        foreach ($details['qualified_options'] as $option) {
                            $this->info("  ✓ Qualified by: {$option['description']}");
                        }
                    } else {
                        $this->line("\nGrade {$grade}: <fg=red>NOT ELIGIBLE</>");
                        if ($this->option('show-details') && isset($details['all_options'])) {
                            foreach ($details['all_options'] as $option) {
                                $this->warn("  Option {$option['option']}: {$option['description']}");
                                if (isset($option['status'])) {
                                    foreach ($option['status'] as $req) {
                                        $status = $req['met'] ? '✓' : '✗';
                                        $color = $req['met'] ? 'green' : 'red';
                                        $this->line("    [{$status}] {$req['requirement']}: <fg={$color}>{$req['actual']}/{$req['required']}</>");
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $this->warn("✗ Not eligible for any advancement");
            }

        } catch (\Exception $e) {
            $this->error("Error during analysis: " . $e->getMessage());
            Log::error("Debug mode error", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Traitement par lot avec table temporaire
     */
    private function handleBatchProcessingWithTempTable(): int
    {
        $period = $this->argument('period') ?? date('Y-m');
        $validatedOnly = $this->option('validated-only');

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Invalid period format. Use YYYY-MM format.");
            return self::FAILURE;
        }

        $this->info("Starting advancement process for period: <fg=yellow>{$period}</> using temporary calculation table");

        if ($validatedOnly) {
            $this->info("🛒 <fg=cyan>VALIDATED-ONLY MODE:</fg> Processing only distributors with validated purchases");
        }

        if ($this->option('dry-run')) {
            $this->warn('⚠️  <fg=yellow>DRY RUN MODE:</fg> No changes will be applied to main tables');
        }

        if (!$this->option('force') && !$this->option('dry-run') &&
            !$this->confirm("This will calculate distributor grade advancements for period {$period}. Continue?", false)) {
            $this->comment('Operation cancelled.');
            return self::FAILURE;
        }

        // Générer un ID de session unique
        $this->calculationSessionId = 'CALC_' . date('YmdHis') . '_' . Str::random(8);
        $this->info("Calculation session ID: <fg=cyan>{$this->calculationSessionId}</>");

        try {
            // 1. Initialiser (juste la structure, pas de données)
            $this->initializeTempTable($period);

            // 2. Configurer le service pour utiliser la table temporaire
            $this->lineageService->setCalculationSession($this->calculationSessionId);

            // 3. Traiter les avancements
            $totalPromotions = $this->processAdvancementsIteratively($period);

            // 4. Afficher les résultats
            $this->displayResults($totalPromotions);

            // 5. Exporter si demandé
            if ($export = $this->option('export')) {
                $this->exportResults($export);
            }

            // 6. Appliquer les changements si pas en dry-run
            if (!$this->option('dry-run')) {
                if ($totalPromotions === 0) {
                    $this->info("No promotions to apply.");
                } else {
                    if ($this->option('force') || $this->confirm("Apply {$totalPromotions} promotions to main tables?", true)) {
                        $this->applyPromotionsFromTemp($period);
                    }
                }
            } else {
                $this->info("\nDRY RUN completed. No changes applied to main tables.");
                $this->info("Review the temporary data with session ID: {$this->calculationSessionId}");
            }

            // 7. Nettoyer la table temporaire (sauf si --keep-temp)
            if (!$this->option('keep-temp') && !$this->option('dry-run')) {
                $this->cleanupTempTable();
            } else {
                $this->info("\nTemporary data kept in table with session ID: {$this->calculationSessionId}");
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Advancement process failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'session_id' => $this->calculationSessionId
            ]);

            // Nettoyer en cas d'erreur
            $this->cleanupTempTable();

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Initialise la table temporaire UNIQUEMENT avec la structure (pas de données)
     */
    private function initializeTempTable(string $period): void
    {
        $this->info("Initializing temporary calculation table structure...");

        // Vérifier que la période existe
        $count = DB::table('level_currents')
            ->where('period', $period)
            ->count();

        if ($count === 0) {
            throw new \Exception("No data found for period {$period}");
        }

        $this->info("Ready to process {$count} distributors from level_currents");

        // La table temporaire est vide au départ, on n'insère que les promotions
    }

    /**
     * Traite les avancements de manière itérative
     */
    private function processAdvancementsIteratively(string $period): int
    {
        $maxIterations = (int) $this->option('max-iterations');
        $batchSize = (int) $this->option('batch-size');
        $passNumber = 0;
        $totalPromotions = 0;

        $this->info("\nStarting iterative advancement calculation...");

        do {
            $passNumber++;
            $promotionsInThisPass = 0;

            $this->info("\n--- Pass #{$passNumber} ---");

            // Récupérer tous les distributeurs de la session
            $distributors = TempGradeCalculation::forSession($this->calculationSessionId)
                ->orderBy('grade_actuel') // Traiter d'abord les grades les plus bas
                ->get();

            $progressBar = $this->output->createProgressBar($distributors->count());
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% | %message%');
            $progressBar->start();

            // Traiter par batches
            foreach ($distributors->chunk($batchSize) as $batch) {
                foreach ($batch as $tempCalc) {
                    $progressBar->setMessage("Checking {$tempCalc->matricule} (Grade {$tempCalc->grade_actuel})");

                    // Skip si déjà au grade max
                    if ($tempCalc->grade_actuel >= 11) {
                        $progressBar->advance();
                        continue;
                    }

                    // Vérifier l'éligibilité
                    $eligibility = $this->lineageService->checkGradeEligibility($tempCalc->matricule, $period, [
                        'target_grade' => $tempCalc->grade_actuel + 1,
                        'include_details' => true,
                        'stop_on_first_failure' => true,
                        'use_cache' => false
                    ]);

                    if (!isset($eligibility['error']) && $eligibility['can_advance']) {
                        $newGrade = $eligibility['max_achievable_grade'];

                        if ($newGrade > $tempCalc->grade_actuel) {
                            // Obtenir la méthode de qualification
                            $qualification = 'N/A';
                            if (isset($eligibility['eligibilities'][$newGrade]['qualified_options'][0])) {
                                $qualification = $eligibility['eligibilities'][$newGrade]['qualified_options'][0]['description'];
                            }

                            // Mettre à jour dans la table temporaire
                            $tempCalc->grade_precedent = $tempCalc->grade_actuel;
                            $tempCalc->grade_actuel = $newGrade;
                            $tempCalc->pass_number = $passNumber;
                            $tempCalc->qualification_method = $qualification;
                            $tempCalc->promoted = true;

                            // Ajouter à l'historique
                            $tempCalc->addPromotionToHistory(
                                $tempCalc->grade_precedent,
                                $newGrade,
                                $passNumber,
                                $qualification
                            );

                            $tempCalc->save();

                            $promotionsInThisPass++;
                            $totalPromotions++;
                        }
                    }

                    $progressBar->advance();
                }
            }

            $progressBar->finish();
            $this->newLine();
            $this->info("Pass #{$passNumber} completed: {$promotionsInThisPass} promotions found");

            // Vérifier si on doit continuer
            if ($passNumber >= $maxIterations) {
                $this->warn("Maximum iterations ({$maxIterations}) reached. Stopping.");
                break;
            }

            if ($promotionsInThisPass === 0) {
                $this->info("No more promotions found. Process stabilized.");
                break;
            }

        } while (true);

        $this->info("\nCalculation completed after {$passNumber} passes.");
        $this->info("Total promotions found: {$totalPromotions}");

        return $totalPromotions;
    }

    /**
     * Affiche les résultats depuis la table temporaire
     */
    private function displayResults(int $totalPromotions): void
    {
        if ($totalPromotions === 0) {
            $this->info("\nNo promotions detected.");
            return;
        }

        $this->info("\n=== PROMOTIONS SUMMARY ===");

        // Résumé par grade de destination
        $byGrade = TempGradeCalculation::forSession($this->calculationSessionId)
            ->where('promoted', true)
            ->selectRaw('grade_actuel as grade, COUNT(*) as count')
            ->groupBy('grade_actuel')
            ->orderBy('grade_actuel')
            ->get();

        $this->table(
            ['To Grade', 'Count'],
            $byGrade->map(function($row) {
                return [$row->grade, $row->count];
            })
        );

        // Résumé par passe
        $byPass = TempGradeCalculation::forSession($this->calculationSessionId)
            ->where('promoted', true)
            ->selectRaw('pass_number, COUNT(*) as count')
            ->groupBy('pass_number')
            ->orderBy('pass_number')
            ->get();

        $this->info("\n=== PROMOTIONS BY PASS ===");
        $this->table(
            ['Pass', 'Count'],
            $byPass->map(function($row) {
                return [$row->pass_number, $row->count];
            })
        );

        if ($this->option('show-details')) {
            $this->info("\n=== DETAILED PROMOTIONS ===");

            $promotions = TempGradeCalculation::forSession($this->calculationSessionId)
                ->where('promoted', true)
                ->with('distributeur')
                ->orderBy('pass_number')
                ->orderBy('grade_actuel', 'desc')
                ->get();

            $headers = ['Matricule', 'Name', 'Initial', 'Final', 'Passes', 'Last Method'];
            $rows = $promotions->map(function($p) {
                $passes = collect($p->promotion_history)->pluck('pass')->implode(',');
                return [
                    $p->matricule,
                    $p->distributeur->nom_distributeur . ' ' . $p->distributeur->pnom_distributeur,
                    $p->grade_initial,
                    $p->grade_actuel,
                    $passes,
                    Str::limit($p->qualification_method ?? 'N/A', 40)
                ];
            });

            $this->table($headers, $rows);
        }
    }

    /**
     * Applique les promotions depuis la table temporaire vers les tables principales
     */
    private function applyPromotionsFromTemp(string $period): void
    {
        $this->info("\nApplying promotions to main tables...");

        $promotions = TempGradeCalculation::forSession($this->calculationSessionId)
            ->where('promoted', true)
            ->get();

        $progressBar = $this->output->createProgressBar($promotions->count());

        DB::transaction(function() use ($promotions, $period, $progressBar) {
            foreach ($promotions as $tempCalc) {
                // 1. Mettre à jour table distributeurs
                Distributeur::where('distributeur_id', $tempCalc->matricule)
                          ->update(['etoiles_id' => $tempCalc->grade_actuel]);

                // 2. Mettre à jour table level_currents
                DB::table('level_currents')
                    ->where('id', $tempCalc->level_current_id)
                    ->update(['etoiles' => $tempCalc->grade_actuel]);

                // 3. Créer entrées dans avancement_history pour chaque promotion
                foreach ($tempCalc->promotion_history as $promo) {
                    AvancementHistory::create([
                        'distributeur_id' => $tempCalc->distributeur_id,
                        'period' => $period,
                        'ancien_grade' => $promo['from'],
                        'nouveau_grade' => $promo['to'],
                        'type_calcul' => $this->option('validated-only') ? 'validated_only' : 'all',
                        'details' => json_encode([
                            'matricule' => $tempCalc->matricule,
                            'cumul_individuel' => $tempCalc->cumul_individuel,
                            'cumul_collectif' => $tempCalc->cumul_collectif,
                            'pass' => $promo['pass'],
                            'qualification_method' => $promo['method'],
                            'calculated_by' => 'ProcessAllDistributorAdvancements_v3',
                            'calculation_session_id' => $this->calculationSessionId,
                            'calculated_at' => $promo['timestamp']
                        ])
                    ]);
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();
        $this->info("All promotions applied successfully!");

        // Log summary
        Log::info("Advancements processed with temporary table", [
            'period' => $period,
            'session_id' => $this->calculationSessionId,
            'total_promotions' => $promotions->count(),
            'validated_only' => $this->option('validated-only')
        ]);
    }

    /**
     * Exporte les résultats
     */
    private function exportResults(string $filename): void
    {
        $csv = fopen($filename, 'w');

        // Headers
        fputcsv($csv, [
            'Matricule',
            'Nom',
            'Grade Initial',
            'Grade Final',
            'Nombre de Promotions',
            'Passes',
            'Cumul Individuel',
            'Cumul Collectif',
            'Dernière Méthode de Qualification'
        ]);

        $promotions = TempGradeCalculation::forSession($this->calculationSessionId)
            ->where('promoted', true)
            ->with('distributeur')
            ->get();

        foreach ($promotions as $promo) {
            $passes = collect($promo->promotion_history)->pluck('pass')->implode(',');

            fputcsv($csv, [
                $promo->matricule,
                $promo->distributeur->nom_distributeur . ' ' . $promo->distributeur->pnom_distributeur,
                $promo->grade_initial,
                $promo->grade_actuel,
                count($promo->promotion_history),
                $passes,
                $promo->cumul_individuel,
                $promo->cumul_collectif,
                $promo->qualification_method ?? 'N/A'
            ]);
        }

        fclose($csv);
        $this->info("Results exported to: {$filename}");
    }

    /**
     * Nettoie la table temporaire
     */
    private function cleanupTempTable(): void
    {
        $this->info("Cleaning up temporary data...");

        TempGradeCalculation::forSession($this->calculationSessionId)->delete();

        $this->info("Temporary data cleaned.");
    }
}
