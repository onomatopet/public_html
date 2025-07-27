<?php

namespace App\Services;

use App\Models\Achat;        // Important
use App\Models\Level_current_test;
use App\Models\Distributeur;
use App\Services\EternalHelperMatriculeBased;
use App\Services\GradeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

// Nom de classe pour refléter la logique : commence par les achats, utilise les matricules
class DistributorAdvancementService
{
    protected EternalHelperMatriculeBased $branchQualifier;
    protected GradeCalculator $gradeCalculator;
    protected ?Collection $matriculeToIdMap = null;

    public function __construct(EternalHelperMatriculeBased $branchQualifier, GradeCalculator $gradeCalculator)
    {
        $this->branchQualifier = $branchQualifier;
        $this->gradeCalculator = $gradeCalculator;
    }

    protected function buildMatriculeToIdMap(): bool
    {
        Log::info("Construction de la map matricule -> ID primaire...");
        try {
            $this->matriculeToIdMap = DB::table('distributeurs')
                ->whereNotNull('distributeur_id')
                ->where('distributeur_id', '!=', '')
                ->pluck('id', 'distributeur_id');

            if ($this->matriculeToIdMap === null || $this->matriculeToIdMap->isEmpty()) {
                Log::error("La map matricule -> ID est vide ou n'a pas pu être construite.");
                return false;
            }
            // Vérification Doublon Matricule (Importante)
             $duplicateCheck = DB::table('distributeurs')
                ->select('distributeur_id')
                ->whereNotNull('distributeur_id')->where('distributeur_id', '!=', '')
                ->groupBy('distributeur_id')->havingRaw('COUNT(*) > 1')->first();
             if ($duplicateCheck) {
                 Log::critical("DOUBLON de matricule trouvé: {$duplicateCheck->distributeur_id}. La logique d'avancement sera incorrecte.");
                 return false;
             }
            Log::info("Map matricule -> ID construite avec " . $this->matriculeToIdMap->count() . " entrées.");
            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la construction de la map matricule -> ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calcule l'avancement en grade des distributeurs AYANT FAIT DES ACHATS
     * pour une période donnée.
     * Suppose que achats.distributeur_id et level_currents.distributeur_id sont des MATRICULES.
     *
     * @param string $period
     * @return array
     */
    public function processAdvancementsForPeriod(string $period): array
    {
        Log::info("Début du processus d'avancement (AchatBased - Matricules) pour la période: {$period}");
        $resultsLog = [];

        if ($this->matriculeToIdMap === null) {
            if (!$this->buildMatriculeToIdMap()) {
                return ['message' => 'Erreur critique: Impossible de construire la map des matricules.', 'details' => []];
            }
        }

        // 1. Identifier les distributeurs (matricules) uniques ayant fait des achats dans la période
        $activeDistributorMatricules = Achat::where('period', $period)
            ->distinct()
            ->pluck('distributeur_id'); // Récupère une collection de matricules

        if ($activeDistributorMatricules->isEmpty()) {
            Log::info("Aucun achat trouvé pour la période {$period}. Aucun avancement à traiter.");
            return ['message' => 'Aucun achat trouvé pour cette période.', 'details' => []];
        }

        Log::info(count($activeDistributorMatricules) . " distributeurs uniques avec achats à vérifier pour avancement.");

        DB::beginTransaction();

        try {
            foreach ($activeDistributorMatricules as $currentMatricule) {
                // 2. Pour chaque matricule actif, récupérer son Level_current_test (où distributeur_id est un matricule)
                $levelEntry = Level_current_test::where('distributeur_id', $currentMatricule)
                                         ->where('period', $period)
                                         ->select(
                                             'etoiles',
                                             'cumul_individuel',
                                             'cumul_collectif'
                                             // On n'a pas besoin de 'distributeur_id' ici car on l'a déjà ($currentMatricule)
                                         )
                                         ->first();

                if (!$levelEntry) {
                    Log::warning("Aucun enregistrement Level_current_test trouvé pour Matricule {$currentMatricule} (période {$period}). Ligne d'achat ignorée pour l'avancement.");
                    $resultsLog[] = [
                        'matricule' => $currentMatricule,
                        'status' => 'Erreur: Level_current_test non trouvé pour la période',
                        'etoiles_actuel' => 'N/A',
                        'etoiles_avancement' => 'N/A',
                        'branches_details' => null
                    ];
                    continue;
                }

                // 3. Récupérer l'ID PRIMAIRE correspondant à ce matricule
                $currentPrimaryId = $this->matriculeToIdMap->get($currentMatricule);

                if (!$currentPrimaryId) {
                    Log::warning("Distributeur ID Primaire non trouvé dans la map pour Matricule {$currentMatricule} (actif par achat). Ligne sautée.");
                    $resultsLog[] = [
                        'matricule' => $currentMatricule,
                        'etoiles_actuel' => $levelEntry->etoiles,
                        'status' => 'Erreur: Matricule non trouvé dans la table distributeurs',
                        'etoiles_avancement' => $levelEntry->etoiles,
                        'branches_details' => null
                    ];
                    continue;
                }

                Log::debug("Traitement Matricule: {$currentMatricule} (ID Primaire: {$currentPrimaryId}), Etoiles Actuelles: {$levelEntry->etoiles}");

                // 4. Calcul des Branches Qualifiées
                $branchQualificationCounts = $this->branchQualifier->checkMultiLevelQualificationSeparateCountsMatricule(
                    $currentPrimaryId,
                    $levelEntry->etoiles
                );

                if (isset($branchQualificationCounts['error'])) {
                    Log::error("Erreur lors de la qualification des branches pour Matricule {$currentMatricule}: " . $branchQualificationCounts['error']);
                    $resultsLog[] = [ /* ... */ ];
                    continue;
                }

                $pass1 = $branchQualificationCounts['level_n_qualified_count'];
                $pass2 = $branchQualificationCounts['level_n_minus_1_qualified_count'];

                Log::debug("  Branches qualifiées - Pass1 (Niveau {$levelEntry->etoiles}): {$pass1}, Pass2 (Niveau " . max(1, $levelEntry->etoiles - 1) . "): {$pass2}");

                // 5. Calcul du Grade Potentiel
                $newPotentialLevel = $this->gradeCalculator->calculatePotentialGrade(
                    $levelEntry->etoiles,
                    (float)$levelEntry->cumul_individuel,
                    (float)$levelEntry->cumul_collectif,
                    $pass1,
                    $pass2,
                    $currentPrimaryId
                );

                Log::debug("  Niveau potentiel calculé: {$newPotentialLevel}");

                $statusMessage = 'Aucun changement';
                if ($newPotentialLevel > $levelEntry->etoiles) {
                    $statusMessage = 'Avancement en grade';

                    $distUpdateCount = Distributeur::where('distributeur_id', $currentMatricule)
                                ->update(['etoiles_id' => $newPotentialLevel]);
                    Log::info("  MAJ Distributeur (Matricule {$currentMatricule}): etoiles_id -> {$newPotentialLevel}. Lignes affectées: {$distUpdateCount}");

                    $lcUpdateCount = Level_current_test::where('distributeur_id', $currentMatricule)
                                 ->where('period', $period)
                                 ->update(['etoiles' => $newPotentialLevel]);
                    Log::info("  MAJ Level_current_test (Matricule {$currentMatricule}, Période {$period}): etoiles -> {$newPotentialLevel}. Lignes affectées: {$lcUpdateCount}");
                }

                $resultsLog[] = [
                    'matricule' => $currentMatricule,
                    'etoiles_actuel' => $levelEntry->etoiles,
                    'etoiles_avancement' => $newPotentialLevel,
                    'status' => $statusMessage,
                    'branches_details' => $branchQualificationCounts
                ];
            } // Fin foreach

            DB::commit();
            Log::info("Processus d'avancement (AchatBased - Matricules) terminé avec succès pour la période {$period}.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::critical("Erreur critique pendant le processus d'avancement (AchatBased - Matricules) pour {$period}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return ['message' => 'Erreur critique pendant le processus.', 'error' => $e->getMessage(), 'details' => $resultsLog];
        }

        return ['message' => 'Processus terminé.', 'details' => $resultsLog];
    }
}

// --- Utilisation ---
/*
$branchQualifierService = app(\App\Services\EternalHelperMatriculeBased::class);
$gradeCalculatorService = app(\App\Services\GradeCalculator::class);

$advancementService = new \App\Services\AchatBasedAdvancementMatriculeService($branchQualifierService, $gradeCalculatorService);
$result = $advancementService->processAdvancementsForPeriod('2025-03');

dd($result);
*/

