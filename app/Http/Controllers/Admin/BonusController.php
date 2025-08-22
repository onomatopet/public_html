<?php
// app/Http/Controllers/Admin/BonusController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BonusCalculationService;
use App\Models\Bonus;
use App\Models\SystemPeriod;
use App\Models\BonusThreshold;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Achat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $search = $request->get('search'); // Récupérer le terme de recherche

        $bonuses = Bonus::with(['distributeur'])
                    ->when($period, function($query, $period) {
                        return $query->where('period', $period);
                    })
                    ->when($search, function($query, $search) {
                        return $query->whereHas('distributeur', function($q) use ($search) {
                            $q->where('nom_distributeur', 'like', '%' . $search . '%')
                                ->orWhere('pnom_distributeur', 'like', '%' . $search . '%')
                                ->orWhere('distributeur_id', 'like', '%' . $search . '%');
                        });
                    })
                    ->orderBy('bonus', 'desc')
                    ->paginate(50);

        // Mise à jour des statistiques pour prendre en compte les filtres
        $statsQuery = Bonus::where('period', $period);

        if ($search) {
            $statsQuery->whereHas('distributeur', function($q) use ($search) {
                $q->where('nom_distributeur', 'like', '%' . $search . '%')
                ->orWhere('pnom_distributeur', 'like', '%' . $search . '%')
                ->orWhere('distributeur_id', 'like', '%' . $search . '%');
            });
        }

        $stats = [
            'total_calcule' => (clone $statsQuery)->sum('bonus'),
            'total_bonus_direct' => (clone $statsQuery)->sum('bonus_direct'),
            'total_bonus_indirect' => (clone $statsQuery)->sum('bonus_indirect'),
            'total_epargne' => (clone $statsQuery)->sum('epargne'),
            'count_distributeurs' => (clone $statsQuery)->distinct('distributeur_id')->count()
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
        $existingBonuses = Bonus::where('period', $period)->count();

        // Récupérer les seuils actuels
        $thresholds = BonusThreshold::where('is_active', true)->orderBy('grade')->get();

        // Statistiques des distributeurs éligibles
        $eligibleStats = $this->getEligibleDistributorsStats($period);

        return view('admin.bonuses.calculate', compact(
            'period',
            'systemPeriod',
            'existingBonuses',
            'thresholds',
            'eligibleStats'
        ));
    }

    /**
     * Lance le calcul des bonus pour tous les distributeurs
     */
    public function calculate(Request $request, string $period)
    {
        $request->validate([
            'mode' => 'required|in:simulation,real',
            'force_recalculation' => 'boolean'
        ]);

        $dryRun = $request->input('mode') === 'simulation';
        $forceRecalculation = $request->boolean('force_recalculation', false);

        DB::beginTransaction();

        try {
            // Si des bonus existent déjà et qu'on ne force pas le recalcul
            if (!$forceRecalculation && !$dryRun) {
                $existingCount = Bonus::where('period', $period)->count();
                if ($existingCount > 0) {
                    return redirect()->back()
                        ->with('error', "Des bonus existent déjà pour cette période. Utilisez l'option 'Forcer le recalcul' pour recalculer.");
                }
            }

            // Utiliser le service pour calculer les bonus
            $result = $this->bonusService->calculateBonusForPeriod($period);

            if (!$result['success']) {
                DB::rollBack();
                return redirect()->back()->with('error', $result['message']);
            }

            if ($dryRun) {
                DB::rollBack();
                // En simulation, afficher les résultats détaillés
                return view('admin.bonuses.simulation-results', [
                    'period' => $period,
                    'results' => $result['details'],
                    'summary' => $result['stats']
                ]);
            }

            DB::commit();

            return redirect()->route('admin.bonuses.index', ['period' => $period])
                           ->with('success', "Calcul terminé : {$result['stats']['bonus_calculated']} bonus calculés pour un total de " . number_format($result['stats']['total_amount'], 2) . " €");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur calcul bonus", [
                'period' => $period,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Erreur lors du calcul : ' . $e->getMessage());
        }
    }

    /**
     * Calcul de bonus pour un distributeur spécifique
     */
    public function calculateForDistributor(Request $request)
    {
        $request->validate([
            'matricule' => 'required|string',
            'period' => 'required|string'
        ]);

        $matricule = $request->input('matricule');
        $period = $request->input('period');

        $result = $this->bonusService->calculateBonusForDistributor($matricule, $period);

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        // Si c'est une simulation, afficher les détails
        if ($request->input('mode') === 'simulation') {
            return view('admin.bonuses.distributor-simulation', [
                'result' => $result['data'],
                'period' => $period
            ]);
        }

        // Sinon, enregistrer le bonus
        DB::beginTransaction();
        try {
            $bonusData = $result['data'];

            // Vérifier qu'il n'existe pas déjà
            $existing = Bonus::where('distributeur_id', $bonusData['distributeur_id'])
                            ->where('period', $period)
                            ->first();

            if ($existing) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Un bonus existe déjà pour ce distributeur sur cette période');
            }

            // Créer le bonus
            $bonus = Bonus::create([
                'num' => $bonusData['numero'],
                'distributeur_id' => $bonusData['distributeur_id'],
                'period' => $period,
                'bonus_direct' => $bonusData['bonus_direct'],
                'bonus_indirect' => $bonusData['bonus_indirect'],
                'bonus' => $bonusData['bonusFinal'],
                'epargne' => $bonusData['epargne']
            ]);

            DB::commit();

            return redirect()->route('admin.bonuses.show', $bonus)
                           ->with('success', 'Bonus calculé et enregistré avec succès');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
        }
    }

    /**
     * Affiche le détail d'un bonus
     */
    public function show(Bonus $bonus)
    {
        $bonus->load(['distributeur']);

        // Récupérer les détails du calcul
        $levelCurrent = LevelCurrent::where('distributeur_id', $bonus->distributeur_id)
                                   ->where('period', $bonus->period)
                                   ->first();

        return view('admin.bonuses.show', compact('bonus', 'levelCurrent'));
    }

    /**
     * Affiche le détail d'un bonus
     */
    public function generateHtml(Bonus $bonus)
    {
        $bonus->load(['distributeur']);

        // Récupérer les détails du calcul
        $levelCurrent = LevelCurrent::where('distributeur_id', $bonus->distributeur_id)
                                   ->where('period', $bonus->period)
                                   ->first();

        return view('admin.bonuses.imprimable', compact('bonus', 'levelCurrent'));
    }

    /**
     * Export des bonus en CSV/Excel
     */
    public function export(Request $request)
    {
        $period = $request->get('period');
        $format = $request->get('format', 'csv');

        $bonuses = Bonus::with('distributeur')
                       ->when($period, fn($q) => $q->where('period', $period))
                       ->orderBy('num')
                       ->get();

        // Export CSV uniquement pour l'instant
        // Si vous avez Laravel Excel installé, décommentez la ligne suivante :
        // if ($format === 'excel' && class_exists('\Maatwebsite\Excel\Facades\Excel')) {
        //     return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BonusExport($bonuses, $period), "bonus_{$period}.xlsx");
        // }

        // Export CSV
        $filename = "bonus_{$period}_" . date('YmdHis') . ".csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\""
        ];

        $callback = function() use ($bonuses) {
            $file = fopen('php://output', 'w');

            // En-têtes
            fputcsv($file, [
                'Numéro',
                'Matricule',
                'Nom',
                'Prénom',
                'Grade',
                'Bonus Direct',
                'Bonus Indirect',
                'Bonus Total',
                'Épargne'
            ]);

            // Données
            foreach ($bonuses as $bonus) {
                fputcsv($file, [
                    $bonus->num,
                    $bonus->distributeur->distributeur_id,
                    $bonus->distributeur->nom_distributeur,
                    $bonus->distributeur->pnom_distributeur,
                    $bonus->distributeur->etoiles_id,
                    number_format($bonus->bonus_direct, 2, '.', ''),
                    number_format($bonus->bonus_indirect, 2, '.', ''),
                    number_format($bonus->bonus, 2, '.', ''),
                    number_format($bonus->epargne, 2, '.', '')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Supprime un bonus (avec autorisation)
     */
    public function destroy(Bonus $bonus)
    {
        // Vérifier les permissions
        // Si vous utilisez Spatie Laravel Permission :
        // if (!Auth::user()->can('delete-bonus')) {

        // Sinon, vérification basique par rôle :
        if (!Auth::user()->is_admin) {
            return redirect()->back()->with('error', 'Vous n\'avez pas la permission de supprimer des bonus');
        }

        DB::beginTransaction();
        try {
            Log::info("Suppression bonus", [
                'bonus_id' => $bonus->id,
                'user_id' => Auth::id(),
                'distributeur' => $bonus->distributeur->distributeur_id,
                'period' => $bonus->period,
                'montant' => $bonus->bonus
            ]);

            $bonus->delete();

            DB::commit();

            return redirect()->route('admin.bonuses.index', ['period' => $bonus->period])
                           ->with('success', 'Bonus supprimé avec succès');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erreur lors de la suppression');
        }
    }

    /**
     * Récupère les statistiques des distributeurs éligibles
     */
    private function getEligibleDistributorsStats(string $period): array
    {
        $totalDistributors = LevelCurrent::where('period', $period)->count();

        // Distributeurs avec achats
        $withPurchases = LevelCurrent::where('period', $period)
            ->whereHas('distributeur.achats', function($q) use ($period) {
                $q->where('period', $period);
            })
            ->count();

        // Distributeurs éligibles selon les seuils
        $eligible = 0;
        $byGrade = [];

        for ($grade = 1; $grade <= 10; $grade++) {
            $threshold = BonusThreshold::where('grade', $grade)
                                     ->where('is_active', true)
                                     ->first();

            if (!$threshold) continue;

            $count = LevelCurrent::where('period', $period)
                ->where('etoiles', $grade)
                ->where('new_cumul', '>=', $threshold->minimum_pv)
                ->whereHas('distributeur.achats', function($q) use ($period) {
                    $q->where('period', $period);
                })
                ->count();

            $eligible += $count;
            $byGrade[$grade] = [
                'count' => $count,
                'threshold' => $threshold->minimum_pv
            ];
        }

        return [
            'total' => $totalDistributors,
            'with_purchases' => $withPurchases,
            'eligible' => $eligible,
            'by_grade' => $byGrade
        ];
    }

    /**
     * Page de duplicata de bonus
     */
    public function duplicate(Request $request)
    {
        $matricule = $request->input('matricule');
        $period = $request->input('period');

        if (!$matricule || !$period) {
            return view('admin.bonuses.duplicate-search');
        }

        $distributeur = Distributeur::where('distributeur_id', $matricule)->first();
        if (!$distributeur) {
            return redirect()->back()->with('error', 'Distributeur non trouvé');
        }

        $bonus = Bonus::where('distributeur_id', $distributeur->id)
                     ->where('period', $period)
                     ->first();

        if (!$bonus) {
            return redirect()->back()->with('error', 'Aucun bonus trouvé pour ce distributeur sur cette période');
        }

        return view('admin.bonuses.duplicate', compact('bonus', 'distributeur'));
    }
}
