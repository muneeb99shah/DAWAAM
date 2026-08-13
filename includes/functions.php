<?php
/**
 * Dawaam - Local Business Continuity Software
 * Helper Utilities & Security Functions
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Sanitize string for XSS prevention in HTML output
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to specific internal path
 */
function redirect($path) {
    $url = (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) 
        ? $path 
        : get_base_url() . '/' . ltrim($path, '/');
    header("Location: " . $url);
    exit;
}

/**
 * Flash message session management
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

function display_flash_messages() {
    $flash = get_flash_message();
    if ($flash) {
        $alert_type = sanitize($flash['type']);
        $message = sanitize($flash['message']);
        echo "
        <div class='alert alert-{$alert_type} alert-dismissible fade show shadow-sm border-0 mb-4' role='alert'>
            <div class='d-flex align-items-center'>
                <i class='bi bi-info-circle-fill me-2 fs-5'></i>
                <div>{$message}</div>
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}

/**
 * Generate CSRF Token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF Token Hidden Input Field
 */
function csrf_field() {
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . sanitize($token) . '">';
}

/**
 * Format currency amount for Pakistani Rupee (PKR)
 */
function format_currency($amount) {
    return 'PKR ' . number_format((float)$amount, 2);
}

/**
 * Format datetime display
 */
function format_date($datetime, $format = 'd M Y, h:i A') {
    if (!$datetime) return 'N/A';
    $date = new DateTime($datetime);
    return $date->format($format);
}

/**
 * Generate formatted code (e.g., DW-0001, SALE-8942)
 */
function generate_unique_code($prefix = 'DW', $length = 4) {
    return $prefix . '-' . strtoupper(substr(uniqid(), - $length)) . rand(10, 99);
}

/**
 * Output JSON Response
 */
function json_response($status, $message = '', $data = [], $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Record Audit Log in Database
 */
function log_audit_action($action, $module, $record_id = null, $description = null, $user_id = null) {
    try {
        $pdo = get_db_connection();
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, description, ip_address)
            VALUES (:user_id, :action, :module, :record_id, :description, :ip_address)
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':action' => $action,
            ':module' => $module,
            ':record_id' => $record_id,
            ':description' => $description,
            ':ip_address' => $ip_address
        ]);
    } catch (Exception $e) {
        error_log('Audit Log Error: ' . $e->getMessage());
    }
}

/**
 * Record Sync Change Queue Log
 */
function queue_sync_record($table_name, $record_id, $action) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            INSERT INTO sync_log (table_name, record_id, action, synced, sync_status)
            VALUES (:table_name, :record_id, :action, 0, 'pending')
        ");
        $stmt->execute([
            ':table_name' => $table_name,
            ':record_id' => $record_id,
            ':action' => $action
        ]);
    } catch (Exception $e) {
        error_log('Sync Queue Error: ' . $e->getMessage());
    }
}
