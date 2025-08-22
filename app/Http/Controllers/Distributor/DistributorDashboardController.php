<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\Distributeur;
use App\Models\Achat;
use App\Models\Bonus;
use App\Models\LevelCurrent;
use App\Models\SystemPeriod;
use App\Services\NetworkStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DistributorDashboardController extends Controller
{
    protected NetworkStatsService $networkStats;

    public function __construct(NetworkStatsService $networkStats)
    {
        $this->networkStats = $networkStats;
    }

    /**
     * Affiche le tableau de bord du distributeur
     */
    public function index()
    {
        $user = Auth::user();
        $distributeur = $user->distributeur;

        if (!$distributeur) {
            return redirect()->route('home')
                ->with('error', 'Vous n\'êtes pas enregistré comme distributeur.');
        }

        // Période actuelle
        $currentPeriod = SystemPeriod::getCurrentPeriod();
        $period = $currentPeriod->period;

        // Statistiques principales
        $stats = $this->getMainStats($distributeur, $period);

        // Évolution des performances
        $performanceData = $this->getPerformanceEvolution($distributeur);

        // Activité récente
        $recentActivity = $this->getRecentActivity($distributeur);

        // Notifications
        $notifications = $this->getNotifications($distributeur);

        // Prochains objectifs
        $objectives = $this->getObjectives($distributeur, $period);

        return view('distributor.dashboard.index', compact(
            'distributeur',
            'stats',
            'performanceData',
            'recentActivity',
            'notifications',
            'objectives',
            'currentPeriod'
        ));
    }

    /**
     * Récupère les statistiques principales
     */
    protected function getMainStats(Distributeur $distributeur, string $period): array
    {
        // Performance du mois
        $levelCurrent = LevelCurrent::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        // Achats du mois
        $monthlyPurchases = Achat::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->where('status', 'validated')
            ->sum('montant_total_ligne');

        // Bonus du mois
        $monthlyBonus = Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->where('status', 'validated')
            ->sum('montant');

        // Statistiques réseau
        $networkStats = $this->networkStats->getStats($distributeur->id, $period);

        return [
            'grade_actuel' => $distributeur->etoiles_id,
            'points_personnels' => $levelCurrent->pv ?? 0,
            'points_groupe' => $levelCurrent->pg ?? 0,
            'cumul_individuel' => $levelCurrent->cumul_individuel ?? 0,
            'cumul_collectif' => $levelCurrent->cumul_collectif ?? 0,
            'achats_mois' => $monthlyPurchases,
            'bonus_mois' => $monthlyBonus,
            'equipe_directe' => $networkStats['direct_count'],
            'equipe_totale' => $networkStats['total_count'],
            'nouveaux_ce_mois' => $networkStats['new_this_month']
        ];
    }

    /**
     * Récupère l'évolution des performances
     */
    protected function getPerformanceEvolution(Distributeur $distributeur): array
    {
        $periods = SystemPeriod::orderBy('period', 'desc')
            ->limit(12)
            ->pluck('period');

        $evolution = [];

        foreach ($periods as $period) {
            $level = LevelCurrent::where('distributeur_id', $distributeur->id)
                ->where('period', $period)
                ->first();

            $achats = Achat::where('distributeur_id', $distributeur->id)
                ->where('period', $period)
                ->where('status', 'validated')
                ->sum('montant_total_ligne');

            $bonus = Bonus::where('distributeur_id', $distributeur->id)
                ->where('period', $period)
                ->where('status', 'validated')
                ->sum('montant');

            $evolution[] = [
                'period' => $period,
                'pv' => $level->pv ?? 0,
                'pg' => $level->pg ?? 0,
                'achats' => $achats,
                'bonus' => $bonus
            ];
        }

        return array_reverse($evolution);
    }

    /**
     * Récupère l'activité récente
     */
    protected function getRecentActivity(Distributeur $distributeur): array
    {
        $activities = [];

        // Derniers achats
        $recentPurchases = Achat::where('distributeur_id', $distributeur->id)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentPurchases as $purchase) {
            $activities[] = [
                'type' => 'purchase',
                'icon' => 'shopping-cart',
                'title' => 'Achat effectué',
                'description' => $purchase->product->nom_produit ?? 'Produit',
                'amount' => $purchase->montant_total_ligne,
                'date' => $purchase->created_at
            ];
        }

        // Derniers bonus
        $recentBonus = Bonus::where('distributeur_id', $distributeur->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentBonus as $bonus) {
            $activities[] = [
                'type' => 'bonus',
                'icon' => 'currency-dollar',
                'title' => $this->getBonusTypeLabel($bonus->type_bonus),
                'description' => 'Bonus gagné',
                'amount' => $bonus->montant,
                'date' => $bonus->created_at
            ];
        }

        // Nouveaux membres dans l'équipe
        $newMembers = Distributeur::where('id_distrib_parent', $distributeur->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($newMembers as $member) {
            $activities[] = [
                'type' => 'new_member',
                'icon' => 'user-add',
                'title' => 'Nouveau membre',
                'description' => $member->nom_distributeur . ' ' . $member->pnom_distributeur,
                'amount' => null,
                'date' => $member->created_at
            ];
        }

        // Trier par date et limiter
        usort($activities, function($a, $b) {
            return $b['date']->timestamp - $a['date']->timestamp;
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Récupère les notifications
     */
    protected function getNotifications(Distributeur $distributeur): array
    {
        $notifications = [];

        // Vérifier les objectifs du grade suivant
        $nextGrade = $distributeur->etoiles_id + 1;
        if ($nextGrade <= 7) {
            $gradeRequirements = $this->getGradeRequirements($nextGrade);
            $currentLevel = LevelCurrent::where('distributeur_id', $distributeur->id)
                ->where('period', SystemPeriod::getCurrentPeriod()->period)
                ->first();

            if ($currentLevel) {
                $progress = $this->calculateGradeProgress($currentLevel, $gradeRequirements);
                if ($progress >= 80) {
                    $notifications[] = [
                        'type' => 'info',
                        'message' => "Vous êtes à {$progress}% d'atteindre le grade {$nextGrade} !",
                        'icon' => 'star'
                    ];
                }
            }
        }

        // Bonus en attente
        $pendingBonus = Bonus::where('distributeur_id', $distributeur->id)
            ->where('status', 'pending')
            ->count();

        if ($pendingBonus > 0) {
            $notifications[] = [
                'type' => 'warning',
                'message' => "Vous avez {$pendingBonus} bonus en attente de validation",
                'icon' => 'clock'
            ];
        }

        // Nouveaux membres sans achat
        $inactiveMembers = Distributeur::where('id_distrib_parent', $distributeur->id)
            ->whereDoesntHave('achats', function($query) {
                $query->where('period', SystemPeriod::getCurrentPeriod()->period);
            })
            ->count();

        if ($inactiveMembers > 0) {
            $notifications[] = [
                'type' => 'alert',
                'message' => "{$inactiveMembers} membres de votre équipe n'ont pas encore acheté ce mois",
                'icon' => 'exclamation'
            ];
        }

        return $notifications;
    }

    /**
     * Récupère les objectifs
     */
    protected function getObjectives(Distributeur $distributeur, string $period): array
    {
        $objectives = [];
        $currentLevel = LevelCurrent::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        if (!$currentLevel) {
            return $objectives;
        }

        // Objectif de grade
        $nextGrade = $distributeur->etoiles_id + 1;
        if ($nextGrade <= 7) {
            $requirements = $this->getGradeRequirements($nextGrade);
            $objectives[] = [
                'title' => "Atteindre le grade {$nextGrade}",
                'current' => $currentLevel->cumul_individuel,
                'target' => $requirements['cumul_individuel'],
                'type' => 'cumul_individuel',
                'icon' => 'star',
                'progress' => min(100, ($currentLevel->cumul_individuel / $requirements['cumul_individuel']) * 100)
            ];

            $objectives[] = [
                'title' => "Cumul collectif pour grade {$nextGrade}",
                'current' => $currentLevel->cumul_collectif,
                'target' => $requirements['cumul_collectif'],
                'type' => 'cumul_collectif',
                'icon' => 'users',
                'progress' => min(100, ($currentLevel->cumul_collectif / $requirements['cumul_collectif']) * 100)
            ];
        }

        // Objectif mensuel de points
        $monthlyTarget = $this->getMonthlyPointsTarget($distributeur->etoiles_id);
        $objectives[] = [
            'title' => "Points personnels ce mois",
            'current' => $currentLevel->pv,
            'target' => $monthlyTarget,
            'type' => 'points_mensuels',
            'icon' => 'chart-bar',
            'progress' => min(100, ($currentLevel->pv / $monthlyTarget) * 100)
        ];

        return $objectives;
    }

    /**
     * Calcule la progression vers un grade
     */
    protected function calculateGradeProgress($currentLevel, $requirements): int
    {
        $cumulProgress = ($currentLevel->cumul_individuel / $requirements['cumul_individuel']) * 50;
        $collectifProgress = ($currentLevel->cumul_collectif / $requirements['cumul_collectif']) * 50;

        return min(100, $cumulProgress + $collectifProgress);
    }

    /**
     * Obtient les exigences d'un grade
     */
    protected function getGradeRequirements(int $grade): array
    {
        // Ces valeurs devraient venir de la configuration
        $requirements = [
            1 => ['cumul_individuel' => 1000, 'cumul_collectif' => 0],
            2 => ['cumul_individuel' => 3000, 'cumul_collectif' => 5000],
            3 => ['cumul_individuel' => 6000, 'cumul_collectif' => 15000],
            4 => ['cumul_individuel' => 12000, 'cumul_collectif' => 40000],
            5 => ['cumul_individuel' => 24000, 'cumul_collectif' => 100000],
            6 => ['cumul_individuel' => 48000, 'cumul_collectif' => 250000],
            7 => ['cumul_individuel' => 96000, 'cumul_collectif' => 500000],
        ];

        return $requirements[$grade] ?? ['cumul_individuel' => 0, 'cumul_collectif' => 0];
    }

    /**
     * Obtient l'objectif mensuel de points
     */
    protected function getMonthlyPointsTarget(int $grade): int
    {
        $targets = [
            0 => 100,
            1 => 200,
            2 => 300,
            3 => 500,
            4 => 750,
            5 => 1000,
            6 => 1500,
            7 => 2000
        ];

        return $targets[$grade] ?? 100;
    }

    /**
     * Obtient le label d'un type de bonus
     */
    protected function getBonusTypeLabel(string $type): string
    {
        return match($type) {
            'direct' => 'Bonus direct',
            'indirect' => 'Bonus indirect',
            'leadership' => 'Bonus leadership',
            'rank' => 'Bonus de grade',
            'special' => 'Bonus spécial',
            default => 'Bonus'
        };
    }
}
