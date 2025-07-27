<?php
// app/Console/Commands/RunBatchAggregation.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BatchAggregationService;
use App\Models\SystemPeriod;

class RunBatchAggregation extends Command
{
    protected $signature = 'mlm:aggregate-batch
                            {period? : La période à traiter (YYYY-MM)}
                            {--dry-run : Simuler sans appliquer les changements}
                            {--batch-size=100 : Taille des lots pour le traitement}
                            {--report : Générer un rapport après traitement}';

    protected $description = 'Exécute l\'agrégation batch des achats et la propagation des cumuls';

    protected BatchAggregationService $batchService;

    public function __construct(BatchAggregationService $batchService)
    {
        parent::__construct();
        $this->batchService = $batchService;
    }

    public function handle()
    {
        // Déterminer la période
        $period = $this->argument('period');
        if (!$period) {
            $currentPeriod = SystemPeriod::getCurrentPeriod();
            if (!$currentPeriod) {
                $this->error('Aucune période courante définie. Veuillez spécifier une période.');
                return 1;
            }
            $period = $currentPeriod->period;
        }

        $this->info("=== Agrégation Batch MLM ===");
        $this->info("Période: {$period}");

        if ($this->option('dry-run')) {
            $this->warn("Mode simulation activé - Aucune modification ne sera appliquée");
        }

        // Confirmer l'exécution
        if (!$this->option('dry-run') && !$this->confirm("Voulez-vous exécuter l'agrégation pour la période {$period} ?")) {
            $this->comment('Opération annulée.');
            return 0;
        }

        // Exécuter l'agrégation
        $this->info('Traitement en cours...');

        $result = $this->batchService->executeBatchAggregation($period, [
            'dry_run' => $this->option('dry-run'),
            'batch_size' => (int) $this->option('batch-size')
        ]);

        if ($result['success']) {
            $this->info($result['message']);

            // Afficher les statistiques
            $this->table(
                ['Métrique', 'Valeur'],
                [
                    ['Durée', $result['duration'] . ' secondes'],
                    ['Distributeurs traités', $result['stats']['distributors_processed']],
                    ['Mises à jour', $result['stats']['updates']],
                    ['Insertions', $result['stats']['inserts']],
                    ['Propagations réussies', $result['stats']['propagation']['propagated'] ?? 'N/A'],
                    ['Erreurs de propagation', $result['stats']['propagation']['errors'] ?? 'N/A']
                ]
            );

            // Générer le rapport si demandé
            if ($this->option('report') && !$this->option('dry-run')) {
                $this->info("\nGénération du rapport...");
                $report = $this->batchService->generateAggregationReport($period);

                $this->info("\n=== RAPPORT D'AGRÉGATION ===");
                $this->info("Distributeurs actifs: " . $report['summary']['total_distributeurs_actifs']);
                $this->info("Total points: " . number_format($report['summary']['total_points']));
                $this->info("Moyenne points: " . number_format($report['summary']['average_points'], 2));

                if (count($report['top_performers']) > 0) {
                    $this->info("\nTop 10 Performers:");
                    $this->table(
                        ['Matricule', 'Nom', 'Points', 'Grade'],
                        $report['top_performers']->map(function($p) {
                            return [
                                $p['matricule'],
                                $p['nom'],
                                number_format($p['points']),
                                $p['grade'] . ' ⭐'
                            ];
                        })->toArray()
                    );
                }
            }

            return 0;
        } else {
            $this->error($result['message']);
            return 1;
        }
    }
}
