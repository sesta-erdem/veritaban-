<?php

$counts = $code->dashboardCounts();

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Dashboard</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item active">Anasayfa</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
          <div class="inner"><h3><?= e((string) $counts['projeler']) ?></h3><p>Projeler</p></div>
          <i class="small-box-icon bi bi-buildings"></i>
          <a href="index.php?do=projeler" class="small-box-footer">Yönet <i class="bi bi-arrow-right-circle"></i></a>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
          <div class="inner"><h3><?= e((string) $counts['hizmetler']) ?></h3><p>Hizmetler</p></div>
          <i class="small-box-icon bi bi-diagram-3"></i>
          <a href="index.php?do=hizmetler" class="small-box-footer">Yönet <i class="bi bi-arrow-right-circle"></i></a>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
          <div class="inner"><h3><?= e((string) $counts['blog']) ?></h3><p>Blog Yazıları</p></div>
          <i class="small-box-icon bi bi-journal-text"></i>
          <a href="index.php?do=blog" class="small-box-footer">Yönet <i class="bi bi-arrow-right-circle"></i></a>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
          <div class="inner"><h3><?= e((string) $counts['talepler']) ?></h3><p>İletişim Talepleri</p></div>
          <i class="small-box-icon bi bi-inbox"></i>
          <a href="index.php?do=talepler" class="small-box-footer">İncele <i class="bi bi-arrow-right-circle"></i></a>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Öncelikli İş Akışı</h3></div>
      <div class="card-body">
        <ol class="mb-0">
          <li>Projeler modülüne gerçek proje kayıtlarını gir.</li>
          <li>Hizmet teslim paketlerini netleştir.</li>
          <li>Hero ve blog içeriklerini panelden yönetilecek hale getir.</li>
          <li>İletişim formunu `iletisim_talepleri` tablosuna bağla.</li>
        </ol>
      </div>
    </div>
  </div>
</div>

