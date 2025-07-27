<?php
// app/Services/CumulManagementService.php

namespace App\Services;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Achat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CumulManagementService
{
    /**
     * Propage un montant dans toute la chaîne parentale
     *
     * @param int $distributeurId ID du distributeur source
     * @param float $amount Montant à propager
     * @param string $period Période concernée
     */
    public function propagateToParents(int $distributeurId, float $amount, string $period): void
    {
        // Récupérer le distributeur source
        $distributeur = Distributeur::find($distributeurId);
        if (!$distributeur || !$distributeur->id_distrib_parent) {
            return; // Pas de parent, rien à propager
        }

        // Parcourir la chaîne parentale
        $currentParentId = $distributeur->id_distrib_parent;
        $visitedParents = []; // Protection contre les boucles infinies
        $maxDepth = 50; // Limite de profondeur
        $depth = 0;

        while ($currentParentId && !in_array($currentParentId, $visitedParents) && $depth < $maxDepth) {
            $visitedParents[] = $currentParentId;

            // Mettre à jour le cumul_collectif du parent
            $this->updateParentCumuls($currentParentId, $amount, $period);

            // Récupérer le parent du parent
            $parent = Distributeur::find($currentParentId);
            $currentParentId = $parent ? $parent->id_distrib_parent : null;
            $depth++;
        }

        Log::info("Propagation terminée", [
            'source' => $distributeurId,
            'amount' => $amount,
            'period' => $period,
            'parents_updated' => count($visitedParents),
            'max_depth_reached' => $depth >= $maxDepth
        ]);
    }

    /**
     * Met à jour les cumuls d'un parent
     */
    protected function updateParentCumuls(int $parentId, float $amount, string $period): void
    {
        // Récupérer ou créer le level_current du parent
        $levelCurrent = LevelCurrent::firstOrCreate(
            [
                'distributeur_id' => $parentId,
                'period' => $period
            ],
            [
                'rang' => 0,
                'etoiles' => 1,
                'cumul_individuel' => 0,
                'new_cumul' => 0,
                'cumul_total' => 0,
                'cumul_collectif' => 0,
                'id_distrib_parent' => Distributeur::find($parentId)->id_distrib_parent
            ]
        );

        // Incrémenter le cumul_collectif (qui inclut les ventes de toute la descendance)
        $levelCurrent->increment('cumul_collectif', $amount);

        // Le cumul_total de la période inclut aussi les ventes des filleuls
        $levelCurrent->increment('cumul_total', $amount);

        Log::debug("Cumuls parent mis à jour", [
            'parent_id' => $parentId,
            'period' => $period,
            'amount_added' => $amount,
            'new_cumul_collectif' => $levelCurrent->cumul_collectif,
            'new_cumul_total' => $levelCurrent->cumul_total
        ]);
    }

    /**
     * Recalcule tous les cumuls collectifs pour une période (utile pour corrections)
     */
    public function recalculateAllCollectiveCumuls(string $period): array
    {
        $startTime = microtime(true);
        $processedCount = 0;

        DB::beginTransaction();
        try {
            // 1. Réinitialiser tous les cumul_collectif à la valeur du cumul_individuel
            LevelCurrent::where('period', $period)
                       ->update([
                           'cumul_collectif' => DB::raw('cumul_individuel'),
                           'cumul_total' => DB::raw('new_cumul')
                       ]);

            // 2. Récupérer tous les distributeurs ayant des ventes
            $distributorsWithSales = LevelCurrent::where('period', $period)
                                                ->where('new_cumul', '>', 0)
                                                ->orderBy('distributeur_id')
                                                ->get();

            // 3. Pour chaque distributeur, propager ses ventes dans sa chaîne parentale
            foreach ($distributorsWithSales as $level) {
                $this->propagateToParents(
                    $level->distributeur_id,
                    $level->new_cumul,
                    $period
                );
                $processedCount++;
            }

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'message' => "Recalcul terminé en {$duration}s",
                'processed' => $processedCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors du recalcul des cumuls collectifs", [
                'period' => $period,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Gère la suppression d'un distributeur et le transfert de ses cumuls
     */
    public function handleDistributeurDeletion(Distributeur $distributeur, string $currentPeriod): array
    {
        DB::beginTransaction();
        try {
            $parentId = $distributeur->id_distrib_parent;
            $transferredAmount = 0;

            if ($parentId) {
                // Récupérer le cumul_collectif du distributeur à supprimer
                $levelCurrent = LevelCurrent::where('distributeur_id', $distributeur->id)
                                          ->where('period', $currentPeriod)
                                          ->first();

                if ($levelCurrent && $levelCurrent->cumul_collectif > 0) {
                    // Transférer le cumul au parent
                    $parentLevel = LevelCurrent::firstOrCreate(
                        [
                            'distributeur_id' => $parentId,
                            'period' => $currentPeriod
                        ],
                        [
                            'rang' => 0,
                            'etoiles' => 1,
                            'cumul_individuel' => 0,
                            'new_cumul' => 0,
                            'cumul_total' => 0,
                            'cumul_collectif' => 0,
                            'id_distrib_parent' => Distributeur::find($parentId)->id_distrib_parent
                        ]
                    );

                    $parentLevel->increment('cumul_collectif', $levelCurrent->cumul_collectif);
                    $transferredAmount = $levelCurrent->cumul_collectif;
                }

                // Réaffecter tous les enfants directs au parent
                Distributeur::where('id_distrib_parent', $distributeur->id)
                          ->update(['id_distrib_parent' => $parentId]);

                // Mettre à jour aussi dans level_currents
                LevelCurrent::where('id_distrib_parent', $distributeur->id)
                          ->update(['id_distrib_parent' => $parentId]);
            }

            DB::commit();

            return [
                'success' => true,
                'transferred_amount' => $transferredAmount,
                'affected_parent' => $parentId,
                'message' => "Cumuls transférés avec succès"
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors du transfert: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Optimise la récupération de l'arbre hiérarchique avec cache
     */
    public function getHierarchyTree(int $distributeurId, int $maxDepth = 3): array
    {
        $cacheKey = "hierarchy_{$distributeurId}_depth_{$maxDepth}";

        return Cache::remember($cacheKey, 3600, function() use ($distributeurId, $maxDepth) {
            return $this->buildHierarchyTree($distributeurId, 0, $maxDepth);
        });
    }

    /**
     * Construit récursivement l'arbre hiérarchique
     */
    protected function buildHierarchyTree(int $distributeurId, int $currentDepth, int $maxDepth): array
    {
        if ($currentDepth >= $maxDepth) {
            return [];
        }

        $children = Distributeur::where('id_distrib_parent', $distributeurId)
                              ->select('id', 'distributeur_id', 'nom_distributeur', 'pnom_distributeur', 'etoiles_id')
                              ->get();

        $tree = [];
        foreach ($children as $child) {
            $tree[] = [
                'id' => $child->id,
                'matricule' => $child->distributeur_id,
                'nom' => $child->nom_distributeur . ' ' . $child->pnom_distributeur,
                'grade' => $child->etoiles_id,
                'children' => $this->buildHierarchyTree($child->id, $currentDepth + 1, $maxDepth)
            ];
        }

        return $tree;
    }

    /**
     * Invalide le cache pour un distributeur et ses parents
     */
    public function invalidateHierarchyCache(int $distributeurId): void
    {
        // Invalider le cache du distributeur
        Cache::forget("hierarchy_{$distributeurId}_*");

        // Invalider le cache de tous ses parents
        $currentId = $distributeurId;
        $maxDepth = 20;
        $depth = 0;

        while ($currentId && $depth < $maxDepth) {
            $parent = Distributeur::find($currentId);
            if ($parent && $parent->id_distrib_parent) {
                Cache::forget("hierarchy_{$parent->id_distrib_parent}_*");
                $currentId = $parent->id_distrib_parent;
            } else {
                break;
            }
            $depth++;
        }
    }
}
