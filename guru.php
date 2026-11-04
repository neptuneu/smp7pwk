<?php
/**
 * ==============================================================
 * PORTAL GURU - SMPN 7 PURWAKARTA
 * --------------------------------------------------------------
 * Hak Akses Dinamis & Export CSV Ledger Rapor (Buku Induk)
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/wali_ledger.php';

// Pastikan koneksi database ($conn / $koneksi) tersedia
$conn = $conn ?? ($koneksi ?? null);

$user = require_login('guru');
$guruName = $user['nama'] ?: $user['username'];
$csrf = csrf_token();

if (!isset($_SESSION['user_id']) && isset($user['id'])) {
    $_SESSION['user_id'] = (int) $user['id'];
}
$user_id = (int) ($_SESSION['user_id'] ?? ($user['id'] ?? 0));

// ==============================================================
// FITUR: EXPORT CSV LEDGER RAPOR (BUKU INDUK)
// HANYA DAPAT DIUNDUH OLEH WALI KELAS DI KELAS BINAANNYA
// ==============================================================
if (isset($_GET['action']) && $_GET['action'] === 'export_ledger_csv') {
    $rombel_param = trim((string) ($_GET['rombel'] ?? $_GET['rombel_id'] ?? ''));
    if ($rombel_param === '') {
        die('Parameter kelas tidak valid.');
    }

    // Cari rombel berdasarkan ID atau Nama
    $stmt_r = $conn->prepare("SELECT * FROM rombel WHERE id = ? OR nama = ? LIMIT 1");
    $stmt_r->bind_param("ss", $rombel_param, $rombel_param);
    $stmt_r->execute();
    $rombel = $stmt_r->get_result()->fetch_assoc();
    $stmt_r->close();

    if (!$rombel) {
        die('Kelas tidak ditemukan.');
    }

    $rombel_id = (int) $rombel['id'];

    // Verifikasi Hak Akses: Wajib wali kelas rombel ini
    $hak = function_exists('tentukan_hak_akses_kelas') 
        ? tentukan_hak_akses_kelas($conn, $user_id, $rombel_id)
        : 'guru_mapel';

    if ($hak !== 'wali_kelas') {
        http_response_code(403);
        die('Akses ditolak: Anda bukan wali kelas untuk kelas ' . htmlspecialchars($rombel['nama']));
    }

    // Ambil data konsolidasi all mapel & presensi
    $ledger = function_exists('fetch_ledger_rapor') ? fetch_ledger_rapor($conn, $rombel_id) : [];
    $daftar_mapel   = $ledger['daftar_mapel'] ?? [];
    $daftar_siswa   = $ledger['daftar_siswa'] ?? [];
    $matriks_nilai  = $ledger['matriks_nilai'] ?? [];
    $rekap_presensi = $ledger['rekap_presensi'] ?? [];

    $cleanName = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $rombel['nama']);
    $filename = 'Ledger_Buku_Induk_' . $cleanName . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM untuk Microsoft Excel agar rapi tanpa karakter berantakan
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Judul & Metadata Sekolah
    fputcsv($output, ['BUKU INDUK & LEDGER RAPOR - SMP NEGERI 7 PURWAKARTA']);
    fputcsv($output, [
        'Kelas: ' . $rombel['nama'],
        'Tahun Ajaran: ' . (defined('TAHUN_AJARAN') ? TAHUN_AJARAN : '2026/2027'),
        'Semester: ' . (defined('SEMESTER_AKTIF') ? SEMESTER_AKTIF : 'Ganjil'),
        'Wali Kelas: ' . ($ledger['wali_nama'] ?? $guruName),
    ]);
    fputcsv($output, []); // Baris kosong

    // Baris Header Kolom
    $header = ['No', 'NISN', 'Nama Siswa'];
    foreach ($daftar_mapel as $mapel) {
        $header[] = $mapel['nama_mapel'];
    }
    $header[] = 'Rata-Rata Nilai';
    $header[] = 'Predikat';
    $header[] = 'Hadir (H)';
    $header[] = 'Izin (I)';
    $header[] = 'Sakit (S)';
    $header[] = 'Alpa (A)';
    $header[] = '% Kehadiran';
    fputcsv($output, $header);

    // Baris Data Siswa
    $no = 1;
    $sumMapelKelas = [];
    $countMapelKelas = [];
    foreach ($daftar_mapel as $m) {
        $sumMapelKelas[(int)$m['id']] = 0.0;
        $countMapelKelas[(int)$m['id']] = 0;
    }

    foreach ($daftar_siswa as $siswa) {
        $sId = (int) $siswa['id'];
        $presensi = $rekap_presensi[$sId] ?? ['total_h' => 0, 'total_i' => 0, 'total_s' => 0, 'total_a' => 0];
        
        $row = [
            $no++,
            $siswa['nisn'] ?: '-',
            $siswa['nama_siswa'],
        ];

        $totalNilaiSiswa = 0.0;
        $mapelTerisi = 0;
        foreach ($daftar_mapel as $mapel) {
            $mId = (int) $mapel['id'];
            $val = $matriks_nilai[$sId][$mId] ?? null;
            if ($val !== null) {
                $row[] = number_format((float)$val, 1, '.', '');
                $totalNilaiSiswa += (float)$val;
                $mapelTerisi++;
                $sumMapelKelas[$mId] += (float)$val;
                $countMapelKelas[$mId]++;
            } else {
                $row[] = '';
            }
        }

        $rataSiswa = $mapelTerisi > 0 ? round($totalNilaiSiswa / $mapelTerisi, 1) : null;
        $row[] = $rataSiswa !== null ? number_format($rataSiswa, 1, '.', '') : '';
        $row[] = $siswa['predikat'] ?? '-';
        $row[] = $presensi['total_h'] ?? 0;
        $row[] = $presensi['total_i'] ?? 0;
        $row[] = $presensi['total_s'] ?? 0;
        $row[] = $presensi['total_a'] ?? 0;
        $row[] = ($siswa['persen_hadir'] !== null ? $siswa['persen_hadir'] . '%' : '-');

        fputcsv($output, $row);
    }

    // Baris Footer Rata-Rata Kelas
    $footer = ['Rata-Rata Kelas', '', ''];
    foreach ($daftar_mapel as $mapel) {
        $mId = (int) $mapel['id'];
        $c = $countMapelKelas[$mId] ?? 0;
        $footer[] = $c > 0 ? number_format($sumMapelKelas[$mId] / $c, 1, '.', '') : '';
    }
    $footer[] = '';
    $footer[] = '';
    $footer[] = '';
    $footer[] = '';
    $footer[] = '';
    $footer[] = '';
    $footer[] = '';
    fputcsv($output, $footer);

    fclose($output);
    exit;
}

// ==============================================================
// TAMPILAN UTAMA PORTAL GURU
// ==============================================================
require __DIR__ . '/views/guru_body.php';
?>

<script>
  window.GURU_CONFIG = {
    csrf: <?= json_encode($csrf) ?>,
    userName: <?= json_encode($guruName) ?>,
    apiUrl: 'api.php',
    logoutUrl: 'logout.php'
  };
</script>
<script src="assets/guru-app.js?v=<?= (int) @filemtime(__DIR__ . '/assets/guru-app.js') ?>"></script>
</body>
</html>
