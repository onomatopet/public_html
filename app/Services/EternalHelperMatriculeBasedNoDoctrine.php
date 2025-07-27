<?php

namespace App\Services;

use App\Models\Distributeur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
// Retirer: use Illuminate\Support\Facades\Schema; // Plus nécessaire ici
// Retirer: use Illuminate\Support\Facades\DB; // Plus utilisé directement ici pour le schéma

class EternalHelperMatriculeBasedNoDoctrine
{
    private ?Collection $descendantsMap = null;
    private ?int $loadedRootId = null;
    private ?Collection $matriculeToIdMap = null;
    private ?Collection $distributorObjectMap = null;

    /**
     * Orchestre une vérification de qualification multi-niveaux basée sur les MATRICULES.
     *
     * @param int $rootParentId     L'ID PRIMAIRE du distributeur racine initial.
     * @param int $initialLevel     Le niveau requis pour la première passe (N).
     * @return array                Résultat avec comptes séparés et MATRICULES non qualifiés.
     * @throws \RuntimeException    Si la map de descendance ne peut pas être chargée.
     */
    public function checkMultiLevelQualificationSeparateCountsMatricule(int $rootParentId, int $initialLevel): array
    {
        $levelN = max(1, $initialLevel);
        $levelNMinus1 = max(1, $levelN - 1);

        Log::info("HELPER: Début vérif multi-niveaux MATRICULE pour Parent ID: {$rootParentId}, N >= {$levelN}, N-1 >= {$levelNMinus1}");

        // Le chargement est maintenant fait une seule fois par l'appelant
        // et les maps sont passées ou accessibles via $this.
        // S'assurer que les maps sont chargées (fait par la classe service appelante)
        if ($this->descendantsMap === null || $this->matriculeToIdMap === null || $this->distributorObjectMap === null || $this->loadedRootId !== $rootParentId) {
            // C'est à la classe appelante (AchatBasedAdvancementMatriculeService) de s'assurer que
            // loadAllDescendantsMatricule est appelé pour le bon rootParentId
            // ou de passer les maps en argument, ce qui serait plus propre.
            // Pour l'instant, on suppose qu'elles sont correctement settées par l'instance.
            // Si ce n'est pas le cas, la logique échouera plus bas.
            // On pourrait ajouter ici un appel à $this->loadAllDescendantsMatricule($rootParentId) si cette classe
            // est utilisée de manière autonome et doit gérer son propre chargement.
            // Mais dans notre flux actuel, AchatBasedAdvancementMatriculeService le fait.
            Log::warning("HELPER: Les maps de descendants ne semblent pas être chargées ou pas pour le bon root ID {$rootParentId}. Résultat peut être incorrect.");
            // Considerer de charger ici si ce helper est utilisé indépendamment:
            // $this->loadAllDescendantsMatricule($rootParentId);
             if ($this->descendantsMap === null || $this->matriculeToIdMap === null || $this->distributorObjectMap === null) {
                 throw new \RuntimeException("Les maps de descendants sont requises mais non initialisées dans EternalHelper.");
             }
        }


        // --- Étape 1 : Vérification au niveau N ---
        // Cette fonction utilise maintenant les maps de l'instance
        $step1ResultsArray = $this->getBranchQualificationStatusMatricule($rootParentId, $levelN);
        $processedStep1 = $this->processQualificationResultsMatricule($step1ResultsArray);
        $levelNQualifiedCount = $processedStep1['qualified_count'];
        $step1UnqualifiedRootMatricules = $processedStep1['unqualified_root_matricules'];

        Log::info("HELPER: Étape 1 (N >= {$levelN}): {$levelNQualifiedCount} qualifiée(s). " . count($step1UnqualifiedRootMatricules) . " à revérifier.");

        // --- Étape 2 : Revérification au niveau N-1 ---
        $levelNMinus1QualifiedCount = 0;
        $finallyUnqualifiedRootMatricules = [];

        Log::info("HELPER: Étape 2: Revérification (N >= {$levelNMinus1})");

        if (!empty($step1UnqualifiedRootMatricules)) {
            foreach ($step1UnqualifiedRootMatricules as $childMatriculeToRecheck) {
                $isQualifiedStep2 = false;
                $childPrimaryId = $this->matriculeToIdMap->get($childMatriculeToRecheck);
                $childObject = $childPrimaryId ? $this->distributorObjectMap->get($childPrimaryId) : null;

                if ($childObject) {
                    if ($childObject->etoiles_id >= $levelNMinus1) {
                        $isQualifiedStep2 = true;
                    } else {
                        if ($this->checkSubtreeForLevelOrHigherMatricule($childMatriculeToRecheck, $levelNMinus1)) {
                             $isQualifiedStep2 = true;
                        }
                    }
                } else {
                     Log::warning("HELPER: Objet Distributeur introuvable pour matricule {$childMatriculeToRecheck} dans map interne (revérification).");
                }

                if ($isQualifiedStep2) {
                    $levelNMinus1QualifiedCount++;
                } else {
                    $finallyUnqualifiedRootMatricules[] = $childMatriculeToRecheck;
                }
            }
        }

        Log::info("HELPER: Fin vérif multi-niveaux MATRICULE. N: {$levelNQualifiedCount}. N-1: {$levelNMinus1QualifiedCount}. Échecs Finaux: " . count($finallyUnqualifiedRootMatricules));
        return [
            'level_n_qualified_count'         => $levelNQualifiedCount,
            'level_n_minus_1_qualified_count' => $levelNMinus1QualifiedCount,
            'finally_unqualified_root_matricules'  => $finallyUnqualifiedRootMatricules,
        ];
    }

    private function getBranchQualificationStatusMatricule(int $rootParentId, int $requiredLevel): array
    {
        $comparisonLevel = max(1, $requiredLevel);
        $directChildren = $this->descendantsMap->get($rootParentId); // Récupère Collection [matricule => childObject]

        if (!$directChildren || $directChildren->isEmpty()) return [];

        $branchResults = [];
        foreach ($directChildren as $childMatricule => $child) {
            $isBranchQualified = false;
            if ($child->etoiles_id >= $comparisonLevel) {
                $isBranchQualified = true;
            } else {
                if ($this->checkSubtreeForLevelOrHigherMatricule($childMatricule, $comparisonLevel)) {
                    $isBranchQualified = true;
                }
            }
            $branchResults[] = $isBranchQualified ? 1 : $childMatricule;
        }
        return $branchResults;
    }

    private function processQualificationResultsMatricule(array $branchResults): array
    {
        $qualifiedCount = count(array_filter($branchResults, fn($value) => $value === 1));
        $unqualifiedRootMatricules = array_values(array_filter($branchResults, fn($value) => $value !== 1));
        return [
            'qualified_count' => $qualifiedCount,
            'unqualified_root_matricules' => $unqualifiedRootMatricules,
        ];
     }

    private function checkSubtreeForLevelOrHigherMatricule(int|string $parentMatricule, int $targetLevel): bool
    {
         if ($this->descendantsMap === null || $this->matriculeToIdMap === null) return false;
         $parentId = $this->matriculeToIdMap->get($parentMatricule);
         if (!$parentId) return false;
         if (!$this->descendantsMap->has($parentId)) return false;
         $children = $this->descendantsMap->get($parentId);
         if ($children->isEmpty()) return false;

         foreach ($children as $childMatricule => $child) {
             if ($child->etoiles_id >= $targetLevel) return true;
             if ($this->checkSubtreeForLevelOrHigherMatricule($childMatricule, $targetLevel)) return true;
         }
         return false;
    }

    /**
     * Méthode PUBLIQUE pour permettre à AchatBasedAdvancementMatriculeService
     * de charger les données dans CETTE instance de EternalHelper.
     * Doit être appelée par AchatBasedAdvancementMatriculeService.
     */
    public function primeDataForAllDescendants(int $rootParentId): bool
    {
        // Si déjà chargé pour ce root, ne rien faire.
        if ($this->descendantsMap !== null && $this->matriculeToIdMap !== null && $this->distributorObjectMap !== null && $this->loadedRootId === $rootParentId) {
            Log::info("HELPER: Data for root {$rootParentId} already primed.");
            return true;
        }

        Log::info("HELPER: Priming data for root ID: {$rootParentId} in EternalHelper instance.");
        $this->descendantsMap = collect();
        $this->matriculeToIdMap = collect();
        $this->distributorObjectMap = collect();
        $this->loadedRootId = $rootParentId;
        $idsToLoad = [$rootParentId];
        $loadedParentIds = [];
        $iteration = 0;
        $maxIterations = 100;

        while (!empty($idsToLoad) && $iteration < $maxIterations) {
            $iteration++;
            $currentBatchParentIds = array_diff($idsToLoad, $loadedParentIds);
            $idsToLoad = [];
            if (empty($currentBatchParentIds)) break;
            $loadedParentIds = array_merge($loadedParentIds, $currentBatchParentIds);

            $childrenBatch = Distributeur::whereIn('id_distrib_parent', $currentBatchParentIds)
                ->select('id', 'id_distrib_parent', 'distributeur_id', 'etoiles_id')
                ->get();

            if ($childrenBatch->isEmpty()) continue;

            foreach ($childrenBatch as $child) {
                 if (empty($child->distributeur_id)) {
                     Log::warning("HELPER (Load) Enfant ID {$child->id} a matricule vide/null. Ignoré.");
                     continue;
                 }
                 if ($this->matriculeToIdMap->has($child->distributeur_id) && $this->matriculeToIdMap->get($child->distributeur_id) !== $child->id) {
                      $msg = "HELPER (Load) DOUBLON DE MATRICULE DÉTECTÉ: {$child->distributeur_id}";
                      Log::critical($msg);
                      throw new \RuntimeException($msg);
                 }

                 $this->matriculeToIdMap->put($child->distributeur_id, $child->id);
                 $this->distributorObjectMap->put($child->id, $child);

                 if (!$this->descendantsMap->has($child->id_distrib_parent)) {
                     $this->descendantsMap->put($child->id_distrib_parent, collect());
                 }
                 $this->descendantsMap->get($child->id_distrib_parent)->put($child->distributeur_id, $child);

                 if (!in_array($child->id, $loadedParentIds)) {
                     $idsToLoad[] = $child->id;
                 }
            }
            $idsToLoad = array_unique($idsToLoad);
        }

        if ($iteration >= $maxIterations) {
             Log::error("HELPER (Load) Limite {$maxIterations} itérations atteinte pour Parent ID {$rootParentId}.");
             return false; // Indiquer un échec de chargement
        }
        Log::info("HELPER (Load) Descendance chargée. Maps: Desc({$this->descendantsMap->count()}), Matr->ID({$this->matriculeToIdMap->count()}), ID->Obj({$this->distributorObjectMap->count()}).");
        return true; // Succès
    }
}
