<?php

use App\Models\Booking;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

new class extends Component
{
    /** Keranjang booking, key = product_id. */
    public array $cart = [];

    public string $search = '';

    public function boot(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'operator', 'spg'], true), 403);
    }

    /**
     * Jalur scan kamera (lookup-only, dipanggil dari booking.js).
     * Barcode tak dikenal ditolak — tidak membuat produk (DEC-05).
     */
    public function addByBarcode(string $barcode): array
    {
        $validator = Validator::make(
            ['barcode' => $barcode],
            ['barcode' => ['required', 'string', 'max:64']],
        );

        if ($validator->fails()) {
            return ['status' => 'rejected', 'reason' => 'Barcode tidak valid'];
        }

        $product = Product::where('barcode', $barcode)->first();

        if (! $product) {
            return ['status' => 'rejected', 'reason' => 'Barcode tidak dikenal: '.$barcode];
        }

        return [
            'status' => 'added',
            'namaProduk' => $product->nama_produk,
            'qty' => $this->addToCart($product),
        ];
    }

    /** Jalur pencarian nama produk. */
    public function addProduct(int $productId): void
    {
        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        $this->addToCart($product);
        $this->search = '';
    }

    protected function addToCart(Product $product): int
    {
        $this->resetErrorBag('cart');

        if (isset($this->cart[$product->id])) {
            $this->cart[$product->id]['qty']++;
        } else {
            $this->cart[$product->id] = [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'nama_produk' => $product->nama_produk,
                'stok_sekarang' => $product->stok_sekarang,
                'qty' => 1,
            ];
        }

        return $this->cart[$product->id]['qty'];
    }

    public function incrementQty(int $productId): void
    {
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['qty']++;
        }
    }

    public function decrementQty(int $productId): void
    {
        if (isset($this->cart[$productId]) && $this->cart[$productId]['qty'] > 1) {
            $this->cart[$productId]['qty']--;
        }
    }

    public function setQty(int $productId, $qty): void
    {
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['qty'] = max(1, (int) $qty);
        }
    }

    public function removeItem(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    /**
     * Simpan booking + item dalam satu transaksi, lalu ke layar struk.
     * TIDAK menulis stock_movements / stok_sekarang (DEC-21).
     */
    public function save()
    {
        if ($this->cart === []) {
            $this->addError('cart', 'Keranjang masih kosong — scan atau cari produk dulu.');

            return;
        }

        $productIds = array_column($this->cart, 'product_id');
        $existing = Product::whereIn('id', $productIds)->pluck('id')->all();

        if ($missing = array_diff($productIds, $existing)) {
            foreach ($missing as $id) {
                unset($this->cart[$id]);
            }
            $this->addError('cart', 'Sebagian produk sudah tidak ada di master — item dihapus dari keranjang, cek ulang.');

            return;
        }

        $booking = DB::transaction(function () {
            $booking = Booking::create([
                'booking_code' => Booking::generateCode(),
                'nomor_urut' => Booking::nextNomorUrut(),
                'user_id' => auth()->id(),
                'status' => Booking::STATUS_PRINTED,
            ]);

            foreach ($this->cart as $item) {
                $booking->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => max(1, (int) $item['qty']),
                ]);
            }

            return $booking;
        });

        return $this->redirectRoute('booking.struk', ['booking' => $booking->id]);
    }

    public function with(): array
    {
        $term = trim($this->search);

        $searchResults = mb_strlen($term) >= 2
            ? Product::where(function ($query) use ($term) {
                $query->where('nama_produk', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "{$term}%");
            })->orderBy('nama_produk')->limit(10)->get()
            : collect();

        return [
            'searchResults' => $searchResults,
            'totalQty' => array_sum(array_column($this->cart, 'qty')),
        ];
    }
}; ?>

<div
    class="page max-w-lg!"
    x-data="bookingScanner({
        cooldownMs: 1000,
        modeTeliti: true,
        missFramesThreshold: 3,
    })"
    x-init="init()"
>
    <h1 class="page-title"><span class="pokeball-dot"></span> Booking Order</h1>

    <div class="scan-frame" x-show="cameraOn || starting">
        <video x-ref="video" autoplay playsinline muted></video>
        <div class="reticle" x-ref="reticle"></div>

        <div class="scan-count-badge" x-ref="counter">
            <span>{{ $totalQty }}</span>
            <small>ITEM</small>
        </div>

        <button type="button" class="torch-btn" x-show="torchSupported" @click="toggleTorch()">
            <span x-text="torchOn ? '🔦 On' : '🔦 Off'"></span>
        </button>

        <div class="scan-info">
            <span class="scan-ready-dot" :class="(ready && decoderReady) && 'on'"></span>
            <span class="min-w-0 flex-1 truncate" x-text="lastMessage || (decoderReady ? 'Scan barcode produk untuk booking' : 'Menyiapkan kamera & decoder…')"></span>
        </div>
    </div>

    <div class="mt-2 flex items-center gap-2">
        <button type="button" class="btn-secondary" x-show="cameraOn" @click="stopCamera()">Matikan kamera</button>
        <button type="button" class="btn-secondary" x-show="!cameraOn" x-cloak @click="startCamera()">Nyalakan kamera</button>
        <p class="text-xs text-black/40" x-show="usingFallback" x-cloak>Pakai decoder fallback (ZXing).</p>
    </div>

    <div class="card mt-4">
        <div class="field mb-0!">
            <label for="search">Cari produk (nama / barcode)</label>
            <input type="text" id="search" wire:model.live.debounce.300ms="search" placeholder="min. 2 huruf" autocomplete="off">
        </div>

        @if (trim($search) !== '' && mb_strlen(trim($search)) >= 2)
            <ul class="mt-2 divide-y divide-black/10">
                @forelse ($searchResults as $product)
                    <li wire:key="result-{{ $product->id }}">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 py-2 text-left"
                            wire:click="addProduct({{ $product->id }})"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $product->nama_produk }}</span>
                                <span class="block text-xs text-black/50">{{ $product->barcode }}</span>
                            </span>
                            <span class="{{ $product->stok_sekarang > 0 ? 'badge-in' : 'badge-out' }}">stok {{ $product->stok_sekarang }}</span>
                        </button>
                    </li>
                @empty
                    <li class="py-2 text-sm text-black/50">Tidak ada produk cocok "{{ $search }}".</li>
                @endforelse
            </ul>
        @endif
    </div>

    <div class="card mt-4">
        <h2 class="text-lg font-bold mb-2">Keranjang ({{ $totalQty }} item)</h2>

        @error('cart') <p class="error mb-2">{{ $message }}</p> @enderror

        @if ($cart === [])
            <p class="text-sm text-black/50">Belum ada item. Scan barcode atau cari produk di atas.</p>
        @else
            <ul class="divide-y divide-black/10">
                @foreach ($cart as $item)
                    <li class="flex items-center gap-2 py-2" wire:key="cart-{{ $item['product_id'] }}">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $item['nama_produk'] }}</span>
                            <span class="block text-xs text-black/50">{{ $item['barcode'] }} · stok {{ $item['stok_sekarang'] }}</span>
                        </span>

                        <span class="flex items-center gap-1">
                            <button type="button" class="btn-secondary btn-sm" wire:click="decrementQty({{ $item['product_id'] }})">−</button>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                class="w-14 text-center"
                                value="{{ $item['qty'] }}"
                                wire:change="setQty({{ $item['product_id'] }}, $event.target.value)"
                            >
                            <button type="button" class="btn-secondary btn-sm" wire:click="incrementQty({{ $item['product_id'] }})">+</button>
                        </span>

                        <button type="button" class="btn-secondary btn-sm text-poke-red" wire:click="removeItem({{ $item['product_id'] }})" title="Hapus item">✕</button>
                    </li>
                @endforeach
            </ul>

            <button
                type="button"
                class="btn-primary mt-3 w-full"
                wire:click="save"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                Simpan &amp; Cetak
            </button>
        @endif
    </div>
</div>

@once
    @push('scripts')
        @vite(['resources/js/booking.js'])
    @endpush
@endonce
