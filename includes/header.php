<?php
/**
 * includes/header.php — Kepala halaman + navigasi (dipakai semua halaman).
 * Variabel yang dapat diatur SEBELUM include:
 *   $page_title : judul tab browser
 *   $active     : item nav aktif ('dashboard'|'upload'|'')
 *   $area       : 'public' (halaman user) atau 'admin' (halaman admin)
 */

require_once __DIR__ . '/auth.php';
$area       = $area ?? 'public';
$active     = $active ?? '';
$page_title = $page_title ?? APP_NAME;
$isAdmin    = is_admin();
$isViewer   = is_viewer();          // role 'viewer' — hanya dapat melihat
$canPanel   = $isAdmin || $isViewer; // boleh membuka dashboard admin
?>
<!doctype html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($page_title) ?> · <?= APP_NAME ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <!-- Font Awesome 6 (seluruh ikon aplikasi) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: {
      fontFamily: {
        // Font situs: DM Sans (keseluruhan halaman) — tanpa fallback Inter/Kantumruy
        sans: ['"DM Sans"', 'Segoe UI', 'system-ui', 'sans-serif'],
        hero: ['"DM Sans"', 'Segoe UI', 'system-ui', 'sans-serif'],
      },
      colors: { primary: {50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',400:'#60a5fa',
                          500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',900:'#1e3a8a'} },
      boxShadow: { card: '0 8px 24px -10px rgb(15 23 42 / .15)' }
    } } };
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- DM Sans — font utama seluruh situs (mendukung bobot 100–1000) -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= is_file(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : '1' ?>">
</head>
<body class="bg-slate-100 text-slate-800 font-sans antialiased">

<nav class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
  <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
    <a href="<?= $area === 'admin' ? 'dashboard.php' : 'index.php' ?>"
       class="flex items-center gap-2.5 text-lg font-extrabold tracking-tight">
      <img src="assets/img/logo.png" alt="Logo BPS Kab. Halut"
           class="w-10 h-10">
      <div class="leading-tight">
          <div class="font-bold text-slate-900">Badan Pusat Statistik</div>
          <div class="text-[11px] text-slate-500 -mt-0.5">Kabupaten Halmahera Utara</div>
      </div>
    </a>

    <?php if ($area === 'admin'): ?>
      <div class="hidden items-center gap-1 text-sm font-medium md:flex">
        <a href="dashboard.php" class="rounded-lg px-3 py-2 <?= $active === 'dashboard' ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-100' ?>">Dashboard</a>
        <?php if ($isAdmin): ?><a href="upload.php"  class="rounded-lg px-3 py-2 <?= $active === 'upload' ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-100' ?>">Deteksi Data Tidur</a><?php endif; ?>
        <?php if ($isViewer): ?><span class="rounded-lg bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-700"><i class="fa-solid fa-eye mr-1.5 text-[10px]" aria-hidden="true"></i>Viewer</span><?php endif; ?>
        <a href="index.php" target="_blank" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Halaman Publik <i class="fa-solid fa-arrow-up-right-from-square ml-1 text-xs" aria-hidden="true"></i></a>
        <a href="logout.php" class="ml-2 rounded-xl bg-slate-900 px-4 py-2 text-white hover:bg-slate-700"><i class="fa-solid fa-right-from-bracket mr-1.5 text-xs" aria-hidden="true"></i>Keluar</a>
      </div>
      <button id="btnMenu" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" aria-label="Buka menu"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
    <?php else: ?>
      <div class="hidden items-center gap-1 text-sm font-medium md:flex">
        <a href="#ringkasan" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Ringkasan</a>
        <a href="#shped"     class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100">Informasi SHPED</a>
        <!-- Tabel, grafik, peta, dan kelengkapan data kini hanya di dashboard admin -->
        <a href="<?= $canPanel ? 'dashboard.php' : 'login.php' ?>"
           class="ml-2 rounded-xl bg-slate-900 px-4 py-2 text-white hover:bg-slate-700">
          <i class="fa-solid <?= $canPanel ? 'fa-gauge-high' : 'fa-user-shield' ?> mr-1.5 text-xs" aria-hidden="true"></i><?= $canPanel ? 'Dashboard Admin' : 'Login Admin' ?></a>
      </div>
      <button id="btnMenu" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" aria-label="Buka menu"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
    <?php endif; ?>
  </div>

  <!-- Menu mobile -->
  <div id="mobileMenu" class="hidden border-t border-slate-200 bg-white px-4 py-3 md:hidden">
    <?php if ($area === 'admin'): ?>
      <a href="dashboard.php" class="block rounded-lg px-3 py-2 text-sm font-medium <?= $active === 'dashboard' ? 'bg-primary-50 text-primary-700' : 'text-slate-600' ?>">Dashboard</a>
      <?php if ($isAdmin): ?><a href="upload.php"  class="block rounded-lg px-3 py-2 text-sm font-medium <?= $active === 'upload' ? 'bg-primary-50 text-primary-700' : 'text-slate-600' ?>">Deteksi Data Tidur</a><?php endif; ?>
      <?php if ($isViewer): ?><span class="block rounded-lg px-3 py-2 text-xs font-semibold text-violet-700"><i class="fa-solid fa-eye mr-1.5 text-[10px]" aria-hidden="true"></i>Mode Viewer — hanya melihat</span><?php endif; ?>
      <a href="index.php"   class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600">Halaman Publik <i class="fa-solid fa-arrow-up-right-from-square ml-1 text-xs" aria-hidden="true"></i></a>
      <a href="logout.php"  class="mt-2 block rounded-xl bg-slate-900 px-4 py-2 text-center text-sm font-medium text-white"><i class="fa-solid fa-right-from-bracket mr-1.5 text-xs" aria-hidden="true"></i>Keluar</a>
    <?php else: ?>
      <a href="#ringkasan" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600">Ringkasan</a>
      <a href="#shped"     class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-600">Informasi SHPED</a>
      <a href="<?= $canPanel ? 'dashboard.php' : 'login.php' ?>" class="mt-2 block rounded-xl bg-slate-900 px-4 py-2 text-center text-sm font-medium text-white">
        <i class="fa-solid <?= $canPanel ? 'fa-gauge-high' : 'fa-user-shield' ?> mr-1.5 text-xs" aria-hidden="true"></i><?= $canPanel ? 'Dashboard Admin' : 'Login Admin' ?></a>
    <?php endif; ?>
  </div>
</nav>

<script>
  (function () {
    var b = document.getElementById('btnMenu'), m = document.getElementById('mobileMenu');
    if (b && m) b.addEventListener('click', function () { m.classList.toggle('hidden'); });
  })();
</script>
