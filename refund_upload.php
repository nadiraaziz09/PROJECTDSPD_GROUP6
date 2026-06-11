<?php
include 'layout.php';
require_role(1);

$uid = current_user_id();
$payId = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT pay.*, pr.name product_name, pr.photo FROM product_payments pay JOIN products pr ON pay.product_id=pr.id WHERE pay.id=? AND pay.user_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $payId, $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pay = mysqli_fetch_assoc($result);

if (!$pay) {
    flash('error', 'Payment record not found.');
    header('Location: payment_history.php');
    exit();
}

$isOverpaid = isset($pay['paid_amount']) && $pay['paid_amount'] !== null && (float)$pay['paid_amount'] > (float)$pay['amount'];
$overpaidAmount = $isOverpaid ? ((float)$pay['paid_amount'] - (float)$pay['amount']) : 0;
$refundStatus = strtolower(trim((string)($pay['refund_status'] ?? 'not required')));
if ($refundStatus === '') $refundStatus = 'not required';
$refundUserNote = trim((string)($pay['refund_user_note'] ?? ''));

if (!$isOverpaid) {
    flash('error', 'This payment does not have an overpayment refund request.');
    header('Location: payment_history.php');
    exit();
}

function save_refund_qr_upload($field, $payId) {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Please choose your refund QR image.');
        return '';
    }

    $allowedExt = ['jpg','jpeg','png','webp'];
    $originalName = $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        flash('error', 'Refund QR must be JPG, PNG, JPEG, or WEBP image.');
        return '';
    }

    if ((int)$_FILES[$field]['size'] > 5 * 1024 * 1024) {
        flash('error', 'Refund QR image is too large. Maximum size is 5MB.');
        return '';
    }

    $uploadDir = __DIR__ . '/uploads/refund_qr';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $safeFile = 'refund_qr_' . $payId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $safeFile;
    $relativePath = 'uploads/refund_qr/' . $safeFile;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $targetPath)) {
        flash('error', 'Refund QR upload failed. Please try again.');
        return '';
    }

    return $relativePath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_refund_qr'])) {
    if (!in_array($refundStatus, ['requested','qr uploaded'], true)) {
        flash('error', 'Refund QR can only be uploaded after staff/admin requests it.');
        header('Location: refund_upload.php?id=' . $payId);
        exit();
    }

    $qrFile = save_refund_qr_upload('refund_qr_file', $payId);
    if ($qrFile !== '') {
        $newStatus = 'qr uploaded';
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET refund_qr_file=?, refund_status=?, refund_amount=?, refund_updated_at=NOW() WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'ssdii', $qrFile, $newStatus, $overpaidAmount, $payId, $uid);
        mysqli_stmt_execute($stmt);
        flash('success', 'Your refund QR has been uploaded. Staff/admin can now pay back the extra amount.');
    }
    header('Location: refund_upload.php?id=' . $payId);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_refund_issue'])) {
    if ($refundStatus !== 'paid' || empty($pay['refund_receipt_file'])) {
        flash('error', 'You can only send a refund comment after staff/admin uploads the refund proof.');
        header('Location: refund_upload.php?id=' . $payId);
        exit();
    }

    $refundNote = trim((string)($_POST['refund_user_note'] ?? ''));
    if ($refundNote === '') {
        flash('error', 'Please write the issue first, for example if the refund amount is less or more than expected.');
        header('Location: refund_upload.php?id=' . $payId);
        exit();
    }
    if (mb_strlen($refundNote) > 1000) {
        $refundNote = mb_substr($refundNote, 0, 1000);
    }

    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET refund_user_note=?, refund_issue_reported_at=NOW(), refund_updated_at=NOW() WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'sii', $refundNote, $payId, $uid);
    mysqli_stmt_execute($stmt);
    flash('success', 'Your refund comment has been sent to staff/admin. Please wait for them to check it.');
    header('Location: refund_upload.php?id=' . $payId);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_refund_received'])) {
    if ($refundStatus !== 'paid' || empty($pay['refund_receipt_file'])) {
        flash('error', 'You can only complete this refund after staff/admin uploads the refund receipt.');
        header('Location: refund_upload.php?id=' . $payId);
        exit();
    }
    if (empty($_POST['refund_received_tick'])) {
        flash('error', 'Please tick the confirmation box first.');
        header('Location: refund_upload.php?id=' . $payId);
        exit();
    }
    $newStatus = 'completed';
    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET refund_status=?, refund_updated_at=NOW() WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'sii', $newStatus, $payId, $uid);
    mysqli_stmt_execute($stmt);
    flash('success', 'Refund marked as completed. Thank you for confirming.');
    header('Location: payment_history.php');
    exit();
}

page_header('Refund QR Upload - PawFect Home', 'payments');
page_title('Overpayment Refund', 'Upload your refund QR and confirm once the extra payment is returned.');
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
            <div><span>Paid</span><strong>RM <?php echo number_format((float)$pay['paid_amount'], 2); ?></strong></div>
            <div><span>Refund Amount</span><strong class="text-primary">RM <?php echo number_format($overpaidAmount, 2); ?></strong></div>
        </div>

        <?php if ($refundStatus === 'not required'): ?>
            <div class="alert alert-info mb-0">Staff/admin has not requested your refund QR yet. Please wait for the request to appear in your payment history.</div>
        <?php elseif (in_array($refundStatus, ['requested','qr uploaded'], true)): ?>
            <div class="alert alert-warning">
                Staff/admin needs your QR code to return the extra RM <?php echo number_format($overpaidAmount, 2); ?>.
            </div>

            <?php if (!empty($pay['refund_qr_file'])): ?>
                <div class="mb-3">
                    <strong>Your uploaded refund QR:</strong><br>
                    <a href="<?php echo h($pay['refund_qr_file']); ?>" target="_blank">View uploaded QR</a>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Upload Your Refund QR Code</label>
                    <input type="file" name="refund_qr_file" class="form-control-file" accept="image/*" required>
                    <small class="form-text text-muted">Upload your DuitNow / bank / e-wallet QR image for staff/admin to scan and refund.</small>
                </div>
                <button name="upload_refund_qr" class="btn btn-primary">
                    <i class="fas fa-qrcode mr-2"></i>Submit Refund QR
                </button>
                <a href="payment_history.php" class="btn btn-outline-primary ml-2">Back</a>
            </form>
        <?php elseif ($refundStatus === 'paid'): ?>
            <div class="alert alert-success">
                Staff/admin has uploaded refund proof. Please check the receipt, then tick complete only if you received the correct refund amount.
            </div>
            <?php if (!empty($pay['refund_receipt_file'])): ?>
                <p><strong>Refund receipt/proof:</strong> <a href="<?php echo h($pay['refund_receipt_file']); ?>" target="_blank">View refund receipt</a></p>
            <?php endif; ?>

            <?php if ($refundUserNote !== ''): ?>
                <div class="alert alert-warning">
                    <strong>Your refund comment has been sent to staff/admin:</strong><br>
                    <?php echo nl2br(h($refundUserNote)); ?>
                    <br><small>Please wait for staff/admin to check and upload corrected proof if needed.</small>
                </div>
            <?php endif; ?>

            <form method="post" class="mb-4">
                <div class="custom-control custom-checkbox mb-3">
                    <input type="checkbox" class="custom-control-input" id="refund_received_tick" name="refund_received_tick" value="1" required>
                    <label class="custom-control-label" for="refund_received_tick">I have received the correct refund amount of RM <?php echo number_format($overpaidAmount, 2); ?>.</label>
                </div>
                <button name="confirm_refund_received" class="btn btn-success">
                    <i class="fas fa-check-circle mr-2"></i>Tick Complete
                </button>
                <a href="payment_history.php" class="btn btn-outline-primary ml-2">Back</a>
            </form>

            <div class="refund-issue-box">
                <h6 class="mb-2"><i class="fas fa-comment-dots mr-2"></i>Refund amount problem?</h6>
                <p class="text-muted small mb-3">If staff/admin refunded less or more than RM <?php echo number_format($overpaidAmount, 2); ?>, write the issue here. It will appear on the staff/admin payment management page.</p>
                <form method="post">
                    <div class="form-group">
                        <textarea name="refund_user_note" class="form-control" rows="3" maxlength="1000" placeholder="Example: I only received RM 600, but the refund should be RM <?php echo number_format($overpaidAmount, 2); ?>."><?php echo h($refundUserNote); ?></textarea>
                    </div>
                    <button name="submit_refund_issue" class="btn btn-outline-danger">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Refund Comment
                    </button>
                </form>
            </div>
        <?php elseif ($refundStatus === 'completed'): ?>
            <div class="alert alert-success mb-3">You already confirmed this refund as completed.</div>
            <?php if (!empty($pay['refund_receipt_file'])): ?>
                <p><strong>Refund receipt/proof:</strong> <a href="<?php echo h($pay['refund_receipt_file']); ?>" target="_blank">View refund receipt</a></p>
            <?php endif; ?>
            <a href="payment_history.php" class="btn btn-outline-primary">Back to Payment History</a>
        <?php endif; ?>
    </div>
</div>
<?php page_footer(); ?>
