<?php
$pager = pagination_params();
$slidePage = $code->getHeroSlidesPage($pager['page'], $pager['limit']);
$slides = $slidePage['items'];
$pagination = $slidePage['pagination'];
$canManage = can_manage_admin_content();
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Hero Slaytları</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Hero Slaytları</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Ana Sayfa Hero Slaytları</h3>
        <?php if ($canManage): ?>
          <a href="index.php?do=hero_form" class="btn btn-primary ms-auto">
            <i class="bi bi-plus"></i> Yeni Slayt
          </a>
        <?php endif; ?>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Sıra</th>
              <th>Görsel</th>
              <th>Başlık</th>
              <th>Alt Başlık</th>
              <th>Durum</th>
              <?php if ($canManage): ?>
                <th class="text-end">İşlem</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($slides as $slide): ?>
              <tr>
                <td><?= e((string) $slide['sirasi']) ?></td>
                <td>
                  <?php if (!empty($slide['gorsel'])): ?>
                    <img src="..<?= e($slide['gorsel']) ?>" alt="" style="width: 96px; height: 54px; object-fit: cover;" />
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= e($slide['baslik']) ?></strong>
                  <div class="text-secondary small"><?= e($slide['eyebrow']) ?></div>
                </td>
                <td><?= e($slide['alt_baslik']) ?></td>
                <td><?= (int) $slide['durum'] === 1 ? 'Aktif' : 'Pasif' ?></td>
                <?php if ($canManage): ?>
                  <td class="text-end">
                    <a href="index.php?do=hero_form&id=<?= e((string) $slide['hero_id']) ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?do=hero_sil" class="d-inline" onsubmit="return confirm('Bu slaytı silmek istiyor musunuz?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="id" value="<?= e((string) $slide['hero_id']) ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$slides): ?>
              <tr><td colspan="<?= $canManage ? '6' : '5' ?>" class="text-center p-4">Henüz hero slaytı yok.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_admin_pagination($pagination, ['do' => 'hero']) ?>
    </div>
  </div>
</div>
