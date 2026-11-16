<?php
/**
 * api/upload_file.php — Terima file (CSV/XLSX) dari admin:
 *   1. Validasi ekstensi + ukuran + CSRF.
 *   2. Simpan ke folder uploads/ dan buat baris di tabel uploads.
 *   3. Minta FastAPI "mengintip" isi file (/inspect) → daftar kolom +
 *      preview 5 baris + tebakan mapping otomatis.
 * Respons JSON dipakai JS untuk menampilkan form "Mapping Kolom".
 */

require_once __DIR__ . '/../includes/auth.php';

if (!is_admin())                       json_out(['ok' => false, 'error' => 'Akses ditolak. Silakan login.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Metode tidak valid'], 405);
if (!csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))
                                       json_out(['ok' => false, 'error' => 'CSRF token tidak valid'], 403);

$f = $_FILES['file'] ?? null;
if (!$f) json_out(['ok' => false, 'error' =>
    'File tidak diterima server. Kemungkinan ukurannya melebihi post_max_size di '
    . 'C:\\xampp\\php\\php.ini — naikkan post_max_size & upload_max_filesize lalu '
    . 'restart Apache.'], 400);
if ($f['error'] !== UPLOAD_ERR_OK) {
    // Pesan spesifik per kode error PHP agar tidak lagi muncul "gagal" tanpa sebab
    $pesan = [
        UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi upload_max_filesize di php.ini '
                                  . '(naikkan jadi mis. 20M di C:\\xampp\\php\\php.ini lalu restart Apache).',
        UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas formulir (10 MB).',
        UPLOAD_ERR_PARTIAL    => 'File terkirim sebagian saja — koneksi terputus. Coba lagi.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary PHP hilang (cek upload_tmp_dir di php.ini).',
        UPLOAD_ERR_CANT_WRITE => 'PHP gagal menulis file ke disk (cek izin folder temporary).',
        UPLOAD_ERR_EXTENSION  => 'Ekstensi PHP menghentikan unggahan (sering karena antivirus).',
    ];
    json_out(['ok' => false, 'error' => $pesan[$f['error']] ?? 'File tidak berhasil diunggah (kode ' . $f['error'] . ')'], 400);
}

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXT, true)) json_out(['ok' => false, 'error' => 'Format harus .csv atau .xlsx'], 400);
if ($f['size'] > UPLOAD_MAX_BYTES)      json_out(['ok' => false, 'error' => 'Ukuran file maksimal 10 MB'], 400);

// 1) Simpan file dengan nama acak (cegah path traversal & tabrakan nama)
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
$stored = 'up_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest   = UPLOAD_DIR . $stored;
if (!move_uploaded_file($f['tmp_name'], $dest)) {
    json_out(['ok' => false, 'error' => 'Gagal menyimpan file ke server'], 500);
}

// 2) Catat di database (status awal: processing)
try {
    $stmt = db()->prepare('INSERT INTO uploads (original_name, stored_name, file_type, status) VALUES (?,?,?,?)');
    $stmt->execute([$f['name'], $stored, $ext, 'processing']);
    $uploadId = (int)db()->lastInsertId();
} catch (Throwable $e) {
    @unlink($dest);
    json_out(['ok' => false, 'error' => 'Gagal mencatat di database: ' . err_detail($e)], 500);
}

// 3) Minta FastAPI memeriksa struktur file
$mime = $ext === 'csv'
    ? 'text/csv'
    : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

$ch = curl_init(API_BASE . '/inspect');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 5,
    // Tanpa header Expect: 100-continue — beberapa antivirus/proxy menanggapi
    // header ini dengan respons kosong (mis. 204) sehingga unggahan tampak gagal.
    CURLOPT_HTTPHEADER     => ['Expect:'],
    CURLOPT_POSTFIELDS     => ['file' => new CURLFile($dest, $mime, $f['name'])],
]);
$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false) {
    // Bersihkan baris yatim lalu laporkan
    try { db()->prepare('DELETE FROM uploads WHERE id = ?')->execute([$uploadId]); } catch (Throwable $e) {}
    @unlink($dest);
    json_out(['ok' => false, 'error' =>
        'Layanan analisis (FastAPI) tidak dapat dihubungi. Jalankan dulu jalankan_api.bat '
        . 'di folder proyek (detail: ' . $err . ')'], 502);
}

$data = json_decode($res, true);
if ($code !== 200 || empty($data['ok'])) {
    try { db()->prepare('DELETE FROM uploads WHERE id = ?')->execute([$uploadId]); } catch (Throwable $e) {}
    @unlink($dest);
    $detail = isset($data['error']) ? $data['error'] : ($data['detail'] ?? null);
    if (!$detail) {
        // Respons bukan JSON (mis. HTML error) — sertakan cuplikan agar terlihat
        $detail = 'respons tidak dikenal (HTTP ' . $code . '): "'
                . mb_substr(trim(preg_replace('/\s+/', ' ', (string)$res)), 0, 120) . '"';
    }
    json_out(['ok' => false, 'error' => 'Gagal memeriksa isi file — ' . $detail], 502);
}

// 4) Sukses → kirim info kolom + preview ke browser
json_out([
    'ok'         => true,
    'upload_id'  => $uploadId,
    'file'       => ['name' => $f['name'], 'size' => $f['size'], 'type' => $ext],
    'columns'    => $data['columns'],
    'preview'    => $data['preview'],
    'total_rows' => $data['total_rows'],
    'guess'      => $data['guess'],
]);
