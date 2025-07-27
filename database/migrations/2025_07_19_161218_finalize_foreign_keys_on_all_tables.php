<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. D'abord, nettoyer les données invalides
        $this->cleanInvalidReferences();

        // 2. Vérifier si la contrainte existe déjà
        $constraintName = 'distributeurs_id_distrib_parent_foreign';
        $tableSchema = DB::connection()->getDatabaseName();

        $constraintExists = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = 'distributeurs'
            AND CONSTRAINT_NAME = ?
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$tableSchema, $constraintName]);

        if ($constraintExists[0]->count == 0) {
            // La contrainte n'existe pas, on peut la créer
            try {
                Schema::table('distributeurs', function (Blueprint $table) {
                    $table->foreign('id_distrib_parent')
                        ->references('id')
                        ->on('distributeurs')
                        ->onDelete('set null')
                        ->onUpdate('cascade');
                });

                $this->info("Contrainte de clé étrangère créée avec succès.");
            } catch (\Exception $e) {
                $this->error("Erreur lors de la création de la contrainte : " . $e->getMessage());

                // En cas d'erreur, essayer une approche différente
                DB::statement('SET FOREIGN_KEY_CHECKS = 0');

                try {
                    DB::statement('
                        ALTER TABLE distributeurs
                        ADD CONSTRAINT distributeurs_id_distrib_parent_foreign
                        FOREIGN KEY (id_distrib_parent)
                        REFERENCES distributeurs(id)
                        ON DELETE SET NULL
                        ON UPDATE CASCADE
                    ');
                } catch (\Exception $e2) {
                    $this->error("La contrainte existe peut-être déjà ou il y a un autre problème : " . $e2->getMessage());
                } finally {
                    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                }
            }
        } else {
            $this->info("La contrainte existe déjà, pas de modification nécessaire.");
        }
    }

    /**
     * Nettoyer les références invalides
     */
    private function cleanInvalidReferences(): void
    {
        // Désactiver temporairement les contraintes
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            // Trouver les distributeurs avec des parents invalides
            $invalidReferences = DB::select("
                SELECT d1.id, d1.distributeur_id, d1.id_distrib_parent
                FROM distributeurs d1
                LEFT JOIN distributeurs d2 ON d1.id_distrib_parent = d2.id
                WHERE d1.id_distrib_parent IS NOT NULL
                AND d2.id IS NULL
            ");

            if (count($invalidReferences) > 0) {
                $this->info("Trouvé " . count($invalidReferences) . " références invalides.");

                // Créer une table de sauvegarde si elle n'existe pas
                DB::statement("
                    CREATE TABLE IF NOT EXISTS distributeurs_orphelins_backup (
                        id INT,
                        distributeur_id VARCHAR(255),
                        id_distrib_parent INT,
                        corrected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_id (id)
                    )
                ");

                // Sauvegarder les références invalides
                foreach ($invalidReferences as $ref) {
                    DB::table('distributeurs_orphelins_backup')->insert([
                        'id' => $ref->id,
                        'distributeur_id' => $ref->distributeur_id,
                        'id_distrib_parent' => $ref->id_distrib_parent,
                    ]);
                }

                // Corriger les références invalides
                DB::statement("
                    UPDATE distributeurs d1
                    LEFT JOIN distributeurs d2 ON d1.id_distrib_parent = d2.id
                    SET d1.id_distrib_parent = NULL
                    WHERE d1.id_distrib_parent IS NOT NULL
                    AND d2.id IS NULL
                ");

                $this->info("Références invalides corrigées (mises à NULL).");
            } else {
                $this->info("Aucune référence invalide trouvée.");
            }

        } finally {
            // Réactiver les contraintes
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Vérifier si la contrainte existe avant d'essayer de la supprimer
        $constraintName = 'distributeurs_id_distrib_parent_foreign';
        $tableSchema = DB::connection()->getDatabaseName();

        $constraintExists = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = 'distributeurs'
            AND CONSTRAINT_NAME = ?
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$tableSchema, $constraintName]);

        if ($constraintExists[0]->count > 0) {
            Schema::table('distributeurs', function (Blueprint $table) {
                $table->dropForeign(['id_distrib_parent']);
            });
        }
    }

    /**
     * Afficher un message d'information
     */
    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "[INFO] " . $message . PHP_EOL;
        }
    }

    /**
     * Afficher un message d'erreur
     */
    private function error(string $message): void
    {
        if (app()->runningInConsole()) {
            echo "[ERROR] " . $message . PHP_EOL;
        }
    }
};
