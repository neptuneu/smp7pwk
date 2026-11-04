<?php
/**
 * ==============================================================
 * PEMERIKSA STATUS INSTALASI DATABASE
 * --------------------------------------------------------------
 * Skema TIDAK lagi dibuat otomatis dari file ini (dulu berisi data
 * dummy). Jalankan database/schema.sql terlebih dahulu, lalu
 * database/migrate_legacy.php bila memigrasikan data lama.
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/koneksi.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    require_once __DIR__ . '/auth.php';
    require_login('admin');
}

$tables = [
    'users', 'data_guru', 'rombel', 'data_siswa', 'mata_pelajaran',
    'pengampu', 'nilai_siswa', 'jurnal_guru', 'presensi',
    'arsip_siswa', 'arsip_guru', 'pengaturan',
];

$status = [];
foreach ($tables as $table) {
    $row = db_select_one(
        $koneksi,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        's',
        [$table]
    );
    $exists = $row !== null && (int) $row['c'] > 0;
    $count = 0;
    if ($exists) {
        $countRow = db_select_one($koneksi, 'SELECT COUNT(*) AS c FROM `' . $table . '`');
        $count = (int) ($countRow['c'] ?? 0);
    }
    $status[$table] = ['exists' => $exists, 'count' => $count];
}

$legacyTables = [];
foreach (['legacy_data_guru', 'legacy_data_siswa', 'legacy_nilai_siswa', 'legacy_jurnal_guru'] as $legacy) {
    $row = db_select_one(
        $koneksi,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        's',
        [$legacy]
    );
    if ($row !== null && (int) $row['c'] > 0) {
        $legacyTables[] = $legacy;
    }
}

$allReady = true;
foreach ($status as $info) {
    if (!$info['exists']) {
        $allReady = false;
        break;
    }
}

if ($isCli) {
    echo "=== STATUS INSTALASI ===\n";
    foreach ($status as $name => $info) {
        echo str_pad($name, 20) . ($info['exists'] ? 'OK  (' . $info['count'] . ' baris)' : 'BELUM ADA') . "\n";
    }
    echo $allReady ? "Skema siap dipakai.\n" : "Skema belum lengkap. Impor database/schema.sql.\n";
    if ($legacyTables) {
        echo 'Tabel lama masih tersimpan sebagai cadangan: ' . implode(', ', $legacyTables) . ".\n";
        echo "Data sudah dimigrasikan ke skema baru. Hapus tabel legacy setelah Anda yakin dengan backup.\n";
    }
    exit($allReady ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Status Instalasi — <?= e(APP_SHORT_NAME) ?></title>
  <link rel="icon" type="image/png" href="logoSMP.png">
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
</head>
<body class="bg-slate-100 min-h-screen p-4 sm:p-8 text-slate-800">
  <div class="max-w-3xl mx-auto space-y-5">
    <div class="bg-indigo-950 text-white rounded-3xl p-6">
      <h1 class="text-xl font-extrabold">Status Instalasi Database</h1>
      <p class="text-xs text-indigo-200 mt-1">Database: <code><?= e(DB_NAME) ?></code> · Tabel diperiksa: <?= count($tables) ?></p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-5">
      <h2 class="font-bold text-sm mb-3">Tabel Aplikasi</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
        <?php foreach ($status as $name => $info): ?>
          <div class="flex items-center justify-between p-2.5 rounded-xl border <?= $info['exists'] ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200' ?>">
            <span class="font-mono font-semibold"><?= e($name) ?></span>
            <span class="font-bold <?= $info['exists'] ? 'text-emerald-700' : 'text-rose-700' ?>">
              <?= $info['exists'] ? $info['count'] . ' baris' : 'Belum ada' ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($legacyTables): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-900">
        <p class="font-bold">Tabel data lama masih tersimpan sebagai cadangan</p>
        <p class="mt-1"><?= e(implode(', ', $legacyTables)) ?>. Data sudah dimigrasikan ke skema baru. Hapus tabel legacy setelah Anda yakin memiliki backup.</p>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-200 p-5 text-xs space-y-2">
      <h2 class="font-bold text-sm">Langkah Instalasi</h2>
      <ol class="list-decimal ml-5 space-y-1 text-slate-600">
        <li>Impor <code class="bg-slate-100 px-1.5 py-0.5 rounded">database/schema.sql</code> melalui phpMyAdmin / terminal MySQL.</li>
        <li>Jika memigrasikan data lama, rename tabel lama lalu jalankan <code class="bg-slate-100 px-1.5 py-0.5 rounded">php database/migrate_legacy.php</code>.</li>
        <li>Masuk sebagai admin (akun seed: <strong>admin / admin12345</strong>) lalu ganti kata sandi.</li>
        <li>Buka halaman ini kembali untuk memastikan seluruh tabel berstatus OK.</li>
      </ol>
      <p class="pt-2"><a href="admin.php" class="font-bold text-indigo-700 hover:underline">&larr; Kembali ke Dashboard Admin</a></p>
    </div>
  </div>
</body>
</html>
