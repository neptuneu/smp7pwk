<?php
/**
 * ==============================================================
 * PORTAL SISWA
 * --------------------------------------------------------------
 * Hanya untuk sesi dengan role "siswa". Data nilai, presensi, dan
 * catatan wali kelas dibaca langsung dari database melalui api.php.
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php';

$user = require_login('siswa');
$studentName = $user['nama'] ?: $user['username'];
$csrf = csrf_token();
$flashSuccess = flash('success');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Siswa — <?= e(APP_SHORT_NAME) ?></title>
  <link rel="icon" type="image/png" href="logoSMP.png">
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    body { font-family: 'Plus Jakarta Sans', sans-serif; -webkit-tap-highlight-color: transparent; }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
  </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen">

  <!-- HEADER -->
  <header class="bg-indigo-950 text-white shadow-lg sticky top-0 z-30">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-11 h-11 bg-white rounded-2xl p-1.5 flex items-center justify-center shrink-0 shadow">
          <img src="logoSMP.png" alt="Logo sekolah" class="w-full h-full object-contain">
        </div>
        <div class="min-w-0">
          <p class="font-extrabold text-sm sm:text-base truncate"><?= e(APP_SHORT_NAME) ?></p>
          <p class="text-[10px] sm:text-[11px] text-amber-300 font-semibold">Portal Peserta Didik · T.A. <?= e(TAHUN_AJARAN) ?></p>
        </div>
      </div>
      <div class="flex items-center gap-2 sm:gap-3">
        <div class="text-right hidden sm:block">
          <p class="text-xs font-bold text-white" id="header-student-name"><?= e($studentName) ?></p>
          <p class="text-[10px] text-indigo-200" id="header-student-class">Memuat kelas...</p>
        </div>
        <div class="w-9 h-9 rounded-full bg-amber-400 text-indigo-950 font-black flex items-center justify-center text-xs shadow" id="header-student-initials">
          <?= e(strtoupper(mb_substr($studentName, 0, 2))) ?>
        </div>
        <a href="logout.php" onclick="return confirm('Keluar dari Portal Siswa?')" title="Keluar"
           class="p-2 rounded-xl bg-indigo-900 hover:bg-rose-600 border border-indigo-700 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 sm:px-6 py-5 sm:py-7 space-y-5">

    <?php if ($flashSuccess): ?>
      <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <!-- PROFIL & RINGKASAN -->
    <section class="bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-900 text-white rounded-3xl p-5 sm:p-7 shadow-lg relative overflow-hidden">
      <div class="absolute -top-20 -right-16 w-64 h-64 bg-amber-400/10 rounded-full blur-3xl"></div>
      <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
          <span class="text-[10px] bg-amber-400 text-indigo-950 font-black px-3 py-1 rounded-full uppercase tracking-wider">Rapor Digital</span>
          <h1 class="text-xl sm:text-2xl font-extrabold mt-3" id="welcome-name">Halo, <?= e($studentName) ?> 👋</h1>
          <p class="text-xs text-indigo-200 mt-1 max-w-xl">Pantau nilai, kehadiran, dan catatan wali kelas Anda. Data ditampilkan langsung dari database sekolah.</p>
          <div class="flex flex-wrap gap-2 mt-3 text-[11px]">
            <span class="px-2.5 py-1 rounded-lg bg-white/10 border border-white/15">NISN: <strong id="profile-nisn">-</strong></span>
            <span class="px-2.5 py-1 rounded-lg bg-white/10 border border-white/15">Kelas: <strong id="profile-class">-</strong></span>
            <span class="px-2.5 py-1 rounded-lg bg-white/10 border border-white/15">Wali: <strong id="profile-parent">-</strong></span>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-2.5 shrink-0 text-center">
          <div class="bg-white/10 border border-white/15 rounded-2xl p-3 min-w-[86px]">
            <p class="text-[10px] text-indigo-200">Rata-Rata</p>
            <p class="text-xl font-black text-amber-300 mt-0.5" id="stat-average">-</p>
          </div>
          <div class="bg-white/10 border border-white/15 rounded-2xl p-3 min-w-[86px]">
            <p class="text-[10px] text-indigo-200">Kehadiran</p>
            <p class="text-xl font-black text-emerald-300 mt-0.5" id="stat-attendance">-</p>
          </div>
          <div class="bg-white/10 border border-white/15 rounded-2xl p-3 min-w-[86px]">
            <p class="text-[10px] text-indigo-200">Mapel</p>
            <p class="text-xl font-black text-white mt-0.5" id="stat-subjects">0</p>
          </div>
        </div>
      </div>
    </section>

    <!-- LOADING / ERROR -->
    <div id="portal-loading" class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-slate-400 text-xs">
      Memuat data akademik dari database...
    </div>
    <div id="portal-error" class="hidden bg-rose-50 border border-rose-200 rounded-2xl p-4 text-rose-800 text-xs font-semibold"></div>

    <!-- CONTENT -->
    <div id="portal-content" class="hidden space-y-5">

      <!-- PRESENSI -->
      <section class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-extrabold text-indigo-950">Rekap Kehadiran</h2>
            <p class="text-[11px] text-slate-500">Akumulasi presensi dari seluruh mata pelajaran.</p>
          </div>
          <span class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200" id="attendance-pct-badge">0%</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 text-center">
          <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3">
            <p class="text-[10px] text-emerald-700 font-bold uppercase">Hadir</p>
            <p class="text-xl font-black text-emerald-700" id="att-hadir">0</p>
          </div>
          <div class="bg-amber-50 border border-amber-100 rounded-xl p-3">
            <p class="text-[10px] text-amber-700 font-bold uppercase">Izin</p>
            <p class="text-xl font-black text-amber-700" id="att-izin">0</p>
          </div>
          <div class="bg-purple-50 border border-purple-100 rounded-xl p-3">
            <p class="text-[10px] text-purple-700 font-bold uppercase">Sakit</p>
            <p class="text-xl font-black text-purple-700" id="att-sakit">0</p>
          </div>
          <div class="bg-rose-50 border border-rose-100 rounded-xl p-3">
            <p class="text-[10px] text-rose-700 font-bold uppercase">Alpa</p>
            <p class="text-xl font-black text-rose-700" id="att-alpa">0</p>
          </div>
        </div>
        <p class="text-[11px] text-slate-500 mt-3">Total pertemuan tercatat: <strong id="att-total">0</strong></p>
      </section>

      <!-- NILAI PER MAPEL -->
      <section class="space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-extrabold text-indigo-950">Nilai per Mata Pelajaran</h2>
            <p class="text-[11px] text-slate-500">Nilai diinput oleh guru pengampu masing-masing.</p>
          </div>
        </div>
        <div id="subjects-container" class="space-y-3"></div>
      </section>

      <!-- MATERI & MODUL DARI GURU -->
      <section class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-extrabold text-indigo-950">Materi &amp; Modul dari Guru</h2>
            <p class="text-[11px] text-slate-500">Bahan ajar yang diterbitkan guru untuk kelas Anda.</p>
          </div>
          <select id="modules-filter" class="bg-slate-100 border border-slate-300 rounded-lg px-2.5 py-1 text-xs text-slate-800 font-bold">
            <option value="ALL">Semua Mata Pelajaran</option>
          </select>
        </div>
        <div id="modules-container" class="mt-4 space-y-2.5">
          <p class="text-center text-slate-400 text-xs py-4">Memuat materi...</p>
        </div>
      </section>

      <!-- CATATAN WALI -->
      <section class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
        <h2 class="text-sm font-extrabold text-indigo-950">Catatan Wali Kelas</h2>
        <p class="text-[11px] text-slate-500 mt-0.5">Evaluasi dan bimbingan dari wali kelas Anda.</p>
        <p id="student-note" class="mt-3 text-xs text-slate-700 leading-relaxed bg-slate-50 border border-slate-200 rounded-xl p-3.5">Belum ada catatan.</p>
      </section>

    </div>

    <p class="text-center text-[10px] text-slate-400 py-3">Data bersifat pribadi dan rahasia. &copy; <?= date('Y') ?> <?= e(SCHOOL_NAME) ?></p>
  </main>

  <script>
    window.STUDENT_CONFIG = {
      csrf: <?= json_encode($csrf) ?>,
      apiUrl: 'api.php',
      studentName: <?= json_encode($studentName) ?>
    };
  </script>
  <script src="assets/murid-app.js?v=<?= (int) @filemtime(__DIR__ . '/assets/murid-app.js') ?>"></script>
</body>
</html>