<?php
/**
 * account_settings.php – Change password (local users) + view profile info
 */
require 'config.php';
require_login();

$uid  = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id = $uid")->fetch_assoc();
$isGoogle = $user['auth_provider'] === 'google';
$isAdmin  = is_admin();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isGoogle && ($_POST['action'] ?? '') !== 'save_gform') {
    csrf_check();
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $error = 'New password must be at least 8 characters with 1 uppercase and 1 number.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $uid);
        $stmt->execute();
        $success = 'Password updated successfully!';
    }
}

page_top('Account Settings', null, 'account');
?>
<section class="welcome">
  <h1>Account <span class="gold">Settings</span></h1>
</section>

<!-- Profile card -->
<div class="panel" style="max-width:500px;margin-bottom:24px">
  <h2>Profile</h2>
  <div class="profile-row">
    <?php if ($user['google_avatar']): ?>
      <img src="<?= e($user['google_avatar']) ?>" class="profile-avatar-lg" alt="avatar">
    <?php else: ?>
      <div class="avatar avatar-lg"><?= strtoupper(substr($user['gmail'], 0, 1)) ?></div>
    <?php endif; ?>
    <div>
      <p><strong><?= e($user['full_name'] ?: $user['gmail']) ?></strong></p>
      <p class="muted"><?= e($user['gmail']) ?></p>
      <p class="muted">Role: <span class="badge"><?= ucfirst($user['role']) ?></span></p>
      <p class="muted">Auth: <?= $isGoogle ? '🔵 Google Sign-In' : '🔐 Email / Password' ?></p>
      <?php if ($user['last_login']): ?>
        <p class="muted">Last login: <?= date('M j, Y g:i a', strtotime($user['last_login'])) ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="panel" style="max-width:500px;margin-bottom:24px">
  <h2>AssetVault PWA</h2>
  <p class="muted" style="margin:10px 0">Install the app on your phone to scan asset stickers and submit reports. Camera scanning requires HTTPS (or localhost) and permission to use your camera.</p>
  <a class="btn btn-ghost" href="report_concern.php">📷 Scan &amp; Report</a>
  <button class="btn btn-gold" type="button" id="installPwaButton" style="margin-left:6px">Install App</button>
  <p id="installPwaMessage" class="muted" aria-live="polite" style="margin-top:10px">If the install button is unavailable, use your browser menu and choose “Install app” or “Add to Home Screen”.</p>
</div>

<?php if ($isGoogle): ?>
  <div class="alert alert-success" style="max-width:500px">
    Your account uses Google Sign-In. Password management is handled by Google.
  </div>
<?php else: ?>
  <!-- Change password -->
  <div class="panel" style="max-width:500px">
    <h2>Change Password</h2>
    <?php if ($error):  ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success):?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Current Password</label>
      <input type="password" name="current_password" required>
      <label>New Password</label>
      <input type="password" name="new_password" id="np" required
             placeholder="Min 8 chars, 1 uppercase, 1 number">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required placeholder="Repeat new password">
      <button type="submit" class="btn btn-gold" style="margin-top:16px">Update Password</button>
    </form>
  </div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- ── Google Form Integration (admin only) ────────────────── -->
<?php
  $gf_saved = false;
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_gform') {
      csrf_check();
      save_setting('google_form_url',            trim($_POST['google_form_url'] ?? ''));
      save_setting('google_form_entry_tag',       trim($_POST['google_form_entry_tag'] ?? ''));
      save_setting('google_form_entry_name',      trim($_POST['google_form_entry_name'] ?? ''));
      save_setting('google_form_entry_location',  trim($_POST['google_form_entry_location'] ?? ''));
      save_setting('google_form_direct',          isset($_POST['google_form_direct']) ? '1' : '0');
      $gf_saved = true;
  }
  $directSetting = setting('google_form_direct', '1');
?>
<div class="panel" style="max-width:600px;margin-top:20px">
  <h2>📋 Google Form Integration (Option A: Direct Redirect)</h2>
  <p class="muted" style="margin:8px 0 14px">
    Configure your school's Google Form. When a student or teacher scans an asset QR sticker, their phone will
    <strong>instantly jump directly to the Google Form</strong> with the asset tag, name, and room pre-filled!
  </p>
  <?php if ($gf_saved): ?><div class="alert alert-success">Google Form settings saved.</div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_gform">

    <label style="display:flex;align-items:center;gap:10px;margin-bottom:14px;cursor:pointer">
      <input type="checkbox" name="google_form_direct" value="1" <?= $directSetting === '1' ? 'checked' : '' ?> style="width:18px;height:18px">
      <span><strong>Direct Redirect (Option A)</strong> — Open Google Form immediately upon scanning</span>
    </label>

    <label>Google Form URL <span class="gray">(up to and including <code>viewform</code>)</span></label>
    <input type="url" name="google_form_url" placeholder="https://docs.google.com/forms/d/e/1FAIpQLSc.../viewform"
           value="<?= e(setting('google_form_url') ?: (defined('GOOGLE_FORM_BASE') && !str_contains(GOOGLE_FORM_BASE,'PLACEHOLDER') ? GOOGLE_FORM_BASE : '')) ?>">
    <p class="gray" style="margin:4px 0 10px">Example: <code>https://docs.google.com/forms/d/e/1FAIpQLSc.../viewform</code></p>

    <label>Entry ID — Asset Tag / Code <span class="gray">(e.g. <code>entry.123456789</code>)</span></label>
    <input type="text" name="google_form_entry_tag" placeholder="entry.123456789"
           value="<?= e(setting('google_form_entry_tag') ?: (defined('FORM_FIELD_ASSET_ID') && !str_contains(FORM_FIELD_ASSET_ID,'111111111') ? FORM_FIELD_ASSET_ID : '')) ?>">

    <label>Entry ID — Asset Name <span class="gray">(e.g. <code>entry.987654321</code>)</span></label>
    <input type="text" name="google_form_entry_name" placeholder="entry.987654321"
           value="<?= e(setting('google_form_entry_name') ?: (defined('FORM_FIELD_ASSET_NAME') && !str_contains(FORM_FIELD_ASSET_NAME,'222222222') ? FORM_FIELD_ASSET_NAME : '')) ?>">

    <label>Entry ID — Location <span class="gray">(e.g. <code>entry.112233445</code>)</span></label>
    <input type="text" name="google_form_entry_location" placeholder="entry.112233445"
           value="<?= e(setting('google_form_entry_location') ?: (defined('FORM_FIELD_CONCERN') && !str_contains(FORM_FIELD_CONCERN,'333333333') ? FORM_FIELD_CONCERN : '')) ?>">

    <button type="submit" class="btn btn-gold" style="margin-top:16px">Save Google Form Settings</button>
  </form>

  <?php if (setting('google_form_url') || (defined('GOOGLE_FORM_BASE') && !str_contains(GOOGLE_FORM_BASE,'PLACEHOLDER'))): ?>
  <div style="margin-top:16px;padding:12px 16px;background:rgba(31,77,54,.2);border:1px solid var(--forest);border-radius:10px">
    <strong style="color:var(--gold)">⚡ Option A Active: Direct Scan-to-Google Form</strong>
    <p class="muted" style="margin-top:6px">
      Scanning any sticker QR code will immediately launch the Google Form on the user's phone, pre-filled with the asset's Tag, Name, and Location.
    </p>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>
document.getElementById('installPwaButton').addEventListener('click', async function () {
  const message = document.getElementById('installPwaMessage');
  if (!window.assetVaultInstallPrompt) {
    message.textContent = 'This browser has not offered installation. Use its menu to choose “Install app” or “Add to Home Screen”.';
    return;
  }
  window.assetVaultInstallPrompt.prompt();
  const choice = await window.assetVaultInstallPrompt.userChoice;
  message.textContent = choice.outcome === 'accepted' ? 'AssetVault was installed.' : 'Installation was cancelled.';
  window.assetVaultInstallPrompt = null;
});
</script>
<?php page_bottom(); ?>
