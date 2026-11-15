"""
passenger_wsgi.py — Titik masuk WSGI untuk cPanel "Setup Python App" (Passenger).

MENGAPA FILE INI ADA?
  Layanan analisis ditulis dengan FastAPI (protokol ASGI), sedangkan Passenger
  di cPanel menjalankan aplikasi Python dengan protokol WSGI. Paket `a2wsgi`
  menjembatani keduanya, sehingga SEMUA rute tetap sama persis:
      GET  /health   POST /inspect   POST /analyze   GET /export   GET /docs

PENGATURAN DI cPanel → Setup Python App:
  - Python version   : 3.12 atau lebih tinggi (yang tersedia di hosting)
  - Application root : bps-data-tidur/python-api   (folder file ini berada)
  - Application URL  : mis. "api"                   (bebas — catat nilainya!)
  - Application startup file : passenger_wsgi.py    (file ini)
  Lalu:
    1. Klik "Run Pip Install"  → memasang seluruh isi requirements.txt
       (termasuk a2wsgi) ke virtual environment milik aplikasi.
    2. Klik "Restart".
    3. Tes di browser:  https://domain-anda/api/health
       Bila muncul JSON {"ok": true, "service": "deteksi-data-tidur", ...}
       berarti layanan hidup. Lanjutkan dengan mengarahkan API_BASE aplikasi
       PHP ke URL tersebut (lihat app/config/config.php).

Di komputer lokal (XAMPP) file ini TIDAK dipakai — tetap jalankan
jalankan_api.bat seperti biasa (uvicorn langsung, tanpa Passenger).
"""

import os
import sys

# Pastikan modul proyek (main.py, analyzer.py, db.py, config.py) dapat diimpor
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from a2wsgi.asgi import ASGIMiddleware   # pip install a2wsgi
from main import app                      # FastAPI: /health /inspect /analyze /export


class _BaseURIFix:
    """Rapikan PATH_INFO agar rute FastAPI cocok di bawah subpath Passenger.

    Berbagai setup cPanel/Passenger mengirim path dengan pola berbeda:
      a) SCRIPT_NAME=/api  + PATH_INFO=/health      (pola standar — sudah benar)
      b) SCRIPT_NAME=/api  + PATH_INFO=/api/health  (path base ikut terkirim)
      c) SCRIPT_NAME kosong + PATH_INFO=/api/health + env PASSENGER_BASE_URI=/api
    Pola b dan c membuat FastAPI menjawab 404 karena rute tersisa "/api/health".
    Middleware ini membuang prefiks base URI tersebut (dan mengisi SCRIPT_NAME
    secara benar) sehingga ketiga pola sama-sama bekerja.
    """

    def __init__(self, wsgi_app):
        self.wsgi_app = wsgi_app

    def __call__(self, environ, start_response):
        path = environ.get("PATH_INFO", "") or "/"
        base = environ.get("SCRIPT_NAME", "") or environ.get("PASSENGER_BASE_URI", "")
        if base and base != "/" and (path == base or path.startswith(base + "/")):
            environ["PATH_INFO"] = path[len(base):] or "/"
            environ["SCRIPT_NAME"] = base
        return self.wsgi_app(environ, start_response)


# Inilah variabel `application` yang dicari Passenger.
application = _BaseURIFix(ASGIMiddleware(app))
