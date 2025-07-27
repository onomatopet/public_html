<?php
// database/migrations/2025_01_26_update_bonuses_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bonuses', function (Blueprint $table) {
            // Ajouter les nouvelles colonnes si elles n'existent pas
            if (!Schema::hasColumn('bonuses', 'montant_direct')) {
                $table->decimal('montant_direct', 10, 2)->default(0)->after('montant');
            }
            if (!Schema::hasColumn('bonuses', 'montant_indirect')) {
                $table->decimal('montant_indirect', 10, 2)->default(0)->after('montant_direct');
            }
            if (!Schema::hasColumn('bonuses', 'montant_leadership')) {
                $table->decimal('montant_leadership', 10, 2)->default(0)->after('montant_indirect');
            }
            if (!Schema::hasColumn('bonuses', 'montant_total')) {
                $table->decimal('montant_total', 10, 2)->default(0)->after('montant_leadership');
            }
            if (!Schema::hasColumn('bonuses', 'status')) {
                $table->enum('status', ['calculé', 'validé', 'en_paiement', 'payé', 'annulé'])
                      ->default('calculé')
                      ->after('montant_total');
            }
            if (!Schema::hasColumn('bonuses', 'details')) {
                $table->json('details')->nullable()->after('status');
            }
            if (!Schema::hasColumn('bonuses', 'calculated_at')) {
                $table->timestamp('calculated_at')->nullable();
            }
            if (!Schema::hasColumn('bonuses', 'validated_by')) {
                $table->unsignedBigInteger('validated_by')->nullable();
                $table->foreign('validated_by')->references('id')->on('users');
            }
            if (!Schema::hasColumn('bonuses', 'validated_at')) {
                $table->timestamp('validated_at')->nullable();
            }
            if (!Schema::hasColumn('bonuses', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (!Schema::hasColumn('bonuses', 'payment_reference')) {
                $table->string('payment_reference')->nullable();
            }

            // Ajouter des index pour les performances
            $table->index(['period', 'status']);
            $table->index(['distributeur_id', 'period']);
        });

        // Migrer les données existantes si nécessaire
        DB::statement('UPDATE bonuses SET montant_total = montant WHERE montant_total = 0');
    }

    public function down()
    {
        Schema::table('bonuses', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn([
                'montant_direct',
                'montant_indirect',
                'montant_leadership',
                'montant_total',
                'status',
                'details',
                'calculated_at',
                'validated_by',
                'validated_at',
                'paid_at',
                'payment_reference'
            ]);
        });
    }
};
