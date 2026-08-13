<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - SMS Queue Manager Module
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms.php';

require_permission('sms.view');

$pdo = get_db_connection();

// Load Config
$config_file = __DIR__ . '/../../config/sms_gateway.json';
$settings = ['recipient_phone' => '+923001234567'];
if (file_exists($config_file)) {
    $settings = array_merge($settings, json_decode(file_get_contents($config_file), true) ?? []);
}

$query = "
    SELECT a.id, a.type, a.message, a.is_sent, a.created_at, p.name AS product_name, p.stock_qty, p.low_stock_threshold
    FROM alerts a
    LEFT JOIN products p ON a.product_id = p.id
    ORDER BY (a.is_sent = 0) DESC, a.created_at DESC
";
$alerts = $pdo->query($query)->fetchAll();

$page_title = "SMS Dispatch Queue";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-list-check text-primary me-2"></i> Cellular SMS Dispatch Queue
        </h2>
        <p class="text-muted small mb-0">Manage pending SMS alert payloads queued for Android Gateway or GSM SIM transmission.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> SMS Dashboard
        </a>
        <?php if (has_permission('sms.manage')): ?>
            <a href="settings.php" class="btn btn-dw-primary btn-sm">
                <i class="bi bi-gear me-1"></i> Gateway Settings
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>SMS Dispatch Queue (<?php echo count($alerts); ?> Items)</span>
                <span class="badge bg-dark rounded-pill"><?php echo count($alerts); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($alerts) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Event Type</th>
                                    <th>Recipient</th>
                                    <th>Formatted Payload (160 Chars)</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $a): ?>
                                    <?php 
                                        $payload = format_sms_payload(
                                            $a['type'], 
                                            $a['product_name'] ?? 'System Event', 
                                            $a['stock_qty'] ?? null, 
                                            $a['low_stock_threshold'] ?? null
                                        );
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?php echo $a['id']; ?></td>
                                        <td>
                                            <span class="badge bg-dark">
                                                <?php echo sanitize($a['type']); ?>
                                            </span>
                                        </td>
                                        <td class="font-monospace text-dark"><?php echo sanitize($settings['recipient_phone']); ?></td>
                                        <td style="max-width: 380px;">
                                            <div class="p-2 bg-light rounded border font-monospace small" style="white-space: pre-wrap; color: #1e293b;">
                                                <?php echo sanitize($payload); ?>
                                            </div>
                                            <span class="small text-muted ms-1"><?php echo mb_strlen($payload); ?> / 160 chars</span>
                                        </td>
                                        <td>
                                            <?php if ((int)$a['is_sent'] === 1): ?>
                                                <span class="badge bg-success px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Sent
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">
                                                    <i class="bi bi-clock me-1"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($a['created_at']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if (has_permission('sms.manage')): ?>
                                                <a href="../alerts/manage.php?action=toggle_sent&id=<?php echo $a['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-send me-1"></i> Dispatch
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-list-task fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No SMS Queue Items</h5>
                        <p class="mb-0 small">The SMS dispatch queue is currently empty.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
