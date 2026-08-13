<?php
/**
 * Dawaam - Local Business Continuity Software
 * Webhook Endpoint - Provider Delivery Status Report Callback (WhatsApp & SMS)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/notification_service.php';

// Accept JSON payload or POST fields
$raw_input = file_get_contents('php_input') ?: file_get_contents('php://input');
$data = json_decode($raw_input, true) ?? $_POST;

$msg_id = trim($data['provider_msg_id'] ?? $data['message_id'] ?? $data['id'] ?? $data['entry'][0]['changes'][0]['value']['statuses'][0]['id'] ?? '');
$status_raw = strtolower(trim($data['status'] ?? $data['entry'][0]['changes'][0]['value']['statuses'][0]['status'] ?? ''));
$error_detail = trim($data['error'] ?? $data['error_message'] ?? $data['reason'] ?? '');

if (empty($msg_id)) {
    echo json_encode([
        'status' => 'ignored',
        'message' => 'Missing provider message ID (provider_msg_id / message_id).'
    ]);
    exit;
}

$pdo = get_db_connection();

// Match Record in notification_logs
$stmt = $pdo->prepare("SELECT id, status FROM notification_logs WHERE provider_msg_id = :msg_id LIMIT 1");
$stmt->execute([':msg_id' => $msg_id]);
$log = $stmt->fetch();

if (!$log) {
    echo json_encode([
        'status' => 'not_found',
        'message' => "No notification log record found matching provider_msg_id '{$msg_id}'."
    ]);
    exit;
}

// Ignore if already marked delivered
if ($log['status'] === 'delivered') {
    echo json_encode([
        'status' => 'already_delivered',
        'message' => "Notification ID #{$log['id']} is already marked as delivered."
    ]);
    exit;
}

// Map Provider Status
$new_status = 'sent';
if (in_array($status_raw, ['delivered', 'read', 'success'], true)) {
    $new_status = 'delivered';
} elseif (in_array($status_raw, ['failed', 'rejected', 'undelivered', 'error'], true)) {
    $new_status = 'failed';
}

if ($new_status === 'delivered') {
    $upd = $pdo->prepare("UPDATE notification_logs SET status = 'delivered', delivered_at = NOW() WHERE id = :id");
    $upd->execute([':id' => $log['id']]);
} elseif ($new_status === 'failed') {
    $upd = $pdo->prepare("UPDATE notification_logs SET status = 'failed', error_message = :err WHERE id = :id");
    $upd->execute([':err' => ($error_detail ?: 'Provider reported delivery failure'), ':id' => $log['id']]);
}

echo json_encode([
    'status' => 'success',
    'message' => "Notification log #{$log['id']} updated to status '{$new_status}'.",
    'log_id' => $log['id'],
    'provider_msg_id' => $msg_id,
    'new_status' => $new_status
]);
