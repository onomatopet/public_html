<?php

namespace App\Services;

use App\Models\TempGradeCalculation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Extension du DistributorLineageService pour supporter la table temporaire
 *
 * @method int|null resolveDistributorId($identifier)
 * @method array checkSpecificGradeEligibility(int $currentGrade, int $targetGrade, float $cumulIndividuel, float $cumulCollectif, array $branchAnalysis, bool $includeDetails)
 * @method void loadDistributorsData()
 *
 * Note: Cette classe utilise la réflexion pour accéder aux propriétés privées de la classe parent
 * car elles ne sont pas accessibles directement.
 */
class DistributorLineageServiceAdapter extends DistributorLineageService
{
    /**
     * Session ID pour la table temporaire
     */
    private ?string $calculationSessionId = null;

    /**
     * Override clearCache pour éviter l'erreur de cache tags
     */
    public function clearCache(): void
    {
        // Réinitialiser le cache interne sans utiliser les tags
        // car le driver de cache actuel ne les supporte pas

        // Utiliser la réflexion pour accéder au cache interne
        $reflection = new \ReflectionClass(parent::class);

        if ($reflection->hasProperty('lineageCache')) {
            $lineageCacheProp = $reflection->getProperty('lineageCache');
            $lineageCacheProp->setAccessible(true);
            $lineageCacheProp->setValue($this, []);
        }

        // Note: On ne peut pas effacer le cache Laravel sans tags,
        // mais ce n'est pas grave car nous utilisons la table temporaire
    }

    /**
     * Active l'utilisation de la table temporaire
     */
    public function setCalculationSession(?string $sessionId): void
    {
        $this->calculationSessionId = $sessionId;
        // Forcer le rechargement des données
        $this->clearCache();

        // Utiliser la réflexion pour réinitialiser les propriétés privées du parent
        $reflection = new \ReflectionClass(parent::class);

        $distributorsMapProp = $reflection->getProperty('distributorsMap');
        $distributorsMapProp->setAccessible(true);
        $distributorsMapProp->setValue($this, null);

        $parentChildrenMapProp = $reflection->getProperty('parentChildrenMap');
        $parentChildrenMapProp->setAccessible(true);
        $parentChildrenMapProp->setValue($this, null);
    }

    /**
     * Override de checkGradeEligibility pour utiliser la table temporaire
     */
    public function checkGradeEligibility($distributorIdentifier, string $period, array $options = []): array
    {
        // Si pas de session, utiliser le comportement parent normal
        if (!$this->calculationSessionId) {
            // Charger les données une seule fois si pas déjà fait
            $reflection = new \ReflectionClass(parent::class);
            $distributorsMapProp = $reflection->getProperty('distributorsMap');
            $distributorsMapProp->setAccessible(true);

            if ($distributorsMapProp->getValue($this) === null) {
                $method = $reflection->getMethod('loadDistributorsData');
                $method->setAccessible(true);
                $method->invoke($this);
            }

            return parent::checkGradeEligibility($distributorIdentifier, $period, $options);
        }

        $defaultOptions = [
            'target_grade' => null,
            'check_all_possible' => true,
            'include_details' => true,
            'use_cache' => true,
            'only_validated' => true,
            'debug' => false,
            'stop_on_first_failure' => true
        ];

        $options = array_merge($defaultOptions, $options);

        // Valider le format de la période
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return [
                'error' => 'Format de période invalide. Utilisez YYYY-MM',
                'eligible' => false
            ];
        }

        // Résoudre l'identifiant du distributeur nous-mêmes
        $distributorId = null;
        if (is_numeric($distributorIdentifier) && $distributorIdentifier < 1000000) {
            // Probablement un ID primaire
            $distributorId = (int) $distributorIdentifier;
        } else {
            // Rechercher par matricule
            $distributor = DB::table('distributeurs')
                ->where('distributeur_id', $distributorIdentifier)
                ->select('id')
                ->first();
            $distributorId = $distributor ? $distributor->id : null;
        }

        if (!$distributorId) {
            return [
                'error' => 'Distributeur non trouvé',
                'eligible' => false
            ];
        }

        // Récupérer depuis la table temporaire
        $tempData = TempGradeCalculation::forSession($this->calculationSessionId)
            ->where('distributeur_id', $distributorId)
            ->where('period', $period)
            ->first();

        if (!$tempData) {
            return [
                'error' => 'Aucune donnée temporaire pour ce distributeur',
                'eligible' => false
            ];
        }

        // Utiliser les données de la table temporaire
        $currentGrade = $tempData->grade_actuel;
        $cumulIndividuel = $tempData->cumul_individuel;
        $cumulCollectif = $tempData->cumul_collectif;

        // Récupérer le distributeur pour les infos de base
        $distributor = $tempData->distributeur;

        // Pour l'analyse des branches, nous devons utiliser la méthode parent
        // mais avec les données mises à jour depuis la table temporaire
        // Ne recharger que si nécessaire
        $reflection = new \ReflectionClass(parent::class);
        $distributorsMapProp = $reflection->getProperty('distributorsMap');
        $distributorsMapProp->setAccessible(true);

        if ($distributorsMapProp->getValue($this) === null) {
            $method = $reflection->getMethod('loadDistributorsData');
            $method->setAccessible(true);
            $method->invoke($this);
        }

        // Maintenant appeler la méthode parent avec les bonnes données
        return parent::checkGradeEligibility($distributor->distributeur_id, $period, $options);
    }

    /**
     * Override pour charger les données depuis la table temporaire
     */
    protected function loadDistributorsData(): void
    {
        if (!$this->calculationSessionId) {
            // Appeler la méthode privée du parent via réflexion
            $reflection = new \ReflectionClass(parent::class);
            $method = $reflection->getMethod('loadDistributorsData');
            $method->setAccessible(true);
            $method->invoke($this);
            return;
        }

        Log::info("Chargement des données depuis la table temporaire (session: {$this->calculationSessionId})...");

        // Vérifier si la table temporaire contient des données
        $tempCount = DB::table('temp_grade_calculations')
            ->where('calculation_session_id', $this->calculationSessionId)
            ->count();

        if ($tempCount == 0) {
            // Si la table temporaire est vide, charger depuis la table normale
            $reflection = new \ReflectionClass(parent::class);
            $method = $reflection->getMethod('loadDistributorsData');
            $method->setAccessible(true);
            $method->invoke($this);
            return;
        }

        // Joindre temp_grade_calculations avec distributeurs
        $allDistributors = DB::table('temp_grade_calculations as tgc')
            ->join('distributeurs as d', 'tgc.distributeur_id', '=', 'd.id')
            ->where('tgc.calculation_session_id', $this->calculationSessionId)
            ->select(
                'd.id',
                'd.distributeur_id',
                'tgc.grade_actuel as etoiles_id', // Utiliser le grade de la table temp
                'd.id_distrib_parent',
                'd.rang',
                'd.nom_distributeur',
                'd.pnom_distributeur',
                'd.tel_distributeur',
                'd.adress_distributeur',
                'd.statut_validation_periode',
                'd.is_indivual_cumul_checked',
                'd.created_at',
                'd.updated_at'
            )
            ->get();

        // Utiliser la réflexion pour accéder aux propriétés privées du parent
        $reflection = new \ReflectionClass(parent::class);

        $distributorsMapProp = $reflection->getProperty('distributorsMap');
        $distributorsMapProp->setAccessible(true);
        $distributorsMapProp->setValue($this, $allDistributors->keyBy('id'));

        $parentChildrenMapProp = $reflection->getProperty('parentChildrenMap');
        $parentChildrenMapProp->setAccessible(true);
        $parentChildrenMapProp->setValue($this, collect());

        $parentChildrenMap = collect();
        foreach ($allDistributors as $distributor) {
            if ($distributor->id_distrib_parent) {
                if (!$parentChildrenMap->has($distributor->id_distrib_parent)) {
                    $parentChildrenMap->put($distributor->id_distrib_parent, collect());
                }
                $parentChildrenMap->get($distributor->id_distrib_parent)->push($distributor);
            }
        }
        $parentChildrenMapProp->setValue($this, $parentChildrenMap);

        Log::info("Données temporaires chargées: {$allDistributors->count()} distributeurs");
    }
}
