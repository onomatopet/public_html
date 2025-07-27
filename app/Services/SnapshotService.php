<?php

namespace App\Services;

use App\Models\LevelCurrent;
use App\Models\LevelCurrentHistory; // Utiliser le bon nom du modèle existant
use App\Models\Distributeur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service pour créer des snapshots des niveaux des distributeurs.
 * Un snapshot est une copie de l'état des distributeurs à un moment donné.
 */
class SnapshotService
{
    /**
     * Crée un snapshot de l'état actuel des distributeurs pour une période donnée.
     *
     * @param string $period Période au format 'YYYY-MM'.
     * @param bool $force Optional: Si true, écrase un snapshot existant pour cette période. Défaut: false.
     * @return array ['success' => bool, 'message' => string, 'count' => int]
     */
    public function createSnapshot(string $period, bool $force = false): array
    {
        Log::info("Tentative de création de snapshot pour la période: {$period}");

        // --- Validation ---
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $message = "Format de période invalide. Utilisez YYYY-MM.";
            Log::warning($message);
            return ['success' => false, 'message' => $message, 'count' => 0];
        }

        // Vérifier si un snapshot existe déjà pour cette période
        $existingSnapshot = LevelCurrentHistory::where('period', $period)->exists();

        if ($existingSnapshot && !$force) {
            $message = "Un snapshot existe déjà pour la période {$period}. Utilisez l'option 'forcer' pour écraser.";
            Log::warning($message);
            return ['success' => false, 'message' => $message, 'count' => 0];
        }

        // --- Préparation ---
        $snapshotTimestamp = Carbon::now();
        $recordsCreated = 0;

        // Utiliser une transaction pour assurer l'intégrité
        DB::beginTransaction();

        try {
            // Si force=true, supprimer l'ancien snapshot
            if ($existingSnapshot && $force) {
                Log::warning("Suppression du snapshot existant pour la période {$period} (Forcé).");
                LevelCurrentHistory::where('period', $period)->delete();
            }

            // --- Lecture et Écriture par Chunks ---
            // Utiliser chunkById pour traiter les données par lots et éviter les problèmes de mémoire
            Distributeur::orderBy('id') // Important pour chunkById
                ->chunkById(500, function ($distributors) use ($period, $snapshotTimestamp, &$recordsCreated) {

                    $historyData = [];
                    foreach ($distributors as $distributor) {
                        // Préparer les données pour l'insertion dans level_history
                        $historyData[] = [
                            'distributeur_id'            => $distributor->distributeur_id,
                            'rang'                       => $distributor->rang,
                            'current_id'                 => $distributor->current_id,
                            'period'                     => $period,
                            'etoiles'                    => $distributor->etoiles, // Grade actuel au moment du snapshot
                            'cumul_individuel'           => $distributor->cumul_individuel, // Cumul actuel
                            'new_cumul'                  => $distributor->new_cumul,
                            'cumul_total'                => $distributor->cumul_total,
                            'cumul_collectif'            => $distributor->cumul_collectif, // Cumul collectif actuel
                            'id_distrib_parent'          => $distributor->id_distrib_parent,
                            'is_children'                => $distributor->is_children,
                            'is_indivual_cumul_checked'  => $distributor->is_indivual_cumul_checked	,
                            'snapshot_date'              => $snapshotTimestamp,
                            'created_at'                 => $distributor->created_at,
                            'updated_at'                 => $distributor->updated_at,

                            // IMPORTANT: Ne pas inclure created_at/updated_at ici si
                            // la table history n'a pas ces colonnes ou si elles sont gérées par la DB.
                            // Si elles existent et doivent être définies :
                            // 'created_at' => $snapshotTimestamp,
                            // 'updated_at' => $snapshotTimestamp,
                        ];
                    }

                    // Insérer le lot dans level_history
                    if (!empty($historyData)) {
                        LevelCurrentHistory::insert($historyData); // Insertion en masse (plus rapide)
                        $recordsCreated += count($historyData);
                        Log::debug("Snapshot: Lot de " . count($historyData) . " enregistrements inséré pour la période {$period}. Total: {$recordsCreated}");
                    }
                });

            // Si tout s'est bien passé, valider la transaction
            DB::commit();
            $message = "Snapshot créé avec succès pour la période {$period}. {$recordsCreated} enregistrements ajoutés.";
            Log::info($message);
            return ['success' => true, 'message' => $message, 'count' => $recordsCreated];

        } catch (\Exception $e) {
            // En cas d'erreur, annuler la transaction
            DB::rollBack();
            $message = "Erreur lors de la création du snapshot pour la période {$period}: " . $e->getMessage();
            Log::error($message, ['exception' => $e]);
            return ['success' => false, 'message' => "Une erreur est survenue. Consultez les logs.", 'count' => 0];
        }
    }

     /**
      * Optionnel: Méthode pour recalculer les grades avant snapshot.
      * À appeler explicitement si nécessaire avant createSnapshot.
      */
    /*
    public function ensureLatestRanks(DistributorRankService $rankService)
    {
        Log::info("Recalcul des grades avant snapshot...");
        Distributor::chunkById(200, function($distributors) use ($rankService) {
            foreach ($distributors as $distributor) {
                $potentialRank = $rankService->calculatePotentialRank($distributor);
                if ($potentialRank != $distributor->etoiles) {
                    $distributor->etoiles = $potentialRank;
                    $distributor->save(); // Sauvegarde individuelle ici
                }
            }
        });
        Log::info("Fin du recalcul des grades.");
    }
    */
}
