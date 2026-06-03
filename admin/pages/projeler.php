<?php

$pager = pagination_params();
$projectPage = $code->getProjectsPage($pager['page'], $pager['limit']);
$projects = $projectPage['items'];
$pagination = $projectPage['pagination'];
$canManage = can_manage_admin_content();

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Projeler</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Projeler</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Proje Listesi</h3>
        <?php if ($canManage): ?>
          <a href="index.php?do=proje_form" class="btn btn-primary ms-auto">
            <i class="bi bi-plus"></i> Yeni Proje
          </a>
        <?php endif; ?>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Sıra</th>
              <th>Proje</th>
              <th>Kategori</th>
              <th>Şehir</th>
              <th>Veri Durumu</th>
              <th>Öne Çıkan</th>
              <th>Durum</th>
              <?php if ($canManage): ?>
                <th class="text-end">İşlem</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $project): ?>
              <tr>
                <td><?= e((string) $project['sirasi']) ?></td>
                <td>
                  <strong><?= e($project['proje_adi']) ?></strong>
                  <div class="text-secondary small"><?= e($project['slug']) ?></div>
                </td>
                <td><?= e($project['kategori']) ?></td>
                <td><?= e($project['sehir']) ?></td>
                <td><span class="badge text-bg-info"><?= e($project['veri_durumu']) ?></span></td>
                <td><?= (int) $project['one_cikan'] === 1 ? 'Evet' : 'Hayır' ?></td>
                <td><?= (int) $project['durum'] === 1 ? 'Aktif' : 'Pasif' ?></td>
                <?php if ($canManage): ?>
                  <td class="text-end">
                    <a href="index.php?do=proje_form&id=<?= e((string) $project['proje_id']) ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?do=proje_sil" class="d-inline" onsubmit="return confirm('Bu projeyi silmek istiyor musunuz?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="id" value="<?= e((string) $project['proje_id']) ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$projects): ?>
              <tr><td colspan="<?= $canManage ? '8' : '7' ?>" class="text-center p-4">Henüz proje kaydı yok.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_admin_pagination($pagination, ['do' => 'projeler']) ?>
    </div>
  </div>
</div>
