import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';
import {
    applyOptimisticLocalStock,
    flushQueue,
    getCachedProduct,
    getLastSyncAt,
    getQueueCount,
    refreshMasterCache,
    queueScan,
    submitToServer,
} from './offline-sync';

// Satu AudioContext dipakai bersama: browser membatasi jumlah context aktif,
// dan context yang dibuat tanpa user gesture tertahan 'suspended' (autoplay policy).
let sharedAudioCtx = null;

function audioCtx() {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;

    if (!sharedAudioCtx) {
        sharedAudioCtx = new AudioCtx();
    }

    if (sharedAudioCtx.state === 'suspended') {
        sharedAudioCtx.resume();
    }

    return sharedAudioCtx;
}

// Unlock audio pada gesture pertama di halaman (tap di mana pun).
['touchstart', 'click'].forEach((evt) => {
    document.addEventListener(evt, () => audioCtx(), { once: true, passive: true });
});

function beep({ frequency, duration, type = 'sine' }) {
    const ctx = audioCtx();

    if (ctx.state !== 'running') {
        return; // belum di-unlock gesture — lewati, jangan antri bunyi basi
    }

    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = type;
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.2, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);

    oscillator.connect(gain);
    gain.connect(ctx.destination);

    oscillator.start();
    oscillator.stop(ctx.currentTime + duration);
    oscillator.onended = () => {
        oscillator.disconnect();
        gain.disconnect();
    };
}

function playSuccessSound() {
    beep({ frequency: 880, duration: 0.12 });
}

function playErrorSound() {
    beep({ frequency: 160, duration: 0.35, type: 'sawtooth' });
}

function playDuplicateSound() {
    beep({ frequency: 440, duration: 0.15, type: 'triangle' });
}

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

        stream: null,
        track: null,
        detector: null,
        zxingReader: null,
        zxingControls: null,
        countedState: new Map(),
        rafId: null,
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

            try {
                // Resolusi tinggi penting: default browser (±640x480) tidak cukup
                // pixel untuk decode barcode kecil dari jarak normal.
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                    },
                });
            } catch (e) {
                this.lastMessage = 'Kamera tidak bisa diakses: ' + e.message;

                return;
            }

            this.$refs.video.srcObject = this.stream;
            await this.$refs.video.play();

            this.track = this.stream.getVideoTracks()[0];
            const capabilities = this.track.getCapabilities ? this.track.getCapabilities() : {};
            this.torchSupported = !!capabilities.torch;

            if (Array.isArray(capabilities.focusMode) && capabilities.focusMode.includes('continuous')) {
                try {
                    await this.track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                } catch (e) {
                    // kamera tidak mendukung set fokus via constraint — pakai default
                }
            }

            if ('BarcodeDetector' in window) {
                this.detector = new window.BarcodeDetector({
                    formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code'],
                });
                this.decoderReady = true;
                this.loopBarcodeDetector();
            } else {
                this.usingFallback = true;
                const hints = new Map();
                hints.set(DecodeHintType.POSSIBLE_FORMATS, [
                    BarcodeFormat.EAN_13,
                    BarcodeFormat.EAN_8,
                    BarcodeFormat.UPC_A,
                    BarcodeFormat.UPC_E,
                    BarcodeFormat.CODE_128,
                    BarcodeFormat.CODE_39,
                    BarcodeFormat.QR_CODE,
                ]);
                hints.set(DecodeHintType.TRY_HARDER, true);
                this.zxingReader = new BrowserMultiFormatReader(hints);
                this.decoderReady = true;
                this.zxingControls = await this.zxingReader.decodeFromVideoElement(
                    this.$refs.video,
                    (result) => this.handleFrame(result ? result.getText() : null),
                );
            }
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
                await flushQueue(async () => {
                    this.pendingCount = await getQueueCount();
                });
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

        async loopBarcodeDetector() {
            if (!this.detector) return;

            try {
                const results = await this.detector.detect(this.$refs.video);
                this.handleFrame(results.length ? results[0].rawValue : null);
            } catch (e) {
                // frame decode gagal sesekali, lanjut ke frame berikutnya
            }

            this.rafId = requestAnimationFrame(() => this.loopBarcodeDetector());
        },

        handleFrame(visibleBarcode) {
            for (const [code, st] of this.countedState) {
                if (code !== visibleBarcode) {
                    st.missStreak++;

                    if (this.modeTeliti && st.missStreak >= this.missFramesThreshold) {
                        this.countedState.delete(code);
                    }
                }
            }

            if (!visibleBarcode) {
                return;
            }

            const now = Date.now();
            const st = this.countedState.get(visibleBarcode);

            if (!st) {
                this.countScan(visibleBarcode);
                this.countedState.set(visibleBarcode, { lastTs: now, missStreak: 0 });

                return;
            }

            st.missStreak = 0;

            if (this.modeTeliti) {
                this.duplicateFeedback(st, now);

                return;
            }

            if (now - st.lastTs >= this.cooldownMs) {
                this.countScan(visibleBarcode);
                st.lastTs = now;
            } else {
                this.duplicateFeedback(st, now);
            }
        },

        // Barcode yang sama terbaca terus tiap frame — bunyikan nada duplikat
        // maksimal 1x/detik, bukan puluhan kali per detik.
        duplicateFeedback(st, now) {
            if (!st.lastDupBeepTs || now - st.lastDupBeepTs >= 1000) {
                playDuplicateSound();
                st.lastDupBeepTs = now;
            }

            this.ready = false;
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
                    this.applyServerResult(data);

                    return;
                } catch (e) {
                    // jaringan gagal di tengah jalan — lanjut ke jalur antrian offline
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
            if (!this.track) return;

            this.torchOn = !this.torchOn;

            try {
                await this.track.applyConstraints({ advanced: [{ torch: this.torchOn }] });
            } catch (e) {
                this.torchOn = !this.torchOn;
            }
        },

        destroy() {
            if (this.rafId) cancelAnimationFrame(this.rafId);
            if (this.zxingControls) this.zxingControls.stop();
            if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
            if (this.autoSyncTimer) window.clearInterval(this.autoSyncTimer);
        },
    }));
});
