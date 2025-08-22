<?php

namespace App\Console\Commands;

use App\Models\Distributeur;
use App\Models\TempGradeCalculation;
use App\Services\DistributorLineageServiceAdapter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessAdvancementsCommand extends Command
{
    protected $signature = 'app:process-advancements
                            {period : Period in YYYY-MM format}
                            {--dry-run : Simulate without applying changes}
                            {--show-details : Show detailed progress}
                            {--limit=0 : Limit number of distributors to process}
                            {--offset=0 : Skip first N distributors}
                            {--matricule= : Process specific distributor}
                            {--min-grade=1 : Minimum grade to process}
                            {--max-grade=11 : Maximum grade to process}
                            {--include-non-validated : Include distributors with statut_validation_periode = 0}
                            {--cleanup : Remove temporary data after processing}';

    protected $description = 'Process grade advancements for distributors using temporary calculation table';

    private DistributorLineageServiceAdapter $lineageService;
    private string $calculationSessionId;
    private int $passNumber = 0;
    private array $promotionStats = [];
    private bool $showDetails;

    public function __construct(DistributorLineageServiceAdapter $lineageService)
    {
        parent::__construct();
        $this->lineageService = $lineageService;
    }

    public function handle()
    {
        $period = $this->argument('period');
        $isDryRun = $this->option('dry-run');
        $this->showDetails = $this->option('show-details');
        $matricule = $this->option('matricule');

        Log::info("ProcessAdvancementsCommand started", [
            'period' => $period,
            'dry_run' => $isDryRun,
            'show_details' => $this->showDetails,
            'matricule' => $matricule,
            'options' => [
                'limit' => $this->option('limit'),
                'offset' => $this->option('offset'),
                'min_grade' => $this->option('min-grade'),
                'max_grade' => $this->option('max-grade'),
                'include_non_validated' => $this->option('include-non-validated'),
                'cleanup' => $this->option('cleanup')
            ]
        ]);

        // Validate period format
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            Log::error("Invalid period format", ['period' => $period]);
            $this->error('Invalid period format. Please use YYYY-MM');
            return 1;
        }

        $this->info("Starting advancement process for period: {$period} using temporary calculation table");

        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN MODE: No changes will be applied to main tables');
        }

        // Generate unique session ID
        $this->calculationSessionId = 'CALC_' . now()->format('YmdHis') . '_' . Str::random(8);
        $this->info("Calculation session ID: {$this->calculationSessionId}");

        Log::info("Calculation session created", ['session_id' => $this->calculationSessionId]);

        // If specific matricule, run debug mode
        if ($matricule) {
            Log::info("Running in debug mode for specific matricule", ['matricule' => $matricule]);
            return $this->runDebugMode($matricule, $period);
        }

        try {
            // Initialize temporary table structure
            $this->initializeTempTable();

            // Get total distributors to process from level_currents
            $totalCount = $this->getTotalDistributorsCount($period);
            $this->info("Ready to process {$totalCount} distributors from level_currents");

            Log::info("Total distributors to process", ['count' => $totalCount, 'period' => $period]);

            // Process advancements iteratively
            $this->processIterativeAdvancements($period);

            // Apply changes if not dry run
            if (!$isDryRun) {
                Log::info("Applying advancements to main tables", ['session_id' => $this->calculationSessionId]);
                $this->applyAdvancementsToMainTables($period);
            } else {
                Log::info("Dry run completed, no changes applied", ['session_id' => $this->calculationSessionId]);
                $this->info("\nDRY RUN completed. No changes applied to main tables.");
                $this->info("Review the temporary data with session ID: {$this->calculationSessionId}");
            }

            // Cleanup if requested
            if ($this->option('cleanup') && !$isDryRun) {
                Log::info("Cleaning up temporary data", ['session_id' => $this->calculationSessionId]);
                $this->cleanupTempData();
            } else {
                Log::info("Keeping temporary data", ['session_id' => $this->calculationSessionId]);
                $this->info("Temporary data kept in table with session ID: {$this->calculationSessionId}");
            }

            Log::info("ProcessAdvancementsCommand completed successfully", [
                'session_id' => $this->calculationSessionId,
                'total_passes' => $this->passNumber
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Error during processing: " . $e->getMessage());
            Log::error("Advancement processing error", [
                'session_id' => $this->calculationSessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function initializeTempTable(): void
    {
        Log::debug("Initializing temporary table structure");
        $this->info("Initializing temporary calculation table structure...");

        // The table structure should already exist from migration
        // Just ensure it's ready for use
        DB::statement('SET SESSION sql_mode = ""'); // For MySQL compatibility

        Log::debug("Temporary table initialized");
    }

    private function getTotalDistributorsCount(string $period): int
    {
        Log::debug("Getting total distributors count", ['period' => $period]);

        $query = DB::table('level_currents as lc')
            ->join('distributeurs as d', 'lc.distributeur_id', '=', 'd.id')
            ->where('lc.period', $period);

        if (!$this->option('include-non-validated')) {
            $query->where('d.statut_validation_periode', 1);
            Log::debug("Filtering only validated distributors");
        }

        if ($offset = $this->option('offset')) {
            $query->skip($offset);
            Log::debug("Applying offset", ['offset' => $offset]);
        }

        if ($limit = $this->option('limit')) {
            $query->limit($limit);
            Log::debug("Applying limit", ['limit' => $limit]);
        }

        if ($minGrade = $this->option('min-grade')) {
            $query->where('lc.etoiles', '>=', $minGrade);
            Log::debug("Applying min grade filter", ['min_grade' => $minGrade]);
        }

        if ($maxGrade = $this->option('max-grade')) {
            $query->where('lc.etoiles', '<=', $maxGrade);
            Log::debug("Applying max grade filter", ['max_grade' => $maxGrade]);
        }

        $count = $query->count();
        Log::debug("Total distributors count retrieved", ['count' => $count]);

        return $count;
    }

    private function processIterativeAdvancements(string $period): void
    {
        Log::info("Starting iterative advancement calculation", ['period' => $period]);
        $this->info("\nStarting iterative advancement calculation...");

        $totalPromotions = 0;
        $continueProcessing = true;

        while ($continueProcessing) {
            $this->passNumber++;
            Log::info("Starting pass", ['pass_number' => $this->passNumber]);
            $this->info("\n--- Pass #{$this->passNumber} ---");

            if ($this->passNumber === 1) {
                // First pass: process from level_currents
                Log::debug("Processing first pass from level_currents");
                $promotionsCount = $this->processFirstPass($period);
            } else {
                // Subsequent passes: process from temp table (propagate upwards)
                Log::debug("Processing subsequent pass from temp table");
                $promotionsCount = $this->processSubsequentPass($period);
            }

            $totalPromotions += $promotionsCount;
            Log::info("Pass completed", [
                'pass_number' => $this->passNumber,
                'promotions_count' => $promotionsCount,
                'total_promotions' => $totalPromotions
            ]);
            $this->info("Pass #{$this->passNumber} completed: {$promotionsCount} promotions found");

            if ($promotionsCount === 0) {
                Log::info("No more promotions found, process stabilized");
                $this->info("\nNo more promotions found. Process stabilized.");
                $continueProcessing = false;
            }

            // Safety check to prevent infinite loops
            if ($this->passNumber > 20) {
                Log::warning("Maximum number of passes reached", ['pass_number' => $this->passNumber]);
                $this->warn("Maximum number of passes reached. Stopping.");
                $continueProcessing = false;
            }
        }

        Log::info("Iterative advancement calculation completed", [
            'total_passes' => $this->passNumber,
            'total_promotions' => $totalPromotions
        ]);

        $this->info("\nCalculation completed after {$this->passNumber} passes.");
        $this->info("Total promotions found: {$totalPromotions}");

        if ($this->showDetails && $totalPromotions > 0) {
            $this->displayPromotionsSummary();
        }
    }

    private function processFirstPass(string $period): int
    {
        Log::debug("Starting first pass processing", ['period' => $period]);

        // For first pass, don't use the calculation session yet
        // Let the service load from the main distributeurs table
        $this->lineageService->setCalculationSession(null);

        $query = DB::table('level_currents as lc')
            ->join('distributeurs as d', 'lc.distributeur_id', '=', 'd.id')
            ->where('lc.period', $period)
            ->select(
                'd.id as distributeur_id',
                'd.distributeur_id as matricule',
                'lc.id as level_current_id',
                'lc.etoiles as grade_actuel',
                'lc.cumul_individuel',
                'lc.cumul_collectif'
            );

        if (!$this->option('include-non-validated')) {
            $query->where('d.statut_validation_periode', 1);
        }

        if ($offset = $this->option('offset')) {
            $query->skip($offset);
        }

        if ($limit = $this->option('limit')) {
            $query->limit($limit);
        }

        if ($minGrade = $this->option('min-grade')) {
            $query->where('lc.etoiles', '>=', $minGrade);
        }

        if ($maxGrade = $this->option('max-grade')) {
            $query->where('lc.etoiles', '<=', $maxGrade);
        }

        $distributors = $query->get();
        $distributorCount = $distributors->count();
        Log::debug("Retrieved distributors for first pass", ['count' => $distributorCount]);

        $promotionsCount = 0;

        $progress = $this->output->createProgressBar($distributorCount);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% | %message%');
        $progress->setMessage('Processing first pass...');
        $progress->start();

        foreach ($distributors as $distributor) {
            $progress->setMessage("Checking {$distributor->matricule}");

            Log::debug("Checking distributor eligibility", [
                'matricule' => $distributor->matricule,
                'current_grade' => $distributor->grade_actuel,
                'cumul_individuel' => $distributor->cumul_individuel,
                'cumul_collectif' => $distributor->cumul_collectif
            ]);

            // Check eligibility using the service
            $eligibility = $this->lineageService->checkGradeEligibility(
                $distributor->matricule,
                $period,
                [
                    'check_all_possible' => true,
                    'include_details' => false,
                    //'only_validated' => true,
                    'stop_on_first_failure' => true
                ]
            );

            if (isset($eligibility['can_advance']) && $eligibility['can_advance']) {
                $newGrade = $eligibility['max_achievable_grade'];

                Log::info("Distributor eligible for advancement", [
                    'matricule' => $distributor->matricule,
                    'from_grade' => $distributor->grade_actuel,
                    'to_grade' => $newGrade,
                    'pass' => $this->passNumber
                ]);

                // Insert into temp table
                TempGradeCalculation::create([
                    'calculation_session_id' => $this->calculationSessionId,
                    'period' => $period,
                    'distributeur_id' => $distributor->distributeur_id,
                    'matricule' => $distributor->matricule,
                    'level_current_id' => $distributor->level_current_id,
                    'grade_initial' => $distributor->grade_actuel,
                    'grade_actuel' => $newGrade,
                    'grade_precedent' => $distributor->grade_actuel,
                    'cumul_individuel' => $distributor->cumul_individuel,
                    'cumul_collectif' => $distributor->cumul_collectif,
                    'pass_number' => $this->passNumber,
                    'qualification_method' => $this->extractQualificationMethod($eligibility),
                    'promoted' => true,
                    'promotion_history' => [[
                        'pass' => $this->passNumber,
                        'from' => $distributor->grade_actuel,
                        'to' => $newGrade,
                        'method' => $this->extractQualificationMethod($eligibility),
                        'timestamp' => now()->toISOString()
                    ]]
                ]);

                $promotionsCount++;

                if ($this->showDetails) {
                    $progress->clear();
                    $this->info("✓ {$distributor->matricule}: Grade {$distributor->grade_actuel} → {$newGrade}");
                    $progress->display();
                }
            } else {
                Log::debug("Distributor not eligible for advancement", [
                    'matricule' => $distributor->matricule,
                    'current_grade' => $distributor->grade_actuel
                ]);
            }

            $progress->advance();

            // Force garbage collection every 1000 distributors to free memory
            if ($progress->getProgress() % 1000 === 0) {
                Log::debug("Forcing garbage collection", ['processed' => $progress->getProgress()]);
                gc_collect_cycles();
            }
        }

        $progress->finish();
        $this->newLine();

        Log::info("First pass completed", [
            'distributors_checked' => $distributorCount,
            'promotions_found' => $promotionsCount
        ]);

        return $promotionsCount;
    }

    private function processSubsequentPass(string $period): int
    {
        Log::debug("Starting subsequent pass processing", [
            'period' => $period,
            'pass_number' => $this->passNumber
        ]);

        // For subsequent passes, use the calculation session
        $this->lineageService->setCalculationSession($this->calculationSessionId);

        // Get distributors promoted in previous passes
        $promotedDistributors = TempGradeCalculation::forSession($this->calculationSessionId)
            ->where('period', $period)
            ->where('pass_number', '<', $this->passNumber)
            ->pluck('distributeur_id')
            ->toArray();

        Log::debug("Retrieved promoted distributors from previous passes", [
            'count' => count($promotedDistributors)
        ]);

        if (empty($promotedDistributors)) {
            Log::debug("No promoted distributors found in previous passes");
            return 0;
        }

        // Find parents of promoted distributors who haven't been checked yet
        $query = DB::table('distributeurs as d')
            ->join('level_currents as lc', 'd.id', '=', 'lc.distributeur_id')
            ->whereIn('d.id', function($query) use ($promotedDistributors) {
                $query->select('id_distrib_parent')
                    ->from('distributeurs')
                    ->whereIn('id', $promotedDistributors)
                    ->whereNotNull('id_distrib_parent');
            })
            ->where('lc.period', $period)
            ->whereNotIn('d.id', function($query) {
                $query->select('distributeur_id')
                    ->from('temp_grade_calculations')
                    ->where('calculation_session_id', $this->calculationSessionId);
            });

        if (!$this->option('include-non-validated')) {
            $query->where('d.statut_validation_periode', 1);
        }

        $parentsToCheck = $query->select(
                'd.id as distributeur_id',
                'd.distributeur_id as matricule',
                'lc.id as level_current_id',
                'lc.etoiles as grade_actuel',
                'lc.cumul_individuel',
                'lc.cumul_collectif'
            )
            ->distinct()
            ->get();

        $parentCount = $parentsToCheck->count();
        Log::debug("Found parents to check", ['count' => $parentCount]);

        $promotionsCount = 0;

        $progress = $this->output->createProgressBar($parentCount);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% | %message%');
        $progress->setMessage('Processing upward propagation...');
        $progress->start();

        foreach ($parentsToCheck as $parent) {
            $progress->setMessage("Checking parent {$parent->matricule}");

            Log::debug("Checking parent eligibility", [
                'matricule' => $parent->matricule,
                'current_grade' => $parent->grade_actuel,
                'pass' => $this->passNumber
            ]);

            // Check eligibility with updated branch grades
            $eligibility = $this->lineageService->checkGradeEligibility(
                $parent->matricule,
                $period,
                [
                    'check_all_possible' => true,
                    'include_details' => false,
                    //'only_validated' => true,
                    'stop_on_first_failure' => true
                ]
            );

            if (isset($eligibility['can_advance']) && $eligibility['can_advance']) {
                $newGrade = $eligibility['max_achievable_grade'];

                Log::info("Parent eligible for advancement", [
                    'matricule' => $parent->matricule,
                    'from_grade' => $parent->grade_actuel,
                    'to_grade' => $newGrade,
                    'pass' => $this->passNumber
                ]);

                // Insert into temp table
                TempGradeCalculation::create([
                    'calculation_session_id' => $this->calculationSessionId,
                    'period' => $period,
                    'distributeur_id' => $parent->distributeur_id,
                    'matricule' => $parent->matricule,
                    'level_current_id' => $parent->level_current_id,
                    'grade_initial' => $parent->grade_actuel,
                    'grade_actuel' => $newGrade,
                    'grade_precedent' => $parent->grade_actuel,
                    'cumul_individuel' => $parent->cumul_individuel,
                    'cumul_collectif' => $parent->cumul_collectif,
                    'pass_number' => $this->passNumber,
                    'qualification_method' => $this->extractQualificationMethod($eligibility),
                    'promoted' => true,
                    'promotion_history' => [[
                        'pass' => $this->passNumber,
                        'from' => $parent->grade_actuel,
                        'to' => $newGrade,
                        'method' => $this->extractQualificationMethod($eligibility),
                        'timestamp' => now()->toISOString()
                    ]]
                ]);

                $promotionsCount++;

                if ($this->showDetails) {
                    $progress->clear();
                    $this->info("✓ {$parent->matricule}: Grade {$parent->grade_actuel} → {$newGrade} (Pass #{$this->passNumber})");
                    $progress->display();
                }
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();

        Log::info("Subsequent pass completed", [
            'pass_number' => $this->passNumber,
            'parents_checked' => $parentCount,
            'promotions_found' => $promotionsCount
        ]);

        return $promotionsCount;
    }

    private function extractQualificationMethod(array $eligibility): string
    {
        if (isset($eligibility['max_achievable_grade']) &&
            isset($eligibility['eligibilities'][$eligibility['max_achievable_grade']]['qualified_options'][0]['description'])) {
            $method = $eligibility['eligibilities'][$eligibility['max_achievable_grade']]['qualified_options'][0]['description'];
            Log::debug("Extracted qualification method", ['method' => $method]);
            return $method;
        }
        Log::debug("Could not extract qualification method, using default");
        return 'Unknown method';
    }

    private function applyAdvancementsToMainTables(string $period): void
    {
        Log::info("Starting to apply advancements to main tables", [
            'period' => $period,
            'session_id' => $this->calculationSessionId
        ]);

        $this->info("\nApplying advancements to main tables...");

        $promotions = TempGradeCalculation::forSession($this->calculationSessionId)
            ->where('period', $period)
            ->where('promoted', true)
            ->get();

        $promotionCount = $promotions->count();
        Log::debug("Retrieved promotions to apply", ['count' => $promotionCount]);

        if ($promotions->isEmpty()) {
            Log::info("No promotions to apply");
            $this->info("No promotions to apply.");
            return;
        }

        DB::beginTransaction();
        Log::debug("Database transaction started");

        try {
            $updateCount = 0;
            $progress = $this->output->createProgressBar($promotionCount);
            $progress->setMessage('Updating main tables...');
            $progress->start();

            foreach ($promotions as $promotion) {
                Log::debug("Applying promotion", [
                    'distributeur_id' => $promotion->distributeur_id,
                    'matricule' => $promotion->matricule,
                    'from_grade' => $promotion->grade_precedent,
                    'to_grade' => $promotion->grade_actuel
                ]);

                // Update distributeurs table
                DB::table('distributeurs')
                    ->where('id', $promotion->distributeur_id)
                    ->update(['etoiles_id' => $promotion->grade_actuel]);

                // Update level_currents table
                DB::table('level_currents')
                    ->where('id', $promotion->level_current_id)
                    ->update(['etoiles' => $promotion->grade_actuel]);

                $updateCount++;
                $progress->advance();
            }

            $progress->finish();
            $this->newLine();

            DB::commit();
            Log::info("Database transaction committed", [
                'updates_applied' => $updateCount
            ]);

            $this->info("✓ Successfully updated {$updateCount} distributors in main tables.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Database transaction rolled back", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function displayPromotionsSummary(): void
    {
        Log::debug("Displaying promotions summary");

        $this->info("\n--- PROMOTIONS SUMMARY ---");

        $promotions = TempGradeCalculation::forSession($this->calculationSessionId)
            ->where('promoted', true)
            ->get();

        // Group by new grade
        $byGrade = $promotions->groupBy('grade_actuel')->map->count();

        Log::info("Promotions summary by grade", $byGrade->toArray());

        $this->table(
            ['New Grade', 'Count'],
            $byGrade->map(function($count, $grade) {
                return [$grade, $count];
            })->sortKeys()->values()
        );

        // Show promotions by pass
        $byPass = $promotions->groupBy('pass_number')->map->count();

        Log::info("Promotions summary by pass", $byPass->toArray());

        $this->info("\nPromotions by pass:");
        foreach ($byPass as $pass => $count) {
            $this->info("  Pass #{$pass}: {$count} promotions");
        }
    }

    private function cleanupTempData(): void
    {
        Log::info("Starting cleanup of temporary data", [
            'session_id' => $this->calculationSessionId
        ]);

        $this->info("\nCleaning up temporary data...");

        $deletedCount = TempGradeCalculation::where('calculation_session_id', $this->calculationSessionId)->delete();

        Log::info("Temporary data cleaned up", [
            'session_id' => $this->calculationSessionId,
            'deleted_records' => $deletedCount
        ]);

        $this->info("✓ Temporary data cleaned up.");
    }

    private function runDebugMode(string $matricule, string $period): int
    {
        Log::info("Running debug mode", [
            'matricule' => $matricule,
            'period' => $period
        ]);

        $this->info("\n--- DEBUG MODE for Matricule {$matricule} for Period {$period} ---");

        // Find distributor
        $distributor = DB::table('distributeurs as d')
            ->join('level_currents as lc', 'd.id', '=', 'lc.distributeur_id')
            ->where('d.distributeur_id', $matricule)
            ->where('lc.period', $period)
            ->select(
                'd.id',
                'd.distributeur_id',
                'd.nom_distributeur',
                'd.pnom_distributeur',
                'lc.etoiles',
                'lc.cumul_individuel',
                'lc.cumul_collectif'
            )
            ->first();

        if (!$distributor) {
            Log::error("Distributor not found in debug mode", ['matricule' => $matricule]);
            $this->error("Distributor not found!");
            return 1;
        }

        Log::debug("Distributor found in debug mode", [
            'id' => $distributor->id,
            'matricule' => $distributor->distributeur_id,
            'name' => $distributor->nom_distributeur . ' ' . $distributor->pnom_distributeur,
            'grade' => $distributor->etoiles
        ]);

        $this->info("Distributor found: {$distributor->nom_distributeur} {$distributor->pnom_distributeur}");

        $this->table(
            ['Metric', 'Value'],
            [
                ['Matricule', $distributor->distributeur_id],
                ['Internal ID', $distributor->id],
                ['Current Grade', $distributor->etoiles],
                ['Cumul Individuel', number_format($distributor->cumul_individuel, 2)],
                ['Cumul Collectif', number_format($distributor->cumul_collectif, 2)],
            ]
        );

        // Check eligibility
        $this->info("\n--- ELIGIBILITY ANALYSIS ---");

        Log::debug("Checking eligibility in debug mode");

        $eligibility = $this->lineageService->checkGradeEligibility(
            $matricule,
            $period,
            [
                'check_all_possible' => true,
                'include_details' => true,
                //'only_validated' => true,
                'debug' => true
            ]
        );

        if (isset($eligibility['error'])) {
            Log::error("Eligibility check error in debug mode", [
                'error' => $eligibility['error']
            ]);
            $this->error("Error: " . $eligibility['error']);
            return 1;
        }

        if ($eligibility['can_advance']) {
            Log::info("Distributor eligible for advancement in debug mode", [
                'matricule' => $matricule,
                'max_achievable_grade' => $eligibility['max_achievable_grade']
            ]);

            $this->info("✓ Eligible for advancement to grade " . $eligibility['max_achievable_grade']);

            foreach ($eligibility['eligibilities'] as $grade => $details) {
                if ($details['eligible']) {
                    $this->info("\nGrade {$grade}: ELIGIBLE");
                    foreach ($details['qualified_options'] as $option) {
                        $this->info("  ✓ " . $option['description']);
                    }
                }
            }
        } else {
            Log::info("Distributor not eligible for advancement in debug mode", [
                'matricule' => $matricule
            ]);

            $this->warn("✗ Not eligible for any advancement");

            if ($this->option('show-details')) {
                foreach ($eligibility['eligibilities'] as $grade => $details) {
                    if (isset($details['all_options'])) {
                        $this->info("\nGrade {$grade}: NOT ELIGIBLE");
                        foreach ($details['all_options'] as $option) {
                            $this->info("  ✗ " . $option['description']);
                            foreach ($option['status'] as $status) {
                                $statusSymbol = $status['met'] ? '✓' : '✗';
                                $this->info("    {$statusSymbol} {$status['requirement']}: {$status['actual']} / {$status['required']}");
                            }
                        }
                    }
                }
            }
        }

        Log::info("Debug mode completed", ['matricule' => $matricule]);
        return 0;
    }
}
