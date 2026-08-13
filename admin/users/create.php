<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Add New User Account
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('users.create');

$pdo = get_db_connection();
$roles = get_all_roles();

$user_code = generate_next_user_code();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token validation failed.';
    }

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) $errors[] = 'Full name is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters long.';
    if ($password !== $confirm_password) $errors[] = 'Password confirmation does not match.';
    if ($role_id <= 0) $errors[] = 'Please select a valid system role.';

    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'active';
    }

    // Check Unique Username
    if (empty($errors)) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR user_code = :user_code");
        $stmt_check->execute([':username' => $username, ':user_code' => $user_code]);
        if ($stmt_check->fetchColumn() > 0) {
            $errors[] = "Username '{$username}' or User Code '{$user_code}' is already taken.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $pwd_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt_ins = $pdo->prepare("
                INSERT INTO users (user_code, name, username, email, password_hash, status)
                VALUES (:user_code, :name, :username, :email, :password_hash, :status)
            ");
            $stmt_ins->execute([
                ':user_code' => $user_code,
                ':name' => $name,
                ':username' => $username,
                ':email' => !empty($email) ? $email : null,
                ':password_hash' => $pwd_hash,
                ':status' => $status
            ]);
            $new_user_id = $pdo->lastInsertId();

            // Insert User Role Assignment
            $stmt_ur = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
            $stmt_ur->execute([':user_id' => $new_user_id, ':role_id' => $role_id]);

            log_audit_action('CREATE_USER', 'users', $new_user_id, "Created new user account '{$username}' ({$user_code})");

            $pdo->commit();

            set_flash_message('success', "User account '{$name}' ({$user_code}) created successfully!");
            redirect('admin/users/index.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Create User Error: ' . $e->getMessage());
            $errors[] = 'Failed to create user account: ' . $e->getMessage();
        }
    }
}

$page_title = "Create New User Account";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-person-plus text-success me-2"></i> Create New User Account
        </h2>
        <p class="text-muted small mb-0">Generate a unique User ID (`DW-XXXX`), configure credentials, and assign system role permissions.</p>
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
            <form action="create.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <!-- Auto-Generated User Code Banner -->
                <div class="p-3 bg-light rounded border mb-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small d-block">Generated Unique User ID Code</span>
                        <strong class="font-monospace fs-5 text-dark"><?php echo sanitize($user_code); ?></strong>
                    </div>
                    <span class="badge bg-dark px-3 py-2 fs-6 font-monospace">Auto-Generated</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Dr. Muhammad Tariq" value="<?php echo sanitize($_POST['name'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" name="username" placeholder="e.g. tariq_pharm" value="<?php echo sanitize($_POST['username'] ?? ''); ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Email Address (Optional)</label>
                        <input type="email" class="form-control" name="email" placeholder="e.g. tariq@dawaam.local" value="<?php echo sanitize($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                        <div class="form-text small">Minimum 6 characters.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Primary System Role <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo (int)($_POST['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($r['name']); ?> (<?php echo sanitize($r['description']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Account Login Status</label>
                        <select name="status" class="form-select">
                            <option value="active" selected>Active (Granted Login Access)</option>
                            <option value="inactive">Inactive (Disabled Login Access)</option>
                        </select>
                    </div>

                    <div class="col-12 pt-3 border-top mt-4 d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dw-primary px-4">
                            <i class="bi bi-person-check me-1"></i> Create User Account
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
