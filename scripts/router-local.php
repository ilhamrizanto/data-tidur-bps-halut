<?php
/**
 * scripts/router-local.php — Router untuk server lokal `php -S` (non-Docker).
 * Blokir akses HTTP ke file sensitif / infrastruktur, layani sisanya normal.
 * Pemakaian: php -S 127.0.0.1:8090 scripts/router-local.php
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

// Sensitif / bukan bagian aplikasi web
if (preg_match('#^/(\.env|\.git|\.hermes|\.local-stack|deploy|python-api|scripts)(/|$)#', $uri)
    || $uri === '/.env' || $uri === '/.htaccess' || $uri === '/.gitignore' || $uri === '/.dockerignore') {
    http_response_code(403);
    exit('Forbidden');
}

// '/' dan file yang benar-benar ada (termasuk semua .php) → layani default server
if ($uri === '/' || file_exists(__DIR__ . '/..' . $uri)) {
    return false;
}

http_response_code(404);
exit('Not found');
