<?php

namespace App\Http\Controllers; // Ou App\Services, App\Console\Commands, etc.

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\LevelCurrentTest; // Ajustez si votre namespace/nom de modèle est différent
use Illuminate\Database\QueryException;
use Illuminate\Log\Logger; // Pour enregistrer les erreurs
use Illuminate\Support\Facades\Log;

class LevelCurrentCopyToHistory extends Controller // Ou une autre classe de base
{

    /**
     * Copie les entrées de level_current_tests d'une période source vers une période cible,
     * en réinitialisant certains cumuls.
     *
     * @param string $sourcePeriod La période source (ex: '2025-02')
     * @param string $targetPeriod La période cible (ex: '2025-03')
     * @return bool Retourne true en cas de succès, false en cas d'échec ou si aucune donnée source n'est trouvée.
     */
    public function copyLevelTestData(string $sourcePeriod, string $targetPeriod): bool
    {
        Log::info("Tentative de copie des données de la période '$sourcePeriod' vers '$targetPeriod'.");

        $tableName = (new LevelCurrentTest())->getTable(); // Obtient le nom de la table depuis le modèle (bonne pratique)
        // Alternative si vous n'avez pas de modèle : $tableName = 'level_current_tests';

        try {
            // 1. Récupérer toutes les données de la période source
            // Utilisation de ->get()->toArray() pour obtenir un array simple, plus facile à manipuler pour l'insertion.
            $sourceData = DB::table($tableName)
                            ->where('period', $sourcePeriod)
                            ->get();

            // Vérifier s'il y a des données à copier
            if ($sourceData->isEmpty()) {
                Log::warning("Aucune donnée trouvée pour la période source '$sourcePeriod'. Aucune copie effectuée.");
                return false; // Ou vous pourriez vouloir retourner true si ce n'est pas une erreur
            }

            Log::info("Nombre d'enregistrements trouvés pour '$sourcePeriod': " . $sourceData->count());

            // Préparer les données pour l'insertion
            $dataToInsert = [];
            $now = Carbon::now(); // Obtenir l'heure actuelle pour les timestamps

            foreach ($sourceData as $row) {
                // Convertir l'objet stdClass en tableau associatif
                $rowData = (array) $row;

                // Retirer l'ID primaire pour permettre l'auto-incrémentation
                unset($rowData['id']);

                // Modifier les champs requis
                $rowData['period'] = $targetPeriod;
                $rowData['new_cumul'] = 0.00;
                $rowData['cumul_total'] = 0.00;

                // Mettre à jour les timestamps (important pour l'insertion)
                $rowData['created_at'] = $now;
                $rowData['updated_at'] = $now;

                // Ajouter au tableau pour l'insertion en masse
                $dataToInsert[] = $rowData;
            }

            // 3. Insérer les nouvelles données en masse
            // L'insertion en masse est beaucoup plus rapide que des insertions individuelles dans une boucle.
            $inserted = DB::table($tableName)->insert($dataToInsert);

            if ($inserted) {
                Log::info("Succès : " . count($dataToInsert) . " enregistrements copiés de '$sourcePeriod' vers '$targetPeriod'.");
                return true;
            } else {
                // Ce cas est peu probable avec insert() si $dataToInsert n'est pas vide,
                // mais incluons-le par sécurité.
                Log::error("L'insertion en masse pour '$targetPeriod' semble avoir échoué sans lever d'exception.");
                return false;
            }

        } catch (QueryException $e) {
            // Gérer les erreurs potentielles de base de données (contraintes, etc.)
            Log::error("Erreur lors de la copie des données de '$sourcePeriod' vers '$targetPeriod': " . $e->getMessage());
            // Vous pourriez vouloir logger $e->getSql() et $e->getBindings() pour le débogage
            return false;
        } catch (\Exception $e) {
            // Gérer toute autre exception inattendue
            Log::error("Erreur inattendue lors de la copie des données de '$sourcePeriod' vers '$targetPeriod': " . $e->getMessage());
            return false;
        }
    }

    // --- Exemple d'utilisation dans une méthode de contrôleur ---
    public function processMonthlyData(Request $request)
    {
        $sourcePeriod = '2025-02'; // Peut venir de la requête, d'une config, etc.
        $targetPeriod = '2025-03'; // Calculé ou défini

        // Optionnel : Vérifier si des données existent déjà pour la période cible
        if (DB::table('level_current_tests')->where('period', $targetPeriod)->exists()) {
             // Gérer le cas : logguer, retourner une erreur, ne rien faire...
             Log::warning("Des données existent déjà pour la période cible '$targetPeriod'. La copie est annulée.");
             // return redirect()->back()->with('error', "Données déjà existantes pour $targetPeriod");
             return; // Ou une autre logique
        }


        $success = $this->copyLevelTestData($sourcePeriod, $targetPeriod);

        if ($success) {
            // Action en cas de succès (redirection, message flash, etc.)
            return redirect()->back()->with('success', "Données copiées avec succès pour la période $targetPeriod.");
        } else {
            // Action en cas d'échec
            return redirect()->back()->with('error', "Erreur lors de la copie des données pour la période $targetPeriod.");
        }
    }
}
