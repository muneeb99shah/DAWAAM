<?php
/**
 * Dawaam - Local Business Continuity Software
 * Shared HTML Header Template
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo isset($page_title) ? sanitize($page_title) . ' - ' . APP_NAME : APP_NAME . ' | ' . APP_TAGLINE; ?></title>
    
    <!-- Local Offline CSS Assets (Zero Internet CDN Dependencies) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<?php 
$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$is_admin_route = (strpos($script_path, '/admin/') !== false);
$show_operational_layout = is_logged_in() && $is_admin_route;
?>
<main class="py-4 flex-grow-1">
    <div class="<?php echo $show_operational_layout ? 'container-fluid px-3 px-xl-4' : 'container'; ?>">
        <?php display_flash_messages(); ?>

        <?php if ($show_operational_layout): ?>
            <div class="d-flex gap-3 align-items-start">
                <div class="flex-shrink-0 d-none d-md-block">
                    <?php echo render_quick_access_sidebar(); ?>
                </div>
                <div class="flex-grow-1 min-w-0">
        <?php endif; ?>
