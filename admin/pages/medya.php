<?php

$selectedCategory = trim((string) getv('kategori', ''));
$mediaCategories = $code->getMediaCategories();
$pager = pagination_params();
$mediaPage = $code->getMediaItemsFilteredPage($selectedCategory !== '' ? $selectedCategory : null, $pager['page'], $pager['limit']);
$mediaItems = $mediaPage['items'];
$pagination = $mediaPage['pagination'];
$canManage = can_manage_admin_content();

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Medya Kütüphanesi</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Medya</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Görseller ve Dosyalar</h3>
        <?php if ($canManage): ?>
          <a href="index.php?do=medya_form" class="btn btn-primary ms-auto">
            <i class="bi bi-plus"></i> Yeni Medya
          </a>
        <?php endif; ?>
      </div>
      <div class="card-body border-bottom">
        <form method="get" class="row g-3 align-items-end">
          <input type="hidden" name="do" value="medya" />
          <div class="col-md-4">
            <label class="form-label">Kategori Filtresi</label>
            <select name="kategori" class="form-select">
              <option value="">Tum kategoriler</option>
              <?php foreach ($mediaCategories as $category): ?>
                <option value="<?= e($category) ?>" <?= selected_value($selectedCategory, $category) ?>>
                  <?= e($category) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-auto">
            <button type="submit" class="btn btn-outline-primary">Filtrele</button>
            <a href="index.php?do=medya" class="btn btn-outline-secondary">Temizle</a>
          </div>
        </form>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Önizleme</th>
              <th>Dosya</th>
              <th>Kategori</th>
              <th>Durum</th>
              <?php if ($canManage): ?>
                <th class="text-end">İşlem</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mediaItems as $item): ?>
              <tr>
                <td style="width: 120px;">
                  <?php if (preg_match('/\.(jpg|jpeg|png|webp|gif|svg)$/i', (string) $item['dosya_yolu'])): ?>
                    <button
                      type="button"
                      class="btn p-0 border-0 bg-transparent"
                      data-media-preview-trigger
                      data-media-preview-src="..<?= e($item['dosya_yolu']) ?>"
                      data-media-preview-alt="<?= e($item['alt_metin'] ?: $item['dosya_adi']) ?>"
                    >
                      <img src="..<?= e($item['dosya_yolu']) ?>" alt="<?= e($item['alt_metin']) ?>" style="width: 96px; height: 64px; object-fit: cover;" />
                    </button>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Dosya</span>
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= e($item['dosya_adi']) ?></strong>
                  <div class="text-secondary small"><?= e($item['dosya_yolu']) ?></div>
                  <?php if (!empty($item['alt_metin'])): ?>
                    <div class="text-secondary small">Alt: <?= e($item['alt_metin']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= e($item['kategori']) ?></td>
                <td><?= (int) $item['durum'] === 1 ? 'Aktif' : 'Pasif' ?></td>
                <?php if ($canManage): ?>
                  <td class="text-end">
                    <a href="index.php?do=medya_form&id=<?= e((string) $item['medya_id']) ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?do=medya_sil" class="d-inline" onsubmit="return confirm('Bu medya kaydını silmek istiyor musunuz?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="id" value="<?= e((string) $item['medya_id']) ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$mediaItems): ?>
              <tr><td colspan="<?= $canManage ? '5' : '4' ?>" class="text-center p-4">Medya kaydı henüz eklenmedi.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_admin_pagination($pagination, ['do' => 'medya', 'kategori' => $selectedCategory]) ?>
    </div>
  </div>
</div>
<div
  class="modal fade"
  id="mediaPreviewModal"
  tabindex="-1"
  aria-hidden="true"
>
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-white">Medya Önizleme</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body text-center">
        <img
          src=""
          alt=""
          data-media-preview-image
          style="max-width: 100%; max-height: 78vh; width: auto; height: auto; object-fit: contain;"
        />
      </div>
    </div>
  </div>
</div>
<script>
  (() => {
    const modalElement = document.getElementById("mediaPreviewModal");
    const previewImage = modalElement?.querySelector("[data-media-preview-image]");
    if (!modalElement || !(previewImage instanceof HTMLImageElement) || typeof bootstrap === "undefined") return;

    const modal = new bootstrap.Modal(modalElement);
    document.querySelectorAll("[data-media-preview-trigger]").forEach((trigger) => {
      trigger.addEventListener("click", () => {
        if (!(trigger instanceof HTMLElement)) return;
        previewImage.src = trigger.dataset.mediaPreviewSrc || "";
        previewImage.alt = trigger.dataset.mediaPreviewAlt || "Medya önizleme";
        modal.show();
      });
    });
  })();
</script>
