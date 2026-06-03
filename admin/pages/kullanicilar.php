<?php

$pager = pagination_params();
$userPage = $code->getAdminUsersPage($pager['page'], $pager['limit']);
$users = $userPage['items'];
$pagination = $userPage['pagination'];

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Kullanıcılar</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Kullanıcılar</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Admin Kullanıcıları</h3>
        <a href="index.php?do=kullanici_form" class="btn btn-primary ms-auto">
          <i class="bi bi-plus"></i> Yeni Kullanıcı
        </a>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Ad Soyad</th>
              <th>E-posta</th>
              <th>Rol</th>
              <th>Durum</th>
              <th>Son Giriş</th>
              <th class="text-end">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $adminUser): ?>
              <tr>
                <td><strong><?= e($adminUser['ad_soyad']) ?></strong></td>
                <td><?= e($adminUser['eposta']) ?></td>
                <td><?= e(admin_role_label((int) $adminUser['rutbe'])) ?></td>
                <td><?= (int) $adminUser['sil'] === 2 ? 'Aktif' : 'Pasif' ?></td>
                <td><?= e((string) ($adminUser['son_giris_tarihi'] ?: '-')) ?></td>
                <td class="text-end">
                  <a href="index.php?do=kullanici_form&id=<?= e((string) $adminUser['kullanici_id']) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
              <tr><td colspan="6" class="text-center p-4">Henüz admin kullanıcı kaydı yok.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_admin_pagination($pagination, ['do' => 'kullanicilar']) ?>
    </div>
  </div>
</div>
