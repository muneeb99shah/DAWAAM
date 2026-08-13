<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Inventory Stock & Valuation Report
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('reports.view');

$pdo = get_db_connection();

// 1. Overall Asset Valuation & Stock Counts
$stat_total_val = $pdo->query("SELECT COALESCE(SUM(price * stock_qty), 0) FROM products")->fetchColumn();
$stat_total_items = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stat_low = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= low_stock_threshold AND stock_qty > 0")->fetchColumn();
$stat_out = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty = 0")->fetchColumn();

// 2. Reorder Action List (Stock <= Threshold)
$stmt_reorder = $pdo->query("
    SELECT p.id, p.name, p.sku, p.price, p.stock_qty, p.low_stock_threshold, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock_qty <= p.low_stock_threshold
    ORDER BY p.stock_qty ASC
");
$reorder_list = $stmt_reorder->fetchAll();

// 3. Category Breakdown
$stmt_cat = $pdo->query("
    SELECT c.name AS category_name, 
           COUNT(p.id) AS total_items, 
           COALESCE(SUM(p.stock_qty), 0) AS total_units,
           COALESCE(SUM(p.price * p.stock_qty), 0) AS category_valuation
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY category_valuation DESC
");
$category_breakdown = $stmt_cat->fetchAll();

$page_title = "Inventory Valuation & Stock Report";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-box-seam text-warning me-2"></i> Inventory Stock & Asset Valuation Report
        </h2>
        <p class="text-muted small mb-0">Total shelf asset value in PKR, category distribution, and low stock reorder list.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Reports Hub
        </a>
        <button onclick="window.print();" class="btn btn-dw-primary btn-sm">
            <i class="bi bi-printer me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Aggregated KPI Summary Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Total Inventory Asset Value</div>
            <div class="dw-stat-value" style="font-size: 1.5rem;"><?php echo format_currency($stat_total_val); ?></div>
            <span class="small text-muted"><i class="bi bi-cash-stack me-1"></i> Total Shelf Value</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Catalog Product Items</div>
            <div class="dw-stat-value"><?php echo number_format($stat_total_items); ?></div>
            <span class="small text-muted"><i class="bi bi-capsule me-1"></i> Active SKUs</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-amber">
            <div class="dw-stat-label">Low Stock Items</div>
            <div class="dw-stat-value"><?php echo number_format($stat_low); ?></div>
            <span class="small text-muted"><i class="bi bi-exclamation-triangle me-1"></i> &le; Threshold</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-rose">
            <div class="dw-stat-label">Out of Stock Items</div>
            <div class="dw-stat-value"><?php echo number_format($stat_out); ?></div>
            <span class="small text-muted"><i class="bi bi-x-circle me-1"></i> 0 Shelf Stock</span>
        </div>
    </div>
</div>

<!-- Reorder Action List Table -->
<div class="row mb-4">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Low Stock Reorder Action List (<?php echo count($reorder_list); ?> Items Below Threshold)</span>
                <span class="badge bg-danger rounded-pill"><?php echo count($reorder_list); ?> Reorder Required</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($reorder_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Product Name & SKU</th>
                                    <th>Category</th>
                                    <th>Unit Price</th>
                                    <th>Current Shelf Stock</th>
                                    <th>Alert Threshold</th>
                                    <th>Reorder Urgency</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reorder_list as $r): ?>
                                    <?php $is_out = ($r['stock_qty'] == 0); ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark d-block"><?php echo sanitize($r['name']); ?></strong>
                                            <span class="badge bg-light text-dark border font-monospace small"><?php echo sanitize($r['sku']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo sanitize($r['category_name'] ?? 'Unassigned'); ?></span>
                                        </td>
                                        <td class="fw-bold text-dark"><?php echo format_currency($r['price']); ?></td>
                                        <td class="fw-bold fs-6 text-dark"><?php echo $r['stock_qty']; ?> units</td>
                                        <td class="small text-muted">&le; <?php echo $r['low_stock_threshold']; ?> units</td>
                                        <td>
                                            <?php if ($is_out): ?>
                                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> CRITICAL: OUT OF STOCK</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> LOW STOCK REORDER</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle fs-2 d-block mb-2 text-success"></i>
                        <p class="mb-0 small">All inventory items are currently above alert thresholds.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Category Valuation Breakdown Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header">
                <span>Category Inventory Valuation Breakdown</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Category Name</th>
                                <th class="text-center">Total SKUs</th>
                                <th class="text-center">Total Available Units</th>
                                <th class="text-end pe-4">Category Asset Valuation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category_breakdown as $cb): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo sanitize($cb['category_name']); ?></td>
                                    <td class="text-center"><?php echo $cb['total_items']; ?></td>
                                    <td class="text-center fw-bold text-dark"><?php echo number_format($cb['total_units']); ?></td>
                                    <td class="text-end pe-4 fw-bold text-success"><?php echo format_currency($cb['category_valuation']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
