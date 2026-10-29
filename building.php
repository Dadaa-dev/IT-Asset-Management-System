<?php
require 'config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT id, name FROM buildings WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$building = $stmt->get_result()->fetch_assoc();
if (!$building) { header('Location: dashboard.php'); exit; }

$stmt = $conn->prepare('SELECT id, floor_number FROM floors WHERE building_id = ? ORDER BY floor_number');
$stmt->bind_param('i', $id);
$stmt->execute();
$floors = $stmt->get_result();

page_top($building['name'], 'dashboard.php');
?>
<section class="welcome">
  <h1><?= e($building['name']) ?></h1>
  <p class="muted">Choose a floor.</p>
</section>

<div class="grid grid-3">
  <?php while ($f = $floors->fetch_assoc()): ?>
    <a class="card card-big" href="floor.php?id=<?= (int)$f['id'] ?>">
      <div class="card-icon">🪜</div>
      <h2>Floor <?= (int)$f['floor_number'] ?></h2>
      <span class="card-hint">View rooms &rarr;</span>
    </a>
  <?php endwhile; ?>
</div>
<?php page_bottom(); ?>