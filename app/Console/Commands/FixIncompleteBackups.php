<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixIncompleteBackups extends Command
{
    protected $signature = 'backup:fix-incomplete {--analyze : Analyser seulement sans modifier}';
    protected $description = 'Corrige les backups incomplets en essayant de retrouver les données manquantes';

    public function handle()
    {
        $analyzeOnly = $this->option('analyze');

        $this->info($analyzeOnly ? '🔍 Analyse des backups incomplets...' : '🔧 Correction des backups incomplets...');

        $backups = DB::table('deletion_backups')
            ->where('entity_type', 'distributeur')
            ->whereNull('restored_at')
            ->get();

        $issues = [];
        $fixed = 0;

        foreach ($backups as $backup) {
            $backupData = json_decode($backup->backup_data, true);

            if (!isset($backupData['related_data']['achats'])) {
                continue;
            }

            $hasIssues = false;
            $fixableAchats = [];
            $unfixableAchats = [];

            foreach ($backupData['related_data']['achats'] as $index => $achat) {
                if (!isset($achat['products_id']) && !isset($achat['distributeur_id'])) {
                    $hasIssues = true;

                    // Essayer de retrouver les informations manquantes
                    $canFix = false;
                    $fixedAchat = $achat;

                    // Le distributeur_id peut être déduit du parent
                    if (!isset($achat['distributeur_id'])) {
                        $fixedAchat['distributeur_id'] = $backupData['entity_id'];
                        $canFix = true;
                    }

                    // Pour products_id, on doit chercher dans la base si possible
                    if (!isset($achat['products_id']) && isset($achat['id'])) {
                        // Vérifier si on peut retrouver le products_id original
                        // Note: Ceci ne fonctionnera que si l'achat existe encore quelque part

                        // Tentative 1: Chercher dans la table achats (au cas où il y aurait des doublons)
                        $originalAchat = DB::table('achats')
                            ->where('id', $achat['id'])
                            ->whereNotNull('products_id')
                            ->first();

                        if ($originalAchat) {
                            $fixedAchat['products_id'] = $originalAchat->products_id;
                            $canFix = true;
                            $this->info("  ✓ Trouvé products_id {$originalAchat->products_id} pour achat {$achat['id']}");
                        } else {
                            // Tentative 2: Essayer de déduire à partir du montant et de la période
                            $this->warn("  ✗ Impossible de retrouver products_id pour achat {$achat['id']}");
                        }
                    }

                    if ($canFix && isset($fixedAchat['products_id'])) {
                        $fixableAchats[$index] = $fixedAchat;
                    } else {
                        $unfixableAchats[$index] = $achat;
                    }
                }
            }

            if ($hasIssues) {
                $issue = [
                    'backup_id' => $backup->backup_id,
                    'entity_id' => $backupData['entity_id'],
                    'total_achats' => count($backupData['related_data']['achats']),
                    'fixable' => count($fixableAchats),
                    'unfixable' => count($unfixableAchats),
                    'created_at' => $backup->created_at
                ];

                $issues[] = $issue;

                if (!$analyzeOnly && count($fixableAchats) > 0) {
                    // Appliquer les corrections
                    foreach ($fixableAchats as $index => $fixedAchat) {
                        $backupData['related_data']['achats'][$index] = $fixedAchat;
                    }

                    // Mettre à jour le backup
                    DB::table('deletion_backups')
                        ->where('id', $backup->id)
                        ->update([
                            'backup_data' => json_encode($backupData),
                            'updated_at' => now()
                        ]);

                    $fixed++;
                    $this->info("✅ Backup {$backup->backup_id} partiellement corrigé");
                }
            }
        }

        // Afficher le rapport
        if (empty($issues)) {
            $this->info("✅ Aucun backup incomplet trouvé!");
            return 0;
        }

        $this->table(
            ['Backup ID', 'Distributeur', 'Total Achats', 'Corrigibles', 'Non corrigibles', 'Date'],
            collect($issues)->map(function ($issue) {
                return [
                    substr($issue['backup_id'], 0, 20) . '...',
                    $issue['entity_id'],
                    $issue['total_achats'],
                    $issue['fixable'],
                    $issue['unfixable'],
                    Carbon::parse($issue['created_at'])->format('Y-m-d H:i')
                ];
            })
        );

        if (!$analyzeOnly) {
            $this->info("\n✅ {$fixed} backups partiellement corrigés");

            if (collect($issues)->sum('unfixable') > 0) {
                $this->warn("\n⚠️ Certains achats ne peuvent pas être restaurés car le products_id est introuvable.");
                $this->warn("Options :");
                $this->warn("1. Restaurer le distributeur sans ces achats");
                $this->warn("2. Recréer manuellement ces achats après restauration");
                $this->warn("3. Abandonner la restauration");
            }
        }

        return 0;
    }
}
