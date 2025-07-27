<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Nécessaire pour DB::select et DB::statement

return new class extends Migration
{
    /**
     * Helper pour supprimer une FK via DB::statement en trouvant son nom d'abord.
     */
    private function dropForeignKeySQL(string $tableName, string $columnName): void
    {
        $keyIdentifier = "{$tableName}_{$columnName}_foreign"; // Nom conventionnel
        Log::debug("Attempting to find and drop FK for {$tableName}.{$columnName}...");

        // 1. Trouver le nom réel de la contrainte (plus robuste que de deviner)
        $constraintName = null;
        try {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL;
            ", [$tableName, $columnName]);

            if (!empty($foreignKeys)) {
                $constraintName = $foreignKeys[0]->CONSTRAINT_NAME;
                Log::info("Found existing FK constraint named '{$constraintName}' for {$tableName}.{$columnName}.");
            } else {
                 Log::info("No existing FK constraint found for {$tableName}.{$columnName}.");
                 return; // Rien à supprimer
            }
        } catch (\Exception $e) {
             Log::error("Error querying information_schema for FK on {$tableName}.{$columnName}: " . $e->getMessage());
             // Ne pas continuer si on ne peut pas vérifier
             return;
        }

        // 2. Supprimer la contrainte par son nom si trouvée
        if ($constraintName) {
            try {
                DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
                Log::info("Successfully dropped FK '{$constraintName}' for {$tableName}.{$columnName}.");
            } catch (\Exception $e) {
                // Logguer si la suppression échoue même avec le nom correct (inhabituel)
                Log::error("Error executing DROP FOREIGN KEY `{$constraintName}` on {$tableName}: " . $e->getMessage());
                // Ne pas relancer pour permettre à la migration de continuer si possible
            }
        }
    }

    public function up(): void
    {
        Log::info('Finalizing database structure: Dropping old cols & adding FKs (v5 - DB::statement for FK drop)...');

        // --- 1. Nettoyer la table achats ---
        // ... (inchangé : suppression pointvaleur, montant) ...
        Log::info('Step 1: Dropping old columns from achats...');
        Schema::table('achats', function (Blueprint $table) { /* ... drop columns ... */ });
        Log::info('Step 1: Finished dropping old columns.');

        // --- 2. TENTER DE SUPPRIMER TOUTES LES FK POTENTIELLES (via DB::statement) ---
        Log::info('Step 2: Preemptively dropping potentially existing FKs using DB::statement...');

        $this->dropForeignKeySQL('bonuses', 'distributeur_id');
        $this->dropForeignKeySQL('level_currents', 'id_distrib_parent');
        $this->dropForeignKeySQL('level_currents', 'distributeur_id');
        $this->dropForeignKeySQL('achats', 'products_id');
        $this->dropForeignKeySQL('achats', 'distributeur_id');
        $this->dropForeignKeySQL('products', 'pointvaleur_id');
        $this->dropForeignKeySQL('products', 'category_id');
        $this->dropForeignKeySQL('distributeurs', 'id_distrib_parent');

        Log::info('Step 2: Finished attempting to drop existing FKs.');


        // --- 3. Ajouter les clés étrangères ---
        // Le code ici reste le même, utilisant Schema Builder pour l'ajout
        Log::info('Step 3: Adding Foreign Key constraints...');
        Schema::table('distributeurs', function (Blueprint $table) { /* ... ajout FK id_distrib_parent ... */ });
        Schema::table('products', function (Blueprint $table) { /* ... ajout FK category_id, pointvaleur_id ... */ });
        Schema::table('achats', function (Blueprint $table) { /* ... ajout FK distributeur_id, products_id ... */ });
        Schema::table('level_currents', function (Blueprint $table) { /* ... ajout FK distributeur_id, id_distrib_parent ... */ });
        Schema::table('bonuses', function (Blueprint $table) { /* ... ajout FK distributeur_id ... */ });

        Log::info('Database structure finalized with Foreign Keys.');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Log::warning('Reverting final structure changes (Dropping FKs, Re-adding columns)...');
        // Utiliser la même méthode de suppression pour down
        $this->dropForeignKeySQL('bonuses', 'distributeur_id');
        $this->dropForeignKeySQL('level_currents', 'id_distrib_parent');
        $this->dropForeignKeySQL('level_currents', 'distributeur_id');
        $this->dropForeignKeySQL('achats', 'products_id');
        $this->dropForeignKeySQL('achats', 'distributeur_id');
        $this->dropForeignKeySQL('products', 'pointvaleur_id');
        $this->dropForeignKeySQL('products', 'category_id');
        $this->dropForeignKeySQL('distributeurs', 'id_distrib_parent');

        // ... Recréer colonnes achats ...
        Schema::table('achats', function (Blueprint $table) {
            if (!Schema::hasColumn('achats', 'montant')) { $table->double('montant', 12, 2)->nullable(); }
            if (!Schema::hasColumn('achats', 'pointvaleur')) { $table->bigInteger('pointvaleur')->nullable(); }
        });
        Log::info('Reverted FKs and re-added old achats columns (data not restored).');
    }
};
