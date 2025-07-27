<?php

namespace App\Services;

use App\Models\Distributeur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EternalHelperLegacyMatriculeDB
{
    /**
     * Map de la descendance chargée.
     * Structure: [parent_MATRICULE => Collection [child_MATRICULE => childObject]]
     * @var Collection|null
     */
    private ?Collection $descendantsMapByMatricule = null;

    /**
     * Map de tous les distributeurs pour un accès rapide par matricule.
     * Structure: [MATRICULE => object(id, distributeur_id, etoiles_id, id_distrib_parent)]
     * @var Collection|null
     */
    private ?Collection $allDistributorsMap = null;

    /**
     * Cache pour les calculs de branches qualifiées.
     * Structure: [parentMatricule_targetLevel => count]
     * @var array
     */
    private array $qualifiedBranchesCache = [];

    /**
     * Cache pour les vérifications de sous-arbres.
     * Structure: [parentMatricule_targetLevel => bool]
     * @var array
     */
    private array $subtreeCheckCache = [];

    /**
     * Mode debug activé/désactivé
     * @var bool
     */
    private bool $debugMode = false;

    /**
     * Active le mode debug
     */
    public function enableDebugMode(): void
    {
        $this->debugMode = true;
    }

    /**
     * Désactive le mode debug
     */
    public function disableDebugMode(): void
    {
        $this->debugMode = false;
    }

    /**
     * Compte le nombre de branches enfants directes qui contiennent au moins un membre
     * ayant un niveau supérieur ou égal au niveau cible.
     * Suppose que loadAndBuildMaps a déjà été appelé.
     *
     * @param int|string $rootParentMatricule Le matricule du distributeur dont on évalue les branches.
     * @param int $targetLevel Le niveau à rechercher dans chaque branche.
     * @return int Le nombre de branches qualifiées.
     * @throws \LogicException Si les maps ne sont pas chargées.
     */
    public function countQualifiedBranches(int|string $rootParentMatricule, int $targetLevel): int
    {
        if ($this->descendantsMapByMatricule === null) {
            throw new \LogicException("Les maps ne sont pas chargées. Appelez loadAndBuildMaps() d'abord.");
        }

        // Vérifier le cache
        $cacheKey = "{$rootParentMatricule}_{$targetLevel}";
        if (isset($this->qualifiedBranchesCache[$cacheKey])) {
            return $this->qualifiedBranchesCache[$cacheKey];
        }

        $directChildren = $this->descendantsMapByMatricule->get($rootParentMatricule);

        // Log de débogage pour le matricule spécifique (seulement si debug mode activé)
        if ($this->debugMode && $rootParentMatricule == '2224878' && $targetLevel <= 8) {
            Log::debug("DEBUG POUR 2224878 (Recherche niveau >= {$targetLevel}):");
            if (!$directChildren || $directChildren->isEmpty()) {
                Log::debug("  Aucun enfant direct trouvé dans la map !");
            } else {
                Log::debug("  Enfants directs trouvés dans la map (" . $directChildren->count() . " au total):");
                foreach($directChildren as $matricule => $child) {
                    Log::debug("    - Matricule: {$matricule}, Grade: {$child->etoiles_id}");
                }
            }
        }

        if (!$directChildren || $directChildren->isEmpty()) {
            $this->qualifiedBranchesCache[$cacheKey] = 0;
            return 0;
        }

        $qualifiedBranchCount = 0;
        foreach ($directChildren as $childMatricule => $child) {
            // Vérifier d'abord le niveau de l'enfant direct
            if ($child->etoiles_id >= $targetLevel) {
                $qualifiedBranchCount++;
            } else {
                // Sinon, vérifier dans le sous-arbre (avec cache)
                if ($this->checkSubtreeForLevel($childMatricule, $targetLevel)) {
                    $qualifiedBranchCount++;
                }
            }
        }

        // Mettre en cache le résultat
        $this->qualifiedBranchesCache[$cacheKey] = $qualifiedBranchCount;
        return $qualifiedBranchCount;
    }

    /**
     * Fonction récursive privée (en mémoire) pour vérifier si un descendant atteint ou dépasse un niveau.
     * Utilise un cache pour éviter les recalculs.
     */
    private function checkSubtreeForLevel(int|string $parentMatricule, int $targetLevel): bool
    {
        // Vérifier le cache
        $cacheKey = "{$parentMatricule}_{$targetLevel}";
        if (isset($this->subtreeCheckCache[$cacheKey])) {
            return $this->subtreeCheckCache[$cacheKey];
        }

        $children = $this->descendantsMapByMatricule->get($parentMatricule);
        if (!$children || $children->isEmpty()) {
            $this->subtreeCheckCache[$cacheKey] = false;
            return false;
        }

        // Parcourir les enfants
        foreach ($children as $childMatricule => $child) {
            if ($child->etoiles_id >= $targetLevel) {
                $this->subtreeCheckCache[$cacheKey] = true;
                return true;
            }
        }

        // Si aucun enfant direct n'a le niveau requis, vérifier récursivement
        foreach ($children as $childMatricule => $child) {
            if ($this->checkSubtreeForLevel($childMatricule, $targetLevel)) {
                $this->subtreeCheckCache[$cacheKey] = true;
                return true;
            }
        }

        $this->subtreeCheckCache[$cacheKey] = false;
        return false;
    }

    /**
     * Charge TOUS les distributeurs et construit les maps de hiérarchie basées sur les matricules.
     * Doit être appelée une fois avant d'utiliser les autres méthodes.
     *
     * @throws \RuntimeException Si un doublon de matricule est détecté.
     */
    public function loadAndBuildMaps(): void
    {
        // Ne charger qu'une seule fois par instance de classe
        if ($this->descendantsMapByMatricule !== null) {
            return;
        }

        Log::info("HELPER (LegacyDB): Chargement de TOUS les distributeurs et construction des maps...");
        $startTime = microtime(true);

        // 1. Charger tous les distributeurs en une fois
        $allDistributors = Distributeur::select('id', 'distributeur_id', 'id_distrib_parent', 'etoiles_id')->get();

        // 2. Créer une map pour un accès rapide par matricule
        $this->allDistributorsMap = $allDistributors->keyBy('distributeur_id');

        // Vérification des doublons de matricule
        if ($this->allDistributorsMap->count() < $allDistributors->count()) {
            throw new \RuntimeException("Doublon de matricule détecté dans la table distributeurs. Le processus ne peut continuer.");
        }

        // 3. Créer la map de descendance [parent_MATRICULE => Collection<enfants>]
        $this->descendantsMapByMatricule = collect();
        foreach ($allDistributors as $distributor) {
            $parentMatricule = $distributor->id_distrib_parent;

            // Ignorer si pas de parent ou si parent = 0 (représente une racine dans l'ancienne structure)
            if (empty($parentMatricule) || $parentMatricule == 0) {
                continue;
            }

            // Initialiser la collection pour le parent si elle n'existe pas
            if (!$this->descendantsMapByMatricule->has($parentMatricule)) {
                $this->descendantsMapByMatricule->put($parentMatricule, collect());
            }

            // Ajouter l'enfant à la collection de son parent
            $this->descendantsMapByMatricule->get($parentMatricule)->put($distributor->distributeur_id, $distributor);
        }

        // Réinitialiser les caches
        $this->qualifiedBranchesCache = [];
        $this->subtreeCheckCache = [];

        $duration = round((microtime(true) - $startTime) * 1000);
        Log::info("HELPER (LegacyDB): Maps construites en {$duration}ms. " . $this->allDistributorsMap->count() . " distributeurs mappés.");
    }

    /**
     * Permet à la commande d'avancement de mettre à jour le niveau d'un noeud dans la map
     * pour les calculs itératifs.
     * IMPORTANT: Invalide les caches concernés.
     */
    public function updateNodeLevelInMap(int|string $matricule, int $newLevel): void
    {
        if ($this->allDistributorsMap && $this->allDistributorsMap->has($matricule)) {
            $oldLevel = $this->allDistributorsMap->get($matricule)->etoiles_id;
            $this->allDistributorsMap->get($matricule)->etoiles_id = $newLevel;

            // Invalider les caches pertinents si le niveau a changé
            if ($oldLevel != $newLevel) {
                $this->invalidateCacheForMatricule($matricule);
            }
        }
    }

    /**
     * Invalide les entrées de cache qui pourraient être affectées par un changement de niveau
     * pour un matricule donné.
     */
    private function invalidateCacheForMatricule(int|string $matricule): void
    {
        // Trouver le parent de ce matricule
        $parentMatricule = null;
        if ($this->allDistributorsMap->has($matricule)) {
            $parentMatricule = $this->allDistributorsMap->get($matricule)->id_distrib_parent;
        }

        // Invalider les caches liés à ce matricule et son parent
        $keysToRemove = [];

        // Invalider les entrées où ce matricule est le parent
        foreach ($this->qualifiedBranchesCache as $key => $value) {
            if (str_starts_with($key, "{$matricule}_")) {
                $keysToRemove[] = $key;
            }
        }

        // Invalider les entrées où le parent de ce matricule est impliqué
        if ($parentMatricule) {
            foreach ($this->qualifiedBranchesCache as $key => $value) {
                if (str_starts_with($key, "{$parentMatricule}_")) {
                    $keysToRemove[] = $key;
                }
            }
        }

        // Supprimer les entrées invalides
        foreach ($keysToRemove as $key) {
            unset($this->qualifiedBranchesCache[$key]);
        }

        // Faire de même pour subtreeCheckCache
        $keysToRemove = [];
        foreach ($this->subtreeCheckCache as $key => $value) {
            if (str_starts_with($key, "{$matricule}_") || ($parentMatricule && str_starts_with($key, "{$parentMatricule}_"))) {
                $keysToRemove[] = $key;
            }
        }

        foreach ($keysToRemove as $key) {
            unset($this->subtreeCheckCache[$key]);
        }
    }

    /**
     * Retourne des statistiques sur l'utilisation du cache (utile pour le débogage).
     */
    public function getCacheStats(): array
    {
        return [
            'qualified_branches_cache_size' => count($this->qualifiedBranchesCache),
            'subtree_check_cache_size' => count($this->subtreeCheckCache),
            'total_distributors' => $this->allDistributorsMap ? $this->allDistributorsMap->count() : 0,
            'debug_mode' => $this->debugMode,
        ];
    }
}
