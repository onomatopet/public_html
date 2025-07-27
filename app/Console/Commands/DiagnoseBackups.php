<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DiagnoseBackups extends Command
{
    protected $signature = 'backup:diagnose {--fix : Tenter de corriger les problèmes automatiquement}';
    protected $description = 'Diagnostique les backups existants pour identifier les champs manquants';

    private $requiredFields = [
        'achat' => [
            'required' => ['id', 'period', 'distributeur_id', 'products_id', 'purchase_date'],
            'defaults' => [
                'qt' => 1,
                'online' => 1,
                'prix_unitaire_achat' => 0,
                'points_unitaire_achat' => 0,
                'montant_total_ligne' => 0,
            ]
        ],
        'distributeur' => [
            'required' => ['id', 'distributeur_id', 'nom_distributeur'],
            'defaults' => []
        ],
        'product' => [
            'required' => ['id', 'code_product', 'name_product'],
            'defaults' => []
        ],
        'bonus' => [
            'required' => ['id', 'distributeur_id', 'period', 'montant'],
            'defaults' => []
        ]
    ];

    private $oldToNewFieldMapping = [
        'achat' => [
            'montant' => 'montant_total_ligne',
            'pointvaleur' => 'points_unitaire_achat',
            'points' => 'points_unitaire_achat'
        ],
        'bonus' => [
            'bonus' => 'montant'
        ]
    ];

    public function handle()
    {
        $this->info('🔍 Diagnostic des backups...');

        $backups = DB::table('deletion_backups')->get();
        $this->info("Nombre total de backups : " . $backups->count());

        $issues = [];
        $fixableIssues = 0;

        foreach ($backups as $backup) {
            $backupData = json_decode($backup->backup_data, true);

            if (!$backupData) {
                $issues[] = [
                    'backup_id' => $backup->backup_id,
                    'issue' => 'Données JSON invalides',
                    'fixable' => false
                ];
                continue;
            }

            $entityType = $backupData['entity_type'] ?? null;
            $entityData = $backupData['entity_data'] ?? [];

            if (!$entityType || !isset($this->requiredFields[$entityType])) {
                continue;
            }

            // Vérifier les champs requis
            $missingFields = [];
            foreach ($this->requiredFields[$entityType]['required'] as $field) {
                if (!isset($entityData[$field])) {
                    $missingFields[] = $field;
                }
            }

            // Vérifier les données liées
            $relatedIssues = [];
            if (isset($backupData['related_data'])) {
                foreach ($backupData['related_data'] as $relationType => $relatedItems) {
                    if ($relationType === 'achats' && is_array($relatedItems)) {
                        foreach ($relatedItems as $index => $item) {
                            $itemMissing = [];
                            foreach ($this->requiredFields['achat']['required'] as $field) {
                                if (!isset($item[$field])) {
                                    $itemMissing[] = $field;
                                }
                            }
                            if (!empty($itemMissing)) {
                                $relatedIssues[] = "achat[{$index}]: " . implode(', ', $itemMissing);
                            }
                        }
                    }
                }
            }

            if (!empty($missingFields) || !empty($relatedIssues)) {
                $issue = [
                    'backup_id' => $backup->backup_id,
                    'entity_type' => $entityType,
                    'entity_id' => $backupData['entity_id'] ?? 'unknown',
                    'missing_fields' => $missingFields,
                    'related_issues' => $relatedIssues,
                    'created_at' => $backup->created_at,
                    'fixable' => $this->isFixable($entityType, $missingFields)
                ];

                if ($issue['fixable']) {
                    $fixableIssues++;
                }

                $issues[] = $issue;
            }
        }

        // Afficher le rapport
        $this->displayReport($issues, $fixableIssues);

        // Corriger si demandé
        if ($this->option('fix') && $fixableIssues > 0) {
            $this->info("\n🔧 Correction des problèmes...");
            $this->fixIssues($issues);
        }

        return 0;
    }

    private function isFixable(string $entityType, array $missingFields): bool
    {
        // purchase_date peut être déduit de created_at
        if ($entityType === 'achat' && count($missingFields) === 1 && $missingFields[0] === 'purchase_date') {
            return true;
        }

        // Vérifier si tous les champs manquants ont des valeurs par défaut
        foreach ($missingFields as $field) {
            if (!isset($this->requiredFields[$entityType]['defaults'][$field])) {
                return false;
            }
        }

        return true;
    }

    private function displayReport(array $issues, int $fixableIssues): void
    {
        if (empty($issues)) {
            $this->info("✅ Aucun problème détecté dans les backups!");
            return;
        }

        $this->warn("\n⚠️  Problèmes détectés : " . count($issues));
        $this->info("🔧 Problèmes corrigibles : " . $fixableIssues);

        $this->table(
            ['Backup ID', 'Type', 'Entity ID', 'Champs manquants', 'Fixable', 'Date'],
            collect($issues)->map(function ($issue) {
                return [
                    substr($issue['backup_id'], 0, 8) . '...',
                    $issue['entity_type'] ?? 'N/A',
                    $issue['entity_id'] ?? 'N/A',
                    implode(', ', $issue['missing_fields'] ?? []),
                    $issue['fixable'] ? '✅' : '❌',
                    Carbon::parse($issue['created_at'] ?? now())->format('Y-m-d H:i')
                ];
            })
        );

        // Afficher les problèmes dans les données liées
        foreach ($issues as $issue) {
            if (!empty($issue['related_issues'])) {
                $this->warn("\nProblèmes dans les données liées pour backup " . substr($issue['backup_id'], 0, 8) . ":");
                foreach ($issue['related_issues'] as $relatedIssue) {
                    $this->line("  - " . $relatedIssue);
                }
            }
        }
    }

    private function fixIssues(array $issues): void
    {
        $fixed = 0;

        foreach ($issues as $issue) {
            if (!$issue['fixable']) {
                continue;
            }

            try {
                $backup = DB::table('deletion_backups')
                    ->where('backup_id', $issue['backup_id'])
                    ->first();

                if (!$backup) {
                    continue;
                }

                $backupData = json_decode($backup->backup_data, true);

                // Corriger l'entité principale
                $backupData['entity_data'] = $this->fixEntityData(
                    $issue['entity_type'],
                    $backupData['entity_data']
                );

                // Corriger les données liées
                if (isset($backupData['related_data']['achats'])) {
                    foreach ($backupData['related_data']['achats'] as &$achat) {
                        $achat = $this->fixEntityData('achat', $achat);
                    }
                }

                // Mettre à jour le backup
                DB::table('deletion_backups')
                    ->where('backup_id', $issue['backup_id'])
                    ->update([
                        'backup_data' => json_encode($backupData),
                        'updated_at' => now()
                    ]);

                $fixed++;
                $this->info("✅ Corrigé : " . substr($issue['backup_id'], 0, 8));

            } catch (\Exception $e) {
                $this->error("❌ Erreur lors de la correction de " . substr($issue['backup_id'], 0, 8) . ": " . $e->getMessage());
            }
        }

        $this->info("\n✅ {$fixed} backups corrigés avec succès!");
    }

    private function fixEntityData(string $entityType, array $entityData): array
    {
        // Appliquer le mapping des anciens champs
        if (isset($this->oldToNewFieldMapping[$entityType])) {
            foreach ($this->oldToNewFieldMapping[$entityType] as $old => $new) {
                if (isset($entityData[$old]) && !isset($entityData[$new])) {
                    $entityData[$new] = $entityData[$old];
                    unset($entityData[$old]);
                }
            }
        }

        // Ajouter les champs manquants avec valeurs par défaut
        if ($entityType === 'achat') {
            if (!isset($entityData['purchase_date'])) {
                $entityData['purchase_date'] = isset($entityData['created_at'])
                    ? date('Y-m-d', strtotime($entityData['created_at']))
                    : date('Y-m-d');
            }

            // Appliquer les valeurs par défaut
            foreach ($this->requiredFields[$entityType]['defaults'] as $field => $default) {
                if (!isset($entityData[$field])) {
                    $entityData[$field] = $default;
                }
            }

            // Supprimer les champs obsolètes
            unset($entityData['id_distrib_parent']);
            unset($entityData['pointvaleur']);
            unset($entityData['montant']);
        }

        return $entityData;
    }
}
