<?php

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $barcode = '';

    public string $nama_produk = '';

    public ?int $vendor_id = null;

    public ?int $editingId = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function save(): void
    {
        $this->validate([
            'barcode' => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'barcode')->ignore($this->editingId),
            ],
            'nama_produk' => ['required', 'string', 'max:255'],
            'vendor_id' => ['required', 'exists:vendors,id'],
        ]);

        if ($this->editingId) {
            $product = Product::findOrFail($this->editingId);
            $product->update([
                'barcode' => $this->barcode,
                'nama_produk' => $this->nama_produk,
                'vendor_id' => $this->vendor_id,
            ]);
        } else {
            Product::create([
                'barcode' => $this->barcode,
                'nama_produk' => $this->nama_produk,
                'vendor_id' => $this->vendor_id,
                'stok_sekarang' => 0,
            ]);
        }

        $this->reset(['barcode', 'nama_produk', 'vendor_id', 'editingId']);
    }

    public function edit(int $id): void
    {
        $product = Product::findOrFail($id);
        $this->editingId = $product->id;
        $this->barcode = $product->barcode;
        $this->nama_produk = $product->nama_produk;
        $this->vendor_id = $product->vendor_id;
    }

    public function cancelEdit(): void
    {
        $this->reset(['barcode', 'nama_produk', 'vendor_id', 'editingId']);
    }

    public function delete(int $id): void
    {
        $product = Product::withCount('stockMovements')->findOrFail($id);

        if ($product->stock_movements_count > 0) {
            $this->addError('delete', 'Produk tidak bisa dihapus, sudah punya riwayat pergerakan stok.');

            return;
        }

        $product->delete();
    }

    public function with(): array
    {
        return [
            'products' => Product::with('vendor')->orderBy('nama_produk')->get(),
            'vendors' => Vendor::orderBy('nama')->get(),
        ];
    }
};
?>

<div>
    <h1 class="page-title"><span class="pokeball-dot"></span> Produk</h1>

    <div class="card">
        <h2 class="text-lg font-bold mb-3">{{ $editingId ? 'Ubah Produk' : 'Tambah Produk' }}</h2>

        @error('delete') <p class="error mb-2">{{ $message }}</p> @enderror

        <form wire:submit="save" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="field mb-0! w-full sm:w-auto sm:min-w-48">
                <label for="barcode">Barcode</label>
                <input type="text" id="barcode" wire:model="barcode" autofocus>
                @error('barcode') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field mb-0! w-full flex-1 sm:min-w-48">
                <label for="nama_produk">Nama Produk</label>
                <input type="text" id="nama_produk" wire:model="nama_produk">
                @error('nama_produk') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field mb-0! w-full sm:w-auto sm:min-w-48">
                <label for="vendor_id">Vendor</label>
                <select id="vendor_id" wire:model="vendor_id">
                    <option value="">-- pilih vendor --</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->nama }}</option>
                    @endforeach
                </select>
                @error('vendor_id') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="flex w-full gap-2 sm:w-auto">
                <button type="submit" class="btn-primary flex-1 sm:flex-none" wire:loading.attr="disabled" wire:target="save">
                    {{ $editingId ? 'Simpan Perubahan' : 'Tambah Produk' }}
                </button>

                @if ($editingId)
                    <button type="button" class="btn-secondary flex-1 sm:flex-none" wire:click="cancelEdit">Batal</button>
                @endif
            </div>
        </form>
    </div>

    <div class="table-wrap responsive-cards mt-4">
        <table>
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Nama Produk</th>
                    <th>Vendor</th>
                    <th>Stok</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td data-label="Barcode">{{ $product->barcode }}</td>
                        <td data-label="Nama Produk">{{ $product->nama_produk }}</td>
                        <td data-label="Vendor">{{ $product->vendor->nama }}</td>
                        <td data-label="Stok">{{ $product->stok_sekarang }}</td>
                        <td class="cards-actions flex gap-2">
                            <button type="button" class="btn-secondary btn-sm" wire:click="edit({{ $product->id }})">Ubah</button>
                            <button type="button" class="btn-secondary btn-sm text-poke-red" wire:click="delete({{ $product->id }})" wire:confirm="Hapus produk ini?">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Belum ada produk.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
