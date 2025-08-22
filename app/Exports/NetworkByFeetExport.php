<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class NetworkByFeetExport implements WithMultipleSheets
{
    protected $networkData;

    public function __construct(array $networkData)
    {
        $this->networkData = $networkData;
    }

    /**
     * Retourne un array de sheets
     */
    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Résumé
        $sheets[] = new NetworkSummarySheet($this->networkData);

        // Sheet 2: Vue d'ensemble par pieds
        $sheets[] = new NetworkOverviewSheet($this->networkData);

        // Sheets suivants: Un sheet par pied
        foreach ($this->networkData['feet'] as $index => $foot) {
            $sheets[] = new FootSheet($foot, $index + 1, $this->networkData['period']);
        }

        return $sheets;
    }
}

/**
 * Sheet de résumé
 */
class NetworkSummarySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected $networkData;

    public function __construct($networkData)
    {
        $this->networkData = $networkData;
    }

    public function collection()
    {
        $data = collect();

        // Informations principales
        $data->push([
            'Distributeur principal',
            $this->networkData['main_distributor']['distributeur_id'],
            $this->networkData['main_distributor']['nom_distributeur'],
            $this->networkData['main_distributor']['pnom_distributeur']
        ]);

        $data->push(['Période', $this->networkData['period'], '', '']);
        $data->push(['Nombre de pieds', $this->networkData['total_feet'], '', '']);
        $data->push(['Total réseau', $this->networkData['total_network'], '', '']);
        $data->push(['', '', '', '']); // Ligne vide

        // Résumé par pied
        $data->push(['RÉSUMÉ PAR PIED', '', '', '']);
        $data->push(['Pied', 'Chef de pied', 'Membres', 'Total PV']);

        foreach ($this->networkData['feet'] as $foot) {
            $totalPv = collect($foot['members'])->sum('new_cumul');
            $data->push([
                'Pied ' . $foot['foot_number'],
                $foot['foot_leader']['nom'],
                $foot['total_members'],
                $totalPv
            ]);
        }

        return $data;
    }

    public function headings(): array
    {
        return ['Information', 'Valeur', 'Détail 1', 'Détail 2'];
    }

    public function title(): string
    {
        return 'Résumé';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            'A6' => ['font' => ['bold' => true, 'size' => 12]],
            'A7' => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 20,
            'C' => 30,
            'D' => 20,
        ];
    }
}

/**
 * Sheet vue d'ensemble
 */
class NetworkOverviewSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $networkData;

    public function __construct($networkData)
    {
        $this->networkData = $networkData;
    }

    public function collection()
    {
        $data = collect();

        // Ajouter le distributeur principal
        $main = $this->networkData['main_distributor'];
        $data->push([
            0,
            $main['distributeur_id'],
            $main['nom_distributeur'],
            $main['pnom_distributeur'],
            $main['etoiles'],
            $main['new_cumul'],
            $main['cumul_total'],
            $main['cumul_collectif'],
            '-',
            'PRINCIPAL'
        ]);

        // Ajouter tous les membres par pied
        foreach ($this->networkData['feet'] as $foot) {
            // Ligne de séparation pour le pied
            $data->push([
                '',
                '',
                'PIED ' . $foot['foot_number'],
                $foot['foot_leader']['nom'],
                '',
                '',
                '',
                '',
                '',
                'DÉBUT PIED'
            ]);

            // Membres du pied
            foreach ($foot['members'] as $member) {
                $data->push([
                    $member['niveau'],
                    $member['distributeur_id'],
                    $member['nom_distributeur'],
                    $member['pnom_distributeur'],
                    $member['etoiles'],
                    $member['new_cumul'],
                    $member['cumul_total'],
                    $member['cumul_collectif'],
                    $member['parent_matricule'] ?? '-',
                    'Pied ' . $foot['foot_number']
                ]);
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Niveau',
            'Matricule',
            'Nom',
            'Prénom',
            'Grade',
            'PV Mois',
            'Cumul Total',
            'Cumul Collectif',
            'Parent',
            'Pied'
        ];
    }

    public function title(): string
    {
        return 'Vue d\'ensemble';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 15,
            'C' => 25,
            'D' => 25,
            'E' => 10,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 15,
            'J' => 15,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Colorer les lignes de séparation des pieds
                $rowCount = $sheet->getHighestRow();
                for ($row = 1; $row <= $rowCount; $row++) {
                    $cellValue = $sheet->getCell('J' . $row)->getValue();
                    if ($cellValue === 'DÉBUT PIED') {
                        $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => 'E0E0E0']
                            ],
                            'font' => ['bold' => true]
                        ]);
                    }
                }
            },
        ];
    }
}

/**
 * Sheet pour chaque pied
 */
class FootSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithMapping
{
    protected $foot;
    protected $footNumber;
    protected $period;

    public function __construct($foot, $footNumber, $period)
    {
        $this->foot = $foot;
        $this->footNumber = $footNumber;
        $this->period = $period;
    }

    public function collection()
    {
        return collect($this->foot['members']);
    }

    public function map($member): array
    {
        // Indentation visuelle selon le niveau
        $indent = str_repeat('  ', $member['niveau'] - 1);

        return [
            $member['niveau'],
            $member['distributeur_id'],
            $indent . $member['nom_distributeur'],
            $member['pnom_distributeur'],
            $member['etoiles'],
            number_format($member['new_cumul'], 0, ',', ' '),
            number_format($member['cumul_total'], 0, ',', ' '),
            number_format($member['cumul_collectif'], 0, ',', ' '),
            number_format($member['cumul_individuel'], 0, ',', ' '),
            $member['parent_matricule'] ?? '-'
        ];
    }

    public function headings(): array
    {
        return [
            'Niveau',
            'Matricule',
            'Nom',
            'Prénom',
            'Grade',
            'PV Mois',
            'Cumul Total',
            'Cumul Collectif',
            'Cumul Individuel',
            'Parent'
        ];
    }

    public function title(): string
    {
        return 'Pied ' . $this->footNumber;
    }

    public function styles(Worksheet $sheet)
    {
        $styles = [
            1 => ['font' => ['bold' => true]],
        ];

        // Style pour le chef de pied (première ligne de données)
        $styles[2] = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'F0F0F0']
            ]
        ];

        return $styles;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 15,
            'C' => 30,
            'D' => 25,
            'E' => 10,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 15,
            'J' => 15,
        ];
    }
}
