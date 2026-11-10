<?php
/**
 * includes/section_kelengkapan.php — Kelengkapan Data
 * (tabel seluruh data: data tidur + harga bergerak, tab, pencarian, halaman,
 * dan tombol unduh CSV/Excel).
 * Dipakai oleh dashboard.php — bagian ini dipindah dari halaman publik agar
 * hanya ditampilkan di dashboard admin.
 */
?>

<section id="kelengkapan" class="mb-4">
  <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
      <h2 class="text-xl font-extrabold tracking-tight">Kelengkapan Data</h2>
      <p class="mt-1 text-sm text-slate-500">Seluruh data yang ada — baik <b>data tidur</b> maupun <b>harga yang bergerak</b> — untuk semua komoditas dan kecamatan.</p>
    </div>
    <div class="flex gap-2">
      <a href="api/download.php?format=csv" id="unduhCsv"
         class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-emerald-700">
        <i class="fa-solid fa-file-csv" aria-hidden="true"></i>Unduh CSV
      </a>
      <a href="api/download.php?format=xlsx" id="unduhXlsx"
         class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-slate-700">
        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>Unduh Excel
      </a>
    </div>
  </div>

  <!-- Tab status + pencarian -->
  <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
    <div class="flex gap-1.5" id="tabKelengkapan">
      <button data-tab="semua"    class="tab-btn bg-primary-600 text-white">Semua</button>
      <button data-tab="tidur"    class="tab-btn bg-white text-slate-600"><i class="fa-solid fa-moon mr-1.5 text-[10px] text-rose-500" aria-hidden="true"></i>Data Tidur</button>
      <button data-tab="bergerak" class="tab-btn bg-white text-slate-600"><i class="fa-solid fa-arrow-trend-up mr-1.5 text-[10px] text-emerald-500" aria-hidden="true"></i>Harga Bergerak</button>
    </div>
    <div class="relative">
      <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
      <input id="cariKelengkapan" type="text" placeholder="Cari komoditas / kecamatan…"
             class="w-72 rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm outline-none focus:border-primary-400">
    </div>
  </div>

  <div class="overflow-x-auto rounded-2xl bg-white shadow-card">
    <table class="w-full min-w-[900px] text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400">
        <tr>
          <th class="px-5 py-3 font-semibold">Komoditas</th>
          <th class="px-4 py-3 font-semibold">Kualitas</th>
          <th class="px-4 py-3 font-semibold">Kecamatan</th>
          <th class="px-4 py-3 font-semibold">Harga Awal</th>
          <th class="px-4 py-3 font-semibold">Harga Akhir</th>
          <th class="px-4 py-3 font-semibold">Perubahan</th>
          <th class="px-4 py-3 font-semibold">Bulan</th>
          <th class="px-4 py-3 font-semibold">Status</th>
        </tr>
      </thead>
      <tbody id="tbodyKelengkapan" class="divide-y divide-slate-100">
        <tr><td colspan="8" class="px-5 py-8 text-center text-slate-400">Memuat…</td></tr>
      </tbody>
    </table>
  </div>
  <div id="tabelPager" class="mt-4 flex flex-wrap items-center justify-center gap-1.5"></div>
</section>
