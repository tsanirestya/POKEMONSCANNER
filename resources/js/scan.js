import {
    createScanGate,
    playDuplicateSound,
    playErrorSound,
    playSuccessSound,
    startCameraScanner,
} from './scanner-core';
import {
    applyOptimisticLocalStock,
    flushQueue,
    getCachedProduct,
    getLastSyncAt,
    getQueueCount,
    isAuthError,
    refreshMasterCache,
    queueScan,
    submitToServer,
} from './offline-sync';

const AUTO_SYNC_INTERVAL_MS = 20000;

document.addEventListener('alpine:init', () => {
    Alpine.data('pokemonScanner', ({ tipe, cooldownMs, modeTeliti, missFramesThreshold }) => ({
        tipe,
        cooldownMs,
        modeTeliti,
        missFramesThreshold,
        sessionCount: 0,
        ready: true,
        lastMessage: '',
        decoderReady: false,
        usingFallback: false,
        torchSupported: false,
        torchOn: false,

        isOnline: navigator.onLine,
        pendingCount: 0,
        lastSyncAt: null,
        syncing: false,
        authExpired: false,

        camera: null,
        gate: null,
        autoSyncTimer: null,

        async init() {
            this.lastSyncAt = getLastSyncAt();
            this.pendingCount = await getQueueCount();

            window.addEventListener('online', () => this.handleOnline());
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            if (this.isOnline) {
                this.refreshCache();
            }

            this.autoSyncTimer = window.setInterval(() => {
                if (this.isOnline) this.trySync();
            }, AUTO_SYNC_INTERVAL_MS);

            this.gate = createScanGate({
                getConfig: () => ({
                    cooldownMs: this.cooldownMs,
                    modeTeliti: this.modeTeliti,
                    missFramesThreshold: this.missFramesThreshold,
                }),
                onCount: (barcode) => this.countScan(barcode),
                onDuplicate: ({ beep }) => {
                    if (beep) playDuplicateSound();
                    this.ready = false;
                },
            });

            try {
                this.camera = await startCameraScanner({
                    video: this.$refs.video,
                    onFrame: (barcode) => this.gate.handleFrame(barcode),
                });
            } catch (e) {
                this.lastMessage = 'Kamera tidak bisa diakses: ' + e.message;

                return;
            }

            this.torchSupported = this.camera.torchSupported;
            this.usingFallback = this.camera.usingFallback;
            this.decoderReady = true;
        },

        async handleOnline() {
            this.isOnline = true;
            await this.refreshCache();
            await this.trySync();
        },

        async refreshCache() {
            try {
                await refreshMasterCache();
            } catch (e) {
                // gagal refresh (mis. race saat baru online) — dicoba lagi di siklus berikutnya
            }
        },

        async trySync() {
            if (this.syncing) return;

            this.syncing = true;

            try {
                const result = await flushQueue(async () => {
                    this.pendingCount = await getQueueCount();
                });
                this.authExpired = result.authError;
                this.lastSyncAt = getLastSyncAt();
            } finally {
                this.syncing = false;
            }
        },

        async manualSync() {
            if (!navigator.onLine) {
                this.lastMessage = 'Masih offline, tidak bisa sync sekarang';

                return;
            }

            await this.refreshCache();
            await this.trySync();
        },

        async countScan(barcode) {
            const scanUuid = crypto.randomUUID();
            this.ready = false;

            await this.submitOrQueue(barcode, this.tipe, scanUuid);

            window.setTimeout(() => {
                this.ready = true;
            }, this.modeTeliti ? 0 : this.cooldownMs);
        },

        async submitOrQueue(barcode, tipe, scanUuid) {
            if (navigator.onLine) {
                try {
                    const data = await submitToServer({ barcode, tipe, scan_uuid: scanUuid });
                    this.authExpired = false;
                    this.applyServerResult(data);

                    return;
                } catch (e) {
                    // jaringan gagal / sesi habis — scan diamankan ke antrian offline
                    if (isAuthError(e)) {
                        this.authExpired = true;
                    }
                }
            }

            await this.queueOffline(barcode, tipe, scanUuid);
        },

        applyServerResult(data) {
            if (data.status === 'success') {
                playSuccessSound();
                this.sessionCount++;
                this.lastMessage = `${data.namaProduk} → stok ${data.stok}`;
                this.flashSuccess();
            } else if (data.status === 'duplicate') {
                playDuplicateSound();
                this.lastMessage = 'Scan duplikat (sudah tercatat sebelumnya)';
            } else {
                playErrorSound();
                this.lastMessage = data.reason || 'Scan ditolak';
            }
        },

        flashSuccess() {
            const counter = this.$refs.counter;
            const reticle = this.$refs.reticle;

            if (counter) {
                counter.classList.remove('bump');
                void counter.offsetWidth;
                counter.classList.add('bump');
            }

            if (reticle) {
                reticle.classList.remove('flash');
                void reticle.offsetWidth;
                reticle.classList.add('flash');
            }
        },

        async queueOffline(barcode, tipe, scanUuid) {
            const product = await getCachedProduct(barcode);

            if (!product) {
                playErrorSound();
                this.lastMessage = 'Barcode tidak dikenal (offline): ' + barcode;

                return;
            }

            await queueScan({ scan_uuid: scanUuid, barcode, tipe, waktu: Date.now() });
            await applyOptimisticLocalStock(barcode, tipe);
            this.pendingCount = await getQueueCount();

            const updated = await getCachedProduct(barcode);

            playSuccessSound();
            this.sessionCount++;
            this.lastMessage = `${updated.nama_produk} → stok ${updated.stok_sekarang} (perkiraan, belum sync)`;
            this.flashSuccess();
        },

        async toggleTorch() {
            if (!this.camera || !this.torchSupported) return;

            this.torchOn = !this.torchOn;

            try {
                await this.camera.setTorch(this.torchOn);
            } catch (e) {
                this.torchOn = !this.torchOn;
            }
        },

        destroy() {
            if (this.camera) this.camera.stop();
            if (this.autoSyncTimer) window.clearInterval(this.autoSyncTimer);
        },
    }));
});
