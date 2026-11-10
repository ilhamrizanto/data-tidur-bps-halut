/* =====================================================================
   dashboard.js — Logika dashboard admin
   1. Muat statistik + riwayat unggahan (api/uploads.php)
   2. Cek status layanan FastAPI (api/health.php)
   3. Tombol "Coba Data Contoh" (api/sample.php) — hanya admin
   4. Hapus unggahan — hanya admin
   Role viewer (hanya melihat): BOLEH_KELOLA=false dari dashboard.php —
   tombol hapus disembunyikan; penolakan utama tetap di server.
   ===================================================================== */

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
// BOLEH_KELOLA di-set dashboard.php via <script> sebelum file ini (true utk admin).
const BOLEH_KELOLA = (typeof window.BOLEH_KELOLA === 'boolean') ? window.BOLEH_KELOLA : true;
const NCOL_TABEL = BOLEH_KELOLA ? 8 : 7;   // kolom hapus hanya ada utk admin
const el = id => document.getElementById(id);

const STATUS_PILL = {
  done:       ['bg-emerald-100 text-emerald-700', 'Selesai'],
  processing: ['bg-amber-100 text-amber-700', 'Proses'],
  failed:     ['bg-rose-100 text-rose-700', 'Gagal'],
};

const esc = s => String(s ?? '').replace(/[&<>"']/g, c =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const tglID = iso => {
  if (!iso) return '-';
  const d = new Date(String(iso).slice(0, 10) + 'T00:00:00');
  return isNaN(d) ? String(iso) : d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

/* ---------- statistik ---------- */
/* set(): tulis textContent HANYA bila elemennya ada — mencegah error
   "Cannot set properties of null (setting 'textContent')" bila file JS
   dan HTML tidak sinkron versinya (mis. cache browser masih menyimpan
   JS lama). */
const set = (id, v) => { const n = el(id); if (n) n.textContent = v; };

async function muatStats() {
  try {
    const res = await fetch('api/uploads.php');
    const json = await res.json();
    if (!json.ok) throw new Error(json.error);

    const s = json.stats || {};
    set('statUpload', (s.total_upload ?? 0).toLocaleString('id-ID'));
    set('statKomoditas', s.komoditas_terakhir ?? '0');
    set('statTidur', s.tidur_terakhir ?? '0');
    set('statPeriode', s.periode?.[0]
      ? `${tglID(s.periode[0])} – ${tglID(s.periode[1])}`
      : '-');
    renderTabel(json.uploads || []);
  } catch (err) {
    set('statUpload', '!');
    renderTabelError(err.message);
  }
}

/* ---------- cek layanan FastAPI ---------- */
async function cekLayanan() {
  const pill = el('statStatus');
  if (!pill) return;                       // elemen tidak ada → lewati saja
  try {
    const res = await fetch('api/health.php');
    const json = await res.json();
    if (json.api === 'online') {
      pill.className = 'inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700';
      pill.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i> API Siap';
    } else {
      throw new Error('offline');
    }
  } catch (e) {
    pill.className = 'inline-flex items-center gap-2 rounded-full bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700';
    pill.innerHTML = '<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> Offline';
    pill.title = 'Jalankan: cd python-api && python -m uvicorn main:app --port 8000';
  }
}

/* ---------- tabel riwayat ---------- */
function renderTabel(rows) {
  const tb = el('tblUploads');
  if (!rows.length) {
    tb.innerHTML = `<tr><td colspan="${NCOL_TABEL}" class="px-6 py-8 text-center text-slate-400">Belum ada unggahan.${BOLEH_KELOLA ? ' Klik "Coba Data Contoh" untuk mencoba cepat.' : ''}</td></tr>`;
    return;
  }
  tb.innerHTML = rows.map(r => {
    const [pillCls, pillTxt] = STATUS_PILL[r.status] || ['bg-slate-100 text-slate-500', r.status];
    const periode = r.periode?.[0] ? `${tglID(r.periode[0])} – ${tglID(r.periode[1])}` : '-';
    return `
      <tr class="hover:bg-slate-50">
        <td class="px-6 py-3">
          <p class="max-w-[220px] truncate font-semibold text-slate-700" title="${esc(r.original_name)}">${esc(r.original_name)}</p>
          <p class="text-xs text-slate-400">${r.total_baris.toLocaleString('id-ID')} baris · .${esc(r.file_type)}</p>
        </td>
        <td class="px-4 py-3 text-xs text-slate-500">${periode}</td>
        <td class="px-4 py-3 tnum">${r.total_komoditas || '-'}</td>
        <td class="px-4 py-3 tnum font-semibold ${r.total_tidur > 0 ? 'text-rose-600' : 'text-slate-400'}">${r.status === 'done' ? r.total_tidur : '-'}</td>
        <td class="px-4 py-3 tnum text-emerald-600">${r.status === 'done' ? r.total_bergerak : '-'}</td>
        <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ${pillCls}">${pillTxt}</span>${r.status === 'failed' && r.pesan ? `<p class="mt-1 max-w-[180px] text-[11px] text-rose-400">${esc(r.pesan)}</p>` : ''}</td>
        <td class="px-4 py-3 text-xs text-slate-500">${tglID(r.created_at)}</td>
        ${BOLEH_KELOLA ? `<td class="px-4 py-3 text-right">
          <button data-del="${r.id}" title="Hapus" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-400 hover:bg-rose-50 hover:text-rose-600">Hapus</button>
        </td>` : ''}
      </tr>`;
  }).join('');

  tb.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => hapusUpload(b.dataset.del)));
}

function renderTabelError(msg) {
  const tb = el('tblUploads');
  if (!tb) return;
  tb.innerHTML =
    `<tr><td colspan="${NCOL_TABEL}" class="px-6 py-8 text-center text-rose-500">${esc(msg)}</td></tr>`;
}

async function hapusUpload(id) {
  if (!confirm('Hapus unggahan ini beserta seluruh hasil analisisnya?')) return;
  const res = await fetch('api/uploads.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ action: 'delete', id: Number(id) }),
  });
  const json = await res.json();
  json.ok ? muatStats() : alert(json.error || 'Gagal menghapus');
}

/* ---------- data contoh ---------- */
el('btnSample')?.addEventListener('click', async () => {
  const btn = el('btnSample');
  if (!confirm('Jalankan analisis dengan data contoh (contoh_harga.csv)?')) return;
  btn.disabled = true;
  btn.style.opacity = '.6';
  try {
    const res = await fetch('api/sample.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': CSRF },
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Gagal menjalankan analisis contoh');
    toast(`Analisis contoh selesai — ${json.ringkasan.total_komoditas} komoditas, ${json.ringkasan.total_tidur} data tidur, ${json.ringkasan.total_bergerak} harga bergerak (periode ${json.ringkasan.periode_mulai} s.d. ${json.ringkasan.periode_selesai}).`);
    muatStats();
  } catch (err) {
    toast(err.message, true);
  } finally {
    btn.disabled = false;
    btn.style.opacity = '';
  }
});

function toast(msg, error = false) {
  const t = el('toast');
  if (!t) return;                          // elemen tidak ada → lewati saja
  t.innerHTML = `<div class="flex items-start gap-2"><i class="fa-solid ${error ? 'fa-triangle-exclamation text-amber-400' : 'fa-circle-check text-emerald-400'} mt-0.5" aria-hidden="true"></i><span>${esc(msg)}</span></div>
    <a href="index.php" target="_blank" class="mt-3 inline-block text-xs font-semibold text-blue-300 hover:text-blue-200">Lihat Halaman Publik <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>`;
  t.classList.remove('hidden');
  clearTimeout(t._tm);
  t._tm = setTimeout(() => t.classList.add('hidden'), 8000);
}

/* ---------- init ---------- */
el('btnRefresh')?.addEventListener('click', muatStats);
muatStats();
cekLayanan();
// Catatan: tombol "Coba Data Contoh" (btnSample) hanya ada di halaman admin —
// untuk viewer elemennya tidak dirender sehingga listener di atas aman (?.).
