<?php

$current = (string) getv('do', 'dashboard');
$canManageContent = can_manage_admin_content();
$canViewContactRequests = can_view_contact_requests();
$canViewSystemTools = can_view_system_tools();

?>
  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
      <a href="index.php" class="brand-link">
        <img src="assets/img/AdminLTELogo.png" alt="AdminLTE" class="brand-image opacity-75 shadow" />
        <span class="brand-text fw-light">Dolunay Admin</span>
      </a>
    </div>
    <div class="sidebar-wrapper">
      <nav class="mt-2">
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" data-accordion="false">
          <li class="nav-item">
            <a href="index.php?do=dashboard" class="nav-link <?= active_nav($current, 'dashboard') ?>">
              <i class="nav-icon bi bi-speedometer"></i>
              <p>Dashboard</p>
            </a>
          </li>
          <li class="nav-header">İçerik Yönetimi</li>
          <li class="nav-item">
            <a href="index.php?do=projeler" class="nav-link <?= active_nav($current, 'projeler') ?>">
              <i class="nav-icon bi bi-buildings"></i>
              <p>Projeler</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="index.php?do=hizmetler" class="nav-link <?= active_nav($current, 'hizmetler') ?>">
              <i class="nav-icon bi bi-diagram-3"></i>
              <p>Hizmetler</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="index.php?do=hero" class="nav-link <?= active_nav($current, 'hero') ?>">
              <i class="nav-icon bi bi-images"></i>
              <p>Hero Slaytları</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="index.php?do=blog" class="nav-link <?= active_nav($current, 'blog') ?>">
              <i class="nav-icon bi bi-journal-text"></i>
              <p>Blog</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="index.php?do=faq" class="nav-link <?= active_nav($current, 'faq') ?>">
              <i class="nav-icon bi bi-question-circle"></i>
              <p>FAQ</p>
            </a>
          </li>
          <?php if ($canViewContactRequests): ?>
            <li class="nav-item">
              <a href="index.php?do=talepler" class="nav-link <?= active_nav($current, 'talepler') ?>">
                <i class="nav-icon bi bi-inbox"></i>
                <p>İletişim Talepleri</p>
              </a>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a href="index.php?do=medya" class="nav-link <?= active_nav($current, 'medya') ?>">
              <i class="nav-icon bi bi-folder2-open"></i>
              <p>Medya</p>
            </a>
          </li>
          <?php if ($canViewSystemTools): ?>
            <li class="nav-header">Sistem</li>
            <li class="nav-item">
              <a href="index.php?do=kullanicilar" class="nav-link <?= active_nav($current, 'kullanicilar') ?><?= active_nav($current, 'kullanici_form') ?>">
                <i class="nav-icon bi bi-people"></i>
                <p>Kullanıcılar</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="index.php?do=ayarlar" class="nav-link <?= active_nav($current, 'ayarlar') ?>">
                <i class="nav-icon bi bi-gear"></i>
                <p>Site Ayarları</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="index.php?do=link_kontrol" class="nav-link <?= active_nav($current, 'link_kontrol') ?>">
                <i class="nav-icon bi bi-link-45deg"></i>
                <p>Link Kontrol</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="index.php?do=site_guncelle" class="nav-link <?= active_nav($current, 'site_guncelle') ?>">
                <i class="nav-icon bi bi-arrow-repeat"></i>
                <p>Siteyi Güncelle</p>
              </a>
            </li>
          <?php endif; ?>
          <?php if (!$canManageContent): ?>
            <li class="nav-header">Bilgi</li>
            <li class="nav-item px-3 pb-2 text-body-secondary small">
              Hesabiniz su anda salt okunur erisimle sinirlidir.
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <form method="post" action="index.php?do=logout" class="m-0">
              <?= csrf_input() ?>
              <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent">
                <i class="nav-icon bi bi-box-arrow-right"></i>
                <p>Çıkış</p>
              </button>
            </form>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <main class="app-main">
    <?php if ($flash): ?>
      <div class="container-fluid pt-3">
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      </div>
    <?php endif; ?>
