<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Query\Builder; // Pour type hint

class DataCorrectionService
{
    /**
     * Identifie les périodes dupliquées pour chaque distributeur dans 'level_current_tests'
     * et supprime les doublons en priorisant la conservation des enregistrements avec new_cumul != 0.
     * Si tous les doublons ont new_cumul = 0, un seul est conservé (celui avec le plus petit ID).
     *
     * @return array Un résumé des opérations ['enregistrements_supprimes' => int, 'groupes_corriges' => int]
     */
    public function supprimerDoublonsPeriodZeroPv(): array // Nom de méthode légèrement ajusté
    {
        $tableName = 'level_current_tests';
        $primaryKey = 'id'; // !! VÉRIFIEZ CE NOM DE CLÉ PRIMAIRE !!
        $distributorIdField = 'distributeur_id';
        $periodField = 'period';
        $cumulativeField = 'new_cumul'; // <--- MODIFICATION ICI

        Log::info("Début de la correction AFFINÉE des doublons de période (basée sur {$cumulativeField}) dans la table {$tableName}.");

        $deletedCount = 0;
        $groupsCorrected = 0; // Groupes où une suppression a eu lieu

        try {
            // --- Étape 1: Identifier les groupes (distributeur_id, period) qui ont des doublons ---
            $duplicateGroups = DB::table($tableName)
                ->select($distributorIdField, $periodField)
                ->groupBy($distributorIdField, $periodField)
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $totalDuplicateGroups = $duplicateGroups->count();
            Log::info("Nombre de groupes (distributeur_id, period) avec doublons trouvés : {$totalDuplicateGroups}");

            if ($totalDuplicateGroups === 0) {
                Log::info("Aucun groupe avec doublons trouvé. Aucune action nécessaire.");
                return ['enregistrements_supprimes' => 0, 'groupes_corriges' => 0, 'message' => 'Aucun doublon trouvé.'];
            }

            // --- Étape 2: Traiter chaque groupe de doublons ---
            DB::beginTransaction();

            foreach ($duplicateGroups as $group) {
                $distribId = $group->$distributorIdField;
                $periodValue = $group->$periodField;

                Log::debug("Traitement du groupe: {$distributorIdField}={$distribId}, {$periodField}={$periodValue}");

                // Récupérer TOUS les enregistrements pour ce groupe (ID et new_cumul)
                $groupRecords = DB::table($tableName)
                    ->where($distributorIdField, $distribId)
                    ->where($periodField, $periodValue)
                    ->select($primaryKey, $cumulativeField) // <--- MODIFICATION ICI (utilise $cumulativeField)
                    ->orderBy($primaryKey, 'asc') // Important pour choisir lequel garder si tous sont à zéro
                    ->get();

                // Séparer les enregistrements en fonction de new_cumul
                $zeroCumulRecords = $groupRecords->where($cumulativeField, '=', 0); // <--- MODIFICATION ICI
                $nonZeroCumulRecords = $groupRecords->where($cumulativeField, '<>', 0); // <--- MODIFICATION ICI

                $idsToDelete = collect(); // Initialiser la collection des IDs à supprimer

                // --- Logique de décision ---
                if ($nonZeroCumulRecords->isNotEmpty()) {
                    // Cas 1: Il existe au moins un enregistrement avec new_cumul != 0
                    $idsToDelete = $zeroCumulRecords->pluck($primaryKey);
                    if ($idsToDelete->isNotEmpty()) {
                         Log::debug("-> Groupe {$distribId}/{$periodValue}: Présence de non-zéro {$cumulativeField}. Suppression des zéro {$cumulativeField}. IDs: " . $idsToDelete->implode(', '));
                    } else {
                         Log::debug("-> Groupe {$distribId}/{$periodValue}: Présence de non-zéro {$cumulativeField}, mais aucun enregistrement zéro {$cumulativeField} à supprimer.");
                         if ($nonZeroCumulRecords->count() > 1) {
                             Log::warning("-> Groupe {$distribId}/{$periodValue}: Anomalie détectée - Plusieurs enregistrements avec {$cumulativeField} non nul ! Aucune suppression automatique effectuée.");
                             $idsToDelete = collect(); // Annuler toute suppression
                         }
                    }

                } else {
                    // Cas 2: TOUS les enregistrements du groupe ont new_cumul = 0
                    if ($zeroCumulRecords->count() > 1) { // S'il y a bien plus d'un enregistrement à zéro
                        $recordToKeep = $zeroCumulRecords->first();
                        $idsToDelete = $zeroCumulRecords->where($primaryKey, '!=', $recordToKeep->$primaryKey)->pluck($primaryKey);
                        Log::debug("-> Groupe {$distribId}/{$periodValue}: Tous les doublons ont zéro {$cumulativeField}. Conservation de ID={$recordToKeep->$primaryKey}. Suppression des autres. IDs: " . $idsToDelete->implode(', '));
                    } else {
                         Log::debug("-> Groupe {$distribId}/{$periodValue}: Tous les doublons ont zéro {$cumulativeField}, mais un seul trouvé ? Aucune suppression.");
                    }
                }

                // --- Étape 3: Exécuter la suppression si nécessaire ---
                if ($idsToDelete->isNotEmpty()) {
                    $numDeleted = DB::table($tableName)
                                  ->whereIn($primaryKey, $idsToDelete->toArray())
                                  ->delete();
                    $deletedCount += $numDeleted;
                    $groupsCorrected++;
                    Log::info("-> Groupe {$distribId}/{$periodValue}: {$numDeleted} enregistrement(s) supprimé(s).");
                }
            }

            DB::commit();

            Log::info("Correction AFFINÉE (basée sur {$cumulativeField}) terminée. Total enregistrements supprimés : {$deletedCount}. Nombre de groupes où une correction a eu lieu : {$groupsCorrected} (sur {$totalDuplicateGroups} groupes dupliqués trouvés).");
            return [
                'enregistrements_supprimes' => $deletedCount,
                'groupes_corriges' => $groupsCorrected,
                'message' => "Correction affinée (basée sur {$cumulativeField}) terminée. {$deletedCount} enregistrement(s) dupliqué(s) supprimé(s)."
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la suppression affinée des doublons de période (basée sur {$cumulativeField}) : " . $e->getMessage(), ['exception' => $e]);
            return [
                'enregistrements_supprimes' => $deletedCount,
                'groupes_corriges' => $groupsCorrected,
                'error' => "Une erreur est survenue durant la correction affinée (basée sur {$cumulativeField}): " . $e->getMessage()
             ];
        }
    }
}
