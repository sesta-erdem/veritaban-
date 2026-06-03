<?php

$id = (int) getv('id', 0);
$project = $id ? $code->getProject($id) : null;
$errors = [];
$mediaItems = $code->getActiveMediaItemsByCategory(['Proje', 'Genel', 'Hero']);
$formActionUrl = 'index.php?do=proje_form' . ($id ? '&id=' . $id : '');
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
$parseGalleryPaths = static function (string $value): array {
    $paths = preg_split('/\r\n|\r|\n/', $value) ?: [];
    $normalized = [];

    foreach ($paths as $path) {
        $path = trim((string) $path);
        if ($path === '' || in_array($path, $normalized, true)) {
            continue;
        }

        $normalized[] = $path;
    }

    return $normalized;
};
$deriveUploadLabel = static function (array $file): string {
    $baseName = trim((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_FILENAME));
    return $baseName !== '' ? $baseName : 'Proje Gorseli';
};

if ($id && !$project) {
    flash_set('danger', 'Proje kaydı bulunamadı.');
    redirect('index.php?do=projeler');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($formActionUrl);

    $title = trim((string) post('proje_adi'));
    $slug = slugify(trim((string) post('slug')) ?: $title);
    $newGalleryFiles = $normalizeUploadedFiles($_FILES['yeni_galeri_dosyalari'] ?? null);
    $gallerySelections = array_values(array_filter(
        array_map('trim', (array) post('galeri_secim', [])),
        static fn (string $value): bool => $value !== ''
    ));
    $galleryPaths = array_values(array_unique(array_merge(
        $gallerySelections,
        $parseGalleryPaths((string) post('galeri_gorselleri'))
    )));

    $payload = [
        'sirasi' => (int) post('sirasi', 0),
        'proje_adi' => $title,
        'slug' => $slug,
        'kategori' => trim((string) post('kategori')),
        'sehir' => trim((string) post('sehir')),
        'yapi_tipi' => trim((string) post('yapi_tipi')),
        'kapsam' => trim((string) post('kapsam')),
        'rol' => trim((string) post('rol')),
        'teknik_cikti' => trim((string) post('teknik_cikti')),
        'tonaj' => trim((string) post('tonaj')),
        'metrekare' => trim((string) post('metrekare')),
        'teslim_suresi' => trim((string) post('teslim_suresi')),
        'kisa_ozet' => trim((string) post('kisa_ozet')),
        'veri_durumu' => trim((string) post('veri_durumu')),
        'veri_notu' => trim((string) post('veri_notu')),
        'one_cikan' => (int) post('one_cikan', 2),
        'tasarim_gorseli' => trim((string) post('tasarim_gorseli')),
        'final_gorseli' => trim((string) post('final_gorseli')),
        'galeri_gorselleri' => implode("\n", $galleryPaths),
        'durum' => (int) post('durum', 1),
    ];
    $project = array_merge($project ?? [], $payload);

    if (!validate_required($payload['proje_adi'])) {
        $errors[] = 'Proje adi zorunludur.';
    }
    if (!validate_required($payload['kategori'])) {
        $errors[] = 'Kategori zorunludur.';
    }
    if (!validate_required($payload['sehir'])) {
        $errors[] = 'Sehir zorunludur.';
    }
    if (!validate_required($payload['kisa_ozet'])) {
        $errors[] = 'Kisa ozet zorunludur.';
    }
    if (!validate_asset_path($payload['tasarim_gorseli'])) {
        $errors[] = 'Tasarim gorseli public klasorundeki gecerli bir dosyayi gostermelidir.';
    }
    if (!validate_asset_path($payload['final_gorseli'])) {
        $errors[] = 'Final gorseli public klasorundeki gecerli bir dosyayi gostermelidir.';
    }
    foreach ($galleryPaths as $galleryPath) {
        if (!validate_asset_path($galleryPath)) {
            $errors[] = 'Proje galerisi icin her satirda public klasorundeki gecerli bir dosya yolu bulunmalidir.';
            break;
        }
    }
    if ($code->projectSlugExists($payload['slug'], $id ?: null)) {
        $errors[] = 'Bu proje slug zaten kullaniliyor.';
    }

    if (!$errors) {
        $uploadedGalleryPaths = [];

        foreach ($newGalleryFiles as $index => $uploadedFile) {
            try {
                $storedPath = secure_image_upload(
                    $uploadedFile,
                    dirname(__DIR__, 2) . '/public/uploads',
                    '/uploads'
                );
                $uploadLabel = $deriveUploadLabel($uploadedFile);

                $code->saveMediaItem([
                    'dosya_adi' => $title !== '' ? $title . ' - ' . $uploadLabel : $uploadLabel,
                    'dosya_yolu' => $storedPath,
                    'alt_metin' => $title !== '' ? $title . ' galeri gorseli ' . ($index + 1) : $uploadLabel,
                    'kategori' => 'Proje',
                    'durum' => 1,
                ]);

                $uploadedGalleryPaths[] = $storedPath;
            } catch (Throwable $error) {
                log_exception($error, ['upload' => 'project_gallery_file', 'file' => $uploadedFile['name'] ?? '']);
                $errors[] = sprintf(
                    '`%s` yuklenemedi: %s',
                    (string) ($uploadedFile['name'] ?? 'dosya'),
                    $error->getMessage()
                );
                break;
            }
        }
    }

    if (!$errors) {
        if ($uploadedGalleryPaths) {
            $galleryPaths = array_values(array_unique(array_merge($uploadedGalleryPaths, $galleryPaths)));
            $payload['galeri_gorselleri'] = implode("\n", $galleryPaths);
            $project = array_merge($project ?? [], $payload);
        }

        try {
            $code->saveProject($payload, $id ?: null);
            flash_set('success', $id ? 'Proje güncellendi.' : 'Proje eklendi.');
            redirect('index.php?do=projeler');
        } catch (InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        } catch (Throwable $error) {
            log_exception($error, ['page' => 'proje_form', 'id' => $id]);
            $errors[] = 'Proje kaydi kaydedilemedi.';
        }
    }
}

$defaults = [
    'sirasi' => 0,
    'proje_adi' => '',
    'slug' => '',
    'kategori' => 'Celik Yapilar',
    'sehir' => '',
    'yapi_tipi' => '',
    'kapsam' => '',
    'rol' => '',
    'teknik_cikti' => '',
    'tonaj' => '',
    'metrekare' => '',
    'teslim_suresi' => '',
    'kisa_ozet' => '',
    'veri_durumu' => 'Taslak proje kaydi',
    'veri_notu' => 'Gercek portfoy verisiyle teyit bekliyor.',
    'one_cikan' => 2,
    'tasarim_gorseli' => '/images/projects/project-b.jpeg',
    'final_gorseli' => '/images/hero/great-new-workshop.jpeg',
    'galeri_gorselleri' => '',
    'durum' => 1,
];

$project = array_merge($defaults, $project ?? []);
$selectedGalleryPaths = $parseGalleryPaths((string) $project['galeri_gorselleri']);
$selectedGalleryLookup = array_fill_keys($selectedGalleryPaths, true);

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0"><?= $id ? 'Proje Düzenle' : 'Yeni Proje' ?></h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php?do=projeler">Projeler</a></li>
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
      <div class="card-header"><h3 class="card-title">Proje Bilgileri</h3></div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?= e(implode(' ', $errors)) ?>
          </div>
        <?php endif; ?>
        <div class="row">
          <div class="col-md-2 mb-3">
            <label class="form-label">Sıra</label>
            <input type="number" name="sirasi" class="form-control" value="<?= e((string) $project['sirasi']) ?>" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Proje Adı</label>
            <input type="text" name="proje_adi" class="form-control" value="<?= e($project['proje_adi']) ?>" required />
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-control" value="<?= e($project['slug']) ?>" placeholder="Bos kalirsa otomatik uretilir ve normalize edilir" />
          </div>
        </div>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Kategori</label>
            <input type="text" name="kategori" class="form-control" value="<?= e($project['kategori']) ?>" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Şehir</label>
            <input type="text" name="sehir" class="form-control" value="<?= e($project['sehir']) ?>" placeholder="Üsküdar / İstanbul" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Yapı Tipi</label>
            <input type="text" name="yapi_tipi" class="form-control" value="<?= e($project['yapi_tipi']) ?>" placeholder="Çelik fabrika çatısı, saçak, taşıyıcı konstrüksiyon" />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Kısa Özet</label>
          <textarea name="kisa_ozet" class="form-control" rows="3" placeholder="Atölye imalatı, saha montajı ve proje kapsamını kısa ve net biçimde özetleyin."><?= e($project['kisa_ozet']) ?></textarea>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">İmalat Kapsamı</label>
            <textarea name="kapsam" class="form-control" rows="4" placeholder="Çelik taşıyıcı sistem imalatı, çatı karkası, saha montajı."><?= e($project['kapsam']) ?></textarea>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Dolunay Rolü</label>
            <textarea name="rol" class="form-control" rows="4" placeholder="İmalat koordinasyonu, shop drawing kontrolü, saha teknik desteği."><?= e($project['rol']) ?></textarea>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Teknik Çıktı</label>
            <textarea name="teknik_cikti" class="form-control" rows="4" placeholder="Shop drawing seti, bağlantı detayları, montaj çizimleri."><?= e($project['teknik_cikti']) ?></textarea>
          </div>
        </div>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Tonaj</label>
            <input type="text" name="tonaj" class="form-control" value="<?= e($project['tonaj']) ?>" placeholder="8 ton" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Metrekare</label>
            <input type="text" name="metrekare" class="form-control" value="<?= e($project['metrekare']) ?>" placeholder="180 m²" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Teslim Süresi</label>
            <input type="text" name="teslim_suresi" class="form-control" value="<?= e($project['teslim_suresi']) ?>" placeholder="25 gün" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Veri Durumu</label>
            <select name="veri_durumu" class="form-select">
              <option value="Taslak proje kaydi" <?= selected_value($project['veri_durumu'], 'Taslak proje kaydi') ?>>Taslak proje kaydı</option>
              <option value="Gercek proje verisi" <?= selected_value($project['veri_durumu'], 'Gercek proje verisi') ?>>Gerçek proje verisi</option>
              <option value="Yayin onayi bekliyor" <?= selected_value($project['veri_durumu'], 'Yayin onayi bekliyor') ?>>Yayın onayı bekliyor</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Veri Notu</label>
          <textarea name="veri_notu" class="form-control" rows="2"><?= e($project['veri_notu']) ?></textarea>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Tasarım Görseli</label>
            <input type="text" name="tasarim_gorseli" class="form-control" value="<?= e($project['tasarim_gorseli']) ?>" />
            <?php if ($mediaItems): ?>
              <select class="form-select mt-2" data-media-target="tasarim_gorseli">
                <option value="">Kutuphane secin</option>
                <?php foreach ($mediaItems as $item): ?>
                  <option value="<?= e($item['dosya_yolu']) ?>" <?= selected_value($project['tasarim_gorseli'], $item['dosya_yolu']) ?>>
                    <?= e(($item['kategori'] ?: 'Genel') . ' / ' . $item['dosya_adi']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Final/Saha Görseli</label>
            <input type="text" name="final_gorseli" class="form-control" value="<?= e($project['final_gorseli']) ?>" />
            <?php if ($mediaItems): ?>
              <select class="form-select mt-2" data-media-target="final_gorseli">
                <option value="">Kutuphane secin</option>
                <?php foreach ($mediaItems as $item): ?>
                  <option value="<?= e($item['dosya_yolu']) ?>" <?= selected_value($project['final_gorseli'], $item['dosya_yolu']) ?>>
                    <?= e(($item['kategori'] ?: 'Genel') . ' / ' . $item['dosya_adi']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
        </div>
        <div class="mb-3">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <label class="form-label mb-0">Proje Galerisi</label>
            <a href="index.php?do=medya_form" class="btn btn-outline-secondary btn-sm">Medyaya toplu görsel yükle</a>
          </div>
          <div class="form-text mb-2">Medyadan birden fazla görsel seçebilirsiniz. Seçtikleriniz proje kartı galerisinde ve proje detay sayfasında birlikte gösterilir.</div>
          <div class="card border-dashed mt-3">
            <div class="card-body">
              <label class="form-label">Bu proje için yeni görseller yükle</label>
              <input type="file" name="yeni_galeri_dosyalari[]" class="form-control" accept=".jpg,.jpeg,.png,.webp" multiple />
              <div class="form-text">Buradan seçtiğiniz görseller kaydettiğiniz anda hem medya kütüphanesine eklenir hem de bu projenin galerisine otomatik bağlanır. En fazla 20 MB.</div>
            </div>
          </div>
          <?php if ($mediaItems): ?>
            <div class="card mt-3" data-gallery-library>
              <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <div class="small text-muted">Kütüphaneden seçin; isterseniz alttaki metin alanından yolları elle de düzenleyebilirsiniz.</div>
                  <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-gallery-action="select-all">Tümünü Seç</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-gallery-action="clear">Temizle</button>
                  </div>
                </div>
                <div class="small fw-semibold mb-3">Seçili görsel: <span data-gallery-selection-count><?= count($selectedGalleryPaths) ?></span></div>
                <div class="row g-3">
                  <?php foreach ($mediaItems as $item): ?>
                    <div class="col-sm-6 col-xl-4">
                      <label class="card h-100 shadow-sm" style="cursor: pointer;">
                        <div class="ratio ratio-4x3 bg-light">
                          <img src="<?= e($item['dosya_yolu']) ?>" alt="<?= e($item['alt_metin'] ?: $item['dosya_adi']) ?>" class="w-100 h-100" style="object-fit: cover;" />
                        </div>
                        <div class="card-body">
                          <div class="d-flex align-items-start gap-2">
                            <input
                              type="checkbox"
                              class="form-check-input mt-1"
                              name="galeri_secim[]"
                              value="<?= e($item['dosya_yolu']) ?>"
                              data-gallery-checkbox
                              <?= isset($selectedGalleryLookup[$item['dosya_yolu']]) ? 'checked' : '' ?>
                            />
                            <div class="small">
                              <div class="fw-semibold"><?= e($item['dosya_adi']) ?></div>
                              <div class="text-muted"><?= e($item['kategori'] ?: 'Genel') ?></div>
                              <div class="text-muted" style="word-break: break-all;"><?= e($item['dosya_yolu']) ?></div>
                            </div>
                          </div>
                        </div>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
          <textarea
            name="galeri_gorselleri"
            class="form-control mt-3"
            rows="5"
            placeholder="/uploads/proje-01.jpg&#10;/uploads/proje-02.jpg&#10;/uploads/proje-03.jpg"
          ><?= e($project['galeri_gorselleri']) ?></textarea>
          <div class="form-text">Her satıra bir public görsel yolu yazabilirsiniz. Medya kütüphanesinden seçtikleriniz bu alana otomatik yansır.</div>
        </div>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Öne Çıkan</label>
            <select name="one_cikan" class="form-select">
              <option value="1" <?= selected_value($project['one_cikan'], 1) ?>>Evet</option>
              <option value="2" <?= selected_value($project['one_cikan'], 2) ?>>Hayır</option>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Durum</label>
            <select name="durum" class="form-select">
              <option value="1" <?= selected_value($project['durum'], 1) ?>>Aktif</option>
              <option value="2" <?= selected_value($project['durum'], 2) ?>>Pasif</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer text-end">
        <a href="index.php?do=projeler" class="btn btn-secondary">Vazgeç</a>
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
      const field = document.querySelector(`[name="${target.dataset.mediaTarget}"]`);
      if (field instanceof HTMLInputElement && target.value !== "") {
        field.value = target.value;
        return;
      }

      if (field instanceof HTMLTextAreaElement && target.value !== "") {
        const currentLines = field.value
          .split(/\r\n|\r|\n/)
          .map((line) => line.trim())
          .filter(Boolean);

        if (!currentLines.includes(target.value)) {
          currentLines.push(target.value);
          field.value = currentLines.join("\n");
        }
      }
    });
  });

  (() => {
    const textarea = document.querySelector('[name="galeri_gorselleri"]');
    const galleryLibrary = document.querySelector("[data-gallery-library]");
    if (!(textarea instanceof HTMLTextAreaElement) || !(galleryLibrary instanceof HTMLElement)) return;

    const checkboxes = Array.from(galleryLibrary.querySelectorAll("[data-gallery-checkbox]")).filter(
      (checkbox) => checkbox instanceof HTMLInputElement,
    );
    if (checkboxes.length === 0) return;

    const counter = document.querySelector("[data-gallery-selection-count]");
    const libraryValues = new Set(
      checkboxes
        .map((checkbox) => checkbox.value.trim())
        .filter(Boolean),
    );

    const normalizeLines = (value) => {
      const uniqueLines = [];
      value
        .split(/\r\n|\r|\n/)
        .map((line) => line.trim())
        .filter(Boolean)
        .forEach((line) => {
          if (!uniqueLines.includes(line)) {
            uniqueLines.push(line);
          }
        });

      return uniqueLines;
    };

    const updateCounter = () => {
      if (counter) {
        counter.textContent = String(normalizeLines(textarea.value).length);
      }
    };

    const syncTextareaFromCheckboxes = () => {
      const selectedLibraryValues = checkboxes
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.value.trim())
        .filter(Boolean);
      const manualOnlyValues = normalizeLines(textarea.value).filter((value) => !libraryValues.has(value));
      textarea.value = [...selectedLibraryValues, ...manualOnlyValues].join("\n");
      updateCounter();
    };

    const syncCheckboxesFromTextarea = () => {
      const selectedValues = new Set(normalizeLines(textarea.value));
      checkboxes.forEach((checkbox) => {
        checkbox.checked = selectedValues.has(checkbox.value.trim());
      });
      updateCounter();
    };

    checkboxes.forEach((checkbox) => {
      checkbox.addEventListener("change", syncTextareaFromCheckboxes);
    });

    textarea.addEventListener("input", syncCheckboxesFromTextarea);

    const selectAllButton = galleryLibrary.querySelector('[data-gallery-action="select-all"]');
    if (selectAllButton instanceof HTMLButtonElement) {
      selectAllButton.addEventListener("click", () => {
        checkboxes.forEach((checkbox) => {
          checkbox.checked = true;
        });
        syncTextareaFromCheckboxes();
      });
    }

    const clearButton = galleryLibrary.querySelector('[data-gallery-action="clear"]');
    if (clearButton instanceof HTMLButtonElement) {
      clearButton.addEventListener("click", () => {
        checkboxes.forEach((checkbox) => {
          checkbox.checked = false;
        });
        textarea.value = "";
        updateCounter();
      });
    }

    syncCheckboxesFromTextarea();
  })();
</script>
