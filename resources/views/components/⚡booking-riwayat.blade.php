<?php

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Livewire\Component;

new class extends Component
{
    /** Filter tanggal (Y-m-d), default hari ini (WIB via app timezone). */
    public string $tanggal = '';

    public function boot(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'operator', 'spg'], true), 403);
    }

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
    }

    /**
     * Void booking (FR-BOOK-03): ubah status, bukan hapus — jejak audit tetap ada.
     * Hanya status `printed` yang bisa di-void; SPG hanya miliknya sendiri.
     */
    public function void(int $bookingId): void
    {
        $this->resetErrorBag('void');

        $booking = Booking::find($bookingId);

        if (! $booking) {
            return;
        }

        abort_if(auth()->user()->isSpg() && $booking->user_id !== auth()->id(), 403);

        if ($booking->status !== Booking::STATUS_PRINTED) {
            $this->addError('void', "Booking {$booking->booking_code} tidak bisa di-void — status sudah \"{$this->statusLabel($booking->status)}\".");

            return;
        }

        $booking->update(['status' => Booking::STATUS_VOID]);
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

        $bookings = Booking::with('items.product', 'user')
            ->whereDate('created_at', $tanggal)
            // SPG hanya melihat booking miliknya; admin/operator (store keeper) melihat semua.
            ->when(auth()->user()->isSpg(), fn ($query) => $query->where('user_id', auth()->id()))
            ->latest('id')
            ->get();

        return [
            'bookings' => $bookings,
            'tanggalAktif' => $tanggal,
        ];
    }
}; ?>

<div class="page max-w-lg!">
    <h1 class="page-title"><span class="pokeball-dot"></span> Riwayat Booking</h1>

    <div class="card">
        <div class="field mb-0!">
            <label for="tanggal">Tanggal</label>
            <input type="date" id="tanggal" wire:model.live="tanggal" max="{{ today()->toDateString() }}">
        </div>
    </div>

    @error('void') <p class="error mt-3">{{ $message }}</p> @enderror

    @if ($bookings->isEmpty())
        <div class="card mt-4">
            <p class="text-sm text-black/50">
                Tidak ada booking pada {{ Illuminate\Support\Carbon::parse($tanggalAktif)->translatedFormat('d F Y') }}.
            </p>
        </div>
    @else
        @foreach ($bookings as $booking)
            <div class="card mt-4 {{ $booking->status === App\Models\Booking::STATUS_VOID ? 'opacity-60' : '' }}" wire:key="booking-{{ $booking->id }}">
                <div class="flex items-center gap-3">
                    <span class="text-3xl font-black tabular-nums">{{ $booking->nomorUrutPadded() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-mono text-sm font-semibold">{{ $booking->booking_code }}</span>
                        <span class="block text-xs text-black/50">
                            {{ $booking->created_at->format('H:i') }} WIB
                            @unless (auth()->user()->isSpg())
                                · {{ $booking->user?->name ?? '—' }}
                            @endunless
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

                <div class="mt-2 flex items-center justify-end gap-2">
                    @if ($booking->status !== App\Models\Booking::STATUS_VOID)
                        <a href="{{ route('booking.struk', $booking) }}" class="btn-secondary btn-sm">Cetak ulang</a>
                    @endif

                    @if ($booking->status === App\Models\Booking::STATUS_PRINTED)
                        <button
                            type="button"
                            class="btn-secondary btn-sm text-poke-red"
                            wire:click="void({{ $booking->id }})"
                            wire:confirm="Void booking {{ $booking->booking_code }}? Pakai ini kalau customer batal sebelum ke kasir. Status berubah jadi Void, data tidak dihapus."
                        >
                            Void
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
