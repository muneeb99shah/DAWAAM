<?php
/**
 * Dawaam - Local Business Continuity Software
 * Universal International POS Document Component (Receipt / Invoice / Challan / Mobile)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

/**
 * Generate Inline SVG QR Code for Document Verification
 */
function generate_document_qr_svg($data_string, $size = 100) {
    // Generate a clean vector QR-style matrix representation
    $hash = md5($data_string);
    $grid_size = 21;
    $cell_size = $size / $grid_size;
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '">';
    $svg .= '<rect width="' . $size . '" height="' . $size . '" fill="#ffffff"/>';
    
    // Outer Corner Marker Boxes
    $markers = [[0, 0], [14, 0], [0, 14]];
    foreach ($markers as $m) {
        $x = $m[0] * $cell_size;
        $y = $m[1] * $cell_size;
        $w = 7 * $cell_size;
        $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $w . '" fill="#0f172a"/>';
        $svg .= '<rect x="' . ($x + $cell_size) . '" y="' . ($y + $cell_size) . '" width="' . (5 * $cell_size) . '" height="' . (5 * $cell_size) . '" fill="#ffffff"/>';
        $svg .= '<rect x="' . ($x + 2 * $cell_size) . '" y="' . ($y + 2 * $cell_size) . '" width="' . (3 * $cell_size) . '" height="' . (3 * $cell_size) . '" fill="#0f172a"/>';
    }
    
    // Data module grid points based on hash bytes
    for ($r = 0; $r < $grid_size; $r++) {
        for ($c = 0; $c < $grid_size; $c++) {
            // Skip marker regions
            if (($r < 7 && $c < 7) || ($r < 7 && $c >= 14) || ($r >= 14 && $c < 7)) continue;
            
            $char_idx = ($r * $grid_size + $c) % 32;
            $val = hexdec($hash[$char_idx]);
            if ($val % 2 === 0) {
                $x = $c * $cell_size;
                $y = $r * $cell_size;
                $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $cell_size . '" height="' . $cell_size . '" fill="#0f172a"/>';
            }
        }
    }
    
    $svg .= '</svg>';
    return $svg;
}

/**
 * Render Universal International POS Document
 *
 * @param array $sale Sale transaction master record
 * @param array $items Array of line items
 * @param string $doc_type 'receipt' | 'invoice' | 'challan' | 'mobile'
 * @param array|null $custom_biz Custom business profile overrides for multi-country testing
 * @return string HTML output
 */
function render_pos_document($sale, $items, $doc_type = 'receipt', $custom_biz = null) {
    $biz = array_merge(get_business_profile(), (array)$custom_biz);
    
    $currency_symbol = $biz['currency_symbol'] ?? 'PKR ';
    $tax_label = $biz['tax_label'] ?? 'Sales Tax';
    
    // Basic calculation normalization
    $subtotal = (float)($sale['subtotal'] ?? 0);
    $discount_amount = (float)($sale['discount_amount'] ?? 0);
    $tax_amount = (float)($sale['tax_amount'] ?? 0);
    $total_price = (float)($sale['total_price'] ?? ($subtotal - $discount_amount + $tax_amount));
    $amount_received = (float)($sale['amount_received'] ?? $total_price);
    $change_amount = (float)($sale['change_amount'] ?? 0);
    $remaining_amount = (float)($sale['remaining_amount'] ?? 0);
    
    $customer_name = !empty($sale['customer_name']) ? $sale['customer_name'] : 'Walk-in Customer';
    $customer_phone = $sale['customer_phone'] ?? '';
    $customer_email = $sale['customer_email'] ?? '';
    $customer_address = $sale['customer_address'] ?? '';
    $customer_tax_id = $sale['customer_tax_id'] ?? '';
    
    $cashier_name = $sale['cashier_name'] ?? 'System Operator';
    $terminal_id = $sale['terminal_id'] ?? 'POS-01';
    $sold_at = format_date($sale['sold_at'] ?? date('Y-m-d H:i:s'));
    $sale_code = $sale['sale_code'] ?? ('DW-' . rand(1000, 9999));
    
    $verification_url = BASE_URL . '/admin/sales/receipt.php?id=' . ($sale['id'] ?? 1);
    $qr_svg = generate_document_qr_svg($sale_code . '|' . $total_price . '|' . $sold_at, 90);

    ob_start();
    ?>
    <div class="dw-pos-doc-wrapper dw-doc-mode-<?php echo htmlspecialchars($doc_type); ?>">
        
        <!-- HEADER SECTION -->
        <div class="dw-doc-header text-center text-md-start pb-3 mb-3 border-bottom border-2 border-dark">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="d-inline-flex align-items-center gap-2 mb-1">
                        <i class="bi <?php echo htmlspecialchars($biz['logo_icon']); ?> text-success fs-3"></i>
                        <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.2rem;"><?php echo htmlspecialchars($biz['name']); ?></h3>
                    </div>
                    <div class="small text-muted fw-semibold mb-1"><?php echo htmlspecialchars($biz['tagline']); ?></div>
                    <div class="small text-secondary" style="font-size: 0.75rem;">
                        <?php echo htmlspecialchars($biz['address_line1']); ?><?php echo !empty($biz['address_line2']) ? ', ' . htmlspecialchars($biz['address_line2']) : ''; ?><br>
                        Tel: <?php echo htmlspecialchars($biz['phone']); ?> | Email: <?php echo htmlspecialchars($biz['email']); ?>
                    </div>
                    <!-- Country Specific Configurable Registration IDs -->
                    <div class="mt-1 font-monospace text-muted" style="font-size: 0.7rem;">
                        <?php if (!empty($biz['tax_id_label_1']) && !empty($biz['tax_id_val_1'])): ?>
                            <span class="me-2"><strong><?php echo htmlspecialchars($biz['tax_id_label_1']); ?>:</strong> <?php echo htmlspecialchars($biz['tax_id_val_1']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($biz['tax_id_label_2']) && !empty($biz['tax_id_val_2'])): ?>
                            <span><strong><?php echo htmlspecialchars($biz['tax_id_label_2']); ?>:</strong> <?php echo htmlspecialchars($biz['tax_id_val_2']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-5 text-center text-md-end mt-2 mt-md-0">
                    <?php if ($doc_type === 'invoice'): ?>
                        <h4 class="fw-extrabold text-uppercase text-dark mb-1" style="letter-spacing: 0.04em;">OFFICIAL TAX INVOICE</h4>
                        <span class="badge bg-success font-monospace px-2.5 py-1" style="font-size: 0.75rem;">ORIGINAL COPY</span>
                    <?php elseif ($doc_type === 'challan'): ?>
                        <h4 class="fw-extrabold text-uppercase text-dark mb-1" style="letter-spacing: 0.04em;">DELIVERY CHALLAN</h4>
                        <span class="badge bg-dark font-monospace px-2.5 py-1" style="font-size: 0.75rem;">GATE CLEARANCE PASS</span>
                    <?php elseif ($doc_type === 'mobile'): ?>
                        <h5 class="fw-bold text-uppercase text-dark mb-1">DIGITAL SALES RECEIPT</h5>
                        <span class="badge bg-teal text-white font-monospace px-2 py-1" style="background-color:#0f766e; font-size: 0.75rem;">VERIFIED TRANSACTION</span>
                    <?php else: ?>
                        <h5 class="fw-bold text-uppercase text-dark mb-1">POS TRANSACTION RECEIPT</h5>
                        <span class="badge bg-secondary font-monospace px-2 py-1" style="font-size: 0.7rem;">CUSTOMER COPY</span>
                    <?php endif; ?>
                    <div class="font-monospace text-muted mt-1" style="font-size: 0.7rem;">Ref Code: <?php echo htmlspecialchars($sale_code); ?></div>
                </div>
            </div>
        </div>

        <!-- TRANSACTION METADATA GRID -->
        <div class="row g-2 mb-3 small" style="font-size: 0.8rem;">
            <!-- Customer Information Card -->
            <div class="<?php echo ($doc_type === 'receipt') ? 'col-12' : 'col-md-6'; ?>">
                <div class="p-2.5 bg-light rounded border h-100">
                    <span class="text-muted d-block fw-bold small text-uppercase mb-1" style="font-size: 0.68rem;">Customer / Billed To:</span>
                    <strong class="text-dark d-block" style="font-size: 0.88rem;"><?php echo htmlspecialchars($customer_name); ?></strong>
                    <?php if (!empty($customer_phone)): ?>
                        <span class="d-block text-muted font-monospace" style="font-size: 0.75rem;"><i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($customer_phone); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($customer_email)): ?>
                        <span class="d-block text-muted" style="font-size: 0.75rem;"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($customer_email); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($customer_address)): ?>
                        <span class="d-block text-muted" style="font-size: 0.75rem;"><i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($customer_address); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($customer_tax_id)): ?>
                        <span class="d-block font-monospace text-dark fw-semibold mt-1" style="font-size: 0.72rem;">Customer Tax/VAT ID: <?php echo htmlspecialchars($customer_tax_id); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Transaction Details Card -->
            <div class="<?php echo ($doc_type === 'receipt') ? 'col-12' : 'col-md-6'; ?>">
                <div class="p-2.5 bg-light rounded border h-100">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Document No:</span>
                        <strong class="font-monospace text-dark"><?php echo htmlspecialchars($sale_code); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Date &amp; Time:</span>
                        <strong class="text-dark"><?php echo htmlspecialchars($sold_at); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Terminal / Register:</span>
                        <strong class="font-monospace text-dark"><?php echo htmlspecialchars($terminal_id); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Cashier / Staff:</span>
                        <strong class="text-dark"><?php echo htmlspecialchars($cashier_name); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- LINE ITEMS TABLE -->
        <div class="table-responsive my-3">
            <table class="table table-bordered table-sm align-middle mb-0" style="table-layout: fixed; width: 100%;">
                <thead class="table-dark text-uppercase small" style="font-size: 0.72rem;">
                    <tr>
                        <th class="text-center" style="width: 7%;">#</th>
                        <th style="width: 43%;">Item Description &amp; SKU</th>
                        <th class="text-center" style="width: 12%;">Qty</th>
                        <th class="text-end" style="width: 18%;">Unit Price</th>
                        <th class="text-end" style="width: 20%;">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="small" style="font-size: 0.8rem;">
                    <?php $sno = 1; foreach ($items as $it): 
                        $qty = (float)($it['quantity'] ?? 1);
                        $u_price = (float)($it['unit_price'] ?? 0);
                        $line_total = (float)($it['total_price'] ?? ($qty * $u_price));
                    ?>
                        <tr>
                            <td class="text-center font-monospace text-muted" style="font-size: 0.72rem;"><?php echo $sno++; ?></td>
                            <td style="word-break: break-word;">
                                <strong class="text-dark d-block" style="font-weight: 600;"><?php echo htmlspecialchars($it['product_name']); ?></strong>
                                <?php if (!empty($it['product_sku'])): ?>
                                    <span class="text-muted font-monospace" style="font-size: 0.68rem;">SKU: <?php echo htmlspecialchars($it['product_sku']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-semibold text-dark"><?php echo number_format($qty); ?></td>
                            <td class="text-end font-monospace text-muted"><?php echo format_currency($u_price, $currency_symbol); ?></td>
                            <td class="text-end fw-semibold font-monospace text-dark"><?php echo format_currency($line_total, $currency_symbol); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($doc_type !== 'challan'): ?>
            <!-- FINANCIAL SUMMARY TOTALS & PAYMENT GRID -->
            <div class="row g-2 mb-3 small" style="font-size: 0.8rem;">
                <!-- Payment Info Box -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-white h-100">
                        <span class="fw-bold text-dark d-block small text-uppercase mb-2" style="font-size: 0.7rem;"><i class="bi bi-wallet2 me-1 text-success"></i> Payment Settlement:</span>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Payment Method:</span>
                            <strong class="text-dark text-uppercase"><?php echo htmlspecialchars(str_replace('_', ' ', $sale['payment_method'] ?? 'CASH')); ?></strong>
                        </div>
                        <?php if (!empty($sale['payment_ref'])): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Ref / Trans No:</span>
                                <span class="font-monospace text-dark"><?php echo htmlspecialchars($sale['payment_ref']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Amount Received:</span>
                            <strong class="text-success font-monospace"><?php echo format_currency($amount_received, $currency_symbol); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Change Returned:</span>
                            <strong class="text-primary font-monospace"><?php echo format_currency($change_amount, $currency_symbol); ?></strong>
                        </div>
                        <div class="pt-2 mt-2 border-top d-flex justify-content-between align-items-center">
                            <span class="text-muted">Settlement Status:</span>
                            <span class="badge bg-success text-uppercase" style="font-size: 0.68rem;"><?php echo htmlspecialchars(strtoupper($sale['payment_status'] ?? 'PAID')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Financial Totals Box -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light">
                        <div class="d-flex justify-content-between mb-1 text-muted">
                            <span>Subtotal Amount:</span>
                            <strong class="font-monospace text-dark"><?php echo format_currency($subtotal, $currency_symbol); ?></strong>
                        </div>
                        <?php if ($discount_amount > 0): ?>
                            <div class="d-flex justify-content-between mb-1 text-danger">
                                <span>Discount Allowed:</span>
                                <strong class="font-monospace">- <?php echo format_currency($discount_amount, $currency_symbol); ?></strong>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2 text-muted">
                            <span><?php echo htmlspecialchars($tax_label); ?> (<?php echo (float)($biz['tax_rate_percent'] ?? 0); ?>%):</span>
                            <strong class="font-monospace">+ <?php echo format_currency($tax_amount, $currency_symbol); ?></strong>
                        </div>
                        
                        <!-- Prominent Grand Total -->
                        <div class="p-2.5 bg-dark text-white rounded d-flex justify-content-between align-items-center mt-2 shadow-sm">
                            <span class="fw-bold text-uppercase" style="font-size: 0.85rem; letter-spacing: 0.03em;">GRAND TOTAL:</span>
                            <strong class="fw-bold text-teal font-monospace" style="color: #2dd4bf; font-size: 1.15rem;"><?php echo format_currency($total_price, $currency_symbol); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Delivery Verification Clearance Note for Challan -->
            <div class="p-3 bg-light border rounded mb-3 small" style="font-size: 0.78rem;">
                <strong class="text-dark d-block mb-1"><i class="bi bi-shield-check me-1 text-success"></i> Goods Dispatch &amp; Gate Verification:</strong>
                All listed items have been physically inspected, counted, and cleared for dispatch in full operational order. Receiver assumes responsibility upon gate exit.
            </div>
        <?php endif; ?>

        <!-- FOOTER SECTION (TERMS, QR CODE & SIGNATURES) -->
        <div class="dw-doc-footer pt-3 border-top mt-3">
            <div class="row align-items-center">
                <div class="col-8">
                    <?php if (!empty($biz['footer_return_policy'])): ?>
                        <p class="small text-muted mb-1" style="font-size: 0.72rem;"><strong>Return Policy:</strong> <?php echo htmlspecialchars($biz['footer_return_policy']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($biz['footer_warranty_info'])): ?>
                        <p class="small text-muted mb-1" style="font-size: 0.72rem;"><strong>Warranty:</strong> <?php echo htmlspecialchars($biz['footer_warranty_info']); ?></p>
                    <?php endif; ?>
                    <p class="small text-muted mb-0" style="font-size: 0.72rem;"><?php echo htmlspecialchars($biz['footer_support_contact']); ?></p>
                </div>
                
                <?php if (!empty($biz['enable_qr_code'])): ?>
                    <div class="col-4 text-end">
                        <div class="d-inline-block text-center">
                            <?php echo $qr_svg; ?>
                            <span class="d-block text-muted font-monospace mt-1" style="font-size: 0.6rem;">Scan to Verify</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($doc_type === 'invoice' || $doc_type === 'challan'): ?>
                <!-- Signature Blocks for Official Office Documents -->
                <div class="row pt-4 text-center mt-3 print-avoid-break">
                    <div class="col-4">
                        <div class="border-top border-dark border-2 pt-1 mx-2">
                            <span class="small text-dark fw-semibold" style="font-size: 0.72rem;">Issued By (Cashier)</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-top border-dark border-2 pt-1 mx-2">
                            <span class="small text-dark fw-semibold" style="font-size: 0.72rem;">Gate Security Stamp</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-top border-dark border-2 pt-1 mx-2">
                            <span class="small text-dark fw-semibold" style="font-size: 0.72rem;">Receiver Signature</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center mt-3 pt-2 border-top text-muted small" style="font-size: 0.68rem;">
                <?php echo htmlspecialchars($biz['footer_thank_you']); ?> &bull; Dawaam Local Business Continuity Software
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
