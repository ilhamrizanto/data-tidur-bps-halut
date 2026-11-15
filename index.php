<?php

$page_title = 'Beranda';
include __DIR__ . '/includes/header.php';

$canPanel = is_admin() || is_viewer();

// ---------------------------------------------------------------------
// Daftar kuesioner SHPED (PDF) — dibaca OTOMATIS dari folder docs/kuesioner.
// Tambah/hapus file PDF di folder itu untuk mengubah daftar unduhan;
// penamaan file mengikuti pola: Kuesioner_HD-1_2026.pdf → label "HD-1 · 2026".
// ---------------------------------------------------------------------
$kuesionerPdf = [];
foreach (glob(__DIR__ . '/docs/kuesioner/*.pdf') ?: [] as $_f) {
    $_label = str_replace('_', ' ', basename($_f, '.pdf'));          // "Kuesioner HD-1 2026"
    $_kode  = preg_replace('/^Kuesioner\s+/', '', $_label);         // "HD-1 2026"
    $_tahun = '';
    if (preg_match('/\s(\d{4})$/', $_kode, $_m)) {                  // pisah tahun di akhir
        $_tahun = $_m[1];
        $_kode  = trim(substr($_kode, 0, -strlen($_m[1])));
    }
    $kuesionerPdf[] = [
        'href'  => 'docs/kuesioner/' . rawurlencode(basename($_f)),
        'kode'  => $_kode,
        'tahun' => $_tahun,
        'grup'  => strtoupper(explode('-', $_kode)[0]),              // HD | HKD
    ];
}
usort($kuesionerPdf, fn($a, $b) => strnatcasecmp($a['kode'], $b['kode']));

// Keterangan tiap kelompok kuesioner (urutan tampil: HD → HKD → lainnya)
$grupKuesMeta = [
    'HD'  => ['judul' => 'Kuesioner HD — Harga Perdesaan',
              'sub'   => 'Responden petani — harga komoditas pertanian & pengeluaran produksi pertanian.'],
    'HKD' => ['judul' => 'Kuesioner HKD — Harga Konsumen Perdesaan',
              'sub'   => 'Responden pedagang/penjual pasar — harga barang konsumsi perdesaan.'],
];
?>

<main class="mx-auto max-w-7xl px-4 py-8">

  <!-- ============ HERO SECTION ============ -->
  <section class="font-hero mb-10 overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-600 text-white shadow-card">
    <div class="grid items-stretch gap-8 md:grid-cols-2 md:gap-8">

      <!-- Kiri: teks -->
      <div class="px-6 py-10 md:py-14 md:pl-10 md:pr-0">
        <span class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
          Data harga komoditas pasar · Kabupaten Halmahera Utara
        </span>
        <h1 class="max-w-3xl text-3xl font-extrabold leading-tight tracking-tight md:text-4xl">
          Deteksi Data Tidur pada Harga Komoditas Pasar
        </h1>
        <p class="mt-3 max-w-2xl text-sm font-light leading-relaxed text-emerald-100 md:text-base">
          Platform informasi untuk memantau harga dan perkembangan berbagai komoditas pasar, seperti beras, cabai, bawang, dan kebutuhan pokok lainnya secara mudah dan cepat.
        </p>
        <div class="mt-6 flex flex-wrap items-center gap-3">
          <a href="#shped" class="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-emerald-700 shadow transition hover:bg-emerald-50">
            <i class="fa-solid fa-magnifying-glass-chart mr-2" aria-hidden="true"></i>Informasi Data SHPED
          </a>
          <a href="<?= $canPanel ? 'dashboard.php#kelengkapan' : 'login.php' ?>"
             class="rounded-xl bg-white/10 px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-white/30 backdrop-blur transition hover:bg-white/20">
            <i class="fa-solid fa-moon mr-2" aria-hidden="true"></i>Data Tidur
          </a>
          <span id="statusChip" class="rounded-full bg-black/20 px-3 py-1.5 text-xs text-emerald-100">
            <span class="skeleton inline-block h-3 w-56 rounded"></span>
          </span>
        </div>
      </div>

      <!-- Kanan: foto pasar -->
      <figure class="relative h-56 md:h-auto md:min-h-[420px]">
        <img src="assets/img/pasar-modern1.jpeg"
             alt="Pasar Modern Tobelo — bangunan putih arsitektur modern dengan aksen merah"
             class="absolute inset-0 h-full w-full object-cover"
             loading="eager">
      </figure>

    </div>
  </section>

  <!-- ============ 3 KARTU RINGKASAN (angka sesuai isi file Excel yang diunggah admin) ============ -->
  <section id="ringkasan" class="mb-10">
    <h2 class="mb-4 text-xl font-extrabold tracking-tight">Ringkasan</h2>

    <div class="grid gap-5 md:grid-cols-3">
      <!-- Kartu 1: Total Komoditas → grafik di dashboard (admin/viewer) atau detail SHPED (publik) -->
      <a href="<?= $canPanel ? 'dashboard.php#grafik' : '#shped' ?>"
         class="card-hover group block rounded-2xl bg-white p-6 shadow-card ring-1 ring-slate-100" aria-label="Lihat grafik komoditas">
        <div class="flex items-start justify-between">
          <div class="grid h-12 w-12 place-items-center rounded-2xl bg-blue-50 text-xl text-primary-600"><i class="fa-solid fa-basket-shopping" aria-hidden="true"></i></div>
          <span class="text-xs font-medium text-primary-600 opacity-0 transition group-hover:opacity-100"><?= $canPanel ? 'Buka grafik' : 'Lihat detail' ?> <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></span>
        </div>
        <p class="mt-4 text-sm font-medium text-slate-500">Total Komoditas</p>
        <p id="sumTotal" class="mt-1 text-4xl font-extrabold tnum text-slate-800">—</p>
        <p class="mt-2 text-xs text-slate-400">sesuai jumlah komoditas pada file Excel yang diunggah</p>
      </a>

      <!-- Kartu 2: Komoditas Data Tidur → daftar data tidur di dashboard (admin/viewer) -->
      <a href="<?= $canPanel ? 'dashboard.php#kelengkapan' : '#shped' ?>" data-tab="tidur"
         class="card-tab card-hover group block rounded-2xl bg-white p-6 shadow-card ring-1 ring-slate-100" aria-label="Lihat daftar data tidur">
        <div class="flex items-start justify-between">
          <div class="grid h-12 w-12 place-items-center rounded-2xl bg-rose-50 text-xl text-rose-500"><i class="fa-solid fa-moon" aria-hidden="true"></i></div>
          <span class="text-xs font-medium text-rose-500 opacity-0 transition group-hover:opacity-100"><?= $canPanel ? 'Lihat daftar' : 'Lihat detail' ?> <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></span>
        </div>
        <p class="mt-4 text-sm font-medium text-slate-500">Komoditas Data Tidur</p>
        <p id="sumTidur" class="mt-1 text-4xl font-extrabold tnum text-rose-600">—</p>
        <p class="mt-2 text-xs text-slate-400">harga konstan minimal 3 bulan berturut-turut</p>
      </a>

      <!-- Kartu 3: Kelengkapan Data → tabel kelengkapan di dashboard (admin/viewer) -->
      <a href="<?= $canPanel ? 'dashboard.php#kelengkapan' : '#shped' ?>" data-tab="semua"
         class="card-tab card-hover group block rounded-2xl bg-white p-6 shadow-card ring-1 ring-slate-100" aria-label="Lihat seluruh kelengkapan data">
        <div class="flex items-start justify-between">
          <div class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></div>
          <span class="text-xs font-medium text-emerald-600 opacity-0 transition group-hover:opacity-100"><?= $canPanel ? 'Lihat seluruh data' : 'Lihat detail' ?> <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></span>
        </div>
        <p class="mt-4 text-sm font-medium text-slate-500">Kelengkapan Data</p>
        <p id="sumLengkap" class="mt-1 text-4xl font-extrabold tnum text-emerald-600">—</p>
        <p class="mt-2 text-xs text-slate-400">
          <span id="breakLengkap" class="font-medium">—</span>
        </p>
      </a>
    </div>

    <!-- Keadaan kosong (belum ada data) -->
    <div id="emptyState" class="hidden rounded-2xl bg-white p-10 text-center shadow-card">
      <div class="text-5xl text-slate-300"><i class="fa-solid fa-database" aria-hidden="true"></i></div>
      <h3 class="mt-4 text-lg font-bold">Belum ada data harga</h3>
      <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
        Data akan muncul setelah admin mengunggah file Excel/CSV lewat dashboard.
        Halaman ini diperbarui otomatis setiap kali ada analisis baru.
      </p>
      <a href="login.php" class="mt-5 inline-block rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"><i class="fa-solid fa-user-shield mr-2" aria-hidden="true"></i>Login Admin</a>
    </div>
  </section>

  <!-- ============ INFORMASI DATA SHPED (filter kuesioner + komoditas + detail + grafik) ============ -->
  <?php $shped_tabel = false; include __DIR__ . '/includes/section_shped.php'; ?>

  <!-- ============ METADATA KEGIATAN STATISTIK SHPED (kartu pemicu + modal) ============ -->
  <button id="btnMetadata" type="button"
          class="card-hover group flex w-full flex-wrap items-center gap-4 rounded-2xl bg-white px-6 py-5 text-left shadow-card ring-1 ring-slate-100"
          aria-haspopup="dialog" aria-controls="modalMetadata">
    <span class="grid h-12 w-12 flex-none place-items-center rounded-2xl bg-violet-50 text-xl text-violet-600">
      <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
    </span>
    <span class="min-w-[240px] flex-1">
      <span class="block text-sm font-extrabold tracking-tight text-slate-800">Metadata Kegiatan Statistik — Survei Harga Perdesaan (SHPED)</span>
      <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">Klik untuk melihat metadata kegiatan: penyelenggara, tujuan, metode pengumpulan data, responden, hingga diseminasi — sesuai dokumen metadata resmi BPS.</span>
    </span>
    <span class="ml-auto inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-xs font-semibold text-white shadow transition group-hover:bg-violet-700">
      Lihat Metadata <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
    </span>
  </button>
</main>

<!-- ============ MODAL METADATA KEGIATAN STATISTIK SHPED (pop-up) ============ -->
<!-- Terbuka saat kartu "Metadata Kegiatan Statistik" di atas diklik; ditutup
     lewat tombol ✕, tombol Tutup, klik area gelap di luar panel, atau tombol Esc. -->
<div id="modalMetadata" class="fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true" aria-labelledby="mdJudul">
  
  <div id="modalMetadataLatar" class="md-backdrop absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>

  <div class="absolute inset-0 flex items-start justify-center overflow-y-auto p-4 md:items-center" id="modalMetadataArea">
    <div class="md-panel relative my-8 w-full max-w-3xl rounded-3xl bg-white shadow-2xl">
      <!-- Kepala modal -->
      <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
        <div>
          <h3 id="mdJudul" class="text-lg font-extrabold tracking-tight text-slate-800">Metadata Kegiatan Statistik</h3>
          <p class="mt-0.5 text-sm text-slate-500">Survei Harga Perdesaan (SHPED) · Badan Pusat Statistik (BPS)</p>
        </div>
        <button id="btnMetadataX" type="button"
                class="grid h-9 w-9 flex-none place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Tutup metadata">
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </div>

      <!-- Isi modal: tabel metadata + deskripsi singkat (dapat digulir) -->
      <div class="max-h-[62vh] overflow-y-auto px-6 py-5">
        <div class="overflow-x-auto rounded-2xl ring-1 ring-slate-200">
          <table class="w-full min-w-[560px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="w-56 px-4 py-3 font-semibold">Komponen</th>
                <th class="px-4 py-3 font-semibold">Uraian</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Nama Kegiatan</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Survei Harga Perdesaan (SHPED)</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Penyelenggara</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Badan Pusat Statistik (BPS)</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Tujuan Kegiatan</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Memperoleh data dan informasi mengenai perkembangan harga barang konsumsi masyarakat perdesaan serta harga komoditas dan pengeluaran produksi pertanian.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Jenis Kegiatan</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Survei</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Frekuensi Pelaksanaan</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Bulanan</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Cakupan Wilayah</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Wilayah perdesaan sesuai lokasi pencacahan yang ditetapkan BPS.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Metode Pengumpulan Data</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Wawancara langsung kepada responden menggunakan kuesioner.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Unit Observasi</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Pedagang/penjual di pasar untuk HKD dan petani untuk HD.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Responden</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Pedagang/penjual dan petani.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Variabel yang Dikumpulkan</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Harga barang konsumsi, harga komoditas pertanian, serta pengeluaran atau biaya produksi pertanian.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Instrumen Pengumpulan Data</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Kuesioner SHPED.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Periode Referensi</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Bulanan.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Pengolahan Data</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Pemeriksaan, validasi, dan pengolahan data hasil pencacahan.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Keluaran Kegiatan</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Data harga konsumen perdesaan, harga perdesaan, dan pengeluaran produksi pertanian.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Pemanfaatan Data</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Sebagai bahan pemantauan perkembangan harga, penyusunan indikator statistik harga, dan analisis kondisi ekonomi masyarakat perdesaan.</td>
              </tr>
              <tr class="even:bg-slate-50/60">
                <th scope="row" class="px-4 py-3 align-top font-bold text-slate-700">Diseminasi</th>
                <td class="px-4 py-3 align-top leading-relaxed text-slate-600">Hasil kegiatan digunakan untuk kebutuhan statistik BPS dan disampaikan kepada unit kerja terkait.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- UNDUH KUESIONER SHPED — tombol di bawah baris Diseminasi;
             klik tombol → pilih kuesioner mana yang ingin diunduh (HD, HKD-1,
             HKD-2.1, dan seterusnya). Daftar dibaca otomatis dari folder
             docs/kuesioner oleh PHP di bagian atas file ini. -->
        <div id="boxUnduhKues" class="mt-5 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-[240px] flex-1">
              <p class="text-sm font-extrabold tracking-tight text-slate-800">
                <i class="fa-solid fa-file-pdf mr-2 text-rose-500" aria-hidden="true"></i>Unduh Kuesioner SHPED (PDF)
              </p>
              <p class="mt-0.5 text-xs leading-relaxed text-slate-500">Instrumen pencacahan resmi — pilih kuesioner yang ingin diunduh: HD, HKD-1, HKD-2.1, dan seterusnya.</p>
            </div>
            <button id="btnUnduhKues" type="button"
                    class="inline-flex flex-none items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white shadow transition hover:bg-violet-700"
                    aria-expanded="false" aria-controls="panelUnduhKues">
              Pilih Kuesioner
              <i class="fa-solid fa-chevron-down text-[10px] transition-transform" aria-hidden="true"></i>
            </button>
          </div>

          <?php if ($kuesionerPdf): ?>
          <!-- Panel pilihan kuesioner (muncul saat tombol di atas diklik) -->
          <div id="panelUnduhKues" class="mt-4 hidden space-y-4 rounded-2xl bg-white p-4 ring-1 ring-slate-200">
            <?php
            $_urutan = array_merge(array_keys($grupKuesMeta), ['*']);
            foreach ($_urutan as $_g):
                $_items = array_values(array_filter($kuesionerPdf,
                    fn($k) => $_g === '*' ? !isset($grupKuesMeta[$k['grup']]) : $k['grup'] === $_g));
                if (!$_items) continue;
                $_meta = $grupKuesMeta[$_g] ?? ['judul' => 'Kuesioner lainnya', 'sub' => ''];
            ?>
            <div>
              <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($_meta['judul']) ?></p>
              <?php if ($_meta['sub']): ?><p class="text-[11px] text-slate-400"><?= htmlspecialchars($_meta['sub']) ?></p><?php endif; ?>
              <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <?php foreach ($_items as $k): ?>
                <a href="<?= $k['href'] ?>" download
                   class="group flex items-center gap-2.5 rounded-xl bg-white px-3.5 py-2.5 ring-1 ring-slate-200 transition hover:bg-violet-50 hover:ring-violet-300"
                   title="Unduh <?= htmlspecialchars($k['kode'] . ($k['tahun'] ? ' (' . $k['tahun'] . ')' : '')) ?>">
                  <i class="fa-solid fa-file-pdf text-base text-rose-500" aria-hidden="true"></i>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-bold text-slate-700">Kuesioner <?= htmlspecialchars($k['kode']) ?></span>
                    <?php if ($k['tahun']): ?><span class="block text-[10px] text-slate-400">Edisi <?= htmlspecialchars($k['tahun']) ?></span><?php endif; ?>
                  </span>
                  <i class="fa-solid fa-download text-[11px] text-violet-500 opacity-0 transition group-hover:opacity-100" aria-hidden="true"></i>
                </a>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-700 ring-1 ring-amber-200">
            Belum ada file kuesioner. Tempatkan file PDF di folder <b>docs/kuesioner/</b> (pola nama: Kuesioner_HD-1_2026.pdf).
          </p>
          <?php endif; ?>
        </div>

        <!-- Deskripsi singkat kegiatan (sesuai dokumen metadata) -->
        <h4 class="mt-6 text-xs font-extrabold uppercase tracking-wide text-slate-400">Deskripsi Singkat Kegiatan</h4>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
          Survei Harga Perdesaan (SHPED) merupakan kegiatan survei bulanan yang dilaksanakan untuk memperoleh
          data perkembangan harga barang dan komoditas di wilayah perdesaan. Kegiatan ini terdiri atas dua jenis
          pendataan, yaitu Harga Konsumen Perdesaan (HKD) dengan responden pedagang atau penjual di pasar untuk
          memperoleh informasi harga barang konsumsi, serta Harga Perdesaan (HD) dengan responden petani untuk
          memperoleh informasi harga komoditas pertanian dan pengeluaran produksi pertanian. Pengumpulan data
          dilakukan melalui wawancara langsung menggunakan kuesioner.
        </p>
        <p class="mt-4 text-xs italic text-slate-400">Metadata Kegiatan Statistik – SHPED</p>
      </div>

      <!-- Kaki modal -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4">
        <p class="text-xs text-slate-400">Sumber: Metadata Kegiatan Statistik Survei Harga Perdesaan (SHPED), BPS.</p>
        <button id="btnMetadataTutup" type="button"
                class="rounded-xl bg-slate-900 px-5 py-2 text-xs font-semibold text-white transition hover:bg-slate-700">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Buka/tutup modal metadata: klik kartu pemicu, tombol ✕ / Tutup,
     klik area gelap di luar panel, atau tekan Esc -->
<script>
  (function () {
    var modal = document.getElementById('modalMetadata');
    if (!modal) return;
    var buka  = function () { modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; };
    var tutup = function () { modal.classList.add('hidden'); document.body.style.overflow = ''; };
    document.getElementById('btnMetadata').addEventListener('click', buka);
    document.getElementById('btnMetadataX').addEventListener('click', tutup);
    document.getElementById('btnMetadataTutup').addEventListener('click', tutup);
    /* Klik di luar panel: wadah area menutupi seluruh layar di atas latar
       gelap, jadi handler dipasang di wadah — hanya menutup bila yang
       diklik adalah wadahnya sendiri (bukan panel isinya). */
    document.getElementById('modalMetadataArea').addEventListener('click', function (ev) {
      if (ev.target === ev.currentTarget) tutup();
    });

    /* Panel "Pilih Kuesioner" di dalam modal: klik tombol → muncul daftar
       kuesioner (HD, HKD-1, HKD-2.1, …) → klik salah satu untuk mengunduh
       PDF-nya. Panel ditutup dengan klik tombol lagi, klik di luar kotak,
       atau Esc — Esc pertama hanya menutup panel, Esc kedua menutup modal. */
    var btnKues   = document.getElementById('btnUnduhKues');
    var panelKues = document.getElementById('panelUnduhKues');
    var kuesTerbuka = function () {
      return !!(btnKues && panelKues) && !panelKues.classList.contains('hidden');
    };
    var setKues = function (tampil) {
      if (!btnKues || !panelKues) return;
      panelKues.classList.toggle('hidden', !tampil);
      btnKues.setAttribute('aria-expanded', tampil ? 'true' : 'false');
      var panah = btnKues.querySelector('.fa-chevron-down');
      if (panah) panah.style.transform = tampil ? 'rotate(180deg)' : '';
    };
    if (btnKues && panelKues) {
      btnKues.addEventListener('click', function (ev) {
        ev.stopPropagation();
        setKues(!kuesTerbuka());
      });
      document.addEventListener('click', function (ev) {
        if (kuesTerbuka() && !ev.target.closest('#boxUnduhKues')) setKues(false);
      });
    }

    /* Esc: bila panel kuesioner terbuka → tutup panelnya saja;
       selain itu → tutup modal metadata. */
    document.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Escape' || modal.classList.contains('hidden')) return;
      if (kuesTerbuka()) { setKues(false); return; }
      tutup();
    });
  })();
</script>

<!-- Library pihak ketiga (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<?php
$scripts = ['assets/js/landing.js'];
include __DIR__ . '/includes/footer.php';
?>
