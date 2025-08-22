<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * Affiche la liste des logs d'activité
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['user', 'subject']);

        // Filtres
        if ($request->filled('action_type')) {
            $query->where('action', $request->action_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('properties', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $logs = $query->paginate(50)->withQueryString();

        // Données pour les filtres
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $actionTypes = $this->getActionTypes();
        $subjectTypes = $this->getSubjectTypes();

        // Statistiques
        $stats = $this->getStatistics($request);

        return view('admin.logs.index', compact(
            'logs',
            'users',
            'actionTypes',
            'subjectTypes',
            'stats'
        ));
    }

    /**
     * Affiche les détails d'un log
     */
    public function show($id)
    {
        $log = ActivityLog::with(['user', 'subject'])->findOrFail($id);

        // Logs similaires (même utilisateur, même action, dans les 24h)
        $similarLogs = ActivityLog::where('user_id', $log->user_id)
            ->where('action', $log->action)
            ->where('id', '!=', $log->id)
            ->whereBetween('created_at', [
                $log->created_at->subDay(),
                $log->created_at->addDay()
            ])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.logs.show', compact('log', 'similarLogs'));
    }

    /**
     * Export des logs
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,excel,pdf',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from'
        ]);

        $query = ActivityLog::with(['user', 'subject'])
            ->whereBetween('created_at', [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay()
            ]);

        // Appliquer les mêmes filtres que l'index
        if ($request->filled('action_type')) {
            $query->where('action', $request->action_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        switch ($request->format) {
            case 'csv':
                return $this->exportCsv($logs);
            case 'excel':
                return $this->exportExcel($logs);
            case 'pdf':
                return $this->exportPdf($logs, $request->date_from, $request->date_to);
        }
    }

    /**
     * Nettoie les anciens logs
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:30|max:365',
            'confirm' => 'required|accepted'
        ]);

        $date = Carbon::now()->subDays($request->days);

        DB::beginTransaction();
        try {
            // Archiver avant suppression si nécessaire
            if (config('logging.archive_before_delete', false)) {
                $this->archiveLogs($date);
            }

            $count = ActivityLog::where('created_at', '<', $date)->delete();

            DB::commit();

            return back()->with('success', "Nettoyage terminé. {$count} logs supprimés.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erreur lors du nettoyage: ' . $e->getMessage());
        }
    }

    /**
     * Analyse des logs pour détecter des anomalies
     */
    public function analyze()
    {
        $anomalies = [];

        // Tentatives de connexion échouées répétées
        $failedLogins = DB::table('activity_logs')
            ->where('action', 'login_failed')
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->select('ip_address', DB::raw('count(*) as attempts'))
            ->groupBy('ip_address')
            ->having('attempts', '>', 5)
            ->get();

        if ($failedLogins->count() > 0) {
            $anomalies[] = [
                'type' => 'security',
                'severity' => 'high',
                'title' => 'Tentatives de connexion suspectes',
                'description' => "Plusieurs tentatives de connexion échouées détectées depuis {$failedLogins->count()} adresse(s) IP.",
                'data' => $failedLogins
            ];
        }

        // Actions massives
        $massActions = DB::table('activity_logs')
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->select('user_id', 'action', DB::raw('count(*) as count'))
            ->groupBy('user_id', 'action')
            ->having('count', '>', 100)
            ->get();

        if ($massActions->count() > 0) {
            $anomalies[] = [
                'type' => 'performance',
                'severity' => 'medium',
                'title' => 'Actions massives détectées',
                'description' => 'Des utilisateurs ont effectué un nombre inhabituellement élevé d\'actions.',
                'data' => $massActions
            ];
        }

        // Accès en dehors des heures de bureau
        $afterHoursAccess = DB::table('activity_logs')
            ->whereTime('created_at', '<', '08:00:00')
            ->orWhereTime('created_at', '>', '20:00:00')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->get();

        if ($afterHoursAccess->count() > 0) {
            $anomalies[] = [
                'type' => 'compliance',
                'severity' => 'low',
                'title' => 'Accès en dehors des heures de bureau',
                'description' => "Des activités ont été détectées en dehors des heures normales de travail.",
                'data' => $afterHoursAccess
            ];
        }

        return view('admin.logs.analyze', compact('anomalies'));
    }

    /**
     * Récupère les types d'actions disponibles
     */
    protected function getActionTypes()
    {
        return ActivityLog::distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(function ($action) {
                return [
                    'value' => $action,
                    'label' => $this->formatActionName($action)
                ];
            });
    }

    /**
     * Récupère les types de sujets disponibles
     */
    protected function getSubjectTypes()
    {
        return ActivityLog::distinct()
            ->whereNotNull('subject_type')
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => class_basename($type)
                ];
            });
    }

    /**
     * Récupère les statistiques
     */
    protected function getStatistics($request)
    {
        $query = ActivityLog::query();

        // Appliquer les mêmes filtres
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return [
            'total_logs' => $query->count(),
            'unique_users' => $query->distinct('user_id')->count('user_id'),
            'unique_ips' => $query->distinct('ip_address')->count('ip_address'),
            'most_common_action' => $query->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->first()
        ];
    }

    /**
     * Formate le nom d'une action
     */
    protected function formatActionName($action)
    {
        $translations = [
            'create' => 'Création',
            'update' => 'Modification',
            'delete' => 'Suppression',
            'login' => 'Connexion',
            'logout' => 'Déconnexion',
            'login_failed' => 'Connexion échouée',
            'export' => 'Export',
            'import' => 'Import',
            'view' => 'Consultation',
            'download' => 'Téléchargement',
            'approve' => 'Approbation',
            'reject' => 'Rejet'
        ];

        return $translations[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * Export CSV
     */
    protected function exportCsv($logs)
    {
        $filename = 'activity_logs_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');

            // En-têtes
            fputcsv($file, [
                'Date/Heure',
                'Utilisateur',
                'Action',
                'Description',
                'IP',
                'User Agent',
                'Sujet'
            ]);

            // Données
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user ? $log->user->name : 'Système',
                    $this->formatActionName($log->action),
                    $log->description,
                    $log->ip_address,
                    $log->user_agent,
                    $log->subject ? class_basename($log->subject_type) . ' #' . $log->subject_id : ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Archive les logs avant suppression
     */
    protected function archiveLogs($beforeDate)
    {
        // Implémentation de l'archivage selon les besoins
        // Par exemple, export vers un fichier ou une table d'archive
    }
}
