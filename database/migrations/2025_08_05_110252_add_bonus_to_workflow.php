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
        // 1. Ajouter les colonnes de workflow pour les bonus dans system_periods
        Schema::table('system_periods', function (Blueprint $table) {
            // Vérifier si les colonnes existent déjà
            if (!Schema::hasColumn('system_periods', 'bonus_calculated')) {
                $table->boolean('bonus_calculated')->default(false)->after('advancements_calculated_by');
            }

            if (!Schema::hasColumn('system_periods', 'bonus_calculated_at')) {
                $table->timestamp('bonus_calculated_at')->nullable()->after('bonus_calculated');
            }

            if (!Schema::hasColumn('system_periods', 'bonus_calculated_by')) {
                $table->unsignedBigInteger('bonus_calculated_by')->nullable()->after('bonus_calculated_at');
                $table->foreign('bonus_calculated_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // Vérifier si l'index existe avant de le créer
        $indexExists = collect(DB::select("SHOW INDEX FROM system_periods WHERE Key_name = 'idx_period_bonus'"))->isNotEmpty();

        if (!$indexExists) {
            Schema::table('system_periods', function (Blueprint $table) {
                $table->index(['period', 'bonus_calculated'], 'idx_period_bonus');
            });
        }

        // 2. Ajouter la colonne épargne dans la table bonuses si elle n'existe pas
        if (!Schema::hasColumn('bonuses', 'epargne')) {
            Schema::table('bonuses', function (Blueprint $table) {
                $table->decimal('epargne', 15, 2)->default(0)->after('montant_total');
            });
        }

        // 3. S'assurer que la colonne 'num' existe et est au bon format
        if (!Schema::hasColumn('bonuses', 'num')) {
            Schema::table('bonuses', function (Blueprint $table) {
                $table->string('num', 20)->unique()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer l'index s'il existe
        $indexExists = collect(DB::select("SHOW INDEX FROM system_periods WHERE Key_name = 'idx_period_bonus'"))->isNotEmpty();
        if ($indexExists) {
            Schema::table('system_periods', function (Blueprint $table) {
                $table->dropIndex('idx_period_bonus');
            });
        }

        Schema::table('system_periods', function (Blueprint $table) {
            if (Schema::hasColumn('system_periods', 'bonus_calculated_by')) {
                $table->dropForeign(['bonus_calculated_by']);
            }

            if (Schema::hasColumn('system_periods', 'bonus_calculated')) {
                $table->dropColumn('bonus_calculated');
            }

            if (Schema::hasColumn('system_periods', 'bonus_calculated_at')) {
                $table->dropColumn('bonus_calculated_at');
            }

            if (Schema::hasColumn('system_periods', 'bonus_calculated_by')) {
                $table->dropColumn('bonus_calculated_by');
            }
        });
    }
};
