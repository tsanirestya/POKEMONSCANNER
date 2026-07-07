---
title: 00 - Index (MOC)
type: brd
status: final
tags: [pokemonscanner, moc]
created: 2026-07-02
updated: 2026-07-07
---

# 00 - Index (MOC) — PokemonScanner

Map of Content (peta utama). Semua note BRD terhubung dari sini. Baca berurutan atau lompat lewat wikilink.

## Ringkasan Singkat

**PokemonScanner** = aplikasi manajemen stok berbasis scan barcode kamera HP untuk produk Pokémon (data awal dari KidzStation). Stack: **Laravel monolith (Blade + Livewire/Alpine.js) + MySQL**, frontend scanner sebagai **PWA** (installable, wajib HTTPS untuk akses kamera). Stok dibangun murni dari ledger `stock_movements` (mulai 0).

## Daftar Note

| No | Note | Status | Cakupan |
|---|---|---|---|
| 01 | [[01 - Ringkasan Proyek]] | final | Executive summary, latar belakang, tujuan bisnis |
| 02 | [[02 - Tujuan & Ruang Lingkup]] | final | Objectives terukur, in-scope, out-of-scope |
| 03 | [[03 - Stakeholder & Role]] | final | Admin, operator, hak akses |
| 04 | [[04 - Functional Requirements]] | final | Semua fitur + acceptance criteria (ber-ID) |
| 05 | [[05 - Non-Functional Requirements]] | final | Performa, PWA/HTTPS, keamanan, usability, reliability |
| 06 | [[06 - Data Model & ERD]] | final | Tabel, relasi, ERD mermaid, barcode=VARCHAR |
| 07 | [[07 - Spesifikasi Import Excel]] | final | Format kolom, mapping, cleaning, upsert, no-reset stok |
| 08 | [[08 - Spesifikasi Scan & Anti-Double-Input]] | final | Decode, bunyi, cooldown, idempotency |
| 09 | [[09 - Spesifikasi Offline Sync]] | final | Arsitektur + accepted constraints |
| 10 | [[10 - Dashboard & Reporting]] | final | Metrik, feed, grafik, input manual |
| 11 | [[11 - UI-UX & Tema Pokemon]] | final | Tema Pokéball + catatan IP |
| 12 | [[12 - Asumsi, Batasan & Risiko]] | final | Assumptions, constraints, risks |
| 13 | [[13 - Decision Log]] | final | 17 keputusan terkunci + tanggal |
| 14 | [[14 - Open Questions]] | final | Kosong — semua pertanyaan terjawab |
| 15 | [[15 - Roadmap Implementasi]] | in-progress | Fase kerja kode (checklist), 1 fase = 1 sesi chat |
| 16 | [[16 - Runbook Deployment & Troubleshooting Hosting]] | final | Arsitektur deploy FTP 2-step, diagnosis error production, teknik tanpa SSH |
| 17 | [[17 - Spesifikasi Booking Order]] | final | Modul BO: SPG cetak struk booking (tanpa redeem, tanpa sentuh stok), role `spg`, rekonsiliasi keeper |

## Alur Baca yang Disarankan

1. Konteks bisnis → [[01 - Ringkasan Proyek]], [[02 - Tujuan & Ruang Lingkup]]
2. Siapa pakai → [[03 - Stakeholder & Role]]
3. Apa yang dibangun → [[04 - Functional Requirements]], [[05 - Non-Functional Requirements]]
4. Bagaimana data → [[06 - Data Model & ERD]], [[07 - Spesifikasi Import Excel]]
5. Fitur inti → [[08 - Spesifikasi Scan & Anti-Double-Input]], [[09 - Spesifikasi Offline Sync]], [[10 - Dashboard & Reporting]]
6. Rupa & risiko → [[11 - UI-UX & Tema Pokemon]], [[12 - Asumsi, Batasan & Risiko]]
7. Jejak keputusan → [[13 - Decision Log]], [[14 - Open Questions]]
8. Eksekusi → [[15 - Roadmap Implementasi]]

## Taksonomi Tag

`#pokemonscanner/functional` · `#pokemonscanner/nfr` · `#pokemonscanner/data` · `#pokemonscanner/scan` · `#pokemonscanner/offline` · `#pokemonscanner/decision` · `#pokemonscanner/question` · `#pokemonscanner/roadmap`
