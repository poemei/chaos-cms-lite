<?php
$dir = $ADMIN['ROOT'].'/data/posts';
$items = [];
if (is_dir($dir)) {
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..' || !str_ends_with($f, '.json')) continue;
    $p = json_read($dir.'/'.$f, []);
    $items[] = [
      'slug'  => basename($f, '.json'),
      'title' => (string)($p['title'] ?? basename($f, '.json')),
      'date'  => (string)($p['date'] ?? ''),
    ];
  }
}
usort($items, fn($a,$b)=>strcmp($b['date'] ?? '', $a['date'] ?? ''));
?>
<h2>Posts</h2>
<?php if (!$items): ?>
  <p>No posts yet.</p>
<?php else: ?>
  <ul>
    <?php foreach ($items as $it): ?>
      <li><a href="/post/<?= h($it['slug']) ?>"><?= h($it['title']) ?></a><?= $it['date'] ? ' — '.h($it['date']) : '' ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>