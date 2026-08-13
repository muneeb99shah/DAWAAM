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
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
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
