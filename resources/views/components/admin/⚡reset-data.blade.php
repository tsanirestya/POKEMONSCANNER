<?php

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $konfirmasi = '';

    public ?string $done = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function resetData(): void
    {
        if (strtoupper(trim($this->konfirmasi)) !== 'RESET') {
            $this->addError('konfirmasi', 'Ketik RESET di kolom konfirmasi untuk melanjutkan.');

            return;
        }

        $jumlah = StockMovement::count();

        DB::transaction(function () {
            StockMovement::query()->delete();
            Product::query()->update(['stok_sekarang' => 0]);
        });

        $this->reset('konfirmasi');
        $this->done = "{$jumlah} riwayat pergerakan dihapus; stok semua produk kembali 0. Produk, vendor, dan user tidak disentuh.";
    }
};
?>

<div class="card mt-4 border-poke-red/40">
    <h2 class="text-lg font-bold mb-1 text-poke-red">Reset Data In/Out</h2>
    <p class="text-sm text-black/60 mb-3">
        Menghapus <strong>seluruh riwayat pergerakan stok</strong> (scan &amp; manual) dan mengembalikan stok semua produk ke 0.
        Master produk, vendor, dan user <strong>tidak</strong> ikut terhapus. Aksi ini tidak bisa dibatalkan —
        pastikan semua HP operator sudah sync &amp; antrian offline kosong, karena antrian lama yang baru ter-sync akan tercatat lagi.
    </p>

    @if ($done)
        <p class="status-banner online mb-3">{{ $done }}</p>
    @endif

    <form wire:submit="resetData" class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="field mb-0! w-full sm:w-56">
            <label for="konfirmasi">Ketik <strong>RESET</strong> untuk konfirmasi</label>
            <input type="text" id="konfirmasi" wire:model="konfirmasi" autocomplete="off">
            @error('konfirmasi') <span class="error">{{ $message }}</span> @enderror
        </div>

        <button
            type="submit"
            class="btn-primary sm:flex-none"
            wire:loading.attr="disabled"
            wire:target="resetData"
            wire:confirm="Hapus SEMUA riwayat in/out dan nolkan stok? Aksi ini tidak bisa dibatalkan."
        >
            Reset Riwayat In/Out
        </button>
    </form>
</div>
