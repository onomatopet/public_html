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
        Schema::table('achats', function (Blueprint $table) {
            // Vérifier si la colonne status existe déjà
            if (!Schema::hasColumn('achats', 'status')) {
                // Status de validation
                $table->enum('status', ['pending', 'validated', 'rejected', 'cancelled'])
                      ->default('pending')
                      ->after('online')
                      ->comment('Statut de validation de l\'achat');
            }

            // Vérifier si la colonne validated_at existe déjà
            if (!Schema::hasColumn('achats', 'validated_at')) {
                // Date de validation
                $table->timestamp('validated_at')->nullable()
                      ->after('status')
                      ->comment('Date de validation/rejet');
            }

            // Vérifier si la colonne validation_errors existe déjà
            if (!Schema::hasColumn('achats', 'validation_errors')) {
                // Erreurs de validation (JSON)
                $table->json('validation_errors')->nullable()
                      ->after('validated_at')
                      ->comment('Erreurs de validation si rejeté');
            }
        });

        // Vérifier si les index existent avant de les créer
        $existingIndexes = DB::select("SHOW INDEX FROM achats WHERE Key_name IN ('idx_achats_period_status', 'achats_validated_at_index')");
        $indexNames = array_column($existingIndexes, 'Key_name');

        Schema::table('achats', function (Blueprint $table) use ($indexNames) {
            // Index pour les requêtes
            if (!in_array('idx_achats_period_status', $indexNames)) {
                $table->index(['period', 'status'], 'idx_achats_period_status');
            }

            if (!in_array('achats_validated_at_index', $indexNames)) {
                $table->index('validated_at', 'achats_validated_at_index');
            }
        });

        // Mettre à jour les achats existants comme validés seulement si nécessaire
        $pendingCount = DB::table('achats')->where('status', 'pending')->count();
        if ($pendingCount > 0) {
            DB::statement("UPDATE achats SET status = 'validated', validated_at = created_at WHERE status = 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Récupérer les index existants avant suppression
        $existingIndexes = DB::select("SHOW INDEX FROM achats WHERE Key_name IN ('idx_achats_period_status', 'achats_validated_at_index')");
        $indexNames = array_column($existingIndexes, 'Key_name');

        Schema::table('achats', function (Blueprint $table) use ($indexNames) {
            // Supprimer les index s'ils existent
            if (in_array('idx_achats_period_status', $indexNames)) {
                $table->dropIndex('idx_achats_period_status');
            }

            if (in_array('achats_validated_at_index', $indexNames)) {
                $table->dropIndex('achats_validated_at_index');
            }
        });

        Schema::table('achats', function (Blueprint $table) {
            // Supprimer les colonnes si elles existent
            $columnsToRemove = [];

            if (Schema::hasColumn('achats', 'status')) {
                $columnsToRemove[] = 'status';
            }

            if (Schema::hasColumn('achats', 'validated_at')) {
                $columnsToRemove[] = 'validated_at';
            }

            if (Schema::hasColumn('achats', 'validation_errors')) {
                $columnsToRemove[] = 'validation_errors';
            }

            if (!empty($columnsToRemove)) {
                $table->dropColumn($columnsToRemove);
            }
        });
    }
};
