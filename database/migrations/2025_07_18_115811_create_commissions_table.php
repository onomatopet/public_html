<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vérifier si la table existe déjà
        if (Schema::connection('mysql')->hasTable('commissions')) {
            // La table existe, ajouter seulement les clés étrangères manquantes
            Schema::connection('mysql')->table('commissions', function (Blueprint $table) {
                // Ajouter les FK si elles n'existent pas
                if (!$this->foreignKeyExists('commissions', 'commissions_distributeur_id_foreign')) {
                    $table->foreign('distributeur_id')->references('id')->on('users')->onDelete('cascade');
                }
                if (!$this->foreignKeyExists('commissions', 'commissions_vente_id_foreign')) {
                    $table->foreign('vente_id')->references('id')->on('ventes')->onDelete('set null');
                }
            });
            return;
        }

        // Créer la table si elle n'existe pas
        Schema::connection('mysql')->create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributeur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->onDelete('set null');
            $table->enum('type_commission', [
                'vente_directe',
                'bonus_parrainage',
                'bonus_niveau',
                'bonus_volume',
                'bonus_leadership'
            ]);
            $table->decimal('montant', 10, 2);
            $table->decimal('pourcentage', 5, 2)->nullable();
            $table->integer('niveau')->nullable();
            $table->date('date_commission');
            $table->enum('statut', ['en_attente', 'valide', 'paye', 'annule'])->default('en_attente');
            $table->text('description')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['distributeur_id', 'date_commission']);
            $table->index(['type_commission', 'statut']);
            $table->index('date_commission');
            $table->index('statut');
        });
    }

    private function foreignKeyExists(string $table, string $keyName): bool
    {
        try {
            $keys = DB::connection('mysql')->select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$table, $keyName]);

            return count($keys) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('commissions');
    }
};
