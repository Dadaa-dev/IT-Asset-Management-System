<?php
require 'config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT f.id, f.floor_number, b.id AS building_id, b.name AS building_name
     FROM floors f JOIN buildings b ON b.id = f.building_id WHERE f.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$floor = $stmt->get_result()->fetch_assoc();
if (!$floor) { header('Location: dashboard.php'); exit; }

$stmt = $conn->prepare('SELECT id, room_number, label FROM rooms WHERE floor_id = ? ORDER BY room_number');
$stmt->bind_param('i', $id);
$stmt->execute();
$rooms = $stmt->get_result();

page_top('Floor ' . $floor['floor_number'], 'building.php?id=' . $floor['building_id']);
?>
<section class="welcome">
  <h1><?= e($floor['building_name']) ?> <span class="gold">/ Floor <?= (int)$floor['floor_number'] ?></span></h1>
  <p class="muted">Choose a room.</p>
</section>

<div class="grid grid-4">
  <?php while ($r = $rooms->fetch_assoc()): ?>
    <a class="card" href="room.php?id=<?= (int)$r['id'] ?>">
      <div class="card-icon">🚪</div>
      <h3><?= e($r['label'] ?: ('Room ' . $r['room_number'])) ?></h3>
    </a>
  <?php endwhile; ?>
</div>
<?php page_bottom(); ?>