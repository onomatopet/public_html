<?php

namespace App\Exports;

use App\Models\Achat;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AchatsExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Achat::query()
            ->with(['distributeur', 'product', 'session']);

        // Filtres
        if (!empty($this->filters['period'])) {
            $query->where('period', $this->filters['period']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['distributeur_id'])) {
            $query->where('distributeur_id', $this->filters['distributeur_id']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('purchase_date', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->whereDate('purchase_date', '<=', $this->filters['date_to']);
        }

        return $query->orderBy('purchase_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Période',
            'Date achat',
            'Distributeur',
            'Produit',
            'Code produit',
            'Quantité',
            'Prix unitaire',
            'Points unitaire',
            'Total ligne',
            'Total points',
            'Statut',
            'Session',
            'Créé le'
        ];
    }

    public function map($achat): array
    {
        return [
            $achat->id,
            $achat->period,
            $achat->purchase_date,
            $achat->distributeur ? $achat->distributeur->distributeur_id . ' - ' . $achat->distributeur->nom_distributeur : '',
            $achat->product ? $achat->product->nom_produit : '',
            $achat->product ? $achat->product->code_product : '',
            $achat->qt,
            number_format($achat->prix_unitaire_achat, 2, ',', ' '),
            $achat->points_unitaire_achat,
            number_format($achat->montant_total_ligne, 2, ',', ' '),
            $achat->points_total_ligne,
            $this->getStatusLabel($achat->status),
            $achat->session ? $achat->session->session_code : '',
            $achat->created_at->format('Y-m-d H:i:s')
        ];
    }

    protected function getStatusLabel($status): string
    {
        return match($status) {
            'pending' => 'En attente',
            'validated' => 'Validé',
            'delivered' => 'Livré',
            'cancelled' => 'Annulé',
            'refunded' => 'Remboursé',
            default => $status
        };
    }

    public function title(): string
    {
        return 'Achats';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A1:N1' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ]
            ],
        ];
    }
}
