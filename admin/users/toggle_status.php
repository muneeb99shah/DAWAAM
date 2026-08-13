<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Quick User Account Status Activator / Deactivator
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users.disable');

$user_id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!verify_csrf_token($token)) {
    set_flash_message('danger', 'Invalid security token for status toggle.');
    redirect('admin/users/index.php');
}

if ($user_id <= 0) {
    set_flash_message('danger', 'Invalid user account ID specified.');
    redirect('admin/users/index.php');
}

// Safety check: Cannot deactivate current user or primary Super Admin ID 1
if ($user_id === 1 || $user_id === (int)$_SESSION['user_id']) {
    set_flash_message('danger', 'Action Denied: You cannot deactivate your own account or the primary Super Admin account.');
    redirect('admin/users/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT id, user_code, name, status FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash_message('danger', 'User account not found.');
    redirect('admin/users/index.php');
}

$new_status = ($user['status'] === 'active') ? 'inactive' : 'active';

$upd = $pdo->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id");
$upd->execute([':status' => $new_status, ':id' => $user_id]);

log_audit_action('TOGGLE_USER_STATUS', 'users', $user_id, "Toggled status of user {$user['user_code']} to '{$new_status}'");

set_flash_message('success', "Account status for '{$user['name']}' ({$user['user_code']}) changed to '{$new_status}'.");
redirect('admin/users/index.php');
