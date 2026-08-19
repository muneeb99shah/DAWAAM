<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Sales History & Transaction Log Module
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('sales.view');

$pdo = get_db_connection();

// KPI Stats (Single Aggregated Query using Indexable Date Range)
$sales_kpi = $pdo->query("
    SELECT 
        COALESCE(SUM(total_price), 0) AS total_revenue,
        COUNT(*) AS total_count,
        COALESCE(SUM(CASE WHEN sold_at >= CURRENT_DATE() THEN total_price ELSE 0 END), 0) AS today_revenue,
        COALESCE(SUM(CASE WHEN sold_at >= CURRENT_DATE() THEN 1 ELSE 0 END), 0) AS today_count
    FROM sales
")->fetch();

$stat_today_revenue = $sales_kpi['today_revenue'];
$stat_today_count = $sales_kpi['today_count'];
$stat_total_revenue = $sales_kpi['total_revenue'];
$stat_total_count = $sales_kpi['total_count'];

// Search & Filter
$search = trim($_GET['search'] ?? '');
$date_filter = trim($_GET['date'] ?? '');

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.sale_code LIKE :search OR p.name LIKE :search OR u.name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($date_filter)) {
    $where_clauses[] = "s.sold_at >= :date_start AND s.sold_at <= :date_end";
    $params[':date_start'] = $date_filter . ' 00:00:00';
    $params[':date_end'] = $date_filter . ' 23:59:59';
}

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 15);

$paginated_res = get_paginated_data($pdo, [
    'table' => 'sales s INNER JOIN products p ON s.product_id = p.id INNER JOIN users u ON s.user_id = u.id',
    'select_fields' => 's.id, s.sale_code, s.quantity, s.unit_price, s.total_price, s.sold_at, p.name AS product_name, p.sku AS product_sku, u.name AS cashier_name, u.username AS cashier_username',
    'where_clause' => count($where_clauses) > 0 ? implode(" AND ", $where_clauses) : '',
    'params' => $params,
    'order_by' => 's.id DESC',
    'page' => $page,
    'limit' => $limit,
    'count_field' => 's.id',
    'primary_key' => 's.id'
]);

$sales = $paginated_res['data'];
$pagination = $paginated_res['pagination'];

$page_title = "Sales History & POS Transactions";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-cart-check text-success me-2"></i> Sales History & POS Transactions
        </h2>
        <p class="text-muted small mb-0">View recorded local sales receipts, cashier logs, and daily revenue.</p>
    </div>
    <div>
        <?php if (has_permission('sales.create')): ?>
            <a href="create.php" class="btn btn-dw-primary btn-sm px-3">
                <i class="bi bi-calculator me-1"></i> Open Local POS Terminal
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Metrics Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="index.php?date=<?php echo date('Y-m-d'); ?>" class="dw-stat-card stat-emerald" title="Filter Sales History to Today's Transactions">
            <div class="dw-stat-label">Today's Revenue</div>
            <div class="dw-stat-value"><?php echo format_currency($stat_today_revenue); ?></div>
            <div class="dw-stat-sub"><i class="bi bi-calendar-check text-emerald"></i> <?php echo date('d M Y'); ?></div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?date=<?php echo date('Y-m-d'); ?>" class="dw-stat-card stat-emerald" title="Filter Sales History to Today's Transactions">
            <div class="dw-stat-label">Today's Sales Count</div>
            <div class="dw-stat-value"><?php echo number_format($stat_today_count); ?></div>
            <div class="dw-stat-sub"><i class="bi bi-receipt text-primary"></i> Transactions Today</div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="<?php echo BASE_URL; ?>/admin/reports/sales.php" class="dw-stat-card stat-sky" title="View Detailed Financial Revenue Analytics">
            <div class="dw-stat-label">Lifetime Revenue</div>
            <div class="dw-stat-value"><?php echo format_currency($stat_total_revenue); ?></div>
            <div class="dw-stat-sub"><i class="bi bi-cash-stack text-info"></i> Total Sales Income</div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php" class="dw-stat-card" title="View Full Receipts Log">
            <div class="dw-stat-label">Total Receipts</div>
            <div class="dw-stat-value"><?php echo number_format($stat_total_count); ?></div>
            <div class="dw-stat-sub"><i class="bi bi-file-text text-secondary"></i> Lifetime Records</div>
        </a>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="dw-card dw-filter-card mb-3">
    <form id="sales-filter-form" action="index.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="sales-search-input" class="form-control form-control-sm" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search sale code, product or cashier...">
            </div>
        </div>

        <div class="col-md-4">
            <input type="date" class="form-control form-control-sm" name="date" value="<?php echo sanitize($date_filter); ?>">
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-dw-primary btn-sm w-100">
                <i class="bi bi-filter me-1"></i> Filter Logs
            </button>
            <?php if (!empty($search) || !empty($date_filter)): ?>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Sales Transactions Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Completed Sales Receipts (<?php echo count($sales); ?> Records)</span>
                <span class="badge bg-dark rounded-pill"><?php echo count($sales); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($sales) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Sale Code</th>
                                    <th>Item Name & SKU</th>
                                    <th>Cashier</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total Amount</th>
                                    <th>Date / Time</th>
                                    <th class="text-end pe-4">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $s): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark font-monospace"><?php echo sanitize($s['sale_code']); ?></strong>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block"><?php echo sanitize($s['product_name']); ?></strong>
                                            <span class="badge bg-light text-dark border font-monospace small">
                                                <?php echo sanitize($s['product_sku'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="small text-muted">
                                                <i class="bi bi-person me-1"></i> <?php echo sanitize($s['cashier_name']); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?php echo $s['quantity']; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_currency($s['unit_price']); ?>
                                        </td>
                                        <td class="fw-bold text-success">
                                            <?php echo format_currency($s['total_price']); ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($s['sold_at']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="receipt.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-primary btn-sm" title="Print Receipt">
                                                <i class="bi bi-printer me-1"></i> Receipt
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination_links($pagination, 'index.php', ['search' => $search, 'date' => $date_filter]); ?>
                <?php else: ?>
                    <div class="dw-empty-state">
                        <i class="bi bi-receipt dw-empty-state-icon"></i>
                        <div class="dw-empty-state-title">No Sales Transactions Found</div>
                        <div class="dw-empty-state-text">No sale receipts match your search parameters. Try clearing your filters or process a new checkout.</div>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
                            <?php if (has_permission('sales.create')): ?>
                                <a href="create.php" class="btn btn-dw-primary btn-sm">
                                    <i class="bi bi-cart-check me-1"></i> Open POS Terminal
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
    DawaamDataTable.attachDebouncedSearch('#sales-search-input', '#sales-filter-form', 350);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
