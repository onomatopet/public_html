<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Préparer la table distributeurs pour la correction des IDs.
     * Ajoute UNIQUE, rend parent nullable, nettoie colonnes, ajoute index.
     * NE PAS AJOUTER LA FK PARENT ICI.
     */
    public function up(): void
    {
        Log::info('Modifying distributeurs table (Step 1: Structure Prep)...');
        Schema::table('distributeurs', function (Blueprint $table) {

            // S'assurer que la PK est bien configurée (normalement déjà le cas)
            // $table->id()->change(); // Pas sûr de pouvoir changer une PK existante facilement

            // 1. Changer etoiles_id en SMALLINT si approprié (max 65535)
            // Attention aux données existantes! Commentez si le type actuel vous convient.
            $table->smallInteger('etoiles_id')->unsigned()->default(1)->comment('Niveau actuel (dénormalisé)')->change();

            // 2. Default sur rang
            $table->integer('rang')->default(0)->comment('Rang actuel (dénormalisé)')->change();

            // 3. Contrainte UNIQUE sur distributeur_id (Matricule)
            // Assurez-vous d'avoir nettoyé les doublons manuellement avant !
            try {
                $table->unique('distributeur_id', 'distributeurs_distributeur_id_unique');
                Log::info('Added unique constraint on distributeurs.distributeur_id');
            } catch (\Illuminate\Database\QueryException $e) {
                 // Code 1062: Duplicate entry (si nettoyage manuel oublié)
                 // Code 1061: Duplicate key name (si index existe déjà)
                if (isset($e->errorInfo[1]) && ($e->errorInfo[1] == 1062 || $e->errorInfo[1] == 1061)) {
                    Log::warning('Unique constraint/index on distributeur_id already exists or duplicates found: ' . $e->getMessage());
                } else { throw $e; } // Relancer pour autres erreurs
            } catch (\Exception $e) { throw $e; }

            // 4. Rendre id_distrib_parent nullable (Crucial pour la correction 0 -> NULL)
            // Le type actuel bigint(20) est probablement unsigned, on le garde.
            // Le NOT NULL original doit être retiré.
            $table->unsignedBigInteger('id_distrib_parent')->nullable()->comment('Contiendra ID Parent après correction')->change();

            // 5. Supprimer is_children
            if (Schema::hasColumn('distributeurs', 'is_children')) {
                $table->dropColumn('is_children');
                Log::info('Dropped column is_children');
            }

            // 6. Modifier is_indivual_cumul_checked en boolean
             if (Schema::hasColumn('distributeurs', 'is_indivual_cumul_checked')) {
                // Tenter de juste changer le type en TINYINT(1)
                $table->tinyInteger('is_indivual_cumul_checked')->default(0)->comment('Flag interne (ex: cumul vérifié?)')->change();
                Log::info('Changed column is_indivual_cumul_checked to tinyint');
                // Renommer manuellement plus tard si souhaité via phpMyAdmin pour éviter complexité ici.
             }

            // 7. Ajouter des index (ignorer erreur si existe déjà)
            try { $table->index('id_distrib_parent', 'distributeurs_id_distrib_parent_index'); } catch (\Exception $e) { Log::warning('Index on id_distrib_parent creation failed (maybe exists).'); }
            try { $table->index('etoiles_id', 'distributeurs_etoiles_id_index'); } catch (\Exception $e) { Log::warning('Index on etoiles_id creation failed (maybe exists).'); }
            // L'index unique sur distributeur_id a déjà été tenté

        });
        Log::info('Finished modifying distributeurs table (Step 1).');
    }

    /**
     * Reverse the migrations.
     * Essaye d'annuler les changements structurels.
     */
    public function down(): void
    {
        Log::warning('Reverting distributeurs table modifications (Step 1 - Limited Rollback)...');
        Schema::table('distributeurs', function (Blueprint $table) {
            // Supprimer index (ignorer erreur si inexistant)
            try { $table->dropIndex('distributeurs_etoiles_id_index'); } catch (\Exception $e) {}
            try { $table->dropIndex('distributeurs_id_distrib_parent_index'); } catch (\Exception $e) {}

            // Remettre is_indivual_cumul_checked en ENUM ?
             if (Schema::hasColumn('distributeurs', 'is_indivual_cumul_checked')) {
                 $table->enum('is_indivual_cumul_checked', ['on', 'off'])->default('off')->change();
             }

            // Recréer is_children
             if (!Schema::hasColumn('distributeurs', 'is_children')) {
                 $table->enum('is_children', ['on', 'off'])->default('off')->after('id_distrib_parent'); // Ajuster position
             }

            // Remettre id_distrib_parent NOT NULL ? Risqué si des NULL ont été introduits
             // $table->unsignedBigInteger('id_distrib_parent')->nullable(false)->change();

            // Supprimer unique sur distributeur_id
             try { $table->dropUnique('distributeurs_distributeur_id_unique'); } catch (\Exception $e) {}

            // Remettre rang sans default?
             $table->integer('rang')->change();

            // Remettre etoiles_id en BIGINT ? Risqué.
             // $table->bigInteger('etoiles_id')->unsigned()->change();
        });
    }
};
