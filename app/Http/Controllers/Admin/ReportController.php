<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributeur;
use App\Models\Achat;
use App\Models\Bonus;
use App\Models\LevelCurrent;
use App\Models\SystemPeriod;
use App\Exports\AchatsExport;
use App\Exports\BonusExport;
use App\Exports\NetworkExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Affiche la page principale des rapports
     */
    public function index()
    {
        $currentPeriod = SystemPeriod::getCurrentPeriod();

        // Statistiques rapides pour la page d'accueil
        $stats = Cache::remember('reports.dashboard.stats', 3600, function () use ($currentPeriod) {
            return [
                'total_sales' => Achat::where('period', $currentPeriod->period)
                    ->where('status', 'validé')
                    ->sum('montant_total_ligne'),

                'total_commissions' => Bonus::where('period', $currentPeriod->period)
                    ->where('status', 'validé')
                    ->sum('montant'),

                'active_distributors' => LevelCurrent::where('period', $currentPeriod->period)
                    ->where('new_cumul', '>', 0)  // Utiliser new_cumul au lieu de pv
                    ->count(),

                'new_distributors' => Distributeur::whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year)
                    ->count(),
            ];
        });

        // Récupérer les périodes disponibles
        $availablePeriods = SystemPeriod::orderBy('period', 'desc')
            ->take(12)
            ->pluck('period');

        return view('admin.reports.index', compact('stats', 'availablePeriods', 'currentPeriod'));
    }

    /**
     * Rapport des ventes
     */
    public function sales(Request $request)
    {
        $request->validate([
            'period_start' => 'nullable|date_format:Y-m',
            'period_end' => 'nullable|date_format:Y-m|after_or_equal:period_start',
            'format' => 'nullable|in:pdf,excel,csv'
        ]);

        $periodStart = $request->period_start ?? Carbon::now()->startOfMonth()->format('Y-m');
        $periodEnd = $request->period_end ?? $periodStart;

        // Données du rapport
        $salesData = $this->getSalesReportData($periodStart, $periodEnd);

        // Export si demandé
        if ($request->format) {
            return $this->exportSalesReport($salesData, $request->format, $periodStart, $periodEnd);
        }

        return view('admin.reports.sales', compact('salesData', 'periodStart', 'periodEnd'));
    }

    /**
     * Rapport des commissions
     */
    public function commissions(Request $request)
    {
        $request->validate([
            'period' => 'nullable|date_format:Y-m',
            'grade' => 'nullable|integer|min:0|max:5',
            'status' => 'nullable|in:pending,validated,paid',
            'format' => 'nullable|in:pdf,excel,csv'
        ]);

        $period = $request->period ?? SystemPeriod::getCurrentPeriod()->period;

        // Données du rapport
        $commissionsData = $this->getCommissionsReportData($period, $request->grade, $request->status);

        // Export si demandé
        if ($request->format) {
            return $this->exportCommissionsReport($commissionsData, $request->format, $period);
        }

        return view('admin.reports.commissions', compact('commissionsData', 'period'));
    }

    /**
     * Rapport de croissance du réseau
     */
    public function networkGrowth(Request $request)
    {
        $request->validate([
            'months' => 'nullable|integer|min:1|max:24',
            'region' => 'nullable|string',
            'format' => 'nullable|in:pdf,excel,csv'
        ]);

        $months = $request->months ?? 12;

        // Données du rapport
        $growthData = $this->getNetworkGrowthData($months, $request->region);

        // Export si demandé
        if ($request->format) {
            return $this->exportNetworkGrowthReport($growthData, $request->format, $months);
        }

        return view('admin.reports.network-growth', compact('growthData', 'months'));
    }

    /**
     * Export de rapport personnalisé
     */
    public function export(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:sales,commissions,network,performance,custom',
            'period_start' => 'required|date_format:Y-m',
            'period_end' => 'required|date_format:Y-m|after_or_equal:period_start',
            'format' => 'required|in:pdf,excel,csv',
            'options' => 'nullable|array'
        ]);

        $data = $this->getCustomReportData(
            $request->report_type,
            $request->period_start,
            $request->period_end,
            $request->options ?? []
        );

        switch ($request->format) {
            case 'pdf':
                return $this->generatePdfReport($data, $request->report_type);
            case 'excel':
                return $this->generateExcelReport($data, $request->report_type);
            case 'csv':
                return $this->generateCsvReport($data, $request->report_type);
        }
    }

    /**
     * Récupère les données du rapport des ventes
     */
    protected function getSalesReportData($periodStart, $periodEnd)
    {
        return Cache::remember("reports.sales.{$periodStart}.{$periodEnd}", 3600, function () use ($periodStart, $periodEnd) {
            // Ventes par produit
            $salesByProduct = DB::table('achats')
                ->join('products', 'achats.products_id', '=', 'products.id')
                ->whereBetween('achats.period', [$periodStart, $periodEnd])
                ->where('achats.status', 'validé')
                ->select(
                    'products.name',
                    'products.code',
                    DB::raw('COUNT(*) as quantity'),
                    DB::raw('SUM(achats.qt) as total_quantity'),
                    DB::raw('SUM(achats.montant_total_ligne) as total_amount'),
                    DB::raw('SUM(achats.points_unitaire_achat * achats.qt) as total_points')
                )
                ->groupBy('products.id', 'products.name', 'products.code')
                ->orderByDesc('total_amount')
                ->get();

            // Ventes par distributeur (Top 20)
            $salesByDistributor = DB::table('achats')
                ->join('distributeurs', 'achats.distributeur_id', '=', 'distributeurs.id')
                ->whereBetween('achats.period', [$periodStart, $periodEnd])
                ->where('achats.status', 'validé')
                ->select(
                    'distributeurs.distributeur_id',
                    'distributeurs.nom_distributeur',  // Corrigé
                    'distributeurs.pnom_distributeur', // Corrigé
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(achats.montant_total_ligne) as total_amount'),
                    DB::raw('SUM(achats.points_unitaire_achat * achats.qt) as total_points')
                )
                ->groupBy('distributeurs.id', 'distributeurs.distributeur_id', 'distributeurs.nom_distributeur', 'distributeurs.pnom_distributeur')
                ->orderByDesc('total_amount')
                ->limit(20)
                ->get();

            // Évolution mensuelle
            $monthlyEvolution = DB::table('achats')
                ->whereBetween('period', [$periodStart, $periodEnd])
                ->where('status', 'validé')
                ->select(
                    'period',
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(montant_total_ligne) as total_amount'),
                    DB::raw('SUM(points_unitaire_achat * qt) as total_points')
                )
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            return [
                'sales_by_product' => $salesByProduct,
                'sales_by_distributor' => $salesByDistributor,
                'monthly_evolution' => $monthlyEvolution,
                'summary' => [
                    'total_orders' => $monthlyEvolution->sum('order_count'),
                    'total_amount' => $monthlyEvolution->sum('total_amount'),
                    'total_points' => $monthlyEvolution->sum('total_points'),
                    'average_order' => $monthlyEvolution->sum('order_count') > 0
                        ? $monthlyEvolution->sum('total_amount') / $monthlyEvolution->sum('order_count')
                        : 0,
                ]
            ];
        });
    }

    /**
     * Récupère les données du rapport des commissions
     */
    protected function getCommissionsReportData($period, $grade = null, $status = null)
    {
        return Cache::remember("reports.commissions.{$period}.{$grade}.{$status}", 3600, function () use ($period, $grade, $status) {
            $query = DB::table('bonuses')
                ->join('distributeurs', 'bonuses.distributeur_id', '=', 'distributeurs.id')
                ->where('bonuses.period', $period);

            if ($grade !== null) {
                $query->where('distributeurs.rang', $grade);
            }

            if ($status) {
                $query->where('bonuses.status', $status);
            }

            // Commissions par type (si les colonnes détaillées existent)
            $commissionsByType = collect();
            if (DB::getSchemaBuilder()->hasColumn('bonuses', 'bonus_direct')) {
                $commissionsByType = collect([
                    (object)['type_bonus' => 'Bonus Direct', 'count' => $query->clone()->count(), 'total_amount' => $query->clone()->sum('bonuses.bonus_direct'), 'avg_amount' => $query->clone()->avg('bonuses.bonus_direct')],
                    (object)['type_bonus' => 'Bonus Indirect', 'count' => $query->clone()->count(), 'total_amount' => $query->clone()->sum('bonuses.bonus_indirect'), 'avg_amount' => $query->clone()->avg('bonuses.bonus_indirect')],
                    (object)['type_bonus' => 'Bonus Leadership', 'count' => $query->clone()->count(), 'total_amount' => $query->clone()->sum('bonuses.bonus_leadership'), 'avg_amount' => $query->clone()->avg('bonuses.bonus_leadership')],
                ]);
            } elseif (DB::getSchemaBuilder()->hasColumn('bonuses', 'type_bonus')) {
                $commissionsByType = $query->clone()
                    ->select(
                        'bonuses.type_bonus',
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM(bonuses.montant) as total_amount'),
                        DB::raw('AVG(bonuses.montant) as avg_amount')
                    )
                    ->groupBy('bonuses.type_bonus')
                    ->get();
            } else {
                // Fallback si pas de colonnes de type
                $totalQuery = $query->clone();
                $commissionsByType = collect([
                    (object)[
                        'type_bonus' => 'Total',
                        'count' => $totalQuery->count(),
                        'total_amount' => $totalQuery->sum('bonuses.montant'),
                        'avg_amount' => $totalQuery->avg('bonuses.montant')
                    ]
                ]);
            }

            // Top 20 des bénéficiaires
            $topBeneficiaries = $query->clone()
                ->select(
                    'distributeurs.distributeur_id',
                    'distributeurs.nom_distributeur',  // Corrigé
                    'distributeurs.pnom_distributeur', // Corrigé
                    'distributeurs.rang',
                    DB::raw('COUNT(*) as bonus_count'),
                    DB::raw('SUM(bonuses.montant) as total_amount')
                )
                ->groupBy('distributeurs.id', 'distributeurs.distributeur_id', 'distributeurs.nom_distributeur', 'distributeurs.pnom_distributeur', 'distributeurs.rang')
                ->orderByDesc('total_amount')
                ->limit(20)
                ->get();

            // Statistiques par grade
            $statsByGrade = DB::table('bonuses')
                ->join('distributeurs', 'bonuses.distributeur_id', '=', 'distributeurs.id')
                ->where('bonuses.period', $period)
                ->select(
                    'distributeurs.rang',
                    DB::raw('COUNT(DISTINCT distributeurs.id) as distributor_count'),
                    DB::raw('COUNT(*) as bonus_count'),
                    DB::raw('SUM(bonuses.montant) as total_amount'),
                    DB::raw('AVG(bonuses.montant) as avg_amount')
                )
                ->groupBy('distributeurs.rang')
                ->orderBy('distributeurs.rang')
                ->get();

            return [
                'commissions_by_type' => $commissionsByType,
                'top_beneficiaries' => $topBeneficiaries,
                'stats_by_grade' => $statsByGrade,
                'summary' => [
                    'total_commissions' => $commissionsByType->sum('total_amount'),
                    'total_beneficiaries' => $topBeneficiaries->count(),
                    'average_commission' => $commissionsByType->avg('avg_amount') ?? 0,
                ]
            ];
        });
    }

    /**
     * Récupère les données de croissance du réseau
     */
    protected function getNetworkGrowthData($months, $region = null)
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        return Cache::remember("reports.network.{$months}.{$region}", 3600, function () use ($startDate, $region) {
            $query = DB::table('distributeurs')
                ->where('created_at', '>=', $startDate);

            if ($region) {
                $query->where('ville', $region);
            }

            // Évolution mensuelle des inscriptions
            $monthlyGrowth = $query->clone()
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as new_distributors')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Répartition par grade
            $gradeDistribution = $query->clone()
                ->select(
                    'rang',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('rang')
                ->orderBy('rang')
                ->get();

            // Top villes
            $topCities = $query->clone()
                ->select(
                    'ville',
                    DB::raw('COUNT(*) as count')
                )
                ->whereNotNull('ville')
                ->groupBy('ville')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            // Taux de rétention (distributeurs actifs)
            $retentionData = DB::table('distributeurs as d')
                ->join('level_currents as lc', 'd.id', '=', 'lc.distributeur_id')
                ->where('d.created_at', '>=', $startDate)
                ->where('lc.period', SystemPeriod::getCurrentPeriod()->period)
                ->where('lc.new_cumul', '>', 0)  // Utiliser new_cumul au lieu de pv
                ->select(
                    DB::raw('DATE_FORMAT(d.created_at, "%Y-%m") as cohort_month'),
                    DB::raw('COUNT(DISTINCT d.id) as active_count')
                )
                ->groupBy('cohort_month')
                ->get();

            return [
                'monthly_growth' => $monthlyGrowth,
                'grade_distribution' => $gradeDistribution,
                'top_cities' => $topCities,
                'retention_data' => $retentionData,
                'summary' => [
                    'total_new_distributors' => $monthlyGrowth->sum('new_distributors'),
                    'average_monthly_growth' => $monthlyGrowth->avg('new_distributors') ?? 0,
                    'retention_rate' => $this->calculateRetentionRate($monthlyGrowth, $retentionData),
                ]
            ];
        });
    }

    /**
     * Calcule le taux de rétention
     */
    protected function calculateRetentionRate($growth, $retention)
    {
        if ($growth->isEmpty()) {
            return 0;
        }

        $totalNew = $growth->sum('new_distributors');
        $totalActive = $retention->sum('active_count');

        return $totalNew > 0 ? round(($totalActive / $totalNew) * 100, 2) : 0;
    }

    /**
     * Récupère les données pour un rapport personnalisé
     */
    protected function getCustomReportData($type, $periodStart, $periodEnd, $options)
    {
        switch ($type) {
            case 'sales':
                return $this->getSalesReportData($periodStart, $periodEnd);

            case 'commissions':
                return $this->getCommissionsReportData($periodStart, $options['grade'] ?? null, $options['status'] ?? null);

            case 'network':
                $months = Carbon::parse($periodStart)->diffInMonths(Carbon::parse($periodEnd));
                return $this->getNetworkGrowthData($months, $options['region'] ?? null);

            case 'performance':
                return $this->getPerformanceReportData($periodStart, $periodEnd, $options);

            default:
                return [];
        }
    }

    /**
     * Génère un rapport PDF
     */
    protected function generatePdfReport($data, $type)
    {
        $pdf = PDF::loadView("admin.reports.pdf.{$type}", compact('data'));
        return $pdf->download("rapport-{$type}-" . now()->format('Y-m-d') . ".pdf");
    }

    /**
     * Génère un rapport Excel
     */
    protected function generateExcelReport($data, $type)
    {
        // Utiliser les classes d'export existantes avec les bons paramètres
        switch ($type) {
            case 'sales':
                // Utiliser AchatsExport avec les filtres appropriés
                $filters = [
                    'period' => $data['period'] ?? null,
                    'date_from' => $data['date_from'] ?? null,
                    'date_to' => $data['date_to'] ?? null,
                ];
                return Excel::download(new AchatsExport($filters), "rapport-ventes-" . now()->format('Y-m-d') . ".xlsx");

            case 'commissions':
                // Utiliser BonusExport avec les filtres appropriés
                $filters = [
                    'period' => $data['period'] ?? null,
                    'grade' => $data['grade'] ?? null,
                    'status' => $data['status'] ?? null,
                ];
                return Excel::download(new BonusExport($filters), "rapport-commissions-" . now()->format('Y-m-d') . ".xlsx");

            case 'network':
                // Pour le réseau, on pourrait utiliser NetworkExport si elle existe
                // ou créer un export personnalisé basé sur les données
                abort(400, 'Export Excel du réseau non disponible pour le moment');

            default:
                abort(400, 'Type de rapport non supporté pour l\'export Excel');
        }
    }

    /**
     * Génère un rapport CSV
     */
    protected function generateCsvReport($data, $type)
    {
        $filename = "rapport-{$type}-" . now()->format('Y-m-d') . ".csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data, $type) {
            $file = fopen('php://output', 'w');

            // En-têtes spécifiques selon le type
            switch ($type) {
                case 'sales':
                    fputcsv($file, ['Produit', 'Code', 'Quantité', 'Montant Total', 'Points Total']);
                    foreach ($data['sales_by_product'] as $product) {
                        fputcsv($file, [
                            $product->name,
                            $product->code,
                            $product->total_quantity,
                            $product->total_amount,
                            $product->total_points
                        ]);
                    }
                    break;

                case 'commissions':
                    fputcsv($file, ['Type Bonus', 'Nombre', 'Montant Total', 'Montant Moyen']);
                    foreach ($data['commissions_by_type'] as $commission) {
                        fputcsv($file, [
                            $commission->type_bonus,
                            $commission->count,
                            $commission->total_amount,
                            $commission->avg_amount
                        ]);
                    }
                    break;
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export du rapport des ventes
     */
    protected function exportSalesReport($data, $format, $periodStart, $periodEnd)
    {
        $filename = "rapport-ventes-{$periodStart}-{$periodEnd}";

        switch ($format) {
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.pdf.sales', compact('data', 'periodStart', 'periodEnd'));
                return $pdf->download("{$filename}.pdf");

            case 'excel':
                // Préparer les filtres pour AchatsExport
                $filters = [
                    'period' => $periodStart,
                    'date_from' => $periodStart . '-01',
                    'date_to' => $periodEnd . '-31'
                ];
                return Excel::download(new AchatsExport($filters), "{$filename}.xlsx");

            case 'csv':
                return $this->generateCsvReport($data, 'sales');
        }
    }

    /**
     * Export du rapport des commissions
     */
    protected function exportCommissionsReport($data, $format, $period)
    {
        $filename = "rapport-commissions-{$period}";

        switch ($format) {
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.pdf.commissions', compact('data', 'period'));
                return $pdf->download("{$filename}.pdf");

            case 'excel':
                // Préparer les filtres pour BonusExport
                $filters = [
                    'period' => $period,
                    'status' => $data['filters']['status'] ?? null,
                    'grade' => $data['filters']['grade'] ?? null
                ];
                return Excel::download(new BonusExport($filters), "{$filename}.xlsx");

            case 'csv':
                return $this->generateCsvReport($data, 'commissions');
        }
    }

    /**
     * Export du rapport de croissance du réseau
     */
    protected function exportNetworkGrowthReport($data, $format, $months)
    {
        $filename = "rapport-croissance-reseau-{$months}mois";

        switch ($format) {
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.pdf.network-growth', compact('data', 'months'));
                return $pdf->download("{$filename}.pdf");

            case 'excel':
                // Pour le moment, on génère un CSV car NetworkExport nécessite des paramètres spécifiques
                // On pourrait adapter NetworkExport ou créer une nouvelle classe d'export
                return $this->generateCsvReport($data, 'network');

            case 'csv':
                return $this->generateCsvReport($data, 'network');
        }
    }

    /**
     * Récupère les données de performance
     */
    protected function getPerformanceReportData($periodStart, $periodEnd, $options)
    {
        // Implémentation des métriques de performance
        return [
            'sales_performance' => $this->calculateSalesPerformance($periodStart, $periodEnd),
            'network_performance' => $this->calculateNetworkPerformance($periodStart, $periodEnd),
            'product_performance' => $this->calculateProductPerformance($periodStart, $periodEnd),
            'regional_performance' => $this->calculateRegionalPerformance($periodStart, $periodEnd)
        ];
    }

    /**
     * Calcule les performances de vente
     */
    protected function calculateSalesPerformance($periodStart, $periodEnd)
    {
        return DB::table('achats')
            ->whereBetween('period', [$periodStart, $periodEnd])
            ->where('status', 'validé')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(montant_total_ligne) as total_sales'),
                DB::raw('AVG(montant_total_ligne) as avg_order_value')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Calcule les performances du réseau
     */
    protected function calculateNetworkPerformance($periodStart, $periodEnd)
    {
        return DB::table('level_currents')
            ->whereBetween('period', [$periodStart, $periodEnd])
            ->select(
                'period',
                DB::raw('COUNT(DISTINCT distributeur_id) as active_distributors'),
                DB::raw('SUM(new_cumul) as total_pv'),  // Utiliser new_cumul au lieu de pv
                DB::raw('AVG(new_cumul) as avg_pv')     // Utiliser new_cumul au lieu de pv
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    /**
     * Calcule les performances des produits
     */
    protected function calculateProductPerformance($periodStart, $periodEnd)
    {
        return DB::table('achats')
            ->join('products', 'achats.products_id', '=', 'products.id')
            ->whereBetween('achats.period', [$periodStart, $periodEnd])
            ->where('achats.status', 'validé')
            ->select(
                'products.name',
                'products.code',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(achats.qt) as units_sold'),
                DB::raw('SUM(achats.montant_total_ligne) as revenue'),
                DB::raw('AVG(achats.montant_total_ligne) as avg_revenue_per_order')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();
    }

    /**
     * Calcule les performances régionales
     */
    protected function calculateRegionalPerformance($periodStart, $periodEnd)
    {
        return DB::table('achats')
            ->join('distributeurs', 'achats.distributeur_id', '=', 'distributeurs.id')
            ->whereBetween('achats.period', [$periodStart, $periodEnd])
            ->where('achats.status', 'validé')
            ->select(
                'distributeurs.ville',
                DB::raw('COUNT(DISTINCT distributeurs.id) as active_distributors'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(achats.montant_total_ligne) as total_sales'),
                DB::raw('AVG(achats.montant_total_ligne) as avg_order_value')
            )
            ->whereNotNull('distributeurs.ville')
            ->groupBy('distributeurs.ville')
            ->orderByDesc('total_sales')
            ->limit(15)
            ->get();
    }
}
