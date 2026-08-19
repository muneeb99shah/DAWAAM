<?php
/**
 * Dawaam - Local Business Continuity Software
 * Permission-Based Desktop Sidebar & Mobile Offcanvas Navigation Components
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

/**
 * Get All Authorized Operational Module Navigation Items
 *
 * @return array
 */
function get_operational_nav_items() {
    $nav_items = [
        [
            'title' => 'Dashboard',
            'url' => BASE_URL . '/admin/index.php',
            'script_match' => '/admin/index.php',
            'icon' => 'bi-grid-1x2-fill',
            'color' => '#0284c7',
            'permission' => true
        ],
        [
            'title' => 'Sales POS',
            'url' => BASE_URL . '/admin/sales/create.php',
            'script_match' => '/admin/sales/create.php',
            'icon' => 'bi-cart-check-fill',
            'color' => '#059669',
            'permission' => has_permission('sales.create') || has_permission('pos.view')
        ],
        [
            'title' => 'Sales Receipts',
            'url' => BASE_URL . '/admin/sales/index.php',
            'script_match' => '/admin/sales/index.php',
            'icon' => 'bi-receipt-cutoff',
            'color' => '#2563eb',
            'permission' => has_permission('sales.view')
        ],
        [
            'title' => 'Products Catalog',
            'url' => BASE_URL . '/admin/products/index.php',
            'script_match' => '/admin/products/',
            'icon' => 'bi-capsule',
            'color' => '#8b5cf6',
            'permission' => has_permission('products.view')
        ],
        [
            'title' => 'Stock Monitor',
            'url' => BASE_URL . '/admin/inventory/index.php',
            'script_match' => '/admin/inventory/',
            'icon' => 'bi-box-seam-fill',
            'color' => '#d97706',
            'permission' => has_permission('inventory.view') || has_permission('inventory.adjust')
        ],
        [
            'title' => 'Urgent Alerts',
            'url' => BASE_URL . '/admin/alerts/index.php',
            'script_match' => '/admin/alerts/',
            'icon' => 'bi-bell-fill',
            'color' => '#dc2626',
            'permission' => has_permission('alerts.view') || has_permission('alerts.manage')
        ],
        [
            'title' => 'Reports',
            'url' => BASE_URL . '/admin/reports/sales.php',
            'script_match' => '/admin/reports/',
            'icon' => 'bi-graph-up-arrow',
            'color' => '#10b981',
            'permission' => has_permission('reports.view')
        ],
        [
            'title' => 'Messages',
            'url' => BASE_URL . '/admin/messages/index.php',
            'script_match' => '/admin/messages/',
            'icon' => 'bi-envelope-fill',
            'color' => '#06b6d4',
            'permission' => has_permission('messages.view')
        ],
        [
            'title' => 'Cloud Sync',
            'url' => BASE_URL . '/admin/sync/index.php',
            'script_match' => '/admin/sync/',
            'icon' => 'bi-cloud-arrow-up-fill',
            'color' => '#4f46e5',
            'permission' => has_permission('sync.view') || has_permission('sync.manage')
        ],
        [
            'title' => 'Network Hub',
            'url' => BASE_URL . '/admin/network/index.php',
            'script_match' => '/admin/network/',
            'icon' => 'bi-router-fill',
            'color' => '#0284c7',
            'permission' => has_permission('network.view')
        ],
        [
            'title' => 'SMS Gateway',
            'url' => BASE_URL . '/admin/sms/index.php',
            'script_match' => '/admin/sms/',
            'icon' => 'bi-chat-text-fill',
            'color' => '#eab308',
            'permission' => has_permission('sms.manage')
        ],
        [
            'title' => 'User Directory',
            'url' => BASE_URL . '/admin/users/index.php',
            'script_match' => '/admin/users/index.php',
            'icon' => 'bi-people-fill',
            'color' => '#3b82f6',
            'permission' => has_permission('users.view') || has_permission('users.manage')
        ],
        [
            'title' => 'Permission Matrix',
            'url' => BASE_URL . '/admin/users/permissions.php',
            'script_match' => '/admin/users/permissions.php',
            'icon' => 'bi-sliders',
            'color' => '#64748b',
            'permission' => has_permission('permissions.manage') || has_role('super_admin')
        ]
    ];

    $authorized = [];
    foreach ($nav_items as $item) {
        if ($item['permission']) {
            $authorized[] = $item;
        }
    }

    return $authorized;
}

/**
 * Render Quick Access Desktop Sidebar
 *
 * @return string HTML
 */
function render_quick_access_sidebar() {
    if (!is_logged_in()) {
        return '';
    }

    $authorized_links = get_operational_nav_items();
    if (empty($authorized_links)) {
        return '';
    }

    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';

    $html = '<aside id="dw-main-sidebar" class="dw-sidebar dw-sidebar-collapsed" aria-label="Operational Navigation">';
    
    // Toggle Control Header
    $html .= '<div class="dw-sidebar-header">';
    $html .= '<button type="button" id="dw-sidebar-toggle-btn" class="dw-sidebar-toggle-btn" aria-label="Toggle Sidebar Navigation" title="Expand Sidebar Navigation">';
    $html .= '<i class="bi bi-chevron-double-right dw-toggle-icon"></i>';
    $html .= '</button>';
    $html .= '</div>';

    // Quick Search Input (Visible in Expanded Mode)
    $html .= '<div class="dw-sidebar-search-wrap">';
    $html .= '<div class="input-group input-group-sm">';
    $html .= '<span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>';
    $html .= '<input type="text" id="dw-sidebar-search-input" class="form-control form-control-sm border-start-0" placeholder="Search Menu..." autocomplete="off">';
    $html .= '</div>';
    $html .= '</div>';

    // Navigation Links Stack
    $html .= '<div class="dw-sidebar-nav">';
    foreach ($authorized_links as $link) {
        $is_active = (strpos($current_script, $link['script_match']) !== false);
        $active_class = $is_active ? ' active' : '';

        $html .= '<a href="' . htmlspecialchars($link['url']) . '" class="dw-sidebar-link' . $active_class . '" data-title="' . htmlspecialchars($link['title']) . '">';
        $html .= '<i class="bi ' . htmlspecialchars($link['icon']) . ' dw-sidebar-icon" style="color: ' . htmlspecialchars($link['color']) . ';"></i>';
        $html .= '<span class="dw-sidebar-label">' . htmlspecialchars($link['title']) . '</span>';
        $html .= '<i class="bi bi-chevron-right dw-sidebar-arrow"></i>';
        $html .= '<span class="dw-sidebar-tooltip">' . htmlspecialchars($link['title']) . '</span>';
        $html .= '</a>';
    }
    $html .= '</div>';
    $html .= '</aside>';

    return $html;
}

/**
 * Render Mobile Offcanvas Navigation Drawer & Bottom Bar
 *
 * @return string HTML
 */
function render_mobile_navigation_drawer() {
    if (!is_logged_in()) {
        return '';
    }

    $authorized_links = get_operational_nav_items();
    if (empty($authorized_links)) {
        return '';
    }

    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $user = current_user();

    $html = '<div class="offcanvas offcanvas-start dw-mobile-offcanvas" tabindex="-1" id="dwMobileNav" aria-labelledby="dwMobileNavLabel">';
    
    // Header
    $html .= '<div class="offcanvas-header border-bottom py-3 bg-dark text-white">';
    $html .= '<div class="d-flex align-items-center gap-2">';
    $html .= '<i class="bi bi-shield-check fs-4 text-emerald" style="color: #10b981;"></i>';
    $html .= '<div>';
    $html .= '<h6 class="offcanvas-title fw-bold mb-0 text-white" id="dwMobileNavLabel">' . APP_NAME . ' Navigation</h6>';
    $html .= '<span class="small text-white-50" style="font-size: 0.72rem;">User: ' . sanitize($user['user_code']) . ' (' . sanitize($user['name']) . ')</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>';
    $html .= '</div>';

    // Body
    $html .= '<div class="offcanvas-body p-3 bg-white">';
    
    // Live Search Box
    $html .= '<div class="mb-3">';
    $html .= '<div class="input-group input-group-sm">';
    $html .= '<span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>';
    $html .= '<input type="text" id="dw-mobile-search-input" class="form-control form-control-sm border-start-0" placeholder="Search modules..." autocomplete="off">';
    $html .= '</div>';
    $html .= '</div>';

    // Item List
    $html .= '<div class="list-group list-group-flush border-0" id="dwMobileNavList">';
    foreach ($authorized_links as $link) {
        $is_active = (strpos($current_script, $link['script_match']) !== false);
        $active_class = $is_active ? ' active border-start border-4 border-success bg-success bg-opacity-10 fw-bold' : '';

        $html .= '<a href="' . htmlspecialchars($link['url']) . '" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 border-0 rounded-3 mb-1' . $active_class . '" data-mobile-title="' . htmlspecialchars($link['title']) . '">';
        $html .= '<div class="d-flex align-items-center gap-3">';
        $html .= '<i class="bi ' . htmlspecialchars($link['icon']) . ' fs-5" style="color: ' . htmlspecialchars($link['color']) . ';"></i>';
        $html .= '<span class="text-dark fs-6" style="font-size: 0.9rem;">' . htmlspecialchars($link['title']) . '</span>';
        $html .= '</div>';
        $html .= '<i class="bi bi-chevron-right text-muted small"></i>';
        $html .= '</a>';
    }
    $html .= '</div>';

    $html .= '</div>'; // End body

    // Footer
    $html .= '<div class="offcanvas-footer p-3 border-top bg-light">';
    $html .= '<a href="' . BASE_URL . '/admin/logout.php" class="btn btn-outline-danger btn-sm w-100 py-2 font-monospace fw-semibold"><i class="bi bi-box-arrow-right me-1"></i> Logout Account</a>';
    $html .= '</div>';

    $html .= '</div>'; // End offcanvas

    // Fixed Bottom Mobile Navigation Bar
    $html .= '<div class="dw-mobile-bottom-bar d-flex d-md-none justify-content-around align-items-center bg-white border-top shadow-lg py-1 px-2 fixed-bottom" style="z-index: 1030; height: 56px;">';
    
    $bottom_links = [
        ['title' => 'Dashboard', 'url' => BASE_URL . '/admin/index.php', 'icon' => 'bi-speedometer2', 'script' => '/admin/index.php'],
        ['title' => 'POS', 'url' => BASE_URL . '/admin/sales/create.php', 'icon' => 'bi-cart-check', 'script' => '/admin/sales/create.php', 'perm' => (has_permission('sales.create') || has_permission('pos.view'))],
        ['title' => 'Stock', 'url' => BASE_URL . '/admin/inventory/index.php', 'icon' => 'bi-box-seam', 'script' => '/admin/inventory/', 'perm' => (has_permission('inventory.view') || has_permission('inventory.adjust'))],
        ['title' => 'Receipts', 'url' => BASE_URL . '/admin/sales/index.php', 'icon' => 'bi-receipt', 'script' => '/admin/sales/index.php', 'perm' => has_permission('sales.view')],
    ];

    foreach ($bottom_links as $b) {
        if (isset($b['perm']) && !$b['perm']) continue;
        $is_act = (strpos($current_script, $b['script']) !== false);
        $cls = $is_act ? ' text-success fw-bold' : ' text-muted';
        $html .= '<a href="' . htmlspecialchars($b['url']) . '" class="text-decoration-none text-center px-2 py-1' . $cls . '" style="font-size: 0.68rem;">';
        $html .= '<i class="bi ' . htmlspecialchars($b['icon']) . ' d-block fs-5 mb-0"></i>';
        $html .= '<span>' . htmlspecialchars($b['title']) . '</span>';
        $html .= '</a>';
    }

    $html .= '<button type="button" class="btn btn-link text-decoration-none text-center px-2 py-1 text-muted border-0" data-bs-toggle="offcanvas" data-bs-target="#dwMobileNav" aria-controls="dwMobileNav" style="font-size: 0.68rem;">';
    $html .= '<i class="bi bi-list d-block fs-5 mb-0 text-primary"></i>';
    $html .= '<span class="text-dark fw-semibold">Menu</span>';
    $html .= '</button>';

    $html .= '</div>';

    return $html;
}
