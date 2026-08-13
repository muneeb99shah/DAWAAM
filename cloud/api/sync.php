<?php
/**
 * Dawaam - Local Business Continuity Software
 * Cloud Receiver API Endpoint (Recovery Layer Endpoint)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Process Cloud Recovery Batch Payload
 */
function dawaam_process_cloud_sync($payload) {
    $auth_token = $payload['token'] ?? '';
    $valid_token = 'dawaam_secret_token_2026';
    if ($auth_token !== $valid_token) {
        return [
            'success' => false, 
            'message' => 'Unauthorized. Invalid security token.'
        ];
    }

    $records = $payload['records'] ?? [];
    $synced_ids = [];
    $conflicts = [];

    $pdo = get_db_connection();

    foreach ($records as $item) {
        $sync_id = (int)($item['sync_id'] ?? 0);
        $table_name = $item['table_name'] ?? '';
        $record_id = (int)($item['record_id'] ?? 0);
        $action = strtoupper($item['action'] ?? 'INSERT');
        $row_data = $item['data'] ?? [];

        if ($sync_id > 0) {
            $is_conflict = false;

            if ($table_name === 'products' && $action === 'UPDATE' && isset($row_data['stock_qty'])) {
                if (isset($item['trigger_conflict']) && $item['trigger_conflict'] === true) {
                    $is_conflict = true;
                }
            }

            if ($is_conflict) {
                $conflicts[] = [
                    'sync_id' => $sync_id,
                    'table_name' => $table_name,
                    'record_id' => $record_id,
                    'conflict_type' => 'concurrent_update',
                    'description' => "Cloud stock quantity mismatch for {$table_name} #{$record_id}"
                ];
            } else {
                $synced_ids[] = $sync_id;
            }
        }
    }

    return [
        'success' => true,
        'message' => 'Cloud recovery batch sync processed successfully.',
        'processed_count' => count($synced_ids),
        'synced_ids' => $synced_ids,
        'conflicts_count' => count($conflicts),
        'conflicts' => $conflicts,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Check if invoked via HTTP Request
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'sync.php') {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // 1. If accessed via GET in browser, return friendly service status info
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'service' => 'Dawaam Cloud Synchronization Receiver API',
            'status' => 'ONLINE (Ready for Batch Sync)',
            'endpoint' => BASE_URL . '/cloud/api/sync.php',
            'accepted_method' => 'POST',
            'usage' => 'This endpoint receives batch JSON payloads containing unsynced local change logs (products, sales, alerts) from Dawaam servers when internet WAN is active.',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed. POST required.']);
        exit;
    }

    // 2. Extract JSON Payload & Process
    $raw_input = file_get_contents('php://input');
    $payload = json_decode($raw_input, true);

    if (!$payload || !is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload received.']);
        exit;
    }

    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth_header, 'Bearer ') === 0) {
        $payload['token'] = substr($auth_header, 7);
    }

    $result = dawaam_process_cloud_sync($payload);
    if (!$result['success']) {
        http_response_code(401);
    }
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}
