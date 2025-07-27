<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_vente')->unique();
            $table->unsignedBigInteger('distributeur_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->decimal('montant_ht', 10, 2);
            $table->decimal('montant_ttc', 10, 2);
            $table->decimal('taux_tva', 5, 2)->default(0);
            $table->integer('volume_points')->default(0);
            $table->date('date_vente');
            $table->enum('statut', ['en_cours', 'valide', 'livree', 'annulee', 'remboursee'])->default('en_cours');
            $table->enum('type_vente', ['directe', 'en_ligne', 'catalogue', 'evenement'])->default('directe');
            $table->text('notes')->nullable();
            $table->json('details_produits')->nullable();
            $table->timestamps();

            $table->index(['distributeur_id', 'date_vente']);
            $table->index(['statut', 'date_vente']);
            $table->index('date_vente');
            $table->index('volume_points');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('ventes');
    }
};
