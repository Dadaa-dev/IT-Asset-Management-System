<?php
/** my_concerns.php — Student view of their own submitted concerns */
require 'config.php';
require_login();

$uid = $_SESSION['user_id'];

$concerns = $conn->prepare(
    'SELECT c.*, i.name AS item_name, i.asset_code AS item_code, u2.gmail AS resolved_by_email
     FROM concerns c
     LEFT JOIN items i ON i.id=c.item_id
     LEFT JOIN users u2 ON u2.id=c.resolved_by
     WHERE c.user_id=?
     ORDER BY c.created_at DESC'
);
$concerns->bind_param('i',$uid);
$concerns->execute();
$rows = $concerns->get_result();

page_top('My Concerns', 'dashboard.php', 'my_concerns');
?>
<section class="welcome">
  <h1>📋 My <span class="gold">Concerns</span></h1>
  <p class="muted">Track all concerns and reports you have submitted.</p>
</section>

<div style="margin-bottom:16px">
  <a class="btn btn-gold" href="report_concern.php">🚨 Report New Concern</a>
</div>

<?php if ($rows->num_rows===0): ?>
  <div class="alert alert-success" style="max-width:520px">You have no concerns submitted yet.</div>
<?php else: ?>
<div class="concerns-list">
<?php while ($c=$rows->fetch_assoc()): ?>
  <div class="concern-card concern-<?= $c['status'] ?>">
    <div class="concern-header">
      <div>
        <h3><?= e($c['title']) ?></h3>
        <?php if ($c['item_name']): ?>
          <p class="muted">📦 Asset: <?= e($c['item_name']) ?> · <?= e($c['item_code']) ?></p>
        <?php endif; ?>
      </div>
      <div class="concern-badges">
        <span class="badge prio-<?= $c['priority'] ?>"><?= ucfirst($c['priority']) ?></span>
        <span class="badge status-<?= $c['status'] ?>"><?= ucwords(str_replace('_',' ',$c['status'])) ?></span>
      </div>
    </div>
    <p><?= nl2br(e($c['description'])) ?></p>
    <?php if ($c['admin_note']): ?>
      <div class="admin-reply">
        <strong>💬 IT Staff Reply:</strong><br><?= nl2br(e($c['admin_note'])) ?>
        <?php if ($c['resolved_by_email']): ?>
          <small class="muted">— <?= e($c['resolved_by_email']) ?></small>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="concern-meta">
      <small class="muted">Submitted: <?= date('M j, Y g:i a', strtotime($c['created_at'])) ?></small>
      <?php if ($c['resolved_at']): ?>
        <small class="muted"> &bull; Resolved: <?= date('M j, Y', strtotime($c['resolved_at'])) ?></small>
      <?php endif; ?>
    </div>
  </div>
<?php endwhile; ?>
</div>
<?php endif; ?>
<?php page_bottom('student'); ?>
