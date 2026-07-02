---
title: 03 - Stakeholder & Role
type: brd
status: final
tags: [pokemonscanner, stakeholder]
created: 2026-07-02
updated: 2026-07-02
---

# 03 - Stakeholder & Role

← [[00 - Index (MOC)]]

## Role & Hak Akses

Autentikasi pakai Laravel auth. Kolom `role` di tabel `users`: `admin` | `operator`.

### Admin

Kontrol penuh manajemen & data.

- Kelola master produk (CRUD, termasuk tambah produk baru dengan barcode manual).
- Kelola vendor.
- Kelola user (buat/ubah role).
- Import Excel master produk (berulang).
- Input stok masuk manual (qty bebas, alasan opsional).
- Keluarkan stok manual (qty bebas, alasan **wajib**).
- Lihat dashboard & reporting penuh.
- Boleh melakukan scan juga.

### Operator

Fokus kecepatan lapangan.

- Scan masuk (+1) dan scan keluar (−1).
- Lihat status sync + antrian offline.
- Lihat **dashboard read-only** di menu terpisah dari layar scan (lihat [[13 - Decision Log]] DEC-11).
- **Tidak bisa** kelola master, vendor, user, import Excel, atau input manual berdasar alasan.

## Matriks Akses

| Kapabilitas | Admin | Operator |
|---|---|---|
| Login | ✓ | ✓ |
| Scan masuk/keluar | ✓ | ✓ |
| Tambah produk baru (barcode manual) | ✓ | ✗ |
| CRUD produk & vendor | ✓ | ✗ |
| Import Excel | ✓ | ✗ |
| Input manual masuk/keluar (alasan) | ✓ | ✗ |
| Kelola user & role | ✓ | ✗ |
| Dashboard & reporting | ✓ (penuh) | ✓ (read-only, menu terpisah) |

> Menu utama operator tetap fokus scan; dashboard read-only ditempatkan terpisah supaya tidak mengganggu alur kerja. Lihat [[13 - Decision Log]] DEC-11.

## Note Terkait

- [[04 - Functional Requirements]]
- [[05 - Non-Functional Requirements]] (keamanan/otorisasi)
