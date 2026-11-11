<?php
/**
 * Google Forms -> Google Sheets -> AssetVault webhook.
 *
 * Accepts JSON POST requests from the Apps Script in integrations/google_forms/Code.gs.
 * Configure app_settings.google_form_webhook_secret with a long random secret.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function webhook_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    webhook_response(405, ['ok' => false, 'error' => 'POST required']);
}

$expectedSecret = trim((string)setting('google_form_webhook_secret', ''));
$providedSecret = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
if (stripos($providedSecret, 'Bearer ') === 0) {
    $providedSecret = trim(substr($providedSecret, 7));
}
if ($expectedSecret === '' || $providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
    webhook_response(401, ['ok' => false, 'error' => 'Unauthorized']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    webhook_response(400, ['ok' => false, 'error' => 'Invalid JSON payload']);
}

$responseId = trim((string)($data['response_id'] ?? ''));
$concernText = trim((string)($data['concern'] ?? ''));
$assetCode = trim((string)($data['asset_tag'] ?? ''));
$assetName = trim((string)($data['asset_name'] ?? ''));
$location = trim((string)($data['location'] ?? ''));
$reporterName = trim((string)($data['reporter_name'] ?? ''));

if ($responseId === '' || $concernText === '') {
    webhook_response(422, ['ok' => false, 'error' => 'response_id and concern are required']);
}
if (strlen($responseId) > 191 || strlen($reporterName) > 180) {
    webhook_response(422, ['ok' => false, 'error' => 'A field exceeds its allowed length']);
}

// Resolve the QR pre-filled asset tag to the existing inventory record.
$itemId = null;
$roomId = null;
if ($assetCode !== '') {
    $assetStmt = $conn->prepare('SELECT id, room_id, name FROM items WHERE asset_code = ? LIMIT 1');
    $assetStmt->bind_param('s', $assetCode);
    $assetStmt->execute();
    $asset = $assetStmt->get_result()->fetch_assoc();
    if ($asset) {
        $itemId = (int)$asset['id'];
        $roomId = (int)$asset['room_id'];
        if ($assetName === '') $assetName = $asset['name'];
    }
}

$title = $assetName !== '' ? 'Issue with: ' . $assetName : 'Student / Teacher Concern';
$title = mb_substr($title, 0, 200);
$descriptionParts = [];
if ($assetCode !== '') $descriptionParts[] = 'Asset Tag: ' . $assetCode;
if ($location !== '') $descriptionParts[] = 'Location: ' . $location;
$descriptionParts[] = 'Problem / Concern: ' . $concernText;
$description = implode("\n", $descriptionParts);

$reportUserId = public_report_user_id();
$stmt = $conn->prepare(
    "INSERT IGNORE INTO concerns
      (item_id, room_id, user_id, title, description, priority, status, reporter_name, source, external_submission_id)
     VALUES (?, ?, ?, ?, ?, 'medium', 'open', ?, 'google_form', ?)"
);
$stmt->bind_param('iiissss', $itemId, $roomId, $reportUserId, $title, $description, $reporterName, $responseId);
if (!$stmt->execute()) {
    error_log('AssetVault Google Forms webhook insert failed: ' . $stmt->error);
    webhook_response(500, ['ok' => false, 'error' => 'Could not save report']);
}

if ($stmt->affected_rows === 0) {
    webhook_response(200, ['ok' => true, 'duplicate' => true, 'message' => 'This response was already imported']);
}

webhook_response(201, ['ok' => true, 'duplicate' => false, 'concern_id' => $conn->insert_id]);
