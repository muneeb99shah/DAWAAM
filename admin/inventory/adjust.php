<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Inventory Manager Stock Adjustment Interface
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alerts.php';

require_permission('inventory.adjust');

$pdo = get_db_connection();

// Pre-select product if passed via GET
$selected_product_id = (int)($_GET['product_id'] ?? 0);

// Fetch all active products for dropdown selection
$products = $pdo->query("SELECT id, name, sku, price, stock_qty, low_stock_threshold FROM products ORDER BY name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token check failed.';
    }

    $product_id = (int)($_POST['product_id'] ?? 0);
    $adj_type = $_POST['adj_type'] ?? 'add';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($product_id <= 0) $errors[] = 'Please select a valid product.';
    if ($quantity <= 0 && $adj_type !== 'set') $errors[] = 'Adjustment quantity must be greater than zero.';
    if (!in_array($adj_type, ['add', 'remove', 'set'], true)) $errors[] = 'Invalid adjustment type selected.';

    // Fetch target product
    if (empty($errors)) {
        $stmt_p = $pdo->prepare("SELECT id, name, sku, stock_qty, low_stock_threshold FROM products WHERE id = :id LIMIT 1");
        $stmt_p->execute([':id' => $product_id]);
        $target_product = $stmt_p->fetch();

        if (!$target_product) {
            $errors[] = 'Selected product not found in catalog.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $old_stock = (int)$target_product['stock_qty'];
            $new_stock = $old_stock;

            if ($adj_type === 'add') {
                $new_stock = $old_stock + $quantity;
            } elseif ($adj_type === 'remove') {
                $new_stock = max(0, $old_stock - $quantity);
            } elseif ($adj_type === 'set') {
                $new_stock = max(0, $quantity);
            }

            // Update Product Stock
            $stmt_u = $pdo->prepare("UPDATE products SET stock_qty = :new_stock, updated_at = NOW() WHERE id = :id");
            $stmt_u->execute([':new_stock' => $new_stock, ':id' => $product_id]);

            // Queue for Cloud WAN Sync
            log_data_change('products', $product_id, 'UPDATE', [
                'id' => $product_id,
                'name' => $target_product['name'],
                'stock_qty' => $new_stock,
                'previous_stock' => $old_stock,
                'adjustment_type' => $adj_type,
                'notes' => $notes
            ]);

            // Log Audit Entry
            $audit_msg = "Stock adjustment for '{$target_product['name']}' ({$target_product['sku']}): {$old_stock} -> {$new_stock} ({$adj_type}). Notes: {$notes}";
            log_audit_action('STOCK_ADJUSTMENT', 'inventory', $product_id, $audit_msg);

            // Trigger Alert if below threshold
            if ($new_stock <= (int)$target_product['low_stock_threshold']) {
                trigger_urgent_alert('LOW_STOCK', "Low Stock Alert: '{$target_product['name']}' stock adjusted to {$new_stock} units (Threshold: {$target_product['low_stock_threshold']}).", [
                    'product_id' => $product_id,
                    'stock_qty' => $new_stock,
                    'threshold' => $target_product['low_stock_threshold']
                ]);
            }

            $pdo->commit();

            set_flash_message('success', "Stock quantity for '{$target_product['name']}' updated cleanly ({$old_stock} -> {$new_stock} units).");
            redirect('admin/products/index.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Stock Adjustment Error: ' . $e->getMessage());
            $errors[] = 'Failed to execute stock adjustment: ' . $e->getMessage();
        }
    }
}

$page_title = "Manual Stock Adjustment";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-box-seam text-primary me-2"></i> Inventory Stock Adjustment Manager
        </h2>
        <p class="text-muted small mb-0">Record received supplier shipments, adjust damaged/expired stock, or update audit counts.</p>
    </div>
    <div>
        <a href="<?php echo BASE_URL; ?>/admin/products/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Product Catalog
        </a>
    </div>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?php echo sanitize($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="dw-card p-4 p-md-5 bg-white">
            <form action="adjust.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Select Pharmacy Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select form-select-lg" required>
                            <option value="">-- Choose Item from Inventory --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $selected_product_id === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($p['name']); ?> (SKU: <?php echo sanitize($p['sku']); ?>) - Current Stock: <?php echo number_format($p['stock_qty']); ?> units
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Adjustment Action Type <span class="text-danger">*</span></label>
                        <select name="adj_type" class="form-select" required>
                            <option value="add" selected>Stock Received (+) [Supplier Shipment]</option>
                            <option value="remove">Stock Removed (-) [Damaged / Expired / Lost]</option>
                            <option value="set">Set Exact Count (=) [Physical Audit Correction]</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Adjustment Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control font-monospace" name="quantity" min="1" placeholder="e.g. 25" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Reason / Audit Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Enter reason (e.g. Received shipment invoice #4820, damaged medicine disposed...)" required></textarea>
                    </div>

                    <div class="col-12 pt-3 border-top mt-4 d-flex justify-content-end gap-2">
                        <a href="<?php echo BASE_URL; ?>/admin/products/index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dw-primary px-4">
                            <i class="bi bi-check-circle me-1"></i> Apply Stock Adjustment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
