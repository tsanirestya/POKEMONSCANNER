<?php

use App\Models\Vendor;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $nama = '';

    public ?int $editingId = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function save(): void
    {
        $this->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vendors', 'nama')->ignore($this->editingId),
            ],
        ]);

        if ($this->editingId) {
            $vendor = Vendor::findOrFail($this->editingId);
            $vendor->update(['nama' => $this->nama]);
        } else {
            Vendor::create(['nama' => $this->nama]);
        }

        $this->reset(['nama', 'editingId']);
    }

    public function edit(int $id): void
    {
        $vendor = Vendor::findOrFail($id);
        $this->editingId = $vendor->id;
        $this->nama = $vendor->nama;
    }

    public function cancelEdit(): void
    {
        $this->reset(['nama', 'editingId']);
    }

    public function delete(int $id): void
    {
        $vendor = Vendor::withCount('products')->findOrFail($id);

        if ($vendor->products_count > 0) {
            $this->addError('delete', 'Vendor tidak bisa dihapus, masih punya produk terkait.');

            return;
        }

        $vendor->delete();
    }

    public function with(): array
    {
        return [
            'vendors' => Vendor::withCount('products')->orderBy('nama')->get(),
        ];
    }
};
?>

<div>
    <h1 class="page-title"><span class="pokeball-dot"></span> Vendor</h1>

    <div class="card">
        <h2 class="text-lg font-bold mb-3">{{ $editingId ? 'Ubah Vendor' : 'Tambah Vendor' }}</h2>

        @error('delete') <p class="error mb-2">{{ $message }}</p> @enderror

        <form wire:submit="save" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="field mb-0! w-full flex-1 sm:min-w-48">
                <label for="nama">Nama Vendor</label>
                <input type="text" id="nama" wire:model="nama" autofocus>
                @error('nama') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="flex w-full gap-2 sm:w-auto">
                <button type="submit" class="btn-primary flex-1 sm:flex-none" wire:loading.attr="disabled" wire:target="save">
                    {{ $editingId ? 'Simpan Perubahan' : 'Tambah Vendor' }}
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
                    <th>Nama</th>
                    <th>Jumlah Produk</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendors as $vendor)
                    <tr wire:key="vendor-{{ $vendor->id }}">
                        <td data-label="Nama">{{ $vendor->nama }}</td>
                        <td data-label="Jumlah Produk">{{ $vendor->products_count }}</td>
                        <td class="cards-actions flex gap-2">
                            <button type="button" class="btn-secondary btn-sm" wire:click="edit({{ $vendor->id }})">Ubah</button>
                            <button type="button" class="btn-secondary btn-sm text-poke-red" wire:click="delete({{ $vendor->id }})" wire:confirm="Hapus vendor ini?">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">Belum ada vendor.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
