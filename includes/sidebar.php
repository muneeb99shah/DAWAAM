<?php
/**
 * Dawaam - Local Business Continuity Software
 * Permission-Based Quick Access Sidebar Component
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

/**
 * Render Quick Access Sidebar for Authenticated Operational Users
 *
 * @return string HTML
 */
function render_quick_access_sidebar() {
    if (!is_logged_in()) {
        return ''; // Public landing pages show ZERO operational sidebar
    }

    $user = current_user();
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';

    // Define All Operational Module Navigation Items, Permission Keys, and Icon Colors
    $nav_items = [
        [
            'title' => 'Dashboard',
            'url' => BASE_URL . '/admin/index.php',
            'script_match' => '/admin/index.php',
            'icon' => 'bi-grid-1x2-fill',
            'color' => '#0284c7',
            'permission' => true // Always available for authenticated operational users
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

    // Filter permitted navigation links
    $authorized_links = [];
    foreach ($nav_items as $item) {
        if ($item['permission']) {
            $authorized_links[] = $item;
        }
    }

    if (empty($authorized_links)) {
        return '';
    }

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
