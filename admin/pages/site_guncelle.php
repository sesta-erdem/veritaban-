<?php

$hasRun = false;
$exitCode = null;
$output = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf('index.php?do=site_guncelle');
    $hasRun = true;
    
    $githubToken = getenv('SESTA_GITHUB_TOKEN') ?: '';
    $repoOwner = 'sesta-erdem';
    $repoName = 'dolunay';
    
    if (empty($githubToken)) {
        $exitCode = 1;
        $output = ['Hata: SESTA_GITHUB_TOKEN ayarlanmamis! Lutfen .htaccess dosyaniza ekleyin.'];
    } else {
        $ch = curl_init("https://api.github.com/repos/{$repoOwner}/{$repoName}/dispatches");
        $payload = json_encode(['event_type' => 'deploy-site']);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.github.v3+json',
            'Authorization: token ' . $githubToken,
            'User-Agent: Sesta-Admin-Panel',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $exitCode = 0;
            $output = ['Basarili! GitHub Actions derleme islemi baslatildi. Yaklasik 1-2 dakika icinde site guncellenecektir.'];
        } else {
            $exitCode = 1;
            $output = ['GitHub tetikleme hatasi: ' . $httpCode, 'Yanit: ' . $response];
        }
    }

    if ($exitCode === 0) {
        flash_set('success', 'Site başarıyla güncellendi.');
        redirect('index.php?do=site_guncelle&ok=1');
    }
}

$success = (int) getv('ok', 0) === 1;

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Siteyi Güncelle</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Siteyi Güncelle</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Static Site Yayını</h3></div>
      <div class="card-body">
        <?php if ($success): ?>
          <div class="alert alert-success">Son içerik değişiklikleri siteye aktarıldı.</div>
        <?php endif; ?>
        <?php if ($hasRun && $exitCode !== 0): ?>
          <div class="alert alert-danger">Site güncellenemedi. Komut çıktısını kontrol edin.</div>
        <?php endif; ?>
        <p class="text-secondary">
          Admin panelde yapılan MySQL değişikliklerini Astro static site dosyalarına aktarır ve Apache altındaki yayını yeniler.
        </p>
        <form method="post" id="site-sync-form">
          <?= csrf_input() ?>
          <button type="submit" class="btn btn-primary" id="site-sync-button">
            <i class="bi bi-arrow-repeat"></i> Siteyi Güncelle
          </button>
          <a href="../" target="_blank" class="btn btn-outline-secondary">Siteyi Aç</a>
        </form>
        <?php if ($hasRun && $output): ?>
          <pre class="mt-4 rounded bg-dark p-3 text-white small" style="max-height: 420px; overflow: auto; white-space: pre-wrap;"><?= e(implode("\n", $output)) ?></pre>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
  (() => {
    const form = document.getElementById('site-sync-form');
    const button = document.getElementById('site-sync-button');

    if (!form || !button) return;

    form.addEventListener('submit', () => {
      button.disabled = true;
      button.innerHTML = '<i class="bi bi-arrow-repeat"></i> Güncelleniyor...';
    });
  })();
</script>
