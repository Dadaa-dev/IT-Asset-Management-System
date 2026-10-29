<?php
header('Content-Type: application/json');
require 'config.php';

$body  = json_decode(file_get_contents('php://input'), true);
$token = trim($body['idToken']  ?? '');
$uid   = trim($body['uid']      ?? '');
$email = strtolower(trim($body['email']   ?? ''));
$name  = trim($body['name']     ?? '');
$photo = trim($body['photoURL'] ?? '');

if (!$token || !$uid || !$email) { echo json_encode(['ok'=>false,'error'=>'Missing data.']); exit; }

// Server-side domain check
if (!str_ends_with($email, '@' . ALLOWED_DOMAIN)) {
    echo json_encode(['ok'=>false,'error'=>'Only @' . ALLOWED_DOMAIN . ' accounts are allowed.']); exit;
}

// Verify token via Google tokeninfo (no SDK needed)
$url  = 'https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . urlencode($token);
$resp = @file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>8]]));
if ($resp !== false) {
    $d = json_decode($resp, true);
    if (!empty($d['error_description'])) {
        // Only hard-fail if not localhost
        if (!str_contains($_SERVER['HTTP_HOST']??'', 'localhost')) {
            echo json_encode(['ok'=>false,'error'=>$d['error_description']]); exit;
        }
    }
}

// Upsert user
$stmt = $conn->prepare('SELECT id, role FROM users WHERE google_uid=? OR gmail=? LIMIT 1');
$stmt->bind_param('ss', $uid, $email); $stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $upd = $conn->prepare('UPDATE users SET google_uid=?,google_avatar=?,full_name=IF(full_name IS NULL OR full_name="",?,full_name),auth_provider="google",last_login=NOW() WHERE id=?');
    $upd->bind_param('sssi', $uid, $photo, $name, $existing['id']); $upd->execute();
    $userId = $existing['id'];
    $role   = $existing['role'];
} else {
    $ins = $conn->prepare('INSERT INTO users (gmail,password,full_name,google_uid,google_avatar,auth_provider,role,last_login) VALUES (?,NULL,?,?,?,"google","staff",NOW())');
    $ins->bind_param('ssss', $email, $name, $uid, $photo); $ins->execute();
    $userId = $conn->insert_id;
    $role   = 'staff';
}

session_regenerate_id(true);
$_SESSION['user_id']       = $userId;
$_SESSION['gmail']         = $email;
$_SESSION['role']          = $role;
$_SESSION['google_avatar'] = $photo;
$_SESSION['full_name']     = $name;

echo json_encode(['ok'=>true, 'role'=>$role]);
