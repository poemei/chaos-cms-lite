<?php
// /app/admin/pages/main.php — minimal, no external helpers required

// pull username safely from session
$__user = $_SESSION['user'] ?? [];
$__name = (string)($__user['username'] ?? $__user['email'] ?? 'admin');

// tiny local esc helper to avoid collisions
$__h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<div class="card" style="padding:12px">
  <h3 style="margin:0 0 8px 0">Dashboard</h3>
  <p>Welcome, <?= $__h($__name) ?>.</p>

  <ul style="margin-top:8px">
    <li><a href="/admin?p=pages">Manage Pages</a></li>
    <li><a href="/admin?p=posts">Manage Posts</a></li>
    <li><a href="/admin?p=users">Manage Users</a></li>
    <li><a href="/logout">Logout</a></li>
  </ul>
</div>
