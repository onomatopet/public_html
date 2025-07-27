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
        Schema::table('achats', function (Blueprint $table) {
            // Ajouter la colonne purchase_date après la colonne period
            $table->date('purchase_date')
                  ->nullable()
                  ->after('period')
                  ->comment('Date réelle de l\'achat');

            // Index pour améliorer les performances des requêtes par date
            $table->index('purchase_date', 'achats_purchase_date_index');
        });

        // Mettre à jour les enregistrements existants avec created_at comme valeur par défaut
        DB::statement('UPDATE achats SET purchase_date = DATE(created_at) WHERE purchase_date IS NULL');

        // Rendre la colonne non-nullable après la mise à jour
        Schema::table('achats', function (Blueprint $table) {
            $table->date('purchase_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->dropIndex('achats_purchase_date_index');
            $table->dropColumn('purchase_date');
        });
    }
};
