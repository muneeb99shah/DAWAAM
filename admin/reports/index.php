<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Reports & Analytics Overview Hub
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('reports.view');

$pdo = get_db_connection();

// KPI Stat Calculations
$stat_lifetime_rev = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM sales")->fetchColumn();
$stat_today_rev = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE DATE(sold_at) = CURRENT_DATE()")->fetchColumn();
$stat_asset_val = $pdo->query("SELECT COALESCE(SUM(price * stock_qty), 0) FROM products")->fetchColumn();
$stat_reorder_count = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= low_stock_threshold")->fetchColumn();

$page_title = "Reports & Analytics Hub";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-graph-up-arrow text-primary me-2"></i> Reports & Business Analytics
        </h2>
        <p class="text-muted small mb-0">Operational performance reports, inventory asset valuation, and executive printable summaries.</p>
    </div>
    <div>
        <a href="print.php" target="_blank" class="btn btn-dw-primary btn-sm">
            <i class="bi bi-printer me-1"></i> Print Executive Summary
        </a>
    </div>
</div>

<!-- KPI Metrics Grid -->
<div class="row g-3 mb-5">
    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Lifetime Revenue</div>
            <div class="dw-stat-value" style="font-size: 1.5rem;"><?php echo format_currency($stat_lifetime_rev); ?></div>
            <span class="small text-muted"><i class="bi bi-cash-stack me-1"></i> Total POS Income</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Inventory Asset Value</div>
            <div class="dw-stat-value" style="font-size: 1.5rem;"><?php echo format_currency($stat_asset_val); ?></div>
            <span class="small text-muted"><i class="bi bi-box me-1"></i> Total Shelf Valuation</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Today's Revenue</div>
            <div class="dw-stat-value" style="font-size: 1.5rem;"><?php echo format_currency($stat_today_rev); ?></div>
            <span class="small text-muted"><i class="bi bi-calendar-check me-1"></i> <?php echo date('d M Y'); ?></span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card <?php echo $stat_reorder_count > 0 ? 'stat-amber' : ''; ?>">
            <div class="dw-stat-label">Reorder Warning List</div>
            <div class="dw-stat-value"><?php echo number_format($stat_reorder_count); ?></div>
            <span class="small text-muted"><i class="bi bi-exclamation-triangle me-1"></i> Items &le; Threshold</span>
        </div>
    </div>
</div>

<!-- Report Module Access Grid -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="dw-card h-100 p-4 d-flex flex-column">
            <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle d-inline-block mb-3" style="width: 54px; height: 54px;">
                <i class="bi bi-receipt fs-3"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Sales & Revenue Report</h5>
            <p class="small text-muted flex-grow-1">Analyze daily sales, total dispensed medicine units, best-selling items, and custom date range revenue.</p>
            <a href="sales.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                <i class="bi bi-arrow-right me-1"></i> Open Sales Report
            </a>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="dw-card h-100 p-4 d-flex flex-column">
            <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle d-inline-block mb-3" style="width: 54px; height: 54px;">
                <i class="bi bi-box-seam fs-3"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Inventory Asset Valuation</h5>
            <p class="small text-muted flex-grow-1">Track shelf asset values in PKR, review healthy vs out-of-stock items, and export low stock reorder lists.</p>
            <a href="inventory.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                <i class="bi bi-arrow-right me-1"></i> Open Stock Report
            </a>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="dw-card h-100 p-4 d-flex flex-column">
            <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle d-inline-block mb-3" style="width: 54px; height: 54px;">
                <i class="bi bi-bell fs-3"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Alert Engine Analytics</h5>
            <p class="small text-muted flex-grow-1">Review urgent alert triggers, pending SMS dispatch queues, and cellular SIM delivery ratios.</p>
            <a href="alerts.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                <i class="bi bi-arrow-right me-1"></i> Open Alert Report
            </a>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="dw-card h-100 p-4 d-flex flex-column">
            <div class="bg-dark bg-opacity-10 text-dark p-3 rounded-circle d-inline-block mb-3" style="width: 54px; height: 54px;">
                <i class="bi bi-printer fs-3"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Printable Executive Summary</h5>
            <p class="small text-muted flex-grow-1">Generate a clean, print-friendly 1-page business continuity summary for pharmacy owners.</p>
            <a href="print.php" target="_blank" class="btn btn-dw-primary btn-sm w-100 mt-2">
                <i class="bi bi-printer me-1"></i> Print Summary
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
