"""Net karakterisasi untuk analyzer.py — golden values dari sample/contoh_harga.csv.

Data sample: 165 baris = 55 seri (11 komoditas × 5 kecamatan) × 3 bulan
(Mei–Juli 2026). Seri tidur (harga konstan 3 bulan): 4 per kecamatan.
Bila golden meleset, jalankan capture (lihat plan) dan pakai output AKTUAL —
jangan mengubah kode analyzer agar cocok dengan tebakan.
"""
import os

import analyzer

SAMPLE = os.path.abspath(os.path.join(
    os.path.dirname(os.path.abspath(__file__)), "..", "..", "sample", "contoh_harga.csv"))

LONG_MAP = {
    "mode": "long",
    "komoditas_col": "komoditas",
    "kualitas_col": "kualitas",
    "kecamatan_col": "kecamatan",
    "satuan_col": "satuan",
    "value_col": "harga",
    "time_col": "tanggal",
    "month_cols": [],
}


def _analyze():
    return analyzer.analyze_file(SAMPLE, dict(LONG_MAP), None)


def test_sample_long_golden():
    r = _analyze()["ringkasan"]
    assert r["total_baris"] == 165
    assert r["total_seri"] == 55
    assert r["total_komoditas"] == 11
    assert r["total_tidur"] == 20
    assert r["total_bergerak"] == 35
    assert str(r["periode_mulai"])[:10] == "2026-05-01"
    assert str(r["periode_selesai"])[:10] == "2026-07-01"


def test_semua_seri_tidur_punya_alasan():
    for s in _analyze()["seri"]:
        if s["is_tidur"]:
            assert s["alasan_tidur"], f"seri {s['komoditas']} tidur tanpa alasan"
        else:
            assert s["harga_min"] < s["harga_max"]


def test_bulan_kontras_seri_bergerak():
    # Telur Ayam Ras Tobelo: 27600 -> 27350 -> 27350 (konstan 2 bulan terakhir
    # tapi TIDAK seluruh seri) harusnya BERGERAK, bukan tidur.
    s = next(x for x in _analyze()["seri"]
             if x["komoditas"] == "Telur Ayam Ras" and x["kecamatan"] == "Tobelo")
    assert not s["is_tidur"]
