<?php

$user = $code->currentUser();
$flash = flash_get();
$canViewSystemTools = can_view_system_tools();

?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>DOLUNAY MÜHENDİSLİK Admin</title>
  <link rel="stylesheet" href="assets/vendor/css/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="assets/vendor/css/overlayscrollbars.min.css" />
  <link rel="stylesheet" href="assets/css/adminlte.css" />
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
  <nav class="app-header navbar navbar-expand bg-body">
    <div class="container-fluid">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
            <i class="bi bi-list"></i>
          </a>
        </li>
        <li class="nav-item d-none d-md-block">
          <a href="../" class="nav-link" target="_blank">Siteyi Aç</a>
        </li>
        <?php if ($canViewSystemTools): ?>
          <li class="nav-item d-none d-md-block">
            <a href="index.php?do=link_kontrol" class="nav-link">
              <i class="bi bi-link-45deg"></i> Link Kontrol
            </a>
          </li>
          <li class="nav-item d-none d-md-block">
            <a href="index.php?do=site_guncelle" class="nav-link">
              <i class="bi bi-arrow-repeat"></i> Siteyi Güncelle
            </a>
          </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown user-menu">
          <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
            <img src="assets/img/user2-160x160.jpg" class="user-image rounded-circle shadow" alt="Admin" />
            <span class="d-none d-md-inline"><?= e($user['ad_soyad'] ?? 'Admin') ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
            <li class="user-header text-bg-primary">
              <img src="assets/img/user2-160x160.jpg" class="rounded-circle shadow" alt="Admin" />
              <p><?= e($user['ad_soyad'] ?? 'Admin') ?><small><?= e($user['eposta'] ?? '') ?> · <?= e(admin_role_label((int) ($user['rutbe'] ?? 0))) ?></small></p>
            </li>
            <li class="user-footer">
              <form method="post" action="index.php?do=logout" class="float-end">
                <?= csrf_input() ?>
                <button type="submit" class="btn btn-default btn-flat">Çıkış</button>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </nav>
