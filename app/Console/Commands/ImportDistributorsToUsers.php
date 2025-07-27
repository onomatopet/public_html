<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImportDistributorsToUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-distributors {--force : Skip confirmation} {--chunk=500 : Chunk size for batch processing} {--verify : Verify data integrity after import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimized import from "distributeurs_old" table to "users" table with batch processing.';

    /**
     * Map [ancien_matricule => nouvel_user_id]
     * @var array
     */
    private array $matriculeToNewIdMap = [];

    /**
     * Batch size for processing
     * @var int
     */
    private int $chunkSize;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->chunkSize = (int) $this->option('chunk');

        $this->warn("--- Starting Optimized Distributor Data Import ---");
        $this->line("Chunk size: {$this->chunkSize}");
        $this->line("Reading from 'distributeurs_old' table on connection 'db_first'.");
        $this->line("Writing to 'users' table on default connection.");

        // Vérifications préliminaires
        if (!$this->performPreChecks()) {
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("This will CREATE/UPDATE users. Ensure proper backup. Proceed?")) {
            $this->comment("Operation cancelled.");
            return self::FAILURE;
        }

        $startTime = microtime(true);

        try {
            // Désactiver les vérifications de clés étrangères temporairement
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Optimiser les paramètres MySQL pour l'import en masse
            $this->optimizeMySQLSettings();

            // Étape 1: Import des utilisateurs en masse
            $totalCount = $this->importUsersInBatches();

            // Étape 2: Mise à jour des relations parentales
            $this->updateParentRelationships();

            // Réactiver les vérifications
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Vérification de l'intégrité si demandée
            if ($this->option('verify')) {
                $this->verifyDataIntegrity($totalCount);
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info("\n<fg=green>Import completed successfully in {$executionTime} seconds!</>");
            $this->info("Processed {$totalCount} distributors.");

        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->error("\nImport failed: " . $e->getMessage());
            Log::critical("Distributor import failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Perform preliminary checks
     */
    private function performPreChecks(): bool
    {
        if (!Schema::connection('db_first')->hasTable('distributeurs_old')) {
            $this->error("Source table 'distributeurs_old' not found on 'db_first' connection.");
            return false;
        }

        // Vérifier la mémoire disponible
        $memoryLimit = ini_get('memory_limit');
        $this->line("PHP Memory limit: {$memoryLimit}");

        // Compter les enregistrements source
        $sourceCount = DB::connection('db_first')->table('distributeurs_old')->count();
        $this->line("Source records: {$sourceCount}");

        if ($sourceCount === 0) {
            $this->warn("No records found in source table.");
            return false;
        }

        // Vérifier le schéma de la table users de destination
        $this->checkDestinationSchema();

        return true;
    }

    /**
     * Check destination table schema
     */
    private function checkDestinationSchema(): void
    {
        $this->line("\nChecking destination table schema...");

        $userColumns = Schema::getColumnListing('users');
        $this->line("Available columns in users table: " . implode(', ', $userColumns));

        // Colonnes requises selon votre schéma
        $requiredColumns = ['id', 'name', 'email', 'password', 'distributeur_id'];
        $missingColumns = array_diff($requiredColumns, $userColumns);

        if (!empty($missingColumns)) {
            $this->error("Missing required columns in users table: " . implode(', ', $missingColumns));
            throw new \Exception("Missing required columns in destination table");
        }

        $this->info("✓ Destination table schema validated");
        $this->line("Note: Only basic user data will be imported (name, email, distributeur_id)");
        $this->line("Other distributor fields will be stored in separate tables or ignored");
    }

    /**
     * Optimize MySQL settings for bulk import
     */
    private function optimizeMySQLSettings(): void
    {
        $optimizations = [
            'SET autocommit=0',
            'SET unique_checks=0',
            'SET sql_log_bin=0'
        ];

        foreach ($optimizations as $query) {
            try {
                DB::statement($query);
            } catch (\Exception $e) {
                $this->line("Note: Could not apply optimization: {$query}");
            }
        }
    }

    /**
     * Import users in batches using efficient bulk operations
     */
    private function importUsersInBatches(): int
    {
        $this->line("\nStep 1: Importing users in batches...");

        $totalCount = DB::connection('db_first')->table('distributeurs_old')->count();
        $this->line("Total records to process: {$totalCount}");

        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $processedCount = 0;
        $chunkNumber = 0;

        // Traitement par chunks pour éviter les problèmes de mémoire
        DB::connection('db_first')->table('distributeurs_old')
            ->orderBy('id')
            ->chunk($this->chunkSize, function ($distributors) use (&$processedCount, &$chunkNumber, $progressBar, $totalCount) {
                $chunkNumber++;
                $chunkSize = count($distributors);

                $this->line("\nProcessing chunk {$chunkNumber} ({$chunkSize} records)...");

                $usersData = [];
                $now = now();

                foreach ($distributors as $distributor) {
                    $usersData[] = [
                        'name' => trim(($distributor->pnom_distributeur ?? '') . ' ' . ($distributor->nom_distributeur ?? '')),
                        'distributeur_id' => $distributor->distributeur_id,
                        'email' => "user_{$distributor->distributeur_id}@example.com",
                        'password' => Hash::make('default_password_' . $distributor->distributeur_id),
                        'email_verified_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Construire la map pour les relations parentales
                    $this->matriculeToNewIdMap[$distributor->distributeur_id] = null; // On assignera l'ID plus tard
                }

                // Bulk insert avec gestion des doublons
                $startTime = microtime(true);
                $this->bulkUpsertUsers($usersData);
                $upsertTime = round(microtime(true) - $startTime, 2);

                // Récupérer les IDs générés et mettre à jour la map
                $startTime = microtime(true);
                $this->updateMatriculeMap($distributors);
                $mapTime = round(microtime(true) - $startTime, 2);

                $processedCount += $chunkSize;
                $progressBar->advance($chunkSize);

                $this->line("  → Upsert: {$upsertTime}s, Map update: {$mapTime}s");
                $this->line("  → Progress: {$processedCount}/{$totalCount} (" . round(($processedCount/$totalCount)*100, 1) . "%)");
            });

        $progressBar->finish();
        $this->info("\nStep 1 completed. Processed {$processedCount} users.");

        return $processedCount;
    }

    /**
     * Bulk upsert users with conflict resolution
     */
    private function bulkUpsertUsers(array $usersData): void
    {
        if (empty($usersData)) return;

        // Utiliser upsert pour gérer les conflits de manière efficace
        // Colonnes correspondant exactement à votre table users
        DB::table('users')->upsert(
            $usersData,
            ['distributeur_id'], // Colonne unique pour détecter les conflits
            ['name', 'email', 'email_verified_at', 'updated_at'] // Colonnes à mettre à jour en cas de conflit
        );
    }

    /**
     * Update the matricule to ID mapping
     */
    private function updateMatriculeMap($distributors): void
    {
        $distributeurIds = collect($distributors)->pluck('distributeur_id')->toArray();

        $users = DB::table('users')
            ->whereIn('distributeur_id', $distributeurIds)
            ->select('id', 'distributeur_id')
            ->get();

        foreach ($users as $user) {
            $this->matriculeToNewIdMap[$user->distributeur_id] = $user->id;
        }
    }

    /**
     * Update parent relationships efficiently
     */
    private function updateParentRelationships(): void
    {
        $this->line("\nStep 2: Updating parent relationships...");

        // Récupérer toutes les relations parent-enfant en une seule requête
        $parentRelations = DB::connection('db_first')->table('distributeurs_old')
            ->whereNotNull('id_distrib_parent')
            ->where('id_distrib_parent', '!=', 0)
            ->select('distributeur_id', 'id_distrib_parent')
            ->get();

        $totalRelations = $parentRelations->count();
        $this->line("Found {$totalRelations} parent-child relationships to process.");

        if ($totalRelations === 0) {
            $this->info("No parent relationships to update.");
            return;
        }

        $progressBar = $this->output->createProgressBar($totalRelations);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $updateCases = [];
        $validChildIds = [];
        $orphanCount = 0;
        $processed = 0;

        foreach ($parentRelations as $relation) {
            $childId = $this->matriculeToNewIdMap[$relation->distributeur_id] ?? null;
            $parentId = $this->matriculeToNewIdMap[$relation->id_distrib_parent] ?? null;

            if ($childId && $parentId) {
                $updateCases[] = "WHEN {$childId} THEN {$parentId}";
                $validChildIds[] = $childId;
            } else {
                $orphanCount++;
                Log::warning("Orphan relationship", [
                    'child_matricule' => $relation->distributeur_id,
                    'parent_matricule' => $relation->id_distrib_parent
                ]);
            }

            $processed++;
            $progressBar->advance();

            // Affichage périodique du progrès
            if ($processed % 1000 === 0) {
                $this->line("\n  → Processed {$processed}/{$totalRelations} relationships...");
            }
        }

        $progressBar->finish();

        // Mise à jour en masse avec CASE WHEN
        if (!empty($updateCases)) {
            $this->line("\nExecuting bulk update for " . count($validChildIds) . " relationships...");
            $startTime = microtime(true);

            $caseStatement = implode(' ', $updateCases);
            $childIdsList = implode(',', $validChildIds);

            $sql = "UPDATE users SET id_distrib_parent = CASE id {$caseStatement} END WHERE id IN ({$childIdsList})";
            DB::statement($sql);

            $updateTime = round(microtime(true) - $startTime, 2);
            $this->info("✓ Updated " . count($validChildIds) . " parent relationships in {$updateTime}s");
        }

        if ($orphanCount > 0) {
            $this->warn("⚠ Found {$orphanCount} orphaned relationships (logged for review)");
        }
    }

    /**
     * Verify data integrity after import
     */
    private function verifyDataIntegrity(int $expectedCount): void
    {
        $this->line("\nVerifying data integrity...");

        $importedCount = DB::table('users')->whereNotNull('distributeur_id')->count();
        $parentLinksCount = DB::table('users')->whereNotNull('id_distrib_parent')->count();

        $this->line("Expected records: {$expectedCount}");
        $this->line("Imported records: {$importedCount}");
        $this->line("Parent links created: {$parentLinksCount}");

        if ($importedCount !== $expectedCount) {
            $this->warn("Warning: Record count mismatch!");
        } else {
            $this->info("✓ Data integrity check passed.");
        }

        // Vérifier les orphelins
        $orphans = DB::table('users')
            ->whereNotNull('id_distrib_parent')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users as parent')
                    ->whereColumn('parent.id', 'users.id_distrib_parent');
            })
            ->count();

        if ($orphans > 0) {
            $this->warn("Warning: {$orphans} orphaned records found!");
        } else {
            $this->info("✓ No orphaned relationships found.");
        }
    }
}
