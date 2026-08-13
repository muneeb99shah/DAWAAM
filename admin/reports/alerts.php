<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Alert Engine Analytics Report
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('reports.view');

$pdo = get_db_connection();

// KPI Metrics
$stat_total = $pdo->query("SELECT COUNT(*) FROM alerts")->fetchColumn();
$stat_pending = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_sent = 0")->fetchColumn();
$stat_sent = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_sent = 1")->fetchColumn();

// Type Breakdown
$stmt_type = $pdo->query("
    SELECT type, COUNT(*) AS event_count, 
           SUM(CASE WHEN is_sent = 1 THEN 1 ELSE 0 END) AS sent_count,
           SUM(CASE WHEN is_sent = 0 THEN 1 ELSE 0 END) AS pending_count
    FROM alerts
    GROUP BY type
    ORDER BY event_count DESC
");
$type_breakdown = $stmt_type->fetchAll();

$page_title = "Alert Engine Analytics Report";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-bell text-info me-2"></i> Urgent Event Alert Engine Analytics
        </h2>
        <p class="text-muted small mb-0">Rule engine event frequencies, low-stock notifications, and cellular SMS dispatch ratios.</p>
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
    <div class="col-6 col-md-4">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Total Triggered Alerts</div>
            <div class="dw-stat-value"><?php echo number_format($stat_total); ?></div>
            <span class="small text-muted"><i class="bi bi-lightning me-1"></i> Event Rule Triggers</span>
        </div>
    </div>

    <div class="col-6 col-md-4">
        <div class="dw-stat-card stat-rose">
            <div class="dw-stat-label">Pending SMS Queue</div>
            <div class="dw-stat-value"><?php echo number_format($stat_pending); ?></div>
            <span class="small text-muted"><i class="bi bi-clock me-1"></i> Waiting for Dispatch</span>
        </div>
    </div>

    <div class="col-6 col-md-4">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Sent via Cellular SIM</div>
            <div class="dw-stat-value"><?php echo number_format($stat_sent); ?></div>
            <span class="small text-muted"><i class="bi bi-check-circle me-1"></i> Delivered SMS</span>
        </div>
    </div>
</div>

<!-- Event Type Breakdown Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header">
                <span>Rule Engine Event Type Distribution</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Event Type</th>
                                <th class="text-center">Total Triggers</th>
                                <th class="text-center">Pending SMS</th>
                                <th class="text-center">Sent SMS</th>
                                <th class="text-end pe-4">Delivery Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($type_breakdown as $tb): ?>
                                <?php 
                                    $rate = $tb['event_count'] > 0 ? round(($tb['sent_count'] / $tb['event_count']) * 100) : 0;
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark">
                                        <span class="badge bg-dark px-2 py-1 font-monospace">
                                            <?php echo sanitize(str_replace('_', ' ', strtoupper($tb['type']))); ?>
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold text-dark"><?php echo $tb['event_count']; ?></td>
                                    <td class="text-center text-warning fw-bold"><?php echo $tb['pending_count']; ?></td>
                                    <td class="text-center text-success fw-bold"><?php echo $tb['sent_count']; ?></td>
                                    <td class="text-end pe-4">
                                        <span class="badge bg-info text-dark px-2 py-1"><?php echo $rate; ?>% Delivered</span>
                                    </td>
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
