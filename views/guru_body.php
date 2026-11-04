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
  <section id="screen-login" style="display:none" class="min-h-screen flex items-center justify-center p-4 bg-slate-900 relative overflow-hidden">
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
          <p class="font-bold text-white text-xs" id="step2-teacher-name">Memuat data guru...</p>
          <p class="text-slate-400 text-[10px]">Guru Pengampu Aktif</p>
        </div>
      </div>
    </div>

    <!-- Container Form Pemilihan Bertingkat -->
    <div class="max-w-2xl w-full mx-auto my-auto py-4 sm:py-6">
      
      <div class="text-center mb-4 sm:mb-6">
        <span class="text-[11px] sm:text-xs bg-amber-400 text-indigo-950 font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">Langkah Pemilihan Ruang Kerja</span>
        <h2 class="text-xl sm:text-3xl font-extrabold text-white mt-2">Pilih Mata Pelajaran & Kelas Belajar</h2>
        <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-lg mx-auto">
          Pilih mata pelajaran yang Anda ampu dan rombel kelas yang ingin dikelola.
        </p>
      </div>

      <div class="bg-white text-slate-800 rounded-3xl p-4 sm:p-8 shadow-2xl border border-slate-100 space-y-5 sm:space-y-6">
        
        <!-- PILIHAN PERAN (DISEMBUNYIKAN SECARA UI - HAK WALI KELAS BERJALAN OTOMATIS) -->
        <div class="hidden" aria-hidden="true">
          <button type="button" onclick="selectTeacherRole('guru_mapel')" id="btn-role-guru-mapel"></button>
          <button type="button" onclick="selectTeacherRole('wali_kelas')" id="btn-role-wali-kelas"></button>
        </div>

        <!-- LANGKAH 1: PILIH MATA PELAJARAN -->
        <div id="container-select-mapel">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
            1. Mata Pelajaran yang Diajarkan
          </label>
          <div class="relative">
            <select id="select-subject-dropdown" class="w-full bg-slate-50 border-2 border-slate-200 hover:border-indigo-500 focus:border-indigo-600 rounded-xl px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-slate-800 focus:ring-0 focus:outline-none transition-all appearance-none cursor-pointer"><option value="">Memuat mata pelajaran...</option></select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
          </div>
        </div>

        <!-- INFO STATUS PENUGASAN (DETEKSI OTOMATIS BERDASARKAN KELAS YANG DIPILIH) -->
        <div id="container-wali-note" class="bg-indigo-50 border-2 border-indigo-200 rounded-2xl p-3.5 sm:p-4 text-xs text-indigo-950 flex items-start gap-3">
          <span class="text-xl flex-shrink-0">📘</span>
          <div>
            <p class="font-bold text-xs sm:text-sm text-indigo-950">Mode Guru Mata Pelajaran</p>
            <p class="text-slate-600 text-[11px] mt-0.5 leading-relaxed">
              Pilih kelas dan mata pelajaran untuk menginput nilai harian dan presensi siswa.
            </p>
          </div>
        </div>

        <!-- LANGKAH 2: PILIH TINGKAT KELAS (7, 8, atau 9) -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2" id="label-step-grade">
            2. Pilih Tingkat Jenjang (7, 8, atau 9)
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

        <!-- LANGKAH 3: PILIH ROMBEL (A SAMPAI I -> 9 ROMBEL) -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500" id="label-step-rombel">
              3. Pilih Rombongan Belajar (Rombel A sampai I)
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
          <button type="button" onclick="goToLogin()" class="order-2 sm:order-1 w-full sm:w-auto px-6 py-3 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs sm:text-sm shadow-md transition-all flex items-center justify-center gap-2 active:scale-95">
             <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
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
                <span id="active-subject-text">-</span>
              </div>

              <!-- Class Tag -->
              <div class="flex items-center gap-1 bg-indigo-800 px-2.5 py-1 rounded-lg font-medium text-white border border-indigo-700">
                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span id="active-class-text">-</span>
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
              <p class="text-xs font-semibold text-white" id="header-teacher-name">Memuat data guru...</p>
              <p class="text-[11px] text-amber-300/90" id="header-teacher-role">NIP -</p>
            </div>
            <button onclick="goToLogin()" title="Keluar / Ganti Akun" class="w-8 sm:w-9 h-8 sm:h-9 rounded-full bg-indigo-800 border-2 border-amber-400 flex items-center justify-center font-bold text-amber-300 text-xs sm:text-sm hover:scale-105 transition-transform flex-shrink-0">
              <span id="header-teacher-initials">G</span>
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
          <span class="font-bold text-amber-300 truncate" id="mob-active-subject-text">-</span>
          <span class="text-indigo-400">·</span>
          <span class="font-medium text-white truncate" id="mob-active-class-text">-</span>
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
              <div class="w-8 h-8 rounded-full bg-indigo-900 text-amber-300 font-bold flex items-center justify-center text-xs" id="mob-drawer-teacher-initials">G</div>
              <div>
                <p class="font-bold text-indigo-950 text-xs" id="mob-drawer-teacher-name">Memuat data guru...</p>
                <p class="text-[10px] text-slate-500" id="mob-drawer-teacher-nip">NIP -</p>
              </div>
            </div>
            <div class="mt-2.5 pt-2 border-t border-indigo-100/80 flex items-center justify-between">
              <span class="text-[10px] text-slate-500 font-medium">Ruang: <strong class="text-indigo-950" id="mob-drawer-class-label">-</strong></span>
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
                <span data-modul-count class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-md font-semibold">0 File</span>
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
              <span class="text-indigo-700 font-bold bg-white border border-indigo-200 px-2 py-0.5 rounded-lg text-xs" id="mob-sidebar-kkm-display">0</span>
            </div>
            <p class="text-[10px] text-slate-500 mb-1.5">Kelas: <strong id="mob-sidebar-class-name">-</strong></p>
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
              <span data-modul-count class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold">0 File</span>
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
              <span class="text-indigo-700 font-bold bg-white border border-indigo-200 px-2 py-0.5 rounded-lg" id="sidebar-kkm-display">0</span>
            </div>
            <p class="text-slate-600 leading-relaxed mb-2">
              Kelas: <span class="font-bold text-indigo-950" id="sidebar-class-name">-</span>
            </p>
            <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
              <div class="bg-indigo-600 h-full w-[100%] rounded-full" id="sidebar-progress-bar"></div>
            </div>
            <p class="text-[11px] text-slate-500 mt-1.5 text-right font-medium" id="sidebar-pass-percent">0 Siswa Terdaftar</p>
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
                  <select id="select-wali-pov-subject" onchange="handleWaliPovSubjectChange(this.value)" class="bg-indigo-900 text-white font-bold text-xs rounded-lg px-3 py-1.5 border border-indigo-700 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer w-full sm:w-auto"><option value="">Memuat mapel...</option></select>
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
                    Kompilasi nilai akhir seluruh mata pelajaran beserta perhitungan rata-rata gabungan untuk setiap siswa di kelas.
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
                  <p class="text-xl font-black text-indigo-950 mt-0.5" id="metric-ledger-all-avg">0.0</p>
                  <p class="text-[10px] text-emerald-600 font-semibold mt-0.5">Tuntas di atas KKM Sekolah (75)</p>
                </div>
                <div class="bg-emerald-50/60 border border-emerald-100 rounded-xl p-3.5">
                  <p class="text-slate-500 text-[11px] font-medium">Siswa Tuntas Seluruh Mapel</p>
                  <p class="text-xl font-black text-emerald-700 mt-0.5" id="metric-ledger-pass-count">0 / 0 Siswa</p>
                  <p class="text-[10px] text-emerald-700 font-semibold mt-0.5">83.3% Ketuntasan Belajar</p>
                </div>
                <div class="bg-amber-50/60 border border-amber-100 rounded-xl p-3.5">
                  <p class="text-slate-500 text-[11px] font-medium">Perlu Pendampingan / Remedial</p>
                  <p class="text-xl font-black text-amber-700 mt-0.5" id="metric-ledger-remedial-count">0 Siswa</p>
                  <p class="text-[10px] text-amber-800 font-semibold mt-0.5" id="metric-ledger-remedial-note">Siswa dengan rata-rata di bawah KKM</p>
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
                  <thead id="ledger-table-head">
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
                  <span class="text-xs bg-emerald-100 text-emerald-800 font-semibold px-2.5 py-0.5 rounded-full" id="attend-column-count-badge">0 Pertemuan Tercatat</span>
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
                  <span class="text-xs bg-amber-100 text-amber-900 font-semibold px-2.5 py-0.5 rounded-full" id="jurnal-tag-class">-</span>
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

          <!-- Banner Read-Only Wali Kelas (sama dengan tab Input Nilai) -->
          <div id="modul-readonly-banner" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl p-4 text-xs text-amber-950 flex items-start gap-3.5 shadow-2xs">
            <div class="w-9 h-9 rounded-xl bg-amber-400 text-indigo-950 flex items-center justify-center font-black text-lg flex-shrink-0">🔒</div>
            <div class="flex-1">
              <p class="font-bold text-sm text-indigo-950">Akses Pemantauan Wali Kelas (Hanya Lihat / Read-Only)</p>
              <p class="text-slate-600 text-[11px] mt-0.5 leading-relaxed">
                Wali Kelas <strong>tidak dapat mengunggah, menerbitkan, atau menghapus modul</strong>. Unggahan modul sepenuhnya menjadi wewenang Guru Mata Pelajaran pengampu. Anda dapat melihat dan membuka modul yang sudah diterbitkan.
              </p>
              <div id="modul-wali-bar" class="hidden mt-3 flex flex-wrap items-center gap-2">
                <label for="modul-pov-subject" class="font-bold text-indigo-950 text-[11px]">Pantau mapel:</label>
                <select id="modul-pov-subject" onchange="handleWaliPovSubjectChange(this.value)" class="bg-indigo-900 text-white font-bold text-xs rounded-lg px-3 py-1.5 border border-indigo-700 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"></select>
              </div>
            </div>
          </div>

          
          <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
              <div>
                <h2 id="modul-heading" class="text-base sm:text-lg font-bold text-indigo-950">Upload Modul / Materi Pembelajaran</h2>
                <p id="modul-subheading" class="text-xs text-slate-500">Unggah bahan ajar, modul PDF, LKPD, atau kisi-kisi untuk kelas aktif.</p>
              </div>
              <span id="modul-max-badge" class="text-xs bg-amber-100 text-amber-900 font-semibold px-2.5 py-1 rounded-lg self-start sm:self-auto">Maksimal 15 MB / file</span>
            </div>

            <form id="form-upload-modul" enctype="multipart/form-data" onsubmit="event.preventDefault(); submitModule('Terbit');" class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5 mt-4 text-xs">
              <div class="space-y-3">
                <div>
                  <label class="block text-slate-600 font-semibold mb-1">Judul Materi / Modul</label>
                  <input id="input-modul-title" name="judul" type="text" placeholder="Contoh: Modul Eksponen & Bentuk Akar" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-slate-600 font-semibold mb-1">Mata Pelajaran (Terkunci)</label>
                    <input type="text" id="modul-subject-locked" name="mapel" value="Matematika" readonly class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 font-medium cursor-not-allowed text-slate-600">
                  </div>
                  <div>
                    <label class="block text-slate-600 font-semibold mb-1">Kelas Sasaran</label>
                    <input type="text" id="modul-class-locked" name="rombel" value="Kelas VIII A" readonly class="w-full bg-slate-100 border border-slate-200 rounded-lg px-3 py-2 font-medium cursor-not-allowed text-slate-600">
                  </div>
                </div>

                <div>
                  <label class="block text-slate-600 font-semibold mb-1">Deskripsi Singkat untuk Siswa</label>
                  <textarea id="input-modul-desc" name="deskripsi" rows="3" placeholder="Catatan instruksi membaca modul..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>
              </div>

              <!-- Drag & Drop Zone -->
              <div class="flex flex-col justify-between">
                <div id="modul-dropzone" onclick="if(event.target.id!=='input-modul-file')document.getElementById('input-modul-file').click()" ondragover="event.preventDefault()" ondrop="onModulFileDrop(event)" class="border-2 border-dashed border-indigo-200 hover:border-indigo-400 bg-indigo-50/30 rounded-xl p-5 sm:p-6 text-center flex flex-col items-center justify-center flex-1 cursor-pointer transition-colors group">
                  <input type="file" id="input-modul-file" name="file" class="hidden" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png" onchange="onModulFileChosen(this)">
                  <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                  </div>
                  <p class="font-bold text-indigo-950 text-sm">Tarik & Lepas File Modul (PDF / DOCX)</p>
                  <p class="text-slate-400 text-xs mt-1">atau klik untuk telusuri file dari perangkat</p>
                  <div class="mt-3 px-3 py-1 bg-white border border-indigo-100 text-indigo-800 rounded-md font-medium text-[11px] shadow-sm">
                    <span id="modul-file-label">Belum ada file dipilih</span>
                  </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 mt-4">
                  <button type="button" id="btn-modul-draf" onclick="submitModule('Draf')" class="w-full sm:w-auto px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl font-semibold justify-center">Simpan Draf</button>
                  <button type="submit" id="btn-modul-terbit" class="w-full sm:w-auto px-5 py-2 bg-indigo-900 hover:bg-indigo-800 text-white rounded-xl font-bold shadow-sm transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Terbitkan Modul</span>
                  </button>
                </div>
              </div>
            </form>
          </div>

          <!-- Daftar Modul Terbit -->
          <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3 sm:py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
              <h3 class="font-bold text-sm text-indigo-950" id="module-header-title">Modul Terbit</h3>
              <span class="text-xs text-slate-500">Tersedia untuk siswa di kelas ini</span>
            </div>

            <div class="divide-y divide-slate-100 text-xs" id="module-list-container">
              <div class="p-6 text-center text-slate-400 text-[11px]">Belum ada modul yang diunggah untuk kelas ini.</div>
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
                <p class="text-lg sm:text-xl font-bold text-amber-300 mt-0.5" id="metric-class-avg">0.0</p>
                <p class="text-[10px] text-emerald-400 mt-1" id="metric-kkm-comparison">Target KKM: 75</p>
              </div>
              <div class="bg-indigo-900/60 rounded-xl p-3 border border-indigo-700/50">
                <p class="text-[11px] text-indigo-300">Rata-Rata Kehadiran</p>
                <p class="text-lg sm:text-xl font-bold text-emerald-400 mt-0.5" id="metric-attend-avg">0%</p>
                <p class="text-[10px] text-indigo-200 mt-1">Akumulasi 1 Semester</p>
              </div>
              <div class="bg-indigo-900/60 rounded-xl p-3 border border-indigo-700/50 col-span-2 sm:col-span-2">
                <p class="text-[11px] text-indigo-300">Catatan Evaluasi Guru</p>
                <p class="text-lg sm:text-xl font-bold text-amber-300 mt-0.5" id="metric-notes-count">0 Siswa</p>
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