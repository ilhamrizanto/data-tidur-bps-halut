"""
analyzer.py — INTI ANALISIS DETEKSI DATA TIDUR (Pandas + NumPy + OpenPyXL).

Konsep baru (sesuai definisi pengguna):
  • "Komoditas"  = pasangan unik (NAMA komoditas + KUALITAS), mis. "Beras Premium".
    Jumlah komoditas yang ditampilkan = persis jumlah baris unik di file Excel.
  • "Data tidur" = komoditas yang HARGANYA KONSTAN (tidak berubah) MINIMAL 3 BULAN
    BERTURUT-TURUT. Contoh: beras, gula, dan garam tetap Rp 30.000/kg pada
    Mei–Juli (3 bulan) → 3 data tidur. Harga yang konstan hanya 2 bulan
    (mis. Nov–Des) TIDAK dihitung sebagai data tidur.
  • Unit wilayah = KECAMATAN (kabupaten Halmahera Utara), default "Tobelo"
    bila file tidak memiliki kolom kecamatan.
  • Dukung dua format file:
      - MODE BARIS : satu baris = satu harga  (komoditas, kualitas, kecamatan,
                     satuan, tanggal/bulan, harga).
      - MODE LEBAR : satu baris = satu komoditas, kolom = bulan
                     (komoditas, kualitas, kecamatan, satuan, Mei, Juni, Juli, ...).

Tahapan analisis:
  1. Baca CSV/Excel → bersihkan angka ("Rp 12.500", "1.234,56") & waktu.
  2. Normalisasi ke struktur standar → agregasi per BULAN (rata-rata harga).
  3. Analisis tiap seri (komoditas × kualitas × kecamatan):
       is_tidur = harga semua bulan SAMA (konstan) dan ≥ 3 bulan data
       (min_bulan_tidur di DEFAULT_CFG — konstan 2 bulan bukan data tidur).
  4. Ringkasan: jumlah komoditas, jumlah tidur, jumlah bergerak, periode.
"""

import calendar
import math
import os
import re
import warnings
from typing import Optional

import numpy as np
import pandas as pd

# ------------------------- Parameter default -------------------------
DEFAULT_CFG = {
    "toleransi_std": 1e-9,     # selisih harga bulanan ≤ ini dianggap nol (konstan)
    "min_bulan_tidur": 3,      # ATURAN TIDUR: konstan minimal 3 bulan → data tidur
}                             # (2 bulan konstan TIDAK dihitung data tidur)

# ------------------------- Nama bulan Indonesia -------------------------
BULAN_KE_ANGKA = {
    "januari": 1, "jan": 1, "februari": 2, "feb": 2, "pebruari": 2, "peb": 2,
    "maret": 3, "mar": 3, "april": 4, "apr": 4, "mei": 5, "juni": 6, "jun": 6,
    "juli": 7, "jul": 7, "agustus": 8, "agu": 8, "ags": 8, "agt": 8, "aug": 8,
    "september": 9, "sep": 9, "oktober": 10, "okt": 10, "oct": 10,
    "november": 11, "nov": 11, "desember": 12, "des": 12, "dec": 12,
}
NAMA_BULAN = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni",
              "Juli", "Agustus", "September", "Oktober", "November", "Desember"]


def _bulan_dari_teks(teks: str) -> Optional[int]:
    """Cari nama bulan Indonesia/Inggris di dalam sebuah teks → nomor bulan."""
    kata = re.findall(r"[A-Za-z]+", str(teks).lower())
    for k in kata:
        if k in BULAN_KE_ANGKA:
            return BULAN_KE_ANGKA[k]
    return None


def _is_label_bulan(teks) -> bool:
    """TRUE bila sebuah HEADER kolom adalah label bulan murni.

    Didukung: "Mei", "Juni 2026", "Des…", "Jan-26", "Jan'26", "Harga Jan",
    "2026-01", "2026-01-01" (header bertipe tanggal), "01/2026".
    Angka bulan polos ("1"…"12") DIPERLUIKAN terpisah di inspect_file
    karena berisiko salah tangkap (bisa jadi kolom kode/no).
    """
    s = str(teks).strip().lower()
    if not s:
        return False
    # Header bertipe tanggal/datetime: 2026-01-01 00:00:00 / 2026-01-01 / 2026-01
    m = re.match(r"^(20\d{2}|19\d{2})[-/](\d{1,2})(?:[-/]\d{1,2})?", s)
    if m and 1 <= int(m.group(2)) <= 12:
        return True
    if re.fullmatch(r"\d{4}[-/ ]\d{1,2}", s):
        return True
    # "mei", "juni", "mei 2026", "mei-2026", "mei/2026", "jan-26", "jan'26"
    return _bulan_dari_teks(s) is not None


def _parse_sel_bulan(v, tahun_default: int):
    """Ubah satu sel waktu menjadi (tahun, bulan) atau None.

    Mendukung: datetime, "2026-05", "05/2026", "Mei 2026", "Mei", "Mei-26",
    serta ANGKA BULAN POLOS 1–12 (header "1".."12" pada ekspor SHPED —
    tahunnya diambil dari tahun_default).
    """
    if v is None or (isinstance(v, float) and math.isnan(v)):
        return None
    if isinstance(v, (pd.Timestamp,)) or "Timestamp" in type(v).__name__:
        return (v.year, v.month)

    s = str(v).strip()
    if s == "" or s.lower() in ("nan", "none", "nat", "-"):
        return None

    # ANGKA BULAN POLOS "1".."12" → pakai tahun_default
    # (konsisten dengan tebakan inspect_file yang menerima header angka)
    if re.fullmatch(r"\d{1,2}", s):
        n = int(s)
        if 1 <= n <= 12:
            return (int(tahun_default or 2026), n)
        return None

    # 2026-05  /  2026/05  /  2026-05-01  /  "2026-05-01 00:00:00" (header datetime)
    m = re.match(r"^(\d{4})\s*[-/]\s*(\d{1,2})(?:\s*[-/]\s*\d{1,2})?(?:[ T].*)?$", s)
    if m and 1 <= int(m.group(2)) <= 12:
        return (int(m.group(1)), int(m.group(2)))
    # 05-2026  /  05/2026
    m = re.match(r"^(\d{1,2})\s*[-/]\s*(\d{4})$", s)
    if m and 1 <= int(m.group(1)) <= 12:
        return (int(m.group(2)), int(m.group(1)))

    bulan = _bulan_dari_teks(s)
    if bulan is None:
        return None
    m = re.search(r"(20\d{2}|19\d{2})", s)          # tahun 4 digit di teks
    tahun = int(m.group(1)) if m else int(tahun_default or 2026)
    return (tahun, bulan)


# ------------------------- Pembersihan angka -------------------------
def _clean_number(v):
    """Ubah berbagai format angka menjadi float (atau None bila kosong)."""
    if v is None:
        return None
    if isinstance(v, (int, float)) and not isinstance(v, bool):
        return float(v) if math.isfinite(float(v)) else None

    s = str(v).strip()
    if s == "" or s.lower() in ("nan", "none", "nat", "-", "null"):
        return None

    s = re.sub(r"(?i)rp\.?", "", s)            # buang "Rp"
    s = re.sub(r"[^\d.,\-]", "", s).strip()    # buang karakter lain ("/kg" dll)
    if s in ("", "-", ".", ","):
        return None

    neg = s.startswith("-")
    s = s.lstrip("-")

    if "," in s and "." in s:
        # Pemisah desimal = tanda yang muncul PALING KANAN
        if s.rfind(",") > s.rfind("."):        # 1.234.567,89 (Indonesia)
            s = s.replace(".", "").replace(",", ".")
        else:                                  # 1,234,567.89 (Inggris)
            s = s.replace(",", "")
    elif "," in s:
        parts = s.split(",")
        if len(parts) == 2 and len(parts[1]) != 3:
            s = s.replace(",", ".")            # koma = desimal, mis. "12,5"
        else:
            s = s.replace(",", "")             # koma = ribuan
    elif "." in s:
        parts = s.split(".")
        if len(parts) > 2:
            s = "".join(parts)                 # 1.234.567 → ribuan
        elif len(parts[1]) == 3:
            s = "".join(parts)                 # 12.500 → 12500 (gaya harga)
        # selain itu biarkan sebagai desimal, mis. "12.5"

    try:
        out = float(s)
    except ValueError:
        return None
    return -out if neg else out


# ------------------------- Pembersihan tanggal (mode baris) -------------------------
def _rp(n: float) -> str:
    """Format angka gaya Indonesia: 1234567 → '1.234.567'."""
    return f"{n:,.0f}".replace(",", ".")


def _parse_dates(s: pd.Series) -> pd.Series:
    """Konversi kolom waktu menjadi datetime; dukung format campuran."""
    def _valid(dt: pd.Series) -> int:
        return int((~dt.isna()).sum())

    with warnings.catch_warnings():
        warnings.simplefilter("ignore")          # kolom nama bulan → dateutil gagal, wajar
        try:
            dt_iso = pd.to_datetime(s, errors="coerce")
        except (TypeError, ValueError):
            dt_iso = pd.Series(pd.NaT, index=s.index)

        try:
            dt_day = pd.to_datetime(s, errors="coerce", dayfirst=True, format="mixed")
        except (TypeError, ValueError):
            try:
                dt_day = pd.to_datetime(s, errors="coerce", dayfirst=True)
            except (TypeError, ValueError):
                dt_day = pd.Series(pd.NaT, index=s.index)

    dt = dt_day if _valid(dt_day) > _valid(dt_iso) else dt_iso

    mask = dt.isna()
    if bool(mask.any()):
        for fmt in ("%d/%m/%Y", "%Y-%m-%d", "%d-%m-%Y", "%d %b %Y", "%d %B %Y", "%d/%m/%y"):
            try:
                with warnings.catch_warnings():
                    warnings.simplefilter("ignore")
                    dt2 = pd.to_datetime(s[mask], format=fmt, errors="coerce")
            except Exception:
                continue
            dt.loc[mask] = dt2
            mask = dt.isna()
            if not bool(mask.any()):
                break
    return dt


def _parse_waktu_long(s: pd.Series, tahun_default: int) -> pd.Series:
    """Kolom waktu mode baris: tanggal penuh ATAU nama bulan ("Mei", "Mei 2026").

    Dipilih strategi yang paling banyak berhasil mem-parse seluruh kolom.
    """
    dt_tanggal = _parse_dates(s)

    parsed_bulan = s.map(lambda v: _parse_sel_bulan(v, tahun_default))
    valid_bulan = int(parsed_bulan.map(lambda x: x is not None).sum())
    valid_tanggal = int((~dt_tanggal.isna()).sum())

    if valid_bulan > valid_tanggal:
        # Kolom ini berisi nama bulan → tanggal = tanggal 1 bulan tersebut
        return parsed_bulan.map(
            lambda x: pd.Timestamp(year=x[0], month=x[1], day=1) if x else pd.NaT)
    return dt_tanggal


# ------------------------- Pembacaan file -------------------------
def load_table(path: str) -> pd.DataFrame:
    """Baca CSV (csv+pandas, deteksi pemisah otomatis) atau XLSX (openpyxl)."""
    ext = os.path.splitext(path)[1].lower()
    if ext == ".csv":
        try:
            df = pd.read_csv(path, sep=None, engine="python", dtype=str)
        except UnicodeDecodeError:
            df = pd.read_csv(path, sep=None, engine="python", dtype=str, encoding="latin-1")
    elif ext == ".xlsx":
        df = pd.read_excel(path, engine="openpyxl", dtype=str)
    else:
        raise ValueError(f"Format .{ext} tidak didukung (gunakan .csv atau .xlsx)")

    if df.empty:
        raise ValueError("File kosong / tidak memiliki baris data.")
    # Rapikan nama kolom: buang BOM UTF-8 (file ekspor sering berawalan \ufeff,
    # mis. "\ufeffTahun" → tanpa ini kolom "Tahun" tidak dikenali dan tahun
    # default salah) beserta spasi di sekelilingnya.
    df.columns = [str(c).replace("\ufeff", "").strip() for c in df.columns]
    return df


# ------------------------- Inspeksi (preview + tebakan peran) -------------------------
def inspect_file(path: str, filename: str = "") -> dict:
    """Kirim daftar kolom, preview 5 baris, dan tebakan mapping otomatis
    (peran tiap kolom + deteksi format baris/lebar).

    Tebakan dirancang untuk struktur asli ekspor SHPED/SPHPD (Badan Pangan
    Nasional): Tahun, Id Dok, Kuesioner, Prov, Kab, Kec, Nama Kec, Kode Kom,
    Nama Kom, Status Kom, Kode/Nama Kualitas, Jan-26 … Des-26.
    """
    df = load_table(path)
    cols = list(df.columns)
    tahun_kini = pd.Timestamp.now().year

    # --- Deteksi kolom bulan (mode lebar) ---
    # Kolom berakhiran "Prev" (ekspor SHPED: "Januari Prev" = harga bulan
    # sebelumnya) TIDAK dianggap kolom bulan — bila ikut dipilih, harga bulan
    # tercampur harga bulan sebelumnya. Tetap bisa dipilih manual bila perlu.
    month_cols = [c for c in cols
                  if _is_label_bulan(c) and not c.strip().lower().endswith("prev")]

    # Header angka polos "1"…"12" dianggap bulan HANYA bila ada ≥ 2 berurutan
    # (mencegah kolom kode/no ikut tertangkap).
    angka_bulan = [c for c in cols if re.fullmatch(r"\d{1,2}", str(c).strip())
                   and 1 <= int(str(c).strip()) <= 12]
    if len(angka_bulan) >= 2 and not month_cols:
        month_cols = angka_bulan

    # --- Tebakan peran berdasarkan nama kolom ---
    def cari(kata_kunci, kecuali=()):
        """Kolom pertama yang memuat salah satu kata kunci (urut prioritas)."""
        for k in kata_kunci:                      # prioritas kata kunci dulu
            for c in cols:
                if c in month_cols or c in kecuali:
                    continue
                if k in c.lower():
                    return c
        return None

    # Kolom identitas yang bukan kandidat komoditas (kode, wilayah admin, dll.)
    bukan_kom = ("kode", "id dok", "iddok", "kuesioner", "prov", "kab",
                 "kota/kab", "tahun", "status", "kec", "dok", "no")

    kandidat_kom = cari(("nama kom", "komoditas", "nama barang", "barang",
                         "commodity", "nama bahan", "jenis"))
    if kandidat_kom is None:
        # Fallback: kolom teks pertama yang "mirip nama" dan bukan kolom
        # administratif/identitas, bulan, harga, atau waktu.
        for c in cols:
            if c in month_cols:
                continue
            low = c.lower()
            if any(b in low for b in bukan_kom):
                continue
            vals = df[c].dropna().astype(str).head(30)
            if len(vals) and sum(v.isalpha() or " " in v for v in vals) / len(vals) > 0.5:
                kandidat_kom = c
                break

    guess = {
        "mode": "wide" if len(month_cols) >= 2 else "long",
        "komoditas_col": kandidat_kom,
        # "Nama Kec" / "Kecamatan" diprioritaskan di atas kode "Kec"
        "kecamatan_col": cari(("nama kec", "kecamatan", "kelurahan", "desa",
                               "kota", "wilayah", "daerah", "lokasi", "region")),
        "kualitas_col":  cari(("kualitas", "mutu", "grade", "spesifikasi", "quality")),
        "kuesioner_col": cari(("kuesioner", "kode kuesioner", "no kuesioner")),
        "satuan_col":    cari(("satuan", "unit")),
        "value_col":     cari(("harga", "price", "nilai", "value")),
        "time_col":      cari(("tanggal", "periode", "waktu", "date", "bulan")),
        "month_cols":    month_cols,
        "tahun_default": tahun_kini,
    }

    # Kecamatan: bila kolom "Nama Kec" tidak ada, barter teranggap kode "Kec"
    # (tetap berguna — nilainya yang dipakai). Tapi JANGAN memakai kolom yang
    # sama dengan komoditas.
    if guess["kecamatan_col"] == guess["komoditas_col"]:
        guess["kecamatan_col"] = None

    # Harga/waktu tidak boleh menabrak kolom komoditas/kualitas/kecamatan
    for role in ("value_col", "time_col"):
        if guess[role] in (guess["komoditas_col"], guess["kualitas_col"],
                           guess["kecamatan_col"]):
            guess[role] = None

    # --- Tahun default dari kolom "Tahun" (sesuai isi file, bukan tebakan) ---
    col_tahun = next((c for c in cols if c.strip().lower() in ("tahun", "year")), None)
    if col_tahun:
        vals = pd.to_numeric(df[col_tahun], errors="coerce").dropna()
        if len(vals):
            tahun_file = int(vals.mode().iloc[0])
            if 2000 <= tahun_file <= 2100:
                guess["tahun_default"] = tahun_file

    return {
        "ok": True,
        "file": filename,
        "columns": cols,
        "preview": df.head(5).fillna("").values.tolist(),
        "total_rows": int(len(df)),
        "guess": guess,
    }


# ------------------------- Analisis satu seri (per bulan) -------------------------
def _analyze_series(g: pd.DataFrame, cfg: dict) -> dict:
    """Statistik + deteksi tidur utk 1 seri (komoditas × kualitas × kecamatan).

    g sudah teragregasi per bulan & terurut: kolom [bulan, harga, jumlah_hari].
    ATURAN TIDUR: harga SEMUA bulan sama (konstan) dan data ≥ 3 bulan
    (cfg "min_bulan_tidur"). Konstan hanya 2 bulan → BUKAN data tidur.
    """
    harga = g["harga"].to_numpy(dtype=float)
    bulan = list(g["bulan"])
    n_bulan = len(harga)

    mean = float(np.mean(harga))
    vmin, vmax = float(np.min(harga)), float(np.max(harga))
    min_bulan = int(cfg.get("min_bulan_tidur", 3))
    konstan_semua = math.isclose(vmax, vmin, rel_tol=1e-9, abs_tol=cfg["toleransi_std"])
    konstan = n_bulan >= min_bulan and konstan_semua

    harga_awal, harga_akhir = float(harga[0]), float(harga[-1])
    perubahan = ((harga_akhir - harga_awal) / harga_awal * 100.0) if harga_awal else 0.0

    b0, b1 = bulan[0], bulan[-1]
    label_periode = (f"{NAMA_BULAN[b0.month]}–{NAMA_BULAN[b1.month]} {b1.year}"
                     if b0.month != b1.month or b0.year != b1.year
                     else f"{NAMA_BULAN[b0.month]} {b0.year}")

    if konstan:
        alasan = (f"Harga konstan Rp {_rp(harga_akhir)}/kg "
                  f"selama {n_bulan} bulan ({label_periode})")
    elif n_bulan < 2:
        alasan = "Hanya 1 bulan data — belum dapat dinilai"
    elif konstan_semua:
        # Konstan tapi belum memenuhi durasi minimum (mis. hanya 2 bulan)
        alasan = (f"Harga konstan Rp {_rp(harga_akhir)}/kg selama {n_bulan} bulan "
                  f"({label_periode}) — konstan kurang dari {min_bulan} bulan, "
                  f"tidak dihitung data tidur")
    else:
        alasan = (f"Harga bergerak (terendah Rp {_rp(vmin)}, "
                  f"tertinggi Rp {_rp(vmax)})")

    # Tanggal periode: awal bulan pertama s.d. akhir bulan terakhir
    mulai = pd.Timestamp(year=b0.year, month=b0.month, day=1)
    selesai = pd.Timestamp(year=b1.year, month=b1.month, day=calendar.monthrange(b1.year, b1.month)[1])

    return {
        "jumlah_data": int(g["jumlah_hari"].sum()),   # total baris harga pada seri ini
        "jumlah_bulan": n_bulan,
        "harga_awal": round(harga_awal, 2),
        "harga_akhir": round(harga_akhir, 2),
        "harga_min": round(vmin, 2),
        "harga_max": round(vmax, 2),
        "perubahan_persen": round(perubahan, 2),
        "is_tidur": bool(konstan),
        "alasan_tidur": alasan,
        "bulan_mulai": mulai.strftime("%Y-%m-%d"),
        "bulan_selesai": selesai.strftime("%Y-%m-%d"),
        "titik": [[b.strftime("%Y-%m-%d"), round(float(p), 2)] for b, p in zip(bulan, harga)],
    }


# ------------------------- Analisis seluruh file -------------------------
def analyze_file(path: str, mapping: dict, cfg: Optional[dict] = None) -> dict:
    """Alur utama: baca → normalisasi (baris/lebar) → agregasi bulanan → analisis → ringkasan."""
    cfg = {**DEFAULT_CFG, **(cfg or {})}
    df = load_table(path)
    total_baris = int(len(df))

    mode = (mapping.get("mode") or "long").lower()
    tahun_default = int(mapping.get("tahun_default") or pd.Timestamp.now().year)

    def kolom(nama_role):
        c = mapping.get(nama_role)
        return c if c and c in df.columns else None

    c_kom = kolom("komoditas_col")
    c_kua = kolom("kualitas_col")
    c_kec = kolom("kecamatan_col")
    c_sat = kolom("satuan_col")
    c_val = kolom("value_col")
    c_kues = kolom("kuesioner_col")   # opsional: kode kuesioner (mis. HKD-1, HKD-2.1)

    if not c_kom:
        raise ValueError("Kolom komoditas wajib dipilih dan harus ada di file.")

    # ---- Normalisasi ke struktur panjang: 1 baris = 1 harga 1 bulan ----
    default_ident = {"kualitas": "Standar", "kecamatan": "Tobelo", "satuan": "/kg"}

    if mode == "wide":
        month_cols = [c for c in (mapping.get("month_cols") or []) if c in df.columns]
        if not month_cols:
            raise ValueError("Mode lebar membutuhkan minimal 1 kolom bulan (Mei, Juni, …).")
        id_cols = [c for c in (c_kom, c_kua, c_kec, c_sat, c_kues) if c]
        work = df.melt(id_vars=id_cols, value_vars=month_cols,
                       var_name="label_bulan", value_name="harga")
        work["harga"] = work["harga"].map(_clean_number)
        sel = work["label_bulan"].map(lambda v: _parse_sel_bulan(v, tahun_default))
        work["tanggal"] = sel.map(
            lambda x: pd.Timestamp(year=x[0], month=x[1], day=1) if x else pd.NaT)
        work = work.drop(columns=["label_bulan"])
    else:
        if not c_val:
            raise ValueError("Kolom harga wajib dipilih untuk format baris.")
        c_time = kolom("time_col")
        if not c_time:
            raise ValueError("Kolom waktu (tanggal/bulan) wajib dipilih untuk format baris.")
        work = pd.DataFrame(index=df.index)
        work["harga"] = df[c_val].map(_clean_number)
        work["tanggal"] = _parse_waktu_long(df[c_time], tahun_default)

    # ---- Kolom identitas (dengan nilai default bila tidak ada di file) ----
    ident_cols = {"komoditas": c_kom, "kualitas": c_kua,
                  "kecamatan": c_kec, "satuan": c_sat}
    for role, col in ident_cols.items():
        if col:
            src = work[col] if mode == "wide" else df[col]
            work[role] = src.astype(str).str.strip()
        else:
            work[role] = default_ident.get(role, "-")

    work["komoditas"]  = work["komoditas"].replace({"": "-", "nan": "-", "None": "-"})
    work["kualitas"]   = work["kualitas"].replace({"": "Standar", "-": "Standar",
                                                   "nan": "Standar", "None": "Standar"})
    work["kecamatan"]  = work["kecamatan"].replace({"": "Tobelo", "-": "Tobelo",
                                                    "nan": "Tobelo", "None": "Tobelo"})
    work["satuan"]     = work["satuan"].replace({"": "/kg", "-": "/kg",
                                                 "nan": "/kg", "None": "/kg"})

    # ---- Kolom kuesioner (atribut komoditas, opsional — tanpa nilai default) ----
    if c_kues:
        src_kues = work[c_kues] if mode == "wide" else df[c_kues]
        work["kuesioner"] = src_kues.astype(str).str.strip()
        work["kuesioner"] = work["kuesioner"].replace(
            {"": None, "-": None, "nan": None, "None": None, "NaN": None})
    else:
        work["kuesioner"] = None

    work = work.dropna(subset=["harga", "tanggal"])
    if work.empty:
        # ---- Diagnosis spesifik: mana yang gagal, harga atau label bulan? ----
        def contoh(nilai_list, n=3):
            unik = []
            for v in nilai_list:
                t = str(v).strip()
                if t and t.lower() not in ("nan", "none", "nat", "-") and t not in unik:
                    unik.append(t)
                if len(unik) >= n:
                    break
            return ", ".join(f"\"{u}\"" for u in unik) or "(kosong)"

        if mode == "wide":
            n_sel = int(len(df) * max(len(month_cols), 1))
            n_harga = int(df[month_cols].apply(lambda c: c.map(_clean_number).notna()).sum().sum())
            n_tgl = sum(1 for c in month_cols if _parse_sel_bulan(c, tahun_default))
            label_gagal = [c for c in month_cols if not _parse_sel_bulan(c, tahun_default)]
            detail = (f"Tidak ada baris valid. Diagnosis: {n_harga} dari {n_sel} sel harga terbaca"
                      f"{'' if n_harga else ' — isian tidak dikenali (contoh: ' + contoh(df[month_cols].values.ravel()) + ')'}. "
                      f"Label kolom bulan dikenali {n_tgl}/{len(month_cols)}"
                      f"{'; tidak dikenali: ' + ', '.join(label_gagal[:6]) if label_gagal else ''}. ")
        else:
            n_harga = int(df[c_val].map(_clean_number).notna().sum())
            n_tgl = int(_parse_waktu_long(df[c_time], tahun_default).notna().sum())
            detail = (f"Tidak ada baris valid. Diagnosis: kolom harga '{c_val}' terbaca {n_harga}/{len(df)}"
                      f"{'' if n_harga else ' — contoh isian: ' + contoh(df[c_val].tolist())}; "
                      f"kolom waktu '{c_time}' terbaca {n_tgl}/{len(df)}"
                      f"{'' if n_tgl else ' — contoh isian: ' + contoh(df[c_time].tolist())}. ")
        detail += "Periksa kembali pilihan kolom, atau rapikan format angka/header di file."
        raise ValueError(detail)

    # ---- Agregasi per bulan: rata-rata harga tiap bulan ----
    work["bulan"] = work["tanggal"].values.astype("datetime64[M]")
    bulanan = (work.groupby(["komoditas", "kualitas", "kecamatan", "satuan", "bulan"], as_index=False)
                   .agg(harga=("harga", "mean"), jumlah_hari=("harga", "size"),
                        kuesioner=("kuesioner", "first"))
                   .sort_values(["komoditas", "kualitas", "kecamatan", "bulan"]))

    # ---- Analisis per seri ----
    seri_list = []
    for (kom, kua, kec, sat), g in bulanan.groupby(["komoditas", "kualitas", "kecamatan", "satuan"], sort=True):
        stat = _analyze_series(g, cfg)
        kues = g["kuesioner"].iloc[0]
        if kues is not None and str(kues).strip().lower() in ("", "nan", "none"):
            kues = None
        seri_list.append({"komoditas": kom, "kualitas": kua, "kecamatan": kec,
                          "satuan": sat, "kuesioner": kues, **stat})

    # ---- Ringkasan ----
    komoditas_unik = {(s["komoditas"], s["kualitas"]) for s in seri_list}
    tidur_komoditas = {k for k in komoditas_unik
                       if all(s["is_tidur"] for s in seri_list
                              if (s["komoditas"], s["kualitas"]) == k)}
    b_min = bulanan["bulan"].min()
    b_max = bulanan["bulan"].max()

    ringkasan = {
        "total_baris": total_baris,
        "total_seri": len(seri_list),
        "total_komoditas": len(komoditas_unik),          # sesuai isi Excel
        "total_tidur": len(tidur_komoditas),
        "total_bergerak": len(komoditas_unik) - len(tidur_komoditas),
        "periode_mulai": pd.Timestamp(year=b_min.year, month=b_min.month, day=1).strftime("%Y-%m-%d"),
        "periode_selesai": pd.Timestamp(year=b_max.year, month=b_max.month,
                                        day=calendar.monthrange(b_max.year, b_max.month)[1]).strftime("%Y-%m-%d"),
        "jumlah_bulan": int(bulanan["bulan"].nunique()),
    }

    return {"ok": True, "cfg": cfg, "ringkasan": ringkasan, "seri": seri_list}
