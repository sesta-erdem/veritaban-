<?php

$id = (int) getv('id', 0);
$slide = $id ? $code->getHeroSlide($id) : null;
$formActionUrl = 'index.php?do=hero_form' . ($id ? '&id=' . $id : '');

if ($id && !$slide) {
    flash_set('danger', 'Hero slaytı bulunamadı.');
    redirect('index.php?do=hero');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($formActionUrl);

    $imagePath = trim((string) post('gorsel'));

    if (!empty($_FILES['gorsel_dosya']['name'])) {
        try {
            $imagePath = secure_image_upload(
                $_FILES['gorsel_dosya'],
                dirname(__DIR__, 2) . '/public/uploads',
                '/uploads'
            );
        } catch (Throwable $error) {
            log_exception($error, ['upload' => 'hero_image']);
            flash_set('danger', $error->getMessage());
            redirect($formActionUrl);
        }
    }

    $payload = [
        'sirasi' => (int) post('sirasi', 0),
        'eyebrow' => trim((string) post('eyebrow')),
        'baslik' => trim((string) post('baslik')),
        'alt_baslik' => trim((string) post('alt_baslik')),
        'aciklama' => trim((string) post('aciklama')),
        'gorsel' => $imagePath,
        'cta_bir_metin' => trim((string) post('cta_bir_metin')),
        'cta_bir_link' => trim((string) post('cta_bir_link')),
        'cta_iki_metin' => trim((string) post('cta_iki_metin')),
        'cta_iki_link' => trim((string) post('cta_iki_link')),
        'durum' => (int) post('durum', 1),
    ];

    try {
        $code->saveHeroSlide($payload, $id ?: null);
    } catch (InvalidArgumentException $error) {
        flash_set('danger', $error->getMessage());
        redirect($formActionUrl);
    } catch (Throwable $error) {
        log_exception($error, ['page' => 'hero_form', 'id' => $id]);
        flash_set('danger', 'Hero slayti kaydedilemedi.');
        redirect($formActionUrl);
    }

    $syncResult = run_static_site_sync();

    if (($syncResult['exitCode'] ?? 1) === 0) {
        flash_set('success', $id ? 'Hero slayti guncellendi ve anasayfaya yansitildi.' : 'Hero slayti eklendi ve anasayfaya yansitildi.');
    } else {
        flash_set('danger', $id ? 'Hero slayti guncellendi fakat anasayfa yayinina aktarilamadi.' : 'Hero slayti eklendi fakat anasayfa yayinina aktarilamadi.');
    }

    redirect('index.php?do=hero');
}

$defaults = [
    'sirasi' => 0,
    'eyebrow' => 'DOLUNAY MÜHENDİSLİK',
    'baslik' => '',
    'alt_baslik' => '',
    'aciklama' => '',
    'gorsel' => '/images/hero/great-new-workshop.jpeg',
    'cta_bir_metin' => 'Teklif Al',
    'cta_bir_link' => '/iletisim',
    'cta_iki_metin' => 'Projeleri İncele',
    'cta_iki_link' => '/projeler',
    'durum' => 1,
];

$slide = array_merge($defaults, $slide ?? []);

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0"><?= $id ? 'Hero Slaytı Düzenle' : 'Yeni Hero Slaytı' ?></h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php?do=hero">Hero</a></li>
          <li class="breadcrumb-item active"><?= $id ? 'Düzenle' : 'Ekle' ?></li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_input() ?>
      <div class="card-header"><h3 class="card-title">Slayt Bilgileri</h3></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-2 mb-3">
            <label class="form-label">Sıra</label>
            <input type="number" name="sirasi" class="form-control" value="<?= e((string) $slide['sirasi']) ?>" />
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Eyebrow</label>
            <input type="text" name="eyebrow" class="form-control" value="<?= e($slide['eyebrow']) ?>" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Başlık</label>
            <input type="text" name="baslik" class="form-control" value="<?= e($slide['baslik']) ?>" required />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Alt Başlık</label>
          <input type="text" name="alt_baslik" class="form-control" value="<?= e($slide['alt_baslik']) ?>" />
        </div>
        <div class="mb-3">
          <label class="form-label">Açıklama</label>
          <textarea name="aciklama" class="form-control" rows="4"><?= e($slide['aciklama']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Gorsel Yukle</label>
          <input type="file" name="gorsel_dosya" class="form-control" accept=".jpg,.jpeg,.png,.webp" />
          <div class="form-text">Isterseniz dogrudan buradan gorsel yukleyebilirsiniz. Sadece jpg, jpeg, png ve webp kabul edilir; dosya `public/uploads` icine kaydedilir.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Görsel Yolu</label>
          <input type="text" name="gorsel" class="form-control" value="<?= e($slide['gorsel']) ?>" />
          <div class="form-text">Ornek: /images/hero/bg-1.jpeg veya /uploads/yeni-slider.webp</div>
        </div>
        <?php if (!empty($slide['gorsel'])): ?>
          <div class="mb-3">
            <label class="form-label d-block">Mevcut Onizleme</label>
            <img src="..<?= e($slide['gorsel']) ?>" alt="" style="width: 240px; max-width: 100%; height: 140px; object-fit: cover; border-radius: 12px;" />
          </div>
        <?php endif; ?>
        <div class="mb-3">
          <div class="alert alert-info mb-0">
            Kaydettiginizde sistem slider degisikligini otomatik olarak anasayfaya yansitmayi dener.
          </div>
        </div>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">CTA 1 Metin</label>
            <input type="text" name="cta_bir_metin" class="form-control" value="<?= e($slide['cta_bir_metin']) ?>" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">CTA 1 Link</label>
            <input type="text" name="cta_bir_link" class="form-control" value="<?= e($slide['cta_bir_link']) ?>" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">CTA 2 Metin</label>
            <input type="text" name="cta_iki_metin" class="form-control" value="<?= e($slide['cta_iki_metin']) ?>" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">CTA 2 Link</label>
            <input type="text" name="cta_iki_link" class="form-control" value="<?= e($slide['cta_iki_link']) ?>" />
          </div>
        </div>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Durum</label>
            <select name="durum" class="form-select">
              <option value="1" <?= selected_value($slide['durum'], 1) ?>>Aktif</option>
              <option value="2" <?= selected_value($slide['durum'], 2) ?>>Pasif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer text-end">
        <a href="index.php?do=hero" class="btn btn-secondary">Vazgeç</a>
        <button type="submit" class="btn btn-primary">Kaydet</button>
      </div>
    </form>
  </div>
</div>
