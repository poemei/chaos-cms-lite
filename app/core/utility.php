<?php
// app/core/utility.php — Lite core helpers (JSON-backed, no DB)
declare(strict_types=1);

/* ---------- HTML / URL ---------- */

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('url')) {
  function url(string $path = ''): string {
    if ($path === '') return '/';
    // collapse // and normalize
    $u = '/' . ltrim($path, '/');
    $u = preg_replace('~/{2,}~', '/', $u);
    return $u;
  }
}

if (!function_exists('redirect_to')) {
  function redirect_to(string $path): void {
    header('Location: ' . url($path));
    exit;
  }
}

/* ---------- JSON I/O ---------- */

if (!function_exists('json_read')) {
  /**
   * Read JSON from file. Returns $fallback if missing/invalid.
   * Strips UTF-8 BOM and tolerates invalid UTF-8.
   */
  function json_read(string $file, $fallback = null) {
    if (!is_file($file)) return $fallback;
    $raw = @file_get_contents($file);
    if ($raw === false) return $fallback;
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3); // BOM
    $data = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    if (json_last_error() !== JSON_ERROR_NONE) return $fallback;
    return $data;
  }
}

if (!function_exists('json_write')) {
  /**
   * Pretty-write JSON to file, creating parent dirs if needed.
   */
  function json_write(string $file, $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    return @file_put_contents($file, $json, LOCK_EX) !== false;
  }
}

/* ---------- RENDER ---------- */

if (!function_exists('render_article')) {
  /**
   * Render one “article-like” block:
   * - title: string
   * - html: raw HTML string (already trusted by editor)
   * - sections: optional array of {id,title,html}
   * - links: optional array of {label,href}
   * - codes: optional array of code strings
   * - lang: optional language hint for <code class="language-...">
   */
  function render_article(array $block): string {
    $title    = (string)($block['title'] ?? '');
    $html     = (string)($block['html']  ?? '');
    $sections = is_array($block['sections'] ?? null) ? $block['sections'] : [];
    $links    = is_array($block['links']    ?? null) ? $block['links']    : [];
    $codes    = is_array($block['codes']    ?? null) ? $block['codes']    : [];
    $lang     = (string)($block['lang']     ?? '');

    ob_start(); ?>
    <article class="chaos-article" style="max-width:1000px;margin:16px auto;padding:0 12px">
      <?php if ($title !== ''): ?>
        <h2><?= h($title) ?></h2>
      <?php endif; ?>

      <?php if ($links): ?>
        <nav class="mb-3">
          <ul class="list-inline">
            <?php foreach ($links as $lk): ?>
              <li class="list-inline-item"><a href="<?= h((string)($lk['href'] ?? '#')) ?>"><?= h((string)($lk['label'] ?? 'link')) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endif; ?>

      <?php if ($html !== ''): ?>
        <div class="article-body"><?= $html ?></div>
      <?php endif; ?>

      <?php foreach ($codes as $c): ?>
        <pre><code<?= $lang ? ' class="language-'.h($lang).'"' : '' ?>><?= htmlspecialchars($c, ENT_NOQUOTES, 'UTF-8') ?></code></pre>
      <?php endforeach; ?>

      <?php foreach ($sections as $sec): ?>
        <section id="<?= h((string)($sec['id'] ?? '')) ?>" class="mt-4">
          <?php if (!empty($sec['title'])): ?><h3><?= h((string)$sec['title']) ?></h3><?php endif; ?>
          <?php if (!empty($sec['html'])): ?><div><?= (string)$sec['html'] ?></div><?php endif; ?>
        </section>
      <?php endforeach; ?>
    </article>
    <?php
    return (string)ob_get_clean();
  }
}


