<?php

// Dans un service, ex: app/Services/PurchaseAggregationService.php
namespace App\Services;

use App\Models\Achat;
use App\Models\LevelCurrent;
use App\Models\Distributeur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PurchaseAggregationService
{
    /**
     * Agrège les achats et met à jour/crée les enregistrements de niveau pour une période.
     * - new_cumul = achats de la période
     * - cumul_individuel = ancien_cumul_individuel + achats_période
     * - cumul_total = ancien_cumul_total + achats_période
     * - cumul_collectif = ancien_cumul_collectif + achats_période (suite à la nouvelle règle)
     *
     * @param string $period
     * @return array ['message' => string, 'active_distributors_details' => Collection]
     *         active_distributors_details contient les objets LevelCurrent des distributeurs actifs
     */
    public function aggregateAndApplyPurchases(string $period): array
    {
        Log::info("[PAA] Début du traitement des achats pour la période: {$period}");

        $achatsAgreges = Achat::selectRaw('distributeur_id, SUM(points_unitaire_achat * qt) as total_new_achats')
            ->where('period', $period)
            ->groupBy('distributeur_id')
            ->havingRaw('SUM(points_unitaire_achat * qt) > 0')
            ->get()
            ->keyBy('distributeur_id');

        if ($achatsAgreges->isEmpty()) {
            Log::info("[PAA] Aucun achat trouvé pour la période {$period}.");
            return ['message' => "Aucun achat à traiter pour {$period}.", 'active_distributors_details' => collect()];
        }
        Log::info("[PAA] Achats agrégés pour {$achatsAgreges->count()} distributeurs.");

        $existingLevels = LevelCurrent::where('period', $period)
            ->whereIn('distributeur_id', $achatsAgreges->keys())
            ->get()
            ->keyBy('distributeur_id');

        $distributeursACreerIds = $achatsAgreges->keys()->diff($existingLevels->keys());
        $updatesData = [];
        $insertsData = [];
        $distributorIdsActifs = $achatsAgreges->keys()->toArray(); // Pour récupérer les modèles complets plus tard

        // Préparer les mises à jour
        foreach ($existingLevels as $distribId => $level) {
            if ($achatsAgreges->has($distribId)) {
                $nouveauxAchats = (float) $achatsAgreges->get($distribId)->total_new_achats;
                $updatesData[] = [
                    'distributeur_id' => $distribId,
                    'period' => $period,
                    'new_cumul_assign' => $nouveauxAchats,
                    'cumul_individuel_increment' => $nouveauxAchats,
                    'cumul_total_increment' => $nouveauxAchats,
                    'cumul_collectif_increment' => $nouveauxAchats, // MODIFIÉ: les achats directs incrémentent aussi le collectif
                ];
            }
        }

        // Préparer les insertions
        if ($distributeursACreerIds->isNotEmpty()) {
            $distributeursInfo = Distributeur::whereIn('id', $distributeursACreerIds)
                ->select('id', 'distributeur_id', 'id_distrib_parent', 'rang')
                ->get()
                ->keyBy('id');

            foreach ($distributeursACreerIds as $distribId) {
                $achat = $achatsAgreges->get($distribId);
                $distribInfo = $distributeursInfo->get($distribId);
                if ($achat && $distribInfo) {
                    $nouveauxAchats = (float) $achat->total_new_achats;
                    $insertsData[] = [
                        'distributeur_id' => $distribId,
                        'period' => $period,
                        'rang' => $distribInfo->rang ?? null,
                        'etoiles' => 1,
                        'cumul_individuel' => $nouveauxAchats,
                        'new_cumul' => $nouveauxAchats,
                        'cumul_total' => $nouveauxAchats,
                        'cumul_collectif' => $nouveauxAchats, // MODIFIÉ: initialisé avec les achats directs
                        'id_distrib_parent' => $distribInfo->id_distrib_parent ?? null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                } else {
                    Log::warning("[PAA] Infos manquantes pour créer LevelCurrent pour distrib_id {$distribId} période {$period}.");
                }
            }
        }

        $updatedCount = 0;
        $insertedCount = 0;

        try {
            DB::beginTransaction();
            if (!empty($updatesData)) {
                foreach ($updatesData as $data) {
                    $affected = LevelCurrent::where('distributeur_id', $data['distributeur_id'])
                        ->where('period', $data['period'])
                        ->update([
                            'new_cumul' => $data['new_cumul_assign'],
                            'cumul_individuel' => DB::raw("cumul_individuel + " . $data['cumul_individuel_increment']),
                            'cumul_total' => DB::raw("cumul_total + " . $data['cumul_total_increment']),
                            'cumul_collectif' => DB::raw("cumul_collectif + " . $data['cumul_collectif_increment']), // MODIFIÉ
                            'updated_at' => Carbon::now(),
                        ]);
                    if ($affected > 0) $updatedCount++;
                }
            }
            if (!empty($insertsData)) {
                foreach (array_chunk($insertsData, 500) as $chunk) {
                    LevelCurrent::insert($chunk);
                    $insertedCount += count($chunk);
                }
            }
            DB::commit();

            // Récupérer les modèles LevelCurrent complets pour les distributeurs actifs pour le retour
            $activeDistributorsDetails = LevelCurrent::where('period', $period)
                ->whereIn('distributeur_id', $distributorIdsActifs)
                ->get();

            Log::info("[PAA] Traitement achats terminé pour {$period}. MàJ:{$updatedCount}, Ins:{$insertedCount}.");
            return [
                'message' => "Achats appliqués pour {$period}. MàJ:{$updatedCount}, Ins:{$insertedCount}.",
                'active_distributors_details' => $activeDistributorsDetails // Retourne les modèles LevelCurrent
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[PAA] Erreur traitement achats pour {$period}: " . $e->getMessage(), ['exception' => $e]);
            return [
                'message' => "Erreur lors de l'application des achats pour {$period}.",
                'active_distributors_details' => collect(),
                'error' => $e->getMessage()
            ];
        }
    }
}
