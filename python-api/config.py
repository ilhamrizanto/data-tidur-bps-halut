"""
config.py — Konfigurasi layanan analisis Python (FastAPI).
Semua nilai bisa dioverride lewat environment variable (Docker/Dokploy):
  DB_HOST, DB_USER, DB_PASS, DB_NAME, UPLOAD_DIR, CORS_ORIGINS
Tanpa env → perilaku identik dengan setup lokal (XAMPP default).
"""
import os

DB = {
    "host": os.environ.get("DB_HOST", "127.0.0.1"),
    "user": os.environ.get("DB_USER", "root"),
    "password": os.environ.get("DB_PASS", ""),
    "database": os.environ.get("DB_NAME", "db_data_tidur"),
}

# ---------- Folder upload PHP (tempat FastAPI membaca file) ----------
# Prioritas: env UPLOAD_DIR (Docker) → auto-deteksi lokasi lokal (XAMPP).
_THIS_DIR    = os.path.dirname(os.path.abspath(__file__))
_PROJECT_DIR = os.path.dirname(_THIS_DIR)

_UPLOAD_CANDIDATES = [
    os.path.join(_PROJECT_DIR, "uploads"),              # repo root (struktur sekarang)
    os.path.join(_PROJECT_DIR, "app", "uploads"),       # pola lama README versi awal
    r"C:\xampp\htdocs\deteksi-data-tidur\uploads",      # XAMPP Windows umum
    r"C:\xampp\htdocs\data-tidur\uploads",              # pola lama
    "/opt/lampp/htdocs/data-tidur/uploads",             # XAMPP Linux
]
UPLOAD_DIR = os.environ.get("UPLOAD_DIR") or next(
    (p for p in _UPLOAD_CANDIDATES if os.path.isdir(p)), _UPLOAD_CANDIDATES[0]
)

# ---------- CORS (browser TIDAK memanggil FastAPI langsung; kosong = tanpa CORS) ----------
CORS_ORIGINS = [o.strip() for o in os.environ.get("CORS_ORIGINS", "").split(",") if o.strip()]

# ---------- Batasan file ----------
ALLOWED_EXT = {"csv", "xlsx"}
