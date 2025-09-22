<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$ROOT = dirname(__DIR__);                    // project root
define('APP_CORE', $ROOT . '/app');          // app root
$app = APP_CORE;

require_once $app . '/core/json_store.php';
require_once $app . '/core/utility.php';

$site = json_read($ROOT.'/data/site.json', ['name'=>'chaoscms-lite']);
$__site = $site; $site = $__site;
require $ROOT.'/public/themes/ . $theme /includes/header.php';

$ok = null; $err = null; $saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim((string)($_POST['name']  ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $msg   = trim((string)($_POST['message'] ?? ''));
  if ($name === '' || $email === '' || $msg === '') {
    $err = 'All fields are required.';
  } else {
    $logdir = $ROOT.'/logs';
    if (!is_dir($logdir)) @mkdir($logdir, 0775, true);
    $record = [
      'ts'    => date('c'),
      'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
      'ua'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
      'name'  => $name,
      'email' => $email,
      'msg'   => $msg,
    ];
    $saved = (bool) @file_put_contents($logdir.'/contact_inbox.jsonl', json_encode($record, JSON_UNESCAPED_UNICODE)."\n", 8);
    $ok = $saved;
    if (!$saved) $err = 'Could not save your message.';
  }
}

if ($ok && $saved) {
  echo '<div class="card" style="border-left:4px solid #6c757d;margin-bottom:12px">Thanks! Your message was saved. Email is currently disabled.</div>';
} elseif ($err) {
  echo '<div class="card" style="border-left:4px solid #dc3545;margin-bottom:12px">'.h($err).'</div>';
}
?>
<h2>Contact</h2>
<form method="post" style="display:grid;gap:10px;max-width:560px">
  <label>Name<input type="text" name="name" required></label>
  <label>Email<input type="email" name="email" required></label>
  <label>Message<textarea name="message" rows="6" required></textarea></label>
  <div><button type="submit">Send</button></div>
</form>
<?php require $ROOT.'/public/themes/ . $theme /includes/footer.php'; ?>
