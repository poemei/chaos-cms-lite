<?php
// /app/routes/auth.php
require_once __DIR__ . '/../core/utility.php';
require_once __DIR__ . '/../core/auth.php';

$ROOT = dirname(__DIR__, 1);

// login (GET)
if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
  if (is_logged_in()) redirect_to($_GET['next'] ?? '/admin');
  $page = json_read($ROOT.'/data/pages/login.json', null);
  echo $page ? render_article($page) : '<h2>Login</h2>';
  require $ROOT.'/public/themes/'.$theme.'/includes/footer.php';
  exit;
}

// login (POST)
if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = trim($_POST['user'] ?? '');
  $pass = (string)($_POST['pass'] ?? '');
  if (auth_attempt($user, $pass)) {
    redirect_to($_POST['next'] ?? '/admin');
  }
  $_SESSION['flash'] = 'Invalid credentials';
  redirect_to('/login');
}

// register (GET)
if ($path === '/register' && $_SERVER['REQUEST_METHOD'] === 'GET') {
  if (is_logged_in()) redirect_to('/admin');
  $page = json_read($ROOT.'/data/pages/register.json', null);
  echo $page ? render_article($page) : '<h2>Register</h2>';
  require $ROOT.'/public/themes/'.$theme.'/includes/footer.php';
  exit;
}

// register (POST)
if ($path === '/register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = trim($_POST['user'] ?? '');
  $pass = (string)($_POST['pass'] ?? '');
  if (auth_register($user, $pass)) {
    redirect_to('/login');
  }
  $_SESSION['flash'] = 'Registration failed';
  redirect_to('/register');
}

// logout
if ($path === '/logout') {
  auth_logout();
  redirect_to('/');
}
