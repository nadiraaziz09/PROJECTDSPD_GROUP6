<?php
include 'layout.php';
require_login();
$uid = current_user_id();
$id = (int)($_GET['id'] ?? 0);
$sql = "SELECT pay.*, pr.name product_name, pr.category, acc.Name customer_name FROM product_payments pay JOIN products pr ON pay.product_id=pr.id JOIN account acc ON pay.user_id=acc.ID WHERE pay.id=?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$receipt = mysqli_fetch_assoc($result);
if (!$receipt || (($_SESSION['role'] ?? 0) == 1 && (int)$receipt['user_id'] !== $uid)) {
    flash('error', 'Receipt not found.');
    header('Location: payment_history.php');
    exit();
}

// Normal users may view a receipt once they have submitted payment proof
// or the online payment has been marked completed. This keeps unpaid checkout
// records hidden, while allowing pending-verification receipts to appear in history.
$isCustomerReceiptReady = (int)($receipt['payment_completed'] ?? 0) === 1
    || strtolower(trim((string)($receipt['status'] ?? ''))) === 'completed'
    || !empty($receipt['receipt_file'])
    || $receipt['paid_amount'] !== null
    || !empty($receipt['topup_receipt_file']);

if ((int)($_SESSION['role'] ?? 0) === 1 && !$isCustomerReceiptReady) {
    $method = strtolower(trim((string)$receipt['payment_method']));
    flash('error', 'Payment receipt is not available yet. Please complete payment or upload your receipt first.');
    if ($method === 'manual bank in') {
        header('Location: manual_bank_payment.php?id=' . (int)$receipt['id']);
    } elseif (strpos($method, 'qr') !== false) {
        header('Location: qr_payment.php?id=' . (int)$receipt['id']);
    } elseif (strpos($method, 'online banking') !== false || strpos($method, 'transfer') !== false) {
        header('Location: bank_payment.php?id=' . (int)$receipt['id']);
    } else {
        header('Location: products.php');
    }
    exit();
}

function receipt_payment_items($receipt) {
    $items = [];
    if (!empty($receipt['cart_items'])) {
        $decoded = json_decode($receipt['cart_items'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $items[] = [
                    'name' => $item['name'] ?? 'Pet Needs Item',
                    'category' => $item['category'] ?? '',
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'subtotal' => (float)($item['subtotal'] ?? 0)
                ];
            }
        }
    }
    if (empty($items)) {
        $items[] = [
            'name' => $receipt['product_name'],
            'category' => $receipt['category'],
            'quantity' => (int)$receipt['quantity'],
            'subtotal' => (float)$receipt['amount']
        ];
    }
    return $items;
}

$items = receipt_payment_items($receipt);
$basePaid = ($receipt['paid_amount'] !== null) ? (float)$receipt['paid_amount'] : null;
$topupPaid = ($receipt['topup_paid_amount'] !== null) ? (float)$receipt['topup_paid_amount'] : 0;
$totalPaid = $basePaid !== null ? $basePaid + $topupPaid : null;
$isOverpaid = $totalPaid !== null && $totalPaid > (float)$receipt['amount'];
$isUnderpaid = $totalPaid !== null && $totalPaid < (float)$receipt['amount'];
$overpaidAmount = $isOverpaid ? ($totalPaid - (float)$receipt['amount']) : 0;
$underpaidAmount = $isUnderpaid ? ((float)$receipt['amount'] - $totalPaid) : 0;
$refundStatus = strtolower(trim((string)($receipt['refund_status'] ?? 'not required')));
if ($refundStatus === '') $refundStatus = 'not required';
$underpayStatus = strtolower(trim((string)($receipt['underpay_status'] ?? 'not required')));
if ($underpayStatus === '') $underpayStatus = 'not required';

if (isset($_GET['download'])) {
    $receiptNo = 'PFH-' . str_pad($receipt['id'], 5, '0', STR_PAD_LEFT);
    $filename = 'PawFect-Receipt-' . $receiptNo . '.html';
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PawFect Receipt <?php echo h($receiptNo); ?></title>
    <style>
        body{font-family:Arial,sans-serif;color:#222;margin:30px}.receipt{max-width:760px;margin:auto;border:1px solid #ddd;padding:24px;border-radius:8px}h1{margin:0 0 8px}.muted{color:#666}.row{display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding:9px 0}.label{font-weight:bold}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ddd;padding:8px;text-align:left}.total{font-size:18px;font-weight:bold}.status{text-transform:uppercase}
    </style>
</head>
<body>
<div class="receipt">
    <h1>PawFect Home</h1>
    <p class="muted">Pet Needs Payment Receipt</p>
    <div class="row"><span class="label">Receipt No.</span><span><?php echo h($receiptNo); ?></span></div>
    <div class="row"><span class="label">Transaction ID</span><span><?php echo h($receipt['transaction_id']); ?></span></div>
    <div class="row"><span class="label">Customer</span><span><?php echo h($receipt['customer_name']); ?></span></div>
    <div class="row"><span class="label">Payer Name</span><span><?php echo h($receipt['payer_name'] ?: $receipt['customer_name']); ?></span></div>
    <div class="row"><span class="label">Date</span><span><?php echo h(date('d M Y, h:i A', strtotime($receipt['created_at']))); ?></span></div>
    <div class="row"><span class="label">Payment Method</span><span><?php echo h($receipt['payment_method']); ?></span></div>
    <div class="row"><span class="label">Status</span><span class="status"><?php echo h($receipt['status']); ?></span></div>
    <table>
        <thead><tr><th>Product</th><th>Category</th><th>Qty</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr><td><?php echo h($item['name']); ?></td><td><?php echo h($item['category']); ?></td><td><?php echo (int)$item['quantity']; ?></td><td>RM <?php echo number_format((float)$item['subtotal'], 2); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="total">Required Amount: RM <?php echo number_format((float)$receipt['amount'], 2); ?></p>
    <p>Uploaded Paid Amount: <?php echo $totalPaid !== null ? 'RM ' . number_format($totalPaid, 2) : '-'; ?></p>
    <?php if ($isOverpaid): ?><p>Refund Amount: RM <?php echo number_format($overpaidAmount, 2); ?> | Refund Status: <?php echo h(ucwords($refundStatus)); ?></p><?php endif; ?>
    <?php if ($isUnderpaid): ?><p>Top-Up Needed: RM <?php echo number_format($underpaidAmount, 2); ?> | Top-Up Status: <?php echo h(ucwords($underpayStatus)); ?></p><?php endif; ?>
    <p class="muted">This file was generated by PawFect Home. Open it in a browser to print or save as PDF.</p>
</div>
</body>
</html>
    <?php
    exit();
}

page_header('Payment Receipt - PawFect Home', 'payments');
page_title('Product Payment Receipt', 'Printable receipt for pet needs product payment.');
?>
<div class="container py-5">
    <div class="receipt-box mx-auto" style="max-width:760px">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><span class="text-primary">Paw</span>Fect Home</h2>
            <?php echo status_badge($receipt['status']); ?>
        </div>
        <?php if ($isOverpaid): ?>
            <div class="alert alert-warning">
                <strong>Overpayment report sent to admin:</strong> Customer uploaded total paid amount RM <?php echo number_format($totalPaid,2); ?>, which is RM <?php echo number_format($overpaidAmount,2); ?> more than the required total.
            </div>
        <?php elseif ($isUnderpaid): ?>
            <div class="alert alert-danger">
                <strong>Underpayment report:</strong> Uploaded total paid amount is still RM <?php echo number_format($underpaidAmount,2); ?> lower than the required total.
            </div>
        <?php endif; ?>
        <table class="table">
            <tr><th>Receipt No.</th><td>#PFH-<?php echo str_pad($receipt['id'],5,'0',STR_PAD_LEFT); ?></td></tr>
            <tr><th>Transaction ID</th><td><?php echo h($receipt['transaction_id']); ?></td></tr>
            <tr><th>Payment Reference</th><td><?php echo h($receipt['bank_reference'] ?: '-'); ?></td></tr>
            <tr><th>Customer</th><td><?php echo h($receipt['customer_name']); ?></td></tr>
            <tr><th>Payer Name</th><td><?php echo h($receipt['payer_name'] ?: $receipt['customer_name']); ?></td></tr>
            <tr>
                <th>Product</th>
                <td>
                    <?php foreach ($items as $item): ?>
                        <div class="mb-1">
                            <?php echo h($item['name']); ?>
                            <span class="text-muted small">(Qty: <?php echo (int)$item['quantity']; ?>)</span>
                            <strong class="float-right">RM <?php echo number_format((float)$item['subtotal'],2); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr><th>Category</th><td><?php echo !empty($receipt['cart_items']) ? 'Multiple Pet Needs Items' : h($receipt['category']); ?></td></tr>
            <tr><th>Total Quantity</th><td><?php echo (int)$receipt['quantity']; ?></td></tr>
            <tr><th>Payment Method</th><td><?php echo h($receipt['payment_method']); ?></td></tr>
            <tr><th>Bank</th><td><?php echo h($receipt['bank_name'] ?: '-'); ?></td></tr>
            <tr><th>Date</th><td><?php echo h(date('d M Y, h:i A', strtotime($receipt['created_at']))); ?></td></tr>
            <tr><th>Required Amount</th><td><strong>RM <?php echo number_format($receipt['amount'],2); ?></strong></td></tr>
            <tr><th>First Uploaded Paid Amount</th><td><?php echo $basePaid !== null ? 'RM ' . number_format($basePaid,2) : '-'; ?></td></tr>
            <?php if ($topupPaid > 0): ?>
                <tr><th>Top-Up Paid Amount</th><td>RM <?php echo number_format($topupPaid,2); ?></td></tr>
                <tr><th>Total Uploaded Paid Amount</th><td><strong>RM <?php echo number_format($totalPaid,2); ?></strong></td></tr>
            <?php endif; ?>
            <tr>
                <th>Uploaded Receipt</th>
                <td>
                    <?php if (!empty($receipt['receipt_file'])): ?>
                        <a href="<?php echo h($receipt['receipt_file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-alt mr-1"></i>View Uploaded Receipt</a>
                    <?php else: ?>
                        <span class="text-muted">Not uploaded yet</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($underpayStatus !== 'not required'): ?>
                <tr><th>Top-Up Status</th><td><?php echo h(ucwords($underpayStatus)); ?></td></tr>
                <tr><th>Staff/Admin Message</th><td><?php echo !empty($receipt['underpay_message']) ? h($receipt['underpay_message']) : '-'; ?></td></tr>
                <tr>
                    <th>New Top-Up Receipt</th>
                    <td>
                        <?php if (!empty($receipt['topup_receipt_file'])): ?>
                            <a href="<?php echo h($receipt['topup_receipt_file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-upload mr-1"></i>View New Top-Up Receipt</a>
                        <?php else: ?>
                            <span class="text-muted">Not uploaded yet</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ($isOverpaid): ?>
                <tr><th>Refund Amount</th><td><strong>RM <?php echo number_format($overpaidAmount,2); ?></strong></td></tr>
                <tr><th>Refund Status</th><td><?php echo h(ucwords($refundStatus)); ?></td></tr>
                <tr>
                    <th>User Refund QR</th>
                    <td>
                        <?php if (!empty($receipt['refund_qr_file'])): ?>
                            <a href="<?php echo h($receipt['refund_qr_file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-qrcode mr-1"></i>View User Refund QR</a>
                        <?php else: ?>
                            <span class="text-muted">Not uploaded yet</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Refund Receipt</th>
                    <td>
                        <?php if (!empty($receipt['refund_receipt_file'])): ?>
                            <a href="<?php echo h($receipt['refund_receipt_file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-invoice-dollar mr-1"></i>View Refund Receipt</a>
                        <?php else: ?>
                            <span class="text-muted">Not uploaded yet</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print mr-2"></i>Print / Save PDF</button>
        <a href="receipt.php?id=<?php echo (int)$receipt['id']; ?>&download=1" class="btn btn-outline-primary"><i class="fas fa-download mr-2"></i>Download Receipt</a>
        <a href="payment_history.php" class="btn btn-outline-secondary">Back</a>
    </div>
</div>
<?php page_footer(); ?>
