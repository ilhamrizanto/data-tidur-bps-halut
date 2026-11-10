<?php
/**
 * api/sample.php — Tombol "Coba Data Contoh" di dashboard.
 * Menyalin contoh_harga.csv ke folder uploads, mencatatnya sebagai unggahan,
 * lalu menjalankan analisis lengkap dengan mapping standar:
 *   identitas = [komoditas, kualitas, kecamatan, satuan],
 *   nilai = harga, waktu = tanggal (Mei–Juli 2026, kecamatan Halmahera Utara).
 */

require_once __DIR__ . '/../includes/auth.php';

if (!is_admin())                           json_out(['ok' => false, 'error' => 'Akses ditolak'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Metode tidak valid'], 405);
if (!csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))
                                           json_out(['ok' => false, 'error' => 'CSRF token tidak valid'], 403);

$src = realpath(__DIR__ . '/../sample/contoh_harga.csv');
if (!$src) json_out(['ok' => false, 'error' => 'File contoh tidak ditemukan di app/sample/'], 404);

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
$stored = 'up_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_contoh.csv';
$dest   = UPLOAD_DIR . $stored;
if (!copy($src, $dest)) json_out(['ok' => false, 'error' => 'Gagal menyalin file contoh'], 500);

try {
    $stmt = db()->prepare('INSERT INTO uploads (original_name, stored_name, file_type, status) VALUES (?,?,?,?)');
    $stmt->execute(['contoh_harga.csv (contoh)', $stored, 'csv', 'processing']);
    $uploadId = (int)db()->lastInsertId();
} catch (Throwable $e) {
    @unlink($dest);
    json_out(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}

$payload = [
    'file_path' => $dest,
    'upload_id' => $uploadId,
    'mapping'   => [
        'mode'          => 'long',
        'komoditas_col' => 'komoditas',
        'kualitas_col'  => 'kualitas',
        'kecamatan_col' => 'kecamatan',
        'satuan_col'    => 'satuan',
        'value_col'     => 'harga',
        'time_col'      => 'tanggal',
        'tahun_default' => 2026,
    ],
];

$ch = curl_init(API_BASE . '/analyze');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 300,
]);
$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false) json_out(['ok' => false, 'error' => 'Layanan analisis tidak dapat dihubungi (' . $err . ')'], 502);

$data = json_decode($res, true);
if ($code !== 200 || empty($data['ok'])) {
    json_out(['ok' => false, 'error' => $data['error'] ?? 'Analisis contoh gagal'], 502);
}

json_out($data);
