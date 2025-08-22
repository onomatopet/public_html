<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Models\Distributeur;
use App\Models\LevelCurrent;
use App\Models\SystemPeriod;
use App\Services\NetworkVisualizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DistributorNetworkController extends Controller
{
    protected NetworkVisualizationService $visualizationService;

    public function __construct(NetworkVisualizationService $visualizationService)
    {
        $this->visualizationService = $visualizationService;
    }

    /**
     * Affiche la vue liste du réseau
     */
    public function index(Request $request)
    {
        $distributeur = Auth::user()->distributeur;

        if (!$distributeur) {
            return redirect()->route('distributor.dashboard')
                ->with('error', 'Profil distributeur non trouvé.');
        }

        // Récupérer la période actuelle
        $currentPeriod = SystemPeriod::getCurrentPeriod();

        // Paramètres de recherche et filtrage
        $search = $request->get('search');
        $gradeFilter = $request->get('grade');
        $statusFilter = $request->get('status');
        $level = $request->get('level', 'all'); // all, direct, level2, level3

        // Construire la requête
        $query = $this->buildNetworkQuery($distributeur, $level);

        // Appliquer les filtres
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nom_distributeur', 'like', "%{$search}%")
                  ->orWhere('pnom_distributeur', 'like', "%{$search}%")
                  ->orWhere('distributeur_id', 'like', "%{$search}%")
                  ->orWhere('mail_distributeur', 'like', "%{$search}%");
            });
        }

        if ($gradeFilter !== null && $gradeFilter !== '') {
            $query->where('etoiles_id', $gradeFilter);
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('statut_validation_periode', $statusFilter);
        }

        // Paginer les résultats
        $members = $query->paginate(20)->withQueryString();

        // Ajouter les performances pour chaque membre
        $members->each(function($member) use ($currentPeriod) {
            $level = LevelCurrent::where('distributeur_id', $member->id)
                ->where('period', $currentPeriod->period)
                ->first();

            $member->current_pv = $level->pv ?? 0;
            $member->current_pg = $level->pg ?? 0;
            $member->team_size = $this->getTeamSize($member->id);
        });

        // Statistiques globales du réseau
        $networkStats = $this->getNetworkStats($distributeur);

        return view('distributor.network.index', compact(
            'members',
            'networkStats',
            'search',
            'gradeFilter',
            'statusFilter',
            'level',
            'currentPeriod'
        ));
    }

    /**
     * Affiche la vue arbre du réseau
     */
    public function tree(Request $request)
    {
        $distributeur = Auth::user()->distributeur;

        if (!$distributeur) {
            return redirect()->route('distributor.dashboard')
                ->with('error', 'Profil distributeur non trouvé.');
        }

        // Profondeur maximale à afficher
        $maxDepth = $request->get('depth', 3);
        $maxDepth = min(max($maxDepth, 1), 5); // Entre 1 et 5

        // Générer les données de l'arbre
        $treeData = $this->visualizationService->generateTreeData(
            $distributeur->id,
            $maxDepth
        );

        // Statistiques par niveau
        $levelStats = $this->getLevelStats($distributeur);

        return view('distributor.network.tree', compact(
            'treeData',
            'levelStats',
            'maxDepth'
        ));
    }

    /**
     * Affiche les détails d'un membre du réseau
     */
    public function show($id)
    {
        $distributeur = Auth::user()->distributeur;
        $member = Distributeur::findOrFail($id);

        // Vérifier que le membre fait partie du réseau
        if (!$this->isInNetwork($distributeur->id, $member->id)) {
            abort(403, 'Ce distributeur ne fait pas partie de votre réseau.');
        }

        // Charger les informations détaillées
        $member->load(['parent', 'children']);

        // Performance actuelle
        $currentPeriod = SystemPeriod::getCurrentPeriod();
        $currentLevel = LevelCurrent::where('distributeur_id', $member->id)
            ->where('period', $currentPeriod->period)
            ->first();

        // Historique des performances (6 derniers mois)
        $performanceHistory = LevelCurrent::where('distributeur_id', $member->id)
            ->orderBy('period', 'desc')
            ->limit(6)
            ->get();

        // Statistiques
        $stats = [
            'total_achats' => $member->achats()->sum('montant_total_ligne'),
            'achats_ce_mois' => $member->achats()
                ->where('period', $currentPeriod->period)
                ->sum('montant_total_ligne'),
            'equipe_directe' => $member->children()->count(),
            'equipe_totale' => $this->getTeamSize($member->id),
            'membre_depuis' => $member->created_at->diffForHumans()
        ];

        // Position dans l'arbre
        $genealogy = $this->getGenealogy($distributeur->id, $member->id);

        return view('distributor.network.show', compact(
            'member',
            'currentLevel',
            'performanceHistory',
            'stats',
            'genealogy',
            'currentPeriod'
        ));
    }

    /**
     * Exporte le réseau
     */
    public function export(Request $request)
    {
        $distributeur = Auth::user()->distributeur;
        $format = $request->get('format', 'excel');

        // Préparer les données
        $members = $this->getAllNetworkMembers($distributeur->id);

        // Ajouter les performances actuelles
        $currentPeriod = SystemPeriod::getCurrentPeriod();
        $members->each(function($member) use ($currentPeriod) {
            $level = LevelCurrent::where('distributeur_id', $member->id)
                ->where('period', $currentPeriod->period)
                ->first();

            $member->pv = $level->pv ?? 0;
            $member->pg = $level->pg ?? 0;
            $member->cumul_individuel = $level->cumul_individuel ?? 0;
            $member->cumul_collectif = $level->cumul_collectif ?? 0;
        });

        // Générer l'export selon le format
        switch ($format) {
            case 'csv':
                return $this->exportCsv($members);
            case 'pdf':
                return $this->exportPdf($members);
            default:
                return $this->exportExcel($members);
        }
    }

    /**
     * Construit la requête pour récupérer les membres du réseau
     */
    protected function buildNetworkQuery(Distributeur $distributeur, string $level)
    {
        switch ($level) {
            case 'direct':
                // Seulement les filleuls directs
                return Distributeur::where('id_distrib_parent', $distributeur->id);

            case 'level2':
                // Filleuls directs et leurs filleuls
                $directIds = Distributeur::where('id_distrib_parent', $distributeur->id)
                    ->pluck('id');

                return Distributeur::whereIn('id_distrib_parent', $directIds)
                    ->orWhere('id_distrib_parent', $distributeur->id);

            case 'level3':
                // Jusqu'au niveau 3
                $directIds = Distributeur::where('id_distrib_parent', $distributeur->id)
                    ->pluck('id');

                $level2Ids = Distributeur::whereIn('id_distrib_parent', $directIds)
                    ->pluck('id');

                return Distributeur::whereIn('id_distrib_parent', $level2Ids)
                    ->orWhereIn('id_distrib_parent', $directIds)
                    ->orWhere('id_distrib_parent', $distributeur->id);

            default:
                // Tout le réseau
                return $this->getAllNetworkMembersQuery($distributeur->id);
        }
    }

    /**
     * Récupère tous les membres du réseau (récursif)
     */
    protected function getAllNetworkMembersQuery($distributeurId)
    {
        // Utiliser une CTE récursive pour récupérer tout l'arbre
        return Distributeur::whereIn('id', function($query) use ($distributeurId) {
            $query->select('id')
                ->from(DB::raw('(
                    WITH RECURSIVE network AS (
                        SELECT id, id_distrib_parent
                        FROM distributeurs
                        WHERE id_distrib_parent = ' . $distributeurId . '

                        UNION ALL

                        SELECT d.id, d.id_distrib_parent
                        FROM distributeurs d
                        INNER JOIN network n ON d.id_distrib_parent = n.id
                    )
                    SELECT id FROM network
                ) as network_members'));
        });
    }

    /**
     * Récupère tous les membres du réseau
     */
    protected function getAllNetworkMembers($distributeurId)
    {
        return $this->getAllNetworkMembersQuery($distributeurId)->get();
    }

    /**
     * Calcule la taille de l'équipe d'un distributeur
     */
    protected function getTeamSize($distributeurId): int
    {
        return DB::table(DB::raw('(
            WITH RECURSIVE network AS (
                SELECT id
                FROM distributeurs
                WHERE id_distrib_parent = ' . $distributeurId . '

                UNION ALL

                SELECT d.id
                FROM distributeurs d
                INNER JOIN network n ON d.id_distrib_parent = n.id
            )
            SELECT COUNT(*) as count FROM network
        ) as result'))->value('count');
    }

    /**
     * Vérifie si un membre fait partie du réseau
     */
    protected function isInNetwork($parentId, $memberId): bool
    {
        if ($parentId == $memberId) {
            return true;
        }

        return DB::table(DB::raw('(
            WITH RECURSIVE network AS (
                SELECT id
                FROM distributeurs
                WHERE id_distrib_parent = ' . $parentId . '

                UNION ALL

                SELECT d.id
                FROM distributeurs d
                INNER JOIN network n ON d.id_distrib_parent = n.id
            )
            SELECT id FROM network WHERE id = ' . $memberId . '
        ) as result'))->exists();
    }

    /**
     * Obtient la généalogie entre deux distributeurs
     */
    protected function getGenealogy($rootId, $targetId): array
    {
        $path = [];
        $current = Distributeur::find($targetId);

        while ($current && $current->id != $rootId) {
            array_unshift($path, [
                'id' => $current->id,
                'name' => $current->nom_distributeur . ' ' . $current->pnom_distributeur,
                'matricule' => $current->distributeur_id
            ]);
            $current = $current->parent;
        }

        return $path;
    }

    /**
     * Obtient les statistiques du réseau
     */
    protected function getNetworkStats(Distributeur $distributeur): array
    {
        $currentPeriod = SystemPeriod::getCurrentPeriod();

        // Statistiques de base
        $directCount = $distributeur->children()->count();
        $totalCount = $this->getTeamSize($distributeur->id);

        // Nouveaux ce mois
        $newThisMonth = $this->getAllNetworkMembers($distributeur->id)
            ->where('created_at', '>=', $currentPeriod->start_date)
            ->count();

        // Actifs ce mois
        $activeThisMonth = DB::table('distributeurs as d')
            ->join('achats as a', 'd.id', '=', 'a.distributeur_id')
            ->whereIn('d.id', $this->getAllNetworkMembers($distributeur->id)->pluck('id'))
            ->where('a.period', $currentPeriod->period)
            ->distinct('d.id')
            ->count('d.id');

        // Volume total du réseau
        $networkVolume = DB::table('distributeurs as d')
            ->join('level_currents as lc', 'd.id', '=', 'lc.distributeur_id')
            ->whereIn('d.id', $this->getAllNetworkMembers($distributeur->id)->pluck('id'))
            ->where('lc.period', $currentPeriod->period)
            ->sum('lc.pv');

        return [
            'direct_count' => $directCount,
            'total_count' => $totalCount,
            'new_this_month' => $newThisMonth,
            'active_this_month' => $activeThisMonth,
            'activity_rate' => $totalCount > 0 ? round(($activeThisMonth / $totalCount) * 100, 1) : 0,
            'network_volume' => $networkVolume
        ];
    }

    /**
     * Obtient les statistiques par niveau
     */
    protected function getLevelStats(Distributeur $distributeur): array
    {
        $stats = [];
        $currentLevel = [$distributeur->id];

        for ($level = 1; $level <= 5; $level++) {
            $nextLevel = Distributeur::whereIn('id_distrib_parent', $currentLevel)
                ->pluck('id')
                ->toArray();

            $count = count($nextLevel);
            if ($count == 0) break;

            $stats[] = [
                'level' => $level,
                'count' => $count,
                'percentage' => 0 // Sera calculé côté vue
            ];

            $currentLevel = $nextLevel;
        }

        return $stats;
    }

    /**
     * Export CSV
     */
    protected function exportCsv($members)
    {
        $filename = 'reseau_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($members) {
            $file = fopen('php://output', 'w');

            // En-têtes
            fputcsv($file, [
                'Matricule',
                'Nom',
                'Prénom',
                'Email',
                'Téléphone',
                'Grade',
                'PV',
                'PG',
                'Cumul Individuel',
                'Cumul Collectif',
                'Date inscription'
            ]);

            // Données
            foreach ($members as $member) {
                fputcsv($file, [
                    $member->distributeur_id,
                    $member->nom_distributeur,
                    $member->pnom_distributeur,
                    $member->mail_distributeur,
                    $member->tel_distributeur,
                    $member->etoiles_id,
                    $member->pv,
                    $member->pg,
                    $member->cumul_individuel,
                    $member->cumul_collectif,
                    $member->created_at->format('Y-m-d')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
