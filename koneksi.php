<?php
/**
 * ==============================================================
 * KONEKSI DATABASE (MySQLi)
 * - Charset utf8mb4
 * - Mode exception agar error SQL tidak pernah bocor ke UI
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $koneksi->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('[DB] ' . $e->getMessage());
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    $pesan = APP_DEBUG
        ? 'Koneksi database gagal: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'Koneksi database gagal. Hubungi administrator sekolah.';
    die('<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Database Error</title></head>'
        . '<body style="font-family:system-ui;background:#0f172a;color:#e2e8f0;padding:3rem">'
        . '<h1 style="color:#fbbf24">Terjadi Kesalahan Sistem</h1><p>' . $pesan . '</p></body></html>');
}

/**
 * Menjalankan query SELECT dengan prepared statement.
 *
 * @param string $sql    Query dengan placeholder ?.
 * @param string $types  String tipe bind_param, contoh "iss".
 * @param array  $params Nilai yang di-bind.
 * @return array<int,array<string,mixed>>
 */
function db_select(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/**
 * Mengambil satu baris hasil query.
 */
function db_select_one(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_select($db, $sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * Menjalankan INSERT/UPDATE/DELETE dengan prepared statement.
 * @return int Jumlah baris terpengaruh.
 */
function db_execute(mysqli $db, string $sql, string $types = '', array $params = []): int
{
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/**
 * INSERT dan kembalikan insert_id.
 */
function db_insert(mysqli $db, string $sql, string $types = '', array $params = []): int
{
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();
    return (int) $id;
}
