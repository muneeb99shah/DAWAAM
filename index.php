<?php
/**
 * Dawaam - Local Business Continuity Software
 * Root Index Entry Point
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already logged in, redirect directly to their Operational Dashboard
if (is_logged_in()) {
    redirect('admin/index.php');
}

$page_title = "Local Business Continuity System";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<div class="row mb-5">
    <div class="col-12">
        <div class="dw-hero-banner">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill mb-3 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-wifi-off fs-6"></i> Zero-Internet Operation Mode
                    </span>
                    <h1 class="display-5 fw-bold mb-3 text-white">
                        Your business doesn't stop when the internet does.
                    </h1>
                    <p class="lead text-white-50 mb-4" style="font-size: 1.1rem; line-height: 1.6;">
                        Dawaam is a local-first business continuity system designed for businesses in Quetta, Balochistan. Run daily sales, manage pharmacy inventory, and trigger emergency SMS alerts over local Wi-Fi — with zero dependency on external cloud connections.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?php echo BASE_URL; ?>/admin/login.php" class="btn btn-warning btn-lg fw-bold px-4 py-2 text-dark">
                            <i class="bi bi-speedometer2 me-2"></i> Staff & POS Admin Login
                        </a>
                        <a href="<?php echo BASE_URL; ?>/test_db.php" class="btn btn-outline-light btn-lg px-4 py-2">
                            <i class="bi bi-shield-check me-2"></i> System Health Status
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 d-none d-lg-block text-center">
                    <div class="p-4 bg-dark bg-opacity-40 border border-light border-opacity-25 rounded-4 backdrop-blur">
                        <i class="bi bi-shield-lock-fill display-1 d-block mb-3" style="color:#2dd4bf;"></i>
                        <h5 class="fw-bold text-white mb-2">Local LAN Server Active</h5>
                        <p class="small text-white-50 mb-3">Connected devices operate seamlessly on local IP network.</p>
                        <div class="p-2 bg-black bg-opacity-60 rounded font-monospace small text-success fw-bold border border-secondary border-opacity-50">
                            <?php echo SERVER_LAN_IP; ?>:8000
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Architecture & Continuity Flow -->
<div class="row mb-5">
    <div class="col-12 text-center mb-4">
        <h2 class="fw-bold text-dark">How Dawaam Maintains Business Continuity</h2>
        <p class="text-muted">Three resilient layers keeping your operations active during cellular & internet blackouts</p>
    </div>
    
    <div class="col-md-4">
        <div class="dw-card h-100 p-4 text-center">
            <div class="mb-3 d-inline-flex align-items-center justify-content-center bg-teal-light rounded-circle p-3" style="width: 70px; height: 70px; background-color: #ccfbf1;">
                <i class="bi bi-router-fill fs-2 text-teal" style="color:#0f766e;"></i>
            </div>
            <h4 class="fw-bold mb-2 text-dark">1. Local Operations</h4>
            <p class="text-muted small mb-0">
                All devices inside the pharmacy communicate over local Wi-Fi/LAN. Register sales, update stock, and query inventory with zero internet access.
            </p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="dw-card h-100 p-4 text-center">
            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle p-3" style="width: 70px; height: 70px; background-color: #fef3c7;">
                <i class="bi bi-bell-fill fs-2 text-warning"></i>
            </div>
            <h4 class="fw-bold mb-2 text-dark">2. Urgent Alert Engine</h4>
            <p class="text-muted small mb-0">
                Continuous local rule evaluation detects critical insulin stock drops or unusually high sales, queuing urgent notifications automatically.
            </p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="dw-card h-100 p-4 text-center">
            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle p-3" style="width: 70px; height: 70px; background-color: #fee2e2;">
                <i class="bi bi-phone-vibrate-fill fs-2 text-danger"></i>
            </div>
            <h4 class="fw-bold mb-2 text-dark">3. SMS & Cloud Sync</h4>
            <p class="text-muted small mb-0">
                Emergency alerts leave the building via local Android SMS gateway SIM towers. When WAN returns, pending records sync automatically.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
