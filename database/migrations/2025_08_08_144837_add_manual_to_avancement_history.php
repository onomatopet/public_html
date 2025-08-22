<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Log::info('Ajout de la valeur "manual" à la colonne type_calcul de avancement_history...');

        // Pour modifier un ENUM en MySQL, il faut utiliser une requête SQL directe
        DB::statement("ALTER TABLE avancement_history MODIFY COLUMN type_calcul ENUM('normal', 'validated_only', 'manual') DEFAULT 'normal' COMMENT 'Type de calcul utilisé'");

        Log::info('Colonne type_calcul modifiée avec succès.');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Log::info('Suppression de la valeur "manual" de la colonne type_calcul...');

        // Vérifier s'il y a des enregistrements avec 'manual'
        $manualCount = DB::table('avancement_history')
            ->where('type_calcul', 'manual')
            ->count();

        if ($manualCount > 0) {
            Log::warning("Il y a {$manualCount} enregistrements avec type_calcul='manual'. Mise à jour vers 'normal'...");

            // Mettre à jour les enregistrements 'manual' vers 'normal'
            DB::table('avancement_history')
                ->where('type_calcul', 'manual')
                ->update(['type_calcul' => 'normal']);
        }

        // Retirer 'manual' de l'ENUM
        DB::statement("ALTER TABLE avancement_history MODIFY COLUMN type_calcul ENUM('normal', 'validated_only') DEFAULT 'normal' COMMENT 'Type de calcul utilisé'");
    }
};
