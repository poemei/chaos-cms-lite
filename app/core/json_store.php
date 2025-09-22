<?php
function json_read(string $path, $fallback = []) {
  if (!is_file($path)) return $fallback;
  $raw = file_get_contents($path);
  if ($raw === false || $raw === '') return $fallback;
  try { $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
  catch(Throwable) { return $fallback; }
  return $data;
}
function json_write_atomic(string $path, $data): bool {
  $dir = dirname($path);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $json = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;
  $tmp = tempnam($dir, 'json_'); if (!$tmp) return false;
  if (file_put_contents($tmp, $json, LOCK_EX) === false) { @unlink($tmp); return false; }
  return @rename($tmp, $path);
}
