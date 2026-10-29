<?php
/**
 * Public QR landing page. Students/teachers scan a sticker URL like:
 *   /r.php?tag=IT-KENJI-002
 * No app install required. Optional Google Form is an alternate to the PWA.
 */
require 'config.php';

$tag = strtoupper(trim($_GET['tag'] ?? $_GET['code'] ?? ''));
$error = '';
$ok = '';
$asset = null;

if ($tag !== '') {
    $stmt = $conn->prepare(
        'SELECT i.id, i.name, i.asset_code, i.serial_no, i.property_no, i.description,
                r.room_number, r.label AS room_label, f.floor_number, b.name AS building_name
         FROM items i
         JOIN rooms r ON r.id = i.room_id
         JOIN floors f ON f.id = r.floor_id
         JOIN buildings b ON b.id = f.building_id
         WHERE i.asset_code = ?'
    );
    $stmt->bind_param('s', $tag);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    if (!$asset) $error = 'No asset found for tag ' . $tag . '. Check the sticker and try again.';
} else {
    $error = 'Missing asset tag. Scan the QR code printed on the equipment sticker.';
}

$gform = $asset ? google_form_url_for_asset($asset) : '';

// Direct Redirect (Option A): If Google Form is configured, jump directly to it!
// (Add ?local=1 to URL if you ever need to view the built-in form instead)
$directRedirect = setting('google_form_direct', '1');
if ($asset && $gform && $directRedirect === '1' && !isset($_GET['local']) && !isset($_GET['preview'])) {
    header('Location: ' . $gform);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $asset) {
    csrf_check();
    $reporter = trim($_POST['reporter_name'] ?? '');
    $email    = strtolower(trim($_POST['reporter_email'] ?? ''));
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $prio     = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent'], true) ? $_POST['priority'] : 'medium';

    if ($title === '' || $desc === '' || $reporter === '') {
        $error = 'Name, title, and description are required.';
    } else {
        $full = $desc . "\n\nReported by: $reporter"
            . ($email !== '' ? " ($email)" : '')
            . "\nAsset tag: " . $asset['asset_code']
            . "\nSource: QR sticker / public form";
        $uid = public_report_user_id();
        $itemId = (int)$asset['id'];
        $roomStmt = $conn->prepare('SELECT room_id FROM items WHERE id=?');
        $roomStmt->bind_param('i', $itemId);
        $roomStmt->execute();
        $roomId = (int)$roomStmt->get_result()->fetch_assoc()['room_id'];
        $stmt = $conn->prepare('INSERT INTO concerns (item_id, room_id, user_id, title, description, priority) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('iiisss', $itemId, $roomId, $uid, $title, $full, $prio);
        $stmt->execute();
        $ok = 'Concern submitted. IT staff will review it.';
        if ($gform) {
            header('Location: ' . $gform);
            exit;
        }
    }
}

$loc = $asset
    ? ($asset['building_name'] . ' · Floor ' . (int)$asset['floor_number'] . ' · ' . ($asset['room_label'] ?: ('Room ' . $asset['room_number'])))
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Report asset <?= $asset ? e($asset['asset_code']) : '' ?> | AssetVault</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body class="gform-body">
  <div class="gform-card">
    <p class="gform-kicker">SPIST AssetVault</p>
    <h1>Report a problem</h1>
    <p class="muted">Scan-to-report form. You do not need to install the app.</p>

    <?php if ($asset): ?>
      <div class="gform-asset">
        <div><strong><?= e($asset['name']) ?></strong></div>
        <div class="gform-tag"><?= e($asset['asset_code']) ?></div>
        <div class="muted"><?= e($loc) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>

    <?php if ($asset && !$ok): ?>
      <?php if ($gform): ?>
        <a class="btn btn-gold btn-block" href="<?= e($gform) ?>">Open Google Form (pre-filled tag)</a>
        <p class="muted" style="text-align:center;margin:12px 0">or fill this short form</p>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Your name *</label>
        <input type="text" name="reporter_name" required placeholder="e.g. Juan Dela Cruz">
        <label>School email</label>
        <input type="email" name="reporter_email" placeholder="name@spist.edu.ph">
        <label>Problem title *</label>
        <input type="text" name="title" required maxlength="200" value="Issue with <?= e($asset['name']) ?>">
        <label>What is wrong? *</label>
        <textarea name="description" rows="4" required placeholder="Describe the problem..."></textarea>
        <label>Priority</label>
        <select name="priority" class="select-field">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="urgent">Urgent</option>
        </select>
        <button class="btn btn-gold btn-block" type="submit" style="margin-top:18px">Submit report</button>
      </form>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id'])): ?>
      <p class="auth-link"><a href="report_concern.php?tag=<?= e(urlencode($tag)) ?>">Open in AssetVault instead</a></p>
    <?php else: ?>
      <p class="auth-link"><a href="login.php">Staff / student login</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
