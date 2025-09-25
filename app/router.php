<?php
// /app/router.php ? skinny router
declare(strict_types=1);

require_once __DIR__ . '/core/utility.php';

$ROOT  = dirname(__DIR__);
$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$parts = array_values(array_filter(explode('/', trim($path,'/'))));
$seg1  = $parts[0] ?? '';
$seg2  = $parts[1] ?? '';

// Site + theme
$site  = json_read($ROOT.'/data/site.json', ['name'=>'chaoscms-lite','nav'=>[]]);
$theme = $site['theme'] ?? 'chaos';

// Special routes (no theme shell)
if ($path === '/sitemap.xml') { require __DIR__.'/routes/sitemap.php'; exit; }
if ($seg1 === 'contact')     { require __DIR__.'/routes/contact.php'; exit; }
if ($seg1 === 'login' || $seg1 === 'logout' || $seg1 === 'register') { require __DIR__.'/routes/auth.php'; exit; }
if ($seg1 === 'admin')       { require __DIR__.'/routes/admin.php'; exit; }

// Theme header
require $ROOT.'/public/themes/'.$theme.'/includes/header.php';

/* ---------- HOME ---------- *
 * Prefer module: /public/modules/home/main.php
 * Fallback:      /data/pages/home.json
 */
if ($seg1 === '' || $seg1 === 'index.php') {
    $modHome = $ROOT.'/public/modules/home/main.php';
    if (is_file($modHome)) {
        // Modules run inside the theme shell; do NOT include header/footer inside module
        require $modHome;
        require $ROOT.'/public/themes/'.$theme.'/includes/footer.php';
        exit;
    }
    $data = json_read($ROOT.'/data/pages/home.json', ['title'=>'Home','html'=>'<p>Welcome.</p>']);
    echo render_article($data);
    require $ROOT.'/public/themes/'.$theme.'/includes/footer.php';
    exit;
}

// Posts
if ($seg1 === 'posts') { require __DIR__.'/routes/posts.php'; exit; }

// Explicit pages: /page/<slug>
if ($seg1 === 'page' && $seg2 !== '') {
    $data = json_read($ROOT.'/data/pages/'.$seg2.'.json', null);
    echo $data ? render_article($data) : '<h2>Not found</h2>';
    require $ROOT.'/public/themes/'.$theme.'/includes/footer.php';
    exit;
}

// Shorthand pages: /<slug> -> data/pages/<slug>.json
if ($seg1 && is_file($ROOT.'/data/pages/'.$seg1.'.json')) {
    $data = json_read($ROOT.'/data/pages/'.$seg1.'.json', null);
    echo $data ? render_article($data) : '<h2>Not found</h2>';
    require $ROOT.'/public/themes/'.$theme.'/includes/footer.php';
    exit;
}

// Module fallback: /<slug> -> /public/modules/<slug>/main.php
$modMain = $ROOT.'/public/modules/'.$seg1.'/main.php';
if ($seg1 && is_file($modMain)) {
    require $modMain; // runs inside shell
    require $ROOT.'/public/themes/'.$theme.'/includes/footer.php';
    exit;
}

// 404
http_response_code(404);
echo '<h2>404</h2><p>Page not found.</p>';
require $ROOT.'/public/themes/'.$theme.'/includes/footer.php';
