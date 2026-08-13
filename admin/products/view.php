<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - View Product Details
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('products.view');

$product_id = (int)($_GET['id'] ?? 0);
if ($product_id <= 0) {
    set_flash_message('danger', 'Invalid product ID.');
    redirect('admin/products/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.description AS category_description
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $product_id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash_message('danger', 'Product not found.');
    redirect('admin/products/index.php');
}

// Fetch Alerts for this product
$stmt_alerts = $pdo->prepare("
    SELECT * FROM alerts 
    WHERE product_id = :id 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt_alerts->execute([':id' => $product_id]);
$alerts = $stmt_alerts->fetchAll();

// Fetch Sales Summary for this product
$stmt_sales = $pdo->prepare("
    SELECT COUNT(*) AS total_sales, COALESCE(SUM(quantity), 0) AS units_sold, COALESCE(SUM(total_price), 0) AS total_revenue
    FROM sales 
    WHERE product_id = :id
");
$stmt_sales->execute([':id' => $product_id]);
$sales_stat = $stmt_sales->fetch();

$is_low = ($product['stock_qty'] <= $product['low_stock_threshold']);

$page_title = "View Product - " . sanitize($product['name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-box-seam text-primary me-2"></i> <?php echo sanitize($product['name']); ?>
        </h2>
        <span class="badge bg-light text-dark border font-monospace me-2">SKU: <?php echo sanitize($product['sku']); ?></span>
        <span class="badge bg-info text-dark"><?php echo sanitize($product['category_name'] ?? 'Unassigned'); ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Products
        </a>
        <?php if (has_permission('inventory.adjust')): ?>
            <a href="../inventory/adjustment.php?product_id=<?php echo $product['id']; ?>" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-box-seam me-1"></i> Adjust Stock
            </a>
        <?php endif; ?>
        <?php if (has_permission('products.edit')): ?>
            <a href="edit.php?id=<?php echo $product['id']; ?>" class="btn btn-dw-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Product
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Product Overview Cards -->
    <div class="col-md-4">
        <div class="dw-card h-100 p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Stock Condition</h5>
            
            <div class="mb-3 text-center p-3 rounded-3 <?php echo $is_low ? 'bg-danger bg-opacity-10 border border-danger' : 'bg-success bg-opacity-10 border border-success'; ?>">
                <div class="fs-1 fw-bold <?php echo $is_low ? 'text-danger' : 'text-success'; ?>">
                    <?php echo $product['stock_qty']; ?>
                </div>
                <div class="small fw-semibold <?php echo $is_low ? 'text-danger' : 'text-success'; ?>">
                    <?php echo $is_low ? 'CRITICAL LOW STOCK' : 'Available Units On Shelf'; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                <span class="text-muted">Low Stock Alert Threshold:</span>
                <span class="fw-bold text-dark">&le; <?php echo $product['low_stock_threshold']; ?> units</span>
            </div>

            <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                <span class="text-muted">Unit Price:</span>
                <span class="fw-bold text-dark"><?php echo format_currency($product['price']); ?></span>
            </div>

            <div class="d-flex justify-content-between small">
                <span class="text-muted">Registered On:</span>
                <span class="text-dark"><?php echo format_date($product['created_at']); ?></span>
            </div>
        </div>
    </div>

    <!-- Sales & Revenue Stat -->
    <div class="col-md-8">
        <div class="dw-card h-100 p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Lifetime Sales Performance</h5>

            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="dw-stat-card">
                        <div class="dw-stat-label">Total Transactions</div>
                        <div class="dw-stat-value"><?php echo number_format($sales_stat['total_sales']); ?></div>
                        <span class="small text-muted">Completed Sales</span>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="dw-stat-card stat-emerald">
                        <div class="dw-stat-label">Units Sold</div>
                        <div class="dw-stat-value"><?php echo number_format($sales_stat['units_sold']); ?></div>
                        <span class="small text-muted">Total Quantity Dispensed</span>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="dw-stat-card">
                        <div class="dw-stat-label">Total Revenue</div>
                        <div class="dw-stat-value" style="font-size: 1.4rem;"><?php echo format_currency($sales_stat['total_revenue']); ?></div>
                        <span class="small text-muted">Sales Income</span>
                    </div>
                </div>
            </div>

            <!-- Recent Alert Log for this Product -->
            <h6 class="fw-bold text-dark mb-2">Urgent Alert History</h6>
            <?php if (count($alerts) > 0): ?>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($alerts as $alt): ?>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-warning text-dark me-2"><?php echo sanitize($alt['type']); ?></span>
                                <span class="text-dark"><?php echo sanitize($alt['message']); ?></span>
                            </div>
                            <span class="text-muted small"><?php echo format_date($alt['created_at']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="small text-muted mb-0"><i class="bi bi-check-circle text-success me-1"></i> No urgent alerts triggered for this item.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
