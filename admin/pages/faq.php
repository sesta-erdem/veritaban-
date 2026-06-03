<?php
$pager = pagination_params();
$faqPage = $code->getFaqsPage($pager['page'], $pager['limit']);
$faqs = $faqPage['items'];
$pagination = $faqPage['pagination'];
$canManage = can_manage_admin_content();
?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">FAQ</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item active">FAQ</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Sık Sorulan Sorular</h3>
        <?php if ($canManage): ?>
          <a href="index.php?do=faq_form" class="btn btn-primary ms-auto">
            <i class="bi bi-plus"></i> Yeni Soru
          </a>
        <?php endif; ?>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 80px;">Sıra</th>
              <th>Soru</th>
              <th>Cevap</th>
              <th style="width: 100px;">Durum</th>
              <?php if ($canManage): ?>
                <th class="text-end" style="width: 120px;">İşlem</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($faqs as $faq): ?>
              <tr>
                <td><?= e((string) $faq['sirasi']) ?></td>
                <td><strong><?= e($faq['soru']) ?></strong></td>
                <td><?= e(short_text($faq['cevap'], 110)) ?></td>
                <td><?= (int) $faq['durum'] === 1 ? 'Aktif' : 'Pasif' ?></td>
                <?php if ($canManage): ?>
                  <td class="text-end">
                    <a href="index.php?do=faq_form&id=<?= e((string) $faq['faq_id']) ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="index.php?do=faq_sil" class="d-inline" onsubmit="return confirm('Bu FAQ kaydını silmek istiyor musunuz?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="id" value="<?= e((string) $faq['faq_id']) ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (!$faqs): ?>
              <tr><td colspan="<?= $canManage ? '5' : '4' ?>" class="text-center p-4">FAQ kaydı henüz eklenmedi.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= render_admin_pagination($pagination, ['do' => 'faq']) ?>
    </div>
  </div>
</div>
