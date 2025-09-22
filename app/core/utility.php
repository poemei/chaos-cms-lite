<?php
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function url(string $path=''): string { return ($path && $path[0]=='/') ? $path : '/'.$path; }

/**
 * Render a page JSON shape:
 * {
 *   "title": "Title",
 *   "html": "<p>optional single block</p>",
 *   "sections": [{"html":"..."}, {"html":"..."}],
 *   "links": [{"label":"Codex","href":"/page/codex"}, {"label":"External","href":"https://..."}]
 * }
 */
function render_article(array $p): string {
  $title    = (string)($p['title'] ?? '');
  $html     = (string)($p['html']  ?? '');
  $sections = is_array($p['sections'] ?? null) ? $p['sections'] : [];
  $links    = is_array($p['links'] ?? null) ? $p['links'] : [];

  // --- normalize code/codes at top level ---
  $topCodes = [];
  if (isset($p['code']) && is_string($p['code']) && $p['code'] !== '') {
    $topCodes[] = $p['code'];
  }
  if (isset($p['codes'])) {
    if (is_string($p['codes']) && $p['codes'] !== '') {
      $topCodes[] = $p['codes'];
    } elseif (is_array($p['codes'])) {
      foreach ($p['codes'] as $c) {
        if (is_string($c) && $c !== '') $topCodes[] = $c;
      }
    }
  }

  $out  = '<article>';
  if ($title !== '') $out .= '<h2>'.h($title).'</h2>';

  // raw HTML (intentionally unescaped; you control JSON)
  if ($html !== '') $out .= $html;

  // render top-level code blocks (escaped)
  foreach ($topCodes as $c) {
    $out .= '<pre>'.h($c).'</pre>';
  }

  // sections: html + code/codes (+ optional lang class)
  foreach ($sections as $sec) {
    $secHtml = (string)($sec['html'] ?? '');
    if ($secHtml !== '') $out .= $secHtml;

    $lang = '';
    if (isset($sec['lang']) && is_string($sec['lang'])) {
      $lang = preg_replace('~[^a-z0-9_+-]~i','', $sec['lang']) ?: '';
    }
    $cls = $lang ? ' class="language-'.h($lang).'"' : '';

    // normalize section codes
    $secCodes = [];
    if (isset($sec['code']) && is_string($sec['code']) && $sec['code'] !== '') {
      $secCodes[] = $sec['code'];
    }
    if (isset($sec['codes'])) {
      if (is_string($sec['codes']) && $sec['codes'] !== '') {
        $secCodes[] = $sec['codes'];
      } elseif (is_array($sec['codes'])) {
        foreach ($sec['codes'] as $c) {
          if (is_string($c) && $c !== '') $secCodes[] = $c;
        }
      }
    }
    foreach ($secCodes as $c) {
      $out .= '<pre><code'.$cls.'>'.h($c).'</code></pre>';
    }
  }

  // optional links list
  if ($links) {
    $out .= '<div class="page-links" style="margin-top:20px"><h3>Explore</h3><ul>';
    foreach ($links as $ln) {
      $label = h((string)($ln['label'] ?? ''));
      $href  = (string)($ln['href']  ?? '');
      if ($label !== '' && $href !== '') {
        $safeHref = h($href);
        $target   = (preg_match('#^https?://#', $href)) ? ' target="_blank" rel="noopener"' : '';
        $out .= '<li><a href="'.$safeHref.'"'.$target.'>'.$label.'</a></li>';
      }
    }
    $out .= '</ul></div>';
  }

  $out .= '</article>';
  return $out;
}

function redirect_to($url) {
	header('Location: '. $url);
	exit;
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
        
    exit;
}

  // Escape for <pre><code> blocks: keep quotes as-is, escape only &, <, >
function codeesc(string $s): string {
  return str_replace(
    ['&',   '<',   '>'],
    ['&amp;','&lt;','&gt;'],
    $s
  );
}
//$out .= '<pre><code>'.codeesc($c).'</code></pre>';