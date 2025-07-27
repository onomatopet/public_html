<?php
// php artisan make:migration create_parrainages_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parrainages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributeur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parrain_id')->constrained('users')->onDelete('cascade');
            $table->date('date_parrainage');
            $table->enum('statut', ['actif', 'inactif', 'suspendu'])->default('actif');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index(['distributeur_id', 'parrain_id']);
            $table->index('date_parrainage');
            $table->index('statut');

            // Un distributeur ne peut avoir qu'un seul parrain actif
            $table->unique(['distributeur_id', 'statut']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('parrainages');
    }
};
