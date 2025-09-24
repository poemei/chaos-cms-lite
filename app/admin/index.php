<?php
// /app/admin/index.php — router-only
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

// No session_start() here — auth.php already started it via main router
$APP  = dirname(__DIR__);      // /app
$ROOT = dirname($APP);         // project root

require_once $APP.'/core/utility.php';
require_once $APP.'/core/auth.php';

if (!is_logged_in()) { redirect_to('/login'); }

$site  = json_read($ROOT.'/data/site.json', ['name'=>'chaoscms-lite','nav'=>[]]);
$theme = $site['theme'] ?? 'chaos';

$slug = $_GET['p'] ?? 'main';
$slug = preg_replace('~[^a-z0-9_\-]~i', '', $slug);
if ($slug === '') $slug = 'main';

$pageFile = __DIR__.'/pages/'.$slug.'.php';

$header = $ROOT."/public/themes/{$theme}/includes/header.php";
$footer = $ROOT."/public/themes/{$theme}/includes/footer.php";

if (is_file($header)) require $header;

if (!is_file($pageFile)) {
  http_response_code(404);
  echo '<h2>Admin</h2><p>Page not found.</p>';
} else {
  // Common vars available to pages
  $ADMIN = ['ROOT'=>$ROOT,'APP'=>$APP,'SITE'=>$site,'THEME'=>$theme];
  require $pageFile;
}

if (is_file($footer)) require $footer;
