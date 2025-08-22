<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('temp_grade_calculations')) {
            Schema::create('temp_grade_calculations', function (Blueprint $table) {
                $table->id();
                $table->string('calculation_session_id', 50)->index(); // ID unique pour chaque session de calcul
                $table->string('period', 7)->index(); // Format YYYY-MM
                $table->unsignedBigInteger('distributeur_id'); // ID primaire du distributeur
                $table->string('matricule', 50)->index(); // Matricule pour faciliter les recherches
                $table->unsignedBigInteger('level_current_id'); // ID de la ligne level_currents
                $table->unsignedInteger('grade_initial'); // Grade au début du calcul
                $table->unsignedInteger('grade_actuel'); // Grade actuel dans cette session
                $table->unsignedInteger('grade_precedent'); // Grade avant la dernière promotion
                $table->decimal('cumul_individuel', 15, 2);
                $table->decimal('cumul_collectif', 15, 2);
                $table->unsignedInteger('pass_number')->default(0); // Numéro de la passe où la promotion a eu lieu
                $table->text('qualification_method')->nullable(); // Méthode de qualification
                $table->boolean('promoted')->default(false); // Si promu dans cette session
                $table->json('promotion_history')->nullable(); // Historique des promotions par passe
                $table->timestamps();

                // Index composites pour les performances (noms courts)
                $table->index(['calculation_session_id', 'distributeur_id'], 'idx_session_distrib');
                $table->index(['calculation_session_id', 'grade_actuel'], 'idx_session_grade');
                $table->index(['calculation_session_id', 'promoted'], 'idx_session_promoted');

                // Clé étrangère
                $table->foreign('distributeur_id')->references('id')->on('distributeurs')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('temp_grade_calculations');
    }
};
