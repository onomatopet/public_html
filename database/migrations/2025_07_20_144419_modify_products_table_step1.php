<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Préparer la table products.
     * Change prix en DECIMAL, ajoute UNIQUE sur code, ajoute index.
     * NE PAS AJOUTER LES FK ICI.
     */
    public function up(): void
    {
        Log::info('Modifying products table (Step 1: Structure Prep)...');
        Schema::table('products', function (Blueprint $table) {

            // 1. Changer prix_product en DECIMAL (si c'était double avant)
            // La vérification de type exacte sans doctrine est peu fiable, on tente directement.
             try {
                 $table->decimal('prix_product', 12, 2)->nullable(false)->change(); // Assurer NOT NULL
                 Log::info('Attempted to change products.prix_product to DECIMAL(12,2)');
             } catch (\Exception $e) {
                 // Ignorer si déjà decimal ou autre erreur non bloquante de change()
                  Log::warning('Could not change prix_product type (maybe already decimal?): ' . $e->getMessage());
             }

             // 2. Changer pointvaleur_id en SMALLINT si approprié (max 65535)
             // Assurez-vous que les ID existants sont compatibles !
             // $table->smallInteger('pointvaleur_id')->unsigned()->default(1)->change(); // Attention au default existant
             // Si vous changez, assurez-vous de garder le default si la colonne l'avait déjà
             $table->unsignedBigInteger('pointvaleur_id')->default(1)->change(); // Garder BigInt pour l'instant, mais s'assurer du default

            // 3. Ajouter UNIQUE sur code_product
            // Assurez-vous d'avoir nettoyé les doublons manuellement avant !
            try {
                $table->unique('code_product', 'products_code_product_unique');
                Log::info('Added unique constraint on products.code_product');
            } catch (\Illuminate\Database\QueryException $e) {
                if (isset($e->errorInfo[1]) && ($e->errorInfo[1] == 1062 || $e->errorInfo[1] == 1061)) {
                    Log::warning('Unique constraint/index on code_product already exists or duplicates found: ' . $e->getMessage());
                } else { throw $e; }
            } catch (\Exception $e) { throw $e; }


            // 4. Ajouter des index (ignorer erreur si existe déjà)
            try { $table->index('category_id', 'products_category_id_index'); } catch (\Exception $e) { Log::warning('Index on category_id creation failed (maybe exists).'); }
            try { $table->index('pointvaleur_id', 'products_pointvaleur_id_index'); } catch (\Exception $e) { Log::warning('Index on pointvaleur_id creation failed (maybe exists).'); }
            try { $table->index('nom_produit', 'products_nom_produit_index'); } catch (\Exception $e) { Log::warning('Index on nom_produit creation failed (maybe exists).'); }


        });
        Log::info('Finished modifying products table (Step 1).');
    }

    /**
     * Reverse the migrations.
     * Essaye d'annuler les changements structurels.
     */
    public function down(): void
    {
         Log::warning('Reverting products table modifications (Step 1 - Limited Rollback)...');
         Schema::table('products', function (Blueprint $table) {
             // Supprimer index (ignorer erreur si inexistant)
            try { $table->dropIndex('products_nom_produit_index'); } catch (\Exception $e) {}
            try { $table->dropIndex('products_pointvaleur_id_index'); } catch (\Exception $e) {}
            try { $table->dropIndex('products_category_id_index'); } catch (\Exception $e) {}

             // Supprimer unique sur code_product
             try { $table->dropUnique('products_code_product_unique'); } catch (\Exception $e) {}

             // Remettre pointvaleur_id en BIGINT? Risqué.
             // $table->unsignedBigInteger('pointvaleur_id')->default(1)->change();

             // Remettre prix_product en DOUBLE? Risqué.
             // $table->double('prix_product', 12, 2)->nullable(false)->change();
         });
    }
};
