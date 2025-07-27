<?php

namespace App\Services; // Ou App\Http\Controllers, etc.
use App\Models\Distributeur;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Ajouté pour la vérification d'unicité

class EternalHelperMatriculeBased // Nom de classe modifié pour clarté
{
    // Propriété pour stocker la descendance chargée [parent_PRIMARY_KEY_ID => Collection [child_MATRICULE => childObject]]
    // Note: La clé externe est l'ID primaire, la clé interne est le matricule
    private ?Collection $descendantsMap = null;
    private ?int $loadedRootId = null; // Toujours l'ID primaire de la racine initiale

    /**
     * Orchestre une vérification de qualification multi-niveaux basée sur les MATRICULES.
     * 1. Compte les branches qualifiées au niveau N.
     * 2. Pour les branches échouées, compte celles qui se qualifient au niveau N-1.
     * 3. Liste les MATRICULES des enfants directs dont les branches ont échoué aux deux niveaux.
     *
     * @param int $rootParentId     L'ID PRIMAIRE du distributeur racine initial.
     * @param int $initialLevel     Le niveau requis pour la première passe (N).
     * @return array                Résultat avec comptes séparés et MATRICULES non qualifiés.
     *                              Ex: [
     *                                  'level_n_qualified_count' => 2,
     *                                  'level_n_minus_1_qualified_count' => 2,
     *                                  'finally_unqualified_root_matricules' => [D.matricule]
     *                              ]
     */
    public function checkMultiLevelQualificationSeparateCountsMatricule(int $rootParentId, int $initialLevel): array
    {
        // Vérification préliminaire de l'unicité du matricule (ESSENTIEL)
        if (!$this->checkMatriculeUniqueness()) {
             throw new \RuntimeException("La colonne 'distributeur_id' n'est pas unique dans la table 'distributeurs'. Le traitement basé sur les matricules est impossible.");
        }

        $levelN = max(1, $initialLevel);
        $levelNMinus1 = max(1, $levelN - 1);

        Log::info("Début de la vérification multi-niveaux SÉPARÉE (basée MATRICULE) pour Parent ID: {$rootParentId}, N >= {$levelN}, N-1 >= {$levelNMinus1}");

        // --- Étape 1 : Vérification au niveau N ---
        // Note: La fonction suivante charge la descendance si nécessaire
        $step1ResultsArray = $this->getBranchQualificationStatusMatricule($rootParentId, $levelN);

        // Séparer les résultats de l'étape 1
        $processedStep1 = $this->processQualificationResultsMatricule($step1ResultsArray);
        $levelNQualifiedCount = $processedStep1['qualified_count'];
        $step1UnqualifiedRootMatricules = $processedStep1['unqualified_root_matricules']; // MATRICULES des enfants directs à revérifier

        Log::info("Étape 1 (Niveau >= {$levelN}): {$levelNQualifiedCount} branche(s) qualifiée(s). " . count($step1UnqualifiedRootMatricules) . " branche(s) à revérifier au niveau inférieur.");

        // --- Étape 2 : Revérification au niveau N-1 pour les branches échouées ---
        $levelNMinus1QualifiedCount = 0;
        $finallyUnqualifiedRootMatricules = []; // MATRICULES des branches échouant même à l'étape 2

        Log::info("Étape 2: Revérification des branches non qualifiées au Niveau >= {$levelNMinus1}");

        if (empty($step1UnqualifiedRootMatricules)) {
            Log::info("Aucune branche à revérifier.");
        } else {
            foreach ($step1UnqualifiedRootMatricules as $childMatriculeToRecheck) {
                Log::debug("Revérification de la branche commençant par l'enfant MATRICULE: {$childMatriculeToRecheck} au niveau {$levelNMinus1}");

                // Relancer la vérification pour cette branche spécifique au niveau N-1
                // On a besoin de l'ID primaire pour démarrer la recherche des enfants de cet enfant
                 $childData = $this->findDistributorByMatricule($childMatriculeToRecheck);

                 if ($childData) {
                    // On utilise l'ID primaire pour lancer getBranchQualificationStatus
                     $step2BranchResults = $this->getBranchQualificationStatusMatricule($childData->id, $levelNMinus1);
                     $isQualifiedStep2 = in_array(1, $step2BranchResults, true);

                     if ($isQualifiedStep2) {
                         Log::debug("Branche enfant MATRICULE {$childMatriculeToRecheck}: QUALIFIÉE au niveau {$levelNMinus1}.");
                         $levelNMinus1QualifiedCount++;
                     } else {
                         Log::debug("Branche enfant MATRICULE {$childMatriculeToRecheck}: NON QUALIFIÉE même au niveau {$levelNMinus1}.");
                         $finallyUnqualifiedRootMatricules[] = $childMatriculeToRecheck; // Échec final
                     }
                 } else {
                     Log::error("Impossible de retrouver l'ID primaire pour le matricule {$childMatriculeToRecheck} lors de la revérification. Branche considérée comme non qualifiée.");
                     $finallyUnqualifiedRootMatricules[] = $childMatriculeToRecheck; // Considérer comme échec
                 }
            }
        }

        // --- Étape 3 : Consolidation et Retour ---
        Log::info("Fin de la vérification multi-niveaux (basée MATRICULE). N: {$levelNQualifiedCount}. N-1: {$levelNMinus1QualifiedCount}. Échecs Finaux: " . count($finallyUnqualifiedRootMatricules));

        return [
            'level_n_qualified_count'       => $levelNQualifiedCount,
            'level_n_minus_1_qualified_count' => $levelNMinus1QualifiedCount,
            'finally_unqualified_root_matricules'  => $finallyUnqualifiedRootMatricules, // Retourne les matricules
        ];
    }

    /**
     * Vérifie, pour chaque branche enfant directe, si le niveau requis est atteint (>=)
     * soit par l'enfant direct, soit par au moins un descendant.
     * Retourne 1 si qualifié, MATRICULE enfant si non qualifié.
     *
     * @param int $rootParentId     L'ID PRIMAIRE du distributeur racine.
     * @param int $requiredLevel    Le niveau minimum requis (>=).
     * @return array                Tableau de 1s et MATRICULES enfants.
     */
    public function getBranchQualificationStatusMatricule(int $rootParentId, int $requiredLevel): array
    {
        $comparisonLevel = max(1, $requiredLevel);
        // Charge la descendance basée sur l'ID primaire parent
        $this->loadAllDescendantsMatricule($rootParentId);
        // Récupère les enfants directs associés à l'ID primaire parent
        $directChildren = $this->descendantsMap->get($rootParentId);

        if (!$directChildren || $directChildren->isEmpty()) return [];

        $branchResults = [];
        foreach ($directChildren as $childMatricule => $child) { // La clé est maintenant le matricule
            $isBranchQualified = false;
            if ($child->etoiles_id >= $comparisonLevel) {
                $isBranchQualified = true;
            } else {
                // Lancer la vérification de la sous-branche en utilisant le MATRICULE de l'enfant
                if ($this->checkSubtreeForLevelOrHigherMatricule($childMatricule, $comparisonLevel)) {
                    $isBranchQualified = true;
                }
            }
            // Retourne 1 ou le MATRICULE de l'enfant
            $branchResults[] = $isBranchQualified ? 1 : $childMatricule;
        }
        return $branchResults;
    }

     /**
     * Sépare les résultats en compte qualifié et liste des MATRICULES non qualifiés.
     *
     * @param array $branchResults Tableau de 1s et MATRICULES.
     * @return array ['qualified_count' => int, 'unqualified_root_matricules' => array].
     */
    public function processQualificationResultsMatricule(array $branchResults): array
    {
        // Compte les 1
        $qualifiedCount = count(array_filter($branchResults, fn($value) => $value === 1));
        // Filtre pour garder les matricules (ce qui n'est pas 1)
        $unqualifiedRootMatricules = array_values(array_filter($branchResults, fn($value) => $value !== 1));
        return [
            'qualified_count' => $qualifiedCount,
            'unqualified_root_matricules' => $unqualifiedRootMatricules, // Nom de clé modifié
        ];
    }

    /**
     * Fonction récursive (en mémoire) basée sur les MATRICULES pour vérifier si un nœud
     * ou l'un de ses descendants atteint OU DÉPASSE le niveau cible.
     *
     * @param int|string $parentMatricule LE MATRICULE du nœud de départ.
     * @param int $targetLevel      Le niveau minimum (>=).
     * @return bool                True si trouvé, False sinon.
     */
    protected function checkSubtreeForLevelOrHigherMatricule(int|string $parentMatricule, int $targetLevel): bool
    {
        // On a besoin de l'ID primaire pour trouver les enfants dans la map
        $parentData = $this->findDistributorByMatricule($parentMatricule);
        if (!$parentData) {
             Log::warning("checkSubtree (Matricule): Impossible de trouver l'ID pour le matricule parent {$parentMatricule}.");
             return false; // Ne peut pas trouver les enfants
        }
        $parentId = $parentData->id; // ID Primaire

        // Vérifier si ce parent a des enfants dans la map
        if ($this->descendantsMap === null || !$this->descendantsMap->has($parentId)) {
            return false; // Pas d'enfants chargés pour ce parent
        }

        $children = $this->descendantsMap->get($parentId); // Obtient Collection [childMatricule => childObject]

        if ($children->isEmpty()){
             return false;
        }

        // Parcourir les enfants (la clé est le matricule)
        foreach ($children as $childMatricule => $child) {
            if ($child->etoiles_id >= $targetLevel) {
                return true;
            }
            // Appel récursif avec le MATRICULE de l'enfant
            if ($this->checkSubtreeForLevelOrHigherMatricule($childMatricule, $targetLevel)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Charge tous les descendants et les organise dans une Map
     * [parent_PRIMARY_KEY_ID => Collection [child_MATRICULE => childObject]].
     * C'est la modification clé.
     *
     * @param int $rootParentId L'ID PRIMAIRE de la racine.
     */
    protected function loadAllDescendantsMatricule(int $rootParentId): void
    {
        if ($this->descendantsMap !== null && $this->loadedRootId === $rootParentId) {
            return;
        }
        Log::info("(Matricule) Chargement de la descendance pour le parent ID: {$rootParentId}");

        // La map externe utilise toujours l'ID primaire parent comme clé pour la recherche facile des enfants
        $this->descendantsMap = collect();
        $this->loadedRootId = $rootParentId;
        $idsToLoad = [$rootParentId]; // Commence avec l'ID primaire de la racine
        $loadedParentIds = []; // IDs primaires des parents dont les enfants ont été chargés
        $iteration = 0;
        $maxIterations = 100;

        while (!empty($idsToLoad) && $iteration < $maxIterations) {
            $iteration++;
            $currentBatchParentIds = array_diff($idsToLoad, $loadedParentIds);
            $idsToLoad = [];
            if (empty($currentBatchParentIds)) break;
            $loadedParentIds = array_merge($loadedParentIds, $currentBatchParentIds);

            // Récupère les enfants en sélectionnant l'ID, le parent_ID, le MATRICULE et les étoiles
            $childrenBatch = Distributeur::whereIn('id_distrib_parent', $currentBatchParentIds)
                ->select('id', 'id_distrib_parent', 'distributeur_id', 'etoiles_id') // AJOUT de distributeur_id
                ->get();

            if ($childrenBatch->isEmpty()) continue;
            foreach ($childrenBatch as $child) {
                 // Vérifier si le matricule enfant est valide (non null/vide)
                 if (empty($child->distributeur_id)) {
                     Log::warning("(Matricule Load) Enfant ID {$child->id} a un matricule vide ou null. Il sera ignoré.");
                     continue;
                 }

                // Ajouter à la map sous l'ID primaire du parent
                if (!$this->descendantsMap->has($child->id_distrib_parent)) {
                    $this->descendantsMap->put($child->id_distrib_parent, collect());
                }
                // Utiliser le MATRICULE enfant comme clé interne de la collection enfant
                $this->descendantsMap->get($child->id_distrib_parent)->put($child->distributeur_id, $child);

                // Ajouter l'ID primaire de l'enfant pour charger ses propres enfants au prochain tour
                if (!in_array($child->id, $loadedParentIds)) {
                    $idsToLoad[] = $child->id;
                }
            }
            $idsToLoad = array_unique($idsToLoad);
        }
        if ($iteration >= $maxIterations) {
             Log::error("(Matricule Load) Limite d'itérations atteinte ({$maxIterations}) pour parent ID: {$rootParentId}.");
        }
        // Log::info("(Matricule) Descendance chargée. Nombre de parents dans la map: " . $this->descendantsMap->count());
    }

    /**
     * Helper pour retrouver un distributeur (id, etoiles_id) par son matricule.
     * Essentiel pour la navigation basée sur les matricules.
     * Pourrait bénéficier d'une mise en cache si appelé très souvent.
     */
    protected function findDistributorByMatricule(int|string $matricule): ?object
    {
         // Essayer de trouver dans la map déjà chargée si possible (optimisation)
         // Cela nécessite de stocker TOUS les nœuds, pas juste la structure parent->enfants
         // Solution simple : requête DB directe
         return Distributeur::where('distributeur_id', $matricule)
                            ->select('id', 'etoiles_id') // Sélectionner les données nécessaires
                            ->first();
    }

    /**
     * Vérifie si la colonne distributeur_id a une contrainte unique.
     * Requis pour que la logique basée sur le matricule soit fiable.
     */
    protected function checkMatriculeUniqueness(): bool
    {
        try {
            $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('distributeurs');
            foreach ($indexes as $index) {
                if ($index->isUnique() && count($index->getColumns()) === 1 && $index->getColumns()[0] === 'distributeur_id') {
                    return true;
                }
            }
             Log::error("La colonne 'distributeur_id' doit avoir une contrainte UNIQUE pour utiliser la logique basée sur les matricules.");
            return false;
        } catch (\Exception $e) {
             Log::error("Erreur lors de la vérification de l'unicité de 'distributeur_id': " . $e->getMessage());
            return false; // Assumer non unique en cas d'erreur
        }
    }


     // --- Exemple d'utilisation ---

    public function demonstrateMultiLevelCheckMatricule(int $distributorPrimaryId) // Prend l'ID primaire
    {
        $initialLevel = 4;
        try {
             $results = $this->checkMultiLevelQualificationSeparateCountsMatricule($distributorPrimaryId, $initialLevel);

             $levelN = max(1, $initialLevel);
             $levelNMinus1 = max(1, $levelN - 1);

             echo "Vérification Multi-Niveau (Basée MATRICULE) pour Distributeur ID Primaire: {$distributorPrimaryId}<br>";
             echo "Nombre de Branches Qualifiées au Niveau >= {$levelN} : {$results['level_n_qualified_count']}<br>";
             echo "Nombre de Branches (parmi les précédentes échouées) Qualifiées au Niveau >= {$levelNMinus1} : {$results['level_n_minus_1_qualified_count']}<br>";

             if (!empty($results['finally_unqualified_root_matricules'])) {
                 echo "Branches commençant par les MATRICULES Enfants suivants n'ont pas été qualifiées même au niveau {$levelNMinus1} : " . implode(', ', $results['finally_unqualified_root_matricules']);
             } else {
                 echo "Toutes les branches initiales se sont qualifiées à l'un des deux niveaux.";
             }

             dd($results);

         } catch (\RuntimeException $e) {
              echo "ERREUR : " . $e->getMessage();
              // Gérer l'erreur (par exemple, l'absence d'index unique)
         }
    }

} // Fin de la classe
