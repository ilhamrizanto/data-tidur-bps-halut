<?php
/**
 * api/data.php — Endpoint JSON PUBLIK untuk halaman landing (sisi user).
 *
 * GET ?action=landing            → data unggahan TERAKHIR yang selesai dianalisis
 * GET ?action=landing&tahun=2025 → data unggahan yang memuat tahun 2025
 *                                  (dipakai saat user memilih tahun di dropdown)
 *
 * Dalam SATU request mengembalikan:
 *   - ringkasan: angka kartu (komoditas, tidur, bergerak, periode)
 *   - seri     : komoditas × kualitas × kecamatan lengkap dengan titik harga/bulan
 *   - tahun_tersedia: DAFTAR TAHUN dari SEMUA unggahan yang selesai dianalisis
 *                     → sumber dropdown "Tahun" (mis. 2025 + 2026 sekaligus)
 * Dipakai: 3 kartu ringkasan, Informasi Data SHPED, grid grafik PIHPS,
 *          peta Halmahera Utara, dan tabel kelengkapan data.
 */

require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Database tidak terhubung. Pastikan MySQL aktif.'], 500);
}

$action = $_GET['action'] ?? 'landing';

/**
 * Daftar tahun yang tersedia dari SEMUA unggahan berstatus "done".
 * Tahun diambil dari data harga yang benar-benar tersimpan (kolom bulan pada
 * harga_bulanan), bukan dari nama file — jadi selalu akurat.
 * Untuk tiap tahun dipilih unggahan TERBARU yang memuatnya.
 */
function tahun_tersedia(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT YEAR(h.bulan) AS tahun, MAX(h.upload_id) AS upload_id
           FROM harga_bulanan h
           JOIN uploads u ON u.id = h.upload_id
          WHERE u.status = 'done'
          GROUP BY YEAR(h.bulan)
          ORDER BY tahun DESC"
    )->fetchAll();

    if (!$rows) {
        return [];
    }

    // Ambil meta unggahan pilihan untuk tiap tahun (jumlah tahun kecil, jadi 1 query saja)
    $st = $pdo->prepare(
        'SELECT id, original_name, periode_mulai, periode_selesai,
                total_komoditas, total_tidur, total_bergerak, created_at
           FROM uploads WHERE id = ?'
    );

    $hasil = [];
    foreach ($rows as $r) {
        $st->execute([(int)$r['upload_id']]);
        $u = $st->fetch();
        $hasil[] = [
            'tahun'            => (int)$r['tahun'],
            'upload_id'        => (int)$r['upload_id'],
            'nama_file'        => $u['original_name']   ?? '',
            'periode_mulai'    => $u['periode_mulai']   ?? null,
            'periode_selesai'  => $u['periode_selesai'] ?? null,
            'total_komoditas'  => (int)($u['total_komoditas'] ?? 0),
            'total_tidur'      => (int)($u['total_tidur'] ?? 0),
            'total_bergerak'   => (int)($u['total_bergerak'] ?? 0),
            'diperbarui'       => $u['created_at'] ?? null,
        ];
    }
    return $hasil;
}

if ($action === 'landing') {

    // ---- Pilih unggahan: terakhir (default) atau sesuai tahun diminta ----
    $tahunDiminta = trim((string)($_GET['tahun'] ?? ''));

    if ($tahunDiminta !== '') {
        if (!preg_match('/^\d{4}$/', $tahunDiminta)) {
            json_out(['ok' => false, 'error' => 'Parameter tahun harus 4 digit angka, contoh: ?action=landing&tahun=2025'], 400);
        }
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
        $st->execute([$tahunDiminta]);
        $pilih = $st->fetch();
        if (!$pilih) {
            json_out(['ok' => false, 'error' => "Tidak ada data harga untuk tahun {$tahunDiminta}. Unggah file tahun tersebut terlebih dahulu."], 404);
        }
        $st = $pdo->prepare('SELECT * FROM uploads WHERE id = ?');
        $st->execute([(int)$pilih['id']]);
        $up = $st->fetch();
    } else {
        // Ambil unggahan terbaru yang selesai dianalisis
        $up = $pdo->query("SELECT * FROM uploads WHERE status = 'done' ORDER BY id DESC LIMIT 1")->fetch();
    }

    if (!$up) {
        json_out(['ok' => true, 'data' => null]);   // belum ada data sama sekali
    }
    $uid = (int)$up['id'];

    // ---- Meta tiap seri (komoditas × kualitas × kecamatan) ----
    $seriMeta = [];
    $st = $pdo->prepare('SELECT * FROM analisis_seri WHERE upload_id = ? ORDER BY komoditas, kualitas, kecamatan');
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $r) {
        $key = $r['komoditas'] . '|' . $r['kualitas'] . '|' . $r['kecamatan'];
        $seriMeta[$key] = [
            'komoditas'        => $r['komoditas'],
            'kualitas'         => $r['kualitas'],
            'kecamatan'        => $r['kecamatan'],
            'satuan'           => $r['satuan'],
            'kuesioner'        => $r['kuesioner'] ?? null,   // kode kuesioner (HKD-1, HKD-2.1, …) — sumber dropdown filter
            'jumlah_data'      => (int)$r['jumlah_data'],
            'jumlah_bulan'     => (int)$r['jumlah_bulan'],
            'harga_awal'       => (float)$r['harga_awal'],
            'harga_akhir'      => (float)$r['harga_akhir'],
            'harga_min'        => (float)$r['harga_min'],
            'harga_max'        => (float)$r['harga_max'],
            'perubahan_persen' => (float)$r['perubahan_persen'],
            'is_tidur'         => (bool)$r['is_tidur'],
            'alasan_tidur'     => $r['alasan_tidur'],
            'bulan_mulai'      => $r['bulan_mulai'],
            'bulan_selesai'    => $r['bulan_selesai'],
            'titik'            => [],   // diisi di bawah: [[bulan, harga], ...]
        ];
    }

    // ---- Titik harga per bulan ----
    $st = $pdo->prepare('SELECT komoditas, kualitas, kecamatan, bulan, harga
                           FROM harga_bulanan WHERE upload_id = ? ORDER BY bulan');
    $st->execute([$uid]);
    while ($r = $st->fetch()) {
        $key = $r['komoditas'] . '|' . $r['kualitas'] . '|' . $r['kecamatan'];
        if (isset($seriMeta[$key])) {
            $seriMeta[$key]['titik'][] = [$r['bulan'], (float)$r['harga']];
        }
    }
    // array_values() agar key asosiatif komoditas|kualitas|kecamatan menjadi
    // array JSON biasa (bernomor 0,1,2,…). Catatan: dulu memakai pipe operator
    // PHP 8.5 ($seriMeta |> array_values(...)) — diganti agar bekerja di semua
    // PHP 8.x dan tidak ditandai error sintaks oleh editor (Intelephense).
    $seri = array_values($seriMeta);

    json_out(['ok' => true, 'data' => [
        'ringkasan' => [
            'upload_id'       => $uid,
            'nama_file'       => $up['original_name'],
            'total_baris'     => (int)$up['total_baris'],
            'total_seri'      => (int)$up['total_seri'],
            'total_komoditas' => (int)$up['total_komoditas'],   // sesuai isi file Excel
            'total_tidur'     => (int)$up['total_tidur'],
            'total_bergerak'  => (int)$up['total_bergerak'],
            'periode_mulai'   => $up['periode_mulai'],
            'periode_selesai' => $up['periode_selesai'],
            'diperbarui'      => $up['created_at'],
            'tahun_diminta'   => $tahunDiminta !== '' ? (int)$tahunDiminta : null,
        ],
        'seri' => $seri,

        // Dropdown "Tahun" memakai daftar ini — berisi tahun dari SEMUA
        // unggahan yang selesai, bukan hanya unggahan yang sedang dimuat.
        'tahun_tersedia' => tahun_tersedia($pdo),
    ]]);
}

json_out(['ok' => false, 'error' => 'Action tidak dikenal'], 400);
