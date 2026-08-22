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
        <div class="row gy-4 align-items-start">
            <div class="col-lg-6 col-md-6">
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

            <div class="col-lg-5 col-md-6 offset-lg-1">
                <h6 class="text-white fw-bold mb-3 d-flex align-items-center">
                    <i class="bi bi-cpu text-success me-2 fs-5"></i> System Architecture & Resilience
                </h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2.5 d-flex align-items-center"><i class="bi bi-wifi text-success me-2 fs-6"></i> <span>Local LAN &amp; Wi-Fi Grid Network</span></li>
                    <li class="mb-2.5 d-flex align-items-center"><i class="bi bi-phone-vibrate text-warning me-2 fs-6"></i> <span>Android SMS Emergency Gateway</span></li>
                    <li class="mb-2.5 d-flex align-items-center"><i class="bi bi-cloud-arrow-up text-info me-2 fs-6"></i> <span>WAN Recovery Sync Engine</span></li>
                    <li class="mb-2.5 d-flex align-items-center"><i class="bi bi-shield-lock text-primary me-2 fs-6"></i> <span>Enterprise Security &amp; Access Controls</span></li>
                </ul>
            </div>
        </div>

        <hr class="border-secondary border-opacity-50 my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-white-50">
            <div>
                &copy; <?php echo date('Y'); ?> <strong class="text-white"><?php echo APP_NAME; ?></strong>.
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
<script src="<?php echo BASE_URL; ?>/assets/js/bootstrap.bundle.min.js?v=1.0.6"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/app.js?v=1.0.6"></script>
</body>
</html>
