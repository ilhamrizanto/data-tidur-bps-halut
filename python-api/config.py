"""
config.py — Konfigurasi layanan analisis Python (FastAPI).
"""

# ---------- Koneksi MySQL (harus sama dengan app/config/config.php) ----------
# LOKAL (XAMPP)  : biarkan seperti di bawah.
# HOSTING cPanel : ganti sesuai kredensial MySQL cPanel Anda (prefix akun,
#                  mis. thek3726_...) — buat lewat menu MySQL® Databases,
#                  host umumnya "localhost":
#   "host": "localhost",
#   "user": "thek3726_tidur",
#   "password": "password-db-anda",
#   "database": "thek3726_db_data_tidur",
DB = {
    "host": "127.0.0.1",
    "user": "root",
    "password": "",
    "database": "db_data_tidur",
}

# ---------- Folder upload PHP (tempat FastAPI membaca file) ----------
# OTOMATIS mengikuti lokasi folder python-api ini:
#   <proyek>/python-api/config.py  →  folder upload PHP ada di <proyek>/app/uploads
# Jadi tidak perlu mengubah apa pun lagi, walau nama folder proyek atau
# lokasi htdocs berbeda-beda (mis. C:\xampp\htdocs\deteksi-data-tidur).
import os

_THIS_DIR    = os.path.dirname(os.path.abspath(__file__))   # .../deteksi-data-tidur/python-api
_PROJECT_DIR = os.path.dirname(_THIS_DIR)                   # .../deteksi-data-tidur

_UPLOAD_CANDIDATES = [
    os.path.join(_PROJECT_DIR, "app", "uploads"),           # 1) struktur bawaan proyek (utama)
    os.path.join(_PROJECT_DIR, "uploads"),                  # 2) bila isi app/ disalin satu tingkat
    r"C:\xampp\htdocs\deteksi-data-tidur\app\uploads",      # 3) path XAMPP Windows umum
    r"C:\xampp\htdocs\data-tidur\uploads",                  # 4) pola lama (README versi awal)
    "/opt/lampp/htdocs/data-tidur/uploads",                 # 5) XAMPP Linux
    "/Applications/XAMPP/htdocs/data-tidur/uploads",        # 6) XAMPP macOS
]
# Pilih kandidat pertama yang benar-benar ada; bila tidak ada yang ada,
# pakai kandidat utama (health akan menampilkan statusnya untuk diagnosis).
UPLOAD_DIR = next((p for p in _UPLOAD_CANDIDATES if os.path.isdir(p)), _UPLOAD_CANDIDATES[0])

# ---------- Batasan file ----------
ALLOWED_EXT = {"csv", "xlsx"}
