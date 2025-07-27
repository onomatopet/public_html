<?php
// app/Http/Controllers/Admin/BonusController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BonusCalculationService;
use App\Models\Bonus;
use App\Models\SystemPeriod;
use App\Models\BonusThreshold;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BonusController extends Controller
{
    protected BonusCalculationService $bonusService;

    public function __construct(BonusCalculationService $bonusService)
    {
        $this->bonusService = $bonusService;
    }

    /**
     * Liste des bonus
     */
    public function index(Request $request)
    {
        $period = $request->get('period', SystemPeriod::getCurrentPeriod()?->period);

        $bonuses = Bonus::with(['distributeur'])
                       ->when($period, function($query, $period) {
                           return $query->where('period', $period);
                       })
                       ->orderBy('montant_total', 'desc')
                       ->paginate(50);

        $stats = [
            'total_calcule' => Bonus::forPeriod($period)->where('status', 'calculé')->sum('montant_total'),
            'total_valide' => Bonus::forPeriod($period)->where('status', 'validé')->sum('montant_total'),
            'total_paye' => Bonus::forPeriod($period)->where('status', 'payé')->sum('montant_total'),
            'count_distributeurs' => Bonus::forPeriod($period)->distinct('distributeur_id')->count()
        ];

        $periods = SystemPeriod::orderBy('period', 'desc')->pluck('period');

        return view('admin.bonuses.index', compact('bonuses', 'stats', 'periods', 'period'));
    }

    /**
     * Page de calcul des bonus
     */
    public function showCalculation(string $period)
    {
        $systemPeriod = SystemPeriod::where('period', $period)->first();
        if (!$systemPeriod) {
            return redirect()->route('admin.bonuses.index')
                           ->with('error', 'Période invalide');
        }

        // Vérifier si des bonus existent déjà
        $existingBonuses = Bonus::forPeriod($period)->count();

        // Récupérer les seuils actuels
        $thresholds = BonusThreshold::where('is_active', true)->orderBy('grade')->get();

        return view('admin.bonuses.calculate', compact('period', 'systemPeriod', 'existingBonuses', 'thresholds'));
    }

    /**
     * Lance le calcul des bonus
     */
    public function calculate(Request $request, string $period)
    {
        $request->validate([
            'mode' => 'required|in:simulation,real',
            'only_eligible' => 'boolean'
        ]);

        $dryRun = $request->input('mode') === 'simulation';
        $onlyEligible = $request->boolean('only_eligible', true);

        $result = $this->bonusService->calculateBonusesForPeriod($period, [
            'dry_run' => $dryRun,
            'only_eligible' => $onlyEligible
        ]);

        if ($result['success']) {
            if ($dryRun) {
                // En simulation, afficher les résultats détaillés
                return view('admin.bonuses.simulation-results', [
                    'period' => $period,
                    'results' => $result['stats']['details'],
                    'summary' => [
                        'eligible_count' => $result['stats']['eligible_distributors'],
                        'bonus_count' => $result['stats']['bonuses_calculated'],
                        'total_amount' => $result['stats']['total_amount']
                    ]
                ]);
            } else {
                return redirect()->route('admin.bonuses.index', ['period' => $period])
                               ->with('success', $result['message']);
            }
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Valide les bonus pour paiement
     */
    public function validateForPayment(Request $request)
    {
        $request->validate([
            'period' => 'required|string',
            'bonus_ids' => 'array'
        ]);

        $period = $request->input('period');
        $result = $this->bonusService->validateBonusesForPayment($period, Auth::id());

        if ($result['success']) {
            return redirect()->route('admin.bonuses.index', ['period' => $period])
                           ->with('success', $result['message'] . " ({$result['count']} bonus validés pour un total de " . number_format($result['total'], 2) . " €)");
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Affiche le détail d'un bonus
     */
    public function show(Bonus $bonus)
    {
        $bonus->load(['distributeur', 'validator']);

        return view('admin.bonuses.show', compact('bonus'));
    }

    /**
     * Export des bonus en CSV
     */
    public function export(Request $request)
    {
        $period = $request->get('period');
        $status = $request->get('status');

        $bonuses = Bonus::with('distributeur')
                       ->when($period, fn($q) => $q->where('period', $period))
                       ->when($status, fn($q) => $q->where('status', $status))
                       ->get();

        $filename = "bonus_{$period}_{$status}_" . date('YmdHis') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\""
        ];

        $callback = function() use ($bonuses) {
            $file = fopen('php://output', 'w');

            // En-têtes
            fputcsv($file, [
                'Numéro Bonus',
                'Matricule',
                'Nom',
                'Grade',
                'Bonus Direct',
                'Bonus Indirect',
                'Bonus Leadership',
                'Total',
                'Statut',
                'Période'
            ]);

            // Données
            foreach ($bonuses as $bonus) {
                fputcsv($file, [
                    $bonus->formatted_num,
                    $bonus->distributeur->distributeur_id,
                    $bonus->distributeur->nom_distributeur . ' ' . $bonus->distributeur->pnom_distributeur,
                    $bonus->distributeur->etoiles_id,
                    number_format($bonus->montant_direct, 2),
                    number_format($bonus->montant_indirect, 2),
                    number_format($bonus->montant_leadership, 2),
                    number_format($bonus->montant_total, 2),
                    $bonus->status,
                    $bonus->period
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
