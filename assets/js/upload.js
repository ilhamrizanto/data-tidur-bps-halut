/* =====================================================================
   upload.js — Logika halaman "Upload & Konfigurasi" (admin)
   Alur: pilih/drag file → POST api/upload_file.php (dapat kolom + preview
   + tebakan peran) → mapping peran kolom (FORMAT LEBAR: kolom = bulan)
   → POST api/analyze.php → tampilkan hasil.
   Catatan: opsi "Format Baris" sudah dihapus — halaman ini selalu memakai
   format lebar (setiap kolom bulan berisi harga satu bulan).
   ===================================================================== */

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const MB = 1024 * 1024;

const el = id => document.getElementById(id);
const state = { file: null, uploadId: null };

/* Escape HTML (anti-XSS) — dipakai saat merender preview & mapping */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ---------- helper tampilan ---------- */
function showAlert(msg) {
  const a = el('alertBox');
  a.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-2" aria-hidden="true"></i>' + esc(msg);
  a.classList.remove('hidden');
}
function hideAlert() { el('alertBox').classList.add('hidden'); }

/* Baca respons fetch dengan aman.
 * Masalah lama: bila server menjawab 204/5xx berupa HTML/Respons kosong,
 * res.json() meledak dengan pesan samar ("Unexpected end of JSON input")
 * sehingga pengguna hanya melihat kode seperti "204 No Content" tanpa tahu
 * apa yang harus dilakukan. Sekarang status HTTP + cuplikan isi respons
 * selalu ikut ditampilkan, beserta petunjuk penyebab yang paling mungkin. */
async function bacaJson(res) {
  const teks = await res.text();
  const status = res.status + (res.statusText ? ' ' + res.statusText : '');
  if (teks) {
    try { return JSON.parse(teks); } catch (_) {
      throw new Error('Server menjawab HTTP ' + status + ' tetapi isinya bukan JSON. '
        + 'Cuplikan: "' + teks.slice(0, 140).replace(/\s+/g, ' ') + '". '
        + 'Biasanya ini halaman error Apache/PHP — cek error_log XAMPP.');
    }
  }
  // Respons kosong tanpa isi (mis. 204 No Content)
  const penyebab = res.status === 204
    ? 'Respons kosong (204 No Content) biasanya berarti permintaan tidak pernah '
      + 'sampai ke aplikasi: (1) layanan Python belum jalan — jalankan '
      + 'jalankan_api.bat; (2) ada proses lama di port 8000 — tutup lalu jalankan '
      + 'ulang jalankan_api.bat; (3) antivirus/ekstensi browser memblokir unggahan.'
    : 'Respons kosong dari server (HTTP ' + status + '). Periksa apakah layanan '
      + 'analisis Python sedang berjalan (jalankan_api.bat).';
  throw new Error(penyebab);
}

function chipIconFor(name) {
  return /\.xlsx$/i.test(name)
    ? '<i class="fa-solid fa-file-excel text-emerald-600" aria-hidden="true"></i>'
    : '<i class="fa-solid fa-file-csv text-primary-600" aria-hidden="true"></i>';
}
function fmtSize(bytes) {
  return bytes >= MB ? (bytes / MB).toFixed(2) + ' MB' : (bytes / 1024).toFixed(1) + ' KB';
}

/* ---------- 0. Cek status layanan Python (api/health.php) ---------- */
(async () => {
  const box = el('apiStatus');
  if (!box) return;
  try {
    const res = await fetch('api/health.php', { headers: { 'X-CSRF-Token': CSRF } });
    const j = await bacaJson(res);
    if (j.api !== 'online') throw new Error('offline');
    box.className = 'mb-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700';
    box.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>'
      + '<span><b>Layanan analisis aktif</b> — file dapat diunggah.</span>';
  } catch (_) {
    box.className = 'mb-6 flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800';
    box.innerHTML = '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>'
      + '<span><b>Layanan analisis Python tidak aktif.</b></span>';
  }
})();

/* ---------- 1. Dropzone ---------- */
const dz = el('dropZone'), fi = el('fileInput');

dz.addEventListener('click', () => fi.click());
['dragenter', 'dragover'].forEach(ev =>
  dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
['dragleave', 'drop'].forEach(ev =>
  dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
dz.addEventListener('drop', e => {
  const f = e.dataTransfer?.files?.[0];
  if (f) pilihFile(f);
});
fi.addEventListener('change', () => { if (fi.files[0]) pilihFile(fi.files[0]); });

function pilihFile(f) {
  const ext = f.name.split('.').pop().toLowerCase();
  if (!['csv', 'xlsx'].includes(ext))  { showAlert('Format harus .csv atau .xlsx'); return; }
  if (f.size > 10 * MB)                { showAlert('Ukuran file maksimal 10 MB'); return; }
  hideAlert();
  unggahFile(f);
}

/* ---------- 2. Unggah + inspect ---------- */
async function unggahFile(f) {
  state.file = f;
  state.uploadId = null;
  el('cardMapping').classList.add('hidden');
  el('resultPanel').classList.add('hidden');
  el('previewWrap').classList.add('hidden');

  el('fileChip').classList.remove('hidden');
  el('fileChip').classList.add('flex');
  el('chipName').textContent = f.name;
  el('chipSize').textContent = fmtSize(f.size);
  el('chipIcon').innerHTML = chipIconFor(f.name);
  el('chipStatus').innerHTML = '<span class="spinner inline-block !h-4 !w-4 !border-2 align-middle"></span> memeriksa file…';
  el('btnAnalyze').disabled = true;

  const fd = new FormData();
  fd.append('file', f);
  try {
    const res = await fetch('api/upload_file.php', {
      method: 'POST', body: fd, headers: { 'X-CSRF-Token': CSRF },
    });
    const json = await bacaJson(res);
    if (!json.ok) throw new Error(json.error || 'Gagal memeriksa file (HTTP ' + res.status + ')');

    state.uploadId = json.upload_id;
    el('chipStatus').textContent = json.total_rows.toLocaleString('id-ID') + ' baris terbaca';
    isiPreview(json.columns, json.preview);
    isiMapping(json.columns, json.guess || {});
  } catch (err) {
    el('chipStatus').textContent = 'gagal';
    showAlert(err.message);
  }
}

function isiPreview(columns, rows) {
  el('tblPreviewHead').innerHTML =
    '<tr>' + columns.map(c => `<th class="px-3 py-2 font-semibold">${esc(c)}</th>`).join('') + '</tr>';
  el('tblPreviewBody').innerHTML = rows.map(r =>
    '<tr>' + r.map(v => `<td class="px-3 py-2 text-slate-600">${esc(v === null ? '' : v)}</td>`).join('') + '</tr>'
  ).join('');
  el('previewWrap').classList.remove('hidden');
}

/* ---------- 3. Mapping peran kolom ---------- */
function isiSelect(selectId, columns, terpilih, kosongBoleh, labelKosong) {
  const s = el(selectId);
  let html = kosongBoleh ? `<option value="">${esc(labelKosong || '— tidak ada —')}</option>` : '';
  html += columns.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
  s.innerHTML = html;
  if (terpilih && columns.includes(terpilih)) {
    s.value = terpilih;                       // tebakan yakin → pakai tebakan
  } else if (kosongBoleh) {
    s.value = '';
  } else {
    // WAJIB tapi tebakan tidak yakin → kosongkan (wajib dipilih manual).
    // Jangan pernah diam-diam memakai kolom pertama — itu penyebab hasil
    // analisis salah/kosong pada file SHPED asli.
    s.insertAdjacentHTML('afterbegin',
      '<option value="" disabled selected>— pilih kolom —</option>');
    s.value = '';
  }
}

function isiMapping(columns, guess) {
  isiSelect('komoditasCol', columns, guess.komoditas_col, false);
  isiSelect('kualitasCol',  columns, guess.kualitas_col,  true, '— tanpa kualitas (dipakai “Standar”) —');
  isiSelect('kecamatanCol', columns, guess.kecamatan_col, true, '— tanpa kecamatan (dipakai “Tobelo”) —');
  isiSelect('satuanCol',    columns, guess.satuan_col,    true, '— tanpa satuan (dipakai “/kg”) —');
  // Kolom kuesioner (opsional) — kode kuesioner file SHPED (HKD-1, HKD-2.1, …)
  // yang menjadi sumber dropdown filter kuesioner di halaman depan & dashboard.
  isiSelect('kuesionerCol', columns, guess.kuesioner_col, true, '— tanpa kuesioner —');

  // Kolom bulan — chip auto terpilih dari tebakan
  const bulanCols = guess.month_cols || [];
  const box = el('monthCols');
  box.innerHTML = '';
  columns.forEach(c => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'chip-col' + (bulanCols.includes(c) ? ' on' : '');
    b.textContent = c;
    b.dataset.col = c;
    b.addEventListener('click', () => b.classList.toggle('on'));
    box.appendChild(b);
  });

  el('tahunWide').value = guess.tahun_default || new Date().getFullYear();

  el('cardMapping').classList.remove('hidden');
  el('btnAnalyze').disabled = false;
}

/* ---------- 4. Analisis ---------- */
el('btnAnalyze').addEventListener('click', async () => {
  const monthCols = [...el('monthCols').querySelectorAll('.chip-col.on')].map(b => b.dataset.col);

  if (!el('komoditasCol').value)        return showAlert('Pilih kolom komoditas.');
  if (!monthCols.length)                return showAlert('Pilih minimal satu kolom bulan.');
  if (!el('tahunWide').value)           return showAlert('Isi tahun untuk kolom bulan.');
  if (!state.uploadId)                  return showAlert('Unggah file terlebih dahulu.');
  hideAlert();

  el('btnAnalyze').disabled = true;
  el('analyzeOverlay').classList.remove('hidden');
  el('analyzeOverlay').classList.add('flex');

  try {
    const body = {
      upload_id: state.uploadId,
      mode: 'wide',
      komoditas_col: el('komoditasCol').value,
      kualitas_col:  el('kualitasCol').value  || null,
      kecamatan_col: el('kecamatanCol').value || null,
      satuan_col:    el('satuanCol').value    || null,
      kuesioner_col: el('kuesionerCol').value || null,
      value_col:     null,
      time_col:      null,
      month_cols:    monthCols,
      tahun_default: parseInt(el('tahunWide').value, 10) || null,
    };

    const res = await fetch('api/analyze.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(body),
    });
    const json = await bacaJson(res);
    if (!json.ok) throw new Error(json.error || 'Analisis gagal (HTTP ' + res.status + ')');

    const R = json.ringkasan;
    el('resKomoditas').textContent = R.total_komoditas.toLocaleString('id-ID');
    el('resTidur').textContent     = R.total_tidur.toLocaleString('id-ID');
    el('resBergerak').textContent  = R.total_bergerak.toLocaleString('id-ID');
    el('resPeriode').textContent   = periodeTeks(R.periode_mulai, R.periode_selesai);
    el('resultPanel').classList.remove('hidden');
    el('resultPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch (err) {
    showAlert(err.message);
  } finally {
    el('analyzeOverlay').classList.add('hidden');
    el('analyzeOverlay').classList.remove('flex');
    el('btnAnalyze').disabled = false;
  }
});

const BULAN_ID = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function periodeTeks(mulai, selesai) {
  if (!mulai || !selesai) return '-';
  const f = iso => {
    const d = new Date(String(iso).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? iso : `${String(d.getDate()).padStart(2, '0')} ${BULAN_ID[d.getMonth()]} ${d.getFullYear()}`;
  };
  return `${f(mulai)} – ${f(selesai)}`;
}

/* ---------- 5. Reset ---------- */
el('chipRemove').addEventListener('click', () => resetSemua());
el('btnReset').addEventListener('click', () => resetSemua());

function resetSemua() {
  state.file = null;
  state.uploadId = null;
  fi.value = '';
  el('fileChip').classList.add('hidden');
  el('fileChip').classList.remove('flex');
  el('previewWrap').classList.add('hidden');
  el('cardMapping').classList.add('hidden');
  el('resultPanel').classList.add('hidden');
  hideAlert();
}
