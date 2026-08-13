<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - LAN Status & Network Operations Hub Route Endpoint
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

require_permission('network.view');

// Forward directly to Network Operations Hub
require_once __DIR__ . '/network/index.php';
