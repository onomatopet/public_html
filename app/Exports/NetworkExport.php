<?php

namespace App\Exports;

use App\Models\Distributeur;
use App\Models\Level_current;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;

class NetworkExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected $distributeurId;
    protected $period;
    protected $networkData;

    public function __construct($distributeurId, $period)
    {
        $this->distributeurId = $distributeurId;
        $this->period = $period;
        $this->networkData = $this->getNetworkData();
    }

    public function collection()
    {
        return collect($this->networkData);
    }

    public function headings(): array
    {
        return [
            'ID Distributeur',
            'Niveau',
            'Nom',
            'Prénom',
            'Grade (Étoiles)',
            'New PV',
            'Total PV',
            'Cumul Collectif',
            'Cumul Individuel',
            'ID Parent',
            'Nom Parent',
            'Prénom Parent'
        ];
    }

    public function map($row): array
    {
        return [
            $row['distributeur_id'],
            $row['rang'],
            $row['nom_distributeur'],
            $row['pnom_distributeur'],
            $row['etoiles'],
            $row['new_cumul'],
            $row['cumul_total'],
            $row['cumul_collectif'],
            $row['cumul_individuel'],
            $row['id_distrib_parent'] ?: '-',
            $row['id_distrib_parent'] ? $row['nom_parent'] : '-',
            $row['id_distrib_parent'] ? $row['pnom_parent'] : '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style de l'en-tête
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A90E2']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ]
        ]);

        // Hauteur de la ligne d'en-tête
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Bordures pour toutes les cellules avec données
        $lastRow = count($this->networkData) + 1;
        $sheet->getStyle("A1:L{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);

        // Alignement des colonnes numériques
        $sheet->getStyle("E:I")->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
        );

        // Format nombre pour les colonnes PV
        $sheet->getStyle("F:I")->getNumberFormat()->setFormatCode('#,##0');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // ID Distributeur
            'B' => 10,  // Niveau
            'C' => 20,  // Nom
            'D' => 20,  // Prénom
            'E' => 15,  // Grade
            'F' => 12,  // New PV
            'G' => 12,  // Total PV
            'H' => 15,  // Cumul Collectif
            'I' => 15,  // Cumul Individuel
            'J' => 15,  // ID Parent
            'K' => 20,  // Nom Parent
            'L' => 20   // Prénom Parent
        ];
    }

    public function title(): string
    {
        return "Réseau {$this->distributeurId} - {$this->period}";
    }

    private function getNetworkData()
    {
        $network = [];
        $processedIds = [];
        $queue = [['id' => $this->distributeurId, 'level' => 0]];
        $limit = 5000;

        while (!empty($queue) && count($network) < $limit) {
            $current = array_shift($queue);
            $currentId = $current['id'];
            $currentLevel = $current['level'];

            if (in_array($currentId, $processedIds)) {
                continue;
            }
            $processedIds[] = $currentId;

            $data = DB::table('distributeurs as d')
                ->leftJoin('level_currents as lc', function($join) {
                    $join->on('d.distributeur_id', '=', 'lc.distributeur_id')
                         ->where('lc.period', '=', $this->period);
                })
                ->leftJoin('distributeurs as parent', 'd.id_distrib_parent', '=', 'parent.distributeur_id')
                ->where('d.distributeur_id', $currentId)
                ->select([
                    'd.distributeur_id',
                    'd.nom_distributeur',
                    'd.pnom_distributeur',
                    'd.id_distrib_parent',
                    'parent.nom_distributeur as nom_parent',
                    'parent.pnom_distributeur as pnom_parent',
                    'lc.etoiles',
                    'lc.new_cumul',
                    'lc.cumul_total',
                    'lc.cumul_collectif',
                    'lc.cumul_individuel'
                ])
                ->first();

            if ($data) {
                $network[] = [
                    'rang' => $currentLevel,
                    'distributeur_id' => $data->distributeur_id,
                    'nom_distributeur' => $data->nom_distributeur ?? 'N/A',
                    'pnom_distributeur' => $data->pnom_distributeur ?? 'N/A',
                    'etoiles' => $data->etoiles ?? 0,
                    'new_cumul' => $data->new_cumul ?? 0,
                    'cumul_total' => $data->cumul_total ?? 0,
                    'cumul_collectif' => $data->cumul_collectif ?? 0,
                    'cumul_individuel' => $data->cumul_individuel ?? 0,
                    'id_distrib_parent' => $data->id_distrib_parent,
                    'nom_parent' => $data->nom_parent ?? 'N/A',
                    'pnom_parent' => $data->pnom_parent ?? 'N/A',
                ];

                $children = DB::table('distributeurs')
                    ->where('id_distrib_parent', $currentId)
                    ->pluck('distributeur_id')
                    ->toArray();

                foreach ($children as $childId) {
                    $queue[] = ['id' => $childId, 'level' => $currentLevel + 1];
                }
            }
        }

        return $network;
    }
}
