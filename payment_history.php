<?php
include 'layout.php';
include_once 'payment_expiry_helpers.php';
require_role(1);
$uid = current_user_id();

// Manual Bank In only: if no receipt is uploaded after 3 days, automatically mark it as failed.
mark_expired_manual_bank_payments($conn, $uid);

$stmt = mysqli_prepare($conn, "SELECT pay.*, pr.name product_name, pr.photo FROM product_payments pay JOIN products pr ON pay.product_id=pr.id WHERE pay.user_id=? AND (pay.payment_completed=1 OR LOWER(pay.status)='completed' OR pay.receipt_file IS NOT NULL OR pay.paid_amount IS NOT NULL OR pay.topup_receipt_file IS NOT NULL OR pay.payment_method='Manual Bank In') ORDER BY pay.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

function history_product_label($payment) {
    if (!empty($payment['cart_items'])) {
        $decoded = json_decode($payment['cart_items'], true);
        if (is_array($decoded) && count($decoded) > 0) {
            $names = [];
            foreach ($decoded as $item) {
                $names[] = ($item['name'] ?? 'Pet Needs Item') . ' x' . (int)($item['quantity'] ?? 1);
            }
            return 'Cart Checkout: ' . implode(', ', $names);
        }
    }
    return $payment['product_name'];
}


function is_manual_bank_waiting_for_receipt($payment) {
    $method = strtolower(trim((string)($payment['payment_method'] ?? '')));
    $status = strtolower(trim((string)($payment['status'] ?? '')));
    return $method === 'manual bank in'
        && empty($payment['receipt_file'])
        && $payment['paid_amount'] === null
        && in_array($status, ['pending','pending verification'], true);
}

function is_manual_bank_failed_without_receipt($payment) {
    $method = strtolower(trim((string)($payment['payment_method'] ?? '')));
    $status = strtolower(trim((string)($payment['status'] ?? '')));
    return $method === 'manual bank in'
        && empty($payment['receipt_file'])
        && $payment['paid_amount'] === null
        && $status === 'failed';
}

function user_refund_status_badge($status) {
    $s = strtolower(trim((string)$status));
    if ($s === '' || $s === 'not required') return '<span class="badge badge-secondary">NO REFUND</span>';
    if ($s === 'requested') return '<span class="badge badge-warning">UPLOAD YOUR QR</span>';
    if ($s === 'qr uploaded') return '<span class="badge badge-info">WAITING REFUND</span>';
    if ($s === 'paid') return '<span class="badge badge-primary">REFUND SENT</span>';
    if ($s === 'completed') return '<span class="badge badge-success">REFUND COMPLETE</span>';
    return '<span class="badge badge-secondary">' . h($status) . '</span>';
}

function user_underpay_status_badge($status) {
    $s = strtolower(trim((string)$status));
    if ($s === '' || $s === 'not required') return '<span class="badge badge-secondary">NO TOP-UP</span>';
    if ($s === 'requested') return '<span class="badge badge-danger">NEED TOP-UP</span>';
    if ($s === 'submitted') return '<span class="badge badge-info">TOP-UP SUBMITTED</span>';
    if ($s === 'completed') return '<span class="badge badge-success">TOP-UP COMPLETE</span>';
    return '<span class="badge badge-secondary">' . h($status) . '</span>';
}

page_header('Payment History - PawFect Home', 'payments');
page_title('Product Payment History', 'Track pet needs purchases, QR payment receipts, refunds and top-up requests.');
?>
<div class="container-fluid px-lg-5 py-5 payment-history-page">
    <div class="table-responsive card-clean p-0 payment-history-wrap">
        <table class="table payment-history-table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Required Amount</th>
                    <th>Paid Amount</th>
                    <th>Payment Status</th>
                    <th>Refund / Top-Up</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="8" class="text-center text-muted p-4">No product payments yet. <a href="products.php">Shop pet needs</a>.</td></tr>
            <?php endif; ?>
            <?php while($p = mysqli_fetch_assoc($result)): ?>
                <?php
                    $basePaid = ($p['paid_amount'] !== null) ? (float)$p['paid_amount'] : null;
                    $topupPaid = ($p['topup_paid_amount'] !== null) ? (float)$p['topup_paid_amount'] : 0;
                    $totalPaid = $basePaid !== null ? $basePaid + $topupPaid : null;
                    $isOverpaid = $totalPaid !== null && $totalPaid > (float)$p['amount'];
                    $isUnderpaid = $totalPaid !== null && $totalPaid < (float)$p['amount'];
                    $overpaidAmount = $isOverpaid ? ($totalPaid - (float)$p['amount']) : 0;
                    $underpaidAmount = $isUnderpaid ? ((float)$p['amount'] - $totalPaid) : 0;
                    $refundStatus = strtolower(trim((string)($p['refund_status'] ?? 'not required')));
                    if ($refundStatus === '') $refundStatus = 'not required';
                    $underpayStatus = strtolower(trim((string)($p['underpay_status'] ?? 'not required')));
                    if ($underpayStatus === '') $underpayStatus = 'not required';
                    $hasUnderpayCase = ($underpayStatus !== 'not required') || ($basePaid !== null && $basePaid < (float)$p['amount']);
                    $isManualWaitingReceipt = is_manual_bank_waiting_for_receipt($p);
                    $isManualFailedNoReceipt = is_manual_bank_failed_without_receipt($p);
                    $manualBankPayBefore = manual_bank_expires_at($p['created_at']);
                ?>
                <tr class="<?php echo $isOverpaid ? 'table-warning' : ($isUnderpaid ? 'table-danger' : ''); ?>">
                    <td><?php echo h(date('d M Y', strtotime($p['created_at']))); ?></td>
                    <td class="history-product-cell"><img src="<?php echo h(pawfect_image_src($p['photo'], 'img/feature.jpg')); ?>" class="history-product-img mr-2"><?php echo h(history_product_label($p)); ?></td>
                    <td><?php echo (int)$p['quantity']; ?></td>
                    <td>RM <?php echo number_format($p['amount'],2); ?></td>
                    <td>
                        <?php if ($basePaid !== null): ?>
                            RM <?php echo number_format($basePaid,2); ?>
                            <?php if ($topupPaid > 0): ?>
                                <br><small>+ Top-up RM <?php echo number_format($topupPaid,2); ?></small>
                                <br><strong>Total RM <?php echo number_format($totalPaid,2); ?></strong>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?php echo status_badge($p['status']); ?></td>
                    <td class="history-refund-cell">
                        <?php if ($isOverpaid): ?>
                            <div><strong>Extra RM <?php echo number_format($overpaidAmount, 2); ?></strong></div>
                            <div class="my-1"><?php echo user_refund_status_badge($refundStatus); ?></div>
                            <?php if ($refundStatus === 'not required'): ?>
                                <small class="text-muted">Waiting for staff/admin to request your refund QR.</small>
                            <?php elseif ($refundStatus === 'requested'): ?>
                                <a href="refund_upload.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary mt-1">Upload Refund QR</a>
                            <?php elseif ($refundStatus === 'qr uploaded'): ?>
                                <a href="refund_upload.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary mt-1">View Refund Request</a>
                                <small class="text-muted d-block mt-1">Staff/admin will pay back the extra amount.</small>
                            <?php elseif ($refundStatus === 'paid'): ?>
                                <a href="refund_upload.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-success mt-1">View & Tick Complete</a>
                            <?php elseif ($refundStatus === 'completed'): ?>
                                <?php if (!empty($p['refund_receipt_file'])): ?>
                                    <a href="<?php echo h($p['refund_receipt_file']); ?>" target="_blank" class="small d-block">View refund receipt</a>
                                <?php endif; ?>
                                <small class="text-success">Refund confirmed by you.</small>
                                <?php if (strtolower(trim((string)$p['status'])) === 'completed'): ?>
                                    <div class="mt-2"><strong class="text-primary">Please book appointment to pick up</strong></div>
                                    <a href="appointment.php?type=pickup" class="btn btn-sm btn-primary mt-1">Book Pick-Up</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php elseif ($hasUnderpayCase): ?>
                            <div><strong>Need RM <?php echo number_format(max(0, (float)$p['amount'] - ($totalPaid ?? 0)), 2); ?></strong></div>
                            <div class="my-1"><?php echo user_underpay_status_badge($underpayStatus); ?></div>
                            <?php if ($underpayStatus === 'not required'): ?>
                                <small class="text-muted">Waiting for staff/admin instruction.</small>
                            <?php elseif ($underpayStatus === 'requested'): ?>
                                <small class="text-danger d-block">Not enough payment. Please submit a new receipt.</small>
                                <a href="underpayment_upload.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-danger mt-1">Submit New Receipt</a>
                            <?php elseif ($underpayStatus === 'submitted'): ?>
                                <a href="underpayment_upload.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary mt-1">View Top-Up</a>
                                <small class="text-muted d-block mt-1">Staff/admin will check old and new receipt.</small>
                            <?php elseif ($underpayStatus === 'completed'): ?>
                                <?php if (!empty($p['topup_receipt_file'])): ?>
                                    <a href="<?php echo h($p['topup_receipt_file']); ?>" target="_blank" class="small d-block">View top-up receipt</a>
                                <?php endif; ?>
                                <small class="text-success">Top-up verified by staff/admin.</small>
                                <?php if (strtolower(trim((string)$p['status'])) === 'completed'): ?>
                                    <div class="mt-2"><strong class="text-primary">Please book appointment to pick up</strong></div>
                                    <a href="appointment.php?type=pickup" class="btn btn-sm btn-primary mt-1">Book Pick-Up</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($isManualWaitingReceipt): ?>
                                <strong class="text-primary d-block">Manual bank in waiting for receipt</strong>
                                <small class="text-muted d-block">Upload before <?php echo h(date('d M Y h:i A', $manualBankPayBefore)); ?>.</small>
                                <a href="manual_bank_payment.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary mt-1">Upload Receipt</a>
                            <?php elseif ($isManualFailedNoReceipt): ?>
                                <strong class="text-danger d-block">Manual bank in expired</strong>
                                <small class="text-muted d-block">No receipt was submitted within 3 days. Please start a new checkout.</small>
                            <?php elseif (strtolower(trim((string)$p['status'])) === 'completed'): ?>
                                <strong class="text-primary d-block">Please book appointment to pick up</strong>
                                <a href="appointment.php?type=pickup" class="btn btn-sm btn-primary mt-1">Book Pick-Up</a>
                            <?php else: ?>
                                <span class="text-muted">No action needed</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isManualWaitingReceipt): ?>
                            <a href="manual_bank_payment.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary mb-1">Upload Receipt</a><br>
                            <small class="text-muted">Receipt not submitted yet</small>
                        <?php elseif ($isManualFailedNoReceipt): ?>
                            <span class="badge badge-danger">EXPIRED</span><br>
                            <a href="products.php" class="btn btn-sm btn-outline-primary mt-1">New Checkout</a>
                        <?php else: ?>
                            <a href="receipt.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary mb-1">View</a><br>
                            <a href="receipt.php?id=<?php echo (int)$p['id']; ?>&download=1" class="btn btn-sm btn-outline-primary"><i class="fas fa-download mr-1"></i>Download</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_footer(); ?>
