<?php
require_once __DIR__ . '/koneksi.php';

// 1. Buat Tabel Data Siswa
$sqlSiswa = "CREATE TABLE IF NOT EXISTS data_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(20) NOT NULL UNIQUE,
    nama_siswa VARCHAR(150) NOT NULL,
    kelas VARCHAR(30) NOT NULL,
    gender ENUM('L', 'P') DEFAULT 'L',
    tanggal_lahir VARCHAR(20) DEFAULT '14092012',
    orang_tua VARCHAR(150) DEFAULT '-',
    telepon VARCHAR(50) DEFAULT '-',
    catatan_wali TEXT,
    status VARCHAR(20) DEFAULT 'Aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($koneksi, $sqlSiswa) or die("Error Siswa: " . mysqli_error($koneksi));

// 2. Buat Tabel Nilai Siswa
$sqlNilai = "CREATE TABLE IF NOT EXISTS nilai_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(20) NOT NULL,
    nama_siswa VARCHAR(150) NOT NULL,
    kelas VARCHAR(30) NOT NULL,
    mata_pelajaran VARCHAR(100) NOT NULL,
    guru_pengampu VARCHAR(150) DEFAULT '-',
    jenis_penilaian VARCHAR(100) NOT NULL,
    nilai DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    kkm INT DEFAULT 75,
    keterangan VARCHAR(50) DEFAULT 'Tuntas',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entry (nisn, kelas, mata_pelajaran, jenis_penilaian)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($koneksi, $sqlNilai) or die("Error Nilai: " . mysqli_error($koneksi));

// 3. Buat Tabel Jurnal Guru
$sqlJurnal = "CREATE TABLE IF NOT EXISTS jurnal_guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guru_kode VARCHAR(50) NOT NULL,
    nama_guru VARCHAR(150) NOT NULL,
    kelas VARCHAR(30) NOT NULL,
    mata_pelajaran VARCHAR(100) NOT NULL,
    hari VARCHAR(20) NOT NULL,
    tanggal DATE NOT NULL,
    pertemuan VARCHAR(100) NOT NULL,
    materi VARCHAR(255) NOT NULL,
    kegiatan TEXT NOT NULL,
    hadir INT DEFAULT 0,
    izin INT DEFAULT 0,
    sakit INT DEFAULT 0,
    alpa INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($koneksi, $sqlJurnal) or die("Error Jurnal: " . mysqli_error($koneksi));

// Seed Siswa Awal jika kosong
$qCheck = mysqli_query($koneksi, "SELECT COUNT(*) as cnt FROM data_siswa");
$rowCheck = mysqli_fetch_assoc($qCheck);
if ($rowCheck['cnt'] == 0) {
    $seedStudents = [
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'L', '14092012', 'H. Bambang Irawan', '0812-7788-9900', 'Aktif bertanya dan memiliki daya nalar yang sangat baik.'],
        ['0098427142', 'Alifia Nurul Zahra', 'Kelas VIII A', 'P', '14092012', 'Agus Setiawan', '0813-2233-4455', 'Rajin mencatat dan menyelesaikan tugas tepat waktu.'],
        ['0098427150', 'Bayu Arya Putra', 'Kelas VIII A', 'L', '14092012', 'Arya Supriatna', '0857-1122-3344', 'Pertahankan fokus saat latihan soal analitis.'],
        ['0098427161', 'Citra Kirana Dewi', 'Kelas VIII A', 'P', '14092012', 'Dewi Sartika', '0819-6677-8899', 'Sangat rapi dan teliti dalam presentasi kelompok.'],
        ['0098427178', 'Daffa Ramadhan', 'Kelas VIII A', 'L', '14092012', 'Rahmat Hidayat', '0812-9900-1122', 'Perlu bimbingan remedial dan peningkatan kedisiplinan kehadiran.'],
        ['0098427189', 'Farah Salsabila', 'Kelas VIII A', 'P', '14092012', 'Suhartono', '0852-3344-5566', 'Konsisten berprestasi baik dan disiplin.'],
        ['0098427195', 'Gilang Ramadhan', 'Kelas VIII B', 'L', '14092012', 'Wawan Gunawan', '0813-8899-0011', 'Bakat dalam pembelajaran numerik dan logika.'],
        ['0098427201', 'Hana Khairunnisa', 'Kelas VIII B', 'P', '14092012', 'Iwan Ridwan', '0878-1122-4455', 'Aktif dalam kegiatan literasi dan sains.'],
        ['0098427210', 'Ilham Pratama', 'Kelas VII A', 'L', '14092012', 'Sandi Sujana', '0821-5566-7788', 'Adaptif dan mudah bekerja sama dengan rekan.'],
        ['0098427225', 'Jasmine Putri', 'Kelas VII A', 'P', '14092012', 'Andri Kusuma', '0812-3344-8899', 'Semangat belajar tinggi dan ramah.'],
        ['0098427230', 'Kenzo Alfarizi', 'Kelas IX A', 'L', '14092012', 'Budi Darmawan', '0856-7788-9900', 'Mandiri dan memiliki daya analisis tajam.'],
        ['0098427245', 'Laila Zahira', 'Kelas IX A', 'P', '14092012', 'Zulkifli', '0813-4455-6677', 'Komunikatif dan berprestasi akademik tinggi.']
    ];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO data_siswa (nisn, nama_siswa, kelas, gender, tanggal_lahir, orang_tua, telepon, catatan_wali) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($seedStudents as $s) {
        mysqli_stmt_bind_param($stmt, 'ssssssss', $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7]);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}

// Seed Nilai Awal jika kosong
$qCheckNilai = mysqli_query($koneksi, "SELECT COUNT(*) as cnt FROM nilai_siswa");
$rowCheckNilai = mysqli_fetch_assoc($qCheckNilai);
if ($rowCheckNilai['cnt'] == 0) {
    $seedGrades = [
        // Aditya Pratama (0098427135) di Kelas VIII A
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'Matematika', 'Dra. Puas Hadiningsih', 'Tugas 1', 88.0, 75, 'Tuntas'],
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'Matematika', 'Dra. Puas Hadiningsih', 'Tugas 2', 92.0, 75, 'Tuntas'],
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'Matematika', 'Dra. Puas Hadiningsih', 'Sumatif 1', 85.0, 75, 'Tuntas'],
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'Matematika', 'Dra. Puas Hadiningsih', 'PTS', 90.0, 75, 'Tuntas'],
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'Ilmu Pengetahuan Alam (IPA)', 'Eli Sutiani, S.Pd', 'Tugas 1', 86.0, 75, 'Tuntas'],
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'Ilmu Pengetahuan Alam (IPA)', 'Eli Sutiani, S.Pd', 'Sumatif 1', 89.0, 75, 'Tuntas'],
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'Bahasa Indonesia', 'Siti Laela, S.Pd', 'Tugas 1', 90.0, 78, 'Tuntas'],
        ['0098427135', 'Aditya Pratama', 'Kelas VIII A', 'Bahasa Inggris', 'Asep Sunjaya S.Pd', 'Tugas 1', 84.0, 75, 'Tuntas'],

        // Daffa Ramadhan (0098427178) di Kelas VIII A
        ['0098427178', 'Daffa Ramadhan', 'Kelas VIII A', 'Matematika', 'Dra. Puas Hadiningsih', 'Tugas 1', 68.0, 75, 'Remedial'],
        ['0098427178', 'Daffa Ramadhan', 'Kelas VIII A', 'Matematika', 'Dra. Puas Hadiningsih', 'Tugas 2', 72.0, 75, 'Remedial'],
        ['0098427178', 'Daffa Ramadhan', 'Kelas VIII A', 'Matematika', 'Dra. Puas Hadiningsih', 'Sumatif 1', 65.0, 75, 'Remedial'],
        ['0098427178', 'Daffa Ramadhan', 'Kelas VIII A', 'Matematika', 'Dra. Puas Hadiningsih', 'PTS', 70.0, 75, 'Remedial'],
        ['0098427178', 'Daffa Ramadhan', 'Kelas VIII A', 'Ilmu Pengetahuan Alam (IPA)', 'Eli Sutiani, S.Pd', 'Tugas 1', 70.0, 75, 'Remedial'],
        ['0098427178', 'Daffa Ramadhan', 'Kelas VIII A', 'Bahasa Indonesia', 'Siti Laela, S.Pd', 'Tugas 1', 78.0, 78, 'Tuntas']
    ];

    $stmtN = mysqli_prepare($koneksi, "INSERT INTO nilai_siswa (nisn, nama_siswa, kelas, mata_pelajaran, guru_pengampu, jenis_penilaian, nilai, kkm, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai), keterangan=VALUES(keterangan)");
    foreach ($seedGrades as $g) {
        mysqli_stmt_bind_param($stmtN, 'ssssssdis', $g[0], $g[1], $g[2], $g[3], $g[4], $g[5], $g[6], $g[7], $g[8]);
        mysqli_stmt_execute($stmtN);
    }
    mysqli_stmt_close($stmtN);
}

// Seed Jurnal Awal jika kosong
$qCheckJurnal = mysqli_query($koneksi, "SELECT COUNT(*) as cnt FROM jurnal_guru");
$rowCheckJurnal = mysqli_fetch_assoc($qCheckJurnal);
if ($rowCheckJurnal['cnt'] == 0) {
    $seedJournals = [
        ['2', 'Dra. Puas Hadiningsih', 'Kelas VIII A', 'IPS', 'Senin', '2026-10-05', 'Pertemuan ke-8 (Jam 1-3)', 'Interaksi Antarnegara ASEAN dan Pengaruhnya', 'Penyampaian materi letak geografis kawasan Asia Tenggara, diskusi kelompok mengenai komoditas perdagangan, dan kuis pemahaman.', 31, 1, 0, 0],
        ['2', 'Dra. Puas Hadiningsih', 'Kelas VIII A', 'IPS', 'Senin', '2026-09-28', 'Pertemuan ke-7 (Jam 1-3)', 'Ulangan Harian 1: Keruangan dan Konektivitas Wilayah', 'Pelaksanaan asesmen sumatif bab 1 selama 60 menit, dilanjutkan ulasan bersama soal uraian analisis peta.', 32, 0, 0, 0]
    ];

    $stmtJ = mysqli_prepare($koneksi, "INSERT INTO jurnal_guru (guru_kode, nama_guru, kelas, mata_pelajaran, hari, tanggal, pertemuan, materi, kegiatan, hadir, izin, sakit, alpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($seedJournals as $j) {
        mysqli_stmt_bind_param($stmtJ, 'sssssssssiiii', $j[0], $j[1], $j[2], $j[3], $j[4], $j[5], $j[6], $j[7], $j[8], $j[9], $j[10], $j[11], $j[12]);
        mysqli_stmt_execute($stmtJ);
    }
    mysqli_stmt_close($stmtJ);
}

echo "Database Penilaian Siswa, Jurnal Guru, dan Data Siswa berhasil diinisialisasi!\n";
