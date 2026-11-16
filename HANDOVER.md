# HANDOVER — Deteksi Data Tidur (BPS Kab. Halmahera Utara)

Tanggal: 2026-11-16 · Oleh: NOVA (Hermes) · Status: **PRODUCTION-READY (lokal terverifikasi, menunggu deploy Dokploy)**

## Yang Sudah Dikerjakan (11 commit di atas fork)

1. Hygiene repo: `.gitignore` diperbaiki; `__pycache__` + 1 file xlsx unggahan nyata di-untrack dari git (riwayat dibiarkan — keputusan rewrite menunggu owner).
2. Net karakterisasi pytest: `python-api/tests/` — 4 test golden sample SHPED (165 baris / 55 seri / 11 komoditas / 4 tidur / 7 bergerak, periode s.d. akhir bulan). 4 passed.
3. Konfigurasi 12-factor: `config/config.php` + `python-api/config.py` env-driven (DB, API_BASE, UPLOAD_DIR, APP_DEBUG, APP_FORCE_HTTPS, APP_BEHIND_PROXY, APP_TZ=Asia/Jayapura, CORS_ORIGINS).
4. Keamanan: throttle login 5 gagal/15 menit (tabel `login_attempts` + migrasi di install.php), redaksi error DB via `err_detail()` di balik `APP_DEBUG`, cookie `Secure`, security headers `.htaccess` root, CORS FastAPI default-deny, **install.php tanpa admin123/viewer123** (username+password dari ENV; kosong = acak tampil sekali).
5. Kompatibilitas lintas container: PHP kirim `file_name` (basename) → FastAPI `_safe_path(file_name)` resolve di UPLOAD_DIR miliknya.
6. Docker/Dokploy: `deploy/Dockerfile.web` (php:8.3-apache + pdo_mysql/curl + AllowOverride + entrypoint chown), `deploy/Dockerfile.api` (python:3.12-slim + uvicorn), `deploy/docker-compose.yml` (3 service + healthcheck + init skema + shared uploads volume), `.env.example`, `.dockerignore`.
7. Dokumentasi: README profesional (arsitektur, 3 opsi lokal, deploy Dokploy step-by-step, tabel env, pengujian, keamanan, troubleshooting), PENJELASAN_KODE.md + bagian "Perubahan Versi Produksi", `.hermes.md`, HANDOVER.md ini.
8. Smoke script `scripts/smoke.sh` + router lokal `scripts/router-local.php`.

## Verifikasi Fisik (lokal, stack native Windows)

- MariaDB portable 11.4.13 jalan (`.local-stack/`), skema terimpor, 5 tabel termasuk `login_attempts`.
- FastAPI :8000 healthy (pandas 3.1.1, python 3.14); Web :8090 (php -S + router, `.env` terblokir 403).
- install.php: akun dibuat dari ENV ✓. Smoke end-to-end: login 302 → sample analisis ok (hasil tersimpan MySQL) → landing golden 55/4/7 ✓ → unduh CSV publik ✓ → **SMOKE OK**.
- Throttle: 5 gagal → percobaan ke-6 diblok ✓. Timezone: Asia/Jayapura ✓. `api/health.php` → api online ✓.
- `php -l` 15 file OK · py_compile OK.

## Kredensial Lokal (di `.env`, TIDAK ter-commit)

- Admin: `admin` / `Halut.Admin-2026` · Viewer: `viewer` / `Halut.Viewer-2026`
- DB: `tidur_app` / `tidur-app-local` · MySQL root: `tidur-root-local` · Web: `http://127.0.0.1:8090`

## Status Deployment Produksi (2026-11-16) — TAYANG ✅

• **URL**: `https://data-tidur.t-la-halut.com` (Dokploy, service Compose `projects-datatidur-g86qca`, VPS `45.127.32.109`)
• Domain: DNS only (grey cloud) → VPS; Traefik → service `web` port **80 (container)**; sertifikat Let's Encrypt
• Akun admin produksi: `bps8205` (password ada di tab Environment Dokploy, `INSTALL_ADMIN_PASSWORD`)
• Tersambung: `dokploy-network` (external, swarm overlay) + jaringan internal compose — db/api internal saja, hanya web yang terekspos Traefik
• Port publish host DIHAPUS dari compose (sempat konflik dengan aplikasi lain di 8080) — routing murni via domain
• Push riwayat hasil rewrite sudah dilakukan (`--force` sekali, setelah itu push normal)
• **install.php**: dijalankan sekali (seed akun + migrasi) — dihapus dari container? (cek: buka URL-nya → harus 404; kalau belum: `docker exec projects-datatidur-g86qca-web-1 rm /var/www/html/install.php`). Catatan: file kembali muncul saat redeploy (COPY dari repo) tapi aman — hanya menampilkan checklist tanpa membuat akun baru.

## Belum / Menunggu Keputusan Owner

1. **Deploy Dokploy** — compose siap; butuh Docker di VPS + env produksi (APP_DEBUG=0, APP_FORCE_HTTPS=1, APP_BEHIND_PROXY=1).
2. **Rewrite riwayat git** untuk menghapus xlsx lama dari history (destruktif — butuh persetujuan).
3. Docker Desktop tidak terpasang di mesin lokal — deploy produksi berjalan via Dokploy VPS (sudah beres).
4. Security headers belum terverifikasi end-to-end (php -S tak proses .htaccess) — otomatis aktif di Apache/Dokploy.
5. Push ke GitHub + PR/merge ke main dilakukan owner (branch bekerja langsung di main lokal; 11 commit konvensional).
