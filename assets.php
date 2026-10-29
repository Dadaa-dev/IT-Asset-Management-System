<?php
/**
 * assets.php — Full CRUD Asset Manager (Admin) / Read-only Browser (Student)
 * Features: search, filter by category/status/condition, pagination, CRUD
 */
require 'config.php';
require_login();

$isAdmin = is_admin();
$uid     = $_SESSION['user_id'];

// ── Handle CRUD (Admin only) ─────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $iid  = (int)($_POST['item_id'] ?? 0);
        $ph   = $conn->query("SELECT photo, name FROM items WHERE id=$iid")->fetch_assoc();
        $conn->query("DELETE FROM items WHERE id=$iid");
        if ($ph && $ph['photo']) @unlink(__DIR__.'/uploads/'.$ph['photo']);
        $log = $conn->prepare('INSERT INTO item_logs (item_id,user_id,action,note) VALUES (?,?,?,?)');
        $act='delete'; $note='Deleted: '.($ph['name']??'');
        $log->bind_param('iiss', $iid, $uid, $act, $note); $log->execute();
        flash('Asset deleted.', 'error');
        header('Location: assets.php'); exit;

    } elseif ($action === 'add' || $action === 'edit') {
        $iid      = (int)($_POST['item_id'] ?? 0);
        $room_id  = (int)($_POST['room_id']  ?? 0);
        $cat_id   = (int)($_POST['category_id'] ?? 0) ?: null;
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $serial   = trim($_POST['serial_no'] ?? '');
        $prop     = trim($_POST['property_no'] ?? '');
        $qty      = max(1,(int)($_POST['quantity'] ?? 1));
        $cost     = (float)($_POST['unit_cost'] ?? 0);
        $cond     = in_array($_POST['condition']??'',['good','fair','poor']) ? $_POST['condition'] : 'good';
        $status   = in_array($_POST['status']??'',['active','under_repair','disposed','reserved']) ? $_POST['status'] : 'active';
        $acq      = $_POST['acquired_date'] ?? null ?: null;
        $photo    = null;

        if (!$name) { flash('Item name required.','error'); header('Location: assets.php'); exit; }

        // Photo upload
        if (!empty($_FILES['photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext,['jpg','jpeg','png','gif','webp'])) { flash('Photo: jpg/png/gif/webp only.','error'); header('Location: assets.php'); exit; }
            if ($_FILES['photo']['size'] > 5*1024*1024) { flash('Photo must be under 5MB.','error'); header('Location: assets.php'); exit; }
            $dir = __DIR__.'/uploads/'; if (!is_dir($dir)) mkdir($dir,0755,true);
            $fname = uniqid('img_').'.'.$ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $dir.$fname);
            $photo = $fname;
        }

        if ($action === 'add') {
            $assetCode = 'AV' . strtoupper(bin2hex(random_bytes(8)));
            $stmt = $conn->prepare('INSERT INTO items (room_id,category_id,name,description,serial_no,property_no,asset_code,quantity,unit_cost,`condition`,status,acquired_date,photo,added_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->bind_param('iisssssidssssi', $room_id,$cat_id,$name,$desc,$serial,$prop,$assetCode,$qty,$cost,$cond,$status,$acq,$photo,$uid);
            $stmt->execute();
            $newId = $conn->insert_id;
            $log = $conn->prepare('INSERT INTO item_logs (item_id,user_id,action,note) VALUES (?,?,?,?)');
            $act='add'; $note="Added: $name";
            $log->bind_param('iiss',$newId,$uid,$act,$note); $log->execute();
            flash("Asset \"$name\" added.");
        } else {
            $old = $conn->query("SELECT photo FROM items WHERE id=$iid")->fetch_assoc();
            $finalPhoto = $photo ?? ($old['photo'] ?? null);
            $stmt = $conn->prepare('UPDATE items SET room_id=?,category_id=?,name=?,description=?,serial_no=?,property_no=?,quantity=?,unit_cost=?,`condition`=?,status=?,acquired_date=?,photo=?,updated_by=? WHERE id=?');
            $stmt->bind_param('iissssidssssii', $room_id,$cat_id,$name,$desc,$serial,$prop,$qty,$cost,$cond,$status,$acq,$finalPhoto,$uid,$iid);
            $stmt->execute();
            $log = $conn->prepare('INSERT INTO item_logs (item_id,user_id,action,note) VALUES (?,?,?,?)');
            $act='edit'; $note="Edited: $name";
            $log->bind_param('iiss',$iid,$uid,$act,$note); $log->execute();
            flash("Asset \"$name\" updated.");
        }
        header('Location: assets.php'); exit;
    }
}

// ── Filters ──────────────────────────────────────────────────
$search    = trim($_GET['q']      ?? '');
$filterCat = (int)($_GET['cat']    ?? 0);
$filterSt  = trim($_GET['status'] ?? '');
$filterCond= trim($_GET['cond']   ?? '');
$page      = max(1,(int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page-1)*$perPage;

$where = ['1=1'];
$params = []; $types = '';
if ($search)    { $where[]="(i.name LIKE ? OR i.serial_no LIKE ? OR i.property_no LIKE ? OR i.asset_code LIKE ?)"; $s="%$search%"; $params[]=$s;$params[]=$s;$params[]=$s;$params[]=$s; $types.='ssss'; }
if ($filterCat) { $where[]="i.category_id=?"; $params[]=$filterCat; $types.='i'; }
if ($filterSt)  { $where[]="i.status=?";      $params[]=$filterSt;  $types.='s'; }
if ($filterCond){ $where[]="i.condition=?";   $params[]=$filterCond;$types.='s'; }
$wStr = implode(' AND ',$where);

$countQ = $conn->prepare("SELECT COUNT(*) FROM items i WHERE $wStr");
if ($types) $countQ->bind_param($types,...$params);
$countQ->execute();
$total = $countQ->get_result()->fetch_row()[0];
$pages = ceil($total/$perPage);

$q = $conn->prepare("SELECT i.*,c.name AS cat_name,r.room_number,f.floor_number,b.name AS building_name,u.gmail AS added_by_email
    FROM items i
    LEFT JOIN categories c ON c.id=i.category_id
    JOIN rooms r ON r.id=i.room_id
    JOIN floors f ON f.id=r.floor_id
    JOIN buildings b ON b.id=f.building_id
    LEFT JOIN users u ON u.id=i.added_by
    WHERE $wStr ORDER BY i.updated_at DESC LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types.'ii';
$q->bind_param($allTypes, ...$allParams);
$q->execute();
$items = $q->get_result();

$categories = $conn->query('SELECT * FROM categories ORDER BY name');
$rooms_list = $conn->query('SELECT r.id,r.room_number,r.label,f.floor_number,b.name AS bname FROM rooms r JOIN floors f ON f.id=r.floor_id JOIN buildings b ON b.id=f.building_id ORDER BY b.name,f.floor_number,r.room_number');

$fabType = $isAdmin ? 'admin' : 'student';
page_top('Assets', null, 'assets');
?>

<section class="welcome">
  <h1>💻 Asset <span class="gold">Management</span></h1>
  <p class="muted"><?= $isAdmin ? 'Full CRUD control over all school assets.' : 'Browse all registered school assets.' ?></p>
</section>

<!-- Search & Filter -->
<form method="get" class="filter-bar">
  <input type="text" name="q" placeholder="🔍 Search name, serial, asset code…" value="<?= e($search) ?>">
  <select name="cat" class="select-field">
    <option value="">All Categories</option>
    <?php $categories->data_seek(0); while($c=$categories->fetch_assoc()): ?>
      <option value="<?= $c['id'] ?>" <?= $filterCat==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
    <?php endwhile; ?>
  </select>
  <select name="status" class="select-field">
    <option value="">All Status</option>
    <option value="active"       <?= $filterSt==='active'?'selected':''        ?>>Active</option>
    <option value="under_repair" <?= $filterSt==='under_repair'?'selected':''  ?>>Under Repair</option>
    <option value="disposed"     <?= $filterSt==='disposed'?'selected':''      ?>>Disposed</option>
    <option value="reserved"     <?= $filterSt==='reserved'?'selected':''      ?>>Reserved</option>
  </select>
  <select name="cond" class="select-field">
    <option value="">All Conditions</option>
    <option value="good" <?= $filterCond==='good'?'selected':'' ?>>Good</option>
    <option value="fair" <?= $filterCond==='fair'?'selected':'' ?>>Fair</option>
    <option value="poor" <?= $filterCond==='poor'?'selected':'' ?>>Poor</option>
  </select>
  <button class="btn btn-gold" type="submit">Filter</button>
  <a class="btn btn-ghost" href="assets.php">Reset</a>
  <?php if ($isAdmin): ?>
    <button class="btn btn-gold" type="button" onclick="openModal('addModal')">➕ Add Asset</button>
  <?php endif; ?>
</form>

<p class="muted" style="margin:8px 0 16px">Showing <?= $items->num_rows ?> of <?= $total ?> assets</p>

<!-- Asset Table -->
<div class="panel" style="overflow-x:auto;padding:0">
<table class="report-table full-table">
  <thead>
    <tr>
      <th>Photo</th><th>Name</th><th>Category</th><th>Location</th>
      <th>S/N</th><th>Asset Code</th><th>Qty</th><th>Condition</th><th>Status</th>
      <?php if ($isAdmin): ?><th>Cost</th><th>Actions</th><?php endif; ?>
    </tr>
  </thead>
  <tbody>
  <?php if ($items->num_rows === 0): ?>
    <tr><td colspan="<?= $isAdmin?11:9 ?>" style="text-align:center;padding:40px" class="muted">No assets found.</td></tr>
  <?php endif; ?>
  <?php while ($it = $items->fetch_assoc()): ?>
    <tr>
      <td>
        <?php if ($it['photo']): ?>
          <img src="uploads/<?= e($it['photo']) ?>" class="table-thumb" alt="">
        <?php else: ?>
          <div class="table-thumb-empty">📦</div>
        <?php endif; ?>
      </td>
      <td>
        <strong><?= e($it['name']) ?></strong>
        <?php if ($it['property_no']): ?><br><small class="muted">Prop: <?= e($it['property_no']) ?></small><?php endif; ?>
      </td>
      <td><?= e($it['cat_name'] ?? '—') ?></td>
      <td>
        <small><?= e($it['building_name']) ?></small><br>
        <small class="muted">Floor <?= (int)$it['floor_number'] ?> / Room <?= e($it['room_number']) ?><?= $it['label']?' — '.e($it['label']):'' ?></small>
      </td>
      <td class="muted"><small><?= e($it['serial_no']??'—') ?></small></td>
      <td><code><?= e($it['asset_code']) ?></code></td>
      <td><?= (int)$it['quantity'] ?></td>
      <td><span class="badge badge-<?= $it['condition'] ?>"><?= ucfirst($it['condition']) ?></span></td>
      <td><span class="badge badge-status-<?= $it['status'] ?>"><?= ucwords(str_replace('_',' ',$it['status'])) ?></span></td>
      <?php if ($isAdmin): ?>
      <td class="muted">₱<?= number_format($it['unit_cost'],2) ?></td>
      <td>
        <button class="btn btn-ghost btn-sm" onclick='editItem(<?= json_encode($it) ?>)'>✏️ Edit</button>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this asset permanently?')">
          <input type="hidden" name="csrf"    value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action"  value="delete">
          <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">🗑️</button>
        </form>
        <a class="btn btn-ghost btn-sm" href="asset_label.php?id=<?= (int)$it['id'] ?>">🏷️ Sticker</a>
        <?php if ($_SESSION['role']==='admin'): ?>
        <a class="btn btn-ghost btn-sm" href="report_concern.php?item_id=<?= $it['id'] ?>">⚠️</a>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
  <?php endwhile; ?>
  </tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination">
  <?php for($i=1;$i<=$pages;$i++): ?>
    <a class="page-btn <?= $i==$page?'active':'' ?>"
       href="?q=<?= urlencode($search) ?>&cat=<?= $filterCat ?>&status=<?= $filterSt ?>&cond=<?= $filterCond ?>&page=<?= $i ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- ── Add Modal ───────────────────────────────────────────── -->
<div class="modal-overlay" id="addModal" onclick="closeOnBg(event,'addModal')">
  <div class="modal">
    <div class="modal-header">
      <h2 id="modalTitle">➕ Add Asset</h2>
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    </div>
    <form method="post" enctype="multipart/form-data" class="modal-body">
      <input type="hidden" name="csrf"    value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action"  id="formAction" value="add">
      <input type="hidden" name="item_id" id="formItemId" value="">

      <div class="form-grid-2">
        <div>
          <label>Asset Name *</label>
          <input type="text" name="name" id="fName" maxlength="150" placeholder="e.g. Mechanical Keyboard" required>
        </div>
        <div>
          <label>Category</label>
          <select name="category_id" id="fCat" class="select-field">
            <option value="">— Select —</option>
            <?php $categories->data_seek(0); while($c=$categories->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label>Location (Room) *</label>
          <select name="room_id" id="fRoom" class="select-field" required>
            <option value="">— Select Room —</option>
            <?php $rooms_list->data_seek(0); $lastB=''; while($r=$rooms_list->fetch_assoc()):
              if ($r['bname']!==$lastB) { echo '<optgroup label="'.e($r['bname']).'">'; $lastB=$r['bname']; } ?>
              <option value="<?= $r['id'] ?>">Floor <?= $r['floor_number'] ?> / Room <?= e($r['room_number']) ?><?= $r['label']?' — '.e($r['label']):'' ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label>Serial No.</label>
          <input type="text" name="serial_no" id="fSerial" maxlength="100" placeholder="SN-XXXXXXX">
        </div>
        <div>
          <label>Asset Code / Barcode</label>
          <p class="muted">Generated automatically when the asset is saved. Print its sticker from the Assets list.</p>
        </div>
        <div>
          <label>Property No.</label>
          <input type="text" name="property_no" id="fProp" maxlength="100" placeholder="PROP-XXXX">
        </div>
        <div>
          <label>Quantity</label>
          <input type="number" name="quantity" id="fQty" min="1" max="9999" value="1">
        </div>
        <div>
          <label>Unit Cost (₱)</label>
          <input type="number" name="unit_cost" id="fCost" step="0.01" min="0" value="0.00">
        </div>
        <div>
          <label>Date Acquired</label>
          <input type="date" name="acquired_date" id="fDate">
        </div>
        <div>
          <label>Condition</label>
          <select name="condition" id="fCond" class="select-field">
            <option value="good">Good</option>
            <option value="fair">Fair</option>
            <option value="poor">Poor</option>
          </select>
        </div>
        <div>
          <label>Status</label>
          <select name="status" id="fStatus" class="select-field">
            <option value="active">Active</option>
            <option value="under_repair">Under Repair</option>
            <option value="reserved">Reserved</option>
            <option value="disposed">Disposed</option>
          </select>
        </div>
      </div>

      <label>Description</label>
      <textarea name="description" id="fDesc" rows="3" placeholder="Asset description…"></textarea>

      <label>Photo <span class="gray">(max 5MB)</span></label>
      <input type="file" name="photo" id="fPhoto" accept="image/*">
      <img id="fPhotoPreview" src="" alt="" style="display:none;max-height:100px;margin-top:8px;border-radius:6px">

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-gold" id="modalSubmitBtn">Add Asset</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
function closeOnBg(e,id){ if(e.target.id===id) closeModal(id); }

function editItem(d){
  document.getElementById('modalTitle').textContent='✏️ Edit Asset';
  document.getElementById('formAction').value='edit';
  document.getElementById('formItemId').value=d.id;
  document.getElementById('fName').value=d.name||'';
  document.getElementById('fCat').value=d.category_id||'';
  document.getElementById('fRoom').value=d.room_id||'';
  document.getElementById('fSerial').value=d.serial_no||'';
  document.getElementById('fProp').value=d.property_no||'';
  document.getElementById('fQty').value=d.quantity||1;
  document.getElementById('fCost').value=d.unit_cost||0;
  document.getElementById('fDate').value=d.acquired_date||'';
  document.getElementById('fCond').value=d.condition||'good';
  document.getElementById('fStatus').value=d.status||'active';
  document.getElementById('fDesc').value=d.description||'';
  document.getElementById('modalSubmitBtn').textContent='Update Asset';
  if(d.photo){ const p=document.getElementById('fPhotoPreview'); p.src='uploads/'+d.photo; p.style.display='block'; }
  openModal('addModal');
}

// Photo preview
document.getElementById('fPhoto').addEventListener('change',function(){
  const p=document.getElementById('fPhotoPreview');
  if(this.files[0]){ const r=new FileReader(); r.onload=e=>{ p.src=e.target.result; p.style.display='block'; }; r.readAsDataURL(this.files[0]); }
});
</script>
<?php endif; ?>
<?php page_bottom($fabType); ?>
