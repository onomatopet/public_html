<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributeur;
use App\Models\Achat;
use App\Models\ModificationRequest;
use App\Models\DeletionRequest;
use App\Models\LevelCurrent;
use App\Models\Bonus;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request)
    {
        // 1. Récupérer les périodes disponibles pour le sélecteur
        $availablePeriods = $this->getAvailablePeriods();

        // 2. Période sélectionnée (par défaut la période courante)
        $period = $request->get('period', date('Y-m'));

        // 3. Vérifier que la période est valide
        if (!$availablePeriods->contains($period)) {
            $period = $availablePeriods->first() ?? date('Y-m');
        }

        // 4. Récupérer les données du dashboard via le service
        $dashboardData = $this->dashboardService->getDashboardData($period);

        // 5. Statistiques générales
        $stats = [
            'total_distributeurs' => Distributeur::count(),
            'active_distributeurs' => Distributeur::where('created_at', '>=', now()->subDays(30))->count(),
            'total_achats' => Achat::where('period', $period)->count(),
            // Utiliser points_unitaire_achat au lieu de point_achat
            'revenue_month' => Achat::where('period', $period)
                ->sum(DB::raw('points_unitaire_achat * qt')),
            'pending_modifications' => ModificationRequest::pending()->count(),
            'pending_deletions' => DeletionRequest::pending()->count(),
        ];

        // 6. Données supplémentaires pour les graphiques
        $monthlyRevenue = $this->getMonthlyRevenue();
        $topDistributeurs = $this->getTopDistributeurs($period);
        $recentActivities = $this->getRecentActivities();

        // 7. Alertes système
        $alerts = $this->getSystemAlerts();

        return view('admin.dashboard.index', compact(
            'stats',
            'monthlyRevenue',
            'topDistributeurs',
            'recentActivities',
            'availablePeriods',
            'period',
            'dashboardData',
            'alerts'
        ));
    }

    public function performance(Request $request)
    {
        // Récupérer les périodes pour le sélecteur
        $availablePeriods = $this->getAvailablePeriods();
        $period = $request->get('period', date('Y-m'));

        // Données de performance
        $performanceData = [
            'grade_distribution' => $this->getGradeDistribution($period),
            'network_growth' => $this->getNetworkGrowth(),
            'bonus_statistics' => $this->getBonusStatistics($period),
            'top_performers' => $this->getTopPerformers($period),
        ];

        return view('admin.dashboard.performance', compact(
            'performanceData',
            'availablePeriods',
            'period'
        ));
    }

    /**
     * Récupérer toutes les périodes disponibles dans le système
     */
    private function getAvailablePeriods()
    {
        // Récupérer les périodes depuis plusieurs sources et les combiner
        $achatPeriods = Achat::select('period')
            ->distinct()
            ->whereNotNull('period')
            ->pluck('period');

        $levelPeriods = LevelCurrent::select('period')
            ->distinct()
            ->whereNotNull('period')
            ->pluck('period');

        $bonusPeriods = Bonus::select('period')
            ->distinct()
            ->whereNotNull('period')
            ->pluck('period');

        // Combiner toutes les périodes et les trier
        $allPeriods = $achatPeriods
            ->merge($levelPeriods)
            ->merge($bonusPeriods)
            ->unique()
            ->sort()
            ->reverse()
            ->values();

        // S'assurer qu'au moins la période courante est disponible
        if ($allPeriods->isEmpty()) {
            $allPeriods = collect([date('Y-m')]);
        }

        return $allPeriods;
    }

    /**
     * Récupérer les revenus mensuels
     */
    private function getMonthlyRevenue()
    {
        return Achat::selectRaw('
                SUBSTRING(period, 1, 7) as month,
                SUM(points_unitaire_achat * qt) as total,
                COUNT(*) as count_achats,
                COUNT(DISTINCT distributeur_id) as count_distributeurs
            ')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => Carbon::parse($item->month)->format('M Y'),
                    'month_number' => Carbon::parse($item->month)->month,
                    'total' => $item->total ?? 0,
                    'count_achats' => $item->count_achats,
                    'count_distributeurs' => $item->count_distributeurs,
                ];
            });
    }

    /**
     * Récupérer les top distributeurs pour une période
     */
    private function getTopDistributeurs($period)
    {
        return Distributeur::select(
                'distributeurs.id',
                'distributeurs.distributeur_id',
                'distributeurs.nom_distributeur',
                'distributeurs.pnom_distributeur',
                DB::raw('COALESCE(lc.etoiles, 0) as grade_actuel'),
                DB::raw('COALESCE(lc.cumul_individuel, 0) as cumul_individuel'),
                DB::raw('COALESCE(lc.cumul_collectif, 0) as cumul_collectif')
            )
            ->leftJoin('level_currents as lc', function($join) use ($period) {
                $join->on('distributeurs.id', '=', 'lc.distributeur_id')
                     ->where('lc.period', '=', $period);
            })
            ->orderBy('cumul_individuel', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Récupérer les activités récentes
     */
    private function getRecentActivities()
    {
        $activities = collect();

        // Nouveaux distributeurs
        $newDistributeurs = Distributeur::latest()
            ->limit(5)
            ->get()
            ->map(function ($dist) {
                return [
                    'type' => 'new_distributor',
                    'title' => 'Nouveau distributeur',
                    'description' => "{$dist->nom_distributeur} {$dist->pnom_distributeur} a rejoint le réseau",
                    'created_at' => $dist->created_at,
                    'icon' => 'user-plus',
                    'color' => 'green'
                ];
            });

        // Achats récents importants
        $recentPurchases = Achat::with('distributeur')
            ->where('montant_total_ligne', '>', 100000)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($achat) {
                return [
                    'type' => 'purchase',
                    'title' => 'Achat important',
                    'description' => "Achat de " . number_format($achat->montant_total_ligne) . " FCFA",
                    'created_at' => $achat->created_at,
                    'icon' => 'shopping-cart',
                    'color' => 'blue'
                ];
            });

        // Demandes de modification
        $modRequests = ModificationRequest::with('distributeur')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($req) {
                return [
                    'type' => 'modification',
                    'title' => 'Demande de modification',
                    'description' => "Grade {$req->old_grade} → {$req->new_grade}",
                    'created_at' => $req->created_at,
                    'icon' => 'pencil',
                    'color' => 'yellow'
                ];
            });

        return $activities
            ->merge($newDistributeurs)
            ->merge($recentPurchases)
            ->merge($modRequests)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
    }

    /**
     * Récupérer la distribution des grades
     */
    private function getGradeDistribution($period)
    {
        return LevelCurrent::select('etoiles', DB::raw('COUNT(*) as count'))
            ->where('period', $period)
            ->groupBy('etoiles')
            ->orderBy('etoiles')
            ->get()
            ->map(function ($item) {
                return [
                    'grade' => $item->etoiles,
                    'count' => $item->count,
                    'percentage' => 0 // Sera calculé côté vue
                ];
            });
    }

    /**
     * Récupérer la croissance du réseau
     */
    private function getNetworkGrowth()
    {
        $months = collect();

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Distributeur::where('created_at', '<=', $date->endOfMonth())->count();

            $months->push([
                'month' => $date->format('M Y'),
                'count' => $count
            ]);
        }

        return $months;
    }

    /**
     * Récupérer les statistiques des bonus
     */
    private function getBonusStatistics($period)
    {
        $bonusStats = [];

        // Vérifier quelles colonnes existent dans la table bonuses
        $hasTypeBonusColumn = Schema::hasColumn('bonuses', 'type_bonus');
        $hasDetailedColumns = Schema::hasColumn('bonuses', 'montant_direct') &&
                            Schema::hasColumn('bonuses', 'montant_indirect') &&
                            Schema::hasColumn('bonuses', 'montant_leadership');

        if ($hasDetailedColumns) {
            // Utiliser les nouvelles colonnes détaillées
            $stats = Bonus::where('period', $period)
                ->selectRaw('
                    SUM(montant_direct) as total_direct,
                    SUM(montant_indirect) as total_indirect,
                    SUM(montant_leadership) as total_leadership,
                    SUM(COALESCE(montant_total, montant)) as total_all,
                    COUNT(DISTINCT distributeur_id) as beneficiaires,
                    AVG(COALESCE(montant_total, montant)) as moyenne
                ')
                ->first();

            $bonusStats = [
                'total' => $stats->total_all ?? 0,
                'beneficiaires' => $stats->beneficiaires ?? 0,
                'moyenne' => $stats->moyenne ?? 0,
                'par_type' => [
                    ['type' => 'Bonus Direct', 'total' => $stats->total_direct ?? 0],
                    ['type' => 'Bonus Indirect', 'total' => $stats->total_indirect ?? 0],
                    ['type' => 'Bonus Leadership', 'total' => $stats->total_leadership ?? 0],
                ]
            ];
        } elseif ($hasTypeBonusColumn) {
            // Utiliser l'ancienne colonne type_bonus si elle existe
            $bonusStats = Bonus::where('period', $period)
                ->selectRaw('type_bonus, SUM(montant) as total')
                ->groupBy('type_bonus')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->type_bonus => $item->total];
                })
                ->toArray();
        } else {
            // Fallback : calculer les totaux sans types
            $stats = Bonus::where('period', $period)
                ->selectRaw('
                    COUNT(DISTINCT distributeur_id) as beneficiaires,
                    SUM(COALESCE(montant, bonus)) as total,
                    AVG(COALESCE(montant, bonus)) as moyenne
                ')
                ->first();

            $bonusStats = [
                'total' => $stats->total ?? 0,
                'beneficiaires' => $stats->beneficiaires ?? 0,
                'moyenne' => $stats->moyenne ?? 0,
                'par_type' => []
            ];
        }

        return $bonusStats;
    }

    /**
     * Récupérer les top performers
     */
    private function getTopPerformers($period)
    {
        return Distributeur::select(
                'distributeurs.*',
                'lc.etoiles',
                'lc.cumul_individuel',
                'lc.cumul_collectif'
            )
            ->join('level_currents as lc', 'distributeurs.id', '=', 'lc.distributeur_id')
            ->where('lc.period', $period)
            ->orderBy('lc.cumul_individuel', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Récupérer les alertes système
     */
    private function getSystemAlerts()
    {
        $alerts = collect();

        // Alerte si trop de demandes en attente
        if (ModificationRequest::pending()->count() > 10) {
            $alerts->push([
                'type' => 'warning',
                'title' => 'Demandes de modification en attente',
                'message' => ModificationRequest::pending()->count() . ' demandes nécessitent votre attention'
            ]);
        }

        // Alerte si période non clôturée - CORRECTION ICI
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');

        // Utiliser SystemPeriod pour vérifier si la période est fermée
        $systemPeriod = \App\Models\SystemPeriod::where('period', $lastMonth)->first();

        if ($systemPeriod && $systemPeriod->status !== \App\Models\SystemPeriod::STATUS_CLOSED) {
            $alerts->push([
                'type' => 'error',
                'title' => 'Période non clôturée',
                'message' => "La période {$lastMonth} doit être clôturée"
            ]);
        } elseif (!$systemPeriod && Carbon::now()->day > 10) {
            // Si on est après le 10 du mois et que la période n'existe pas
            $alerts->push([
                'type' => 'warning',
                'title' => 'Période manquante',
                'message' => "La période {$lastMonth} n'a pas été créée dans le système"
            ]);
        }

        return $alerts;
    }

    /**
     * Récupérer les statistiques du système
     */
    private function getSystemStats()
    {
        return [
            'total_distributeurs' => Distributeur::count(),
            'total_achats' => Achat::count(),
            'total_bonus' => Bonus::sum('montant'),
            'periods_count' => LevelCurrent::distinct('period')->count()
        ];
    }
}
