<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Sales Staff Transaction & Receipt History Module
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('sales.view');

$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$date_filter = $_GET['date'] ?? '';

// Auto-Suggest Options List
$suggest_raw = $pdo->query("SELECT DISTINCT sale_code FROM sales UNION SELECT DISTINCT name FROM products")->fetchAll(PDO::FETCH_COLUMN);

// Build Query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.sale_code LIKE :search_code OR p.name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params[':search_code'] = "%{$search}%";
    $params[':search_name'] = "%{$search}%";
    $params[':search_sku'] = "%{$search}%";
}

if (!empty($date_filter)) {
    $where_clauses[] = "DATE(s.sold_at) = :date";
    $params[':date'] = $date_filter;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$query = "
    SELECT s.id, s.sale_code, s.product_id, s.quantity, s.unit_price, s.total_price, s.sold_at,
           p.name AS product_name, p.sku AS product_sku,
           u.name AS seller_name
    FROM sales s
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN users u ON s.user_id = u.id
    {$where_sql}
    ORDER BY s.id DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales_list = $stmt->fetchAll();

// Calculate Summary Metrics
$stat_today_count = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(sold_at) = CURRENT_DATE()")->fetchColumn();
$stat_today_rev = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE DATE(sold_at) = CURRENT_DATE()")->fetchColumn();
$stat_lifetime_count = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();

$page_title = "My Sales & Transaction History";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-receipt text-success me-2"></i> Sales & Transaction History
        </h2>
        <p class="text-muted small mb-0">Review processed customer receipts, transaction totals, and reprint sales invoices.</p>
    </div>
    <div>
        <?php if (has_permission('sales.create')): ?>
            <a href="<?php echo BASE_URL; ?>/admin/sales/index.php" class="btn btn-dw-primary btn-sm px-3">
                <i class="bi bi-cart-plus me-1"></i> Open POS Terminal
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Today's Transactions</div>
            <div class="dw-stat-value"><?php echo number_format($stat_today_count); ?></div>
            <span class="small text-muted"><i class="bi bi-receipt-cutoff me-1"></i> Customer Receipts Processed</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Today's Revenue</div>
            <div class="dw-stat-value" style="font-size: 1.6rem;"><?php echo format_currency($stat_today_rev); ?></div>
            <span class="small text-muted"><i class="bi bi-cash-stack me-1"></i> Daily Cash & Card Collections</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Lifetime Receipts</div>
            <div class="dw-stat-value"><?php echo number_format($stat_lifetime_count); ?></div>
            <span class="small text-muted"><i class="bi bi-archive me-1"></i> Recorded Transaction Archives</span>
        </div>
    </div>
</div>

<!-- Search & Filter Bar with Auto-Suggest Datalist -->
<div class="dw-card p-3 mb-4 bg-white">
    <form action="history.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-7">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="search" id="salesSearchInput" list="salesSuggestions" autocomplete="off" placeholder="Type to search receipt code (POS-XXXX), product name, SKU..." value="<?php echo sanitize($search); ?>">
                <datalist id="salesSuggestions">
                    <?php foreach ($suggest_raw as $s_item): ?>
                        <option value="<?php echo sanitize($s_item); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>
        <div class="col-md-3">
            <input type="date" class="form-control form-control-sm" name="date" value="<?php echo sanitize($date_filter); ?>">
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-dw-primary btn-sm w-100">Filter</button>
            <a href="history.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Sales Transactions Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Processed Sales Receipts (<?php echo count($sales_list); ?> Records)</span>
                <span class="badge bg-dark rounded-pill"><?php echo count($sales_list); ?> Receipts</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($sales_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Receipt Code</th>
                                    <th>Medicine Item & SKU</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total Paid</th>
                                    <th>Processed By</th>
                                    <th>Date & Time</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales_list as $s): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="font-monospace text-dark"><?php echo sanitize($s['sale_code']); ?></strong>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block"><?php echo sanitize($s['product_name'] ?? 'Pharmacy Product'); ?></strong>
                                            <span class="small text-muted font-monospace">SKU: <?php echo sanitize($s['product_sku'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo number_format($s['quantity']); ?> Units</span>
                                        </td>
                                        <td class="small text-dark">
                                            <?php echo format_currency($s['unit_price']); ?>
                                        </td>
                                        <td>
                                            <strong class="text-success"><?php echo format_currency($s['total_price']); ?></strong>
                                        </td>
                                        <td class="small text-dark">
                                            <i class="bi bi-person me-1"></i> <?php echo sanitize($s['seller_name'] ?? 'System User'); ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($s['sold_at']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?php echo BASE_URL; ?>/admin/sales/receipt.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-primary btn-sm" target="_blank" title="Print Invoice Receipt">
                                                <i class="bi bi-printer me-1"></i> Print Receipt
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-receipt-cutoff fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No Sales Transactions Found</h5>
                        <p class="mb-0 small">No processed receipts match the selected search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
