<?php
// ==============================================================
// BACKEND LOGIC: KONEKSI & CRUD DATA GURU MYSQL
// ==============================================================
require_once __DIR__ . '/koneksi.php';

// ==============================================================
// MULAI TAMBAHAN LANGKAH 1: HANDLER UPLOAD CSV GURU
// ==============================================================
if (isset($_POST['submit_import'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        $handle  = fopen($tmpName, 'r');
        
        // Lewati baris pertama (Header CSV)
        fgetcsv($handle, 2000, ','); 
        $inserted = 0;
        
        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if (empty(trim($row[0]))) continue;
            
            // Format Kolom: NIP, Nama, Mapel, Wali, No HP, Status
            $nip    = trim($row[0] ?? '');
            $nama   = trim($row[1] ?? '');
            $mapel  = trim($row[2] ?? '-');
            $wali   = trim($row[3] ?? '-');
            $phone  = trim($row[4] ?? '');
            $status = trim($row[5] ?? 'Aktif');
            
            if (empty($nama)) continue;

            $qMax = mysqli_query($koneksi, "SELECT MAX(CAST(kode AS UNSIGNED)) as max_kode FROM data_guru");
            $rMax = mysqli_fetch_assoc($qMax);
            $nextKode = (string)(($rMax['max_kode'] ?? 0) + 1);

            $stmt = mysqli_prepare($koneksi, "INSERT INTO data_guru (kode, nama_guru, nip, mata_pelajaran, kelas, wali_kelas, phone, jam_per_minggu, password, status, jumlah) VALUES (?, ?, ?, ?, '-', ?, ?, '0', 'guru12345', ?, 0)");
            
            mysqli_stmt_bind_param($stmt, 'sssssss', $nextKode, $nama, $nip, $mapel, $wali, $phone, $status);
            if(mysqli_stmt_execute($stmt)) {
                $inserted++;
            }
            mysqli_stmt_close($stmt);
        }
        fclose($handle);
        
        echo "<script>alert('Berhasil! $inserted data guru berhasil diunggah.'); window.location.href='admin.php#guru';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal mengunggah file. Pastikan format CSV.'); window.location.href='admin.php#guru';</script>";
        exit;
    }
}
// ==============================================================
// AKHIR TAMBAHAN LANGKAH 1
// ==============================================================


// 1. AJAX / API HANDLER UNTUK CRUD & UPLOAD GURU
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'add_teacher' || $action === 'edit_teacher') {
        $name     = trim($_POST['name'] ?? '');
        $nip      = trim($_POST['nip'] ?? '');
        $mapel    = trim($_POST['mapel'] ?? '');
        $wali     = trim($_POST['wali'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $kelas    = trim($_POST['kelas'] ?? '-');
        $jam      = trim($_POST['jam_per_minggu'] ?? '0');
        $status   = trim($_POST['status'] ?? 'Aktif');
        $password = trim($_POST['password'] ?? '');
        $id       = trim($_POST['id'] ?? '');

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nama guru wajib diisi!']);
            exit;
        }

        if ($action === 'add_teacher' || empty($id)) {
            $qMax = mysqli_query($koneksi, "SELECT MAX(CAST(kode AS UNSIGNED)) as max_kode FROM data_guru");
            $rMax = mysqli_fetch_assoc($qMax);
            $nextKode = (string)(($rMax['max_kode'] ?? 0) + 1);
            $pwdToSave = !empty($password) ? $password : 'guru12345';

            $stmt = mysqli_prepare($koneksi, "INSERT INTO data_guru (kode, nama_guru, nip, mata_pelajaran, kelas, wali_kelas, phone, jam_per_minggu, password, status, jumlah) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            mysqli_stmt_bind_param($stmt, 'ssssssssss', $nextKode, $name, $nip, $mapel, $kelas, $wali, $phone, $jam, $pwdToSave, $status);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            echo json_encode(['success' => $ok, 'message' => $ok ? 'Guru berhasil ditambahkan ke database MySQL.' : mysqli_error($koneksi)]);
            exit;
        } else {
            if (!empty($password)) {
                $stmt = mysqli_prepare($koneksi, "UPDATE data_guru SET nama_guru = ?, nip = ?, mata_pelajaran = ?, kelas = ?, wali_kelas = ?, phone = ?, jam_per_minggu = ?, status = ?, password = ? WHERE kode = ?");
                mysqli_stmt_bind_param($stmt, 'ssssssssss', $name, $nip, $mapel, $kelas, $wali, $phone, $jam, $status, $password, $id);
            } else {
                $stmt = mysqli_prepare($koneksi, "UPDATE data_guru SET nama_guru = ?, nip = ?, mata_pelajaran = ?, kelas = ?, wali_kelas = ?, phone = ?, jam_per_minggu = ?, status = ? WHERE kode = ?");
                mysqli_stmt_bind_param($stmt, 'sssssssss', $name, $nip, $mapel, $kelas, $wali, $phone, $jam, $status, $id);
            }
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            echo json_encode(['success' => $ok, 'message' => $ok ? 'Data guru berhasil diperbarui di database.' : mysqli_error($koneksi)]);
            exit;
        }
    }

    if ($action === 'delete_teacher') {
        $id = trim($_POST['id'] ?? '');
        $stmt = mysqli_prepare($koneksi, "DELETE FROM data_guru WHERE kode = ?");
        mysqli_stmt_bind_param($stmt, 's', $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => $ok, 'message' => $ok ? 'Data guru berhasil dihapus dari database.' : mysqli_error($koneksi)]);
        exit;
    }
}

// 2. QUERY SEMUA DATA GURU DARI MYSQL UNTUK DI-RENDER KE HALAMAN
$teachersList = [];
$queryGuru = mysqli_query($koneksi, "SELECT * FROM data_guru ORDER BY CAST(kode AS UNSIGNED) ASC");
if ($queryGuru && mysqli_num_rows($queryGuru) > 0) {
    while ($row = mysqli_fetch_assoc($queryGuru)) {
        $kodeNum = is_numeric($row['kode']) ? (int)$row['kode'] : count($teachersList) + 1;
        $teachersList[] = [
            'id'             => $row['kode'],
            'kode'           => $row['kode'],
            'name'           => $row['nama_guru'],
            'nip'            => !empty($row['nip']) ? $row['nip'] : ('198' . (50 + ($kodeNum % 40)) . '0101 ' . (2010 + ($kodeNum % 10)) . '01 ' . ($kodeNum % 2 == 0 ? '2' : '1') . ' ' . str_pad($kodeNum, 3, '0', STR_PAD_LEFT)),
            'mapel'          => !empty($row['mata_pelajaran']) ? $row['mata_pelajaran'] : 'Tenaga Pendidik / Staf',
            'kelas'          => !empty($row['kelas']) ? $row['kelas'] : '-',
            'wali'           => !empty($row['wali_kelas']) ? $row['wali_kelas'] : 'Bukan Wali Kelas',
            'jam_per_minggu' => $row['jam_per_minggu'] ?? '0',
            'jumlah'         => isset($row['jumlah']) ? (int)$row['jumlah'] : 0,
            'phone'          => !empty($row['phone']) ? $row['phone'] : ('081' . (2 + ($kodeNum % 8)) . '-' . str_pad(($kodeNum * 137) % 9000 + 1000, 4, '0', STR_PAD_LEFT) . '-' . str_pad(($kodeNum * 259) % 9000 + 1000, 4, '0', STR_PAD_LEFT)),
            'password'       => !empty($row['password']) ? $row['password'] : 'guru12345',
            'status'         => !empty($row['status']) ? $row['status'] : 'Aktif'
        ];
    }
}
$totalGuru = count($teachersList);
// ==============================================================
// BACKEND AJAX HANDLER UNTUK CRUD & IMPORT SISWA
// ==============================================================

// A. HANDLER IMPORT CSV SISWA
if (isset($_POST['submit_import_siswa'])) {
    if (isset($_FILES['csv_file_siswa']) && $_FILES['csv_file_siswa']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file_siswa']['tmp_name'];
        $handle  = fopen($tmpName, 'r');
        
        fgetcsv($handle, 2000, ','); // Lewati baris pertama (Header)
        $inserted = 0;
        
        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            $nisn   = trim($row[0] ?? '');
            $nama   = trim($row[1] ?? '');
            $gender = trim($row[2] ?? 'L');
            $kelas  = trim($row[3] ?? 'VIII A');
            $parent = trim($row[4] ?? '-');
            $phone  = trim($row[5] ?? '-');
            $status = trim($row[6] ?? 'Aktif Belajar');
            
            if (empty($nama)) continue; // Jika nama kosong, lewati baris ini

            $stmt = mysqli_prepare($koneksi, "INSERT INTO data_siswa (nisn, nama_siswa, kelas, gender, orang_tua, telepon, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssssss', $nisn, $nama, $kelas, $gender, $parent, $phone, $status);
            if(mysqli_stmt_execute($stmt)) {
                $inserted++;
            }
            mysqli_stmt_close($stmt);
        }
        fclose($handle);
        
        echo "<script>alert('Berhasil! $inserted data siswa berhasil diunggah ke database.'); window.location.href='admin.php#siswa';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal mengunggah file. Pastikan formatnya CSV.'); window.location.href='admin.php#siswa';</script>";
        exit;
    }
}

// B. HANDLER TAMBAH, EDIT, DAN HAPUS SISWA AJAX
if (isset($_POST['action'])) {
    ini_set('display_errors', 0); error_reporting(0);
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'add_student' || $action === 'edit_student') {
        $nama_asli = trim($_POST['id'] ?? ''); 
        $nisn   = trim($_POST['nisn'] ?? '');
        $name   = trim($_POST['name'] ?? '');
        $gender = trim($_POST['gender'] ?? 'L');
        $class  = trim($_POST['class'] ?? 'VIII A');
        $parent = trim($_POST['parent'] ?? '-');
        $phone  = trim($_POST['phone'] ?? '-');
        $status = trim($_POST['status'] ?? 'Aktif Belajar');

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nama Siswa wajib diisi!']); exit;
        }

        if (empty($nama_asli)) {
            // INSERT DATA BARU
            $stmt = mysqli_prepare($koneksi, "INSERT INTO data_siswa (nisn, nama_siswa, kelas, gender, orang_tua, telepon, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssssss', $nisn, $name, $class, $gender, $parent, $phone, $status);
        } else {
            // UPDATE BERDASARKAN NAMA SEBELUMNYA
            $stmt = mysqli_prepare($koneksi, "UPDATE data_siswa SET nisn = ?, nama_siswa = ?, kelas = ?, gender = ?, orang_tua = ?, telepon = ?, status = ? WHERE nama_siswa = ?");
            mysqli_stmt_bind_param($stmt, 'ssssssss', $nisn, $name, $class, $gender, $parent, $phone, $status, $nama_asli);
        }
        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Data siswa berhasil disimpan.' : 'Error SQL: ' . mysqli_error($koneksi)]); exit;
    }

    if ($action === 'delete_student') {
        $nama_hapus = trim($_POST['id'] ?? '');
        $stmt = mysqli_prepare($koneksi, "DELETE FROM data_siswa WHERE nama_siswa = ?");
        mysqli_stmt_bind_param($stmt, 's', $nama_hapus);
        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Siswa berhasil dihapus.' : 'Error SQL: ' . mysqli_error($koneksi)]); exit;
    }
}

// C. QUERY AMBIL SEMUA DATA SISWA DARI MYSQL UNTUK KE JAVASCRIPT
$studentsList = [];
$querySiswa = mysqli_query($koneksi, "SELECT * FROM data_siswa ORDER BY nama_siswa ASC");
if ($querySiswa && mysqli_num_rows($querySiswa) > 0) {
    while ($row = mysqli_fetch_assoc($querySiswa)) {
        $studentsList[] = [
            'id'     => $row['nama_siswa'], // Menggunakan nama sebagai ID
            'nisn'   => !empty($row['nisn']) ? $row['nisn'] : '-',
            'name'   => $row['nama_siswa'] ?? '',
            'gender' => $row['gender'] ?? 'L',
            'class'  => $row['kelas'] ?? 'VIII A',
            'parent' => $row['orang_tua'] ?? '-',
            'phone'  => $row['telepon'] ?? '-',
            'status' => $row['status'] ?? 'Aktif Belajar'
        ];
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

          <button onclick="switchAdminTab('guru')" id="nav-btn-guru" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              <span class="truncate">Data Guru & Staff</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-amber-300 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-guru">32</span>
          </button>

          <button onclick="switchAdminTab('siswa')" id="nav-btn-siswa" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
              <span class="truncate">Data Siswa (Induk)</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-indigo-200 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-siswa">864</span>
          </button>

          <button onclick="switchAdminTab('mapel')" id="nav-btn-mapel" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
              <span class="truncate">Mata Pelajaran & KKM</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-indigo-200 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-mapel">11</span>
          </button>

          <button onclick="switchAdminTab('rombel')" id="nav-btn-rombel" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
              <span class="truncate">Kelas & Rombel</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-indigo-200 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-rombel">27 Rombel</span>
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
            <span class="bg-indigo-800 text-[10px] text-amber-300 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-arsip-siswa">7</span>
          </button>

          <button onclick="switchAdminTab('arsip-guru')" id="nav-btn-arsip-guru" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-indigo-200 hover:bg-indigo-900/60 hover:text-white transition-all text-left">
            <div class="flex items-center gap-3 min-w-0">
              <svg class="w-4 h-4 text-amber-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
              <span class="truncate">Arsip Guru & Staff</span>
            </div>
            <span class="bg-indigo-800 text-[10px] text-amber-300 font-bold px-2 py-0.5 rounded-full shrink-0" id="sidebar-count-arsip-guru">5</span>
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
            AD
          </div>
          <div class="min-w-0">
            <p class="text-xs font-bold text-white leading-tight truncate">Drs. H. Mulyadi</p>
            <p class="text-[10px] text-indigo-300 truncate">Admin Utama (TU)</p>
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

        <!-- 4 Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
          
          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Total Siswa Aktif</p>
              <h4 class="text-2xl font-black text-slate-800 mt-1" id="stat-total-siswa">864</h4>
              <p class="text-[11px] text-emerald-600 font-semibold mt-1">27 Rombongan Belajar (A-I)</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-slate-500 font-medium">Guru & Tenaga Pendidik</p>
                <h4 class="text-2xl font-black text-slate-800 mt-1" id="stat-total-guru">32</h4>
                <p class="text-[11px] text-indigo-600 font-semibold mt-1">100% Memiliki Akun</p>
              </div>
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px]">
              <button onclick="switchAdminTab('monitoring-guru')" class="text-slate-500 hover:text-indigo-600 font-semibold flex items-center gap-0.5">
                Pantau Nilai &rarr;
              </button>
              <a href="guru.php" target="_blank" class="text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-0.5">
                Portal Guru &nearr;
              </a>
            </div>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Mata Pelajaran</p>
              <h4 class="text-2xl font-black text-slate-800 mt-1" id="stat-total-mapel">11</h4>
              <p class="text-[11px] text-amber-600 font-semibold mt-1">KKM Rata-Rata: 75</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            </div>
          </div>

          <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
              <p class="text-slate-500 font-medium">Rombongan Belajar</p>
              <h4 class="text-2xl font-black text-emerald-600 mt-1">27 Rombel</h4>
              <p class="text-[11px] text-slate-500 font-semibold mt-1">Kelas 7, 8, 9 (A s/d I)</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
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
                <p class="text-slate-500 text-[11px]">Total 864 siswa terbagi di 27 rombongan belajar aktif (A sampai I).</p>
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
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 font-bold rounded-lg text-xs">288 Siswa Aktif</span>
              </div>

              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 flex items-center justify-between">
                <div>
                  <p class="font-bold text-slate-800 text-xs">Jenjang Kelas VIII (Rombel VIII A - VIII I)</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">9 Kelas · Kapasitas 32 siswa/kelas</p>
                </div>
                <span class="px-3 py-1 bg-indigo-100 text-indigo-800 font-bold rounded-lg text-xs">288 Siswa Aktif</span>
              </div>

              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60 flex items-center justify-between">
                <div>
                  <p class="font-bold text-slate-800 text-xs">Jenjang Kelas IX (Rombel IX A - IX I)</p>
                  <p class="text-[11px] text-slate-500 mt-0.5">9 Kelas · Kapasitas 32 siswa/kelas</p>
                </div>
                <span class="px-3 py-1 bg-amber-100 text-amber-900 font-bold rounded-lg text-xs">288 Siswa Aktif</span>
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
            <div class="divide-y divide-slate-100">
              
              <div class="py-2.5 flex items-start gap-3">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-[10px] flex-shrink-0 mt-0.5">
                  SR
                </div>
                <div>
                  <p class="text-slate-800 font-bold">Ibu Siti Rahmawati, S.Pd</p>
                  <p class="text-slate-500 text-[11px]">Memperbarui nilai 32 siswa Matematika (Kelas VIII A)</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">10 menit yang lalu</p>
                </div>
              </div>

              <div class="py-2.5 flex items-start gap-3">
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-[10px] flex-shrink-0 mt-0.5">
                  BS
                </div>
                <div>
                  <p class="text-slate-800 font-bold">Bapak Budi Santoso, M.Pd</p>
                  <p class="text-slate-500 text-[11px]">Mengunggah modul "Hukum Gerak Newton" (IPA Kelas VII)</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">42 menit yang lalu</p>
                </div>
              </div>

              <div class="py-2.5 flex items-start gap-3">
                <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-bold text-[10px] flex-shrink-0 mt-0.5">
                  RN
                </div>
                <div>
                  <p class="text-slate-800 font-bold">Ibu Rina Nurhasanah, S.Pd</p>
                  <p class="text-slate-500 text-[11px]">Melakukan presensi harian Kelas IX B (Bahasa Inggris)</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">2 jam yang lalu</p>
                </div>
              </div>

              <div class="py-2.5 flex items-start gap-3">
                <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-[10px] flex-shrink-0 mt-0.5">
                  TU
                </div>
                <div>
                  <p class="text-slate-800 font-bold">Tata Usaha (Admin)</p>
                  <p class="text-slate-500 text-[11px]">Menambahkan 2 siswa mutasi masuk ke Rombel VII C</p>
                  <p class="text-[10px] text-slate-400 mt-0.5">Kemarin, 14.20 WIB</p>
                </div>
              </div>

            </div>
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
              <select id="filter-guru-mapel" onchange="renderTeachersTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Mata Pelajaran</option>
                <option value="Matematika">Matematika</option>
                <option value="Ilmu Pengetahuan Alam (IPA)">Ilmu Pengetahuan Alam (IPA)</option>
                <option value="Bahasa Indonesia">Bahasa Indonesia</option>
                <option value="Bahasa Inggris">Bahasa Inggris</option>
                <option value="Pendidikan Pancasila">Pendidikan Pancasila</option>
                <option value="Informatika">Informatika</option>
              </select>
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
                <option value="8" selected>Kelas 8</option>
                <option value="9">Kelas 9</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Rombel Kelas</label>
              <select id="filter-siswa-rombel" onchange="renderStudentsTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Rombel</option>
                <option value="VIII A" selected>Kelas VIII A</option>
                <option value="VIII B">Kelas VIII B</option>
                <option value="VIII C">Kelas VIII C</option>
                <option value="VIII D">Kelas VIII D</option>
                <option value="VIII E">Kelas VIII E</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-600 mb-1">Status Keaktifan</label>
              <select id="filter-siswa-status" onchange="renderStudentsTable()" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                <option value="ALL">Semua Status</option>
                <option value="Aktif Belajar" selected>Aktif Belajar</option>
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
              <select id="bulk-target-class" class="bg-white text-slate-800 font-bold text-xs rounded-lg px-2.5 py-1 focus:outline-none cursor-pointer">
                <optgroup label="Jenjang Kelas VII">
                  <option value="VII A">Kelas VII A</option>
                  <option value="VII B">Kelas VII B</option>
                  <option value="VII C">Kelas VII C</option>
                  <option value="VII D">Kelas VII D</option>
                  <option value="VII E">Kelas VII E</option>
                  <option value="VII F">Kelas VII F</option>
                  <option value="VII G">Kelas VII G</option>
                  <option value="VII H">Kelas VII H</option>
                  <option value="VII I">Kelas VII I</option>
                </optgroup>
                <optgroup label="Jenjang Kelas VIII">
                  <option value="VIII A" selected>Kelas VIII A</option>
                  <option value="VIII B">Kelas VIII B</option>
                  <option value="VIII C">Kelas VIII C</option>
                  <option value="VIII D">Kelas VIII D</option>
                  <option value="VIII E">Kelas VIII E</option>
                  <option value="VIII F">Kelas VIII F</option>
                  <option value="VIII G">Kelas VIII G</option>
                  <option value="VIII H">Kelas VIII H</option>
                  <option value="VIII I">Kelas VIII I</option>
                </optgroup>
                <optgroup label="Jenjang Kelas IX">
                  <option value="IX A">Kelas IX A</option>
                  <option value="IX B">Kelas IX B</option>
                  <option value="IX C">Kelas IX C</option>
                  <option value="IX D">Kelas IX D</option>
                  <option value="IX E">Kelas IX E</option>
                  <option value="IX F">Kelas IX F</option>
                  <option value="IX G">Kelas IX G</option>
                  <option value="IX H">Kelas IX H</option>
                  <option value="IX I">Kelas IX I</option>
                </optgroup>
              </select>
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
                <span>🏫 Total 27 Rombongan Belajar (A s/d I)</span>
              </div>
              <h3 class="text-lg font-bold text-slate-800">Daftar Rombongan Belajar (27 Kelas)</h3>
              <p class="text-xs text-slate-500">Struktur rombel kelas VII, VIII, dan IX. <strong class="text-indigo-950">Klik pada kartu kelas</strong> untuk memantau seluruh mata pelajaran dan performa keseluruhan rombel.</p>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-slate-600">Filter Jenjang:</span>
              <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold">
                <button onclick="filterRombelCards('ALL')" id="btn-rf-all" class="px-3 py-1 rounded-lg bg-white shadow-xs text-indigo-950">Semua (27)</button>
                <button onclick="filterRombelCards('7')" id="btn-rf-7" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900">Kelas 7 (9)</button>
                <button onclick="filterRombelCards('8')" id="btn-rf-8" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900">Kelas 8 (9)</button>
                <button onclick="filterRombelCards('9')" id="btn-rf-9" class="px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900">Kelas 9 (9)</button>
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
                  <select class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                    <option selected>2026/2027</option>
                    <option>2025/2026</option>
                  </select>
                </div>
                <div>
                  <label class="block font-bold text-slate-700 mb-1">Semester Aktif</label>
                  <select class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
                    <option selected>Semester 1 (Ganjil)</option>
                    <option>Semester 2 (Genap)</option>
                  </select>
                </div>
                <div>
                  <label class="block font-bold text-slate-700 mb-1">Batas Akhir Input Nilai Guru</label>
                  <input type="date" value="2026-10-20" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
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
          <input type="text" id="input-guru-pass" placeholder="Default: guru12345 (Diatur Admin)" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-mono focus:ring-2 focus:ring-indigo-600 focus:outline-none">
          <p class="text-[10px] text-slate-500 mt-1">Kata sandi yang digunakan oleh guru untuk masuk ke sistem.</p>
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
            <select id="input-siswa-class" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 font-medium">
              <optgroup label="Jenjang VII">
                <option value="VII A">VII A</option>
                <option value="VII B">VII B</option>
                <option value="VII C">VII C</option>
                <option value="VII D">VII D</option>
                <option value="VII E">VII E</option>
                <option value="VII F">VII F</option>
                <option value="VII G">VII G</option>
                <option value="VII H">VII H</option>
                <option value="VII I">VII I</option>
              </optgroup>
              <optgroup label="Jenjang VIII">
                <option value="VIII A" selected>VIII A</option>
                <option value="VIII B">VIII B</option>
                <option value="VIII C">VIII C</option>
                <option value="VIII D">VIII D</option>
                <option value="VIII E">VIII E</option>
                <option value="VIII F">VIII F</option>
                <option value="VIII G">VIII G</option>
                <option value="VIII H">VIII H</option>
                <option value="VIII I">VIII I</option>
              </optgroup>
              <optgroup label="Jenjang IX">
                <option value="IX A">IX A</option>
                <option value="IX B">IX B</option>
                <option value="IX C">IX C</option>
                <option value="IX D">IX D</option>
                <option value="IX E">IX E</option>
                <option value="IX F">IX F</option>
                <option value="IX G">IX G</option>
                <option value="IX H">IX H</option>
                <option value="IX I">IX I</option>
              </optgroup>
            </select>
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
            <input type="text" id="input-mapel-lead" required placeholder="Contoh: Ibu Siti Rahmawati, S.Pd" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
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
      <form action="" method="POST" enctype="multipart/form-data">
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
            <button type="submit" name="submit_import_siswa" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-sm flex items-center gap-1.5">
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
      <form action="" method="POST" enctype="multipart/form-data">
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
            <button type="submit" name="submit_import" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-sm flex items-center gap-1.5">
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
            <p class="text-indigo-200 text-xs mt-0.5" id="detail-rombel-sub">Wali Kelas: Ibu Siti Rahmawati, S.Pd · Ruang: R. 201 · 32 / 32 Siswa</p>
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
          <p class="text-indigo-200 text-xs mt-0.5" id="cek-input-modal-subtitle">Matematika · Kelas VIII A · Ibu Siti Rahmawati, S.Pd</p>
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
          <input type="file" class="hidden" id="file-input-upload-arsip-siswa">
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
          <input type="file" class="hidden" id="file-input-upload-arsip-guru">
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
  <script>
    // DATA MASTER GURU DARI MYSQL
    let teachersData = <?php echo !empty($teachersList) ? json_encode($teachersList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) : '[]'; ?>;


    // DATA MASTER SISWA
   // DATA MASTER SISWA DARI MYSQL
    let studentsData = <?php echo json_encode($studentsList ?? []); ?>;

    // DATA MASTER MATA PELAJARAN (11 MAPEL - 27 ROMBEL)
    let mapelData = [
      { code: 'MP-01', name: 'Matematika', group: 'Kelompok A (Umum)', hours: 4, kkm: 75, lead: 'Ibu Siti Rahmawati, S.Pd', rombelCovered: 27 },
      { code: 'MP-02', name: 'Ilmu Pengetahuan Alam (IPA)', group: 'Kelompok A (Umum)', hours: 5, kkm: 75, lead: 'Bapak Budi Santoso, M.Pd', rombelCovered: 27 },
      { code: 'MP-03', name: 'Bahasa Indonesia', group: 'Kelompok A (Umum)', hours: 5, kkm: 78, lead: 'Ibu Dra. Hj. Nur Aini', rombelCovered: 27 },
      { code: 'MP-04', name: 'Bahasa Inggris', group: 'Kelompok A (Umum)', hours: 4, kkm: 75, lead: 'Ibu Rina Nurhasanah, S.Pd', rombelCovered: 27 },
      { code: 'MP-05', name: 'Pendidikan Pancasila', group: 'Kelompok A (Umum)', hours: 3, kkm: 78, lead: 'Bapak Dedi Suhendar, S.Pd', rombelCovered: 27 },
      { code: 'MP-06', name: 'Pendidikan Agama & Budi Pekerti', group: 'Kelompok A (Umum)', hours: 3, kkm: 80, lead: 'Ibu Eni Rohaeni, S.Ag', rombelCovered: 27 },
      { code: 'MP-07', name: 'Ilmu Pengetahuan Sosial (IPS)', group: 'Kelompok A (Umum)', hours: 4, kkm: 75, lead: 'Bapak Tatang Sutisna, S.Pd', rombelCovered: 27 },
      { code: 'MP-08', name: 'Informatika', group: 'Kelompok B (Pengembangan)', hours: 2, kkm: 75, lead: 'Bapak Ahmad Fauzi, S.Kom', rombelCovered: 27 },
      { code: 'MP-09', name: 'Pendidikan Jasmani (PJOK)', group: 'Kelompok B (Pengembangan)', hours: 3, kkm: 78, lead: 'Bapak Asep Kurnia, S.Pd', rombelCovered: 27 },
      { code: 'MP-10', name: 'Seni Budaya', group: 'Kelompok B (Pengembangan)', hours: 3, kkm: 75, lead: 'Ibu Maya Lestari, S.Sn', rombelCovered: 27 },
      { code: 'MP-11', name: 'Bahasa Sunda (Muatan Lokal)', group: 'Kelompok C (Muatan Lokal)', hours: 2, kkm: 75, lead: 'Ibu Ceceu Marlina, S.Pd', rombelCovered: 27 }
    ];

    // DATA 27 ROMBEL LENGKAP (7A-I, 8A-I, 9A-I)
    const rombelList = [
      // JENJANG KELAS VII (A - I)
      { name: 'VII A', grade: '7', wali: 'Bapak Budi Santoso, M.Pd', count: 32, max: 32, room: 'R. 101' },
      { name: 'VII B', grade: '7', wali: 'Ibu Eni Rohaeni, S.Ag', count: 32, max: 32, room: 'R. 102' },
      { name: 'VII C', grade: '7', wali: 'Bapak Tatang Sutisna, S.Pd', count: 32, max: 32, room: 'R. 103' },
      { name: 'VII D', grade: '7', wali: 'Ibu Maya Lestari, S.Sn', count: 32, max: 32, room: 'R. 104' },
      { name: 'VII E', grade: '7', wali: 'Bapak Asep Kurnia, S.Pd', count: 32, max: 32, room: 'R. 105' },
      { name: 'VII F', grade: '7', wali: 'Ibu Ratih Pratiwi, S.Pd', count: 32, max: 32, room: 'R. 106' },
      { name: 'VII G', grade: '7', wali: 'Bapak Joko Susilo, M.Pd', count: 32, max: 32, room: 'R. 107' },
      { name: 'VII H', grade: '7', wali: 'Ibu Fitri Handayani, S.Pd', count: 32, max: 32, room: 'R. 108' },
      { name: 'VII I', grade: '7', wali: 'Bapak Agus Setiawan, S.Kom', count: 32, max: 32, room: 'R. 109' },

      // JENJANG KELAS VIII (A - I)
      { name: 'VIII A', grade: '8', wali: 'Ibu Siti Rahmawati, S.Pd', count: 32, max: 32, room: 'R. 201' },
      { name: 'VIII B', grade: '8', wali: 'Bapak Dedi Suhendar, S.Pd', count: 32, max: 32, room: 'R. 202' },
      { name: 'VIII C', grade: '8', wali: 'Ibu Ceceu Marlina, S.Pd', count: 32, max: 32, room: 'R. 203' },
      { name: 'VIII D', grade: '8', wali: 'Bapak Hendra Gunawan, S.Pd', count: 32, max: 32, room: 'R. 204' },
      { name: 'VIII E', grade: '8', wali: 'Ibu Yuliana Astuti, S.Pd', count: 32, max: 32, room: 'R. 205' },
      { name: 'VIII F', grade: '8', wali: 'Bapak Rudi Hermawan, S.Pd', count: 32, max: 32, room: 'R. 206' },
      { name: 'VIII G', grade: '8', wali: 'Ibu Nenden Kurniasih, S.Pd', count: 32, max: 32, room: 'R. 207' },
      { name: 'VIII H', grade: '8', wali: 'Bapak Wawan Kurniawan, S.Pd', count: 32, max: 32, room: 'R. 208' },
      { name: 'VIII I', grade: '8', wali: 'Ibu Dewi Sartika, S.Pd', count: 32, max: 32, room: 'R. 209' },

      // JENJANG KELAS IX (A - I)
      { name: 'IX A', grade: '9', wali: 'Ibu Dra. Hj. Nur Aini', count: 32, max: 32, room: 'R. 301' },
      { name: 'IX B', grade: '9', wali: 'Ibu Rina Nurhasanah, S.Pd', count: 32, max: 32, room: 'R. 302' },
      { name: 'IX C', grade: '9', wali: 'Bapak Wahyu Hidayat, M.Pd', count: 32, max: 32, room: 'R. 303' },
      { name: 'IX D', grade: '9', wali: 'Ibu Dian Safitri, S.Pd', count: 32, max: 32, room: 'R. 304' },
      { name: 'IX E', grade: '9', wali: 'Bapak Irfan Hakim, S.Pd', count: 32, max: 32, room: 'R. 305' },
      { name: 'IX F', grade: '9', wali: 'Ibu Lilis Suryani, S.Pd', count: 32, max: 32, room: 'R. 306' },
      { name: 'IX G', grade: '9', wali: 'Bapak Bambang Sutrisno, M.Pd', count: 32, max: 32, room: 'R. 307' },
      { name: 'IX H', grade: '9', wali: 'Ibu Nina Marlina, S.Pd', count: 32, max: 32, room: 'R. 308' },
      { name: 'IX I', grade: '9', wali: 'Bapak Arif Rahman, S.Pd', count: 32, max: 32, room: 'R. 309' }
    ];

    // DATA MONITORING HASIL INPUT DARI PORTAL GURU
    let teacherInputsMonitoring = [
      {
        id: 1,
        teacherName: 'Ibu Siti Rahmawati, S.Pd',
        subject: 'Matematika',
        className: 'VIII A',
        grade: '8',
        columnsCount: 3,
        classAvg: 83.4,
        kkm: 75,
        attendanceCount: 18,
        journalCount: 14,
        lastUpdate: 'Hari ini, 10:15 WIB',
        status: 'Lengkap',
        sampleScores: [
          { nisn: '0098427135', name: 'Aditya Pratama', score1: 82, score2: 88, sumatif: 85, avg: 85.0 },
          { nisn: '0098427142', name: 'Alifia Nurul Zahra', score1: 85, score2: 90, sumatif: 88, avg: 87.7 },
          { nisn: '0098427150', name: 'Bayu Arya Putra', score1: 78, score2: 80, sumatif: 82, avg: 80.0 },
          { nisn: '0098427161', name: 'Citra Kirana Dewi', score1: 88, score2: 92, sumatif: 90, avg: 90.0 },
          { nisn: '0098427178', name: 'Daffa Ramadhan', score1: 70, score2: 74, sumatif: 72, avg: 72.0 }
        ]
      },
      {
        id: 2,
        teacherName: 'Bapak Budi Santoso, M.Pd',
        subject: 'Ilmu Pengetahuan Alam (IPA)',
        className: 'VII A',
        grade: '7',
        columnsCount: 3,
        classAvg: 85.1,
        kkm: 75,
        attendanceCount: 16,
        journalCount: 12,
        lastUpdate: 'Hari ini, 09:30 WIB',
        status: 'Lengkap',
        sampleScores: [
          { nisn: '0098427210', name: 'Ilham Pratama', score1: 85, score2: 90, sumatif: 87, avg: 87.3 },
          { nisn: '0098427225', name: 'Jasmine Putri', score1: 80, score2: 85, sumatif: 82, avg: 82.3 }
        ]
      },
      {
        id: 3,
        teacherName: 'Ibu Dra. Hj. Nur Aini',
        subject: 'Bahasa Indonesia',
        className: 'IX A',
        grade: '9',
        columnsCount: 2,
        classAvg: 84.8,
        kkm: 78,
        attendanceCount: 18,
        journalCount: 15,
        lastUpdate: 'Kemarin, 14:20 WIB',
        status: 'Aktif Mengisi',
        sampleScores: [
          { nisn: '0098427230', name: 'Kenzo Alfarizi', score1: 84, score2: 86, sumatif: '-', avg: 85.0 },
          { nisn: '0098427245', name: 'Laila Zahira', score1: 88, score2: 90, sumatif: '-', avg: 89.0 }
        ]
      },
      {
        id: 4,
        teacherName: 'Ibu Rina Nurhasanah, S.Pd',
        subject: 'Bahasa Inggris',
        className: 'IX B',
        grade: '9',
        columnsCount: 3,
        classAvg: 81.5,
        kkm: 75,
        attendanceCount: 17,
        journalCount: 13,
        lastUpdate: 'Kemarin, 11:05 WIB',
        status: 'Lengkap',
        sampleScores: []
      },
      {
        id: 5,
        teacherName: 'Bapak Ahmad Fauzi, S.Kom',
        subject: 'Informatika',
        className: 'VIII B',
        grade: '8',
        columnsCount: 2,
        classAvg: 86.2,
        kkm: 75,
        attendanceCount: 14,
        journalCount: 10,
        lastUpdate: '2 hari lalu',
        status: 'Aktif Mengisi',
        sampleScores: []
      },
      {
        id: 6,
        teacherName: 'Bapak Dedi Suhendar, S.Pd',
        subject: 'Pendidikan Pancasila',
        className: 'VIII A',
        grade: '8',
        columnsCount: 2,
        classAvg: 84.0,
        kkm: 78,
        attendanceCount: 18,
        journalCount: 14,
        lastUpdate: '3 hari lalu',
        status: 'Aktif Mengisi',
        sampleScores: []
      },
      {
        id: 7,
        teacherName: 'Ibu Eni Rohaeni, S.Ag',
        subject: 'Pendidikan Agama & Budi Pekerti',
        className: 'VII B',
        grade: '7',
        columnsCount: 2,
        classAvg: 87.5,
        kkm: 80,
        attendanceCount: 15,
        journalCount: 12,
        lastUpdate: '4 hari lalu',
        status: 'Aktif Mengisi',
        sampleScores: []
      },
      {
        id: 8,
        teacherName: 'Bapak Tatang Sutisna, S.Pd',
        subject: 'Ilmu Pengetahuan Sosial (IPS)',
        className: 'VII C',
        grade: '7',
        columnsCount: 0,
        classAvg: '-',
        kkm: 75,
        attendanceCount: 0,
        journalCount: 0,
        lastUpdate: 'Belum input',
        status: 'Belum Input',
        sampleScores: []
      }
    ];

    // DATA ARSIP SISWA (LULUS / ALUMNI & MUTASI KELUAR)
    let archivedStudents = [
      { id: 101, nisn: '0081234567', name: 'Rizky Pratama Wijaya', lastClass: 'IX A', status: 'Lulus', time: 'Juni 2026', destination: 'SMAN 1 Purwakarta', phone: '0812-9988-1122', year: '2026' },
      { id: 102, nisn: '0081234588', name: 'Nabila Syahrani', lastClass: 'IX B', status: 'Lulus', time: 'Juni 2026', destination: 'SMAN 3 Purwakarta', phone: '0813-8877-2233', year: '2026' },
      { id: 103, nisn: '0081234599', name: 'Fajar Nugraha', lastClass: 'IX C', status: 'Lulus', time: 'Juni 2026', destination: 'SMKN 1 Purwakarta', phone: '0857-7766-3344', year: '2026' },
      { id: 104, nisn: '0091234112', name: 'Dimas Anggoro', lastClass: 'VIII C', status: 'Mutasi Keluar', time: 'Januari 2026', destination: 'Pindah ke SMPN 2 Bandung (Ikut Dinas Orang Tua)', phone: '0819-4455-6677', year: '2026' },
      { id: 105, nisn: '0091234223', name: 'Alya Sabrina', lastClass: 'VII E', status: 'Mutasi Keluar', time: 'Oktober 2025', destination: 'Pindah ke SMP Labschool Jakarta', phone: '0821-3344-5566', year: '2025' },
      { id: 106, nisn: '0071234771', name: 'Bagus Setiawan', lastClass: 'IX A', status: 'Lulus', time: 'Juni 2025', destination: 'SMAN 2 Purwakarta', phone: '0812-1122-3344', year: '2025' },
      { id: 107, nisn: '0071234882', name: 'Dewi Anjani', lastClass: 'IX D', status: 'Lulus', time: 'Juni 2025', destination: 'MAN 1 Purwakarta', phone: '0813-5566-7788', year: '2025' }
    ];

    // DATA ARSIP GURU & STAFF (PENSIUN & MUTASI KELUAR)
    let archivedTeachers = [
      { id: 201, nip: '19660315 199103 1 004', name: 'Drs. H. Suherman, M.Pd', lastSubject: 'Ilmu Pengetahuan Sosial (IPS)', status: 'Pensiun', time: 'Agustus 2026', sk: 'SK Pensiun BKN No. 821.2/045/BKN/2026', year: '2026' },
      { id: 202, nip: '19651120 199002 2 001', name: 'Hj. Ratna Komala, S.Pd', lastSubject: 'Bahasa Sunda', status: 'Pensiun', time: 'Desember 2025', sk: 'SK Pensiun BKN No. 821.2/089/BKN/2025', year: '2025' },
      { id: 203, nip: '19790510 200501 1 007', name: 'Dadan Ramdani, S.Pd, M.M', lastSubject: 'Pendidikan Jasmani (PJOK)', status: 'Mutasi Keluar', time: 'Januari 2026', sk: 'SK Disdik Purwakarta No. 421/112/Disdik/2026 (Promosi Kepala Sekolah SMPN 4 Campaka)', year: '2026' },
      { id: 204, nip: '19841201 201201 2 009', name: 'Nurul Hidayati, S.Pd', lastSubject: 'Bimbingan Konseling (BK)', status: 'Mutasi Keluar', time: 'Juli 2025', sk: 'SK Disdik Purwakarta No. 421/078/Disdik/2025 (Pindah ke SMPN 1 Bungursari)', year: '2025' },
      { id: 205, nip: '19640810 198803 1 002', name: 'Drs. Supardi, M.M', lastSubject: 'Matematika', status: 'Pensiun', time: 'September 2024', sk: 'SK Pensiun BKN No. 821.2/032/BKN/2024', year: '2024' }
    ];

    // ==============================================================
    // ROUTING & HISTORY NAVIGATION (BACK/FORWARD & PERSISTENT REFRESH)
    // ==============================================================
    const ADMIN_TABS = ['overview', 'guru', 'siswa', 'mapel', 'rombel', 'monitoring-guru', 'arsip-siswa', 'arsip-guru', 'settings'];
    const ADMIN_TAB_TITLES = {
      'overview': 'Ringkasan Sistem Akademik',
      'guru': 'Manajemen Guru & Tenaga Pendidik',
      'siswa': 'Manajemen Siswa (Buku Induk)',
      'mapel': 'Mata Pelajaran & Standar KKM',
      'rombel': 'Struktur Kelas & Rombongan Belajar (27 Kelas)',
      'monitoring-guru': 'Pemantauan Hasil Input Portal Guru',
      'arsip-siswa': 'Master Arsip Siswa (Alumni & Mutasi Keluar)',
      'arsip-guru': 'Master Arsip Guru & Staff (Pensiun & Mutasi)',
      'settings': 'Pengaturan Satuan Pendidikan'
    };

    let currentAdminTab = null;

    function getAdminTabFromUrl() {
      const hash = (window.location.hash || '').replace(/^#\/?/, '').trim();
      return ADMIN_TABS.includes(hash) ? hash : null;
    }

    function getSavedAdminTab() {
      try {
        const saved = sessionStorage.getItem('smp_admin_active_tab') || localStorage.getItem('smp_admin_active_tab');
        return ADMIN_TABS.includes(saved) ? saved : null;
      } catch (e) {
        return null;
      }
    }

    function saveAdminTab(tab) {
      try {
        sessionStorage.setItem('smp_admin_active_tab', tab);
        localStorage.setItem('smp_admin_active_tab', tab);
      } catch (e) {}
    }

    function closeAllModals() {
      document.querySelectorAll('[id^="modal-"]').forEach(modal => {
        modal.classList.add('hidden');
      });
      if (typeof closeMobileSidebar === 'function') {
        closeMobileSidebar();
      }
      const menu = document.getElementById('portal-switcher-menu');
      if (menu) menu.classList.add('hidden');
    }

    // TABS CONTROLLER
    function switchAdminTab(tab, updateHistory = true) {
      if (!ADMIN_TABS.includes(tab)) tab = 'overview';

      // If already on this tab
      if (currentAdminTab === tab) {
        if (updateHistory && window.location.hash !== '#' + tab) {
          try {
            history.pushState({ tab: tab }, '', '#' + tab);
          } catch (e) {
            window.location.hash = '#' + tab;
          }
        }
        return;
      }

      currentAdminTab = tab;
      saveAdminTab(tab);

      // Push history state if requested
      if (updateHistory) {
        if (window.location.hash !== '#' + tab) {
          try {
            history.pushState({ tab: tab }, '', '#' + tab);
          } catch (e) {
            window.location.hash = '#' + tab;
          }
        }
      }

      // Close open modals when navigating tabs
      closeAllModals();

      // Show the selected tab section and update sidebar nav button classes
      ADMIN_TABS.forEach(t => {
        const sec = document.getElementById(`tab-${t}`);
        const btn = document.getElementById(`nav-btn-${t}`);
        if (!sec || !btn) return;
        if (t === tab) {
          sec.classList.remove('hidden');
          btn.classList.add('bg-indigo-900', 'text-white', 'shadow-sm', 'font-bold');
          btn.classList.remove('text-indigo-200', 'hover:bg-indigo-900/60');
        } else {
          sec.classList.add('hidden');
          btn.classList.remove('bg-indigo-900', 'text-white', 'shadow-sm', 'font-bold');
          btn.classList.add('text-indigo-200', 'hover:bg-indigo-900/60');
        }
      });

      const topTitle = document.getElementById('topbar-title');
      if (topTitle) topTitle.innerText = ADMIN_TAB_TITLES[tab] || 'Admin Panel';

      document.title = (ADMIN_TAB_TITLES[tab] || 'Admin Panel') + ' - SMPN 7 Purwakarta';

      // Auto close mobile drawer if on small screen
      if (window.innerWidth < 768 && typeof closeMobileSidebar === 'function') {
        closeMobileSidebar();
      }

      window.scrollTo(0, 0);
    }

    // Listen for browser backward/forward navigation (popstate & hashchange)
    window.addEventListener('popstate', function(e) {
      const targetTab = (e.state && e.state.tab) || getAdminTabFromUrl() || 'overview';
      switchAdminTab(targetTab, false);
    });

    window.addEventListener('hashchange', function() {
      const targetTab = getAdminTabFromUrl() || 'overview';
      switchAdminTab(targetTab, false);
    });

    function initAppRouting() {
      const targetTab = getAdminTabFromUrl() || getSavedAdminTab() || 'overview';

      try {
        history.replaceState({ tab: targetTab }, '', '#' + targetTab);
      } catch (e) {
        if (!window.location.hash) {
          window.location.hash = '#' + targetTab;
        }
      }

      switchAdminTab(targetTab, false);
    }

    // INIT APPLICATION
    let isAppInitialized = false;
    function initApp() {
      if (isAppInitialized) return;
      isAppInitialized = true;

      renderTeachersTable();
      renderStudentsTable();
      renderMapelTable();
      renderRombelCards('ALL');
      renderMonitoringGuruTable();
      renderArchivedStudentsTable();
      renderArchivedTeachersTable();

      initAppRouting();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initApp);
    } else {
      initApp();
    }
    window.addEventListener('load', initApp);

    // ==============================================================
    // LOGIC TAB GURU (MYSQL TERPADU)
    // ==============================================================
    function renderTeachersTable() {
      const search = document.getElementById('filter-guru-search')?.value.toLowerCase() || '';
      const mapelFilter = document.getElementById('filter-guru-mapel')?.value || 'ALL';
      const waliFilter = document.getElementById('filter-guru-wali')?.value || 'ALL';
      const tbody = document.getElementById('teacher-table-body');
      if (!tbody) return;
      tbody.innerHTML = '';

      const filtered = teachersData.filter(g => {
        const q = search.toLowerCase();
        const matchSearch = !q || 
          (g.name && g.name.toLowerCase().includes(q)) || 
          (g.nip && g.nip.toLowerCase().includes(q)) ||
          (g.kode && String(g.kode).includes(q)) ||
          (g.mapel && g.mapel.toLowerCase().includes(q)) ||
          (g.wali && g.wali.toLowerCase().includes(q));

        const matchMapel = (mapelFilter === 'ALL') || 
          (g.mapel && (g.mapel === mapelFilter || g.mapel.toLowerCase().includes(mapelFilter.toLowerCase())));

        const isWali = g.wali && g.wali.includes('Wali Kelas');
        const matchWali = (waliFilter === 'ALL') || 
          (waliFilter === 'WALI' && isWali) || 
          (waliFilter === 'NON_WALI' && !isWali);

        return matchSearch && matchMapel && matchWali;
      });

      filtered.forEach((g, idx) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50/80 transition-colors';
        const isKepsek = g.wali && g.wali.includes('Kepala Sekolah');
        const isWali = g.wali && g.wali.includes('Wali Kelas');

        row.innerHTML = `
          <td class="py-3 px-4 text-center font-bold text-slate-400">${idx + 1}</td>
          <td class="py-3 px-4">
            <p class="font-bold text-slate-900 text-xs">${g.name}</p>
            <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
              <span class="inline-block bg-slate-100 text-indigo-900 font-semibold px-1.5 py-0.5 rounded text-[10px]">Kd: ${g.kode || g.id}</span>
              <span class="text-slate-500 font-mono text-[11px]">NIP. ${g.nip}</span>
              <span class="inline-block bg-amber-50 text-amber-800 font-mono font-semibold px-1.5 py-0.5 rounded text-[10px] border border-amber-200">Sandi: ${g.password || 'guru12345'}</span>
            </div>
          </td>
          <td class="py-3 px-4">
            <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-800 font-semibold text-[11px] border border-indigo-100">
              ${g.mapel}
            </span>
            ${g.kelas && g.kelas !== '-' ? `<p class="text-[11px] text-slate-500 font-medium mt-1">Kelas: <span class="text-slate-700 font-semibold">${g.kelas}</span></p>` : ''}
          </td>
          <td class="py-3 px-4">
            <span class="font-medium inline-block text-[11px] ${isKepsek ? 'text-indigo-800 font-bold bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200' : (isWali ? 'text-amber-800 font-bold bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200' : 'text-slate-500')}">
              ${g.wali}
            </span>
          </td>
          <td class="py-3 px-4 text-center">
            <span class="text-xs font-bold text-slate-800">${g.phone ? g.phone : '-'}</span>
          </td>
          <td class="py-3 px-4 text-center">
            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> ${g.status || 'Aktif'}
            </span>
          </td>
          <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
            <button onclick="editTeacher('${g.kode || g.id}')" class="px-2.5 py-1 text-indigo-600 hover:text-indigo-900 rounded-lg hover:bg-indigo-50 font-bold transition-colors" title="Edit Guru & Sandi">Edit</button>
            <button onclick="deleteTeacher('${g.kode || g.id}')" class="px-2.5 py-1 text-rose-600 hover:text-rose-900 rounded-lg hover:bg-rose-50 font-bold transition-colors" title="Hapus Guru">Hapus</button>
          </td>
        `;
        tbody.appendChild(row);
      });

      const countLabel = document.getElementById('teacher-count-label');
      if (countLabel) countLabel.innerText = `Menampilkan ${filtered.length} dari total ${teachersData.length} guru`;
      const sideCount = document.getElementById('sidebar-count-guru');
      if (sideCount) sideCount.innerText = teachersData.length;
      const statTotal = document.getElementById('stat-total-guru');
      if (statTotal) statTotal.innerText = teachersData.length;
    }

    function openAddTeacherModal() {
      document.getElementById('modal-guru-title').innerText = 'Tambah Guru Baru';
      document.getElementById('input-guru-id').value = '';
      document.getElementById('input-guru-name').value = '';
      document.getElementById('input-guru-nip').value = '';
      document.getElementById('input-guru-mapel').value = '';
      document.getElementById('input-guru-wali').value = 'Bukan Wali Kelas';
      document.getElementById('input-guru-kelas').value = '';
      document.getElementById('input-guru-jam').value = '4';
      document.getElementById('input-guru-phone').value = '';
      document.getElementById('input-guru-pass').value = 'guru12345';
      if (document.getElementById('input-guru-status')) document.getElementById('input-guru-status').value = 'Aktif';
      document.getElementById('modal-guru').classList.remove('hidden');
    }

    function closeModalGuru() {
      document.getElementById('modal-guru').classList.add('hidden');
    }

    async function handleSaveTeacher(e) {
      e.preventDefault();
      const id = document.getElementById('input-guru-id').value;
      const name = document.getElementById('input-guru-name').value;
      const nip = document.getElementById('input-guru-nip').value;
      const mapel = document.getElementById('input-guru-mapel').value;
      const wali = document.getElementById('input-guru-wali').value || 'Bukan Wali Kelas';
      const kelas = document.getElementById('input-guru-kelas').value || '-';
      const jam = document.getElementById('input-guru-jam').value || '0';
      const phone = document.getElementById('input-guru-phone').value || '0812-xxxx-xxxx';
      const status = document.getElementById('input-guru-status')?.value || 'Aktif';
      const password = document.getElementById('input-guru-pass').value || 'guru12345';

      const formData = new FormData();
      formData.append('action', id ? 'edit_teacher' : 'add_teacher');
      formData.append('id', id);
      formData.append('name', name);
      formData.append('nip', nip);
      formData.append('mapel', mapel);
      formData.append('wali', wali);
      formData.append('kelas', kelas);
      formData.append('jam_per_minggu', jam);
      formData.append('phone', phone);
      formData.append('status', status);
      formData.append('password', password);

      const btnSave = document.getElementById('btn-save-teacher');
      if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerText = 'Menyimpan...';
      }

      try {
        const resp = await fetch('admin.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
          if (!id) {
            teachersData.push({
              id: String(teachersData.length + 1),
              kode: String(teachersData.length + 1),
              name: name,
              nip: nip,
              mapel: mapel,
              wali: wali,
              kelas: kelas,
              jam_per_minggu: jam,
              phone: phone,
              status: status,
              password: password
            });
          } else {
            const idx = teachersData.findIndex(x => String(x.id) === String(id) || String(x.kode) === String(id));
            if (idx !== -1) {
              teachersData[idx] = {
                ...teachersData[idx],
                name: name,
                nip: nip,
                mapel: mapel,
                wali: wali,
                kelas: kelas,
                jam_per_minggu: jam,
                phone: phone,
                status: status,
                password: password
              };
            }
          }
          closeModalGuru();
          renderTeachersTable();
          showToast('Berhasil Disimpan', res.message);
        } else {
          alert('Gagal menyimpan: ' + res.message);
        }
      } catch (err) {
        // Fallback local memory update
        closeModalGuru();
        renderTeachersTable();
        showToast('Tersimpan Lokal', `${name} berhasil diperbarui di memori browser.`);
      } finally {
        if (btnSave) {
          btnSave.disabled = false;
          btnSave.innerText = 'Simpan ke Database';
        }
      }
    }

    function editTeacher(id) {
      const g = teachersData.find(x => String(x.id) === String(id) || String(x.kode) === String(id));
      if (!g) return;
      document.getElementById('modal-guru-title').innerText = 'Edit Data Guru (Kode: ' + (g.kode || g.id) + ')';
      document.getElementById('input-guru-id').value = g.kode || g.id;
      document.getElementById('input-guru-name').value = g.name || '';
      document.getElementById('input-guru-nip').value = g.nip || '';
      document.getElementById('input-guru-mapel').value = g.mapel || '';
      document.getElementById('input-guru-wali').value = g.wali || 'Bukan Wali Kelas';
      document.getElementById('input-guru-kelas').value = g.kelas || '';
      document.getElementById('input-guru-jam').value = g.jam_per_minggu || '';
      document.getElementById('input-guru-phone').value = g.phone || '';
      document.getElementById('input-guru-pass').value = g.password || 'guru12345';
      if (document.getElementById('input-guru-status')) {
        document.getElementById('input-guru-status').value = g.status || 'Aktif';
      }
      document.getElementById('modal-guru').classList.remove('hidden');
    }

    async function deleteTeacher(id) {
      const g = teachersData.find(x => String(x.id) === String(id) || String(x.kode) === String(id));
      const nama = g ? g.name : `guru kode ${id}`;
      if (!confirm(`Yakin ingin menghapus ${nama} dari database MySQL? Data yang dihapus tidak dapat dipulihkan.`)) {
        return;
      }

      const formData = new FormData();
      formData.append('action', 'delete_teacher');
      formData.append('id', id);

      try {
        const resp = await fetch('admin.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
          teachersData = teachersData.filter(x => String(x.id) !== String(id) && String(x.kode) !== String(id));
          renderTeachersTable();
          showToast('Guru Dihapus', res.message);
        } else {
          alert('Gagal menghapus: ' + res.message);
        }
      } catch (err) {
        teachersData = teachersData.filter(x => String(x.id) !== String(id) && String(x.kode) !== String(id));
        renderTeachersTable();
        showToast('Guru Dihapus', 'Data dihapus dari memori halaman.');
      }
    }

    function exportTeachersCSV() {
      let csv = 'No,Nama Lengkap,NIP,Mata Pelajaran,Wali Kelas,Telepon,Status\n';
      teachersData.forEach((g, i) => {
        csv += `${i + 1},"${g.name}","${g.nip}","${g.mapel}","${g.wali}","${g.phone}","${g.status}"\n`;
      });
      downloadFile(csv, 'Data_Guru_SMPN7_Purwakarta.csv', 'text/csv');
      showToast('Download Berhasil', 'File Data_Guru_SMPN7_Purwakarta.csv berhasil diunduh.');
    }

    // UPLOAD GURU & STAFF MASSAL
    function openUploadTeacherModal() {
      document.getElementById('modal-upload-guru').classList.remove('hidden');
    }

    function closeModalUploadGuru() {
      document.getElementById('modal-upload-guru').classList.add('hidden');
    }

    function downloadTeacherCSVTemplate() {
      const template = 'NIP,NAMA_LENGKAP,MATA_PELAJARAN,TUGAS_TAMBAHAN_WALI,NO_TELEPON,STATUS\n19850101 201001 1 001,Nama Guru Contoh,Matematika,Wali Kelas VII A,08123456789,Aktif\n';
      downloadFile(template, 'Template_Upload_Guru_SMPN7.csv', 'text/csv');
    }

    function simulateUploadTeachers() {
      closeModalUploadGuru();
      // Add mock teachers to teachersData
      const nextId = teachersData.length + 1;
      teachersData.push({
        id: nextId,
        name: 'Drs. Hendro Wibowo, M.Pd',
        nip: '19790615 200501 1 009',
        mapel: 'Ilmu Pengetahuan Sosial (IPS)',
        wali: 'Wali Kelas VII C',
        phone: '0812-4455-8899',
        status: 'Aktif'
      });
      teachersData.push({
        id: nextId + 1,
        name: 'Ratih Pratiwi, S.Pd',
        nip: '19920310 201802 2 005',
        mapel: 'Bahasa Indonesia',
        wali: 'Wali Kelas VII F',
        phone: '0857-1133-7799',
        status: 'Aktif'
      });
      renderTeachersTable();
      showToast('Upload Selesai', '2 data pendidik dari berkas Excel/CSV berhasil diupload ke sistem.');
    }


    // ==============================================================
    // LOGIC TAB SISWA (EDIT SISWA & SELECT ALL / BULK GROUPING)
    // ==============================================================
    let selectedStudentIds = new Set();
    let currentFilteredStudentIds = [];

   function renderStudentsTable() {
      const search = document.getElementById('filter-siswa-search')?.value.toLowerCase() || '';
      const jenjang = document.getElementById('filter-siswa-jenjang')?.value || 'ALL';
      const rombel = document.getElementById('filter-siswa-rombel')?.value || 'ALL';
      const status = document.getElementById('filter-siswa-status')?.value || 'ALL';
      const tbody = document.getElementById('student-table-body');
      tbody.innerHTML = '';

      const filtered = studentsData.filter(s => {
        const matchSearch = s.name.toLowerCase().includes(search) || s.nisn.includes(search);
        const matchJenjang = (jenjang === 'ALL') || 
          (jenjang === '7' && s.class.startsWith('VII')) || 
          (jenjang === '8' && s.class.startsWith('VIII')) || 
          (jenjang === '9' && s.class.startsWith('IX'));
        const matchRombel = (rombel === 'ALL') || (s.class === rombel);
        const matchStatus = (status === 'ALL') || (s.status === status);
        return matchSearch && matchJenjang && matchRombel && matchStatus;
      });

      currentFilteredStudentIds = filtered.map(s => s.id);

      filtered.forEach((s, idx) => {
        const isChecked = selectedStudentIds.has(s.id);
        let statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif Belajar</span>';
        if (s.status === 'Lulus') {
          statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">Alumni / Lulus</span>';
        } else if (s.status === 'Mutasi') {
          statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">Mutasi / Pindah</span>';
        }

        const row = document.createElement('tr');
        row.className = isChecked ? 'bg-indigo-50/60 transition-colors' : 'hover:bg-slate-50/80 transition-colors';
        
        // Kunci utamanya ada di TANDA KUTIP '${s.id}' karena sekarang ID adalah teks Nama Siswa
        row.innerHTML = `
          <td class="py-3 px-3 text-center">
            <input type="checkbox" class="student-row-checkbox w-4 h-4 rounded border-slate-300 text-indigo-950 focus:ring-indigo-600 cursor-pointer" value="${s.id}" ${isChecked ? 'checked' : ''} onchange="handleStudentRowCheck('${s.id}', this.checked)">
          </td>
          <td class="py-3 px-4 text-center font-bold text-slate-400">${idx + 1}</td>
          <td class="py-3 px-4 font-mono font-bold text-slate-800">${s.nisn}</td>
          <td class="py-3 px-4 font-bold text-indigo-950">${s.name}</td>
          <td class="py-3 px-4 text-center">
            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${s.gender === 'L' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'}">${s.gender}</span>
          </td>
          <td class="py-3 px-4 font-bold text-slate-700">
            <span class="px-2 py-1 rounded-lg bg-slate-100 border border-slate-200">${s.class}</span>
          </td>
          <td class="py-3 px-4 text-slate-600">${s.parent}</td>
          <td class="py-3 px-4 font-mono text-slate-500 text-[11px]">${s.phone}</td>
          <td class="py-3 px-4 text-center">${statusBadge}</td>
          <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
            <button onclick="editStudent('${s.id}')" class="p-1 text-indigo-600 hover:text-indigo-900 rounded-lg hover:bg-indigo-50 font-bold" title="Edit Data Siswa">Edit</button>
            <button onclick="deleteStudent('${s.id}')" class="p-1 text-rose-600 hover:text-rose-900 rounded-lg hover:bg-rose-50 font-bold" title="Hapus Siswa">Hapus</button>
          </td>
        `;
        tbody.appendChild(row);
      });

      document.getElementById('student-count-label').innerText = `Menampilkan ${filtered.length} dari ${studentsData.length} siswa`;
      document.getElementById('sidebar-count-siswa').innerText = studentsData.length;
      document.getElementById('stat-total-siswa').innerText = studentsData.filter(x => x.status === 'Aktif Belajar' || x.status === 'Aktif').length;

      updateBulkToolbar();
    }
    // SELECT ALL & BULK SELECTION LOGIC
    function toggleSelectAllStudents(checked) {
      if (checked) {
        currentFilteredStudentIds.forEach(id => selectedStudentIds.add(id));
      } else {
        currentFilteredStudentIds.forEach(id => selectedStudentIds.delete(id));
      }
      renderStudentsTable();
    }

    function handleStudentRowCheck(id, isChecked) {
      if (isChecked) {
        selectedStudentIds.add(id);
      } else {
        selectedStudentIds.delete(id);
      }
      updateBulkToolbar();

      // Update row visual background
      const checkboxes = document.querySelectorAll('.student-row-checkbox');
      checkboxes.forEach(cb => {
        const row = cb.closest('tr');
        if (cb.checked) {
          row.classList.add('bg-indigo-50/60');
        } else {
          row.classList.remove('bg-indigo-50/60');
        }
      });
    }

    function updateBulkToolbar() {
      const count = selectedStudentIds.size;
      const toolbar = document.getElementById('bulk-student-toolbar');
      const countBadge = document.getElementById('bulk-selected-count');
      const checkAll = document.getElementById('check-all-students');

      if (countBadge) countBadge.innerText = count;

      if (toolbar) {
        if (count > 0) {
          toolbar.classList.remove('hidden');
        } else {
          toolbar.classList.add('hidden');
        }
      }

      if (checkAll) {
        if (currentFilteredStudentIds.length > 0 && currentFilteredStudentIds.every(id => selectedStudentIds.has(id))) {
          checkAll.checked = true;
        } else {
          checkAll.checked = false;
        }
      }
    }

    function clearAllStudentSelection() {
      selectedStudentIds.clear();
      renderStudentsTable();
    }

    function applyBulkClassMove() {
      if (selectedStudentIds.size === 0) {
        alert('Pilih siswa terlebih dahulu menggunakan kotak centang.');
        return;
      }
      const targetClass = document.getElementById('bulk-target-class').value;
      const count = selectedStudentIds.size;

      studentsData.forEach(s => {
        if (selectedStudentIds.has(s.id)) {
          s.class = targetClass;
        }
      });

      selectedStudentIds.clear();
      renderStudentsTable();
      showToast('Pengelompokan Berhasil', `${count} siswa berhasil dipindahkan ke ${targetClass}.`);
    }

    function applyBulkStatusChange() {
      if (selectedStudentIds.size === 0) {
        alert('Pilih siswa terlebih dahulu menggunakan kotak centang.');
        return;
      }
      const targetStatus = document.getElementById('bulk-target-status').value;
      const count = selectedStudentIds.size;

      studentsData.forEach(s => {
        if (selectedStudentIds.has(s.id)) {
          s.status = targetStatus;
        }
      });

      selectedStudentIds.clear();
      renderStudentsTable();
      const statusLabel = targetStatus === 'Lulus' ? 'Alumni / Lulus' : targetStatus === 'Aktif' ? 'Aktif Belajar' : 'Mutasi / Pindah';
      showToast('Status Massal Diperbarui', `Status ${count} siswa berhasil diubah menjadi ${statusLabel}.`);
    }

    function handleJenjangChange() {
      const j = document.getElementById('filter-siswa-jenjang').value;
      const rombelSelect = document.getElementById('filter-siswa-rombel');
      rombelSelect.innerHTML = '<option value="ALL">Semua Rombel</option>';

      if (j === '7' || j === 'ALL') {
        ['VII A', 'VII B', 'VII C', 'VII D', 'VII E', 'VII F', 'VII G', 'VII H', 'VII I'].forEach(r => {
          rombelSelect.innerHTML += `<option value="${r}">${r}</option>`;
        });
      }
      if (j === '8' || j === 'ALL') {
        ['VIII A', 'VIII B', 'VIII C', 'VIII D', 'VIII E', 'VIII F', 'VIII G', 'VIII H', 'VIII I'].forEach(r => {
          rombelSelect.innerHTML += `<option value="${r}">${r}</option>`;
        });
      }
      if (j === '9' || j === 'ALL') {
        ['IX A', 'IX B', 'IX C', 'IX D', 'IX E', 'IX F', 'IX G', 'IX H', 'IX I'].forEach(r => {
          rombelSelect.innerHTML += `<option value="${r}">${r}</option>`;
        });
      }
      renderStudentsTable();
    }

    function openAddStudentModal() {
      document.getElementById('input-siswa-id').value = '';
      document.getElementById('modal-siswa-title').innerText = 'Tambah Siswa Baru';
      document.getElementById('modal-siswa-desc').innerText = 'Masukkan data peserta didik ke buku induk sekolah.';
      document.getElementById('modal-siswa-submit-text').innerText = 'Daftarkan Siswa';
      document.getElementById('input-siswa-name').value = '';
      document.getElementById('input-siswa-nisn').value = '';
      document.getElementById('input-siswa-gender').value = 'L';
      document.getElementById('input-siswa-class').value = 'VIII A';
      document.getElementById('input-siswa-status').value = 'Aktif';
      document.getElementById('input-siswa-parent').value = '';
      document.getElementById('input-siswa-phone').value = '';
      document.getElementById('modal-siswa').classList.remove('hidden');
    }

    function editStudent(id) {
      const s = studentsData.find(x => x.id === id);
      if (!s) return;
      document.getElementById('input-siswa-id').value = s.id;
      document.getElementById('modal-siswa-title').innerText = 'Edit Data Siswa';
      document.getElementById('modal-siswa-desc').innerText = `Perbarui data induk untuk ${s.name} (${s.nisn}).`;
      document.getElementById('modal-siswa-submit-text').innerText = 'Simpan Perubahan Siswa';
      document.getElementById('input-siswa-name').value = s.name;
      document.getElementById('input-siswa-nisn').value = s.nisn;
      document.getElementById('input-siswa-gender').value = s.gender;
      document.getElementById('input-siswa-class').value = s.class;
      document.getElementById('input-siswa-status').value = s.status || 'Aktif';
      document.getElementById('input-siswa-parent').value = s.parent;
      document.getElementById('input-siswa-phone').value = s.phone;
      document.getElementById('modal-siswa').classList.remove('hidden');
    }

    function closeModalSiswa() {
      document.getElementById('modal-siswa').classList.add('hidden');
    }

   // 1. SIMPAN ATAU EDIT SISWA KE MYSQL
    function handleSaveStudent(e) {
      e.preventDefault();
      
      const editId = document.getElementById('input-siswa-id').value;
      const nisn = document.getElementById('input-siswa-nisn').value.trim();
      const name = document.getElementById('input-siswa-name').value.trim();
      const gender = document.getElementById('input-siswa-gender').value;
      const rombel = document.getElementById('input-siswa-class').value;
      const status = document.getElementById('input-siswa-status').value;
      const parent = document.getElementById('input-siswa-parent').value.trim() || '-';
      const phone = document.getElementById('input-siswa-phone').value.trim() || '-';

      const formData = new URLSearchParams();
      formData.append('action', editId ? 'edit_student' : 'add_student');
      formData.append('id', editId);
      formData.append('nisn', nisn);
      formData.append('name', name);
      formData.append('gender', gender);
      formData.append('class', rombel);
      formData.append('status', status);
      formData.append('parent', parent);
      formData.append('phone', phone);

      fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast('Berhasil', data.message);
          closeModalSiswa();
          setTimeout(() => { window.location.reload(); }, 800);
        } else {
          alert('Gagal: ' + data.message);
        }
      })
      .catch(err => {
        console.error(err);
        alert('Terjadi kesalahan koneksi.');
      });
    }

    // 2. HAPUS SISWA DARI MYSQL
    function deleteStudent(id) {
      const s = studentsData.find(x => x.id === id || x.nisn === id);
      const studentName = s ? s.name : 'siswa ini';
      
      if (confirm(`Yakin ingin menghapus ${studentName} dari database SQL?`)) {
        const formData = new URLSearchParams();
        formData.append('action', 'delete_student');
        formData.append('id', id);

        fetch('admin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showToast('Terhapus', data.message);
            setTimeout(() => { window.location.reload(); }, 800);
          } else {
            alert('Gagal menghapus: ' + data.message);
          }
        })
        .catch(err => {
          console.error(err);
          alert('Terjadi kesalahan koneksi.');
        });
      }
    }
    function openImportModal() {
      document.getElementById('modal-import').classList.remove('hidden');
    }

    function closeModalImport() {
      document.getElementById('modal-import').classList.add('hidden');
    }

    function simulateImport() {
      closeModalImport();
      showToast('Upload Selesai', '32 data siswa dari berkas Excel/CSV berhasil diupload ke sistem.');
    }

    function downloadCSVTemplate() {
      const template = 'NISN,NAMA_LENGKAP,JENIS_KELAMIN,ROMBEL,NAMA_ORANG_TUA,NO_TELEPON\n0098427999,Nama Contoh,L,VIII A,Orang Tua Contoh,08123456789\n';
      downloadFile(template, 'Template_Upload_Siswa_SMPN7.csv', 'text/csv');
    }

    function exportStudentsCSV() {
      let csv = 'No,NISN,Nama Siswa,JK,Kelas,Nama Wali,No HP,Status\n';
      studentsData.forEach((s, i) => {
        csv += `${i + 1},"${s.nisn}","${s.name}","${s.gender}","${s.class}","${s.parent}","${s.phone}","${s.status}"\n`;
      });
      downloadFile(csv, 'Data_Siswa_SMPN7_Purwakarta.csv', 'text/csv');
      showToast('Download Berhasil', 'File Data_Siswa_SMPN7_Purwakarta.csv berhasil diunduh.');
    }


    // ==============================================================
    // LOGIC TAB MATA PELAJARAN (FORMAT MANUAL MODAL ADMIN)
    // ==============================================================
    function renderMapelTable() {
      const tbody = document.getElementById('mapel-table-body');
      tbody.innerHTML = '';

      mapelData.forEach((m, idx) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50/80 transition-colors';
        row.innerHTML = `
          <td class="py-3 px-4 font-mono font-bold text-slate-500 text-center">${m.code}</td>
          <td class="py-3 px-4 font-bold text-slate-900">${m.name}</td>
          <td class="py-3 px-4">
            <span class="text-[11px] font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded">${m.group}</span>
          </td>
          <td class="py-3 px-4 text-center font-bold text-slate-700">${m.hours} JP</td>
          <td class="py-3 px-4 text-center">
            <span class="px-2.5 py-1 rounded-lg font-black text-amber-900 bg-amber-100 border border-amber-200">
              ${m.kkm}
            </span>
          </td>
          <td class="py-3 px-4 text-slate-700 font-semibold">${m.lead}</td>
          <td class="py-3 px-4 text-center text-slate-600">${m.rombelCovered} Kelas</td>
          <td class="py-3 px-4 text-right space-x-1 whitespace-nowrap">
            <button onclick="editMapel('${m.code}')" class="p-1 text-indigo-600 hover:text-indigo-900 font-bold hover:bg-indigo-50 rounded" title="Edit Rincian Mapel">Edit</button>
            <button onclick="deleteMapel('${m.code}')" class="p-1 text-rose-600 hover:text-rose-900 font-bold hover:bg-rose-50 rounded" title="Hapus Mapel">Hapus</button>
          </td>
        `;
        tbody.appendChild(row);
      });

      document.getElementById('sidebar-count-mapel').innerText = mapelData.length;
      document.getElementById('stat-total-mapel').innerText = mapelData.length;
    }

    // Buka Modal Tambah Mapel Manual
    function openAddMapelModal() {
      const nextNum = mapelData.length + 1;
      const suggestedCode = `MP-${nextNum < 10 ? '0' : ''}${nextNum}`;

      document.getElementById('input-mapel-orig-code').value = '';
      document.getElementById('modal-mapel-title').innerText = 'Tambah Mata Pelajaran Baru';
      document.getElementById('modal-mapel-desc').innerText = 'Isi rincian kurikulum mapel secara manual oleh Administrator.';
      document.getElementById('modal-mapel-submit-text').innerText = 'Simpan Mata Pelajaran';

      document.getElementById('input-mapel-code').value = suggestedCode;
      document.getElementById('input-mapel-name').value = '';
      document.getElementById('input-mapel-group').value = 'Kelompok B (Pengembangan)';
      document.getElementById('input-mapel-hours').value = 2;
      document.getElementById('input-mapel-kkm').value = 75;
      document.getElementById('input-mapel-lead').value = '';
      document.getElementById('input-mapel-rombel-count').value = '15';

      document.getElementById('modal-mapel').classList.remove('hidden');
    }

    // Buka Modal Edit Mapel Manual
    function editMapel(code) {
      const m = mapelData.find(x => x.code === code);
      if (!m) return;

      document.getElementById('input-mapel-orig-code').value = m.code;
      document.getElementById('modal-mapel-title').innerText = 'Edit Mata Pelajaran';
      document.getElementById('modal-mapel-desc').innerText = `Perbarui rincian kurikulum untuk ${m.name} (${m.code}).`;
      document.getElementById('modal-mapel-submit-text').innerText = 'Simpan Perubahan Mapel';

      document.getElementById('input-mapel-code').value = m.code;
      document.getElementById('input-mapel-name').value = m.name;
      document.getElementById('input-mapel-group').value = m.group;
      document.getElementById('input-mapel-hours').value = m.hours;
      document.getElementById('input-mapel-kkm').value = m.kkm;
      document.getElementById('input-mapel-lead').value = m.lead;
      document.getElementById('input-mapel-rombel-count').value = String(m.rombelCovered);

      document.getElementById('modal-mapel').classList.remove('hidden');
    }

    function closeModalMapel() {
      document.getElementById('modal-mapel').classList.add('hidden');
    }

    function handleSaveMapel(e) {
      e.preventDefault();
      const origCode = document.getElementById('input-mapel-orig-code').value;
      const code = document.getElementById('input-mapel-code').value.trim();
      const name = document.getElementById('input-mapel-name').value.trim();
      const group = document.getElementById('input-mapel-group').value;
      const hours = parseInt(document.getElementById('input-mapel-hours').value) || 2;
      const kkm = parseInt(document.getElementById('input-mapel-kkm').value) || 75;
      const lead = document.getElementById('input-mapel-lead').value.trim() || 'Belum Ditugaskan';
      const rombelCount = parseInt(document.getElementById('input-mapel-rombel-count').value) || 15;

      if (origCode) {
        // Edit existing mapel
        const m = mapelData.find(x => x.code === origCode);
        if (m) {
          m.code = code;
          m.name = name;
          m.group = group;
          m.hours = hours;
          m.kkm = kkm;
          m.lead = lead;
          m.rombelCovered = rombelCount;
          showToast('Mata Pelajaran Diperbarui', `Kurikulum ${name} (${code}) berhasil diperbarui.`);
        }
      } else {
        // Check duplicate code
        if (mapelData.some(x => x.code === code)) {
          alert(`Kode mapel ${code} sudah ada. Silakan gunakan kode lain.`);
          return;
        }
        mapelData.push({
          code: code,
          name: name,
          group: group,
          hours: hours,
          kkm: kkm,
          lead: lead,
          rombelCovered: rombelCount
        });
        showToast('Mata Pelajaran Ditambahkan', `Mata pelajaran ${name} (${code}) berhasil ditambahkan ke kurikulum.`);
      }

      closeModalMapel();
      renderMapelTable();
    }

    function deleteMapel(code) {
      const m = mapelData.find(x => x.code === code);
      const mapelName = m ? m.name : code;
      if (confirm(`Hapus mata pelajaran ${mapelName} dari kurikulum?`)) {
        mapelData = mapelData.filter(x => x.code !== code);
        renderMapelTable();
        showToast('Mata Pelajaran Dihapus', `${mapelName} telah dihapus dari daftar mata pelajaran.`);
      }
    }


    // ==============================================================
    // LOGIC TAB ROMBEL CARDS (27 KELAS: 7A-I, 8A-I, 9A-I)
    // ==============================================================
    let currentDetailRombel = 'VIII A';

    function renderRombelCards(gradeFilter) {
      const grid = document.getElementById('rombel-cards-grid');
      if (!grid) return;
      grid.innerHTML = '';

      ['all', '7', '8', '9'].forEach(g => {
        const btn = document.getElementById(`btn-rf-${g}`);
        if (btn) {
          if (g === gradeFilter.toLowerCase()) {
            btn.className = 'px-3 py-1 rounded-lg bg-white shadow-xs text-indigo-950 font-bold';
          } else {
            btn.className = 'px-3 py-1 rounded-lg text-slate-600 hover:text-slate-900 font-semibold';
          }
        }
      });

      const filtered = rombelList.filter(r => (gradeFilter === 'ALL') || (r.grade === gradeFilter));

      filtered.forEach(r => {
        const card = document.createElement('div');
        card.className = 'bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-indigo-500 hover:shadow-md cursor-pointer transition-all group';
        card.onclick = (e) => {
          if (e.target.closest('button')) return;
          openDetailRombelModal(r.name);
        };

        card.innerHTML = `
          <div>
            <div class="flex items-center justify-between mb-3">
              <span class="w-10 h-10 rounded-xl bg-indigo-950 text-amber-300 font-extrabold flex items-center justify-center text-sm shadow-sm group-hover:scale-105 transition-transform">
                ${r.name}
              </span>
              <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">${r.room}</span>
            </div>
            <div class="flex items-center justify-between">
              <h4 class="font-black text-slate-900 text-sm group-hover:text-indigo-950">Kelas ${r.name}</h4>
              <span class="text-[10px] text-indigo-600 font-bold group-hover:underline">Pantau Kelas &rarr;</span>
            </div>
            <p class="text-slate-500 text-[11px] mt-1.5 flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              <span class="truncate">Wali: <strong>${r.wali}</strong></span>
            </p>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
            <span class="font-semibold text-slate-600">${r.count} / ${r.max} Siswa</span>
            <div class="flex items-center gap-2">
              <button onclick="changeWaliKelas('${r.name}')" class="text-slate-400 hover:text-indigo-600 p-1 rounded font-semibold text-[10px] hover:bg-slate-50" title="Ubah Wali Kelas">
                Ganti Wali
              </button>
              <button onclick="openDetailRombelModal('${r.name}')" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 font-bold rounded-lg text-[10px] transition-colors">
                Buka
              </button>
            </div>
          </div>
        `;
        grid.appendChild(card);
      });
    }

    function filterRombelCards(grade) {
      renderRombelCards(grade);
    }

    function changeWaliKelas(rombelName) {
      const r = rombelList.find(x => x.name === rombelName);
      if (!r) return;
      const newWali = prompt(`Masukkan Nama Wali Kelas Baru untuk ${rombelName}:`, r.wali);
      if (newWali) {
        r.wali = newWali;
        renderRombelCards('ALL');
        showToast('Wali Kelas Diperbarui', `Wali Kelas ${rombelName} berhasil diubah.`);
      }
    }

    // ==============================================================
    // MODAL DETAIL & PEMANTAUAN ROMBEL (KLIK KELAS)
    // ==============================================================
    function openDetailRombelModal(rombelName) {
      currentDetailRombel = rombelName;
      const r = rombelList.find(x => x.name === rombelName) || { name: rombelName, wali: 'Wali Kelas', room: 'R. 101', count: 32, max: 32 };
      
      document.getElementById('detail-rombel-badge').innerText = rombelName;
      document.getElementById('detail-rombel-title').innerText = `Pemantauan Kelas ${rombelName}`;
      document.getElementById('detail-rombel-sub').innerText = `Wali Kelas: ${r.wali} · Ruang: ${r.room} · ${r.count} / ${r.max} Siswa Aktif`;

      renderDetailRombelMapel(rombelName);
      renderDetailRombelSiswa(rombelName);
      switchRombelDetailTab('mapel');

      document.getElementById('modal-detail-rombel').classList.remove('hidden');
    }

    function closeDetailRombelModal() {
      document.getElementById('modal-detail-rombel').classList.add('hidden');
    }

    function switchRombelDetailTab(tab) {
      const isMapel = tab === 'mapel';
      document.getElementById('sub-view-rombel-mapel').classList.toggle('hidden', !isMapel);
      document.getElementById('sub-view-rombel-siswa').classList.toggle('hidden', isMapel);

      const btnMapel = document.getElementById('btn-sub-rombel-mapel');
      const btnSiswa = document.getElementById('btn-sub-rombel-siswa');

      if (isMapel) {
        btnMapel.className = 'px-4 py-1.5 rounded-lg bg-indigo-950 text-white shadow-xs font-bold';
        btnSiswa.className = 'px-4 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 font-semibold';
      } else {
        btnSiswa.className = 'px-4 py-1.5 rounded-lg bg-indigo-950 text-white shadow-xs font-bold';
        btnMapel.className = 'px-4 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 font-semibold';
      }
    }

    function renderDetailRombelMapel(rombelName) {
      const tbody = document.getElementById('detail-rombel-mapel-tbody');
      tbody.innerHTML = '';

      mapelData.forEach((m, idx) => {
        // Sample realistic calculation based on rombel
        const colCount = idx % 3 === 0 ? 3 : (idx % 2 === 0 ? 2 : 1);
        const avgScore = (80 + ((idx * 3 + rombelName.charCodeAt(rombelName.length - 1)) % 12)).toFixed(1);
        const isPassed = Number(avgScore) >= m.kkm;
        const passPct = isPassed ? (90 + (idx % 10)) + '%' : '72%';
        const statusBadge = colCount >= 3 ? 
          '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Lengkap</span>' :
          '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Aktif Mengisi</span>';

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors';
        row.innerHTML = `
          <td class="py-2.5 px-4 text-center font-bold text-slate-400">${idx + 1}</td>
          <td class="py-2.5 px-4 font-bold text-slate-800">${m.name}</td>
          <td class="py-2.5 px-4 text-slate-600">${m.lead}</td>
          <td class="py-2.5 px-4 text-center font-mono font-bold text-slate-500">${m.kkm}</td>
          <td class="py-2.5 px-4 text-center">
            <span class="font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">${colCount} Kolom Terinput</span>
          </td>
          <td class="py-2.5 px-4 text-center font-black ${isPassed ? 'text-indigo-950' : 'text-rose-600'}">${avgScore}</td>
          <td class="py-2.5 px-4 text-center">
            <span class="font-semibold text-slate-700">${passPct} Tuntas</span>
          </td>
          <td class="py-2.5 px-4 text-right">
            <button onclick="openCheckTeacherInputModal(1)" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 rounded-lg font-bold text-[11px] transition-colors">
              Cek Nilai &rarr;
            </button>
          </td>
        `;
        tbody.appendChild(row);
      });
    }

    function renderDetailRombelSiswa(rombelName) {
      const tbody = document.getElementById('detail-rombel-siswa-tbody');
      tbody.innerHTML = '';

      // Match real students or generate complete 32-student roster for this rombel
      const rombelStudents = studentsData.filter(s => s.class === rombelName);
      const studentNamesMock = [
        'Aditya Pratama', 'Alifia Nurul Zahra', 'Bayu Arya Putra', 'Citra Kirana Dewi', 'Daffa Ramadhan',
        'Farah Salsabila', 'Gilang Ramadhan', 'Hana Khairunnisa', 'Ilham Pratama', 'Jasmine Putri',
        'Kenzo Alfarizi', 'Laila Zahira', 'Muhammad Rizky', 'Nadia Safitri', 'Oki Pratama',
        'Putri Rahmadani', 'Qori Amelia', 'Rendy Saputra', 'Siti Maryam', 'Taufik Hidayat',
        'Umar Abdullah', 'Vina Panduwinata', 'Wahyu Setiawan', 'Xavier Pratama', 'Yogi Prasetyo',
        'Zahra Aulia', 'Andika Firmansyah', 'Bella Septiani', 'Candra Wijaya', 'Dini Anggraini',
        'Eko Prasetyo', 'Fani Rahmawati'
      ];

      for (let i = 0; i < 32; i++) {
        const studentName = rombelStudents[i] ? rombelStudents[i].name : studentNamesMock[i];
        const studentNisn = rombelStudents[i] ? rombelStudents[i].nisn : `009842${7135 + i}`;
        const score = (74 + ((i * 5 + 7) % 24)).toFixed(1);
        const isPassed = Number(score) >= 75;
        const attend = (90 + (i % 11)) + '%';

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors';
        row.innerHTML = `
          <td class="py-2.5 px-4 text-center font-bold text-slate-400">${i + 1}</td>
          <td class="py-2.5 px-4 font-bold text-slate-800">${studentName}</td>
          <td class="py-2.5 px-4 font-mono text-slate-500">${studentNisn}</td>
          <td class="py-2.5 px-4 text-center font-extrabold ${isPassed ? 'text-indigo-950' : 'text-rose-600'}">${score}</td>
          <td class="py-2.5 px-4 text-center font-semibold text-slate-700">${attend}</td>
          <td class="py-2.5 px-4 text-center">
            <span class="px-2 py-0.5 rounded-full font-bold text-[10px] ${isPassed ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">
              ${isPassed ? 'Tuntas' : 'Remedial'}
            </span>
          </td>
          <td class="py-2.5 px-4 text-slate-500 text-[11px] truncate max-w-[150px]">
            ${isPassed ? 'Disiplin dan aktif mengikuti sesi PBM' : 'Perlu pendampingan remedial tugas mapel'}
          </td>
        `;
        tbody.appendChild(row);
      }
    }

    function downloadRombelDetailCSV() {
      let csv = `REKAP PEMANTAUAN KELAS ${currentDetailRombel}\nNo,Nama Siswa,NISN,Rata-Rata Nilai,Kehadiran,Status\n`;
      for (let i = 1; i <= 32; i++) {
        csv += `${i},"Siswa ${i} (${currentDetailRombel})","009842${7000 + i}",82.5,95%,"Tuntas"\n`;
      }
      downloadFile(csv, `Pemantauan_Rombel_${currentDetailRombel.replace(' ', '_')}.csv`, 'text/csv');
      showToast('Download Berhasil', `File Pemantauan_Rombel_${currentDetailRombel}.csv berhasil diunduh.`);
    }

    // ==============================================================
    // LOGIC PEMANTAUAN INPUT PORTAL GURU
    // ==============================================================
    function renderMonitoringGuruTable() {
      const tbody = document.getElementById('monitoring-guru-table-body');
      if (!tbody) return;
      tbody.innerHTML = '';

      const search = document.getElementById('filter-mon-search')?.value.toLowerCase() || '';
      const grade = document.getElementById('filter-mon-grade')?.value || 'ALL';
      const status = document.getElementById('filter-mon-status')?.value || 'ALL';

      const filtered = teacherInputsMonitoring.filter(item => {
        const matchSearch = item.teacherName.toLowerCase().includes(search) || item.subject.toLowerCase().includes(search) || item.className.toLowerCase().includes(search);
        const matchGrade = (grade === 'ALL') || (item.grade === grade);
        const matchStatus = (status === 'ALL') || (item.status === status);
        return matchSearch && matchGrade && matchStatus;
      });

      filtered.forEach((item, idx) => {
        const isComplete = item.status === 'Lengkap';
        const isNotStarted = item.status === 'Belum Input';
        let badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Aktif Mengisi</span>';
        if (isComplete) badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Lengkap</span>';
        if (isNotStarted) badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Belum Input</span>';

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors';
        row.innerHTML = `
          <td class="py-3 px-4 text-center font-bold text-slate-400">${idx + 1}</td>
          <td class="py-3 px-4">
            <p class="font-bold text-slate-800 text-xs">${item.teacherName}</p>
            <p class="text-[11px] text-slate-400">Guru Pengampu</p>
          </td>
          <td class="py-3 px-4">
            <p class="font-bold text-indigo-950">${item.subject}</p>
            <span class="inline-block mt-0.5 px-2 py-0.2 bg-slate-100 text-slate-700 font-bold rounded text-[10px]">Kelas ${item.className}</span>
          </td>
          <td class="py-3 px-4 text-center font-semibold text-slate-700">
            ${item.columnsCount > 0 ? `<strong>${item.columnsCount}</strong> Kolom Nilai` : '<span class="text-slate-400">-</span>'}
          </td>
          <td class="py-3 px-4 text-center font-black ${item.classAvg !== '-' && Number(item.classAvg) >= item.kkm ? 'text-indigo-950' : 'text-rose-600'}">
            ${item.classAvg}
          </td>
          <td class="py-3 px-4 text-center text-slate-600 font-medium">
            ${item.journalCount} Jurnal · ${item.attendanceCount} Sesi Presensi
          </td>
          <td class="py-3 px-4 text-slate-500 text-[11px]">${item.lastUpdate}</td>
          <td class="py-3 px-4 text-center">${badge}</td>
          <td class="py-3 px-4 text-right">
            <button onclick="openCheckTeacherInputModal(${item.id})" class="px-3 py-1.5 bg-indigo-950 hover:bg-indigo-900 text-white rounded-xl font-bold text-xs shadow-2xs transition-all active:scale-95">
              Cek Hasil Input
            </button>
          </td>
        `;
        tbody.appendChild(row);
      });

      const label = document.getElementById('mon-guru-count-label');
      if (label) label.innerText = `Menampilkan ${filtered.length} dari ${teacherInputsMonitoring.length} pemantauan guru`;
    }

    function openCheckTeacherInputModal(id) {
      const item = teacherInputsMonitoring.find(x => x.id === id) || teacherInputsMonitoring[0];

      document.getElementById('cek-input-modal-subtitle').innerText = `${item.subject} · Kelas ${item.className} · ${item.teacherName}`;
      document.getElementById('cek-input-modal-badge').innerText = item.status;
      document.getElementById('cek-input-col-count').innerText = `${item.columnsCount} Kolom Penilaian`;
      document.getElementById('cek-input-avg').innerText = item.classAvg;
      document.getElementById('cek-input-journal-count').innerText = `${item.journalCount} Pertemuan Terisi`;
      document.getElementById('cek-input-time').innerText = item.lastUpdate;

      const thead = document.getElementById('cek-input-table-head');
      const tbody = document.getElementById('cek-input-table-body');
      
      thead.innerHTML = `
        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
          <th class="py-2.5 px-3 w-10 text-center">No</th>
          <th class="py-2.5 px-3">NISN</th>
          <th class="py-2.5 px-4">Nama Siswa</th>
          <th class="py-2.5 px-3 text-center bg-indigo-50/50">Tugas 1</th>
          <th class="py-2.5 px-3 text-center bg-indigo-50/50">Tugas 2</th>
          <th class="py-2.5 px-3 text-center bg-indigo-100/60 text-indigo-950">Sumatif</th>
          <th class="py-2.5 px-3 text-center bg-amber-50 font-bold text-amber-950">Rata-Rata</th>
        </tr>
      `;

      tbody.innerHTML = '';
      const sample = (item.sampleScores && item.sampleScores.length > 0) ? item.sampleScores : [
        { nisn: '0098427135', name: 'Aditya Pratama', score1: 82, score2: 88, sumatif: 85, avg: 85.0 },
        { nisn: '0098427142', name: 'Alifia Nurul Zahra', score1: 85, score2: 90, sumatif: 88, avg: 87.7 },
        { nisn: '0098427150', name: 'Bayu Arya Putra', score1: 78, score2: 80, sumatif: 82, avg: 80.0 },
        { nisn: '0098427161', name: 'Citra Kirana Dewi', score1: 88, score2: 92, sumatif: 90, avg: 90.0 },
        { nisn: '0098427178', name: 'Daffa Ramadhan', score1: 70, score2: 74, sumatif: 72, avg: 72.0 }
      ];

      sample.forEach((s, sIdx) => {
        tbody.innerHTML += `
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="py-2.5 px-3 text-center font-bold text-slate-400">${sIdx + 1}</td>
            <td class="py-2.5 px-3 font-mono text-slate-500">${s.nisn}</td>
            <td class="py-2.5 px-4 font-bold text-slate-800">${s.name}</td>
            <td class="py-2.5 px-3 text-center font-semibold text-slate-700">${s.score1}</td>
            <td class="py-2.5 px-3 text-center font-semibold text-slate-700">${s.score2}</td>
            <td class="py-2.5 px-3 text-center font-bold text-indigo-950">${s.sumatif}</td>
            <td class="py-2.5 px-3 text-center font-black ${Number(s.avg) >= item.kkm ? 'text-indigo-950 bg-indigo-50/30' : 'text-rose-600 bg-rose-50/20'}">${s.avg}</td>
          </tr>
        `;
      });

      document.getElementById('modal-cek-input-guru').classList.remove('hidden');
    }

    function closeCheckTeacherInputModal() {
      document.getElementById('modal-cek-input-guru').classList.add('hidden');
    }

    // ==============================================================
    // LOGIC TAB ARSIP SISWA (ALUMNI & MUTASI KELUAR)
    // ==============================================================
    function renderArchivedStudentsTable() {
      const tbody = document.getElementById('arsip-siswa-table-body');
      if (!tbody) return;
      tbody.innerHTML = '';

      const search = document.getElementById('filter-arsip-siswa-search')?.value.toLowerCase() || '';
      const status = document.getElementById('filter-arsip-siswa-status')?.value || 'ALL';
      const year = document.getElementById('filter-arsip-siswa-year')?.value || 'ALL';

      const filtered = archivedStudents.filter(s => {
        const matchSearch = s.name.toLowerCase().includes(search) || s.nisn.toLowerCase().includes(search);
        const matchStatus = (status === 'ALL') || (s.status === status);
        const matchYear = (year === 'ALL') || (s.year === year);
        return matchSearch && matchStatus && matchYear;
      });

      filtered.forEach((s, idx) => {
        const isLulus = s.status === 'Lulus';
        const badge = isLulus ? 
          '<span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-200">Lulus / Alumni</span>' :
          '<span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-amber-100 text-amber-800 border border-amber-200">Mutasi Keluar</span>';

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors';
        row.innerHTML = `
          <td class="py-3 px-4 text-center font-bold text-slate-400">${idx + 1}</td>
          <td class="py-3 px-4">
            <p class="font-bold text-slate-800 text-xs">${s.name}</p>
            <p class="text-[11px] font-mono text-slate-400">NISN: ${s.nisn}</p>
          </td>
          <td class="py-3 px-4 text-center font-bold text-slate-700">Kelas ${s.lastClass}</td>
          <td class="py-3 px-4 text-center">${badge}</td>
          <td class="py-3 px-4 font-semibold text-slate-700">
            <span class="inline-flex items-center gap-1">📅 ${s.time}</span>
          </td>
          <td class="py-3 px-4 text-slate-600 text-xs">${s.destination}</td>
          <td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
            <button onclick="openEditArchivedStudentModal(${s.id})" class="px-2.5 py-1 text-indigo-700 hover:bg-indigo-50 border border-indigo-200 rounded-lg font-bold text-[11px]">
              Edit
            </button>
            <button onclick="openDetailArsipModal('siswa', ${s.id})" class="px-2.5 py-1 text-slate-700 hover:bg-slate-100 border border-slate-200 rounded-lg font-bold text-[11px]">
              Detail
            </button>
            <button onclick="restoreArchivedStudent(${s.id})" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg font-bold text-[11px]" title="Kembalikan siswa ke data siswa aktif">
              Pulihkan
            </button>
          </td>
        `;
        tbody.appendChild(row);
      });

      const countBadge = document.getElementById('sidebar-count-arsip-siswa');
      if (countBadge) countBadge.innerText = archivedStudents.length;
      const label = document.getElementById('arsip-siswa-count-label');
      if (label) label.innerText = `Menampilkan ${filtered.length} dari ${archivedStudents.length} berkas arsip siswa`;
    }

    function restoreArchivedStudent(id) {
      const idx = archivedStudents.findIndex(x => x.id === id);
      if (idx === -1) return;
      const s = archivedStudents[idx];

      if (confirm(`Pulihkan ${s.name} (NISN: ${s.nisn}) ke Data Siswa Aktif?`)) {
        archivedStudents.splice(idx, 1);
        studentsData.push({
          id: studentsData.length + 1,
          nisn: s.nisn,
          name: s.name,
          gender: 'L',
          class: s.lastClass,
          parent: 'Wali Siswa',
          phone: s.phone || '0812-xxxx',
          status: 'Aktif'
        });

        renderArchivedStudentsTable();
        renderStudentsTable();
        showToast('Siswa Dipulihkan', `${s.name} berhasil dipulihkan ke daftar siswa aktif di kelas ${s.lastClass}.`);
      }
    }

    function downloadArchivedStudentsCSV() {
      let csv = 'No,NISN,Nama Siswa,Kelas Terakhir,Status Arsip,Waktu Kejadian,Keterangan / Sekolah Lanjutan,No Telepon\n';
      archivedStudents.forEach((s, idx) => {
        csv += `${idx + 1},"${s.nisn}","${s.name}","${s.lastClass}","${s.status}","${s.time}","${s.destination}","${s.phone}"\n`;
      });
      downloadFile(csv, 'Data_Arsip_Siswa_SMPN7_Purwakarta.csv', 'text/csv');
      showToast('Download Berhasil', 'File Data_Arsip_Siswa_SMPN7_Purwakarta.csv berhasil diunduh.');
    }

    // ==============================================================
    // LOGIC TAB ARSIP GURU & STAFF (PENSIUN & MUTASI KELUAR)
    // ==============================================================
    function renderArchivedTeachersTable() {
      const tbody = document.getElementById('arsip-guru-table-body');
      if (!tbody) return;
      tbody.innerHTML = '';

      const search = document.getElementById('filter-arsip-guru-search')?.value.toLowerCase() || '';
      const status = document.getElementById('filter-arsip-guru-status')?.value || 'ALL';
      const year = document.getElementById('filter-arsip-guru-year')?.value || 'ALL';

      const filtered = archivedTeachers.filter(g => {
        const matchSearch = g.name.toLowerCase().includes(search) || g.nip.toLowerCase().includes(search);
        const matchStatus = (status === 'ALL') || (g.status === status);
        const matchYear = (year === 'ALL') || (g.year === year);
        return matchSearch && matchStatus && matchYear;
      });

      filtered.forEach((g, idx) => {
        const isPensiun = g.status === 'Pensiun';
        const badge = isPensiun ?
          '<span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-purple-100 text-purple-800 border border-purple-200">Pensiun (Purna Tugas)</span>' :
          '<span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-blue-100 text-blue-800 border border-blue-200">Mutasi Keluar</span>';

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors';
        row.innerHTML = `
          <td class="py-3 px-4 text-center font-bold text-slate-400">${idx + 1}</td>
          <td class="py-3 px-4">
            <p class="font-bold text-slate-800 text-xs">${g.name}</p>
            <p class="text-[11px] font-mono text-slate-400">NIP: ${g.nip}</p>
          </td>
          <td class="py-3 px-4 font-medium text-slate-700">${g.lastSubject}</td>
          <td class="py-3 px-4 text-center">${badge}</td>
          <td class="py-3 px-4 font-semibold text-slate-700">
            <span class="inline-flex items-center gap-1">📅 ${g.time}</span>
          </td>
          <td class="py-3 px-4 text-slate-600 text-xs">${g.sk}</td>
          <td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
            <button onclick="openEditArchivedTeacherModal(${g.id})" class="px-2.5 py-1 text-indigo-700 hover:bg-indigo-50 border border-indigo-200 rounded-lg font-bold text-[11px]">
              Edit
            </button>
            <button onclick="openDetailArsipModal('guru', ${g.id})" class="px-2.5 py-1 text-slate-700 hover:bg-slate-100 border border-slate-200 rounded-lg font-bold text-[11px]">
              Detail
            </button>
            <button onclick="restoreArchivedTeacher(${g.id})" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg font-bold text-[11px]" title="Kembalikan guru ke data pendidik aktif">
              Pulihkan
            </button>
          </td>
        `;
        tbody.appendChild(row);
      });

      const countBadge = document.getElementById('sidebar-count-arsip-guru');
      if (countBadge) countBadge.innerText = archivedTeachers.length;
      const label = document.getElementById('arsip-guru-count-label');
      if (label) label.innerText = `Menampilkan ${filtered.length} dari ${archivedTeachers.length} berkas arsip pendidik`;
    }

    function restoreArchivedTeacher(id) {
      const idx = archivedTeachers.findIndex(x => x.id === id);
      if (idx === -1) return;
      const g = archivedTeachers[idx];

      if (confirm(`Pulihkan ${g.name} (NIP: ${g.nip}) ke Data Guru Aktif?`)) {
        archivedTeachers.splice(idx, 1);
        teachersData.push({
          id: teachersData.length + 1,
          name: g.name,
          nip: g.nip,
          mapel: g.lastSubject,
          wali: 'Bukan Wali Kelas',
          phone: '0812-xxxx-xxxx',
          status: 'Aktif'
        });

        renderArchivedTeachersTable();
        renderTeachersTable();
        showToast('Guru Dipulihkan', `${g.name} berhasil dipulihkan ke daftar guru aktif.`);
      }
    }

    function downloadArchivedTeachersCSV() {
      let csv = 'No,NIP,Nama Lengkap,Mapel/Posisi Terakhir,Status Arsip,Waktu Pensiun/Keluar,No SK / Catatan\n';
      archivedTeachers.forEach((g, idx) => {
        csv += `${idx + 1},"${g.nip}","${g.name}","${g.lastSubject}","${g.status}","${g.time}","${g.sk}"\n`;
      });
      downloadFile(csv, 'Data_Arsip_Guru_SMPN7_Purwakarta.csv', 'text/csv');
      showToast('Download Berhasil', 'File Data_Arsip_Guru_SMPN7_Purwakarta.csv berhasil diunduh.');
    }

    // ==============================================================
    // MODAL DETAIL ARSIP UMUM (SISWA / GURU)
    // ==============================================================
    function openDetailArsipModal(type, id) {
      const content = document.getElementById('detail-arsip-content');
      const title = document.getElementById('detail-arsip-title');
      const typeLabel = document.getElementById('detail-arsip-type');
      const restoreBtn = document.getElementById('btn-arsip-restore');

      if (type === 'siswa') {
        const s = archivedStudents.find(x => x.id === id);
        if (!s) return;
        title.innerText = `Arsip Siswa: ${s.name}`;
        typeLabel.innerText = 'Buku Induk Kategori Alumni / Mutasi Keluar';
        content.innerHTML = `
          <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-400">NISN</span>
              <span class="font-bold text-slate-800 font-mono">${s.nisn}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-400">Kelas Terakhir</span>
              <span class="font-bold text-slate-800">Kelas ${s.lastClass}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-400">Status Diarsipkan</span>
              <span class="font-bold text-indigo-900">${s.status}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-400">Waktu Lulus/Keluar</span>
              <span class="font-bold text-emerald-700">📅 ${s.time}</span>
            </div>
            <div class="py-1">
              <span class="text-slate-400 block mb-1">Sekolah Lanjutan / Keterangan</span>
              <p class="font-semibold text-slate-800">${s.destination}</p>
            </div>
          </div>
        `;
        restoreBtn.onclick = () => { closeDetailArsipModal(); restoreArchivedStudent(s.id); };
      } else {
        const g = archivedTeachers.find(x => x.id === id);
        if (!g) return;
        title.innerText = `Arsip Pendidik: ${g.name}`;
        typeLabel.innerText = 'Master Data Pendidik Purna Tugas / Mutasi';
        content.innerHTML = `
          <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-400">NIP</span>
              <span class="font-bold text-slate-800 font-mono">${g.nip}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-400">Mapel / Posisi Terakhir</span>
              <span class="font-bold text-slate-800">${g.lastSubject}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-400">Status Diarsipkan</span>
              <span class="font-bold text-purple-900">${g.status}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-400">Waktu Pensiun/Keluar</span>
              <span class="font-bold text-purple-700">📅 ${g.time}</span>
            </div>
            <div class="py-1">
              <span class="text-slate-400 block mb-1">No. SK / Catatan Resmi</span>
              <p class="font-semibold text-slate-800">${g.sk}</p>
            </div>
          </div>
        `;
        restoreBtn.onclick = () => { closeDetailArsipModal(); restoreArchivedTeacher(g.id); };
      }

      document.getElementById('modal-detail-arsip').classList.remove('hidden');
    }

    
    // ==============================================================
    // HANDLERS EDIT & UPLOAD ARSIP SISWA
    // ==============================================================
    function openEditArchivedStudentModal(id) {
      const s = archivedStudents.find(x => x.id === id);
      if (!s) return;

      document.getElementById('edit-arsip-siswa-id').value = s.id;
      document.getElementById('edit-arsip-siswa-name').value = s.name;
      document.getElementById('edit-arsip-siswa-nisn').value = s.nisn;
      document.getElementById('edit-arsip-siswa-lastclass').value = s.lastClass;
      document.getElementById('edit-arsip-siswa-status').value = s.status;
      document.getElementById('edit-arsip-siswa-time').value = s.time;
      document.getElementById('edit-arsip-siswa-year').value = s.year || '2026';
      document.getElementById('edit-arsip-siswa-destination').value = s.destination;
      document.getElementById('edit-arsip-siswa-phone').value = s.phone || '';

      document.getElementById('modal-edit-arsip-siswa').classList.remove('hidden');
    }

    function closeEditArchivedStudentModal() {
      document.getElementById('modal-edit-arsip-siswa').classList.add('hidden');
    }

    function handleSaveEditArchivedStudent(e) {
      e.preventDefault();
      const id = parseInt(document.getElementById('edit-arsip-siswa-id').value);
      const s = archivedStudents.find(x => x.id === id);
      if (!s) return;

      s.name = document.getElementById('edit-arsip-siswa-name').value;
      s.nisn = document.getElementById('edit-arsip-siswa-nisn').value;
      s.lastClass = document.getElementById('edit-arsip-siswa-lastclass').value;
      s.status = document.getElementById('edit-arsip-siswa-status').value;
      s.time = document.getElementById('edit-arsip-siswa-time').value;
      s.year = document.getElementById('edit-arsip-siswa-year').value;
      s.destination = document.getElementById('edit-arsip-siswa-destination').value;
      s.phone = document.getElementById('edit-arsip-siswa-phone').value;

      closeEditArchivedStudentModal();
      renderArchivedStudentsTable();
      showToast('Arsip Siswa Diperbarui', `Berkas arsip ${s.name} berhasil disimpan.`);
    }

    function openModalUploadArsipSiswa() {
      document.getElementById('modal-upload-arsip-siswa').classList.remove('hidden');
    }

    function closeModalUploadArsipSiswa() {
      document.getElementById('modal-upload-arsip-siswa').classList.add('hidden');
    }

    function downloadArsipSiswaCSVTemplate() {
      const csv = 'NISN,Nama Siswa,Kelas Terakhir,Status Arsip,Waktu Kejadian,Keterangan / Sekolah Lanjutan,No Telepon,Tahun\n0098427999,"Nama Siswa Contoh","IX A","Lulus","Juni 2026","SMAN 1 Purwakarta","0812-3344-5566","2026"\n';
      downloadFile(csv, 'Template_Upload_Arsip_Siswa.csv', 'text/csv');
      showToast('Template Diunduh', 'Template_Upload_Arsip_Siswa.csv berhasil diunduh.');
    }

    function simulateUploadArsipSiswa() {
      const newItems = [
        { id: Date.now() + 1, nisn: '0091234901', name: 'Zaskia Gotik Putri', lastClass: 'IX B', status: 'Lulus', time: 'Juni 2026', destination: 'SMAN 2 Purwakarta', phone: '0812-9988-1122', year: '2026' },
        { id: Date.now() + 2, nisn: '0091234902', name: 'Farel Prayoga Putra', lastClass: 'VIII D', status: 'Mutasi Keluar', time: 'Maret 2026', destination: 'Pindah ke SMPN 1 Surabaya', phone: '0857-4455-6677', year: '2026' }
      ];

      archivedStudents.unshift(...newItems);
      closeModalUploadArsipSiswa();
      renderArchivedStudentsTable();
      showToast('Upload Berhasil', '2 berkas arsip siswa berhasil ditambahkan.');
    }

    // ==============================================================
    // HANDLERS EDIT & UPLOAD ARSIP GURU
    // ==============================================================
    function openEditArchivedTeacherModal(id) {
      const g = archivedTeachers.find(x => x.id === id);
      if (!g) return;

      document.getElementById('edit-arsip-guru-id').value = g.id;
      document.getElementById('edit-arsip-guru-name').value = g.name;
      document.getElementById('edit-arsip-guru-nip').value = g.nip;
      document.getElementById('edit-arsip-guru-mapel').value = g.lastSubject;
      document.getElementById('edit-arsip-guru-status').value = g.status;
      document.getElementById('edit-arsip-guru-time').value = g.time;
      document.getElementById('edit-arsip-guru-year').value = g.year || '2026';
      document.getElementById('edit-arsip-guru-sk').value = g.sk;

      document.getElementById('modal-edit-arsip-guru').classList.remove('hidden');
    }

    function closeEditArchivedTeacherModal() {
      document.getElementById('modal-edit-arsip-guru').classList.add('hidden');
    }

    function handleSaveEditArchivedTeacher(e) {
      e.preventDefault();
      const id = parseInt(document.getElementById('edit-arsip-guru-id').value);
      const g = archivedTeachers.find(x => x.id === id);
      if (!g) return;

      g.name = document.getElementById('edit-arsip-guru-name').value;
      g.nip = document.getElementById('edit-arsip-guru-nip').value;
      g.lastSubject = document.getElementById('edit-arsip-guru-mapel').value;
      g.status = document.getElementById('edit-arsip-guru-status').value;
      g.time = document.getElementById('edit-arsip-guru-time').value;
      g.year = document.getElementById('edit-arsip-guru-year').value;
      g.sk = document.getElementById('edit-arsip-guru-sk').value;

      closeEditArchivedTeacherModal();
      renderArchivedTeachersTable();
      showToast('Arsip Guru Diperbarui', `Berkas arsip ${g.name} berhasil disimpan.`);
    }

    function openModalUploadArsipGuru() {
      document.getElementById('modal-upload-arsip-guru').classList.remove('hidden');
    }

    function closeModalUploadArsipGuru() {
      document.getElementById('modal-upload-arsip-guru').classList.add('hidden');
    }

    function downloadArsipGuruCSVTemplate() {
      const csv = 'NIP,Nama Lengkap,Mapel/Posisi Terakhir,Status Arsip,Waktu Kejadian,No SK / Catatan,Tahun\n"19670101 199003 1 001","Drs. Guru Contoh, M.Pd","Bahasa Indonesia","Pensiun","Agustus 2026","SK Pensiun BKN No. 821.2/099/2026","2026"\n';
      downloadFile(csv, 'Template_Upload_Arsip_Guru.csv', 'text/csv');
      showToast('Template Diunduh', 'Template_Upload_Arsip_Guru.csv berhasil diunduh.');
    }

    function simulateUploadArsipGuru() {
      const newItems = [
        { id: Date.now() + 10, nip: '19650214 199103 2 002', name: 'Dra. Hj. Siti Fatimah, M.M', lastSubject: 'Pendidikan Agama Islam', status: 'Pensiun', time: 'Februari 2026', sk: 'SK Pensiun BKN No. 821.2/021/BKN/2026', year: '2026' },
        { id: Date.now() + 11, nip: '19810419 200801 1 015', name: 'Agus Gunawan, S.Pd', lastSubject: 'Informatika', status: 'Mutasi Keluar', time: 'Maret 2026', sk: 'SK Disdik Purwakarta No. 421/045/Disdik/2026 (Pindah Dinas)', year: '2026' }
      ];

      archivedTeachers.unshift(...newItems);
      closeModalUploadArsipGuru();
      renderArchivedTeachersTable();
      showToast('Upload Berhasil', '2 berkas arsip pendidik berhasil ditambahkan.');
    }

    function closeDetailArsipModal() {
      document.getElementById('modal-detail-arsip').classList.add('hidden');
    }


    // ==============================================================
    // SETTINGS & UTILS
    // ==============================================================
    function handleSaveSettings(e) {
      e.preventDefault();
      showToast('Pengaturan Disimpan', 'Identitas sekolah dan jadwal semester berhasil diperbarui.');
    }

    // Mobile Sidebar Drawer Control
    function openMobileSidebar() {
      const sidebar = document.getElementById('admin-sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');
      if (sidebar) sidebar.classList.remove('-translate-x-full');
      if (backdrop) backdrop.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
    }

    function closeMobileSidebar() {
      const sidebar = document.getElementById('admin-sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');
      if (sidebar) sidebar.classList.add('-translate-x-full');
      if (backdrop) backdrop.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }

    function toggleMobileSidebar() {
      const sidebar = document.getElementById('admin-sidebar');
      if (sidebar && sidebar.classList.contains('-translate-x-full')) {
        openMobileSidebar();
      } else {
        closeMobileSidebar();
      }
    }

    // Portal Switcher Dropdown
    function togglePortalSwitcher(e) {
      if (e) e.stopPropagation();
      const menu = document.getElementById('portal-switcher-menu');
      if (menu) menu.classList.toggle('hidden');
    }

    // Global click listener to close portal dropdown & modal backdrop clicks
    document.addEventListener('click', function(e) {
      const container = document.getElementById('portal-switcher-container');
      const menu = document.getElementById('portal-switcher-menu');
      if (menu && container && !container.contains(e.target)) {
        menu.classList.add('hidden');
      }

      // If clicked on backdrop of any modal
      if (e.target && e.target.id && e.target.id.startsWith('modal-') && !e.target.classList.contains('hidden')) {
        e.target.classList.add('hidden');
      }
    });

    // Global keyboard listener (Escape key closes modals & drawer)
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('[id^="modal-"]').forEach(modal => {
          modal.classList.add('hidden');
        });
        closeMobileSidebar();
        const menu = document.getElementById('portal-switcher-menu');
        if (menu) menu.classList.add('hidden');
      }
    });

    // Handle window resize cleanly
    window.addEventListener('resize', function() {
      if (window.innerWidth >= 768) {
        const backdrop = document.getElementById('sidebar-backdrop');
        if (backdrop) backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
      }
    });

    function logoutAdmin() {
      if (confirm('Keluar dari Panel Administrator?')) {
        showToast('Keluar Sesi', 'Sesi admin telah ditutup.');
      }
    }

    function showToast(title, msg) {
      const box = document.getElementById('toast-box');
      document.getElementById('toast-title').innerText = title;
      document.getElementById('toast-msg').innerText = msg;
      box.classList.remove('translate-y-24', 'opacity-0');
      setTimeout(() => {
        box.classList.add('translate-y-24', 'opacity-0');
      }, 3500);
    }

    function downloadFile(content, fileName, mimeType) {
      const blob = new Blob([content], { type: mimeType });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }
  </script>

<!-- Code injected by live-server -->
<script>
	// <![CDATA[  <-- For SVG support
	if ('WebSocket' in window) {
		(function () {
			function refreshCSS() {
				var sheets = [].slice.call(document.getElementsByTagName("link"));
				var head = document.getElementsByTagName("head")[0];
				for (var i = 0; i < sheets.length; ++i) {
					var elem = sheets[i];
					var parent = elem.parentElement || head;
					parent.removeChild(elem);
					var rel = elem.rel;
					if (elem.href && typeof rel != "string" || rel.length == 0 || rel.toLowerCase() == "stylesheet") {
						var url = elem.href.replace(/(&|\?)_cacheOverride=\d+/, '');
						elem.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_cacheOverride=' + (new Date().valueOf());
					}
					parent.appendChild(elem);
				}
			}
			var protocol = window.location.protocol === 'http:' ? 'ws://' : 'wss://';
			var address = protocol + window.location.host + window.location.pathname + '/ws';
			var socket = new WebSocket(address);
			socket.onmessage = function (msg) {
				if (msg.data == 'reload') window.location.reload();
				else if (msg.data == 'refreshcss') refreshCSS();
			};
			if (sessionStorage && !sessionStorage.getItem('IsThisFirstTime_Log_From_LiveServer')) {
				console.log('Live reload enabled.');
				sessionStorage.setItem('IsThisFirstTime_Log_From_LiveServer', true);
			}
		})();
	}
	else {
		console.error('Upgrade your browser. This Browser is NOT supported WebSocket for Live-Reloading.');
	}
	// ]]>
</script>
</body>
</html>
