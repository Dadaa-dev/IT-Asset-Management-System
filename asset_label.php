<?php
require 'config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare(
    'SELECT i.id, i.name, i.serial_no, i.property_no, i.asset_code, r.room_number, r.label AS room_label,
            f.floor_number, b.name AS building_name
     FROM items i
     JOIN rooms r ON r.id=i.room_id
     JOIN floors f ON f.id=r.floor_id
     JOIN buildings b ON b.id=f.building_id
     WHERE i.id=?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
if (!$asset) {
    http_response_code(404);
    exit('Asset not found.');
}

$reportUrl = asset_report_url($asset['asset_code']);
$gform = google_form_url_for_asset($asset);
page_top('Print Asset Sticker', 'assets.php', 'assets');
?>
<section class="welcome no-print">
  <h1>Print asset sticker</h1>
  <p class="muted">QR opens a report URL that already includes this permanent asset tag. Students and teachers can report without installing the PWA. Use Google Form as the alternate if it is configured in Settings.</p>
  <p class="muted">Sticker URL: <code><?= e($reportUrl) ?></code></p>
</section>

<div class="asset-sticker">
  <small class="sticker-brand">SPIST AssetVault</small>
  <strong class="sticker-name"><?= e($asset['name']) ?></strong>
  <div id="assetQr" class="asset-qr"></div>
  <strong class="sticker-code"><?= e($asset['asset_code']) ?></strong>
  <small>
    <?= e($asset['building_name']) ?> · Fl <?= (int)$asset['floor_number'] ?> · <?= e($asset['room_label'] ?: ('Rm ' . $asset['room_number'])) ?>
  </small>
  <small>Scan QR to report a problem</small>
</div>

<div class="no-print" style="margin-top:20px">
  <button type="button" class="btn btn-gold" onclick="window.print()">Print sticker</button>
  <?php if ($gform): ?>
    <a class="btn btn-ghost" href="<?= e($gform) ?>" target="_blank" rel="noopener">Preview Google Form</a>
  <?php endif; ?>
  <a class="btn btn-ghost" href="assets.php">Back to assets</a>
</div>
<script src="qrcode.min.js"></script>
<script>
(function () {
  var url = <?= json_encode($reportUrl) ?>;
  var qr = qrcode(0, 'M');
  qr.addData(url);
  qr.make();
  document.getElementById('assetQr').innerHTML = qr.createSvgTag(4, 0);
})();
</script>
<?php page_bottom('admin'); ?>
