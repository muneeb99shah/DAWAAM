<?php
/**
 * Dawaam - Local Business Continuity Software
 * Shared HTML Footer Template
 */
$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$is_admin_route = (strpos($script_path, '/admin/') !== false);
$show_operational_layout = is_logged_in() && $is_admin_route;
?>

<?php if ($show_operational_layout): ?>
        </div>
    </div>
<?php endif; ?>
    </div>
</main>

<footer class="dw-footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-5 col-md-6">
                <h5 class="text-white fw-bold mb-3 d-flex align-items-center">
                    <i class="bi bi-shield-check text-success me-2 fs-4"></i> <?php echo APP_NAME; ?>
                </h5>
                <p class="small text-white-50 mb-3" style="line-height: 1.6;">
                    <strong class="text-white">Your business doesn't stop when the internet does.</strong><br>
                    Designed specifically for local business resilience in Quetta, Balochistan. Ensures 100% operational uptime for sales, stock management, and urgent SMS notifications during cellular and WAN blackouts.
                </p>
                <div class="small fw-semibold text-light">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> Quetta, Balochistan, Pakistan
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3">System Architecture</h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><i class="bi bi-wifi text-success me-2"></i> Local LAN / Wi-Fi Grid</li>
                    <li class="mb-2"><i class="bi bi-phone-vibrate text-warning me-2"></i> Android SMS Emergency Gateway</li>
                    <li class="mb-2"><i class="bi bi-cloud-arrow-up text-info me-2"></i> WAN Recovery Sync Engine</li>
                    <li class="mb-2"><i class="bi bi-shield-lock text-primary me-2"></i> PDO Prepared Statements & RBAC</li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-12">
                <h6 class="text-white fw-bold mb-3">Local Server Health Status</h6>
                <div class="dw-footer-box">
                    <div class="status-row">
                        <span class="text-white-50 small">Local Server IP:</span>
                        <span class="text-white font-monospace fw-bold"><?php echo SERVER_LAN_IP; ?></span>
                    </div>
                    <div class="status-row">
                        <span class="text-white-50 small">HTTP Port:</span>
                        <span class="text-white font-monospace fw-bold">8000</span>
                    </div>
                    <div class="status-row">
                        <span class="text-white-50 small">Database Engine:</span>
                        <span class="text-success font-monospace fw-bold">MySQL (dawaam_db)</span>
                    </div>
                </div>
            </div>
        </div>

        <hr class="border-secondary border-opacity-50 my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-white-50">
            <div>
                &copy; <?php echo date('Y'); ?> <strong class="text-white"><?php echo APP_NAME; ?></strong>. Final Year Business Continuity System.
            </div>
            <div class="mt-2 mt-md-0">
                <span class="badge bg-secondary me-2">Version <?php echo APP_VERSION; ?></span>
                <span class="badge bg-success">Local-First Engine Active</span>
            </div>
        </div>
    </div>
</footer>

<?php 
if (function_exists('render_mobile_navigation_drawer')) {
    echo render_mobile_navigation_drawer();
}
?>

<!-- Local Offline JS Assets (Zero Internet CDN Dependencies) -->
<script src="<?php echo BASE_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
</body>
</html>
