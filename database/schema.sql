-- ==============================================================
-- DATABASE: Sistem Manajemen Akademik Sekolah
-- Sekolah   : SMP Negeri 7 Purwakarta
-- Engine    : InnoDB / MySQL 8+ / MariaDB 10.4+
-- Charset   : utf8mb4
-- --------------------------------------------------------------
-- File ini membuat struktur BERSIH (fresh install).
-- Untuk database lama yang sudah berisi data, jalankan
-- database/migrate_legacy.php SETELAH file ini diimpor.
-- --------------------------------------------------------------
-- Akun seed:
--   Admin : username "admin" / password "admin12345"
--   Guru  : dibuat lewat register.php atau migrasi (NIP / guru12345)
--   Siswa : dibuat lewat register.php atau migrasi (NISN / siswa12345)
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `portalspenju`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `portalspenju`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS `v_nilai_siswa`;
DROP TABLE IF EXISTS `presensi`;
DROP TABLE IF EXISTS `nilai_siswa`;
DROP TABLE IF EXISTS `jurnal_guru`;
DROP TABLE IF EXISTS `pengampu`;
DROP TABLE IF EXISTS `mata_pelajaran`;
DROP TABLE IF EXISTS `arsip_siswa`;
DROP TABLE IF EXISTS `arsip_guru`;
DROP TABLE IF EXISTS `pengaturan`;
DROP TABLE IF EXISTS `data_siswa`;
DROP TABLE IF EXISTS `rombel`;
DROP TABLE IF EXISTS `data_guru`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================
-- 1. USERS : akun autentikasi terpusat (password_hash)
-- ==============================================================
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) DEFAULT NULL,
  `nama_lengkap`  VARCHAR(150) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','guru','siswa') NOT NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 2. DATA GURU : profil pendidik, terhubung ke users
-- ==============================================================
CREATE TABLE `data_guru` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED DEFAULT NULL,
  `kode`           VARCHAR(30) NOT NULL,
  `nip`            VARCHAR(50) DEFAULT NULL,
  `nama_guru`      VARCHAR(150) NOT NULL,
  `mata_pelajaran` VARCHAR(100) DEFAULT NULL,
  `kelas`          VARCHAR(100) DEFAULT NULL,
  `wali_kelas`     VARCHAR(100) DEFAULT NULL,
  `phone`          VARCHAR(50) DEFAULT NULL,
  `jam_per_minggu` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status`         ENUM('Aktif','Cuti','Non-Aktif') NOT NULL DEFAULT 'Aktif',
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_guru_kode` (`kode`),
  UNIQUE KEY `uq_guru_nip` (`nip`),
  UNIQUE KEY `uq_guru_user` (`user_id`),
  KEY `idx_guru_nama` (`nama_guru`),
  KEY `idx_guru_status` (`status`),
  CONSTRAINT `fk_guru_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 3. ROMBEL : rombongan belajar, wali kelas -> data_guru
-- ==============================================================
CREATE TABLE `rombel` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`         VARCHAR(20) NOT NULL,
  `tingkat`      TINYINT UNSIGNED NOT NULL,
  `ruang`        VARCHAR(30) DEFAULT NULL,
  `kapasitas`    SMALLINT UNSIGNED NOT NULL DEFAULT 32,
  `wali_guru_id` INT UNSIGNED DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rombel_nama` (`nama`),
  KEY `idx_rombel_tingkat` (`tingkat`),
  KEY `idx_rombel_wali` (`wali_guru_id`),
  CONSTRAINT `fk_rombel_wali` FOREIGN KEY (`wali_guru_id`) REFERENCES `data_guru` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 4. DATA SISWA : buku induk, rombel_id -> rombel
-- ==============================================================
CREATE TABLE `data_siswa` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED DEFAULT NULL,
  `nisn`          VARCHAR(20) DEFAULT NULL,
  `nama_siswa`    VARCHAR(150) NOT NULL,
  `rombel_id`     INT UNSIGNED DEFAULT NULL,
  `gender`        ENUM('L','P') NOT NULL DEFAULT 'L',
  `tanggal_lahir` DATE DEFAULT NULL,
  `orang_tua`     VARCHAR(150) DEFAULT NULL,
  `telepon`       VARCHAR(50) DEFAULT NULL,
  `catatan_wali`  TEXT DEFAULT NULL,
  `status`        ENUM('Aktif','Lulus','Mutasi','Mengundurkan Diri') NOT NULL DEFAULT 'Aktif',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_siswa_nisn` (`nisn`),
  UNIQUE KEY `uq_siswa_user` (`user_id`),
  KEY `idx_siswa_rombel` (`rombel_id`),
  KEY `idx_siswa_nama` (`nama_siswa`),
  KEY `idx_siswa_status` (`status`),
  CONSTRAINT `fk_siswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_siswa_rombel` FOREIGN KEY (`rombel_id`) REFERENCES `rombel` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 5. MATA PELAJARAN : koordinator -> data_guru
-- ==============================================================
CREATE TABLE `mata_pelajaran` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode`                VARCHAR(20) NOT NULL,
  `nama`                VARCHAR(100) NOT NULL,
  `kelompok`            VARCHAR(80) NOT NULL DEFAULT 'Kelompok A (Umum)',
  `jam_per_minggu`      TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `kkm`                 TINYINT UNSIGNED NOT NULL DEFAULT 75,
  `cakupan_rombel`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `koordinator_guru_id` INT UNSIGNED DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mapel_kode` (`kode`),
  UNIQUE KEY `uq_mapel_nama` (`nama`),
  KEY `idx_mapel_koordinator` (`koordinator_guru_id`),
  CONSTRAINT `fk_mapel_koordinator` FOREIGN KEY (`koordinator_guru_id`) REFERENCES `data_guru` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 6. PENGAMPU : penugasan guru x mapel x rombel
--    Inilah jembatan sinkronisasi Admin -> Portal Guru.
-- ==============================================================
CREATE TABLE `pengampu` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id`      INT UNSIGNED NOT NULL,
  `mapel_id`     INT UNSIGNED NOT NULL,
  `rombel_id`    INT UNSIGNED NOT NULL,
  `tahun_ajaran` VARCHAR(9) NOT NULL DEFAULT '2026/2027',
  `semester`     ENUM('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pengampu` (`guru_id`,`mapel_id`,`rombel_id`,`tahun_ajaran`,`semester`),
  KEY `idx_pengampu_rombel` (`rombel_id`),
  KEY `idx_pengampu_mapel` (`mapel_id`),
  CONSTRAINT `fk_pengampu_guru` FOREIGN KEY (`guru_id`) REFERENCES `data_guru` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pengampu_mapel` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pengampu_rombel` FOREIGN KEY (`rombel_id`) REFERENCES `rombel` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 7. NILAI SISWA : relasi ke siswa, mapel, guru, rombel
-- ==============================================================
CREATE TABLE `nilai_siswa` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `siswa_id`        INT UNSIGNED NOT NULL,
  `mapel_id`        INT UNSIGNED NOT NULL,
  `guru_id`         INT UNSIGNED DEFAULT NULL,
  `rombel_id`       INT UNSIGNED DEFAULT NULL,
  `jenis_penilaian` VARCHAR(100) NOT NULL,
  `kategori`        ENUM('Formatif','Sumatif','Praktik','Sikap') NOT NULL DEFAULT 'Formatif',
  `nilai`           DECIMAL(5,2) NOT NULL,
  `kkm`             TINYINT UNSIGNED NOT NULL DEFAULT 75,
  `keterangan`      ENUM('Tuntas','Remedial') NOT NULL DEFAULT 'Tuntas',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nilai` (`siswa_id`,`mapel_id`,`jenis_penilaian`),
  KEY `idx_nilai_mapel` (`mapel_id`),
  KEY `idx_nilai_rombel` (`rombel_id`),
  KEY `idx_nilai_guru` (`guru_id`),
  CONSTRAINT `fk_nilai_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `data_siswa` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_nilai_mapel` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_nilai_guru` FOREIGN KEY (`guru_id`) REFERENCES `data_guru` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_nilai_rombel` FOREIGN KEY (`rombel_id`) REFERENCES `rombel` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 8. JURNAL GURU : relasi ke guru, mapel, rombel
-- ==============================================================
CREATE TABLE `jurnal_guru` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id`    INT UNSIGNED NOT NULL,
  `mapel_id`   INT UNSIGNED NOT NULL,
  `rombel_id`  INT UNSIGNED NOT NULL,
  `hari`       VARCHAR(20) NOT NULL,
  `tanggal`    DATE NOT NULL,
  `pertemuan`  VARCHAR(100) NOT NULL,
  `materi`     VARCHAR(255) NOT NULL,
  `kegiatan`   TEXT NOT NULL,
  `hadir`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `izin`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `sakit`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `alpa`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jurnal_guru` (`guru_id`),
  KEY `idx_jurnal_rombel` (`rombel_id`),
  KEY `idx_jurnal_mapel` (`mapel_id`),
  KEY `idx_jurnal_tanggal` (`tanggal`),
  CONSTRAINT `fk_jurnal_guru` FOREIGN KEY (`guru_id`) REFERENCES `data_guru` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jurnal_mapel` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jurnal_rombel` FOREIGN KEY (`rombel_id`) REFERENCES `rombel` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 9. PRESENSI : kehadiran per siswa per pertemuan
-- ==============================================================
CREATE TABLE `presensi` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `siswa_id`   INT UNSIGNED NOT NULL,
  `rombel_id`  INT UNSIGNED NOT NULL,
  `mapel_id`   INT UNSIGNED NOT NULL,
  `guru_id`    INT UNSIGNED DEFAULT NULL,
  `tanggal`    DATE NOT NULL,
  `pertemuan`  VARCHAR(100) NOT NULL,
  `status`     ENUM('H','I','S','A') NOT NULL DEFAULT 'H',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_presensi` (`siswa_id`,`mapel_id`,`tanggal`,`pertemuan`),
  KEY `idx_presensi_rombel` (`rombel_id`),
  KEY `idx_presensi_guru` (`guru_id`),
  CONSTRAINT `fk_presensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `data_siswa` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_presensi_rombel` FOREIGN KEY (`rombel_id`) REFERENCES `rombel` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_presensi_mapel` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_presensi_guru` FOREIGN KEY (`guru_id`) REFERENCES `data_guru` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 10. ARSIP SISWA
-- ==============================================================
CREATE TABLE `arsip_siswa` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `siswa_id`    INT UNSIGNED DEFAULT NULL,
  `nisn`        VARCHAR(20) DEFAULT NULL,
  `nama_siswa`  VARCHAR(150) NOT NULL,
  `last_class`  VARCHAR(20) DEFAULT NULL,
  `status`      ENUM('Lulus','Mutasi Keluar','Mengundurkan Diri') NOT NULL,
  `time_label`  VARCHAR(50) DEFAULT NULL,
  `tahun`       SMALLINT UNSIGNED DEFAULT NULL,
  `destination` VARCHAR(255) DEFAULT NULL,
  `phone`       VARCHAR(50) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arsip_siswa_status` (`status`),
  KEY `idx_arsip_siswa_tahun` (`tahun`),
  CONSTRAINT `fk_arsip_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `data_siswa` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 11. ARSIP GURU
-- ==============================================================
CREATE TABLE `arsip_guru` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id`      INT UNSIGNED DEFAULT NULL,
  `nip`          VARCHAR(50) DEFAULT NULL,
  `nama_guru`    VARCHAR(150) NOT NULL,
  `last_subject` VARCHAR(100) DEFAULT NULL,
  `status`       ENUM('Pensiun','Mutasi Keluar','Purna Bakti') NOT NULL,
  `time_label`   VARCHAR(50) DEFAULT NULL,
  `tahun`        SMALLINT UNSIGNED DEFAULT NULL,
  `sk`           VARCHAR(255) DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arsip_guru_status` (`status`),
  CONSTRAINT `fk_arsip_guru` FOREIGN KEY (`guru_id`) REFERENCES `data_guru` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 12. PENGATURAN SEKOLAH (key-value)
-- ==============================================================
CREATE TABLE `pengaturan` (
  `kunci`      VARCHAR(80) NOT NULL,
  `nilai`      TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`kunci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- VIEW BANTU : rekap nilai lengkap
-- ==============================================================
CREATE VIEW `v_nilai_siswa` AS
SELECT
  n.`id`,
  n.`siswa_id`,
  s.`nisn`,
  s.`nama_siswa`,
  r.`nama` AS `rombel`,
  r.`tingkat`,
  m.`id` AS `mapel_id`,
  m.`kode` AS `kode_mapel`,
  m.`nama` AS `mata_pelajaran`,
  m.`kkm` AS `kkm_mapel`,
  g.`id` AS `guru_id`,
  g.`nama_guru` AS `guru_pengampu`,
  n.`jenis_penilaian`,
  n.`kategori`,
  n.`nilai`,
  n.`kkm`,
  n.`keterangan`,
  n.`created_at`,
  n.`updated_at`
FROM `nilai_siswa` n
JOIN `data_siswa` s ON s.`id` = n.`siswa_id`
LEFT JOIN `rombel` r ON r.`id` = n.`rombel_id`
JOIN `mata_pelajaran` m ON m.`id` = n.`mapel_id`
LEFT JOIN `data_guru` g ON g.`id` = n.`guru_id`;

-- ==============================================================
-- SEED: AKUN ADMIN
-- password: admin12345 (WAJIB diganti setelah login pertama)
-- ==============================================================
INSERT INTO `users` (`username`, `email`, `nama_lengkap`, `password_hash`, `role`)
VALUES ('admin', 'admin@smpn7purwakarta.sch.id', 'Administrator Sekolah', '$2y$10$6qqFvK3VdhT7TKB/ZTfz7enuYrTUbSr4YZFrFgFopgTEsk0rX/4im', 'admin');

-- ==============================================================
-- SEED: 11 MATA PELAJARAN
-- ==============================================================
INSERT INTO `mata_pelajaran`
  (`kode`, `nama`, `kelompok`, `jam_per_minggu`, `kkm`, `cakupan_rombel`) VALUES
('MP-01', 'Matematika',                        'Kelompok A (Umum)',           4, 75, 27),
('MP-02', 'Ilmu Pengetahuan Alam (IPA)',       'Kelompok A (Umum)',           5, 75, 27),
('MP-03', 'Bahasa Indonesia',                  'Kelompok A (Umum)',           5, 78, 27),
('MP-04', 'Bahasa Inggris',                    'Kelompok A (Umum)',           4, 75, 27),
('MP-05', 'Pendidikan Pancasila',              'Kelompok A (Umum)',           3, 78, 27),
('MP-06', 'Pendidikan Agama & Budi Pekerti',   'Kelompok A (Umum)',           3, 80, 27),
('MP-07', 'Ilmu Pengetahuan Sosial (IPS)',     'Kelompok A (Umum)',           4, 75, 27),
('MP-08', 'Informatika',                       'Kelompok B (Pengembangan)',   2, 75, 27),
('MP-09', 'Pendidikan Jasmani (PJOK)',         'Kelompok B (Pengembangan)',   3, 78, 27),
('MP-10', 'Seni Budaya',                       'Kelompok B (Pengembangan)',   3, 75, 27),
('MP-11', 'Bahasa Sunda (Muatan Lokal)',       'Kelompok C (Muatan Lokal)',   2, 75, 27);

-- ==============================================================
-- SEED: 27 ROMBEL (VII A - IX I)
-- ==============================================================
INSERT INTO `rombel` (`nama`, `tingkat`, `ruang`, `kapasitas`) VALUES
('VII A', 7, 'R. 101', 32), ('VII B', 7, 'R. 102', 32), ('VII C', 7, 'R. 103', 32),
('VII D', 7, 'R. 104', 32), ('VII E', 7, 'R. 105', 32), ('VII F', 7, 'R. 106', 32),
('VII G', 7, 'R. 107', 32), ('VII H', 7, 'R. 108', 32), ('VII I', 7, 'R. 109', 32),
('VIII A', 8, 'R. 201', 32), ('VIII B', 8, 'R. 202', 32), ('VIII C', 8, 'R. 203', 32),
('VIII D', 8, 'R. 204', 32), ('VIII E', 8, 'R. 205', 32), ('VIII F', 8, 'R. 206', 32),
('VIII G', 8, 'R. 207', 32), ('VIII H', 8, 'R. 208', 32), ('VIII I', 8, 'R. 209', 32),
('IX A', 9, 'R. 301', 32), ('IX B', 9, 'R. 302', 32), ('IX C', 9, 'R. 303', 32),
('IX D', 9, 'R. 304', 32), ('IX E', 9, 'R. 305', 32), ('IX F', 9, 'R. 306', 32),
('IX G', 9, 'R. 307', 32), ('IX H', 9, 'R. 308', 32), ('IX I', 9, 'R. 309', 32);

-- ==============================================================
-- SEED: PENGATURAN SEKOLAH
-- ==============================================================
INSERT INTO `pengaturan` (`kunci`, `nilai`) VALUES
('school_name',      'SMP Negeri 7 Purwakarta'),
('npsn',             '20205819'),
('address',          'Jl. Veteran No. 128, Ciseureuh, Kec. Purwakarta, Kab. Purwakarta, Jawa Barat 41118'),
('headmaster',       'H. Ahmad Sanusi, M.Pd'),
('headmaster_nip',   '19680315 199303 1 004'),
('tahun_ajaran',     '2026/2027'),
('semester',         'Semester 1 (Ganjil)'),
('deadline_nilai',   '2026-10-20'),
('registration_open','1');
