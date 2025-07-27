<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Préparer la table achats: Ajouter nouvelles colonnes, modifier types existants.
     * PAS de copie, PAS de suppression d'anciennes colonnes, PAS d'index/FK ici.
     */
    public function up(): void
    {
        Log::info('Modifying achats table (Step 1: Add/Modify Columns)...');
        Schema::table('achats', function (Blueprint $table) {

            // 1. AJOUTER les NOUVELLES colonnes (si elles n'existent pas)
            if (!Schema::hasColumn('achats', 'points_unitaire_achat')) {
                 $table->decimal('points_unitaire_achat', 10, 2)->default(0.00)->comment('PV unitaire achat')->after('products_id');
                 Log::info('Added column points_unitaire_achat');
             }
             if (!Schema::hasColumn('achats', 'montant_total_ligne')) {
                  $table->decimal('montant_total_ligne', 14, 2)->default(0.00)->comment('Montant total ligne')->after('points_unitaire_achat');
                  Log::info('Added column montant_total_ligne');
              }
             if (!Schema::hasColumn('achats', 'prix_unitaire_achat')) {
                $table->decimal('prix_unitaire_achat', 12, 2)->default(0.00)->after('montant_total_ligne')->comment('Prix unitaire achat');
                Log::info('Added column prix_unitaire_achat');
            }

            // 2. MODIFIER les types des colonnes existantes
            // Modifier qt (si la colonne existe)
             if (Schema::hasColumn('achats', 'qt')) {
                $table->mediumInteger('qt')->unsigned()->default(1)->comment('Quantité achetée')->change();
                Log::info('Changed type for qt');
             }
            // Modifier online (si la colonne existe)
             if (Schema::hasColumn('achats', 'online')) {
                $table->tinyInteger('online')->default(1)->comment('Achat en ligne ? (1=oui, 0=non)')->change();
                Log::info('Changed type for online');
             }

             // NE RIEN SUPPRIMER ICI (pointvaleur, montant, id_distrib_parent)
             // NE PAS AJOUTER D'INDEX OU DE FK ICI

        });
        Log::info('Finished modifying achats table (Step 1).');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Log::warning('Reverting achats table modifications (Step 1 - Limited Rollback)...');
         Schema::table('achats', function (Blueprint $table) {
             // Supprimer les colonnes ajoutées
             if (Schema::hasColumn('achats', 'prix_unitaire_achat')) { $table->dropColumn('prix_unitaire_achat'); }
             if (Schema::hasColumn('achats', 'montant_total_ligne')) { $table->dropColumn('montant_total_ligne'); }
             if (Schema::hasColumn('achats', 'points_unitaire_achat')) { $table->dropColumn('points_unitaire_achat'); }

             // Revenir aux types originaux (approximatif)
              if (Schema::hasColumn('achats', 'qt')) {
                  $table->bigInteger('qt')->unsigned()->change(); // Type original ?
              }
               if (Schema::hasColumn('achats', 'online')) {
                   $table->enum('online', ['on', 'off'])->default('on')->change(); // Type original
               }
         });
    }
};
