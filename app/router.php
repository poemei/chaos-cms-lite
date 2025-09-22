<?php
// app/router.php — lean JSON-first router (no admin logic here)
// Assumes .htaccess routes /admin/* to /admin/index.php directly.
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

$ROOT  = dirname(__DIR__);               // project root
require_once $ROOT.'/app/core/utility.php';

// ---- request parts ----
$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path  = '/' . ltrim($path, '/');
$path  = preg_replace('~/{2,}~', '/', $path);
$parts = array_values(array_filter(explode('/', trim($path, '/'))));
$seg1  = $parts[0] ?? '';
$seg2  = $parts[1] ?? '';

// ---- special endpoints (no theme shell here)
if ($path === '/sitemap.xml') { require $ROOT.'/sitemap.php'; exit; }
if ($seg1 === 'contact')     { require $ROOT.'/contact.php'; exit; }

// ---- site + theme shell (public only)
$site  = json_read($ROOT.'/data/site.json', []);
$theme = (string)($site['theme'] ?? 'chaos');
$hdr   = $ROOT."/public/themes/{$theme}/includes/header.php";
$ftr   = $ROOT."/public/themes/{$theme}/includes/footer.php";

// ---- tiny helper: render single or list-of-blocks JSON
$render = function($data): void {
  if (is_array($data) && $data && isset($data[0]) && is_array($data[0])) {
    foreach ($data as $blk) {
      echo render_article([
        'title'    => (string)($blk['title'] ?? ''),
        'html'     => (string)($blk['html']  ?? ''),
        'sections' => is_array($blk['sections'] ?? null) ? $blk['sections'] : [],
        'links'    => is_array($blk['links']    ?? null) ? $blk['links']    : [],
        'codes'    => is_array($blk['codes']    ?? null) ? $blk['codes']    : [],
        'lang'     => (string)($blk['lang']     ?? ''),
      ]);
    }
  } else {
    echo render_article(is_array($data) ? $data : ['title'=>'','html'=>'']);
  }
};

// ================= ROUTES =================

// Home -> /data/pages/home.json
if ($seg1 === '' || $seg1 === 'index.php') {
  require $hdr;
  $data = json_read($ROOT.'/data/pages/home.json', ['title'=>'Home','html'=>'<p>Welcome.</p>']);
  $render($data);
  require $ftr; exit;
}

// Explicit page: /page/<slug> -> /data/pages/<slug>.json
if ($seg1 === 'page' && $seg2 !== '') {
  require $hdr;
  $data = json_read($ROOT.'/data/pages/'.$seg2.'.json', null);
  $render($data ?? ['title'=>'Not found','html'=>'<p>Page not found.</p>']);
  require $ftr; exit;
}

// Shorthand page: /<slug> -> /data/pages/<slug>.json (no reserved collisions)
$reserved = ['admin','page','post','posts','contact','sitemap.xml','modules'];
if ($seg1 !== '' && $seg2 === '' && !in_array($seg1, $reserved, true)) {
  $pf = $ROOT.'/data/pages/'.$seg1.'.json';
  if (is_file($pf)) {
    require $hdr;
    $render(json_read($pf, null));
    require $ftr; exit;
  }
}

// Nested pages: /a/b/c -> /data/pages/a/b/c.json or /data/pages/a/b/c/main.json
if ($seg1 !== '' && !in_array($seg1, $reserved, true)) {
  $base = $ROOT.'/data/pages/'.trim($path, '/');
  foreach ([$base.'.json', $base.'/main.json'] as $pf) {
    if (is_file($pf)) {
      require $hdr;
      $render(json_read($pf, null));
      require $ftr; exit;
    }
  }
}

// Posts index: /posts -> list /data/posts/*.json
if ($seg1 === 'posts' && $seg2 === '') {
  $dir = $ROOT.'/data/posts';
  $items = [];
  if (is_dir($dir)) {
    foreach (scandir($dir) ?: [] as $f) {
      if ($f === '.' || $f === '..' || !str_ends_with($f, '.json')) continue;
      $post = json_read($dir.'/'.$f, null) ?: [];
      $items[] = [
        'slug'  => basename($f, '.json'),
        'title' => (string)($post['title'] ?? basename($f, '.json')),
        'date'  => (string)($post['date']  ?? ''),
        'desc'  => (string)($post['excerpt'] ?? ''),
      ];
    }
  }
  require $hdr;
  echo '<h2>Posts</h2>';
  if (!$items) { echo '<p>No posts yet.</p>'; require $ftr; exit; }
  usort($items, fn($a,$b)=>strcmp($b['date'] ?? '', $a['date'] ?? ''));
  echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">';
  foreach ($items as $it) {
    echo '<div class="card p-3">';
    echo '<div style="font-weight:600"><a href="'.h('/post/'.$it['slug']).'">'.h($it['title']).'</a></div>';
    if ($it['date']) echo '<div style="color:#777;font-size:.9rem">'.h($it['date']).'</div>';
    if ($it['desc']) echo '<div style="margin-top:6px;color:#444">'.h($it['desc']).'</div>';
    echo '</div>';
  }
  echo '</div>';
  require $ftr; exit;
}

// Single post: /post/<slug> -> /data/posts/<slug>.json
if ($seg1 === 'post' && $seg2 !== '') {
  require $hdr;
  $post = json_read($ROOT.'/data/posts/'.$seg2.'.json', null);
  $render($post ?? ['title'=>'Not found','html'=>'<p>Post not found.</p>']);
  require $ftr; exit;
}

// Module fallback: /<slug> -> /public/modules/<slug>/main.php (only top-level)
if ($seg1 !== '' && $seg2 === '') {
  $mod = $ROOT.'/public/modules/'.$seg1.'/main.php';
  if (is_file($mod)) {
    require $hdr;
    $MODULE_ROOT = dirname($mod);
    require $mod;
    require $ftr; exit;
  }
}

// 404
http_response_code(404);
require $hdr;
$pg404 = json_read($ROOT.'/data/pages/404.json', null);
$render($pg404 ?? ['title'=>'404','html'=>'<p>Page not found.</p>']);
require $ftr;
