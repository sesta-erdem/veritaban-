<?php

require_post_csrf('index.php?do=medya');

$id = (int) post('id', 0);

if ($id > 0) {
    $code->deleteMediaItem($id);
    flash_set('success', 'Medya kaydı silindi.');
}

redirect('index.php?do=medya');
