<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/sistem/loader.php';

$root = dirname(__DIR__);
$generatedDir = $root . '/src/data/generated';
$blogDir = $root . '/src/content/blog';

if (!is_dir($generatedDir)) {
    mkdir($generatedDir, 0755, true);
}

if (!is_dir($blogDir)) {
    mkdir($blogDir, 0755, true);
}

foreach (glob($blogDir . '/*.md') ?: [] as $markdownFile) {
    if (is_file($markdownFile)) {
        unlink($markdownFile);
    }
}

function writeJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON üretilemedi: ' . $path);
    }

    file_put_contents($path, $json . PHP_EOL);
}

function splitLines(?string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim((string) $value)) ?: [];
    return array_values(array_filter(array_map('trim', $lines)));
}

function buildProjectGallery(?string $value): array
{
    $gallery = [];

    foreach (splitLines($value) as $index => $path) {
        if ($path === '' || !str_starts_with($path, '/')) {
            continue;
        }

        $gallery[] = [
            'src' => $path,
            'label' => 'Görsel ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'detail' => '',
        ];
    }

    return $gallery;
}

function frontmatterString(string $value): string
{
    return '"' . str_replace('"', '\"', $value) . '"';
}

function activePublishedRows(array $rows): array
{
    return array_values(array_filter($rows, static function (array $row): bool {
        return (int) ($row['sil'] ?? 2) === 2 && (int) ($row['durum'] ?? 2) === 1;
    }));
}

function sortRowsAscending(array $rows, string $sortField, string $idField): array
{
    usort($rows, static function (array $left, array $right) use ($sortField, $idField): int {
        $sort = (int) ($left[$sortField] ?? 0) <=> (int) ($right[$sortField] ?? 0);
        if ($sort !== 0) {
            return $sort;
        }

        return (int) ($left[$idField] ?? 0) <=> (int) ($right[$idField] ?? 0);
    });

    return $rows;
}

function sortRowsDescending(array $rows, string $idField): array
{
    usort($rows, static function (array $left, array $right) use ($idField): int {
        return (int) ($right[$idField] ?? 0) <=> (int) ($left[$idField] ?? 0);
    });

    return $rows;
}

function sortRowsByDateDescending(array $rows, string $dateField, string $idField): array
{
    usort($rows, static function (array $left, array $right) use ($dateField, $idField): int {
        $dateSort = strcmp((string) ($right[$dateField] ?? ''), (string) ($left[$dateField] ?? ''));
        if ($dateSort !== 0) {
            return $dateSort;
        }

        return (int) ($right[$idField] ?? 0) <=> (int) ($left[$idField] ?? 0);
    });

    return $rows;
}

try {
    $settings = $code->getSiteSettings();
    $heroSlides = sortRowsAscending(activePublishedRows($code->getHeroSlides()), 'sirasi', 'hero_id');
    $services = sortRowsAscending(activePublishedRows($code->getServices()), 'sirasi', 'hizmet_id');
    $projects = sortRowsAscending(activePublishedRows($code->getProjects()), 'sirasi', 'proje_id');
    $faqs = sortRowsAscending(activePublishedRows($code->getFaqs()), 'sirasi', 'faq_id');
    $media = sortRowsDescending(activePublishedRows($code->getMediaItems()), 'medya_id');
    $posts = sortRowsByDateDescending(activePublishedRows($code->getBlogPosts()), 'yayin_tarihi', 'yazi_id');
} catch (Throwable $exception) {
    $errorId = log_exception($exception, ['source' => 'export-static-procedure-read']);
    fwrite(STDERR, "Veri okunamadı. Hata ID: " . $errorId . PHP_EOL);
    fwrite(STDERR, "Stored procedure tanımları ve veritabanı bağlantısını kontrol edin." . PHP_EOL);
    exit(1);
}

writeJson($generatedDir . '/site.json', [
    'settings' => $settings,
    'heroSlides' => array_map(fn (array $slide): array => [
        'image' => $slide['gorsel'] ?? '',
        'eyebrow' => $slide['eyebrow'] ?? '',
        'title' => $slide['baslik'] ?? '',
        'subtitle' => $slide['alt_baslik'] ?? '',
        'text' => $slide['aciklama'] ?? '',
        'primaryCtaText' => $slide['cta_bir_metin'] ?? '',
        'primaryCtaLink' => $slide['cta_bir_link'] ?? '',
        'secondaryCtaText' => $slide['cta_iki_metin'] ?? '',
        'secondaryCtaLink' => $slide['cta_iki_link'] ?? '',
    ], $heroSlides),
]);

$deliverablesByService = [];
foreach ($services as $service) {
    $serviceId = (int) ($service['hizmet_id'] ?? 0);
    if ($serviceId <= 0) {
        continue;
    }

    foreach ($code->getServiceDeliverables($serviceId) as $deliverable) {
        if ((int) ($deliverable['durum'] ?? 1) !== 1) {
            continue;
        }

        $title = trim((string) ($deliverable['baslik'] ?? ''));
        if ($title === '') {
            continue;
        }

        $deliverablesByService[$serviceId][] = $title;
    }
}

$servicePayload = [];
foreach ($services as $service) {
    $serviceId = (int) ($service['hizmet_id'] ?? 0);

    $servicePayload[] = [
        'title' => $service['baslik'] ?? '',
        'slug' => slugify((string) ($service['slug'] ?: $service['baslik'])),
        'summary' => $service['kisa_ozet'] ?? '',
        'description' => $service['detay'] ?? '',
        'image' => $service['gorsel'] ?? '',
        'icon' => ((int) ($service['sirasi'] ?? 0) === 1) ? 'beam' : 'draft',
        'highlights' => splitLines($service['ne_yapiyoruz'] ?? ''),
        'outputs' => $deliverablesByService[$serviceId] ?? [],
        'audience' => $service['kimler_icin'] ?? '',
        'contribution' => $service['surece_katkisi'] ?? '',
    ];
}

writeJson($generatedDir . '/services.json', $servicePayload);

writeJson($generatedDir . '/projects.json', array_map(fn (array $project): array => [
    'title' => $project['proje_adi'] ?? '',
    'slug' => slugify((string) ($project['slug'] ?: $project['proje_adi'])),
    'category' => $project['kategori'] ?? '',
    'location' => $project['sehir'] ?? '',
    'structureType' => $project['yapi_tipi'] ?? '',
    'summary' => $project['kisa_ozet'] ?? '',
    'designImage' => $project['tasarim_gorseli'] ?? '',
    'finalImage' => $project['final_gorseli'] ?? '',
    'featured' => (int) ($project['one_cikan'] ?? 2) === 1,
    'scope' => $project['kapsam'] ?? '',
    'role' => $project['rol'] ?? '',
    'outputType' => $project['teknik_cikti'] ?? '',
    'dataStatus' => $project['veri_durumu'] ?? '',
    'dataNote' => $project['veri_notu'] ?? '',
    'tonnage' => $project['tonaj'] ?? '',
    'area' => $project['metrekare'] ?? '',
    'duration' => $project['teslim_suresi'] ?? '',
    'gallery' => buildProjectGallery($project['galeri_gorselleri'] ?? ''),
], $projects));

writeJson($generatedDir . '/faq.json', array_map(fn (array $faq): array => [
    'question' => $faq['soru'] ?? '',
    'answer' => $faq['cevap'] ?? '',
], $faqs));

writeJson($generatedDir . '/media.json', array_map(fn (array $item): array => [
    'name' => $item['dosya_adi'] ?? '',
    'path' => $item['dosya_yolu'] ?? '',
    'alt' => $item['alt_metin'] ?? '',
    'category' => $item['kategori'] ?? '',
], $media));

foreach ($posts as $post) {
    $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($post['etiketler'] ?? '')))));
    $markdown = "---\n";
    $markdown .= 'title: ' . frontmatterString((string) ($post['baslik'] ?? '')) . "\n";
    $markdown .= 'excerpt: ' . frontmatterString((string) ($post['excerpt'] ?? '')) . "\n";
    $markdown .= 'publishDate: ' . ($post['yayin_tarihi'] ?: date('Y-m-d')) . "\n";
    $markdown .= 'author: ' . frontmatterString((string) ($post['yazar'] ?? 'DOLUNAY MÜHENDİSLİK')) . "\n";
    $markdown .= 'coverImage: ' . frontmatterString((string) ($post['kapak_gorseli'] ?? '/images/hero/2.jpeg')) . "\n";
    $markdown .= "tags:\n";

    foreach ($tags as $tag) {
        $markdown .= '  - ' . frontmatterString($tag) . "\n";
    }

    $markdown .= "---\n\n" . trim((string) ($post['icerik'] ?? '')) . "\n";

    $slug = slugify((string) ($post['slug'] ?: $post['baslik']));
    file_put_contents($blogDir . '/' . $slug . '.md', $markdown);
}

echo "Static export tamamlandı.\n";
