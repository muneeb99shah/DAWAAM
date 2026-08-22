<?php
/**
 * Dawaam - Local Business Continuity Software
 * About Page - System Mission & Problem Statement
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already logged in, redirect directly to their Operational Dashboard
if (is_logged_in()) {
    redirect('admin/index.php');
}

$page_title = "About Business Continuity & Local-First Mission";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<div class="row mb-5">
    <div class="col-12">
        <div class="dw-hero-banner">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill mb-3 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-shield-check fs-6"></i> System Architecture & Mission
                    </span>
                    <h1 class="display-5 fw-bold mb-3 text-white">
                        Built for Local Commercial Resilience in Quetta
                    </h1>
                    <p class="lead text-white-50 mb-0" style="font-size: 1.1rem; line-height: 1.6;">
                        Dawaam is engineered specifically to address internet and mobile data blackouts in Quetta, Balochistan, Pakistan — ensuring local pharmacies, clinics, and businesses maintain 100% operational uptime regardless of external network shutdowns.
                    </p>
                </div>
                <div class="col-lg-4 d-none d-lg-block text-center">
                    <div class="p-4 bg-dark bg-opacity-40 border border-light border-opacity-25 rounded-4 backdrop-blur">
                        <i class="bi bi-geo-alt-fill text-danger display-1 d-block mb-2"></i>
                        <h5 class="fw-bold text-white mb-1">Quetta, Balochistan</h5>
                        <p class="small text-white-50 mb-0">Local-First Business Continuity Platform</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section 1: The Problem & The Cloud Paradox -->
<div class="row g-4 mb-5">
    <div class="col-lg-6">
        <div class="dw-card h-100 p-4 border-start border-4 border-danger">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3 me-3">
                    <i class="bi bi-wifi-off fs-2"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-0">The Problem: Internet Blackouts</h4>
                    <span class="text-muted small">Quetta & Regional Commercial Reality</span>
                </div>
            </div>
            <p class="text-muted">
                In Quetta and surrounding regions of Balochistan, cellular data and broadband internet access are frequently suspended due to security measures, maintenance, or regional infrastructure failures. These outages can last for hours or even several days at a time.
            </p>
            <p class="text-muted mb-0">
                When a business relies on traditional cloud-only POS systems, an internet blackout immediately brings daily operations to a halt — staff cannot process medicine sales, check stock levels, or print receipts.
            </p>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="dw-card h-100 p-4 border-start border-4 border-warning">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 me-3">
                    <i class="bi bi-cloud-slash fs-2"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-0">Why Cloud-Only Systems Fail</h4>
                    <span class="text-muted small">Single Point of Failure Vulnerability</span>
                </div>
            </div>
            <p class="text-muted">
                Most modern Point-of-Sale (POS) and inventory software are hosted entirely on cloud servers. Every barcode scan, customer lookup, and checkout transaction requires a continuous round-trip request over the internet.
            </p>

            <ul class="list-unstyled text-muted small mb-0">
                <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> <strong>Zero Connectivity = Zero Sales</strong>: Checkout counters freeze when cloud servers become unreachable.</li>
                <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> <strong>Stock Blindness</strong>: Inventory managers cannot check medicine quantities or critical thresholds.</li>
                <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> <strong>Owner Disconnect</strong>: Urgent stock emergencies (such as insulin depletion) fail to trigger alerts.</li>
            </ul>
        </div>
    </div>
</div>

<!-- Section 2: The Dawaam Solution & Mission -->
<div class="row mb-5">
    <div class="col-12">
        <div class="dw-card p-4 p-md-5 bg-white shadow-sm">
            <div class="text-center max-w-75 mx-auto mb-4">
                <span class="badge bg-teal text-white px-3 py-2 rounded-pill mb-2" style="background-color:#0f766e;">The Dawaam Philosophy</span>
                <h2 class="fw-bold text-dark">Local-First Business Continuity</h2>
                <p class="text-muted">
                    Dawaam shifts the core authority of software execution back into the business building. Instead of depending on distant cloud data centers, Dawaam operates an autonomous local server inside your store.
                </p>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3 text-center h-100">
                        <i class="bi bi-hdd-rack text-teal fs-1 mb-2 d-block" style="color:#0f766e;"></i>
                        <h5 class="fw-bold text-dark">Local Database</h5>
                        <p class="small text-muted mb-0">
                            All products, user permissions, stock levels, and sales transactions are stored in a high-speed local database on your local server.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3 text-center h-100">
                        <i class="bi bi-wifi text-success fs-1 mb-2 d-block"></i>
                        <h5 class="fw-bold text-dark">Wi-Fi LAN Grid</h5>
                        <p class="small text-muted mb-0">
                            Phones, tablets, and billing terminals connect over the store's local Wi-Fi router. Devices communicate instantly without needing an active internet connection.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3 text-center h-100">
                        <i class="bi bi-phone-vibrate text-warning fs-1 mb-2 d-block"></i>
                        <h5 class="fw-bold text-dark">SMS Fallback</h5>
                        <p class="small text-muted mb-0">
                            Critical business events (low insulin stock, large purchases) trigger automated SMS text alerts sent directly via a local Android cellular gateway.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section 3: Target Business Adaptability -->
<div class="row mb-4">
    <div class="col-12 text-center mb-4">
        <h3 class="fw-bold text-dark">Adaptable Across Local Sectors</h3>
        <p class="text-muted">Starting with a pilot pharmacy, Dawaam's architecture scales to any local business</p>
    </div>

    <div class="col-md-4 col-sm-6 mb-3">
        <div class="dw-card p-3 d-flex align-items-center">
            <i class="bi bi-capsule text-danger fs-2 me-3"></i>
            <div>
                <strong class="text-dark d-block">Pharmacies & Clinics</strong>
                <span class="small text-muted">Stock monitoring & urgent medicine sales</span>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-sm-6 mb-3">
        <div class="dw-card p-3 d-flex align-items-center">
            <i class="bi bi-shop text-primary fs-2 me-3"></i>
            <div>
                <strong class="text-dark d-block">General Stores & Retail</strong>
                <span class="small text-muted">Multi-register POS checkout continuity</span>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-sm-6 mb-3">
        <div class="dw-card p-3 d-flex align-items-center">
            <i class="bi bi-box-seam text-warning fs-2 me-3"></i>
            <div>
                <strong class="text-dark d-block">Warehouses & Logistics</strong>
                <span class="small text-muted">Inventory movements & dispatch tracking</span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
