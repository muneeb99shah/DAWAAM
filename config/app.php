<?php
/**
 * Dawaam - Local Business Continuity Software
 * Application Runtime Configuration
 */

require_once __DIR__ . '/constants.php';
require_once DIR_INCLUDES . '/pagination.php';

// Timezone Setup
date_default_timezone_set('Asia/Karachi');

// Session Setup
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

/**
 * Detect Server Host / Local IP Address for LAN Access
 */
function get_server_lan_ip() {
    $local_ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
    if ($local_ip === '127.0.0.1' || $local_ip === '::1') {
        $local_ip = gethostbyname(gethostname());
    }
    return $local_ip;
}

/**
 * Get Base URL of the Application
 */
function get_base_url() {
    if (defined('BASE_URL')) {
        return BASE_URL;
    }
    
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
        
    $protocol = $is_https ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $script_dir = str_replace('\\', '/', dirname($script_name));
    
    // Clean up relative path markers
    $base_path = preg_replace('#/(public|admin|api|cloud).*$#i', '', $script_dir);
    $base_path = rtrim(str_replace('.', '', $base_path), '/');
    
    return $protocol . $host . ($base_path ? '/' . ltrim($base_path, '/') : '');
}

define('BASE_URL', get_base_url());
define('SERVER_LAN_IP', get_server_lan_ip());

/**
 * Get Configurable Business Profile for International & Local Document Generation
 * Supports Pakistan, USA, UK, Europe, Middle East & Custom Regions
 */
function get_business_profile() {
    return [
        'name' => 'DAWAAAM Solutions',
        'tagline' => 'Enterprise Medical & Business Continuity Center',
        'logo_icon' => 'bi-shield-check',
        'address_line1' => 'MA Jinnah Road, Quetta',
        'address_line2' => 'Balochistan, Pakistan',
        'city' => 'Quetta',
        'state' => 'Balochistan',
        'country' => 'Pakistan',
        'postal_code' => '87300',
        'phone' => '+92 (81) 283-9102',
        'email' => 'support@dawaam.local',
        'website' => 'www.dawaam.local',
        
        // International Currency Configuration
        'currency_code' => 'PKR',
        'currency_symbol' => 'PKR ',
        
        // International & Country-Specific Tax & Business Registration
        'tax_label' => 'Sales Tax', // 'Sales Tax', 'GST', 'VAT', etc.
        'tax_rate_percent' => 0.0,
        
        'tax_id_label_1' => 'NTN', // 'NTN' (PK), 'EIN / Tax ID' (US), 'VAT Reg No' (UK/EU)
        'tax_id_val_1' => '8492041-7',
        
        'tax_id_label_2' => 'STRN', // 'STRN' (PK), 'Company Reg No' (UK), etc.
        'tax_id_val_2' => '3277876123490',
        
        'business_reg_label' => 'Reg No',
        'business_reg_val' => 'BAL-QTA-2024-8891',
        
        // Document Footer Settings
        'footer_return_policy' => 'Items can be returned or exchanged within 7 days of purchase with original receipt.',
        'footer_warranty_info' => 'Hardware components are covered by 1-year local operational warranty.',
        'footer_support_contact' => 'Customer Support: support@dawaam.local | Helpdesk: +92 (81) 283-9102',
        'footer_thank_you' => 'Thank you for choosing Dawaam Business Continuity System!',
        'enable_qr_code' => true
    ];
}
