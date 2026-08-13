<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Contact Messages Management Module
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('messages.view');

$pdo = get_db_connection();

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Security check failed.');
        redirect('admin/messages/index.php');
    }

    $msg_id = (int)($_POST['message_id'] ?? 0);
    if ($msg_id > 0) {
        $stmt_del = $pdo->prepare("DELETE FROM contact_messages WHERE id = :id");
        $stmt_del->execute([':id' => $msg_id]);
        log_audit_action('DELETE_CONTACT_MESSAGE', 'contact_messages', $msg_id, "Deleted contact message ID {$msg_id}");
        set_flash_message('success', 'Contact message deleted successfully.');
    }
    redirect('admin/messages/index.php');
}

// Fetch all contact messages ordered by newest first
$stmt = $pdo->query("SELECT id, name, email, message, submitted_at FROM contact_messages ORDER BY submitted_at DESC");
$messages = $stmt->fetchAll();

$page_title = "Contact Messages Management";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-envelope-open text-primary me-2"></i> Client Inquiry Messages
        </h2>
        <p class="text-muted small mb-0">Messages submitted by local business owners through the public contact form.</p>
    </div>
    <div>
        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Return to Dashboard
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Received Messages (<?php echo count($messages); ?>)</span>
                <span class="badge bg-primary rounded-pill"><?php echo count($messages); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($messages) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width: 60px;">#</th>
                                    <th>Sender Info</th>
                                    <th>Message Details</th>
                                    <th>Submitted Date</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $msg): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?php echo $msg['id']; ?></td>
                                        <td>
                                            <strong class="text-dark d-block"><?php echo sanitize($msg['name']); ?></strong>
                                            <a href="mailto:<?php echo sanitize($msg['email']); ?>" class="small text-muted text-decoration-none">
                                                <i class="bi bi-envelope me-1"></i> <?php echo sanitize($msg['email']); ?>
                                            </a>
                                        </td>
                                        <td style="max-width: 400px;">
                                            <div class="small text-dark p-2 bg-light rounded border">
                                                <?php echo nl2br(sanitize($msg['message'])); ?>
                                            </div>
                                        </td>
                                        <td class="small text-muted">
                                            <i class="bi bi-clock me-1"></i> <?php echo format_date($msg['submitted_at']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form action="index.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this message?');" class="d-inline">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-trash me-1"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No Inquiry Messages Yet</h5>
                        <p class="mb-0 small">Messages submitted through the public contact form will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
