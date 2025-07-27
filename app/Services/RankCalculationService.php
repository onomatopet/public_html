<?php

// Dans un service, ex: app/Services/RankCalculationService.php
namespace App\Services;
use App\Models\Level_current_test;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB; // Pour la requête CTE
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RankCalculationService
{
    // ... (Constantes RANK_X de DistributorRankService) ...
    const RANK_1 = 1;
    const RANK_2 = 2;
    const RANK_3 = 3;
    const RANK_4 = 4;
    const RANK_5 = 5;
    const RANK_6 = 6;
    const RANK_7 = 7;
    const RANK_8 = 8;
    const RANK_9 = 9;
    const RANK_10 = 10;
    const RANK_11 = 11;

    // Instance de la classe Distributor pour accéder aux méthodes comme getCurrentDescendantIdsForPeriod
    // ou passer les méthodes en statique si elles ne dépendent pas de l'état de l'instance
    protected Level_current_test $levelModelInstance;

    public function __construct() {
        $this->levelModelInstance = new Level_current_test();
    }

    /**
     * Récupère les IDs de tous les descendants pour un distributeur et une période donnée.
     * Nécessite que Level_current_test ait id_distrib_parent et distributeur_id (matricule).
     */
    private function getCurrentDescendantIdsForPeriod(string $distributeurMatricule, string $period): Collection
    {
        $tableName = $this->levelModelInstance->getTable(); // 'level_current_tests'
        $primaryKeyField = 'distributeur_id'; // On se base sur le matricule pour la hiérarchie
        $parentIdKey = 'id_distrib_parent';

        $sql = <<<SQL
        WITH RECURSIVE descendant_cte ($primaryKeyField) AS (
            SELECT $primaryKeyField
            FROM $tableName
            WHERE $parentIdKey = ? AND period = ?

            UNION ALL

            SELECT t.$primaryKeyField
            FROM $tableName t
            INNER JOIN descendant_cte dc ON t.$parentIdKey = dc.$primaryKeyField
            WHERE t.period = ? -- Assurer que les descendants sont de la même période
        )
        SELECT $primaryKeyField FROM descendant_cte;
        SQL;

        $results = DB::select($sql, [$distributeurMatricule, $period, $period]);
        return collect($results)->pluck($primaryKeyField);
    }


    /**
     * Calcule et met à jour les grades pour TOUS les distributeurs d'une période donnée.
     *
     * @param string $period
     * @return array
     */
    public function calculateAndUpdateAllRanksForPeriod(string $period): array
    {
        Log::info("[RCS] Début calcul des grades pour période {$period}.");

        $distributorsForPeriod = Level_current_test::where('period', $period)
            // Charger les données nécessaires. Si getCurrentDescendantIdsForPeriod est efficace,
            // on peut charger moins ici et plus dans les helpers.
            ->select('distributeur_id', 'id_distrib_parent', 'etoiles', 'cumul_individuel', 'cumul_collectif')
            ->get();

        if ($distributorsForPeriod->isEmpty()) {
            Log::info("[RCS] Aucun distributeur trouvé pour période {$period}.");
            return ['message' => "Aucun distributeur pour calculer les grades pour {$period}.", 'updated_count' => 0];
        }

        // Pré-charger toutes les données des enfants pour la période pour optimiser les lookups
        // Crée une map: parent_matricule => Collection d'enfants (objets Level_current_test)
        $childrenMap = $distributorsForPeriod->whereNotNull('id_distrib_parent')->groupBy('id_distrib_parent');

        // Pré-charger tous les distributeurs par leur matricule pour accès rapide
        $distributorsMap = $distributorsForPeriod->keyBy('distributeur_id');

        $updatedCount = 0;
        $processedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($distributorsForPeriod as $distributor) {
                $processedCount++;
                $originalRank = $distributor->etoiles;

                // --- Appel de la logique de calcul de grade (ancien calculatePotentialRank) ---
                $potentialRank = $this->calculatePotentialRankLogic(
                    $distributor, // L'objet Level_current_test du distributeur actuel
                    $childrenMap, // Map de tous les enfants pour la période
                    $distributorsMap // Map de tous les distributeurs pour la période (pour les lookups de rangs des descendants)
                );
                // --- Fin Appel ---

                if ($potentialRank > $originalRank) {
                    // Mise à jour du grade dans la BDD
                    Level_current_test::where($distributor->getKeyName(), $distributor->getKey()) // Cibler par PK
                                    ->update(['etoiles' => $potentialRank, 'updated_at' => Carbon::now()]);
                    $updatedCount++;
                    Log::debug("[RCS] Grade mis à jour pour {$distributor->distributeur_id} période {$period}: {$originalRank} -> {$potentialRank}");
                }
            }
            DB::commit();
            Log::info("[RCS] Calcul des grades terminé pour {$period}. MàJ:{$updatedCount} sur {$processedCount} traités.");
            return ['message' => "Calcul des grades terminé pour {$period}. {$updatedCount} grades mis à jour.", 'updated_count' => $updatedCount];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[RCS] Erreur calcul des grades pour {$period}: " . $e->getMessage(), ['exception' => $e]);
            return ['message' => "Erreur calcul des grades pour {$period}.", 'updated_count' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Logique interne de calcul du grade potentiel.
     * Adaptée de l'ancien DistributorRankService::calculatePotentialRank
     *
     * @param Level_current_test $distributor L'enregistrement du distributeur à évaluer.
     * @param Collection $allChildrenMap Map de parent_id => Collection d'enfants pour la période.
     * @param Collection $allDistributorsMap Map de distrib_id => Enregistrement distributeur pour la période.
     * @return int Le nouveau grade potentiel.
     */
    private function calculatePotentialRankLogic(Level_current_test $distributor, Collection $allChildrenMap, Collection $allDistributorsMap): int
    {
        $potentialRank = $distributor->etoiles ?? self::RANK_1;
        $individualScore = (float) $distributor->cumul_individuel;
        $collectiveScore = (float) $distributor->cumul_collectif; // Score collectif déjà mis à jour

        // Récupérer les enfants directs du distributeur actuel depuis la map pré-chargée
        $directChildrenRecords = $allChildrenMap->get($distributor->distributeur_id) ?? collect();

        // Logique de rank 2 & 3
        if ($potentialRank < self::RANK_2 && $individualScore >= 100) $potentialRank = self::RANK_2;
        if ($potentialRank < self::RANK_3 && $individualScore >= 200) $potentialRank = self::RANK_3;

        // Logique pour Rank 4
        if ($potentialRank == self::RANK_3) {
            // Conditions spécifiques pour Rank 4
            $cond1_r4 = ($individualScore >= 1000 && $this->countLegsWithMinRankRecursive($directChildrenRecords, $allChildrenMap, $allDistributorsMap, self::RANK_3) >= 2 && $collectiveScore >= 2200);
            $cond2_r4 = ($individualScore >= 1000 && $this->countLegsWithMinRankRecursive($directChildrenRecords, $allChildrenMap, $allDistributorsMap, self::RANK_3) >= 3 && $collectiveScore >= 1000);
            if ($cond1_r4 || $cond2_r4) $potentialRank = self::RANK_4;
        }

        // Logique pour Rank 5
        if (in_array($potentialRank, [self::RANK_3, self::RANK_4])) {
            $legsWithRank4 = $this->countLegsWithMinRankRecursive($directChildrenRecords, $allChildrenMap, $allDistributorsMap, self::RANK_4);
            $exclusiveLegsWithRank3 = $this->countLegsWithExactRankExcludingHigherRecursive($directChildrenRecords, $allChildrenMap, $allDistributorsMap, self::RANK_3, self::RANK_4);

            $cond1_r5 = ($legsWithRank4 >= 2 && $collectiveScore >= 7800);
            $cond2_r5 = ($legsWithRank4 >= 3 && $collectiveScore >= 3800);
            $cond3_r5 = ($legsWithRank4 >= 2 && $exclusiveLegsWithRank3 >= 4 && $collectiveScore >= 3800);
            $cond4_r5 = ($legsWithRank4 >= 1 && $exclusiveLegsWithRank3 >= 6 && $collectiveScore >= 3800);
            if ($cond1_r5 || $cond2_r5 || $cond3_r5 || $cond4_r5) $potentialRank = self::RANK_5;
        }
        // ... Implémenter les conditions pour les grades 6 à 11 de manière similaire ...
        // en utilisant countLegsWithMinRankRecursive et countLegsWithExactRankExcludingHigherRecursive

        return $potentialRank;
    }

    // --- Méthodes HELPER pour l'analyse des pieds (adaptées pour travailler avec les maps pré-chargées) ---

    /**
     * Compte le nombre de pieds directs qui contiennent au moins un membre (direct ou indirect)
     * avec le grade minimum requis.
     *
     * @param Collection $directChildrenRecords Collection des enfants directs du distributeur actuel.
     * @param Collection $allChildrenMap Map globale: parent_id => enfants.
     * @param Collection $allDistributorsMap Map globale: distrib_id => data.
     * @param int $minRank
     * @return int
     */
    private function countLegsWithMinRankRecursive(Collection $directChildrenRecords, Collection $allChildrenMap, Collection $allDistributorsMap, int $minRank): int
    {
        $qualifiedLegs = 0;
        foreach ($directChildrenRecords as $childRecord) {
            if ($this->legContainsMinRankRecursive($childRecord, $allChildrenMap, $allDistributorsMap, $minRank)) {
                $qualifiedLegs++;
            }
        }
        return $qualifiedLegs;
    }

    /**
     * Vérifie si un pied (commençant par $legRootRecord) contient un membre avec le grade minRank.
     */
    private function legContainsMinRankRecursive(Level_current_test $legRootRecord, Collection $allChildrenMap, Collection $allDistributorsMap, int $minRank): bool
    {
        if ($legRootRecord->etoiles >= $minRank) {
            return true;
        }
        $childrenOfLegRoot = $allChildrenMap->get($legRootRecord->distributeur_id) ?? collect();
        foreach ($childrenOfLegRoot as $child) {
            // $child est un objet Level_current_test complet car il vient de $allDistributorsMap implicitement via $allChildrenMap
            if ($this->legContainsMinRankRecursive($child, $allChildrenMap, $allDistributorsMap, $minRank)) {
                return true;
            }
        }
        return false;
    }

    // Adapter countLegsWithExactRankExcludingHigherRecursive et legContainsExactRankRecursive de manière similaire...
    private function countLegsWithExactRankExcludingHigherRecursive(Collection $directChildrenRecords, Collection $allChildrenMap, Collection $allDistributorsMap, int $exactRank, int $excludingMinRank): int
    {
        $qualifiedLegs = 0;
        foreach ($directChildrenRecords as $childRecord) {
            // 1. Vérifier si le pied contient le grade supérieur (à exclure)
            if ($this->legContainsMinRankRecursive($childRecord, $allChildrenMap, $allDistributorsMap, $excludingMinRank)) {
                continue; // Ce pied est déjà qualifié pour un rang supérieur, on l'ignore
            }
            // 2. Si non exclu, vérifier s'il contient le grade exact recherché
            if ($this->legContainsExactRankRecursive($childRecord, $allChildrenMap, $allDistributorsMap, $exactRank)) {
                 $qualifiedLegs++;
            }
        }
        return $qualifiedLegs;
    }

    private function legContainsExactRankRecursive(Level_current_test $legRootRecord, Collection $allChildrenMap, Collection $allDistributorsMap, int $exactRank): bool
    {
        if ($legRootRecord->etoiles == $exactRank) {
            return true;
        }
        $childrenOfLegRoot = $allChildrenMap->get($legRootRecord->distributeur_id) ?? collect();
        foreach ($childrenOfLegRoot as $child) {
            if ($this->legContainsExactRankRecursive($child, $allChildrenMap, $allDistributorsMap, $exactRank)) {
                return true;
            }
        }
        return false;
    }
}
