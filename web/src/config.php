<?php
declare(strict_types=1);

/**
 * Loads .env file and bootstraps PDO + secure session.
 */

define('WEB_ROOT', dirname(__DIR__));

// For debugging: display PHP errors (remove or disable in production)
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ---------------------------------------------------------------------------
// Load .env
// ---------------------------------------------------------------------------
(function () {
    $envFile = WEB_ROOT . '/.env';
    if (!file_exists($envFile)) {
        return; // fall back to system env vars
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // If the value is quoted, keep the inner content as-is.
            if (preg_match('/^(\"|\')(.*)\\1$/', $value, $m)) {
                $value = $m[2];
            } else {
                // Strip inline comments starting with # (only when not quoted)
                $value = preg_replace('/\s+#.*$/', '', $value);
                $value = rtrim($value);
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key]         = $value;
                $_SERVER[$key]      = $value;
                putenv("{$key}={$value}");
            }
        }
    }
})();

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? (getenv($key) !== false ? getenv($key) : $default);
}

function site_base_path(?string $script = null): string
{
    $script ??= $_SERVER['SCRIPT_NAME'] ?? '/';
    $base = rtrim(dirname($script), '/');

    if ($base === '.' || $base === '/') {
        return '';
    }

    if (str_ends_with($base, '/app')) {
        $base = preg_replace('#/app$#', '', $base);
    }

    return $base;
}

function app_dashboard_url(?string $script = null): string
{
    $script ??= $_SERVER['SCRIPT_NAME'] ?? '/';

    if (str_contains($script, '/web/public')) {
        $base = site_base_path($script);
        return $base . '/app/index.php';
    }

    return '/cboemarkets/web/public/app/index.php';
}

function auth_login_url(?string $script = null): string
{
    $script ??= $_SERVER['SCRIPT_NAME'] ?? '/';

    if (str_contains($script, '/web/public')) {
        $base = site_base_path($script);
        return $base . '/index.php';
    }

    return '/cboemarkets/web/public/index.php';
}

// ---------------------------------------------------------------------------
// Secure session bootstrap
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('COMMAS_SESSION');
    session_start();

    // Regenerate on first access to prevent session fixation
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

// ---------------------------------------------------------------------------
// PDO singleton
// ---------------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'commas_web');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
