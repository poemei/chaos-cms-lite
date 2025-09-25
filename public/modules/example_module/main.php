<?php
// /public/modules/example_module/main.php
// Module-level router: decides what function to call

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$parts = array_values(array_filter(explode('/', trim($path,'/'))));

// Drop "compare"
array_shift($parts);
$action = $parts[0] ?? 'home';

function home() {
    echo '<div class="container">';
    echo '<h2>Hello from Example Module</h2>';
    echo '<p>This is a working module placeholder. Edit <code>main.php</code> to add your content.</p>';
    echo '<p>The entirety of this theme and this module, uses Bootstrap.</p>';
    echo '<p>Check out the <a href="example_module/routing">Routing Example</a> to see how modules get routed.</p>';
    echo '</div>';
}

function module_routing() {
    echo '<h3>Routing in modules</h3>';
    echo 'Using PHP to parse the path (/your_module/slug) and a switch.<br>';
    echo '<pre>Router gets the input from a link in the browser or a direct URL and sets the $action variable.</pre><br><pre>Switch($action) routes to the correct function within the module to provide content</pre>';
}

switch($action) {
	case 'home':
    case '':
    home();
    break;
    
    case 'routing':
    module_routing();
    break;
    
    case 'doc':
    require __DIR__ . '/doc.json';
    break;
    
    default:
    http_response_code(404);
    echo "<h2>Not found</h2>";
    break;
}