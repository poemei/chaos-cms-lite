<?php
// Minimal JSON "DB" for ChaosCMS-Lite
// - Collections live in:  /data/db/<collection>/
// - Records are files:    /data/db/<collection>/<id>.json
// - Atomic writes via temp+rename
// - Auto-increment integer IDs via /data/db/<collection>/.seq

declare(strict_types=1);

function jsondb_col_dir(string $root, string $collection): string {
  $safe = preg_replace('~[^a-z0-9_-]~i', '', $collection) ?: 'default';
  return rtrim($root, '/')."/data/db/$safe";
}

function jsondb_ensure_dir(string $dir): void {
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
}

function jsondb_read_file(string $file, $fallback=null) {
  if (!is_file($file)) return $fallback;
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') return $fallback;
  $j = json_decode($raw, true);
  return (json_last_error() === JSON_ERROR_NONE) ? $j : $fallback;
}

function jsondb_write_atomic(string $file, $value): bool {
  $dir = dirname($file);
  jsondb_ensure_dir($dir);
  $tmp = $dir.'/.tmp.'.bin2hex(random_bytes(6)).'.json';
  $raw = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($raw === false) return false;
  if (@file_put_contents($tmp, $raw, LOCK_EX) === false) return false;
  return @rename($tmp, $file);
}

// ---- ID generation (auto-increment int) ----
function jsondb_next_id(string $root, string $collection): int {
  $dir = jsondb_col_dir($root, $collection);
  jsondb_ensure_dir($dir);
  $seq = $dir.'/.seq';
  $h = @fopen($seq, 'c+'); if (!$h) throw new RuntimeException('seq lock failed');
  if (!flock($h, LOCK_EX)) { fclose($h); throw new RuntimeException('seq flock failed'); }
  $n = (int)trim((string)stream_get_contents($h));
  $n = $n > 0 ? $n+1 : 1;
  ftruncate($h, 0);
  rewind($h);
  fwrite($h, (string)$n);
  fflush($h);
  flock($h, LOCK_UN);
  fclose($h);
  return $n;
}

// ---- CRUD ----
function jsondb_get(string $root, string $collection, string $id) {
  $dir = jsondb_col_dir($root, $collection);
  return jsondb_read_file("$dir/$id.json", null);
}

function jsondb_put(string $root, string $collection, string $id, array $record): bool {
  $dir = jsondb_col_dir($root, $collection);
  $file = "$dir/$id.json";
  $record['_id'] = $id;
  $record['_updated_at'] = date('c');
  if (!isset($record['_created_at'])) $record['_created_at'] = $record['_updated_at'];
  return jsondb_write_atomic($file, $record);
}

function jsondb_create(string $root, string $collection, array $record): string {
  $id = (string)jsondb_next_id($root, $collection);   // numeric IDs ("1","2",…)
  if (!jsondb_put($root, $collection, $id, $record)) throw new RuntimeException('create failed');
  return $id;
}

function jsondb_delete(string $root, string $collection, string $id): bool {
  $dir = jsondb_col_dir($root, $collection);
  $file = "$dir/$id.json";
  return is_file($file) ? @unlink($file) : false;
}

function jsondb_all(string $root, string $collection): array {
  $dir = jsondb_col_dir($root, $collection);
  if (!is_dir($dir)) return [];
  $out = [];
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..' || !str_ends_with($f, '.json') || $f[0]==='.') continue;
    $rec = jsondb_read_file("$dir/$f", null);
    if (is_array($rec)) $out[] = $rec;
  }
  return $out;
}

// Simple filter query: $pred is callable($rec): bool
function jsondb_query(string $root, string $collection, callable $pred): array {
  $rows = jsondb_all($root, $collection);
  $out = [];
  foreach ($rows as $r) { if ($pred($r)) $out[] = $r; }
  return $out;
}

// Convenience: partial update (merge)
function jsondb_update(string $root, string $collection, string $id, array $patch): bool {
  $cur = jsondb_get($root, $collection, $id);
  if (!is_array($cur)) return false;
  $next = array_merge($cur, $patch);
  return jsondb_put($root, $collection, $id, $next);
}
