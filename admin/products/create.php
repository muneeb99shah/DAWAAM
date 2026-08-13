<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Create New Product / Medicine
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alerts.php';

require_permission('products.create');

$pdo = get_db_connection();
$errors = [];

$form_data = [
    'name' => '',
    'category_id' => '',
    'sku' => '',
    'price' => '',
    'stock_qty' => '0',
    'low_stock_threshold' => (string)DEFAULT_LOW_STOCK_THRESHOLD
];

// Fetch Categories
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

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

    if (!empty($form_data['sku'])) {
        // Check SKU uniqueness
        $stmt_sku = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
        $stmt_sku->execute([':sku' => $form_data['sku']]);
        if ($stmt_sku->fetch()) {
            $errors[] = "SKU / Barcode '{$form_data['sku']}' is already assigned to another product.";
        }
    }

    if ($form_data['price'] === '' || !is_numeric($form_data['price']) || (float)$form_data['price'] < 0) {
        $errors[] = "Please enter a valid price (greater than or equal to 0).";
    }

    if ($form_data['stock_qty'] === '' || !is_numeric($form_data['stock_qty']) || (int)$form_data['stock_qty'] < 0) {
        $errors[] = "Please enter a valid initial stock quantity.";
    }

    if ($form_data['low_stock_threshold'] === '' || !is_numeric($form_data['low_stock_threshold']) || (int)$form_data['low_stock_threshold'] < 0) {
        $errors[] = "Please enter a valid low stock alert threshold.";
    }

    // 4. PDO Insertion
    if (empty($errors)) {
        try {
            $cat_id = !empty($form_data['category_id']) ? (int)$form_data['category_id'] : null;
            $sku_val = !empty($form_data['sku']) ? $form_data['sku'] : generate_unique_code('PRD', 4);

            $stmt_ins = $pdo->prepare("
                INSERT INTO products (category_id, name, sku, price, stock_qty, low_stock_threshold, created_at)
                VALUES (:category_id, :name, :sku, :price, :stock_qty, :low_stock_threshold, NOW())
            ");
            $stmt_ins->execute([
                ':category_id' => $cat_id,
                ':name' => $form_data['name'],
                ':sku' => $sku_val,
                ':price' => (float)$form_data['price'],
                ':stock_qty' => (int)$form_data['stock_qty'],
                ':low_stock_threshold' => (int)$form_data['low_stock_threshold']
            ]);

            $product_id = $pdo->lastInsertId();

            // Evaluate low stock rule engine & queue sync record
            check_and_trigger_low_stock_alert($product_id);
            queue_sync_record('products', $product_id, 'INSERT');
            log_audit_action('CREATE_PRODUCT', 'products', $product_id, "Added product '{$form_data['name']}' (Stock: {$form_data['stock_qty']})");

            set_flash_message('success', "Product '{$form_data['name']}' added successfully.");
            redirect('admin/products/index.php');
        } catch (Exception $e) {
            error_log('Create Product Error: ' . $e->getMessage());
            $errors[] = "Failed to add product: " . $e->getMessage();
        }
    }
}

$page_title = "Add New Product";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-plus-circle text-primary me-2"></i> Add New Product / Medicine
        </h2>
        <p class="text-muted small mb-0">Register a new item in the local inventory database.</p>
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

            <form action="create.php" method="POST">
                <?php csrf_field(); ?>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label fw-semibold text-dark">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize($form_data['name']); ?>" placeholder="e.g. Cefixime 400mg Capsules" required autofocus>
                    </div>

                    <div class="col-md-4">
                        <label for="sku" class="form-label fw-semibold text-dark">SKU / Barcode</label>
                        <input type="text" class="form-control font-monospace" id="sku" name="sku" value="<?php echo sanitize($form_data['sku']); ?>" placeholder="Auto-generated if empty">
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
                            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo sanitize($form_data['price']); ?>" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="stock_qty" class="form-label fw-semibold text-dark">Initial Stock Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="stock_qty" name="stock_qty" value="<?php echo sanitize($form_data['stock_qty']); ?>" required>
                        <div class="form-text">Units available locally on shelf.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="low_stock_threshold" class="form-label fw-semibold text-dark">Low Stock Alert Threshold <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo sanitize($form_data['low_stock_threshold']); ?>" required>
                        <div class="form-text">Triggers urgent alert when stock &le; threshold.</div>
                    </div>

                    <div class="col-12 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dw-primary px-4">
                            <i class="bi bi-save me-1"></i> Save Product
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
