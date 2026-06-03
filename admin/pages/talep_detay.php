<?php

$id = (int) getv('id', 0);
$request = $id ? $code->getContactRequest($id) : null;
$formActionUrl = 'index.php?do=talep_detay&id=' . $id;

if (!$request) {
    flash_set('danger', 'İletişim talebi bulunamadı.');
    redirect('index.php?do=talepler');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($formActionUrl);

    try {
        $code->updateContactRequest($id, [
            'okundu' => (int) post('okundu', 1),
            'admin_notu' => trim((string) post('admin_notu')),
        ]);

        flash_set('success', 'Talep güncellendi.');
        redirect('index.php?do=talep_detay&id=' . $id);
    } catch (InvalidArgumentException $error) {
        flash_set('danger', $error->getMessage());
        redirect('index.php?do=talep_detay&id=' . $id);
    } catch (Throwable $error) {
        log_exception($error, ['page' => 'talep_detay', 'id' => $id]);
        flash_set('danger', 'Talep guncellenemedi.');
        redirect('index.php?do=talep_detay&id=' . $id);
    }
}

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Talep Detayı</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php?do=talepler">İletişim Talepleri</a></li>
          <li class="breadcrumb-item active">Detay</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Talep Bilgileri</h3></div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-3">Ad Soyad</dt>
              <dd class="col-sm-9"><?= e($request['ad_soyad']) ?></dd>
              <dt class="col-sm-3">Telefon</dt>
              <dd class="col-sm-9"><a href="tel:<?= e($request['telefon']) ?>"><?= e($request['telefon']) ?></a></dd>
              <dt class="col-sm-3">E-posta</dt>
              <dd class="col-sm-9"><a href="mailto:<?= e($request['eposta']) ?>"><?= e($request['eposta']) ?></a></dd>
              <dt class="col-sm-3">Proje Tipi</dt>
              <dd class="col-sm-9"><?= e($request['proje_tipi']) ?></dd>
              <dt class="col-sm-3">Hizmet Alanı</dt>
              <dd class="col-sm-9"><?= e($request['hizmet_alani']) ?></dd>
              <dt class="col-sm-3">Lokasyon</dt>
              <dd class="col-sm-9"><?= e($request['lokasyon']) ?></dd>
              <dt class="col-sm-3">Tarih</dt>
              <dd class="col-sm-9"><?= e($request['kayit_tarihi']) ?></dd>
              <dt class="col-sm-3">IP</dt>
              <dd class="col-sm-9"><?= e($request['ip_adresi']) ?></dd>
              <dt class="col-sm-3">Mesaj</dt>
              <dd class="col-sm-9"><?= nl2br(e($request['mesaj'])) ?></dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <form method="post" class="card">
          <?= csrf_input() ?>
          <div class="card-header"><h3 class="card-title">Takip Notu</h3></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Durum</label>
              <select name="okundu" class="form-select">
                <option value="2" <?= selected_value($request['okundu'], 2) ?>>Yeni</option>
                <option value="1" <?= selected_value($request['okundu'], 1) ?>>Okundu</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Admin Notu</label>
              <textarea name="admin_notu" class="form-control" rows="8"><?= e($request['admin_notu']) ?></textarea>
            </div>
          </div>
          <div class="card-footer text-end">
            <a href="index.php?do=talepler" class="btn btn-secondary">Listeye Dön</a>
            <button type="submit" class="btn btn-primary">Kaydet</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
