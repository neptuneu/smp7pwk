<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback Nama Admin
if (!isset($adminName)) {
    $adminName = isset($_SESSION['user']['nama_lengkap']) 
        ? $_SESSION['user']['nama_lengkap'] 
        : (isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'Administrator');
}

// Fallback Inisial Admin (Ambil huruf depan)
if (!isset($adminInitials)) {
    $words = explode(' ', trim($adminName));
    $adminInitials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
    if (empty($adminInitials)) {
        $adminInitials = 'A';
    }
}

// Fallback helper function e() jika belum didefinisikan
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - SMPN 7 Purwakarta (Manajemen Sekolah & Akademik)</title>
  <link rel="icon" type="image/png" href="logoSMP.png">
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <script>
    if (window.tailwind) {
      tailwind.config = {
        theme: {
          extend: {
            screens: {
              'xs': '480px',
            }
          }
        }
      };
    }
  </script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      -webkit-tap-highlight-color: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: #f1f5f9;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 9999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
    /* Smooth touch scrolling */
    .overflow-x-auto, .overflow-y-auto {
      -webkit-overflow-scrolling: touch;
    }
  </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex relative selection:bg-indigo-900 selection:text-white">

  <!-- ============================================================== -->
  <!-- MOBILE SIDEBAR BACKDROP OVERLAY -->
  <!-- ============================================================== -->
  <div id="sidebar-backdrop" onclick="closeMobileSidebar()" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs z-40 hidden md:hidden transition-opacity duration-300"></div>

  <!-- ============================================================== -->
  <!-- SIDEBAR ADMIN UTAMA -->
  <!-- ============================================================== -->
  <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] bg-indigo-950 text-white flex-shrink-0 flex flex-col justify-between h-full border-r border-indigo-900 shadow-2xl md:shadow-none transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0 md:static md:w-64 md:h-screen md:sticky md:top-0">
    <div class="flex flex-col h-full min-h-0">
      <!-- School Admin Brand Header -->
      <div class="p-4 sm:p-5 border-b border-indigo-900/80 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-2xl bg-white p-1 flex items-center justify-center shadow-lg shadow-indigo-950/40 shrink-0 overflow-hidden border border-indigo-800/80">
            <img src="logoSMP.png" alt="Logo SMPN 7 Purwakarta" class="w-full h-full object-contain">
          </div>
          <div class="min-w-0">
            <h1 class="font-extrabold text-sm tracking-tight text-white truncate">SMPN 7 PURWAKARTA</h1>
            <p class="text-[10px] text-amber-300 font-semibold uppercase tracking-wider truncate">PANEL ADMINISTRATOR</p>
          </div>
        </div>

        <!-- Mobile Drawer Close Button -->
        <button onclick="closeMobileSidebar()" class="md:hidden text-indigo-300 hover:text-white p-2 rounded-xl hover:bg-indigo-900 transition-colors shrink-0" aria-label="Tutup Menu">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Navigation Menus (Scrollable for mobile / small screen) -->
      <div class="px-3 py-4 overflow-y-auto custom-scrollbar flex-1">
        <p class="px-3 text-[10px] font-bold text-indigo-300 uppercase tracking-wider mb-2">Menu Utama</p>
        <nav class="space-y-1 text-xs font-semibold">
          
          <button onclick="switchAdminTab('overview')" id="nav-btn-overview" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl bg-indigo-900 text-white shadow-sm transition-all text-left">
            <svg class="w-4 h-4 text-amber-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Ringkasan & Dasbor</span>
          </button>

          <button onclick="switchAdminTab('approval')" id="nav-btn-approval" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span class="truncate">Persetujuan Akun</span>
            </div>
            <span class="bg-amber-400 text-indigo-950 font-black text-[10px] px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-approval">0</span>
          </button>

          <button onclick="switchAdminTab('guru')" id="nav-btn-guru" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              <span class="truncate">Data Guru & Staff</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-amber-300 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-guru">0</span>
          </button>

          <button onclick="switchAdminTab('siswa')" id="nav-btn-siswa" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
              <span class="truncate">Data Siswa (Induk)</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-indigo-200 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-siswa">0</span>
          </button>

          <button onclick="switchAdminTab('mapel')" id="nav-btn-mapel" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              <span class="truncate">Mata Pelajaran & KKM</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-indigo-200 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-mapel">0</span>
          </button>

          <button onclick="switchAdminTab('rombel')" id="nav-btn-rombel" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
              <span class="truncate">Kelas & Rombel</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-indigo-200 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-rombel">0 Rombel</span>
          </button>

          <button onclick="switchAdminTab('monitoring-guru')" id="nav-btn-monitoring-guru" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
              <span class="truncate">Pantau Input Guru</span>
            </div>
            <span class="bg-emerald-900/80 text-[10px] text-emerald-300 font-bold px-2 py-0.5 rounded-full border border-emerald-500/30 shrink-0">Live</span>
          </button>

          <p class="px-3 text-[10px] font-bold text-indigo-300 uppercase tracking-wider mb-2 pt-4">Arsip & Rekam Jejak</p>

          <button onclick="switchAdminTab('arsip-siswa')" id="nav-btn-arsip-siswa" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-amber-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
              <span class="truncate">Arsip Siswa (Lulus/Keluar)</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-amber-300 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-arsip-siswa">0</span>
          </button>

          <button onclick="switchAdminTab('arsip-guru')" id="nav-btn-arsip-guru" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-amber-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
              <span class="truncate">Arsip Guru & Staff</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-amber-300 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-arsip-guru">0</span>
          </button>

          <p class="px-3 text-[10px] font-bold text-indigo-300 uppercase tracking-wider mb-2 pt-4">Sistem & Pengaturan</p>

          <button onclick="switchAdminTab('settings')" id="nav-btn-settings" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="truncate">Pengaturan Sekolah</span>
          </button>
        </nav>
      </div>
    </div>

    <!-- Admin Profile in Sidebar Bottom -->
    <div class="p-4 border-t border-indigo-900/80 bg-indigo-950/70 shrink-0">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-9 h-9 rounded-full bg-amber-400 text-indigo-950 font-black flex items-center justify-center text-xs shadow-sm shrink-0">
            <?= e($adminInitials) ?>
          </div>
          <div class="min-w-0">
            <p class="text-xs font-bold text-white leading-tight truncate"><?= e($adminName) ?></p>
            <p class="text-[10px] text-indigo-300 truncate">Administrator</p>
          </div>
        </div>
        <button onclick="logoutAdmin()" title="Keluar Akun" class="text-indigo-400 hover:text-rose-400 p-2 rounded-lg hover:bg-indigo-900/60 transition-colors shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </button>
      </div>
    </div>
  </aside>

  <!-- ============================================================== -->
  <!-- MAIN CONTENT AREA -->
  <!-- ============================================================== -->
  <div class="flex-1 flex flex-col min-w-0 min-h-screen">
    
    <!-- Top Header Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 px-3 sm:px-6 py-2.5 sm:py-3 flex items-center justify-between gap-2 sm:gap-4 shadow-xs">
      <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <!-- Mobile Sidebar Toggle -->
        <button onclick="toggleMobileSidebar()" class="md:hidden p-2 rounded-xl text-slate-700 bg-slate-100 hover:bg-slate-200 active:scale-95 transition-all shrink-0" aria-label="Buka Navigasi">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
          <img src="logoSMP.png" alt="Logo SMPN 7 Purwakarta" class="w-8 h-8 object-contain rounded-xl md:hidden shrink-0">
          <div class="min-w-0">
            <h2 class="text-sm sm:text-lg font-extrabold text-slate-800 truncate" id="topbar-title">Ringkasan Sistem Akademik</h2>
            <p class="text-[11px] sm:text-xs text-slate-500 hidden sm:block truncate">Tahun Ajaran 2026/2027 · Semester Ganjil (Kurikulum Merdeka)</p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        <!-- Badge Info Semester -->
        <div class="hidden lg:flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-1.5 rounded-xl text-xs font-semibold">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Semester Ganjil Aktif</span>
        </div>

        <!-- Quick Switcher to Teacher / Student Portals -->
        <div class="relative" id="portal-switcher-container">
          <button onclick="togglePortalSwitcher(event)" id="portal-switcher-btn" class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-all border border-slate-200">
            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            <span class="hidden xs:inline">Buka Portal</span>
            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div id="portal-switcher-menu" class="absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 hidden z-50 text-xs">
            <a href="guru.php" target="_blank" class="flex items-center justify-between px-3.5 py-2.5 text-slate-700 hover:bg-emerald-50 hover:text-emerald-900 font-semibold transition-colors">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Buka Portal Guru</span>
              </div>
              <span class="text-[10px] text-slate-400">&rarr;</span>
            </a>
            <a href="murid.php" target="_blank" class="flex items-center justify-between px-3.5 py-2.5 text-slate-700 hover:bg-indigo-50 hover:text-indigo-900 font-semibold transition-colors border-t border-slate-100">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span>Buka Portal Siswa</span>
              </div>
              <span class="text-[10px] text-slate-400">&rarr;</span>
            </a>
          </div>
        </div>

        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-indigo-50 text-indigo-900 flex items-center justify-center font-bold text-xs border border-indigo-100 shadow-2xs">
          TU
        </div>
      </div>
    </header>

    <!-- CONTENT BODY CONTAINER -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">

      <!-- ============================================================== -->
      <!-- TAB 1: OVERVIEW & RINGKASAN AKADEMIK -->
      <!-- ============================================================== -->
      <section id="tab-overview" class="space-y-6">
        
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
          <div class="relative z-10 flex items-start gap-4">
            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white p-1.5 flex items-center justify-center shrink-0 shadow-md hidden xs:flex">
              <img src="logoSMP.png" alt="Logo SMPN 7 Purwakarta" class="w-full h-full object-contain">
            </div>
            <div>
              <span class="text-[11px] bg-amber-400 text-indigo-950 font-black px-3 py-1 rounded-full uppercase tracking-wider">Pusat Kendali Sekolah</span>
              <h3 class="text-2xl sm:text-3xl font-extrabold text-white mt-3">Selamat Datang, Tim Administrator 👋</h3>
              <p class="text-xs sm:text-sm text-indigo-200 mt-1 max-w-2xl leading-relaxed">
                Mengelola data pendidik dan peserta didik, kurikulum dan mata pelajaran, serta pembagian wali kelas dan rombel untuk kelas 7 s/d 9.
              </p>
            </div>
          </div>
          <div class="relative z-10 flex flex-wrap gap-2">
            <a href="guru.php" target="_blank" class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-xl text-xs shadow-md transition-all flex items-center gap-1.5 active:scale-95">
              <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              <span>Akses Portal Guru</span>
            </a>
            <button onclick="switchAdminTab('guru'); openAddTeacherModal();" class="px-4 py-2.5 bg-amber-400 hover:bg-amber-300 text-indigo-950 font-bold rounded-xl text-xs shadow-md transition-all flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              <span>Tambah Guru</span>
            </button>
            <button onclick="switchAdminTab('siswa'); openAddStudentModal();" class="px-4 py-2.5 bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold rounded-xl text-xs transition-all flex items-center gap-1.5">
              <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
              <span>Tambah Siswa</span>
            </button>
          </div>
        </div>

        <!-- Banner Peringatan Antrean Persetujuan Akun Baru -->
        <div id="overview-pending-alert" class="hidden p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200/90 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xs">
          <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-amber-400 text-indigo-950 flex items-center justify-center font-black shrink-0 shadow-xs">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
              <p class="font-extrabold text-sm text-indigo-950">Permintaan Pendaftaran Akun Menunggu Persetujuan</p>
              <p class="text-xs text-amber-900 mt-0.5" id="overview-pending-text">Terdapat calon pengguna baru yang mendaftar dan menunggu verifikasi Anda.</p>
            </div>
          </div>
          <button onclick="switchAdminTab('approval')" class="px-4 py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs transition-all shrink-0 flex items-center gap-2 shadow-sm">
            <span>Tinjau &amp; Setujui Akun</span>
            <span>&rarr;</span>
          </button>
        </div>

        <!-- 5 Metric Cards (seluruh angka dihitung dari database) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 text-xs">

          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Total Siswa Aktif</p>
              <h4 class="text-2xl font-black text-slate-800 mt-1" id="stat-total-siswa">0</h4>
              <p class="text-[11px] text-emerald-600 font-semibold mt-1" id="stat-siswa-sub">Memuat data...</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-slate-500 font-medium">Guru &amp; Tenaga Pendidik</p>
                <h4 class="text-2xl font-black text-slate-800 mt-1" id="stat-total-guru">0</h4>
                <p class="text-[11px] text-indigo-600 font-semibold mt-1" id="stat-guru-sub">Memuat data...</p>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px]">
              <button onclick="switchAdminTab('monitoring-guru')" class="text-slate-500 hover:text-indigo-600 font-semibold flex items-center gap-0.5">
                Pantau Nilai &rarr;
              </button>
              <a href="guru.php" class="text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-0.5">
                Portal Guru &nearr;
              </a>
            </div>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Mata Pelajaran</p>
              <h4 class="text-2xl font-black text-slate-800 mt-1" id="stat-total-mapel">0</h4>
              <p class="text-[11px] text-amber-600 font-semibold mt-1" id="stat-mapel-sub">Memuat data...</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Rombongan Belajar</p>
              <h4 class="text-2xl font-black text-emerald-600 mt-1" id="stat-total-rombel">0</h4>
              <p class="text-[11px] text-slate-500 font-semibold mt-1" id="stat-rombel-sub">Memuat data...</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Rata-Rata Nilai Sekolah</p>
              <h4 class="text-2xl font-black text-indigo-950 mt-1" id="stat-rata-nilai">0.0</h4>
              <p class="text-[11px] text-indigo-600 font-semibold mt-1" id="stat-nilai-sub">Memuat data...</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-950 text-amber-300 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
          </div>

        </div>
        <!-- Banner Akses Langsung Portal Guru & Monitoring -->
        <div class="bg-gradient-to-r from-emerald-950 via-indigo-950 to-slate-900 border border-emerald-500/30 rounded-2xl p-4 sm:p-5 text-white shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-center gap-3.5 min-w-0">
            <div class="w-11 h-11 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center shrink-0 shadow-inner">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h4 class="font-bold text-sm text-white truncate">Portal Pendidik & Tenaga Kependidikan</h4>
                <span class="bg-emerald-500/20 text-emerald-300 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-500/40 shrink-0">Live Terhubung</span>
              </div>
              <p class="text-[11px] text-slate-300 mt-0.5 truncate sm:whitespace-normal">Buka antarmuka guru untuk melihat lembar input nilai sumatif/formatif, jurnal mengajar, dan absensi rombel.</p>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button onclick="switchAdminTab('monitoring-guru')" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-indigo-200 hover:text-white rounded-xl text-xs font-semibold border border-white/15 transition-all">
              Pantau Input Admin
            </button>
            <a href="guru.php" target="_blank" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-xl text-xs shadow-md shadow-emerald-500/20 transition-all flex items-center gap-1.5 active:scale-95">
              <span>Buka Portal Guru</span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
          </div>
        </div>

        <!-- Distribusi Siswa Tiap Jenjang & Log Aktivitas -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 text-xs">
          
          <!-- Kolom Kiri: Distribusi Siswa (7, 8, 9) -->
          <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="font-bold text-slate-800 text-sm">Distribusi Peserta Didik per Jenjang</h4>
                <p class="text-slate-500 text-[11px]" id="dist-summary">Memuat distribusi peserta didik...</p>
              </div>
              <button onclick="switchAdminTab('rombel')" class="text-indigo-600 hover:text-indigo-800 font-bold text-[11px]">
                Kelola Rombel &rarr;
              </button>
            </div>

            <div class="space-y-3.5 pt-2">
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 flex items-center justify-between">
                <div>
                  <p class="font-bold text-slate-800 text-xs">Jenjang Kelas VII (Rombel VII A - VII I)</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">9 Kelas · Kapasitas 32 siswa/kelas</p>
                </div>
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 font-bold rounded-lg text-xs"><span id="dist-7">0</span> Siswa Aktif</span>
              </div>

              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 flex items-center justify-between">
                <div>
                  <p class="font-bold text-slate-800 text-xs">Jenjang Kelas VIII (Rombel VIII A - VIII I)</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">9 Kelas · Kapasitas 32 siswa/kelas</p>
                </div>
                <span class="px-3 py-1 bg-indigo-100 text-indigo-800 font-bold rounded-lg text-xs"><span id="dist-8">0</span> Siswa Aktif</span>
              </div>

              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 flex items-center justify-between">
                <div>
                  <p class="font-bold text-slate-800 text-xs">Jenjang Kelas IX (Rombel IX A - IX I)</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">9 Kelas · Kapasitas 32 siswa/kelas</p>
                </div>
                <span class="px-3 py-1 bg-amber-100 text-amber-900 font-bold rounded-lg text-xs"><span id="dist-9">0</span> Siswa Aktif</span>
              </div>
            </div>

            <!-- School Info Box -->
            <div class="bg-indigo-50/70 border border-indigo-200/80 rounded-xl p-3.5 flex items-start gap-3 mt-4">
              <svg class="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <div class="text-[11px] leading-relaxed text-indigo-950">
                <span class="font-bold">Periode Akademik Aktif:</span> Semester Ganjil T.A 2026/2027. Konfigurasi data induk pendidik dan peserta didik dapat dikelola secara mandiri melalui menu navigasi di sebelah kiri.
              </div>
            </div>
          </div>

          <!-- Kolom Kanan: Aktivitas Sistem Terkini -->
          <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="font-bold text-slate-800 text-sm">Aktivitas Terkini Guru</h4>
              <a href="guru.php" target="_blank" class="text-[11px] text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-1">
                <span>Portal Guru &rarr;</span>
              </a>
            </div>
            <div id="overview-activity-list" class="divide-y divide-slate-100">
              <div class="py-6 text-center text-slate-400 text-[11px]">Memuat aktivitas terbaru...</div>
            </div>          </div>

        </div>

      </section>

      <!-- ============================================================== -->
      <!-- TAB: PERSETUJUAN PENDAFTARAN AKUN (APPROVAL QUEUE) -->
      <!-- ============================================================== -->
      <section id="tab-approval" class="hidden space-y-4">
        
        <!-- Header Info -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300 uppercase tracking-wider">Antrean Verifikasi</span>
              <span class="text-xs text-slate-400 font-semibold" id="approval-total-count">0 Permintaan</span>
            </div>
            <h3 class="text-lg font-bold text-indigo-950">Persetujuan Registrasi Akun Baru</h3>
            <p class="text-xs text-slate-500 mt-0.5">Daftar calon pengguna (Guru &amp; Murid) yang telah mendaftar mandiri dan menunggu persetujuan administrator sebelum dapat login ke sistem.</p>
          </div>
          <button onclick="reloadPendingUsers()" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-all flex items-center gap-1.5 self-start sm:self-auto active:scale-95">
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <span>Segarkan Data</span>
          </button>
        </div>

        <!-- Tabel Antrean Akun -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                  <th class="py-3.5 px-4 w-12 text-center">No</th>
                  <th class="py-3.5 px-4 min-w-[160px]">Username Akun</th>
                  <th class="py-3.5 px-4 w-36 text-center">Peran yang Diminta</th>
                  <th class="py-3.5 px-4 w-44">Waktu Pendaftaran</th>
                  <th class="py-3.5 px-4 w-36 text-center">Status Akun</th>
                  <th class="py-3.5 px-4 min-w-[200px] text-center">Tindakan Admin</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100" id="approval-table-body">
                <!-- Javascript will inject pending rows -->
              </tbody>
            </table>
          </div>

          <!-- Empty State -->
          <div id="approval-empty-state" class="hidden p-10 text-center">
            <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center text-2xl shadow-xs">
              ✓
            </div>
            <p class="font-extrabold text-sm text-slate-800">Tidak Ada Permintaan Akun Baru</p>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Semua akun guru dan siswa yang mendaftar telah diproses atau disetujui oleh Administrator.</p>
          </div>
        </div>

      </section>

      <!-- ============================================================== -->
      <!-- TAB 2: MANAJEMEN DATA GURU & STAFF -->
      <!-- ============================================================== -->
      <section id="tab-guru" class="hidden space-y-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold text-slate-800">Manajemen Guru & Tenaga Pendidik</h3>
              <p class="text-xs text-slate-500">Atur akun akses guru, mata pelajaran yang diampu, dan penugasan wali kelas.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button onclick="openUploadTeacherModal()" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <span>Upload Excel/CSV</span>
              </button>
              <button onclick="exportTeachersCSV()" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Download CSV</span>
              </button>
              <button onclick="openAddTeacherModal()" class="px-4 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition-all">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Guru Baru</span>
              </button>
            </div>
          </div>

          <!-- Filter & Search Toolbar -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5 pt-4 border-t border-slate-100 text-xs">
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Cari Nama / NIP</label>
              <input type="text" id="filter-guru-search" oninput="renderTeachersTable()" placeholder="Ketik nama atau NIP..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Filter Mata Pelajaran</label>
              <select id="filter-guru-mapel" onchange="renderTeachersTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium"><option value="ALL">Semua Mata Pelajaran</option></select>
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Status Wali Kelas</label>
              <select id="filter-guru-wali" onchange="renderTeachersTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Peran</option>
                <option value="WALI">Wali Kelas Saja</option>
                <option value="NON_WALI">Bukan Wali Kelas</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Teachers Data Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[700px] text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                  <th class="py-3.5 px-4 w-12 text-center">No</th>
                  <th class="py-3.5 px-4">Nama Lengkap & NIP</th>
                  <th class="py-3.5 px-4">Mata Pelajaran yang Diampu</th>
                  <th class="py-3.5 px-4">Tugas Tambahan</th>
                  <th class="py-3.5 px-4">Kontak / Email</th>
                  <th class="py-3.5 px-4 text-center">Status Akun</th>
                  <th class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="teacher-table-body" class="divide-y divide-slate-100">
                <!-- Injected via JavaScript -->
              </tbody>
            </table>
          </div>
          <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500 flex items-center justify-between">
            <span id="teacher-count-label">Menampilkan data guru</span>
            <span class="text-[11px] text-slate-400">Guru yang login di portal hanya dapat menginput mapel yang ditentukan di sini.</span>
          </div>
        </div>

      </section>




      <!-- ============================================================== -->
      <!-- TAB 3: MANAJEMEN DATA SISWA (BUKU INDUK) -->
      <!-- ============================================================== -->
      <section id="tab-siswa" class="hidden space-y-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold text-slate-800">Manajemen Siswa (Buku Induk Peserta Didik)</h3>
              <p class="text-xs text-slate-500">Kelola NISN, rombel penempatan kelas 7 s/d 9, data orang tua, dan status peserta didik.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button onclick="openImportModal()" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <span>Upload Excel/CSV</span>
              </button>
              <button onclick="exportStudentsCSV()" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Download CSV</span>
              </button>
              <button onclick="openAddStudentModal()" class="px-4 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition-all">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Siswa Baru</span>
              </button>
            </div>
          </div>

          <!-- Filter Bertingkat: Jenjang & Rombel -->
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mt-5 pt-4 border-t border-slate-100 text-xs">
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Cari Nama / NISN</label>
              <input type="text" id="filter-siswa-search" oninput="renderStudentsTable()" placeholder="Ketik nama atau NISN siswa..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Pilih Tingkat Jenjang</label>
              <select id="filter-siswa-jenjang" onchange="handleJenjangChange()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Jenjang (7, 8, 9)</option>
                <option value="7">Kelas 7</option>
                <option value="8">Kelas 8</option>
                <option value="9">Kelas 9</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Rombel Kelas</label>
              <select id="filter-siswa-rombel" onchange="renderStudentsTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium"><option value="ALL">Semua Rombel</option></select>
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Status Keaktifan</label>
              <select id="filter-siswa-status" onchange="renderStudentsTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Status</option>
                <option value="Aktif" selected>Aktif Belajar</option>
                <option value="Mutasi">Mutasi / Pindah</option>
                <option value="Lulus">Alumni / Lulus</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Sticky / Floating Bulk Action Toolbar -->
        <div id="bulk-student-toolbar" class="hidden bg-indigo-950 text-white p-4 rounded-2xl shadow-xl flex flex-wrap items-center justify-between gap-4 border border-indigo-800 transition-all">
          <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-xl bg-amber-400 text-indigo-950 font-black text-xs flex items-center justify-center shadow-xs" id="bulk-selected-count">0</span>
            <div>
              <p class="font-bold text-white text-xs">Siswa Dipilih untuk Tindakan Massal</p>
              <p class="text-[11px] text-indigo-200">Kelompokkan kelas rombel baru atau perbarui status keaktifan sekaligus tanpa satu per satu.</p>
            </div>
          </div>
          
          <div class="flex flex-wrap items-center gap-2.5 text-xs">
            <!-- Grouping 1: Pindah Rombel Massal -->
            <div class="flex items-center gap-1.5 bg-indigo-900/90 px-3 py-1.5 rounded-xl border border-indigo-700/80">
              <span class="text-[11px] font-semibold text-indigo-200">Pindah ke Rombel:</span>
              <select id="bulk-target-class" class="bg-white text-slate-800 font-bold text-xs rounded-lg px-2.5 py-1 focus:outline-none cursor-pointer"><option value="VIII A">Kelas VIII A</option></select>
              <button type="button" onclick="applyBulkClassMove()" class="bg-amber-400 hover:bg-amber-300 text-indigo-950 font-bold px-3 py-1 rounded-lg transition-all shadow-xs flex items-center gap-1 active:scale-95">
                <span>Terapkan Kelas</span>
              </button>
            </div>

            <!-- Grouping 2: Ubah Status Massal (Alumni / Aktif / Mutasi) -->
            <div class="flex items-center gap-1.5 bg-indigo-900/90 px-3 py-1.5 rounded-xl border border-indigo-700/80">
              <span class="text-[11px] font-semibold text-indigo-200">Set Status:</span>
              <select id="bulk-target-status" class="bg-white text-slate-800 font-bold text-xs rounded-lg px-2.5 py-1 focus:outline-none cursor-pointer">
                <option value="Lulus">Alumni / Lulus</option>
                <option value="Aktif">Aktif Belajar</option>
                <option value="Mutasi">Mutasi / Pindah</option>
              </select>
              <button type="button" onclick="applyBulkStatusChange()" class="bg-emerald-500 hover:bg-emerald-400 text-white font-bold px-3 py-1 rounded-lg transition-all shadow-xs flex items-center gap-1 active:scale-95">
                <span>Terapkan Status</span>
              </button>
            </div>

            <button type="button" onclick="clearAllStudentSelection()" class="text-xs text-indigo-300 hover:text-white px-2.5 py-1 rounded-lg border border-indigo-800 hover:border-slate-500 transition-colors">
              Batal Pilih
            </button>
          </div>
        </div>

        <!-- Student Master Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[760px] text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                  <th class="py-3.5 px-3 w-10 text-center">
                    <input type="checkbox" id="check-all-students" onchange="toggleSelectAllStudents(this.checked)" title="Pilih Semua Siswa yang Ditampilkan" class="w-4 h-4 rounded border-slate-300 text-indigo-950 focus:ring-indigo-600 cursor-pointer">
                  </th>
                  <th class="py-3.5 px-4 w-12 text-center">No</th>
                  <th class="py-3.5 px-4">NISN</th>
                  <th class="py-3.5 px-4">Nama Siswa</th>
                  <th class="py-3.5 px-4 text-center">L/P</th>
                  <th class="py-3.5 px-4">Rombel / Kelas</th>
                  <th class="py-3.5 px-4">Nama Wali / Orang Tua</th>
                  <th class="py-3.5 px-4">No. HP / WA Wali</th>
                  <th class="py-3.5 px-4 text-center">Status</th>
                  <th class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="student-table-body" class="divide-y divide-slate-100">
                <!-- Injected via JavaScript -->
              </tbody>
            </table>
          </div>
          <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500 flex items-center justify-between">
            <span id="student-count-label">Menampilkan data siswa</span>
            <span class="text-[11px] text-slate-400">Data rombel ini otomatis terhubung saat guru menginput nilai di kelas terkait.</span>
          </div>
        </div>

      </section>




      <!-- ============================================================== -->
      <!-- TAB 4: MATA PELAJARAN & STANDAR KKM -->
      <!-- ============================================================== -->
      <section id="tab-mapel" class="hidden space-y-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold text-slate-800">Kurikulum Mata Pelajaran & Standar KKM</h3>
              <p class="text-xs text-slate-500">Atur bobot KKM sekolah, kelompok mata pelajaran (A/B/Mulok), dan guru koordinator.</p>
            </div>
            <button onclick="openAddMapelModal()" class="px-4 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 shadow-sm transition-all">
              <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              <span>+ Tambah Mata Pelajaran</span>
            </button>
          </div>
        </div>

        <!-- Table Mapel -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[750px] text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                  <th class="py-3.5 px-4 w-14 text-center">Kode</th>
                  <th class="py-3.5 px-4">Nama Mata Pelajaran</th>
                  <th class="py-3.5 px-4">Kelompok Kurikulum</th>
                  <th class="py-3.5 px-4 text-center">Beban Jam / Minggu</th>
                  <th class="py-3.5 px-4 text-center">Standar KKM Sekolah</th>
                  <th class="py-3.5 px-4">Guru Koordinator</th>
                  <th class="py-3.5 px-4 text-center">Rombel Tercover</th>
                  <th class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="mapel-table-body" class="divide-y divide-slate-100">
                <!-- Injected via JavaScript -->
              </tbody>
            </table>
          </div>
          <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500 flex items-center justify-between">
            <span>Standar KKM ini menjadi patokan default saat guru mengampu kelas. Guru tetap dapat menyesuaikan target KKM di kelas masing-masing.</span>
          </div>
        </div>

      </section>




      <!-- ============================================================== -->
      <!-- TAB 5: KELAS & ROMBEL (27 KELAS: 7A-I, 8A-I, 9A-I) -->
      <!-- ============================================================== -->
      <section id="tab-rombel" class="hidden space-y-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-800 text-[11px] font-bold border border-indigo-200 mb-2">
                <span>🏫 <span id="rombel-total-label">0</span> Rombongan Belajar</span>
              </div>
              <h3 class="text-lg font-bold text-slate-800">Daftar Rombongan Belajar</h3>
              <p class="text-xs text-slate-500">Struktur rombel kelas VII, VIII, dan IX. <strong class="text-indigo-950">Klik pada kartu kelas</strong> untuk memantau seluruh mata pelajaran dan performa keseluruhan rombel.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button onclick="openAddRombelModal()" class="px-3 py-1.5 bg-indigo-950 hover:bg-indigo-900 text-white text-[11px] font-bold rounded-lg shadow-xs">
                + Rombel
              </button>
              <span class="text-xs font-bold text-slate-600">Filter Jenjang:</span>
              <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold">
                <button onclick="filterRombelCards('ALL')" id="btn-rf-all" class="px-3 py-1 rounded-lg bg-white shadow-xs text-indigo-950">Semua (<span id="rf-count-all">0</span>)</button>
                <button onclick="filterRombelCards('7')" id="btn-rf-7" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900">Kelas 7 (<span id="rf-count-7">0</span>)</button>
                <button onclick="filterRombelCards('8')" id="btn-rf-8" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900">Kelas 8 (<span id="rf-count-8">0</span>)</button>
                <button onclick="filterRombelCards('9')" id="btn-rf-9" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900">Kelas 9 (<span id="rf-count-9">0</span>)</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Grid Cards 27 Rombel (Clickable) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs" id="rombel-cards-grid">
          <!-- Injected via JavaScript -->
        </div>

      </section>



      <!-- ============================================================== -->
      <!-- TAB: PEMANTAUAN HASIL INPUT PORTAL GURU -->
      <!-- ============================================================== -->
      <section id="tab-monitoring-guru" class="hidden space-y-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-800 text-[11px] font-bold border border-emerald-200 mb-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Sinkronisasi Data Guru Real-Time</span>
              </div>
              <h3 class="text-lg font-bold text-slate-800">Pemantauan Hasil Input Portal Guru</h3>
              <p class="text-xs text-slate-500">Pantau progres pengisian nilai tugas/sumatif, jurnal mengajar, dan presensi siswa yang diinput oleh guru dari portal guru secara langsung.</p>
            </div>
            <div class="flex items-center gap-2">
              <button onclick="renderMonitoringGuruTable()" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Segarkan Data</span>
              </button>
            </div>
          </div>

          <!-- Toolbar filter -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5 pt-4 border-t border-slate-100 text-xs">
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Cari Guru / Mapel / Kelas</label>
              <input type="text" id="filter-mon-search" oninput="renderMonitoringGuruTable()" placeholder="Ketik nama guru, mapel, atau kelas..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Filter Tingkat Jenjang</label>
              <select id="filter-mon-grade" onchange="renderMonitoringGuruTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Jenjang (7, 8, 9)</option>
                <option value="7">Jenjang Kelas VII (7A - 7I)</option>
                <option value="8">Jenjang Kelas VIII (8A - 8I)</option>
                <option value="9">Jenjang Kelas IX (9A - 9I)</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Status Kesiapan Nilai</label>
              <select id="filter-mon-status" onchange="renderMonitoringGuruTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Status</option>
                <option value="Lengkap">Lengkap (Sumatif & Tugas Terisi)</option>
                <option value="Aktif Mengisi">Aktif Mengisi (Sedang Input)</option>
                <option value="Belum Input">Belum Ada Nilai Terinput</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Table monitoring input guru -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[850px] text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                  <th class="py-3.5 px-4 w-12 text-center">No</th>
                  <th class="py-3.5 px-4">Guru Pengampu</th>
                  <th class="py-3.5 px-4">Mata Pelajaran & Kelas</th>
                  <th class="py-3.5 px-4 text-center">Progres Nilai</th>
                  <th class="py-3.5 px-4 text-center">Rata-Rata Kelas</th>
                  <th class="py-3.5 px-4 text-center">Jurnal / Presensi</th>
                  <th class="py-3.5 px-4">Update Terakhir</th>
                  <th class="py-3.5 px-4 text-center">Status</th>
                  <th class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="monitoring-guru-table-body" class="divide-y divide-slate-100">
                <!-- Injected via JavaScript -->
              </tbody>
            </table>
          </div>
          <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500 flex items-center justify-between">
            <span id="mon-guru-count-label">Menampilkan data monitoring</span>
            <span class="text-[11px] text-slate-400">Admin dapat membuka lembar nilai guru secara langsung untuk verifikasi.</span>
          </div>
        </div>

      </section>



      <!-- ============================================================== -->
      <!-- TAB: ARSIP SISWA (LULUS, ALUMNI & MUTASI KELUAR) -->
      <!-- ============================================================== -->
      <section id="tab-arsip-siswa" class="hidden space-y-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 text-[11px] font-bold border border-amber-200 mb-2">
                <span>📁 Master Arsip & Rekam Jejak Siswa</span>
              </div>
              <h3 class="text-lg font-bold text-slate-800">Arsip Siswa (Alumni & Mutasi Keluar)</h3>
              <p class="text-xs text-slate-500">Pencatatan data peserta didik yang telah lulus (alumni), pindah sekolah (mutasi keluar), atau keluar lengkap dengan waktu dan keterangan.</p>
            </div>
            <div class="flex items-center gap-2">
              <button onclick="openModalUploadArsipSiswa()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all shadow-xs">
                <svg class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Upload Excel/CSV Arsip Siswa</span>
              </button>
              <button onclick="downloadArchivedStudentsCSV()" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Download Data Arsip (CSV)</span>
              </button>
            </div>
          </div>

          <!-- Filter Toolbar Arsip Siswa -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5 pt-4 border-t border-slate-100 text-xs">
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Cari Nama / NISN</label>
              <input type="text" id="filter-arsip-siswa-search" oninput="renderArchivedStudentsTable()" placeholder="Ketik nama atau NISN..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Kategori Arsip</label>
              <select id="filter-arsip-siswa-status" onchange="renderArchivedStudentsTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Kategori (Lulus & Keluar)</option>
                <option value="Lulus">Lulus / Alumni</option>
                <option value="Mutasi Keluar">Mutasi Keluar (Pindah Sekolah)</option>
                <option value="Mengundurkan Diri">Mengundurkan Diri / Drop Out</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Tahun Kelulusan / Keluar</label>
              <select id="filter-arsip-siswa-year" onchange="renderArchivedStudentsTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Tahun</option>
                <option value="2026">Tahun 2026</option>
                <option value="2025">Tahun 2025</option>
                <option value="2024">Tahun 2024</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Table Arsip Siswa -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[760px] text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                  <th class="py-3.5 px-4 w-12 text-center">No</th>
                  <th class="py-3.5 px-4">Nama Siswa & NISN</th>
                  <th class="py-3.5 px-4 text-center">Kelas Terakhir</th>
                  <th class="py-3.5 px-4 text-center">Status Arsip</th>
                  <th class="py-3.5 px-4">Waktu Lulus / Keluar</th>
                  <th class="py-3.5 px-4">Keterangan / Sekolah Lanjutan</th>
                  <th class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="arsip-siswa-table-body" class="divide-y divide-slate-100">
                <!-- Injected via JavaScript -->
              </tbody>
            </table>
          </div>
          <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500 flex items-center justify-between">
            <span id="arsip-siswa-count-label">Menampilkan data arsip siswa</span>
            <span class="text-[11px] text-slate-400">Siswa yang diarsipkan dapat dipulihkan kembali ke data siswa aktif jika diperlukan.</span>
          </div>
        </div>

      </section>



      <!-- ============================================================== -->
      <!-- TAB: ARSIP GURU & STAFF (PENSIUN, MUTASI & KELUAR) -->
      <!-- ============================================================== -->
      <section id="tab-arsip-guru" class="hidden space-y-4">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-800 text-[11px] font-bold border border-purple-200 mb-2">
                <span>📁 Master Arsip & Rekam Jejak Guru</span>
              </div>
              <h3 class="text-lg font-bold text-slate-800">Arsip Guru & Tenaga Kependidikan</h3>
              <p class="text-xs text-slate-500">Pencatatan data guru dan staf yang telah pensiun (purna tugas), mutasi ke dinas/sekolah lain, atau purna bakti beserta nomor SK resmi dan waktu.</p>
            </div>
            <div class="flex items-center gap-2">
              <button onclick="openModalUploadArsipGuru()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all shadow-xs">
                <svg class="w-4 h-4 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Upload Excel/CSV Arsip Guru</span>
              </button>
              <button onclick="downloadArchivedTeachersCSV()" class="px-3.5 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 font-bold rounded-xl text-xs flex items-center gap-1.5 transition-all">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>Download Data Arsip (CSV)</span>
              </button>
            </div>
          </div>

          <!-- Filter Toolbar Arsip Guru -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5 pt-4 border-t border-slate-100 text-xs">
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Cari Nama / NIP</label>
              <input type="text" id="filter-arsip-guru-search" oninput="renderArchivedTeachersTable()" placeholder="Ketik nama atau NIP..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Kategori Arsip</label>
              <select id="filter-arsip-guru-status" onchange="renderArchivedTeachersTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Kategori (Pensiun & Mutasi)</option>
                <option value="Pensiun">Pensiun / Purna Tugas</option>
                <option value="Mutasi Keluar">Mutasi ke Sekolah / Instansi Lain</option>
                <option value="Purna Bakti">Purna Bakti / Resign</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Tahun Pensiun / Mutasi</label>
              <select id="filter-arsip-guru-year" onchange="renderArchivedTeachersTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Tahun</option>
                <option value="2026">Tahun 2026</option>
                <option value="2025">Tahun 2025</option>
                <option value="2024">Tahun 2024</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Table Arsip Guru -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
          <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[760px] text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                  <th class="py-3.5 px-4 w-12 text-center">No</th>
                  <th class="py-3.5 px-4">Nama Lengkap & NIP</th>
                  <th class="py-3.5 px-4">Mata Pelajaran / Posisi Terakhir</th>
                  <th class="py-3.5 px-4 text-center">Status Arsip</th>
                  <th class="py-3.5 px-4">Waktu Pensiun / Keluar</th>
                  <th class="py-3.5 px-4">No. SK / Catatan Keputusan</th>
                  <th class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="arsip-guru-table-body" class="divide-y divide-slate-100">
                <!-- Injected via JavaScript -->
              </tbody>
            </table>
          </div>
          <div class="p-4 bg-slate-50 border-t border-slate-200 text-xs text-slate-500 flex items-center justify-between">
            <span id="arsip-guru-count-label">Menampilkan data arsip guru</span>
            <span class="text-[11px] text-slate-400">Guru/staf yang diarsipkan dapat dipulihkan kembali ke data pendidik aktif jika terjadi kekeliruan.</span>
          </div>
        </div>

      </section>



      <!-- ============================================================== -->
      <!-- TAB 6: PENGATURAN SEKOLAH (SETTINGS) -->
      <!-- ============================================================== -->
      <section id="tab-settings" class="hidden space-y-6">
        
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-6">
          <div class="border-b border-slate-100 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-bold text-slate-800">Identitas Satuan Pendidikan & Periode Akademik</h3>
              <p class="text-xs text-slate-500">Informasi resmi sekolah yang tertera pada kop dokumen, lembar nilai, dan identitas sistem.</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <div class="w-14 h-14 rounded-2xl bg-white border border-slate-200 p-1 flex items-center justify-center shadow-xs overflow-hidden">
                <img src="logoSMP.png" alt="Logo SMPN 7 Purwakarta" class="w-full h-full object-contain">
              </div>
              <div>
                <p class="font-bold text-slate-800 text-xs">Logo SMPN 7 Purwakarta</p>
                <p class="text-[10px] text-emerald-600 font-semibold">● Format PNG Aktif</p>
              </div>
            </div>
          </div>

          <form onsubmit="handleSaveSettings(event)" class="space-y-6 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Nama Satuan Pendidikan</label>
                <input type="text" id="set-school-name" value="SMP Negeri 7 Purwakarta" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-semibold focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">NPSN (Nomor Pokok Sekolah Nasional)</label>
                <input type="text" id="set-npsn" value="20205819" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              </div>
              <div class="md:col-span-2">
                <label class="block font-bold text-slate-700 mb-1">Alamat Lengkap Sekolah</label>
                <input type="text" id="set-address" value="Jl. Veteran No. 128, Ciseureuh, Kec. Purwakarta, Kab. Purwakarta, Jawa Barat 41118" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Nama Kepala Sekolah</label>
                <input type="text" id="set-headmaster" value="H. Ahmad Sanusi, M.Pd" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-semibold focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">NIP Kepala Sekolah</label>
                <input type="text" id="set-headmaster-nip" value="19680315 199303 1 004" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
              <h4 class="font-bold text-slate-800 text-sm mb-3">Tahun Ajaran & Kunci Semester</h4>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block font-bold text-slate-700 mb-1">Tahun Ajaran Aktif</label>
                  <select id="set-tahun-ajaran" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                    <option>2026/2027</option>
                    <option>2025/2026</option>
                  </select>
                </div>
                <div>
                  <label class="block font-bold text-slate-700 mb-1">Semester Aktif</label>
                  <select id="set-semester" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                    <option>Semester 1 (Ganjil)</option>
                    <option>Semester 2 (Genap)</option>
                  </select>
                </div>
                <div>
                  <label class="block font-bold text-slate-700 mb-1">Batas Akhir Input Nilai Guru</label>
                  <input type="date" id="set-deadline" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
              <button type="button" onclick="showToast('Batal', 'Perubahan dibatalkan')" class="px-4 py-2.5 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
                Reset
              </button>
              <button type="submit" class="px-5 py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Simpan Pengaturan Sekolah</span>
              </button>
            </div>
          </form>
        </div>

      </section>



    </main>

  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH / EDIT GURU -->
  <!-- ============================================================== -->
  <div id="modal-guru" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-800 text-base" id="modal-guru-title">Tambah Guru Baru</h3>
          <p class="text-slate-500 text-[11px]">Masukkan data pengajar dan alokasi mata pelajarannya.</p>
        </div>
        <button onclick="closeModalGuru()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleSaveTeacher(event)" class="mt-4 space-y-3.5">
        <input type="hidden" id="input-guru-id" value="">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Nama Lengkap & Gelar</label>
          <input type="text" id="input-guru-name" required placeholder="Contoh: Drs. Bambang Irawan, M.Pd" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">NIP / Nomor Pegawai (Untuk Login)</label>
          <input type="text" id="input-guru-nip" required placeholder="Contoh: 19820514 200801 1 009" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Mata Pelajaran</label>
            <input type="text" id="input-guru-mapel" required placeholder="Contoh: IPA atau Bahasa Sunda/B.Indonesia" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Tugas / Wali Kelas</label>
            <input type="text" id="input-guru-wali" placeholder="Contoh: Wali Kelas VIII.A atau Bukan Wali Kelas" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Kelas yang Diampu</label>
            <input type="text" id="input-guru-kelas" placeholder="Contoh: 7G-7I, 8A-8D" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Jam Mengajar / Beban</label>
            <input type="text" id="input-guru-jam" placeholder="Contoh: 4 atau 2/6" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">No. WhatsApp / HP</label>
            <input type="text" id="input-guru-phone" placeholder="08123456789" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Status Akun</label>
            <select id="input-guru-status" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
              <option value="Aktif">Aktif</option>
              <option value="Cuti">Cuti</option>
              <option value="Non-Aktif">Non-Aktif</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Kata Sandi Guru (Login)</label>
          <input type="password" id="input-guru-pass" placeholder="Kosongkan bila tidak diubah" autocomplete="new-password" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          <p class="text-[10px] text-slate-500 mt-1">Kosongkan saat menambah guru untuk memakai kata sandi default, atau isi minimal 8 karakter. Kata sandi disimpan sebagai hash.</p>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
          <button type="button" onclick="closeModalGuru()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
            Batal
          </button>
          <button type="submit" id="btn-save-teacher" class="px-5 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-sm flex items-center gap-1.5">
            <span>Simpan ke Database</span>
          </button>
        </div>
      </form>
    </div>
  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH / EDIT SISWA -->
  <!-- ============================================================== -->
  <div id="modal-siswa" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-800 text-base" id="modal-siswa-title">Tambah Siswa Baru</h3>
          <p class="text-slate-500 text-[11px]" id="modal-siswa-desc">Masukkan data peserta didik ke buku induk sekolah.</p>
        </div>
        <button onclick="closeModalSiswa()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleSaveStudent(event)" class="mt-4 space-y-3.5">
        <input type="hidden" id="input-siswa-id" value="">

        <div>
          <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Siswa</label>
          <input type="text" id="input-siswa-name" required placeholder="Contoh: Muhammad Farhan" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">NISN (10 Digit Angka)</label>
            <input type="text" id="input-siswa-nisn" required maxlength="10" placeholder="0098427999" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Jenis Kelamin</label>
            <select id="input-siswa-gender" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
              <option value="L">Laki-laki (L)</option>
              <option value="P">Perempuan (P)</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Penempatan Rombel / Kelas</label>
            <select id="input-siswa-class" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium"><option value="VIII A">VIII A</option></select>
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Status Keaktifan</label>
            <select id="input-siswa-status" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
              <option value="Aktif" selected>Aktif Belajar</option>
              <option value="Lulus">Alumni / Lulus</option>
              <option value="Mutasi">Mutasi / Pindah</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Nama Orang Tua / Wali</label>
            <input type="text" id="input-siswa-parent" placeholder="Contoh: Hendra Wijaya" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">No. WhatsApp Wali</label>
            <input type="text" id="input-siswa-phone" placeholder="0821xxxx" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
          <button type="button" onclick="closeModalSiswa()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
            Batal
          </button>
          <button type="submit" class="px-5 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-sm flex items-center gap-1.5">
            <span id="modal-siswa-submit-text">Daftarkan Siswa</span>
          </button>
        </div>
      </form>
    </div>
  </div>


  <!-- ============================================================== -->
  <!-- MODAL: TAMBAH / EDIT MATA PELAJARAN MANUAL -->
  <!-- ============================================================== -->
  <div id="modal-mapel" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-800 text-base" id="modal-mapel-title">Tambah Mata Pelajaran Baru</h3>
          <p class="text-slate-500 text-[11px]" id="modal-mapel-desc">Isi rincian kurikulum mapel secara manual oleh Administrator.</p>
        </div>
        <button onclick="closeModalMapel()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleSaveMapel(event)" class="mt-4 space-y-3.5">
        <input type="hidden" id="input-mapel-orig-code" value="">

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Kode Mapel</label>
            <input type="text" id="input-mapel-code" required placeholder="MP-12" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-mono font-bold focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div class="sm:col-span-2">
            <label class="block font-bold text-slate-700 mb-1">Nama Mata Pelajaran</label>
            <input type="text" id="input-mapel-name" required placeholder="Contoh: Bimbingan & Konseling (BK)" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Kelompok Kurikulum</label>
            <select id="input-mapel-group" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
              <option value="Kelompok A (Umum)">Kelompok A (Umum)</option>
              <option value="Kelompok B (Pengembangan)" selected>Kelompok B (Pengembangan)</option>
              <option value="Kelompok C (Muatan Lokal)">Kelompok C (Muatan Lokal)</option>
              <option value="Program Khusus">Program Khusus</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Guru Koordinator / Pengampu</label>
            <input type="text" id="input-mapel-lead" required placeholder="Contoh: Nama Guru, S.Pd" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Beban Jam (JP / Minggu)</label>
            <input type="number" id="input-mapel-hours" min="1" max="10" value="2" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Standar KKM Sekolah</label>
            <input type="number" id="input-mapel-kkm" min="50" max="100" value="75" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-bold focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Cakupan Rombel Tercover</label>
          <select id="input-mapel-rombel-count" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
            <option value="15" selected>15 Kelas (Semua Jenjang 7, 8, 9)</option>
            <option value="10">10 Kelas (Jenjang 7 & 8)</option>
            <option value="5">5 Kelas (Satu Jenjang Saja)</option>
          </select>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
          <button type="button" onclick="closeModalMapel()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
            Batal
          </button>
          <button type="submit" class="px-5 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-sm flex items-center gap-1.5">
            <span id="modal-mapel-submit-text">Simpan Mata Pelajaran</span>
          </button>
        </div>
      </form>
    </div>
  </div>


  <!-- ============================================================== -->
  <!-- MODAL: UPLOAD DATA SISWA MASSAL -->
  <!-- ============================================================== -->
  <div id="modal-import" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      
      <!-- Bungkus dengan form untuk import MySQL -->
      <form id="form-import-siswa" onsubmit="handleImportStudents(event)" enctype="multipart/form-data">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="font-bold text-slate-800 text-base">Upload Data Siswa Massal</h3>
            <p class="text-slate-500 text-[11px]">Unggah berkas spreadsheet .csv</p>
          </div>
          <button type="button" onclick="closeModalImport()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="mt-4 space-y-4">
          <div class="border-2 border-dashed border-indigo-200 bg-indigo-50/40 rounded-2xl p-6 text-center flex flex-col items-center justify-center">
            <svg class="w-10 h-10 text-indigo-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            <p class="font-bold text-indigo-950 mb-1">Pilih File CSV Siswa</p>
            <p class="text-slate-400 text-[11px] mb-3">Format kolom: NISN, Nama, JK, Kelas, Nama Wali, No HP, Status</p>
            
            <!-- Input File Asli -->
            <input type="file" name="csv_file_siswa" accept=".csv" required class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-100 file:text-indigo-900 hover:file:bg-indigo-200 cursor-pointer border border-slate-200 rounded-xl p-1 bg-white">
          </div>

          <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 text-[11px] text-slate-600 flex items-center justify-between">
            <span>Unduh Template Format (.csv)</span>
            <button type="button" onclick="downloadCSVTemplate()" class="font-bold text-indigo-600 hover:text-indigo-800">Download Template &darr;</button>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="closeModalImport()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">Tutup</button>
            <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-sm flex items-center gap-1.5">
              <span>Mulai Upload Data</span>
            </button>
          </div>
        </div>
      </form>

    </div>
  </div>
  <!-- ============================================================== -->
  <!-- MODAL: UPLOAD DATA GURU & STAFF MASSAL -->
  <!-- ============================================================== -->
  <div id="modal-upload-guru" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      
      <!-- BUNGKUS DENGAN FORM UNTUK MENGIRIM FILE KE PHP -->
      <form id="form-import-guru" onsubmit="handleImportTeachers(event)" enctype="multipart/form-data">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="font-bold text-slate-800 text-base">Upload Data Guru & Staff</h3>
            <p class="text-slate-500 text-[11px]">Unggah berkas spreadsheet .csv untuk upload data pendidik massal.</p>
          </div>
          <!-- UBAH TYPE JADI BUTTON AGAR TIDAK SUBMIT SAAT DIKLIK -->
          <button type="button" onclick="closeModalUploadGuru()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="mt-4 space-y-4">
          <div class="border-2 border-dashed border-indigo-200 bg-indigo-50/40 rounded-2xl p-6 text-center flex flex-col items-center justify-center">
            <svg class="w-10 h-10 text-indigo-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            <p class="font-bold text-indigo-950">Pilih Berkas Excel / CSV Guru & Staff</p>
            <p class="text-slate-400 text-[11px] mt-1 mb-4">Format kolom: NIP, Nama Lengkap, Mapel, Tugas Tambahan/Wali, No HP, Status</p>
            
            <!-- INPUT FILE UNTUK MENANGKAP CSV DARI KOMPUTER -->
            <input type="file" name="csv_file" accept=".csv" required class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-100 file:text-indigo-900 hover:file:bg-indigo-200 cursor-pointer border border-slate-200 rounded-xl p-1 bg-white">
          </div>

          <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 text-[11px] text-slate-600 flex items-center justify-between">
            <span>Unduh Template Format (.csv)</span>
            <button type="button" onclick="downloadTeacherCSVTemplate()" class="font-bold text-indigo-600 hover:text-indigo-800">Download Template &darr;</button>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="closeModalUploadGuru()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
              Batal
            </button>
            <!-- TOMBOL UPLOAD DIUBAH JADI TYPE SUBMIT -->
            <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-sm flex items-center gap-1.5">
              <span>Mulai Upload Data</span>
            </button>
          </div>
        </div>
      </form>
      <!-- AKHIR DARI FORM UPLOAD -->

    </div>
  </div>

  <!-- ============================================================== -->
  <!-- MODAL: DETAIL & PEMANTAUAN ROMBEL (KLIK KELAS) -->
  <!-- ============================================================== -->
  <div id="modal-detail-rombel" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 text-xs overflow-hidden">
      <!-- Header Modal -->
      <div class="p-6 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
        <div class="flex items-center gap-3.5">
          <span class="w-12 h-12 rounded-2xl bg-amber-400 text-indigo-950 font-black text-lg flex items-center justify-center shadow-md" id="detail-rombel-badge">
            8A
          </span>
          <div>
            <div class="flex items-center gap-2">
              <h3 class="font-extrabold text-white text-lg" id="detail-rombel-title">Pemantauan Kelas VIII A</h3>
              <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[10px] font-bold">Aktif Semester Ganjil</span>
            </div>
            <p class="text-indigo-200 text-xs mt-0.5" id="detail-rombel-sub">Memuat data rombel...</p>
          </div>
        </div>
        <button onclick="closeDetailRombelModal()" class="text-slate-400 hover:text-white p-2 rounded-xl hover:bg-white/10 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Tabs Bar inside Modal -->
      <div class="px-6 py-2.5 bg-slate-100 border-b border-slate-200 flex items-center justify-between">
        <div class="inline-flex rounded-xl bg-white p-1 border border-slate-200 text-xs font-bold">
          <button onclick="switchRombelDetailTab('mapel')" id="btn-sub-rombel-mapel" class="px-4 py-1.5 rounded-lg bg-indigo-950 text-white shadow-xs">
            📊 Pemantauan Mata Pelajaran (11 Mapel)
          </button>
          <button onclick="switchRombelDetailTab('siswa')" id="btn-sub-rombel-siswa" class="px-4 py-1.5 rounded-lg text-slate-600 hover:text-slate-900">
            👥 Keseluruhan Siswa & Ketuntasan (32 Siswa)
          </button>
        </div>
        <button onclick="downloadRombelDetailCSV()" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-xl font-bold flex items-center gap-1.5 text-[11px] shadow-2xs">
          <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          <span>Download Rekap Rombel (CSV)</span>
        </button>
      </div>

      <!-- Body Content Scrollable -->
      <div class="p-6 overflow-y-auto custom-scrollbar flex-1 space-y-4">
        
        <!-- SUB-VIEW 1: PEMANTAUAN MATA PELAJARAN -->
        <div id="sub-view-rombel-mapel" class="space-y-4">
          <div class="bg-indigo-50/60 border border-indigo-100 rounded-2xl p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="w-9 h-9 rounded-xl bg-indigo-900 text-white font-bold flex items-center justify-center text-xs">ℹ️</span>
              <div>
                <p class="font-bold text-indigo-950 text-xs">Status Kesiapan Nilai Seluruh Mata Pelajaran</p>
                <p class="text-[11px] text-indigo-700">Setiap guru pengampu memasukkan nilai tugas dan sumatif secara mandiri. Di bawah ini adalah hasil kompilasi nilai yang masuk.</p>
              </div>
            </div>
            <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-xl font-bold text-xs" id="detail-rombel-complete-badge">10 / 11 Mapel Mengisi</span>
          </div>

          <div class="overflow-x-auto custom-scrollbar border border-slate-200 rounded-2xl shadow-2xs">
            <table class="w-full min-w-[680px] text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                  <th class="py-3 px-4 w-12 text-center">No</th>
                  <th class="py-3 px-4">Mata Pelajaran</th>
                  <th class="py-3 px-4">Guru Pengampu</th>
                  <th class="py-3 px-4 text-center">KKM</th>
                  <th class="py-3 px-4 text-center">Kolom Penilaian</th>
                  <th class="py-3 px-4 text-center">Rata-Rata</th>
                  <th class="py-3 px-4 text-center">Ketuntasan</th>
                  <th class="py-3 px-4 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="detail-rombel-mapel-tbody" class="divide-y divide-slate-100">
                <!-- Injected via JS -->
              </tbody>
            </table>
          </div>
        </div>

        <!-- SUB-VIEW 2: KESELURUHAN SISWA & KELAS -->
        <div id="sub-view-rombel-siswa" class="hidden space-y-4">
          <!-- 3 Stats cards for class -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
              <p class="text-slate-500 text-[11px]">Rata-Rata Nilai Keseluruhan</p>
              <h4 class="text-xl font-black text-indigo-950 mt-1" id="detail-rombel-class-avg">83.4</h4>
              <p class="text-[10px] text-emerald-600 font-semibold mt-0.5">✓ Target KKM Sekolah Tercapai</p>
            </div>
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
              <p class="text-slate-500 text-[11px]">Tingkat Kehadiran Rombel</p>
              <h4 class="text-xl font-black text-slate-800 mt-1" id="detail-rombel-attend-pct">96.2%</h4>
              <p class="text-[10px] text-slate-500 mt-0.5">Hadir: 984 · Alpa: 12</p>
            </div>
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
              <p class="text-slate-500 text-[11px]">Status Ketuntasan Siswa</p>
              <h4 class="text-xl font-black text-emerald-600 mt-1" id="detail-rombel-pass-count">30 / 32 Tuntas</h4>
              <p class="text-[10px] text-amber-700 font-semibold mt-0.5">2 Siswa Perlu Bimbingan / Remedial</p>
            </div>
          </div>

          <div class="overflow-x-auto custom-scrollbar border border-slate-200 rounded-2xl shadow-2xs">
            <table class="w-full min-w-[650px] text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                  <th class="py-3 px-4 w-12 text-center">No</th>
                  <th class="py-3 px-4">Nama Siswa</th>
                  <th class="py-3 px-4">NISN</th>
                  <th class="py-3 px-4 text-center">Rata-Rata Nilai</th>
                  <th class="py-3 px-4 text-center">Kehadiran</th>
                  <th class="py-3 px-4 text-center">Status</th>
                  <th class="py-3 px-4">Catatan Wali Kelas</th>
                </tr>
              </thead>
              <tbody id="detail-rombel-siswa-tbody" class="divide-y divide-slate-100">
                <!-- Injected via JS -->
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Footer Modal -->
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
        <p class="text-[11px] text-slate-500">Data terintegrasi langsung dengan portal guru pengampu dan portal siswa.</p>
        <button onclick="closeDetailRombelModal()" class="px-5 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-xs">
          Tutup Pemantauan
        </button>
      </div>
    </div>
  </div>

  <!-- ============================================================== -->
  <!-- MODAL: CEK RINCIAN HASIL INPUT DARI PORTAL GURU -->
  <!-- ============================================================== -->
  <div id="modal-cek-input-guru" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-3xl w-full max-h-[85vh] flex flex-col shadow-2xl border border-slate-200 text-xs overflow-hidden">
      <div class="p-5 bg-indigo-950 text-white flex items-center justify-between border-b border-indigo-900">
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-extrabold text-white text-base" id="cek-input-modal-title">Pratinjau Hasil Input Lembar Nilai Guru</h3>
            <span class="px-2 py-0.5 rounded-full bg-emerald-400 text-indigo-950 font-bold text-[10px]" id="cek-input-modal-badge">Terverifikasi</span>
          </div>
          <p class="text-indigo-200 text-xs mt-0.5" id="cek-input-modal-subtitle">Memuat data hasil input...</p>
        </div>
        <button onclick="closeCheckTeacherInputModal()" class="text-indigo-300 hover:text-white p-1 rounded-lg">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="p-5 overflow-y-auto custom-scrollbar flex-1 space-y-4">
        <!-- Summary bar -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
            <p class="text-slate-400 text-[10px]">Kolom Nilai Dibuat</p>
            <p class="font-bold text-slate-800 text-xs mt-0.5" id="cek-input-col-count">3 Kolom</p>
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
            <p class="text-slate-400 text-[10px]">Rata-Rata Kelas</p>
            <p class="font-bold text-indigo-950 text-xs mt-0.5" id="cek-input-avg">83.4</p>
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
            <p class="text-slate-400 text-[10px]">Jurnal Mengajar</p>
            <p class="font-bold text-emerald-700 text-xs mt-0.5" id="cek-input-journal-count">14 Pertemuan</p>
          </div>
          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
            <p class="text-slate-400 text-[10px]">Terakhir Diperbarui</p>
            <p class="font-bold text-slate-700 text-xs mt-0.5" id="cek-input-time">Hari ini, 10:15 WIB</p>
          </div>
        </div>

        <!-- Sheet Preview Table -->
        <div class="overflow-x-auto custom-scrollbar border border-slate-200 rounded-2xl shadow-2xs">
          <table class="w-full min-w-[650px] text-left text-xs border-collapse">
            <thead id="cek-input-table-head">
              <!-- Injected via JS -->
            </thead>
            <tbody id="cek-input-table-body" class="divide-y divide-slate-100">
              <!-- Injected via JS -->
            </tbody>
          </table>
        </div>
      </div>

      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button onclick="closeCheckTeacherInputModal()" class="px-5 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-xs">
          Selesai Meninjau
        </button>
      </div>
    </div>
  </div>

  <!-- ============================================================== -->
  <!-- MODAL: DETAIL ARSIP & RESTORE (PULIHKAN) -->
  <!-- ============================================================== -->
  <div id="modal-detail-arsip" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-800 text-base" id="detail-arsip-title">Detail Berkas Arsip</h3>
          <p class="text-slate-500 text-[11px]" id="detail-arsip-type">Arsip Peserta Didik / Pendidik</p>
        </div>
        <button onclick="closeDetailArsipModal()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="mt-4 space-y-3.5" id="detail-arsip-content">
        <!-- Injected via JS -->
      </div>

      <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <button type="button" id="btn-arsip-restore" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span>Pulihkan ke Data Aktif</span>
        </button>
        <button type="button" onclick="closeDetailArsipModal()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
          Tutup
        </button>
      </div>
    </div>
  </div>


  <!-- ============================================================== -->
  <!-- TOAST NOTIFICATION CONTAINER -->
  <!-- ============================================================== -->
  
  <!-- ============================================================== -->
  <!-- MODAL: EDIT DATA ARSIP SISWA -->
  <!-- ============================================================== -->
  <div id="modal-edit-arsip-siswa" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-800 text-base">Edit Berkas Arsip Siswa</h3>
          <p class="text-slate-500 text-[11px]">Perbarui data kelulusan atau mutasi siswa</p>
        </div>
        <button onclick="closeEditArchivedStudentModal()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleSaveEditArchivedStudent(event)" class="mt-4 space-y-3.5">
        <input type="hidden" id="edit-arsip-siswa-id">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Siswa <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-siswa-name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-semibold focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">NISN <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-siswa-nisn" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Kelas Terakhir <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-siswa-lastclass" required placeholder="Contoh: IX A / VIII C" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Status Arsip <span class="text-rose-500">*</span></label>
            <select id="edit-arsip-siswa-status" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              <option value="Lulus">Lulus / Alumni</option>
              <option value="Mutasi Keluar">Mutasi Keluar (Pindah Sekolah)</option>
              <option value="Mengundurkan Diri">Mengundurkan Diri</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Waktu Kejadian (Bulan & Tahun) <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-siswa-time" required placeholder="Contoh: Juni 2026" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Tahun Lulus/Keluar <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-siswa-year" required placeholder="2026" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Sekolah Lanjutan / Keterangan Mutasi</label>
          <input type="text" id="edit-arsip-siswa-destination" required placeholder="Contoh: SMAN 1 Purwakarta / Pindah ikut orang tua" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Nomor Telepon / Kontak</label>
          <input type="text" id="edit-arsip-siswa-phone" placeholder="Contoh: 0812-3456-7890" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
          <button type="button" onclick="closeEditArchivedStudentModal()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
            Batal
          </button>
          <button type="submit" class="px-5 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-xs">
            Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ============================================================== -->
  <!-- MODAL: EDIT DATA ARSIP GURU -->
  <!-- ============================================================== -->
  <div id="modal-edit-arsip-guru" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-lg w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-800 text-base">Edit Berkas Arsip Guru & Staff</h3>
          <p class="text-slate-500 text-[11px]">Perbarui data pensiun atau mutasi pendidik</p>
        </div>
        <button onclick="closeEditArchivedTeacherModal()" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form onsubmit="handleSaveEditArchivedTeacher(event)" class="mt-4 space-y-3.5">
        <input type="hidden" id="edit-arsip-guru-id">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap & Gelar <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-guru-name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-semibold focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">NIP <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-guru-nip" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Mapel / Posisi Terakhir <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-guru-mapel" required placeholder="Contoh: IPS / Matematika" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Status Arsip <span class="text-rose-500">*</span></label>
            <select id="edit-arsip-guru-status" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:outline-none">
              <option value="Pensiun">Pensiun / Purna Tugas</option>
              <option value="Mutasi Keluar">Mutasi ke Sekolah / Instansi Lain</option>
              <option value="Purna Bakti">Purna Bakti / Resign</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Waktu Kejadian (Bulan & Tahun) <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-guru-time" required placeholder="Contoh: Agustus 2026" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Tahun Pensiun / Mutasi <span class="text-rose-500">*</span></label>
            <input type="text" id="edit-arsip-guru-year" required placeholder="2026" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          </div>
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">No. SK / Catatan Keputusan Resmi</label>
          <input type="text" id="edit-arsip-guru-sk" required placeholder="Contoh: SK Pensiun BKN No. 821.2/045/BKN/2026" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
          <button type="button" onclick="closeEditArchivedTeacherModal()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
            Batal
          </button>
          <button type="submit" class="px-5 py-2 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl shadow-xs">
            Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ============================================================== -->
  <!-- MODAL: UPLOAD EXCEL / CSV ARSIP SISWA -->
  <!-- ============================================================== -->
  <div id="modal-upload-arsip-siswa" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-800 text-base">Upload Arsip Siswa (Excel/CSV)</h3>
          <p class="text-slate-500 text-[11px]">Upload berkas massal alumni atau siswa mutasi keluar</p>
        </div>
        <button onclick="closeModalUploadArsipSiswa()" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="mt-4 space-y-4">
        <div class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-6 text-center cursor-pointer transition-colors bg-slate-50/50">
          <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center mx-auto mb-2 text-xl font-bold">
            📄
          </div>
          <p class="font-bold text-slate-700">Tarik & Letakkan File Excel / CSV di sini</p>
          <p class="text-slate-400 text-[11px] mt-1">Format didukung: .csv, .xlsx, .xls (Maks. 10MB)</p>
          <input type="file" accept=".csv" class="hidden" id="file-input-upload-arsip-siswa" onchange="handleArchiveStudentFile(this)">
        </div>

        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-950 flex items-center justify-between">
          <span>Unduh Template Format (.csv)</span>
          <button onclick="downloadArsipSiswaCSVTemplate()" class="font-bold text-emerald-700 hover:text-emerald-900">Download Template &darr;</button>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" onclick="closeModalUploadArsipSiswa()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
            Batal
          </button>
          <button type="button" onclick="simulateUploadArsipSiswa()" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs flex items-center gap-1.5">
            <span>Mulai Upload Data</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================================== -->
  <!-- MODAL: UPLOAD EXCEL / CSV ARSIP GURU -->
  <!-- ============================================================== -->
  <div id="modal-upload-arsip-guru" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-3xl max-w-md w-full max-h-[90vh] overflow-y-auto custom-scrollbar p-5 sm:p-6 shadow-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="font-bold text-slate-800 text-base">Upload Arsip Guru (Excel/CSV)</h3>
          <p class="text-slate-500 text-[11px]">Upload berkas massal guru pensiun atau mutasi dinas</p>
        </div>
        <button onclick="closeModalUploadArsipGuru()" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="mt-4 space-y-4">
        <div class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-6 text-center cursor-pointer transition-colors bg-slate-50/50">
          <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center mx-auto mb-2 text-xl font-bold">
            📄
          </div>
          <p class="font-bold text-slate-700">Tarik & Letakkan File Excel / CSV di sini</p>
          <p class="text-slate-400 text-[11px] mt-1">Format didukung: .csv, .xlsx, .xls (Maks. 10MB)</p>
          <input type="file" accept=".csv" class="hidden" id="file-input-upload-arsip-guru" onchange="handleArchiveTeacherFile(this)">
        </div>

        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-950 flex items-center justify-between">
          <span>Unduh Template Format (.csv)</span>
          <button onclick="downloadArsipGuruCSVTemplate()" class="font-bold text-emerald-700 hover:text-emerald-900">Download Template &darr;</button>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" onclick="closeModalUploadArsipGuru()" class="px-4 py-2 border border-slate-300 text-slate-700 font-bold rounded-xl hover:bg-slate-50">
            Batal
          </button>
          <button type="button" onclick="simulateUploadArsipGuru()" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-xs flex items-center gap-1.5">
            <span>Mulai Upload Data</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div id="toast-box" class="fixed bottom-4 right-4 sm:bottom-5 sm:right-5 z-50 transform transition-all duration-300 translate-y-24 opacity-0 pointer-events-none max-w-[calc(100vw-2rem)] sm:max-w-md">
    <div class="bg-indigo-950 text-white px-4 sm:px-5 py-3.5 rounded-2xl shadow-2xl border border-indigo-800 flex items-center gap-3 text-xs">
      <div class="w-7 h-7 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center font-bold shrink-0">
        ✓
      </div>
      <div>
        <p class="font-bold text-white text-sm" id="toast-title">Notifikasi</p>
        <p class="text-indigo-200 text-[11px]" id="toast-msg">Operasi berhasil dijalankan.</p>
      </div>
    </div>
  </div>


  <!-- ============================================================== -->
  <!-- JAVASCRIPT LOGIC & DATA SIMULATION -->
  <!-- ============================================================== -->
  
