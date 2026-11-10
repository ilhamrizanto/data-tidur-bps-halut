<?php
/**
 * includes/section_grafik.php — Grid Grafik ala PIHPS Nasional
 * (1 komoditas = 1 grafik · 4 kolom × 6 baris per halaman · berhalaman).
 * Dipakai oleh dashboard.php — bagian ini dipindah dari halaman publik agar
 * hanya ditampilkan di dashboard admin.
 */
?>

<section id="grafik" class="mb-10">
  <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
      <h2 class="text-xl font-extrabold tracking-tight">Grafik Harga Komoditas</h2>
      <p class="mt-1 text-sm text-slate-500">Satu komoditas satu grafik — klik kartu untuk membuka detail di Informasi Data SHPED. Geser kursor pada grafik untuk melihat harga.</p>
    </div>
    <p id="chartInfo" class="text-xs text-slate-400"></p>
  </div>

  <div id="chartGrid" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"></div>

  <!-- Halaman grafik (bisa diklik; pergantian halaman otomatis menggulir ke atas grid) -->
  <div id="chartPager" class="mt-6 flex flex-wrap items-center justify-center gap-1.5"></div>
</section>
