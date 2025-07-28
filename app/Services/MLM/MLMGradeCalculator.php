<?php

namespace App\Services\MLM;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MLMGradeCalculator
{
    protected array $gradeRules;
    protected array $lastCheckDetails = [];
    protected ?string $currentPeriod = null;
    protected array $cache = [];

    public function __construct()
    {
        $this->gradeRules = config('mlm-cleaning.grades.rules', []);
    }

    /**
     * Calculer le grade d'un distributeur pour une période
     */
    public function calculateGrade(int $distributeurId, string $period): int
    {
        $this->currentPeriod = $period;
        $this->lastCheckDetails = [];

        // Récupérer les données du distributeur
        $levelCurrent = LevelCurrent::where('distributeur_id', $distributeurId)
            ->where('period', $period)
            ->first();

        if (!$levelCurrent) {
            Log::warning("No level_current record found for distributeur {$distributeurId} in period {$period}");
            return 1; // Grade initial par défaut
        }

        // Vérifier chaque grade de 2 à 11
        $maxGradeAchieved = 1;

        for ($grade = 2; $grade <= config('mlm-cleaning.grades.max_grade', 11); $grade++) {
            if ($this->meetsGradeConditions($distributeurId, $grade, $levelCurrent)) {
                $maxGradeAchieved = $grade;
                $this->lastCheckDetails['achieved_grades'][] = $grade;
            } else {
                // Si on ne remplit pas les conditions pour ce grade, on arrête
                break;
            }
        }

        $this->lastCheckDetails['final_grade'] = $maxGradeAchieved;
        return $maxGradeAchieved;
    }

    /**
     * Vérifier si un distributeur remplit les conditions pour un grade
     */
    protected function meetsGradeConditions(int $distributeurId, int $targetGrade, $levelCurrent): bool
    {
        if (!isset($this->gradeRules[$targetGrade])) {
            return false;
        }

        $conditions = $this->gradeRules[$targetGrade]['conditions'];

        // Pour chaque ensemble de conditions (OR)
        foreach ($conditions as $conditionIndex => $conditionSet) {
            $this->lastCheckDetails['grade_' . $targetGrade]['condition_' . $conditionIndex] = [];

            if ($this->checkConditionSet($distributeurId, $conditionSet, $levelCurrent, $targetGrade, $conditionIndex)) {
                $this->lastCheckDetails['grade_' . $targetGrade]['passed'] = true;
                $this->lastCheckDetails['grade_' . $targetGrade]['passed_condition'] = $conditionIndex;
                return true;
            }
        }

        $this->lastCheckDetails['grade_' . $targetGrade]['passed'] = false;
        return false;
    }

    /**
     * Vérifier un ensemble de conditions (AND)
     */
    protected function checkConditionSet(
        int $distributeurId,
        array $conditionSet,
        $levelCurrent,
        int $targetGrade,
        int $conditionIndex
    ): bool {
        $details = &$this->lastCheckDetails['grade_' . $targetGrade]['condition_' . $conditionIndex];
        $allConditionsMet = true;

        // 1. Vérifier le cumul individuel
        if (isset($conditionSet['cumul_individuel'])) {
            $required = $conditionSet['cumul_individuel'];
            $actual = $levelCurrent->cumul_individuel;
            $met = $actual >= $required;

            $details['cumul_individuel'] = [
                'required' => $required,
                'actual' => $actual,
                'met' => $met
            ];

            if (!$met) {
                $allConditionsMet = false;
            }
        }

        // 2. Vérifier le grade minimum requis
        if (isset($conditionSet['min_grade'])) {
            $required = $conditionSet['min_grade'];
            $actual = $levelCurrent->etoiles;
            $met = $actual >= $required;

            $details['min_grade'] = [
                'required' => $required,
                'actual' => $actual,
                'met' => $met
            ];

            if (!$met) {
                $allConditionsMet = false;
            }
        }

        // 3. Vérifier le cumul collectif
        if (isset($conditionSet['cumul_collectif'])) {
            $required = $conditionSet['cumul_collectif'];
            $actual = $levelCurrent->cumul_collectif;
            $met = $actual >= $required;

            $details['cumul_collectif'] = [
                'required' => $required,
                'actual' => $actual,
                'met' => $met
            ];

            if (!$met) {
                $allConditionsMet = false;
            }
        }

        // 4. Vérifier les conditions sur les enfants (filleuls)
        if (isset($conditionSet['children'])) {
            $childrenMet = $this->checkChildrenConditions(
                $distributeurId,
                $conditionSet['children'],
                $details
            );

            if (!$childrenMet) {
                $allConditionsMet = false;
            }
        }

        return $allConditionsMet;
    }

    /**
     * Vérifier les conditions sur les enfants
     */
    protected function checkChildrenConditions(int $distributeurId, $childrenConditions, &$details): bool
    {
        // Si c'est un tableau simple (une seule condition)
        if (isset($childrenConditions['grade'])) {
            return $this->checkSingleChildrenCondition($distributeurId, $childrenConditions, $details);
        }

        // Si c'est un tableau de conditions multiples
        $allConditionsMet = true;
        foreach ($childrenConditions as $index => $condition) {
            $subDetails = [];
            if (!$this->checkSingleChildrenCondition($distributeurId, $condition, $subDetails)) {
                $allConditionsMet = false;
            }
            $details['children_condition_' . $index] = $subDetails;
        }

        return $allConditionsMet;
    }

    /**
     * Vérifier une condition unique sur les enfants
     */
    protected function checkSingleChildrenCondition(int $distributeurId, array $condition, &$details): bool
    {
        $requiredGrade = $condition['grade'];
        $requiredCount = $condition['count'];
        $requiredFeet = $condition['feet'];

        // Obtenir les enfants par pied
        $childrenByFeet = $this->getChildrenByFeet($distributeurId);

        // Compter les pieds qui ont au moins un enfant du grade requis
        $qualifiedFeet = 0;
        $totalQualifiedChildren = 0;
        $feetDetails = [];

        foreach ($childrenByFeet as $footId => $children) {
            $qualifiedInFoot = 0;

            foreach ($children as $child) {
                if ($child->etoiles >= $requiredGrade) {
                    $qualifiedInFoot++;
                    $totalQualifiedChildren++;
                }
            }

            if ($qualifiedInFoot > 0) {
                $qualifiedFeet++;
                $feetDetails[$footId] = $qualifiedInFoot;
            }
        }

        $met = $qualifiedFeet >= $requiredFeet && $totalQualifiedChildren >= $requiredCount;

        $details = [
            'required_grade' => $requiredGrade,
            'required_count' => $requiredCount,
            'required_feet' => $requiredFeet,
            'actual_qualified_children' => $totalQualifiedChildren,
            'actual_qualified_feet' => $qualifiedFeet,
            'feet_details' => $feetDetails,
            'met' => $met
        ];

        return $met;
    }

    /**
     * Obtenir les enfants groupés par pied
     */
    protected function getChildrenByFeet(int $distributeurId): array
    {
        $cacheKey = "children_by_feet_{$distributeurId}_{$this->currentPeriod}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Récupérer tous les enfants directs
        $directChildren = Distributeur::where('id_distrib_parent', $distributeurId)
            ->pluck('id')
            ->toArray();

        $childrenByFeet = [];

        foreach ($directChildren as $childId) {
            // Pour chaque enfant direct, récupérer toute sa descendance
            $descendants = $this->getAllDescendants($childId);
            $descendants[] = $childId; // Inclure l'enfant direct lui-même

            // Récupérer les données de niveau pour cette période
            $childrenData = LevelCurrent::whereIn('distributeur_id', $descendants)
                ->where('period', $this->currentPeriod)
                ->get();

            $childrenByFeet[$childId] = $childrenData;
        }

        $this->cache[$cacheKey] = $childrenByFeet;

        return $childrenByFeet;
    }

    /**
     * Obtenir tous les descendants d'un distributeur
     */
    protected function getAllDescendants(int $distributeurId): array
    {
        $descendants = [];
        $toProcess = [$distributeurId];

        while (!empty($toProcess)) {
            $current = array_shift($toProcess);

            $children = Distributeur::where('id_distrib_parent', $current)
                ->pluck('id')
                ->toArray();

            foreach ($children as $childId) {
                $descendants[] = $childId;
                $toProcess[] = $childId;
            }
        }

        return $descendants;
    }

    /**
     * Obtenir les détails de la dernière vérification
     */
    public function getLastCheckDetails(): array
    {
        return $this->lastCheckDetails;
    }

    /**
     * Recalculer les grades pour une période complète
     */
    public function recalculateGradesForPeriod(string $period, ?callable $progressCallback = null): array
    {
        $stats = [
            'total' => 0,
            'changed' => 0,
            'upgraded' => 0,
            'downgraded' => 0,
            'errors' => 0
        ];

        $totalRecords = LevelCurrent::where('period', $period)->count();
        $processed = 0;

        LevelCurrent::where('period', $period)
            ->chunk(100, function($records) use ($period, &$stats, &$processed, $totalRecords, $progressCallback) {
                foreach ($records as $record) {
                    try {
                        $calculatedGrade = $this->calculateGrade($record->distributeur_id, $period);

                        if ($calculatedGrade != $record->etoiles) {
                            if ($calculatedGrade > $record->etoiles) {
                                $stats['upgraded']++;
                            } else {
                                $stats['downgraded']++;
                            }

                            $record->etoiles = $calculatedGrade;
                            $record->save();

                            $stats['changed']++;
                        }

                        $stats['total']++;
                        $processed++;

                        if ($progressCallback && $processed % 10 == 0) {
                            $progressCallback($processed, $totalRecords);
                        }

                    } catch (\Exception $e) {
                        Log::error("Error calculating grade for distributeur {$record->distributeur_id}: " . $e->getMessage());
                        $stats['errors']++;
                    }
                }
            });

        return $stats;
    }

    /**
     * Obtenir l'explication des conditions pour un grade
     */
    public function getGradeRequirementsExplanation(int $grade): array
    {
        if (!isset($this->gradeRules[$grade])) {
            return ['error' => 'Grade non défini'];
        }

        $conditions = $this->gradeRules[$grade]['conditions'];
        $explanations = [];

        foreach ($conditions as $index => $conditionSet) {
            $parts = [];

            if (isset($conditionSet['cumul_individuel'])) {
                $parts[] = "Cumul individuel ≥ " . number_format($conditionSet['cumul_individuel']);
            }

            if (isset($conditionSet['min_grade'])) {
                $parts[] = "Avoir au moins le grade " . $conditionSet['min_grade'];
            }

            if (isset($conditionSet['cumul_collectif'])) {
                $parts[] = "Cumul collectif ≥ " . number_format($conditionSet['cumul_collectif']);
            }

            if (isset($conditionSet['children'])) {
                $childrenParts = $this->explainChildrenConditions($conditionSet['children']);
                $parts = array_merge($parts, $childrenParts);
            }

            $explanations[] = [
                'condition' => $index + 1,
                'requirements' => $parts,
                'type' => count($conditions) > 1 ? 'OU' : 'ET'
            ];
        }

        return [
            'grade' => $grade,
            'conditions' => $explanations
        ];
    }

    /**
     * Expliquer les conditions sur les enfants
     */
    protected function explainChildrenConditions($childrenConditions): array
    {
        $explanations = [];

        if (isset($childrenConditions['grade'])) {
            // Condition simple
            $explanations[] = sprintf(
                "Avoir %d enfants de grade %d dans %d pieds différents",
                $childrenConditions['count'],
                $childrenConditions['grade'],
                $childrenConditions['feet']
            );
        } else {
            // Conditions multiples
            foreach ($childrenConditions as $condition) {
                $explanations[] = sprintf(
                    "Avoir %d enfants de grade %d dans %d pieds différents",
                    $condition['count'],
                    $condition['grade'],
                    $condition['feet']
                );
            }
        }

        return $explanations;
    }
}
