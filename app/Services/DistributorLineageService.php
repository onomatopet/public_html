<?php

namespace App\Services;

use App\Models\Distributeur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DistributorLineageService
{
    /**
     * Cache pour les résultats de recherche
     * @var array
     */
    private array $lineageCache = [];

    /**
     * Map de tous les distributeurs pour accès rapide
     * @var Collection|null
     */
    private ?Collection $distributorsMap = null;

    /**
     * Map parent->enfants pour navigation rapide
     * @var Collection|null
     */
    private ?Collection $parentChildrenMap = null;

    /**
     * Statistiques de la dernière recherche
     * @var array
     */
    private array $lastSearchStats = [];

    /**
     * Recherche tous les filleuls d'une lignée à partir d'un distributeur
     *
     * @param int|string $rootIdentifier ID primaire ou matricule du distributeur racine
     * @param array $options Options de recherche
     * @return Collection
     */
    public function getAllDescendants($rootIdentifier, array $options = []): Collection
    {
        $defaultOptions = [
            'max_depth' => null,              // Profondeur maximale (null = illimité)
            'include_root' => false,          // Inclure le distributeur racine dans les résultats
            'only_active' => false,           // Filtrer uniquement les distributeurs validés
            'with_grade' => null,             // Filtrer par grade minimum
            'with_relations' => [],           // Relations Eloquent à charger
            'use_cache' => true,              // Utiliser le cache
            'cache_duration' => 3600,         // Durée du cache en secondes
            'format' => 'collection',         // Format de sortie: collection, array, tree
            'include_stats' => true,          // Inclure les statistiques
            'filter_cumul_checked' => null,   // Filtrer par is_indivual_cumul_checked
            'search_term' => null,            // Recherche par nom/prénom
        ];

        $options = array_merge($defaultOptions, $options);

        // Réinitialiser les stats
        $this->lastSearchStats = [
            'total_found' => 0,
            'max_depth_reached' => 0,
            'search_time_ms' => 0,
            'cache_used' => false,
        ];

        $startTime = microtime(true);

        // Obtenir l'ID primaire si matricule fourni
        $rootId = $this->resolveDistributorId($rootIdentifier);
        if (!$rootId) {
            Log::warning("Distributeur non trouvé pour l'identifiant: {$rootIdentifier}");
            return collect();
        }

        // Vérifier le cache si activé
        $cacheKey = $this->getCacheKey($rootId, $options);
        if ($options['use_cache'] && Cache::has($cacheKey)) {
            $this->lastSearchStats['cache_used'] = true;
            $result = Cache::get($cacheKey);
            $this->lastSearchStats['search_time_ms'] = (microtime(true) - $startTime) * 1000;
            return $result;
        }

        // Charger les données si nécessaire
        if (!$this->distributorsMap || !$this->parentChildrenMap) {
            $this->loadDistributorsData();
        }

        // Effectuer la recherche récursive
        $descendants = collect();
        $this->searchDescendantsRecursive(
            $rootId,
            $descendants,
            0,
            $options['max_depth'],
            $options
        );

        // Inclure la racine si demandé
        if ($options['include_root'] && isset($this->distributorsMap[$rootId])) {
            $descendants->prepend($this->distributorsMap[$rootId]);
        }

        // Appliquer les filtres
        $descendants = $this->applyFilters($descendants, $options);

        // Charger les relations si demandées
        if (!empty($options['with_relations'])) {
            $descendants = $this->loadRelations($descendants, $options['with_relations']);
        }

        // Formater selon le besoin
        $result = $this->formatResult($descendants, $options['format'], $rootId);

        // Mettre en cache si activé
        if ($options['use_cache']) {
            Cache::put($cacheKey, $result, $options['cache_duration']);
        }

        // Finaliser les stats
        $this->lastSearchStats['total_found'] = $descendants->count();
        $this->lastSearchStats['search_time_ms'] = (microtime(true) - $startTime) * 1000;

        return $result;
    }

    /**
     * Recherche tous les filleuls directs (enfants de premier niveau)
     *
     * @param int|string $parentIdentifier
     * @param array $options
     * @return Collection
     */
    public function getDirectChildren($parentIdentifier, array $options = []): Collection
    {
        $options['max_depth'] = 1;
        $options['include_root'] = false;
        return $this->getAllDescendants($parentIdentifier, $options);
    }

    /**
     * Recherche tous les ancêtres d'un distributeur (remonte la lignée)
     *
     * @param int|string $childIdentifier
     * @param array $options
     * @return Collection
     */
    public function getAllAncestors($childIdentifier, array $options = []): Collection
    {
        $ancestors = collect();
        $childId = $this->resolveDistributorId($childIdentifier);

        if (!$childId || !$this->distributorsMap) {
            $this->loadDistributorsData();
        }

        $current = $this->distributorsMap->get($childId);
        while ($current && $current->id_distrib_parent) {
            $parent = $this->distributorsMap->get($current->id_distrib_parent);
            if ($parent) {
                $ancestors->push($parent);
                $current = $parent;
            } else {
                break;
            }
        }

        return $ancestors;
    }

    /**
     * Compte le nombre total de filleuls dans une lignée
     *
     * @param int|string $rootIdentifier
     * @param array $options
     * @return int
     */
    public function countDescendants($rootIdentifier, array $options = []): int
    {
        $options['with_relations'] = []; // Pas besoin de charger les relations pour compter
        $options['format'] = 'collection';
        return $this->getAllDescendants($rootIdentifier, $options)->count();
    }

    /**
     * Obtient l'arbre généalogique complet avec structure hiérarchique
     *
     * @param int|string $rootIdentifier
     * @param array $options
     * @return array
     */
    public function getGenealogicalTree($rootIdentifier, array $options = []): array
    {
        $rootId = $this->resolveDistributorId($rootIdentifier);
        if (!$rootId) return [];

        if (!$this->distributorsMap || !$this->parentChildrenMap) {
            $this->loadDistributorsData();
        }

        $root = $this->distributorsMap->get($rootId);
        if (!$root) return [];

        return $this->buildTreeStructure($root, $options);
    }

    /**
     * Recherche les distributeurs par niveau de profondeur
     *
     * @param int|string $rootIdentifier
     * @param int $targetDepth
     * @return Collection
     */
    public function getDescendantsByDepth($rootIdentifier, int $targetDepth): Collection
    {
        $allDescendants = collect();
        $currentLevel = collect([$this->resolveDistributorId($rootIdentifier)]);
        $currentDepth = 0;

        if (!$this->parentChildrenMap) {
            $this->loadDistributorsData();
        }

        while ($currentDepth < $targetDepth && $currentLevel->isNotEmpty()) {
            $nextLevel = collect();

            foreach ($currentLevel as $parentId) {
                $children = $this->parentChildrenMap->get($parentId, collect());
                foreach ($children as $child) {
                    $nextLevel->push($child->id);
                }
            }

            if ($currentDepth + 1 == $targetDepth) {
                // Récupérer les objets complets pour le niveau cible
                foreach ($nextLevel as $childId) {
                    if ($distributor = $this->distributorsMap->get($childId)) {
                        $allDescendants->push($distributor);
                    }
                }
            }

            $currentLevel = $nextLevel;
            $currentDepth++;
        }

        return $allDescendants;
    }

    /**
     * Obtient des statistiques détaillées sur une lignée
     *
     * @param int|string $rootIdentifier
     * @return array
     */
    public function getLineageStatistics($rootIdentifier): array
    {
        $descendants = $this->getAllDescendants($rootIdentifier, [
            'include_stats' => true,
            'use_cache' => false
        ]);

        $stats = [
            'total_descendants' => $descendants->count(),
            'by_grade' => [],
            'by_rang' => [],
            'validated_count' => 0,
            'not_validated_count' => 0,
            'cumul_checked_count' => 0,
            'max_depth' => $this->lastSearchStats['max_depth_reached'],
            'average_children_per_distributor' => 0,
            'distributors_with_children' => 0,
            'distributors_without_children' => 0,
        ];

        // Analyser les descendants
        $distributorsWithChildren = 0;
        $totalChildren = 0;

        foreach ($descendants as $descendant) {
            // Par grade
            $grade = $descendant->etoiles_id ?? 1;
            if (!isset($stats['by_grade'][$grade])) {
                $stats['by_grade'][$grade] = 0;
            }
            $stats['by_grade'][$grade]++;

            // Par rang
            $rang = $descendant->rang ?? 0;
            if (!isset($stats['by_rang'][$rang])) {
                $stats['by_rang'][$rang] = 0;
            }
            $stats['by_rang'][$rang]++;

            // Statut de validation
            if ($descendant->statut_validation_periode == 1) {
                $stats['validated_count']++;
            } else {
                $stats['not_validated_count']++;
            }

            // Cumul vérifié
            if ($descendant->is_indivual_cumul_checked == 1) {
                $stats['cumul_checked_count']++;
            }

            // Compter les enfants
            $childrenCount = $this->parentChildrenMap->get($descendant->id, collect())->count();
            if ($childrenCount > 0) {
                $distributorsWithChildren++;
                $totalChildren += $childrenCount;
            }
        }

        // Calculer les moyennes
        $stats['distributors_with_children'] = $distributorsWithChildren;
        $stats['distributors_without_children'] = $descendants->count() - $distributorsWithChildren;
        $stats['average_children_per_distributor'] = $distributorsWithChildren > 0
            ? round($totalChildren / $distributorsWithChildren, 2)
            : 0;

        // Calculer la distribution par profondeur
        $stats['by_depth'] = $this->calculateDepthDistribution($rootIdentifier);

        // Trier les statistiques par grade et rang
        ksort($stats['by_grade']);
        ksort($stats['by_rang']);

        return $stats;
    }

    /**
     * Recherche récursive des descendants
     */
    private function searchDescendantsRecursive(
        int $parentId,
        Collection &$descendants,
        int $currentDepth,
        ?int $maxDepth,
        array $options
    ): void {
        if ($maxDepth !== null && $currentDepth >= $maxDepth) {
            return;
        }

        $children = $this->parentChildrenMap->get($parentId, collect());

        foreach ($children as $child) {
            // Ajouter l'enfant aux résultats
            $descendants->push($child);

            // Mettre à jour la profondeur max atteinte
            if ($currentDepth + 1 > $this->lastSearchStats['max_depth_reached']) {
                $this->lastSearchStats['max_depth_reached'] = $currentDepth + 1;
            }

            // Recherche récursive dans les sous-branches
            $this->searchDescendantsRecursive(
                $child->id,
                $descendants,
                $currentDepth + 1,
                $maxDepth,
                $options
            );
        }
    }

    /**
     * Charge toutes les données des distributeurs en mémoire
     */
    private function loadDistributorsData(): void
    {
        Log::info("Chargement des données des distributeurs en mémoire...");

        // Charger tous les distributeurs avec les colonnes disponibles
        $allDistributors = DB::table('distributeurs')
            ->select(
                'id',
                'distributeur_id',
                'etoiles_id',
                'id_distrib_parent',
                'rang',
                'nom_distributeur',
                'pnom_distributeur',
                'tel_distributeur',
                'adress_distributeur',
                'statut_validation_periode',
                'is_indivual_cumul_checked',
                'created_at',
                'updated_at'
            )
            ->get();

        // Créer la map des distributeurs
        $this->distributorsMap = $allDistributors->keyBy('id');

        // Créer la map parent->enfants
        $this->parentChildrenMap = collect();

        foreach ($allDistributors as $distributor) {
            if ($distributor->id_distrib_parent) {
                if (!$this->parentChildrenMap->has($distributor->id_distrib_parent)) {
                    $this->parentChildrenMap->put($distributor->id_distrib_parent, collect());
                }
                $this->parentChildrenMap->get($distributor->id_distrib_parent)->push($distributor);
            }
        }

        Log::info("Données chargées: {$allDistributors->count()} distributeurs");
    }

    /**
     * Résout un identifiant (matricule ou ID) vers un ID primaire
     */
    private function resolveDistributorId($identifier): ?int
    {
        if (is_numeric($identifier) && $identifier < 1000000) {
            // Probablement un ID primaire
            return (int) $identifier;
        }

        // Rechercher par matricule
        $distributor = DB::table('distributeurs')
            ->where('distributeur_id', $identifier)
            ->select('id')
            ->first();

        return $distributor ? $distributor->id : null;
    }

    /**
     * Applique les filtres sur la collection
     */
    private function applyFilters(Collection $descendants, array $options): Collection
    {
        // Filtrer par statut de validation
        if ($options['only_active']) {
            $descendants = $descendants->filter(function ($d) {
                return $d->statut_validation_periode == 1;
            });
        }

        // Filtrer par grade minimum
        if ($options['with_grade'] !== null) {
            $descendants = $descendants->filter(function ($d) use ($options) {
                return ($d->etoiles_id ?? 0) >= $options['with_grade'];
            });
        }

        // Filtrer par is_indivual_cumul_checked
        if ($options['filter_cumul_checked'] !== null) {
            $descendants = $descendants->filter(function ($d) use ($options) {
                return $d->is_indivual_cumul_checked == $options['filter_cumul_checked'];
            });
        }

        // Recherche par nom/prénom
        if ($options['search_term'] !== null) {
            $searchTerm = strtolower($options['search_term']);
            $descendants = $descendants->filter(function ($d) use ($searchTerm) {
                return str_contains(strtolower($d->nom_distributeur), $searchTerm) ||
                       str_contains(strtolower($d->pnom_distributeur), $searchTerm);
            });
        }

        return $descendants;
    }

    /**
     * Charge les relations Eloquent
     */
    private function loadRelations(Collection $descendants, array $relations): Collection
    {
        if (empty($relations)) return $descendants;

        $ids = $descendants->pluck('id');
        $models = Distributeur::whereIn('id', $ids)->with($relations)->get()->keyBy('id');

        return $descendants->map(function ($item) use ($models) {
            return $models->get($item->id) ?? $item;
        });
    }

    /**
     * Formate le résultat selon le format demandé
     */
    private function formatResult(Collection $descendants, string $format, int $rootId): mixed
    {
        switch ($format) {
            case 'array':
                return $descendants->toArray();

            case 'tree':
                return $this->buildTreeFromFlat($descendants, $rootId);

            case 'collection':
            default:
                return $descendants;
        }
    }

    /**
     * Construit une structure d'arbre hiérarchique
     */
    private function buildTreeStructure($node, array $options, int $currentDepth = 0): array
    {
        $tree = [
            'id' => $node->id,
            'matricule' => $node->distributeur_id,
            'nom' => $node->nom_distributeur,
            'prenom' => $node->pnom_distributeur,
            'grade' => $node->etoiles_id ?? 1,
            'rang' => $node->rang ?? 0,
            'validated' => $node->statut_validation_periode == 1,
            'depth' => $currentDepth,
            'children_count' => 0,
            'children' => []
        ];

        if (!isset($options['max_depth']) || $currentDepth < $options['max_depth']) {
            $children = $this->parentChildrenMap->get($node->id, collect());
            $tree['children_count'] = $children->count();

            foreach ($children as $child) {
                $tree['children'][] = $this->buildTreeStructure($child, $options, $currentDepth + 1);
            }
        }

        return $tree;
    }

    /**
     * Calcule la distribution par profondeur
     */
    private function calculateDepthDistribution($rootIdentifier): array
    {
        $distribution = [];
        $currentLevel = collect([$this->resolveDistributorId($rootIdentifier)]);
        $depth = 0;

        while ($currentLevel->isNotEmpty()) {
            $distribution[$depth] = $currentLevel->count();

            $nextLevel = collect();
            foreach ($currentLevel as $parentId) {
                $children = $this->parentChildrenMap->get($parentId, collect());
                foreach ($children as $child) {
                    $nextLevel->push($child->id);
                }
            }

            $currentLevel = $nextLevel;
            $depth++;
        }

        return $distribution;
    }

    /**
     * Génère une clé de cache unique
     */
    private function getCacheKey(int $rootId, array $options): string
    {
        $optionsHash = md5(json_encode($options));
        return "lineage_{$rootId}_{$optionsHash}";
    }

    /**
     * Construit un arbre à partir d'une collection plate
     */
    private function buildTreeFromFlat(Collection $descendants, int $rootId): array
    {
        // Implémenter si nécessaire
        return [];
    }

    /**
     * Analyse chaque branche (lignée) d'un distributeur et compte les filleuls par grade
     *
     * @param int|string $rootIdentifier ID ou matricule du distributeur racine
     * @param array $filters Filtres à appliquer
     * @return array
     */
    public function analyzeBranchesGradeDistribution($rootIdentifier, array $filters = []): array
    {
        $defaultFilters = [
            'min_grade' => null,              // Grade minimum à inclure dans le comptage
            'max_grade' => null,              // Grade maximum à inclure dans le comptage
            'only_validated' => false,        // Compter uniquement les distributeurs validés
            'only_cumul_checked' => false,    // Compter uniquement ceux avec cumul vérifié
            'exclude_grade_1' => false,       // Exclure le grade 1 du comptage
            'min_branch_size' => 0,           // Taille minimale d'une branche pour l'inclure
            'max_depth' => null,              // Profondeur maximale de recherche
            'include_branch_root' => false,   // Inclure l'enfant direct dans le comptage
            'sort_by' => 'total_desc',        // Tri: total_desc, total_asc, grade_X_desc, root_name
            'top_branches' => null,           // Limiter aux N meilleures branches
            'search_term' => null,            // Filtrer par nom dans les branches
            'created_after' => null,          // Date de création minimum
            'detailed_stats' => true,         // Inclure des stats détaillées par branche
        ];

        $filters = array_merge($defaultFilters, $filters);

        // Résoudre l'ID du distributeur racine
        $rootId = $this->resolveDistributorId($rootIdentifier);
        if (!$rootId) {
            Log::warning("Distributeur non trouvé pour l'analyse des branches: {$rootIdentifier}");
            return [];
        }

        // Charger les données si nécessaire
        if (!$this->distributorsMap || !$this->parentChildrenMap) {
            $this->loadDistributorsData();
        }

        // Obtenir les enfants directs (racines des branches)
        $directChildren = $this->parentChildrenMap->get($rootId, collect());

        if ($directChildren->isEmpty()) {
            return [
                'root_distributor' => $this->getDistributorBasicInfo($rootId),
                'total_branches' => 0,
                'branches' => [],
                'summary' => [
                    'total_descendants_all_branches' => 0,
                    'average_branch_size' => 0,
                    'largest_branch_size' => 0,
                    'grades_distribution_all_branches' => []
                ]
            ];
        }

        $branchesAnalysis = [];
        $allGradesCount = [];
        $totalDescendantsAllBranches = 0;

        // Analyser chaque branche
        foreach ($directChildren as $child) {
            $branchAnalysis = $this->analyzeSingleBranch($child, $filters);

            // Appliquer le filtre de taille minimale
            if ($branchAnalysis['total'] < $filters['min_branch_size']) {
                continue;
            }

            $branchesAnalysis[] = $branchAnalysis;
            $totalDescendantsAllBranches += $branchAnalysis['total'];

            // Agréger les comptages de grades
            foreach ($branchAnalysis['grades_count'] as $grade => $count) {
                if (!isset($allGradesCount[$grade])) {
                    $allGradesCount[$grade] = 0;
                }
                $allGradesCount[$grade] += $count;
            }
        }

        // Trier les branches selon le critère choisi
        $branchesAnalysis = $this->sortBranches($branchesAnalysis, $filters['sort_by']);

        // Limiter au top N branches si demandé
        if ($filters['top_branches'] !== null && $filters['top_branches'] > 0) {
            $branchesAnalysis = array_slice($branchesAnalysis, 0, $filters['top_branches']);
        }

        // Calculer les statistiques globales
        $branchSizes = array_column($branchesAnalysis, 'total');
        $summary = [
            'total_descendants_all_branches' => $totalDescendantsAllBranches,
            'average_branch_size' => count($branchSizes) > 0 ? round(array_sum($branchSizes) / count($branchSizes), 2) : 0,
            'largest_branch_size' => count($branchSizes) > 0 ? max($branchSizes) : 0,
            'smallest_branch_size' => count($branchSizes) > 0 ? min($branchSizes) : 0,
            'grades_distribution_all_branches' => $allGradesCount
        ];

        // Identifier les branches les plus qualifiées pour chaque grade
        if ($filters['detailed_stats']) {
            $summary['best_branches_by_grade'] = $this->identifyBestBranchesByGrade($branchesAnalysis);
            $summary['branches_meeting_criteria'] = $this->analyzeBranchesForCriteria($branchesAnalysis);
        }

        return [
            'root_distributor' => $this->getDistributorBasicInfo($rootId),
            'total_branches' => count($directChildren),
            'analyzed_branches' => count($branchesAnalysis),
            'branches' => $branchesAnalysis,
            'summary' => $summary,
            'filters_applied' => $filters
        ];
    }

    /**
     * Analyse une branche unique et compte les grades
     */
    private function analyzeSingleBranch($branchRoot, array $filters): array
    {
        // Obtenir tous les descendants de cette branche
        $options = [
            'use_cache' => false,
            'include_root' => $filters['include_branch_root'],
            'only_active' => $filters['only_validated'],
            'max_depth' => $filters['max_depth'],
            'search_term' => $filters['search_term'],
            'filter_cumul_checked' => $filters['only_cumul_checked'] ? 1 : null,
        ];

        if ($filters['created_after']) {
            $options['created_after'] = $filters['created_after'];
        }

        $descendants = $this->getAllDescendants($branchRoot->id, $options);

        // Compter par grade
        $gradesCount = [];
        $validatedCount = 0;
        $totalCumulIndividuel = 0;
        $maxGradeInBranch = 0;

        foreach ($descendants as $descendant) {
            $grade = $descendant->etoiles_id ?? 1;

            // Appliquer les filtres de grade
            if ($filters['min_grade'] !== null && $grade < $filters['min_grade']) continue;
            if ($filters['max_grade'] !== null && $grade > $filters['max_grade']) continue;
            if ($filters['exclude_grade_1'] && $grade == 1) continue;

            if (!isset($gradesCount[$grade])) {
                $gradesCount[$grade] = 0;
            }
            $gradesCount[$grade]++;

            if ($descendant->statut_validation_periode == 1) {
                $validatedCount++;
            }

            $maxGradeInBranch = max($maxGradeInBranch, $grade);
        }

        // Trier les grades
        ksort($gradesCount);

        $branchInfo = [
            'root' => [
                'id' => $branchRoot->id,
                'matricule' => $branchRoot->distributeur_id,
                'nom' => $branchRoot->nom_distributeur,
                'prenom' => $branchRoot->pnom_distributeur,
                'grade' => $branchRoot->etoiles_id,
                'validated' => $branchRoot->statut_validation_periode == 1
            ],
            'grades_count' => $gradesCount,
            'total' => array_sum($gradesCount),
            'validated_count' => $validatedCount,
            'validation_rate' => array_sum($gradesCount) > 0 ? round(($validatedCount / array_sum($gradesCount)) * 100, 2) : 0,
            'max_grade_in_branch' => $maxGradeInBranch,
            'unique_grades_count' => count($gradesCount),
            'depth' => $this->lastSearchStats['max_depth_reached'] ?? 0
        ];

        // Ajouter des métriques spécifiques si demandé
        if ($filters['detailed_stats']) {
            $branchInfo['grade_progression'] = $this->calculateGradeProgression($gradesCount);
            $branchInfo['qualified_for_grades'] = $this->checkBranchQualificationForGrades($gradesCount);
        }

        return $branchInfo;
    }

    /**
     * Trie les branches selon le critère spécifié
     */
    private function sortBranches(array $branches, string $sortBy): array
    {
        switch ($sortBy) {
            case 'total_asc':
                usort($branches, fn($a, $b) => $a['total'] <=> $b['total']);
                break;

            case 'root_name':
                usort($branches, fn($a, $b) => strcmp($a['root']['nom'], $b['root']['nom']));
                break;

            case 'validation_rate':
                usort($branches, fn($a, $b) => $b['validation_rate'] <=> $a['validation_rate']);
                break;

            case 'max_grade':
                usort($branches, fn($a, $b) => $b['max_grade_in_branch'] <=> $a['max_grade_in_branch']);
                break;

            case 'total_desc':
            default:
                usort($branches, fn($a, $b) => $b['total'] <=> $a['total']);
                break;
        }

        // Pour les tris par grade spécifique (ex: grade_5_desc)
        if (preg_match('/^grade_(\d+)_(desc|asc)$/', $sortBy, $matches)) {
            $targetGrade = (int) $matches[1];
            $direction = $matches[2];

            usort($branches, function($a, $b) use ($targetGrade, $direction) {
                $aCount = $a['grades_count'][$targetGrade] ?? 0;
                $bCount = $b['grades_count'][$targetGrade] ?? 0;
                return $direction === 'desc' ? $bCount <=> $aCount : $aCount <=> $bCount;
            });
        }

        return $branches;
    }

    /**
     * Identifie les meilleures branches pour chaque grade
     */
    private function identifyBestBranchesByGrade(array $branches): array
    {
        $bestByGrade = [];

        foreach ($branches as $branch) {
            foreach ($branch['grades_count'] as $grade => $count) {
                if (!isset($bestByGrade[$grade]) || $count > $bestByGrade[$grade]['count']) {
                    $bestByGrade[$grade] = [
                        'branch_root' => $branch['root'],
                        'count' => $count,
                        'branch_total' => $branch['total']
                    ];
                }
            }
        }

        ksort($bestByGrade);
        return $bestByGrade;
    }

    /**
     * Analyse quelles branches remplissent certains critères de qualification
     */
    private function analyzeBranchesForCriteria(array $branches): array
    {
        $criteria = [
            'has_grade_9' => [],
            'has_3_or_more_grade_7' => [],
            'has_5_or_more_grade_5' => [],
            'qualified_for_advancement' => []
        ];

        foreach ($branches as $branch) {
            // A au moins un grade 9
            if (($branch['grades_count'][9] ?? 0) > 0) {
                $criteria['has_grade_9'][] = $branch['root']['matricule'];
            }

            // A 3 ou plus de grade 7
            if (($branch['grades_count'][7] ?? 0) >= 3) {
                $criteria['has_3_or_more_grade_7'][] = $branch['root']['matricule'];
            }

            // A 5 ou plus de grade 5
            if (($branch['grades_count'][5] ?? 0) >= 5) {
                $criteria['has_5_or_more_grade_5'][] = $branch['root']['matricule'];
            }

            // Branches potentiellement qualifiées pour l'avancement
            $totalHighGrades = 0;
            for ($g = 5; $g <= 11; $g++) {
                $totalHighGrades += $branch['grades_count'][$g] ?? 0;
            }
            if ($totalHighGrades >= 2) {
                $criteria['qualified_for_advancement'][] = [
                    'matricule' => $branch['root']['matricule'],
                    'high_grades_count' => $totalHighGrades
                ];
            }
        }

        return $criteria;
    }

    /**
     * Calcule la progression des grades dans une branche
     */
    private function calculateGradeProgression(array $gradesCount): array
    {
        $progression = [];
        $total = array_sum($gradesCount);

        if ($total == 0) return $progression;

        foreach ($gradesCount as $grade => $count) {
            $progression[$grade] = [
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 2)
            ];
        }

        return $progression;
    }

    /**
     * Vérifie pour quels grades une branche est qualifiée
     */
    private function checkBranchQualificationForGrades(array $gradesCount): array
    {
        $qualifications = [];

        // Règles simplifiées - à adapter selon vos besoins réels
        $rules = [
            4 => ['min_grade_3' => 1],
            5 => ['min_grade_4' => 1],
            6 => ['min_grade_5' => 1],
            7 => ['min_grade_6' => 1],
            8 => ['min_grade_7' => 1],
            9 => ['min_grade_8' => 1],
        ];

        foreach ($rules as $targetGrade => $requirements) {
            $qualified = true;
            foreach ($requirements as $gradeKey => $minCount) {
                $grade = (int) str_replace('min_grade_', '', $gradeKey);
                if (($gradesCount[$grade] ?? 0) < $minCount) {
                    $qualified = false;
                    break;
                }
            }
            $qualifications[$targetGrade] = $qualified;
        }

        return $qualifications;
    }

    /**
     * Obtient les informations de base d'un distributeur
     */
    private function getDistributorBasicInfo(int $distributorId): array
    {
        $distributor = $this->distributorsMap->get($distributorId);
        if (!$distributor) return [];

        return [
            'id' => $distributor->id,
            'matricule' => $distributor->distributeur_id,
            'nom' => $distributor->nom_distributeur,
            'prenom' => $distributor->pnom_distributeur,
            'grade' => $distributor->etoiles_id,
            'rang' => $distributor->rang
        ];
    }

    /**
     * Obtient les statistiques de la dernière recherche
     */
    public function getLastSearchStats(): array
    {
        return $this->lastSearchStats;
    }

    /**
     * Vide le cache de lignée
     */
    public function clearCache(): void
    {
        $this->lineageCache = [];
        Cache::tags(['lineage'])->flush();
    }

    /**
     * Vérifie l'éligibilité d'un distributeur pour l'avancement en grade
     * selon les règles métiers officielles
     *
     * @param int|string $distributorIdentifier ID ou matricule
     * @param string $period Période à vérifier (format YYYY-MM)
     * @param array $options Options supplémentaires
     * @return array
     */
    public function checkGradeEligibility($distributorIdentifier, string $period, array $options = []): array
    {
        $defaultOptions = [
            'target_grade' => null,           // Grade cible spécifique à vérifier
            'check_all_possible' => true,     // Vérifier tous les grades possibles
            'include_details' => true,        // Inclure les détails de chaque condition
            'use_cache' => true,              // Utiliser le cache pour les performances
            'only_validated' => true,         // Considérer uniquement les branches validées
            'debug' => false,                 // Mode debug pour voir les détails
            'stop_on_first_failure' => true   // Arrêter dès le premier échec
        ];

        $options = array_merge($defaultOptions, $options);

        // Valider le format de la période
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return [
                'error' => 'Format de période invalide. Utilisez YYYY-MM',
                'eligible' => false
            ];
        }

        // Obtenir les informations du distributeur
        $distributorId = $this->resolveDistributorId($distributorIdentifier);
        if (!$distributorId) {
            return [
                'error' => 'Distributeur non trouvé',
                'eligible' => false
            ];
        }

        // Récupérer les données du distributeur
        $distributor = Distributeur::find($distributorId);
        if (!$distributor) {
            return [
                'error' => 'Distributeur non trouvé dans la base',
                'eligible' => false
            ];
        }

        // Récupérer les données de niveau pour la période spécifiée
        $levelCurrent = DB::table('level_currents')
            ->where('distributeur_id', $distributorId)
            ->where('period', $period)
            ->first();

        if (!$levelCurrent) {
            return [
                'error' => 'Aucune donnée de niveau pour la période ' . $period,
                'eligible' => false,
                'distributor' => [
                    'id' => $distributorId,
                    'matricule' => $distributor->distributeur_id,
                    'nom' => $distributor->nom_distributeur,
                    'prenom' => $distributor->pnom_distributeur
                ],
                'period' => $period
            ];
        }

        $currentGrade = $levelCurrent->etoiles;
        $cumulIndividuel = $levelCurrent->cumul_individuel;
        $cumulCollectif = $levelCurrent->cumul_collectif;

        // Analyser les branches
        $branchAnalysis = $this->analyzeBranchesGradeDistribution($distributor->distributeur_id, [
            'only_validated' => $options['only_validated'],
            'detailed_stats' => true
        ]);

        // Mode debug : afficher l'analyse des branches
        if ($options['debug']) {
            Log::info("Branch Analysis for {$distributor->distributeur_id}:", [
                'branches_count' => count($branchAnalysis['branches']),
                'branches' => array_map(function($branch) {
                    return [
                        'root_matricule' => $branch['root']['matricule'],
                        'root_grade' => $branch['root']['grade'],
                        'grades_in_branch' => $branch['grades_count']
                    ];
                }, $branchAnalysis['branches'])
            ]);
        }

        $result = [
            'distributor' => [
                'id' => $distributorId,
                'matricule' => $distributor->distributeur_id,
                'nom' => $distributor->nom_distributeur,
                'prenom' => $distributor->pnom_distributeur,
                'current_grade' => $currentGrade,
                'cumul_individuel' => $cumulIndividuel,
                'cumul_collectif' => $cumulCollectif
            ],
            'period' => $period,
            'eligibilities' => [],
            'stopped_at_grade' => null  // Nouveau : indique où on s'est arrêté
        ];

        // Ajouter les détails de debug si demandé
        if ($options['debug']) {
            $result['debug'] = [
                'branch_analysis' => $branchAnalysis,
                'branch_counts_by_grade' => $this->countBranchesByGrade($branchAnalysis)
            ];
        }

        // Définir les grades à vérifier
        if ($options['target_grade']) {
            $gradesToCheck = [$options['target_grade']];
        } elseif ($options['check_all_possible']) {
            // Vérifier tous les grades possibles depuis le grade actuel
            $gradesToCheck = range($currentGrade + 1, min($currentGrade + 3, 11));
        } else {
            $gradesToCheck = [$currentGrade + 1];
        }

        // Vérifier l'éligibilité pour chaque grade
        $hasFailedPreviousGrade = false;

        foreach ($gradesToCheck as $targetGrade) {
            // Si on a activé stop_on_first_failure et qu'on a déjà échoué, arrêter
            if ($options['stop_on_first_failure'] && $hasFailedPreviousGrade) {
                // Marquer les grades suivants comme non vérifiés
                $result['eligibilities'][$targetGrade] = [
                    'eligible' => false,
                    'reason' => 'Non vérifié - échec au grade ' . ($targetGrade - 1),
                    'skipped' => true
                ];
                continue;
            }

            $eligibility = $this->checkSpecificGradeEligibility(
                $currentGrade,
                $targetGrade,
                $cumulIndividuel,
                $cumulCollectif,
                $branchAnalysis,
                $options['include_details']
            );

            $result['eligibilities'][$targetGrade] = $eligibility;

            // Si non éligible, marquer pour les prochaines itérations
            if (!$eligibility['eligible']) {
                $hasFailedPreviousGrade = true;
                if ($result['stopped_at_grade'] === null) {
                    $result['stopped_at_grade'] = $targetGrade;
                }
            }
        }

        // Déterminer le grade maximum atteignable
        $result['max_achievable_grade'] = $currentGrade;
        foreach ($result['eligibilities'] as $grade => $eligibility) {
            // Ne pas considérer les grades qui ont été sautés
            if (!isset($eligibility['skipped']) && $eligibility['eligible'] && $grade > $result['max_achievable_grade']) {
                $result['max_achievable_grade'] = $grade;
            }
        }

        $result['can_advance'] = $result['max_achievable_grade'] > $currentGrade;

        // Ajouter un résumé si on s'est arrêté tôt
        if ($options['stop_on_first_failure'] && $result['stopped_at_grade']) {
            $result['optimization_summary'] = "Vérification arrêtée au grade {$result['stopped_at_grade']} (échec). Les grades supérieurs n'ont pas été vérifiés en détail.";
        }

        return $result;
    }

    /**
     * Vérifie l'éligibilité pour un grade spécifique
     */
    private function checkSpecificGradeEligibility(
        int $currentGrade,
        int $targetGrade,
        float $cumulIndividuel,
        float $cumulCollectif,
        array $branchAnalysis,
        bool $includeDetails = true
    ): array {
        // Règles pour chaque grade
        $gradeRules = $this->getGradeRules();

        if (!isset($gradeRules[$targetGrade])) {
            return [
                'eligible' => false,
                'reason' => 'Grade non défini dans les règles'
            ];
        }

        $rules = $gradeRules[$targetGrade];
        $eligibility = [
            'eligible' => false,
            'qualified_options' => [],
            'missing_requirements' => []
        ];

        // Vérifier les prérequis de base
        if (isset($rules['min_current_grade']) && $currentGrade < $rules['min_current_grade']) {
            $eligibility['missing_requirements'][] = 'Grade actuel insuffisant (minimum grade ' . $rules['min_current_grade'] . ' requis)';
            return $eligibility;
        }

        // Compter les branches par grade
        $branchCounts = $this->countBranchesByGrade($branchAnalysis);

        // Vérifier chaque option
        foreach ($rules['options'] as $optionIndex => $option) {
            $optionQualified = true;
            $optionDetails = [
                'option' => $optionIndex + 1,
                'description' => $option['description'] ?? '',
                'requirements' => [],
                'status' => []
            ];

            // Vérifier le cumul individuel si requis
            if (isset($option['cumul_individuel'])) {
                $hasRequiredCumul = $cumulIndividuel >= $option['cumul_individuel'];
                $optionDetails['requirements'][] = 'Cumul individuel ≥ ' . number_format($option['cumul_individuel']);
                $optionDetails['status'][] = [
                    'requirement' => 'Cumul individuel',
                    'required' => $option['cumul_individuel'],
                    'actual' => $cumulIndividuel,
                    'met' => $hasRequiredCumul
                ];

                if (!$hasRequiredCumul) {
                    $optionQualified = false;
                }
            }

            // Vérifier le cumul collectif si requis
            if (isset($option['cumul_collectif'])) {
                $hasRequiredCumul = $cumulCollectif >= $option['cumul_collectif'];
                $optionDetails['requirements'][] = 'Cumul collectif ≥ ' . number_format($option['cumul_collectif']);
                $optionDetails['status'][] = [
                    'requirement' => 'Cumul collectif',
                    'required' => $option['cumul_collectif'],
                    'actual' => $cumulCollectif,
                    'met' => $hasRequiredCumul
                ];

                if (!$hasRequiredCumul) {
                    $optionQualified = false;
                }
            }

            // Vérifier les branches requises
            if (isset($option['branches'])) {
                foreach ($option['branches'] as $branchReq) {
                    $requiredGrade = $branchReq['grade'];
                    $requiredCount = $branchReq['count'];
                    $actualCount = $branchCounts[$requiredGrade] ?? 0;
                    $hasBranches = $actualCount >= $requiredCount;

                    $optionDetails['requirements'][] = $requiredCount . ' branches avec grade ' . $requiredGrade;
                    $optionDetails['status'][] = [
                        'requirement' => 'Branches grade ' . $requiredGrade,
                        'required' => $requiredCount,
                        'actual' => $actualCount,
                        'met' => $hasBranches
                    ];

                    if (!$hasBranches) {
                        $optionQualified = false;
                    }
                }
            }

            $optionDetails['qualified'] = $optionQualified;

            if ($optionQualified) {
                $eligibility['eligible'] = true;
                $eligibility['qualified_options'][] = $optionDetails;
            } elseif ($includeDetails) {
                $eligibility['all_options'][] = $optionDetails;
            }
        }

        // Ajouter un résumé des manques si non éligible
        if (!$eligibility['eligible'] && $includeDetails) {
            $eligibility['summary'] = $this->generateEligibilitySummary(
                $targetGrade,
                $cumulIndividuel,
                $cumulCollectif,
                $branchCounts,
                $rules
            );
        }

        return $eligibility;
    }

    /**
     * Définit les règles métiers pour chaque grade
     */
    private function getGradeRules(): array
    {
        return [
            2 => [
                'min_current_grade' => 1,
                'options' => [
                    [
                        'description' => 'Cumul individuel de 100',
                        'cumul_individuel' => 100
                    ]
                ]
            ],
            3 => [
                'min_current_grade' => 2,
                'options' => [
                    [
                        'description' => 'Cumul individuel de 200',
                        'cumul_individuel' => 200
                    ]
                ]
            ],
            4 => [
                'min_current_grade' => 3,
                'options' => [
                    [
                        'description' => 'Cumul individuel de 1000',
                        'cumul_individuel' => 1000
                    ],
                    [
                        'description' => '2 branches grade 3 + cumul collectif 2200',
                        'cumul_collectif' => 2200,
                        'branches' => [
                            ['grade' => 3, 'count' => 2]
                        ]
                    ],
                    [
                        'description' => '3 branches grade 3 + cumul collectif 1000',
                        'cumul_collectif' => 1000,
                        'branches' => [
                            ['grade' => 3, 'count' => 3]
                        ]
                    ]
                ]
            ],
            5 => [
                'min_current_grade' => 3,
                'options' => [
                    [
                        'description' => '2 branches grade 4 + cumul collectif 7800',
                        'cumul_collectif' => 7800,
                        'branches' => [
                            ['grade' => 4, 'count' => 2]
                        ]
                    ],
                    [
                        'description' => '3 branches grade 4 + cumul collectif 3800',
                        'cumul_collectif' => 3800,
                        'branches' => [
                            ['grade' => 4, 'count' => 3]
                        ]
                    ],
                    [
                        'description' => '2 branches grade 4 + 4 branches grade 3 + cumul collectif 3800',
                        'cumul_collectif' => 3800,
                        'branches' => [
                            ['grade' => 4, 'count' => 2],
                            ['grade' => 3, 'count' => 4]
                        ]
                    ],
                    [
                        'description' => '1 branche grade 4 + 6 branches grade 3 + cumul collectif 3800',
                        'cumul_collectif' => 3800,
                        'branches' => [
                            ['grade' => 4, 'count' => 1],
                            ['grade' => 3, 'count' => 6]
                        ]
                    ]
                ]
            ],
            6 => [
                'min_current_grade' => 3,
                'options' => [
                    [
                        'description' => '2 branches grade 5 + cumul collectif 35000',
                        'cumul_collectif' => 35000,
                        'branches' => [
                            ['grade' => 5, 'count' => 2]
                        ]
                    ],
                    [
                        'description' => '3 branches grade 5 + cumul collectif 16000',
                        'cumul_collectif' => 16000,
                        'branches' => [
                            ['grade' => 5, 'count' => 3]
                        ]
                    ],
                    [
                        'description' => '2 branches grade 5 + 4 branches grade 4 + cumul collectif 16000',
                        'cumul_collectif' => 16000,
                        'branches' => [
                            ['grade' => 5, 'count' => 2],
                            ['grade' => 4, 'count' => 4]
                        ]
                    ],
                    [
                        'description' => '1 branche grade 5 + 6 branches grade 4 + cumul collectif 16000',
                        'cumul_collectif' => 16000,
                        'branches' => [
                            ['grade' => 5, 'count' => 1],
                            ['grade' => 4, 'count' => 6]
                        ]
                    ]
                ]
            ],
            7 => [
                'min_current_grade' => 3,
                'options' => [
                    [
                        'description' => '2 branches grade 6 + cumul collectif 145000',
                        'cumul_collectif' => 145000,
                        'branches' => [
                            ['grade' => 6, 'count' => 2]
                        ]
                    ],
                    [
                        'description' => '3 branches grade 6 + cumul collectif 73000',
                        'cumul_collectif' => 73000,
                        'branches' => [
                            ['grade' => 6, 'count' => 3]
                        ]
                    ],
                    [
                        'description' => '2 branches grade 6 + 4 branches grade 5 + cumul collectif 73000',
                        'cumul_collectif' => 73000,
                        'branches' => [
                            ['grade' => 6, 'count' => 2],
                            ['grade' => 5, 'count' => 4]
                        ]
                    ],
                    [
                        'description' => '1 branche grade 6 + 6 branches grade 5 + cumul collectif 73000',
                        'cumul_collectif' => 73000,
                        'branches' => [
                            ['grade' => 6, 'count' => 1],
                            ['grade' => 5, 'count' => 6]
                        ]
                    ]
                ]
            ],
            8 => [
                'min_current_grade' => 3,
                'options' => [
                    [
                        'description' => '2 branches grade 7 + cumul collectif 580000',
                        'cumul_collectif' => 580000,
                        'branches' => [
                            ['grade' => 7, 'count' => 2]
                        ]
                    ],
                    [
                        'description' => '3 branches grade 7 + cumul collectif 280000',
                        'cumul_collectif' => 280000,
                        'branches' => [
                            ['grade' => 7, 'count' => 3]
                        ]
                    ],
                    [
                        'description' => '2 branches grade 7 + 4 branches grade 6 + cumul collectif 280000',
                        'cumul_collectif' => 280000,
                        'branches' => [
                            ['grade' => 7, 'count' => 2],
                            ['grade' => 6, 'count' => 4]
                        ]
                    ],
                    [
                        'description' => '1 branche grade 7 + 6 branches grade 6 + cumul collectif 280000',
                        'cumul_collectif' => 280000,
                        'branches' => [
                            ['grade' => 7, 'count' => 1],
                            ['grade' => 6, 'count' => 6]
                        ]
                    ]
                ]
            ],
            9 => [
                'min_current_grade' => 3,
                'options' => [
                    [
                        'description' => '2 branches grade 8 + cumul collectif 780000',
                        'cumul_collectif' => 780000,
                        'branches' => [
                            ['grade' => 8, 'count' => 2]
                        ]
                    ],
                    [
                        'description' => '3 branches grade 8 + cumul collectif 400000',
                        'cumul_collectif' => 400000,
                        'branches' => [
                            ['grade' => 8, 'count' => 3]
                        ]
                    ],
                    [
                        'description' => '2 branches grade 8 + 4 branches grade 7 + cumul collectif 400000',
                        'cumul_collectif' => 400000,
                        'branches' => [
                            ['grade' => 8, 'count' => 2],
                            ['grade' => 7, 'count' => 4]
                        ]
                    ],
                    [
                        'description' => '1 branche grade 8 + 6 branches grade 7 + cumul collectif 400000',
                        'cumul_collectif' => 400000,
                        'branches' => [
                            ['grade' => 8, 'count' => 1],
                            ['grade' => 7, 'count' => 6]
                        ]
                    ]
                ]
            ],
            10 => [
                'min_current_grade' => 3,
                'options' => [
                    [
                        'description' => '2 branches grade 9',
                        'branches' => [
                            ['grade' => 9, 'count' => 2]
                        ]
                    ]
                ]
            ],
            11 => [
                'min_current_grade' => 3,
                'options' => [
                    [
                        'description' => '3 branches grade 9',
                        'branches' => [
                            ['grade' => 9, 'count' => 3]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Compte les branches par grade minimum atteint
     * Une branche est comptée pour un grade X si elle contient au moins un membre de grade X ou supérieur
     */
    private function countBranchesByGrade(array $branchAnalysis): array
    {
        $counts = [];

        // Initialiser les compteurs pour tous les grades
        for ($grade = 1; $grade <= 11; $grade++) {
            $counts[$grade] = 0;
        }

        // Pour chaque branche
        foreach ($branchAnalysis['branches'] as $branch) {
            // Déterminer le grade maximum dans cette branche
            $maxGradeInBranch = 0;

            // D'abord vérifier le grade du root de la branche lui-même
            if (isset($branch['root']['grade'])) {
                $maxGradeInBranch = $branch['root']['grade'];
            }

            // Puis vérifier tous les grades dans grades_count
            foreach ($branch['grades_count'] as $grade => $count) {
                if ($count > 0 && $grade > $maxGradeInBranch) {
                    $maxGradeInBranch = $grade;
                }
            }

            // Si la branche a un grade maximum, la compter pour tous les grades <= à ce maximum
            if ($maxGradeInBranch > 0) {
                for ($g = 1; $g <= $maxGradeInBranch; $g++) {
                    $counts[$g]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Génère un résumé des manques pour l'éligibilité
     */
    private function generateEligibilitySummary(
        int $targetGrade,
        float $cumulIndividuel,
        float $cumulCollectif,
        array $branchCounts,
        array $rules
    ): array {
        $summary = [
            'closest_option' => null,
            'minimum_requirements' => [],
            'recommendations' => []
        ];

        $closestScore = 0;

        foreach ($rules['options'] as $optionIndex => $option) {
            $score = 0;
            $missing = [];

            // Évaluer le cumul individuel
            if (isset($option['cumul_individuel'])) {
                if ($cumulIndividuel >= $option['cumul_individuel']) {
                    $score += 1;
                } else {
                    $missing[] = 'Cumul individuel: manque ' .
                        number_format($option['cumul_individuel'] - $cumulIndividuel);
                }
            }

            // Évaluer le cumul collectif
            if (isset($option['cumul_collectif'])) {
                $ratio = $cumulCollectif / $option['cumul_collectif'];
                $score += min(1, $ratio);

                if ($ratio < 1) {
                    $missing[] = 'Cumul collectif: manque ' .
                        number_format($option['cumul_collectif'] - $cumulCollectif);
                }
            }

            // Évaluer les branches
            if (isset($option['branches'])) {
                foreach ($option['branches'] as $branchReq) {
                    $actual = $branchCounts[$branchReq['grade']] ?? 0;
                    $ratio = $actual / $branchReq['count'];
                    $score += min(1, $ratio);

                    if ($ratio < 1) {
                        $missing[] = 'Branches grade ' . $branchReq['grade'] . ': manque ' .
                            ($branchReq['count'] - $actual);
                    }
                }
            }

            if ($score > $closestScore) {
                $closestScore = $score;
                $summary['closest_option'] = [
                    'option' => $optionIndex + 1,
                    'description' => $option['description'],
                    'missing' => $missing,
                    'completion_percentage' => round(($score / count($option)) * 100, 2)
                ];
            }
        }

        // Recommandations générales
        if ($cumulCollectif < 1000) {
            $summary['recommendations'][] = 'Augmenter significativement le cumul collectif';
        }

        // Identifier les grades de branches manquants
        $highestBranchGrade = 0;
        for ($g = 11; $g >= 1; $g--) {
            if (($branchCounts[$g] ?? 0) > 0) {
                $highestBranchGrade = $g;
                break;
            }
        }

        if ($highestBranchGrade < $targetGrade - 2) {
            $summary['recommendations'][] = 'Développer des branches avec des grades plus élevés';
        }

        return $summary;
    }

    /**
     * Vérifie l'avancement automatique d'un distributeur
     * et retourne le grade maximum qu'il devrait avoir
     *
     * @param int|string $distributorIdentifier ID ou matricule
     * @param string $period Période à vérifier (format YYYY-MM)
     * @param array $options Options supplémentaires
     * @return array
     */
    public function calculateAutomaticGrade($distributorIdentifier, string $period, array $options = []): array
    {
        $eligibility = $this->checkGradeEligibility($distributorIdentifier, $period, [
            'check_all_possible' => true,
            'include_details' => $options['include_details'] ?? true,
            'only_validated' => $options['only_validated'] ?? true
        ]);

        if (isset($eligibility['error'])) {
            return $eligibility;
        }

        $currentGrade = $eligibility['distributor']['current_grade'];
        $shouldBeGrade = $currentGrade;
        $promotionPath = [];

        // Simuler les promotions successives
        $simulatedGrade = $currentGrade;
        while ($simulatedGrade < 11) {
            $nextGrade = $simulatedGrade + 1;

            if (isset($eligibility['eligibilities'][$nextGrade]) &&
                $eligibility['eligibilities'][$nextGrade]['eligible']) {
                $shouldBeGrade = $nextGrade;
                $promotionPath[] = [
                    'from' => $simulatedGrade,
                    'to' => $nextGrade,
                    'qualified_options' => $eligibility['eligibilities'][$nextGrade]['qualified_options']
                ];
                $simulatedGrade = $nextGrade;

                // Re-vérifier pour les grades suivants si nécessaire
                if ($nextGrade < 11) {
                    $newEligibility = $this->checkGradeEligibility($distributorIdentifier, $period, [
                        'target_grade' => $nextGrade + 1,
                        'include_details' => false
                    ]);

                    if (isset($newEligibility['eligibilities'][$nextGrade + 1])) {
                        $eligibility['eligibilities'][$nextGrade + 1] = $newEligibility['eligibilities'][$nextGrade + 1];
                    }
                }
            } else {
                break;
            }
        }

        return [
            'distributor' => $eligibility['distributor'],
            'period' => $period,
            'current_grade' => $currentGrade,
            'calculated_grade' => $shouldBeGrade,
            'needs_promotion' => $shouldBeGrade > $currentGrade,
            'promotion_path' => $promotionPath,
            'detailed_eligibilities' => $options['include_details'] ? $eligibility['eligibilities'] : null
        ];
    }

    /**
     * Vérifie l'éligibilité de plusieurs distributeurs pour une période
     *
     * @param array $distributorIdentifiers Liste de matricules ou IDs
     * @param string $period Période à vérifier
     * @param array $options Options
     * @return array
     */
    public function checkMultipleEligibilities(array $distributorIdentifiers, string $period, array $options = []): array
    {
        $results = [
            'period' => $period,
            'total_checked' => count($distributorIdentifiers),
            'eligible_for_promotion' => [],
            'not_eligible' => [],
            'errors' => [],
            'summary' => [
                'by_target_grade' => [],
                'total_eligible' => 0
            ]
        ];

        foreach ($distributorIdentifiers as $identifier) {
            try {
                $check = $this->calculateAutomaticGrade($identifier, $period, [
                    'include_details' => $options['include_details'] ?? false
                ]);

                if (isset($check['error'])) {
                    $results['errors'][] = [
                        'identifier' => $identifier,
                        'error' => $check['error']
                    ];
                    continue;
                }

                if ($check['needs_promotion']) {
                    $results['eligible_for_promotion'][] = [
                        'matricule' => $check['distributor']['matricule'],
                        'nom' => $check['distributor']['nom'],
                        'prenom' => $check['distributor']['prenom'],
                        'current_grade' => $check['current_grade'],
                        'new_grade' => $check['calculated_grade'],
                        'promotion_path' => $check['promotion_path']
                    ];

                    // Compter par grade cible
                    $targetGrade = $check['calculated_grade'];
                    if (!isset($results['summary']['by_target_grade'][$targetGrade])) {
                        $results['summary']['by_target_grade'][$targetGrade] = 0;
                    }
                    $results['summary']['by_target_grade'][$targetGrade]++;
                    $results['summary']['total_eligible']++;
                } else {
                    $results['not_eligible'][] = [
                        'matricule' => $check['distributor']['matricule'],
                        'nom' => $check['distributor']['nom'],
                        'current_grade' => $check['current_grade']
                    ];
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'identifier' => $identifier,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Trier les résultats
        usort($results['eligible_for_promotion'], function($a, $b) {
            return $b['new_grade'] <=> $a['new_grade']; // Trier par nouveau grade décroissant
        });

        return $results;
    }

    /**
     * Obtient un rapport d'éligibilité détaillé pour une période
     *
     * @param string $period Période à analyser
     * @param array $filters Filtres optionnels
     * @return array
     */
    public function generateEligibilityReport(string $period, array $filters = []): array
    {
        $defaultFilters = [
            'min_grade' => null,
            'max_grade' => null,
            'only_active' => true,
            'limit' => null,
            'check_specific_grades' => []
        ];

        $filters = array_merge($defaultFilters, $filters);

        // Récupérer les distributeurs à vérifier
        $query = DB::table('level_currents as lc')
            ->join('distributeurs as d', 'lc.distributeur_id', '=', 'd.id')
            ->where('lc.period', $period);

        if ($filters['min_grade']) {
            $query->where('lc.etoiles', '>=', $filters['min_grade']);
        }

        if ($filters['max_grade']) {
            $query->where('lc.etoiles', '<=', $filters['max_grade']);
        }

        if ($filters['only_active']) {
            $query->where('d.statut_validation_periode', 1);
        }

        if ($filters['limit']) {
            $query->limit($filters['limit']);
        }

        $distributors = $query->select('d.distributeur_id', 'lc.etoiles')->get();

        // Analyser chaque distributeur
        $report = [
            'period' => $period,
            'generated_at' => now()->toISOString(),
            'filters_applied' => $filters,
            'total_analyzed' => $distributors->count(),
            'promotions_by_grade' => [],
            'detailed_promotions' => [],
            'statistics' => [
                'total_eligible' => 0,
                'by_current_grade' => [],
                'by_qualification_method' => []
            ]
        ];

        foreach ($distributors as $distributor) {
            $eligibility = $this->checkGradeEligibility(
                $distributor->distributeur_id,
                $period,
                ['include_details' => true]
            );

            if ($eligibility['can_advance']) {
                $report['statistics']['total_eligible']++;

                // Statistiques par grade actuel
                $currentGrade = $eligibility['distributor']['current_grade'];
                if (!isset($report['statistics']['by_current_grade'][$currentGrade])) {
                    $report['statistics']['by_current_grade'][$currentGrade] = 0;
                }
                $report['statistics']['by_current_grade'][$currentGrade]++;

                // Détails de la promotion
                $maxGrade = $eligibility['max_achievable_grade'];
                $promotion = [
                    'matricule' => $eligibility['distributor']['matricule'],
                    'nom' => $eligibility['distributor']['nom'],
                    'current_grade' => $currentGrade,
                    'new_grade' => $maxGrade,
                    'qualified_by' => []
                ];

                // Récupérer les méthodes de qualification
                if (isset($eligibility['eligibilities'][$maxGrade]['qualified_options'])) {
                    foreach ($eligibility['eligibilities'][$maxGrade]['qualified_options'] as $option) {
                        $promotion['qualified_by'][] = $option['description'];

                        // Statistiques par méthode
                        $method = $option['description'];
                        if (!isset($report['statistics']['by_qualification_method'][$method])) {
                            $report['statistics']['by_qualification_method'][$method] = 0;
                        }
                        $report['statistics']['by_qualification_method'][$method]++;
                    }
                }

                $report['detailed_promotions'][] = $promotion;

                // Grouper par nouveau grade
                if (!isset($report['promotions_by_grade'][$maxGrade])) {
                    $report['promotions_by_grade'][$maxGrade] = [];
                }
                $report['promotions_by_grade'][$maxGrade][] = $promotion;
            }
        }

        return $report;
    }
}
