---
title: 17 - Spesifikasi Booking Order
type: brd
status: final
tags: [pokemonscanner, functional, booking]
created: 2026-07-07
updated: 2026-07-07
---

# 17 - Spesifikasi Booking Order

← [[00 - Index (MOC)]]

Tag: `#pokemonscanner/functional` `#pokemonscanner/booking`

Modul tambahan pasca Fase 0–9. Keputusan terkunci: [[13 - Decision Log]] DEC-21 s.d. DEC-25 (2026-07-07). Roadmap pengerjaan: [[15 - Roadmap Implementasi]] Fase BO-0..BO-4.

---

## 1. Latar Belakang Masalah

Flow toko saat ini: SPG ambil barang dari rak → customer bawa barang ke kasir → kasir input POS. Masalah:

1. **Risiko kehilangan sebelum kasir** — customer kabur, barang hilang, batal beli, kasir lupa input → sulit tahu kehilangan terjadi di mana.
2. **Nota manual lambat** — SPG tulis nama customer, daftar barang, hitung qty; saat ramai sering terlewat → data penjualan tidak lengkap.
3. **SPG harusnya fokus jualan** (melayani, upselling, closing), bukan administrasi → human error.
4. **Tidak ada tracking barang keluar rak** — barang apa, kapan, SPG siapa, dibayar atau tidak.
5. **Kasir kadang input sebagian** — customer bawa 5, kasir input 4 → stok sistem selisih, SPG disalahkan, sumber masalah tak terlacak.

## 2. Konsep Solusi

**Booking Order (BO)** = pengganti nota manual. SPG pilih produk + qty → cetak struk thermal ber-barcode → customer bawa struk + barang ke kasir → kasir proses POS **seperti biasa, tanpa redeem, tanpa sistem kedua**. Akhir hari store keeper rekonsiliasi.

**Sifat Booking Order (DEC-21, final):**
- BO = **catatan barang keluar rak**, BUKAN transaksi penjualan, BUKAN pergerakan stok.
- BO **tidak pernah menyentuh** `stock_movements` maupun `products.stok_sekarang` — tabel terpisah total. Ledger stok tetap murni (konsisten DEC-04/DEC-07).
- BO tidak pernah di-redeem kasir. Konsekuensi diterima: sistem tidak bisa memastikan barang benar terjual; kepastian datang dari rekonsiliasi akhir hari (accepted constraint).
- Struk BO bukan sistem antrean formal — hanya bukti daftar barang yang akan dibeli. Namun tiap booking punya **nomor urut harian 001–999** yang dicetak besar di struk sebagai identitas cepat ala nomor antrian (DEC-25); reset tiap hari (WIB), rujukan pasti tetap `booking_code`.
- BO **online-only** (konsisten accepted constraint input manual, DEC-08).

## 3. Role Baru: SPG (DEC-22)

Role user jadi tiga: `admin` | `operator` | `spg`.

| Akses | Admin | Operator | SPG |
|---|---|---|---|
| Buat/cetak/void Booking Order | ✅ | ✅ | ✅ (hanya miliknya) |
| Riwayat booking sendiri | ✅ | ✅ | ✅ |
| Rekonsiliasi booking (semua SPG) | ✅ | ✅ (store keeper) | ❌ |
| Scan stok gudang (`/scan`) | ✅ | ✅ | ❌ |
| Laporan/dashboard, master, dst. | (tetap) | (tetap) | ❌ |

- SPG login → redirect ke halaman Booking.
- SPG **boleh lihat `stok_sekarang`** produk saat memilih item (final) — tahu ketersediaan, tapi tidak bisa mengubah stok.
- Store keeper = role `operator` existing (final, DEC-23) — tidak ada role keempat.

## 4. Flow

### Step 1 — SPG (target: hitungan detik)
1. Buka halaman Booking (PWA yang sama).
2. Tambah item: **scan barcode produk pakai kamera** (reuse penuh scanner Fase 4: BarcodeDetector + ZXing + bunyi) ATAU cari nama produk.
3. Item masuk keranjang; qty bisa diubah; barcode tak dikenal → tolak + bunyi error (konsisten DEC-05).
4. Klik **Simpan & Cetak** → booking tersimpan dengan `booking_code` unik → layar struk → print.

### Step 2 — Customer
Bawa barang + struk booking ke kasir.

### Step 3 — Kasir
Proses pembayaran di POS seperti biasa. **Nol perubahan, nol redeem, nol scan booking.**

### Step 4 — Store Keeper (akhir hari)
Buka halaman Rekonsiliasi Booking → bandingkan: stok awal → BO tercetak → stok akhir rak → penjualan POS. Tandai hasil per booking.

## 5. Data Model

```
bookings
  id            bigint PK
  booking_code  VARCHAR UNIQUE       -- format BK-YYMMDD-XXXX, X = alfanumerik acak
  nomor_urut    smallint unsigned    -- 1..999, reset harian (WIB), tampil besar di struk (DEC-25)
  user_id       FK users             -- SPG pembuat
  status        enum: printed | void | checked_ok | checked_selisih
  catatan_keeper VARCHAR nullable    -- diisi store keeper saat rekonsiliasi
  created_at, updated_at

booking_items
  id          bigint PK
  booking_id  FK bookings (cascade delete)
  product_id  FK products
  qty         unsignedInteger, min 1
```

- Tanpa kolom harga — sistem tidak menyimpan harga; pembayaran urusan POS. Struk tanpa harga.
- `booking_code` string pendek, dicetak sebagai barcode Code128 + teks — untuk referensi manusia/investigasi, bukan untuk di-scan sistem.
- `nomor_urut` = urutan booking dalam satu hari (WIB): booking pertama hari itu = 1, dst; hari baru mulai lagi dari 1. Ditampilkan 3 digit (`001`–`999`). Melewati 999 → berputar ke 001; duplikat tampilan diterima karena ID pasti = `booking_code` (DEC-25). Nomor tersimpan di baris booking agar cetak ulang menampilkan nomor yang sama.
- Void = ubah `status`, **bukan** delete — jejak audit tetap ada.

## 6. Functional Requirements

### FR-BOOK-01 — Buat Booking Order
SPG/operator/admin membuat booking berisi ≥1 item (produk master + qty ≥1). Tambah item via scan kamera atau pencarian nama. Barcode tak dikenal ditolak + bunyi error.
**AC:** booking tersimpan dengan `booking_code` unik; tidak ada baris baru di `stock_movements`; `stok_sekarang` tidak berubah.

### FR-BOOK-02 — Cetak Struk Thermal
Setelah simpan, tampil layar struk printer thermal 58mm berisi: **nomor urut harian dicetak besar** (3 digit, ala nomor antrian — DEC-25), `booking_code` (barcode Code128 + teks), tanggal-jam (WIB, DEC-18), nama SPG, daftar item (nama produk + qty), total item. Cetak via print dialog browser → printer thermal Bluetooth (RawBT sebagai virtual printer Android) (DEC-24).
**AC:** struk muat 58mm; barcode terbaca scanner; bisa cetak ulang dari riwayat.

### FR-BOOK-03 — Riwayat & Void
SPG melihat daftar booking miliknya (hari ini default). Booking berstatus `printed` bisa di-void (customer batal sebelum ke kasir) dengan konfirmasi.
**AC:** void mengubah status, tidak menghapus baris; booking yang sudah dicek keeper tidak bisa di-void SPG.

### FR-BOOK-04 — Rekonsiliasi Store Keeper
Halaman khusus admin+operator, filter per tanggal:
- **Agregat per produk:** qty ter-booking vs qty keluar di ledger (`stock_movements` tipe `out`) hari itu — bahan banding dengan POS & stok fisik.
- **Daftar booking:** tiap booking bisa ditandai `checked_ok` / `checked_selisih` + `catatan_keeper`.
- Export Excel (reuse Maatwebsite, pola DEC-19; barcode dipaksa string, DEC-06).
**AC:** SPG tidak bisa akses halaman ini (403, guard `boot()` server-side, pola Fase 9).

### FR-BOOK-05 — Metrik Dashboard
Dashboard admin menampilkan jumlah booking & item ter-booking hari ini.

## 7. Matriks Analisa Selisih (panduan keeper, di luar sistem)

| Case | BO | POS | Stok rak berkurang | Arti |
|---|---|---|---|---|
| 1 | ada | ada | ya | ✅ Normal |
| 2 | ada | tidak | ya | Customer batal / kasir lupa input / customer kabur → investigasi |
| 3 | tidak | tidak | ya | Indikasi shoplifting / barang hilang dari rak |
| 4 | ada | sebagian | ya | Item yang terlewat kasir teridentifikasi dari daftar item BO |

Sistem menyediakan data sisi BO + ledger; data POS & stok fisik dibandingkan manual oleh keeper.

## 8. Reuse dari Fase 0–9 (tidak kerja dua kali)

| Komponen existing | Dipakai untuk |
|---|---|
| `resources/js/scan.js` (BarcodeDetector+ZXing, bunyi Web Audio) | Scan produk saat menyusun booking |
| Middleware `role:` (`EnsureUserHasRole`, variadic) | Route SPG — tanpa perubahan middleware |
| CRUD user `⚡users.blade.php` | Tambah opsi role `spg` |
| Pola Livewire SFC + guard `boot()` (Fase 9) | Semua komponen booking |
| Design system Pokéball (`app.css`) | UI booking & rekonsiliasi |
| Maatwebsite Excel (`ReportExport`) | Export rekonsiliasi |

## 9. Batasan & Non-Scope

- Booking online-only; offline booking = [[14 - Open Questions]] OQ-09.
- Booking TIDAK dikonversi otomatis jadi movement gudang; kalau kelak diinginkan = OQ-10.
- Tidak ada integrasi POS (POS sistem terpisah, tidak disentuh).
- Tidak ada harga/nominal di sistem.

## Note Terkait

- [[00 - Index (MOC)]]
- [[13 - Decision Log]] (DEC-21..24)
- [[14 - Open Questions]] (OQ-09, OQ-10)
- [[15 - Roadmap Implementasi]] (Fase BO-0..BO-4)
- [[03 - Stakeholder & Role]] · [[08 - Spesifikasi Scan & Anti-Double-Input]]
