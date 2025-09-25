<?php
// Add a ?Ping? item into the main nav
add_filter('nav.render', function(array $nav){
  $nav[] = ['label' => 'Ping', 'href' => '/plugins/example/ping'];
  return $nav; // important
}, 10);

// Claim the /plugins/example/ping route
add_filter('router.claim', function($claimed, $path){
  if ($claimed) return true;
  if ($path === '/plugins/example/ping') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK";
    return true;
  }
  return false;
}, 10);
