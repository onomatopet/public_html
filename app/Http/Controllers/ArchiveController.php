<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\LevelCurrentTest;
use App\Models\LevelCurrentTestHistory;
use Carbon\Carbon;

class ArchiveController extends Controller
{
    /**
     * Archive les données de level_current_tests vers level_current_test_history
     * en se basant sur la dernière période archivée.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function archiveLevelTests(Request $request)
    {
        Log::info('Début du processus d\'archivage de level_current_tests.');

        try {
            // 1. Trouver la dernière période archivée dans l'historique
            $lastArchivedPeriod = LevelCurrentTestHistory::query()
                ->orderBy('period', 'desc') // Trier par période décroissante
                ->value('period'); // Obtenir uniquement la valeur de la colonne 'period'

            if ($lastArchivedPeriod) {
                Log::info("Dernière période trouvée dans l'historique: {$lastArchivedPeriod}");
            } else {
                Log::info("Aucune période trouvée dans l'historique. Archivage de toutes les périodes disponibles.");
            }

            // 2. Identifier les périodes distinctes dans level_current_tests
            //    qui sont plus récentes que la dernière période archivée.
            $periodsToArchiveQuery = LevelCurrentTest::query()
                ->select('period')
                ->distinct()
                ->orderBy('period', 'asc'); // Traiter les périodes dans l'ordre chronologique

            if ($lastArchivedPeriod) {
                // Si une période archivée existe, prendre uniquement celles qui sont strictement plus récentes
                $periodsToArchiveQuery->where('period', '>', $lastArchivedPeriod);
            }

            $periodsToArchive = $periodsToArchiveQuery->pluck('period');

            // 3. Vérifier s'il y a de nouvelles périodes à archiver
            if ($periodsToArchive->isEmpty()) {
                Log::info("Aucune nouvelle période à archiver trouvée dans level_current_tests.");
                return redirect()->back()->with('info', 'Aucune nouvelle période à archiver.');
            }

            Log::info("Périodes à archiver trouvées: " . $periodsToArchive->implode(', '));

            $archivedCount = 0;
            $failedPeriods = [];

            // 4. Boucler sur chaque période à archiver et copier les données
            foreach ($periodsToArchive as $period) {
                Log::info("Traitement de la période: {$period}");

                // Utiliser une transaction pour chaque période pour l'atomicité
                DB::beginTransaction();
                try {
                    // Sélectionner les données de la période courante
                    $sourceData = DB::table('level_current_tests')
                                     ->where('period', $period)
                                     ->get();

                    if ($sourceData->isEmpty()) {
                        Log::warning("Aucune donnée trouvée pour la période {$period} dans level_current_tests. Skipping.");
                        DB::rollBack(); // Annuler la transaction (même si vide)
                        continue; // Passer à la période suivante
                    }

                    // Préparer les données pour l'insertion dans l'historique
                    $dataToInsert = [];
                    $now = Carbon::now(); // Timestamp de l'archivage

                    foreach ($sourceData as $row) {
                        // Convertir en tableau associatif
                        $rowData = (array) $row;

                        // Retirer l'ID de la table source car la table history a son propre ID auto-incrémenté
                        unset($rowData['id']);
                        // Retirer aussi les timestamps originaux pour utiliser ceux de l'archivage
                        unset($rowData['created_at']);
                        unset($rowData['updated_at']);

                        // Ajouter les timestamps d'archivage
                        $rowData['created_at'] = $now;
                        $rowData['updated_at'] = $now;

                        $dataToInsert[] = $rowData;
                    }

                    // Insérer en masse dans la table d'historique
                    $inserted = DB::table('level_current_test_history')->insert($dataToInsert);

                    if ($inserted) {
                         Log::info(count($dataToInsert) . " enregistrements archivés pour la période {$period}.");
                         $archivedCount += count($dataToInsert);
                         DB::commit(); // Valider la transaction pour cette période

                         // OPTIONNEL: Supprimer les données de la table 'level_current_tests' après archivage réussi
                         // DB::table('level_current_tests')->where('period', $period)->delete();
                         // Log::info("Données pour la période {$period} supprimées de level_current_tests.");

                    } else {
                        Log::error("Échec de l'insertion pour la période {$period} sans exception.");
                        $failedPeriods[] = $period;
                        DB::rollBack(); // Annuler la transaction
                    }

                } catch (\Exception $e) {
                    DB::rollBack(); // Annuler la transaction en cas d'erreur
                    Log::error("Erreur lors de l'archivage de la période {$period}: " . $e->getMessage());
                    $failedPeriods[] = $period;
                    // Optionnel: Stopper tout le processus si une période échoue ?
                    // return redirect()->back()->with('error', "Erreur lors de l'archivage de la période {$period}. Processus stoppé.");
                }
            } // Fin de la boucle foreach period

            // 5. Préparer le message de retour
            if ($archivedCount > 0 && empty($failedPeriods)) {
                return redirect()->back()->with('success', "Archivage réussi. {$archivedCount} enregistrements ajoutés à l'historique pour les périodes : " . $periodsToArchive->implode(', ') . ".");
            } elseif ($archivedCount > 0 && !empty($failedPeriods)) {
                return redirect()->back()->with('warning', "Archivage partiel. {$archivedCount} enregistrements ajoutés. Échec pour les périodes : " . implode(', ', $failedPeriods) . ".");
            } else {
                return redirect()->back()->with('error', 'Échec de l\'archivage. Aucune donnée n\'a pu être archivée. Périodes échouées : ' . implode(', ', $failedPeriods));
            }

        } catch (\Exception $e) {
            Log::error('Erreur générale durant le processus d\'archivage: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Une erreur inattendue est survenue lors de l\'archivage.');
        }
    }
}
