<?php

declare(strict_types=1);

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../sistem/loader.php';

send_public_api_security_headers(300);
reject_disallowed_origin_json();
require_rate_limit_json('public-api-services', rate_limit_client_ip(), 240, 10 * 60);

function public_services_payload(array $rows): array
{
    $services = array_values(array_filter($rows, static function (array $row): bool {
        return (int) ($row['sil'] ?? 2) === 2 && (int) ($row['durum'] ?? 2) === 1;
    }));

    usort($services, static function (array $left, array $right): int {
        $sort = (int) ($left['sirasi'] ?? 0) <=> (int) ($right['sirasi'] ?? 0);
        if ($sort !== 0) {
            return $sort;
        }

        return (int) ($left['hizmet_id'] ?? 0) <=> (int) ($right['hizmet_id'] ?? 0);
    });

    return array_map(static function (array $row): array {
        return [
            'hizmet_id' => (int) ($row['hizmet_id'] ?? 0),
            'sirasi' => (int) ($row['sirasi'] ?? 0),
            'baslik' => (string) ($row['baslik'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'kisa_ozet' => (string) ($row['kisa_ozet'] ?? ''),
            'detay' => (string) ($row['detay'] ?? ''),
            'gorsel' => (string) ($row['gorsel'] ?? ''),
            'ne_yapiyoruz' => (string) ($row['ne_yapiyoruz'] ?? ''),
            'hangi_ciktilar' => (string) ($row['hangi_ciktilar'] ?? ''),
            'kimler_icin' => (string) ($row['kimler_icin'] ?? ''),
            'surece_katkisi' => (string) ($row['surece_katkisi'] ?? ''),
            'durum' => (int) ($row['durum'] ?? 2),
        ];
    }, $services);
}

try {
    $services = app_cache_remember('public_api_services', $_SERVER['REQUEST_URI'] ?? 'public_api_services', 300, static function () use ($code): array {
        return public_services_payload($code->getServices());
    });

    echo json_encode(json_safe_payload($services), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Services API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Servis gecici olarak kullanilamiyor.']);
}
