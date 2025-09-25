<?php
// super-light hooks/filters
if (!isset($GLOBALS['chaos_hooks']))   $GLOBALS['chaos_hooks'] = [];
if (!isset($GLOBALS['chaos_filters'])) $GLOBALS['chaos_filters'] = [];

function add_action(string $event, callable $fn, int $prio = 10): void {
  $GLOBALS['chaos_hooks'][$event][$prio][] = $fn;
}
function do_action(string $event, ...$args): void {
  if (empty($GLOBALS['chaos_hooks'][$event])) return;
  ksort($GLOBALS['chaos_hooks'][$event]);
  foreach ($GLOBALS['chaos_hooks'][$event] as $group) {
    foreach ($group as $fn) { $fn(...$args); }
  }
}

function add_filter(string $event, callable $fn, int $prio = 10): void {
  $GLOBALS['chaos_filters'][$event][$prio][] = $fn;
}
function apply_filters(string $event, $value, ...$args) {
  if (empty($GLOBALS['chaos_filters'][$event])) return $value;
  ksort($GLOBALS['chaos_filters'][$event]);
  foreach ($GLOBALS['chaos_filters'][$event] as $group) {
    foreach ($group as $fn) { $value = $fn($value, ...$args); }
  }
  return $value;
}
