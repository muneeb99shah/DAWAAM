<?php
/**
 * Dawaam - Local Business Continuity Software
 * Urgent Rule Engine & Alert Dispatcher
 */

require_once __DIR__ . '/functions.php';

/**
 * Evaluate product stock level after stock changes or sales
 */
function check_and_trigger_low_stock_alert($product_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT id, name, stock_qty, low_stock_threshold
            FROM products
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $product_id]);
        $product = $stmt->fetch();

        if ($product && $product['stock_qty'] <= $product['low_stock_threshold']) {
            $msg = sprintf(
                "URGENT ALERT: Product '%s' stock is critically low. Current stock: %d units (Threshold: %d units). Please reorder immediately.",
                $product['name'],
                $product['stock_qty'],
                $product['low_stock_threshold']
            );

            // Avoid duplicate pending alert for same product
            $stmt_check = $pdo->prepare("
                SELECT id FROM alerts 
                WHERE product_id = :product_id 
                AND type = 'low_stock' 
                AND is_sent = 0 
                LIMIT 1
            ");
            $stmt_check->execute([':product_id' => $product_id]);
            
            if (!$stmt_check->fetch()) {
                $stmt_insert = $pdo->prepare("
                    INSERT INTO alerts (product_id, type, message, is_sent)
                    VALUES (:product_id, 'low_stock', :message, 0)
                ");
                $stmt_insert->execute([
                    ':product_id' => $product_id,
                    ':message' => $msg
                ]);

                queue_sync_record('alerts', $pdo->lastInsertId(), 'INSERT');
                log_audit_action('ALERT_CREATED', 'alerts', $product_id, "Triggered low stock alert for {$product['name']}");
                return true;
            }
        }
    } catch (Exception $e) {
        error_log('Low Stock Alert Error: ' . $e->getMessage());
    }
    return false;
}

/**
 * Evaluate sale amount against big sale threshold
 */
function check_and_trigger_big_sale_alert($sale_id, $total_amount, $product_name, $quantity) {
    try {
        $pdo = get_db_connection();
        if ($total_amount >= DEFAULT_BIG_SALE_THRESHOLD) {
            $msg = sprintf(
                "HIGH VALUE SALE ALERT: Large sale recorded! Item: %s (Qty: %d). Total Amount: PKR %s.",
                $product_name,
                $quantity,
                number_format($total_amount, 2)
            );

            $stmt_insert = $pdo->prepare("
                INSERT INTO alerts (product_id, type, message, is_sent)
                VALUES (NULL, 'big_sale', :message, 0)
            ");
            $stmt_insert->execute([':message' => $msg]);

            $alert_id = $pdo->lastInsertId();
            queue_sync_record('alerts', $alert_id, 'INSERT');
            log_audit_action('ALERT_CREATED', 'alerts', $alert_id, "Triggered big sale alert for amount PKR " . number_format($total_amount, 2));
            return true;
        }
    } catch (Exception $e) {
        error_log('Big Sale Alert Error: ' . $e->getMessage());
    }
    return false;
}

/**
 * Fetch counts of pending and sent alerts
 */
function get_alert_summary_counts() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN is_sent = 0 THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN is_sent = 1 THEN 1 ELSE 0 END) AS sent_count,
            COUNT(*) AS total_count
        FROM alerts
    ");
    $result = $stmt->fetch();
    return [
        'pending' => (int)($result['pending_count'] ?? 0),
        'sent' => (int)($result['sent_count'] ?? 0),
        'total' => (int)($result['total_count'] ?? 0)
    ];
}
