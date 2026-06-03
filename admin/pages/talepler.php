<?php
$pager = pagination_params();
$requestPage = $code->getContactRequestsPage($pager['page'], $pager['limit']);
$requests = $requestPage['items'];
$pagination = $requestPage['pagination'];
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">İletişim Talepleri</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">İletişim Talepleri</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Form Talepleri</h3></div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Tarih</th>
              <th>Ad Soyad</th>
              <th>İletişim</th>
              <th>Hizmet</th>
              <th>Lokasyon</th>
              <th>Mesaj</th>
              <th>Durum</th>
              <th class="text-end">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $request): ?>
              <tr>
                <td><?= e($request['kayit_tarihi']) ?></td>
                <td><strong><?= e($request['ad_soyad']) ?></strong></td>
                <td>
                  <div><?= e($request['telefon']) ?></div>
                  <div class="text-secondary small"><?= e($request['eposta']) ?></div>
                </td>
                <td><?= e($request['hizmet_alani']) ?></td>
                <td><?= e($request['lokasyon']) ?></td>
                <td class="text-secondary small" style="max-width: 320px;"><?= e(short_text((string) $request['mesaj'])) ?></td>
                <td>
                  <span class="badge text-bg-<?= (int) $request['okundu'] === 1 ? 'secondary' : 'success' ?>">
                    <?= (int) $request['okundu'] === 1 ? 'Okundu' : 'Yeni' ?>
                  </span>
                </td>
                <td class="text-end">
                  <a href="index.php?do=talep_detay&id=<?= e((string) $request['talep_id']) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye"></i>
                  </a>
                  <form method="post" action="index.php?do=talep_sil" class="d-inline" onsubmit="return confirm('Bu talebi silmek istiyor musunuz?')">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= e((string) $request['talep_id']) ?>" />
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
              <tr><td colspan="8" class="text-center p-4">Henüz talep yok.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_admin_pagination($pagination, ['do' => 'talepler']) ?>
    </div>
  </div>
</div>
