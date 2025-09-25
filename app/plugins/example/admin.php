<?php
// /app/plugins/example/admin.php
if (!function_exists('is_admin') || !is_admin()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$cfgPath = __DIR__ . '/../../data/plugins/example.json';
$cfg = json_read($cfgPath, ['enabled'=>true, 'example_key'=>'default']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cfg['enabled'] = isset($_POST['enabled']);
    $cfg['example_key'] = trim($_POST['example_key'] ?? '');
    json_write($cfgPath, $cfg);
    echo '<div class="alert alert-success">Saved.</div>';
}

?>
<h2>Example Plugin Settings</h2>
<form method="post">
  <label class="form-label d-block">
    <input type="checkbox" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>> Enabled
  </label>
  <label class="form-label mt-2">Example Key
    <input type="text" name="example_key" class="form-control" value="<?= h($cfg['example_key']) ?>">
  </label>
  <button class="btn btn-primary mt-3">Save</button>
</form>
