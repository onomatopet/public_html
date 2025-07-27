<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BackupService
{
    /**
     * Crée un backup avant suppression
     */
    public function createBackup(string $entityType, int $entityId): array
    {
        try {
            // Générer un ID unique pour le backup
            $backupId = Str::uuid()->toString();
            $timestamp = now();

            // Récupérer les données de l'entité
            $entityData = $this->getEntityData($entityType, $entityId);

            // Récupérer les données liées si nécessaire
            $relatedData = $this->getRelatedData($entityType, $entityId);

            // Préparer les données du backup
            $backupData = [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_data' => $entityData,
                'related_data' => $relatedData,
                'metadata' => [
                    'created_by' => Auth::id() ?? 0,
                    'created_at' => $timestamp,
                    'app_version' => config('app.version', '1.0.0'),
                    'backup_version' => '2.0'
                ]
            ];

            // Sauvegarder en JSON dans le dossier storage
            $filename = "backups/{$entityType}/{$backupId}.json";
            $fullPath = storage_path("app/{$filename}");

            // Créer le répertoire si nécessaire
            $directory = dirname($fullPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Écrire le fichier
            file_put_contents($fullPath, json_encode($backupData, JSON_PRETTY_PRINT));

            // Enregistrer dans la base de données
            DB::table('deletion_backups')->insert([
                'backup_id' => $backupId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'backup_data' => json_encode($backupData),
                'file_path' => $filename,
                'created_by' => Auth::id() ?? 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            Log::info("Backup créé avec succès", [
                'backup_id' => $backupId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'related_data_counts' => array_map('count', $relatedData)
            ]);

            return [
                'success' => true,
                'backup_id' => $backupId,
                'file_path' => $filename,
                'created_at' => $timestamp
            ];

        } catch (\Exception $e) {
            Log::error("Erreur lors de la création du backup", [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restaure depuis un backup
     */
    public function restoreFromBackup(string $backupId): array
    {
        try {
            // Récupérer le backup depuis la DB
            $backup = DB::table('deletion_backups')
                ->where('backup_id', $backupId)
                ->first();

            if (!$backup) {
                throw new \Exception("Backup introuvable : {$backupId}");
            }

            $backupData = json_decode($backup->backup_data, true);

            // Vérifier que l'entité n'existe pas déjà
            if ($this->entityExists($backupData['entity_type'], $backupData['entity_id'])) {
                throw new \Exception("L'entité existe déjà et ne peut pas être restaurée");
            }

            DB::beginTransaction();

            // Restaurer l'entité principale
            $this->restoreEntity($backupData['entity_type'], $backupData['entity_data']);

            // Restaurer les données liées si présentes
            if (!empty($backupData['related_data'])) {
                $this->restoreRelatedData($backupData['entity_type'], $backupData['entity_id'], $backupData['related_data']);
            }

            // Marquer le backup comme restauré
            DB::table('deletion_backups')
                ->where('backup_id', $backupId)
                ->update([
                    'restored_at' => now(),
                    'restored_by' => Auth::id() ?: null, // Utiliser null au lieu de 0 pour éviter les erreurs FK
                    'updated_at' => now()
                ]);

            DB::commit();

            Log::info("Restauration réussie depuis le backup", [
                'backup_id' => $backupId,
                'entity_type' => $backupData['entity_type'],
                'entity_id' => $backupData['entity_id']
            ]);

            return [
                'success' => true,
                'entity_type' => $backupData['entity_type'],
                'entity_id' => $backupData['entity_id'],
                'message' => 'Restauration réussie'
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Erreur lors de la restauration", [
                'backup_id' => $backupId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les données liées à une entité
     * VERSION CORRIGÉE : S'assure de récupérer TOUS les champs
     */
    private function getRelatedData(string $entityType, int $entityId): array
    {
        $relatedData = [];

        switch ($entityType) {
            case 'distributeur':
                // Récupérer les achats AVEC TOUS LES CHAMPS
                $relatedData['achats'] = DB::table('achats')
                    ->where('distributeur_id', $entityId)
                    ->select('*') // S'assurer de récupérer TOUS les champs
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })
                    ->toArray();

                // Récupérer les bonus
                $relatedData['bonuses'] = DB::table('bonuses')
                    ->where('distributeur_id', $entityId)
                    ->select('*')
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })
                    ->toArray();

                // Récupérer les niveaux
                $relatedData['level_currents'] = DB::table('level_currents')
                    ->where('distributeur_id', $entityId)
                    ->select('*')
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })
                    ->toArray();

                // Récupérer les enfants directs (juste les IDs)
                $relatedData['children_ids'] = DB::table('distributeurs')
                    ->where('id_distrib_parent', $entityId)
                    ->pluck('id')
                    ->toArray();

                // Log pour vérification
                Log::info("Données liées récupérées pour distributeur {$entityId}", [
                    'achats_count' => count($relatedData['achats']),
                    'bonuses_count' => count($relatedData['bonuses']),
                    'levels_count' => count($relatedData['level_currents']),
                    'children_count' => count($relatedData['children_ids'])
                ]);

                // Vérifier que les achats ont bien tous les champs requis
                foreach ($relatedData['achats'] as $index => $achat) {
                    $requiredFields = ['id', 'distributeur_id', 'products_id', 'period'];
                    $missingFields = array_diff($requiredFields, array_keys($achat));
                    if (!empty($missingFields)) {
                        Log::warning("Achat {$index} manque des champs requis", [
                            'achat_id' => $achat['id'] ?? 'unknown',
                            'missing_fields' => $missingFields
                        ]);
                    }
                }
                break;

            case 'product':
                // Récupérer les achats liés au produit AVEC TOUS LES CHAMPS
                $relatedData['achats'] = DB::table('achats')
                    ->where('products_id', $entityId)
                    ->select('*')
                    ->get()
                    ->map(function ($item) {
                        return (array) $item;
                    })
                    ->toArray();
                break;

            case 'achat':
                // Pour un achat, on peut vouloir sauvegarder les informations du distributeur et produit
                $achat = DB::table('achats')->find($entityId);
                if ($achat) {
                    $relatedData['distributeur'] = (array) DB::table('distributeurs')
                        ->where('id', $achat->distributeur_id)
                        ->first();

                    $relatedData['product'] = (array) DB::table('products')
                        ->where('id', $achat->products_id)
                        ->first();
                }
                break;
        }

        return $relatedData;
    }

    /**
     * Récupère les données d'une entité
     */
    private function getEntityData(string $entityType, int $entityId): array
    {
        switch ($entityType) {
            case 'distributeur':
                $entity = DB::table('distributeurs')->find($entityId);
                break;
            case 'achat':
                $entity = DB::table('achats')->find($entityId);
                break;
            case 'product':
                $entity = DB::table('products')->find($entityId);
                break;
            case 'bonus':
                $entity = DB::table('bonuses')->find($entityId);
                break;
            default:
                throw new \Exception("Type d'entité non supporté : {$entityType}");
        }

        if (!$entity) {
            throw new \Exception("Entité introuvable : {$entityType}#{$entityId}");
        }

        return (array) $entity;
    }

    /**
     * Vérifie si une entité existe
     */
    private function entityExists(string $entityType, int $entityId): bool
    {
        switch ($entityType) {
            case 'distributeur':
                return DB::table('distributeurs')->where('id', $entityId)->exists();
            case 'achat':
                return DB::table('achats')->where('id', $entityId)->exists();
            case 'product':
                return DB::table('products')->where('id', $entityId)->exists();
            case 'bonus':
                return DB::table('bonuses')->where('id', $entityId)->exists();
            default:
                return false;
        }
    }

    /**
     * Mapper les anciennes colonnes vers les nouvelles ET ajouter les champs requis manquants
     * PUBLIC pour permettre l'utilisation dans les commandes si nécessaire
     */
    public function mapOldColumnsToNew(string $entityType, array $entityData): array
    {
        switch ($entityType) {
            case 'achat':
                // Mapper les anciennes colonnes vers les nouvelles
                if (isset($entityData['montant']) && !isset($entityData['montant_total_ligne'])) {
                    $entityData['montant_total_ligne'] = $entityData['montant'];
                    unset($entityData['montant']);
                }

                // La colonne 'points' doit être mappée vers 'points_unitaire_achat'
                if (isset($entityData['points'])) {
                    if (!isset($entityData['points_unitaire_achat'])) {
                        $entityData['points_unitaire_achat'] = $entityData['points'];
                    }
                    unset($entityData['points']);
                }

                // Si 'pointvaleur' existe dans les anciennes données, la mapper vers points_unitaire_achat
                if (isset($entityData['pointvaleur'])) {
                    if (!isset($entityData['points_unitaire_achat'])) {
                        $entityData['points_unitaire_achat'] = $entityData['pointvaleur'];
                    }
                    // IMPORTANT : Supprimer pointvaleur car cette colonne n'existe plus
                    unset($entityData['pointvaleur']);
                }

                // S'assurer que les colonnes requises existent avec des valeurs par défaut
                if (!isset($entityData['prix_unitaire_achat'])) {
                    $entityData['prix_unitaire_achat'] = 0;
                }

                if (!isset($entityData['points_unitaire_achat'])) {
                    $entityData['points_unitaire_achat'] = 0;
                }

                if (!isset($entityData['qt'])) {
                    $entityData['qt'] = 1;
                }

                if (!isset($entityData['online'])) {
                    $entityData['online'] = 1;
                }

                // IMPORTANT : Ajouter purchase_date si manquant
                if (!isset($entityData['purchase_date'])) {
                    // Utiliser la date de création si disponible, sinon la date actuelle
                    if (isset($entityData['created_at'])) {
                        $entityData['purchase_date'] = date('Y-m-d', strtotime($entityData['created_at']));
                    } else {
                        $entityData['purchase_date'] = date('Y-m-d');
                    }
                }

                // Supprimer les colonnes qui n'existent plus
                unset($entityData['id_distrib_parent']);

                break;

            case 'bonus':
                // Mapper bonus vers montant si nécessaire
                if (isset($entityData['bonus']) && !isset($entityData['montant'])) {
                    $entityData['montant'] = $entityData['bonus'];
                    unset($entityData['bonus']);
                }
                break;
        }

        return $entityData;
    }

    /**
     * Restaure une entité
     */
    private function restoreEntity(string $entityType, array $entityData): void
    {
        // LOG DE DEBUG
        Log::info("=== DEBUT restoreEntity ===");
        Log::info("Type: {$entityType}");
        Log::info("Données AVANT mapping:", $entityData);

        // Mapper les anciennes colonnes vers les nouvelles ET ajouter les champs manquants
        $entityData = $this->mapOldColumnsToNew($entityType, $entityData);

        // LOG DE DEBUG
        Log::info("Données APRÈS mapping:", $entityData);

        // Retirer les timestamps pour éviter les conflits
        unset($entityData['created_at'], $entityData['updated_at']);

        // Ajouter les nouveaux timestamps
        $entityData['created_at'] = now();
        $entityData['updated_at'] = now();

        // LOG DE DEBUG
        Log::info("Données FINALES avant insertion:", $entityData);

        switch ($entityType) {
            case 'distributeur':
                DB::table('distributeurs')->insert($entityData);
                break;
            case 'achat':
                DB::table('achats')->insert($entityData);
                break;
            case 'product':
                DB::table('products')->insert($entityData);
                break;
            case 'bonus':
                DB::table('bonuses')->insert($entityData);
                break;
            default:
                throw new \Exception("Type d'entité non supporté pour la restauration : {$entityType}");
        }

        Log::info("=== FIN restoreEntity - Insertion réussie ===");
    }

    /**
     * Restaure les données liées
     * VERSION CORRIGÉE : Gestion robuste des données manquantes
     */
    private function restoreRelatedData(string $entityType, int $entityId, array $relatedData): void
    {
        Log::info("Restauration des données liées", [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'related_keys' => array_keys($relatedData)
        ]);

        // Restaurer les achats d'un distributeur
        if ($entityType === 'distributeur' && isset($relatedData['achats'])) {
            $restored = 0;
            $skipped = 0;

            foreach ($relatedData['achats'] as $achat) {
                try {
                    // IMPORTANT : S'assurer que le distributeur_id est présent
                    if (!isset($achat['distributeur_id'])) {
                        $achat['distributeur_id'] = $entityId;
                        Log::info("Ajout du distributeur_id manquant dans l'achat", [
                            'achat_id' => $achat['id'] ?? 'unknown',
                            'distributeur_id' => $entityId
                        ]);
                    }

                    // Vérifier les champs obligatoires avant insertion
                    if (!isset($achat['products_id'])) {
                        Log::warning("Achat ignoré car products_id manquant", [
                            'achat_id' => $achat['id'] ?? 'unknown',
                            'distributeur_id' => $entityId,
                            'achat_data' => $achat
                        ]);
                        $skipped++;
                        continue; // Passer à l'achat suivant
                    }

                    // Mapper les données de l'achat
                    $achat = $this->mapOldColumnsToNew('achat', $achat);

                    // Supprimer les timestamps pour éviter les conflits
                    unset($achat['created_at'], $achat['updated_at']);
                    $achat['created_at'] = now();
                    $achat['updated_at'] = now();

                    DB::table('achats')->insert($achat);
                    $restored++;
                    Log::info("Achat restauré avec succès", ['achat_id' => $achat['id'] ?? 'new']);

                } catch (\Exception $e) {
                    Log::error("Erreur lors de la restauration d'un achat lié", [
                        'error' => $e->getMessage(),
                        'achat_data' => $achat
                    ]);
                    $skipped++;
                }
            }

            if ($skipped > 0) {
                Log::warning("Certains achats n'ont pas pu être restaurés", [
                    'restored' => $restored,
                    'skipped' => $skipped,
                    'total' => count($relatedData['achats'])
                ]);
            }
        }

        // Restaurer les bonus d'un distributeur
        if ($entityType === 'distributeur' && isset($relatedData['bonuses'])) {
            foreach ($relatedData['bonuses'] as $bonus) {
                try {
                    // S'assurer que le distributeur_id est présent
                    if (!isset($bonus['distributeur_id'])) {
                        $bonus['distributeur_id'] = $entityId;
                    }

                    $bonus = $this->mapOldColumnsToNew('bonus', $bonus);
                    unset($bonus['created_at'], $bonus['updated_at']);
                    $bonus['created_at'] = now();
                    $bonus['updated_at'] = now();

                    DB::table('bonuses')->insert($bonus);

                } catch (\Exception $e) {
                    Log::error("Erreur lors de la restauration d'un bonus lié", [
                        'error' => $e->getMessage(),
                        'bonus_data' => $bonus
                    ]);
                }
            }
        }

        // Restaurer les level_currents d'un distributeur
        if ($entityType === 'distributeur' && isset($relatedData['level_currents'])) {
            foreach ($relatedData['level_currents'] as $level) {
                try {
                    // S'assurer que le distributeur_id est présent
                    if (!isset($level['distributeur_id'])) {
                        $level['distributeur_id'] = $entityId;
                    }

                    unset($level['created_at'], $level['updated_at']);
                    $level['created_at'] = now();
                    $level['updated_at'] = now();

                    DB::table('level_currents')->insert($level);

                } catch (\Exception $e) {
                    Log::error("Erreur lors de la restauration d'un level_current lié", [
                        'error' => $e->getMessage(),
                        'level_data' => $level
                    ]);
                }
            }
        }
    }

    /**
     * Liste les backups disponibles
     */
    public function listBackups(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::table('deletion_backups');

        // Appliquer les filtres
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['restored'])) {
            if ($filters['restored'] === 'yes') {
                $query->whereNotNull('restored_at');
            } else {
                $query->whereNull('restored_at');
            }
        }

        // Ordonner par date de création décroissante
        $query->orderBy('created_at', 'desc');

        // Paginer les résultats
        $backups = $query->paginate(20);

        // Transformer les données JSON en tableaux PHP
        $backups->transform(function ($backup) {
            $backup->backup_data = json_decode($backup->backup_data, true);

            // Ajouter la relation creator si nécessaire
            if ($backup->created_by) {
                $backup->creator = DB::table('users')
                    ->where('id', $backup->created_by)
                    ->first(['id', 'name', 'email']);
            }

            // Convertir les dates en objets Carbon
            $backup->created_at = Carbon::parse($backup->created_at);
            $backup->restored_at = $backup->restored_at ? Carbon::parse($backup->restored_at) : null;

            return $backup;
        });

        return $backups;
    }

    /**
     * Exporte un backup vers un fichier téléchargeable
     */
    public function exportBackup(string $backupId): array
    {
        try {
            $backup = DB::table('deletion_backups')
                ->where('backup_id', $backupId)
                ->first();

            if (!$backup) {
                throw new \Exception("Backup introuvable : {$backupId}");
            }

            $filename = "backup_{$backupId}.json";
            $content = json_encode(json_decode($backup->backup_data), JSON_PRETTY_PRINT);

            return [
                'success' => true,
                'filename' => $filename,
                'content' => $content,
                'mime_type' => 'application/json'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
