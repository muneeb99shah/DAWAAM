<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Cloud Recovery Batch Sync Processor Engine
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../cloud/api/sync.php';

require_permission('sync.manage');

// Validate CSRF Token
$token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    set_flash_message('danger', 'Security token validation failed.');
    redirect('admin/sync/index.php');
}

$pdo = get_db_connection();

// 1. Query Pending Unsynced Change Logs
$stmt_pending = $pdo->query("
    SELECT id, table_name, record_id, action, created_at 
    FROM sync_log 
    WHERE synced = 0 
    ORDER BY id ASC 
    LIMIT 50
");
$pending_logs = $stmt_pending->fetchAll();

if (count($pending_logs) === 0) {
    set_flash_message('info', 'No pending change logs found. All local records are already synced to cloud!');
    redirect('admin/sync/index.php');
}

// 2. Build Batch Payload with Table Data
$records = [];

foreach ($pending_logs as $log) {
    $table = $log['table_name'];
    $rec_id = (int)$log['record_id'];
    $data = null;
    $stmt_data = null;

    switch ($table) {
        case 'products':
            $stmt_data = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
            break;
        case 'sales':
            $stmt_data = $pdo->prepare("SELECT * FROM sales WHERE id = :id LIMIT 1");
            break;
        case 'alerts':
            $stmt_data = $pdo->prepare("SELECT * FROM alerts WHERE id = :id LIMIT 1");
            break;
        case 'contact_messages':
            $stmt_data = $pdo->prepare("SELECT * FROM contact_messages WHERE id = :id LIMIT 1");
            break;
        case 'users':
            $stmt_data = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            break;
        case 'categories':
            $stmt_data = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
            break;
    }

    if ($stmt_data) {
        $stmt_data->execute([':id' => $rec_id]);
        $data = $stmt_data->fetch();
    }

    $records[] = [
        'sync_id' => (int)$log['id'],
        'table_name' => $table,
        'record_id' => $rec_id,
        'action' => $log['action'],
        'created_at' => $log['created_at'],
        'data' => $data
    ];
}

// 3. Post Payload to Cloud API Endpoint
$cloud_api_url = BASE_URL . '/cloud/api/sync.php';
$payload_data = [
    'token' => 'dawaam_secret_token_2026',
    'batch_size' => count($records),
    'records' => $records
];

$post_payload = json_encode($payload_data);

$opts = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n" .
                     "Authorization: Bearer dawaam_secret_token_2026\r\n",
        'content' => $post_payload,
        'timeout' => 1
    ]
];

$context = stream_context_create($opts);
$result = @file_get_contents($cloud_api_url, false, $context);

if ($result !== false) {
    $response = json_decode($result, true);
} else {
    // Graceful internal execution fallback for single-threaded PHP dev server
    $response = dawaam_process_cloud_sync($payload_data);
}

if (!$response || !isset($response['success']) || !$response['success']) {
    $err_msg = $response['message'] ?? 'Unknown Cloud API Error';
    set_flash_message('danger', 'Cloud Recovery Sync Error: ' . $err_msg);
    redirect('admin/sync/index.php');
}

// 4. Mark Synced Records in Database
$synced_ids = $response['synced_ids'] ?? [];
$conflicts = $response['conflicts'] ?? [];

if (count($synced_ids) > 0) {
    $in_clause = implode(',', array_map('intval', $synced_ids));
    $pdo->exec("UPDATE sync_log SET synced = 1, synced_at = NOW() WHERE id IN ({$in_clause})");
}

// 5. Handle Conflicts if any
if (count($conflicts) > 0) {
    $stmt_conf = $pdo->prepare("
        INSERT INTO sync_conflicts (table_name, record_id, local_data, remote_data, status, created_at)
        VALUES (:table_name, :record_id, :local_data, :remote_data, 'unresolved', NOW())
    ");
    foreach ($conflicts as $c) {
        $stmt_conf->execute([
            ':table_name' => $c['table_name'],
            ':record_id' => $c['record_id'],
            ':local_data' => json_encode(['description' => $c['description']]),
            ':remote_data' => json_encode(['cloud_status' => 'mismatch'])
        ]);
    }
}

// 6. Record Security Audit Log
$count_synced = count($synced_ids);
$count_conflicts = count($conflicts);
log_audit_action('CLOUD_BATCH_SYNC', 'sync_log', 0, "Executed batch recovery sync. Synced: {$count_synced} records, Conflicts: {$count_conflicts}");

set_flash_message('success', "Cloud Recovery Sync Complete! {$count_synced} local records successfully backed up to cloud server.");
redirect('admin/sync/index.php');
