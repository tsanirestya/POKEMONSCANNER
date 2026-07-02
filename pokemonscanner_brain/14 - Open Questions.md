---
title: 14 - Open Questions
type: brd
status: final
tags: [pokemonscanner, question]
created: 2026-07-02
updated: 2026-07-03
---

# 14 - Open Questions

← [[00 - Index (MOC)]]

Tag: `#pokemonscanner/question`

Hal yang **belum diputuskan**. Jangan mengarang keputusan — catat di sini sampai diklarifikasi. Reverse chronological.

---

## OQ-08 — NFR-PERF-03 saat online: validasi barcode tetap round-trip ke server

**Ditemukan:** 2026-07-03, saat review keamanan/performa Fase 9 (Hardening).

[[05 - Non-Functional Requirements]] NFR-PERF-03 mensyaratkan "validasi barcode dikenal/tidak dilakukan lokal dari cache (tanpa round-trip server saat scan)". Implementasi saat ini (`resources/js/scan.js` fungsi `submitOrQueue`) hanya memvalidasi dari cache IndexedDB (`getCachedProduct`) di jalur **offline**. Saat `navigator.onLine` true, tiap scan langsung `submitToServer(...)` — barcode tak dikenal baru diketahui setelah respons server (`ScanService::record` di server), bukan dari cache lokal duluan.

Secara praktik ini tidak melanggar korektnes (server tetap source of truth, idempotent via `scan_uuid`), dan loop scan tetap dalam anggaran NFR-PERF-01 (≤1.5 dtk) selama jaringan lokal wajar. Tapi ini gap literal terhadap NFR-PERF-03 untuk jalur online.

**Opsi (belum diputuskan):**
1. Biarkan (server round-trip saat online dianggap cukup cepat, prioritas korektnes > local-first).
2. Tambah pre-check lokal dari cache master (sama seperti jalur offline) sebelum submit ke server, walau online — beri feedback instan barcode tak dikenal, submit tetap ke server untuk item yang lolos cek lokal.

Belum diimplementasikan (opsi 2 mengubah alur UX scan yang sudah stabil sejak Fase 4/6 — perlu keputusan eksplisit + uji device nyata sebelum diubah).

---

*(OQ-01 s.d. OQ-07 sudah dijawab dan tercatat di [[13 - Decision Log]] sebagai DEC-11 s.d. DEC-17, 2026-07-02.)*

## Note Terkait

- [[13 - Decision Log]]
- [[12 - Asumsi, Batasan & Risiko]]
