<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Alert Management & Manual Trigger Handler
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alerts.php';
require_once __DIR__ . '/../../includes/sms.php';
require_once __DIR__ . '/../../includes/notification_service.php';

require_permission('alerts.manage');

$token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    set_flash_message('danger', 'Security check failed. Please try again.');
    redirect('admin/alerts/index.php');
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$alert_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$pdo = get_db_connection();

switch ($action) {
    case 'trigger_test':
        try {
            $msg = "TEST ALERT: Urgent stock monitor check triggered manually at " . date('h:i A') . ". Please verify WhatsApp/SMS connectivity.";
            $stmt = $pdo->prepare("
                INSERT INTO alerts (product_id, type, message, is_sent, created_at)
                VALUES (1, 'low_stock', :message, 0, NOW())
            ");
            $stmt->execute([':message' => $msg]);
            $new_id = $pdo->lastInsertId();

            queue_sync_record('alerts', $new_id, 'INSERT');
            log_audit_action('TRIGGER_TEST_ALERT', 'alerts', $new_id, "Manually triggered test alert #{$new_id}");

            // Execute Smart Dispatch immediately
            $payload = format_sms_payload('low_stock', 'System Test Alert', 5, 10);
            send_smart_notification($payload, $new_id);

            set_flash_message('success', "Test alert #{$new_id} created and dispatched to recipient numbers.");
        } catch (Exception $e) {
            error_log('Trigger Test Alert Error: ' . $e->getMessage());
            set_flash_message('danger', 'Failed to trigger test alert: ' . $e->getMessage());
        }
        break;

    case 'dispatch_now':
    case 'toggle_sent':
        if ($alert_id > 0) {
            try {
                $stmt_get = $pdo->prepare("
                    SELECT a.id, a.type, a.message, p.name AS product_name, p.stock_qty, p.low_stock_threshold
                    FROM alerts a
                    LEFT JOIN products p ON a.product_id = p.id
                    WHERE a.id = :id LIMIT 1
                ");
                $stmt_get->execute([':id' => $alert_id]);
                $alert = $stmt_get->fetch();

                if ($alert) {
                    $payload = format_sms_payload($alert['type'], $alert['product_name'] ?? 'System Event', $alert['stock_qty'] ?? null, $alert['low_stock_threshold'] ?? null);
                    $results = send_smart_notification($payload, $alert['id']);
                    
                    $used_str = implode(', ', array_column($results, 'channel'));
                    set_flash_message('success', "Alert #{$alert_id} dispatched via {$used_str}. Notification record updated.");
                }
            } catch (Exception $e) {
                error_log('Dispatch Alert Error: ' . $e->getMessage());
                set_flash_message('danger', 'Failed to dispatch alert: ' . $e->getMessage());
            }
        }
        break;

    case 'delete_alert':
        if ($alert_id > 0) {
            try {
                $stmt_del = $pdo->prepare("DELETE FROM alerts WHERE id = :id");
                $stmt_del->execute([':id' => $alert_id]);

                queue_sync_record('alerts', $alert_id, 'DELETE');
                log_audit_action('DELETE_ALERT', 'alerts', $alert_id, "Deleted alert #{$alert_id}");

                set_flash_message('success', "Alert #{$alert_id} deleted successfully.");
            } catch (Exception $e) {
                error_log('Delete Alert Error: ' . $e->getMessage());
                set_flash_message('danger', 'Failed to delete alert: ' . $e->getMessage());
            }
        }
        break;

    default:
        set_flash_message('warning', 'Invalid alert action requested.');
        break;
}

redirect('admin/sms/index.php');
