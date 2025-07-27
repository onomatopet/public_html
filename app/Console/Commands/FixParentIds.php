<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection; // Pour le type hinting de la map

class FixParentIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // Changer 'app:fix-parent-ids' si vous préférez un autre nom
    protected $signature = 'app:fix-parent-ids {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrects id_distrib_parent columns to point to primary key ID instead of matricule (distributeur_id)';

    /**
     * Tables to process. Add or remove tables as needed.
     * @var array
     */
    protected array $targetTables = [
        'distributeurs',
        'level_current_tests',
        'level_current_test_history',
    ];

    /**
     * Lookup map [matricule => primary_key_id].
     * @var Collection|null
     */
    protected ?Collection $distributorMap = null;

    /**
     * Counters for reporting.
     */
    protected int $totalRowsChecked = 0;
    protected int $zeroCorrectedCount = 0;
    protected int $parentsUpdatedCount = 0;
    protected int $orphanRowsCount = 0;
    protected array $orphanDetails = [];


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Starting Parent ID Correction Process...');

        // Confirmation
        if (!$this->option('force') && !$this->confirm('This command will modify data in multiple tables. Are you sure you want to proceed? [y/N]', false)) {
            $this->comment('Operation cancelled.');
            return self::FAILURE;
        }

        // --- 1. Build the Distributor Lookup Map ---
        $this->info('Building distributor matricule-to-id map...');
        if (!$this->buildDistributorMap()) {
            // Error already logged in buildDistributorMap
            return self::FAILURE;
        }
        $this->info('Distributor map built successfully (' . $this->distributorMap->count() . ' entries).');


        // --- 2. Correct id_distrib_parent = 0 to NULL ---
        $this->info('Correcting id_distrib_parent = 0 to NULL...');
        $this->correctZeroValues();
        $this->info("Corrected {$this->zeroCorrectedCount} rows where id_distrib_parent was 0.");


        // --- 3. Process Each Table ---
        foreach ($this->targetTables as $tableName) {
            $this->info("Processing table: {$tableName}...");
            $this->processTable($tableName);
        }


        // --- 4. Final Report ---
        $this->info('------------------------------------------');
        $this->info('Parent ID Correction Process Finished.');
        $this->info("Total rows checked (excluding 0s): {$this->totalRowsChecked}");
        $this->info("Parent IDs updated: {$this->parentsUpdatedCount}");
        $this->warn("Orphan rows found (parent matricule not in distributeurs table): {$this->orphanRowsCount}");

        if (!empty($this->orphanDetails)) {
            $this->warn('Orphan details (Table: Row ID -> Missing Parent Matricule):');
            foreach($this->orphanDetails as $detail) {
                $this->warn("- {$detail['table']}: ID {$detail['row_id']} -> Matricule {$detail['missing_matricule']}");
            }
             $this->warn('These orphan rows likely had their id_distrib_parent set to NULL.');
        }
         $this->info('------------------------------------------');


        return self::SUCCESS;
    }

    /**
     * Fetches distributeur_id and id from distributeurs table
     * and builds the lookup map.
     *
     * @return bool True on success, false on failure.
     */
    protected function buildDistributorMap(): bool
    {
        try {
            // Use pluck to directly get [matricule => id]
            // Assumes 'distributeur_id' is unique (should have a unique constraint)
            $this->distributorMap = DB::table('distributeurs')
                ->pluck('id', 'distributeur_id'); // Key is matricule, Value is id

            // Check for potential issues like NULL matricules if the column allows it
            if ($this->distributorMap->has(null) || $this->distributorMap->has('')) {
                 $this->error('Found NULL or empty matricules (distributeur_id) in the distributeurs table. Please fix data before proceeding.');
                 Log::error('FixParentIds: Found NULL or empty matricules in distributeurs table.');
                 return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->error('Failed to build distributor map: ' . $e->getMessage());
            Log::error('FixParentIds: Failed to build distributor map: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates id_distrib_parent from 0 to NULL in all target tables.
     */
    protected function correctZeroValues(): void
    {
        foreach ($this->targetTables as $tableName) {
            try {
                 $count = DB::table($tableName)
                    ->where('id_distrib_parent', 0)
                    ->update(['id_distrib_parent' => null]);
                 $this->zeroCorrectedCount += $count;
            } catch (\Exception $e) {
                 $this->error("Error updating 0 values in {$tableName}: " . $e->getMessage());
                 Log::error("FixParentIds: Error updating 0 values in {$tableName}: " . $e->getMessage());
                 // Continue to next table
            }
        }
    }

    /**
     * Processes a single table to correct parent IDs.
     *
     * @param string $tableName The name of the table to process.
     */
    protected function processTable(string $tableName): void
    {
        $tableRowCount = 0;
        $tableUpdatedCount = 0;
        $tableOrphanCount = 0;

        // Estimate total rows for progress bar (only non-null, non-zero parents)
        $totalRowsToProcess = DB::table($tableName)->whereNotNull('id_distrib_parent')->where('id_distrib_parent', '!=', 0)->count();
        if ($totalRowsToProcess == 0) {
            $this->line("  No rows with non-NULL, non-zero parent IDs to check in {$tableName}. Skipping.");
            return;
        }

        $progressBar = $this->output->createProgressBar($totalRowsToProcess);
        $progressBar->start();

        // Process in chunks
        DB::table($tableName)
            ->whereNotNull('id_distrib_parent') // Exclude NULLs (already correct or set from 0)
            ->where('id_distrib_parent', '!=', 0) // Redundant check, but safe
            ->select('id', 'id_distrib_parent') // Select only needed columns
            ->chunkById(200, function (Collection $rows) use ($tableName, &$tableRowCount, &$tableUpdatedCount, &$tableOrphanCount, $progressBar) {

                $updates = []; // Batch updates for efficiency within a chunk

                foreach ($rows as $row) {
                    $tableRowCount++;
                    $this->totalRowsChecked++;
                    $currentParentMatricule = $row->id_distrib_parent;

                    // Lookup the correct ID using the map
                    $correctParentId = $this->distributorMap->get($currentParentMatricule);

                    if ($correctParentId !== null) {
                        // Correct parent ID found, schedule update
                        // Use 'id' as key to avoid duplicate updates if chunk contains same row twice (unlikely with chunkById)
                        $updates[$row->id] = $correctParentId;
                        $tableUpdatedCount++;
                        $this->parentsUpdatedCount++;
                    } else {
                        // Orphan row: Parent Matricule does not exist in the map
                        $tableOrphanCount++;
                        $this->orphanRowsCount++;
                        $this->orphanDetails[] = [
                            'table' => $tableName,
                            'row_id' => $row->id,
                            'missing_matricule' => $currentParentMatricule
                        ];
                        Log::warning("FixParentIds: Orphan row found in {$tableName} (ID: {$row->id}). Parent matricule '{$currentParentMatricule}' not found in distributeurs map. Setting parent to NULL.");

                         // Decide action for orphan: Set parent to NULL
                        try {
                            DB::table($tableName)->where('id', $row->id)->update(['id_distrib_parent' => null]);
                        } catch (\Exception $e) {
                             Log::error("FixParentIds: Failed to set orphan parent to NULL for {$tableName} ID {$row->id}: ".$e->getMessage());
                        }
                    }
                } // End foreach row in chunk

                // Apply batch updates for this chunk
                if (!empty($updates)) {
                    foreach ($updates as $rowId => $newParentId) {
                         try {
                            DB::table($tableName)->where('id', $rowId)->update(['id_distrib_parent' => $newParentId]);
                        } catch (\Exception $e) {
                             Log::error("FixParentIds: Failed to update parent ID for {$tableName} ID {$rowId}: ".$e->getMessage());
                             // Decrement counters if update failed? Or just log.
                             $tableUpdatedCount--;
                             $this->parentsUpdatedCount--;
                             $this->error("\nUpdate failed for {$tableName} ID {$rowId}: ".$e->getMessage());
                        }
                    }
                }

                $progressBar->advance($rows->count());

            }); // End chunkById

        $progressBar->finish();
        $this->info("\n  Finished processing {$tableName}. Checked: {$tableRowCount}, Updated: {$tableUpdatedCount}, Orphans (set to NULL): {$tableOrphanCount}");
    }
}
