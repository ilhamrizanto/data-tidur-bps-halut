# Deteksi Data Tidur — Informasi Data SHPED Kab. Halmahera Utara

Aplikasi web untuk **mendeteksi "data tidur"** — harga komoditas yang **konstan (tidak berubah) selama tiga bulan atau lebih** — dari file **Excel/CSV** yang diunggah admin, lalu menampilkannya dengan tampilan bergaya **PIHPS Nasional**.

**Stack:** PHP 8.1+ (direkomendasikan 8.3) · MySQL/MariaDB · Python 3.12+ · FastAPI · Pandas + NumPy + OpenPyXL · Tailwind CSS · ECharts · Leaflet · **Docker Compose (Dokploy-ready)**

> **Data tidur** = harga yang konstan **minimal 3 bulan berturut-turut** (konstan hanya 2 bulan **tidak** dihitung). Contoh: harga **beras, gula, dan garam tetap Rp 30.000/kg dari Mei sampai Juli** → sistem menghitungnya sebagai **data tidur**.

---

## Fitur Utama

| Sisi | Fitur |
|---|---|
| **User (publik)** | Hero hijau berfont **DM Sans** + foto Pasar Modern Tobelo; **3 kartu ringkasan** (Total Komoditas, Komoditas Data Tidur, Kelengkapan Data); **Informasi Data SHPED** dengan 4 dropdown berurutan **Jenis Dokumen → Komoditas → Tahun → Kecamatan**; **modal Metadata SHPED** + unduh kuesioner (HD-1…HD-6, HKD-1, HKD-2.1, HKD-2.2 — daftar otomatis dari `docs/kuesioner/`); **halaman Tentang Kami** tersendiri (`tentang-kami.php`) dengan jam langsung WIT |
| **Dashboard admin** | Tabel harga bulanan, **grid grafik ala PIHPS** (4×6 per halaman), **peta harga** Leaflet per kecamatan, **Kelengkapan Data** (tab + pencarian + Unduh CSV/Excel) |
| **Role** | **admin** = akses penuh (unggah, analisis, hapus); **viewer** = hanya melihat (endpoint server menolak tulisan 401/403, bukan hanya UI) |
| **Upload** | Drag & drop + **mapping peran kolom dengan tebakan otomatis** (komoditas, kualitas, kecamatan, satuan, **kuesioner**), adaptasi khusus ekspor SHPED (kolom `Prev` dikecualikan, prioritas "Nama Kualitas") |

**Teknologi lengkap:** Tailwind CSS · Font Awesome 6 · DM Sans · Apache ECharts 5 · Leaflet · MySQL · FastAPI · Pandas + NumPy · OpenPyXL.

---

## Konsep Analisis

- **Komoditas** = pasangan unik **(nama + kualitas)** — "Beras — Premium" dan "Beras — Medium" = 2 komoditas.
- **Seri** = komoditas × kualitas × **kecamatan**.
- **Harga dianalisis per BULAN** (rata-rata bila satu bulan berisi banyak tanggal).
- **Data tidur** = harga semua bulan **konstan** dan **≥ 3 bulan** (`min_bulan_tidur` di `DEFAULT_CFG`). Konstan 2 bulan **tidak** dihitung — alasan otomatis berbahasa Indonesia.
- **Ringkasan dihitung dua lapis**: `total_komoditas/total_tidur/total_bergerak` per **nama komoditas** (kartu ringkasan); status per **seri** tersimpan di `analisis_seri` (tabel, peta, grafik).
- Pembersihan angka Indonesia (`Rp 12.500`, `1.234,56`), 30+ label bulan, **BOM UTF-8 dibuang otomatis**, format file **baris** maupun **lebar**.
- File tanpa kolom kualitas/kecamatan/satuan memakai default **"Standar" / "Tobelo" / "/kg"**.

Penjelasan teknis mendalam per file: [`docs/PENJELASAN_KODE.md`](docs/PENJELASAN_KODE.md).

---

## Arsitektur

```
┌──────────────────────────── BROWSER ────────────────────────────┐
│  Halaman Publik (index.php)     Dashboard (dashboard.php)       │
└──────────────┬───────────────────────────┬──────────────────────┘
               │ JSON                      │ upload + mapping
               ▼                           ▼
┌──────────── WEB (PHP 8.3 + Apache) ─────────────────────────────┐
│  api/*.php — landing, unduh, upload, analyze (CSRF + role)      │
└──────────────┬────────────────────────────┬─────────────────────┘
               │                            │ HTTP proxy (cURL)
               ▼                            ▼
┌─── MySQL/MariaDB ───────────┐  ┌─── API (FastAPI, internal) ─────┐
│ users · uploads             │  │ /health /inspect /analyze       │
│ harga_bulanan · analisis_seri│ │ /export — Pandas + NumPy        │
│ login_attempts (throttle)   │  │ deteksi tidur → tulis MySQL     │
└─────────────────────────────┘  └─────────────────────────────────┘
```

Dalam Docker (`deploy/docker-compose.yml`) ketiga lapisan berjalan sebagai **3 service**: `db` (MySQL 8.4), `api` (uvicorn, **tidak diekspos ke publik**), `web` (Apache, satu-satunya port publik). Folder `uploads/` dibagi via shared volume; PHP mengirim `file_name` (basename) dan FastAPI me-resolve di dalam `UPLOAD_DIR` miliknya — aman lintas container.

**Alur singkat:** Admin upload → PHP menyimpan file & mencatat `uploads` → PHP memanggil FastAPI `/analyze` (mengirim `file_name`) → FastAPI membaca file (Pandas/OpenPyXL), **mengagregasi per bulan**, mendeteksi data tidur, **menulis ke MySQL** → halaman publik membaca lewat `api/data.php`.

---

## Struktur Proyek

```
data-tidur-bps-halut/
├── index.php · dashboard.php · upload.php · login.php · logout.php
├── install.php                ← setup + migrasi + seed akun (ENV-based)
├── tentang-kami.php
├── api/                       ← data · download · upload_file · analyze ·
│                                 sample · uploads · health
├── config/                    ← config.php (env-driven) · db.php (PDO)
├── includes/                  ← auth.php · header · footer · section_*.php
├── assets/                    ← css · js (landing, upload, dashboard) · img
├── database/db_data_tidur.sql ← skema lengkap + tabel login_attempts
├── docs/                      ← Buku Panduan PDF + kuesioner HD/HKD + PENJELASAN_KODE.md
├── sample/                    ← contoh_harga.csv (baris) + contoh_harga_lebar.xlsx (lebar)
├── uploads/                   ← runtime (di-gitignore; .htaccess anti-eksekusi)
├── python-api/                ← main.py · analyzer.py · db.py · config.py ·
│                                 tests/ (pytest) · requirements.txt
├── deploy/                    ← Dockerfile.web · Dockerfile.api ·
│                                 docker-compose.yml · web-entrypoint.sh
├── scripts/                   ← smoke.sh · router-local.php
├── jalankan_api.bat / .sh     ← cara cepat menjalankan FastAPI (XAMPP)
├── .env.example               ← template konfigurasi (salin → .env)
└── .hermes.md                 ← catatan konteks untuk agen
```

**Data contoh** (`sample/`): 165 baris = 11 komoditas × 5 kecamatan (Tobelo, Tobelo Barat, Galela, Kao, Wasile Selatan) × 3 bulan (Mei–Juli 2026). Hasil analisis: **4 komoditas tidur** (konstan di semua kecamatan), 7 bergerak — 20 seri tidur dari 55 seri.

---

## Menjalankan Lokal

### Opsi A — Docker Compose (disarankan)

```bash
cp .env.example .env          # isi password
docker compose -f deploy/docker-compose.yml --env-file .env up -d --build
docker compose -f deploy/docker-compose.yml ps      # 3 service healthy
# buka http://localhost:8090 → install.php sekali → login
```

Skema database terimpor otomatis saat pertama kali (`docker-entrypoint-initdb.d`). Akun admin/viewer dibuat dari `.env` (`INSTALL_ADMIN_USERNAME/PASSWORD`, dst.) saat membuka `install.php` pertama kali — **hapus install.php setelahnya**.

### Opsi B — XAMPP klasik

1. Start Apache + MySQL → impor `database/db_data_tidur.sql` via phpMyAdmin.
2. Salin isi repo ke `C:\xampp\htdocs\data-tidur`.
3. Jalankan layanan analisis: klik dua kali `jalankan_api.bat` (auto-venv + bersihkan port 8000). Cek `http://127.0.0.1:8000/health` → `"uploads_dir_ada": true`.
4. Buka `http://localhost/data-tidur/install.php` sekali → login.

> Bila PowerShell menolak `Activate.ps1` ("running scripts is disabled"), pakai CMD atau panggil langsung `.venv\Scripts\python.exe -m uvicorn main:app --host 127.0.0.1 --port 8000`.

### Opsi C — Native Windows tanpa Docker

Contoh stack tanpa Docker & tanpa XAMPP: PHP CLI (winget) + **MariaDB portable** (folder `.local-stack/`, di-gitignore) + venv FastAPI:

```bash
# 1) Skema + user aplikasi
mariadb -u root < database/db_data_tidur.sql
# 2) Layanan analisis (port 8000)
cd python-api && python -m venv .venv
.venv\Scripts\pip install -r requirements.txt
# 3) Web (port 8090; router memblokir .env/.git/.local-stack)
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8090 scripts/router-local.php
```

> **`php -S` tidak memproses `.htaccess`** — security headers & proteksi uploads/ hanya aktif penuh di Apache (Opsi A/B) atau Dokploy.

---

## Deploy Produksi ke Dokploy

1. Push repo ke GitHub (`ilhamrizanto/data-tidur-bps-halut`, branch `main`).
2. Dokploy → **Create Service → Compose** → hubungkan repo, branch `main`, **Compose Path**: `deploy/docker-compose.yml`.
3. Tab **Environment** — isi semua variabel dari `.env.example` dengan nilai produksi (password kuat, `APP_DEBUG=0`, `APP_FORCE_HTTPS=1`, `APP_BEHIND_PROXY=1`).
4. **Deploy** → tunggu 3 service healthy.
5. Pasang **Domain** ke service `web` (port 80) — TLS otomatis oleh proxy Dokploy.
6. Buka `https://domain/install.php` **SEKALI** (membuat akun + tabel `login_attempts`) → **hapus install.php**.
7. (Opsional) aktifkan webhook auto-deploy.

## Environment Variables

| Variabel | Default | Keterangan |
|---|---|---|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | `127.0.0.1` / `db_data_tidur` / `root` / kosong | Koneksi MySQL (PHP + Python) |
| `API_BASE` | `http://127.0.0.1:8000` | Alamat FastAPI dari sisi PHP (Docker: `http://api:8000`) |
| `UPLOAD_DIR` | auto-detect | Folder unggahan bersama PHP ↔ Python (Docker api: `/data/uploads`) |
| `APP_DEBUG` | `0` | `1` = detail error tampil di respons (lokal saja) |
| `APP_FORCE_HTTPS` | `0` | `1` = cookie `Secure` (produksi di balik TLS) |
| `APP_BEHIND_PROXY` | `0` | `1` = percayai `X-Forwarded-For` (throttle IP akurat) |
| `APP_TZ` | `Asia/Jayapura` | Zona waktu tampilan (**WIT**) |
| `CORS_ORIGINS` | kosong (deny) | Daftar origin CORS FastAPI, koma; kosong = tanpa CORS |
| `MYSQL_ROOT_PASSWORD` | — | Compose: password root MySQL |
| `INSTALL_ADMIN_USERNAME` / `INSTALL_ADMIN_PASSWORD` | `admin` / acak-tampil-sekali | Akun admin saat install |
| `INSTALL_VIEWER_USERNAME` / `INSTALL_VIEWER_PASSWORD` | `viewer` / acak-tampil-sekali | Akun viewer saat install |
| `WEB_PORT` | `8090` | Compose: port host untuk web |

---

## Pengujian

```bash
# 1) Unit — net karakterisasi analyzer (golden sample SHPED)
cd python-api && python -m venv .venv
.venv\Scripts\pip install -r requirements.txt -r requirements-dev.txt
.venv\Scripts\python -m pytest tests -q
# expected: 4 passed

# 2) Integration — stack harus hidup
ADMIN_PASS='...' bash scripts/smoke.sh http://localhost:8090
# expected: login 302 → sample ok → landing golden 55 seri/4 tidur/7 bergerak → SMOKE OK
```

## Keamanan

- PDO prepared statement di semua query; `EMULATE_PREPARES=false`.
- CSRF token di semua endpoint mutasi (form login & header `X-CSRF-Token`).
- bcrypt + role check dua lapis (UI disembunyikan **dan** endpoint menolak 401/403).
- **Throttle login**: maks 5 gagal per IP+username per 15 menit (tabel `login_attempts`).
- `session_regenerate_id` saat login; cookie httponly + SameSite=Lax + `Secure` (`APP_FORCE_HTTPS`).
- Redaksi pesan error DB di belakang `APP_DEBUG` (detail hanya ke `error_log` server).
- **Tanpa kredensial default**: akun dibuat dari ENV, atau password acak yang tampil sekali di `install.php`.
- Anti path traversal berlapis: nama file acak + `realpath` PHP + `_safe_path(file_name)` FastAPI.
- `uploads/.htaccess`: eksekusi skrip ditolak + directory listing mati; root `.htaccess`: security headers.
- CORS FastAPI **default-deny** (browser tidak pernah memanggil api langsung).

---

## Dokumentasi API

### PHP (dipakai halaman web)
| Endpoint | Metode | Keterangan |
|---|---|---|
| `api/data.php?action=landing[&tahun=YYYY]` | GET | **Publik.** Ringkasan + seluruh seri (+ kuesioner) + titik harga bulanan (1 request) + `tahun_tersedia` |
| `api/download.php?format=csv\|xlsx[&upload_id=][&tahun=]` | GET | **Publik.** Unduh seluruh data. CSV dibuat PHP; Excel diproxy ke FastAPI |
| `api/upload_file.php` | POST multipart | **Admin.** Simpan file → `upload_id` + kolom + preview + tebakan |
| `api/analyze.php` | POST JSON | **Admin.** `{upload_id, mode, komoditas_col, ..., file_name otomatis}` |
| `api/sample.php` | POST | **Admin.** Analisis data contoh |
| `api/uploads.php` | GET / POST | GET: **admin + viewer**. POST hapus: **admin saja** (viewer → 403) |
| `api/health.php` | GET | Status layanan FastAPI |

### Python FastAPI (internal)
| Endpoint | Keterangan |
|---|---|
| `GET /health` | Cek layanan + diagnosis folder uploads |
| `POST /inspect` | Multipart file → `{columns, preview, total_rows, guess}` |
| `POST /analyze` | `{file_path, file_name?, upload_id, mapping, cfg?}` → ringkasan + meta seri (tanpa titik) |
| `GET /export?upload_id=&format=xlsx\|csv` | **Publik.** File unduhan (dipakai `api/download.php`) |

Contoh respons ringkasan `/analyze`:
```json
{"ok": true,
 "ringkasan": {"total_baris": 165, "total_seri": 55, "total_komoditas": 11,
               "total_tidur": 4, "total_bergerak": 7, "jumlah_bulan": 3,
               "periode_mulai": "2026-05-01", "periode_selesai": "2026-07-31"},
 "seri": [{"komoditas": "Beras Premium", "kualitas": "Premium", "kecamatan": "Tobelo",
           "is_tidur": true, "alasan_tidur": "Harga konstan Rp 30.000/kg selama 3 bulan (Mei–Juli 2026)"}]}
```

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| Analisis gagal 422 | Pesan memuat diagnosis (jumlah sel terbaca + contoh gagal + bulan tak dikenali). Umum: kolom harga salah peta, mode lebar tanpa kolom bulan/tahun |
| Port 8000 terpakai (`Errno 10048`) | `jalankan_api.bat` otomatis membersihkan; manual: `netstat -ano \| findstr :8000` → `taskkill /PID <PID> /F` |
| "Layanan analisis tidak dapat dihubungi" | Jalankan uvicorn / `jalankan_api.bat`; cek `/health`; di Docker pastikan service `api` healthy |
| 400 "Path file tidak diizinkan" | `UPLOAD_DIR` PHP ≠ milik api — bandingkan `uploads_dir` di `/health`; di Docker `file_name` sudah otomatis |
| Upload gagal "204 No Content" | Antivirus/ad-blocker memfilter; pastikan lewat Apache, bukan `:8000` |
| "Koneksi database gagal" | MySQL/MariaDB jalan? `database/db_data_tidur.sql` sudah diimpor? kredensial env benar? |
| "Terlalu banyak percobaan gagal" | Throttle login aktif — tunggu 15 menit, atau bersihkan tabel `login_attempts` |
| Upload besar gagal | Naikkan `upload_max_filesize` & `post_max_size` di php.ini (XAMPP), restart Apache |
| Dropdown Jenis Dokumen kosong | Data lama belum punya kolom kuesioner — buka `install.php` sekali, unggah ulang dengan **Kolom Kuesioner** dipilih |
| Peta kecamatan tidak muncul | Nama kecamatan harus cocok `KOORDINAT_KEC` di `landing.js` (23 kecamatan Halut) |
| Peta/grafik kosong | CDN (Tailwind/ECharts/Leaflet/OSM) butuh internet saat load pertama |
| Excel `.xls` ditolak | Simpan ulang sebagai `.xlsx` (OpenPyXL hanya baca `.xlsx`) |
| Ganti port FastAPI | `API_BASE` di env (Docker: service `api`) |
| File diunggah tapi tak muncul | Kolom **Status** di riwayat dashboard (Proses/Gagal + pesan) |

---

## Dokumen Pendukung

- **Buku Panduan**: `docs/Buku_Panduan_Deteksi_Data_Tidur.pdf`
- **Kuesioner SHPED 2026**: `docs/kuesioner/` (HD-1…HD-6, HKD-1, HKD-2.1, HKD-2.2)
- **Penjelasan kode per file**: `docs/PENJELASAN_KODE.md`

## Tim

Profil lengkap di halaman **Tentang Kami**: **Ros** (Ketua Tim Distribusi), **Avhan** (Anggota), mentor **Frida**, dan Tim Pengembang Website — BPS Kab. Halmahera Utara.

## Pengembangan

- Konteks agen/developer: `.hermes.md`; riwayat kerja: `HANDOVER.md`.
- Konvensi commit: conventional commits, satu concern per commit.
- Net regresi: `python-api/tests/` — perubahan `analyzer.py` wajib lolos pytest dulu.
