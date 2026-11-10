/* =====================================================================
   landing.js — Logika data SHPED (halaman publik + dashboard admin) — v3
   1. Ambil seluruh data dari api/data.php?action=landing (1 request)
   2. 3 kartu ringkasan di halaman publik (angka sesuai isi file Excel
      yang diunggah admin)
   3. Informasi Data SHPED: dropdown KUESIONER (paling kiri — kode kuesioner
      dari kolom "Kuesioner" file SHPED, mis. HKD-1, HKD-2.1 — memfilter
      daftar komoditas pada card/grid agar hanya kuesioner terpilih),
      Komoditas (+daftar kualitas), Tahun, Kecamatan (default: Tobelo)
      + detail harga bulanan + grafik tren
      · Dropdown Tahun otomatis berisi tahun dari SEMUA unggahan yang
        selesai dianalisis — unggah file 2025 lalu 2026 → kedua tahun
        langsung muncul; memilih tahun milik unggahan lain memuat
        ulang data dari server (api/data.php?action=landing&tahun=…)
   4. Grid grafik ala PIHPS Nasional: 1 komoditas = 1 grafik,
      4 kolom × 6 baris (24) per halaman, navigasi halaman klik
      (bagian ini hanya dirender di dashboard admin)
   5. Peta harga Kabupaten Halmahera Utara (hanya di dashboard admin):
      warna kecamatan dari harga terendah (hijau) s.d. tertinggi (merah)
   6. Kelengkapan Data (hanya di dashboard admin): tabel seluruh data
      (tidur + bergerak) dengan tab, pencarian, halaman, tombol unduh
   Catatan arsitektur: file ini dipakai index.php DAN dashboard.php.
   Semua fungsi memeriksa keberadaan elemen (elemen bagian yang tidak ada
   di halaman tersebut otomatis dilewati). Seluruh kode dibungkus IIFE agar
   aman dimuat berdampingan dengan dashboard.js tanpa bentrokan konstanta.
   ===================================================================== */

(function () {
'use strict';

/* ---------------- Konstanta ---------------- */
const DEFAULT_KEC    = 'Tobelo';   // default kecamatan sesuai permintaan
const PER_HAL_GRAFIK = 24;         // 4 kolom × 6 baris
const PER_HAL_TABEL  = 25;

// Skala warna harga: terendah (hijau) → tertinggi (merah)
const WARNA_SKALA = ['#16a34a', '#a3e635', '#facc15', '#f97316', '#dc2626'];

// Koordinat perkiraan pusat kecamatan di Kabupaten Halmahera Utara
// (urut abjad — sesuaikan bila perlu lebih presisi)
const KOORDINAT_KEC = {
  'galela':           [1.8667, 127.8333],
  'galela barat':     [1.9500, 127.7167],
  'galela selatan':   [1.7833, 127.8000],
  'galela timur':     [1.9000, 127.9500],
  'galela utara':     [2.0333, 127.8667],
  'kao':              [1.5333, 127.8500],
  'kao barat':        [1.5833, 127.7167],
  'kao teluk':        [1.4667, 127.8833],
  'kao utara':        [1.6500, 127.9000],
  'loloda':           [1.2000, 127.5500],
  'loloda kepulauan': [1.3000, 127.4500],
  'malifut':          [1.2667, 127.7333],
  'tobelo':           [1.7333, 128.0167],
  'tobelo barat':     [1.7833, 127.8833],
  'tobelo selatan':   [1.6000, 128.0500],
  'tobelo tengah':    [1.6833, 127.9833],
  'tobelo timur':     [1.7833, 128.1333],
  'tobelo utara':     [1.8833, 128.0333],
  'wasile':           [1.4167, 128.1667],
  'wasile selatan':   [1.3167, 128.1500],
  'wasile tengah':    [1.5000, 128.1833],
  'wasile timur':     [1.4833, 128.3167],
  'wasile utara':     [1.6167, 128.1500],
};

const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

/* ---------------- State ---------------- */
let DATA = null;
let chartSHPED = null;
let chartKecil = [];                 // instance ECharts kartu grafik halaman aktif
let peta = null, layerPeta = null, ctrlLegenda = null;
let markerKec = new Map();           // nama kecamatan → marker (untuk klik peringkat)
let TAHUN_TERSEDIA = [];             // daftar tahun dari SEMUA unggahan (server)

const state = {
  kuesioner: 'semua',                // 'semua' | 'HKD-1' | 'HKD-2.1' | ...
  nama: '', kualitas: '',            // komoditas terpilih (Informasi Data SHPED)
  tahun: 'semua',                    // 'semua' | '2026' | ...
  kecamatan: DEFAULT_KEC,            // 'semua' | nama kecamatan
  halGrafik: 0,                      // halaman grid grafik aktif (0-based)
  tab: 'semua',                      // tab kelengkapan: semua | tidur | bergerak
  cari: '',
  halTabel: 0,
};

/* ---------------- Util ---------------- */
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const rp = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
const rpShort = n => {
  n = Number(n || 0);
  return n >= 1000 ? 'Rp ' + (n / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'rb' : rp(n);
};
const bulanID = iso => {
  if (!iso) return '-';
  const [y, m] = String(iso).slice(0, 7).split('-');
  return (NAMA_BULAN[+m - 1] || m) + ' ' + y;
};
const bulanPendek = iso => {
  const m = +String(iso).slice(5, 7);
  return (NAMA_BULAN[m - 1] || '').slice(0, 3);
};
const tglID = iso => {
  if (!iso) return '-';
  const d = new Date(String(iso).slice(0, 10) + 'T00:00:00');
  return isNaN(d) ? String(iso) : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};
const hexA = (hex, a) => {
  const v = hex.replace('#', '');
  return `rgba(${parseInt(v.slice(0, 2), 16)},${parseInt(v.slice(2, 4), 16)},${parseInt(v.slice(4, 6), 16)},${a})`;
};

function countUp(el, target, suffix = '') {
  const dur = 700, t0 = performance.now();
  const step = now => {
    const p = Math.min((now - t0) / dur, 1);
    el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('id-ID') + suffix;
    if (p < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/* Warna harga berdasarkan posisi relatif terhadap [min, max] */
function warnaHarga(v, min, max) {
  if (!isFinite(v) || max <= min) return WARNA_SKALA[2];
  const i = Math.min(WARNA_SKALA.length - 1,
                     Math.floor((v - min) / (max - min) * WARNA_SKALA.length));
  return WARNA_SKALA[i];
}

/* ---------------- Derivasi data ---------------- */

/* Seri yang tampak sesuai filter KUESIONER aktif.
   'semua' → seluruh seri; selain itu hanya seri dengan kode kuesioner sama.
   Seri tanpa kuesioner (data lama / kolom tidak dipilih) hanya muncul pada 'semua'. */
function seriTampak() {
  if (state.kuesioner === 'semua') return DATA.seri;
  return DATA.seri.filter(s => (s.kuesioner || '') === state.kuesioner);
}

/* Daftar kode kuesioner unik dari SELURUH data (bukan hanya yang tampak),
   diurut alami (HKD-1, HKD-2.1, HKD-2.2, HKD-10 setelah HKD-9). */
function kuesionerList() {
  const set = new Set();
  DATA.seri.forEach(s => { const k = (s.kuesioner || '').trim(); if (k) set.add(k); });
  return [...set].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
}

/* Grup komoditas (nama → daftar kualitas) dari seri yang tampak —
   dipakai dropdown komoditas, detail SHPED, dan grid grafik, sehingga
   semuanya menyesuaikan filter kuesioner. */
function grupKomoditas() {
  const map = new Map();
  for (const s of seriTampak()) {
    if (!map.has(s.komoditas)) map.set(s.komoditas, []);
    const list = map.get(s.komoditas);
    if (!list.includes(s.kualitas)) list.push(s.kualitas);
  }
  return [...map.entries()].map(([nama, kualitasList]) => ({ nama, kualitasList }));
}

/* Seri gabungan utk (nama+kualitas) pada filter kecamatan & tahun aktif.
   kecamatan 'semua' → rata-rata antar kecamatan per bulan. */
function seriAgg(nama, kualitas) {
  const pasangan = seriTampak().filter(s =>
    s.komoditas === nama && s.kualitas === kualitas &&
    (state.kecamatan === 'semua' || s.kecamatan === state.kecamatan));
  if (!pasangan.length) return null;

  // Kumpulkan nilai per bulan
  const perBulan = new Map();
  for (const s of pasangan) {
    for (const [iso, h] of s.titik) {
      if (state.tahun !== 'semua' && String(iso).slice(0, 4) !== String(state.tahun)) continue;
      if (!perBulan.has(iso)) perBulan.set(iso, []);
      perBulan.get(iso).push(h);
    }
  }
  const titik = [...perBulan.entries()]
    .sort((a, b) => a[0].localeCompare(b[0]))
    .map(([iso, arr]) => [iso, arr.reduce((x, y) => x + y, 0) / arr.length]);
  if (!titik.length) return null;

  const vals = titik.map(t => t[1]);
  const awal = vals[0], akhir = vals[vals.length - 1];
  const isTidur = pasangan.every(s => s.is_tidur) && Math.max(...vals) === Math.min(...vals);
  return {
    titik, jumlahBulan: titik.length,
    hargaAwal: awal, hargaAkhir: akhir,
    min: Math.min(...vals), max: Math.max(...vals),
    perubahan: awal ? (akhir - awal) / awal * 100 : 0,
    isTidur,
    satuan: pasangan[0].satuan,
    kuesioner: pasangan[0].kuesioner || '',
    alasan: pasangan[0].alasan_tidur,
    bulanMulai: titik[0][0], bulanAkhir: titik[titik.length - 1][0],
  };
}

function tahunList() {
  const set = new Set();
  seriTampak().forEach(s => s.titik.forEach(t => set.add(String(t[0]).slice(0, 4))));
  return [...set].sort();
}

function kecamatanList() {
  return [...new Set(seriTampak().map(s => s.kecamatan))];
}

/* =====================================================================
   1) KARTU RINGKASAN + CHIP STATUS
   ===================================================================== */
function renderRingkasan() {
  const R = DATA.ringkasan;
  if ($('sumTotal'))   countUp($('sumTotal'), R.total_komoditas);
  if ($('sumTidur'))   countUp($('sumTidur'), R.total_tidur);
  if ($('sumLengkap')) countUp($('sumLengkap'), R.total_komoditas);
  if ($('breakLengkap')) {
    $('breakLengkap').innerHTML =
      `<b class="text-rose-600">${R.total_tidur.toLocaleString('id-ID')} data tidur</b> · ` +
      `<b class="text-emerald-600">${R.total_bergerak.toLocaleString('id-ID')} harga bergerak</b>`;
  }
  perbaruiChip();

  // Tombol unduh mengikuti data yang sedang ditampilkan (tahun aktif)
  const unduhCsv = $('unduhCsv'), unduhXlsx = $('unduhXlsx');
  if (unduhCsv)  unduhCsv.href  = `api/download.php?format=csv&upload_id=${R.upload_id}`;
  if (unduhXlsx) unduhXlsx.href = `api/download.php?format=xlsx&upload_id=${R.upload_id}`;
}

/* Chip status di hero/dashboard — menunjukkan tahun data yang sedang ditampilkan */
function perbaruiChip() {
  if (!DATA || !$('statusChip')) return;
  const R = DATA.ringkasan;
  const th = state.tahun !== 'semua'
    ? `Tahun ${state.tahun} · `
    : (R.tahun_diminta ? `Tahun ${R.tahun_diminta} · ` : '');
  $('statusChip').textContent =
    `${th}Periode ${bulanID(R.periode_mulai)} – ${bulanID(R.periode_selesai)} · ` +
    `${R.total_komoditas.toLocaleString('id-ID')} komoditas`;
}

/* =====================================================================
   2) INFORMASI DATA SHPED (dropdown + detail + tabel bulanan)
   ===================================================================== */
function tutupSemuaPanel() {
  ['panelKuesioner', 'panelKomoditas', 'panelTahun', 'panelKecamatan']
    .forEach(id => { const p = $(id); if (p) p.classList.add('hidden'); });
}

function pasangDropdown() {
  // Tombol buka/tutup (elemen yang tidak ada di halaman ini dilewati)
  [['btnDdKuesioner', 'panelKuesioner'],
   ['btnDdKomoditas', 'panelKomoditas'],
   ['btnDdTahun', 'panelTahun'],
   ['btnDdKecamatan', 'panelKecamatan']].forEach(([b, p]) => {
    const btn = $(b);
    if (!btn) return;
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const akanBuka = $(p).classList.contains('hidden');
      tutupSemuaPanel();
      if (akanBuka) $(p).classList.remove('hidden');
    });
  });
  document.addEventListener('click', e => {
    if (!e.target.closest('#ddKuesioner, #ddKomoditas, #ddTahun, #ddKecamatan')) tutupSemuaPanel();
  });
  if ($('cariKomoditas')) $('cariKomoditas').addEventListener('input', renderListKomoditas);
}

function renderListKuesioner() {
  const box = $('listKuesioner');
  if (!box || !DATA) return;
  const daftar = kuesionerList();

  // Jumlah komoditas (nama+kualitas unik) per kuesioner — tampil sebagai keterangan
  const jumlah = k => {
    const unik = new Set();
    DATA.seri.forEach(s => { if ((s.kuesioner || '') === k) unik.add(s.komoditas + '|' + s.kualitas); });
    return unik.size;
  };

  const tombol = [{ k: 'semua', label: 'Semua Kuesioner', sub: `${DATA.seri.length.toLocaleString('id-ID')} seri data tanpa filter` }]
    .concat(daftar.map(k => ({ k, label: k, sub: `${jumlah(k).toLocaleString('id-ID')} komoditas` })));

  if (daftar.length === 0) {
    box.innerHTML =
      '<p class="px-3 py-3 text-[11px] leading-relaxed text-slate-400">' +
      'Belum ada kode kuesioner pada data ini. Unggah ulang file dengan kolom ' +
      '<b>Kuesioner</b> dipilih di halaman upload, lalu analisis kembali.</p>';
    return;
  }

  box.innerHTML = tombol.map(t => {
    const on = state.kuesioner === t.k;
    return `
      <button type="button" class="dd-item ${on ? 'on' : ''}" data-kues="${esc(t.k)}">
        <span class="min-w-0">
          <span class="block font-semibold">${esc(t.label)}</span>
          <span class="block text-[10px] font-normal ${on ? '' : 'text-slate-400'}">${esc(t.sub)}</span>
        </span>
        ${on ? '<i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>' : ''}
      </button>`;
  }).join('');

  box.querySelectorAll('.dd-item').forEach(b => b.addEventListener('click', () => {
    state.kuesioner = b.dataset.kues;
    tutupSemuaPanel();
    state.halGrafik = 0;
    renderSHPED();       // memilih ulang komoditas default milik kuesioner ini
    renderGrafikGrid();
    renderPeta();
  }));
}

function renderListKomoditas() {
  if (!DATA || !$('listKomoditas')) return;
  const q = (($('cariKomoditas')?.value) || '').toLowerCase();
  const grup = grupKomoditas().filter(g => g.nama.toLowerCase().includes(q));
  const box = $('listKomoditas');
  if (!grup.length) {
    box.innerHTML = '<p class="px-3 py-4 text-center text-xs text-slate-400">Tidak ada komoditas cocok.</p>';
    return;
  }
  box.innerHTML = grup.map((g, gi) => {
    const buka = g.nama === state.nama ? 'open' : '';
    return `
      <div class="dd-group">
        <button type="button" class="dd-name ${buka}" data-grup="${esc(g.nama)}">
          <span>${esc(g.nama)} <span class="text-[10px] font-normal text-slate-400">(${g.kualitasList.length} kualitas)</span></span>
          <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="dd-sub ${buka ? '' : 'hidden'}">
          ${g.kualitasList.map(k => `
            <button type="button" class="dd-item ${g.nama === state.nama && k === state.kualitas ? 'on' : ''}"
                    data-kom="${esc(g.nama)}" data-kua="${esc(k)}">
              <span>${esc(k)}</span>
              ${g.nama === state.nama && k === state.kualitas ? '<i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>' : ''}
            </button>`).join('')}
        </div>
      </div>`;
  }).join('');

  // Buka/tutup grup kualitas
  box.querySelectorAll('.dd-name').forEach(b => b.addEventListener('click', e => {
    e.stopPropagation();
    b.classList.toggle('open');
    b.nextElementSibling.classList.toggle('hidden');
  }));
  // Pilih kualitas
  box.querySelectorAll('.dd-item').forEach(b => b.addEventListener('click', () => {
    state.nama = b.dataset.kom;
    state.kualitas = b.dataset.kua;
    tutupSemuaPanel();
    renderSHPED();
    renderPeta();
  }));
}

function renderListTahun() {
  if (!$('listTahun') || !DATA) return;
  // Sumber daftar: SEMUA unggahan selesai (field tahun_tersedia dari server)
  // — unggah file 2025 lalu 2026 → kedua tahun langsung muncul di sini.
  // Fallback: tahun yang ada dalam data yang sedang dimuat.
  const daftar = TAHUN_TERSEDIA.length
    ? TAHUN_TERSEDIA.map(t => ({
        th: String(t.tahun),
        sub: `${(t.total_komoditas || 0).toLocaleString('id-ID')} komoditas · ` +
             `${bulanID(t.periode_mulai)} – ${bulanID(t.periode_selesai)}`,
      }))
    : tahunList().map(t => ({ th: t, sub: 'dalam data yang dimuat' }));

  const tombol = [{ th: 'semua', label: 'Semua Tahun', sub: 'semua bulan pada data yang sedang dimuat' }]
    .concat(daftar.map(d => ({ th: d.th, label: d.th, sub: d.sub })));

  $('listTahun').innerHTML = tombol.map(t => {
    const on = String(state.tahun) === t.th;
    return `
      <button type="button" class="dd-item ${on ? 'on' : ''}" data-tahun="${t.th}">
        <span class="min-w-0">
          <span class="block font-semibold">${esc(t.label)}</span>
          <span class="block text-[10px] font-normal ${on ? '' : 'text-slate-400'}">${esc(t.sub)}</span>
        </span>
        ${on ? '<i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>' : ''}
      </button>`;
  }).join('');

  $('listTahun').querySelectorAll('.dd-item').forEach(b => b.addEventListener('click', async () => {
    const th = b.dataset.tahun;
    tutupSemuaPanel();

    if (th === 'semua' || tahunList().includes(th)) {
      // Tahun ada di data yang sedang dimuat → cukup filter lokal (cepat)
      state.tahun = th;
      perbaruiChip();
      renderSHPED();
      renderGrafikGrid();
      renderPeta();
      return;
    }

    // Tahun milik unggahan LAIN → muat ulang data dari server
    await muatDataTahun(th);
  }));
}

/* Muat ulang seluruh data dari unggahan yang memuat `tahun` (mis. 2025),
   lalu render ulang semua bagian. Pilihan kuesioner, komoditas & kecamatan
   dipertahankan bila masih tersedia pada data tahun baru. */
async function muatDataTahun(tahun) {
  const chip = $('statusChip');
  if (chip) chip.textContent = `Memuat data tahun ${tahun}…`;
  try {
    const res = await fetch(`api/data.php?action=landing&tahun=${encodeURIComponent(tahun)}`,
                            { headers: { Accept: 'application/json' } });
    const json = await res.json();
    if (!json.ok || !json.data) throw new Error(json.error || 'Data tidak tersedia.');

    DATA = json.data;
    TAHUN_TERSEDIA = DATA.tahun_tersedia || [];
    state.tahun = String(tahun);

    // Filter kuesioner dipertahankan bila kode itu masih ada di data baru
    if (state.kuesioner !== 'semua' && !kuesionerList().includes(state.kuesioner)) {
      state.kuesioner = 'semua';
    }

    const grup = grupKomoditas();
    if (!grup.length) throw new Error('Tidak ada data seri pada tahun ini.');
    if (!grup.find(g => g.nama === state.nama && g.kualitasList.includes(state.kualitas))) {
      state.nama = grup[0].nama;
      state.kualitas = grup[0].kualitasList[0];
    }
    if (!kecamatanList().includes(state.kecamatan)) {
      state.kecamatan = kecamatanList().includes(DEFAULT_KEC) ? DEFAULT_KEC : kecamatanList()[0];
    }
    state.halGrafik = 0;
    state.halTabel = 0;

    renderRingkasan();
    renderSHPED();
    renderGrafikGrid();
    renderPeta();
    renderKelengkapan();
  } catch (err) {
    if (chip) {
      chip.innerHTML =
        `<i class="fa-solid fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>` +
        esc(err.message || `Gagal memuat tahun ${tahun}`);
      setTimeout(perbaruiChip, 4000);
    }
  }
}

function renderListKecamatan() {
  if (!$('listKecamatan')) return;
  const list = kecamatanList();
  $('listKecamatan').innerHTML =
    `<button type="button" class="dd-item ${state.kecamatan === 'semua' ? 'on' : ''}" data-kec="semua">
       <span>Semua Kecamatan</span></button>` +
    list.map(k => `
      <button type="button" class="dd-item ${state.kecamatan === k ? 'on' : ''}" data-kec="${esc(k)}">
        <span>${esc(k)}</span></button>`).join('');
  $('listKecamatan').querySelectorAll('.dd-item').forEach(b => b.addEventListener('click', () => {
    state.kecamatan = b.dataset.kec;
    tutupSemuaPanel();
    state.halGrafik = 0;
    renderSHPED();
    renderGrafikGrid();
  }));
}

function badgeStatus(isTidur) {
  return isTidur
    ? '<span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-600">DATA TIDUR</span>'
    : '<span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-600">HARGA BERGERAK</span>';
}

function chipPerubahan(p) {
  if (Math.abs(p) < 0.005) return '<span class="text-xs font-semibold text-slate-400">— konstan</span>';
  const naik = p > 0;
  return `<span class="text-xs font-bold ${naik ? 'text-rose-600' : 'text-emerald-600'}">
      <i class="fa-solid fa-arrow-trend-${naik ? 'up' : 'down'} mr-1" aria-hidden="true"></i>${naik ? '+' : ''}${p.toFixed(1)}%</span>`;
}

function renderSHPED() {
  if (!DATA) return;
  const grup = grupKomoditas();
  if (!grup.length) return;
  if (!state.nama || !grup.find(g => g.nama === state.nama)) {
    state.nama = grup[0].nama;
    state.kualitas = grup[0].kualitasList[0];
  }
  if (!state.kualitas || !grup.find(g => g.nama === state.nama).kualitasList.includes(state.kualitas)) {
    state.kualitas = grup.find(g => g.nama === state.nama).kualitasList[0];
  }

  // Panel detail — fallback ke "Semua Kecamatan" bila komoditas ini
  // tidak memiliki data pada kecamatan yang sedang dipilih
  let S = seriAgg(state.nama, state.kualitas);
  if (!S && state.kecamatan !== 'semua') {
    state.kecamatan = 'semua';
    S = seriAgg(state.nama, state.kualitas);
  }
  if (!S) return;

  // Label tombol dropdown
  if ($('lblKuesioner')) $('lblKuesioner').textContent = state.kuesioner === 'semua' ? 'Semua Kuesioner' : state.kuesioner;
  if ($('lblKomoditas')) $('lblKomoditas').textContent = `${state.nama} — ${state.kualitas}`;
  if ($('lblTahun'))     $('lblTahun').textContent = state.tahun === 'semua' ? 'Semua Tahun' : state.tahun;
  if ($('lblKecamatan')) $('lblKecamatan').textContent = state.kecamatan === 'semua' ? 'Semua Kecamatan' : state.kecamatan;

  renderListKuesioner();
  renderListKomoditas();
  renderListTahun();
  renderListKecamatan();

  const R = DATA.ringkasan;

  if ($('dtlNama')) {
    $('dtlNama').textContent = state.nama;
    $('dtlKualitas').textContent = state.kualitas;
    // Badge kuesioner seri terpilih (disembunyikan bila data tidak punya kuesioner)
    if ($('dtlKuesioner')) {
      if (S.kuesioner) {
        $('dtlKuesioner').textContent = S.kuesioner;
        $('dtlKuesioner').classList.remove('hidden');
      } else {
        $('dtlKuesioner').classList.add('hidden');
      }
    }
    const elStatus = $('dtlStatus');
    if (elStatus) elStatus.outerHTML = badgeStatus(S.isTidur).replace('class="', 'id="dtlStatus" class="');
    $('dtlWilayah').textContent =
      `Kecamatan ${state.kecamatan === 'semua' ? 'semua kecamatan (rata-rata)' : state.kecamatan}` +
      ` · periode ${bulanID(S.bulanMulai)} – ${bulanID(S.bulanAkhir)}`;
    $('dtlHarga').textContent = rp(S.hargaAkhir);
    $('dtlSatuan').textContent = S.satuan;
    $('dtlBulanAkhir').textContent = `per ${bulanID(S.bulanAkhir)} `;
    $('dtlMax').textContent = rp(S.max);
    $('dtlMin').textContent = rp(S.min);
    $('dtlPerubahan').innerHTML = `<span class="${S.perubahan > 0.005 ? 'text-rose-600' : S.perubahan < -0.005 ? 'text-emerald-600' : 'text-slate-500'}">${S.perubahan > 0 ? '+' : ''}${S.perubahan.toFixed(1)}%</span>`;
    $('dtlAlasan').innerHTML =
      `<i class="fa-solid fa-circle-info mr-1.5 text-primary-500" aria-hidden="true"></i>` + esc(S.alasan);
  }

  if ($('chartSHPED')) renderChartSHPED(S);
  if ($('tbodyBulanan')) renderTabelBulanan(S);
}

function opsiGrafikBesar(S) {
  const warna = S.isTidur ? '#e11d48' : '#2563eb';
  return {
    tooltip: {
      trigger: 'axis',
      backgroundColor: 'rgba(15,23,42,.92)', borderWidth: 0,
      textStyle: { color: '#fff', fontSize: 12 },
      valueFormatter: v => rp(v),
    },
    grid: { left: 70, right: 20, top: 24, bottom: 34 },
    xAxis: {
      type: 'category',
      data: S.titik.map(t => bulanID(t[0])),
      axisLine: { lineStyle: { color: '#cbd5e1' } },
      axisLabel: { color: '#64748b', fontSize: 11 },
    },
    yAxis: {
      type: 'value', scale: true,
      axisLabel: { color: '#64748b', formatter: v => rpShort(v) },
      splitLine: { lineStyle: { color: '#e2e8f0' } },
    },
    series: [{
      type: 'line', smooth: 0.35,
      symbol: 'circle', symbolSize: 8, showSymbol: true,
      itemStyle: { color: warna, borderColor: '#fff', borderWidth: 2 },
      lineStyle: { width: 3, color: warna },
      areaStyle: {
        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
          { offset: 0, color: hexA(warna, 0.30) },
          { offset: 1, color: hexA(warna, 0.02) },
        ]),
      },
      data: S.titik.map(t => Math.round(t[1] * 100) / 100),
    }],
  };
}

function renderChartSHPED(S) {
  if (typeof echarts === 'undefined') return;
  const el = $('chartSHPED');
  if (!el) return;
  if (!chartSHPED) chartSHPED = echarts.init(el);
  chartSHPED.setOption(opsiGrafikBesar(S), true);
}

function renderTabelBulanan(S) {
  const tb = $('tbodyBulanan');
  if (!tb) return;
  let prev = null;
  tb.innerHTML = S.titik.map(([iso, h]) => {
    let delta = '<span class="text-xs text-slate-300">—</span>';
    let ket = '<span class="text-xs text-slate-400">Bulan awal periode</span>';
    if (prev !== null) {
      const p = prev ? (h - prev) / prev * 100 : 0;
      delta = Math.abs(h - prev) < 0.005
        ? '<span class="text-xs font-semibold text-slate-400">0%</span>'
        : `<span class="text-xs font-bold ${h > prev ? 'text-rose-600' : 'text-emerald-600'}">${h > prev ? '+' : ''}${p.toFixed(1)}%</span>`;
      ket = Math.abs(h - prev) < 0.005
        ? '<span class="text-xs text-slate-500"><i class="fa-solid fa-moon mr-1 text-rose-400" aria-hidden="true"></i>Tidak berubah</span>'
        : `<span class="text-xs text-slate-500">${h > prev ? 'Naik' : 'Turun'} dari ${rp(prev)}</span>`;
    }
    prev = h;
    return `
      <tr class="hover:bg-slate-50">
        <td class="px-5 py-3 font-semibold text-slate-700">${bulanID(iso)}</td>
        <td class="px-4 py-3 font-semibold tnum">${rp(h)} <span class="text-xs font-normal text-slate-400">${esc(S.satuan)}</span></td>
        <td class="px-4 py-3">${delta}</td>
        <td class="px-4 py-3">${ket}</td>
      </tr>`;
  }).join('');
}

/* =====================================================================
   3) GRID GRAFIK ala PIHPS NASIONAL (4 kolom × 6 baris, berhalaman)
      — hanya dirender di dashboard admin (elemen chartGrid ada)
   ===================================================================== */
function kartuGrafikHTML(u, i) {
  const idx = i;                                        // index dalam halaman
  const badge = u.S.isTidur
    ? '<span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-600">TIDUR</span>'
    : chipPerubahan(u.S.perubahan);
  return `
    <div class="chart-card cursor-pointer rounded-2xl bg-white p-4 shadow-card ring-1 ring-slate-100"
         data-idx="${idx}" title="Buka detail ${esc(u.nama)} — ${esc(u.kualitas)}">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <p class="truncate text-sm font-bold text-slate-700">${esc(u.nama)}</p>
          <p class="text-[11px] text-slate-400">${esc(u.kualitas)} · ${esc(u.S.satuan)}</p>
        </div>
        ${badge}
      </div>
      <p class="mt-2 text-xl font-extrabold tnum text-slate-800">${rp(u.S.hargaAkhir)}</p>
      <p class="text-[11px] text-slate-400">terakhir · ${bulanID(u.S.bulanAkhir)}</p>
      <div class="spark mt-2 h-28 w-full" data-idx="${idx}"></div>
    </div>`;
}

function sparkOption(S, warna) {
  return {
    grid: { left: 4, right: 4, top: 6, bottom: 2 },
    tooltip: {
      trigger: 'axis',
      backgroundColor: 'rgba(15,23,42,.92)', borderWidth: 0,
      textStyle: { color: '#fff', fontSize: 11 },
      formatter: ps => `${ps[0].name}: <b>${rp(ps[0].value)}</b>`,
    },
    xAxis: { type: 'category', data: S.titik.map(t => bulanPendek(t[0])), show: false },
    yAxis: { type: 'value', show: false, scale: true },
    series: [{
      type: 'line', smooth: 0.35, showSymbol: false,
      lineStyle: { width: 2, color: warna },
      areaStyle: {
        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
          { offset: 0, color: hexA(warna, 0.28) },
          { offset: 1, color: hexA(warna, 0.02) },
        ]),
      },
      data: S.titik.map(t => Math.round(t[1] * 100) / 100),
    }],
  };
}

function pagerHTML(halAktif, totalHal, idPrefix) {
  if (totalHal <= 1) return '';
  let btn = `<button class="page-btn" data-${idPrefix}-hal="${halAktif - 1}" ${halAktif === 0 ? 'disabled' : ''} aria-label="Halaman sebelumnya"><i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i></button>`;
  const mulai = Math.max(0, Math.min(halAktif - 3, totalHal - 7));
  const akhir = Math.min(totalHal, mulai + 7);
  if (mulai > 0) btn += `<span class="px-1 text-xs text-slate-400">…</span>`;
  for (let i = mulai; i < akhir; i++) {
    btn += `<button class="page-btn ${i === halAktif ? 'on' : ''}" data-${idPrefix}-hal="${i}">${i + 1}</button>`;
  }
  if (akhir < totalHal) btn += `<span class="px-1 text-xs text-slate-400">…</span>`;
  btn += `<button class="page-btn" data-${idPrefix}-hal="${halAktif + 1}" ${halAktif >= totalHal - 1 ? 'disabled' : ''} aria-label="Halaman berikutnya"><i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i></button>`;
  return btn;
}

function renderGrafikGrid() {
  if (!$('chartGrid')) return;               // bagian hanya ada di dashboard

  chartKecil.forEach(c => { try { c.dispose(); } catch (e) {} });
  chartKecil = [];

  if (typeof echarts === 'undefined') {
    $('chartGrid').innerHTML =
      '<div class="col-span-full rounded-2xl bg-white p-8 text-center text-sm text-slate-400"><i class="fa-solid fa-triangle-exclamation mr-2 text-amber-400" aria-hidden="true"></i>Library ECharts gagal dimuat (cek koneksi internet untuk CDN).</div>';
    return;
  }

  // Satu unit grafik per (komoditas + kualitas) sesuai filter kuesioner,
  // kecamatan & tahun — inilah "card/grid" yang menyesuaikan kuesioner.
  const unit = [];
  grupKomoditas().forEach(g => g.kualitasList.forEach(k => {
    const S = seriAgg(g.nama, k);
    if (S) unit.push({ nama: g.nama, kualitas: k, S });
  }));

  const totalHal = Math.ceil(unit.length / PER_HAL_GRAFIK);
  if (state.halGrafik >= totalHal) state.halGrafik = Math.max(0, totalHal - 1);
  const hal = state.halGrafik;
  const potongan = unit.slice(hal * PER_HAL_GRAFIK, (hal + 1) * PER_HAL_GRAFIK);

  if ($('chartInfo')) {
    $('chartInfo').textContent = unit.length
      ? `Menampilkan ${hal * PER_HAL_GRAFIK + 1}–${hal * PER_HAL_GRAFIK + potongan.length} dari ${unit.length.toLocaleString('id-ID')} grafik komoditas · halaman ${hal + 1}/${totalHal}` +
        (state.kuesioner !== 'semua' ? ` · kuesioner ${state.kuesioner}` : '')
      : 'Tidak ada data untuk filter ini.';
  }

  $('chartGrid').innerHTML = potongan.map((u, i) => kartuGrafikHTML(u, i)).join('');

  // Inisialisasi sparkline
  potongan.forEach((u, i) => {
    const el = document.querySelector(`.spark[data-idx="${i}"]`);
    if (!el) return;
    const c = echarts.init(el);
    c.setOption(sparkOption(u.S, u.S.isTidur ? '#e11d48' : '#2563eb'));
    chartKecil.push(c);
  });

  // Klik kartu → buka di Informasi Data SHPED
  $('chartGrid').querySelectorAll('.chart-card').forEach(card => {
    card.addEventListener('click', () => {
      const u = potongan[+card.dataset.idx];
      if (!u) return;
      state.nama = u.nama;
      state.kualitas = u.kualitas;
      renderSHPED();
      renderPeta();
      $('shped')?.scrollIntoView({ behavior: 'smooth' });
    });
  });

  // Halaman
  if ($('chartPager')) {
    $('chartPager').innerHTML = pagerHTML(hal, totalHal, 'g');
    $('chartPager').querySelectorAll('.page-btn:not(:disabled)').forEach(b => {
      b.addEventListener('click', () => {
        state.halGrafik = +b.dataset.gHal;
        renderGrafikGrid();
        $('grafik')?.scrollIntoView({ behavior: 'smooth' });
      });
    });
  }
}

/* =====================================================================
   4) PETA HARGA KABUPATEN HALMAHERA UTARA — hanya di dashboard admin
   ===================================================================== */
function renderPeta() {
  if (!$('map')) return;                     // bagian hanya ada di dashboard

  if (typeof L === 'undefined') {
    $('map').innerHTML =
      '<div class="grid h-full place-items-center gap-2 text-sm text-slate-400"><i class="fa-solid fa-triangle-exclamation text-2xl text-amber-400" aria-hidden="true"></i>Library Leaflet gagal dimuat (cek koneksi internet untuk CDN).</div>';
    return;
  }

  if (!peta) {
    peta = L.map('map', { scrollWheelZoom: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(peta);
  }

  if (layerPeta) layerPeta.remove();
  if (ctrlLegenda) { peta.removeControl(ctrlLegenda); ctrlLegenda = null; }
  layerPeta = L.layerGroup().addTo(peta);
  markerKec.clear();

  // Harga komoditas TERPILIH (SHPED) per kecamatan pada bulan terakhir filter
  const titikKec = [];
  seriTampak().forEach(s => {
    if (s.komoditas !== state.nama || s.kualitas !== state.kualitas) return;
    const filterTahun = t => state.tahun === 'semua' || String(t[0]).slice(0, 4) === String(state.tahun);
    const ada = s.titik.filter(t => filterTahun(t));
    if (ada.length) titikKec.push({ kec: s.kecamatan, seri: s, terakhir: ada[ada.length - 1] });
  });

  const tanpaKoordinat = [];
  const bernilai = titikKec.filter(p => KOORDINAT_KEC[(p.kec || '').toLowerCase().trim()]);
  titikKec.forEach(p => { if (!KOORDINAT_KEC[(p.kec || '').toLowerCase().trim()]) tanpaKoordinat.push(p.kec); });

  const hargaArr = bernilai.map(p => p.terakhir[1]);
  const hMin = hargaArr.length ? Math.min(...hargaArr) : 0;
  const hMax = hargaArr.length ? Math.max(...hargaArr) : 0;

  bernilai.forEach(p => {
    const ko = KOORDINAT_KEC[(p.kec || '').toLowerCase().trim()];
    const warna = warnaHarga(p.terakhir[1], hMin, hMax);
    const m = L.circleMarker(ko, {
      radius: 13, color: '#ffffff', weight: 2.5, fillColor: warna, fillOpacity: 0.95,
    }).addTo(layerPeta);
    markerKec.set((p.kec || '').trim(), m);

    // Keterangan langsung di peta: nama kecamatan + harga singkat
    m.bindTooltip(`<b>${esc(p.kec)}</b> · ${rpShort(p.terakhir[1])}`, {
      permanent: true, direction: 'right', offset: [10, 0], className: 'kec-label',
    });

    m.bindPopup(
      `<div style="min-width:220px;font-size:13px">
         <p style="font-size:15px;font-weight:800;margin-bottom:2px">
           <i class="fa-solid fa-location-dot" style="color:${warna};margin-right:6px" aria-hidden="true"></i>${esc(p.kec)}</p>
         <p style="color:#64748b;margin-bottom:6px">${esc(state.nama)} — ${esc(state.kualitas)}${p.seri.kuesioner ? ' · ' + esc(p.seri.kuesioner) : ''}</p>
         <p style="font-size:20px;font-weight:800">${rp(p.terakhir[1])} <span style="font-size:11px;font-weight:400;color:#94a3b8">${esc(p.seri.satuan)}</span></p>
         <p style="color:#64748b;margin:2px 0 6px"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Data bulan <b>${bulanID(p.terakhir[0])}</b></p>
         ${p.seri.is_tidur
           ? '<span style="background:#ffe4e6;color:#e11d48;font-weight:700;font-size:11px;padding:2px 8px;border-radius:999px">DATA TIDUR — konstan ≥ 3 bulan</span>'
           : '<span style="background:#d1fae5;color:#059669;font-weight:700;font-size:11px;padding:2px 8px;border-radius:999px">HARGA BERGERAK</span>'}
       </div>`);
  });

  // Panel peringkat harga (ala PIHPS Nasional): terendah → tertinggi
  renderRankingPeta(bernilai, hMin, hMax);

  // Legenda harga (terendah → tertinggi)
  const Legenda = L.Control.extend({
    options: { position: 'bottomleft' },
    onAdd() {
      const d = L.DomUtil.create('div', 'map-legend');
      d.innerHTML =
        `<p style="font-weight:800;margin:0">Harga ${esc(state.nama)} — ${esc(state.kualitas)}</p>
         <div class="bar"></div>
         <div style="display:flex;justify-content:space-between;gap:12px">
           <span>Terendah<br><b>${rp(hMin)}</b></span>
           <span style="text-align:right">Tertinggi<br><b>${rp(hMax)}</b></span>
         </div>`;
      L.DomEvent.disableClickPropagation(d);
      return d;
    },
  });
  ctrlLegenda = new Legenda();
  peta.addControl(ctrlLegenda);

  // Fokus kamera ke Halmahera Utara
  if (bernilai.length) {
    peta.fitBounds(L.latLngBounds(bernilai.map(p => KOORDINAT_KEC[p.kec.toLowerCase().trim()])).pad(0.25));
  } else {
    peta.setView([1.55, 127.95], 9);
  }

  const info = $('petaInfo');
  if (info) {
    if (bernilai.length) {
      info.textContent =
        `${state.nama} — ${state.kualitas} · ${state.tahun === 'semua' ? 'bulan terakhir' : 'bulan terakhir tahun ' + state.tahun}: ` +
        bulanID(bernilai[0].terakhir[0]);
    } else {
      info.textContent = '';
    }
  }

  const note = $('mapNote');
  if (note) {
    if (tanpaKoordinat.length) {
      note.textContent = 'Kecamatan belum memiliki koordinat (tambahkan di KOORDINAT_KEC pada landing.js): ' + [...new Set(tanpaKoordinat)].join(', ');
      note.classList.remove('hidden');
    } else {
      note.classList.add('hidden');
    }
  }
}

/* Panel peringkat harga kecamatan — daftar terendah → tertinggi (ala
   PIHPS Nasional). Klik baris → peta terbang ke penanda + popup terbuka. */
function renderRankingPeta(bernilai, hMin, hMax) {
  const ol = $('rankKecamatan');
  if (!ol) return;
  if (!bernilai.length) {
    ol.innerHTML = '<li class="py-6 text-center text-xs text-slate-400">Belum ada kecamatan dengan koordinat yang cocok.</li>';
    if ($('rankBulan'))     $('rankBulan').textContent = '—';
    if ($('rankKomoditas')) $('rankKomoditas').textContent = '—';
    if ($('rankUpdate'))    $('rankUpdate').textContent = '—';
    return;
  }
  const urut = [...bernilai].sort((a, b) => a.terakhir[1] - b.terakhir[1]);
  if ($('rankBulan'))     $('rankBulan').textContent = bulanID(urut[0].terakhir[0]);
  if ($('rankKomoditas')) $('rankKomoditas').textContent = `${state.nama} — ${state.kualitas}`;
  if ($('rankUpdate'))    $('rankUpdate').textContent =
    DATA?.ringkasan?.diperbarui ? tglID(DATA.ringkasan.diperbarui) : '-';

  ol.innerHTML = urut.map((p, i) => {
    const warna = warnaHarga(p.terakhir[1], hMin, hMax);
    return `
      <li>
        <button type="button" data-kec="${esc((p.kec || '').trim())}"
                class="rank-item w-full" title="Tampilkan di peta">
          <span class="rank-no tnum">${i + 1}</span>
          <span class="rank-dot" style="background:${warna}"></span>
          <span class="min-w-0 flex-1 text-left">
            <span class="block truncate text-xs font-bold text-slate-700">${esc(p.kec)}</span>
            <span class="block text-[10px] text-slate-400">Data ${bulanID(p.terakhir[0])}${p.seri.is_tidur ? ' · tidur' : ''}</span>
          </span>
          <span class="rank-harga tnum">${rp(p.terakhir[1])}</span>
        </button>
      </li>`;
  }).join('');

  ol.querySelectorAll('.rank-item').forEach(b => b.addEventListener('click', () => {
    const m = markerKec.get(b.dataset.kec);
    if (!m) return;
    peta.flyTo(m.getLatLng(), Math.max(peta.getZoom(), 10), { duration: 0.6 });
    m.openPopup();
  }));
}

/* =====================================================================
   5) KELENGKAPAN DATA — hanya di dashboard admin
      (tabel seluruh data + tab + cari + halaman)
   ===================================================================== */
function renderKelengkapan() {
  if (!$('tbodyKelengkapan')) return;        // bagian hanya ada di dashboard

  // Tab aktif
  $('tabKelengkapan')?.querySelectorAll('.tab-btn').forEach(b => {
    const on = b.dataset.tab === state.tab;
    b.classList.toggle('bg-primary-600', on);
    b.classList.toggle('text-white', on);
    b.classList.toggle('bg-white', !on);
    b.classList.toggle('text-slate-600', !on);
  });

  const q = state.cari.toLowerCase();
  const rows = seriTampak().filter(s => {
    if (state.tab === 'tidur' && !s.is_tidur) return false;
    if (state.tab === 'bergerak' && s.is_tidur) return false;
    if (q && !(`${s.komoditas} ${s.kualitas} ${s.kecamatan}`.toLowerCase().includes(q))) return false;
    return true;
  });

  const totalHal = Math.ceil(rows.length / PER_HAL_TABEL);
  if (state.halTabel >= totalHal) state.halTabel = Math.max(0, totalHal - 1);
  const potongan = rows.slice(state.halTabel * PER_HAL_TABEL, (state.halTabel + 1) * PER_HAL_TABEL);

  const tb = $('tbodyKelengkapan');
  if (!potongan.length) {
    tb.innerHTML = '<tr><td colspan="8" class="px-5 py-8 text-center text-slate-400"><i class="fa-solid fa-circle-check mr-2 text-emerald-500" aria-hidden="true"></i>Tidak ada data pada tab/pencarian ini.</td></tr>';
  } else {
    tb.innerHTML = potongan.map(s => `
      <tr class="hover:bg-slate-50">
        <td class="px-5 py-3 font-semibold text-slate-700">${esc(s.komoditas)}</td>
        <td class="px-4 py-3 text-slate-500">${esc(s.kualitas)}</td>
        <td class="px-4 py-3 text-slate-500">${esc(s.kecamatan)}</td>
        <td class="px-4 py-3 tnum">${rp(s.harga_awal)}</td>
        <td class="px-4 py-3 font-semibold tnum">${rp(s.harga_akhir)} <span class="text-xs font-normal text-slate-400">${esc(s.satuan)}</span></td>
        <td class="px-4 py-3">${chipPerubahan(s.perubahan_persen)}</td>
        <td class="px-4 py-3 text-xs text-slate-500">${s.jumlah_bulan} bulan<br><span class="text-slate-400">${bulanID(s.bulan_mulai)} – ${bulanID(s.bulan_selesai)}</span></td>
        <td class="px-4 py-3">${badgeStatus(s.is_tidur)}</td>
      </tr>`).join('');
  }

  if ($('tabelPager')) {
    $('tabelPager').innerHTML = pagerHTML(state.halTabel, totalHal, 't');
    $('tabelPager').querySelectorAll('.page-btn:not(:disabled)').forEach(b => {
      b.addEventListener('click', () => {
        state.halTabel = +b.dataset.tHal;
        renderKelengkapan();
      });
    });
  }
}

/* =====================================================================
   INIT
   ===================================================================== */
async function init() {
  pasangDropdown();

  // Klik kartu ringkasan #2 & #3 → set tab kelengkapan sebelum berpindah
  // (tab hanya berpengaruh di halaman yang memuat tabel kelengkapan)
  document.querySelectorAll('.card-tab').forEach(a => {
    a.addEventListener('click', () => {
      state.tab = a.dataset.tab === 'tidur' ? 'tidur' : 'semua';
      state.halTabel = 0;
      if (DATA) renderKelengkapan();
    });
  });

  // Tab kelengkapan manual (hanya ada di dashboard)
  $('tabKelengkapan')?.querySelectorAll('.tab-btn').forEach(b => {
    b.addEventListener('click', () => {
      state.tab = b.dataset.tab;
      state.halTabel = 0;
      renderKelengkapan();
    });
  });
  if ($('cariKelengkapan')) {
    $('cariKelengkapan').addEventListener('input', e => {
      state.cari = e.target.value;
      state.halTabel = 0;
      renderKelengkapan();
    });
  }

  try {
    const res = await fetch('api/data.php?action=landing', { headers: { Accept: 'application/json' } });
    const json = await res.json();
    if (!json.ok || !json.data) {
      // Belum ada data sama sekali → sembunyikan bagian data, tampilkan pesan
      ['shped', 'grafik', 'peta', 'kelengkapan'].forEach(id => $(id)?.classList.add('hidden'));
      if ($('emptyState')) $('emptyState').classList.remove('hidden');
      if ($('dashEmpty'))  $('dashEmpty').classList.remove('hidden');
      if ($('ringkasan')?.querySelector('.grid')) $('ringkasan').querySelector('.grid').classList.add('hidden');
      ['sumTotal', 'sumTidur', 'sumLengkap'].forEach(id => { const n = $(id); if (n) n.textContent = '0'; });
      if ($('statusChip')) $('statusChip').textContent = 'Belum ada data — menunggu unggahan admin';
      return;
    }

    DATA = json.data;
    TAHUN_TERSEDIA = DATA.tahun_tersedia || [];   // tahun dari SEMUA unggahan

    // Default filter: kuesioner semua · komoditas pertama · tahun semua · kecamatan Tobelo
    const grup = grupKomoditas();
    state.kuesioner = 'semua';
    state.nama = grup[0].nama;
    state.kualitas = grup[0].kualitasList[0];
    state.kecamatan = kecamatanList().includes(DEFAULT_KEC) ? DEFAULT_KEC : kecamatanList()[0];
    state.tahun = 'semua';

    renderRingkasan();
    renderSHPED();
    renderGrafikGrid();
    renderPeta();
    renderKelengkapan();
  } catch (err) {
    if ($('statusChip')) {
      $('statusChip').innerHTML =
        '<i class="fa-solid fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>Gagal memuat data — pastikan Apache & MySQL aktif';
    }
  }
}

window.addEventListener('resize', () => {
  chartSHPED?.resize();
  chartKecil.forEach(c => c.resize());
});
init();

})();
