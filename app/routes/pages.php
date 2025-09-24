<?php
// pages.php — JSON-driven content (home, explicit, shorthand, nested + collection)
// Expects $ROOT, $path, $parts, $seg1, $seg2 from the router.

if (!isset($ROOT)) {
  // Fallback only if router didn't provide it.
  $ROOT = dirname(__DIR__, 2); // project root
}

// helper to echo one or many blocks
$render = function($data) {
  if (is_array($data) && $data && isset($data[0]) && is_array($data[0])) {
    foreach ($data as $b) echo render_article($b);
  } else {
    echo render_article(is_array($data) ? $data : ['title'=>'','html'=>'']);
  }
};

// 1) HOME
if ($seg1 === '' || $seg1 === 'index.php') {
  $data = json_read($ROOT.'/data/pages/home.json', ['title'=>'Home','html'=>'<p>Welcome.</p>']);
  $render($data);
  return;
}

// 2) EXPLICIT: /page/<slug>
if ($seg1 === 'page' && $seg2 !== '') {
  $pf = $ROOT.'/data/pages/'.$seg2.'.json';
  $data = json_read($pf, null);
  if ($data !== null) { $render($data); return; }
  http_response_code(404); echo '<h2>Not found</h2>'; return;
}

// 3) SHORTHAND FILE: /<slug> -> data/pages/<slug>.json
if ($seg1 !== '') {
  $pf = $ROOT.'/data/pages/'.$seg1.'.json';
  if (is_file($pf)) { $render(json_read($pf, null)); return; }

  // 3b) SHORTHAND COLLECTION: data/pages/<slug>/*.json
  $dir = $ROOT.'/data/pages/'.$seg1;
  if (is_dir($dir)) {
    $files = glob($dir.'/*.json', GLOB_NOSORT) ?: [];
    if ($files) {
      foreach ($files as $f) {
        $blk = json_read($f, null);
        if ($blk !== null) $render($blk);
      }
      return;
    }
  }
}

// 4) NESTED: /a/b/c -> data/pages/a/b/c.json OR .../main.json OR collection dir
$reservedTop = ['admin','page','sitemap.xml','posts'];
$subpath = trim($path, '/');
if ($subpath !== '' && !in_array($seg1, $reservedTop, true)) {
  $base = $ROOT.'/data/pages/'.$subpath;

  foreach ([$base.'.json', $base.'/main.json'] as $cand) {
    if (is_file($cand)) { $render(json_read($cand, null)); return; }
  }

  if (is_dir($base)) {
    $files = glob($base.'/*.json', GLOB_NOSORT) ?: [];
    if ($files) {
      foreach ($files as $f) {
        $blk = json_read($f, null);
        if ($blk !== null) $render($blk);
      }
      return;
    }
  }
}

// let router produce 404
http_response_code(404);
