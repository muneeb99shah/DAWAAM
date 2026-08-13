<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Stock Level Adjustment Module
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alerts.php';

require_permission('inventory.adjust');

$pdo = get_db_connection();
$errors = [];

$prefilled_product_id = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

// Fetch all active products
$products = $pdo->query("SELECT id, name, sku, stock_qty, low_stock_threshold FROM products ORDER BY name ASC")->fetchAll();

$form_data = [
    'product_id' => (string)$prefilled_product_id,
    'adjustment_type' => 'stock_in', // 'stock_in', 'stock_out', 'set'
    'quantity' => '',
    'reason' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please try again.";
    }

    // 2. Extract & Sanitize
    $form_data['product_id'] = trim($_POST['product_id'] ?? '');
    $form_data['adjustment_type'] = trim($_POST['adjustment_type'] ?? 'stock_in');
    $form_data['quantity'] = trim($_POST['quantity'] ?? '');
    $form_data['reason'] = trim($_POST['reason'] ?? '');

    $target_product_id = (int)$form_data['product_id'];

    if ($target_product_id <= 0) {
        $errors[] = "Please select a valid product.";
    }

    if ($form_data['quantity'] === '' || !is_numeric($form_data['quantity']) || (int)$form_data['quantity'] < 0) {
        $errors[] = "Please enter a valid non-negative adjustment quantity.";
    }

    // Verify Target Product Exists
    $stmt_p = $pdo->prepare("SELECT id, name, stock_qty, low_stock_threshold FROM products WHERE id = :id LIMIT 1");
    $stmt_p->execute([':id' => $target_product_id]);
    $product_record = $stmt_p->fetch();

    if (!$product_record) {
        $errors[] = "Selected product was not found.";
    }

    if (empty($errors)) {
        $current_stock = (int)$product_record['stock_qty'];
        $adj_qty = (int)$form_data['quantity'];
        $new_stock = $current_stock;

        if ($form_data['adjustment_type'] === 'stock_in') {
            $new_stock = $current_stock + $adj_qty;
        } elseif ($form_data['adjustment_type'] === 'stock_out') {
            if ($adj_qty > $current_stock) {
                $errors[] = "Cannot remove {$adj_qty} units. Current shelf stock is only {$current_stock} units.";
            } else {
                $new_stock = $current_stock - $adj_qty;
            }
        } elseif ($form_data['adjustment_type'] === 'set') {
            $new_stock = $adj_qty;
        }
    }

    // Execute Stock Adjustment inside DB Transaction
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt_upd = $pdo->prepare("
                UPDATE products 
                SET stock_qty = :stock_qty, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt_upd->execute([
                ':stock_qty' => $new_stock,
                ':id' => $target_product_id
            ]);

            $pdo->commit();

            // Evaluate low stock rule engine & queue sync record
            check_and_trigger_low_stock_alert($target_product_id);
            queue_sync_record('products', $target_product_id, 'UPDATE');
            
            $log_desc = sprintf(
                "Stock %s: Product '%s' adjusted from %d to %d units. Reason: %s",
                strtoupper($form_data['adjustment_type']),
                $product_record['name'],
                $current_stock,
                $new_stock,
                !empty($form_data['reason']) ? $form_data['reason'] : 'Manual adjustment'
            );
            log_audit_action('STOCK_ADJUSTMENT', 'inventory', $target_product_id, $log_desc);

            set_flash_message('success', "Stock updated for '{$product_record['name']}'. New stock: {$new_stock} units.");
            redirect('admin/inventory/index.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Stock Adjustment Error: ' . $e->getMessage());
            $errors[] = "Failed to update stock: " . $e->getMessage();
        }
    }
}

$page_title = "Perform Stock Adjustment";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-plus-slash-minus text-warning me-2"></i> Inventory Stock Adjustment
        </h2>
        <p class="text-muted small mb-0">Manually add stock arrival, record stock removal, or set shelf balance.</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Stock Monitor
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="dw-card p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
                    <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i> Please fix the following errors:</h6>
                    <ul class="mb-0 ps-3 small">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo sanitize($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="adjustment.php" method="POST">
                <?php csrf_field(); ?>

                <div class="row g-3">
                    <div class="col-12">
                        <label for="product_id" class="form-label fw-semibold text-dark">Select Target Product <span class="text-danger">*</span></label>
                        <select class="form-select" id="product_id" name="product_id" required autofocus>
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $form_data['product_id'] == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($p['name']); ?> (SKU: <?php echo sanitize($p['sku'] ?? 'N/A'); ?>) - Current Stock: <?php echo $p['stock_qty']; ?> units
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="adjustment_type" class="form-label fw-semibold text-dark">Adjustment Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                            <option value="stock_in" <?php echo $form_data['adjustment_type'] === 'stock_in' ? 'selected' : ''; ?>>+ Stock Arrival (Add Units)</option>
                            <option value="stock_out" <?php echo $form_data['adjustment_type'] === 'stock_out' ? 'selected' : ''; ?>- Stock Removal / Damage (Deduct Units)</option>
                            <option value="set" <?php echo $form_data['adjustment_type'] === 'set' ? 'selected' : ''; ?>>= Override Exact Stock Level</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="quantity" class="form-label fw-semibold text-dark">Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="quantity" name="quantity" value="<?php echo sanitize($form_data['quantity']); ?>" placeholder="e.g. 10" required>
                    </div>

                    <div class="col-12">
                        <label for="reason" class="form-label fw-semibold text-dark">Adjustment Reason / Audit Note</label>
                        <input type="text" class="form-control" id="reason" name="reason" value="<?php echo sanitize($form_data['reason']); ?>" placeholder="e.g. Supplier Shipment Arrival #842, Expired medicine return, Shelf count audit">
                    </div>

                    <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
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
