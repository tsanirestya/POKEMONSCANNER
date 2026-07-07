---
title: 13 - Decision Log
type: brd
status: final
tags: [pokemonscanner, decision]
created: 2026-07-02
updated: 2026-07-07
---

# 13 - Decision Log

← [[00 - Index (MOC)]]

Tag: `#pokemonscanner/decision`

Keputusan terkunci (final). Reverse chronological — keputusan baru di atas. DEC-01..17 bertanggal 2026-07-02 (keputusan awal proyek).

---

### DEC-25 — Nomor urut harian di booking pass (001–999, tampil besar)
**2026-07-07** · Selain `booking_code`, tiap booking punya **nomor urut harian** (`nomor_urut`, 1–999, reset tiap hari WIB) yang dicetak **besar** di struk sebagai identitas cepat ala nomor antrian. `booking_code` tetap satu-satunya ID unik; nomor urut hanya alat rujuk manusia, ditampilkan 3 digit (`001`). Jika satu hari melewati 999, berputar kembali ke 001 (duplikat tampilan diterima — referensi pasti tetap `booking_code`). **Alasan:** kasir/keeper/SPG lebih cepat merujuk "booking 042" daripada kode alfanumerik panjang. → [[17 - Spesifikasi Booking Order]]

### DEC-24 — Struk booking: thermal 58mm via print browser + RawBT, barcode Code128
**2026-07-07** · Struk Booking Order dicetak ke printer thermal Bluetooth 58mm: halaman print CSS 58mm + `window.print()`, target printer via app RawBT (virtual printer Android). Barcode `booking_code` = Code128, digenerate client-side (JsBarcode via npm, ikut bundle Vite). Struk tanpa harga (sistem tak menyimpan harga). **Alasan:** tanpa backend printing, tanpa driver khusus; JsBarcode lokal = tetap jalan tanpa CDN. → [[17 - Spesifikasi Booking Order]]

### DEC-23 — Store keeper = role operator existing
**2026-07-07** · Rekonsiliasi booking diakses admin + operator; tidak ada role keempat. SPG tidak bisa akses rekonsiliasi. **Alasan:** store keeper di lapangan = operator gudang; hindari kompleksitas role tambahan. → [[17 - Spesifikasi Booking Order]]

### DEC-22 — Role baru `spg`: hanya booking, boleh lihat stok
**2026-07-07** · Role user jadi `admin|operator|spg`. SPG hanya akses halaman Booking + riwayat miliknya (buat, cetak, void); tidak akses scan gudang/master/dashboard. SPG boleh melihat `stok_sekarang` saat memilih produk (info ketersediaan), tapi tak bisa mengubah stok. Login SPG redirect ke halaman Booking. **Alasan:** SPG fokus jualan; input harus hitungan detik; visibilitas stok membantu closing. → [[17 - Spesifikasi Booking Order]]

### DEC-21 — Modul Booking Order: catatan keluar rak, tanpa redeem, tanpa sentuh stok
**2026-07-07** · Booking Order menggantikan nota manual SPG: pilih/scan produk + qty → cetak struk ber-barcode → customer bawa ke kasir → kasir proses POS normal **tanpa redeem**. BO disimpan di tabel `bookings` + `booking_items`, **terpisah total dari `stock_movements` dan tidak pernah mengubah `stok_sekarang`**. Pencocokan akhir hari oleh store keeper (rekonsiliasi BO vs ledger vs POS vs stok fisik). Accepted constraints: (a) BO bukan bukti transaksi selesai — selisih tetap dianalisis via rekonsiliasi; (b) BO online-only (konsisten DEC-08); (c) void = ubah status, bukan hapus. **Alasan:** kesederhanaan — kasir nol perubahan; ledger stok tetap murni (konsisten DEC-04/07). → [[17 - Spesifikasi Booking Order]]

### DEC-20 — Utilitas admin: reset riwayat in/out (pra-production)
**2026-07-03** · Kartu danger di dashboard admin menghapus seluruh `stock_movements` dan menolkan `stok_sekarang` semua produk (produk/vendor/user tidak disentuh), dengan konfirmasi ketik RESET + dialog. Tujuan: bersihkan data testing sebelum go-live. Catatan: antrian offline HP yang belum sync akan tercatat lagi setelah reset — pastikan semua HP sync dulu. → [[10 - Dashboard & Reporting]]

### DEC-19 — Export report Excel dari Laporan & Dashboard
**2026-07-03** · Menu export report (.xlsx) ditambahkan atas permintaan owner, tersedia untuk admin (dashboard) & operator (/laporan). Isi: Sheet 1 = stok saat ini (barcode, nama, vendor, stok); Sheet 2 = pergerakan stok pada rentang tanggal (default 30 hari terakhir). Barcode dipaksa string di kedua sheet (konsisten DEC-06). **Alasan:** kebutuhan pelaporan keluar aplikasi (share/arsip). → [[10 - Dashboard & Reporting]]

### DEC-18 — Timezone aplikasi: GMT+7 (Asia/Jakarta)
**2026-07-03** · Timezone aplikasi diubah dari UTC ke `Asia/Jakarta` (configurable via `APP_TIMEZONE`). Berlaku untuk tampilan waktu, metrik "hari ini", dan rentang export. Timestamp lama di DB tetap; hanya interpretasi tampilan yang bergeser +7. **Alasan:** operasional gudang di WIB, metrik harian harus mengikuti hari lokal. → [[10 - Dashboard & Reporting]]

### DEC-17 — Mode teliti: keluar frame = 3 frame berturut tanpa deteksi
**2026-07-02** · "Keluar frame" didefinisikan sebagai 3 frame decode berturut-turut tanpa deteksi barcode yang sama. Nilai ini dapat dikonfigurasi, sama seperti cooldown (DEC-13). **Alasan:** cukup singkat agar operator tak menunggu lama, cukup lama untuk bukan sekadar noise/blur sesaat. → [[08 - Spesifikasi Scan & Anti-Double-Input]] (jawaban OQ-07)

### DEC-16 — Antrian offline: notifikasi status, tanpa batas retensi
**2026-07-02** · Tidak ada batas waktu/jumlah item antrian offline. Cukup notifikasi "sedang offline, cari sinyal untuk sync" + jumlah task belum ter-sync. **Alasan:** kesederhanaan; retensi tak terbatas cukup untuk skala operasional saat ini. → [[09 - Spesifikasi Offline Sync]] (jawaban OQ-06)

### DEC-15 — Rentang & default periode grafik: dinamis
**2026-07-02** · Grafik in/out pakai rentang/default dinamis (ditentukan saat desain UI, bukan dikunci di BRD). **Alasan:** fleksibilitas implementasi, tidak ada preferensi bisnis spesifik. → [[10 - Dashboard & Reporting]] (jawaban OQ-05)

### DEC-14 — Fitur ambang "stok menipis" dihilangkan (sementara)
**2026-07-02** · Peringatan stok menipis di dashboard dihapus dari scope untuk saat ini. **Alasan:** belum ada kebutuhan/nilai ambang yang jelas; bisa ditambah lagi nanti. → [[10 - Dashboard & Reporting]] (jawaban OQ-04)

### DEC-13 — Cooldown scan bisa dikonfigurasi
**2026-07-02** · Nilai cooldown ±1 detik per-barcode dijadikan setting yang bisa diubah, bukan hardcode. **Alasan:** fleksibilitas tuning di lapangan sesuai kondisi kamera/koneksi. → [[08 - Spesifikasi Scan & Anti-Double-Input]] (jawaban OQ-03)

### DEC-12 — Validasi barcode: terima apa pun yang cocok di master
**2026-07-02** · Tidak ada validasi checksum EAN-13/UPC-A. Barcode diterima selama cocok dengan data master (barcode-matching langsung, tanpa normalisasi 12 vs 13 digit). **Alasan:** kesederhanaan; master data jadi sumber kebenaran tunggal. → [[06 - Data Model & ERD]] (jawaban OQ-02)

### DEC-11 — Operator boleh akses dashboard read-only di menu terpisah
**2026-07-02** · Dashboard read-only untuk operator ditempatkan di menu terpisah dari layar scan, supaya menu utama operator tetap fokus scan. **Alasan:** visibilitas untuk operator tanpa mengganggu kecepatan alur scan. → [[03 - Stakeholder & Role]] (jawaban OQ-01)

### DEC-10 — Tema Pokéball orisinal, tanpa aset/SFX Pokémon asli
**2026-07-02** · Tema merah/putih/hitam, kartu, animasi kecil, ikon berkarakter. **Alasan:** menarik untuk operator tanpa melanggar IP Pokémon berlisensi. Aset & bunyi harus orisinal/terinspirasi. → [[11 - UI-UX & Tema Pokemon]]

### DEC-09 — Dashboard + input manual berbasis alasan
**2026-07-02** · Metrik (total produk/stok, in/out harian), peringatan stok menipis, feed, produk paling sering keluar, grafik in/out. Input/keluar manual dari dashboard dengan alasan. **Alasan:** visibilitas + jalur koreksi terkontrol. → [[10 - Dashboard & Reporting]]

### DEC-08 — Offline sync dengan 3 accepted constraints
**2026-07-02** · Cache master (IndexedDB) + antrian scan + auto-sync + indikator. Diterima: stok offline = perkiraan; produk baru tak dikenal offline sampai sync; login & input manual butuh online. **Alasan:** sinyal gudang jelek; kecepatan scan > akurasi real-time sesaat, hasil akhir tetap benar. → [[09 - Spesifikasi Offline Sync]]

### DEC-07 — Import Excel tidak pernah menyentuh stok
**2026-07-02** · Import upsert produk/vendor saja; stok murni dari `stock_movements`. **Alasan:** upload berulang harus aman; pisahkan master data dari pergerakan stok. → [[07 - Spesifikasi Import Excel]]

### DEC-06 — Barcode disimpan sebagai VARCHAR
**2026-07-02** · Bukan integer. **Alasan:** risiko leading zero hilang; data campuran 12 & 13 digit. → [[06 - Data Model & ERD]]

### DEC-05 — Barcode tak dikenal ditolak saat scan
**2026-07-02** · Scan barcode asing → tolak + bunyi error. Tambah produk lewat menu terpisah (boleh barcode manual). **Alasan:** jaga kecepatan & kebersihan data di mode scan cepat. → [[04 - Functional Requirements]] FR-SCAN-04

### DEC-04 — Empat jalur pergerakan, satu ledger
**2026-07-02** · Scan Masuk (+1), Scan Keluar (−1), Input Masuk manual (bebas, alasan opsional), Input Keluar manual (bebas, alasan wajib). Semua di `stock_movements`; stok mulai 0. **Alasan:** satu sumber kebenaran, auditable. → [[04 - Functional Requirements]] FR-MOVE-01

### DEC-03 — Anti-double-input berlapis
**2026-07-02** · Cooldown ±1 detik per-barcode; barcode beda langsung dihitung; indikator siap; toggle mode teliti (keluar frame); server idempotent via `scan_uuid`. **Alasan:** kamera membaca terus-menerus → cegah double-count tanpa mengorbankan kecepatan scan campur. → [[08 - Spesifikasi Scan & Anti-Double-Input]]

### DEC-02 — Scan = +1 otomatis dengan bunyi
**2026-07-02** · Tiap barcode terbaca +1 (atau −1) otomatis; bunyi dibedakan sukses/error/duplikat (Web Audio API). **Alasan:** operator paham hasil tanpa lihat layar; input cepat. → [[08 - Spesifikasi Scan & Anti-Double-Input]]

### DEC-01 — Scanner = kamera HP (PWA), bukan USB
**2026-07-02** · Decode `BarcodeDetector API` (Chrome Android), fallback ZXing. Viewfinder, kotak bidik, torch, angka besar. PWA installable, wajib HTTPS. **Alasan:** tanpa hardware khusus; cukup HP operator. → [[04 - Functional Requirements]] FR-SCAN-01

## Note Terkait

- [[00 - Index (MOC)]]
- [[14 - Open Questions]]
