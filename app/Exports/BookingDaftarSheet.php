<?php

namespace App\Exports;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class BookingDaftarSheet extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithHeadings, WithTitle
{
    public function __construct(
        private CarbonImmutable $tanggal,
    ) {}

    public function title(): string
    {
        return 'Daftar Booking';
    }

    public function headings(): array
    {
        return ['No Urut', 'Booking Code', 'Waktu (WIB)', 'SPG', 'Item', 'Total Qty', 'Status', 'Catatan Keeper'];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Kolom A = nomor urut "001": paksa string supaya leading zero tidak hilang (pola DEC-06).
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function collection(): Collection
    {
        return Booking::query()
            ->with('items.product', 'user')
            ->whereDate('created_at', $this->tanggal)
            ->orderBy('id')
            ->get()
            ->map(fn (Booking $booking) => [
                $booking->nomorUrutPadded(),
                $booking->booking_code,
                $booking->created_at->format('Y-m-d H:i:s'),
                $booking->user?->name ?? '-',
                $booking->items
                    ->map(fn ($item) => ($item->product?->nama_produk ?? '(produk terhapus)').' x'.$item->qty)
                    ->implode('; '),
                (int) $booking->items->sum('qty'),
                $booking->status,
                $booking->catatan_keeper ?? '',
            ]);
    }
}
