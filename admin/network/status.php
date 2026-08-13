<?php
/**
 * Dawaam - Local Business Continuity Software
 * Network Operations - Live Status API Endpoint (JSON)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('network.view');

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$lan_ip = get_server_lan_ip();
$server_port = $_SERVER['SERVER_PORT'] ?? 8000;
$access_url = "http://{$lan_ip}:{$server_port}";

// Quick WAN Internet Check (Socket check to 8.8.8.8:53 with 1s timeout)
$is_wan_online = false;
$sock = @fsockopen('8.8.8.8', 53, $errno, $errstr, 1);
if ($sock) {
    $is_wan_online = true;
    fclose($sock);
}

$pdo = get_db_connection();
$unsynced_count = (int)$pdo->query("SELECT COUNT(*) FROM sync_log WHERE synced = 0")->fetchColumn();
$pending_alerts_count = (int)$pdo->query("SELECT COUNT(*) FROM alerts WHERE is_sent = 0")->fetchColumn();

echo json_encode([
    'success' => true,
    'app' => APP_NAME,
    'version' => APP_VERSION,
    'lan_ip' => $lan_ip,
    'server_port' => (int)$server_port,
    'access_url' => $access_url,
    'lan_status' => 'active',
    'internet_status' => $is_wan_online ? 'online' : 'offline',
    'unsynced_records' => $unsynced_count,
    'pending_alerts' => $pending_alerts_count,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
