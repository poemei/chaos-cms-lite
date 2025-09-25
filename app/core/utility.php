<?php
declare(strict_types=1);

/**
 * Utility helpers for ChaosCMS-Lite
 * - Safe to include multiple times across pages
 * - No double-escaping of embedded HTML from JSON
 * - Robust code-block rendering with ["lang","code"] objects OR raw strings
 */

if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('url')) {
  function url(string $path): string {
    if ($path === '') return '/';
    return '/' . ltrim($path, '/');
  }
}

if (!function_exists('json_read')) {
  function json_read(string $file, $default = null) {
    if (!is_file($file)) return $default;
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : $default;
  }
}

if (!function_exists('json_write')) {
  function json_write(string $file, $data, bool $pretty = true): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    if ($pretty) $flags |= JSON_PRETTY_PRINT;
    $json = json_encode($data, $flags);
    if ($json === false) return false;
    return (bool)@file_put_contents($file, $json);
  }
}

if (!function_exists('render_code_block')) {
  /**
   * Render a single code block with optional language class.
   * Content is entity-encoded; HTML inside code snippets is shown verbatim.
   */
  function render_code_block(string $code, string $lang = 'text'): string {
    // Don't escape the <pre><code> tags, but do escape the code content.
    $codeEsc = htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8');
    $langCls = 'language-' . preg_replace('/[^a-z0-9_\-]+/i', '', $lang);
    return '<pre class="code-block"><code class="' . $langCls . '">' . $codeEsc . '</code></pre>';
  }
}

if (!function_exists('normalize_codes_list')) {
  /**
   * Accepts:
   *  - array of strings
   *  - array of objects with { code, lang }
   *  - single string
   * Returns an array of ['code' => string, 'lang' => string]
   */
  function normalize_codes_list($codes): array {
    $out = [];
    if ($codes === null) return $out;

    // Single string ? single item
    if (is_string($codes)) {
      $out[] = ['code' => $codes, 'lang' => 'text'];
      return $out;
    }

    // Array input
    if (is_array($codes)) {
      foreach ($codes as $item) {
        if (is_string($item)) {
          $out[] = ['code' => $item, 'lang' => 'text'];
        } elseif (is_array($item)) {
          $code = (string)($item['code'] ?? ($item['codes'] ?? ''));
          $lang = (string)($item['lang'] ?? 'text');
          if ($code !== '') {
            $out[] = ['code' => $code, 'lang' => $lang];
          }
        }
      }
    }
    return $out;
  }
}

if (!function_exists('render_article')) {
  /**
   * Render a JSON "article" shape:
   * {
   *   "title": "Title",
   *   "html": "<p>Raw HTML allowed</p>",
   *   "sections": [
   *     { "html": "...", "codes": [...], "links":[{"label":"..","href":".."}] },
   *     ...
   *   ],
   *   "links": [...]
   * }
   *
   * NOTE: We intentionally DO NOT escape "html" — it is trusted authored content.
   * Code blocks ARE escaped inside <pre><code>.
   */
  function render_article($data): string {
    if (!is_array($data)) return '';

    $buf = '';

    // Title
    $title = $data['title'] ?? null;
    if (is_string($title) && $title !== '') {
      $buf .= '<h2>' . h($title) . '</h2>';
    }

    // Top-level HTML (raw, trusted)
    if (isset($data['html']) && is_string($data['html'])) {
      $buf .= $data['html'];
    }

    // Top-level code blocks (rare, but supported)
    if (isset($data['codes'])) {
      foreach (normalize_codes_list($data['codes']) as $block) {
        $buf .= render_code_block($block['code'], $block['lang']);
      }
    }

    // Sections
    if (!empty($data['sections']) && is_array($data['sections'])) {
      foreach ($data['sections'] as $sec) {
        if (!is_array($sec)) continue;

        if (isset($sec['title']) && is_string($sec['title']) && $sec['title'] !== '') {
          $buf .= '<h3>' . h($sec['title']) . '</h3>';
        }

        if (isset($sec['html']) && is_string($sec['html'])) {
          $buf .= $sec['html']; // raw, trusted
        }

        if (isset($sec['codes'])) {
          foreach (normalize_codes_list($sec['codes']) as $block) {
            $buf .= render_code_block($block['code'], $block['lang']);
          }
        }

        if (isset($sec['links']) && is_array($sec['links']) && $sec['links']) {
          $buf .= '<ul class="doc-links">';
          foreach ($sec['links'] as $lnk) {
            $label = is_string($lnk['label'] ?? null) ? $lnk['label'] : '';
            $href  = is_string($lnk['href']  ?? null) ? $lnk['href']  : '#';
            if ($label !== '') {
              $buf .= '<li><a href="' . h(url($href)) . '">' . h($label) . '</a></li>';
            }
          }
          $buf .= '</ul>';
        }
      }
    }

    // Footer links (optional)
    if (isset($data['links']) && is_array($data['links']) && $data['links']) {
      $buf .= '<ul class="doc-links">';
      foreach ($data['links'] as $lnk) {
        $label = is_string($lnk['label'] ?? null) ? $lnk['label'] : '';
        $href  = is_string($lnk['href']  ?? null) ? $lnk['href']  : '#';
        if ($label !== '') {
          $buf .= '<li><a href="' . h(url($href)) . '">' . h($label) . '</a></li>';
        }
      }
      $buf .= '</ul>';
    }

    return $buf;
  }
}

function load_file($path) {
    if (file_exists($path)) {
        include $path;
    } else {
        pretty_error("Missing file: <code>$path</code>");
        exit;
    }
}
        
function pretty_error($message) {
    echo "<div style='
        background: #1e1e1e;
        color: #f88;
        padding: 1.5em;
        border: 2px solid #f00;
        font-family: monospace;
        margin: 2em;
        border-radius: 10px;
        '><strong>Error:</strong><br>$message</div>";
        
        $log_file = APP_ROOT . '/logs/site_errors.log'; // ?? This was missing!
        
        $log_line = "[" . date('Y-m-d H:i:s') . "] $message\n";
        
        if (file_exists($log_file) && filesize($log_file) > 1024 * 1024) { // 1MB
            rename($log_file, $log_file . '.' . time());
        }
        
        file_put_contents($log_file, $log_line, FILE_APPEND); // ?? Was missing semicolon
}
        
function throw_error($code = 500, $message = 'Unknown Error') {
    http_response_code($code);
        
    $friendly = [
        400 => 'Bad Request',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable'
    ];
        
    $title = $friendly[$code] ?? 'Error';
    pretty_error("[$code] $title: $message");
        
    // Optional: Log it
    $log_line = "[" . date('Y-m-d H:i:s') . "] [$code] $title — $message\n";
    @file_put_contents(APP_ROOT . '/logs/site_errors.log', $log_line, FILE_APPEND);
}     
         
function redirect_to($location) {
    header("Location: " . $location);
    exit;
}

function no_cacheHeader() {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
}