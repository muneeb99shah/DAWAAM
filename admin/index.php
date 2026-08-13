<?php
/**
 * Dawaam - Local Business Continuity Software
 * Dynamic Permission-Driven Operational Dashboard
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user = current_user();
$pdo = get_db_connection();

// Role Determination
$is_super_admin = has_role('super_admin');

// Fetch Operational KPI Metrics from MySQL
$stat_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stat_sales_count = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$stat_sales_today = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM sales WHERE DATE(sold_at) = CURRENT_DATE()")->fetchColumn();
$stat_sales_today_count = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(sold_at) = CURRENT_DATE()")->fetchColumn();
$stat_low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= low_stock_threshold")->fetchColumn();
$stat_alerts_pending = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_sent = 0")->fetchColumn();
$stat_unsynced = $pdo->query("SELECT COUNT(*) FROM sync_log WHERE synced = 0")->fetchColumn();
$stat_conflicts = $pdo->query("SELECT COUNT(*) FROM sync_conflicts WHERE status = 'unresolved'")->fetchColumn();
$stat_messages = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$stat_asset_val = $pdo->query("SELECT COALESCE(SUM(price * stock_qty), 0) FROM products")->fetchColumn();

$page_title = "Operations Dashboard";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Role-Tailored Dashboard Welcome Header Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="dw-hero-banner py-4 px-4 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-light text-dark fw-bold px-3 py-1.5 rounded-pill mb-2">
                        <i class="bi bi-person-badge-fill me-1" style="color:#0f766e;"></i> User Code: <?php echo sanitize($user['user_code']); ?>
                    </span>
                    <h2 class="display-6 fw-bold text-white mb-1">
                        Welcome back, <?php echo sanitize($user['name']); ?>!
                    </h2>
                    <p class="lead text-white-50 small mb-0">
                        Operational Application Portal — Dawaam Business Continuity
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php foreach ($user['roles'] as $r): ?>
                        <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold">
                            <i class="bi bi-shield-check me-1"></i> <?php echo sanitize($r['name']); ?>
                        </span>
                    <?php endforeach; ?>
                    <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="btn btn-outline-light btn-sm ms-2">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic KPI Stat Cards Row (Filter by Permissions) -->
<div class="row g-3 mb-5">
    <?php if (has_permission('products.view')): ?>
        <div class="col-6 col-md-3">
            <a href="products/index.php" class="dw-stat-card" title="Manage Products Catalog">
                <div class="dw-stat-label">Total Products</div>
                <div class="dw-stat-value"><?php echo number_format($stat_products); ?></div>
                <div class="dw-stat-sub"><i class="bi bi-box text-emerald"></i> Active Inventory Items</div>
            </a>
        </div>
    <?php endif; ?>

    <?php if (has_permission('sales.view')): ?>
        <div class="col-6 col-md-3">
            <a href="sales/index.php?date=<?php echo date('Y-m-d'); ?>" class="dw-stat-card stat-emerald" title="View Today's Sales Receipts">
                <div class="dw-stat-label">Today's Sales</div>
                <div class="dw-stat-value"><?php echo format_currency($stat_sales_today); ?></div>
                <div class="dw-stat-sub"><i class="bi bi-cash-stack text-emerald"></i> Today's Revenue (<?php echo number_format($stat_sales_today_count); ?> txns)</div>
            </a>
        </div>
    <?php endif; ?>

    <?php if (has_permission('inventory.view') || has_permission('alerts.view')): ?>
        <div class="col-6 col-md-3">
            <a href="inventory/index.php?filter=low_stock" class="dw-stat-card stat-amber" title="Monitor Low Stock Items">
                <div class="dw-stat-label">Low Stock Items</div>
                <div class="dw-stat-value"><?php echo number_format($stat_low_stock); ?></div>
                <div class="dw-stat-sub"><i class="bi bi-exclamation-triangle text-amber"></i> Below Threshold</div>
            </a>
        </div>
    <?php endif; ?>

    <?php if (has_permission('alerts.view') || has_permission('sms.manage')): ?>
        <div class="col-6 col-md-3">
            <a href="alerts/index.php?status=pending" class="dw-stat-card stat-rose" title="View Pending SMS Alerts">
                <div class="dw-stat-label">Pending SMS Alerts</div>
                <div class="dw-stat-value"><?php echo number_format($stat_alerts_pending); ?></div>
                <div class="dw-stat-sub"><i class="bi bi-bell text-rose"></i> Queued for Dispatch</div>
            </a>
        </div>
    <?php endif; ?>

    <?php if (has_permission('reports.view') || has_permission('inventory.view')): ?>
        <div class="col-6 col-md-3">
            <a href="reports/sales.php" class="dw-stat-card stat-sky" title="View Financial Asset Valuation">
                <div class="dw-stat-label">Shelf Asset Valuation</div>
                <div class="dw-stat-value"><?php echo format_currency($stat_asset_val); ?></div>
                <div class="dw-stat-sub"><i class="bi bi-graph-up text-info"></i> Total Inventory Value</div>
            </a>
        </div>
    <?php endif; ?>

    <?php if (has_permission('sync.view')): ?>
        <div class="col-6 col-md-3">
            <a href="sync/index.php" class="dw-stat-card" title="View Cloud Sync Engine">
                <div class="dw-stat-label">Unsynced Records</div>
                <div class="dw-stat-value"><?php echo number_format($stat_unsynced); ?></div>
                <div class="dw-stat-sub"><i class="bi bi-cloud-upload text-primary"></i> Pending Recovery Sync</div>
            </a>
        </div>
    <?php endif; ?>

    <?php if (has_permission('messages.view')): ?>
        <div class="col-6 col-md-3">
            <a href="messages/index.php" class="dw-stat-card" title="View Client Messages">
                <div class="dw-stat-label">Client Messages</div>
                <div class="dw-stat-value"><?php echo number_format($stat_messages); ?></div>
                <div class="dw-stat-sub"><i class="bi bi-envelope text-secondary"></i> Contact Inquiries</div>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Dynamic Permission-Driven Feature Modules Grid -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <h4 class="fw-bold text-dark mb-3 border-bottom pb-2">
            Authorized Operational Features
        </h4>
    </div>

    <?php $rendered_cards = 0; ?>

    <!-- POS Terminal Card -->
    <?php if (has_permission('sales.create') || has_permission('pos.view')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column border-success">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                        <i class="bi bi-cart-check fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Sales / Local POS</h5>
                        <span class="text-muted small">Record sales & deduct stock</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Process customer checkouts over local LAN, calculate totals, issue receipts, and automatically trigger low-stock alerts.</p>
                <a href="<?php echo BASE_URL; ?>/admin/sales/create.php" class="btn btn-dw-primary btn-sm w-100 mt-2">
                    <i class="bi bi-cart-plus me-1"></i> Open POS Terminal
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sales Receipts Card -->
    <?php if (has_permission('sales.view')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                        <i class="bi bi-receipt-cutoff fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Sales History</h5>
                        <span class="text-muted small">Review processed receipts</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">View transaction receipts processed by your account, filter sales history by date, and reprint customer receipts.</p>
                <a href="<?php echo BASE_URL; ?>/admin/sales/index.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-clock-history me-1"></i> View Sales Receipts
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Products Catalog Card -->
    <?php if (has_permission('products.view')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle me-3">
                        <i class="bi bi-capsule fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Products & Catalog</h5>
                        <span class="text-muted small">View stock levels & prices</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Search medicine names, check unit prices, manage catalog items, and verify current stock availability.</p>
                <a href="<?php echo BASE_URL; ?>/admin/products/index.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-box-seam me-1"></i> Manage Products
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Manual Stock Adjustment Card -->
    <?php if (has_permission('inventory.adjust')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                        <i class="bi bi-arrow-down-up fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Manual Stock Adjustment</h5>
                        <span class="text-muted small">Shipments & audit corrections</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Record received supplier shipments (+), adjust damaged/expired stock (-), or update physical audit counts.</p>
                <a href="<?php echo BASE_URL; ?>/admin/inventory/adjust.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-pencil-square me-1"></i> Adjust Stock Levels
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Urgent Alerts Card -->
    <?php if (has_permission('alerts.view') || has_permission('alerts.manage')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle me-3">
                        <i class="bi bi-bell fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Urgent Alert Engine</h5>
                        <span class="text-muted small">Low stock & threshold warnings</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Monitor stock threshold breaches, view active urgent alerts, and track threshold warning dispatches.</p>
                <a href="<?php echo BASE_URL; ?>/admin/alerts/index.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-arrow-right me-1"></i> View Alerts
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reports & Analytics Card -->
    <?php if (has_permission('reports.view')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                        <i class="bi bi-graph-up-arrow fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Reports & Analytics</h5>
                        <span class="text-muted small">Sales & inventory reports</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Analyze daily sales revenue, inventory shelf valuation in PKR, low-stock reorder lists, and generate reports.</p>
                <a href="<?php echo BASE_URL; ?>/admin/reports/sales.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Open Reports
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Contact Messages Card -->
    <?php if (has_permission('messages.view')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle me-3">
                        <i class="bi bi-envelope-open fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Contact Messages</h5>
                        <span class="text-muted small">View client inquiries</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Review inquiry messages and consultation requests submitted through the public website portal.</p>
                <a href="<?php echo BASE_URL; ?>/admin/messages/index.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-arrow-right me-1"></i> View Messages (<?php echo $stat_messages; ?>)
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cloud Sync & Recovery Card -->
    <?php if (has_permission('sync.view') || has_permission('sync.manage')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-dark bg-opacity-10 text-dark p-3 rounded-circle me-3">
                        <i class="bi bi-cloud-arrow-up fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Cloud Sync & Recovery</h5>
                        <span class="text-muted small">Offline queue & data recovery</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Monitor offline records pending synchronization, trigger cloud backup when WAN restores, and resolve conflicts.</p>
                <a href="<?php echo BASE_URL; ?>/admin/sync/index.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-arrow-right me-1"></i> View Sync Status
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Local Network Hub Card -->
    <?php if (has_permission('network.view')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                        <i class="bi bi-router fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Local Network Hub</h5>
                        <span class="text-muted small">LAN Setup & QR Onboarding</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Monitor server IP address (192.168.108.1:8000), copy quick links, and generate pure PHP vector SVG QR codes.</p>
                <a href="<?php echo BASE_URL; ?>/admin/network/index.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-arrow-right me-1"></i> Open Network Hub
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- SMS Gateway Card -->
    <?php if (has_permission('sms.manage')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle me-3">
                        <i class="bi bi-chat-text fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">SMS Cellular Gateway</h5>
                        <span class="text-muted small">Android SIM SMS Fallback</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Configure local Android SMS SIM gateway IP, manage 160-char dispatch queues, and view cellular transmission logs.</p>
                <a href="<?php echo BASE_URL; ?>/admin/sms/index.php" class="btn btn-dw-outline btn-sm w-100 mt-2">
                    <i class="bi bi-arrow-right me-1"></i> Open SMS Gateway
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- User Management Card -->
    <?php if (has_permission('users.view') || has_permission('users.manage')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column border-primary">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle me-3">
                        <i class="bi bi-shield-check fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">User Account Management</h5>
                        <span class="text-muted small">User accounts & roles</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">Create staff accounts, generate User IDs (`DW-XXXX`), manage account statuses, and assign roles.</p>
                <a href="<?php echo BASE_URL; ?>/admin/users/index.php" class="btn btn-dw-primary btn-sm w-100 mt-2">
                    <i class="bi bi-arrow-right me-1"></i> Open User Directory
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Granular Permission Matrix Card -->
    <?php if ($is_super_admin || has_permission('permissions.manage')): ?>
        <?php $rendered_cards++; ?>
        <div class="col-md-6 col-lg-4">
            <div class="dw-card h-100 p-4 d-flex flex-column border-dark">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-dark text-white p-3 rounded-circle me-3">
                        <i class="bi bi-sliders fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Permission Matrix</h5>
                        <span class="text-muted small">Granular Role Permissions</span>
                    </div>
                </div>
                <p class="small text-muted flex-grow-1">System Administration tool to configure granular operational feature checkboxes for each role across all modules.</p>
                <a href="<?php echo BASE_URL; ?>/admin/users/permissions.php" class="btn btn-dark btn-sm w-100 mt-2">
                    <i class="bi bi-sliders me-1"></i> Open Permission Matrix
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($rendered_cards === 0): ?>
        <div class="col-12 text-center py-5">
            <div class="dw-card p-5 bg-white shadow-sm border-0">
                <i class="bi bi-shield-slash display-4 text-muted mb-3"></i>
                <h4 class="fw-bold text-dark">No Operational Features Assigned</h4>
                <p class="text-muted mb-0">Your account does not currently have any active feature permissions assigned. Please contact your System Administrator to enable features in the Granular Permission Matrix.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
