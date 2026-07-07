import {
    createScanGate,
    playDuplicateSound,
    playErrorSound,
    playSuccessSound,
    startCameraScanner,
} from './scanner-core';

// Scanner lookup-only untuk Booking Order (DEC-21): barcode dikenal → tambah
// keranjang via aksi Livewire, TANPA submit movement. BO online-only (DEC-08).
document.addEventListener('alpine:init', () => {
    Alpine.data('bookingScanner', ({ cooldownMs, modeTeliti, missFramesThreshold }) => ({
        cooldownMs,
        modeTeliti,
        missFramesThreshold,
        cameraOn: false,
        starting: false,
        ready: true,
        lastMessage: '',
        decoderReady: false,
        usingFallback: false,
        torchSupported: false,
        torchOn: false,

        camera: null,
        gate: null,

        init() {
            this.gate = createScanGate({
                getConfig: () => ({
                    cooldownMs: this.cooldownMs,
                    modeTeliti: this.modeTeliti,
                    missFramesThreshold: this.missFramesThreshold,
                }),
                onCount: (barcode) => this.lookup(barcode),
                onDuplicate: ({ beep }) => {
                    if (beep) playDuplicateSound();
                    this.ready = false;
                },
            });

            this.startCamera();
        },

        async startCamera() {
            if (this.cameraOn || this.starting) return;

            this.starting = true;

            try {
                this.camera = await startCameraScanner({
                    video: this.$refs.video,
                    onFrame: (barcode) => this.gate.handleFrame(barcode),
                });
            } catch (e) {
                this.lastMessage = 'Kamera tidak bisa diakses: ' + e.message;
                this.starting = false;

                return;
            }

            this.torchSupported = this.camera.torchSupported;
            this.usingFallback = this.camera.usingFallback;
            this.decoderReady = true;
            this.cameraOn = true;
            this.starting = false;
        },

        stopCamera() {
            if (this.camera) this.camera.stop();
            this.camera = null;
            this.cameraOn = false;
            this.decoderReady = false;
            this.torchSupported = false;
            this.torchOn = false;
        },

        async lookup(barcode) {
            this.ready = false;

            try {
                const result = await this.$wire.addByBarcode(barcode);

                if (result && result.status === 'added') {
                    playSuccessSound();
                    this.lastMessage = `${result.namaProduk} × ${result.qty}`;
                    this.flashSuccess();
                } else {
                    playErrorSound();
                    this.lastMessage = (result && result.reason) || 'Barcode ditolak';
                }
            } catch (e) {
                // BO online-only — tanpa antrian offline (DEC-08)
                playErrorSound();
                this.lastMessage = 'Gagal terhubung — booking butuh koneksi online';
            }

            window.setTimeout(() => {
                this.ready = true;
            }, this.modeTeliti ? 0 : this.cooldownMs);
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
            this.stopCamera();
        },
    }));
});
