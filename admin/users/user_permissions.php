<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Individual User Permission Overrides Manager
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users.manage');

$pdo = get_db_connection();

$target_user_id = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);

if ($target_user_id <= 0) {
    set_flash_message('danger', 'Invalid user account specified.');
    redirect('admin/users/index.php');
}

// Fetch Target User Information
$stmt_target = $pdo->prepare("SELECT id, user_code, name, username, email, status FROM users WHERE id = :id LIMIT 1");
$stmt_target->execute([':id' => $target_user_id]);
$target_user = $stmt_target->fetch();

if (!$target_user) {
    set_flash_message('danger', 'User account not found.');
    redirect('admin/users/index.php');
}

// Fetch Target User Roles
$stmt_user_roles = $pdo->prepare("
    SELECT r.id, r.name, r.slug
    FROM roles r
    INNER JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = :uid
");
$stmt_user_roles->execute([':uid' => $target_user_id]);
$target_roles = $stmt_user_roles->fetchAll();
$target_role_slugs = array_column($target_roles, 'slug');
$is_target_super_admin = in_array(ROLE_SUPER_ADMIN, $target_role_slugs) || in_array('super_admin', $target_role_slugs);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token validation failed.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $submitted_overrides = $_POST['overrides'] ?? []; // format: overrides[permission_id] = DEFAULT|ALLOW|DENY

            // Delete existing overrides for target user
            $stmt_del = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :uid");
            $stmt_del->execute([':uid' => $target_user_id]);

            // Insert non-DEFAULT overrides
            $stmt_ins = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id, state) VALUES (:uid, :pid, :state)");

            foreach ($submitted_overrides as $pid => $state) {
                $pid = (int)$pid;
                $state = strtoupper(trim($state));

                if (in_array($state, ['ALLOW', 'DENY'])) {
                    $stmt_ins->execute([
                        ':uid' => $target_user_id,
                        ':pid' => $pid,
                        ':state' => $state
                    ]);
                }
            }

            log_audit_action('UPDATE_USER_PERMISSIONS', 'users', $target_user_id, "Updated individual permission overrides for user: {$target_user['username']}");

            $pdo->commit();

            set_flash_message('success', "Individual permission overrides updated successfully for user '{$target_user['name']}'.");
            redirect("admin/users/user_permissions.php?id={$target_user_id}");
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Update User Permissions Error: ' . $e->getMessage());
            $errors[] = 'Failed to update user permissions: ' . $e->getMessage();
        }
    }
}

// Fetch Effective Permission Matrix for Target User
$matrix_items = get_user_effective_permissions_matrix($target_user_id);
$grouped_matrix = [];
foreach ($matrix_items as $item) {
    $grouped_matrix[$item['module']][] = $item;
}

$page_title = "Manage User Permissions - " . $target_user['name'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-person-lock text-primary me-2"></i> Individual User Permission Overrides
        </h2>
        <p class="text-muted small mb-0">Configure user-specific ALLOW/DENY permission overrides for <?php echo sanitize($target_user['name']); ?>.</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to User Directory
        </a>
    </div>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?php echo sanitize($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Target User Metadata Summary Card -->
<div class="dw-card p-3 mb-4 bg-white shadow-sm border-start border-4 border-primary">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h5 class="fw-bold text-dark mb-0" style="font-size: 1.05rem;"><?php echo sanitize($target_user['name']); ?></h5>
                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.72rem;">Code: <?php echo sanitize($target_user['user_code']); ?></span>
            </div>
            <p class="text-muted small mb-0" style="font-size: 0.8125rem;">
                Username: <code><?php echo sanitize($target_user['username']); ?></code> &bull; Email: <code><?php echo sanitize($target_user['email']); ?></code>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-semibold me-1" style="font-size: 0.75rem;">Assigned Role:</span>
            <?php foreach ($target_roles as $tr): ?>
                <span class="badge bg-primary px-2.5 py-1 fw-bold" style="font-size: 0.75rem;">
                    <i class="bi bi-shield-check me-1"></i> <?php echo sanitize($tr['name']); ?>
                </span>
            <?php endforeach; ?>

            <?php if ($is_target_super_admin): ?>
                <span class="badge bg-success px-2.5 py-1 fw-bold ms-2" style="font-size: 0.75rem;">
                    <i class="bi bi-star-fill me-1"></i> Full Access Unrestricted
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<form action="user_permissions.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="user_id" value="<?php echo $target_user_id; ?>">

    <div class="dw-card mb-4 shadow-sm">
        <div class="dw-card-header d-flex justify-content-between align-items-center py-2.5 px-3">
            <span class="fw-bold text-dark" style="font-size: 0.9rem;"><i class="bi bi-sliders me-1.5 text-primary"></i> Role Permissions vs User Individual Overrides Matrix</span>
            <button type="submit" class="btn btn-dw-primary btn-sm px-3">
                <i class="bi bi-check-circle me-1"></i> Save User Permissions
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 border-0">
                    <thead class="table-dark text-white" style="background-color: #0f172a; font-size: 0.72rem;">
                        <tr>
                            <th class="ps-3 py-2 text-white text-uppercase" style="min-width: 260px; background-color: #0f172a;">Permission & Description</th>
                            <th class="text-center py-2 text-white text-uppercase" style="min-width: 110px; background-color: #0f172a;">Role Default</th>
                            <th class="text-center py-2 text-white text-uppercase" style="min-width: 180px; background-color: #0f172a;">User Individual Override</th>
                            <th class="text-center py-2 text-white text-uppercase" style="min-width: 120px; background-color: #0f172a;">Effective Access</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped_matrix as $module_name => $items): ?>
                            <tr class="table-light border-bottom">
                                <td colspan="4" class="ps-3 py-1.5 bg-secondary bg-opacity-10 fw-bold text-dark text-uppercase small" style="font-size: 0.72rem;">
                                    <i class="bi bi-folder2-open me-1.5 text-primary"></i> Module: <?php echo sanitize($module_name); ?>
                                </td>
                            </tr>
                            <?php foreach ($items as $m): ?>
                                <tr>
                                    <td class="ps-3 py-1.5">
                                        <strong class="text-dark d-block" style="font-size: 0.8rem; font-weight: 600;"><?php echo sanitize($m['description']); ?></strong>
                                        <code class="text-primary" style="font-size: 0.68rem; font-family: var(--dw-font-mono);"><?php echo sanitize($m['permission_key']); ?></code>
                                    </td>

                                    <!-- Role Default Column -->
                                    <td class="text-center py-1.5">
                                        <?php if ($m['role_has']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 fw-bold" style="font-size: 0.68rem;">
                                                <i class="bi bi-check-circle me-1"></i> YES
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-0.5 fw-semibold" style="font-size: 0.68rem;">
                                                <i class="bi bi-x-circle me-1"></i> NO
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- User Individual Override Select (INHERIT ROLE / ALLOW / DENIED) -->
                                    <td class="text-center py-1.5">
                                        <?php if ($is_target_super_admin): ?>
                                            <span class="text-muted font-monospace" style="font-size: 0.68rem;">Super Admin Full Access</span>
                                        <?php else: ?>
                                            <select name="overrides[<?php echo $m['id']; ?>]" class="form-select form-select-sm d-inline-block text-center fw-semibold" style="max-width: 165px; font-size: 0.72rem; height: 26px; padding: 1.5px 4px; line-height: 1.2;">
                                                <option value="DEFAULT" <?php echo $m['override_state'] === 'DEFAULT' ? 'selected' : ''; ?>>
                                                    INHERIT ROLE (Default)
                                                </option>
                                                <option value="ALLOW" class="text-success fw-bold" <?php echo $m['override_state'] === 'ALLOW' ? 'selected' : ''; ?>>
                                                    ALLOW (Explicit Grant)
                                                </option>
                                                <option value="DENY" class="text-danger fw-bold" <?php echo $m['override_state'] === 'DENY' ? 'selected' : ''; ?>>
                                                    DENIED (Explicit Deny)
                                                </option>
                                            </select>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Effective Final Access Preview -->
                                    <td class="text-center py-1.5">
                                        <?php if ($m['effective']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-0.5 fw-bold" style="font-size: 0.68rem;">
                                                <i class="bi bi-shield-check me-1"></i> ALLOWED
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-0.5 fw-bold" style="font-size: 0.68rem;">
                                                <i class="bi bi-shield-x me-1"></i> DENIED
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white p-3 d-flex justify-content-end border-top">
            <button type="submit" class="btn btn-dw-primary btn-sm px-4">
                <i class="bi bi-check-circle me-1"></i> Save User Permission Overrides
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
