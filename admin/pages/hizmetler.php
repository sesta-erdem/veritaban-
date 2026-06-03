<?php
$pager = pagination_params();
$servicePage = $code->getServicesPage($pager['page'], $pager['limit']);
$services = $servicePage['items'];
$pagination = $servicePage['pagination'];
$canManage = can_manage_admin_content();
?>
<div class="app-content-header"><div class="container-fluid"><h3 class="mb-0">Hizmetler</h3></div></div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Hizmet Listesi</h3>
        <?php if ($canManage): ?>
          <a href="index.php?do=hizmet_form" class="btn btn-primary ms-auto">
            <i class="bi bi-plus"></i> Yeni Hizmet
          </a>
        <?php endif; ?>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Sıra</th><th>Başlık</th><th>Slug</th><th>Durum</th><?php if ($canManage): ?><th class="text-end">İşlem</th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($services as $service): ?>
              <tr>
                <td><?= e((string) $service['sirasi']) ?></td>
                <td><?= e($service['baslik']) ?></td>
                <td><?= e($service['slug']) ?></td>
                <td><?= (int) $service['durum'] === 1 ? 'Aktif' : 'Pasif' ?></td>
                <?php if ($canManage): ?>
                  <td class="text-end">
                    <a href="index.php?do=hizmet_form&id=<?= e((string) $service['hizmet_id']) ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?do=hizmet_sil" class="d-inline" onsubmit="return confirm('Bu hizmeti silmek istiyor musunuz?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="id" value="<?= e((string) $service['hizmet_id']) ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$services): ?><tr><td colspan="<?= $canManage ? '5' : '4' ?>" class="text-center p-4">Hizmet kayıtları henüz eklenmedi.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_admin_pagination($pagination, ['do' => 'hizmetler']) ?>
    </div>
  </div>
</div>
