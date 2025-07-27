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
        Schema::table('users', function (Blueprint $table) {
            // Option 1: Champ role unique
            $table->string('role', 50)->default('user')->after('email_verified_at')->comment('Rôle utilisateur: user, admin, super_admin');

            // Option 2: Champs booléens séparés (commentés, choisissez l'une des deux approches)
            // $table->boolean('is_admin')->default(false)->after('email_verified_at')->comment('Accès administration');
            // $table->boolean('is_super_admin')->default(false)->after('is_admin')->comment('Super administrateur');

            // Autres champs utiles
            $table->timestamp('last_login_at')->nullable()->after('updated_at')->comment('Dernière connexion');
            $table->boolean('is_active')->default(true)->after('last_login_at')->comment('Compte actif');

            // Index pour les performances
            $table->index('role');
            $table->index(['is_active', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'role']);
            $table->dropIndex(['role']);

            $table->dropColumn([
                'role',
                'last_login_at',
                'is_active'
            ]);

            // Si vous avez choisi les champs booléens, décommentez :
            // $table->dropColumn(['is_admin', 'is_super_admin', 'last_login_at', 'is_active']);
        });
    }
};
