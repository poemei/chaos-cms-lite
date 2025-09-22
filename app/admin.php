<?php
/** admin.php — ChaosCMS Lite, single-file admin (JSON only, no mailer, no DB)
 * - Dashboard / Posts / Pages (nested) / Users / Settings
 * - Self-contained helpers: h(), json_read/json_write, csrf, slugify, recursive page scan
 * - Works standalone (prints full HTML) or inside your themed shell (router)
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

session_start();
$ROOT = dirname(__DIR__);     // project root (adjust if placed elsewhere)
$self = '/admin';             // public route to this file (adjust to your routing)

// ---------- tiny “theme-aware” wrapper ----------
$standalone = !defined('__ADMIN_SHELL_OPEN__');
if ($standalone) {
  echo '<!doctype html><meta charset="utf-8"><title>Admin</title>
  <style>body{font:14px system-ui,Arial,sans-serif;margin:20px} .wrap{max-width:1000px;margin:0 auto}
  nav a{margin-right:12px} .card{border:1px solid #ddd;border-radius:12px;padding:12px;margin:12px 0}
  table{border-collapse:collapse;width:100%} th,td{border-bottom:1px solid #eee;padding:8px;text-align:left}
  input,select,textarea{width:100%;max-width:720px;padding:8px;border:1px solid #ccc;border-radius:8px}
  .row{display:flex;gap:12px;flex-wrap:wrap} .btn{padding:6px 10px;border:1px solid #888;border-radius:8px;background:#f7f7f7}
  .btn-sm{padding:4px 8px;font-size:.9rem} .good{color:#0a7d00} .bad{color:#b00020}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
  code,pre{background:#f6f8fa;border-radius:6px;padding:2px 6px} pre{padding:10px;overflow:auto}
  details summary{cursor:pointer} .nowrap{white-space:nowrap}
  </style><div class="wrap">';
  define('__ADMIN_SHELL_OPEN__',1);
}

// ---------- minimal auth guard (expects login to have set $_SESSION['user']) ----------
function auth_user(): array { return (array)($_SESSION['user'] ?? []); }
function is_admin(): bool {
  $u = auth_user();
  $roles = (array)($u['roles'] ?? []);
  return in_array('admin', $roles, true);
}
if (!is_admin()) {
  http_response_code(403);
  echo '<div class="card"><h3>Forbidden</h3><p>You need admin access.</p><p><a class="btn" href="/login">Go to login</a></p></div>';
  if ($standalone) echo '</div>';
  exit;
}

// ---------- helpers ----------
//function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function slugify(string $s): string {
  $s = strtolower(trim($s));
  $s = preg_replace('~[^a-z0-9-]+~', '-', $s);
  return trim($s, '-') ?: bin2hex(random_bytes(3));
}
//function redirect_to(string $url): never { header('Location: '.$url); exit; }

/*
function json_read(string $file, $default = null) {
  if (!is_file($file)) return $default;
  $j = file_get_contents($file);
  $d = json_decode($j, true);
  return (json_last_error() === JSON_ERROR_NONE) ? $d : $default;
}
function json_write(string $file, $data): bool {
  $dir = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $j = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  if ($j === false) return false;
  return file_put_contents($file, $j) !== false;
}
*/
// CSRF (session-based)
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf(): string { return (string)($_SESSION['csrf'] ?? ''); }
function csrf_ok(string $token): bool { return hash_equals(csrf(), $token); }

// Paths
function posts_dir(string $root): string { return $root.'/data/posts'; }
function post_path(string $root, string $slug): string { return posts_dir($root).'/'.$slug.'.json'; }

function pages_root(string $root): string { return $root.'/data/pages'; }
function page_fullpath(string $root, string $slug): string {
  $slug = trim($slug,'/');
  if ($slug==='' || $slug==='home') return $root.'/data/pages/home.json';
  return pages_root($root).'/'.$slug.'.json';
}
function page_indexpath(string $root, string $slug): string {
  $slug = trim($slug,'/');
  if ($slug==='' || $slug==='home') return $root.'/data/pages/index.json';
  return pages_root($root).'/'.$slug.'/index.json';
}
function pages_list_all(string $root): array {
  $base = pages_root($root);
  $out = [];
  if (!is_dir($base)) return $out;
  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );
  foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if ($f->isFile() && strtolower($f->getExtension()) === 'json') {
      $rel  = str_replace($base.'/', '', $f->getPathname());
      $slug = preg_replace('~\.json$~', '', $rel);
      $slug = preg_replace('~/index$~', '', $slug); // normalize folder/index.json ? folder
      if ($slug === 'home') $slug = '';
      $out[$slug] = $f->getPathname();
    }
  }
  ksort($out, SORT_NATURAL|SORT_FLAG_CASE);
  return $out;
}

function users_file(string $root): string { return $root.'/data/users.json'; }
function users_load(string $root): array { return json_read(users_file($root), []); }
function users_save(string $root, array $rows): bool { return json_write(users_file($root), array_values($rows)); }
function user_find(array $rows, string $name): ?int {
  foreach ($rows as $i => $r) if (strcasecmp((string)($r['user'] ?? ''), $name)===0) return $i;
  return null;
}
function themes_list(string $root): array {
  $dir = $root.'/public/themes';
  $out = [];
  if (!is_dir($dir)) return $out;
  foreach (scandir($dir) ?: [] as $e) if ($e!=='.' && $e!=='..' && is_dir($dir.'/'.$e)) $out[] = $e;
  sort($out, SORT_NATURAL|SORT_FLAG_CASE);
  return $out;
}

// ---------- state ----------
$act = (string)($_GET['a'] ?? 'dashboard');
$ok  = (string)($_GET['ok'] ?? '');
$err = (string)($_GET['err'] ?? '');

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok((string)($_POST['csrf'] ?? ''))) redirect_to($self.'?err=csrf');

  // POSTS: save
  if ($act === 'post_save') {
    $title   = trim((string)($_POST['title'] ?? ''));
    $slugIn  = trim((string)($_POST['slug'] ?? ''));
    $date    = trim((string)($_POST['date'] ?? ''));
    $excerpt = (string)($_POST['excerpt'] ?? '');
    $html    = (string)($_POST['html'] ?? '');
    if ($title==='') redirect_to($self.'?a=posts&err=missing_title');
    $slug = $slugIn!=='' ? $slugIn : slugify($title);
    $okw = json_write(post_path($ROOT,$slug), [
      'title'=>$title, 'date'=>$date ?: date('Y-m-d'),
      'excerpt'=>$excerpt, 'html'=>$html
    ]);
    redirect_to($self.'?a=posts'.($okw?'&ok=saved':'&err=save_failed'));
  }
  // POSTS: delete
  if ($act === 'post_delete') {
    $slug = trim((string)($_POST['slug'] ?? ''));
    $okd = ($slug!=='') && is_file(post_path($ROOT,$slug)) ? (bool)@unlink(post_path($ROOT,$slug)) : false;
    redirect_to($self.'?a=posts'.($okd?'&ok=deleted':'&err=delete_failed'));
  }

  // PAGES: save
  if ($act === 'page_save') {
    $slug = trim((string)($_POST['slug'] ?? '')); // supports codex/core or blank=home
    $title= (string)($_POST['title'] ?? '');
    $html = (string)($_POST['html']  ?? '');
    $idx  = ((string)($_POST['as_index'] ?? '0')==='1');
    if ($slug==='' && $title==='') redirect_to($self.'?a=pages&err=missing');

    if ($slug==='') $slug='home';
    $file = $idx ? page_indexpath($ROOT,$slug) : page_fullpath($ROOT,$slug);
    $okp  = json_write($file, ['title'=>$title, 'html'=>$html]);
    redirect_to($self.'?a=pages'.($okp?'&ok=saved':'&err=save_failed'));
  }
  // PAGES: delete
  if ($act === 'page_delete') {
    $slug = trim((string)($_POST['slug'] ?? ''));
    if ($slug==='') $slug='home';
    $targets = [page_fullpath($ROOT,$slug), page_indexpath($ROOT,$slug)];
    $okx = false; foreach ($targets as $f) { if (is_file($f)) { $okx = @unlink($f); if ($okx) break; } }
    redirect_to($self.'?a=pages'.($okx?'&ok=deleted':'&err=delete_failed'));
  }

  // USERS: create/update/delete
  if ($act === 'user_create') {
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');
    $role = (string)($_POST['role'] ?? 'admin');
    if ($user===''||$pass==='') redirect_to($self.'?a=users&err=missing');
    $rows = users_load($ROOT);
    if (user_find($rows,$user)!==null) redirect_to($self.'?a=users&err=exists');
    $hash = password_hash($pass,PASSWORD_DEFAULT); if ($hash===false) redirect_to($self.'?a=users&err=hash');
    $rows[] = ['user'=>$user,'pass_hash'=>$hash,'roles'=>[$role]];
    $okc = users_save($ROOT,$rows);
    redirect_to($self.'?a=users'.($okc?'&ok=created':'&err=save'));
  }
  if ($act === 'user_pass') {
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');
    if ($user===''||$pass==='') redirect_to($self.'?a=users&err=missing');
    $rows = users_load($ROOT);
    $i = user_find($rows,$user);
    if ($i===null) redirect_to($self.'?a=users&err=notfound');
    $hash = password_hash($pass,PASSWORD_DEFAULT); if ($hash===false) redirect_to($self.'?a=users&err=hash');
    $rows[$i]['pass_hash'] = $hash;
    $okp = users_save($ROOT,$rows);
    redirect_to($self.'?a=users'.($okp?'&ok=pass':'&err=save'));
  }
  if ($act === 'user_delete') {
    $user = trim((string)($_POST['user'] ?? ''));
    $me   = (string)((auth_user()['user'] ?? ''));
    if (strcasecmp($user,$me)===0) redirect_to($self.'?a=users&err=self');
    $rows = users_load($ROOT);
    $i = user_find($rows,$user);
    $okd = false;
    if ($i!==null) { array_splice($rows,$i,1); $okd = users_save($ROOT,$rows); }
    redirect_to($self.'?a=users'.($okd?'&ok=udeleted':'&err=udelete'));
  }

  // SETTINGS: site.json (name + theme)
  if ($act === 'settings_save') {
    $siteFile = $ROOT.'/data/site.json';
    $site = json_read($siteFile, []);
    $site['name']  = trim((string)($_POST['name'] ?? ($site['name'] ?? 'Chaos CMS')));
    $themeIn = (string)($_POST['theme'] ?? ($site['theme'] ?? 'chaos'));
    $site['theme'] = preg_replace('~[^a-z0-9_-]~i','',$themeIn) ?: 'chaos';
    if (!isset($site['nav'])) $site['nav']=[];
    $oks = json_write($siteFile,$site);
    redirect_to($self.'?a=settings'.($oks?'&ok=saved':'&err=save_failed'));
  }

  redirect_to($self.'?err=bad_action');
}

// ---------- UI ----------
echo '<header class="row" style="align-items:center;justify-content:space-between;margin:8px 0">
  <h2 style="margin:0">Admin</h2>
  <nav>
    <a href="'.$self.'">Dashboard</a>
    <a href="'.$self.'?a=posts">Posts</a>
    <a href="'.$self.'?a=pages">Pages</a>
    <a href="'.$self.'?a=users">Users</a>
    <a href="'.$self.'?a=settings">Settings</a>
    <a href="/logout">Logout</a>
  </nav>
</header>';

if ($ok)  echo '<div class="good">'.h($ok).'</div>';
if ($err) echo '<div class="bad">Error: '.h($err).'</div>';

// DASHBOARD
if ($act === 'dashboard') {
  echo '<div class="card"><h3 style="margin:0 0 6px 0">Welcome</h3>
        <p>Manage your content via Posts & Pages. Users and basic site settings are here too.</p></div>';
}

// POSTS (list)
if ($act === 'posts') {
  $dir = posts_dir($ROOT);
  $posts = [];
  if (is_dir($dir)) {
    foreach (scandir($dir) ?: [] as $f) {
      if ($f==='.'||$f==='..'||!str_ends_with($f,'.json')) continue;
      $slug = basename($f,'.json');
      $p = json_read($dir.'/'.$f, []);
      $posts[] = ['slug'=>$slug,'title'=>$p['title'] ?? $slug,'date'=>$p['date'] ?? ''];
    }
  }
  usort($posts, fn($a,$b)=>strcmp($b['date'] ?? '', $a['date'] ?? ''));

  echo '<div class="card">';
  echo '<div class="row" style="justify-content:space-between;align-items:center">
          <h3 style="margin:0">Posts</h3>
          <a class="btn" href="'.$self.'?a=post_edit">New Post</a>
        </div>';
  if (!$posts) echo '<p>No posts yet.</p>';
  else {
    echo '<table><tr><th>Title</th><th class="nowrap">Date</th><th class="nowrap">Actions</th></tr>';
    foreach ($posts as $p): ?>
      <tr>
        <td><?=h($p['title'])?></td>
        <td class="nowrap"><?=h((string)$p['date'])?></td>
        <td class="nowrap">
          <a class="btn btn-sm" href="<?=$self?>?a=post_edit&slug=<?=h($p['slug'])?>">Edit</a>
          <form style="display:inline" method="post" action="<?=$self?>?a=post_delete" onsubmit="return confirm('Delete this post?')">
            <input type="hidden" name="csrf" value="<?=h(csrf())?>">
            <input type="hidden" name="slug" value="<?=h($p['slug'])?>">
            <button class="btn btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach;
    echo '</table>';
  }
  echo '</div>';
}

// POST editor
if ($act === 'post_edit') {
  $slug = trim((string)($_GET['slug'] ?? ''));
  $post = ['title'=>'','slug'=>'','date'=>date('Y-m-d'),'excerpt'=>'','html'=>''];
  $ro = '';
  if ($slug!=='') { $post = json_read(post_path($ROOT,$slug), $post); $post['slug']=$slug; $ro='readonly'; }
  echo '<div class="card"><h3 style="margin:0 0 6px 0">'.($slug===''?'New':'Edit').' Post</h3>
    <form method="post" action="'.$self.'?a=post_save">
      <input type="hidden" name="csrf" value="'.h(csrf()).'">
      <label>Title<br><input name="title" value="'.h((string)$post['title']).'" required></label><br>
      <label>Slug<br><input name="slug" value="'.h((string)$post['slug']).'" '.$ro.'></label><br>
      <label>Date<br><input name="date" value="'.h((string)$post['date']).'"></label><br>
      <label>Excerpt<br><textarea name="excerpt" rows="3">'.h((string)$post['excerpt']).'</textarea></label><br>
      <label>HTML<br><textarea name="html" rows="12">'.h((string)$post['html']).'</textarea></label><br>
      <button class="btn" type="submit">Save</button>
    </form></div>';
}

// PAGES (list)
if ($act === 'pages') {
  $pages = pages_list_all($ROOT);
  echo '<div class="card">';
  echo '<div class="row" style="justify-content:space-between;align-items:center">
          <h3 style="margin:0">Pages</h3>
          <a class="btn" href="'.$self.'?a=page_edit">New Page</a>
        </div>';
  if (!$pages) echo '<p>No pages yet.</p>';
  else {
    echo '<table><tr><th>Slug</th><th>URL</th><th class="nowrap">Actions</th></tr>';
    foreach ($pages as $slug => $abs): $pretty = ($slug===''?'/':'/'.ltrim($slug,'/')); ?>
      <tr>
        <td><?=h($slug===''? '(home)' : $slug)?></td>
        <td><a href="<?=h($pretty)?>" target="_blank"><?=h($pretty)?></a></td>
        <td class="nowrap">
          <a class="btn btn-sm" href="<?=$self?>?a=page_edit&slug=<?=h($slug)?>">Edit</a>
          <form style="display:inline" method="post" action="<?=$self?>?a=page_delete"
                onsubmit="return confirm('Delete page <?=h($slug===''? 'home' : $slug)?>?')">
            <input type="hidden" name="csrf" value="<?=h(csrf())?>">
            <input type="hidden" name="slug" value="<?=h($slug)?>">
            <button class="btn btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach;
    echo '</table>';
  }
  echo '</div>';
}

// PAGE editor
if ($act === 'page_edit') {
  $slug = trim((string)($_GET['slug'] ?? ''));
  $data = ['title'=>'','html'=>''];
  $fileA = page_fullpath($ROOT,$slug);
  $fileB = page_indexpath($ROOT,$slug);
  $used  = '';
  if ($slug!=='') {
    if (is_file($fileA)) { $data=json_read($fileA,$data); $used=$fileA; }
    elseif (is_file($fileB)) { $data=json_read($fileB,$data); $used=$fileB; }
  } else {
    $home = $ROOT.'/data/pages/home.json';
    if (is_file($home)) { $data=json_read($home,$data); $used=$home; }
  }
  $isIndex = ($used === $fileB);

  echo '<div class="card"><h3 style="margin:0 0 6px 0">'.($slug===''?'New':'Edit').' Page</h3>
    <form method="post" action="'.$self.'?a=page_save">
      <input type="hidden" name="csrf" value="'.h(csrf()).'">
      <label>Slug (nested ok, e.g. <code>codex/core</code>; blank=home)<br>
        <input name="slug" value="'.h($slug).'">
      </label><br>
      <label>Save as <code>index.json</code> in a folder?
        <input type="checkbox" name="as_index" value="1" '.($isIndex?'checked':'').'>
      </label><br>
      <label>Title<br><input name="title" value="'.h((string)$data['title']).'"></label><br>
      <label>HTML<br><textarea name="html" rows="14">'.h((string)$data['html']).'</textarea></label><br>
      <button class="btn" type="submit">Save</button>
    </form></div>';
}

// USERS
if ($act === 'users') {
  $rows = users_load($ROOT);
  echo '<div class="card"><h3 style="margin:0 0 6px 0">Users</h3>';
  echo '<details open><summary><strong>Create user</strong></summary>
        <form method="post" action="'.$self.'?a=user_create" class="row" style="margin-top:8px">
          <input type="hidden" name="csrf" value="'.h(csrf()).'">
          <label>Username<br><input name="user" required></label>
          <label>Password<br><input type="password" name="pass" required></label>
          <label>Role<br><select name="role"><option>admin</option><option>editor</option></select></label>
          <button class="btn" type="submit">Create</button>
        </form></details><hr>';

  if (!$rows) echo '<p>No users yet.</p>';
  else {
    echo '<table><tr><th>User</th><th>Roles</th><th class="nowrap">Actions</th></tr>';
    foreach ($rows as $u):
      $name = (string)($u['user'] ?? '');
      $roles = implode(',', (array)($u['roles'] ?? [])); ?>
      <tr>
        <td><?=h($name)?></td>
        <td><?=h($roles)?></td>
        <td class="nowrap">
          <form style="display:inline" method="post" action="<?=$self?>?a=user_pass">
            <input type="hidden" name="csrf" value="<?=h(csrf())?>">
            <input type="hidden" name="user" value="<?=h($name)?>">
            <input type="password" name="pass" placeholder="New password" required>
            <button class="btn btn-sm" type="submit">Set password</button>
          </form>
          <form style="display:inline" method="post" action="<?=$self?>?a=user_delete"
                onsubmit="return confirm('Delete user <?=h($name)?>?')">
            <input type="hidden" name="csrf" value="<?=h(csrf())?>">
            <input type="hidden" name="user" value="<?=h($name)?>">
            <button class="btn btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach;
    echo '</table>';
  }
  echo '</div>';
}

// SETTINGS
if ($act === 'settings') {
  $siteFile = $ROOT.'/data/site.json';
  $site = json_read($siteFile, ['name'=>'Chaos CMS','theme'=>'chaos','nav'=>[]]);
  $themes = themes_list($ROOT);
  echo '<div class="card"><h3 style="margin:0 0 6px 0">Site Settings</h3>
    <form method="post" action="'.$self.'?a=settings_save">
      <input type="hidden" name="csrf" value="'.h(csrf()).'">
      <label>Site name<br><input name="name" value="'.h((string)($site['name'] ?? 'Chaos CMS')).'"></label><br>
      <label>Theme<br><select name="theme">';
      $cur = (string)($site['theme'] ?? 'chaos');
      foreach ($themes as $t) {
        $sel = ($t===$cur)?' selected':'';
        echo '<option value="'.h($t).'"'.$sel.'>'.h($t).'</option>';
      }
  echo '</select></label><br>
      <button class="btn" type="submit">Save</button>
    </form></div>';
}

if ($standalone) echo '</div>'; // .wrap (standalone shell)
