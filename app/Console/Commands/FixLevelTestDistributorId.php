<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class FixLevelTestDistributorId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-leveltest-distributor-id {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrects level_current_tests.distributeur_id column to contain primary key ID instead of matricule';

    /**
     * Table to process.
     * @var string
     */
    protected string $targetTable = 'level_current_tests';

    /**
     * Lookup map [matricule => primary_key_id].
     * @var Collection|null
     */
    protected ?Collection $distributorMap = null;

    /**
     * Counters for reporting.
     */
    protected int $totalRowsChecked = 0;
    protected int $rowsUpdatedCount = 0;
    protected int $orphanRowsCount = 0;
    protected array $orphanDetails = [];


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info("Starting LevelCurrentTests Distributor ID Correction Process for table '{$this->targetTable}'...");

        // Confirmation
        if (!$this->option('force') && !$this->confirm("This command will modify the 'distributeur_id' column in '{$this->targetTable}'. Are you sure? [y/N]", false)) {
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
        $this->info('LevelCurrentTests Distributor ID Correction Process Finished.');
        $this->info("Total rows checked: {$this->totalRowsChecked}");
        $this->info("Rows updated: {$this->rowsUpdatedCount}");
        $this->warn("Orphan rows found (matricule in 'distributeur_id' not in 'distributeurs' table): {$this->orphanRowsCount}");

        if (!empty($this->orphanDetails)) {
            $this->warn('Orphan details (Row ID -> Missing Matricule):');
            foreach($this->orphanDetails as $detail) {
                $this->warn("- ID {$detail['row_id']} -> Matricule {$detail['missing_matricule']}");
            }
            $this->warn('These orphan rows likely had their distributeur_id skipped or handled as error.');
        }
         $this->info('------------------------------------------');

        if ($this->orphanRowsCount > 0) {
            $this->error("Process completed with {$this->orphanRowsCount} orphan rows detected. Foreign key constraint might still fail. Please check logs and data.");
            return self::FAILURE; // Indicate failure if orphans found
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
                 Log::error('FixLevelTestDistributorId: Found NULL or empty matricules in distributeurs table.');
                 return false;
            }
            return true;
        } catch (\Exception $e) {
            $this->error('Failed to build distributor map: ' . $e->getMessage());
            Log::error('FixLevelTestDistributorId: Failed to build distributor map: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Processes the target table to correct distributor IDs.
     */
    protected function processTable(): void
    {
        // Estimate total rows for progress bar
        $totalRowsToProcess = DB::table($this->targetTable)->count(); // Check all rows
         if ($totalRowsToProcess == 0) {
            $this->line("  Table '{$this->targetTable}' is empty. Nothing to process.");
            return;
        }

        $progressBar = $this->output->createProgressBar($totalRowsToProcess);
        $progressBar->start();

        // Process in chunks
        DB::table($this->targetTable)
            ->select('id', 'distributeur_id') // Select only needed columns
            ->chunkById(200, function (Collection $rows) use ($progressBar) {

                $updates = [];

                foreach ($rows as $row) {
                    $this->totalRowsChecked++;
                    $currentValue = $row->distributeur_id; // This is currently a matricule or maybe already an ID

                    // Try to find this value in the map keys (matricules)
                    $correctPrimaryKeyId = $this->distributorMap->get($currentValue);

                    if ($correctPrimaryKeyId !== null) {
                        // Found the matricule in the map!
                        // We need to update the row's 'distributeur_id' with the correct primary key ID.
                        // Only update if the value actually needs changing.
                        if ($currentValue != $correctPrimaryKeyId) {
                            $updates[$row->id] = $correctPrimaryKeyId;
                            $this->rowsUpdatedCount++;
                        } else {
                             // Value is already the correct primary key ID, no update needed.
                        }
                    } else {
                         // The value in 'distributeur_id' is NOT a known matricule.
                         // IS IT a valid primary key ID already? Check against map values.
                         if ($this->distributorMap->contains($currentValue)) {
                              // Yes, it's already a correct primary key ID. No action needed.
                         } else {
                              // No, it's not a known matricule AND not a known primary key ID. It's an orphan.
                              $this->orphanRowsCount++;
                              $this->orphanDetails[] = [
                                  'row_id' => $row->id,
                                  'missing_matricule' => $currentValue // The invalid value found
                              ];
                              Log::warning("FixLevelTestDistributorId: Orphan row found in {$this->targetTable} (ID: {$row->id}). Value '{$currentValue}' in 'distributeur_id' is neither a known matricule nor a known primary key ID.");
                              // ACTION FOR ORPHAN: What to do? Delete the row? Set to NULL? Log and skip?
                              // For now, just log and skip the update. FK constraint will fail later if not fixed.
                         }
                    }
                } // End foreach row in chunk

                // Apply batch updates for this chunk
                if (!empty($updates)) {
                     foreach ($updates as $rowId => $newPrimaryKeyId) {
                         try {
                            DB::table($this->targetTable)->where('id', $rowId)->update(['distributeur_id' => $newPrimaryKeyId]);
                        } catch (\Exception $e) {
                             Log::error("FixLevelTestDistributorId: Failed to update distributor_id for {$this->targetTable} ID {$rowId}: ".$e->getMessage());
                             $this->rowsUpdatedCount--; // Decrement counter as update failed
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
