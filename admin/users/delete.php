<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Delete User Account Handler
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users.delete');

$user_id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!verify_csrf_token($token)) {
    set_flash_message('danger', 'Invalid security token for deletion.');
    redirect('admin/users/index.php');
}

if ($user_id <= 0) {
    set_flash_message('danger', 'Invalid user account ID specified.');
    redirect('admin/users/index.php');
}

// Safety check: Cannot delete current logged-in user or primary Super Admin (ID 1)
if ($user_id === 1 || $user_id === (int)$_SESSION['user_id']) {
    set_flash_message('danger', 'Action Denied: You cannot delete your own account or the primary Super Admin account.');
    redirect('admin/users/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT id, user_code, name, username FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash_message('danger', 'User account not found.');
    redirect('admin/users/index.php');
}

try {
    $pdo->beginTransaction();

    $stmt1 = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :id");
    $stmt1->execute([':id' => $user_id]);

    $stmt2 = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt2->execute([':id' => $user_id]);

    log_audit_action('DELETE_USER', 'users', $user_id, "Deleted user account '{$user['username']}' ({$user['user_code']})");

    $pdo->commit();

    set_flash_message('success', "User account '{$user['name']}' ({$user['user_code']}) deleted successfully.");
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete User Error: ' . $e->getMessage());
    set_flash_message('danger', 'Failed to delete user account: ' . $e->getMessage());
}

redirect('admin/users/index.php');
