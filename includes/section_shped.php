<?php
/**
 * includes/section_shped.php — Bagian "Informasi Data SHPED".
 * Dipakai oleh index.php (halaman publik) DAN dashboard.php (admin/viewer).
 *
 * Variabel yang dapat diatur SEBELUM include:
 *   $shped_tabel (bool, default false) → tampilkan tabel harga bulanan di
 *   bawah panel detail. Tabel ini ikut dipindah ke dashboard admin —
 *   halaman publik menampilkan dropdown + detail + grafik saja.
 *
 * Dropdown filter (kiri → kanan):
 *   1. KUESIONER  (kode kuesioner dari kolom "Kuesioner" file SHPED,
 *                  mis. HKD-1, HKD-2.1) — filter agar daftar komoditas pada
 *                  card/grid menyesuaikan kuesioner yang dipilih.
 *   2. Komoditas (+ daftar kualitas di bawah nama komoditas)
 *   3. Tahun
 *   4. Kecamatan
 *
 * Seluruh data dimuat oleh assets/js/landing.js dari api/data.php?action=landing.
 */
$shped_tabel = $shped_tabel ?? false;
?>

<section id="shped" class="mb-10 mt-10">
  <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="text-xl font-extrabold tracking-tight">Informasi Data SHPED</h2>
      <p class="mt-1 text-sm text-slate-500">Pilih kuesioner, komoditas, tahun, dan kecamatan untuk melihat detail harga bulanannya.</p>
    </div>

    <!-- Empat tombol dropdown: Kuesioner · Komoditas (+kualitas) · Tahun · Kecamatan -->
    <div class="flex flex-wrap items-center gap-2.5">
      <!-- Dropdown KUESIONER — berada di samping KIRI tombol komoditas -->
      <div class="relative" id="ddKuesioner">
        <button id="btnDdKuesioner" type="button"
                class="dd-btn inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-card ring-1 ring-slate-200 hover:ring-primary-300">
          <i class="fa-solid fa-clipboard-list text-violet-500" aria-hidden="true"></i>
          <span id="lblKuesioner">Kuesioner…</span>
          <i class="fa-solid fa-chevron-down text-[10px] text-slate-400" aria-hidden="true"></i>
        </button>
        <div id="panelKuesioner" class="dd-panel dd-panel-left hidden">
          <div id="listKuesioner" class="max-h-72 overflow-y-auto p-1.5"></div>
        </div>
      </div>

      <div class="relative" id="ddKomoditas">
        <button id="btnDdKomoditas" type="button"
                class="dd-btn inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-card ring-1 ring-slate-200 hover:ring-primary-300">
          <i class="fa-solid fa-basket-shopping text-primary-600" aria-hidden="true"></i>
          <span id="lblKomoditas">Komoditas…</span>
          <i class="fa-solid fa-chevron-down text-[10px] text-slate-400" aria-hidden="true"></i>
        </button>
        <div id="panelKomoditas" class="dd-panel hidden">
          <div class="border-b border-slate-100 p-2">
            <input id="cariKomoditas" type="text" placeholder="Cari komoditas…"
                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm outline-none focus:border-primary-400">
          </div>
          <div id="listKomoditas" class="max-h-72 overflow-y-auto p-1.5"></div>
        </div>
      </div>

      <div class="relative" id="ddTahun">
        <button id="btnDdTahun" type="button"
                class="dd-btn inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-card ring-1 ring-slate-200 hover:ring-primary-300">
          <i class="fa-solid fa-calendar text-amber-500" aria-hidden="true"></i>
          <span id="lblTahun">Tahun…</span>
          <i class="fa-solid fa-chevron-down text-[10px] text-slate-400" aria-hidden="true"></i>
        </button>
        <div id="panelTahun" class="dd-panel hidden">
          <div id="listTahun" class="max-h-72 overflow-y-auto p-1.5"></div>
        </div>
      </div>

      <div class="relative" id="ddKecamatan">
        <button id="btnDdKecamatan" type="button"
                class="dd-btn inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-card ring-1 ring-slate-200 hover:ring-primary-300">
          <i class="fa-solid fa-location-dot text-rose-500" aria-hidden="true"></i>
          <span id="lblKecamatan">Kecamatan…</span>
          <i class="fa-solid fa-chevron-down text-[10px] text-slate-400" aria-hidden="true"></i>
        </button>
        <div id="panelKecamatan" class="dd-panel hidden">
          <div id="listKecamatan" class="max-h-72 overflow-y-auto p-1.5"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Panel detail komoditas terpilih -->
  <div class="grid gap-5 lg:grid-cols-5">
    <div class="rounded-2xl bg-white p-6 shadow-card lg:col-span-2">
      <div class="flex flex-wrap items-center gap-2">
        <h3 id="dtlNama" class="text-lg font-extrabold">—</h3>
        <span id="dtlKualitas" class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-primary-600">—</span>
        <span id="dtlKuesioner" class="hidden rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-bold text-violet-600">—</span>
        <span id="dtlStatus" class="rounded-full px-2.5 py-0.5 text-xs font-bold">—</span>
      </div>
      <p id="dtlWilayah" class="mt-1 text-xs text-slate-400">—</p>

      <p class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-400">Harga terakhir</p>
      <p class="mt-1">
        <span id="dtlHarga" class="text-4xl font-extrabold tnum text-slate-800">—</span>
        <span id="dtlSatuan" class="text-sm text-slate-400">—</span>
      </p>
      <p id="dtlBulanAkhir" class="mt-1 text-xs text-slate-400">—</p>

      <div class="mt-5 grid grid-cols-3 gap-3 text-center">
        <div class="rounded-xl bg-slate-50 p-3">
          <p class="text-[11px] text-slate-400">Tertinggi</p>
          <p id="dtlMax" class="mt-0.5 text-sm font-bold tnum">—</p>
        </div>
        <div class="rounded-xl bg-slate-50 p-3">
          <p class="text-[11px] text-slate-400">Terendah</p>
          <p id="dtlMin" class="mt-0.5 text-sm font-bold tnum">—</p>
        </div>
        <div class="rounded-xl bg-slate-50 p-3">
          <p class="text-[11px] text-slate-400">Perubahan</p>
          <p id="dtlPerubahan" class="mt-0.5 text-sm font-bold tnum">—</p>
        </div>
      </div>
      <p id="dtlAlasan" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500">—</p>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-card lg:col-span-3">
      <div id="chartSHPED" class="h-[320px] w-full"></div>
    </div>
  </div>

<?php if ($shped_tabel): ?>
  <!-- Tabel harga bulanan komoditas terpilih (hanya di dashboard admin) -->
  <div class="mt-5 overflow-x-auto rounded-2xl bg-white shadow-card">
    <table class="w-full min-w-[560px] text-left text-sm">
      <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400">
        <tr>
          <th class="px-5 py-3 font-semibold">Bulan</th>
          <th class="px-4 py-3 font-semibold">Harga</th>
          <th class="px-4 py-3 font-semibold">Perubahan dari bulan sebelumnya</th>
          <th class="px-4 py-3 font-semibold">Keterangan</th>
        </tr>
      </thead>
      <tbody id="tbodyBulanan" class="divide-y divide-slate-100">
        <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">Memuat…</td></tr>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</section>
