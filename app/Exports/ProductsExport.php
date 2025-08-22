<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Product::query()
            ->with(['category']);

        // Filtres
        if (!empty($this->filters['category_id'])) {
            $query->where('category_id', $this->filters['category_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('is_active', $this->filters['status'] === 'active');
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('nom_produit', 'like', "%{$search}%")
                  ->orWhere('code_product', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('code_product');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code produit',
            'Nom produit',
            'Description',
            'Catégorie',
            'Prix HT',
            'Prix TTC',
            'Points',
            'Stock',
            'Stock minimum',
            'Statut',
            'Date création',
            'Dernière modification'
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->code_product,
            $product->nom_produit,
            $product->description ?? '',
            $product->category ? $product->category->name : '',
            number_format($product->prix_ht, 2, ',', ' '),
            number_format($product->prix_product, 2, ',', ' '),
            $product->point_product,
            $product->stock_quantity ?? 0,
            $product->min_stock ?? 0,
            $product->is_active ? 'Actif' : 'Inactif',
            $product->created_at->format('Y-m-d'),
            $product->updated_at->format('Y-m-d H:i:s')
        ];
    }

    public function title(): string
    {
        return 'Produits';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A1:M1' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ]
            ],
        ];
    }
}
