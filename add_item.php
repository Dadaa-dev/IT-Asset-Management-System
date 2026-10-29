<?php
require 'config.php';
require_login();

// ── Handle Add Item ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action'] ?? '';
    $room_id = (int)($_POST['room_id'] ?? 0);

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $serial   = trim($_POST['serial_no'] ?? '');
        $qty      = max(1, (int)($_POST['quantity'] ?? 1));
        $cond     = in_array($_POST['condition'] ?? '', ['good','fair','poor']) ? $_POST['condition'] : 'good';
        $photo    = null;

        if (!$name) { flash('Item name is required.', 'error'); }
        else {
            // Handle photo upload
            if (!empty($_FILES['photo']['name'])) {
                $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (!in_array($ext, $allowed)) {
                    flash('Photo must be jpg, png, gif, or webp.', 'error');
                    header('Location: room.php?id=' . $room_id); exit;
                }
                if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                    flash('Photo must be under 5 MB.', 'error');
                    header('Location: room.php?id=' . $room_id); exit;
                }
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = uniqid('img_') . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename);
                $photo = $filename;
            }

            $uid  = $_SESSION['user_id'];
            $assetCode = 'AV' . strtoupper(bin2hex(random_bytes(8)));
            $stmt = $conn->prepare(
                'INSERT INTO items (room_id, name, description, category, serial_no, asset_code, quantity, `condition`, photo, added_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isssssissi', $room_id, $name, $desc, $category, $serial, $assetCode, $qty, $cond, $photo, $uid);
            $stmt->execute();
            $item_id = $conn->insert_id;

            // Audit log
            $log = $conn->prepare('INSERT INTO item_logs (item_id, user_id, action, note) VALUES (?,?,?,?)');
            $note = "Added: $name";
            $act  = 'add';
            $log->bind_param('iiss', $item_id, $uid, $act, $note);
            $log->execute();

            flash("Item added successfully.");
        }
    } elseif ($action === 'delete') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        // Get photo filename before deleting
        $ph = $conn->query("SELECT photo, name FROM items WHERE id = $item_id")->fetch_assoc();
        $conn->query("DELETE FROM items WHERE id = $item_id");

        if ($ph && $ph['photo']) {
            @unlink(__DIR__ . '/uploads/' . $ph['photo']);
        }

        // Audit log
        $uid  = $_SESSION['user_id'];
        $log  = $conn->prepare('INSERT INTO item_logs (item_id, user_id, action, note) VALUES (?,?,?,?)');
        $act  = 'delete';
        $note = 'Deleted: ' . ($ph['name'] ?? 'item');
        $log->bind_param('iiss', $item_id, $uid, $act, $note);
        $log->execute();

        flash('Item deleted.');
    }

    header('Location: room.php?id=' . $room_id);
    exit;
}

header('Location: dashboard.php');
exit;
