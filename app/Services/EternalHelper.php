<?php

namespace App\Services;
use Illuminate\Support\Collection;
use App\Models\Achat;
use App\Models\Distributeur;
use App\Models\Etoile;
use App\Models\Level_current_test;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class EternalHelper {

    //ANCIENNE IMPLEMENTATION
    // Propriété pour stocker la descendance chargée
    private ?Collection $descendantsMap = null;
    private ?int $loadedRootId = null; // Pour savoir pour quel root la map a été chargée

    /**
     * Vérifie, pour chaque branche enfant directe d'un distributeur racine, si le niveau requis
     * est atteint soit par l'enfant direct LUI-MÊME, soit par AU MOINS UN descendant dans cette branche.
     * Utilise le même niveau requis (>=) pour la vérification directe et la recherche dans la descendance.
     *
     * @param int $rootParentId     L'ID (primaire) du distributeur racine pour la vérification.
     * @param int $requiredLevel    Le niveau d'étoiles minimum requis (>=).
     * @return array                Un tableau de 1s et 0s, un pour chaque branche enfant directe,
     *                              indiquant si la condition a été remplie (1) ou non (0).
     */
    /*
    public function getBranchLevelStatus(int $rootParentId, int $requiredLevel): array
    {
        // 1. Valider et définir le niveau de comparaison (identique pour direct et descendance)
        $comparisonLevel = max(1, $requiredLevel); // Assurer au moins le niveau 1

        // 2. Charger/Recharger la descendance si nécessaire
        // Cette fonction charge tous les descendants et les organise en mémoire
        $this->loadAllDescendants($rootParentId);

        // 3. Obtenir les enfants directs depuis la map chargée
        $directChildren = $this->descendantsMap->get($rootParentId);

        // Gérer le cas où il n'y a pas d'enfants directs
        if (!$directChildren || $directChildren->isEmpty()) {
            Log::info("Aucun enfant direct trouvé pour le parent ID: {$rootParentId}.");
            return []; // Pas de branches, donc résultat vide
        }
        Log::info("Vérification des " . $directChildren->count() . " enfants directs du parent ID: {$rootParentId} pour le niveau >= {$comparisonLevel}.");

        // 4. Traiter chaque enfant direct (chaque branche)
        $branchResults = [];
        foreach ($directChildren as $child) {
            // 4a. Vérifier si l'enfant direct atteint le niveau
            if ($child->etoiles_id >= $comparisonLevel) {
                // Oui, la condition est remplie pour cette branche par l'enfant direct.
                Log::debug("Branche enfant ID {$child->id}: Niveau atteint DIRECTEMENT (>= {$comparisonLevel}). Résultat: 1");
                $branchResults[] = 1;
            } else {
                // 4b. Non, l'enfant direct n'atteint pas le niveau.
                // Vérifier récursivement si AU MOINS UN descendant dans sa sous-arborescence atteint le niveau.
                Log::debug("Branche enfant ID {$child->id}: Niveau NON atteint directement (< {$comparisonLevel}). Vérification de la descendance...");
                $foundInDownline = $this->checkSubtreeForLevelOrHigher($child->id, $comparisonLevel);

                if ($foundInDownline) {
                    Log::debug("Branche enfant ID {$child->id}: Niveau atteint DANS LA DESCENDANCE (>= {$comparisonLevel}). Résultat: 1");
                    $branchResults[] = 1; // Condition remplie par un descendant.
                } else {
                    Log::debug("Branche enfant ID {$child->id}: Niveau NON atteint dans la descendance (>= {$comparisonLevel}). Résultat: 0");
                    $branchResults[] = 0; // Condition non remplie pour cette branche.
                }
            }
        }

        // 5. Retourner le tableau des résultats pour chaque branche
        Log::info("Résultats finaux pour les branches du parent ID {$rootParentId}: " . json_encode($branchResults));
        return $branchResults;
    }

    /**
     * Fonction récursive (en mémoire) pour vérifier si un nœud ou l'un de ses descendants
     * dans la map préchargée atteint OU DÉPASSE le niveau cible.
     * Retourne true dès qu'un correspondant est trouvé.
     *
     * @param int $parentId       L'ID du nœud de départ pour la recherche dans la sous-arborescence.
     * @param int $targetLevel    Le niveau d'étoiles minimum à rechercher (>=).
     * @return bool              True si trouvé (dans ce nœud ou en dessous), False sinon.
     */
    /*
    protected function checkSubtreeForLevelOrHigher(int $parentId, int $targetLevel): bool
    {
        // Récupérer les enfants directs de ce parent depuis la map
        $children = $this->descendantsMap->get($parentId);

        // S'il n'y a pas d'enfants pour ce parent dans la map, la condition ne peut pas être remplie plus bas.
        if (!$children || $children->isEmpty()) {
            return false;
        }

        // Parcourir les enfants de ce niveau
        foreach ($children as $child) {
            // Vérifier si cet enfant atteint ou dépasse le niveau
            if ($child->etoiles_id >= $targetLevel) {
                // Trouvé ! Pas besoin de chercher plus loin dans cette sous-branche ou les autres enfants de CE niveau.
                return true;
            }
            // Si l'enfant actuel n'atteint pas le niveau, explorer sa propre sous-branche récursivement.
            // Si la recherche récursive retourne true, cela signifie qu'un descendant a été trouvé.
            if ($this->checkSubtreeForLevelOrHigher($child->id, $targetLevel)) {
                // Trouvé dans une sous-branche ! On peut arrêter la recherche.
                return true;
            }
        }

        // Si on a parcouru tous les enfants et leurs sous-branches sans trouver le niveau requis.
        return false;
    }

    /**
     * Charge tous les descendants (directs et indirects) d'un parent donné
     * et les organise dans une Map [parentId => Collection<children>].
     * Utilise une approche itérative. Recharge si le rootParentId est différent.
     *
     * @param int $rootParentId
     */
    /*
    protected function loadAllDescendants(int $rootParentId): void
    {
        // Si la map est déjà chargée pour le bon parent, ne rien faire
        if ($this->descendantsMap !== null && $this->loadedRootId === $rootParentId) {
             Log::debug("Descendance pour le parent ID {$rootParentId} déjà chargée.");
            return;
        }

        Log::info("Chargement de la descendance pour le parent ID: {$rootParentId}");

        $this->descendantsMap = collect(); // Map: parentId => Collection [childId => childObject]
        $this->loadedRootId = $rootParentId; // Mémoriser pour quel parent on charge
        $idsToLoad = [$rootParentId]; // Commencer par charger les enfants directs de la racine
        $loadedIds = []; // Garder une trace des parents dont les enfants ont été chargés
        $iteration = 0;
        $maxIterations = 100; // Sécurité

        while (!empty($idsToLoad) && $iteration < $maxIterations) {
            $iteration++;
            // Charger les enfants pour les parents qui n'ont pas encore été chargés
            $currentBatchIds = array_diff($idsToLoad, $loadedIds);
            $idsToLoad = []; // Réinitialiser pour les enfants trouvés dans ce batch

            if (empty($currentBatchIds)) {
                break; // Plus rien de nouveau à charger
            }

            // Marquer ces parents comme chargés
            $loadedIds = array_merge($loadedIds, $currentBatchIds);
            // Log::debug("Itération {$iteration}: Chargement enfants pour parents: " . implode(', ', $currentBatchIds)); // Verbeux

            // Requête pour obtenir les enfants
            $childrenBatch = Distributeur::whereIn('id_distrib_parent', $currentBatchIds)
                ->select('id', 'id_distrib_parent', 'etoiles_id') // Colonnes nécessaires
                ->get();

            if ($childrenBatch->isEmpty()) {
                // Log::debug("Itération {$iteration}: Aucun enfant trouvé."); // Verbeux
                continue; // Pas d'enfants pour ces parents, passer au prochain batch
            }
            // Log::debug("Itération {$iteration}: Enfants trouvés: " . $childrenBatch->count()); // Verbeux

            // Organiser les enfants trouvés dans la map et préparer le prochain niveau
            foreach ($childrenBatch as $child) {
                // Ajouter à la map sous le bon parent
                if (!$this->descendantsMap->has($child->id_distrib_parent)) {
                    $this->descendantsMap->put($child->id_distrib_parent, collect());
                }
                $this->descendantsMap->get($child->id_distrib_parent)->put($child->id, $child);

                // Préparer le chargement des petits-enfants (si pas déjà chargés/prévus)
                if (!in_array($child->id, $loadedIds)) {
                    $idsToLoad[] = $child->id;
                }
            }
            $idsToLoad = array_unique($idsToLoad); // Éviter les doublons
        } // Fin While

        if ($iteration >= $maxIterations) {
             Log::error("Limite d'itérations atteinte ({$maxIterations}) lors du chargement de la descendance pour le parent ID: {$rootParentId}. Vérifiez les données pour des boucles ou augmentez la limite.");
        }

        Log::info("Descendance chargée. Nombre de parents dans la map: " . $this->descendantsMap->count());
    }

    */
     // --- Exemple d'utilisation dans un contrôleur ou une autre méthode ---
    /*
    public function checkDistributorBranches(int $distributorId)
    {
        $requiredLevel = 4; // Le niveau 4 étoiles
        $eternalHelper = new EternalHelper(); // Ou injectez-le via le constructeur

        // Obtenir le tableau [1, 0, 1, ...] pour les branches enfants de $distributorId
        $branchStatuses = $eternalHelper->getBranchLevelStatus($distributorId, $requiredLevel);

        // Compter combien de branches ont rempli la condition
        $numberOfQualifiedBranches = array_sum($branchStatuses);

        echo "Le distributeur ID {$distributorId} a {$numberOfQualifiedBranches} branche(s) qualifiée(s) pour le niveau {$requiredLevel}.";
        // Résultats détaillés par branche :
        // print_r($branchStatuses);

        return view('some_view', compact('branchStatuses', 'numberOfQualifiedBranches'));
    }
    */

    /* Sépare les résultats de getBranchQualificationStatus en compte qualifié et IDs non qualifiés (version Collection).
    *
    * @param array $branchResults Le tableau retourné par getBranchQualificationStatus (contient des 1 et des IDs).
    * @return array Un tableau associatif ['qualified_count' => int, 'unqualified_root_ids' => array].
    */
    public function processQualificationResultsCollection(array $branchResults): array
    {
        $collection = collect($branchResults);

        // Compter les '1'
        $qualifiedCount = $collection->filter(fn($value) => $value === 1)->count();

        // Obtenir les IDs (tout ce qui n'est pas '1')
        $unqualifiedRootIds = $collection->filter(fn($value) => $value !== 1)->values()->all();

        return [
            'qualified_count' => $qualifiedCount,
            'unqualified_root_ids' => $unqualifiedRootIds,
        ];
    }

     // --- Exemple d'utilisation (dans checkDistributorBranches) ---
    /*
        // ... appel à getBranchQualificationStatus ...
        $processedResults = $this->processQualificationResultsCollection($branchResults);
        // ... reste du code ...
    */

    //NOUVELLE IMPLEMENTATION AVEC RETOUR DE L'ID RACINE DU DISTRIBUTEUR

    /**
     * Vérifie, pour chaque branche enfant directe d'un distributeur racine, si le niveau requis
     * est atteint (>=) soit par l'enfant direct, soit par au moins un descendant.
     * Retourne 1 si la condition est remplie pour la branche, ou l'ID de l'enfant direct si elle ne l'est pas.
     *
     * @param int $rootParentId     L'ID (primaire) du distributeur racine pour la vérification.
     * @param int $requiredLevel    Le niveau d'étoiles minimum requis (>=).
     * @return array                Un tableau contenant des 1 (pour les branches qualifiées)
     *                              et des IDs d'enfants directs (pour les branches non qualifiées).
     */
    public function getBranchQualificationStatus(int $rootParentId, int $requiredLevel): array
    {
        // 1. Valider et définir le niveau de comparaison
        $comparisonLevel = max(1, $requiredLevel);

        // 2. Charger/Recharger la descendance si nécessaire
        $this->loadAllDescendants($rootParentId);

        // 3. Obtenir les enfants directs depuis la map chargée
        $directChildren = $this->descendantsMap->get($rootParentId);

        // Gérer le cas où il n'y a pas d'enfants directs
        if (!$directChildren || $directChildren->isEmpty()) {
            Log::info("Aucun enfant direct trouvé pour le parent ID: {$rootParentId}.");
            return [];
        }
        Log::info("Vérification des " . $directChildren->count() . " enfants directs du parent ID: {$rootParentId} pour le niveau >= {$comparisonLevel}.");

        // 4. Traiter chaque enfant direct (chaque branche)
        $branchResults = [];
        foreach ($directChildren as $child) {
            $isBranchQualified = false; // Flag pour cette branche

            // 4a. Vérifier si l'enfant direct atteint le niveau
            if ($child->etoiles_id >= $comparisonLevel) {
                // Oui, la condition est remplie pour cette branche par l'enfant direct.
                Log::debug("Branche enfant ID {$child->id}: Niveau atteint DIRECTEMENT (>= {$comparisonLevel}).");
                $isBranchQualified = true;
            } else {
                // 4b. Non, l'enfant direct n'atteint pas le niveau.
                // Vérifier récursivement si AU MOINS UN descendant dans sa sous-arborescence atteint le niveau.
                Log::debug("Branche enfant ID {$child->id}: Niveau NON atteint directement (< {$comparisonLevel}). Vérification de la descendance...");
                if ($this->checkSubtreeForLevelOrHigher($child->id, $comparisonLevel)) {
                     Log::debug("Branche enfant ID {$child->id}: Niveau atteint DANS LA DESCENDANCE (>= {$comparisonLevel}).");
                    $isBranchQualified = true; // Condition remplie par un descendant.
                } else {
                    Log::debug("Branche enfant ID {$child->id}: Niveau NON atteint dans la descendance (>= {$comparisonLevel}).");
                    // $isBranchQualified reste false
                }
            }

            // 4c. Ajouter le résultat pour cette branche au tableau final
            if ($isBranchQualified) {
                $branchResults[] = 1; // La branche est qualifiée
            } else {
                $branchResults[] = $child->id; // La branche n'est PAS qualifiée, retourner l'ID de l'enfant direct
            }
        } // Fin foreach $directChildren

        // 5. Retourner le tableau des résultats pour chaque branche
        Log::info("Résultats finaux pour les branches du parent ID {$rootParentId}: " . json_encode($branchResults));
        return $branchResults; // Le tableau contient déjà les 1 et les IDs, pas besoin de flatten
    }

    /**
     * Fonction récursive (en mémoire) pour vérifier si un nœud ou l'un de ses descendants
     * dans la map préchargée atteint OU DÉPASSE le niveau cible.
     * Retourne true dès qu'un correspondant est trouvé.
     * (Identique à la version précédente)
     *
     * @param int $parentId       L'ID du nœud de départ pour la recherche dans la sous-arborescence.
     * @param int $targetLevel    Le niveau d'étoiles minimum à rechercher (>=).
     * @return bool              True si trouvé (dans ce nœud ou en dessous), False sinon.
     */
    protected function checkSubtreeForLevelOrHigher(int $parentId, int $targetLevel): bool
    {
        $children = $this->descendantsMap->get($parentId);
        if (!$children || $children->isEmpty()) {
            return false;
        }
        foreach ($children as $child) {
            if ($child->etoiles_id >= $targetLevel) {
                return true;
            }
            if ($this->checkSubtreeForLevelOrHigher($child->id, $targetLevel)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Charge tous les descendants (directs et indirects) d'un parent donné
     * et les organise dans une Map [parentId => Collection<children>].
     * Utilise une approche itérative. Recharge si le rootParentId est différent.
     * (Identique à la version précédente)
     *
     * @param int $rootParentId
     */
    protected function loadAllDescendants(int $rootParentId): void
    {
        if ($this->descendantsMap !== null && $this->loadedRootId === $rootParentId) {
             Log::debug("Descendance pour le parent ID {$rootParentId} déjà chargée.");
            return;
        }
        Log::info("Chargement de la descendance pour le parent ID: {$rootParentId}");
        $this->descendantsMap = collect();
        $this->loadedRootId = $rootParentId;
        $idsToLoad = [$rootParentId];
        $loadedIds = [];
        $iteration = 0;
        $maxIterations = 100;

        while (!empty($idsToLoad) && $iteration < $maxIterations) {
            $iteration++;
            $currentBatchIds = array_diff($idsToLoad, $loadedIds);
            $idsToLoad = [];
            if (empty($currentBatchIds)) break;
            $loadedIds = array_merge($loadedIds, $currentBatchIds);
            $childrenBatch = Distributeur::whereIn('id_distrib_parent', $currentBatchIds)
                ->select('id', 'id_distrib_parent', 'etoiles_id')
                ->get();
            if ($childrenBatch->isEmpty()) continue;
            foreach ($childrenBatch as $child) {
                if (!$this->descendantsMap->has($child->id_distrib_parent)) {
                    $this->descendantsMap->put($child->id_distrib_parent, collect());
                }
                $this->descendantsMap->get($child->id_distrib_parent)->put($child->id, $child);
                if (!in_array($child->id, $loadedIds)) {
                    $idsToLoad[] = $child->id;
                }
            }
            $idsToLoad = array_unique($idsToLoad);
        }
        if ($iteration >= $maxIterations) {
             Log::error("Limite d'itérations atteinte ({$maxIterations}) lors du chargement de la descendance pour le parent ID: {$rootParentId}.");
        }
        Log::info("Descendance chargée. Nombre de parents dans la map: " . $this->descendantsMap->count());
    }

     // --- Exemple d'utilisation ---
    /*
    public function checkDistributorBranches(int $distributorId)
    {
        $requiredLevel = 4; // Le niveau 4 étoiles
        $eternalHelper = new EternalHelper(); // Ou injectez-le

        // Obtenir le tableau [1, 1, C.id, D.id, 1] par exemple
        $branchResults = $eternalHelper->getBranchQualificationStatus($distributorId, $requiredLevel);

        $qualifiedBranches = [];
        $unqualifiedBranchRoots = [];
        foreach ($branchResults as $result) {
            if ($result === 1) {
                $qualifiedBranches[] = $result; // ou une autre info si nécessaire
            } else {
                $unqualifiedBranchRoots[] = $result; // Contient les IDs des enfants non qualifiés
            }
        }

        echo "Le distributeur ID {$distributorId} a " . count($qualifiedBranches) . " branche(s) qualifiée(s) pour le niveau {$requiredLevel}.";
        if (!empty($unqualifiedBranchRoots)) {
            echo " Les branches non qualifiées commencent par les IDs enfants directs : " . implode(', ', $unqualifiedBranchRoots);
        }

        // print_r($branchResults);

        // return view('some_view', compact('branchResults', 'qualifiedBranches', 'unqualifiedBranchRoots'));
    }
    */

    public function addCumulToDistributeur($period)
    {

        $achats = Achat::selectRaw('achats.distributeur_id, sum(achats.pointvaleur) as new_achats')
            ->groupBy('achats.distributeur_id')
            ->where('achats.period', $period)
            //->toSql();
            ->get();
        //return $achats;
        foreach ($achats as $val) {
            $level = Level_current_test::where('distributeur_id', $val->distributeur_id)->where('period', $period)->first();
            $collectif = $level->cumul_collectif ?? 0;
            $individuel = $level->cumul_individuel ?? 0;

            Level_current_test::updateOrCreate(
                ['distributeur_id' => $val->distributeur_id, 'period' => $period],
                [
                    'cumul_individuel' => $val->new_achats + $individuel,
                    'new_cumul' =>  $val->new_achats,
                    'cumul_total' => $val->new_achats,
                    'cumul_collectif' => $val->new_achats + $collectif
                ]
            );

            $addcumul[] = array(
                'period' => $period,
                'distributeur_id' => $val->distributeur_id,
                'cumul_individuel' => $val->new_achats + $individuel,
                'new_cumul' => $val->new_achats,
                'point_valeur' => $val->new_achats,
                'cumul_total' => $val->new_achats,
                'cumul_collectif' =>  $val->new_achats + $collectif
            );
            /*
            */
        }

        return $addcumul;
    }

    public function addCumulFromChildrenDebug($distributeur_id)
    {
        $children = [];

        $child = Level_current_test::where('id_distrib_parent', $distributeur_id)->distinct('period')->get();

        if($child)
        {
            foreach ($child as $value) {

                $children[] = array(
                    'distributeur_id' => $value->distributeur_id,
                    'cumul_total' => $value->cumul_total,
                    'cumul_collectif' =>  $value->cumul_collectif
                );

                $children[] = $this->addCumulFromChildrenDebug($value->distributeur_id);
            }
        }

        return $children;//Arr::flatten($children);
    }

    public function addCumulToParainsDebugDiffere($id_distrib_parent, $cumul, $period)
    {
        $children = [];

        $parains = Level_current_test::where('distributeur_id', $id_distrib_parent)->where('period', $period)->first();

        if($parains)
        {

            $parains->cumul_collectif = $parains->cumul_collectif + $cumul;
            $parains->update();

            $children[] = array(
                'period' => $period,
                'distributeur_id' => $parains->distributeur_id,
                'cumul_individuel' => $parains->cumul_individuel.' + '.$cumul.' = '.$parains->cumul_individuel+$cumul,
                'value_pv' => $cumul,
                'cumul_total' => $parains->cumul_total.' + '.$cumul.' = '.$parains->cumul_total+$cumul,
                'cumul_collectif' =>  $parains->cumul_collectif.' + '.$cumul.' = '.$parains->cumul_collectif+$cumul,
                'children' =>  $this->addCumulToParainsDebugDiffere($parains->id_distrib_parent, $cumul, $period)
            );
        }

        return $children;//Arr::flatten($children);
    }

    public function addCumulToParainsCollectif($id_distrib_parent, $cumul, $period)
    {
        $children = [];

        $parains = Level_current_test::where('distributeur_id', $id_distrib_parent)->where('period', $period)->first();

        if($parains)
        {
            $parains->cumul_collectif = $parains->cumul_collectif + $cumul;
            $parains->update();

            $children[] = array(
                'period' => $period,
                'distributeur_id' => $parains->distributeur_id,
                'cumul_collectif' =>  $parains->cumul_collectif.' + '.$cumul.' = '.$parains->cumul_collectif+$cumul,
                'children' =>  $this->addCumulToParainsCollectif($parains->id_distrib_parent, $cumul, $period)
            );
        }

        return $children;//Arr::flatten($children);
    }

    public function addCumulToParainsDebug($id_distrib_parent, $cumul, $period)
    {
        $children = [];

        $parains = Level_current_test::where('distributeur_id', $id_distrib_parent)->where('period', $period)->first();

        if($parains)
        {
            $parains->cumul_total = $parains->cumul_total + $cumul;
            $parains->cumul_collectif = $parains->cumul_collectif + $cumul;
            $parains->update();

            $children[] = array(
                'period' => $period,
                'distributeur_id' => $parains->distributeur_id,
                'cumul_individuel' => $parains->cumul_individuel.' + '.$cumul.' = '.$parains->cumul_individuel+$cumul,
                'value_pv' => $cumul,
                'cumul_total' => $parains->cumul_total.' + '.$cumul.' = '.$parains->cumul_total+$cumul,
                'cumul_collectif' =>  $parains->cumul_collectif.' + '.$cumul.' = '.$parains->cumul_collectif+$cumul,
                'children' =>  $this->addCumulToParainsDebug($parains->id_distrib_parent, $cumul, $period)
            );
        }

        return $children;//Arr::flatten($children);
    }

    public function subCumulToParainsDebug($id_distrib_parent, $cumul, $period)
    {
        $children = [];

        $parains = Level_current_test::where('distributeur_id', $id_distrib_parent)->where('period', $period)->first();

        if($parains)
        {
            $parains->cumul_total = $parains->cumul_total - $cumul;
            $parains->cumul_collectif = $parains->cumul_collectif - $cumul;
            $parains->update();

            $children[] = array(
                'distributeur_id' => $parains->distributeur_id,
                'value_pv' => $cumul,
                'cumul_total' => $parains->cumul_total.' - '.$cumul.' = '.$parains->cumul_total+$cumul,
                'cumul_collectif' =>  $parains->cumul_collectif.' - '.$cumul.' = '.$parains->cumul_collectif+$cumul,
                'children' =>  $this->subCumulToParainsDebug($parains->id_distrib_parent, $cumul, $period)
            );
        }

        return $children;//Arr::flatten($children);
    }

    public function subCumulToParainsDebugDiffere($id_distrib_parent, $cumul, $period)
    {
        $children = [];

        $parains = Level_current_test::where('distributeur_id', $id_distrib_parent)->where('period', $period)->first();

        if($parains)
        {
            $parains->cumul_collectif = $parains->cumul_collectif - $cumul;
            $parains->update();

            $children[] = array(
                'distributeur_id' => $parains->distributeur_id,
                'value_pv' => $cumul,
                'cumul_total' => $parains->cumul_total.' - '.$cumul.' = '.$parains->cumul_total+$cumul,
                'cumul_collectif' =>  $parains->cumul_collectif.' - '.$cumul.' = '.$parains->cumul_collectif+$cumul,
                'children' =>  $this->subCumulToParainsDebugDiffere($parains->id_distrib_parent, $cumul, $period)
            );
        }

        return $children;//Arr::flatten($children);
    }

    public function addCumulToParains($id_distrib_parent, $cumul, $period)
    {
         $children = [];

        //$childinfos = Distributeur::join('levels', 'levels.distributeur_id', '=', 'distributeurs.distributeur_id')->get(['distributeurs.*', 'levels.*']);
        $parains = Level_current_test::where('distributeur_id', $id_distrib_parent)->where('period', $period)->first();

        if($parains)
        {
            $children[] = array(
                'distributeur_id' => $parains->distributeur_id,
                'cumul' => $cumul,
                'cumul_total' => $parains->cumul_total.' + '.$cumul.' = '.$parains->cumul_total+$cumul,
                'cumul_collectif' =>  $parains->cumul_collectif.' + '.$cumul.' = '.$parains->cumul_collectif+$cumul,
                'response' =>  'insertion effectuée avec succès'
            );

            $parains->cumul_total = $parains->cumul_total + $cumul;
            $parains->cumul_collectif = $parains->cumul_collectif + $cumul;
            $parains->update();

            $children[] = $this->addCumulToParains($parains->id_distrib_parent, $cumul, $period);
            //return $children;//Arr::flatten($children);
        }

    }

    public function cumlIndividuel()
    {

     //CALCUL DU CUMUL INDIVIDUEL DE L'ENSEMBLE MINORITAIRE A PARTIR DE L'ID : 6685253

        $period1 = '2024-04';
        $period2 = '2024-03';
        $dbRecup1 = Level_current_test::where('period', $period2)->get();

        foreach ($dbRecup1 as $value) {

            //$total = Level_current_test::selectRaw('SUM(cumul_collectif) as collectif')->where('id_distrib_parent', $value->distributeur_id)->where('period', $period)->get();
            $equal = Level_current_test::where('period', $period1)->where('distributeur_id', $value->distributeur_id)->first();

            $equal->cumul_collectif = $value->cumul_collectif;
            $equal->cumul_total = 0;

            $equal->update();

            $response[] = array(
                'distributeur_id' => $value->distributeur_id,
                'collectif_04' => $equal->cumul_collectif,
                'collectif_03' => $value->cumul_collectif
            );
            /*
            $nbrChildren = count($total);

            if($nbrChildren > 0)
            {
                $cumulIndividuel[] = array(
                    'distributeur_id' => $value->distributeur_id,
                    'actual_individuel' => $value->cumul_individuel,
                    'cumul_collectif' => $value->cumul_collectif,
                    'child_collectif' => $total[0]->collectif,
                    'cumul_individuel' => $value->cumul_collectif - $total[0]->collectif
                );
            }
            */
        }

        return $response;
    }

    public function equalize()
    {
        $period = '2024-03';
        $dbRecup = Level_current_test::where('cumul_individuel', 0)->where('period', $period)->where('cumul_collectif','>',0)->get();
        //return $dbRecup;
        foreach ($dbRecup as $key => $value) {

            $childDistributeurs = Level_current_test::where('id_distrib_parent', $value->distributeur_id)->get();
            $total = Level_current_test::selectRaw('SUM(cumul_collectif) as collectif')->where('id_distrib_parent', $value->distributeur_id)->where('period', $period)->get();
            $nbrChildren = count($childDistributeurs);
            if($nbrChildren > 0)
            {
                $reste = $value->cumul_collectif - $total[0]->collectif;
                if($value->cumul_individuel != $reste)
                {

                    $updater = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
                    $updater->cumul_individuel = $reste;
                    $updater->update();

                    $individuelTab[] = array(
                        'cas' => '1er cas reste différent',
                        'period' => $period,
                        'distributeur_id' => $value->distributeur_id,
                        'id_distrib_parent' => $value->distributeur_id,
                        'cumul_individuel' => $value->cumul_individuel,
                        'cumul_collectif' => $value->cumul_collectif,
                        'cumul_enfants_collectif' => $total[0]->collectif,
                        'reste' => $reste,
                        'response' => 'cumul_individuel ajouter',
                    );
                }else {

                    $updater = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
                    $updater->cumul_individuel = $reste;
                    $updater->update();

                    $individuelTab[] = array(
                        'cas' => '2er cas reste égale',
                        'period' => $period,
                        'distributeur_id' => $value->distributeur_id,
                        'id_distrib_parent' => $value->distributeur_id,
                        'cumul_individuel' => $value->cumul_individuel,
                        'cumul_collectif' => $value->cumul_collectif,
                        'cumul_enfants_collectif' => $total[0]->collectif,
                        'reste' => $reste,
                        'response' => 'cumul_individuel ajouter',
                    );
                }
            }
            else {

                    $updater = Level_current_test::where('distributeur_id', $value->distributeur_id)->where('period', $period)->first();
                    $updater->cumul_individuel = $value->cumul_collectif;
                    $updater->update();

                    $individuelTab[] = array(
                        'cas' => '3eme cas pas de fieuil',
                        'period' => $period,
                        'distributeur_id' => $value->distributeur_id,
                        'id_distrib_parent' => $value->distributeur_id,
                        'cumul_individuel' => $value->cumul_individuel,
                        'cumul_collectif' => $value->cumul_collectif,
                        'cumul_enfants_collectif' => $total[0]->collectif,
                        'response' => 'cumul_individuel ajouter',
                    );
            }
        }
        return $individuelTab;
    }

    public function getChildrenNetworkAdvance($disitributeurId, $level, $i)
    {
        $children = [];
        $nbr = 0;
        $allChildren = Distributeur::where('id_distrib_parent', $disitributeurId)->get();
        if($i > 0)
        {
            foreach ($allChildren as $value) {
                if($value->etoiles_id >= $level)
                {
                    $children[] = array(
                        'isit' => 1
                    );
                }
                else {
                    $children[] = array(
                        'isit' => $this->getChildrenNetworkAdvanceLoup($value->distributeur_id, $level)
                    );
                    //$children[] = $this->array_flatten($temp);
                }
                //$tab_etoiles[] = $this->mapRecursive($child, $regul->etoiles);
            }
        }
        else {
            foreach ($allChildren as $value) {
                if($value->etoiles_id == ($level-1))
                {
                    $children[] = array(
                        'isit' => 1
                    );
                    continue;
                }
                else {
                    $children[] = array(
                        'isit' => $this->getChildrenNetworkAdvanceLoup($value->distributeur_id, ($level-1))
                    );
                    //$children[] = $this->array_flatten($temp);
                }
                //$tab_etoiles[] = $this->mapRecursive($child, $regul->etoiles);
            }
        }
        return Arr::flatten($children);
    }

    public function getChildrenNetworkAdvanceLoup($disitributeurId, $level)
    {
        $children = [];
        $nbr = 0;
        $allChildren = Distributeur::where('id_distrib_parent', $disitributeurId)->get();

        foreach ($allChildren as $value) {
            if($value->etoiles_id >= $level)
            {
                $children[] = array(
                    'isit' => 1
                );
                //exit();
            }
            else {
                $children[] = array(
                    'isit' => $this->getChildrenNetworkAdvanceLoup($value->distributeur_id, $level)
                );
                //$children[] = $this->array_flatten($temp);
            }
            //$tab_etoiles[] = $this->mapRecursive($child, $regul->etoiles);
        }
        $nbr = array_sum(Arr::flatten($children));
        if($nbr > 0) $nbr = 1;
        return $nbr;
    }
    //NOUVEAU ALGORITHME

    public function calculAvancementDebug($etoiles, $cumul_individuel, $cumul_collectif, $pass1, $pass2)
    {

        if($etoiles < 3){
            if($cumul_individuel >= 200)
            {
                return 3;
            }
            elseif($cumul_individuel >= 100)
            {
                return 2;
            }
            else {
                return 1;
            }
        }
        elseif($etoiles >=9)
        {
            switch($etoiles)
            {
                case 9 :
                    if($pass1 >= 3)
                    {
                        return 11;
                    }
                    elseif($pass1 >=2)
                    {
                        return 10;
                    }
                    else {
                        return 9;
                    }
                break;
                case 10 :
                    if($pass2 >= 3)
                    {
                        return 10;
                    }
                break;
                default:
                    return 9;
                break;
            }
        }
        else
        {
            switch($cumul_collectif)
            {
                case $cumul_collectif >= 780000:   //PASSAGE AU GRADE 9 AVEC CUMUL SUPERIEUR 780000
                    if($pass1 >= 2)
                    {
                        return 9;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case (($cumul_collectif >= 400000) && ($cumul_collectif < 780000)): //PASSAGE AU GRADE 8 AVEC CUMUL COMPRIS ENTRE 400000 ET 780000
                    if($pass1 >= 3)
                    {
                        return 9;
                    }
                    elseif(($pass1 == 2) && ($pass2 >= 4))
                    {
                        return 9;
                    }
                    elseif(($pass1 == 1) && ($pass2 >= 6))
                    {
                        return 9;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case $cumul_collectif >= 580000:   //PASSAGE AU GRADE 8 AVEC CUMUL SUPERIEUR 580000
                    if($pass1 >= 2)
                    {
                        return 8;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case (($cumul_collectif >= 280000) && ($cumul_collectif < 580000)): //PASSAGE AU GRADE 8 AVEC CUMUL COMPRIS ENTRE 280000 ET 580000
                    if($pass1 >= 3)
                    {
                        return 8;
                    }
                    elseif(($pass1 == 2) && ($pass2 >= 4))
                    {
                        return 8;
                    }
                    elseif(($pass1 == 1) && ($pass2 >= 6))
                    {
                        return 8;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case $cumul_collectif >= 145000:   //PASSAGE AU GRADE 7 AVEC CUMUL SUPERIEUR 35000
                    if($pass1 >= 2)
                    {
                        return 7;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case (($cumul_collectif >= 73000) && ($cumul_collectif < 145000)): //PASSAGE AU GRADE 7 AVEC CUMUL COMPRIS ENTRE 73000 ET 145000
                    if($pass1 >= 3)
                    {
                        return 7;
                    }
                    elseif(($pass1 == 2) && ($pass2 >= 4))
                    {
                        return 7;
                    }
                    elseif(($pass1 == 1) && ($pass2 >= 6))
                    {
                        return 7;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case $cumul_collectif >= 35000:  //PASSAGE AU GRADE 6 AVEC CUMUL SUPERIEUR 35000
                    if($pass1 >= 2)
                    {
                        return 6;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case (($cumul_collectif >= 16000) && ($cumul_collectif < 35000)): //PASSAGE AU GRADE 6 AVEC CUMUL COMPRIS ENTRE 16000 ET 35000
                    if($pass1 >= 3)
                    {
                        return 6;
                    }
                    elseif(($pass1 == 2) && ($pass2 >= 4))
                    {
                        return 6;
                    }
                    elseif(($pass1 == 1) && ($pass2 >= 6))
                    {
                        return 6;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case $cumul_collectif >= 7800: //PASSAGE AU GRADE 5 AVEC CUMUL SUPERIEUR 7800
                    if($pass1 >= 2)
                    {
                        return 5;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case (($cumul_collectif >= 3800) && ($cumul_collectif < 7800)): //PASSAGE AU GRADE 5 AVEC CUMUL COMPRIS ENTRE 3800 ET 7800
                    if($pass1 >= 3)
                    {
                        return 5;
                    }
                    elseif(($pass1 == 2) && ($pass2 >= 4))
                    {
                        return 5;
                    }
                    elseif(($pass1 == 1) && ($pass2 >= 6))
                    {
                        return 5;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case $cumul_collectif >= 2200: //PASSAGE AU GRADE 4 AVEC CUMUL SUPERIEUR 2200
                    if($pass1 >= 2)
                    {
                        return 4;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                case (($cumul_collectif >= 1000) && ($cumul_collectif < 2200)): //PASSAGE AU GRADE 5 AVEC CUMUL COMPRIS ENTRE 1000 ET 2000
                    if($pass1 >= 3)
                    {
                        return 4;
                    }
                    else {
                        return $etoiles;
                    }
                break;

                default: return $etoiles;
                break;
            }
        }
    }


    // ANCIEN ALOGORITHME
    public function calculAvancementDistribDebug($distributeur_id, $etoiles, $cumul_individuel, $cumul_collectif, $etoilesCountChildren)
    {

        $childrenVerif = Distributeur::where('id_distrib_parent', $distributeur_id)->get();
        $etoiles_requis = Etoile::where('etoile_level', ( $etoiles+1 ))->first();

        switch($etoiles)
        {
            case 1:
                //return 'Passage du niveau 1* au niveau 2*';
                $etoiles_individuel = Etoile::where('cumul_individuel', '>=', 100)->where('cumul_individuel', '<=', $cumul_individuel)->latest()->first();
                if($etoiles_individuel){
                    return $etoiles_individuel->etoile_level;//[$distributeur_id, $etoiles_individuel->etoile_level];
                }
                else {
                    return $etoiles;
                }

            break;
            case 2:
                //return 'Passage du niveau 2* au niveau 3*';
                $etoiles_individuel = Etoile::where('cumul_individuel', '>=', 200)->where('cumul_individuel', '<=', $cumul_individuel)->latest()->first();
                if($etoiles_individuel){
                    return $etoiles_individuel->etoile_level;
                }
                else {
                    return $etoiles;
                }

            break;
            case 3:
                //return 'Passage du niveau 3* au niveau 4*';
                if($cumul_individuel >= $etoiles_requis->cumul_individuel){
                    return $etoiles_requis->etoile_level;
                }
                else {

                    if($childrenVerif->count() > 0)
                    {
                        if($cumul_collectif >= $etoiles_requis->cumul_collectif_1)
                        {
                            if($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                            {
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                return $etoiles;
                            }
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2)
                        {
                            if($etoilesCountChildren >= $etoiles_requis->nb_child_2)
                            {
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                return $etoiles;
                            }
                        }
                        else {
                            return $etoiles;
                        }
                    }
                    else {
                        return $etoiles;
                    }
                }

            break;
            case 4:
                //return 'Passage du niveau 4* au niveau 5*';
                if($childrenVerif->count() > 0)
                {
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            //'[1er cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= ($etoiles_requis->nb_child_1-1)) {
                            $nbChildEtoiles = array_sum($this->getBranchQualificationStatus($distributeur_id, ($etoiles-1)));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['2eme cas positif', $distributeur_id,
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['2eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['1er cas negatif',
                            return $etoiles;
                        }
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_2)
                        {
                            //['3eme cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            $nbChildEtoiles = array_sum($this->getBranchQualificationStatus($distributeur_id, ($etoiles-1)));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_3)
                            {
                                //['4eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['4eme cas negatif',
                                return $etoiles;
                            }
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_0)
                        {
                            $nbChildEtoiles = array_sum($this->getBranchQualificationStatus($distributeur_id, ($etoiles-1)));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['5eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['5eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['3eme cas negatif',
                            return $etoiles;
                        }
                    }
                    else {
                        //['Negatif',
                        return $etoiles;
                    }
                }
                else {
                    //['Negatif',
                    return $etoiles;
                }

            break;
            case 5:
                //return 'Passage du niveau 5* au niveau 6*';
                if($childrenVerif->count() > 0)
                {
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            //['1er cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= ($etoiles_requis->nb_child_1-1)) {
                            $nbChildEtoiles = array_sum($this->getBranchQualificationStatus($distributeur_id, ($etoiles-1)));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['2eme cas positif', $distributeur_id,
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['2eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['1er cas negatif',
                            return $etoiles;
                        }
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_2)
                        {
                            //['3eme cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            $nbChildEtoiles = array_sum($this->getBranchQualificationStatus($distributeur_id, ($etoiles-1)));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_3)
                            {
                                //['4eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['4eme cas negatif',
                                return $etoiles;
                            }
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_0)
                        {
                            $nbChildEtoiles = array_sum($this->getBranchQualificationStatus($distributeur_id, ($etoiles-1)));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['5eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['5eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['3eme cas negatif',
                            return $etoiles;
                        }
                    }
                    else {
                        //['Negatif',
                        return $etoiles;
                    }
                }
                else {
                    //['Negatif',
                    return $etoiles;
                }

            break;
            case 6:
                //return 'Passage du niveau 6* au niveau 7*';
                if($childrenVerif->count() > 0)
                {
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            //['1er cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= ($etoiles_requis->nb_child_1-1)) {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['2eme cas positif', $distributeur_id,
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['2eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['1er cas negatif',
                            return $etoiles;
                        }
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_2)
                        {
                            //['3eme cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_3)
                            {
                                //['4eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['4eme cas negatif',
                                return $etoiles;
                            }
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_0)
                        {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['5eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['5eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['3eme cas negatif',
                            return $etoiles;
                        }
                    }
                    else {
                        //['Negatif',
                        return $etoiles;
                    }
                }
                else {
                    //['Negatif',
                    return $etoiles;
                }

            break;
            case 7:
                //return 'Passage du niveau 7* au niveau 8*';
                if($childrenVerif->count() > 0)
                {
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            //['1er cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= ($etoiles_requis->nb_child_1-1)) {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['2eme cas positif', $distributeur_id,
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['2eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['1er cas negatif',
                            return $etoiles;
                        }
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_2)
                        {
                            //['3eme cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_3)
                            {
                                //['4eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['4eme cas negatif',
                                return $etoiles;
                            }
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_0)
                        {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['5eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['5eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['3eme cas negatif',
                            return $etoiles;
                        }
                    }
                    else {
                        //['Negatif',
                        return $etoiles;
                    }
                }
                else {
                    //['Negatif',
                    return $etoiles;
                }

            break;
            case 8:
                //return 'Passage du niveau 8* au niveau 9*';
                if($childrenVerif->count() > 0)
                {
                    if($cumul_collectif >= $etoiles_requis->cumul_collectif_1)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            //['1er cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= ($etoiles_requis->nb_child_1-1)) {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['2eme cas positif', $distributeur_id,
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['2eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['1er cas negatif',
                            return $etoiles;
                        }
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2)
                    {
                        if($etoilesCountChildren >= $etoiles_requis->nb_child_2)
                        {
                            //['3eme cas positif', $distributeur_id,
                            return $etoiles_requis->etoile_level;
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                        {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_3)
                            {
                                //['4eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['4eme cas negatif',
                                return $etoiles;
                            }
                        }
                        elseif($etoilesCountChildren >= $etoiles_requis->nb_child_0)
                        {
                            $nbChildEtoiles = array_sum($this->getChildrenNetworkAdvance($distributeur_id, $etoiles, -1));
                            if($nbChildEtoiles >= $etoiles_requis->nb_child_4)
                            {
                                //['5eme cas positif',
                                return $etoiles_requis->etoile_level;
                            }
                            else {
                                //['5eme cas negatif',
                                return $etoiles;
                            }
                        }
                        else {
                            //['3eme cas negatif',
                            return $etoiles;
                        }
                    }
                    else {
                        //['Negatif',
                        return $etoiles;
                    }
                }
                else {
                    //['Negatif',
                    return $etoiles;
                }

            break;
            case 9:
                //return 'Passage du niveau 9* au niveau 10*';
                if($childrenVerif->count() > 0)
                {
                    if($etoilesCountChildren >= $etoiles_requis->nb_child_1)
                    {
                        //'['1er cas positif', $distributeur_id,
                        return $etoiles_requis->etoile_level;
                    }
                    else {
                        //['1er cas negatif',
                        return $etoiles;
                    }
                }
                else {
                    //['Negatif',
                    return $etoiles;
                }
            break;
            case 10:
                //return 'Passage du niveau 9* au niveau 10*';
                if($childrenVerif->count() > 0)
                {
                    if($etoilesCountChildren >= $etoiles_requis->nb_child_2)
                    {
                        //'['1er cas positif', $distributeur_id,
                        return $etoiles_requis->etoile_level;
                    }
                    else {
                        //['1er cas negatif',
                        return $etoiles;
                    }
                }
                else {
                    //['Negatif',
                    return $etoiles;
                }
            break;
            default: return $etoiles;
            break;
        }
    }

    public function avancementGrade($distributeur_id, $etoiles_in, $cumul_individuel, $cumul_collectif)
    {

        $etoiles_requis = Etoile::where('etoile_level', ( $etoiles_in+1))->first();
        global $etoiles;

        switch($etoiles_in)
        {
            case 1:
                //return 'Passage du niveau 1* au niveau 2*';
                for($i=4; $i>0;$i--){

                    $etoiles_requis = Etoile::where('etoile_level', $i)->first();
                    if($cumul_individuel >= $etoiles_requis->cumul_individuel)
                    {
                        $etoiles = $i;
                        break;
                    }
                    else{
                        $etoiles = 1;
                    }
                }
            break;

            case 2:
                //return 'Passage du niveau 2* au niveau 3*';
                for($i=4; $i>1;$i--){

                    $etoiles_requis = Etoile::where('etoile_level', $i)->first();
                    if($cumul_individuel >= $etoiles_requis->cumul_individuel)
                    {
                        $etoiles = $i;
                        break;
                    }
                    else{
                        $etoiles = 2;
                    }
                }
            break;
            case 3:

                if($cumul_collectif > $etoiles_requis->cumul_collectif_1)
                {
                    $nbthird = $this->getChilrenDistrib($distributeur_id, 3);
                    if(count($nbthird) >= 2)
                    {
                        $etoiles = 4;
                    }
                    if($cumul_individuel >= $etoiles_requis->cumul_individuel)
                    {
                        $etoiles = 4;
                    }
                    else{
                        $etoiles = 3;
                    }
                }
                else {
                    if($cumul_collectif > $etoiles_requis->cumul_collectif_2)
                    {
                        $nbthird = $this->getChilrenDistrib($distributeur_id, 3);
                        if(count($nbthird) >= 3)
                        {
                            $etoiles = 4;
                        }
                        else{
                            if($cumul_individuel >= $etoiles_requis->cumul_individuel)
                            {
                                $etoiles = 4;
                            }
                            else{
                                $etoiles = 3;
                            }
                        }
                    }
                    else {
                        if($cumul_individuel >= $etoiles_requis->cumul_individuel)
                        {
                            $etoiles = 4;
                        }
                        else{
                            $etoiles = 3;
                        }
                    }
                }

            break;
            case 4:
                //return 'Passage du niveau 4* au niveau 5*';
                //return [$distributeur_id, $cumul_individuel, $cumul_collectif, ];

                if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                    $nbthird = $this->getChilrenDistrib($distributeur_id, 4);
                    return $nbthird;
                    if(count($nbthird) >= 2)
                    {
                        $etoiles = 5;
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                        $nbthird = $this->getChilrenDistrib($distributeur_id, 4);

                        if(count($nbthird) >= 3)
                        {
                            $etoiles = 5;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 4);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 3);
                            if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                            {
                                $etoiles = 5;
                            }else {
                                $etoiles = 4;
                            }
                        }
                    }
                    else {
                        $etoiles = 4;
                    }
                }
                else {
                    $etoiles = 4;
                }
            break;
            case 5:
                if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                    $nbthird = $this->getChilrenDistrib($distributeur_id, 5);

                    if(count($nbthird) >= 2)
                    {
                        $etoiles = 6;
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                        $nbthird = $this->getChilrenDistrib($distributeur_id, 5);

                        if(count($nbthird) >= 3)
                        {
                            $etoiles = 6;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 5);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 4);
                            if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                            {
                                $etoiles = 6;
                            }else {
                                $etoiles = 5;
                            }
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_4){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 5);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 4);
                            if((count($nbthird) >= 2) && (count($nbforth) >= 6))
                            {
                                $etoiles = 6;
                            }else {
                                $etoiles = 5;
                            }
                        }
                    }
                    else {
                        $etoiles = 5;
                    }
                }
                else {
                    $etoiles = 5;
                }
            break;
            case 6:
                //return 'Passage du niveau 6* au niveau 7*';
                if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                    $nbthird = $this->getChilrenDistrib($distributeur_id, 6);
                    if(count($nbthird) >= 2)
                    {
                        $etoiles = 7;
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                        $nbthird = $this->getChilrenDistrib($distributeur_id, 6);

                        if(count($nbthird) >= 3)
                        {
                            $etoiles = 7;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 6);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 5);
                            if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                            {
                                $etoiles = 7;
                            }else {
                                $etoiles = 6;
                            }
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_4){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 6);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 5);
                            if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                            {
                                $etoiles = 7;
                            }else {
                                $etoiles = 6;
                            }
                        }
                    }
                    else {
                        $etoiles = 6;
                    }
                }
                else {
                    $etoiles = 6;
                }
            break;
            case 7:

                if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                    $nbthird = $this->getChilrenDistrib($distributeur_id, 7);
                    if(count($nbthird) >= 2)
                    {
                        $etoiles = 8;
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                        $nbthird = $this->getChilrenDistrib($distributeur_id, 7);

                        if(count($nbthird) >= 3)
                        {
                            $etoiles = 8;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 7);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 6);
                            if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                            {
                                $etoiles = 8;
                            }else {
                                $etoiles = 7;
                            }
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_4){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 7);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 6);

                            if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                            {
                                $etoiles = 8;
                            }else {
                                $etoiles = 7;
                            }
                        }
                    }
                    else {
                        $etoiles = 7;
                    }
                }
                else {
                    $etoiles = 7;
                }
            break;
            case 8:

                if($cumul_collectif >= $etoiles_requis->cumul_collectif_1){
                    $nbthird = $this->getChilrenDistrib($distributeur_id, 8);

                    if(count($nbthird) >= 2)
                    {
                        $etoiles = 9;
                    }
                    elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_2){
                        $nbthird = $this->getChilrenDistrib($distributeur_id, 8);

                        if(count($nbthird) >= 3)
                        {
                            $etoiles = 9;
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_3){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 8);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 7);
                            if((count($nbthird) >= 2) && (count($nbforth) >= 4))
                            {
                                $etoiles = 9;
                            }else {
                                $etoiles = 8;
                            }
                        }
                        elseif($cumul_collectif >= $etoiles_requis->cumul_collectif_4){
                            $nbthird = $this->getChilrenDistrib($distributeur_id, 8);
                            $nbforth = $this->getChilrenDistrib($distributeur_id, 7);
                            if((count($nbthird) >= 1) && (count($nbforth) >= 6))
                            {
                                $etoiles = 9;
                            }else {
                                $etoiles = 8;
                            }
                        }
                    }
                    else {
                        $etoiles = 8;
                    }
                }
                else {
                    $etoiles = 8;
                }
            break;
            case 9:

                $nbthird = $this->getChilrenDistrib($distributeur_id, 9);
                if(count($nbthird) >= 2)
                {
                    $etoiles = 10;
                }
                else {
                    $etoiles = 9;
                }
            break;
            case 10:

                $nbthird = $this->getChilrenDistrib($distributeur_id, 9);
                if(count($nbthird) >= 3)
                {
                    $etoiles = 11;
                }
                else {
                    $etoiles = 10;
                }
            default: $etoiles = 1;
        }

        return $etoiles;

    }

    public function addNewCumulDiffere($id, $value_pv, $period)
    {

        $levelInsert = Level_current_test::where('distributeur_id', $id)->where('period', $period)->first();

        $cumul_individuel = $levelInsert->cumul_individuel + $value_pv;
        $cumul_collectif = $levelInsert->cumul_collectif + $value_pv;

        $levelInsert = Level_current_test::updateOrCreate(
            ['period' => $period, 'distributeur_id' => $id],
            ['cumul_individuel' => $cumul_individuel, 'cumul_collectif' => $cumul_collectif]
        );

        $tab[] = array(
            'period' => $period,
            'achat' => $value_pv,
            'distributeur_id' => $id,
            'old_cumul_individuel' => $levelInsert->cumul_individuel,
            'old_new_cumul' => $levelInsert->new_cumul,
            'old_cumul_total' => $levelInsert->cumul_total,
            'old_cumul_collectif' => $levelInsert->cumul_collectif,
            'etoiles' => $levelInsert->etoiles,
            'cumul_individuel' => $cumul_individuel,
            'cumul_collectif' => $cumul_collectif
        );

        return $tab;
    }

    //2EME VERSION AVEC CHATGPT

    public function addNewCumulChatGPT($id, $value_pv, $period)
    {
        // Met à jour ou crée le niveau pour la période donnée
        $levelInsert = Level_current_test::updateOrCreate(
            ['period' => $period, 'distributeur_id' => $id],
            []
        );

        // Récupérer les valeurs actuelles avec protection contre NULL
        $new_cumuls = ($levelInsert->new_cumul ?? 0) + $value_pv;
        $cumul_individuel = ($levelInsert->cumul_individuel ?? 0) + $value_pv;
        $cumul_total = ($levelInsert->cumul_total ?? 0) + $value_pv;
        $cumul_collectif = ($levelInsert->cumul_collectif ?? 0) + $value_pv;

        // Mise à jour des cumuls avec les nouvelles valeurs
        $levelInsert->update([
            'new_cumul' => $new_cumuls,
            'cumul_individuel' => $cumul_individuel,
            'cumul_total' => $cumul_total,
            'cumul_collectif' => $cumul_collectif
        ]);

        // Préparation des données à retourner
        return [[
            'achat' => $value_pv,
            'distributeur_id' => $id,
            'old_cumul_individuel' => $levelInsert->getOriginal('cumul_individuel') ?? 0,
            'old_new_cumul' => $levelInsert->getOriginal('new_cumul') ?? 0,
            'old_cumul_total' => $levelInsert->getOriginal('cumul_total') ?? 0,
            'old_cumul_collectif' => $levelInsert->getOriginal('cumul_collectif') ?? 0,
            'etoiles' => $levelInsert->etoiles ?? 0,
            'cumul_individuel' => $cumul_individuel,
            'new_cumul' => $new_cumuls,
            'cumul_total' => $cumul_total,
            'cumul_collectif' => $cumul_collectif,
            'period' => $period,
        ]];
    }

    //1ERE VERSION
    public function addNewCumul($id, $value_pv, $period)
    {

        $levelInsert = Level_current_test::where('distributeur_id', $id)->where('period', $period)->first();

        $new_cumuls = $levelInsert->new_cumul + $value_pv;
        $cumul_individuel = $levelInsert->cumul_individuel + $value_pv;
        $cumul_total = $levelInsert->cumul_total + $value_pv;
        $cumul_collectif = $levelInsert->cumul_collectif + $value_pv;

        $levelInsert = Level_current_test::updateOrCreate(
            ['period' => $period, 'distributeur_id' => $id],
            ['cumul_individuel' => $cumul_individuel, 'new_cumul' => $new_cumuls, 'cumul_total' => $cumul_total, 'cumul_collectif' =>$cumul_collectif]
        );

        $tab[] = array(
            'achat' => $value_pv,
            'distributeur_id' => $id,
            'old_cumul_individuel' => $levelInsert->cumul_individuel,
            'old_new_cumul' => $levelInsert->new_cumul,
            'old_cumul_total' => $levelInsert->cumul_total,
            'old_cumul_collectif' => $levelInsert->cumul_collectif,
            'etoiles' => $levelInsert->etoiles,
            'cumul_individuel' => $cumul_individuel,
            'new_cumul' => $new_cumuls,
            'cumul_total' => $cumul_total,
            'cumul_collectif' => $cumul_collectif,
            'period' => $period,
        );

        return $tab;
    }

    public function subNewCumul($id, $value_pv, $period)
    {

        $levelInsert = Level_current_test::where('distributeur_id', $id)->where('period', $period)->first();

        $new_cumuls = $levelInsert->new_cumul - $value_pv;
        $cumul_individuel = $levelInsert->cumul_individuel - $value_pv;
        $cumul_total = $levelInsert->cumul_total - $value_pv;
        $cumul_collectif = $levelInsert->cumul_collectif - $value_pv;

        $levelInsert = Level_current_test::updateOrCreate(
            ['period' => $period, 'distributeur_id' => $id],
            ['cumul_individuel' => $cumul_individuel, 'new_cumul' => $new_cumuls, 'cumul_total' => $cumul_total, 'cumul_collectif' =>$cumul_collectif]
        );

        $tab[] = array(
            'achat' => $value_pv,
            'distributeur_id' => $id,
            'old_cumul_individuel' => $levelInsert->cumul_individuel,
            'old_new_cumul' => $levelInsert->new_cumul,
            'old_cumul_total' => $levelInsert->cumul_total,
            'old_cumul_collectif' => $levelInsert->cumul_collectif,
            'etoiles' => $levelInsert->etoiles,
            'cumul_individuel' => $cumul_individuel,
            'new_cumul' => $new_cumuls,
            'cumul_total' => $cumul_total,
            'cumul_collectif' => $cumul_collectif,
            'period' => $period,
        );

        return $tab;
    }

    public function subNewCumulDiffere($id, $value_pv, $period)
    {

        $levelInsert = Level_current_test::where('distributeur_id', $id)->where('period', $period)->first();

        $cumul_individuel = $levelInsert->cumul_individuel - $value_pv;
        $cumul_collectif = $levelInsert->cumul_collectif - $value_pv;

        $levelInsert = Level_current_test::updateOrCreate(
            ['period' => $period, 'distributeur_id' => $id],
            ['cumul_individuel' => $cumul_individuel, 'cumul_collectif' => $cumul_collectif]
        );

        $tab[] = array(
            'achat' => $value_pv,
            'distributeur_id' => $id,
            'old_cumul_individuel' => $levelInsert->cumul_individuel,
            'old_new_cumul' => $levelInsert->new_cumul,
            'old_cumul_total' => $levelInsert->cumul_total,
            'old_cumul_collectif' => $levelInsert->cumul_collectif,
            'etoiles' => $levelInsert->etoiles,
            'cumul_individuel' => $cumul_individuel,
            'cumul_collectif' => $cumul_collectif,
            'period' => $period,
        );

        return $tab;
    }

    //fonctions utiles

    public function getChilrenDistrib($disitributeurId, $rang)
    {
        $children = [];
        //$children[] = $disitributeurId;
        $parentDistributeurs = Level_current_test::where('id_distrib_parent', $disitributeurId)->where('etoiles', '>=', $rang)->get();
        $direct = $parentDistributeurs->count();
        if($direct > 0){

            foreach ($parentDistributeurs as $parent)
            {
                $children[] = $this->getChilrenDistrib($parent->distributeur_id, $rang, $children);
            }
        }
        return $children;
    }

    public function CalculCumlIndividuel($disitributeurId, $parentDistributeurId, $cumul_collectif, $cumul_individuel, $new_cumul, $period)
    {
        $children = [];
        $individuelTab = [];
        $total = 0;
        $reste = 0;
        $childDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->get();
        $total = Level_current_test::selectRaw('SUM(cumul_collectif) as collectif')->where('id_distrib_parent', $disitributeurId)->where('period', $period)->get();
        $level = Level_current_test::where('distributeur_id', $disitributeurId)->where('period', $period)->first();

        return $total;

        $nbrChildren = count($childDistributeurs);

        if($nbrChildren > 0)
        {
            //return $childDistributeurs;
            $reste = $level->cumul_collectif - $total[0]->collectif;
            if($level->cumul_individuel != $reste)
            {
                $individuelTab[] = array(
                    'cas' => '1er cas standard',
                    'period' => $period,
                    'distributeur_id' => $disitributeurId,
                    'id_distrib_parent' => $parentDistributeurId,
                    'cumul_individuel' => $level->cumul_individuel,
                    'cumul_collectif' => $level->cumul_collectif,
                    'cumul_enfants_collectif' => $total[0]->collectif,
                    'reste' => $reste,
                    //'response' => 'cumul_individuel ajouter',
                );
            }
            /*
            $updater = Level_current_test::where('distributeur_id', $disitributeurId)->where('period', $period)->first();
            $updater->cumul_individuel = $reste;
            $updater->update();
            */
            foreach ($childDistributeurs as $child)
            {
                $individuelTab[] = $this->CalculCumlIndividuel($child->distributeur_id, $child->id_distrib_parent, $level->cumul_collectif,  $level->cumul_individuel, $level->new_cumul, $period, $children);
            }
            /*
            else {
                $reste = $total - $cumul_parent;
            }*/


        }

        else {
            if($cumul_collectif > $new_cumul){

                $reste = $cumul_collectif;
                /*
                $updater = Level_current_2024_02::where('distributeur_id', $disitributeurId)->first();
                $updater->cumul_individuel = $reste;
                $updater->update();
                */
                $individuelTab[] = array(
                    'cas' => '2eme cas pas de filleul, mais fait des achats',
                    'period' => $period,
                    'distributeur_id' => $disitributeurId,
                    'cumul_collectif' => $cumul_collectif,
                    'cumul_enfants_collectif' => $total[0]->collectif,
                    'cumul_individuel' => $reste,
                    //'response' => 'cumul_individuel ajouter',
                );
            }

            elseif($cumul_collectif <= $new_cumul)
            {
                /*
                $updater = Level_current_2024_02::where('distributeur_id', $disitributeurId)->first();
                $updater->cumul_individuel = $reste;
                $updater->update();
                */
                $individuelTab[] = array(
                    'cas' => '3eme cas pas de filleul, 1er achat',
                    'period' => $period,
                    'distributeur_id' => $disitributeurId,
                    'cumul_collectif' => $cumul_collectif,
                    'new_cumul' => $new_cumul,
                    'cumul_enfants_collectif' => $total[0]->collectif,
                    'cumul_individuel' => $reste,
                    //'response' => 'cumul_individuel ajouter',
                );

            }
        }

       return $individuelTab;

    }

    public function getPercentCumulCollectif($disitributeurId)
    {
        $tab = 0;
        foreach ($disitributeurId as $distrib) {
            $child = Level_current_test::where('distributeur_id', $distrib->distributeur_id)->get();
            foreach ($child as $value) {
                if($value->new_cumul > 0)
                {
                    $tab = $tab + 1;
                }
            }
        }
        return $tab;
    }

    public function getAllChildrenNetwork($disitributeurId, $level)
    {
        $children = [];

        $childDistributeurs = Distributeur::where('id_distrib_parent', $disitributeurId)->where('etoiles', '>=', $level)->get();
        foreach ($childDistributeurs as $child)
        {
            $children[] = array(
                'tour' => $level,
                'id' => $child->distributeur_id,
                'children' => $this->getAllChildrenNetwork($child->distributeur_id, $level, $children)
            );
            //$children[] = $this->array_flatten($temp);
        }
        return $children;//Arr::flatten($children);
    }


    public function getChildrenNetwork($disitributeurId)
    {
        $nb = 0;
        foreach ($disitributeurId as $distrib)
        {
            $child = Level_current_test::where('distributeur_id', $distrib->distributeur_id)->get();
            $nb = $nb + count($child);
        }

        return $nb;
    }

    function array_flatten($array = null) {
        $result = array();

        if (!is_array($array)) {
            $array = func_get_args();
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->array_flatten($value));
            } else {
                $result = array_merge($result, array($key => $value));
            }
        }

        return $result;
    }

    public function getChilren($disitributeurId, $period)
    {
        $children = [];

        //$children[] = $disitributeurId;
        $parentDistributeurs = Level_current_test::join('distributeurs', 'distributeurs.distributeur_id', '=', 'level_current_tests.distributeur_id')
                ->where('level_current_tests.id_distrib_parent', $disitributeurId)
                ->where('level_current_tests.period', $period)
                ->get();

        $nb = $parentDistributeurs->count();
        if($nb > 0){

            foreach ($parentDistributeurs as $parent)
            {
                $children[] = $parent;
                $children[] = $this->getChilren($parent->distributeur_id, $period, $children);
            }
        }
        //return $this->array_flatten($children);
        return Arr::flatten($children);
    }


    public function bonusIndirect($disitributeurId, $etoiles, $period)
    {
        $bonus = [];
        $children = Level_current_test::where('id_distrib_parent', $disitributeurId)->where('period', $period)->get();
        if(count($children) > 0)
        {
            foreach ($children as $value) {

                $diff = $etoiles - $value->etoiles;
                $taux = $this->etoilesChecker($etoiles, $diff);
                $bonus[] = $value->cumul_total * $taux;
            }
        }
        else {
            $bonus[] = 0;
        }

        return array_sum($bonus);
    }


    public function isBonusEligible($etoiles, $cumul)
    {
        switch($etoiles)
        {
            case 1 :
                $bonus = false;
                $quota = 0;
            case 2 :
                $bonus = true;
                $quota = 0;
            break;
            case 3 :
                $bonus = ($cumul >= 10) ? true : false;
                $quota = 10;
            break;
            case 4 :
                $bonus = ($cumul >= 15) ? true : false;
                $quota = 15;
            break;
            case 5 :
                $bonus = ($cumul >= 30) ? true : false;
                $quota = 30;
            break;
            case 6 :
                $bonus = ($cumul >= 50) ? true : false;
                $quota = 50;
            break;
            case 7 :
                $bonus = ($cumul >= 100) ? true : false;
                $quota = 100;
            break;
            case 8 :
                $bonus = ($cumul >= 150) ? true : false;
                $quota = 150;
            break;
            case 9 :
                $bonus = ($cumul >= 180) ? true : false;
                $quota = 180;
            break;
            case 10 :
                $bonus = ($cumul >= 180) ? true : false;
                $quota = 180;
            break;
            default: $bonus = false; $quota = 0;
        }
        return ['eligible' => $bonus, 'quota' => $quota];
    }


    public function tauxDirectCalculator($etoiles)
    {
        switch($etoiles)
        {
            case 1: $taux_dir = 0;
            break;
            case 2: $taux_dir = 6/100;
            break;
            case 3: $taux_dir = 22/100;
            break;
            case 4: $taux_dir = 26/100;
            break;
            case 5: $taux_dir = 30/100;
            break;
            case 6: $taux_dir = 34/100;
            break;
            case 7: $taux_dir = 40/100;
            break;
            case 8: $taux_dir = 43/100;
            break;
            case 9: $taux_dir = 45/100;
            break;
            case 10: $taux_dir = 45/100;
            break;
        }
        return $taux_dir;
    }


    public function etoilesChecker($etoiles, $diff)
    {
        switch($etoiles)
        {
            case 1 :

                    $taux = 0;
            break;
            case 2 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                $taux = 0.06;
            break;
            case 3 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.16;
                if($diff == 2)
                    $taux = 0.22;
            break;
            case 4 :

                if($diff <= 0)
                    $taux = 0;
                if($diff == 1)
                    $taux = 0.04;
                if($diff == 2)
                    $taux = 0.20;
                if($diff == 3)
                    $taux = 0.26;
            break;
            case 5 :

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
            case 6 :

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
            case 7 :

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
            case 8 :

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
            case 9 :

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

            break;
            default: $taux = 0;
        }
        return $taux;
    }
}
