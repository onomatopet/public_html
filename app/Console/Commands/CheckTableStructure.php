<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CheckTableStructure extends Command
{
    protected $signature = 'db:check-structure';
    protected $description = 'Vérifier la structure des tables distributeurs et bonuses';

    public function handle()
    {
        $this->info('=== Vérification de la structure des tables ===');

        // Vérifier la table distributeurs
        $this->info("\n1. Table 'distributeurs':");
        if (Schema::hasTable('distributeurs')) {
            $columns = Schema::getColumnListing('distributeurs');
            $this->info("   Colonnes existantes: " . implode(', ', $columns));

            if (in_array('statut_validation_periode', $columns)) {
                $this->info("   ✓ La colonne 'statut_validation_periode' existe");
            } else {
                $this->warn("   ✗ La colonne 'statut_validation_periode' est manquante");
            }
        } else {
            $this->error("   La table 'distributeurs' n'existe pas!");
        }

        // Vérifier la table bonuses
        $this->info("\n2. Table 'bonuses':");
        if (Schema::hasTable('bonuses')) {
            $columns = Schema::getColumnListing('bonuses');
            $this->info("   Colonnes existantes: " . implode(', ', $columns));

            if (in_array('montant', $columns)) {
                $this->info("   ✓ La colonne 'montant' existe");
            } elseif (in_array('bonus', $columns)) {
                $this->warn("   ✗ La colonne 'montant' est manquante, mais 'bonus' existe");
                $this->info("   → La migration peut renommer 'bonus' en 'montant'");
            } else {
                $this->warn("   ✗ Ni 'montant' ni 'bonus' n'existent");
            }
        } else {
            $this->error("   La table 'bonuses' n'existe pas!");
        }

        // Afficher les types de colonnes pour debug
        $this->info("\n3. Détails des colonnes (si disponibles):");
        try {
            $distribColumns = DB::select("SHOW COLUMNS FROM distributeurs");
            $this->info("\n   Table distributeurs:");
            foreach ($distribColumns as $col) {
                if (in_array($col->Field, ['statut_validation_periode', 'rang', 'etoiles_id'])) {
                    $this->info("   - {$col->Field}: {$col->Type} (Null: {$col->Null}, Default: {$col->Default})");
                }
            }
        } catch (\Exception $e) {
            $this->error("   Impossible de récupérer les détails: " . $e->getMessage());
        }

        try {
            $bonusColumns = DB::select("SHOW COLUMNS FROM bonuses");
            $this->info("\n   Table bonuses:");
            foreach ($bonusColumns as $col) {
                if (in_array($col->Field, ['montant', 'bonus', 'distributeur_id'])) {
                    $this->info("   - {$col->Field}: {$col->Type} (Null: {$col->Null}, Default: {$col->Default})");
                }
            }
        } catch (\Exception $e) {
            $this->error("   Impossible de récupérer les détails: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
