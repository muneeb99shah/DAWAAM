<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Conflict Resolution Action Processor
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('conflicts.resolve');

$token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    set_flash_message('danger', 'Security token check failed.');
    redirect('admin/conflicts/index.php');
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$conflict_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($conflict_id <= 0) {
    set_flash_message('danger', 'Invalid conflict ID.');
    redirect('admin/conflicts/index.php');
}

$pdo = get_db_connection();
$user = current_user();

// Fetch Conflict Record
$stmt = $pdo->prepare("SELECT * FROM sync_conflicts WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $conflict_id]);
$conflict = $stmt->fetch();

if (!$conflict) {
    set_flash_message('danger', 'Conflict record not found.');
    redirect('admin/conflicts/index.php');
}

if ($conflict['status'] === 'resolved') {
    set_flash_message('info', 'This conflict has already been resolved.');
    redirect('admin/conflicts/index.php');
}

try {
    $pdo->beginTransaction();

    if ($action === 'keep_local') {
        // Strategy A: Keep Pharmacy Local Version -> Mark resolved & queue cloud push
        $stmt_res = $pdo->prepare("
            UPDATE sync_conflicts 
            SET status = 'resolved', resolved_at = NOW(), resolved_by = :user_id 
            WHERE id = :id
        ");
        $stmt_res->execute([
            ':user_id' => $user['id'],
            ':id' => $conflict_id
        ]);

        queue_sync_record($conflict['table_name'], $conflict['record_id'], 'UPDATE');
        log_audit_action('RESOLVE_CONFLICT_KEEP_LOCAL', 'sync_conflicts', $conflict_id, "Resolved conflict #{$conflict_id} keeping local LAN data");

        set_flash_message('success', "Conflict #{$conflict_id} resolved! Pharmacy local data retained and queued for cloud update.");
    } elseif ($action === 'keep_cloud') {
        // Strategy B: Overwrite Local Database with Cloud Master Data
        $remote_data = json_decode($conflict['remote_data'], true) ?? [];
        $table_name = $conflict['table_name'];
        $record_id = (int)$conflict['record_id'];

        if ($table_name === 'products' && count($remote_data) > 0) {
            $stmt_upd = $pdo->prepare("
                UPDATE products 
                SET price = COALESCE(:price, price), 
                    stock_qty = COALESCE(:stock_qty, stock_qty), 
                    updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt_upd->execute([
                ':price' => $remote_data['price'] ?? null,
                ':stock_qty' => $remote_data['stock_qty'] ?? null,
                ':id' => $record_id
            ]);
        }

        $stmt_res = $pdo->prepare("
            UPDATE sync_conflicts 
            SET status = 'resolved', resolved_at = NOW(), resolved_by = :user_id 
            WHERE id = :id
        ");
        $stmt_res->execute([
            ':user_id' => $user['id'],
            ':id' => $conflict_id
        ]);

        log_audit_action('RESOLVE_CONFLICT_KEEP_CLOUD', 'sync_conflicts', $conflict_id, "Resolved conflict #{$conflict_id} overwriting local database with cloud master data");

        set_flash_message('success', "Conflict #{$conflict_id} resolved! Local database updated with cloud master data.");
    } else {
        set_flash_message('warning', 'Invalid resolution action specified.');
        redirect('admin/conflicts/index.php');
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Resolve Conflict Error: ' . $e->getMessage());
    set_flash_message('danger', 'Failed to resolve conflict: ' . $e->getMessage());
}

redirect('admin/conflicts/index.php');
