<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Préparer la table level_currents.
     * Change floats en decimal, parent nullable, supprime colonnes redondantes, ajoute index.
     * NE PAS AJOUTER LES FK ICI.
     */
    public function up(): void
    {
        // !! Important: Assurez-vous que la table s'appelle bien 'level_currents' !!
        $tableName = 'level_currents'; // Utiliser une variable pour faciliter adaptation si besoin

        Log::info("Modifying {$tableName} table (Step 1: Structure Prep)...");
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {

            // 1. Changer les types float en decimal
            // Utiliser change() directement
            try {
                $table->decimal('cumul_individuel', 12, 2)->default(0.00)->change();
                $table->decimal('new_cumul', 12, 2)->unsigned()->default(0.00)->change(); // unsignedDecimal n'existe pas, on garde juste unsigned() pour l'info conceptuelle
                $table->decimal('cumul_total', 12, 2)->unsigned()->default(0.00)->change();
                $table->decimal('cumul_collectif', 12, 2)->unsigned()->default(0.00)->change();
                Log::info("Changed float columns to decimal in {$tableName}");
            } catch (\Exception $e) {
                 Log::warning("Could not change float columns to decimal in {$tableName} (maybe already done?): ".$e->getMessage());
            }

            // 2. Ajuster type 'etoiles' si nécessaire (int unsigned est ok, mais cohérence avec distributeurs.etoiles_id?)
            // $table->smallInteger('etoiles')->unsigned()->default(1)->change();

            // 3. Rendre id_distrib_parent nullable (Crucial pour correction 0 -> NULL)
            // Le type est déjà bigint unsigned NOT NULL d'après le schéma initial
            $table->unsignedBigInteger('id_distrib_parent')->nullable()->comment('Contiendra ID Parent après correction')->change();
             Log::info("Made id_distrib_parent nullable in {$tableName}");

            // 4. Rendre `period` nullable si ce n'est pas le cas (le schéma initial le permettait)
             $table->string('period', 20)->nullable()->change();

             // 5. Modifier les colonnes ENUM en TINYINT (boolean)
             // et les renommer si elles ne servent qu'à l'état interne (optionnel)
             if (Schema::hasColumn($tableName, 'is_children')) {
                  // $table->renameColumn('is_children', 'has_children_flag'); // Renommage manuel si souhaité
                  $table->tinyInteger('is_children')->default(0)->comment('Flag interne? 0=off, 1=on')->change();
                   Log::info("Changed is_children to tinyint in {$tableName}");
             }
             if (Schema::hasColumn($tableName, 'is_indivual_cumul_checked')) {
                  // $table->renameColumn('is_indivual_cumul_checked', 'cumul_checked_flag'); // Renommage manuel si souhaité
                  $table->tinyInteger('is_indivual_cumul_checked')->default(0)->comment('Flag interne? 0=off, 1=on')->change();
                   Log::info("Changed is_indivual_cumul_checked to tinyint in {$tableName}");
             }

             // 6. Assurer un default pour rang
             $table->integer('rang')->default(0)->change();


            // 7. Ajouter des index (ignorer erreur si existe déjà)
            try { $table->index('period', "{$tableName}_period_index"); } catch (\Exception $e) { Log::warning("Index on period creation failed (maybe exists) for {$tableName}."); }
            try { $table->index('distributeur_id', "{$tableName}_distributeur_id_index"); } catch (\Exception $e) { Log::warning("Index on distributeur_id creation failed (maybe exists) for {$tableName}."); }
            try { $table->index('id_distrib_parent', "{$tableName}_id_distrib_parent_index"); } catch (\Exception $e) { Log::warning("Index on id_distrib_parent creation failed (maybe exists) for {$tableName}."); }

        });
        Log::info("Finished modifying {$tableName} table (Step 1).");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'level_currents'; // Important pour le rollback aussi
        Log::warning("Reverting {$tableName} table modifications (Step 1 - Limited Rollback)...");
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Supprimer index
            try { $table->dropIndex("{$tableName}_id_distrib_parent_index"); } catch (\Exception $e) {}
            try { $table->dropIndex("{$tableName}_distributeur_id_index"); } catch (\Exception $e) {}
            try { $table->dropIndex("{$tableName}_period_index"); } catch (\Exception $e) {}

            // Remettre en ENUM ?
             if (Schema::hasColumn($tableName, 'is_indivual_cumul_checked')) {
                 $table->enum('is_indivual_cumul_checked', ['on', 'off'])->default('off')->change();
             }
             if (Schema::hasColumn($tableName, 'is_children')) {
                 $table->enum('is_children', ['on', 'off'])->default('off')->change();
             }

            // Remettre id_distrib_parent NOT NULL ? Risqué
             // $table->unsignedBigInteger('id_distrib_parent')->nullable(false)->change();

            // Remettre en FLOAT ? Risqué
            /*
             $table->float('cumul_collectif', 12, 2)->unsigned()->default(0.00)->change();
             $table->float('cumul_total', 12, 2)->unsigned()->default(0.00)->change();
             $table->float('new_cumul', 12, 2)->unsigned()->default(0.00)->change();
             $table->float('cumul_individuel', 12, 2)->default(0.00)->change();
            */
        });
    }
};
