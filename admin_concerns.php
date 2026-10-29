<?php
/** admin_concerns.php — Admin/Facilitator manages all concerns */
require 'config.php';
require_admin();

$uid = $_SESSION['user_id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cid    = (int)($_POST['concern_id'] ?? 0);
    $status = in_array($_POST['status']??'',['open','in_progress','resolved','closed']) ? $_POST['status'] : 'open';
    $note   = trim($_POST['admin_note'] ?? '');
    $resolved_at = in_array($status,['resolved','closed']) ? 'NOW()' : 'NULL';

    $stmt = $conn->prepare("UPDATE concerns SET status=?, admin_note=?, resolved_by=?, resolved_at=$resolved_at, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('ssii', $status, $note, $uid, $cid);
    $stmt->execute();
    flash('Concern updated.');
    header('Location: admin_concerns.php'); exit;
}

$filterSt = trim($_GET['status'] ?? '');
$where    = $filterSt ? "WHERE c.status='".mysqli_real_escape_string($conn,$filterSt)."'" : '';

$concerns = $conn->query(
    "SELECT c.*,i.name AS item_name,i.asset_code AS item_code,u.gmail AS reporter_email,u.full_name AS reporter_name,u2.gmail AS resolved_by_email
     FROM concerns c
     LEFT JOIN items i ON i.id=c.item_id
     JOIN users u ON u.id=c.user_id
     LEFT JOIN users u2 ON u2.id=c.resolved_by
     $where ORDER BY
       FIELD(c.priority,'urgent','high','medium','low'),
       FIELD(c.status,'open','in_progress','resolved','closed'),
       c.created_at DESC"
);

$counts = [
    'all'         => $conn->query('SELECT COUNT(*) FROM concerns')->fetch_row()[0],
    'open'        => $conn->query("SELECT COUNT(*) FROM concerns WHERE status='open'")->fetch_row()[0],
    'in_progress' => $conn->query("SELECT COUNT(*) FROM concerns WHERE status='in_progress'")->fetch_row()[0],
    'resolved'    => $conn->query("SELECT COUNT(*) FROM concerns WHERE status='resolved'")->fetch_row()[0],
];

page_top('Manage Concerns', null, 'concerns');
?>
<section class="welcome">
  <h1>📋 Student <span class="gold">Concerns</span></h1>
  <p class="muted">Review, respond, and resolve reported asset issues.</p>
</section>

<!-- Filter tabs -->
<div class="tab-bar">
  <a class="tab-btn <?= !$filterSt?'active':'' ?>"    href="admin_concerns.php">All (<?= $counts['all'] ?>)</a>
  <a class="tab-btn <?= $filterSt==='open'?'active':'' ?>"        href="?status=open">🔴 Open (<?= $counts['open'] ?>)</a>
  <a class="tab-btn <?= $filterSt==='in_progress'?'active':'' ?>" href="?status=in_progress">🟡 In Progress (<?= $counts['in_progress'] ?>)</a>
  <a class="tab-btn <?= $filterSt==='resolved'?'active':'' ?>"    href="?status=resolved">🟢 Resolved (<?= $counts['resolved'] ?>)</a>
</div>

<div class="concerns-list" style="margin-top:16px">
<?php if ($concerns->num_rows === 0): ?>
  <div class="alert alert-success">No concerns in this category.</div>
<?php endif; ?>
<?php while ($c = $concerns->fetch_assoc()): ?>
  <div class="concern-card concern-<?= $c['status'] ?>">
    <div class="concern-header">
      <div>
        <h3><?= e($c['title']) ?></h3>
        <p class="muted">👤 <?= e($c['reporter_name']??$c['reporter_email']) ?> &bull; <?= date('M j, Y g:i a',strtotime($c['created_at'])) ?></p>
        <?php if ($c['item_name']): ?><p class="muted">📦 <?= e($c['item_name']) ?> · <?= e($c['item_code']) ?></p><?php endif; ?>
      </div>
      <div class="concern-badges">
        <span class="badge prio-<?= $c['priority'] ?>"><?= ucfirst($c['priority']) ?></span>
        <span class="badge status-<?= $c['status'] ?>"><?= ucwords(str_replace('_',' ',$c['status'])) ?></span>
      </div>
    </div>
    <p style="margin-bottom:12px"><?= nl2br(e($c['description'])) ?></p>

    <!-- Admin reply form -->
    <details class="concern-reply" <?= $c['status']==='open'?'open':'' ?>>
      <summary class="btn btn-ghost btn-sm" style="cursor:pointer">💬 Respond / Update Status</summary>
      <form method="post" style="margin-top:12px">
        <input type="hidden" name="csrf"       value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="concern_id" value="<?= (int)$c['id'] ?>">
        <label>Update Status</label>
        <select name="status" class="select-field" style="max-width:220px">
          <option value="open"        <?= $c['status']==='open'?'selected':''        ?>>🔴 Open</option>
          <option value="in_progress" <?= $c['status']==='in_progress'?'selected':'' ?>>🟡 In Progress</option>
          <option value="resolved"    <?= $c['status']==='resolved'?'selected':''    ?>>🟢 Resolved</option>
          <option value="closed"      <?= $c['status']==='closed'?'selected':''      ?>>⚫ Closed</option>
        </select>
        <label>Reply / Note to Student</label>
        <textarea name="admin_note" rows="3" placeholder="Explain what action was taken…"><?= e($c['admin_note']??'') ?></textarea>
        <button type="submit" class="btn btn-gold" style="margin-top:10px">Save Response</button>
      </form>
    </details>
  </div>
<?php endwhile; ?>
</div>
<?php page_bottom('admin'); ?>
