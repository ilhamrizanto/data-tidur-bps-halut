"""
generate_sample.py — Membuat data contoh yang disertakan di app/sample/
(juga dipakai tombol "Coba Data Contoh" di dashboard admin).

Isi (sesuai contoh kasus pengguna):
  • Periode: Mei–Juli 2026 (3 bulan) — harga per BULAN per kecamatan.
  • Wilayah: 5 kecamatan Kabupaten Halmahera Utara
    (Tobelo, Tobelo Barat, Galela, Kao, Wasile Selatan).
  • KONSTAN (→ DATA TIDUR): Beras Premium, Gula Pasir, Garam Beryodium
    — semuanya Rp 30.000/kg Mei–Juli → data tidur terhitung 3.
  • BERGERAK: Beras Medium, Garam Krosok, Minyak Goreng (Kemasan/Curah),
    Telur Ayam Ras, Daging Ayam Ras, Cabai Rawit Merah.
  • Kolom: komoditas, kualitas, kecamatan, satuan, tanggal, harga
    (format BARIS). Juga dibuat contoh_harga_lebar.xlsx (format LEBAR:
    kolom Mei/Juni/Juli) untuk menguji mode lebar.

Jalankan:  python generate_sample.py [folder_tujuan]
"""

import os
import sys

import numpy as np
import pandas as pd

DEFAULT_DIR = os.path.join(os.path.dirname(__file__), "..", "app", "sample")

KECAMATAN = ["Tobelo", "Tobelo Barat", "Galela", "Kao", "Wasile Selatan"]
BULAN = [("2026-05-01", "Mei"), ("2026-06-01", "Juni"), ("2026-07-01", "Juli")]

# (komoditas, kualitas, harga dasar, mode, satuan)
KOMODITAS = [
    ("Beras Premium",       "Premium", 30000, "konstan",    "/kg"),
    ("Gula Pasir",          "Standar", 30000, "konstan",    "/kg"),
    ("Garam Beryodium",     "Standar", 30000, "konstan",    "/kg"),
    ("Beras Medium",        "Medium",  11800, "fluktuasi",  "/kg"),
    ("Garam Krosok",        "Krosok",  12000, "naik",       "/kg"),
    ("Minyak Goreng",       "Kemasan", 17500, "naik",       "/kg"),
    ("Minyak Goreng",       "Curah",   15700, "fluktuasi",  "/kg"),
    ("Telur Ayam Ras",      "Standar", 27500, "fluktuasi",  "/kg"),
    ("Daging Ayam Ras",     "Standar", 38000, "naik",       "/kg"),
    ("Cabai Rawit Merah",   "Standar", 45000, "fluktuasi",  "/kg"),
    ("Terigu Segitiga Biru","Standar", 13000, "konstan",    "/kg"),  # tidur ke-4 (variasi kasus)
]


def main(dest_dir: str):
    os.makedirs(dest_dir, exist_ok=True)
    rng = np.random.default_rng(42)          # seed tetap → hasil dapat direproduksi
    rows = []

    for kec_i, kec in enumerate(KECAMATAN):
        # Kenaikan harga wilayah: Galela/Kao sedikit lebih mahal (akses)
        faktor_kec = 1.0 + [0.00, 0.02, 0.05, 0.06, 0.04][kec_i]

        for nama, kualitas, dasar, mode, satuan in KOMODITAS:
            harga = float(dasar) * faktor_kec
            for iso, _lbl in BULAN:
                if mode == "konstan":                      # → DATA TIDUR
                    p = round(harga / 100) * 100           # angka bulat rapi
                elif mode == "fluktuasi":
                    harga = harga * (1 + rng.normal(0, 0.03))
                    harga = min(max(harga, dasar * 0.85 * faktor_kec),
                                dasar * 1.20 * faktor_kec)
                    p = int(round(harga / 50) * 50)
                elif mode == "naik":                       # tren naik antar bulan
                    t = BULAN.index((iso, _lbl))
                    p = int(round(harga * (1 + 0.045 * t) / 50) * 50)
                else:
                    raise ValueError(mode)

                rows.append([nama, kualitas, kec, satuan, iso, int(p)])

    df = pd.DataFrame(rows, columns=["komoditas", "kualitas", "kecamatan",
                                     "satuan", "tanggal", "harga"])

    csv_path = os.path.join(dest_dir, "contoh_harga.csv")
    xlsx_path = os.path.join(dest_dir, "contoh_harga.xlsx")
    lebar_path = os.path.join(dest_dir, "contoh_harga_lebar.xlsx")
    df.to_csv(csv_path, index=False, encoding="utf-8")
    with pd.ExcelWriter(xlsx_path, engine="openpyxl") as w:
        df.to_excel(w, sheet_name="Harga", index=False)

    # ---- Versi format LEBAR (kolom = bulan) ----
    lebar = df.pivot_table(index=["komoditas", "kualitas", "kecamatan", "satuan"],
                           columns="tanggal", values="harga", aggfunc="first")
    lebar.columns = [BULAN[[b[0] for b in BULAN].index(c)][1] for c in lebar.columns]
    lebar = lebar.reset_index()
    with pd.ExcelWriter(lebar_path, engine="openpyxl") as w:
        lebar.to_excel(w, sheet_name="Harga Lebar", index=False)

    n_kom = df[["komoditas", "kualitas"]].drop_duplicates().shape[0]
    n_tidur = len([k for k in KOMODITAS if k[3] == "konstan"])
    print(f"OK — {len(df)} baris · {n_kom} komoditas (nama+kualitas) · "
          f"{n_tidur} di antaranya konstan (data tidur) · 3 bulan × 5 kecamatan")
    print("  CSV         :", os.path.abspath(csv_path))
    print("  XLSX (baris):", os.path.abspath(xlsx_path))
    print("  XLSX (lebar):", os.path.abspath(lebar_path))


if __name__ == "__main__":
    dest = sys.argv[1] if len(sys.argv) > 1 else DEFAULT_DIR
    main(dest)
