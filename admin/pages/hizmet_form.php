<?php

$id = (int) getv('id', 0);
$service = $id ? $code->getService($id) : null;
$deliverables = $id ? $code->getServiceDeliverables($id) : [];
$errors = [];
$mediaItems = $code->getActiveMediaItemsByCategory(['Hizmet', 'Genel', 'Hero']);
$formActionUrl = 'index.php?do=hizmet_form' . ($id ? '&id=' . $id : '');

if ($id && !$service) {
    flash_set('danger', 'Hizmet kaydı bulunamadı.');
    redirect('index.php?do=hizmetler');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($formActionUrl);

    $title = trim((string) post('baslik'));
    $slug = slugify(trim((string) post('slug')) ?: $title);

    $payload = [
        'sirasi' => (int) post('sirasi', 0),
        'baslik' => $title,
        'slug' => $slug,
        'kisa_ozet' => trim((string) post('kisa_ozet')),
        'detay' => trim((string) post('detay')),
        'gorsel' => trim((string) post('gorsel')),
        'ne_yapiyoruz' => trim((string) post('ne_yapiyoruz')),
        'hangi_ciktilar' => trim((string) post('hangi_ciktilar')),
        'kimler_icin' => trim((string) post('kimler_icin')),
        'surece_katkisi' => trim((string) post('surece_katkisi')),
        'durum' => (int) post('durum', 1),
    ];

    $postedDeliverables = [];
    foreach ((array) post('teslimler', []) as $deliverable) {
        $postedDeliverables[] = [
            'baslik' => trim((string) ($deliverable['baslik'] ?? '')),
            'aciklama' => trim((string) ($deliverable['aciklama'] ?? '')),
        ];
    }

    if (!validate_required($payload['baslik'])) {
        $errors[] = 'Hizmet basligi zorunludur.';
    }
    if (!validate_required($payload['kisa_ozet'])) {
        $errors[] = 'Kisa ozet zorunludur.';
    }
    if (!validate_required($payload['detay'])) {
        $errors[] = 'Detay metni zorunludur.';
    }
    if (!validate_asset_path($payload['gorsel'])) {
        $errors[] = 'Gorsel yolu public klasorundeki gecerli bir dosyayi gostermelidir.';
    }
    if ($code->serviceSlugExists($payload['slug'], $id ?: null)) {
        $errors[] = 'Bu hizmet slug zaten kullaniliyor.';
    }

    if (!$errors) {
        try {
            $code->saveService($payload, $postedDeliverables, $id ?: null);
            flash_set('success', $id ? 'Hizmet güncellendi.' : 'Hizmet eklendi.');
            redirect('index.php?do=hizmetler');
        } catch (InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        } catch (Throwable $error) {
            log_exception($error, ['page' => 'hizmet_form', 'id' => $id]);
            $errors[] = 'Hizmet kaydi kaydedilemedi.';
        }
    }
}

$defaults = [
    'sirasi' => 0,
    'baslik' => '',
    'slug' => '',
    'kisa_ozet' => '',
    'detay' => '',
    'gorsel' => '/images/services/shared-visual.png',
    'ne_yapiyoruz' => '',
    'hangi_ciktilar' => '',
    'kimler_icin' => '',
    'surece_katkisi' => '',
    'durum' => 1,
];

$service = array_merge($defaults, $service ?? []);

for ($i = count($deliverables); $i < 5; $i++) {
    $deliverables[] = ['baslik' => '', 'aciklama' => ''];
}

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0"><?= $id ? 'Hizmet Düzenle' : 'Yeni Hizmet' ?></h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php?do=hizmetler">Hizmetler</a></li>
          <li class="breadcrumb-item active"><?= $id ? 'Düzenle' : 'Ekle' ?></li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <form method="post" class="card">
      <?= csrf_input() ?>
      <div class="card-header"><h3 class="card-title">Hizmet Bilgileri</h3></div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?= e(implode(' ', $errors)) ?>
          </div>
        <?php endif; ?>
        <div class="row">
          <div class="col-md-2 mb-3">
            <label class="form-label">Sıra</label>
            <input type="number" name="sirasi" class="form-control" value="<?= e((string) $service['sirasi']) ?>" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Hizmet Başlığı</label>
            <input type="text" name="baslik" class="form-control" value="<?= e($service['baslik']) ?>" required />
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-control" value="<?= e($service['slug']) ?>" placeholder="Bos kalirsa otomatik uretilir ve normalize edilir" />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Kısa Özet</label>
          <textarea name="kisa_ozet" class="form-control" rows="3"><?= e($service['kisa_ozet']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Detay Metni</label>
          <textarea name="detay" class="form-control" rows="5"><?= e($service['detay']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Görsel Yolu</label>
          <input type="text" name="gorsel" class="form-control" value="<?= e($service['gorsel']) ?>" />
          <?php if ($mediaItems): ?>
            <select class="form-select mt-2" data-media-target="gorsel">
              <option value="">Kutuphane secin</option>
              <?php foreach ($mediaItems as $item): ?>
                <option value="<?= e($item['dosya_yolu']) ?>" <?= selected_value($service['gorsel'], $item['dosya_yolu']) ?>>
                  <?= e(($item['kategori'] ?: 'Genel') . ' / ' . $item['dosya_adi']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Ne Yapıyoruz</label>
            <textarea name="ne_yapiyoruz" class="form-control" rows="4"><?= e($service['ne_yapiyoruz']) ?></textarea>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Hangi Çıktılar</label>
            <textarea name="hangi_ciktilar" class="form-control" rows="4"><?= e($service['hangi_ciktilar']) ?></textarea>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Kimler İçin</label>
            <textarea name="kimler_icin" class="form-control" rows="4"><?= e($service['kimler_icin']) ?></textarea>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Sürece Katkısı</label>
            <textarea name="surece_katkisi" class="form-control" rows="4"><?= e($service['surece_katkisi']) ?></textarea>
          </div>
        </div>

        <h5 class="mt-4">Teslim Maddeleri</h5>
        <?php foreach ($deliverables as $index => $deliverable): ?>
          <div class="row border rounded p-3 mb-3">
            <div class="col-md-4 mb-3 mb-md-0">
              <label class="form-label">Başlık <?= $index + 1 ?></label>
              <input type="text" name="teslimler[<?= $index ?>][baslik]" class="form-control" value="<?= e($deliverable['baslik'] ?? '') ?>" />
            </div>
            <div class="col-md-8">
              <label class="form-label">Açıklama</label>
              <input type="text" name="teslimler[<?= $index ?>][aciklama]" class="form-control" value="<?= e($deliverable['aciklama'] ?? '') ?>" />
            </div>
          </div>
        <?php endforeach; ?>

        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Durum</label>
            <select name="durum" class="form-select">
              <option value="1" <?= selected_value($service['durum'], 1) ?>>Aktif</option>
              <option value="2" <?= selected_value($service['durum'], 2) ?>>Pasif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer text-end">
        <a href="index.php?do=hizmetler" class="btn btn-secondary">Vazgeç</a>
        <button type="submit" class="btn btn-primary">Kaydet</button>
      </div>
    </form>
  </div>
</div>
<script>
  document.querySelectorAll("[data-media-target]").forEach((select) => {
    select.addEventListener("change", (event) => {
      const target = event.currentTarget;
      if (!(target instanceof HTMLSelectElement)) return;
      const input = document.querySelector(`input[name="${target.dataset.mediaTarget}"]`);
      if (input instanceof HTMLInputElement && target.value !== "") {
        input.value = target.value;
      }
    });
  });
</script>
