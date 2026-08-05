<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/helpers.php';

if (is_logged_in()) {
    redirect('app/index.php');
}

require_once __DIR__ . '/index.php';
