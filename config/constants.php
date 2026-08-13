<?php
/**
 * Dawaam - Local Business Continuity Software
 * Global Application Constants
 */

// Application Info
define('APP_NAME', 'DAWAAM');
define('APP_TAGLINE', 'Local-First Business Continuity System');
define('APP_VERSION', '1.0.0');
define('APP_ORGANIZATION', 'Quetta Business Continuity Network');

// Database Configuration Defaults
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'dawaam_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// System Threshold Defaults
define('DEFAULT_LOW_STOCK_THRESHOLD', 5);
define('DEFAULT_BIG_SALE_THRESHOLD', 50000.00); // PKR 50,000 trigger for urgent notification

// User Roles Slugs
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_PHARMACIST', 'pharmacist');
define('ROLE_SALES_STAFF', 'sales_staff');
define('ROLE_INVENTORY_MANAGER', 'inventory_manager');

// Alert Types
define('ALERT_TYPE_LOW_STOCK', 'low_stock');
define('ALERT_TYPE_BIG_SALE', 'big_sale');
define('ALERT_TYPE_CRITICAL', 'critical_event');

// Sync Statuses
define('SYNC_STATUS_PENDING', 'pending');
define('SYNC_STATUS_SYNCED', 'synced');
define('SYNC_STATUS_FAILED', 'failed');
define('SYNC_STATUS_CONFLICT', 'conflict');

// User Account Statuses
define('USER_STATUS_ACTIVE', 'active');
define('USER_STATUS_INACTIVE', 'inactive');
define('USER_STATUS_SUSPENDED', 'suspended');

// Directory Paths
define('DIR_ROOT', dirname(__DIR__));
define('DIR_CONFIG', DIR_ROOT . '/config');
define('DIR_INCLUDES', DIR_ROOT . '/includes');
define('DIR_UPLOADS', DIR_ROOT . '/uploads');
define('DIR_ASSETS', DIR_ROOT . '/assets');
