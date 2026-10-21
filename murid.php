<?php
require_once 'koneksi.php';

// Helper function untuk mengambil data siswa dan nilainya berdasarkan NISN
function getStudentFullData($koneksi, $nisn) {
    $nisn = trim($nisn);
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM data_siswa WHERE nisn = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $nisn);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$student) {
        return null;
    }

    // Ambil semua nilai siswa dari tabel nilai_siswa
    $stmtN = mysqli_prepare($koneksi, "SELECT * FROM nilai_siswa WHERE nisn = ? ORDER BY mata_pelajaran, id ASC");
    mysqli_stmt_bind_param($stmtN, 's', $nisn);
    mysqli_stmt_execute($stmtN);
    $resN = mysqli_stmt_get_result($stmtN);

    $subjects = [];
    $totalScoreSum = 0;
    $totalGradeCount = 0;
    $hasRemedial = false;

    while ($rN = mysqli_fetch_assoc($resN)) {
        $subj = $rN['mata_pelajaran'];
        if (!isset($subjects[$subj])) {
            $subjects[$subj] = [
                'teacher' => $rN['guru_pengampu'] ?: 'Guru Pengampu',
                'kkm'     => (int)$rN['kkm'],
                'grades'  => []
            ];
        }
        $score = (float)$rN['nilai'];
        $kkm = (int)$rN['kkm'];
        $isPassed = $score >= $kkm;
        if (!$isPassed) $hasRemedial = true;

        $subjects[$subj]['grades'][] = [
            'no'     => count($subjects[$subj]['grades']) + 1,
            'title'  => $rN['jenis_penilaian'],
            'date'   => date('d M Y', strtotime($rN['updated_at'] ?: $rN['created_at'])),
            'kkm'    => $kkm,
            'score'  => $score,
            'passed' => $isPassed
        ];

        $totalScoreSum += $score;
        $totalGradeCount++;
    }
    mysqli_stmt_close($stmtN);

    // Hitung rata-rata per mapel
    foreach ($subjects as $sName => &$sData) {
        if (!empty($sData['grades'])) {
            $subSum = array_sum(array_column($sData['grades'], 'score'));
            $sData['average'] = number_format($subSum / count($sData['grades']), 1);
            $sData['passed'] = (float)$sData['average'] >= $sData['kkm'];
        } else {
            $sData['average'] = '-';
            $sData['passed'] = true;
        }
    }
    unset($sData);

    // Inisial avatar
    $names = preg_split('/\s+/', trim($student['nama_siswa']));
    $avatar = '';
    if (count($names) >= 2) {
        $avatar = strtoupper(substr($names[0], 0, 1) . substr($names[1], 0, 1));
    } else {
        $avatar = strtoupper(substr($names[0], 0, 2));
    }

    $overallAvg = $totalGradeCount > 0 ? number_format($totalScoreSum / $totalGradeCount, 1) : '85.0';

    return [
        'id'            => (int)$student['id'],
        'nisn'          => $student['nisn'],
        'name'          => $student['nama_siswa'],
        'class'         => $student['kelas'],
        'gender'        => $student['gender'],
        'dob'           => $student['tanggal_lahir'],
        'avatar'        => $avatar,
        'teacher'       => 'Wali Kelas ' . $student['kelas'],
        'notes'         => $student['catatan_wali'] ?: 'Pertahankan fokus belajar dan tingkatkan keaktifan di kelas.',
        'overallPassed' => !$hasRemedial,
        'average'       => $overallAvg,
        'attendance'    => [
            'pct'       => '94.4%',
            'hadir'     => 34,
            'izin'      => 1,
            'sakit'     => 1,
            'alpa'      => 0,
            'total'     => 36,
            'predicate' => 'A (Sangat Disiplin)'
        ],
        'subjects'      => $subjects,
        'meetingsHistory' => [
            ['name' => 'Pertemuan 1 (Matematika)', 'date' => 'Senin, 05 Okt 2026', 'status' => 'H', 'note' => 'Hadir'],
            ['name' => 'Pertemuan 2 (IPA)', 'date' => 'Selasa, 06 Okt 2026', 'status' => 'H', 'note' => 'Hadir'],
            ['name' => 'Pertemuan 3 (Bahasa Indonesia)', 'date' => 'Rabu, 07 Okt 2026', 'status' => 'I', 'note' => 'Izin (Ada Keperluan Keluarga)'],
            ['name' => 'Pertemuan 4 (Bahasa Inggris)', 'date' => 'Kamis, 08 Okt 2026', 'status' => 'H', 'note' => 'Hadir'],
            ['name' => 'Pertemuan 5 (Informatika)', 'date' => 'Jumat, 09 Okt 2026', 'status' => 'H', 'note' => 'Hadir']
        ]
    ];
}

// Endpoint AJAX: Login Siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'student_login') {
    header('Content-Type: application/json');
    $nisn = trim($_POST['nisn'] ?? '');
    $dob  = trim($_POST['dob'] ?? '');

    $stmt = mysqli_prepare($koneksi, "SELECT * FROM data_siswa WHERE nisn = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $nisn);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $siswa = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$siswa) {
        echo json_encode(['success' => false, 'message' => 'NISN tidak terdaftar dalam database sekolah!']);
        exit;
    }

    $cleanInputDob = preg_replace('/[^0-9]/', '', $dob);
    $cleanDbDob    = preg_replace('/[^0-9]/', '', $siswa['tanggal_lahir']);

    if (!empty($cleanDbDob) && !empty($cleanInputDob) && $cleanInputDob !== $cleanDbDob) {
        echo json_encode(['success' => false, 'message' => 'Tanggal lahir tidak sesuai! Masukkan format DDMMYYYY (contoh: 14092012).']);
        exit;
    }

    $fullData = getStudentFullData($koneksi, $nisn);
    echo json_encode([
        'success' => true,
        'student' => $fullData
    ]);
    exit;
}

// Endpoint AJAX: Ambil Data Terbaru Siswa (Real-time Live Sync)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_student_data') {
    header('Content-Type: application/json');
    $nisn = trim($_POST['nisn'] ?? '');
    $fullData = getStudentFullData($koneksi, $nisn);
    if ($fullData) {
        echo json_encode(['success' => true, 'student' => $fullData]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data siswa tidak ditemukan']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Siswa SMPN 7 Purwakarta - Ruang Belajar & Nilai Siswa</title>
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <script>
    // Langsung periksa status login agar tidak berkedip ke halaman login saat dimuat ulang
    try {
      if (localStorage.getItem('smp_student_logged_in') === 'true' && window.location.hash !== '#login') {
        document.documentElement.classList.add('is-student-logged-in');
      }
    } catch (e) {}
  </script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    /* Sembunyikan login & tampilkan dashboard seketika jika sebelumnya sudah login */
    html.is-student-logged-in #screen-login-student {
      display: none !important;
    }
    html.is-student-logged-in #screen-dashboard-student {
      display: flex !important;
    }
    /* Sidebar navigasi kiri selalu tampil di desktop dan toggle di mobile */
    #student-sidebar {
      display: flex !important;
    }
    @media (max-width: 767px) {
      #student-sidebar {
        display: none !important;
      }
      #student-sidebar.mobile-open {
        display: flex !important;
      }
    }

    /* ================================================================
       UI/UX ENHANCEMENT — SMPN 7 PURWAKARTA
       Tidak mengubah struktur data/logic utama.
       ================================================================ */
    :root{
      --school-navy:#111b4d;
      --school-indigo:#27348b;
      --school-gold:#f5b83d;
      --soft-bg:#f5f7fb;
    }
    html { scroll-behavior:smooth; }
    body{
      background:
        radial-gradient(circle at 10% 0%, rgba(39,52,139,.05), transparent 28%),
        radial-gradient(circle at 90% 10%, rgba(245,184,61,.06), transparent 25%),
        var(--soft-bg);
    }

    /* Login */
    #screen-login-student{
      background:
        radial-gradient(circle at 15% 15%, rgba(83,102,255,.20), transparent 30%),
        radial-gradient(circle at 85% 85%, rgba(245,184,61,.16), transparent 28%),
        linear-gradient(135deg,#0a1235 0%,#111b4d 52%,#18255e 100%) !important;
    }
    #screen-login-student:before{
      content:"";
      position:absolute; inset:0;
      background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),
                       linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);
      background-size:38px 38px;
      mask-image:linear-gradient(to bottom,black,transparent 85%);
      pointer-events:none;
    }
    #screen-login-student > div:last-child{
      box-shadow:0 30px 80px rgba(0,0,0,.28),0 10px 30px rgba(17,27,77,.15) !important;
      border:1px solid rgba(255,255,255,.7) !important;
    }
    #screen-login-student form input{
      transition:.2s ease;
      background:#fbfcff;
    }
    #screen-login-student form input:hover{border-color:#aab4d8;}
    #screen-login-student form input:focus{
      transform:translateY(-1px);
      box-shadow:0 0 0 4px rgba(39,52,139,.10);
    }
    #screen-login-student form button{
      background:linear-gradient(135deg,#111b4d,#27348b) !important;
      box-shadow:0 10px 22px rgba(17,27,77,.20) !important;
    }
    #screen-login-student form button:hover{
      transform:translateY(-1px);
      box-shadow:0 14px 26px rgba(17,27,77,.25) !important;
    }

    /* Header */
    #screen-dashboard-student > header{
      background:rgba(17,27,77,.96) !important;
      backdrop-filter:blur(14px);
      box-shadow:0 8px 24px rgba(17,27,77,.10) !important;
    }
    #screen-dashboard-student > header .h-16{height:70px;}
    #header-student-avatar{
      box-shadow:0 0 0 4px rgba(245,184,61,.10),0 5px 14px rgba(0,0,0,.18) !important;
    }

    /* Main layout */
    #screen-dashboard-student .max-w-7xl{
      max-width:1320px;
    }
    #student-sidebar{
      border-radius:24px !important;
      box-shadow:0 12px 35px rgba(17,27,77,.06) !important;
      border-color:#e4e8f2 !important;
    }
    #student-sidebar nav button{
      position:relative;
      min-height:48px;
      transition:transform .18s ease, background .18s ease, box-shadow .18s ease;
    }
    #student-sidebar nav button:hover{
      transform:translateX(2px);
    }
    #student-sidebar nav button.bg-indigo-950{
      box-shadow:0 8px 18px rgba(17,27,77,.16) !important;
      background:linear-gradient(135deg,#111b4d,#27348b) !important;
    }
    #student-sidebar .pt-3 > div{
      background:linear-gradient(135deg,#f0f3ff,#fafbff) !important;
    }

    /* Content cards */
    #screen-dashboard-student main > section > div,
    #screen-dashboard-student main > section .bg-white{
      transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    #screen-dashboard-student main > section .bg-white:hover{
      border-color:#dbe1f1;
      box-shadow:0 12px 30px rgba(17,27,77,.06);
    }

    /* Hero */
    #student-view-beranda > div:first-child{
      background:
        radial-gradient(circle at 88% 20%, rgba(245,184,61,.18), transparent 25%),
        radial-gradient(circle at 75% 100%, rgba(84,102,255,.22), transparent 30%),
        linear-gradient(135deg,#111b4d 0%,#27348b 62%,#1a245e 100%) !important;
      box-shadow:0 18px 38px rgba(17,27,77,.16) !important;
    }
    #student-view-beranda > div:first-child:after{
      content:"";
      position:absolute;
      width:180px;height:180px;
      right:-65px;bottom:-80px;
      border:1px solid rgba(255,255,255,.10);
      border-radius:50%;
      box-shadow:0 0 0 30px rgba(255,255,255,.025),0 0 0 60px rgba(255,255,255,.018);
    }
    #student-view-beranda .grid > div{
      min-height:126px;
    }

    /* Better typography hierarchy */
    #screen-dashboard-student h2{letter-spacing:-.02em;}
    #screen-dashboard-student h3{letter-spacing:-.01em;}
    #screen-dashboard-student table tbody tr{
      transition:background .15s ease;
    }
    #screen-dashboard-student table tbody tr:hover{
      background:#f8f9ff;
    }

    /* Buttons */
    #screen-dashboard-student button{
      -webkit-tap-highlight-color:transparent;
    }
    #screen-dashboard-student main button:not([onclick*="switchStudentTab"]):not([onclick*="toggleStudentMobileSidebar"]):not([onclick*="goToStudentLogin"]){
      transition:transform .18s ease,box-shadow .18s ease;
    }
    #screen-dashboard-student main button:hover{
      transform:translateY(-1px);
    }

    /* Footer */
    #screen-dashboard-student footer{
      background:rgba(255,255,255,.82) !important;
      backdrop-filter:blur(10px);
    }

    /* Mobile */
    @media (max-width:767px){
      #screen-dashboard-student > header .h-16{height:62px;}
      #screen-dashboard-student > header .school-brand-name{
        max-width:145px !important;
        overflow:visible !important;
        text-overflow:clip !important;
        white-space:nowrap !important;
        font-size:12px !important;
      }
      #screen-dashboard-student > header .school-brand-logo{
        width:32px !important;
        height:32px !important;
      }
      #student-sidebar{
        position:fixed !important;
        left:12px; right:12px; top:74px;
        width:auto !important;
        max-height:calc(100vh - 88px);
        overflow:auto;
        box-shadow:0 24px 60px rgba(0,0,0,.20) !important;
      }
      #screen-dashboard-student .max-w-7xl{
        padding-left:14px !important;
        padding-right:14px !important;
      }
      #student-view-beranda > div:first-child{
        padding:22px !important;
      }
      #student-view-beranda .grid > div{
        min-height:110px;
      }
    }

    /* Subtle entrance animation */
    @keyframes portalFadeUp{
      from{opacity:0;transform:translateY(8px)}
      to{opacity:1;transform:translateY(0)}
    }
    #student-view-beranda:not(.hidden),
    #student-view-materi:not(.hidden),
    #student-view-kehadiran:not(.hidden),
    #student-view-nilai:not(.hidden),
    #student-view-faq:not(.hidden){
      animation:portalFadeUp .28s ease both;
    }

    /* Accessibility */
    :focus-visible{
      outline:3px solid rgba(245,184,61,.65);
      outline-offset:2px;
    }

  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

  <!-- ============================================================== -->
  <!-- SCREEN 1: LOGIN SISWA (NISN & TANGGAL LAHIR) -->
  <!-- ============================================================== -->
  <section id="screen-login-student" class="min-h-screen flex items-center justify-center p-4 bg-slate-900 relative overflow-hidden">
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-400/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-800/40 relative z-10 p-7 sm:p-8">
      
      <!-- Logo & Header Siswa -->
      <div class="text-center mb-6">
        <div class="w-24 h-24 rounded-2xl text-indigo-950 font-black text-xl flex items-center justify-center mx-auto mb-3">
          <img src="logoSMP.png" alt="Logo SMPN 7 Purwakarta" class="w-22 h-22 object-contain">
        </div>
        <h1 class="text-xl font-extrabold text-indigo-950 tracking-tight">PORTAL SISWA</h1>
        <p class="text-xs text-slate-500 font-medium">SMPN 7 Purwakarta · T.A 2026/2027</p>
      </div>

      <!-- Privacy Notice -->
      <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-3 mb-5 text-xs text-indigo-900 flex items-start gap-2">
        <svg class="w-4 h-4 text-indigo-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <span class="leading-relaxed">Data pada portal ini bersifat <strong>pribadi dan rahasia</strong>. Anda hanya dapat melihat materi pembelajaran, rekap kehadiran, dan nilai tugas milik Anda sendiri.</span>
      </div>

      <!-- Form Login Siswa -->
      <form onsubmit="handleStudentLogin(event)" class="space-y-4 text-xs">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Nomor Induk Siswa Nasional (NISN)</label>
          <input type="text" id="input-nisn" required value="0098427135" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-mono text-sm focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Tanggal Lahir (DD/MM/YYYY)</label>
          <input type="password" id="input-dob" required value="14092012" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 text-sm focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <button type="submit" class="w-full py-3 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-md transition-all active:scale-[0.99] flex items-center justify-center gap-2 text-sm mt-2">
          <span>Masuk ke Portal Siswa</span>
          <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </button>
      </form>

    </div>
  </section>


  <!-- ============================================================== -->
  <!-- SCREEN 2: DASHBOARD UTAMA SISWA (POV PRIBADI & AMAN) -->
  <!-- ============================================================== -->
  <div id="screen-dashboard-student" class="min-h-screen flex flex-col hidden">
    
    <!-- Top Header Siswa -->
    <header class="bg-indigo-950 text-white border-b border-indigo-900 sticky top-0 z-40 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between gap-2 h-16">
          
          <div class="flex items-center gap-2 sm:gap-3 min-w-0">
            <!-- Mobile Toggle Menu Navigasi -->
            <button onclick="toggleStudentMobileSidebar()" id="student-mobile-menu-toggle" class="md:hidden flex-shrink-0 px-2 py-1.5 rounded-xl bg-indigo-900/80 hover:bg-indigo-800 text-amber-300 border border-indigo-700/60 transition-all flex items-center gap-1.5 text-xs font-bold shadow-sm active:scale-95 cursor-pointer" title="Buka/Tutup Menu Navigasi">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
              <span class="hidden sm:inline">Menu</span>
            </button>

            <div class="school-brand-logo w-9 h-9 sm:w-10 sm:h-10 rounded-xl text-indigo-950 font-bold flex items-center justify-center flex-shrink-0">
              <img src="logoSMP.png" alt="Logo SMPN 7 Purwakarta" class="w-9 h-9 sm:w-10 sm:h-10 object-contain">
            </div>
            <div class="min-w-0 flex-shrink-0">
              <span class="school-brand-name block max-w-[145px] sm:max-w-none font-bold tracking-tight text-sm text-white whitespace-nowrap">SMPN 7 PURWAKARTA</span>
              <p class="hidden sm:block text-[11px] text-indigo-200">Portal Akademik Siswa</p>
            </div>
          </div>

          <!-- Student Profile in Header -->
          <div class="flex items-center gap-1.5 sm:gap-3 flex-shrink-0">
            <div class="text-right hidden sm:block">
              <p class="text-xs font-bold text-white" id="header-student-name">Aditya Pratama</p>
              <p class="text-[11px] text-amber-300 font-medium" id="header-student-class">Kelas VIII A · NISN 0098427135</p>
            </div>
            <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-indigo-800 border-2 border-amber-400 flex items-center justify-center font-bold text-amber-300 text-xs sm:text-sm shadow-sm" id="header-student-avatar">
              AP
            </div>
            <button onclick="goToStudentLogin()" title="Keluar dari Akun" class="text-xs text-indigo-300 hover:text-white px-2 py-1 rounded-lg border border-indigo-800 hover:border-rose-400 transition-colors whitespace-nowrap">
              Keluar
            </button>
          </div>

        </div>
      </div>
    </header>

    <!-- WRAPPER BODY DENGAN NAVIGASI MENU MEMANJANG KE BAWAH DI SEBELAH KIRI -->
    <div class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col md:flex-row gap-6 items-start">

      <!-- ============================================================== -->
      <!-- NAVIGASI MENU SISWA (MEMANJANG KE BAWAH DI SEBELAH KIRI) -->
      <!-- ============================================================== -->
      <aside id="student-sidebar" class="w-full md:w-64 bg-white rounded-3xl border border-slate-200/80 p-4 shadow-sm flex-shrink-0 md:sticky md:top-24 space-y-4 flex flex-col transition-all z-20">
        
        <!-- Header Menu Navigasi -->
        <div class="px-2 pt-1 pb-2 border-b border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
            <p class="text-xs font-extrabold tracking-wide uppercase text-slate-800">Navigasi Utama</p>
          </div>
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" title="Sesi Belajar Aktif"></span>
        </div>

        <!-- 5 Pilihan Fitur Vertikal Memanjang ke Bawah -->
        <nav class="space-y-1.5 text-xs font-semibold">
          
          <!-- Fitur 1: Beranda -->
          <button onclick="switchStudentTab('beranda')" id="tab-btn-beranda" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl transition-all text-left bg-indigo-950 text-white shadow-sm font-bold">
            <div class="flex items-center gap-3">
              <svg class="w-4 h-4 text-amber-300 flex-shrink-0 nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
              <span>Beranda</span>
            </div>
          </button>

          <!-- Fitur 2: Materi dan Modul Pembelajaran -->
          <button onclick="switchStudentTab('materi')" id="tab-btn-materi" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl transition-all text-left text-slate-600 hover:bg-slate-100 hover:text-indigo-950">
            <div class="flex items-center gap-3">
              <svg class="w-4 h-4 text-slate-400 flex-shrink-0 nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              <span>Materi dan Modul Pembelajaran</span>
            </div>
          </button>

          <!-- Fitur 3: Rekapan Kehadiran -->
          <button onclick="switchStudentTab('kehadiran')" id="tab-btn-kehadiran" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl transition-all text-left text-slate-600 hover:bg-slate-100 hover:text-indigo-950">
            <div class="flex items-center gap-3">
              <svg class="w-4 h-4 text-slate-400 flex-shrink-0 nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <span>Kehadiran</span>
            </div>
          </button>

          <!-- Fitur 4: Nilai -->
          <button onclick="switchStudentTab('nilai')" id="tab-btn-nilai" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl transition-all text-left text-slate-600 hover:bg-slate-100 hover:text-indigo-950">
            <div class="flex items-center gap-3">
              <svg class="w-4 h-4 text-slate-400 flex-shrink-0 nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
              <span>Nilai</span>
            </div>
          </button>

          <!-- Fitur 5: Bantuan (FAQ) -->
          <button onclick="switchStudentTab('faq')" id="tab-btn-faq" class="w-full flex items-center justify-between px-3.5 py-3 rounded-2xl transition-all text-left text-slate-600 hover:bg-slate-100 hover:text-indigo-950">
            <div class="flex items-center gap-3">
              <svg class="w-4 h-4 text-slate-400 flex-shrink-0 nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>Bantuan</span>
            </div>
          </button>

        </nav>

        <!-- Informasi Tambahan di Bawah Menu -->
        <div class="pt-3 border-t border-slate-100">
          <div class="bg-indigo-50/70 border border-indigo-100 rounded-2xl p-3.5 text-xs text-indigo-950">
            <div class="flex items-center gap-2 mb-1.5">
              <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
              <p class="font-extrabold text-[11px] uppercase tracking-wider text-indigo-900">Info Akademik</p>
            </div>
            <p class="text-[11px] text-slate-600 leading-relaxed">
              Semester Ganjil 2026/2027 aktif. Pastikan tugas dan modul serta materi pembelajaran dibaca tepat waktu.
            </p>
          </div>
        </div>

      </aside>

      <!-- ============================================================== -->
      <!-- MAIN BODY CONTENT (DI SEBELAH KANAN SIDEBAR) -->
      <!-- ============================================================== -->
      <main class="flex-1 min-w-0 w-full space-y-6">
        <div class="flex items-center justify-between gap-3 px-1 text-[11px] text-slate-500">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>Sistem aktif</span>
            <span class="text-slate-300">•</span>
            <span>Semester Ganjil 2026/2027</span>
          </div>
          <span class="hidden sm:inline text-slate-400">Portal Akademik SMPN 7 Purwakarta</span>
        </div>

      <!-- ============================================================== -->
      <!-- TAB 1: RINGKASAN (BERANDA SISWA) -->
      <!-- ============================================================== -->
      <section id="student-view-beranda" class="space-y-6">
        
        <!-- Welcome Card -->
        <div class="bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-md relative overflow-hidden">
          <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
              <span class="text-xs bg-amber-400 text-indigo-950 font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Semester Ganjil 2026/2027</span>
              <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-3" id="student-welcome-title">Halo, Aditya Pratama! 👋</h2>
              <p class="text-xs sm:text-sm text-indigo-200 mt-1 max-w-xl">
                Tetap semangat belajar. Di portal ini kamu dapat membaca materi yang dibagikan guru, memeriksa riwayat kehadiranmu, dan mengecek nilai tugas harianmu.
              </p>
            </div>

            <!-- Student Badge / Status Card -->
            <div class="bg-white/10 backdrop-blur-md border border-white/10 rounded-2xl p-4 text-xs min-w-[200px]">
              <p class="text-indigo-200 text-[11px]">Wali Kelas:</p>
              <p class="font-bold text-white text-sm">Ibu Siti Rahmawati, S.Pd</p>
              <hr class="my-2 border-white/10">
              <div class="flex items-center justify-between">
                <span class="text-indigo-200">Status Belajar:</span>
                <span class="px-2 py-0.5 rounded-full font-bold text-[11px]" id="student-overall-status-badge">Tuntas</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 3 Highlight Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
          
          <!-- Card Nilai Pribadi -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Rata-Rata Nilai</p>
              <p class="text-2xl font-black text-indigo-950 mt-1" id="stat-my-average">85,2</p>
              <p class="text-[11px] text-emerald-600 mt-1" id="stat-my-kkm-note">✓ Di atas target KKM (75)</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
          </div>

          <!-- Card Kehadiran Pribadi -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Tingkat Kehadiran</p>
              <p class="text-2xl font-black text-emerald-600 mt-1" id="stat-my-attendance-pct">94,4%</p>
              <p class="text-[11px] text-slate-500 mt-1" id="stat-my-attendance-note">34 Hadir · 1 Izin · 1 Sakit</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
          </div>

          <!-- Card Modul Tersedia -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Bahan Modul Ajar</p>
              <p class="text-2xl font-black text-indigo-950 mt-1">3 File</p>
              <p class="text-[11px] text-indigo-600 mt-1">Tersedia untuk dibaca</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
          </div>

        </div>

        <!-- Catatan Pembimbing Guru untuk Siswa Ini (Privat) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm text-xs">
          <div class="flex items-center gap-2 mb-2">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
            <h3 class="font-bold text-slate-800 text-sm">Catatan Pembelajaran Khusus dari Guru Pengampu</h3>
          </div>
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/60 text-slate-700 italic leading-relaxed" id="student-personal-notes-box">
            "Aktif bertanya dan memiliki daya nalar geometri yang sangat baik. Pertahankan fokus saat latihan soal bentuk akar."
          </div>
        </div>

      </section>


      <!-- ============================================================== -->
      <!-- TAB 2: MATERI & MODUL AJAR (BACA ONLINE SAJA) -->
      <!-- ============================================================== -->
      <section id="student-view-materi" class="hidden space-y-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h2 class="text-lg font-bold text-indigo-950">Materi dan Modul Pembelajaran</h2>
              <p class="text-xs text-slate-500">Baca materi dan  modul pelajaran yang dibagikan guru mata pelajaranmu secara daring.</p>
            </div>
            <div class="flex items-center gap-2 text-xs">
              <span class="text-slate-500 font-medium">Filter Mapel:</span>
              <select class="bg-slate-100 border border-slate-300 rounded-lg px-2.5 py-1 text-slate-800 font-bold">
                <option>Semua Mata Pelajaran</option>
                <option selected>Matematika</option>
                <option>IPA</option>
              </select>
            </div>
          </div>
        </div>

        <div class="space-y-3" id="student-modules-list">
          
          <!-- Modul 1 -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-indigo-300 transition-all">
            <div class="flex items-start gap-3.5">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold flex-shrink-0 text-xs">
                MODUL
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-bold bg-indigo-50 text-indigo-800 px-2 py-0.5 rounded">Matematika · Bab 1</span>
                  <span class="text-[10px] text-slate-400">14 Halaman</span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm mt-1">Bilangan Berpangkat & Bentuk Akar</h3>
                <p class="text-xs text-slate-500 mt-0.5">Modul konsep dasar eksponen, sifat perkalian bilangan berpangkat, dan penyederhanaan bentuk akar.</p>
              </div>
            </div>

            <button onclick="alert('Membuka materi online: Bilangan Berpangkat & Bentuk Akar')" class="px-4 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold text-xs rounded-xl transition-all flex items-center gap-1.5 flex-shrink-0">
              <svg class="w-3.5 h-3.5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              <span>Baca Materi</span>
            </button>
          </div>

          <!-- Modul 2 -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-indigo-300 transition-all">
            <div class="flex items-start gap-3.5">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold flex-shrink-0 text-xs">
                LKPD
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-bold bg-indigo-50 text-indigo-800 px-2 py-0.5 rounded">Matematika · Bab 2</span>
                  <span class="text-[10px] text-slate-400">8 Halaman</span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm mt-1">Latihan Mandiri & Teorema Pythagoras</h3>
                <p class="text-xs text-slate-500 mt-0.5">Lembar kerja mandiri untuk menguji pembuktian rumus Pythagoras pada segitiga siku-siku.</p>
              </div>
            </div>

            <button onclick="alert('Membuka materi online: Teorema Pythagoras')" class="px-4 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold text-xs rounded-xl transition-all flex items-center gap-1.5 flex-shrink-0">
              <svg class="w-3.5 h-3.5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              <span>Baca Materi</span>
            </button>
          </div>

          <!-- Modul 3 -->
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-indigo-300 transition-all">
            <div class="flex items-start gap-3.5">
              <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold flex-shrink-0 text-xs">
                KISI
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-bold bg-amber-50 text-amber-800 px-2 py-0.5 rounded">Evaluasi</span>
                  <span class="text-[10px] text-slate-400">Kisi-Kisi Ujian</span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm mt-1">Kisi-Kisi Penilaian Tengah Semester</h3>
                <p class="text-xs text-slate-500 mt-0.5">Panduan cakupan materi yang akan diujikan pada Sumatif Tengah Semester (STS).</p>
              </div>
            </div>

            <button onclick="alert('Membuka materi online: Kisi-Kisi STS')" class="px-4 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold text-xs rounded-xl transition-all flex items-center gap-1.5 flex-shrink-0">
              <svg class="w-3.5 h-3.5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              <span>Baca Materi</span>
            </button>
          </div>

        </div>

      </section>


      <!-- ============================================================== -->
      <!-- TAB 3: REKAP KEHADIRAN PRIBADI SISWA -->
      <!-- ============================================================== -->
      <section id="student-view-kehadiran" class="hidden space-y-6">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-indigo-950">Rekapan Kehadiran</h2>
                <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-2.5 py-0.5 rounded-full" id="student-attend-badge">Predikat: A (Sangat Disiplin)</span>
              </div>
              <p class="text-xs text-slate-500 mt-1">
                Catatan kehadiran Anda selama Semester Ganjil 2026/2027. Persentase minimum kehadiran adalah 85%.
              </p>
            </div>

            <div class="bg-indigo-50 border border-indigo-100 px-4 py-2 rounded-xl text-xs text-indigo-950">
              <span>Total Hari Pertemuan: </span>
              <strong id="student-attend-total-meetings">36 Kali</strong>
            </div>
          </div>

          <!-- Progress Bar Kehadiran Siswa -->
          <div class="mt-5 pt-4 border-t border-slate-100">
            <div class="flex items-center justify-between text-xs font-semibold mb-1.5">
              <span class="text-slate-600">Tingkat Kehadiran Pribadi</span>
              <span class="text-indigo-950 font-black text-sm" id="student-attend-pct-bar">94,4%</span>
            </div>
            <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
              <div class="bg-emerald-500 h-full rounded-full transition-all duration-500" id="student-attend-progress-fill" style="width: 94.4%"></div>
            </div>
          </div>

          <!-- Rincian 4 Status -->
          <div class="grid grid-cols-4 gap-2 mt-4 pt-3 text-center text-xs">
            <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200">
              <span class="block text-lg font-black text-emerald-700" id="student-count-h">34</span>
              <span class="text-[11px] text-emerald-800 font-bold">Hadir (H)</span>
            </div>
            <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200">
              <span class="block text-lg font-black text-amber-700" id="student-count-i">1</span>
              <span class="text-[11px] text-amber-800 font-bold">Izin (I)</span>
            </div>
            <div class="p-2.5 rounded-xl bg-purple-50 border border-purple-200">
              <span class="block text-lg font-black text-purple-700" id="student-count-s">1</span>
              <span class="text-[11px] text-purple-800 font-bold">Sakit (S)</span>
            </div>
            <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200">
              <span class="block text-lg font-black text-rose-700" id="student-count-a">0</span>
              <span class="text-[11px] text-rose-800 font-bold">Alpa (A)</span>
            </div>
          </div>
        </div>

        <!-- Riwayat Pertemuan Hari Masuk Siswa -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
          <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between text-xs">
            <span class="font-bold text-slate-800">Riwayat Presensi Per Hari Masuk (Bulan Ini)</span>
            <span class="text-slate-400">Data tercatat otomatis oleh guru pengampu</span>
          </div>

          <div class="divide-y divide-slate-100 text-xs" id="student-daily-history-list">
            <!-- Dynamically populated -->
          </div>
        </div>

      </section>


      <!-- ============================================================== -->
      <!-- TAB 4: REKAP NILAI (MURNI NILAI TUGAS & ULANGAN PRIBADI) -->
      <!-- ============================================================== -->
      <section id="student-view-nilai" class="space-y-6 hidden">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-indigo-950">Nilai</h2>
                <span class="text-xs bg-indigo-100 text-indigo-800 font-bold px-2.5 py-0.5 rounded-full">Nilai Pribadi</span>
              </div>
              <p class="text-xs text-slate-500 mt-1">
                Laporan capaian nilai tugas, ulangan harian, dan sumatif Anda. Batas KKM yang berlaku adalah <strong id="student-subject-kkm-note">75</strong>.
              </p>
            </div>

            <!-- Dropdown Pilihan Nilai Mapel yang Ingin Dilihat -->
            <div class="flex items-center gap-2.5 bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-2xl">
              <label for="select-student-subject" class="text-xs font-bold text-slate-700 whitespace-nowrap flex items-center gap-1.5">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span>Pilih Mapel:</span>
              </label>
              <select id="select-student-subject" onchange="handleStudentSubjectChange(this.value)" class="bg-indigo-950 text-white font-bold text-xs rounded-xl px-3 py-1.5 border border-indigo-900 shadow-xs focus:ring-2 focus:ring-amber-400 focus:outline-none cursor-pointer">
                <option value="Matematika" selected>Matematika</option>
                <option value="IPA">Ilmu Pengetahuan Alam (IPA)</option>
                <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                <option value="Bahasa Inggris">Bahasa Inggris</option>
                <option value="Informatika">Informatika</option>
                <option value="Pendidikan Pancasila">Pendidikan Pancasila</option>
                <option value="IPS">Ilmu Pengetahuan Sosial (IPS)</option>
                <option value="PJOK">PJOK (Penjaskes)</option>
                <option value="Seni Budaya">Seni Budaya</option>
                <option value="PAI">Pendidikan Agama Islam (PAI)</option>
                <option value="Bahasa Sunda">Bahasa Sunda</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Tabel Rincian Nilai Mata Pelajaran -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
          <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
            <span class="font-bold text-slate-800" id="student-grades-table-title">Daftar Nilai Tugas & Evaluasi - Matematika</span>
            <span class="text-slate-600 font-medium" id="student-subject-teacher">Guru Pengampu: Ibu Siti Rahmawati, S.Pd</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-100 text-slate-600 font-semibold border-b border-slate-200">
                  <th class="py-3 px-4 w-12 text-center">No</th>
                  <th class="py-3 px-4">Nama Penilaian</th>
                  <th class="py-3 px-4">Tanggal Pelaksanaan</th>
                  <th class="py-3 px-4 text-center w-24">KKM</th>
                  <th class="py-3 px-4 text-center w-28 font-bold">Nilai Anda</th>
                  <th class="py-3 px-4 text-center w-32">Status Capaian</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100" id="student-grades-table-body">
                <!-- Javascript will populate rows -->
              </tbody>
            </table>
          </div>

          <!-- Total Rata-Rata Footer -->
          <div class="p-5 bg-indigo-50/60 border-t border-indigo-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div>
              <span class="font-bold text-indigo-950 text-sm">Rata-Rata Nilai Tugas & Ulangan</span>
              <p class="text-slate-500 text-[11px] mt-0.5">Akumulasi seluruh nilai yang telah dinilai guru di semester ini.</p>
            </div>
            
            <div class="flex items-center gap-4">
              <div class="text-right">
                <span class="text-slate-500 block text-[11px]">Nilai Rata-Rata:</span>
                <span class="text-xl font-black text-indigo-950" id="student-final-score-display">85,2</span>
              </div>
              <span class="px-3 py-1.5 rounded-xl font-bold text-xs shadow-sm" id="student-final-status-badge">Tuntas</span>
            </div>
          </div>
        </div>

      </section>

      <!-- ============================================================== -->
      <!-- TAB 5: PUSAT BANTUAN & FAQ SISWA -->
      <!-- ============================================================== -->
      <section id="student-view-faq" class="hidden space-y-6">
        
        <div class="bg-white rounded-3xl border border-slate-200/80 p-6 sm:p-8 shadow-sm">
          
          <div class="flex items-center gap-3.5 mb-2">
            <div class="w-12 h-12 rounded-2xl bg-indigo-900 text-amber-300 font-bold flex items-center justify-center text-xl shadow-sm">
              ❓
            </div>
            <div>
              <h2 class="text-xl sm:text-2xl font-extrabold text-indigo-950">Bantuan & FAQ Siswa</h2>
              <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Pertanyaan umum seputar nilai, materi, kehadiran, dan akun portal siswa SMPN 7 Purwakarta.</p>
            </div>
          </div>

          <div class="mt-6 space-y-3.5 text-xs">

            <!-- FAQ 1 -->
            <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
              <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">1</span>
                  <span class="text-xs sm:text-sm">Apakah teman sekelas bisa melihat nilai saya di portal ini?</span>
                </div>
                <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
              </summary>
              <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                <p><strong>Tidak.</strong> Nilai di portal ini bersifat <strong>pribadi dan rahasia (confidential)</strong>. Kamu hanya dapat melihat nilaimu sendiri setelah login menggunakan NISN milikmu. Teman sekelasmu tidak memiliki akses untuk melihat riwayat nilai maupun catatan evaluasi pribadimu.</p>
              </div>
            </details>

            <!-- FAQ 2 -->
            <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
              <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">2</span>
                  <span class="text-xs sm:text-sm">Bagaimana cara melihat nilai untuk mata pelajaran lain?</span>
                </div>
                <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
              </summary>
              <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                <p>Klik menu <strong>Nilai Saya Saya</strong> di bilah atas. Di bagian atas tabel terdapat kotak pilihan (dropdown) bertuliskan <strong>"Pilih Mata Pelajaran"</strong>. Klik menu tersebut untuk memilih mata pelajaran lain (seperti IPA, Bahasa Indonesia, Bahasa Inggris, Informatika, PJOK, dll.). Nilai tugas harian dan sumatif untuk mapel yang kamu pilih akan langsung ditampilkan secara otomatis.</p>
              </div>
            </details>

            <!-- FAQ 3 -->
            <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
              <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">3</span>
                  <span class="text-xs sm:text-sm">Berapa batas KKM / KKTP dan apa yang harus saya lakukan jika nilai saya di bawah batas tersebut?</span>
                </div>
                <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
              </summary>
              <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                <p>Batas KKM (Kriteria Ketuntasan Minimal) atau KKTP umumnya adalah <strong>75</strong> (atau sesuai ketetapan guru mapel, misal 78 untuk Bahasa Indonesia/PPKn). Jika nilaimu di bawah KKM, status akan bertanda merah bertuliskan <strong>"Perlu Remedial"</strong>. Jangan panik! Segera temui Guru Mata Pelajaran pengampu untuk mengikuti jadwal pendampingan atau program ujian perbaikan (remedial).</p>
              </div>
            </details>

            <!-- FAQ 4 -->
            <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
              <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">4</span>
                  <span class="text-xs sm:text-sm">Bagaimana jika ada kekeliruan absensi (misalnya hadir tapi tertulis alpa)?</span>
                </div>
                <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
              </summary>
              <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                <p>Periksa riwayat pada menu <strong>Kehadiran Saya Saya</strong>. Jika kamu mendapati tanggal di mana kamu sebenarnya hadir atau memiliki surat izin/dokter tetapi tercatat Alpa, segera laporkan kepada <strong>Wali Kelas</strong> atau <strong>Guru Piket</strong> dengan menunjukkan bukti surat keterangan agar status kehadiranmu dapat segera diperbaiki oleh guru.</p>
              </div>
            </details>

            <!-- FAQ 5 -->
            <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
              <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">5</span>
                  <span class="text-xs sm:text-sm">Bagaimana cara membaca dan mengunduh modul ajar dari guru?</span>
                </div>
                <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
              </summary>
              <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                <p>Buka menu <strong>Materi dan Modul Pembelajaran</strong>. Di sana terdapat modul kurikulum dan LKPD yang dibagikan oleh bapak/ibu guru. Kamu dapat membaca langsung secara online atau mengklik tombol <strong>Unduh Dokumen</strong> untuk menyimpannya di perangkat HP atau laptop agar bisa dipelajari tanpa koneksi internet.</p>
              </div>
            </details>

            <!-- FAQ 6 -->
            <details class="group bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden open:bg-indigo-50/30 open:border-indigo-200 transition-colors">
              <summary class="flex items-center justify-between p-4 cursor-pointer font-bold text-slate-800 group-hover:text-indigo-950 select-none">
                <div class="flex items-center gap-2.5">
                  <span class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-900 font-extrabold flex items-center justify-center text-[11px]">6</span>
                  <span class="text-xs sm:text-sm">Ke mana saya harus menghubungi jika lupa NISN atau mengalami kendala login?</span>
                </div>
                <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
              </summary>
              <div class="px-5 pb-4 text-slate-600 leading-relaxed text-xs border-t border-slate-100/80 pt-3">
                <p>Jika kamu lupa NISN (Nomor Induk Siswa Nasional) atau mengalami kendala saat masuk ke portal, silakan tanyakan kepada <strong>Wali Kelas</strong> masing-masing atau datang langsung ke <strong>Ruang Tata Usaha (TU) SMPN 7 Purwakarta</strong> dengan membawa kartu pelajar.</p>
              </div>
            </details>

          </div>

          <!-- Student Support Contact Card -->
          <div class="mt-8 p-5 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
              <div class="w-10 h-10 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center text-lg font-bold">
                🤝
              </div>
              <div>
                <h4 class="font-bold text-indigo-950 text-sm">Butuh Konsultasi Belajar atau Curhat Akademik?</h4>
                <p class="text-slate-600 text-[11px] mt-0.5">Bimbingan Konseling (BK) dan Wali Kelas selalu siap mendengarkan dan membimbingmu.</p>
              </div>
            </div>
            <span class="px-4 py-2 bg-indigo-950 text-white rounded-xl font-bold text-xs shadow-xs text-center">Ruang BK SMPN 7 Purwakarta</span>
          </div>

        </div>

      </section>

    </main>

    </div>

    <!-- Footer Siswa -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400">
      Portal Siswa SMPN 7 Purwakarta · Portal Akademik • Pembelajaran • Nilai • Kehadiran
    </footer>

  </div>


  <!-- ============================================================== -->
  <!-- JAVASCRIPT LOGIC UNTUK PORTAL SISWA -->
  <!-- ============================================================== -->
  <script>
    // DATA DUA SISWA LENGKAP MULTI-MAPEL (ADITYA PRATAMA & DAFFA RAMADHAN)
    const studentsData = {
      1: {
        id: 1,
        nisn: '0098427135',
        name: 'Aditya Pratama',
        class: 'Kelas VIII A',
        avatar: 'AP',
        teacher: 'Ibu Siti Rahmawati, S.Pd',
        notes: 'Aktif bertanya dan memiliki daya nalar geometri yang sangat baik. Pertahankan fokus saat latihan soal bentuk akar.',
        overallPassed: true,
        average: '85.2',
        attendance: {
          pct: '94.4%',
          hadir: 34,
          izin: 1,
          sakit: 1,
          alpa: 0,
          total: 36,
          predicate: 'A (Sangat Disiplin)'
        },
        subjects: {
          'Matematika': {
            teacher: 'Ibu Siti Rahmawati, S.Pd',
            kkm: 75,
            average: '85.0',
            passed: true,
            grades: [
              { no: 1, title: 'Tugas 1: Eksponen & Pangkat', date: 'Senin, 08 Sep 2026', kkm: 75, score: 82 },
              { no: 2, title: 'Tugas 2: Bentuk Akar', date: 'Senin, 15 Sep 2026', kkm: 75, score: 88 },
              { no: 3, title: 'Sumatif 1: Ulangan Bab 1 Eksponen', date: 'Kamis, 25 Sep 2026', kkm: 75, score: 85 }
            ]
          },
          'IPA': {
            teacher: 'Bapak Budi Santoso, M.Pd',
            kkm: 75,
            average: '87.3',
            passed: true,
            grades: [
              { no: 1, title: 'Tugas 1: Struktur Sel Hewan & Tumbuhan', date: 'Selasa, 09 Sep 2026', kkm: 75, score: 85 },
              { no: 2, title: 'Praktikum: Pengamatan Mikroskopis Daun', date: 'Selasa, 16 Sep 2026', kkm: 75, score: 90 },
              { no: 3, title: 'Sumatif 1: Sistem Organisasi Kehidupan', date: 'Selasa, 23 Sep 2026', kkm: 75, score: 87 }
            ]
          },
          'Bahasa Indonesia': {
            teacher: 'Ibu Dra. Hj. Nur Aini',
            kkm: 78,
            average: '88.0',
            passed: true,
            grades: [
              { no: 1, title: 'Tugas 1: Struktur Teks Laporan Hasil Observasi', date: 'Rabu, 10 Sep 2026', kkm: 78, score: 86 },
              { no: 2, title: 'Tugas 2: Kaidah Kebahasaan & Kalimat Definisi', date: 'Rabu, 17 Sep 2026', kkm: 78, score: 92 },
              { no: 3, title: 'Sumatif 1: Menulis Teks LHO Lingkungan Sekolah', date: 'Rabu, 24 Sep 2026', kkm: 78, score: 86 }
            ]
          },
          'Bahasa Inggris': {
            teacher: 'Ibu Rina Nurhasanah, S.Pd',
            kkm: 75,
            average: '84.0',
            passed: true,
            grades: [
              { no: 1, title: 'Assignment 1: Past Tense & Recount Text', date: 'Jumat, 12 Sep 2026', kkm: 75, score: 80 },
              { no: 2, title: 'Assignment 2: Regular & Irregular Verbs Quiz', date: 'Jumat, 19 Sep 2026', kkm: 75, score: 86 },
              { no: 3, title: 'Summative 1: Writing Holiday Experience', date: 'Jumat, 26 Sep 2026', kkm: 75, score: 86 }
            ]
          },
          'Informatika': {
            teacher: 'Bapak Ahmad Fauzi, S.Kom',
            kkm: 75,
            average: '91.0',
            passed: true,
            grades: [
              { no: 1, title: 'Praktikum 1: Berpikir Komputasional (Algoritma)', date: 'Kamis, 11 Sep 2026', kkm: 75, score: 94 },
              { no: 2, title: 'Praktikum 2: Pengolah Angka & Rumus Logika Excel', date: 'Kamis, 18 Sep 2026', kkm: 75, score: 90 },
              { no: 3, title: 'Sumatif 1: Analisis Data & Logika Percabangan', date: 'Kamis, 25 Sep 2026', kkm: 75, score: 89 }
            ]
          },
          'Pendidikan Pancasila': {
            teacher: 'Bapak Dedi Suhendar, S.Pd',
            kkm: 78,
            average: '83.3',
            passed: true,
            grades: [
              { no: 1, title: 'Tugas 1: Kedudukan Pancasila Sebagai Pandangan Hidup', date: 'Senin, 08 Sep 2026', kkm: 78, score: 82 },
              { no: 2, title: 'Tugas 2: Norma & Penegakan Hukum di Masyarakat', date: 'Senin, 15 Sep 2026', kkm: 78, score: 85 },
              { no: 3, title: 'Sumatif 1: Nilai Pancasila dalam Sejarah Perjuangan', date: 'Senin, 22 Sep 2026', kkm: 78, score: 83 }
            ]
          },
          'IPS': {
            teacher: 'Bapak Tatang Sutisna, S.Pd',
            kkm: 75,
            average: '82.0',
            passed: true,
            grades: [
              { no: 1, title: 'Tugas 1: Letak Geografis & Iklim Benua Asia', date: 'Selasa, 09 Sep 2026', kkm: 75, score: 80 },
              { no: 2, title: 'Tugas 2: Interaksi Antarruang Negara ASEAN', date: 'Selasa, 16 Sep 2026', kkm: 75, score: 84 },
              { no: 3, title: 'Sumatif 1: Dampak Globalisasi pada Ekonomi Lokal', date: 'Selasa, 23 Sep 2026', kkm: 75, score: 82 }
            ]
          },
          'PJOK': {
            teacher: 'Bapak Asep Kurnia, S.Pd',
            kkm: 78,
            average: '89.3',
            passed: true,
            grades: [
              { no: 1, title: 'Praktik: Keterampilan Passing Sepak Bola', date: 'Sabtu, 13 Sep 2026', kkm: 78, score: 88 },
              { no: 2, title: 'Praktik: Servis Bawah & Atas Bola Voli', date: 'Sabtu, 20 Sep 2026', kkm: 78, score: 92 },
              { no: 3, title: 'Sumatif 1: Kebugaran Jasmani & Kelincahan', date: 'Sabtu, 27 Sep 2026', kkm: 78, score: 88 }
            ]
          },
          'Seni Budaya': {
            teacher: 'Ibu Maya Lestari, S.Sn',
            kkm: 75,
            average: '86.0',
            passed: true,
            grades: [
              { no: 1, title: 'Tugas 1: Konsep Ragam Seni Lukis Daerah', date: 'Rabu, 10 Sep 2026', kkm: 75, score: 84 },
              { no: 2, title: 'Karya: Sketsa Gambar Ilustrasi Tradisional', date: 'Rabu, 17 Sep 2026', kkm: 75, score: 88 },
              { no: 3, title: 'Sumatif 1: Apresiasi Seni Rupa Nusantara', date: 'Rabu, 24 Sep 2026', kkm: 75, score: 86 }
            ]
          }
        },
        meetingsHistory: [
          { name: 'Pertemuan 1 (Matematika)', date: 'Kamis, 01 Okt 2026', status: 'H', note: 'Hadir tepat waktu' },
          { name: 'Pertemuan 2 (Matematika)', date: 'Kamis, 08 Okt 2026', status: 'H', note: 'Hadir tepat waktu' },
          { name: 'Pertemuan 3 (Matematika)', date: 'Kamis, 15 Okt 2026', status: 'H', note: 'Hadir tepat waktu' }
        ]
      },
      5: {
        id: 5,
        nisn: '0098427178',
        name: 'Daffa Ramadhan',
        class: 'Kelas VIII A',
        avatar: 'DR',
        teacher: 'Ibu Siti Rahmawati, S.Pd',
        notes: 'Perlu bimbingan remedial untuk materi Tugas 1 & Sumatif 1 (nilai di bawah KKM 75). Perhatikan kedisiplinan agar tidak menambah catatan alpa.',
        overallPassed: false,
        average: '72.0',
        attendance: {
          pct: '80.5%',
          hadir: 29,
          izin: 2,
          sakit: 1,
          alpa: 4,
          total: 36,
          predicate: 'D (Perlu Pembinaan)'
        },
        subjects: {
          'Matematika': {
            teacher: 'Ibu Siti Rahmawati, S.Pd',
            kkm: 75,
            average: '72.0',
            passed: false,
            grades: [
              { no: 1, title: 'Tugas 1: Eksponen & Pangkat', date: 'Senin, 08 Sep 2026', kkm: 75, score: 70 },
              { no: 2, title: 'Tugas 2: Bentuk Akar', date: 'Senin, 15 Sep 2026', kkm: 75, score: 74 },
              { no: 3, title: 'Sumatif 1: Ulangan Bab 1 Eksponen', date: 'Kamis, 25 Sep 2026', kkm: 75, score: 72 }
            ]
          },
          'IPA': {
            teacher: 'Bapak Budi Santoso, M.Pd',
            kkm: 75,
            average: '71.3',
            passed: false,
            grades: [
              { no: 1, title: 'Tugas 1: Struktur Sel Hewan & Tumbuhan', date: 'Selasa, 09 Sep 2026', kkm: 75, score: 70 },
              { no: 2, title: 'Praktikum: Pengamatan Mikroskopis Daun', date: 'Selasa, 16 Sep 2026', kkm: 75, score: 76 },
              { no: 3, title: 'Sumatif 1: Sistem Organisasi Kehidupan', date: 'Selasa, 23 Sep 2026', kkm: 75, score: 68 }
            ]
          },
          'Bahasa Indonesia': {
            teacher: 'Ibu Dra. Hj. Nur Aini',
            kkm: 78,
            average: '79.0',
            passed: true,
            grades: [
              { no: 1, title: 'Tugas 1: Struktur Teks Laporan Hasil Observasi', date: 'Rabu, 10 Sep 2026', kkm: 78, score: 78 },
              { no: 2, title: 'Tugas 2: Kaidah Kebahasaan & Kalimat Definisi', date: 'Rabu, 17 Sep 2026', kkm: 78, score: 80 },
              { no: 3, title: 'Sumatif 1: Menulis Teks LHO Lingkungan Sekolah', date: 'Rabu, 24 Sep 2026', kkm: 78, score: 79 }
            ]
          },
          'Bahasa Inggris': {
            teacher: 'Ibu Rina Nurhasanah, S.Pd',
            kkm: 75,
            average: '68.7',
            passed: false,
            grades: [
              { no: 1, title: 'Assignment 1: Past Tense & Recount Text', date: 'Jumat, 12 Sep 2026', kkm: 75, score: 65 },
              { no: 2, title: 'Assignment 2: Regular & Irregular Verbs Quiz', date: 'Jumat, 19 Sep 2026', kkm: 75, score: 72 },
              { no: 3, title: 'Summative 1: Writing Holiday Experience', date: 'Jumat, 26 Sep 2026', kkm: 75, score: 69 }
            ]
          },
          'Informatika': {
            teacher: 'Bapak Ahmad Fauzi, S.Kom',
            kkm: 75,
            average: '82.0',
            passed: true,
            grades: [
              { no: 1, title: 'Praktikum 1: Berpikir Komputasional (Algoritma)', date: 'Kamis, 11 Sep 2026', kkm: 75, score: 85 },
              { no: 2, title: 'Praktikum 2: Pengolah Angka & Rumus Logika Excel', date: 'Kamis, 18 Sep 2026', kkm: 75, score: 80 },
              { no: 3, title: 'Sumatif 1: Analisis Data & Logika Percabangan', date: 'Kamis, 25 Sep 2026', kkm: 75, score: 81 }
            ]
          },
          'Pendidikan Pancasila': {
            teacher: 'Bapak Dedi Suhendar, S.Pd',
            kkm: 78,
            average: '76.0',
            passed: false,
            grades: [
              { no: 1, title: 'Tugas 1: Kedudukan Pancasila Sebagai Pandangan Hidup', date: 'Senin, 08 Sep 2026', kkm: 78, score: 75 },
              { no: 2, title: 'Tugas 2: Norma & Penegakan Hukum di Masyarakat', date: 'Senin, 15 Sep 2026', kkm: 78, score: 78 },
              { no: 3, title: 'Sumatif 1: Nilai Pancasila dalam Sejarah Perjuangan', date: 'Senin, 22 Sep 2026', kkm: 78, score: 75 }
            ]
          },
          'IPS': {
            teacher: 'Bapak Tatang Sutisna, S.Pd',
            kkm: 75,
            average: '74.3',
            passed: false,
            grades: [
              { no: 1, title: 'Tugas 1: Letak Geografis & Iklim Benua Asia', date: 'Selasa, 09 Sep 2026', kkm: 75, score: 72 },
              { no: 2, title: 'Tugas 2: Interaksi Antarruang Negara ASEAN', date: 'Selasa, 16 Sep 2026', kkm: 75, score: 76 },
              { no: 3, title: 'Sumatif 1: Dampak Globalisasi pada Ekonomi Lokal', date: 'Selasa, 23 Sep 2026', kkm: 75, score: 75 }
            ]
          },
          'PJOK': {
            teacher: 'Bapak Asep Kurnia, S.Pd',
            kkm: 78,
            average: '84.0',
            passed: true,
            grades: [
              { no: 1, title: 'Praktik: Keterampilan Passing Sepak Bola', date: 'Sabtu, 13 Sep 2026', kkm: 78, score: 85 },
              { no: 2, title: 'Praktik: Servis Bawah & Atas Bola Voli', date: 'Sabtu, 20 Sep 2026', kkm: 78, score: 83 },
              { no: 3, title: 'Sumatif 1: Kebugaran Jasmani & Kelincahan', date: 'Sabtu, 27 Sep 2026', kkm: 78, score: 84 }
            ]
          },
          'Seni Budaya': {
            teacher: 'Ibu Maya Lestari, S.Sn',
            kkm: 75,
            average: '78.0',
            passed: true,
            grades: [
              { no: 1, title: 'Tugas 1: Konsep Ragam Seni Lukis Daerah', date: 'Rabu, 10 Sep 2026', kkm: 75, score: 75 },
              { no: 2, title: 'Karya: Sketsa Gambar Ilustrasi Tradisional', date: 'Rabu, 17 Sep 2026', kkm: 75, score: 80 },
              { no: 3, title: 'Sumatif 1: Apresiasi Seni Rupa Nusantara', date: 'Rabu, 24 Sep 2026', kkm: 75, score: 79 }
            ]
          }
        },
        meetingsHistory: [
          { name: 'Pertemuan 1 (Matematika)', date: 'Kamis, 01 Okt 2026', status: 'I', note: 'Izin surat orang tua' },
          { name: 'Pertemuan 2 (Matematika)', date: 'Kamis, 08 Okt 2026', status: 'A', note: 'Tanpa keterangan (Alpa)' },
          { name: 'Pertemuan 3 (Matematika)', date: 'Kamis, 15 Okt 2026', status: 'H', note: 'Hadir' }
        ]
      }
    };

    let activeStudent = null;
    let selectedStudentSubject = 'Matematika';

    const STORAGE_KEY_LOGIN = 'smp_student_logged_in';
    const STORAGE_KEY_NISN = 'smp_student_nisn';
    const STORAGE_KEY_TAB = 'smp_student_active_tab';
    const STORAGE_KEY_SUBJECT = 'smp_student_selected_subject';

    let currentStudentTab = 'beranda';

    async function fetchLatestStudentData(nisn, silent = false) {
      if (!nisn) return;
      try {
        const formData = new FormData();
        formData.append('action', 'get_student_data');
        formData.append('nisn', nisn);

        const resp = await fetch('murid.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success && res.student) {
          activeStudent = res.student;
          try {
            localStorage.setItem('smp_student_cache', JSON.stringify(res.student));
          } catch (e) {}
          loadStudentDashboard(currentStudentTab || 'beranda');
        }
      } catch (err) {
        if (!silent) console.error('Gagal mengambil data siswa terbaru:', err);
      }
    }

    function showStudentScreen(screenName) {
      const loginScreen = document.getElementById('screen-login-student');
      const dashboardScreen = document.getElementById('screen-dashboard-student');

      if (screenName === 'login') {
        document.documentElement.classList.remove('is-student-logged-in');
        if (loginScreen) {
          loginScreen.classList.remove('hidden');
          loginScreen.style.display = 'flex';
        }
        if (dashboardScreen) {
          dashboardScreen.classList.add('hidden');
          dashboardScreen.style.display = 'none';
        }
        window.scrollTo(0, 0);
      } else {
        document.documentElement.classList.add('is-student-logged-in');
        if (loginScreen) {
          loginScreen.classList.add('hidden');
          loginScreen.style.display = 'none';
        }
        if (dashboardScreen) {
          dashboardScreen.classList.remove('hidden');
          dashboardScreen.style.display = 'flex';
        }
      }
    }

    async function handleStudentLogin(e) {
      e.preventDefault();
      const enteredNisn = document.getElementById('input-nisn').value.trim();
      const enteredDob  = document.getElementById('input-dob').value.trim();

      const btn = e.target.querySelector('button[type="submit"]');
      const origText = btn ? btn.innerHTML : '';
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>Memverifikasi Data Siswa...</span>';
      }

      try {
        const formData = new FormData();
        formData.append('action', 'student_login');
        formData.append('nisn', enteredNisn);
        formData.append('dob', enteredDob);

        const resp = await fetch('murid.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.success && res.student) {
          activeStudent = res.student;
          try {
            localStorage.setItem(STORAGE_KEY_LOGIN, 'true');
            localStorage.setItem(STORAGE_KEY_NISN, enteredNisn);
            localStorage.setItem('smp_student_cache', JSON.stringify(res.student));
          } catch (err) {}

          loadStudentDashboard('beranda');
          showStudentScreen('dashboard');

          try {
            history.pushState({ page: 'dashboard', tab: 'beranda' }, '', '#beranda');
          } catch (err) {}
        } else {
          alert('Akses Masuk Ditolak:\n\n' + (res.message || 'NISN atau Tanggal Lahir tidak cocok!'));
        }
      } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan jaringan saat mencoba masuk ke portal siswa.');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = origText;
        }
      }
    }

    function goToStudentLogin(pushHistory = true) {
      try {
        localStorage.removeItem(STORAGE_KEY_LOGIN);
        localStorage.removeItem(STORAGE_KEY_NISN);
        localStorage.removeItem(STORAGE_KEY_TAB);
        localStorage.removeItem(STORAGE_KEY_SUBJECT);
        localStorage.removeItem('smp_student_cache');
      } catch (err) {}

      showStudentScreen('login');

      if (pushHistory) {
        try {
          if (window.location.hash !== '#login') {
            history.pushState({ page: 'login' }, '', '#login');
          }
        } catch (err) {}
      }
    }

    function loadStudentDashboard(targetTab) {
      // Header info
      document.getElementById('header-student-name').innerText = activeStudent.name;
      document.getElementById('header-student-class').innerText = `${activeStudent.class} · NISN ${activeStudent.nisn}`;
      document.getElementById('header-student-avatar').innerText = activeStudent.avatar;

      // Welcome title & notes
      document.getElementById('student-welcome-title').innerText = `Halo, ${activeStudent.name}! 👋`;
      document.getElementById('student-personal-notes-box').innerText = `"${activeStudent.notes}"`;

      // Status Badge
      const statusBadge = document.getElementById('student-overall-status-badge');
      if (activeStudent.overallPassed) {
        statusBadge.className = 'px-2 py-0.5 rounded-full font-bold text-[11px] bg-emerald-400 text-indigo-950';
        statusBadge.innerText = 'Tuntas (Di Atas KKM)';
      } else {
        statusBadge.className = 'px-2 py-0.5 rounded-full font-bold text-[11px] bg-rose-400 text-indigo-950';
        statusBadge.innerText = 'Perlu Remedial';
      }

      // Stats
      document.getElementById('stat-my-average').innerText = activeStudent.average;
      const kkmNote = document.getElementById('stat-my-kkm-note');
      if (Number(activeStudent.average) >= 75) {
        kkmNote.className = 'text-[11px] text-emerald-600 mt-1';
        kkmNote.innerText = '✓ Di atas target KKM (75)';
      } else {
        kkmNote.className = 'text-[11px] text-rose-600 font-bold mt-1';
        kkmNote.innerText = '⚠️ Di bawah target KKM (75)';
      }

      document.getElementById('stat-my-attendance-pct').innerText = activeStudent.attendance.pct;
      document.getElementById('stat-my-attendance-note').innerText = `${activeStudent.attendance.hadir} Hadir · ${activeStudent.attendance.izin} Izin · ${activeStudent.attendance.sakit} Sakit · ${activeStudent.attendance.alpa} Alpa`;

      // Tab Kehadiran
      document.getElementById('student-attend-badge').innerText = `Predikat: ${activeStudent.attendance.predicate}`;
      document.getElementById('student-attend-badge').className = `text-xs font-bold px-2.5 py-0.5 rounded-full ${activeStudent.attendance.alpa > 3 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'}`;
      document.getElementById('student-attend-total-meetings').innerText = `${activeStudent.attendance.total} Kali`;
      document.getElementById('student-attend-pct-bar').innerText = activeStudent.attendance.pct;
      document.getElementById('student-attend-progress-fill').style.width = activeStudent.attendance.pct;
      document.getElementById('student-attend-progress-fill').className = `h-full rounded-full transition-all duration-500 ${Number(activeStudent.attendance.pct.replace('%', '')) >= 85 ? 'bg-emerald-500' : 'bg-rose-500'}`;

      document.getElementById('student-count-h').innerText = activeStudent.attendance.hadir;
      document.getElementById('student-count-i').innerText = activeStudent.attendance.izin;
      document.getElementById('student-count-s').innerText = activeStudent.attendance.sakit;
      document.getElementById('student-count-a').innerText = activeStudent.attendance.alpa;

      // Render Riwayat Pertemuan Harian Siswa
      const historyList = document.getElementById('student-daily-history-list');
      historyList.innerHTML = '';
      activeStudent.meetingsHistory.forEach(item => {
        let badgeColor = 'bg-emerald-100 text-emerald-800 border-emerald-300';
        let label = 'Hadir';
        if (item.status === 'I') { badgeColor = 'bg-amber-100 text-amber-800 border-amber-300'; label = 'Izin'; }
        if (item.status === 'S') { badgeColor = 'bg-purple-100 text-purple-800 border-purple-300'; label = 'Sakit'; }
        if (item.status === 'A') { badgeColor = 'bg-rose-100 text-rose-800 border-rose-300'; label = 'Alpa'; }

        historyList.innerHTML += `
          <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition-colors">
            <div class="flex items-center gap-3">
              <span class="w-8 h-8 rounded-xl font-bold flex items-center justify-center border text-xs ${badgeColor}">
                ${item.status}
              </span>
              <div>
                <p class="font-bold text-slate-800 text-xs">${item.name}</p>
                <p class="text-[11px] text-slate-400">📅 ${item.date}</p>
              </div>
            </div>
            <div class="text-right">
              <span class="text-xs font-semibold text-slate-700">${label}</span>
              <p class="text-[11px] text-slate-400">${item.note}</p>
            </div>
          </div>
        `;
      });

      // Render Nilai Siswa Sesuai Pilihan Mapel
      const subjectSelect = document.getElementById('select-student-subject');
      let savedSubj = null;
      try {
        savedSubj = localStorage.getItem(STORAGE_KEY_SUBJECT);
      } catch (err) {}
      if (savedSubj && activeStudent.subjects && activeStudent.subjects[savedSubj]) {
        selectedStudentSubject = savedSubj;
        if (subjectSelect) subjectSelect.value = savedSubj;
      } else if (subjectSelect) {
        selectedStudentSubject = subjectSelect.value || 'Matematika';
      }
      renderStudentGrades(selectedStudentSubject);

      showStudentScreen('dashboard');

      let tabToActivate = targetTab;
      if (!tabToActivate) {
        try {
          tabToActivate = localStorage.getItem(STORAGE_KEY_TAB) || 'beranda';
        } catch (err) {
          tabToActivate = 'beranda';
        }
      }
      switchStudentTab(tabToActivate, false);
      window.scrollTo(0, 0);
    }

    // HANDLER PILIHAN NILAI MAPEL OLEH SISWA
    function handleStudentSubjectChange(subject) {
      selectedStudentSubject = subject;
      try {
        localStorage.setItem(STORAGE_KEY_SUBJECT, subject);
      } catch (err) {}
      renderStudentGrades(subject);
    }

    function findSubjectData(subject) {
      if (!activeStudent || !activeStudent.subjects) return null;
      if (activeStudent.subjects[subject]) return activeStudent.subjects[subject];

      const norm = s => (s || '').toLowerCase().replace(/[^a-z0-9]/g, '');
      const tNorm = norm(subject);
      for (const [key, val] of Object.entries(activeStudent.subjects)) {
        const kNorm = norm(key);
        if (kNorm === tNorm || kNorm.includes(tNorm) || tNorm.includes(kNorm)) {
          return val;
        }
      }
      return null;
    }

    function renderStudentGrades(subject) {
      const subjectData = findSubjectData(subject) || {
        teacher: 'Guru Pengampu Mata Pelajaran',
        kkm: 75,
        average: '-',
        passed: true,
        grades: []
      };

      // Update table header and notes
      const titleElem = document.getElementById('student-grades-table-title');
      if (titleElem) titleElem.innerText = `Daftar Nilai Tugas & Evaluasi - ${subject}`;

      const teacherElem = document.getElementById('student-subject-teacher');
      if (teacherElem) teacherElem.innerText = `Guru Pengampu: ${subjectData.teacher}`;

      const kkmNote = document.getElementById('student-subject-kkm-note');
      if (kkmNote) kkmNote.innerText = subjectData.kkm;

      const gradesBody = document.getElementById('student-grades-table-body');
      gradesBody.innerHTML = '';

      if (!subjectData.grades || subjectData.grades.length === 0) {
        gradesBody.innerHTML = `
          <tr>
            <td colspan="6" class="py-8 text-center text-slate-400">
              <p class="font-semibold text-slate-500">Belum ada tugas atau ulangan yang dinilai untuk mata pelajaran ini.</p>
              <p class="text-[11px] text-slate-400 mt-1">Nilai akan otomatis muncul setelah guru pengampu menginput hasil penilaian.</p>
            </td>
          </tr>
        `;
      } else {
        subjectData.grades.forEach(g => {
          const isPassed = g.score >= g.kkm;
          gradesBody.innerHTML += `
            <tr class="hover:bg-slate-50 transition-colors">
              <td class="py-3 px-4 text-center text-slate-400 font-medium">${g.no}</td>
              <td class="py-3 px-4 font-bold text-slate-800">${g.title}</td>
              <td class="py-3 px-4 text-slate-500">📅 ${g.date}</td>
              <td class="py-3 px-4 text-center font-bold text-slate-500">${g.kkm}</td>
              <td class="py-3 px-4 text-center font-extrabold text-sm ${isPassed ? 'text-indigo-950' : 'text-rose-600'}">${g.score}</td>
              <td class="py-3 px-4 text-center">
                <span class="px-2.5 py-0.5 rounded-full font-bold text-[11px] ${isPassed ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">
                  ${isPassed ? 'Tuntas' : 'Perlu Remedial'}
                </span>
              </td>
            </tr>
          `;
        });
      }

      // Update Rata-rata Footer Mapel
      const scoreDisplay = document.getElementById('student-final-score-display');
      if (scoreDisplay) scoreDisplay.innerText = subjectData.average;

      const finalBadge = document.getElementById('student-final-status-badge');
      if (finalBadge) {
        if (subjectData.average !== '-' && subjectData.passed) {
          finalBadge.className = 'px-3 py-1.5 rounded-xl font-bold text-xs bg-emerald-600 text-white shadow-sm';
          finalBadge.innerText = 'Tuntas (Di Atas KKM)';
        } else if (subjectData.average !== '-') {
          finalBadge.className = 'px-3 py-1.5 rounded-xl font-bold text-xs bg-rose-600 text-white shadow-sm';
          finalBadge.innerText = 'Perlu Remedial';
        } else {
          finalBadge.className = 'px-3 py-1.5 rounded-xl font-bold text-xs bg-slate-400 text-white shadow-sm';
          finalBadge.innerText = 'Belum Ada Penilaian';
        }
      }
    }

    // TOGGLE MOBILE SIDEBAR
    function toggleStudentMobileSidebar() {
      const sb = document.getElementById('student-sidebar');
      if (sb) {
        sb.classList.toggle('mobile-open');
      }
    }

    document.addEventListener('click', event => {
      const sidebar = document.getElementById('student-sidebar');
      const menuToggle = document.getElementById('student-mobile-menu-toggle');
      if (
        window.innerWidth < 768 &&
        sidebar?.classList.contains('mobile-open') &&
        !sidebar.contains(event.target) &&
        !menuToggle?.contains(event.target)
      ) {
        sidebar.classList.remove('mobile-open');
      }
    });

    // TAB SWITCHER SISWA
    function switchStudentTab(tabId, pushHistory = true) {
      const allTabs = ['beranda', 'materi', 'kehadiran', 'nilai', 'faq'];
      if (!allTabs.includes(tabId)) tabId = 'beranda';
      currentStudentTab = tabId;

      allTabs.forEach(t => {
        const view = document.getElementById(`student-view-${t}`);
        if (view) {
          view.classList.add('hidden');
          view.style.display = 'none';
        }
        const btn = document.getElementById(`tab-btn-${t}`);
        if (btn) {
          btn.classList.remove('bg-indigo-950', 'text-white', 'shadow-sm', 'font-bold');
          btn.classList.add('text-slate-600', 'hover:bg-slate-100', 'hover:text-indigo-950');
          const icon = btn.querySelector('.nav-icon');
          if (icon) {
            icon.classList.remove('text-amber-300');
            icon.classList.add('text-slate-400');
          }
        }
      });

      const activeView = document.getElementById(`student-view-${tabId}`);
      if (activeView) {
        activeView.classList.remove('hidden');
        activeView.style.display = 'block';
      }
      const activeBtn = document.getElementById(`tab-btn-${tabId}`);
      if (activeBtn) {
        activeBtn.classList.add('bg-indigo-950', 'text-white', 'shadow-sm', 'font-bold');
        activeBtn.classList.remove('text-slate-600', 'hover:bg-slate-100', 'hover:text-indigo-950');
        const icon = activeBtn.querySelector('.nav-icon');
        if (icon) {
          icon.classList.remove('text-slate-400');
          icon.classList.add('text-amber-300');
        }
      }

      // Sembunyikan sidebar di mobile setelah item dipilih agar konten langsung terlihat
      if (window.innerWidth < 768) {
        const sb = document.getElementById('student-sidebar');
        if (sb) sb.classList.remove('mobile-open');
      }

      try {
        localStorage.setItem(STORAGE_KEY_TAB, tabId);
      } catch (e) {}

      if (pushHistory) {
        try {
          const currentCleanHash = window.location.hash.replace('#', '');
          if (currentCleanHash !== tabId) {
            history.pushState({ page: 'dashboard', tab: tabId }, '', '#' + tabId);
          }
        } catch (e) {}
      }
    }

    // SINKRONISASI TAMPILAN SESUAI HASH / HISTORY STATE (MENDUKUNG TOMBOL BACK & FORWARD BROWSER SERTA REFRESH)
    function handleStudentLocationSync() {
      const rawHash = (window.location.hash || '').replace('#', '').toLowerCase();

      // KONDISI 1: Pengguna mundur ke #login -> Tampilkan Section 1 (Layar Login)
      if (rawHash === 'login') {
        showStudentScreen('login');
        return;
      }

      let isLoggedIn = false;
      try {
        isLoggedIn = localStorage.getItem(STORAGE_KEY_LOGIN) === 'true';
      } catch (e) {}

      // KONDISI 2: Belum login dan tidak sedang di tab apapun -> Tampilkan Section 1
      if (!isLoggedIn) {
        showStudentScreen('login');
        return;
      }

      // KONDISI 3: Sesi login aktif -> Pastikan data siswa tersedia
      const savedNisn = localStorage.getItem(STORAGE_KEY_NISN) || '0098427135';

      if (!activeStudent) {
        try {
          const cached = localStorage.getItem('smp_student_cache');
          if (cached) {
            activeStudent = JSON.parse(cached);
          }
        } catch (err) {}
      }

      // Ambil data terbaru dari MySQL secara latar belakang
      fetchLatestStudentData(savedNisn, true);

      if (!activeStudent) {
        activeStudent = {
          id: 1,
          nisn: savedNisn,
          name: 'Siswa SMPN 7 Purwakarta',
          class: 'Kelas VIII A',
          avatar: 'SW',
          teacher: 'Wali Kelas VIII A',
          notes: 'Memuat data nilai dari database...',
          overallPassed: true,
          average: '-',
          attendance: { pct: '100%', hadir: 36, izin: 0, sakit: 0, alpa: 0, total: 36, predicate: 'A' },
          subjects: {},
          meetingsHistory: []
        };
      }

      const allTabs = ['beranda', 'materi', 'kehadiran', 'nilai', 'faq'];
      let targetTab = allTabs.includes(rawHash) ? rawHash : null;
      if (!targetTab) {
        try {
          targetTab = localStorage.getItem(STORAGE_KEY_TAB) || 'beranda';
        } catch (e) {
          targetTab = 'beranda';
        }
      }

      // Tampilkan Section 2 (Dashboard Siswa) beserta tab yang aktif
      loadStudentDashboard(targetTab);
      showStudentScreen('dashboard');
      switchStudentTab(targetTab, false);
    }

    // LISTENER EVENT BROWSER (POPSTATE & HASHCHANGE)
    window.addEventListener('popstate', handleStudentLocationSync);
    window.addEventListener('hashchange', handleStudentLocationSync);

    // INISIALISASI SAAT DOM SELESAI DIMUAT
    window.addEventListener('DOMContentLoaded', () => {
      const rawHash = (window.location.hash || '').replace('#', '').toLowerCase();
      let isLoggedIn = false;
      try {
        isLoggedIn = localStorage.getItem(STORAGE_KEY_LOGIN) === 'true';
      } catch (e) {}

      // Pastikan entry history awal terkonfigurasi dengan benar
      if (!isLoggedIn && !rawHash) {
        try {
          history.replaceState({ page: 'login' }, '', '#login');
        } catch (e) {}
      } else if (isLoggedIn && !rawHash) {
        const savedTab = localStorage.getItem(STORAGE_KEY_TAB) || 'beranda';
        try {
          history.replaceState({ page: 'dashboard', tab: savedTab }, '', '#' + savedTab);
        } catch (e) {}
      }

      handleStudentLocationSync();
    });
  </script>
</body>
</html>
