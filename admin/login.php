<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin Panel - Staff & Administrator Login Page
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// If user is already logged in, redirect directly to admin dashboard
if (is_logged_in()) {
    redirect('admin/index.php');
}

$error_message = '';
$username_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error_message = "Security check failed. Please refresh the page and try again.";
    } else {
        $username_input = trim($_POST['username'] ?? '');
        $password_input = $_POST['password'] ?? '';

        if (empty($username_input) || empty($password_input)) {
            $error_message = "Please enter both username and password.";
        } else {
            // Authenticate user credentials
            $result = login_user($username_input, $password_input);
            if ($result['success']) {
                set_flash_message('success', 'Welcome back! You have successfully logged in.');
                redirect('admin/index.php');
            } else {
                $error_message = $result['message'];
            }
        }
    }
}

$page_title = "Staff Login - Admin Panel";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-5">
    <div class="col-md-6 col-lg-5">
        <div class="dw-card p-4 p-md-5 shadow-lg border-0">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-dark text-teal rounded-circle p-3 mb-3 shadow" style="width: 75px; height: 75px; color:#2dd4bf;">
                    <i class="bi bi-shield-lock-fill display-5"></i>
                </div>
                <h3 class="fw-bold text-dark mb-1">Dawaam Staff Portal</h3>
                <p class="text-muted small">Sign in to access local POS, inventory & system controls</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?php echo sanitize($error_message); ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <?php csrf_field(); ?>

                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold text-dark">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo sanitize($username_input); ?>" placeholder="e.g. admin or tariq_pharm" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label fw-semibold text-dark mb-0">Password</label>
                        <span class="small text-muted">Local Security Enforced</span>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-key-fill"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter account password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-dw-primary w-100 py-2.5 fw-bold fs-6">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Authenticate & Login
                </button>
            </form>

            <hr class="my-4 border-secondary border-opacity-25">

            <!-- Default Seed Demo Credentials Card -->
            <div class="p-3 bg-light rounded-3 border">
                <h6 class="fw-bold text-dark mb-2 small text-uppercase">
                    <i class="bi bi-info-circle-fill text-primary me-1"></i> Pre-Configured Demo Accounts
                </h6>
                <div class="d-flex justify-content-between align-items-center mb-1 small">
                    <span><strong class="text-dark">Super Admin:</strong> <code>admin</code></span>
                    <span class="badge bg-dark font-monospace">Admin@1234</span>
                </div>
                <div class="d-flex justify-content-between align-items-center small">
                    <span><strong class="text-dark">Pharmacist:</strong> <code>tariq_pharm</code></span>
                    <span class="badge bg-secondary font-monospace">Admin@1234</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
