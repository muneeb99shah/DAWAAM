<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Comprehensive Notification Audit Logs
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notification_service.php';

require_permission('sms.view');

$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$channel_filter = $_GET['channel'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build Query
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(recipient_name LIKE :search_name OR recipient_phone LIKE :search_phone OR provider_msg_id LIKE :search_msgid)";
    $params[':search_name'] = "%{$search}%";
    $params[':search_phone'] = "%{$search}%";
    $params[':search_msgid'] = "%{$search}%";
}

if (!empty($channel_filter) && in_array($channel_filter, ['whatsapp', 'sms'], true)) {
    $where_clauses[] = "channel_used = :channel";
    $params[':channel'] = $channel_filter;
}

if (!empty($status_filter) && in_array($status_filter, ['pending', 'sending', 'sent', 'delivered', 'failed', 'retrying'], true)) {
    $where_clauses[] = "status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_filter)) {
    $where_clauses[] = "DATE(created_at) = :date";
    $params[':date'] = $date_filter;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$query = "
    SELECT id, alert_id, recipient_name, recipient_phone, message, primary_channel, channel_used,
           status, provider, provider_msg_id, provider_response, error_message, retry_count,
           fallback_channel, fallback_reason, sent_at, delivered_at, created_at
    FROM notification_logs
    {$where_sql}
    ORDER BY id DESC
    LIMIT 100
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// KPI Summary Counters
$stat_total = $pdo->query("SELECT COUNT(*) FROM notification_logs")->fetchColumn();
$stat_delivered = $pdo->query("SELECT COUNT(*) FROM notification_logs WHERE status = 'delivered'")->fetchColumn();
$stat_sent = $pdo->query("SELECT COUNT(*) FROM notification_logs WHERE status = 'sent'")->fetchColumn();
$stat_wa_count = $pdo->query("SELECT COUNT(*) FROM notification_logs WHERE channel_used = 'whatsapp'")->fetchColumn();
$stat_sms_count = $pdo->query("SELECT COUNT(*) FROM notification_logs WHERE channel_used = 'sms'")->fetchColumn();
$stat_fallback_count = $pdo->query("SELECT COUNT(*) FROM notification_logs WHERE fallback_channel IS NOT NULL")->fetchColumn();

$page_title = "Notification Transmission Audit Logs";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-journal-text text-primary me-2"></i> Notification Transmission Audit Logs
        </h2>
        <p class="text-muted small mb-0">Detailed real-time logs of WhatsApp & SMS dispatches, fallback events, and provider message IDs.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chat-left-text me-1"></i> Gateway Dashboard
        </a>
        <a href="numbers.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-telephone-outbound me-1"></i> Notification Numbers
        </a>
    </div>
</div>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="dw-stat-card">
            <div class="dw-stat-label">Total Attempts</div>
            <div class="dw-stat-value"><?php echo number_format($stat_total); ?></div>
            <span class="small text-muted"><i class="bi bi-send me-1"></i> Logged Payloads</span>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Delivered</div>
            <div class="dw-stat-value"><?php echo number_format($stat_delivered); ?></div>
            <span class="small text-muted"><i class="bi bi-check-all me-1"></i> Confirmed Received</span>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="dw-stat-card stat-sky">
            <div class="dw-stat-label">Sent via Cellular</div>
            <div class="dw-stat-value"><?php echo number_format($stat_sent); ?></div>
            <span class="small text-muted"><i class="bi bi-check me-1"></i> Accepted by Gateway</span>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">WhatsApp Dispatches</div>
            <div class="dw-stat-value"><?php echo number_format($stat_wa_count); ?></div>
            <span class="small text-muted"><i class="bi bi-whatsapp me-1"></i> Data Network</span>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="dw-stat-card">
            <div class="dw-stat-label">SMS Dispatches</div>
            <div class="dw-stat-value"><?php echo number_format($stat_sms_count); ?></div>
            <span class="small text-muted"><i class="bi bi-chat-text me-1"></i> GSM SIM Network</span>
        </div>
    </div>

    <div class="col-6 col-md-2">
        <div class="dw-stat-card stat-amber">
            <div class="dw-stat-label">SMS Fallbacks</div>
            <div class="dw-stat-value"><?php echo number_format($stat_fallback_count); ?></div>
            <span class="small text-muted"><i class="bi bi-arrow-repeat me-1"></i> WhatsApp Failover</span>
        </div>
    </div>
</div>

<!-- Filter & Search Bar -->
<div class="dw-card p-3 mb-4 bg-white">
    <form action="logs.php" method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" name="search" placeholder="Search recipient name, phone (+92...), or provider ID..." value="<?php echo sanitize($search); ?>">
        </div>

        <div class="col-md-2">
            <select name="channel" class="form-select form-select-sm">
                <option value="">-- All Channels --</option>
                <option value="whatsapp" <?php echo $channel_filter === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                <option value="sms" <?php echo $channel_filter === 'sms' ? 'selected' : ''; ?>>SMS</option>
            </select>
        </div>

        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">-- All Statuses --</option>
                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="sending" <?php echo $status_filter === 'sending' ? 'selected' : ''; ?>>Sending</option>
                <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                <option value="retrying" <?php echo $status_filter === 'retrying' ? 'selected' : ''; ?>>Retrying</option>
            </select>
        </div>

        <div class="col-md-2">
            <input type="date" class="form-control form-control-sm" name="date" value="<?php echo sanitize($date_filter); ?>">
        </div>

        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-dw-primary btn-sm w-100">Filter</button>
            <a href="logs.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Audit Log Table -->
<div class="row">
    <div class="col-12">
        <div class="dw-card">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span>Transmission Audit Records (<?php echo count($logs); ?> Payloads)</span>
                <span class="badge bg-dark rounded-pill"><?php echo count($logs); ?> Displayed</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($logs) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Log ID</th>
                                    <th>Recipient</th>
                                    <th>Channel Used</th>
                                    <th>Status</th>
                                    <th>Provider Message ID</th>
                                    <th>Text Payload Message</th>
                                    <th>Fallback Details</th>
                                    <th class="text-end pe-4">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold text-muted">#<?php echo $l['id']; ?></td>
                                        <td>
                                            <strong class="text-dark d-block"><?php echo sanitize($l['recipient_name'] ?? 'Recipient'); ?></strong>
                                            <span class="small font-monospace text-muted"><?php echo sanitize($l['recipient_phone']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($l['channel_used'] === 'whatsapp'): ?>
                                                <span class="badge bg-success px-2.5 py-1">
                                                    <i class="bi bi-whatsapp me-1"></i> WhatsApp
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary px-2.5 py-1">
                                                    <i class="bi bi-chat-text me-1"></i> SMS
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $status = $l['status'];
                                                $badge_cls = 'bg-secondary';
                                                if ($status === 'delivered') $badge_cls = 'bg-success';
                                                elseif ($status === 'sent') $badge_cls = 'bg-info text-dark';
                                                elseif ($status === 'sending') $badge_cls = 'bg-primary';
                                                elseif ($status === 'pending') $badge_cls = 'bg-warning text-dark';
                                                elseif ($status === 'failed') $badge_cls = 'bg-danger';
                                                elseif ($status === 'retrying') $badge_cls = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge <?php echo $badge_cls; ?> text-uppercase px-2 py-1">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td class="font-monospace small">
                                            <?php echo sanitize($l['provider_msg_id'] ?? 'N/A'); ?>
                                            <div class="small text-muted"><?php echo sanitize($l['provider']); ?></div>
                                        </td>
                                        <td>
                                            <div class="p-2 bg-light rounded border font-monospace small" style="white-space: pre-wrap; max-width: 280px; color: #334155;">
                                                <?php echo sanitize($l['message']); ?>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <?php if (!empty($l['fallback_channel'])): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-0.5 d-inline-block mb-1">
                                                    Fallback to <?php echo strtoupper($l['fallback_channel']); ?>
                                                </span>
                                                <div class="text-muted small" style="max-width: 220px; font-size: 0.75rem;">
                                                    <?php echo sanitize($l['fallback_reason'] ?? 'WhatsApp API unavailable'); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">— Direct</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 small text-muted">
                                            <?php echo format_date($l['created_at']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-journal-x fs-1 d-block mb-3 text-secondary"></i>
                        <h5>No Transmission Logs Found</h5>
                        <p class="mb-0 small">No notification logs match your filter selection.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
