<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\Bonus;
use App\Models\SystemPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DistributorBonusController extends Controller
{
    /**
     * Affiche la liste des bonus
     */
    public function index(Request $request)
    {
        $distributeur = Auth::user()->distributeur;

        if (!$distributeur) {
            return redirect()->route('distributor.dashboard')
                ->with('error', 'Profil distributeur non trouvé.');
        }

        // Paramètres de filtrage
        $period = $request->get('period');
        $typeBonus = $request->get('type');
        $status = $request->get('status');

        // Construire la requête
        $query = Bonus::where('distributeur_id', $distributeur->id)
            ->with(['bonus_type']);

        // Appliquer les filtres
        if ($period) {
            $query->where('period', $period);
        }

        if ($typeBonus) {
            $query->where('type_bonus', $typeBonus);
        }

        if ($status) {
            $query->where('status', $status);
        }

        // Ordre et pagination
        $bonuses = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Statistiques globales
        $stats = $this->getBonusStats($distributeur);

        // Périodes disponibles pour le filtre
        $availablePeriods = Bonus::where('distributeur_id', $distributeur->id)
            ->distinct('period')
            ->orderBy('period', 'desc')
            ->pluck('period');

        // Statistiques par type pour la période actuelle
        $currentPeriod = SystemPeriod::getCurrentPeriod();
        $bonusByType = $this->getBonusByType($distributeur, $currentPeriod->period);

        return view('distributor.bonus.index', compact(
            'bonuses',
            'stats',
            'availablePeriods',
            'bonusByType',
            'period',
            'typeBonus',
            'status',
            'currentPeriod'
        ));
    }

    /**
     * Affiche les détails d'un bonus
     */
    public function show($id)
    {
        $distributeur = Auth::user()->distributeur;
        $bonus = Bonus::where('distributeur_id', $distributeur->id)
            ->with(['source_distributeur', 'bonus_type'])
            ->findOrFail($id);

        // Détails du calcul si disponibles
        $calculationDetails = $this->getCalculationDetails($bonus);

        // Bonus similaires
        $similarBonuses = Bonus::where('distributeur_id', $distributeur->id)
            ->where('type_bonus', $bonus->type_bonus)
            ->where('period', $bonus->period)
            ->where('id', '!=', $bonus->id)
            ->limit(5)
            ->get();

        return view('distributor.bonus.show', compact(
            'bonus',
            'calculationDetails',
            'similarBonuses'
        ));
    }

    /**
     * Affiche le récapitulatif mensuel
     */
    public function monthly(Request $request)
    {
        $distributeur = Auth::user()->distributeur;
        $period = $request->get('period', SystemPeriod::getCurrentPeriod()->period);

        // Récupérer tous les bonus du mois
        $monthlyBonuses = Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->get();

        // Grouper par type
        $bonusesByType = $monthlyBonuses->groupBy('type_bonus')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('montant'),
                    'validated' => $group->where('status', 'validated')->sum('montant'),
                    'paid' => $group->where('status', 'paid')->sum('montant'),
                    'details' => $group
                ];
            });

        // Résumé global
        $summary = [
            'total_amount' => $monthlyBonuses->sum('montant'),
            'validated_amount' => $monthlyBonuses->where('status', 'validated')->sum('montant'),
            'paid_amount' => $monthlyBonuses->where('status', 'paid')->sum('montant'),
            'pending_amount' => $monthlyBonuses->where('status', 'pending')->sum('montant'),
            'count' => $monthlyBonuses->count()
        ];

        // Évolution par jour
        $dailyEvolution = $this->getDailyEvolution($distributeur, $period);

        // Comparaison avec le mois précédent
        $previousPeriod = Carbon::parse($period . '-01')->subMonth()->format('Y-m');
        $comparison = $this->getMonthComparison($distributeur, $period, $previousPeriod);

        return view('distributor.bonus.monthly', compact(
            'period',
            'bonusesByType',
            'summary',
            'dailyEvolution',
            'comparison'
        ));
    }

    /**
     * Affiche l'historique annuel
     */
    public function yearly(Request $request)
    {
        $distributeur = Auth::user()->distributeur;
        $year = $request->get('year', date('Y'));

        // Récupérer les bonus de l'année
        $yearlyBonuses = Bonus::where('distributeur_id', $distributeur->id)
            ->whereYear('created_at', $year)
            ->get();

        // Grouper par mois
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $period = $year . '-' . $monthStr;

            $monthBonuses = $yearlyBonuses->filter(function ($bonus) use ($period) {
                return $bonus->period == $period;
            });

            $monthlyData[] = [
                'month' => $month,
                'period' => $period,
                'total' => $monthBonuses->sum('montant'),
                'count' => $monthBonuses->count(),
                'by_type' => $monthBonuses->groupBy('type_bonus')->map->sum('montant')
            ];
        }

        // Statistiques annuelles
        $yearStats = [
            'total_amount' => $yearlyBonuses->sum('montant'),
            'total_count' => $yearlyBonuses->count(),
            'average_monthly' => $yearlyBonuses->sum('montant') / 12,
            'best_month' => collect($monthlyData)->sortByDesc('total')->first(),
            'by_type' => $yearlyBonuses->groupBy('type_bonus')->map->sum('montant')
        ];

        // Comparaison avec l'année précédente
        $previousYear = $year - 1;
        $previousYearTotal = Bonus::where('distributeur_id', $distributeur->id)
            ->whereYear('created_at', $previousYear)
            ->sum('montant');

        $yearComparison = [
            'amount_diff' => $yearStats['total_amount'] - $previousYearTotal,
            'percentage_diff' => $previousYearTotal > 0
                ? (($yearStats['total_amount'] - $previousYearTotal) / $previousYearTotal) * 100
                : 0
        ];

        return view('distributor.bonus.yearly', compact(
            'year',
            'monthlyData',
            'yearStats',
            'yearComparison'
        ));
    }

    /**
     * Export des bonus
     */
    public function export(Request $request)
    {
        $distributeur = Auth::user()->distributeur;

        $validated = $request->validate([
            'format' => 'required|in:csv,excel,pdf',
            'period_start' => 'nullable|date_format:Y-m',
            'period_end' => 'nullable|date_format:Y-m|after_or_equal:period_start',
            'type' => 'nullable|string'
        ]);

        // Construire la requête
        $query = Bonus::where('distributeur_id', $distributeur->id);

        if ($validated['period_start'] ?? null) {
            $query->where('period', '>=', $validated['period_start']);
        }

        if ($validated['period_end'] ?? null) {
            $query->where('period', '<=', $validated['period_end']);
        }

        if ($validated['type'] ?? null) {
            $query->where('type_bonus', $validated['type']);
        }

        $bonuses = $query->orderBy('period', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Générer l'export selon le format
        switch ($validated['format']) {
            case 'csv':
                return $this->exportCsv($bonuses);
            case 'pdf':
                return $this->exportPdf($bonuses);
            default:
                return $this->exportExcel($bonuses);
        }
    }

    /**
     * Obtient les statistiques globales des bonus
     */
    protected function getBonusStats($distributeur): array
    {
        $allTime = Bonus::where('distributeur_id', $distributeur->id)
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(montant) as total_amount,
                SUM(CASE WHEN status = "paid" THEN montant ELSE 0 END) as paid_amount,
                SUM(CASE WHEN status = "validated" THEN montant ELSE 0 END) as validated_amount,
                SUM(CASE WHEN status = "pending" THEN montant ELSE 0 END) as pending_amount
            ')
            ->first();

        $currentPeriod = SystemPeriod::getCurrentPeriod();
        $thisMonth = Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $currentPeriod->period)
            ->sum('montant');

        $lastMonth = Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', Carbon::now()->subMonth()->format('Y-m'))
            ->sum('montant');

        return [
            'all_time_total' => $allTime->total_amount ?? 0,
            'all_time_count' => $allTime->total_count ?? 0,
            'paid_total' => $allTime->paid_amount ?? 0,
            'validated_total' => $allTime->validated_amount ?? 0,
            'pending_total' => $allTime->pending_amount ?? 0,
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'monthly_average' => $this->getMonthlyAverage($distributeur)
        ];
    }

    /**
     * Obtient la moyenne mensuelle des bonus
     */
    protected function getMonthlyAverage($distributeur): float
    {
        $firstBonus = Bonus::where('distributeur_id', $distributeur->id)
            ->orderBy('created_at')
            ->first();

        if (!$firstBonus) {
            return 0;
        }

        $monthsSinceFirst = Carbon::parse($firstBonus->created_at)->diffInMonths(now()) + 1;
        $total = Bonus::where('distributeur_id', $distributeur->id)->sum('montant');

        return $monthsSinceFirst > 0 ? $total / $monthsSinceFirst : 0;
    }

    /**
     * Obtient les bonus par type pour une période
     */
    protected function getBonusByType($distributeur, $period): array
    {
        return Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->groupBy('type_bonus')
            ->selectRaw('type_bonus, COUNT(*) as count, SUM(montant) as total')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type_bonus,
                    'label' => $this->getBonusTypeLabel($item->type_bonus),
                    'count' => $item->count,
                    'total' => $item->total
                ];
            })
            ->toArray();
    }

    /**
     * Obtient les détails de calcul d'un bonus
     */
    protected function getCalculationDetails(Bonus $bonus): array
    {
        $details = [];

        switch ($bonus->type_bonus) {
            case 'direct':
                $details['description'] = 'Bonus sur vente directe';
                $details['taux'] = $bonus->taux . '%';
                $details['base_calcul'] = 'Points de vente du filleul direct';
                break;

            case 'indirect':
                $details['description'] = 'Bonus sur vente indirecte';
                $details['taux'] = $bonus->taux . '%';
                $details['base_calcul'] = 'Points de vente des filleuls indirects';
                $details['niveau'] = $bonus->niveau ?? 'N/A';
                break;

            case 'leadership':
                $details['description'] = 'Bonus de leadership';
                $details['condition'] = 'Basé sur la performance globale de l\'équipe';
                break;

            case 'rank':
                $details['description'] = 'Bonus de grade';
                $details['grade'] = $bonus->grade ?? 'N/A';
                break;
        }

        // Si des détails JSON sont stockés
        if ($bonus->details) {
            $details['details_supplementaires'] = $bonus->details;
        }

        return $details;
    }

    /**
     * Obtient l'évolution journalière des bonus
     */
    protected function getDailyEvolution($distributeur, $period): array
    {
        $startDate = Carbon::parse($period . '-01');
        $endDate = $startDate->copy()->endOfMonth();

        $bonuses = Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $dailyData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dayBonuses = $bonuses->filter(function ($bonus) use ($currentDate) {
                return $bonus->created_at->format('Y-m-d') == $currentDate->format('Y-m-d');
            });

            $dailyData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day' => $currentDate->day,
                'amount' => $dayBonuses->sum('montant'),
                'count' => $dayBonuses->count()
            ];

            $currentDate->addDay();
        }

        return $dailyData;
    }

    /**
     * Compare deux mois
     */
    protected function getMonthComparison($distributeur, $currentPeriod, $previousPeriod): array
    {
        $current = Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $currentPeriod)
            ->selectRaw('COUNT(*) as count, SUM(montant) as total')
            ->first();

        $previous = Bonus::where('distributeur_id', $distributeur->id)
            ->where('period', $previousPeriod)
            ->selectRaw('COUNT(*) as count, SUM(montant) as total')
            ->first();

        $amountDiff = ($current->total ?? 0) - ($previous->total ?? 0);
        $countDiff = ($current->count ?? 0) - ($previous->count ?? 0);

        return [
            'amount_diff' => $amountDiff,
            'amount_percentage' => $previous->total > 0
                ? ($amountDiff / $previous->total) * 100
                : 0,
            'count_diff' => $countDiff,
            'count_percentage' => $previous->count > 0
                ? ($countDiff / $previous->count) * 100
                : 0
        ];
    }

    /**
     * Obtient le label d'un type de bonus
     */
    protected function getBonusTypeLabel($type): string
    {
        return match($type) {
            'direct' => 'Bonus direct',
            'indirect' => 'Bonus indirect',
            'leadership' => 'Bonus leadership',
            'rank' => 'Bonus de grade',
            'special' => 'Bonus spécial',
            default => ucfirst($type)
        };
    }

    /**
     * Export CSV des bonus
     */
    protected function exportCsv($bonuses)
    {
        $filename = 'bonus_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($bonuses) {
            $file = fopen('php://output', 'w');

            // En-têtes
            fputcsv($file, [
                'Période',
                'Date',
                'Type',
                'Montant',
                'Statut',
                'Source',
                'Description'
            ]);

            // Données
            foreach ($bonuses as $bonus) {
                fputcsv($file, [
                    $bonus->period,
                    $bonus->created_at->format('Y-m-d'),
                    $this->getBonusTypeLabel($bonus->type_bonus),
                    number_format($bonus->montant, 2, ',', ' ') . ' €',
                    ucfirst($bonus->status),
                    $bonus->source_distributeur_id ?? 'Système',
                    $bonus->description ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
