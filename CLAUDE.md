# claude.md — PokemonScanner

## Ringkasan Proyek

**PokemonScanner** adalah aplikasi manajemen stok berbasis **scan barcode kamera HP** untuk produk Pokémon (data awal dari KidzStation, 109 produk). Operator menghitung barang masuk/keluar dengan mengarahkan kamera ke barcode — tiap scan otomatis +1/−1 dengan umpan balik bunyi. Stok tidak diinput sebagai angka mutlak; dibangun murni dari ledger append-only `stock_movements` (mulai 0). Harus tetap jalan saat sinyal jelek (offline cache + antrian + auto-sync). **Tech stack:** Laravel monolith (Blade + Livewire/Alpine.js) + MySQL; frontend scanner sebagai **PWA** installable, wajib HTTPS (akses kamera), decode `BarcodeDetector API` dengan fallback ZXing.

## Status Tahap Ini

**BRD selesai (final), 2026-07-02.** Semua 14 note BRD di `pokemonscanner_brain/` berstatus `final`, seluruh Open Questions terjawab (lihat [`13 - Decision Log.md`](pokemonscanner_brain/13%20-%20Decision%20Log.md) DEC-01..17). **Sekarang tahap implementasi**, dikerjakan bertahap via [`15 - Roadmap Implementasi.md`](pokemonscanner_brain/15%20-%20Roadmap%20Implementasi.md) — checklist per fase, satu fase = satu sesi chat baru. Kode harus mengikuti keputusan terkunci di bawah & Decision Log — jangan mengarang keputusan baru, catat gap baru di `14 - Open Questions.md`.

**Progres fase (cek detail & centang di roadmap):**
- ✅ Fase 0 — Setup Proyek (Laravel + Livewire + MySQL + migration + model) — SELESAI 2026-07-02
- ✅ Fase 1 — Auth & Role (login Livewire, middleware role, seeder admin, redirect by role) — SELESAI 2026-07-02
- ✅ Fase 2 — Master Produk & Vendor (Admin) (CRUD vendor + produk, barcode manual) — SELESAI 2026-07-02
- ✅ Fase 3 — Import Excel (upload xlsx/xls/csv, barcode dipaksa string, vendor `firstOrCreate`, upsert by barcode, cleaning mojibake, tidak sentuh stok, ringkasan X/Y/Z) — SELESAI 2026-07-02
- ✅ Fase 4 — Scan & Anti-Double-Input (PWA) (manifest+SW, viewfinder, `BarcodeDetector`+ZXing fallback, bunyi Web Audio orisinal, cooldown & mode teliti configurable, `scan_uuid` idempotent, barcode tak dikenal ditolak, movement scan +1/−1) — SELESAI 2026-07-02
- ✅ Fase 5 — Input Manual (Dashboard) (form masuk qty bebas alasan opsional, form keluar qty bebas alasan wajib, `metode=manual` ke ledger + update cache stok via transaksi terkunci, guard admin-only via route `role:admin`) — SELESAI 2026-07-02
- ✅ Fase 6 — Offline Sync (cache master via IndexedDB, antrian scan `scan_uuid`+barcode+tipe+waktu, auto-sync FIFO saat online/tombol manual, indikator offline+pending+waktu sync terakhir, `POST /scan/submit` + `GET /scan/master-cache`, logic scan diekstrak ke `ScanService`) — SELESAI 2026-07-02
- ✅ Fase 7 — Dashboard & Reporting (komponen Livewire `dashboard.metrics` dipakai bareng di dashboard admin & `/laporan` operator; metrik total produk/stok/in-out hari ini, feed pergerakan, ranking produk paling sering keluar, grafik in/out CSS-bar dengan rentang 7/30/90 hari) — SELESAI 2026-07-02
- ✅ Fase 8 — Tema UI/UX Pokéball (design system Tailwind v4 merah/putih/hitam di `resources/css/app.css`, layout kartu di semua layar, animasi CSS singkat saat scan sukses dipicu dari `resources/js/scan.js`, diaudit tanpa aset/SFX Pokémon asli) — SELESAI 2026-07-03
- ✅ Fase 9 — Hardening (NFR) (gap keamanan ditemukan+ditutup: endpoint aksi Livewire `/livewire/update` tidak diwarisi middleware route `role:admin`/`auth` — ditambah guard `boot()` server-side di 6 komponen; `php artisan stock:reconcile [--fix]` rekonsiliasi cache vs ledger; tes reliability scan-duplikat & import-berulang; gap NFR-PERF-03 jalur online dicatat sebagai OQ-08 di `14 - Open Questions.md`) — SELESAI 2026-07-03

**Semua 10 fase roadmap implementasi (Fase 0–9) selesai.**

**Modul Booking Order (Fase BO, mulai 2026-07-07)** — spesifikasi di [`17 - Spesifikasi Booking Order.md`](pokemonscanner_brain/17%20-%20Spesifikasi%20Booking%20Order.md), keputusan DEC-21..25:
- ✅ Fase BO-0 — Skema & Role SPG (migration `bookings`+`booking_items`, role `spg`, redirect & nav, generator `booking_code`) — SELESAI 2026-07-07
- ✅ Fase BO-1 — Halaman Booking SPG (komponen `⚡booking` guard 3 role; scan lookup-only reuse logic kamera+bunyi+gate diekstrak ke `resources/js/scanner-core.js`, `scan.js` di-refactor memakainya; cari nama+stok; keranjang qty; simpan transaksi → `/booking/{id}/struk` placeholder, print thermal menyusul BO-2) — SELESAI 2026-07-07
- ✅ Fase BO-2 — Struk Thermal (JsBarcode via npm + entry `struk.js`; view struk `@media print` 58mm: nomor urut besar, barcode Code128+teks, tanggal WIB, SPG, item+qty, total, tanpa harga; tombol Cetak `window.print()` untuk RawBT) — kode SELESAI 2026-07-07; **uji cetak printer thermal fisik pending (manual user)**
- ✅ Fase BO-3 — Riwayat & Void SPG (komponen `⚡booking-riwayat` guard 3 role; daftar booking default hari ini + filter tanggal, SPG hanya miliknya / admin+operator semua booking; cetak ulang struk dari riwayat; void hanya status `printed` via `wire:confirm` — ubah status, bukan hapus; route `/booking/riwayat` + nav Riwayat semua role) — SELESAI 2026-07-07
- ✅ Fase BO-4 — Rekonsiliasi Store Keeper (komponen `⚡booking-rekonsiliasi` guard admin+operator, SPG 403; filter tanggal; agregat per produk qty ter-booking non-void vs keluar ledger + selisih; tandai booking `checked_ok`/`checked_selisih` + `catatan_keeper`, void tak bisa ditandai; export Excel 2 sheet `BookingRekonsiliasiExport` route `/booking/rekonsiliasi/export`; metrik dashboard booking & item ter-booking hari ini di `dashboard.metrics`) — SELESAI 2026-07-08

**Semua fase Booking Order (BO-0..BO-4) selesai.** Sisa item manual: uji cetak struk di printer thermal fisik (BO-2, oleh user).

> Saat fase selesai: centang checklist di [`15 - Roadmap Implementasi.md`](pokemonscanner_brain/15%20-%20Roadmap%20Implementasi.md), lalu update tanda ✅/⬜ di daftar ini.

## Keputusan Arsitektur Terkunci (final)

1. **Scanner = kamera HP** (bukan USB). `BarcodeDetector API` (Chrome Android), fallback ZXing. Viewfinder, kotak bidik, torch, angka besar. PWA, wajib HTTPS.
2. **Scan = +1/−1 otomatis + bunyi** (Web Audio API). Bunyi dibedakan: sukses (klik), tak dikenal (buzz), duplikat (nada beda).
3. **Anti-double-input berlapis:** cooldown per-barcode (default ±1 dtk, **dapat dikonfigurasi**, DEC-13); barcode beda langsung dihitung; indikator "siap scan berikutnya"; toggle "mode teliti" (keluar frame = **3 frame decode berturut tanpa deteksi**, dapat dikonfigurasi, DEC-17); server idempotent via `scan_uuid` UNIQUE.
4. **Empat jalur pergerakan, satu ledger `stock_movements`:** Scan Masuk (+1), Scan Keluar (−1), Input Masuk manual (bebas, alasan opsional), Input Keluar manual (bebas, alasan wajib). Stok mulai 0.
5. **Barcode tak dikenal saat scan → DITOLAK + bunyi error.** Tambah produk baru lewat menu terpisah (boleh barcode manual). Validasi barcode: **exact match ke master, tanpa checksum EAN-13/UPC-A** (DEC-12).
6. **Import Excel master** (kolom `NO`, `BRAND`, `BARCODE`, `PRODUCT NAME`): `BRAND`→vendor via `firstOrCreate`; upsert produk by barcode; bersihkan mojibake (`POKÃÂÃÂ©MON`→`POKÉMON`); **tidak pernah menyentuh stok**; tampilkan ringkasan X dibuat/Y diupdate/Z error.
7. **Auth + 2 role:** Admin (master, vendor, user, import, input manual, dashboard penuh) & Operator (scan masuk/keluar + **dashboard read-only di menu terpisah**, DEC-11).
8. **Offline sync:** cache master (IndexedDB) + antrian scan (`scan_uuid`) + auto-sync. **Tanpa batas retensi antrian**; indikator status = saat offline tampilkan notifikasi "sedang offline, cari sinyal untuk sync" + jumlah task belum ter-sync, saat online tampilkan waktu sync terakhir (DEC-16). Accepted constraints: (a) stok offline = perkiraan, akurat setelah sync; (b) produk baru tak dikenal HP offline sampai sync ulang; (c) login pertama & input manual butuh online.
9. **Dashboard:** total produk/stok, in/out hari ini, feed pergerakan, produk paling sering keluar, grafik in/out (rentang/default **dinamis**, DEC-15); input manual berbasis alasan. **Peringatan stok menipis dihapus dari scope** (sementara, DEC-14).
10. **Tema Pokéball orisinal** (merah/putih/hitam, kartu, animasi kecil). **Jangan pakai aset/sprite/SFX asli Pokémon (IP berlisensi)** — hanya orisinal/terinspirasi.
11. **Booking Order (DEC-21..25):** catatan barang keluar rak oleh SPG, **BUKAN transaksi & TIDAK PERNAH menyentuh `stock_movements`/`stok_sekarang`** (tabel `bookings`+`booking_items` terpisah). Kasir tanpa redeem — POS jalan normal. Role ketiga `spg` (hanya booking, boleh lihat stok); store keeper = role operator existing. Struk thermal 58mm via print browser + RawBT, barcode Code128 (JsBarcode via npm), tanpa harga; **nomor urut harian 001–999 (reset per hari WIB) dicetak besar ala nomor antrian** — ID pasti tetap `booking_code` (DEC-25). BO online-only; void = ubah status, bukan hapus.

## Aturan Penting / JANGAN LAKUKAN

- **Barcode SELALU string (VARCHAR)**, tidak pernah integer (leading zero, campuran 12/13 digit).
- **Import Excel TIDAK PERNAH mengubah/mereset stok** — stok hanya dari `stock_movements`.
- **Booking Order TIDAK PERNAH mengubah stok** — tidak menulis `stock_movements`, tidak menyentuh `stok_sekarang` (DEC-21).
- **JANGAN** pakai aset/sprite/SFX asli Pokémon — hanya aset orisinal bertema.
- Semua keputusan di atas **final**. Jika menemukan gap, **jangan mengarang keputusan baru** — catat di `pokemonscanner_brain/14 - Open Questions.md` dengan tag `#pokemonscanner/question`.

## Lokasi & Struktur BRD

BRD tersimpan sebagai vault Obsidian di [`pokemonscanner_brain/`](pokemonscanner_brain/). Peta utama: [`00 - Index (MOC)`](pokemonscanner_brain/00%20-%20Index%20(MOC).md).

```
pokemonscanner_brain/
  00 - Index (MOC).md
  01 - Ringkasan Proyek.md
  02 - Tujuan & Ruang Lingkup.md
  03 - Stakeholder & Role.md
  04 - Functional Requirements.md
  05 - Non-Functional Requirements.md
  06 - Data Model & ERD.md
  07 - Spesifikasi Import Excel.md
  08 - Spesifikasi Scan & Anti-Double-Input.md
  09 - Spesifikasi Offline Sync.md
  10 - Dashboard & Reporting.md
  11 - UI-UX & Tema Pokemon.md
  12 - Asumsi, Batasan & Risiko.md
  13 - Decision Log.md
  14 - Open Questions.md
```

## Skema Database (acuan)

- `vendors`: id, nama, timestamps.
- `products`: id, barcode (**VARCHAR**, UNIQUE, index), nama_produk, vendor_id (FK), stok_sekarang (cache, default 0), timestamps.
- `stock_movements` (append-only): id, product_id (FK), tipe (`in`/`out`), qty (default 1), metode (`scan`/`manual`), alasan (nullable), scan_uuid (UNIQUE, nullable), user_id (FK), created_at.
- `users`: default Laravel + kolom role (`admin`/`operator`/`spg`).
- `bookings` (modul BO, terpisah dari stok): id, booking_code (**VARCHAR**, UNIQUE, format `BK-YYMMDD-XXXX`), nomor_urut (smallint unsigned, 1–999 reset harian WIB, DEC-25), user_id (FK), status (`printed`/`void`/`checked_ok`/`checked_selisih`), catatan_keeper (nullable), timestamps.
- `booking_items`: id, booking_id (FK cascade), product_id (FK), qty (min 1).

## Guidance Pengisian & Pemeliharaan Obsidian

- **Frontmatter YAML** wajib di tiap note:
  ```yaml
  ---
  title: <judul note>
  type: brd
  status: draft        # draft | review | final
  tags: [pokemonscanner, <kategori>]
  created: <YYYY-MM-DD>
  updated: <YYYY-MM-DD>
  ---
  ```
- **Wikilink** antar-note pakai `[[Nama Note]]`. Setiap note punya minimal 1 link balik ke `[[00 - Index (MOC)]]`.
- **Taksonomi tag** konsisten: `#pokemonscanner/functional`, `/nfr`, `/data`, `/scan`, `/offline`, `/decision`, `/question`.
- **`00 - Index (MOC)`** = peta utama; berisi daftar ber-link ke semua note + status.
- **Atomic notes:** satu note = satu topik. Kalau kepanjangan, pecah + hubungkan wikilink.
- **Decision Log & Open Questions** = daftar ber-tanggal, entri baru ditambah di atas (reverse chronological).
- Penamaan file pakai prefix nomor urut + judul deskriptif (agar urut di sidebar Obsidian).
- Saat mengubah note, perbarui field `updated:`.
