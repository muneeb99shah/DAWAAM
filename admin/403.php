<?php
/**
 * Dawaam - Local Business Continuity Software
 * 403 Access Denied Response Page
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$page_title = "403 Access Denied";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-8 col-lg-6 text-center">
        <div class="dw-card p-5 bg-white shadow-sm border-danger border-top border-4">
            <div class="mb-3">
                <i class="bi bi-shield-x text-danger display-1"></i>
            </div>
            <h2 class="fw-bold text-dark mb-2">403 — Access Denied</h2>
            <p class="text-muted mb-4">
                You do not have the required permissions to access this module or action.
            </p>

            <?php if (has_flash_message('danger')): ?>
                <div class="alert alert-danger mb-4 small">
                    <?php echo get_flash_message('danger'); ?>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-center gap-3">
                <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> Go Back
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-dw-primary btn-sm px-4">
                    <i class="bi bi-speedometer2 me-1"></i> Admin Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
