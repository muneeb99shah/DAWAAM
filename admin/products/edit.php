<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Edit Product / Medicine
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alerts.php';

require_permission('products.edit');

$pdo = get_db_connection();
$errors = [];

$product_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($product_id <= 0) {
    set_flash_message('danger', 'Invalid product ID specified.');
    redirect('admin/products/index.php');
}

// Fetch existing product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $product_id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash_message('danger', 'Product not found in database.');
    redirect('admin/products/index.php');
}

// Fetch Categories
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$form_data = [
    'name' => $product['name'],
    'category_id' => $product['category_id'],
    'sku' => $product['sku'],
    'price' => $product['price'],
    'stock_qty' => $product['stock_qty'],
    'low_stock_threshold' => $product['low_stock_threshold']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Token Verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token verification failed. Please try again.";
    }

    // 2. Extract & Sanitize
    $form_data['name'] = trim($_POST['name'] ?? '');
    $form_data['category_id'] = trim($_POST['category_id'] ?? '');
    $form_data['sku'] = trim($_POST['sku'] ?? '');
    $form_data['price'] = trim($_POST['price'] ?? '');
    $form_data['stock_qty'] = trim($_POST['stock_qty'] ?? '0');
    $form_data['low_stock_threshold'] = trim($_POST['low_stock_threshold'] ?? (string)DEFAULT_LOW_STOCK_THRESHOLD);

    // 3. Validation
    if (empty($form_data['name'])) {
        $errors[] = "Product name is required.";
    } elseif (mb_strlen($form_data['name']) > 150) {
        $errors[] = "Product name cannot exceed 150 characters.";
    }

    if (!empty($form_data['sku']) && $form_data['sku'] !== $product['sku']) {
        // Check SKU uniqueness
        $stmt_sku = $pdo->prepare("SELECT id FROM products WHERE sku = :sku AND id != :id LIMIT 1");
        $stmt_sku->execute([':sku' => $form_data['sku'], ':id' => $product_id]);
        if ($stmt_sku->fetch()) {
            $errors[] = "SKU / Barcode '{$form_data['sku']}' is already assigned to another product.";
        }
    }

    if ($form_data['price'] === '' || !is_numeric($form_data['price']) || (float)$form_data['price'] < 0) {
        $errors[] = "Please enter a valid price (greater than or equal to 0).";
    }

    if ($form_data['stock_qty'] === '' || !is_numeric($form_data['stock_qty']) || (int)$form_data['stock_qty'] < 0) {
        $errors[] = "Please enter a valid stock quantity.";
    }

    if ($form_data['low_stock_threshold'] === '' || !is_numeric($form_data['low_stock_threshold']) || (int)$form_data['low_stock_threshold'] < 0) {
        $errors[] = "Please enter a valid low stock alert threshold.";
    }

    // 4. PDO Update
    if (empty($errors)) {
        try {
            $cat_id = !empty($form_data['category_id']) ? (int)$form_data['category_id'] : null;

            $stmt_upd = $pdo->prepare("
                UPDATE products 
                SET category_id = :category_id,
                    name = :name,
                    sku = :sku,
                    price = :price,
                    stock_qty = :stock_qty,
                    low_stock_threshold = :low_stock_threshold,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt_upd->execute([
                ':category_id' => $cat_id,
                ':name' => $form_data['name'],
                ':sku' => $form_data['sku'],
                ':price' => (float)$form_data['price'],
                ':stock_qty' => (int)$form_data['stock_qty'],
                ':low_stock_threshold' => (int)$form_data['low_stock_threshold'],
                ':id' => $product_id
            ]);

            // Evaluate low stock rule engine & queue sync record
            check_and_trigger_low_stock_alert($product_id);
            queue_sync_record('products', $product_id, 'UPDATE');
            log_audit_action('EDIT_PRODUCT', 'products', $product_id, "Updated product '{$form_data['name']}'");

            set_flash_message('success', "Product '{$form_data['name']}' updated successfully.");
            redirect('admin/products/index.php');
        } catch (Exception $e) {
            error_log('Edit Product Error: ' . $e->getMessage());
            $errors[] = "Failed to update product: " . $e->getMessage();
        }
    }
}

$page_title = "Edit Product - " . sanitize($product['name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-pencil-square text-primary me-2"></i> Edit Product / Medicine
        </h2>
        <p class="text-muted small mb-0">Modify product pricing, stock quantity, or low-stock threshold.</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Products
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

            <form action="edit.php?id=<?php echo $product_id; ?>" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $product_id; ?>">

                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label fw-semibold text-dark">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize($form_data['name']); ?>" required autofocus>
                    </div>

                    <div class="col-md-4">
                        <label for="sku" class="form-label fw-semibold text-dark">SKU / Barcode</label>
                        <input type="text" class="form-control font-monospace" id="sku" name="sku" value="<?php echo sanitize($form_data['sku']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="category_id" class="form-label fw-semibold text-dark">Product Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">-- Unassigned Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $form_data['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="price" class="form-label fw-semibold text-dark">Unit Price (PKR) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">PKR</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo sanitize($form_data['price']); ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="stock_qty" class="form-label fw-semibold text-dark">Stock Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="stock_qty" name="stock_qty" value="<?php echo sanitize($form_data['stock_qty']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="low_stock_threshold" class="form-label fw-semibold text-dark">Low Stock Alert Threshold <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo sanitize($form_data['low_stock_threshold']); ?>" required>
                    </div>

                    <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dw-primary px-4">
                            <i class="bi bi-save me-1"></i> Update Product
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
