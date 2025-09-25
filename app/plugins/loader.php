<?php
// load each plugin’s bootstrap once; keep dumb on purpose
$PLUGINS_DIR = __DIR__;
$dh = @scandir($PLUGINS_DIR) ?: [];
foreach ($dh as $slug) {
  if ($slug === '.' || $slug === '..') continue;
  $base = $PLUGINS_DIR . '/' . $slug;
  if (!is_dir($base)) continue;
  $bootstrap = $base . '/bootstrap.php';
  if (is_file($bootstrap)) { require_once $bootstrap; }
}
