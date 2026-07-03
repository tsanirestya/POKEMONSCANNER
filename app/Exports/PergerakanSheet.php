<?php

namespace App\Exports;

use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class PergerakanSheet extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithHeadings, WithTitle
{
    public function __construct(
        private CarbonImmutable $dari,
        private CarbonImmutable $sampai,
    ) {}

    public function title(): string
    {
        return 'Pergerakan';
    }

    public function headings(): array
    {
        return ['Waktu', 'Barcode', 'Nama Produk', 'Tipe', 'Qty', 'Metode', 'Alasan', 'User'];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Kolom B = barcode: paksa string supaya leading zero tidak hilang (DEC-06).
        if ($cell->getColumn() === 'B') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function collection(): Collection
    {
        return StockMovement::query()
            ->with(['product', 'user'])
            ->whereBetween('created_at', [$this->dari->startOfDay(), $this->sampai->endOfDay()])
            ->orderBy('created_at')
            ->get()
            ->map(fn (StockMovement $m) => [
                $m->created_at->format('Y-m-d H:i:s'),
                $m->product?->barcode ?? '-',
                $m->product?->nama_produk ?? '(produk terhapus)',
                $m->tipe === 'in' ? 'Masuk' : 'Keluar',
                $m->qty,
                $m->metode,
                $m->alasan ?? '',
                $m->user?->name ?? '-',
            ]);
    }
}
