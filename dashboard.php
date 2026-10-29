<?php
require 'config.php';
require_login();

$isAdmin = is_admin();
$uid     = $_SESSION['user_id'];

// Stats
$totalItems    = $conn->query('SELECT COUNT(*) FROM items WHERE status != "disposed"')->fetch_row()[0] ?? 0;
$totalBuildings= $conn->query('SELECT COUNT(*) FROM buildings')->fetch_row()[0] ?? 0;
$openConcerns  = $conn->query('SELECT COUNT(*) FROM concerns WHERE status IN ("open","in_progress")')->fetch_row()[0] ?? 0;
$poorItems     = $conn->query('SELECT COUNT(*) FROM items WHERE `condition`="poor" OR status="under_repair"')->fetch_row()[0] ?? 0;

if (!$isAdmin) {
    $myConcerns = $conn->prepare('SELECT COUNT(*) FROM concerns WHERE user_id=? AND status NOT IN ("resolved","closed")');
    $myConcerns->bind_param('i', $uid); $myConcerns->execute();
    $openConcerns = $myConcerns->get_result()->fetch_row()[0] ?? 0;
}

$buildings = $conn->query('SELECT b.*,
    (SELECT COUNT(*) FROM floors f WHERE f.building_id=b.id) AS floor_count,
    (SELECT COUNT(*) FROM items i JOIN rooms r ON r.id=i.room_id JOIN floors f ON f.id=r.floor_id WHERE f.building_id=b.id AND i.status!="disposed") AS asset_count
    FROM buildings b ORDER BY b.id');

$name = $_SESSION['full_name'] ?? '';
if (!$name) {
    $namePart = strstr($_SESSION['gmail'],'@',true);
    $name = ucwords(str_replace(['.','_','-'],' ',$namePart));
}

$fabType = $isAdmin ? 'admin' : 'student';
page_top('Dashboard', null, 'home', 'dash-bg');
?>
<!-- Welcome Hero -->
<section class="welcome-hero">
  <div>
    <p class="welcome-sub">Welcome back,     <?= $isAdmin ? '🛠️ Facilitator' : '🎓 Student / Teacher' ?></p>
    <h1 class="welcome-name"><?= e($name) ?>!</h1>
    <p class="muted">
      <?= $isAdmin
        ? 'Manage buildings, track assets, and monitor concerns.'
        : 'Browse assets and report any concerns about equipment.' ?>
    </p>
  </div>
</section>

<!-- Stat Cards -->
<div class="stats-row">
  <div class="stat-card">
    <div class="stat-icon">💻</div>
    <div class="stat-num"><?= $totalItems ?></div>
    <div class="stat-label">Active Assets</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🏢</div>
    <div class="stat-num"><?= $totalBuildings ?></div>
    <div class="stat-label">Buildings</div>
  </div>
  <div class="stat-card <?= $openConcerns > 0 ? 'stat-warn' : '' ?>">
    <div class="stat-icon">📋</div>
    <div class="stat-num"><?= $openConcerns ?></div>
    <div class="stat-label"><?= $isAdmin ? 'Open Concerns' : 'My Open Concerns' ?></div>
  </div>
  <div class="stat-card <?= $poorItems > 0 ? 'stat-danger' : '' ?>">
    <div class="stat-icon">⚠️</div>
    <div class="stat-num"><?= $poorItems ?></div>
    <div class="stat-label">At-Risk Assets</div>
  </div>
</div>

<?php if (!$isAdmin): ?>
<!-- Student Quick Actions -->
<div class="quick-actions">
  <a class="qcard" href="report_concern.php">
    <span class="qcard-icon">📷</span>
    <div><strong>Scan &amp; Report</strong><p>Scan an asset sticker or report an issue</p></div>
  </a>
  <a class="qcard" href="my_concerns.php">
    <span class="qcard-icon">📋</span>
    <div><strong>My Concerns</strong><p>Track your submitted reports</p></div>
  </a>
  <a class="qcard" href="assets.php">
    <span class="qcard-icon">🔍</span>
    <div><strong>Browse Assets</strong><p>View all school equipment</p></div>
  </a>
</div>
<?php endif; ?>

<!-- Buildings -->
<h2 class="section-title">📍 Buildings</h2>
<div class="grid grid-3">
<?php
$imgs = [
    'Main Bldg'            => 'asset/main-building.jpg',
    'Melloville Bldg'      => 'asset/meloville-building.jpg',
    'Carbag Building'     => 'asset/carbarg-building.jpg',
];
while ($b = $buildings->fetch_assoc()):
    $img = $imgs[$b['name']] ?? null;
?>
  <a class="bcard" href="building.php?id=<?= (int)$b['id'] ?>">
    <div class="bcard-photo">
      <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($b['name']) ?>" onerror="this.style.display='none'"><?php endif; ?>
      <div class="bcard-overlay">
        <span class="badge"><?= (int)$b['floor_count'] ?> floors</span>
        <span class="badge badge-status-active"><?= (int)$b['asset_count'] ?> assets</span>
      </div>
    </div>
    <div class="bcard-footer">
      <h3><?= e($b['name']) ?></h3>
      <span class="bcard-hint">View floors &rarr;</span>
    </div>
  </a>
<?php endwhile; ?>
</div>
<?php page_bottom($fabType); ?>