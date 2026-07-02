---
title: 02 - Tujuan & Ruang Lingkup
type: brd
status: final
tags: [pokemonscanner, scope]
created: 2026-07-02
updated: 2026-07-02
---

# 02 - Tujuan & Ruang Lingkup

← [[00 - Index (MOC)]]

## Objectives (Terukur)

| ID | Objective | Ukuran Keberhasilan |
|---|---|---|
| OBJ-01 | Scan cepat masuk/keluar | Satu item identik bisa dihitung ≤1.5 detik/scan; scan campur beda-barcode tanpa jeda tambahan |
| OBJ-02 | Akurasi stok | 100% pergerakan tercatat di `stock_movements`; tidak ada double-count akibat kamera membaca berulang |
| OBJ-03 | Operasional offline | Scan tetap jalan tanpa sinyal; antrian tersync otomatis saat online; 0 duplikat setelah sync |
| OBJ-04 | Import master akurat | Import 109 produk tanpa mengubah stok; mojibake dibersihkan; barcode tetap string |
| OBJ-05 | Visibilitas | Admin (penuh) & operator (read-only) lihat stok, in/out harian, feed pergerakan real-time |

## In-Scope

- Autentikasi + 2 role (admin, operator).
- Master produk & vendor (CRUD, import Excel berulang).
- Scan masuk / scan keluar via kamera HP (PWA).
- Input manual masuk / keluar dari dashboard (dengan alasan).
- Ledger `stock_movements` sebagai satu-satunya sumber stok.
- Anti-double-input (client cooldown + mode teliti + server idempotency via `scan_uuid`).
- Offline cache master + antrian scan + auto-sync + indikator status.
- Dashboard & reporting dasar (metrik, feed, grafik in/out, produk paling sering keluar).
- Tema UI Pokéball orisinal + bunyi orisinal.

## Out-of-Scope (untuk rilis ini)

- Scanner hardware USB.
- Multi-gudang / multi-lokasi stok (asumsi 1 lokasi).
- Integrasi POS / akuntansi eksternal.
- Manajemen harga jual, pembelian, atau faktur.
- Aplikasi native iOS/Android (hanya PWA).
- Laporan keuangan / analitik lanjutan di luar metrik yang disebut.
- Aset atau SFX asli Pokémon (dilarang, lihat [[11 - UI-UX & Tema Pokemon]]).

## Note Terkait

- [[01 - Ringkasan Proyek]]
- [[04 - Functional Requirements]]
- [[12 - Asumsi, Batasan & Risiko]]
