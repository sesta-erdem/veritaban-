<?php

declare(strict_types=1);

ob_start();

const ADMIN_ROLE_SUPER = 1;
const ADMIN_ROLE_EDITOR = 2;
const ADMIN_PERMISSION_DASHBOARD_VIEW = 'dashboard.view';
const ADMIN_PERMISSION_CONTENT_READ = 'content.read';
const ADMIN_PERMISSION_CONTENT_WRITE = 'content.write';
const ADMIN_PERMISSION_MEDIA_READ = 'media.read';
const ADMIN_PERMISSION_MEDIA_WRITE = 'media.write';
const ADMIN_PERMISSION_CONTACTS_READ = 'contacts.read';
const ADMIN_PERMISSION_CONTACTS_WRITE = 'contacts.write';
const ADMIN_PERMISSION_USERS_MANAGE = 'users.manage';
const ADMIN_PERMISSION_SETTINGS_MANAGE = 'settings.manage';
const ADMIN_PERMISSION_SYSTEM_TOOLS = 'system.tools';
const ADMIN_PERMISSION_DEPLOY_RUN = 'deploy.run';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean_text(mixed $value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return strip_tags($value);
}

function clean_multiline_text(mixed $value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return strip_tags($value);
}

function clean_url_value(mixed $value): string
{
    $value = clean_text($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^\s*(javascript|data|vbscript):/i', $value)) {
        return '';
    }

    return $value;
}

function clean_record(array $data, array $fields, array $urlFields = [], array $multilineFields = []): array
{
    $clean = [];

    foreach ($fields as $field) {
        $value = $data[$field] ?? '';

        if (in_array($field, $urlFields, true)) {
            $clean[$field] = clean_url_value($value);
            continue;
        }

        if (in_array($field, $multilineFields, true)) {
            $clean[$field] = clean_multiline_text($value);
            continue;
        }

        $clean[$field] = clean_text($value);
    }

    return $clean;
}


function json_safe_payload(mixed $value): mixed
{
    if (is_array($value)) {
        $safe = [];

        foreach ($value as $key => $item) {
            $safe[$key] = json_safe_payload($item);
        }

        return $safe;
    }

    if (is_string($value)) {
        return clean_multiline_text($value);
    }

    return $value;
}

function post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function getv(string $key, mixed $default = ''): mixed
{
    return $_GET[$key] ?? $default;
}

function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

function active_nav(string $current, string $target): string
{
    return $current === $target ? 'active' : '';
}

function checked_value(mixed $value, mixed $expected): string
{
    return (string) $value === (string) $expected ? 'checked' : '';
}

function selected_value(mixed $value, mixed $expected): string
{
    return (string) $value === (string) $expected ? 'selected' : '';
}

function short_text(?string $value, int $limit = 160): string
{
    $value = trim((string) $value);
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $limit, '...', 'UTF-8');
    }

    return strlen($value) > $limit ? substr($value, 0, $limit) . '...' : $value;
}

function slugify(string $value): string
{
    $map = [
        'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i',
        'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u'
    ];
    $value = strtr($value, $map);
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'kayit';
}

function validate_required(?string $value): bool
{
    return trim((string) $value) !== '';
}

function validate_date_string(?string $value): bool
{
    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function validate_asset_path(?string $value): bool
{
    $value = trim((string) $value);
    if ($value === '' || !str_starts_with($value, '/')) {
        return false;
    }

    if (preg_match('/(?:\.\.|[\x00-\x1F\x7F]|[?#])/', $value) === 1) {
        return false;
    }

    $allowedPrefixes = ['/images/', '/uploads/'];
    $matchedPrefix = null;

    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($value, $prefix)) {
            $matchedPrefix = $prefix;
            break;
        }
    }

    if ($matchedPrefix === null) {
        return false;
    }

    $extension = strtolower((string) pathinfo($value, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'], true)) {
        return false;
    }

    $root = dirname(__DIR__, 2);
    $candidatePaths = [
        $root . '/public' . $value,
        $root . $value,
    ];
    $allowedRealRoots = array_filter(array_map('realpath', [
        $root . '/public/images',
        $root . '/public/uploads',
        $root . '/images',
        $root . '/uploads',
    ]));

    foreach ($candidatePaths as $candidatePath) {
        $resolved = realpath($candidatePath);
        if ($resolved === false || !is_file($resolved)) {
            continue;
        }

        foreach ($allowedRealRoots as $allowedRealRoot) {
            $prefix = rtrim((string) $allowedRealRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($resolved, $prefix)) {
                return true;
            }
        }
    }

    return false;
}

function pagination_params(int $defaultLimit = 25, int $maxLimit = 100): array
{
    $page = max((int) getv('page', 1), 1);
    $limit = max((int) getv('limit', $defaultLimit), 1);
    $limit = min($limit, $maxLimit);

    return [
        'page' => $page,
        'limit' => $limit,
    ];
}

function pagination_meta(int $total, int $page, int $limit): array
{
    $limit = min(max($limit, 1), 100);
    $total = max($total, 0);
    $totalPages = max((int) ceil($total / $limit), 1);
    $page = min(max($page, 1), $totalPages);
    $offset = ($page - 1) * $limit;

    return [
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset,
        'total_pages' => $totalPages,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
}

function render_admin_pagination(array $pagination, array $query = []): string
{
    $page = max((int) ($pagination['page'] ?? 1), 1);
    $limit = max((int) ($pagination['limit'] ?? 25), 1);
    $totalPages = max((int) ($pagination['total_pages'] ?? 1), 1);

    if ($totalPages <= 1) {
        return '';
    }

    $windowStart = max(1, $page - 2);
    $windowEnd = min($totalPages, $page + 2);

    if (($windowEnd - $windowStart) < 4) {
        if ($windowStart === 1) {
            $windowEnd = min($totalPages, $windowStart + 4);
        } elseif ($windowEnd === $totalPages) {
            $windowStart = max(1, $windowEnd - 4);
        }
    }

    $buildUrl = static function (int $targetPage) use ($query, $limit): string {
        $params = array_filter(
            array_merge($query, ['page' => $targetPage, 'limit' => $limit]),
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        return 'index.php?' . http_build_query($params);
    };

    ob_start();
    ?>
    <div class="card-footer d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
      <div class="text-secondary small">
        Sayfa <?= e((string) $page) ?> / <?= e((string) $totalPages) ?>
      </div>
      <nav aria-label="Sayfalama">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= $page <= 1 ? '#' : e($buildUrl($page - 1)) ?>"<?= $page <= 1 ? ' tabindex="-1" aria-disabled="true"' : '' ?>>Onceki</a>
          </li>
          <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
            <li class="page-item<?= $i === $page ? ' active' : '' ?>">
              <a class="page-link" href="<?= e($buildUrl($i)) ?>"><?= e((string) $i) ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= $page >= $totalPages ? '#' : e($buildUrl($page + 1)) ?>"<?= $page >= $totalPages ? ' tabindex="-1" aria-disabled="true"' : '' ?>>Sonraki</a>
          </li>
        </ul>
      </nav>
    </div>
    <?php

    return (string) ob_get_clean();
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function admin_role_permissions(): array
{
    return [
        ADMIN_ROLE_SUPER => [
            ADMIN_PERMISSION_DASHBOARD_VIEW,
            ADMIN_PERMISSION_CONTENT_READ,
            ADMIN_PERMISSION_CONTENT_WRITE,
            ADMIN_PERMISSION_MEDIA_READ,
            ADMIN_PERMISSION_MEDIA_WRITE,
            ADMIN_PERMISSION_CONTACTS_READ,
            ADMIN_PERMISSION_CONTACTS_WRITE,
            ADMIN_PERMISSION_USERS_MANAGE,
            ADMIN_PERMISSION_SETTINGS_MANAGE,
            ADMIN_PERMISSION_SYSTEM_TOOLS,
            ADMIN_PERMISSION_DEPLOY_RUN,
        ],
        ADMIN_ROLE_EDITOR => [
            ADMIN_PERMISSION_DASHBOARD_VIEW,
            ADMIN_PERMISSION_CONTENT_READ,
            ADMIN_PERMISSION_MEDIA_READ,
        ],
    ];
}

function current_admin_role(): int
{
    return (int) ($_SESSION['admin_user']['rutbe'] ?? 0);
}

function current_admin_permissions(): array
{
    return admin_role_permissions()[current_admin_role()] ?? [];
}

function has_admin_role(array $allowedRoles): bool
{
    return in_array(current_admin_role(), $allowedRoles, true);
}

function has_admin_permission(string $permission): bool
{
    return in_array($permission, current_admin_permissions(), true);
}

function has_any_admin_permission(array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (has_admin_permission((string) $permission)) {
            return true;
        }
    }

    return false;
}

function is_full_admin(): bool
{
    return has_admin_role([ADMIN_ROLE_SUPER]);
}

function can_manage_admin_content(): bool
{
    return has_any_admin_permission([ADMIN_PERMISSION_CONTENT_WRITE, ADMIN_PERMISSION_MEDIA_WRITE]);
}

function can_view_contact_requests(): bool
{
    return has_admin_permission(ADMIN_PERMISSION_CONTACTS_READ);
}

function can_view_system_tools(): bool
{
    return has_any_admin_permission([ADMIN_PERMISSION_SYSTEM_TOOLS, ADMIN_PERMISSION_DEPLOY_RUN]);
}

function admin_role_label(?int $role = null): string
{
    $role ??= current_admin_role();

    return match ($role) {
        ADMIN_ROLE_SUPER => 'Yonetici',
        ADMIN_ROLE_EDITOR => 'Editor',
        default => 'Kisitli',
    };
}

function require_admin_roles(array $allowedRoles, string $redirectUrl = 'index.php'): void
{
    if (has_admin_role($allowedRoles)) {
        return;
    }

    flash_set('danger', 'Bu islemi gerceklestirme yetkiniz yok.');
    redirect($redirectUrl);
}

function require_admin_permissions(array|string $permissions, string $redirectUrl = 'index.php'): void
{
    $permissions = is_array($permissions) ? $permissions : [$permissions];

    if (has_any_admin_permission($permissions)) {
        return;
    }

    flash_set('danger', 'Bu islemi gerceklestirme yetkiniz yok.');
    redirect($redirectUrl);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function request_header(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$key] ?? ''));
}

function verify_csrf(): bool
{
    $token = (string) post('csrf_token');

    if ($token === '') {
        $token = request_header('X-CSRF-Token');
    }

    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

    return $token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function require_post_csrf(string $redirectUrl): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf() || !is_same_origin_request()) {
        flash_set('danger', 'Gecersiz istek.');
        redirect($redirectUrl);
    }
}

function current_request_origin(): string
{
    $scheme = sesta_request_uses_https() ? 'https' : 'http';

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

    return $host === '' ? '' : $scheme . '://' . $host;
}

function normalize_origin_url(string $url): string
{
    $parts = parse_url($url);

    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) $parts['scheme']);
    $host = strtolower((string) $parts['host']);
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

    return $scheme . '://' . $host . $port;
}

function is_same_origin_request(): bool
{
    $expectedOrigin = current_request_origin();

    if ($expectedOrigin === '') {
        return false;
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        return normalize_origin_url($origin) === $expectedOrigin;
    }

    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        return normalize_origin_url($referer) === $expectedOrigin;
    }

    $fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '') {
        return in_array($fetchSite, ['same-origin', 'same-site', 'none'], true);
    }

    return false;
}

function allowed_request_origins(): array
{
    $configured = trim((string) (getenv('SESTA_ALLOWED_ORIGINS') ?: ''));
    $origins = $configured === ''
        ? []
        : array_values(array_filter(array_map('trim', explode(',', $configured)), static fn (string $origin): bool => $origin !== ''));

    $currentOrigin = current_request_origin();
    if ($currentOrigin !== '') {
        $origins[] = $currentOrigin;
    }

    $normalizedOrigins = [];
    foreach ($origins as $origin) {
        $normalized = normalize_origin_url($origin);
        if ($normalized !== '') {
            $normalizedOrigins[] = $normalized;
        }
    }

    return array_values(array_unique($normalizedOrigins));
}

function is_allowed_origin_request(?string $origin = null): bool
{
    $origin = trim((string) ($origin ?? ($_SERVER['HTTP_ORIGIN'] ?? '')));
    if ($origin === '') {
        return false;
    }

    $normalized = normalize_origin_url($origin);
    if ($normalized === '') {
        return false;
    }

    return in_array($normalized, allowed_request_origins(), true);
}

function send_public_api_security_headers(int $cacheTtlSeconds = 300): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=' . max($cacheTtlSeconds, 0));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), microphone=(), payment=(), usb=()');
    header('Vary: Origin');
}

function reject_disallowed_origin_json(string $message = 'Bu origin icin erisim izni yok.'): void
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return;
    }

    if (is_allowed_origin_request($origin)) {
        return;
    }

    reject_json(403, $message);
}

function reject_json(int $statusCode, string $message): never
{
    http_response_code($statusCode);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function require_same_origin_post_json(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        reject_json(405, 'Gecersiz istek yontemi.');
    }

    if (!is_same_origin_request()) {
        reject_json(403, 'Gecersiz istek kaynagi.');
    }
}

function rate_limit_client_ip(): string
{
    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $trustProxyHeaders = (getenv('SESTA_TRUST_PROXY_HEADERS') ?: '0') === '1';
    $shouldTrustForwardedHeaders = $trustProxyHeaders || sesta_is_trusted_proxy($remoteAddress);
    $candidates = [$remoteAddress];

    if ($shouldTrustForwardedHeaders) {
        array_unshift(
            $candidates,
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_REAL_IP'] ?? null
        );

        $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== '') {
            foreach (explode(',', $forwardedFor) as $part) {
                $candidates[] = trim($part);
            }
        }
    }

    foreach ($candidates as $candidate) {
        $ip = trim((string) $candidate);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

function rate_limit_cache_path(string $bucket, string $key): string
{
    $safeBucket = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($bucket)) ?: 'bucket';
    return ADMIN_CACHE_PATH . '/rate-limit-' . $safeBucket . '-' . sha1($key) . '.json';
}

function consume_rate_limit(string $bucket, string $key, int $maxAttempts, int $windowSeconds): bool
{
    $path = rate_limit_cache_path($bucket, $key);
    $now = time();
    $windowStart = $now - max($windowSeconds, 1);
    $attempts = [];

    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (is_array($decoded)) {
            foreach ($decoded as $timestamp) {
                $value = (int) $timestamp;
                if ($value >= $windowStart) {
                    $attempts[] = $value;
                }
            }
        }
    }

    if (count($attempts) >= max($maxAttempts, 1)) {
        return false;
    }

    $attempts[] = $now;
    @file_put_contents($path, json_encode($attempts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($path, 0660);

    return true;
}

function require_rate_limit_json(string $bucket, string $key, int $maxAttempts, int $windowSeconds): void
{
    if (!consume_rate_limit($bucket, $key, $maxAttempts, $windowSeconds)) {
        reject_json(429, 'Cok fazla deneme yapildi. Lutfen kisa bir sure sonra tekrar deneyin.');
    }
}

function require_rate_limit_or_redirect(string $bucket, string $key, int $maxAttempts, int $windowSeconds, string $redirectUrl): void
{
    if (consume_rate_limit($bucket, $key, $maxAttempts, $windowSeconds)) {
        return;
    }

    flash_set('danger', 'Cok fazla deneme yapildi. Lutfen kisa bir sure sonra tekrar deneyin.');
    redirect($redirectUrl);
}

function destroy_active_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function send_admin_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cross-Origin-Embedder-Policy: require-corp');
    header('Origin-Agent-Cluster: ?1');
    header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), microphone=(), payment=(), usb=()');
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (sesta_request_uses_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: https:; "
        . "font-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "frame-ancestors 'none'; "
        . "form-action 'self'"
    );
}

function current_admin_session_fingerprint(): string
{
    $userAgent = strtolower(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    return hash('sha256', $userAgent);
}

function enforce_admin_session_timeout(): void
{
    if (empty($_SESSION['admin_user'])) {
        return;
    }

    $now = time();
    $idleLimit = defined('ADMIN_SESSION_IDLE_TIMEOUT') ? ADMIN_SESSION_IDLE_TIMEOUT : 15 * 60;
    $absoluteLimit = defined('ADMIN_SESSION_ABSOLUTE_TIMEOUT') ? ADMIN_SESSION_ABSOLUTE_TIMEOUT : 8 * 60 * 60;

    $_SESSION['admin_session_created_at'] ??= $now;
    $_SESSION['admin_session_last_seen'] ??= $now;
    $_SESSION['admin_session_fingerprint'] ??= current_admin_session_fingerprint();

    $createdAt = (int) $_SESSION['admin_session_created_at'];
    $lastSeen = (int) $_SESSION['admin_session_last_seen'];
    $fingerprint = (string) ($_SESSION['admin_session_fingerprint'] ?? '');

    if ($fingerprint === '' || !hash_equals($fingerprint, current_admin_session_fingerprint())) {
        destroy_active_session();
        redirect('index.php?expired=1');
    }

    if (($now - $lastSeen) > $idleLimit || ($now - $createdAt) > $absoluteLimit) {
        destroy_active_session();
        redirect('index.php?expired=1');
    }

    $_SESSION['admin_session_last_seen'] = $now;
}

function run_static_site_sync(): array
{
    $root = dirname(__DIR__, 2);
    $cachePath = $root . '/.cache';

    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0777, true);
    }

    $basePath = getenv('PATH') ?: '';
    $path = 'export PATH=' . escapeshellarg($basePath . ':/opt/homebrew/bin:/usr/local/bin:/Applications/XAMPP/xamppfiles/bin:/usr/bin:/bin') . ';';
    $env = ' export HOME=' . escapeshellarg($cachePath)
        . ' XDG_CONFIG_HOME=' . escapeshellarg($cachePath)
        . ' npm_config_cache=' . escapeshellarg($cachePath . '/npm')
        . ' ASTRO_TELEMETRY_DISABLED=1;';
    $command = $path . $env . ' cd ' . escapeshellarg($root) . ' && npm run export:static && npm run deploy:apache 2>&1';

    $output = [];
    $exitCode = null;
    exec($command, $output, $exitCode);

    return [
        'output' => $output,
        'exitCode' => (int) $exitCode,
    ];
}

function sanitize_log_context(mixed $value): mixed
{
    $sensitiveKeys = ['pass', 'password', 'sifre', 'token', 'csrf', 'cookie', 'session', 'secret', 'key'];

    if (is_array($value)) {
        $clean = [];
        foreach ($value as $key => $item) {
            $keyString = strtolower((string) $key);
            $isSensitive = false;
            foreach ($sensitiveKeys as $needle) {
                if (str_contains($keyString, $needle)) {
                    $isSensitive = true;
                    break;
                }
            }
            $clean[$key] = $isSensitive ? '[MASKED]' : sanitize_log_context($item);
        }
        return $clean;
    }

    if (is_object($value)) {
        return '[OBJECT:' . $value::class . ']';
    }

    if (is_string($value)) {
        return mb_substr($value, 0, 1000, 'UTF-8');
    }

    return $value;
}

function log_security_event(string $event, array $context = []): void
{
    $safeContext = sanitize_log_context($context);
    error_log('[SESTA_SECURITY] ' . $event . ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function log_exception(Throwable $error, array $context = []): string
{
    $errorId = bin2hex(random_bytes(8));
    log_security_event('exception', [
        'error_id' => $errorId,
        'type' => $error::class,
        'message' => $error->getMessage(),
        'file' => $error->getFile(),
        'line' => $error->getLine(),
        'context' => $context,
    ]);

    return $errorId;
}

function register_safe_error_handlers(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    set_exception_handler(static function (Throwable $error): void {
        $errorId = log_exception($error, ['uri' => $_SERVER['REQUEST_URI'] ?? '', 'method' => $_SERVER['REQUEST_METHOD'] ?? '']);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo 'Beklenmeyen bir hata olustu. Hata kodu: ' . $errorId;
        exit;
    });

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        log_security_event('php_error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ]);

        return true;
    });
}

function secure_image_upload(array $file, string $targetDir, string $publicPrefix = '/uploads', int $maxBytes = 20971520): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Dosya yukleme islemi basarisiz oldu.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Gecersiz dosya yukleme istegi.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('Dosya boyutu en fazla 20 MB olabilir.');
    }

    $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimeTypes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Sadece jpg, jpeg, png ve webp dosyalari yuklenebilir.');
    }

    $mimeType = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mimeType = finfo_file($finfo, $tmpName) ?: null;
            finfo_close($finfo);
        }
    }

    if ($mimeType === null || !in_array($mimeType, $allowedMimeTypes[$extension] ?? [], true)) {
        throw new RuntimeException('Yuklenen dosyanin bicimi dogrulanamadi.');
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        throw new RuntimeException('Yuklenen dosya gecerli bir gorsel degil.');
    }

    $imageWidth = (int) ($imageInfo[0] ?? 0);
    $imageHeight = (int) ($imageInfo[1] ?? 0);

    if ($imageWidth <= 0 || $imageHeight <= 0 || $imageWidth > 8000 || $imageHeight > 8000) {
        throw new RuntimeException('Gorsel boyutlari desteklenen araligin disinda.');
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $storedBaseName = bin2hex(random_bytes(16));
    $optimizedPath = secure_image_upload_optimized_path(
        $tmpName,
        $mimeType,
        $storedBaseName,
        $targetDir
    );

    if ($optimizedPath !== null) {
        secure_image_generate_responsive_variants($optimizedPath);
        @chmod($optimizedPath, 0644);

        return rtrim($publicPrefix, '/') . '/' . basename($optimizedPath);
    }

    $storedName = $storedBaseName . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
    $targetPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Dosya kaydedilemedi.');
    }

    secure_image_generate_responsive_variants($targetPath);
    @chmod($targetPath, 0644);

    return rtrim($publicPrefix, '/') . '/' . $storedName;
}

function secure_image_responsive_widths(): array
{
    $configured = trim((string) UPLOAD_RESPONSIVE_WIDTHS);
    if ($configured === '') {
        return [];
    }

    $widths = array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', $configured)
    );

    $widths = array_values(array_filter(
        $widths,
        static fn (int $width): bool => $width >= 320
    ));

    sort($widths);

    return array_values(array_unique($widths));
}

function secure_image_upload_optimized_path(
    string $tmpName,
    string $mimeType,
    string $storedBaseName,
    string $targetDir
): ?string {
    $image = secure_image_create_resource($tmpName, $mimeType);
    if ($image === false) {
        return null;
    }

    $image = secure_image_apply_orientation($image, $tmpName, $mimeType);

    $sourceWidth = (int) imagesx($image);
    $sourceHeight = (int) imagesy($image);
    [$processedImage, $didResize] = secure_image_resize_resource(
        $image,
        $sourceWidth,
        $sourceHeight,
        (int) UPLOAD_IMAGE_MAX_WIDTH,
        (int) UPLOAD_IMAGE_MAX_HEIGHT
    );

    if ($processedImage !== $image) {
        imagedestroy($image);
    }

    $targetFormat = secure_image_upload_output_format($mimeType);
    $targetPath = rtrim($targetDir, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . $storedBaseName
        . '.'
        . $targetFormat;

    if (!secure_image_write_resource($processedImage, $targetPath, $targetFormat)) {
        imagedestroy($processedImage);
        @unlink($targetPath);
        return null;
    }

    clearstatcache(true, $tmpName);
    clearstatcache(true, $targetPath);

    $originalBytes = @filesize($tmpName);
    $optimizedBytes = @filesize($targetPath);

    if (
        $didResize === false
        && is_int($originalBytes)
        && is_int($optimizedBytes)
        && $optimizedBytes >= $originalBytes
    ) {
        imagedestroy($processedImage);
        @unlink($targetPath);
        return null;
    }

    imagedestroy($processedImage);
    @unlink($tmpName);

    return $targetPath;
}

function secure_image_upload_output_format(string $mimeType): string
{
    if (UPLOAD_IMAGE_OUTPUT_FORMAT === 'webp' && function_exists('imagewebp')) {
        return 'webp';
    }

    return match ($mimeType) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };
}

function secure_image_create_resource(string $tmpName, string $mimeType)
{
    return match ($mimeType) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpName) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpName) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpName) : false,
        default => false,
    };
}

function secure_image_apply_orientation($image, string $tmpName, string $mimeType)
{
    if ($mimeType !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($tmpName);
    if (!is_array($exif)) {
        return $image;
    }

    $orientation = (int) ($exif['Orientation'] ?? 1);
    $transformed = $image;

    switch ($orientation) {
        case 2:
            if (function_exists('imageflip')) {
                @imageflip($transformed, IMG_FLIP_HORIZONTAL);
            }
            break;
        case 3:
            $rotated = @imagerotate($transformed, 180, 0);
            if ($rotated !== false) {
                $transformed = $rotated;
            }
            break;
        case 4:
            if (function_exists('imageflip')) {
                @imageflip($transformed, IMG_FLIP_VERTICAL);
            }
            break;
        case 5:
            if (function_exists('imageflip')) {
                @imageflip($transformed, IMG_FLIP_VERTICAL);
            }
            $rotated = @imagerotate($transformed, 270, 0);
            if ($rotated !== false) {
                $transformed = $rotated;
            }
            break;
        case 6:
            $rotated = @imagerotate($transformed, 270, 0);
            if ($rotated !== false) {
                $transformed = $rotated;
            }
            break;
        case 7:
            if (function_exists('imageflip')) {
                @imageflip($transformed, IMG_FLIP_HORIZONTAL);
            }
            $rotated = @imagerotate($transformed, 270, 0);
            if ($rotated !== false) {
                $transformed = $rotated;
            }
            break;
        case 8:
            $rotated = @imagerotate($transformed, 90, 0);
            if ($rotated !== false) {
                $transformed = $rotated;
            }
            break;
    }

    if ($transformed !== $image) {
        imagedestroy($image);
    }

    return $transformed;
}

function secure_image_resize_resource($image, int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight): array
{
    if ($sourceWidth <= $maxWidth && $sourceHeight <= $maxHeight) {
        return [$image, false];
    }

    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $targetWidth = max(1, (int) round($sourceWidth * $ratio));
    $targetHeight = max(1, (int) round($sourceHeight * $ratio));

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($canvas === false) {
        return [$image, false];
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);

    if (!imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight)) {
        imagedestroy($canvas);
        return [$image, false];
    }

    return [$canvas, true];
}

function secure_image_write_resource($image, string $targetPath, string $targetFormat): bool
{
    switch ($targetFormat) {
        case 'png':
            imagealphablending($image, false);
            imagesavealpha($image, true);
            return function_exists('imagepng')
                ? @imagepng($image, $targetPath, (int) UPLOAD_IMAGE_PNG_COMPRESSION)
                : false;
        case 'webp':
            if (function_exists('imagepalettetotruecolor') && function_exists('imageistruecolor') && !imageistruecolor($image)) {
                @imagepalettetotruecolor($image);
            }
            imagealphablending($image, false);
            imagesavealpha($image, true);
            return function_exists('imagewebp')
                ? @imagewebp($image, $targetPath, (int) UPLOAD_IMAGE_QUALITY)
                : false;
        case 'jpg':
        default:
            if (function_exists('imageinterlace')) {
                @imageinterlace($image, true);
            }
            return function_exists('imagejpeg')
                ? @imagejpeg($image, $targetPath, (int) UPLOAD_IMAGE_QUALITY)
                : false;
    }
}

function secure_image_generate_responsive_variants(string $sourcePath): void
{
    $widths = secure_image_responsive_widths();
    if ($widths === [] || !is_file($sourcePath)) {
        return;
    }

    $extension = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return;
    }

    $imageInfo = @getimagesize($sourcePath);
    if ($imageInfo === false) {
        return;
    }

    $sourceWidth = (int) ($imageInfo[0] ?? 0);
    $sourceHeight = (int) ($imageInfo[1] ?? 0);
    $mimeType = (string) ($imageInfo['mime'] ?? '');

    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
        return;
    }

    $image = secure_image_create_resource($sourcePath, $mimeType);
    if ($image === false) {
        return;
    }

    $basePath = preg_replace('/\.[^.]+$/', '', $sourcePath) ?: $sourcePath;
    $targetFormat = $extension === 'jpeg' ? 'jpg' : $extension;

    foreach ($widths as $targetWidth) {
        if ($targetWidth >= $sourceWidth) {
            continue;
        }

        [$variantImage] = secure_image_resize_resource(
            $image,
            $sourceWidth,
            $sourceHeight,
            $targetWidth,
            $sourceHeight
        );

        if ($variantImage === false) {
            continue;
        }

        if ((int) imagesx($variantImage) >= $sourceWidth) {
            if ($variantImage !== $image) {
                imagedestroy($variantImage);
            }
            continue;
        }

        $variantPath = $basePath . '-w' . $targetWidth . '.' . $targetFormat;

        if (!secure_image_write_resource($variantImage, $variantPath, $targetFormat)) {
            @unlink($variantPath);
        } else {
            @chmod($variantPath, 0644);
        }

        if ($variantImage !== $image) {
            imagedestroy($variantImage);
        }
    }

    imagedestroy($image);
}


function app_cache_enabled(): bool
{
    return getenv('SESTA_CACHE_ENABLED') === '1';
}

function app_cache_backend(): string
{
    $backend = strtolower((string) (getenv('SESTA_CACHE_BACKEND') ?: 'file'));
    return in_array($backend, ['file', 'redis'], true) ? $backend : 'file';
}

function app_cache_dir(): string
{
    $dir = defined('ADMIN_CACHE_PATH') ? ADMIN_CACHE_PATH : dirname(__DIR__) . '/tmp/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 01777, true);
    }

    if (is_dir($dir)) {
        @chmod($dir, 01777);
    }

    return $dir;
}

function app_cache_key(string $namespace, string $key): string
{
    $safeNamespace = preg_replace('/[^a-z0-9_-]/i', '_', $namespace) ?: 'app';
    return $safeNamespace . ':' . hash('sha256', $key);
}

function app_cache_file_path(string $cacheKey): string
{
    return app_cache_dir() . '/' . hash('sha256', $cacheKey) . '.json';
}

function app_cache_redis(): ?Redis
{
    static $redis = false;

    if ($redis instanceof Redis) {
        return $redis;
    }

    if ($redis === null || !class_exists('Redis')) {
        return null;
    }

    $host = (string) (getenv('SESTA_REDIS_HOST') ?: '127.0.0.1');
    $port = (int) (getenv('SESTA_REDIS_PORT') ?: 6379);
    $timeout = (float) (getenv('SESTA_REDIS_TIMEOUT') ?: 0.25);

    try {
        $client = new Redis();
        if (!$client->connect($host, $port, $timeout)) {
            $redis = null;
            return null;
        }

        $password = getenv('SESTA_REDIS_PASSWORD');
        if (is_string($password) && $password !== '') {
            $client->auth($password);
        }

        $database = getenv('SESTA_REDIS_DATABASE');
        if (is_string($database) && $database !== '') {
            $client->select((int) $database);
        }

        $redis = $client;
        return $redis;
    } catch (Throwable $error) {
        $redis = null;
        log_security_event('cache_redis_unavailable', ['message' => $error->getMessage()]);
        return null;
    }
}

function app_cache_get(string $cacheKey): mixed
{
    if (!app_cache_enabled()) {
        return null;
    }

    if (app_cache_backend() === 'redis') {
        $redis = app_cache_redis();
        if ($redis instanceof Redis) {
            $raw = $redis->get($cacheKey);
            if (is_string($raw) && $raw !== '') {
                return json_decode($raw, true);
            }
        }
    }

    $path = app_cache_file_path($cacheKey);
    if (!is_file($path)) {
        return null;
    }

    $payload = json_decode((string) @file_get_contents($path), true);
    if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
        @unlink($path);
        return null;
    }

    return $payload['value'] ?? null;
}

function app_cache_set(string $cacheKey, mixed $value, int $ttlSeconds = 300): void
{
    if (!app_cache_enabled() || $ttlSeconds <= 0) {
        return;
    }

    if (app_cache_backend() === 'redis') {
        $redis = app_cache_redis();
        if ($redis instanceof Redis) {
            $redis->setex($cacheKey, $ttlSeconds, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }
    }

    $payload = [
        'expires_at' => time() + $ttlSeconds,
        'value' => $value,
    ];

    @file_put_contents(app_cache_file_path($cacheKey), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod(app_cache_file_path($cacheKey), 0600);
}

function app_cache_remember(string $namespace, string $key, int $ttlSeconds, callable $callback): mixed
{
    $cacheKey = app_cache_key($namespace, $key);
    $cached = app_cache_get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $value = $callback();
    app_cache_set($cacheKey, $value, $ttlSeconds);
    return $value;
}

function app_cache_clear(?string $namespace = null): void
{
    if (app_cache_backend() === 'redis') {
        $redis = app_cache_redis();
        if ($redis instanceof Redis) {
            // Namespace bazlı temizlik file cache kadar ucuz olmadığı için tüm app cache anahtarları yerine
            // küçük projede admin yazma işlemlerinden sonra file fallback temizliği esas alınır.
            return;
        }
    }

    $dir = app_cache_dir();
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        @unlink($file);
    }
}

register_safe_error_handlers();
