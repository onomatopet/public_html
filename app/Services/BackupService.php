<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;
use ZipArchive;

class BackupService
{
    protected string $backupPath;
    protected array $excludedDirectories = [
        'node_modules',
        'vendor',
        '.git',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'storage/debugbar'
    ];

    public function __construct()
    {
        $this->backupPath = storage_path('app/backups');
        $this->ensureBackupDirectoryExists();
    }

    /**
     * S'assure que le répertoire de backup existe
     */
    protected function ensureBackupDirectoryExists(): void
    {
        if (!file_exists($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }

        // Créer aussi les sous-dossiers pour les backups d'entités
        $entityTypes = ['distributeur', 'product', 'achat', 'bonus'];
        foreach ($entityTypes as $type) {
            $path = $this->backupPath . '/' . $type;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Crée un backup système (database/files/full)
     * Utilisé par SettingsController
     */
    public function createBackup(string $type = 'full'): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "backup_{$type}_{$timestamp}";

        switch ($type) {
            case 'database':
                return $this->backupDatabase($filename);

            case 'files':
                return $this->backupFiles($filename);

            case 'full':
                return $this->backupFull($filename);

            default:
                throw new \InvalidArgumentException("Type de backup invalide: {$type}");
        }
    }

    /**
     * Crée un backup d'entité MLM
     * Utilisé par DeletionRequestController
     */
    public function createEntityBackup(string $entityType, int $entityId): array
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
            $filename = "{$entityType}/{$backupId}.json";
            $fullPath = $this->backupPath . '/' . $filename;

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
     * Sauvegarde uniquement la base de données
     */
    protected function backupDatabase(string $filename): string
    {
        $filename .= '.sql';
        $filepath = $this->backupPath . '/' . $filename;

        try {
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port', 3306);

            // Commande mysqldump
            $command = sprintf(
                'mysqldump --single-transaction --routines --triggers --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($filepath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                // Essayer une méthode alternative si mysqldump n'est pas disponible
                return $this->backupDatabaseAlternative($filename);
            }

            // Compresser le fichier SQL
            $this->compressFile($filepath);
            unlink($filepath); // Supprimer le fichier non compressé

            Log::info("Backup database créé: {$filename}.gz");
            return $filename . '.gz';

        } catch (\Exception $e) {
            Log::error("Erreur backup database: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Méthode alternative pour backup database sans mysqldump
     */
    protected function backupDatabaseAlternative(string $filename): string
    {
        $filepath = $this->backupPath . '/' . $filename;
        $sql = "-- Backup database alternative\n";
        $sql .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";

        // Récupérer toutes les tables
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_{$dbName}"};

            // Structure de la table
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`")[0]->{"Create Table"};
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createTable . ";\n\n";

            // Données de la table
            $rows = DB::table($tableName)->get();
            if ($rows->count() > 0) {
                $sql .= "INSERT INTO `{$tableName}` VALUES\n";
                $values = [];

                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $rowValues[] = $value;
                        } else {
                            $rowValues[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = '(' . implode(',', $rowValues) . ')';
                }

                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        file_put_contents($filepath, $sql);

        // Compresser
        $this->compressFile($filepath);
        unlink($filepath);

        return $filename . '.gz';
    }

    /**
     * Sauvegarde uniquement les fichiers
     */
    protected function backupFiles(string $filename): string
    {
        $filename .= '.zip';
        $filepath = $this->backupPath . '/' . $filename;

        try {
            $zip = new ZipArchive();

            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Impossible de créer l'archive ZIP");
            }

            // Ajouter les dossiers importants
            $this->addDirectoryToZip($zip, base_path('app'), 'app');
            $this->addDirectoryToZip($zip, base_path('config'), 'config');
            $this->addDirectoryToZip($zip, base_path('database'), 'database');
            $this->addDirectoryToZip($zip, base_path('resources'), 'resources');
            $this->addDirectoryToZip($zip, base_path('routes'), 'routes');
            $this->addDirectoryToZip($zip, storage_path('app'), 'storage/app', ['backups']);

            // Ajouter les fichiers racine importants
            $rootFiles = ['.env', 'composer.json', 'composer.lock', 'package.json'];
            foreach ($rootFiles as $file) {
                $path = base_path($file);
                if (file_exists($path)) {
                    $zip->addFile($path, $file);
                }
            }

            $zip->close();

            Log::info("Backup fichiers créé: {$filename}");
            return $filename;

        } catch (\Exception $e) {
            Log::error("Erreur backup fichiers: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sauvegarde complète (database + fichiers)
     */
    protected function backupFull(string $filename): string
    {
        try {
            // Créer d'abord le backup de la base de données
            $dbBackup = $this->backupDatabase($filename . '_db');

            // Puis le backup des fichiers
            $filesBackup = $this->backupFiles($filename . '_files');

            // Créer une archive combinée
            $fullBackupName = $filename . '_full.zip';
            $fullBackupPath = $this->backupPath . '/' . $fullBackupName;

            $zip = new ZipArchive();
            if ($zip->open($fullBackupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile($this->backupPath . '/' . $dbBackup, $dbBackup);
                $zip->addFile($this->backupPath . '/' . $filesBackup, $filesBackup);
                $zip->close();

                // Supprimer les fichiers individuels
                unlink($this->backupPath . '/' . $dbBackup);
                unlink($this->backupPath . '/' . $filesBackup);
            }

            Log::info("Backup complet créé: {$fullBackupName}");
            return $fullBackupName;

        } catch (\Exception $e) {
            Log::error("Erreur backup complet: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Compresse un fichier avec gzip
     */
    protected function compressFile(string $filepath): void
    {
        $data = file_get_contents($filepath);
        $gzdata = gzencode($data, 9);
        file_put_contents($filepath . '.gz', $gzdata);
    }

    /**
     * Ajoute un répertoire à une archive ZIP
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $directory, string $localPath = '', array $exclude = []): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $relativePath = str_replace($directory . '/', '', $filePath);

                // Vérifier les exclusions
                $shouldExclude = false;
                foreach ($exclude as $excludePattern) {
                    if (strpos($relativePath, $excludePattern) !== false) {
                        $shouldExclude = true;
                        break;
                    }
                }

                // Vérifier aussi les répertoires exclus globalement
                foreach ($this->excludedDirectories as $excludedDir) {
                    if (strpos($filePath, $excludedDir) !== false) {
                        $shouldExclude = true;
                        break;
                    }
                }

                if (!$shouldExclude) {
                    $localName = $localPath ? $localPath . '/' . $relativePath : $relativePath;
                    $zip->addFile($filePath, $localName);
                }
            }
        }
    }

    // === MÉTHODES POUR LES BACKUPS D'ENTITÉS (depuis votre code existant) ===

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
                    'restored_by' => Auth::id() ?: null,
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

    // ... (inclure toutes les autres méthodes de votre BackupService existant)
    // getRelatedData, getEntityData, entityExists, mapOldColumnsToNew, etc.

    /**
     * Récupère les données liées à une entité
     */
    private function getRelatedData(string $entityType, int $entityId): array
    {
        $relatedData = [];

        switch ($entityType) {
            case 'distributeur':
                // Récupérer les achats AVEC TOUS LES CHAMPS
                $relatedData['achats'] = DB::table('achats')
                    ->where('distributeur_id', $entityId)
                    ->select('*')
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

                break;

            case 'product':
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
     * Mapper les anciennes colonnes vers les nouvelles
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

                // S'assurer que les colonnes requises existent
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

                if (!isset($entityData['purchase_date'])) {
                    if (isset($entityData['created_at'])) {
                        $entityData['purchase_date'] = date('Y-m-d', strtotime($entityData['created_at']));
                    } else {
                        $entityData['purchase_date'] = date('Y-m-d');
                    }
                }

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

    /**
     * Restaure une entité
     */
    private function restoreEntity(string $entityType, array $entityData): void
    {
        // Mapper les anciennes colonnes vers les nouvelles
        $entityData = $this->mapOldColumnsToNew($entityType, $entityData);

        // Retirer les timestamps pour éviter les conflits
        unset($entityData['created_at'], $entityData['updated_at']);

        // Ajouter les nouveaux timestamps
        $entityData['created_at'] = now();
        $entityData['updated_at'] = now();

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
    }

    /**
     * Restaure les données liées
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
                    if (!isset($achat['distributeur_id'])) {
                        $achat['distributeur_id'] = $entityId;
                    }

                    if (!isset($achat['products_id'])) {
                        Log::warning("Achat ignoré car products_id manquant", [
                            'achat_id' => $achat['id'] ?? 'unknown',
                            'distributeur_id' => $entityId
                        ]);
                        $skipped++;
                        continue;
                    }

                    $achat = $this->mapOldColumnsToNew('achat', $achat);
                    unset($achat['created_at'], $achat['updated_at']);
                    $achat['created_at'] = now();
                    $achat['updated_at'] = now();

                    DB::table('achats')->insert($achat);
                    $restored++;

                } catch (\Exception $e) {
                    Log::error("Erreur lors de la restauration d'un achat lié", [
                        'error' => $e->getMessage(),
                        'achat_data' => $achat
                    ]);
                    $skipped++;
                }
            }
        }

        // Restaurer les bonus d'un distributeur
        if ($entityType === 'distributeur' && isset($relatedData['bonuses'])) {
            foreach ($relatedData['bonuses'] as $bonus) {
                try {
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
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Restaurer les level_currents d'un distributeur
        if ($entityType === 'distributeur' && isset($relatedData['level_currents'])) {
            foreach ($relatedData['level_currents'] as $level) {
                try {
                    if (!isset($level['distributeur_id'])) {
                        $level['distributeur_id'] = $entityId;
                    }

                    unset($level['created_at'], $level['updated_at']);
                    $level['created_at'] = now();
                    $level['updated_at'] = now();

                    DB::table('level_currents')->insert($level);

                } catch (\Exception $e) {
                    Log::error("Erreur lors de la restauration d'un level_current lié", [
                        'error' => $e->getMessage()
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
