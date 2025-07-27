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
        Schema::table('distributeurs', function (Blueprint $table) {
            $table->boolean('statut_validation_periode')
                  ->default(false)
                  ->after('rang')
                  ->comment('Statut de validation pour la période courante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distributeurs', function (Blueprint $table) {
            $table->dropColumn('statut_validation_periode');
        });
    }
};
