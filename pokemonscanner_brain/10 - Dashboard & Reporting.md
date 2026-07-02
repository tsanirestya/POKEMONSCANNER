---
title: 10 - Dashboard & Reporting
type: brd
status: final
tags: [pokemonscanner, functional, dashboard]
created: 2026-07-02
updated: 2026-07-02
---

# 10 - Dashboard & Reporting

← [[00 - Index (MOC)]]

Tag: `#pokemonscanner/functional`

## Tujuan

Beri admin visibilitas stok & pergerakan, plus jalur input manual dengan alasan.

## Komponen Dashboard

| Komponen | Deskripsi | Sumber |
|---|---|---|
| Total produk | jumlah produk master | `products` |
| Total stok | agregat `stok_sekarang` | cache `products` |
| In hari ini | jumlah qty `in` hari ini | `stock_movements` |
| Out hari ini | jumlah qty `out` hari ini | `stock_movements` |
| Feed pergerakan terakhir | movement terbaru (produk, tipe, qty, metode, user, waktu) | `stock_movements` |
| Produk paling sering keluar | ranking by total `out` per periode | `stock_movements` |
| Grafik in/out per periode | tren masuk vs keluar, rentang/default **dinamis** (ditentukan saat desain UI) | `stock_movements` |

> **Peringatan stok menipis dihapus dari scope** (sementara). Lihat [[13 - Decision Log]] DEC-14.

## Input Manual dari Dashboard

Admin dapat memasukkan/keluarkan stok manual langsung dari dashboard:

- **Input Masuk manual:** qty bebas, alasan **opsional** (pembelian, koreksi, opname).
- **Input Keluar manual:** qty bebas, alasan **wajib** (rusak, terjual, transfer).
- Tercatat sebagai movement `metode = manual`. Lihat [[04 - Functional Requirements]] FR-MOVE-01.
- Butuh **online** (lihat accepted constraint di [[09 - Spesifikasi Offline Sync]]).

## Catatan

- Ambang "stok menipis" dihapus dari scope (sementara) → [[13 - Decision Log]] DEC-14.
- Periode grafik: rentang/default dinamis, fleksibel di implementasi → [[13 - Decision Log]] DEC-15.

## Note Terkait

- [[04 - Functional Requirements]] (Modul DASH & MOVE)
- [[06 - Data Model & ERD]]
