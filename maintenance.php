<?php
/** maintenance.php — Admin: schedule and track maintenance tasks */
require 'config.php';
require_admin();

$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $sched   = $_POST['scheduled_date'] ?? '';
        $type    = in_array($_POST['type']??'',['preventive','corrective','inspection']) ? $_POST['type'] : 'preventive';
        $desc    = trim($_POST['description'] ?? '');
        $cost    = (float)($_POST['cost'] ?? 0);
        if ($item_id && $sched) {
            $stmt = $conn->prepare('INSERT INTO maintenance (item_id,scheduled_date,type,description,cost,performed_by) VALUES (?,?,?,?,?,?)');
            $stmt->bind_param('isssdi',$item_id,$sched,$type,$desc,$cost,$uid);
            $stmt->execute();
            flash('Maintenance scheduled.');
        }
    } elseif ($action === 'done') {
        $mid  = (int)($_POST['maint_id'] ?? 0);
        $done = date('Y-m-d');
        $conn->query("UPDATE maintenance SET status='done', performed_date='$done', performed_by=$uid WHERE id=$mid");
        // Update item status back to active
        $mid_row = $conn->query("SELECT item_id FROM maintenance WHERE id=$mid")->fetch_assoc();
        if ($mid_row) $conn->query("UPDATE items SET status='active' WHERE id={$mid_row['item_id']} AND status='under_repair'");
        flash('Maintenance marked as done.');
    } elseif ($action === 'cancel') {
        $mid = (int)($_POST['maint_id'] ?? 0);
        $conn->query("UPDATE maintenance SET status='cancelled' WHERE id=$mid");
        flash('Maintenance cancelled.','error');
    }
    header('Location: maintenance.php'); exit;
}

$filter = trim($_GET['status'] ?? 'scheduled');
$maint  = $conn->query(
    "SELECT m.*,i.name AS item_name,r.room_number,b.name AS bname,u.gmail AS done_by
     FROM maintenance m
     JOIN items i ON i.id=m.item_id
     JOIN rooms r ON r.id=i.room_id
     JOIN floors f ON f.id=r.floor_id
     JOIN buildings b ON b.id=f.building_id
     LEFT JOIN users u ON u.id=m.performed_by
     WHERE m.status='".mysqli_real_escape_string($conn,$filter)."'
     ORDER BY m.scheduled_date ASC"
);

$assets = $conn->query('SELECT i.id,i.name,r.room_number,b.name AS bname FROM items i JOIN rooms r ON r.id=i.room_id JOIN floors f ON f.id=r.floor_id JOIN buildings b ON b.id=f.building_id ORDER BY b.name,i.name');

page_top('Maintenance', null, 'maintenance');
?>
<section class="welcome">
  <h1>🔧 Asset <span class="gold">Maintenance</span></h1>
  <p class="muted">Schedule and track preventive/corrective maintenance for assets.</p>
</section>

<div style="margin-bottom:16px">
  <button class="btn btn-gold" onclick="openModal('maint-modal')">➕ Schedule Maintenance</button>
</div>

<div class="tab-bar">
  <a class="tab-btn <?= $filter==='scheduled'?'active':'' ?>" href="?status=scheduled">📅 Scheduled</a>
  <a class="tab-btn <?= $filter==='done'?'active':'' ?>"      href="?status=done">✅ Done</a>
  <a class="tab-btn <?= $filter==='cancelled'?'active':'' ?>" href="?status=cancelled">❌ Cancelled</a>
</div>

<div class="panel" style="overflow-x:auto;margin-top:16px;padding:0">
<table class="report-table full-table">
  <thead><tr><th>Asset</th><th>Location</th><th>Type</th><th>Scheduled</th><th>Done</th><th>Cost</th><th>Description</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if ($maint->num_rows===0): ?>
    <tr><td colspan="8" style="text-align:center;padding:40px" class="muted">No records.</td></tr>
  <?php endif; ?>
  <?php while ($m=$maint->fetch_assoc()): ?>
  <tr>
    <td><strong><?= e($m['item_name']) ?></strong></td>
    <td><small><?= e($m['bname']) ?> / Rm <?= e($m['room_number']) ?></small></td>
    <td><span class="badge"><?= ucfirst($m['type']) ?></span></td>
    <td><?= date('M j, Y',strtotime($m['scheduled_date'])) ?></td>
    <td><?= $m['performed_date'] ? date('M j, Y',strtotime($m['performed_date'])) : '—' ?></td>
    <td>₱<?= number_format($m['cost'],2) ?></td>
    <td class="muted"><small><?= e(mb_strimwidth($m['description']??'',0,60,'…')) ?></small></td>
    <td>
      <?php if ($m['status']==='scheduled'): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf"     value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action"   value="done">
        <input type="hidden" name="maint_id" value="<?= $m['id'] ?>">
        <button class="btn btn-gold btn-sm">✅ Done</button>
      </form>
      <form method="post" style="display:inline" onsubmit="return confirm('Cancel this schedule?')">
        <input type="hidden" name="csrf"     value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action"   value="cancel">
        <input type="hidden" name="maint_id" value="<?= $m['id'] ?>">
        <button class="btn btn-danger btn-sm">❌</button>
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

<!-- Add Maintenance Modal -->
<div class="modal-overlay" id="maint-modal" onclick="closeOnBg(event,'maint-modal')">
  <div class="modal">
    <div class="modal-header">
      <h2>➕ Schedule Maintenance</h2>
      <button class="modal-close" onclick="closeModal('maint-modal')">✕</button>
    </div>
    <form method="post" class="modal-body">
      <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">

      <label>Asset *</label>
      <select name="item_id" class="select-field" required>
        <option value="">— Select Asset —</option>
        <?php while ($a=$assets->fetch_assoc()): ?>
          <option value="<?= $a['id'] ?>"><?= e($a['name']) ?> (<?= e($a['bname']) ?>/<?= e($a['room_number']) ?>)</option>
        <?php endwhile; ?>
      </select>

      <label>Type</label>
      <select name="type" class="select-field">
        <option value="preventive">Preventive</option>
        <option value="corrective">Corrective</option>
        <option value="inspection">Inspection</option>
      </select>

      <label>Scheduled Date *</label>
      <input type="date" name="scheduled_date" required min="<?= date('Y-m-d') ?>">

      <label>Estimated Cost (₱)</label>
      <input type="number" name="cost" step="0.01" min="0" value="0">

      <label>Description / Notes</label>
      <textarea name="description" rows="3" placeholder="What needs to be done…"></textarea>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('maint-modal')">Cancel</button>
        <button type="submit" class="btn btn-gold">Schedule</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
function closeOnBg(e,id){ if(e.target.id===id) closeModal(id); }
</script>
<?php page_bottom('admin'); ?>
