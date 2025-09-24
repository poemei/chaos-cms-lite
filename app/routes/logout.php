<?php
// /app/routes/logout.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$APP  = dirname(__DIR__);
require_once $APP . '/core/utility.php';
require_once $APP . '/core/auth.php';

auth_logout();
redirect_to('/');
