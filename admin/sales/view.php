<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - View Sale Transaction Details
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('sales.view');

$sale_id = (int)($_GET['id'] ?? 0);
if ($sale_id <= 0) {
    set_flash_message('danger', 'Invalid sale ID specified.');
    redirect('admin/sales/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT s.*, p.name AS product_name, p.sku AS product_sku, p.stock_qty AS remaining_stock, p.low_stock_threshold,
           u.name AS cashier_name, u.username AS cashier_username, u.user_code AS cashier_code
    FROM sales s
    INNER JOIN products p ON s.product_id = p.id
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    set_flash_message('danger', 'Sale transaction record not found.');
    redirect('admin/sales/index.php');
}

$page_title = "View Transaction - " . sanitize($sale['sale_code']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-receipt text-primary me-2"></i> Transaction Details: <?php echo sanitize($sale['sale_code']); ?>
        </h2>
        <p class="text-muted small mb-0">Local POS Checkout Record & Audit Overview.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Sales Log
        </a>
        <a href="receipt.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-receipt me-1"></i> Receipt
        </a>
        <a href="invoice.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i> Tax Invoice
        </a>
        <a href="challan.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-truck me-1"></i> Delivery Challan
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="dw-card h-100 p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Transaction Summary</h5>

            <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                <span class="text-muted">Sale Code:</span>
                <strong class="text-dark font-monospace"><?php echo sanitize($sale['sale_code']); ?></strong>
            </div>

            <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                <span class="text-muted">Product Purchased:</span>
                <strong class="text-dark"><?php echo sanitize($sale['product_name']); ?></strong>
            </div>

            <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                <span class="text-muted">Product SKU:</span>
                <span class="font-monospace text-dark"><?php echo sanitize($sale['product_sku']); ?></span>
            </div>

            <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                <span class="text-muted">Quantity Dispensed:</span>
                <span class="fw-bold text-dark"><?php echo $sale['quantity']; ?> units</span>
            </div>

            <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                <span class="text-muted">Unit Price:</span>
                <span class="text-dark"><?php echo format_currency($sale['unit_price']); ?></span>
            </div>

            <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                <span class="text-muted">Total Revenue:</span>
                <span class="fw-bold text-success fs-5"><?php echo format_currency($sale['total_price']); ?></span>
            </div>

            <div class="d-flex justify-content-between small">
                <span class="text-muted">Transaction Timestamp:</span>
                <span class="text-dark"><?php echo format_date($sale['sold_at']); ?></span>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="dw-card h-100 p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Staff & Inventory Impact</h5>

            <div class="d-flex justify-content-between mb-3 small border-bottom pb-2">
                <span class="text-muted">Processed By Cashier:</span>
                <strong class="text-dark"><?php echo sanitize($sale['cashier_name']); ?> (<?php echo sanitize($sale['cashier_code']); ?>)</strong>
            </div>

            <div class="d-flex justify-content-between mb-3 small border-bottom pb-2">
                <span class="text-muted">Current Shelf Stock Remaining:</span>
                <strong class="text-dark"><?php echo $sale['remaining_stock']; ?> units</strong>
            </div>

            <div class="p-3 bg-light rounded-3 border">
                <h6 class="fw-bold text-dark mb-1 small text-uppercase">
                    <i class="bi bi-shield-check text-success me-1"></i> Data Continuity Status
                </h6>
                <p class="small text-muted mb-0">
                    This sale was completed locally on the internal LAN database. Change queued in <code>sync_log</code> for cloud backup when internet access returns.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
