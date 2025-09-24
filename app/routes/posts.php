<?php
// posts.php — list + single from /data/posts
if (!isset($ROOT)) { $ROOT = dirname(__DIR__, 2); } // project root

$postsDir = $ROOT.'/data/posts';
$slug = $seg2 ?? '';

// Single: /posts/<slug>
if ($seg2 !== '') {
  $pf = $postsDir.'/'.$slug.'.json';
  $post = is_file($pf) ? json_read($pf, null) : null;
  if ($post) { echo render_article($post); return; }
  http_response_code(404); echo '<h2>Post not found</h2>'; return;
}

// List
echo '<h2>Posts</h2>';
$files = glob($postsDir.'/*.json', GLOB_NOSORT) ?: [];
if (!$files) { echo '<p>No posts yet.</p>'; return; }

$items = [];
foreach ($files as $pf) {
  $post = json_read($pf, null);
  if (!is_array($post)) continue;
  $slug = basename($pf, '.json');
  $items[] = [
    'slug'  => $slug,
    'title' => trim((string)($post['title'] ?? $slug)),
    'date'  => trim((string)($post['date']  ?? '')),
    'desc'  => trim((string)($post['excerpt'] ?? ($post['summary'] ?? ''))),
  ];
}

usort($items, function($a,$b){
  $da = $a['date'] ?? '';
  $db = $b['date'] ?? '';
  if ($da === '' && $db === '') return 0;
  if ($da === '') return 1;
  if ($db === '') return -1;
  return strcmp($db, $da);
});

echo '<div class="row g-3">';
foreach ($items as $it) {
  echo '<div class="col-12 col-sm-6 col-lg-4">';
  echo '  <div class="card h-100" style="border:1px solid #eee;border-radius:10px">';
  echo '    <div class="card-body">';
  echo '      <h5 class="card-title"><a href="/posts/'.h($it['slug']).'">'.h($it['title']).'</a></h5>';
  if ($it['date']) echo '  <div class="text-muted small">'.h($it['date']).'</div>';
  if ($it['desc']) echo '  <p class="card-text mt-2">'.h($it['desc']).'</p>';
  echo '    </div>';
  echo '  </div>';
  echo '</div>';
}
echo '</div>';
