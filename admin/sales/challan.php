<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Official Delivery Challan / Goods Dispatch Note Printable Document Module
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
           s.payment_method, s.payment_status, s.sold_at,
           p.name AS product_name, p.sku AS product_sku,
           u.name AS cashier_name, u.user_code AS cashier_code
    FROM sales s
    INNER JOIN products p ON s.product_id = p.id
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    set_flash_message('danger', 'Delivery challan record not found.');
    redirect('admin/sales/index.php');
}

// Fetch line items from sale_items
$stmt_items = $pdo->prepare("
    SELECT si.quantity, p.name AS product_name, p.sku AS product_sku
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
        'quantity' => $sale['quantity']
    ]];
}

$page_title = "Delivery Challan " . sanitize($sale['sale_code']);
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Multi-Printer Paper Size & Print CSS Configuration -->
<style id="dw-challan-print-style">
@media print {
    @page {
        size: A5 landscape;
        margin: 8mm 10mm;
    }
    .dw-navbar, .dw-footer, .no-print, header, nav, footer {
        display: none !important;
    }
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-family: var(--dw-font-sans) !important;
    }
    .challan-box {
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
                        <i class="bi bi-truck text-dark me-1"></i> Delivery Challan Format Setup
                    </span>
                    <span class="small text-muted">Select physical challan format:</span>
                    <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Challan Paper Selector">
                        <button type="button" class="btn btn-outline-dark active dw-ch-paper-btn" onclick="setChallanPaperFormat('a5', this)">
                            Standard Challan A5 (Landscape)
                        </button>
                        <button type="button" class="btn btn-outline-dark dw-ch-paper-btn" onclick="setChallanPaperFormat('a4', this)">
                            Full Sheet Challan A4
                        </button>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="receipt.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-receipt me-1"></i> Receipt
                    </a>
                    <a href="invoice.php?id=<?php echo $sale['id']; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i> Tax Invoice
                    </a>
                    <button onclick="window.print();" class="btn btn-dw-primary btn-sm px-3">
                        <i class="bi bi-printer me-1"></i> Print Delivery Challan
                    </button>
                </div>
            </div>
        </div>

        <!-- Goods Delivery Challan Document Container -->
        <div id="dw-challan-container" class="dw-card p-4 bg-white shadow-sm challan-box paper-format-challan">
            <!-- Challan Header Section -->
            <div class="row align-items-center pb-3 mb-3 border-bottom border-2 border-dark">
                <div class="col-7">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-box-seam-fill text-success fs-3"></i>
                        <h4 class="fw-bold mb-0 text-dark" style="font-size: 1.15rem;"><?php echo APP_NAME; ?> DISPATCH & LOGISTICS</h4>
                    </div>
                    <div class="small text-muted fw-semibold" style="font-size: 0.8125rem;">Store Dispatch Gate & Central Warehouse &bull; Quetta Facility</div>
                </div>
                <div class="col-5 text-end">
                    <h3 class="fw-extrabold text-uppercase text-dark mb-0" style="font-size: 1.25rem; letter-spacing: 0.03em;">DELIVERY CHALLAN</h3>
                    <span class="small font-monospace text-muted" style="font-size: 0.75rem;">Gate Pass No: GP-<?php echo sanitize($sale['id']); ?></span>
                </div>
            </div>

            <!-- Challan Grid Info -->
            <div class="row mb-3 small" style="font-size: 0.8125rem;">
                <div class="col-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <span class="text-muted d-block fw-bold small text-uppercase mb-1" style="font-size: 0.7rem;">Consignee / Recipient:</span>
                        <strong class="text-dark d-block" style="font-size: 0.9rem;"><?php echo sanitize($sale['customer_name'] ?? 'Walk-in Customer'); ?></strong>
                        <?php if (!empty($sale['customer_phone'])): ?>
                            <span class="d-block font-monospace text-muted" style="font-size: 0.75rem;"><i class="bi bi-telephone me-1"></i> <?php echo sanitize($sale['customer_phone']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Challan No:</span>
                            <strong class="font-monospace text-dark">CH-<?php echo sanitize($sale['sale_code']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Dispatch Date:</span>
                            <strong class="text-dark"><?php echo format_date($sale['sold_at']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Dispatcher Staff:</span>
                            <strong class="text-dark"><?php echo sanitize($sale['cashier_name']); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Goods Item Table -->
            <table class="table table-bordered table-sm align-middle mb-4" style="table-layout: fixed; width: 100%;">
                <thead class="table-dark text-uppercase small" style="font-size: 0.75rem;">
                    <tr>
                        <th class="text-center" style="width: 8%;">S.No</th>
                        <th style="width: 52%;">Dispatched Item</th>
                        <th style="width: 20%;">Product Code / SKU</th>
                        <th class="text-center" style="width: 20%;">Qty Delivered</th>
                    </tr>
                </thead>
                <tbody class="small" style="font-size: 0.8125rem;">
                    <?php $sno = 1; foreach ($items as $it): ?>
                        <tr>
                            <td class="text-center font-monospace" style="font-size: 0.75rem;"><?php echo $sno++; ?></td>
                            <td class="fw-semibold text-dark" style="word-break: break-word; font-size: 0.8125rem;"><?php echo sanitize($it['product_name']); ?></td>
                            <td class="font-monospace text-muted" style="font-size: 0.75rem;"><?php echo sanitize($it['product_sku']); ?></td>
                            <td class="text-center fw-semibold text-dark" style="font-size: 0.8125rem;"><?php echo number_format($it['quantity']); ?> units</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Physical Verification Note -->
            <div class="p-2 bg-light rounded border mb-4 small" style="font-size: 0.75rem;">
                <strong class="text-dark">Delivery Verification:</strong> Received the above dispatched items in complete order and verified condition. Gate clearance granted.
            </div>

            <!-- Signatures & Verification Row -->
            <div class="row pt-4 text-center print-avoid-break">
                <div class="col-4">
                    <div class="border-top border-dark border-2 pt-2 mt-4 mx-2">
                        <span class="small text-muted d-block fw-bold" style="font-size: 0.75rem;">Store Keeper / Dispatcher</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border-top border-dark border-2 pt-2 mt-4 mx-2">
                        <span class="small text-muted d-block fw-bold" style="font-size: 0.75rem;">Security Gate Stamp</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border-top border-dark border-2 pt-2 mt-4 mx-2">
                        <span class="small text-muted d-block fw-bold" style="font-size: 0.75rem;">Receiver's Signature & Date</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setChallanPaperFormat(format, btn) {
    const container = document.getElementById('dw-challan-container');
    const styleEl = document.getElementById('dw-challan-print-style');
    
    document.querySelectorAll('.dw-ch-paper-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    let pageCss = (format === 'a4') ? '@page { size: A4 portrait; margin: 12mm 15mm; }' : '@page { size: A5 landscape; margin: 8mm 10mm; }';
    styleEl.innerHTML = `@media print { ${pageCss} .dw-navbar, .dw-footer, .no-print, header, nav, footer { display: none !important; } body { background-color: #ffffff !important; color: #000000 !important; } .challan-box { box-shadow: none !important; border: none !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; } }`;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
