<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class UpdateParentLinksInUsers extends Command
{
    protected $signature = 'app:update-parent-links {--force : Skip confirmation} {--chunk=500 : Number of records to process per batch.}';
    protected $description = 'Updates the id_distrib_parent in the users table based on the old distributeurs table.';

    private array $matriculeToNewIdMap = [];

    public function handle(): int
    {
        $this->warn("--- Starting Parent Relationship Update in Users Table ---");
        if (!Schema::connection('db_first')->hasTable('distributeurs_old')) {
            $this->error("Source table 'distributeurs_old' not found. Please ensure it exists.");
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("This will update 'id_distrib_parent' for all users. Proceed?")) {
            $this->comment("Operation cancelled.");
            return self::FAILURE;
        }

        // --- ÉTAPE 1: Reconstruire la map matricule => nouvel ID ---
        $this->line("Building matricule-to-new-ID map from 'users' table...");
        try {
            $this->matriculeToNewIdMap = User::whereNotNull('distributeur_id')->pluck('id', 'distributeur_id')->all();
            if (empty($this->matriculeToNewIdMap)) {
                $this->error("The matricule-to-ID map is empty. Did the import command run correctly?");
                return self::FAILURE;
            }
            $this->info("Map built successfully with " . count($this->matriculeToNewIdMap) . " entries.");
        } catch (\Exception $e) {
            $this->error("Failed to build map: " . $e->getMessage());
            return self::FAILURE;
        }


        // --- ÉTAPE 2: Mettre à jour les liens de parenté par lots ---
        $this->line("Updating parent relationships (id_distrib_parent)...");
        $chunkSize = (int)$this->option('chunk');
        $totalCount = DB::connection('db_first')->table('distributeurs_old')->count();
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        try {
            DB::connection('db_first')->table('distributeurs_old')->orderBy('id')->chunk($chunkSize, function ($oldDistributorsChunk) use ($progressBar) {
                DB::connection('mysql')->beginTransaction(); // Transaction par lot
                try {
                    foreach ($oldDistributorsChunk as $oldDistributor) {
                        if (!empty($oldDistributor->id_distrib_parent) && $oldDistributor->id_distrib_parent != 0) {
                            $parentMatricule = $oldDistributor->id_distrib_parent;
                            $childMatricule = $oldDistributor->distributeur_id;

                            $newParentId = $this->matriculeToNewIdMap[$parentMatricule] ?? null;
                            $newChildId = $this->matriculeToNewIdMap[$childMatricule] ?? null;

                            if ($newParentId && $newChildId) {
                                // Mettre à jour l'enfant avec l'ID de son nouveau parent
                                User::where('id', $newChildId)->update(['id_distrib_parent' => $newParentId]);
                            } else {
                                Log::warning("Orphan link detected: Child matricule {$childMatricule}, Parent matricule '{$parentMatricule}' not found in users map.");
                            }
                        }
                        $progressBar->advance();
                    }
                    DB::connection('mysql')->commit(); // Valider la transaction pour ce lot
                } catch (\Exception $e) {
                    DB::connection('mysql')->rollBack();
                    $this->error("\nAn error occurred during a chunk update. This chunk was rolled back. Error: " . $e->getMessage());
                    Log::error("Chunk parent link update failed: " . $e->getMessage());
                    $progressBar->advance(count($oldDistributorsChunk)); // Avancer pour ne pas bloquer
                }
            });

            $progressBar->finish();
            $this->info("\nParent relationship update complete.");

        } catch (\Exception $e) {
            $this->error("\nA critical error occurred during the parent update process.");
            $this->error($e->getMessage());
            Log::critical("Parent link update failed critically: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("\n<fg=green>Parent link update process finished successfully!</>");
        return self::SUCCESS;
    }
}
