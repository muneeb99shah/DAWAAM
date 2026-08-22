<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Local Point-of-Sale (POS) Checkout Terminal (Live Type & Search, Fast Pills, Multi-Item Cart)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/alerts.php';

require_permission('sales.create');

$pdo = get_db_connection();
$user = current_user();
$errors = [];

// Fetch products with available stock
$stmt_p = $pdo->query("
    SELECT id, name, sku, price, stock_qty, low_stock_threshold 
    FROM products 
    ORDER BY name ASC
");
$products = $stmt_p->fetchAll();

// Top 5 Fast-Moving Medicines for Quick Pills
$fast_moving = array_slice($products, 0, 5);

$form_data = [
    'customer_name' => 'Walk-in Customer',
    'customer_phone' => '',
    'discount_type' => 'fixed',
    'discount_val' => '0',
    'tax_percent' => '0',
    'payment_method' => 'cash',
    'payment_ref' => '',
    'amount_received' => '0'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security check failed. Please try again.";
    }

    // 2. Extract Cart Data (JSON Payload or Form Array)
    $cart_json = $_POST['cart_data'] ?? '[]';
    $cart_items = json_decode($cart_json, true);

    if (!is_array($cart_items) || count($cart_items) === 0) {
        $errors[] = "Cart is empty! Please search and select at least one medicine item.";
    }

    // 3. Extract & Sanitize Checkout Fields
    $form_data['customer_name'] = trim($_POST['customer_name'] ?? 'Walk-in Customer');
    if (empty($form_data['customer_name'])) $form_data['customer_name'] = 'Walk-in Customer';
    $form_data['customer_phone'] = trim($_POST['customer_phone'] ?? '');
    $form_data['customer_email'] = trim($_POST['customer_email'] ?? '');
    $form_data['customer_address'] = trim($_POST['customer_address'] ?? '');
    $form_data['customer_tax_id'] = trim($_POST['customer_tax_id'] ?? '');
    $form_data['discount_type'] = in_array($_POST['discount_type'] ?? '', ['fixed', 'percent'], true) ? $_POST['discount_type'] : 'fixed';
    $form_data['discount_val'] = max(0, (float)($_POST['discount_val'] ?? 0));
    $form_data['tax_percent'] = max(0, (float)($_POST['tax_percent'] ?? 0));
    $form_data['payment_method'] = in_array($_POST['payment_method'] ?? '', ['cash', 'card', 'bank_transfer', 'mobile_wallet'], true) ? $_POST['payment_method'] : 'cash';
    $form_data['payment_ref'] = trim($_POST['payment_ref'] ?? '');
    $form_data['amount_received'] = max(0, (float)($_POST['amount_received'] ?? 0));

    // 4. Server-Side Validation of Cart Products & Stock
    $validated_cart = [];
    $subtotal = 0.00;

    if (empty($errors) && is_array($cart_items)) {
        foreach ($cart_items as $item) {
            $pid = (int)($item['id'] ?? 0);
            $qty = (int)($item['qty'] ?? 1);

            if ($pid <= 0 || $qty <= 0) continue;

            $stmt_check = $pdo->prepare("SELECT id, name, sku, price, stock_qty, low_stock_threshold FROM products WHERE id = :id LIMIT 1");
            $stmt_check->execute([':id' => $pid]);
            $p_db = $stmt_check->fetch();

            if (!$p_db) {
                $errors[] = "Product ID #{$pid} was not found in catalog.";
                break;
            }

            if ($qty > (int)$p_db['stock_qty']) {
                $errors[] = sprintf(
                    "Insufficient Stock! Requested %d units, but only %d units of '%s' are available on shelf.",
                    $qty,
                    $p_db['stock_qty'],
                    $p_db['name']
                );
                break;
            }

            $line_price = (float)$p_db['price'];
            $line_total = $line_price * $qty;
            $subtotal += $line_total;

            $validated_cart[] = [
                'product' => $p_db,
                'quantity' => $qty,
                'unit_price' => $line_price,
                'total_price' => $line_total
            ];
        }
    }

    if (empty($errors) && count($validated_cart) === 0) {
        $errors[] = "No valid cart items were processed.";
    }

    // 5. Financial Math & Tax Calculations
    $discount_amount = 0.00;
    $tax_amount = 0.00;
    $grand_total = 0.00;
    $change_amount = 0.00;
    $remaining_amount = 0.00;
    $payment_status = 'paid';

    if (empty($errors)) {
        if ($form_data['discount_type'] === 'percent') {
            $discount_amount = $subtotal * ($form_data['discount_val'] / 100);
        } else {
            $discount_amount = $form_data['discount_val'];
        }

        // Cap discount
        $discount_amount = min($subtotal, max(0, $discount_amount));
        $after_discount = $subtotal - $discount_amount;

        // Calculate Tax
        if ($form_data['tax_percent'] > 0) {
            $tax_amount = $after_discount * ($form_data['tax_percent'] / 100);
        }

        $grand_total = max(0, $after_discount + $tax_amount);

        // Payment validation
        if ($form_data['payment_method'] !== 'cash') {
            $form_data['amount_received'] = $grand_total;
            $change_amount = 0.00;
            $remaining_amount = 0.00;
            $payment_status = 'paid';
        } else {
            if ($form_data['amount_received'] >= $grand_total) {
                $change_amount = $form_data['amount_received'] - $grand_total;
                $remaining_amount = 0.00;
                $payment_status = 'paid';
            } else {
                $remaining_amount = $grand_total - $form_data['amount_received'];
                $change_amount = 0.00;
                $payment_status = 'partial';
                $errors[] = sprintf(
                    "Insufficient Cash Payment! Grand Total is PKR %s, but Customer Paid PKR %s. Remaining: PKR %s.",
                    number_format($grand_total, 2),
                    number_format($form_data['amount_received'], 2),
                    number_format($remaining_amount, 2)
                );
            }
        }
    }

    // 6. Process Sale inside Atomic PDO Database Transaction
    if (empty($errors) && count($validated_cart) > 0) {
        try {
            $pdo->beginTransaction();

            $sale_code = generate_unique_code('SALE', 4);
            $primary_prod_id = $validated_cart[0]['product']['id'];
            $total_qty_sum = array_sum(array_column($validated_cart, 'quantity'));

            // A. Insert Master Sale Receipt Record
            $stmt_sale = $pdo->prepare("
                INSERT INTO sales 
                (sale_code, user_id, product_id, customer_name, customer_phone, customer_email, customer_address, customer_tax_id, quantity, unit_price, subtotal, 
                 discount_type, discount_val, discount_amount, tax_amount, total_price, payment_method, payment_ref, 
                 amount_received, change_amount, remaining_amount, payment_status, sold_at, created_at)
                VALUES 
                (:sale_code, :user_id, :product_id, :cust_name, :cust_phone, :cust_email, :cust_address, :cust_tax_id, :quantity, :unit_price, :subtotal, 
                 :disc_type, :disc_val, :disc_amount, :tax_amount, :total_price, :pay_method, :pay_ref, 
                 :amt_rec, :change_amt, :rem_amt, :pay_status, NOW(), NOW())
            ");
            $stmt_sale->execute([
                ':sale_code' => $sale_code,
                ':user_id' => $user['id'],
                ':product_id' => $primary_prod_id,
                ':cust_name' => $form_data['customer_name'],
                ':cust_phone' => $form_data['customer_phone'],
                ':cust_email' => $form_data['customer_email'],
                ':cust_address' => $form_data['customer_address'],
                ':cust_tax_id' => $form_data['customer_tax_id'],
                ':quantity' => $total_qty_sum,
                ':unit_price' => $validated_cart[0]['unit_price'],
                ':subtotal' => $subtotal,
                ':disc_type' => $form_data['discount_type'],
                ':disc_val' => $form_data['discount_val'],
                ':disc_amount' => $discount_amount,
                ':tax_amount' => $tax_amount,
                ':total_price' => $grand_total,
                ':pay_method' => $form_data['payment_method'],
                ':pay_ref' => $form_data['payment_ref'],
                ':amt_rec' => $form_data['amount_received'],
                ':change_amt' => $change_amount,
                ':rem_amt' => $remaining_amount,
                ':pay_status' => $payment_status
            ]);

            $sale_id = $pdo->lastInsertId();

            // B. Insert Line Items & Deduct Stock Atomically
            $stmt_item = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price, created_at)
                VALUES (:sale_id, :pid, :qty, :uprice, :total_price, NOW())
            ");

            $stmt_stock = $pdo->prepare("
                UPDATE products 
                SET stock_qty = stock_qty - :quantity, updated_at = NOW() 
                WHERE id = :id
            ");

            foreach ($validated_cart as $vc) {
                $stmt_item->execute([
                    ':sale_id' => $sale_id,
                    ':pid' => $vc['product']['id'],
                    ':qty' => $vc['quantity'],
                    ':uprice' => $vc['unit_price'],
                    ':total_price' => $vc['total_price']
                ]);

                $stmt_stock->execute([
                    ':quantity' => $vc['quantity'],
                    ':id' => $vc['product']['id']
                ]);

                // Check rule engine per item
                check_and_trigger_low_stock_alert($vc['product']['id']);
            }

            $pdo->commit();

            // Evaluate big sale trigger
            check_and_trigger_big_sale_alert($sale_id, $grand_total, $validated_cart[0]['product']['name'], $total_qty_sum);

            // Queue for recovery sync & audit logging
            queue_sync_record('sales', $sale_id, 'INSERT');
            log_audit_action('PROCESS_SALE', 'sales', $sale_id, "POS Sale {$sale_code}: " . count($validated_cart) . " item(s) for " . format_currency($grand_total));

            set_flash_message('success', "Sale {$sale_code} completed successfully! Total PKR " . number_format($grand_total, 2) . " recorded.");
            redirect('admin/sales/receipt.php?id=' . $sale_id);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Process Sale Error: ' . $e->getMessage());
            $errors[] = "Transaction failed: " . $e->getMessage();
        }
    }
}

$page_title = "Point-of-Sale (POS) Terminal";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-calculator text-primary me-2"></i> Local POS Checkout Terminal
        </h2>
        <p class="text-muted small mb-0">Type & search medicine names, scan SKUs, add items to cart, apply discounts & print receipts.</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-receipt me-1"></i> Sales History Log
        </a>
    </div>
</div>

<!-- Fast-Moving Medicine Quick Pills Bar -->
<?php if (count($fast_moving) > 0): ?>
    <div class="dw-card p-3 mb-4 bg-white">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="small fw-bold text-muted text-uppercase me-2"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Fast-Moving Items:</span>
            <?php foreach ($fast_moving as $fm): ?>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 quick-add-pill" 
                        data-id="<?php echo $fm['id']; ?>"
                        data-name="<?php echo sanitize($fm['name']); ?>"
                        data-sku="<?php echo sanitize($fm['sku']); ?>"
                        data-price="<?php echo $fm['price']; ?>"
                        data-stock="<?php echo $fm['stock_qty']; ?>">
                    + <?php echo sanitize($fm['name']); ?> (PKR <?php echo number_format($fm['price'], 0); ?>)
                </button>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<form action="create.php" method="POST" id="posForm">
    <?php csrf_field(); ?>
    <input type="hidden" name="cart_data" id="cart_data_input" value="[]">

    <div class="row g-4">
        <!-- Main POS Item Search & Cart Column -->
        <div class="col-lg-7">
            <div class="dw-card p-4 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="fw-bold text-dark"><i class="bi bi-person-circle me-1 text-teal" style="color:#0f766e;"></i> Cashier: <?php echo sanitize($user['name']); ?></span>
                    <span class="badge bg-light text-dark border font-monospace">Local Server Mode</span>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
                        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i> Transaction Alert:</h6>
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo sanitize($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Customer Details Row -->
                <div class="row g-3 mb-3 p-3 bg-light rounded border">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold text-dark">Customer Name / Label</label>
                        <input type="text" class="form-control form-control-sm" name="customer_name" value="<?php echo sanitize($form_data['customer_name']); ?>" placeholder="Walk-in Customer">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold text-dark">Customer Phone (Optional)</label>
                        <input type="text" class="form-control form-control-sm font-monospace" name="customer_phone" value="<?php echo sanitize($form_data['customer_phone']); ?>" placeholder="Example: +1234567890">
                    </div>
                </div>

                <!-- Live Type & Search Product Autocomplete Input -->
                <div class="mb-4">
                    <label for="product_search" class="form-label fw-semibold text-dark">
                        Type & Search Medicine Name / Scan SKU Barcode <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-primary text-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control font-monospace" id="product_search" list="posProductSuggestions" placeholder="Type medicine name or scan SKU barcode..." autocomplete="off" autofocus>
                        <datalist id="posProductSuggestions">
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo sanitize($p['name']); ?> [SKU: <?php echo sanitize($p['sku']); ?>]" 
                                        data-id="<?php echo $p['id']; ?>"
                                        data-name="<?php echo sanitize($p['name']); ?>"
                                        data-sku="<?php echo sanitize($p['sku']); ?>"
                                        data-price="<?php echo $p['price']; ?>"
                                        data-stock="<?php echo $p['stock_qty']; ?>">
                                    Price: PKR <?php echo number_format($p['price'], 2); ?> | Stock: <?php echo $p['stock_qty']; ?>
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                        <button type="button" class="btn btn-primary px-4" id="add_searched_item_btn">
                            <i class="bi bi-cart-plus me-1"></i> Add to Cart
                        </button>
                    </div>
                    <div class="form-text small">Type any part of the medicine name or SKU barcode and select or press Enter to add to cart.</div>
                </div>

                <!-- Multi-Item Shopping Cart Table -->
                <div class="border rounded p-3 mb-4 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-cart3 text-primary me-1"></i> Checkout Shopping Cart Table</h6>
                        <span class="badge bg-dark rounded-pill" id="cart_badge_count">0 Items</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="cart_table">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Item & SKU</th>
                                    <th class="text-center" style="width: 140px;">Quantity</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Line Total</th>
                                    <th class="text-end" style="width: 45px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cart_tbody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="bi bi-basket fs-3 d-block mb-1 text-secondary"></i>
                                        Cart is empty. Search medicine above or click Fast-Moving items to add.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Discount & Tax Controls -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <label class="form-label fw-semibold text-dark mb-2"><i class="bi bi-tag text-primary me-1"></i> Discount Rules</label>
                            <div class="input-group input-group-sm mb-1">
                                <select name="discount_type" id="discount_type" class="form-select" style="max-width: 130px;">
                                    <option value="fixed" <?php echo $form_data['discount_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed (PKR)</option>
                                    <option value="percent" <?php echo $form_data['discount_type'] === 'percent' ? 'selected' : ''; ?>>Percent (%)</option>
                                </select>
                                <span class="input-group-text" id="discount_symbol">PKR</span>
                                <input type="number" step="any" min="0" class="form-control font-monospace fw-bold" id="discount_val" name="discount_val" value="<?php echo sanitize($form_data['discount_val']); ?>" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <label class="form-label fw-semibold text-dark mb-2"><i class="bi bi-percent text-info me-1"></i> Sales Tax Rate</label>
                            <select name="tax_percent" id="tax_percent" class="form-select form-select-sm">
                                <option value="0" selected>0% Tax (Medical Exempt)</option>
                                <option value="5">5% Standard Medical Sales Tax</option>
                                <option value="13">13% Balochistan PRA Services Tax</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Payment Method & Reference -->
                <div class="p-3 bg-light rounded border mt-3 mb-2">
                    <label class="form-label fw-semibold text-dark mb-2"><i class="bi bi-wallet2 text-success me-1"></i> Payment Method</label>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <select name="payment_method" id="payment_method" class="form-select">
                                <option value="cash" <?php echo $form_data['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash Payment</option>
                                <option value="card" <?php echo $form_data['payment_method'] === 'card' ? 'selected' : ''; ?>>Credit / Debit Card</option>
                                <option value="bank_transfer" <?php echo $form_data['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="mobile_wallet" <?php echo $form_data['payment_method'] === 'mobile_wallet' ? 'selected' : ''; ?>>Mobile Wallet (EasyPaisa/JazzCash)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control font-monospace" name="payment_ref" id="payment_ref" value="<?php echo sanitize($form_data['payment_ref']); ?>" placeholder="Transaction ID / Card Ref (Optional)">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Checkout Financial Summary Side Column -->
        <div class="col-lg-5">
            <div class="dw-card p-4 bg-white border-2 border-primary shadow-sm sticky-top" style="top: 20px;">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                    <i class="bi bi-receipt-cutoff text-primary me-2"></i> Sale Breakdown Summary
                </h5>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal:</span>
                    <strong class="text-dark font-monospace" id="summary_subtotal">PKR 0.00</strong>
                </div>

                <div class="d-flex justify-content-between mb-2 text-danger">
                    <span>Discount:</span>
                    <strong class="font-monospace" id="summary_discount">- PKR 0.00</strong>
                </div>

                <div class="d-flex justify-content-between mb-3 text-muted">
                    <span>Tax:</span>
                    <strong class="font-monospace" id="summary_tax">PKR 0.00</strong>
                </div>

                <hr>

                <!-- Total Payable Header -->
                <div class="p-3 bg-dark text-white rounded-3 mb-3">
                    <span class="text-white-50 small uppercase d-block">GRAND TOTAL PAYABLE</span>
                    <div class="display-6 fw-bold text-success" id="summary_grand_total">
                        PKR 0.00
                    </div>
                    <small id="cart_items_info" class="text-warning d-block mt-1">0 items in cart</small>
                </div>

                <!-- Cash Payment Section -->
                <div id="cash_payment_section">
                    <label class="form-label fw-semibold text-dark">Customer Paid / Amount Received <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg mb-2">
                        <span class="input-group-text">PKR</span>
                        <input type="number" step="any" min="0" class="form-control font-monospace fw-bold fs-4 text-dark" id="amount_received" name="amount_received" value="<?php echo sanitize($form_data['amount_received']); ?>" required>
                    </div>

                    <!-- Quick Cash Buttons -->
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill quick-cash-btn" data-val="exact">Exact</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill quick-cash-btn" data-val="500">500</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill quick-cash-btn" data-val="1000">1,000</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill quick-cash-btn" data-val="2000">2,000</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill quick-cash-btn" data-val="5000">5,000</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill quick-cash-btn" data-val="10000">10,000</button>
                    </div>

                    <!-- Change / Remaining Display Box -->
                    <div class="p-3 rounded-3 mb-3 border text-center" id="change_status_box" style="background-color: #f8fafc;">
                        <span class="text-muted small uppercase d-block" id="change_label">CHANGE TO RETURN</span>
                        <div class="fs-3 fw-bold text-success" id="change_val_display">PKR 0.00</div>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-dw-primary btn-lg w-100 py-3 fw-bold fs-5 shadow" disabled>
                    <i class="bi bi-cart-check-fill me-2"></i> COMPLETE SALE
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Real-Time Client-Side Autocomplete, Multi-Item Cart & Change Calculator Engine -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const productsMap = {};
    <?php foreach ($products as $p): ?>
        productsMap[<?php echo $p['id']; ?>] = {
            id: <?php echo $p['id']; ?>,
            name: <?php echo json_encode($p['name']); ?>,
            sku: <?php echo json_encode($p['sku']); ?>,
            price: <?php echo (float)$p['price']; ?>,
            stock: <?php echo (int)$p['stock_qty']; ?>
        };
    <?php endforeach; ?>

    let cart = [];

    const productSearch = document.getElementById('product_search');
    const addSearchedBtn = document.getElementById('add_searched_item_btn');
    const cartTbody = document.getElementById('cart_tbody');
    const cartBadgeCount = document.getElementById('cart_badge_count');
    const cartDataInput = document.getElementById('cart_data_input');
    const cartItemsInfo = document.getElementById('cart_items_info');

    const discountType = document.getElementById('discount_type');
    const discountVal = document.getElementById('discount_val');
    const discountSymbol = document.getElementById('discount_symbol');
    const taxPercent = document.getElementById('tax_percent');
    const paymentMethod = document.getElementById('payment_method');
    const amountReceived = document.getElementById('amount_received');
    const cashSection = document.getElementById('cash_payment_section');
    const submitBtn = document.getElementById('submitBtn');

    const summarySubtotal = document.getElementById('summary_subtotal');
    const summaryDiscount = document.getElementById('summary_discount');
    const summaryTax = document.getElementById('summary_tax');
    const summaryGrandTotal = document.getElementById('summary_grand_total');
    const changeLabel = document.getElementById('change_label');
    const changeValDisplay = document.getElementById('change_val_display');
    const changeStatusBox = document.getElementById('change_status_box');

    let currentGrandTotal = 0;

    function formatMoney(amt) {
        return 'PKR ' + amt.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function addToCart(pid, qty = 1) {
        if (!productsMap[pid]) return;

        const prod = productsMap[pid];
        const existing = cart.find(c => c.id === pid);

        if (existing) {
            if (existing.qty + qty > prod.stock) {
                alert('Stock Limit Warning: Only ' + prod.stock + ' units of ' + prod.name + ' available on shelf.');
                return;
            }
            existing.qty += qty;
        } else {
            if (qty > prod.stock) {
                alert('Stock Limit Warning: Only ' + prod.stock + ' units of ' + prod.name + ' available on shelf.');
                return;
            }
            cart.push({
                id: prod.id,
                name: prod.name,
                sku: prod.sku,
                price: prod.price,
                stock: prod.stock,
                qty: qty
            });
        }

        productSearch.value = '';
        renderCart();
    }

    function removeFromCart(pid) {
        cart = cart.filter(c => c.id !== pid);
        renderCart();
    }

    function updateCartQty(pid, newQty) {
        const item = cart.find(c => c.id === pid);
        if (!item) return;

        if (newQty <= 0) {
            removeFromCart(pid);
            return;
        }

        if (newQty > item.stock) {
            alert('Stock Limit Warning: Only ' + item.stock + ' units of ' + item.name + ' available on shelf.');
            item.qty = item.stock;
        } else {
            item.qty = newQty;
        }
        renderCart();
    }

    function renderCart() {
        if (cart.length === 0) {
            cartTbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="bi bi-basket fs-3 d-block mb-1 text-secondary"></i>
                        Cart is empty. Search medicine above or click Fast-Moving items to add.
                    </td>
                </tr>
            `;
            cartBadgeCount.textContent = '0 Items';
            cartItemsInfo.textContent = '0 items in cart';
        } else {
            let html = '';
            let totalUnits = 0;

            cart.forEach(item => {
                const lineTotal = item.price * item.qty;
                totalUnits += item.qty;
                html += `
                    <tr>
                        <td>
                            <strong class="text-dark d-block">${item.name}</strong>
                            <span class="small text-muted font-monospace">SKU: ${item.sku}</span>
                        </td>
                        <td class="text-center">
                            <div class="input-group input-group-sm">
                                <button type="button" class="btn btn-outline-secondary cart-qty-minus" data-id="${item.id}">-</button>
                                <input type="number" min="1" max="${item.stock}" class="form-control text-center font-monospace cart-qty-input" data-id="${item.id}" value="${item.qty}">
                                <button type="button" class="btn btn-outline-secondary cart-qty-plus" data-id="${item.id}">+</button>
                            </div>
                        </td>
                        <td class="text-end text-muted">${item.price.toFixed(2)}</td>
                        <td class="text-end fw-bold text-dark">${lineTotal.toFixed(2)}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 cart-remove-btn" data-id="${item.id}" title="Remove item">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            cartTbody.innerHTML = html;
            cartBadgeCount.textContent = cart.length + ' Items (' + totalUnits + ' Units)';
            cartItemsInfo.textContent = cart.length + ' Item(s) in Cart (' + totalUnits + ' Total Units)';
        }

        cartDataInput.value = JSON.stringify(cart);
        calculatePOSMath();
    }

    function calculatePOSMath() {
        let subtotal = 0;
        cart.forEach(c => subtotal += (c.price * c.qty));

        summarySubtotal.textContent = formatMoney(subtotal);

        // Discount Math
        discountSymbol.textContent = (discountType.value === 'percent') ? '%' : 'PKR';
        let discVal = parseFloat(discountVal.value || 0);
        let discAmount = 0;

        if (discountType.value === 'percent') {
            discAmount = subtotal * (discVal / 100);
        } else {
            discAmount = discVal;
        }

        discAmount = Math.min(subtotal, Math.max(0, discAmount));
        summaryDiscount.textContent = '- ' + formatMoney(discAmount);

        let afterDiscount = subtotal - discAmount;

        // Tax Math
        let taxPct = parseFloat(taxPercent.value || 0);
        let taxAmt = 0;
        if (taxPct > 0) {
            taxAmt = afterDiscount * (taxPct / 100);
        }
        summaryTax.textContent = formatMoney(taxAmt);

        currentGrandTotal = Math.max(0, afterDiscount + taxAmt);
        summaryGrandTotal.textContent = formatMoney(currentGrandTotal);

        // Cash vs Non-Cash
        if (cart.length === 0) {
            submitBtn.disabled = true;
            changeValDisplay.textContent = formatMoney(0);
            return;
        }

        if (paymentMethod.value !== 'cash') {
            cashSection.style.display = 'none';
            amountReceived.value = currentGrandTotal.toFixed(2);
            submitBtn.disabled = false;
        } else {
            cashSection.style.display = 'block';
            let rec = parseFloat(amountReceived.value || 0);

            if (rec >= currentGrandTotal) {
                let change = rec - currentGrandTotal;
                changeLabel.textContent = 'CHANGE TO RETURN TO CUSTOMER';
                changeValDisplay.textContent = formatMoney(change);
                changeValDisplay.className = 'fs-3 fw-bold text-success';
                changeStatusBox.style.backgroundColor = '#f0fdf4';
                submitBtn.disabled = false;
            } else {
                let rem = currentGrandTotal - rec;
                changeLabel.textContent = 'INSUFFICIENT PAYMENT (REMAINING)';
                changeValDisplay.textContent = formatMoney(rem);
                changeValDisplay.className = 'fs-3 fw-bold text-danger';
                changeStatusBox.style.backgroundColor = '#fef2f2';
                submitBtn.disabled = true;
            }
        }
    }

    // Handle Item Search Add
    function handleSearchAdd() {
        const val = productSearch.value.trim();
        if (!val) return;

        // Try exact match or datalist option lookup
        const datalist = document.getElementById('posProductSuggestions');
        for (let opt of datalist.options) {
            if (opt.value === val || opt.value.toLowerCase().includes(val.toLowerCase())) {
                const pid = parseInt(opt.getAttribute('data-id'));
                if (pid > 0) {
                    addToCart(pid, 1);
                    return;
                }
            }
        }
    }

    addSearchedBtn.addEventListener('click', handleSearchAdd);
    productSearch.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSearchAdd();
        }
    });

    // Quick Add Fast-Moving Pills Listener
    document.querySelectorAll('.quick-add-pill').forEach(pill => {
        pill.addEventListener('click', function () {
            const pid = parseInt(this.getAttribute('data-id'));
            addToCart(pid, 1);
        });
    });

    // Cart Table Actions Event Delegation
    cartTbody.addEventListener('click', function (e) {
        const target = e.target.closest('button');
        if (!target) return;

        const pid = parseInt(target.getAttribute('data-id'));

        if (target.classList.contains('cart-remove-btn')) {
            removeFromCart(pid);
        } else if (target.classList.contains('cart-qty-minus')) {
            const item = cart.find(c => c.id === pid);
            if (item) updateCartQty(pid, item.qty - 1);
        } else if (target.classList.contains('cart-qty-plus')) {
            const item = cart.find(c => c.id === pid);
            if (item) updateCartQty(pid, item.qty + 1);
        }
    });

    cartTbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('cart-qty-input')) {
            const pid = parseInt(e.target.getAttribute('data-id'));
            const qty = parseInt(e.target.value || 1);
            updateCartQty(pid, qty);
        }
    });

    // Quick Cash Buttons Listener
    document.querySelectorAll('.quick-cash-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const val = this.getAttribute('data-val');
            if (val === 'exact') {
                amountReceived.value = currentGrandTotal.toFixed(2);
            } else {
                amountReceived.value = parseFloat(val).toFixed(2);
            }
            calculatePOSMath();
        });
    });

    // Double Submit Prevention
    document.getElementById('posForm').addEventListener('submit', function (e) {
        if (cart.length === 0) {
            e.preventDefault();
            alert('Please add at least one medicine item to the cart.');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i> Processing Sale...';
    });

    discountType.addEventListener('change', calculatePOSMath);
    discountVal.addEventListener('input', calculatePOSMath);
    taxPercent.addEventListener('change', calculatePOSMath);
    paymentMethod.addEventListener('change', calculatePOSMath);
    amountReceived.addEventListener('input', calculatePOSMath);

    renderCart();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
