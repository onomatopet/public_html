<?php
// app/Services/BonusCalculationService.php

namespace App\Services;

use App\Models\Bonus;
use App\Models\BonusThreshold;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use App\Models\SystemPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class BonusCalculationService
{
    /**
     * Calcule les bonus pour tous les distributeurs d'une période
     */
    public function calculateBonusesForPeriod(string $period, array $options = []): array
    {
        $startTime = microtime(true);
        $dryRun = $options['dry_run'] ?? false;
        $onlyEligible = $options['only_eligible'] ?? true;

        Log::info("Début du calcul des bonus", [
            'period' => $period,
            'options' => $options
        ]);

        // Vérifier que la période est valide
        $systemPeriod = SystemPeriod::where('period', $period)->first();
        if (!$systemPeriod) {
            return [
                'success' => false,
                'message' => 'Période invalide'
            ];
        }

        DB::beginTransaction();
        try {
            // 1. Récupérer tous les distributeurs éligibles
            $eligibleDistributors = $this->getEligibleDistributors($period, $onlyEligible);

            // 2. Calculer les bonus pour chaque distributeur
            $bonusResults = $this->calculateIndividualBonuses($eligibleDistributors, $period);

            // 3. Sauvegarder les bonus (si pas dry run)
            if (!$dryRun) {
                $savedBonuses = $this->saveBonuses($bonusResults, $period);
            } else {
                $savedBonuses = ['count' => count($bonusResults), 'total' => collect($bonusResults)->sum('total_bonus')];
            }

            if ($dryRun) {
                DB::rollBack();
                $message = "Simulation terminée - Aucun bonus enregistré";
            } else {
                DB::commit();
                $message = "Calcul des bonus terminé avec succès";
            }

            $duration = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'message' => $message,
                'duration' => $duration,
                'stats' => [
                    'eligible_distributors' => $eligibleDistributors->count(),
                    'bonuses_calculated' => count($bonusResults),
                    'total_amount' => $savedBonuses['total'],
                    'details' => $dryRun ? $bonusResults : []
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors du calcul des bonus", [
                'period' => $period,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les distributeurs éligibles aux bonus
     */
    protected function getEligibleDistributors(string $period, bool $onlyEligible = true): Collection
    {
        $query = LevelCurrent::where('period', $period)
                            ->with(['distributeur', 'distributeur.parent']);

        if ($onlyEligible) {
            // Joindre avec la table des seuils pour filtrer
            $query->join('bonus_thresholds', 'level_currents.etoiles', '=', 'bonus_thresholds.grade')
                  ->where('bonus_thresholds.is_active', true)
                  ->whereRaw('level_currents.new_cumul >= bonus_thresholds.minimum_pv')
                  ->select('level_currents.*', 'bonus_thresholds.minimum_pv');
        }

        return $query->get();
    }

    /**
     * Calcule les bonus individuels pour chaque distributeur
     */
    protected function calculateIndividualBonuses(Collection $distributors, string $period): array
    {
        $bonusResults = [];

        foreach ($distributors as $levelCurrent) {
            $distributeur = $levelCurrent->distributeur;

            // Vérifier le seuil minimum de PV
            $minimumPv = BonusThreshold::getMinimumPvForGrade($levelCurrent->etoiles);
            if ($levelCurrent->new_cumul < $minimumPv) {
                Log::debug("Distributeur {$distributeur->distributeur_id} non éligible - PV insuffisants", [
                    'pv_actuel' => $levelCurrent->new_cumul,
                    'pv_minimum' => $minimumPv
                ]);
                continue;
            }

            // Calculer les différents types de bonus
            $bonusDetail = [
                'distributeur_id' => $distributeur->id,
                'matricule' => $distributeur->distributeur_id,
                'nom' => $distributeur->nom_distributeur . ' ' . $distributeur->pnom_distributeur,
                'grade' => $levelCurrent->etoiles,
                'pv_periode' => $levelCurrent->new_cumul,
                'bonus_direct' => 0,
                'bonus_indirect' => 0,
                'bonus_leadership' => 0,
                'total_bonus' => 0,
                'details' => []
            ];

            // 1. Calcul du bonus direct (sur ses propres ventes)
            $bonusDetail['bonus_direct'] = $this->calculateDirectBonus($levelCurrent);

            // 2. Calcul du bonus indirect (sur les ventes de la descendance)
            $indirectResult = $this->calculateIndirectBonus($distributeur->id, $levelCurrent->etoiles, $period);
            $bonusDetail['bonus_indirect'] = $indirectResult['total'];
            $bonusDetail['details']['indirect'] = $indirectResult['details'];

            // 3. Calcul du bonus de leadership (pour grades 4+)
            if ($levelCurrent->etoiles >= 4) {
                $leadershipResult = $this->calculateLeadershipBonus($distributeur->id, $levelCurrent->etoiles, $period);
                $bonusDetail['bonus_leadership'] = $leadershipResult['total'];
                $bonusDetail['details']['leadership'] = $leadershipResult['details'];
            }

            // Total
            $bonusDetail['total_bonus'] = $bonusDetail['bonus_direct'] +
                                         $bonusDetail['bonus_indirect'] +
                                         $bonusDetail['bonus_leadership'];

            if ($bonusDetail['total_bonus'] > 0) {
                $bonusResults[] = $bonusDetail;
            }
        }

        return $bonusResults;
    }

    /**
     * Calcule le bonus direct sur les ventes personnelles
     */
    protected function calculateDirectBonus(LevelCurrent $levelCurrent): float
    {
        // Taux de bonus direct selon le grade
        $tauxDirect = [
            1 => 0.10,  // 10%
            2 => 0.12,  // 12%
            3 => 0.14,  // 14%
            4 => 0.16,  // 16%
            5 => 0.18,  // 18%
            6 => 0.20,  // 20%
            7 => 0.22,  // 22%
            8 => 0.24,  // 24%
            9 => 0.26,  // 26%
            10 => 0.28  // 28%
        ];

        $taux = $tauxDirect[$levelCurrent->etoiles] ?? 0.10;

        return $levelCurrent->new_cumul * $taux;
    }

    /**
     * Calcule le bonus indirect récursivement
     */
    protected function calculateIndirectBonus(int $distributeurId, int $gradeParent, string $period, int $depth = 0): array
    {
        $maxDepth = 20; // Protection contre récursion infinie
        if ($depth >= $maxDepth) {
            return ['total' => 0, 'details' => []];
        }

        $totalBonus = 0;
        $details = [];

        // Récupérer les filleuls directs
        $filleuls = LevelCurrent::where('period', $period)
                               ->where('id_distrib_parent', $distributeurId)
                               ->with('distributeur')
                               ->get();

        foreach ($filleuls as $filleul) {
            // Calculer la différence de grade
            $differenceGrade = $gradeParent - $filleul->etoiles;

            if ($differenceGrade > 0) {
                // Calculer le taux selon la différence
                $taux = $this->getTauxByDifference($differenceGrade);

                // Bonus sur le cumul_total du filleul (inclut ses ventes + celles de sa descendance)
                $bonusFilleul = $filleul->cumul_total * $taux;

                $totalBonus += $bonusFilleul;

                $details[] = [
                    'filleul_matricule' => $filleul->distributeur->distributeur_id,
                    'filleul_nom' => $filleul->distributeur->nom_distributeur . ' ' . $filleul->distributeur->pnom_distributeur,
                    'filleul_grade' => $filleul->etoiles,
                    'difference_grade' => $differenceGrade,
                    'taux' => $taux,
                    'cumul_total' => $filleul->cumul_total,
                    'bonus' => $bonusFilleul
                ];

                // Récursion pour les sous-filleuls
                $sousFilleulsResult = $this->calculateIndirectBonus(
                    $filleul->distributeur_id,
                    $gradeParent,
                    $period,
                    $depth + 1
                );

                $totalBonus += $sousFilleulsResult['total'];
                if (!empty($sousFilleulsResult['details'])) {
                    $details = array_merge($details, $sousFilleulsResult['details']);
                }
            }
        }

        return ['total' => $totalBonus, 'details' => $details];
    }

    /**
     * Calcule le bonus de leadership pour les grades élevés
     */
    protected function calculateLeadershipBonus(int $distributeurId, int $grade, string $period): array
    {
        // Le bonus de leadership est calculé sur le volume total de l'équipe
        // mais uniquement pour les branches où il y a au moins un manager (grade 4+)

        $totalBonus = 0;
        $details = [];

        // Taux de leadership selon le grade
        $tauxLeadership = [
            4 => 0.02,   // 2%
            5 => 0.025,  // 2.5%
            6 => 0.03,   // 3%
            7 => 0.035,  // 3.5%
            8 => 0.04,   // 4%
            9 => 0.045,  // 4.5%
            10 => 0.05   // 5%
        ];

        $taux = $tauxLeadership[$grade] ?? 0;

        if ($taux > 0) {
            // Récupérer toutes les branches avec au moins un manager
            $branchesWithManagers = $this->getBranchesWithManagers($distributeurId, $period);

            foreach ($branchesWithManagers as $branche) {
                $bonusBranche = $branche['volume_total'] * $taux;
                $totalBonus += $bonusBranche;

                $details[] = [
                    'branche_manager' => $branche['manager_matricule'],
                    'branche_volume' => $branche['volume_total'],
                    'taux' => $taux,
                    'bonus' => $bonusBranche
                ];
            }
        }

        return ['total' => $totalBonus, 'details' => $details];
    }

    /**
     * Récupère les branches avec des managers
     */
    protected function getBranchesWithManagers(int $distributeurId, string $period): array
    {
        $branches = [];

        // Récupérer les filleuls directs qui sont managers (grade 4+)
        $managers = LevelCurrent::where('period', $period)
                              ->where('id_distrib_parent', $distributeurId)
                              ->where('etoiles', '>=', 4)
                              ->with('distributeur')
                              ->get();

        foreach ($managers as $manager) {
            // Calculer le volume total de la branche du manager
            $volumeBranche = $this->calculateBranchVolume($manager->distributeur_id, $period);

            $branches[] = [
                'manager_matricule' => $manager->distributeur->distributeur_id,
                'manager_nom' => $manager->distributeur->nom_distributeur . ' ' . $manager->distributeur->pnom_distributeur,
                'manager_grade' => $manager->etoiles,
                'volume_total' => $volumeBranche
            ];
        }

        return $branches;
    }

    /**
     * Calcule le volume total d'une branche
     */
    protected function calculateBranchVolume(int $rootDistributeurId, string $period): float
    {
        // Le cumul_collectif contient déjà le volume total de la branche
        $levelCurrent = LevelCurrent::where('distributeur_id', $rootDistributeurId)
                                  ->where('period', $period)
                                  ->first();

        return $levelCurrent ? $levelCurrent->cumul_collectif : 0;
    }

    /**
     * Retourne le taux selon la différence de grade
     */
    protected function getTauxByDifference(int $difference): float
    {
        $taux = [
            1 => 0.04,   // 4%
            2 => 0.08,   // 8%
            3 => 0.12,   // 12%
            4 => 0.16,   // 16%
            5 => 0.18    // 18%
        ];

        // Pour une différence > 5, on garde 18%
        return $taux[$difference] ?? 0.18;
    }

    /**
     * Sauvegarde les bonus calculés
     */
    protected function saveBonuses(array $bonusResults, string $period): array
    {
        $savedCount = 0;
        $totalAmount = 0;

        foreach ($bonusResults as $bonusData) {
            // Générer le numéro de bonus
            $numBonus = $this->generateBonusNumber($period);

            $bonus = Bonus::create([
                'num' => $numBonus,
                'distributeur_id' => $bonusData['distributeur_id'],
                'period' => $period,
                'montant_direct' => $bonusData['bonus_direct'],
                'montant_indirect' => $bonusData['bonus_indirect'],
                'montant_leadership' => $bonusData['bonus_leadership'],
                'montant_total' => $bonusData['total_bonus'],
                'status' => 'calculé',
                'details' => json_encode($bonusData['details']),
                'calculated_at' => now()
            ]);

            $savedCount++;
            $totalAmount += $bonusData['total_bonus'];
        }

        Log::info("Bonus sauvegardés", [
            'period' => $period,
            'count' => $savedCount,
            'total' => $totalAmount
        ]);

        return [
            'count' => $savedCount,
            'total' => $totalAmount
        ];
    }

    /**
     * Génère un numéro de bonus unique
     */
    protected function generateBonusNumber(string $period): string
    {
        // Format: 7770MMYYXXX
        $prefix = '7770';

        // Extraire mois et année
        $parts = explode('-', $period);
        $year = substr($parts[0], -2);
        $month = $parts[1];

        $baseNumber = $prefix . $month . $year;

        // Trouver le prochain numéro séquentiel
        $lastBonus = Bonus::where('num', 'like', $baseNumber . '%')
                         ->orderBy('num', 'desc')
                         ->first();

        if ($lastBonus) {
            $lastSequence = intval(substr($lastBonus->num, -3));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $baseNumber . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Valide et approuve les bonus pour paiement
     */
    public function validateBonusesForPayment(string $period, int $userId): array
    {
        DB::beginTransaction();
        try {
            $bonuses = Bonus::where('period', $period)
                          ->where('status', 'calculé')
                          ->get();

            $validatedCount = 0;
            $totalValidated = 0;

            foreach ($bonuses as $bonus) {
                $bonus->update([
                    'status' => 'validé',
                    'validated_by' => $userId,
                    'validated_at' => now()
                ]);

                $validatedCount++;
                $totalValidated += $bonus->montant_total;
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "Bonus validés avec succès",
                'count' => $validatedCount,
                'total' => $totalValidated
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }
}
