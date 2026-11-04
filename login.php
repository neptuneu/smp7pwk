<?php
/**
 * ==============================================================
 * LOGIN TERPUSAT (Admin, Guru, Siswa)
 * - password_verify() terhadap tabel users
 * - Regenerasi session ID setelah login
 * - Pembatasan percobaan login (rate limit) sederhana
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php';

// Sudah login? langsung ke portal sesuai peran.
if (is_logged_in() && validate_session()) {
    redirect(role_home(user_role() ?? 'siswa'));
}

$errors = [];
$old = ['identifier' => ''];
$justRegistered = isset($_GET['registered']);

/**
 * Ambil user berdasarkan username, NIP guru, atau NISN siswa.
 */
function find_login_user(mysqli $db, string $identifier): ?array
{
    $sql = "SELECT u.id, u.username, u.password_hash, u.role, u.is_active,
                   COALESCE(u.nama_lengkap, g.nama_guru, s.nama_siswa) AS nama,
                   COALESCE(g.id, s.id) AS profile_id,
                   g.status AS guru_status, s.status AS siswa_status
            FROM users u
            LEFT JOIN data_guru g ON g.user_id = u.id
            LEFT JOIN data_siswa s ON s.user_id = u.id
            WHERE u.username = ? OR g.nip = ? OR s.nisn = ?
            LIMIT 1";
    return db_select_one($db, $sql, 'sss', [$identifier, $identifier, $identifier]);
}
/** Catat login gagal. Return true bila akun baru saja dikunci. */
function catat_login_gagal(int $now): bool
{
    $_SESSION['login_failed'] = (int) ($_SESSION['login_failed'] ?? 0) + 1;
    if ($_SESSION['login_failed'] >= 5) {
        $_SESSION['login_lock_until'] = $now + 60; // kunci 60 detik
        $_SESSION['login_failed'] = 0;
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $next = $_POST['next'] ?? ($_GET['next'] ?? null);
    $old['identifier'] = $identifier;

    // ---- Rate limit: maksimal 5 percobaan / 5 menit per sesi ----
        $now = time();
    $lockUntil = (int) ($_SESSION['login_lock_until'] ?? 0);
    if ($lockUntil > 0 && $lockUntil <= $now) { // kunci sudah habis, mulai dari nol
        unset($_SESSION['login_lock_until'], $_SESSION['login_failed']);
        $lockUntil = 0;
    }
    if ($lockUntil > $now) {
        $errors[] = 'Terlalu banyak percobaan login. Coba lagi dalam ' . ($lockUntil - $now) . ' detik.';
    } else {
        if ($identifier === '' || $password === '') {
            $errors[] = 'Username/NIP/NISN dan kata sandi wajib diisi.';
        } else {
            $user = find_login_user($koneksi, $identifier);

            // Pesan error dibuat generik agar tidak membocorkan akun mana yang ada.
            if (!$user) {
                $errors[] = 'Kombinasi akun dan kata sandi tidak cocok.';
                if (catat_login_gagal($now)) {
                    $errors = ['Terlalu banyak percobaan login. Coba lagi dalam 60 detik.'];
                }
            } elseif ((int) $user['is_active'] !== 1) {
                $errors[] = 'Akun Anda belum disetujui atau sedang dinonaktifkan oleh administrator sekolah. Silakan hubungi Administrator / Tata Usaha.';
            } elseif ($user['role'] === 'guru' && ($user['guru_status'] ?? 'Aktif') === 'Non-Aktif') {
                $errors[] = 'Akun guru Anda berstatus Non-Aktif. Hubungi Tata Usaha.';
            } elseif ($user['role'] === 'siswa' && !in_array($user['siswa_status'] ?? 'Aktif', ['Aktif'], true)) {
                $errors[] = 'Akun siswa hanya dapat dipakai selama status peserta didik Aktif.';
            } else {
                // Upgrade hash bila algoritma berubah.
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    db_execute(
                        $koneksi,
                        'UPDATE users SET password_hash = ? WHERE id = ?',
                        'si',
                        [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]
                    );
                }

                db_execute($koneksi, 'UPDATE users SET last_login_at = NOW() WHERE id = ?', 'i', [(int) $user['id']]);

                unset($_SESSION['login_failed'], $_SESSION['login_lock_until']);
                login_user($user);
                flash('success', 'Selamat datang, ' . ($user['nama'] ?: $user['username']) . '!');
                redirect(safe_next_url(is_string($next) ? $next : null, $user['role']));
            }
        }
    }
} else {
    // Normalisasi next agar tidak menyimpan URL berbahaya di form.
    $nextParam = $_GET['next'] ?? '';
}
$nextValue = $_POST['next'] ?? ($_GET['next'] ?? '');
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk — <?= e(APP_SHORT_NAME) ?></title>
  <link rel="icon" type="image/png" href="logoSMP.png">
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
  </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-800 antialiased flex items-center justify-center p-4 relative overflow-hidden">
  <div class="absolute -top-40 -left-40 w-[28rem] h-[28rem] bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-40 -right-40 w-[28rem] h-[28rem] bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

  <main class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-12 bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-800/40 relative z-10">
    <!-- Branding -->
    <section class="md:col-span-5 bg-gradient-to-br from-indigo-950 via-indigo-900 to-slate-900 p-6 sm:p-9 text-white flex flex-col justify-between">
      <div>
        <div class="flex items-center gap-3 mb-7">
          <div class="w-14 h-14 bg-white/95 rounded-2xl p-1.5 flex items-center justify-center shadow-lg">
            <img src="logoSMP.png" alt="Logo sekolah" class="w-full h-full object-contain">
          </div>
          <div>
            <h1 class="font-extrabold text-sm tracking-wide"><?= e(APP_SHORT_NAME) ?></h1>
            <p class="text-[11px] text-amber-300 font-semibold">Sistem Manajemen Akademik</p>
          </div>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold leading-tight">Satu akun untuk seluruh portal akademik.</h2>
        <p class="text-xs sm:text-sm text-indigo-200 mt-3 leading-relaxed">
          Administrator mengelola data induk, guru menginput nilai &amp; presensi, siswa memantau hasil belajar — semuanya tersimpan dalam satu database.
        </p>

        <ul class="mt-7 space-y-3 text-[11px] text-indigo-100">
          <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold">✓</span> Data tersinkron antar portal</li>
          <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold">✓</span> Kata sandi terenkripsi (bcrypt)</li>
          <li class="flex items-center gap-2.5"><span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold">✓</span> Sesi aman &amp; terlindungi CSRF</li>
        </ul>
      </div>
      <div class="mt-8 pt-5 border-t border-indigo-800/60 text-[11px] text-indigo-300 flex items-center justify-between">
        <span>T.A. <?= e(TAHUN_AJARAN) ?></span>
        <span class="bg-indigo-800/80 px-2 py-0.5 rounded-full text-amber-300 font-semibold border border-indigo-700">Kurikulum Merdeka</span>
      </div>
    </section>

    <!-- Form -->
    <section class="md:col-span-7 p-6 sm:p-10 flex flex-col justify-center">
      <div class="max-w-md w-full mx-auto">
        <h2 class="text-xl sm:text-2xl font-extrabold text-indigo-950">Masuk ke Akun Anda</h2>
        <p class="text-xs text-slate-500 mt-1">Gunakan username, NIP guru, atau NISN siswa beserta kata sandi.</p>

        <?php if ($justRegistered): ?>
          <div class="mt-4 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold flex items-start gap-2.5">
            <span class="text-base shrink-0">⏳</span>
            <div>
              <p class="font-bold">Pendaftaran Berhasil Dikirim!</p>
              <p class="mt-0.5 text-amber-800 font-normal">Akun Anda sedang dalam antrean persetujuan (approval) oleh Administrator sekolah sebelum dapat digunakan untuk masuk.</p>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <div class="mt-4 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1" role="alert">
            <?php foreach ($errors as $err): ?>
              <p class="font-semibold">⚠ <?= e($err) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="login.php" class="mt-5 space-y-4 text-xs">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="next" value="<?= e((string) $nextValue) ?>">

          <div>
            <label for="identifier" class="block font-bold text-slate-700 mb-1.5">Username / NIP / NISN</label>
            <input id="identifier" name="identifier" type="text" required autocomplete="username"
                   value="<?= e($old['identifier']) ?>" placeholder="contoh: admin / 1985xxxx / 0098xxxx"
                   class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 focus:outline-none">
          </div>

          <div>
            <label for="password" class="block font-bold text-slate-700 mb-1.5">Kata Sandi</label>
            <div class="relative">
              <input id="password" name="password" type="password" required autocomplete="current-password"
                     placeholder="Masukkan kata sandi"
                     class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 pr-11 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 focus:outline-none">
              <button type="button" onclick="togglePassword()" aria-label="Tampilkan kata sandi"
                      class="absolute inset-y-0 right-0 px-3.5 text-slate-400 hover:text-indigo-700 font-semibold text-[11px]">Lihat</button>
            </div>
          </div>

          <button type="submit" class="w-full py-3 bg-indigo-950 hover:bg-indigo-900 active:scale-[0.99] text-white font-bold rounded-xl shadow-md transition-all text-sm">
            Masuk Sekarang
          </button>
        </form>

        <p class="text-xs text-slate-500 mt-5 text-center">
          Belum punya akun?
          <a href="register.php" class="font-bold text-indigo-700 hover:text-indigo-900">Daftar di sini</a>
        </p>

        <div class="mt-6 pt-4 border-t border-slate-100 text-[11px] text-slate-500 leading-relaxed">
          <p><strong class="text-slate-700">Lupa kata sandi?</strong> Hubungi Tata Usaha sekolah dengan membawa kartu pelajar / identitas pegawai.</p>
        </div>
      </div>
    </section>
  </main>

  <script>
    function togglePassword() {
      const input = document.getElementById('password');
      input.type = input.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>
