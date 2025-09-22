<?php
// /app/logout.php
declare(strict_types=1);

$ROOT = dirname(__DIR__);                    // project root
define('APP_CORE', $ROOT . '/app/core');
require_once APP_CORE . '/auth.php';

auth_logout();
header('Location: /login'); exit;