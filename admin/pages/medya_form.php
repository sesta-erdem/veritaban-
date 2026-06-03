<?php

$id = (int) getv('id', 0);
$item = $id ? $code->getMediaItem($id) : null;
$formActionUrl = 'index.php?do=medya_form' . ($id ? '&id=' . $id : '');
$normalizeUploadedFiles = static function (?array $fileField): array {
    if (!$fileField || !array_key_exists('name', $fileField)) {
        return [];
    }

    if (!is_array($fileField['name'])) {
        $name = trim((string) ($fileField['name'] ?? ''));
        if ($name === '' && (int) ($fileField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [[
            'name' => $fileField['name'] ?? '',
            'type' => $fileField['type'] ?? '',
            'tmp_name' => $fileField['tmp_name'] ?? '',
            'error' => $fileField['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileField['size'] ?? 0,
        ]];
    }

    $files = [];
    foreach ($fileField['name'] as $index => $name) {
        $trimmedName = trim((string) $name);
        $errorCode = (int) ($fileField['error'][$index] ?? UPLOAD_ERR_NO_FILE);

        if ($trimmedName === '' && $errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => $fileField['name'][$index] ?? '',
            'type' => $fileField['type'][$index] ?? '',
            'tmp_name' => $fileField['tmp_name'][$index] ?? '',
            'error' => $errorCode,
            'size' => $fileField['size'][$index] ?? 0,
        ];
    }

    return $files;
};
$deriveUploadLabel = static function (array $file): string {
    $baseName = trim((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_FILENAME));
    return $baseName !== '' ? $baseName : 'Medya Gorseli';
};

if ($id && !$item) {
    flash_set('danger', 'Medya kaydı bulunamadı.');
    redirect('index.php?do=medya');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($formActionUrl);

    $filePath = trim((string) post('dosya_yolu'));
    $fileName = trim((string) post('dosya_adi'));
    $altText = trim((string) post('alt_metin'));
    $category = trim((string) post('kategori'));
    $status = (int) post('durum', 1);
    $uploadedFiles = $normalizeUploadedFiles($_FILES['dosyalar'] ?? null);

    if ($id && count($uploadedFiles) > 1) {
        flash_set('danger', 'Mevcut medya kaydini guncellerken tek dosya secilebilir. Toplu yukleme icin Yeni Medya ekranini kullanin.');
        redirect($formActionUrl);
    }

    if (!$id && count($uploadedFiles) > 1) {
        $createdCount = 0;
        $uploadErrors = [];

        foreach ($uploadedFiles as $uploadedFile) {
            try {
                $storedPath = secure_image_upload(
                    $uploadedFile,
                    dirname(__DIR__, 2) . '/public/uploads',
                    '/uploads'
                );
                $uploadLabel = $deriveUploadLabel($uploadedFile);
                $resolvedName = $fileName !== ''
                    ? $fileName . ' - ' . $uploadLabel
                    : $uploadLabel;
                $resolvedAlt = $altText !== ''
                    ? $altText . ' - ' . $uploadLabel
                    : $uploadLabel;

                $code->saveMediaItem([
                    'dosya_adi' => $resolvedName,
                    'dosya_yolu' => $storedPath,
                    'alt_metin' => $resolvedAlt,
                    'kategori' => $category,
                    'durum' => $status,
                ]);

                $createdCount++;
            } catch (Throwable $error) {
                log_exception($error, ['upload' => 'media_batch_file', 'file' => $uploadedFile['name'] ?? '']);
                $uploadErrors[] = sprintf(
                    '`%s` yuklenemedi: %s',
                    (string) ($uploadedFile['name'] ?? 'dosya'),
                    $error->getMessage()
                );
            }
        }

        if ($createdCount > 0) {
            flash_set('success', $createdCount . ' medya kaydi eklendi.');
        }

        if ($uploadErrors) {
            flash_set('danger', implode(' ', $uploadErrors));
        }

        redirect($createdCount > 0 ? 'index.php?do=medya' : $formActionUrl);
    }

    if (count($uploadedFiles) === 1) {
        try {
            $filePath = secure_image_upload(
                $uploadedFiles[0],
                dirname(__DIR__, 2) . '/public/uploads',
                '/uploads'
            );
            $fileName = $fileName !== '' ? $fileName : $deriveUploadLabel($uploadedFiles[0]);
        } catch (Throwable $error) {
            log_exception($error, ['upload' => 'media_file']);
            flash_set('danger', $error->getMessage());
            redirect($formActionUrl);
        }
    }

    $payload = [
        'dosya_adi' => $fileName,
        'dosya_yolu' => $filePath,
        'alt_metin' => $altText,
        'kategori' => $category,
        'durum' => $status,
    ];

    if ($payload['dosya_adi'] === '' || $payload['dosya_yolu'] === '') {
        flash_set('danger', 'Dosya adı ve dosya yolu zorunludur.');
        redirect($formActionUrl);
    }

    try {
        $code->saveMediaItem($payload, $id ?: null);
        flash_set('success', $id ? 'Medya kaydı güncellendi.' : 'Medya kaydı eklendi.');
        redirect('index.php?do=medya');
    } catch (InvalidArgumentException $error) {
        flash_set('danger', $error->getMessage());
        redirect($formActionUrl);
    } catch (Throwable $error) {
        log_exception($error, ['page' => 'medya_form', 'id' => $id]);
        flash_set('danger', 'Medya kaydi kaydedilemedi.');
        redirect($formActionUrl);
    }
}

$defaults = [
    'dosya_adi' => '',
    'dosya_yolu' => '',
    'alt_metin' => '',
    'kategori' => 'Genel',
    'durum' => 1,
];

$item = array_merge($defaults, $item ?? []);

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0"><?= $id ? 'Medya Düzenle' : 'Yeni Medya' ?></h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php?do=medya">Medya</a></li>
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
      <div class="card-header"><h3 class="card-title">Medya Bilgileri</h3></div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Dosya Yükle</label>
          <input type="file" name="dosyalar[]" class="form-control" accept=".jpg,.jpeg,.png,.webp" <?= $id ? '' : 'multiple' ?> />
          <div class="form-text">
            Izin verilen turler: jpg, jpeg, png, webp. En fazla 20 MB. Dosyalar `public/uploads` icine kaydedilir.
            <?= $id ? 'Duzenleme ekraninda tek dosya secilebilir.' : 'Yeni medya eklerken birden fazla dosya secerseniz her gorsel ayri medya kaydi olarak eklenir.' ?>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Dosya Adı</label>
            <input type="text" name="dosya_adi" class="form-control" value="<?= e($item['dosya_adi']) ?>" />
            <div class="form-text">Toplu yuklemede bos birakirsaniz her dosya adi gorselin kendi isminden otomatik olusturulur.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Dosya Yolu</label>
            <input type="text" name="dosya_yolu" class="form-control" value="<?= e($item['dosya_yolu']) ?>" placeholder="/images/hero/bg-1.jpeg veya /uploads/dosya.webp" />
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Alt Metin</label>
            <input type="text" name="alt_metin" class="form-control" value="<?= e($item['alt_metin']) ?>" />
            <div class="form-text">Toplu yuklemede ayni alt metin tum secili gorsellere uygulanir ve dosya ismiyle ayirt edilir.</div>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Kategori</label>
            <input type="text" name="kategori" class="form-control" value="<?= e($item['kategori']) ?>" placeholder="Hero, Proje, Hizmet, Blog" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Durum</label>
            <select name="durum" class="form-select">
              <option value="1" <?= selected_value($item['durum'], 1) ?>>Aktif</option>
              <option value="2" <?= selected_value($item['durum'], 2) ?>>Pasif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer text-end">
        <a href="index.php?do=medya" class="btn btn-secondary">Vazgeç</a>
        <button type="submit" class="btn btn-primary">Kaydet</button>
      </div>
    </form>
  </div>
</div>
