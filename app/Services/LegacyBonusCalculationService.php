<?php

namespace App\Services;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Bonus;
use App\Models\Achat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de calcul des bonus selon l'ancienne méthode
 * Corrigé pour utiliser exactement la logique métier qui fonctionne
 */
class LegacyBonusCalculationService
{
    /**
     * Taux de bonus direct selon votre système
     */
    private const TAUX_DIRECT = [
        1 => 0,
        2 => 0.06,
        3 => 0.22,
        4 => 0.26,
        5 => 0.30,
        6 => 0.34,
        7 => 0.40,
        8 => 0.43,
        9 => 0.45,
        10 => 0.45
    ];

    /**
     * Seuils d'éligibilité selon votre système
     */
    private const SEUILS_ELIGIBILITE = [
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
     * Calcule les bonus pour une période donnée
     */
    public function calculateBonusesForPeriod(string $period, array $options = []): array
    {
        Log::info("Début du calcul des bonus (Legacy)", ['period' => $period]);

        DB::beginTransaction();

        try {
            $startTime = microtime(true);

            // Récupérer les distributeurs éligibles
            $eligibleDistributors = $this->getEligibleDistributors($period);
            $totalDistributors = LevelCurrent::where('period', $period)->count();

            Log::info("Distributeurs éligibles trouvés", [
                'count' => $eligibleDistributors->count()
            ]);

            // Calculer les bonus par batch
            $allBonusResults = collect();
            $batchSize = $options['batch_size'] ?? 100;

            foreach ($eligibleDistributors->chunk($batchSize) as $batch) {
                $batchResults = $this->calculateBonusBatch($batch, $period);
                $allBonusResults = $allBonusResults->concat($batchResults);
            }

            // Sauvegarder les résultats
            $savedStats = $this->saveBonuses($allBonusResults, $period);

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);

            Log::info("Calcul des bonus terminé (Legacy)", [
                'period' => $period,
                'calculated' => $allBonusResults->count(),
                'saved' => $savedStats['count'],
                'total_amount' => $savedStats['total'],
                'total_amount_cfa' => $savedStats['total_cfa']
            ]);

            return [
                'success' => true,
                'period' => $period,
                'stats' => [
                    'total_distributeurs' => $totalDistributors,
                    'eligibles' => $eligibleDistributors->count(),
                    'eligible_count' => $eligibleDistributors->count(),
                    'bonuses_calculated' => $allBonusResults->count(),
                    'calculated_count' => $allBonusResults->count(),
                    'saved_count' => $savedStats['count'],
                    'total_amount' => $savedStats['total_cfa'], // En CFA pour le workflow
                    'total_bonus' => $savedStats['total'],
                    'total_cfa' => $savedStats['total_cfa']
                ],
                'bonuses' => $allBonusResults,
                'duration' => $duration
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur calcul bonus (Legacy)", [
                'period' => $period,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère les distributeurs éligibles selon votre logique
     */
    private function getEligibleDistributors(string $period): Collection
    {
        return LevelCurrent::where('period', $period)
            ->with('distributeur')
            ->get()
            ->filter(function ($levelCurrent) use ($period) {
                // Appliquer votre logique d'éligibilité
                $seuil = self::SEUILS_ELIGIBILITE[$levelCurrent->etoiles] ?? null;

                if (!$seuil || !$seuil['eligible']) {
                    return false;
                }

                if ($seuil['eligible'] === 'conditionnel') {
                    $quota = $seuil['quota'];
                    return $levelCurrent->new_cumul >= $quota;
                }

                return true;
            });
    }

    /**
     * Calcule les bonus pour un batch de distributeurs
     */
    private function calculateBonusBatch(Collection $batch, string $period): Collection
    {
        $results = collect();

        foreach ($batch as $levelCurrent) {
            // Vérifier qu'il a fait des achats
            $hasAchats = Achat::where('distributeur_id', $levelCurrent->distributeur_id)
                ->where('period', $period)
                ->exists();

            if (!$hasAchats) {
                continue;
            }

            // Vérifier qu'il n'a pas déjà de bonus pour cette période
            $existingBonus = Bonus::where('distributeur_id', $levelCurrent->distributeur_id)
                ->where('period', $period)
                ->exists();

            if ($existingBonus) {
                Log::warning("Bonus déjà existant", [
                    'distributeur_id' => $levelCurrent->distributeur_id,
                    'period' => $period
                ]);
                continue;
            }

            // Calculer les bonus
            $bonusData = $this->calculateIndividualBonus($levelCurrent, $period);
            if ($bonusData['bonus_final'] > 0) {
                $results->push($bonusData);
            }
        }

        return $results;
    }

    /**
     * Calcule le bonus individuel d'un distributeur
     * Utilise exactement votre logique métier
     */
    private function calculateIndividualBonus(LevelCurrent $levelCurrent, string $period): array
    {
        $distributeur = $levelCurrent->distributeur;

        // 1. Bonus direct avec vos taux exacts
        $tauxDirect = self::TAUX_DIRECT[$levelCurrent->etoiles] ?? 0;
        $bonusDirect = $levelCurrent->new_cumul * $tauxDirect;

        // 2. Bonus indirect - UNIQUEMENT première ligne
        $bonusIndirectData = $this->calculateBonusIndirect(
            $levelCurrent->distributeur_id,
            $levelCurrent->etoiles,
            $period
        );
        $bonusIndirect = $bonusIndirectData['total'];

        // 3. Total et épargne selon votre logique exacte
        $bonusTotal = $bonusDirect + $bonusIndirect;
        $decimal = $bonusTotal - floor($bonusTotal);

        // Votre logique d'épargne spécifique
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
            'distributeur_id' => $levelCurrent->distributeur_id,
            'matricule' => $distributeur->distributeur_id,
            'nom' => $distributeur->nom_distributeur . ' ' . $distributeur->pnom_distributeur,
            'grade' => $levelCurrent->etoiles,
            'new_cumul' => $levelCurrent->new_cumul,
            'bonus_direct' => $bonusDirect,
            'bonus_indirect' => $bonusIndirect,
            'bonus_total' => $bonusTotal,
            'bonus_final' => $bonusFinal,
            'epargne' => $epargne,
            'details' => $bonusIndirectData['details']
        ];
    }

    /**
     * Calcule le bonus indirect avec la logique correcte
     * Le cumul_total inclut déjà toute la descendance
     */
    private function calculateBonusIndirect(int $distributeurId, int $gradeParent, string $period): array
    {
        $totalBonus = 0;
        $details = [];

        // Récupérer les filleuls directs
        $filleuls = LevelCurrent::where('period', $period)
            ->where('id_distrib_parent', $distributeurId)
            ->with('distributeur')
            ->get();

        foreach ($filleuls as $filleul) {
            // Si le filleul a un grade >= au parent, on bloque
            if ($filleul->etoiles >= $gradeParent) {
                $details[] = [
                    'filleul_id' => $filleul->distributeur_id,
                    'filleul_matricule' => $filleul->distributeur->distributeur_id,
                    'filleul_nom' => $filleul->distributeur->nom_distributeur,
                    'filleul_grade' => $filleul->etoiles,
                    'status' => 'BLOQUE',
                    'raison' => "Grade filleul ({$filleul->etoiles}) >= Grade parent ({$gradeParent})",
                    'bonus' => 0
                ];
                continue;
            }

            // Le filleul a un grade inférieur, on calcule le bonus
            $diff = $gradeParent - $filleul->etoiles;
            $taux = $this->getAncienTauxIndirect($gradeParent, $diff);

            // Vérifier s'il y a des blocages dans sa descendance
            $cumulAjuste = $this->getCumulAjuste($filleul, $gradeParent, $period);

            $bonusFilleul = $cumulAjuste * $taux;
            $totalBonus += $bonusFilleul;

            $details[] = [
                'filleul_id' => $filleul->distributeur_id,
                'filleul_matricule' => $filleul->distributeur->distributeur_id,
                'filleul_nom' => $filleul->distributeur->nom_distributeur,
                'filleul_grade' => $filleul->etoiles,
                'difference_grade' => $diff,
                'taux' => $taux,
                'cumul_total_original' => $filleul->cumul_total,
                'cumul_ajuste' => $cumulAjuste,
                'bonus' => $bonusFilleul,
                'status' => 'OK'
            ];
        }

        return ['total' => $totalBonus, 'details' => $details];
    }

    /**
     * Obtient le cumul ajusté en soustrayant les branches bloquées
     */
    private function getCumulAjuste($distributeur, $gradeOrigine, $period): float
    {
        $cumulTotal = $distributeur->cumul_total;

        // Parcourir récursivement pour trouver les blocages
        $blocages = $this->findBlocages($distributeur->distributeur_id, $gradeOrigine, $period);

        // Soustraire les cumuls bloqués
        foreach ($blocages as $blocage) {
            $cumulTotal -= $blocage['cumul_bloque'];
        }

        return max(0, $cumulTotal);
    }

    /**
     * Trouve tous les blocages dans la descendance
     */
    private function findBlocages($distributeurId, $gradeOrigine, $period, $depth = 0): array
    {
        if ($depth >= 20) {
            return [];
        }

        $blocages = [];

        $enfants = LevelCurrent::where('period', $period)
            ->where('id_distrib_parent', $distributeurId)
            ->get();

        foreach ($enfants as $enfant) {
            if ($enfant->etoiles >= $gradeOrigine) {
                // Cet enfant bloque, on ajoute son cumul
                $blocages[] = [
                    'distributeur_id' => $enfant->distributeur_id,
                    'grade' => $enfant->etoiles,
                    'cumul_bloque' => $enfant->cumul_total
                ];
                // On ne continue pas dans cette branche
            } else {
                // On continue la recherche dans cette branche
                $sousBlocages = $this->findBlocages($enfant->distributeur_id, $gradeOrigine, $period, $depth + 1);
                $blocages = array_merge($blocages, $sousBlocages);
            }
        }

        return $blocages;
    }

    /**
     * Retourne le taux selon votre matrice exacte (méthode etoilesChecker)
     */
    private function getAncienTauxIndirect(int $etoiles, int $diff): float
    {
        if ($diff <= 0) return 0;

        // Votre matrice exacte de taux
        $matrice = [
            2 => [1 => 0.06],
            3 => [1 => 0.16, 2 => 0.22],
            4 => [1 => 0.04, 2 => 0.20, 3 => 0.26],
            5 => [1 => 0.04, 2 => 0.08, 3 => 0.24, 4 => 0.30],
            6 => [1 => 0.04, 2 => 0.08, 3 => 0.12, 4 => 0.28, 5 => 0.34],
            7 => [1 => 0.06, 2 => 0.10, 3 => 0.14, 4 => 0.18, 5 => 0.34, 6 => 0.40],
            8 => [1 => 0.03, 2 => 0.09, 3 => 0.13, 4 => 0.17, 5 => 0.21, 6 => 0.37, 7 => 0.43],
            9 => [1 => 0.02, 2 => 0.05, 3 => 0.11, 4 => 0.15, 5 => 0.19, 6 => 0.23, 7 => 0.39, 8 => 0.45],
        ];

        return $matrice[$etoiles][$diff] ?? 0;
    }

    /**
     * Sauvegarde les bonus calculés
     */
    private function saveBonuses(Collection $bonusResults, string $period): array
    {
        $savedCount = 0;
        $totalAmount = 0;
        $totalAmountCFA = 0;

        foreach ($bonusResults as $bonusData) {
            // Générer le numéro de bonus
            $numBonus = $this->generateBonusNumber();

            // Calcul des montants en CFA (1€ = 500 FCFA)
            $montantDirectCFA = $bonusData['bonus_direct'] * 500;
            $montantIndirectCFA = $bonusData['bonus_indirect'] * 500;
            $montantTotalCFA = $bonusData['bonus_final'] * 500;

            $bonus = Bonus::create([
                'num' => $numBonus,
                'distributeur_id' => $bonusData['distributeur_id'],
                'period' => $period,
                // Montants en euros
                'bonus_direct' => $bonusData['bonus_direct'],
                'bonus_indirect' => $bonusData['bonus_indirect'],
                'bonus' => $bonusData['bonus_final'], // Après épargne
                'epargne' => $bonusData['epargne'],
                // Montants en CFA
                'montant_direct' => $montantDirectCFA,
                'montant_indirect' => $montantIndirectCFA,
                'montant_total' => $montantTotalCFA,
                // Champ montant pour compatibilité
                'montant' => $bonusData['bonus_final'],
                // Statut
                'status' => 'calculé',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $savedCount++;
            $totalAmount += $bonusData['bonus_final'];
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
     * Génère un numéro de bonus unique (votre format exact)
     */
    private function generateBonusNumber(): string
    {
        $lastBonus = Bonus::orderBy('id', 'desc')->first();

        if ($lastBonus && preg_match('/^777\d+$/', $lastBonus->num)) {
            // Incrémenter depuis le dernier numéro
            $lastNumber = intval($lastBonus->num);
            return strval($lastNumber + 1);
        }

        // Numéro de départ
        return '77700304001';
    }

    /**
     * Méthode pour vérifier l'éligibilité (utilisée par les contrôleurs)
     */
    public function isBonusEligible($etoiles, $cumul): array
    {
        $seuil = self::SEUILS_ELIGIBILITE[$etoiles] ?? ['eligible' => false, 'quota' => 0];

        if (!$seuil['eligible']) {
            return [false, $seuil['quota']];
        }

        if ($seuil['eligible'] === 'conditionnel') {
            $isEligible = $cumul >= $seuil['quota'];
            return [$isEligible, $seuil['quota']];
        }

        return [true, $seuil['quota']];
    }
}
