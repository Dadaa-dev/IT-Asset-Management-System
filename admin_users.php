<?php
/**
 * admin_users.php – Admin-only: list all users, change roles, delete accounts
 */
require 'config.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'admin') {
    flash('Access denied. Admins only.', 'error');
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action    = $_POST['action'] ?? '';
    $target_id = (int)($_POST['target_id'] ?? 0);

    // Prevent self-modification
    if ($target_id === (int)$_SESSION['user_id']) {
        flash('You cannot modify your own account here.', 'error');
        header('Location: admin_users.php');
        exit;
    }

    if ($action === 'set_role') {
        $role = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';
        $stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->bind_param('si', $role, $target_id);
        $stmt->execute();
        flash('Role updated.');
    } elseif ($action === 'delete') {
        $conn->query("DELETE FROM users WHERE id = $target_id");
        flash('User deleted.', 'error');
    }

    header('Location: admin_users.php');
    exit;
}

$users = $conn->query(
    'SELECT id, gmail, full_name, role, auth_provider, created_at, last_login, google_avatar FROM users ORDER BY id'
);

page_top('User Management', null, 'admin');
?>
<section class="welcome">
  <h1>User <span class="gold">Management</span></h1>
  <p class="muted">Manage all registered accounts.</p>
</section>

<div class="panel" style="overflow-x:auto">
  <table class="report-table full-table">
    <thead>
      <tr>
        <th>#</th><th>Avatar</th><th>Email</th><th>Name</th>
        <th>Role</th><th>Auth</th><th>Joined</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($u = $users->fetch_assoc()): $isSelf = ($u['id'] == $_SESSION['user_id']); ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td>
            <?php if ($u['google_avatar']): ?>
              <img src="<?= e($u['google_avatar']) ?>" class="avatar avatar-sm" alt="">
            <?php else: ?>
              <div class="avatar avatar-sm"><?= strtoupper(substr($u['gmail'],0,1)) ?></div>
            <?php endif; ?>
          </td>
          <td><?= e($u['gmail']) ?> <?= $isSelf ? '<span class="badge">You</span>' : '' ?></td>
          <td><?= e($u['full_name'] ?: '—') ?></td>
          <td><span class="badge"><?= ucfirst($u['role']) ?></span></td>
          <td><?= $u['auth_provider'] === 'google' ? '🔵 Google' : '🔐 Local' ?></td>
          <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if (!$isSelf): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf"      value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"    value="set_role">
                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                <select name="role" class="select-field select-sm">
                  <option value="staff" <?= $u['role']==='staff'?'selected':'' ?>>Staff</option>
                  <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                </select>
                <button class="btn btn-ghost btn-sm" type="submit">Save</button>
              </form>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Delete <?= e($u['gmail']) ?>? This cannot be undone.')">
                <input type="hidden" name="csrf"      value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"    value="delete">
                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php page_bottom(); ?>
