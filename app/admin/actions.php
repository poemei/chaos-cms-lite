<?php
// /app/admin/actions.php — process ?a=... then typically redirect

$action = $_GET['a'] ?? '';

if ($action === 'logout') {
  auth_logout();
  redirect_to('/');
}

if ($action === 'settings_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $cfg = json_read($ROOT.'/data/settings.json', []);
  $cfg['send_from']   = trim($_POST['send_from']   ?? ($cfg['send_from']   ?? ''));
  $cfg['smtp_host']   = trim($_POST['smtp_host']   ?? ($cfg['smtp_host']   ?? ''));
  $cfg['smtp_port']   = (int)($_POST['smtp_port']  ?? ($cfg['smtp_port']   ?? 0));
  $cfg['smtp_user']   = trim($_POST['smtp_user']   ?? ($cfg['smtp_user']   ?? ''));
  $cfg['smtp_pass']   = trim($_POST['smtp_pass']   ?? ($cfg['smtp_pass']   ?? ''));
  $cfg['smtp_secure'] = trim($_POST['smtp_secure'] ?? ($cfg['smtp_secure'] ?? ''));
  $cfg['use_mail_fallback'] = isset($_POST['use_mail_fallback']) ? 1 : 0;

  json_write($ROOT.'/data/settings.json', $cfg);
  $_SESSION['flash_ok'] = 'Settings saved.';
  redirect_to('/admin?p=settings');
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  // accepts username or email
  $login = $_POST['user'] ?? '';
  $pass  = $_POST['pass'] ?? '';
  [$ok,$msg] = auth_login($login, $pass);
  if ($ok) redirect_to('/admin');
  $_SESSION['flash_error'] = $msg;
  redirect_to('/login');
}