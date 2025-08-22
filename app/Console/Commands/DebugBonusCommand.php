<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LevelCurrent;
use App\Models\Distributeur;

class DebugBonusCommand extends Command
{
    protected $signature = 'bonus:debug {matricule} {period}';
    protected $description = 'Debug détaillé du calcul de bonus';

    public function handle()
    {
        $matricule = $this->argument('matricule');
        $period = $this->argument('period');

        $this->info("=== DEBUG CALCUL BONUS ===");
        $this->info("Distributeur: {$matricule} - Période: {$period}\n");

        // Récupérer le distributeur
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

        $this->info("Distributeur: {$distributeur->nom_distributeur} {$distributeur->pnom_distributeur}");
        $this->info("Grade: {$levelCurrent->etoiles} étoiles");
        $this->info("New Cumul: {$levelCurrent->new_cumul}");
        $this->info("Cumul Total: {$levelCurrent->cumul_total}\n");

        // Calcul du bonus direct
        $tauxDirect = $this->tauxDirectCalculator($levelCurrent->etoiles);
        $bonusDirect = $levelCurrent->new_cumul * $tauxDirect;
        $this->info("=== BONUS DIRECT ===");
        $this->info("Taux: " . ($tauxDirect * 100) . "%");
        $this->info("Calcul: {$levelCurrent->new_cumul} × {$tauxDirect} = {$bonusDirect}\n");

        // Calcul du bonus indirect avec debug
        $this->info("=== BONUS INDIRECT (Debug détaillé) ===");
        $result = $this->debugBonusIndirect(
            $levelCurrent->distributeur_id,
            $levelCurrent->etoiles,
            $period,
            0,
            ""
        );

        $this->info("\nTOTAL BONUS INDIRECT: {$result['total']}");

        // Résumé
        $bonusTotal = $bonusDirect + $result['total'];
        $this->info("\n=== RÉSUMÉ ===");
        $this->table(
            ['Type', 'Montant'],
            [
                ['Bonus Direct', number_format($bonusDirect, 2) . ' €'],
                ['Bonus Indirect', number_format($result['total'], 2) . ' €'],
                ['TOTAL', number_format($bonusTotal, 2) . ' €']
            ]
        );

        return 0;
    }

    private function debugBonusIndirect($distributeurId, $gradeOrigine, $period, $depth, $prefix)
    {
        if ($depth >= 20) {
            return ['total' => 0];
        }

        $totalBonus = 0;
        $indent = str_repeat("  ", $depth);

        // Récupérer les enfants directs uniquement
        $enfants = LevelCurrent::where('id_distrib_parent', $distributeurId)
            ->where('period', $period)
            ->with('distributeur')
            ->get();

        // Si on est au niveau 0 (enfants directs du distributeur principal)
        if ($depth == 0) {
            foreach ($enfants as $enfant) {
                $distrib = $enfant->distributeur;
                $this->line("{$indent}├─ [{$distrib->distributeur_id}] {$distrib->nom_distributeur} - Grade {$enfant->etoiles} - Cumul: {$enfant->cumul_total}");

                // Vérifier le blocage
                if ($enfant->etoiles >= $gradeOrigine) {
                    $this->warn("{$indent}   ⛔ BLOQUÉ (Grade {$enfant->etoiles} >= Grade origine {$gradeOrigine})");
                    continue;
                }

                // Calculer le bonus sur le cumul total (qui inclut déjà toute la descendance)
                $diff = $gradeOrigine - $enfant->etoiles;
                $taux = $this->etoilesChecker($gradeOrigine, $diff);

                // Vérifier s'il y a des blocages dans la descendance
                $blocages = $this->findBlocagesDebug($enfant->distributeur_id, $gradeOrigine, $period, $depth + 1);
                $cumulBloque = array_sum(array_column($blocages, 'cumul'));
                $cumulAjuste = $enfant->cumul_total - $cumulBloque;

                if ($taux > 0) {
                    $bonus = $cumulAjuste * $taux;
                    $totalBonus += $bonus;
                    $this->info("{$indent}   ✓ Diff: {$diff}, Taux: " . ($taux * 100) . "%");
                    $this->info("{$indent}     Cumul total: {$enfant->cumul_total}");
                    if ($cumulBloque > 0) {
                        $this->warn("{$indent}     Cumul bloqué: {$cumulBloque}");
                        $this->info("{$indent}     Cumul ajusté: {$cumulAjuste}");
                    }
                    $this->info("{$indent}     Bonus: {$cumulAjuste} × {$taux} = {$bonus}");
                } else {
                    $this->line("{$indent}   ✗ Diff: {$diff}, Taux: 0%");
                }

                // Afficher les blocages trouvés
                if (!empty($blocages)) {
                    $this->warn("{$indent}   Branches bloquées dans la descendance:");
                    foreach ($blocages as $blocage) {
                        $this->warn("{$indent}     └─ {$blocage['matricule']} (Grade {$blocage['grade']}) - Cumul bloqué: {$blocage['cumul']}");
                    }
                }
            }
        }

        return ['total' => $totalBonus];
    }

    private function findBlocagesDebug($distributeurId, $gradeOrigine, $period, $depth)
    {
        if ($depth >= 20) {
            return [];
        }

        $blocages = [];
        $indent = str_repeat("  ", $depth);

        $enfants = LevelCurrent::where('id_distrib_parent', $distributeurId)
            ->where('period', $period)
            ->with('distributeur')
            ->get();

        foreach ($enfants as $enfant) {
            if ($enfant->etoiles >= $gradeOrigine) {
                // Cet enfant bloque
                $blocages[] = [
                    'distributeur_id' => $enfant->distributeur_id,
                    'matricule' => $enfant->distributeur->distributeur_id,
                    'grade' => $enfant->etoiles,
                    'cumul' => $enfant->cumul_total
                ];
            } else {
                // Chercher les blocages plus bas
                $sousBlocages = $this->findBlocagesDebug($enfant->distributeur_id, $gradeOrigine, $period, $depth + 1);
                $blocages = array_merge($blocages, $sousBlocages);
            }
        }

        return $blocages;
    }

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
        $taux = 0;
        switch($etoiles) {
            case 2:
                if($diff == 1) $taux = 0.06;
                break;
            case 3:
                if($diff == 1) $taux = 0.16;
                if($diff == 2) $taux = 0.22;
                break;
            case 4:
                if($diff == 1) $taux = 0.04;
                if($diff == 2) $taux = 0.20;
                if($diff == 3) $taux = 0.26;
                break;
            case 5:
                if($diff == 1) $taux = 0.04;
                if($diff == 2) $taux = 0.08;
                if($diff == 3) $taux = 0.24;
                if($diff == 4) $taux = 0.30;
                break;
            case 6:
                if($diff == 1) $taux = 0.04;
                if($diff == 2) $taux = 0.08;
                if($diff == 3) $taux = 0.12;
                if($diff == 4) $taux = 0.28;
                if($diff == 5) $taux = 0.34;
                break;
            case 7:
                if($diff == 1) $taux = 0.06;
                if($diff == 2) $taux = 0.10;
                if($diff == 3) $taux = 0.14;
                if($diff == 4) $taux = 0.18;
                if($diff == 5) $taux = 0.34;
                if($diff == 6) $taux = 0.40;
                break;
            case 8:
                if($diff == 1) $taux = 0.03;
                if($diff == 2) $taux = 0.09;
                if($diff == 3) $taux = 0.13;
                if($diff == 4) $taux = 0.17;
                if($diff == 5) $taux = 0.21;
                if($diff == 6) $taux = 0.37;
                if($diff == 7) $taux = 0.43;
                break;
            case 9:
                if($diff == 1) $taux = 0.02;
                if($diff == 2) $taux = 0.05;
                if($diff == 3) $taux = 0.11;
                if($diff == 4) $taux = 0.15;
                if($diff == 5) $taux = 0.19;
                if($diff == 6) $taux = 0.23;
                if($diff == 7) $taux = 0.39;
                if($diff == 8) $taux = 0.45;
                break;
        }
        return $taux;
    }
}
