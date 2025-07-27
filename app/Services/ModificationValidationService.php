<?php

namespace App\Services;

use App\Models\ModificationRequest;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Bonus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CumulManagementService;

class ModificationValidationService
{
    private CumulManagementService $cumulService;

    public function __construct(CumulManagementService $cumulService)
    {
        $this->cumulService = $cumulService;
    }
    /**
     * Valide une demande de changement de parent
     */
    public function validateParentChange(Distributeur $distributeur, Distributeur $newParent): array
    {
        $result = [
            'is_valid' => true,
            'warnings' => [],
            'blockers' => [],
            'impact' => []
        ];

        // 1. Vérifier que le nouveau parent n'est pas un descendant
        if ($this->isDescendant($distributeur, $newParent)) {
            $result['blockers'][] = "Le nouveau parent ne peut pas être un descendant du distributeur";
            $result['is_valid'] = false;
        }

        // 2. Vérifier que ce n'est pas une boucle
        if ($distributeur->id === $newParent->id) {
            $result['blockers'][] = "Un distributeur ne peut pas être son propre parent";
            $result['is_valid'] = false;
        }

        // 3. Analyser l'impact sur la hiérarchie
        $children = $distributeur->children()->count();
        if ($children > 0) {
            $result['impact']['children_count'] = $children;
            $result['warnings'][] = "Ce distributeur a {$children} enfant(s) qui seront également déplacés";
        }

        // 4. Impact sur les calculs MLM
        $currentPeriod = date('Y-m');
        $affectedLevels = $this->getAffectedLevels($distributeur, $newParent);

        if ($affectedLevels > 0) {
            $result['impact']['affected_levels'] = $affectedLevels;
            $result['warnings'][] = "Ce changement affectera {$affectedLevels} niveaux dans la hiérarchie";
        }

        // 5. Vérifier les bonus en cours
        $activeBonus = Bonus::where('distributeur_id', $distributeur->id)
                           ->where('period', $currentPeriod)
                           ->exists();

        if ($activeBonus) {
            $result['warnings'][] = "Des bonus ont déjà été calculés pour la période en cours";
            $result['impact']['recalculation_needed'] = true;
        }

        return $result;
    }

    /**
     * Valide un changement de grade manuel
     */
    public function validateGradeChange(Distributeur $distributeur, int $newGrade): array
    {
        $result = [
            'is_valid' => true,
            'warnings' => [],
            'blockers' => [],
            'impact' => [],
            'justification_required' => false
        ];

        $currentGrade = $distributeur->etoiles_id;
        $gradeDiff = $newGrade - $currentGrade;

        // 1. Vérifier la validité du grade
        if ($newGrade < 1 || $newGrade > 10) {
            $result['blockers'][] = "Le grade doit être entre 1 et 10";
            $result['is_valid'] = false;
            return $result;
        }

        // 2. Vérifier l'ampleur du changement
        if (abs($gradeDiff) > 2) {
            $result['warnings'][] = "Changement de grade important ({$gradeDiff} niveaux)";
            $result['justification_required'] = true;
        }

        // 3. Vérifier la cohérence avec les performances
        $currentLevel = LevelCurrent::where('distributeur_id', $distributeur->id)
                                   ->where('period', date('Y-m'))
                                   ->first();

        if ($currentLevel) {
            $expectedGrade = $this->calculateExpectedGrade($currentLevel);

            if ($newGrade > $expectedGrade + 1) {
                $result['warnings'][] = "Le nouveau grade est supérieur au grade calculé ({$expectedGrade})";
                $result['justification_required'] = true;
            }

            if ($newGrade < $expectedGrade - 1) {
                $result['warnings'][] = "Le nouveau grade est inférieur au grade calculé ({$expectedGrade})";
            }
        }

        // 4. Impact sur les enfants
        $childrenWithHigherGrade = $distributeur->children()
            ->where('etoiles_id', '>', $newGrade)
            ->count();

        if ($childrenWithHigherGrade > 0) {
            $result['warnings'][] = "{$childrenWithHigherGrade} enfant(s) ont un grade supérieur au nouveau grade";
            $result['impact']['children_with_higher_grade'] = $childrenWithHigherGrade;
        }

        // 5. Impact sur les bonus
        $result['impact']['bonus_recalculation'] = true;
        $result['impact']['affected_periods'] = [date('Y-m')];

        return $result;
    }

    /**
     * Valide un ajustement de cumuls
     */
    public function validateCumulAdjustment(
        LevelCurrent $levelCurrent,
        array $newValues
    ): array {
        $result = [
            'is_valid' => true,
            'warnings' => [],
            'blockers' => [],
            'impact' => []
        ];

        // 1. Vérifier la cohérence des valeurs
        if (isset($newValues['cumul_individuel']) && $newValues['cumul_individuel'] < 0) {
            $result['blockers'][] = "Le cumul individuel ne peut pas être négatif";
            $result['is_valid'] = false;
        }

        if (isset($newValues['cumul_collectif']) && $newValues['cumul_collectif'] < 0) {
            $result['blockers'][] = "Le cumul collectif ne peut pas être négatif";
            $result['is_valid'] = false;
        }

        // 2. Vérifier l'impact sur le grade
        if (isset($newValues['cumul_individuel'])) {
            $oldGrade = $levelCurrent->etoiles;
            $newGrade = $this->calculateGradeFromCumul($newValues['cumul_individuel']);

            if ($newGrade !== $oldGrade) {
                $result['warnings'][] = "Cet ajustement changera le grade de {$oldGrade} à {$newGrade}";
                $result['impact']['grade_change'] = [
                    'from' => $oldGrade,
                    'to' => $newGrade
                ];
            }
        }

        // 3. Impact sur la hiérarchie
        $parentId = $levelCurrent->distributeur->id_distrib_parent;
        if ($parentId && isset($newValues['cumul_individuel'])) {
            $result['impact']['parent_cumul_update_needed'] = true;
            $result['warnings'][] = "Les cumuls du parent devront être recalculés";
        }

        return $result;
    }

    /**
     * Exécute une modification approuvée
     */
    public function executeModification(ModificationRequest $request): array
    {
        if (!$request->canBeExecuted()) {
            return [
                'success' => false,
                'error' => 'Cette modification ne peut pas être exécutée'
            ];
        }

        DB::beginTransaction();
        try {
            $result = match($request->modification_type) {
                ModificationRequest::MOD_CHANGE_PARENT => $this->executeParentChange($request),
                ModificationRequest::MOD_MANUAL_GRADE => $this->executeGradeChange($request),
                ModificationRequest::MOD_ADJUST_CUMUL => $this->executeCumulAdjustment($request),
                ModificationRequest::MOD_REASSIGN_CHILDREN => $this->executeChildrenReassignment($request),
                default => throw new \Exception("Type de modification non supporté")
            };

            if ($result['success']) {
                $request->markAsExecuted($result);
                DB::commit();

                Log::info("Modification exécutée avec succès", [
                    'request_id' => $request->id,
                    'type' => $request->modification_type,
                    'entity' => $request->entity_type . '#' . $request->entity_id
                ]);
            } else {
                DB::rollBack();
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Erreur lors de l'exécution de la modification", [
                'request_id' => $request->id,
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
     * Exécute un changement de parent
     */
    private function executeParentChange(ModificationRequest $request): array
    {
        $distributeur = Distributeur::find($request->entity_id);
        if (!$distributeur) {
            return ['success' => false, 'error' => 'Distributeur non trouvé'];
        }

        $oldParentId = $distributeur->id_distrib_parent;
        $newParentId = $request->new_values['id_distrib_parent'];
        $currentPeriod = date('Y-m');

        DB::beginTransaction();
        try {
            // 1. Mettre à jour le parent dans la table distributeurs
            $distributeur->update(['id_distrib_parent' => $newParentId]);

            // 2. Mettre à jour dans level_currents pour la période courante
            LevelCurrent::where('distributeur_id', $distributeur->id)
                        ->where('period', $currentPeriod)
                        ->update(['id_distrib_parent' => $newParentId]);

            // 3. Recalculer les cumuls collectifs et totaux
            $recalcResult = $this->cumulService->recalculateAfterParentChange(
                $distributeur,
                $oldParentId,
                $newParentId,
                $currentPeriod
            );

            if (!$recalcResult['success']) {
                throw new \Exception($recalcResult['message']);
            }

            // 4. Recalculer les cumuls individuels des parents affectés
            if ($oldParentId) {
                $this->cumulService->recalculateIndividualCumul($oldParentId, $currentPeriod);
            }
            if ($newParentId) {
                $this->cumulService->recalculateIndividualCumul($newParentId, $currentPeriod);
            }

            DB::commit();

            // 5. Logger le changement
            Log::info("Changement de parent exécuté avec succès", [
                'distributeur_id' => $distributeur->id,
                'matricule' => $distributeur->distributeur_id,
                'ancien_parent' => $oldParentId,
                'nouveau_parent' => $newParentId,
                'period' => $currentPeriod,
                'cumuls_deplaces' => $recalcResult['amount_moved']
            ]);

            return [
                'success' => true,
                'details' => [
                    'distributeur_id' => $distributeur->id,
                    'old_parent_id' => $oldParentId,
                    'new_parent_id' => $newParentId,
                    'period_affected' => $currentPeriod,
                    'cumuls_moved' => $recalcResult['amount_moved'],
                    'timestamp' => now()
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Erreur lors du changement de parent", [
                'distributeur_id' => $distributeur->id,
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
     * Exécute un changement de grade
     */
    private function executeGradeChange(ModificationRequest $request): array
    {
        $distributeur = Distributeur::find($request->entity_id);
        if (!$distributeur) {
            return ['success' => false, 'error' => 'Distributeur non trouvé'];
        }

        $oldGrade = $distributeur->etoiles_id;
        $newGrade = $request->new_values['etoiles_id'];

        // 1. Mettre à jour le grade
        $distributeur->update(['etoiles_id' => $newGrade]);

        // 2. Mettre à jour dans level_current
        $currentPeriod = date('Y-m');
        LevelCurrent::where('distributeur_id', $distributeur->id)
                    ->where('period', $currentPeriod)
                    ->update(['etoiles' => $newGrade]);

        // 3. Créer un historique
        DB::table('grade_change_history')->insert([
            'distributeur_id' => $distributeur->id,
            'old_grade' => $oldGrade,
            'new_grade' => $newGrade,
            'change_type' => 'manual',
            'changed_by' => $request->approved_by_id,
            'reason' => $request->reason,
            'created_at' => now()
        ]);

        return [
            'success' => true,
            'details' => [
                'distributeur_id' => $distributeur->id,
                'old_grade' => $oldGrade,
                'new_grade' => $newGrade,
                'period' => $currentPeriod
            ]
        ];
    }

    /**
     * Exécute un ajustement de cumuls
     */
    private function executeCumulAdjustment(ModificationRequest $request): array
    {
        $levelCurrent = LevelCurrent::find($request->entity_id);
        if (!$levelCurrent) {
            return ['success' => false, 'error' => 'LevelCurrent non trouvé'];
        }

        $oldValues = [
            'cumul_individuel' => $levelCurrent->cumul_individuel,
            'cumul_collectif' => $levelCurrent->cumul_collectif,
            'new_cumul' => $levelCurrent->new_cumul
        ];

        // 1. Appliquer les nouveaux cumuls
        $levelCurrent->update($request->new_values);

        // 2. Recalculer le grade si nécessaire
        if (isset($request->new_values['cumul_individuel'])) {
            $newGrade = $this->calculateGradeFromCumul($request->new_values['cumul_individuel']);
            if ($newGrade !== $levelCurrent->etoiles) {
                $levelCurrent->update(['etoiles' => $newGrade]);

                // Mettre à jour aussi dans distributeurs
                Distributeur::where('id', $levelCurrent->distributeur_id)
                           ->update(['etoiles_id' => $newGrade]);
            }
        }

        // 3. Logger l'ajustement
        DB::table('cumul_adjustment_history')->insert([
            'level_current_id' => $levelCurrent->id,
            'distributeur_id' => $levelCurrent->distributeur_id,
            'period' => $levelCurrent->period,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($request->new_values),
            'adjusted_by' => $request->approved_by_id,
            'reason' => $request->reason,
            'created_at' => now()
        ]);

        return [
            'success' => true,
            'details' => [
                'level_current_id' => $levelCurrent->id,
                'distributeur_id' => $levelCurrent->distributeur_id,
                'adjustments' => $request->new_values
            ]
        ];
    }

    /**
     * Vérifie si un distributeur est descendant d'un autre
     */
    private function isDescendant(Distributeur $parent, Distributeur $potentialDescendant): bool
    {
        $current = $potentialDescendant;
        $maxDepth = 20; // Protection contre les boucles infinies
        $depth = 0;

        while ($current && $current->parent && $depth < $maxDepth) {
            if ($current->id_distrib_parent === $parent->id) {
                return true;
            }
            $current = $current->parent;
            $depth++;
        }

        return false;
    }

    /**
     * Calcule le nombre de niveaux affectés
     */
    private function getAffectedLevels(Distributeur $distributeur, Distributeur $newParent): int
    {
        // Calculer la profondeur de l'ancienne branche
        $oldDepth = $this->calculateBranchDepth($distributeur);

        // Calculer la nouvelle profondeur
        $newParentDepth = $this->getDistributorDepth($newParent);

        return abs($oldDepth + $newParentDepth);
    }

    /**
     * Calcule la profondeur d'une branche
     */
    private function calculateBranchDepth(Distributeur $distributeur): int
    {
        $maxDepth = 0;

        $children = $distributeur->children;
        foreach ($children as $child) {
            $childDepth = 1 + $this->calculateBranchDepth($child);
            $maxDepth = max($maxDepth, $childDepth);
        }

        return $maxDepth;
    }

    /**
     * Obtient la profondeur d'un distributeur dans l'arbre
     */
    private function getDistributorDepth(Distributeur $distributeur): int
    {
        $depth = 0;
        $current = $distributeur;

        while ($current->parent) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    /**
     * Calcule le grade basé sur le cumul individuel
     */
    private function calculateGradeFromCumul(float $cumulIndividuel): int
    {
        // À adapter selon vos règles métier
        // Exemple simplifié :
        if ($cumulIndividuel >= 1000) return 4;
        if ($cumulIndividuel >= 200) return 3;
        if ($cumulIndividuel >= 100) return 2;
        return 1;
    }

    /**
     * Calcule le grade attendu basé sur les performances
     */
    private function calculateExpectedGrade(LevelCurrent $level): int
    {
        return $this->calculateGradeFromCumul($level->cumul_individuel);
    }

    /**
     * Recalcule les cumuls collectifs après un changement
     */
    private function recalculateCollectiveCumuls(?int $distributeurId): void
    {
        if (!$distributeurId) return;

        // TODO: Implémenter le recalcul des cumuls collectifs
        // Ceci devrait parcourir l'arbre et recalculer tous les cumuls
        Log::info("Recalcul des cumuls collectifs nécessaire pour distributeur #{$distributeurId}");
    }

    /**
     * Exécute la réassignation d'enfants
     */
    private function executeChildrenReassignment(ModificationRequest $request): array
    {
        // TODO: Implémenter la réassignation d'enfants
        return [
            'success' => true,
            'details' => [
                'message' => 'Réassignation d\'enfants à implémenter'
            ]
        ];
    }
}
