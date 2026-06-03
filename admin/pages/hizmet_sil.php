<?php

require_post_csrf('index.php?do=hizmetler');

$id = (int) post('id', 0);

if ($id > 0) {
    $code->deleteService($id);
    flash_set('success', 'Hizmet silindi.');
}

redirect('index.php?do=hizmetler');
