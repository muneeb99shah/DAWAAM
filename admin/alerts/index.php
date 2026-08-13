<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Urgent Alert Engine Dashboard & History
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alerts.php';

require_permission('alerts.view');

$pdo = get_db_connection();

// Filter Parameter: 'all', 'pending', 'sent'
$status_filter = $_GET['status'] ?? 'all';

// KPI Metrics
$stat_pending = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_sent = 0")->fetchColumn();
$stat_sent = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_sent = 1")->fetchColumn();
$stat_low_stock = $pdo->query("SELECT COUNT(*) FROM alerts WHERE type = 'low_stock'")->fetchColumn();
$stat_big_sale = $pdo->query("SELECT COUNT(*) FROM alerts WHERE type = 'big_sale'")->fetchColumn();

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 15);

$where_clause = "";
if ($status_filter === 'pending') {
    $where_clause = "a.is_sent = 0";
} elseif ($status_filter === 'sent') {
    $where_clause = "a.is_sent = 1";
}

$paginated_res = get_paginated_data($pdo, [
    'table' => 'alerts a LEFT JOIN products p ON a.product_id = p.id',
    'select_fields' => 'a.id, a.product_id, a.type, a.message, a.is_sent, a.created_at, p.name AS product_name, p.sku AS product_sku',
    'where_clause' => $where_clause,
    'order_by' => 'a.created_at DESC, a.id DESC',
    'page' => $page,
    'limit' => $limit,
    'count_field' => 'a.id'
]);

$alerts = $paginated_res['data'];
$pagination = $paginated_res['pagination'];

$page_title = "Urgent Alert Engine Dashboard";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-bell-fill text-warning me-2"></i> Urgent Event Alert Engine
        </h2>
        <p class="text-muted small mb-0">Monitors critical low-stock depletions, large sales, and queues emergency SMS dispatches.</p>
    </div>
    <div>
        <?php if (has_permission('alerts.manage')): ?>
            <a href="manage.php?action=trigger_test&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-dw-primary btn-sm">
                <i class="bi bi-broadcast me-1"></i> Trigger Test Alert
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Metrics Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="index.php?status=pending" class="text-decoration-none">
            <div class="dw-stat-card stat-rose <?php echo $status_filter === 'pending' ? 'shadow border-2' : ''; ?>">
                <div class="dw-stat-label">Pending SMS Alerts</div>
                <div class="dw-stat-value"><?php echo number_format($stat_pending); ?></div>
                <span class="small text-muted"><i class="bi bi-clock me-1"></i> Queued for Dispatch</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?status=sent" class="text-decoration-none">
            <div class="dw-stat-card stat-emerald <?php echo $status_filter === 'sent' ? 'shadow border-2' : ''; ?>">
                <div class="dw-stat-label">Sent via SMS</div>
                <div class="dw-stat-value"><?php echo number_format($stat_sent); ?></div>
                <span class="small text-muted"><i class="bi bi-check-circle me-1"></i> Delivered over Cellular</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?status=all" class="text-decoration-none">
            <div class="dw-stat-card stat-amber">
                <div class="dw-stat-label">Low Stock Events</div>
                <div class="dw-stat-value"><?php echo number_format($stat_low_stock); ?></div>
                <span class="small text-muted"><i class="bi bi-capsule me-1"></i> Threshold Triggers</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?status=all" class="text-decoration-none">
            <div class="dw-stat-card">
                <div class="dw-stat-label">Large Sale Events</div>
                <div class="dw-stat-value"><?php echo number_format($stat_big_sale); ?></div>
                <span class="small text-muted"><i class="bi bi-cash-stack me-1"></i> &ge; PKR 50,000 Sales</span>
            </div>
        </a>
    </div>
</div>

<!-- Alerts Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Alert Notifications (<?php echo count($alerts); ?> Records)</span>
                <div class="btn-group btn-group-sm">
                    <a href="index.php?status=all" class="btn <?php echo $status_filter === 'all' ? 'btn-dark' : 'btn-outline-secondary'; ?>">All</a>
                    <a href="index.php?status=pending" class="btn <?php echo $status_filter === 'pending' ? 'btn-danger' : 'btn-outline-secondary'; ?>">Pending SMS</a>
                    <a href="index.php?status=sent" class="btn <?php echo $status_filter === 'sent' ? 'btn-success' : 'btn-outline-secondary'; ?>">Sent via SMS</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($alerts) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Event Type</th>
                                    <th>Product / Context</th>
                                    <th>Alert Message Body</th>
                                    <th>SMS Gateway Status</th>
                                    <th>Triggered At</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $a): ?>
                                    <?php
                                        $type_badge = 'bg-secondary';
                                        if ($a['type'] === 'low_stock') $type_badge = 'bg-danger';
                                        elseif ($a['type'] === 'big_sale') $type_badge = 'bg-info text-dark';
                                        elseif ($a['type'] === 'critical_event') $type_badge = 'bg-warning text-dark';
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?php echo $a['id']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $type_badge; ?> px-2 py-1">
                                                <i class="bi bi-lightning-fill me-1"></i> <?php echo sanitize(str_replace('_', ' ', strtoupper($a['type']))); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($a['product_name'])): ?>
                                                <strong class="text-dark d-block"><?php echo sanitize($a['product_name']); ?></strong>
                                                <span class="badge bg-light text-dark border font-monospace small">
                                                    <?php echo sanitize($a['product_sku']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">System / General Event</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 350px;">
                                            <span class="small text-dark d-block"><?php echo sanitize($a['message']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ((int)$a['is_sent'] === 1): ?>
                                                <span class="badge bg-success px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Sent via SMS
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">
                                                    <i class="bi bi-clock-history me-1"></i> Pending SMS Dispatch
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($a['created_at']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $a['id']; ?>" class="btn btn-outline-info" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if (has_permission('alerts.manage')): ?>
                                                    <a href="manage.php?action=toggle_sent&id=<?php echo $a['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-outline-secondary" title="Toggle Sent Status">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </a>
                                                    <a href="manage.php?action=delete_alert&id=<?php echo $a['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" onclick="return confirm('Are you sure you want to delete this alert?');" class="btn btn-outline-danger" title="Delete Alert">
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
                    <?php echo render_pagination_links($pagination, 'index.php', ['status' => $status_filter]); ?>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-bell-slash fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No Alerts Found</h5>
                        <p class="mb-0 small">No event notifications match your selected filter state.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
