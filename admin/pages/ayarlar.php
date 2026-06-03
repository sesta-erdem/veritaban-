<?php

$settings = $code->getSiteSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf('index.php?do=ayarlar');

    $payload = [
        'site_url' => trim((string) post('site_url')),
        'site_baslik' => trim((string) post('site_baslik')),
        'site_desc' => trim((string) post('site_desc')),
        'marka_adi' => trim((string) post('marka_adi')),
        'marka_ust_tanim' => trim((string) post('marka_ust_tanim')),
        'telefon' => trim((string) post('telefon')),
        'eposta' => trim((string) post('eposta')),
        'adres' => trim((string) post('adres')),
        'harita_linki' => trim((string) post('harita_linki')),
        'bakim_modu' => (int) post('bakim_modu', 2),
    ];

    try {
        $code->saveSiteSettings($payload);
        flash_set('success', 'Site ayarları güncellendi.');
        redirect('index.php?do=ayarlar');
    } catch (InvalidArgumentException $error) {
        flash_set('danger', $error->getMessage());
        redirect('index.php?do=ayarlar');
    } catch (Throwable $error) {
        log_exception($error, ['page' => 'ayarlar']);
        flash_set('danger', 'Site ayarlari kaydedilemedi.');
        redirect('index.php?do=ayarlar');
    }
}

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Site Ayarları</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Site Ayarları</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <form method="post" class="card">
      <?= csrf_input() ?>
      <div class="card-header"><h3 class="card-title">Genel Ayarlar</h3></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Site URL</label>
            <input type="url" name="site_url" class="form-control" value="<?= e($settings['site_url']) ?>" required />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Site Başlığı</label>
            <input type="text" name="site_baslik" class="form-control" value="<?= e($settings['site_baslik']) ?>" required />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Site Açıklaması</label>
          <textarea name="site_desc" class="form-control" rows="3"><?= e($settings['site_desc']) ?></textarea>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Marka Adı</label>
            <input type="text" name="marka_adi" class="form-control" value="<?= e($settings['marka_adi']) ?>" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Marka Üst Tanımı</label>
            <input type="text" name="marka_ust_tanim" class="form-control" value="<?= e($settings['marka_ust_tanim']) ?>" />
          </div>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Telefon</label>
            <input type="text" name="telefon" class="form-control" value="<?= e($settings['telefon']) ?>" />
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">E-posta</label>
            <input type="email" name="eposta" class="form-control" value="<?= e($settings['eposta']) ?>" />
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Bakım Modu</label>
            <select name="bakim_modu" class="form-select">
              <option value="2" <?= selected_value($settings['bakim_modu'], 2) ?>>Kapalı</option>
              <option value="1" <?= selected_value($settings['bakim_modu'], 1) ?>>Açık</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Adres</label>
          <textarea name="adres" class="form-control" rows="3"><?= e($settings['adres']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Harita Linki</label>
          <input type="url" name="harita_linki" class="form-control" value="<?= e($settings['harita_linki']) ?>" />
        </div>
      </div>
      <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
      </div>
    </form>
  </div>
</div>
