<?php
/**
 * ==============================================================
 * KONFIGURASI APLIKASI
 * Sistem Manajemen Akademik SMPN 7 Purwakarta
 * --------------------------------------------------------------
 * Ubah nilai di bawah ini sesuai lingkungan server Anda.
 * Disarankan memakai environment variable pada server produksi.
 * ==============================================================
 */
declare(strict_types=1);

// ------------------------- DATABASE ---------------------------
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'portalspenju');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));

// ------------------------- APLIKASI ---------------------------
define('APP_NAME', 'Sistem Manajemen Akademik SMPN 7 Purwakarta');
define('APP_SHORT_NAME', 'SMPN 7 Purwakarta');
define('SCHOOL_NAME', 'SMP Negeri 7 Purwakarta');
define('SCHOOL_NPSN', '20205819');
define('SCHOOL_ADDRESS', 'Jl. Veteran No. 128, Ciseureuh, Kec. Purwakarta, Kab. Purwakarta, Jawa Barat 41118');
define('TAHUN_AJARAN', getenv('TAHUN_AJARAN') ?: '2026/2027');
define('SEMESTER_AKTIF', getenv('SEMESTER_AKTIF') ?: 'Ganjil');

/**
 * Kode rahasia untuk mendaftarkan akun ADMIN baru.
 * WAJIB diganti sebelum dipakai di server produksi.
 * Bisa juga diisi lewat environment variable ADMIN_REGISTRATION_CODE.
 */
define('ADMIN_REGISTRATION_CODE', getenv('ADMIN_REGISTRATION_CODE') ?: 'SMPN7-ADMIN-2026');

// ------------------------- SESI -------------------------------
define('SESSION_NAME', 'SMPN7AKADEMIK');
define('SESSION_IDLE_TIMEOUT', 7200);      // detik (2 jam)
define('SESSION_REGENERATE_EVERY', 900);   // detik (15 menit)
define('PASSWORD_MIN_LENGTH', 8);

// ------------------------- LAIN-LAIN --------------------------
define('DEFAULT_TEACHER_PASSWORD', 'guru12345');
define('DEFAULT_STUDENT_PASSWORD', 'siswa12345');
// NIP (= username) guru yang berperan sebagai Kepala Sekolah: boleh MELIHAT seluruh kelas & jurnal
define('KEPSEK_USERNAME', '198510101 201101 1 001');

date_default_timezone_set('Asia/Jakarta');

// Tampilkan error hanya saat pengembangan.
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'], true);
define('APP_DEBUG', $isLocal && (getenv('APP_DEBUG') !== '0'));
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
