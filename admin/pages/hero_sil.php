<?php

require_post_csrf('index.php?do=hero');

$id = (int) post('id', 0);

if ($id > 0) {
    $code->deleteHeroSlide($id);
    $syncResult = run_static_site_sync();

    if (($syncResult['exitCode'] ?? 1) === 0) {
        flash_set('success', 'Hero slayti silindi ve anasayfa guncellendi.');
    } else {
        flash_set('danger', 'Hero slayti silindi fakat anasayfa yayinina aktarilamadi.');
    }
}

redirect('index.php?do=hero');
