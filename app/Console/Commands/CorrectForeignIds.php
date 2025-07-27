<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CorrectForeignIds extends Command
{
    protected $signature = 'app:correct-foreign-ids {--force : Skip confirmation} {--chunk=500 : Chunk size}';

    protected $description = 'Corrects columns storing distributor matricules to store primary IDs. Orphaned parent links are set to NULL.';

    private ?Collection $matriculeToIdMap = null;
    private array $orphanDetails = [];
    private array $tablesToCorrect = [
        'distributeurs'             => ['id_distrib_parent'],
        'level_currents'            => ['distributeur_id', 'id_distrib_parent'],
        'level_current_histories'   => ['distributeur_id', 'id_distrib_parent'],
        'achats'                    => ['distributeur_id'],
        'bonuses'                   => ['distributeur_id'],
    ];

    public function handle(): int
    {
        $this->warn("--- Starting In-Place Foreign ID Correction Process ---");
        $this->warn("This will modify data to use Primary IDs. Orphaned PARENT links will be set to NULL.");
        $this->warn("Orphaned main distributor links (in achats, bonuses, etc.) will be REPORTED as errors.");
        if (!$this->option('force') && !$this->confirm("BACKUP YOUR DATABASE FIRST! Proceed?")) {
            $this->comment("Operation cancelled.");
            return self::FAILURE;
        }

        $this->line("\nBuilding matricule-to-ID map from 'distributeurs' table...");
        if (!$this->buildMatriculeMap()) {
            return self::FAILURE;
        }
        $this->info("Map built successfully with " . $this->matriculeToIdMap->count() . " entries.");

        $this->line("\nUpdating foreign key columns...");
        DB::beginTransaction();
        try {
            foreach ($this->tablesToCorrect as $tableName => $columns) {
                $this->processTable($tableName, $columns);
            }

            // Vérifier les orphelins CRITIQUES avant de committer
            if (!empty($this->orphanDetails)) {
                DB::rollBack(); // Annuler tout si des orphelins critiques sont trouvés
                $this->displayOrphanReport();
                return self::FAILURE;
            }

            DB::commit(); // Valider si tout est OK
            $this->displayOrphanReport();
            $this->info("<fg=green>Data correction successful. You should now be able to add foreign key constraints.</>");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\nAn error occurred. All changes have been rolled back.");
            $this->error($e->getMessage());
            Log::critical("Foreign ID correction failed: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function buildMatriculeMap(): bool
    {
        try {
            $this->matriculeToIdMap = DB::table('distributeurs')->whereNotNull('distributeur_id')->where('distributeur_id', '!=', 0)->pluck('id', 'distributeur_id');
            if ($this->matriculeToIdMap === null) { $this->error("Failed to build map."); return false; }
        } catch (\Exception $e) {
            $this->error("Failed to build matricule map: " . $e->getMessage()); return false;
        }
        return true;
    }

    private function processTable(string $tableName, array $columnsToCorrect): void
    {
        $this->info("  Processing table: <fg=yellow>{$tableName}</>");
        $totalRows = DB::table($tableName)->count();
        if ($totalRows === 0) { $this->line("    Table is empty. Skipping."); return; }

        $progressBar = $this->output->createProgressBar($totalRows);
        $progressBar->start();
        $chunkSize = (int)$this->option('chunk');
        $selectColumns = array_merge(['id'], $columnsToCorrect);

        DB::table($tableName)->select($selectColumns)->orderBy('id')
            ->chunkById($chunkSize, function (Collection $rows) use ($tableName, $columnsToCorrect, $progressBar) {
                foreach ($rows as $row) {
                    $rowUpdates = [];
                    foreach ($columnsToCorrect as $columnName) {
                        if (!property_exists($row, $columnName)) continue;
                        $currentValue = $row->$columnName;

                        if ($currentValue === null || !is_numeric($currentValue)) continue;
                        if ($currentValue == 0) {
                            $rowUpdates[$columnName] = null;
                            continue;
                        }

                        $correctId = $this->matriculeToIdMap->get($currentValue);

                        // --- NOUVELLE LOGIQUE DE GESTION DES ORPHELINS ---
                        if ($correctId !== null) {
                            // C'est un matricule valide, on le traduit en ID
                            if ($currentValue != $correctId) {
                                $rowUpdates[$columnName] = $correctId;
                            }
                        } else {
                            // C'est un orphelin (matricule non trouvé dans la map)

                            // Si c'est une colonne de PARENT, on met à NULL
                            if (str_contains($columnName, 'parent')) { // Simple check sur le nom de la colonne
                                $rowUpdates[$columnName] = null;
                                Log::info("Orphan PARENT link found in {$tableName}: RowID={$row->id}, Col={$columnName}, Matricule={$currentValue}. SETTING TO NULL.");
                            } else {
                                // Sinon (ex: distributeur_id), c'est un orphelin CRITIQUE. On le signale.
                                $this->orphanDetails[$tableName][] = [
                                    'row_id' => $row->id,
                                    'column' => $columnName,
                                    'invalid_value' => $currentValue
                                ];
                                Log::warning("CRITICAL Orphan found in {$tableName}: RowID={$row->id}, Col={$columnName}, Matricule={$currentValue}. This must be fixed manually.");
                            }
                        }
                        // --- FIN DE LA NOUVELLE LOGIQUE ---
                    }
                    if (!empty($rowUpdates)) {
                        try {
                            DB::table($tableName)->where('id', $row->id)->update($rowUpdates);
                        } catch (\Exception $e) {
                             Log::error("Failed to update RowID {$row->id} in {$tableName}: " . $e->getMessage());
                        }
                    }
                }
                $progressBar->advance($rows->count());
            });
        $progressBar->finish();
        $this->info("\n    Finished processing {$tableName}.");
    }

    private function displayOrphanReport(): void
    {
        if (!empty($this->orphanDetails)) {
            $totalOrphans = 0;
            $this->error("\n<fg=red>CRITICAL Orphan Records Found (Changes have been ROLLED BACK):</>");
            $this->error("These are records (achats, bonuses, etc.) linked to a main distributor that does not exist.");
            foreach($this->orphanDetails as $table => $orphans) {
                 $this->warn("  Table '{$table}':");
                 foreach($orphans as $orphan) {
                      $this->warn("    - Row ID: {$orphan['row_id']}, Column: {$orphan['column']}, Invalid Matricule: {$orphan['invalid_value']}");
                      $totalOrphans++;
                 }
            }
             $this->error("\nTotal: {$totalOrphans} CRITICAL orphan records found.");
             $this->error("You MUST DELETE these specific records or INSERT the missing distributors before retrying.");
        } else {
            $this->info("\nNo critical orphan records found. All parent links have been corrected.");
        }
    }
}
