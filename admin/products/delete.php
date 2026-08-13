<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Delete Product Handler
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('products.delete');

$token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    set_flash_message('danger', 'Security token verification failed.');
    redirect('admin/products/index.php');
}

$product_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($product_id <= 0) {
    set_flash_message('danger', 'Invalid product specified for deletion.');
    redirect('admin/products/index.php');
}

$pdo = get_db_connection();

// Check if product exists
$stmt = $pdo->prepare("SELECT name FROM products WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $product_id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash_message('danger', 'Product not found in database.');
    redirect('admin/products/index.php');
}

// Check if product has sales transactions
$stmt_sales = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE product_id = :id");
$stmt_sales->execute([':id' => $product_id]);
$sales_count = (int)$stmt_sales->fetchColumn();

if ($sales_count > 0) {
    set_flash_message('danger', "Cannot delete '{$product['name']}' because {$sales_count} sale transactions exist for this item. Historical business records are preserved for continuity.");
    redirect('admin/products/index.php');
}

try {
    $stmt_del = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt_del->execute([':id' => $product_id]);

    queue_sync_record('products', $product_id, 'DELETE');
    log_audit_action('DELETE_PRODUCT', 'products', $product_id, "Deleted product '{$product['name']}'");

    set_flash_message('success', "Product '{$product['name']}' deleted successfully.");
} catch (Exception $e) {
    error_log('Delete Product Error: ' . $e->getMessage());
    set_flash_message('danger', "Failed to delete product: " . $e->getMessage());
}

redirect('admin/products/index.php');
