<?php
/**
 * ==============================================================
 * DASHBOARD ADMINISTRATOR
 * --------------------------------------------------------------
 * Halaman ini HANYA dapat diakses oleh sesi dengan role "admin".
 * Seluruh data tabel dimuat dari database melalui api.php (AJAX).
 * Tidak ada lagi data dummy / array statis di frontend.
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php';

$user = require_login('admin');
$adminName = $user['nama'] ?: 'Administrator';
$adminInitials = strtoupper(mb_substr($adminName, 0, 2));
$csrf = csrf_token();
$flashSuccess = flash('success');

require __DIR__ . '/views/admin_body.php';
?>

<script>
  window.ADMIN_CONFIG = {
    csrf: <?= json_encode($csrf) ?>,
    userName: <?= json_encode($adminName) ?>,
    userInitials: <?= json_encode($adminInitials) ?>,
    flashSuccess: <?= json_encode($flashSuccess) ?>,
    apiUrl: 'api.php'
  };
</script>
<script src="assets/admin-app.js?v=<?= (int) @filemtime(__DIR__ . '/assets/admin-app.js') ?>"></script>
</body>
</html>
