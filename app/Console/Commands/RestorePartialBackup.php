<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class RestorePartialBackup extends Command
{
    protected $signature = 'backup:restore-partial {backup_id} {--skip-achats : Ne pas restaurer les achats} {--skip-invalid : Ignorer les enregistrements invalides}';
    protected $description = 'Restaure partiellement un backup en ignorant les données problématiques';

    private BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        parent::__construct();
        $this->backupService = $backupService;
    }

    public function handle()
    {
        $backupId = $this->argument('backup_id');
        $skipAchats = $this->option('skip-achats');
        $skipInvalid = $this->option('skip-invalid');

        // Recherche du backup
        $backup = DB::table('deletion_backups')
            ->where('backup_id', 'like', $backupId . '%')
            ->first();

        if (!$backup) {
            $this->error("Backup non trouvé : {$backupId}");
            return 1;
        }

        $this->info("=== RESTAURATION PARTIELLE ===");
        $this->info("Backup : " . $backup->backup_id);
        $this->info("Options : " . ($skipAchats ? "Sans achats" : "Avec achats valides") . ", " . ($skipInvalid ? "Ignorer invalides" : "Strict"));

        $backupData = json_decode($backup->backup_data, true);

        if (!$backupData) {
            $this->error("Impossible de décoder les données du backup");
            return 1;
        }

        // Vérifier que l'entité n'existe pas déjà
        if ($this->entityExists($backupData['entity_type'], $backupData['entity_id'])) {
            $this->error("L'entité existe déjà et ne peut pas être restaurée");
            return 1;
        }

        DB::beginTransaction();

        try {
            // 1. Restaurer l'entité principale
            $this->info("\n1. Restauration de l'entité principale...");
            $this->restoreMainEntity($backupData['entity_type'], $backupData['entity_data']);
            $this->info("✅ Entité principale restaurée");

            // 2. Restaurer les données liées
            if (!empty($backupData['related_data']) && !$skipAchats) {
                $this->info("\n2. Restauration des données liées...");
                $this->restoreValidRelatedData(
                    $backupData['entity_type'],
                    $backupData['entity_id'],
                    $backupData['related_data'],
                    $skipInvalid
                );
            } elseif ($skipAchats) {
                $this->warn("⏭️  Achats ignorés selon l'option --skip-achats");
            }

            // 3. Marquer le backup comme partiellement restauré
            DB::table('deletion_backups')
                ->where('backup_id', $backup->backup_id)
                ->update([
                    'restored_at' => now(),
                    'restored_by' => Auth::id() ?: null, // Utiliser null au lieu de 0
                    'updated_at' => now()
                ]);

            DB::commit();

            $this->info("\n✅ Restauration partielle terminée avec succès!");

            if ($skipAchats || $skipInvalid) {
                $this->warn("\n⚠️  ATTENTION : La restauration est partielle.");
                $this->warn("Certaines données n'ont pas été restaurées.");
                $this->warn("Vérifiez manuellement les données manquantes.");
            }

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n❌ Erreur lors de la restauration : " . $e->getMessage());
            Log::error("Erreur restauration partielle", [
                'backup_id' => $backup->backup_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function entityExists(string $entityType, int $entityId): bool
    {
        $tableMap = [
            'distributeur' => 'distributeurs',
            'achat' => 'achats',
            'product' => 'products',
            'bonus' => 'bonuses'
        ];

        $table = $tableMap[$entityType] ?? null;
        if (!$table) {
            return false;
        }

        return DB::table($table)->where('id', $entityId)->exists();
    }

    private function restoreMainEntity(string $entityType, array $entityData): void
    {
        // Mapper les colonnes directement ici
        $mappedData = $this->mapOldColumnsToNew($entityType, $entityData);

        // Retirer les timestamps
        unset($mappedData['created_at'], $mappedData['updated_at']);
        $mappedData['created_at'] = now();
        $mappedData['updated_at'] = now();

        $tableMap = [
            'distributeur' => 'distributeurs',
            'achat' => 'achats',
            'product' => 'products',
            'bonus' => 'bonuses'
        ];

        $table = $tableMap[$entityType] ?? null;
        if (!$table) {
            throw new \Exception("Type d'entité non supporté : {$entityType}");
        }

        DB::table($table)->insert($mappedData);
    }

    /**
     * Mapper les anciennes colonnes vers les nouvelles
     */
    private function mapOldColumnsToNew(string $entityType, array $entityData): array
    {
        switch ($entityType) {
            case 'achat':
                // Mapper les anciennes colonnes vers les nouvelles
                if (isset($entityData['montant']) && !isset($entityData['montant_total_ligne'])) {
                    $entityData['montant_total_ligne'] = $entityData['montant'];
                    unset($entityData['montant']);
                }

                if (isset($entityData['points'])) {
                    if (!isset($entityData['points_unitaire_achat'])) {
                        $entityData['points_unitaire_achat'] = $entityData['points'];
                    }
                    unset($entityData['points']);
                }

                if (isset($entityData['pointvaleur'])) {
                    if (!isset($entityData['points_unitaire_achat'])) {
                        $entityData['points_unitaire_achat'] = $entityData['pointvaleur'];
                    }
                    unset($entityData['pointvaleur']);
                }

                // Valeurs par défaut
                $entityData['prix_unitaire_achat'] = $entityData['prix_unitaire_achat'] ?? 0;
                $entityData['points_unitaire_achat'] = $entityData['points_unitaire_achat'] ?? 0;
                $entityData['qt'] = $entityData['qt'] ?? 1;
                $entityData['online'] = $entityData['online'] ?? 1;

                if (!isset($entityData['purchase_date'])) {
                    $entityData['purchase_date'] = isset($entityData['created_at'])
                        ? date('Y-m-d', strtotime($entityData['created_at']))
                        : date('Y-m-d');
                }

                // Supprimer les colonnes obsolètes
                unset($entityData['id_distrib_parent']);
                break;

            case 'bonus':
                if (isset($entityData['bonus']) && !isset($entityData['montant'])) {
                    $entityData['montant'] = $entityData['bonus'];
                    unset($entityData['bonus']);
                }
                break;
        }

        return $entityData;
    }

    private function restoreValidRelatedData(string $entityType, int $entityId, array $relatedData, bool $skipInvalid): void
    {
        $restored = [
            'achats' => 0,
            'bonuses' => 0,
            'level_currents' => 0
        ];

        $skipped = [
            'achats' => 0,
            'bonuses' => 0,
            'level_currents' => 0
        ];

        // Restaurer les achats
        if ($entityType === 'distributeur' && isset($relatedData['achats'])) {
            foreach ($relatedData['achats'] as $achat) {
                try {
                    // Ajouter le distributeur_id manquant
                    if (!isset($achat['distributeur_id'])) {
                        $achat['distributeur_id'] = $entityId;
                    }

                    // Vérifier si products_id existe
                    if (!isset($achat['products_id'])) {
                        if ($skipInvalid) {
                            $this->warn("  ⏭️  Achat ID {$achat['id']} ignoré (products_id manquant)");
                            $skipped['achats']++;
                            continue;
                        } else {
                            throw new \Exception("products_id manquant pour l'achat ID {$achat['id']}");
                        }
                    }

                    // Mapper et restaurer
                    $mappedAchat = $this->mapOldColumnsToNew('achat', $achat);
                    unset($mappedAchat['created_at'], $mappedAchat['updated_at']);
                    $mappedAchat['created_at'] = now();
                    $mappedAchat['updated_at'] = now();

                    DB::table('achats')->insert($mappedAchat);
                    $restored['achats']++;

                } catch (\Exception $e) {
                    if (!$skipInvalid) {
                        throw $e;
                    }
                    $this->warn("  ⏭️  Erreur lors de la restauration de l'achat : " . $e->getMessage());
                    $skipped['achats']++;
                }
            }
        }

        // Restaurer les bonus
        if ($entityType === 'distributeur' && isset($relatedData['bonuses'])) {
            foreach ($relatedData['bonuses'] as $bonus) {
                try {
                    if (!isset($bonus['distributeur_id'])) {
                        $bonus['distributeur_id'] = $entityId;
                    }

                    $mappedBonus = $this->mapOldColumnsToNew('bonus', $bonus);
                    unset($mappedBonus['created_at'], $mappedBonus['updated_at']);
                    $mappedBonus['created_at'] = now();
                    $mappedBonus['updated_at'] = now();

                    DB::table('bonuses')->insert($mappedBonus);
                    $restored['bonuses']++;

                } catch (\Exception $e) {
                    if (!$skipInvalid) {
                        throw $e;
                    }
                    $skipped['bonuses']++;
                }
            }
        }

        // Afficher le résumé
        $this->info("\nRésumé de la restauration des données liées :");
        foreach ($restored as $type => $count) {
            if ($count > 0 || $skipped[$type] > 0) {
                $this->info("  - {$type} : {$count} restauré(s), {$skipped[$type]} ignoré(s)");
            }
        }
    }
}
