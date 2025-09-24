<?php
$users = json_read($ADMIN['ROOT'].'/data/users.json', []);
?>
<h2>Users</h2>
<?php if (!$users): ?>
  <p>No users yet.</p>
<?php else: ?>
  <div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= h($u['username'] ?? '') ?></td>
        <td><?= h($u['email'] ?? '') ?></td>
        <td><?= h($u['role'] ?? 'user') ?></td>
        <td><?= h($u['created'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
