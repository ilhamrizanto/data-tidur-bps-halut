<?php
/**
 * api/download.php — Unduhan data untuk HALAMAN PUBLIK (tanpa login).
 *
 * GET ?format=csv         → CSV dibuat langsung oleh PHP (selalu tersedia,
 *                           pemisah ';' + BOM UTF-8 agar rapi dibuka Excel).
 * GET ?format=xlsx        → diteruskan (proxy) ke FastAPI /export (pandas).
 * GET ?upload_id=123      → opsional; bila kosong dipakai unggahan terbaru.
 * GET ?tahun=2025         → opsional; dipakai unggahan terbaru yang memuat
 *                           tahun tersebut (diabaikan bila upload_id diisi).
 *
 * Isi unduhan: seluruh data harga bulanan + status (Data Tidur / Harga
 * Bergerak) — sesuai kartu "Kelengkapan Data" di halaman publik.
 */

require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Database tidak terhubung. Pastikan MySQL aktif.'], 500);
}

$format   = ($_GET['format'] ?? 'csv') === 'xlsx' ? 'xlsx' : 'csv';
$uploadId = (int)($_GET['upload_id'] ?? 0);
$tahun    = trim((string)($_GET['tahun'] ?? ''));

if ($uploadId <= 0 && preg_match('/^\d{4}$/', $tahun)) {
    // Unggahan terbaru yang benar-benar memiliki harga pada tahun itu
    $st = $pdo->prepare(
        "SELECT h.upload_id AS id
           FROM harga_bulanan h
           JOIN uploads u ON u.id = h.upload_id
          WHERE u.status = 'done' AND YEAR(h.bulan) = ?
          GROUP BY h.upload_id
          ORDER BY h.upload_id DESC
          LIMIT 1"
    );
    $st->execute([$tahun]);
    $p = $st->fetch();
    if ($p) $uploadId = (int)$p['id'];
}

if ($uploadId <= 0) {
    $up = $pdo->query("SELECT id FROM uploads WHERE status='done' ORDER BY id DESC LIMIT 1")->fetch();
    if (!$up) json_out(['ok' => false, 'error' => 'Belum ada data yang dapat diunduh'], 404);
    $uploadId = (int)$up['id'];
}

const BULAN_ID = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

/** '2026-05-01' → 'Mei 2026' */
function label_bulan(string $iso): string
{
    $t = strtotime($iso);
    return $t ? (BULAN_ID[(int)date('n', $t)] . ' ' . date('Y', $t)) : $iso;
}

if ($format === 'csv') {
    $st = $pdo->prepare(
        "SELECT h.komoditas, h.kualitas, h.kecamatan, h.satuan, h.bulan, h.harga,
                a.is_tidur
           FROM harga_bulanan h
           JOIN analisis_seri a
             ON a.upload_id = h.upload_id
            AND a.komoditas = h.komoditas
            AND a.kualitas  = h.kualitas
            AND a.kecamatan = h.kecamatan
          WHERE h.upload_id = ?
          ORDER BY h.komoditas, h.kualitas, h.kecamatan, h.bulan");
    $st->execute([$uploadId]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data-shped-' . $uploadId . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM UTF-8 (agar karakter tampil benar di Excel)
    fputcsv($out, ['Komoditas', 'Kualitas', 'Kecamatan', 'Satuan', 'Bulan', 'Harga', 'Status'], ';');
    while ($r = $st->fetch()) {
        fputcsv($out, [
            $r['komoditas'], $r['kualitas'], $r['kecamatan'], $r['satuan'],
            label_bulan($r['bulan']), $r['harga'],
            ((int)$r['is_tidur'] === 1) ? 'Data Tidur' : 'Harga Bergerak',
        ], ';');
    }
    fclose($out);
    exit;
}

// ---- format=xlsx → proxy ke layanan Python (pandas + openpyxl) ----
$ch = curl_init(API_BASE . '/export?upload_id=' . $uploadId . '&format=xlsx');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
]);
$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($res === false || $code !== 200) {
    json_out(['ok' => false,
              'error' => 'Unduhan Excel gagal — layanan analisis (FastAPI) tidak aktif. Gunakan unduhan CSV, atau jalankan: cd python-api && python -m uvicorn main:app --port 8000 (' . $err . ')'], 502);
}

header('Content-Type: ' . ($type ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
header('Content-Disposition: attachment; filename="data-shped-' . $uploadId . '.xlsx"');
header('Content-Length: ' . strlen($res));
echo $res;
exit;
