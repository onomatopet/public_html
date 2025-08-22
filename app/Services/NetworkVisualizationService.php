<?php

namespace App\Services;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\SystemPeriod;
use Illuminate\Support\Collection;

class NetworkVisualizationService
{
    /**
     * Génère les données pour la visualisation en arbre
     */
    public function generateTreeData(int $rootId, int $maxDepth = 3): array
    {
        $root = Distributeur::find($rootId);

        if (!$root) {
            return [];
        }

        $currentPeriod = SystemPeriod::getCurrentPeriod();

        return $this->buildNode($root, $currentPeriod->period, 1, $maxDepth);
    }

    /**
     * Construit un nœud de l'arbre récursivement
     */
    protected function buildNode(Distributeur $distributeur, string $period, int $currentDepth, int $maxDepth): array
    {
        // Récupérer les performances actuelles
        $level = LevelCurrent::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        $node = [
            'id' => $distributeur->id,
            'name' => $distributeur->nom_distributeur . ' ' . $distributeur->pnom_distributeur,
            'matricule' => $distributeur->distributeur_id,
            'grade' => $distributeur->etoiles_id,
            'pv' => $level->pv ?? 0,
            'pg' => $level->pg ?? 0,
            'active' => $distributeur->statut_validation_periode,
            'level' => $currentDepth,
            'children' => []
        ];

        // Si on n'a pas atteint la profondeur max, récupérer les enfants
        if ($currentDepth < $maxDepth) {
            $children = $distributeur->children()
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($children as $child) {
                $node['children'][] = $this->buildNode($child, $period, $currentDepth + 1, $maxDepth);
            }
        } else {
            // Indiquer s'il y a des enfants non affichés
            $node['has_more_children'] = $distributeur->children()->exists();
        }

        return $node;
    }

    /**
     * Génère les données pour un graphique en sunburst
     */
    public function generateSunburstData(int $rootId, int $maxDepth = 4): array
    {
        $root = Distributeur::find($rootId);

        if (!$root) {
            return [];
        }

        return $this->buildSunburstNode($root, 1, $maxDepth);
    }

    /**
     * Construit un nœud sunburst récursivement
     */
    protected function buildSunburstNode(Distributeur $distributeur, int $currentDepth, int $maxDepth): array
    {
        $node = [
            'name' => $distributeur->nom_distributeur,
            'value' => 1, // Peut être remplacé par PV, volume, etc.
            'children' => []
        ];

        if ($currentDepth < $maxDepth) {
            $children = $distributeur->children()->get();

            foreach ($children as $child) {
                $childNode = $this->buildSunburstNode($child, $currentDepth + 1, $maxDepth);
                $node['children'][] = $childNode;
                $node['value'] += $childNode['value'];
            }
        }

        return $node;
    }

    /**
     * Génère les données pour une matrice de réseau
     */
    public function generateNetworkMatrix(int $rootId, int $levels = 3): array
    {
        $matrix = [];
        $currentLevel = [$rootId];

        for ($level = 0; $level < $levels; $level++) {
            if (empty($currentLevel)) {
                break;
            }

            $levelData = Distributeur::whereIn('id', $currentLevel)
                ->with(['levelCurrent' => function($query) {
                    $query->where('period', SystemPeriod::getCurrentPeriod()->period);
                }])
                ->get();

            $matrix[$level] = $levelData->map(function ($dist) {
                $level = $dist->levelCurrent->first();

                return [
                    'id' => $dist->id,
                    'name' => $dist->nom_distributeur . ' ' . $dist->pnom_distributeur,
                    'matricule' => $dist->distributeur_id,
                    'grade' => $dist->etoiles_id,
                    'pv' => $level->pv ?? 0,
                    'pg' => $level->pg ?? 0,
                    'children_count' => $dist->children()->count()
                ];
            })->toArray();

            // Préparer le niveau suivant
            $nextLevel = Distributeur::whereIn('id_distrib_parent', $currentLevel)
                ->pluck('id')
                ->toArray();

            $currentLevel = $nextLevel;
        }

        return $matrix;
    }

    /**
     * Calcule les statistiques par niveau de profondeur
     */
    public function calculateDepthStats(int $rootId, int $maxDepth = 10): array
    {
        $stats = [];
        $currentLevel = [$rootId];
        $depth = 0;

        while (!empty($currentLevel) && $depth < $maxDepth) {
            $levelCount = count($currentLevel);

            // Récupérer les statistiques de ce niveau
            $levelStats = DB::table('distributeurs as d')
                ->leftJoin('level_currents as lc', function($join) {
                    $join->on('d.id', '=', 'lc.distributeur_id')
                         ->where('lc.period', '=', SystemPeriod::getCurrentPeriod()->period);
                })
                ->whereIn('d.id', $currentLevel)
                ->selectRaw('
                    COUNT(d.id) as count,
                    AVG(d.etoiles_id) as avg_grade,
                    SUM(lc.pv) as total_pv,
                    SUM(lc.pg) as total_pg,
                    COUNT(CASE WHEN d.statut_validation_periode = 1 THEN 1 END) as active_count
                ')
                ->first();

            $stats[] = [
                'level' => $depth,
                'count' => $levelCount,
                'avg_grade' => round($levelStats->avg_grade ?? 0, 2),
                'total_pv' => $levelStats->total_pv ?? 0,
                'total_pg' => $levelStats->total_pg ?? 0,
                'active_count' => $levelStats->active_count ?? 0,
                'active_rate' => $levelCount > 0 ? round(($levelStats->active_count / $levelCount) * 100, 2) : 0
            ];

            // Préparer le niveau suivant
            $currentLevel = Distributeur::whereIn('id_distrib_parent', $currentLevel)
                ->pluck('id')
                ->toArray();

            $depth++;
        }

        return $stats;
    }

    /**
     * Génère un graphique de force pour visualiser les connexions
     */
    public function generateForceGraphData(int $rootId, int $maxNodes = 100): array
    {
        $nodes = [];
        $links = [];
        $visited = [];
        $queue = [$rootId];

        while (!empty($queue) && count($nodes) < $maxNodes) {
            $currentId = array_shift($queue);

            if (in_array($currentId, $visited)) {
                continue;
            }

            $visited[] = $currentId;
            $distributeur = Distributeur::find($currentId);

            if (!$distributeur) {
                continue;
            }

            // Ajouter le nœud
            $nodes[] = [
                'id' => $distributeur->id,
                'name' => $distributeur->nom_distributeur . ' ' . $distributeur->pnom_distributeur,
                'grade' => $distributeur->etoiles_id,
                'group' => $distributeur->etoiles_id // Pour la coloration
            ];

            // Ajouter les liens vers les enfants
            $children = $distributeur->children()->pluck('id')->toArray();

            foreach ($children as $childId) {
                $links[] = [
                    'source' => $distributeur->id,
                    'target' => $childId,
                    'value' => 1
                ];

                if (!in_array($childId, $visited)) {
                    $queue[] = $childId;
                }
            }
        }

        return [
            'nodes' => $nodes,
            'links' => $links
        ];
    }
}
