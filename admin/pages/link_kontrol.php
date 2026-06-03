<?php

declare(strict_types=1);

function link_check_scheme(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

    return $https ? 'https' : 'http';
}

function link_check_site_base_path(): string
{
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $adminDir = dirname($scriptPath);
    $sitePath = dirname($adminDir);

    return $sitePath === DIRECTORY_SEPARATOR ? '' : rtrim($sitePath, '/');
}

function link_check_site_base_url(): string
{
    $configured = trim((string) (getenv('SESTA_PUBLIC_SITE_URL') ?: ''));
    if ($configured !== '') {
        $sanitizedConfigured = rtrim($configured, '/');
        if (filter_var($sanitizedConfigured, FILTER_VALIDATE_URL)) {
            return $sanitizedConfigured;
        }
    }

    $host = preg_replace('/[^A-Za-z0-9\.\-\:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1')) ?: '127.0.0.1';
    return link_check_scheme() . '://' . $host . link_check_site_base_path();
}

function link_check_request(string $url, bool $includeBody = false): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Dolunay-Link-Check/1.0',
        CURLOPT_NOBODY => !$includeBody,
        CURLOPT_HEADER => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => $includeBody ? (string) $body : '',
        'error' => $error,
        'content_type' => $contentType,
    ];
}

function link_check_resolve_url(string $baseUrl, string $candidate): ?string
{
    $candidate = trim($candidate);
    if ($candidate === '' || str_starts_with($candidate, '#')) {
        return null;
    }

    foreach (['mailto:', 'tel:', 'javascript:', 'data:'] as $scheme) {
        if (stripos($candidate, $scheme) === 0) {
            return null;
        }
    }

    if (preg_match('~^https?://~i', $candidate)) {
        return preg_replace('/#.*$/', '', $candidate);
    }

    $baseParts = parse_url($baseUrl);
    if (!$baseParts || empty($baseParts['scheme']) || empty($baseParts['host'])) {
        return null;
    }

    $scheme = $baseParts['scheme'];
    $host = $baseParts['host'];
    $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

    if (str_starts_with($candidate, '//')) {
        return $scheme . ':' . preg_replace('/#.*$/', '', $candidate);
    }

    if (str_starts_with($candidate, '/')) {
        return $scheme . '://' . $host . $port . preg_replace('/#.*$/', '', $candidate);
    }

    $basePath = $baseParts['path'] ?? '/';
    $dir = preg_replace('~/[^/]*$~', '/', $basePath);
    $combined = $dir . $candidate;
    $segments = [];

    foreach (explode('/', $combined) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    return $scheme . '://' . $host . $port . '/' . implode('/', $segments);
}

function link_check_is_internal(string $url, string $siteBaseUrl, string $siteBasePath): bool
{
    $urlParts = parse_url($url);
    $baseParts = parse_url($siteBaseUrl);

    if (!$urlParts || !$baseParts) {
        return false;
    }

    $urlHost = strtolower((string) ($urlParts['host'] ?? ''));
    $baseHost = strtolower((string) ($baseParts['host'] ?? ''));
    if ($urlHost !== '' && $urlHost !== $baseHost) {
        return false;
    }

    $path = (string) ($urlParts['path'] ?? '/');
    $prefix = $siteBasePath === '' ? '/' : $siteBasePath . '/';

    return $path === $siteBasePath || str_starts_with($path, $prefix);
}

function link_check_is_html_candidate(string $url): bool
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
    if ($path === '' || str_ends_with($path, '/')) {
        return true;
    }

    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    return $extension === '' || $extension === 'html';
}

function link_check_collect_links(string $html, string $pageUrl): array
{
    $links = [];
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($document);
    $queries = [
        ['//a[@href]', 'href', 'page'],
        ['//link[@href]', 'href', 'asset'],
        ['//script[@src]', 'src', 'asset'],
        ['//img[@src]', 'src', 'asset'],
        ['//source[@src]', 'src', 'asset'],
        ['//form[@action]', 'action', 'page'],
    ];

    foreach ($queries as [$query, $attribute, $kind]) {
        foreach ($xpath->query($query) ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $value = trim((string) $node->getAttribute($attribute));
            if ($value === '') {
                continue;
            }

            $links[] = [
                'raw' => $value,
                'resolved' => link_check_resolve_url($pageUrl, $value),
                'kind' => $kind,
            ];
        }
    }

    return $links;
}

$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf('index.php?do=link_kontrol');

    $siteBaseUrl = link_check_site_base_url();
    $siteBasePath = link_check_site_base_path();
    $queue = [$siteBaseUrl . '/'];
    $visitedPages = [];
    $checkedLinks = [];
    $results = [];
    $maxPages = 40;

    while ($queue && count($visitedPages) < $maxPages) {
        $pageUrl = array_shift($queue);
        if (!$pageUrl || isset($visitedPages[$pageUrl])) {
            continue;
        }

        $visitedPages[$pageUrl] = true;
        $pageResponse = link_check_request($pageUrl, true);

        $results[$pageUrl] = [
            'url' => $pageUrl,
            'status' => $pageResponse['status'],
            'error' => $pageResponse['error'],
            'source' => 'Tarama girisi',
            'kind' => 'page',
        ];

        if ($pageResponse['status'] >= 400 || $pageResponse['error'] !== '') {
            continue;
        }

        if (!str_contains(strtolower($pageResponse['content_type']), 'text/html')) {
            continue;
        }

        foreach (link_check_collect_links($pageResponse['body'], $pageUrl) as $link) {
            $resolved = $link['resolved'];
            if ($resolved === null || !link_check_is_internal($resolved, $siteBaseUrl, $siteBasePath)) {
                continue;
            }

            if (!isset($checkedLinks[$resolved])) {
                $response = link_check_request($resolved, false);
                $checkedLinks[$resolved] = true;
                $results[$resolved] = [
                    'url' => $resolved,
                    'status' => $response['status'],
                    'error' => $response['error'],
                    'source' => $pageUrl,
                    'kind' => $link['kind'],
                ];
            }

            if ($link['kind'] === 'page' && link_check_is_html_candidate($resolved) && !isset($visitedPages[$resolved])) {
                $queue[] = $resolved;
            }
        }
    }

    $errors = [];
    $warnings = [];
    $ok = [];

    foreach ($results as $item) {
        $path = (string) (parse_url($item['url'], PHP_URL_PATH) ?? '');
        $hasUppercasePath = $item['kind'] === 'page'
            && $path !== ''
            && !str_starts_with($path, $siteBasePath . '/_astro/')
            && preg_match('/[A-Z]/', $path) === 1;

        if ($item['error'] !== '' || $item['status'] >= 400 || $item['status'] === 0) {
            $errors[] = $item;
            continue;
        }

        if (($item['status'] >= 300 && $item['status'] < 400) || $hasUppercasePath) {
            if ($hasUppercasePath) {
                $item['error'] = 'URL yolunda buyuk harf var. Linux sunucuda sorun cikarabilir.';
            }
            $warnings[] = $item;
            continue;
        }

        $ok[] = $item;
    }

    usort($errors, fn (array $a, array $b): int => strcmp($a['url'], $b['url']));
    usort($warnings, fn (array $a, array $b): int => strcmp($a['url'], $b['url']));

    $report = [
        'site_base_url' => $siteBaseUrl,
        'checked_count' => count($results),
        'visited_pages' => count($visitedPages),
        'errors' => $errors,
        'warnings' => $warnings,
        'ok_count' => count($ok),
        'generated_at' => date('d.m.Y H:i:s'),
    ];
}

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Link Kontrol</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Link Kontrol</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Dahili Link ve Dosya Taramasi</h3></div>
      <div class="card-body">
        <p class="text-secondary mb-3">
          Bu arac siteyi lokal yayin URL'i uzerinden tarar; sayfa linkleri, gorseller, CSS ve JS dosyalari icin durum kodu raporu olusturur.
        </p>
        <form method="post" class="mb-4">
          <?= csrf_input() ?>
          <button type="submit" class="btn btn-primary" onclick="this.disabled=true; this.form.submit();">
            <i class="bi bi-search"></i> Taramayi Baslat
          </button>
        </form>

        <?php if ($report): ?>
          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="small-box text-bg-primary">
                <div class="inner">
                  <h3><?= e((string) $report['checked_count']) ?></h3>
                  <p>Kontrol Edilen URL</p>
                </div>
                <i class="small-box-icon bi bi-link-45deg"></i>
              </div>
            </div>
            <div class="col-md-3">
              <div class="small-box text-bg-danger">
                <div class="inner">
                  <h3><?= e((string) count($report['errors'])) ?></h3>
                  <p>Hata</p>
                </div>
                <i class="small-box-icon bi bi-exclamation-triangle"></i>
              </div>
            </div>
            <div class="col-md-3">
              <div class="small-box text-bg-warning">
                <div class="inner">
                  <h3><?= e((string) count($report['warnings'])) ?></h3>
                  <p>Uyari</p>
                </div>
                <i class="small-box-icon bi bi-signpost-split"></i>
              </div>
            </div>
            <div class="col-md-3">
              <div class="small-box text-bg-success">
                <div class="inner">
                  <h3><?= e((string) $report['ok_count']) ?></h3>
                  <p>Sorunsuz</p>
                </div>
                <i class="small-box-icon bi bi-check-circle"></i>
              </div>
            </div>
          </div>

          <div class="alert alert-light border">
            <strong>Taranan site:</strong> <?= e($report['site_base_url']) ?><br />
            <strong>Taranan sayfa:</strong> <?= e((string) $report['visited_pages']) ?><br />
            <strong>Rapor zamani:</strong> <?= e($report['generated_at']) ?>
          </div>

          <div class="card card-danger card-outline mb-4">
            <div class="card-header"><h3 class="card-title">Hatalar</h3></div>
            <div class="card-body table-responsive p-0">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Durum</th>
                    <th>URL</th>
                    <th>Kaynak</th>
                    <th>Tip</th>
                    <th>Mesaj</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$report['errors']): ?>
                    <tr><td colspan="5" class="text-success">404 veya object not found hatasi bulunmadi.</td></tr>
                  <?php else: ?>
                    <?php foreach ($report['errors'] as $item): ?>
                      <tr>
                        <td><span class="badge text-bg-danger"><?= e((string) $item['status']) ?></span></td>
                        <td class="small"><?= e($item['url']) ?></td>
                        <td class="small"><?= e($item['source']) ?></td>
                        <td><?= e($item['kind']) ?></td>
                        <td class="small"><?= e($item['error'] ?: 'HTTP hatasi') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card card-warning card-outline">
            <div class="card-header"><h3 class="card-title">Uyarilar</h3></div>
            <div class="card-body table-responsive p-0">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Durum</th>
                    <th>URL</th>
                    <th>Kaynak</th>
                    <th>Tip</th>
                    <th>Mesaj</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$report['warnings']): ?>
                    <tr><td colspan="5" class="text-success">Yonlendirme veya buyuk harfli yol uyarisi bulunmadi.</td></tr>
                  <?php else: ?>
                    <?php foreach ($report['warnings'] as $item): ?>
                      <tr>
                        <td><span class="badge text-bg-warning"><?= e((string) $item['status']) ?></span></td>
                        <td class="small"><?= e($item['url']) ?></td>
                        <td class="small"><?= e($item['source']) ?></td>
                        <td><?= e($item['kind']) ?></td>
                        <td class="small"><?= e($item['error'] ?: 'Yonlendirme veya normalize edilmemis yol') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
