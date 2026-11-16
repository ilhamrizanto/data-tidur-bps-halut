<?php
/**
 * includes/auth.php — Sesi, otentikasi admin, CSRF, dan helper umum.
 * Di-require oleh setiap halaman/endpoint.
 */

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => APP_FORCE_HTTPS,
    ]);
}

/* ------------------------- OTENTIKASI ------------------------- */

function is_admin(): bool
{
    return isset($_SESSION['admin_id']) && ($_SESSION['admin_role'] ?? '') === 'admin';
}

/** TRUE bila yang login adalah akun dengan role 'viewer' (hanya dapat melihat). */
function is_viewer(): bool
{
    return isset($_SESSION['admin_id']) && ($_SESSION['admin_role'] ?? '') === 'viewer';
}

/** TRUE bila ada pengguna yang sudah login (admin ATAU viewer). */
function is_logged_in(): bool
{
    return is_admin() || is_viewer();
}

/** Paksa halaman hanya untuk admin yang sudah login.
 *  Catatan: viewer yang membuka halaman admin-only (mis. upload.php)
 *  dialihkan balik ke dashboard, bukan ke halaman login. */
function require_admin(): void
{
    if (!is_admin()) {
        header('Location: ' . (is_viewer() ? 'dashboard.php' : 'login.php'));
        exit;
    }
}

/** Paksa halaman agar dapat dibuka oleh admin ATAU viewer (read-only).
 *  Dipakai dashboard.php yang kini memuat tabel, grafik, peta, dan
 *  kelengkapan data yang dipindah dari halaman publik. */
function require_panel(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/* ---------------------------- CSRF ---------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf']) && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

/* --------------------------- HELPERS --------------------------- */

/** Escape output HTML (anti-XSS). */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Kirim respons JSON lalu hentikan eksekusi.
 * Tipe kembalian `never` (PHP 8.1+) memberi tahu editor/analisis statis bahwa
 * fungsi ini TIDAK PERNAH kembali — sehingga variabel yang diisi di dalam blok
 * `try` dianggap selalu terdefinisi setelah `catch` memanggil json_out()
 * (menghilangkan warning "Possible undefined variable '$pdo'" di VS Code). */
function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** IP klien; hanya percayai X-Forwarded-For bila APP_BEHIND_PROXY aktif
 *  (deploy di balik proxy Dokploy/Traefik). */
function client_ip(): string
{
    if (APP_BEHIND_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Detail error HANYA saat APP_DEBUG; detail selalu dicatat ke error_log server. */
function err_detail(Throwable $e): string
{
    error_log('[data-tidur] ' . $e->getMessage());
    return APP_DEBUG ? $e->getMessage()
                     : 'Terjadi kesalahan internal. Hubungi administrator.';
}

/** Halaman error database yang ramah (untuk halaman web biasa). */
function handle_db_error(Throwable $e): never
{
    http_response_code(500);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Error Database</title><script src="https://cdn.tailwindcss.com"></script>'
       . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"></head>'
       . '<body class="min-h-screen bg-slate-100 p-6 flex items-center justify-center">'
       . '<div class="max-w-lg w-full bg-white rounded-2xl shadow-card p-8 text-center">'
       . '<div class="text-5xl mb-4 text-amber-500"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></div>'
       . '<h1 class="text-xl font-bold text-slate-800 mb-2">Koneksi database gagal</h1>'
       . '<p class="text-slate-600 text-sm mb-4">Pastikan <b>MySQL/MariaDB</b> sudah berjalan dan file '
       . '<code>database/db_data_tidur.sql</code> sudah diimpor lewat phpMyAdmin.</p>'
       . '<p class="text-xs text-slate-400 break-words">' . e(err_detail($e)) . '</p>'
       . '</div></body></html>';
    exit;
}
