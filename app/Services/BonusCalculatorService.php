<?php

namespace App\Services; // Ou App\Http\Controllers, etc.

use App\Models\LevelCurrent; // Utiliser le bon modèle
use Illuminate\Support\Arr; // Peut ne plus être nécessaire
use Illuminate\Support\Facades\Log;

class BonusCalculatorService
{
    /**
     * Structure des taux pour le bonus indirect.
     * Clé externe: Niveau d'étoiles du parent.
     * Clé interne: Différence de niveau (Parent - Enfant).
     * Valeur: Taux applicable.
     *
     * @var array<int, array<int, float>>
     */
    private const INDIRECT_RATES = [
        1 => [], // Pas de bonus si parent est niveau 1
        2 => [1 => 0.06],
        3 => [1 => 0.16, 2 => 0.22],
        4 => [1 => 0.04, 2 => 0.20, 3 => 0.26],
        5 => [1 => 0.04, 2 => 0.08, 3 => 0.24, 4 => 0.30],
        6 => [1 => 0.04, 2 => 0.08, 3 => 0.12, 4 => 0.28, 5 => 0.34],
        7 => [1 => 0.06, 2 => 0.10, 3 => 0.14, 4 => 0.18, 5 => 0.34, 6 => 0.40], // Correction : 0.1 au lieu de 0.10
        8 => [1 => 0.03, 2 => 0.09, 3 => 0.13, 4 => 0.17, 5 => 0.21, 6 => 0.37, 7 => 0.43],
        9 => [1 => 0.02, 2 => 0.05, 3 => 0.11, 4 => 0.15, 5 => 0.19, 6 => 0.23, 7 => 0.39, 8 => 0.45],
        // Ajouter les niveaux 10, 11 etc. si nécessaire, sinon le taux sera 0 par défaut
        // 10 => [1 => X, 2 => Y, ...],
        // 11 => [...]
    ];

    /**
     * Calcule le bonus indirect total pour un distributeur pour une période donnée.
     *
     * @param int    $distributeurId L'ID primaire du distributeur parent.
     * @param int    $parentEtoiles  Le niveau d'étoiles du distributeur parent.
     * @param string $period         La période au format 'YYYY-MM'.
     *
     * @return float Le montant total du bonus indirect.
     */
    public function calculateIndirectBonus(int $distributeurId, int $parentEtoiles, string $period): float
    {
        // 1. Récupérer les données pertinentes des enfants directs pour la période
        $childrenData = LevelCurrent::where('id_distrib_parent', $distributeurId)
                                     ->where('period', $period)
                                     ->select('etoiles', 'cumul_total') // Sélectionner seulement le nécessaire
                                     ->get();

        // Si pas d'enfants, le bonus est 0
        if ($childrenData->isEmpty()) {
            return 0.0;
        }

        // 2. Calculer le bonus pour chaque enfant et sommer
        $totalIndirectBonus = 0.0;

        foreach ($childrenData as $child) {
            // Assurer que les données sont valides (au cas où)
            if (!isset($child->etoiles) || !isset($child->cumul_total)) {
                 Log::warning("Données enfant incomplètes pour calcul bonus indirect. ParentID: {$distributeurId}, Period: {$period}, Enfant data: " . json_encode($child));
                 continue; // Ignorer cet enfant
            }

            $diff = $parentEtoiles - $child->etoiles;

            // Obtenir le taux en utilisant la structure de données optimisée
            $rate = $this->getIndirectRate($parentEtoiles, $diff);

            // Ajouter le bonus généré par cet enfant
            $totalIndirectBonus += $child->cumul_total * $rate;
        }

        return $totalIndirectBonus;
    }

    /**
     * Fonction helper optimisée pour obtenir le taux indirect.
     * Utilise la structure de données self::INDIRECT_RATES.
     *
     * @param int $parentEtoiles Niveau du parent.
     * @param int $levelDifference Différence (Parent - Enfant).
     *
     * @return float Le taux applicable (0.0 si non défini ou diff <= 0).
     */
    private function getIndirectRate(int $parentEtoiles, int $levelDifference): float
    {
        // Si la différence est négative ou nulle, ou si le parent n'est pas dans la table des taux, le taux est 0
        if ($levelDifference <= 0 || !isset(self::INDIRECT_RATES[$parentEtoiles])) {
            return 0.0;
        }

        // Chercher le taux pour cette différence dans le tableau du niveau parent
        // Si la différence exacte n'est pas trouvée (ex: diff=9 pour parent=9), retourne 0.0
        return self::INDIRECT_RATES[$parentEtoiles][$levelDifference] ?? 0.0;
    }
}

// --- Exemple d'Utilisation ---
/*
$calculator = new BonusCalculatorService();
$distribId = 123;
$periode = '2025-03';

// Supposons qu'on récupère le niveau du distributeur 123 pour la période
$distribLevelData = LevelCurrent::where('distributeur_id', $distribId)->where('period', $periode)->first();

if ($distribLevelData) {
    $parentLevel = $distribLevelData->etoiles;
    $indirectBonus = $calculator->calculateIndirectBonus($distribId, $parentLevel, $periode);
    echo "Bonus Indirect pour Distributeur {$distribId} en {$periode}: " . number_format($indirectBonus, 2);
} else {
    echo "Niveau du distributeur {$distribId} non trouvé pour la période {$periode}.";
}
*/
