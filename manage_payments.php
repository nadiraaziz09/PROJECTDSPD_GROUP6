<?php
include 'layout.php';
include_once 'payment_expiry_helpers.php';
require_role([2,3]);

// Manual Bank In only: pending payments without receipt fail after 3 days.
mark_expired_manual_bank_payments($conn);

function payment_items_for_admin($payment) {
    $items = [];
    if (!empty($payment['cart_items'])) {
        $decoded = json_decode($payment['cart_items'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $items[] = [
                    'product_id' => (int)($item['product_id'] ?? 0),
                    'name' => $item['name'] ?? 'Pet Needs Item',
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'subtotal' => (float)($item['subtotal'] ?? 0)
                ];
            }
        }
    }
    if (empty($items)) {
        $items[] = [
            'product_id' => (int)$payment['product_id'],
            'name' => $payment['product_name'] ?? 'Pet Needs Item',
            'quantity' => (int)$payment['quantity'],
            'subtotal' => (float)$payment['amount']
        ];
    }
    return $items;
}

function deduct_payment_stock_once($conn, $payment) {
    $items = payment_items_for_admin($payment);
    foreach ($items as $item) {
        $pid = (int)$item['product_id'];
        $qty = max(1, (int)$item['quantity']);
        if ($pid > 0) {
            decrease_product_stock($conn, $pid, $qty);
        }
    }
}

function refund_status_badge_text($status) {
    $s = strtolower(trim((string)$status));
    if ($s === '' || $s === 'not required') return '<span class="badge badge-secondary">NO REFUND</span>';
    if ($s === 'requested') return '<span class="badge badge-warning">QR REQUESTED</span>';
    if ($s === 'qr uploaded') return '<span class="badge badge-info">USER QR READY</span>';
    if ($s === 'paid') return '<span class="badge badge-primary">REFUND SENT</span>';
    if ($s === 'completed') return '<span class="badge badge-success">USER COMPLETED</span>';
    return '<span class="badge badge-secondary">' . h($status) . '</span>';
}

function underpay_status_badge_text($status) {
    $s = strtolower(trim((string)$status));
    if ($s === '' || $s === 'not required') return '<span class="badge badge-secondary">NO TOP-UP</span>';
    if ($s === 'requested') return '<span class="badge badge-danger">TOP-UP REQUESTED</span>';
    if ($s === 'submitted') return '<span class="badge badge-info">TOP-UP SUBMITTED</span>';
    if ($s === 'completed') return '<span class="badge badge-success">TOP-UP COMPLETE</span>';
    return '<span class="badge badge-secondary">' . h($status) . '</span>';
}

function save_payment_upload($field, $folder, $prefix, $allowedExt = ['jpg','jpeg','png','webp','pdf']) {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Please choose a file to upload.');
        return '';
    }

    $originalName = $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        flash('error', 'File must be JPG, PNG, WEBP, or PDF.');
        return '';
    }

    if ((int)$_FILES[$field]['size'] > 5 * 1024 * 1024) {
        flash('error', 'File is too large. Maximum size is 5MB.');
        return '';
    }

    $uploadDir = __DIR__ . '/' . $folder;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $safeFile = $prefix . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $safeFile;
    $relativePath = $folder . '/' . $safeFile;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $targetPath)) {
        flash('error', 'Upload failed. Please try again.');
        return '';
    }

    return $relativePath;
}

if (isset($_POST['update_status'])) {
    $id = (int)($_POST['payment_id'] ?? 0);
    $statusNew = trim($_POST['status'] ?? '');
    if (in_array($statusNew, ['pending','pending verification','completed','failed','refunded'], true)) {
        $paymentBefore = mysqli_fetch_assoc(mysqli_query($conn, "SELECT pay.*, pr.name product_name FROM product_payments pay JOIN products pr ON pay.product_id=pr.id WHERE pay.id=$id LIMIT 1"));
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'si', $statusNew, $id);
        mysqli_stmt_execute($stmt);
        if ($paymentBefore && $statusNew === 'completed') {
            if ((int)($paymentBefore['payment_completed'] ?? 0) !== 1 && strtolower($paymentBefore['status']) !== 'completed') {
                deduct_payment_stock_once($conn, $paymentBefore);
            }
            mark_product_payment_completed($conn, $id, true);
            if (!empty($paymentBefore['underpay_status']) && strtolower($paymentBefore['underpay_status']) !== 'not required') {
                mysqli_query($conn, "UPDATE product_payments SET underpay_status='completed', underpay_updated_at=NOW() WHERE id=$id");
            }
        } else {
            mark_product_payment_completed($conn, $id, false);
        }
        flash('success', 'Payment status updated.');
    }
    header('Location: manage_payments.php');
    exit();
}

if (isset($_POST['request_refund_qr'])) {
    $id = (int)($_POST['payment_id'] ?? 0);
    $payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM product_payments WHERE id=$id LIMIT 1"));
    if ($payment && $payment['paid_amount'] !== null && (float)$payment['paid_amount'] > (float)$payment['amount']) {
        $refundAmount = (float)$payment['paid_amount'] - (float)$payment['amount'];
        $status = 'requested';
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET refund_status=?, refund_amount=?, refund_updated_at=NOW() WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sdi', $status, $refundAmount, $id);
        mysqli_stmt_execute($stmt);
        flash('success', 'Refund QR request sent to user payment history.');
    } else {
        flash('error', 'Refund QR can only be requested for overpaid payments.');
    }
    header('Location: manage_payments.php');
    exit();
}

if (isset($_POST['upload_refund_receipt'])) {
    $id = (int)($_POST['payment_id'] ?? 0);
    $payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM product_payments WHERE id=$id LIMIT 1"));
    if (!$payment || empty($payment['refund_qr_file'])) {
        flash('error', 'User must upload their refund QR before staff/admin uploads refund proof.');
        header('Location: manage_payments.php');
        exit();
    }

    $refundFile = save_payment_upload('refund_receipt_file', 'uploads/refund_receipts', 'refund_receipt_' . $id);
    if ($refundFile !== '') {
        $status = 'paid';
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET refund_receipt_file=?, refund_status=?, refund_user_note=NULL, refund_issue_reported_at=NULL, refund_updated_at=NOW() WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssi', $refundFile, $status, $id);
        mysqli_stmt_execute($stmt);
        flash('success', 'Refund receipt uploaded. User can now view it and mark refund as completed.');
    }
    header('Location: manage_payments.php');
    exit();
}

if (isset($_POST['request_topup_payment'])) {
    $id = (int)($_POST['payment_id'] ?? 0);
    $payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM product_payments WHERE id=$id LIMIT 1"));
    $basePaid = ($payment && $payment['paid_amount'] !== null) ? (float)$payment['paid_amount'] : null;
    $topupPaid = ($payment && $payment['topup_paid_amount'] !== null) ? (float)$payment['topup_paid_amount'] : 0;
    $totalPaid = $basePaid !== null ? $basePaid + $topupPaid : null;
    if ($payment && $totalPaid !== null && $totalPaid < (float)$payment['amount']) {
        $missingAmount = (float)$payment['amount'] - $totalPaid;
        $underpayStatus = 'requested';
        $message = 'Not enough payment. Please resubmit payment by uploading your old receipt and new receipt so the total payment becomes full.';
        $failedStatus = 'failed';
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET underpay_status=?, underpay_amount=?, underpay_message=?, status=?, underpay_updated_at=NOW() WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sdssi', $underpayStatus, $missingAmount, $message, $failedStatus, $id);
        mysqli_stmt_execute($stmt);
        flash('success', 'Top-up request sent to user payment history. User can upload a new receipt for the missing amount.');
    } else {
        flash('error', 'Top-up request can only be sent for payments lower than the required amount.');
    }
    header('Location: manage_payments.php');
    exit();
}

$status = trim($_GET['status'] ?? '');
$method = trim($_GET['method'] ?? '');
$filterDate = trim($_GET['date'] ?? '');
// Keep old date_from/date_to support just in case an old bookmark uses it.
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$conditions = [];
if ($status !== '') {
    $conditions[] = "pay.status='" . mysqli_real_escape_string($conn, $status) . "'";
}
if ($method !== '') {
    $safeMethod = mysqli_real_escape_string($conn, $method);
    $conditions[] = "(pay.payment_method='$safeMethod' OR pay.bank_name='$safeMethod' OR pay.gateway_provider='$safeMethod')";
}
if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $conditions[] = "DATE(pay.created_at) = '" . mysqli_real_escape_string($conn, $filterDate) . "'";
} else {
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $conditions[] = "DATE(pay.created_at) >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $conditions[] = "DATE(pay.created_at) <= '" . mysqli_real_escape_string($conn, $dateTo) . "'";
    }
}
$where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
$result = mysqli_query($conn, "SELECT pay.*, pr.name product_name, acc.Name customer_name FROM product_payments pay JOIN products pr ON pay.product_id=pr.id JOIN account acc ON pay.user_id=acc.ID $where ORDER BY pay.created_at DESC");
$paymentMethods = mysqli_query($conn, "
    SELECT method_name FROM (
        SELECT DISTINCT payment_method AS method_name FROM product_payments WHERE payment_method IS NOT NULL AND payment_method <> ''
        UNION
        SELECT DISTINCT bank_name AS method_name FROM product_payments WHERE bank_name IS NOT NULL AND bank_name <> ''
        UNION
        SELECT DISTINCT gateway_provider AS method_name FROM product_payments WHERE gateway_provider IS NOT NULL AND gateway_provider <> ''
    ) m
    ORDER BY method_name
");
$openPaymentSql = "LOWER(status) <> 'completed' AND LOWER(status) <> 'refunded'";
$overpayCountRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM product_payments WHERE paid_amount IS NOT NULL AND (paid_amount + IFNULL(topup_paid_amount,0)) > amount AND $openPaymentSql AND LOWER(IFNULL(refund_status,'not required')) <> 'completed'"));
$overpayCount = (int)($overpayCountRow['total'] ?? 0);
$refundActionRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM product_payments WHERE refund_status IN ('requested','qr uploaded','paid') AND $openPaymentSql"));
$refundActionCount = (int)($refundActionRow['total'] ?? 0);
$underpayCountRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM product_payments WHERE paid_amount IS NOT NULL AND (paid_amount + IFNULL(topup_paid_amount,0)) < amount AND $openPaymentSql AND LOWER(IFNULL(underpay_status,'not required')) <> 'completed'"));
$underpayCount = (int)($underpayCountRow['total'] ?? 0);
$topupActionRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM product_payments WHERE underpay_status IN ('requested','submitted') AND $openPaymentSql"));
$topupActionCount = (int)($topupActionRow['total'] ?? 0);

page_header('Payment Management - PawFect Home', 'payments');
page_title('Product Payment Transactions', 'Staff and admin can track QR payments, receipts, overpayment reports, refunds and top-up requests.');
?>
<div class="container-fluid px-lg-5 py-5 manage-payments-page">
    <?php if ($overpayCount > 0): ?>
        <div class="alert alert-warning">
            <strong><i class="fas fa-exclamation-triangle mr-2"></i>Overpayment Report:</strong>
            <?php echo $overpayCount; ?> payment(s) have uploaded paid amount higher than the required amount. Use the refund column to request the user QR and upload refund proof.
        </div>
    <?php endif; ?>

    <?php if ($underpayCount > 0): ?>
        <div class="alert alert-danger">
            <strong><i class="fas fa-exclamation-circle mr-2"></i>Underpayment Report:</strong>
            <?php echo $underpayCount; ?> payment(s) have paid amount lower than the required amount. Use the top-up button to ask the user for an additional receipt.
        </div>
    <?php endif; ?>

    <?php if ($refundActionCount > 0): ?>
        <div class="alert alert-info">
            <strong><i class="fas fa-undo-alt mr-2"></i>Refund Tracking:</strong>
            <?php echo $refundActionCount; ?> refund case(s) are waiting for user QR, staff/admin refund proof, or user confirmation.
        </div>
    <?php endif; ?>

    <?php if ($topupActionCount > 0): ?>
        <div class="alert alert-danger">
            <strong><i class="fas fa-plus-circle mr-2"></i>Top-Up Tracking:</strong>
            <?php echo $topupActionCount; ?> underpayment case(s) are waiting for user additional receipt or staff/admin verification.
        </div>
    <?php endif; ?>

    <form class="action-bar mb-4 payment-filter-bar" method="get">
        <div class="payment-filter-grid">
            <div class="payment-filter-item">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo h($filterDate); ?>">
            </div>
            <div class="payment-filter-item">
                <label>Status</label>
                <select name="status" class="custom-select">
                    <option value="">All Status</option>
                    <?php foreach(['pending','pending verification','completed','failed','refunded'] as $s): ?>
                        <option value="<?php echo h($s); ?>" <?php echo $status===$s?'selected':''; ?>><?php echo h(ucwords($s)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="payment-filter-item">
                <label>Payment Method</label>
                <select name="method" class="custom-select">
                    <option value="">All Methods</option>
                    <?php while($pm = mysqli_fetch_assoc($paymentMethods)): ?>
                        <option value="<?php echo h($pm['method_name']); ?>" <?php echo $method===$pm['method_name']?'selected':''; ?>><?php echo h($pm['method_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="payment-filter-button"><button class="btn btn-primary btn-block">Filter</button></div>
            <div class="payment-filter-button"><a href="manage_payments.php" class="btn btn-outline-secondary btn-block">Reset</a></div>
        </div>
    </form>
    <div class="table-responsive card-clean payment-table-wrap">
        <table class="table payment-admin-table mb-0">
            <thead>
                <tr>
                    <th class="payment-col-date">Date</th>
                    <th class="payment-col-customer">Customer</th>
                    <th class="payment-col-products">Product(s)</th>
                    <th class="payment-col-qty text-center">Qty</th>
                    <th class="payment-col-required">Required</th>
                    <th class="payment-col-paid">Paid / Report</th>
                    <th class="payment-col-refund">Refund / Top-Up</th>
                    <th class="payment-col-method">Method</th>
                    <th class="payment-col-ref">Ref.</th>
                    <th class="payment-col-status">Current Status</th>
                    <th class="payment-col-update">Update Status</th>
                    <th class="payment-col-receipt">Receipt</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="12" class="text-center text-muted p-4">No product payment transactions found.</td></tr>
            <?php endif; ?>
            <?php while($p=mysqli_fetch_assoc($result)): ?>
                <?php
                    $items = payment_items_for_admin($p);
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
                    $paymentStatusLower = strtolower(trim((string)$p['status']));
                    $paymentClosed = in_array($paymentStatusLower, ['completed','refunded'], true);
                    $needsRefundAction = $isOverpaid && !$paymentClosed && $refundStatus !== 'completed';
                    $hasUnderpayCase = !$paymentClosed && $underpayStatus !== 'completed' && (($underpayStatus !== 'not required') || ($basePaid !== null && $basePaid < (float)$p['amount']));
                ?>
                <tr class="<?php echo $needsRefundAction ? 'table-warning' : ($hasUnderpayCase ? 'table-danger' : ''); ?>">
                    <td class="payment-date"><?php echo h(date('d M Y', strtotime($p['created_at']))); ?></td>
                    <td class="payment-customer"><?php echo h($p['customer_name']); ?></td>
                    <td class="payment-product-list">
                        <?php foreach ($items as $item): ?>
                            <div><?php echo h($item['name']); ?> <small class="text-muted">x<?php echo (int)$item['quantity']; ?></small></div>
                        <?php endforeach; ?>
                    </td>
                    <td class="text-center"><?php echo (int)$p['quantity']; ?></td>
                    <td class="payment-money">RM <?php echo number_format($p['amount'],2); ?></td>
                    <td class="payment-report-cell">
                        <?php if ($basePaid !== null): ?>
                            <strong>Paid: RM <?php echo number_format($basePaid,2); ?></strong><br>
                            <?php if ($topupPaid > 0): ?>
                                <small class="d-block">Top-up: RM <?php echo number_format($topupPaid,2); ?></small>
                                <strong class="d-block">Total: RM <?php echo number_format($totalPaid,2); ?></strong>
                            <?php endif; ?>
                            <?php if ($needsRefundAction): ?>
                                <span class="badge badge-warning">Overpaid RM <?php echo number_format($overpaidAmount, 2); ?></span>
                            <?php elseif ($hasUnderpayCase && $isUnderpaid): ?>
                                <span class="badge badge-danger">Underpaid RM <?php echo number_format($underpaidAmount, 2); ?></span>
                            <?php elseif ($paymentClosed || $refundStatus === 'completed' || $underpayStatus === 'completed'): ?>
                                <span class="badge badge-success">Closed</span>
                            <?php elseif ($topupPaid > 0): ?>
                                <span class="badge badge-success">Full After Top-Up</span>
                            <?php else: ?>
                                <span class="badge badge-success">Exact Amount</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Not uploaded</span>
                        <?php endif; ?>
                        <?php if (!empty($p['receipt_file'])): ?>
                            <br><a href="<?php echo h($p['receipt_file']); ?>" target="_blank" class="small">View old receipt</a>
                        <?php endif; ?>
                        <?php if (!empty($p['topup_receipt_file'])): ?>
                            <br><a href="<?php echo h($p['topup_receipt_file']); ?>" target="_blank" class="small">View new top-up receipt</a>
                        <?php endif; ?>
                    </td>
                    <td class="payment-refund-cell">
                        <?php if ($needsRefundAction): ?>
                            <div class="refund-mini-box">
                                <div class="mb-1"><strong>Extra RM <?php echo number_format($overpaidAmount, 2); ?></strong></div>
                                <div class="mb-2"><?php echo refund_status_badge_text($refundStatus); ?></div>
                                <?php if (!empty($p['refund_user_note'])): ?>
                                    <div class="alert alert-warning py-2 px-2 small mb-2">
                                        <strong>User refund comment:</strong><br>
                                        <?php echo nl2br(h($p['refund_user_note'])); ?>
                                        <?php if (!empty($p['refund_issue_reported_at'])): ?>
                                            <br><span class="text-muted">Reported: <?php echo h(date('d M Y h:i A', strtotime($p['refund_issue_reported_at']))); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($refundStatus === 'not required'): ?>
                                    <form method="post" class="mb-0">
                                        <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                                        <button name="request_refund_qr" class="btn btn-sm btn-primary btn-block">Ask User QR</button>
                                    </form>
                                <?php elseif ($refundStatus === 'requested'): ?>
                                    <small class="text-muted d-block">Waiting for user to upload refund QR.</small>
                                <?php elseif ($refundStatus === 'qr uploaded'): ?>
                                    <a href="<?php echo h($p['refund_qr_file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary btn-block mb-2">View User QR</a>
                                    <form method="post" enctype="multipart/form-data" class="refund-upload-form">
                                        <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                                        <input type="file" name="refund_receipt_file" class="form-control-file small mb-2" accept="image/*,.pdf" required>
                                        <button name="upload_refund_receipt" class="btn btn-sm btn-success btn-block">Upload Refund Receipt</button>
                                    </form>
                                <?php elseif ($refundStatus === 'paid'): ?>
                                    <?php if (!empty($p['refund_qr_file'])): ?>
                                        <a href="<?php echo h($p['refund_qr_file']); ?>" target="_blank" class="small d-block">View user QR</a>
                                    <?php endif; ?>
                                    <?php if (!empty($p['refund_receipt_file'])): ?>
                                        <a href="<?php echo h($p['refund_receipt_file']); ?>" target="_blank" class="small d-block">View refund receipt</a>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1">Waiting for user to tick complete.</small>
                                    <?php if (!empty($p['refund_user_note'])): ?>
                                        <form method="post" enctype="multipart/form-data" class="refund-upload-form mt-2">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                                            <input type="file" name="refund_receipt_file" class="form-control-file small mb-2" accept="image/*,.pdf" required>
                                            <button name="upload_refund_receipt" class="btn btn-sm btn-success btn-block">Upload Corrected Proof</button>
                                            <small class="text-muted d-block mt-1">Use this if the user says the refund amount is wrong.</small>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($refundStatus === 'completed'): ?>
                                    <?php if (!empty($p['refund_receipt_file'])): ?>
                                        <a href="<?php echo h($p['refund_receipt_file']); ?>" target="_blank" class="small d-block">View refund receipt</a>
                                    <?php endif; ?>
                                    <small class="text-success d-block mt-1">User confirmed refund received.</small>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($hasUnderpayCase): ?>
                            <div class="refund-mini-box underpay-mini-box">
                                <div class="mb-1"><strong>Need RM <?php echo number_format(max(0, (float)$p['amount'] - ($totalPaid ?? 0)), 2); ?></strong></div>
                                <div class="mb-2"><?php echo underpay_status_badge_text($underpayStatus); ?></div>

                                <?php if ($underpayStatus === 'not required'): ?>
                                    <form method="post" class="mb-0">
                                        <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                                        <button name="request_topup_payment" class="btn btn-sm btn-danger btn-block">Ask Top-Up</button>
                                    </form>
                                    <small class="text-muted d-block mt-1">Sends comment: not enough payment, resubmit old and new receipt.</small>
                                <?php elseif ($underpayStatus === 'requested'): ?>
                                    <small class="text-muted d-block">Waiting for user to upload new receipt.</small>
                                    <?php if (!empty($p['underpay_message'])): ?>
                                        <small class="d-block mt-1"><?php echo h($p['underpay_message']); ?></small>
                                    <?php endif; ?>
                                <?php elseif ($underpayStatus === 'submitted'): ?>
                                    <small class="text-info d-block">User submitted additional payment.</small>
                                    <?php if (!empty($p['topup_receipt_file'])): ?>
                                        <a href="<?php echo h($p['topup_receipt_file']); ?>" target="_blank" class="small d-block">View new receipt</a>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1">Check both receipts, then update status.</small>
                                <?php elseif ($underpayStatus === 'completed'): ?>
                                    <small class="text-success d-block">Top-up verified and completed.</small>
                                    <?php if (!empty($p['topup_receipt_file'])): ?>
                                        <a href="<?php echo h($p['topup_receipt_file']); ?>" target="_blank" class="small d-block">View top-up receipt</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">No action needed</span>
                        <?php endif; ?>
                    </td>
                    <td class="payment-method-cell"><?php echo h($p['bank_name'] ?: $p['payment_method']); ?><br><small class="text-muted"><?php echo h($p['gateway_provider'] ?: '-'); ?></small></td>
                    <td class="payment-ref"><?php echo h($p['bank_reference'] ?: '-'); ?></td>
                    <td class="payment-status-cell"><?php echo status_badge($p['status']); ?></td>
                    <td class="payment-update-cell">
                        <form method="post" class="payment-status-form">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                            <select name="status" class="custom-select custom-select-sm status-auto-select" onchange="this.form.submit()">
                                <?php foreach(['pending','pending verification','completed','failed','refunded'] as $s): ?>
                                    <option value="<?php echo h($s); ?>" <?php echo $p['status']===$s?'selected':''; ?>><?php echo h(ucwords($s)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted payment-action-note">Auto-saves after change</small>
                            <noscript><button name="update_status" value="1" class="btn btn-sm btn-primary mt-2 btn-block">Save</button></noscript>
                        </form>
                    </td>
                    <td class="payment-receipt-cell"><a href="receipt.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_footer(); ?>
