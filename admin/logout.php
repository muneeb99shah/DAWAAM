<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin Panel - Staff Logout Processor
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
set_flash_message('success', 'You have been successfully logged out of the system.');
redirect('admin/login.php');
