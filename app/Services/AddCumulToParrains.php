<?php

// Supposons que cette fonction est dans une classe où $this est disponible
// et qu'elle a accès au modèle Level_current_test.
// Si c'est un Service, le modèle devrait être injecté ou utilisé statiquement.

namespace App\Services;
use App\Models\Level_current_test; // Assurez-vous que le chemin et le nom du modèle sont corrects
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddCumulToParrains // Remplacez par le nom de votre classe (Service, Controller, etc.)
{
    /**
     * Ajoute un cumul aux cumuls totaux et collectifs de tous les parrains
     * d'un distributeur pour une période donnée, de manière optimisée.
     * Retourne un tableau de débogage traçant les mises à jour.
     *
     * @param string|int|null $id_distrib_parent_matricule Le matricule du parent direct à partir duquel commencer.
     * @param float $cumul La valeur du cumul à ajouter.
     * @param string $period La période concernée.
     * @param array $debugTrace Le tableau de trace (passé par référence interne pour la récursion).
     * @return array La trace des opérations.
     */
    public function addCumulToParainsOptimized($id_distrib_parent_matricule, float $cumul, string $period, array &$debugTrace = []): array
    {
        // Condition d'arrêt : si pas de parent ou cumul à zéro (pas d'impact)
        if (empty($id_distrib_parent_matricule) || $cumul == 0) {
            return $debugTrace;
        }

        Log::debug("addCumulToParainsOptimized: Parent matricule={$id_distrib_parent_matricule}, Cumul={$cumul}, Period={$period}");

        // Récupérer l'enregistrement du parent pour la période donnée
        $parainRecord = Level_current_test::where('distributeur_id', $id_distrib_parent_matricule)
                                       ->where('period', $period)
                                       ->first();

        if ($parainRecord) {
            // Enregistrer les valeurs AVANT l'incrémentation pour le debug
            $oldCumulTotal = $parainRecord->cumul_total;
            $oldCumulCollectif = $parainRecord->cumul_collectif;

            // Utiliser increment() pour des mises à jour atomiques et efficaces
            // Cela exécute une requête UPDATE comme:
            // UPDATE level_current_tests SET cumul_total = cumul_total + ?, cumul_collectif = cumul_collectif + ? WHERE id = ?
            // C'est beaucoup plus efficace que de lire, modifier en PHP, puis sauvegarder.
            try {
                // Si la mise à jour doit se faire en une seule transaction pour tous les parents,
                // la transaction DB::beginTransaction() doit être en dehors de l'appel initial à cette fonction.
                // Ici, chaque parent est mis à jour individuellement.
                Level_current_test::where($parainRecord->getKeyName(), $parainRecord->getKey()) // Cibler par clé primaire
                    ->increment('cumul_total', $cumul, [
                        // On peut aussi mettre à jour d'autres champs en même temps avec increment si la valeur est fixe ou calculée en SQL
                        // Pour 'cumul_collectif', on le fait séparément ou on s'assure qu'il est inclus
                        // dans la même logique atomique.
                        // Le troisième argument de increment() permet de mettre à jour d'autres colonnes.
                        // Pour une addition simple, on peut faire deux increments ou une requête DB::update.
                        // Pour garder simple et atomique par champ:
                    ]);

                Level_current_test::where($parainRecord->getKeyName(), $parainRecord->getKey())
                    ->increment('cumul_collectif', $cumul);


                // Mettre à jour l'objet en mémoire si on le réutilise pour le debug (après les increments)
                $parainRecord->refresh(); // Recharge le modèle depuis la BDD

                Log::info("Parent matricule={$parainRecord->distributeur_id} (ID: {$parainRecord->getKey()}) période {$period} mis à jour. Ajout de {$cumul}.");

                // Préparer les données de débogage avec les nouvelles valeurs
                $debugData = [
                    'period' => $period,
                    'distributeur_id' => $parainRecord->distributeur_id, // Matricule du parent
                    'action' => "Ajout de {$cumul}",
                    'cumul_total_avant' => $oldCumulTotal,
                    'cumul_total_apres' => $parainRecord->cumul_total,
                    'cumul_collectif_avant' => $oldCumulCollectif,
                    'cumul_collectif_apres' => $parainRecord->cumul_collectif,
                    'children' => [] // Initialisé vide, sera rempli par l'appel récursif
                ];

                // Appel récursif pour le parent du parent actuel
                // On ne modifie pas la référence $debugTrace directement ici, mais on passe le résultat
                // de l'appel récursif pour l'assigner à 'children'.
                $debugData['children'] = $this->addCumulToParainsOptimized(
                    $parainRecord->id_distrib_parent, // Matricule du grand-parent
                    $cumul,
                    $period
                    // La trace est construite en remontant, le tableau est retourné et assigné
                );

                // Ajouter les données de debug pour CE parent au début du tableau de trace
                // pour avoir un ordre descendant dans le debug final (de l'appelant initial vers les ancêtres)
                // ou à la fin si on veut un ordre ascendant. Pour un chemin, l'imbrication suffit.
                // Si on veut une liste plate, la gestion de $debugTrace change.
                // Pour l'imbrication comme l'original :
                $debugTrace[] = $debugData;


            } catch (\Exception $e) {
                Log::error("Erreur lors de la mise à jour du parent matricule={$id_distrib_parent_matricule} période {$period}: " . $e->getMessage());
                // Gérer l'erreur, peut-être ajouter une entrée d'erreur dans $debugTrace
                $debugTrace[] = [
                    'period' => $period,
                    'distributeur_id' => $id_distrib_parent_matricule,
                    'error' => "Erreur de mise à jour: " . $e->getMessage(),
                    'children' => []
                ];
            }
        } else {
            Log::warning("Parent matricule={$id_distrib_parent_matricule} non trouvé pour la période {$period}. Arrêt de la propagation du cumul.");
            $debugTrace[] = [
                'period' => $period,
                'distributeur_id' => $id_distrib_parent_matricule,
                'status' => 'Non trouvé, arrêt de la propagation.',
                'children' => []
            ];
        }

        return $debugTrace;
    }

    /**
     * Fonction "wrapper" pour initialiser et appeler la fonction récursive.
     * C'est cette fonction que vous appelleriez avec "return $this->fonctionQuiLanceLaMiseAJour(...);"
     */
    public function lancerMiseAJourCumulParrains(string $id_distrib_fils_matricule, float $cumulInitial, string $period): array
    {
        // D'abord, trouver le parent direct du fils qui a généré le cumul
        $fils = Level_current_test::where('distributeur_id', $id_distrib_fils_matricule)
                                 ->where('period', $period)
                                 ->select('id_distrib_parent') // On a besoin que de l'ID du parent
                                 ->first();

        if (!$fils || empty($fils->id_distrib_parent)) {
            Log::info("Le distributeur fils matricule={$id_distrib_fils_matricule} n'a pas de parent ou n'existe pas pour la période {$period}.");
            return ['message' => "Aucun parent à mettre à jour pour le distributeur {$id_distrib_fils_matricule} ou le distributeur n'existe pas pour la période {$period}.", 'trace' => []];
        }

        $trace = []; // Initialiser le tableau de trace vide
        $this->addCumulToParainsOptimized($fils->id_distrib_parent, $cumulInitial, $period, $trace);

        // La trace est maintenant remplie, on peut la retourner si besoin de l'analyser
        return ['message' => "Mise à jour des cumuls des parrains terminée.", 'trace' => $trace];
    }
}


// UTILISATION
// Supposons que $yourService est une instance de YourClass
// $idDuFilsQuiAGenereLeCumul = 'MATRICULE_FILLEUL';
// $montantDuCumul = 100.00;
// $periodeConcernee = '2025-03';

// $resultatDebug = $yourService->lancerMiseAJourCumulParrains(
//     $idDuFilsQuiAGenereLeCumul,
//     $montantDuCumul,
//     $periodeConcernee
// );

// Pour correspondre à votre "return $this->fonctionCorrigeProbleme();"
// vous auriez une méthode dans votre contrôleur ou service :
// public function maFonctionQuiAppelleLaCorrection() {
//     $service = new YourClass(); // Ou injecté
//     $resultat = $service->lancerMiseAJourCumulParrains('MATRICULE_FILLEUL_X', 50.0, '2025-03');
//     return $resultat; // Retourne ['message' => ..., 'trace' => [...]]
// }
