<?php

namespace App\Services;

use App\Models\SystemPeriod;
use App\Models\Achat;
use App\Models\Levelcurrent;

class WorkflowService
{
    /**
     * Obtient le statut complet du workflow pour une période
     */
    public function getWorkflowStatus(SystemPeriod $period): array
    {
        $status = [
            'period_opened' => [
                'completed' => $period->status !== 'draft',
                'label' => 'Période ouverte',
                'icon' => 'calendar',
                'can_execute' => false
            ],
            'period_active' => [
                'completed' => $period->status === 'active',
                'label' => 'Période activée',
                'icon' => 'play-circle',
                'can_execute' => $period->status === 'open'
            ],
            'purchases_validated' => [
                'completed' => $period->purchases_validated,
                'label' => 'Achats validés',
                'icon' => 'check-circle',
                'can_execute' => $this->canValidatePurchases($period),
                'stats' => $this->getPurchaseValidationStats($period)
            ],
            'purchases_aggregated' => [
                'completed' => $period->purchases_aggregated,
                'label' => 'Achats agrégés',
                'icon' => 'calculator',
                'can_execute' => $this->canAggregatePurchases($period),
                'stats' => $this->getAggregationStats($period)
            ],
            'advancements_calculated' => [
                'completed' => $period->advancements_calculated,
                'label' => 'Avancements calculés',
                'icon' => 'trending-up',
                'can_execute' => $this->canCalculateAdvancements($period),
                'stats' => $this->getAdvancementStats($period)
            ],
            'snapshot_created' => [
                'completed' => $period->snapshot_created,
                'label' => 'Snapshot créé',
                'icon' => 'camera',
                'can_execute' => $this->canCreateSnapshot($period)
            ],
            'period_closed' => [
                'completed' => $period->status === 'closed',
                'label' => 'Période clôturée',
                'icon' => 'lock',
                'can_execute' => $this->canClosePeriod($period)
            ]
        ];

        // Calculer la progression globale
        $completedSteps = collect($status)->filter(fn($step) => $step['completed'])->count();
        $totalSteps = count($status);
        $progressPercentage = ($completedSteps / $totalSteps) * 100;

        return [
            'steps' => $status,
            'progress' => [
                'completed' => $completedSteps,
                'total' => $totalSteps,
                'percentage' => $progressPercentage
            ]
        ];
    }

    /**
     * Vérifie si on peut valider les achats
     */
    public function canValidatePurchases(SystemPeriod $period): bool
    {
        return $period->status === 'active' && !$period->purchases_validated;
    }

    /**
     * Vérifie si on peut agréger les achats
     */
    public function canAggregatePurchases(SystemPeriod $period): bool
    {
        return $period->purchases_validated && !$period->purchases_aggregated;
    }

    /**
     * Vérifie si on peut calculer les avancements
     */
    public function canCalculateAdvancements(SystemPeriod $period): bool
    {
        return $period->purchases_aggregated && !$period->advancements_calculated;
    }

    /**
     * Vérifie si on peut créer un snapshot
     */
    public function canCreateSnapshot(SystemPeriod $period): bool
    {
        return $period->advancements_calculated && !$period->snapshot_created;
    }

    /**
     * Vérifie si on peut clôturer la période
     */
    public function canClosePeriod(SystemPeriod $period): bool
    {
        return $period->snapshot_created && $period->status !== 'closed';
    }

    /**
     * Obtient les statistiques de validation des achats
     */
    protected function getPurchaseValidationStats(SystemPeriod $period): array
    {
        $query = Achat::where('period', $period->period);

        return [
            'total' => $query->count(),
            'validated' => $query->where('status', 'validated')->count(),
            'pending' => $query->where('status', 'pending')->count(),
            'rejected' => $query->where('status', 'rejected')->count()
        ];
    }

    /**
     * Obtient les statistiques d'agrégation
     */
    protected function getAggregationStats(SystemPeriod $period): array
    {
        $levelCurrents = Levelcurrent::where('period', $period->period)->count();
        $totalNewCumul = Levelcurrent::where('period', $period->period)->sum('new_cumul');

        return [
            'distributeurs_impactes' => $levelCurrents,
            'total_points' => number_format($totalNewCumul, 0, ',', ' ')
        ];
    }

    /**
     * Obtient les statistiques d'avancement
     */
    protected function getAdvancementStats(SystemPeriod $period): array
    {
        // Compter les avancements depuis la table historique
        $avancements = \DB::table('avancement_history')
            ->where('period', $period->period)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN nouveau_grade > ancien_grade THEN 1 ELSE 0 END) as promotions,
                SUM(CASE WHEN nouveau_grade < ancien_grade THEN 1 ELSE 0 END) as demotions
            ')
            ->first();

        return [
            'total' => $avancements->total ?? 0,
            'promotions' => $avancements->promotions ?? 0,
            'demotions' => $avancements->demotions ?? 0
        ];
    }

    /**
     * Vérifie l'intégrité du workflow
     */
    public function checkWorkflowIntegrity(SystemPeriod $period): array
    {
        $issues = [];

        // Vérifier la séquence des étapes
        if ($period->purchases_aggregated && !$period->purchases_validated) {
            $issues[] = 'Agrégation effectuée sans validation des achats';
        }

        if ($period->advancements_calculated && !$period->purchases_aggregated) {
            $issues[] = 'Avancements calculés sans agrégation des achats';
        }

        if ($period->snapshot_created && !$period->advancements_calculated) {
            $issues[] = 'Snapshot créé sans calcul des avancements';
        }

        if ($period->status === 'closed' && !$period->snapshot_created) {
            $issues[] = 'Période clôturée sans création de snapshot';
        }

        // Vérifier les timestamps
        if ($period->purchases_validated_at && $period->purchases_aggregated_at
            && $period->purchases_aggregated_at < $period->purchases_validated_at) {
            $issues[] = 'Agrégation effectuée avant la validation';
        }

        return $issues;
    }
}
