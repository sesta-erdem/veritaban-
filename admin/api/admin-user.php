<?php

declare(strict_types=1);

require_once __DIR__ . '/../sistem/loader.php';
require_once __DIR__ . '/../sistem/classes/AdminUserApi.php';

send_admin_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
reject_disallowed_origin_json('Bu admin origin icin erisim izni yok.');

function admin_user_api_response(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_user_api_read_json_body(): array
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if ($contentType !== '' && !str_contains($contentType, 'application/json')) {
        throw new InvalidArgumentException('Sadece JSON istek gövdeleri desteklenir.');
    }

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        return [];
    }

    if (strlen($rawBody) > 65536) {
        throw new InvalidArgumentException('JSON gövdesi izin verilen boyutu aşıyor.');
    }

    try {
        $decoded = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new InvalidArgumentException('JSON gövdesi çözümlenemedi.', 0, $exception);
    }

    if (!is_array($decoded)) {
        throw new InvalidArgumentException('JSON gövdesi nesne tipinde olmalıdır.');
    }

    return $decoded;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$ipAddress = rate_limit_client_ip();
$currentUser = $code->currentUser();
$rateLimitKey = (string) (($currentUser['id'] ?? 'guest') . '|' . $ipAddress . '|' . $method);

if ($method === 'GET') {
    require_rate_limit_json('admin-user-api-read', $rateLimitKey, 60, 5 * 60);
} elseif (in_array($method, ['PATCH', 'POST'], true)) {
    require_rate_limit_json('admin-user-api-write', $rateLimitKey, 20, 15 * 60);
} else {
    require_rate_limit_json('admin-user-api-other', $rateLimitKey, 10, 15 * 60);
}

if (!is_array($currentUser) || empty($currentUser['id'])) {
    admin_user_api_response(401, [
        'ok' => false,
        'message' => 'Oturum doğrulanamadı.',
    ]);
}

$body = [];

if (in_array($method, ['PATCH', 'POST'], true)) {
    try {
        $body = admin_user_api_read_json_body();
    } catch (InvalidArgumentException $exception) {
        admin_user_api_response(422, [
            'ok' => false,
            'message' => $exception->getMessage(),
        ]);
    }
}

$handler = new AdminUserApiHandler(new AdminCodeAdminUserApiRepository($code));

try {
    $response = $handler->handle([
        'method' => $method,
        'actor' => $currentUser,
        'query' => [
            'id' => $_GET['id'] ?? null,
        ],
        'body' => $body,
        'csrf_valid' => in_array($method, ['PATCH', 'POST'], true) ? verify_csrf() : true,
        'same_origin' => in_array($method, ['PATCH', 'POST'], true) ? is_same_origin_request() : true,
    ]);

    if ($response->sessionUserUpdate !== null) {
        $_SESSION['admin_user'] = $response->sessionUserUpdate;
    }

    admin_user_api_response($response->status, $response->body);
} catch (Throwable $error) {
    $errorId = log_exception($error, [
        'endpoint' => 'admin-user',
        'actor_id' => (int) ($currentUser['id'] ?? 0),
        'method' => $method,
    ]);

    admin_user_api_response(500, [
        'ok' => false,
        'message' => 'İşlem tamamlanamadı. Lütfen daha sonra tekrar deneyin.',
        'error_id' => $errorId,
    ]);
}
