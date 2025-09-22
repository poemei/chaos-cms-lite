<?php
// /app/router.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Project root (router is in /app)
$ROOT = dirname(__DIR__);

// --- Expect these from bootstrap; provide safe defaults if missing ---
if (!isset($site) || !is_array($site)) {
  // fallback only; bootstrap should normally set this
  $site = ['name'=>'chaoscms-lite', 'theme'=>'chaos', 'nav'=>[]];
}
if (!isset($theme)) {
  $theme = preg_replace('~[^a-z0-9_-]~i', '', (string)($site['theme'] ?? 'chaos'));
}
$THEME_DIR = $ROOT . '/public/themes/' . $theme;
$HEADER    = $HEADER    ?? ($THEME_DIR . '/includes/header.php');
$FOOTER    = $FOOTER    ?? ($THEME_DIR . '/includes/footer.php');

// --- URL parts ---
$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$parts = array_values(array_filter(explode('/', trim($path, '/'))));
$seg1  = $parts[0] ?? '';
$seg2  = $parts[1] ?? '';

// --- helpers ---
if (!function_exists('render_page_data')) {
  function render_page_data($data): void {
    // Accept one object or a list of blocks
    $isList = is_array($data) && $data && isset($data[0]) && is_array($data[0]);
    if ($isList) {
      foreach ($data as $block) {
        echo render_article([
          'slug'     => (string)($block['slug'] ?? ''),
          'title'    => (string)($block['title'] ?? ''),
          'html'     => (string)($block['html'] ?? ''),
          'sections' => is_array($block['sections'] ?? null) ? $block['sections'] : [],
          'links'    => is_array($block['links'] ?? null) ? $block['links'] : [],
        ]);
      }
    } else {
      echo render_article(is_array($data) ? $data : ['title'=>'', 'html'=>'']);
    }
  }
}

// ---------- special endpoints (NO THEME SHELL) ----------
if ($path === '/sitemap.xml') { require $ROOT . '/app/sitemap.php'; exit; }
if ($seg1 === 'contact')     { require $ROOT . '/app/contact.php'; exit; }

// ---------- theme shell ----------
if (is_file($HEADER)) require $HEADER; else require $ROOT . '/includes/header.php';

// ---------- Auth ---------
// after the theme header is required…
if ($seg1 === 'register' && $seg2 === '') {
  require $ROOT . '/data/pages/register.php';
  if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
  exit;
}

if ($seg1 === 'login' && $seg2 === '') {
  require $ROOT . '/data/pages/login.php';
  if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
  exit;
}

if ($seg1 === 'logout') {
  require $ROOT . '/data/pages/logout.php'; // will redirect
  exit;
}

// --- Admin (themed) ---
if ($seg1 === 'admin') {
  require $ROOT . '/app/admin.php';
  if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
  exit;
}


// ---------- home ----------
if ($seg1 === '' || $seg1 === 'index.php') {
  $data = json_read($ROOT . '/data/pages/home.json', ['title'=>'Home','html'=>'<p>Welcome.</p>']);
  render_page_data($data);
  if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
  exit;
}

// ---------- posts list ----------
if ($seg1 === 'posts' && $seg2 === '') {
  echo '<h2>Posts</h2>';
  $dir = $ROOT . '/data/posts';
  $items = [];
  if (is_dir($dir)) {
    foreach (scandir($dir) ?: [] as $f) {
      if ($f === '.' || $f === '..' || !str_ends_with($f, '.json')) continue;
      $post = json_read($dir . '/' . $f, null); if (!$post) continue;
      $slug = basename($f, '.json');
      $items[] = [
        'slug'  => $slug,
        'title' => (string)($post['title'] ?? $slug),
        'date'  => (string)($post['date'] ?? ''),
        'desc'  => (string)($post['excerpt'] ?? ''),
      ];
    }
  }
  if (!$items) { echo '<p>No posts yet.</p>'; if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php'; exit; }
  usort($items, fn($a,$b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
  echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px">';
  foreach ($items as $it) {
    echo '<div class="card">';
    echo '<div style="font-weight:600"><a href="/post/'.h($it['slug']).'">'.h($it['title']).'</a></div>';
    if ($it['date']) echo '<div style="color:#777;font-size:.9rem">'.h($it['date']).'</div>';
    if ($it['desc']) echo '<div style="margin-top:6px;color:#444">'.h($it['desc']).'</div>';
    echo '</div>';
  }
  echo '</div>';
  if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
  exit;
}

// ---------- single post ----------
if ($seg1 === 'post' && $seg2 !== '') {
  $post = json_read($ROOT . '/data/posts/' . $seg2 . '.json', null);
  if (!$post) { http_response_code(404); echo '<h2>Not found</h2>'; if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php'; exit; }
  echo render_article($post);
  if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
  exit;
}

// ---------- explicit pages: /page/<slug> ----------
if ($seg1 === 'page' && $seg2 !== '') {
  $data = json_read($ROOT . '/data/pages/' . $seg2 . '.json', null);
  if (!$data) { http_response_code(404); echo '<h2>Not found</h2>'; if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php'; exit; }
  render_page_data($data);
  if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
  exit;
}

// ---------- nested pages: /a[/b[/c...]] -> data/pages/<path>.json or .../<path>/index.json ----------
$reservedTop = ['page', 'post', 'posts', 'contact', 'sitemap.xml', 'admin', 'login', 'logout'];
$subpath = trim($path, '/');

if ($subpath !== '' && !in_array($seg1, $reservedTop, true)) {
  $base = $ROOT . '/data/pages/' . $subpath;
  $candidates = [
    $base . '.json',          // e.g., data/pages/codex/core.json
    $base . '/main.json',    // e.g., data/pages/codex/index.json  (for /codex)
  ];
  foreach ($candidates as $pf) {
    if (is_file($pf)) {
      $data = json_read($pf, null);
      if ($data) {
        render_page_data($data);
        if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
        exit;
      }
    }
  }
}

// ---------- module fallback: /<slug> -> public/modules/<slug>/main.php ----------
$modMain = $ROOT . '/public/modules/' . $seg1 . '/main.php';
if ($seg1 !== '' && $seg2 === '' && is_file($modMain)) {
  $MODULE_ROOT = dirname($modMain);
  require $modMain;  // module prints content within theme shell
  if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
  exit;
}

// ---------- 404 ----------
http_response_code(404);
$pg404 = json_read($ROOT . '/data/pages/404.json', null);
if ($pg404) { render_page_data($pg404); }
else { echo '<h2>404</h2><p>Page not found.</p>'; }
if (is_file($FOOTER)) require $FOOTER; else require $ROOT . '/includes/footer.php';
