<?php
/**
 * ==============================================================
 * REGISTRASI PENGGUNA BARU (Guru & Murid / Siswa)
 * --------------------------------------------------------------
 * Sistem Akademik SMPN 7 Purwakarta
 *
 * Fitur:
 *  - Pilihan peran disederhanakan: HANYA Guru dan Murid (Siswa).
 *    (Administrator tidak dapat mendaftar dari halaman publik).
 *  - Form input ringkas: Hanya Username, Kata Sandi, dan Konfirmasi Kata Sandi.
 *  - Akun disimpan dengan is_active = 0 (menunggu persetujuan Admin).
 *  - Dilindungi CSRF, validasi regex, prepared statement.
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php';

if (is_logged_in() && validate_session()) {
    redirect(role_home(user_role() ?? 'siswa'));
}

$registrationOpen = (db_select_one($koneksi, "SELECT nilai FROM pengaturan WHERE kunci = 'registration_open'")['nilai'] ?? '1') === '1';

// HANYA ada 2 peran: Murid (siswa) & Guru (guru). Administrator dihilangkan.
$allowedRoles = ['siswa', 'guru'];
$role = strtolower((string) ($_POST['role'] ?? ($_GET['role'] ?? 'siswa')));
if (!in_array($role, $allowedRoles, true)) {
    $role = 'siswa';
}

$errors = [];
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $oldUsername = $username;

    // --------------------- VALIDASI FORM ---------------------
    if (!$registrationOpen) {
        $errors[] = 'Pendaftaran akun sedang ditutup oleh administrator sekolah.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username)) {
        $errors[] = 'Username harus 4–50 karakter (hanya huruf, angka, titik, garis bawah, atau strip tanpa spasi).';
    }
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Kata sandi minimal ' . PASSWORD_MIN_LENGTH . ' karakter.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Konfirmasi kata sandi tidak sama dengan kata sandi.';
    }

    if (empty($errors)) {
        try {
            $koneksi->begin_transaction();

            // Cek apakah username sudah pernah digunakan
            if (db_select_one($koneksi, 'SELECT id FROM users WHERE username = ?', 's', [$username])) {
                throw new RuntimeException('Username "' . $username . '" sudah digunakan. Silakan pilih username lain.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Simpan akun ke tabel users dengan status is_active = 0 (Approval Queue)
            $userId = db_insert(
                $koneksi,
                'INSERT INTO users (username, nama_lengkap, password_hash, role, is_active) VALUES (?, ?, ?, ?, 0)',
                'ssss',
                [$username, $username, $hash, $role]
            );

            // Buat record profil dasar agar query join data_guru / data_siswa sinkron
            if ($role === 'guru') {
                $nextKodeRow = db_select_one($koneksi, "SELECT COALESCE(MAX(CAST(kode AS UNSIGNED)), 0) + 1 AS next_kode FROM data_guru");
                $kode = (string) ((int) ($nextKodeRow['next_kode'] ?? 1));
                db_insert(
                    $koneksi,
                    'INSERT INTO data_guru (user_id, kode, nama_guru, status) VALUES (?, ?, ?, ?)',
                    'isss',
                    [$userId, $kode, $username, 'Non-Aktif']
                );
            } elseif ($role === 'siswa') {
                db_insert(
                    $koneksi,
                    'INSERT INTO data_siswa (user_id, nama_siswa, status, catatan_wali) VALUES (?, ?, ?, ?)',
                    'isss',
                    [$userId, $username, 'Pending', 'Menunggu persetujuan akun oleh Administrator.']
                );
            }

            $koneksi->commit();
            redirect('login.php?registered=pending&role=' . urlencode($role));
        } catch (RuntimeException $ex) {
            $koneksi->rollback();
            $errors[] = $ex->getMessage();
        } catch (mysqli_sql_exception $ex) {
            $koneksi->rollback();
            error_log('[REGISTER] ' . $ex->getMessage());
            $errors[] = $ex->getCode() === 1062
                ? 'Username tersebut sudah terdaftar. Silakan gunakan username lain.'
                : 'Registrasi gagal disimpan: ' . $ex->getMessage();
        }
    }
}

$csrf = csrf_token();
$roleLabels = [
    'siswa' => 'Murid (Siswa)',
    'guru'  => 'Guru (Pendidik)'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrasi Akun Baru — <?= e(APP_SHORT_NAME) ?></title>
  <link rel="icon" type="image/png" href="logoSMP.png">
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
  </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased flex flex-col justify-center py-8 px-4 sm:px-6">
  <main class="max-w-md w-full mx-auto">
    <!-- Header Logo & Sekolah -->
    <div class="text-center mb-6">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white shadow-md p-2 mb-3 border border-slate-200">
        <img src="logoSMP.png" alt="Logo SMPN 7 Purwakarta" class="w-full h-full object-contain">
      </div>
      <h1 class="text-2xl font-extrabold text-indigo-950 tracking-tight"><?= e(APP_SHORT_NAME) ?></h1>
      <p class="text-xs text-slate-500 mt-0.5">Sistem Manajemen Akademik SMPN 7 Purwakarta</p>
    </div>

    <!-- Registration Card -->
    <div class="bg-white rounded-3xl shadow-xl border border-slate-200/80 p-6 sm:p-8">
      
      <?php if (!$registrationOpen): ?>
        <div class="mb-5 p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold">
          ⚠ Pendaftaran akun sedang ditutup sementara oleh administrator sekolah.
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="mb-5 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1" role="alert">
          <?php foreach ($errors as $err): ?>
            <p class="font-semibold flex items-center gap-1.5">
              <span>⚠</span> <span><?= e($err) ?></span>
            </p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="mb-5">
        <h2 class="text-lg font-extrabold text-slate-900">Buat Akun Baru</h2>
        <p class="text-xs text-slate-500 mt-1">Pilih peran dan buat username serta kata sandi Anda.</p>
      </div>

      <!-- Pemilihan Role: HANYA GURU DAN MURID (SISWA) -->
      <div class="mb-5">
        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-2">Daftar Sebagai Peran</label>
        <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-2xl text-xs font-bold" role="tablist">
          <button type="button" role="tab"
                  onclick="switchRole('siswa')"
                  id="role-tab-siswa"
                  class="role-tab py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 <?= $role === 'siswa' ? 'bg-indigo-950 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <span>🎒</span>
            <span>Murid (Siswa)</span>
          </button>

          <button type="button" role="tab"
                  onclick="switchRole('guru')"
                  id="role-tab-guru"
                  class="role-tab py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 <?= $role === 'guru' ? 'bg-indigo-950 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <span>👨‍🏫</span>
            <span>Guru (Pendidik)</span>
          </button>
        </div>
      </div>

      <!-- Info Persetujuan Admin (Approval Queue) -->
      <div class="mb-5 p-3.5 rounded-2xl bg-amber-50/80 border border-amber-200/90 text-amber-900 text-xs flex items-start gap-2.5">
        <span class="text-base shrink-0 mt-0.5">🔒</span>
        <div class="leading-relaxed">
          <p class="font-bold text-amber-950">Menunggu Persetujuan Admin</p>
          <p class="text-[11px] text-amber-800 mt-0.5">
            Akun baru tidak langsung aktif. Setelah pendaftaran, Administrator sekolah akan memeriksa dan menyetujui akun Anda terlebih dahulu sebelum dapat digunakan untuk login.
          </p>
        </div>
      </div>

      <!-- Form Pendaftaran Ringkas -->
      <form method="post" action="register.php" class="space-y-4 text-xs" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="role" id="role-input" value="<?= e($role) ?>">

        <!-- Username -->
        <div>
          <label for="username" class="block font-bold text-slate-700 mb-1">
            Username <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <input id="username" name="username" type="text" required minlength="4" maxlength="50"
                   value="<?= e($oldUsername) ?>" placeholder="mis. budi.santoso"
                   class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 focus:outline-none transition-all placeholder:text-slate-400">
          </div>
          <p class="text-[10px] text-slate-400 mt-1">Minimal 4 karakter (huruf, angka, titik, atau strip).</p>
        </div>

        <!-- Kata Sandi -->
        <div>
          <label for="password" class="block font-bold text-slate-700 mb-1">
            Kata Sandi <span class="text-rose-500">*</span>
          </label>
          <input id="password" name="password" type="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>"
                 placeholder="Minimal <?= PASSWORD_MIN_LENGTH ?> karakter"
                 class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 focus:outline-none transition-all placeholder:text-slate-400">
        </div>

        <!-- Konfirmasi Kata Sandi -->
        <div>
          <label for="password_confirm" class="block font-bold text-slate-700 mb-1">
            Konfirmasi Kata Sandi <span class="text-rose-500">*</span>
          </label>
          <input id="password_confirm" name="password_confirm" type="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>"
                 placeholder="Ketik ulang kata sandi"
                 class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 focus:outline-none transition-all placeholder:text-slate-400">
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
          <button type="submit" <?= !$registrationOpen ? 'disabled' : '' ?>
                  class="w-full py-3 px-4 bg-indigo-950 hover:bg-indigo-900 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow-md hover:shadow-lg transition-all text-xs flex items-center justify-center gap-2 active:scale-98">
            <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            <span>Daftar sebagai <strong id="submit-role-label"><?= e($roleLabels[$role]) ?></strong></span>
          </button>
        </div>
      </form>

      <!-- Back to login link -->
      <div class="mt-6 pt-5 border-t border-slate-100 text-center">
        <p class="text-xs text-slate-500">
          Sudah memiliki akun terdaftar? 
          <a href="login.php" class="font-bold text-indigo-900 hover:text-indigo-700 hover:underline">
            Masuk di sini &rarr;
          </a>
        </p>
      </div>

    </div>

    <!-- Footer Copyright -->
    <p class="text-center text-[11px] text-slate-400 mt-6">
      &copy; <?= date('Y') ?> SMPN 7 Purwakarta · Sistem Akademik Terpadu
    </p>
  </main>

  <script>
    const ROLE_LABELS = {
      'siswa': 'Murid (Siswa)',
      'guru': 'Guru (Pendidik)'
    };

    function switchRole(role) {
      if (role !== 'guru' && role !== 'siswa') role = 'siswa';
      document.getElementById('role-input').value = role;

      const tabSiswa = document.getElementById('role-tab-siswa');
      const tabGuru = document.getElementById('role-tab-guru');

      if (role === 'siswa') {
        tabSiswa.className = 'role-tab py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 bg-indigo-950 text-white shadow-sm';
        tabGuru.className = 'role-tab py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
      } else {
        tabGuru.className = 'role-tab py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 bg-indigo-950 text-white shadow-sm';
        tabSiswa.className = 'role-tab py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
      }

      const submitLabel = document.getElementById('submit-role-label');
      if (submitLabel) {
        submitLabel.textContent = ROLE_LABELS[role] || role;
      }
    }
  </script>
</body>
</html>
