<?php
/**
 * ==============================================================
 * DOWNLOAD / BACA MATERI
 * Siswa  : hanya modul Terbit milik rombelnya.
 * Guru   : pemilik modul, atau (modul Terbit) wali kelas / pengampu rombel.
 * Kepsek : bebas memantau seluruh modul di semua kelas.
 * File disimpan di storage/materi/ dan uploads_materi/
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/koneksi.php';

$user = require_login(['guru', 'siswa']);
$id = (int) ($_GET['id'] ?? 0);
$inline = (($_GET['mode'] ?? '') === 'view');

$row = db_select_one($koneksi, 'SELECT * FROM materi_modul WHERE id = ? LIMIT 1', 'i', [$id]);
if (!$row) {
    http_response_code(404);
    exit('Modul tidak ditemukan.');
}

$allowed = false;
$isOwner = false;

if ($user['role'] === 'siswa') {
    $s = db_select_one($koneksi, 'SELECT rombel_id FROM data_siswa WHERE user_id = ? LIMIT 1', 'i', [(int) $user['id']]);
    $allowed = $s && (int) $s['rombel_id'] === (int) $row['rombel_id'] && $row['status'] === 'Terbit';
} else {
    // Kepala Sekolah dapat melihat semua modul
    if (defined('KEPSEK_USERNAME') && ($user['username'] ?? '') === KEPSEK_USERNAME) {
        $allowed = true;
    }
    $g = db_select_one($koneksi, 'SELECT id FROM data_guru WHERE user_id = ? LIMIT 1', 'i', [(int) $user['id']]);
    if ($g) {
        $gid = (int) $g['id'];
        $isOwner = (int) ($row['guru_id'] ?? 0) === $gid;
        if ($isOwner) {
            $allowed = true;
        } elseif ($row['status'] === 'Terbit') {
            $wali = db_select_one($koneksi, 'SELECT id FROM rombel WHERE id = ? AND wali_guru_id = ?', 'ii', [(int) $row['rombel_id'], $gid]);
            $peng = db_select_one($koneksi, 'SELECT id FROM pengampu WHERE guru_id = ? AND rombel_id = ? LIMIT 1', 'ii', [$gid, (int) $row['rombel_id']]);
            $allowed = (bool) ($wali || $peng);
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Akses ditolak: modul ini bukan untuk Anda.');
}

$stored = basename((string) (($row['path_file'] ?? '') ?: ($row['file_path'] ?? '')));
$path = __DIR__ . '/storage/materi/' . $stored;
if (!is_file($path)) {
    $path = __DIR__ . '/uploads_materi/' . $stored;
}
if ($stored === '' || !is_file($path)) {
    http_response_code(404);
    exit('File modul tidak ditemukan di server.');
}

$ext = strtolower(pathinfo((string) $row['nama_file'], PATHINFO_EXTENSION));
$mimes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';
$showInBrowser = $inline && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);

if (!$showInBrowser && !$isOwner) {
    db_execute($koneksi, 'UPDATE materi_modul SET jumlah_unduh = jumlah_unduh + 1 WHERE id = ?', 'i', [$id]);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . ($showInBrowser ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode((string) $row['nama_file']));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
