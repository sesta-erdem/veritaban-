<?php

require_post_csrf('index.php?do=blog');

$id = (int) post('id', 0);

if ($id > 0) {
    $code->deleteBlogPost($id);
    flash_set('success', 'Blog yazısı silindi.');
}

redirect('index.php?do=blog');
