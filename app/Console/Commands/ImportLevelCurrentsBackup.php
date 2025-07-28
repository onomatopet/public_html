<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ImportLevelCurrentsBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-level-currents-backup
                            {--force : Skip confirmation}
                            {--chunk=1000 : Chunk size for batch processing}
                            {--verify : Verify data integrity after import}
                            {--dry-run : Simulate the import without inserting data}
                            {--skip-errors : Continue processing even if errors occur}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from level_current_tests_back_2025-06 to level_currents with matricule to ID conversion';

    /**
     * Map [matricule => distributeur_id]
     * @var array
     */
    private array $matriculeToIdMap = [];

    /**
     * Source table name
     * @var string
     */
    private string $sourceTable = 'level_current_tests_back_2025-06';

    /**
     * Destination table name
     * @var string
     */
    private string $destinationTable = 'level_currents';

    /**
     * Statistics counters
     * @var array
     */
    private array $stats = [
        'total' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'missing_distributeur' => 0,
        'missing_parent' => 0
    ];

    /**
     * Error log
     * @var array
     */
    private array $errorLog = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn("--- Starting Level Currents Backup Import ---");
        $this->info("Source table: {$this->sourceTable}");
        $this->info("Destination table: {$this->destinationTable}");
        $this->info("Chunk size: " . $this->option('chunk'));

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN MODE - No data will be inserted");
        }

        // Perform pre-checks
        if (!$this->performPreChecks()) {
            return self::FAILURE;
        }

        // Ask for confirmation
        if (!$this->option('force') && !$this->option('dry-run')) {
            if (!$this->confirm("This will import/update data in {$this->destinationTable}. Have you made a backup?")) {
                $this->comment("Operation cancelled.");
                return self::FAILURE;
            }
        }

        $startTime = microtime(true);

        try {
            // Disable foreign key checks temporarily
            if (!$this->option('dry-run')) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }

            // Build matricule to ID map
            $this->buildMatriculeMap();

            // Import data in chunks
            $this->importDataInChunks();

            // Re-enable foreign key checks
            if (!$this->option('dry-run')) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            // Verify data if requested
            if ($this->option('verify') && !$this->option('dry-run')) {
                $this->verifyDataIntegrity();
            }

            // Display final report
            $this->displayFinalReport($startTime);

            // Save error log if there were errors
            if (!empty($this->errorLog)) {
                $this->saveErrorLog();
            }

        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->error("\nImport failed: " . $e->getMessage());
            Log::error("Level currents import failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Perform pre-import checks
     */
    private function performPreChecks(): bool
    {
        $this->line("\nPerforming pre-import checks...");

        // Check if source table exists
        if (!Schema::hasTable($this->sourceTable)) {
            $this->error("Source table '{$this->sourceTable}' does not exist!");
            return false;
        }

        // Check if destination table exists
        if (!Schema::hasTable($this->destinationTable)) {
            $this->error("Destination table '{$this->destinationTable}' does not exist!");
            return false;
        }

        // Verify required columns in source table
        $sourceColumns = Schema::getColumnListing($this->sourceTable);
        $requiredColumns = [
            'distributeur_id', 'id_distrib_parent', 'period', 'etoiles',
            'cumul_individuel', 'new_cumul', 'cumul_total', 'cumul_collectif'
        ];

        $missingColumns = array_diff($requiredColumns, $sourceColumns);
        if (!empty($missingColumns)) {
            $this->error("Missing required columns in source table: " . implode(', ', $missingColumns));
            return false;
        }

        // Count records in source table
        $this->stats['total'] = DB::table($this->sourceTable)->count();
        $this->info("Found {$this->stats['total']} records to process");

        if ($this->stats['total'] === 0) {
            $this->warn("Source table is empty!");
            return false;
        }

        $this->info("✓ All pre-checks passed");
        return true;
    }

    /**
     * Build matricule to ID map from distributeurs table
     */
    private function buildMatriculeMap(): void
    {
        $this->line("\nBuilding matricule to ID map...");

        $this->matriculeToIdMap = Distributeur::pluck('id', 'distributeur_id')
            ->toArray();

        $mapCount = count($this->matriculeToIdMap);
        $this->info("Built map with {$mapCount} distributeurs");

        if ($mapCount === 0) {
            throw new \Exception("No distributeurs found in database!");
        }
    }

    /**
     * Import data in chunks
     */
    private function importDataInChunks(): void
    {
        $chunkSize = (int) $this->option('chunk');
        $this->line("\nImporting data in chunks of {$chunkSize}...");

        $progressBar = $this->output->createProgressBar($this->stats['total']);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        DB::table($this->sourceTable)
            ->orderBy('id')
            ->chunk($chunkSize, function ($records) use ($progressBar) {
                $this->processChunk($records);
                $progressBar->advance(count($records));
            });

        $progressBar->finish();
        $this->line("\n");
    }

    /**
     * Process a chunk of records
     */
    private function processChunk($records): void
    {
        $dataToInsert = [];
        $dataToUpdate = [];

        foreach ($records as $record) {
            try {
                // Convert matricules to IDs
                $distributeurId = $this->getDistributeurId($record->distributeur_id);
                if (!$distributeurId) {
                    $this->stats['missing_distributeur']++;
                    $this->logError($record, "Distributeur matricule not found: {$record->distributeur_id}");
                    continue;
                }

                // Convert parent matricule to ID (can be null)
                $parentId = null;
                if ($record->id_distrib_parent) {
                    $parentId = $this->getDistributeurId($record->id_distrib_parent);
                    if (!$parentId) {
                        $this->stats['missing_parent']++;
                        // Log but don't skip - parent can be null
                        $this->logError($record, "Parent matricule not found: {$record->id_distrib_parent}");
                    }
                }

                // Prepare data
                $data = [
                    'distributeur_id' => $distributeurId,
                    'id_distrib_parent' => $parentId,
                    'period' => $record->period,
                    'etoiles' => $record->etoiles ?? 1,
                    'cumul_individuel' => $record->cumul_individuel ?? 0,
                    'new_cumul' => $record->new_cumul ?? 0,
                    'cumul_total' => $record->cumul_total ?? 0,
                    'cumul_collectif' => $record->cumul_collectif ?? 0,
                    'rang' => $record->rang ?? 0,
                    'is_children' => $this->convertOnOffToBoolean($record->is_children ?? 'off'),
                    'is_indivual_cumul_checked' => $this->convertOnOffToBoolean($record->is_indivual_cumul_checked ?? 'off'),
                    'created_at' => $record->created_at ?? now(),
                    'updated_at' => now()
                ];

                // Check if record already exists
                if (!$this->option('dry-run')) {
                    $exists = LevelCurrent::where('distributeur_id', $distributeurId)
                        ->where('period', $record->period)
                        ->exists();

                    if ($exists) {
                        $dataToUpdate[] = $data;
                    } else {
                        $dataToInsert[] = $data;
                    }
                } else {
                    // In dry-run mode, just count
                    $this->stats['imported']++;
                }

            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->logError($record, "Processing error: " . $e->getMessage());

                if (!$this->option('skip-errors')) {
                    throw $e;
                }
            }
        }

        // Perform batch operations
        if (!$this->option('dry-run')) {
            $this->performBatchOperations($dataToInsert, $dataToUpdate);
        }
    }

    /**
     * Get distributeur ID from matricule
     */
    private function getDistributeurId($matricule): ?int
    {
        return $this->matriculeToIdMap[$matricule] ?? null;
    }

    /**
     * Perform batch insert and update operations
     */
    private function performBatchOperations(array $dataToInsert, array $dataToUpdate): void
    {
        // Batch insert
        if (!empty($dataToInsert)) {
            try {
                DB::table($this->destinationTable)->insert($dataToInsert);
                $this->stats['imported'] += count($dataToInsert);
            } catch (\Exception $e) {
                $this->stats['errors'] += count($dataToInsert);
                Log::error("Batch insert failed", ['error' => $e->getMessage()]);

                if (!$this->option('skip-errors')) {
                    throw $e;
                }
            }
        }

        // Batch update
        foreach ($dataToUpdate as $data) {
            try {
                LevelCurrent::where('distributeur_id', $data['distributeur_id'])
                    ->where('period', $data['period'])
                    ->update($data);
                $this->stats['updated']++;
            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::error("Update failed", [
                    'distributeur_id' => $data['distributeur_id'],
                    'period' => $data['period'],
                    'error' => $e->getMessage()
                ]);

                if (!$this->option('skip-errors')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Log error for a record
     */
    private function logError($record, string $message): void
    {
        $this->errorLog[] = [
            'record_id' => $record->id ?? 'unknown',
            'distributeur_matricule' => $record->distributeur_id ?? 'unknown',
            'period' => $record->period ?? 'unknown',
            'error' => $message,
            'timestamp' => now()->toDateTimeString()
        ];
    }

    /**
     * Verify data integrity after import
     */
    private function verifyDataIntegrity(): void
    {
        $this->line("\nVerifying data integrity...");

        // Count imported records
        $importedCount = DB::table($this->destinationTable)
            ->whereIn('distributeur_id', array_values($this->matriculeToIdMap))
            ->count();

        $this->info("Records in destination table: {$importedCount}");

        // Check for orphaned parents
        $orphanedParents = DB::table($this->destinationTable)
            ->whereNotNull('id_distrib_parent')
            ->whereNotIn('id_distrib_parent', array_values($this->matriculeToIdMap))
            ->count();

        if ($orphanedParents > 0) {
            $this->warn("Found {$orphanedParents} records with orphaned parent references");
        }

        // Check for duplicate entries
        $duplicates = DB::table($this->destinationTable)
            ->select('distributeur_id', 'period')
            ->groupBy('distributeur_id', 'period')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            $this->warn("Found {$duplicates} duplicate (distributeur_id, period) combinations");
        }

        $this->info("✓ Data integrity check completed");
    }

    /**
     * Display final import report
     */
    private function displayFinalReport(float $startTime): void
    {
        $executionTime = round(microtime(true) - $startTime, 2);

        $this->line("\n" . str_repeat('=', 60));
        $this->info("IMPORT SUMMARY");
        $this->line(str_repeat('=', 60));

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total records processed', number_format($this->stats['total'])],
                ['Successfully imported', number_format($this->stats['imported'])],
                ['Updated existing', number_format($this->stats['updated'])],
                ['Skipped', number_format($this->stats['skipped'])],
                ['Errors', number_format($this->stats['errors'])],
                ['Missing distributeurs', number_format($this->stats['missing_distributeur'])],
                ['Missing parents', number_format($this->stats['missing_parent'])],
                ['Execution time', "{$executionTime} seconds"],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn("\nDRY RUN - No data was actually imported");
        }

        $successRate = $this->stats['total'] > 0
            ? round((($this->stats['imported'] + $this->stats['updated']) / $this->stats['total']) * 100, 2)
            : 0;

        $this->info("\nSuccess rate: {$successRate}%");

        if ($this->stats['errors'] > 0) {
            $this->warn("\n⚠️  There were {$this->stats['errors']} errors during import.");
            $this->warn("Check the error log at: storage/logs/level_currents_import_errors_" . date('Y-m-d_His') . ".log");
        } else {
            $this->info("\n✅ Import completed successfully with no errors!");
        }
    }

    /**
     * Save error log to file
     */
    private function saveErrorLog(): void
    {
        if (empty($this->errorLog)) {
            return;
        }

        $filename = 'level_currents_import_errors_' . date('Y-m-d_His') . '.log';
        $path = storage_path('logs/' . $filename);

        $content = "Level Currents Import Error Log\n";
        $content .= "Generated at: " . now()->toDateTimeString() . "\n";
        $content .= str_repeat('=', 80) . "\n\n";

        foreach ($this->errorLog as $error) {
            $content .= "Record ID: {$error['record_id']}\n";
            $content .= "Distributeur Matricule: {$error['distributeur_matricule']}\n";
            $content .= "Period: {$error['period']}\n";
            $content .= "Error: {$error['error']}\n";
            $content .= "Timestamp: {$error['timestamp']}\n";
            $content .= str_repeat('-', 40) . "\n\n";
        }

        file_put_contents($path, $content);

        $this->info("Error log saved to: {$path}");
    }

    /**
     * Convert 'on'/'off' string values to boolean
     */
    private function convertOnOffToBoolean($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $value = strtolower(trim($value));

        return match($value) {
            'on', 'yes', 'true', '1' => 1,
            'off', 'no', 'false', '0', '' => 0,
            default => 0
        };
    }
}
