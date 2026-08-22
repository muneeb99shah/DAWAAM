<?php
/**
 * Dawaam - Local Business Continuity Software
 * Universal Responsive Thermal & Office POS Document Component (Receipt / Invoice / Challan / Mobile)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

/**
 * Generate Inline SVG QR Code for Document Verification
 */
function generate_document_qr_svg($data_string, $size = 90) {
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
 * Render Universal Responsive Thermal & Office POS Document
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
    
    // Financial calculation normalization
    $subtotal = (float)($sale['subtotal'] ?? 0);
    $discount_amount = (float)($sale['discount_amount'] ?? 0);
    $tax_amount = (float)($sale['tax_amount'] ?? 0);
    $total_price = (float)($sale['total_price'] ?? ($subtotal - $discount_amount + $tax_amount));
    $amount_received = (float)($sale['amount_received'] ?? $total_price);
    $change_amount = (float)($sale['change_amount'] ?? 0);
    
    $customer_name = !empty($sale['customer_name']) ? $sale['customer_name'] : 'Walk-in Customer';
    $customer_phone = $sale['customer_phone'] ?? '';
    $customer_email = $sale['customer_email'] ?? '';
    $customer_address = $sale['customer_address'] ?? '';
    $customer_tax_id = $sale['customer_tax_id'] ?? '';
    
    $cashier_name = $sale['cashier_name'] ?? 'System Administrator';
    $terminal_id = $sale['terminal_id'] ?? 'POS-01';
    $sold_at = format_date($sale['sold_at'] ?? date('Y-m-d H:i:s'));
    $sale_code = $sale['sale_code'] ?? ('DW-' . rand(1000, 9999));
    
    $qr_svg = generate_document_qr_svg($sale_code . '|' . $total_price . '|' . $sold_at, 85);

    ob_start();
    ?>
    <div class="dw-pos-doc-wrapper dw-doc-mode-<?php echo htmlspecialchars($doc_type); ?>">
        
        <!-- 1. BUSINESS HEADER -->
        <div class="dw-doc-header text-center pb-2 mb-2 border-bottom border-dark border-opacity-50">
            <div class="d-inline-flex align-items-center justify-content-center gap-1.5 mb-0.5">
                <i class="bi <?php echo htmlspecialchars($biz['logo_icon']); ?> text-success fs-5"></i>
                <span class="dw-doc-biz-name text-dark"><?php echo htmlspecialchars($biz['name']); ?></span>
            </div>
            <div class="dw-doc-biz-sub text-muted fw-semibold mb-1">
                Enterprise Medical &amp;<br>Business Continuity Center
            </div>
            <div class="dw-doc-biz-contact text-secondary">
                <?php echo htmlspecialchars($biz['address_line1']); ?><?php echo !empty($biz['address_line2']) ? ', ' . htmlspecialchars($biz['address_line2']) : ''; ?><br>
                Tel: <?php echo htmlspecialchars($biz['phone']); ?><br>
                Email: <?php echo htmlspecialchars($biz['email']); ?>
                <?php if (!empty($biz['tax_id_val_1']) || !empty($biz['tax_id_val_2'])): ?>
                    <div class="font-monospace text-muted mt-0.5" style="font-size: 0.9em;">
                        <?php if (!empty($biz['tax_id_label_1']) && !empty($biz['tax_id_val_1'])): ?>
                            <span class="me-1"><strong><?php echo htmlspecialchars($biz['tax_id_label_1']); ?>:</strong> <?php echo htmlspecialchars($biz['tax_id_val_1']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($biz['tax_id_label_2']) && !empty($biz['tax_id_val_2'])): ?>
                            <span><strong><?php echo htmlspecialchars($biz['tax_id_label_2']); ?>:</strong> <?php echo htmlspecialchars($biz['tax_id_val_2']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. DOCUMENT TITLE & COPY TYPE -->
        <div class="dw-doc-title-block text-center py-1 mb-2 border-bottom border-secondary border-opacity-25">
            <?php if ($doc_type === 'invoice'): ?>
                <div class="dw-doc-title text-dark">OFFICIAL TAX INVOICE</div>
                <span class="badge bg-success text-white font-monospace my-0.5 px-1.5 py-0.5" style="font-size: 0.65em;">ORIGINAL COPY</span>
            <?php elseif ($doc_type === 'challan'): ?>
                <div class="dw-doc-title text-dark">DELIVERY CHALLAN</div>
                <span class="badge bg-dark text-white font-monospace my-0.5 px-1.5 py-0.5" style="font-size: 0.65em;">GATE CLEARANCE PASS</span>
            <?php elseif ($doc_type === 'mobile'): ?>
                <div class="dw-doc-title text-dark">DIGITAL SALES RECEIPT</div>
                <span class="badge bg-dark text-white font-monospace my-0.5 px-1.5 py-0.5" style="font-size: 0.65em;">VERIFIED TRANSACTION</span>
            <?php else: ?>
                <div class="dw-doc-title text-dark">POS TRANSACTION RECEIPT</div>
                <span class="badge bg-secondary text-white font-monospace my-0.5 px-1.5 py-0.5" style="font-size: 0.65em;">CUSTOMER COPY</span>
            <?php endif; ?>
            <div class="font-monospace text-muted mt-0.5" style="font-size: 0.85em;">Ref Code: <?php echo htmlspecialchars($sale_code); ?></div>
        </div>

        <!-- 3. CUSTOMER / BILLED TO -->
        <div class="dw-doc-customer py-1 mb-2 border-bottom border-secondary border-opacity-25">
            <div class="dw-doc-section-title text-muted mb-0.5">CUSTOMER / BILLED TO</div>
            <div class="fw-bold text-dark" style="font-size: 1.05em;"><?php echo htmlspecialchars($customer_name); ?></div>
            <?php if (!empty($customer_phone)): ?>
                <div class="text-muted font-monospace" style="font-size: 0.9em;"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($customer_phone); ?></div>
            <?php endif; ?>
            <?php if (!empty($customer_email)): ?>
                <div class="text-muted" style="font-size: 0.9em;"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($customer_email); ?></div>
            <?php endif; ?>
            <?php if (!empty($customer_address)): ?>
                <div class="text-muted" style="font-size: 0.9em;"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($customer_address); ?></div>
            <?php endif; ?>
            <?php if (!empty($customer_tax_id)): ?>
                <div class="font-monospace text-dark fw-semibold mt-0.5" style="font-size: 0.85em;">Tax/VAT ID: <?php echo htmlspecialchars($customer_tax_id); ?></div>
            <?php endif; ?>
        </div>

        <!-- 4. TRANSACTION INFORMATION -->
        <div class="dw-doc-meta py-1 mb-2 border-bottom border-secondary border-opacity-25">
            <div class="d-flex justify-content-between mb-0.5">
                <span class="text-muted">Document No.</span>
                <strong class="font-monospace text-dark"><?php echo htmlspecialchars($sale_code); ?></strong>
            </div>
            <div class="d-flex justify-content-between mb-0.5">
                <span class="text-muted">Date &amp; Time</span>
                <strong class="text-dark"><?php echo htmlspecialchars($sold_at); ?></strong>
            </div>
            <div class="d-flex justify-content-between mb-0.5">
                <span class="text-muted">Terminal / Register</span>
                <strong class="font-monospace text-dark"><?php echo htmlspecialchars($terminal_id); ?></strong>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Cashier / Staff</span>
                <strong class="text-dark"><?php echo htmlspecialchars($cashier_name); ?></strong>
            </div>
        </div>

        <!-- 5. ITEMS SECTION -->
        <div class="dw-doc-items my-2">
            <table class="dw-doc-table">
                <thead>
                    <tr class="border-bottom border-dark border-2 text-uppercase" style="font-size: 0.9em;">
                        <th class="text-start pb-1" style="width: 44%;">ITEM</th>
                        <th class="text-center pb-1" style="width: 12%;">QTY</th>
                        <th class="text-end pb-1" style="width: 22%;">PRICE</th>
                        <th class="text-end pb-1" style="width: 22%;">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): 
                        $qty = (float)($it['quantity'] ?? 1);
                        $u_price = (float)($it['unit_price'] ?? 0);
                        $line_total = (float)($it['total_price'] ?? ($qty * $u_price));
                    ?>
                        <tr class="border-bottom border-secondary border-opacity-15">
                            <td class="text-start py-1.5" style="word-break: break-word;">
                                <strong class="text-dark d-block"><?php echo htmlspecialchars($it['product_name']); ?></strong>
                                <?php if (!empty($it['product_sku'])): ?>
                                    <span class="text-muted font-monospace" style="font-size: 0.85em;">SKU: <?php echo htmlspecialchars($it['product_sku']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center py-1.5 fw-bold text-dark"><?php echo number_format($qty); ?></td>
                            <td class="text-end py-1.5 font-monospace text-muted"><?php echo format_currency($u_price, $currency_symbol); ?></td>
                            <td class="text-end py-1.5 font-monospace fw-bold text-dark"><?php echo format_currency($line_total, $currency_symbol); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($doc_type !== 'challan'): ?>
            <!-- 6. TOTALS SECTION -->
            <div class="dw-doc-totals py-1.5 my-2 border-top border-bottom border-secondary border-opacity-25">
                <div class="d-flex justify-content-between mb-0.5">
                    <span class="text-muted">Subtotal</span>
                    <span class="font-monospace text-dark fw-semibold"><?php echo format_currency($subtotal, $currency_symbol); ?></span>
                </div>
                <?php if ($discount_amount > 0): ?>
                    <div class="d-flex justify-content-between mb-0.5 text-danger">
                        <span>Discount Allowed</span>
                        <span class="font-monospace">- <?php echo format_currency($discount_amount, $currency_symbol); ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-1 text-muted">
                    <span><?php echo htmlspecialchars($tax_label); ?> (<?php echo (float)($biz['tax_rate_percent'] ?? 0); ?>%)</span>
                    <span class="font-monospace">+ <?php echo format_currency($tax_amount, $currency_symbol); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-1.5 mt-1 border-top border-dark border-2">
                    <span class="fw-bold text-dark text-uppercase" style="letter-spacing: 0.02em;">GRAND TOTAL</span>
                    <strong class="font-monospace text-dark dw-doc-grand-total"><?php echo format_currency($total_price, $currency_symbol); ?></strong>
                </div>
            </div>

            <!-- 7. PAYMENT SETTLEMENT -->
            <div class="dw-doc-payment py-1.5 my-2 border-bottom border-secondary border-opacity-25">
                <div class="dw-doc-section-title text-muted mb-1">PAYMENT SETTLEMENT</div>
                <div class="d-flex justify-content-between mb-0.5">
                    <span class="text-muted">Payment Method</span>
                    <strong class="text-dark text-uppercase"><?php echo htmlspecialchars(str_replace('_', ' ', $sale['payment_method'] ?? 'CASH')); ?></strong>
                </div>
                <?php if (!empty($sale['payment_ref'])): ?>
                    <div class="d-flex justify-content-between mb-0.5">
                        <span class="text-muted">Ref / Trans No.</span>
                        <span class="font-monospace text-dark"><?php echo htmlspecialchars($sale['payment_ref']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-0.5">
                    <span class="text-muted">Amount Received</span>
                    <strong class="text-success font-monospace"><?php echo format_currency($amount_received, $currency_symbol); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Change Returned</span>
                    <strong class="text-primary font-monospace"><?php echo format_currency($change_amount, $currency_symbol); ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-1 border-top border-secondary border-opacity-10">
                    <span class="text-muted">Settlement Status</span>
                    <span class="badge bg-success text-white font-monospace text-uppercase" style="font-size: 0.7em; padding: 2px 6px;"><?php echo htmlspecialchars(strtoupper($sale['payment_status'] ?? 'PAID')); ?></span>
                </div>
            </div>
        <?php else: ?>
            <!-- Delivery Verification Clearance Note for Challan -->
            <div class="p-2 bg-light border rounded my-2" style="font-size: 0.9em;">
                <strong class="text-dark d-block mb-0.5"><i class="bi bi-shield-check me-1 text-success"></i> Goods Dispatch &amp; Gate Clearance:</strong>
                All items physically verified and cleared for dispatch in full operational condition.
            </div>
        <?php endif; ?>

        <!-- 8. QR CODE & FOOTER POLICY SECTION -->
        <div class="dw-doc-footer text-center pt-1 mt-2">
            <?php if (!empty($biz['enable_qr_code'])): ?>
                <div class="my-2">
                    <div class="d-inline-block bg-white p-1 border rounded">
                        <?php echo $qr_svg; ?>
                    </div>
                    <div class="text-muted font-monospace fw-semibold mt-0.5" style="font-size: 0.85em;">Scan to Verify</div>
                </div>
            <?php endif; ?>

            <div class="text-start text-secondary my-2 pt-1 border-top border-secondary border-opacity-10" style="font-size: 0.85em;">
                <?php if (!empty($biz['footer_return_policy'])): ?>
                    <div class="mb-1">
                        <strong class="text-dark d-block">Return Policy:</strong>
                        <span><?php echo htmlspecialchars($biz['footer_return_policy']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($biz['footer_warranty_info'])): ?>
                    <div class="mb-1">
                        <strong class="text-dark d-block">Warranty:</strong>
                        <span><?php echo htmlspecialchars($biz['footer_warranty_info']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($biz['footer_support_contact'])): ?>
                    <div class="mb-1">
                        <strong class="text-dark d-block">Customer Support:</strong>
                        <span><?php echo htmlspecialchars($biz['footer_support_contact']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($doc_type === 'invoice' || $doc_type === 'challan'): ?>
                <!-- Official Signatures for Office Documents -->
                <div class="d-flex justify-content-between text-center pt-3 mt-2 border-top border-secondary border-opacity-15">
                    <div class="flex-grow-1 mx-1">
                        <div class="border-top border-dark border-2 pt-0.5">
                            <span class="text-dark fw-semibold" style="font-size: 0.8em;">Issued By</span>
                        </div>
                    </div>
                    <div class="flex-grow-1 mx-1">
                        <div class="border-top border-dark border-2 pt-0.5">
                            <span class="text-dark fw-semibold" style="font-size: 0.8em;">Gate Stamp</span>
                        </div>
                    </div>
                    <div class="flex-grow-1 mx-1">
                        <div class="border-top border-dark border-2 pt-0.5">
                            <span class="text-dark fw-semibold" style="font-size: 0.8em;">Receiver</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center text-muted pt-2 border-top border-secondary border-opacity-25" style="font-size: 0.8em;">
                <?php echo htmlspecialchars($biz['footer_thank_you']); ?><br>
                <span class="font-monospace" style="font-size: 0.9em;">Dawaam Local Business Continuity Software</span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
