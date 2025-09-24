<?php
// app/routes/auth.php
// Requires utility.php and auth.php already loaded by bootstrap/router.
// Usage from router: if (auth_routes($ROOT, $theme)) return;

if (!function_exists('auth_routes')) {
  function auth_routes(string $ROOT, string $theme): bool {
    // parse URL bits (the router likely already has these; re-derive to be self-contained)
    $path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $parts = array_values(array_filter(explode('/', trim($path,'/'))));
    $seg1  = $parts[0] ?? '';

    // ---- POST handlers (no output yet!)
    if ($seg1 === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $tok = $_POST['csrf'] ?? '';
      if (!csrf_check($tok)) { http_response_code(400); echo 'Bad CSRF'; return true; }
      [$ok,$msg] = auth_login($_POST['login'] ?? '', $_POST['password'] ?? '');
      if ($ok) { redirect_to('/admin'); }
      $_SESSION['flash_error'] = $msg;
      redirect_to('/login');
    }

    if ($seg1 === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $tok = $_POST['csrf'] ?? '';
      if (!csrf_check($tok)) { http_response_code(400); echo 'Bad CSRF'; return true; }
      [$ok,$msg] = auth_register($_POST['username'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
      $_SESSION['flash_'.($ok?'ok':'error')] = $msg;
      redirect_to($ok?'/login':'/register');
    }

    if ($seg1 === 'logout') { auth_logout(); redirect_to('/'); }

    // ---- Views (GET) – render inside theme shell, then exit.
    if ($seg1 === 'login' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
      $site  = json_read($ROOT.'/data/site.json', ['name'=>'chaoscms-lite','nav'=>[]]);
      $theme = $site['theme'] ?? $theme;
      require $ROOT."/public/themes/{$theme}/includes/header.php";

      $err = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
      if ($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>';

      // Prefer JSON page if present
      $page = json_read($ROOT.'/data/pages/login.json', null);
      if (is_array($page) && isset($page['html'])) {
        $html = str_replace('{{csrf}}', htmlspecialchars(csrf_token()), $page['html']);
        echo $html;
      } else {
        // Fallback form
        ?>
        <h2>Login</h2>
        <form method="post" action="/login" class="card" style="max-width:420px;padding:16px">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">Email or Username</label>
            <input class="form-control" name="login" required></div>
          <div class="mb-2"><label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required></div>
          <button class="btn btn-primary">Sign in</button>
        </form>
        <?php
      }
      require $ROOT."/public/themes/{$theme}/includes/footer.php";
      return true;
    }

    if ($seg1 === 'register' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
      $site  = json_read($ROOT.'/data/site.json', ['name'=>'chaoscms-lite','nav'=>[]]);
      $theme = $site['theme'] ?? $theme;
      require $ROOT."/public/themes/{$theme}/includes/header.php";

      $ok = $_SESSION['flash_ok'] ?? null; unset($_SESSION['flash_ok']);
      $er = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
      if ($ok) echo '<div class="alert alert-success">'.htmlspecialchars($ok).'</div>';
      if ($er) echo '<div class="alert alert-danger">'.htmlspecialchars($er).'</div>';

      $page = json_read($ROOT.'/data/pages/register.json', null);
      if (is_array($page) && isset($page['html'])) {
        $html = str_replace('{{csrf}}', htmlspecialchars(csrf_token()), $page['html']);
        echo $html;
      } else {
        ?>
        <h2>Create account</h2>
        <form method="post" action="/register" class="card" style="max-width:520px;padding:16px">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">Username</label>
            <input class="form-control" name="username" required></div>
          <div class="mb-2"><label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required></div>
          <div class="mb-2"><label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required></div>
          <button class="btn btn-success">Create account</button>
        </form>
        <?php
      }
      require $ROOT."/public/themes/{$theme}/includes/footer.php";
      return true;
    }

    return false; // not handled
  }
}
