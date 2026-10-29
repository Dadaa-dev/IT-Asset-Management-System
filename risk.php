<?php
/**
 * risk.php – Flags items in poor condition or under_repair
 */
require 'config.php';
require_login();

$risks = $conn->query(
    'SELECT i.id, i.name, i.`condition`, i.status, i.description,
            r.room_number, f.floor_number, b.name AS building_name, u.gmail,
            i.created_at
     FROM items i
     JOIN rooms r     ON r.id = i.room_id
     JOIN floors f    ON f.id = r.floor_id
     JOIN buildings b ON b.id = f.building_id
     LEFT JOIN users u ON u.id = i.added_by
     WHERE i.`condition` = "poor" OR i.status = "under_repair"
     ORDER BY i.`condition` DESC, i.created_at ASC'
);

page_top('Risk', null, 'risk');
?>
<section class="welcome">
  <h1>Risk <span class="gold">Assessment</span></h1>
  <p class="muted">Items marked as <strong>Poor</strong> condition or <strong>Under Repair</strong>.</p>
</section>

<?php if ($risks->num_rows === 0): ?>
  <div class="alert alert-success" style="max-width:520px">
    ✅ No at-risk items found. All assets are in good standing.
  </div>
<?php else: ?>
<div class="panel" style="overflow-x:auto">
  <table class="report-table full-table">
    <thead>
      <tr>
        <th>Item</th><th>Building</th><th>Floor</th><th>Room</th>
        <th>Condition</th><th>Status</th><th>Added By</th><th>Note</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($r = $risks->fetch_assoc()): ?>
        <tr class="risk-row risk-<?= $r['condition'] === 'poor' ? 'poor' : 'repair' ?>">
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['building_name']) ?></td>
          <td><?= (int)$r['floor_number'] ?></td>
          <td><?= e($r['room_number']) ?></td>
          <td><span class="badge badge-<?= $r['condition'] ?>"><?= ucfirst($r['condition']) ?></span></td>
          <td><span class="badge badge-status-<?= $r['status'] ?>"><?= ucwords(str_replace('_',' ',$r['status'])) ?></span></td>
          <td><?= e($r['gmail'] ?? '—') ?></td>
          <td class="muted"><?= e(mb_strimwidth($r['description'] ?? '', 0, 60, '…')) ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php page_bottom(); ?>
