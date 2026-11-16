# Penjelasan Setiap Bagian Kode

Dokumen ini menjelaskan **setiap file** pada proyek *Deteksi Data Tidur — Informasi Data SHPED* — perannya, logika intinya, dan alasannya dibuat demikian. Baca berurutan mengikuti alur data: **Database → Python → PHP → JavaScript**.

> **Konsep kunci versi ini:** komoditas = pasangan **(nama + kualitas)**; wilayah = **kecamatan** (Kab. Halmahera Utara); harga dianalisis **per bulan**; **data tidur** = harga **konstan minimal 3 bulan berturut-turut** (contoh: beras, gula, garam tetap Rp 30.000/kg Mei–Juli → 3 data tidur; **konstan 2 bulan TIDAK dihitung** — `min_bulan_tidur` di `DEFAULT_CFG`). Font situs = **DM Sans**. **Tabel, grafik, peta, dan kelengkapan data hanya tampil di dashboard admin**; halaman publik menampilkan ringkasan + Informasi Data SHPED dengan **dropdown filter Kuesioner**.

---

## 0. Gambaran Alur Besar

1. Admin **atau viewer** login (PHP session) di `login.php`. **Admin** dapat mengunggah/menghapus; **viewer** hanya dapat melihat semua tampilan (halaman depan + dashboard) tanpa mengunggah, mengedit, ataupun menghapus file.
2. Admin mengunggah file di `upload.php`. `api/upload_file.php` menyimpan file, mencatat baris di tabel `uploads`, lalu memanggil FastAPI `/inspect` agar browser mendapat **daftar kolom + preview + tebakan** (format lebar + peran tiap kolom — termasuk **kuesioner_col**).
3. Admin menetapkan **peran kolom** (komoditas, kualitas, kecamatan, satuan, **kuesioner**) dan **kolom bulan + tahun** lalu klik *Mulai Analisis* → `api/analyze.php` meneruskan mapping ke FastAPI `/analyze`.
4. FastAPI (`analyzer.py`) membaca file dengan **Pandas/OpenPyXL**, **mengagregasi harga per bulan**, menentukan status **data tidur**, dan `db.py` menuliskan hasil ke **MySQL** (termasuk **kode kuesioner per seri**).
5. Halaman publik `index.php` dan **dashboard admin** `dashboard.php` (via `landing.js`) memanggil `api/data.php?action=landing` **satu kali**. Halaman publik merender kartu ringkasan + Informasi Data SHPED; dashboard merender **tabel harga bulanan, grid grafik PIHPS, peta, dan kelengkapan data** (bagian yang dipindah dari halaman publik). Unduhan lewat `api/download.php` (CSV oleh PHP, Excel diproxy ke FastAPI `/export`).

> **Kenapa dipisah PHP vs Python?** PHP unggul untuk serving halaman + sesi di XAMPP; Python unggul untuk komputasi data (Pandas/NumPy). FastAPI menjadi "mesin analisis" yang dipanggil PHP hanya saat upload — file tidak diunggah ulang (dibaca lewat path), dan halaman data membaca langsung dari MySQL tanpa menyentuh Python.

---

## 1. Lapisan Database — `database/db_data_tidur.sql`

| Tabel | Peran |
|---|---|
| `users` | Akun dengan role **`admin`** (akses penuh) dan **`viewer`** (hanya melihat). Password **bcrypt** (`password_hash`). Akun dibuat `install.php` dari **environment variable** `INSTALL_ADMIN_USERNAME/PASSWORD` & `INSTALL_VIEWER_USERNAME/PASSWORD`; password kosong = diacak & ditampilkan sekali. **Tanpa kredensial default.** |
| `uploads` | Satu baris per file unggahan: nama file, status (`processing`/`done`/`failed`), dan **ringkasan**: `total_baris`, `total_seri`, **`total_komoditas`** (unik nama+kualitas — sesuai isi Excel), **`total_tidur`**, **`total_bergerak`**, `periode_mulai/selesai` (dari tanggal-bulan-tahun file). Kartu dashboard & halaman publik membaca tabel ini — cepat tanpa agregasi ulang. |
| `harga_bulanan` | **Harga per BULAN**: 1 baris = 1 harga satu bulan untuk 1 kombinasi **komoditas × kualitas × kecamatan** (kolom `bulan` bertipe DATE = tanggal 1). Sumber titik semua grafik, tabel bulanan, dan peta. |
| `analisis_seri` | Hasil analisis per seri: **`kuesioner`** (kode kuesioner file SHPED — HKD-1, HKD-2.1 — sumber dropdown filter), `jumlah_bulan`, harga awal/akhir/min/max, `perubahan_persen`, **`is_tidur`**, `alasan_tidur`, `bulan_mulai/selesai`. |

**Relasi:** `harga_bulanan` dan `analisis_seri` merujuk `uploads(id)` dengan **ON DELETE CASCADE** — menghapus unggahan otomatis membersihkan semua turunannya. Charset `utf8mb4` agar nama komoditas aman. `db_data_tidur.sql` juga menghapus tabel versi lama (`harga_harian`) bila ada.

> **Migrasi database lama:** `install.php` otomatis menjalankan `ALTER TABLE analisis_seri ADD COLUMN kuesioner ...` (bila kolom belum ada) dan `ALTER TABLE users MODIFY role ENUM('admin','viewer')` — sehingga database versi sebelumnya tidak perlu di-import ulang.

---

## 2. Lapisan Python — `python-api/`

### `config.py`
Pusat konfigurasi Python — **env-driven**: `DB_HOST/DB_USER/DB_PASS/DB_NAME` (default identik dengan XAMPP), **UPLOAD_DIR** (env override → auto-deteksi path XAMPP umum), dan `CORS_ORIGINS` (kosong = tanpa CORS). FastAPI **hanya diizinkan membaca file di dalam folder ini** (lihat `_safe_path`).

### `analyzer.py` — inti algoritma
- **`_clean_number(v)`** — pembersih angka: membuang `Rp`/simbol, menentukan pemisah desimal dari tanda **paling kanan** (`1.234,56` Indonesia vs `1,234.56` Inggris vs `12.500` dibaca ribuan).
- **Nama bulan** — `BULAN_KE_ANGKA` mengenali Januari–Desember penuh & singkat (termasuk `Agu/Ags/Agt`); `_parse_sel_bulan` mengubah `Mei`, `Mei 2026`, `2026-05`, `05/2026` menjadi `(tahun, bulan)`; `_parse_waktu_long` memilih strategi tanggal-penuh vs nama-bulan yang paling banyak berhasil per kolom.
- **`load_table(path)`** — CSV (`sep=None` → deteksi pemisah otomatis, fallback latin-1) atau XLSX (OpenPyXL, `dtype=str`). Nama kolom dirapikan: **BOM UTF-8 dibuang** (file ekspor berawalan `\ufeff` — tanpa ini kolom `Tahun` tidak dikenali sehingga tahun default salah) + trim spasi.
- **`inspect_file(path)`** — menebak **mode** (`wide` bila ada ≥2 header kolom berupa nama bulan) dan **peran kolom**: komoditas / kualitas / kecamatan / satuan / **kuesioner** / harga / waktu / daftar `month_cols` / `tahun_default`. Tebakan `month_cols` **mengabaikan kolom berakhiran `Prev`** (harga bulan sebelumnya pada ekspor SHPED) agar nilai bulan tidak tercampur. Tebakan **kualitas_col memprioritaskan "Nama Kualitas"** di atas "Kode Kualitas"/"Status Kualitas" (file SHPED menaruh kolom kode lebih dulu — tanpa prioritas ini tebakan jatuh ke kolom kode).
- **`analyze_file(path, mapping, cfg)`** — alur utama:
  1. **Mode baris**: 1 baris = 1 harga → parse waktu (tanggal penuh **atau** nama bulan + `tahun_default`).
     **Mode lebar**: `melt` kolom bulan menjadi baris → setiap kolom "Mei/Juni/Juli" jadi periode.
  2. Identitas default bila kolom tidak ada: kualitas **"Standar"**, kecamatan **"Tobelo"**, satuan **"/kg"**; kolom **kuesioner** opsional — tanpa default (kosong = `NULL`).
  3. **Agregasi per bulan**: `groupby(komoditas, kualitas, kecamatan, satuan, bulan)` → rata-rata harga + `jumlah_hari` + `kuesioner` (nilai pertama seri).
  4. `_analyze_series()` per seri: harga awal/akhir, min/max, **perubahan %**, dan **ATURAN TIDUR: harga semua bulan sama (konstan) dan ≥ 3 bulan data** (`min_bulan_tidur` di `DEFAULT_CFG`; **konstan 2 bulan → BUKAN data tidur**, alasan otomatis: *"Harga konstan Rp 18.000/kg selama 2 bulan (November–Desember 2025) — konstan kurang dari 3 bulan, tidak dihitung data tidur"*) — `alasan_tidur` berbahasa Indonesia ("Harga konstan Rp 30.000/kg selama 3 bulan (Mei–Juli 2026)").
  5. **Ringkasan**: `total_komoditas` = pasangan (nama+kualitas) unik; `total_tidur` = komoditas yang **semua** serinya tidur; `total_bergerak` = sisanya; `periode_mulai/selesai` = bulan pertama s.d. **akhir bulan** terakhir.

### `db.py` — penulisan & pembacaan hasil
`save_results()` dalam **satu transaksi**: hapus hasil lama `upload_id` sama (mendukung analisis ulang), perbarui ringkasan `uploads` + status `done`, insert massal `harga_bulanan` (batch 1000) dan `analisis_seri` **termasuk kolom `kuesioner`**. Gagal di tengah → **rollback penuh** + status `failed` berisi pesan. `fetch_export_rows()` mengambil seluruh harga bulanan + status untuk fitur unduhan.

### `main.py` — layanan FastAPI
- `GET /health` — probe kesehatan + diagnosis folder uploads.
- `POST /inspect` — file multipart → preview + tebakan (file sementara dihapus setelah dipakai).
- `POST /analyze` — validasi `_safe_path()` (**anti path traversal**), analisis, simpan MySQL, kembalikan ringkasan **tanpa titik** (respons ringan). Model pydantic `Mapping` menerima **`kuesioner_col`**. `422` data/mapping tidak valid, `400` path ilegal.
- `GET /export?upload_id=&format=xlsx|csv` — **publik**; menyusun DataFrame (Kolom: Komoditas, Kualitas, Kecamatan, Satuan, Bulan "Mei 2026", Harga, Perubahan, Status) → XLSX (pandas+openpyxl, streaming) **atau** CSV pemisah `;` + BOM UTF-8 agar rapi dibuka Excel Indonesia.

---

## 3. Lapisan PHP — `app/`

### Konfigurasi & fondasi
- **`config/config.php`** — kredensial DB, `API_BASE`, batas upload 10 MB, ekstensi, zona waktu.
- **`config/db.php`** — koneksi **PDO** (`db()`), `ERRMODE_EXCEPTION` + prepared statement (anti SQL injection).
- **`includes/auth.php`** — sesi (httponly + SameSite=Lax); fungsi role: **`is_admin()`**, **`is_viewer()`**, **`is_logged_in()`**, **`require_panel()`** (dashboard: admin ATAU viewer), **`require_admin()`** (halaman admin-only; viewer dialihkan balik ke dashboard); **CSRF** (`csrf_token`/`csrf_verify` di semua POST), helper `e()` (anti-XSS), `json_out()` (tipe `never`), `handle_db_error()`.
- **`includes/header.php`** — layout Tailwind + font **DM Sans** (Google Fonts, bobot 100–1000; config `tailwind.config.fontFamily.sans/hero`); navigasi menyesuaikan role: **admin** melihat menu Dashboard · Deteksi Data Tidur · Halaman Publik · Keluar; **viewer** melihat Dashboard · badge "Viewer" · Halaman Publik · Keluar (tanpa menu upload); **publik** melihat Ringkasan · Informasi SHPED · **Tentang Kami (membuka `tentang-kami.php` di TAB BARU)** · **Buku Panduan (unduh PDF)** + tombol Login/Dashboard.

### Section include (dipakai bersama index.php & dashboard.php)
- **`includes/section_shped.php`** — bagian **Informasi Data SHPED**: **4 dropdown berurutan** — **Kuesioner** (paling kiri, `dd-panel-left` agar panel tidak keluar layar), Komoditas (+sub-daftar kualitas + pencarian), Tahun, Kecamatan; panel detail (harga terakhir, badge **kuesioner**, tertinggi/terendah/perubahan, alasan deteksi) + grafik besar. Variabel `$shped_tabel = true` → menampilkan **tabel harga bulanan** (hanya di dashboard).
- **`includes/section_grafik.php`** — grid grafik PIHPS (`#chartGrid` 4×6 + `#chartPager`).
- **`includes/section_peta.php`** — peta Leaflet + panel peringkat harga kecamatan.
- **`includes/section_kelengkapan.php`** — tabel kelengkapan + tab + pencarian + tombol **Unduh CSV/Excel**.
- Keempatnya **hanya di-include dashboard.php** — inilah pemindahan "tabel diikuti grafik, peta, serta kelengkapan data" sehingga semuanya **hanya tampil di dashboard admin**.

### Halaman
- **`dashboard.php`** — halaman admin **dan viewer** (`require_panel()`): kartu statistik (Total Unggahan, **Komoditas Terakhir**, **Data Tidur**, **Periode Data**), riwayat unggahan, lalu bagian **Data & Analisis SHPED** (4 section di atas). **Untuk viewer**: banner "Mode Viewer", kartu aksi (Upload/Coba Contoh) & tombol Unggah **tidak dirender**, kolom Hapus pada riwayat disembunyikan (flag JS `BOLEH_KELOLA=false`), chip status tetap tampil. Muat `dashboard.js` + `landing.js` + ECharts/Leaflet.
- **`upload.php`** — dropzone + preview + panel mapping: **format lebar (kolom = bulan) satu-satunya format**; 5 select peran (Komoditas*, Kualitas, Kecamatan, Satuan, **Kuesioner** — empat terakhir opsional), **chip kolom bulan** auto-terpilih + **Tahun**, panel hasil 4 kotak (Komoditas, Data Tidur, Harga Bergerak, **Periode**). Khusus admin (`require_admin()`).
- **`index.php`** — halaman publik: hero hijau (DM Sans) + foto pasar; **3 kartu ringkasan** (untuk admin/viewer menuju `dashboard.php#grafik` / `dashboard.php#kelengkapan`); bagian **Informasi Data SHPED** (include `section_shped.php` **tanpa** tabel — tabel kini hanya di dashboard); kartu **"Metadata Kegiatan Statistik — SHPED"** yang membuka **pop-up/modal** (`#modalMetadata`) berisi tabel metadata 16 komponen (Nama Kegiatan s.d. Diseminasi — sesuai dokumen metadata BPS) + **kotak "Unduh Kuesioner SHPED (PDF)" di bawah baris Diseminasi** — tombol **Pilih Kuesioner** membuka panel daftar PDF (grup HD & HKD) yang dibaca **otomatis oleh PHP dari `app/docs/kuesioner/*.pdf`** (tautan `download`; daftar ikut berubah bila file di folder ditambah/dihapus); + **Deskripsi Singkat Kegiatan**; modal ditutup via tombol ✕ / **Tutup** / klik area gelap di luar panel (`#modalMetadataArea`) / tombol **Esc**, dengan animasi `.md-backdrop`/`.md-panel` dari `style.css`. Bagian **Tentang Kami in-page SUDAH DIHAPUS** — seluruh profil tim kini ada di halaman `tentang-kami.php` (dibuka dari menu navigasi di tab baru). Banner pengarah ke dashboard + tombol **Buka Dashboard** sudah **dihapus** dari halaman publik. **Tidak lagi memuat** grid grafik, peta, dan kelengkapan data.
- **`tentang-kami.php`** — **halaman "Tentang Kami" tersendiri, dibuka di TAB BARU** saat menu Tentang Kami diklik (desktop & mobile memakai `target="_blank"`). Desain **ala halaman portofolio**: nav minimalis (logo kiri · menu tengah · **lokasi + jam langsung WIT `Asia/Jayapura` + tombol gelap "Unduh Buku Panduan"** kanan), **hero split** — kartu profil gradient (inisial "R") + sapaan besar "Halo! Saya Ros" di kiri, judul jabatan besar + deskripsi + tombol **Lihat Tim Pengembang** (anchor `#pengembang`) dan **Kembali ke Beranda** di kanan — serta **4 ikon mengambang melingkar di sisi kanan layar** (Beranda, Informasi SHPED, unduh Buku Panduan, kembali ke atas). Isi mengikuti urutan permintaan: **1)** **Ros** (Ketua Tim Distribusi — hero utama), **2)** **Avhan** (Anggota Tim Distribusi), **3)** **Frida** (Mentor), **4)** **Tim Pengembang Website 4 orang** + deskripsi keahlian masing-masing (nama masih placeholder — ganti di blok ber-comment "GANTI NAMA"). Halaman ini berdiri sendiri (head Tailwind/DM Sans sendiri, `require auth.php` untuk helper `e()`), lengkap menu mobile, dan footer tautan Beranda/Buku Panduan.
- **`login.php`** — form login **admin & viewer**: memverifikasi `role IN ('admin','viewer')` via bcrypt, menyimpan `$_SESSION['admin_role']`; **throttle anti-bruteforce**: maks 5 login gagal per IP+username per 15 menit via tabel `login_attempts` (IP klien dari `client_ip()`, sadar `X-Forwarded-For` saat `APP_BEHIND_PROXY=1`).
- **`install.php`** — setup + **migrasi otomatis**: tabel `users` (role admin/viewer), **tabel `login_attempts`** (throttle produksi), seed akun **dari ENV** (password kosong = acak & tampil sekali), penambahan kolom `kuesioner` pada `analisis_seri` bila belum ada, folder uploads.

### Endpoint API
- **`api/data.php?action=landing`** — publik; satu request: ringkasan dari `uploads` + semua seri dari `analisis_seri` **(kini termasuk `kuesioner`)** + titik bulanan dari `harga_bulanan` (key join `komoditas|kualitas|kecamatan`). Serialisasi memakai `array_values()` biasa (bukan pipe operator PHP 8.5, agar editor tidak menandai error sintaks). Tanggap `&tahun=YYYY` → memuat unggahan terbaru yang benar-benar punya harga pada tahun itu; respons selalu memuat **`tahun_tersedia`** (GROUP BY `YEAR(bulan)` atas **semua** unggahan `done` + meta unggahan per tahun) — sumber dropdown Tahun multi-file (2025 & 2026 sekaligus). Di `landing.js`: memilih tahun yang sudah ada di data termuat cukup **filter lokal**; tahun milik unggahan lain → `muatDataTahun()` memuat ulang dari server dan mempertahankan pilihan kuesioner/komoditas/kecamatan bila masih ada.
- **`api/download.php`** — **publik**: `format=csv` dibangun langsung PHP (query join `harga_bulanan`+`analisis_seri`, label bulan Indonesia, BOM + pemisah `;`); `format=xlsx` diproxy ke FastAPI `/export` dengan pesan ramah bila layanan mati. Menerima `upload_id` **atau** `tahun`.
- **`api/upload_file.php`** — validasi **admin** + CSRF + ekstensi + ukuran; nama file acak; panggil `/inspect`; bersihkan baris yatim bila gagal. Viewer → 401.
- **`api/analyze.php`** — validasi mapping per mode (lebar wajib ≥1 kolom bulan), **meneruskan `kuesioner_col`** ke FastAPI, pastikan file fisik di dalam uploads. Viewer → 401.
- **`api/sample.php`** — analisis data contoh sekali klik dengan mapping standar. **Admin saja** (viewer → 401).
- **`api/uploads.php`** — **GET**: statistik + 20 unggahan terakhir (admin **dan** viewer). **POST delete**: **admin saja** — viewer menerima **403** "Hanya admin yang dapat menghapus unggahan…".
- **`api/health.php`** — proxy status FastAPI (timeout 3 dtk).

---

## 4. Lapisan JavaScript — `app/assets/js/`

- **`landing.js`** — otak data SHPED, **dipakai index.php DAN dashboard.php** (seluruh kode dibungkus **IIFE** agar aman dimuat berdampingan dengan `dashboard.js`; setiap fungsi **memeriksa keberadaan elemen** — bagian yang tidak ada di halaman itu otomatis dilewati):
  - **State**: `{kuesioner ('semua' | 'HKD-1' | …), nama, kualitas, tahun, kecamatan (default 'semua' = Semua Kecamatan), halGrafik, tab, cari, halTabel}`.
  - **Filter kuesioner (label "Jenis Dokumen")**: `seriTampak()` menyaring seri sesuai kuesioner aktif; `grupKomoditas()` kini **berbasis seriTampak()** — sehingga dropdown komoditas, panel detail, **card/grid grafik**, dan peta hanya menampilkan komoditas milik kuesioner terpilih (grid tidak terlalu banyak); `kuesionerList()` mengumpulkan kode unik (urut alami: HKD-1, HKD-2.1, …); `renderListKuesioner()` menampilkan "Semua Jenis Dokumen" + tiap kode dengan **jumlah komoditasnya**; label tombol saat tanpa filter: **"Jenis Dokumen"**; bila data belum punya kuesioner (analisis versi lama) panel menampilkan petunjuk unggah ulang. `seriAgg()` juga membawa `kuesioner` untuk badge detail & popup peta.
  - **Derivasi lain**: `seriAgg(nama, kualitas)` (gabung seri sesuai filter kecamatan — "semua" = rata-rata antar kecamatan — dan tahun), `tahunList()`, `kecamatanList()`.
  - **Kartu ringkasan** (hanya index.php): count-up; kartu kelengkapan menampilkan "**X data tidur · Y harga bergerak**".
  - **Dropdown SHPED**: panel kustom (tutup saat klik luar), daftar komoditas **dengan sub-daftar kualitas terlipat** + pencarian; perubahan kuesioner reset halaman grafik + pilih komoditas default milik kuesioner; perubahan tahun/kecamatan/komoditas memicu render ulang bagian terkait. Fallback otomatis ke "Semua Kecamatan" bila komoditas tidak punya data di kecamatan terpilih.
  - **Grid grafik PIHPS** (dashboard): satu kartu per (komoditas + kualitas) — judul, harga terakhir, badge **TIDUR** atau chip naik/turun, **sparkline ECharts**; **24 kartu per halaman (4×6)**; info halaman menyertakan kuesioner aktif; instance lama selalu di-`dispose`.
  - **Peta Halmahera Utara** (dashboard): Leaflet + OSM; **`KOORDINAT_KEC` = 23 kecamatan**; `circleMarker` berwarna sesuai posisi harga (skala 5 warna hijau→merah), tooltip permanen, popup berisi harga/satuan/bulan/status (+kuesioner); legenda gradien; `fitBounds` ke titik bersada.
  - **Kelengkapan Data** (dashboard): tab + pencarian + **25 baris per halaman**; tombol unduh tautan langsung ke `api/download.php`. Semua nilai di-escape (`esc`) — anti XSS.
- **`upload.js`** — dropzone + preview, mengisi **6 select peran (termasuk kuesionerCol)** + chip kolom bulan + tahun default, membangun body JSON mapping (`kuesioner_col` ikut dikirim), validasi sebelum analisis, panel hasil.
- **`dashboard.js`** — statistik + riwayat; membaca flag **`BOLEH_KELOLA`** (dari `dashboard.php`): **false untuk viewer** → tombol Hapus tidak dirender dan jumlah kolom tabel menyesuaikan (`NCOL_TABEL`); tombol "Coba Data Contoh" aman untuk viewer karena elemennya tidak ada (opsional chaining).
- **`css/style.css`** — tambahan Tailwind: `.dd-panel` + **`.dd-panel-left`** (panel dropdown Kuesioner rata kiri), `.dd-name/.dd-item`, `.tab-btn`, `.chart-card`, `.page-btn`, `.kec-label`, `.map-legend` (font DM Sans), skeleton/spinner/chip/dragover, `.font-hero` (DM Sans).

---

## 5. Data Contoh — `app/sample/` + `generate_sample.py`

`contoh_harga.csv` (format **baris**, 165 baris) & `contoh_harga_lebar.xlsx` (format **lebar**, kolom Mei/Juni/Juli) — keduanya menghasilkan analisis **identik**: **11 komoditas × 5 kecamatan × 3 bulan (Mei–Juli 2026)**. Harga tiap kecamatan dibuat sedikit berbeda (Galela/Kao lebih mahal).

| Komoditas (kualitas) | Pola di Tobelo | Ekspektasi |
|---|---|---|
| Beras Premium (Premium) | **konstan Rp 30.000** | **TIDUR** — contoh kasus pengguna |
| Gula Pasir (Standar) | **konstan Rp 30.000** | **TIDUR** — contoh kasus pengguna |
| Garam Beryodium (Standar) | **konstan Rp 30.000** | **TIDUR** — contoh kasus pengguna |
| Terigu Segitiga Biru (Standar) | konstan (harga kecamatan masing-masing) | **TIDUR** |
| Beras Medium, Garam Krosok, Minyak Goreng (Kemasan/Curah), Telur Ayam Ras, Daging Ayam Ras, Cabai Rawit Merah | fluktuasi / tren naik | Harga bergerak |

Hasil uji aktual: `total_komoditas=11, total_tidur=4, total_bergerak=7, periode 2026-05-01 s.d. 2026-07-31` — mode baris & lebar **identik** ✅. Uji tambahan: file tanpa kolom kualitas/kecamatan (default "Standar"/"Tobelo") dan kolom waktu berisi nama bulan saja ✅. File SHPED asli (kolom `Kuesioner` berisi HKD-1, HKD-2.1, …) mendukung dropdown filter kuesioner bila **Kolom Kuesioner** dipilih saat mapping ✅.

---

## 6. Keamanan Ringkas
- Password bcrypt + **role check** (`is_admin`/`is_viewer`) + `session_regenerate_id`; sesi httponly/SameSite.
- **Role viewer = read-only di dua lapis**: UI (tombol unggah/edit/hapus disembunyikan) **dan** server (`upload_file.php`, `analyze.php`, `sample.php` menolak non-admin 401; `uploads.php` POST hapus menolak viewer 403; `upload.php` dialihkan ke dashboard).
- **CSRF token** di semua endpoint POST (form login & header fetch).
- Prepared statement PDO di semua query (anti SQL injection).
- Escape output (`e()` di PHP, `esc()` di JS) — anti XSS.
- Validasi ekstensi/ukuran upload, nama file acak, FastAPI hanya membaca path di folder uploads (`_safe_path`).
- `download.php` & `/export` bersifat publik namun **read-only** dan hanya menerima `upload_id` yang berstatus `done`.

## 7. Kustomisasi Cepat
| Ingin mengubah… | Edit… |
|---|---|
| Koordinat kecamatan di peta | `landing.js → KOORDINAT_KEC` |
| Default kecamatan | `landing.js → DEFAULT_KEC` |
| Jumlah grafik per halaman (default 24 = 4×6) | `landing.js → PER_HAL_GRAFIK` |
| Skala warna harga peta | `landing.js → WARNA_SKALA` + `.map-legend .bar` di `style.css` |
| Aturan data tidur / nilai default kolom | `analyzer.py → _analyze_series / default_ident` |
| Warna tema / **font (DM Sans)** | `includes/header.php → tailwind.config` |
| Tampilan bagian tabel/grafik/peta/kelengkapan | `includes/section_*.php` (dipakai dashboard.php) |
| Hak akses viewer | `includes/auth.php`, `dashboard.php`, endpoint `api/*.php` |
| Durasi maks / format file | `config/config.php` |
| Data contoh lain | `python-api/generate_sample.py` lalu jalankan ulang |

---

## 8. Perubahan Versi Produksi (Docker/Dokploy)

Versi produksi menambahkan lapisan di atas arsitektur di atas tanpa mengubah alur data:

- **Konfigurasi 12-factor**: `config/config.php` (PHP) dan `python-api/config.py` (Python) membaca env — `DB_*`, `API_BASE`, `UPLOAD_DIR`, `APP_DEBUG`, `APP_FORCE_HTTPS`, `APP_BEHIND_PROXY`, `APP_TZ` (default `Asia/Jayapura`/WIT), `CORS_ORIGINS`. Tanpa env → perilaku identik setup XAMPP.
- **Container** (`deploy/`): `web` (php:8.3-apache + pdo_mysql/curl, AllowOverride All, entrypoint chown uploads), `api` (python:3.12-slim + uvicorn, internal saja), `db` (mysql:8.4 + init skema otomatis via `docker-entrypoint-initdb.d`). `uploads/` dibagi via volume `uploads_data`.
- **Resolve file lintas kontainer**: `api/analyze.php` & `api/sample.php` kirim `file_name` (basename) → `_safe_path()` FastAPI me-resolve di dalam `UPLOAD_DIR`-nya sendiri; validasi realpath legacy tetap jalan di mode lokal.
- **Keamanan tambahan**: throttle login (`login_attempts`), redaksi error via `err_detail()` di balik `APP_DEBUG`, cookie `Secure` via `APP_FORCE_HTTPS`, security headers `.htaccess` root, CORS default-deny, install.php tanpa kredensial default.
- **Pengujian**: `python-api/tests/` (4 pytest golden) + `scripts/smoke.sh` (end-to-end login → sample → landing → unduh).
