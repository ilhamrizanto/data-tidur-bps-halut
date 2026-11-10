@echo off
REM =====================================================
REM  jalankan_api.bat — Menjalankan layanan analisis FastAPI
REM  Untuk Windows. Jalankan SEBELUM melakukan upload data.
REM =====================================================
title API Deteksi Data Tidur (FastAPI)
cd /d "%~dp0python-api"

echo.
echo [1/3] Membersihkan proses lama yang masih memakai port 8000...
REM  Jika ada uvicorn/proses lama yang masih hidup di port 8000, kode terbaru
REM  tidak akan pernah aktif dan unggahan bisa gagal. Matikan dulu.
for /f "tokens=5" %%p in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do (
    echo       Mematikan proses lama PID %%p ...
    taskkill /PID %%p /F >nul 2>&1
)

if not exist .venv (
  echo [2/3] Membuat virtual environment + instal dependensi...
  python -m venv .venv
  call .venv\Scripts\activate.bat
  python -m pip install --upgrade pip
  pip install -r requirements.txt
) else (
  call .venv\Scripts\activate.bat
)

echo [3/3] Menjalankan API di http://127.0.0.1:8000 ...
echo       ^> Biarkan jendela ini TERBUKA selagi aplikasi dipakai.
echo       ^> Jika muncul "Uvicorn running on http://127.0.0.1:8000" berarti sukses.
echo.
python -m uvicorn main:app --host 127.0.0.1 --port 8000

echo.
echo API berhenti. Bila ada pesan error di atas, baca pesannya lalu jalankan lagi.
pause
