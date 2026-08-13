<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Sales Analytics Report
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('reports.view');

$pdo = get_db_connection();

// Date Range Parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default: Beginning of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d');      // Default: Today

// 1. Overall Aggregated Metrics
$stmt_agg = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_transactions,
        COALESCE(SUM(quantity), 0) AS total_units,
        COALESCE(SUM(total_price), 0) AS total_revenue,
        COALESCE(AVG(total_price), 0) AS avg_receipt_value
    FROM sales
    WHERE DATE(sold_at) BETWEEN :start_date AND :end_date
");
$stmt_agg->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$agg = $stmt_agg->fetch();

// 2. Top-Selling Products
$stmt_top = $pdo->prepare("
    SELECT p.name AS product_name, p.sku AS product_sku, 
           SUM(s.quantity) AS units_sold, SUM(s.total_price) AS product_revenue
    FROM sales s
    INNER JOIN products p ON s.product_id = p.id
    WHERE DATE(s.sold_at) BETWEEN :start_date AND :end_date
    GROUP BY s.product_id, p.name, p.sku
    ORDER BY product_revenue DESC
    LIMIT 5
");
$stmt_top->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$top_products = $stmt_top->fetchAll();

// 3. Daily Breakdown List
$stmt_daily = $pdo->prepare("
    SELECT DATE(sold_at) AS sale_date,
           COUNT(*) AS transactions_count,
           SUM(quantity) AS units_sold,
           SUM(total_price) AS daily_revenue
    FROM sales
    WHERE DATE(sold_at) BETWEEN :start_date AND :end_date
    GROUP BY DATE(sold_at)
    ORDER BY sale_date DESC
");
$stmt_daily->execute([':start_date' => $start_date, ':end_date' => $end_date]);
$daily_breakdown = $stmt_daily->fetchAll();

$page_title = "Sales Analytics Report";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-receipt text-success me-2"></i> Sales & Revenue Analytical Report
        </h2>
        <p class="text-muted small mb-0">Period revenue breakdown, transaction volumes, and top-selling medicines.</p>
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

<!-- Date Range Filter Form -->
<div class="dw-card p-3 mb-4 bg-white">
    <form action="sales.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <label class="form-label small fw-semibold text-muted mb-1">Start Date</label>
            <input type="date" class="form-control form-control-sm" name="start_date" value="<?php echo sanitize($start_date); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold text-muted mb-1">End Date</label>
            <input type="date" class="form-control form-control-sm" name="end_date" value="<?php echo sanitize($end_date); ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end pt-3">
            <button type="submit" class="btn btn-dw-primary btn-sm w-100 me-2">
                <i class="bi bi-filter me-1"></i> Apply Filter
            </button>
            <a href="sales.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Aggregated KPI Summary Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Period Revenue</div>
            <div class="dw-stat-value" style="font-size: 1.5rem;"><?php echo format_currency($agg['total_revenue']); ?></div>
            <span class="small text-muted"><i class="bi bi-cash-stack me-1"></i> Total Income</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Total Transactions</div>
            <div class="dw-stat-value"><?php echo number_format($agg['total_transactions']); ?></div>
            <span class="small text-muted"><i class="bi bi-file-text me-1"></i> Receipt Count</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Dispensed Units</div>
            <div class="dw-stat-value"><?php echo number_format($agg['total_units']); ?></div>
            <span class="small text-muted"><i class="bi bi-box me-1"></i> Medicine Quantity</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Average Receipt Value</div>
            <div class="dw-stat-value" style="font-size: 1.3rem;"><?php echo format_currency($agg['avg_receipt_value']); ?></div>
            <span class="small text-muted"><i class="bi bi-calculator me-1"></i> Per Basket Size</span>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Top 5 Best Sellers -->
    <div class="col-lg-5">
        <div class="dw-card h-100 p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                <i class="bi bi-trophy text-warning me-2"></i> Top 5 Best-Selling Medicines
            </h5>

            <?php if (count($top_products) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Medicine Name</th>
                                <th class="text-center">Units</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $tp): ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark d-block"><?php echo sanitize($tp['product_name']); ?></strong>
                                        <span class="badge bg-light text-dark border font-monospace small"><?php echo sanitize($tp['product_sku']); ?></span>
                                    </td>
                                    <td class="text-center fw-bold text-dark"><?php echo $tp['units_sold']; ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo format_currency($tp['product_revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">No sales recorded for this period.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Daily Breakdown Table -->
    <div class="col-lg-7">
        <div class="dw-card h-100 p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                <i class="bi bi-calendar-range text-primary me-2"></i> Daily Sales Breakdown
            </h5>

            <?php if (count($daily_breakdown) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th class="text-center">Receipts</th>
                                <th class="text-center">Units Sold</th>
                                <th class="text-end">Daily Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_breakdown as $db): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo date('d M Y (D)', strtotime($db['sale_date'])); ?></td>
                                    <td class="text-center"><?php echo $db['transactions_count']; ?></td>
                                    <td class="text-center"><?php echo $db['units_sold']; ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo format_currency($db['daily_revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">No daily data available for selected date range.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
