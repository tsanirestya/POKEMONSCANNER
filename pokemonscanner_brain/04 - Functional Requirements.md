---
title: 04 - Functional Requirements
type: brd
status: final
tags: [pokemonscanner, functional]
created: 2026-07-02
updated: 2026-07-02
---

# 04 - Functional Requirements

← [[00 - Index (MOC)]]

Tag: `#pokemonscanner/functional`

Tiap requirement punya **ID** dan **acceptance criteria** yang testable. Modul: AUTH, PROD, VEND, IMPORT, SCAN, MOVE, OFFLINE, DASH.

---

## Modul AUTH — Autentikasi & Otorisasi

### FR-AUTH-01 — Login
Sistem menyediakan login berbasis Laravel auth.
- **AC1:** User dengan kredensial valid berhasil login dan diarahkan sesuai role.
- **AC2:** Kredensial salah ditolak dengan pesan error.
- **AC3:** Login pertama wajib online (lihat [[09 - Spesifikasi Offline Sync]]).

### FR-AUTH-02 — Role-based access
Sistem membatasi fitur berdasar role (`admin`/`operator`).
- **AC1:** Operator mengakses route admin → ditolak (403/redirect).
- **AC2:** Menu yang tidak diizinkan tidak ditampilkan untuk operator.

---

## Modul PROD — Master Produk

### FR-PROD-01 — CRUD produk (admin)
Admin dapat membuat, melihat, mengubah, menghapus produk.
- **AC1:** Produk punya `barcode` (string, unik), `nama_produk`, `vendor_id`.
- **AC2:** Barcode duplikat ditolak saat create/update.
- **AC3:** `barcode` selalu disimpan sebagai VARCHAR (leading zero tidak hilang).

### FR-PROD-02 — Tambah produk baru dari luar mode scan
Penambahan produk baru dilakukan lewat menu terpisah (bukan mode scan cepat), boleh isi barcode manual.
- **AC1:** Form tambah produk punya field barcode manual.
- **AC2:** Tidak bisa menambah produk dari dalam alur scan cepat (lihat FR-SCAN-04).

---

## Modul VEND — Vendor

### FR-VEND-01 — CRUD vendor (admin)
Admin dapat mengelola vendor.
- **AC1:** Vendor punya `nama` unik-secara-logis.
- **AC2:** Vendor bisa dibuat otomatis saat import via `firstOrCreate` (lihat FR-IMPORT-02).

---

## Modul IMPORT — Import Excel

Detail lengkap: [[07 - Spesifikasi Import Excel]].

### FR-IMPORT-01 — Upload file Excel (admin)
Admin mengunggah file Excel master produk dari menu Admin, dapat dipakai berulang.
- **AC1:** Kolom yang dibaca: `NO`, `BRAND`, `BARCODE`, `PRODUCT NAME`.
- **AC2:** `BARCODE` dibaca sebagai string (tidak dikonversi ke integer).

### FR-IMPORT-02 — Mapping vendor otomatis
`BRAND` dipetakan menjadi vendor via `firstOrCreate`.
- **AC1:** Brand yang belum ada → vendor baru dibuat.
- **AC2:** Brand yang sudah ada → dipakai ulang, tidak duplikat.

### FR-IMPORT-03 — Upsert produk by barcode
Produk dicocokkan berdasarkan barcode.
- **AC1:** Barcode sudah ada → update nama & vendor.
- **AC2:** Barcode belum ada → buat produk baru.

### FR-IMPORT-04 — Cleaning encoding
Import membersihkan mojibake/encoding rusak.
- **AC1:** `POKÃÂÃÂ©MON` tersimpan sebagai `POKÉMON`.

### FR-IMPORT-05 — Import tidak menyentuh stok
Import tidak pernah mengubah/mereset stok.
- **AC1:** Nilai `stok_sekarang` & isi `stock_movements` tidak berubah akibat import.
- **AC2:** Upload ulang file yang sama aman (idempotent terhadap stok).

### FR-IMPORT-06 — Ringkasan hasil import
Tampilkan hasil: X dibuat, Y diupdate, Z error.
- **AC1:** Baris error dilaporkan dengan alasan (mis. barcode kosong).

---

## Modul SCAN — Scan Barcode

Detail lengkap: [[08 - Spesifikasi Scan & Anti-Double-Input]].

### FR-SCAN-01 — Decode via kamera HP
Scanner memakai `BarcodeDetector API`, fallback ZXing.
- **AC1:** Pada Chrome Android dengan `BarcodeDetector`, barcode terdecode dari kamera belakang.
- **AC2:** Bila API tak tersedia, fallback ZXing aktif otomatis.
- **AC3:** UI menampilkan viewfinder, kotak bidik, tombol senter/torch, angka hitungan besar.

### FR-SCAN-02 — Scan = +1 / −1 otomatis + bunyi
Tiap barcode terbaca menambah/mengurangi 1 sesuai mode, dengan umpan balik audio.
- **AC1:** Mode masuk: barcode dikenal → movement `in` qty 1 + bunyi sukses (klik).
- **AC2:** Mode keluar: barcode dikenal → movement `out` qty 1 + bunyi sukses.
- **AC3:** Barcode tak dikenal → ditolak + bunyi error (buzz).
- **AC4:** Duplikat (dalam cooldown) → diabaikan + nada berbeda.

### FR-SCAN-03 — Anti-double-input
- **AC1:** Barcode sama terbaca ulang dalam cooldown (default ±1 detik, dapat dikonfigurasi) → diabaikan.
- **AC2:** Barcode berbeda → langsung dihitung tanpa nunggu cooldown barcode lain.
- **AC3:** Ada indikator "siap scan berikutnya" untuk item identik.
- **AC4:** Toggle "mode teliti": barcode wajib keluar frame (3 frame decode berturut tanpa deteksi, dapat dikonfigurasi) dulu sebelum boleh dihitung lagi.
- **AC5:** Tiap scan membawa `scan_uuid` unik; server menolak UUID kembar (idempotent).

### FR-SCAN-04 — Barcode tak dikenal ditolak dalam mode scan
- **AC1:** Barcode tak dikenal tidak membuat produk baru di alur scan.
- **AC2:** Sistem memberi bunyi error dan tidak menambah movement.

---

## Modul MOVE — Pergerakan Stok

### FR-MOVE-01 — Empat jalur pergerakan
Semua tercatat di `stock_movements`.

| Jalur | Qty | Metode | Alasan | Role |
|---|---|---|---|---|
| Scan Masuk | +1 | `scan` | tidak perlu | admin/operator |
| Scan Keluar | −1 | `scan` | tidak perlu | admin/operator |
| Input Masuk manual | bebas | `manual` | opsional | admin |
| Input Keluar manual | bebas | `manual` | **wajib** | admin |

- **AC1:** Stok awal semua produk = 0; nilai stok = agregasi movement.
- **AC2:** Input keluar manual tanpa alasan → ditolak.
- **AC3:** `stock_movements` bersifat append-only (tidak di-edit/hapus untuk koreksi; koreksi = movement baru).

### FR-MOVE-02 — Cache stok
`products.stok_sekarang` menyimpan cache agar query cepat.
- **AC1:** Setiap movement memperbarui cache secara konsisten dengan ledger.
- **AC2:** Tersedia cara rekonsiliasi cache dari ledger bila menyimpang.

---

## Modul OFFLINE — Offline & Sync

Detail lengkap: [[09 - Spesifikasi Offline Sync]].

### FR-OFFLINE-01 — Cache master ke HP
Master produk di-cache ke IndexedDB untuk validasi offline.
- **AC1:** Saat offline, barcode dikenal/tak dikenal tetap tervalidasi dari cache.

### FR-OFFLINE-02 — Antrian scan offline
Scan offline masuk antrian lokal, tiap item bawa `scan_uuid`.
- **AC1:** Saat offline, scan tersimpan di antrian, hitungan lokal tetap jalan.

### FR-OFFLINE-03 — Auto-sync
Saat online, antrian terkirim otomatis; server tolak duplikat `scan_uuid`.
- **AC1:** Kembali online → antrian terkirim tanpa aksi manual.
- **AC2:** UUID yang sudah pernah diproses tidak menambah stok dua kali.

### FR-OFFLINE-04 — Indikator status sync
- **AC1:** Saat offline, tampilkan notifikasi "sedang offline, cari sinyal untuk sync" + jumlah task belum ter-sync.
- **AC2:** Saat online, tampilkan waktu sync terakhir; tombol "Sync sekarang" memicu sync manual.

---

## Modul DASH — Dashboard & Reporting

Detail lengkap: [[10 - Dashboard & Reporting]].

### FR-DASH-01 — Metrik ringkas
- **AC1:** Menampilkan total produk, total stok, in/out hari ini.
- **AC2:** Terlihat oleh admin (penuh) dan operator (read-only, menu terpisah dari layar scan).

### FR-DASH-02 — Feed pergerakan & analitik
- **AC1:** Feed pergerakan terakhir tampil real-time-ish.
- **AC2:** Menampilkan produk paling sering keluar.
- **AC3:** Grafik in/out per periode, rentang/default dinamis.

### FR-DASH-03 — Input manual dari dashboard
- **AC1:** Admin bisa masukkan/keluarkan stok manual dengan alasan langsung dari dashboard (lihat FR-MOVE-01).

> Peringatan stok menipis dihapus dari scope (sementara), lihat [[13 - Decision Log]] DEC-14.

## Note Terkait

- [[05 - Non-Functional Requirements]]
- [[06 - Data Model & ERD]]
- [[13 - Decision Log]]
