<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_periods', function (Blueprint $table) {
            // Validation des achats
            $table->boolean('purchases_validated')->default(false)->after('status');
            $table->timestamp('purchases_validated_at')->nullable()->after('purchases_validated');
            $table->unsignedBigInteger('purchases_validated_by')->nullable()->after('purchases_validated_at');

            // Agrégation des achats
            $table->boolean('purchases_aggregated')->default(false)->after('purchases_validated_by');
            $table->timestamp('purchases_aggregated_at')->nullable()->after('purchases_aggregated');
            $table->unsignedBigInteger('purchases_aggregated_by')->nullable()->after('purchases_aggregated_at');

            // Calcul des avancements
            $table->boolean('advancements_calculated')->default(false)->after('purchases_aggregated_by');
            $table->timestamp('advancements_calculated_at')->nullable()->after('advancements_calculated');
            $table->unsignedBigInteger('advancements_calculated_by')->nullable()->after('advancements_calculated_at');

            // Création du snapshot
            $table->boolean('snapshot_created')->default(false)->after('advancements_calculated_by');
            $table->timestamp('snapshot_created_at')->nullable()->after('snapshot_created');
            $table->unsignedBigInteger('snapshot_created_by')->nullable()->after('snapshot_created_at');

            // Foreign keys
            $table->foreign('purchases_validated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('purchases_aggregated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('advancements_calculated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('snapshot_created_by')->references('id')->on('users')->onDelete('set null');

            // Index pour les performances
            $table->index(['period', 'status', 'purchases_validated', 'purchases_aggregated', 'advancements_calculated', 'snapshot_created'], 'idx_system_periods_workflow');
        });

        // Mettre à jour les périodes existantes déjà clôturées
        DB::statement("
            UPDATE system_periods
            SET
                purchases_validated = TRUE,
                purchases_aggregated = TRUE,
                advancements_calculated = TRUE,
                snapshot_created = EXISTS(
                    SELECT 1 FROM level_current_histories
                    WHERE period = system_periods.period
                    LIMIT 1
                )
            WHERE status = 'closed'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_periods', function (Blueprint $table) {
            // Supprimer l'index
            $table->dropIndex('idx_system_periods_workflow');

            // Supprimer les foreign keys
            $table->dropForeign(['purchases_validated_by']);
            $table->dropForeign(['purchases_aggregated_by']);
            $table->dropForeign(['advancements_calculated_by']);
            $table->dropForeign(['snapshot_created_by']);

            // Supprimer les colonnes
            $table->dropColumn([
                'purchases_validated',
                'purchases_validated_at',
                'purchases_validated_by',
                'purchases_aggregated',
                'purchases_aggregated_at',
                'purchases_aggregated_by',
                'advancements_calculated',
                'advancements_calculated_at',
                'advancements_calculated_by',
                'snapshot_created',
                'snapshot_created_at',
                'snapshot_created_by',
            ]);
        });
    }
};
