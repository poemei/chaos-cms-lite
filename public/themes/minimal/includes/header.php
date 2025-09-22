<?php
// minimal header; expects $site, $theme; uses h(), url(), is_admin()
$site = $site ?? ['name' => 'chaoscms-lite', 'nav' => []];
$theme = $theme ?? 'minimal';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($site['name'] ?? 'chaoscms-lite') ?></title>
  <link rel="icon" href="/public/themes/<?= h($theme) ?>/assets/icon.png">
  <link rel="stylesheet" href="/public/themes/<?= h($theme) ?>/css/minimal.css">
</head>
<body>
<header class="wrap header">
  <a class="brand" href="/"><?= h($site['name'] ?? 'chaoscms-lite') ?></a>
  <nav class="nav">
    <?php foreach (($site['nav'] ?? []) as $n): ?>
      <a href="<?= h(url($n['href'] ?? '#')) ?>"><?= h($n['label'] ?? '') ?></a>
    <?php endforeach; ?>

    <?php if (function_exists('is_admin') && is_admin()): ?>
      <a href="/admin">Admin</a>
      <a href="/logout">Logout</a>
    <?php else: ?>
      <a href="/login">Login</a>
    <?php endif; ?>
  </nav>
</header>

<main class="wrap main">
