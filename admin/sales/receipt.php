<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Printable Sales Receipt & Success Confirmation Module
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_permission('sales.view');

$sale_id = (int)($_GET['id'] ?? 0);
if ($sale_id <= 0) {
    set_flash_message('danger', 'Invalid sale ID specified.');
    redirect('admin/sales/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT s.id, s.sale_code, s.customer_name, s.customer_phone, s.quantity, s.unit_price, s.subtotal,
           s.discount_type, s.discount_val, s.discount_amount, s.tax_amount, s.total_price,
           s.payment_method, s.payment_ref, s.amount_received, s.change_amount, s.remaining_amount, s.payment_status, s.sold_at,
           p.name AS product_name, p.sku AS product_sku, c.name AS category_name,
           u.name AS cashier_name, u.user_code AS cashier_code
    FROM sales s
    INNER JOIN products p ON s.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    set_flash_message('danger', 'Sale receipt not found.');
    redirect('admin/sales/index.php');
}

// Fetch line items from sale_items
$stmt_items = $pdo->prepare("
    SELECT si.quantity, si.unit_price, si.total_price, p.name AS product_name, p.sku AS product_sku
    FROM sale_items si
    INNER JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = :sale_id
");
$stmt_items->execute([':sale_id' => $sale_id]);
$items = $stmt_items->fetchAll();

if (empty($items)) {
    $items = [[
        'product_name' => $sale['product_name'],
        'product_sku' => $sale['product_sku'],
        'quantity' => $sale['quantity'],
        'unit_price' => $sale['unit_price'],
        'total_price' => $sale['subtotal']
    ]];
}

$page_title = "Receipt " . sanitize($sale['sale_code']);
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Multi-Printer Paper Size & Print CSS Configuration -->
<style id="dw-print-page-style">
@media print {
    @page {
        size: 80mm auto;
        margin: 2mm 3mm;
    }
    .dw-navbar, .dw-footer, .no-print, header, nav, footer {
        display: none !important;
    }
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-family: var(--dw-font-sans) !important;
    }
    .receipt-box {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<div class="row justify-content-center mb-5">
    <div class="col-md-9 col-lg-8">
        <!-- Action Toolbar & Printer Paper Size Selector -->
        <div class="card border-0 shadow-sm p-3 mb-3 bg-white no-print">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-bold text-dark d-block mb-1">
                        <i class="bi bi-printer text-success me-1"></i> Receipt & Printer Setup
                    </span>
                    <span class="small text-muted">Select printer paper format:</span>
                    <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Paper Format Selector">
                        <button type="button" class="btn btn-outline-success active dw-paper-btn" onclick="setPaperFormat('80mm', this)">
                            Thermal 80mm
                        </button>
                        <button type="button" class="btn btn-outline-success dw-paper-btn" onclick="setPaperFormat('58mm', this)">
                            Thermal 58mm
                        </button>
                        <button type="button" class="btn btn-outline-secondary dw-paper-btn" onclick="setPaperFormat('a4', this)">
                            Standard A4
                        </button>
                        <button type="button" class="btn btn-outline-secondary dw-paper-btn" onclick="setPaperFormat('a5', this)">
                            Standard A5
                        </button>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="invoice.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i> Tax Invoice
                    </a>
                    <a href="challan.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-truck me-1"></i> Delivery Challan
                    </a>
                    <button onclick="window.print();" class="btn btn-dw-primary btn-sm px-3">
                        <i class="bi bi-printer me-1"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>

        <!-- Receipt Box Container -->
        <div class="table-responsive bg-white rounded-3 shadow-sm border-0 p-2">
            <div id="dw-receipt-container" class="dw-card p-3 bg-white receipt-box paper-format-80mm">
                <div class="text-center pb-2 mb-2 border-bottom border-2 border-dark">
                    <div class="d-inline-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-shield-check text-success fs-4"></i>
                        <h4 class="fw-bold mb-0 text-dark"><?php echo APP_NAME; ?> PHARMACY</h4>
                    </div>
                    <div class="small text-muted fw-semibold">Quetta Medical & Continuity Center</div>
                    <div class="text-muted" style="font-size: 0.65rem;">MA Jinnah Road, Quetta, Balochistan, Pakistan</div>
                    <div class="font-monospace text-muted" style="font-size: 0.6rem;">LAN Local POS Active</div>
                </div>

                <!-- Receipt Invoice Info Header -->
                <div class="row g-2 mb-2 pb-2 border-bottom small" style="font-size: 0.7rem;">
                    <div class="col-6">
                        <span class="text-muted d-block">Invoice / Receipt Code:</span>
                        <strong class="font-monospace text-dark d-block receipt-meta-val"><?php echo sanitize($sale['sale_code']); ?></strong>
                    </div>
                    <div class="col-6 text-end">
                        <span class="text-muted d-block">Date & Time:</span>
                        <strong class="text-dark d-block receipt-meta-val"><?php echo format_date($sale['sold_at'], 'd M Y, h:i A'); ?></strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Customer:</span>
                        <strong class="text-dark d-block receipt-meta-val"><?php echo sanitize($sale['customer_name'] ?? 'Walk-in Customer'); ?></strong>
                    </div>
                    <div class="col-6 text-end">
                        <span class="text-muted d-block">Cashier:</span>
                        <strong class="text-dark d-block receipt-meta-val"><?php echo sanitize($sale['cashier_name']); ?></strong>
                    </div>
                </div>

                <!-- Purchased Item Table -->
                <table class="table table-sm border-top border-bottom my-2 align-middle" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr class="table-light small">
                            <th style="width: 44%;">Item</th>
                            <th class="text-center" style="width: 12%; white-space: nowrap;">Qty</th>
                            <th class="text-end" style="width: 22%; white-space: nowrap;">Price</th>
                            <th class="text-end" style="width: 22%; white-space: nowrap;">Total</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td style="word-break: break-word; padding-right: 2px;">
                                    <strong class="text-dark d-block"><?php echo sanitize($it['product_name']); ?></strong>
                                    <span class="text-muted font-monospace" style="font-size: 0.65rem;">SKU: <?php echo sanitize($it['product_sku']); ?></span>
                                </td>
                                <td class="text-center fw-bold text-dark" style="white-space: nowrap;"><?php echo number_format($it['quantity']); ?></td>
                                <td class="text-end text-muted font-monospace" style="white-space: nowrap; font-size: 0.7rem;"><?php echo number_format($it['unit_price'], 2); ?></td>
                                <td class="text-end fw-bold text-dark font-monospace" style="white-space: nowrap; font-size: 0.7rem;"><?php echo number_format($it['total_price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Financial Summary Breakdown Box -->
                <div class="p-2 bg-light rounded-3 my-2">
                    <div class="d-flex justify-content-between mb-1 small">
                        <span class="text-muted">Subtotal:</span>
                        <strong class="font-monospace text-dark">PKR <?php echo number_format($sale['subtotal'], 2); ?></strong>
                    </div>
                    <?php if ($sale['discount_amount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-1 small text-danger">
                            <span>Discount (<?php echo sanitize($sale['discount_val']); ?><?php echo in_array($sale['discount_type'], ['percent', 'percentage'], true) ? '%' : ' PKR'; ?>):</span>
                            <strong class="font-monospace">- PKR <?php echo number_format($sale['discount_amount'], 2); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($sale['tax_amount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-1 small text-muted">
                            <span>Sales Tax:</span>
                            <strong class="font-monospace">+ PKR <?php echo number_format($sale['tax_amount'], 2); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top border-dark">
                        <span class="fw-bold text-dark total-payable-label" style="font-size: 0.85rem;">TOTAL PAYABLE:</span>
                        <strong class="fw-bold text-dark font-monospace total-payable-amount" style="font-size: 0.95rem;">PKR <?php echo number_format($sale['total_price'], 2); ?></strong>
                    </div>
                </div>

                <!-- Payment Details Grid -->
                <div class="p-2 border rounded-3 bg-white my-2">
                    <div class="row g-1 small">
                        <div class="col-6">
                            <span class="text-muted d-block" style="font-size: 0.65rem;">Payment Method:</span>
                            <strong class="text-dark uppercase" style="font-size: 0.75rem;"><i class="bi bi-wallet2 me-1"></i> <?php echo str_replace('_', ' ', $sale['payment_method']); ?></strong>
                            <?php if (!empty($sale['payment_ref'])): ?>
                                <span class="d-block font-monospace text-muted" style="font-size: 0.65rem;">Ref: <?php echo sanitize($sale['payment_ref']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 text-end">
                            <span class="text-muted d-block" style="font-size: 0.65rem;">Customer Paid:</span>
                            <strong class="text-success font-monospace" style="font-size: 0.85rem;"><?php echo format_currency($sale['amount_received']); ?></strong>
                        </div>
                        <div class="col-6 border-top pt-1 mt-1">
                            <span class="text-muted d-block" style="font-size: 0.65rem;">Payment Status:</span>
                            <span class="badge bg-success uppercase" style="font-size: 0.65rem;"><?php echo sanitize(strtoupper($sale['payment_status'])); ?></span>
                        </div>
                        <div class="col-6 text-end border-top pt-1 mt-1">
                            <span class="text-muted d-block" style="font-size: 0.65rem;">Change Returned:</span>
                            <strong class="text-primary font-monospace" style="font-size: 0.85rem;"><?php echo format_currency($sale['change_amount']); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Footer Message -->
                <div class="text-center pt-3 border-top small text-muted">
                    <p class="mb-1 fw-bold text-dark">Thank you for visiting Quetta Pharmacy!</p>
                    <p class="mb-0">Dawaam Local-First Business Continuity Software &bull; 100% Offline Uptime</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setPaperFormat(format, btn) {
    const container = document.getElementById('dw-receipt-container');
    const styleEl = document.getElementById('dw-print-page-style');
    
    document.querySelectorAll('.dw-paper-btn').forEach(b => b.classList.remove('active', 'btn-success', 'btn-secondary'));
    btn.classList.add('active', format.includes('mm') ? 'btn-success' : 'btn-secondary');

    container.classList.remove('paper-format-80mm', 'paper-format-58mm', 'paper-format-a4', 'paper-format-a5');
    container.classList.add('paper-format-' + format);

    let pageCss = '';
    if (format === '80mm') {
        pageCss = '@page { size: 80mm auto; margin: 2mm 3mm; }';
    } else if (format === '58mm') {
        pageCss = '@page { size: 58mm auto; margin: 2mm 2mm; }';
    } else if (format === 'a4') {
        pageCss = '@page { size: A4 portrait; margin: 12mm 15mm; }';
    } else if (format === 'a5') {
        pageCss = '@page { size: A5 portrait; margin: 8mm 10mm; }';
    }

    styleEl.innerHTML = `@media print { ${pageCss} .dw-navbar, .dw-footer, .no-print, header, nav, footer { display: none !important; } body { background-color: #ffffff !important; color: #000000 !important; } .receipt-box { box-shadow: none !important; border: none !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; } }`;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
