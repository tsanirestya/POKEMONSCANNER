---
title: 05 - Non-Functional Requirements
type: brd
status: final
tags: [pokemonscanner, nfr]
created: 2026-07-02
updated: 2026-07-02
---

# 05 - Non-Functional Requirements

← [[00 - Index (MOC)]]

Tag: `#pokemonscanner/nfr`

## Performa

| ID | Requirement |
|---|---|
| NFR-PERF-01 | Loop scan item identik selesai ≤1.5 detik/scan (decode → hitung → bunyi → siap lagi). |
| NFR-PERF-02 | Scan barcode berbeda tidak menunggu cooldown barcode lain. |
| NFR-PERF-03 | Validasi barcode dikenal/tidak dilakukan lokal dari cache (tanpa round-trip server saat scan). |
| NFR-PERF-04 | Dashboard query stok memakai cache `stok_sekarang`, bukan agregasi penuh tiap request. |

## PWA & Platform

| ID | Requirement |
|---|---|
| NFR-PWA-01 | App installable ke home screen (manifest + service worker). |
| NFR-PWA-02 | Wajib HTTPS (akses kamera & service worker). |
| NFR-PWA-03 | Target utama Chrome Android (`BarcodeDetector`); fallback ZXing untuk browser tanpa API. |
| NFR-PWA-04 | Fitur torch/senter dipakai bila didukung `MediaStreamTrack`. |

## Keamanan

| ID | Requirement |
|---|---|
| NFR-SEC-01 | Otorisasi per-role ditegakkan di server (bukan hanya sembunyikan menu). |
| NFR-SEC-02 | Endpoint scan idempotent via `scan_uuid` unik (tolak replay/duplikat). |
| NFR-SEC-03 | Password ter-hash (default Laravel). Session aman. |
| NFR-SEC-04 | Input manual keluar wajib alasan (jejak audit). |
| NFR-SEC-05 | Ledger append-only sebagai jejak audit; koreksi lewat movement baru. |

## Usability

| ID | Requirement |
|---|---|
| NFR-UX-01 | Operator paham hasil scan tanpa melihat layar (bunyi dibedakan: sukses/error/duplikat). |
| NFR-UX-02 | Angka hitungan besar & jelas di layar scan. |
| NFR-UX-03 | Indikator "siap scan berikutnya" untuk item identik. |
| NFR-UX-04 | Status sync selalu terlihat: saat offline tampil notifikasi + jumlah task belum sync; saat online tampil waktu sync terakhir. |

## Reliability

| ID | Requirement |
|---|---|
| NFR-REL-01 | Scan tetap berfungsi offline (cache + antrian lokal). |
| NFR-REL-02 | Hasil stok akhir selalu konsisten setelah semua device sync (eventual consistency). |
| NFR-REL-03 | Tidak ada double-count akibat kamera membaca berulang atau sync ganda. |
| NFR-REL-04 | Import berulang aman terhadap stok (idempotent). |

## Kompatibilitas Data

| ID | Requirement |
|---|---|
| NFR-DATA-01 | `barcode` selalu VARCHAR; mendukung 12 & 13 digit dan leading zero. |
| NFR-DATA-02 | Import menangani encoding UTF-8 & memperbaiki mojibake. |

## Note Terkait

- [[04 - Functional Requirements]]
- [[09 - Spesifikasi Offline Sync]]
- [[06 - Data Model & ERD]]
