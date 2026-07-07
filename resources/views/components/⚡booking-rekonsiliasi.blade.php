<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Livewire\Component;

new class extends Component
{
    /** Filter tanggal (Y-m-d), default hari ini (WIB via app timezone). */
    public string $tanggal = '';

    /** Draft catatan keeper per booking id (prefill dari catatan_keeper tersimpan). */
    public array $catatan = [];

    public function boot(): void
    {
        // FR-BOOK-04: hanya admin + operator (store keeper); SPG 403 (pola Fase 9).
        abort_unless(in_array(auth()->user()?->role, ['admin', 'operator'], true), 403);
    }

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
    }

    /**
     * Tandai hasil rekonsiliasi per booking: checked_ok / checked_selisih + catatan keeper.
     * Booking void tidak direkonsiliasi (barang dianggap kembali ke rak).
     */
    public function tandai(int $bookingId, string $status): void
    {
        $this->resetErrorBag('tandai');

        abort_unless(in_array($status, [Booking::STATUS_CHECKED_OK, Booking::STATUS_CHECKED_SELISIH], true), 400);

        $booking = Booking::find($bookingId);

        if (! $booking) {
            return;
        }

        if ($booking->status === Booking::STATUS_VOID) {
            $this->addError('tandai', "Booking {$booking->booking_code} sudah di-void — tidak ikut rekonsiliasi.");

            return;
        }

        $booking->update([
            'status' => $status,
            'catatan_keeper' => trim($this->catatan[$booking->id] ?? '') ?: null,
        ]);
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            Booking::STATUS_PRINTED => 'Tercetak',
            Booking::STATUS_VOID => 'Void',
            Booking::STATUS_CHECKED_OK => 'Dicek OK',
            Booking::STATUS_CHECKED_SELISIH => 'Dicek Selisih',
            default => $status,
        };
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            Booking::STATUS_PRINTED => 'badge bg-black/10 text-black/70',
            Booking::STATUS_VOID => 'badge-out',
            Booking::STATUS_CHECKED_OK => 'badge-in',
            Booking::STATUS_CHECKED_SELISIH => 'badge bg-amber-100 text-amber-700',
            default => 'badge bg-black/10 text-black/70',
        };
    }

    public function with(): array
    {
        try {
            $tanggal = Carbon::createFromFormat('Y-m-d', $this->tanggal)->toDateString();
        } catch (\Throwable) {
            $tanggal = today()->toDateString();
        }

        // Agregat per produk (FR-BOOK-04): qty ter-booking (non-void — void = barang
        // kembali ke rak) vs qty keluar ledger `stock_movements` tipe out hari itu.
        $terbooking = BookingItem::query()
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->whereDate('bookings.created_at', $tanggal)
            ->where('bookings.status', '!=', Booking::STATUS_VOID)
            ->groupBy('booking_items.product_id')
            ->selectRaw('booking_items.product_id, SUM(booking_items.qty) as total')
            ->pluck('total', 'product_id');

        $keluarLedger = StockMovement::query()
            ->where('tipe', 'out')
            ->whereDate('created_at', $tanggal)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(qty) as total')
            ->pluck('total', 'product_id');

        $productIds = $terbooking->keys()->merge($keluarLedger->keys())->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $agregat = $productIds
            ->map(function ($productId) use ($products, $terbooking, $keluarLedger) {
                $booked = (int) ($terbooking[$productId] ?? 0);
                $out = (int) ($keluarLedger[$productId] ?? 0);

                return [
                    'barcode' => $products[$productId]->barcode ?? '-',
                    'nama' => $products[$productId]->nama_produk ?? '(produk terhapus)',
                    'terbooking' => $booked,
                    'keluar' => $out,
                    'selisih' => $booked - $out,
                ];
            })
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $bookings = Booking::with('items.product', 'user')
            ->whereDate('created_at', $tanggal)
            ->latest('id')
            ->get();

        foreach ($bookings as $booking) {
            $this->catatan[$booking->id] ??= $booking->catatan_keeper ?? '';
        }

        return [
            'agregat' => $agregat,
            'bookings' => $bookings,
            'tanggalAktif' => $tanggal,
        ];
    }
}; ?>

<div class="page max-w-3xl!">
    <h1 class="page-title"><span class="pokeball-dot"></span> Rekonsiliasi Booking</h1>

    <div class="card">
        <div class="flex flex-wrap items-end gap-3">
            <div class="field mb-0! flex-1">
                <label for="tanggal">Tanggal</label>
                <input type="date" id="tanggal" wire:model.live="tanggal" max="{{ today()->toDateString() }}">
            </div>
            <a href="{{ route('booking.rekonsiliasi.export', ['tanggal' => $tanggalAktif]) }}" class="btn-secondary">
                Export Excel
            </a>
        </div>
        <p class="mt-2 text-xs text-black/50">
            Bandingkan agregat di bawah dengan data POS &amp; stok fisik rak. Booking void tidak dihitung (barang kembali ke rak).
        </p>
    </div>

    <section class="card">
        <h3 class="mb-3 text-lg font-bold">Agregat per Produk</h3>
        <div class="table-wrap responsive-cards">
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Ter-booking</th>
                        <th>Keluar Ledger</th>
                        <th>Selisih</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agregat as $row)
                        <tr wire:key="agregat-{{ $row['barcode'] }}">
                            <td data-label="Produk">
                                {{ $row['nama'] }}
                                <span class="block font-mono text-xs text-black/50">{{ $row['barcode'] }}</span>
                            </td>
                            <td data-label="Ter-booking" class="tabular-nums">{{ $row['terbooking'] }}</td>
                            <td data-label="Keluar Ledger" class="tabular-nums">{{ $row['keluar'] }}</td>
                            <td data-label="Selisih" class="tabular-nums {{ $row['selisih'] === 0 ? 'text-emerald-600' : 'font-bold text-poke-red' }}">{{ $row['selisih'] > 0 ? '+' : '' }}{{ $row['selisih'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Tidak ada booking maupun barang keluar pada tanggal ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-black/50">
            Selisih = ter-booking − keluar ledger. Positif: ada booking tanpa scan keluar gudang; negatif: barang keluar tanpa booking.
        </p>
    </section>

    <section class="mt-4">
        <h3 class="mb-1 text-lg font-bold">Daftar Booking</h3>

        @error('tandai') <p class="error mb-2">{{ $message }}</p> @enderror

        @if ($bookings->isEmpty())
            <div class="card">
                <p class="text-sm text-black/50">
                    Tidak ada booking pada {{ Illuminate\Support\Carbon::parse($tanggalAktif)->translatedFormat('d F Y') }}.
                </p>
            </div>
        @else
            @foreach ($bookings as $booking)
                <div class="card mt-3 {{ $booking->status === App\Models\Booking::STATUS_VOID ? 'opacity-60' : '' }}" wire:key="booking-{{ $booking->id }}">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl font-black tabular-nums">{{ $booking->nomorUrutPadded() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-mono text-sm font-semibold">{{ $booking->booking_code }}</span>
                            <span class="block text-xs text-black/50">
                                {{ $booking->created_at->format('H:i') }} WIB · {{ $booking->user?->name ?? '—' }}
                            </span>
                        </span>
                        <span class="{{ $this->statusClass($booking->status) }}">{{ $this->statusLabel($booking->status) }}</span>
                    </div>

                    <ul class="mt-2 divide-y divide-black/10 border-t border-black/10">
                        @foreach ($booking->items as $item)
                            <li class="flex items-center gap-2 py-1.5 text-sm" wire:key="item-{{ $item->id }}">
                                <span class="min-w-0 flex-1 truncate">{{ $item->product?->nama_produk ?? '(produk terhapus)' }}</span>
                                <span class="font-semibold tabular-nums">×{{ $item->qty }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-1 text-right text-xs font-bold text-black/60">Total {{ $booking->items->sum('qty') }} item</p>

                    @if ($booking->status !== App\Models\Booking::STATUS_VOID)
                        <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-black/10 pt-2">
                            <input
                                type="text"
                                class="min-w-0 flex-1"
                                placeholder="Catatan keeper (opsional)"
                                maxlength="255"
                                wire:model="catatan.{{ $booking->id }}"
                            >
                            <button
                                type="button"
                                class="btn-secondary btn-sm text-emerald-600"
                                wire:click="tandai({{ $booking->id }}, '{{ App\Models\Booking::STATUS_CHECKED_OK }}')"
                            >
                                Tandai OK
                            </button>
                            <button
                                type="button"
                                class="btn-secondary btn-sm text-poke-red"
                                wire:click="tandai({{ $booking->id }}, '{{ App\Models\Booking::STATUS_CHECKED_SELISIH }}')"
                            >
                                Tandai Selisih
                            </button>
                        </div>
                        @if ($booking->catatan_keeper)
                            <p class="mt-1 text-xs text-black/60">Catatan tersimpan: {{ $booking->catatan_keeper }}</p>
                        @endif
                    @endif
                </div>
            @endforeach
        @endif
    </section>
</div>
