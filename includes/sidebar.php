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
 * Render Enterprise Mobile Navigation Left Drawer & Bottom Bar
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

    // Group items into 5 logical enterprise ERP categories
    $groups = [
        [
            'id' => 'main',
            'name' => 'MAIN',
            'icon' => 'bi-grid-1x2',
            'items' => []
        ],
        [
            'id' => 'ops',
            'name' => 'OPERATIONS',
            'icon' => 'bi-cart-check',
            'items' => []
        ],
        [
            'id' => 'inv',
            'name' => 'INVENTORY & CATALOG',
            'icon' => 'bi-box-seam',
            'items' => []
        ],
        [
            'id' => 'analytics',
            'name' => 'ANALYTICS & SMS',
            'icon' => 'bi-graph-up',
            'items' => []
        ],
        [
            'id' => 'admin',
            'name' => 'SYSTEM ADMINISTRATION',
            'icon' => 'bi-gear',
            'items' => []
        ]
    ];

    foreach ($authorized_links as $link) {
        $match = $link['script_match'];
        if ($match === '/admin/index.php') {
            $groups[0]['items'][] = $link;
        } elseif (in_array($match, ['/admin/sales/create.php', '/admin/sales/index.php'])) {
            $groups[1]['items'][] = $link;
        } elseif (in_array($match, ['/admin/products/', '/admin/inventory/', '/admin/alerts/'])) {
            $groups[2]['items'][] = $link;
        } elseif (in_array($match, ['/admin/reports/', '/admin/messages/', '/admin/sms/'])) {
            $groups[3]['items'][] = $link;
        } else {
            $groups[4]['items'][] = $link;
        }
    }

    // Left Drawer Navigation Container
    $html = '<aside id="dwMobileNavDrawer" class="dw-mobile-drawer d-md-none" aria-label="Mobile Navigation Drawer">';
    
    // Drawer Header
    $html .= '<div class="px-3 py-3 border-bottom bg-dark text-white d-flex align-items-center justify-content-between">';
    $html .= '<div class="d-flex align-items-center gap-2.5">';
    $html .= '<i class="bi bi-shield-check text-emerald" style="color: #10b981; font-size: 1.35rem;"></i>';
    $html .= '<div>';
    $html .= '<h6 class="fw-bold mb-0 text-white" style="font-size: 0.925rem; letter-spacing: -0.01em;">' . APP_NAME . ' Navigation</h6>';
    $html .= '<span class="small text-white-50" style="font-size: 0.72rem;">User Code: ' . sanitize($user['user_code']) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<button type="button" id="dw-mobile-drawer-close-btn" class="btn-close btn-close-white p-1" aria-label="Close Navigation" style="font-size: 0.8rem;"></button>';
    $html .= '</div>';

    // Drawer Body
    $html .= '<div class="p-3 bg-white" id="dwMobileDrawerBody" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">';
    
    // Search Box
    $html .= '<div class="mb-3">';
    $html .= '<div class="input-group input-group-sm">';
    $html .= '<span class="input-group-text bg-light border-end-0 py-1.5"><i class="bi bi-search text-muted" style="font-size: 0.85rem;"></i></span>';
    $html .= '<input type="text" id="dw-mobile-search-input" class="form-control form-control-sm border-start-0 py-1.5" placeholder="Search modules..." autocomplete="off" style="font-size: 0.85rem;">';
    $html .= '</div>';
    $html .= '</div>';

    // Expandable Group Accordion Stack
    $html .= '<div id="dwMobileNavList">';
    foreach ($groups as $group) {
        if (empty($group['items'])) continue;

        // Check if group contains the active script
        $group_has_active = false;
        foreach ($group['items'] as $item) {
            if (strpos($current_script, $item['script_match']) !== false) {
                $group_has_active = true;
                break;
            }
        }

        // Main & Active groups start expanded
        $is_open = $group_has_active || ($group['id'] === 'main') || ($group['id'] === 'ops' && !has_active_in_any($groups, $current_script));
        $body_class = $is_open ? 'dw-group-body show' : 'dw-group-body collapsed d-none';
        $chevron_class = $is_open ? 'bi bi-chevron-down dw-group-chevron text-success' : 'bi bi-chevron-right dw-group-chevron text-muted';

        $html .= '<div class="dw-mobile-group-wrap mb-2">';
        $html .= '<button type="button" class="dw-mobile-group-header btn btn-light btn-sm w-100 d-flex align-items-center justify-content-between text-start py-2 px-3 border-0 rounded-2" data-group-target="#dw-group-' . $group['id'] . '" style="background: #f8fafc;">';
        $html .= '<span class="d-inline-flex align-items-center gap-2.5 fw-bold text-uppercase" style="font-size: 0.725rem; color: #475569; letter-spacing: 0.05em;">';
        $html .= '<i class="bi ' . $group['icon'] . ' text-muted" style="font-size: 0.925rem;"></i>';
        $html .= '<span>' . htmlspecialchars($group['name']) . '</span>';
        $html .= '</span>';
        $html .= '<i class="' . $chevron_class . '" style="font-size: 0.75rem;"></i>';
        $html .= '</button>';

        $html .= '<div id="dw-group-' . $group['id'] . '" class="' . $body_class . ' ps-1 pe-1 pt-1.5">';
        $html .= '<div class="list-group list-group-flush border-0">';

        foreach ($group['items'] as $link) {
            $is_active = (strpos($current_script, $link['script_match']) !== false);
            $active_class = $is_active ? ' active' : '';

            $html .= '<a href="' . htmlspecialchars($link['url']) . '" class="list-group-item list-group-item-action py-2.5 px-3 mb-1 border-0 rounded-2' . $active_class . '" data-mobile-title="' . htmlspecialchars($link['title']) . '" style="min-height: 44px; display: flex; align-items: center; justify-content: space-between;">';
            $html .= '<div class="d-flex align-items-center me-2" style="min-width: 0;">';
            $html .= '<i class="bi ' . htmlspecialchars($link['icon']) . ' flex-shrink-0" style="color: ' . htmlspecialchars($link['color']) . '; font-size: 1.15rem; width: 24px; text-align: center; margin-right: 0.75rem;"></i>';
            $html .= '<span class="text-truncate" style="font-size: 0.9rem; font-weight: ' . ($is_active ? '600' : '500') . ';">' . htmlspecialchars($link['title']) . '</span>';
            $html .= '</div>';
            $html .= '<i class="bi bi-chevron-right text-muted flex-shrink-0" style="font-size: 0.75rem;"></i>';
            $html .= '</a>';
        }

        $html .= '</div>'; // End list-group
        $html .= '</div>'; // End group body
        $html .= '</div>'; // End group wrap
    }
    $html .= '</div>'; // End dwMobileNavList

    $html .= '</div>'; // End body

    // Drawer Footer
    $html .= '<div class="p-3 border-top bg-light mt-auto">';
    $html .= '<a href="' . BASE_URL . '/admin/logout.php" class="btn btn-outline-danger btn-sm w-100 py-2 font-monospace fw-semibold" style="font-size: 0.825rem;"><i class="bi bi-box-arrow-right me-1"></i> Logout Account</a>';
    $html .= '</div>';

    $html .= '</aside>';

    // Drawer Backdrop Overlay
    $html .= '<div id="dwMobileNavBackdrop" class="dw-mobile-drawer-backdrop d-md-none"></div>';

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

    $html .= '<button type="button" id="dw-bottom-nav-menu-btn" class="btn btn-link text-decoration-none text-center px-2 py-1 text-muted border-0" style="font-size: 0.68rem;">';
    $html .= '<i class="bi bi-list d-block fs-5 mb-0 text-primary"></i>';
    $html .= '<span class="text-dark fw-semibold">Menu</span>';
    $html .= '</button>';

    $html .= '</div>';

    return $html;
}

/**
 * Helper to check if current script is active in any group
 */
function has_active_in_any($groups, $current_script) {
    foreach ($groups as $g) {
        foreach ($g['items'] as $item) {
            if (strpos($current_script, $item['script_match']) !== false) {
                return true;
            }
        }
    }
    return false;
}
