<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectBackup extends Command
{
    protected $signature = 'backup:inspect {backup_id}';
    protected $description = 'Inspecte en détail le contenu d\'un backup spécifique';

    public function handle()
    {
        $backupId = $this->argument('backup_id');

        // Recherche partielle si ID court fourni
        $backup = DB::table('deletion_backups')
            ->where('backup_id', 'like', $backupId . '%')
            ->first();

        if (!$backup) {
            $this->error("Backup non trouvé : {$backupId}");
            return 1;
        }

        $this->info("=== INSPECTION DU BACKUP ===");
        $this->info("ID complet : " . $backup->backup_id);
        $this->info("Créé le : " . $backup->created_at);
        $this->info("Restauré : " . ($backup->restored_at ? "Oui ({$backup->restored_at})" : "Non"));

        $backupData = json_decode($backup->backup_data, true);

        if (!$backupData) {
            $this->error("Impossible de décoder les données JSON");
            return 1;
        }

        // Afficher l'entité principale
        $this->info("\n=== ENTITÉ PRINCIPALE ===");
        $this->info("Type : " . ($backupData['entity_type'] ?? 'N/A'));
        $this->info("ID : " . ($backupData['entity_id'] ?? 'N/A'));

        if (isset($backupData['entity_data'])) {
            $this->info("\nDonnées de l'entité :");
            $this->displayData($backupData['entity_data']);
        }

        // Afficher les données liées
        if (isset($backupData['related_data']) && !empty($backupData['related_data'])) {
            $this->info("\n=== DONNÉES LIÉES ===");

            foreach ($backupData['related_data'] as $type => $items) {
                $this->info("\n{$type} (" . (is_array($items) ? count($items) : 0) . " enregistrements)");

                if (is_array($items) && count($items) > 0) {
                    // Afficher le premier élément en détail
                    $this->info("\nPremier enregistrement :");

                    // Vérifier si c'est un tableau indexé ou associatif
                    if (isset($items[0])) {
                        // Tableau indexé
                        $this->displayData($items[0]);
                    } else {
                        // Tableau associatif ou structure différente
                        $firstKey = array_key_first($items);
                        if ($firstKey !== null) {
                            $this->info("Clé : {$firstKey}");
                            $this->displayData(is_array($items[$firstKey]) ? $items[$firstKey] : [$firstKey => $items[$firstKey]]);
                        }
                    }

                    if (count($items) > 1) {
                        $this->info("\n... et " . (count($items) - 1) . " autres enregistrements");

                        // Afficher un résumé des champs manquants seulement pour les tableaux indexés
                        if (isset($items[0])) {
                            $this->analyzeMissingFields($type, $items);
                        }
                    }
                }
            }
        }

        // Métadonnées
        if (isset($backupData['metadata'])) {
            $this->info("\n=== MÉTADONNÉES ===");
            $this->displayData($backupData['metadata']);
        }

        return 0;
    }

    private function displayData(array $data, $indent = 0): void
    {
        foreach ($data as $key => $value) {
            $prefix = str_repeat('  ', $indent);

            if (is_array($value)) {
                $this->line("{$prefix}{$key}:");
                $this->displayData($value, $indent + 1);
            } else {
                $displayValue = is_null($value) ? '<null>' : (string)$value;
                $this->line("{$prefix}{$key}: {$displayValue}");
            }
        }
    }

    private function analyzeMissingFields(string $type, array $items): void
    {
        $requiredFields = [
            'achats' => ['id', 'period', 'distributeur_id', 'products_id', 'purchase_date'],
            'bonuses' => ['id', 'distributeur_id', 'period', 'montant'],
            'level_currents' => ['id', 'distributeur_id', 'period', 'etoiles']
        ];

        if (!isset($requiredFields[$type])) {
            return;
        }

        $missingFieldsCount = [];

        foreach ($items as $item) {
            foreach ($requiredFields[$type] as $field) {
                if (!isset($item[$field])) {
                    if (!isset($missingFieldsCount[$field])) {
                        $missingFieldsCount[$field] = 0;
                    }
                    $missingFieldsCount[$field]++;
                }
            }
        }

        if (!empty($missingFieldsCount)) {
            $this->warn("\nChamps manquants dans {$type} :");
            foreach ($missingFieldsCount as $field => $count) {
                $this->warn("  - {$field} : manquant dans {$count} enregistrement(s)");
            }
        }
    }
}
