<?php

declare(strict_types=1);

require_once __DIR__ . '/sistem/loader.php';
send_admin_security_headers();

$page = (string) getv('do', 'dashboard');

if ($page === 'logout') {
    if ($code->isLoggedIn()) {
        require_post_csrf('index.php');
    }
    $code->logout();
    redirect('index.php');
}

if (!$code->isLoggedIn()) {
    require __DIR__ . '/pages/login.php';
    exit;
}

enforce_admin_session_timeout();

$routes = [
    'dashboard' => ['file' => 'dashboard.php', 'permissions' => [ADMIN_PERMISSION_DASHBOARD_VIEW]],
    'projeler' => ['file' => 'projeler.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_READ]],
    'proje_form' => ['file' => 'proje_form.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'proje_sil' => ['file' => 'proje_sil.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'hizmetler' => ['file' => 'hizmetler.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_READ]],
    'hizmet_form' => ['file' => 'hizmet_form.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'hizmet_sil' => ['file' => 'hizmet_sil.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'hero' => ['file' => 'hero.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_READ]],
    'hero_form' => ['file' => 'hero_form.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'hero_sil' => ['file' => 'hero_sil.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'blog' => ['file' => 'blog.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_READ]],
    'blog_form' => ['file' => 'blog_form.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'blog_sil' => ['file' => 'blog_sil.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'faq' => ['file' => 'faq.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_READ]],
    'faq_form' => ['file' => 'faq_form.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'faq_sil' => ['file' => 'faq_sil.php', 'permissions' => [ADMIN_PERMISSION_CONTENT_WRITE]],
    'talepler' => ['file' => 'talepler.php', 'permissions' => [ADMIN_PERMISSION_CONTACTS_READ]],
    'talep_detay' => ['file' => 'talep_detay.php', 'permissions' => [ADMIN_PERMISSION_CONTACTS_WRITE]],
    'talep_sil' => ['file' => 'talep_sil.php', 'permissions' => [ADMIN_PERMISSION_CONTACTS_WRITE]],
    'medya' => ['file' => 'medya.php', 'permissions' => [ADMIN_PERMISSION_MEDIA_READ]],
    'medya_form' => ['file' => 'medya_form.php', 'permissions' => [ADMIN_PERMISSION_MEDIA_WRITE]],
    'medya_sil' => ['file' => 'medya_sil.php', 'permissions' => [ADMIN_PERMISSION_MEDIA_WRITE]],
    'kullanicilar' => ['file' => 'kullanicilar.php', 'permissions' => [ADMIN_PERMISSION_USERS_MANAGE]],
    'kullanici_form' => ['file' => 'kullanici_form.php', 'permissions' => [ADMIN_PERMISSION_USERS_MANAGE]],
    'ayarlar' => ['file' => 'ayarlar.php', 'permissions' => [ADMIN_PERMISSION_SETTINGS_MANAGE]],
    'link_kontrol' => ['file' => 'link_kontrol.php', 'permissions' => [ADMIN_PERMISSION_SYSTEM_TOOLS]],
    'site_guncelle' => ['file' => 'site_guncelle.php', 'permissions' => [ADMIN_PERMISSION_DEPLOY_RUN]],
];

$route = $routes[$page] ?? $routes['dashboard'];
require_admin_permissions($route['permissions']);
$pageFile = $route['file'];

require __DIR__ . '/include/header.php';
require __DIR__ . '/include/sidebar.php';
require __DIR__ . '/pages/' . $pageFile;
require __DIR__ . '/include/footer.php';
