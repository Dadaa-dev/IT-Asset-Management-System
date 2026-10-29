<?php
/** report_concern.php — Students & Admins submit concerns about assets */
require 'config.php';
require_login();

$uid     = $_SESSION['user_id'];
$isAdmin = is_admin();
$prefill_item = (int)($_GET['item_id'] ?? 0);
$scan_code = strtoupper(trim($_GET['code'] ?? ''));
$error = '';

// Resolve a scanned or manually entered sticker code to its asset.
$prefill_data = null;
if ($scan_code !== '') {
    $s = $conn->prepare('SELECT id FROM items WHERE asset_code=?');
    $s->bind_param('s', $scan_code);
    $s->execute();
    $matched = $s->get_result()->fetch_assoc();
    if ($matched) {
        $prefill_item = (int)$matched['id'];
    } else {
        $error = 'No asset was found for that sticker code. Check the code and try again.';
        $prefill_item = 0;
    }
}
if ($prefill_item) {
    $s = $conn->prepare('SELECT i.id, i.name, i.asset_code, i.serial_no, r.room_number, f.floor_number, b.name AS bname FROM items i JOIN rooms r ON r.id=i.room_id JOIN floors f ON f.id=r.floor_id JOIN buildings b ON b.id=f.building_id WHERE i.id=?');
    $s->bind_param('i',$prefill_item);
    $s->execute();
    $prefill_data = $s->get_result()->fetch_assoc();
    if (!$prefill_data && !$error) $error = 'The selected asset could not be found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $item_id = (int)($_POST['item_id'] ?? 0) ?: null;
    $room_id = null;
    $title   = trim($_POST['title'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $prio    = in_array($_POST['priority']??'',['low','medium','high','urgent']) ? $_POST['priority'] : 'medium';

    if (!$title || !$desc) { $error = 'Title and description are required.'; }
    else {
        if ($item_id) {
            $assetStmt = $conn->prepare('SELECT room_id FROM items WHERE id=?');
            $assetStmt->bind_param('i', $item_id);
            $assetStmt->execute();
            $asset = $assetStmt->get_result()->fetch_assoc();
            if (!$asset) {
                $error = 'The selected asset could not be found.';
            } else {
                $room_id = (int)$asset['room_id'];
            }
        }
    }
    if (!$error && $title && $desc) {
        $stmt = $conn->prepare('INSERT INTO concerns (item_id,room_id,user_id,title,description,priority) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('iiisss',$item_id,$room_id,$uid,$title,$desc,$prio);
        $stmt->execute();
        flash('Your concern has been submitted successfully. IT Staff will review it.','success');
        header('Location: my_concerns.php'); exit;
    }
}

// Asset list for dropdown
$asset_list = $conn->query('SELECT i.id, i.name, i.asset_code, r.room_number, b.name AS bname FROM items i JOIN rooms r ON r.id=i.room_id JOIN floors f ON f.id=r.floor_id JOIN buildings b ON b.id=f.building_id ORDER BY b.name,i.name');

page_top('Scan & Report', ($isAdmin?'admin_concerns.php':'my_concerns.php'), ($isAdmin?'concerns':'scan'));
?>
<section class="welcome">
  <h1>📷 Scan &amp; <span class="gold">Report</span></h1>
  <p class="muted">Scan an AssetVault Code 39 sticker or enter its code to report an issue with that asset.</p>
</section>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<section class="panel scanner-panel" style="max-width:680px;margin-bottom:20px">
  <h2>Find an asset sticker</h2>
  <div class="scanner-actions">
    <button type="button" class="btn btn-ghost" id="startScanner">Use Camera</button>
    <button type="button" class="btn btn-ghost" id="stopScanner" hidden>Stop Camera</button>
  </div>
  <video id="barcodeVideo" class="barcode-video" playsinline muted hidden></video>
  <p id="scannerMessage" class="muted" aria-live="polite" style="margin-top:10px">Camera scanning needs HTTPS (or localhost). Fill the camera view with the sticker; you can always type its code instead.</p>
  <form method="get" action="report_concern.php" class="barcode-lookup">
    <label for="barcodeCode">Asset code</label>
    <input id="barcodeCode" name="code" value="<?= e($scan_code) ?>" placeholder="e.g. AV0123456789ABCDEF" autocomplete="off">
    <button class="btn btn-gold" type="submit">Find Asset</button>
  </form>
</section>

<script src="barcode.js"></script>
<script>
(function () {
  const video = document.getElementById('barcodeVideo');
  const start = document.getElementById('startScanner');
  const stop = document.getElementById('stopScanner');
  const message = document.getElementById('scannerMessage');
  const input = document.getElementById('barcodeCode');
  const form = input.form;
  let stopCamera = function () {};

  start.addEventListener('click', async function () {
    start.disabled = true;
    message.textContent = 'Starting camera…';
    stopCamera = await AssetBarcode.scan(video, function (code) {
      input.value = code;
      form.requestSubmit();
    }, function (text) {
      message.textContent = text;
    });
    video.hidden = false;
    stop.hidden = false;
    start.disabled = false;
  });
  stop.addEventListener('click', function () {
    stopCamera();
    video.hidden = true;
    stop.hidden = true;
    message.textContent = 'Camera stopped. Enter a sticker code or start scanning again.';
  });
  window.addEventListener('pagehide', function () { stopCamera(); });
})();
</script>

<div class="panel" style="max-width:680px">
  <?php if ($prefill_data): ?>
    <div class="alert alert-success">
      <strong><?= e($prefill_data['name']) ?></strong> · <?= e($prefill_data['asset_code']) ?><br>
      <?= e($prefill_data['bname']) ?> · Floor <?= (int)$prefill_data['floor_number'] ?> · Room <?= e($prefill_data['room_number']) ?>
    </div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <label>Related Asset <span class="gray">(optional)</span></label>
    <select name="item_id" class="select-field" id="assetSelect">
      <option value="">— Not specific to one asset —</option>
      <?php while($a=$asset_list->fetch_assoc()): ?>
        <option value="<?= $a['id'] ?>" <?= $prefill_item==$a['id']?'selected':'' ?>>
          <?= e($a['name']) ?> [<?= e($a['asset_code']) ?>] (<?= e($a['bname']) ?> / Rm <?= e($a['room_number']) ?>)
        </option>
      <?php endwhile; ?>
    </select>

    <label>Concern Title *</label>
    <input type="text" name="title" maxlength="200" placeholder="e.g. Keyboard not working in Lab 1"
           value="<?= e($_POST['title']??($prefill_data?'Issue with: '.$prefill_data['name']:'')) ?>" required>

    <label>Description *</label>
    <textarea name="description" rows="5" placeholder="Describe the problem in detail…" required><?= e($_POST['description']??'') ?></textarea>

    <label>Priority</label>
    <select name="priority" class="select-field">
      <option value="low"    <?= ($_POST['priority']??'')==='low'   ?'selected':'' ?>>🟢 Low</option>
      <option value="medium" <?= ($_POST['priority']??'medium')==='medium'?'selected':'' ?>>🟡 Medium</option>
      <option value="high"   <?= ($_POST['priority']??'')==='high'  ?'selected':'' ?>>🟠 High</option>
      <option value="urgent" <?= ($_POST['priority']??'')==='urgent'?'selected':'' ?>>🔴 Urgent</option>
    </select>

    <button type="submit" class="btn btn-gold" style="margin-top:20px">Submit Concern</button>
  </form>
</div>
<?php page_bottom($isAdmin ? 'admin' : 'student'); ?>
