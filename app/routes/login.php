<?php
// /app/routes/login.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$APP  = dirname(__DIR__);       // /app
$ROOT = dirname($APP);          // project root

require_once $APP . '/core/utility.php';
require_once $APP . '/core/auth.php';

// Resolve theme for header/footer
$site  = json_read($ROOT . '/data/site.json', []);
$theme = $site['theme'] ?? 'chaos';
$hdr   = $ROOT . "/public/themes/{$theme}/includes/header.php";
$ftr   = $ROOT . "/public/themes/{$theme}/includes/footer.php";

// POST = attempt login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login = '';
  foreach (['user','username','email'] as $k) { if (isset($_POST[$k]) && $_POST[$k] !== '') { $login = (string)$_POST[$k]; break; } }
  $pass  = '';
  foreach (['pass','password'] as $k) { if (isset($_POST[$k]) && $_POST[$k] !== '') { $pass  = (string)$_POST[$k]; break; } }

  if ($login === '' || $pass === '') {
    $_SESSION['flash_error'] = 'Please enter your username/email and password.';
    redirect_to('/login');
  }

  [$ok, $msg] = auth_login($login, $pass);
  if ($ok) {
    $_SESSION['flash_ok'] = 'Welcome back!';
    redirect_to('/admin');
  }
  $_SESSION['flash_error'] = $msg ?: 'Login failed.';
  redirect_to('/login');
}

// GET = render page (JSON if available, else fallback)
if (is_file($hdr)) require $hdr;

if (!empty($_SESSION['flash_ok']))   { echo '<div class="alert alert-success">'.h($_SESSION['flash_ok']).'</div>'; unset($_SESSION['flash_ok']); }
if (!empty($_SESSION['flash_error'])){ echo '<div class="alert alert-danger">'.h($_SESSION['flash_error']).'</div>'; unset($_SESSION['flash_error']); }

// Try data/pages/login.json then data/pages/auth/login.json
$candidates = [
  $ROOT . '/data/pages/login.json',
  $ROOT . '/data/pages/auth/login.json',
];

$found = null;
foreach ($candidates as $pf) {
  if (is_file($pf)) { $found = json_read($pf, null); if ($found !== null) break; }
}

if ($found) {
  if (isset($found[0]) && is_array($found[0])) {
    foreach ($found as $block) echo render_article($block);
  } else {
    echo render_article($found);
  }
} else {
  // Safe inline fallback
  echo render_article([
    'title' => 'Login',
    'html'  => '<form method="post" action="/login" class="vstack gap-3">'
             . '<label class="form-label">Username or Email'
             . '<input class="form-control" type="text" name="user" required></label>'
             . '<label class="form-label">Password'
             . '<input class="form-control" type="password" name="pass" required></label>'
             . '<button class="btn btn-primary">Sign in</button>'
             . '<p class="mt-2"><a href="/register">Need an account? Register</a></p>'
             . '</form>',
  ]);
}

if (is_file($ftr)) require $ftr;
