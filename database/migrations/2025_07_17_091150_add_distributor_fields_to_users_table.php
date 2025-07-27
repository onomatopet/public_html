<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        // Utiliser un seul bloc Schema::table est plus performant
        Schema::table('users', function (Blueprint $table) {
            Log::info('Adding distributor specific fields to users table (idempotent)...');

            // Le champ 'name' de Laravel peut servir de nom complet.
            if (!Schema::hasColumn('users', 'pnom_distributeur')) {
                $table->string('pnom_distributeur', 120)->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'nom_distributeur')) {
                $table->string('nom_distributeur', 120)->nullable()->after('pnom_distributeur');
            }
            if (!Schema::hasColumn('users', 'tel_distributeur')) {
                $table->string('tel_distributeur', 120)->nullable()->after('nom_distributeur');
            }
            if (!Schema::hasColumn('users', 'adress_distributeur')) {
                $table->string('adress_distributeur', 120)->nullable()->after('tel_distributeur');
            }

            // Champs métier
            if (!Schema::hasColumn('users', 'distributeur_id')) {
                $table->bigInteger('distributeur_id')->unsigned()->unique()->nullable()->comment('Matricule unique')->after('email');
            }
            if (!Schema::hasColumn('users', 'etoiles_id')) {
                $table->smallInteger('etoiles_id')->unsigned()->default(1)->after('distributeur_id')->comment('Niveau actuel');
            }
            if (!Schema::hasColumn('users', 'rang')) {
                $table->integer('rang')->default(0)->after('etoiles_id')->comment('Rang actuel');
            }
            if (!Schema::hasColumn('users', 'id_distrib_parent')) {
                $table->unsignedBigInteger('id_distrib_parent')->nullable()->after('adress_distributeur')->comment('Référence users.id du parent');
                $table->index('id_distrib_parent', 'users_id_distrib_parent_index');
            }
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->tinyInteger('role_id')->unsigned()->default(0)->after('remember_token')->comment('0: User, 1: Admin');
                $table->index('role_id', 'users_role_id_index');
            }
        });
         Log::info('Finished adding distributor specific fields to users table.');
    }

    public function down(): void
    {
        // La méthode down reste la même, elle supprime les colonnes et index
        Schema::table('users', function (Blueprint $table) {
            Log::info('Removing distributor specific fields from users table...');
             $columnsToDrop = [
                'pnom_distributeur', 'nom_distributeur', 'distributeur_id', 'etoiles_id',
                'rang', 'tel_distributeur', 'adress_distributeur', 'id_distrib_parent', 'role_id'
            ];
             // Supprimer d'abord les index
            try { $table->dropIndex('users_id_distrib_parent_index'); } catch(\Exception $e) {}
            try { $table->dropIndex('users_role_id_index'); } catch(\Exception $e) {}
            try { $table->dropUnique(['distributeur_id']); } catch(\Exception $e) {}

            $table->dropColumn($columnsToDrop);
        });
    }
};
