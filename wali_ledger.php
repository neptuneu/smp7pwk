<?php
/**
 * ==============================================================
 * WALI LEDGER — Hak Akses Dinamis & Rekapitulasi Ledger Rapor
 * --------------------------------------------------------------
 * Modul ini dipakai oleh guru.php untuk:
 *   1. Menentukan $hak_akses_kelas ('wali_kelas' | 'guru_mapel').
 *   2. Mengambil konsolidasi nilai (AVG per siswa per mapel).
 *   3. Mengambil akumulasi presensi (SUM CASE WHEN H/I/S/A).
 *   4. Menyusun matriks 2D siap-render untuk Buku Induk.
 *
 * KOMPATIBEL DUA VARIAN SKEMA:
 *   A) rombel.wali_guru_id  -> data_guru.id -> data_guru.user_id -> users.id
 *   B) rombel.wall_guru_id  -> users.id (langsung)
 * Nama kolom siswa  : nama_siswa | nama_lengkap
 * Nama kolom mapel  : nama | nama_mapel
 * KKM               : mata_pelajaran.kkm | nilai_siswa.kkm
 *
 * Deteksi dilakukan lewat information_schema / SHOW COLUMNS,
 * jadi tidak ada query yang gagal hanya karena nama kolom berbeda.
 * ==============================================================
 */
declare(strict_types=1);

/**
 * false = hanya mapel yang punya nilai / guru pengampu di rombel tsb.
 * true  = tampilkan seluruh mapel yang ada di tabel mata_pelajaran.
 */
if (!defined('LEDGER_TAMPILKAN_SEMUA_MAPEL')) {
    define('LEDGER_TAMPILKAN_SEMUA_MAPEL', false);
}

/* ==============================================================
 * A. UTILITAS SKEMA (cache per request)
 * ============================================================== */

/**
 * Daftar kolom sebuah tabel: ['nama_kolom_lower' => 'NamaKolomAsli'].
 *
 * @return array<string,string>
 */
function ledger_columns(mysqli $db, string $table): array
{
    static $cache = [];
    $key = strtolower($table);

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $columns = [];
    try {
        $result = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        while ($result && ($row = $result->fetch_assoc())) {
            $name = (string) ($row['Field'] ?? '');
            if ($name !== '') {
                $columns[strtolower($name)] = $name;
            }
        }
    } catch (Throwable $e) {
        error_log('[LEDGER] SHOW COLUMNS ' . $table . ': ' . $e->getMessage());
    }

    return $cache[$key] = $columns;
}

/**
 * Ambil nama kolom asli dari daftar kandidat (urutan = prioritas).
 *
 * @param array<string,string> $columns
 * @param array<int,string>    $candidates
 */
function ledger_column(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($columns[$key])) {
            return $columns[$key];
        }
    }
    return null;
}

function ledger_has_table(mysqli $db, string $table): bool
{
    static $cache = [];
    $key = strtolower($table);

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $row = db_select_one(
            $db,
            'SELECT 1 AS ada FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
            's',
            [$table]
        );
        return $cache[$key] = (bool) $row;
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

/**
 * Tabel yang direferensikan sebuah Foreign Key (null bila bukan FK / tidak ada).
 */
function ledger_fk_target(mysqli $db, string $table, string $column): ?string
{
    try {
        $row = db_select_one(
            $db,
            'SELECT REFERENCED_TABLE_NAME AS tabel
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            'ss',
            [$table, $column]
        );
        return $row ? strtolower((string) $row['tabel']) : null;
    } catch (Throwable $e) {
        return null;
    }
}

/* ==============================================================
 * B. SESI & HAK AKSES
 * ============================================================== */

/**
 * ID user yang sedang login.
 * Mendukung $_SESSION['user_id'] (permintaan fitur) sekaligus
 * struktur sesi aplikasi ini: $_SESSION['user']['id'].
 */
function ledger_current_user_id(): ?int
{
    foreach (['user_id', 'id_user', 'auth_user_id'] as $key) {
        if (!empty($_SESSION[$key])) {
            return (int) $_SESSION[$key];
        }
    }
    if (!empty($_SESSION['user']['id'])) {
        return (int) $_SESSION['user']['id'];
    }
    if (!empty($_SESSION['id'])) {
        return (int) $_SESSION['id'];
    }
    return null;
}

/** ID profil guru (data_guru.id) yang tersimpan di sesi saat login. */
function ledger_current_profile_id(): ?int
{
    if (!empty($_SESSION['user']['profile_id'])) {
        return (int) $_SESSION['user']['profile_id'];
    }
    return !empty($_SESSION['profile_id']) ? (int) $_SESSION['profile_id'] : null;
}

/** Nama kolom wali kelas pada tabel rombel. */
function ledger_wali_column(mysqli $db): ?string
{
    return ledger_column(
        ledger_columns($db, 'rombel'),
        ['wali_guru_id', 'wall_guru_id', 'wali_kelas_id', 'wali_user_id', 'guru_id']
    );
}

/**
 * Menentukan arti kolom wali: 'users' (FK ke users.id) atau
 * 'data_guru' (FK ke data_guru.id).
 */
function ledger_wali_mode(mysqli $db, ?string $column = null): string
{
    static $cache = [];

    $column = $column ?? ledger_wali_column($db);
    if ($column === null) {
        return 'users';
    }

    $key = strtolower($column);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $target = ledger_fk_target($db, 'rombel', $column);
    if ($target === 'users') {
        return $cache[$key] = 'users';
    }
    if ($target === 'data_guru') {
        return $cache[$key] = 'data_guru';
    }

    // Tanpa FK eksplisit: pakai pola nama kolom sebagai heuristik.
    if (str_starts_with($key, 'wall') || str_contains($key, 'user')) {
        return $cache[$key] = 'users';
    }
    if (str_contains($key, 'guru') && ledger_has_table($db, 'data_guru')) {
        return $cache[$key] = 'data_guru';
    }

    return $cache[$key] = 'users';
}

/**
 * Referensi wali kelas sebuah rombel.
 *
 * @return array{id:int,mode:string,column:string}|null
 */
function ledger_wali_reference(mysqli $db, int $rombel_id): ?array
{
    $column = ledger_wali_column($db);
    if ($column === null || $rombel_id <= 0) {
        return null;
    }

    try {
        $row = db_select_one($db, 'SELECT * FROM rombel WHERE id = ? LIMIT 1', 'i', [$rombel_id]);
    } catch (Throwable $e) {
        error_log('[LEDGER] rombel: ' . $e->getMessage());
        return null;
    }

    if (!$row) {
        return null;
    }

    $value = $row[$column] ?? null;
    if ($value === null || (int) $value <= 0) {
        return null;
    }

    return [
        'id'     => (int) $value,
        'mode'   => ledger_wali_mode($db, $column),
        'column' => $column,
    ];
}

/**
 * Nama wali kelas (opsional, hanya untuk tampilan header).
 */
function ledger_nama_guru(mysqli $db, array $reference): ?string
{
    try {
        if (($reference['mode'] ?? '') === 'data_guru' && ledger_has_table($db, 'data_guru')) {
            $columns = ledger_columns($db, 'data_guru');
            $namaCol = ledger_column($columns, ['nama_guru', 'nama_lengkap', 'nama', 'username']);
            if ($namaCol === null) {
                return null;
            }
            $row = db_select_one($db, "SELECT `$namaCol` AS nama FROM data_guru WHERE id = ? LIMIT 1", 'i', [$reference['id']]);
        } else {
            $columns = ledger_columns($db, 'users');
            $namaCol = ledger_column($columns, ['nama_lengkap', 'nama', 'username']);
            if ($namaCol === null) {
                return null;
            }
            $row = db_select_one($db, "SELECT `$namaCol` AS nama FROM users WHERE id = ? LIMIT 1", 'i', [$reference['id']]);
        }

        return $row && $row['nama'] !== null ? (string) $row['nama'] : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * INTI FITUR 1 — pengecekan role wali kelas secara dinamis.
 *
 * @return string 'wali_kelas' bila user sesi adalah wali rombel ini,
 *                selain itu 'guru_mapel'.
 */
function tentukan_hak_akses_kelas(mysqli $db, ?int $user_id, ?int $rombel_id): string
{
    if (isset($_SESSION['user_username']) && defined('KEPSEK_USERNAME') && $_SESSION['user_username'] === KEPSEK_USERNAME) {
        return 'wali_kelas';
    }
    if ($user_id === null || $user_id <= 0 || $rombel_id === null || $rombel_id <= 0) {
        return 'guru_mapel';
    }

    $reference = ledger_wali_reference($db, $rombel_id);
    if ($reference === null) {
        return 'guru_mapel';
    }

    // Varian B: rombel.wall_guru_id / wali_guru_id menyimpan users.id.
    if ($reference['mode'] === 'users') {
        return $reference['id'] === $user_id ? 'wali_kelas' : 'guru_mapel';
    }

    // Varian A: kolom menyimpan data_guru.id.
    $profileId = ledger_current_profile_id();
    if ($profileId !== null && $reference['id'] === $profileId) {
        return 'wali_kelas';
    }

    try {
        $match = db_select_one(
            $db,
            'SELECT id FROM data_guru WHERE id = ? AND user_id = ? LIMIT 1',
            'ii',
            [$reference['id'], $user_id]
        );
        if ($match) {
            return 'wali_kelas';
        }
    } catch (Throwable $e) {
        error_log('[LEDGER] cek wali: ' . $e->getMessage());
    }

    return 'guru_mapel';
}

/**
 * Cari rombel yang diwalikan guru ini (dipakai bila URL tanpa ?rombel_id).
 */
function cari_rombel_wali(mysqli $db, ?int $user_id): int
{
    if ($user_id === null || $user_id <= 0) {
        return 0;
    }

    $column = ledger_wali_column($db);
    if ($column === null) {
        return 0;
    }

    try {
        if (ledger_wali_mode($db, $column) === 'users') {
            $row = db_select_one(
                $db,
                "SELECT id FROM rombel WHERE `$column` = ? ORDER BY tingkat ASC, nama ASC LIMIT 1",
                'i',
                [$user_id]
            );
        } else {
            $row = db_select_one(
                $db,
                "SELECT r.id FROM rombel r
                 JOIN data_guru dg ON dg.id = r.`$column`
                 WHERE dg.user_id = ? OR dg.id = ?
                 ORDER BY r.tingkat ASC, r.nama ASC
                 LIMIT 1",
                'ii',
                [$user_id, ledger_current_profile_id() ?? 0]
            );
        }

        return $row ? (int) $row['id'] : 0;
    } catch (Throwable $e) {
        error_log('[LEDGER] cari rombel: ' . $e->getMessage());
        return 0;
    }
}

/* ==============================================================
 * C. HELPER TAMPILAN
 * ============================================================== */

function ledger_predikat(?float $nilai): ?string
{
    if ($nilai === null) {
        return null;
    }
    if ($nilai >= 90) {
        return 'A';
    }
    if ($nilai >= 80) {
        return 'B';
    }
    if ($nilai >= 70) {
        return 'C';
    }
    return 'D';
}

function ledger_predikat_label(?string $predikat): string
{
    return match ($predikat) {
        'A' => 'Sangat Baik',
        'B' => 'Baik',
        'C' => 'Cukup',
        'D' => 'Perlu Bimbingan',
        default => '-',
    };
}

/* ==============================================================
 * D. INTI FITUR 2 & 3 — KONSOLIDASI LEDGER RAPOR
 * ============================================================== */

/**
 * Mengambil seluruh data Buku Induk/Ledger untuk satu rombel.
 *
 * @return array<string,mixed>
 */
function fetch_ledger_rapor(mysqli $db, int $rombel_id): array
{
    $hasil = [
        'rombel'         => null,
        'wali_nama'      => null,
        'tahun_ajaran'   => defined('TAHUN_AJARAN') ? TAHUN_AJARAN : '-',
        'semester'       => defined('SEMESTER_AKTIF') ? SEMESTER_AKTIF : '-',
        'daftar_mapel'   => [],
        'daftar_siswa'   => [],
        'matriks_nilai'  => [],   // [siswa_id][mapel_id] => rerata
        'kkm_mapel'      => [],   // [mapel_id] => kkm
        'rekap_presensi' => [],   // [siswa_id] => [total_h, ...]
        'ringkasan'      => [
            'jumlah_siswa'        => 0,
            'jumlah_mapel'        => 0,
            'rata_kelas'          => null,
            'persen_hadir'        => null,
            'siswa_belum_lengkap' => 0,
        ],
        'error'          => null,
    ];

    try {
        // ---------- 1. Rombel & wali ----------
        $hasil['rombel'] = db_select_one($db, 'SELECT * FROM rombel WHERE id = ? LIMIT 1', 'i', [$rombel_id]);
        if (!$hasil['rombel']) {
            $hasil['error'] = 'Rombel tidak ditemukan.';
            return $hasil;
        }

        $reference = ledger_wali_reference($db, $rombel_id);
        if ($reference !== null) {
            $hasil['wali_nama'] = ledger_nama_guru($db, $reference);
        }

        // ---------- 2. Siswa rombel ----------
        $kolomSiswa = ledger_columns($db, 'data_siswa');
        $namaSiswaCol = ledger_column($kolomSiswa, ['nama_siswa', 'nama_lengkap', 'nama']);
        if ($namaSiswaCol === null) {
            throw new RuntimeException('Kolom nama siswa (nama_siswa/nama_lengkap) tidak ditemukan pada data_siswa.');
        }

        $nisnSelect = isset($kolomSiswa['nisn']) ? "IFNULL(`nisn`, '-')" : "'-'";
        $hasil['daftar_siswa'] = db_select(
            $db,
            "SELECT `id`, $nisnSelect AS nisn, `$namaSiswaCol` AS nama_siswa
             FROM data_siswa
             WHERE rombel_id = ?
             ORDER BY `$namaSiswaCol` ASC",
            'i',
            [$rombel_id]
        );

        // ---------- 3. Master mata pelajaran ----------
        $kolomMapel = ledger_columns($db, 'mata_pelajaran');
        $namaMapelCol = ledger_column($kolomMapel, ['nama_mapel', 'nama', 'mata_pelajaran']);
        if ($namaMapelCol === null) {
            throw new RuntimeException('Kolom nama mapel (nama_mapel/nama) tidak ditemukan pada mata_pelajaran.');
        }
        $kkmMapelSelect = isset($kolomMapel['kkm']) ? '`kkm`' : 'NULL';
        $semuaMapel = db_select(
            $db,
            "SELECT `id`, `$namaMapelCol` AS nama_mapel, $kkmMapelSelect AS kkm
             FROM mata_pelajaran
             ORDER BY `$namaMapelCol` ASC"
        );

        // ---------- 4. Rerata nilai per siswa per mapel ----------
        $kolomNilai = ledger_columns($db, 'nilai_siswa');
        if (!isset($kolomNilai['siswa_id'], $kolomNilai['mapel_id'], $kolomNilai['nilai'])) {
            throw new RuntimeException('Tabel nilai_siswa tidak memiliki kolom siswa_id/mapel_id/nilai.');
        }
        $kkmNilaiSelect = isset($kolomNilai['kkm']) ? 'MAX(n.`kkm`)' : 'NULL';

        $barisNilai = db_select(
            $db,
            "SELECT n.`siswa_id`,
                    n.`mapel_id`,
                    AVG(n.`nilai`)  AS rerata,
                    $kkmNilaiSelect AS kkm_tertinggi,
                    COUNT(n.`id`)   AS jumlah_penilaian
             FROM nilai_siswa n
             JOIN data_siswa s ON s.id = n.`siswa_id`
             WHERE s.`rombel_id` = ?
             GROUP BY n.`siswa_id`, n.`mapel_id`",
            'i',
            [$rombel_id]
        );

        $mapelBernilai = [];
        foreach ($barisNilai as $row) {
            $siswaId = (int) $row['siswa_id'];
            $mapelId = (int) $row['mapel_id'];
            $hasil['matriks_nilai'][$siswaId][$mapelId] = round((float) $row['rerata'], 2);
            $mapelBernilai[$mapelId] = true;

            if ($row['kkm_tertinggi'] !== null) {
                $hasil['kkm_mapel'][$mapelId] = (int) $row['kkm_tertinggi'];
            }
        }

        // ---------- 5. Mapel yang diajarkan di rombel (pengampu) ----------
        $mapelPengampu = [];
        if (ledger_has_table($db, 'pengampu')) {
            foreach (db_select(
                $db,
                'SELECT DISTINCT `mapel_id` FROM pengampu WHERE `rombel_id` = ?',
                'i',
                [$rombel_id]
            ) as $row) {
                $mapelPengampu[(int) $row['mapel_id']] = true;
            }
        }

        // ---------- 6. Tentukan kolom mapel yang ditampilkan ----------
        if (LEDGER_TAMPILKAN_SEMUA_MAPEL) {
            $daftarMapel = $semuaMapel;
        } else {
            $daftarMapel = [];
            foreach ($semuaMapel as $mapel) {
                $mapelId = (int) $mapel['id'];
                if (isset($mapelBernilai[$mapelId]) || isset($mapelPengampu[$mapelId])) {
                    $daftarMapel[] = $mapel;
                }
            }
            // Jangan sampai tabel kosong hanya karena belum ada penugasan.
            if ($daftarMapel === []) {
                $daftarMapel = $semuaMapel;
            }
        }

        $hasil['daftar_mapel'] = $daftarMapel;

        // Pastikan setiap mapel punya KKM final.
        foreach ($daftarMapel as $mapel) {
            $mapelId = (int) $mapel['id'];
            $kkm = (int) ($mapel['kkm'] ?? 0);
            if ($kkm <= 0 && isset($hasil['kkm_mapel'][$mapelId])) {
                $kkm = (int) $hasil['kkm_mapel'][$mapelId];
            }
            if ($kkm <= 0) {
                $kkm = 75;
            }
            $hasil['kkm_mapel'][$mapelId] = $kkm;
        }

        // ---------- 7. Akumulasi presensi seluruh mapel ----------
        $hasil['rekap_presensi'] = [];
        foreach (db_select(
            $db,
            "SELECT p.`siswa_id`,
                    SUM(CASE WHEN p.`status` = 'H' THEN 1 ELSE 0 END) AS total_h,
                    SUM(CASE WHEN p.`status` = 'I' THEN 1 ELSE 0 END) AS total_i,
                    SUM(CASE WHEN p.`status` = 'S' THEN 1 ELSE 0 END) AS total_s,
                    SUM(CASE WHEN p.`status` = 'A' THEN 1 ELSE 0 END) AS total_a,
                    COUNT(p.`id`)                                        AS total_pertemuan,
                    COUNT(DISTINCT p.`mapel_id`)                         AS total_mapel,
                    COUNT(DISTINCT CONCAT(p.`mapel_id`, '#', p.`tanggal`, '#', p.`pertemuan`)) AS total_sesi
             FROM presensi p
             JOIN data_siswa s ON s.id = p.`siswa_id`
             WHERE s.`rombel_id` = ?
             GROUP BY p.`siswa_id`",
            'i',
            [$rombel_id]
        ) as $row) {
            $hasil['rekap_presensi'][(int) $row['siswa_id']] = [
                'total_h'         => (int) $row['total_h'],
                'total_i'         => (int) $row['total_i'],
                'total_s'         => (int) $row['total_s'],
                'total_a'         => (int) $row['total_a'],
                'total_pertemuan' => (int) $row['total_pertemuan'],
                'total_mapel'     => (int) $row['total_mapel'],
                'total_sesi'      => (int) $row['total_sesi'],
            ];
        }

        // ---------- 8. Susun baris siswa + rata-rata + peringkat ----------
        $jumlahMapel = count($daftarMapel);
        $totalRata = 0.0;
        $siswaBernilai = 0;

        foreach ($hasil['daftar_siswa'] as $index => $siswa) {
            $siswaId = (int) $siswa['id'];

            $presensi = $hasil['rekap_presensi'][$siswaId] ?? [
                'total_h' => 0, 'total_i' => 0, 'total_s' => 0, 'total_a' => 0,
                'total_pertemuan' => 0, 'total_mapel' => 0, 'total_sesi' => 0,
            ];

            $jumlahNilai = 0;
            $jumlahNilaiTotal = 0.0;

            foreach ($daftarMapel as $mapel) {
                $mapelId = (int) $mapel['id'];
                if (isset($hasil['matriks_nilai'][$siswaId][$mapelId])) {
                    $jumlahNilai++;
                    $jumlahNilaiTotal += $hasil['matriks_nilai'][$siswaId][$mapelId];
                }
            }

            $rataRata = $jumlahNilai > 0 ? round($jumlahNilaiTotal / $jumlahNilai, 2) : null;

            $totalPresensi = $presensi['total_h'] + $presensi['total_i'] + $presensi['total_s'] + $presensi['total_a'];
            if ($totalPresensi <= 0) {
                $totalPresensi = $presensi['total_pertemuan'];
            }
            $persenHadir = $totalPresensi > 0
                ? round(($presensi['total_h'] / $totalPresensi) * 100, 1)
                : null;

            $hasil['daftar_siswa'][$index]['nomor'] = $index + 1;
            $hasil['daftar_siswa'][$index]['jumlah_mapel_terisi'] = $jumlahNilai;
            $hasil['daftar_siswa'][$index]['rata_rata'] = $rataRata;
            $hasil['daftar_siswa'][$index]['predikat'] = ledger_predikat($rataRata);
            $hasil['daftar_siswa'][$index]['persen_hadir'] = $persenHadir;
            $hasil['daftar_siswa'][$index]['presensi'] = $presensi;

            if ($rataRata !== null) {
                $totalRata += $rataRata;
                $siswaBernilai++;
            }
            if ($jumlahNilai < $jumlahMapel) {
                $hasil['ringkasan']['siswa_belum_lengkap']++;
            }
        }

        // Peringkat (competition ranking; nilai kosong tidak diberi peringkat).
        $urut = $hasil['daftar_siswa'];
        usort($urut, static function (array $a, array $b): int {
            $rataA = $a['rata_rata'] ?? -1;
            $rataB = $b['rata_rata'] ?? -1;
            return ($rataB <=> $rataA) ?: strcmp((string) $a['nama_siswa'], (string) $b['nama_siswa']);
        });

        $peringkat = [];
        $posisi = 0;
        $nilaiSebelum = null;
        $peringkatSebelum = 0;
        foreach ($urut as $index => $siswa) {
            if ($siswa['rata_rata'] === null) {
                $peringkat[(int) $siswa['id']] = null;
                continue;
            }
            if ($nilaiSebelum === null || $siswa['rata_rata'] !== $nilaiSebelum) {
                $posisi = $index + 1;
                $peringkatSebelum = $posisi;
                $nilaiSebelum = $siswa['rata_rata'];
            }
            $peringkat[(int) $siswa['id']] = $peringkatSebelum;
        }

        foreach ($hasil['daftar_siswa'] as $index => $siswa) {
            $hasil['daftar_siswa'][$index]['peringkat'] = $peringkat[(int) $siswa['id']] ?? null;
        }

        // ---------- 9. Ringkasan kelas ----------
        $totalHadir = 0;
        $totalPertemuan = 0;
        foreach ($hasil['rekap_presensi'] as $presensi) {
            $totalHadir += $presensi['total_h'];
            $totalPertemuan += $presensi['total_h'] + $presensi['total_i'] + $presensi['total_s'] + $presensi['total_a'];
        }

        $hasil['ringkasan']['jumlah_siswa'] = count($hasil['daftar_siswa']);
        $hasil['ringkasan']['jumlah_mapel'] = $jumlahMapel;
        $hasil['ringkasan']['rata_kelas'] = $siswaBernilai > 0 ? round($totalRata / $siswaBernilai, 2) : null;
        $hasil['ringkasan']['persen_hadir'] = $totalPertemuan > 0
            ? round(($totalHadir / $totalPertemuan) * 100, 1)
            : null;

        return $hasil;
    } catch (Throwable $e) {
        error_log('[LEDGER] fetch_ledger_rapor: ' . $e->getMessage());
        $hasil['error'] = (defined('APP_DEBUG') && APP_DEBUG)
            ? 'Data ledger gagal dimuat: ' . $e->getMessage()
            : 'Data ledger gagal dimuat. Hubungi administrator sekolah.';
        return $hasil;
    }
}
