<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Product Management Module (Index / Listing)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alerts.php';

require_permission('products.view');

$pdo = get_db_connection();

// Filters & Search Parameters
$search = trim($_GET['search'] ?? '');
$category_filter = (int)($_GET['category'] ?? 0);

// Fetch Categories for dropdown filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Fetch Auto-Suggest Suggestions List (Limited to top 50 entries for high DOM rendering performance)
$suggest_raw = $pdo->query("(SELECT name FROM products ORDER BY name ASC LIMIT 30) UNION (SELECT sku FROM products WHERE sku IS NOT NULL ORDER BY sku ASC LIMIT 20)")->fetchAll(PDO::FETCH_COLUMN);

// Build Query with PDO prepared statements
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(p.name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params[':search_name'] = '%' . $search . '%';
    $params[':search_sku'] = '%' . $search . '%';
}

if ($category_filter > 0) {
    $where_clauses[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 15);

$paginated_res = get_paginated_data($pdo, [
    'table' => 'products p LEFT JOIN categories c ON p.category_id = c.id',
    'select_fields' => 'p.id, p.name, p.sku, p.price, p.stock_qty, p.low_stock_threshold, p.created_at, c.name AS category_name',
    'where_clause' => count($where_clauses) > 0 ? implode(" AND ", $where_clauses) : '',
    'params' => $params,
    'order_by' => 'p.id DESC',
    'page' => $page,
    'limit' => $limit,
    'count_field' => 'p.id'
]);

$products = $paginated_res['data'];
$pagination = $paginated_res['pagination'];

$page_title = "Product Management";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-capsule text-primary me-2"></i> Product & Medicine Inventory
        </h2>
        <p class="text-muted small mb-0">Manage pilot pharmacy products, stock quantities, and low-stock alert thresholds.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>/admin/inventory/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-seam me-1"></i> Stock Monitor
        </a>
        <?php if (has_permission('products.create')): ?>
            <a href="create.php" class="btn btn-dw-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add New Product
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search & Category Filter Form with Live Datalist Auto-Suggest -->
<div class="dw-card dw-filter-card mb-3">
    <form id="products-filter-form" action="index.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control form-control-sm" name="search" id="productSearchInput" list="productSuggestions" autocomplete="off" value="<?php echo sanitize($search); ?>" placeholder="Type medicine name or SKU code...">
                <datalist id="productSuggestions">
                    <?php foreach ($suggest_raw as $s_item): ?>
                        <option value="<?php echo sanitize($s_item); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>

        <div class="col-md-4">
            <select class="form-select form-select-sm" name="category">
                <option value="0">-- All Categories --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter === (int)$cat['id'] ? 'selected' : ''; ?>>
                        <?php echo sanitize($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-dw-primary btn-sm w-100">
                <i class="bi bi-filter me-1"></i> Apply Filter
            </button>
            <?php if (!empty($search) || $category_filter > 0): ?>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Product Listing (<?php echo count($products); ?> Items)</span>
                <span class="badge bg-dark rounded-pill"><?php echo count($products); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($products) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Product Info</th>
                                    <th>Category</th>
                                    <th>Unit Price</th>
                                    <th>Stock Level</th>
                                    <th>Threshold</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <?php 
                                        $is_low = ($p['stock_qty'] <= $p['low_stock_threshold']);
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark d-block"><?php echo sanitize($p['name']); ?></strong>
                                            <span class="badge bg-light text-dark border font-monospace small">
                                                SKU: <?php echo sanitize($p['sku'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php echo sanitize($p['category_name'] ?? 'Unassigned'); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?php echo format_currency($p['price']); ?>
                                        </td>
                                        <td>
                                            <?php if ($is_low): ?>
                                                <span class="badge bg-danger px-2 py-1">
                                                    <i class="bi bi-exclamation-triangle me-1"></i> LOW (<?php echo $p['stock_qty']; ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success px-2 py-1">
                                                    <?php echo $p['stock_qty']; ?> units
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            Alert at &le; <?php echo $p['low_stock_threshold']; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-info" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if (has_permission('inventory.adjust')): ?>
                                                    <a href="../inventory/adjustment.php?product_id=<?php echo $p['id']; ?>" class="btn btn-outline-warning" title="Adjust Stock">
                                                        <i class="bi bi-box-seam"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (has_permission('products.edit')): ?>
                                                    <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-primary" title="Edit Product">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (has_permission('products.delete')): ?>
                                                    <a href="delete.php?id=<?php echo $p['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" onclick="return confirm('Are you sure you want to delete this product?');" class="btn btn-outline-danger" title="Delete Product">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination_links($pagination, 'index.php', ['search' => $search, 'category' => $category_filter]); ?>
                <?php else: ?>
                    <div class="dw-empty-state">
                        <i class="bi bi-capsule dw-empty-state-icon"></i>
                        <div class="dw-empty-state-title">No Products Found</div>
                        <div class="dw-empty-state-text">No product records match your current search parameters. Clear your filters or add a new medicine item.</div>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
                            <?php if (has_permission('products.create')): ?>
                                <a href="create.php" class="btn btn-dw-primary btn-sm">
                                    <i class="bi bi-plus-lg me-1"></i> Add New Product
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/datatable_helper.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    DawaamDataTable.attachDebouncedSearch('#productSearchInput', '#products-filter-form', 350);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
