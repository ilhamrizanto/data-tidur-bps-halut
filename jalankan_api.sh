#!/usr/bin/env bash
# =====================================================
#  jalankan_api.sh — Menjalankan layanan analisis FastAPI
#  Untuk Linux / macOS. Jalankan SEBELUM melakukan upload data.
# =====================================================
set -e
cd "$(dirname "$0")/python-api"

if [ ! -d .venv ]; then
  echo "[1/2] Membuat virtual environment + instal dependensi..."
  python3 -m venv .venv
  # shellcheck disable=SC1091
  source .venv/bin/activate
  python -m pip install --upgrade pip
  pip install -r requirements.txt
else
  # shellcheck disable=SC1091
  source .venv/bin/activate
fi

echo "[1/2] Membersihkan proses lama yang masih memakai port 8000..."
pids=$(lsof -t -i :8000 2>/dev/null || true)
if [ -n "$pids" ]; then
  for p in $pids; do echo "      Mematikan proses lama PID $p ..."; kill -9 "$p" 2>/dev/null || true; done
  sleep 1
fi

echo "[2/2] Menjalankan API di http://127.0.0.1:8000 ..."
python -m uvicorn main:app --host 127.0.0.1 --port 8000
