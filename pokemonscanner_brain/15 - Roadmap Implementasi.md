---
title: 15 - Roadmap Implementasi
type: brd
status: done
tags: [pokemonscanner, roadmap]
created: 2026-07-02
updated: 2026-07-03
---



# 15 - Roadmap Implementasi

← [[00 - Index (MOC)]]

Tag: `#pokemonscanner/roadmap`

Peta fase pengerjaan kode, urut dependency. **Satu fase = satu sesi chat baru** (biar konteks tetap ringkas). Centang tiap item selesai; update `status` fase & tanggal `updated` di atas. Cek [`CLAUDE.md`](../CLAUDE.md) bagian "Status Tahap Ini" untuk ringkasan progres saat ini.

---

## Fase 0 — Setup Proyek ✅ SELESAI (2026-07-02)

- [x] Composer + Laravel 12 skeleton
- [x] Install `livewire/livewire`
- [x] Konfigurasi `.env` → MySQL, DB `pokemonscanner` dibuat
- [x] Migration `users` (+kolom `role` enum admin/operator)
- [x] Migration `vendors`, `products`, `stock_movements` (append-only, `scan_uuid` unique), urutan FK benar
- [x] Model `User` (+`isAdmin()`/`isOperator()`), `Vendor`, `Product`, `StockMovement` + relasi
- [x] `php artisan migrate` sukses, skema tervalidasi cocok [[06 - Data Model & ERD]]

## Fase 1 — Auth & Role ✅ SELESAI (2026-07-02)

Acuan: [[03 - Stakeholder & Role]], [[04 - Functional Requirements]] Modul AUTH.

- [x] Scaffold login (Laravel auth / Livewire form) — `auth.login` Livewire SFC di [`resources/views/components/auth/⚡login.blade.php`](../resources/views/components/auth/⚡login.blade.php)
- [x] Middleware role-based (`admin`/`operator`) — FR-AUTH-02, [`EnsureUserHasRole`](../app/Http/Middleware/EnsureUserHasRole.php) alias `role:`
- [x] Redirect setelah login sesuai role (admin → dashboard, operator → layar scan)
- [x] Seeder user admin default (buat login pertama, karena login wajib online — [[09 - Spesifikasi Offline Sync]]) — `admin@pokemonscanner.test` / `password`
- [x] Route/menu tersembunyi untuk role tak berhak (AC2 FR-AUTH-02) — nav layout `@if isAdmin`, route `/dashboard` 403 untuk operator

## Fase 2 — Master Produk & Vendor (Admin) ✅ SELESAI (2026-07-02)

Acuan: [[04 - Functional Requirements]] Modul PROD & VEND, [[03 - Stakeholder & Role]].

- [x] CRUD Vendor (admin) — FR-VEND-01 — [`components/admin/⚡vendors.blade.php`](../resources/views/components/admin/⚡vendors.blade.php), route `/admin/vendors`
- [x] CRUD Produk (admin), barcode unik & VARCHAR — FR-PROD-01 — [`components/admin/⚡products.blade.php`](../resources/views/components/admin/⚡products.blade.php), route `/admin/products`
- [x] Form tambah produk baru + barcode manual (menu terpisah dari scan) — FR-PROD-02 (menu admin, terpisah dari `/scan`)

## Fase 3 — Import Excel ✅ SELESAI (2026-07-02)

Acuan: [[07 - Spesifikasi Import Excel]], [[04 - Functional Requirements]] Modul IMPORT.

- [x] Upload Excel (kolom `NO`, `BRAND`, `BARCODE`, `PRODUCT NAME`), `BARCODE` dibaca string — `WithCustomValueBinder` di [`app/Imports/ProductsImport.php`](../app/Imports/ProductsImport.php) paksa semua cell jadi string (cegah scientific notation / leading zero hilang)
- [x] Mapping vendor otomatis via `firstOrCreate`
- [x] Upsert produk by barcode
- [x] Cleaning mojibake (double UTF-8-as-Latin1 repair, sampai 2 pass)
- [x] Pastikan import tidak pernah menyentuh stok (diuji: upload ulang → 0 dibuat, N diupdate, stok tetap 0)
- [x] Ringkasan hasil: X dibuat / Y diupdate / Z error — komponen [`components/admin/⚡products-import.blade.php`](../resources/views/components/admin/⚡products-import.blade.php), route `/admin/products/import`

## Fase 4 — Scan & Anti-Double-Input (PWA) ✅ SELESAI (2026-07-02)

Acuan: [[08 - Spesifikasi Scan & Anti-Double-Input]], [[04 - Functional Requirements]] Modul SCAN & MOVE (jalur scan).

- [x] PWA shell: manifest, service worker dasar, HTTPS lokal untuk tes kamera — [`public/manifest.json`](../public/manifest.json), [`public/sw.js`](../public/sw.js) (HTTPS lokal: pakai `php artisan serve` di balik tunnel/mkcert, kamera butuh secure context)
- [x] UI scan: viewfinder, kotak bidik, torch, angka besar, toggle mode teliti — [`components/⚡scan.blade.php`](../resources/views/components/⚡scan.blade.php)
- [x] Decode `BarcodeDetector API` + fallback ZXing — [`resources/js/scan.js`](../resources/js/scan.js) (`@zxing/browser`)
- [x] Bunyi via Web Audio API: sukses/error/duplikat (orisinal, bukan SFX Pokémon — [[13 - Decision Log]] DEC-10) — oscillator tones sintetis di `scan.js`
- [x] Cooldown per-barcode (default ±1 dtk, configurable — DEC-13) — input UI `cooldownMs`, default 1000ms
- [x] Mode teliti: keluar frame = 3 frame tanpa deteksi, configurable — DEC-17 — input UI `missFramesThreshold`, default 3
- [x] `scan_uuid` per scan + endpoint idempotent (server tolak UUID kembar) — `StockMovement.scan_uuid` UNIQUE, `QueryException` ditangkap → event `scan-duplicate-server`
- [x] Barcode tak dikenal → tolak + bunyi error, tidak buat produk (DEC-05) — cek `Product::where('barcode', ...)` sebelum tulis movement
- [x] Movement scan masuk (+1) / keluar (−1) tercatat ke `stock_movements`, update cache `stok_sekarang` — `DB::transaction` + `lockForUpdate`, diuji `tests/Feature/ScanFlowTest.php` (4 test)

## Fase 5 — Input Manual (Dashboard) ✅ SELESAI (2026-07-02)

Acuan: [[04 - Functional Requirements]] FR-MOVE-01, [[10 - Dashboard & Reporting]].

- [x] Form input masuk manual (qty bebas, alasan opsional) — [`components/admin/⚡manual-input.blade.php`](../resources/views/components/admin/⚡manual-input.blade.php)
- [x] Form input keluar manual (qty bebas, alasan **wajib**) — validasi `alasan` required bila `tipe=out`
- [x] Movement `metode = manual` tercatat, update cache stok — `DB::transaction` + `lockForUpdate`, sama pola dengan scan (Fase 4)
- [x] Guard: hanya admin, wajib online — route `/dashboard` sudah `role:admin`; Livewire request sinkron (tidak lewat antrian offline Fase 6) — diuji `tests/Feature/ManualInputFlowTest.php` (4 test)

## Fase 6 — Offline Sync

Acuan: [[09 - Spesifikasi Offline Sync]], [[04 - Functional Requirements]] Modul OFFLINE.

- [x] Cache master produk ke IndexedDB — `refreshMasterCache()` di `resources/js/offline-sync.js`, sumber data `GET /scan/master-cache` (`ScanSyncController::masterCache`)
- [x] Antrian scan offline (`scan_uuid`, barcode, tipe, waktu) di IndexedDB — store `queue`, diisi `queueOffline()` di `resources/js/scan.js` saat submit gagal/offline
- [x] Auto-sync saat online kembali, kirim antrian satu per satu — `flushQueue()` FIFO ke `POST /scan/submit`, dipicu event `online` + interval 20 dtk
- [x] Indikator status: offline → notifikasi "cari sinyal untuk sync" + jumlah task belum ter-sync; online → waktu sync terakhir (DEC-16) — UI di `resources/views/components/⚡scan.blade.php`
- [x] Tombol "Sync sekarang" — `manualSync()`
- [x] Refresh cache master saat online (produk baru dikenali ulang) — dipanggil saat `init()` & event `online`
- [x] Logic scan (Livewire `scan()` & endpoint HTTP `POST /scan/submit`) diekstrak ke `App\Services\ScanService` supaya idempotency/anti-double-input konsisten di kedua jalur — diuji `tests/Feature/ScanSyncTest.php` (5 test) + `ScanFlowTest.php` tetap hijau

## Fase 7 — Dashboard & Reporting

Acuan: [[10 - Dashboard & Reporting]], [[04 - Functional Requirements]] Modul DASH.

- [x] Metrik ringkas: total produk, total stok, in/out hari ini
- [x] Dashboard read-only untuk operator (menu terpisah dari layar scan — DEC-11)
- [x] Feed pergerakan terakhir
- [x] Produk paling sering keluar (ranking)
- [x] Grafik in/out (rentang/default dinamis — DEC-15)

## Fase 8 — Tema UI/UX Pokéball

Acuan: [[11 - UI-UX & Tema Pokemon]]. Status: **done** 2026-07-03.

- [x] Palet merah/putih/hitam, layout kartu — design system di `resources/css/app.css` (`@theme` warna `poke-red`/`poke-black`/`poke-cream` + komponen `.card`, `.stat-card`, `.btn-*`, `.badge-*`, `.table-wrap`, `.tab-*`), diterapkan di semua layar (nav, login, scan, dashboard/metrics, laporan, admin vendor/produk/import/input-manual)
- [x] Animasi kecil saat scan sukses — `flashSuccess()` di `resources/js/scan.js` men-trigger animasi CSS (`.scan-count.bump`, `.reticle.flash`) tiap hasil sukses (online & offline-queued), tidak memblok scan berikutnya
- [x] Aset & bunyi orisinal (audit: pastikan tidak ada aset Pokémon asli — DEC-10, CON-06) — diaudit: satu-satunya aset gambar `public/icons/icon.svg` adalah bentuk geometris orisinal (lingkaran+pita+tombol), bunyi scan 100% Web Audio API sintetis (`beep()` di `scan.js`), tidak ada sprite/SFX berlisensi

Catatan: `resources/views/login.blade.php` sebelumnya tidak memuat `@vite` sama sekali (halaman login tanpa CSS/JS) — diperbaiki sekaligus saat theming.

## Fase 9 — Hardening (NFR) ✅ SELESAI (2026-07-03)

Acuan: [[05 - Non-Functional Requirements]], [[12 - Asumsi, Batasan & Risiko]].

- [x] Cek performa: loop scan ≤1.5 detik/scan — direview di kode: `requestAnimationFrame` decode loop (`resources/js/scan.js` `loopBarcodeDetector`) tidak diblok oleh `countScan()` (fire-and-forget, async tanpa `await` di `handleFrame`), cooldown default 1000ms < anggaran 1.5 dtk (NFR-PERF-01/02). Gap ditemukan & dicatat: [[14 - Open Questions]] OQ-08 — jalur online tetap round-trip server untuk validasi barcode (bukan cache lokal dulu) berbeda dari NFR-PERF-03 literal; belum diubah karena berisiko ke alur scan yang sudah stabil, butuh keputusan eksplisit.
- [x] Review keamanan: otorisasi server-side, idempotency, password hashing — **gap ditemukan & diperbaiki**: 5 komponen Livewire (`admin.vendors`, `admin.products`, `admin.products-import`, `admin.manual-input`, `scan`, `dashboard.metrics`) tidak punya guard di dalam komponennya sendiri; route middleware (`role:admin`) hanya menjaga *initial page load*, bukan endpoint aksi Livewire (`/livewire/update` cuma pakai middleware `web`, bukan `auth`/`role`). Ditambahkan `boot()` (bukan `mount()` — `boot()` jalan di *setiap* hydration/request, `mount()` cuma sekali di awal) berisi `abort_unless(auth()->user()?->isAdmin(), 403)` di 4 komponen admin-only, dan `abort_unless(auth()->check(), 403)` di `scan` & `dashboard.metrics`. Diuji `tests/Feature/HardeningTest.php`. Idempotency `scan_uuid` & password hashing (`'hashed'` cast) sudah terverifikasi benar sejak Fase 4.
- [x] Uji reliability: double-count, sync ganda, import berulang — `tests/Feature/HardeningTest.php` (scan_uuid duplikat via `/scan/submit`), `tests/Feature/ScanFlowTest.php` + `ScanSyncTest.php` (sudah ada dari Fase 4/6), `tests/Feature/ProductsImportTest.php` baru (import ulang barcode sama tidak reset stok yang sudah bergerak — NFR-REL-04)
- [x] Rekonsiliasi `stok_sekarang` vs agregasi ledger (job/perintah artisan) — `php artisan stock:reconcile` (opsional `--fix`), [`app/Console/Commands/ReconcileStock.php`](../app/Console/Commands/ReconcileStock.php), diuji `tests/Feature/HardeningTest.php`

---

## Cara Pakai (multi-sesi chat)

1. Mulai chat baru → minta lanjut ke fase berikutnya (sebut nomor fase / nama).
2. Kerjakan checklist fase itu saja.
3. Centang item selesai di note ini, update `status` fase (`in-progress` → `done`) dan `updated:` di frontmatter.
4. Update ringkasan progres di [`CLAUDE.md`](../CLAUDE.md) → "Status Tahap Ini".

## Note Terkait

- [[00 - Index (MOC)]]
- [[04 - Functional Requirements]]
- [[13 - Decision Log]]
