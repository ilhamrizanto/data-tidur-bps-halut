<?php
/**
 * includes/section_peta.php — Peta Harga Kabupaten Halmahera Utara
 * (warna kecamatan mengikuti level harga + panel peringkat terendah–tertinggi).
 * Dipakai oleh dashboard.php — bagian ini dipindah dari halaman publik agar
 * hanya ditampilkan di dashboard admin.
 */
?>

<section id="peta" class="mb-10">
  <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
    <div>
      <h2 class="text-xl font-extrabold tracking-tight">Peta Harga — Kabupaten Halmahera Utara</h2>
      <p class="mt-1 text-sm text-slate-500">
        Warna tiap kecamatan mengikuti level harga:
        <span class="font-semibold text-emerald-600">hijau = terendah</span> hingga
        <span class="font-semibold text-rose-600">merah = tertinggi</span>.
        Klik penanda untuk melihat harga dan tanggal pembaruannya.
      </p>
    </div>
    <p id="petaInfo" class="text-xs text-slate-400"></p>
  </div>
  <div class="grid gap-4 lg:grid-cols-3">
    <!-- Peta -->
    <div class="overflow-hidden rounded-2xl bg-white p-2 shadow-card lg:col-span-2">
      <div id="map" class="h-[480px] w-full rounded-xl bg-slate-100">
        <div class="grid h-full place-items-center text-sm text-slate-400">Memuat peta…</div>
      </div>
    </div>

    <!-- Panel peringkat harga (ala PIHPS Nasional: terendah → tertinggi) -->
    <div class="rounded-2xl bg-white p-5 shadow-card">
      <div class="flex items-center justify-between gap-2">
        <h3 class="text-sm font-extrabold tracking-tight">Peringkat Harga Kecamatan</h3>
        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500" id="rankBulan">—</span>
      </div>
      <p class="mt-1 text-[11px] text-slate-400">Harga <b id="rankKomoditas">—</b> · terendah → tertinggi · diperbarui <span id="rankUpdate">—</span></p>
      <div class="map-legend !shadow-none !border !border-slate-100 mt-3">
        <div class="bar"></div>
        <div style="display:flex;justify-content:space-between;gap:12px">
          <span>Terendah</span>
          <span style="text-align:right">Tertinggi</span>
        </div>
      </div>
      <ol id="rankKecamatan" class="mt-3 max-h-[300px] space-y-1.5 overflow-y-auto pr-1">
        <li class="py-6 text-center text-xs text-slate-400">Memuat…</li>
      </ol>
    </div>
  </div>
  <p id="mapNote" class="mt-2 hidden text-xs text-slate-400"></p>
</section>
