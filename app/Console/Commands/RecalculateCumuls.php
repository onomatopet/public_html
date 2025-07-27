<?php

namespace App\Console\Commands;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Services\CumulManagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateCumuls extends Command
{
    protected $signature = 'mlm:recalculate-cumuls
                            {distributeur? : Matricule du distributeur (optionnel)}
                            {--period= : Période spécifique (défaut: période courante)}
                            {--fix-individual : Recalculer les cumuls individuels}
                            {--dry-run : Mode simulation}';

    protected $description = 'Recalcule les cumuls pour un distributeur ou tous les distributeurs';

    private CumulManagementService $cumulService;

    public function __construct(CumulManagementService $cumulService)
    {
        parent::__construct();
        $this->cumulService = $cumulService;
    }

    public function handle()
    {
        $period = $this->option('period') ?: date('Y-m');
        $dryRun = $this->option('dry-run');
        $matricule = $this->argument('distributeur');

        $this->info("Recalcul des cumuls pour la période: {$period}");

        if ($dryRun) {
            $this->warn("MODE SIMULATION - Aucune modification ne sera sauvegardée");
        }

        if ($matricule) {
            // Recalcul pour un distributeur spécifique
            $this->recalculateForDistributor($matricule, $period, $dryRun);
        } else {
            // Recalcul global
            $this->recalculateAll($period, $dryRun);
        }

        $this->info("Recalcul terminé!");
    }

    private function recalculateForDistributor(string $matricule, string $period, bool $dryRun): void
    {
        $distributeur = Distributeur::where('distributeur_id', $matricule)->first();

        if (!$distributeur) {
            $this->error("Distributeur non trouvé: {$matricule}");
            return;
        }

        $this->info("Traitement du distributeur: {$distributeur->full_name} (#{$matricule})");

        if ($this->option('fix-individual')) {
            $oldValue = LevelCurrent::where('distributeur_id', $distributeur->id)
                                    ->where('period', $period)
                                    ->value('cumul_individuel');

            if (!$dryRun) {
                $newValue = $this->cumulService->recalculateIndividualCumul($distributeur->id, $period);
                $this->info("  Cumul individuel: {$oldValue} → {$newValue}");
            } else {
                $this->info("  Cumul individuel actuel: {$oldValue}");
            }
        }
    }

    private function recalculateAll(string $period, bool $dryRun): void
    {
        $this->info("Recalcul global des cumuls individuels...");

        // Récupérer tous les distributeurs ayant des données pour cette période
        $distributors = LevelCurrent::where('period', $period)
                                   ->select('distributeur_id')
                                   ->distinct()
                                   ->pluck('distributeur_id');

        $progressBar = $this->output->createProgressBar($distributors->count());
        $progressBar->start();

        $corrections = 0;

        foreach ($distributors as $distributeurId) {
            $levelCurrent = LevelCurrent::where('distributeur_id', $distributeurId)
                                        ->where('period', $period)
                                        ->first();

            if ($levelCurrent) {
                // Calculer ce que devrait être le cumul individuel
                $childrenTotal = LevelCurrent::where('id_distrib_parent', $distributeurId)
                                             ->where('period', $period)
                                             ->sum('cumul_total');

                $expectedIndividual = $levelCurrent->cumul_total - $childrenTotal;

                if (abs($levelCurrent->cumul_individuel - $expectedIndividual) > 0.01) {
                    $corrections++;

                    if (!$dryRun) {
                        $levelCurrent->update(['cumul_individuel' => $expectedIndividual]);
                    }

                    $this->line("\nCorrection: Distributeur #{$distributeurId} - {$levelCurrent->cumul_individuel} → {$expectedIndividual}");
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Corrections nécessaires: {$corrections}");

        if ($dryRun && $corrections > 0) {
            $this->warn("Exécutez sans --dry-run pour appliquer les corrections");
        }
    }
}
