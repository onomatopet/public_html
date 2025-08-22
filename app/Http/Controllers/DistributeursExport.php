<?php

namespace App\Exports;

use App\Models\Distributeur;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Builder;

class DistributeursExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Distributeur::query()->with(['parent']);

        // Appliquer les filtres
        if (!empty($this->filters['grade'])) {
            $query->where('etoiles_id', $this->filters['grade']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('statut_validation_periode', $this->filters['status']);
        }

        if (!empty($this->filters['created_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['created_from']);
        }

        if (!empty($this->filters['created_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['created_to']);
        }

        return $query->orderBy('distributeur_id');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Matricule',
            'Nom',
            'Prénom',
            'Email',
            'Téléphone',
            'Adresse',
            'Parent (Matricule)',
            'Grade',
            'Statut',
            'Date création',
            'Dernière activité'
        ];
    }

    public function map($distributeur): array
    {
        return [
            $distributeur->id,
            $distributeur->distributeur_id,
            $distributeur->nom_distributeur,
            $distributeur->pnom_distributeur,
            $distributeur->mail_distributeur,
            $distributeur->tel_distributeur,
            $distributeur->adress_distributeur,
            $distributeur->parent ? $distributeur->parent->distributeur_id : '',
            $distributeur->etoiles_id,
            $distributeur->statut_validation_periode ? 'Actif' : 'Inactif',
            $distributeur->created_at->format('Y-m-d'),
            $distributeur->updated_at->format('Y-m-d H:i:s')
        ];
    }

    public function title(): string
    {
        return 'Distributeurs';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // En-têtes en gras
            1 => ['font' => ['bold' => true]],

            // Couleur de fond pour les en-têtes
            'A1:L1' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ]
            ],
        ];
    }
}
