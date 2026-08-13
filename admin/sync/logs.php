<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Cloud Sync Execution Logs
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('sync.view');

$pdo = get_db_connection();

// Fetch Cloud Sync Audit Entries
$stmt = $pdo->prepare("
    SELECT a.id, a.user_id, a.action, a.module, a.record_id, a.description, a.ip_address, a.created_at,
           u.name AS user_name, u.username
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.action = 'CLOUD_BATCH_SYNC' OR a.module = 'sync_log' OR a.module = 'system'
    ORDER BY a.created_at DESC
    LIMIT 50
");
$stmt->execute();
$logs = $stmt->fetchAll();

$page_title = "Cloud Sync Execution Logs";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-journal-text text-primary me-2"></i> Cloud Sync Execution Logs
        </h2>
        <p class="text-muted small mb-0">Audit history of executed cloud recovery batch sync sessions.</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Sync Dashboard
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Batch Sync Execution Audit Log (<?php echo count($logs); ?> Records)</span>
                <span class="badge bg-dark rounded-pill"><?php echo count($logs); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($logs) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Log ID</th>
                                    <th>Event Action</th>
                                    <th>Executed By</th>
                                    <th>Target Module</th>
                                    <th>Details / Batch Sync Outcome</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?php echo $l['id']; ?></td>
                                        <td>
                                            <span class="badge bg-success font-monospace">
                                                <?php echo sanitize($l['action']); ?>
                                            </span>
                                        </td>
                                        <td class="small text-dark">
                                            <i class="bi bi-person me-1"></i> <?php echo sanitize($l['user_name'] ?? 'System Sync Engine'); ?>
                                        </td>
                                        <td class="font-monospace small text-muted">
                                            <?php echo sanitize($l['module']); ?> #<?php echo $l['record_id']; ?>
                                        </td>
                                        <td class="small text-dark" style="max-width: 350px;">
                                            <?php echo sanitize($l['description']); ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($l['created_at']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-journal-x fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No Execution Logs</h5>
                        <p class="mb-0 small">No cloud batch sync sessions executed yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
