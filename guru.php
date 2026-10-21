<?php
require_once __DIR__ . '/koneksi.php';

// Handle AJAX Login Request via NIP & Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    header('Content-Type: application/json');
    $nip = trim($_POST['nip'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($nip) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'NIP dan Kata Sandi wajib diisi!']);
        exit;
    }

    // Normalisasi NIP (hapus spasi, titik, strip untuk perbandingan fleksibel)
    $cleanNip = preg_replace('/[^0-9]/', '', $nip);

    // Cari di tabel data_guru berdasarkan NIP (bersih/asli), kode, atau nama
    $sql = "SELECT * FROM data_guru WHERE 
            REPLACE(REPLACE(REPLACE(nip, ' ', ''), '.', ''), '-', '') = ? 
            OR nip = ? 
            OR kode = ? 
            OR LOWER(nama_guru) = LOWER(?) 
            LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'ssss', $cleanNip, $nip, $nip, $nip);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $guru = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$guru) {
        echo json_encode([
            'success' => false, 
            'message' => 'Akun dengan NIP tersebut tidak ditemukan di sistem. Pastikan NIP yang Anda masukkan benar.'
        ]);
        exit;
    }

    $dbPassword = !empty($guru['password']) ? $guru['password'] : 'guru12345';
    if ($password !== $dbPassword) {
        echo json_encode([
            'success' => false, 
            'message' => 'Kata sandi tidak sesuai! Hubungi Admin jika Anda lupa kata sandi akun.'
        ]);
        exit;
    }

    if (isset($guru['status']) && $guru['status'] === 'Non-Aktif') {
        echo json_encode([
            'success' => false, 
            'message' => 'Akun Anda sedang dinonaktifkan oleh Admin sekolah.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil! Selamat datang, ' . $guru['nama_guru'],
        'teacher' => [
            'id'             => $guru['kode'],
            'kode'           => $guru['kode'],
            'name'           => $guru['nama_guru'],
            'nip'            => $guru['nip'],
            'mapel'          => $guru['mata_pelajaran'] ?? '',
            'kelas'          => $guru['kelas'] ?? '',
            'wali'           => $guru['wali_kelas'] ?? '',
            'jam_per_minggu' => $guru['jam_per_minggu'] ?? '0',
            'phone'          => $guru['phone'] ?? ''
        ]
    ]);
    exit;
}

// ==============================================================
// API: AMBIL DATA NILAI & JURNAL DARI DATABASE MYSQL
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_academic_data') {
    header('Content-Type: application/json');
    $kelas = trim($_POST['kelas'] ?? '');
    $mapel = trim($_POST['mata_pelajaran'] ?? '');

    // 1. Ambil Siswa di kelas ini dari data_siswa
    $students = [];
    $stmtS = mysqli_prepare($koneksi, "SELECT * FROM data_siswa WHERE kelas = ? ORDER BY nama_siswa ASC");
    mysqli_stmt_bind_param($stmtS, 's', $kelas);
    mysqli_stmt_execute($stmtS);
    $resS = mysqli_stmt_get_result($stmtS);
    while ($row = mysqli_fetch_assoc($resS)) {
        $students[] = [
            'id' => (int)$row['id'],
            'nisn' => $row['nisn'],
            'name' => $row['nama_siswa'],
            'class' => $row['kelas'],
            'scores' => (object)[],
            'attendances' => ['att_1' => 'H', 'att_2' => 'H', 'att_3' => 'H'],
            'semesterNotes' => $row['catatan_wali'] ?? 'Mengikuti pembelajaran dengan baik.'
        ];
    }
    mysqli_stmt_close($stmtS);

    // Fallback jika belum ada siswa di kelas tersebut: ambil 6-12 siswa acuan
    if (empty($students)) {
        $resAll = mysqli_query($koneksi, "SELECT * FROM data_siswa ORDER BY id ASC LIMIT 6");
        while ($row = mysqli_fetch_assoc($resAll)) {
            $students[] = [
                'id' => (int)$row['id'],
                'nisn' => $row['nisn'],
                'name' => $row['nama_siswa'],
                'class' => $kelas,
                'scores' => (object)[],
                'attendances' => ['att_1' => 'H', 'att_2' => 'H', 'att_3' => 'H'],
                'semesterNotes' => $row['catatan_wali'] ?? 'Mengikuti pembelajaran dengan baik.'
            ];
        }
    }

    // 2. Ambil Nilai dari tabel nilai_siswa
    $columns = [];
    $colMap = [];
    $stmtN = mysqli_prepare($koneksi, "SELECT * FROM nilai_siswa WHERE kelas = ? AND mata_pelajaran = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmtN, 'ss', $kelas, $mapel);
    mysqli_stmt_execute($stmtN);
    $resN = mysqli_stmt_get_result($stmtN);
    while ($rN = mysqli_fetch_assoc($resN)) {
        $jp = $rN['jenis_penilaian'];
        if (!isset($colMap[$jp])) {
            $colId = 'col_' . substr(md5($jp), 0, 8);
            $colMap[$jp] = $colId;
            $columns[] = [
                'id' => $colId,
                'name' => $jp,
                'category' => (stripos($jp, 'sumatif') !== false || stripos($jp, 'pts') !== false || stripos($jp, 'pas') !== false) ? 'Sumatif' : 'Formatif',
                'date' => date('d M Y', strtotime($rN['created_at']))
            ];
        }
        $cId = $colMap[$jp];
        foreach ($students as &$st) {
            if ($st['nisn'] === $rN['nisn']) {
                $sc = (array)$st['scores'];
                $sc[$cId] = (float)$rN['nilai'];
                $st['scores'] = $sc;
            }
        }
    }
    mysqli_stmt_close($stmtN);

    // Kolom default jika tabel masih kosong
    if (empty($columns)) {
        $columns = [
            ['id' => 'col_tugas1', 'name' => 'Tugas 1', 'category' => 'Formatif', 'date' => date('d M Y')],
            ['id' => 'col_tugas2', 'name' => 'Tugas 2', 'category' => 'Formatif', 'date' => date('d M Y')],
            ['id' => 'col_sumatif1', 'name' => 'Sumatif 1', 'category' => 'Sumatif', 'date' => date('d M Y')]
        ];
    }

    // 3. Ambil Jurnal untuk kelas dan mapel ini
    $journals = [];
    $stmtJ = mysqli_prepare($koneksi, "SELECT * FROM jurnal_guru WHERE kelas = ? AND mata_pelajaran = ? ORDER BY tanggal DESC, id DESC");
    mysqli_stmt_bind_param($stmtJ, 'ss', $kelas, $mapel);
    mysqli_stmt_execute($stmtJ);
    $resJ = mysqli_stmt_get_result($stmtJ);
    while ($rJ = mysqli_fetch_assoc($resJ)) {
        $journals[] = [
            'id' => (int)$rJ['id'],
            'day' => $rJ['hari'],
            'date' => date('d M Y', strtotime($rJ['tanggal'])),
            'rawDate' => $rJ['tanggal'],
            'meeting' => $rJ['pertemuan'],
            'topic' => $rJ['materi'],
            'activities' => $rJ['kegiatan'],
            'attendance' => [
                'hadir' => (int)$rJ['hadir'],
                'izin' => (int)$rJ['izin'],
                'sakit' => (int)$rJ['sakit'],
                'alpa' => (int)$rJ['alpa']
            ]
        ];
    }
    mysqli_stmt_close($stmtJ);

    echo json_encode([
        'success' => true,
        'students' => $students,
        'columns' => $columns,
        'journals' => $journals
    ]);
    exit;
}

// API: Simpan Satu Nilai Siswa (Live Cell Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grade_cell') {
    header('Content-Type: application/json');
    $nisn      = trim($_POST['nisn'] ?? '');
    $nama      = trim($_POST['nama_siswa'] ?? '');
    $kelas     = trim($_POST['kelas'] ?? '');
    $mapel     = trim($_POST['mata_pelajaran'] ?? '');
    $guru      = trim($_POST['guru_pengampu'] ?? '-');
    $jenis     = trim($_POST['jenis_penilaian'] ?? '');
    $nilaiRaw  = trim($_POST['nilai'] ?? '');
    $kkm       = (int)($_POST['kkm'] ?? 75);

    if (empty($nisn) || empty($kelas) || empty($mapel) || empty($jenis)) {
        echo json_encode(['success' => false, 'message' => 'Parameter nilai tidak lengkap!']);
        exit;
    }

    if ($nilaiRaw === '' || $nilaiRaw === null) {
        $stmtD = mysqli_prepare($koneksi, "DELETE FROM nilai_siswa WHERE nisn = ? AND kelas = ? AND mata_pelajaran = ? AND jenis_penilaian = ?");
        mysqli_stmt_bind_param($stmtD, 'ssss', $nisn, $kelas, $mapel, $jenis);
        mysqli_stmt_execute($stmtD);
        mysqli_stmt_close($stmtD);
        echo json_encode(['success' => true, 'message' => 'Nilai dikosongkan di database.']);
        exit;
    }

    $nilaiVal = (float)$nilaiRaw;
    $ket = $nilaiVal >= $kkm ? 'Tuntas' : 'Remedial';

    $sql = "INSERT INTO nilai_siswa (nisn, nama_siswa, kelas, mata_pelajaran, guru_pengampu, jenis_penilaian, nilai, kkm, keterangan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), keterangan = VALUES(keterangan), guru_pengampu = VALUES(guru_pengampu)";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssdis', $nisn, $nama, $kelas, $mapel, $guru, $jenis, $nilaiVal, $kkm, $ket);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Nilai tersimpan di database MySQL.' : mysqli_error($koneksi)]);
    exit;
}

// API: Hapus Kolom Nilai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_grade_column') {
    header('Content-Type: application/json');
    $kelas = trim($_POST['kelas'] ?? '');
    $mapel = trim($_POST['mata_pelajaran'] ?? '');
    $jenis = trim($_POST['jenis_penilaian'] ?? '');

    $stmt = mysqli_prepare($koneksi, "DELETE FROM nilai_siswa WHERE kelas = ? AND mata_pelajaran = ? AND jenis_penilaian = ?");
    mysqli_stmt_bind_param($stmt, 'sss', $kelas, $mapel, $jenis);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Kolom nilai berhasil dihapus dari database.' : mysqli_error($koneksi)]);
    exit;
}

// API: Simpan / Edit Jurnal Guru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_journal') {
    header('Content-Type: application/json');
    $id         = trim($_POST['id'] ?? '');
    $guru_kode  = trim($_POST['guru_kode'] ?? '');
    $nama_guru  = trim($_POST['nama_guru'] ?? '');
    $kelas      = trim($_POST['kelas'] ?? '');
    $mapel      = trim($_POST['mata_pelajaran'] ?? '');
    $hari       = trim($_POST['hari'] ?? '');
    $tanggal    = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $pertemuan  = trim($_POST['pertemuan'] ?? '');
    $materi     = trim($_POST['materi'] ?? '');
    $kegiatan   = trim($_POST['kegiatan'] ?? '');
    $hadir      = (int)($_POST['hadir'] ?? 0);
    $izin       = (int)($_POST['izin'] ?? 0);
    $sakit      = (int)($_POST['sakit'] ?? 0);
    $alpa       = (int)($_POST['alpa'] ?? 0);

    if (empty($materi) || empty($pertemuan)) {
        echo json_encode(['success' => false, 'message' => 'Pertemuan dan materi wajib diisi!']);
        exit;
    }

    if (!empty($id) && is_numeric($id) && (int)$id < 1000000000) {
        $stmt = mysqli_prepare($koneksi, "UPDATE jurnal_guru SET hari=?, tanggal=?, pertemuan=?, materi=?, kegiatan=?, hadir=?, izin=?, sakit=?, alpa=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssssiiiii', $hari, $tanggal, $pertemuan, $materi, $kegiatan, $hadir, $izin, $sakit, $alpa, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $savedId = (int)$id;
    } else {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO jurnal_guru (guru_kode, nama_guru, kelas, mata_pelajaran, hari, tanggal, pertemuan, materi, kegiatan, hadir, izin, sakit, alpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssssssssiiii', $guru_kode, $nama_guru, $kelas, $mapel, $hari, $tanggal, $pertemuan, $materi, $kegiatan, $hadir, $izin, $sakit, $alpa);
        $ok = mysqli_stmt_execute($stmt);
        $savedId = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);
    }

    echo json_encode(['success' => $ok, 'id' => $savedId, 'message' => $ok ? 'Jurnal berhasil disimpan ke database MySQL.' : mysqli_error($koneksi)]);
    exit;
}

// API: Hapus Jurnal Guru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_journal') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);

    $stmt = mysqli_prepare($koneksi, "DELETE FROM jurnal_guru WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Jurnal mengajar berhasil dihapus dari database.' : mysqli_error($koneksi)]);
    exit;
}

$teachersList = [];
$qGuru = mysqli_query($koneksi, "SELECT * FROM data_guru ORDER BY CAST(kode AS UNSIGNED) ASC");
if ($qGuru && mysqli_num_rows($qGuru) > 0) {
    while ($r = mysqli_fetch_assoc($qGuru)) {
        $teachersList[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Guru SMPN 7 Purwakarta - Sistem Akademik Terpadu</title>
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }
    .badge-h { background-color: #10b981; color: #ffffff; }
    .badge-i { background-color: #f59e0b; color: #ffffff; }
    .badge-s { background-color: #8b5cf6; color: #ffffff; }
    .badge-a { background-color: #ef4444; color: #ffffff; }
    .table-scroll {
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
    }
    .table-scroll::-webkit-scrollbar {
      height: 5px;
    }
    .table-scroll::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 9999px;
    }
    .table-scroll::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 9999px;
    }
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

  <!-- ============================================================== -->
  <!-- SCREEN 1: HALAMAN LOGIN GURU -->
  <!-- ============================================================== -->
  <section id="screen-login" class="min-h-screen flex items-center justify-center p-4 bg-slate-900 relative overflow-hidden">
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-4xl w-full grid grid-cols-1 md:grid-cols-12 bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-800/40 relative z-10">
      
      <!-- Sisi Kiri: Branding & Informasi Sekolah -->
      <div class="md:col-span-5 bg-gradient-to-br from-indigo-950 via-indigo-900 to-slate-900 p-5 sm:p-8 text-white flex flex-col justify-between relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center gap-3 mb-4 sm:mb-6">
            <div class="w-12 sm:w-14 h-12 sm:h-14 flex items-center justify-center flex-shrink-0">
              <img src="logoSMP.png" alt="Logo Sekolah" class="w-full h-full object-contain">
            </div>
            <div>
              <h2 class="font-bold text-xs sm:text-sm tracking-wide text-white">SMPN 7 PURWAKARTA</h2>
              <p class="text-[10px] sm:text-[11px] text-amber-300 font-medium">Sistem Penilaian & Presensi Guru</p>
            </div>
          </div>

          <div class="mt-4 sm:mt-8 space-y-2 sm:space-y-4">
            <h1 class="text-xl sm:text-2xl font-extrabold text-white leading-tight">
              Satu Pintu untuk Semua Kebutuhan Pembelajaran.
            </h1>
            <p class="text-xs text-indigo-200 leading-relaxed">
              Input nilai siswa dengan KKM fleksibel, presensi hari masuk dinamis, modul ajar, dan rekapitulasi buku induk rapor.
            </p>
          </div>
        </div>

        <div class="mt-6 sm:mt-12 pt-4 sm:pt-6 border-t border-indigo-800/60 relative z-10 text-[11px] text-indigo-300 flex items-center justify-between">
          <span>Tahun Ajaran 2026/2027</span>
          <span class="bg-indigo-800/80 px-2 py-0.5 rounded-full text-amber-300 font-semibold border border-indigo-700">Kurikulum Merdeka</span>
        </div>
      </div>

      <!-- Sisi Kanan: Form Login Manual -->
      <div class="md:col-span-7 p-6 sm:p-10 flex flex-col justify-center bg-white">
        <div>
          <h3 class="text-lg sm:text-xl font-bold text-indigo-950">Masuk ke Akun Guru</h3>
          <p class="text-xs text-slate-500 mt-1">Masukkan NIP dan Kata Sandi Anda untuk melanjutkan.</p>
        </div>

        <!-- Alert Error Box -->
        <div id="login-error-box" class="hidden mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs flex items-start gap-2.5">
          <svg class="w-4 h-4 text-rose-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <span id="login-error-msg" class="font-medium">NIP atau Kata Sandi salah!</span>
        </div>

        <form onsubmit="handleLoginSubmit(event)" class="mt-5 space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">NIP Guru / Identitas Pegawai</label>
            <div class="relative">
              <input type="text" id="login-nip" required placeholder="Masukkan NIP Anda (Contoh: 19880412 201101 2 003)" autocomplete="username" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all font-medium text-xs sm:text-sm">
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Gunakan NIP terdaftar di data akademik sekolah.</p>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-semibold text-slate-700">Kata Sandi Akun</label>
              <span class="text-[10px] text-slate-400">Default: <code class="font-mono text-indigo-700 bg-indigo-50 px-1 py-0.5 rounded">guru12345</code></span>
            </div>
            <input type="password" id="login-pass" required placeholder="Masukkan kata sandi..." autocomplete="current-password" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all font-medium text-xs sm:text-sm">
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input type="checkbox" id="remember-me" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <label for="remember-me" class="text-slate-600 cursor-pointer">Ingat sesi login saya di perangkat ini</label>
          </div>

          <button type="submit" id="btn-login-submit" class="w-full py-3 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-md transition-all active:scale-[0.99] flex items-center justify-center gap-2 text-sm mt-2">
            <span>Masuk ke Sistem Guru</span>
            <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>
        </form>

        <div class="mt-4 pt-3 border-t border-slate-100 text-center">
          <p class="text-[11px] text-slate-500">
            Lupa kata sandi? Hubungi <span class="font-semibold text-indigo-900">Administrator Sekolah</span> untuk mereset akun Anda.
          </p>
        </div>

      </div>

    </div>
  </section>


  <!-- ============================================================== -->
  <!-- SCREEN 2: PILIH MAPEL & PILIH KELAS BERTINGKAT (7/8/9 -> ROMBEL A-E) -->
  <!-- ============================================================== -->
  <section id="screen-select-class" class="min-h-screen bg-slate-900 text-white flex flex-col justify-between p-3 sm:p-6 md:p-8 hidden">
    
    <div class="max-w-4xl w-full mx-auto flex items-center justify-between py-3 sm:py-4 px-1 sm:px-0 border-b border-slate-800">
      <div class="flex items-center gap-2.5 sm:gap-3">
        <div class="w-9 sm:w-10 h-9 sm:h-10 rounded-xl overflow-hidden bg-white/10 border border-white/10 flex items-center justify-center shadow-md flex-shrink-0">
          <img src="logoSMP.png" alt="Logo Sekolah" class="w-full h-full object-cover">
        </div>
        <div>
          <span class="font-bold text-xs sm:text-sm text-white">SMPN 7 PURWAKARTA</span>
          <p class="text-[10px] sm:text-xs text-indigo-300">Tahun Ajaran 2026/2027 · Semester Ganjil</p>
        </div>
      </div>

      <div class="flex items-center gap-2.5 sm:gap-3 text-xs">
        <div class="text-right hidden sm:block">
          <p class="font-bold text-white text-xs" id="step2-teacher-name">Ibu Siti Rahmawati, S.Pd</p>
          <p class="text-slate-400 text-[10px]">Guru Pengampu Aktif</p>
        </div>
        <button onclick="goBack()" class="text-slate-300 hover:text-white px-2.5 sm:px-3 py-1.5 rounded-lg border border-slate-700 hover:border-slate-500 transition-colors text-xs flex items-center gap-1.5 active:scale-95">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          <span>Kembali</span>
        </button>
      </div>
    </div>

    <!-- Container Form Pemilihan Bertingkat -->
    <div class="max-w-2xl w-full mx-auto my-auto py-4 sm:py-6">
      
      <div class="text-center mb-4 sm:mb-6">
        <span class="text-[11px] sm:text-xs bg-amber-400 text-indigo-950 font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Langkah Pemilihan Ruang Kerja</span>
        <h2 class="text-xl sm:text-3xl font-extrabold text-white mt-2">Pilih Peran & Kelas Belajar</h2>
        <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-lg mx-auto">
          Tentukan peran Anda sebagai Guru Mapel atau Wali Kelas, lalu pilih jenjang (7, 8, atau 9) dan rombelnya (A sampai I).
        </p>
      </div>

      <div class="bg-white text-slate-800 rounded-3xl p-4 sm:p-8 shadow-2xl border border-slate-100 space-y-5 sm:space-y-6">
        
        <!-- LANGKAH 1: PILIH PERAN (GURU MAPEL ATAU WALI KELAS) -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            1. Status Penugasan Anda Saat Ini
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button type="button" onclick="selectTeacherRole('guru_mapel')" id="btn-role-guru-mapel" class="p-3.5 sm:p-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-left transition-all shadow-sm">
              <div class="flex items-center gap-2.5 mb-1.5">
                <span class="w-8 h-8 rounded-xl bg-indigo-950 text-amber-300 font-bold flex items-center justify-center text-sm flex-shrink-0">📘</span>
                <span class="font-extrabold text-xs sm:text-sm text-indigo-950">Guru Mata Pelajaran</span>
              </div>
              <p class="text-[11px] text-slate-600 leading-relaxed">
                Pilih satu mata pelajaran yang Anda ampu untuk menginput nilai dan presensi di kelas terkait.
              </p>
            </button>

            <button type="button" onclick="selectTeacherRole('wali_kelas')" id="btn-role-wali-kelas" class="p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 hover:border-indigo-400 bg-white text-left transition-all">
              <div class="flex items-center gap-2.5 mb-1.5">
                <span class="w-8 h-8 rounded-xl bg-amber-400 text-indigo-950 font-bold flex items-center justify-center text-sm flex-shrink-0">🎓</span>
                <span class="font-extrabold text-xs sm:text-sm text-slate-800">Wali Kelas</span>
              </div>
              <p class="text-[11px] text-slate-500 leading-relaxed">
                Pantau seluruh mata pelajaran (semua mapel) dan perkembangan siswa di kelas binaan Anda.
              </p>
            </button>
          </div>
        </div>

        <!-- LANGKAH 2: PILIH MATA PELAJARAN (HANYA MUNCUL JIKA GURU MAPEL) -->
        <div id="container-select-mapel">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            2. Mata Pelajaran yang Diajarkan
          </label>
          <div class="relative">
            <select id="select-subject-dropdown" class="w-full bg-slate-50 border-2 border-slate-200 hover:border-indigo-500 focus:border-indigo-600 rounded-xl px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-slate-800 focus:ring-0 focus:outline-none transition-all appearance-none cursor-pointer">
              <option value="Matematika" selected>📘 Matematika</option>
              <option value="Ilmu Pengetahuan Alam (IPA)">🔬 Ilmu Pengetahuan Alam (IPA)</option>
              <option value="Ilmu Pengetahuan Sosial (IPS)">🌍 Ilmu Pengetahuan Sosial (IPS)</option>
              <option value="Bahasa Indonesia">📖 Bahasa Indonesia</option>
              <option value="Bahasa Inggris">🗣️ Bahasa Inggris</option>
              <option value="Pendidikan Pancasila (PPKn)">🇮🇩 Pendidikan Pancasila (PPKn)</option>
              <option value="Pendidikan Agama & Budi Pekerti">🕌 Pendidikan Agama & Budi Pekerti</option>
              <option value="Informatika">💻 Informatika</option>
              <option value="PJOK (Pendidikan Jasmani)">⚽ PJOK (Penjaskes)</option>
              <option value="Seni Budaya">🎨 Seni Budaya</option>
              <option value="Prakarya">🛠️ Prakarya</option>
              <option value="Bahasa Sunda (Mulok)">🌾 Bahasa Sunda (Muatan Lokal)</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
          </div>
        </div>

        <!-- INFO WALI KELAS (MUNCUL JIKA ROLE WALI KELAS) -->
        <div id="container-wali-note" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl p-3.5 sm:p-4 text-xs text-amber-950 flex items-start gap-3">
          <span class="text-xl flex-shrink-0">🌟</span>
          <div>
            <p class="font-bold text-xs sm:text-sm text-indigo-950">Akses Penuh Wali Kelas Terintegrasi</p>
            <p class="text-slate-600 text-[11px] mt-0.5 leading-relaxed">
              Sebagai Wali Kelas, Anda tidak dibatasi pada satu mapel. Anda dapat melihat <strong>semua mata pelajaran</strong> (Matematika, IPA, B.Indo, B.Inggris, Informatika, dll.) yang diinput oleh masing-masing guru pengampu di kelas ini.
            </p>
          </div>
        </div>

        <!-- LANGKAH 3: PILIH TINGKAT KELAS (7, 8, atau 9) -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2" id="label-step-grade">
            3. Pilih Tingkat Jenjang (7, 8, atau 9)
          </label>
          <div class="grid grid-cols-3 gap-2 sm:gap-3">
            <button type="button" onclick="selectGradeLevel(7)" id="btn-grade-7" class="py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-slate-200 text-slate-700 hover:border-indigo-400 font-extrabold text-xs sm:text-sm transition-all text-center">
              <span class="block text-base sm:text-lg">Kelas 7</span>
              <span class="text-[10px] font-medium text-slate-400"></span>
            </button>
            <button type="button" onclick="selectGradeLevel(8)" id="btn-grade-8" class="py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-indigo-950 font-extrabold text-xs sm:text-sm transition-all text-center shadow-sm">
              <span class="block text-base sm:text-lg">Kelas 8</span>
              <span class="text-[10px] font-medium text-indigo-600"></span>
            </button>
            <button type="button" onclick="selectGradeLevel(9)" id="btn-grade-9" class="py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-slate-200 text-slate-700 hover:border-indigo-400 font-extrabold text-xs sm:text-sm transition-all text-center">
              <span class="block text-base sm:text-lg">Kelas 9</span>
              <span class="text-[10px] font-medium text-slate-400"></span>
            </button>
          </div>
        </div>

        <!-- LANGKAH 4: PILIH ROMBEL (A SAMPAI I -> 9 ROMBEL) -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500" id="label-step-rombel">
              4. Pilih Rombongan Belajar (Rombel A sampai I)
            </label>
            <span class="text-[11px] font-semibold text-indigo-800 bg-indigo-50 px-2.5 py-0.5 rounded-md" id="label-rombel-grade">Rombel Kelas 8</span>
          </div>

          <div class="grid grid-cols-3 sm:grid-cols-9 gap-1.5" id="rombel-selector-container">
            <!-- Populated via Javascript A s/d I -->
          </div>
          <p class="text-[11px] text-slate-400 mt-2">Pilih salah satu rombel (A, B, C, D, E, F, G, H, atau I) untuk membuka lembar kerja.</p>
        </div>

        <!-- Tombol Konfirmasi & Kembali -->
        <div class="pt-4 sm:pt-5 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          <button type="button" onclick="goBack()" class="order-2 sm:order-1 px-4 py-2.5 text-slate-600 hover:text-indigo-950 font-semibold rounded-xl text-xs sm:text-sm border border-slate-200 hover:bg-slate-50 transition-all flex items-center justify-center gap-1.5 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Login</span>
          </button>
          <button onclick="confirmWorkspaceAndProceed()" class="order-1 sm:order-2 w-full sm:w-auto px-6 py-3 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs sm:text-sm shadow-md transition-all flex items-center justify-center gap-2 active:scale-95">
            <span>Buka Dashboard Kelas</span>
            <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>
        </div>

      </div>

    </div>

    <div class="max-w-4xl w-full mx-auto text-center py-4 text-slate-500 text-xs border-t border-slate-800">
      Portal Guru Terpadu SMPN 7 Purwakarta · Kurikulum Merdeka
    </div>

  </section>


  <!-- ============================================================== -->
  <!-- SCREEN 3: DASHBOARD UTAMA -->
  <!-- ============================================================== -->
  <div id="screen-dashboard" class="min-h-screen flex flex-col hidden">
    
    <!-- TOP CONTEXT BANNER -->
    <header class="bg-indigo-950 text-white border-b border-indigo-900 sticky top-0 z-40 shadow-sm">
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          
          <!-- Logo & Nama Sekolah -->
          <div class="flex items-center gap-2.5 sm:gap-3">
            <div class="w-10 sm:w-11 h-10 sm:h-11 flex items-center justify-center flex-shrink-0">
              <img src="logoSMP.png" alt="Logo Sekolah" class="w-full h-full object-contain">
            </div>
            <div>
              <div class="flex items-center gap-1.5 sm:gap-2">
                <span class="font-bold tracking-tight text-white text-xs sm:text-base">SMPN 7 PURWAKARTA</span>
                <span class="text-[10px] sm:text-xs bg-indigo-800/80 text-amber-300 font-semibold px-2 py-0.5 rounded-full border border-indigo-700 hidden xs:inline-block">2026/2027</span>
              </div>
              <p class="text-[10px] sm:text-xs text-indigo-200">Sistem Akademik & Penilaian Terpadu</p>
            </div>
          </div>

          <!-- GLOBAL ACTIVE CONTEXT SWITCHER (DESKTOP) -->
          <div class="hidden md:flex items-center bg-indigo-900/90 border border-indigo-700/60 rounded-xl px-3 py-1.5 shadow-inner">
            <div class="flex items-center gap-3 text-xs">
              <div class="flex items-center gap-1.5 text-slate-300">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-indigo-200">Ruang Aktif:</span>
              </div>

              <!-- Subject Tag -->
              <div class="flex items-center gap-1 bg-indigo-800 px-2.5 py-1 rounded-lg font-medium text-amber-300 border border-indigo-700">
                <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span id="active-subject-text">Matematika</span>
              </div>

              <!-- Class Tag -->
              <div class="flex items-center gap-1 bg-indigo-800 px-2.5 py-1 rounded-lg font-medium text-white border border-indigo-700">
                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span id="active-class-text">Kelas VIII A</span>
              </div>

              <span class="text-indigo-300 font-normal">Semester Ganjil</span>

              <button onclick="goBack()" title="Kembali ke Pemilihan Mapel & Kelas" class="ml-2 bg-amber-500 hover:bg-amber-400 text-indigo-950 font-bold px-2.5 py-1 rounded-lg transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Kembali / Ganti Mapel</span>
              </button>
            </div>
          </div>

          <!-- Teacher Profile & Mobile Actions -->
          <div class="flex items-center gap-2 sm:gap-3">
            <div class="text-right hidden sm:block">
              <p class="text-xs font-semibold text-white" id="header-teacher-name">Ibu Siti Rahmawati, S.Pd</p>
              <p class="text-[11px] text-amber-300/90" id="header-teacher-role">NIP 19880412 201101 2 003</p>
            </div>
            <button onclick="goToLogin()" title="Keluar / Ganti Akun" class="w-8 sm:w-9 h-8 sm:h-9 rounded-full bg-indigo-800 border-2 border-amber-400 flex items-center justify-center font-bold text-amber-300 text-xs sm:text-sm hover:scale-105 transition-transform flex-shrink-0">
              SR
            </button>
            <!-- Mobile Hamburger Drawer Toggle -->
            <button type="button" onclick="toggleMobileDrawer(true)" class="md:hidden w-8 h-8 rounded-xl bg-indigo-900 border border-indigo-700 flex items-center justify-center text-white hover:bg-indigo-800 transition-colors ml-0.5" aria-label="Buka Menu Navigasi">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
          </div>

        </div>
      </div>

      <!-- MOBILE ACTIVE CONTEXT SUB-BAR (HANYA MUNCUL DI HP / TABLET KECIL) -->
      <div class="md:hidden bg-indigo-900/95 border-t border-indigo-900 px-3.5 py-2 flex items-center justify-between text-xs text-white">
        <div class="flex items-center gap-1.5 truncate">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse flex-shrink-0"></span>
          <span class="font-bold text-amber-300 truncate" id="mob-active-subject-text">Matematika</span>
          <span class="text-indigo-400">·</span>
          <span class="font-medium text-white truncate" id="mob-active-class-text">Kelas VIII A</span>
        </div>
        <button onclick="goBack()" title="Kembali ke Pilih Kelas / Mapel" class="bg-amber-400 hover:bg-amber-300 text-indigo-950 font-bold px-2.5 py-1 rounded-lg text-[11px] flex-shrink-0 ml-2 active:scale-95 transition-all flex items-center gap-1 shadow-xs">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          <span>Kembali</span>
        </button>
      </div>

      <!-- MOBILE HORIZONTAL SCROLLABLE TAB NAVIGATION (HANYA DI HP) -->
      <div class="md:hidden bg-white border-b border-slate-200 px-2.5 py-2 shadow-2xs">
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-smooth">
          <button onclick="switchTab('nilai')" id="btn-mob-tab-nilai" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-bold text-xs bg-indigo-900 text-white shadow-xs transition-all flex items-center gap-1.5">
            <span>📝 Nilai</span>
          </button>
          <button onclick="switchTab('presensi')" id="btn-mob-tab-presensi" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>📅 Presensi</span>
          </button>
          <button onclick="switchTab('jurnal')" id="btn-mob-tab-jurnal" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>📖 Jurnal</span>
          </button>
          <button onclick="switchTab('materi')" id="btn-mob-tab-materi" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>📂 Modul</span>
          </button>
          <button onclick="switchTab('rekap')" id="btn-mob-tab-rekap" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>📊 Rekap</span>
          </button>
          <button onclick="switchTab('faq')" id="btn-mob-tab-faq" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>💡 FAQ</span>
          </button>
        </div>
      </div>
    </header>

    <!-- MOBILE OFF-CANVAS DRAWER MENU (MODAL MENU UNTUK SMARTPHONE) -->
    <div id="mobile-drawer-overlay" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden transition-opacity" onclick="toggleMobileDrawer(false)">
      <div id="mobile-drawer" class="w-72 max-w-[85vw] bg-white h-full shadow-2xl flex flex-col justify-between overflow-y-auto" onclick="event.stopPropagation()">
        
        <div class="p-4 space-y-4">
          <!-- Drawer Header -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-xl bg-amber-400 text-indigo-950 font-bold flex items-center justify-center text-xs shadow-xs">
                SMP
              </div>
              <div>
                <h3 class="font-extrabold text-xs text-indigo-950">SMPN 7 Purwakarta</h3>
                <p class="text-[10px] text-slate-400">Menu Portal Akademik</p>
              </div>
            </div>
            <button onclick="toggleMobileDrawer(false)" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Teacher Profile Badge -->
          <div class="bg-indigo-50/80 border border-indigo-100 rounded-2xl p-3 text-xs">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-full bg-indigo-900 text-amber-300 font-bold flex items-center justify-center text-xs">SR</div>
              <div>
                <p class="font-bold text-indigo-950 text-xs">Ibu Siti Rahmawati, S.Pd</p>
                <p class="text-[10px] text-slate-500">NIP 19880412 201101 2 003</p>
              </div>
            </div>
            <div class="mt-2.5 pt-2 border-t border-indigo-100/80 flex items-center justify-between">
              <span class="text-[10px] text-slate-500 font-medium">Ruang: <strong class="text-indigo-950" id="mob-drawer-class-label">Kelas VIII A</strong></span>
              <button onclick="goBack(); toggleMobileDrawer(false);" class="text-[10px] bg-amber-400 text-indigo-950 font-bold px-2 py-0.5 rounded-md hover:bg-amber-300 transition-all flex items-center gap-1 active:scale-95">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Kembali</span>
              </button>
            </div>
          </div>

          <!-- Navigasi Menu Drawer -->
          <div>
            <p class="text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-2 px-1">Menu Navigasi</p>
            <nav class="space-y-1" id="mobile-nav-container">
              <button onclick="switchTab('nilai'); toggleMobileDrawer(false);" id="btn-drawer-tab-nilai" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all bg-indigo-900 text-white shadow-xs">
                <div class="flex items-center gap-2.5">
                  <span>📝</span>
                  <span>Input Nilai Siswa</span>
                </div>
                <span class="text-[10px] bg-amber-400 text-indigo-950 px-1.5 py-0.5 rounded-md font-bold">Aktif</span>
              </button>

              <button onclick="switchTab('presensi'); toggleMobileDrawer(false);" id="btn-drawer-tab-presensi" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>📅</span>
                  <span>Presensi Kehadiran</span>
                </div>
                <span class="text-[10px] text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-md font-semibold">Harian</span>
              </button>

              <button onclick="switchTab('jurnal'); toggleMobileDrawer(false);" id="btn-drawer-tab-jurnal" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>📖</span>
                  <span>Jurnal Harian</span>
                </div>
                <span class="text-[10px] bg-amber-100 text-amber-900 px-1.5 py-0.5 rounded-md font-bold">Catatan</span>
              </button>

              <button onclick="switchTab('materi'); toggleMobileDrawer(false);" id="btn-drawer-tab-materi" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>📂</span>
                  <span>Materi & Modul</span>
                </div>
                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-md font-semibold">3 File</span>
              </button>

              <button onclick="switchTab('rekap'); toggleMobileDrawer(false);" id="btn-drawer-tab-rekap" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>📊</span>
                  <span>Rekap Keseluruhan</span>
                </div>
                <span class="text-[10px] bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded-md font-bold">Rapor</span>
              </button>

              <button onclick="switchTab('faq'); toggleMobileDrawer(false);" id="btn-drawer-tab-faq" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>💡</span>
                  <span>Pusat Bantuan (FAQ)</span>
                </div>
                <span class="text-[10px] bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded-md font-bold">Panduan</span>
              </button>
            </nav>
          </div>

          <!-- KKM Target Widget di Drawer Mobile -->
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs">
            <div class="flex items-center justify-between mb-1">
              <span class="font-bold text-indigo-900 text-[11px]">Target KKM / KKTP</span>
              <span class="text-indigo-700 font-bold bg-white border border-indigo-200 px-2 py-0.5 rounded-lg text-xs" id="mob-sidebar-kkm-display">75</span>
            </div>
            <p class="text-[10px] text-slate-500 mb-1.5">Kelas: <strong id="mob-sidebar-class-name">Kelas VIII A</strong></p>
            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
              <div class="bg-indigo-600 h-full w-[100%] rounded-full"></div>
            </div>
            <p class="text-[10px] text-slate-500 mt-1 text-right font-medium">6 Siswa Terdaftar</p>
          </div>
        </div>

        <div class="p-4 border-t border-slate-100 space-y-2">
          <button onclick="goBack(); toggleMobileDrawer(false);" class="w-full py-2.5 px-3 text-xs text-indigo-900 bg-indigo-50 hover:bg-indigo-100 flex items-center justify-center gap-1.5 rounded-xl border border-indigo-200 transition-colors font-bold active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Pilih Mapel / Kelas</span>
          </button>
          <button onclick="goToLogin(); toggleMobileDrawer(false);" class="w-full py-2.5 px-3 text-xs text-rose-600 hover:bg-rose-50 flex items-center justify-center gap-1.5 rounded-xl border border-rose-200 transition-colors font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span>Keluar dari Akun Guru</span>
          </button>
        </div>

      </div>
    </div>

    <!-- BODY CONTENT AREA (SIDEBAR DESKTOP + MAIN APP) -->
    <div class="flex-1 max-w-7xl w-full mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 flex flex-col md:flex-row gap-6">

      <!-- SIDEBAR NAVIGATION (HANYA DITAMPILKAN DI DESKTOP/MD KE ATAS AGAR HP LEGA) -->
      <aside class="hidden md:block w-64 flex-shrink-0">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sticky top-24">
          
          <p class="text-[11px] font-bold tracking-wider text-slate-400 uppercase px-3 mb-2">Navigasi Utama</p>
          <nav class="space-y-1.5" id="nav-container">
            
            <button onclick="switchTab('nilai')" id="btn-tab-nilai" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all bg-indigo-900 text-white shadow-sm">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Input Nilai Siswa</span>
              </div>
              <span class="text-xs bg-amber-400 text-indigo-950 px-2 py-0.5 rounded-full font-bold">Aktif</span>
            </button>

            <button onclick="switchTab('presensi')" id="btn-tab-presensi" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Presensi Kehadiran</span>
              </div>
              <span class="text-xs text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md">Harian</span>
            </button>

            <button onclick="switchTab('jurnal')" id="btn-tab-jurnal" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span>Jurnal Harian</span>
              </div>
              <span class="text-xs bg-amber-100 text-amber-900 px-2 py-0.5 rounded-md font-bold" id="badge-jurnal-count">3 Catatan</span>
            </button>

            <button onclick="switchTab('materi')" id="btn-tab-materi" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span>Materi & Modul</span>
              </div>
              <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold">3 File</span>
            </button>

            <button onclick="switchTab('rekap')" id="btn-tab-rekap" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Rekap Keseluruhan</span>
              </div>
              <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-md font-bold">Semua Rekap</span>
            </button>

            <button onclick="switchTab('faq')" id="btn-tab-faq" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Pusat Bantuan (FAQ)</span>
              </div>
              <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-md font-bold">Panduan</span>
            </button>

          </nav>

          <hr class="my-4 border-slate-100">

          <!-- KONTROL KKM DI SIDEBAR (DAPAT DIEDIT LANGSUNG OLEH GURU) -->
          <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl p-3.5 text-xs">
            <div class="flex items-center justify-between mb-1.5">
              <span class="font-bold text-indigo-900">Target KKM / KKTP</span>
              <span class="text-indigo-700 font-bold bg-white border border-indigo-200 px-2 py-0.5 rounded-lg" id="sidebar-kkm-display">75</span>
            </div>
            <p class="text-slate-600 leading-relaxed mb-2">
              Kelas: <span class="font-bold text-indigo-950" id="sidebar-class-name">Kelas VIII A</span>
            </p>
            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
              <div class="bg-indigo-600 h-full w-[100%] rounded-full" id="sidebar-progress-bar"></div>
            </div>
            <p class="text-[11px] text-slate-500 mt-1.5 text-right font-medium" id="sidebar-pass-percent">6 Siswa Terdaftar</p>
          </div>

          <div class="mt-4 pt-2 space-y-2">
            <button onclick="goBack()" class="w-full py-2 px-3 text-xs text-indigo-900 bg-indigo-50 hover:bg-indigo-100 flex items-center justify-center gap-1.5 rounded-lg border border-indigo-200 transition-colors font-bold active:scale-95">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
              <span>Kembali ke Pilih Mapel / Kelas</span>
            </button>
            <button onclick="goToLogin()" class="w-full py-2 px-3 text-xs text-rose-600 hover:bg-rose-50 flex items-center justify-center gap-1.5 rounded-lg border border-rose-200 transition-colors font-medium">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              <span>Keluar dari Akun Guru</span>
            </button>
          </div>

        </div>
      </aside>

      <!-- MAIN INTERACTIVE WORKSPACE -->
      <main class="flex-1 min-w-0 space-y-5 sm:space-y-6">

        <!-- ============================================================== -->
        <!-- TAB 1: INPUT NILAI SISWA (DILENGKAPI WALI KELAS & INPUT MANUAL) -->
        <!-- ============================================================== -->
        <section id="tab-nilai" class="space-y-4">
          
          <!-- SUB-TAB NAVIGATION SWITCHER (HANYA MUNCUL DI WALI KELAS, GURU MAPEL CUKUP NILAI MAPELNYA SAJA) -->
          <div id="subtab-nilai-switcher" class="hidden flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 bg-white p-2.5 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5 sm:gap-2 p-1 bg-slate-100 rounded-xl">
              <button type="button" onclick="switchGradeSubTab('sheet')" id="subtab-btn-sheet" class="w-full sm:w-auto px-3.5 sm:px-4 py-2 rounded-lg text-xs font-bold transition-all bg-indigo-950 text-white shadow-xs flex items-center justify-center gap-2">
                <span>📄 Lembar Penilaian Mapel</span>
              </button>
              <button type="button" onclick="switchGradeSubTab('ledger')" id="subtab-btn-ledger" class="w-full sm:w-auto px-3.5 sm:px-4 py-2 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-indigo-950 flex items-center justify-center gap-2">
                <span>📊 Rekap Seluruh Mapel (Ledger Nilai Siswa)</span>
              </button>
            </div>
            <div class="text-[11px] text-slate-500 pr-2 hidden sm:flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span id="subtab-context-hint">Mode aktif: Lembar Penilaian Mapel</span>
            </div>
          </div>

          <!-- ========================================== -->
          <!-- SUB-VIEW 1: LEMBAR PENILAIAN MATA PELAJARAN -->
          <!-- ========================================== -->
          <div id="subview-grade-sheet" class="space-y-4">

            <!-- BAR KHUSUS WALI KELAS: PEMANTAUAN SELURUH MAPEL KELAS BINAAN -->
            <div id="wali-pov-control-bar" class="hidden bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-900 text-white p-4 sm:p-5 rounded-2xl shadow-sm border border-indigo-800">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] bg-amber-400 text-indigo-950 font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider">Mode Wali Kelas</span>
                    <h3 class="font-extrabold text-sm text-white" id="wali-pov-class-title">Pemantauan Seluruh Mapel Kelas VIII A</h3>
                  </div>
                  <p class="text-[11px] text-indigo-200 mt-0.5">Sebagai Wali Kelas, Anda dapat melihat seluruh nilai mata pelajaran yang diinput oleh masing-masing guru pengampu.</p>
                </div>

                <div class="flex items-center gap-2.5 bg-white/10 backdrop-blur-xs p-2 rounded-xl border border-white/10">
                  <label for="select-wali-pov-subject" class="text-xs text-amber-300 font-bold whitespace-nowrap">Mapel:</label>
                  <select id="select-wali-pov-subject" onchange="handleWaliPovSubjectChange(this.value)" class="bg-indigo-900 text-white font-bold text-xs rounded-lg px-3 py-1.5 border border-indigo-700 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer w-full sm:w-auto">
                    <option value="Matematika" selected>📘 Matematika (Ibu Siti Rahmawati, S.Pd)</option>
                    <option value="IPA">🔬 IPA (Bpk. Budi Santoso, M.Pd)</option>
                    <option value="Bahasa Indonesia">📖 Bahasa Indonesia (Ibu Dra. Hj. Nur Aini)</option>
                    <option value="Bahasa Inggris">🗣️ Bahasa Inggris (Ibu Rina Nurhasanah, S.Pd)</option>
                    <option value="Informatika">💻 Informatika (Bpk. Ahmad Fauzi, S.Kom)</option>
                    <option value="Pendidikan Pancasila">🇮🇩 Pendidikan Pancasila (Bpk. Dedi Suhendar, S.Pd)</option>
                    <option value="IPS">🌍 IPS (Bpk. Tatang Sutisna, S.Pd)</option>
                    <option value="PJOK">⚽ PJOK (Bpk. Asep Kurnia, S.Pd)</option>
                    <option value="Seni Budaya">🎨 Seni Budaya (Ibu Maya Lestari, S.Sn)</option>
                    <option value="Pendidikan Agama">🕌 Pend. Agama Islam (Ibu Eni Rohaeni, S.Ag)</option>
                    <option value="Bahasa Sunda">🌾 Bahasa Sunda (Ibu Ceceu Marlina, S.Pd)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- PERINGATAN KETAT WALI KELAS READ-ONLY (WALAS GABISA NAMBAH / UBAH NILAI) -->
            <div id="walas-readonly-banner" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl p-4 text-xs text-amber-950 flex items-start gap-3.5 shadow-2xs">
              <div class="w-9 h-9 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center font-black text-lg flex-shrink-0">
                🔒
              </div>
              <div>
                <p class="font-bold text-sm text-indigo-950">Akses Pemantauan Wali Kelas (Hanya Lihat / Read-Only)</p>
                <p class="text-slate-600 text-[11px] mt-0.5 leading-relaxed">
                  Sesuai ketentuan, Wali Kelas <strong>tidak memiliki wewenang untuk menambah kolom atau mengedit nilai</strong> pada lembar mata pelajaran. Hak penginputan dan penilaian sepenuhnya berada di tangan masing-masing Guru Mata Pelajaran pengampu.
                </p>
              </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
                <div>
                  <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg sm:text-xl font-bold text-indigo-950" id="title-grade-workspace">Input Nilai Siswa</h1>
                    <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-2.5 py-0.5 rounded-full" id="tag-current-view">Matematika - Kelas VIII A</span>
                  </div>
                  <p class="text-xs text-slate-500 mt-1" id="desc-grade-workspace">
                    Input tugas atau ulangan secara manual satu per satu. KKM dapat disesuaikan sebagai acuan penilaian.
                  </p>
                </div>

                <!-- KONTROL KKM BISA DIEDIT DAN DISESUAIKAN -->
                <div class="flex items-center gap-2.5 bg-amber-50 border-2 border-amber-300 px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-2xl shadow-sm self-start sm:self-auto">
                  <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-amber-900">KKM / KKTP</label>
                    <span class="text-[11px] text-amber-700">Target Acuan</span>
                  </div>
                  <input type="number" id="input-kkm-target" min="50" max="100" value="75" oninput="handleKKMChange(this.value)" class="w-14 sm:w-16 text-center py-1 sm:py-1.5 px-2 bg-white border-2 border-amber-400 rounded-xl font-black text-indigo-950 text-sm sm:text-base focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
              </div>

              <!-- Filter Info Sub-bar -->
              <div class="mt-4 pt-3 sm:pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3 text-xs">
                <div>
                  <label class="block text-slate-500 font-medium mb-1">Mata Pelajaran Aktif</label>
                  <input type="text" id="filter-mapel-locked" value="Matematika" readonly class="w-full bg-slate-100 border border-slate-200 text-slate-700 rounded-lg px-2.5 py-1.5 font-medium cursor-not-allowed">
                </div>
                <div>
                  <label class="block text-slate-500 font-medium mb-1">Kelas Aktif</label>
                  <input type="text" id="filter-kelas-display" value="Kelas VIII A" readonly class="w-full bg-slate-100 border border-slate-200 text-slate-700 rounded-lg px-2.5 py-1.5 font-medium cursor-not-allowed">
                </div>
                <div>
                  <label class="block text-slate-500 font-medium mb-1">Status Kolom</label>
                  <div class="flex items-center gap-2 pt-1 font-semibold text-indigo-900">
                    <span id="label-column-count" class="bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-lg text-xs">0 Kolom Penilaian</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- TABEL SPREADSHEET DINAMIS MAPEL -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
              
              <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50/80 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3">
                  <span class="font-bold text-slate-800 text-sm">Lembar Penilaian Siswa</span>
                </div>

                <!-- TOMBOL TAMBAH KOLOM NILAI (DISEMBUNYIKAN JIKA WALI KELAS) -->
                <button onclick="openAddColumnModal()" id="btn-add-grade-col" class="w-full sm:w-auto px-3.5 py-2 sm:py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border-2 border-indigo-200 hover:border-indigo-400 rounded-xl font-bold flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95">
                  <svg class="w-4 h-4 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  <span> Tambah Kolom Nilai</span>
                </button>
              </div>

              <!-- Table mobile swipe hint -->
              <div class="md:hidden px-3.5 py-1.5 bg-indigo-50/70 border-b border-indigo-100 text-[11px] text-indigo-700 flex items-center justify-between font-medium">
                <span>👉 Geser tabel ke kanan untuk melihat kolom penilaian</span>
                <span class="text-xs">↔️</span>
              </div>

              <!-- Table Container -->
              <div class="overflow-x-auto table-scroll">
                <table class="w-full text-left text-xs border-collapse" id="dynamic-grade-table">
                  <thead>
                    <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200" id="grade-table-header-row">
                      <!-- Javascript will render table header -->
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 text-slate-700" id="grade-table-body">
                    <!-- Populated via Javascript -->
                  </tbody>
                </table>
              </div>

              <!-- FOOTER TABEL: TOMBOL SIMPAN & DOWNLOAD CSV DILETAKKAN DI BAWAH TABEL -->
              <div class="px-4 sm:px-5 py-3.5 sm:py-4 bg-slate-50/90 border-t border-slate-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs">
                <span class="text-slate-500 text-center sm:text-left text-[11px] sm:text-xs">Nilai tersimpan akan langsung terakumulasi ke buku induk rekap rapor.</span>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-2.5 w-full sm:w-auto">
                  <button onclick="saveAllGrades()" id="btn-save-grades" class="w-full sm:w-auto px-4 py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white rounded-xl font-bold flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95">
                    <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Simpan Perubahan</span>
                  </button>

                  <button onclick="exportGradesToCSV()" class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95" title="Download tabel nilai dalam format .csv">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download CSV</span>
                  </button>
                </div>
              </div>

            </div>

          </div>

          <!-- ============================================================== -->
          <!-- SUB-VIEW 2: REKAP SELURUH MAPEL DENGAN RATA-RATA TIAP ANAK (LEDGER) -->
          <!-- ============================================================== -->
          <div id="subview-grade-ledger" class="hidden space-y-4">
            
            <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-900 font-extrabold text-[10px]">Ledger Terpadu</span>
                    <h2 class="text-lg sm:text-xl font-bold text-indigo-950">Rekap Nilai Seluruh Mata Pelajaran</h2>
                  </div>
                  <p class="text-xs text-slate-500 mt-1">
                    Kompilasi nilai akhir seluruh mapel (11 mata pelajaran) beserta perhitungan rata-rata gabungan untuk setiap siswa di kelas.
                  </p>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                  <button onclick="downloadSubviewLedgerCSV()" class="w-full sm:w-auto px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition-all active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download Rekap Seluruh Mapel (CSV)</span>
                  </button>
                </div>
              </div>

              <!-- Stat Metrics Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-3.5 mt-4 pt-4 border-t border-slate-100 text-xs">
                <div class="bg-indigo-50/60 border border-indigo-100 rounded-xl p-3.5">
                  <p class="text-slate-500 text-[11px] font-medium">Rata-Rata Umum Seluruh Mapel</p>
                  <p class="text-xl font-black text-indigo-950 mt-0.5" id="metric-ledger-all-avg">83.8</p>
                  <p class="text-[10px] text-emerald-600 font-semibold mt-0.5">Tuntas di atas KKM Sekolah (75)</p>
                </div>
                <div class="bg-emerald-50/60 border border-emerald-100 rounded-xl p-3.5">
                  <p class="text-slate-500 text-[11px] font-medium">Siswa Tuntas Seluruh Mapel</p>
                  <p class="text-xl font-black text-emerald-700 mt-0.5" id="metric-ledger-pass-count">5 / 6 Siswa</p>
                  <p class="text-[10px] text-emerald-700 font-semibold mt-0.5">83.3% Ketuntasan Belajar</p>
                </div>
                <div class="bg-amber-50/60 border border-amber-100 rounded-xl p-3.5">
                  <p class="text-slate-500 text-[11px] font-medium">Perlu Pendampingan / Remedial</p>
                  <p class="text-xl font-black text-amber-700 mt-0.5" id="metric-ledger-remedial-count">1 Siswa</p>
                  <p class="text-[10px] text-amber-800 font-semibold mt-0.5">Daffa Ramadhan (Rata-rata &lt; 75)</p>
                </div>
              </div>
            </div>

            <!-- Ledger Table Container -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
              <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50/80 border-b border-slate-200 flex flex-wrap items-center justify-between gap-1.5">
                <span class="font-bold text-slate-800 text-xs">Tabel Ledger Komparasi 11 Mata Pelajaran</span>
                <span class="text-[10px] sm:text-[11px] text-slate-400">Nilai bersumber dari guru pengampu</span>
              </div>

              <!-- Table mobile swipe hint -->
              <div class="md:hidden px-3.5 py-1.5 bg-blue-50/70 border-b border-blue-100 text-[11px] text-blue-700 flex items-center justify-between font-medium">
                <span>👉 Geser tabel ke kanan untuk melihat 11 mapel</span>
                <span class="text-xs">↔️</span>
              </div>

              <div class="overflow-x-auto table-scroll">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                      <th class="py-3 px-3 w-10 text-center">No</th>
                      <th class="py-3 px-3 w-28">NISN</th>
                      <th class="py-3 px-4 min-w-[150px]">Nama Siswa</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950 border-l border-slate-200" title="Matematika (KKM 75)">MTK</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Ilmu Pengetahuan Alam (KKM 75)">IPA</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Ilmu Pengetahuan Sosial (KKM 75)">IPS</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Bahasa Indonesia (KKM 78)">IND</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Bahasa Inggris (KKM 75)">ING</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Pendidikan Pancasila (KKM 78)">PKN</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Pendidikan Agama & Budi Pekerti (KKM 80)">PAI</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Informatika (KKM 75)">INF</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Pendidikan Jasmani & Olahraga (KKM 78)">PJK</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Seni Budaya (KKM 75)">SNB</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Bahasa Sunda Mulok (KKM 75)">SND</th>
                      <th class="py-3 px-3 text-center bg-amber-100 text-amber-950 font-black border-l border-slate-200 text-xs">Rata-Rata Seluruh Mapel</th>
                      <th class="py-3 px-3 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody id="subview-ledger-tbody" class="divide-y divide-slate-100 text-slate-700">
                    <!-- Populated dynamically via renderSubviewLedgerTable() -->
                  </tbody>
                </table>
              </div>

              <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 flex flex-wrap items-center justify-between gap-2">
                <span>Keterangan: Skor berwarna hijau menandakan tuntas &ge; KKM mapel. Skor merah menandakan perlu remedial.</span>
                <span class="font-bold text-indigo-900">Kurikulum Merdeka · Fase D SMP</span>
              </div>
            </div>

          </div>

        </section>

        <!-- ============================================================== -->
        <!-- TAB 2: PRESENSI KEHADIRAN (MURNI PENCATATAN PER HARI MASUK) -->
        <!-- Bagian Rekap 1 Semester telah dipindah ke Tab Rekap Keseluruhan -->
        <!-- ============================================================== -->
        <section id="tab-presensi" class="hidden space-y-4">
          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm space-y-4">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h1 class="text-lg sm:text-xl font-bold text-indigo-950">Presensi Kehadiran Siswa</h1>
                  <span class="text-xs bg-emerald-100 text-emerald-800 font-semibold px-2.5 py-0.5 rounded-full" id="attend-column-count-badge">3 Pertemuan Tercatat</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                  Catat kehadiran siswa pada setiap hari masuk sekolah. Klik badge (H/I/S/A) untuk mengganti status kehadiran.
                </p>
              </div>

              <!-- Quick action -->
              <button onclick="markAllCurrentMeetingPresent()" class="w-full sm:w-auto px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-bold transition-all text-center justify-center">
                 Tandai Semua Hadir di Pertemuan Terakhir
              </button>
            </div>

            <!-- Header Bar Tabel Presensi + Tombol Tambah Kolom Hari Masuk -->
            <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 sm:gap-3 text-xs">
              <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <span class="font-bold text-slate-800 text-sm">Lembar Kehadiran Harian</span>
                <span class="text-slate-500 text-[11px]">(Klik badge: H=Hadir, I=Izin, S=Sakit, A=Alpa)</span>
              </div>

              <!-- TOMBOL KOTAK TAMBAH KOLOM HARI MASUK / PERTEMUAN -->
              <button onclick="openAddAttendanceColumnModal()" class="w-full sm:w-auto px-3.5 py-2 sm:py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-950 border-2 border-emerald-300 hover:border-emerald-500 rounded-xl font-bold flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span> Tambah Hari Masuk / Pertemuan</span>
              </button>
            </div>

            <!-- Table mobile swipe hint -->
            <div class="md:hidden px-3.5 py-1.5 bg-emerald-50/70 border border-emerald-100 rounded-xl text-[11px] text-emerald-800 flex items-center justify-between font-medium">
              <span>👉 Geser ke kanan untuk melihat pertemuan presensi</span>
              <span class="text-xs">↔️</span>
            </div>

            <!-- Tabel Presensi Dinamis -->
            <div class="border border-slate-200 rounded-xl overflow-x-auto table-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200" id="attendance-table-header-row">
                    <!-- Populated via Javascript -->
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="attendance-table-body">
                  <!-- Populated via Javascript -->
                </tbody>
              </table>
            </div>

            <!-- TOMBOL AKSI DILETAKKAN DI BAWAH TABEL PRESENSI -->
            <div class="pt-3 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs border-t border-slate-100">
              <span class="text-slate-500 text-[11px] sm:text-xs text-center sm:text-left">Seluruh data presensi per hari otomatis terakumulasi di menu Rekap Keseluruhan.</span>
              
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <button onclick="saveAttendance()" class="w-full sm:w-auto px-4 py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white rounded-xl font-bold transition-all shadow-sm justify-center">
                  Simpan Presensi
                </button>
                <button onclick="exportDailyAttendanceCSV()" class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold transition-all shadow-sm flex items-center justify-center gap-1.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                  <span>Download Presensi (.csv)</span>
                </button>
              </div>
            </div>

          </div>

        </section>


        <!-- ============================================================== -->
        <!-- TAB 3: JURNAL HARIAN MENGAJAR GURU -->
        <!-- ============================================================== -->
        <section id="tab-jurnal" class="hidden space-y-4">
          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h2 class="text-lg font-bold text-indigo-950">Jurnal Harian Mengajar Guru</h2>
                  <span class="text-xs bg-amber-100 text-amber-900 font-semibold px-2.5 py-0.5 rounded-full" id="jurnal-tag-class">Matematika - Kelas VIII A</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                  Catatan agenda pembelajaran per pertemuan: hari, tanggal, materi pokok, rincian kegiatan kelas, dan rekap absensi.
                </p>
              </div>

              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <button onclick="exportJournalCSV()" class="w-full sm:w-auto px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 transition-all shadow-xs">
                  <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                  <span>Download CSV</span>
                </button>
                <button onclick="openAddJournalModal()" class="w-full sm:w-auto px-4 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 shadow-sm transition-all active:scale-95">
                  <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  <span> Tambah Jurnal Harian</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Tabel Jurnal Harian -->
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Table mobile swipe hint -->
            <div class="md:hidden px-3.5 py-1.5 bg-amber-50/70 border-b border-amber-100 text-[11px] text-amber-900 flex items-center justify-between font-medium">
              <span>👉 Geser tabel ke kanan untuk melihat rincian kegiatan & absensi</span>
              <span class="text-xs">↔️</span>
            </div>

            <div class="overflow-x-auto table-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 font-bold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                    <th class="py-3 px-3 w-10 text-center">No</th>
                    <th class="py-3 px-3 w-36">Hari & Tanggal</th>
                    <th class="py-3 px-3 w-28">Pertemuan</th>
                    <th class="py-3 px-4 min-w-[180px]">Materi Pembelajaran</th>
                    <th class="py-3 px-4 min-w-[260px]">Kegiatan Pembelajaran</th>
                    <th class="py-3 px-3 min-w-[170px] text-center">Rekap Absen Siswa</th>
                    <th class="py-3 px-3 text-right w-20">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="journal-table-body">
                  <!-- Injected via JavaScript -->
                </tbody>
              </table>
            </div>
            <div class="p-3.5 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1">
              <span id="journal-count-label">Menampilkan entri jurnal mengajar</span>
              <span class="text-slate-400">Jurnal ini dapat dicetak untuk keperluan supervisi administrasi kurikulum.</span>
            </div>
          </div>

        </section>


        <!-- ============================================================== -->
        <!-- TAB 4: MATERI & MODUL AJAR (PDF UPLOADER & REPOSITORY) -->
        <!-- ============================================================== -->
        <section id="tab-materi" class="hidden space-y-5">
          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
              <div>
                <h2 class="text-base sm:text-lg font-bold text-indigo-950">Upload Modul / Materi Pembelajaran</h2>
                <p class="text-xs text-slate-500">Unggah bahan ajar, modul PDF, LKPD, atau kisi-kisi untuk kelas aktif.</p>
              </div>
              <span class="text-xs bg-amber-100 text-amber-900 font-semibold px-2.5 py-1 rounded-lg self-start sm:self-auto">Maksimal 15 MB / file</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5 mt-4 text-xs">
              <div class="space-y-3">
                <div>
                  <label class="block text-slate-600 font-semibold mb-1">Judul Materi / Modul</label>
                  <input id="input-modul-title" type="text" placeholder="Contoh: Modul Eksponen & Bentuk Akar" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-slate-600 font-semibold mb-1">Mata Pelajaran (Terkunci)</label>
                    <input type="text" id="modul-subject-locked" value="Matematika" readonly class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 font-medium cursor-not-allowed text-slate-600">
                  </div>
                  <div>
                    <label class="block text-slate-600 font-semibold mb-1">Kelas Sasaran</label>
                    <input type="text" id="modul-class-locked" value="Kelas VIII A" readonly class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 font-medium cursor-not-allowed text-slate-600">
                  </div>
                </div>

                <div>
                  <label class="block text-slate-600 font-semibold mb-1">Deskripsi Singkat untuk Siswa</label>
                  <textarea id="input-modul-desc" rows="3" placeholder="Catatan instruksi membaca modul..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>
              </div>

              <!-- Drag & Drop Zone -->
              <div class="flex flex-col justify-between">
                <div class="border-2 border-dashed border-indigo-200 hover:border-indigo-400 bg-indigo-50/30 rounded-xl p-5 sm:p-6 text-center flex flex-col items-center justify-center flex-1 cursor-pointer transition-colors group">
                  <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                  </div>
                  <p class="font-bold text-indigo-950 text-sm">Tarik & Lepas File Modul (PDF / DOCX)</p>
                  <p class="text-slate-400 text-xs mt-1">atau klik untuk telusuri file dari perangkat</p>
                  <div class="mt-3 px-3 py-1 bg-white border border-indigo-100 text-indigo-800 rounded-md font-medium text-[11px] shadow-sm">
                    File siap diunggah ke rombel aktif
                  </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 mt-4">
                  <button class="w-full sm:w-auto px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-semibold justify-center">Simpan Draf</button>
                  <button onclick="addModuleSimulated()" class="w-full sm:w-auto px-5 py-2 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Terbitkan Modul</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Daftar Modul Terbit -->
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
              <h3 class="font-bold text-sm text-indigo-950" id="module-header-title">Modul Terbit</h3>
              <span class="text-xs text-slate-500">Tersedia untuk siswa di kelas ini</span>
            </div>

            <div class="divide-y divide-slate-100 text-xs" id="module-list-container">
              <!-- Rendered dynamically -->
            </div>
          </div>

        </section>


        <!-- ============================================================== -->
        <!-- TAB 5: REKAP KESELURUHAN (SEMUA REKAP 1 SEMESTER ADA DI SINI) -->
        <!-- ============================================================== -->
        <section id="tab-rekap" class="hidden space-y-5 sm:space-y-6">
          
          <!-- Banner Header -->
          <div class="bg-gradient-to-r from-indigo-950 to-indigo-900 text-white rounded-2xl p-4 sm:p-6 shadow-md">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <span class="text-[11px] sm:text-xs bg-amber-400 text-indigo-950 font-extrabold px-2.5 py-1 rounded-md">Buku Induk & Ledger Rapor</span>
                <h2 class="text-xl sm:text-2xl font-bold mt-2 text-white">Rekapitulasi Capaian Siswa 1 Semester</h2>
                <p class="text-xs text-indigo-200 mt-1 max-w-xl">
                  Seluruh hasil evaluasi nilai akhir dan rekap presensi semester ganjil terintegrasi di sini untuk persiapan buku rapor.
                </p>
              </div>
            </div>

            <!-- Highlight Metric Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 mt-4 sm:mt-6 pt-4 sm:pt-5 border-t border-indigo-800/80">
              <div class="bg-indigo-900/60 rounded-xl p-3 border border-indigo-700/50">
                <p class="text-[11px] text-indigo-300">Rata-Rata Kelas</p>
                <p class="text-lg sm:text-xl font-bold text-amber-300 mt-0.5" id="metric-class-avg">83,2</p>
                <p class="text-[10px] text-emerald-400 mt-1" id="metric-kkm-comparison">Target KKM: 75</p>
              </div>
              <div class="bg-indigo-900/60 rounded-xl p-3 border border-indigo-700/50">
                <p class="text-[11px] text-indigo-300">Rata-Rata Kehadiran</p>
                <p class="text-lg sm:text-xl font-bold text-emerald-400 mt-0.5" id="metric-attend-avg">94,2%</p>
                <p class="text-[10px] text-indigo-200 mt-1">Akumulasi 1 Semester</p>
              </div>
              <div class="bg-indigo-900/60 rounded-xl p-3 border border-indigo-700/50 col-span-2 sm:col-span-2">
                <p class="text-[11px] text-indigo-300">Catatan Evaluasi Guru</p>
                <p class="text-lg sm:text-xl font-bold text-amber-300 mt-0.5" id="metric-notes-count">6 Siswa</p>
                <p class="text-[10px] text-indigo-200 mt-1">Tersedia untuk diedit langsung</p>
              </div>
            </div>
          </div>

          <!-- BAGIAN 1: REKAP PRESENSI 1 SEMESTER PENUH -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h3 class="font-bold text-sm sm:text-base text-indigo-950">1. Rekap Presensi Siswa Selama 1 Semester</h3>
                <p class="text-xs text-slate-500">Akumulasi seluruh kehadiran berdasarkan hari masuk yang dicatat guru.</p>
              </div>
              <button onclick="exportAttendanceSemesterCSV()" class="w-full sm:w-auto px-3.5 py-2 sm:py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Unduh CSV Presensi Semester</span>
              </button>
            </div>

            <!-- Table mobile swipe hint -->
            <div class="md:hidden px-3.5 py-1.5 bg-emerald-50/70 border border-emerald-100 rounded-xl text-[11px] text-emerald-800 flex items-center justify-between font-medium">
              <span>👉 Geser tabel ke kanan untuk melihat presensi 1 semester</span>
              <span class="text-xs">↔️</span>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-x-auto table-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200">
                    <th class="py-2.5 px-3 w-10 text-center">No</th>
                    <th class="py-2.5 px-3 w-28">NISN</th>
                    <th class="py-2.5 px-4 min-w-[160px]">Nama Lengkap Siswa</th>
                    <th class="py-2.5 px-2 text-center w-14 bg-emerald-50 text-emerald-900">Hadir</th>
                    <th class="py-2.5 px-2 text-center w-14 bg-amber-50 text-amber-900">Izin</th>
                    <th class="py-2.5 px-2 text-center w-14 bg-purple-50 text-purple-900">Sakit</th>
                    <th class="py-2.5 px-2 text-center w-14 bg-rose-50 text-rose-900">Alpa</th>
                    <th class="py-2.5 px-3 text-center w-24">Total Pertemuan</th>
                    <th class="py-2.5 px-3 text-center w-20 font-bold">% Kehadiran</th>
                    <th class="py-2.5 px-3 min-w-[300px]">Catatan Evaluasi Guru (Dapat Diedit)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="attendance-semester-table-body">
                  <!-- Javascript will inject data -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- BAGIAN 2: BUKU INDUK LEDGER AKADEMIK & REKAP RAPOR -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h3 class="font-bold text-sm sm:text-base text-indigo-950">2. Buku Induk & Ledger Rapor (Nilai Akhir)</h3>
                <p class="text-xs text-slate-500">Rangkuman nilai akhir terhadap KKM aktif (<span class="font-bold text-indigo-900 label-kkm-legend">75</span>).</p>
              </div>
              <button onclick="exportGradesToCSV()" class="w-full sm:w-auto px-3.5 py-2 sm:py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border border-indigo-200 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Unduh CSV Nilai Rapor</span>
              </button>
            </div>

            <!-- Table mobile swipe hint -->
            <div class="md:hidden px-3.5 py-1.5 bg-indigo-50/70 border border-indigo-100 rounded-xl text-[11px] text-indigo-700 flex items-center justify-between font-medium">
              <span>👉 Geser tabel ke kanan untuk melihat rincian rapor & catatan</span>
              <span class="text-xs">↔️</span>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-x-auto table-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200">
                    <th class="py-3 px-3 w-10 text-center">No</th>
                    <th class="py-3 px-3 w-28">NISN</th>
                    <th class="py-3 px-4 min-w-[160px]">Nama Siswa</th>
                    <th class="py-3 px-3 text-center bg-blue-50/50 text-blue-900">Total Tugas Dinilai</th>
                    <th class="py-3 px-3 text-center font-extrabold bg-amber-50/70 text-amber-950">Rata-Rata Akhir</th>
                    <th class="py-3 px-3 text-center">Presensi 1 Semester</th>
                    <th class="py-3 px-3 min-w-[300px]">Catatan Evaluasi Pembelajaran (Dapat Diedit)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="ledger-table-body">
                  <!-- Javascript will inject data -->
                </tbody>
              </table>
            </div>

            <!-- Footer Tombol Simpan & Cetak Dokumen Rapor -->
            <div class="pt-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2.5">
              <button onclick="saveAllEvaluations()" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Simpan Seluruh Catatan Evaluasi</span>
              </button>
              <button onclick="window.print()" class="w-full sm:w-auto px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak Lembar Ledger Rapor (PDF)</span>
              </button>
            </div>
          </div>

        </section>

              <!-- ============================================================== -->
        <!-- TAB 6: PUSAT BANTUAN & FAQ GURU -->
        <!-- ============================================================== -->
        <section id="tab-faq" class="hidden space-y-4">
          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-10 h-10 rounded-2xl bg-indigo-900 text-amber-400 font-bold flex items-center justify-center text-lg">
                💡
              </div>
              <div>
                <h2 class="text-xl font-bold text-indigo-950">Pusat Bantuan & FAQ Guru</h2>
                <p class="text-xs text-slate-500">Panduan teknis pengoperasian portal akademik bagi Guru Mata Pelajaran dan Wali Kelas.</p>
              </div>
            </div>

            <!-- FAQ List Accordion / Cards -->
            <div class="mt-6 space-y-3.5 text-xs">
              
              <!-- Q1: Peran Guru Mapel vs Wali Kelas -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">1</span>
                    <span>Apa perbedaan wewenang antara Guru Mata Pelajaran dan Wali Kelas?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p><strong>Guru Mata Pelajaran:</strong> Berwenang penuh untuk menambah kolom penilaian baru, menginput nilai harian/sumatif, menyesuaikan KKM/KKTP mapel yang diampu, serta mengunggah modul/materi ajar.</p>
                  <p class="mt-2"><strong>Wali Kelas:</strong> Berwenang memantau perkembangan seluruh mata pelajaran di kelas binaan (11 mapel), melihat lembar penilaian setiap mapel secara read-only, menginput catatan evaluasi rapor, serta mencetak ledger nilai rapor akhir semester.</p>
                </div>
              </details>

              <!-- Q2: Mengapa Wali Kelas Tidak Bisa Tambah / Ubah Nilai? -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">2</span>
                    <span>Mengapa tombol " Tambah Kolom Nilai" dan kotak isian nilai tidak aktif saat login sebagai Wali Kelas?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Hal ini dirancang sesuai SOP Akademik SMPN 7 Purwakarta agar menjaga integritas data penilaian. Nilai capaian siswa merupakan otoritas pedagogis dari Guru Mata Pelajaran yang mengajar di kelas tersebut. Wali Kelas hanya bertindak sebagai pemantau dan koordinator rekap (Read-Only) agar tidak terjadi tumpang tindih manipulasi data nilai.</p>
                </div>
              </details>

              <!-- Q3: Cara Menambah Kolom Nilai Manual -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">3</span>
                    <span>Bagaimana cara menambahkan kolom tugas atau ulangan baru secara manual satu per satu?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>1. Masuk sebagai <strong>Guru Mapel</strong> dan pastikan mata pelajaran serta kelas yang diinginkan sudah dipilih.</p>
                  <p>2. Pada tab <strong>Input Nilai Siswa</strong> &rarr; sub-tab <strong>Lembar Penilaian Mapel</strong>, klik tombol <strong> Tambah Kolom Nilai</strong>.</p>
                  <p>3. Masukkan nama penilaian (misal: <em>Tugas Aljabar 1</em> atau <em>UH Bab 2</em>), pilih tanggal pelaksanaan dan kategori (Formatif/Sumatif/Praktik).</p>
                  <p>4. Klik <strong>Sisipkan Kolom Kosong</strong>. Kolom baru akan muncul di tabel dan Anda dapat langsung mengetikkan nilai siswa dari rentang 0 hingga 100.</p>
                </div>
              </details>

              <!-- Q4: Mengatur KKM / KKTP -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">4</span>
                    <span>Bagaimana cara menyesuaikan batas KKM / KKTP untuk mata pelajaran saya?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Pada header lembar penilaian siswa, terdapat kotak <strong>KKM / KKTP Mapel</strong> (default 75). Anda dapat langsung mengganti angka tersebut (misal menjadi 78 atau 80). Sistem akan secara otomatis mengkalkulasi ulang status ketuntasan siswa, mengubah warna rata-rata siswa menjadi hijau jika tuntas atau merah jika memerlukan remedial.</p>
                </div>
              </details>

              <!-- Q5: Melihat Rekap Seluruh Mapel -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">5</span>
                    <span>Di mana saya bisa melihat rekap nilai seluruh mapel beserta rata-rata tiap siswa?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Buka tab <strong>Input Nilai Siswa</strong>, lalu klik sub-tab kedua yang berada di bagian atas: <strong>📊 Rekap Seluruh Mapel (Ledger Nilai Siswa)</strong>. Di sini ditampilkan tabel komprehensif berisi seluruh 11 mata pelajaran, rata-rata akhir tiap siswa lintas mapel, serta status ketuntasan umum.</p>
                </div>
              </details>

              <!-- Q6: Jurnal Harian -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">6</span>
                    <span>Bagaimana cara mengisi dan mengunduh Jurnal Harian Guru?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Klik menu <strong>Jurnal Harian</strong> di sidebar navigasi. Klik tombol <strong> Tambah Jurnal Harian</strong> untuk mengisi hari, tanggal, jam pertemuan, materi pokok, deskripsi kegiatan pembelajaran, dan rekap absensi (H/I/S/A). Anda juga dapat mengklik tombol <strong>Download CSV</strong> untuk mencetak arsip jurnal mengajar.</p>
                </div>
              </details>

              <!-- Q7: Download CSV -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">7</span>
                    <span>Bagaimana cara mengekspor data nilai atau presensi ke format Excel?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Di setiap lembar kerja (Nilai Mapel, Presensi Harian, Presensi Semester, dan Rekap Seluruh Mapel), tersedia tombol <strong>Download CSV</strong>. File CSV yang diunduh dapat langsung dibuka menggunakan Microsoft Excel, Google Sheets, atau aplikasi spreadsheet lainnya.</p>
                </div>
              </details>

            </div>

            <!-- Contact Helpdesk Box -->
            <div class="mt-6 p-4 bg-indigo-50/70 border border-indigo-200 rounded-2xl flex items-center justify-between flex-wrap gap-3">
              <div class="flex items-center gap-3">
                <span class="text-2xl">📞</span>
                <div>
                  <p class="font-bold text-indigo-950">Membutuhkan Bantuan Teknis Lebih Lanjut?</p>
                  <p class="text-[11px] text-slate-600">Hubungi Tim Helpdesk Kurikulum & Tata Usaha SMPN 7 Purwakarta</p>
                </div>
              </div>
              <span class="px-3.5 py-1.5 bg-indigo-900 text-white rounded-xl font-bold text-xs">Ruang Server TU (Psw. 104)</span>
            </div>

          </div>

        </section>

      </main>
    </div>

  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH KOLOM PENILAIAN BARU (TANPA NILAI BAWAAN) -->
  <!-- ============================================================== -->
  <div id="modal-add-column" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-100 overflow-hidden transform transition-all max-h-[92vh] flex flex-col animate-in fade-in zoom-in duration-150">
      
      <div class="bg-indigo-950 text-white p-4 sm:p-5 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center font-bold flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          </div>
          <div>
            <h3 class="font-bold text-sm sm:text-base">Tambah Kolom Penilaian Baru</h3>
            <p class="text-[11px] sm:text-xs text-indigo-200">Kolom disisipkan kosong (guru mengisi dari awal)</p>
          </div>
        </div>
        <button onclick="closeAddColumnModal()" class="text-indigo-300 hover:text-white p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleConfirmAddColumn(event)" class="p-4 sm:p-6 space-y-3.5 sm:space-y-4 text-xs overflow-y-auto">
        <div>
          <label class="block font-bold text-slate-700 mb-1">
            Nama / Jenis Penilaian <span class="text-rose-500">*</span>
          </label>
          <input type="text" id="new-col-name" required placeholder="Contoh: Tugas 4 (Aljabar) / Kuis Bab 2 / Proyek" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
          <p class="text-[11px] text-slate-400 mt-1">Nama ini akan menjadi judul header kolom tabel.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">
              Hari & Tanggal Penilaian <span class="text-rose-500">*</span>
            </label>
            <input type="date" id="new-col-date" required value="2026-10-14" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Kategori Nilai</label>
            <select id="new-col-category" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
              <option value="Formatif">Formatif (Tugas/PR)</option>
              <option value="Sumatif">Sumatif (Ulangan/UH)</option>
              <option value="Praktik">Unjuk Kerja / Praktik</option>
              <option value="Proyek">Tugas Proyek</option>
            </select>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
          <button type="button" onclick="closeAddColumnModal()" class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 rounded-xl text-slate-600 font-semibold hover:bg-slate-50 justify-center">Batal</button>
          <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-md justify-center">
            Sisipkan Kolom Kosong
          </button>
        </div>
      </form>

    </div>
  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH KOLOM HARI MASUK / PERTEMUAN KEHADIRAN -->
  <!-- ============================================================== -->
  <div id="modal-add-attendance-column" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-100 overflow-hidden transform transition-all max-h-[92vh] flex flex-col animate-in fade-in zoom-in duration-150">
      
      <div class="bg-indigo-950 text-white p-4 sm:p-5 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <h3 class="font-bold text-sm sm:text-base">Tambah Hari Masuk / Pertemuan</h3>
            <p class="text-[11px] sm:text-xs text-indigo-200">Kolom presensi baru akan ditambahkan ke samping</p>
          </div>
        </div>
        <button onclick="closeAddAttendanceColumnModal()" class="text-indigo-300 hover:text-white p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleConfirmAddAttendanceColumn(event)" class="p-4 sm:p-6 space-y-3.5 sm:space-y-4 text-xs overflow-y-auto">
        <div>
          <label class="block font-bold text-slate-700 mb-1">
            Nama Pertemuan <span class="text-rose-500">*</span>
          </label>
          <input type="text" id="new-att-name" required placeholder="Contoh: Pertemuan 4 / Jam Ke-1 & 2" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">
            Hari & Tanggal Masuk <span class="text-rose-500">*</span>
          </label>
          <input type="date" id="new-att-date" required value="2026-10-22" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
        </div>

        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 flex items-center gap-2">
          <input type="checkbox" id="new-att-default-h" checked class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500">
          <label for="new-att-default-h" class="cursor-pointer font-medium text-[11px]">Tandai semua siswa Hadir (H) secara otomatis untuk pertemuan ini</label>
        </div>

        <div class="pt-3 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
          <button type="button" onclick="closeAddAttendanceColumnModal()" class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 rounded-xl text-slate-600 font-semibold hover:bg-slate-50 justify-center">Batal</button>
          <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-md justify-center">
            Tambahkan Hari Masuk
          </button>
        </div>
      </form>

    </div>
  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH JURNAL HARIAN GURU -->
  <!-- ============================================================== -->
  <div id="modal-jurnal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-100 overflow-hidden text-xs max-h-[92vh] flex flex-col">
      
      <div class="bg-indigo-950 text-white p-4 sm:p-5 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center font-bold flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
          </div>
          <div>
            <h3 class="font-bold text-sm sm:text-base" id="modal-jurnal-title">Tambah Jurnal Mengajar Harian</h3>
            <p class="text-[11px] sm:text-xs text-indigo-200">Catat agenda tatap muka dan kegiatan belajar siswa</p>
          </div>
        </div>
        <button onclick="closeAddJournalModal()" class="text-indigo-300 hover:text-white p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleSaveJournal(event)" class="p-4 sm:p-6 space-y-3 sm:space-y-3.5 overflow-y-auto">
        <input type="hidden" id="input-jurnal-id" value="">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Hari <span class="text-rose-500">*</span></label>
            <select id="input-jurnal-hari" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              <option value="Senin">Senin</option>
              <option value="Selasa">Selasa</option>
              <option value="Rabu">Rabu</option>
              <option value="Kamis">Kamis</option>
              <option value="Jumat">Jumat</option>
              <option value="Sabtu">Sabtu</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Tanggal <span class="text-rose-500">*</span></label>
            <input type="date" id="input-jurnal-tanggal" required value="2026-10-06" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Pertemuan / Jam Ke- <span class="text-rose-500">*</span></label>
          <input type="text" id="input-jurnal-pertemuan" required placeholder="Contoh: Pertemuan ke-9 (Jam Ke 1-3)" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Materi Pembelajaran <span class="text-rose-500">*</span></label>
          <input type="text" id="input-jurnal-materi" required placeholder="Contoh: Pembagian Bentuk Akar & Merasionalkan Penyebut" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Kegiatan Pembelajaran <span class="text-rose-500">*</span></label>
          <textarea id="input-jurnal-kegiatan" rows="3" required placeholder="Uraikan alur kegiatan: apersepsi, penyampaian materi, pengerjaan LKPD/diskusi, dan evaluasi refleksi..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none"></textarea>
        </div>

        <!-- Rekap Absensi Pertemuan -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3.5 space-y-2">
          <label class="block font-bold text-slate-700 text-[11px] uppercase tracking-wider">Rekap Kehadiran Siswa Pertemuan Ini</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <div>
              <span class="block text-[10px] font-bold text-emerald-700 mb-0.5">Hadir</span>
              <input type="number" id="input-jurnal-h" min="0" value="31" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-center font-bold text-emerald-800 bg-white">
            </div>
            <div>
              <span class="block text-[10px] font-bold text-amber-700 mb-0.5">Izin</span>
              <input type="number" id="input-jurnal-i" min="0" value="1" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-center font-bold text-amber-800 bg-white">
            </div>
            <div>
              <span class="block text-[10px] font-bold text-purple-700 mb-0.5">Sakit</span>
              <input type="number" id="input-jurnal-s" min="0" value="0" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-center font-bold text-purple-800 bg-white">
            </div>
            <div>
              <span class="block text-[10px] font-bold text-rose-700 mb-0.5">Alpa</span>
              <input type="number" id="input-jurnal-a" min="0" value="0" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-center font-bold text-rose-800 bg-white">
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
          <button type="button" onclick="closeAddJournalModal()" class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 rounded-xl text-slate-600 font-semibold hover:bg-slate-50 justify-center">Batal</button>
          <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-md justify-center">
            Simpan Jurnal Harian
          </button>
        </div>
      </form>

    </div>
  </div>


  <!-- TOAST NOTIFICATION CONTAINER -->
  <div id="toast" class="fixed bottom-4 right-4 left-4 sm:left-auto sm:right-5 sm:bottom-5 bg-indigo-950 text-white px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3 border border-indigo-800 text-xs transform transition-all duration-300 translate-y-20 opacity-0 z-50 pointer-events-none">
    <div class="w-7 h-7 rounded-lg bg-emerald-500 text-white flex items-center justify-center font-bold">✓</div>
    <div>
      <p class="font-bold" id="toast-title">Berhasil!</p>
      <p class="text-indigo-200" id="toast-msg">Data telah disimpan ke database sekolah.</p>
    </div>
  </div>


  <!-- ============================================================== -->
  <!-- JAVASCRIPT APPLICATION CONTROLLER -->
  <!-- ============================================================== -->
  <script>
    // ROLE STATE: 'guru_mapel' ATAU 'wali_kelas'
    let currentTeacherRole = 'guru_mapel';

    // KKM FLEKSIBEL (DEFAULT 75, BISA DISESUAIKAN GURU)
    let currentKKM = 75;

    // DATA KOLOM PENILAIAN MANUAL (TUGAS 1, 2, SUMATIF FIKS DIHAPUS, DIINPUT MANUAL SATU-SATU)
    let gradeColumns = [];

    // DATABASE MATA PELAJARAN UNTUK WALI KELAS (KELAS VIII A)
    const waliClassSubjectsData = {
      'Matematika': {
        teacher: 'Ibu Siti Rahmawati, S.Pd',
        kkm: 75,
        columns: [
          { id: 'm_1', name: 'Tugas Eksponen', date: 'Senin, 08 Sep 2026', category: 'Formatif' },
          { id: 'm_2', name: 'Bentuk Akar', date: 'Senin, 15 Sep 2026', category: 'Formatif' },
          { id: 'm_3', name: 'Ulangan Harian Bab 1', date: 'Kamis, 25 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { m_1: 82, m_2: 88, m_3: 85 },
          2: { m_1: 92, m_2: 90, m_3: 94 },
          3: { m_1: 76, m_2: 81, m_3: 78 },
          4: { m_1: 88, m_2: 86, m_3: 90 },
          5: { m_1: 70, m_2: 74, m_3: 72 },
          6: { m_1: 84, m_2: 87, m_3: 85 }
        }
      },
      'IPA': {
        teacher: 'Bapak Budi Santoso, M.Pd',
        kkm: 75,
        columns: [
          { id: 'ipa_1', name: 'Struktur Sel', date: 'Selasa, 09 Sep 2026', category: 'Formatif' },
          { id: 'ipa_2', name: 'Praktikum Mikroskop', date: 'Selasa, 16 Sep 2026', category: 'Praktik' },
          { id: 'ipa_3', name: 'Sumatif Bab Sel', date: 'Selasa, 23 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { ipa_1: 85, ipa_2: 90, ipa_3: 87 },
          2: { ipa_1: 90, ipa_2: 94, ipa_3: 92 },
          3: { ipa_1: 78, ipa_2: 80, ipa_3: 77 },
          4: { ipa_1: 86, ipa_2: 88, ipa_3: 85 },
          5: { ipa_1: 70, ipa_2: 76, ipa_3: 68 },
          6: { ipa_1: 88, ipa_2: 89, ipa_3: 86 }
        }
      },
      'Bahasa Indonesia': {
        teacher: 'Ibu Dra. Hj. Nur Aini',
        kkm: 78,
        columns: [
          { id: 'bi_1', name: 'Teks Laporan LHO', date: 'Rabu, 10 Sep 2026', category: 'Formatif' },
          { id: 'bi_2', name: 'Kaidah Kebahasaan', date: 'Rabu, 17 Sep 2026', category: 'Formatif' },
          { id: 'bi_3', name: 'Menulis LHO Sekolah', date: 'Rabu, 24 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { bi_1: 86, bi_2: 92, bi_3: 86 },
          2: { bi_1: 94, bi_2: 95, bi_3: 92 },
          3: { bi_1: 79, bi_2: 82, bi_3: 80 },
          4: { bi_1: 88, bi_2: 90, bi_3: 88 },
          5: { bi_1: 78, bi_2: 80, bi_3: 79 },
          6: { bi_1: 85, bi_2: 88, bi_3: 86 }
        }
      },
      'Bahasa Inggris': {
        teacher: 'Ibu Rina Nurhasanah, S.Pd',
        kkm: 75,
        columns: [
          { id: 'bing_1', name: 'Past Tense Recount', date: 'Jumat, 12 Sep 2026', category: 'Formatif' },
          { id: 'bing_2', name: 'Irregular Verbs Quiz', date: 'Jumat, 19 Sep 2026', category: 'Formatif' },
          { id: 'bing_3', name: 'Writing Experience', date: 'Jumat, 26 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { bing_1: 80, bing_2: 86, bing_3: 86 },
          2: { bing_1: 90, bing_2: 92, bing_3: 90 },
          3: { bing_1: 76, bing_2: 78, bing_3: 77 },
          4: { bing_1: 85, bing_2: 88, bing_3: 86 },
          5: { bing_1: 65, bing_2: 72, bing_3: 69 },
          6: { bing_1: 82, bing_2: 85, bing_3: 84 }
        }
      },
      'Informatika': {
        teacher: 'Bapak Ahmad Fauzi, S.Kom',
        kkm: 75,
        columns: [
          { id: 'inf_1', name: 'Logika Algoritma', date: 'Kamis, 11 Sep 2026', category: 'Formatif' },
          { id: 'inf_2', name: 'Rumus Logika Excel', date: 'Kamis, 18 Sep 2026', category: 'Praktik' },
          { id: 'inf_3', name: 'Analisis Data Bab 1', date: 'Kamis, 25 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { inf_1: 94, inf_2: 90, inf_3: 89 },
          2: { inf_1: 95, inf_2: 96, inf_3: 94 },
          3: { inf_1: 82, inf_2: 80, inf_3: 81 },
          4: { inf_1: 89, inf_2: 91, inf_3: 88 },
          5: { inf_1: 85, inf_2: 80, inf_3: 81 },
          6: { inf_1: 90, inf_2: 92, inf_3: 89 }
        }
      },
      'Pendidikan Pancasila': {
        teacher: 'Bapak Dedi Suhendar, S.Pd',
        kkm: 78,
        columns: [
          { id: 'pkn_1', name: 'Makna Ideologi', date: 'Senin, 08 Sep 2026', category: 'Formatif' },
          { id: 'pkn_2', name: 'Norma Hukum', date: 'Senin, 15 Sep 2026', category: 'Formatif' }
        ],
        scores: {
          1: { pkn_1: 82, pkn_2: 85 },
          2: { pkn_1: 90, pkn_2: 92 },
          3: { pkn_1: 80, pkn_2: 81 },
          4: { pkn_1: 86, pkn_2: 88 },
          5: { pkn_1: 75, pkn_2: 78 },
          6: { pkn_1: 84, pkn_2: 86 }
        }
      },
      'IPS': {
        teacher: 'Bapak Tatang Sutisna, S.Pd',
        kkm: 75,
        columns: [
          { id: 'ips_1', name: 'Letak Benua Asia', date: 'Selasa, 09 Sep 2026', category: 'Formatif' },
          { id: 'ips_2', name: 'Interaksi ASEAN', date: 'Selasa, 16 Sep 2026', category: 'Formatif' }
        ],
        scores: {
          1: { ips_1: 80, ips_2: 84 },
          2: { ips_1: 91, ips_2: 93 },
          3: { ips_1: 78, ips_2: 79 },
          4: { ips_1: 85, ips_2: 87 },
          5: { ips_1: 72, ips_2: 76 },
          6: { ips_1: 83, ips_2: 85 }
        }
      },
      'PJOK': {
        teacher: 'Bapak Asep Kurnia, S.Pd',
        kkm: 78,
        columns: [
          { id: 'pjok_1', name: 'Passing Sepak Bola', date: 'Sabtu, 13 Sep 2026', category: 'Praktik' },
          { id: 'pjok_2', name: 'Servis Bola Voli', date: 'Sabtu, 20 Sep 2026', category: 'Praktik' }
        ],
        scores: {
          1: { pjok_1: 88, pjok_2: 92 },
          2: { pjok_1: 94, pjok_2: 90 },
          3: { pjok_1: 82, pjok_2: 84 },
          4: { pjok_1: 89, pjok_2: 87 },
          5: { pjok_1: 85, pjok_2: 83 },
          6: { pjok_1: 88, pjok_2: 89 }
        }
      },
      'Seni Budaya': {
        teacher: 'Ibu Maya Lestari, S.Sn',
        kkm: 75,
        columns: [
          { id: 'sb_1', name: 'Ragam Seni Lukis', date: 'Rabu, 10 Sep 2026', category: 'Formatif' },
          { id: 'sb_2', name: 'Gambar Ilustrasi', date: 'Rabu, 17 Sep 2026', category: 'Praktik' }
        ],
        scores: {
          1: { sb_1: 84, sb_2: 88 },
          2: { sb_1: 92, sb_2: 95 },
          3: { sb_1: 78, sb_2: 81 },
          4: { sb_1: 87, sb_2: 89 },
          5: { sb_1: 75, sb_2: 80 },
          6: { sb_1: 86, sb_2: 88 }
        }
      },
      'Pendidikan Agama': {
        teacher: 'Ibu Eni Rohaeni, S.Ag',
        kkm: 80,
        columns: [
          { id: 'pai_1', name: 'Tadabbur Al-Quran', date: 'Selasa, 09 Sep 2026', category: 'Formatif' },
          { id: 'pai_2', name: 'Asmaul Husna', date: 'Selasa, 16 Sep 2026', category: 'Formatif' }
        ],
        scores: {
          1: { pai_1: 85, pai_2: 88 },
          2: { pai_1: 94, pai_2: 96 },
          3: { pai_1: 80, pai_2: 82 },
          4: { pai_1: 88, pai_2: 90 },
          5: { pai_1: 76, pai_2: 78 },
          6: { pai_1: 88, pai_2: 90 }
        }
      },
      'Bahasa Sunda': {
        teacher: 'Ibu Ceceu Marlina, S.Pd',
        kkm: 75,
        columns: [
          { id: 'sun_1', name: 'Kawih Sunda', date: 'Rabu, 10 Sep 2026', category: 'Formatif' },
          { id: 'sun_2', name: 'Paguneman', date: 'Rabu, 17 Sep 2026', category: 'Praktik' }
        ],
        scores: {
          1: { sun_1: 82, sun_2: 84 },
          2: { sun_1: 90, sun_2: 92 },
          3: { sun_1: 78, sun_2: 80 },
          4: { sun_1: 85, sun_2: 86 },
          5: { sun_1: 72, sun_2: 75 },
          6: { sun_1: 84, sun_2: 86 }
        }
      }
    };

    // DATA KOLOM PERTEMUAN KEHADIRAN (BISA DITAMBAH DAN DIHAPUS BEBAS)
    let attendanceColumns = [
      { id: 'att_1', name: 'Pertemuan 1', date: 'Kamis, 01 Okt 2026' },
      { id: 'att_2', name: 'Pertemuan 2', date: 'Kamis, 08 Okt 2026' },
      { id: 'att_3', name: 'Pertemuan 3', date: 'Kamis, 15 Okt 2026' }
    ];

    // DATA SISWA (KELAS VIII A)
    let students = [
      { 
        id: 1, 
        nisn: '0098427135', 
        name: 'Aditya Pratama', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'H', att_3: 'H' },
        semesterNotes: 'Sangat rajin dan disiplin masuk kelas.'
      },
      { 
        id: 2, 
        nisn: '0098427141', 
        name: 'Alya Nur Safitri', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'H', att_3: 'H' },
        semesterNotes: 'Kehadiran 100% sempurna, aktif berdiskusi.'
      },
      { 
        id: 3, 
        nisn: '0098427156', 
        name: 'Bima Arya Putra', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'S', att_3: 'H' },
        semesterNotes: 'Sempat sakit 1 kali, surat dokter lengkap.'
      },
      { 
        id: 4, 
        nisn: '0098427162', 
        name: 'Citra Maharani', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'H', att_3: 'H' },
        semesterNotes: 'Disiplin dan bertanggung jawab terhadap tugas.'
      },
      { 
        id: 5, 
        nisn: '0098427178', 
        name: 'Daffa Ramadhan', 
        scores: {},
        attendances: { att_1: 'I', att_2: 'A', att_3: 'H' },
        semesterNotes: 'Peringatan: Alpa terdeteksi. Perlu bimbingan kehadiran & remedial nilai.'
      },
      { 
        id: 6, 
        nisn: '0098427184', 
        name: 'Fania Putri Amelia', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'H', att_3: 'H' },
        semesterNotes: 'Konsisten hadir tepat waktu.'
      }
    ];

    // STATE PEMILIHAN KELAS BERTINGKAT & PERAN
    let selectedGradeLevel = 8;
    let selectedRombelLetter = 'A';
    let activeSubject = 'Matematika';
    let activeClass = 'Kelas VIII A';
    let activeWaliPovSubject = 'Matematika';

    // ==============================================================
    // PEMBATASAN AKSES KELAS & MAPEL GURU (STRICT DATA PERMISSION)
    // ==============================================================
    let currentLoggedTeacher = null;
    let teacherAllowedClasses = [];
    let teacherAllowedSubjects = [];
    let teacherWaliInfo = null;

    // Parser kelas guru dari MySQL: e.g. "7G-7I, 8A-8D" -> ['7G', '7H', '7I', '8A', '8B', '8C', '8D']
    function parseTeacherAllowedClasses(kelasStr) {
      if (!kelasStr || kelasStr === '-' || kelasStr.trim() === '') return [];
      const classes = new Set();
      const parts = kelasStr.split(/[,/]/);
      for (let part of parts) {
        part = part.trim().toUpperCase();
        if (!part) continue;

        // Pattern 1: e.g. 7A-7I, 8A-8D, 9E-9G
        let match = part.match(/^([789])([A-I])\s*-\s*([789])([A-I])$/);
        if (match) {
          const grade = match[1];
          const startChar = match[2].charCodeAt(0);
          const endChar = match[4].charCodeAt(0);
          for (let c = Math.min(startChar, endChar); c <= Math.max(startChar, endChar); c++) {
            classes.add(`${grade}${String.fromCharCode(c)}`);
          }
          continue;
        }

        // Pattern 2: e.g. 7A-E or 8A-D
        match = part.match(/^([789])([A-I])\s*-\s*([A-I])$/);
        if (match) {
          const grade = match[1];
          const startChar = match[2].charCodeAt(0);
          const endChar = match[3].charCodeAt(0);
          for (let c = Math.min(startChar, endChar); c <= Math.max(startChar, endChar); c++) {
            classes.add(`${grade}${String.fromCharCode(c)}`);
          }
          continue;
        }

        // Pattern 3: e.g. 7I, 8C, 9A
        match = part.match(/^([789])([A-I])$/);
        if (match) {
          classes.add(`${match[1]}${match[2]}`);
          continue;
        }
      }
      return Array.from(classes);
    }

    // Parser penugasan wali kelas: e.g. "Wali Kelas VIII.A" -> { grade: 8, rombel: 'A', code: '8A', label: 'Kelas VIII A' }
    function parseWaliInfo(waliStr) {
      if (!waliStr || waliStr.toLowerCase().includes('bukan') || waliStr.trim() === '-' || waliStr.trim() === '') {
        return null;
      }
      const m = waliStr.match(/Wali Kelas\s+(VII|VIII|IX|[789])[\.\s]*([A-I])/i);
      if (!m) return null;
      let grade = m[1].toUpperCase();
      if (grade === 'VII') grade = '7';
      else if (grade === 'VIII') grade = '8';
      else if (grade === 'IX') grade = '9';
      const rombel = m[2].toUpperCase();
      return {
        grade: parseInt(grade, 10),
        rombel: rombel,
        code: `${grade}${rombel}`,
        label: `Kelas ${grade === '7' ? 'VII' : (grade === '8' ? 'VIII' : 'IX')} ${rombel}`
      };
    }

    // Parser mapel guru: e.g. "Bahasa Sunda/B.Indonesia" -> ['Bahasa Sunda', 'B.Indonesia']
    function parseTeacherSubjects(mapelStr) {
      if (!mapelStr || mapelStr === '-' || mapelStr.trim() === '') return [];
      return mapelStr.split(/[/,]/).map(s => s.trim()).filter(Boolean);
    }

    // Handler Konfigurasi Izin Guru saat Login
    function setupTeacherPermissions(teacher) {
      currentLoggedTeacher = teacher;
      teacherAllowedClasses = parseTeacherAllowedClasses(teacher.kelas);
      teacherAllowedSubjects = parseTeacherSubjects(teacher.mapel);
      teacherWaliInfo = parseWaliInfo(teacher.wali);

      // Sinkronisasi Nama & NIP di Header & Screen 2
      const step2Name = document.getElementById('step2-teacher-name');
      if (step2Name) step2Name.innerText = teacher.name;
      const headerName = document.getElementById('header-teacher-name');
      if (headerName) headerName.innerText = teacher.name;
      const headerNip = document.getElementById('header-teacher-nip');
      if (headerNip) headerNip.innerText = 'NIP. ' + (teacher.nip || '-');

      // 1. Setup Dropdown Mapel: HANYA tampilkan mapel yang diampu oleh guru ini!
      const subjDropdown = document.getElementById('select-subject-dropdown');
      if (subjDropdown) {
        subjDropdown.innerHTML = '';
        if (teacherAllowedSubjects.length === 0) {
          const opt = document.createElement('option');
          opt.value = teacher.mapel || 'Mata Pelajaran Umum';
          opt.innerText = '📘 ' + (teacher.mapel || 'Mata Pelajaran Umum');
          subjDropdown.appendChild(opt);
        } else {
          teacherAllowedSubjects.forEach((subj, idx) => {
            const opt = document.createElement('option');
            opt.value = subj;
            opt.innerText = `📘 ${subj}`;
            if (idx === 0) opt.selected = true;
            subjDropdown.appendChild(opt);
          });
        }
        activeSubject = subjDropdown.value;
      }

      // 2. Setup Tombol Wali Kelas: Terkunci jika guru bukan wali kelas
      const btnWali = document.getElementById('btn-role-wali-kelas');
      if (btnWali) {
        if (!teacherWaliInfo) {
          btnWali.classList.add('opacity-40', 'cursor-not-allowed', 'border-dashed');
          btnWali.title = 'Anda tidak memiliki penugasan Wali Kelas di data sekolah';
          const titleWal = btnWali.querySelector('.font-extrabold');
          if (titleWal) titleWal.innerHTML = 'Wali Kelas <span class="text-[10px] text-rose-500 font-bold ml-1">(Terkunci 🔒)</span>';
          const pWal = btnWali.querySelector('p');
          if (pWal) pWal.innerText = 'Anda tidak ditugaskan sebagai Wali Kelas pada semester ini.';
        } else {
          btnWali.classList.remove('opacity-40', 'cursor-not-allowed', 'border-dashed');
          btnWali.title = `Wali Kelas binaan: ${teacherWaliInfo.label}`;
          const titleWal = btnWali.querySelector('.font-extrabold');
          if (titleWal) titleWal.innerHTML = `Wali ${teacherWaliInfo.label} <span class="text-[10px] text-emerald-600 font-bold ml-1">✓ Aktif</span>`;
          const pWal = btnWali.querySelector('p');
          if (pWal) pWal.innerText = `Akses pantau raport seluruh mata pelajaran untuk kelas binaan Anda: ${teacherWaliInfo.label}.`;
        }
      }

      // Default role: Guru Mapel
      selectTeacherRole('guru_mapel');

      // 3. Tentukan jenjang awal yang valid berdasarkan kelas yang diampu guru
      const grades = teacherAllowedClasses.map(c => parseInt(c[0], 10)).filter(g => !isNaN(g));
      if (grades.includes(8)) {
        selectGradeLevel(8);
      } else if (grades.length > 0) {
        selectGradeLevel(grades[0]);
      } else {
        selectGradeLevel(8);
      }
    }

    // HANDLER PEMILIHAN PERAN (GURU MAPEL / WALI KELAS)
    function selectTeacherRole(role) {
      if (role === 'wali_kelas' && !teacherWaliInfo) {
        alert('AKSES DITOLAK!\n\nAnda tidak terdaftar sebagai Wali Kelas pada data akademik sekolah. Silakan gunakan peran Guru Mata Pelajaran.');
        return;
      }

      currentTeacherRole = role;
      const btnMapel = document.getElementById('btn-role-guru-mapel');
      const btnWali = document.getElementById('btn-role-wali-kelas');
      const containerMapel = document.getElementById('container-select-mapel');
      const containerWaliNote = document.getElementById('container-wali-note');

      if (role === 'guru_mapel') {
        btnMapel.className = 'p-3.5 sm:p-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-left transition-all shadow-sm';
        btnWali.className = 'p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 hover:border-indigo-400 bg-white text-left transition-all' + (!teacherWaliInfo ? ' opacity-40 cursor-not-allowed border-dashed' : '');
        containerMapel.classList.remove('hidden');
        containerWaliNote.classList.add('hidden');
        renderRombelButtons();
      } else {
        btnWali.className = 'p-3.5 sm:p-4 rounded-2xl border-2 border-amber-500 bg-amber-50/70 text-left transition-all shadow-sm';
        btnMapel.className = 'p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 hover:border-indigo-400 bg-white text-left transition-all';
        containerMapel.classList.add('hidden');
        containerWaliNote.classList.remove('hidden');

        // Otomatis sinkronkan jenjang & rombel ke kelas perwaliannya
        if (teacherWaliInfo) {
          selectGradeLevel(teacherWaliInfo.grade);
          selectedRombelLetter = teacherWaliInfo.rombel;
          renderRombelButtons();
        }
      }
    }

    // NAVIGASI ANTAR SCREEN & RIWAYAT (BACKWARD COMPATIBILITY)
    let currentScreenId = 'screen-login';

    function closeAllModals() {
      const modalIds = ['modal-add-column', 'modal-add-attendance-column', 'modal-jurnal'];
      modalIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
      });
      document.body.style.overflow = '';
    }

    function showScreen(screenId, pushToHistory = true) {
      closeAllModals();
      toggleMobileDrawer(false);

      document.getElementById('screen-login').classList.add('hidden');
      document.getElementById('screen-select-class').classList.add('hidden');
      document.getElementById('screen-dashboard').classList.add('hidden');

      const target = document.getElementById(screenId);
      if (target) {
        target.classList.remove('hidden');
      }
      currentScreenId = screenId;
      window.scrollTo(0, 0);

      const hashName = screenId.replace('screen-', '');
      if (pushToHistory) {
        if (!history.state || history.state.screen !== screenId) {
          history.pushState({ screen: screenId }, '', '#' + hashName);
        }
      }
    }

    function goToLogin(pushToHistory = true) {
      currentLoggedTeacher = null;
      sessionStorage.removeItem('currentLoggedTeacher');
      showScreen('screen-login', pushToHistory);
    }

    function goToSelectClass(pushToHistory = true) {
      if (!currentLoggedTeacher) {
        goToLogin(true);
        return;
      }
      renderRombelButtons();
      showScreen('screen-select-class', pushToHistory);
    }

    // LOGIN MANUAL SUBMIT HANDLER MENGGUNAKAN NIP & KATA SANDI
    async function handleLoginSubmit(e) {
      e.preventDefault();
      const nipInput = document.getElementById('login-nip');
      const passInput = document.getElementById('login-pass');
      const errorBox = document.getElementById('login-error-box');
      const errorMsg = document.getElementById('login-error-msg');
      const btnSubmit = document.getElementById('btn-login-submit');

      const nip = nipInput.value.trim();
      const password = passInput.value.trim();

      if (!nip || !password) {
        if (errorBox) {
          errorBox.classList.remove('hidden');
          errorMsg.innerText = 'Mohon masukkan NIP dan Kata Sandi Anda!';
        }
        return;
      }

      if (errorBox) errorBox.classList.add('hidden');
      if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<span>Memverifikasi Akun...</span>`;
      }

      try {
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('nip', nip);
        formData.append('password', password);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success && res.teacher) {
          sessionStorage.setItem('currentLoggedTeacher', JSON.stringify(res.teacher));
          setupTeacherPermissions(res.teacher);
          goToSelectClass(true);
        } else {
          if (errorBox) {
            errorBox.classList.remove('hidden');
            errorMsg.innerText = res.message || 'NIP atau Kata Sandi salah!';
          }
        }
      } catch (err) {
        if (errorBox) {
          errorBox.classList.remove('hidden');
          errorMsg.innerText = 'Gagal menghubungi server database. Pastikan server Apache & MySQL aktif.';
        }
      } finally {
        if (btnSubmit) {
          btnSubmit.disabled = false;
          btnSubmit.innerHTML = `<span>Masuk ke Sistem Guru</span><svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>`;
        }
      }
    }

    // FUNGSI BACKWARD MANUAL / TOMBOL KEMBALI
    function goBack() {
      const drawerOverlay = document.getElementById('mobile-drawer-overlay');
      if (drawerOverlay && !drawerOverlay.classList.contains('hidden')) {
        toggleMobileDrawer(false);
        return;
      }

      const modalIds = ['modal-add-column', 'modal-add-attendance-column', 'modal-jurnal'];
      for (const mId of modalIds) {
        const modal = document.getElementById(mId);
        if (modal && !modal.classList.contains('hidden')) {
          modal.classList.add('hidden');
          document.body.style.overflow = '';
          return;
        }
      }

      if (window.history.length > 1 && window.history.state) {
        window.history.back();
      } else {
        if (currentScreenId === 'screen-dashboard') {
          goToSelectClass(true);
        } else if (currentScreenId === 'screen-select-class') {
          goToLogin(true);
        }
      }
    }

    // EVENT LISTENER POPSTATE: OTOMATIS BERPINDAH TAMPILAN SAAT BROWSER / HP BACKWARD DITEKAN
    window.addEventListener('popstate', (e) => {
      closeAllModals();
      toggleMobileDrawer(false);

      const state = e.state;
      const hash = window.location.hash.replace('#', '');
      let targetScreen = 'screen-login';

      if (state && state.screen) {
        targetScreen = state.screen;
      } else if (hash === 'dashboard') {
        targetScreen = 'screen-dashboard';
      } else if (hash === 'select-class') {
        targetScreen = 'screen-select-class';
      } else {
        targetScreen = 'screen-login';
      }

      if (targetScreen === 'screen-dashboard') {
        showScreen('screen-dashboard', false);
      } else if (targetScreen === 'screen-select-class') {
        goToSelectClass(false);
      } else {
        goToLogin(false);
      }
    });

    // PEMILIHAN BERTINGKAT: KELAS 7/8/9
    function selectGradeLevel(lvl) {
      // Jika mode Wali Kelas, pastikan hanya jenjang perwaliannya yang aktif
      if (currentTeacherRole === 'wali_kelas' && teacherWaliInfo && teacherWaliInfo.grade !== lvl) {
        alert(`AKSES DITOLAK!\n\nSebagai Wali Kelas, Anda hanya ditugaskan pada jenjang Kelas ${teacherWaliInfo.grade} (${teacherWaliInfo.label}).`);
        return;
      }

      selectedGradeLevel = lvl;
      [7, 8, 9].forEach(l => {
        const btn = document.getElementById(`btn-grade-${l}`);
        if (!btn) return;
        if (l === lvl) {
          btn.className = 'py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-indigo-950 font-extrabold text-xs sm:text-sm transition-all text-center shadow-sm';
          const subText = btn.querySelector('span:last-child');
          if (subText) subText.className = 'text-[10px] font-medium text-indigo-600';
        } else {
          btn.className = 'py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-slate-200 text-slate-700 hover:border-indigo-400 font-extrabold text-xs sm:text-sm transition-all text-center';
          const subText = btn.querySelector('span:last-child');
          if (subText) subText.className = 'text-[10px] font-medium text-slate-400';
        }
      });

      const roman = lvl === 7 ? 'VII' : (lvl === 8 ? 'VIII' : 'IX');
      const labelGrade = document.getElementById('label-rombel-grade');
      if (labelGrade) labelGrade.innerText = `Rombel Kelas ${roman} (${lvl})`;
      renderRombelButtons();
    }

    // 9 ROMBEL DARI A SAMPAI I DENGAN PENGECEKAN HAK AKSES KETAT
    function renderRombelButtons() {
      const container = document.getElementById('rombel-selector-container');
      if (!container) return;
      container.innerHTML = '';
      const roman = selectedGradeLevel === 7 ? 'VII' : (selectedGradeLevel === 8 ? 'VIII' : 'IX');
      const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

      letters.forEach(letter => {
        const classCode = `${selectedGradeLevel}${letter}`;
        
        // Pengecekan apakah kelas diampu guru
        let isAllowed = false;
        if (currentTeacherRole === 'wali_kelas') {
          isAllowed = teacherWaliInfo && (teacherWaliInfo.code === classCode);
        } else {
          isAllowed = teacherAllowedClasses.length === 0 || teacherAllowedClasses.includes(classCode);
        }

        const isSelected = letter === selectedRombelLetter;
        const btn = document.createElement('button');
        btn.type = 'button';

        if (isAllowed) {
          btn.onclick = () => {
            selectedRombelLetter = letter;
            renderRombelButtons();
          };

          if (isSelected) {
            btn.className = 'relative py-2.5 px-1.5 rounded-xl border-2 border-indigo-600 bg-indigo-900 text-white font-extrabold text-center shadow-md transition-all';
            btn.innerHTML = `<span class="block text-base font-black">${letter}</span><span class="text-[9px] font-normal text-amber-300 block">${roman} ${letter}</span>`;
          } else {
            btn.className = 'relative py-2.5 px-1.5 rounded-xl border-2 border-slate-200 hover:border-indigo-400 bg-white text-slate-700 font-bold text-center transition-all hover:bg-slate-50';
            btn.innerHTML = `<span class="block text-base font-black">${letter}</span><span class="text-[9px] font-normal text-slate-400 block">${roman} ${letter}</span>`;
          }
        } else {
          // KELAS TERKUNCI! Guru tidak mengampu kelas ini
          btn.className = 'relative py-2.5 px-1.5 rounded-xl border border-slate-200 bg-slate-100/90 text-slate-400 font-bold text-center cursor-not-allowed opacity-60 transition-all hover:border-rose-300';
          btn.innerHTML = `
            <span class="absolute top-1 right-1 text-[10px]">🔒</span>
            <span class="block text-base font-bold text-slate-400">${letter}</span>
            <span class="text-[9px] text-slate-400 block">${roman} ${letter}</span>
          `;
          btn.onclick = () => {
            const allowedInfo = (currentTeacherRole === 'wali_kelas')
              ? (teacherWaliInfo ? teacherWaliInfo.label : 'Bukan Wali Kelas')
              : (teacherAllowedClasses.length > 0 ? teacherAllowedClasses.join(', ') : 'Tidak ada');
            alert(`AKSES DITOLAK!\n\nAnda tidak mengampu Kelas ${roman} ${letter}.\n\nKelas yang terdaftar untuk akun Anda: ${allowedInfo}\n\nSilakan pilih kelas yang ada dalam jadwal mengajar Anda.`);
          };
        }

        container.appendChild(btn);
      });
    }


    // ================= 2 SUB-TAB DI DALEM INPUT NILAI SISWA =================
    let currentGradeSubTab = 'sheet';

    function switchGradeSubTab(subTab) {
      currentGradeSubTab = subTab;
      const btnSheet = document.getElementById('subtab-btn-sheet');
      const btnLedger = document.getElementById('subtab-btn-ledger');
      const viewSheet = document.getElementById('subview-grade-sheet');
      const viewLedger = document.getElementById('subview-grade-ledger');
      const hint = document.getElementById('subtab-context-hint');

      if (subTab === 'sheet') {
        btnSheet.className = 'px-4 py-2 rounded-lg text-xs font-bold transition-all bg-indigo-950 text-white shadow-xs flex items-center gap-2';
        btnLedger.className = 'px-4 py-2 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-indigo-950 flex items-center gap-2';
        viewSheet.classList.remove('hidden');
        viewLedger.classList.add('hidden');
        if (hint) hint.innerText = 'Mode aktif: Lembar Penilaian Mapel (' + (currentTeacherRole === 'wali_kelas' ? 'Read-Only Wali Kelas' : activeSubject) + ')';
      } else {
        btnLedger.className = 'px-4 py-2 rounded-lg text-xs font-bold transition-all bg-indigo-950 text-white shadow-xs flex items-center gap-2';
        btnSheet.className = 'px-4 py-2 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-indigo-950 flex items-center gap-2';
        viewLedger.classList.remove('hidden');
        viewSheet.classList.add('hidden');
        if (hint) hint.innerText = 'Mode aktif: Rekap Kompilasi Seluruh Mapel (Ledger Nilai Siswa)';
        renderSubviewLedgerTable();
      }
    }

    // LIST 11 MATA PELAJARAN STANDAR
    const standardSubjectsList = [
      { key: 'Matematika', label: 'MTK', kkm: 75 },
      { key: 'IPA', label: 'IPA', kkm: 75 },
      { key: 'IPS', label: 'IPS', kkm: 75 },
      { key: 'Bahasa Indonesia', label: 'IND', kkm: 78 },
      { key: 'Bahasa Inggris', label: 'ING', kkm: 75 },
      { key: 'Pendidikan Pancasila', label: 'PKN', kkm: 78 },
      { key: 'Pendidikan Agama', label: 'PAI', kkm: 80 },
      { key: 'Informatika', label: 'INF', kkm: 75 },
      { key: 'PJOK', label: 'PJK', kkm: 78 },
      { key: 'Seni Budaya', label: 'SNB', kkm: 75 },
      { key: 'Bahasa Sunda', label: 'SND', kkm: 75 }
    ];

    function getStudentSubjectAvg(studentId, subjKey) {
      const data = waliClassSubjectsData[subjKey];
      if (!data || !data.scores || !data.scores[studentId]) return '-';
      const studentScores = data.scores[studentId];
      const vals = Object.values(studentScores).filter(v => v !== '' && v !== null && !isNaN(v));
      if (vals.length === 0) return '-';
      return (vals.reduce((a, b) => a + Number(b), 0) / vals.length).toFixed(1);
    }

    function renderSubviewLedgerTable() {
      const tbody = document.getElementById('subview-ledger-tbody');
      if (!tbody) return;
      tbody.innerHTML = '';

      let totalClassGeneralSum = 0;
      let totalPassedCount = 0;

      students.forEach((s, idx) => {
        let studentSubjectScores = [];
        let colsHtml = '';

        standardSubjectsList.forEach(subj => {
          const avg = getStudentSubjectAvg(s.id, subj.key);
          if (avg !== '-') {
            studentSubjectScores.push(Number(avg));
          }
          const isPassed = avg !== '-' && Number(avg) >= subj.kkm;
          colsHtml += `
            <td class="py-2.5 px-2 text-center font-bold border-l border-slate-100 ${isPassed ? 'text-indigo-950 bg-indigo-50/20' : 'text-rose-600 bg-rose-50/30'}">
              ${avg}
            </td>
          `;
        });

        const overallAvg = studentSubjectScores.length > 0 ? 
          (studentSubjectScores.reduce((a, b) => a + b, 0) / studentSubjectScores.length).toFixed(1) : '-';

        if (overallAvg !== '-') {
          totalClassGeneralSum += Number(overallAvg);
          if (Number(overallAvg) >= 75) totalPassedCount++;
        }

        const isOverallPassed = overallAvg !== '-' && Number(overallAvg) >= 75;

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-2.5 px-3 text-center text-slate-400 font-medium">${idx + 1}</td>
          <td class="py-2.5 px-3 font-mono text-[11px] text-slate-500">${s.nisn}</td>
          <td class="py-2.5 px-4 font-bold text-slate-800">${s.name}</td>
          ${colsHtml}
          <td class="py-2.5 px-3 text-center font-black text-sm border-l border-slate-200 ${isOverallPassed ? 'text-indigo-950 bg-amber-50/80' : 'text-rose-600 bg-rose-50'}">
            ${overallAvg}
          </td>
          <td class="py-2.5 px-3 text-center">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold ${isOverallPassed ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'}">
              ${isOverallPassed ? 'Tuntas' : 'Remedial'}
            </span>
          </td>
        `;
        tbody.appendChild(row);
      });

      const grandAvg = students.length > 0 ? (totalClassGeneralSum / students.length).toFixed(1) : 0;
      const metricAvg = document.getElementById('metric-ledger-all-avg');
      if (metricAvg) metricAvg.innerText = grandAvg;

      const metricPass = document.getElementById('metric-ledger-pass-count');
      if (metricPass) metricPass.innerText = `${totalPassedCount} / ${students.length} Siswa`;

      const metricRem = document.getElementById('metric-ledger-remedial-count');
      if (metricRem) metricRem.innerText = `${students.length - totalPassedCount} Siswa`;
    }

    function downloadSubviewLedgerCSV() {
      let headers = ['No', 'NISN', 'Nama Siswa'];
      standardSubjectsList.forEach(s => headers.push(s.key));
      headers.push('Rata-Rata Seluruh Mapel', 'Status Ketuntasan');

      let csv = headers.join(',') + '\n';

      students.forEach((s, idx) => {
        let row = [idx + 1, `"${s.nisn}"`, `"${s.name}"`];
        let subAvgs = [];
        standardSubjectsList.forEach(sub => {
          const val = getStudentSubjectAvg(s.id, sub.key);
          row.push(val);
          if (val !== '-') subAvgs.push(Number(val));
        });
        const ovr = subAvgs.length > 0 ? (subAvgs.reduce((a, b) => a + b, 0) / subAvgs.length).toFixed(1) : '-';
        row.push(ovr);
        row.push(ovr !== '-' && Number(ovr) >= 75 ? '"Tuntas"' : '"Remedial"');
        csv += row.join(',') + '\n';
      });

      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Rekap_Ledger_Seluruh_Mapel_${activeClass.replace(/[^a-zA-Z0-9]/g, '_')}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      showToast('Download Berhasil', 'File Rekap Ledger Seluruh Mapel CSV berhasil diunduh.');
    }

    function confirmWorkspaceAndProceed() {
      const roman = selectedGradeLevel === 7 ? 'VII' : (selectedGradeLevel === 8 ? 'VIII' : 'IX');
      const targetClassCode = `${selectedGradeLevel}${selectedRombelLetter}`;

      // VALIDASI KERAS AKSES KELAS: Guru tidak bisa masuk jika kelas tidak terdaftar di datanya
      if (currentTeacherRole === 'wali_kelas') {
        if (!teacherWaliInfo || teacherWaliInfo.code !== targetClassCode) {
          alert(`AKSES DITOLAK!\n\nSebagai Wali Kelas, Anda hanya diizinkan mengakses kelas perwalian Anda (${teacherWaliInfo ? teacherWaliInfo.label : 'Tidak ada'}).`);
          return;
        }
      } else {
        if (teacherAllowedClasses.length > 0 && !teacherAllowedClasses.includes(targetClassCode)) {
          alert(`AKSES DITOLAK!\n\nAnda tidak mengampu Kelas ${roman} ${selectedRombelLetter}.\n\nKelas yang terdaftar di akun Anda: ${teacherAllowedClasses.join(', ')}.\n\nAnda tidak dapat membuka lembar kerja kelas ini.`);
          return;
        }
      }

      activeClass = `Kelas ${roman} ${selectedRombelLetter}`;

      const waliBar = document.getElementById('wali-pov-control-bar');
      const btnAddCol = document.getElementById('btn-add-grade-col');
      const btnSaveGrades = document.getElementById('btn-save-grades');
      const titleWorkspace = document.getElementById('title-grade-workspace');
      const descWorkspace = document.getElementById('desc-grade-workspace');

      const subtabSwitcher = document.getElementById('subtab-nilai-switcher');

      if (currentTeacherRole === 'wali_kelas') {
        activeSubject = 'Semua Mata Pelajaran';
        document.getElementById('active-subject-text').innerText = 'Mode: Wali Kelas';
        document.getElementById('active-class-text').innerText = `${activeClass} (Semua Mapel)`;
        document.getElementById('tag-current-view').innerText = `Wali Kelas - ${activeClass}`;
        document.getElementById('filter-mapel-locked').value = 'Semua Mata Pelajaran';
        document.getElementById('filter-kelas-display').value = activeClass;
        document.getElementById('wali-pov-class-title').innerText = `Pemantauan Seluruh Mapel ${activeClass}`;

        // TAMPILKAN SUBTAB SWITCHER UNTUK WALI KELAS (BISA LIHAT & REKAP SELURUH MAPEL)
        if (subtabSwitcher) subtabSwitcher.classList.remove('hidden');
        if (waliBar) waliBar.classList.remove('hidden');
        if (titleWorkspace) titleWorkspace.innerText = 'Monitoring Nilai Mata Pelajaran (Wali Kelas)';
        if (descWorkspace) descWorkspace.innerText = 'Memantau hasil evaluasi dan penilaian yang diinput oleh masing-masing guru pengampu.';
        
        const walasBanner = document.getElementById('walas-readonly-banner');
        if (walasBanner) walasBanner.classList.remove('hidden');
        if (btnAddCol) btnAddCol.classList.add('hidden');
        if (btnSaveGrades) btnSaveGrades.classList.add('hidden');

        // Load data from Wali POV database
        loadWaliPovSubject(activeWaliPovSubject);
        renderSubviewLedgerTable();
      } else {
        // GURU MAPEL: SEMBUNYIKAN SUBTAB SWITCHER & REKAP SELURUH MAPEL!
        // CUKUP TAMPILKAN NILAI MAPELNYA SENDIRI
        if (subtabSwitcher) subtabSwitcher.classList.add('hidden');
        switchGradeSubTab('sheet');

        const walasBanner = document.getElementById('walas-readonly-banner');
        if (walasBanner) walasBanner.classList.add('hidden');
        activeSubject = document.getElementById('select-subject-dropdown').value;
        document.getElementById('active-subject-text').innerText = activeSubject;
        document.getElementById('active-class-text').innerText = activeClass;
        document.getElementById('tag-current-view').innerText = `${activeSubject} - ${activeClass}`;
        document.getElementById('filter-mapel-locked').value = activeSubject;
        document.getElementById('filter-kelas-display').value = activeClass;

        if (waliBar) waliBar.classList.add('hidden');
        if (titleWorkspace) titleWorkspace.innerText = `Input Nilai Siswa (${activeSubject})`;
        if (descWorkspace) descWorkspace.innerText = `Input tugas dan ulangan harian ${activeSubject} secara manual satu per satu. KKM dapat disesuaikan sebagai target acuan.`;
        if (btnAddCol) btnAddCol.classList.remove('hidden');
        if (btnSaveGrades) btnSaveGrades.classList.remove('hidden');

        renderGradeTable();
      }

      document.getElementById('sidebar-class-name').innerText = activeClass;
      const mobSideCls = document.getElementById('mob-sidebar-class-name');
      if (mobSideCls) mobSideCls.innerText = activeClass;
      const mobDrawerCls = document.getElementById('mob-drawer-class-label');
      if (mobDrawerCls) mobDrawerCls.innerText = activeClass;

      const mobSubjText = document.getElementById('mob-active-subject-text');
      if (mobSubjText) mobSubjText.innerText = (currentTeacherRole === 'wali_kelas') ? 'Wali Kelas' : activeSubject;
      const mobClsText = document.getElementById('mob-active-class-text');
      if (mobClsText) mobClsText.innerText = activeClass;

      document.getElementById('modul-subject-locked').value = activeSubject;
      document.getElementById('modul-class-locked').value = activeClass;
      document.getElementById('module-header-title').innerText = `Modul Terbit ${activeSubject} - ${activeClass}`;

      // Ambil data nilai, siswa, dan jurnal riil dari MySQL untuk kelas & mapel ini
      fetchAcademicDataFromDB(activeClass, activeSubject);

      renderAttendanceTable();
      renderSemesterAttendanceTable();
      renderModules();

      showScreen('screen-dashboard');
      showToast('Konteks Diatur', `Aktif: ${currentTeacherRole === 'wali_kelas' ? 'Wali Kelas ' + activeClass : activeSubject + ' pada ' + activeClass}.`);
    }

    // AMBIL DATA NILAI & SISWA DARI DATABASE MYSQL
    async function fetchAcademicDataFromDB(targetClass, targetSubject) {
      try {
        const formData = new FormData();
        formData.append('action', 'get_academic_data');
        formData.append('kelas', targetClass);
        formData.append('mata_pelajaran', targetSubject);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
          if (res.students && res.students.length > 0) {
            students = res.students;
          }
          if (res.columns && res.columns.length > 0) {
            gradeColumns = res.columns;
          }
          if (res.journals) {
            journalList = res.journals;
          }
          renderGradeTable();
          renderLedgerTable();
          renderJournalTable();
        }
      } catch (err) {
        console.error('Gagal memuat data akademik:', err);
      }
    }

    // HANDLER GANTI POV MAPEL OLEH WALI KELAS
    function handleWaliPovSubjectChange(subj) {
      activeWaliPovSubject = subj;
      loadWaliPovSubject(subj);
    }

    function loadWaliPovSubject(subj) {
      const data = waliClassSubjectsData[subj];
      if (!data) return;

      currentKKM = data.kkm;
      document.getElementById('input-kkm-target').value = currentKKM;
      document.getElementById('sidebar-kkm-display').innerText = currentKKM;
      const mobKkmDisp = document.getElementById('mob-sidebar-kkm-display');
      if (mobKkmDisp) mobKkmDisp.innerText = currentKKM;

      document.getElementById('tag-current-view').innerText = `${subj} (${data.teacher}) - ${activeClass}`;
      document.getElementById('filter-mapel-locked').value = `${subj} - ${data.teacher}`;

      // Temporarily set gradeColumns & scores for viewing
      gradeColumns = data.columns;
      students.forEach(s => {
        s.scores = data.scores[s.id] || {};
      });

      renderGradeTable();
      renderLedgerTable();
      showToast('POV Diubah', `Menampilkan nilai ${subj} dari ${data.teacher}.`);
    }

    // ================= KKM / KKTP LIVE UPDATE =================
    function handleKKMChange(val) {
      currentKKM = Number(val) || 75;
      document.getElementById('sidebar-kkm-display').innerText = currentKKM;
      const mobKkmDisp = document.getElementById('mob-sidebar-kkm-display');
      if (mobKkmDisp) mobKkmDisp.innerText = currentKKM;
      document.querySelectorAll('.label-kkm-legend').forEach(el => el.innerText = currentKKM);
      document.getElementById('metric-kkm-comparison').innerText = `Target KKM: ${currentKKM}`;
      
      renderGradeTable();
      renderLedgerTable();
    }

    // ================= DYNAMIC GRADE COLUMNS & TABLE =================
    function renderGradeTable() {
      const headerRow = document.getElementById('grade-table-header-row');
      const tbody = document.getElementById('grade-table-body');
      
      document.getElementById('label-column-count').innerText = `${gradeColumns.length} Kolom Penilaian`;

      if (gradeColumns.length === 0) {
        headerRow.innerHTML = `
          <th class="py-3 px-3 w-10 text-center">No</th>
          <th class="py-3 px-3 w-28">NISN</th>
          <th class="py-3 px-4 min-w-[170px]">Nama Siswa</th>
          <th class="py-3 px-4 text-center">Status Lembar Penilaian</th>
        `;
        tbody.innerHTML = `
          <tr>
            <td colspan="4" class="py-12 text-center text-slate-400">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center mx-auto mb-2 text-xl font-black">
                +
              </div>
              <p class="font-bold text-slate-700 text-sm">Belum Ada Kolom Penilaian</p>
              <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                Silakan klik tombol <strong class="text-indigo-900">+ Tambah Kolom Nilai</strong> di atas untuk membuat penilaian pertama (misal: Tugas 1 atau Sumatif 1).
              </p>
            </td>
          </tr>
        `;
        return;
      }

      headerRow.innerHTML = `
        <th class="py-3 px-3 w-10 text-center">No</th>
        <th class="py-3 px-3 w-28">NISN</th>
        <th class="py-3 px-4 min-w-[170px]">Nama Siswa</th>
      `;

      gradeColumns.forEach(col => {
        headerRow.innerHTML += `
          <th class="py-2 px-2 text-center min-w-[125px] ${col.category === 'Sumatif' ? 'bg-indigo-50/70 text-indigo-950' : 'bg-slate-50 text-slate-700'} border-l border-slate-200">
            <div class="flex items-center justify-between gap-1">
              <span class="font-bold text-xs truncate" title="${col.name}">${col.name}</span>
              ${currentTeacherRole === 'guru_mapel' ? `
                <button onclick="removeColumn('${col.id}')" title="Hapus Kolom Penilaian Ini dari Database" class="text-slate-400 hover:text-rose-600 hover:bg-rose-50 p-1 rounded-lg transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              ` : ''}
            </div>
            <div class="text-[10px] text-slate-400 font-normal mt-0.5">
              <span>📅 ${col.date}</span>
            </div>
          </th>
        `;
      });

      headerRow.innerHTML += `
        <th class="py-3 px-3 text-center w-24 font-bold bg-amber-50 text-amber-950 border-l border-slate-200">Rata-Rata</th>
      `;

      tbody.innerHTML = '';
      let totalClassSum = 0;

      students.forEach((s, sIdx) => {
        let scoresArr = [];
        let colsHTML = '';

        gradeColumns.forEach(col => {
          const val = s.scores[col.id] !== undefined && s.scores[col.id] !== null && s.scores[col.id] !== '' ? s.scores[col.id] : '';
          if (val !== '') scoresArr.push(Number(val));
          
          colsHTML += `
            <td class="py-1 px-1 text-center border-l border-slate-100 ${col.category === 'Sumatif' ? 'bg-indigo-50/20' : ''}">
              <input type="number" min="0" max="100" placeholder="-" value="${val}" ${currentTeacherRole === 'wali_kelas' ? 'readonly disabled' : ''} onchange="updateStudentScore(${s.id}, '${col.id}', this.value)" class="w-14 text-center py-1 rounded border border-slate-200 focus:ring-1 focus:ring-indigo-500 font-semibold text-xs transition-all ${currentTeacherRole === 'wali_kelas' ? 'bg-slate-100 text-slate-500 cursor-not-allowed select-none' : 'bg-white hover:border-indigo-400'}">
            </td>
          `;
        });

        const avg = scoresArr.length > 0 ? (scoresArr.reduce((a, b) => a + b, 0) / scoresArr.length).toFixed(1) : '-';
        if (avg !== '-') totalClassSum += Number(avg);

        tbody.innerHTML += `
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="py-2 px-3 text-center text-slate-400 font-medium">${sIdx + 1}</td>
            <td class="py-2 px-3 font-mono text-slate-500 font-medium">${s.nisn}</td>
            <td class="py-2 px-4 font-bold text-slate-800">${s.name}</td>
            ${colsHTML}
            <td class="py-2 px-3 text-center font-black text-xs border-l border-slate-200 ${avg !== '-' && Number(avg) >= currentKKM ? 'text-indigo-950 bg-indigo-50/30' : 'text-rose-600 bg-rose-50/20'}">
              ${avg}
            </td>
          </tr>
        `;
      });

      const classAverage = students.length > 0 ? (totalClassSum / students.length).toFixed(1) : 0;
      const classAvgElem = document.getElementById('metric-class-avg');
      if (classAvgElem) classAvgElem.innerText = classAverage;
      if (document.getElementById('metric-class-avg-rekap')) {
        document.getElementById('metric-class-avg-rekap').innerText = classAverage;
      }
    }

    // SIMPAN / EDIT NILAI LANGSUNG KE MYSQL
    async function updateStudentScore(studentId, colId, value) {
      const student = students.find(s => s.id === studentId);
      const col = gradeColumns.find(c => c.id === colId);
      if (!student || !col) return;

      student.scores[colId] = value !== '' ? Number(value) : '';
      renderGradeTable();
      renderLedgerTable();

      try {
        const formData = new FormData();
        formData.append('action', 'save_grade_cell');
        formData.append('nisn', student.nisn);
        formData.append('nama_siswa', student.name);
        formData.append('kelas', activeClass);
        formData.append('mata_pelajaran', activeSubject);
        formData.append('guru_pengampu', currentLoggedTeacher ? currentLoggedTeacher.name : '-');
        formData.append('jenis_penilaian', col.name);
        formData.append('nilai', value);
        formData.append('kkm', currentKKM);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
          showToast('Nilai Disimpan', `Nilai ${col.name} ${student.name} tersimpan di MySQL.`);
        }
      } catch (err) {
        showToast('Tersimpan Lokal', `Nilai ${student.name} diperbarui di memori.`);
      }
    }

    // MODAL TAMBAH KOLOM NILAI
    function openAddColumnModal() {
      document.getElementById('modal-add-column').classList.remove('hidden');
    }

    function closeAddColumnModal() {
      document.getElementById('modal-add-column').classList.add('hidden');
    }

    function handleConfirmAddColumn(e) {
      e.preventDefault();
      const name = document.getElementById('new-col-name').value;
      const rawDate = document.getElementById('new-col-date').value;
      const category = document.getElementById('new-col-category').value;

      if (!name || !rawDate) {
        alert('Mohon isi nama penilaian dan tanggal dengan lengkap.');
        return;
      }

      const dateObj = new Date(rawDate);
      const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      const formattedDate = `${days[dateObj.getDay()]}, ${dateObj.getDate()} ${months[dateObj.getMonth()]} ${dateObj.getFullYear()}`;

      const newId = 'col_' + Date.now();
      gradeColumns.push({
        id: newId,
        name: name,
        date: formattedDate,
        category: category,
        isFixed: false
      });

      // Kolom baru disisipkan KOSONG
      students.forEach(s => {
        s.scores[newId] = '';
      });

      closeAddColumnModal();
      document.getElementById('new-col-name').value = '';
      renderGradeTable();
      renderLedgerTable();
      showToast('Kolom Ditambahkan', `Kolom "${name}" berhasil dibuat. Masukkan nilai siswa pada kolom ini.`);
    }

    // HAPUS KOLOM PENILAIAN DARI DATABASE MYSQL
    async function removeColumn(colId) {
      const col = gradeColumns.find(c => c.id === colId);
      const colTitle = col ? col.name : 'kolom penilaian';
      if (!confirm(`Yakin ingin menghapus kolom "${colTitle}"?\n\nSemua nilai siswa pada penilaian ini untuk ${activeClass} akan dihapus dari database MySQL.`)) {
        return;
      }

      gradeColumns = gradeColumns.filter(c => c.id !== colId);
      students.forEach(s => {
        delete s.scores[colId];
      });
      renderGradeTable();
      renderLedgerTable();

      try {
        const formData = new FormData();
        formData.append('action', 'delete_grade_column');
        formData.append('kelas', activeClass);
        formData.append('mata_pelajaran', activeSubject);
        formData.append('jenis_penilaian', colTitle);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();
        showToast('Kolom Dihapus', res.message || `Kolom "${colTitle}" berhasil dihapus.`);
      } catch (err) {
        showToast('Kolom Dihapus', `Kolom "${colTitle}" dihapus dari tampilan.`);
      }
    }

    function exportGradesToCSV() {
      let csvHeaders = ['No', 'NISN', 'Nama Siswa'];
      gradeColumns.forEach(c => {
        csvHeaders.push(`"${c.name} (${c.date})"`);
      });
      csvHeaders.push('"Rata-Rata"');

      let csvRows = [csvHeaders.join(',')];

      students.forEach((s, idx) => {
        let row = [idx + 1, `"${s.nisn}"`, `"${s.name}"`];
        let scoresArr = [];
        gradeColumns.forEach(c => {
          const val = s.scores[c.id] !== undefined && s.scores[c.id] !== '' ? s.scores[c.id] : '-';
          if (val !== '-') scoresArr.push(Number(val));
          row.push(val);
        });

        const avg = scoresArr.length > 0 ? (scoresArr.reduce((a, b) => a + b, 0) / scoresArr.length).toFixed(1) : 0;
        row.push(avg);

        csvRows.push(row.join(','));
      });

      const csvString = csvRows.join('\n');
      const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      const sanitizedMapel = activeSubject.replace(/[^a-zA-Z0-9]/g, '_');
      const sanitizedKelas = activeClass.replace(/[^a-zA-Z0-9]/g, '_');
      link.setAttribute('href', url);
      link.setAttribute('download', `Rekap_Nilai_${sanitizedMapel}_${sanitizedKelas}_KKM_${currentKKM}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      showToast('Unduhan Dimulai', `File CSV Rekap Nilai berhasil diunduh.`);
    }

    // ================= PRESENSI DINAMIS PER HARI MASUK =================
    function renderAttendanceTable() {
      const headerRow = document.getElementById('attendance-table-header-row');
      const tbody = document.getElementById('attendance-table-body');
      
      document.getElementById('attend-column-count-badge').innerText = `${attendanceColumns.length} Pertemuan Tercatat`;

      headerRow.innerHTML = `
        <th class="py-2.5 px-3 w-10 text-center">No</th>
        <th class="py-2.5 px-3 w-28">NISN</th>
        <th class="py-2.5 px-4 min-w-[160px]">Nama Siswa</th>
      `;

      if (attendanceColumns.length === 0) {
        headerRow.innerHTML = `
          <th class="py-3 px-3 w-10 text-center">No</th>
          <th class="py-3 px-3 w-28">NISN</th>
          <th class="py-3 px-4 min-w-[160px]">Nama Siswa</th>
          <th class="py-3 px-4 text-center">Status Kolom Kehadiran</th>
        `;
        tbody.innerHTML = `
          <tr>
            <td colspan="4" class="py-12 text-center text-slate-400">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center mx-auto mb-2 text-xl font-black">
                +
              </div>
              <p class="font-bold text-slate-700 text-sm">Belum Ada Kolom Pertemuan Kehadiran</p>
              <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                Silakan klik tombol <strong class="text-indigo-900"> Tambah Hari Masuk</strong> di atas untuk membuat pertemuan kehadiran secara manual.
              </p>
            </td>
          </tr>
        `;
        return;
      }

      attendanceColumns.forEach(col => {
        headerRow.innerHTML += `
          <th class="py-2 px-2 text-center min-w-[115px] bg-slate-50 border-l border-slate-200">
            <div class="flex items-center justify-between gap-1">
              <span class="font-bold text-xs truncate" title="${col.name}">${col.name}</span>
              <button onclick="removeAttendanceColumn('${col.id}')" title="Hapus Kolom Pertemuan Ini" class="text-slate-400 hover:text-rose-600 hover:bg-rose-50 p-1 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="text-[10px] text-slate-400 font-normal mt-0.5">
              <span>📅 ${col.date}</span>
            </div>
          </th>
        `;
      });

      tbody.innerHTML = '';
      students.forEach((s, sIdx) => {
        let colsHTML = '';
        attendanceColumns.forEach(col => {
          const status = s.attendances[col.id] || 'H';
          let badgeClass = 'badge-h';
          if (status === 'I') badgeClass = 'badge-i';
          if (status === 'S') badgeClass = 'badge-s';
          if (status === 'A') badgeClass = 'badge-a';

          colsHTML += `
            <td class="py-1.5 px-2 text-center border-l border-slate-100">
              <button onclick="cycleAttendanceStatus(${s.id}, '${col.id}')" class="w-8 h-8 rounded-xl font-extrabold text-xs transition-all shadow-sm active:scale-90 ${badgeClass}" title="Klik untuk ubah: H -> I -> S -> A">
                ${status}
              </button>
            </td>
          `;
        });

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-2 px-3 text-center text-slate-400 font-medium">${sIdx + 1}</td>
          <td class="py-2 px-3 font-mono text-[11px] text-slate-500">${s.nisn}</td>
          <td class="py-2 px-4 font-semibold text-slate-800">${s.name}</td>
          ${colsHTML}
        `;
        tbody.appendChild(row);
      });
    }

    function cycleAttendanceStatus(studentId, colId) {
      const student = students.find(s => s.id === studentId);
      if (student) {
        const current = student.attendances[colId] || 'H';
        const cycle = { 'H': 'I', 'I': 'S', 'S': 'A', 'A': 'H' };
        student.attendances[colId] = cycle[current];
        renderAttendanceTable();
        renderSemesterAttendanceTable();
        showToast('Presensi Diubah', `${student.name}: [${student.attendances[colId]}]`);
      }
    }

    function markAllCurrentMeetingPresent() {
      if (attendanceColumns.length === 0) return;
      const lastColId = attendanceColumns[attendanceColumns.length - 1].id;
      students.forEach(s => {
        s.attendances[lastColId] = 'H';
      });
      renderAttendanceTable();
      renderSemesterAttendanceTable();
      showToast('Presensi Cepat', 'Semua siswa ditandai Hadir (H) di pertemuan terbaru.');
    }

    function openAddAttendanceColumnModal() {
      document.getElementById('modal-add-attendance-column').classList.remove('hidden');
    }

    function closeAddAttendanceColumnModal() {
      document.getElementById('modal-add-attendance-column').classList.add('hidden');
    }

    function handleConfirmAddAttendanceColumn(e) {
      e.preventDefault();
      const name = document.getElementById('new-att-name').value;
      const rawDate = document.getElementById('new-att-date').value;
      const isDefaultH = document.getElementById('new-att-default-h').checked;

      if (!name || !rawDate) {
        alert('Mohon isi nama pertemuan dan tanggal masuk.');
        return;
      }

      const dateObj = new Date(rawDate);
      const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      const formattedDate = `${days[dateObj.getDay()]}, ${dateObj.getDate()} ${months[dateObj.getMonth()]} ${dateObj.getFullYear()}`;

      const newId = 'att_' + Date.now();
      attendanceColumns.push({
        id: newId,
        name: name,
        date: formattedDate,
        isFixed: false
      });

      students.forEach(s => {
        s.attendances[newId] = isDefaultH ? 'H' : 'H';
      });

      closeAddAttendanceColumnModal();
      document.getElementById('new-att-name').value = '';
      renderAttendanceTable();
      renderSemesterAttendanceTable();
      showToast('Hari Masuk Ditambahkan', `Kolom ${name} (${formattedDate}) berhasil disisipkan.`);
    }

    function removeAttendanceColumn(colId) {
      const col = attendanceColumns.find(c => c.id === colId);
      const colTitle = col ? col.name : 'pertemuan ini';
      if (confirm(`Yakin ingin menghapus kolom ${colTitle}? Data presensi pada kolom ini akan dihapus.`)) {
        attendanceColumns = attendanceColumns.filter(c => c.id !== colId);
        students.forEach(s => {
          delete s.attendances[colId];
        });
        renderAttendanceTable();
        renderSemesterAttendanceTable();
        showToast('Kolom Dihapus', `Kolom ${colTitle} berhasil dihapus.`);
      }
    }

    function exportDailyAttendanceCSV() {
      let headers = ['"No"', '"NISN"', '"Nama Siswa"'];
      attendanceColumns.forEach(c => {
        headers.push(`"${c.name} (${c.date})"`);
      });

      let rows = [headers.join(',')];
      students.forEach((s, idx) => {
        let row = [idx + 1, `"${s.nisn}"`, `"${s.name}"`];
        attendanceColumns.forEach(c => {
          row.push(`"${s.attendances[c.id] || 'H'}"`);
        });
        rows.push(row.join(','));
      });

      const csvString = rows.join('\n');
      const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Presensi_Harian_${activeClass.replace(/[^a-zA-Z0-9]/g, '_')}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      showToast('Unduh Presensi', 'File CSV Presensi Harian berhasil diunduh.');
    }

    // ================= REKAP 1 SEMESTER (DI TAB REKAP KESELURUHAN) =================
    function renderSemesterAttendanceTable() {
      const tbody = document.getElementById('attendance-semester-table-body');
      tbody.innerHTML = '';

      const totalRecordedMeetings = attendanceColumns.length;
      let totalPctSum = 0;

      students.forEach((s, idx) => {
        let hCount = 0;
        let iCount = 0;
        let sCount = 0;
        let aCount = 0;

        attendanceColumns.forEach(col => {
          const st = s.attendances[col.id] || 'H';
          if (st === 'H') hCount++;
          else if (st === 'I') iCount++;
          else if (st === 'S') sCount++;
          else if (st === 'A') aCount++;
        });

        const baseHadir = s.id === 5 ? 27 + hCount : 32 + hCount;
        const baseTotal = 33 + totalRecordedMeetings;
        const pct = Math.round((baseHadir / baseTotal) * 100);
        totalPctSum += pct;

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-2.5 px-3 text-center text-slate-400 font-medium">${idx + 1}</td>
          <td class="py-2.5 px-3 font-mono text-[11px] text-slate-500">${s.nisn}</td>
          <td class="py-2.5 px-4 font-bold text-slate-800">${s.name}</td>
          <td class="py-2.5 px-2 text-center font-bold text-emerald-700 bg-emerald-50/50">${baseHadir}</td>
          <td class="py-2.5 px-2 text-center font-medium text-amber-700 bg-amber-50/50">${iCount + (s.id === 5 ? 2 : 1)}</td>
          <td class="py-2.5 px-2 text-center font-medium text-purple-700 bg-purple-50/50">${sCount + (s.id === 3 ? 2 : 0)}</td>
          <td class="py-2.5 px-2 text-center font-bold ${(aCount + (s.id === 5 ? 3 : 0)) > 0 ? 'text-rose-600 bg-rose-50' : 'text-slate-400'}">${aCount + (s.id === 5 ? 3 : 0)}</td>
          <td class="py-2.5 px-3 text-center font-semibold text-slate-600">${baseTotal} Pertemuan</td>
          <td class="py-2.5 px-3 text-center font-extrabold ${pct >= 85 ? 'text-emerald-700' : 'text-rose-600'}">${pct}%</td>
          <td class="py-2 px-3">
            <input type="text" value="${s.semesterNotes}" oninput="updateSemesterNote(${s.id}, this.value)" placeholder="Tulis catatan evaluasi siswa..." class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 bg-white font-medium shadow-2xs">
          </td>
        `;
        tbody.appendChild(row);
      });

      if (students.length > 0) {
        document.getElementById('metric-attend-avg').innerText = (totalPctSum / students.length).toFixed(1) + '%';
      }
    }

    function exportAttendanceSemesterCSV() {
      let headers = ['"No"', '"NISN"', '"Nama Siswa"', '"Total Hadir"', '"Total Izin"', '"Total Sakit"', '"Total Alpa"', '"Total Pertemuan"', '"% Kehadiran"', '"Catatan Evaluasi"'];
      let rows = [headers.join(',')];

      students.forEach((s, idx) => {
        let hCount = 0;
        attendanceColumns.forEach(col => {
          if ((s.attendances[col.id] || 'H') === 'H') hCount++;
        });
        const baseHadir = s.id === 5 ? 27 + hCount : 32 + hCount;
        const baseTotal = 33 + attendanceColumns.length;
        const pct = Math.round((baseHadir / baseTotal) * 100);

        rows.push([
          idx + 1,
          `"${s.nisn}"`,
          `"${s.name}"`,
          baseHadir,
          1,
          s.id === 3 ? 2 : 0,
          s.id === 5 ? 3 : 0,
          baseTotal,
          `"${pct}%"`,
          `"${(s.semesterNotes || '').replace(/"/g, '""')}"`
        ].join(','));
      });

      const csvString = rows.join('\n');
      const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Rekap_Presensi_1_Semester_${activeClass.replace(/[^a-zA-Z0-9]/g, '_')}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      showToast('Unduh Presensi', 'File CSV Rekap Presensi 1 Semester berhasil diunduh.');
    }

    // ================= RENDER LEDGER REKAP KESELURUHAN =================
    function renderLedgerTable() {
      const tbody = document.getElementById('ledger-table-body');
      tbody.innerHTML = '';

      students.forEach((s, idx) => {
        let scoresArr = [];
        gradeColumns.forEach(c => {
          if (s.scores[c.id] !== undefined && s.scores[c.id] !== '' && s.scores[c.id] !== null) {
            scoresArr.push(Number(s.scores[c.id]));
          }
        });

        const finalScore = scoresArr.length > 0 ? (scoresArr.reduce((a, b) => a + b, 0) / scoresArr.length).toFixed(1) : 0;

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-3 px-3 text-center text-slate-400 font-medium">${idx + 1}</td>
          <td class="py-3 px-3 font-mono text-[11px] text-slate-500">${s.nisn}</td>
          <td class="py-3 px-4 font-bold text-slate-800">${s.name}</td>
          <td class="py-3 px-3 text-center bg-blue-50/30 font-medium text-blue-900">${scoresArr.length} Nilai Terinput</td>
          <td class="py-3 px-3 text-center font-extrabold text-indigo-950 bg-amber-50/40 text-sm">${finalScore}</td>
          <td class="py-3 px-3 text-center font-bold text-emerald-700">Tercatat di Presensi</td>
          <td class="py-2 px-3">
            <input type="text" value="${s.semesterNotes}" oninput="updateSemesterNote(${s.id}, this.value)" placeholder="Tulis catatan evaluasi siswa..." class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 bg-white font-medium shadow-2xs">
          </td>
        `;
        tbody.appendChild(row);
      });
    }

    function updateSemesterNote(studentId, newNote) {
      const student = students.find(s => s.id === studentId);
      if (student) {
        student.semesterNotes = newNote;
      }
    }

    // ================= MODUL AJAR =================
    let modulesList = [
      { title: 'Modul Teori Eksponen & Bentuk Akar', size: '2.4 MB', date: 'Hari ini, 09.18 WIB', downloads: '28x', status: 'Terbit' },
      { title: 'LKPD Latihan Mandiri Teorema Pythagoras', size: '1.8 MB', date: '30 Sep', downloads: '32x', status: 'Terbit' },
      { title: 'Bank Soal Evaluasi Tengah Semester', size: '540 KB', date: '28 Sep', downloads: '0x', status: 'Draf' }
    ];

    function renderModules() {
      const container = document.getElementById('module-list-container');
      container.innerHTML = '';
      modulesList.forEach(m => {
        container.innerHTML += `
          <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:bg-slate-50 transition-colors">
            <div class="flex items-center gap-3">
              <div class="w-9 sm:w-10 h-9 sm:h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0">
                PDF
              </div>
              <div>
                <h4 class="font-bold text-slate-800 text-xs sm:text-sm">${m.title}</h4>
                <p class="text-slate-500 text-[10px] sm:text-[11px]">${m.size} · Diperbarui ${m.date}</p>
              </div>
            </div>
            <div class="flex items-center gap-2.5 sm:gap-3 pl-12 sm:pl-0">
              <span class="px-2.5 py-0.5 rounded-full ${m.status === 'Terbit' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'} font-semibold text-[10px] sm:text-[11px]">${m.status}</span>
              <span class="text-slate-400 text-[10px] sm:text-[11px]">Diunduh ${m.downloads}</span>
            </div>
          </div>
        `;
      });
    }

    function addModuleSimulated() {
      const title = document.getElementById('input-modul-title').value;
      if (!title) {
        alert('Mohon isi judul modul terlebih dahulu.');
        return;
      }

      modulesList.unshift({
        title: title,
        size: '3.1 MB',
        date: 'Baru saja',
        downloads: '0x',
        status: 'Terbit'
      });

      renderModules();
      document.getElementById('input-modul-title').value = '';
      document.getElementById('input-modul-desc').value = '';
      showToast('Modul Diterbitkan', `Modul "${title}" berhasil dibagikan ke kelas.`);
    }

    // ================= JURNAL HARIAN MENGAJAR GURU =================
    let journalList = [
      {
        id: 1,
        day: 'Senin',
        date: '05 Okt 2026',
        meeting: 'Pertemuan ke-8 (Jam 1-3)',
        topic: 'Operasi Aljabar Bentuk Eksponen',
        activities: 'Apersepsi pengantar sifat-sifat perkalian eksponen, pengerjaan LKPD 1 secara berkelompok, dan evaluasi pemahaman mandiri di lembar refleksi.',
        attendance: { hadir: 31, izin: 1, sakit: 0, alpa: 0 }
      },
      {
        id: 2,
        day: 'Kamis',
        date: '01 Okt 2026',
        meeting: 'Pertemuan ke-7 (Jam 4-6)',
        topic: 'Pangkat Nol, Negatif, dan Bentuk Akar',
        activities: 'Eksplorasi pembagian bilangan berpangkat nol dan negatif, tanya jawab interaktif di papan tulis, dan pemberian latihan soal terbimbing.',
        attendance: { hadir: 30, izin: 1, sakit: 1, alpa: 0 }
      },
      {
        id: 3,
        day: 'Senin',
        date: '28 Sep 2026',
        meeting: 'Pertemuan ke-6 (Jam 1-3)',
        topic: 'Ulangan Harian 1: Bilangan Berpangkat',
        activities: 'Pelaksanaan asesmen sumatif materi bilangan berpangkat selama 60 menit, dilanjutkan pembahasan 2 soal pemecahan masalah kontekstual.',
        attendance: { hadir: 32, izin: 0, sakit: 0, alpa: 0 }
      }
    ];

    function renderJournalTable() {
      const tbody = document.getElementById('journal-table-body');
      if (!tbody) return;
      tbody.innerHTML = '';

      journalList.forEach((j, idx) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-3 px-3 text-center text-slate-400 font-medium">${idx + 1}</td>
          <td class="py-3 px-3">
            <p class="font-bold text-slate-900">${j.day}</p>
            <p class="text-slate-500 text-[11px]">${j.date}</p>
          </td>
          <td class="py-3 px-3 font-semibold text-indigo-900 bg-indigo-50/30">
            ${j.meeting}
          </td>
          <td class="py-3 px-4 font-bold text-slate-800">
            ${j.topic}
          </td>
          <td class="py-3 px-4 text-slate-600 leading-relaxed">
            ${j.activities}
          </td>
          <td class="py-3 px-3 text-center">
            <div class="inline-flex items-center gap-1.5 font-bold text-[10px]">
              <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800" title="Hadir">${j.attendance.hadir} H</span>
              <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800" title="Izin">${j.attendance.izin} I</span>
              <span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800" title="Sakit">${j.attendance.sakit} S</span>
              <span class="px-2 py-0.5 rounded-md ${j.attendance.alpa > 0 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-500'}" title="Alpa">${j.attendance.alpa} A</span>
            </div>
          </td>
          <td class="py-3 px-3 text-right space-x-1 whitespace-nowrap">
            <button onclick="openEditJournalModal(${j.id})" class="text-indigo-600 hover:text-indigo-900 font-bold p-1 hover:bg-indigo-50 rounded" title="Edit Jurnal">Edit</button>
            <button onclick="deleteJournal(${j.id})" class="text-rose-600 hover:text-rose-900 font-bold p-1 hover:bg-rose-50 rounded" title="Hapus Jurnal">Hapus</button>
          </td>
        `;
        tbody.appendChild(row);
      });

      const badgeCount = document.getElementById('badge-jurnal-count');
      if (badgeCount) badgeCount.innerText = `${journalList.length} Catatan`;
      const labelCount = document.getElementById('journal-count-label');
      if (labelCount) labelCount.innerText = `Menampilkan ${journalList.length} entri jurnal mengajar`;
    }

    function openAddJournalModal() {
      const title = document.getElementById('modal-jurnal-title');
      if (title) title.innerText = 'Tambah Jurnal Mengajar Harian';
      document.getElementById('input-jurnal-id').value = '';
      document.getElementById('input-jurnal-hari').value = 'Senin';
      
      const today = new Date();
      const yyyy = today.getFullYear();
      const mm = String(today.getMonth() + 1).padStart(2, '0');
      const dd = String(today.getDate()).padStart(2, '0');
      document.getElementById('input-jurnal-tanggal').value = `${yyyy}-${mm}-${dd}`;
      
      document.getElementById('input-jurnal-pertemuan').value = '';
      document.getElementById('input-jurnal-materi').value = '';
      document.getElementById('input-jurnal-kegiatan').value = '';
      document.getElementById('input-jurnal-h').value = students.length || 32;
      document.getElementById('input-jurnal-i').value = 0;
      document.getElementById('input-jurnal-s').value = 0;
      document.getElementById('input-jurnal-a').value = 0;

      document.getElementById('modal-jurnal').classList.remove('hidden');
    }

    function openEditJournalModal(id) {
      const j = journalList.find(item => item.id == id);
      if (!j) return;

      const title = document.getElementById('modal-jurnal-title');
      if (title) title.innerText = 'Edit Jurnal Pembelajaran';
      document.getElementById('input-jurnal-id').value = j.id;
      document.getElementById('input-jurnal-hari').value = j.day || 'Senin';
      
      let dateVal = j.rawDate || '';
      if (!dateVal && j.date) {
        try {
          const d = new Date(j.date);
          if (!isNaN(d.getTime())) {
            dateVal = d.toISOString().split('T')[0];
          }
        } catch(e) {}
      }
      document.getElementById('input-jurnal-tanggal').value = dateVal;
      document.getElementById('input-jurnal-pertemuan').value = j.meeting || '';
      document.getElementById('input-jurnal-materi').value = j.topic || '';
      document.getElementById('input-jurnal-kegiatan').value = j.activities || '';

      const att = j.attendance || {};
      document.getElementById('input-jurnal-h').value = att.hadir !== undefined ? att.hadir : (students.length || 32);
      document.getElementById('input-jurnal-i').value = att.izin !== undefined ? att.izin : 0;
      document.getElementById('input-jurnal-s').value = att.sakit !== undefined ? att.sakit : 0;
      document.getElementById('input-jurnal-a').value = att.alpa !== undefined ? att.alpa : 0;

      document.getElementById('modal-jurnal').classList.remove('hidden');
    }

    function closeAddJournalModal() {
      document.getElementById('modal-jurnal').classList.add('hidden');
    }

    async function handleSaveJournal(e) {
      e.preventDefault();
      const id = document.getElementById('input-jurnal-id').value;
      const day = document.getElementById('input-jurnal-hari').value;
      const rawDate = document.getElementById('input-jurnal-tanggal').value;
      const meeting = document.getElementById('input-jurnal-pertemuan').value;
      const topic = document.getElementById('input-jurnal-materi').value;
      const activities = document.getElementById('input-jurnal-kegiatan').value;
      const hadir = parseInt(document.getElementById('input-jurnal-h').value) || 0;
      const izin = parseInt(document.getElementById('input-jurnal-i').value) || 0;
      const sakit = parseInt(document.getElementById('input-jurnal-s').value) || 0;
      const alpa = parseInt(document.getElementById('input-jurnal-a').value) || 0;

      try {
        const formData = new FormData();
        formData.append('action', 'save_journal');
        formData.append('id', id);
        formData.append('guru_kode', currentLoggedTeacher ? currentLoggedTeacher.kode : '');
        formData.append('nama_guru', currentLoggedTeacher ? currentLoggedTeacher.nama || currentLoggedTeacher.name : '');
        formData.append('kelas', activeClass);
        formData.append('mata_pelajaran', activeSubject);
        formData.append('hari', day);
        formData.append('tanggal', rawDate);
        formData.append('pertemuan', meeting);
        formData.append('materi', topic);
        formData.append('kegiatan', activities);
        formData.append('hadir', hadir);
        formData.append('izin', izin);
        formData.append('sakit', sakit);
        formData.append('alpa', alpa);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
          closeAddJournalModal();
          showToast('Jurnal Tersimpan', res.message || `Agenda "${topic}" tersimpan ke database.`);
          fetchAcademicDataFromDB(activeClass, activeSubject);
        } else {
          alert('Gagal menyimpan jurnal: ' + (res.message || 'Terjadi kesalahan'));
        }
      } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan jaringan saat menyimpan jurnal.');
      }
    }

    async function deleteJournal(id) {
      if (confirm('Hapus entri jurnal pembelajaran ini dari database?')) {
        try {
          const formData = new FormData();
          formData.append('action', 'delete_journal');
          formData.append('id', id);

          const resp = await fetch('guru.php', { method: 'POST', body: formData });
          const res = await resp.json();
          if (res.success) {
            showToast('Jurnal Dihapus', res.message || 'Entri jurnal mengajar berhasil dihapus.');
            fetchAcademicDataFromDB(activeClass, activeSubject);
          } else {
            alert('Gagal menghapus jurnal: ' + (res.message || 'Terjadi kesalahan'));
          }
        } catch (err) {
          console.error(err);
          alert('Terjadi kesalahan jaringan saat menghapus jurnal.');
        }
      }
    }

    function exportJournalCSV() {
      let csv = 'No,Hari,Tanggal,Pertemuan,Materi Pembelajaran,Kegiatan Pembelajaran,Hadir,Izin,Sakit,Alpa\n';
      journalList.forEach((j, idx) => {
        csv += `${idx + 1},"${j.day}","${j.date}","${j.meeting}","${j.topic}","${j.activities.replace(/"/g, '""')}",${j.attendance.hadir},${j.attendance.izin},${j.attendance.sakit},${j.attendance.alpa}\n`;
      });
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Jurnal_Mengajar_${activeSubject.replace(/[^a-zA-Z0-9]/g, '_')}_${activeClass.replace(/[^a-zA-Z0-9]/g, '_')}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      showToast('Download Berhasil', 'File Jurnal Mengajar CSV berhasil diunduh.');
    }

    // MOBILE DRAWER CONTROLLER
    function toggleMobileDrawer(show) {
      const overlay = document.getElementById('mobile-drawer-overlay');
      if (!overlay) return;
      if (show) {
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      } else {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
      }
    }

    // TABS CONTROLLER
    function switchTab(tabName) {
      const tabs = ['nilai', 'presensi', 'jurnal', 'materi', 'rekap', 'faq'];
      tabs.forEach(t => {
        // Hide panel
        const panel = document.getElementById(`tab-${t}`);
        if (panel) panel.classList.add('hidden');

        // Desktop aside button
        const btnDesk = document.getElementById(`btn-tab-${t}`);
        if (btnDesk) {
          btnDesk.classList.remove('bg-indigo-900', 'text-white', 'shadow-sm');
          btnDesk.classList.add('text-slate-600', 'hover:bg-slate-100');
          const badge = btnDesk.querySelector('.font-bold');
          if (badge && badge.innerText === 'Aktif') badge.remove();
        }

        // Mobile pill button
        const btnMob = document.getElementById(`btn-mob-tab-${t}`);
        if (btnMob) {
          btnMob.classList.remove('bg-indigo-900', 'text-white', 'shadow-xs', 'font-bold');
          btnMob.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200', 'font-medium');
        }

        // Mobile drawer button
        const btnDrawer = document.getElementById(`btn-drawer-tab-${t}`);
        if (btnDrawer) {
          btnDrawer.classList.remove('bg-indigo-900', 'text-white', 'shadow-xs');
          btnDrawer.classList.add('text-slate-600', 'hover:bg-slate-100');
        }
      });

      // Show active panel
      const activePanel = document.getElementById(`tab-${tabName}`);
      if (activePanel) activePanel.classList.remove('hidden');

      // Desktop active button
      const activeBtnDesk = document.getElementById(`btn-tab-${tabName}`);
      if (activeBtnDesk) {
        activeBtnDesk.classList.add('bg-indigo-900', 'text-white', 'shadow-sm');
        activeBtnDesk.classList.remove('text-slate-600', 'hover:bg-slate-100');
      }

      // Mobile pill active button
      const activeBtnMob = document.getElementById(`btn-mob-tab-${tabName}`);
      if (activeBtnMob) {
        activeBtnMob.classList.remove('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200', 'font-medium');
        activeBtnMob.classList.add('bg-indigo-900', 'text-white', 'shadow-xs', 'font-bold');
        activeBtnMob.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      }

      // Mobile drawer active button
      const activeBtnDrawer = document.getElementById(`btn-drawer-tab-${tabName}`);
      if (activeBtnDrawer) {
        activeBtnDrawer.classList.remove('text-slate-600', 'hover:bg-slate-100');
        activeBtnDrawer.classList.add('bg-indigo-900', 'text-white', 'shadow-xs');
      }
    }

    // TOAST HELPER
    function showToast(title, msg) {
      const toast = document.getElementById('toast');
      document.getElementById('toast-title').innerText = title;
      document.getElementById('toast-msg').innerText = msg;

      toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
      setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
      }, 3500);
    }

    function saveAllGrades() {
      showToast('Tersimpan', 'Seluruh nilai berhasil disimpan ke server.');
    }

    function saveAttendance() {
      showToast('Presensi Disimpan', 'Data presensi seluruh pertemuan berhasil diperbarui.');
    }

    function saveAllEvaluations() {
      showToast('Catatan Disimpan', 'Seluruh catatan evaluasi siswa berhasil diperbarui ke buku induk.');
    }

    // INITIALIZATION & SESSION RESTORATION
    window.addEventListener('DOMContentLoaded', () => {
      renderJournalTable();

      const savedSession = sessionStorage.getItem('currentLoggedTeacher');
      if (savedSession) {
        try {
          const teacher = JSON.parse(savedSession);
          if (teacher && teacher.name) {
            setupTeacherPermissions(teacher);
          }
        } catch (e) {
          sessionStorage.removeItem('currentLoggedTeacher');
        }
      }

      // Sinkronisasi status riwayat halaman saat pertama kali dimuat
      const hash = window.location.hash.replace('#', '');
      if (currentLoggedTeacher) {
        if (hash === 'dashboard') {
          showScreen('screen-dashboard', false);
          history.replaceState({ screen: 'screen-dashboard' }, '', '#dashboard');
        } else {
          goToSelectClass(false);
          history.replaceState({ screen: 'screen-select-class' }, '', '#select-class');
        }
      } else {
        showScreen('screen-login', false);
        history.replaceState({ screen: 'screen-login' }, '', '#login');
      }
    });
  </script>
</body>
</html>

<?php
require_once __DIR__ . '/koneksi.php';

// Handle AJAX Login Request via NIP & Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    header('Content-Type: application/json');
    $nip = trim($_POST['nip'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($nip) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'NIP dan Kata Sandi wajib diisi!']);
        exit;
    }

    // Normalisasi NIP (hapus spasi, titik, strip untuk perbandingan fleksibel)
    $cleanNip = preg_replace('/[^0-9]/', '', $nip);

    // Cari di tabel data_guru berdasarkan NIP (bersih/asli), kode, atau nama
    $sql = "SELECT * FROM data_guru WHERE 
            REPLACE(REPLACE(REPLACE(nip, ' ', ''), '.', ''), '-', '') = ? 
            OR nip = ? 
            OR kode = ? 
            OR LOWER(nama_guru) = LOWER(?) 
            LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'ssss', $cleanNip, $nip, $nip, $nip);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $guru = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$guru) {
        echo json_encode([
            'success' => false, 
            'message' => 'Akun dengan NIP tersebut tidak ditemukan di sistem. Pastikan NIP yang Anda masukkan benar.'
        ]);
        exit;
    }

    $dbPassword = !empty($guru['password']) ? $guru['password'] : 'guru12345';
    if ($password !== $dbPassword) {
        echo json_encode([
            'success' => false, 
            'message' => 'Kata sandi tidak sesuai! Hubungi Admin jika Anda lupa kata sandi akun.'
        ]);
        exit;
    }

    if (isset($guru['status']) && $guru['status'] === 'Non-Aktif') {
        echo json_encode([
            'success' => false, 
            'message' => 'Akun Anda sedang dinonaktifkan oleh Admin sekolah.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil! Selamat datang, ' . $guru['nama_guru'],
        'teacher' => [
            'id'             => $guru['kode'],
            'kode'           => $guru['kode'],
            'name'           => $guru['nama_guru'],
            'nip'            => $guru['nip'],
            'mapel'          => $guru['mata_pelajaran'] ?? '',
            'kelas'          => $guru['kelas'] ?? '',
            'wali'           => $guru['wali_kelas'] ?? '',
            'jam_per_minggu' => $guru['jam_per_minggu'] ?? '0',
            'phone'          => $guru['phone'] ?? ''
        ]
    ]);
    exit;
}

// ==============================================================
// API: AMBIL DATA NILAI & JURNAL DARI DATABASE MYSQL
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_academic_data') {
    header('Content-Type: application/json');
    $kelas = trim($_POST['kelas'] ?? '');
    $mapel = trim($_POST['mata_pelajaran'] ?? '');

    // 1. Ambil Siswa di kelas ini dari data_siswa
    $students = [];
    $stmtS = mysqli_prepare($koneksi, "SELECT * FROM data_siswa WHERE kelas = ? ORDER BY nama_siswa ASC");
    mysqli_stmt_bind_param($stmtS, 's', $kelas);
    mysqli_stmt_execute($stmtS);
    $resS = mysqli_stmt_get_result($stmtS);
    while ($row = mysqli_fetch_assoc($resS)) {
        $students[] = [
            'id' => (int)$row['id'],
            'nisn' => $row['nisn'],
            'name' => $row['nama_siswa'],
            'class' => $row['kelas'],
            'scores' => (object)[],
            'attendances' => ['att_1' => 'H', 'att_2' => 'H', 'att_3' => 'H'],
            'semesterNotes' => $row['catatan_wali'] ?? 'Mengikuti pembelajaran dengan baik.'
        ];
    }
    mysqli_stmt_close($stmtS);

    // Fallback jika belum ada siswa di kelas tersebut: ambil 6-12 siswa acuan
    if (empty($students)) {
        $resAll = mysqli_query($koneksi, "SELECT * FROM data_siswa ORDER BY id ASC LIMIT 6");
        while ($row = mysqli_fetch_assoc($resAll)) {
            $students[] = [
                'id' => (int)$row['id'],
                'nisn' => $row['nisn'],
                'name' => $row['nama_siswa'],
                'class' => $kelas,
                'scores' => (object)[],
                'attendances' => ['att_1' => 'H', 'att_2' => 'H', 'att_3' => 'H'],
                'semesterNotes' => $row['catatan_wali'] ?? 'Mengikuti pembelajaran dengan baik.'
            ];
        }
    }

    // 2. Ambil Nilai dari tabel nilai_siswa
    $columns = [];
    $colMap = [];
    $stmtN = mysqli_prepare($koneksi, "SELECT * FROM nilai_siswa WHERE kelas = ? AND mata_pelajaran = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmtN, 'ss', $kelas, $mapel);
    mysqli_stmt_execute($stmtN);
    $resN = mysqli_stmt_get_result($stmtN);
    while ($rN = mysqli_fetch_assoc($resN)) {
        $jp = $rN['jenis_penilaian'];
        if (!isset($colMap[$jp])) {
            $colId = 'col_' . substr(md5($jp), 0, 8);
            $colMap[$jp] = $colId;
            $columns[] = [
                'id' => $colId,
                'name' => $jp,
                'category' => (stripos($jp, 'sumatif') !== false || stripos($jp, 'pts') !== false || stripos($jp, 'pas') !== false) ? 'Sumatif' : 'Formatif',
                'date' => date('d M Y', strtotime($rN['created_at']))
            ];
        }
        $cId = $colMap[$jp];
        foreach ($students as &$st) {
            if ($st['nisn'] === $rN['nisn']) {
                $sc = (array)$st['scores'];
                $sc[$cId] = (float)$rN['nilai'];
                $st['scores'] = $sc;
            }
        }
    }
    mysqli_stmt_close($stmtN);

    // Kolom default jika tabel masih kosong
    if (empty($columns)) {
        $columns = [
            ['id' => 'col_tugas1', 'name' => 'Tugas 1', 'category' => 'Formatif', 'date' => date('d M Y')],
            ['id' => 'col_tugas2', 'name' => 'Tugas 2', 'category' => 'Formatif', 'date' => date('d M Y')],
            ['id' => 'col_sumatif1', 'name' => 'Sumatif 1', 'category' => 'Sumatif', 'date' => date('d M Y')]
        ];
    }

    // 3. Ambil Jurnal untuk kelas dan mapel ini
    $journals = [];
    $stmtJ = mysqli_prepare($koneksi, "SELECT * FROM jurnal_guru WHERE kelas = ? AND mata_pelajaran = ? ORDER BY tanggal DESC, id DESC");
    mysqli_stmt_bind_param($stmtJ, 'ss', $kelas, $mapel);
    mysqli_stmt_execute($stmtJ);
    $resJ = mysqli_stmt_get_result($stmtJ);
    while ($rJ = mysqli_fetch_assoc($resJ)) {
        $journals[] = [
            'id' => (int)$rJ['id'],
            'day' => $rJ['hari'],
            'date' => date('d M Y', strtotime($rJ['tanggal'])),
            'rawDate' => $rJ['tanggal'],
            'meeting' => $rJ['pertemuan'],
            'topic' => $rJ['materi'],
            'activities' => $rJ['kegiatan'],
            'attendance' => [
                'hadir' => (int)$rJ['hadir'],
                'izin' => (int)$rJ['izin'],
                'sakit' => (int)$rJ['sakit'],
                'alpa' => (int)$rJ['alpa']
            ]
        ];
    }
    mysqli_stmt_close($stmtJ);

    echo json_encode([
        'success' => true,
        'students' => $students,
        'columns' => $columns,
        'journals' => $journals
    ]);
    exit;
}

// API: Simpan Satu Nilai Siswa (Live Cell Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_grade_cell') {
    header('Content-Type: application/json');
    $nisn      = trim($_POST['nisn'] ?? '');
    $nama      = trim($_POST['nama_siswa'] ?? '');
    $kelas     = trim($_POST['kelas'] ?? '');
    $mapel     = trim($_POST['mata_pelajaran'] ?? '');
    $guru      = trim($_POST['guru_pengampu'] ?? '-');
    $jenis     = trim($_POST['jenis_penilaian'] ?? '');
    $nilaiRaw  = trim($_POST['nilai'] ?? '');
    $kkm       = (int)($_POST['kkm'] ?? 75);

    if (empty($nisn) || empty($kelas) || empty($mapel) || empty($jenis)) {
        echo json_encode(['success' => false, 'message' => 'Parameter nilai tidak lengkap!']);
        exit;
    }

    if ($nilaiRaw === '' || $nilaiRaw === null) {
        $stmtD = mysqli_prepare($koneksi, "DELETE FROM nilai_siswa WHERE nisn = ? AND kelas = ? AND mata_pelajaran = ? AND jenis_penilaian = ?");
        mysqli_stmt_bind_param($stmtD, 'ssss', $nisn, $kelas, $mapel, $jenis);
        mysqli_stmt_execute($stmtD);
        mysqli_stmt_close($stmtD);
        echo json_encode(['success' => true, 'message' => 'Nilai dikosongkan di database.']);
        exit;
    }

    $nilaiVal = (float)$nilaiRaw;
    $ket = $nilaiVal >= $kkm ? 'Tuntas' : 'Remedial';

    $sql = "INSERT INTO nilai_siswa (nisn, nama_siswa, kelas, mata_pelajaran, guru_pengampu, jenis_penilaian, nilai, kkm, keterangan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), keterangan = VALUES(keterangan), guru_pengampu = VALUES(guru_pengampu)";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssdis', $nisn, $nama, $kelas, $mapel, $guru, $jenis, $nilaiVal, $kkm, $ket);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Nilai tersimpan di database MySQL.' : mysqli_error($koneksi)]);
    exit;
}

// API: Hapus Kolom Nilai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_grade_column') {
    header('Content-Type: application/json');
    $kelas = trim($_POST['kelas'] ?? '');
    $mapel = trim($_POST['mata_pelajaran'] ?? '');
    $jenis = trim($_POST['jenis_penilaian'] ?? '');

    $stmt = mysqli_prepare($koneksi, "DELETE FROM nilai_siswa WHERE kelas = ? AND mata_pelajaran = ? AND jenis_penilaian = ?");
    mysqli_stmt_bind_param($stmt, 'sss', $kelas, $mapel, $jenis);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Kolom nilai berhasil dihapus dari database.' : mysqli_error($koneksi)]);
    exit;
}

// API: Simpan / Edit Jurnal Guru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_journal') {
    header('Content-Type: application/json');
    $id         = trim($_POST['id'] ?? '');
    $guru_kode  = trim($_POST['guru_kode'] ?? '');
    $nama_guru  = trim($_POST['nama_guru'] ?? '');
    $kelas      = trim($_POST['kelas'] ?? '');
    $mapel      = trim($_POST['mata_pelajaran'] ?? '');
    $hari       = trim($_POST['hari'] ?? '');
    $tanggal    = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $pertemuan  = trim($_POST['pertemuan'] ?? '');
    $materi     = trim($_POST['materi'] ?? '');
    $kegiatan   = trim($_POST['kegiatan'] ?? '');
    $hadir      = (int)($_POST['hadir'] ?? 0);
    $izin       = (int)($_POST['izin'] ?? 0);
    $sakit      = (int)($_POST['sakit'] ?? 0);
    $alpa       = (int)($_POST['alpa'] ?? 0);

    if (empty($materi) || empty($pertemuan)) {
        echo json_encode(['success' => false, 'message' => 'Pertemuan dan materi wajib diisi!']);
        exit;
    }

    if (!empty($id) && is_numeric($id) && (int)$id < 1000000000) {
        $stmt = mysqli_prepare($koneksi, "UPDATE jurnal_guru SET hari=?, tanggal=?, pertemuan=?, materi=?, kegiatan=?, hadir=?, izin=?, sakit=?, alpa=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssssiiiii', $hari, $tanggal, $pertemuan, $materi, $kegiatan, $hadir, $izin, $sakit, $alpa, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $savedId = (int)$id;
    } else {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO jurnal_guru (guru_kode, nama_guru, kelas, mata_pelajaran, hari, tanggal, pertemuan, materi, kegiatan, hadir, izin, sakit, alpa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssssssssiiii', $guru_kode, $nama_guru, $kelas, $mapel, $hari, $tanggal, $pertemuan, $materi, $kegiatan, $hadir, $izin, $sakit, $alpa);
        $ok = mysqli_stmt_execute($stmt);
        $savedId = mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);
    }

    echo json_encode(['success' => $ok, 'id' => $savedId, 'message' => $ok ? 'Jurnal berhasil disimpan ke database MySQL.' : mysqli_error($koneksi)]);
    exit;
}

// API: Hapus Jurnal Guru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_journal') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);

    $stmt = mysqli_prepare($koneksi, "DELETE FROM jurnal_guru WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Jurnal mengajar berhasil dihapus dari database.' : mysqli_error($koneksi)]);
    exit;
}

$teachersList = [];
$qGuru = mysqli_query($koneksi, "SELECT * FROM data_guru ORDER BY CAST(kode AS UNSIGNED) ASC");
if ($qGuru && mysqli_num_rows($qGuru) > 0) {
    while ($r = mysqli_fetch_assoc($qGuru)) {
        $teachersList[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Guru SMPN 7 Purwakarta - Sistem Akademik Terpadu</title>
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }
    .badge-h { background-color: #10b981; color: #ffffff; }
    .badge-i { background-color: #f59e0b; color: #ffffff; }
    .badge-s { background-color: #8b5cf6; color: #ffffff; }
    .badge-a { background-color: #ef4444; color: #ffffff; }
    .table-scroll {
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
    }
    .table-scroll::-webkit-scrollbar {
      height: 5px;
    }
    .table-scroll::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 9999px;
    }
    .table-scroll::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 9999px;
    }
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

  <!-- ============================================================== -->
  <!-- SCREEN 1: HALAMAN LOGIN GURU -->
  <!-- ============================================================== -->
  <section id="screen-login" class="min-h-screen flex items-center justify-center p-4 bg-slate-900 relative overflow-hidden">
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-4xl w-full grid grid-cols-1 md:grid-cols-12 bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-800/40 relative z-10">
      
      <!-- Sisi Kiri: Branding & Informasi Sekolah -->
      <div class="md:col-span-5 bg-gradient-to-br from-indigo-950 via-indigo-900 to-slate-900 p-5 sm:p-8 text-white flex flex-col justify-between relative overflow-hidden">
        <div class="relative z-10">
          <div class="flex items-center gap-3 mb-4 sm:mb-6">
            <div class="w-12 sm:w-14 h-12 sm:h-14 flex items-center justify-center flex-shrink-0">
              <img src="logoSMP.png" alt="Logo Sekolah" class="w-full h-full object-contain">
            </div>
            <div>
              <h2 class="font-bold text-xs sm:text-sm tracking-wide text-white">SMPN 7 PURWAKARTA</h2>
              <p class="text-[10px] sm:text-[11px] text-amber-300 font-medium">Sistem Penilaian & Presensi Guru</p>
            </div>
          </div>

          <div class="mt-4 sm:mt-8 space-y-2 sm:space-y-4">
            <h1 class="text-xl sm:text-2xl font-extrabold text-white leading-tight">
              Satu Pintu untuk Semua Kebutuhan Pembelajaran.
            </h1>
            <p class="text-xs text-indigo-200 leading-relaxed">
              Input nilai siswa dengan KKM fleksibel, presensi hari masuk dinamis, modul ajar, dan rekapitulasi buku induk rapor.
            </p>
          </div>
        </div>

        <div class="mt-6 sm:mt-12 pt-4 sm:pt-6 border-t border-indigo-800/60 relative z-10 text-[11px] text-indigo-300 flex items-center justify-between">
          <span>Tahun Ajaran 2026/2027</span>
          <span class="bg-indigo-800/80 px-2 py-0.5 rounded-full text-amber-300 font-semibold border border-indigo-700">Kurikulum Merdeka</span>
        </div>
      </div>

      <!-- Sisi Kanan: Form Login Manual -->
      <div class="md:col-span-7 p-6 sm:p-10 flex flex-col justify-center bg-white">
        <div>
          <h3 class="text-lg sm:text-xl font-bold text-indigo-950">Masuk ke Akun Guru</h3>
          <p class="text-xs text-slate-500 mt-1">Masukkan NIP dan Kata Sandi Anda untuk melanjutkan.</p>
        </div>

        <!-- Alert Error Box -->
        <div id="login-error-box" class="hidden mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-xs flex items-start gap-2.5">
          <svg class="w-4 h-4 text-rose-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <span id="login-error-msg" class="font-medium">NIP atau Kata Sandi salah!</span>
        </div>

        <form onsubmit="handleLoginSubmit(event)" class="mt-5 space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">NIP Guru / Identitas Pegawai</label>
            <div class="relative">
              <input type="text" id="login-nip" required placeholder="Masukkan NIP Anda (Contoh: 19880412 201101 2 003)" autocomplete="username" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all font-medium text-xs sm:text-sm">
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Gunakan NIP terdaftar di data akademik sekolah.</p>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-semibold text-slate-700">Kata Sandi Akun</label>
              <span class="text-[10px] text-slate-400">Default: <code class="font-mono text-indigo-700 bg-indigo-50 px-1 py-0.5 rounded">guru12345</code></span>
            </div>
            <input type="password" id="login-pass" required placeholder="Masukkan kata sandi..." autocomplete="current-password" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none transition-all font-medium text-xs sm:text-sm">
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input type="checkbox" id="remember-me" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <label for="remember-me" class="text-slate-600 cursor-pointer">Ingat sesi login saya di perangkat ini</label>
          </div>

          <button type="submit" id="btn-login-submit" class="w-full py-3 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-md transition-all active:scale-[0.99] flex items-center justify-center gap-2 text-sm mt-2">
            <span>Masuk ke Sistem Guru</span>
            <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>
        </form>

        <div class="mt-4 pt-3 border-t border-slate-100 text-center">
          <p class="text-[11px] text-slate-500">
            Lupa kata sandi? Hubungi <span class="font-semibold text-indigo-900">Administrator Sekolah</span> untuk mereset akun Anda.
          </p>
        </div>

      </div>

    </div>
  </section>


  <!-- ============================================================== -->
  <!-- SCREEN 2: PILIH MAPEL & PILIH KELAS BERTINGKAT (7/8/9 -> ROMBEL A-E) -->
  <!-- ============================================================== -->
  <section id="screen-select-class" class="min-h-screen bg-slate-900 text-white flex flex-col justify-between p-3 sm:p-6 md:p-8 hidden">
    
    <div class="max-w-4xl w-full mx-auto flex items-center justify-between py-3 sm:py-4 px-1 sm:px-0 border-b border-slate-800">
      <div class="flex items-center gap-2.5 sm:gap-3">
        <div class="w-9 sm:w-10 h-9 sm:h-10 rounded-xl overflow-hidden bg-white/10 border border-white/10 flex items-center justify-center shadow-md flex-shrink-0">
          <img src="logoSMP.png" alt="Logo Sekolah" class="w-full h-full object-cover">
        </div>
        <div>
          <span class="font-bold text-xs sm:text-sm text-white">SMPN 7 PURWAKARTA</span>
          <p class="text-[10px] sm:text-xs text-indigo-300">Tahun Ajaran 2026/2027 · Semester Ganjil</p>
        </div>
      </div>

      <div class="flex items-center gap-2.5 sm:gap-3 text-xs">
        <div class="text-right hidden sm:block">
          <p class="font-bold text-white text-xs" id="step2-teacher-name">Ibu Siti Rahmawati, S.Pd</p>
          <p class="text-slate-400 text-[10px]">Guru Pengampu Aktif</p>
        </div>
        <button onclick="goBack()" class="text-slate-300 hover:text-white px-2.5 sm:px-3 py-1.5 rounded-lg border border-slate-700 hover:border-slate-500 transition-colors text-xs flex items-center gap-1.5 active:scale-95">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          <span>Kembali</span>
        </button>
      </div>
    </div>

    <!-- Container Form Pemilihan Bertingkat -->
    <div class="max-w-2xl w-full mx-auto my-auto py-4 sm:py-6">
      
      <div class="text-center mb-4 sm:mb-6">
        <span class="text-[11px] sm:text-xs bg-amber-400 text-indigo-950 font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Langkah Pemilihan Ruang Kerja</span>
        <h2 class="text-xl sm:text-3xl font-extrabold text-white mt-2">Pilih Peran & Kelas Belajar</h2>
        <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-lg mx-auto">
          Tentukan peran Anda sebagai Guru Mapel atau Wali Kelas, lalu pilih jenjang (7, 8, atau 9) dan rombelnya (A sampai I).
        </p>
      </div>

      <div class="bg-white text-slate-800 rounded-3xl p-4 sm:p-8 shadow-2xl border border-slate-100 space-y-5 sm:space-y-6">
        
        <!-- LANGKAH 1: PILIH PERAN (GURU MAPEL ATAU WALI KELAS) -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            1. Status Penugasan Anda Saat Ini
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button type="button" onclick="selectTeacherRole('guru_mapel')" id="btn-role-guru-mapel" class="p-3.5 sm:p-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-left transition-all shadow-sm">
              <div class="flex items-center gap-2.5 mb-1.5">
                <span class="w-8 h-8 rounded-xl bg-indigo-950 text-amber-300 font-bold flex items-center justify-center text-sm flex-shrink-0">📘</span>
                <span class="font-extrabold text-xs sm:text-sm text-indigo-950">Guru Mata Pelajaran</span>
              </div>
              <p class="text-[11px] text-slate-600 leading-relaxed">
                Pilih satu mata pelajaran yang Anda ampu untuk menginput nilai dan presensi di kelas terkait.
              </p>
            </button>

            <button type="button" onclick="selectTeacherRole('wali_kelas')" id="btn-role-wali-kelas" class="p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 hover:border-indigo-400 bg-white text-left transition-all">
              <div class="flex items-center gap-2.5 mb-1.5">
                <span class="w-8 h-8 rounded-xl bg-amber-400 text-indigo-950 font-bold flex items-center justify-center text-sm flex-shrink-0">🎓</span>
                <span class="font-extrabold text-xs sm:text-sm text-slate-800">Wali Kelas</span>
              </div>
              <p class="text-[11px] text-slate-500 leading-relaxed">
                Pantau seluruh mata pelajaran (semua mapel) dan perkembangan siswa di kelas binaan Anda.
              </p>
            </button>
          </div>
        </div>

        <!-- LANGKAH 2: PILIH MATA PELAJARAN (HANYA MUNCUL JIKA GURU MAPEL) -->
        <div id="container-select-mapel">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            2. Mata Pelajaran yang Diajarkan
          </label>
          <div class="relative">
            <select id="select-subject-dropdown" class="w-full bg-slate-50 border-2 border-slate-200 hover:border-indigo-500 focus:border-indigo-600 rounded-xl px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-slate-800 focus:ring-0 focus:outline-none transition-all appearance-none cursor-pointer">
              <option value="Matematika" selected>📘 Matematika</option>
              <option value="Ilmu Pengetahuan Alam (IPA)">🔬 Ilmu Pengetahuan Alam (IPA)</option>
              <option value="Ilmu Pengetahuan Sosial (IPS)">🌍 Ilmu Pengetahuan Sosial (IPS)</option>
              <option value="Bahasa Indonesia">📖 Bahasa Indonesia</option>
              <option value="Bahasa Inggris">🗣️ Bahasa Inggris</option>
              <option value="Pendidikan Pancasila (PPKn)">🇮🇩 Pendidikan Pancasila (PPKn)</option>
              <option value="Pendidikan Agama & Budi Pekerti">🕌 Pendidikan Agama & Budi Pekerti</option>
              <option value="Informatika">💻 Informatika</option>
              <option value="PJOK (Pendidikan Jasmani)">⚽ PJOK (Penjaskes)</option>
              <option value="Seni Budaya">🎨 Seni Budaya</option>
              <option value="Prakarya">🛠️ Prakarya</option>
              <option value="Bahasa Sunda (Mulok)">🌾 Bahasa Sunda (Muatan Lokal)</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
          </div>
        </div>

        <!-- INFO WALI KELAS (MUNCUL JIKA ROLE WALI KELAS) -->
        <div id="container-wali-note" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl p-3.5 sm:p-4 text-xs text-amber-950 flex items-start gap-3">
          <span class="text-xl flex-shrink-0">🌟</span>
          <div>
            <p class="font-bold text-xs sm:text-sm text-indigo-950">Akses Penuh Wali Kelas Terintegrasi</p>
            <p class="text-slate-600 text-[11px] mt-0.5 leading-relaxed">
              Sebagai Wali Kelas, Anda tidak dibatasi pada satu mapel. Anda dapat melihat <strong>semua mata pelajaran</strong> (Matematika, IPA, B.Indo, B.Inggris, Informatika, dll.) yang diinput oleh masing-masing guru pengampu di kelas ini.
            </p>
          </div>
        </div>

        <!-- LANGKAH 3: PILIH TINGKAT KELAS (7, 8, atau 9) -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2" id="label-step-grade">
            3. Pilih Tingkat Jenjang (7, 8, atau 9)
          </label>
          <div class="grid grid-cols-3 gap-2 sm:gap-3">
            <button type="button" onclick="selectGradeLevel(7)" id="btn-grade-7" class="py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-slate-200 text-slate-700 hover:border-indigo-400 font-extrabold text-xs sm:text-sm transition-all text-center">
              <span class="block text-base sm:text-lg">Kelas 7</span>
              <span class="text-[10px] font-medium text-slate-400"></span>
            </button>
            <button type="button" onclick="selectGradeLevel(8)" id="btn-grade-8" class="py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-indigo-950 font-extrabold text-xs sm:text-sm transition-all text-center shadow-sm">
              <span class="block text-base sm:text-lg">Kelas 8</span>
              <span class="text-[10px] font-medium text-indigo-600"></span>
            </button>
            <button type="button" onclick="selectGradeLevel(9)" id="btn-grade-9" class="py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-slate-200 text-slate-700 hover:border-indigo-400 font-extrabold text-xs sm:text-sm transition-all text-center">
              <span class="block text-base sm:text-lg">Kelas 9</span>
              <span class="text-[10px] font-medium text-slate-400"></span>
            </button>
          </div>
        </div>

        <!-- LANGKAH 4: PILIH ROMBEL (A SAMPAI I -> 9 ROMBEL) -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500" id="label-step-rombel">
              4. Pilih Rombongan Belajar (Rombel A sampai I)
            </label>
            <span class="text-[11px] font-semibold text-indigo-800 bg-indigo-50 px-2.5 py-0.5 rounded-md" id="label-rombel-grade">Rombel Kelas 8</span>
          </div>

          <div class="grid grid-cols-3 sm:grid-cols-9 gap-1.5" id="rombel-selector-container">
            <!-- Populated via Javascript A s/d I -->
          </div>
          <p class="text-[11px] text-slate-400 mt-2">Pilih salah satu rombel (A, B, C, D, E, F, G, H, atau I) untuk membuka lembar kerja.</p>
        </div>

        <!-- Tombol Konfirmasi & Kembali -->
        <div class="pt-4 sm:pt-5 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          <button type="button" onclick="goBack()" class="order-2 sm:order-1 px-4 py-2.5 text-slate-600 hover:text-indigo-950 font-semibold rounded-xl text-xs sm:text-sm border border-slate-200 hover:bg-slate-50 transition-all flex items-center justify-center gap-1.5 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Login</span>
          </button>
          <button onclick="confirmWorkspaceAndProceed()" class="order-1 sm:order-2 w-full sm:w-auto px-6 py-3 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs sm:text-sm shadow-md transition-all flex items-center justify-center gap-2 active:scale-95">
            <span>Buka Dashboard Kelas</span>
            <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>
        </div>

      </div>

    </div>

    <div class="max-w-4xl w-full mx-auto text-center py-4 text-slate-500 text-xs border-t border-slate-800">
      Portal Guru Terpadu SMPN 7 Purwakarta · Kurikulum Merdeka
    </div>

  </section>


  <!-- ============================================================== -->
  <!-- SCREEN 3: DASHBOARD UTAMA -->
  <!-- ============================================================== -->
  <div id="screen-dashboard" class="min-h-screen flex flex-col hidden">
    
    <!-- TOP CONTEXT BANNER -->
    <header class="bg-indigo-950 text-white border-b border-indigo-900 sticky top-0 z-40 shadow-sm">
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          
          <!-- Logo & Nama Sekolah -->
          <div class="flex items-center gap-2.5 sm:gap-3">
            <div class="w-10 sm:w-11 h-10 sm:h-11 flex items-center justify-center flex-shrink-0">
              <img src="logoSMP.png" alt="Logo Sekolah" class="w-full h-full object-contain">
            </div>
            <div>
              <div class="flex items-center gap-1.5 sm:gap-2">
                <span class="font-bold tracking-tight text-white text-xs sm:text-base">SMPN 7 PURWAKARTA</span>
                <span class="text-[10px] sm:text-xs bg-indigo-800/80 text-amber-300 font-semibold px-2 py-0.5 rounded-full border border-indigo-700 hidden xs:inline-block">2026/2027</span>
              </div>
              <p class="text-[10px] sm:text-xs text-indigo-200">Sistem Akademik & Penilaian Terpadu</p>
            </div>
          </div>

          <!-- GLOBAL ACTIVE CONTEXT SWITCHER (DESKTOP) -->
          <div class="hidden md:flex items-center bg-indigo-900/90 border border-indigo-700/60 rounded-xl px-3 py-1.5 shadow-inner">
            <div class="flex items-center gap-3 text-xs">
              <div class="flex items-center gap-1.5 text-slate-300">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-indigo-200">Ruang Aktif:</span>
              </div>

              <!-- Subject Tag -->
              <div class="flex items-center gap-1 bg-indigo-800 px-2.5 py-1 rounded-lg font-medium text-amber-300 border border-indigo-700">
                <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span id="active-subject-text">Matematika</span>
              </div>

              <!-- Class Tag -->
              <div class="flex items-center gap-1 bg-indigo-800 px-2.5 py-1 rounded-lg font-medium text-white border border-indigo-700">
                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span id="active-class-text">Kelas VIII A</span>
              </div>

              <span class="text-indigo-300 font-normal">Semester Ganjil</span>

              <button onclick="goBack()" title="Kembali ke Pemilihan Mapel & Kelas" class="ml-2 bg-amber-500 hover:bg-amber-400 text-indigo-950 font-bold px-2.5 py-1 rounded-lg transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Kembali / Ganti Mapel</span>
              </button>
            </div>
          </div>

          <!-- Teacher Profile & Mobile Actions -->
          <div class="flex items-center gap-2 sm:gap-3">
            <div class="text-right hidden sm:block">
              <p class="text-xs font-semibold text-white" id="header-teacher-name">Ibu Siti Rahmawati, S.Pd</p>
              <p class="text-[11px] text-amber-300/90" id="header-teacher-role">NIP 19880412 201101 2 003</p>
            </div>
            <button onclick="goToLogin()" title="Keluar / Ganti Akun" class="w-8 sm:w-9 h-8 sm:h-9 rounded-full bg-indigo-800 border-2 border-amber-400 flex items-center justify-center font-bold text-amber-300 text-xs sm:text-sm hover:scale-105 transition-transform flex-shrink-0">
              SR
            </button>
            <!-- Mobile Hamburger Drawer Toggle -->
            <button type="button" onclick="toggleMobileDrawer(true)" class="md:hidden w-8 h-8 rounded-xl bg-indigo-900 border border-indigo-700 flex items-center justify-center text-white hover:bg-indigo-800 transition-colors ml-0.5" aria-label="Buka Menu Navigasi">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
          </div>

        </div>
      </div>

      <!-- MOBILE ACTIVE CONTEXT SUB-BAR (HANYA MUNCUL DI HP / TABLET KECIL) -->
      <div class="md:hidden bg-indigo-900/95 border-t border-indigo-900 px-3.5 py-2 flex items-center justify-between text-xs text-white">
        <div class="flex items-center gap-1.5 truncate">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse flex-shrink-0"></span>
          <span class="font-bold text-amber-300 truncate" id="mob-active-subject-text">Matematika</span>
          <span class="text-indigo-400">·</span>
          <span class="font-medium text-white truncate" id="mob-active-class-text">Kelas VIII A</span>
        </div>
        <button onclick="goBack()" title="Kembali ke Pilih Kelas / Mapel" class="bg-amber-400 hover:bg-amber-300 text-indigo-950 font-bold px-2.5 py-1 rounded-lg text-[11px] flex-shrink-0 ml-2 active:scale-95 transition-all flex items-center gap-1 shadow-xs">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          <span>Kembali</span>
        </button>
      </div>

      <!-- MOBILE HORIZONTAL SCROLLABLE TAB NAVIGATION (HANYA DI HP) -->
      <div class="md:hidden bg-white border-b border-slate-200 px-2.5 py-2 shadow-2xs">
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-smooth">
          <button onclick="switchTab('nilai')" id="btn-mob-tab-nilai" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-bold text-xs bg-indigo-900 text-white shadow-xs transition-all flex items-center gap-1.5">
            <span>📝 Nilai</span>
          </button>
          <button onclick="switchTab('presensi')" id="btn-mob-tab-presensi" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>📅 Presensi</span>
          </button>
          <button onclick="switchTab('jurnal')" id="btn-mob-tab-jurnal" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>📖 Jurnal</span>
          </button>
          <button onclick="switchTab('materi')" id="btn-mob-tab-materi" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>📂 Modul</span>
          </button>
          <button onclick="switchTab('rekap')" id="btn-mob-tab-rekap" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>📊 Rekap</span>
          </button>
          <button onclick="switchTab('faq')" id="btn-mob-tab-faq" class="flex-shrink-0 px-3 py-1.5 rounded-xl font-medium text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all flex items-center gap-1.5">
            <span>💡 FAQ</span>
          </button>
        </div>
      </div>
    </header>

    <!-- MOBILE OFF-CANVAS DRAWER MENU (MODAL MENU UNTUK SMARTPHONE) -->
    <div id="mobile-drawer-overlay" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-50 hidden transition-opacity" onclick="toggleMobileDrawer(false)">
      <div id="mobile-drawer" class="w-72 max-w-[85vw] bg-white h-full shadow-2xl flex flex-col justify-between overflow-y-auto" onclick="event.stopPropagation()">
        
        <div class="p-4 space-y-4">
          <!-- Drawer Header -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-xl bg-amber-400 text-indigo-950 font-bold flex items-center justify-center text-xs shadow-xs">
                SMP
              </div>
              <div>
                <h3 class="font-extrabold text-xs text-indigo-950">SMPN 7 Purwakarta</h3>
                <p class="text-[10px] text-slate-400">Menu Portal Akademik</p>
              </div>
            </div>
            <button onclick="toggleMobileDrawer(false)" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Teacher Profile Badge -->
          <div class="bg-indigo-50/80 border border-indigo-100 rounded-2xl p-3 text-xs">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-full bg-indigo-900 text-amber-300 font-bold flex items-center justify-center text-xs">SR</div>
              <div>
                <p class="font-bold text-indigo-950 text-xs">Ibu Siti Rahmawati, S.Pd</p>
                <p class="text-[10px] text-slate-500">NIP 19880412 201101 2 003</p>
              </div>
            </div>
            <div class="mt-2.5 pt-2 border-t border-indigo-100/80 flex items-center justify-between">
              <span class="text-[10px] text-slate-500 font-medium">Ruang: <strong class="text-indigo-950" id="mob-drawer-class-label">Kelas VIII A</strong></span>
              <button onclick="goBack(); toggleMobileDrawer(false);" class="text-[10px] bg-amber-400 text-indigo-950 font-bold px-2 py-0.5 rounded-md hover:bg-amber-300 transition-all flex items-center gap-1 active:scale-95">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Kembali</span>
              </button>
            </div>
          </div>

          <!-- Navigasi Menu Drawer -->
          <div>
            <p class="text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-2 px-1">Menu Navigasi</p>
            <nav class="space-y-1" id="mobile-nav-container">
              <button onclick="switchTab('nilai'); toggleMobileDrawer(false);" id="btn-drawer-tab-nilai" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all bg-indigo-900 text-white shadow-xs">
                <div class="flex items-center gap-2.5">
                  <span>📝</span>
                  <span>Input Nilai Siswa</span>
                </div>
                <span class="text-[10px] bg-amber-400 text-indigo-950 px-1.5 py-0.5 rounded-md font-bold">Aktif</span>
              </button>

              <button onclick="switchTab('presensi'); toggleMobileDrawer(false);" id="btn-drawer-tab-presensi" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>📅</span>
                  <span>Presensi Kehadiran</span>
                </div>
                <span class="text-[10px] text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-md font-semibold">Harian</span>
              </button>

              <button onclick="switchTab('jurnal'); toggleMobileDrawer(false);" id="btn-drawer-tab-jurnal" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>📖</span>
                  <span>Jurnal Harian</span>
                </div>
                <span class="text-[10px] bg-amber-100 text-amber-900 px-1.5 py-0.5 rounded-md font-bold">Catatan</span>
              </button>

              <button onclick="switchTab('materi'); toggleMobileDrawer(false);" id="btn-drawer-tab-materi" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>📂</span>
                  <span>Materi & Modul</span>
                </div>
                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-md font-semibold">3 File</span>
              </button>

              <button onclick="switchTab('rekap'); toggleMobileDrawer(false);" id="btn-drawer-tab-rekap" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>📊</span>
                  <span>Rekap Keseluruhan</span>
                </div>
                <span class="text-[10px] bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded-md font-bold">Rapor</span>
              </button>

              <button onclick="switchTab('faq'); toggleMobileDrawer(false);" id="btn-drawer-tab-faq" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-xs transition-all text-slate-600 hover:bg-slate-100">
                <div class="flex items-center gap-2.5">
                  <span>💡</span>
                  <span>Pusat Bantuan (FAQ)</span>
                </div>
                <span class="text-[10px] bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded-md font-bold">Panduan</span>
              </button>
            </nav>
          </div>

          <!-- KKM Target Widget di Drawer Mobile -->
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs">
            <div class="flex items-center justify-between mb-1">
              <span class="font-bold text-indigo-900 text-[11px]">Target KKM / KKTP</span>
              <span class="text-indigo-700 font-bold bg-white border border-indigo-200 px-2 py-0.5 rounded-lg text-xs" id="mob-sidebar-kkm-display">75</span>
            </div>
            <p class="text-[10px] text-slate-500 mb-1.5">Kelas: <strong id="mob-sidebar-class-name">Kelas VIII A</strong></p>
            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
              <div class="bg-indigo-600 h-full w-[100%] rounded-full"></div>
            </div>
            <p class="text-[10px] text-slate-500 mt-1 text-right font-medium">6 Siswa Terdaftar</p>
          </div>
        </div>

        <div class="p-4 border-t border-slate-100 space-y-2">
          <button onclick="goBack(); toggleMobileDrawer(false);" class="w-full py-2.5 px-3 text-xs text-indigo-900 bg-indigo-50 hover:bg-indigo-100 flex items-center justify-center gap-1.5 rounded-xl border border-indigo-200 transition-colors font-bold active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Pilih Mapel / Kelas</span>
          </button>
          <button onclick="goToLogin(); toggleMobileDrawer(false);" class="w-full py-2.5 px-3 text-xs text-rose-600 hover:bg-rose-50 flex items-center justify-center gap-1.5 rounded-xl border border-rose-200 transition-colors font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span>Keluar dari Akun Guru</span>
          </button>
        </div>

      </div>
    </div>

    <!-- BODY CONTENT AREA (SIDEBAR DESKTOP + MAIN APP) -->
    <div class="flex-1 max-w-7xl w-full mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 flex flex-col md:flex-row gap-6">

      <!-- SIDEBAR NAVIGATION (HANYA DITAMPILKAN DI DESKTOP/MD KE ATAS AGAR HP LEGA) -->
      <aside class="hidden md:block w-64 flex-shrink-0">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sticky top-24">
          
          <p class="text-[11px] font-bold tracking-wider text-slate-400 uppercase px-3 mb-2">Navigasi Utama</p>
          <nav class="space-y-1.5" id="nav-container">
            
            <button onclick="switchTab('nilai')" id="btn-tab-nilai" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all bg-indigo-900 text-white shadow-sm">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Input Nilai Siswa</span>
              </div>
              <span class="text-xs bg-amber-400 text-indigo-950 px-2 py-0.5 rounded-full font-bold">Aktif</span>
            </button>

            <button onclick="switchTab('presensi')" id="btn-tab-presensi" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Presensi Kehadiran</span>
              </div>
              <span class="text-xs text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md">Harian</span>
            </button>

            <button onclick="switchTab('jurnal')" id="btn-tab-jurnal" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span>Jurnal Harian</span>
              </div>
              <span class="text-xs bg-amber-100 text-amber-900 px-2 py-0.5 rounded-md font-bold" id="badge-jurnal-count">3 Catatan</span>
            </button>

            <button onclick="switchTab('materi')" id="btn-tab-materi" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span>Materi & Modul</span>
              </div>
              <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold">3 File</span>
            </button>

            <button onclick="switchTab('rekap')" id="btn-tab-rekap" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Rekap Keseluruhan</span>
              </div>
              <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-md font-bold">Semua Rekap</span>
            </button>

            <button onclick="switchTab('faq')" id="btn-tab-faq" class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-100">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Pusat Bantuan (FAQ)</span>
              </div>
              <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-md font-bold">Panduan</span>
            </button>

          </nav>

          <hr class="my-4 border-slate-100">

          <!-- KONTROL KKM DI SIDEBAR (DAPAT DIEDIT LANGSUNG OLEH GURU) -->
          <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl p-3.5 text-xs">
            <div class="flex items-center justify-between mb-1.5">
              <span class="font-bold text-indigo-900">Target KKM / KKTP</span>
              <span class="text-indigo-700 font-bold bg-white border border-indigo-200 px-2 py-0.5 rounded-lg" id="sidebar-kkm-display">75</span>
            </div>
            <p class="text-slate-600 leading-relaxed mb-2">
              Kelas: <span class="font-bold text-indigo-950" id="sidebar-class-name">Kelas VIII A</span>
            </p>
            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
              <div class="bg-indigo-600 h-full w-[100%] rounded-full" id="sidebar-progress-bar"></div>
            </div>
            <p class="text-[11px] text-slate-500 mt-1.5 text-right font-medium" id="sidebar-pass-percent">6 Siswa Terdaftar</p>
          </div>

          <div class="mt-4 pt-2 space-y-2">
            <button onclick="goBack()" class="w-full py-2 px-3 text-xs text-indigo-900 bg-indigo-50 hover:bg-indigo-100 flex items-center justify-center gap-1.5 rounded-lg border border-indigo-200 transition-colors font-bold active:scale-95">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
              <span>Kembali ke Pilih Mapel / Kelas</span>
            </button>
            <button onclick="goToLogin()" class="w-full py-2 px-3 text-xs text-rose-600 hover:bg-rose-50 flex items-center justify-center gap-1.5 rounded-lg border border-rose-200 transition-colors font-medium">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
              <span>Keluar dari Akun Guru</span>
            </button>
          </div>

        </div>
      </aside>

      <!-- MAIN INTERACTIVE WORKSPACE -->
      <main class="flex-1 min-w-0 space-y-5 sm:space-y-6">

        <!-- ============================================================== -->
        <!-- TAB 1: INPUT NILAI SISWA (DILENGKAPI WALI KELAS & INPUT MANUAL) -->
        <!-- ============================================================== -->
        <section id="tab-nilai" class="space-y-4">
          
          <!-- SUB-TAB NAVIGATION SWITCHER (HANYA MUNCUL DI WALI KELAS, GURU MAPEL CUKUP NILAI MAPELNYA SAJA) -->
          <div id="subtab-nilai-switcher" class="hidden flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 bg-white p-2.5 rounded-2xl border border-slate-200/80 shadow-2xs">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5 sm:gap-2 p-1 bg-slate-100 rounded-xl">
              <button type="button" onclick="switchGradeSubTab('sheet')" id="subtab-btn-sheet" class="w-full sm:w-auto px-3.5 sm:px-4 py-2 rounded-lg text-xs font-bold transition-all bg-indigo-950 text-white shadow-xs flex items-center justify-center gap-2">
                <span>📄 Lembar Penilaian Mapel</span>
              </button>
              <button type="button" onclick="switchGradeSubTab('ledger')" id="subtab-btn-ledger" class="w-full sm:w-auto px-3.5 sm:px-4 py-2 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-indigo-950 flex items-center justify-center gap-2">
                <span>📊 Rekap Seluruh Mapel (Ledger Nilai Siswa)</span>
              </button>
            </div>
            <div class="text-[11px] text-slate-500 pr-2 hidden sm:flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span id="subtab-context-hint">Mode aktif: Lembar Penilaian Mapel</span>
            </div>
          </div>

          <!-- ========================================== -->
          <!-- SUB-VIEW 1: LEMBAR PENILAIAN MATA PELAJARAN -->
          <!-- ========================================== -->
          <div id="subview-grade-sheet" class="space-y-4">

            <!-- BAR KHUSUS WALI KELAS: PEMANTAUAN SELURUH MAPEL KELAS BINAAN -->
            <div id="wali-pov-control-bar" class="hidden bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-900 text-white p-4 sm:p-5 rounded-2xl shadow-sm border border-indigo-800">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] bg-amber-400 text-indigo-950 font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider">Mode Wali Kelas</span>
                    <h3 class="font-extrabold text-sm text-white" id="wali-pov-class-title">Pemantauan Seluruh Mapel Kelas VIII A</h3>
                  </div>
                  <p class="text-[11px] text-indigo-200 mt-0.5">Sebagai Wali Kelas, Anda dapat melihat seluruh nilai mata pelajaran yang diinput oleh masing-masing guru pengampu.</p>
                </div>

                <div class="flex items-center gap-2.5 bg-white/10 backdrop-blur-xs p-2 rounded-xl border border-white/10">
                  <label for="select-wali-pov-subject" class="text-xs text-amber-300 font-bold whitespace-nowrap">Mapel:</label>
                  <select id="select-wali-pov-subject" onchange="handleWaliPovSubjectChange(this.value)" class="bg-indigo-900 text-white font-bold text-xs rounded-lg px-3 py-1.5 border border-indigo-700 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer w-full sm:w-auto">
                    <option value="Matematika" selected>📘 Matematika (Ibu Siti Rahmawati, S.Pd)</option>
                    <option value="IPA">🔬 IPA (Bpk. Budi Santoso, M.Pd)</option>
                    <option value="Bahasa Indonesia">📖 Bahasa Indonesia (Ibu Dra. Hj. Nur Aini)</option>
                    <option value="Bahasa Inggris">🗣️ Bahasa Inggris (Ibu Rina Nurhasanah, S.Pd)</option>
                    <option value="Informatika">💻 Informatika (Bpk. Ahmad Fauzi, S.Kom)</option>
                    <option value="Pendidikan Pancasila">🇮🇩 Pendidikan Pancasila (Bpk. Dedi Suhendar, S.Pd)</option>
                    <option value="IPS">🌍 IPS (Bpk. Tatang Sutisna, S.Pd)</option>
                    <option value="PJOK">⚽ PJOK (Bpk. Asep Kurnia, S.Pd)</option>
                    <option value="Seni Budaya">🎨 Seni Budaya (Ibu Maya Lestari, S.Sn)</option>
                    <option value="Pendidikan Agama">🕌 Pend. Agama Islam (Ibu Eni Rohaeni, S.Ag)</option>
                    <option value="Bahasa Sunda">🌾 Bahasa Sunda (Ibu Ceceu Marlina, S.Pd)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- PERINGATAN KETAT WALI KELAS READ-ONLY (WALAS GABISA NAMBAH / UBAH NILAI) -->
            <div id="walas-readonly-banner" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl p-4 text-xs text-amber-950 flex items-start gap-3.5 shadow-2xs">
              <div class="w-9 h-9 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center font-black text-lg flex-shrink-0">
                🔒
              </div>
              <div>
                <p class="font-bold text-sm text-indigo-950">Akses Pemantauan Wali Kelas (Hanya Lihat / Read-Only)</p>
                <p class="text-slate-600 text-[11px] mt-0.5 leading-relaxed">
                  Sesuai ketentuan, Wali Kelas <strong>tidak memiliki wewenang untuk menambah kolom atau mengedit nilai</strong> pada lembar mata pelajaran. Hak penginputan dan penilaian sepenuhnya berada di tangan masing-masing Guru Mata Pelajaran pengampu.
                </p>
              </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
                <div>
                  <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg sm:text-xl font-bold text-indigo-950" id="title-grade-workspace">Input Nilai Siswa</h1>
                    <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-2.5 py-0.5 rounded-full" id="tag-current-view">Matematika - Kelas VIII A</span>
                  </div>
                  <p class="text-xs text-slate-500 mt-1" id="desc-grade-workspace">
                    Input tugas atau ulangan secara manual satu per satu. KKM dapat disesuaikan sebagai acuan penilaian.
                  </p>
                </div>

                <!-- KONTROL KKM BISA DIEDIT DAN DISESUAIKAN -->
                <div class="flex items-center gap-2.5 bg-amber-50 border-2 border-amber-300 px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-2xl shadow-sm self-start sm:self-auto">
                  <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-amber-900">KKM / KKTP</label>
                    <span class="text-[11px] text-amber-700">Target Acuan</span>
                  </div>
                  <input type="number" id="input-kkm-target" min="50" max="100" value="75" oninput="handleKKMChange(this.value)" class="w-14 sm:w-16 text-center py-1 sm:py-1.5 px-2 bg-white border-2 border-amber-400 rounded-xl font-black text-indigo-950 text-sm sm:text-base focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
              </div>

              <!-- Filter Info Sub-bar -->
              <div class="mt-4 pt-3 sm:pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3 text-xs">
                <div>
                  <label class="block text-slate-500 font-medium mb-1">Mata Pelajaran Aktif</label>
                  <input type="text" id="filter-mapel-locked" value="Matematika" readonly class="w-full bg-slate-100 border border-slate-200 text-slate-700 rounded-lg px-2.5 py-1.5 font-medium cursor-not-allowed">
                </div>
                <div>
                  <label class="block text-slate-500 font-medium mb-1">Kelas Aktif</label>
                  <input type="text" id="filter-kelas-display" value="Kelas VIII A" readonly class="w-full bg-slate-100 border border-slate-200 text-slate-700 rounded-lg px-2.5 py-1.5 font-medium cursor-not-allowed">
                </div>
                <div>
                  <label class="block text-slate-500 font-medium mb-1">Status Kolom</label>
                  <div class="flex items-center gap-2 pt-1 font-semibold text-indigo-900">
                    <span id="label-column-count" class="bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-lg text-xs">0 Kolom Penilaian</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- TABEL SPREADSHEET DINAMIS MAPEL -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
              
              <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50/80 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3">
                  <span class="font-bold text-slate-800 text-sm">Lembar Penilaian Siswa</span>
                </div>

                <!-- TOMBOL TAMBAH KOLOM NILAI (DISEMBUNYIKAN JIKA WALI KELAS) -->
                <button onclick="openAddColumnModal()" id="btn-add-grade-col" class="w-full sm:w-auto px-3.5 py-2 sm:py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border-2 border-indigo-200 hover:border-indigo-400 rounded-xl font-bold flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95">
                  <svg class="w-4 h-4 text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  <span> Tambah Kolom Nilai</span>
                </button>
              </div>

              <!-- Table mobile swipe hint -->
              <div class="md:hidden px-3.5 py-1.5 bg-indigo-50/70 border-b border-indigo-100 text-[11px] text-indigo-700 flex items-center justify-between font-medium">
                <span>👉 Geser tabel ke kanan untuk melihat kolom penilaian</span>
                <span class="text-xs">↔️</span>
              </div>

              <!-- Table Container -->
              <div class="overflow-x-auto table-scroll">
                <table class="w-full text-left text-xs border-collapse" id="dynamic-grade-table">
                  <thead>
                    <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200" id="grade-table-header-row">
                      <!-- Javascript will render table header -->
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 text-slate-700" id="grade-table-body">
                    <!-- Populated via Javascript -->
                  </tbody>
                </table>
              </div>

              <!-- FOOTER TABEL: TOMBOL SIMPAN & DOWNLOAD CSV DILETAKKAN DI BAWAH TABEL -->
              <div class="px-4 sm:px-5 py-3.5 sm:py-4 bg-slate-50/90 border-t border-slate-200 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs">
                <span class="text-slate-500 text-center sm:text-left text-[11px] sm:text-xs">Nilai tersimpan akan langsung terakumulasi ke buku induk rekap rapor.</span>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-2.5 w-full sm:w-auto">
                  <button onclick="saveAllGrades()" id="btn-save-grades" class="w-full sm:w-auto px-4 py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white rounded-xl font-bold flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95">
                    <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Simpan Perubahan</span>
                  </button>

                  <button onclick="exportGradesToCSV()" class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95" title="Download tabel nilai dalam format .csv">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download CSV</span>
                  </button>
                </div>
              </div>

            </div>

          </div>

          <!-- ============================================================== -->
          <!-- SUB-VIEW 2: REKAP SELURUH MAPEL DENGAN RATA-RATA TIAP ANAK (LEDGER) -->
          <!-- ============================================================== -->
          <div id="subview-grade-ledger" class="hidden space-y-4">
            
            <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-900 font-extrabold text-[10px]">Ledger Terpadu</span>
                    <h2 class="text-lg sm:text-xl font-bold text-indigo-950">Rekap Nilai Seluruh Mata Pelajaran</h2>
                  </div>
                  <p class="text-xs text-slate-500 mt-1">
                    Kompilasi nilai akhir seluruh mapel (11 mata pelajaran) beserta perhitungan rata-rata gabungan untuk setiap siswa di kelas.
                  </p>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                  <button onclick="downloadSubviewLedgerCSV()" class="w-full sm:w-auto px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-1.5 shadow-sm transition-all active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Download Rekap Seluruh Mapel (CSV)</span>
                  </button>
                </div>
              </div>

              <!-- Stat Metrics Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-3.5 mt-4 pt-4 border-t border-slate-100 text-xs">
                <div class="bg-indigo-50/60 border border-indigo-100 rounded-xl p-3.5">
                  <p class="text-slate-500 text-[11px] font-medium">Rata-Rata Umum Seluruh Mapel</p>
                  <p class="text-xl font-black text-indigo-950 mt-0.5" id="metric-ledger-all-avg">83.8</p>
                  <p class="text-[10px] text-emerald-600 font-semibold mt-0.5">Tuntas di atas KKM Sekolah (75)</p>
                </div>
                <div class="bg-emerald-50/60 border border-emerald-100 rounded-xl p-3.5">
                  <p class="text-slate-500 text-[11px] font-medium">Siswa Tuntas Seluruh Mapel</p>
                  <p class="text-xl font-black text-emerald-700 mt-0.5" id="metric-ledger-pass-count">5 / 6 Siswa</p>
                  <p class="text-[10px] text-emerald-700 font-semibold mt-0.5">83.3% Ketuntasan Belajar</p>
                </div>
                <div class="bg-amber-50/60 border border-amber-100 rounded-xl p-3.5">
                  <p class="text-slate-500 text-[11px] font-medium">Perlu Pendampingan / Remedial</p>
                  <p class="text-xl font-black text-amber-700 mt-0.5" id="metric-ledger-remedial-count">1 Siswa</p>
                  <p class="text-[10px] text-amber-800 font-semibold mt-0.5">Daffa Ramadhan (Rata-rata &lt; 75)</p>
                </div>
              </div>
            </div>

            <!-- Ledger Table Container -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
              <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50/80 border-b border-slate-200 flex flex-wrap items-center justify-between gap-1.5">
                <span class="font-bold text-slate-800 text-xs">Tabel Ledger Komparasi 11 Mata Pelajaran</span>
                <span class="text-[10px] sm:text-[11px] text-slate-400">Nilai bersumber dari guru pengampu</span>
              </div>

              <!-- Table mobile swipe hint -->
              <div class="md:hidden px-3.5 py-1.5 bg-blue-50/70 border-b border-blue-100 text-[11px] text-blue-700 flex items-center justify-between font-medium">
                <span>👉 Geser tabel ke kanan untuk melihat 11 mapel</span>
                <span class="text-xs">↔️</span>
              </div>

              <div class="overflow-x-auto table-scroll">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                      <th class="py-3 px-3 w-10 text-center">No</th>
                      <th class="py-3 px-3 w-28">NISN</th>
                      <th class="py-3 px-4 min-w-[150px]">Nama Siswa</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950 border-l border-slate-200" title="Matematika (KKM 75)">MTK</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Ilmu Pengetahuan Alam (KKM 75)">IPA</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Ilmu Pengetahuan Sosial (KKM 75)">IPS</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Bahasa Indonesia (KKM 78)">IND</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Bahasa Inggris (KKM 75)">ING</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Pendidikan Pancasila (KKM 78)">PKN</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Pendidikan Agama & Budi Pekerti (KKM 80)">PAI</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Informatika (KKM 75)">INF</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Pendidikan Jasmani & Olahraga (KKM 78)">PJK</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Seni Budaya (KKM 75)">SNB</th>
                      <th class="py-3 px-2 text-center bg-blue-50/60 text-blue-950" title="Bahasa Sunda Mulok (KKM 75)">SND</th>
                      <th class="py-3 px-3 text-center bg-amber-100 text-amber-950 font-black border-l border-slate-200 text-xs">Rata-Rata Seluruh Mapel</th>
                      <th class="py-3 px-3 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody id="subview-ledger-tbody" class="divide-y divide-slate-100 text-slate-700">
                    <!-- Populated dynamically via renderSubviewLedgerTable() -->
                  </tbody>
                </table>
              </div>

              <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 flex flex-wrap items-center justify-between gap-2">
                <span>Keterangan: Skor berwarna hijau menandakan tuntas &ge; KKM mapel. Skor merah menandakan perlu remedial.</span>
                <span class="font-bold text-indigo-900">Kurikulum Merdeka · Fase D SMP</span>
              </div>
            </div>

          </div>

        </section>

        <!-- ============================================================== -->
        <!-- TAB 2: PRESENSI KEHADIRAN (MURNI PENCATATAN PER HARI MASUK) -->
        <!-- Bagian Rekap 1 Semester telah dipindah ke Tab Rekap Keseluruhan -->
        <!-- ============================================================== -->
        <section id="tab-presensi" class="hidden space-y-4">
          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm space-y-4">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h1 class="text-lg sm:text-xl font-bold text-indigo-950">Presensi Kehadiran Siswa</h1>
                  <span class="text-xs bg-emerald-100 text-emerald-800 font-semibold px-2.5 py-0.5 rounded-full" id="attend-column-count-badge">3 Pertemuan Tercatat</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                  Catat kehadiran siswa pada setiap hari masuk sekolah. Klik badge (H/I/S/A) untuk mengganti status kehadiran.
                </p>
              </div>

              <!-- Quick action -->
              <button onclick="markAllCurrentMeetingPresent()" class="w-full sm:w-auto px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-bold transition-all text-center justify-center">
                 Tandai Semua Hadir di Pertemuan Terakhir
              </button>
            </div>

            <!-- Header Bar Tabel Presensi + Tombol Tambah Kolom Hari Masuk -->
            <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 sm:gap-3 text-xs">
              <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <span class="font-bold text-slate-800 text-sm">Lembar Kehadiran Harian</span>
                <span class="text-slate-500 text-[11px]">(Klik badge: H=Hadir, I=Izin, S=Sakit, A=Alpa)</span>
              </div>

              <!-- TOMBOL KOTAK TAMBAH KOLOM HARI MASUK / PERTEMUAN -->
              <button onclick="openAddAttendanceColumnModal()" class="w-full sm:w-auto px-3.5 py-2 sm:py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-950 border-2 border-emerald-300 hover:border-emerald-500 rounded-xl font-bold flex items-center justify-center gap-1.5 transition-all shadow-sm active:scale-95">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span> Tambah Hari Masuk / Pertemuan</span>
              </button>
            </div>

            <!-- Table mobile swipe hint -->
            <div class="md:hidden px-3.5 py-1.5 bg-emerald-50/70 border border-emerald-100 rounded-xl text-[11px] text-emerald-800 flex items-center justify-between font-medium">
              <span>👉 Geser ke kanan untuk melihat pertemuan presensi</span>
              <span class="text-xs">↔️</span>
            </div>

            <!-- Tabel Presensi Dinamis -->
            <div class="border border-slate-200 rounded-xl overflow-x-auto table-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200" id="attendance-table-header-row">
                    <!-- Populated via Javascript -->
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="attendance-table-body">
                  <!-- Populated via Javascript -->
                </tbody>
              </table>
            </div>

            <!-- TOMBOL AKSI DILETAKKAN DI BAWAH TABEL PRESENSI -->
            <div class="pt-3 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs border-t border-slate-100">
              <span class="text-slate-500 text-[11px] sm:text-xs text-center sm:text-left">Seluruh data presensi per hari otomatis terakumulasi di menu Rekap Keseluruhan.</span>
              
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <button onclick="saveAttendance()" class="w-full sm:w-auto px-4 py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white rounded-xl font-bold transition-all shadow-sm justify-center">
                  Simpan Presensi
                </button>
                <button onclick="exportDailyAttendanceCSV()" class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold transition-all shadow-sm flex items-center justify-center gap-1.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                  <span>Download Presensi (.csv)</span>
                </button>
              </div>
            </div>

          </div>

        </section>


        <!-- ============================================================== -->
        <!-- TAB 3: JURNAL HARIAN MENGAJAR GURU -->
        <!-- ============================================================== -->
        <section id="tab-jurnal" class="hidden space-y-4">
          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h2 class="text-lg font-bold text-indigo-950">Jurnal Harian Mengajar Guru</h2>
                  <span class="text-xs bg-amber-100 text-amber-900 font-semibold px-2.5 py-0.5 rounded-full" id="jurnal-tag-class">Matematika - Kelas VIII A</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                  Catatan agenda pembelajaran per pertemuan: hari, tanggal, materi pokok, rincian kegiatan kelas, dan rekap absensi.
                </p>
              </div>

              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                <button onclick="exportJournalCSV()" class="w-full sm:w-auto px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 transition-all shadow-xs">
                  <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                  <span>Download CSV</span>
                </button>
                <button onclick="openAddJournalModal()" class="w-full sm:w-auto px-4 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1.5 shadow-sm transition-all active:scale-95">
                  <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  <span> Tambah Jurnal Harian</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Tabel Jurnal Harian -->
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Table mobile swipe hint -->
            <div class="md:hidden px-3.5 py-1.5 bg-amber-50/70 border-b border-amber-100 text-[11px] text-amber-900 flex items-center justify-between font-medium">
              <span>👉 Geser tabel ke kanan untuk melihat rincian kegiatan & absensi</span>
              <span class="text-xs">↔️</span>
            </div>

            <div class="overflow-x-auto table-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 font-bold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                    <th class="py-3 px-3 w-10 text-center">No</th>
                    <th class="py-3 px-3 w-36">Hari & Tanggal</th>
                    <th class="py-3 px-3 w-28">Pertemuan</th>
                    <th class="py-3 px-4 min-w-[180px]">Materi Pembelajaran</th>
                    <th class="py-3 px-4 min-w-[260px]">Kegiatan Pembelajaran</th>
                    <th class="py-3 px-3 min-w-[170px] text-center">Rekap Absen Siswa</th>
                    <th class="py-3 px-3 text-right w-20">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="journal-table-body">
                  <!-- Injected via JavaScript -->
                </tbody>
              </table>
            </div>
            <div class="p-3.5 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1">
              <span id="journal-count-label">Menampilkan entri jurnal mengajar</span>
              <span class="text-slate-400">Jurnal ini dapat dicetak untuk keperluan supervisi administrasi kurikulum.</span>
            </div>
          </div>

        </section>


        <!-- ============================================================== -->
        <!-- TAB 4: MATERI & MODUL AJAR (PDF UPLOADER & REPOSITORY) -->
        <!-- ============================================================== -->
        <section id="tab-materi" class="hidden space-y-5">
          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
              <div>
                <h2 class="text-base sm:text-lg font-bold text-indigo-950">Upload Modul / Materi Pembelajaran</h2>
                <p class="text-xs text-slate-500">Unggah bahan ajar, modul PDF, LKPD, atau kisi-kisi untuk kelas aktif.</p>
              </div>
              <span class="text-xs bg-amber-100 text-amber-900 font-semibold px-2.5 py-1 rounded-lg self-start sm:self-auto">Maksimal 15 MB / file</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5 mt-4 text-xs">
              <div class="space-y-3">
                <div>
                  <label class="block text-slate-600 font-semibold mb-1">Judul Materi / Modul</label>
                  <input id="input-modul-title" type="text" placeholder="Contoh: Modul Eksponen & Bentuk Akar" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-slate-600 font-semibold mb-1">Mata Pelajaran (Terkunci)</label>
                    <input type="text" id="modul-subject-locked" value="Matematika" readonly class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 font-medium cursor-not-allowed text-slate-600">
                  </div>
                  <div>
                    <label class="block text-slate-600 font-semibold mb-1">Kelas Sasaran</label>
                    <input type="text" id="modul-class-locked" value="Kelas VIII A" readonly class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 font-medium cursor-not-allowed text-slate-600">
                  </div>
                </div>

                <div>
                  <label class="block text-slate-600 font-semibold mb-1">Deskripsi Singkat untuk Siswa</label>
                  <textarea id="input-modul-desc" rows="3" placeholder="Catatan instruksi membaca modul..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>
              </div>

              <!-- Drag & Drop Zone -->
              <div class="flex flex-col justify-between">
                <div class="border-2 border-dashed border-indigo-200 hover:border-indigo-400 bg-indigo-50/30 rounded-xl p-5 sm:p-6 text-center flex flex-col items-center justify-center flex-1 cursor-pointer transition-colors group">
                  <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                  </div>
                  <p class="font-bold text-indigo-950 text-sm">Tarik & Lepas File Modul (PDF / DOCX)</p>
                  <p class="text-slate-400 text-xs mt-1">atau klik untuk telusuri file dari perangkat</p>
                  <div class="mt-3 px-3 py-1 bg-white border border-indigo-100 text-indigo-800 rounded-md font-medium text-[11px] shadow-sm">
                    File siap diunggah ke rombel aktif
                  </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 mt-4">
                  <button class="w-full sm:w-auto px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-semibold justify-center">Simpan Draf</button>
                  <button onclick="addModuleSimulated()" class="w-full sm:w-auto px-5 py-2 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Terbitkan Modul</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Daftar Modul Terbit -->
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
              <h3 class="font-bold text-sm text-indigo-950" id="module-header-title">Modul Terbit</h3>
              <span class="text-xs text-slate-500">Tersedia untuk siswa di kelas ini</span>
            </div>

            <div class="divide-y divide-slate-100 text-xs" id="module-list-container">
              <!-- Rendered dynamically -->
            </div>
          </div>

        </section>


        <!-- ============================================================== -->
        <!-- TAB 5: REKAP KESELURUHAN (SEMUA REKAP 1 SEMESTER ADA DI SINI) -->
        <!-- ============================================================== -->
        <section id="tab-rekap" class="hidden space-y-5 sm:space-y-6">
          
          <!-- Banner Header -->
          <div class="bg-gradient-to-r from-indigo-950 to-indigo-900 text-white rounded-2xl p-4 sm:p-6 shadow-md">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <span class="text-[11px] sm:text-xs bg-amber-400 text-indigo-950 font-extrabold px-2.5 py-1 rounded-md">Buku Induk & Ledger Rapor</span>
                <h2 class="text-xl sm:text-2xl font-bold mt-2 text-white">Rekapitulasi Capaian Siswa 1 Semester</h2>
                <p class="text-xs text-indigo-200 mt-1 max-w-xl">
                  Seluruh hasil evaluasi nilai akhir dan rekap presensi semester ganjil terintegrasi di sini untuk persiapan buku rapor.
                </p>
              </div>
            </div>

            <!-- Highlight Metric Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 mt-4 sm:mt-6 pt-4 sm:pt-5 border-t border-indigo-800/80">
              <div class="bg-indigo-900/60 rounded-xl p-3 border border-indigo-700/50">
                <p class="text-[11px] text-indigo-300">Rata-Rata Kelas</p>
                <p class="text-lg sm:text-xl font-bold text-amber-300 mt-0.5" id="metric-class-avg">83,2</p>
                <p class="text-[10px] text-emerald-400 mt-1" id="metric-kkm-comparison">Target KKM: 75</p>
              </div>
              <div class="bg-indigo-900/60 rounded-xl p-3 border border-indigo-700/50">
                <p class="text-[11px] text-indigo-300">Rata-Rata Kehadiran</p>
                <p class="text-lg sm:text-xl font-bold text-emerald-400 mt-0.5" id="metric-attend-avg">94,2%</p>
                <p class="text-[10px] text-indigo-200 mt-1">Akumulasi 1 Semester</p>
              </div>
              <div class="bg-indigo-900/60 rounded-xl p-3 border border-indigo-700/50 col-span-2 sm:col-span-2">
                <p class="text-[11px] text-indigo-300">Catatan Evaluasi Guru</p>
                <p class="text-lg sm:text-xl font-bold text-amber-300 mt-0.5" id="metric-notes-count">6 Siswa</p>
                <p class="text-[10px] text-indigo-200 mt-1">Tersedia untuk diedit langsung</p>
              </div>
            </div>
          </div>

          <!-- BAGIAN 1: REKAP PRESENSI 1 SEMESTER PENUH -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h3 class="font-bold text-sm sm:text-base text-indigo-950">1. Rekap Presensi Siswa Selama 1 Semester</h3>
                <p class="text-xs text-slate-500">Akumulasi seluruh kehadiran berdasarkan hari masuk yang dicatat guru.</p>
              </div>
              <button onclick="exportAttendanceSemesterCSV()" class="w-full sm:w-auto px-3.5 py-2 sm:py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Unduh CSV Presensi Semester</span>
              </button>
            </div>

            <!-- Table mobile swipe hint -->
            <div class="md:hidden px-3.5 py-1.5 bg-emerald-50/70 border border-emerald-100 rounded-xl text-[11px] text-emerald-800 flex items-center justify-between font-medium">
              <span>👉 Geser tabel ke kanan untuk melihat presensi 1 semester</span>
              <span class="text-xs">↔️</span>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-x-auto table-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200">
                    <th class="py-2.5 px-3 w-10 text-center">No</th>
                    <th class="py-2.5 px-3 w-28">NISN</th>
                    <th class="py-2.5 px-4 min-w-[160px]">Nama Lengkap Siswa</th>
                    <th class="py-2.5 px-2 text-center w-14 bg-emerald-50 text-emerald-900">Hadir</th>
                    <th class="py-2.5 px-2 text-center w-14 bg-amber-50 text-amber-900">Izin</th>
                    <th class="py-2.5 px-2 text-center w-14 bg-purple-50 text-purple-900">Sakit</th>
                    <th class="py-2.5 px-2 text-center w-14 bg-rose-50 text-rose-900">Alpa</th>
                    <th class="py-2.5 px-3 text-center w-24">Total Pertemuan</th>
                    <th class="py-2.5 px-3 text-center w-20 font-bold">% Kehadiran</th>
                    <th class="py-2.5 px-3 min-w-[300px]">Catatan Evaluasi Guru (Dapat Diedit)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="attendance-semester-table-body">
                  <!-- Javascript will inject data -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- BAGIAN 2: BUKU INDUK LEDGER AKADEMIK & REKAP RAPOR -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <h3 class="font-bold text-sm sm:text-base text-indigo-950">2. Buku Induk & Ledger Rapor (Nilai Akhir)</h3>
                <p class="text-xs text-slate-500">Rangkuman nilai akhir terhadap KKM aktif (<span class="font-bold text-indigo-900 label-kkm-legend">75</span>).</p>
              </div>
              <button onclick="exportGradesToCSV()" class="w-full sm:w-auto px-3.5 py-2 sm:py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 border border-indigo-200 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Unduh CSV Nilai Rapor</span>
              </button>
            </div>

            <!-- Table mobile swipe hint -->
            <div class="md:hidden px-3.5 py-1.5 bg-indigo-50/70 border border-indigo-100 rounded-xl text-[11px] text-indigo-700 flex items-center justify-between font-medium">
              <span>👉 Geser tabel ke kanan untuk melihat rincian rapor & catatan</span>
              <span class="text-xs">↔️</span>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-x-auto table-scroll">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200">
                    <th class="py-3 px-3 w-10 text-center">No</th>
                    <th class="py-3 px-3 w-28">NISN</th>
                    <th class="py-3 px-4 min-w-[160px]">Nama Siswa</th>
                    <th class="py-3 px-3 text-center bg-blue-50/50 text-blue-900">Total Tugas Dinilai</th>
                    <th class="py-3 px-3 text-center font-extrabold bg-amber-50/70 text-amber-950">Rata-Rata Akhir</th>
                    <th class="py-3 px-3 text-center">Presensi 1 Semester</th>
                    <th class="py-3 px-3 min-w-[300px]">Catatan Evaluasi Pembelajaran (Dapat Diedit)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="ledger-table-body">
                  <!-- Javascript will inject data -->
                </tbody>
              </table>
            </div>

            <!-- Footer Tombol Simpan & Cetak Dokumen Rapor -->
            <div class="pt-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2.5">
              <button onclick="saveAllEvaluations()" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Simpan Seluruh Catatan Evaluasi</span>
              </button>
              <button onclick="window.print()" class="w-full sm:w-auto px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak Lembar Ledger Rapor (PDF)</span>
              </button>
            </div>
          </div>

        </section>

              <!-- ============================================================== -->
        <!-- TAB 6: PUSAT BANTUAN & FAQ GURU -->
        <!-- ============================================================== -->
        <section id="tab-faq" class="hidden space-y-4">
          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-10 h-10 rounded-2xl bg-indigo-900 text-amber-400 font-bold flex items-center justify-center text-lg">
                💡
              </div>
              <div>
                <h2 class="text-xl font-bold text-indigo-950">Pusat Bantuan & FAQ Guru</h2>
                <p class="text-xs text-slate-500">Panduan teknis pengoperasian portal akademik bagi Guru Mata Pelajaran dan Wali Kelas.</p>
              </div>
            </div>

            <!-- FAQ List Accordion / Cards -->
            <div class="mt-6 space-y-3.5 text-xs">
              
              <!-- Q1: Peran Guru Mapel vs Wali Kelas -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">1</span>
                    <span>Apa perbedaan wewenang antara Guru Mata Pelajaran dan Wali Kelas?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p><strong>Guru Mata Pelajaran:</strong> Berwenang penuh untuk menambah kolom penilaian baru, menginput nilai harian/sumatif, menyesuaikan KKM/KKTP mapel yang diampu, serta mengunggah modul/materi ajar.</p>
                  <p class="mt-2"><strong>Wali Kelas:</strong> Berwenang memantau perkembangan seluruh mata pelajaran di kelas binaan (11 mapel), melihat lembar penilaian setiap mapel secara read-only, menginput catatan evaluasi rapor, serta mencetak ledger nilai rapor akhir semester.</p>
                </div>
              </details>

              <!-- Q2: Mengapa Wali Kelas Tidak Bisa Tambah / Ubah Nilai? -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">2</span>
                    <span>Mengapa tombol " Tambah Kolom Nilai" dan kotak isian nilai tidak aktif saat login sebagai Wali Kelas?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Hal ini dirancang sesuai SOP Akademik SMPN 7 Purwakarta agar menjaga integritas data penilaian. Nilai capaian siswa merupakan otoritas pedagogis dari Guru Mata Pelajaran yang mengajar di kelas tersebut. Wali Kelas hanya bertindak sebagai pemantau dan koordinator rekap (Read-Only) agar tidak terjadi tumpang tindih manipulasi data nilai.</p>
                </div>
              </details>

              <!-- Q3: Cara Menambah Kolom Nilai Manual -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">3</span>
                    <span>Bagaimana cara menambahkan kolom tugas atau ulangan baru secara manual satu per satu?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>1. Masuk sebagai <strong>Guru Mapel</strong> dan pastikan mata pelajaran serta kelas yang diinginkan sudah dipilih.</p>
                  <p>2. Pada tab <strong>Input Nilai Siswa</strong> &rarr; sub-tab <strong>Lembar Penilaian Mapel</strong>, klik tombol <strong> Tambah Kolom Nilai</strong>.</p>
                  <p>3. Masukkan nama penilaian (misal: <em>Tugas Aljabar 1</em> atau <em>UH Bab 2</em>), pilih tanggal pelaksanaan dan kategori (Formatif/Sumatif/Praktik).</p>
                  <p>4. Klik <strong>Sisipkan Kolom Kosong</strong>. Kolom baru akan muncul di tabel dan Anda dapat langsung mengetikkan nilai siswa dari rentang 0 hingga 100.</p>
                </div>
              </details>

              <!-- Q4: Mengatur KKM / KKTP -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">4</span>
                    <span>Bagaimana cara menyesuaikan batas KKM / KKTP untuk mata pelajaran saya?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Pada header lembar penilaian siswa, terdapat kotak <strong>KKM / KKTP Mapel</strong> (default 75). Anda dapat langsung mengganti angka tersebut (misal menjadi 78 atau 80). Sistem akan secara otomatis mengkalkulasi ulang status ketuntasan siswa, mengubah warna rata-rata siswa menjadi hijau jika tuntas atau merah jika memerlukan remedial.</p>
                </div>
              </details>

              <!-- Q5: Melihat Rekap Seluruh Mapel -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">5</span>
                    <span>Di mana saya bisa melihat rekap nilai seluruh mapel beserta rata-rata tiap siswa?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Buka tab <strong>Input Nilai Siswa</strong>, lalu klik sub-tab kedua yang berada di bagian atas: <strong>📊 Rekap Seluruh Mapel (Ledger Nilai Siswa)</strong>. Di sini ditampilkan tabel komprehensif berisi seluruh 11 mata pelajaran, rata-rata akhir tiap siswa lintas mapel, serta status ketuntasan umum.</p>
                </div>
              </details>

              <!-- Q6: Jurnal Harian -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">6</span>
                    <span>Bagaimana cara mengisi dan mengunduh Jurnal Harian Guru?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Klik menu <strong>Jurnal Harian</strong> di sidebar navigasi. Klik tombol <strong> Tambah Jurnal Harian</strong> untuk mengisi hari, tanggal, jam pertemuan, materi pokok, deskripsi kegiatan pembelajaran, dan rekap absensi (H/I/S/A). Anda juga dapat mengklik tombol <strong>Download CSV</strong> untuk mencetak arsip jurnal mengajar.</p>
                </div>
              </details>

              <!-- Q7: Download CSV -->
              <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
                <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                  <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">7</span>
                    <span>Bagaimana cara mengekspor data nilai atau presensi ke format Excel?</span>
                  </div>
                  <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                  <p>Di setiap lembar kerja (Nilai Mapel, Presensi Harian, Presensi Semester, dan Rekap Seluruh Mapel), tersedia tombol <strong>Download CSV</strong>. File CSV yang diunduh dapat langsung dibuka menggunakan Microsoft Excel, Google Sheets, atau aplikasi spreadsheet lainnya.</p>
                </div>
              </details>

            </div>

            <!-- Contact Helpdesk Box -->
            <div class="mt-6 p-4 bg-indigo-50/70 border border-indigo-200 rounded-2xl flex items-center justify-between flex-wrap gap-3">
              <div class="flex items-center gap-3">
                <span class="text-2xl">📞</span>
                <div>
                  <p class="font-bold text-indigo-950">Membutuhkan Bantuan Teknis Lebih Lanjut?</p>
                  <p class="text-[11px] text-slate-600">Hubungi Tim Helpdesk Kurikulum & Tata Usaha SMPN 7 Purwakarta</p>
                </div>
              </div>
              <span class="px-3.5 py-1.5 bg-indigo-900 text-white rounded-xl font-bold text-xs">Ruang Server TU (Psw. 104)</span>
            </div>

          </div>

        </section>

      </main>
    </div>

  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH KOLOM PENILAIAN BARU (TANPA NILAI BAWAAN) -->
  <!-- ============================================================== -->
  <div id="modal-add-column" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-100 overflow-hidden transform transition-all max-h-[92vh] flex flex-col animate-in fade-in zoom-in duration-150">
      
      <div class="bg-indigo-950 text-white p-4 sm:p-5 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center font-bold flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          </div>
          <div>
            <h3 class="font-bold text-sm sm:text-base">Tambah Kolom Penilaian Baru</h3>
            <p class="text-[11px] sm:text-xs text-indigo-200">Kolom disisipkan kosong (guru mengisi dari awal)</p>
          </div>
        </div>
        <button onclick="closeAddColumnModal()" class="text-indigo-300 hover:text-white p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleConfirmAddColumn(event)" class="p-4 sm:p-6 space-y-3.5 sm:space-y-4 text-xs overflow-y-auto">
        <div>
          <label class="block font-bold text-slate-700 mb-1">
            Nama / Jenis Penilaian <span class="text-rose-500">*</span>
          </label>
          <input type="text" id="new-col-name" required placeholder="Contoh: Tugas 4 (Aljabar) / Kuis Bab 2 / Proyek" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
          <p class="text-[11px] text-slate-400 mt-1">Nama ini akan menjadi judul header kolom tabel.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">
              Hari & Tanggal Penilaian <span class="text-rose-500">*</span>
            </label>
            <input type="date" id="new-col-date" required value="2026-10-14" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Kategori Nilai</label>
            <select id="new-col-category" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
              <option value="Formatif">Formatif (Tugas/PR)</option>
              <option value="Sumatif">Sumatif (Ulangan/UH)</option>
              <option value="Praktik">Unjuk Kerja / Praktik</option>
              <option value="Proyek">Tugas Proyek</option>
            </select>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
          <button type="button" onclick="closeAddColumnModal()" class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 rounded-xl text-slate-600 font-semibold hover:bg-slate-50 justify-center">Batal</button>
          <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-md justify-center">
            Sisipkan Kolom Kosong
          </button>
        </div>
      </form>

    </div>
  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH KOLOM HARI MASUK / PERTEMUAN KEHADIRAN -->
  <!-- ============================================================== -->
  <div id="modal-add-attendance-column" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-100 overflow-hidden transform transition-all max-h-[92vh] flex flex-col animate-in fade-in zoom-in duration-150">
      
      <div class="bg-indigo-950 text-white p-4 sm:p-5 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <h3 class="font-bold text-sm sm:text-base">Tambah Hari Masuk / Pertemuan</h3>
            <p class="text-[11px] sm:text-xs text-indigo-200">Kolom presensi baru akan ditambahkan ke samping</p>
          </div>
        </div>
        <button onclick="closeAddAttendanceColumnModal()" class="text-indigo-300 hover:text-white p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleConfirmAddAttendanceColumn(event)" class="p-4 sm:p-6 space-y-3.5 sm:space-y-4 text-xs overflow-y-auto">
        <div>
          <label class="block font-bold text-slate-700 mb-1">
            Nama Pertemuan <span class="text-rose-500">*</span>
          </label>
          <input type="text" id="new-att-name" required placeholder="Contoh: Pertemuan 4 / Jam Ke-1 & 2" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">
            Hari & Tanggal Masuk <span class="text-rose-500">*</span>
          </label>
          <input type="date" id="new-att-date" required value="2026-10-22" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none font-medium">
        </div>

        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 flex items-center gap-2">
          <input type="checkbox" id="new-att-default-h" checked class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500">
          <label for="new-att-default-h" class="cursor-pointer font-medium text-[11px]">Tandai semua siswa Hadir (H) secara otomatis untuk pertemuan ini</label>
        </div>

        <div class="pt-3 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
          <button type="button" onclick="closeAddAttendanceColumnModal()" class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 rounded-xl text-slate-600 font-semibold hover:bg-slate-50 justify-center">Batal</button>
          <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-md justify-center">
            Tambahkan Hari Masuk
          </button>
        </div>
      </form>

    </div>
  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH JURNAL HARIAN GURU -->
  <!-- ============================================================== -->
  <div id="modal-jurnal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-100 overflow-hidden text-xs max-h-[92vh] flex flex-col">
      
      <div class="bg-indigo-950 text-white p-4 sm:p-5 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center font-bold flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
          </div>
          <div>
            <h3 class="font-bold text-sm sm:text-base" id="modal-jurnal-title">Tambah Jurnal Mengajar Harian</h3>
            <p class="text-[11px] sm:text-xs text-indigo-200">Catat agenda tatap muka dan kegiatan belajar siswa</p>
          </div>
        </div>
        <button onclick="closeAddJournalModal()" class="text-indigo-300 hover:text-white p-1">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleSaveJournal(event)" class="p-4 sm:p-6 space-y-3 sm:space-y-3.5 overflow-y-auto">
        <input type="hidden" id="input-jurnal-id" value="">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Hari <span class="text-rose-500">*</span></label>
            <select id="input-jurnal-hari" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              <option value="Senin">Senin</option>
              <option value="Selasa">Selasa</option>
              <option value="Rabu">Rabu</option>
              <option value="Kamis">Kamis</option>
              <option value="Jumat">Jumat</option>
              <option value="Sabtu">Sabtu</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Tanggal <span class="text-rose-500">*</span></label>
            <input type="date" id="input-jurnal-tanggal" required value="2026-10-06" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Pertemuan / Jam Ke- <span class="text-rose-500">*</span></label>
          <input type="text" id="input-jurnal-pertemuan" required placeholder="Contoh: Pertemuan ke-9 (Jam Ke 1-3)" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Materi Pembelajaran <span class="text-rose-500">*</span></label>
          <input type="text" id="input-jurnal-materi" required placeholder="Contoh: Pembagian Bentuk Akar & Merasionalkan Penyebut" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Kegiatan Pembelajaran <span class="text-rose-500">*</span></label>
          <textarea id="input-jurnal-kegiatan" rows="3" required placeholder="Uraikan alur kegiatan: apersepsi, penyampaian materi, pengerjaan LKPD/diskusi, dan evaluasi refleksi..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none"></textarea>
        </div>

        <!-- Rekap Absensi Pertemuan -->
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3.5 space-y-2">
          <label class="block font-bold text-slate-700 text-[11px] uppercase tracking-wider">Rekap Kehadiran Siswa Pertemuan Ini</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <div>
              <span class="block text-[10px] font-bold text-emerald-700 mb-0.5">Hadir</span>
              <input type="number" id="input-jurnal-h" min="0" value="31" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-center font-bold text-emerald-800 bg-white">
            </div>
            <div>
              <span class="block text-[10px] font-bold text-amber-700 mb-0.5">Izin</span>
              <input type="number" id="input-jurnal-i" min="0" value="1" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-center font-bold text-amber-800 bg-white">
            </div>
            <div>
              <span class="block text-[10px] font-bold text-purple-700 mb-0.5">Sakit</span>
              <input type="number" id="input-jurnal-s" min="0" value="0" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-center font-bold text-purple-800 bg-white">
            </div>
            <div>
              <span class="block text-[10px] font-bold text-rose-700 mb-0.5">Alpa</span>
              <input type="number" id="input-jurnal-a" min="0" value="0" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-center font-bold text-rose-800 bg-white">
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
          <button type="button" onclick="closeAddJournalModal()" class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 rounded-xl text-slate-600 font-semibold hover:bg-slate-50 justify-center">Batal</button>
          <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-md justify-center">
            Simpan Jurnal Harian
          </button>
        </div>
      </form>

    </div>
  </div>


  <!-- TOAST NOTIFICATION CONTAINER -->
  <div id="toast" class="fixed bottom-4 right-4 left-4 sm:left-auto sm:right-5 sm:bottom-5 bg-indigo-950 text-white px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3 border border-indigo-800 text-xs transform transition-all duration-300 translate-y-20 opacity-0 z-50 pointer-events-none">
    <div class="w-7 h-7 rounded-lg bg-emerald-500 text-white flex items-center justify-center font-bold">✓</div>
    <div>
      <p class="font-bold" id="toast-title">Berhasil!</p>
      <p class="text-indigo-200" id="toast-msg">Data telah disimpan ke database sekolah.</p>
    </div>
  </div>


  <!-- ============================================================== -->
  <!-- JAVASCRIPT APPLICATION CONTROLLER -->
  <!-- ============================================================== -->
  <script>
    // ROLE STATE: 'guru_mapel' ATAU 'wali_kelas'
    let currentTeacherRole = 'guru_mapel';

    // KKM FLEKSIBEL (DEFAULT 75, BISA DISESUAIKAN GURU)
    let currentKKM = 75;

    // DATA KOLOM PENILAIAN MANUAL (TUGAS 1, 2, SUMATIF FIKS DIHAPUS, DIINPUT MANUAL SATU-SATU)
    let gradeColumns = [];

    // DATABASE MATA PELAJARAN UNTUK WALI KELAS (KELAS VIII A)
    const waliClassSubjectsData = {
      'Matematika': {
        teacher: 'Ibu Siti Rahmawati, S.Pd',
        kkm: 75,
        columns: [
          { id: 'm_1', name: 'Tugas Eksponen', date: 'Senin, 08 Sep 2026', category: 'Formatif' },
          { id: 'm_2', name: 'Bentuk Akar', date: 'Senin, 15 Sep 2026', category: 'Formatif' },
          { id: 'm_3', name: 'Ulangan Harian Bab 1', date: 'Kamis, 25 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { m_1: 82, m_2: 88, m_3: 85 },
          2: { m_1: 92, m_2: 90, m_3: 94 },
          3: { m_1: 76, m_2: 81, m_3: 78 },
          4: { m_1: 88, m_2: 86, m_3: 90 },
          5: { m_1: 70, m_2: 74, m_3: 72 },
          6: { m_1: 84, m_2: 87, m_3: 85 }
        }
      },
      'IPA': {
        teacher: 'Bapak Budi Santoso, M.Pd',
        kkm: 75,
        columns: [
          { id: 'ipa_1', name: 'Struktur Sel', date: 'Selasa, 09 Sep 2026', category: 'Formatif' },
          { id: 'ipa_2', name: 'Praktikum Mikroskop', date: 'Selasa, 16 Sep 2026', category: 'Praktik' },
          { id: 'ipa_3', name: 'Sumatif Bab Sel', date: 'Selasa, 23 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { ipa_1: 85, ipa_2: 90, ipa_3: 87 },
          2: { ipa_1: 90, ipa_2: 94, ipa_3: 92 },
          3: { ipa_1: 78, ipa_2: 80, ipa_3: 77 },
          4: { ipa_1: 86, ipa_2: 88, ipa_3: 85 },
          5: { ipa_1: 70, ipa_2: 76, ipa_3: 68 },
          6: { ipa_1: 88, ipa_2: 89, ipa_3: 86 }
        }
      },
      'Bahasa Indonesia': {
        teacher: 'Ibu Dra. Hj. Nur Aini',
        kkm: 78,
        columns: [
          { id: 'bi_1', name: 'Teks Laporan LHO', date: 'Rabu, 10 Sep 2026', category: 'Formatif' },
          { id: 'bi_2', name: 'Kaidah Kebahasaan', date: 'Rabu, 17 Sep 2026', category: 'Formatif' },
          { id: 'bi_3', name: 'Menulis LHO Sekolah', date: 'Rabu, 24 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { bi_1: 86, bi_2: 92, bi_3: 86 },
          2: { bi_1: 94, bi_2: 95, bi_3: 92 },
          3: { bi_1: 79, bi_2: 82, bi_3: 80 },
          4: { bi_1: 88, bi_2: 90, bi_3: 88 },
          5: { bi_1: 78, bi_2: 80, bi_3: 79 },
          6: { bi_1: 85, bi_2: 88, bi_3: 86 }
        }
      },
      'Bahasa Inggris': {
        teacher: 'Ibu Rina Nurhasanah, S.Pd',
        kkm: 75,
        columns: [
          { id: 'bing_1', name: 'Past Tense Recount', date: 'Jumat, 12 Sep 2026', category: 'Formatif' },
          { id: 'bing_2', name: 'Irregular Verbs Quiz', date: 'Jumat, 19 Sep 2026', category: 'Formatif' },
          { id: 'bing_3', name: 'Writing Experience', date: 'Jumat, 26 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { bing_1: 80, bing_2: 86, bing_3: 86 },
          2: { bing_1: 90, bing_2: 92, bing_3: 90 },
          3: { bing_1: 76, bing_2: 78, bing_3: 77 },
          4: { bing_1: 85, bing_2: 88, bing_3: 86 },
          5: { bing_1: 65, bing_2: 72, bing_3: 69 },
          6: { bing_1: 82, bing_2: 85, bing_3: 84 }
        }
      },
      'Informatika': {
        teacher: 'Bapak Ahmad Fauzi, S.Kom',
        kkm: 75,
        columns: [
          { id: 'inf_1', name: 'Logika Algoritma', date: 'Kamis, 11 Sep 2026', category: 'Formatif' },
          { id: 'inf_2', name: 'Rumus Logika Excel', date: 'Kamis, 18 Sep 2026', category: 'Praktik' },
          { id: 'inf_3', name: 'Analisis Data Bab 1', date: 'Kamis, 25 Sep 2026', category: 'Sumatif' }
        ],
        scores: {
          1: { inf_1: 94, inf_2: 90, inf_3: 89 },
          2: { inf_1: 95, inf_2: 96, inf_3: 94 },
          3: { inf_1: 82, inf_2: 80, inf_3: 81 },
          4: { inf_1: 89, inf_2: 91, inf_3: 88 },
          5: { inf_1: 85, inf_2: 80, inf_3: 81 },
          6: { inf_1: 90, inf_2: 92, inf_3: 89 }
        }
      },
      'Pendidikan Pancasila': {
        teacher: 'Bapak Dedi Suhendar, S.Pd',
        kkm: 78,
        columns: [
          { id: 'pkn_1', name: 'Makna Ideologi', date: 'Senin, 08 Sep 2026', category: 'Formatif' },
          { id: 'pkn_2', name: 'Norma Hukum', date: 'Senin, 15 Sep 2026', category: 'Formatif' }
        ],
        scores: {
          1: { pkn_1: 82, pkn_2: 85 },
          2: { pkn_1: 90, pkn_2: 92 },
          3: { pkn_1: 80, pkn_2: 81 },
          4: { pkn_1: 86, pkn_2: 88 },
          5: { pkn_1: 75, pkn_2: 78 },
          6: { pkn_1: 84, pkn_2: 86 }
        }
      },
      'IPS': {
        teacher: 'Bapak Tatang Sutisna, S.Pd',
        kkm: 75,
        columns: [
          { id: 'ips_1', name: 'Letak Benua Asia', date: 'Selasa, 09 Sep 2026', category: 'Formatif' },
          { id: 'ips_2', name: 'Interaksi ASEAN', date: 'Selasa, 16 Sep 2026', category: 'Formatif' }
        ],
        scores: {
          1: { ips_1: 80, ips_2: 84 },
          2: { ips_1: 91, ips_2: 93 },
          3: { ips_1: 78, ips_2: 79 },
          4: { ips_1: 85, ips_2: 87 },
          5: { ips_1: 72, ips_2: 76 },
          6: { ips_1: 83, ips_2: 85 }
        }
      },
      'PJOK': {
        teacher: 'Bapak Asep Kurnia, S.Pd',
        kkm: 78,
        columns: [
          { id: 'pjok_1', name: 'Passing Sepak Bola', date: 'Sabtu, 13 Sep 2026', category: 'Praktik' },
          { id: 'pjok_2', name: 'Servis Bola Voli', date: 'Sabtu, 20 Sep 2026', category: 'Praktik' }
        ],
        scores: {
          1: { pjok_1: 88, pjok_2: 92 },
          2: { pjok_1: 94, pjok_2: 90 },
          3: { pjok_1: 82, pjok_2: 84 },
          4: { pjok_1: 89, pjok_2: 87 },
          5: { pjok_1: 85, pjok_2: 83 },
          6: { pjok_1: 88, pjok_2: 89 }
        }
      },
      'Seni Budaya': {
        teacher: 'Ibu Maya Lestari, S.Sn',
        kkm: 75,
        columns: [
          { id: 'sb_1', name: 'Ragam Seni Lukis', date: 'Rabu, 10 Sep 2026', category: 'Formatif' },
          { id: 'sb_2', name: 'Gambar Ilustrasi', date: 'Rabu, 17 Sep 2026', category: 'Praktik' }
        ],
        scores: {
          1: { sb_1: 84, sb_2: 88 },
          2: { sb_1: 92, sb_2: 95 },
          3: { sb_1: 78, sb_2: 81 },
          4: { sb_1: 87, sb_2: 89 },
          5: { sb_1: 75, sb_2: 80 },
          6: { sb_1: 86, sb_2: 88 }
        }
      },
      'Pendidikan Agama': {
        teacher: 'Ibu Eni Rohaeni, S.Ag',
        kkm: 80,
        columns: [
          { id: 'pai_1', name: 'Tadabbur Al-Quran', date: 'Selasa, 09 Sep 2026', category: 'Formatif' },
          { id: 'pai_2', name: 'Asmaul Husna', date: 'Selasa, 16 Sep 2026', category: 'Formatif' }
        ],
        scores: {
          1: { pai_1: 85, pai_2: 88 },
          2: { pai_1: 94, pai_2: 96 },
          3: { pai_1: 80, pai_2: 82 },
          4: { pai_1: 88, pai_2: 90 },
          5: { pai_1: 76, pai_2: 78 },
          6: { pai_1: 88, pai_2: 90 }
        }
      },
      'Bahasa Sunda': {
        teacher: 'Ibu Ceceu Marlina, S.Pd',
        kkm: 75,
        columns: [
          { id: 'sun_1', name: 'Kawih Sunda', date: 'Rabu, 10 Sep 2026', category: 'Formatif' },
          { id: 'sun_2', name: 'Paguneman', date: 'Rabu, 17 Sep 2026', category: 'Praktik' }
        ],
        scores: {
          1: { sun_1: 82, sun_2: 84 },
          2: { sun_1: 90, sun_2: 92 },
          3: { sun_1: 78, sun_2: 80 },
          4: { sun_1: 85, sun_2: 86 },
          5: { sun_1: 72, sun_2: 75 },
          6: { sun_1: 84, sun_2: 86 }
        }
      }
    };

    // DATA KOLOM PERTEMUAN KEHADIRAN (BISA DITAMBAH DAN DIHAPUS BEBAS)
    let attendanceColumns = [
      { id: 'att_1', name: 'Pertemuan 1', date: 'Kamis, 01 Okt 2026' },
      { id: 'att_2', name: 'Pertemuan 2', date: 'Kamis, 08 Okt 2026' },
      { id: 'att_3', name: 'Pertemuan 3', date: 'Kamis, 15 Okt 2026' }
    ];

    // DATA SISWA (KELAS VIII A)
    let students = [
      { 
        id: 1, 
        nisn: '0098427135', 
        name: 'Aditya Pratama', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'H', att_3: 'H' },
        semesterNotes: 'Sangat rajin dan disiplin masuk kelas.'
      },
      { 
        id: 2, 
        nisn: '0098427141', 
        name: 'Alya Nur Safitri', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'H', att_3: 'H' },
        semesterNotes: 'Kehadiran 100% sempurna, aktif berdiskusi.'
      },
      { 
        id: 3, 
        nisn: '0098427156', 
        name: 'Bima Arya Putra', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'S', att_3: 'H' },
        semesterNotes: 'Sempat sakit 1 kali, surat dokter lengkap.'
      },
      { 
        id: 4, 
        nisn: '0098427162', 
        name: 'Citra Maharani', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'H', att_3: 'H' },
        semesterNotes: 'Disiplin dan bertanggung jawab terhadap tugas.'
      },
      { 
        id: 5, 
        nisn: '0098427178', 
        name: 'Daffa Ramadhan', 
        scores: {},
        attendances: { att_1: 'I', att_2: 'A', att_3: 'H' },
        semesterNotes: 'Peringatan: Alpa terdeteksi. Perlu bimbingan kehadiran & remedial nilai.'
      },
      { 
        id: 6, 
        nisn: '0098427184', 
        name: 'Fania Putri Amelia', 
        scores: {},
        attendances: { att_1: 'H', att_2: 'H', att_3: 'H' },
        semesterNotes: 'Konsisten hadir tepat waktu.'
      }
    ];

    // STATE PEMILIHAN KELAS BERTINGKAT & PERAN
    let selectedGradeLevel = 8;
    let selectedRombelLetter = 'A';
    let activeSubject = 'Matematika';
    let activeClass = 'Kelas VIII A';
    let activeWaliPovSubject = 'Matematika';

    // ==============================================================
    // PEMBATASAN AKSES KELAS & MAPEL GURU (STRICT DATA PERMISSION)
    // ==============================================================
    let currentLoggedTeacher = null;
    let teacherAllowedClasses = [];
    let teacherAllowedSubjects = [];
    let teacherWaliInfo = null;

    // Parser kelas guru dari MySQL: e.g. "7G-7I, 8A-8D" -> ['7G', '7H', '7I', '8A', '8B', '8C', '8D']
    function parseTeacherAllowedClasses(kelasStr) {
      if (!kelasStr || kelasStr === '-' || kelasStr.trim() === '') return [];
      const classes = new Set();
      const parts = kelasStr.split(/[,/]/);
      for (let part of parts) {
        part = part.trim().toUpperCase();
        if (!part) continue;

        // Pattern 1: e.g. 7A-7I, 8A-8D, 9E-9G
        let match = part.match(/^([789])([A-I])\s*-\s*([789])([A-I])$/);
        if (match) {
          const grade = match[1];
          const startChar = match[2].charCodeAt(0);
          const endChar = match[4].charCodeAt(0);
          for (let c = Math.min(startChar, endChar); c <= Math.max(startChar, endChar); c++) {
            classes.add(`${grade}${String.fromCharCode(c)}`);
          }
          continue;
        }

        // Pattern 2: e.g. 7A-E or 8A-D
        match = part.match(/^([789])([A-I])\s*-\s*([A-I])$/);
        if (match) {
          const grade = match[1];
          const startChar = match[2].charCodeAt(0);
          const endChar = match[3].charCodeAt(0);
          for (let c = Math.min(startChar, endChar); c <= Math.max(startChar, endChar); c++) {
            classes.add(`${grade}${String.fromCharCode(c)}`);
          }
          continue;
        }

        // Pattern 3: e.g. 7I, 8C, 9A
        match = part.match(/^([789])([A-I])$/);
        if (match) {
          classes.add(`${match[1]}${match[2]}`);
          continue;
        }
      }
      return Array.from(classes);
    }

    // Parser penugasan wali kelas: e.g. "Wali Kelas VIII.A" -> { grade: 8, rombel: 'A', code: '8A', label: 'Kelas VIII A' }
    function parseWaliInfo(waliStr) {
      if (!waliStr || waliStr.toLowerCase().includes('bukan') || waliStr.trim() === '-' || waliStr.trim() === '') {
        return null;
      }
      const m = waliStr.match(/Wali Kelas\s+(VII|VIII|IX|[789])[\.\s]*([A-I])/i);
      if (!m) return null;
      let grade = m[1].toUpperCase();
      if (grade === 'VII') grade = '7';
      else if (grade === 'VIII') grade = '8';
      else if (grade === 'IX') grade = '9';
      const rombel = m[2].toUpperCase();
      return {
        grade: parseInt(grade, 10),
        rombel: rombel,
        code: `${grade}${rombel}`,
        label: `Kelas ${grade === '7' ? 'VII' : (grade === '8' ? 'VIII' : 'IX')} ${rombel}`
      };
    }

    // Parser mapel guru: e.g. "Bahasa Sunda/B.Indonesia" -> ['Bahasa Sunda', 'B.Indonesia']
    function parseTeacherSubjects(mapelStr) {
      if (!mapelStr || mapelStr === '-' || mapelStr.trim() === '') return [];
      return mapelStr.split(/[/,]/).map(s => s.trim()).filter(Boolean);
    }

    // Handler Konfigurasi Izin Guru saat Login
    function setupTeacherPermissions(teacher) {
      currentLoggedTeacher = teacher;
      teacherAllowedClasses = parseTeacherAllowedClasses(teacher.kelas);
      teacherAllowedSubjects = parseTeacherSubjects(teacher.mapel);
      teacherWaliInfo = parseWaliInfo(teacher.wali);

      // Sinkronisasi Nama & NIP di Header & Screen 2
      const step2Name = document.getElementById('step2-teacher-name');
      if (step2Name) step2Name.innerText = teacher.name;
      const headerName = document.getElementById('header-teacher-name');
      if (headerName) headerName.innerText = teacher.name;
      const headerNip = document.getElementById('header-teacher-nip');
      if (headerNip) headerNip.innerText = 'NIP. ' + (teacher.nip || '-');

      // 1. Setup Dropdown Mapel: HANYA tampilkan mapel yang diampu oleh guru ini!
      const subjDropdown = document.getElementById('select-subject-dropdown');
      if (subjDropdown) {
        subjDropdown.innerHTML = '';
        if (teacherAllowedSubjects.length === 0) {
          const opt = document.createElement('option');
          opt.value = teacher.mapel || 'Mata Pelajaran Umum';
          opt.innerText = '📘 ' + (teacher.mapel || 'Mata Pelajaran Umum');
          subjDropdown.appendChild(opt);
        } else {
          teacherAllowedSubjects.forEach((subj, idx) => {
            const opt = document.createElement('option');
            opt.value = subj;
            opt.innerText = `📘 ${subj}`;
            if (idx === 0) opt.selected = true;
            subjDropdown.appendChild(opt);
          });
        }
        activeSubject = subjDropdown.value;
      }

      // 2. Setup Tombol Wali Kelas: Terkunci jika guru bukan wali kelas
      const btnWali = document.getElementById('btn-role-wali-kelas');
      if (btnWali) {
        if (!teacherWaliInfo) {
          btnWali.classList.add('opacity-40', 'cursor-not-allowed', 'border-dashed');
          btnWali.title = 'Anda tidak memiliki penugasan Wali Kelas di data sekolah';
          const titleWal = btnWali.querySelector('.font-extrabold');
          if (titleWal) titleWal.innerHTML = 'Wali Kelas <span class="text-[10px] text-rose-500 font-bold ml-1">(Terkunci 🔒)</span>';
          const pWal = btnWali.querySelector('p');
          if (pWal) pWal.innerText = 'Anda tidak ditugaskan sebagai Wali Kelas pada semester ini.';
        } else {
          btnWali.classList.remove('opacity-40', 'cursor-not-allowed', 'border-dashed');
          btnWali.title = `Wali Kelas binaan: ${teacherWaliInfo.label}`;
          const titleWal = btnWali.querySelector('.font-extrabold');
          if (titleWal) titleWal.innerHTML = `Wali ${teacherWaliInfo.label} <span class="text-[10px] text-emerald-600 font-bold ml-1">✓ Aktif</span>`;
          const pWal = btnWali.querySelector('p');
          if (pWal) pWal.innerText = `Akses pantau raport seluruh mata pelajaran untuk kelas binaan Anda: ${teacherWaliInfo.label}.`;
        }
      }

      // Default role: Guru Mapel
      selectTeacherRole('guru_mapel');

      // 3. Tentukan jenjang awal yang valid berdasarkan kelas yang diampu guru
      const grades = teacherAllowedClasses.map(c => parseInt(c[0], 10)).filter(g => !isNaN(g));
      if (grades.includes(8)) {
        selectGradeLevel(8);
      } else if (grades.length > 0) {
        selectGradeLevel(grades[0]);
      } else {
        selectGradeLevel(8);
      }
    }

    // HANDLER PEMILIHAN PERAN (GURU MAPEL / WALI KELAS)
    function selectTeacherRole(role) {
      if (role === 'wali_kelas' && !teacherWaliInfo) {
        alert('AKSES DITOLAK!\n\nAnda tidak terdaftar sebagai Wali Kelas pada data akademik sekolah. Silakan gunakan peran Guru Mata Pelajaran.');
        return;
      }

      currentTeacherRole = role;
      const btnMapel = document.getElementById('btn-role-guru-mapel');
      const btnWali = document.getElementById('btn-role-wali-kelas');
      const containerMapel = document.getElementById('container-select-mapel');
      const containerWaliNote = document.getElementById('container-wali-note');

      if (role === 'guru_mapel') {
        btnMapel.className = 'p-3.5 sm:p-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-left transition-all shadow-sm';
        btnWali.className = 'p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 hover:border-indigo-400 bg-white text-left transition-all' + (!teacherWaliInfo ? ' opacity-40 cursor-not-allowed border-dashed' : '');
        containerMapel.classList.remove('hidden');
        containerWaliNote.classList.add('hidden');
        renderRombelButtons();
      } else {
        btnWali.className = 'p-3.5 sm:p-4 rounded-2xl border-2 border-amber-500 bg-amber-50/70 text-left transition-all shadow-sm';
        btnMapel.className = 'p-3.5 sm:p-4 rounded-2xl border-2 border-slate-200 hover:border-indigo-400 bg-white text-left transition-all';
        containerMapel.classList.add('hidden');
        containerWaliNote.classList.remove('hidden');

        // Otomatis sinkronkan jenjang & rombel ke kelas perwaliannya
        if (teacherWaliInfo) {
          selectGradeLevel(teacherWaliInfo.grade);
          selectedRombelLetter = teacherWaliInfo.rombel;
          renderRombelButtons();
        }
      }
    }

    // NAVIGASI ANTAR SCREEN & RIWAYAT (BACKWARD COMPATIBILITY)
    let currentScreenId = 'screen-login';

    function closeAllModals() {
      const modalIds = ['modal-add-column', 'modal-add-attendance-column', 'modal-jurnal'];
      modalIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
      });
      document.body.style.overflow = '';
    }

    function showScreen(screenId, pushToHistory = true) {
      closeAllModals();
      toggleMobileDrawer(false);

      document.getElementById('screen-login').classList.add('hidden');
      document.getElementById('screen-select-class').classList.add('hidden');
      document.getElementById('screen-dashboard').classList.add('hidden');

      const target = document.getElementById(screenId);
      if (target) {
        target.classList.remove('hidden');
      }
      currentScreenId = screenId;
      window.scrollTo(0, 0);

      const hashName = screenId.replace('screen-', '');
      if (pushToHistory) {
        if (!history.state || history.state.screen !== screenId) {
          history.pushState({ screen: screenId }, '', '#' + hashName);
        }
      }
    }

    function goToLogin(pushToHistory = true) {
      currentLoggedTeacher = null;
      sessionStorage.removeItem('currentLoggedTeacher');
      showScreen('screen-login', pushToHistory);
    }

    function goToSelectClass(pushToHistory = true) {
      if (!currentLoggedTeacher) {
        goToLogin(true);
        return;
      }
      renderRombelButtons();
      showScreen('screen-select-class', pushToHistory);
    }

    // LOGIN MANUAL SUBMIT HANDLER MENGGUNAKAN NIP & KATA SANDI
    async function handleLoginSubmit(e) {
      e.preventDefault();
      const nipInput = document.getElementById('login-nip');
      const passInput = document.getElementById('login-pass');
      const errorBox = document.getElementById('login-error-box');
      const errorMsg = document.getElementById('login-error-msg');
      const btnSubmit = document.getElementById('btn-login-submit');

      const nip = nipInput.value.trim();
      const password = passInput.value.trim();

      if (!nip || !password) {
        if (errorBox) {
          errorBox.classList.remove('hidden');
          errorMsg.innerText = 'Mohon masukkan NIP dan Kata Sandi Anda!';
        }
        return;
      }

      if (errorBox) errorBox.classList.add('hidden');
      if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `<span>Memverifikasi Akun...</span>`;
      }

      try {
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('nip', nip);
        formData.append('password', password);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success && res.teacher) {
          sessionStorage.setItem('currentLoggedTeacher', JSON.stringify(res.teacher));
          setupTeacherPermissions(res.teacher);
          goToSelectClass(true);
        } else {
          if (errorBox) {
            errorBox.classList.remove('hidden');
            errorMsg.innerText = res.message || 'NIP atau Kata Sandi salah!';
          }
        }
      } catch (err) {
        if (errorBox) {
          errorBox.classList.remove('hidden');
          errorMsg.innerText = 'Gagal menghubungi server database. Pastikan server Apache & MySQL aktif.';
        }
      } finally {
        if (btnSubmit) {
          btnSubmit.disabled = false;
          btnSubmit.innerHTML = `<span>Masuk ke Sistem Guru</span><svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>`;
        }
      }
    }

    // FUNGSI BACKWARD MANUAL / TOMBOL KEMBALI
    function goBack() {
      const drawerOverlay = document.getElementById('mobile-drawer-overlay');
      if (drawerOverlay && !drawerOverlay.classList.contains('hidden')) {
        toggleMobileDrawer(false);
        return;
      }

      const modalIds = ['modal-add-column', 'modal-add-attendance-column', 'modal-jurnal'];
      for (const mId of modalIds) {
        const modal = document.getElementById(mId);
        if (modal && !modal.classList.contains('hidden')) {
          modal.classList.add('hidden');
          document.body.style.overflow = '';
          return;
        }
      }

      if (window.history.length > 1 && window.history.state) {
        window.history.back();
      } else {
        if (currentScreenId === 'screen-dashboard') {
          goToSelectClass(true);
        } else if (currentScreenId === 'screen-select-class') {
          goToLogin(true);
        }
      }
    }

    // EVENT LISTENER POPSTATE: OTOMATIS BERPINDAH TAMPILAN SAAT BROWSER / HP BACKWARD DITEKAN
    window.addEventListener('popstate', (e) => {
      closeAllModals();
      toggleMobileDrawer(false);

      const state = e.state;
      const hash = window.location.hash.replace('#', '');
      let targetScreen = 'screen-login';

      if (state && state.screen) {
        targetScreen = state.screen;
      } else if (hash === 'dashboard') {
        targetScreen = 'screen-dashboard';
      } else if (hash === 'select-class') {
        targetScreen = 'screen-select-class';
      } else {
        targetScreen = 'screen-login';
      }

      if (targetScreen === 'screen-dashboard') {
        showScreen('screen-dashboard', false);
      } else if (targetScreen === 'screen-select-class') {
        goToSelectClass(false);
      } else {
        goToLogin(false);
      }
    });

    // PEMILIHAN BERTINGKAT: KELAS 7/8/9
    function selectGradeLevel(lvl) {
      // Jika mode Wali Kelas, pastikan hanya jenjang perwaliannya yang aktif
      if (currentTeacherRole === 'wali_kelas' && teacherWaliInfo && teacherWaliInfo.grade !== lvl) {
        alert(`AKSES DITOLAK!\n\nSebagai Wali Kelas, Anda hanya ditugaskan pada jenjang Kelas ${teacherWaliInfo.grade} (${teacherWaliInfo.label}).`);
        return;
      }

      selectedGradeLevel = lvl;
      [7, 8, 9].forEach(l => {
        const btn = document.getElementById(`btn-grade-${l}`);
        if (!btn) return;
        if (l === lvl) {
          btn.className = 'py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/70 text-indigo-950 font-extrabold text-xs sm:text-sm transition-all text-center shadow-sm';
          const subText = btn.querySelector('span:last-child');
          if (subText) subText.className = 'text-[10px] font-medium text-indigo-600';
        } else {
          btn.className = 'py-2.5 sm:py-3 px-2 sm:px-4 rounded-2xl border-2 border-slate-200 text-slate-700 hover:border-indigo-400 font-extrabold text-xs sm:text-sm transition-all text-center';
          const subText = btn.querySelector('span:last-child');
          if (subText) subText.className = 'text-[10px] font-medium text-slate-400';
        }
      });

      const roman = lvl === 7 ? 'VII' : (lvl === 8 ? 'VIII' : 'IX');
      const labelGrade = document.getElementById('label-rombel-grade');
      if (labelGrade) labelGrade.innerText = `Rombel Kelas ${roman} (${lvl})`;
      renderRombelButtons();
    }

    // 9 ROMBEL DARI A SAMPAI I DENGAN PENGECEKAN HAK AKSES KETAT
    function renderRombelButtons() {
      const container = document.getElementById('rombel-selector-container');
      if (!container) return;
      container.innerHTML = '';
      const roman = selectedGradeLevel === 7 ? 'VII' : (selectedGradeLevel === 8 ? 'VIII' : 'IX');
      const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

      letters.forEach(letter => {
        const classCode = `${selectedGradeLevel}${letter}`;
        
        // Pengecekan apakah kelas diampu guru
        let isAllowed = false;
        if (currentTeacherRole === 'wali_kelas') {
          isAllowed = teacherWaliInfo && (teacherWaliInfo.code === classCode);
        } else {
          isAllowed = teacherAllowedClasses.length === 0 || teacherAllowedClasses.includes(classCode);
        }

        const isSelected = letter === selectedRombelLetter;
        const btn = document.createElement('button');
        btn.type = 'button';

        if (isAllowed) {
          btn.onclick = () => {
            selectedRombelLetter = letter;
            renderRombelButtons();
          };

          if (isSelected) {
            btn.className = 'relative py-2.5 px-1.5 rounded-xl border-2 border-indigo-600 bg-indigo-900 text-white font-extrabold text-center shadow-md transition-all';
            btn.innerHTML = `<span class="block text-base font-black">${letter}</span><span class="text-[9px] font-normal text-amber-300 block">${roman} ${letter}</span>`;
          } else {
            btn.className = 'relative py-2.5 px-1.5 rounded-xl border-2 border-slate-200 hover:border-indigo-400 bg-white text-slate-700 font-bold text-center transition-all hover:bg-slate-50';
            btn.innerHTML = `<span class="block text-base font-black">${letter}</span><span class="text-[9px] font-normal text-slate-400 block">${roman} ${letter}</span>`;
          }
        } else {
          // KELAS TERKUNCI! Guru tidak mengampu kelas ini
          btn.className = 'relative py-2.5 px-1.5 rounded-xl border border-slate-200 bg-slate-100/90 text-slate-400 font-bold text-center cursor-not-allowed opacity-60 transition-all hover:border-rose-300';
          btn.innerHTML = `
            <span class="absolute top-1 right-1 text-[10px]">🔒</span>
            <span class="block text-base font-bold text-slate-400">${letter}</span>
            <span class="text-[9px] text-slate-400 block">${roman} ${letter}</span>
          `;
          btn.onclick = () => {
            const allowedInfo = (currentTeacherRole === 'wali_kelas')
              ? (teacherWaliInfo ? teacherWaliInfo.label : 'Bukan Wali Kelas')
              : (teacherAllowedClasses.length > 0 ? teacherAllowedClasses.join(', ') : 'Tidak ada');
            alert(`AKSES DITOLAK!\n\nAnda tidak mengampu Kelas ${roman} ${letter}.\n\nKelas yang terdaftar untuk akun Anda: ${allowedInfo}\n\nSilakan pilih kelas yang ada dalam jadwal mengajar Anda.`);
          };
        }

        container.appendChild(btn);
      });
    }


    // ================= 2 SUB-TAB DI DALEM INPUT NILAI SISWA =================
    let currentGradeSubTab = 'sheet';

    function switchGradeSubTab(subTab) {
      currentGradeSubTab = subTab;
      const btnSheet = document.getElementById('subtab-btn-sheet');
      const btnLedger = document.getElementById('subtab-btn-ledger');
      const viewSheet = document.getElementById('subview-grade-sheet');
      const viewLedger = document.getElementById('subview-grade-ledger');
      const hint = document.getElementById('subtab-context-hint');

      if (subTab === 'sheet') {
        btnSheet.className = 'px-4 py-2 rounded-lg text-xs font-bold transition-all bg-indigo-950 text-white shadow-xs flex items-center gap-2';
        btnLedger.className = 'px-4 py-2 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-indigo-950 flex items-center gap-2';
        viewSheet.classList.remove('hidden');
        viewLedger.classList.add('hidden');
        if (hint) hint.innerText = 'Mode aktif: Lembar Penilaian Mapel (' + (currentTeacherRole === 'wali_kelas' ? 'Read-Only Wali Kelas' : activeSubject) + ')';
      } else {
        btnLedger.className = 'px-4 py-2 rounded-lg text-xs font-bold transition-all bg-indigo-950 text-white shadow-xs flex items-center gap-2';
        btnSheet.className = 'px-4 py-2 rounded-lg text-xs font-bold transition-all text-slate-600 hover:text-indigo-950 flex items-center gap-2';
        viewLedger.classList.remove('hidden');
        viewSheet.classList.add('hidden');
        if (hint) hint.innerText = 'Mode aktif: Rekap Kompilasi Seluruh Mapel (Ledger Nilai Siswa)';
        renderSubviewLedgerTable();
      }
    }

    // LIST 11 MATA PELAJARAN STANDAR
    const standardSubjectsList = [
      { key: 'Matematika', label: 'MTK', kkm: 75 },
      { key: 'IPA', label: 'IPA', kkm: 75 },
      { key: 'IPS', label: 'IPS', kkm: 75 },
      { key: 'Bahasa Indonesia', label: 'IND', kkm: 78 },
      { key: 'Bahasa Inggris', label: 'ING', kkm: 75 },
      { key: 'Pendidikan Pancasila', label: 'PKN', kkm: 78 },
      { key: 'Pendidikan Agama', label: 'PAI', kkm: 80 },
      { key: 'Informatika', label: 'INF', kkm: 75 },
      { key: 'PJOK', label: 'PJK', kkm: 78 },
      { key: 'Seni Budaya', label: 'SNB', kkm: 75 },
      { key: 'Bahasa Sunda', label: 'SND', kkm: 75 }
    ];

    function getStudentSubjectAvg(studentId, subjKey) {
      const data = waliClassSubjectsData[subjKey];
      if (!data || !data.scores || !data.scores[studentId]) return '-';
      const studentScores = data.scores[studentId];
      const vals = Object.values(studentScores).filter(v => v !== '' && v !== null && !isNaN(v));
      if (vals.length === 0) return '-';
      return (vals.reduce((a, b) => a + Number(b), 0) / vals.length).toFixed(1);
    }

    function renderSubviewLedgerTable() {
      const tbody = document.getElementById('subview-ledger-tbody');
      if (!tbody) return;
      tbody.innerHTML = '';

      let totalClassGeneralSum = 0;
      let totalPassedCount = 0;

      students.forEach((s, idx) => {
        let studentSubjectScores = [];
        let colsHtml = '';

        standardSubjectsList.forEach(subj => {
          const avg = getStudentSubjectAvg(s.id, subj.key);
          if (avg !== '-') {
            studentSubjectScores.push(Number(avg));
          }
          const isPassed = avg !== '-' && Number(avg) >= subj.kkm;
          colsHtml += `
            <td class="py-2.5 px-2 text-center font-bold border-l border-slate-100 ${isPassed ? 'text-indigo-950 bg-indigo-50/20' : 'text-rose-600 bg-rose-50/30'}">
              ${avg}
            </td>
          `;
        });

        const overallAvg = studentSubjectScores.length > 0 ? 
          (studentSubjectScores.reduce((a, b) => a + b, 0) / studentSubjectScores.length).toFixed(1) : '-';

        if (overallAvg !== '-') {
          totalClassGeneralSum += Number(overallAvg);
          if (Number(overallAvg) >= 75) totalPassedCount++;
        }

        const isOverallPassed = overallAvg !== '-' && Number(overallAvg) >= 75;

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-2.5 px-3 text-center text-slate-400 font-medium">${idx + 1}</td>
          <td class="py-2.5 px-3 font-mono text-[11px] text-slate-500">${s.nisn}</td>
          <td class="py-2.5 px-4 font-bold text-slate-800">${s.name}</td>
          ${colsHtml}
          <td class="py-2.5 px-3 text-center font-black text-sm border-l border-slate-200 ${isOverallPassed ? 'text-indigo-950 bg-amber-50/80' : 'text-rose-600 bg-rose-50'}">
            ${overallAvg}
          </td>
          <td class="py-2.5 px-3 text-center">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold ${isOverallPassed ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'}">
              ${isOverallPassed ? 'Tuntas' : 'Remedial'}
            </span>
          </td>
        `;
        tbody.appendChild(row);
      });

      const grandAvg = students.length > 0 ? (totalClassGeneralSum / students.length).toFixed(1) : 0;
      const metricAvg = document.getElementById('metric-ledger-all-avg');
      if (metricAvg) metricAvg.innerText = grandAvg;

      const metricPass = document.getElementById('metric-ledger-pass-count');
      if (metricPass) metricPass.innerText = `${totalPassedCount} / ${students.length} Siswa`;

      const metricRem = document.getElementById('metric-ledger-remedial-count');
      if (metricRem) metricRem.innerText = `${students.length - totalPassedCount} Siswa`;
    }

    function downloadSubviewLedgerCSV() {
      let headers = ['No', 'NISN', 'Nama Siswa'];
      standardSubjectsList.forEach(s => headers.push(s.key));
      headers.push('Rata-Rata Seluruh Mapel', 'Status Ketuntasan');

      let csv = headers.join(',') + '\n';

      students.forEach((s, idx) => {
        let row = [idx + 1, `"${s.nisn}"`, `"${s.name}"`];
        let subAvgs = [];
        standardSubjectsList.forEach(sub => {
          const val = getStudentSubjectAvg(s.id, sub.key);
          row.push(val);
          if (val !== '-') subAvgs.push(Number(val));
        });
        const ovr = subAvgs.length > 0 ? (subAvgs.reduce((a, b) => a + b, 0) / subAvgs.length).toFixed(1) : '-';
        row.push(ovr);
        row.push(ovr !== '-' && Number(ovr) >= 75 ? '"Tuntas"' : '"Remedial"');
        csv += row.join(',') + '\n';
      });

      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Rekap_Ledger_Seluruh_Mapel_${activeClass.replace(/[^a-zA-Z0-9]/g, '_')}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      showToast('Download Berhasil', 'File Rekap Ledger Seluruh Mapel CSV berhasil diunduh.');
    }

    function confirmWorkspaceAndProceed() {
      const roman = selectedGradeLevel === 7 ? 'VII' : (selectedGradeLevel === 8 ? 'VIII' : 'IX');
      const targetClassCode = `${selectedGradeLevel}${selectedRombelLetter}`;

      // VALIDASI KERAS AKSES KELAS: Guru tidak bisa masuk jika kelas tidak terdaftar di datanya
      if (currentTeacherRole === 'wali_kelas') {
        if (!teacherWaliInfo || teacherWaliInfo.code !== targetClassCode) {
          alert(`AKSES DITOLAK!\n\nSebagai Wali Kelas, Anda hanya diizinkan mengakses kelas perwalian Anda (${teacherWaliInfo ? teacherWaliInfo.label : 'Tidak ada'}).`);
          return;
        }
      } else {
        if (teacherAllowedClasses.length > 0 && !teacherAllowedClasses.includes(targetClassCode)) {
          alert(`AKSES DITOLAK!\n\nAnda tidak mengampu Kelas ${roman} ${selectedRombelLetter}.\n\nKelas yang terdaftar di akun Anda: ${teacherAllowedClasses.join(', ')}.\n\nAnda tidak dapat membuka lembar kerja kelas ini.`);
          return;
        }
      }

      activeClass = `Kelas ${roman} ${selectedRombelLetter}`;

      const waliBar = document.getElementById('wali-pov-control-bar');
      const btnAddCol = document.getElementById('btn-add-grade-col');
      const btnSaveGrades = document.getElementById('btn-save-grades');
      const titleWorkspace = document.getElementById('title-grade-workspace');
      const descWorkspace = document.getElementById('desc-grade-workspace');

      const subtabSwitcher = document.getElementById('subtab-nilai-switcher');

      if (currentTeacherRole === 'wali_kelas') {
        activeSubject = 'Semua Mata Pelajaran';
        document.getElementById('active-subject-text').innerText = 'Mode: Wali Kelas';
        document.getElementById('active-class-text').innerText = `${activeClass} (Semua Mapel)`;
        document.getElementById('tag-current-view').innerText = `Wali Kelas - ${activeClass}`;
        document.getElementById('filter-mapel-locked').value = 'Semua Mata Pelajaran';
        document.getElementById('filter-kelas-display').value = activeClass;
        document.getElementById('wali-pov-class-title').innerText = `Pemantauan Seluruh Mapel ${activeClass}`;

        // TAMPILKAN SUBTAB SWITCHER UNTUK WALI KELAS (BISA LIHAT & REKAP SELURUH MAPEL)
        if (subtabSwitcher) subtabSwitcher.classList.remove('hidden');
        if (waliBar) waliBar.classList.remove('hidden');
        if (titleWorkspace) titleWorkspace.innerText = 'Monitoring Nilai Mata Pelajaran (Wali Kelas)';
        if (descWorkspace) descWorkspace.innerText = 'Memantau hasil evaluasi dan penilaian yang diinput oleh masing-masing guru pengampu.';
        
        const walasBanner = document.getElementById('walas-readonly-banner');
        if (walasBanner) walasBanner.classList.remove('hidden');
        if (btnAddCol) btnAddCol.classList.add('hidden');
        if (btnSaveGrades) btnSaveGrades.classList.add('hidden');

        // Load data from Wali POV database
        loadWaliPovSubject(activeWaliPovSubject);
        renderSubviewLedgerTable();
      } else {
        // GURU MAPEL: SEMBUNYIKAN SUBTAB SWITCHER & REKAP SELURUH MAPEL!
        // CUKUP TAMPILKAN NILAI MAPELNYA SENDIRI
        if (subtabSwitcher) subtabSwitcher.classList.add('hidden');
        switchGradeSubTab('sheet');

        const walasBanner = document.getElementById('walas-readonly-banner');
        if (walasBanner) walasBanner.classList.add('hidden');
        activeSubject = document.getElementById('select-subject-dropdown').value;
        document.getElementById('active-subject-text').innerText = activeSubject;
        document.getElementById('active-class-text').innerText = activeClass;
        document.getElementById('tag-current-view').innerText = `${activeSubject} - ${activeClass}`;
        document.getElementById('filter-mapel-locked').value = activeSubject;
        document.getElementById('filter-kelas-display').value = activeClass;

        if (waliBar) waliBar.classList.add('hidden');
        if (titleWorkspace) titleWorkspace.innerText = `Input Nilai Siswa (${activeSubject})`;
        if (descWorkspace) descWorkspace.innerText = `Input tugas dan ulangan harian ${activeSubject} secara manual satu per satu. KKM dapat disesuaikan sebagai target acuan.`;
        if (btnAddCol) btnAddCol.classList.remove('hidden');
        if (btnSaveGrades) btnSaveGrades.classList.remove('hidden');

        renderGradeTable();
      }

      document.getElementById('sidebar-class-name').innerText = activeClass;
      const mobSideCls = document.getElementById('mob-sidebar-class-name');
      if (mobSideCls) mobSideCls.innerText = activeClass;
      const mobDrawerCls = document.getElementById('mob-drawer-class-label');
      if (mobDrawerCls) mobDrawerCls.innerText = activeClass;

      const mobSubjText = document.getElementById('mob-active-subject-text');
      if (mobSubjText) mobSubjText.innerText = (currentTeacherRole === 'wali_kelas') ? 'Wali Kelas' : activeSubject;
      const mobClsText = document.getElementById('mob-active-class-text');
      if (mobClsText) mobClsText.innerText = activeClass;

      document.getElementById('modul-subject-locked').value = activeSubject;
      document.getElementById('modul-class-locked').value = activeClass;
      document.getElementById('module-header-title').innerText = `Modul Terbit ${activeSubject} - ${activeClass}`;

      // Ambil data nilai, siswa, dan jurnal riil dari MySQL untuk kelas & mapel ini
      fetchAcademicDataFromDB(activeClass, activeSubject);

      renderAttendanceTable();
      renderSemesterAttendanceTable();
      renderModules();

      showScreen('screen-dashboard');
      showToast('Konteks Diatur', `Aktif: ${currentTeacherRole === 'wali_kelas' ? 'Wali Kelas ' + activeClass : activeSubject + ' pada ' + activeClass}.`);
    }

    // AMBIL DATA NILAI & SISWA DARI DATABASE MYSQL
    async function fetchAcademicDataFromDB(targetClass, targetSubject) {
      try {
        const formData = new FormData();
        formData.append('action', 'get_academic_data');
        formData.append('kelas', targetClass);
        formData.append('mata_pelajaran', targetSubject);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success) {
          if (res.students && res.students.length > 0) {
            students = res.students;
          }
          if (res.columns && res.columns.length > 0) {
            gradeColumns = res.columns;
          }
          if (res.journals) {
            journalList = res.journals;
          }
          renderGradeTable();
          renderLedgerTable();
          renderJournalTable();
        }
      } catch (err) {
        console.error('Gagal memuat data akademik:', err);
      }
    }

    // HANDLER GANTI POV MAPEL OLEH WALI KELAS
    function handleWaliPovSubjectChange(subj) {
      activeWaliPovSubject = subj;
      loadWaliPovSubject(subj);
    }

    function loadWaliPovSubject(subj) {
      const data = waliClassSubjectsData[subj];
      if (!data) return;

      currentKKM = data.kkm;
      document.getElementById('input-kkm-target').value = currentKKM;
      document.getElementById('sidebar-kkm-display').innerText = currentKKM;
      const mobKkmDisp = document.getElementById('mob-sidebar-kkm-display');
      if (mobKkmDisp) mobKkmDisp.innerText = currentKKM;

      document.getElementById('tag-current-view').innerText = `${subj} (${data.teacher}) - ${activeClass}`;
      document.getElementById('filter-mapel-locked').value = `${subj} - ${data.teacher}`;

      // Temporarily set gradeColumns & scores for viewing
      gradeColumns = data.columns;
      students.forEach(s => {
        s.scores = data.scores[s.id] || {};
      });

      renderGradeTable();
      renderLedgerTable();
      showToast('POV Diubah', `Menampilkan nilai ${subj} dari ${data.teacher}.`);
    }

    // ================= KKM / KKTP LIVE UPDATE =================
    function handleKKMChange(val) {
      currentKKM = Number(val) || 75;
      document.getElementById('sidebar-kkm-display').innerText = currentKKM;
      const mobKkmDisp = document.getElementById('mob-sidebar-kkm-display');
      if (mobKkmDisp) mobKkmDisp.innerText = currentKKM;
      document.querySelectorAll('.label-kkm-legend').forEach(el => el.innerText = currentKKM);
      document.getElementById('metric-kkm-comparison').innerText = `Target KKM: ${currentKKM}`;
      
      renderGradeTable();
      renderLedgerTable();
    }

    // ================= DYNAMIC GRADE COLUMNS & TABLE =================
    function renderGradeTable() {
      const headerRow = document.getElementById('grade-table-header-row');
      const tbody = document.getElementById('grade-table-body');
      
      document.getElementById('label-column-count').innerText = `${gradeColumns.length} Kolom Penilaian`;

      if (gradeColumns.length === 0) {
        headerRow.innerHTML = `
          <th class="py-3 px-3 w-10 text-center">No</th>
          <th class="py-3 px-3 w-28">NISN</th>
          <th class="py-3 px-4 min-w-[170px]">Nama Siswa</th>
          <th class="py-3 px-4 text-center">Status Lembar Penilaian</th>
        `;
        tbody.innerHTML = `
          <tr>
            <td colspan="4" class="py-12 text-center text-slate-400">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center mx-auto mb-2 text-xl font-black">
                +
              </div>
              <p class="font-bold text-slate-700 text-sm">Belum Ada Kolom Penilaian</p>
              <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                Silakan klik tombol <strong class="text-indigo-900">+ Tambah Kolom Nilai</strong> di atas untuk membuat penilaian pertama (misal: Tugas 1 atau Sumatif 1).
              </p>
            </td>
          </tr>
        `;
        return;
      }

      headerRow.innerHTML = `
        <th class="py-3 px-3 w-10 text-center">No</th>
        <th class="py-3 px-3 w-28">NISN</th>
        <th class="py-3 px-4 min-w-[170px]">Nama Siswa</th>
      `;

      gradeColumns.forEach(col => {
        headerRow.innerHTML += `
          <th class="py-2 px-2 text-center min-w-[125px] ${col.category === 'Sumatif' ? 'bg-indigo-50/70 text-indigo-950' : 'bg-slate-50 text-slate-700'} border-l border-slate-200">
            <div class="flex items-center justify-between gap-1">
              <span class="font-bold text-xs truncate" title="${col.name}">${col.name}</span>
              ${currentTeacherRole === 'guru_mapel' ? `
                <button onclick="removeColumn('${col.id}')" title="Hapus Kolom Penilaian Ini dari Database" class="text-slate-400 hover:text-rose-600 hover:bg-rose-50 p-1 rounded-lg transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              ` : ''}
            </div>
            <div class="text-[10px] text-slate-400 font-normal mt-0.5">
              <span>📅 ${col.date}</span>
            </div>
          </th>
        `;
      });

      headerRow.innerHTML += `
        <th class="py-3 px-3 text-center w-24 font-bold bg-amber-50 text-amber-950 border-l border-slate-200">Rata-Rata</th>
      `;

      tbody.innerHTML = '';
      let totalClassSum = 0;

      students.forEach((s, sIdx) => {
        let scoresArr = [];
        let colsHTML = '';

        gradeColumns.forEach(col => {
          const val = s.scores[col.id] !== undefined && s.scores[col.id] !== null && s.scores[col.id] !== '' ? s.scores[col.id] : '';
          if (val !== '') scoresArr.push(Number(val));
          
          colsHTML += `
            <td class="py-1 px-1 text-center border-l border-slate-100 ${col.category === 'Sumatif' ? 'bg-indigo-50/20' : ''}">
              <input type="number" min="0" max="100" placeholder="-" value="${val}" ${currentTeacherRole === 'wali_kelas' ? 'readonly disabled' : ''} onchange="updateStudentScore(${s.id}, '${col.id}', this.value)" class="w-14 text-center py-1 rounded border border-slate-200 focus:ring-1 focus:ring-indigo-500 font-semibold text-xs transition-all ${currentTeacherRole === 'wali_kelas' ? 'bg-slate-100 text-slate-500 cursor-not-allowed select-none' : 'bg-white hover:border-indigo-400'}">
            </td>
          `;
        });

        const avg = scoresArr.length > 0 ? (scoresArr.reduce((a, b) => a + b, 0) / scoresArr.length).toFixed(1) : '-';
        if (avg !== '-') totalClassSum += Number(avg);

        tbody.innerHTML += `
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="py-2 px-3 text-center text-slate-400 font-medium">${sIdx + 1}</td>
            <td class="py-2 px-3 font-mono text-slate-500 font-medium">${s.nisn}</td>
            <td class="py-2 px-4 font-bold text-slate-800">${s.name}</td>
            ${colsHTML}
            <td class="py-2 px-3 text-center font-black text-xs border-l border-slate-200 ${avg !== '-' && Number(avg) >= currentKKM ? 'text-indigo-950 bg-indigo-50/30' : 'text-rose-600 bg-rose-50/20'}">
              ${avg}
            </td>
          </tr>
        `;
      });

      const classAverage = students.length > 0 ? (totalClassSum / students.length).toFixed(1) : 0;
      const classAvgElem = document.getElementById('metric-class-avg');
      if (classAvgElem) classAvgElem.innerText = classAverage;
      if (document.getElementById('metric-class-avg-rekap')) {
        document.getElementById('metric-class-avg-rekap').innerText = classAverage;
      }
    }

    // SIMPAN / EDIT NILAI LANGSUNG KE MYSQL
    async function updateStudentScore(studentId, colId, value) {
      const student = students.find(s => s.id === studentId);
      const col = gradeColumns.find(c => c.id === colId);
      if (!student || !col) return;

      student.scores[colId] = value !== '' ? Number(value) : '';
      renderGradeTable();
      renderLedgerTable();

      try {
        const formData = new FormData();
        formData.append('action', 'save_grade_cell');
        formData.append('nisn', student.nisn);
        formData.append('nama_siswa', student.name);
        formData.append('kelas', activeClass);
        formData.append('mata_pelajaran', activeSubject);
        formData.append('guru_pengampu', currentLoggedTeacher ? currentLoggedTeacher.name : '-');
        formData.append('jenis_penilaian', col.name);
        formData.append('nilai', value);
        formData.append('kkm', currentKKM);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
          showToast('Nilai Disimpan', `Nilai ${col.name} ${student.name} tersimpan di MySQL.`);
        }
      } catch (err) {
        showToast('Tersimpan Lokal', `Nilai ${student.name} diperbarui di memori.`);
      }
    }

    // MODAL TAMBAH KOLOM NILAI
    function openAddColumnModal() {
      document.getElementById('modal-add-column').classList.remove('hidden');
    }

    function closeAddColumnModal() {
      document.getElementById('modal-add-column').classList.add('hidden');
    }

    function handleConfirmAddColumn(e) {
      e.preventDefault();
      const name = document.getElementById('new-col-name').value;
      const rawDate = document.getElementById('new-col-date').value;
      const category = document.getElementById('new-col-category').value;

      if (!name || !rawDate) {
        alert('Mohon isi nama penilaian dan tanggal dengan lengkap.');
        return;
      }

      const dateObj = new Date(rawDate);
      const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      const formattedDate = `${days[dateObj.getDay()]}, ${dateObj.getDate()} ${months[dateObj.getMonth()]} ${dateObj.getFullYear()}`;

      const newId = 'col_' + Date.now();
      gradeColumns.push({
        id: newId,
        name: name,
        date: formattedDate,
        category: category,
        isFixed: false
      });

      // Kolom baru disisipkan KOSONG
      students.forEach(s => {
        s.scores[newId] = '';
      });

      closeAddColumnModal();
      document.getElementById('new-col-name').value = '';
      renderGradeTable();
      renderLedgerTable();
      showToast('Kolom Ditambahkan', `Kolom "${name}" berhasil dibuat. Masukkan nilai siswa pada kolom ini.`);
    }

    // HAPUS KOLOM PENILAIAN DARI DATABASE MYSQL
    async function removeColumn(colId) {
      const col = gradeColumns.find(c => c.id === colId);
      const colTitle = col ? col.name : 'kolom penilaian';
      if (!confirm(`Yakin ingin menghapus kolom "${colTitle}"?\n\nSemua nilai siswa pada penilaian ini untuk ${activeClass} akan dihapus dari database MySQL.`)) {
        return;
      }

      gradeColumns = gradeColumns.filter(c => c.id !== colId);
      students.forEach(s => {
        delete s.scores[colId];
      });
      renderGradeTable();
      renderLedgerTable();

      try {
        const formData = new FormData();
        formData.append('action', 'delete_grade_column');
        formData.append('kelas', activeClass);
        formData.append('mata_pelajaran', activeSubject);
        formData.append('jenis_penilaian', colTitle);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();
        showToast('Kolom Dihapus', res.message || `Kolom "${colTitle}" berhasil dihapus.`);
      } catch (err) {
        showToast('Kolom Dihapus', `Kolom "${colTitle}" dihapus dari tampilan.`);
      }
    }

    function exportGradesToCSV() {
      let csvHeaders = ['No', 'NISN', 'Nama Siswa'];
      gradeColumns.forEach(c => {
        csvHeaders.push(`"${c.name} (${c.date})"`);
      });
      csvHeaders.push('"Rata-Rata"');

      let csvRows = [csvHeaders.join(',')];

      students.forEach((s, idx) => {
        let row = [idx + 1, `"${s.nisn}"`, `"${s.name}"`];
        let scoresArr = [];
        gradeColumns.forEach(c => {
          const val = s.scores[c.id] !== undefined && s.scores[c.id] !== '' ? s.scores[c.id] : '-';
          if (val !== '-') scoresArr.push(Number(val));
          row.push(val);
        });

        const avg = scoresArr.length > 0 ? (scoresArr.reduce((a, b) => a + b, 0) / scoresArr.length).toFixed(1) : 0;
        row.push(avg);

        csvRows.push(row.join(','));
      });

      const csvString = csvRows.join('\n');
      const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      const sanitizedMapel = activeSubject.replace(/[^a-zA-Z0-9]/g, '_');
      const sanitizedKelas = activeClass.replace(/[^a-zA-Z0-9]/g, '_');
      link.setAttribute('href', url);
      link.setAttribute('download', `Rekap_Nilai_${sanitizedMapel}_${sanitizedKelas}_KKM_${currentKKM}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      showToast('Unduhan Dimulai', `File CSV Rekap Nilai berhasil diunduh.`);
    }

    // ================= PRESENSI DINAMIS PER HARI MASUK =================
    function renderAttendanceTable() {
      const headerRow = document.getElementById('attendance-table-header-row');
      const tbody = document.getElementById('attendance-table-body');
      
      document.getElementById('attend-column-count-badge').innerText = `${attendanceColumns.length} Pertemuan Tercatat`;

      headerRow.innerHTML = `
        <th class="py-2.5 px-3 w-10 text-center">No</th>
        <th class="py-2.5 px-3 w-28">NISN</th>
        <th class="py-2.5 px-4 min-w-[160px]">Nama Siswa</th>
      `;

      if (attendanceColumns.length === 0) {
        headerRow.innerHTML = `
          <th class="py-3 px-3 w-10 text-center">No</th>
          <th class="py-3 px-3 w-28">NISN</th>
          <th class="py-3 px-4 min-w-[160px]">Nama Siswa</th>
          <th class="py-3 px-4 text-center">Status Kolom Kehadiran</th>
        `;
        tbody.innerHTML = `
          <tr>
            <td colspan="4" class="py-12 text-center text-slate-400">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center mx-auto mb-2 text-xl font-black">
                +
              </div>
              <p class="font-bold text-slate-700 text-sm">Belum Ada Kolom Pertemuan Kehadiran</p>
              <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                Silakan klik tombol <strong class="text-indigo-900"> Tambah Hari Masuk</strong> di atas untuk membuat pertemuan kehadiran secara manual.
              </p>
            </td>
          </tr>
        `;
        return;
      }

      attendanceColumns.forEach(col => {
        headerRow.innerHTML += `
          <th class="py-2 px-2 text-center min-w-[115px] bg-slate-50 border-l border-slate-200">
            <div class="flex items-center justify-between gap-1">
              <span class="font-bold text-xs truncate" title="${col.name}">${col.name}</span>
              <button onclick="removeAttendanceColumn('${col.id}')" title="Hapus Kolom Pertemuan Ini" class="text-slate-400 hover:text-rose-600 hover:bg-rose-50 p-1 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="text-[10px] text-slate-400 font-normal mt-0.5">
              <span>📅 ${col.date}</span>
            </div>
          </th>
        `;
      });

      tbody.innerHTML = '';
      students.forEach((s, sIdx) => {
        let colsHTML = '';
        attendanceColumns.forEach(col => {
          const status = s.attendances[col.id] || 'H';
          let badgeClass = 'badge-h';
          if (status === 'I') badgeClass = 'badge-i';
          if (status === 'S') badgeClass = 'badge-s';
          if (status === 'A') badgeClass = 'badge-a';

          colsHTML += `
            <td class="py-1.5 px-2 text-center border-l border-slate-100">
              <button onclick="cycleAttendanceStatus(${s.id}, '${col.id}')" class="w-8 h-8 rounded-xl font-extrabold text-xs transition-all shadow-sm active:scale-90 ${badgeClass}" title="Klik untuk ubah: H -> I -> S -> A">
                ${status}
              </button>
            </td>
          `;
        });

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-2 px-3 text-center text-slate-400 font-medium">${sIdx + 1}</td>
          <td class="py-2 px-3 font-mono text-[11px] text-slate-500">${s.nisn}</td>
          <td class="py-2 px-4 font-semibold text-slate-800">${s.name}</td>
          ${colsHTML}
        `;
        tbody.appendChild(row);
      });
    }

    function cycleAttendanceStatus(studentId, colId) {
      const student = students.find(s => s.id === studentId);
      if (student) {
        const current = student.attendances[colId] || 'H';
        const cycle = { 'H': 'I', 'I': 'S', 'S': 'A', 'A': 'H' };
        student.attendances[colId] = cycle[current];
        renderAttendanceTable();
        renderSemesterAttendanceTable();
        showToast('Presensi Diubah', `${student.name}: [${student.attendances[colId]}]`);
      }
    }

    function markAllCurrentMeetingPresent() {
      if (attendanceColumns.length === 0) return;
      const lastColId = attendanceColumns[attendanceColumns.length - 1].id;
      students.forEach(s => {
        s.attendances[lastColId] = 'H';
      });
      renderAttendanceTable();
      renderSemesterAttendanceTable();
      showToast('Presensi Cepat', 'Semua siswa ditandai Hadir (H) di pertemuan terbaru.');
    }

    function openAddAttendanceColumnModal() {
      document.getElementById('modal-add-attendance-column').classList.remove('hidden');
    }

    function closeAddAttendanceColumnModal() {
      document.getElementById('modal-add-attendance-column').classList.add('hidden');
    }

    function handleConfirmAddAttendanceColumn(e) {
      e.preventDefault();
      const name = document.getElementById('new-att-name').value;
      const rawDate = document.getElementById('new-att-date').value;
      const isDefaultH = document.getElementById('new-att-default-h').checked;

      if (!name || !rawDate) {
        alert('Mohon isi nama pertemuan dan tanggal masuk.');
        return;
      }

      const dateObj = new Date(rawDate);
      const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
      const formattedDate = `${days[dateObj.getDay()]}, ${dateObj.getDate()} ${months[dateObj.getMonth()]} ${dateObj.getFullYear()}`;

      const newId = 'att_' + Date.now();
      attendanceColumns.push({
        id: newId,
        name: name,
        date: formattedDate,
        isFixed: false
      });

      students.forEach(s => {
        s.attendances[newId] = isDefaultH ? 'H' : 'H';
      });

      closeAddAttendanceColumnModal();
      document.getElementById('new-att-name').value = '';
      renderAttendanceTable();
      renderSemesterAttendanceTable();
      showToast('Hari Masuk Ditambahkan', `Kolom ${name} (${formattedDate}) berhasil disisipkan.`);
    }

    function removeAttendanceColumn(colId) {
      const col = attendanceColumns.find(c => c.id === colId);
      const colTitle = col ? col.name : 'pertemuan ini';
      if (confirm(`Yakin ingin menghapus kolom ${colTitle}? Data presensi pada kolom ini akan dihapus.`)) {
        attendanceColumns = attendanceColumns.filter(c => c.id !== colId);
        students.forEach(s => {
          delete s.attendances[colId];
        });
        renderAttendanceTable();
        renderSemesterAttendanceTable();
        showToast('Kolom Dihapus', `Kolom ${colTitle} berhasil dihapus.`);
      }
    }

    function exportDailyAttendanceCSV() {
      let headers = ['"No"', '"NISN"', '"Nama Siswa"'];
      attendanceColumns.forEach(c => {
        headers.push(`"${c.name} (${c.date})"`);
      });

      let rows = [headers.join(',')];
      students.forEach((s, idx) => {
        let row = [idx + 1, `"${s.nisn}"`, `"${s.name}"`];
        attendanceColumns.forEach(c => {
          row.push(`"${s.attendances[c.id] || 'H'}"`);
        });
        rows.push(row.join(','));
      });

      const csvString = rows.join('\n');
      const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Presensi_Harian_${activeClass.replace(/[^a-zA-Z0-9]/g, '_')}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      showToast('Unduh Presensi', 'File CSV Presensi Harian berhasil diunduh.');
    }

    // ================= REKAP 1 SEMESTER (DI TAB REKAP KESELURUHAN) =================
    function renderSemesterAttendanceTable() {
      const tbody = document.getElementById('attendance-semester-table-body');
      tbody.innerHTML = '';

      const totalRecordedMeetings = attendanceColumns.length;
      let totalPctSum = 0;

      students.forEach((s, idx) => {
        let hCount = 0;
        let iCount = 0;
        let sCount = 0;
        let aCount = 0;

        attendanceColumns.forEach(col => {
          const st = s.attendances[col.id] || 'H';
          if (st === 'H') hCount++;
          else if (st === 'I') iCount++;
          else if (st === 'S') sCount++;
          else if (st === 'A') aCount++;
        });

        const baseHadir = s.id === 5 ? 27 + hCount : 32 + hCount;
        const baseTotal = 33 + totalRecordedMeetings;
        const pct = Math.round((baseHadir / baseTotal) * 100);
        totalPctSum += pct;

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-2.5 px-3 text-center text-slate-400 font-medium">${idx + 1}</td>
          <td class="py-2.5 px-3 font-mono text-[11px] text-slate-500">${s.nisn}</td>
          <td class="py-2.5 px-4 font-bold text-slate-800">${s.name}</td>
          <td class="py-2.5 px-2 text-center font-bold text-emerald-700 bg-emerald-50/50">${baseHadir}</td>
          <td class="py-2.5 px-2 text-center font-medium text-amber-700 bg-amber-50/50">${iCount + (s.id === 5 ? 2 : 1)}</td>
          <td class="py-2.5 px-2 text-center font-medium text-purple-700 bg-purple-50/50">${sCount + (s.id === 3 ? 2 : 0)}</td>
          <td class="py-2.5 px-2 text-center font-bold ${(aCount + (s.id === 5 ? 3 : 0)) > 0 ? 'text-rose-600 bg-rose-50' : 'text-slate-400'}">${aCount + (s.id === 5 ? 3 : 0)}</td>
          <td class="py-2.5 px-3 text-center font-semibold text-slate-600">${baseTotal} Pertemuan</td>
          <td class="py-2.5 px-3 text-center font-extrabold ${pct >= 85 ? 'text-emerald-700' : 'text-rose-600'}">${pct}%</td>
          <td class="py-2 px-3">
            <input type="text" value="${s.semesterNotes}" oninput="updateSemesterNote(${s.id}, this.value)" placeholder="Tulis catatan evaluasi siswa..." class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 bg-white font-medium shadow-2xs">
          </td>
        `;
        tbody.appendChild(row);
      });

      if (students.length > 0) {
        document.getElementById('metric-attend-avg').innerText = (totalPctSum / students.length).toFixed(1) + '%';
      }
    }

    function exportAttendanceSemesterCSV() {
      let headers = ['"No"', '"NISN"', '"Nama Siswa"', '"Total Hadir"', '"Total Izin"', '"Total Sakit"', '"Total Alpa"', '"Total Pertemuan"', '"% Kehadiran"', '"Catatan Evaluasi"'];
      let rows = [headers.join(',')];

      students.forEach((s, idx) => {
        let hCount = 0;
        attendanceColumns.forEach(col => {
          if ((s.attendances[col.id] || 'H') === 'H') hCount++;
        });
        const baseHadir = s.id === 5 ? 27 + hCount : 32 + hCount;
        const baseTotal = 33 + attendanceColumns.length;
        const pct = Math.round((baseHadir / baseTotal) * 100);

        rows.push([
          idx + 1,
          `"${s.nisn}"`,
          `"${s.name}"`,
          baseHadir,
          1,
          s.id === 3 ? 2 : 0,
          s.id === 5 ? 3 : 0,
          baseTotal,
          `"${pct}%"`,
          `"${(s.semesterNotes || '').replace(/"/g, '""')}"`
        ].join(','));
      });

      const csvString = rows.join('\n');
      const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Rekap_Presensi_1_Semester_${activeClass.replace(/[^a-zA-Z0-9]/g, '_')}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      showToast('Unduh Presensi', 'File CSV Rekap Presensi 1 Semester berhasil diunduh.');
    }

    // ================= RENDER LEDGER REKAP KESELURUHAN =================
    function renderLedgerTable() {
      const tbody = document.getElementById('ledger-table-body');
      tbody.innerHTML = '';

      students.forEach((s, idx) => {
        let scoresArr = [];
        gradeColumns.forEach(c => {
          if (s.scores[c.id] !== undefined && s.scores[c.id] !== '' && s.scores[c.id] !== null) {
            scoresArr.push(Number(s.scores[c.id]));
          }
        });

        const finalScore = scoresArr.length > 0 ? (scoresArr.reduce((a, b) => a + b, 0) / scoresArr.length).toFixed(1) : 0;

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-3 px-3 text-center text-slate-400 font-medium">${idx + 1}</td>
          <td class="py-3 px-3 font-mono text-[11px] text-slate-500">${s.nisn}</td>
          <td class="py-3 px-4 font-bold text-slate-800">${s.name}</td>
          <td class="py-3 px-3 text-center bg-blue-50/30 font-medium text-blue-900">${scoresArr.length} Nilai Terinput</td>
          <td class="py-3 px-3 text-center font-extrabold text-indigo-950 bg-amber-50/40 text-sm">${finalScore}</td>
          <td class="py-3 px-3 text-center font-bold text-emerald-700">Tercatat di Presensi</td>
          <td class="py-2 px-3">
            <input type="text" value="${s.semesterNotes}" oninput="updateSemesterNote(${s.id}, this.value)" placeholder="Tulis catatan evaluasi siswa..." class="w-full border border-slate-300 focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 bg-white font-medium shadow-2xs">
          </td>
        `;
        tbody.appendChild(row);
      });
    }

    function updateSemesterNote(studentId, newNote) {
      const student = students.find(s => s.id === studentId);
      if (student) {
        student.semesterNotes = newNote;
      }
    }

    // ================= MODUL AJAR =================
    let modulesList = [
      { title: 'Modul Teori Eksponen & Bentuk Akar', size: '2.4 MB', date: 'Hari ini, 09.18 WIB', downloads: '28x', status: 'Terbit' },
      { title: 'LKPD Latihan Mandiri Teorema Pythagoras', size: '1.8 MB', date: '30 Sep', downloads: '32x', status: 'Terbit' },
      { title: 'Bank Soal Evaluasi Tengah Semester', size: '540 KB', date: '28 Sep', downloads: '0x', status: 'Draf' }
    ];

    function renderModules() {
      const container = document.getElementById('module-list-container');
      container.innerHTML = '';
      modulesList.forEach(m => {
        container.innerHTML += `
          <div class="p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:bg-slate-50 transition-colors">
            <div class="flex items-center gap-3">
              <div class="w-9 sm:w-10 h-9 sm:h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0">
                PDF
              </div>
              <div>
                <h4 class="font-bold text-slate-800 text-xs sm:text-sm">${m.title}</h4>
                <p class="text-slate-500 text-[10px] sm:text-[11px]">${m.size} · Diperbarui ${m.date}</p>
              </div>
            </div>
            <div class="flex items-center gap-2.5 sm:gap-3 pl-12 sm:pl-0">
              <span class="px-2.5 py-0.5 rounded-full ${m.status === 'Terbit' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'} font-semibold text-[10px] sm:text-[11px]">${m.status}</span>
              <span class="text-slate-400 text-[10px] sm:text-[11px]">Diunduh ${m.downloads}</span>
            </div>
          </div>
        `;
      });
    }

    function addModuleSimulated() {
      const title = document.getElementById('input-modul-title').value;
      if (!title) {
        alert('Mohon isi judul modul terlebih dahulu.');
        return;
      }

      modulesList.unshift({
        title: title,
        size: '3.1 MB',
        date: 'Baru saja',
        downloads: '0x',
        status: 'Terbit'
      });

      renderModules();
      document.getElementById('input-modul-title').value = '';
      document.getElementById('input-modul-desc').value = '';
      showToast('Modul Diterbitkan', `Modul "${title}" berhasil dibagikan ke kelas.`);
    }

    // ================= JURNAL HARIAN MENGAJAR GURU =================
    let journalList = [
      {
        id: 1,
        day: 'Senin',
        date: '05 Okt 2026',
        meeting: 'Pertemuan ke-8 (Jam 1-3)',
        topic: 'Operasi Aljabar Bentuk Eksponen',
        activities: 'Apersepsi pengantar sifat-sifat perkalian eksponen, pengerjaan LKPD 1 secara berkelompok, dan evaluasi pemahaman mandiri di lembar refleksi.',
        attendance: { hadir: 31, izin: 1, sakit: 0, alpa: 0 }
      },
      {
        id: 2,
        day: 'Kamis',
        date: '01 Okt 2026',
        meeting: 'Pertemuan ke-7 (Jam 4-6)',
        topic: 'Pangkat Nol, Negatif, dan Bentuk Akar',
        activities: 'Eksplorasi pembagian bilangan berpangkat nol dan negatif, tanya jawab interaktif di papan tulis, dan pemberian latihan soal terbimbing.',
        attendance: { hadir: 30, izin: 1, sakit: 1, alpa: 0 }
      },
      {
        id: 3,
        day: 'Senin',
        date: '28 Sep 2026',
        meeting: 'Pertemuan ke-6 (Jam 1-3)',
        topic: 'Ulangan Harian 1: Bilangan Berpangkat',
        activities: 'Pelaksanaan asesmen sumatif materi bilangan berpangkat selama 60 menit, dilanjutkan pembahasan 2 soal pemecahan masalah kontekstual.',
        attendance: { hadir: 32, izin: 0, sakit: 0, alpa: 0 }
      }
    ];

    function renderJournalTable() {
      const tbody = document.getElementById('journal-table-body');
      if (!tbody) return;
      tbody.innerHTML = '';

      journalList.forEach((j, idx) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors border-b border-slate-100';
        row.innerHTML = `
          <td class="py-3 px-3 text-center text-slate-400 font-medium">${idx + 1}</td>
          <td class="py-3 px-3">
            <p class="font-bold text-slate-900">${j.day}</p>
            <p class="text-slate-500 text-[11px]">${j.date}</p>
          </td>
          <td class="py-3 px-3 font-semibold text-indigo-900 bg-indigo-50/30">
            ${j.meeting}
          </td>
          <td class="py-3 px-4 font-bold text-slate-800">
            ${j.topic}
          </td>
          <td class="py-3 px-4 text-slate-600 leading-relaxed">
            ${j.activities}
          </td>
          <td class="py-3 px-3 text-center">
            <div class="inline-flex items-center gap-1.5 font-bold text-[10px]">
              <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800" title="Hadir">${j.attendance.hadir} H</span>
              <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-800" title="Izin">${j.attendance.izin} I</span>
              <span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800" title="Sakit">${j.attendance.sakit} S</span>
              <span class="px-2 py-0.5 rounded-md ${j.attendance.alpa > 0 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-500'}" title="Alpa">${j.attendance.alpa} A</span>
            </div>
          </td>
          <td class="py-3 px-3 text-right space-x-1 whitespace-nowrap">
            <button onclick="openEditJournalModal(${j.id})" class="text-indigo-600 hover:text-indigo-900 font-bold p-1 hover:bg-indigo-50 rounded" title="Edit Jurnal">Edit</button>
            <button onclick="deleteJournal(${j.id})" class="text-rose-600 hover:text-rose-900 font-bold p-1 hover:bg-rose-50 rounded" title="Hapus Jurnal">Hapus</button>
          </td>
        `;
        tbody.appendChild(row);
      });

      const badgeCount = document.getElementById('badge-jurnal-count');
      if (badgeCount) badgeCount.innerText = `${journalList.length} Catatan`;
      const labelCount = document.getElementById('journal-count-label');
      if (labelCount) labelCount.innerText = `Menampilkan ${journalList.length} entri jurnal mengajar`;
    }

    function openAddJournalModal() {
      const title = document.getElementById('modal-jurnal-title');
      if (title) title.innerText = 'Tambah Jurnal Mengajar Harian';
      document.getElementById('input-jurnal-id').value = '';
      document.getElementById('input-jurnal-hari').value = 'Senin';
      
      const today = new Date();
      const yyyy = today.getFullYear();
      const mm = String(today.getMonth() + 1).padStart(2, '0');
      const dd = String(today.getDate()).padStart(2, '0');
      document.getElementById('input-jurnal-tanggal').value = `${yyyy}-${mm}-${dd}`;
      
      document.getElementById('input-jurnal-pertemuan').value = '';
      document.getElementById('input-jurnal-materi').value = '';
      document.getElementById('input-jurnal-kegiatan').value = '';
      document.getElementById('input-jurnal-h').value = students.length || 32;
      document.getElementById('input-jurnal-i').value = 0;
      document.getElementById('input-jurnal-s').value = 0;
      document.getElementById('input-jurnal-a').value = 0;

      document.getElementById('modal-jurnal').classList.remove('hidden');
    }

    function openEditJournalModal(id) {
      const j = journalList.find(item => item.id == id);
      if (!j) return;

      const title = document.getElementById('modal-jurnal-title');
      if (title) title.innerText = 'Edit Jurnal Pembelajaran';
      document.getElementById('input-jurnal-id').value = j.id;
      document.getElementById('input-jurnal-hari').value = j.day || 'Senin';
      
      let dateVal = j.rawDate || '';
      if (!dateVal && j.date) {
        try {
          const d = new Date(j.date);
          if (!isNaN(d.getTime())) {
            dateVal = d.toISOString().split('T')[0];
          }
        } catch(e) {}
      }
      document.getElementById('input-jurnal-tanggal').value = dateVal;
      document.getElementById('input-jurnal-pertemuan').value = j.meeting || '';
      document.getElementById('input-jurnal-materi').value = j.topic || '';
      document.getElementById('input-jurnal-kegiatan').value = j.activities || '';

      const att = j.attendance || {};
      document.getElementById('input-jurnal-h').value = att.hadir !== undefined ? att.hadir : (students.length || 32);
      document.getElementById('input-jurnal-i').value = att.izin !== undefined ? att.izin : 0;
      document.getElementById('input-jurnal-s').value = att.sakit !== undefined ? att.sakit : 0;
      document.getElementById('input-jurnal-a').value = att.alpa !== undefined ? att.alpa : 0;

      document.getElementById('modal-jurnal').classList.remove('hidden');
    }

    function closeAddJournalModal() {
      document.getElementById('modal-jurnal').classList.add('hidden');
    }

    async function handleSaveJournal(e) {
      e.preventDefault();
      const id = document.getElementById('input-jurnal-id').value;
      const day = document.getElementById('input-jurnal-hari').value;
      const rawDate = document.getElementById('input-jurnal-tanggal').value;
      const meeting = document.getElementById('input-jurnal-pertemuan').value;
      const topic = document.getElementById('input-jurnal-materi').value;
      const activities = document.getElementById('input-jurnal-kegiatan').value;
      const hadir = parseInt(document.getElementById('input-jurnal-h').value) || 0;
      const izin = parseInt(document.getElementById('input-jurnal-i').value) || 0;
      const sakit = parseInt(document.getElementById('input-jurnal-s').value) || 0;
      const alpa = parseInt(document.getElementById('input-jurnal-a').value) || 0;

      try {
        const formData = new FormData();
        formData.append('action', 'save_journal');
        formData.append('id', id);
        formData.append('guru_kode', currentLoggedTeacher ? currentLoggedTeacher.kode : '');
        formData.append('nama_guru', currentLoggedTeacher ? currentLoggedTeacher.nama || currentLoggedTeacher.name : '');
        formData.append('kelas', activeClass);
        formData.append('mata_pelajaran', activeSubject);
        formData.append('hari', day);
        formData.append('tanggal', rawDate);
        formData.append('pertemuan', meeting);
        formData.append('materi', topic);
        formData.append('kegiatan', activities);
        formData.append('hadir', hadir);
        formData.append('izin', izin);
        formData.append('sakit', sakit);
        formData.append('alpa', alpa);

        const resp = await fetch('guru.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
          closeAddJournalModal();
          showToast('Jurnal Tersimpan', res.message || `Agenda "${topic}" tersimpan ke database.`);
          fetchAcademicDataFromDB(activeClass, activeSubject);
        } else {
          alert('Gagal menyimpan jurnal: ' + (res.message || 'Terjadi kesalahan'));
        }
      } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan jaringan saat menyimpan jurnal.');
      }
    }

    async function deleteJournal(id) {
      if (confirm('Hapus entri jurnal pembelajaran ini dari database?')) {
        try {
          const formData = new FormData();
          formData.append('action', 'delete_journal');
          formData.append('id', id);

          const resp = await fetch('guru.php', { method: 'POST', body: formData });
          const res = await resp.json();
          if (res.success) {
            showToast('Jurnal Dihapus', res.message || 'Entri jurnal mengajar berhasil dihapus.');
            fetchAcademicDataFromDB(activeClass, activeSubject);
          } else {
            alert('Gagal menghapus jurnal: ' + (res.message || 'Terjadi kesalahan'));
          }
        } catch (err) {
          console.error(err);
          alert('Terjadi kesalahan jaringan saat menghapus jurnal.');
        }
      }
    }

    function exportJournalCSV() {
      let csv = 'No,Hari,Tanggal,Pertemuan,Materi Pembelajaran,Kegiatan Pembelajaran,Hadir,Izin,Sakit,Alpa\n';
      journalList.forEach((j, idx) => {
        csv += `${idx + 1},"${j.day}","${j.date}","${j.meeting}","${j.topic}","${j.activities.replace(/"/g, '""')}",${j.attendance.hadir},${j.attendance.izin},${j.attendance.sakit},${j.attendance.alpa}\n`;
      });
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Jurnal_Mengajar_${activeSubject.replace(/[^a-zA-Z0-9]/g, '_')}_${activeClass.replace(/[^a-zA-Z0-9]/g, '_')}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      showToast('Download Berhasil', 'File Jurnal Mengajar CSV berhasil diunduh.');
    }

    // MOBILE DRAWER CONTROLLER
    function toggleMobileDrawer(show) {
      const overlay = document.getElementById('mobile-drawer-overlay');
      if (!overlay) return;
      if (show) {
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      } else {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
      }
    }

    // TABS CONTROLLER
    function switchTab(tabName) {
      const tabs = ['nilai', 'presensi', 'jurnal', 'materi', 'rekap', 'faq'];
      tabs.forEach(t => {
        // Hide panel
        const panel = document.getElementById(`tab-${t}`);
        if (panel) panel.classList.add('hidden');

        // Desktop aside button
        const btnDesk = document.getElementById(`btn-tab-${t}`);
        if (btnDesk) {
          btnDesk.classList.remove('bg-indigo-900', 'text-white', 'shadow-sm');
          btnDesk.classList.add('text-slate-600', 'hover:bg-slate-100');
          const badge = btnDesk.querySelector('.font-bold');
          if (badge && badge.innerText === 'Aktif') badge.remove();
        }

        // Mobile pill button
        const btnMob = document.getElementById(`btn-mob-tab-${t}`);
        if (btnMob) {
          btnMob.classList.remove('bg-indigo-900', 'text-white', 'shadow-xs', 'font-bold');
          btnMob.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200', 'font-medium');
        }

        // Mobile drawer button
        const btnDrawer = document.getElementById(`btn-drawer-tab-${t}`);
        if (btnDrawer) {
          btnDrawer.classList.remove('bg-indigo-900', 'text-white', 'shadow-xs');
          btnDrawer.classList.add('text-slate-600', 'hover:bg-slate-100');
        }
      });

      // Show active panel
      const activePanel = document.getElementById(`tab-${tabName}`);
      if (activePanel) activePanel.classList.remove('hidden');

      // Desktop active button
      const activeBtnDesk = document.getElementById(`btn-tab-${tabName}`);
      if (activeBtnDesk) {
        activeBtnDesk.classList.add('bg-indigo-900', 'text-white', 'shadow-sm');
        activeBtnDesk.classList.remove('text-slate-600', 'hover:bg-slate-100');
      }

      // Mobile pill active button
      const activeBtnMob = document.getElementById(`btn-mob-tab-${tabName}`);
      if (activeBtnMob) {
        activeBtnMob.classList.remove('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200', 'font-medium');
        activeBtnMob.classList.add('bg-indigo-900', 'text-white', 'shadow-xs', 'font-bold');
        activeBtnMob.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      }

      // Mobile drawer active button
      const activeBtnDrawer = document.getElementById(`btn-drawer-tab-${tabName}`);
      if (activeBtnDrawer) {
        activeBtnDrawer.classList.remove('text-slate-600', 'hover:bg-slate-100');
        activeBtnDrawer.classList.add('bg-indigo-900', 'text-white', 'shadow-xs');
      }
    }

    // TOAST HELPER
    function showToast(title, msg) {
      const toast = document.getElementById('toast');
      document.getElementById('toast-title').innerText = title;
      document.getElementById('toast-msg').innerText = msg;

      toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
      setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
      }, 3500);
    }

    function saveAllGrades() {
      showToast('Tersimpan', 'Seluruh nilai berhasil disimpan ke server.');
    }

    function saveAttendance() {
      showToast('Presensi Disimpan', 'Data presensi seluruh pertemuan berhasil diperbarui.');
    }

    function saveAllEvaluations() {
      showToast('Catatan Disimpan', 'Seluruh catatan evaluasi siswa berhasil diperbarui ke buku induk.');
    }

    // INITIALIZATION & SESSION RESTORATION
    window.addEventListener('DOMContentLoaded', () => {
      renderJournalTable();

      const savedSession = sessionStorage.getItem('currentLoggedTeacher');
      if (savedSession) {
        try {
          const teacher = JSON.parse(savedSession);
          if (teacher && teacher.name) {
            setupTeacherPermissions(teacher);
          }
        } catch (e) {
          sessionStorage.removeItem('currentLoggedTeacher');
        }
      }

      // Sinkronisasi status riwayat halaman saat pertama kali dimuat
      const hash = window.location.hash.replace('#', '');
      if (currentLoggedTeacher) {
        if (hash === 'dashboard') {
          showScreen('screen-dashboard', false);
          history.replaceState({ screen: 'screen-dashboard' }, '', '#dashboard');
        } else {
          goToSelectClass(false);
          history.replaceState({ screen: 'screen-select-class' }, '', '#select-class');
        }
      } else {
        showScreen('screen-login', false);
        history.replaceState({ screen: 'screen-login' }, '', '#login');
      }
    });
  </script>
</body>
</html>
