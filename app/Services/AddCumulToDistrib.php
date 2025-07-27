<?php

namespace App\Services;

use App\Models\Achat;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AddCumulToDistrib
{
    /**
     * Traite les achats et met à jour les niveaux pour une période donnée
     *
     * IMPORTANT:
     * - cumul_total = performance du réseau pour la période courante (se réinitialise chaque mois)
     * - new_cumul = achats personnels de la période courante (se réinitialise chaque mois)
     * - cumul_individuel = cumul historique des achats personnels (ne se réinitialise jamais)
     * - cumul_collectif = cumul historique personnel + réseau (ne se réinitialise jamais)
     */
    public function processAchatsAndLevels(string $period)
    {
        Log::info("Début du traitement des achats pour la période: {$period}");

        // 1. Récupérer tous les achats agrégés pour la période
        $achatsAgreges = Achat::selectRaw('distributeur_id, SUM(pointvaleur) as total_new_achats')
            ->where('period', $period)
            ->groupBy('distributeur_id')
            ->havingRaw('SUM(pointvaleur) > 0')
            ->get()
            ->keyBy('distributeur_id');

        if ($achatsAgreges->isEmpty()) {
            Log::info("Aucun achat trouvé pour la période {$period}.");
            return "Aucun achat à traiter pour la période {$period}.";
        }

        // 2. Récupérer les enregistrements existants
        $existingLevels = LevelCurrent::where('period', $period)
            ->whereIn('distributeur_id', $achatsAgreges->keys())
            ->get()
            ->keyBy('distributeur_id');

        $updates = [];
        $inserts = [];
        $distributeursACreerIds = [];

        // 3. Préparer les mises à jour et insertions
        foreach ($achatsAgreges as $distribId => $achat) {
            $nouveauxAchats = (float) $achat->total_new_achats;

            if ($existingLevel = $existingLevels->get($distribId)) {
                // Mise à jour d'un enregistrement existant
                $updates[] = [
                    'distributeur_id' => $distribId,
                    'period' => $period,
                    'cumul_individuel_increment' => $nouveauxAchats,
                    'new_cumul_assign' => $nouveauxAchats, // Remplace la valeur existante
                    'cumul_total_assign' => $nouveauxAchats, // Pour cette période, cumul_total = achats du mois
                    'cumul_collectif_increment' => $nouveauxAchats,
                ];
            } else {
                // Nouvelle entrée à créer
                $distributeursACreerIds[] = $distribId;
            }
        }

        // 4. Récupérer les infos pour les nouvelles créations
        $distributeursPourCreation = collect();
        if (!empty($distributeursACreerIds)) {
            $distributeursPourCreation = Distributeur::whereIn('distributeur_id', $distributeursACreerIds)
                ->select('distributeur_id', 'id_distrib_parent', 'rang', 'etoiles_id')
                ->get()
                ->keyBy('distributeur_id');
        }

        // 5. Préparer les données d'insertion
        foreach ($distributeursACreerIds as $distribId) {
            $achatInfo = $achatsAgreges->get($distribId);
            $distribInfo = $distributeursPourCreation->get($distribId);

            if ($achatInfo && $distribInfo) {
                $nouveauxAchats = (float) $achatInfo->total_new_achats;

                // Pour une nouvelle période, on initialise avec les achats du mois
                $inserts[] = [
                    'distributeur_id' => $distribId,
                    'period' => $period,
                    'rang' => $distribInfo->rang ?? 0,
                    'etoiles' => $distribInfo->etoiles_id ?? 1,
                    'cumul_individuel' => $nouveauxAchats, // Premier achat = cumul individuel
                    'new_cumul' => $nouveauxAchats, // Achats du mois
                    'cumul_total' => $nouveauxAchats, // Performance réseau du mois = achats personnels au début
                    'cumul_collectif' => $nouveauxAchats, // Cumul collectif = achats personnels au début
                    'id_distrib_parent' => $distribInfo->id_distrib_parent ?? null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        // 6. Exécuter les opérations
        $updatedCount = 0;
        $insertedCount = 0;

        try {
            DB::beginTransaction();

            // Exécuter les mises à jour
            if (!empty($updates)) {
                foreach ($updates as $updateData) {
                    $affectedRows = LevelCurrent::where('distributeur_id', $updateData['distributeur_id'])
                        ->where('period', $updateData['period'])
                        ->update([
                            'cumul_individuel' => DB::raw("cumul_individuel + " . $updateData['cumul_individuel_increment']),
                            'new_cumul' => $updateData['new_cumul_assign'], // Assignation directe
                            'cumul_total' => $updateData['cumul_total_assign'], // Assignation directe pour la période
                            'cumul_collectif' => DB::raw("cumul_collectif + " . $updateData['cumul_collectif_increment']),
                            'updated_at' => Carbon::now(),
                        ]);

                    if ($affectedRows > 0) {
                        $updatedCount++;
                    }
                }
            }

            // Exécuter les insertions
            if (!empty($inserts)) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    LevelCurrent::insert($chunk);
                    $insertedCount += count($chunk);
                }
            }

            DB::commit();

            Log::info("Traitement terminé pour {$period}. Mises à jour: {$updatedCount}, Insertions: {$insertedCount}");

            return [
                'success' => true,
                'message' => "Traitement réussi pour la période {$period}",
                'updated' => $updatedCount,
                'inserted' => $insertedCount
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur traitement achats pour {$period}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => "Erreur lors du traitement: " . $e->getMessage()
            ];
        }
    }

    /**
     * Met à jour le cumul_total en calculant la performance du réseau
     * Cette méthode devrait être appelée après le traitement des achats individuels
     */
    public function updateCumulTotalFromNetwork(string $period)
    {
        Log::info("Mise à jour du cumul_total (performance réseau) pour la période: {$period}");

        try {
            // Pour chaque distributeur ayant des données pour cette période
            $distributeurs = LevelCurrent::where('period', $period)->get();

            foreach ($distributeurs as $level) {
                // Calculer la somme des new_cumul de tous les enfants directs et indirects
                $networkPerformance = $this->calculateNetworkPerformance($level->distributeur_id, $period);

                // cumul_total = performance personnelle (new_cumul) + performance du réseau
                $cumulTotal = $level->new_cumul + $networkPerformance;

                $level->update(['cumul_total' => $cumulTotal]);

                Log::debug("Cumul_total mis à jour pour distributeur {$level->distributeur_id}: {$cumulTotal}");
            }

            return [
                'success' => true,
                'message' => "Cumul_total mis à jour pour tous les distributeurs de la période {$period}"
            ];

        } catch (\Exception $e) {
            Log::error("Erreur mise à jour cumul_total: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erreur: " . $e->getMessage()
            ];
        }
    }

    /**
     * Calcule la performance du réseau d'un distributeur pour une période
     */
    private function calculateNetworkPerformance($distributeurId, $period)
    {
        // Récupérer tous les enfants (directs et indirects)
        $childrenIds = $this->getAllChildrenIds($distributeurId);

        if (empty($childrenIds)) {
            return 0;
        }

        // Sommer les new_cumul de tous les enfants pour cette période
        return LevelCurrent::where('period', $period)
            ->whereIn('distributeur_id', $childrenIds)
            ->sum('new_cumul');
    }

    /**
     * Récupère récursivement tous les IDs des enfants d'un distributeur
     */
    private function getAllChildrenIds($distributeurId)
    {
        $allChildrenIds = [];

        // Récupérer les enfants directs
        $directChildren = Distributeur::where('id_distrib_parent', $distributeurId)
            ->pluck('id')
            ->toArray();

        $allChildrenIds = array_merge($allChildrenIds, $directChildren);

        // Récursion pour les enfants des enfants
        foreach ($directChildren as $childId) {
            $grandChildrenIds = $this->getAllChildrenIds($childId);
            $allChildrenIds = array_merge($allChildrenIds, $grandChildrenIds);
        }

        return array_unique($allChildrenIds);
    }
}
