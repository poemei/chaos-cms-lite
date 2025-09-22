<?php
$site = $site ?? ['name'=>'chaoscms-lite','nav'=>[]];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="icon" type="image/x-icon" href="/public/themes/<?= $theme ?>/assets/icon.png">
  <link rel = "stylesheet" href="/public/themes/<?= $theme ?>/css/chaos.css">
  <title><?= h($site['name'] ?? 'chaoscms-lite') ?></title>
</head>

<body>
  <header class="container" style="display:flex;justify-content:space-between;align-items:center">
    <div style="font-weight:700 bg-body-tertiary">
    <a class="navbar-brand" href="#">
      <img src="/public/themes/<?= $theme ?>/assets/icon.svg" alt="Chaos CMS" width="30" height="24">
    </a>
    <?= h($site['name'] ?? 'chaoscms-lite') ?>
    </div>
      <nav>
        <?php foreach (($site['nav'] ?? []) as $n): ?>
          <a href="<?= h(url($n['href'] ?? '')) ?>"><?= h($n['label'] ?? '') ?></a>
        <?php endforeach; ?>
        <?php
        // Auth
        ?>
      </nav>
  </header>
    <main class="container">
