<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Inventory Stock Monitor Module
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('inventory.view');

$pdo = get_db_connection();

$filter_status = $_GET['filter'] ?? 'all'; // 'all', 'low', 'out'

// Fetch Statistics
$stat_total = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stat_in_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty > low_stock_threshold")->fetchColumn();
$stat_low = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= low_stock_threshold AND stock_qty > 0")->fetchColumn();
$stat_out = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty = 0")->fetchColumn();

// Query Products based on Filter with High-Performance Server-Side Pagination
$where_clauses = [];
$params = [];

if ($filter_status === 'low') {
    $where_clauses[] = "p.stock_qty <= p.low_stock_threshold AND p.stock_qty > 0";
} elseif ($filter_status === 'out') {
    $where_clauses[] = "p.stock_qty = 0";
}

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 15);

$paginated_res = get_paginated_data($pdo, [
    'table' => 'products p LEFT JOIN categories c ON p.category_id = c.id',
    'select_fields' => 'p.id, p.name, p.sku, p.price, p.stock_qty, p.low_stock_threshold, p.updated_at, c.name AS category_name',
    'where_clause' => count($where_clauses) > 0 ? implode(" AND ", $where_clauses) : '',
    'params' => $params,
    'order_by' => '(p.stock_qty <= p.low_stock_threshold) DESC, p.stock_qty ASC',
    'page' => $page,
    'limit' => $limit,
    'count_field' => 'p.id'
]);

$products = $paginated_res['data'];
$pagination = $paginated_res['pagination'];

$page_title = "Inventory Stock Monitor";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-box-seam text-warning me-2"></i> Inventory Stock Monitor
        </h2>
        <p class="text-muted small mb-0">Track shelf quantities, out-of-stock items, and threshold alerts.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/admin/products/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-capsule me-1"></i> Product Catalog
        </a>
        <?php if (has_permission('inventory.adjust')): ?>
            <a href="adjustment.php" class="btn btn-dw-primary btn-sm">
                <i class="bi bi-plus-slash-minus me-1"></i> Adjust Stock Level
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Metrics Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="index.php?filter=all" class="text-decoration-none">
            <div class="dw-stat-card <?php echo $filter_status === 'all' ? 'border-primary shadow' : ''; ?>">
                <div class="dw-stat-label">Total Items</div>
                <div class="dw-stat-value"><?php echo number_format($stat_total); ?></div>
                <span class="small text-muted">All Catalog Products</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?filter=all" class="text-decoration-none">
            <div class="dw-stat-card stat-emerald">
                <div class="dw-stat-label">Healthy Stock</div>
                <div class="dw-stat-value"><?php echo number_format($stat_in_stock); ?></div>
                <span class="small text-muted">Above Alert Threshold</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?filter=low" class="text-decoration-none">
            <div class="dw-stat-card stat-amber <?php echo $filter_status === 'low' ? 'shadow' : ''; ?>">
                <div class="dw-stat-label">Low Stock Items</div>
                <div class="dw-stat-value"><?php echo number_format($stat_low); ?></div>
                <span class="small text-muted">&le; Alert Threshold</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?filter=out" class="text-decoration-none">
            <div class="dw-stat-card stat-rose <?php echo $filter_status === 'out' ? 'shadow' : ''; ?>">
                <div class="dw-stat-label">Out of Stock</div>
                <div class="dw-stat-value"><?php echo number_format($stat_out); ?></div>
                <span class="small text-muted">0 Units Remaining</span>
            </div>
        </a>
    </div>
</div>

<!-- Stock Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Stock Levels (<?php echo count($products); ?> Items)</span>
                <div class="btn-group btn-group-sm">
                    <a href="index.php?filter=all" class="btn <?php echo $filter_status === 'all' ? 'btn-dark' : 'btn-outline-secondary'; ?>">All</a>
                    <a href="index.php?filter=low" class="btn <?php echo $filter_status === 'low' ? 'btn-warning' : 'btn-outline-secondary'; ?>">Low Stock</a>
                    <a href="index.php?filter=out" class="btn <?php echo $filter_status === 'out' ? 'btn-danger' : 'btn-outline-secondary'; ?>">Out of Stock</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($products) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Product Name & SKU</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Low Threshold</th>
                                    <th>Stock Condition</th>
                                    <th>Last Updated</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <?php 
                                        $is_out = ($p['stock_qty'] == 0);
                                        $is_low = ($p['stock_qty'] <= $p['low_stock_threshold']);
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark d-block"><?php echo sanitize($p['name']); ?></strong>
                                            <span class="badge bg-light text-dark border font-monospace small">
                                                <?php echo sanitize($p['sku'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?php echo sanitize($p['category_name'] ?? 'Unassigned'); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold fs-6 text-dark">
                                            <?php echo $p['stock_qty']; ?> units
                                        </td>
                                        <td class="small text-muted">
                                            Alert at &le; <?php echo $p['low_stock_threshold']; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_out): ?>
                                                <span class="badge bg-danger px-2 py-1">
                                                    <i class="bi bi-x-circle me-1"></i> OUT OF STOCK
                                                </span>
                                            <?php elseif ($is_low): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">
                                                    <i class="bi bi-exclamation-triangle me-1"></i> LOW STOCK
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Healthy
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($p['updated_at']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if (has_permission('inventory.adjust')): ?>
                                                <a href="adjustment.php?product_id=<?php echo $p['id']; ?>" class="btn btn-outline-warning btn-sm">
                                                    <i class="bi bi-plus-slash-minus me-1"></i> Adjust
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination_links($pagination, 'index.php', ['filter' => $filter_status]); ?>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-box-seam fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No Items Found</h5>
                        <p class="mb-0 small">No inventory items match the selected filter condition.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
