<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BonusCalculationService;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use App\Models\Achat;

class CompareBonusCalculations extends Command
{
    protected $signature = 'bonus:compare {matricule} {period}';
    protected $description = 'Compare les calculs de bonus entre l\'ancien et le nouveau système';

    private BonusCalculationService $bonusService;

    public function __construct(BonusCalculationService $bonusService)
    {
        parent::__construct();
        $this->bonusService = $bonusService;
    }

    public function handle()
    {
        $matricule = $this->argument('matricule');
        $period = $this->argument('period');

        $this->info("Comparaison des calculs de bonus pour {$matricule} - Période {$period}");
        $this->line('');

        // Récupérer les données
        $distributeur = Distributeur::where('distributeur_id', $matricule)->first();
        if (!$distributeur) {
            $this->error("Distributeur non trouvé");
            return 1;
        }

        $levelCurrent = LevelCurrent::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        if (!$levelCurrent) {
            $this->error("Pas de données pour cette période");
            return 1;
        }

        // 1. Calcul avec l'ancienne méthode (votre code)
        $this->info("=== CALCUL ANCIEN SYSTÈME ===");
        $oldResult = $this->calculateOldWay($levelCurrent, $period);
        $this->displayResult($oldResult);

        // 2. Calcul avec le nouveau service
        $this->info("\n=== CALCUL NOUVEAU SERVICE ===");
        $newResult = $this->bonusService->calculateBonusForDistributor($matricule, $period);
        if ($newResult['success']) {
            $this->displayResult($newResult['data']);
        } else {
            $this->error($newResult['message']);
        }

        // 3. Comparaison
        $this->info("\n=== COMPARAISON ===");
        if ($newResult['success']) {
            $this->compareResults($oldResult, $newResult['data']);
        }

        return 0;
    }

    private function calculateOldWay($levelCurrent, $period)
    {
        // Copie de votre logique originale
        $tauxDirect = $this->tauxDirectCalculator($levelCurrent->etoiles);
        $bonusDirect = $levelCurrent->new_cumul * $tauxDirect;

        // Calcul indirect avec votre logique
        $bonusIndirect = 0;
        $firstGenealogie = LevelCurrent::where('id_distrib_parent', $levelCurrent->distributeur_id)
            ->where('period', $period)
            ->get();

        foreach ($firstGenealogie as $value) {
            if ($levelCurrent->etoiles > $value->etoiles) {
                $diff = $levelCurrent->etoiles - $value->etoiles;
                $taux = $this->etoilesChecker($levelCurrent->etoiles, $diff);
                $bonusIndirect += $value->cumul_total * $taux;
            }
        }

        // Logique d'épargne
        $bonusTotal = $bonusDirect + $bonusIndirect;
        $decimal = $bonusTotal - floor($bonusTotal);

        if ($decimal > 0.5) {
            $bonusFinal = floor($bonusTotal);
            $epargne = $decimal;
        } else {
            if ($bonusTotal > 1) {
                $bonusFinal = $bonusTotal - 1;
                $epargne = 1;
            } else {
                $bonusFinal = $bonusTotal;
                $epargne = 0;
            }
        }

        return [
            'bonus_direct' => $bonusDirect,
            'bonus_indirect' => $bonusIndirect,
            'bonus' => $bonusTotal,
            'bonusFinal' => $bonusFinal,
            'epargne' => $epargne
        ];
    }

    private function displayResult($result)
    {
        $this->table(
            ['Élément', 'Valeur'],
            [
                ['Bonus Direct', number_format($result['bonus_direct'], 2) . ' €'],
                ['Bonus Indirect', number_format($result['bonus_indirect'], 2) . ' €'],
                ['Bonus Total', number_format($result['bonus'], 2) . ' €'],
                ['Bonus Final', number_format($result['bonusFinal'], 2) . ' €'],
                ['Épargne', number_format($result['epargne'], 2) . ' €'],
            ]
        );
    }

    private function compareResults($old, $new)
    {
        $differences = [];

        if (round($old['bonus_direct'], 2) != round($new['bonus_direct'], 2)) {
            $differences[] = ['Bonus Direct', round($old['bonus_direct'], 2), round($new['bonus_direct'], 2)];
        }
        if (round($old['bonus_indirect'], 2) != round($new['bonus_indirect'], 2)) {
            $differences[] = ['Bonus Indirect', round($old['bonus_indirect'], 2), round($new['bonus_indirect'], 2)];
        }
        if (round($old['bonusFinal'], 2) != round($new['bonusFinal'], 2)) {
            $differences[] = ['Bonus Final', round($old['bonusFinal'], 2), round($new['bonusFinal'], 2)];
        }

        if (empty($differences)) {
            $this->info("✅ Les calculs sont identiques !");
        } else {
            $this->warn("⚠️ Des différences ont été détectées :");
            $this->table(['Élément', 'Ancien', 'Nouveau'], $differences);
        }
    }

    // Vos méthodes originales pour la comparaison
    private function tauxDirectCalculator($etoiles)
    {
        switch($etoiles) {
            case 1: return 0;
            case 2: return 0.06;
            case 3: return 0.22;
            case 4: return 0.26;
            case 5: return 0.30;
            case 6: return 0.34;
            case 7: return 0.40;
            case 8: return 0.43;
            case 9: return 0.45;
            case 10: return 0.45;
            default: return 0;
        }
    }

    private function etoilesChecker($etoiles, $diff)
    {
        // Votre logique exacte ici
        $taux = 0;
        switch($etoiles) {
            case 2:
                if($diff == 1) $taux = 0.06;
                break;
            case 3:
                if($diff == 1) $taux = 0.16;
                if($diff == 2) $taux = 0.22;
                break;
            // ... etc
        }
        return $taux;
    }
}
