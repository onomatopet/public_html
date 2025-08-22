<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use App\Models\Achat;
use App\Services\BonusCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DebugBonusZeroCommand extends Command
{
    protected $signature = 'bonus:debug-zero {distributeur_id} {period}';
    protected $description = 'Debug pourquoi les bonus sont à zéro';

    private BonusCalculationService $bonusService;

    public function __construct(BonusCalculationService $bonusService)
    {
        parent::__construct();
        $this->bonusService = $bonusService;
    }

    public function handle()
    {
        $distributeurId = $this->argument('distributeur_id');
        $period = $this->argument('period');

        $this->info("=== DEBUG BONUS ZÉRO ===");
        $this->info("Distributeur ID: {$distributeurId} - Période: {$period}\n");

        // 1. Vérifier le distributeur
        $distributeur = Distributeur::find($distributeurId);
        if (!$distributeur) {
            $this->error("Distributeur avec ID {$distributeurId} non trouvé!");
            return 1;
        }
        $this->info("Distributeur trouvé: {$distributeur->nom_distributeur} (Matricule: {$distributeur->distributeur_id})");

        // 2. Vérifier LevelCurrent
        $levelCurrent = LevelCurrent::where('distributeur_id', $distributeurId)
            ->where('period', $period)
            ->first();

        if (!$levelCurrent) {
            $this->error("Pas de LevelCurrent pour ce distributeur sur cette période!");
            return 1;
        }

        $this->info("\n--- DONNÉES LEVEL_CURRENT ---");
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['ID', $levelCurrent->id],
                ['Distributeur ID', $levelCurrent->distributeur_id],
                ['Étoiles', $levelCurrent->etoiles],
                ['New Cumul', $levelCurrent->new_cumul],
                ['Cumul Total', $levelCurrent->cumul_total],
                ['Cumul Individuel', $levelCurrent->cumul_individuel],
                ['Cumul Collectif', $levelCurrent->cumul_collectif],
                ['Parent ID', $levelCurrent->id_distrib_parent],
            ]
        );

        // 3. Vérifier les achats
        $achats = Achat::where('distributeur_id', $distributeurId)
            ->where('period', $period)
            ->get();

        $this->info("\n--- ACHATS ---");
        $this->info("Nombre d'achats: " . $achats->count());
        if ($achats->isNotEmpty()) {
            $totalPoints = $achats->sum(function($achat) {
                return $achat->points_unitaire_achat * $achat->qt;
            });
            $this->info("Total points: {$totalPoints}");
        }

        // 4. Test d'éligibilité
        $this->info("\n--- TEST ÉLIGIBILITÉ ---");
        $eligible = $this->bonusService->isBonusEligible($levelCurrent->etoiles, $levelCurrent->new_cumul);
        $this->info("Éligible: " . ($eligible[0] ? 'OUI' : 'NON'));
        $this->info("Quota requis: {$eligible[1]}");
        $this->info("New Cumul actuel: {$levelCurrent->new_cumul}");

        // 5. Calcul du bonus direct
        $this->info("\n--- CALCUL BONUS DIRECT ---");
        $tauxDirect = $this->bonusService->tauxDirectCalculator($levelCurrent->etoiles);
        $bonusDirect = $levelCurrent->new_cumul * $tauxDirect;
        $this->info("Taux direct pour {$levelCurrent->etoiles} étoiles: " . ($tauxDirect * 100) . "%");
        $this->info("Calcul: {$levelCurrent->new_cumul} × {$tauxDirect} = {$bonusDirect}");

        // 6. Vérifier les enfants pour le bonus indirect
        $this->info("\n--- ENFANTS DIRECTS ---");
        $enfants = LevelCurrent::where('id_distrib_parent', $distributeurId)
            ->where('period', $period)
            ->get();

        if ($enfants->isEmpty()) {
            $this->warn("Aucun enfant trouvé!");
        } else {
            foreach ($enfants as $enfant) {
                $distEnfant = Distributeur::find($enfant->distributeur_id);
                $this->info("- [{$distEnfant->distributeur_id}] Grade {$enfant->etoiles}, Cumul Total: {$enfant->cumul_total}");
            }
        }

        // 7. Test complet avec le service
        $this->info("\n--- TEST AVEC LE SERVICE ---");
        $result = $this->bonusService->calculateBonusForDistributor($distributeur->distributeur_id, $period);

        if ($result['success']) {
            $data = $result['data'];
            $this->table(
                ['Élément', 'Valeur'],
                [
                    ['Bonus Direct', $data['bonus_direct']],
                    ['Bonus Indirect', $data['bonus_indirect']],
                    ['Bonus Total', $data['bonus']],
                    ['Bonus Final', $data['bonusFinal']],
                    ['Épargne', $data['epargne']],
                ]
            );
        } else {
            $this->error("Erreur: " . $result['message']);
        }

        // 8. Vérifier si c'est un problème de table
        $this->info("\n--- VÉRIFICATION STRUCTURE TABLE ---");
        $columns = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM level_currents");
        $importantColumns = ['new_cumul', 'cumul_total', 'cumul_individuel', 'cumul_collectif'];

        foreach ($columns as $column) {
            if (in_array($column->Field, $importantColumns)) {
                $this->info("Colonne {$column->Field}: Type = {$column->Type}");
            }
        }

        return 0;
    }
}
