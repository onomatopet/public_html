<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BonusCalculationService;
use App\Models\LevelCurrent;
use App\Models\Bonus;
use Illuminate\Support\Facades\DB;

class CalculateBonusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bonus:calculate
                            {period : La période au format Y-m (ex: 2025-07)}
                            {--dry-run : Simule le calcul sans enregistrer en base}
                            {--limit=0 : Limiter le nombre de distributeurs à traiter (0 = tous)}
                            {--matricule= : Calculer pour un seul distributeur}
                            {--show-details : Afficher les détails de chaque calcul}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcule les bonus pour une période donnée';

    private BonusCalculationService $bonusService;

    public function __construct(BonusCalculationService $bonusService)
    {
        parent::__construct();
        $this->bonusService = $bonusService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $period = $this->argument('period');
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $matricule = $this->option('matricule');
        $showDetails = $this->option('show-details');

        $this->info("=== CALCUL DES BONUS ===");
        $this->info("Période : {$period}");

        if ($isDryRun) {
            $this->warn("🔍 MODE DRY-RUN : Aucune modification ne sera enregistrée en base de données");
        }

        // Si un matricule spécifique est demandé
        if ($matricule) {
            return $this->calculateForSingleDistributor($matricule, $period, $isDryRun, $showDetails);
        }

        // Sinon, calculer pour tous les distributeurs éligibles
        return $this->calculateForAllDistributors($period, $isDryRun, $limit, $showDetails);
    }

    /**
     * Calcule le bonus pour un seul distributeur
     */
    private function calculateForSingleDistributor($matricule, $period, $isDryRun, $showDetails)
    {
        $this->info("Calcul pour le distributeur : {$matricule}");

        if ($isDryRun) {
            // En mode dry-run, on utilise une transaction qu'on annule à la fin
            DB::beginTransaction();
        }

        try {
            $result = $this->bonusService->calculateBonusForDistributor($matricule, $period);

            if (!$result['success']) {
                $this->error($result['message']);
                if ($isDryRun) {
                    DB::rollBack();
                }
                return 1;
            }

            $data = $result['data'];

            // Afficher le résultat
            $this->displayBonusResult($data, $showDetails);

            if (!$isDryRun && $data['bonusFinal'] > 0) {
                // Sauvegarder en base
                $bonus = Bonus::create([
                    'num' => $data['numero'],
                    'distributeur_id' => $data['distributeur_id'],
                    'period' => $period,
                    'bonus_direct' => $data['bonus_direct'],
                    'bonus_indirect' => $data['bonus_indirect'],
                    'bonus' => $data['bonusFinal'],
                    'epargne' => $data['epargne']
                ]);

                $this->info("✅ Bonus enregistré avec succès (ID: {$bonus->id})");
            } elseif ($isDryRun) {
                $this->warn("🔍 Mode dry-run : Le bonus n'a PAS été enregistré");
                DB::rollBack();
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Erreur : " . $e->getMessage());
            if ($isDryRun) {
                DB::rollBack();
            }
            return 1;
        }
    }

    /**
     * Calcule les bonus pour tous les distributeurs
     */
    private function calculateForAllDistributors($period, $isDryRun, $limit, $showDetails)
    {
        if ($isDryRun) {
            DB::beginTransaction();
        }

        try {
            // Récupérer les distributeurs éligibles
            $query = LevelCurrent::where('period', $period)
                ->with('distributeur')
                ->whereHas('distributeur.achats', function($q) use ($period) {
                    $q->where('period', $period);
                })
                ->whereNotExists(function($q) use ($period) {
                    $q->select(DB::raw(1))
                        ->from('bonuses')
                        ->whereColumn('bonuses.distributeur_id', 'level_currents.distributeur_id')
                        ->where('bonuses.period', $period);
                });

            if ($limit > 0) {
                $query->limit($limit);
            }

            $distributors = $query->get();

            $this->info("Distributeurs éligibles trouvés : " . $distributors->count());

            if ($distributors->isEmpty()) {
                $this->warn("Aucun distributeur éligible trouvé");
                if ($isDryRun) {
                    DB::rollBack();
                }
                return 0;
            }

            $bar = $this->output->createProgressBar($distributors->count());
            $bar->start();

            $results = [
                'calculated' => 0,
                'saved' => 0,
                'total_amount' => 0,
                'errors' => 0
            ];

            foreach ($distributors as $levelCurrent) {
                try {
                    $matricule = $levelCurrent->distributeur->distributeur_id;
                    $result = $this->bonusService->calculateBonusForDistributor($matricule, $period);

                    if ($result['success'] && isset($result['data'])) {
                        $data = $result['data'];
                        $results['calculated']++;

                        if ($showDetails) {
                            $this->line("\n");
                            $this->displayBonusResult($data, true);
                        }

                        if (!$isDryRun && $data['bonusFinal'] > 0) {
                            Bonus::create([
                                'num' => $data['numero'],
                                'distributeur_id' => $data['distributeur_id'],
                                'period' => $period,
                                'bonus_direct' => $data['bonus_direct'],
                                'bonus_indirect' => $data['bonus_indirect'],
                                'bonus' => $data['bonusFinal'],
                                'epargne' => $data['epargne']
                            ]);

                            $results['saved']++;
                            $results['total_amount'] += $data['bonusFinal'];
                        } elseif ($data['bonusFinal'] > 0) {
                            // En dry-run, on compte quand même
                            $results['saved']++;
                            $results['total_amount'] += $data['bonusFinal'];
                        }
                    }
                } catch (\Exception $e) {
                    $results['errors']++;
                    if ($showDetails) {
                        $this->error("\nErreur pour {$matricule}: " . $e->getMessage());
                    }
                }

                $bar->advance();
            }

            $bar->finish();
            $this->line("\n");

            // Afficher le résumé
            $this->displaySummary($results, $isDryRun);

            if ($isDryRun) {
                DB::rollBack();
                $this->warn("\n🔍 Mode dry-run : AUCUNE modification n'a été enregistrée en base de données");
            }

            return 0;

        } catch (\Exception $e) {
            if ($isDryRun) {
                DB::rollBack();
            }
            $this->error("Erreur globale : " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Affiche le résultat d'un calcul de bonus
     */
    private function displayBonusResult($data, $showDetails = false)
    {
        $this->info("\n--- Distributeur: {$data['matricule']} - {$data['nom_distributeur']} {$data['pnom_distributeur']} ---");

        $this->table(
            ['Élément', 'Valeur'],
            [
                ['Grade', $data['etoiles'] . ' étoiles'],
                ['PV Période', number_format($data['new_cumul'], 2)],
                ['Bonus Direct', number_format($data['bonus_direct'], 2) . ' €'],
                ['Bonus Indirect', number_format($data['bonus_indirect'], 2) . ' €'],
                ['Bonus Total', number_format($data['bonus'], 2) . ' €'],
                ['Bonus Final', number_format($data['bonusFinal'], 2) . ' €'],
                ['Épargne', number_format($data['epargne'], 2) . ' €'],
                ['Numéro', $data['numero']],
            ]
        );
    }

    /**
     * Affiche le résumé des calculs
     */
    private function displaySummary($results, $isDryRun)
    {
        $this->info("\n=== RÉSUMÉ ===");

        $summaryData = [
            ['Bonus calculés', $results['calculated']],
            ['Bonus à enregistrer', $results['saved']],
            ['Montant total', number_format($results['total_amount'], 2) . ' €'],
            ['Erreurs', $results['errors']],
        ];

        if ($isDryRun) {
            $summaryData[] = ['Mode', '🔍 DRY-RUN (simulation)'];
        } else {
            $summaryData[] = ['Mode', '✅ PRODUCTION (enregistré)'];
        }

        $this->table(['Métrique', 'Valeur'], $summaryData);
    }
}
