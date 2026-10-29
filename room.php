<?php
require 'config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT r.id, r.room_number, r.label, f.id AS floor_id, f.floor_number, b.name AS building_name
     FROM rooms r
     JOIN floors f ON f.id = r.floor_id
     JOIN buildings b ON b.id = f.building_id
     WHERE r.id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
if (!$room) { header('Location: dashboard.php'); exit; }

$stmt = $conn->prepare(
    'SELECT i.*, u.gmail AS added_by_email
     FROM items i
     LEFT JOIN users u ON u.id = i.added_by
     WHERE i.room_id = ?
     ORDER BY i.id DESC'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$items = $stmt->get_result();

page_top($room['label'] ?: ('Room ' . $room['room_number']), 'floor.php?id=' . $room['floor_id']);
?>
<section class="welcome">
  <h1><?= e($room['building_name']) ?>
      <span class="gold">/ Floor <?= (int)$room['floor_number'] ?> / <?= e($room['label'] ?: ('Room ' . $room['room_number'])) ?></span></h1>
</section>

<section class="panel">
  <h2>➕ Add New Item</h2>
  <form action="add_item.php" method="post" enctype="multipart/form-data" class="item-form">
    <input type="hidden" name="csrf"    value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action"  value="add">
    <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">

    <div class="form-grid-2">
      <div>
        <label>Item Name *</label>
        <input type="text" name="name" maxlength="150" placeholder="e.g. Office Chair" required>
      </div>
      <div>
        <label>Category</label>
        <input type="text" name="category" maxlength="80" placeholder="e.g. Furniture">
      </div>
      <div>
        <label>Serial / Property No.</label>
        <input type="text" name="serial_no" maxlength="100" placeholder="e.g. SN-20240101">
      </div>
      <div>
        <label>Quantity</label>
        <input type="number" name="quantity" min="1" max="9999" value="1">
      </div>
      <div>
        <label>Condition</label>
        <select name="condition" class="select-field">
          <option value="good">Good</option>
          <option value="fair">Fair</option>
          <option value="poor">Poor</option>
        </select>
      </div>
      <div>
        <label>Photo / Picture</label>
        <input type="file" name="photo" accept="image/*">
      </div>
    </div>

    <label>Description</label>
    <textarea name="description" rows="3" placeholder="Describe the item..."></textarea>

    <button type="submit" class="btn btn-gold" style="margin-top:16px">Add Item</button>
  </form>
</section>

<h2 class="section-title">Items in this Room</h2>

<?php if ($items->num_rows === 0): ?>
  <p class="muted">No items yet. Add your first item above.</p>
<?php else: ?>
<div class="grid grid-items">
  <?php while ($it = $items->fetch_assoc()): ?>
    <article class="item">
      <?php if ($it['photo']): ?>
        <img src="uploads/<?= e($it['photo']) ?>" alt="<?= e($it['name']) ?>">
      <?php else: ?>
        <div class="no-photo">No photo</div>
      <?php endif; ?>
      <div class="item-body">
        <h3><?= e($it['name']) ?></h3>
        <div class="item-meta">
          <?php if ($it['category']): ?><span class="badge"><?= e($it['category']) ?></span><?php endif; ?>
          <span class="badge badge-<?= $it['condition'] ?>"><?= ucfirst($it['condition']) ?></span>
          <span class="badge badge-status-<?= $it['status'] ?>"><?= ucwords(str_replace('_',' ',$it['status'])) ?></span>
        </div>
        <?php if ($it['serial_no']): ?>
          <p class="item-serial">S/N: <?= e($it['serial_no']) ?></p>
        <?php endif; ?>
        <p class="item-serial">Asset code: <?= e($it['asset_code']) ?></p>
        <?php if (is_admin()): ?>
          <a class="btn btn-ghost btn-sm" href="asset_label.php?id=<?= (int)$it['id'] ?>">🏷️ Print Sticker</a>
        <?php endif; ?>
        <p><?= nl2br(e($it['description'])) ?></p>
        <p class="item-qty">Qty: <?= (int)$it['quantity'] ?></p>
        <?php if ($it['added_by_email']): ?>
          <p class="item-by">Added by <?= e($it['added_by_email']) ?></p>
        <?php endif; ?>

        <form action="add_item.php" method="post"
              onsubmit="return confirm('Delete this item permanently?');">
          <input type="hidden" name="csrf"    value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action"  value="delete">
          <input type="hidden" name="room_id" value="<?= (int)$room['id'] ?>">
          <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </div>
    </article>
  <?php endwhile; ?>
</div>
<?php endif; ?>
<?php page_bottom(); ?>