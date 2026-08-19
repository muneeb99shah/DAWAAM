<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - SMS Fallback & WhatsApp Gateway Dashboard
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms.php';
require_once __DIR__ . '/../../includes/notification_service.php';

require_permission('sms.view');

$pdo = get_db_connection();
$settings = get_gateway_settings();

// Real Backend Health Checks (Cached for 60s for instant page render)
$wa_health = test_whatsapp_api_connection(true);
$sms_health = test_sms_gateway_connection(true);

// Fetch Primary Owner Recipient Number
$primary_phone = $pdo->query("SELECT phone_number FROM notification_numbers WHERE is_primary = 1 LIMIT 1")->fetchColumn();
if (!$primary_phone) {
    $primary_phone = '+1234567890'; // Generic Example Fallback
}

// KPI Stats from real notification_logs (Combined Query)
$stats_row = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_cnt,
        COALESCE(SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END), 0) AS sent_cnt
    FROM notification_logs 
    WHERE channel_used = 'sms'
")->fetch();
$stat_pending = $stats_row['pending_cnt'];
$stat_sent = $stats_row['sent_cnt'];

// Calculate Gateway Operating Mode Status
$mode_text = "GATEWAY OFFLINE";
if ($wa_health['success'] && $sms_health['success']) {
    $mode_text = "WHATSAPP + SMS (ONLINE)";
} elseif ($sms_health['success']) {
    $mode_text = "SMS FALLBACK (ACTIVE)";
} elseif ($wa_health['success']) {
    $mode_text = "WHATSAPP ONLY (ONLINE)";
}

// Fetch Alerts Queue with real notification status
$query = "
    SELECT a.id, a.type, a.message, a.is_sent, a.created_at, p.name AS product_name, p.stock_qty, p.low_stock_threshold,
           nl.status AS delivery_status, nl.channel_used, nl.provider_msg_id, nl.fallback_channel, nl.fallback_reason
    FROM alerts a
    LEFT JOIN products p ON a.product_id = p.id
    LEFT JOIN notification_logs nl ON a.id = nl.alert_id
    ORDER BY a.created_at DESC
    LIMIT 15
";
$alerts = $pdo->query($query)->fetchAll();

$page_title = "SMS Fallback Gateway Dashboard";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-chat-left-text-fill text-warning me-2"></i> SMS Fallback Gateway
        </h2>
        <p class="text-muted small mb-0">Cellular SIM SMS dispatch queue for urgent notifications during internet blackouts.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="numbers.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-telephone-outbound me-1"></i> Notification Numbers
        </a>
        <a href="logs.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i> Transmission Logs
        </a>
        <?php if (has_permission('sms.manage')): ?>
            <a href="settings.php" class="btn btn-dw-primary btn-sm">
                <i class="bi bi-gear me-1"></i> Gateway Settings
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Metrics Grid (Clickable Interactive Cards) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="logs.php?status=pending&channel=sms" class="text-decoration-none">
            <div class="dw-stat-card stat-rose">
                <div class="dw-stat-label">Pending Line Dispatch</div>
                <div class="dw-stat-value"><?php echo number_format($stat_pending); ?></div>
                <span class="small text-muted"><i class="bi bi-clock me-1"></i> Waiting for SMS Gateway</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="logs.php?status=sent&channel=sms" class="text-decoration-none">
            <div class="dw-stat-card stat-emerald">
                <div class="dw-stat-label">Sent via Cellular</div>
                <div class="dw-stat-value"><?php echo number_format($stat_sent); ?></div>
                <span class="small text-muted"><i class="bi bi-send-check me-1"></i> Accepted by GSM SIM Gateway</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="numbers.php" class="text-decoration-none">
            <div class="dw-stat-card">
                <div class="dw-stat-label">Recipient Phone</div>
                <div class="dw-stat-value" style="font-size: 1.3rem;"><?php echo sanitize($primary_phone); ?></div>
                <span class="small text-muted"><i class="bi bi-phone me-1"></i> Primary Business Owner</span>
            </div>
        </a>
    </div>

    <div class="col-6 col-md-3">
        <a href="settings.php" class="text-decoration-none">
            <div class="dw-stat-card">
                <div class="dw-stat-label">Gateway Operating Mode</div>
                <div class="dw-stat-value" style="font-size: 1.1rem;"><?php echo sanitize($mode_text); ?></div>
                <span class="small text-muted"><i class="bi bi-hdd-network me-1"></i> Click to Open Settings</span>
            </div>
        </a>
    </div>
</div>

<!-- Recent Cellular SMS Queue Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Recent Cellular SMS Queue Records</span>
                <span class="badge bg-dark rounded-pill"><?php echo number_format($stat_pending + $stat_sent); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="dw-sms-queue-table" class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Alert ID</th>
                                <th>Recipient Phone</th>
                                <th>Formatted SMS Text Payload (Max 160 Chars)</th>
                                <th>Dispatch Status</th>
                                <th>Timestamp</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alerts as $a): ?>
                                <?php 
                                    $payload = format_sms_payload(
                                        $a['type'], 
                                        $a['product_name'] ?? 'System Event', 
                                        $a['stock_qty'] ?? null, 
                                        $a['low_stock_threshold'] ?? null
                                    );
                                    $status = $a['delivery_status'] ?? ((int)$a['is_sent'] === 1 ? 'sent' : 'pending');
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-muted">#<?php echo $a['id']; ?></td>
                                    <td class="font-monospace text-dark"><?php echo sanitize($primary_phone); ?></td>
                                    <td>
                                        <div class="p-2 bg-light rounded border font-monospace small" style="white-space: pre-wrap; max-width: 400px; color: #1e293b;">
                                            <?php echo sanitize($payload); ?>
                                        </div>
                                        <span class="small text-muted ms-1"><?php echo mb_strlen($payload); ?> / 160 characters</span>
                                    </td>
                                    <td>
                                        <?php if ($status === 'delivered'): ?>
                                            <span class="badge bg-success px-2 py-1">
                                                <i class="bi bi-check-all me-1"></i> Delivered via WhatsApp
                                            </span>
                                        <?php elseif ($status === 'sent'): ?>
                                            <span class="badge bg-info text-dark px-2 py-1">
                                                <i class="bi bi-send-check me-1"></i> Accepted by GSM SIM Gateway
                                            </span>
                                        <?php elseif ($status === 'failed'): ?>
                                            <span class="badge bg-danger px-2 py-1">
                                                <i class="bi bi-x-circle me-1"></i> Failed
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark px-2 py-1">
                                                <i class="bi bi-clock me-1"></i> Pending Dispatch
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($a['fallback_channel'])): ?>
                                            <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                                <i class="bi bi-arrow-repeat me-1"></i> SMS Failover
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo format_date($a['created_at']); ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if (has_permission('sms.manage')): ?>
                                            <a href="../alerts/manage.php?action=dispatch_now&id=<?php echo $a['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-send me-1"></i> Dispatch Now
                                            </a>
                                        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && $.fn.DataTable) {
        $('#dw-sms-queue-table').DataTable({
            serverSide: true,
            processing: true,
            deferLoading: <?php echo (int)count($alerts); ?>,
            ajax: {
                url: 'ajax_sms_queue.php',
                type: 'GET'
            },
            pageLength: 15,
            lengthMenu: [10, 15, 25, 50, 100],
            ordering: true,
            order: [[0, 'desc']],
            columnDefs: [
                { className: 'ps-4', targets: 0 },
                { className: 'font-monospace text-dark', targets: 1 },
                { className: 'text-end pe-4', targets: 5 },
                { orderable: false, targets: [1, 2, 3, 5] }
            ]
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
