<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class StokSheet extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Stok Saat Ini';
    }

    public function headings(): array
    {
        return ['Barcode', 'Nama Produk', 'Vendor', 'Stok'];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Kolom A = barcode: paksa string supaya leading zero tidak hilang (DEC-06).
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function collection(): Collection
    {
        return Product::query()
            ->with('vendor')
            ->orderBy('nama_produk')
            ->get()
            ->map(fn (Product $p) => [
                $p->barcode,
                $p->nama_produk,
                $p->vendor?->nama ?? '-',
                $p->stok_sekarang,
            ]);
    }
}
