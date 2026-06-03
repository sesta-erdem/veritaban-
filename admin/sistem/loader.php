<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fonksiyon.php';
require_once __DIR__ . '/classes/AdminInputGuard.php';
require_once __DIR__ . '/classes/AdminStoredProcedureDal.php';
require_once __DIR__ . '/classes/AdminCode.php';

$code = new AdminCode();
