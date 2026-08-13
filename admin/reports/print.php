<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Printable Executive Summary Report
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('reports.view');

$pdo = get_db_connection();

// Aggregate Data
$lifetime_rev = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM sales")->fetchColumn();
$today_rev = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE DATE(sold_at) = CURRENT_DATE()")->fetchColumn();
$sales_count = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$asset_val = $pdo->query("SELECT COALESCE(SUM(price * stock_qty), 0) FROM products")->fetchColumn();
$product_count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_count = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= low_stock_threshold")->fetchColumn();
$pending_alerts = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_sent = 0")->fetchColumn();
$unsynced_logs = $pdo->query("SELECT COUNT(*) FROM sync_log WHERE synced = 0")->fetchColumn();
$conflicts_count = $pdo->query("SELECT COUNT(*) FROM sync_conflicts WHERE status = 'unresolved'")->fetchColumn();

// Fetch Reorder Items
$reorder_items = $pdo->query("
    SELECT name, sku, stock_qty, low_stock_threshold, price 
    FROM products 
    WHERE stock_qty <= low_stock_threshold 
    ORDER BY stock_qty ASC 
    LIMIT 10
")->fetchAll();

$page_title = "Executive Summary Report";
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@media print {
    .dw-navbar, .dw-footer, .no-print {
        display: none !important;
    }
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    .print-box {
        box-shadow: none !important;
        border: 1px solid #000000 !important;
    }
}
</style>

<div class="row justify-content-center mb-5">
    <div class="col-md-9 col-lg-8">
        <!-- Print Action Bar -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Reports Hub
            </a>
            <button onclick="window.print();" class="btn btn-dw-primary btn-sm px-4">
                <i class="bi bi-printer me-1"></i> Print Executive Summary
            </button>
        </div>

        <!-- Printable Document Container -->
        <div class="dw-card p-4 p-md-5 bg-white shadow-sm print-box">
            <!-- Executive Header -->
            <div class="text-center pb-4 mb-4 border-bottom border-2 border-dark">
                <div class="d-inline-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-shield-check text-success fs-2"></i>
                    <h3 class="fw-bold mb-0 text-dark"><?php echo APP_NAME; ?> BUSINESS CONTINUITY SYSTEM</h3>
                </div>
                <div class="lead fw-semibold text-dark fs-6">Executive Pharmacy Operational Summary</div>
                <div class="small text-muted">Quetta Medical & Continuity Center &bull; MA Jinnah Road, Quetta, Balochistan</div>
                <div class="small text-muted font-monospace mt-1">Generated: <?php echo date('F d, Y \a\t H:i A'); ?></div>
            </div>

            <!-- 1. Executive Performance Metrics -->
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">1. Sales & Asset Valuation Summary</h5>
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="p-3 bg-light rounded border">
                        <span class="text-muted small d-block">Lifetime Sales Revenue</span>
                        <strong class="fs-5 text-dark"><?php echo format_currency($lifetime_rev); ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-light rounded border">
                        <span class="text-muted small d-block">Today's Revenue</span>
                        <strong class="fs-5 text-dark"><?php echo format_currency($today_rev); ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-light rounded border">
                        <span class="text-muted small d-block">Inventory Shelf Asset Value</span>
                        <strong class="fs-5 text-dark"><?php echo format_currency($asset_val); ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-light rounded border">
                        <span class="text-muted small d-block">Total Lifetime Sales Receipts</span>
                        <strong class="fs-5 text-dark"><?php echo number_format($sales_count); ?> transactions</strong>
                    </div>
                </div>
            </div>

            <!-- 2. Reorder Action List -->
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">2. Low Stock Reorder List (&le; Threshold)</h5>
            <?php if (count($reorder_items) > 0): ?>
                <table class="table table-sm table-bordered align-middle mb-4 small">
                    <thead class="table-light">
                        <tr>
                            <th>Medicine Description</th>
                            <th>SKU</th>
                            <th class="text-center">Current Stock</th>
                            <th class="text-center">Alert Threshold</th>
                            <th class="text-end">Unit Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reorder_items as $item): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?php echo sanitize($item['name']); ?></td>
                                <td class="font-monospace text-muted"><?php echo sanitize($item['sku']); ?></td>
                                <td class="text-center fw-bold text-danger"><?php echo $item['stock_qty']; ?> units</td>
                                <td class="text-center text-muted">&le; <?php echo $item['low_stock_threshold']; ?></td>
                                <td class="text-end"><?php echo format_currency($item['price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="small text-muted mb-4">No inventory items currently require reordering.</p>
            <?php endif; ?>

            <!-- 3. System Continuity & Network Health -->
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">3. Local LAN Continuity & Cloud Backup State</h5>
            <div class="row g-3 mb-4 small">
                <div class="col-4">
                    <span class="text-muted d-block">Pending SMS Alerts:</span>
                    <strong class="text-dark"><?php echo number_format($pending_alerts); ?> queued</strong>
                </div>
                <div class="col-4">
                    <span class="text-muted d-block">Unsynced Change Logs:</span>
                    <strong class="text-dark"><?php echo number_format($unsynced_logs); ?> logs</strong>
                </div>
                <div class="col-4">
                    <span class="text-muted d-block">Unresolved Conflicts:</span>
                    <strong class="text-dark"><?php echo number_format($conflicts_count); ?> items</strong>
                </div>
            </div>

            <!-- Sign-off Footer -->
            <div class="pt-4 border-top text-center text-muted small">
                <p class="mb-1 fw-bold text-dark">Dawaam Local Business Continuity System &bull; Quetta Pilot</p>
                <p class="mb-0">Certified Local LAN Operation &bull; Zero Internet Outage Resilience</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
