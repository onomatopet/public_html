<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Préparer la table level_current_histories (structure similaire à level_currents).
     * Change floats en decimal, parent nullable, change enums en tinyint, ajoute index.
     * PAS DE FK ICI (car c'est une table historique).
     */
    public function up(): void
    {
        $tableName = 'level_current_histories';

        Log::info("Modifying {$tableName} table (Step 1: Structure Prep)...");
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {

            // 1. Changer les types float en decimal
            try {
                $table->decimal('cumul_individuel', 12, 2)->default(0.00)->change();
                $table->decimal('new_cumul', 12, 2)->unsigned()->default(0.00)->change();
                $table->decimal('cumul_total', 12, 2)->unsigned()->default(0.00)->change();
                $table->decimal('cumul_collectif', 12, 2)->unsigned()->default(0.00)->change();
                Log::info("Changed float columns to decimal in {$tableName}");
            } catch (\Exception $e) {
                 Log::warning("Could not change float columns to decimal in {$tableName} (maybe already done?): ".$e->getMessage());
            }

            // 2. Ajuster type 'etoiles' si nécessaire
            // $table->smallInteger('etoiles')->unsigned()->default(1)->change();

            // 3. Rendre id_distrib_parent nullable
            $table->unsignedBigInteger('id_distrib_parent')->nullable()->comment('Contiendra ID Parent après correction')->change();
             Log::info("Made id_distrib_parent nullable in {$tableName}");

            // 4. Rendre `period` nullable
             $table->string('period', 20)->nullable()->change();

             // 5. Modifier les colonnes ENUM en TINYINT
             if (Schema::hasColumn($tableName, 'is_children')) {
                  $table->tinyInteger('is_children')->default(0)->comment('Flag interne? 0=off, 1=on')->change();
                   Log::info("Changed is_children to tinyint in {$tableName}");
             }
             if (Schema::hasColumn($tableName, 'is_indivual_cumul_checked')) {
                  $table->tinyInteger('is_indivual_cumul_checked')->default(0)->comment('Flag interne? 0=off, 1=on')->change();
                   Log::info("Changed is_indivual_cumul_checked to tinyint in {$tableName}");
             }

             // 6. Assurer un default pour rang
             $table->integer('rang')->default(0)->change();

            // 7. Ajouter des index (ignorer erreur si existe déjà)
            // Utiliser des noms légèrement différents pour éviter conflits si noms d'index doivent être uniques *par base* (rare)
            // Mais généralement, les noms d'index sont uniques *par table*. On peut garder les mêmes noms relatifs.
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
        $tableName = 'level_current_histories';
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

            // Remettre id_distrib_parent NOT NULL ?
             // $table->unsignedBigInteger('id_distrib_parent')->nullable(false)->change();

             // Remettre en FLOAT ?
             /* ... */
        });
    }
};
