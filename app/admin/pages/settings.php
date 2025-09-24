<?php
$cfg = json_read($ADMIN['ROOT'].'/data/settings.json', []);
?>
<h2>Settings</h2>

<form method="post" action="/admin?a=settings_save" class="card" style="max-width:720px;padding:16px">
  <div class="mb-2"><label class="form-label">From address</label>
    <input class="form-control" name="send_from" value="<?= h($cfg['send_from'] ?? '') ?>"></div>

  <div class="row g-2">
    <div class="col-md-6"><label class="form-label">SMTP host</label>
      <input class="form-control" name="smtp_host" value="<?= h($cfg['smtp_host'] ?? '') ?>"></div>
    <div class="col-md-2"><label class="form-label">Port</label>
      <input class="form-control" name="smtp_port" value="<?= h((string)($cfg['smtp_port'] ?? '')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Security</label>
      <select class="form-select" name="smtp_secure">
        <?php $sec = $cfg['smtp_secure'] ?? ''; ?>
        <option value=""   <?= $sec===''   ? 'selected':''?>>None</option>
        <option value="tls"<?= $sec==='tls'? 'selected':''?>>TLS</option>
        <option value="ssl"<?= $sec==='ssl'? 'selected':''?>>SSL</option>
      </select>
    </div>
  </div>

  <div class="row g-2 mt-1">
    <div class="col-md-6"><label class="form-label">SMTP user</label>
      <input class="form-control" name="smtp_user" value="<?= h($cfg['smtp_user'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">SMTP pass</label>
      <input class="form-control" type="password" name="smtp_pass" value="<?= h($cfg['smtp_pass'] ?? '') ?>"></div>
  </div>

  <div class="form-check mt-2">
    <input class="form-check-input" type="checkbox" name="use_mail_fallback" id="fback" <?= !empty($cfg['use_mail_fallback'])?'checked':'' ?>>
    <label class="form-check-label" for="fback">Use PHP mail() fallback</label>
  </div>

  <div class="mt-3"><button class="btn btn-primary">Save</button></div>
</form>