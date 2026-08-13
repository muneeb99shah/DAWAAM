<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Granular Role & Permission Matrix Manager
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users.manage');

$pdo = get_db_connection();

// Fetch All Roles
$roles = $pdo->query("SELECT id, name, slug, description FROM roles ORDER BY id ASC")->fetchAll();

// Fetch All Permissions grouped by module
$all_permissions = $pdo->query("SELECT id, module, action, permission_key, description FROM permissions ORDER BY module ASC, id ASC")->fetchAll();

$grouped_permissions = [];
foreach ($all_permissions as $p) {
    $grouped_permissions[$p['module']][] = $p;
}

// Fetch Current Role Permission Mappings: role_id => [permission_id1, permission_id2]
$role_perms_raw = $pdo->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll();
$role_permissions = [];
foreach ($role_perms_raw as $rp) {
    $role_permissions[$rp['role_id']][] = $rp['permission_id'];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token validation failed.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Clear existing non-super_admin permissions or re-map all roles
            $submitted_permissions = $_POST['role_perms'] ?? []; // format: role_perms[role_id][] = permission_id

            // Truncate role_permissions table
            $pdo->exec("DELETE FROM role_permissions");

            $stmt_ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");

            // Enforce Super Admin (Role ID 1) gets ALL permissions automatically
            foreach ($all_permissions as $p) {
                $stmt_ins->execute([':role_id' => 1, ':permission_id' => $p['id']]);
            }

            // Insert submitted permissions for other roles
            foreach ($submitted_permissions as $r_id => $p_ids) {
                $r_id = (int)$r_id;
                if ($r_id === 1) continue; // Skip super admin, already granted all

                if (is_array($p_ids)) {
                    foreach ($p_ids as $p_id) {
                        $stmt_ins->execute([':role_id' => $r_id, ':permission_id' => (int)$p_id]);
                    }
                }
            }

            log_audit_action('UPDATE_ROLE_PERMISSIONS', 'users', null, "Super Admin updated system granular permission matrix");

            $pdo->commit();

            set_flash_message('success', 'System role permission matrix updated successfully!');
            redirect('admin/users/permissions.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Update Permissions Error: ' . $e->getMessage());
            $errors[] = 'Failed to update permissions matrix: ' . $e->getMessage();
        }
    }
}

$page_title = "Granular Permission Matrix";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-shield-lock text-primary me-2"></i> Granular Permission Matrix Manager
        </h2>
        <p class="text-muted small mb-0">Configure which operational features each role can access across the local system.</p>
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

<form action="permissions.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

    <div class="dw-card mb-4">
        <div class="dw-card-header d-flex justify-content-between align-items-center">
            <span>Module Feature Permissions vs System Roles Matrix</span>
            <button type="submit" class="btn btn-dw-primary btn-sm px-3">
                <i class="bi bi-save me-1"></i> Save Permission Matrix
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 border-0">
                    <thead class="table-dark text-white" style="background-color: #0f172a;">
                        <tr>
                            <th class="ps-4 text-white py-3" style="min-width: 250px; background-color: #0f172a;">System Module & Permission Key</th>
                            <?php foreach ($roles as $r): ?>
                                <th class="text-center py-3" style="min-width: 145px; background-color: #0f172a;">
                                    <span class="d-block text-white fw-bold fs-6"><?php echo sanitize($r['name']); ?></span>
                                    <span class="small font-monospace d-block mt-0.5" style="font-size: 0.78rem; color: #a7f3d0; opacity: 0.95;"><?php echo sanitize($r['slug']); ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped_permissions as $module_name => $perms): ?>
                            <tr class="table-light border-bottom">
                                <td colspan="<?php echo count($roles) + 1; ?>" class="ps-4 py-2 bg-secondary bg-opacity-10 fw-bold text-dark text-uppercase small">
                                    <i class="bi bi-folder2-open me-2 text-primary"></i> Module: <?php echo sanitize($module_name); ?>
                                </td>
                            </tr>
                            <?php foreach ($perms as $p): ?>
                                <tr>
                                    <td class="ps-4 py-2">
                                        <strong class="text-dark d-block"><?php echo sanitize($p['description']); ?></strong>
                                        <code class="text-primary small"><?php echo sanitize($p['permission_key']); ?></code>
                                    </td>
                                    <?php foreach ($roles as $r): ?>
                                        <?php 
                                            $is_super = ($r['id'] == 1);
                                            $is_checked = $is_super || (isset($role_permissions[$r['id']]) && in_array($p['id'], $role_permissions[$r['id']]));
                                        ?>
                                        <td class="text-center py-2">
                                            <?php if ($is_super): ?>
                                                <i class="bi bi-check-circle-fill text-success fs-5" title="Super Admin automatically retains all permissions"></i>
                                            <?php else: ?>
                                                <div class="form-check form-switch d-inline-block m-0 p-0" style="min-height: auto;">
                                                    <input class="form-check-input dw-permission-switch m-0" type="checkbox" role="switch"
                                                           name="role_perms[<?php echo $r['id']; ?>][]" 
                                                           value="<?php echo $p['id']; ?>" 
                                                           id="perm_<?php echo $r['id']; ?>_<?php echo $p['id']; ?>"
                                                           <?php echo $is_checked ? 'checked' : ''; ?>
                                                           aria-label="Toggle <?php echo sanitize($p['description']); ?> for <?php echo sanitize($r['name']); ?>">
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white p-3 d-flex justify-content-end border-top">
            <button type="submit" class="btn btn-dw-primary px-4">
                <i class="bi bi-save me-1"></i> Save Permission Matrix
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
