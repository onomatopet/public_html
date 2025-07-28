<?php

namespace App\Services\MLM;

use App\Models\MLMCleaningSession;
use App\Models\MLMCleaningAnomaly;
use App\Models\MLMCleaningLog;
use App\Models\Distributeur;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class MLMCleaningReportService
{
    protected MLMCleaningSession $session;

    public function __construct(MLMCleaningSession $session)
    {
        $this->session = $session;
    }

    /**
     * Générer le rapport de preview
     */
    public function generatePreviewReport(): array
    {
        $anomalies = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->with('distributeur')
            ->get();

        $groupedBySeverity = $anomalies->groupBy('severity');
        $groupedByType = $anomalies->groupBy('type');

        $report = [
            'session' => [
                'code' => $this->session->session_code,
                'type' => $this->session->type,
                'period_range' => $this->session->period_start . ' - ' . $this->session->period_end,
                'created_at' => $this->session->created_at->format('d/m/Y H:i'),
                'status' => $this->session->status
            ],
            'summary' => [
                'total_records_analyzed' => $this->session->records_analyzed,
                'total_anomalies' => $anomalies->count(),
                'auto_fixable' => $anomalies->where('can_auto_fix', true)->count(),
                'manual_required' => $anomalies->where('can_auto_fix', false)->count(),
                'critical_issues' => $groupedBySeverity[MLMCleaningAnomaly::SEVERITY_CRITICAL]->count() ?? 0
            ],
            'by_severity' => [],
            'by_type' => [],
            'top_affected_distributors' => $this->getTopAffectedDistributors(),
            'recommendations' => $this->generateRecommendations($anomalies)
        ];

        // Détails par sévérité
        foreach ([
            MLMCleaningAnomaly::SEVERITY_CRITICAL,
            MLMCleaningAnomaly::SEVERITY_HIGH,
            MLMCleaningAnomaly::SEVERITY_MEDIUM,
            MLMCleaningAnomaly::SEVERITY_LOW
        ] as $severity) {
            $severityAnomalies = $groupedBySeverity[$severity] ?? collect();
            $report['by_severity'][$severity] = [
                'count' => $severityAnomalies->count(),
                'percentage' => $anomalies->count() > 0
                    ? round(($severityAnomalies->count() / $anomalies->count()) * 100, 2)
                    : 0,
                'auto_fixable' => $severityAnomalies->where('can_auto_fix', true)->count()
            ];
        }

        // Détails par type
        foreach ($groupedByType as $type => $typeAnomalies) {
            $report['by_type'][$type] = [
                'count' => $typeAnomalies->count(),
                'percentage' => round(($typeAnomalies->count() / $anomalies->count()) * 100, 2),
                'severity_breakdown' => $typeAnomalies->groupBy('severity')->map->count(),
                'sample' => $typeAnomalies->take(3)->map(function ($anomaly) {
                    return [
                        'distributeur' => $anomaly->distributeur->nom_distributeur ?? 'N/A',
                        'period' => $anomaly->period,
                        'description' => $anomaly->description
                    ];
                })
            ];
        }

        return $report;
    }

    /**
     * Générer le rapport final
     */
    public function generateFinalReport(): array
    {
        $logs = MLMCleaningLog::where('session_id', $this->session->id)->get();
        $fixedAnomalies = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->where('is_fixed', true)
            ->get();

        $report = [
            'session' => [
                'code' => $this->session->session_code,
                'type' => $this->session->type,
                'period_range' => $this->session->period_start . ' - ' . $this->session->period_end,
                'started_at' => $this->session->started_at->format('d/m/Y H:i'),
                'completed_at' => $this->session->completed_at->format('d/m/Y H:i'),
                'execution_time' => $this->session->getExecutionTimeFormatted(),
                'status' => $this->session->status
            ],
            'summary' => [
                'total_records_analyzed' => $this->session->records_analyzed,
                'total_anomalies_found' => $this->session->records_with_anomalies,
                'total_corrections_applied' => $fixedAnomalies->count(),
                'success_rate' => $this->session->records_with_anomalies > 0
                    ? round(($fixedAnomalies->count() / $this->session->records_with_anomalies) * 100, 2)
                    : 100,
                'total_changes' => $logs->count()
            ],
            'corrections_by_type' => $this->getCorrectionsBreakdown($fixedAnomalies),
            'changes_by_table' => $this->getChangesByTable($logs),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'unresolved_issues' => $this->getUnresolvedIssues()
        ];

        return $report;
    }

    /**
     * Obtenir les distributeurs les plus affectés
     */
    protected function getTopAffectedDistributors(int $limit = 10): array
    {
        return MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->select('distributeur_id', DB::raw('count(*) as anomaly_count'))
            ->groupBy('distributeur_id')
            ->orderByDesc('anomaly_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $distributeur = Distributeur::find($item->distributeur_id);
                return [
                    'id' => $item->distributeur_id,
                    'matricule' => $distributeur->distributeur_id ?? 'N/A',
                    'nom' => $distributeur->nom_distributeur ?? 'N/A',
                    'anomaly_count' => $item->anomaly_count,
                    'types' => MLMCleaningAnomaly::where('session_id', $this->session->id)
                        ->where('distributeur_id', $item->distributeur_id)
                        ->pluck('type')
                        ->unique()
                        ->values()
                ];
            })
            ->toArray();
    }

    /**
     * Générer des recommandations
     */
    protected function generateRecommendations($anomalies): array
    {
        $recommendations = [];

        // Recommandations pour les boucles hiérarchiques
        if ($anomalies->where('type', MLMCleaningAnomaly::TYPE_HIERARCHY_LOOP)->count() > 0) {
            $recommendations[] = [
                'priority' => 'critical',
                'type' => 'hierarchy',
                'message' => 'Des boucles ont été détectées dans la hiérarchie. Une révision manuelle est nécessaire.',
                'action' => 'Vérifier et corriger manuellement les relations parent-enfant'
            ];
        }

        // Recommandations pour les cumuls
        $cumulIssues = $anomalies->whereIn('type', [
            MLMCleaningAnomaly::TYPE_CUMUL_INDIVIDUAL_NEGATIVE,
            MLMCleaningAnomaly::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL
        ])->count();

        if ($cumulIssues > 10) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'cumuls',
                'message' => "Un grand nombre d'anomalies de cumuls ({$cumulIssues}) a été détecté.",
                'action' => 'Envisager une recalculation complète des cumuls pour la période concernée'
            ];
        }

        // Recommandations pour les grades
        $gradeIssues = $anomalies->where('type', MLMCleaningAnomaly::TYPE_GRADE_CONDITIONS_NOT_MET)->count();
        if ($gradeIssues > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'grades',
                'message' => "{$gradeIssues} distributeurs ont des grades ne correspondant pas aux conditions.",
                'action' => 'Lancer une recalculation automatique des grades après correction des cumuls'
            ];
        }

        return $recommendations;
    }

    /**
     * Obtenir la répartition des corrections
     */
    protected function getCorrectionsBreakdown($fixedAnomalies): array
    {
        $breakdown = [];
        $grouped = $fixedAnomalies->groupBy('type');

        foreach ($grouped as $type => $anomalies) {
            $breakdown[$type] = [
                'count' => $anomalies->count(),
                'percentage' => round(($anomalies->count() / $fixedAnomalies->count()) * 100, 2),
                'label' => $this->getTypeLabel($type)
            ];
        }

        return $breakdown;
    }

    /**
     * Obtenir les changements par table
     */
    protected function getChangesByTable($logs): array
    {
        $grouped = $logs->groupBy('table_name');
        $changes = [];

        foreach ($grouped as $table => $tableLogs) {
            $changes[$table] = [
                'total_changes' => $tableLogs->count(),
                'by_action' => $tableLogs->groupBy('action')->map->count(),
                'by_field' => $tableLogs->groupBy('field_name')->map->count()
            ];
        }

        return $changes;
    }

    /**
     * Obtenir les métriques de performance
     */
    protected function getPerformanceMetrics(): array
    {
        $totalTime = $this->session->execution_time ?? 0;
        $recordsPerSecond = $totalTime > 0
            ? round($this->session->records_analyzed / $totalTime, 2)
            : 0;

        return [
            'total_execution_time' => $this->session->getExecutionTimeFormatted(),
            'records_per_second' => $recordsPerSecond,
            'memory_peak' => $this->getMemoryPeak(),
            'phases' => [
                'analysis' => $this->getPhaseDuration('analysis'),
                'correction' => $this->getPhaseDuration('correction'),
                'finalization' => $this->getPhaseDuration('finalization')
            ]
        ];
    }

    /**
     * Obtenir les problèmes non résolus
     */
    protected function getUnresolvedIssues(): array
    {
        $unresolved = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->where('is_fixed', false)
            ->get();

        return [
            'total' => $unresolved->count(),
            'by_severity' => $unresolved->groupBy('severity')->map->count(),
            'by_type' => $unresolved->groupBy('type')->map->count(),
            'critical' => $unresolved->where('severity', MLMCleaningAnomaly::SEVERITY_CRITICAL)
                ->map(function ($anomaly) {
                    return [
                        'distributeur' => $anomaly->distributeur->nom_distributeur ?? 'N/A',
                        'type' => $anomaly->getTypeLabel(),
                        'description' => $anomaly->description
                    ];
                })
        ];
    }

    /**
     * Exporter le rapport en Excel
     */
    public function exportToExcel(?string $filename = null): string
    {
        $filename = $filename ?? 'mlm_cleaning_report_' . $this->session->session_code . '.xlsx';

        return Excel::download(new \App\Exports\MLMCleaningReportExport($this->session), $filename)->getFile();
    }

    /**
     * Exporter le rapport en PDF
     */
    public function exportToPdf(?string $filename = null): string
    {
        $filename = $filename ?? 'mlm_cleaning_report_' . $this->session->session_code . '.pdf';

        $data = [
            'session' => $this->session,
            'report' => $this->generateFinalReport()
        ];

        $pdf = PDF::loadView('admin.mlm-cleaning.report-pdf', $data);

        return $pdf->download($filename)->getFile();
    }

    /**
     * Obtenir le label d'un type
     */
    protected function getTypeLabel(string $type): string
    {
        return match($type) {
            MLMCleaningAnomaly::TYPE_HIERARCHY_LOOP => 'Boucle hiérarchique',
            MLMCleaningAnomaly::TYPE_ORPHAN_PARENT => 'Parent orphelin',
            MLMCleaningAnomaly::TYPE_CUMUL_INDIVIDUAL_NEGATIVE => 'Cumul individuel négatif',
            MLMCleaningAnomaly::TYPE_CUMUL_COLLECTIVE_LESS_THAN_INDIVIDUAL => 'Cumul collectif invalide',
            MLMCleaningAnomaly::TYPE_CUMUL_DECREASE => 'Diminution de cumul',
            MLMCleaningAnomaly::TYPE_GRADE_REGRESSION => 'Régression de grade',
            MLMCleaningAnomaly::TYPE_GRADE_SKIP => 'Saut de grade',
            MLMCleaningAnomaly::TYPE_GRADE_CONDITIONS_NOT_MET => 'Conditions de grade non remplies',
            MLMCleaningAnomaly::TYPE_MISSING_PERIOD => 'Période manquante',
            MLMCleaningAnomaly::TYPE_DUPLICATE_PERIOD => 'Période dupliquée',
            default => 'Autre'
        };
    }

    /**
     * Obtenir le pic mémoire
     */
    protected function getMemoryPeak(): string
    {
        $bytes = memory_get_peak_usage(true);

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } else {
            return number_format($bytes / 1024, 2) . ' KB';
        }
    }

    /**
     * Obtenir la durée d'une phase
     */
    protected function getPhaseDuration(string $phase): string
    {
        // Implémentation simplifiée - à adapter selon vos besoins
        return 'N/A';
    }
}
