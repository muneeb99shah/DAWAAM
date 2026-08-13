<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Official Tax Invoice Printable Document Module (A4 / A5 Office Printers)
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
    set_flash_message('danger', 'Tax invoice record not found.');
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

$page_title = "Tax Invoice " . sanitize($sale['sale_code']);
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Multi-Printer Paper Size & Print CSS Configuration -->
<style id="dw-invoice-print-style">
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm 15mm;
    }
    .dw-navbar, .dw-footer, .no-print, header, nav, footer {
        display: none !important;
    }
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-family: var(--dw-font-sans) !important;
    }
    .invoice-box {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<div class="row justify-content-center mb-5">
    <div class="col-md-10 col-lg-9">
        <!-- Action Toolbar & Printer Paper Size Selector -->
        <div class="card border-0 shadow-sm p-3 mb-3 bg-white no-print">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-bold text-dark d-block mb-1">
                        <i class="bi bi-file-earmark-text text-primary me-1"></i> Tax Invoice & Paper Format
                    </span>
                    <span class="small text-muted">Select office paper size:</span>
                    <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Paper Format Selector">
                        <button type="button" class="btn btn-outline-primary active dw-inv-paper-btn" onclick="setInvoicePaperFormat('a4', this)">
                            Standard A4 (210×297mm)
                        </button>
                        <button type="button" class="btn btn-outline-primary dw-inv-paper-btn" onclick="setInvoicePaperFormat('a5', this)">
                            Compact A5 (148×210mm)
                        </button>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="receipt.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-receipt me-1"></i> Thermal Receipt
                    </a>
                    <a href="challan.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-truck me-1"></i> Delivery Challan
                    </a>
                    <button onclick="window.print();" class="btn btn-dw-primary btn-sm px-3">
                        <i class="bi bi-printer me-1"></i> Print Invoice
                    </button>
                </div>
            </div>
        </div>

        <!-- Official Tax Invoice Document Container -->
        <div class="table-responsive bg-white rounded-3 shadow-sm border-0">
            <div id="dw-invoice-container" class="dw-card p-4 bg-white invoice-box paper-format-a4">
                <!-- Header Section -->
                <div class="row align-items-center pb-3 mb-3 border-bottom border-2 border-dark">
                    <div class="col-7">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-shield-check text-success fs-3"></i>
                            <h4 class="fw-bold mb-0 text-dark" style="font-size: 1.15rem;"><?php echo APP_NAME; ?> PHARMACEUTICALS</h4>
                        </div>
                        <div class="small text-dark fw-semibold" style="font-size: 0.8125rem;">Quetta Medical & Continuity Center &bull; NTN: 8492041-7 &bull; STRN: 3277876123490</div>
                        <div class="small text-muted" style="font-size: 0.75rem;">MA Jinnah Road, Quetta, Balochistan, Pakistan | Tel: +92 (81) 283-9102</div>
                    </div>
                    <div class="col-5 text-end">
                        <h3 class="fw-extrabold text-uppercase text-dark mb-1" style="font-size: 1.25rem; letter-spacing: 0.03em;">OFFICIAL TAX INVOICE</h3>
                        <span class="badge bg-success font-monospace px-2 py-1" style="font-size: 0.75rem;">ORIGINAL</span>
                    </div>
                </div>

                <!-- Invoice Metadata Grid -->
                <div class="row mb-3 small" style="font-size: 0.8125rem;">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded border h-100">
                            <span class="text-muted d-block fw-bold small text-uppercase mb-1" style="font-size: 0.7rem;">Billed To (Customer Details):</span>
                            <strong class="text-dark d-block" style="font-size: 0.9rem;"><?php echo sanitize($sale['customer_name'] ?? 'Walk-in Customer'); ?></strong>
                            <?php if (!empty($sale['customer_phone'])): ?>
                                <span class="d-block font-monospace text-muted" style="font-size: 0.75rem;"><i class="bi bi-telephone me-1"></i> <?php echo sanitize($sale['customer_phone']); ?></span>
                            <?php endif; ?>
                            <span class="d-block text-muted" style="font-size: 0.75rem;">Quetta Local Retail / Institutional Purchase</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Invoice No:</span>
                                <strong class="font-monospace text-dark">INV-<?php echo sanitize($sale['sale_code']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Invoice Date:</span>
                                <strong class="text-dark"><?php echo format_date($sale['sold_at']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Payment Terms:</span>
                                <strong class="text-uppercase text-success"><?php echo str_replace('_', ' ', $sale['payment_method']); ?> (PAID)</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Issued By Cashier:</span>
                                <strong class="text-dark"><?php echo sanitize($sale['cashier_name']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items Table -->
                <table class="table table-bordered table-striped align-middle mb-4" style="table-layout: fixed; width: 100%;">
                    <thead class="table-dark text-uppercase small" style="font-size: 0.75rem;">
                        <tr>
                            <th class="text-center" style="width: 6%;">#</th>
                            <th style="width: 44%;">Item Name & SKU</th>
                            <th class="text-center" style="width: 12%;">Qty</th>
                            <th class="text-end" style="width: 18%;">Unit Price</th>
                            <th class="text-end" style="width: 20%;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody class="small" style="font-size: 0.8125rem;">
                        <?php $idx = 1; foreach ($items as $it): ?>
                            <tr>
                                <td class="text-center font-monospace" style="font-size: 0.75rem;"><?php echo $idx++; ?></td>
                                <td style="word-break: break-word;">
                                    <strong class="text-dark d-block" style="font-size: 0.8125rem; font-weight: 600;"><?php echo sanitize($it['product_name']); ?></strong>
                                    <span class="text-muted font-monospace" style="font-size: 0.7rem;">SKU: <?php echo sanitize($it['product_sku']); ?></span>
                                </td>
                                <td class="text-center fw-semibold text-dark"><?php echo number_format($it['quantity']); ?></td>
                                <td class="text-end font-monospace text-muted"><?php echo format_currency($it['unit_price']); ?></td>
                                <td class="text-end fw-semibold font-monospace text-dark"><?php echo format_currency($it['total_price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Financial Summary Box -->
                <div class="row mb-4 small" style="font-size: 0.8125rem;">
                    <div class="col-6">
                        <div class="p-3 border rounded h-100 bg-light">
                            <span class="fw-bold text-dark d-block small mb-1" style="font-size: 0.75rem;">Payment & Tax Declaration:</span>
                            <p class="text-muted mb-2" style="font-size: 0.75rem;">
                                This tax invoice is issued electronically under the Dawaam Local Business Continuity Framework. All prices include applicable Sales Tax.
                            </p>
                            <span class="badge bg-outline-dark border text-dark font-monospace" style="font-size: 0.7rem;">STATUS: FULLY SETTLED</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-white">
                            <div class="d-flex justify-content-between mb-1 text-muted">
                                <span>Subtotal Amount:</span>
                                <strong class="font-monospace text-dark"><?php echo format_currency($sale['subtotal']); ?></strong>
                            </div>
                            <?php if ($sale['discount_amount'] > 0): ?>
                                <div class="d-flex justify-content-between mb-1 text-danger">
                                    <span>Discount Allowed:</span>
                                    <strong class="font-monospace">- <?php echo format_currency($sale['discount_amount']); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between mb-2 text-muted">
                                <span>Sales Tax (0% Exempt):</span>
                                <strong class="font-monospace">PKR 0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top border-2 border-dark">
                                <span class="fw-bold text-dark" style="font-size: 0.95rem;">NET GRAND TOTAL:</span>
                                <span class="fw-bold text-success font-monospace" style="font-size: 1.1rem;"><?php echo format_currency($sale['total_price']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signatures & Authorization Box -->
                <div class="row pt-4 text-center print-avoid-break">
                    <div class="col-4">
                        <div class="border-top border-dark border-2 pt-2 mt-4 mx-3">
                            <span class="small text-muted d-block fw-bold" style="font-size: 0.75rem;">Customer Signature</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-top border-dark border-2 pt-2 mt-4 mx-3">
                            <span class="small text-muted d-block fw-bold" style="font-size: 0.75rem;">Store Stamp & Security Gate</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-top border-dark border-2 pt-2 mt-4 mx-3">
                            <span class="small text-muted d-block fw-bold" style="font-size: 0.75rem;">Authorized Signatory</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setInvoicePaperFormat(format, btn) {
    const container = document.getElementById('dw-invoice-container');
    const styleEl = document.getElementById('dw-invoice-print-style');
    
    document.querySelectorAll('.dw-inv-paper-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    container.classList.remove('paper-format-a4', 'paper-format-a5');
    container.classList.add('paper-format-' + format);

    let pageCss = (format === 'a5') ? '@page { size: A5 portrait; margin: 8mm 10mm; }' : '@page { size: A4 portrait; margin: 12mm 15mm; }';
    styleEl.innerHTML = `@media print { ${pageCss} .dw-navbar, .dw-footer, .no-print, header, nav, footer { display: none !important; } body { background-color: #ffffff !important; color: #000000 !important; } .invoice-box { box-shadow: none !important; border: none !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; } }`;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
