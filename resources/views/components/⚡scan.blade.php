<?php

use App\Services\ScanService;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

new class extends Component
{
    public int $sessionCount = 0;

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function scan(string $barcode, string $tipe, string $scanUuid): void
    {
        $validator = Validator::make(
            ['barcode' => $barcode, 'tipe' => $tipe, 'scan_uuid' => $scanUuid],
            [
                'barcode' => ['required', 'string', 'max:64'],
                'tipe' => ['required', 'in:in,out'],
                'scan_uuid' => ['required', 'uuid'],
            ],
        );

        if ($validator->fails()) {
            $this->dispatch('scan-rejected', reason: 'Data scan tidak valid');

            return;
        }

        $result = app(ScanService::class)->record($barcode, $tipe, $scanUuid, auth()->id());

        if ($result['status'] === 'rejected') {
            $this->dispatch('scan-rejected', reason: $result['reason']);

            return;
        }

        if ($result['status'] === 'duplicate') {
            $this->dispatch('scan-duplicate-server');

            return;
        }

        $this->sessionCount++;

        $this->dispatch(
            'scan-success',
            barcode: $result['barcode'],
            namaProduk: $result['namaProduk'],
            stok: $result['stok'],
            sessionCount: $this->sessionCount,
        );
    }
}; ?>

<div
    class="page max-w-lg!"
    x-data="pokemonScanner({
        tipe: 'in',
        cooldownMs: 1000,
        modeTeliti: true,
        missFramesThreshold: 3,
    })"
    x-init="init()"
>
    <h1 class="page-title"><span class="pokeball-dot"></span> Layar Scan</h1>

    <div class="card">
        <div class="tab-group mb-3">
            <label class="tab-btn" :class="tipe === 'in' && 'active'">
                <input type="radio" value="in" x-model="tipe" class="hidden">
                Scan Masuk (+1)
            </label>
            <label class="tab-btn" :class="tipe === 'out' && 'active'">
                <input type="radio" value="out" x-model="tipe" class="hidden">
                Scan Keluar (−1)
            </label>
        </div>

        <div class="scan-frame">
            <video x-ref="video" autoplay playsinline muted></video>
            <div class="reticle" x-ref="reticle"></div>
            <button type="button" class="torch-btn" x-show="torchSupported" @click="toggleTorch()">
                <span x-text="torchOn ? '🔦 Senter: On' : '🔦 Senter: Off'"></span>
            </button>
        </div>

        <p class="scan-count text-center mt-3" x-ref="counter" x-text="sessionCount"></p>

        <p class="text-center text-sm font-semibold text-emerald-600" x-show="ready">Siap scan berikutnya</p>
        <p class="text-center text-sm text-black/60" x-show="lastMessage" x-text="lastMessage"></p>
        <p class="text-center text-sm text-black/40" x-show="!decoderReady">Menyiapkan kamera &amp; decoder…</p>
        <p class="text-center text-xs text-black/40" x-show="usingFallback">Pakai decoder fallback (ZXing).</p>
    </div>

    <div class="card mt-4">
        <div class="checkbox-row mb-2">
            <input type="checkbox" x-model="modeTeliti" id="modeTeliti">
            <label for="modeTeliti">Mode teliti (barcode harus keluar frame dulu sebelum dihitung lagi)</label>
        </div>

        <div class="flex flex-wrap gap-4">
            <div class="field mb-0!">
                <label>Cooldown duplikat (ms)</label>
                <input type="number" min="100" step="100" x-model.number="cooldownMs" class="w-24">
            </div>
            <div class="field mb-0!" x-show="modeTeliti">
                <label>Ambang frame kosong</label>
                <input type="number" min="1" step="1" x-model.number="missFramesThreshold" class="w-20">
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <p class="status-banner offline" x-show="authExpired" x-cloak>
            Sesi login habis — scan tetap aman di antrian.
            <a href="{{ route('login') }}" class="font-bold underline">Login ulang</a> untuk melanjutkan sync.
        </p>
        <p class="status-banner offline" x-show="!isOnline">
            Sedang offline, silakan cari sinyal untuk sync.
            <span x-show="pendingCount > 0" x-text="`(${pendingCount} scan belum ter-sync)`"></span>
        </p>
        <p class="status-banner online" x-show="isOnline">
            Online.
            <span x-show="pendingCount > 0" x-text="`${pendingCount} scan belum ter-sync, sinkronisasi...`"></span>
            <span x-show="pendingCount === 0 && lastSyncAt" x-text="'Sync terakhir: ' + (lastSyncAt ? lastSyncAt.toLocaleTimeString() : '-')"></span>
        </p>
        <button type="button" class="btn-secondary mt-2" @click="manualSync()" :disabled="syncing">Sync sekarang</button>
    </div>
</div>

@once
    @push('scripts')
        @vite(['resources/js/scan.js'])
    @endpush
@endonce
