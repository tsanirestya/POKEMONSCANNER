<?php

use App\Models\Setting;
use Livewire\Component;

new class extends Component
{
    public int $cooldownMs = 2000;

    public ?string $saved = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function mount(): void
    {
        $this->cooldownMs = (int) Setting::get('scan_cooldown_ms', '2000');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'cooldownMs' => ['required', 'integer', 'min:100', 'max:10000'],
        ]);

        Setting::set('scan_cooldown_ms', (string) $validated['cooldownMs']);

        $this->saved = 'Tersimpan. Berlaku untuk sesi scan yang dibuka setelah ini.';
    }
};
?>

<div class="card mt-4">
    <h2 class="text-lg font-bold mb-1">Pengaturan Scan</h2>
    <p class="text-sm text-black/60 mb-3">
        Cooldown duplikat default (ms) dipakai saat halaman Scan dibuka. Operator masih bisa menyesuaikan
        sendiri di layar scan untuk sesi berjalan.
    </p>

    @if ($saved)
        <p class="status-banner online mb-3">{{ $saved }}</p>
    @endif

    <form wire:submit="save" class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="field mb-0! w-full sm:w-56">
            <label for="cooldownMs">Cooldown duplikat default (ms)</label>
            <input type="number" id="cooldownMs" min="100" step="100" wire:model="cooldownMs">
            @error('cooldownMs') <span class="error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn-primary sm:flex-none" wire:loading.attr="disabled" wire:target="save">
            Simpan
        </button>
    </form>
</div>
