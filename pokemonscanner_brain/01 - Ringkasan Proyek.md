---
title: 01 - Ringkasan Proyek
type: brd
status: final
tags: [pokemonscanner, overview]
created: 2026-07-02
updated: 2026-07-02
---

# 01 - Ringkasan Proyek

← [[00 - Index (MOC)]]

## Executive Summary

**PokemonScanner** adalah aplikasi web (Laravel monolith + PWA) untuk manajemen stok produk Pokémon berbasis **scan barcode pakai kamera HP**. Operator gudang menghitung barang masuk/keluar cukup dengan mengarahkan kamera ke barcode — tiap scan otomatis +1 (atau −1) dengan umpan balik bunyi. Stok tidak diinput sebagai angka mutlak, melainkan dibangun dari **ledger pergerakan** (`stock_movements`) yang append-only, sehingga setiap perubahan tercatat dan bisa diaudit.

Aplikasi harus tetap berguna walau **sinyal gudang jelek**: master produk di-cache ke HP, scan offline masuk antrian lokal, dan otomatis tersinkron saat online kembali dengan proteksi anti-duplikat.

## Latar Belakang

Data awal berasal dari **KidzStation**: 109 produk Pokémon dengan barcode campuran 12 & 13 digit. Proses stok manual lambat dan rawan salah hitung. Kebutuhan utama: **input stok cepat & akurat** langsung dari lantai gudang tanpa alat khusus (cukup HP).

Kondisi data awal yang harus diperhitungkan:
- Barcode campuran 12 & 13 digit, tidak ada duplikat.
- 1 baris punya encoding rusak / mojibake yang perlu dibersihkan (contoh: `POKÃÂÃÂ©MON` → `POKÉMON`).
- Kolom `BRAND` saat ini semua bernilai "Kidz Station" (akan diisi vendor asli pada upload berikutnya).

## Tujuan Bisnis

1. Percepat pencatatan stok masuk/keluar via scan kamera HP (tanpa scanner USB).
2. Jamin akurasi lewat ledger append-only + proteksi anti-double-input.
3. Tetap operasional saat offline; hasil akhir selalu konsisten setelah sync.
4. Sediakan visibilitas stok & pergerakan lewat dashboard untuk admin.

## Tech Stack

- **Backend:** Laravel (monolith), MySQL.
- **Frontend:** Blade + Livewire/Alpine.js.
- **Scanner:** PWA installable, wajib HTTPS (akses kamera), `BarcodeDetector API` dengan fallback ZXing.
- **Offline:** Service Worker + IndexedDB (cache master produk + antrian scan).

## Note Terkait

- [[02 - Tujuan & Ruang Lingkup]]
- [[04 - Functional Requirements]]
- [[13 - Decision Log]]
