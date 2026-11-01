<?php
declare(strict_types=1);

/**
 * MediTrack GST Tax Invoice PDF Template
 * 
 * Available variables:
 * - $invoice (array): ['invoice_no' => '...', 'issued_at' => '...']
 * - $order (array): ['order_no' => '...', 'total' => 0.00, 'subtotal' => 0.00, 'tax_total' => 0.00, 'delivery_fee' => 0.00, 'payment_method' => '...', 'created_at' => '...']
 * - $items (array): list of items with batch_no, exp_date, mrp, unit_price, quantity, gst_rate, line_total
 * - $settings (array): ['shop_name' => '...', 'owner_name' => '...', 'gst_no' => '...', 'drug_license_no' => '...', ...]
 * - $customer (array): ['name' => '...', 'phone' => '...', 'email' => '...']
 * - $address (array): ['full_name' => '...', 'address_line1' => '...', 'city' => '...', 'state' => '...', 'pincode' => '...']
 * - $pharmacist (array|null): ['name' => '...', 'reg_no' => '...']
 * - $isInterState (bool): true for IGST, false for CGST+SGST
 */

$brandName = brand('app.name', 'MediTrack');
$primaryColor = brand('colors.primary.600', '#059669');
$shopName = $settings['shop_name'] ?? 'Sharma Medical Store';
$ownerName = $settings['owner_name'] ?? 'Ramesh Sharma';
$shopGst = $settings['gst_no'] ?? '07AAAAA0000A1Z5';
$drugLicense = $settings['drug_license_no'] ?? 'DL-2026-MED-9941';
$supportEmail = brand('app.supportEmail', 'support@meditrack.example');
$supportPhone = brand('app.supportPhone', '+91 98765 43210');

$invoiceNo = $invoice['invoice_no'] ?? 'INV-XXXX';
$issuedAt = isset($invoice['issued_at']) ? date('d M Y, h:i A', strtotime($invoice['issued_at'])) : date('d M Y, h:i A');
$orderNo = $order['order_no'] ?? '';
$paymentMethod = strtoupper((string)($order['payment_method'] ?? 'COD'));
if ($paymentMethod === 'COD') {
    $paymentMethodLabel = 'Cash on Delivery (COD)';
} elseif (in_array(strtolower($paymentMethod), ['razorpay', 'upi', 'card', 'netbanking'])) {
    $paymentMethodLabel = 'Online (' . strtoupper($paymentMethod) . ') - Paid';
} else {
    $paymentMethodLabel = $paymentMethod;
}

$subtotal = (float)($order['subtotal'] ?? 0);
$taxTotal = (float)($order['tax_total'] ?? 0);
$deliveryFee = (float)($order['delivery_fee'] ?? 0);
$grandTotal = (float)($order['total'] ?? ($subtotal + $taxTotal + $deliveryFee));
$isInterState = $isInterState ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice - <?= e($invoiceNo) ?></title>
    <style>
        @page {
            margin: 20mm 15mm;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid <?= $primaryColor ?>;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-table td {
            vertical-align: top;
        }
        .brand-title {
            font-size: 24px;
            font-weight: bold;
            color: <?= $primaryColor ?>;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .brand-tagline {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .invoice-badge {
            background-color: <?= $primaryColor ?>;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-block;
            text-transform: uppercase;
        }
        .details-table {
            width: 100%;
            margin-bottom: 18px;
        }
        .details-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 8px 0 0;
        }
        .box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            min-height: 100px;
        }
        .box-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .box-content {
            font-size: 10.5px;
            line-height: 1.5;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 16px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
            padding: 7px 6px;
            text-align: left;
        }
        .items-table td {
            border: 1px solid #e2e8f0;
            padding: 7px 6px;
            font-size: 10px;
        }
        .items-table tr:nth-child(even) {
            background-color: #fbfcfd;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .totals-table {
            width: 100%;
            margin-top: 8px;
        }
        .totals-table td {
            vertical-align: top;
        }
        .summary-box {
            width: 48%;
            float: right;
            border-collapse: collapse;
        }
        .summary-box td {
            padding: 4px 8px;
            font-size: 11px;
        }
        .summary-box .grand-total-row {
            background-color: #f1f5f9;
            font-size: 13px;
            font-weight: bold;
            border-top: 2px solid <?= $primaryColor ?>;
            border-bottom: 2px solid <?= $primaryColor ?>;
            color: #0f172a;
        }
        .pharmacist-stamp {
            border: 1px dashed #059669;
            background: #ecfdf5;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 16px;
            font-size: 10px;
            color: #065f46;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 9px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 60%;">
                <div class="brand-title"><?= e($brandName) ?></div>
                <div class="brand-tagline"><?= e($shopName) ?> &bull; Licensed Retail Pharmacy</div>
                <div style="font-size: 10px; color: #475569; margin-top: 6px;">
                    Proprietor: <strong><?= e($ownerName) ?></strong><br>
                    GSTIN: <strong><?= e($shopGst) ?></strong> | Drug License: <strong><?= e($drugLicense) ?></strong><br>
                    Email: <?= e($supportEmail) ?> | Phone: <?= e($supportPhone) ?>
                </div>
            </td>
            <td style="width: 40%;" class="text-right">
                <span class="invoice-badge">GST TAX INVOICE</span>
                <table style="width: 100%; margin-top: 8px;" cellpadding="2" cellspacing="0">
                    <tr>
                        <td class="text-right" style="color: #64748b; font-size: 10px;">Invoice No:</td>
                        <td class="text-right font-bold" style="font-size: 11px; color: #0f172a;"><?= e($invoiceNo) ?></td>
                    </tr>
                    <tr>
                        <td class="text-right" style="color: #64748b; font-size: 10px;">Date:</td>
                        <td class="text-right font-bold"><?= e($issuedAt) ?></td>
                    </tr>
                    <tr>
                        <td class="text-right" style="color: #64748b; font-size: 10px;">Order No:</td>
                        <td class="text-right font-bold"><?= e($orderNo) ?></td>
                    </tr>
                    <tr>
                        <td class="text-right" style="color: #64748b; font-size: 10px;">Payment Mode:</td>
                        <td class="text-right font-bold"><?= e($paymentMethodLabel) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Billing & Shipping Details -->
    <table class="details-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="box">
                    <div class="box-title">Billed To / Shipping Address</div>
                    <div class="box-content">
                        <strong><?= e($address['full_name'] ?? $customer['name'] ?? 'Valued Customer') ?></strong><br>
                        <?= e($address['address_line1'] ?? '') ?>
                        <?php if (!empty($address['address_line2'])): ?>
                            , <?= e($address['address_line2']) ?>
                        <?php endif; ?><br>
                        <?= e($address['city'] ?? '') ?>, <?= e($address['state'] ?? '') ?> - <strong><?= e($address['pincode'] ?? '') ?></strong><br>
                        Phone: <?= e($address['phone'] ?? $customer['phone'] ?? 'N/A') ?><br>
                        <?php if (!empty($customer['email'])): ?>
                            Email: <?= e($customer['email']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            <td>
                <div class="box">
                    <div class="box-title">Tax & Pharmacy Compliance</div>
                    <div class="box-content">
                        <strong>Place of Supply:</strong> <?= e($address['state'] ?? 'Delhi') ?><br>
                        <strong>Tax Applicable:</strong> <?= $isInterState ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)' ?><br>
                        <strong>Reverse Charge:</strong> No<br>
                        <strong>Dispensed Under:</strong> Pharmacy Practice Regulations & Drugs and Cosmetics Act
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 32%;">Item Description</th>
                <th style="width: 12%;">Batch</th>
                <th style="width: 10%;">Expiry</th>
                <th style="width: 6%;" class="text-center">Qty</th>
                <th style="width: 8%;" class="text-right">MRP</th>
                <th style="width: 8%;" class="text-right">Rate</th>
                <th style="width: 8%;" class="text-center">GST %</th>
                <th style="width: 12%;" class="text-right">Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php $sn = 1; foreach ($items as $item): ?>
            <?php
                $itemName = $item['medicine_name'] ?? $item['name'] ?? 'Medicine';
                $batchNo = $item['batch_no'] ?? ($item['batch_numbers'] ?? 'N/A');
                $expDate = !empty($item['exp_date']) ? date('m/y', strtotime($item['exp_date'])) : 'N/A';
                $qty = (int)($item['quantity'] ?? 1);
                $mrp = (float)($item['mrp'] ?? $item['unit_price'] ?? 0);
                $rate = (float)($item['unit_price'] ?? 0);
                $gstRate = (float)($item['gst_rate'] ?? 12.0);
                $lineTotal = (float)($item['line_total'] ?? ($rate * $qty));
            ?>
            <tr>
                <td class="text-center"><?= $sn++ ?></td>
                <td>
                    <strong><?= e($itemName) ?></strong>
                    <?php if (!empty($item['strength'])): ?>
                        <span style="color: #64748b; font-size: 9px;">(<?= e($item['strength']) ?>)</span>
                    <?php endif; ?>
                </td>
                <td><?= e($batchNo) ?></td>
                <td><?= e($expDate) ?></td>
                <td class="text-center"><?= $qty ?></td>
                <td class="text-right">₹<?= number_format($mrp, 2) ?></td>
                <td class="text-right">₹<?= number_format($rate, 2) ?></td>
                <td class="text-center"><?= number_format($gstRate, 0) ?>%</td>
                <td class="text-right font-bold">₹<?= number_format($lineTotal, 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals & Taxes Summary -->
    <table class="totals-table">
        <tr>
            <td style="width: 50%;">
                <?php if (!empty($pharmacist['name']) || !empty($pharmacist['reg_no']) || !empty($order['pharmacist_reg_no'])): ?>
                <div class="pharmacist-stamp">
                    <strong>✓ Prescribed Medication Verified & Dispensed</strong><br>
                    Dispensing Pharmacist: <strong><?= e($pharmacist['name'] ?? $order['pharmacist_name'] ?? 'Registered Pharmacist') ?></strong><br>
                    Pharmacy Reg. No: <strong><?= e($pharmacist['reg_no'] ?? $order['pharmacist_reg_no'] ?? $drugLicense) ?></strong>
                </div>
                <?php endif; ?>
                <div style="font-size: 9.5px; color: #64748b; margin-top: 14px;">
                    <strong>Terms & Conditions:</strong><br>
                    1. Goods once sold will only be returned or replaced in case of damage or recall.<br>
                    2. Store medicines in cool, dry place away from direct sunlight.<br>
                    3. This is a computer generated tax invoice.
                </div>
            </td>
            <td style="width: 50%;">
                <table class="summary-box" cellpadding="2" cellspacing="0">
                    <tr>
                        <td style="color: #475569;">Items Subtotal:</td>
                        <td class="text-right font-bold">₹<?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <?php if ($isInterState): ?>
                    <tr>
                        <td style="color: #475569;">IGST Total:</td>
                        <td class="text-right font-bold">₹<?= number_format($taxTotal, 2) ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td style="color: #475569;">CGST (Central Tax):</td>
                        <td class="text-right font-bold">₹<?= number_format($taxTotal / 2, 2) ?></td>
                    </tr>
                    <tr>
                        <td style="color: #475569;">SGST (State Tax):</td>
                        <td class="text-right font-bold">₹<?= number_format($taxTotal / 2, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="color: #475569;">Delivery / Shipping Fee:</td>
                        <td class="text-right font-bold">
                            <?= $deliveryFee > 0 ? '₹' . number_format($deliveryFee, 2) : '<span style="color:#059669">FREE</span>' ?>
                        </td>
                    </tr>
                    <tr class="grand-total-row">
                        <td>Grand Total:</td>
                        <td class="text-right">₹<?= number_format($grandTotal, 2) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        Thank you for choosing <?= e($brandName) ?>! &bull; For inquiries, contact <?= e($supportEmail) ?> or call <?= e($supportPhone) ?>.
    </div>

</body>
</html>
