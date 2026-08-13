<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Edit User Account Details
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users.edit');

$user_id = (int)($_GET['id'] ?? 0);
if ($user_id <= 0) {
    set_flash_message('danger', 'Invalid user account ID specified.');
    redirect('admin/users/index.php');
}

$pdo = get_db_connection();
$roles = get_all_roles();

// Fetch Target User
$stmt = $pdo->prepare("
    SELECT u.id, u.user_code, u.name, u.username, u.email, u.status,
           ur.role_id
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    WHERE u.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $user_id]);
$target_user = $stmt->fetch();

if (!$target_user) {
    set_flash_message('danger', 'User account not found.');
    redirect('admin/users/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token validation failed.';
    }

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) $errors[] = 'Full name is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if ($role_id <= 0) $errors[] = 'Please select a valid system role.';
    if (!empty($new_password) && strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }

    // Username unique check for other users
    if (empty($errors)) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username AND id != :id");
        $stmt_check->execute([':username' => $username, ':id' => $user_id]);
        if ($stmt_check->fetchColumn() > 0) {
            $errors[] = "Username '{$username}' is already taken by another account.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if (!empty($new_password)) {
                $pwd_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt_upd = $pdo->prepare("
                    UPDATE users 
                    SET name = :name, username = :username, email = :email, password_hash = :pwd_hash, status = :status, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt_upd->execute([
                    ':name' => $name,
                    ':username' => $username,
                    ':email' => !empty($email) ? $email : null,
                    ':pwd_hash' => $pwd_hash,
                    ':status' => $status,
                    ':id' => $user_id
                ]);
            } else {
                $stmt_upd = $pdo->prepare("
                    UPDATE users 
                    SET name = :name, username = :username, email = :email, status = :status, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt_upd->execute([
                    ':name' => $name,
                    ':username' => $username,
                    ':email' => !empty($email) ? $email : null,
                    ':status' => $status,
                    ':id' => $user_id
                ]);
            }

            // Update Role Assignment
            $stmt_del_r = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
            $stmt_del_r->execute([':user_id' => $user_id]);

            $stmt_ins_r = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
            $stmt_ins_r->execute([':user_id' => $user_id, ':role_id' => $role_id]);

            log_audit_action('EDIT_USER', 'users', $user_id, "Updated user account details for '{$username}' ({$target_user['user_code']})");

            $pdo->commit();

            set_flash_message('success', "User account '{$name}' ({$target_user['user_code']}) updated successfully!");
            redirect('admin/users/index.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Edit User Error: ' . $e->getMessage());
            $errors[] = 'Failed to update user account: ' . $e->getMessage();
        }
    }
}

$page_title = "Edit User Account #" . $target_user['user_code'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-pencil-square text-primary me-2"></i> Edit User Account
        </h2>
        <p class="text-muted small mb-0">Modify user profile, reset password, change role assignment, or update account status.</p>
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

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="dw-card p-4 p-md-5 bg-white">
            <form action="edit.php?id=<?php echo $user_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <div class="p-3 bg-light rounded border mb-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small d-block">User ID (Code)</span>
                        <strong class="font-monospace fs-5 text-dark"><?php echo sanitize($target_user['user_code']); ?></strong>
                    </div>
                    <span class="badge bg-dark px-3 py-2 fs-6 font-monospace">Account #<?php echo $target_user['id']; ?></span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?php echo sanitize($_POST['name'] ?? $target_user['name']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" name="username" value="<?php echo sanitize($_POST['username'] ?? $target_user['username']); ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" class="form-control" name="email" value="<?php echo sanitize($_POST['email'] ?? $target_user['email']); ?>">
                    </div>

                    <div class="col-12">
                        <div class="p-3 bg-light rounded border">
                            <label class="form-label fw-semibold mb-1">Reset Password (Optional)</label>
                            <input type="password" class="form-control" name="new_password" placeholder="Leave blank to keep existing password...">
                            <div class="form-text small">Only fill this field if you wish to change the user's password.</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Primary System Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo (int)($target_user['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($r['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo $target_user['status'] === 'active' ? 'selected' : ''; ?>>Active (Granted Login Access)</option>
                            <option value="inactive" <?php echo $target_user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Disabled Login Access)</option>
                            <option value="suspended" <?php echo $target_user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended (Blocked Access)</option>
                        </select>
                    </div>

                    <div class="col-12 pt-3 border-top mt-4 d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dw-primary px-4">
                            <i class="bi bi-check-circle me-1"></i> Save User Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
