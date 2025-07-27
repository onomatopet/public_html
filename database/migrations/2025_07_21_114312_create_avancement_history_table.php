<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Log::info('Creating avancement_history table...');

        Schema::create('avancement_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distributeur_id');
            $table->string('period', 7)->comment('Format YYYY-MM');
            $table->smallInteger('ancien_grade')->unsigned()->comment('Grade avant avancement');
            $table->smallInteger('nouveau_grade')->unsigned()->comment('Grade après avancement');
            $table->enum('type_calcul', ['normal', 'validated_only'])->default('normal')->comment('Type de calcul utilisé');
            $table->timestamp('date_avancement')->useCurrent()->comment('Date et heure de l\'avancement');
            $table->text('details')->nullable()->comment('Détails supplémentaires (JSON ou texte)');
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['distributeur_id', 'period'], 'idx_distributeur_period');
            $table->index('period', 'idx_period');
            $table->index('date_avancement', 'idx_date_avancement');
            $table->index(['nouveau_grade', 'period'], 'idx_nouveau_grade_period');

            // Contrainte de clé étrangère
            $table->foreign('distributeur_id')->references('id')->on('distributeurs')->onDelete('cascade')->onUpdate('cascade');
        });

        Log::info('Table avancement_history created successfully.');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Log::info('Dropping avancement_history table...');
        Schema::dropIfExists('avancement_history');
    }
};
