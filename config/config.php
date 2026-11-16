<?php
/**
 * config/config.php — Konfigurasi utama aplikasi.
 * Semua nilai bisa dioverride lewat environment variable (Docker/Dokploy).
 * Tanpa env → perilaku identik dengan setup XAMPP lokal.
 */

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

define('APP_NAME', 'BANDA');
define('APP_TAGLINE', 'Bangunin Data Tidur · Deteksi Harga Komoditas Pasar');

// ---------- Database MySQL ----------
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_NAME', env('DB_NAME', 'db_data_tidur'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// ---------- Alamat layanan analisis Python (FastAPI) ----------
define('API_BASE', env('API_BASE', 'http://127.0.0.1:8000'));

// ---------- Folder & pengaturan upload ----------
define('UPLOAD_DIR', env('UPLOAD_DIR', __DIR__ . '/../uploads/'));
define('UPLOAD_MAX_BYTES', 10 * 1024 * 1024);          // 10 MB
define('ALLOWED_EXT', ['csv', 'xlsx']);

// ---------- Mode runtime ----------
define('APP_DEBUG',        env('APP_DEBUG', '0') === '1');          // 1 = tampilkan detail error
define('APP_FORCE_HTTPS',  env('APP_FORCE_HTTPS', '0') === '1');    // 1 = cookie secure (di balik proxy TLS)
define('APP_BEHIND_PROXY', env('APP_BEHIND_PROXY', '0') === '1');   // 1 = percayai X-Forwarded-For

// Zona waktu produksi: WIT (Halmahera Utara, UTC+9)
date_default_timezone_set(env('APP_TZ', 'Asia/Jayapura'));
