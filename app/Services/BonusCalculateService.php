<?php

namespace App\Services;

use App\Models\Bonus;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BonusCalculateService
{
    /**
     * Taux de bonus direct par grade (seuils minimum de points)
     */
    private array $directBonusThresholds = [
        2 => 0,     // Pas de seuil minimum
        3 => 10,
        4 => 20,
        5 => 40,
        6 => 60,
        7 => 100,
        8 => 150,
        9 => 180,
        10 => 180,
        11 => 180
    ];

    /**
     * Taux de bonus indirect par grade (pourcentage sur new_cumul)
     */
    private array $indirectBonusRates = [
        1 => 0,
        2 => 6,     // 6%
        3 => 16,    // 16%
        4 => 4,     // 4%
        5 => 4,     // 4%
        6 => 4,     // 4%
        7 => 6,     // 6%
        8 => 3,     // 3%
        9 => 2,     // 2%
        10 => 2,    // 2%
        11 => 2     // 2%
    ];

    /**
     * Matrice des taux différentiels entre grades
     * [grade_parent][difference_grade] = taux
     */
    private array $differentialRates = [
        1 => [0 => 0],
        2 => [0 => 0, 1 => 0.40],
        3 => [0 => 0, 1 => 0.38, 2 => 0.40],
        4 => [0 => 0, 1 => 0.18, 2 => 0.38, 3 => 0.40],
        5 => [0 => 0, 1 => 0.14, 2 => 0.18, 3 => 0.38, 4 => 0.40],
        6 => [0 => 0, 1 => 0.10, 2 => 0.14, 3 => 0.18, 4 => 0.38, 5 => 0.40],
        7 => [0 => 0, 1 => 0.06, 2 => 0.10, 3 => 0.14, 4 => 0.18, 5 => 0.38, 6 => 0.40],
        8 => [0 => 0, 1 => 0.04, 2 => 0.06, 3 => 0.10, 4 => 0.14, 5 => 0.18, 6 => 0.38, 7 => 0.40],
        9 => [0 => 0, 1 => 0.02, 2 => 0.04, 3 => 0.06, 4 => 0.10, 5 => 0.14, 6 => 0.18, 7 => 0.38, 8 => 0.40],
        10 => [0 => 0, 1 => 0.02, 2 => 0.04, 3 => 0.06, 4 => 0.10, 5 => 0.14, 6 => 0.18, 7 => 0.38, 8 => 0.40, 9 => 0.40],
        11 => [0 => 0, 1 => 0.02, 2 => 0.04, 3 => 0.06, 4 => 0.10, 5 => 0.14, 6 => 0.18, 7 => 0.38, 8 => 0.40, 9 => 0.40, 10 => 0.40]
    ];

    /**
     * Cache pour les calculs
     */
    private array $calculationCache = [];
    private array $childrenCache = [];

    /**
     * Statistiques de calcul
     */
    private array $statistics = [
        'total_distributors' => 0,
        'eligible_distributors' => 0,
        'total_direct_bonus' => 0,
        'total_indirect_bonus' => 0,
        'total_bonus' => 0,
        'errors' => 0
    ];

    /**
     * Calcule les bonus pour tous les distributeurs d'une période
     */
    public function calculateBonusesForPeriod(string $period, array $options = []): array
    {
        $defaultOptions = [
            'batch_size' => 100,
            'only_eligible' => true,
            'dry_run' => false,
            'debug' => false
        ];

        $options = array_merge($defaultOptions, $options);

        Log::info("Starting bonus calculation for period: {$period}", $options);

        // Réinitialiser les statistiques
        $this->resetStatistics();

        // Démarrer une transaction si pas en dry-run
        if (!$options['dry_run']) {
            DB::beginTransaction();
        }

        try {
            // Récupérer tous les distributeurs avec leurs données de niveau
            $distributors = $this->getDistributorsForPeriod($period, $options['only_eligible']);
            $this->statistics['total_distributors'] = $distributors->count();

            $results = [];

            // Traiter par batch
            foreach ($distributors->chunk($options['batch_size']) as $batch) {
                foreach ($batch as $levelCurrent) {
                    try {
                        $result = $this->calculateBonusForDistributor($levelCurrent, $period, $options);

                        if ($result['total_bonus'] > 0) {
                            $results[] = $result;
                            $this->statistics['eligible_distributors']++;

                            // Sauvegarder si pas en dry-run
                            if (!$options['dry_run']) {
                                $this->saveBonusToDatabase($result, $period);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Error calculating bonus for distributor {$levelCurrent->distributeur_id}", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $this->statistics['errors']++;
                    }
                }
            }

            // Commit si pas en dry-run
            if (!$options['dry_run']) {
                DB::commit();
                Log::info("Bonus calculation completed and saved", $this->statistics);
            } else {
                Log::info("Bonus calculation completed (DRY RUN)", $this->statistics);
            }

            return [
                'success' => true,
                'results' => $results,
                'statistics' => $this->statistics
            ];

        } catch (\Exception $e) {
            if (!$options['dry_run']) {
                DB::rollBack();
            }

            Log::error("Critical error during bonus calculation", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'statistics' => $this->statistics
            ];
        }
    }

    /**
     * Calcule le bonus pour un distributeur spécifique
     */
    public function calculateBonusForDistributor($levelCurrent, string $period, array $options = []): array
    {
        $distributeur = $levelCurrent->distributeur;

        // Calculer le bonus direct
        $directBonus = $this->calculateDirectBonus($levelCurrent);

        // Calculer le bonus indirect
        $indirectBonusData = $this->calculateIndirectBonus($levelCurrent, $period);
        $indirectBonus = $indirectBonusData['total'];

        // Total avec arrondi spécial MLM
        $totalBonus = $this->applyMLMRounding($directBonus + $indirectBonus);

        // Mettre à jour les statistiques
        $this->statistics['total_direct_bonus'] += $directBonus;
        $this->statistics['total_indirect_bonus'] += $indirectBonus;
        $this->statistics['total_bonus'] += $totalBonus;

        // Préparer le résultat
        $result = [
            'distributeur_id' => $distributeur->id,
            'matricule' => $distributeur->distributeur_id,
            'nom' => $distributeur->nom_distributeur,
            'prenom' => $distributeur->pnom_distributeur,
            'grade' => $levelCurrent->etoiles,
            'new_cumul' => $levelCurrent->new_cumul,
            'bonus_direct' => round($directBonus, 2),
            'bonus_indirect' => round($indirectBonus, 2),
            'total_bonus' => $totalBonus,
            'details' => [
                'eligible_for_direct' => $this->isEligibleForDirectBonus($levelCurrent),
                'indirect_branches' => $indirectBonusData['branches']
            ]
        ];

        if ($options['debug'] ?? false) {
            Log::debug("Bonus calculation for {$distributeur->distributeur_id}", $result);
        }

        return $result;
    }

    /**
     * Calcule le bonus direct
     */
    private function calculateDirectBonus($levelCurrent): float
    {
        if (!$this->isEligibleForDirectBonus($levelCurrent)) {
            return 0;
        }

        // Le bonus direct est basé sur le new_cumul et le taux du grade
        $rate = $this->getDirectBonusRate($levelCurrent->etoiles);
        $bonus = $levelCurrent->new_cumul * $rate;

        return $bonus;
    }

    /**
     * Vérifie l'éligibilité au bonus direct
     */
    private function isEligibleForDirectBonus($levelCurrent): bool
    {
        $grade = $levelCurrent->etoiles;
        $cumul = $levelCurrent->new_cumul;

        // Grade 2 est toujours éligible
        if ($grade == 2) {
            return true;
        }

        // Pour les autres grades, vérifier le seuil
        $threshold = $this->directBonusThresholds[$grade] ?? 0;
        return $cumul >= $threshold;
    }

    /**
     * Obtient le taux de bonus direct selon le grade
     */
    private function getDirectBonusRate(int $grade): float
    {
        // Pour le bonus direct, on utilise le taux différentiel maximum du grade
        // C'est-à-dire le taux appliqué quand la différence est maximale
        $maxDiff = $grade - 1;
        return $this->differentialRates[$grade][$maxDiff] ?? 0;
    }

    /**
     * Calcule le bonus indirect sur toute la lignée
     */
    private function calculateIndirectBonus($levelCurrent, string $period): array
    {
        $distributeurId = $levelCurrent->distributeur_id;
        $distributeurGrade = $levelCurrent->etoiles;

        // Récupérer tous les enfants directs
        $children = $this->getDirectChildren($distributeurId, $period);

        $totalBonus = 0;
        $branchesDetail = [];

        foreach ($children as $child) {
            // RÈGLE IMPORTANTE : Si l'enfant a le même grade ou plus, on arrête pour cette branche
            if ($child->etoiles >= $distributeurGrade) {
                $branchesDetail[] = [
                    'child_matricule' => $child->distributeur->distributeur_id,
                    'child_grade' => $child->etoiles,
                    'status' => 'BLOCKED_SAME_OR_HIGHER_GRADE',
                    'bonus' => 0
                ];
                continue;
            }

            // Calculer le bonus sur cette branche
            $branchBonus = $this->calculateBranchBonus($child, $distributeurGrade, $period);
            $totalBonus += $branchBonus['total'];

            $branchesDetail[] = [
                'child_matricule' => $child->distributeur->distributeur_id,
                'child_grade' => $child->etoiles,
                'status' => 'CALCULATED',
                'bonus' => $branchBonus['total'],
                'sub_branches' => $branchBonus['details']
            ];
        }

        return [
            'total' => $totalBonus,
            'branches' => $branchesDetail
        ];
    }

    /**
     * Calcule le bonus sur une branche entière (récursif)
     */
    private function calculateBranchBonus($levelCurrent, int $parentGrade, string $period): array
    {
        $childGrade = $levelCurrent->etoiles;
        $childNewCumul = $levelCurrent->new_cumul;

        // Calculer le taux différentiel
        $gradeDiff = $parentGrade - $childGrade;
        $differentialRate = $this->getDifferentialRate($parentGrade, $gradeDiff);

        // Bonus direct sur cet enfant
        $directBonusOnChild = $childNewCumul * $differentialRate;

        // Récupérer les enfants de cet enfant pour continuer la descente
        $grandChildren = $this->getDirectChildren($levelCurrent->distributeur_id, $period);

        $subBranchesBonus = 0;
        $subBranchesDetail = [];

        foreach ($grandChildren as $grandChild) {
            // Si le petit-enfant a un grade >= à l'enfant, on arrête cette sous-branche
            if ($grandChild->etoiles >= $childGrade) {
                continue;
            }

            // Continuer récursivement
            $subBranch = $this->calculateBranchBonus($grandChild, $parentGrade, $period);
            $subBranchesBonus += $subBranch['total'];
            $subBranchesDetail[] = $subBranch;
        }

        return [
            'total' => $directBonusOnChild + $subBranchesBonus,
            'direct' => $directBonusOnChild,
            'indirect' => $subBranchesBonus,
            'details' => $subBranchesDetail
        ];
    }

    /**
     * Obtient le taux différentiel entre deux grades
     */
    private function getDifferentialRate(int $parentGrade, int $gradeDifference): float
    {
        if ($gradeDifference <= 0) {
            return 0;
        }

        return $this->differentialRates[$parentGrade][$gradeDifference] ?? 0;
    }

    /**
     * Récupère les enfants directs d'un distributeur pour une période
     */
    private function getDirectChildren(int $distributeurId, string $period): Collection
    {
        // Utiliser le cache si disponible
        $cacheKey = "{$distributeurId}_{$period}";
        if (isset($this->childrenCache[$cacheKey])) {
            return $this->childrenCache[$cacheKey];
        }

        // Récupérer depuis la base de données
        $children = LevelCurrent::where('id_distrib_parent', $distributeurId)
            ->where('period', $period)
            ->with('distributeur')
            ->get();

        // Mettre en cache
        $this->childrenCache[$cacheKey] = $children;

        return $children;
    }

    /**
     * Récupère les distributeurs pour une période
     */
    private function getDistributorsForPeriod(string $period, bool $onlyEligible = true): Collection
    {
        $query = LevelCurrent::where('period', $period)
            ->with('distributeur');

        if ($onlyEligible) {
            // Filtrer seulement ceux qui ont un new_cumul > 0
            $query->where('new_cumul', '>', 0);
        }

        return $query->get();
    }

    /**
     * Applique l'arrondi spécial MLM
     */
    private function applyMLMRounding(float $amount): float
    {
        $decimal = $amount - floor($amount);

        if ($decimal > 0.5) {
            return floor($amount);
        } else {
            return floor($amount) - 1;
        }
    }

    /**
     * Sauvegarde le bonus dans la base de données
     */
    private function saveBonusToDatabase(array $bonusData, string $period): void
    {
        // Générer le numéro de bonus
        $lastBonus = Bonus::orderBy('id', 'desc')->first();
        $numero = $lastBonus ? ($lastBonus->num + 1) : 77700304001;

        Bonus::create([
            'distributeur_id' => $bonusData['distributeur_id'],
            'period' => $period,
            'num' => $numero,
            'montant_direct' => $bonusData['bonus_direct'],
            'montant_indirect' => $bonusData['bonus_indirect'],
            'montant_total' => $bonusData['total_bonus'],
            'type_bonus' => 'mensuel',
            'statut' => 'calculé',
            'details' => json_encode($bonusData['details'])
        ]);
    }

    /**
     * Génère un rapport détaillé des bonus
     */
    public function generateBonusReport(string $period): array
    {
        $bonuses = Bonus::where('period', $period)
            ->with('distributeur')
            ->get();

        $report = [
            'period' => $period,
            'total_distributors' => $bonuses->count(),
            'total_amount' => $bonuses->sum('montant_total'),
            'total_direct' => $bonuses->sum('montant_direct'),
            'total_indirect' => $bonuses->sum('montant_indirect'),
            'by_grade' => [],
            'top_earners' => []
        ];

        // Grouper par grade
        $byGrade = $bonuses->groupBy(function($bonus) {
            return $bonus->distributeur->etoiles_id;
        });

        foreach ($byGrade as $grade => $gradeBonuses) {
            $report['by_grade'][$grade] = [
                'count' => $gradeBonuses->count(),
                'total' => $gradeBonuses->sum('montant_total'),
                'average' => $gradeBonuses->avg('montant_total')
            ];
        }

        // Top 10 earners
        $report['top_earners'] = $bonuses->sortByDesc('montant_total')
            ->take(10)
            ->map(function($bonus) {
                return [
                    'matricule' => $bonus->distributeur->distributeur_id,
                    'nom' => $bonus->distributeur->nom_distributeur . ' ' . $bonus->distributeur->pnom_distributeur,
                    'grade' => $bonus->distributeur->etoiles_id,
                    'montant' => $bonus->montant_total
                ];
            })
            ->values()
            ->toArray();

        return $report;
    }

    /**
     * Réinitialise les statistiques
     */
    private function resetStatistics(): void
    {
        $this->statistics = [
            'total_distributors' => 0,
            'eligible_distributors' => 0,
            'total_direct_bonus' => 0,
            'total_indirect_bonus' => 0,
            'total_bonus' => 0,
            'errors' => 0
        ];

        // Vider les caches
        $this->calculationCache = [];
        $this->childrenCache = [];
    }

    /**
     * Recalcule les bonus pour un distributeur spécifique
     */
    public function recalculateBonusForDistributor(string $matricule, string $period): array
    {
        $distributeur = Distributeur::where('distributeur_id', $matricule)->first();

        if (!$distributeur) {
            return [
                'success' => false,
                'error' => 'Distributeur non trouvé'
            ];
        }

        $levelCurrent = LevelCurrent::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        if (!$levelCurrent) {
            return [
                'success' => false,
                'error' => 'Aucune donnée pour cette période'
            ];
        }

        // Supprimer l'ancien bonus s'il existe
        Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->delete();

        // Recalculer
        $result = $this->calculateBonusForDistributor($levelCurrent, $period, ['debug' => true]);

        if ($result['total_bonus'] > 0) {
            $this->saveBonusToDatabase($result, $period);
        }

        return [
            'success' => true,
            'bonus' => $result
        ];
    }
}
