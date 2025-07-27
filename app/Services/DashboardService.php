<?php

namespace App\Services;

use App\Models\LevelCurrent;
use App\Models\Achat;
use App\Models\Distributeur;
use App\Models\Bonus;
use App\Models\SystemPeriod;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    protected SharedHostingCacheService $cache;
    protected SharedHostingPerformanceService $monitoring;

    public function __construct(
        SharedHostingCacheService $cache,
        SharedHostingPerformanceService $monitoring
    ) {
        $this->cache = $cache;
        $this->monitoring = $monitoring;
    }

    /**
     * Récupère toutes les données du dashboard principal
     */
    public function getDashboardData(?string $period = null): array
    {
        $period = $period ?? SystemPeriod::getCurrentPeriod()?->period ?? date('Y-m');

        return $this->cache->remember(
            CacheService::PREFIX_DASHBOARD . "main:{$period}",
            CacheService::TTL_SHORT,
            function() use ($period) {
                return [
                    'period' => $period,
                    'kpis' => $this->getKPIs($period),
                    'charts' => $this->getChartsData($period),
                    'recent_activity' => $this->getRecentActivity($period),
                    'alerts' => $this->getAlerts($period),
                    'comparisons' => $this->getPeriodComparisons($period)
                ];
            },
            ['dashboard', "period:{$period}"]
        );
    }

    /**
     * KPIs principaux
     */
    public function getKPIs(string $period): array
    {
        $currentStats = $this->getGlobalStats($period);
        $previousPeriod = Carbon::createFromFormat('Y-m', $period)->subMonth()->format('Y-m');
        $previousStats = $this->getGlobalStats($previousPeriod);

        return [
            'total_revenue' => [
                'value' => $currentStats['total_revenue'],
                'change' => $this->calculatePercentageChange(
                    $previousStats['total_revenue'],
                    $currentStats['total_revenue']
                ),
                'formatted' => number_format($currentStats['total_revenue'], 2) . ' €'
            ],
            'active_distributors' => [
                'value' => $currentStats['active_distributors'],
                'change' => $this->calculatePercentageChange(
                    $previousStats['active_distributors'],
                    $currentStats['active_distributors']
                ),
                'formatted' => number_format($currentStats['active_distributors'])
            ],
            'average_basket' => [
                'value' => $currentStats['average_basket'],
                'change' => $this->calculatePercentageChange(
                    $previousStats['average_basket'],
                    $currentStats['average_basket']
                ),
                'formatted' => number_format($currentStats['average_basket'], 2) . ' €'
            ],
            'total_points' => [
                'value' => $currentStats['total_points'],
                'change' => $this->calculatePercentageChange(
                    $previousStats['total_points'],
                    $currentStats['total_points']
                ),
                'formatted' => number_format($currentStats['total_points'])
            ]
        ];
    }

    /**
     * Statistiques globales
     * CORRECTION : Utiliser points_unitaire_achat * qt au lieu de pointvaleur
     */
    public function getGlobalStats(string $period): array
    {
        return $this->cache->remember(
            CacheService::PREFIX_STATS . "global:{$period}",
            CacheService::TTL_MEDIUM,
            function() use ($period) {
                $achats = Achat::where('period', $period);

                return [
                    'total_revenue' => $achats->sum('montant_total_ligne'),
                    // CORRECTION ICI : Utiliser points_unitaire_achat * qt
                    'total_points' => $achats->sum(DB::raw('points_unitaire_achat * qt')),
                    'total_orders' => $achats->count(),
                    'active_distributors' => $achats->distinct('distributeur_id')->count(),
                    'average_basket' => $achats->avg('montant_total_ligne') ?? 0,
                    'new_distributors' => Distributeur::whereYear('created_at', substr($period, 0, 4))
                                                    ->whereMonth('created_at', substr($period, 5, 2))
                                                    ->count()
                ];
            },
            ['stats', "period:{$period}"]
        );
    }

    /**
     * Données pour les graphiques
     */
    protected function getChartsData(string $period): array
    {
        return [
            'sales_evolution' => $this->getSalesEvolution($period),
            'grade_distribution' => $this->getGradeDistribution($period),
            'top_products' => $this->getTopProducts($period),
            'geographic_distribution' => $this->getGeographicDistribution($period),
            'hourly_activity' => $this->monitoring->collectMetrics($period)['business']['purchase_velocity'] ?? []
        ];
    }

    /**
     * Evolution des ventes sur 12 mois
     * CORRECTION : Utiliser points_unitaire_achat * qt
     */
    protected function getSalesEvolution(string $period): array
    {
        $data = [];
        $current = Carbon::createFromFormat('Y-m', $period);

        for ($i = 11; $i >= 0; $i--) {
            $monthPeriod = $current->copy()->subMonths($i)->format('Y-m');

            $stats = Achat::where('period', $monthPeriod)
                        ->selectRaw('
                            COUNT(*) as orders,
                            SUM(montant_total_ligne) as revenue,
                            SUM(points_unitaire_achat * qt) as points
                        ')
                        ->first();

            $data[] = [
                'period' => $monthPeriod,
                'month' => Carbon::parse($monthPeriod)->format('M Y'),
                'orders' => $stats->orders ?? 0,
                'revenue' => $stats->revenue ?? 0,
                'points' => $stats->points ?? 0
            ];
        }

        return $data;
    }

    /**
     * Distribution des grades
     */
    protected function getGradeDistribution(string $period): array
    {
        return LevelCurrent::where('period', $period)
                         ->join('distributeurs', 'level_currents.distributeur_id', '=', 'distributeurs.id')
                         ->groupBy('level_currents.etoiles')
                         ->selectRaw('level_currents.etoiles as grade, COUNT(*) as count, SUM(level_currents.new_cumul) as total_points')
                         ->orderBy('grade')
                         ->get()
                         ->map(function($item) {
                             return [
                                 'grade' => $item->grade,
                                 'label' => "Grade {$item->grade} ⭐",
                                 'count' => $item->count,
                                 'total_points' => $item->total_points
                             ];
                         })
                         ->toArray();
    }

    /**
     * Top produits
     */
    protected function getTopProducts(string $period): array
    {
        return Achat::where('period', $period)
                   ->join('products', 'achats.products_id', '=', 'products.id')
                   ->groupBy('products.id', 'products.nom_produit')
                   ->selectRaw('
                       products.id,
                       products.nom_produit,
                       COUNT(*) as count,
                       SUM(achats.qt) as quantity,
                       SUM(achats.montant_total_ligne) as revenue,
                       SUM(achats.points_unitaire_achat * achats.qt) as total_points
                   ')
                   ->orderByDesc('revenue')
                   ->limit(10)
                   ->get()
                   ->toArray();
    }

    /**
     * Distribution géographique
     */
    protected function getGeographicDistribution(string $period): array
    {
        // Adapter selon votre structure de données géographiques
        return [];
    }

    /**
     * Activité récente
     */
    protected function getRecentActivity(string $period): array
    {
        return [
            'recent_orders' => $this->getRecentOrders($period, 10),
            'recent_advancements' => $this->getRecentAdvancements($period, 10),
            'recent_registrations' => $this->getRecentRegistrations(10)
        ];
    }

    /**
     * Commandes récentes
     */
    protected function getRecentOrders(string $period, int $limit): array
    {
        return Achat::where('period', $period)
                   ->with(['distributeur', 'product'])
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get()
                   ->map(function($achat) {
                       return [
                           'id' => $achat->id,
                           'distributeur' => $achat->distributeur->nom_distributeur . ' ' . $achat->distributeur->pnom_distributeur,
                           'product' => $achat->product->nom_produit ?? 'N/A',
                           'amount' => $achat->montant_total_ligne,
                           'points' => $achat->points_unitaire_achat * $achat->qt,
                           'created_at' => $achat->created_at
                       ];
                   })
                   ->toArray();
    }

    /**
     * Avancements récents
     */
    protected function getRecentAdvancements(string $period, int $limit): array
    {
        return DB::table('avancement_history')
                ->where('period', $period)
                ->whereColumn('ancien_grade', '<', 'nouveau_grade')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($item) {
                    return [
                        'distributeur_id' => $item->distributeur_id,
                        'ancien_grade' => $item->ancien_grade,
                        'nouveau_grade' => $item->nouveau_grade,
                        'type_calcul' => $item->type_calcul,
                        'date_avancement' => $item->date_avancement,
                        'progression' => $item->nouveau_grade - $item->ancien_grade
                    ];
                })
                ->toArray();
    }

    /**
     * Inscriptions récentes
     */
    protected function getRecentRegistrations(int $limit): array
    {
        return Distributeur::with('parent')
                          ->orderBy('created_at', 'desc')
                          ->limit($limit)
                          ->get()
                          ->map(function($dist) {
                              return [
                                  'id' => $dist->id,
                                  'matricule' => $dist->distributeur_id,
                                  'name' => $dist->nom_distributeur . ' ' . $dist->pnom_distributeur,
                                  'parrain' => $dist->parent ? $dist->parent->nom_distributeur . ' ' . $dist->parent->pnom_distributeur : 'N/A',
                                  'created_at' => $dist->created_at
                              ];
                          })
                          ->toArray();
    }

    /**
     * Alertes système
     */
    protected function getAlerts(string $period): array
    {
        $alerts = [];

        // Vérifier les distributeurs sans grade
        $noGradeCount = Distributeur::whereDoesntHave('levelCurrent', function($q) use ($period) {
            $q->where('period', $period);
        })->count();

        if ($noGradeCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Distributeurs sans grade',
                'message' => "{$noGradeCount} distributeurs n'ont pas de grade pour la période {$period}"
            ];
        }

        // Vérifier les achats non validés
        $unvalidatedPurchases = Achat::where('period', $period)
                                    ->where('online', 0)
                                    ->count();

        if ($unvalidatedPurchases > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Achats hors ligne',
                'message' => "{$unvalidatedPurchases} achats sont marqués comme hors ligne"
            ];
        }

        return $alerts;
    }

    /**
     * Comparaisons entre périodes
     */
    protected function getPeriodComparisons(string $period): array
    {
        $previousPeriod = Carbon::createFromFormat('Y-m', $period)->subMonth()->format('Y-m');

        return [
            'current_period' => $period,
            'previous_period' => $previousPeriod,
            'current_stats' => $this->getGlobalStats($period),
            'previous_stats' => $this->getGlobalStats($previousPeriod)
        ];
    }

    /**
     * Top performers
     */
    public function getTopPerformers(string $period, int $limit = 10): array
    {
        return $this->cache->remember(
            CacheService::PREFIX_STATS . "top_performers:{$period}:{$limit}",
            CacheService::TTL_MEDIUM,
            function() use ($period, $limit) {
                return LevelCurrent::where('period', $period)
                                 ->with('distributeur')
                                 ->orderBy('new_cumul', 'desc')
                                 ->limit($limit)
                                 ->get()
                                 ->map(function($level) {
                                     return [
                                         'rank' => 0, // Will be set after
                                         'matricule' => $level->distributeur->distributeur_id,
                                         'name' => $level->distributeur->nom_distributeur . ' ' . $level->distributeur->pnom_distributeur,
                                         'grade' => $level->etoiles,
                                         'points' => $level->new_cumul,
                                         'team_points' => $level->cumul_collectif
                                     ];
                                 })
                                 ->values()
                                 ->map(function($item, $index) {
                                     $item['rank'] = $index + 1;
                                     return $item;
                                 })
                                 ->toArray();
            },
            ['stats', "period:{$period}"]
        );
    }

    /**
     * Calcule le pourcentage de changement
     */
    protected function calculatePercentageChange($oldValue, $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }
}
