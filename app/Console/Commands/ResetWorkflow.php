<?php

namespace App\Console\Commands;

use App\Models\SystemPeriod;
use App\Models\WorkflowLog;
use App\Models\Achat;
use App\Models\LevelCurrent;
use App\Models\Bonus;
use App\Models\AvancementHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetWorkflow extends Command
{
    protected $signature = 'mlm:reset-workflow
                            {period : La période à réinitialiser (ex: 2024-11)}
                            {--step= : Réinitialiser à partir d\'une étape spécifique}
                            {--force : Forcer la réinitialisation sans confirmation}
                            {--dry-run : Mode simulation}';

    protected $description = 'Réinitialise le workflow pour une période donnée';

    public function handle()
    {
        $period = $this->argument('period');
        $step = $this->option('step');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        // Vérifier que la période existe
        $systemPeriod = SystemPeriod::where('period', $period)->first();
        if (!$systemPeriod) {
            $this->error("La période {$period} n'existe pas!");
            return 1;
        }

        // Afficher l'état actuel
        $this->info("État actuel de la période {$period}:");
        $this->table(
            ['Propriété', 'Valeur'],
            [
                ['Status', $systemPeriod->status],
                ['Achats validés', $systemPeriod->purchases_validated ? 'Oui' : 'Non'],
                ['Achats agrégés', $systemPeriod->purchases_aggregated ? 'Oui' : 'Non'],
                ['Avancements calculés', $systemPeriod->advancements_calculated ? 'Oui' : 'Non'],
                ['Snapshot créé', $systemPeriod->snapshot_created ? 'Oui' : 'Non'],
            ]
        );

        // Demander confirmation
        if (!$force && !$dryRun) {
            if (!$this->confirm("Êtes-vous sûr de vouloir réinitialiser le workflow pour {$period}?")) {
                $this->info("Opération annulée.");
                return 0;
            }
        }

        if ($dryRun) {
            $this->warn("MODE SIMULATION - Aucune modification ne sera effectuée");
        }

        // Déterminer à partir de quelle étape réinitialiser
        $resetSteps = $this->determineResetSteps($step);

        $this->info("Étapes à réinitialiser: " . implode(', ', array_keys(array_filter($resetSteps))));

        DB::beginTransaction();
        try {
            // 1. Réinitialiser les logs du workflow
            if (!$dryRun) {
                WorkflowLog::where('period', $period)->delete();
                $this->info("✓ Logs du workflow supprimés");
            }

            // 2. Réinitialiser les bonus (si nécessaire)
            if ($resetSteps['bonus']) {
                $count = Bonus::where('period', $period)->count();
                if (!$dryRun) {
                    Bonus::where('period', $period)->delete();
                }
                $this->info("✓ {$count} bonus supprimés");
            }

            // 3. Réinitialiser l'historique des avancements
            if ($resetSteps['advancements']) {
                $count = 0;
                if (Schema::hasTable('avancement_history')) {
                    $count = DB::table('avancement_history')
                        ->where('period', $period)
                        ->count();
                    if (!$dryRun) {
                        DB::table('avancement_history')
                            ->where('period', $period)
                            ->delete();
                    }
                }
                $this->info("✓ {$count} avancements supprimés");

                // Réinitialiser les grades dans level_currents
                if (!$dryRun) {
                    LevelCurrent::where('period', $period)
                        ->update(['etoiles' => DB::raw('(SELECT etoiles_id FROM distributeurs WHERE distributeurs.id = level_currents.distributeur_id)')]);
                }
            }

            // 4. Réinitialiser les cumuls (si agrégation à refaire)
            if ($resetSteps['aggregation']) {
                if (!$dryRun) {
                    // Réinitialiser new_cumul et cumul_total à 0
                    LevelCurrent::where('period', $period)
                        ->update([
                            'new_cumul' => 0,
                            'cumul_total' => 0
                        ]);

                    // Pour cumul_individuel et cumul_collectif, il faut les recalculer
                    // en soustrayant les valeurs de la période
                    $this->resetHistoricalCumuls($period, $dryRun);
                }
                $this->info("✓ Cumuls réinitialisés");
            }

            // 5. Réinitialiser le statut des achats
            if ($resetSteps['validation']) {
                $count = Achat::where('period', $period)
                    ->where('statut', '!=', 'pending')
                    ->count();
                if (!$dryRun) {
                    Achat::where('period', $period)->update(['statut' => 'pending']);
                }
                $this->info("✓ {$count} achats remis en statut 'pending'");
            }

            // 6. Mettre à jour SystemPeriod
            if (!$dryRun) {
                $updates = [];

                if ($resetSteps['validation']) {
                    $updates['purchases_validated'] = false;
                    $updates['purchases_validated_at'] = null;
                    $updates['purchases_validated_by'] = null;
                }

                if ($resetSteps['aggregation']) {
                    $updates['purchases_aggregated'] = false;
                    $updates['purchases_aggregated_at'] = null;
                    $updates['purchases_aggregated_by'] = null;
                }

                if ($resetSteps['advancements']) {
                    $updates['advancements_calculated'] = false;
                    $updates['advancements_calculated_at'] = null;
                    $updates['advancements_calculated_by'] = null;
                }

                if ($resetSteps['snapshot']) {
                    $updates['snapshot_created'] = false;
                    $updates['snapshot_created_at'] = null;
                    $updates['snapshot_created_by'] = null;
                }

                // Réinitialiser le statut si nécessaire
                if ($systemPeriod->status === 'closed' ||
                    ($systemPeriod->status === 'validation' && $resetSteps['validation'])) {
                    $updates['status'] = 'active';
                }

                $systemPeriod->update($updates);
                $this->info("✓ SystemPeriod mis à jour");
            }

            if ($dryRun) {
                DB::rollback();
                $this->info("\nSIMULATION TERMINÉE - Aucune modification effectuée");
            } else {
                DB::commit();
                $this->success("\nWorkflow réinitialisé avec succès pour la période {$period}!");
            }

            // Afficher le nouvel état
            $systemPeriod->refresh();
            $this->info("\nNouvel état:");
            $this->table(
                ['Propriété', 'Valeur'],
                [
                    ['Status', $systemPeriod->status],
                    ['Achats validés', $systemPeriod->purchases_validated ? 'Oui' : 'Non'],
                    ['Achats agrégés', $systemPeriod->purchases_aggregated ? 'Oui' : 'Non'],
                    ['Avancements calculés', $systemPeriod->advancements_calculated ? 'Oui' : 'Non'],
                    ['Snapshot créé', $systemPeriod->snapshot_created ? 'Oui' : 'Non'],
                ]
            );

        } catch (\Exception $e) {
            DB::rollback();
            $this->error("Erreur: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Détermine quelles étapes doivent être réinitialisées
     */
    protected function determineResetSteps(?string $fromStep): array
    {
        $steps = [
            'validation' => false,
            'aggregation' => false,
            'advancements' => false,
            'snapshot' => false,
            'bonus' => false,
        ];

        // Si pas d'étape spécifiée, tout réinitialiser
        if (!$fromStep) {
            return array_fill_keys(array_keys($steps), true);
        }

        // Réinitialiser à partir de l'étape spécifiée
        $found = false;
        $stepOrder = ['validation', 'aggregation', 'advancements', 'bonus', 'snapshot'];

        foreach ($stepOrder as $step) {
            if ($step === $fromStep || $found) {
                $steps[$step] = true;
                $found = true;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException("Étape inconnue: {$fromStep}. Étapes valides: " . implode(', ', $stepOrder));
        }

        return $steps;
    }

    /**
     * Réinitialise les cumuls historiques
     */
    protected function resetHistoricalCumuls(string $period, bool $dryRun): void
    {
        // Récupérer les achats de la période
        $achatsParDistributeur = Achat::where('period', $period)
            ->groupBy('distributeur_id')
            ->selectRaw('distributeur_id, SUM(points_unitaire_achat * qt) as total_points')
            ->get();

        foreach ($achatsParDistributeur as $achat) {
            if (!$dryRun) {
                // Soustraire les points de cette période des cumuls historiques
                LevelCurrent::where('period', $period)
                    ->where('distributeur_id', $achat->distributeur_id)
                    ->decrement('cumul_individuel', $achat->total_points);

                // Pour cumul_collectif, il faut aussi propager la soustraction aux parents
                $this->propagateNegativeCumul($achat->distributeur_id, $achat->total_points, $period);
            }
        }
    }

    /**
     * Propage la soustraction dans la hiérarchie
     */
    protected function propagateNegativeCumul(int $distributeurId, float $amount, string $period): void
    {
        // Cette méthode devrait idéalement utiliser CumulManagementService
        // avec une option pour soustraire au lieu d'ajouter

        $distributeur = \App\Models\Distributeur::find($distributeurId);
        if (!$distributeur || !$distributeur->id_distrib_parent) {
            return;
        }

        $currentParentId = $distributeur->id_distrib_parent;
        $maxDepth = 50;
        $depth = 0;

        while ($currentParentId && $depth < $maxDepth) {
            LevelCurrent::where('period', $period)
                ->where('distributeur_id', $currentParentId)
                ->decrement('cumul_collectif', $amount);

            $parent = \App\Models\Distributeur::find($currentParentId);
            $currentParentId = $parent ? $parent->id_distrib_parent : null;
            $depth++;
        }
    }
}
