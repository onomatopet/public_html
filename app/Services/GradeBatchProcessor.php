<?php
namespace App\Services;

use App\Services\EternalHelperLegacyMatriculeDB;
use App\Models\Distributeur;
use App\Services\GradeCalculator;
use App\Models\Level_current_test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception; // Import manquant

class GradeBatchProcessor
{
    private $branchQualifier;
    private $calculator;

    public function __construct()
    {
        $this->branchQualifier = new EternalHelperLegacyMatriculeDB();
        $this->calculator = new GradeCalculator();
    }

    /**
     * Traite un lot de distributeurs et met à jour leurs grades
     *
     * @param array $matricules Liste des matricules à traiter
     * @param string $period Période au format YYYY-MM
     * @return array Résultats du traitement
     */
    public function processBatch(array $matricules, string $period): array
    {
        // Charger les maps une seule fois pour tout le batch
        $this->branchQualifier->loadAndBuildMaps();

        $results = [
            'processed' => 0,
            'updated' => 0,
            'errors' => 0,
            'details' => [],
            'updates' => [],
            'failures' => []
        ];

        // Traiter chaque matricule
        foreach ($matricules as $matricule) {
            $result = $this->processSingleDistributor($matricule, $period);

            $results['processed']++;

            if (isset($result['error'])) {
                $results['errors']++;
                $results['failures'][] = $result;
            } else {
                $results['details'][] = $result;
                if ($result['mise_à_jour']) {
                    $results['updated']++;
                    $results['updates'][] = $result;
                }
            }
        }

        return $results;
    }

    /**
     * Traite un seul distributeur
     */
    private function processSingleDistributor($matricule, $period): array
    {
        try {
            DB::beginTransaction();

            $level = Level_current_test::where('distributeur_id', $matricule)
                                ->where('period', $period)
                                ->first();

            if (!$level) {
                throw new Exception("LevelCurrent non trouvé");
            }

            $oldGrade = $level->etoiles;

            // Calculer le nouveau grade
            $newGrade = $this->calculator->calculatePotentialGrade(
                $level->etoiles,
                (float)$level->cumul_individuel,
                (float)$level->cumul_collectif,
                $matricule,
                $this->branchQualifier
            );

            $result = [
                'matricule' => $matricule,
                'grade_avant' => $oldGrade,
                'grade_après' => $newGrade,
                'mise_à_jour' => false
            ];

            // Mettre à jour si nécessaire
            if ($newGrade != $oldGrade) {
                Level_current_test::where('distributeur_id', $matricule)
                    ->where('period', $period)
                    ->update(['etoiles' => $newGrade]);

                Distributeur::where('distributeur_id', $matricule)
                    ->update(['etoiles_id' => $newGrade]);

                // IMPORTANT: Mettre à jour la map en mémoire pour les calculs suivants
                $this->branchQualifier->updateNodeLevelInMap($matricule, $newGrade);

                $result['mise_à_jour'] = true;
            }

            DB::commit();
            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'error' => true,
                'matricule' => $matricule,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Traite tous les distributeurs d'une période donnée
     */
    public function processAllForPeriod(string $period, ?int $limit = null): array
    {
        $query = Level_current_test::where('period', $period)
                    ->select('distributeur_id');

        if ($limit) {
            $query->limit($limit);
        }

        $matricules = $query->pluck('distributeur_id')->toArray();

        Log::info("Démarrage du traitement batch", [
            'period' => $period,
            'total' => count($matricules)
        ]);

        $startTime = microtime(true);
        $results = $this->processBatch($matricules, $period);
        $duration = round(microtime(true) - $startTime, 2);

        Log::info("Traitement batch terminé", [
            'duration' => $duration,
            'results' => $results
        ]);

        return array_merge($results, ['duration' => $duration]);
    }
}

// UTILISATION EXEMPLE

// Pour un seul distributeur
$processor = new GradeBatchProcessor();
$result = $processor->processBatch([2273898], '2025-03');

// Pour plusieurs distributeurs
$matricules = [2273898, 2224878, 2225001];
$results = $processor->processBatch($matricules, '2025-03');

// Pour tous les distributeurs d'une période
$results = $processor->processAllForPeriod('2025-03');

// Avec une limite
$results = $processor->processAllForPeriod('2025-03', 100);
