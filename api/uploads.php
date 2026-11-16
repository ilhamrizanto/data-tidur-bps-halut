<?php
/**
 * api/uploads.php — Data untuk dashboard admin:
 *   GET  → statistik ringkas + daftar 20 unggahan terakhir
 *          (admin DAN viewer — viewer hanya dapat melihat)
 *   POST → hapus unggahan {action:"delete", id:...} (beserta file fisiknya)
 *          (HANYA admin — viewer tidak dapat menghapus)
 */

require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Akses ditolak. Silakan login.'], 401);

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Statistik kartu dashboard
        $totalUpload = (int)$pdo->query('SELECT COUNT(*) AS c FROM uploads')->fetch()['c'];

        $terakhir = $pdo->query("SELECT total_komoditas, total_tidur, total_bergerak,
                                        periode_mulai, periode_selesai
                                 FROM uploads WHERE status='done' ORDER BY id DESC LIMIT 1")->fetch();

        json_out(['ok' => true, 'stats' => [
            'total_upload'  => $totalUpload,
            'komoditas_terakhir' => $terakhir ? (int)$terakhir['total_komoditas'] : null,
            'tidur_terakhir'  => $terakhir ? (int)$terakhir['total_tidur'] : null,
            'bergerak_terakhir' => $terakhir ? (int)$terakhir['total_bergerak'] : null,
            'periode'         => $terakhir ? [$terakhir['periode_mulai'], $terakhir['periode_selesai']] : null,
        ], 'uploads' => array_map(function ($r) {
            return [
                'id'              => (int)$r['id'],
                'original_name'   => $r['original_name'],
                'file_type'       => $r['file_type'],
                'total_baris'     => (int)$r['total_baris'],
                'total_komoditas' => (int)$r['total_komoditas'],
                'total_tidur'     => (int)$r['total_tidur'],
                'total_bergerak'  => (int)$r['total_bergerak'],
                'periode'         => [$r['periode_mulai'], $r['periode_selesai']],
                'status'          => $r['status'],
                'pesan'           => $r['pesan'],
                'created_at'      => $r['created_at'],
            ];
        }, $pdo->query('SELECT * FROM uploads ORDER BY id DESC LIMIT 20')->fetchAll())]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_admin()) {
            json_out(['ok' => false, 'error' => 'Hanya admin yang dapat menghapus unggahan. Akun viewer hanya dapat melihat.'], 403);
        }
        if (!csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            json_out(['ok' => false, 'error' => 'CSRF token tidak valid'], 403);
        }
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        if (($body['action'] ?? '') !== 'delete') {
            json_out(['ok' => false, 'error' => 'Action tidak dikenal'], 400);
        }
        $id = (int)($body['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT stored_name FROM uploads WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_out(['ok' => false, 'error' => 'Unggahan tidak ditemukan'], 404);

        // Hapus file fisik (basename mencegah path traversal)
        $file = UPLOAD_DIR . basename($row['stored_name']);
        if (is_file($file)) @unlink($file);

        // Hapus baris DB → detail ikut terhapus via ON DELETE CASCADE
        $pdo->prepare('DELETE FROM uploads WHERE id = ?')->execute([$id]);
        json_out(['ok' => true]);
    }

    json_out(['ok' => false, 'error' => 'Metode tidak valid'], 405);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => err_detail($e)], 500);
}
