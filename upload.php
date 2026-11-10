<?php
/**
 * upload.php — Halaman "Upload & Konfigurasi":
 *   1. Area drag & drop file CSV/Excel.
 *   2. Preview isi file.
 *   3. Form Mapping Kolom format lebar (kolom = bulan) + identitas & tahun.
 *   4. Tombol analisis → api/upload_file.php + api/analyze.php.
 */

require_once __DIR__ . '/includes/auth.php';
require_admin();

$area       = 'admin';
$active     = 'upload';
$page_title = 'Upload & Konfigurasi';
include __DIR__ . '/includes/header.php';
?>

<main class="mx-auto max-w-4xl px-4 py-8">

  <div class="mb-6">
    <h1 class="text-2xl font-extrabold tracking-tight">Upload &amp; Konfigurasi</h1>
    <p class="mt-1 text-sm text-slate-500">Unggah file harga komoditas lalu petakan kolomnya. Sistem akan menganalisis otomatis.</p>
  </div>

  <!-- Status layanan analisis Python (dicek otomatis saat halaman dibuka) -->
  <div id="apiStatus"
       class="mb-6 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
    <span class="spinner inline-block !h-4 !w-4 !border-2" aria-hidden="true"></span>
    <span>Memeriksa layanan analisis Python…</span>
  </div>

  <!-- ===== 1. Upload File ===== -->
  <section class="mb-6 rounded-2xl bg-white p-6 shadow-card">
    <h2 class="mb-4 font-bold">1 · Upload File <span class="text-xs font-medium text-slate-400">(CSV atau Excel .xlsx · maks 10 MB)</span></h2>

    <div id="dropZone"
         class="cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center transition hover:border-primary-400 hover:bg-primary-50/40">
      <div class="text-4xl text-slate-300"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></div>
      <p class="mt-3 text-sm font-medium text-slate-600">Tarik &amp; lepas file ke sini, atau <span class="text-primary-600 underline">klik untuk memilih</span></p>
      <p class="mt-1 text-xs text-slate-400">Format: .csv / .xlsx — header kolom harus ada di baris pertama</p>
      <input id="fileInput" type="file" accept=".csv,.xlsx" class="hidden">
    </div>

    <!-- Chip file terpilih -->
    <div id="fileChip" class="mt-4 hidden items-center justify-between rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
      <div class="flex items-center gap-3 overflow-hidden">
        <span class="text-xl text-slate-500" id="chipIcon"><i class="fa-solid fa-file" aria-hidden="true"></i></span>
        <div class="min-w-0">
          <p id="chipName" class="truncate text-sm font-semibold"></p>
          <p id="chipSize" class="text-xs text-slate-400"></p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span id="chipStatus" class="text-xs font-medium text-slate-400"></span>
        <button id="chipRemove" type="button" title="Hapus file"
                class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>
    </div>

    <!-- Preview 5 baris pertama -->
    <div id="previewWrap" class="mt-4 hidden overflow-x-auto rounded-xl ring-1 ring-slate-200">
      <table class="w-full text-left text-xs">
        <thead id="tblPreviewHead" class="bg-slate-50 text-slate-500"></thead>
        <tbody id="tblPreviewBody" class="divide-y divide-slate-100"></tbody>
      </table>
    </div>
  </section>

  <!-- ===== 2. Mapping Kolom ===== -->
  <section id="cardMapping" class="mb-6 hidden rounded-2xl bg-white p-6 shadow-card">
    <h2 class="mb-1 font-bold">2 · Mapping Kolom</h2>
    <p class="mb-5 text-xs text-slate-400">Tebakan otomatis sudah dipilih — silakan sesuaikan bila kurang tepat.</p>

    <!-- Format data: lebar (satu-satunya format — setiap kolom bulan berisi harga satu bulan) -->
    <div class="mb-5 flex items-center gap-2 rounded-xl bg-primary-50 px-4 py-3 text-sm text-primary-700">
      <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
      <span><b>Format Lebar</b> — setiap kolom berisi harga satu bulan (Mei, Juni, …). Pilih kolom bulan di bawah, lalu isi tahunnya.</span>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <label for="komoditasCol" class="mb-2 block text-sm font-semibold text-slate-700">Kolom Komoditas <span class="text-rose-500">*</span></label>
        <select id="komoditasCol" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"></select>
      </div>
      <div>
        <label for="kualitasCol" class="mb-2 block text-sm font-semibold text-slate-700">Kolom Kualitas <span class="text-xs font-normal text-slate-400">(opsional — kosong = “Standar”)</span></label>
        <select id="kualitasCol" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"></select>
      </div>
      <div>
        <label for="kecamatanCol" class="mb-2 block text-sm font-semibold text-slate-700">Kolom Kecamatan <span class="text-xs font-normal text-slate-400">(opsional — kosong = “Tobelo”)</span></label>
        <select id="kecamatanCol" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"></select>
      </div>
      <div>
        <label for="satuanCol" class="mb-2 block text-sm font-semibold text-slate-700">Kolom Satuan <span class="text-xs font-normal text-slate-400">(opsional — kosong = “/kg”)</span></label>
        <select id="satuanCol" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"></select>
      </div>
      <div>
        <label for="kuesionerCol" class="mb-2 block text-sm font-semibold text-slate-700">Kolom Kuesioner <span class="text-xs font-normal text-slate-400">(opsional — kode kuesioner, mis. HKD-1 / HKD-2.1, untuk dropdown filter)</span></label>
        <select id="kuesionerCol" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"></select>
      </div>
    </div>

    <!-- Kolom bulan (format lebar) -->
    <div id="wrapWide">
      <label class="mb-2 block text-sm font-semibold text-slate-700">Kolom Bulan <span class="text-rose-500">*</span> <span class="text-xs font-normal text-slate-400">(pilih kolom yang berisi harga bulanan)</span></label>
      <div id="monthCols" class="mb-4 flex flex-wrap gap-2"></div>
      <div>
        <label for="tahunWide" class="mb-2 block text-sm font-semibold text-slate-700">Tahun untuk Kolom Bulan <span class="text-rose-500">*</span></label>
        <input id="tahunWide" type="number" min="2000" max="2100"
               class="w-40 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
      </div>
    </div>

    <div id="alertBox" class="mt-4 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>

    <button id="btnAnalyze" type="button" disabled
            class="mt-5 w-full rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-slate-300 md:w-auto md:px-8">
      <i class="fa-solid fa-magnifying-glass-chart mr-2" aria-hidden="true"></i>Mulai Analisis
    </button>

    <!-- Overlay saat menganalisis -->
    <div id="analyzeOverlay" class="hidden mt-5 items-center gap-3 rounded-xl bg-primary-50 px-4 py-3 text-sm text-primary-700">
      <span class="spinner inline-block !h-5 !w-5 !border-2"></span>
      <span id="overlayText">Menganalisis data… <span class="text-primary-500/70">(membaca file <i class="fa-solid fa-angle-right mx-0.5" aria-hidden="true"></i> agregasi bulanan <i class="fa-solid fa-angle-right mx-0.5" aria-hidden="true"></i> deteksi data tidur <i class="fa-solid fa-angle-right mx-0.5" aria-hidden="true"></i> simpan database)</span></span>
    </div>
  </section>

  <!-- ===== 3. Hasil ===== -->
  <section id="resultPanel" class="hidden rounded-2xl bg-white p-6 shadow-card">
    <div class="mb-4 flex items-center gap-2">
      <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-100 text-emerald-600"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
      <h2 class="font-bold">Analisis Selesai</h2>
    </div>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
      <div class="rounded-xl bg-slate-50 p-4 text-center">
        <p id="resKomoditas" class="text-2xl font-extrabold tnum">—</p>
        <p class="text-xs text-slate-400">Komoditas</p>
      </div>
      <div class="rounded-xl bg-rose-50 p-4 text-center">
        <p id="resTidur" class="text-2xl font-extrabold tnum text-rose-600">—</p>
        <p class="text-xs text-slate-400">Data Tidur</p>
      </div>
      <div class="rounded-xl bg-emerald-50 p-4 text-center">
        <p id="resBergerak" class="text-2xl font-extrabold tnum text-emerald-600">—</p>
        <p class="text-xs text-slate-400">Harga Bergerak</p>
      </div>
      <div class="rounded-xl bg-blue-50 p-4 text-center">
        <p id="resPeriode" class="text-sm font-extrabold leading-tight">—</p>
        <p class="text-xs text-slate-400">Periode dari file</p>
      </div>
    </div>
    <div class="mt-5 flex flex-wrap gap-3">
      <a href="index.php" target="_blank"
         class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700"><i class="fa-solid fa-arrow-up-right-from-square mr-2 text-xs" aria-hidden="true"></i>Lihat Halaman Publik</a>
      <button id="btnReset" type="button"
              class="rounded-xl bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200">Analisis File Lain</button>
    </div>
  </section>
</main>

<?php
$scripts = ['assets/js/upload.js'];
include __DIR__ . '/includes/footer.php';
?>
