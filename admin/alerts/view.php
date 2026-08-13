<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - View Alert Details
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('alerts.view');

$alert_id = (int)($_GET['id'] ?? 0);
if ($alert_id <= 0) {
    set_flash_message('danger', 'Invalid alert ID specified.');
    redirect('admin/alerts/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT a.*, p.name AS product_name, p.sku AS product_sku, p.price AS product_price,
           p.stock_qty AS product_stock, p.low_stock_threshold, c.name AS category_name
    FROM alerts a
    LEFT JOIN products p ON a.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $alert_id]);
$alert = $stmt->fetch();

if (!$alert) {
    set_flash_message('danger', 'Alert record not found.');
    redirect('admin/alerts/index.php');
}

$page_title = "Alert Detail #" . $alert['id'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-bell-fill text-warning me-2"></i> Event Alert #<?php echo $alert['id']; ?>
        </h2>
        <p class="text-muted small mb-0">Detailed event payload and SMS gateway dispatch log.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Alerts
        </a>
        <?php if (has_permission('alerts.manage')): ?>
            <a href="manage.php?action=toggle_sent&id=<?php echo $alert['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-repeat me-1"></i> Toggle SMS Sent Status
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-7">
        <div class="dw-card h-100 p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Alert Message & Payload</h5>

            <div class="mb-3">
                <span class="text-muted small d-block mb-1">Event Type:</span>
                <span class="badge bg-danger fs-6 px-3 py-1">
                    <i class="bi bi-lightning-fill me-1"></i> <?php echo sanitize(str_replace('_', ' ', strtoupper($alert['type']))); ?>
                </span>
            </div>

            <div class="p-3 bg-light rounded-3 border mb-3">
                <span class="text-muted small d-block mb-1 fw-bold">Message Content:</span>
                <p class="mb-0 text-dark fw-semibold" style="line-height: 1.6;">
                    <?php echo sanitize($alert['message']); ?>
                </p>
            </div>

            <div class="d-flex justify-content-between small border-bottom pb-2 mb-2">
                <span class="text-muted">Triggered Date & Time:</span>
                <span class="fw-bold text-dark"><?php echo format_date($alert['created_at']); ?></span>
            </div>

            <div class="d-flex justify-content-between small">
                <span class="text-muted">SMS Dispatch Status:</span>
                <?php if ((int)$alert['is_sent'] === 1): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Sent via SMS Gateway</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i> Pending SMS Dispatch</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Product Context if linked -->
    <div class="col-md-5">
        <div class="dw-card h-100 p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Associated Product Context</h5>

            <?php if (!empty($alert['product_name'])): ?>
                <div class="mb-3">
                    <strong class="text-dark fs-5 d-block"><?php echo sanitize($alert['product_name']); ?></strong>
                    <span class="badge bg-light text-dark border font-monospace">SKU: <?php echo sanitize($alert['product_sku']); ?></span>
                    <span class="badge bg-info text-dark"><?php echo sanitize($alert['category_name'] ?? 'Unassigned'); ?></span>
                </div>

                <div class="d-flex justify-content-between small border-bottom pb-2 mb-2">
                    <span class="text-muted">Current Stock:</span>
                    <strong class="text-dark"><?php echo $alert['product_stock']; ?> units</strong>
                </div>

                <div class="d-flex justify-content-between small border-bottom pb-2 mb-2">
                    <span class="text-muted">Alert Threshold:</span>
                    <strong class="text-dark">&le; <?php echo $alert['low_stock_threshold']; ?> units</strong>
                </div>

                <div class="d-flex justify-content-between small mb-3">
                    <span class="text-muted">Unit Price:</span>
                    <strong class="text-dark"><?php echo format_currency($alert['product_price']); ?></strong>
                </div>

                <a href="../products/view.php?id=<?php echo $alert['product_id']; ?>" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-box-seam me-1"></i> View Full Product Details
                </a>
            <?php else: ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-info-circle fs-2 d-block mb-2"></i>
                    <p class="mb-0 small">This alert is a system-wide or non-product business event.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
