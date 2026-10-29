<?php
session_start();

// ─── Database ────────────────────────────────────────────────────────────────
$conn = new mysqli('localhost', 'root', '', 'capstone_system');
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

if (file_exists(__DIR__ . '/constants.php')) {
    require_once __DIR__ . '/constants.php';
}

// ─── Firebase / Google config ─────────────────────────────────────────────────
define('FIREBASE_API_KEY',       'AIzaSyAndEQ8njY_9uRyKSyq3sxVQrIXfumi8Rs');
define('FIREBASE_AUTH_DOMAIN',   'assetmanagement-dde44.firebaseapp.com');
define('FIREBASE_PROJECT_ID',    'assetmanagement-dde44');

// ─── Allowed school email domain ──────────────────────────────────────────────
define('ALLOWED_DOMAIN', 'spist.edu.ph');

// ─── Role helpers ─────────────────────────────────────────────────────────────
function role_label($role) {
    return $role === 'admin' ? 'Facilitator / IT Staff' : 'Student / Teacher';
}
function is_admin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function ensure_settings_table() {
    static $done = false;
    if ($done) return;
    global $conn;
    $conn->query("CREATE TABLE IF NOT EXISTS app_settings (
        k VARCHAR(80) PRIMARY KEY,
        v TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

function setting($key, $default = '') {
    global $conn;
    static $cache = null;
    ensure_settings_table();
    if ($cache === null) {
        $cache = [];
        $q = $conn->query('SELECT k, v FROM app_settings');
        if ($q) {
            while ($r = $q->fetch_assoc()) $cache[$r['k']] = $r['v'];
        }
    }
    $val = $cache[$key] ?? $default;
    return ($val === null || $val === '') ? $default : $val;
}

function save_setting($key, $value) {
    global $conn;
    ensure_settings_table();
    $stmt = $conn->prepare('INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

function app_base_url() {
    $saved = setting('app_base_url');
    if ($saved) return rtrim($saved, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/asset_management/index.php');
    $dir = rtrim(dirname($script), '/');
    if ($dir === '/' || $dir === '\\' || $dir === '.') $dir = '';
    return $scheme . '://' . $host . $dir;
}

function asset_report_url($tag) {
    return app_base_url() . '/r.php?tag=' . rawurlencode($tag);
}

function google_form_url_for_asset($asset) {
    $base = trim(setting('google_form_url'));
    if ($base === '' && defined('GOOGLE_FORM_BASE')) {
        $cBase = trim(GOOGLE_FORM_BASE);
        if (!str_contains($cBase, 'PLACEHOLDER_FORM_ID')) {
            $base = $cBase;
        }
    }
    if ($base === '' || str_contains($base, 'PLACEHOLDER_FORM_ID')) return '';

    // Strip existing usp=pp_url or query string from base if user pasted full pre-fill link
    if (str_contains($base, '?')) {
        $base = strtok($base, '?');
    }

    $params = [];
    $map = [
        'google_form_entry_tag'      => $asset['asset_code'] ?? '',
        'google_form_entry_name'     => $asset['name'] ?? '',
        'google_form_entry_location' => trim(($asset['building_name'] ?? '') . ' · Floor ' . ($asset['floor_number'] ?? '') . ' · ' . ($asset['room_label'] ?: ('Room ' . ($asset['room_number'] ?? '')))),
    ];

    foreach ($map as $settingKey => $value) {
        $entry = trim(setting($settingKey));
        if ($entry === '') {
            if ($settingKey === 'google_form_entry_tag' && defined('FORM_FIELD_ASSET_ID') && !str_contains(FORM_FIELD_ASSET_ID, '111111111')) {
                $entry = FORM_FIELD_ASSET_ID;
            } elseif ($settingKey === 'google_form_entry_name' && defined('FORM_FIELD_ASSET_NAME') && !str_contains(FORM_FIELD_ASSET_NAME, '222222222')) {
                $entry = FORM_FIELD_ASSET_NAME;
            } elseif ($settingKey === 'google_form_entry_location' && defined('FORM_FIELD_CONCERN') && !str_contains(FORM_FIELD_CONCERN, '333333333')) {
                $entry = FORM_FIELD_CONCERN;
            }
        }
        if ($entry !== '' && $value !== '') {
            // Strip leading entry. if user provided it or didn't
            $key = str_starts_with($entry, 'entry.') ? $entry : ('entry.' . $entry);
            $params[$key] = $value;
        }
    }

    $sep = str_contains($base, '?') ? '&' : '?';
    return $base . $sep . 'usp=pp_url' . ($params ? ('&' . http_build_query($params)) : '');
}

function public_report_user_id() {
    global $conn;
    $email = 'reports@spist.edu.ph';
    $row = $conn->query("SELECT id FROM users WHERE gmail='" . $conn->real_escape_string($email) . "'")->fetch_assoc();
    if ($row) return (int)$row['id'];
    $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $name = 'QR / Google Form Reports';
    $ins = $conn->prepare("INSERT INTO users (gmail, password, full_name, auth_provider, role) VALUES (?,?,?,'local','staff')");
    $ins->bind_param('sss', $email, $hash, $name);
    $ins->execute();
    return (int)$conn->insert_id;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        flash('Access denied. Facilitator / IT Staff only.', 'error');
        header('Location: dashboard.php');
        exit;
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check() {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        die('Invalid request.');
    }
}

function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

// ─── Page layout ──────────────────────────────────────────────────────────────
function page_top($title, $back = null, $active = '', $body_class = '') {
    $gmail   = $_SESSION['gmail']  ?? '';
    $avatar  = $_SESSION['google_avatar'] ?? '';
    $role    = $_SESSION['role'] ?? 'staff';
    $initial = $gmail !== '' ? strtoupper(substr($gmail, 0, 1)) : '?';
    $isAdmin = is_admin();
    $roleLabel = role_label($role);

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . e($title) . ' | AssetVault</title>
    <meta name="theme-color" content="#14532d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="manifest.json">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{gold:"#c9a227",forest:"#14532d",ink:"#0f172a"}}}}</script>
    <link rel="stylesheet" href="style.css"></head>
    <body class="app-body">
    <div class="app-shell">

      <aside class="sidebar">
        <div class="sidebar-brand">
          <img src="asset/logo.png" alt="AssetVault" class="brand-logo" onerror="this.style.display=\'none\'">
          <div>
            <div class="brand-name">ASSET<span>VAULT</span></div>
            <div class="brand-tag">SPIST asset tracking</div>
          </div>
        </div>

        <button class="menu-toggle" onclick="document.querySelector(\'.app-shell\').classList.toggle(\'sidebar-open\')">Menu</button>

        <nav class="side-nav">
          <a class="side-link ' . ($active === 'home' ? 'active' : '') . '" href="dashboard.php">Home</a>';

    if ($isAdmin) {
        echo '
          <a class="side-link ' . ($active === 'assets' ? 'active' : '') . '" href="assets.php">Assets</a>
          <a class="side-link ' . ($active === 'reports' ? 'active' : '') . '" href="reports.php">Reports</a>
          <a class="side-link ' . ($active === 'concerns' ? 'active' : '') . '" href="admin_concerns.php">Concerns</a>
          <a class="side-link ' . ($active === 'risk' ? 'active' : '') . '" href="risk.php">Risk</a>
          <a class="side-link ' . ($active === 'maintenance' ? 'active' : '') . '" href="maintenance.php">Maintenance</a>
          <a class="side-link ' . ($active === 'admin' ? 'active' : '') . '" href="admin_users.php">Users</a>';
    } else {
        echo '
          <a class="side-link ' . ($active === 'assets' ? 'active' : '') . '" href="assets.php">Assets</a>
          <a class="side-link ' . ($active === 'my_concerns' ? 'active' : '') . '" href="my_concerns.php">My Concerns</a>
          <a class="side-link ' . ($active === 'scan' ? 'active' : '') . '" href="report_concern.php">Scan &amp; Report</a>';
    }

    echo '
          <a class="side-link ' . ($active === 'account' ? 'active' : '') . '" href="account_settings.php">Settings</a>
        </nav>

        <div class="sidebar-footer">
          <div class="sidebar-role-badge">' . e($roleLabel) . '</div>
          <div class="sidebar-quote">Main Building inventory is stored in this system.</div>
          <a class="btn btn-gold sidebar-logout" href="logout.php">Logout</a>
        </div>
      </aside>

      <div class="main-area ' . e($body_class) . '">
        <header class="topbar">
          <span class="topbar-title">' . e($title) . '</span>
          <div class="user-area">
            <span class="user-mail">' . e($gmail) . '</span>
            <span class="role-chip">' . e($roleLabel) . '</span>';

    if ($avatar) {
        echo '<img src="' . e($avatar) . '" class="avatar avatar-photo" alt="avatar">';
    } else {
        echo '<div class="avatar">' . e($initial) . '</div>';
    }

    echo '    </div>
        </header>
        <main class="container">';

    if ($back) {
        echo '<a class="btn btn-ghost back" href="' . e($back) . '">&larr; Back</a>';
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
        unset($_SESSION['flash']);
    }
}

function page_bottom($fab_type = '') {
    // Floating Action Bar
    $fab = '';
    if ($fab_type === 'admin') {
        $fab = '
    <div class="fab-wrap" id="fabWrap">
      <button class="fab-main" id="fabBtn" onclick="toggleFab()" title="Quick Actions">
        <span id="fabIcon">＋</span>
      </button>
      <div class="fab-menu" id="fabMenu">
        <a class="fab-item" href="assets.php">Manage Assets</a>
        <a class="fab-item" href="admin_concerns.php">View Concerns</a>
        <a class="fab-item" href="risk.php">Risk Report</a>
        <a class="fab-item" href="reports.php">Reports</a>
      </div>
    </div>';
    } elseif ($fab_type === 'student') {
        $fab = '
    <div class="fab-wrap" id="fabWrap">
      <button class="fab-main fab-student" id="fabBtn" onclick="toggleFab()" title="Quick Actions">
        <span id="fabIcon">＋</span>
      </button>
      <div class="fab-menu" id="fabMenu">
        <a class="fab-item" href="report_concern.php">Report a Concern</a>
        <a class="fab-item" href="my_concerns.php">My Concerns</a>
        <a class="fab-item" href="assets.php">Browse Assets</a>
      </div>
    </div>';
    }

    echo '</main></div></div>
    ' . $fab . '
    <script>
    window.assetVaultInstallPrompt = null;
    window.addEventListener("beforeinstallprompt", function (event) {
      event.preventDefault();
      window.assetVaultInstallPrompt = event;
    });
    window.addEventListener("appinstalled", function () {
      window.assetVaultInstallPrompt = null;
    });
    if ("serviceWorker" in navigator) {
      window.addEventListener("load", function () {
        navigator.serviceWorker.register("sw.js").catch(function (error) {
          console.error("AssetVault service worker registration failed:", error);
        });
      });
    }
    function toggleFab() {
      const menu = document.getElementById("fabMenu");
      const icon = document.getElementById("fabIcon");
      const btn  = document.getElementById("fabBtn");
      const open = menu.classList.toggle("fab-open");
      icon.textContent = open ? "✕" : "＋";
      btn.classList.toggle("fab-active", open);
    }
    document.addEventListener("click", function(e) {
      const wrap = document.getElementById("fabWrap");
      if (wrap && !wrap.contains(e.target)) {
        const menu = document.getElementById("fabMenu");
        const icon = document.getElementById("fabIcon");
        const btn  = document.getElementById("fabBtn");
        if (menu) { menu.classList.remove("fab-open"); icon.textContent="＋"; btn.classList.remove("fab-active"); }
      }
    });
    </script>
    </body></html>';
}