<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Official Tax Invoice Printable Document Module (A4 / A5 / Thermal)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pos_document_component.php';

require_permission('sales.view');

$sale_id = (int)($_GET['id'] ?? 0);
if ($sale_id <= 0) {
    set_flash_message('danger', 'Invalid sale ID specified.');
    redirect('admin/sales/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT s.id, s.sale_code, s.customer_name, s.customer_phone, s.customer_email, s.customer_address, s.customer_tax_id,
           s.quantity, s.unit_price, s.subtotal,
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
    html, body {
        background-color: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .dw-navbar, .dw-footer, .no-print, header, nav, footer, .dw-sidebar-outer-wrap, #dwMobileNavDrawer, .dw-mobile-bottom-bar {
        display: none !important;
    }
    .dw-doc-card-container {
        box-shadow: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<div class="row justify-content-center mb-5">
    <div class="col-12 col-xl-10">
        <!-- Action Toolbar & Printer Paper Size Selector -->
        <div class="card border-0 shadow-sm p-3 mb-4 bg-white no-print">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-bold text-dark d-block mb-1">
                        <i class="bi bi-file-earmark-text text-primary me-1"></i> Tax Invoice Format &amp; Paper Size
                    </span>
                    <span class="small text-muted">Select office paper size:</span>
                    <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Paper Format Selector">
                        <button type="button" class="btn btn-outline-primary active dw-inv-paper-btn" onclick="setInvoicePaperFormat('a4', this)">
                            Standard A4
                        </button>
                        <button type="button" class="btn btn-outline-primary dw-inv-paper-btn" onclick="setInvoicePaperFormat('a5', this)">
                            Compact A5
                        </button>
                        <button type="button" class="btn btn-outline-success dw-inv-paper-btn" onclick="setInvoicePaperFormat('80mm', this)">
                            Thermal 80mm
                        </button>
                        <button type="button" class="btn btn-outline-success dw-inv-paper-btn" onclick="setInvoicePaperFormat('58mm', this)">
                            Thermal 58mm
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
        <div id="dw-invoice-container" class="dw-doc-card-container paper-format-a4">
            <?php echo render_pos_document($sale, $items, 'invoice'); ?>
        </div>
    </div>
</div>

<script>
function setInvoicePaperFormat(format, btn) {
    const container = document.getElementById('dw-invoice-container');
    const styleEl = document.getElementById('dw-invoice-print-style');
    
    document.querySelectorAll('.dw-inv-paper-btn').forEach(b => b.classList.remove('active', 'btn-primary', 'btn-success'));
    btn.classList.add('active', format.includes('mm') ? 'btn-success' : 'btn-primary');

    container.className = 'dw-doc-card-container paper-format-' + format;

    let pageCss = '';
    if (format === '80mm') {
        pageCss = '@page { size: 80mm auto; margin: 0; }';
    } else if (format === '58mm') {
        pageCss = '@page { size: 58mm auto; margin: 0; }';
    } else if (format === 'a5') {
        pageCss = '@page { size: A5 portrait; margin: 8mm 10mm; }';
    } else {
        pageCss = '@page { size: A4 portrait; margin: 12mm 15mm; }';
    }

    styleEl.innerHTML = `@media print { ${pageCss} html, body { background-color: #ffffff !important; color: #000000 !important; margin: 0 !important; padding: 0 !important; } .dw-navbar, .dw-footer, .no-print, header, nav, footer, .dw-sidebar-outer-wrap, #dwMobileNavDrawer, .dw-mobile-bottom-bar { display: none !important; } .dw-doc-card-container { box-shadow: none !important; border: none !important; border-radius: 0 !important; padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; } }`;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
