<?php
/**
 * /app/core/auth.php
 * JSON-backed auth for chaoscms-lite (no DB).
 *
 * Public API:
 *   - auth_is_logged_in(): bool
 *   - auth_user(): array|null
 *   - auth_login(string $userOrEmail, string $pass): array [ok(bool), msg(string)]
 *   - auth_logout(): void
 *   - auth_register(string $username, string $pass, string $email=''): array [ok(bool), msg(string)]
 *   - auth_require_login(string $redirect='/login'): void (redirects if not logged in)
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$__AUTH_APP  = dirname(__DIR__);     // /app
$__AUTH_ROOT = dirname($__AUTH_APP); // project root

require_once $__AUTH_APP . '/core/utility.php'; // json_read/json_write/h/redirect_to()

/* ---------------- Paths & storage ---------------- */

function auth_users_path(): string {
  $APP  = dirname(__DIR__);       // /app
  $ROOT = dirname($APP);          // project root
  $dir  = $ROOT . '/data';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  return $dir . '/users.json';
}

/** @return array<int, array> */
function auth_load_all(): array {
  $path = auth_users_path();
  $data = json_read($path, null);
  return (is_array($data) ? $data : []);
}

function auth_save_all(array $users): bool {
  $path = auth_users_path();
  return json_write($path, array_values($users));
}

/* ---------------- Utilities ---------------- */

function auth_now(): string {
  return gmdate('c');
}

function auth_normalize_username(string $v): string {
  return trim($v);
}

function auth_normalize_email(string $v): string {
  return trim(strtolower($v));
}

function auth_find_user(string $userOrEmail): ?array {
  $u = auth_normalize_username($userOrEmail);
  $e = auth_normalize_email($userOrEmail);

  $all = auth_load_all();
  foreach ($all as $row) {
    $nameMatch  = isset($row['username']) && strcasecmp($row['username'], $u) === 0;
    $emailMatch = isset($row['email'])    && $e !== '' && strtolower((string)$row['email']) === $e;
    if ($nameMatch || $emailMatch) return $row;
  }
  return null;
}

function auth_hash(string $pass): string {
  return password_hash($pass, PASSWORD_DEFAULT);
}

function auth_verify(string $pass, string $hash): bool {
  return password_verify($pass, $hash);
}

/* ---------------- Session helpers ---------------- */

function auth_is_logged_in(): bool {
  return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

/** @return array|null */
function auth_user(): ?array {
  return auth_is_logged_in() ? $_SESSION['user'] : null;
}

function auth_set_session(array $user): void {
  $safe = $user; // never store hash in session
  unset($safe['pass_hash']);
  $_SESSION['user'] = $safe;
}

/* ---------------- Public actions ---------------- */

/**
 * @return array{0:bool,1:string} [ok, msg]
 */
function auth_login(string $userOrEmail, string $pass): array {
  $userOrEmail = trim($userOrEmail);
  $pass        = (string)$pass;

  if ($userOrEmail === '' || $pass === '') {
    return [false, 'Missing username/email or password.'];
  }

  $row = auth_find_user($userOrEmail);
  if (!$row) {
    return [false, 'Invalid credentials.'];
  }

  $hash = (string)($row['pass_hash'] ?? '');
  if ($hash === '' || !auth_verify($pass, $hash)) {
    return [false, 'Invalid credentials.'];
  }

  auth_set_session($row);
  return [true, 'OK'];
}

/**
 * @return array{0:bool,1:string} [ok, msg]
 */
function auth_register(string $username, string $pass, string $email = ''): array {
  $username = auth_normalize_username($username);
  $email    = auth_normalize_email($email);
  $pass     = (string)$pass;

  if ($username === '' || $pass === '') {
    return [false, 'Username and password are required.'];
  }

  $all = auth_load_all();

  // Uniqueness checks
  foreach ($all as $row) {
    if (isset($row['username']) && strcasecmp($row['username'], $username) === 0) {
      return [false, 'Username already exists.'];
    }
    if ($email !== '' && isset($row['email']) && strtolower((string)$row['email']) === $email) {
      return [false, 'Email already in use.'];
    }
  }

  // First-user-is-admin guard
  $isFirstUser = (count($all) === 0);
  $role        = $isFirstUser ? 'admin' : 'user';

  // Next ID
  $nextId = 1;
  foreach ($all as $row) {
    $rid = (int)($row['id'] ?? 0);
    if ($rid >= $nextId) $nextId = $rid + 1;
  }

  $new = [
    'id'        => $nextId,
    'username'  => $username,
    'email'     => $email,
    'role'      => $role,
    'pass_hash' => auth_hash($pass),
    'created'   => auth_now(),
  ];

  $all[] = $new;
  if (!auth_save_all($all)) {
    return [false, 'Failed to write users.json'];
  }

  return [true, $isFirstUser ? 'Registered (first admin)' : 'Registered'];
}

function auth_logout(): void {
  unset($_SESSION['user']);
}

/**
 * Redirects to $redirect if not logged in.
 */
function auth_require_login(string $redirect = '/login'): void {
  if (!auth_is_logged_in()) {
    redirect_to($redirect . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin'));
  }
}
// Back-compat shims (optional)
if (!function_exists('is_logged_in')) {
  function is_logged_in(): bool { return auth_is_logged_in(); }
}
if (!function_exists('current_user')) {
  function current_user(): ?array { return auth_user(); }
}