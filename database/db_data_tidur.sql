-- =====================================================================
--  DETEKSI DATA TIDUR — Skema Database MySQL (versi 2)
--  Unit analisis: KOMODITAS (nama + kualitas) × KECAMATAN, per BULAN.
--  "Data tidur" = harga komoditas yang KONSTAN antar bulan.
--  Cara import: buka http://localhost/phpmyadmin → menu "Import"
--  → pilih file schema.sql ini → Klik "Go/Kirim".
--  Atau lewat terminal: mysql -u root < schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_data_tidur
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE db_data_tidur;

-- Tabel versi lama (bila pernah diimpor) dibersihkan
DROP TABLE IF EXISTS harga_harian;

-- ---------------------------------------------------------------------
-- 1) users : akun admin & viewer (password disimpan sebagai bcrypt hash).
--    role 'admin'  : akses penuh (unggah, analisis, hapus, kelola).
--    role 'viewer' : HANYA MELIHAT — dapat membuka halaman publik dan
--                    dashboard admin, tetapi tidak dapat mengunggah,
--                    mengedit, ataupun menghapus file.
--    Akun default DIBUAT OLEH install.php (bukan di sini), agar hash
--    password dibuat oleh PHP password_hash() yang kompatibel.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  nama       VARCHAR(100) DEFAULT '',
  role       ENUM('admin','viewer') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2) uploads : riwayat setiap file Excel/CSV yang diunggah admin.
--    total_komoditas = jumlah komoditas unik (nama+kualitas) sesuai isi file.
--    total_tidur     = komoditas dengan harga konstan antar bulan.
--    total_bergerak  = komoditas dengan harga yang berubah (normal).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS uploads;
CREATE TABLE uploads (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  original_name    VARCHAR(255) NOT NULL,
  stored_name      VARCHAR(255) NOT NULL,
  file_type        VARCHAR(10)  NOT NULL DEFAULT 'csv',
  total_baris      INT UNSIGNED    DEFAULT 0,
  total_seri       INT UNSIGNED    DEFAULT 0,
  total_komoditas  INT UNSIGNED    DEFAULT 0,
  total_tidur      INT UNSIGNED    DEFAULT 0,
  total_bergerak   INT UNSIGNED    DEFAULT 0,
  periode_mulai    DATE NULL,
  periode_selesai  DATE NULL,
  status           ENUM('processing','done','failed') DEFAULT 'processing',
  pesan            TEXT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3) harga_bulanan : harga tiap komoditas per BULAN (satu baris = satu
--    harga satu bulan untuk satu komoditas × kecamatan). Sumber grafik
--    ECharts, dropdown SHPED, dan peta harga di halaman publik.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS harga_bulanan;
CREATE TABLE harga_bulanan (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  upload_id  INT UNSIGNED NOT NULL,
  komoditas  VARCHAR(150) NOT NULL,
  kualitas   VARCHAR(100) NOT NULL DEFAULT 'Standar',
  kecamatan  VARCHAR(100) NOT NULL DEFAULT 'Tobelo',
  satuan     VARCHAR(50)  NOT NULL DEFAULT '/kg',
  bulan      DATE NOT NULL,
  harga      DECIMAL(15,2) NOT NULL,
  jumlah_hari INT UNSIGNED DEFAULT 1,
  INDEX idx_h_upload (upload_id),
  INDEX idx_h_seri   (komoditas, kualitas, kecamatan),
  CONSTRAINT fk_harga_upload FOREIGN KEY (upload_id)
    REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4) analisis_seri : hasil analisis per seri (komoditas × kualitas ×
--    kecamatan): statistik harga, status DATA TIDUR + alasannya.
--    kuesioner = kode kuesioner dari kolom "Kuesioner" file SHPED
--    (mis. HKD-1, HKD-2.1) — sumber dropdown filter kuesioner.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS analisis_seri;
CREATE TABLE analisis_seri (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  upload_id        INT UNSIGNED NOT NULL,
  komoditas        VARCHAR(150) NOT NULL,
  kualitas         VARCHAR(100) NOT NULL DEFAULT 'Standar',
  kecamatan        VARCHAR(100) NOT NULL DEFAULT 'Tobelo',
  satuan           VARCHAR(50)  NOT NULL DEFAULT '/kg',
  kuesioner        VARCHAR(50)  DEFAULT NULL,
  jumlah_data      INT UNSIGNED  DEFAULT 0,
  jumlah_bulan     INT UNSIGNED  DEFAULT 0,
  harga_awal       DECIMAL(15,2) NULL,
  harga_akhir      DECIMAL(15,2) NULL,
  harga_min        DECIMAL(15,2) NULL,
  harga_max        DECIMAL(15,2) NULL,
  perubahan_persen DECIMAL(10,2) DEFAULT 0,
  is_tidur         TINYINT(1)    DEFAULT 0,
  alasan_tidur     VARCHAR(255)  DEFAULT '',
  bulan_mulai      DATE NULL,
  bulan_selesai    DATE NULL,
  INDEX idx_a_upload (upload_id),
  INDEX idx_a_tidur  (is_tidur),
  INDEX idx_a_kues   (kuesioner),
  CONSTRAINT fk_seri_upload FOREIGN KEY (upload_id)
    REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB;
