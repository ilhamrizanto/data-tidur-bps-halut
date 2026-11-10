"""
db.py — Penulisan hasil analisis ke MySQL (mysql-connector-python).

Alur save_results():
  1. Hapus hasil lama untuk upload_id yang sama (dukung analisis ulang).
  2. Update baris uploads: statistik ringkasan + status 'done'.
  3. Insert massal harga_bulanan (batch 1000 baris) dan analisis_seri.
  Semua dalam satu transaksi — gagal sebagian = rollback penuh.

fetch_export_rows(): ambil seluruh data (per bulan + status) untuk unduhan.
"""

import mysql.connector

from config import DB


def _cn():
    return mysql.connector.connect(**DB)


def mark_status(upload_id: int, status: str, pesan: str = None):
    """Set status unggahan ('done' | 'failed' | 'processing') + pesan."""
    cn = _cn()
    cur = cn.cursor()
    try:
        cur.execute("UPDATE uploads SET status = %s, pesan = %s WHERE id = %s",
                    (status, pesan, upload_id))
        cn.commit()
    finally:
        cur.close()
        cn.close()


def save_results(upload_id: int, result: dict):
    """Simpan seluruh hasil analisis (ringkasan, seri, harga bulanan) ke database."""
    cn = _cn()
    cur = cn.cursor()
    try:
        r = result["ringkasan"]

        # 1. Bersihkan hasil lama (bila file yang sama dianalisis ulang)
        cur.execute("DELETE FROM harga_bulanan WHERE upload_id = %s", (upload_id,))
        cur.execute("DELETE FROM analisis_seri WHERE upload_id = %s", (upload_id,))

        # 2. Perbarui ringkasan di tabel uploads
        cur.execute(
            """UPDATE uploads SET
                 total_baris = %s, total_seri = %s, total_komoditas = %s,
                 total_tidur = %s, total_bergerak = %s,
                 periode_mulai = %s, periode_selesai = %s,
                 status = 'done', pesan = NULL
               WHERE id = %s""",
            (r["total_baris"], r["total_seri"], r["total_komoditas"],
             r["total_tidur"], r["total_bergerak"],
             r["periode_mulai"], r["periode_selesai"], upload_id))

        # 3a. Insert harga bulanan (batch)
        rows = []
        for s in result["seri"]:
            for tgl, harga in s["titik"]:
                rows.append((upload_id, s["komoditas"], s["kualitas"], s["kecamatan"],
                             s["satuan"], tgl, harga))
        for i in range(0, len(rows), 1000):
            cur.executemany(
                """INSERT INTO harga_bulanan
                     (upload_id, komoditas, kualitas, kecamatan, satuan, bulan, harga)
                   VALUES (%s, %s, %s, %s, %s, %s, %s)""",
                rows[i:i + 1000])

        # 3b. Insert hasil analisis per seri (kuesioner = kode kuesioner file
        #     SHPED, mis. HKD-1 / HKD-2.1 — dipakai dropdown filter kuesioner)
        meta = []
        for s in result["seri"]:
            meta.append((upload_id, s["komoditas"], s["kualitas"], s["kecamatan"],
                         s["satuan"], s.get("kuesioner"),
                         s["jumlah_data"], s["jumlah_bulan"],
                         s["harga_awal"], s["harga_akhir"], s["harga_min"],
                         s["harga_max"], s["perubahan_persen"],
                         1 if s["is_tidur"] else 0, s["alasan_tidur"],
                         s["bulan_mulai"], s["bulan_selesai"]))
        if meta:
            cur.executemany(
                """INSERT INTO analisis_seri
                     (upload_id, komoditas, kualitas, kecamatan, satuan, kuesioner,
                      jumlah_data, jumlah_bulan, harga_awal, harga_akhir,
                      harga_min, harga_max, perubahan_persen, is_tidur,
                      alasan_tidur, bulan_mulai, bulan_selesai)
                   VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
                meta)

        cn.commit()
    except Exception:
        cn.rollback()
        raise
    finally:
        cur.close()
        cn.close()


def fetch_export_rows(upload_id: int):
    """Seluruh data harga bulanan + status tiap seri (untuk fitur unduh user)."""
    cn = _cn()
    cur = cn.cursor(dictionary=True)
    try:
        cur.execute(
            """SELECT h.komoditas, h.kualitas, h.kecamatan, h.satuan,
                      h.bulan, h.harga, a.is_tidur, a.perubahan_persen
                 FROM harga_bulanan h
                 JOIN analisis_seri a
                   ON a.upload_id = h.upload_id
                  AND a.komoditas = h.komoditas
                  AND a.kualitas  = h.kualitas
                  AND a.kecamatan = h.kecamatan
                WHERE h.upload_id = %s
                ORDER BY h.komoditas, h.kualitas, h.kecamatan, h.bulan""",
            (upload_id,))
        return cur.fetchall()
    finally:
        cur.close()
        cn.close()
