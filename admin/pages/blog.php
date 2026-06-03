<?php
$pager = pagination_params();
$postPage = $code->getBlogPostsPage($pager['page'], $pager['limit']);
$posts = $postPage['items'];
$pagination = $postPage['pagination'];
$canManage = can_manage_admin_content();
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Blog</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Blog</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Teknik Yazılar</h3>
        <?php if ($canManage): ?>
          <a href="index.php?do=blog_form" class="btn btn-primary ms-auto">
            <i class="bi bi-plus"></i> Yeni Yazı
          </a>
        <?php endif; ?>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Kapak</th>
              <th>Başlık</th>
              <th>Yayın Tarihi</th>
              <th>Etiketler</th>
              <th>Durum</th>
              <?php if ($canManage): ?>
                <th class="text-end">İşlem</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posts as $post): ?>
              <tr>
                <td>
                  <?php if (!empty($post['kapak_gorseli'])): ?>
                    <img src="..<?= e($post['kapak_gorseli']) ?>" alt="" style="width: 96px; height: 54px; object-fit: cover;" />
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?= e($post['baslik']) ?></strong>
                  <div class="text-secondary small"><?= e($post['slug']) ?></div>
                </td>
                <td><?= e($post['yayin_tarihi']) ?></td>
                <td><?= e($post['etiketler']) ?></td>
                <td><?= (int) $post['durum'] === 1 ? 'Aktif' : 'Taslak' ?></td>
                <?php if ($canManage): ?>
                  <td class="text-end">
                    <a href="index.php?do=blog_form&id=<?= e((string) $post['yazi_id']) ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?do=blog_sil" class="d-inline" onsubmit="return confirm('Bu yazıyı silmek istiyor musunuz?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="id" value="<?= e((string) $post['yazi_id']) ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$posts): ?>
              <tr><td colspan="<?= $canManage ? '6' : '5' ?>" class="text-center p-4">Henüz blog yazısı yok.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_admin_pagination($pagination, ['do' => 'blog']) ?>
    </div>
  </div>
</div>
