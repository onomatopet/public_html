<?php

namespace App\Services; // Assurez-vous que le namespace est correct

use App\Models\Achat;
use App\Models\Level_current_test;
use App\Models\Distributeur;
use App\Services\EternalHelperMatriculeBasedNoDoctrine; // Service pour l'analyse de branche
use App\Services\GradeCalculator;                     // Service pour le calcul de grade
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AchatBasedAdvancementMatriculeService
{
    protected EternalHelperMatriculeBasedNoDoctrine $branchQualifier;
    protected GradeCalculator $gradeCalculator;

    /**
     * Map [matricule => id_primaire] spécifique à ce service pour la conversion initiale.
     * @var Collection|null
     */
    protected ?Collection $serviceMatriculeToIdMap = null;

    public function __construct(EternalHelperMatriculeBasedNoDoctrine $branchQualifier, GradeCalculator $gradeCalculator)
    {
        $this->branchQualifier = $branchQualifier;
        $this->gradeCalculator = $gradeCalculator;
    }

    /**
     * Construit la map matricule => ID primaire à partir de la table distributeurs
     * pour être utilisée par CE service.
     * Vérifie aussi les doublons de matricule.
     *
     * @return bool True en cas de succès, false sinon.
     */
    protected function buildServiceMatriculeToIdMap(): bool
    {
        Log::info("SERVICE: Construction de la map matricule -> ID primaire...");
        try {
            // Récupérer les matricules et leurs ID primaires correspondants
            $this->serviceMatriculeToIdMap = DB::table('distributeurs')
                ->whereNotNull('distributeur_id') // Exclure les matricules NULL
                ->where('distributeur_id', '!=', '')    // Exclure les matricules vides
                ->pluck('id', 'distributeur_id'); // Clé: matricule, Valeur: id primaire

            if ($this->serviceMatriculeToIdMap === null || $this->serviceMatriculeToIdMap->isEmpty()) {
                Log::error("SERVICE: La map matricule -> ID est vide ou n'a pas pu être construite.");
                return false;
            }

            // Vérification de l'unicité des matricules (critique)
            $duplicateCheck = DB::table('distributeurs')
                ->select('distributeur_id')
                ->whereNotNull('distributeur_id')
                ->where('distributeur_id', '!=', '')
                ->groupBy('distributeur_id')
                ->havingRaw('COUNT(*) > 1')
                ->first();

            if ($duplicateCheck) {
                Log::critical("SERVICE: DOUBLON DE MATRICULE TROUVÉ DANS 'distributeurs': {$duplicateCheck->distributeur_id}. Le processus d'avancement sera incorrect et est arrêté.");
                // Vous pourriez vouloir lever une exception ici pour un arrêt plus formel
                // throw new \RuntimeException("Doublon de matricule détecté: {$duplicateCheck->distributeur_id}");
                return false;
            }

            Log::info("SERVICE: Map matricule -> ID construite avec " . $this->serviceMatriculeToIdMap->count() . " entrées.");
            return true;
        } catch (\Exception $e) {
            Log::error("SERVICE: Erreur lors de la construction de la map matricule -> ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calcule l'avancement en grade des distributeurs AYANT FAIT DES ACHATS
     * pour une période donnée.
     * Suppose que achats.distributeur_id et level_currents.distributeur_id sont des MATRICULES.
     * EternalHelper s'attend à un ID primaire pour commencer l'analyse de branche.
     *
     * @param string $period
     * @return array
     */
    public function processAdvancementsForPeriod(string $period): array
    {
        Log::info("Début du processus d'avancement (AchatBased - Matricules) pour la période: {$period}");
        $resultsLog = []; // Pour stocker le résultat détaillé de chaque distributeur traité

        // Construire la map matricule->ID pour ce service si elle n'existe pas déjà
        if ($this->serviceMatriculeToIdMap === null) {
            if (!$this->buildServiceMatriculeToIdMap()) {
                return ['message' => 'Erreur critique: Impossible de construire la map des matricules pour le service.', 'details' => [], 'status' => 'error'];
            }
        }

        // 1. Identifier les distributeurs (matricules) uniques ayant fait des achats dans la période
        $activeDistributorMatricules = Achat::where('period', $period)
            ->distinct()
            ->pluck('distributeur_id'); // Récupère une collection de matricules

        if ($activeDistributorMatricules->isEmpty()) {
            Log::info("Aucun achat trouvé pour la période {$period}. Aucun avancement à traiter.");
            return ['message' => 'Aucun achat trouvé pour cette période.', 'details' => [], 'status' => 'no_data'];
        }

        Log::info(count($activeDistributorMatricules) . " distributeurs uniques avec achats à vérifier pour avancement.");

        DB::beginTransaction(); // Démarrer une transaction globale pour toutes les mises à jour

        try {
            foreach ($activeDistributorMatricules as $currentMatricule) {
                // Initialisation du log pour ce matricule
                $currentMatriculeLog = [
                    'matricule' => $currentMatricule,
                    'etoiles_actuel' => 'N/A',
                    'etoiles_avancement' => 'N/A',
                    'status' => 'Non traité',
                    'branches_details' => null
                ];

                // 2. Pour chaque matricule actif, récupérer son Level_current_test
                $levelEntry = Level_current_test::where('distributeur_id', $currentMatricule) // `distributeur_id` est un matricule ici
                                         ->where('period', $period)->select('etoiles', 'cumul_individue
                                         l', 'cumul_collectif')
                                         ->first();

                if (!$levelEntry) {
                    Log::warning("SERVICE: Aucun enregistrement Level_current_test trouvé pour Matricule {$currentMatricule} (période {$period}). Avancement ignoré pour cet achat.");
                    $currentMatriculeLog['status'] = 'Erreur: Level_current_test non trouvé pour la période';
                    $resultsLog[] = $currentMatriculeLog;
                    continue; // Passer au matricule suivant
                }
                $currentMatriculeLog['etoiles_actuel'] = $levelEntry->etoiles;
                $currentMatriculeLog['etoiles_avancement'] = $levelEntry->etoiles; // Par défaut

                // 3. Récupérer l'ID PRIMAIRE correspondant à ce matricule pour l'analyse de branche
                $currentPrimaryId = $this->serviceMatriculeToIdMap->get($currentMatricule);

                if (!$currentPrimaryId) {
                    Log::warning("SERVICE: ID Primaire non trouvé dans la map pour Matricule {$currentMatricule} (actif par achat). Avancement ignoré.");
                    $currentMatriculeLog['status'] = 'Erreur: Matricule non présent dans la table distributeurs';
                    $resultsLog[] = $currentMatriculeLog;
                    continue;
                }

                Log::debug("SERVICE: Traitement Matricule: {$currentMatricule} (ID Primaire: {$currentPrimaryId}), Etoiles Actuelles: {$levelEntry->etoiles}");

                // 4. Demander à EternalHelper de charger/préparer SES données pour CET ID racine
                // Cela remplit $this->branchQualifier->descendantsMap, matriculeToIdMap, etc.
                if (!$this->branchQualifier->primeDataForAllDescendants($currentPrimaryId)) {
                    Log::error("SERVICE: Échec du chargement des données de descendance dans EternalHelper pour la racine ID {$currentPrimaryId} (Matricule {$currentMatricule}).");
                    $currentMatriculeLog['status'] = "Erreur: Echec chargement descendance pour helper (ID: {$currentPrimaryId})";
                    $resultsLog[] = $currentMatriculeLog;
                    continue;
                }

                // 5. Calcul des Branches Qualifiées via EternalHelper
                $branchQualificationCounts = $this->branchQualifier->checkMultiLevelQualificationSeparateCountsMatricule(
                    $currentPrimaryId, // ID primaire de la racine de branche actuelle
                    $levelEntry->etoiles // Niveau actuel comme référence 'N'
                );

                $currentMatriculeLog['branches_details'] = $branchQualificationCounts; // Sauvegarder les détails

                if (isset($branchQualificationCounts['error'])) {
                    Log::error("SERVICE: Erreur lors de la qualification des branches pour Matricule {$currentMatricule}: " . $branchQualificationCounts['error']);
                    $currentMatriculeLog['status'] = 'Erreur: ' . $branchQualificationCounts['error'];
                    $resultsLog[] = $currentMatriculeLog;
                    continue;
                }

                $pass1 = $branchQualificationCounts['level_n_qualified_count'];
                $pass2 = $branchQualificationCounts['level_n_minus_1_qualified_count'];

                Log::debug("  Branches qualifiées - Pass1 (Niveau {$levelEntry->etoiles}): {$pass1}, Pass2 (Niveau " . max(1, $levelEntry->etoiles - 1) . "): {$pass2}");

                // 6. Calcul du Grade Potentiel
                $newPotentialLevel = $this->gradeCalculator->calculatePotentialGrade(
                    $levelEntry->etoiles,
                    (float)$levelEntry->cumul_individuel,
                    (float)$levelEntry->cumul_collectif,
                    $pass1,
                    $pass2,
                    $currentPrimaryId // ID primaire pour logging dans GradeCalculator
                );
                $currentMatriculeLog['etoiles_avancement'] = $newPotentialLevel;
                Log::debug("  Niveau potentiel calculé: {$newPotentialLevel}");

                if ($newPotentialLevel > $levelEntry->etoiles) {
                    $currentMatriculeLog['status'] = 'Avancement en grade';

                    $distUpdateCount = Distributeur::where('distributeur_id', $currentMatricule)
                                ->update(['etoiles_id' => $newPotentialLevel]);
                    Log::info("  MAJ Distributeur (Matricule {$currentMatricule}): etoiles_id -> {$newPotentialLevel}. Lignes affectées: {$distUpdateCount}");

                    $lcUpdateCount = Level_current_test::where('distributeur_id', $currentMatricule)
                                 ->where('period', $period)
                                 ->update(['etoiles' => $newPotentialLevel]);
                    Log::info("  MAJ Level_current_test (Matricule {$currentMatricule}, Période {$period}): etoiles -> {$newPotentialLevel}. Lignes affectées: {$lcUpdateCount}");
                } else {
                    $currentMatriculeLog['status'] = 'Aucun changement de grade';
                }
                $resultsLog[] = $currentMatriculeLog; // Ajouter le log final pour ce distributeur
            } // Fin foreach

            DB::commit(); // Valider toutes les transactions si tout s'est bien passé
            Log::info("Processus d'avancement (AchatBased - Matricules) terminé avec succès pour la période {$period}.");

        } catch (\Exception $e) {
            DB::rollBack(); // Annuler toutes les modifications en cas d'erreur
            Log::critical("Erreur critique pendant le processus d'avancement (AchatBased - Matricules) pour {$period}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [
                'message' => 'Erreur critique pendant le processus.',
                'error' => $e->getMessage(),
                'details' => $resultsLog, // Retourner les logs partiels
                'status' => 'error'
            ];
        }

        return [
            'message' => 'Processus terminé.',
            'details' => $resultsLog,
            'status' => 'success'
        ];
    }
}
