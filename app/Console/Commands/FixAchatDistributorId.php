<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class FixAchatDistributorId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-achat-distributor-id {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrects achats.distributeur_id column to contain primary key ID instead of matricule';

    /**
     * Table to process.
     * @var string
     */
    protected string $targetTable = 'achats';

    /**
     * Lookup map [matricule => primary_key_id].
     * @var Collection|null
     */
    protected ?Collection $matriculeToIdMap = null;

    /**
     * Counters for reporting.
     */
    protected int $totalRowsChecked = 0;
    protected int $rowsUpdatedCount = 0;
    protected int $orphanRowsCount = 0;
    protected array $orphanDetails = []; // [achat_id => matricule_introuvable]


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info("Starting Achats Distributor ID Correction Process for table '{$this->targetTable}'...");

        if (!$this->option('force') && !$this->confirm("This command will modify the 'distributeur_id' column in '{$this->targetTable}'. BACKUP YOUR DATABASE FIRST! Are you sure? [y/N]", false)) {
            $this->comment('Operation cancelled.');
            return self::FAILURE;
        }

        // --- 1. Build the Distributor Lookup Map ---
        $this->info('Building distributor matricule-to-id map...');
        if (!$this->buildDistributorMap()) {
            return self::FAILURE;
        }
        $this->info('Distributor map built successfully (' . $this->matriculeToIdMap->count() . ' entries).');


        // --- 2. Process the Table ---
        $this->info("Processing table: {$this->targetTable}...");
        $this->processTable();


        // --- 3. Final Report ---
        $this->info('------------------------------------------');
        $this->info('Achats Distributor ID Correction Process Finished.');
        $this->info("Total rows checked: {$this->totalRowsChecked}");
        $this->info("Rows updated: {$this->rowsUpdatedCount}");
        $this->warn("Orphan rows found (matricule in 'distributeur_id' not found in 'distributeurs' table): {$this->orphanRowsCount}");

        if (!empty($this->orphanDetails)) {
            $this->warn('Orphan details (Achat Row ID -> Missing Matricule):');
            foreach($this->orphanDetails as $achatId => $missingMatricule) {
                $this->warn("- ID {$achatId} -> Matricule {$missingMatricule}");
            }
            $this->warn('These orphan rows were NOT updated and will likely cause FK constraint failure if not handled.');
        }
         $this->info('------------------------------------------');

        if ($this->orphanRowsCount > 0) {
            $this->error("Process completed with {$this->orphanRowsCount} orphan rows detected. Foreign key constraint WILL FAIL if these rows are not corrected or deleted.");
            return self::FAILURE; // Indicate failure if orphans found
        }

        return self::SUCCESS;
    }

    /**
     * Fetches distributeur_id (matricule) and id from distributeurs table
     * and builds the lookup map.
     *
     * @return bool True on success, false on failure.
     */
    protected function buildDistributorMap(): bool
    {
        // Identique aux commandes précédentes
        try {
            $this->matriculeToIdMap = DB::table('distributeurs')
                ->pluck('id', 'distributeur_id'); // Clé = Matricule, Valeur = ID Primaire

            if ($this->matriculeToIdMap->has(null) || $this->matriculeToIdMap->has('')) {
                 $this->error('Found NULL or empty matricules (distributeur_id) in the distributeurs table. Please fix data before proceeding.');
                 Log::error('FixAchatDistributorId: Found NULL or empty matricules in distributeurs table.');
                 return false;
            }
            return true;
        } catch (\Exception $e) {
            $this->error('Failed to build distributor map: ' . $e->getMessage());
            Log::error('FixAchatDistributorId: Failed to build distributor map: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Processes the achats table to correct distributor IDs.
     */
    protected function processTable(): void
    {
        $totalRowsToProcess = DB::table($this->targetTable)->count();
         if ($totalRowsToProcess == 0) {
            $this->line("  Table '{$this->targetTable}' is empty.");
            return;
        }

        $progressBar = $this->output->createProgressBar($totalRowsToProcess);
        $progressBar->start();

        DB::table($this->targetTable)
            ->select('id', 'distributeur_id') // Sélectionner l'ID de l'achat et le distributeur_id actuel
            ->orderBy('id') // Important pour chunkById
            ->chunkById(200, function (Collection $rows) use ($progressBar) {

                $updates = []; // [ achat_id => new_primary_key_id ]

                foreach ($rows as $row) {
                    $this->totalRowsChecked++;
                    $currentValue = $row->distributeur_id; // C'est actuellement un matricule (ou potentiellement NULL)

                    if ($currentValue === null) {
                        // Déjà NULL, rien à faire
                        continue;
                    }

                    // Essayer de trouver le matricule dans la map
                    $correctPrimaryKeyId = $this->matriculeToIdMap->get($currentValue);

                    if ($correctPrimaryKeyId !== null) {
                        // Le matricule a été trouvé dans la map des distributeurs
                        // Vérifier si la valeur doit réellement être changée
                        if ($currentValue != $correctPrimaryKeyId) {
                             $updates[$row->id] = $correctPrimaryKeyId; // Planifier la mise à jour
                             $this->rowsUpdatedCount++;
                        } else {
                             // La valeur est déjà l'ID primaire correct (étrange, mais possible)
                        }
                    } else {
                         // Le matricule ($currentValue) n'a pas été trouvé dans la map distributeurs. C'est un orphelin.
                         $this->orphanRowsCount++;
                         $this->orphanDetails[$row->id] = $currentValue; // Stocker l'ID de l'achat et le matricule manquant
                         Log::warning("FixAchatDistributorId: Orphan row found in {$this->targetTable} (ID: {$row->id}). Matricule '{$currentValue}' in 'distributeur_id' not found in 'distributeurs' map.");
                         // NE PAS METTRE À JOUR CETTE LIGNE POUR L'INSTANT. Elle DOIT être corrigée/supprimée manuellement.
                    }
                } // End foreach row in chunk

                // Appliquer les mises à jour pour ce chunk
                if (!empty($updates)) {
                     foreach ($updates as $achatId => $newPrimaryKeyId) {
                         try {
                            DB::table($this->targetTable)->where('id', $achatId)->update(['distributeur_id' => $newPrimaryKeyId]);
                        } catch (\Exception $e) {
                             Log::error("FixAchatDistributorId: Failed to update distributeur_id for {$this->targetTable} ID {$achatId}: ".$e->getMessage());
                             $this->rowsUpdatedCount--; // Décrémenter car l'update a échoué
                             $this->error("\nUpdate failed for {$this->targetTable} ID {$achatId}: ".$e->getMessage());
                             // Re-ajouter aux orphelins? Ou simplement logguer.
                             if(!isset($this->orphanDetails[$achatId])) { // Eviter doublon si déjà marqué orphelin
                                $this->orphanRowsCount++;
                                $this->orphanDetails[$achatId] = 'UPDATE_FAILED';
                             }
                        }
                    }
                }
                $progressBar->advance($rows->count());

            }); // End chunkById

        $progressBar->finish();
        $this->info("\n  Finished processing {$this->targetTable}.");
    }
}
