---
title: 12 - Asumsi, Batasan & Risiko
type: brd
status: final
tags: [pokemonscanner, risk]
created: 2026-07-02
updated: 2026-07-02
---

# 12 - Asumsi, Batasan & Risiko

← [[00 - Index (MOC)]]

## Asumsi

| ID | Asumsi |
|---|---|
| ASM-01 | Operator memakai HP Android dengan Chrome (dukungan `BarcodeDetector`). |
| ASM-02 | Ada 1 lokasi/gudang stok (bukan multi-lokasi). |
| ASM-03 | HTTPS tersedia (wajib untuk kamera & PWA). |
| ASM-04 | Data awal 109 produk KidzStation; vendor asli menyusul di upload berikut. |
| ASM-05 | Barcode unik per produk (data awal tidak ada duplikat). |

## Batasan (Constraints)

| ID | Batasan |
|---|---|
| CON-01 | `barcode` selalu VARCHAR, tidak pernah integer. |
| CON-02 | Import tidak pernah mengubah/mereset stok. |
| CON-03 | Stok hanya berasal dari `stock_movements` (mulai 0). |
| CON-04 | Barcode tak dikenal ditolak dalam mode scan (tambah produk lewat menu terpisah). |
| CON-05 | Input manual keluar wajib alasan. |
| CON-06 | Dilarang memakai aset/SFX asli Pokémon (IP berlisensi). |
| CON-07 | Angka stok offline bersifat perkiraan; akurat setelah semua device sync. |
| CON-08 | Produk baru hasil import tak dikenali HP offline sampai sync ulang. |
| CON-09 | Login pertama & input manual butuh online. |

CON-07..09 = accepted constraints, detail di [[09 - Spesifikasi Offline Sync]].

## Risiko

| ID | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| RSK-01 | Double-count karena kamera baca berulang | stok salah | Cooldown per-barcode + mode teliti + `scan_uuid` idempotent server |
| RSK-02 | Leading zero barcode hilang saat import | mismatch produk | Paksa baca BARCODE sebagai string; validasi panjang |
| RSK-03 | Mojibake nama produk | data kotor | Cleaning encoding saat import ([[07 - Spesifikasi Import Excel]]) |
| RSK-04 | Sync ganda dari beberapa device | double movement | `scan_uuid` UNIQUE di DB |
| RSK-05 | Browser tanpa `BarcodeDetector` | scan gagal | Fallback ZXing |
| RSK-06 | Cache master basi → produk baru tak dikenal offline | scan tertolak salah | Refresh cache saat online / tombol sync; accepted constraint CON-08 |
| RSK-07 | Data app terhapus saat ada antrian offline | scan hilang | Edukasi operator; indikator antrian; dorong sync rutin |
| RSK-08 | Pemakaian aset Pokémon tanpa sengaja | masalah IP/legal | Review aset; hanya orisinal ([[11 - UI-UX & Tema Pokemon]]) |

## Note Terkait

- [[09 - Spesifikasi Offline Sync]]
- [[13 - Decision Log]]
- [[14 - Open Questions]]
