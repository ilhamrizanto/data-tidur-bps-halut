<?php
/**
 * login.php — Halaman login ADMIN & VIEWER.
 * Role 'admin'  → akses penuh (unggah, analisis, hapus).
 * Role 'viewer' → hanya dapat MELIHAT semua tampilan (halaman depan +
 *                 dashboard admin), tidak dapat mengunggah/mengedit/menghapus.
 *
 * Anti-bruteforce: maksimal 5 login gagal per IP+username dalam 15 menit
 * (tabel login_attempts; dibersihkan otomatis setiap percobaan).
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Sesi kedaluwarsa. Silakan coba lagi.';
    } else {
        $ip       = client_ip();
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        try {
            $pdo = db();
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 15 MINUTE');
            $st = $pdo->prepare('SELECT COUNT(*) AS c FROM login_attempts
                                 WHERE ip_address = ? AND username = ?
                                   AND attempted_at >= NOW() - INTERVAL 15 MINUTE');
            $st->execute([$ip, $username]);
            if ((int)$st->fetch()['c'] >= 5) {
                $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.';
            } else {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && in_array($user['role'], ['admin', 'viewer'], true)
                        && password_verify($password, $user['password'])) {
                    $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ? AND username = ?')
                        ->execute([$ip, $username]);
                    session_regenerate_id(true);          // cegah session fixation
                    $_SESSION['admin_id']   = (int)$user['id'];
                    $_SESSION['admin_name'] = $user['nama'] ?: $user['username'];
                    $_SESSION['admin_role'] = $user['role'];   // 'admin' | 'viewer'
                    header('Location: dashboard.php');
                    exit;
                }
                $pdo->prepare('INSERT INTO login_attempts (ip_address, username) VALUES (?,?)')
                    ->execute([$ip, $username]);
                $error = 'Username atau password salah.';
                usleep(400000);
            }
        } catch (Throwable $e) {
            handle_db_error($e);
        }
    }
}
?>
<!doctype html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin · <?= APP_NAME ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { fontFamily: {
      sans: ['"DM Sans"', 'Segoe UI', 'system-ui', 'sans-serif']
    } } } };
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 via-blue-50 to-indigo-100 font-sans text-slate-800 antialiased grid place-items-center p-4">

  <div class="w-full max-w-md">
    <div class="rounded-2xl bg-white p-8 shadow-card">
      <div class="mb-6 text-center">
        <img src="assets/img/logo.png" alt="Logo BPS Kab Halut"
             class="mx-auto mb-4 h-14 w-14 rounded-2xl object-cover">
        <h1 class="text-xl font-extrabold tracking-tight">Login Admin / Viewer</h1>
        <p class="mt-1 text-sm text-slate-500"><?= APP_NAME ?> — <?= APP_TAGLINE ?></p>
      </div>

      <?php if ($error): ?>
        <div class="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
          <span><?= e($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" action="login.php" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div>
          <label for="username" class="mb-1 block text-sm font-medium text-slate-700">Username</label>
          <input id="username" name="username" type="text" required autofocus autocomplete="username"
                 placeholder="admin"
                 class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
        </div>
        <div>
          <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
          <input id="password" name="password" type="password" required autocomplete="current-password"
                 placeholder="••••••••"
                 class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
        </div>
        <button type="submit"
                class="w-full rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-black shadow transition hover:bg-primary-700 active:scale-[.99]">
          <i class="fa-solid fa-right-to-bracket mr-2" aria-hidden="true"></i>Masuk
        </button>
        <p class="mt-4 pt-5 text-center text-xs text-slate-400">
          <a href="index.php" class="hover:text-primary-600"><i class="fa-solid fa-arrow-left mr-1.5" aria-hidden="true"></i>Kembali ke halaman publik</a>
        </p>
      </form>
    </div>
  </div>
</body>
</html>
