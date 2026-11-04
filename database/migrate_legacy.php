<?php
/**
 * ==============================================================
 * MIGRASI DATA LAMA -> SKEMA BARU
 * --------------------------------------------------------------
 * Jalankan dari terminal SETELAH database/schema.sql diimpor:
 *
 *   php database/migrate_legacy.php
 *
 * Prasyarat: tabel lama sudah di-rename menjadi:
 *   legacy_data_guru, legacy_data_siswa,
 *   legacy_nilai_siswa, legacy_jurnal_guru
 *
 * Skrip ini:
 *  - membuat akun users untuk guru (password: guru12345, hash bcrypt)
 *  - memindahkan profil ke data_guru / data_siswa (relasi rombel)
 *  - membuat penugasan pengampu dari string mapel & kelas lama
 *  - menetapkan wali kelas dari teks "Wali Kelas VIII.A"
 *  - memindahkan nilai & jurnal (bila ada)
 * ==============================================================
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Skrip migrasi hanya boleh dijalankan dari command line.');
}

require_once __DIR__ . '/../koneksi.php';

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

function table_exists(mysqli $db, string $table): bool
{
    $row = db_select_one(
        $db,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        's',
        [$table]
    );
    return $row !== null && (int) $row['c'] > 0;
}

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

function rombel_name(string $code): string
{
    if (!preg_match('/^([789])([A-I])$/', strtoupper($code), $m)) {
        return $code;
    }
    $roman = ['7' => 'VII', '8' => 'VIII', '9' => 'IX'][$m[1]] ?? $m[1];
    return $roman . ' ' . $m[2];
}

function resolve_mapel(mysqli $db, string $raw): ?int
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $row = db_select_one($db, 'SELECT id FROM mata_pelajaran WHERE kode = ? OR nama = ? LIMIT 1', 'ss', [$raw, $raw]);
    if ($row) {
        return (int) $row['id'];
    }
    $norm = strtolower(str_replace(['&', '/', '.', ',', '-', '_'], ' ', $raw));
    $norm = trim((string) preg_replace('/\s+/', ' ', $norm));
    $aliases = [
        'matematika' => 'Matematika', 'mtk' => 'Matematika',
        'ipa' => 'Ilmu Pengetahuan Alam (IPA)', 'ilmu pengetahuan alam' => 'Ilmu Pengetahuan Alam (IPA)',
        'b indonesia' => 'Bahasa Indonesia', 'bahasa indonesia' => 'Bahasa Indonesia', 'indonesia' => 'Bahasa Indonesia',
        'b inggris' => 'Bahasa Inggris', 'bahasa inggris' => 'Bahasa Inggris', 'inggris' => 'Bahasa Inggris',
        'pkn' => 'Pendidikan Pancasila', 'ppkn' => 'Pendidikan Pancasila', 'pancasila' => 'Pendidikan Pancasila', 'pendidkan pancasila' => 'Pendidikan Pancasila',
        'pai' => 'Pendidikan Agama & Budi Pekerti', 'agama' => 'Pendidikan Agama & Budi Pekerti',
        'ips' => 'Ilmu Pengetahuan Sosial (IPS)', 'ilmu pengetahuan sosial' => 'Ilmu Pengetahuan Sosial (IPS)',
        'informatika' => 'Informatika', 'tik' => 'Informatika',
        'pjok' => 'Pendidikan Jasmani (PJOK)', 'penjas' => 'Pendidikan Jasmani (PJOK)',
        'seni budaya' => 'Seni Budaya', 'seni' => 'Seni Budaya',
        'sunda' => 'Bahasa Sunda (Muatan Lokal)', 'bahasa sunda' => 'Bahasa Sunda (Muatan Lokal)', 'mulok' => 'Bahasa Sunda (Muatan Lokal)',
    ];
    foreach ($aliases as $needle => $target) {
        if ($norm === $needle || str_contains($norm, $needle)) {
            $m = db_select_one($db, 'SELECT id FROM mata_pelajaran WHERE nama = ?', 's', [$target]);
            if ($m) {
                return (int) $m['id'];
            }
        }
    }
    $m = db_select_one($db, 'SELECT id FROM mata_pelajaran WHERE nama LIKE ? LIMIT 1', 's', ['%' . $raw . '%']);
    return $m ? (int) $m['id'] : null;
}

function parse_tanggal(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

/* ==============================================================
 * CEK PRASYARAT
 * ============================================================== */
$required = ['legacy_data_guru', 'legacy_data_siswa'];
foreach ($required as $table) {
    if (!table_exists($koneksi, $table)) {
        out("SKIP: tabel {$table} tidak ditemukan. Tidak ada data lama untuk dimigrasikan.");
        exit(0);
    }
}

/**
 * Pengaman: jangan menimpa data baru tanpa --force.
 * Migrasi ulang akan MENGHAPUS data guru/siswa/nilai yang sudah ada.
 */
$force = in_array('--force', $argv ?? [], true);
$hasNewData = (int) (db_select_one($koneksi, 'SELECT COUNT(*) AS c FROM data_siswa')['c'] ?? 0) > 0
    || (int) (db_select_one($koneksi, 'SELECT COUNT(*) AS c FROM data_guru')['c'] ?? 0) > 0;
if ($hasNewData && !$force) {
    out('STOP: tabel data_guru/data_siswa sudah berisi data.');
    out('Migrasi ulang akan menghapus data tersebut. Jalankan dengan --force bila benar-benar ingin mengulang.');
    exit(1);
}

out('=== MIGRASI DATA AKADEMIK ===');
$guruRows = db_select($koneksi, 'SELECT * FROM legacy_data_guru ORDER BY CAST(kode AS UNSIGNED), kode');
$siswaRows = db_select($koneksi, 'SELECT * FROM legacy_data_siswa ORDER BY kelas, nama_siswa');
out('Data lama: ' . count($guruRows) . ' guru, ' . count($siswaRows) . ' siswa.');

/* ==============================================================
 * BERSIHKAN TABEL TUJUAN (agar skrip aman dijalankan ulang)
 * ============================================================== */
$koneksi->begin_transaction();
try {
    db_execute($koneksi, 'DELETE FROM arsip_siswa');
    db_execute($koneksi, 'DELETE FROM arsip_guru');
    db_execute($koneksi, 'DELETE FROM presensi');
    db_execute($koneksi, 'DELETE FROM nilai_siswa');
    db_execute($koneksi, 'DELETE FROM jurnal_guru');
    db_execute($koneksi, 'DELETE FROM pengampu');
    db_execute($koneksi, 'DELETE FROM data_siswa');
    db_execute($koneksi, 'UPDATE rombel SET wali_guru_id = NULL');
    db_execute($koneksi, 'DELETE FROM data_guru');
    db_execute($koneksi, "DELETE FROM users WHERE role <> 'admin'");
    $koneksi->commit();
    out('Tabel tujuan dibersihkan.');
} catch (Throwable $e) {
    $koneksi->rollback();
    out('GAGAL membersihkan tabel: ' . $e->getMessage());
    exit(1);
}

/* ==============================================================
 * 1. GURU -> users + data_guru
 * ============================================================== */
$guruMap = [];          // legacy kode => new id
$guruRoleById = [];     // new id => row legacy
$defaultGuruHash = password_hash(DEFAULT_TEACHER_PASSWORD, PASSWORD_DEFAULT);

$koneksi->begin_transaction();
try {
    foreach ($guruRows as $g) {
        $nip = trim((string) ($g['nip'] ?? ''));
        $nama = trim((string) ($g['nama_guru'] ?? ''));
        if ($nama === '') {
            continue;
        }

        $username = $nip !== '' ? $nip : ('guru' . $g['kode']);
        $existingUser = db_select_one($koneksi, 'SELECT id FROM users WHERE username = ?', 's', [$username]);
        if ($existingUser) {
            $userId = (int) $existingUser['id'];
            db_execute($koneksi, 'UPDATE users SET nama_lengkap = ? WHERE id = ?', 'si', [$nama, $userId]);
        } else {
            $userId = db_insert(
                $koneksi,
                'INSERT INTO users (username, nama_lengkap, password_hash, role) VALUES (?, ?, ?, ?)',
                'ssss',
                [$username, $nama, $defaultGuruHash, 'guru']
            );
        }

        $status = in_array(($g['status'] ?? ''), ['Aktif', 'Cuti', 'Non-Aktif'], true) ? $g['status'] : 'Aktif';
        $jam = (int) preg_replace('/[^0-9]/', '', (string) ($g['jam_per_minggu'] ?? '0'));

        $newId = db_insert(
            $koneksi,
            'INSERT INTO data_guru (user_id, kode, nip, nama_guru, mata_pelajaran, kelas, wali_kelas, phone, jam_per_minggu, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'isssssssis',
            [
                $userId,
                (string) ($g['kode'] ?? ''),
                $nip !== '' ? $nip : null,
                $nama,
                trim((string) ($g['mata_pelajaran'] ?? '')),
                trim((string) ($g['kelas'] ?? '')),
                trim((string) ($g['wali_kelas'] ?? '')),
                trim((string) ($g['phone'] ?? '')),
                $jam,
                $status,
            ]
        );
        $guruMap[(string) $g['kode']] = $newId;
        $guruRoleById[$newId] = $g;
    }
    $koneksi->commit();
    out('Guru dimigrasikan: ' . count($guruMap));
} catch (Throwable $e) {
    $koneksi->rollback();
    out('GAGAL migrasi guru: ' . $e->getMessage());
    exit(1);
}

/* ==============================================================
 * 2. SISWA -> data_siswa (+ akun bila NISN tersedia)
 * ============================================================== */
$rombelMap = [];
foreach (db_select($koneksi, 'SELECT id, nama, tingkat FROM rombel') as $r) {
    $rombelMap[$r['nama']] = (int) $r['id'];
}

$defaultSiswaHash = password_hash(DEFAULT_STUDENT_PASSWORD, PASSWORD_DEFAULT);
$seenNisn = [];
$studentCount = 0;
$accountCount = 0;

$koneksi->begin_transaction();
try {
    foreach ($siswaRows as $s) {
        $nama = trim((string) ($s['nama_siswa'] ?? ''));
        if ($nama === '') {
            continue;
        }
        $nisn = trim((string) ($s['nisn'] ?? ''));
        if ($nisn === '' || isset($seenNisn[$nisn])) {
            $nisn = null; // hindari duplikasi; NISN dapat dilengkapi lewat Admin
        } else {
            $seenNisn[$nisn] = true;
        }

        $kelas = trim((string) ($s['kelas'] ?? ''));
        $rombelId = $rombelMap[$kelas] ?? null;
        if ($rombelId === null) {
            $code = extract_class_code($kelas);
            if ($code !== null) {
                $rombelId = $rombelMap[rombel_name($code)] ?? null;
            }
        }

        $statusRaw = trim((string) ($s['status'] ?? 'Aktif'));
        $status = match ($statusRaw) {
            'Lulus' => 'Lulus',
            'Mutasi', 'Mutasi Keluar' => 'Mutasi',
            'Mengundurkan Diri' => 'Mengundurkan Diri',
            default => 'Aktif',
        };

        $userId = null;
        if ($nisn !== null) {
            $existingUser = db_select_one($koneksi, 'SELECT id FROM users WHERE username = ?', 's', [$nisn]);
            if ($existingUser) {
                $userId = (int) $existingUser['id'];
            } else {
                $userId = db_insert(
                    $koneksi,
                    'INSERT INTO users (username, nama_lengkap, password_hash, role) VALUES (?, ?, ?, ?)',
                    'ssss',
                    [$nisn, $nama, $defaultSiswaHash, 'siswa']
                );
                $accountCount++;
            }
        }

        db_insert(
            $koneksi,
            'INSERT INTO data_siswa (user_id, nisn, nama_siswa, rombel_id, gender, tanggal_lahir, orang_tua, telepon, catatan_wali, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'ississssss',
            [
                $userId,
                $nisn,
                $nama,
                $rombelId,
                strtoupper((string) ($s['gender'] ?? 'L')) === 'P' ? 'P' : 'L',
                parse_tanggal($s['tanggal_lahir'] ?? null),
                trim((string) ($s['orang_tua'] ?? '')) ?: null,
                trim((string) ($s['telepon'] ?? '')) ?: null,
                trim((string) ($s['catatan_wali'] ?? '')) ?: null,
                $status,
            ]
        );
        $studentCount++;
    }
    $koneksi->commit();
    out("Siswa dimigrasikan: {$studentCount} (akun login baru: {$accountCount}).");
} catch (Throwable $e) {
    $koneksi->rollback();
    out('GAGAL migrasi siswa: ' . $e->getMessage());
    exit(1);
}

/* ==============================================================
 * 3. PENUGASAN GURU (pengampu) & WALI KELAS
 * ============================================================== */
$assignCount = 0;
$waliCount = 0;
$koneksi->begin_transaction();
try {
    foreach ($guruRoleById as $guruId => $g) {
        // --- Wali kelas dari teks "Wali Kelas VIII.A" ---
        $waliText = trim((string) ($g['wali_kelas'] ?? ''));
        $waliCode = extract_class_code($waliText);
        if ($waliCode !== null) {
            $rombel = db_select_one($koneksi, 'SELECT id FROM rombel WHERE nama = ?', 's', [rombel_name($waliCode)]);
            if ($rombel) {
                db_execute($koneksi, 'UPDATE rombel SET wali_guru_id = ? WHERE id = ?', 'ii', [$guruId, (int) $rombel['id']]);
                db_execute($koneksi, 'UPDATE data_guru SET wali_kelas = ? WHERE id = ?', 'si', ['Wali Kelas ' . rombel_name($waliCode), $guruId]);
                $waliCount++;
            }
        }

        // --- Pengampu dari mapel & kelas ---
        $mapelIds = [];
        foreach (preg_split('/[\/,]+/', (string) ($g['mata_pelajaran'] ?? '')) as $part) {
            $mid = resolve_mapel($koneksi, $part);
            if ($mid) {
                $mapelIds[$mid] = true;
            }
        }
        $classCodes = parse_class_codes((string) ($g['kelas'] ?? ''));
        if (empty($classCodes)) {
            $code = extract_class_code((string) ($g['kelas'] ?? ''));
            if ($code) {
                $classCodes = [$code];
            }
        }

        foreach (array_keys($mapelIds) as $mid) {
            foreach ($classCodes as $code) {
                $rombel = db_select_one($koneksi, 'SELECT id FROM rombel WHERE nama = ?', 's', [rombel_name($code)]);
                if (!$rombel) {
                    continue;
                }
                try {
                    db_insert(
                        $koneksi,
                        'INSERT INTO pengampu (guru_id, mapel_id, rombel_id, tahun_ajaran, semester) VALUES (?, ?, ?, ?, ?)',
                        'iiiss',
                        [$guruId, $mid, (int) $rombel['id'], TAHUN_AJARAN, SEMESTER_AKTIF === 'Genap' ? 'Genap' : 'Ganjil']
                    );
                    $assignCount++;
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() !== 1062) {
                        throw $e;
                    }
                }
            }
        }
    }
    $koneksi->commit();
    out("Penugasan mengajar dibuat: {$assignCount}. Wali kelas ditetapkan: {$waliCount}.");
} catch (Throwable $e) {
    $koneksi->rollback();
    out('GAGAL membuat penugasan: ' . $e->getMessage());
    exit(1);
}

/* ==============================================================
 * 4. NILAI & JURNAL (bila tabel lama tersedia)
 * ============================================================== */
$nilaiCount = 0;
if (table_exists($koneksi, 'legacy_nilai_siswa')) {
    $rows = db_select($koneksi, 'SELECT * FROM legacy_nilai_siswa');
    $koneksi->begin_transaction();
    try {
        foreach ($rows as $n) {
            $nisn = trim((string) ($n['nisn'] ?? ''));
            if ($nisn === '') {
                continue;
            }
            $student = db_select_one($koneksi, 'SELECT id, rombel_id FROM data_siswa WHERE nisn = ?', 's', [$nisn]);
            if (!$student) {
                continue;
            }
            $mapelId = resolve_mapel($koneksi, (string) ($n['mata_pelajaran'] ?? ''));
            if (!$mapelId) {
                continue;
            }
            $rombel = db_select_one($koneksi, 'SELECT id FROM rombel WHERE nama = ?', 's', [trim((string) ($n['kelas'] ?? ''))]);
            $guruId = null;
            $guruName = trim((string) ($n['guru_pengampu'] ?? ''));
            if ($guruName !== '') {
                $g = db_select_one($koneksi, 'SELECT id FROM data_guru WHERE nama_guru = ? LIMIT 1', 's', [$guruName]);
                $guruId = $g ? (int) $g['id'] : null;
            }
            try {
                db_insert(
                    $koneksi,
                    'INSERT INTO nilai_siswa (siswa_id, mapel_id, guru_id, rombel_id, jenis_penilaian, kategori, nilai, kkm, keterangan)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    'iiiissdis',
                    [
                        (int) $student['id'],
                        $mapelId,
                        $guruId,
                        $rombel ? (int) $rombel['id'] : ($student['rombel_id'] ? (int) $student['rombel_id'] : null),
                        trim((string) ($n['jenis_penilaian'] ?? 'Tugas')),
                        stripos((string) ($n['jenis_penilaian'] ?? ''), 'sumatif') !== false ? 'Sumatif' : 'Formatif',
                        (float) ($n['nilai'] ?? 0),
                        (int) ($n['kkm'] ?? 75),
                        ((float) ($n['nilai'] ?? 0) >= (int) ($n['kkm'] ?? 75)) ? 'Tuntas' : 'Remedial',
                    ]
                );
                $nilaiCount++;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062) {
                    throw $e;
                }
            }
        }
        $koneksi->commit();
        out("Nilai dimigrasikan: {$nilaiCount}.");
    } catch (Throwable $e) {
        $koneksi->rollback();
        out('GAGAL migrasi nilai: ' . $e->getMessage());
    }
}

$jurnalCount = 0;
if (table_exists($koneksi, 'legacy_jurnal_guru')) {
    $rows = db_select($koneksi, 'SELECT * FROM legacy_jurnal_guru');
    $koneksi->begin_transaction();
    try {
        foreach ($rows as $j) {
            $guruId = $guruMap[(string) ($j['guru_kode'] ?? '')] ?? null;
            if (!$guruId) {
                $g = db_select_one($koneksi, 'SELECT id FROM data_guru WHERE nama_guru = ? LIMIT 1', 's', [trim((string) ($j['nama_guru'] ?? ''))]);
                $guruId = $g ? (int) $g['id'] : null;
            }
            $mapelId = resolve_mapel($koneksi, (string) ($j['mata_pelajaran'] ?? ''));
            $rombel = db_select_one($koneksi, 'SELECT id FROM rombel WHERE nama = ?', 's', [trim((string) ($j['kelas'] ?? ''))]);
            if (!$guruId || !$mapelId || !$rombel) {
                continue;
            }
            db_insert(
                $koneksi,
                'INSERT INTO jurnal_guru (guru_id, mapel_id, rombel_id, hari, tanggal, pertemuan, materi, kegiatan, hadir, izin, sakit, alpa)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iiisssssiiii',
                [
                    $guruId,
                    $mapelId,
                    (int) $rombel['id'],
                    (string) ($j['hari'] ?? 'Senin'),
                    parse_tanggal($j['tanggal'] ?? null) ?: date('Y-m-d'),
                    (string) ($j['pertemuan'] ?? 'Pertemuan'),
                    (string) ($j['materi'] ?? '-'),
                    (string) ($j['kegiatan'] ?? '-'),
                    (int) ($j['hadir'] ?? 0),
                    (int) ($j['izin'] ?? 0),
                    (int) ($j['sakit'] ?? 0),
                    (int) ($j['alpa'] ?? 0),
                ]
            );
            $jurnalCount++;
        }
        $koneksi->commit();
        out("Jurnal dimigrasikan: {$jurnalCount}.");
    } catch (Throwable $e) {
        $koneksi->rollback();
        out('GAGAL migrasi jurnal: ' . $e->getMessage());
    }
}

out('=== MIGRASI SELESAI ===');
out('Akun guru default: username = NIP, password = ' . DEFAULT_TEACHER_PASSWORD);
out('Akun siswa default: username = NISN, password = ' . DEFAULT_STUDENT_PASSWORD . ' (hanya bila NISN tersedia).');
out('Segera minta pengguna mengganti kata sandi setelah login pertama.');
