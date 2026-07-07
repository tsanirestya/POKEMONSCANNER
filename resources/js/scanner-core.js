import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';

// ============ Bunyi (Web Audio API, orisinal — DEC-10) ============

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

export function playSuccessSound() {
    beep({ frequency: 880, duration: 0.12 });
}

export function playErrorSound() {
    beep({ frequency: 160, duration: 0.35, type: 'sawtooth' });
}

export function playDuplicateSound() {
    beep({ frequency: 440, duration: 0.15, type: 'triangle' });
}

// ============ Kamera + decoder (BarcodeDetector, fallback ZXing) ============

const DETECTOR_FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code'];

const ZXING_FORMATS = [
    BarcodeFormat.EAN_13,
    BarcodeFormat.EAN_8,
    BarcodeFormat.UPC_A,
    BarcodeFormat.UPC_E,
    BarcodeFormat.CODE_128,
    BarcodeFormat.CODE_39,
    BarcodeFormat.QR_CODE,
];

/**
 * Nyalakan kamera + loop decode barcode pada elemen <video>.
 * onFrame dipanggil tiap frame dengan barcode terlihat (string) atau null.
 * Melempar error bila kamera tidak bisa diakses — tangani di pemanggil.
 */
export async function startCameraScanner({ video, onFrame }) {
    // Resolusi tinggi penting: default browser (±640x480) tidak cukup
    // pixel untuk decode barcode kecil dari jarak normal.
    const stream = await navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1920 },
            height: { ideal: 1080 },
        },
    });

    video.srcObject = stream;
    await video.play();

    const track = stream.getVideoTracks()[0];
    const capabilities = track.getCapabilities ? track.getCapabilities() : {};

    if (Array.isArray(capabilities.focusMode) && capabilities.focusMode.includes('continuous')) {
        try {
            await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
        } catch (e) {
            // kamera tidak mendukung set fokus via constraint — pakai default
        }
    }

    let rafId = null;
    let zxingControls = null;
    let usingFallback = false;

    if ('BarcodeDetector' in window) {
        const detector = new window.BarcodeDetector({ formats: DETECTOR_FORMATS });

        const loop = async () => {
            try {
                const results = await detector.detect(video);
                onFrame(results.length ? results[0].rawValue : null);
            } catch (e) {
                // frame decode gagal sesekali, lanjut ke frame berikutnya
            }

            rafId = requestAnimationFrame(loop);
        };

        loop();
    } else {
        usingFallback = true;
        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, ZXING_FORMATS);
        hints.set(DecodeHintType.TRY_HARDER, true);
        const reader = new BrowserMultiFormatReader(hints);
        zxingControls = await reader.decodeFromVideoElement(
            video,
            (result) => onFrame(result ? result.getText() : null),
        );
    }

    return {
        usingFallback,
        torchSupported: !!capabilities.torch,

        async setTorch(on) {
            await track.applyConstraints({ advanced: [{ torch: on }] });
        },

        stop() {
            if (rafId) cancelAnimationFrame(rafId);
            if (zxingControls) zxingControls.stop();
            stream.getTracks().forEach((t) => t.stop());
        },
    };
}

// ============ Gate anti-double-input (cooldown + mode teliti — DEC-13/17) ============

/**
 * Saring hasil decode per-frame jadi hitungan scan:
 * - barcode baru terlihat → onCount(barcode)
 * - mode teliti: barcode harus keluar frame (missFramesThreshold frame kosong) sebelum dihitung lagi
 * - non-teliti: barcode sama dihitung lagi setelah cooldownMs
 * - selain itu → onDuplicate({ beep }) — beep true maks 1x/detik per barcode
 * Config dibaca via getConfig() tiap frame agar nilai reaktif (Alpine) selalu terbaru.
 */
export function createScanGate({ getConfig, onCount, onDuplicate }) {
    const counted = new Map();

    return {
        handleFrame(visibleBarcode) {
            const { cooldownMs, modeTeliti, missFramesThreshold } = getConfig();

            for (const [code, st] of counted) {
                if (code !== visibleBarcode) {
                    st.missStreak++;

                    if (modeTeliti && st.missStreak >= missFramesThreshold) {
                        counted.delete(code);
                    }
                }
            }

            if (!visibleBarcode) {
                return;
            }

            const now = Date.now();
            const st = counted.get(visibleBarcode);

            if (!st) {
                onCount(visibleBarcode);
                counted.set(visibleBarcode, { lastTs: now, missStreak: 0 });

                return;
            }

            st.missStreak = 0;

            if (!modeTeliti && now - st.lastTs >= cooldownMs) {
                onCount(visibleBarcode);
                st.lastTs = now;

                return;
            }

            // Barcode yang sama terbaca terus tiap frame — bunyi duplikat
            // dibatasi 1x/detik, bukan puluhan kali per detik.
            const shouldBeep = !st.lastDupBeepTs || now - st.lastDupBeepTs >= 1000;

            if (shouldBeep) {
                st.lastDupBeepTs = now;
            }

            onDuplicate({ beep: shouldBeep });
        },
    };
}
