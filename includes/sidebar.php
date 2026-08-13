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

    // Define All Operational Module Navigation Items & Permission Keys
    $nav_items = [
        [
            'title' => 'Dashboard',
            'url' => BASE_URL . '/admin/index.php',
            'script_match' => '/admin/index.php',
            'icon' => 'bi-grid-1x2-fill',
            'permission' => true // Always available for authenticated operational users
        ],
        [
            'title' => 'Sales POS',
            'url' => BASE_URL . '/admin/sales/create.php',
            'script_match' => '/admin/sales/create.php',
            'icon' => 'bi-cart-check-fill',
            'permission' => has_permission('sales.create') || has_permission('pos.view')
        ],
        [
            'title' => 'Sales Receipts',
            'url' => BASE_URL . '/admin/sales/index.php',
            'script_match' => '/admin/sales/index.php',
            'icon' => 'bi-receipt-cutoff',
            'permission' => has_permission('sales.view')
        ],
        [
            'title' => 'Products Catalog',
            'url' => BASE_URL . '/admin/products/index.php',
            'script_match' => '/admin/products/',
            'icon' => 'bi-capsule',
            'permission' => has_permission('products.view')
        ],
        [
            'title' => 'Stock Monitor',
            'url' => BASE_URL . '/admin/inventory/index.php',
            'script_match' => '/admin/inventory/',
            'icon' => 'bi-box-seam-fill',
            'permission' => has_permission('inventory.view') || has_permission('inventory.adjust')
        ],
        [
            'title' => 'Urgent Alerts',
            'url' => BASE_URL . '/admin/alerts/index.php',
            'script_match' => '/admin/alerts/',
            'icon' => 'bi-bell-fill',
            'permission' => has_permission('alerts.view') || has_permission('alerts.manage')
        ],
        [
            'title' => 'Reports',
            'url' => BASE_URL . '/admin/reports/sales.php',
            'script_match' => '/admin/reports/',
            'icon' => 'bi-graph-up-arrow',
            'permission' => has_permission('reports.view')
        ],
        [
            'title' => 'Messages',
            'url' => BASE_URL . '/admin/messages/index.php',
            'script_match' => '/admin/messages/',
            'icon' => 'bi-envelope-fill',
            'permission' => has_permission('messages.view')
        ],
        [
            'title' => 'Cloud Sync',
            'url' => BASE_URL . '/admin/sync/index.php',
            'script_match' => '/admin/sync/',
            'icon' => 'bi-cloud-arrow-up-fill',
            'permission' => has_permission('sync.view') || has_permission('sync.manage')
        ],
        [
            'title' => 'Network Hub',
            'url' => BASE_URL . '/admin/network/index.php',
            'script_match' => '/admin/network/',
            'icon' => 'bi-router-fill',
            'permission' => has_permission('network.view')
        ],
        [
            'title' => 'SMS Gateway',
            'url' => BASE_URL . '/admin/sms/index.php',
            'script_match' => '/admin/sms/',
            'icon' => 'bi-chat-text-fill',
            'permission' => has_permission('sms.manage')
        ],
        [
            'title' => 'User Directory',
            'url' => BASE_URL . '/admin/users/index.php',
            'script_match' => '/admin/users/index.php',
            'icon' => 'bi-people-fill',
            'permission' => has_permission('users.view') || has_permission('users.manage')
        ],
        [
            'title' => 'Permission Matrix',
            'url' => BASE_URL . '/admin/users/permissions.php',
            'script_match' => '/admin/users/permissions.php',
            'icon' => 'bi-sliders',
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

    $html = '<aside class="dw-quick-sidebar-compact" aria-label="Operational Quick Access Navigation">';
    foreach ($authorized_links as $link) {
        $is_active = (strpos($current_script, $link['script_match']) !== false);
        $active_class = $is_active ? ' active' : '';

        $html .= '<a href="' . htmlspecialchars($link['url']) . '" class="dw-sidebar-compact-link' . $active_class . '" aria-label="' . htmlspecialchars($link['title']) . '">';
        $html .= '<i class="bi ' . htmlspecialchars($link['icon']) . '"></i>';
        $html .= '<span class="dw-tooltip-label">' . htmlspecialchars($link['title']) . '</span>';
        $html .= '</a>';
    }
    $html .= '</aside>';

    return $html;
}
