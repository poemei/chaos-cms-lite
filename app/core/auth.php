<?php
// /app/core/auth.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$__AUTH_ROOT = $__AUTH_ROOT ?? dirname(__DIR__, 1);   // => /app
$__PROJECT_ROOT = dirname($__AUTH_ROOT);              // => project root

// ---- paths ----
function auth_users_file(): string {
  global $__PROJECT_ROOT;
  return $__PROJECT_ROOT . '/data/users.json';
}

// ---- tiny JSON helpers (atomic write) ----
function auth_json_read(string $file, $fallback = []) {
  if (!is_file($file)) return $fallback;
  $raw = @file_get_contents($file);
  if ($raw === false || $raw === '') return $fallback;
  $j = json_decode($raw, true);
  return (json_last_error() === JSON_ERROR_NONE) ? $j : $fallback;
}
function auth_json_write(string $file, $value): bool {
  $dir = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $tmp = $dir . '/.tmp.' . bin2hex(random_bytes(6)) . '.json';
  $raw = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($raw === false) return false;
  if (@file_put_contents($tmp, $raw, LOCK_EX) === false) return false;
  return @rename($tmp, $file);
}

// ---- csrf ----
function auth_csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function auth_csrf_check(string $t): bool {
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

// ---- users api ----
function auth_load_users(): array {
  return auth_json_read(auth_users_file(), []);
}
function auth_find_user(string $username): ?array {
  $users = auth_load_users();
  foreach ($users as $u) {
    if (isset($u['user']) && strcasecmp($u['user'], $username) === 0) return $u;
  }
  return null;
}
function auth_save_users(array $users): bool {
  return auth_json_write(auth_users_file(), array_values($users));
}

// ---- register / login / logout ----
function auth_register(string $user, string $pass): array {
  $user = trim($user);
  if ($user === '' || $pass === '') return ['ok'=>false,'error'=>'missing_fields'];
  if (!preg_match('~^[a-z0-9._-]{3,40}$~i', $user)) return ['ok'=>false,'error'=>'bad_username'];

  $users = auth_load_users();
  foreach ($users as $u) {
    if (isset($u['user']) && strcasecmp($u['user'], $user) === 0) {
      return ['ok'=>false,'error'=>'exists'];
    }
  }
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  if ($hash === false) return ['ok'=>false,'error'=>'hash_failed'];

  $users[] = [
    'user' => $user,
    'pass_hash' => $hash,
    'roles' => ['admin'] // first users often admin; change later if needed
  ];
  if (!auth_save_users($users)) return ['ok'=>false,'error'=>'save_failed'];
  return ['ok'=>true];
}

function auth_login(string $user, string $pass): array {
  $u = auth_find_user($user);
  if (!$u) return ['ok'=>false,'error'=>'invalid'];
  if (!password_verify($pass, (string)($u['pass_hash'] ?? ''))) return ['ok'=>false,'error'=>'invalid'];
  session_regenerate_id(true);
  $_SESSION['auth'] = ['user'=>$u['user'], 'roles'=>$u['roles'] ?? []];
  return ['ok'=>true];
}

function auth_logout(): void {
  $_SESSION = [];
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
    session_destroy();
  }
}

// ---- guards / helpers ----
function auth_user(): ?array {
  return $_SESSION['auth'] ?? null;
}
function auth_check_role(string $role): bool {
  $a = auth_user(); if (!$a) return false;
  return in_array($role, $a['roles'] ?? [], true);
}
function auth_require_role(string $role): void {
  if (!auth_check_role($role)) {
    header('Location: /login'); exit;
  }
}
