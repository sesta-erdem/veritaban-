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
require_rate_limit_json('public-api-projects', rate_limit_client_ip(), 240, 10 * 60);

function public_projects_payload(array $rows): array
{
    $projects = array_values(array_filter($rows, static function (array $row): bool {
        return (int) ($row['sil'] ?? 2) === 2 && (int) ($row['durum'] ?? 2) === 1;
    }));

    usort($projects, static function (array $left, array $right): int {
        $sort = (int) ($left['sirasi'] ?? 0) <=> (int) ($right['sirasi'] ?? 0);
        if ($sort !== 0) {
            return $sort;
        }

        return (int) ($left['proje_id'] ?? 0) <=> (int) ($right['proje_id'] ?? 0);
    });

    return array_map(static function (array $row): array {
        return [
            'proje_id' => (int) ($row['proje_id'] ?? 0),
            'sirasi' => (int) ($row['sirasi'] ?? 0),
            'proje_adi' => (string) ($row['proje_adi'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'kategori' => (string) ($row['kategori'] ?? ''),
            'sehir' => (string) ($row['sehir'] ?? ''),
            'yapi_tipi' => (string) ($row['yapi_tipi'] ?? ''),
            'kapsam' => (string) ($row['kapsam'] ?? ''),
            'rol' => (string) ($row['rol'] ?? ''),
            'teknik_cikti' => (string) ($row['teknik_cikti'] ?? ''),
            'tonaj' => (string) ($row['tonaj'] ?? ''),
            'metrekare' => (string) ($row['metrekare'] ?? ''),
            'teslim_suresi' => (string) ($row['teslim_suresi'] ?? ''),
            'kisa_ozet' => (string) ($row['kisa_ozet'] ?? ''),
            'veri_durumu' => (string) ($row['veri_durumu'] ?? ''),
            'veri_notu' => (string) ($row['veri_notu'] ?? ''),
            'one_cikan' => (int) ($row['one_cikan'] ?? 2),
            'tasarim_gorseli' => (string) ($row['tasarim_gorseli'] ?? ''),
            'final_gorseli' => (string) ($row['final_gorseli'] ?? ''),
            'durum' => (int) ($row['durum'] ?? 2),
        ];
    }, $projects);
}

try {
    $projects = app_cache_remember('public_api_projects', $_SERVER['REQUEST_URI'] ?? 'public_api_projects', 300, static function () use ($code): array {
        return public_projects_payload($code->getProjects());
    });

    echo json_encode(json_safe_payload($projects), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Projects API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Servis gecici olarak kullanilamiyor.']);
}
