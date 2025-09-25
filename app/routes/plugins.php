<?php
// /app/routes/plugins.php
if (!isset($parts[0]) || $parts[0] !== 'plugins') return;

$slug = $parts[1] ?? '';
$rest = implode('/', array_slice($parts, 2));

if ($slug === 'example') {
    if ($rest === '' || $rest === 'index') {
        echo '<div class="container">';
        echo '<h2>Example Plugin</h2>';
        echo '<p>This content is served by the Example plugin.</p>';
        echo '<link rel="stylesheet" href="/plugins/example/assets/example.css">';
        echo '<div class="example-plugin-box">Styled by /assets/example.css</div>';
        //echo '<p>Go to <a href="/plugins/example/ping">Ping</a> or <a href="/admin/plugins/example">Admin Panel</a>.</p>';
        echo '</div>';
        $__ROUTE_CLAIMED = true;
        return;
    }
    if ($rest === 'ping') {
        header('Content-Type: text/plain');
        echo 'OK';
        $__ROUTE_CLAIMED = true;
        return;
    }
    if ($rest === 'assets/hello.css') {
        header('Content-Type: text/css');
        readfile(__DIR__ . '/../plugins/example/assets/example.css');
        $__ROUTE_CLAIMED = true;
        return;
    }
    if ($rest === 'admin') {
        require __DIR__ . '/../plugins/example/admin.php';
        $__ROUTE_CLAIMED = true;
        return;
    }
}
