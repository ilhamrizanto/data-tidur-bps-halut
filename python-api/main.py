"""
main.py — Layanan analisis "Deteksi Data Tidur" (FastAPI).

Endpoint:
  GET  /health   → cek layanan hidup
  POST /inspect  → upload file (multipart) → daftar kolom + preview + tebakan peran
  POST /analyze  → body JSON {file_path, upload_id, mapping, cfg?}
                   → baca file (mode baris / lebar) → analisis Pandas/NumPy
                   → simpan ke MySQL → kembalikan ringkasan
  GET  /export   → ?upload_id=&format=xlsx|csv → file unduhan data lengkap
                   (dipakai tombol unduh di halaman publik)

Menjalankan (dari folder python-api):
  python -m uvicorn main:app --host 127.0.0.1 --port 8000

Diuji dengan Python 3.15 (rilis stabil terbaru) + FastAPI 0.162.
"""

import copy
import io
import os
import platform
import shutil
import tempfile
from typing import Any, Dict, List, Optional

import pandas as pd
from fastapi import FastAPI, File, HTTPException, Response, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from pydantic import BaseModel

import analyzer
import db as dbm
from config import UPLOAD_DIR

app = FastAPI(title="API Deteksi Data Tidur", version="2.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],          # dev lokal; perketat untuk produksi
    allow_methods=["*"],
    allow_headers=["*"],
)


# ----------------------------- Model request -----------------------------
class Mapping(BaseModel):
    """Pemetaan peran kolom file Excel/CSV.

    mode='long' : 1 baris = 1 harga → butuh value_col + time_col.
    mode='wide' : 1 baris = 1 komoditas, kolom = bulan → butuh month_cols.
    """
    mode: str = "long"
    komoditas_col: str
    kualitas_col: Optional[str] = None
    kecamatan_col: Optional[str] = None
    satuan_col: Optional[str] = None
    kuesioner_col: Optional[str] = None   # kode kuesioner file SHPED (mis. HKD-1)
    value_col: Optional[str] = None
    time_col: Optional[str] = None
    month_cols: List[str] = []
    tahun_default: Optional[int] = None


class AnalyzeBody(BaseModel):
    file_path: str
    upload_id: int
    mapping: Mapping
    cfg: Optional[Dict[str, Any]] = None


# ----------------------------- Helper -----------------------------
def _dump_mapping(m: Mapping) -> dict:
    """Kompatibel pydantic v1 dan v2."""
    try:
        return m.model_dump()
    except AttributeError:
        return m.dict()


def _safe_path(p: str) -> str:
    """Hanya izinkan membaca file di dalam folder uploads PHP (anti path traversal)."""
    real = os.path.realpath(p)
    base = os.path.realpath(UPLOAD_DIR)
    if not real.startswith(base + os.sep) and real != base:
        # Pesan menyertakan base agar salah path langsung terlihat saat debugging.
        raise HTTPException(
            400,
            f"Path file tidak diizinkan. Folder yang diizinkan: {base}. "
            "Periksa UPLOAD_DIR di python-api/config.py (lihat /health).",
        )
    if not os.path.exists(real):
        raise HTTPException(404, "File tidak ditemukan di server (periksa UPLOAD_DIR di config.py).")
    return real


def _label_bulan(iso: str) -> str:
    """'2026-05-01' → 'Mei 2026'."""
    d = pd.Timestamp(iso)
    return f"{analyzer.NAMA_BULAN[d.month]} {d.year}"


# ----------------------------- Endpoint -----------------------------
@app.get("/")
def root():
    """Halaman depan port 8000 — supaya kalau alamat ini dibuka di browser,
    terlihat jelas inilah layanan analisis (bukan aplikasi web PHP)."""
    return {
        "layanan": "API Deteksi Data Tidur (FastAPI)",
        "pesan": "Alamat ini adalah layanan analisis, BUKAN halaman aplikasi. "
                 "Aplikasi web dibuka di http://localhost/deteksi-data-tidur/ (Apache, port 80).",
        "endpoint": ["GET /health", "POST /inspect", "POST /analyze", "GET /export", "GET /docs"],
        "dokumentasi_interaktif": "/docs",
    }


@app.get("/health")
def health():
    return {
        "ok": True,
        "service": "deteksi-data-tidur",
        "version": "2.0.0",
        "python": platform.python_version(),   # bukti versi Python terbaru
        # Info diagnosis path — bila /analyze gagal 400, bandingkan folder ini
        # dengan path yang dikirim PHP (app/config/config.php → UPLOAD_DIR).
        "uploads_dir": UPLOAD_DIR,
        "uploads_dir_ada": os.path.isdir(UPLOAD_DIR),
    }


@app.get("/favicon.ico")
def favicon():
    """Hilangkan noise 'GET /favicon.ico 404' di log uvicorn (request browser)."""
    return Response(status_code=204)


@app.post("/inspect")
async def inspect(file: UploadFile = File(...)):
    ext = os.path.splitext(file.filename or "")[1].lower().lstrip(".")
    if ext not in ("csv", "xlsx"):
        raise HTTPException(400, "Format harus .csv atau .xlsx")

    tmp = tempfile.NamedTemporaryFile(delete=False, suffix="." + ext)
    try:
        shutil.copyfileobj(file.file, tmp)
        tmp.close()  # ditutup sebelum dibaca (penting di Windows)
        try:
            return analyzer.inspect_file(tmp.name, file.filename)
        except HTTPException:
            raise
        except Exception as e:
            raise HTTPException(500, f"Gagal membaca file: {e}")
    finally:
        try:
            os.unlink(tmp.name)
        except OSError:
            pass


@app.post("/analyze")
def analyze(body: AnalyzeBody):
    path = _safe_path(body.file_path)

    try:
        result = analyzer.analyze_file(path, _dump_mapping(body.mapping), body.cfg)
    except HTTPException:
        raise
    except ValueError as e:
        # Mapping/isi file tidak valid → tandai "Gagal" di riwayat unggahan
        # agar tidak menggantung berstatus "Proses" selamanya.
        try:
            dbm.mark_status(body.upload_id, "failed", str(e))
        except Exception:
            pass
        raise HTTPException(422, str(e))
    except Exception as e:
        try:
            dbm.mark_status(body.upload_id, "failed", f"Gagal menganalisis: {e}")
        except Exception:
            pass
        raise HTTPException(500, f"Gagal menganalisis: {e}")

    if result["ringkasan"]["total_seri"] == 0:
        try:
            dbm.mark_status(body.upload_id, "failed", "Tidak ada seri data valid — periksa mapping kolom.")
        except Exception:
            pass
        raise HTTPException(422, "Tidak ada seri data valid. Periksa pilihan kolom dan isi file.")

    try:
        dbm.save_results(body.upload_id, result)
    except Exception as e:
        try:
            dbm.mark_status(body.upload_id, "failed", str(e))
        except Exception:
            pass
        raise HTTPException(500, f"Gagal menyimpan ke database: {e}")

    # Titik harga sudah tersimpan di MySQL — jangan kirim ulang (respons ringan)
    resp = copy.deepcopy(result)
    for s in resp["seri"]:
        s.pop("titik", None)
    return resp


@app.get("/export")
def export(upload_id: int, format: str = "xlsx"):
    """Unduh seluruh data (harga bulanan + status tidur) sebagai XLSX/CSV.

    Endpoint PUBLIK — dipakai tombol unduh di halaman landing (sisi user).
    """
    try:
        rows = dbm.fetch_export_rows(upload_id)
    except Exception as e:
        raise HTTPException(500, f"Gagal membaca data: {e}")
    if not rows:
        raise HTTPException(404, "Data tidak ditemukan untuk upload_id tersebut.")

    df = pd.DataFrame(rows)
    df["bulan"] = df["bulan"].astype(str).map(_label_bulan)
    df["status"] = df["is_tidur"].map(lambda x: "Data Tidur" if x else "Harga Bergerak")
    df["harga"] = df["harga"].map(lambda x: int(x) if float(x).is_integer() else round(float(x), 2))
    df["perubahan_persen"] = df["perubahan_persen"].map(lambda x: f"{float(x):.2f}%")
    df = df.rename(columns={
        "komoditas": "Komoditas", "kualitas": "Kualitas", "kecamatan": "Kecamatan",
        "satuan": "Satuan", "bulan": "Bulan", "harga": "Harga",
        "perubahan_persen": "Perubahan", "status": "Status",
    })[["Komoditas", "Kualitas", "Kecamatan", "Satuan", "Bulan", "Harga", "Perubahan", "Status"]]

    nama_file = f"data-shped-{upload_id}"

    if format == "csv":
        # Excel Indonesia: pemisah ';' + BOM UTF-8 agar langsung rapi saat dibuka
        csv_text = df.to_csv(index=False, sep=";")
        return Response(
            content=csv_text.encode("utf-8-sig"),
            media_type="text/csv; charset=utf-8",
            headers={"Content-Disposition": f'attachment; filename="{nama_file}.csv"'},
        )

    # Default: xlsx
    buf = io.BytesIO()
    with pd.ExcelWriter(buf, engine="openpyxl") as w:
        df.to_excel(w, sheet_name="Data SHPED", index=False)
    buf.seek(0)
    return StreamingResponse(
        buf,
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": f'attachment; filename="{nama_file}.xlsx"'},
    )
