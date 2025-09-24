<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$parts = array_values(array_filter(explode('/', trim($path, '/'))));
$seg1  = $parts[0] ?? '';
$seg2  = $parts[1] ?? '';

// core helpers (no re-defs here)
require_once $ROOT.'/app/core/utility.php';
if (is_file($ROOT.'/app/core/auth.php')) {
  require_once $ROOT.'/app/core/auth.php';
}

// early special (no theme shell)
if ($path === '/sitemap.xml' && is_file($ROOT.'/app/sitemap.php')) { require $ROOT.'/app/sitemap.php'; exit; }
if ($seg1 === 'admin') {
  $admin = $ROOT.'/app/admin/index.php';
  if (is_file($admin)) { require $admin; exit; }
  http_response_code(404); echo 'Admin not found'; exit;
}

// site + theme (only for header/footer includes)
$site   = json_read($ROOT.'/data/site.json', []);

// open header
if (is_file($header)) require $header;

/* ============================
   BETWEEN HEADER & FOOTER ONLY
   ============================ */

// 1) HOME ? module (default_module or "home")
if ($seg1 === '') {
  $defaultMod = (string)($site['default_module'] ?? 'home');
  $homeMain   = $ROOT.'/public/modules/'.$defaultMod.'/main.php';
  if (is_file($homeMain)) {
    $MODULE_ROOT = dirname($homeMain);
    require $homeMain;
    if (is_file($footer)) require $footer;
    exit;
  }
  // if no home module, fall through to pages handler (home.json)
}

// 2) ANY MODULE: /<module>[/...]
if ($seg1 !== '') {
  $modMain = $ROOT.'/public/modules/'.$seg1.'/main.php';
  if (is_file($modMain)) {
    $MODULE_ROOT = dirname($modMain);
    require $modMain;
    if (is_file($footer)) require $footer;
    exit;
  }
}

// 3) PAGES CATCH-ALL (/, /page/<slug>, shorthand /slug, nested /a/b/c)
$pagesHandler = $ROOT.'/app/routes/pages.php';
if (is_file($pagesHandler)) {
  // provide routing context; pages.php echoes content if it finds something
  require $pagesHandler;
  if (is_file($footer)) require $footer;
  exit;
}

// 4) 404 (themed if available)
http_response_code(404);
$nf = $ROOT.'/app/routes/404.php';
if (is_file($nf)) require $nf; else echo '<h2>404</h2><p>Page not found.</p>';

// close footer
if (is_file($footer)) require $footer;
