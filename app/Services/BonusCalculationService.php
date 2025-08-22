<?php

namespace App\Services;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Bonus;
use App\Models\Achat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BonusCalculationService
{
    /**
     * Taux de bonus direct selon votre système actuel
     * Correspond exactement à tauxDirectCalculator()
     */
    protected const TAUX_DIRECT = [
        1 => 0,        // 0%
        2 => 0.06,     // 6%
        3 => 0.22,     // 22%
        4 => 0.26,     // 26%
        5 => 0.30,     // 30%
        6 => 0.34,     // 34%
        7 => 0.40,     // 40%
        8 => 0.43,     // 43%
        9 => 0.45,     // 45%
        10 => 0.45     // 45%
    ];

    /**
     * Seuils d'éligibilité selon votre système actuel
     * Correspond exactement à isBonusEligible()
     */
    protected const SEUILS_ELIGIBILITE = [
        1 => ['eligible' => false, 'quota' => 0],
        2 => ['eligible' => true, 'quota' => 0],
        3 => ['eligible' => 'conditionnel', 'quota' => 10],
        4 => ['eligible' => 'conditionnel', 'quota' => 15],
        5 => ['eligible' => 'conditionnel', 'quota' => 30],
        6 => ['eligible' => 'conditionnel', 'quota' => 50],
        7 => ['eligible' => 'conditionnel', 'quota' => 100],
        8 => ['eligible' => 'conditionnel', 'quota' => 150],
        9 => ['eligible' => 'conditionnel', 'quota' => 180],
        10 => ['eligible' => 'conditionnel', 'quota' => 180]
    ];

    /**
     * Matrice des taux indirects selon votre système actuel
     * Correspond exactement à etoilesChecker()
     */
    protected const MATRICE_TAUX_INDIRECT = [
        2 => [
            0 => 0,
            1 => 0.06
        ],
        3 => [
            0 => 0,
            1 => 0.16,
            2 => 0.22
        ],
        4 => [
            0 => 0,
            1 => 0.04,
            2 => 0.20,
            3 => 0.26
        ],
        5 => [
            0 => 0,
            1 => 0.04,
            2 => 0.08,
            3 => 0.24,
            4 => 0.30
        ],
        6 => [
            0 => 0,
            1 => 0.04,
            2 => 0.08,
            3 => 0.12,
            4 => 0.28,
            5 => 0.34
        ],
        7 => [
            0 => 0,
            1 => 0.06,
            2 => 0.10,
            3 => 0.14,
            4 => 0.18,
            5 => 0.34,
            6 => 0.40
        ],
        8 => [
            0 => 0,
            1 => 0.03,
            2 => 0.09,
            3 => 0.13,
            4 => 0.17,
            5 => 0.21,
            6 => 0.37,
            7 => 0.43
        ],
        9 => [
            0 => 0,
            1 => 0.02,
            2 => 0.05,
            3 => 0.11,
            4 => 0.15,
            5 => 0.19,
            6 => 0.23,
            7 => 0.39,
            8 => 0.45
        ]
    ];

    /**
     * Calcule les bonus pour tous les distributeurs d'une période
     */
    public function calculateBonusForPeriod(string $period)
    {
        DB::beginTransaction();

        try {
            // 1. Récupérer tous les distributeurs éligibles
            $distributors = $this->getEligibleDistributors($period);

            Log::info("Calcul des bonus pour {$distributors->count()} distributeurs éligibles", [
                'period' => $period
            ]);

            // 2. Calculer les bonus individuels
            $bonusResults = $this->calculateIndividualBonuses($distributors, $period);

            // 3. Sauvegarder les bonus
            $savedStats = $this->saveBonuses($bonusResults, $period);

            DB::commit();

            return [
                'success' => true,
                'message' => "Bonus calculés avec succès",
                'stats' => [
                    'total_distributors' => $distributors->count(),
                    'bonus_calculated' => count($bonusResults),
                    'total_amount' => $savedStats['total']
                ],
                'details' => $bonusResults
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
     * Utilise exactement votre logique d'éligibilité
     */
    protected function getEligibleDistributors(string $period): Collection
    {
        return LevelCurrent::where('period', $period)
            ->with(['distributeur'])
            ->get()
            ->filter(function ($levelCurrent) use ($period) {
                // Vérifier l'éligibilité selon votre logique
                $eligible = $this->isBonusEligible($levelCurrent->etoiles, $levelCurrent->new_cumul);

                if (!$eligible[0]) {
                    return false;
                }

                // Vérifier qu'il a fait des achats
                $hasAchats = Achat::where('distributeur_id', $levelCurrent->distributeur_id)
                    ->where('period', $period)
                    ->exists();

                if (!$hasAchats) {
                    Log::debug("Distributeur {$levelCurrent->distributeur_id} n'a pas d'achats pour {$period}");
                    return false;
                }

                // Vérifier qu'il n'a pas déjà de bonus
                $existingBonus = Bonus::where('distributeur_id', $levelCurrent->distributeur_id)
                    ->where('period', $period)
                    ->exists();

                if ($existingBonus) {
                    Log::debug("Distributeur {$levelCurrent->distributeur_id} a déjà un bonus pour {$period}");
                    return false;
                }

                return true;
            });
    }

    /**
     * Calcule les bonus individuels pour chaque distributeur
     * Utilise exactement votre logique de calcul
     */
    protected function calculateIndividualBonuses(Collection $distributors, string $period): array
    {
        $bonusResults = [];

        foreach ($distributors as $levelCurrent) {
            $distributeur = $levelCurrent->distributeur;

            // 1. Calculer le bonus direct
            $tauxDirect = $this->tauxDirectCalculator($levelCurrent->etoiles);
            $bonusDirect = $levelCurrent->new_cumul * $tauxDirect;

            // 2. Calculer le bonus indirect (toute la descendance avec blocage)
            $bonusIndirectResult = $this->calculateBonusIndirect($levelCurrent, $period);
            $bonusIndirect = $bonusIndirectResult['total'];

            // 3. Calculer le total et appliquer la logique d'épargne
            $bonusTotal = $bonusDirect + $bonusIndirect;
            $decimal = $bonusTotal - floor($bonusTotal);

            // Logique d'épargne exacte de votre système
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

            // Générer le numéro de bonus
            $numero = $this->generateBonusNumber();

            $bonusResults[] = [
                'distributeur_id' => $levelCurrent->distributeur_id,
                'matricule' => $distributeur->distributeur_id,
                'nom_distributeur' => $distributeur->nom_distributeur,
                'pnom_distributeur' => $distributeur->pnom_distributeur,
                'period' => $period,
                'numero' => $numero,
                'etoiles' => $levelCurrent->etoiles,
                'new_cumul' => $levelCurrent->new_cumul,
                'bonus_direct' => $bonusDirect,
                'bonus_indirect' => $bonusIndirect,
                'bonus' => $bonusTotal,
                'bonusFinal' => $bonusFinal,
                'epargne' => $epargne
            ];
        }

        return $bonusResults;
    }

    /**
     * Calcule le bonus indirect pour un distributeur
     * Le cumul_total de chaque enfant inclut déjà toute sa descendance
     */
    protected function calculateBonusIndirect(LevelCurrent $levelCurrent, string $period): array
    {
        $totalBonus = 0;
        $details = [];

        // Récupérer les enfants directs
        $enfants = LevelCurrent::where('id_distrib_parent', $levelCurrent->distributeur_id)
            ->where('period', $period)
            ->with('distributeur')
            ->get();

        foreach ($enfants as $enfant) {
            // Si l'enfant a un grade >= au parent, on doit chercher les blocages dans sa descendance
            if ($enfant->etoiles >= $levelCurrent->etoiles) {
                // On ne calcule pas de bonus sur cet enfant
                $details[] = [
                    'distributeur_id' => $enfant->distributeur_id,
                    'matricule' => $enfant->distributeur->distributeur_id,
                    'nom' => $enfant->distributeur->nom_distributeur,
                    'grade' => $enfant->etoiles,
                    'status' => 'BLOQUE - Grade >= Parent',
                    'bonus' => 0
                ];
                continue;
            }

            // L'enfant a un grade inférieur, on peut calculer le bonus
            $diff = $levelCurrent->etoiles - $enfant->etoiles;
            $taux = $this->etoilesChecker($levelCurrent->etoiles, $diff);

            // On doit vérifier s'il y a des blocages dans la descendance de cet enfant
            $cumulAjuste = $this->getCumulAjuste($enfant, $levelCurrent->etoiles, $period);

            $bonusEnfant = $cumulAjuste * $taux;
            $totalBonus += $bonusEnfant;

            $details[] = [
                'distributeur_id' => $enfant->distributeur_id,
                'matricule' => $enfant->distributeur->distributeur_id,
                'nom' => $enfant->distributeur->nom_distributeur,
                'grade' => $enfant->etoiles,
                'difference' => $diff,
                'taux' => $taux,
                'cumul_total_original' => $enfant->cumul_total,
                'cumul_ajuste' => $cumulAjuste,
                'bonus' => $bonusEnfant,
                'status' => 'OK'
            ];
        }

        return [
            'total' => $totalBonus,
            'details' => $details
        ];
    }

    /**
     * Obtient le cumul ajusté en soustrayant les cumuls des branches bloquées
     */
    protected function getCumulAjuste($distributeur, $gradeOrigine, $period): float
    {
        $cumulTotal = $distributeur->cumul_total;

        // Chercher les enfants qui bloquent (grade >= grade origine)
        $enfantsBloquants = LevelCurrent::where('id_distrib_parent', $distributeur->distributeur_id)
            ->where('period', $period)
            ->where('etoiles', '>=', $gradeOrigine)
            ->get();

        // Soustraire le cumul de chaque branche bloquée
        foreach ($enfantsBloquants as $bloquant) {
            $cumulTotal -= $bloquant->cumul_total;
        }

        // S'assurer qu'on ne retourne pas un nombre négatif
        return max(0, $cumulTotal);
    }

    /**
     * Détermine l'éligibilité d'un distributeur
     * Copie exacte de votre méthode isBonusEligible
     */
    public function isBonusEligible($etoiles, $cumul): array
    {
        switch($etoiles) {
            case 1:
                $bonus = false;
                $quota = 0;
                break; // Fix: ajout du break manquant
            case 2:
                $bonus = true;
                $quota = 0;
                break;
            case 3:
                $bonus = ($cumul >= 10) ? true : false;
                $quota = 10;
                break;
            case 4:
                $bonus = ($cumul >= 15) ? true : false;
                $quota = 15;
                break;
            case 5:
                $bonus = ($cumul >= 30) ? true : false;
                $quota = 30;
                break;
            case 6:
                $bonus = ($cumul >= 50) ? true : false;
                $quota = 50;
                break;
            case 7:
                $bonus = ($cumul >= 100) ? true : false;
                $quota = 100;
                break;
            case 8:
                $bonus = ($cumul >= 150) ? true : false;
                $quota = 150;
                break;
            case 9:
                $bonus = ($cumul >= 180) ? true : false;
                $quota = 180;
                break;
            case 10:
                $bonus = ($cumul >= 180) ? true : false;
                $quota = 180;
                break;
            default:
                $bonus = false;
                $quota = 0;
        }

        return [$bonus, $quota];
    }

    /**
     * Calcule le taux direct selon le niveau d'étoiles
     * Copie exacte de votre méthode tauxDirectCalculator
     */
    public function tauxDirectCalculator($etoiles): float
    {
        switch($etoiles) {
            case 1:
                $taux_dir = 0;
                break;
            case 2:
                $taux_dir = 6/100;
                break;
            case 3:
                $taux_dir = 22/100;
                break;
            case 4:
                $taux_dir = 26/100;
                break;
            case 5:
                $taux_dir = 30/100;
                break;
            case 6:
                $taux_dir = 34/100;
                break;
            case 7:
                $taux_dir = 40/100;
                break;
            case 8:
                $taux_dir = 43/100;
                break;
            case 9:
                $taux_dir = 45/100;
                break;
            case 10:
                $taux_dir = 45/100;
                break;
            default:
                $taux_dir = 0;
        }

        return $taux_dir;
    }

    /**
     * Calcule le taux indirect selon la différence d'étoiles
     * Copie exacte de votre méthode etoilesChecker
     */
    public function etoilesChecker($etoiles, $diff): float
    {
        $taux = 0; // Valeur par défaut

        switch($etoiles) {
            case 1:
                $taux = 0;
                break;
            case 2:
                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.06;
                break;
            case 3:
                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.16;
                if($diff == 2)
                    $taux = 0.22;
                break;
            case 4:
                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.04;
                if($diff == 2)
                    $taux = 0.20;
                if($diff == 3)
                    $taux = 0.26;
                break;
            case 5:
                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.04;
                if($diff == 2)
                    $taux = 0.08;
                if($diff == 3)
                    $taux = 0.24;
                if($diff == 4)
                    $taux = 0.30;
                break;
            case 6:
                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.04;
                if($diff == 2)
                    $taux = 0.08;
                if($diff == 3)
                    $taux = 0.12;
                if($diff == 4)
                    $taux = 0.28;
                if($diff == 5)
                    $taux = 0.34;
                break;
            case 7:
                if($diff == 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.06;
                if($diff == 2)
                    $taux = 0.1;
                if($diff == 3)
                    $taux = 0.14;
                if($diff == 4)
                    $taux = 0.18;
                if($diff == 5)
                    $taux = 0.34;
                if($diff == 6)
                    $taux = 0.40;
                break;
            case 8:
                if($diff == 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.03;
                if($diff == 2)
                    $taux = 0.09;
                if($diff == 3)
                    $taux = 0.13;
                if($diff == 4)
                    $taux = 0.17;
                if($diff == 5)
                    $taux = 0.21;
                if($diff == 6)
                    $taux = 0.37;
                if($diff == 7)
                    $taux = 0.43;
                break;
            case 9:
                if($diff == 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.02;
                if($diff == 2)
                    $taux = 0.05;
                if($diff == 3)
                    $taux = 0.11;
                if($diff == 4)
                    $taux = 0.15;
                if($diff == 5)
                    $taux = 0.19;
                if($diff == 6)
                    $taux = 0.23;
                if($diff == 7)
                    $taux = 0.39;
                if($diff == 8)
                    $taux = 0.45;
                break;
            default:
                $taux = 0;
        }

        return $taux;
    }

    /**
     * Sauvegarde les bonus calculés
     */
    protected function saveBonuses(array $bonusResults, string $period): array
    {
        $savedCount = 0;
        $totalAmount = 0;
        $totalAmountCFA = 0;

        foreach ($bonusResults as $bonusData) {
            // Vérifier encore une fois qu'il n'y a pas de doublon
            $exists = Bonus::where('distributeur_id', $bonusData['distributeur_id'])
                ->where('period', $period)
                ->exists();

            if ($exists) {
                continue;
            }

            // Calcul des montants en CFA (1€ = 500 FCFA)
            $montantDirectCFA = $bonusData['bonus_direct'] * 500;
            $montantIndirectCFA = $bonusData['bonus_indirect'] * 500;
            $montantTotalCFA = $bonusData['bonusFinal'] * 500;

            // Créer le tableau de données avec les bonnes valeurs
            $dataToSave = [
                'num' => $bonusData['numero'],
                'distributeur_id' => $bonusData['distributeur_id'],
                'period' => $period,
                // Montants en euros - S'assurer que les valeurs sont bien passées
                'bonus_direct' => floatval($bonusData['bonus_direct']),
                'bonus_indirect' => floatval($bonusData['bonus_indirect']),
                'bonus_leadership' => 0, // Pas encore implémenté
                'bonus' => floatval($bonusData['bonusFinal']),
                'epargne' => floatval($bonusData['epargne']),
                'montant' => floatval($bonusData['bonusFinal']), // Pour compatibilité
                // Montants en CFA
                'montant_direct' => $montantDirectCFA,
                'montant_indirect' => $montantIndirectCFA,
                'montant_leadership' => 0,
                'montant_total' => $montantTotalCFA,
                // Statut
                'status' => 'calculé',
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Log pour debug
            Log::info("Sauvegarde bonus", [
                'distributeur_id' => $bonusData['distributeur_id'],
                'data' => $dataToSave
            ]);

            // Utiliser create avec le tableau de données
            $bonus = Bonus::create($dataToSave);

            $savedCount++;
            $totalAmount += $bonusData['bonusFinal'];
            $totalAmountCFA += $montantTotalCFA;
        }

        Log::info("Bonus sauvegardés", [
            'period' => $period,
            'count' => $savedCount,
            'total' => $totalAmount,
            'total_cfa' => $totalAmountCFA
        ]);

        return [
            'count' => $savedCount,
            'total' => $totalAmount,
            'total_cfa' => $totalAmountCFA
        ];
    }

    /**
     * Génère un numéro de bonus unique
     * Utilise la même logique que votre système
     */
    protected function generateBonusNumber(): string
    {
        $lastBonus = Bonus::orderBy('id', 'desc')->first();

        if ($lastBonus && $lastBonus->num) {
            return strval(intval($lastBonus->num) + 1);
        }

        return '77700304001';
    }

    /**
     * Calcule le bonus pour un distributeur spécifique
     * (Pour remplacer la méthode show() dans les contrôleurs)
     */
    public function calculateBonusForDistributor(string $matricule, string $period): array
    {
        $distributeur = Distributeur::where('distributeur_id', $matricule)->first();

        if (!$distributeur) {
            return [
                'success' => false,
                'message' => 'Distributeur non trouvé'
            ];
        }

        $levelCurrent = LevelCurrent::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        if (!$levelCurrent) {
            return [
                'success' => false,
                'message' => 'Pas de données pour cette période'
            ];
        }

        // Vérifier l'éligibilité
        $eligible = $this->isBonusEligible($levelCurrent->etoiles, $levelCurrent->new_cumul);

        if (!$eligible[0]) {
            return [
                'success' => false,
                'eligible' => false,
                'data' => [
                    'distributeur_id' => $matricule,
                    'nom_distributeur' => $distributeur->nom_distributeur,
                    'pnom_distributeur' => $distributeur->pnom_distributeur,
                    'new_cumul' => $levelCurrent->new_cumul,
                    'period' => $period,
                    'numero' => 'non éligible',
                    'etoiles' => $levelCurrent->etoiles,
                    'bonus_direct' => 0,
                    'bonus_indirect' => 0,
                    'bonus' => 0,
                    'quota' => $eligible[1],
                    'bonusFinal' => 0
                ]
            ];
        }

        // Vérifier les achats
        $hasAchats = Achat::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->exists();

        if (!$hasAchats) {
            return [
                'success' => false,
                'message' => "Le distributeur n'a pas effectué d'achats"
            ];
        }

        // Vérifier si le bonus existe déjà
        $existingBonus = Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        if ($existingBonus) {
            return [
                'success' => false,
                'message' => 'Le distributeur a déjà touché son bonus',
                'data' => [
                    'duplicata' => true,
                    'distributeur_id' => $matricule,
                    'nom_distributeur' => $distributeur->nom_distributeur,
                    'pnom_distributeur' => $distributeur->pnom_distributeur,
                    'period' => $period,
                    'numero' => $existingBonus->num,
                    'etoiles' => $levelCurrent->etoiles,
                    'bonus_direct' => $existingBonus->bonus_direct,
                    'bonus_indirect' => $existingBonus->bonus_indirect,
                    'bonus' => $existingBonus->bonus,
                    'bonusFinal' => $existingBonus->bonus,
                    'epargne' => $existingBonus->epargne
                ]
            ];
        }

        // Calculer le bonus
        $bonusData = $this->calculateIndividualBonuses(collect([$levelCurrent]), $period);

        if (empty($bonusData)) {
            return [
                'success' => false,
                'message' => 'Erreur lors du calcul du bonus'
            ];
        }

        return [
            'success' => true,
            'data' => $bonusData[0]
        ];
    }
}
