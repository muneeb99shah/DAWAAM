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

<!-- Pre-Configured Development & Demo Environment Section -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pb-2 border-bottom">
            <div>
                <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam-fill" style="color: #0f766e;"></i>
                    Pre-Configured Development & Demo Environment
                </h3>
                <p class="text-muted small mb-0">
                    Pre-loaded business scenario and system seed credentials for testing Dawaam's offline continuity features.
                </p>
            </div>
            <span class="badge px-3 py-2 rounded-pill fw-semibold" style="background-color: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4;">
                <i class="bi bi-tools me-1"></i> Demo Environment Active
            </span>
        </div>
    </div>

    <!-- Initial Target Business: Pilot Pharmacy -->
    <div class="col-lg-7">
        <div class="dw-card p-4 h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <div class="p-2 rounded-3 me-3" style="background-color: #e0f2fe; color: #0284c7;">
                            <i class="bi bi-hospital fs-3"></i>
                        </div>
                        <div>
                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 small fw-semibold uppercase mb-1">
                                Pilot Configuration
                            </span>
                            <h4 class="fw-bold mb-0 text-dark">Initial Target Business: Pilot Pharmacy</h4>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-geo-alt-fill text-danger"></i>
                        <strong class="text-dark fs-6">Quetta Medical & Pharmacy Store</strong>
                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-auto">Active Target Model</span>
                    </div>
                    <p class="text-muted small mb-0">
                        Dawaam is pre-configured with this pharmacy model so users, developers, and evaluators can immediately understand and test the system's business-continuity features in a real-world operational scenario.
                    </p>
                </div>

                <h6 class="fw-bold text-dark mb-2 small text-uppercase tracking-wider">
                    <i class="bi bi-shield-check text-success me-1"></i> Outage Management Capabilities
                </h6>
                <div class="row g-2 mb-4">
                    <div class="col-sm-6">
                        <div class="p-2 rounded border bg-white small text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-capsule text-primary"></i> Medicine inventory & stock levels
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2 rounded border bg-white small text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-graph-down-arrow text-warning"></i> Low-stock thresholds
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2 rounded border bg-white small text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-receipt text-success"></i> Sales & thermal receipts
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2 rounded border bg-white small text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-heart-pulse-fill text-danger"></i> Insulin & critical medicine monitor
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2 rounded border bg-white small text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-bell-fill text-warning"></i> Emergency alerts & SMS gateway
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2 rounded border bg-white small text-muted d-flex align-items-center gap-2">
                            <i class="bi bi-wifi-off text-dark"></i> Local-first internet-free operation
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seed Data Summary -->
            <div class="row g-3 pt-3 border-top">
                <div class="col-sm-6">
                    <div class="dw-stat-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="dw-stat-label mb-0">Initial Seed Products</span>
                            <span class="badge bg-primary-subtle text-primary border">Demo Catalog</span>
                        </div>
                        <div class="dw-stat-value text-dark fs-3 fw-bold">4 Items</div>
                        <span class="small text-muted d-block mt-1">
                            <i class="bi bi-info-circle me-1"></i> Humulin Insulin, Amoxicillin, etc.
                        </span>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="dw-stat-card stat-amber h-100">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="dw-stat-label mb-0">Low Stock Threshold</span>
                            <span class="badge bg-warning-subtle text-warning-emphasis border">Rule Trigger</span>
                        </div>
                        <div class="dw-stat-value text-warning-emphasis fs-3 fw-bold">5 Units</div>
                        <span class="small text-muted d-block mt-1">
                            <i class="bi bi-exclamation-circle me-1"></i> Reaching threshold triggers urgent alert
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Seed Accounts -->
    <div class="col-lg-5">
        <div class="dw-card p-4 h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <div class="p-2 rounded-3 me-3" style="background-color: #fef3c7; color: #d97706;">
                            <i class="bi bi-person-badge-fill fs-3"></i>
                        </div>
                        <div>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 small fw-semibold uppercase mb-1">
                                Development Credentials
                            </span>
                            <h4 class="fw-bold mb-0 text-dark">System Seed Accounts</h4>
                        </div>
                    </div>
                </div>

                <p class="text-muted small mb-3">
                    This landing page represents a <strong>working local development/demo system</strong>. Predefined accounts are provided below so developers and evaluators can immediately test authentication, permissions, and role-based access without creating accounts manually.
                </p>

                <!-- Super Admin Card -->
                <div class="mb-3 p-3 bg-light rounded-3 border">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <strong class="text-dark">Super Admin Role</strong>
                                <span class="badge bg-dark text-white px-2 py-1 fs-xs">Full Control</span>
                            </div>
                            <span class="text-muted small">Access to management, permissions, and settings</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                        <div class="small">
                            <span class="text-muted me-1">Username:</span>
                            <code class="fw-bold text-primary bg-white px-2 py-1 rounded border">admin</code>
                        </div>
                        <div class="small">
                            <span class="text-muted me-1">Password:</span>
                            <span class="badge bg-dark px-2 py-1 font-monospace text-white">Admin@1234</span>
                        </div>
                    </div>
                </div>

                <!-- Pharmacist Account Card -->
                <div class="mb-3 p-3 bg-light rounded-3 border">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <strong class="text-dark">Pharmacist Role</strong>
                                <span class="badge text-white px-2 py-1 fs-xs" style="background-color: #0f766e;">POS & Stock</span>
                            </div>
                            <span class="text-muted small">Access to sales checkout, inventory & thermal receipts</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                        <div class="small">
                            <span class="text-muted me-1">Username:</span>
                            <code class="fw-bold text-primary bg-white px-2 py-1 rounded border">tariq_pharm</code>
                        </div>
                        <div class="small">
                            <span class="text-muted me-1">Password:</span>
                            <span class="badge bg-secondary px-2 py-1 font-monospace text-white">Admin@1234</span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="p-3 bg-warning-subtle text-warning-emphasis rounded-3 border border-warning-subtle small d-flex gap-2 mb-3">
                    <i class="bi bi-shield-exclamation fs-5 flex-shrink-0 text-warning"></i>
                    <div>
                        <strong>Development Credentials Only:</strong>
                        These seed accounts are provided solely for local testing of roles and permissions during development. They are not production credentials.
                    </div>
                </div>
                <div class="text-center">
                    <a href="<?php echo BASE_URL; ?>/admin/login.php" class="btn btn-primary w-100 fw-semibold py-2">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Test Login with Seed Accounts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
