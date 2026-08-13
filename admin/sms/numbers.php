<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Notification Numbers Management Module
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notification_service.php';

require_permission('sms.manage');

$pdo = get_db_connection();
$errors = [];

// Handle POST Form Actions (Add, Edit, Delete, Toggle Status, Set Primary)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token validation failed.';
    }

    $action = $_POST['action'] ?? '';

    if (empty($errors)) {
        if ($action === 'add' || $action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $raw_phone = trim($_POST['phone_number'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $is_primary = isset($_POST['is_primary']) ? 1 : 0;
            $receive_whatsapp = isset($_POST['receive_whatsapp']) ? 1 : 0;
            $receive_sms = isset($_POST['receive_sms']) ? 1 : 0;

            if (empty($name)) $errors[] = 'Recipient label name is required.';
            if (empty($raw_phone)) $errors[] = 'Phone number is required.';

            $norm_phone = normalize_phone_number($raw_phone);
            if (empty($norm_phone) || strlen($norm_phone) < 11) {
                $errors[] = "Invalid phone number format: '{$raw_phone}'. Please enter a valid number (e.g. 03138388108).";
            }

            // Duplicate Check
            if (empty($errors)) {
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM notification_numbers WHERE phone_number = :phone AND id != :id");
                $stmt_chk->execute([':phone' => $norm_phone, ':id' => $id]);
                if ($stmt_chk->fetchColumn() > 0) {
                    $errors[] = "Phone number '{$norm_phone}' is already registered in the notification numbers list.";
                }
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    if ($is_primary === 1) {
                        // Reset all other numbers to not primary
                        $pdo->exec("UPDATE notification_numbers SET is_primary = 0");
                    }

                    if ($action === 'add') {
                        $stmt_ins = $pdo->prepare("
                            INSERT INTO notification_numbers (name, phone_number, status, is_primary, receive_whatsapp, receive_sms)
                            VALUES (:name, :phone, :status, :is_primary, :receive_wa, :receive_sms)
                        ");
                        $stmt_ins->execute([
                            ':name' => $name,
                            ':phone' => $norm_phone,
                            ':status' => $status,
                            ':is_primary' => $is_primary,
                            ':receive_wa' => $receive_whatsapp,
                            ':receive_sms' => $receive_sms
                        ]);
                        log_audit_action('ADD_NOTIFICATION_NUMBER', 'sms', $pdo->lastInsertId(), "Added notification number '{$name}' ({$norm_phone})");
                        set_flash_message('success', "Notification number '{$name}' ({$norm_phone}) added successfully.");
                    } else {
                        $stmt_upd = $pdo->prepare("
                            UPDATE notification_numbers 
                            SET name = :name, phone_number = :phone, status = :status, is_primary = :is_primary, 
                                receive_whatsapp = :receive_wa, receive_sms = :receive_sms, updated_at = NOW()
                            WHERE id = :id
                        ");
                        $stmt_upd->execute([
                            ':name' => $name,
                            ':phone' => $norm_phone,
                            ':status' => $status,
                            ':is_primary' => $is_primary,
                            ':receive_wa' => $receive_whatsapp,
                            ':receive_sms' => $receive_sms,
                            ':id' => $id
                        ]);
                        log_audit_action('EDIT_NOTIFICATION_NUMBER', 'sms', $id, "Updated notification number '{$name}' ({$norm_phone})");
                        set_flash_message('success', "Notification number '{$name}' updated successfully.");
                    }

                    $pdo->commit();
                    redirect('admin/sms/numbers.php');
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt_del = $pdo->prepare("DELETE FROM notification_numbers WHERE id = :id");
                $stmt_del->execute([':id' => $id]);
                log_audit_action('DELETE_NOTIFICATION_NUMBER', 'sms', $id, "Deleted notification number ID #{$id}");
                set_flash_message('success', 'Notification number removed.');
                redirect('admin/sms/numbers.php');
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $curr_status = $_POST['current_status'] ?? 'active';
            $new_status = ($curr_status === 'active') ? 'disabled' : 'active';

            $stmt_t = $pdo->prepare("UPDATE notification_numbers SET status = :status WHERE id = :id");
            $stmt_t->execute([':status' => $new_status, ':id' => $id]);
            log_audit_action('TOGGLE_NOTIFICATION_NUMBER', 'sms', $id, "Status toggled to {$new_status}");
            set_flash_message('success', "Notification number status set to {$new_status}.");
            redirect('admin/sms/numbers.php');
        } elseif ($action === 'set_primary') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->exec("UPDATE notification_numbers SET is_primary = 0");
                $stmt_p = $pdo->prepare("UPDATE notification_numbers SET is_primary = 1, status = 'active' WHERE id = :id");
                $stmt_p->execute([':id' => $id]);
                log_audit_action('SET_PRIMARY_NOTIFICATION_NUMBER', 'sms', $id, "Set primary owner number ID #{$id}");
                set_flash_message('success', 'Primary notification owner number updated.');
                redirect('admin/sms/numbers.php');
            }
        }
    }
}

// Fetch All Numbers
$numbers = $pdo->query("SELECT * FROM notification_numbers ORDER BY is_primary DESC, id ASC")->fetchAll();

$page_title = "Notification Numbers Management";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-telephone-outbound text-primary me-2"></i> Notification Numbers Management
        </h2>
        <p class="text-muted small mb-0">Configure business owner and manager recipient phone numbers for WhatsApp & SMS alert dispatches.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chat-left-text me-1"></i> Gateway Dashboard
        </a>
        <button type="button" class="btn btn-dw-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addNumberModal">
            <i class="bi bi-plus-lg me-1"></i> Add Notification Number
        </button>
    </div>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?php echo sanitize($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Numbers Directory Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Notification Recipients Directory (<?php echo count($numbers); ?> Numbers)</span>
                <span class="badge bg-dark rounded-pill"><?php echo count($numbers); ?> Active & Standby</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($numbers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Recipient Name / Label</th>
                                    <th>Phone Number</th>
                                    <th>Status</th>
                                    <th>Primary Owner</th>
                                    <th>Enabled Channels</th>
                                    <th>Date Added</th>
                                    <th class="text-end pe-4">Super Admin Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($numbers as $n): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark d-block"><?php echo sanitize($n['name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark font-monospace fs-6 px-2.5 py-1">
                                                <?php echo sanitize($n['phone_number']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($n['status'] === 'active'): ?>
                                                <span class="badge bg-success px-2.5 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary px-2.5 py-1">
                                                    <i class="bi bi-pause-circle me-1"></i> Disabled
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$n['is_primary'] === 1): ?>
                                                <span class="badge bg-warning text-dark px-2.5 py-1">
                                                    <i class="bi bi-star-fill me-1"></i> Primary Owner
                                                </span>
                                            <?php else: ?>
                                                <form action="numbers.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="set_primary">
                                                    <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                                    <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none small text-muted">
                                                        Set Primary
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <?php if ((int)$n['receive_whatsapp'] === 1): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 small">
                                                        <i class="bi bi-whatsapp me-1"></i> WhatsApp
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ((int)$n['receive_sms'] === 1): ?>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-0.5 small">
                                                        <i class="bi bi-chat-text me-1"></i> SMS
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($n['created_at']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <form action="numbers.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                                    <input type="hidden" name="current_status" value="<?php echo $n['status']; ?>">
                                                    <button type="submit" class="btn <?php echo $n['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                        <?php echo $n['status'] === 'active' ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </form>
                                                <form action="numbers.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this notification number?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-telephone-x fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No Notification Numbers Configured</h5>
                        <p class="mb-0 small">Add at least one owner phone number to receive urgent SMS and WhatsApp alerts.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Number Modal -->
<div class="modal fade" id="addNumberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="numbers.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-telephone-plus me-2 text-primary"></i> Add Notification Recipient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Recipient Name / Label <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Owner / Store Manager / Backup" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" name="phone_number" placeholder="Example: +1234567890" required>
                        <div class="form-text small">Pakistani phone numbers will be automatically normalized to international format (`+923XX...`).</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_primary" id="addPrimaryCheck" value="1">
                                <label class="form-check-label fw-semibold" for="addPrimaryCheck">
                                    Set as Primary Owner
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-2 small text-uppercase text-muted">Enabled Notification Channels</h6>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="receive_whatsapp" id="addWaCheck" value="1" checked>
                            <label class="form-check-label" for="addWaCheck">
                                <i class="bi bi-whatsapp text-success me-1"></i> Receive WhatsApp Notifications
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="receive_sms" id="addSmsCheck" value="1" checked>
                            <label class="form-check-label" for="addSmsCheck">
                                <i class="bi bi-chat-text text-primary me-1"></i> Receive Cellular SMS Fallback
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dw-primary">
                        <i class="bi bi-check-circle me-1"></i> Save Notification Number
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
