<?php

namespace App\Services;

use App\Models\LevelCurrent;
use App\Models\LevelCurrentHistory;
use App\Models\Distributeur; // Optionnel, pour récupérer le nom
use Illuminate\Support\Facades\DB; // Pour des requêtes plus complexes si besoin
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ProgressionService
{
    /**
     * Compare le grade (niveau d'étoiles) d'un distributeur entre deux périodes.
     *
     * @param int $distributorId L'ID primaire du distributeur.
     * @param string $startPeriod Période de début au format 'YYYY-MM'.
     * @param string $endPeriod   Période de fin au format 'YYYY-MM'.
     * @return array              Un tableau associatif contenant le résumé de la progression.
     */
    public function getDistributorProgression(int $distributorId, string $startPeriod, string $endPeriod): array
    {
        Log::debug("Calcul de la progression pour Distributeur ID: {$distributorId} entre {$startPeriod} et {$endPeriod}.");

        // 1. Récupérer les niveaux pour les deux périodes
        // On cherche d'abord dans la table "live" (level_currents) puis dans l'historique.
        $levelStart = $this->findLevelForPeriod($distributorId, $startPeriod);
        $levelEnd = $this->findLevelForPeriod($distributorId, $endPeriod);

        // 2. Initialiser le tableau de résultat
        $result = [
            'distributor_id' => $distributorId,
            'period_start'   => $startPeriod,
            'period_end'     => $endPeriod,
            'level_start'    => $levelStart,
            'level_end'      => $levelEnd,
            'progression'    => 0, // Par défaut
            'status'         => 'Données Incomplètes', // Par défaut
            'message'        => "Les données pour au moins une des périodes n'ont pas été trouvées." // Par défaut
        ];

        // 3. Analyser et calculer la progression si les deux niveaux ont été trouvés
        if ($levelStart !== null && $levelEnd !== null) {
            $progression = $levelEnd - $levelStart;
            $result['progression'] = $progression;

            if ($progression > 0) {
                $result['status'] = 'Promotion';
                $result['message'] = "Le distributeur a été promu du niveau {$levelStart} au niveau {$levelEnd}.";
            } elseif ($progression < 0) {
                $result['status'] = 'Régression';
                $result['message'] = "Le distributeur a régressé du niveau {$levelStart} au niveau {$levelEnd}.";
            } else { // progression == 0
                $result['status'] = 'Maintien';
                $result['message'] = "Le distributeur a maintenu son niveau {$levelStart}.";
            }
        } elseif ($levelStart === null && $levelEnd !== null) {
             // Cas où le distributeur était nouveau à la période de fin
             $result['progression'] = $levelEnd; // Progression est son niveau de départ
             $result['status'] = 'Nouveau Distributeur';
             $result['message'] = "Nouveau distributeur qui a atteint le niveau {$levelEnd} à la période de fin.";
        }
         // Si $levelEnd est null, le message par défaut "Données Incomplètes" est conservé.

        Log::debug("Résultat de la progression pour Distributeur ID {$distributorId}: " . json_encode($result));
        return $result;
    }

    /**
     * Fonction privée pour trouver le niveau d'étoiles d'un distributeur pour une période donnée.
     * Cherche d'abord dans level_currents puis dans level_current_histories.
     *
     * @param int $distributorId L'ID primaire du distributeur.
     * @param string $period     La période recherchée.
     * @return int|null          Le niveau d'étoiles, ou null si non trouvé.
     */
    private function findLevelForPeriod(int $distributorId, string $period): ?int
    {
        // On suppose que la table 'level_currents' contient les données les plus récentes,
        // donc on la vérifie en premier si elle peut contenir la période recherchée.
        // Si 'level_currents' ne contient QUE la période actuelle, on pourrait optimiser.

        // Chercher dans la table "live"
        $level = LevelCurrent::where('distributeur_id', $distributorId)
                             ->where('period', $period)
                             ->value('etoiles'); // Récupère uniquement la valeur de la colonne 'etoiles'

        if ($level !== null) {
            Log::debug("Niveau trouvé dans level_currents pour Distributeur ID {$distributorId} / Période {$period}: {$level}");
            return (int) $level;
        }

        // Si non trouvé, chercher dans l'historique
        $level = LevelCurrentHistory::where('distributeur_id', $distributorId)
                                    ->where('period', $period)
                                    ->value('etoiles');

        if ($level !== null) {
            Log::debug("Niveau trouvé dans level_current_histories pour Distributeur ID {$distributorId} / Période {$period}: {$level}");
            return (int) $level;
        }

        Log::warning("Aucun niveau trouvé pour Distributeur ID {$distributorId} pour la période {$period}.");
        return null; // Retourner null si rien n'est trouvé dans les deux tables
    }

    /**
     * Obtient la progression pour plusieurs distributeurs en une seule fois (plus optimisé).
     *
     * @param array $distributorIds Tableau d'IDs primaires de distributeurs.
     * @param string $startPeriod
     * @param string $endPeriod
     * @return array
     */
    public function getBulkDistributorProgression(array $distributorIds, string $startPeriod, string $endPeriod): array
    {
        // Cette fonction plus avancée récupère les données pour tous les distributeurs
        // en une seule fois pour éviter les requêtes N+1 si on appelle la fonction
        // getDistributorProgression dans une boucle.

        Log::info("Début calcul de progression en masse pour " . count($distributorIds) . " distributeurs entre {$startPeriod} et {$endPeriod}.");

        // Récupérer tous les niveaux de départ et de fin en 2 requêtes
        $levelsStart = $this->findLevelsForPeriodBulk($distributorIds, $startPeriod);
        $levelsEnd = $this->findLevelsForPeriodBulk($distributorIds, $endPeriod);

        $results = [];
        foreach ($distributorIds as $distributorId) {
            // Utiliser les données pré-chargées au lieu d'appeler getDistributorProgression individuellement
            $levelStart = $levelsStart->get($distributorId); // .get() retourne null si la clé n'existe pas
            $levelEnd = $levelsEnd->get($distributorId);

             // Logique de calcul et de message (identique à la fonction simple)
            $result = [
                'distributor_id' => $distributorId,
                'period_start'   => $startPeriod,
                'period_end'     => $endPeriod,
                'level_start'    => $levelStart,
                'level_end'      => $levelEnd,
                'progression'    => 0,
                'status'         => 'Données Incomplètes',
                'message'        => "Les données pour au moins une des périodes n'ont pas été trouvées."
            ];

            if ($levelStart !== null && $levelEnd !== null) {
                $progression = $levelEnd - $levelStart;
                $result['progression'] = $progression;
                if ($progression > 0) {
                    $result['status'] = 'Promotion';
                    $result['message'] = "Promu(e) du niveau {$levelStart} au niveau {$levelEnd}.";
                } elseif ($progression < 0) {
                    $result['status'] = 'Régression';
                    $result['message'] = "Régressé(e) du niveau {$levelStart} au niveau {$levelEnd}.";
                } else {
                    $result['status'] = 'Maintien';
                    $result['message'] = "Maintien au niveau {$levelStart}.";
                }
            } elseif ($levelStart === null && $levelEnd !== null) {
                 $result['progression'] = $levelEnd;
                 $result['status'] = 'Nouveau';
                 $result['message'] = "A atteint le niveau {$levelEnd} à la période de fin.";
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Helper pour trouver les niveaux de plusieurs distributeurs pour une période donnée.
     *
     * @param array $distributorIds
     * @param string $period
     * @return \Illuminate\Support\Collection  Une collection mappée [distributeur_id => etoiles]
     */
    private function findLevelsForPeriodBulk(array $distributorIds, string $period): Collection
    {
        // Chercher dans la table "live"
        $levelsCurrent = LevelCurrent::where('period', $period)
                                     ->whereIn('distributeur_id', $distributorIds)
                                     ->pluck('etoiles', 'distributeur_id');

        // Chercher dans l'historique pour les IDs qui n'ont pas été trouvés
        $remainingIds = array_diff($distributorIds, $levelsCurrent->keys()->all());

        if (!empty($remainingIds)) {
            $levelsHistory = LevelCurrentHistory::where('period', $period)
                                                ->whereIn('distributeur_id', $remainingIds)
                                                ->pluck('etoiles', 'distributeur_id');
            // Fusionner les deux collections
            return $levelsCurrent->union($levelsHistory);
        }

        return $levelsCurrent;
    }
}
