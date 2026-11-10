# Deteksi Data Tidur — Informasi Data SHPED Halmahera Utara

Aplikasi web untuk **mendeteksi "data tidur"** — harga komoditas yang **konstan (tidak berubah) selama tiga bulan atau lebih** — dari file **Excel/CSV** yang diunggah admin, lalu menampilkannya dengan tampilan bergaya **PIHPS Nasional**: kartu ringkasan, *Informasi Data SHPED* dengan dropdown filter **Kuesioner → Komoditas → Tahun → Kecamatan** di **halaman publik**, sedangkan **tabel harga bulanan, galeri grafik 1 komoditas = 1 grafik (4 kolom × 6 baris per halaman), peta harga Kabupaten Halmahera Utara, dan tabel kelengkapan data** kini **hanya ditampilkan di dashboard admin** (dapat dilihat admin maupun viewer).

> **Data tidur** = harga yang konstan **minimal 3 bulan berturut-turut** (konstan hanya 2 bulan **tidak** dihitung). Contoh: harga **beras, gula, dan garam tetap Rp 30.000/kg dari Mei sampai Juli** → sistem menghitungnya sebagai **3 data tidur**.

---

## Fitur Utama

| Sisi | Fitur |
|---|---|
| **User (publik)** | **Hero hijau** berfont **DM Sans** (font seluruh situs) + foto **Pasar Modern Tobelo** |
| | **3 kartu ringkasan** (klik): **Total Komoditas** (persis jumlah komoditas di file Excel), **Komoditas Data Tidur**, dan **Kelengkapan Data** — untuk admin/viewer menuju ke bagian terkait di dashboard |
| | **Informasi Data SHPED** — **4 tombol dropdown berurutan kiri→kanan**: **KUESIONER** (kode dari kolom *Kuesioner* file SHPED — HKD-1, HKD-2.1, … — memfilter daftar komoditas pada card/grid agar **hanya menyesuaikan kuesioner terpilih**), **Komoditas** (dengan **daftar kualitas di bawah nama komoditas**), **Tahun**, dan **Kecamatan** (**default: Tobelo**) → menampilkan detail harga bulanan + grafik tren. **Dropdown Tahun otomatis berisi tahun dari SEMUA file yang diunggah** — unggah data 2025 lalu 2026 → pilihan **2025 dan 2026 langsung muncul** (lengkap dengan jumlah komoditas & periode per tahun); memilih tahun milik unggahan lain memuat ulang data otomatis |
| **Dashboard admin** | **Seluruh bagian data dipindah ke sini** (tidak lagi di halaman publik): **tabel harga bulanan** komoditas terpilih, **grid grafik ala PIHPS Nasional** (4 kolom × 6 baris per halaman, klik kartu = buka detail), **peta harga** Leaflet per kecamatan (hijau terendah → merah tertinggi + legenda + peringkat), dan **Kelengkapan Data** (tab *Semua / Data Tidur / Harga Bergerak*, pencarian, halaman, **Unduh CSV/Excel**) |
| **Role** | **Login admin & viewer** (bcrypt + CSRF + session hardening): **admin** = akses penuh (unggah, analisis, hapus); **viewer** = **hanya dapat melihat** semua tampilan (halaman depan + dashboard admin), **tidak dapat mengunggah, mengedit, ataupun menghapus** — tombol disembunyikan dan endpoint server menolak (401/403) |
| **Admin** | Dashboard: **Periode Data** (diambil dari **tanggal-bulan-tahun file Excel**), jumlah **Komoditas** sesuai file, **Data Tidur**, total unggahan, status layanan, riwayat unggahan |
| | Upload **drag & drop** + preview + **mapping peran kolom** (komoditas, kualitas, kecamatan, satuan, **kuesioner**) dengan **tebakan otomatis** |
| | Format **lebar** (kolom = bulan Mei/Juni/Juli …): centang kolom bulan + isi tahun. Opsi format baris telah dihapus dari UI |
| | Analisis otomatis saat upload → langsung tampil di halaman publik & dashboard |

**Teknologi:** PHP 8.5 · Tailwind CSS · Font Awesome 6 · **DM Sans** · Apache ECharts 5 · Leaflet · MySQL · XAMPP 8.5 · Python 3.15 + FastAPI · Pandas + NumPy · OpenPyXL

> Seluruh ikon memakai **Font Awesome 6.7** (tanpa emoji). Font situs diganti seluruhnya ke
> **[DM Sans](https://fonts.google.com/specimen/DM+Sans)** (Google Fonts, bobot 100–1000) —
> menggantikan Inter dan Kantumruy Pro pada versi sebelumnya.

---

## Konsep Analisis

- **Komoditas** = pasangan unik **(nama + kualitas)**, mis. "Beras — Premium" dan "Beras — Medium" dihitung 2 komoditas. **Jumlah komoditas yang tampil = persis jumlah baris unik di file Excel** (Excel 600 komoditas → kartu & dashboard menampilkan 600).
- **Kuesioner** = kode kuesioner pada kolom *Kuesioner* file SHPED (mis. **HKD-1, HKD-2.1**) — tersimpan per seri di tabel `analisis_seri` dan menjadi **dropdown filter paling kiri** di Informasi Data SHPED. Memilih kuesioner menyaring daftar komoditas pada dropdown, panel detail, dan **card/grid grafik** agar hanya komoditas milik kuesioner itu yang tampil (grid tidak lagi terlalu banyak).
- **Seri** = komoditas × kualitas × **kecamatan** (unit wilayah untuk peta & tabel).
- **Harga dianalisis per BULAN** (rata-rata bila satu bulan berisi banyak tanggal).
- **Data tidur** = harga semua bulan **sama (konstan)** dan data **≥ 3 bulan** (`min_bulan_tidur` di `DEFAULT_CFG`). **Konstan hanya 2 bulan TIDAK dihitung data tidur** — alasannya tampil otomatis, mis. *"Harga konstan Rp 18.000/kg selama 2 bulan (November–Desember 2025) — konstan kurang dari 3 bulan, tidak dihitung data tidur"*. Bila memenuhi syarat: *"Harga konstan Rp 30.000/kg selama 3 bulan (Mei–Juli 2026)"*.
- **Harga bergerak** = harga berubah minimal satu bulan (normal).
- **Kelengkapan data** = seluruh komoditas: jumlah **data tidur** + jumlah **harga bergerak**; detail lengkapnya ada di tabel kelengkapan (dapat diunduh).
- Bila file **tidak punya kolom kualitas/kecamatan/satuan**, dipakai default **"Standar" / "Tobelo" / "/kg"**.
- Kolom waktu fleksibel: `2026-05-01`, `01/05/2026`, **nama bulan** (`Mei`, `Mei 2026`), dan pada format lebar header kolom langsung jadi bulan.
- **Tebakan kolom bulan mengabaikan kolom berakhiran `Prev`** (ekspor SHPED: "Januari Prev" = harga bulan sebelumnya) agar harga tiap bulan tidak tercampur; kolom tetap bisa dipilih manual bila diperlukan.
- **BOM UTF-8 pada header CSV dibuang otomatis** (file ekspor sering berawalan BOM) sehingga kolom **`Tahun`** tetap dikenali dan tahun default mengikuti isi file (bukan tahun berjalan).

---

## Arsitektur & Alur Data

```
┌─────────────────────────── BROWSER ────────────────────────────┐
│  Halaman Publik (user)          Dashboard Admin                │
│  index.php + landing.js         dashboard.php / upload.php     │
│  Tailwind + ECharts + Leaflet   Tailwind + fetch()             │
│  (SHPED + detail + grafik)      (tabel, grafik, peta,          │
│                                  kelengkapan — hanya di sini)  │
└──────────────┬─────────────────────────────┬──────────────────┘
               │ JSON (1 request)            │ upload file + mapping peran
               ▼                             ▼
┌────────────────── APACHE / PHP 8.5 (XAMPP 8.5.x) ──────────────┐
│  api/data.php      → JSON landing (ringkasan + seri + titik)   │
│  api/download.php  → unduh CSV (PHP) / Excel (proxy FastAPI)   │
│  api/upload_file   → simpan file + catat uploads               │
│  api/analyze       → teruskan mapping peran ke FastAPI         │
│  login/logout/install/auth (session, CSRF, bcrypt, role)       │
└──────────────┬─────────────────────────────────┬───────────────┘
               │                                 │ HTTP (cURL)
               ▼                                 ▼
┌────────── MySQL (phpMyAdmin) ───────────┐  ┌── Python FastAPI ──────────────┐
│ db_data_tidur                           │  │ 127.0.0.1:8000                 │
│ ├ users (admin + viewer)                │  │ /health /inspect /analyze      │
│ ├ uploads        (riwayat + ringkasan)◄─┼──┤ /export (xlsx/csv unduhan)     │
│ ├ harga_bulanan  (harga per bulan)      │  │ Pandas + NumPy + OpenPyXL:     │
│ └ analisis_seri  (hasil per seri +      │  │ baca → bersihkan → agregasi    │
│                  kode kuesioner)        │  │ bulanan → deteksi tidur → MySQL│
└─────────────────────────────────────────┘  └────────────────────────────────┘
```

**Alur singkat:** Admin upload → PHP menyimpan file & mencatat `uploads` → PHP memanggil FastAPI `/analyze` → FastAPI membaca file (Pandas/OpenPyXL), **mengagregasi per bulan**, mendeteksi data tidur, **menulis ke MySQL** → halaman publik membaca lewat `api/data.php` dan menggambar semua visualisasi.

---

## Struktur Folder

```
deteksi-data-tidur/
├── README.md
├── docs/
│   └── PENJELASAN_KODE.md        ← penjelasan setiap file & algoritma
├── database/
│   └── schema.sql                ← skema MySQL (import ke phpMyAdmin)
├── jalankan_api.bat / .sh        ← cara cepat menjalankan FastAPI
├── app/                          ← SALIN seluruh isi folder ini ke htdocs
│   ├── config/  config.php · db.php
│   ├── includes/ auth.php · header.php · footer.php ·
│   │             section_shped.php · section_grafik.php ·
│   │             section_peta.php · section_kelengkapan.php
│   ├── api/
│   │   ├── data.php              ← JSON publik (landing, 1 request)
│   │   ├── download.php          ← UNDUHAN user: CSV (PHP) / Excel (proxy)
│   │   ├── upload_file.php       ← terima file + minta preview FastAPI
│   │   ├── analyze.php           ← relay mapping peran → FastAPI /analyze
│   │   ├── sample.php            ← tombol "Coba Data Contoh"
│   │   ├── uploads.php           ← GET statistik+riwayat / POST hapus
│   │   └── health.php            ← cek status layanan Python
│   ├── index.php                 ← HALAMAN PUBLIK (hero, ringkasan, SHPED,
│   │                                modal metadata SHPED — tanpa banner dashboard)
│   ├── dashboard.php             ← ADMIN+VIEWER (tabel, grafik, peta, kelengkapan)
│   ├── login.php · logout.php · upload.php · install.php
│   ├── assets/
│   │   ├── css/style.css         ← dropdown, tab, pagination, label peta, legenda,
│   │   │                            animasi modal metadata
│   │   ├── js/landing.js         ← data SHPED: kuesioner/komoditas/tahun/kecamatan,
│   │   │                            detail, tabel, grid grafik, peta, kelengkapan
│   │   │                            (dipakai index.php + dashboard.php, guarded)
│   │   ├── js/upload.js          ← drag-drop, mapping format lebar
│   │   ├── js/dashboard.js       ← statistik (komoditas, periode), riwayat
│   │   └── img/logo.png · pasar-modern1.jpeg
│   ├── sample/
│   │   ├── contoh_harga.csv      ← 165 baris: 11 komoditas × 5 kecamatan × 3 bulan
│   │   ├── contoh_harga.xlsx     ← versi Excel (format baris)
│   │   └── contoh_harga_lebar.xlsx ← versi FORMAT LEBAR (kolom Mei/Juni/Juli)
│   └── uploads/
└── python-api/
    ├── main.py                   ← FastAPI: /health /inspect /analyze /export
    ├── analyzer.py               ← INTI: agregasi bulanan + deteksi data tidur
    ├── db.py                     ← tulis hasil + ambil data unduhan
    ├── config.py · requirements.txt
    └── generate_sample.py        ← regenerasi ketiga file contoh
```

**Data contoh** (Mei–Juli 2026, kecamatan Tobelo, Tobelo Barat, Galela, Kao, Wasile Selatan):
4 komoditas **konstan** (Beras Premium, Gula Pasir, Garam Beryodium @ Rp 30.000 di Tobelo, dan Terigu Segitiga Biru) → **data tidur** (konstan **3 bulan** — memenuhi aturan minimal 3 bulan); 7 lainnya bergerak (Beras Medium, Garam Krosok, Minyak Goreng Kemasan/Curah, Telur Ayam Ras, Daging Ayam Ras, Cabai Rawit Merah).

---

## Cara Menjalankan (XAMPP)

### Prasyarat
- **XAMPP 8.5.x** (Apache + MySQL + PHP 8.5) — [apachefriends.org](https://www.apachefriends.org)
- **Python 3.15** (minimal 3.12) terpasang di PATH

> Aplikasi menargetkan **PHP 8.5** (XAMPP 8.5.x) dan memakai tipe kembalian
> `never` (PHP 8.1+) di `app/includes/auth.php`. Kode PHP sengaja tidak memakai
> operator pipe `|>` PHP 8.5 agar semua editor (VS Code/Intelephense) tidak
> menandai error sintaks palsu.

### Langkah 1 — Database
1. Start **Apache** dan **MySQL** dari XAMPP Control Panel.
2. Buka `http://localhost/phpmyadmin` → **Import** → pilih `database/schema.sql` → **Go**.
3. Pastikan database `db_data_tidur` muncul dengan tabel `users`, `uploads`, `harga_bulanan`, `analisis_seri`.

> **Pernah memakai versi lama?** `schema.sql` otomatis menghapus tabel lama
> (`harga_harian` dsb.) saat diimpor ulang — cukup import sekali lagi.

### Langkah 2 — Aplikasi PHP
1. Salin **seluruh isi folder `app/`** ke htdocs, mis. `C:\xampp\htdocs\data-tidur`.
2. Buka `http://localhost/data-tidur/install.php` → membuat akun admin + viewer, migrasi kolom **kuesioner** pada `analisis_seri` (untuk database lama), dan folder uploads.
3. **Hapus `install.php`** setelah selesai (disarankan).

### Langkah 3 — Layanan Analisis Python
- **Cara cepat (Windows):** klik dua kali `jalankan_api.bat` — ini cara PALING MUDAH dan bebas dari masalah PowerShell di bawah. Atau manual:
  ```bash
  cd python-api
  python -m venv .venv
  # Windows (CMD): .venv\Scripts\activate          | Linux/macOS: source .venv/bin/activate
  # Windows (PowerShell): .venv\Scripts\Activate.ps1
  pip install -r requirements.txt
  python -m uvicorn main:app --host 127.0.0.1 --port 8000
  ```
- Cek `http://127.0.0.1:8000/health` → `{"ok": true, ...}` (kolom `uploads_dir` harus menunjuk folder uploads PHP dan `uploads_dir_ada: true`).

> ⚠️ **Error PowerShell: "Activate.ps1 cannot be loaded because running scripts is
> disabled on this system"** — ini pengaturan keamanan bawaan Windows (bukan bug
> proyek). Pilih salah satu solusi:
>
> 1. **Termudah:** jangan aktifkan venv lewat PowerShell — pakai **CMD**
>    (`cmd` di Start Menu) atau langsung klik dua kali **`jalankan_api.bat`**.
> 2. Izinkan skrip hanya untuk jendela ini (sementara, hilang saat ditutup):
>    ```powershell
>    Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
>    .venv\Scripts\activate
>    ```
> 3. Izinkan permanen untuk akun Anda (tidak perlu hak admin):
>    ```powershell
>    Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
>    ```
> 4. Tanpa aktivasi venv sama sekali — panggil Python venv langsung:
>    ```powershell
>    .venv\Scripts\python.exe -m uvicorn main:app --host 127.0.0.1 --port 8000
>    ```

### Langkah 4 — Mulai Menggunakan
| URL | Keterangan |
|---|---|
| `http://localhost/data-tidur/` | **Halaman publik** |
| `http://localhost/data-tidur/login.php` | **Login** — admin `admin` / `admin123` (akses penuh) atau viewer `viewer` / `viewer123` (hanya melihat) |

**Alur penggunaan:**
1. Login admin → **Dashboard** (kartu **Periode Data** tampil sesuai file terakhir).
2. **Coba Data Contoh** (uji cepat) **atau** **Upload Data Baru**.
3. Di halaman upload: tarik file → sistem **menebak peran kolom & kolom bulan otomatis** → sesuaikan bila perlu (pilih juga **Kolom Kuesioner**, mis. `Kuesioner` → HKD-1/HKD-2.1) → **Mulai Analisis**. Centang kolom bulan (Mei, Juni, …) dan isi tahun.
4. Hasil: jumlah **Komoditas**, **Data Tidur**, **Harga Bergerak**, dan **Periode** dari file.
5. **Halaman publik** menampilkan ringkasan + **Informasi Data SHPED** (filter Kuesioner → Komoditas → Tahun → Kecamatan + detail + grafik tren).
6. **Dashboard admin** (admin & viewer) memuat bagian **Data & Analisis SHPED**: tabel harga bulanan, grid grafik, peta harga, dan kelengkapan data (dapat diunduh).
7. **Akun viewer** hanya melihat: semua tombol unggah/edit/hapus disembunyikan dan endpoint server menolak permintaan tulis (401/403).

### Mengganti Password Admin
```bash
php -r "echo password_hash('passwordBaruAnda', PASSWORD_DEFAULT);"
```
Lalu di phpMyAdmin: `UPDATE users SET password='HASIL_DI_ATAS' WHERE username='admin';`

---

## Dokumentasi API

### PHP (dipakai halaman web)
| Endpoint | Metode | Keterangan |
|---|---|---|
| `api/data.php?action=landing[&tahun=YYYY]` | GET | **Publik.** Ringkasan + seluruh seri (komoditas × kualitas × kecamatan **+ kuesioner**) + titik harga bulanan (1 request) + `tahun_tersedia` (daftar tahun dari **semua unggahan** untuk dropdown Tahun). `&tahun=2025` memuat unggahan yang memuat tahun itu |
| `api/download.php?format=csv\|xlsx[&upload_id=][&tahun=]` | GET | **Publik.** Unduh seluruh data (harga bulanan + status). CSV dibuat PHP; Excel diproxy ke FastAPI. Tanpa parameter = unggahan terbaru; `&tahun=2025` mengikuti tahun yang dipilih |
| `api/upload_file.php` | POST multipart | **Admin.** Simpan file → `upload_id` + kolom + preview + tebakan (mode & peran kolom, termasuk `kuesioner_col`) |
| `api/analyze.php` | POST JSON | **Admin.** `{upload_id, mode, komoditas_col, kualitas_col?, kecamatan_col?, satuan_col?, kuesioner_col?, value_col, time_col, month_cols?, tahun_default?}` |
| `api/sample.php` | POST | **Admin.** Analisis data contoh |
| `api/uploads.php` | GET / POST | GET: **admin + viewer** (statistik + riwayat). POST hapus: **admin saja** (viewer → 403) |
| `api/health.php` | GET | Status layanan FastAPI |

### Python FastAPI (http://127.0.0.1:8000)
| Endpoint | Keterangan |
|---|---|
| `GET /health` | Cek layanan + diagnosis folder uploads |
| `POST /inspect` | Multipart file → `{columns, preview, total_rows, guess{mode, *_col, kuesioner_col, month_cols, tahun_default}}` |
| `POST /analyze` | `{file_path, upload_id, mapping, cfg?}` → ringkasan + meta seri **termasuk `kuesioner`** (tanpa titik) |
| `GET /export?upload_id=&format=xlsx\|csv` | **Publik.** File unduhan seluruh data (dipakai `api/download.php`) |

Contoh respons ringkasan `/analyze`:
```json
{"ok": true,
 "ringkasan": {"total_baris": 165, "total_seri": 55, "total_komoditas": 11,
               "total_tidur": 4, "total_bergerak": 7, "jumlah_bulan": 3,
               "periode_mulai": "2026-05-01", "periode_selesai": "2026-07-31"},
 "seri": [{"komoditas": "Beras Premium", "kualitas": "Premium", "kecamatan": "Tobelo",
           "is_tidur": true, "alasan_tidur": "Harga konstan Rp 30.000/kg selama 3 bulan (Mei–Juli 2026)", ...}]}
```

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| **Analisis gagal (HTTP 422) / "Tidak ada baris valid"** | Server menemukan 0 data yang bisa dianalisis. Pesan error kini memuat **diagnosis lengkap**: berapa sel harga terbaca + contoh isian yang gagal, dan label bulan mana yang tidak dikenali. Penyebab umum: (1) header bulan berupa **angka polos "1"–"12"** — SUDAH DIPERBAIKI di versi ini, gunakan zip terbaru; (2) kolom harga dipetakan ke kolom non-harga (mis. kolom teks); (3) tahun tidak diisi pada mode lebar. Catatan: bila pesan yang muncul hanya "Analisis gagal (HTTP 422)" tanpa diagnosis, berarti file `app/api/analyze.php` masih versi lama — perbarui dari zip terbaru |
| **`ERROR: [Errno 10048] error while attempting to bind on address ('127.0.0.1', 8000)`** | **Port 8000 masih dipakai proses lama** (biasanya uvicorn sebelumnya yang belum ditutup — cek tab terminal lain di VS Code, mungkin masih jalan; hentikan dengan Ctrl+C). Cara membersihkan: klik dua kali **`jalankan_api.bat`** (otomatis mematikan proses lama), atau manual: `netstat -ano | findstr :8000` → catat PID di kolom terakhir baris LISTENING → `taskkill /PID <PID> /F` → jalankan ulang uvicorn. Verifikasi: `http://127.0.0.1:8000/health` |
| **Upload gagal / muncul "204 No Content"** | Respons kosong = permintaan tidak pernah sampai ke aplikasi. Urutan pengecekan: (1) jalankan `jalankan_api.bat` — versi baru otomatis mematikan proses lama di port 8000; (2) muat ulang halaman (Ctrl+F5) dan pastikan kotak **"Layanan analisis aktif"** berwarna hijau di atas form upload; (3) matikan sementara antivirus / ekstensi browser yang memfilter unggahan (Avast/AVG Web Shield, ad-blocker) lalu coba lagi; (4) pastikan membuka aplikasi lewat **Apache** (`http://localhost/deteksi-data-tidur/`), bukan `http://127.0.0.1:8000` |
| "Layanan analisis (FastAPI) tidak dapat dihubungi" | Jalankan `jalankan_api.bat` / perintah uvicorn; cek `http://127.0.0.1:8000/health` |
| Chip **API Offline** di dashboard | Layanan Python belum jalan — dashboard & halaman publik tetap bisa membaca data lama; **unduhan Excel** butuh API aktif (unduhan CSV tetap jalan) |
| "Koneksi database gagal" | Pastikan MySQL jalan di XAMPP dan `schema.sql` sudah diimpor (versi baru: tabel `harga_bulanan`) |
| Upload file besar selalu gagal | Naikkan `upload_max_filesize` dan `post_max_size` di `C:\xampp\php\php.ini` (mis. 20M) lalu **restart Apache**; pesan error kini menyebutkan kode error PHP secara spesifik |
| Kode terbaru tidak muncul / perilaku lama | (1) **Tutup lalu jalankan ulang** `jalankan_api.bat` — proses Python lama tetap memakai kode lama walau file sudah diganti; (2) browser: Ctrl+F5 (cache JS/CSS sudah otomatis di-bust via versi file, tapi hard refresh lebih aman); (3) pastikan hanya ADA SATU salinan proyek di htdocs — hapus salinan ganda di folder Downloads |
| Jumlah komoditas tidak sesuai harapan | Cek mapping **kolom kualitas** — "Beras Premium" & "Beras Medium" dihitung 2 komoditas; tanpa kolom kualitas keduanya menyatu |
| **Dropdown Kuesioner kosong / hanya "Semua Kuesioner"** | Data yang sedang tampil dianalisis **sebelum fitur ini ada** (kolom kuesioner belum tersimpan). Solusi: buka `install.php` sekali (menambah kolom `kuesioner` di `analisis_seri`), lalu **unggah ulang** file dengan **Kolom Kuesioner** dipilih di halaman upload (mis. kolom `Kuesioner` → HKD-1/HKD-2.1) dan analisis kembali |
| **Login viewer gagal** | Akun `viewer` / `viewer123` dibuat oleh `install.php` versi baru; bila database lama dibuat sebelum update, jalankan `install.php` sekali untuk menambah role viewer (atau import ulang `schema.sql` — hati-hati: menghapus data unggahan) |
| Peta kecamatan tidak muncul | Nama kecamatan di Excel harus cocok dengan `KOORDINAT_KEC` di `landing.js` (daftar 23 kecamatan Halmahera Utara; tambahkan/sesuaikan bila perlu) |
| Peta/grafik kosong | CDN (Tailwind/ECharts/Leaflet/OSM) butuh internet saat pertama load |
| Excel `.xls` lama ditolak | Simpan ulang sebagai `.xlsx` (OpenPyXL hanya membaca `.xlsx`) |
| Ganti port FastAPI | Ubah `API_BASE` di `app/config/config.php` |
| File sudah diunggah tapi tidak muncul | Lihat kolom **Status** di riwayat dashboard (Proses/Gagal + pesan) |

---

## Pengembangan Lanjutan (Ide)
- Batas wilayah (GeoJSON) kecamatan Halmahera Utara untuk peta choropleth penuh.
- Cron/antrian analisis untuk file besar; riwayat periode ganda (multi-tahun).
- Notifikasi email saat ada data tidur baru; kompilasi Tailwind untuk produksi.
