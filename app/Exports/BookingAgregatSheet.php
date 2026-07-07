<?php

namespace App\Exports;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Product;
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

class BookingAgregatSheet extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithHeadings, WithTitle
{
    public function __construct(
        private CarbonImmutable $tanggal,
    ) {}

    public function title(): string
    {
        return 'Agregat per Produk';
    }

    public function headings(): array
    {
        return ['Barcode', 'Nama Produk', 'Qty Ter-booking', 'Qty Keluar Ledger', 'Selisih'];
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
        // Booking void tidak dihitung — barang dianggap kembali ke rak.
        $terbooking = BookingItem::query()
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->whereDate('bookings.created_at', $this->tanggal)
            ->where('bookings.status', '!=', Booking::STATUS_VOID)
            ->groupBy('booking_items.product_id')
            ->selectRaw('booking_items.product_id, SUM(booking_items.qty) as total')
            ->pluck('total', 'product_id');

        $keluarLedger = StockMovement::query()
            ->where('tipe', 'out')
            ->whereDate('created_at', $this->tanggal)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(qty) as total')
            ->pluck('total', 'product_id');

        $productIds = $terbooking->keys()->merge($keluarLedger->keys())->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return $productIds
            ->map(function ($productId) use ($products, $terbooking, $keluarLedger) {
                $booked = (int) ($terbooking[$productId] ?? 0);
                $out = (int) ($keluarLedger[$productId] ?? 0);

                return [
                    $products[$productId]->barcode ?? '-',
                    $products[$productId]->nama_produk ?? '(produk terhapus)',
                    $booked,
                    $out,
                    $booked - $out,
                ];
            })
            ->sortBy(fn (array $row) => $row[1], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
