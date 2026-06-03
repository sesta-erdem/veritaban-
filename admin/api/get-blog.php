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
require_rate_limit_json('public-api-blog', rate_limit_client_ip(), 240, 10 * 60);

function public_blog_payload(array $rows): array
{
    $posts = array_values(array_filter($rows, static function (array $row): bool {
        return (int) ($row['sil'] ?? 2) === 2 && (int) ($row['durum'] ?? 2) === 1;
    }));

    usort($posts, static function (array $left, array $right): int {
        $dateSort = strcmp((string) ($right['yayin_tarihi'] ?? ''), (string) ($left['yayin_tarihi'] ?? ''));
        if ($dateSort !== 0) {
            return $dateSort;
        }

        return (int) ($right['yazi_id'] ?? 0) <=> (int) ($left['yazi_id'] ?? 0);
    });

    return array_map(static function (array $row): array {
        return [
            'yazi_id' => (int) ($row['yazi_id'] ?? 0),
            'baslik' => (string) ($row['baslik'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'excerpt' => (string) ($row['excerpt'] ?? ''),
            'kapak_gorseli' => (string) ($row['kapak_gorseli'] ?? ''),
            'yazar' => (string) ($row['yazar'] ?? ''),
            'yayin_tarihi' => (string) ($row['yayin_tarihi'] ?? ''),
            'etiketler' => (string) ($row['etiketler'] ?? ''),
            'icerik' => (string) ($row['icerik'] ?? ''),
            'seo_title' => (string) ($row['seo_title'] ?? ''),
            'seo_desc' => (string) ($row['seo_desc'] ?? ''),
        ];
    }, $posts);
}

try {
    $posts = app_cache_remember('public_api_blog', $_SERVER['REQUEST_URI'] ?? 'public_api_blog', 300, static function () use ($code): array {
        return public_blog_payload($code->getBlogPosts());
    });

    echo json_encode(json_safe_payload($posts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Blog API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Servis gecici olarak kullanilamiyor.']);
}
