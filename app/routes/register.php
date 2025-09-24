<?php
// /app/routes/register.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$APP  = dirname(__DIR__);
$ROOT = dirname($APP);

require_once $APP . '/core/utility.php';
require_once $APP . '/core/auth.php';

$site  = json_read($ROOT . '/data/site.json', []);
$theme = $site['theme'] ?? 'chaos';
$hdr   = $ROOT . "/public/themes/{$theme}/includes/header.php";
$ftr   = $ROOT . "/public/themes/{$theme}/includes/footer.php";

$read_alias = function(array $keys, string $default = ''): string {
  foreach ($keys as $k) {
    if (isset($_POST[$k]) && $_POST[$k] !== '') {
      return trim((string)$_POST[$k]);
    }
  }
  return $default;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Accept common aliases
  $username = $read_alias(['username','user','name','login']);
  $email    = $read_alias(['email','mail']);
  $pass     = $read_alias(['pass','password','pwd']);

  if ($username === '' || $pass === '') {
    $_SESSION['flash_error'] = 'Username and password are required.';
    redirect_to('/register');
  }

  [$ok, $msg] = auth_register($username, $pass, $email);
  if ($ok) {
    $_SESSION['flash_ok'] = 'Account created. Please sign in.';
    redirect_to('/login');
  }
  $_SESSION['flash_error'] = $msg ?: 'Registration failed.';
  redirect_to('/register');
}

// GET render
if (is_file($hdr)) require $hdr;

if (!empty($_SESSION['flash_ok']))   { echo '<div class="alert alert-success">'.h($_SESSION['flash_ok']).'</div>'; unset($_SESSION['flash_ok']); }
if (!empty($_SESSION['flash_error'])){ echo '<div class="alert alert-danger">'.h($_SESSION['flash_error']).'</div>'; unset($_SESSION['flash_error']); }

$candidates = [
  $ROOT . '/data/pages/register.json',
  $ROOT . '/data/pages/auth/register.json',
];

$found = null;
foreach ($candidates as $pf) {
  if (is_file($pf)) { $found = json_read($pf, null); if ($found !== null) break; }
}

if ($found) {
  if (isset($found[0]) && is_array($found[0])) { foreach ($found as $b) echo render_article($b); }
  else { echo render_article($found); }
} else {
  // Safe fallback form
  echo render_article([
    'title' => 'Register',
    'html'  => '<form method="post" action="/register" class="vstack gap-3">'
             . '<label class="form-label">Username'
             . '<input class="form-control" type="text" name="username" required></label>'
             . '<label class="form-label">Email (optional)'
             . '<input class="form-control" type="email" name="email"></label>'
             . '<label class="form-label">Password'
             . '<input class="form-control" type="password" name="pass" required></label>'
             . '<button class="btn btn-success">Create account</button>'
             . '</form>',
  ]);
}

if (is_file($ftr)) require $ftr;
