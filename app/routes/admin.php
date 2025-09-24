<?php
// /app/routes/admin.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$APP  = dirname(__DIR__);   // /app
$ROOT = dirname($APP);

require_once $APP . '/core/utility.php';
require_once $APP . '/core/auth.php';

// Require login
if (!auth_is_logged_in()) {
  // Optional: remember where to come back to
  redirect_to('/login?next=' . rawurlencode('/admin'));
}

// Hand off to the admin router
require $APP . '/admin/index.php';
