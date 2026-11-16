#!/bin/bash
# Smoke test end-to-end: login admin -> jalankan analisis contoh -> landing berisi data
# Pemakaian: ADMIN_PASS='...' bash scripts/smoke.sh [BASE_URL]
set -e
BASE="${1:-http://localhost:8090}"
JAR=$(mktemp)

echo "== 1) Ambil CSRF halaman login =="
CSRF=$(curl -s -c "$JAR" "$BASE/login.php" | grep -oP 'name="_csrf" value="\K[^"]+')
[ -n "$CSRF" ] || { echo "GAGAL: token login tidak ditemukan"; exit 1; }
echo "csrf login: ${CSRF:0:8}..."

echo "== 2) Login admin =="
CODE=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w "%{http_code}" \
  -d "_csrf=$CSRF&username=${ADMIN_USER:-admin}&password=$ADMIN_PASS" "$BASE/login.php")
echo "HTTP $CODE (harus 302)"

echo "== 3) Ambil CSRF sesi dashboard =="
CSRF2=$(curl -s -b "$JAR" "$BASE/dashboard.php" | grep -oP 'name="csrf-token" content="\K[^"]+')
[ -n "$CSRF2" ] || { echo "GAGAL: token dashboard tidak ditemukan"; exit 1; }
echo "csrf dashboard: ${CSRF2:0:8}..."

echo "== 4) Analisis data contoh (api/sample.php) =="
curl -s -b "$JAR" -H "X-CSRF-Token: $CSRF2" "$BASE/api/sample.php" | head -c 200; echo

echo "== 5) Landing wajib membawa 55 seri / 20 tidur =="
curl -s "$BASE/api/data.php?action=landing" | python -c "
import sys, json
d = json.load(sys.stdin)['data']['ringkasan']
print(d)
assert d['total_seri'] == 55 and d['total_tidur'] == 20, 'GOLDEN MELESET'
"

echo "== 6) Unduh CSV publik =="
curl -s "$BASE/api/download.php?format=csv" | head -2

echo "SMOKE OK"
