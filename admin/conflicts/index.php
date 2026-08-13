<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Conflict Resolution Dashboard & Queue
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('conflicts.view');

$pdo = get_db_connection();

$status_filter = $_GET['status'] ?? 'unresolved'; // 'unresolved', 'resolved', 'all'

// KPI Stats
$stat_unresolved = $pdo->query("SELECT COUNT(*) FROM sync_conflicts WHERE status = 'unresolved'")->fetchColumn();
$stat_resolved = $pdo->query("SELECT COUNT(*) FROM sync_conflicts WHERE status = 'resolved'")->fetchColumn();
$stat_total = $pdo->query("SELECT COUNT(*) FROM sync_conflicts")->fetchColumn();
$last_resolved = $pdo->query("SELECT MAX(resolved_at) FROM sync_conflicts WHERE status = 'resolved'")->fetchColumn();

// Filter Query
$where_sql = "";
if ($status_filter === 'unresolved') {
    $where_sql = "WHERE sc.status = 'unresolved'";
} elseif ($status_filter === 'resolved') {
    $where_sql = "WHERE sc.status = 'resolved'";
}

$query = "
    SELECT sc.id, sc.table_name, sc.record_id, sc.local_data, sc.remote_data, sc.status, sc.created_at, sc.resolved_at,
           u.name AS resolver_name
    FROM sync_conflicts sc
    LEFT JOIN users u ON sc.resolved_by = u.id
    {$where_sql}
    ORDER BY (sc.status = 'unresolved') DESC, sc.id DESC
";

$stmt = $pdo->query($query);
$conflicts = $stmt->fetchAll();

$page_title = "Conflict Resolution Hub";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-arrow-repeat text-warning me-2"></i> Conflict Resolution Hub
        </h2>
        <p class="text-muted small mb-0">Compare and resolve data discrepancies between local LAN database and cloud master server.</p>
    </div>
</div>

<!-- KPI Metrics Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="index.php?status=unresolved" class="text-decoration-none">
            <div class="dw-stat-card <?php echo $stat_unresolved > 0 ? 'stat-amber' : 'stat-emerald'; ?> <?php echo $status_filter === 'unresolved' ? 'shadow border-2' : ''; ?>">
                <div class="dw-stat-label">Unresolved Conflicts</div>
                <div class="dw-stat-value"><?php echo number_format($stat_unresolved); ?></div>
                <span class="small text-muted"><i class="bi bi-exclamation-triangle me-1"></i> Action Required</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?status=resolved" class="text-decoration-none">
            <div class="dw-stat-card stat-emerald <?php echo $status_filter === 'resolved' ? 'shadow border-2' : ''; ?>">
                <div class="dw-stat-label">Resolved History</div>
                <div class="dw-stat-value"><?php echo number_format($stat_resolved); ?></div>
                <span class="small text-muted"><i class="bi bi-check-circle me-1"></i> Successfully Merged</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="index.php?status=all" class="text-decoration-none">
            <div class="dw-stat-card">
                <div class="dw-stat-label">Total Conflict Logs</div>
                <div class="dw-stat-value"><?php echo number_format($stat_total); ?></div>
                <span class="small text-muted"><i class="bi bi-journal-text me-1"></i> Lifetime Entries</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Last Resolved Time</div>
            <div class="dw-stat-value" style="font-size: 1.2rem;"><?php echo $last_resolved ? date('H:i, d M', strtotime($last_resolved)) : 'None'; ?></div>
            <span class="small text-muted"><i class="bi bi-person-check me-1"></i> Manager Resolution</span>
        </div>
    </div>
</div>

<!-- Conflicts Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Conflict Log Queue (<?php echo count($conflicts); ?> Items)</span>
                <div class="btn-group btn-group-sm">
                    <a href="index.php?status=all" class="btn <?php echo $status_filter === 'all' ? 'btn-dark' : 'btn-outline-secondary'; ?>">All</a>
                    <a href="index.php?status=unresolved" class="btn <?php echo $status_filter === 'unresolved' ? 'btn-warning' : 'btn-outline-secondary'; ?>">Unresolved</a>
                    <a href="index.php?status=resolved" class="btn <?php echo $status_filter === 'resolved' ? 'btn-success' : 'btn-outline-secondary'; ?>">Resolved</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (count($conflicts) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Conflict ID</th>
                                    <th>Target Entity</th>
                                    <th>Status</th>
                                    <th>Detected Timestamp</th>
                                    <th>Resolved By</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conflicts as $c): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?php echo $c['id']; ?></td>
                                        <td>
                                            <span class="badge bg-dark font-monospace">
                                                <?php echo sanitize($c['table_name']); ?> #<?php echo $c['record_id']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($c['status'] === 'resolved'): ?>
                                                <span class="badge bg-success px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Resolved
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger px-2 py-1">
                                                    <i class="bi bi-exclamation-triangle me-1"></i> Action Required
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($c['created_at']); ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo !empty($c['resolver_name']) ? sanitize($c['resolver_name']) : 'Pending'; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-info" title="Compare Side-by-Side Diff">
                                                    <i class="bi bi-file-diff me-1"></i> Compare Diff
                                                </a>
                                                <?php if ($c['status'] === 'unresolved' && has_permission('conflicts.resolve')): ?>
                                                    <a href="resolve.php?action=keep_local&id=<?php echo $c['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-outline-success" title="Keep Pharmacy Local Data">
                                                        Keep Local
                                                    </a>
                                                    <a href="resolve.php?action=keep_cloud&id=<?php echo $c['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-outline-primary" title="Keep Cloud Master Data">
                                                        Keep Cloud
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-shield-check fs-1 d-block mb-3 text-success"></i>
                        <h5>No Conflicts Found</h5>
                        <p class="mb-0 small">No data conflicts exist for the selected filter state. All local and cloud records are in harmony!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
