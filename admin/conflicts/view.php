<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Side-by-Side Conflict Data Diff & Resolution
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('conflicts.view');

$conflict_id = (int)($_GET['id'] ?? 0);
if ($conflict_id <= 0) {
    set_flash_message('danger', 'Invalid conflict ID specified.');
    redirect('admin/conflicts/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT sc.*, u.name AS resolver_name
    FROM sync_conflicts sc
    LEFT JOIN users u ON sc.resolved_by = u.id
    WHERE sc.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $conflict_id]);
$conflict = $stmt->fetch();

if (!$conflict) {
    set_flash_message('danger', 'Conflict log entry not found.');
    redirect('admin/conflicts/index.php');
}

// Decode JSON payloads
$local_data = json_decode($conflict['local_data'], true) ?? [];
$remote_data = json_decode($conflict['remote_data'], true) ?? [];

$page_title = "Compare Conflict Diff #" . $conflict['id'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-file-diff text-primary me-2"></i> Side-by-Side Data Diff Comparison
        </h2>
        <p class="text-muted small mb-0">Conflict #<?php echo $conflict['id']; ?> &bull; Entity: <code><?php echo sanitize($conflict['table_name']); ?> #<?php echo $conflict['record_id']; ?></code></p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Conflicts
        </a>
    </div>
</div>

<!-- Side-by-Side Diff Cards -->
<div class="row g-4 mb-4">
    <!-- Column 1: Pharmacy Local Data -->
    <div class="col-md-6">
        <div class="dw-card h-100 p-4 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div>
                    <h5 class="fw-bold text-success mb-0">
                        <i class="bi bi-hdd-network-fill me-1"></i> Pharmacy Local LAN Data
                    </h5>
                    <span class="small text-muted">Recorded on local pharmacy server during blackout</span>
                </div>
                <span class="badge bg-success">Local Version</span>
            </div>

            <div class="p-3 bg-light rounded-3 font-monospace small mb-3 border">
                <table class="table table-sm table-borderless mb-0">
                    <?php if (count($local_data) > 0): ?>
                        <?php foreach ($local_data as $key => $val): ?>
                            <?php 
                                $remote_val = $remote_data[$key] ?? null;
                                $is_mismatch = ($remote_val !== null && (string)$remote_val !== (string)$val);
                            ?>
                            <tr class="<?php echo $is_mismatch ? 'bg-warning bg-opacity-25 fw-bold' : ''; ?>">
                                <td class="text-muted pe-3" style="width: 40%;"><?php echo sanitize($key); ?>:</td>
                                <td class="text-dark"><?php echo is_array($val) ? json_encode($val) : sanitize((string)$val); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td class="text-muted">No detailed local properties recorded.</td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <?php if ($conflict['status'] === 'unresolved' && has_permission('conflicts.resolve')): ?>
                <a href="resolve.php?action=keep_local&id=<?php echo $conflict['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-success w-100 py-2 fw-bold">
                    <i class="bi bi-check-circle me-1"></i> Accept Pharmacy Local Version (Keep Local)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Column 2: Cloud Master Data -->
    <div class="col-md-6">
        <div class="dw-card h-100 p-4 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div>
                    <h5 class="fw-bold text-primary mb-0">
                        <i class="bi bi-cloud-check-fill me-1"></i> Remote Cloud Master Data
                    </h5>
                    <span class="small text-muted">Updated concurrently on cloud backend server</span>
                </div>
                <span class="badge bg-primary">Cloud Master</span>
            </div>

            <div class="p-3 bg-light rounded-3 font-monospace small mb-3 border">
                <table class="table table-sm table-borderless mb-0">
                    <?php if (count($remote_data) > 0): ?>
                        <?php foreach ($remote_data as $key => $val): ?>
                            <?php 
                                $local_val = $local_data[$key] ?? null;
                                $is_mismatch = ($local_val !== null && (string)$local_val !== (string)$val);
                            ?>
                            <tr class="<?php echo $is_mismatch ? 'bg-info bg-opacity-25 fw-bold' : ''; ?>">
                                <td class="text-muted pe-3" style="width: 40%;"><?php echo sanitize($key); ?>:</td>
                                <td class="text-dark"><?php echo is_array($val) ? json_encode($val) : sanitize((string)$val); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td class="text-muted">No detailed cloud properties recorded.</td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <?php if ($conflict['status'] === 'unresolved' && has_permission('conflicts.resolve')): ?>
                <a href="resolve.php?action=keep_cloud&id=<?php echo $conflict['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-primary w-100 py-2 fw-bold">
                    <i class="bi bi-cloud-download me-1"></i> Overwrite with Cloud Master (Keep Cloud)
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
