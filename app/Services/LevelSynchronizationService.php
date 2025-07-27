<?php

namespace App\Services;

use App\Models\Distributeur;      // Votre modèle pour la table 'distributeurs'
use App\Models\Level_current_test; // Votre modèle pour la table 'level_current_tests'
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LevelSynchronizationService
{
    /**
     * S'assure que tous les distributeurs de la table 'distributeurs' ont une entrée
     * dans 'level_current_tests' pour la période donnée.
     * Crée les entrées manquantes avec des valeurs par défaut.
     *
     * @param string $period La période à synchroniser (YYYY-MM).
     * @return array Résumé de l'opération.
     */
    public function ensureDistributeurLevelsExist(string $period): array
    {
        Log::info("[SYNC] Début de la synchronisation des niveaux pour la période: {$period}");

        // --- 1. Récupérer tous les matricules des distributeurs de la table de référence ---
        // On ne récupère que les matricules pour optimiser la mémoire
        $allMasterDistributeurMatricules = Distributeur::select('distributeur_id')->pluck('distributeur_id');

        if ($allMasterDistributeurMatricules->isEmpty()) {
            Log::info("[SYNC] Aucun distributeur trouvé dans la table de référence 'distributeurs'.");
            return ['message' => 'Aucun distributeur de référence trouvé.', 'created_count' => 0];
        }
        Log::info("[SYNC] {$allMasterDistributeurMatricules->count()} distributeurs trouvés dans la table de référence.");

        // --- 2. Récupérer les matricules des distributeurs déjà présents dans level_current_tests pour cette période ---
        $existingLevelMatricules = Level_current_test::where('period', $period)
            ->pluck('distributeur_id');

        Log::info("[SYNC] {$existingLevelMatricules->count()} distributeurs déjà présents dans level_current_tests pour la période {$period}.");

        // --- 3. Identifier les matricules manquants ---
        $missingMatricules = $allMasterDistributeurMatricules->diff($existingLevelMatricules);

        if ($missingMatricules->isEmpty()) {
            Log::info("[SYNC] Tous les distributeurs sont déjà présents dans level_current_tests pour la période {$period}.");
            return ['message' => "Synchronisation terminée. Tous les distributeurs sont à jour pour {$period}.", 'created_count' => 0];
        }
        Log::info("[SYNC] {$missingMatricules->count()} distributeurs manquants à créer dans level_current_tests pour la période {$period}.");

        // --- 4. Récupérer les informations complètes pour les distributeurs manquants depuis la table 'distributeurs' ---
        // (id_distrib_parent, rang, etc.)
        $DistributeursToCreateData = Distributeur::whereIn('distributeur_id', $missingMatricules)
            ->select('distributeur_id', 'id_distrib_parent', 'rang') // Ajoutez d'autres champs si nécessaire
            ->get();

        // --- 5. Préparer les données pour l'insertion groupée ---
        $inserts = [];
        foreach ($DistributeursToCreateData as $distribInfo) {
            $inserts[] = [
                'distributeur_id'   => $distribInfo->distributeur_id,
                'period'            => $period,
                'rang'              => $distribInfo->rang ?? null, // Rang de base du distributeur
                'etoiles'           => 1,                         // Grade initial par défaut
                'cumul_individuel'  => 0,                         // Cumul initial
                'new_cumul'         => 0,                         // Achats de la période (initialement 0)
                'cumul_total'       => 0,                         // Cumul total initial
                'cumul_collectif'   => 0,                         // Cumul collectif initial
                'id_distrib_parent' => $distribInfo->id_distrib_parent ?? null,
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
            ];
        }

        // --- 6. Insérer les enregistrements manquants ---
        $createdCount = 0;
        if (!empty($inserts)) {
            try {
                DB::beginTransaction();
                // Insérer par lots pour gérer un grand nombre de créations
                foreach (array_chunk($inserts, 500) as $chunk) {
                    Level_current_test::insert($chunk); // Utilise l'insert de Query Builder, ne remplit pas les timestamps Eloquent
                                                     // Sauf si on les a mis manuellement comme ci-dessus.
                    $createdCount += count($chunk);
                }
                DB::commit();
                Log::info("[SYNC] {$createdCount} nouveaux enregistrements créés dans level_current_tests pour la période {$period}.");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("[SYNC] Erreur lors de l'insertion des distributeurs manquants pour {$period}: " . $e->getMessage(), ['exception' => $e]);
                return [
                    'message' => "Erreur lors de la création des enregistrements manquants pour {$period}.",
                    'created_count' => 0,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'message' => "Synchronisation terminée pour {$period}. {$createdCount} nouveaux enregistrements créés.",
            'created_count' => $createdCount
        ];
    }
}
