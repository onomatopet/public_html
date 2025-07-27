<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Préparer la table bonuses.
     * Change doubles en decimal, ajoute index distributeur.
     * NE PAS AJOUTER LA FK ICI.
     */
    public function up(): void
    {
        $tableName = 'bonuses';
        Log::info("Modifying {$tableName} table (Step 1: Structure Prep)...");

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {

            // 1. Changer les types double en decimal
            // Le type 'float' dans la définition originale est aussi problématique pour l'argent.
            try {
                // Vérifier le type actuel peut être utile, mais change() est souvent tolérant
                // if(Schema::getColumnType($tableName,'bonus_direct') == 'double') { ... }

                $table->decimal('bonus_direct', 12, 2)->nullable()->default(0.00)->change(); // Ajout default
                $table->decimal('bonus_indirect', 12, 2)->nullable()->default(0.00)->change(); // Ajout default
                $table->decimal('bonus_leadership', 12, 2)->nullable()->default(0.00)->change(); // Ajout default
                $table->decimal('bonus', 14, 2)->default(0.00)->change(); // Total, peut-être plus grand, NOT NULL original
                $table->decimal('epargne', 14, 2)->default(0.00)->change(); // NOT NULL original

                Log::info("Changed double/float columns to decimal in {$tableName}");
            } catch (\Exception $e) {
                 Log::warning("Could not change double/float columns to decimal in {$tableName} (maybe already done?): ".$e->getMessage());
            }

            // 2. Assurer que distributeur_id est unsignedBigInteger (normalement déjà le cas)
            // $table->unsignedBigInteger('distributeur_id')->change(); // Inutile si déjà correct

            // 3. Index sur les colonnes souvent filtrées/jointes
            try { $table->index('period', "{$tableName}_period_index"); } catch (\Exception $e) { Log::warning("Index on period creation failed (maybe exists) for {$tableName}."); }
            try { $table->index('distributeur_id', "{$tableName}_distributeur_id_index"); } catch (\Exception $e) { Log::warning("Index on distributeur_id creation failed (maybe exists) for {$tableName}."); }
            try { $table->index('num', "{$tableName}_num_index"); } catch (\Exception $e) { Log::warning("Index on num creation failed (maybe exists) for {$tableName}."); }


        });
        Log::info("Finished modifying {$tableName} table (Step 1).");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'bonuses';
        Log::warning("Reverting {$tableName} table modifications (Step 1 - Limited Rollback)...");
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            // Supprimer index
            try { $table->dropIndex("{$tableName}_num_index"); } catch (\Exception $e) {}
            try { $table->dropIndex("{$tableName}_distributeur_id_index"); } catch (\Exception $e) {}
            try { $table->dropIndex("{$tableName}_period_index"); } catch (\Exception $e) {}

            // Remettre en DOUBLE/FLOAT ? Risqué.
            /*
            $table->double('bonus_direct', 12, 2)->nullable()->change();
            $table->double('bonus_indirect', 12, 2)->nullable()->change();
            $table->double('bonus_leadership', 12, 2)->nullable()->change();
            $table->float('bonus')->change();
            $table->float('epargne')->change();
            */
        });
    }
};
