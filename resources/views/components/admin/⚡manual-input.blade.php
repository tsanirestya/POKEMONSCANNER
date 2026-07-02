<?php

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $product_id = '';

    public string $tipe = 'in';

    public int $qty = 1;

    public string $alasan = '';

    public ?string $successMessage = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function save(): void
    {
        $this->successMessage = null;

        $this->validate([
            'product_id' => ['required', 'exists:products,id'],
            'tipe' => ['required', 'in:in,out'],
            'qty' => ['required', 'integer', 'min:1'],
            'alasan' => [$this->tipe === 'out' ? 'required' : 'nullable', 'string', 'max:255'],
        ], [
            'alasan.required' => 'Alasan wajib diisi untuk input keluar.',
        ]);

        $result = DB::transaction(function () {
            StockMovement::create([
                'product_id' => $this->product_id,
                'tipe' => $this->tipe,
                'qty' => $this->qty,
                'metode' => 'manual',
                'alasan' => $this->alasan !== '' ? $this->alasan : null,
                'user_id' => auth()->id(),
            ]);

            $product = Product::whereKey($this->product_id)->lockForUpdate()->first();
            $product->stok_sekarang = $this->tipe === 'in'
                ? $product->stok_sekarang + $this->qty
                : $product->stok_sekarang - $this->qty;
            $product->save();

            return $product;
        });

        $this->successMessage = "Tersimpan: {$result->nama_produk} sekarang stok {$result->stok_sekarang}.";

        $this->reset(['product_id', 'qty', 'alasan']);
        $this->qty = 1;
    }

    public function with(): array
    {
        return [
            'products' => Product::orderBy('nama_produk')->get(),
            'recentMovements' => StockMovement::with('product', 'user')
                ->where('metode', 'manual')
                ->latest('id')
                ->limit(10)
                ->get(),
        ];
    }
};
?>

<div class="card mt-6">
    <h2 class="text-lg font-bold mb-3">Input Manual</h2>

    @if ($successMessage)
        <p class="success mb-3">{{ $successMessage }}</p>
    @endif

    <form wire:submit="save" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <div class="field mb-0! w-full flex-1 sm:min-w-64">
            <label for="product_id">Produk</label>
            <select id="product_id" wire:model="product_id">
                <option value="">-- pilih produk --</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">{{ $product->nama_produk }} ({{ $product->barcode }}) — stok {{ $product->stok_sekarang }}</option>
                @endforeach
            </select>
            @error('product_id') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="tab-group">
            <label class="tab-btn {{ $tipe === 'in' ? 'active' : '' }}">
                <input type="radio" value="in" wire:model.live="tipe" class="hidden">
                Input Masuk
            </label>
            <label class="tab-btn {{ $tipe === 'out' ? 'active' : '' }}">
                <input type="radio" value="out" wire:model.live="tipe" class="hidden">
                Input Keluar
            </label>
        </div>

        <div class="field mb-0! w-full sm:w-auto">
            <label for="qty">Qty</label>
            <input type="number" id="qty" min="1" step="1" wire:model="qty" class="w-full sm:w-24">
            @error('qty') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field mb-0! w-full flex-1 sm:min-w-48">
            <label for="alasan">
                Alasan @if ($tipe === 'out') (wajib) @else (opsional) @endif
            </label>
            <input type="text" id="alasan" wire:model="alasan">
            @error('alasan') <span class="error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
            Simpan
        </button>
    </form>

    <h3 class="text-base font-bold mt-5 mb-2">Input Manual Terakhir</h3>
    <div class="table-wrap responsive-cards">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Produk</th>
                    <th>Tipe</th>
                    <th>Qty</th>
                    <th>Alasan</th>
                    <th>Oleh</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentMovements as $movement)
                    <tr wire:key="movement-{{ $movement->id }}">
                        <td data-label="Waktu">{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                        <td data-label="Produk">{{ $movement->product->nama_produk }}</td>
                        <td data-label="Tipe"><span class="{{ $movement->tipe === 'in' ? 'badge-in' : 'badge-out' }}">{{ $movement->tipe === 'in' ? 'Masuk' : 'Keluar' }}</span></td>
                        <td data-label="Qty">{{ $movement->qty }}</td>
                        <td data-label="Alasan">{{ $movement->alasan ?? '-' }}</td>
                        <td data-label="Oleh">{{ $movement->user->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">Belum ada input manual.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
