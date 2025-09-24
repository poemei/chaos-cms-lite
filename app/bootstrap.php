<?php
/*
 * ChaosCMS-Lite Bootstrap
 * Version 1.0.0
 * No database; JSON + modules + themes
 */
declare(strict_types=1);

// ---- Project + Core paths ----
$ROOT = dirname(__DIR__);                    // project root
define('APP_CORE', $ROOT . '/app/core');

// ---- Core includes ----
require_once APP_CORE . '/json_store.php';
require_once APP_CORE . '/utility.php';      // or util.php if that's your file

// Optional JSON datastore (safe to skip if not present)
if (is_file(APP_CORE . '/jsondb.php')) {
  require_once APP_CORE . '/jsondb.php';
}

// ---- Site config (theme, nav, etc.) ----
$site = json_read($ROOT . '/data/site.json', [
  'name'  => 'Chaos CMS',
  'theme' => 'chaos',
  'nav'   => []
]);

// Expose theme vars used by router + theme includes
$theme     = preg_replace('~[^a-z0-9_-]~i', '', (string)($site['theme'] ?? 'chaos'));
$theme_dir = $ROOT . '/public/themes/' . $theme;
$header    = $theme_dir . '/includes/header.php';
$footer    = $theme_dir . '/includes/footer.php';

// ---- Hand off to router ----
$router = $ROOT . '/app/router.php';
if (!is_file($router)) {
  pretty_error('Router not found', "Expected at: $router", 500);
}
require $router;
