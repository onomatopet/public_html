<?php

namespace App\Exports;

use App\Models\Bonus;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BonusExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Bonus::query()
            ->with(['distributeur', 'bonus_type']);

        // Filtres
        if (!empty($this->filters['period'])) {
            $query->where('period', $this->filters['period']);
        }

        if (!empty($this->filters['type_bonus'])) {
            $query->where('type_bonus', $this->filters['type_bonus']);
        }

        if (!empty($this->filters['distributeur_id'])) {
            $query->where('distributeur_id', $this->filters['distributeur_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Période',
            'Distributeur',
            'Type de bonus',
            'Montant',
            'Points',
            'Taux (%)',
            'Source',
            'Statut',
            'Date validation',
            'Date paiement',
            'Créé le'
        ];
    }

    public function map($bonus): array
    {
        return [
            $bonus->id,
            $bonus->period,
            $bonus->distributeur ? $bonus->distributeur->distributeur_id . ' - ' . $bonus->distributeur->nom_distributeur : '',
            $this->getBonusTypeLabel($bonus->type_bonus),
            number_format($bonus->montant, 2, ',', ' ') . ' €',
            $bonus->points ?? 0,
            $bonus->taux ? number_format($bonus->taux, 2, ',', ' ') . '%' : '',
            $bonus->source_distributeur_id ? 'Dist. ' . $bonus->source_distributeur_id : '',
            $this->getStatusLabel($bonus->status),
            $bonus->validated_at ? $bonus->validated_at->format('Y-m-d') : '',
            $bonus->paid_at ? $bonus->paid_at->format('Y-m-d') : '',
            $bonus->created_at->format('Y-m-d H:i:s')
        ];
    }

    protected function getBonusTypeLabel($type): string
    {
        return match($type) {
            'direct' => 'Bonus direct',
            'indirect' => 'Bonus indirect',
            'leadership' => 'Bonus leadership',
            'rank' => 'Bonus de grade',
            'special' => 'Bonus spécial',
            default => $type
        };
    }

    protected function getStatusLabel($status): string
    {
        return match($status) {
            'pending' => 'En attente',
            'validated' => 'Validé',
            'paid' => 'Payé',
            'cancelled' => 'Annulé',
            default => $status
        };
    }

    public function title(): string
    {
        return 'Bonus';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A1:L1' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ]
            ],
        ];
    }
}
