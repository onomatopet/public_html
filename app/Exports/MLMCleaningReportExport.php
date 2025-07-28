<?php

namespace App\Exports;

use App\Models\MLMCleaningSession;
use App\Models\MLMCleaningAnomaly;
use App\Models\MLMCleaningLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MLMCleaningReportExport implements WithMultipleSheets
{
    protected MLMCleaningSession $session;

    public function __construct(MLMCleaningSession $session)
    {
        $this->session = $session;
    }

    public function sheets(): array
    {
        return [
            new SummarySheet($this->session),
            new AnomaliesSheet($this->session),
            new CorrectionsSheet($this->session),
            new StatisticsSheet($this->session)
        ];
    }
}

class SummarySheet implements FromCollection, WithTitle, WithHeadings, ShouldAutoSize, WithStyles
{
    protected MLMCleaningSession $session;

    public function __construct(MLMCleaningSession $session)
    {
        $this->session = $session;
    }

    public function collection()
    {
        return collect([
            ['Code Session', $this->session->session_code],
            ['Type', $this->session->type],
            ['Statut', $this->session->status],
            ['Créé par', $this->session->creator->name ?? 'Système'],
            ['Date de création', $this->session->created_at->format('d/m/Y H:i')],
            [''],
            ['Statistiques', ''],
            ['Enregistrements analysés', number_format($this->session->records_analyzed)],
            ['Anomalies détectées', number_format($this->session->records_with_anomalies)],
            ['Corrections appliquées', number_format($this->session->records_corrected)],
            ['Problèmes de hiérarchie', number_format($this->session->hierarchy_issues)],
            ['Problèmes de cumuls', number_format($this->session->cumul_issues)],
            ['Problèmes de grades', number_format($this->session->grade_issues)],
            [''],
            ['Temps d\'exécution', $this->session->getExecutionTimeFormatted()]
        ]);
    }

    public function headings(): array
    {
        return ['Paramètre', 'Valeur'];
    }

    public function title(): string
    {
        return 'Résumé';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            7 => ['font' => ['bold' => true]],
        ];
    }
}

class AnomaliesSheet implements FromCollection, WithTitle, WithHeadings, WithMapping, ShouldAutoSize
{
    protected MLMCleaningSession $session;

    public function __construct(MLMCleaningSession $session)
    {
        $this->session = $session;
    }

    public function collection()
    {
        return MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->with('distributeur')
            ->get();
    }

    public function map($anomaly): array
    {
        return [
            $anomaly->id,
            $anomaly->distributeur->distributeur_id ?? 'N/A',
            $anomaly->distributeur->nom_distributeur ?? 'N/A',
            $anomaly->period,
            $anomaly->getTypeLabel(),
            $anomaly->getSeverityLabel(),
            $anomaly->description,
            $anomaly->field_name,
            $anomaly->current_value,
            $anomaly->expected_value,
            $anomaly->can_auto_fix ? 'Oui' : 'Non',
            $anomaly->is_fixed ? 'Oui' : 'Non',
            $anomaly->detected_at->format('d/m/Y H:i'),
            $anomaly->fixed_at ? $anomaly->fixed_at->format('d/m/Y H:i') : ''
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Matricule',
            'Distributeur',
            'Période',
            'Type',
            'Sévérité',
            'Description',
            'Champ',
            'Valeur actuelle',
            'Valeur attendue',
            'Auto-correction',
            'Corrigé',
            'Détecté le',
            'Corrigé le'
        ];
    }

    public function title(): string
    {
        return 'Anomalies';
    }
}

class CorrectionsSheet implements FromCollection, WithTitle, WithHeadings, WithMapping, ShouldAutoSize
{
    protected MLMCleaningSession $session;

    public function __construct(MLMCleaningSession $session)
    {
        $this->session = $session;
    }

    public function collection()
    {
        return MLMCleaningLog::where('session_id', $this->session->id)
            ->with('distributeur')
            ->get();
    }

    public function map($log): array
    {
        return [
            $log->id,
            $log->distributeur->distributeur_id ?? 'N/A',
            $log->distributeur->nom_distributeur ?? 'N/A',
            $log->period,
            $log->table_name,
            $log->field_name,
            $log->old_value,
            $log->new_value,
            $log->getActionLabel(),
            $log->reason,
            $log->applied_at->format('d/m/Y H:i')
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Matricule',
            'Distributeur',
            'Période',
            'Table',
            'Champ',
            'Ancienne valeur',
            'Nouvelle valeur',
            'Action',
            'Raison',
            'Appliqué le'
        ];
    }

    public function title(): string
    {
        return 'Corrections';
    }
}

class StatisticsSheet implements FromCollection, WithTitle, WithHeadings, ShouldAutoSize
{
    protected MLMCleaningSession $session;

    public function __construct(MLMCleaningSession $session)
    {
        $this->session = $session;
    }

    public function collection()
    {
        $anomaliesByType = MLMCleaningAnomaly::where('session_id', $this->session->id)
            ->selectRaw('type, severity, COUNT(*) as count')
            ->groupBy('type', 'severity')
            ->get();

        $data = [];
        foreach ($anomaliesByType as $stat) {
            $data[] = [
                'Type' => $this->getTypeLabel($stat->type),
                'Sévérité' => $stat->severity,
                'Nombre' => $stat->count
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return ['Type d\'anomalie', 'Sévérité', 'Nombre'];
    }

    public function title(): string
    {
        return 'Statistiques';
    }

    protected function getTypeLabel(string $type): string
    {
        return match($type) {
            'hierarchy_loop' => 'Boucle hiérarchique',
            'orphan_parent' => 'Parent orphelin',
            'cumul_individual_negative' => 'Cumul individuel négatif',
            'cumul_collective_less_than_individual' => 'Cumul collectif invalide',
            'cumul_decrease' => 'Diminution de cumul',
            'grade_regression' => 'Régression de grade',
            'grade_skip' => 'Saut de grade',
            'grade_conditions_not_met' => 'Conditions de grade non remplies',
            'missing_period' => 'Période manquante',
            'duplicate_period' => 'Période dupliquée',
            default => 'Autre'
        };
    }
}
