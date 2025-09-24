<?php
// home.php — load default module for "/"
if (!isset($ROOT)) { $ROOT = dirname(__DIR__); } // /app
$ROOT = dirname($ROOT); // project root

$site = json_read($ROOT.'/data/site.json', []);
$defaultMod = (string)($site['default_module'] ?? 'home');

$home = $ROOT.'/public/modules/'.$defaultMod.'/main.php';
if (is_file($home)) {
  $MODULE_ROOT = dirname($home);
  require $home;
  return;
}

// Fallback if module missing:
http_response_code(404);
echo '<h2>Home module not found</h2><p>Expected: <code>/public/modules/'
     .h($defaultMod).'/main.php</code></p>';