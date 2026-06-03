<?php

$id = (int) getv('id', 0);
$faq = $id ? $code->getFaq($id) : null;
$formActionUrl = 'index.php?do=faq_form' . ($id ? '&id=' . $id : '');

if ($id && !$faq) {
    flash_set('danger', 'FAQ kaydı bulunamadı.');
    redirect('index.php?do=faq');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($formActionUrl);

    $payload = [
        'sirasi' => (int) post('sirasi', 0),
        'soru' => trim((string) post('soru')),
        'cevap' => trim((string) post('cevap')),
        'durum' => (int) post('durum', 1),
    ];

    try {
        $code->saveFaq($payload, $id ?: null);
        flash_set('success', $id ? 'FAQ kaydı güncellendi.' : 'FAQ kaydı eklendi.');
        redirect('index.php?do=faq');
    } catch (InvalidArgumentException $error) {
        flash_set('danger', $error->getMessage());
        redirect($formActionUrl);
    } catch (Throwable $error) {
        log_exception($error, ['page' => 'faq_form', 'id' => $id]);
        flash_set('danger', 'FAQ kaydi kaydedilemedi.');
        redirect($formActionUrl);
    }
}

$defaults = [
    'sirasi' => 0,
    'soru' => '',
    'cevap' => '',
    'durum' => 1,
];

$faq = array_merge($defaults, $faq ?? []);

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0"><?= $id ? 'FAQ Düzenle' : 'Yeni FAQ' ?></h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php?do=faq">FAQ</a></li>
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
      <div class="card-header"><h3 class="card-title">Soru Bilgileri</h3></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Sıra</label>
            <input type="number" name="sirasi" class="form-control" value="<?= e((string) $faq['sirasi']) ?>" />
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label">Durum</label>
            <select name="durum" class="form-select">
              <option value="1" <?= selected_value($faq['durum'], 1) ?>>Aktif</option>
              <option value="2" <?= selected_value($faq['durum'], 2) ?>>Pasif</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Soru</label>
          <input type="text" name="soru" class="form-control" value="<?= e($faq['soru']) ?>" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Cevap</label>
          <textarea name="cevap" class="form-control" rows="6" required><?= e($faq['cevap']) ?></textarea>
        </div>
      </div>
      <div class="card-footer text-end">
        <a href="index.php?do=faq" class="btn btn-secondary">Vazgeç</a>
        <button type="submit" class="btn btn-primary">Kaydet</button>
      </div>
    </form>
  </div>
</div>
