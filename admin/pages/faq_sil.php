<?php

require_post_csrf('index.php?do=faq');

$id = (int) post('id', 0);

if ($id > 0) {
    $code->deleteFaq($id);
    flash_set('success', 'FAQ kaydı silindi.');
}

redirect('index.php?do=faq');
