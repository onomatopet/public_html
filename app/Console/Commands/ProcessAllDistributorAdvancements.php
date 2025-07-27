<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LevelCurrent; // Changé de LevelCurrentTest
use App\Models\Distributeur;
use App\Services\EternalHelperLegacyMatriculeDB;
use App\Services\GradeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAllDistributorAdvancements extends Command
{
    protected $signature = 'app:process-advancements {period? : The period to process in YYYY-MM format. Defaults to current month.}
                                                    {--matricule= : Process only a single distributor for debugging (no DB updates will be made).}
                                                    {--force : Skip confirmation in batch mode.}
                                                    {--max-iterations=10 : Maximum number of calculation passes.}
                                                    {--batch-size=1000 : Process distributors in batches}
                                                    {--dry-run : Show what would be changed without applying}
                                                    {--validated-only : Process only distributors who have made purchases in the period}
                                                    {--export= : Export promotions to CSV file}';

    protected $description = 'Calculates and applies grade advancements for all distributors iteratively, with optimizations.';

    private EternalHelperLegacyMatriculeDB $branchQualifier;
    private GradeCalculator $gradeCalculator;

    public function __construct(EternalHelperLegacyMatriculeDB $branchQualifier, GradeCalculator $gradeCalculator)
    {
        parent::__construct();
        $this->branchQualifier = $branchQualifier;
        $this->gradeCalculator = $gradeCalculator;
    }

    public function handle(): int
    {
        $matriculeToDebug = $this->option('matricule');

        if ($matriculeToDebug) {
            return $this->handleSingleDistributorDebug($matriculeToDebug);
        } else {
            return $this->handleBatchProcessingOptimized();
        }
    }

    /**
     * Gère le flux d'analyse pour un seul distributeur.
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
            $this->line('Pre-loading all distributor data...');
            $this->branchQualifier->loadAndBuildMaps();
        } catch (\Exception $e) {
             $this->error("CRITICAL ERROR during data loading: " . $e->getMessage());
             return self::FAILURE;
        }

        // CORRECTION : Chercher par matricule dans la table distributeurs, puis récupérer l'ID
        $distributeur = Distributeur::where('distributeur_id', $matricule)->first();
        if (!$distributeur) {
            $this->error("No distributor found with matricule {$matricule}.");
            return self::FAILURE;
        }

        $levelEntry = LevelCurrent::where('distributeur_id', $distributeur->id) // Utiliser l'ID, pas le matricule
                                  ->where('period', $period)
                                  ->first();

        if (!$levelEntry) {
            $this->error("No LevelCurrent entry found for matricule {$matricule} (ID: {$distributeur->id}) in period {$period}.");
            return self::FAILURE;
        }

        $this->info("Distributor found. Current Grade: <fg=cyan>{$levelEntry->etoiles}</>");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Matricule', $matricule],
                ['Internal ID', $distributeur->id],
                ['Current Grade (Etoiles)', $levelEntry->etoiles],
                ['Cumulative Individual', number_format($levelEntry->cumul_individuel)],
                ['Cumulative Collective', number_format($levelEntry->cumul_collectif)],
            ]
        );

        $this->line("\nCalculating potential grade...");

        // Calcul itératif pour ce distributeur
        $currentGrade = $levelEntry->etoiles;
        $iterations = 0;
        $maxIterations = 10;

        while ($iterations < $maxIterations) {
            $newPotentialLevel = $this->gradeCalculator->calculatePotentialGrade(
                $currentGrade,
                (float)$levelEntry->cumul_individuel,
                (float)$levelEntry->cumul_collectif,
                $matricule, // Le service utilise probablement encore le matricule
                $this->branchQualifier
            );

            if ($newPotentialLevel <= $currentGrade) {
                break;
            }

            $this->info("Iteration {$iterations}: Grade {$currentGrade} → {$newPotentialLevel}");
            $currentGrade = $newPotentialLevel;
            $iterations++;
        }

        $this->line("\n--- DEBUG RESULT ---");
        if ($currentGrade > $levelEntry->etoiles) {
            $this->info("Final Calculated Grade: <fg=green>{$currentGrade}</>");
            $this->info("Conclusion: This distributor SHOULD BE PROMOTED from {$levelEntry->etoiles} to {$currentGrade}.");
        } else {
            $this->info("Final Calculated Grade: <fg=yellow>{$currentGrade}</>");
            $this->info("Conclusion: This distributor should be maintained at their current grade.");
        }

        return self::SUCCESS;
    }

    /**
     * Version optimisée du traitement par lot
     */
    private function handleBatchProcessingOptimized(): int
    {
        $period = $this->argument('period') ?? date('Y-m');
        $validatedOnly = $this->option('validated-only');

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error("Invalid period format. Use YYYY-MM format.");
            return self::FAILURE;
        }

        $this->info("Starting OPTIMIZED iterative advancement process for period: <fg=yellow>{$period}</>");

        if ($validatedOnly) {
            $this->info("🛒 <fg=cyan>VALIDATED-ONLY MODE:</fg> Processing only distributors with purchases in period {$period}");
        }

        if ($this->option('dry-run')) {
            $this->warn('⚠️  <fg=yellow>DRY RUN MODE:</fg> No database changes will be applied');
        }

        if (!$this->option('force') && !$this->option('dry-run') &&
            !$this->confirm("This will update distributor grades for period {$period}. Continue?", false)) {
            $this->comment('Operation cancelled.');
            return self::FAILURE;
        }

        try {
            // 1. Charger toutes les données
            $startTime = microtime(true);
            $this->line('Loading and organizing all data...');

            $this->branchQualifier->loadAndBuildMaps();

            // Charger les données avec tri par hiérarchie ET filtrage optionnel
            $levelData = $this->loadDataWithHierarchy($period, $validatedOnly);

            if ($levelData->isEmpty()) {
                $this->warn("No data found for period {$period}" . ($validatedOnly ? " with validated purchases" : "") . ".");
                return self::SUCCESS;
            }

            $loadTime = round(microtime(true) - $startTime, 2);
            $this->info("Loaded {$levelData->count()} distributors in {$loadTime}s" . ($validatedOnly ? " (validated purchases only)" : ""));

            // 2. Traitement optimisé
            $allPromotions = $this->processPromotionsOptimized($levelData);

            // 3. Afficher les résultats
            $this->displayPromotions($allPromotions);

            if ($export = $this->option('export')) {
                $this->exportPromotions($allPromotions, $export);
            }

            if ($this->option('dry-run')) {
                $this->info("\nDRY RUN: No changes applied to database.");
                return self::SUCCESS;
            }

            if ($allPromotions->isEmpty()) {
                $this->info("No promotions to apply.");
                return self::SUCCESS;
            }

            if (!$this->option('force') && !$this->confirm("Apply {$allPromotions->count()} promotions?", true)) {
                return self::SUCCESS;
            }

            // 4. Appliquer les changements
            $this->applyPromotions($allPromotions, $period);

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Advancement process failed", ['error' => $e]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Charge les données avec information de hiérarchie et filtrage optionnel
     * CORRECTION MAJEURE : Adaptation aux nouvelles structures ID/matricule
     */
    private function loadDataWithHierarchy(string $period, bool $validatedOnly = false)
    {
        // CORRECTION : Utiliser la bonne table et les bonnes relations
        $query = DB::table('level_currents as l') // Changé de level_current_test
            ->join('distributeurs as d', 'l.distributeur_id', '=', 'd.id') // CORRECTION : l.distributeur_id (ID) = d.id (ID)
            ->where('l.period', $period);

        if ($validatedOnly) {
            // CORRECTION : Jointure correcte avec achats (utiliser les IDs, pas les matricules)
            $query->join('achats as a', function($join) use ($period) {
                $join->on('d.id', '=', 'a.distributeur_id') // CORRECTION : Les deux sont des IDs maintenant
                     ->where('a.period', '=', $period); // CORRECTION : Utiliser = au lieu de where pour éviter les erreurs SQL
            });

            // Grouper pour éviter les doublons (un distributeur peut avoir plusieurs achats)
            $query->groupBy('l.distributeur_id', 'l.etoiles', 'l.cumul_individuel', 'l.cumul_collectif', 'd.id_distrib_parent', 'd.distributeur_id');
        }

        return $query->select(
                'l.distributeur_id as internal_id', // ID interne pour les calculs
                'd.distributeur_id as matricule',   // Matricule pour les services legacy
                'l.etoiles',
                'l.cumul_individuel',
                'l.cumul_collectif',
                'd.id_distrib_parent'
            )
            ->get()
            ->keyBy('matricule'); // CORRECTION : Indexer par matricule pour compatibilité avec les services
    }

    /**
     * Traitement optimisé des promotions
     * CORRECTION : Adapter aux nouvelles structures de données
     */
    private function processPromotionsOptimized($levelData)
    {
        $allPromotions = collect();
        $maxIterations = (int) $this->option('max-iterations');
        $passCounter = 0;

        // Créer un ordre de traitement optimal (feuilles vers racines)
        $processingOrder = $this->createOptimalProcessingOrder($levelData);

        $this->info("Starting iterative calculation with optimized order...");

        do {
            $promotionsInThisPass = collect();
            $passCounter++;

            $this->info("--- Pass #{$passCounter} ---");
            $progressBar = $this->output->createProgressBar(count($processingOrder));
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $progressBar->start();

            // Traiter dans l'ordre optimal
            foreach ($processingOrder as $matricule) {
                if (!isset($levelData[$matricule])) continue;

                $levelEntry = $levelData[$matricule];
                $currentGrade = $allPromotions->has($matricule)
                    ? $allPromotions[$matricule]['to']
                    : $levelEntry->etoiles;

                $progressBar->setMessage("Checking {$matricule} (Grade {$currentGrade})");

                // Calculer le nouveau grade potentiel
                $newGrade = $this->calculateOptimalGrade(
                    $matricule, // Utiliser le matricule pour les services
                    $currentGrade,
                    $levelEntry->cumul_individuel,
                    $levelEntry->cumul_collectif
                );

                if ($newGrade > $currentGrade) {
                    $promotionsInThisPass[$matricule] = [
                        'internal_id' => $levelEntry->internal_id, // AJOUT : Stocker l'ID interne
                        'matricule' => $matricule,
                        'from' => $levelEntry->etoiles,
                        'to' => $newGrade,
                        'pass' => $passCounter,
                        'cumul_individuel' => $levelEntry->cumul_individuel,
                        'cumul_collectif' => $levelEntry->cumul_collectif
                    ];

                    // Mettre à jour dans la map pour les calculs suivants
                    $this->branchQualifier->updateNodeLevelInMap($matricule, $newGrade);

                    // Mettre à jour la collection globale
                    $allPromotions[$matricule] = $promotionsInThisPass[$matricule];
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();
            $this->info("Pass #{$passCounter}: {$promotionsInThisPass->count()} promotions");

            if ($passCounter >= $maxIterations) {
                $this->warn("Maximum iterations reached. Stopping.");
                break;
            }

        } while ($promotionsInThisPass->isNotEmpty());

        $this->info("Stabilized after {$passCounter} passes.");

        return $allPromotions;
    }

    /**
     * Crée un ordre de traitement optimal (bottom-up)
     */
    private function createOptimalProcessingOrder($levelData): array
    {
        // Construire un graphe des dépendances
        $children = [];
        $depths = [];

        foreach ($levelData as $matricule => $data) {
            $parent = $data->id_distrib_parent;
            if ($parent && $parent != 0) {
                // CORRECTION : Trouver le matricule du parent à partir de son ID
                $parentMatricule = null;
                foreach ($levelData as $checkMatricule => $checkData) {
                    if ($checkData->internal_id == $parent) {
                        $parentMatricule = $checkMatricule;
                        break;
                    }
                }

                if ($parentMatricule) {
                    if (!isset($children[$parentMatricule])) {
                        $children[$parentMatricule] = [];
                    }
                    $children[$parentMatricule][] = $matricule;
                }
            }
        }

        // Calculer la profondeur de chaque nœud
        $calculateDepth = function($matricule) use (&$calculateDepth, &$depths, $children) {
            if (isset($depths[$matricule])) {
                return $depths[$matricule];
            }

            $maxChildDepth = 0;
            if (isset($children[$matricule])) {
                foreach ($children[$matricule] as $child) {
                    $maxChildDepth = max($maxChildDepth, $calculateDepth($child) + 1);
                }
            }

            $depths[$matricule] = $maxChildDepth;
            return $maxChildDepth;
        };

        foreach ($levelData as $matricule => $data) {
            $calculateDepth($matricule);
        }

        // Trier par profondeur (feuilles en premier)
        $order = array_keys($depths);
        usort($order, function($a, $b) use ($depths) {
            return $depths[$a] <=> $depths[$b];
        });

        return $order;
    }

    /**
     * Calcule le grade optimal avec optimisations
     */
    private function calculateOptimalGrade($matricule, $currentGrade, $cumulInd, $cumulCol): int
    {
        // Calcul itératif jusqu'à stabilisation
        $calculatedGrade = $currentGrade;
        $iterations = 0;
        $maxIterations = 5; // Limite pour éviter les boucles infinies

        while ($iterations < $maxIterations) {
            $newGrade = $this->gradeCalculator->calculatePotentialGrade(
                $calculatedGrade,
                (float)$cumulInd,
                (float)$cumulCol,
                $matricule, // Utiliser le matricule pour les services legacy
                $this->branchQualifier
            );

            if ($newGrade <= $calculatedGrade) {
                break;
            }

            $calculatedGrade = $newGrade;
            $iterations++;
        }

        return $calculatedGrade;
    }

    /**
     * Affiche les promotions
     */
    private function displayPromotions($promotions): void
    {
        if ($promotions->isEmpty()) {
            $this->info("\nNo promotions detected.");
            return;
        }

        $this->info("\n=== PROMOTIONS SUMMARY ===");
        $this->info("Total promotions: " . $promotions->count());

        // Grouper par pass
        $byPass = $promotions->groupBy('pass');
        foreach ($byPass as $pass => $passPromotions) {
            $this->info("Pass #{$pass}: " . $passPromotions->count() . " promotions");
        }

        // Statistiques par changement de grade
        $this->info("\n=== PROMOTIONS BY GRADE CHANGE ===");
        $byChange = [];
        foreach ($promotions as $matricule => $promo) {
            $key = "{$promo['from']} → {$promo['to']}";
            if (!isset($byChange[$key])) {
                $byChange[$key] = 0;
            }
            $byChange[$key]++;
        }

        arsort($byChange);

        $this->table(
            ['Grade Change', 'Count'],
            collect($byChange)->map(fn($count, $change) => [$change, $count])->take(10)->all()
        );

        // Afficher quelques exemples
        $this->info("\n=== SAMPLE PROMOTIONS ===");
        $this->table(
            ['Matricule', 'From', 'To', 'Pass', 'Cumul Ind.', 'Cumul Col.'],
            $promotions->take(20)->map(fn($p, $m) => [
                $m,
                $p['from'],
                $p['to'],
                $p['pass'],
                number_format($p['cumul_individuel']),
                number_format($p['cumul_collectif'])
            ])->all()
        );

        if ($promotions->count() > 20) {
            $this->info("... and " . ($promotions->count() - 20) . " more promotions");
        }
    }

    /**
     * Exporte les promotions en CSV
     */
    private function exportPromotions($promotions, $filename): void
    {
        $handle = fopen($filename, 'w');

        fputcsv($handle, ['Matricule', 'From Grade', 'To Grade', 'Pass', 'Cumul Individuel', 'Cumul Collectif']);

        foreach ($promotions as $matricule => $promo) {
            fputcsv($handle, [
                $matricule,
                $promo['from'],
                $promo['to'],
                $promo['pass'],
                $promo['cumul_individuel'],
                $promo['cumul_collectif']
            ]);
        }

        fclose($handle);
        $this->info("Promotions exported to: {$filename}");
    }

    /**
     * Applique les promotions en base de données
     * CORRECTION MAJEURE : Utiliser les bons IDs/matricules pour les mises à jour
     */
    private function applyPromotions($promotions, $period): void
    {
        $batchSize = (int) $this->option('batch-size');

        $this->info("\nApplying promotions to database...");
        $progressBar = $this->output->createProgressBar($promotions->count());

        DB::beginTransaction();
        try {
            $promotions->chunk($batchSize)->each(function($chunk) use ($period, &$progressBar) {
                // Préparer les mises à jour batch
                $distributeurUpdates = [];
                $levelUpdates = [];

                foreach ($chunk as $matricule => $promo) {
                    // CORRECTION : Mise à jour distributeur par matricule
                    $distributeurUpdates[] = [
                        'distributeur_id' => $matricule, // Utiliser le matricule pour identifier
                        'etoiles_id' => $promo['to']
                    ];

                    // CORRECTION : Mise à jour level_currents par ID interne
                    $levelUpdates[] = [
                        'distributeur_id' => $promo['internal_id'], // Utiliser l'ID interne
                        'period' => $period,
                        'etoiles' => $promo['to']
                    ];

                    $progressBar->advance();
                }

                // Batch update pour distributeurs (par matricule)
                if (!empty($distributeurUpdates)) {
                    foreach ($distributeurUpdates as $update) {
                        Distributeur::where('distributeur_id', $update['distributeur_id'])
                                  ->update(['etoiles_id' => $update['etoiles_id']]);
                    }

                    // Batch update pour level_currents (par ID)
                    DB::table('level_currents')->upsert(
                        $levelUpdates,
                        ['distributeur_id', 'period'],
                        ['etoiles']
                    );
                }
            });

            DB::commit();
            $progressBar->finish();
            $this->newLine();
            $this->info("All promotions applied successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
