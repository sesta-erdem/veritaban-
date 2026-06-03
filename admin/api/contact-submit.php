<?php

declare(strict_types=1);

require_once __DIR__ . '/../sistem/loader.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function wants_json_response(): bool
{
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $responseMode = strtolower(trim((string) ($_POST['response_mode'] ?? '')));

    return str_contains($accept, 'application/json')
        || $requestedWith === 'xmlhttprequest'
        || $responseMode === 'json';
}

function contact_fallback_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $needles = ['/admin/api/contact-submit.php', '/contact-submit.php'];

    foreach ($needles as $needle) {
        if ($scriptName !== '' && str_ends_with($scriptName, $needle)) {
            return substr($scriptName, 0, -strlen($needle)) . '/iletisim/';
        }
    }

    return '/iletisim/';
}

function contact_redirect_target(): string
{
    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $expectedOrigin = current_request_origin();

    if ($referer !== '' && $expectedOrigin !== '' && normalize_origin_url($referer) === $expectedOrigin) {
        $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '');
        $query = (string) (parse_url($referer, PHP_URL_QUERY) ?? '');

        if ($path !== '') {
            return $query !== '' ? $path . '?' . $query : $path;
        }
    }

    return contact_fallback_path();
}

function contact_redirect_with_status(string $status): never
{
    $target = contact_redirect_target();
    $parts = parse_url($target);
    $path = (string) ($parts['path'] ?? contact_fallback_path());
    $query = [];

    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }

    $query['contact_status'] = $status;
    $location = $path . '?' . http_build_query($query) . '#contact-form';

    header('Location: ' . $location, true, 303);
    exit;
}

function reject_contact_request(int $statusCode, string $message): never
{
    if (wants_json_response()) {
        reject_json($statusCode, $message);
    }

    contact_redirect_with_status('error');
}

function accept_contact_request(string $message): never
{
    if (wants_json_response()) {
        echo json_encode(['ok' => true, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    contact_redirect_with_status('success');
}

require_same_origin_post_json();

$ipAddress = rate_limit_client_ip();
require_rate_limit_json('contact-submit-ip', $ipAddress, 6, 10 * 60);

// Basic honeypot field for simple bot submissions.
if (!empty($_POST['website'] ?? '')) {
    accept_contact_request('Mesajınız alındı.');
}

$name = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$projectType = trim((string) ($_POST['projectType'] ?? ''));
$service = trim((string) ($_POST['service'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$consent = isset($_POST['consent']);

if ($name === '' || $phone === '' || $email === '' || $message === '' || !$consent) {
    reject_contact_request(422, 'Lütfen zorunlu alanları doldurun.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reject_contact_request(422, 'Geçerli bir e-posta adresi yazın.');
}

try {
    $validatedPayload = AdminInputGuard::sanitizeContactRequestPayload([
        'ad_soyad' => $name,
        'telefon' => $phone,
        'eposta' => $email,
        'proje_tipi' => $projectType,
        'hizmet_alani' => $service,
        'lokasyon' => $location,
        'mesaj' => $message,
        'ip_adresi' => $ipAddress,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
    require_rate_limit_json('contact-submit-email-ip', strtolower($validatedPayload['eposta']) . '|' . $ipAddress, 3, 30 * 60);
    $code->createContactRequest($validatedPayload);

    accept_contact_request('Mesajınız alındı. Kapsamı inceleyip size dönüş sağlayacağız.');
} catch (InvalidArgumentException $error) {
    reject_contact_request(422, $error->getMessage());
} catch (Throwable $error) {
    log_exception($error, ['endpoint' => 'contact-submit']);
    reject_contact_request(500, 'Mesaj kaydedilemedi. Lütfen daha sonra tekrar deneyin.');
}
