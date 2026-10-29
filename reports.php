<?php
/**
 * reports.php – Summary statistics and item listing
 */
require 'config.php';
require_login();

// Total counts
$totalItems     = $conn->query('SELECT COUNT(*) FROM items WHERE status != "disposed"')->fetch_row()[0];
$totalBuildings = $conn->query('SELECT COUNT(*) FROM buildings')->fetch_row()[0];
$totalRooms     = $conn->query('SELECT COUNT(*) FROM rooms')->fetch_row()[0];
$totalDisposed  = $conn->query('SELECT COUNT(*) FROM items WHERE status = "disposed"')->fetch_row()[0];

// Items by condition
$byCondition = $conn->query(
    'SELECT `condition`, COUNT(*) AS cnt FROM items WHERE status != "disposed" GROUP BY `condition`'
)->fetch_all(MYSQLI_ASSOC);

// Items by building
$byBuilding = $conn->query(
    'SELECT b.name, COUNT(i.id) AS cnt
     FROM buildings b
     LEFT JOIN floors f  ON f.building_id = b.id
     LEFT JOIN rooms  r  ON r.floor_id    = f.id
     LEFT JOIN items  i  ON i.room_id     = r.id AND i.status != "disposed"
     GROUP BY b.id ORDER BY cnt DESC'
)->fetch_all(MYSQLI_ASSOC);

// Recent items (last 20)
$recent = $conn->query(
    'SELECT i.name, i.`condition`, i.status, i.created_at,
            r.room_number, f.floor_number, b.name AS building_name, u.gmail
     FROM items i
     JOIN rooms r    ON r.id = i.room_id
     JOIN floors f   ON f.id = r.floor_id
     JOIN buildings b ON b.id = f.building_id
     LEFT JOIN users u ON u.id = i.added_by
     ORDER BY i.created_at DESC LIMIT 20'
);

page_top('Reports', null, 'reports');
?>
<section class="welcome">
  <h1>Reports <span class="gold">& Summary</span></h1>
  <p class="muted">Overview of all tracked assets.</p>
</section>

<!-- ── Stat Cards ─────────────────────────────────────────── -->
<div class="grid grid-4 stat-cards">
  <div class="stat-card">
    <div class="stat-num"><?= $totalItems ?></div>
    <div class="stat-label">Active Assets</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $totalBuildings ?></div>
    <div class="stat-label">Buildings</div>
  </div>
  <div class="stat-card">
    <div class="stat-num"><?= $totalRooms ?></div>
    <div class="stat-label">Rooms</div>
  </div>
  <div class="stat-card stat-card-danger">
    <div class="stat-num"><?= $totalDisposed ?></div>
    <div class="stat-label">Disposed</div>
  </div>
</div>

<!-- ── By Condition ───────────────────────────────────────── -->
<div class="grid grid-2-report">
  <div class="panel">
    <h2>By Condition</h2>
    <table class="report-table">
      <thead><tr><th>Condition</th><th>Count</th></tr></thead>
      <tbody>
        <?php foreach ($byCondition as $row): ?>
          <tr>
            <td><span class="badge badge-<?= $row['condition'] ?>"><?= ucfirst($row['condition']) ?></span></td>
            <td><?= $row['cnt'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="panel">
    <h2>By Building</h2>
    <table class="report-table">
      <thead><tr><th>Building</th><th>Assets</th></tr></thead>
      <tbody>
        <?php foreach ($byBuilding as $row): ?>
          <tr><td><?= e($row['name']) ?></td><td><?= $row['cnt'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Recent Items ───────────────────────────────────────── -->
<h2 class="section-title">Recently Added Assets</h2>
<div class="panel" style="overflow-x:auto">
  <table class="report-table full-table">
    <thead>
      <tr>
        <th>Name</th><th>Building</th><th>Floor</th><th>Room</th>
        <th>Condition</th><th>Status</th><th>Added By</th><th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($r = $recent->fetch_assoc()): ?>
        <tr>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['building_name']) ?></td>
          <td><?= (int)$r['floor_number'] ?></td>
          <td><?= e($r['room_number']) ?></td>
          <td><span class="badge badge-<?= $r['condition'] ?>"><?= ucfirst($r['condition']) ?></span></td>
          <td><span class="badge badge-status-<?= $r['status'] ?>"><?= ucwords(str_replace('_',' ',$r['status'])) ?></span></td>
          <td><?= e($r['gmail'] ?? '—') ?></td>
          <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php page_bottom(); ?>
