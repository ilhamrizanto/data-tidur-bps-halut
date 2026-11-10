<?php
/**
 * config/config.php — Konfigurasi utama aplikasi (sisi PHP 8.5 / XAMPP 8.5.x).
 * Ubah nilai di bawah ini bila setting komputer Anda berbeda.
 */

define('APP_NAME', 'Deteksi Data Tidur');
define('APP_TAGLINE', 'Analisis Harga Komoditas Pasar');

// ---------- Database MySQL (default XAMPP) ----------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'db_data_tidur');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP default: root tanpa password

// ---------- Alamat layanan analisis Python (FastAPI) ----------
define('API_BASE', 'http://127.0.0.1:8000');

// ---------- Pengaturan upload ----------
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_BYTES', 10 * 1024 * 1024);          // 10 MB
define('ALLOWED_EXT', ['csv', 'xlsx']);

// Zona waktu tampilan
date_default_timezone_set('Asia/Jakarta');
