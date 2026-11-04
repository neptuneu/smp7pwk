<?php
/**
 * ==============================================================
 * EXPORT BUKU INDUK & LEDGER RAPOR — SMP NEGERI 7 PURWAKARTA
 * --------------------------------------------------------------
 * File mandiri untuk ekspor CSV Buku Induk (Ledger Rapor).
 * Hanya dapat diakses oleh Wali Kelas pada kelas binaannya.
 *
 * Endpoint: export_ledger_csv.php?rombel=VIII+A
 *
 * Kolom CSV (crosstab / matriks):
 *   No | NISN | Nama Siswa | [Mapel 1] | ... | [Mapel N]
 *   | Rata-Rata Rapor | Predikat
 *   | Hadir (H) | Izin (I) | Sakit (S) | Alpa (A) | % Kehadiran
 *
 * Semua query memakai prepared statement (mysqli).
 * Otorisasi dilakukan lewat auth.php + cek wali_guru_id rombel.
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/wali_ledger.php';

// Pastikan ada koneksi database
$conn = $conn ?? ($koneksi ?? null);

// Wajib login sebagai guru
$user    = require_login('guru');
$user_id = (int) ($user['id'] ?? 0);
$guruName = $user['nama'] ?? ($user['username'] ?? 'Guru');

// ──────────────────────────────────────────────
// 1. VALIDASI PARAMETER
// ──────────────────────────────────────────────
$rombel_param = trim((string) ($_GET['rombel'] ?? ''));
if ($rombel_param === '') {
    http_response_code(400);
    die('Parameter ?rombel= wajib diisi. Contoh: export_ledger_csv.php?rombel=VIII+A');
}

// ──────────────────────────────────────────────
// 2. AMBIL DATA ROMBEL
// ──────────────────────────────────────────────
$stmt_r = $conn->prepare("SELECT * FROM rombel WHERE id = ? OR nama = ? LIMIT 1");
$stmt_r->bind_param("ss", $rombel_param, $rombel_param);
$stmt_r->execute();
$rombel = $stmt_r->get_result()->fetch_assoc();
$stmt_r->close();

if (!$rombel) {
    http_response_code(404);
    die('Kelas "' . htmlspecialchars($rombel_param) . '" tidak ditemukan di database.');
}

$rombel_id = (int) $rombel['id'];

// ──────────────────────────────────────────────
// 3. CEK HAK AKSES — WAJIB WALI KELAS (ATAU KEPALA SEKOLAH)
// ──────────────────────────────────────────────
$isKepsek = defined('KEPSEK_USERNAME') && (($user['username'] ?? '') === KEPSEK_USERNAME);
$hak = function_exists('tentukan_hak_akses_kelas')
    ? tentukan_hak_akses_kelas($conn, $user_id, $rombel_id)
    : 'guru_mapel';

if ($hak !== 'wali_kelas' && !$isKepsek) {
    http_response_code(403);
    // Halaman error yang informatif (bukan sekedar die)
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Akses Ditolak</title>'
        . '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;'
        . 'min-height:100vh;margin:0;background:#f1f5f9;}.box{background:#fff;border:1px solid #e2e8f0;'
        . 'border-radius:16px;padding:2rem 2.5rem;max-width:480px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08);}'
        . '.icon{font-size:3rem;margin-bottom:1rem;}.title{font-size:1.25rem;font-weight:700;color:#1e1b4b;}'
        . '.msg{color:#64748b;font-size:.875rem;margin:.75rem 0 1.5rem;}'
        . '.btn{display:inline-block;padding:.6rem 1.5rem;background:#1e1b4b;color:#fff;border-radius:8px;'
        . 'text-decoration:none;font-size:.875rem;font-weight:600;}</style></head><body>'
        . '<div class="box"><div class="icon">🔒</div>'
        . '<p class="title">Akses Ditolak</p>'
        . '<p class="msg">Buku Induk &amp; Ledger Rapor kelas <strong>' . htmlspecialchars($rombel['nama'])
        . '</strong> hanya dapat diunduh oleh Wali Kelas yang bertugas di kelas tersebut.</p>'
        . '<a class="btn" href="guru.php">← Kembali ke Portal Guru</a></div></body></html>';
    exit;
}

// ──────────────────────────────────────────────
// 4. AMBIL DATA KONSOLIDASI (ALL MAPEL + PRESENSI)
// ──────────────────────────────────────────────
$ledger = function_exists('fetch_ledger_rapor')
    ? fetch_ledger_rapor($conn, $rombel_id)
    : [];

$daftar_mapel   = $ledger['daftar_mapel']   ?? [];
$daftar_siswa   = $ledger['daftar_siswa']   ?? [];
$matriks_nilai  = $ledger['matriks_nilai']  ?? [];
$rekap_presensi = $ledger['rekap_presensi'] ?? [];
$wali_nama      = $ledger['wali_nama']      ?? $guruName;

// Jika fetch_ledger_rapor tidak tersedia, fallback dengan query langsung
if (empty($daftar_mapel)) {
    // Fallback: ambil semua mapel yang ada nilainya di rombel ini
    $r_mapel = $conn->query("
        SELECT DISTINCT mp.id, COALESCE(mp.nama_mapel, mp.nama) AS nama_mapel,
               COALESCE(mp.kkm, 75) AS kkm
        FROM mata_pelajaran mp
        INNER JOIN nilai_siswa ns ON ns.mapel_id = mp.id
        INNER JOIN data_siswa ds ON ds.id = ns.siswa_id
        WHERE ds.rombel_id = {$rombel_id}
        ORDER BY mp.id ASC
    ");
    while ($r_mapel && $row = $r_mapel->fetch_assoc()) {
        $daftar_mapel[] = $row;
    }
}
if (empty($daftar_siswa)) {
    // Fallback: ambil daftar siswa rombel
    $colsSiswa = ledger_columns($conn, 'data_siswa');
    $colNama   = isset($colsSiswa['nama_siswa']) ? 'nama_siswa' : (isset($colsSiswa['nama_lengkap']) ? 'nama_lengkap' : 'nama_siswa');
    $colNisn   = isset($colsSiswa['nisn']) ? 'nisn' : 'id';
    $r_siswa   = $conn->query("
        SELECT id, {$colNisn} AS nisn, {$colNama} AS nama_siswa
        FROM data_siswa WHERE rombel_id = {$rombel_id} ORDER BY {$colNama} ASC
    ");
    while ($r_siswa && $row = $r_siswa->fetch_assoc()) {
        $daftar_siswa[] = $row;
    }
}
if (empty($matriks_nilai) && !empty($daftar_mapel) && !empty($daftar_siswa)) {
    // Fallback: bangun matriks nilai AVG per siswa per mapel
    $stmt_n = $conn->prepare("
        SELECT siswa_id, mapel_id, AVG(nilai) AS avg_nilai
        FROM nilai_siswa ns
        INNER JOIN data_siswa ds ON ds.id = ns.siswa_id
        WHERE ds.rombel_id = ?
        GROUP BY siswa_id, mapel_id
    ");
    $stmt_n->bind_param("i", $rombel_id);
    $stmt_n->execute();
    $res_n = $stmt_n->get_result();
    while ($row = $res_n->fetch_assoc()) {
        $matriks_nilai[(int)$row['siswa_id']][(int)$row['mapel_id']] = (float)$row['avg_nilai'];
    }
    $stmt_n->close();
}
if (empty($rekap_presensi) && !empty($daftar_siswa)) {
    // Fallback: akumulasi presensi per siswa
    $stmt_p = $conn->prepare("
        SELECT siswa_id,
               SUM(CASE WHEN status = 'H' THEN 1 ELSE 0 END) AS total_h,
               SUM(CASE WHEN status = 'I' THEN 1 ELSE 0 END) AS total_i,
               SUM(CASE WHEN status = 'S' THEN 1 ELSE 0 END) AS total_s,
               SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) AS total_a
        FROM presensi
        WHERE rombel_id = ?
        GROUP BY siswa_id
    ");
    $stmt_p->bind_param("i", $rombel_id);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    while ($row = $res_p->fetch_assoc()) {
        $rekap_presensi[(int)$row['siswa_id']] = $row;
    }
    $stmt_p->close();
}

// ──────────────────────────────────────────────
// 5. STREAMING CSV
// ──────────────────────────────────────────────
$tahunAjaran = defined('TAHUN_AJARAN') ? TAHUN_AJARAN : '2026/2027';
$semester    = defined('SEMESTER_AKTIF') ? SEMESTER_AKTIF : 'Ganjil';
$cleanName   = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $rombel['nama']);
$filename    = 'BukuInduk_Kelas_' . $cleanName . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM — supaya Excel tidak mogok saat dibuka
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// ── Baris Judul ──
fputcsv($out, ['BUKU INDUK & LEDGER RAPOR — SMP NEGERI 7 PURWAKARTA']);
fputcsv($out, [
    'Kelas: ' . $rombel['nama'],
    'Tahun Ajaran: ' . $tahunAjaran,
    'Semester: ' . $semester,
    'Wali Kelas: ' . $wali_nama,
    'Dicetak: ' . date('d/m/Y H:i'),
]);
fputcsv($out, []); // baris kosong pemisah

// ── Header Kolom (crosstab dinamis) ──
$header = ['No', 'NISN', 'Nama Siswa'];
foreach ($daftar_mapel as $mapel) {
    $header[] = $mapel['nama_mapel'] ?? ($mapel['nama'] ?? 'Mapel');
}
$header[] = 'Rata-Rata Rapor';
$header[] = 'Predikat';
$header[] = 'Hadir (H)';
$header[] = 'Izin (I)';
$header[] = 'Sakit (S)';
$header[] = 'Alpa (A)';
$header[] = '% Kehadiran';
fputcsv($out, $header);

// ── Baris Data Siswa ──
$no               = 1;
$sumMapelKelas    = [];  // akumulasi untuk baris rata-rata kelas
$countMapelKelas  = [];
foreach ($daftar_mapel as $m) {
    $mId                   = (int) $m['id'];
    $sumMapelKelas[$mId]   = 0.0;
    $countMapelKelas[$mId] = 0;
}

foreach ($daftar_siswa as $siswa) {
    $sId      = (int) $siswa['id'];
    $presensi = $rekap_presensi[$sId] ?? [
        'total_h' => 0, 'total_i' => 0, 'total_s' => 0, 'total_a' => 0
    ];

    $row = [
        $no++,
        ($siswa['nisn'] && $siswa['nisn'] !== '-') ? $siswa['nisn'] : '-',
        $siswa['nama_siswa'] ?? ($siswa['nama_lengkap'] ?? ''),
    ];

    // Nilai per mata pelajaran
    $totalNilaiSiswa = 0.0;
    $mapelTerisi     = 0;
    foreach ($daftar_mapel as $mapel) {
        $mId = (int) $mapel['id'];
        $val = $matriks_nilai[$sId][$mId] ?? null;
        if ($val !== null) {
            $row[] = number_format((float) $val, 1, '.', '');
            $totalNilaiSiswa          += (float) $val;
            $mapelTerisi++;
            $sumMapelKelas[$mId]      += (float) $val;
            $countMapelKelas[$mId]++;
        } else {
            $row[] = ''; // Kosong = belum ada nilai
        }
    }

    // Rata-Rata Rapor & Predikat
    $rataSiswa = $mapelTerisi > 0 ? round($totalNilaiSiswa / $mapelTerisi, 1) : null;
    $row[] = $rataSiswa !== null ? number_format($rataSiswa, 1, '.', '') : '-';

    if ($rataSiswa === null)        { $predikat = '-'; }
    elseif ($rataSiswa >= 91)       { $predikat = 'A'; }
    elseif ($rataSiswa >= 81)       { $predikat = 'B'; }
    elseif ($rataSiswa >= 71)       { $predikat = 'C'; }
    elseif ($rataSiswa >= 61)       { $predikat = 'D'; }
    else                            { $predikat = 'E'; }
    $row[] = $predikat;

    // Kehadiran
    $h    = (int) ($presensi['total_h'] ?? 0);
    $i    = (int) ($presensi['total_i'] ?? 0);
    $s    = (int) ($presensi['total_s'] ?? 0);
    $a    = (int) ($presensi['total_a'] ?? 0);
    $total = $h + $i + $s + $a;
    $pct  = $total > 0 ? round($h / $total * 100, 1) : null;

    $row[] = $h;
    $row[] = $i;
    $row[] = $s;
    $row[] = $a;
    $row[] = $pct !== null ? $pct . '%' : '-';

    fputcsv($out, $row);
}

// ── Baris Rata-Rata Kelas ──
$footerRata = ['Rata-Rata Kelas', '', ''];
foreach ($daftar_mapel as $mapel) {
    $mId = (int) $mapel['id'];
    $c   = $countMapelKelas[$mId] ?? 0;
    $footerRata[] = $c > 0 ? number_format($sumMapelKelas[$mId] / $c, 1, '.', '') : '-';
}
// Isi kolom sisa (Rata2 Rapor, Predikat, H, I, S, A, %)
$footerRata = array_merge($footerRata, ['', '', '', '', '', '', '']);
fputcsv($out, $footerRata);

fclose($out);
exit;
