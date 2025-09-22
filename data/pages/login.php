<?php
// /app/login.php
declare(strict_types=1);

$ROOT = dirname(__DIR__);                    // project root
//define('APP_CORE', $ROOT . '/app/core');
require_once APP_CORE . '/auth.php';

// show form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $err = (string)($_GET['err'] ?? '');
  $ok  = (string)($_GET['ok']  ?? '');
  $t   = auth_csrf_token();
  ?>
  <div class="wrap">
    <div class="card" style="max-width:520px;margin:20px auto;">
      <h2 style="margin:0 0 12px 0;">Sign In</h2>
      <?php if ($ok): ?><div style="color:#0a7d00;margin:8px 0;">Account created. Please sign in.</div><?php endif; ?>
      <?php if ($err): ?><div style="color:#b00020;margin:8px 0;"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <form method="post">
        <label>Username<br><input name="user" required></label><br>
        <label>Password<br><input type="password" name="pass" required></label><br>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($t) ?>">
        <button type="submit">Sign in</button>
        <div style="margin-top:8px;font-size:.9rem;">No account? <a href="/register">Create one</a></div>
      </form>
    </div>
  </div>
  <?php
  return;
}

// handle submit
$user = trim((string)($_POST['user'] ?? ''));
$pass = (string)($_POST['pass'] ?? '');
$tok  = (string)($_POST['csrf'] ?? '');
if (!auth_csrf_check($tok)) { header('Location: /login?err=Bad+token'); exit; }

$res = auth_login($user, $pass);
if (!$res['ok']) { header('Location: /login?err=Invalid+credentials'); exit; }

header('Location: /admin'); exit;