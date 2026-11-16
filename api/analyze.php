<?php
/**
 * api/analyze.php — Jalankan analisis deteksi data tidur.
 * Menerima JSON {upload_id, mode, komoditas_col, kualitas_col?, kecamatan_col?,
 * satuan_col?, value_col, time_col, month_cols?, tahun_default?}
 * lalu meneruskannya ke FastAPI /analyze. FastAPI membaca file yang sudah
 * tersimpan, menganalisis dengan Pandas+NumPy, dan menulis hasilnya ke MySQL.
 */

require_once __DIR__ . '/../includes/auth.php';

if (!is_admin())                           json_out(['ok' => false, 'error' => 'Akses ditolak. Silakan login.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Metode tidak valid'], 405);
if (!csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))
                                           json_out(['ok' => false, 'error' => 'CSRF token tidak valid'], 403);

$body = json_decode(file_get_contents('php://input'), true) ?: [];

$uploadId = (int)($body['upload_id'] ?? 0);
// Format baris sudah dihapus dari UI — default kini 'wide'. Mode 'long' tetap
// diterima bila ada pemanggil programatik (mis. python-api/tests/).
$mode     = (($body['mode'] ?? 'wide') === 'long') ? 'long' : 'wide';
$komCol   = trim((string)($body['komoditas_col'] ?? ''));
$valueCol = trim((string)($body['value_col'] ?? ''));
$timeCol  = trim((string)($body['time_col'] ?? ''));
$monthCols = array_values(array_filter((array)($body['month_cols'] ?? []), 'is_string'));

if ($uploadId <= 0 || $komCol === '') {
    json_out(['ok' => false, 'error' => 'Mapping kolom belum lengkap (kolom komoditas wajib dipilih)'], 400);
}
if ($mode === 'long' && ($valueCol === '' || $timeCol === '')) {
    json_out(['ok' => false, 'error' => 'Format baris membutuhkan kolom harga dan kolom waktu/tanggal'], 400);
}
if ($mode === 'wide' && count($monthCols) === 0) {
    json_out(['ok' => false, 'error' => 'Format lebar membutuhkan minimal satu kolom bulan'], 400);
}

// Pastikan unggahan ada dan filenya masih ada
try {
    $stmt = db()->prepare('SELECT * FROM uploads WHERE id = ? LIMIT 1');
    $stmt->execute([$uploadId]);
    $row = $stmt->fetch();
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Database error: ' . err_detail($e)], 500);
}
if (!$row) json_out(['ok' => false, 'error' => 'Unggahan tidak ditemukan'], 404);

$path = realpath(UPLOAD_DIR . $row['stored_name']);
if (!$path || strpos($path, realpath(UPLOAD_DIR)) !== 0) {
    json_out(['ok' => false, 'error' => 'File fisik tidak ditemukan di server'], 404);
}

// Teruskan ke FastAPI (file dibaca lewat path — tanpa upload ulang, efisien).
// file_name = basename → di-resolve FastAPI di UPLOAD_DIR miliknya, sehingga
// aman lintas kontainer yang titik mount folder uploads-nya berbeda.
$payload = [
    'file_path' => $path,
    'file_name' => $row['stored_name'],
    'upload_id' => $uploadId,
    'mapping'   => [
        'mode'          => $mode,
        'komoditas_col' => $komCol,
        'kualitas_col'  => trim((string)($body['kualitas_col'] ?? '')) ?: null,
        'kecamatan_col' => trim((string)($body['kecamatan_col'] ?? '')) ?: null,
        'satuan_col'    => trim((string)($body['satuan_col'] ?? '')) ?: null,
        'kuesioner_col' => trim((string)($body['kuesioner_col'] ?? '')) ?: null,
        'value_col'     => $valueCol ?: null,
        'time_col'      => $timeCol ?: null,
        'month_cols'    => $monthCols,
        'tahun_default' => isset($body['tahun_default']) ? (int)$body['tahun_default'] : null,
    ],
    'cfg'       => $body['cfg'] ?? null,   // opsional: ambang deteksi kustom
];

$ch = curl_init(API_BASE . '/analyze');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Expect:'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 300,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false) {
    json_out(['ok' => false, 'error' =>
        'Layanan analisis tidak dapat dihubungi. Pastikan jalankan_api.bat sedang berjalan '
        . '(detail: ' . $err . ')'], 502);
}

$data = json_decode($res, true);
if ($code !== 200 || empty($data['ok'])) {
    $detail = $data['error'] ?? ($data['detail'] ?? null);
    if (!$detail) {
        $detail = 'respons tidak dikenal (HTTP ' . $code . '): "'
                . mb_substr(trim(preg_replace('/\s+/', ' ', (string)$res)), 0, 120) . '"';
    }
    json_out(['ok' => false, 'error' => $detail], $code >= 400 && $code < 600 ? $code : 500);
}

json_out($data);
