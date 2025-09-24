<?php
// not_found.php — themed 404 content only
if (!isset($ROOT)) { $ROOT = dirname(__DIR__, 2); } // project root

http_response_code(404);
$pg404 = json_read($ROOT.'/data/pages/404.json', null);

if ($pg404 !== null) {
  if (is_array($pg404) && $pg404 && isset($pg404[0]) && is_array($pg404[0])) {
    foreach ($pg404 as $b) echo render_article($b);
  } else {
    echo render_article($pg404);
  }
} else {
  echo '<h2>404</h2><p>Page not found.</p>';
}
