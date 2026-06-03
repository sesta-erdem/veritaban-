<?php

declare(strict_types=1);

$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
};

if (!function_exists('sesta_trusted_proxy_entries')) {
    function sesta_trusted_proxy_entries(): array
    {
        $configured = trim((string) (getenv('SESTA_TRUSTED_PROXIES') ?: ''));
        $entries = $configured === ''
            ? ['127.0.0.1', '::1']
            : array_values(array_filter(array_map('trim', explode(',', $configured)), static fn (string $value): bool => $value !== ''));

        return array_values(array_unique($entries));
    }
}

if (!function_exists('sesta_ip_matches_cidr')) {
    function sesta_ip_matches_cidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $maskBits = (int) $mask;
        $maxBits = strlen($ipBinary) * 8;

        if ($maskBits < 0 || $maskBits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($maskBits, 8);
        $remainingBits = $maskBits % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $maskByte = (0xFF << (8 - $remainingBits)) & 0xFF;
        $ipByte = ord($ipBinary[$fullBytes]);
        $subnetByte = ord($subnetBinary[$fullBytes]);

        return ($ipByte & $maskByte) === ($subnetByte & $maskByte);
    }
}

if (!function_exists('sesta_is_trusted_proxy')) {
    function sesta_is_trusted_proxy(?string $ip): bool
    {
        $ip = trim((string) $ip);

        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach (sesta_trusted_proxy_entries() as $entry) {
            if ($entry === $ip || sesta_ip_matches_cidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('sesta_request_uses_https')) {
    function sesta_request_uses_https(): bool
    {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https'
            || (getenv('SESTA_FORCE_SECURE_SESSION') ?: '0') === '1') {
            return true;
        }

        $trustProxyHeaders = (getenv('SESTA_TRUST_PROXY_HEADERS') ?: '0') === '1';
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

        return $forwardedProto === 'https' && ($trustProxyHeaders || sesta_is_trusted_proxy($remoteAddress));
    }
}

if (!function_exists('sesta_admin_cookie_path')) {
    function sesta_admin_cookie_path(): string
    {
        $configured = trim((string) (getenv('SESTA_ADMIN_COOKIE_PATH') ?: ''));
        if ($configured !== '') {
            return str_starts_with($configured, '/') ? rtrim($configured, '/') ?: '/' : '/' . trim($configured, '/');
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));

        if (preg_match('~^(.*?/admin)(?:/|$)~', $scriptName, $matches) === 1) {
            return rtrim((string) $matches[1], '/') ?: '/admin';
        }

        $baseDir = str_replace('\\', '/', (string) dirname($scriptName));
        $baseDir = $baseDir === '.' ? '/' : rtrim($baseDir, '/');

        if ($baseDir === '') {
            $baseDir = '/';
        }

        return $baseDir === '/' ? '/admin' : $baseDir . '/admin';
    }
}

define('DB_HOST', $env('SESTA_DB_HOST', '127.0.0.1'));
define('DB_PORT', $env('SESTA_DB_PORT', '3306'));
define('DB_NAME', $env('SESTA_DB_NAME', 'dolunay_admin'));
define('DB_USER', $env('SESTA_DB_USER', 'root'));
define('DB_PASS', $env('SESTA_DB_PASS', ''));
define('ADMIN_BASE', 'index.php');
define('PUBLIC_UPLOAD_PATH', __DIR__ . '/../uploads');
define('PUBLIC_UPLOAD_URL', 'uploads');
define('ADMIN_SESSION_IDLE_TIMEOUT', 15 * 60);
define('ADMIN_SESSION_ABSOLUTE_TIMEOUT', 8 * 60 * 60);
define('ADMIN_RUNTIME_PATH', rtrim($env('SESTA_RUNTIME_DIR', '/tmp/sesta-admin-runtime'), DIRECTORY_SEPARATOR));
define('ADMIN_SESSION_PATH', ADMIN_RUNTIME_PATH . '/sessions');
define('ADMIN_LOG_PATH', ADMIN_RUNTIME_PATH . '/logs');
define('ADMIN_CACHE_PATH', ADMIN_RUNTIME_PATH . '/cache');
define('ADMIN_COOKIE_PATH', sesta_admin_cookie_path());
define('UPLOAD_IMAGE_MAX_WIDTH', max(640, (int) $env('SESTA_UPLOAD_MAX_WIDTH', '2400')));
define('UPLOAD_IMAGE_MAX_HEIGHT', max(640, (int) $env('SESTA_UPLOAD_MAX_HEIGHT', '2400')));
define('UPLOAD_IMAGE_QUALITY', min(92, max(60, (int) $env('SESTA_UPLOAD_IMAGE_QUALITY', '82'))));
define('UPLOAD_IMAGE_PNG_COMPRESSION', min(9, max(0, (int) $env('SESTA_UPLOAD_PNG_COMPRESSION', '9'))));
define('UPLOAD_RESPONSIVE_WIDTHS', trim((string) $env('SESTA_UPLOAD_RESPONSIVE_WIDTHS', '480,768,1024,1280,1600')));

$uploadImageOutputFormat = strtolower((string) $env('SESTA_UPLOAD_IMAGE_OUTPUT_FORMAT', 'webp'));
if (!in_array($uploadImageOutputFormat, ['webp', 'original'], true)) {
    $uploadImageOutputFormat = 'webp';
}

define('UPLOAD_IMAGE_OUTPUT_FORMAT', $uploadImageOutputFormat);

$appDebug = $env('SESTA_APP_DEBUG', '0') === '1';

ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('html_errors', '0');
ini_set('expose_php', '0');
ini_set('allow_url_include', '0');
ini_set('default_charset', 'UTF-8');
ini_set('upload_max_filesize', '8M');
ini_set('post_max_size', '10M');
ini_set('max_file_uploads', '5');
error_reporting(E_ALL);
header_remove('X-Powered-By');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_lifetime', '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', (string) ADMIN_SESSION_ABSOLUTE_TIMEOUT);

$isHttps = sesta_request_uses_https();

if ($isHttps) {
    ini_set('session.cookie_secure', '1');
}

$ensureRuntimeDirectory = static function (string $path): void {
    if (!is_dir($path)) {
        @mkdir($path, 01777, true);
    }

    if (is_dir($path)) {
        @chmod($path, 01777);
    }
};

$ensureRuntimeDirectory(ADMIN_RUNTIME_PATH);
$ensureRuntimeDirectory(ADMIN_SESSION_PATH);
$ensureRuntimeDirectory(ADMIN_LOG_PATH);
$ensureRuntimeDirectory(ADMIN_CACHE_PATH);

ini_set('error_log', ADMIN_LOG_PATH . '/php_errors.log');

session_save_path(ADMIN_SESSION_PATH);
session_name('sesta_admin_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => ADMIN_COOKIE_PATH,
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
