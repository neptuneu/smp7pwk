<?php
/**
 * ==============================================================
 * AUTENTIKASI & MANAJEMEN SESI
 * --------------------------------------------------------------
 * - session_start() dengan cookie HttpOnly + SameSite
 * - Validasi role pada setiap halaman dan endpoint API
 * - CSRF token untuk seluruh operasi tulis (POST)
 * - Idle timeout & regenerasi session ID berkala
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    /**
     * Cek apakah folder benar-benar dapat dipakai menyimpan sesi.
     * is_writable() bisa memberi hasil negatif palsu pada Windows.
     */
    $sessionDirUsable = static function (string $dir): bool {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir)) {
            return false;
        }
        $probe = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.session_probe';
        return @file_put_contents($probe, 'ok') !== false;
    };

    // Simpan sesi di folder aplikasi agar tidak bergantung pada konfigurasi
    // session.save_path global (sering tidak dapat ditulis di XAMPP/cPanel).
    $sessionCandidates = [
        __DIR__ . '/storage/sessions',
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'smpn7_sessions',
        sys_get_temp_dir(),
    ];
    foreach ($sessionCandidates as $sessionDir) {
        if ($sessionDirUsable($sessionDir)) {
            session_save_path($sessionDir);
            break;
        }
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name(SESSION_NAME);
    session_start();
}

/* ==============================================================
 * HELPER UMUM
 * ============================================================== */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    if (!headers_sent()) {
        header('Location: ' . $url, true, 302);
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_api_request(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']);
    return $isPost || stripos($accept, 'application/json') !== false
        || strtolower($requestedWith) === 'xmlhttprequest';
}

/* ==============================================================
 * CSRF
 * ============================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(bool $throw = true): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $valid = is_string($token) && $token !== '' && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);

    if (!$valid && $throw) {
        if (is_api_request()) {
            json_response(['success' => false, 'message' => 'Sesi keamanan (CSRF) tidak valid. Muat ulang halaman.'], 419);
        }
        http_response_code(419);
        die('Sesi keamanan (CSRF) tidak valid. Silakan kembali dan muat ulang halaman.');
    }
    return $valid;
}

/* ==============================================================
 * SESI PENGGUNA
 * ============================================================== */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']['id']);
}

function user_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Login: regenerasi ID sesi (mencegah session fixation) lalu simpan data user.
 */
function login_user(array $userRow): void
{
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['user'] = [
        'id'         => (int) $userRow['id'],
        'username'   => $userRow['username'],
        'role'       => $userRow['role'],
        'nama'       => $userRow['nama'] ?? $userRow['username'],
        'profile_id' => isset($userRow['profile_id']) ? (int) $userRow['profile_id'] : null,
    ];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['created_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regenerate'] = time();
    $_SESSION['fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . APP_NAME);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

/**
 * Validasi sesi: fingerprint, idle timeout, regenerasi berkala.
 * @return bool true bila sesi masih sah.
 */
function validate_session(): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . APP_NAME);
    if (!hash_equals($_SESSION['fingerprint'] ?? '', $fingerprint)) {
        logout_user();
        return false;
    }

    $now = time();
    if (($now - (int) ($_SESSION['last_activity'] ?? $now)) > SESSION_IDLE_TIMEOUT) {
        logout_user();
        return false;
    }

    if (($now - (int) ($_SESSION['last_regenerate'] ?? $now)) > SESSION_REGENERATE_EVERY) {
        session_regenerate_id(true);
        $_SESSION['last_regenerate'] = $now;
    }

    $_SESSION['last_activity'] = $now;
    return true;
}

function role_home(string $role): string
{
    return match ($role) {
        'admin' => 'admin.php',
        'guru'  => 'guru.php',
        'siswa' => 'murid.php',
        default => 'login.php',
    };
}

/**
 * Pastikan pengguna sudah login (dan opsional memiliki role tertentu).
 *
 * @param string|array|null $allowedRoles Role yang diizinkan.
 * @param bool $api true = balas JSON 401/403, false = redirect ke login.
 */
function require_login(string|array|null $allowedRoles = null, bool $api = false): array
{
    if (!validate_session()) {
        if ($api) {
            json_response(['success' => false, 'message' => 'Sesi berakhir. Silakan login kembali.', 'code' => 'UNAUTHENTICATED'], 401);
        }
        $next = basename($_SERVER['PHP_SELF'] ?? 'index.php');
        redirect('login.php?next=' . urlencode($next));
    }

    $user = current_user();
    if ($allowedRoles !== null) {
        $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        if (!in_array($user['role'], $roles, true)) {
            if ($api) {
                json_response(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk aksi ini.', 'code' => 'FORBIDDEN'], 403);
            }
            http_response_code(403);
            $home = role_home($user['role']);
            die('<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>403</title></head>'
                . '<body style="font-family:system-ui;background:#0f172a;color:#e2e8f0;padding:3rem">'
                . '<h1 style="color:#f87171">403 — Akses Ditolak</h1>'
                . '<p>Halaman ini hanya untuk peran <strong>' . e(implode(', ', $roles)) . '</strong>.</p>'
                . '<p><a style="color:#fbbf24" href="' . e($home) . '">Kembali ke portal saya</a></p></body></html>');
        }
    }

    return $user;
}

/* ==============================================================
 * FLASH MESSAGE
 * ============================================================== */

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/**
 * Cegah open redirect: hanya izinkan halaman internal yang dikenal.
 */
function safe_next_url(?string $next, string $role): string
{
    $allowed = ['admin.php', 'guru.php', 'murid.php'];
    if ($next !== null) {
        $next = basename(parse_url($next, PHP_URL_PATH) ?: '');
        if (in_array($next, $allowed, true)) {
            return $next;
        }
    }
    return role_home($role);
}
