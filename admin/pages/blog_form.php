<?php

$id = (int) getv('id', 0);
$post = $id ? $code->getBlogPost($id) : null;
$errors = [];
$mediaItems = $code->getActiveMediaItemsByCategory(['Blog', 'Genel', 'Hero']);
$formActionUrl = 'index.php?do=blog_form' . ($id ? '&id=' . $id : '');

if ($id && !$post) {
    flash_set('danger', 'Blog yazısı bulunamadı.');
    redirect('index.php?do=blog');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($formActionUrl);

    $title = trim((string) post('baslik'));
    $slug = slugify(trim((string) post('slug')) ?: $title);

    $payload = [
        'baslik' => $title,
        'slug' => $slug,
        'excerpt' => trim((string) post('excerpt')),
        'kapak_gorseli' => trim((string) post('kapak_gorseli')),
        'yazar' => trim((string) post('yazar')),
        'yayin_tarihi' => trim((string) post('yayin_tarihi')),
        'etiketler' => trim((string) post('etiketler')),
        'icerik' => trim((string) post('icerik')),
        'seo_title' => trim((string) post('seo_title')),
        'seo_desc' => trim((string) post('seo_desc')),
        'durum' => (int) post('durum', 2),
    ];

    if (!validate_required($payload['baslik'])) {
        $errors[] = 'Baslik zorunludur.';
    }
    if (!validate_required($payload['excerpt'])) {
        $errors[] = 'Ozet zorunludur.';
    }
    if (!validate_required($payload['icerik'])) {
        $errors[] = 'Icerik zorunludur.';
    }
    if (!validate_required($payload['yazar'])) {
        $errors[] = 'Yazar zorunludur.';
    }
    if (!validate_date_string($payload['yayin_tarihi'])) {
        $errors[] = 'Gecerli bir yayin tarihi girin.';
    }
    if (!validate_asset_path($payload['kapak_gorseli'])) {
        $errors[] = 'Kapak gorseli public klasorundeki gecerli bir dosyayi gostermelidir.';
    }
    if ($code->blogSlugExists($payload['slug'], $id ?: null)) {
        $errors[] = 'Bu blog slug zaten kullaniliyor.';
    }

    if (!$errors) {
        try {
            $code->saveBlogPost($payload, $id ?: null);
            flash_set('success', $id ? 'Blog yazısı güncellendi.' : 'Blog yazısı eklendi.');
            redirect('index.php?do=blog');
        } catch (InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        } catch (Throwable $error) {
            log_exception($error, ['page' => 'blog_form', 'id' => $id]);
            $errors[] = 'Blog yazisi kaydedilemedi.';
        }
    }
}

$defaults = [
    'baslik' => '',
    'slug' => '',
    'excerpt' => '',
    'kapak_gorseli' => '/images/hero/great-new-workshop.jpeg',
    'yazar' => 'DOLUNAY MÜHENDİSLİK',
    'yayin_tarihi' => date('Y-m-d'),
    'etiketler' => 'Celik Yapilar, Shop Drawing',
    'icerik' => '',
    'seo_title' => '',
    'seo_desc' => '',
    'durum' => 2,
];

$post = array_merge($defaults, $post ?? []);

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0"><?= $id ? 'Blog Yazısı Düzenle' : 'Yeni Blog Yazısı' ?></h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php?do=blog">Blog</a></li>
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
      <div class="card-header"><h3 class="card-title">Yazı Bilgileri</h3></div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?= e(implode(' ', $errors)) ?>
          </div>
        <?php endif; ?>
        <div class="row">
          <div class="col-md-8 mb-3">
            <label class="form-label">Başlık</label>
            <input type="text" name="baslik" class="form-control" value="<?= e($post['baslik']) ?>" required />
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-control" value="<?= e($post['slug']) ?>" placeholder="Bos kalirsa otomatik uretilir ve normalize edilir" />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Özet</label>
          <textarea name="excerpt" class="form-control" rows="3" required><?= e($post['excerpt']) ?></textarea>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Kapak Görseli</label>
            <input type="text" name="kapak_gorseli" class="form-control" value="<?= e($post['kapak_gorseli']) ?>" />
            <?php if ($mediaItems): ?>
              <select class="form-select mt-2" data-media-target="kapak_gorseli">
                <option value="">Kutuphane secin</option>
                <?php foreach ($mediaItems as $item): ?>
                  <option value="<?= e($item['dosya_yolu']) ?>" <?= selected_value($post['kapak_gorseli'], $item['dosya_yolu']) ?>>
                    <?= e(($item['kategori'] ?: 'Genel') . ' / ' . $item['dosya_adi']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Yazar</label>
            <input type="text" name="yazar" class="form-control" value="<?= e($post['yazar']) ?>" />
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Yayın Tarihi</label>
            <input type="date" name="yayin_tarihi" class="form-control" value="<?= e($post['yayin_tarihi']) ?>" />
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Etiketler</label>
          <input type="text" name="etiketler" class="form-control" value="<?= e($post['etiketler']) ?>" placeholder="Virgülle ayırın" />
        </div>
        <div class="mb-3">
          <label class="form-label">İçerik</label>
          <textarea name="icerik" class="form-control font-monospace" rows="14" required><?= e($post['icerik']) ?></textarea>
          <div class="form-text">Markdown formatında yazılabilir.</div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">SEO Title</label>
            <input type="text" name="seo_title" class="form-control" value="<?= e($post['seo_title']) ?>" />
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">SEO Description</label>
            <input type="text" name="seo_desc" class="form-control" value="<?= e($post['seo_desc']) ?>" />
          </div>
        </div>
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Durum</label>
            <select name="durum" class="form-select">
              <option value="1" <?= selected_value($post['durum'], 1) ?>>Aktif</option>
              <option value="2" <?= selected_value($post['durum'], 2) ?>>Taslak</option>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer text-end">
        <a href="index.php?do=blog" class="btn btn-secondary">Vazgeç</a>
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
