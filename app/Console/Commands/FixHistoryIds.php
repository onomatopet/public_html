<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class FixHistoryIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-history-ids {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrects distributeur_id and id_distrib_parent columns in level_current_test_history to contain primary key IDs instead of matricules.';

    /**
     * Table to process.
     * @var string
     */
    protected string $targetTable = 'level_current_test_history';

    /**
     * Lookup map [matricule => primary_key_id].
     * @var Collection|null
     */
    protected ?Collection $distributorMap = null;

    /**
     * Counters for reporting.
     */
    protected int $totalRowsChecked = 0;
    protected int $distribIdUpdatedCount = 0;
    protected int $parentIdUpdatedCount = 0;
    protected int $distribIdOrphanCount = 0;
    protected int $parentIdOrphanCount = 0;
    protected array $orphanDetails = []; // Combined orphans


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info("Starting History ID Correction Process for table '{$this->targetTable}'...");

        // Confirmation
        if (!$this->option('force') && !$this->confirm("This command will modify 'distributeur_id' and 'id_distrib_parent' columns in '{$this->targetTable}'. Are you sure? [y/N]", false)) {
            $this->comment('Operation cancelled.');
            return self::FAILURE;
        }

        // --- 1. Build the Distributor Lookup Map ---
        $this->info('Building distributor matricule-to-id map...');
        if (!$this->buildDistributorMap()) {
            return self::FAILURE;
        }
        $this->info('Distributor map built successfully (' . $this->distributorMap->count() . ' entries).');


        // --- 2. Process the Table ---
        $this->info("Processing table: {$this->targetTable}...");
        $this->processTable();


        // --- 3. Final Report ---
        $this->info('------------------------------------------');
        $this->info('History ID Correction Process Finished.');
        $this->info("Total rows checked: {$this->totalRowsChecked}");
        $this->info("distributeur_id updated: {$this->distribIdUpdatedCount}");
        $this->info("id_distrib_parent updated: {$this->parentIdUpdatedCount}");
        $this->warn("Orphan rows detected (matricule not found in distributeurs): DistribID Orphans: {$this->distribIdOrphanCount}, ParentID Orphans: {$this->parentIdOrphanCount}");

        if (!empty($this->orphanDetails)) {
            $this->warn('Orphan details (History Row ID -> Column: Missing Matricule):');
            foreach($this->orphanDetails as $detail) {
                $this->warn("- ID {$detail['row_id']} -> {$detail['column']}: {$detail['missing_matricule']}");
            }
            $this->warn('These orphan values were likely NOT updated.');
        }
         $this->info('------------------------------------------');

        if (($this->distribIdOrphanCount + $this->parentIdOrphanCount) > 0) {
             $this->error("Process completed with orphans detected. Check logs and data.");
             return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Fetches distributeur_id and id from distributeurs table
     * and builds the lookup map.
     * (Identical to the one in FixParentIds)
     *
     * @return bool True on success, false on failure.
     */
    protected function buildDistributorMap(): bool
    {
        try {
            $this->distributorMap = DB::table('distributeurs')
                ->pluck('id', 'distributeur_id');

            if ($this->distributorMap->has(null) || $this->distributorMap->has('')) {
                 $this->error('Found NULL or empty matricules (distributeur_id) in the distributeurs table. Please fix data before proceeding.');
                 Log::error('FixHistoryIds: Found NULL or empty matricules in distributeurs table.');
                 return false;
            }
            return true;
        } catch (\Exception $e) {
            $this->error('Failed to build distributor map: ' . $e->getMessage());
            Log::error('FixHistoryIds: Failed to build distributor map: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Processes the target table to correct distributor and parent IDs.
     */
    protected function processTable(): void
    {
        $totalRowsToProcess = DB::table($this->targetTable)->count();
         if ($totalRowsToProcess == 0) {
            $this->line("  Table '{$this->targetTable}' is empty. Nothing to process.");
            return;
        }

        $progressBar = $this->output->createProgressBar($totalRowsToProcess);
        $progressBar->start();

        DB::table($this->targetTable)
            ->select('id', 'distributeur_id', 'id_distrib_parent') // Select needed columns
            ->chunkById(200, function (Collection $rows) use ($progressBar) {

                $updates = []; // Store updates to apply: [rowId => ['column' => newValue, ...]]

                foreach ($rows as $row) {
                    $this->totalRowsChecked++;
                    $rowUpdates = []; // Updates for this specific row

                    // --- Check distributeur_id ---
                    $currentDistribValue = $row->distributeur_id;
                    if ($currentDistribValue !== null) { // Only process non-null values
                        $correctDistribId = $this->distributorMap->get($currentDistribValue);
                        if ($correctDistribId !== null) {
                            // Found matricule in map, needs update if not already correct ID
                            if ($currentDistribValue != $correctDistribId) {
                                $rowUpdates['distributeur_id'] = $correctDistribId;
                                $this->distribIdUpdatedCount++;
                            }
                        } elseif (!$this->distributorMap->contains($currentDistribValue)) {
                            // Not a known matricule AND not a known primary key ID = Orphan
                            $this->distribIdOrphanCount++;
                            $this->orphanDetails[] = ['row_id' => $row->id, 'column' => 'distributeur_id', 'missing_matricule' => $currentDistribValue];
                            Log::warning("FixHistoryIds: Orphan distributeur_id found in {$this->targetTable} (ID: {$row->id}). Value '{$currentDistribValue}' is invalid.");
                            // Action: Log only, do not update.
                        }
                        // If it's already the correct primary key ID, do nothing.
                    }

                    // --- Check id_distrib_parent ---
                    $currentParentValue = $row->id_distrib_parent;
                     // Special handling for 0 - convert to NULL
                    if ($currentParentValue === 0) {
                        $rowUpdates['id_distrib_parent'] = null;
                        $this->parentIdUpdatedCount++; // Count correction from 0 as an update
                    } elseif ($currentParentValue !== null) { // Only process non-null, non-zero values
                        $correctParentId = $this->distributorMap->get($currentParentValue);
                        if ($correctParentId !== null) {
                            // Found matricule in map, needs update if not already correct ID
                            if ($currentParentValue != $correctParentId) {
                                $rowUpdates['id_distrib_parent'] = $correctParentId;
                                $this->parentIdUpdatedCount++;
                            }
                        } elseif (!$this->distributorMap->contains($currentParentValue)) {
                            // Not a known matricule AND not a known primary key ID = Orphan
                            $this->parentIdOrphanCount++;
                             $this->orphanDetails[] = ['row_id' => $row->id, 'column' => 'id_distrib_parent', 'missing_matricule' => $currentParentValue];
                            Log::warning("FixHistoryIds: Orphan id_distrib_parent found in {$this->targetTable} (ID: {$row->id}). Value '{$currentParentValue}' is invalid.");
                             // Action: Set to NULL maybe? Or just log. Let's just log for now.
                             // $rowUpdates['id_distrib_parent'] = null; // Uncomment to set orphans to NULL
                        }
                        // If it's already the correct primary key ID, do nothing.
                    }

                    // Add updates for this row if any changes were detected
                    if (!empty($rowUpdates)) {
                        $updates[$row->id] = $rowUpdates;
                    }

                } // End foreach row in chunk

                // Apply batch updates for this chunk
                if (!empty($updates)) {
                    foreach ($updates as $rowId => $updateData) {
                        try {
                            DB::table($this->targetTable)->where('id', $rowId)->update($updateData);
                        } catch (\Exception $e) {
                            Log::error("FixHistoryIds: Failed to update row ID {$rowId} in {$this->targetTable}: ".$e->getMessage()." | Data: ".json_encode($updateData));
                            // Need to adjust counters if update failed
                            if(isset($updateData['distributeur_id'])) $this->distribIdUpdatedCount--;
                            if(isset($updateData['id_distrib_parent'])) $this->parentIdUpdatedCount--;
                            $this->error("\nUpdate failed for {$this->targetTable} ID {$rowId}: ".$e->getMessage());
                        }
                    }
                }

                $progressBar->advance($rows->count());

            }); // End chunkById

        $progressBar->finish();
        $this->info("\n  Finished processing {$this->targetTable}.");
    }
}
