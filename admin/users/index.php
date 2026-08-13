<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - User Account & RBAC Access Control Directory
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users.view');

$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';

// KPI Stats
$stat_total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stat_active = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$stat_inactive = $pdo->query("SELECT COUNT(*) FROM users WHERE status != 'active'")->fetchColumn();
$stat_super_admin = $pdo->query("SELECT COUNT(*) FROM user_roles WHERE role_id = 1")->fetchColumn();

// Auto-Suggest List for User Search
$suggest_raw = $pdo->query("SELECT name FROM users UNION SELECT username FROM users UNION SELECT user_code FROM users")->fetchAll(PDO::FETCH_COLUMN);

// Build Filter SQL
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE :search_name OR u.username LIKE :search_uname OR u.user_code LIKE :search_code OR u.email LIKE :search_email)";
    $params[':search_name'] = "%{$search}%";
    $params[':search_uname'] = "%{$search}%";
    $params[':search_code'] = "%{$search}%";
    $params[':search_email'] = "%{$search}%";
}

if ($status_filter !== 'all' && in_array($status_filter, ['active', 'inactive', 'suspended'], true)) {
    $where_clauses[] = "u.status = :status";
    $params[':status'] = $status_filter;
}

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 15);

$paginated_res = get_paginated_data($pdo, [
    'table' => 'users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id',
    'select_fields' => "u.id, u.user_code, u.name, u.username, u.email, u.status, u.created_at, GROUP_CONCAT(r.name SEPARATOR ', ') AS role_names, GROUP_CONCAT(r.slug SEPARATOR ', ') AS role_slugs",
    'where_clause' => (count($where_clauses) > 0 ? implode(" AND ", $where_clauses) : ''),
    'group_by' => 'u.id',
    'params' => $params,
    'order_by' => 'u.id ASC',
    'page' => $page,
    'limit' => $limit,
    'count_field' => 'DISTINCT u.id'
]);

$users_list = $paginated_res['data'];
$pagination = $paginated_res['pagination'];

$page_title = "User & Access Control Directory";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-people text-primary me-2"></i> User Account & Access Control Directory
        </h2>
        <p class="text-muted small mb-0">Super Admin user account management, User ID (`DW-XXXX`) generation, role assignments, and granular access permissions.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="permissions.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-shield-lock me-1"></i> Permission Matrix
        </a>
        <?php if (has_permission('users.create')): ?>
            <a href="create.php" class="btn btn-dw-primary btn-sm px-3">
                <i class="bi bi-person-plus me-1"></i> Add New User Account
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Metrics Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Total Accounts</div>
            <div class="dw-stat-value"><?php echo number_format($stat_total); ?></div>
            <span class="small text-muted"><i class="bi bi-person-badge me-1"></i> Registered System Users</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-emerald">
            <div class="dw-stat-label">Active Users</div>
            <div class="dw-stat-value"><?php echo number_format($stat_active); ?></div>
            <span class="small text-muted"><i class="bi bi-check-circle me-1"></i> Authorized Login Access</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-rose">
            <div class="dw-stat-label">Inactive / Suspended</div>
            <div class="dw-stat-value"><?php echo number_format($stat_inactive); ?></div>
            <span class="small text-muted"><i class="bi bi-x-circle me-1"></i> Disabled Credentials</span>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="dw-stat-card stat-amber">
            <div class="dw-stat-label">Super Administrators</div>
            <div class="dw-stat-value"><?php echo number_format($stat_super_admin); ?></div>
            <span class="small text-muted"><i class="bi bi-shield-check me-1"></i> Master Control Privileges</span>
        </div>
    </div>
</div>

<!-- Filter & Search Bar with Auto-Suggest Datalist -->
<div class="dw-card dw-filter-card mb-3">
    <form id="users-filter-form" action="index.php" method="GET" class="row g-2 align-items-center">
        <div class="col-12 col-md-7">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control form-control-sm" name="search" id="userSearchInput" list="userSuggestions" autocomplete="off" placeholder="Search by name, username, User Code (DW-XXXX), or email..." value="<?php echo sanitize($search); ?>">
                <datalist id="userSuggestions">
                    <?php foreach ($suggest_raw as $s_item): ?>
                        <option value="<?php echo sanitize($s_item); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-dw-primary btn-sm w-100">Filter</button>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Users Table -->
<?php $total_users_count = $pagination['total_records'] ?? count($users_list); ?>
<div class="row">
    <div class="col-12">
        <div class="dw-card overflow-hidden">
            <div class="dw-card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">User Accounts Registry (<?php echo number_format($total_users_count); ?> Accounts)</span>
                <span class="badge bg-dark rounded-pill"><?php echo number_format($total_users_count); ?> Total</span>
            </div>
            <div class="card-body p-0">
                <?php if (count($users_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.8125rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 text-nowrap" style="width: 140px;">User ID (Code)</th>
                                    <th class="text-nowrap">Full Name & Username</th>
                                    <th class="text-nowrap">Email Address</th>
                                    <th class="text-nowrap">Assigned Role(s)</th>
                                    <th class="text-nowrap">Account Status</th>
                                    <th class="text-nowrap">Registered Date</th>
                                    <th class="text-end pe-3 text-nowrap" style="width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users_list as $u): ?>
                                    <?php 
                                        $role_names = $u['role_names'] ?? 'Regular User';
                                        $role_slugs = explode(', ', $u['role_slugs'] ?? '');
                                        $is_super = in_array('super_admin', $role_slugs, true);
                                    ?>
                                    <tr>
                                        <td class="ps-3 text-nowrap">
                                            <span class="badge bg-dark font-monospace fs-7 px-2 py-1">
                                                <?php echo sanitize($u['user_code']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block lh-sm"><?php echo sanitize($u['name']); ?></strong>
                                            <span class="small text-muted font-monospace">@<?php echo sanitize($u['username']); ?></span>
                                        </td>
                                        <td class="small text-dark text-nowrap">
                                            <?php echo !empty($u['email']) ? sanitize($u['email']) : '<span class="text-muted">None</span>'; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if ($is_super): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">
                                                    <i class="bi bi-shield-check me-1"></i> Super Admin
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark px-2 py-1">
                                                    <i class="bi bi-person-badge me-1"></i> <?php echo sanitize($role_names); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if ($u['status'] === 'active'): ?>
                                                <span class="badge bg-success px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i> Active
                                                </span>
                                            <?php elseif ($u['status'] === 'inactive'): ?>
                                                <span class="badge bg-secondary px-2 py-1">
                                                    <i class="bi bi-pause-circle me-1"></i> Inactive
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger px-2 py-1">
                                                    <i class="bi bi-x-circle me-1"></i> Suspended
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted text-nowrap">
                                            <?php echo format_date($u['created_at']); ?>
                                        </td>
                                        <td class="text-end pe-3 text-nowrap">
                                            <div class="btn-group btn-group-sm">
                                                <?php if (has_permission('users.manage')): ?>
                                                    <a href="user_permissions.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-info btn-table-action" title="Manage Individual User Permissions">
                                                        <i class="bi bi-shield-lock"></i> Perms
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (has_permission('users.edit')): ?>
                                                    <a href="edit.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-primary btn-table-action" title="Edit User Details">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (has_permission('users.disable') && $u['id'] != 1): ?>
                                                    <a href="toggle_status.php?id=<?php echo $u['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn <?php echo $u['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success'; ?> btn-table-action" title="Toggle Account Status">
                                                        <i class="bi bi-power"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (has_permission('users.delete') && $u['id'] != 1 && $u['id'] != $_SESSION['user_id']): ?>
                                                    <a href="delete.php?id=<?php echo $u['id']; ?>&csrf_token=<?php echo generate_csrf_token(); ?>" class="btn btn-outline-danger btn-table-action" onclick="return confirm('Are you sure you want to delete user account <?php echo sanitize($u['user_code']); ?>?');" title="Delete User Account">
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
                    <?php echo render_pagination_links($pagination, 'index.php', ['search' => $search, 'status' => $status_filter]); ?>
                <?php else: ?>
                    <div class="dw-empty-state">
                        <i class="bi bi-people dw-empty-state-icon"></i>
                        <div class="dw-empty-state-title">No User Accounts Found</div>
                        <div class="dw-empty-state-text">No user accounts match your search criteria. Try resetting your search filter.</div>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
                            <?php if (has_permission('users.create')): ?>
                                <a href="create.php" class="btn btn-dw-primary btn-sm">
                                    <i class="bi bi-person-plus me-1"></i> Add User Account
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/datatable_helper.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    DawaamDataTable.attachDebouncedSearch('#userSearchInput', '#users-filter-form', 350);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
