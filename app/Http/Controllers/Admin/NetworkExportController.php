<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\Achat;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\NetworkExport;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NetworkExportController extends Controller
{
    /**
     * Affiche le formulaire de sélection
     */
    public function index()
    {
        $distributeurs = Distributeur::orderBy('nom_distributeur', 'ASC')
            ->get(['id', 'distributeur_id', 'nom_distributeur', 'pnom_distributeur']);

        $periods = LevelCurrent::select('period')
            ->distinct()
            ->orderBy('period', 'DESC')
            ->pluck('period');

        return view('admin.network.index', compact('distributeurs', 'periods'));
    }

    /**
     * Détecte dans quelle table se trouvent les données pour une période donnée
     */
    private function detectDataTable($period)
    {
        // Vérifier d'abord dans level_currents
        $existsInCurrent = DB::table('level_currents')
            ->where('period', $period)
            ->exists();

        if ($existsInCurrent) {
            return [
                'table' => 'level_currents',
                'is_archive' => false
            ];
        }

        // Si pas trouvé, vérifier dans level_current_histories
        $existsInHistory = DB::table('level_current_histories')
            ->where('period', $period)
            ->exists();

        if ($existsInHistory) {
            return [
                'table' => 'level_current_histories',
                'is_archive' => true
            ];
        }

        // Aucune donnée trouvée
        return null;
    }

    /**
     * Affiche l'aperçu du réseau avant export
     */
    public function export(Request $request)
    {
        $request->validate([
            'distributeur_id' => 'required|exists:distributeurs,distributeur_id',
            'period' => 'required|string|size:7'
        ]);

        $distributeurId = $request->distributeur_id;
        $period = $request->period;

        // Détecter dans quelle table se trouvent les données
        $tableInfo = $this->detectDataTable($period);

        if (!$tableInfo) {
            return back()->with('error', "Aucune donnée trouvée pour la période {$period}.");
        }

        // Récupérer les données du réseau avec la bonne table
        $networkData = $this->getNetworkData($distributeurId, $period, $tableInfo['table']);

        if (empty($networkData)) {
            return back()->with('error', 'Aucune donnée trouvée pour ce distributeur et cette période.');
        }

        // Informations du distributeur principal
        $mainDistributor = Distributeur::where('distributeur_id', $distributeurId)->first();

        return view('admin.network.show', [
            'distributeurs' => $networkData,
            'mainDistributor' => $mainDistributor,
            'period' => $period,
            'totalCount' => count($networkData),
            'isArchive' => $tableInfo['is_archive'] // Passer l'info à la vue
        ]);
    }

    /**
     * Affiche l'aperçu du réseau pour impression
     */
    public function exportHtml(Request $request)
    {
        $request->validate([
            'distributeur_id' => 'required|exists:distributeurs,distributeur_id',
            'period' => 'required|string|size:7'
        ]);

        $distributeurId = $request->distributeur_id;
        $period = $request->period;

        // Détecter dans quelle table se trouvent les données
        $tableInfo = $this->detectDataTable($period);

        if (!$tableInfo) {
            return back()->with('error', "Aucune donnée trouvée pour la période {$period}.");
        }

        // Récupérer les données du réseau
        $networkData = $this->getNetworkData($distributeurId, $period, $tableInfo['table']);

        if (empty($networkData)) {
            return back()->with('error', 'Aucune donnée trouvée pour ce distributeur et cette période.');
        }

        // Informations du distributeur principal
        $mainDistributor = Distributeur::where('distributeur_id', $distributeurId)->first();

        return view('admin.network.imprimable', [
            'distributeurs' => $networkData,
            'mainDistributor' => $mainDistributor,
            'period' => $period,
            'totalCount' => count($networkData),
            'isArchive' => $tableInfo['is_archive']
        ]);
    }

    /**
     * Export en PDF
     */
    public function exportPdf(Request $request)
    {
        $request->validate([
            'distributeur_id' => 'required|exists:distributeurs,distributeur_id',
            'period' => 'required|string|size:7'
        ]);

        // Détecter dans quelle table se trouvent les données
        $tableInfo = $this->detectDataTable($request->period);

        if (!$tableInfo) {
            return back()->with('error', "Aucune donnée trouvée pour la période {$request->period}.");
        }

        $networkData = $this->getNetworkData($request->distributeur_id, $request->period, $tableInfo['table']);
        $mainDistributor = Distributeur::where('distributeur_id', $request->distributeur_id)->first();

        $pdf = PDF::loadView('admin.network.pdf', [
            'distributeurs' => $networkData,
            'mainDistributor' => $mainDistributor,
            'period' => $request->period,
            'totalCount' => count($networkData),
            'printDate' => Carbon::now()->format('d/m/Y H:i'),
            'isArchive' => $tableInfo['is_archive']
        ]);

        $filename = "reseau_{$request->distributeur_id}_{$request->period}" .
                    ($tableInfo['is_archive'] ? '_archive' : '') . ".pdf";

        return $pdf->download($filename);
    }

    /**
     * Export en Excel
     */
    public function exportExcel(Request $request)
    {
        $request->validate([
            'distributeur_id' => 'required|exists:distributeurs,distributeur_id',
            'period' => 'required|string|size:7'
        ]);

        $filename = "reseau_{$request->distributeur_id}_{$request->period}.xlsx";

        return Excel::download(
            new NetworkExport($request->distributeur_id, $request->period),
            $filename
        );
    }

    /**
     * Récupère les données du réseau avec support des archives
     */
    private function getNetworkData($distributeurMatricule, $period, $tableName = 'level_currents')
    {
        \Log::info("=== Début getNetworkData ===");
        \Log::info("Distributeur Matricule: {$distributeurMatricule}, Période: {$period}, Table: {$tableName}");

        // D'abord, obtenir l'ID primaire du distributeur principal
        $distributeurPrincipal = DB::table('distributeurs')
            ->where('distributeur_id', $distributeurMatricule)
            ->first();

        if (!$distributeurPrincipal) {
            \Log::error("Distributeur avec matricule {$distributeurMatricule} non trouvé");
            return [];
        }

        \Log::info("Distributeur principal trouvé - ID: {$distributeurPrincipal->id}, Nom: {$distributeurPrincipal->nom_distributeur}");

        // Initialiser
        $network = [];
        $processedIds = []; // IDs primaires traités
        $queue = [['id' => $distributeurPrincipal->id, 'matricule' => $distributeurPrincipal->distributeur_id, 'level' => 0]];
        $limit = 5000;

        while (!empty($queue) && count($network) < $limit) {
            $current = array_shift($queue);
            $currentId = $current['id']; // ID primaire
            $currentMatricule = $current['matricule']; // Matricule
            $currentLevel = $current['level'];

            \Log::info("Traitement ID: {$currentId}, Matricule: {$currentMatricule}, Niveau: {$currentLevel}");

            // Éviter les doublons
            if (in_array($currentId, $processedIds)) {
                \Log::info("ID {$currentId} déjà traité");
                continue;
            }
            $processedIds[] = $currentId;

            // Récupérer les données avec la table appropriée (currents ou histories)
            $data = DB::table('distributeurs as d')
                ->leftJoin("{$tableName} as lc", function($join) use ($period) {
                    $join->on('d.id', '=', 'lc.distributeur_id')
                         ->where('lc.period', '=', $period);
                })
                ->leftJoin('distributeurs as parent', 'd.id_distrib_parent', '=', 'parent.id')
                ->where('d.id', $currentId)
                ->select([
                    'd.id',
                    'd.distributeur_id',
                    'd.nom_distributeur',
                    'd.pnom_distributeur',
                    'd.id_distrib_parent',
                    'd.etoiles_id',
                    'parent.distributeur_id as parent_matricule',
                    'parent.nom_distributeur as nom_parent',
                    'parent.pnom_distributeur as pnom_parent',
                    'lc.etoiles',
                    'lc.new_cumul',
                    'lc.cumul_total',
                    'lc.cumul_collectif',
                    'lc.cumul_individuel',
                    'lc.rang'
                ])
                ->first();

            if ($data) {
                \Log::info("Données trouvées pour {$currentMatricule}: etoiles={$data->etoiles}, new_cumul={$data->new_cumul}, cumul_collectif={$data->cumul_collectif}");

                $network[] = [
                    'rang' => $currentLevel,
                    'distributeur_id' => $data->distributeur_id,
                    'nom_distributeur' => $data->nom_distributeur ?? 'N/A',
                    'pnom_distributeur' => $data->pnom_distributeur ?? 'N/A',
                    'etoiles' => $data->etoiles ?? $data->etoiles_id ?? 0,
                    'new_cumul' => $data->new_cumul ?? 0,
                    'cumul_total' => $data->cumul_total ?? 0,
                    'cumul_collectif' => $data->cumul_collectif ?? 0,
                    'cumul_individuel' => $data->cumul_individuel ?? 0,
                    'id_distrib_parent' => $data->parent_matricule ?? '',
                    'nom_parent' => $data->nom_parent ?? 'N/A',
                    'pnom_parent' => $data->pnom_parent ?? 'N/A',
                ];

                // Chercher les enfants avec l'ID primaire du parent
                $children = DB::table('distributeurs')
                    ->where('id_distrib_parent', $currentId)
                    ->get(['id', 'distributeur_id']);

                \Log::info("Nombre d'enfants trouvés pour ID {$currentId}: " . $children->count());

                foreach ($children as $child) {
                    $queue[] = [
                        'id' => $child->id,
                        'matricule' => $child->distributeur_id,
                        'level' => $currentLevel + 1
                    ];
                }
            } else {
                \Log::warning("Aucune donnée trouvée pour ID {$currentId}");
            }
        }

        \Log::info("=== Fin getNetworkData ===");
        \Log::info("Total: " . count($network) . " distributeurs");

        return $network;
    }

    /**
     * Recherche AJAX de distributeurs
     */
    public function searchDistributeurs(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $distributeurs = Distributeur::where(function($q) use ($query) {
                $q->where('distributeur_id', 'LIKE', "%{$query}%")
                ->orWhere('nom_distributeur', 'LIKE', "%{$query}%")
                ->orWhere('pnom_distributeur', 'LIKE', "%{$query}%")
                ->orWhere(DB::raw("CONCAT(nom_distributeur, ' ', pnom_distributeur)"), 'LIKE', "%{$query}%")
                ->orWhere(DB::raw("CONCAT(pnom_distributeur, ' ', nom_distributeur)"), 'LIKE', "%{$query}%");
            })
            ->orderBy('nom_distributeur')
            ->limit(30)
            ->get(['id', 'distributeur_id', 'nom_distributeur', 'pnom_distributeur', 'etoiles_id', 'id_distrib_parent']);

        // Formater les résultats pour l'affichage
        $results = $distributeurs->map(function($dist) {
            return [
                'id' => $dist->id,
                'distributeur_id' => $dist->distributeur_id,
                'nom_distributeur' => $dist->nom_distributeur,
                'pnom_distributeur' => $dist->pnom_distributeur,
                'etoiles_id' => $dist->etoiles_id ?? 0,
                'id_distrib_parent' => $dist->id_distrib_parent,
                'display_name' => $dist->distributeur_id . ' - ' . $dist->nom_distributeur . ' ' . $dist->pnom_distributeur,
                'grade_display' => str_repeat('★', $dist->etoiles_id ?? 0)
            ];
        });

        return response()->json($results);
    }

    /**
     * Recherche AJAX des périodes disponibles dans la table achats
     */
    public function searchPeriods(Request $request)
    {
        $query = $request->get('q', '');

        try {
            // Rechercher dans les deux tables
            $periodsCurrents = DB::table('level_currents')
                ->select('period')
                ->whereNotNull('period')
                ->where('period', '!=', '')
                ->when($query, function($q) use ($query) {
                    return $q->where('period', 'LIKE', "%{$query}%");
                })
                ->groupBy('period');

            $periodsHistories = DB::table('level_current_histories')
                ->select('period')
                ->whereNotNull('period')
                ->where('period', '!=', '')
                ->when($query, function($q) use ($query) {
                    return $q->where('period', 'LIKE', "%{$query}%");
                })
                ->groupBy('period');

            // Union des deux requêtes
            $periods = $periodsCurrents->union($periodsHistories)
                ->orderBy('period', 'desc')
                ->limit(empty($query) ? 12 : 20)
                ->pluck('period');

            // Formater les périodes pour l'affichage
            $formattedPeriods = $periods->map(function($period) {
                try {
                    $date = Carbon::createFromFormat('Y-m', $period);

                    // Vérifier si c'est une archive
                    $isArchive = DB::table('level_current_histories')
                        ->where('period', $period)
                        ->exists() &&
                        !DB::table('level_currents')
                        ->where('period', $period)
                        ->exists();

                    return [
                        'value' => $period,
                        'label' => ucfirst($date->locale('fr')->isoFormat('MMMM YYYY')) .
                                  ($isArchive ? ' (Archive)' : ''),
                        'year' => $date->year,
                        'is_archive' => $isArchive
                    ];
                } catch (\Exception $e) {
                    return null;
                }
            })->filter()->values();

            // Grouper par année
            $groupedPeriods = $formattedPeriods->groupBy('year')->sortKeysDesc();

            return response()->json($groupedPeriods);
        } catch (\Exception $e) {
            \Log::error('Erreur searchPeriods: ' . $e->getMessage());
            return response()->json([]);
        }
    }
}
