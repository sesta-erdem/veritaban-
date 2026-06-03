<?php

require_post_csrf('index.php?do=projeler');

$id = (int) post('id', 0);

if ($id > 0) {
    $code->deleteProject($id);
    flash_set('success', 'Proje silindi.');
}

redirect('index.php?do=projeler');
