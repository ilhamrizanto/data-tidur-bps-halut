<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';   // helper e() & handle_db_error()

$langkah = [];

// Aplikasi membutuhkan minimal PHP 8.1 (tipe kembalian `never`); diuji pada 8.3 & 8.5
$phpOk = PHP_VERSION_ID >= 80100;

try {
    $pdo = db();

    // 1. Jaminan tabel users (bila db_data_tidur.sql lupa diimpor tabel ini saja)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username   VARCHAR(50)  NOT NULL UNIQUE,
        password   VARCHAR(255) NOT NULL,
        nama       VARCHAR(100) DEFAULT '',
        role       ENUM('admin','viewer') NOT NULL DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $langkah[] = ['Tabel users siap (role: admin, viewer)', true];

    // 1b. Migrasi database lama: pastikan kolom role mendukung 'viewer'
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','viewer') NOT NULL DEFAULT 'admin'");

    // 1c. Migrasi produksi: tabel login_attempts (throttle login 5 gagal / 15 menit)
    $adaTabelLa = $pdo->query("SHOW TABLES LIKE 'login_attempts'")->fetch();
    if (!$adaTabelLa) {
        $pdo->exec("CREATE TABLE login_attempts (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address   VARCHAR(45)  NOT NULL,
            username     VARCHAR(50)  NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_la (ip_address, username, attempted_at)
        ) ENGINE=InnoDB");
        $langkah[] = ['Tabel login_attempts dibuat (throttle login produksi)', true];
    } else {
        $langkah[] = ['Tabel login_attempts sudah ada', true];
    }

    // 2. Seed akun admin — username & password dari ENV (produksi);
    //    password kosong = diacak & ditampilkan SEKALI di halaman ini.
    $adminUser    = env('INSTALL_ADMIN_USERNAME', 'admin');
    $adminPassEnv = env('INSTALL_ADMIN_PASSWORD');
    $buatAdmin    = false;
    $adminPassAcak = '';
    $jumlah = (int)$pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];
    if ($jumlah === 0) {
        $buatAdmin = true;
        $adminPass = $adminPassEnv !== null ? $adminPassEnv : bin2hex(random_bytes(8)); // 16 karakter acak
        if ($adminPassEnv === null) $adminPassAcak = $adminPass;
        $pdo->prepare('INSERT INTO users (username, password, nama, role) VALUES (?,?,?,?)')
            ->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), 'Administrator', 'admin']);
        $langkah[] = ["Akun admin dibuat → username: {$adminUser}" . ($adminPassEnv !== null ? ' (password dari ENV)' : ' (password acak, lihat di bawah)'), true];
    } else {
        $langkah[] = ["Akun sudah ada ($jumlah user). Melewati pembuatan akun.", true];
    }

    // 3. Seed akun viewer (bila belum ada) — hanya bisa melihat
    $viewerUser     = env('INSTALL_VIEWER_USERNAME', 'viewer');
    $viewerPassEnv  = env('INSTALL_VIEWER_PASSWORD');
    $buatViewer     = false;
    $viewerPassAcak = '';
    $adaViewer = (int)$pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'viewer'")->fetch()['c'];
    if ($adaViewer === 0) {
        $buatViewer = true;
        $viewerPass = $viewerPassEnv !== null ? $viewerPassEnv : bin2hex(random_bytes(8));
        if ($viewerPassEnv === null) $viewerPassAcak = $viewerPass;
        $pdo->prepare('INSERT INTO users (username, password, nama, role) VALUES (?,?,?,?)')
            ->execute([$viewerUser, password_hash($viewerPass, PASSWORD_DEFAULT), 'Pengguna (View Only)', 'viewer']);
        $langkah[] = ["Akun viewer dibuat → username: {$viewerUser}" . ($viewerPassEnv !== null ? ' (password dari ENV)' : ' (password acak, lihat di bawah)') . ' (hanya dapat melihat)', true];
    } else {
        $langkah[] = ['Akun viewer sudah ada. Melewati pembuatan akun viewer.', true];
    }

    // 4. Migrasi kolom kuesioner pada analisis_seri (bila tabel sudah ada
    //    dan kolomnya belum ada — dipakai dropdown filter kuesioner)
    $adaTabelSeri = $pdo->query("SHOW TABLES LIKE 'analisis_seri'")->fetch();
    if ($adaTabelSeri) {
        $adaKolom = $pdo->query("SHOW COLUMNS FROM analisis_seri LIKE 'kuesioner'")->fetch();
        if (!$adaKolom) {
            $pdo->exec("ALTER TABLE analisis_seri
                        ADD COLUMN kuesioner VARCHAR(50) DEFAULT NULL AFTER satuan,
                        ADD INDEX idx_a_kues (kuesioner)");
            $langkah[] = ['Kolom kuesioner ditambahkan ke tabel analisis_seri (fitur filter kuesioner)', true];
        } else {
            $langkah[] = ['Kolom kuesioner pada analisis_seri sudah ada', true];
        }
    }

    // 5. Folder uploads
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
        $langkah[] = ['Folder uploads/ dibuat', true];
    } else {
        $langkah[] = ['Folder uploads/ sudah ada', true];
    }

    $berhasil = true;
} catch (Throwable $e) {
    $berhasil = false;
    $pesanError = $e->getMessage();
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalasi · <?= APP_NAME ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { fontFamily: {
      sans: ['"DM Sans"', 'Segoe UI', 'system-ui', 'sans-serif']
    } } } };
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-slate-100 p-6 font-sans antialiased grid place-items-center">
  <div class="w-full max-w-lg rounded-2xl bg-white p-8 shadow-card">
    <h1 class="mb-1 text-xl font-extrabold">Instalasi <?= APP_NAME ?></h1>
    <p class="mb-6 text-sm text-slate-500">Pemeriksaan setup awal aplikasi.</p>

    <?php if ($berhasil): ?>
      <ul class="mb-6 space-y-2 text-sm">
        <?php if (!$phpOk): ?>
          <li class="flex items-start gap-2 rounded-xl bg-amber-50 px-4 py-2.5 text-amber-700">
            <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i><span>PHP <?= e(PHP_VERSION) ?> terdeteksi — aplikasi butuh minimal <b>PHP 8.1</b> (tipe kembalian <code>never</code>). Gunakan XAMPP 8.1+ atau PHP 8.3.</span>
          </li>
        <?php endif; ?>
        <?php foreach ($langkah as [$teks]): ?>
          <li class="flex items-start gap-2 rounded-xl bg-emerald-50 px-4 py-2.5 text-emerald-700">
            <i class="fa-solid fa-circle-check mt-0.5" aria-hidden="true"></i><span><?= e($teks) ?></span>
          </li>
        <?php endforeach; ?>
        <li class="flex items-start gap-2 rounded-xl bg-blue-50 px-4 py-2.5 text-blue-700">
          <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i><span>Versi PHP aktif: <b><?= e(PHP_VERSION) ?></b> · Target: PHP 8.1+ + Python 3.12+ untuk layanan analisis.</span>
        </li>
      </ul>

      <?php if ($buatAdmin && $adminPassAcak !== ''): ?>
        <div class="mb-3 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          <i class="fa-solid fa-key mt-0.5" aria-hidden="true"></i>
          <span>PASSWORD ADMIN (tampil <b>SEKALI</b> ini — catat sekarang!): <b><?= e($adminPassAcak) ?></b></span>
        </div>
      <?php endif; ?>
      <?php if ($buatViewer && $viewerPassAcak !== ''): ?>
        <div class="mb-3 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          <i class="fa-solid fa-key mt-0.5" aria-hidden="true"></i>
          <span>PASSWORD VIEWER (tampil <b>SEKALI</b> ini — catat sekarang!): <b><?= e($viewerPassAcak) ?></b></span>
        </div>
      <?php endif; ?>

      <div class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
        <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
        <span>Untuk keamanan, <b>hapus file install.php</b> ini setelah instalasi di server produksi.</span>
      </div>
      <div class="mt-6 flex gap-3">
        <a href="login.php" class="flex-1 rounded-xl bg-primary-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-primary-700">Login Admin</a>
        <a href="index.php" class="flex-1 rounded-xl bg-slate-100 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-200">Halaman Publik</a>
      </div>
    <?php else: ?>
      <div class="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700">
        <i class="fa-solid fa-circle-xmark mt-0.5" aria-hidden="true"></i>
        <span>Instalasi gagal: <?= e($pesanError ?? '') ?><br><br>
        Pastikan MySQL berjalan dan <code>database/db_data_tidur.sql</code> sudah diimpor.
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
