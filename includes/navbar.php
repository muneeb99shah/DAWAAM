<?php
/**
 * Dawaam - Local Business Continuity Software
 * Fully Responsive Navigation Bar Component
 */

$user = current_user();
$current_page = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$brand_target = $user ? (BASE_URL . '/admin/index.php') : (BASE_URL . '/index.php');
?>
<nav class="navbar navbar-expand-xl navbar-dark dw-navbar sticky-top">
    <div class="container-fluid px-3 px-xl-4">
        <!-- Brand Logo & Left Hamburger Area -->
        <div class="d-flex align-items-center me-auto me-md-0">
            <?php if ($user): ?>
                <!-- Left Mobile Hamburger Navigation Button (44px Touch Target) -->
                <button id="dw-mobile-hamburger-btn" class="btn btn-link text-white text-decoration-none border-0 p-0 me-2 d-inline-flex align-items-center justify-content-center d-md-none" type="button" aria-label="Toggle Operational Navigation Menu" style="width: 44px; height: 44px;">
                    <i class="bi bi-list fs-2" id="dw-hamburger-icon" style="color: #10b981;"></i>
                </button>
            <?php endif; ?>

            <a class="navbar-brand dw-navbar-brand d-flex align-items-center me-2 me-xl-3" href="<?php echo $brand_target; ?>">
                <i class="bi bi-shield-check text-emerald me-2 fs-3" style="color: #10b981;"></i>
                <span class="fw-bold tracking-tight"><?php echo APP_NAME; ?></span>
                <span class="dw-brand-badge ms-2 d-none d-sm-inline-block">Local Continuity</span>
            </a>
        </div>

        <!-- Mobile/Tablet Toggle Button -->
        <button class="navbar-toggler border-0 shadow-none p-2" type="button" data-bs-toggle="collapse" data-bs-target="#dwNavbar" aria-controls="dwNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Menu Collapse Container -->
        <div class="collapse navbar-collapse" id="dwNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0 py-2 py-xl-0 gap-1 gap-xl-2">
                <?php if (!$user): ?>
                    <!-- Unauthenticated Guest Visitors: Public Landing Navigation -->
                    <li class="nav-item">
                        <a class="nav-link dw-nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php">
                            <span>Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link dw-nav-link <?php echo $current_page === 'about.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/about.php">
                            <span>About</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link dw-nav-link <?php echo $current_page === 'services.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/services.php">
                            <span>Services</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link dw-nav-link <?php echo $current_page === 'contact.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/contact.php">
                            <span>Contact</span>
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Logged-in User Navbar Links Area Left Empty as Requested -->
                <?php endif; ?>
            </ul>

            <div class="d-flex flex-column flex-xl-row align-items-start align-items-xl-center gap-2 gap-xl-3 pt-3 pt-xl-0 border-top border-xl-0 border-secondary border-opacity-25">
                <!-- Local LAN Server IP Badge (Text-Only) -->
                <div class="dw-lan-server-badge text-nowrap">
                    <span class="text-white-50">LAN Server:</span>
                    <strong class="text-white font-monospace ms-1"><?php echo SERVER_LAN_IP; ?>:8000</strong>
                </div>

                <!-- Network Status Badge -->
                <div id="dw-network-status" class="dw-badge-lan text-nowrap">
                    <span class="dw-status-pulse"></span>
                    <span>Local LAN Active</span>
                </div>

                <?php if ($user): ?>
                    <div class="dropdown text-nowrap w-100 w-xl-auto">
                        <button class="btn btn-outline-light dropdown-toggle btn-sm rounded-pill px-3 py-1.5 fw-semibold d-flex align-items-center justify-content-between justify-content-xl-start dw-user-btn" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-inline-flex align-items-center gap-2">
                                <i class="bi bi-person-circle fs-6"></i> 
                                <span class="dw-user-name-text"><?php echo sanitize($user['name']); ?></span>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" aria-labelledby="userMenu">
                            <li><h6 class="dropdown-header text-uppercase text-muted fw-bold small">Logged in as <?php echo sanitize($user['username']); ?></h6></li>
                            <li><span class="dropdown-item-text text-muted small">Code: <code><?php echo sanitize($user['user_code']); ?></code></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item fw-semibold" href="<?php echo BASE_URL; ?>/admin/index.php"><i class="bi bi-speedometer2 me-2 text-primary"></i> Operations Dashboard</a></li>
                            <li><a class="dropdown-item fw-semibold text-danger" href="<?php echo BASE_URL; ?>/admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout Account</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/admin/login.php" class="btn btn-dw-primary btn-sm px-3 py-2 text-nowrap d-inline-flex align-items-center justify-content-center gap-2 w-100 w-xl-auto">
                        <i class="bi bi-lock-fill"></i> <span>Staff Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
