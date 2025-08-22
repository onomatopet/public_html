<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvancementHistory;
use App\Models\SystemPeriod;
use App\Models\Distributeur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class AdvancementHistoryController extends Controller
{
    /**
     * Affiche l'historique des avancements
     */
    public function index(Request $request)
    {
        $period = $request->get('period', date('Y-m'));

        // Debug : vérifier les données
        Log::info('AdvancementHistory index called', [
            'period' => $period,
            'total_in_db' => AvancementHistory::where('period', $period)->count()
        ]);

        // Périodes disponibles
        $periods = AvancementHistory::select('period')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period');

        // Si aucune période n'est trouvée, vérifier toutes les périodes
        if ($periods->isEmpty()) {
            $periods = AvancementHistory::select('period')
                ->distinct()
                ->orderBy('period', 'desc')
                ->pluck('period');

            // Si toujours vide, créer une période par défaut
            if ($periods->isEmpty()) {
                $periods = collect([$period]);
            }
        }

        // Requête de base
        $query = AvancementHistory::with('distributeur')
            ->where('period', $period);

        // Filtres
        if ($request->filled('grade')) {
            $query->where('nouveau_grade', $request->grade);
        }

        if ($request->filled('type_calcul')) {
            $query->where('type_calcul', $request->type_calcul);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('distributeur', function($q) use ($search) {
                $q->where('distributeur_id', 'like', "%{$search}%")
                  ->orWhere('nom_distributeur', 'like', "%{$search}%")
                  ->orWhere('pnom_distributeur', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortField = $request->get('sort', 'date_avancement');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $avancements = $query->paginate(20)->withQueryString();

        // Statistiques
        $stats = $this->getStatistics($period);

        return view('admin.avancements.index', compact(
            'avancements',
            'periods',
            'period',
            'stats'
        ));
    }

    /**
     * Affiche les détails d'un avancement
     */
    public function show($id)
    {
        $avancement = AvancementHistory::with('distributeur')->findOrFail($id);

        // Décoder les détails JSON
        $details = is_string($avancement->details)
            ? json_decode($avancement->details, true)
            : $avancement->details;

        return view('admin.avancements.show', compact('avancement', 'details'));
    }

    /**
     * Exporte les avancements d'une période
     */
    public function export(Request $request)
    {
        $period = $request->get('period', date('Y-m'));
        $format = $request->get('format', 'csv');

        $avancements = AvancementHistory::with('distributeur')
            ->where('period', $period)
            ->orderBy('nouveau_grade', 'desc')
            ->orderBy('date_avancement', 'asc')
            ->get();

        if ($format === 'pdf') {
            return $this->exportPdf($avancements, $period);
        }

        return $this->exportCsv($avancements, $period);
    }

    /**
     * Affiche les statistiques par période
     */
    public function statistics(Request $request)
    {
        $year = $request->get('year', date('Y'));

        // Statistiques par mois
        $monthlyStats = AvancementHistory::selectRaw('
                period,
                COUNT(*) as total_avancements,
                COUNT(DISTINCT distributeur_id) as distributeurs_uniques,
                SUM(CASE WHEN nouveau_grade > ancien_grade THEN 1 ELSE 0 END) as promotions,
                SUM(CASE WHEN nouveau_grade < ancien_grade THEN 1 ELSE 0 END) as retrogradations,
                MAX(nouveau_grade) as plus_haut_grade
            ')
            ->whereYear(DB::raw('STR_TO_DATE(period, "%Y-%m")'), $year)
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Statistiques par grade
        $gradeStats = AvancementHistory::selectRaw('
                nouveau_grade,
                COUNT(*) as nombre,
                period
            ')
            ->whereYear(DB::raw('STR_TO_DATE(period, "%Y-%m")'), $year)
            ->groupBy('nouveau_grade', 'period')
            ->orderBy('period')
            ->orderBy('nouveau_grade')
            ->get();

        // Années disponibles
        $years = AvancementHistory::selectRaw('YEAR(STR_TO_DATE(period, "%Y-%m")) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return view('admin.avancements.statistics', compact(
            'monthlyStats',
            'gradeStats',
            'year',
            'years'
        ));
    }

    /**
     * Obtient les statistiques pour une période
     */
    private function getStatistics($period)
    {
        return [
            'total' => AvancementHistory::where('period', $period)->count(),
            'promotions' => AvancementHistory::where('period', $period)
                ->whereColumn('nouveau_grade', '>', 'ancien_grade')
                ->count(),
            'retrogradations' => AvancementHistory::where('period', $period)
                ->whereColumn('nouveau_grade', '<', 'ancien_grade')
                ->count(),
            'par_grade' => AvancementHistory::where('period', $period)
                ->groupBy('nouveau_grade')
                ->selectRaw('nouveau_grade, COUNT(*) as count')
                ->orderBy('nouveau_grade', 'desc')
                ->get(),
            'top_distributeurs' => AvancementHistory::where('period', $period)
                ->where('nouveau_grade', '>=', 7)
                ->with('distributeur')
                ->orderBy('nouveau_grade', 'desc')
                ->limit(10)
                ->get()
        ];
    }

    /**
     * Exporte en CSV
     */
    private function exportCsv($avancements, $period)
    {
        $filename = "avancements_{$period}_" . date('YmdHis') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\""
        ];

        $callback = function() use ($avancements) {
            $file = fopen('php://output', 'w');

            // En-têtes
            fputcsv($file, [
                'Matricule',
                'Nom',
                'Prénom',
                'Ancien Grade',
                'Nouveau Grade',
                'Type Calcul',
                'Date Avancement',
                'Cumul Individuel',
                'Cumul Collectif'
            ]);

            // Données
            foreach ($avancements as $avancement) {
                $details = json_decode($avancement->details, true);

                fputcsv($file, [
                    $avancement->distributeur->distributeur_id ?? 'N/A',
                    $avancement->distributeur->nom_distributeur ?? 'N/A',
                    $avancement->distributeur->pnom_distributeur ?? 'N/A',
                    $avancement->ancien_grade,
                    $avancement->nouveau_grade,
                    $avancement->type_calcul,
                    $avancement->date_avancement,
                    $details['cumul_individuel'] ?? '',
                    $details['cumul_collectif'] ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Exporte en PDF
     */
    private function exportPdf($avancements, $period)
    {
        $stats = $this->getStatistics($period);

        $pdf = PDF::loadView('admin.avancements.pdf', [
            'avancements' => $avancements,
            'period' => $period,
            'stats' => $stats
        ]);

        return $pdf->download("avancements_{$period}_" . date('YmdHis') . '.pdf');
    }
}
