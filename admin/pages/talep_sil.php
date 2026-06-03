<?php

require_post_csrf('index.php?do=talepler');

$id = (int) post('id', 0);

if ($id > 0) {
    $code->deleteContactRequest($id);
    flash_set('success', 'İletişim talebi silindi.');
}

redirect('index.php?do=talepler');
