<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Cloud Synchronization Recovery Dashboard
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('sync.view');

$pdo = get_db_connection();

// KPI Stats
$stat_unsynced = $pdo->query("SELECT COUNT(*) FROM sync_log WHERE synced = 0")->fetchColumn();
$stat_synced = $pdo->query("SELECT COUNT(*) FROM sync_log WHERE synced = 1")->fetchColumn();
$stat_total = $pdo->query("SELECT COUNT(*) FROM sync_log")->fetchColumn();
$stat_conflicts = $pdo->query("SELECT COUNT(*) FROM sync_conflicts WHERE status = 'unresolved'")->fetchColumn();

// Last Sync Time
$last_sync_time = $pdo->query("SELECT MAX(synced_at) FROM sync_log WHERE synced = 1")->fetchColumn();

// WAN Connectivity Check
$is_wan_online = false;
$sock = @fsockopen('8.8.8.8', 53, $errno, $errstr, 1);
if ($sock) {
    $is_wan_online = true;
    fclose($sock);
}

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 15);

$paginated_res = get_paginated_data($pdo, [
    'table' => 'sync_log',
    'select_fields' => 'id, table_name, record_id, action, synced, synced_at, created_at',
    'order_by' => '(synced = 0) DESC, id DESC',
    'page' => $page,
    'limit' => $limit,
    'count_field' => 'id'
]);

$logs = $paginated_res['data'];
$pagination = $paginated_res['pagination'];

$page_title = "Cloud Recovery Synchronization";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-cloud-arrow-up text-primary me-2"></i> Cloud Synchronization Recovery
        </h2>
        <p class="text-muted small mb-0">Monitors local change logs and transmits offline business data to cloud endpoint when WAN restores.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="logs.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i> Sync Logs
        </a>
        <?php if (has_permission('sync.manage')): ?>
            <a href="process.php?csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-dw-primary btn-sm px-3">
                <i class="bi bi-cloud-upload me-1"></i> Trigger Cloud Batch Sync Now
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Metrics Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="dw-stat-card <?php echo $stat_unsynced > 0 ? 'stat-amber' : 'stat-emerald'; ?>">
            <div class="dw-stat-label">Pending Unsynced Logs</div>
            <div class="dw-stat-value"><?php echo number_format($stat_unsynced); ?></div>
            <span class="small text-muted"><i class="bi bi-hdd-network me-1"></i> Local Queued Changes</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Synced to Cloud</div>
            <div class="dw-stat-value"><?php echo number_format($stat_synced); ?></div>
            <span class="small text-muted"><i class="bi bi-cloud-check me-1"></i> Backed Up Records</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Unresolved Conflicts</div>
            <div class="dw-stat-value"><?php echo number_format($stat_conflicts); ?></div>
            <span class="small text-muted"><i class="bi bi-arrow-repeat me-1"></i> Requires Review</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Last Successful Sync</div>
            <div class="dw-stat-value" style="font-size: 1.2rem;"><?php echo $last_sync_time ? date('H:i, d M', strtotime($last_sync_time)) : 'Never'; ?></div>
            <span class="small text-muted"><i class="bi bi-clock-history me-1"></i> Recovery Timestamp</span>
        </div>
    </div>
</div>

<!-- Connection Status & Recovery Strategy Banner -->
<div class="dw-card p-3 mb-4 bg-white border-start border-4 <?php echo $is_wan_online ? 'border-success' : 'border-warning'; ?>">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <div class="p-2 <?php echo $is_wan_online ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning'; ?> rounded-circle me-3">
                <i class="bi bi-wifi fs-4"></i>
            </div>
            <div>
                <h6 class="fw-bold text-dark mb-0">WAN Connection State</h6>
                <span class="small text-muted">
                    <?php echo $is_wan_online ? 'Internet WAN link active. Local server is ready to transmit batch sync payloads.' : 'Quetta blackout mode active. Local POS and inventory changes are logged locally in sync_log.'; ?>
                </span>
            </div>
        </div>
        <div>
            <?php if ($is_wan_online): ?>
                <span class="badge bg-success px-3 py-2 fs-6">
                    <i class="bi bi-check-circle-fill me-1"></i> WAN ONLINE - READY TO SYNC
                </span>
            <?php else: ?>
                <span class="badge bg-warning text-dark px-3 py-2 fs-6">
                    <i class="bi bi-wifi-off me-1"></i> INTERNET OFFLINE (LOCAL CONTINUITY)
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Change Tracking Log Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Change Tracking Queue (`sync_log`)</span>
                <span class="badge bg-dark rounded-pill"><?php echo count($logs); ?> Shown</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($logs) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Sync ID</th>
                                    <th>Target Entity</th>
                                    <th>Action Type</th>
                                    <th>Record ID</th>
                                    <th>Local Created Timestamp</th>
                                    <th class="text-end pe-4">Backup Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $l): ?>
                                    <?php 
                                        $act_badge = 'bg-info text-dark';
                                        if ($l['action'] === 'INSERT') $act_badge = 'bg-success';
                                        elseif ($l['action'] === 'UPDATE') $act_badge = 'bg-warning text-dark';
                                        elseif ($l['action'] === 'DELETE') $act_badge = 'bg-danger';
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?php echo $l['id']; ?></td>
                                        <td>
                                            <span class="badge bg-dark font-monospace">
                                                <?php echo sanitize($l['table_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $act_badge; ?> px-2 py-1">
                                                <?php echo sanitize($l['action']); ?>
                                            </span>
                                        </td>
                                        <td class="font-monospace text-dark">
                                            #<?php echo $l['record_id']; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo format_date($l['created_at']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ((int)$l['synced'] === 1): ?>
                                                <span class="badge bg-success px-2 py-1">
                                                    <i class="bi bi-cloud-check me-1"></i> Synced to Cloud
                                                </span>
                                                <span class="small text-muted d-block"><?php echo format_date($l['synced_at']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">
                                                    <i class="bi bi-clock me-1"></i> Pending Backup
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination_links($pagination, 'index.php'); ?>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-cloud-check fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No Change Log Entries</h5>
                        <p class="mb-0 small">No local database changes recorded in `sync_log`.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
