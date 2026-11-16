<?php

require_once __DIR__ . '/includes/auth.php';
require_panel();                                   // admin ATAU viewer

$isAdmin    = is_admin();
$isViewer   = is_viewer();
$area       = 'admin';
$active     = 'dashboard';
$page_title = 'Dashboard Admin';
include __DIR__ . '/includes/header.php';
?>

<main class="mx-auto max-w-6xl px-4 py-8">

  <!-- Judul halaman -->
  <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">BANDA — Deteksi Data Tidur</h1>
      <p class="mt-1 text-sm text-slate-500">
        Pantau, unggah, dan analisis harga komoditas yang konstan tiga bulan atau lebih (data tidur).
        <?php if ($isViewer): ?>Anda masuk sebagai <b>viewer</b> — hanya dapat melihat.<?php endif; ?>
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <?php if ($isViewer): ?>
        <span class="inline-flex items-center gap-2 rounded-full bg-violet-100 px-3 py-1.5 text-xs font-semibold text-violet-700">
          <i class="fa-solid fa-eye" aria-hidden="true"></i>Mode Viewer (hanya melihat)
        </span>
      <?php endif; ?>
      <span id="statStatus" class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500">
        <span class="skeleton inline-block h-3 w-16 rounded"></span>
      </span>
      <?php if ($isAdmin): ?>
        <a href="upload.php" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-primary-700">
          <i class="fa-solid fa-plus mr-1.5 text-xs" aria-hidden="true"></i>Unggah Data
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Peringatan untuk viewer (read-only) -->
  <?php if ($isViewer): ?>
    <div class="mb-8 flex items-start gap-3 rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-800">
      <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
      <span>
        <b>Akun viewer.</b> Anda dapat melihat semua tampilan yang ada baik di bagian depan maupun di dashboard admin ini —
        termasuk tabel, grafik, peta, dan kelengkapan data. Namun Anda <b>tidak dapat mengunggah, mengedit, ataupun menghapus file</b>.
      </span>
    </div>
  <?php endif; ?>

  <!-- Panel statistik -->
  <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="rounded-2xl bg-white p-5 shadow-card">
      <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Unggahan</p>
      <p id="statUpload" class="mt-2 text-3xl font-extrabold tnum">—</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-card">
      <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Komoditas Terakhir</p>
      <p id="statKomoditas" class="mt-2 text-3xl font-extrabold tnum text-primary-600">—</p>
      <p class="mt-1 text-xs text-slate-400">sesuai jumlah pada file Excel</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-card">
      <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Data Tidur Ditemukan</p>
      <p id="statTidur" class="mt-2 text-3xl font-extrabold tnum text-rose-600">—</p>
      <p class="mt-1 text-xs text-slate-400">harga konstan ≥ 3 bulan</p>
    </div>
    <div class="rounded-2xl bg-white p-5 shadow-card">
      <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Periode Data</p>
      <p id="statPeriode" class="mt-2 text-sm font-extrabold leading-snug">—</p>
      <p class="mt-1 text-xs text-slate-400">diambil dari tanggal-bulan-tahun file</p>
    </div>
  </div>

  <!-- Riwayat unggahan (viewer dapat melihat; tombol hapus hanya untuk admin) -->
  <div class="overflow-hidden rounded-2xl bg-white shadow-card">
    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
      <h2 class="font-bold">Riwayat Unggahan</h2>
      <button id="btnRefresh" class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100"><i class="fa-solid fa-rotate-right mr-1.5" aria-hidden="true"></i>Muat ulang</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400">
          <tr>
            <th class="px-6 py-3 font-semibold">File</th>
            <th class="px-4 py-3 font-semibold">Periode</th>
            <th class="px-4 py-3 font-semibold">Komoditas</th>
            <th class="px-4 py-3 font-semibold">Data Tidur</th>
            <th class="px-4 py-3 font-semibold">Harga Bergerak</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold">Waktu</th>
            <?php if ($isAdmin): ?><th class="px-4 py-3"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody id="tblUploads" class="divide-y divide-slate-100">
          <tr><td colspan="<?= $isAdmin ? 8 : 7 ?>" class="px-6 py-8 text-center text-slate-400">Memuat data…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Notifikasi hasil analisis contoh -->
  <?php if ($isAdmin): ?>
  <div id="toast" class="pointer-events-none fixed bottom-6 right-6 z-50 hidden w-80 rounded-2xl bg-slate-900 p-4 text-sm text-white shadow-2xl"></div>
  <?php endif; ?>

  <!-- ===================== BAGIAN DATA (dipindah dari halaman publik) ===================== -->
  <div class="mb-6 mt-10 border-t border-slate-200 pt-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-xl font-extrabold tracking-tight">Data &amp; Analisis SHPED</h2>
        <p class="mt-1 text-sm text-slate-500">Tabel, grafik, peta, dan kelengkapan data — hanya ditampilkan di dashboard ini.</p>
      </div>
      <span id="statusChip" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-500">
        <span class="skeleton inline-block h-3 w-56 rounded"></span>
      </span>
    </div>
  </div>

  <!-- Keadaan kosong (belum ada data sama sekali) -->
  <div id="dashEmpty" class="mb-8 hidden rounded-2xl bg-white p-10 text-center shadow-card">
    <div class="text-5xl text-slate-300"><i class="fa-solid fa-database" aria-hidden="true"></i></div>
    <h3 class="mt-4 text-lg font-bold">Belum ada data harga</h3>
    <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
      <?php if ($isAdmin): ?>
        Unggah file Excel/CSV melalui halaman upload, atau klik "Coba Data Contoh" di atas.
      <?php else: ?>
        Data akan muncul setelah admin mengunggah file Excel/CSV.
      <?php endif; ?>
    </p>
  </div>

  <?php $shped_tabel = true; include __DIR__ . '/includes/section_shped.php';       // dropdown kuesioner/komoditas/tahun/kecamatan + detail + grafik + TABEL bulanan ?>
  <?php include __DIR__ . '/includes/section_grafik.php';        // grid grafik ?>
  <?php include __DIR__ . '/includes/section_peta.php';          // peta harga Halmahera Utara + peringkat kecamatan ?>
  <?php include __DIR__ . '/includes/section_kelengkapan.php';   // kelengkapan data + unduh CSV/Excel ?>
</main>

<!-- Library pihak ketiga (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<?php
// Flag read-only untuk dashboard.js: window.BOLEH_KELOLA=true hanya untuk admin.
// (Pakai properti window, BUKAN const global — const akan bentrok dengan
// deklarasi const BOLEH_KELOLA di dashboard.js dan menyebabkan SyntaxError.)
// Enforcement utama tetap di server: api/uploads.php menolak POST dari non-admin.
echo '<script>window.BOLEH_KELOLA = ' . ($isAdmin ? 'true' : 'false') . ';</script>';
$scripts = ['assets/js/dashboard.js', 'assets/js/landing.js'];
include __DIR__ . '/includes/footer.php';
?>
