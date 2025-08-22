<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modifier l'ENUM pour ajouter 'pending' et 'validated'
        DB::statement("ALTER TABLE achats MODIFY COLUMN status ENUM('pending', 'validated', 'active', 'cancelled', 'returned', 'partial_return') NOT NULL DEFAULT 'pending'");

        // Optionnel : Migrer les achats 'active' vers 'validated' pour les périodes où la validation a été faite
        DB::statement("
            UPDATE achats a
            INNER JOIN system_periods sp ON a.period = sp.period
            SET a.status = 'validated',
                a.validated_at = COALESCE(a.validated_at, sp.purchases_validated_at)
            WHERE a.status = 'active'
              AND sp.purchases_validated = 1
              AND a.validated_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remettre les 'validated' en 'active'
        DB::statement("UPDATE achats SET status = 'active' WHERE status IN ('validated', 'pending')");

        // Restaurer l'ENUM original
        DB::statement("ALTER TABLE achats MODIFY COLUMN status ENUM('active', 'cancelled', 'returned', 'partial_return') NOT NULL DEFAULT 'active'");
    }
};
