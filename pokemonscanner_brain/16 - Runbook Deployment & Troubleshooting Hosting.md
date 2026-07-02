---
title: Runbook Deployment & Troubleshooting Hosting
type: runbook
status: final
tags: [pokemonscanner, deployment]
created: 2026-07-03
updated: 2026-07-03
---

# Runbook Deployment & Troubleshooting Hosting

Dokumentasi perbaikan deployment production `scanner.transentertainment.id` (2026-07-03) + panduan diagnosis kalau error serupa muncul lagi. Link balik: [[00 - Index (MOC)]].

## Arsitektur Deployment (kondisi final, jangan diubah tanpa alasan)

- **Hosting:** cPanel shared hosting, LiteSpeed, CloudLinux. **Tidak ada akses SSH** — semua via FTP (GitHub Actions) atau file manager panel.
- **FTP root = home cPanel** (`/home2/transen2/`), **BUKAN** docroot subdomain.
- **Docroot subdomain** = folder `scanner.transentertainment.id/` di dalam home.
- **Workflow** [`deploy.yml`](../.github/workflows/deploy.yml) push ke `main` → build (composer, npm) → **dua step FTP sync**:
  1. Seluruh app Laravel → `./` (home, DI LUAR web root — `.env`, `vendor/`, `app/` tidak bisa diakses browser. Ini disengaja, fitur keamanan).
  2. Isi `public/` saja → `./scanner.transentertainment.id/` (docroot), pakai state file terpisah `state-name: .ftp-deploy-sync-state-docroot.json`.
- **Kenapa jalan tanpa ubah kode:** `public/index.php` pakai path relatif `__DIR__.'/../vendor/autoload.php'` dan `../bootstrap/app.php`. Karena docroot = folder sibling dari `vendor/` & `bootstrap/` di home, path `../` resolve tepat ke app.
- **PHP version:** host default **7.4** — Laravel 12 butuh **≥ 8.2**. Dipaksa via baris pertama `public/.htaccess`:
  ```apache
  AddHandler application/x-httpd-ea-php82 .php
  ```
  Handler tersedia di host ini: `ea-php82`, `ea-php83`, `alt-php82`, `lsphp82` (semua terverifikasi). Pakai `ea-php82` supaya match versi build CI.
- **Step `Ensure runtime directories deploy`** di workflow bikin `.keep` di `storage/*` & `bootstrap/cache` sebelum sync. **Jangan dihapus** — exclude `**/.git*` ikut membuang `.gitignore`, dan folder-folder itu isinya cuma `.gitignore`, jadi tanpa `.keep` foldernya tidak pernah terbentuk di server → Laravel fatal saat boot.

## Kronologi Masalah yang Diperbaiki (4 lapis)

| # | Gejala | Akar Masalah | Fix |
|---|--------|--------------|-----|
| 1 | `Index of /` (directory listing kosong) | FTP root ≠ docroot: seluruh app ter-upload ke home, docroot kosong | Dua step FTP sync (lihat arsitektur di atas) |
| 2 | `500 Internal Server Error` di semua URL | `.htaccess` di root home (workaround lama) bikin rewrite loop `public/public/...`; cPanel/LiteSpeed me-merge `.htaccess` folder induk ke docroot di bawahnya | Hapus `.htaccess` & `index.php` shim di root repo |
| 3 | `Composer detected issues... PHP >= 8.2.0. You are running 7.4.33` | Host default PHP 7.4 | `AddHandler application/x-httpd-ea-php82 .php` di `public/.htaccess` |
| 4 | DB connect tapi `tables (0)` | Migration belum pernah jalan (tidak ada SSH) | Script web temporary `migrate.php` token-gated → `migrate --force` + seed → dihapus lagi |

## Teknik Diagnosis (pakai lagi kalau error habis deploy)

Urutan cek:

1. **Bedakan halaman error.** Error page LiteSpeed generic ("An internal server error has occured", "Proudly powered by LiteSpeed") = PHP fatal SEBELUM Laravel sempat render. Error page Laravel = app boot, exception di dalam app (cek `storage/logs/laravel.log`).
2. **Cek log GitHub Actions step FTP:** `gh run view <id> --log | grep "Sync files"`. Perhatikan: durasi mencurigakan cepat (<1 mnt) untuk upload besar = kemungkinan cuma diff; `File content is the same, doing nothing` = dibandingkan ke **state file**, bukan isi server sebenarnya.
3. **Deploy `checkup.php` temporary di `public/`** (token-gated, hapus setelah selesai): print `PHP_VERSION`, cek extension, exist/writable `vendor`/`storage`/`bootstrap/cache`/`.env`, tail `laravel.log`, lalu coba bootstrap Laravel + `DB::select('select 1')` + list tables dalam try/catch. Satu file ini menjawab hampir semua pertanyaan.
4. **Kalau semua URL 404 padahal file ter-upload:** curiga FTP root ≠ docroot. List isi FTP dari workflow:
   ```yaml
   - run: curl -s "ftp://${{ secrets.FTP_SERVER }}/" --user "${{ secrets.FTP_USERNAME }}:${{ secrets.FTP_PASSWORD }}"
   ```
5. **Cek versi PHP / cari nama handler:** deploy folder test berisi `v.php` (`<?php echo PHP_VERSION;`) + `.htaccess` berisi satu varian `AddHandler` per folder (`ea-php82`, `ea-php83`, `alt-php82`, `lsphp82`), curl satu-satu.
6. **Migration / artisan tanpa SSH:** script web temporary yang bootstrap console kernel lalu `$kernel->call('migrate', ['--force' => true])`. **Wajib token-gated & dihapus setelah dipakai.**

## Aturan Keamanan Runbook

- Script diagnostic (`checkup.php`, `migrate.php`) **selalu** token-gated (`?key=...`, 404 tanpa body kalau salah) dan **dihapus dari repo begitu selesai** — FTP sync otomatis menghapusnya dari server.
- `.env`, `vendor/`, `app/` sengaja di home (luar docroot). Jangan pernah pindahkan seluruh app ke dalam docroot.
- ⚠️ Hutang keamanan (belum dibereskan per 2026-07-03): `APP_KEY` production ter-commit di `deploy.yml` padahal **repo public** → pindahkan ke GitHub Secrets + rotate key; password admin seeder (`admin@pokemonscanner.test` / `password`) harus diganti di production.

---
Kembali ke [[00 - Index (MOC)]] · Terkait: [[15 - Roadmap Implementasi]], [[05 - Non-Functional Requirements]]
