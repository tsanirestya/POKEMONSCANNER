---
title: 15 - Roadmap Implementasi
type: brd
status: in-progress # semua fase kode selesai; sisa 1 item manual (uji printer fisik BO-2)
tags: [pokemonscanner, roadmap]
created: 2026-07-02
updated: 2026-07-08
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

# Modul Booking Order (Fase BO)

Acuan: [[17 - Spesifikasi Booking Order]], [[13 - Decision Log]] DEC-21..24. Fase 0–9 selesai; fase BO menumpang infrastruktur existing (scanner `resources/js/scan.js`, middleware `role:`, pola Livewire SFC + guard `boot()`, design system, Maatwebsite Excel). **Booking tidak pernah menyentuh `stock_movements` / `stok_sekarang`.**

## Fase BO-0 — Skema & Role SPG ✅ SELESAI (2026-07-07)

- [x] Migration `bookings` (`booking_code` VARCHAR UNIQUE, `user_id` FK, `status` enum `printed|void|checked_ok|checked_selisih`, `catatan_keeper` nullable, timestamps) — `2026_07_07_100001_create_bookings_table.php`
- [x] Migration `booking_items` (`booking_id` FK cascade, `product_id` FK, `qty` min 1) — `2026_07_07_100002_create_booking_items_table.php` (tanpa timestamps sesuai spek; min 1 di-enforce app-side, kolom `default(1)`)
- [x] Model `Booking` + `BookingItem` + relasi (`Booking->items`, `->user`; `Product->bookingItems`) — konstanta status di `Booking`, `BookingItem::$timestamps = false`, `BookingFactory` untuk tes
- [x] Role `spg`: perluas kolom/validasi role (`admin|operator|spg`), method `isSpg()` di `User` — migration `2026_07_07_100000_add_spg_to_users_role_enum.php` (enum `->change()` native Laravel 12, jalan di MySQL & SQLite; `down()` konversi spg→operator dulu), relasi `User->bookings`
- [x] Form user admin (`⚡users.blade.php`): opsi role SPG + validasi `in:admin,operator,spg` — label role di tabel jadi map admin/operator/spg
- [x] Guard delete user: cek juga riwayat `bookings` (pola cek `stock_movements` existing)
- [x] Redirect login SPG → halaman Booking; nav layout menu SPG (Booking + Riwayat saja) — `⚡login.blade.php` match admin→dashboard / spg→booking / default→scan; nav SPG hanya link Booking (Riwayat menyusul Fase BO-3); admin dapat Booking di dropdown "Lainnya" (bottom nav) + top-level (desktop), operator dapat link Booking
- [x] Route group `role:admin,operator,spg` untuk `/booking`; SPG 403 di `/scan`, `/laporan`, `/dashboard` — `/booking` sementara placeholder blade (`resources/views/booking.blade.php`), komponen Livewire diisi Fase BO-1; SPG juga 403 di `/scan/submit`, `/scan/master-cache`, `/admin/*`
- [x] Generator `booking_code` format `BK-YYMMDD-XXXX` unik + tes tabrakan — `Booking::generateCode()` (WIB via app timezone, suffix `strtoupper(Str::random(4))`, retry maks 10× + UNIQUE constraint backstop); tes tabrakan pakai `Str::createRandomStringsUsingSequence`
- [x] Kolom `nomor_urut` (1–999, reset harian WIB, DEC-25) + `Booking::nextNomorUrut()` (max hari ini + 1, wrap 999→1, `lockForUpdate` — panggil dalam transaksi saat simpan di BO-1) + `nomorUrutPadded()` 3 digit untuk struk BO-2

Tes: `tests/Feature/BookingSchemaRoleTest.php` (10 tes — redirect login SPG, akses & 403 per role, CRUD user SPG, guard delete, format+tabrakan `booking_code`, booking+item TIDAK menyentuh `stock_movements`/`stok_sekarang`, cascade delete item). Suite penuh 50 tes hijau.

## Fase BO-1 — Halaman Booking SPG ✅ SELESAI (2026-07-07)

- [x] Komponen Livewire SFC `⚡booking` (guard `boot()`: admin/operator/spg) — [`components/⚡booking.blade.php`](../resources/views/components/⚡booking.blade.php), keranjang state server-side (key = product_id)
- [x] Tambah item via scan kamera — reuse `scan.js` mode lookup-only — logic bersama diekstrak ke [`resources/js/scanner-core.js`](../resources/js/scanner-core.js) (bunyi Web Audio, `startCameraScanner` BarcodeDetector+ZXing, `createScanGate` cooldown/mode-teliti); `scan.js` di-refactor memakainya (perilaku identik), `resources/js/booking.js` baru panggil `$wire.addByBarcode()` — dikenal → keranjang + bunyi sukses, tak dikenal → tolak + bunyi error (DEC-05), **tanpa** submit movement; gagal koneksi → bunyi error "booking butuh online" (DEC-08)
- [x] Tambah item via pencarian nama produk (min 2 huruf, juga match prefix barcode, tampilkan `stok_sekarang` — DEC-22)
- [x] Keranjang: ubah qty (+/− /input langsung, min 1), hapus item, item sama di-scan lagi = qty+1
- [x] Simpan booking (transaksi DB, `booking_code` unik via `generateCode()`, `nomor_urut` via `nextNomorUrut()` dalam transaksi, status `printed`) → redirect `/booking/{booking}/struk` — view placeholder [`booking-struk.blade.php`](../resources/views/booking-struk.blade.php) (nomor urut besar + code + item; print thermal dibangun BO-2); struk hanya bisa dilihat pemilik atau admin/operator
- [x] Tes feature: `tests/Feature/BookingCartTest.php` (15 tes — guard guest/3 role, scan dikenal/duplikat/tak dikenal, cari+stok, qty/hapus, simpan+item, stok TIDAK berubah, keranjang kosong ditolak, produk terhapus tidak bikin booking yatim, akses struk per role). Suite penuh 68 tes hijau.

## Fase BO-2 — Struk Thermal ✅ SELESAI (2026-07-07) — kode selesai; uji printer fisik pending (item terakhir)

- [x] Install JsBarcode (npm, bundle Vite) — Code128 client-side (DEC-24); entry baru [`resources/js/struk.js`](../resources/js/struk.js) render `svg[data-barcode]`
- [x] View struk print: CSS `@media print` 58mm (`@page size 58mm auto; margin 0`, hanya blok `.struk` tercetak) — **nomor urut harian dicetak besar** (3 digit ala nomor antrian, DEC-25), `booking_code` (barcode Code128 + teks), tanggal-jam WIB, nama SPG, daftar item+qty, total item, tanpa harga, footer "bukan bukti pembayaran" — [`booking-struk.blade.php`](../resources/views/booking-struk.blade.php)
- [x] Tombol Cetak (`window.print()`, target RawBT di Android) + tombol "Booking baru"; tes struk di `BookingCartTest` diperkuat (barcode svg, nama SPG, total, window.print)
- [ ] Uji cetak di printer thermal Bluetooth nyata (barcode terbaca scanner) — **manual oleh user**; kalau barcode terlalu rapat saat di-scan, naikkan `height`/`width` di `struk.js` atau kecilkan padding `.struk` print

## Fase BO-3 — Riwayat & Void SPG ✅ SELESAI (2026-07-07)

- [x] Daftar booking milik user (default hari ini, filter tanggal `input date`, tanggal invalid jatuh ke hari ini), item + status — komponen SFC [`components/⚡booking-riwayat.blade.php`](../resources/views/components/⚡booking-riwayat.blade.php) guard `boot()` 3 role; **SPG hanya miliknya, admin/operator melihat semua + nama SPG** (konsisten matriks role spek §3); route `/booking/riwayat`; nav "Riwayat" semua role (desktop + bottom nav; admin via dropdown "Lainnya")
- [x] Cetak ulang struk dari riwayat — link ke `/booking/{id}/struk` existing (disembunyikan untuk booking void)
- [x] Void booking status `printed` (konfirmasi `wire:confirm`; ubah status → `void`, bukan hapus; status selain `printed` — sudah dicek keeper / sudah void — ditolak dengan pesan error) — FR-BOOK-03; SPG hanya void miliknya (403), admin/operator boleh semua
- [x] Tes feature: [`tests/Feature/BookingRiwayatTest.php`](../tests/Feature/BookingRiwayatTest.php) (14 tes — guard guest/role komponen+route, SPG hanya lihat miliknya, admin/operator lihat semua, filter tanggal default hari ini, tanggal invalid, item+status+link cetak ulang, void ubah status bukan hapus, SPG tak bisa void milik SPG lain, admin/operator bisa void, status dicek/void tak bisa di-void, void tidak menyentuh stok/item). Suite penuh 82 tes hijau.

## Fase BO-4 — Rekonsiliasi Store Keeper + Dashboard ✅ SELESAI (2026-07-08)

- [x] Halaman Rekonsiliasi (`role:admin,operator` + guard `boot()`; SPG 403): filter tanggal — komponen SFC [`components/⚡booking-rekonsiliasi.blade.php`](../resources/views/components/⚡booking-rekonsiliasi.blade.php), route `/booking/rekonsiliasi`; nav "Rekonsiliasi" admin (desktop + dropdown "Lainnya") & operator (desktop + bottom nav)
- [x] Agregat per produk: qty ter-booking vs qty keluar ledger (`stock_movements` out) — FR-BOOK-04 — gabungan dua sisi (produk hanya-booking & hanya-ledger ikut tampil), kolom selisih = ter-booking − keluar; **booking void tidak dihitung** (barang dianggap kembali ke rak, konsisten semantik void FR-BOOK-03)
- [x] Daftar booking per hari: tandai `checked_ok` / `checked_selisih` + `catatan_keeper` — booking void tidak bisa ditandai; status di luar dua nilai checked ditolak (400); menandai TIDAK menyentuh stok
- [x] Export Excel rekonsiliasi (reuse pola Maatwebsite DEC-19, barcode string DEC-06) — [`BookingRekonsiliasiExport`](../app/Exports/BookingRekonsiliasiExport.php) 2 sheet (`BookingAgregatSheet` barcode kolom A string; `BookingDaftarSheet` nomor urut "001" kolom A string), route `/booking/rekonsiliasi/export?tanggal=`, controller `BookingRekonsiliasiExportController`
- [x] Metrik dashboard admin: booking & item ter-booking hari ini — FR-BOOK-05 — 2 stat-card baru di `dashboard.metrics` (non-void), otomatis tampil juga di `/laporan` operator (komponen bersama)
- [x] Tes feature: agregat benar, guard role, export — [`tests/Feature/BookingRekonsiliasiTest.php`](../tests/Feature/BookingRekonsiliasiTest.php) (16 tes — guard guest/SPG/admin/operator komponen+route+export, agregat dua sisi, void tak dihitung, filter tanggal default hari ini, tandai OK/selisih+catatan, catatan kosong → null, void/status ngawur ditolak, tandai tidak menyentuh stok, export `Excel::fake` nama file bertanggal + export nyata xlsx, metrik dashboard). Suite penuh 98 tes hijau.

## Cara Pakai (multi-sesi chat)

1. Mulai chat baru → minta lanjut ke fase berikutnya (sebut nomor fase / nama).
2. Kerjakan checklist fase itu saja.
3. Centang item selesai di note ini, update `status` fase (`in-progress` → `done`) dan `updated:` di frontmatter.
4. Update ringkasan progres di [`CLAUDE.md`](../CLAUDE.md) → "Status Tahap Ini".

## Note Terkait

- [[00 - Index (MOC)]]
- [[04 - Functional Requirements]]
- [[13 - Decision Log]]
