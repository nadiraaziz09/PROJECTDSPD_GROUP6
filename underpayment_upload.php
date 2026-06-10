<?php
include 'layout.php';
require_role(1);

$uid = current_user_id();
$payId = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn,
    "SELECT pay.*, pr.name product_name, pr.photo
     FROM product_payments pay
     JOIN products pr ON pay.product_id=pr.id
     WHERE pay.id=? AND pay.user_id=?
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $payId, $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pay = mysqli_fetch_assoc($result);

if (!$pay) {
    flash('error', 'Payment record not found.');
    header('Location: payment_history.php');
    exit();
}

$basePaid = ($pay['paid_amount'] !== null) ? (float)$pay['paid_amount'] : null;
$topupPaid = ($pay['topup_paid_amount'] !== null) ? (float)$pay['topup_paid_amount'] : 0;
$totalPaid = $basePaid !== null ? $basePaid + $topupPaid : null;
$remainingAmount = $totalPaid !== null ? max(0, (float)$pay['amount'] - $totalPaid) : (float)$pay['amount'];
$underpayStatus = strtolower(trim((string)($pay['underpay_status'] ?? 'not required')));
if ($underpayStatus === '') $underpayStatus = 'not required';

if (!in_array($underpayStatus, ['requested','submitted','completed'], true)) {
    flash('error', 'This payment does not have a top-up request from staff/admin yet.');
    header('Location: payment_history.php');
    exit();
}

function save_topup_receipt_upload($field, $payId) {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Please choose your new payment receipt.');
        return '';
    }

    $allowedExt = ['jpg','jpeg','png','webp','pdf'];
    $originalName = $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        flash('error', 'Receipt must be JPG, PNG, JPEG, WEBP, or PDF.');
        return '';
    }

    if ((int)$_FILES[$field]['size'] > 5 * 1024 * 1024) {
        flash('error', 'Receipt file is too large. Maximum size is 5MB.');
        return '';
    }

    $uploadDir = __DIR__ . '/uploads/topup_receipts';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $safeFile = 'topup_receipt_' . $payId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $safeFile;
    $relativePath = 'uploads/topup_receipts/' . $safeFile;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $targetPath)) {
        flash('error', 'Top-up receipt upload failed. Please try again.');
        return '';
    }

    return $relativePath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_topup_receipt'])) {
    if (!in_array($underpayStatus, ['requested','submitted'], true)) {
        flash('error', 'Top-up receipt cannot be changed after the payment is completed.');
        header('Location: underpayment_upload.php?id=' . $payId);
        exit();
    }

    $topupAmount = (float)($_POST['topup_paid_amount'] ?? 0);
    if ($topupAmount <= 0) {
        flash('error', 'Please enter the additional amount you paid.');
        header('Location: underpayment_upload.php?id=' . $payId);
        exit();
    }

    $receiptFile = save_topup_receipt_upload('topup_receipt_file', $payId);
    if ($receiptFile !== '') {
        $newStatus = 'submitted';
        $paymentStatus = 'pending verification';
        $missingAfterTopup = max(0, (float)$pay['amount'] - (($basePaid ?? 0) + $topupAmount));
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET topup_receipt_file=?, topup_paid_amount=?, underpay_status=?, underpay_amount=?, status=?, underpay_updated_at=NOW() WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'sdsdsii', $receiptFile, $topupAmount, $newStatus, $missingAfterTopup, $paymentStatus, $payId, $uid);
        mysqli_stmt_execute($stmt);
        flash('success', 'Your new receipt has been submitted. Staff/admin will check your old receipt and new receipt together.');
    }
    header('Location: underpayment_upload.php?id=' . $payId);
    exit();
}

page_header('Top-Up Payment Upload - PawFect Home', 'payments');
page_title('Payment Top-Up Request', 'Submit a new receipt when your first payment was not enough.');
?>
<div class="container py-5">
    <div class="refund-card card-clean p-4 p-md-5 mx-auto">
        <div class="d-flex align-items-center mb-4">
            <img src="<?php echo h(pawfect_image_src($pay['photo'], 'img/feature.jpg')); ?>" class="refund-product-img mr-3">
            <div>
                <h4 class="mb-1"><?php echo h($pay['product_name']); ?></h4>
                <p class="mb-0 text-muted">Payment Ref: <?php echo h($pay['bank_reference'] ?: $pay['transaction_id']); ?></p>
            </div>
        </div>

        <div class="refund-summary-box mb-4">
            <div><span>Required</span><strong>RM <?php echo number_format((float)$pay['amount'], 2); ?></strong></div>
            <div><span>First Paid</span><strong>RM <?php echo $basePaid !== null ? number_format($basePaid, 2) : '-'; ?></strong></div>
            <div><span>Need Top-Up</span><strong class="text-danger">RM <?php echo number_format($remainingAmount, 2); ?></strong></div>
        </div>

        <?php if (!empty($pay['underpay_message'])): ?>
            <div class="alert alert-danger">
                <strong>Message from staff/admin:</strong><br>
                <?php echo h($pay['underpay_message']); ?>
            </div>
        <?php endif; ?>

        <div class="topup-receipt-box mb-4">
            <h6 class="mb-2"><i class="fas fa-file-alt mr-2 text-primary"></i>Receipt Proof</h6>
            <p class="mb-2"><strong>Old receipt:</strong>
                <?php if (!empty($pay['receipt_file'])): ?>
                    <a href="<?php echo h($pay['receipt_file']); ?>" target="_blank">View old receipt</a>
                <?php else: ?>
                    <span class="text-muted">Not uploaded</span>
                <?php endif; ?>
            </p>
            <p class="mb-0"><strong>New top-up receipt:</strong>
                <?php if (!empty($pay['topup_receipt_file'])): ?>
                    <a href="<?php echo h($pay['topup_receipt_file']); ?>" target="_blank">View new receipt</a>
                    <span class="text-muted ml-2">(RM <?php echo number_format($topupPaid, 2); ?>)</span>
                <?php else: ?>
                    <span class="text-muted">Not uploaded yet</span>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($underpayStatus === 'completed'): ?>
            <div class="alert alert-success mb-3">Your top-up payment has been verified and completed by staff/admin.</div>
            <a href="payment_history.php" class="btn btn-outline-primary">Back to Payment History</a>
        <?php else: ?>
            <div class="alert alert-warning">
                Please make the additional payment, then upload the <strong>new receipt</strong>. Your old receipt is already saved, so staff/admin can check both receipts together.
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Additional Amount You Paid (RM)</label>
                    <input type="number" name="topup_paid_amount" class="form-control" step="0.01" min="0.01" value="<?php echo h(number_format($remainingAmount, 2, '.', '')); ?>" required>
                    <small class="form-text text-muted">Enter only the new additional amount, not the old amount.</small>
                </div>
                <div class="form-group">
                    <label>Upload New Receipt Image / PDF</label>
                    <input type="file" name="topup_receipt_file" class="form-control-file" accept="image/*,.pdf" required>
                </div>
                <button name="submit_topup_receipt" class="btn btn-primary">
                    <i class="fas fa-upload mr-2"></i>Submit New Receipt
                </button>
                <a href="payment_history.php" class="btn btn-outline-primary ml-2">Back</a>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php page_footer(); ?>
