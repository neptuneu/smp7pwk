<?php
/**
 * ==============================================================
 * API TERPUSAT — SISTEM MANAJEMEN AKADEMIK
 * --------------------------------------------------------------
 * Semua operasi baca/tulis dari Portal Admin, Guru, dan Siswa
 * melewati file ini.
 *
 * Keamanan:
 *  - Wajib sesi login yang sah (require_login + validate_session)
 *  - Wajib role yang sesuai untuk setiap aksi
 *  - Wajib CSRF token pada setiap POST
 *  - Seluruh query memakai prepared statement (mysqli)
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode tidak diizinkan. Gunakan POST.'], 405);
}

// Dukungan body JSON selain form-urlencoded / multipart.
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '[]', true);
    if (is_array($decoded)) {
        $_POST = array_merge($_POST, $decoded);
    }
}

$user = require_login(null, true);
verify_csrf();

$action = trim((string) ($_POST['action'] ?? ''));
$role = $user['role'];

/* ==============================================================
 * HELPER INPUT & OTORISASI
 * ============================================================== */

function inp(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? trim((string) $value) : $default;
}

function inp_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

function inp_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return array_filter(array_map('trim', explode(',', $value)), static fn($v) => $v !== '');
    }
    return is_array($value) ? $value : [];
}

function assert_role(array $user, array $roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        json_response(['success' => false, 'message' => 'Hak akses ditolak untuk peran ' . $user['role'] . '.', 'code' => 'FORBIDDEN'], 403);
    }
}

function ok(string $message = 'Berhasil.', array $data = []): never
{
    json_response(['success' => true, 'message' => $message, 'data' => $data]);
}

function fail(string $message, int $status = 400): never
{
    json_response(['success' => false, 'message' => $message], $status);
}

function normalize_mapel_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = str_replace(['&', '/', '.', ',', '-', '_'], ' ', $value);
    return trim((string) preg_replace('/\s+/', ' ', $value));
}

/**
 * Mencari mapel_id dari kode/nama/alias (mis. "B. Indonesia", "PKN", "IPA").
 */
function resolve_mapel_id(mysqli $db, string $raw): ?int
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    $row = db_select_one($db, 'SELECT id FROM mata_pelajaran WHERE kode = ? OR nama = ? LIMIT 1', 'ss', [$raw, $raw]);
    if ($row) {
        return (int) $row['id'];
    }

    $aliases = [
        'matematika'        => 'Matematika',
        'mtk'               => 'Matematika',
        'ipa'               => 'Ilmu Pengetahuan Alam (IPA)',
        'ilmu pengetahuan alam' => 'Ilmu Pengetahuan Alam (IPA)',
        'sains'             => 'Ilmu Pengetahuan Alam (IPA)',
        'b indonesia'       => 'Bahasa Indonesia',
        'bahasa indonesia'  => 'Bahasa Indonesia',
        'indonesia'         => 'Bahasa Indonesia',
        'b inggris'         => 'Bahasa Inggris',
        'bahasa inggris'    => 'Bahasa Inggris',
        'inggris'           => 'Bahasa Inggris',
        'pkn'               => 'Pendidikan Pancasila',
        'ppkn'              => 'Pendidikan Pancasila',
        'pancasila'         => 'Pendidikan Pancasila',
        'pendidkan pancasila' => 'Pendidikan Pancasila',
        'pendidikan pancasila' => 'Pendidikan Pancasila',
        'pai'               => 'Pendidikan Agama & Budi Pekerti',
        'agama'             => 'Pendidikan Agama & Budi Pekerti',
        'pendidikan agama'  => 'Pendidikan Agama & Budi Pekerti',
        'ips'               => 'Ilmu Pengetahuan Sosial (IPS)',
        'ilmu pengetahuan sosial' => 'Ilmu Pengetahuan Sosial (IPS)',
        'informatika'       => 'Informatika',
        'tik'               => 'Informatika',
        'komputer'          => 'Informatika',
        'pjok'              => 'Pendidikan Jasmani (PJOK)',
        'penjas'            => 'Pendidikan Jasmani (PJOK)',
        'olahraga'          => 'Pendidikan Jasmani (PJOK)',
        'seni budaya'       => 'Seni Budaya',
        'seni'              => 'Seni Budaya',
        'sunda'             => 'Bahasa Sunda (Muatan Lokal)',
        'bahasa sunda'      => 'Bahasa Sunda (Muatan Lokal)',
        'mulok'             => 'Bahasa Sunda (Muatan Lokal)',
    ];

    $norm = normalize_mapel_key($raw);
    foreach ($aliases as $needle => $target) {
        if ($norm === $needle || str_contains($norm, $needle)) {
            $mapel = db_select_one($db, 'SELECT id FROM mata_pelajaran WHERE nama = ? LIMIT 1', 's', [$target]);
            if ($mapel) {
                return (int) $mapel['id'];
            }
        }
    }

    // Pencocokan kata kunci longgar (mis. "IPA/Matematika" sudah dipecah pemanggil).
    $like = '%' . $raw . '%';
    $row = db_select_one($db, 'SELECT id FROM mata_pelajaran WHERE nama LIKE ? LIMIT 1', 's', [$like]);
    return $row ? (int) $row['id'] : null;
}

/**
 * Parse string kelas guru menjadi kode kelas: "7A-7B, 9A-9I" -> ['7A','7B','9A',...].
 */
function parse_class_codes(string $kelasStr): array
{
    $s = strtoupper(trim($kelasStr));
    if ($s === '') {
        return [];
    }
    $s = str_replace([' ', '.', 'KELAS'], '', $s);
    $s = str_replace('VIII', '8', $s);
    $s = str_replace('VII', '7', $s);
    $s = str_replace('IX', '9', $s);

    $codes = [];
    foreach (preg_split('/[,\/;]+/', $s) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^([789])([A-I])-([789])([A-I])$/', $part, $m)) {
            $start = ord($m[2]);
            $end = ord($m[4]);
            for ($c = min($start, $end); $c <= max($start, $end); $c++) {
                $codes[] = $m[1] . chr($c);
            }
        } elseif (preg_match('/^([789])([A-I])-([A-I])$/', $part, $m)) {
            $start = ord($m[2]);
            $end = ord($m[3]);
            for ($c = min($start, $end); $c <= max($start, $end); $c++) {
                $codes[] = $m[1] . chr($c);
            }
        } elseif (preg_match('/^([789])([A-I])$/', $part, $m)) {
            $codes[] = $m[1] . $m[2];
        }
    }
    return array_values(array_unique($codes));
}

function rombel_name_from_code(string $code): string
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^([789])([A-I])$/', $code, $m)) {
        return $code;
    }
    $roman = ['7' => 'VII', '8' => 'VIII', '9' => 'IX'][$m[1]] ?? $m[1];
    return $roman . ' ' . $m[2];
}

function code_from_rombel_name(string $name): string
{
    $name = strtoupper(trim($name));
    if (preg_match('/^(VII|VIII|IX)\s*([A-I])$/', $name, $m)) {
        $tingkat = ['VII' => '7', 'VIII' => '8', 'IX' => '9'][$m[1]];
        return $tingkat . $m[2];
    }
    return str_replace(' ', '', $name);
}

/**
 * Mengambil satu kode kelas (mis. "8A") dari teks bebas seperti "Wali Kelas VIII.A".
 */
function extract_class_code(string $text): ?string
{
    $t = strtoupper($text);
    $t = str_replace('.', ' ', $t);
    $t = str_replace('VIII', '8', $t);
    $t = str_replace('VII', '7', $t);
    $t = str_replace('IX', '9', $t);
    if (preg_match('/\b([789])\s*([A-I])\b/', $t, $m)) {
        return $m[1] . $m[2];
    }
    return null;
}

function get_rombel(mysqli $db, string $nameOrCode): ?array
{
    $name = rombel_name_from_code($nameOrCode);
    return db_select_one($db, 'SELECT * FROM rombel WHERE nama = ? LIMIT 1', 's', [$name]);
}

function current_guru(mysqli $db, array $user): array
{
    $guru = db_select_one($db, 'SELECT * FROM data_guru WHERE user_id = ? LIMIT 1', 'i', [(int) $user['id']]);
    if (!$guru) {
        fail('Akun ini belum terhubung ke data guru. Hubungi administrator.', 403);
    }
    return $guru;
}

function is_kepsek(array $user): bool
{
    return ($user['username'] ?? '') === KEPSEK_USERNAME;
}

/**
 * Sinkronisasi penugasan guru (pengampu + wali kelas) dari data admin.
 */
function sync_teacher_assignments(mysqli $db, array $guru, string $mapelStr, string $kelasStr, string $waliStr): void
{
    $guruId = (int) $guru['id'];
    $tahun = TAHUN_AJARAN;
    $semester = SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil';

    // Hapus penugasan lama pada tahun ajaran & semester aktif, lalu buat ulang.
    db_execute(
        $db,
        'DELETE FROM pengampu WHERE guru_id = ? AND tahun_ajaran = ? AND semester = ?',
        'iss',
        [$guruId, $tahun, $semester]
    );

    $mapelIds = [];
    foreach (preg_split('/[\/,]+/', $mapelStr) as $part) {
        $id = resolve_mapel_id($db, $part);
        if ($id) {
            $mapelIds[$id] = true;
        }
    }
    $classCodes = parse_class_codes($kelasStr);
    if (empty($classCodes)) {
        $singleCode = extract_class_code($kelasStr);
        if ($singleCode !== null) {
            $classCodes = [$singleCode];
        }
    }
    foreach (array_keys($mapelIds) as $mapelId) {
        foreach ($classCodes as $code) {
            $rombel = get_rombel($db, $code);
            if (!$rombel) {
                continue;
            }
            try {
                db_insert(
                    $db,
                    'INSERT INTO pengampu (guru_id, mapel_id, rombel_id, tahun_ajaran, semester) VALUES (?, ?, ?, ?, ?)',
                    'iiiss',
                    [$guruId, $mapelId, (int) $rombel['id'], $tahun, $semester]
                );
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062) {
                    throw $e;
                }
            }
        }
    }

    // Wali kelas: hanya dari teks "Wali Kelas ..." — JANGAN memakai string kelas,
    // karena string kelas hanya menunjukkan kelas yang diampu, bukan wali kelas.
    db_execute($db, 'UPDATE rombel SET wali_guru_id = NULL WHERE wali_guru_id = ?', 'i', [$guruId]);
    $waliCode = extract_class_code($waliStr);
    if ($waliCode !== null) {
        $rombel = get_rombel($db, $waliCode);
        if ($rombel) {
            db_execute($db, 'UPDATE rombel SET wali_guru_id = ? WHERE id = ?', 'ii', [$guruId, (int) $rombel['id']]);
        }
    }
}

/* ==============================================================
 * FUNGSI DATA BERSAMA
 * ============================================================== */

function fetch_teachers(mysqli $db): array
{
    $rows = db_select(
        $db,
        "SELECT g.*, MAX(u.username) AS username, MAX(u.is_active) AS user_active,
                GROUP_CONCAT(DISTINCT r.nama ORDER BY r.nama SEPARATOR ',') AS wali_rombel
         FROM data_guru g
         LEFT JOIN users u ON u.id = g.user_id
         LEFT JOIN rombel r ON r.wali_guru_id = g.id
         GROUP BY g.id
         ORDER BY CAST(g.kode AS UNSIGNED), g.nama_guru"
    );

    $pengampu = db_select(
        $db,
        "SELECT p.guru_id, m.nama AS mapel, r.nama AS rombel
         FROM pengampu p
         JOIN mata_pelajaran m ON m.id = p.mapel_id
         JOIN rombel r ON r.id = p.rombel_id
         WHERE p.tahun_ajaran = ? AND p.semester = ?
         ORDER BY r.tingkat, r.nama",
        'ss',
        [TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
    );
    $byGuru = [];
    foreach ($pengampu as $p) {
        $byGuru[(int) $p['guru_id']]['mapel'][$p['mapel']] = true;
        $byGuru[(int) $p['guru_id']]['kelas'][code_from_rombel_name($p['rombel'])] = true;
    }

    $result = [];
    foreach ($rows as $g) {
        $gid = (int) $g['id'];
        $waliList = $g['wali_rombel'] ? explode(',', $g['wali_rombel']) : [];
        $wali = 'Bukan Wali Kelas';
        if (!empty($waliList)) {
            $wali = 'Wali Kelas ' . implode(', ', $waliList);
        } elseif (!empty($g['wali_kelas']) && stripos($g['wali_kelas'], 'kepala sekolah') !== false) {
            $wali = 'Kepala Sekolah';
        } elseif (!empty($g['wali_kelas']) && $g['wali_kelas'] !== '-') {
            $wali = $g['wali_kelas'];
        }

        $mapelNames = array_keys($byGuru[$gid]['mapel'] ?? []);
        if (empty($mapelNames) && !empty($g['mata_pelajaran'])) {
            $mapelNames = [$g['mata_pelajaran']];
        }
        $kelasCodes = array_keys($byGuru[$gid]['kelas'] ?? []);

        $result[] = [
            'id'             => $gid,
            'kode'           => $g['kode'],
            'name'           => $g['nama_guru'],
            'nip'            => $g['nip'] ?: '-',
            'mapel'          => implode(', ', $mapelNames) ?: ($g['mata_pelajaran'] ?: 'Tenaga Pendidik / Staf'),
            'kelas'          => implode(', ', $kelasCodes) ?: ($g['kelas'] ?: '-'),
            'wali'           => $wali,
            'jam_per_minggu' => (int) $g['jam_per_minggu'],
            'phone'          => $g['phone'] ?: '-',
            'status'         => $g['status'],
            'username'       => $g['username'] ?? '-',
            'has_account'    => !empty($g['user_id']),
        ];
    }
    return $result;
}

function fetch_students(mysqli $db): array
{
    $rows = db_select(
        $db,
        "SELECT s.*, r.nama AS rombel_nama, r.tingkat, u.username
         FROM data_siswa s
         LEFT JOIN rombel r ON r.id = s.rombel_id
         LEFT JOIN users u ON u.id = s.user_id
         ORDER BY r.tingkat, r.nama, s.nama_siswa"
    );

    $result = [];
    foreach ($rows as $s) {
        $result[] = [
            'id'          => (int) $s['id'],
            'nisn'        => $s['nisn'] ?: '-',
            'name'        => $s['nama_siswa'],
            'gender'      => $s['gender'],
            'class'       => $s['rombel_nama'] ?: '-',
            'tingkat'     => $s['tingkat'] !== null ? (int) $s['tingkat'] : null,
            'parent'      => $s['orang_tua'] ?: '-',
            'phone'       => $s['telepon'] ?: '-',
            'status'      => $s['status'],
            'username'    => $s['username'] ?? '-',
            'has_account' => !empty($s['user_id']),
            'catatan'     => $s['catatan_wali'] ?? '',
        ];
    }
    return $result;
}

function fetch_mapel(mysqli $db): array
{
    $rows = db_select(
        $db,
        "SELECT m.*, g.nama_guru AS lead,
                (SELECT COUNT(DISTINCT p.rombel_id) FROM pengampu p WHERE p.mapel_id = m.id) AS rombel_tercover
         FROM mata_pelajaran m
         LEFT JOIN data_guru g ON g.id = m.koordinator_guru_id
         ORDER BY m.kode"
    );
    $result = [];
    foreach ($rows as $m) {
        $result[] = [
            'id'             => (int) $m['id'],
            'code'           => $m['kode'],
            'name'           => $m['nama'],
            'group'          => $m['kelompok'],
            'hours'          => (int) $m['jam_per_minggu'],
            'kkm'            => (int) $m['kkm'],
            'lead'           => $m['lead'] ?: 'Belum Ditugaskan',
            'rombelCovered'  => max((int) $m['rombel_tercover'], (int) $m['cakupan_rombel']),
        ];
    }
    return $result;
}

function fetch_rombel(mysqli $db): array
{
    $rows = db_select(
        $db,
        "SELECT r.*, g.nama_guru AS wali,
                (SELECT COUNT(*) FROM data_siswa s WHERE s.rombel_id = r.id AND s.status = 'Aktif') AS jumlah,
                (SELECT COUNT(DISTINCT p.mapel_id) FROM pengampu p WHERE p.rombel_id = r.id) AS jumlah_mapel
         FROM rombel r
         LEFT JOIN data_guru g ON g.id = r.wali_guru_id
         ORDER BY r.tingkat, r.nama"
    );
    $result = [];
    foreach ($rows as $r) {
        $result[] = [
            'id'          => (int) $r['id'],
            'name'        => $r['nama'],
            'grade'       => (string) $r['tingkat'],
            'tingkat'     => (int) $r['tingkat'],
            'wali'        => $r['wali'] ?: 'Belum Ditentukan',
            'count'       => (int) $r['jumlah'],
            'max'         => (int) $r['kapasitas'],
            'room'        => $r['ruang'] ?: '-',
            'jumlah_mapel' => (int) $r['jumlah_mapel'],
        ];
    }
    return $result;
}

function fetch_stats(mysqli $db): array
{
    $row = db_select_one(
        $db,
        "SELECT
            (SELECT COUNT(*) FROM data_siswa WHERE status = 'Aktif') AS total_siswa,
            (SELECT COUNT(*) FROM data_siswa) AS total_siswa_all,
            (SELECT COUNT(*) FROM data_guru WHERE status = 'Aktif') AS total_guru,
            (SELECT COUNT(*) FROM mata_pelajaran) AS total_mapel,
            (SELECT COUNT(*) FROM rombel) AS total_rombel,
            (SELECT COUNT(*) FROM nilai_siswa) AS total_nilai,
            (SELECT COUNT(*) FROM jurnal_guru) AS total_jurnal,
            (SELECT COUNT(*) FROM presensi) AS total_presensi,
            (SELECT COUNT(*) FROM users WHERE is_active = 0 AND role IN ('guru', 'siswa')) AS total_pending,
            (SELECT ROUND(AVG(nilai), 1) FROM nilai_siswa) AS rata_nilai,
            (SELECT ROUND(AVG(kkm), 0) FROM mata_pelajaran) AS rata_kkm"
    );

    $dist = db_select(
        $db,
        "SELECT r.tingkat, COUNT(s.id) AS jumlah
         FROM rombel r
         LEFT JOIN data_siswa s ON s.rombel_id = r.id AND s.status = 'Aktif'
         GROUP BY r.tingkat ORDER BY r.tingkat"
    );

    return [
        'total_siswa'     => (int) $row['total_siswa'],
        'total_siswa_all' => (int) $row['total_siswa_all'],
        'total_guru'      => (int) $row['total_guru'],
        'total_mapel'     => (int) $row['total_mapel'],
        'total_rombel'    => (int) $row['total_rombel'],
        'total_nilai'     => (int) $row['total_nilai'],
        'total_jurnal'    => (int) $row['total_jurnal'],
        'total_presensi'  => (int) $row['total_presensi'],
        'total_pending'   => (int) ($row['total_pending'] ?? 0),
        'rata_nilai'      => $row['rata_nilai'] !== null ? (float) $row['rata_nilai'] : 0.0,
        'rata_kkm'        => (int) $row['rata_kkm'],
        'distribusi'      => array_map(static fn($d) => ['tingkat' => (int) $d['tingkat'], 'jumlah' => (int) $d['jumlah']], $dist),
    ];
}

function fetch_pending_users(mysqli $db): array
{
    $rows = db_select(
        $db,
        "SELECT u.id, u.username, u.role, u.is_active, u.created_at,
                COALESCE(u.nama_lengkap, g.nama_guru, s.nama_siswa) AS nama,
                g.nip, s.nisn
         FROM users u
         LEFT JOIN data_guru g ON g.user_id = u.id
         LEFT JOIN data_siswa s ON s.user_id = u.id
         WHERE u.is_active = 0 AND u.role IN ('guru', 'siswa')
         ORDER BY u.created_at DESC"
    );

    return array_map(static function (array $r): array {
        return [
            'id'         => (int) $r['id'],
            'username'   => (string) $r['username'],
            'nama'       => (string) ($r['nama'] ?: $r['username']),
            'role'       => (string) $r['role'],
            'nip'        => (string) ($r['nip'] ?? '-'),
            'nisn'       => (string) ($r['nisn'] ?? '-'),
            'is_active'  => (int) $r['is_active'],
            'created_at' => (string) $r['created_at'],
        ];
    }, $rows);
}

function fetch_monitoring(mysqli $db): array
{
    $tahun = TAHUN_AJARAN;
    $semester = SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil';
    $rows = db_select(
        $db,
        "SELECT p.id, p.guru_id, p.mapel_id, p.rombel_id,
                g.nama_guru, m.nama AS mapel, m.kkm, r.nama AS rombel, r.tingkat,
                (SELECT COUNT(DISTINCT n.jenis_penilaian)
                   FROM nilai_siswa n JOIN data_siswa s ON s.id = n.siswa_id
                  WHERE s.rombel_id = p.rombel_id AND n.mapel_id = p.mapel_id) AS columns_count,
                (SELECT ROUND(AVG(n.nilai), 1)
                   FROM nilai_siswa n JOIN data_siswa s ON s.id = n.siswa_id
                  WHERE s.rombel_id = p.rombel_id AND n.mapel_id = p.mapel_id) AS class_avg,
                (SELECT COUNT(*) FROM jurnal_guru j
                  WHERE j.guru_id = p.guru_id AND j.mapel_id = p.mapel_id AND j.rombel_id = p.rombel_id) AS journal_count,
                (SELECT COUNT(DISTINCT CONCAT(pr.tanggal, '|', pr.pertemuan))
                   FROM presensi pr
                  WHERE pr.rombel_id = p.rombel_id AND pr.mapel_id = p.mapel_id AND pr.guru_id = p.guru_id) AS attendance_count,
                (SELECT MAX(n.updated_at)
                   FROM nilai_siswa n JOIN data_siswa s ON s.id = n.siswa_id
                  WHERE s.rombel_id = p.rombel_id AND n.mapel_id = p.mapel_id) AS last_grade,
                (SELECT MAX(j.updated_at) FROM jurnal_guru j
                  WHERE j.guru_id = p.guru_id AND j.mapel_id = p.mapel_id AND j.rombel_id = p.rombel_id) AS last_journal
         FROM pengampu p
         JOIN data_guru g ON g.id = p.guru_id
         JOIN mata_pelajaran m ON m.id = p.mapel_id
         JOIN rombel r ON r.id = p.rombel_id
         WHERE p.tahun_ajaran = ? AND p.semester = ?
         ORDER BY r.tingkat, r.nama, g.nama_guru",
        'ss',
        [$tahun, $semester]
    );

    $result = [];
    foreach ($rows as $row) {
        $cols = (int) $row['columns_count'];
        $status = $cols >= 3 ? 'Lengkap' : ($cols > 0 ? 'Aktif Mengisi' : 'Belum Input');
        $last = null;
        foreach ([$row['last_grade'], $row['last_journal']] as $candidate) {
            if ($candidate && ($last === null || strtotime($candidate) > strtotime($last))) {
                $last = $candidate;
            }
        }
        $result[] = [
            'id'              => (int) $row['id'],
            'guruId'          => (int) $row['guru_id'],
            'mapelId'         => (int) $row['mapel_id'],
            'rombelId'        => (int) $row['rombel_id'],
            'teacherName'     => $row['nama_guru'],
            'subject'         => $row['mapel'],
            'className'       => $row['rombel'],
            'grade'           => (string) $row['tingkat'],
            'columnsCount'    => $cols,
            'classAvg'        => $row['class_avg'] !== null ? (float) $row['class_avg'] : '-',
            'kkm'             => (int) $row['kkm'],
            'attendanceCount' => (int) $row['attendance_count'],
            'journalCount'    => (int) $row['journal_count'],
            'lastUpdate'      => $last ? date('d M Y H:i', strtotime($last)) : 'Belum ada input',
            'status'          => $status,
        ];
    }
    return $result;
}

function fetch_archive_students(mysqli $db): array
{
    $rows = db_select($db, 'SELECT * FROM arsip_siswa ORDER BY created_at DESC, id DESC');
    return array_map(static fn($a) => [
        'id'          => (int) $a['id'],
        'siswa_id'    => $a['siswa_id'] ? (int) $a['siswa_id'] : null,
        'nisn'        => $a['nisn'] ?: '-',
        'name'        => $a['nama_siswa'],
        'lastClass'   => $a['last_class'] ?: '-',
        'status'      => $a['status'],
        'time'        => $a['time_label'] ?: '-',
        'year'        => $a['tahun'] ? (string) $a['tahun'] : '-',
        'destination' => $a['destination'] ?: '-',
        'phone'       => $a['phone'] ?: '-',
    ], $rows);
}

function fetch_archive_teachers(mysqli $db): array
{
    $rows = db_select($db, 'SELECT * FROM arsip_guru ORDER BY created_at DESC, id DESC');
    return array_map(static fn($a) => [
        'id'          => (int) $a['id'],
        'guru_id'     => $a['guru_id'] ? (int) $a['guru_id'] : null,
        'nip'         => $a['nip'] ?: '-',
        'name'        => $a['nama_guru'],
        'lastSubject' => $a['last_subject'] ?: '-',
        'status'      => $a['status'],
        'time'        => $a['time_label'] ?: '-',
        'year'        => $a['tahun'] ? (string) $a['tahun'] : '-',
        'sk'          => $a['sk'] ?: '-',
    ], $rows);
}

function fetch_settings(mysqli $db): array
{
    $rows = db_select($db, 'SELECT kunci, nilai FROM pengaturan');
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['kunci']] = $r['nilai'];
    }
    return $settings;
}

function fetch_activity(mysqli $db): array
{
    $journals = db_select(
        $db,
        "SELECT j.id, g.nama_guru, j.materi, j.kegiatan, m.nama AS mapel, r.nama AS rombel,
                j.updated_at, j.created_at
         FROM jurnal_guru j
         JOIN data_guru g ON g.id = j.guru_id
         JOIN mata_pelajaran m ON m.id = j.mapel_id
         JOIN rombel r ON r.id = j.rombel_id
         ORDER BY j.updated_at DESC LIMIT 6"
    );

    $grades = db_select(
        $db,
        "SELECT g.nama_guru, m.nama AS mapel, r.nama AS rombel, COUNT(*) AS jumlah,
                MAX(n.updated_at) AS updated_at
         FROM nilai_siswa n
         JOIN data_siswa s ON s.id = n.siswa_id
         JOIN data_guru g ON g.id = n.guru_id
         JOIN mata_pelajaran m ON m.id = n.mapel_id
         LEFT JOIN rombel r ON r.id = n.rombel_id
         GROUP BY g.id, m.id, r.id
         ORDER BY updated_at DESC LIMIT 4"
    );

    $items = [];
    foreach ($journals as $j) {
        $items[] = [
            'type'    => 'jurnal',
            'title'   => $j['nama_guru'],
            'text'    => 'Menulis jurnal "' . $j['materi'] . '" (' . $j['mapel'] . ' · ' . $j['rombel'] . ')',
            'time'    => $j['updated_at'] ?: $j['created_at'],
        ];
    }
    foreach ($grades as $g) {
        $items[] = [
            'type'  => 'nilai',
            'title' => $g['nama_guru'],
            'text'  => 'Memperbarui ' . $g['jumlah'] . ' nilai ' . $g['mapel'] . ' (' . ($g['rombel'] ?: '-') . ')',
            'time'  => $g['updated_at'],
        ];
    }
    usort($items, static fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
    return array_slice($items, 0, 6);
}

/* ==============================================================
 * HELPER MATERI / MODUL AJAR
 * ============================================================== */
function materi_dir(): string
{
    $dir = __DIR__ . '/storage/materi';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $legacy = __DIR__ . '/uploads_materi';
    if (!is_dir($legacy)) {
        @mkdir($legacy, 0755, true);
    }
    return $dir;
}

function ukuran_file(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return (string) round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}

function tgl_pendek(string $datetime): string
{
    $t = strtotime($datetime);
    if (!$t) {
        return $datetime;
    }
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $t) . ' ' . $bulan[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** Rombel + mapel dari input rombel / mapel. @return array{0:?array,1:?array} */
function materi_resolve(mysqli $db): array
{
    $rombel = get_rombel($db, inp('rombel'));
    $mapelIn = inp('mapel');
    $mapel = db_select_one($db, 'SELECT * FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [$mapelIn, $mapelIn]);
    return [$rombel, $mapel];
}

function materi_is_pengampu(mysqli $db, int $guruId, int $mapelId, int $rombelId): bool
{
    return (bool) db_select_one(
        $db,
        'SELECT id FROM pengampu WHERE guru_id = ? AND mapel_id = ? AND rombel_id = ? AND tahun_ajaran = ? AND semester = ?',
        'iiiss',
        [$guruId, $mapelId, $rombelId, TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
    );
}

function materi_format(array $r, int $viewerGuruId): array
{
    $nama = (string) ($r['nama_file'] ?? '');
    return [
        'id'        => (int) $r['id'],
        'title'     => (string) $r['judul'],
        'desc'      => (string) ($r['deskripsi'] ?? ''),
        'subject'   => (string) $r['mata_pelajaran'],
        'teacher'   => (string) ($r['nama_guru'] ?? ''),
        'fileName'  => $nama,
        'ext'       => strtoupper(pathinfo($nama, PATHINFO_EXTENSION)),
        'size'      => ukuran_file((int) ($r['ukuran'] ?? 0)),
        'status'    => (string) $r['status'],
        'downloads' => (int) ($r['jumlah_unduh'] ?? 0),
        'date'      => tgl_pendek((string) $r['created_at']),
        'mine'      => $viewerGuruId > 0 && (int) ($r['guru_id'] ?? 0) === $viewerGuruId,
        'url'       => 'download_materi.php?id=' . (int) $r['id'],
    ];
}

/* ==============================================================
 * ROUTER AKSI
 * ============================================================== */
try {
    switch ($action) {

        /* ======================================================
         * ADMIN — BOOTSTRAP & DASHBOARD
         * ====================================================== */
        case 'admin_bootstrap':
            assert_role($user, ['admin']);
            ok('Data dashboard dimuat.', [
                'stats'            => fetch_stats($koneksi),
                'teachers'         => fetch_teachers($koneksi),
                'students'         => fetch_students($koneksi),
                'mapel'            => fetch_mapel($koneksi),
                'rombel'           => fetch_rombel($koneksi),
                'monitoring'       => fetch_monitoring($koneksi),
                'archivedStudents' => fetch_archive_students($koneksi),
                'archivedTeachers' => fetch_archive_teachers($koneksi),
                'settings'         => fetch_settings($koneksi),
                'activity'         => fetch_activity($koneksi),
                'pendingUsers'     => fetch_pending_users($koneksi),
            ]);

        case 'admin_stats':
            assert_role($user, ['admin']);
            ok('Statistik dimuat.', ['stats' => fetch_stats($koneksi)]);

        case 'get_pending_users':
            assert_role($user, ['admin']);
            ok('Daftar pengguna pending dimuat.', ['pendingUsers' => fetch_pending_users($koneksi)]);

        case 'approve_user':
            assert_role($user, ['admin']);
            $targetId = inp_int('id');
            if ($targetId <= 0) fail('ID pengguna tidak valid.');
            $targetUser = db_select_one($koneksi, 'SELECT id, username, role FROM users WHERE id = ?', 'i', [$targetId]);
            if (!$targetUser) fail('Pengguna tidak ditemukan.');
            
            // Aktifkan akun
            db_execute($koneksi, 'UPDATE users SET is_active = 1 WHERE id = ?', 'i', [$targetId]);
            if ($targetUser['role'] === 'guru') {
                db_execute($koneksi, "UPDATE data_guru SET status = 'Aktif' WHERE user_id = ?", 'i', [$targetId]);
            } elseif ($targetUser['role'] === 'siswa') {
                db_execute($koneksi, "UPDATE data_siswa SET status = 'Aktif', catatan_wali = '' WHERE user_id = ?", 'i', [$targetId]);
            }

            ok('Akun ' . $targetUser['username'] . ' (' . ucfirst($targetUser['role']) . ') berhasil disetujui.', [
                'pendingUsers' => fetch_pending_users($koneksi),
                'stats'        => fetch_stats($koneksi),
            ]);

        case 'reject_user':
            assert_role($user, ['admin']);
            $targetId = inp_int('id');
            if ($targetId <= 0) fail('ID pengguna tidak valid.');
            $targetUser = db_select_one($koneksi, 'SELECT id, username, role FROM users WHERE id = ?', 'i', [$targetId]);
            if (!$targetUser) fail('Pengguna tidak ditemukan.');

            // Hapus profil dan akun
            if ($targetUser['role'] === 'guru') {
                db_execute($koneksi, 'DELETE FROM data_guru WHERE user_id = ?', 'i', [$targetId]);
            } elseif ($targetUser['role'] === 'siswa') {
                db_execute($koneksi, 'DELETE FROM data_siswa WHERE user_id = ?', 'i', [$targetId]);
            }
            db_execute($koneksi, 'DELETE FROM users WHERE id = ?', 'i', [$targetId]);

            ok('Pendaftaran akun ' . $targetUser['username'] . ' berhasil ditolak.', [
                'pendingUsers' => fetch_pending_users($koneksi),
                'stats'        => fetch_stats($koneksi),
            ]);

        /* ======================================================
         * ADMIN — CRUD GURU
         * ====================================================== */
        case 'save_teacher':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $name = inp('name');
            $nip = inp('nip');
            $mapelStr = inp('mapel');
            $wali = inp('wali', 'Bukan Wali Kelas');
            $kelas = inp('kelas');
            $jam = inp_int('jam_per_minggu');
            $phone = inp('phone');
            $status = inp('status', 'Aktif');
            $password = inp('password');

            if (mb_strlen($name) < 3) fail('Nama guru wajib diisi (minimal 3 karakter).');
            if ($jam < 0 || $jam > 60) fail('Jam per minggu tidak wajar.');
            if ($password !== '' && mb_strlen($password) < PASSWORD_MIN_LENGTH) {
                fail('Kata sandi minimal ' . PASSWORD_MIN_LENGTH . ' karakter.');
            }
            if (!in_array($status, ['Aktif', 'Cuti', 'Non-Aktif'], true)) $status = 'Aktif';

            if ($nip !== '') {
                $dup = db_select_one($koneksi, 'SELECT id FROM data_guru WHERE nip = ? AND id <> ? LIMIT 1', 'si', [$nip, $id]);
                if ($dup) fail('NIP tersebut sudah dipakai guru lain.');
            }

            $koneksi->begin_transaction();
            if ($id > 0) {
                $existing = db_select_one($koneksi, 'SELECT * FROM data_guru WHERE id = ?', 'i', [$id]);
                if (!$existing) throw new RuntimeException('Data guru tidak ditemukan.');
                db_execute(
                    $koneksi,
                    'UPDATE data_guru SET nama_guru = ?, nip = ?, mata_pelajaran = ?, kelas = ?, wali_kelas = ?, phone = ?, jam_per_minggu = ?, status = ? WHERE id = ?',
                    'ssssssisi',
                    [$name, $nip !== '' ? $nip : null, $mapelStr, $kelas, $wali, $phone, $jam, $status, $id]
                );
                $guruId = $id;

                // Perbarui kata sandi akun bila diisi.
                if (!empty($existing['user_id'])) {
                    if ($password !== '') {
                        db_execute($koneksi, 'UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), (int) $existing['user_id']]);
                    }
                } else {
                    $username = $nip !== '' ? $nip : ('guru' . $guruId);
                    if (!db_select_one($koneksi, 'SELECT id FROM users WHERE username = ?', 's', [$username])) {
                        $userId = db_insert(
                            $koneksi,
                            'INSERT INTO users (username, nama_lengkap, password_hash, role) VALUES (?, ?, ?, ?)',
                            'ssss',
                            [$username, $name, password_hash($password !== '' ? $password : DEFAULT_TEACHER_PASSWORD, PASSWORD_DEFAULT), 'guru']
                        );
                        db_execute($koneksi, 'UPDATE data_guru SET user_id = ? WHERE id = ?', 'ii', [$userId, $guruId]);
                    }
                }
            } else {
                $nextKodeRow = db_select_one($koneksi, 'SELECT COALESCE(MAX(CAST(kode AS UNSIGNED)), 0) + 1 AS next_kode FROM data_guru');
                $kode = (string) ((int) ($nextKodeRow['next_kode'] ?? 1));
                $guruId = db_insert(
                    $koneksi,
                    'INSERT INTO data_guru (kode, nip, nama_guru, mata_pelajaran, kelas, wali_kelas, phone, jam_per_minggu, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    'sssssssis',
                    [$kode, $nip !== '' ? $nip : null, $name, $mapelStr, $kelas, $wali, $phone, $jam, $status]
                );
                $username = $nip !== '' ? $nip : ('guru' . $guruId);
                if (!db_select_one($koneksi, 'SELECT id FROM users WHERE username = ?', 's', [$username])) {
                    $userId = db_insert(
                        $koneksi,
                        'INSERT INTO users (username, nama_lengkap, password_hash, role) VALUES (?, ?, ?, ?)',
                        'ssss',
                        [$username, $name, password_hash($password !== '' ? $password : DEFAULT_TEACHER_PASSWORD, PASSWORD_DEFAULT), 'guru']
                    );
                    db_execute($koneksi, 'UPDATE data_guru SET user_id = ? WHERE id = ?', 'ii', [$userId, $guruId]);
                }
            }

            $guruRow = db_select_one($koneksi, 'SELECT * FROM data_guru WHERE id = ?', 'i', [$guruId]);
            sync_teacher_assignments($koneksi, $guruRow, $mapelStr, $kelas, $wali);
            $koneksi->commit();
            ok('Data guru berhasil disimpan.', ['id' => $guruId]);

        case 'delete_teacher':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $guru = db_select_one($koneksi, 'SELECT * FROM data_guru WHERE id = ?', 'i', [$id]);
            if (!$guru) fail('Data guru tidak ditemukan.');
            $koneksi->begin_transaction();
            db_execute($koneksi, 'DELETE FROM data_guru WHERE id = ?', 'i', [$id]);
            if (!empty($guru['user_id'])) {
                db_execute($koneksi, 'DELETE FROM users WHERE id = ?', 'i', [(int) $guru['user_id']]);
            }
            $koneksi->commit();
            ok('Data guru beserta akunnya berhasil dihapus.');

        case 'import_teachers':
            assert_role($user, ['admin']);
            if (empty($_FILES['csv_file']['tmp_name']) || ($_FILES['csv_file']['error'] ?? 1) !== UPLOAD_ERR_OK) {
                fail('Pilih berkas CSV terlebih dahulu.');
            }
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if (!$handle) fail('Berkas tidak dapat dibaca.');
            fgetcsv($handle, 4000, ',');
            $inserted = 0;
            $skipped = 0;
            $koneksi->begin_transaction();
            while (($row = fgetcsv($handle, 4000, ',')) !== false) {
                $nip = trim((string) ($row[0] ?? ''));
                $nama = trim((string) ($row[1] ?? ''));
                $mapel = trim((string) ($row[2] ?? ''));
                $wali = trim((string) ($row[3] ?? 'Bukan Wali Kelas'));
                $phone = trim((string) ($row[4] ?? ''));
                $status = trim((string) ($row[5] ?? 'Aktif')) ?: 'Aktif';
                if ($nama === '' || ($nip !== '' && db_select_one($koneksi, 'SELECT id FROM data_guru WHERE nip = ?', 's', [$nip]))) {
                    $skipped++;
                    continue;
                }
                $nextKodeRow = db_select_one($koneksi, 'SELECT COALESCE(MAX(CAST(kode AS UNSIGNED)), 0) + 1 AS next_kode FROM data_guru');
                $kode = (string) ((int) ($nextKodeRow['next_kode'] ?? 1));
                $guruId = db_insert(
                    $koneksi,
                    'INSERT INTO data_guru (kode, nip, nama_guru, mata_pelajaran, kelas, wali_kelas, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    'ssssssss',
                    [$kode, $nip !== '' ? $nip : null, $nama, $mapel, $wali, $wali, $phone, $status]
                );
                $username = $nip !== '' ? $nip : ('guru' . $guruId);
                if (!db_select_one($koneksi, 'SELECT id FROM users WHERE username = ?', 's', [$username])) {
                    $userId = db_insert(
                        $koneksi,
                        'INSERT INTO users (username, nama_lengkap, password_hash, role) VALUES (?, ?, ?, ?)',
                        'ssss',
                        [$username, $nama, password_hash(DEFAULT_TEACHER_PASSWORD, PASSWORD_DEFAULT), 'guru']
                    );
                    db_execute($koneksi, 'UPDATE data_guru SET user_id = ? WHERE id = ?', 'ii', [$userId, $guruId]);
                }
                $guruRow = db_select_one($koneksi, 'SELECT * FROM data_guru WHERE id = ?', 'i', [$guruId]);
                sync_teacher_assignments($koneksi, $guruRow, $mapel, $wali, $wali);
                $inserted++;
            }
            fclose($handle);
            $koneksi->commit();
            ok("Import selesai: {$inserted} guru ditambahkan, {$skipped} dilewati.", ['inserted' => $inserted, 'skipped' => $skipped]);

        /* ======================================================
         * ADMIN — CRUD SISWA
         * ====================================================== */
        case 'save_student':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $nisn = inp('nisn');
            $name = inp('name');
            $gender = inp('gender', 'L');
            $class = inp('class');
            $status = inp('status', 'Aktif');
            $parent = inp('parent', '-');
            $phone = inp('phone', '-');

            if (mb_strlen($name) < 3) fail('Nama siswa wajib diisi (minimal 3 karakter).');
            if (!in_array($gender, ['L', 'P'], true)) $gender = 'L';
            $statusMap = ['Aktif Belajar' => 'Aktif', 'Lulus' => 'Lulus', 'Mutasi' => 'Mutasi', 'Mengundurkan Diri' => 'Mengundurkan Diri'];
            $status = $statusMap[$status] ?? $status;
            if (!in_array($status, ['Aktif', 'Lulus', 'Mutasi', 'Mengundurkan Diri'], true)) $status = 'Aktif';

            $rombel = $class !== '-' ? get_rombel($koneksi, $class) : null;
            $rombelId = $rombel ? (int) $rombel['id'] : null;

            if ($nisn !== '') {
                $dup = db_select_one($koneksi, 'SELECT id FROM data_siswa WHERE nisn = ? AND id <> ? LIMIT 1', 'si', [$nisn, $id]);
                if ($dup) fail('NISN tersebut sudah terdaftar pada siswa lain.');
            }

            $koneksi->begin_transaction();
            if ($id > 0) {
                db_execute(
                    $koneksi,
                    'UPDATE data_siswa SET nisn = ?, nama_siswa = ?, rombel_id = ?, gender = ?, orang_tua = ?, telepon = ?, status = ? WHERE id = ?',
                    'ssissssi',
                    [$nisn !== '' ? $nisn : null, $name, $rombelId, $gender, $parent, $phone, $status, $id]
                );
                $studentId = $id;
            } else {
                $studentId = db_insert(
                    $koneksi,
                    'INSERT INTO data_siswa (nisn, nama_siswa, rombel_id, gender, orang_tua, telepon, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'ssissss',
                    [$nisn !== '' ? $nisn : null, $name, $rombelId, $gender, $parent, $phone, $status]
                );
            }

            // Akun login siswa (username = NISN) agar dapat masuk ke Portal Siswa.
            $student = db_select_one($koneksi, 'SELECT * FROM data_siswa WHERE id = ?', 'i', [$studentId]);
            if (empty($student['user_id'])) {
                $username = $nisn !== '' ? $nisn : ('siswa' . $studentId);
                if (!db_select_one($koneksi, 'SELECT id FROM users WHERE username = ?', 's', [$username])) {
                    $userId = db_insert(
                        $koneksi,
                        'INSERT INTO users (username, nama_lengkap, password_hash, role) VALUES (?, ?, ?, ?)',
                        'ssss',
                        [$username, $name, password_hash(DEFAULT_STUDENT_PASSWORD, PASSWORD_DEFAULT), 'siswa']
                    );
                    db_execute($koneksi, 'UPDATE data_siswa SET user_id = ? WHERE id = ?', 'ii', [$userId, $studentId]);
                }
            }
            $koneksi->commit();
            ok('Data siswa berhasil disimpan.', ['id' => $studentId]);

        case 'delete_student':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $student = db_select_one($koneksi, 'SELECT * FROM data_siswa WHERE id = ?', 'i', [$id]);
            if (!$student) fail('Data siswa tidak ditemukan.');
            $koneksi->begin_transaction();
            db_execute($koneksi, 'DELETE FROM data_siswa WHERE id = ?', 'i', [$id]);
            if (!empty($student['user_id'])) {
                db_execute($koneksi, 'DELETE FROM users WHERE id = ?', 'i', [(int) $student['user_id']]);
            }
            $koneksi->commit();
            ok('Data siswa berhasil dihapus.');

        case 'bulk_students':
            assert_role($user, ['admin']);
            $ids = array_values(array_filter(array_map('intval', inp_array('ids'))));
            if (empty($ids)) fail('Pilih siswa terlebih dahulu.');
            $targetClass = inp('class');
            $targetStatus = inp('status');
            $rombel = $targetClass !== '' && $targetClass !== 'ALL' ? get_rombel($koneksi, $targetClass) : null;
            if ($targetClass !== '' && $targetClass !== 'ALL' && !$rombel) fail('Rombel tujuan tidak ditemukan.');
            if ($targetStatus !== '') {
                $map = ['Aktif Belajar' => 'Aktif'];
                $targetStatus = $map[$targetStatus] ?? $targetStatus;
                if (!in_array($targetStatus, ['Aktif', 'Lulus', 'Mutasi', 'Mengundurkan Diri'], true)) fail('Status tidak valid.');
            }
            $koneksi->begin_transaction();
            $affected = 0;
            foreach ($ids as $sid) {
                if ($rombel) {
                    $affected += db_execute($koneksi, 'UPDATE data_siswa SET rombel_id = ? WHERE id = ?', 'ii', [(int) $rombel['id'], $sid]);
                }
                if ($targetStatus !== '') {
                    $affected += db_execute($koneksi, 'UPDATE data_siswa SET status = ? WHERE id = ?', 'si', [$targetStatus, $sid]);
                }
            }
            $koneksi->commit();
            ok('Perubahan massal diterapkan pada ' . count($ids) . ' siswa.', ['affected' => $affected]);

        case 'import_students':
            assert_role($user, ['admin']);
            $upload = $_FILES['csv_file_siswa'] ?? $_FILES['csv_file'] ?? null;
            if (empty($upload['tmp_name']) || ($upload['error'] ?? 1) !== UPLOAD_ERR_OK) {
                fail('Pilih berkas CSV terlebih dahulu.');
            }
            $handle = fopen($upload['tmp_name'], 'r');
            if (!$handle) fail('Berkas tidak dapat dibaca.');
            fgetcsv($handle, 4000, ',');
            $inserted = 0;
            $skipped = 0;
            $koneksi->begin_transaction();
            while (($row = fgetcsv($handle, 4000, ',')) !== false) {
                $nisn = trim((string) ($row[0] ?? ''));
                $nama = trim((string) ($row[1] ?? ''));
                $gender = strtoupper(trim((string) ($row[2] ?? 'L'))) === 'P' ? 'P' : 'L';
                $kelas = trim((string) ($row[3] ?? ''));
                $parent = trim((string) ($row[4] ?? '-'));
                $phone = trim((string) ($row[5] ?? '-'));
                $status = trim((string) ($row[6] ?? 'Aktif')) ?: 'Aktif';
                $status = $status === 'Aktif Belajar' ? 'Aktif' : $status;
                if ($nama === '') {
                    $skipped++;
                    continue;
                }
                if ($nisn !== '' && db_select_one($koneksi, 'SELECT id FROM data_siswa WHERE nisn = ?', 's', [$nisn])) {
                    $skipped++;
                    continue;
                }
                $rombel = $kelas !== '' ? get_rombel($koneksi, $kelas) : null;
                $studentId = db_insert(
                    $koneksi,
                    'INSERT INTO data_siswa (nisn, nama_siswa, rombel_id, gender, orang_tua, telepon, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'ssissss',
                    [$nisn !== '' ? $nisn : null, $nama, $rombel ? (int) $rombel['id'] : null, $gender, $parent, $phone, in_array($status, ['Aktif', 'Lulus', 'Mutasi', 'Mengundurkan Diri'], true) ? $status : 'Aktif']
                );
                $username = $nisn !== '' ? $nisn : ('siswa' . $studentId);
                if (!db_select_one($koneksi, 'SELECT id FROM users WHERE username = ?', 's', [$username])) {
                    $userId = db_insert(
                        $koneksi,
                        'INSERT INTO users (username, nama_lengkap, password_hash, role) VALUES (?, ?, ?, ?)',
                        'ssss',
                        [$username, $nama, password_hash(DEFAULT_STUDENT_PASSWORD, PASSWORD_DEFAULT), 'siswa']
                    );
                    db_execute($koneksi, 'UPDATE data_siswa SET user_id = ? WHERE id = ?', 'ii', [$userId, $studentId]);
                }
                $inserted++;
            }
            fclose($handle);
            $koneksi->commit();
            ok("Import selesai: {$inserted} siswa ditambahkan, {$skipped} dilewati.", ['inserted' => $inserted, 'skipped' => $skipped]);

        /* ======================================================
         * ADMIN — MATA PELAJARAN
         * ====================================================== */
        case 'save_mapel':
            assert_role($user, ['admin']);
            $origCode = inp('orig_code');
            $code = inp('code');
            $name = inp('name');
            $group = inp('group', 'Kelompok A (Umum)');
            $hours = inp_int('hours', 2);
            $kkm = inp_int('kkm', 75);
            $lead = inp('lead');
            $covered = inp_int('rombel_covered', 0);

            if ($code === '' || $name === '') fail('Kode dan nama mata pelajaran wajib diisi.');
            if ($hours < 1 || $hours > 20) fail('Beban jam tidak wajar.');
            if ($kkm < 0 || $kkm > 100) fail('KKM harus antara 0–100.');

            $dup = db_select_one($koneksi, 'SELECT id FROM mata_pelajaran WHERE (kode = ? OR nama = ?) AND kode <> ?', 'sss', [$code, $name, $origCode]);
            if ($dup) fail('Kode atau nama mata pelajaran sudah dipakai.');

            $leadId = null;
            if ($lead !== '') {
                $guruRow = db_select_one($koneksi, 'SELECT id FROM data_guru WHERE nama_guru = ? LIMIT 1', 's', [$lead]);
                if ($guruRow) $leadId = (int) $guruRow['id'];
            }

            if ($origCode !== '') {
                db_execute(
                    $koneksi,
                    'UPDATE mata_pelajaran SET kode = ?, nama = ?, kelompok = ?, jam_per_minggu = ?, kkm = ?, cakupan_rombel = ?, koordinator_guru_id = ? WHERE kode = ?',
                    'sssiiiis',
                    [$code, $name, $group, $hours, $kkm, $covered, $leadId, $origCode]
                );
                ok('Mata pelajaran berhasil diperbarui.');
            }
            db_insert(
                $koneksi,
                'INSERT INTO mata_pelajaran (kode, nama, kelompok, jam_per_minggu, kkm, cakupan_rombel, koordinator_guru_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                'sssiiii',
                [$code, $name, $group, $hours, $kkm, $covered, $leadId]
            );
            ok('Mata pelajaran berhasil ditambahkan.');

        case 'delete_mapel':
            assert_role($user, ['admin']);
            $code = inp('code');
            $mapel = db_select_one($koneksi, 'SELECT id, nama FROM mata_pelajaran WHERE kode = ?', 's', [$code]);
            if (!$mapel) fail('Mata pelajaran tidak ditemukan.');
            $used = db_select_one($koneksi, 'SELECT COUNT(*) AS c FROM nilai_siswa WHERE mapel_id = ?', 'i', [(int) $mapel['id']]);
            if ((int) $used['c'] > 0) fail('Mata pelajaran tidak dapat dihapus karena sudah memiliki nilai siswa.');
            db_execute($koneksi, 'DELETE FROM mata_pelajaran WHERE id = ?', 'i', [(int) $mapel['id']]);
            ok('Mata pelajaran berhasil dihapus.');

        /* ======================================================
         * ADMIN — ROMBEL
         * ====================================================== */
        case 'save_rombel':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $nama = strtoupper(inp('nama'));
            $tingkat = inp_int('tingkat');
            $ruang = inp('ruang');
            $kapasitas = inp_int('kapasitas', 32);
            if ($nama === '' || !in_array($tingkat, [7, 8, 9], true)) fail('Nama dan tingkat rombel wajib valid.');
            $nama = rombel_name_from_code($nama);
            if ($kapasitas < 1 || $kapasitas > 100) fail('Kapasitas tidak wajar.');
            $dup = db_select_one($koneksi, 'SELECT id FROM rombel WHERE nama = ? AND id <> ?', 'si', [$nama, $id]);
            if ($dup) fail('Nama rombel sudah ada.');
            if ($id > 0) {
                db_execute($koneksi, 'UPDATE rombel SET nama = ?, tingkat = ?, ruang = ?, kapasitas = ? WHERE id = ?', 'sissi', [$nama, $tingkat, $ruang, $kapasitas, $id]);
                ok('Rombel berhasil diperbarui.');
            }
            $newId = db_insert($koneksi, 'INSERT INTO rombel (nama, tingkat, ruang, kapasitas) VALUES (?, ?, ?, ?)', 'sisi', [$nama, $tingkat, $ruang, $kapasitas]);
            ok('Rombel baru berhasil dibuat.', ['id' => $newId]);

        case 'delete_rombel':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $students = db_select_one($koneksi, 'SELECT COUNT(*) AS c FROM data_siswa WHERE rombel_id = ?', 'i', [$id]);
            if ((int) $students['c'] > 0) fail('Rombel masih memiliki siswa. Pindahkan siswa terlebih dahulu.');
            db_execute($koneksi, 'DELETE FROM rombel WHERE id = ?', 'i', [$id]);
            ok('Rombel berhasil dihapus.');

        case 'save_rombel_wali':
            assert_role($user, ['admin']);
            $rombelName = inp('rombel');
            $waliId = inp_int('wali_guru_id');
            $rombel = get_rombel($koneksi, $rombelName);
            if (!$rombel) fail('Rombel tidak ditemukan.');
            if ($waliId > 0 && !db_select_one($koneksi, 'SELECT id FROM data_guru WHERE id = ?', 'i', [$waliId])) {
                fail('Guru wali kelas tidak ditemukan.');
            }
            db_execute($koneksi, 'UPDATE rombel SET wali_guru_id = ? WHERE id = ?', 'ii', [$waliId > 0 ? $waliId : null, (int) $rombel['id']]);
            ok('Wali kelas berhasil diperbarui.');

        case 'rombel_detail':
            assert_role($user, ['admin']);
            $rombelName = inp('rombel');
            $rombel = get_rombel($koneksi, $rombelName);
            if (!$rombel) fail('Rombel tidak ditemukan.');
            $rid = (int) $rombel['id'];

            $mapelStats = db_select(
                $koneksi,
                "SELECT m.id, m.nama, m.kkm, MAX(g.nama_guru) AS lead,
                        COUNT(DISTINCT n.jenis_penilaian) AS col_count,
                        ROUND(AVG(n.nilai), 1) AS avg_score,
                        (SELECT COUNT(*) FROM data_siswa s2 WHERE s2.rombel_id = ? AND s2.status = 'Aktif') AS total_students,
                        (SELECT COUNT(*) FROM data_siswa s4
                          WHERE s4.rombel_id = ? AND s4.status = 'Aktif'
                            AND (SELECT AVG(n2.nilai) FROM nilai_siswa n2
                                  WHERE n2.siswa_id = s4.id AND n2.mapel_id = m.id) >= m.kkm) AS passed
                 FROM mata_pelajaran m
                 LEFT JOIN data_guru g ON g.id = m.koordinator_guru_id
                 LEFT JOIN nilai_siswa n ON n.mapel_id = m.id
                 LEFT JOIN data_siswa s ON s.id = n.siswa_id AND s.rombel_id = ?
                 GROUP BY m.id
                 ORDER BY m.kode",
                'iii',
                [$rid, $rid, $rid]
            );
            $mapelOut = [];
            foreach ($mapelStats as $m) {
                $total = max(1, (int) $m['total_students']);
                $mapelOut[] = [
                    'id'          => (int) $m['id'],
                    'name'        => $m['nama'],
                    'lead'        => $m['lead'] ?: 'Belum Ditugaskan',
                    'kkm'         => (int) $m['kkm'],
                    'columns'     => (int) $m['col_count'],
                    'avg'         => $m['avg_score'] !== null ? (float) $m['avg_score'] : '-',
                    'passPct'     => $m['avg_score'] !== null ? (int) round(((int) $m['passed'] / $total) * 100) : 0,
                    'status'      => (int) $m['col_count'] >= 3 ? 'Lengkap' : ((int) $m['col_count'] > 0 ? 'Aktif Mengisi' : 'Belum Input'),
                ];
            }

            $students = db_select(
                $koneksi,
                "SELECT s.id, s.nisn, s.nama_siswa, s.gender, s.catatan_wali,
                        (SELECT ROUND(AVG(n.nilai), 1) FROM nilai_siswa n WHERE n.siswa_id = s.id) AS avg_nilai,
                        (SELECT COUNT(*) FROM presensi p WHERE p.siswa_id = s.id) AS total_presensi,
                        (SELECT COUNT(*) FROM presensi p WHERE p.siswa_id = s.id AND p.status = 'H') AS hadir
                 FROM data_siswa s
                 WHERE s.rombel_id = ? AND s.status = 'Aktif'
                 ORDER BY s.nama_siswa",
                'i',
                [$rid]
            );
            $studentOut = [];
            foreach ($students as $s) {
                $presensi = (int) $s['total_presensi'];
                $attendance = $presensi > 0 ? round(((int) $s['hadir'] / $presensi) * 100, 1) : null;
                $studentOut[] = [
                    'id'       => (int) $s['id'],
                    'nisn'     => $s['nisn'] ?: '-',
                    'name'     => $s['nama_siswa'],
                    'gender'   => $s['gender'],
                    'avg'      => $s['avg_nilai'] !== null ? (float) $s['avg_nilai'] : '-',
                    'attend'   => $attendance,
                    'catatan'  => $s['catatan_wali'] ?: 'Belum ada catatan wali kelas.',
                ];
            }

            ok('Detail rombel dimuat.', [
                'rombel'    => [
                    'id'    => $rid,
                    'name'  => $rombel['nama'],
                    'wali'  => db_select_one($koneksi, 'SELECT g.nama_guru AS wali FROM rombel r LEFT JOIN data_guru g ON g.id = r.wali_guru_id WHERE r.id = ?', 'i', [$rid])['wali'] ?? 'Belum Ditentukan',
                    'room'  => $rombel['ruang'] ?: '-',
                    'count' => count($studentOut),
                    'max'   => (int) $rombel['kapasitas'],
                ],
                'mapel'     => $mapelOut,
                'students'  => $studentOut,
            ]);

        /* ======================================================
         * ADMIN — MONITORING INPUT GURU
         * ====================================================== */
        case 'monitoring_detail':
            assert_role($user, ['admin']);
            $pengampuId = inp_int('id');
            $p = db_select_one(
                $koneksi,
                "SELECT p.*, g.nama_guru, m.nama AS mapel, m.kkm, r.nama AS rombel
                 FROM pengampu p
                 JOIN data_guru g ON g.id = p.guru_id
                 JOIN mata_pelajaran m ON m.id = p.mapel_id
                 JOIN rombel r ON r.id = p.rombel_id
                 WHERE p.id = ?",
                'i',
                [$pengampuId]
            );
            if (!$p) fail('Data pemantauan tidak ditemukan.');

            $rows = db_select(
                $koneksi,
                "SELECT s.nisn, s.nama_siswa, n.jenis_penilaian, n.nilai, n.kategori
                 FROM data_siswa s
                 LEFT JOIN nilai_siswa n ON n.siswa_id = s.id AND n.mapel_id = ?
                 WHERE s.rombel_id = ? AND s.status = 'Aktif'
                 ORDER BY s.nama_siswa",
                'ii',
                [(int) $p['mapel_id'], (int) $p['rombel_id']]
            );

            $columns = [];
            $studentsMap = [];
            foreach ($rows as $row) {
                $nisnKey = $row['nisn'] ?: $row['nama_siswa'];
                if (!isset($studentsMap[$nisnKey])) {
                    $studentsMap[$nisnKey] = ['nisn' => $row['nisn'] ?: '-', 'name' => $row['nama_siswa'], 'scores' => []];
                }
                if ($row['jenis_penilaian']) {
                    $columns[$row['jenis_penilaian']] = $row['kategori'];
                    $studentsMap[$nisnKey]['scores'][$row['jenis_penilaian']] = (float) $row['nilai'];
                }
            }
            $studentsOut = [];
            foreach ($studentsMap as $s) {
                $scores = $s['scores'];
                $s['avg'] = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : '-';
                $s['scores'] = (object) $scores;
                $studentsOut[] = $s;
            }

            $journals = db_select_one($koneksi, 'SELECT COUNT(*) AS c FROM jurnal_guru WHERE guru_id = ? AND mapel_id = ? AND rombel_id = ?', 'iii', [(int) $p['guru_id'], (int) $p['mapel_id'], (int) $p['rombel_id']]);

            ok('Detail input guru dimuat.', [
                'pengampu'  => $p,
                'columns'   => $columns,
                'students'  => $studentsOut,
                'journalCount' => (int) ($journals['c'] ?? 0),
            ]);

        /* ======================================================
         * ADMIN — ARSIP SISWA
         * ====================================================== */
        case 'save_archive_student':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $name = inp('name');
            $nisn = inp('nisn');
            $lastClass = inp('last_class');
            $status = inp('status', 'Lulus');
            $time = inp('time');
            $year = inp_int('year');
            $destination = inp('destination');
            $phone = inp('phone');
            if (mb_strlen($name) < 3) fail('Nama siswa wajib diisi.');
            if (!in_array($status, ['Lulus', 'Mutasi Keluar', 'Mengundurkan Diri'], true)) fail('Status arsip tidak valid.');
            $student = $nisn !== '' && $nisn !== '-' ? db_select_one($koneksi, 'SELECT id FROM data_siswa WHERE nisn = ?', 's', [$nisn]) : null;
            if ($id > 0) {
                db_execute(
                    $koneksi,
                    'UPDATE arsip_siswa SET siswa_id = ?, nisn = ?, nama_siswa = ?, last_class = ?, status = ?, time_label = ?, tahun = ?, destination = ?, phone = ? WHERE id = ?',
                    'isssssissi',
                    [(int) ($student['id'] ?? 0) ?: null, $nisn ?: null, $name, $lastClass, $status, $time, $year ?: null, $destination, $phone, $id]
                );
                ok('Berkas arsip siswa diperbarui.');
            }
            db_insert(
                $koneksi,
                'INSERT INTO arsip_siswa (siswa_id, nisn, nama_siswa, last_class, status, time_label, tahun, destination, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'isssssiss',
                [$student ? (int) $student['id'] : null, $nisn ?: null, $name, $lastClass, $status, $time, $year ?: null, $destination, $phone]
            );
            ok('Berkas arsip siswa ditambahkan.');

        case 'delete_archive_student':
            assert_role($user, ['admin']);
            db_execute($koneksi, 'DELETE FROM arsip_siswa WHERE id = ?', 'i', [inp_int('id')]);
            ok('Berkas arsip siswa dihapus.');

        case 'restore_archive_student':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $arsip = db_select_one($koneksi, 'SELECT * FROM arsip_siswa WHERE id = ?', 'i', [$id]);
            if (!$arsip) fail('Berkas arsip tidak ditemukan.');
            $student = null;
            if (!empty($arsip['siswa_id'])) {
                $student = db_select_one($koneksi, 'SELECT * FROM data_siswa WHERE id = ?', 'i', [(int) $arsip['siswa_id']]);
            }
            if (!$student && !empty($arsip['nisn'])) {
                $student = db_select_one($koneksi, 'SELECT * FROM data_siswa WHERE nisn = ?', 's', [$arsip['nisn']]);
            }
            $koneksi->begin_transaction();
            if ($student) {
                $rombel = get_rombel($koneksi, $arsip['last_class'] ?: '');
                db_execute(
                    $koneksi,
                    'UPDATE data_siswa SET status = ?, rombel_id = COALESCE(?, rombel_id) WHERE id = ?',
                    'sii',
                    ['Aktif', $rombel ? (int) $rombel['id'] : null, (int) $student['id']]
                );
            } else {
                $rombel = get_rombel($koneksi, $arsip['last_class'] ?: '');
                db_insert(
                    $koneksi,
                    'INSERT INTO data_siswa (nisn, nama_siswa, rombel_id, orang_tua, telepon, status) VALUES (?, ?, ?, ?, ?, ?)',
                    'ssisss',
                    [$arsip['nisn'], $arsip['nama_siswa'], $rombel ? (int) $rombel['id'] : null, '-', $arsip['phone'], 'Aktif']
                );
            }
            db_execute($koneksi, 'DELETE FROM arsip_siswa WHERE id = ?', 'i', [$id]);
            $koneksi->commit();
            ok('Siswa berhasil dipulihkan ke data aktif.');

        /* ======================================================
         * ADMIN — ARSIP GURU
         * ====================================================== */
        case 'save_archive_teacher':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $name = inp('name');
            $nip = inp('nip');
            $lastSubject = inp('last_subject');
            $status = inp('status', 'Pensiun');
            $time = inp('time');
            $year = inp_int('year');
            $sk = inp('sk');
            if (mb_strlen($name) < 3) fail('Nama guru wajib diisi.');
            if (!in_array($status, ['Pensiun', 'Mutasi Keluar', 'Purna Bakti'], true)) fail('Status arsip tidak valid.');
            $teacher = $nip !== '' && $nip !== '-' ? db_select_one($koneksi, 'SELECT id FROM data_guru WHERE nip = ?', 's', [$nip]) : null;
            if ($id > 0) {
                db_execute(
                    $koneksi,
                    'UPDATE arsip_guru SET guru_id = ?, nip = ?, nama_guru = ?, last_subject = ?, status = ?, time_label = ?, tahun = ?, sk = ? WHERE id = ?',
                    'isssssisi',
                    [$teacher ? (int) $teacher['id'] : null, $nip ?: null, $name, $lastSubject, $status, $time, $year ?: null, $sk, $id]
                );
                ok('Berkas arsip guru diperbarui.');
            }
            db_insert(
                $koneksi,
                'INSERT INTO arsip_guru (guru_id, nip, nama_guru, last_subject, status, time_label, tahun, sk) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                'isssssis',
                [$teacher ? (int) $teacher['id'] : null, $nip ?: null, $name, $lastSubject, $status, $time, $year ?: null, $sk]
            );
            ok('Berkas arsip guru ditambahkan.');

        case 'delete_archive_teacher':
            assert_role($user, ['admin']);
            db_execute($koneksi, 'DELETE FROM arsip_guru WHERE id = ?', 'i', [inp_int('id')]);
            ok('Berkas arsip guru dihapus.');

        case 'restore_archive_teacher':
            assert_role($user, ['admin']);
            $id = inp_int('id');
            $arsip = db_select_one($koneksi, 'SELECT * FROM arsip_guru WHERE id = ?', 'i', [$id]);
            if (!$arsip) fail('Berkas arsip tidak ditemukan.');
            $koneksi->begin_transaction();
            if (!empty($arsip['guru_id'])) {
                db_execute($koneksi, 'UPDATE data_guru SET status = ? WHERE id = ?', 'si', ['Aktif', (int) $arsip['guru_id']]);
            } else {
                $nextKodeRow = db_select_one($koneksi, 'SELECT COALESCE(MAX(CAST(kode AS UNSIGNED)), 0) + 1 AS next_kode FROM data_guru');
                $kode = (string) ((int) ($nextKodeRow['next_kode'] ?? 1));
                db_insert($koneksi, 'INSERT INTO data_guru (kode, nip, nama_guru, mata_pelajaran, status) VALUES (?, ?, ?, ?, ?)', 'sssss', [$kode, $arsip['nip'], $arsip['nama_guru'], $arsip['last_subject'], 'Aktif']);
            }
            db_execute($koneksi, 'DELETE FROM arsip_guru WHERE id = ?', 'i', [$id]);
            $koneksi->commit();
            ok('Guru berhasil dipulihkan ke data aktif.');

        /* ======================================================
         * ADMIN — PENGATURAN
         * ====================================================== */
        case 'save_settings':
            assert_role($user, ['admin']);
            $allowed = ['school_name', 'npsn', 'address', 'headmaster', 'headmaster_nip', 'tahun_ajaran', 'semester', 'deadline_nilai', 'registration_open'];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $_POST)) {
                    $value = trim((string) $_POST[$key]);
                    db_execute(
                        $koneksi,
                        'INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)',
                        'ss',
                        [$key, $value]
                    );
                }
            }
            ok('Pengaturan sekolah berhasil disimpan.');

        /* ======================================================
         * GURU — BOOTSTRAP & PORTAL
         * ====================================================== */
        case 'guru_bootstrap':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $gid = (int) $guru['id'];
            $tahun = TAHUN_AJARAN;
            $semester = SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil';

            $assignments = db_select(
                $koneksi,
                "SELECT p.id, p.mapel_id, p.rombel_id, m.nama AS mapel, m.kkm, r.nama AS rombel, r.tingkat
                 FROM pengampu p
                 JOIN mata_pelajaran m ON m.id = p.mapel_id
                 JOIN rombel r ON r.id = p.rombel_id
                 WHERE p.guru_id = ? AND p.tahun_ajaran = ? AND p.semester = ?
                 ORDER BY r.tingkat, r.nama, m.nama",
                'iss',
                [$gid, $tahun, $semester]
            );

            if (is_kepsek($user)) {
                // Kepala Sekolah: pantau semua penugasan di semua kelas
                $assignments = db_select(
                    $koneksi,
                    "SELECT MIN(p.id) AS id, p.mapel_id, p.rombel_id, m.nama AS mapel, m.kkm, r.nama AS rombel, r.tingkat
                     FROM pengampu p
                     JOIN mata_pelajaran m ON m.id = p.mapel_id
                     JOIN rombel r ON r.id = p.rombel_id
                     WHERE p.tahun_ajaran = ? AND p.semester = ?
                     GROUP BY p.mapel_id, p.rombel_id, m.nama, m.kkm, r.nama, r.tingkat
                     ORDER BY r.tingkat, r.nama, m.nama",
                    'ss',
                    [$tahun, $semester]
                );
            }
            $classes = [];
            $subjects = [];
            foreach ($assignments as $a) {
                $classes[code_from_rombel_name($a['rombel'])] = true;
                $subjects[$a['mapel']] = true;
            }

            $waliRombel = db_select_one(
                $koneksi,
                'SELECT r.id, r.nama, r.tingkat FROM rombel r WHERE r.wali_guru_id = ? LIMIT 1',
                'i',
                [$gid]
            );
            if ($waliRombel) {
                $classes[code_from_rombel_name($waliRombel['nama'])] = true;
            }

            $allMapel = db_select($koneksi, 'SELECT id, kode, nama, kkm FROM mata_pelajaran ORDER BY kode');
            $stats = db_select_one(
                $koneksi,
                "SELECT
                    (SELECT COUNT(DISTINCT p.rombel_id) FROM pengampu p WHERE p.guru_id = ?) AS total_kelas,
                    (SELECT COUNT(*) FROM jurnal_guru j WHERE j.guru_id = ?) AS total_jurnal,
                    (SELECT COUNT(*) FROM nilai_siswa n WHERE n.guru_id = ?) AS total_nilai,
                    (SELECT ROUND(AVG(n.nilai), 1) FROM nilai_siswa n WHERE n.guru_id = ?) AS rata_nilai",
                'iiii',
                [$gid, $gid, $gid, $gid]
            );

            $stats['total_siswa'] = (int) (db_select_one(
                $koneksi,
                "SELECT COUNT(DISTINCT s.id) AS c
                 FROM data_siswa s
                 WHERE s.rombel_id IN (SELECT rombel_id FROM pengampu WHERE guru_id = ? UNION SELECT id FROM rombel WHERE wali_guru_id = ?)",
                'ii',
                [$gid, $gid]
            )['c'] ?? 0);

            ok('Portal guru dimuat.', [
                'teacher' => [
                    'id'             => $gid,
                    'kode'           => $guru['kode'],
                    'name'           => $guru['nama_guru'],
                    'nip'            => $guru['nip'],
                    'mapel'          => $guru['mata_pelajaran'] ?: '',
                    'kelas'          => $guru['kelas'] ?: '',
                    'wali'           => $waliRombel ? ('Wali Kelas ' . $waliRombel['nama']) : ($guru['wali_kelas'] ?: 'Bukan Wali Kelas'),
                    'jam_per_minggu' => (int) $guru['jam_per_minggu'],
                    'phone'          => $guru['phone'] ?: '',
                    'username'       => $user['username'],
                    'status'         => $guru['status'],
                ],
                'assignments'     => $assignments,
                'allowedClasses'  => array_keys($classes),
                'allowedSubjects' => array_keys($subjects),
                'waliRombel'      => $waliRombel ? ['id' => (int) $waliRombel['id'], 'name' => $waliRombel['nama'], 'code' => code_from_rombel_name($waliRombel['nama'])] : null,
                'allMapel'        => $allMapel,
                'stats'           => [
                    'total_kelas'  => (int) $stats['total_kelas'],
                    'total_siswa'  => (int) $stats['total_siswa'],
                    'total_jurnal' => (int) $stats['total_jurnal'],
                    'total_nilai'  => (int) $stats['total_nilai'],
                    'rata_nilai'   => $stats['rata_nilai'] !== null ? (float) $stats['rata_nilai'] : 0,
                ],
            ]);

        case 'get_academic_data':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $gid = (int) $guru['id'];
            $rombel = get_rombel($koneksi, inp('rombel'));
            $mapel = db_select_one($koneksi, 'SELECT * FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [inp('mapel'), inp('mapel')]);
            if (!$rombel || !$mapel) fail('Rombel atau mata pelajaran tidak ditemukan.');

            $authorized = db_select_one(
                $koneksi,
                'SELECT id FROM pengampu WHERE guru_id = ? AND mapel_id = ? AND rombel_id = ? AND tahun_ajaran = ? AND semester = ?',
                'iiiss',
                [$gid, (int) $mapel['id'], (int) $rombel['id'], TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
            );
            $isWali = db_select_one($koneksi, 'SELECT id FROM rombel WHERE id = ? AND wali_guru_id = ?', 'ii', [(int) $rombel['id'], $gid]);
            if (!$authorized && !$isWali && !is_kepsek($user)) {
                fail('Anda tidak ditugaskan mengajar mata pelajaran ini di rombel tersebut.', 403);
            }

            $students = db_select(
                $koneksi,
                "SELECT s.id, s.nisn, s.nama_siswa, s.catatan_wali
                 FROM data_siswa s
                 WHERE s.rombel_id = ? AND s.status = 'Aktif'
                 ORDER BY s.nama_siswa",
                'i',
                [(int) $rombel['id']]
            );
            $studentOut = [];
            $byId = [];
            foreach ($students as $s) {
                $entry = [
                    'id'             => (int) $s['id'],
                    'nisn'           => $s['nisn'] ?: '-',
                    'name'           => $s['nama_siswa'],
                    'class'          => $rombel['nama'],
                    'scores'         => [],
                    'attendances'    => (object) [],
                    'semesterNotes'  => $s['catatan_wali'] ?: 'Mengikuti pembelajaran dengan baik.',
                ];
                $byId[(int) $s['id']] = count($studentOut);
                $studentOut[] = $entry;
            }

            $nilaiRows = db_select(
                $koneksi,
                "SELECT n.siswa_id, n.jenis_penilaian, n.kategori, n.nilai, n.created_at
                 FROM nilai_siswa n
                 JOIN data_siswa s ON s.id = n.siswa_id
                 WHERE s.rombel_id = ? AND n.mapel_id = ?
                 ORDER BY n.id",
                'ii',
                [(int) $rombel['id'], (int) $mapel['id']]
            );
            $columns = [];
            foreach ($nilaiRows as $n) {
                $jenis = $n['jenis_penilaian'];
                if (!isset($columns[$jenis])) {
                    $columns[$jenis] = [
                        'id'       => $jenis,
                        'name'     => $jenis,
                        'category' => $n['kategori'] ?: 'Formatif',
                        'date'     => date('d M Y', strtotime($n['created_at'])),
                    ];
                }
                $idx = $byId[(int) $n['siswa_id']] ?? null;
                if ($idx !== null) {
                    $studentOut[$idx]['scores'][$jenis] = (float) $n['nilai'];
                }
            }
            foreach ($studentOut as &$s) {
                $s['scores'] = (object) $s['scores'];
            }
            unset($s);

            $isWaliOrKepsek = (bool) $isWali || is_kepsek($user);
            $jurnalFilter = $isWaliOrKepsek ? '' : 'j.guru_id = ? AND ';
            $jurnalTipe   = $isWaliOrKepsek ? 'ii' : 'iii';
            $jurnalParam  = $isWaliOrKepsek
                ? [(int) $mapel['id'], (int) $rombel['id']]
                : [$gid, (int) $mapel['id'], (int) $rombel['id']];
            $journals = db_select(
                $koneksi,
                "SELECT j.* FROM jurnal_guru j
                 WHERE {$jurnalFilter}j.mapel_id = ? AND j.rombel_id = ?
                 ORDER BY j.tanggal DESC, j.id DESC",
                $jurnalTipe,
                $jurnalParam
            );
            $journalOut = array_map(static fn($j) => [
                'id'         => (int) $j['id'],
                'day'        => $j['hari'],
                'date'       => date('d M Y', strtotime($j['tanggal'])),
                'rawDate'    => $j['tanggal'],
                'meeting'    => $j['pertemuan'],
                'topic'      => $j['materi'],
                'activities' => $j['kegiatan'],
                'attendance' => [
                    'hadir' => (int) $j['hadir'],
                    'izin'  => (int) $j['izin'],
                    'sakit' => (int) $j['sakit'],
                    'alpa'  => (int) $j['alpa'],
                ],
            ], $journals);

            $presensiRows = db_select(
                $koneksi,
                "SELECT p.siswa_id, p.tanggal, p.pertemuan, p.status
                 FROM presensi p
                 WHERE p.rombel_id = ? AND p.mapel_id = ?
                 ORDER BY p.tanggal, p.pertemuan",
                'ii',
                [(int) $rombel['id'], (int) $mapel['id']]
            );
            $attColumns = [];
            foreach ($presensiRows as $p) {
                $key = $p['tanggal'] . '|' . $p['pertemuan'];
                if (!isset($attColumns[$key])) {
                    $attColumns[$key] = [
                        'id'    => $key,
                        'name'  => $p['pertemuan'],
                        'date'  => date('d M Y', strtotime($p['tanggal'])),
                        'rawDate' => $p['tanggal'],
                    ];
                }
                $idx = $byId[(int) $p['siswa_id']] ?? null;
                if ($idx !== null) {
                    $studentOut[$idx]['attendances']->{$key} = $p['status'];
                }
            }
            foreach ($studentOut as &$s) {
                $s['attendances'] = (object) $s['attendances'];
            }
            unset($s);

            ok('Data akademik dimuat.', [
                'students'          => $studentOut,
                'columns'           => array_values($columns),
                'journals'          => $journalOut,
                'attendanceColumns' => array_values($attColumns),
                'kkm'               => (int) $mapel['kkm'],
                'rombel'            => ['id' => (int) $rombel['id'], 'name' => $rombel['nama'], 'tingkat' => (int) $rombel['tingkat']],
                'mapel'             => ['id' => (int) $mapel['id'], 'name' => $mapel['nama'], 'kkm' => (int) $mapel['kkm']],
            ]);

        case 'save_grade_cell':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $rombel = get_rombel($koneksi, inp('rombel'));
            $mapel = db_select_one($koneksi, 'SELECT * FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [inp('mapel'), inp('mapel')]);
            $nisn = inp('nisn');
            $studentIdInput = inp_int('student_id');
            $jenis = inp('jenis_penilaian');
            $nilaiRaw = inp('nilai');
            $kategori = inp('kategori', 'Formatif');
            if (!$rombel || !$mapel || ($nisn === '' && $studentIdInput <= 0) || $jenis === '') fail('Parameter nilai tidak lengkap.');
            if (!in_array($kategori, ['Formatif', 'Sumatif', 'Praktik', 'Sikap'], true)) $kategori = 'Formatif';

            $authorized = db_select_one(
                $koneksi,
                'SELECT id FROM pengampu WHERE guru_id = ? AND mapel_id = ? AND rombel_id = ? AND tahun_ajaran = ? AND semester = ?',
                'iiiss',
                [(int) $guru['id'], (int) $mapel['id'], (int) $rombel['id'], TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
            );
            if (!$authorized) fail('Anda tidak berhak mengubah nilai pada rombel/mapel ini.', 403);

            if ($studentIdInput > 0) {
                $student = db_select_one($koneksi, 'SELECT id FROM data_siswa WHERE id = ? AND rombel_id = ? LIMIT 1', 'ii', [$studentIdInput, (int) $rombel['id']]);
            } else {
                $student = db_select_one($koneksi, 'SELECT id FROM data_siswa WHERE nisn = ? AND rombel_id = ? LIMIT 1', 'si', [$nisn, (int) $rombel['id']]);
            }
            if (!$student) fail('Siswa tidak ditemukan pada rombel ini.');
            $studentId = (int) $student['id'];

            if ($nilaiRaw === '' || $nilaiRaw === null) {
                db_execute($koneksi, 'DELETE FROM nilai_siswa WHERE siswa_id = ? AND mapel_id = ? AND jenis_penilaian = ?', 'iis', [$studentId, (int) $mapel['id'], $jenis]);
                ok('Nilai dikosongkan.');
            }
            $nilai = (float) $nilaiRaw;
            if ($nilai < 0 || $nilai > 100) fail('Nilai harus antara 0–100.');
            $kkm = inp_int('kkm', (int) $mapel['kkm']);
            $ket = $nilai >= $kkm ? 'Tuntas' : 'Remedial';

            db_execute(
                $koneksi,
                'INSERT INTO nilai_siswa (siswa_id, mapel_id, guru_id, rombel_id, jenis_penilaian, kategori, nilai, kkm, keterangan)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), kategori = VALUES(kategori), kkm = VALUES(kkm), keterangan = VALUES(keterangan), guru_id = VALUES(guru_id), rombel_id = VALUES(rombel_id)',
                'iiiissdis',
                [$studentId, (int) $mapel['id'], (int) $guru['id'], (int) $rombel['id'], $jenis, $kategori, $nilai, $kkm, $ket]
            );
            ok('Nilai tersimpan.', ['keterangan' => $ket]);

        case 'delete_grade_column':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $rombel = get_rombel($koneksi, inp('rombel'));
            $mapel = db_select_one($koneksi, 'SELECT * FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [inp('mapel'), inp('mapel')]);
            $jenis = inp('jenis_penilaian');
            if (!$rombel || !$mapel || $jenis === '') fail('Parameter tidak lengkap.');
            $authorized = db_select_one(
                $koneksi,
                'SELECT id FROM pengampu WHERE guru_id = ? AND mapel_id = ? AND rombel_id = ? AND tahun_ajaran = ? AND semester = ?',
                'iiiss',
                [(int) $guru['id'], (int) $mapel['id'], (int) $rombel['id'], TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
            );
            if (!$authorized) fail('Anda tidak berhak menghapus kolom nilai ini.', 403);
            db_execute(
                $koneksi,
                'DELETE n FROM nilai_siswa n JOIN data_siswa s ON s.id = n.siswa_id WHERE s.rombel_id = ? AND n.mapel_id = ? AND n.jenis_penilaian = ?',
                'iis',
                [(int) $rombel['id'], (int) $mapel['id'], $jenis]
            );
            ok('Kolom nilai berhasil dihapus.');

        case 'save_journal':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $id = inp_int('id');
            $rombel = get_rombel($koneksi, inp('rombel'));
            $mapel = db_select_one($koneksi, 'SELECT * FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [inp('mapel'), inp('mapel')]);
            if (!$rombel || !$mapel) fail('Rombel atau mapel tidak ditemukan.');
            $authorized = db_select_one(
                $koneksi,
                'SELECT id FROM pengampu WHERE guru_id = ? AND mapel_id = ? AND rombel_id = ? AND tahun_ajaran = ? AND semester = ?',
                'iiiss',
                [(int) $guru['id'], (int) $mapel['id'], (int) $rombel['id'], TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
            );
            if (!$authorized) fail('Anda tidak berhak mengisi jurnal untuk rombel/mapel ini.', 403);

            $tanggal = inp('tanggal', date('Y-m-d'));
            $hari = inp('hari');
            if ($hari === '') {
                $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][(int) date('w', strtotime($tanggal))] ?? 'Senin';
            }
            $pertemuan = inp('pertemuan');
            $materi = inp('materi');
            $kegiatan = inp('kegiatan');
            if ($pertemuan === '' || $materi === '') fail('Pertemuan dan materi wajib diisi.');
            $hadir = max(0, inp_int('hadir'));
            $izin = max(0, inp_int('izin'));
            $sakit = max(0, inp_int('sakit'));
            $alpa = max(0, inp_int('alpa'));

            if ($id > 0) {
                $existing = db_select_one($koneksi, 'SELECT id FROM jurnal_guru WHERE id = ? AND guru_id = ?', 'ii', [$id, (int) $guru['id']]);
                if (!$existing) fail('Jurnal tidak ditemukan atau bukan milik Anda.', 403);
                db_execute(
                    $koneksi,
                    'UPDATE jurnal_guru SET hari = ?, tanggal = ?, pertemuan = ?, materi = ?, kegiatan = ?, hadir = ?, izin = ?, sakit = ?, alpa = ? WHERE id = ?',
                    'sssssiiiii',
                    [$hari, $tanggal, $pertemuan, $materi, $kegiatan, $hadir, $izin, $sakit, $alpa, $id]
                );
                ok('Jurnal berhasil diperbarui.', ['id' => $id]);
            }
            $newId = db_insert(
                $koneksi,
                'INSERT INTO jurnal_guru (guru_id, mapel_id, rombel_id, hari, tanggal, pertemuan, materi, kegiatan, hadir, izin, sakit, alpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iiisssssiiii',
                [(int) $guru['id'], (int) $mapel['id'], (int) $rombel['id'], $hari, $tanggal, $pertemuan, $materi, $kegiatan, $hadir, $izin, $sakit, $alpa]
            );
            ok('Jurnal berhasil disimpan.', ['id' => $newId]);

        case 'delete_journal':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $id = inp_int('id');
            $deleted = db_execute($koneksi, 'DELETE FROM jurnal_guru WHERE id = ? AND guru_id = ?', 'ii', [$id, (int) $guru['id']]);
            if ($deleted === 0) fail('Jurnal tidak ditemukan atau bukan milik Anda.', 403);
            ok('Jurnal berhasil dihapus.');

        case 'save_attendance_cell':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $rombel = get_rombel($koneksi, inp('rombel'));
            $mapel = db_select_one($koneksi, 'SELECT * FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [inp('mapel'), inp('mapel')]);
            $nisn = inp('nisn');
            $studentIdInput = inp_int('student_id');
            $dateKey = inp('tanggal');
            $pertemuan = inp('pertemuan');
            $status = strtoupper(inp('status', 'H'));
            if (!$rombel || !$mapel || ($nisn === '' && $studentIdInput <= 0) || $dateKey === '' || $pertemuan === '') fail('Parameter presensi tidak lengkap.');
            if (!in_array($status, ['H', 'I', 'S', 'A'], true)) fail('Status presensi tidak valid.');
            $authorized = db_select_one(
                $koneksi,
                'SELECT id FROM pengampu WHERE guru_id = ? AND mapel_id = ? AND rombel_id = ? AND tahun_ajaran = ? AND semester = ?',
                'iiiss',
                [(int) $guru['id'], (int) $mapel['id'], (int) $rombel['id'], TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
            );
            if (!$authorized) fail('Anda tidak berhak mengubah presensi ini.', 403);
            if ($studentIdInput > 0) {
                $student = db_select_one($koneksi, 'SELECT id FROM data_siswa WHERE id = ? AND rombel_id = ? LIMIT 1', 'ii', [$studentIdInput, (int) $rombel['id']]);
            } else {
                $student = db_select_one($koneksi, 'SELECT id FROM data_siswa WHERE nisn = ? AND rombel_id = ? LIMIT 1', 'si', [$nisn, (int) $rombel['id']]);
            }
            if (!$student) fail('Siswa tidak ditemukan pada rombel ini.');
            db_execute(
                $koneksi,
                'INSERT INTO presensi (siswa_id, rombel_id, mapel_id, guru_id, tanggal, pertemuan, status) VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), guru_id = VALUES(guru_id), rombel_id = VALUES(rombel_id)',
                'iiiisss',
                [(int) $student['id'], (int) $rombel['id'], (int) $mapel['id'], (int) $guru['id'], $dateKey, $pertemuan, $status]
            );
            ok('Presensi tersimpan.', ['status' => $status]);

        case 'add_attendance_session':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $rombel = get_rombel($koneksi, inp('rombel'));
            $mapel = db_select_one($koneksi, 'SELECT * FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [inp('mapel'), inp('mapel')]);
            $name = inp('name');
            $date = inp('tanggal', date('Y-m-d'));
            if (!$rombel || !$mapel || $name === '') fail('Parameter sesi presensi tidak lengkap.');
            $authorized = db_select_one(
                $koneksi,
                'SELECT id FROM pengampu WHERE guru_id = ? AND mapel_id = ? AND rombel_id = ? AND tahun_ajaran = ? AND semester = ?',
                'iiiss',
                [(int) $guru['id'], (int) $mapel['id'], (int) $rombel['id'], TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
            );
            if (!$authorized) fail('Anda tidak berhak menambah presensi ini.', 403);
            $students = db_select($koneksi, "SELECT id FROM data_siswa WHERE rombel_id = ? AND status = 'Aktif'", 'i', [(int) $rombel['id']]);
            $koneksi->begin_transaction();
            foreach ($students as $s) {
                try {
                    db_insert(
                        $koneksi,
                        'INSERT INTO presensi (siswa_id, rombel_id, mapel_id, guru_id, tanggal, pertemuan, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        'iiiisss',
                        [(int) $s['id'], (int) $rombel['id'], (int) $mapel['id'], (int) $guru['id'], $date, $name, 'H']
                    );
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() !== 1062) throw $e;
                }
            }
            $koneksi->commit();
            ok('Sesi presensi ditambahkan untuk ' . count($students) . ' siswa.');

        case 'delete_attendance_session':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $rombel = get_rombel($koneksi, inp('rombel'));
            $mapel = db_select_one($koneksi, 'SELECT * FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [inp('mapel'), inp('mapel')]);
            $date = inp('tanggal');
            $pertemuan = inp('pertemuan');
            if (!$rombel || !$mapel || $date === '' || $pertemuan === '') fail('Parameter tidak lengkap.');
            db_execute(
                $koneksi,
                'DELETE FROM presensi WHERE rombel_id = ? AND mapel_id = ? AND tanggal = ? AND pertemuan = ? AND guru_id = ?',
                'iissi',
                [(int) $rombel['id'], (int) $mapel['id'], $date, $pertemuan, (int) $guru['id']]
            );
            ok('Sesi presensi dihapus.');

        case 'get_wali_ledger':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $rombel = get_rombel($koneksi, inp('rombel'));
            if (!$rombel) fail('Rombel tidak ditemukan.');
            $isWali = db_select_one($koneksi, 'SELECT id FROM rombel WHERE id = ? AND wali_guru_id = ?', 'ii', [(int) $rombel['id'], (int) $guru['id']]);
            if (!$isWali && !is_kepsek($user)) fail('Menu rekap hanya untuk wali kelas rombel ini.', 403);

            // Mengambil seluruh mata pelajaran dan guru pengampu yang terikat pada rombel_id kelas binaan ini
            $pengampuList = db_select(
                $koneksi,
                "SELECT p.mapel_id, m.kode, m.nama AS mapel_nama, m.kkm, g.nama_guru
                 FROM pengampu p
                 JOIN mata_pelajaran m ON m.id = p.mapel_id
                 LEFT JOIN data_guru g ON g.id = p.guru_id
                 WHERE p.rombel_id = ?
                 ORDER BY m.kode, m.nama",
                'i',
                [(int) $rombel['id']]
            );

            // Jika tabel pengampu belum diisi untuk rombel ini, fallback ke daftar seluruh mata pelajaran
            if (empty($pengampuList)) {
                $allMapels = db_select($koneksi, 'SELECT id AS mapel_id, kode, nama AS mapel_nama, kkm FROM mata_pelajaran ORDER BY kode, nama');
                foreach ($allMapels as $am) {
                    $pengampuList[] = [
                        'mapel_id'   => $am['mapel_id'],
                        'kode'       => $am['kode'],
                        'mapel_nama' => $am['mapel_nama'],
                        'kkm'        => $am['kkm'],
                        'nama_guru'  => 'Belum Ditugaskan',
                    ];
                }
            }

            $data = [];
            foreach ($pengampuList as $p) {
                $mapelNama = (string) $p['mapel_nama'];
                if (isset($data[$mapelNama])) continue; // Hindari duplikasi jika ada pengampu ganda

                $mapelId = (int) $p['mapel_id'];
                $rows = db_select(
                    $koneksi,
                    "SELECT n.jenis_penilaian, n.nilai, n.kategori, n.created_at, s.id AS siswa_id
                     FROM nilai_siswa n
                     JOIN data_siswa s ON s.id = n.siswa_id
                     WHERE s.rombel_id = ? AND n.mapel_id = ?
                     ORDER BY n.id",
                    'ii',
                    [(int) $rombel['id'], $mapelId]
                );

                $columns = [];
                $scores = [];
                foreach ($rows as $r) {
                    $columns[$r['jenis_penilaian']] = [
                        'id'       => $r['jenis_penilaian'],
                        'name'     => $r['jenis_penilaian'],
                        'category' => $r['kategori'],
                        'date'     => date('d M Y', strtotime($r['created_at'])),
                    ];
                    $scores[(int) $r['siswa_id']][$r['jenis_penilaian']] = (float) $r['nilai'];
                }

                // Masukkan seluruh mapel yang diajarkan di kelas binaan ke dalam ledger (walaupun nilai belum diinput)
                $data[$mapelNama] = [
                    'mapel_id' => $mapelId,
                    'teacher'  => $p['nama_guru'] ?? 'Belum Ditugaskan',
                    'kkm'      => (int) $p['kkm'],
                    'columns'  => array_values($columns),
                    'scores'   => $scores,
                ];
            }
            ok('Rekap wali kelas dimuat.', ['ledger' => (object) $data, 'rombel' => $rombel['nama']]);

        /* ======================================================
         * SISWA — PORTAL
         * ====================================================== */
        /* ======================================================
         * GURU — MATERI / MODUL AJAR
         * Hanya guru PENGAMPU (mapel + rombel) yang boleh upload/ubah/hapus.
         * Wali kelas hanya boleh melihat modul yang berstatus Terbit.
         * ====================================================== */
        case 'get_materi':
        case 'list_materi':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $gid = (int) $guru['id'];
            [$rombel, $mapel] = materi_resolve($koneksi);
            if (!$rombel || !$mapel) fail('Kelas atau mata pelajaran tidak ditemukan.');

            $isPengampu = materi_is_pengampu($koneksi, $gid, (int) $mapel['id'], (int) $rombel['id']);
            $isWali = (bool) db_select_one($koneksi, 'SELECT id FROM rombel WHERE id = ? AND wali_guru_id = ?', 'ii', [(int) $rombel['id'], $gid]);
            if (!$isPengampu && !$isWali && !is_kepsek($user)) fail('Anda tidak berhak melihat materi kelas ini.', 403);

            if ($isPengampu) {
                $rows = db_select(
                    $koneksi,
                    "SELECT * FROM materi_modul WHERE rombel_id = ? AND mapel_id = ? AND (guru_id = ? OR status = 'Terbit') ORDER BY created_at DESC, id DESC",
                    'iii',
                    [(int) $rombel['id'], (int) $mapel['id'], $gid]
                );
            } else {
                $rows = db_select(
                    $koneksi,
                    "SELECT * FROM materi_modul WHERE rombel_id = ? AND mapel_id = ? AND status = 'Terbit' ORDER BY created_at DESC, id DESC",
                    'ii',
                    [(int) $rombel['id'], (int) $mapel['id']]
                );
            }
            ok('Modul dimuat.', [
                'modules'   => array_map(static fn(array $r): array => materi_format($r, $gid), $rows),
                'canUpload' => $isPengampu,
            ]);

        case 'upload_materi':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $gid = (int) $guru['id'];
            [$rombel, $mapel] = materi_resolve($koneksi);
            if (!$rombel || !$mapel) fail('Kelas atau mata pelajaran tidak ditemukan.');
            if (!materi_is_pengampu($koneksi, $gid, (int) $mapel['id'], (int) $rombel['id'])) {
                fail('Hanya guru pengampu mapel ini di kelas ini yang dapat mengunggah modul. Wali kelas hanya dapat memantau.', 403);
            }

            $judul = inp('judul');
            $deskripsi = inp('deskripsi');
            $status = inp('status', 'Terbit') === 'Draf' ? 'Draf' : 'Terbit';
            if ($judul === '') fail('Judul modul wajib diisi.');
            if (mb_strlen($judul) > 200) fail('Judul maksimal 200 karakter.');

            if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                fail('Pilih file modul terlebih dahulu.');
            }
            $f = $_FILES['file'];
            if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
                fail('File melebihi batas ukuran server. Naikkan upload_max_filesize dan post_max_size di php.ini.');
            }
            if ($f['error'] !== UPLOAD_ERR_OK) fail('Upload gagal (kode ' . $f['error'] . ').');
            if ($f['size'] > 15 * 1024 * 1024) fail('Ukuran file maksimal 15 MB.');

            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
            $namaAsli = basename((string) $f['name']);
            $ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                fail('Format file tidak didukung. Gunakan: ' . implode(', ', $allowed) . '.');
            }

            $dir = materi_dir();
            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) {
                fail('Gagal menyimpan file ke folder storage/materi. Cek izin folder.', 500);
            }
            @copy($dir . '/' . $stored, __DIR__ . '/uploads_materi/' . $stored);

            try {
                db_insert(
                    $koneksi,
                    'INSERT INTO materi_modul (guru_id, mapel_id, rombel_id, guru_kode, nama_guru, kelas, mata_pelajaran, judul, deskripsi, nama_file, path_file, file_path, ukuran, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    'iii' . str_repeat('s', 9) . 'is',
                    [
                        $gid, (int) $mapel['id'], (int) $rombel['id'],
                        (string) ($guru['kode'] ?? ''), (string) $guru['nama_guru'],
                        (string) $rombel['nama'], (string) $mapel['nama'],
                        $judul, $deskripsi, $namaAsli, $stored, $stored,
                        (int) $f['size'], $status,
                    ]
                );
            } catch (Throwable $e) {
                @unlink($dir . '/' . $stored);
                throw $e;
            }
            ok($status === 'Terbit' ? 'Modul diterbitkan ke siswa.' : 'Modul disimpan sebagai draf.');

        case 'toggle_materi_status':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $newStatus = inp('status') === 'Terbit' ? 'Terbit' : 'Draf';
            $affected = db_execute(
                $koneksi,
                'UPDATE materi_modul SET status = ? WHERE id = ? AND guru_id = ?',
                'sii',
                [$newStatus, inp_int('id'), (int) $guru['id']]
            );
            ok('Status modul: ' . $newStatus . '.', ['changed' => $affected]);

        case 'delete_materi':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $row = db_select_one($koneksi, 'SELECT * FROM materi_modul WHERE id = ? AND guru_id = ? LIMIT 1', 'ii', [inp_int('id'), (int) $guru['id']]);
            if (!$row) fail('Modul tidak ditemukan atau bukan milik Anda.', 404);
            db_execute($koneksi, 'DELETE FROM materi_modul WHERE id = ?', 'i', [(int) $row['id']]);
            $fileName = basename((string) (($row['path_file'] ?? '') ?: ($row['file_path'] ?? '')));
            if ($fileName !== '') {
                @unlink(materi_dir() . '/' . $fileName);
            }
            ok('Modul dihapus.');

        case 'student_bootstrap':
            assert_role($user, ['siswa']);
            $student = db_select_one(
                $koneksi,
                "SELECT s.*, r.nama AS rombel_nama, r.tingkat
                 FROM data_siswa s LEFT JOIN rombel r ON r.id = s.rombel_id
                 WHERE s.user_id = ? LIMIT 1",
                'i',
                [(int) $user['id']]
            );
            if (!$student) fail('Akun siswa belum terhubung ke data peserta didik.', 403);
            $sid = (int) $student['id'];

            // Modul yang diterbitkan guru untuk rombel siswa ini
            $modulesSiswa = [];
            try {
                foreach (db_select(
                    $koneksi,
                    "SELECT * FROM materi_modul WHERE rombel_id = ? AND status = 'Terbit' ORDER BY created_at DESC, id DESC",
                    'i',
                    [(int) ($student['rombel_id'] ?? 0)]
                ) as $mr) {
                    $modulesSiswa[] = materi_format($mr, 0);
                }
            } catch (Throwable $e) {
                error_log('[MATERI] ' . $e->getMessage());
            }

            $nilaiRows = db_select(
                $koneksi,
                "SELECT n.*, m.nama AS mapel_nama, m.kkm AS mapel_kkm, g.nama_guru
                 FROM nilai_siswa n
                 JOIN mata_pelajaran m ON m.id = n.mapel_id
                 LEFT JOIN data_guru g ON g.id = n.guru_id
                 WHERE n.siswa_id = ?
                 ORDER BY m.nama, n.id",
                'i',
                [$sid]
            );
            $subjects = [];
            $sum = 0.0;
            $count = 0;
            foreach ($nilaiRows as $n) {
                $key = $n['mapel_nama'];
                if (!isset($subjects[$key])) {
                    $subjects[$key] = [
                        'teacher' => $n['nama_guru'] ?: 'Guru Pengampu',
                        'kkm'     => (int) ($n['kkm'] ?: $n['mapel_kkm']),
                        'grades'  => [],
                        'sum'     => 0.0,
                    ];
                }
                $score = (float) $n['nilai'];
                $subjects[$key]['grades'][] = [
                    'no'     => count($subjects[$key]['grades']) + 1,
                    'title'  => $n['jenis_penilaian'],
                    'date'   => date('d M Y', strtotime($n['updated_at'] ?: $n['created_at'])),
                    'kkm'    => (int) $n['kkm'],
                    'score'  => $score,
                    'passed' => $score >= (int) $n['kkm'],
                ];
                $subjects[$key]['sum'] += $score;
                $sum += $score;
                $count++;
            }
            foreach ($subjects as &$s) {
                $avg = count($s['grades']) > 0 ? $s['sum'] / count($s['grades']) : 0;
                $s['average'] = number_format($avg, 1, '.', '');
                $s['passed'] = $avg >= $s['kkm'];
                unset($s['sum']);
            }
            unset($s);

            $presensi = db_select_one(
                $koneksi,
                "SELECT COUNT(*) AS total,
                        SUM(status = 'H') AS hadir,
                        SUM(status = 'I') AS izin,
                        SUM(status = 'S') AS sakit,
                        SUM(status = 'A') AS alpa
                 FROM presensi WHERE siswa_id = ?",
                'i',
                [$sid]
            );

            ok('Portal siswa dimuat.', [
                'student' => [
                    'id'       => $sid,
                    'nisn'     => $student['nisn'] ?: '-',
                    'name'     => $student['nama_siswa'],
                    'class'    => $student['rombel_nama'] ?: '-',
                    'tingkat'  => $student['tingkat'] !== null ? (int) $student['tingkat'] : null,
                    'gender'   => $student['gender'],
                    'parent'   => $student['orang_tua'] ?: '-',
                    'phone'    => $student['telepon'] ?: '-',
                    'status'   => $student['status'],
                    'catatan'  => $student['catatan_wali'] ?: '',
                    'overallAverage' => $count > 0 ? number_format($sum / $count, 1, '.', '') : '-',
                ],
                'subjects'  => (object) $subjects,
                'attendance' => [
                    'total' => (int) ($presensi['total'] ?? 0),
                    'hadir' => (int) ($presensi['hadir'] ?? 0),
                    'izin'  => (int) ($presensi['izin'] ?? 0),
                    'sakit' => (int) ($presensi['sakit'] ?? 0),
                    'alpa'  => (int) ($presensi['alpa'] ?? 0),
                ],
                'modules' => $modulesSiswa,
            ]);

        case 'get_semester_recap':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $rombel = get_rombel($koneksi, inp('rombel'));
            if (!$rombel) fail('Rombel tidak ditemukan.');

            // Identifikasi apakah guru yang login adalah wali kelas di rombel ini (atau Kepala Sekolah)
            $isWali = db_select_one($koneksi, 'SELECT id FROM rombel WHERE id = ? AND wali_guru_id = ?', 'ii', [(int) $rombel['id'], (int) $guru['id']]);
            $isWaliRole = (bool) $isWali || is_kepsek($user);

            $mapelInput = inp('mapel');
            $mapelCurrent = null;
            if ($mapelInput) {
                $mapelCurrent = db_select_one($koneksi, 'SELECT id, kode, nama, kkm FROM mata_pelajaran WHERE nama = ? OR kode = ? LIMIT 1', 'ss', [$mapelInput, $mapelInput]);
            }

            // ── Presensi per mapel AKTIF (digunakan oleh TABEL 1 — berlaku untuk SEMUA role) ──
            // Ini selalu difilter berdasarkan mapel yang sedang dipilih guru, bukan semua mapel.
            $mapelIdForAttend = $mapelCurrent ? (int) $mapelCurrent['id'] : 0;
            $presensiPerMapelMap = [];
            if ($mapelIdForAttend > 0) {
                $resPresensiMapel = db_select(
                    $koneksi,
                    "SELECT p.siswa_id,
                            COUNT(p.id) AS total_presensi,
                            SUM(CASE WHEN p.status = 'H' THEN 1 ELSE 0 END) AS hadir,
                            SUM(CASE WHEN p.status = 'I' THEN 1 ELSE 0 END) AS izin,
                            SUM(CASE WHEN p.status = 'S' THEN 1 ELSE 0 END) AS sakit,
                            SUM(CASE WHEN p.status = 'A' THEN 1 ELSE 0 END) AS alpa
                     FROM presensi p
                     JOIN data_siswa s ON s.id = p.siswa_id
                     WHERE s.rombel_id = ? AND p.mapel_id = ?
                     GROUP BY p.siswa_id",
                    'ii',
                    [(int) $rombel['id'], $mapelIdForAttend]
                );
                foreach ($resPresensiMapel as $rp) {
                    $presensiPerMapelMap[(int) $rp['siswa_id']] = $rp;
                }
            }

            if ($isWaliRole) {
                // WALI KELAS: Muat seluruh mata pelajaran aktif untuk Buku Induk (Tabel 2)
                $subjects = db_select($koneksi, 'SELECT id, kode, nama, kkm FROM mata_pelajaran ORDER BY id');
                
                // Matriks nilai: AVG per siswa per mapel (untuk crosstab Tabel 2)
                $matriksNilai = [];
                $resScores = db_select(
                    $koneksi,
                    "SELECT n.siswa_id, n.mapel_id, ROUND(AVG(n.nilai), 1) AS rerata
                     FROM nilai_siswa n
                     JOIN data_siswa s ON s.id = n.siswa_id
                     WHERE s.rombel_id = ?
                     GROUP BY n.siswa_id, n.mapel_id",
                    'i',
                    [(int) $rombel['id']]
                );
                foreach ($resScores as $rs) {
                    $matriksNilai[(int) $rs['siswa_id']][(int) $rs['mapel_id']] = (float) $rs['rerata'];
                }

                // Presensi SEMUA mapel per siswa (untuk Buku Induk — Tabel 2)
                $presensiAllMap = [];
                $resPresensiAll = db_select(
                    $koneksi,
                    "SELECT p.siswa_id,
                            COUNT(p.id) AS total_presensi,
                            SUM(CASE WHEN p.status = 'H' THEN 1 ELSE 0 END) AS hadir,
                            SUM(CASE WHEN p.status = 'I' THEN 1 ELSE 0 END) AS izin,
                            SUM(CASE WHEN p.status = 'S' THEN 1 ELSE 0 END) AS sakit,
                            SUM(CASE WHEN p.status = 'A' THEN 1 ELSE 0 END) AS alpa
                     FROM presensi p
                     JOIN data_siswa s ON s.id = p.siswa_id
                     WHERE s.rombel_id = ?
                     GROUP BY p.siswa_id",
                    'i',
                    [(int) $rombel['id']]
                );
                foreach ($resPresensiAll as $rp) {
                    $presensiAllMap[(int) $rp['siswa_id']] = $rp;
                }

                $students = db_select(
                    $koneksi,
                    "SELECT s.id, s.nisn, s.nama_siswa, s.catatan_wali
                     FROM data_siswa s
                     WHERE s.rombel_id = ? AND s.status = 'Aktif'
                     ORDER BY s.nama_siswa",
                    'i',
                    [(int) $rombel['id']]
                );

                $out = [];
                $sumAvg = 0.0;
                $avgCount = 0;
                $sumAtt = 0.0;
                $attCount = 0;

                foreach ($students as $s) {
                    $sId = (int) $s['id'];
                    $studentScores = $matriksNilai[$sId] ?? [];
                    
                    // Rata-rata gabungan seluruh mapel (untuk Tabel 2)
                    $scoresList = array_values($studentScores);
                    $avg = count($scoresList) > 0 ? round(array_sum($scoresList) / count($scoresList), 1) : null;
                    if ($avg !== null) {
                        $sumAvg += $avg;
                        $avgCount++;
                    }

                    // Presensi mapel aktif (untuk Tabel 1 — konsisten dengan Guru Mapel)
                    $pMapel = $presensiPerMapelMap[$sId] ?? [
                        'total_presensi' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0
                    ];
                    $totalPMapel = (int) $pMapel['total_presensi'];
                    $pctMapel = $totalPMapel > 0 ? round(((int) $pMapel['hadir'] / $totalPMapel) * 100, 1) : null;
                    if ($pctMapel !== null) { $sumAtt += $pctMapel; $attCount++; }

                    // Presensi semua mapel (untuk Tabel 2 — Buku Induk)
                    $pAll = $presensiAllMap[$sId] ?? [
                        'total_presensi' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0
                    ];
                    $totalPAll = (int) $pAll['total_presensi'];
                    $pctAll = $totalPAll > 0 ? round(((int) $pAll['hadir'] / $totalPAll) * 100, 1) : null;

                    $out[] = [
                        'id'          => $sId,
                        'nisn'        => $s['nisn'] ?: '-',
                        'name'        => $s['nama_siswa'],
                        'catatan'     => $s['catatan_wali'] ?: '',
                        'avg'         => $avg !== null ? $avg : '-',
                        'total_nilai' => count($scoresList),
                        'scores'      => (object) $studentScores,
                        // Tabel 1: presensi mapel aktif saja
                        'attendance'  => [
                            'total' => $totalPMapel,
                            'hadir' => (int) $pMapel['hadir'],
                            'izin'  => (int) $pMapel['izin'],
                            'sakit' => (int) $pMapel['sakit'],
                            'alpa'  => (int) $pMapel['alpa'],
                            'pct'   => $pctMapel,
                        ],
                        // Tabel 2: presensi semua mapel (khusus Buku Induk)
                        'ledgerAttendance' => [
                            'total' => $totalPAll,
                            'hadir' => (int) $pAll['hadir'],
                            'izin'  => (int) $pAll['izin'],
                            'sakit' => (int) $pAll['sakit'],
                            'alpa'  => (int) $pAll['alpa'],
                            'pct'   => $pctAll,
                        ],
                    ];
                }

                ok('Rekap semester wali kelas dimuat.', [
                    'isWali'        => true,
                    'subjects'      => $subjects,
                    'students'      => $out,
                    'classAvg'      => $avgCount ? round($sumAvg / $avgCount, 1) : 0,
                    'attendanceAvg' => $attCount ? round($sumAtt / $attCount, 1) : 0,
                    'rombel'        => $rombel['nama'],
                    'mapel'         => $mapelCurrent ? $mapelCurrent['nama'] : '',
                ]);
            } else {
                // GURU MAPEL: Hanya muat nilai & presensi untuk mata pelajaran yang diampunya saja
                $mapelId = $mapelCurrent ? (int) $mapelCurrent['id'] : 0;
                $subjects = $mapelCurrent ? [$mapelCurrent] : [];

                $rows = db_select(
                    $koneksi,
                    "SELECT s.id, s.nisn, s.nama_siswa, s.catatan_wali,
                            (SELECT ROUND(AVG(n.nilai), 1) FROM nilai_siswa n WHERE n.siswa_id = s.id AND n.mapel_id = ?) AS avg_nilai,
                            (SELECT COUNT(*) FROM nilai_siswa n WHERE n.siswa_id = s.id AND n.mapel_id = ?) AS total_nilai,
                            (SELECT COUNT(*) FROM presensi p WHERE p.siswa_id = s.id AND p.mapel_id = ?) AS total_presensi,
                            (SELECT COUNT(*) FROM presensi p WHERE p.siswa_id = s.id AND p.mapel_id = ? AND p.status = 'H') AS hadir,
                            (SELECT COUNT(*) FROM presensi p WHERE p.siswa_id = s.id AND p.mapel_id = ? AND p.status = 'I') AS izin,
                            (SELECT COUNT(*) FROM presensi p WHERE p.siswa_id = s.id AND p.mapel_id = ? AND p.status = 'S') AS sakit,
                            (SELECT COUNT(*) FROM presensi p WHERE p.siswa_id = s.id AND p.mapel_id = ? AND p.status = 'A') AS alpa
                     FROM data_siswa s
                     WHERE s.rombel_id = ? AND s.status = 'Aktif'
                     ORDER BY s.nama_siswa",
                    'iiiiiiii',
                    [$mapelId, $mapelId, $mapelId, $mapelId, $mapelId, $mapelId, $mapelId, (int) $rombel['id']]
                );

                $out = [];
                $sumAvg = 0.0;
                $avgCount = 0;
                $sumAtt = 0.0;
                $attCount = 0;
                foreach ($rows as $s) {
                    $totalPresensi = (int) $s['total_presensi'];
                    $pct = $totalPresensi > 0 ? round(((int) $s['hadir'] / $totalPresensi) * 100, 1) : null;
                    if ($s['avg_nilai'] !== null) {
                        $sumAvg += (float) $s['avg_nilai'];
                        $avgCount++;
                    }
                    if ($pct !== null) {
                        $sumAtt += $pct;
                        $attCount++;
                    }
                    $sScores = [];
                    if ($s['avg_nilai'] !== null && $mapelId > 0) {
                        $sScores[$mapelId] = (float) $s['avg_nilai'];
                    }
                    $out[] = [
                        'id'          => (int) $s['id'],
                        'nisn'        => $s['nisn'] ?: '-',
                        'name'        => $s['nama_siswa'],
                        'catatan'     => $s['catatan_wali'] ?: '',
                        'avg'         => $s['avg_nilai'] !== null ? (float) $s['avg_nilai'] : '-',
                        'total_nilai' => (int) $s['total_nilai'],
                        'scores'      => (object) $sScores,
                        'attendance'  => [
                            'total' => $totalPresensi,
                            'hadir' => (int) $s['hadir'],
                            'izin'  => (int) $s['izin'],
                            'sakit' => (int) $s['sakit'],
                            'alpa'  => (int) $s['alpa'],
                            'pct'   => $pct,
                        ],
                    ];
                }

                ok('Rekap mapel pengampu dimuat.', [
                    'isWali'        => false,
                    'subjects'      => $subjects,
                    'students'      => $out,
                    'classAvg'      => $avgCount ? round($sumAvg / $avgCount, 1) : 0,
                    'attendanceAvg' => $attCount ? round($sumAtt / $attCount, 1) : 0,
                    'rombel'        => $rombel['nama'],
                    'mapel'         => $mapelCurrent ? $mapelCurrent['nama'] : '',
                ]);
            }

        case 'save_student_note':
            assert_role($user, ['guru']);
            $guru = current_guru($koneksi, $user);
            $rombel = get_rombel($koneksi, inp('rombel'));
            $studentId = inp_int('student_id');
            $note = inp('note');
            if (!$rombel || $studentId <= 0) fail('Data tidak lengkap.');
            $isWali = db_select_one($koneksi, 'SELECT id FROM rombel WHERE id = ? AND wali_guru_id = ?', 'ii', [(int) $rombel['id'], (int) $guru['id']]);
            if (!$isWali && !is_kepsek($user)) fail('Hanya wali kelas yang dapat menyimpan catatan evaluasi.', 403);
            $updated = db_execute(
                $koneksi,
                'UPDATE data_siswa SET catatan_wali = ? WHERE id = ? AND rombel_id = ?',
                'sii',
                [$note, $studentId, (int) $rombel['id']]
            );
            if ($updated === 0 && !db_select_one($koneksi, 'SELECT id FROM data_siswa WHERE id = ? AND rombel_id = ?', 'ii', [$studentId, (int) $rombel['id']])) {
                fail('Siswa tidak ditemukan pada rombel ini.');
            }
            ok('Catatan evaluasi siswa disimpan.');

        default:
            fail('Aksi tidak dikenali: ' . ($action !== '' ? $action : '(kosong)'), 404);
    }
} catch (mysqli_sql_exception $e) {
    if ($koneksi->errno) {
        try { $koneksi->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('[API][' . $action . '] ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => APP_DEBUG ? 'Kesalahan database: ' . $e->getMessage() : 'Terjadi kesalahan pada database. Silakan coba lagi.',
    ], 500);
} catch (Throwable $e) {
    try { $koneksi->rollback(); } catch (Throwable $ignored) {}
    error_log('[API][' . $action . '] ' . $e->getMessage());
    $safeMessage = $e instanceof RuntimeException
        ? $e->getMessage()
        : (APP_DEBUG ? $e->getMessage() : 'Terjadi kesalahan pada server.');
    json_response(['success' => false, 'message' => $safeMessage], 500);
}