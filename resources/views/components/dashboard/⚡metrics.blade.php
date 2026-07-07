<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $range = '7d';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    protected function rangeDays(): int
    {
        return match ($this->range) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
    }

    public function with(): array
    {
        $days = $this->rangeDays();
        $from = Carbon::today()->subDays($days - 1);

        $chartRows = StockMovement::query()
            ->select([
                DB::raw('DATE(created_at) as tanggal'),
                'tipe',
                DB::raw('SUM(qty) as total'),
            ])
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->groupBy('tanggal', 'tipe')
            ->get();

        $chart = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $chart[$date] = ['tanggal' => $date, 'in' => 0, 'out' => 0];
        }
        foreach ($chartRows as $row) {
            $chart[$row->tanggal][$row->tipe] = (int) $row->total;
        }
        $chart = array_values($chart);
        $maxQty = max(1, collect($chart)->flatMap(fn ($r) => [$r['in'], $r['out']])->max());

        $topOut = StockMovement::query()
            ->select('product_id', DB::raw('SUM(qty) as total_keluar'))
            ->where('tipe', 'out')
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->groupBy('product_id')
            ->orderByDesc('total_keluar')
            ->with('product')
            ->limit(10)
            ->get();

        return [
            'totalProduk' => Product::count(),
            'totalStok' => (int) Product::sum('stok_sekarang'),
            'inHariIni' => (int) StockMovement::whereDate('created_at', today())->where('tipe', 'in')->sum('qty'),
            'outHariIni' => (int) StockMovement::whereDate('created_at', today())->where('tipe', 'out')->sum('qty'),
            // FR-BOOK-05: booking hari ini (non-void — void = customer batal, barang kembali ke rak).
            'bookingHariIni' => Booking::whereDate('created_at', today())->where('status', '!=', Booking::STATUS_VOID)->count(),
            'itemTerbookingHariIni' => (int) BookingItem::query()
                ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
                ->whereDate('bookings.created_at', today())
                ->where('bookings.status', '!=', Booking::STATUS_VOID)
                ->sum('booking_items.qty'),
            'feed' => StockMovement::with('product', 'user')->latest('id')->limit(15)->get(),
            'topOut' => $topOut,
            'chart' => $chart,
            'maxQty' => $maxQty,
        ];
    }
};
?>

<div>
    <div class="flex gap-4 flex-wrap">
        <div class="stat-card">
            <div class="stat-label">Total Produk</div>
            <strong class="stat-value">{{ $totalProduk }}</strong>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Stok</div>
            <strong class="stat-value">{{ $totalStok }}</strong>
        </div>
        <div class="stat-card">
            <div class="stat-label">Masuk Hari Ini</div>
            <strong class="stat-value text-emerald-600">{{ $inHariIni }}</strong>
        </div>
        <div class="stat-card">
            <div class="stat-label">Keluar Hari Ini</div>
            <strong class="stat-value text-poke-red">{{ $outHariIni }}</strong>
        </div>
        <div class="stat-card">
            <div class="stat-label">Booking Hari Ini</div>
            <strong class="stat-value">{{ $bookingHariIni }}</strong>
        </div>
        <div class="stat-card">
            <div class="stat-label">Item Ter-booking Hari Ini</div>
            <strong class="stat-value">{{ $itemTerbookingHariIni }}</strong>
        </div>
    </div>

    <section class="card">
        <div class="flex items-center gap-4 flex-wrap mb-3">
            <h3 class="text-lg font-bold">Grafik In/Out</h3>
            <div class="tab-group">
                @foreach (['7d' => '7 Hari', '30d' => '30 Hari', '90d' => '90 Hari'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('range', '{{ $value }}')"
                        class="tab-btn {{ $range === $value ? 'active' : '' }}"
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="chart-bars">
            @foreach ($chart as $point)
                <div class="chart-col" title="{{ $point['tanggal'] }}: masuk {{ $point['in'] }}, keluar {{ $point['out'] }}">
                    <div class="bars">
                        <div class="bar-in" style="height:{{ round($point['in'] / $maxQty * 140) }}px;"></div>
                        <div class="bar-out" style="height:{{ round($point['out'] / $maxQty * 140) }}px;"></div>
                    </div>
                    <span class="text-[0.65rem] text-black/50">{{ \Illuminate\Support\Carbon::parse($point['tanggal'])->format('d/m') }}</span>
                </div>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-black/60"><span class="text-emerald-600">■</span> Masuk &nbsp; <span class="text-poke-red">■</span> Keluar</p>
    </section>

    <section class="card">
        <h3 class="text-lg font-bold mb-3">Produk Paling Sering Keluar</h3>
        <div class="table-wrap responsive-cards">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produk</th>
                        <th>Total Keluar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topOut as $i => $row)
                        <tr wire:key="topout-{{ $row->product_id }}">
                            <td data-label="#">{{ $i + 1 }}</td>
                            <td data-label="Produk">{{ $row->product->nama_produk ?? '(produk dihapus)' }}</td>
                            <td data-label="Total Keluar">{{ $row->total_keluar }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Belum ada data keluar di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h3 class="text-lg font-bold mb-3">Feed Pergerakan Terakhir</h3>
        <div class="table-wrap responsive-cards">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Produk</th>
                        <th>Tipe</th>
                        <th>Qty</th>
                        <th>Metode</th>
                        <th>Alasan</th>
                        <th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($feed as $movement)
                        <tr wire:key="feed-{{ $movement->id }}">
                            <td data-label="Waktu">{{ $movement->created_at->format('Y-m-d H:i:s') }}</td>
                            <td data-label="Produk">{{ $movement->product->nama_produk ?? '(produk dihapus)' }}</td>
                            <td data-label="Tipe"><span class="{{ $movement->tipe === 'in' ? 'badge-in' : 'badge-out' }}">{{ $movement->tipe === 'in' ? 'Masuk' : 'Keluar' }}</span></td>
                            <td data-label="Qty">{{ $movement->qty }}</td>
                            <td data-label="Metode">{{ $movement->metode === 'scan' ? 'Scan' : 'Manual' }}</td>
                            <td data-label="Alasan">{{ $movement->alasan ?? '-' }}</td>
                            <td data-label="Oleh">{{ $movement->user->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">Belum ada pergerakan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
