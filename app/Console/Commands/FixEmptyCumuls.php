<?php

namespace App\Console\Commands;

use App\Models\Achat;
use App\Models\LevelCurrent;
use App\Services\BatchAggregationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixEmptyCumuls extends Command
{
    protected $signature = 'mlm:fix-empty-cumuls
                            {period : La période à corriger (ex: 2024-11)}
                            {--dry-run : Mode simulation}';

    protected $description = 'Corrige les new_cumul et cumul_total vides pour une période';

    protected BatchAggregationService $batchService;

    public function __construct(BatchAggregationService $batchService)
    {
        parent::__construct();
        $this->batchService = $batchService;
    }

    public function handle()
    {
        $period = $this->argument('period');
        $dryRun = $this->option('dry-run');

        $this->info("Correction des cumuls vides pour la période: {$period}");
        if ($dryRun) {
            $this->warn("MODE SIMULATION - Aucune modification ne sera sauvegardée");
        }

        // 1. Vérifier le problème
        $emptyCount = LevelCurrent::where('period', $period)
            ->where(function($query) {
                $query->whereNull('new_cumul')
                    ->orWhere('new_cumul', 0)
                    ->orWhereNull('cumul_total')
                    ->orWhere('cumul_total', 0);
            })
            ->count();

        $this->info("Nombre d'enregistrements avec cumuls vides: {$emptyCount}");

        if ($emptyCount === 0) {
            $this->info("Aucun problème détecté!");
            return 0;
        }

        // 2. Déterminer la colonne de points à utiliser
        $pointColumn = Schema::hasColumn('achats', 'points_unitaire_achat')
            ? 'points_unitaire_achat * qt'
            : 'pointvaleur';

        $this->info("Utilisation de la colonne: {$pointColumn}");

        // 3. Récupérer les achats agrégés
        $achatsAgreges = Achat::where('period', $period)
            ->where('statut', 'validé')
            ->groupBy('distributeur_id')
            ->selectRaw("distributeur_id, SUM({$pointColumn}) as total_points")
            ->get()
            ->keyBy('distributeur_id');

        $this->info("Nombre de distributeurs avec achats: " . $achatsAgreges->count());

        // 4. Corriger les enregistrements
        $fixed = 0;
        $bar = $this->output->createProgressBar($emptyCount);
        $bar->start();

        DB::beginTransaction();
        try {
            foreach ($achatsAgreges as $distributeurId => $achat) {
                $points = (float) $achat->total_points;

                if ($points > 0) {
                    $level = LevelCurrent::where('period', $period)
                        ->where('distributeur_id', $distributeurId)
                        ->first();

                    if ($level && ($level->new_cumul == 0 || $level->cumul_total == 0)) {
                        if (!$dryRun) {
                            $level->new_cumul = $points;
                            $level->cumul_total = $points;
                            $level->save();
                        }

                        $fixed++;
                        $bar->advance();

                        $this->line(" Fixed: Distributeur #{$distributeurId} - Points: {$points}");
                    }
                }
            }

            $bar->finish();
            $this->newLine();

            if ($dryRun) {
                DB::rollback();
                $this->info("SIMULATION: {$fixed} enregistrements auraient été corrigés");
            } else {
                DB::commit();
                $this->success("{$fixed} enregistrements corrigés avec succès!");
            }

            // 5. Afficher un résumé
            $this->table(
                ['Métrique', 'Valeur'],
                [
                    ['Période', $period],
                    ['Enregistrements vides trouvés', $emptyCount],
                    ['Enregistrements corrigés', $fixed],
                    ['Mode', $dryRun ? 'SIMULATION' : 'RÉEL']
                ]
            );

        } catch (\Exception $e) {
            DB::rollback();
            $this->error("Erreur: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
