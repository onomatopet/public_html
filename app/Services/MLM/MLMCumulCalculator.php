<?php

namespace App\Services\MLM;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Achat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MLMCumulCalculator
{
    protected ?string $currentPeriod = null;
    protected array $cache = [];
    protected array $processedNodes = [];

    /**
     * Calculer le cumul individuel d'un distributeur pour une période
     */
    public function calculateIndividualCumul(int $distributeurId, string $period): float
    {
        // Récupérer tous les achats du distributeur pour cette période
        $totalPoints = Achat::where('distributeur_id', $distributeurId)
            ->where('period', $period)
            ->where('status', 'active')
            ->sum(DB::raw('points_unitaire_achat * qt'));

        return round($totalPoints, 2);
    }

    /**
     * Calculer le cumul collectif d'un distributeur pour une période
     */
    public function calculateCollectiveCumul(int $distributeurId, string $period): float
    {
        $this->currentPeriod = $period;
        $this->processedNodes = [];

        // Obtenir le cumul individuel
        $individualCumul = $this->getIndividualCumul($distributeurId, $period);

        // Obtenir la somme des cumuls individuels de tous les descendants
        $descendantsCumul = $this->calculateDescendantsCumul($distributeurId, $period);

        return round($individualCumul + $descendantsCumul, 2);
    }

    /**
     * Obtenir le cumul individuel depuis level_currents
     */
    protected function getIndividualCumul(int $distributeurId, string $period): float
    {
        $record = LevelCurrent::where('distributeur_id', $distributeurId)
            ->where('period', $period)
            ->first();

        return $record ? $record->cumul_individuel : 0;
    }

    /**
     * Calculer la somme des cumuls individuels des descendants
     */
    protected function calculateDescendantsCumul(int $distributeurId, string $period): float
    {
        // Éviter les boucles infinies
        if (in_array($distributeurId, $this->processedNodes)) {
            Log::warning("Circular reference detected for distributeur {$distributeurId}");
            return 0;
        }

        $this->processedNodes[] = $distributeurId;

        // Utiliser une requête récursive pour obtenir tous les descendants
        $descendants = $this->getAllDescendantsWithCumuls($distributeurId, $period);

        $totalDescendantsCumul = 0;
        foreach ($descendants as $descendant) {
            $totalDescendantsCumul += $descendant->cumul_individuel;
        }

        return $totalDescendantsCumul;
    }

    /**
     * Obtenir tous les descendants avec leurs cumuls
     */
    protected function getAllDescendantsWithCumuls(int $distributeurId, string $period): Collection
    {
        // Requête récursive CTE pour obtenir tous les descendants
        $sql = "
            WITH RECURSIVE descendants AS (
                -- Cas de base : enfants directs
                SELECT d.id, d.id_distrib_parent, 1 as level
                FROM distributeurs d
                WHERE d.id_distrib_parent = ?

                UNION ALL

                -- Cas récursif : descendants des enfants
                SELECT d.id, d.id_distrib_parent, desc.level + 1
                FROM distributeurs d
                INNER JOIN descendants desc ON d.id_distrib_parent = desc.id
                WHERE desc.level < 20  -- Limite de sécurité pour éviter les boucles infinies
            )
            SELECT
                desc.id as distributeur_id,
                desc.level,
                COALESCE(lc.cumul_individuel, 0) as cumul_individuel
            FROM descendants desc
            LEFT JOIN level_currents lc ON desc.id = lc.distributeur_id AND lc.period = ?
            ORDER BY desc.level, desc.id
        ";

        return collect(DB::select($sql, [$distributeurId, $period]));
    }

    /**
     * Recalculer tous les cumuls pour une période
     */
    public function recalculateCumulsForPeriod(string $period, ?callable $progressCallback = null): array
    {
        $stats = [
            'total' => 0,
            'updated_individual' => 0,
            'updated_collective' => 0,
            'errors' => 0
        ];

        // D'abord, recalculer tous les cumuls individuels
        $this->recalculateIndividualCumuls($period, $stats, $progressCallback);

        // Ensuite, recalculer les cumuls collectifs de bas en haut
        $this->recalculateCollectiveCumuls($period, $stats, $progressCallback);

        return $stats;
    }

    /**
     * Recalculer les cumuls individuels
     */
    protected function recalculateIndividualCumuls(string $period, array &$stats, ?callable $progressCallback = null): void
    {
        $totalRecords = LevelCurrent::where('period', $period)->count();
        $processed = 0;

        LevelCurrent::where('period', $period)
            ->chunk(100, function($records) use ($period, &$stats, &$processed, $totalRecords, $progressCallback) {
                foreach ($records as $record) {
                    try {
                        $calculatedCumul = $this->calculateIndividualCumul($record->distributeur_id, $period);

                        if (abs($calculatedCumul - $record->cumul_individuel) > 0.01) {
                            $record->cumul_individuel = $calculatedCumul;
                            $record->save();
                            $stats['updated_individual']++;
                        }

                        $stats['total']++;
                        $processed++;

                        if ($progressCallback && $processed % 50 == 0) {
                            $progressCallback($processed, $totalRecords, 'individual');
                        }

                    } catch (\Exception $e) {
                        Log::error("Error calculating individual cumul for distributeur {$record->distributeur_id}: " . $e->getMessage());
                        $stats['errors']++;
                    }
                }
            });
    }

    /**
     * Recalculer les cumuls collectifs de bas en haut
     */
    protected function recalculateCollectiveCumuls(string $period, array &$stats, ?callable $progressCallback = null): void
    {
        // Obtenir l'ordre de traitement (des feuilles vers la racine)
        $processingOrder = $this->getBottomUpProcessingOrder();
        $total = count($processingOrder);
        $processed = 0;

        foreach ($processingOrder as $distributeurId) {
            try {
                $record = LevelCurrent::where('distributeur_id', $distributeurId)
                    ->where('period', $period)
                    ->first();

                if (!$record) {
                    continue;
                }

                // Calculer le cumul collectif
                $calculatedCollectif = $this->calculateCollectiveCumulOptimized($distributeurId, $period);

                if (abs($calculatedCollectif - $record->cumul_collectif) > 0.01) {
                    $record->cumul_collectif = $calculatedCollectif;
                    $record->save();
                    $stats['updated_collective']++;
                }

                $processed++;

                if ($progressCallback && $processed % 50 == 0) {
                    $progressCallback($processed, $total, 'collective');
                }

            } catch (\Exception $e) {
                Log::error("Error calculating collective cumul for distributeur {$distributeurId}: " . $e->getMessage());
                $stats['errors']++;
            }
        }
    }

    /**
     * Obtenir l'ordre de traitement bottom-up
     */
    protected function getBottomUpProcessingOrder(): array
    {
        // Utiliser une requête pour obtenir les distributeurs ordonnés par niveau dans l'arbre
        $sql = "
            WITH RECURSIVE tree_levels AS (
                -- Racines (distributeurs sans parent)
                SELECT id, id_distrib_parent, 0 as level
                FROM distributeurs
                WHERE id_distrib_parent IS NULL

                UNION ALL

                -- Descendants
                SELECT d.id, d.id_distrib_parent, tl.level + 1
                FROM distributeurs d
                INNER JOIN tree_levels tl ON d.id_distrib_parent = tl.id
            )
            SELECT id
            FROM tree_levels
            ORDER BY level DESC, id
        ";

        $results = DB::select($sql);

        return array_map(function($row) {
            return $row->id;
        }, $results);
    }

    /**
     * Calculer le cumul collectif de manière optimisée
     */
    protected function calculateCollectiveCumulOptimized(int $distributeurId, string $period): float
    {
        // Si déjà en cache
        $cacheKey = "collective_cumul_{$distributeurId}_{$period}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Obtenir le cumul individuel
        $record = LevelCurrent::where('distributeur_id', $distributeurId)
            ->where('period', $period)
            ->first();

        if (!$record) {
            return 0;
        }

        $individualCumul = $record->cumul_individuel;

        // Obtenir la somme des cumuls individuels des enfants directs uniquement
        // (car leurs cumuls collectifs incluent déjà leurs descendants)
        $childrenCumul = LevelCurrent::where('period', $period)
            ->whereIn('distributeur_id', function($query) use ($distributeurId) {
                $query->select('id')
                    ->from('distributeurs')
                    ->where('id_distrib_parent', $distributeurId);
            })
            ->sum('cumul_individuel');

        $collectiveCumul = $individualCumul + $childrenCumul;

        // Mettre en cache
        $this->cache[$cacheKey] = $collectiveCumul;

        return round($collectiveCumul, 2);
    }

    /**
     * Valider la cohérence des cumuls
     */
    public function validateCumulCoherence(int $distributeurId, string $period): array
    {
        $errors = [];

        $record = LevelCurrent::where('distributeur_id', $distributeurId)
            ->where('period', $period)
            ->first();

        if (!$record) {
            return ['error' => 'Aucun enregistrement trouvé'];
        }

        // 1. Vérifier que le cumul individuel n'est pas négatif
        if ($record->cumul_individuel < 0) {
            $errors[] = [
                'type' => 'negative_individual',
                'message' => 'Le cumul individuel est négatif',
                'value' => $record->cumul_individuel
            ];
        }

        // 2. Vérifier que le cumul collectif >= cumul individuel
        if ($record->cumul_collectif < $record->cumul_individuel) {
            $errors[] = [
                'type' => 'collective_less_than_individual',
                'message' => 'Le cumul collectif est inférieur au cumul individuel',
                'individual' => $record->cumul_individuel,
                'collective' => $record->cumul_collectif
            ];
        }

        // 3. Recalculer et comparer
        $calculatedIndividual = $this->calculateIndividualCumul($distributeurId, $period);
        $calculatedCollective = $this->calculateCollectiveCumul($distributeurId, $period);

        if (abs($calculatedIndividual - $record->cumul_individuel) > 0.01) {
            $errors[] = [
                'type' => 'individual_mismatch',
                'message' => 'Le cumul individuel ne correspond pas au calcul',
                'stored' => $record->cumul_individuel,
                'calculated' => $calculatedIndividual,
                'difference' => $calculatedIndividual - $record->cumul_individuel
            ];
        }

        if (abs($calculatedCollective - $record->cumul_collectif) > 0.01) {
            $errors[] = [
                'type' => 'collective_mismatch',
                'message' => 'Le cumul collectif ne correspond pas au calcul',
                'stored' => $record->cumul_collectif,
                'calculated' => $calculatedCollective,
                'difference' => $calculatedCollective - $record->cumul_collectif
            ];
        }

        return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
    }

    /**
     * Propager les cumuls dans la hiérarchie
     */
    public function propagateCumuls(int $distributeurId, string $period): array
    {
        $stats = [
            'affected' => 0,
            'updated' => 0
        ];

        // Obtenir tous les ancêtres
        $ancestors = $this->getAncestors($distributeurId);

        foreach ($ancestors as $ancestorId) {
            $record = LevelCurrent::where('distributeur_id', $ancestorId)
                ->where('period', $period)
                ->first();

            if ($record) {
                $oldCollectif = $record->cumul_collectif;
                $newCollectif = $this->calculateCollectiveCumul($ancestorId, $period);

                if (abs($newCollectif - $oldCollectif) > 0.01) {
                    $record->cumul_collectif = $newCollectif;
                    $record->save();
                    $stats['updated']++;
                }

                $stats['affected']++;
            }
        }

        return $stats;
    }

    /**
     * Obtenir tous les ancêtres d'un distributeur
     */
    protected function getAncestors(int $distributeurId): array
    {
        $ancestors = [];
        $current = Distributeur::find($distributeurId);

        while ($current && $current->id_distrib_parent) {
            $ancestors[] = $current->id_distrib_parent;
            $current = Distributeur::find($current->id_distrib_parent);

            // Protection contre les boucles infinies
            if (count($ancestors) > 100) {
                Log::warning("Possible infinite loop detected for distributeur {$distributeurId}");
                break;
            }
        }

        return $ancestors;
    }
}
