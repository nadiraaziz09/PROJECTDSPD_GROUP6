<?php
include 'layout.php';
include_once 'payment_gateway_config.php';
require_role(1);

$payId = (int)($_GET['id'] ?? 0);
$currentUserId = current_user_id();

$stmt = mysqli_prepare($conn,
    "SELECT pp.*, p.name AS product_name, p.category AS product_category, p.photo AS product_photo
     FROM product_payments pp
     JOIN products p ON p.id = pp.product_id
     WHERE pp.id = ? AND pp.user_id = ?
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $payId, $currentUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pay = mysqli_fetch_assoc($result);

if (!$pay) {
    flash('error', 'Payment record not found.');
    header('Location: products.php');
    exit();
}

// QR timer rule:
// The merchant DuitNow QR is static, so the 15-minute timer is only a payment-attempt timer.
// It must start fresh whenever the user opens the QR payment page again.
// Do not permanently fail the database record just because the page timer ended.
$qrLimitSeconds = 15 * 60;
$qrStartSessionKey = 'qr_payment_attempt_started_' . $payId;
$payStatus = strtolower(trim((string)$pay['status']));
$isPostUpload = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_receipt']));

// Old versions of this page marked QR orders as failed after 15 minutes.
// If there is still no receipt, allow the customer to start a fresh QR attempt.
if ($payStatus === 'failed' && empty($pay['receipt_file']) && stripos((string)$pay['payment_method'], 'QR') !== false) {
    $restoredStatus = 'pending verification';
    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=?, payment_completed=0 WHERE id=? AND user_id=? AND receipt_file IS NULL");
    mysqli_stmt_bind_param($stmt, 'sii', $restoredStatus, $payId, $pay['user_id']);
    mysqli_stmt_execute($stmt);
    $pay['status'] = $restoredStatus;
    $payStatus = $restoredStatus;
}

// On normal page entry/re-entry, restart the 15-minute waiting time.
// On receipt POST, keep the existing start time so server-side validation cannot be bypassed.
if (!$isPostUpload && empty($pay['receipt_file']) && in_array($payStatus, ['pending', 'pending verification'], true)) {
    $_SESSION[$qrStartSessionKey] = time();
}
if (empty($_SESSION[$qrStartSessionKey])) {
    $_SESSION[$qrStartSessionKey] = time();
}

$paymentExpiresAt = ((int)$_SESSION[$qrStartSessionKey]) + $qrLimitSeconds;
$paymentSecondsLeft = max(0, $paymentExpiresAt - time());
$paymentUploadOpen = empty($pay['receipt_file']) && in_array($payStatus, ['pending', 'pending verification'], true);
$paymentExpired = $paymentUploadOpen && $paymentSecondsLeft <= 0;

function qr_payment_items($pay) {
    $items = [];
    if (!empty($pay['cart_items'])) {
        $decoded = json_decode($pay['cart_items'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $items[] = [
                    'name' => $item['name'] ?? 'Pet Needs Item',
                    'category' => $item['category'] ?? '',
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'price' => (float)($item['price'] ?? 0),
                    'subtotal' => (float)($item['subtotal'] ?? 0)
                ];
            }
        }
    }
    if (empty($items)) {
        $items[] = [
            'name' => $pay['product_name'],
            'category' => $pay['product_category'] ?? '',
            'quantity' => (int)$pay['quantity'],
            'price' => ((float)$pay['amount'] / max(1, (int)$pay['quantity'])),
            'subtotal' => (float)$pay['amount']
        ];
    }
    return $items;
}

function merchant_qr_image_src() {
    $possibleFiles = [
        'img/payment-qr.png',
        'img/payment-qr.jpg',
        'img/payment-qr.jpeg',
        'img/duitnow-qr.png',
        'img/duitnow-qr.jpg',
        'img/duitnow-qr.jpeg'
    ];
    foreach ($possibleFiles as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            return $file;
        }
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_receipt'])) {
    if ($paymentExpired || ($paymentUploadOpen && $paymentSecondsLeft <= 0)) {
        unset($_SESSION[$qrStartSessionKey]);
        flash('error', 'This QR payment attempt expired. Please start the QR payment again; the timer will restart for another 15 minutes.');
        header('Location: qr_payment.php?id=' . $payId);
        exit();
    }

    if (!$paymentUploadOpen) {
        flash('error', 'Receipt upload is not available for this payment record.');
        header('Location: qr_payment.php?id=' . $payId);
        exit();
    }

    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    if ($paidAmount <= 0) {
        flash('error', 'Please enter the amount you paid.');
        header('Location: qr_payment.php?id=' . $payId);
        exit();
    }

    if (empty($_FILES['receipt_file']['name']) || ($_FILES['receipt_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Please upload your payment receipt image or PDF.');
        header('Location: qr_payment.php?id=' . $payId);
        exit();
    }

    $allowedExt = ['jpg','jpeg','png','webp','pdf'];
    $originalName = $_FILES['receipt_file']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        flash('error', 'Receipt must be JPG, PNG, WEBP, or PDF.');
        header('Location: qr_payment.php?id=' . $payId);
        exit();
    }

    if ((int)$_FILES['receipt_file']['size'] > 5 * 1024 * 1024) {
        flash('error', 'Receipt file is too large. Maximum size is 5MB.');
        header('Location: qr_payment.php?id=' . $payId);
        exit();
    }

    $uploadDir = __DIR__ . '/uploads/receipts';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    $safeFile = 'receipt_' . $payId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . '/' . $safeFile;
    $relativePath = 'uploads/receipts/' . $safeFile;

    if (!move_uploaded_file($_FILES['receipt_file']['tmp_name'], $targetPath)) {
        flash('error', 'Receipt upload failed. Please try again.');
        header('Location: qr_payment.php?id=' . $payId);
        exit();
    }

    $status = 'pending verification';
    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET receipt_file=?, paid_amount=?, status=? WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'sdsii', $relativePath, $paidAmount, $status, $payId, $pay['user_id']);
    mysqli_stmt_execute($stmt);
    unset($_SESSION[$qrStartSessionKey]);

    if ($paidAmount > (float)$pay['amount']) {
        flash('success', 'Receipt uploaded. Overpayment report has been sent to admin for checking.');
    } else {
        flash('success', 'Receipt uploaded. Staff/admin will verify your payment.');
    }
    header('Location: receipt.php?id=' . $payId);
    exit();
}

$paymentItems = qr_payment_items($pay);
$merchantQr = merchant_qr_image_src();
$qrContent = 'DUITNOW:' . BANK_ACCOUNT_NUMBER . ':RM' . $pay['amount'] . ':' . $pay['transaction_id'];
$isCart = !empty($pay['cart_items']);

page_header('QR Code Payment - PawFect Home', 'payments');
page_title('Scan & Pay via QR Code', 'Pay using the QR code, then upload your payment receipt for verification.');
?>

<style>
.qr-card {
    max-width: 560px;
    margin: 0 auto;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(31,36,40,.12);
    overflow: hidden;
    background: #fff;
}
.qr-header {
    background: linear-gradient(135deg, rgba(31,36,40,.90), rgba(175,39,8,.85));
    color: #fff;
    padding: 28px 32px 20px;
}
.qr-header h4 { margin: 0; font-size: 1.35rem; font-weight: 700; }
.qr-header p  { margin: 5px 0 0; opacity: .85; font-size: .9rem; }
.qr-body { background: #fff; padding: 30px 28px; text-align: center; }
#qrcode, .real-qr-frame {
    display: inline-block;
    padding: 14px;
    background: #fff;
    border: 3px solid #af2708;
    border-radius: 12px;
    margin-top: 6px;
}
.real-qr-frame img { width: 240px; max-width: 100%; height: auto; display: block; }
.amount-badge {
    font-size: 2.1rem;
    font-weight: 800;
    color: #af2708;
    margin: 14px 0 4px;
}
.ref-text { font-size: .78rem; color: #888; letter-spacing: .04em; }
.timer-badge {
    background: #fff7f3;
    border: 1px solid #af2708;
    border-radius: 8px;
    padding: 7px 16px;
    font-size: .85rem;
    color: #af2708;
    display: inline-block;
    margin-top: 12px;
    font-weight: 600;
}
.steps, .receipt-upload-box {
    text-align: left;
    background: #f8f9f6;
    border-left: 4px solid #89a07e;
    border-radius: 10px;
    padding: 14px 18px;
    margin-top: 20px;
    font-size: .88rem;
}
.steps strong { color: #1f2428; }
.steps li { margin-bottom: 6px; color: #444; }
.qr-setup-note {
    background: #fff7f3;
    border: 1px dashed #af2708;
    border-radius: 10px;
    padding: 12px 14px;
    margin-top: 16px;
    font-size: .84rem;
    color: #7a2a18;
    text-align: left;
}
.qr-items-list {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 10px 14px;
    margin-top: 10px;
    text-align: left;
}
.qr-items-list .item-line {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid #f0f0f0;
    padding: 7px 0;
}
.qr-items-list .item-line:last-child { border-bottom: 0; }
.divider { border-top: 1px dashed #e0d6d2; margin: 22px 0; }
.btn-receipt {
    background: #89a07e;
    border: none;
    color: #fff;
    padding: 12px 30px;
    font-size: 1rem;
    font-weight: 700;
    border-radius: 10px;
    margin-top: 12px;
    display: inline-block;
    transition: background .2s, transform .15s;
    text-decoration: none;
}
.btn-receipt:hover { background: #6e8563; color: #fff; text-decoration: none; transform: translateY(-2px); }
.btn-back {
    color: #af2708;
    border: 1px solid #af2708;
    border-radius: 8px;
    padding: 7px 20px;
    font-size: .88rem;
    display: inline-block;
    margin-top: 14px;
    text-decoration: none;
    transition: background .2s, color .2s;
}
.btn-back:hover { background: #af2708; color: #fff; text-decoration: none; }
</style>

<div class="container py-5">
    <div class="qr-card">
        <div class="qr-header">
            <h4><i class="fas fa-qrcode mr-2"></i>DuitNow QR Payment</h4>
            <p>Scan with any Malaysian banking app or e-wallet</p>
        </div>

        <div class="qr-body">
            <p class="text-muted small mb-1">You are paying for:</p>
            <strong style="color:#1f2428;"><?php echo $isCart ? 'Cart Checkout' : h($pay['product_name']); ?></strong>
            <span class="badge badge-light ml-1">Qty: <?php echo (int)$pay['quantity']; ?></span>

            <div class="qr-items-list">
                <?php foreach ($paymentItems as $item): ?>
                    <div class="item-line">
                        <span><?php echo h($item['name']); ?> <small class="text-muted">x<?php echo (int)$item['quantity']; ?></small></span>
                        <strong>RM <?php echo number_format((float)$item['subtotal'], 2); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-3">
                <?php if ($merchantQr): ?>
                    <div class="real-qr-frame"><img src="<?php echo h($merchantQr); ?>" alt="Merchant QR Code"></div>
                <?php else: ?>
                    <div id="qrcode"></div>
                <?php endif; ?>
            </div>

            <div class="amount-badge">RM <?php echo h(number_format((float)$pay['amount'], 2)); ?></div>
            <div class="ref-text">Ref: <?php echo h($pay['transaction_id']); ?></div>

            <div class="timer-badge" id="paymentTimer" data-seconds-left="<?php echo (int)$paymentSecondsLeft; ?>">
                <i class="fas fa-clock mr-1"></i>
                <?php if ($paymentExpired): ?>
                    Payment time expired
                <?php elseif (!$paymentUploadOpen): ?>
                    Receipt already submitted
                <?php else: ?>
                    Complete payment within <strong><span id="timerText">15:00</span></strong>
                <?php endif; ?>
            </div>

            <div class="steps">
                <strong>How to pay:</strong>
                <ol class="mt-2 mb-0 pl-3">
                    <li>Open your banking app or e-wallet.</li>
                    <li>Tap <strong>Scan &amp; Pay</strong> or <strong>DuitNow QR</strong>.</li>
                    <li>Scan the QR code above.</li>
                    <li>Pay exactly <strong>RM <?php echo h(number_format((float)$pay['amount'], 2)); ?></strong>.</li>
                    <li>Download or screenshot your payment receipt.</li>
                </ol>
            </div>

            <div class="divider"></div>

            <div class="receipt-upload-box">
                <h6 class="mb-2"><i class="fas fa-upload text-primary mr-2"></i>Upload Real Payment Receipt</h6>
                <?php if ($paymentExpired): ?>
                    <div class="alert alert-danger mb-0">This QR payment attempt has expired. Refresh this QR page to start a new 15-minute timer.</div>
                <?php elseif (!$paymentUploadOpen): ?>
                    <div class="alert alert-success mb-0">Receipt has already been submitted for this payment.</div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" id="receiptUploadForm">
                        <div class="form-group">
                            <label>Amount You Paid (RM)</label>
                            <input type="number" name="paid_amount" class="form-control" step="0.01" min="0.01" value="<?php echo h(number_format((float)$pay['amount'], 2, '.', '')); ?>" required>
                            <small class="form-text text-muted">If this amount is higher than the required amount, admin will see an overpayment report.</small>
                        </div>
                        <div class="form-group">
                            <label>Receipt Image / PDF</label>
                            <input type="file" name="receipt_file" class="form-control-file" accept="image/*,.pdf" required>
                        </div>
                        <button name="upload_receipt" class="btn btn-primary btn-block">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Receipt for Verification
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!empty($pay['receipt_file'])): ?>
                <div class="alert alert-success small mt-3 mb-0">Receipt already uploaded. You may view the receipt record below.</div>
            <?php endif; ?>

            <a href="receipt.php?id=<?php echo $payId; ?>" class="btn-receipt">
                <i class="fas fa-receipt mr-2"></i>View Payment Receipt
            </a>

            <br>
            <a href="products.php" class="btn-back">
                <i class="fas fa-arrow-left mr-1"></i>Back to Products
            </a>
        </div>
    </div>
</div>

<?php if (!$merchantQr): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qrcode'), {
    text: <?php echo json_encode($qrContent); ?>,
    width:  220,
    height: 220,
    colorDark:  '#af2708',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
});
</script>
<?php endif; ?>
<script>
(function () {
    var timer = document.getElementById('paymentTimer');
    var timerText = document.getElementById('timerText');
    var form = document.getElementById('receiptUploadForm');
    if (!timer || !timerText) return;
    var secondsLeft = parseInt(timer.getAttribute('data-seconds-left') || '0', 10);
    function renderTimer() {
        if (secondsLeft <= 0) {
            timer.innerHTML = '<i class="fas fa-clock mr-1"></i> Payment time expired';
            if (form) {
                var button = form.querySelector('button[name="upload_receipt"]');
                if (button) button.disabled = true;
                if (!document.getElementById('qrExpiredNotice')) {
                    var notice = document.createElement('div');
                    notice.id = 'qrExpiredNotice';
                    notice.className = 'alert alert-danger mt-3 mb-0';
                    notice.textContent = 'This QR payment attempt has expired. Refresh this QR page to start a new 15-minute timer.';
                    form.parentNode.insertBefore(notice, form);
                }
            }
            return;
        }
        var minutes = Math.floor(secondsLeft / 60);
        var seconds = secondsLeft % 60;
        timerText.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        secondsLeft -= 1;
        setTimeout(renderTimer, 1000);
    }
    renderTimer();
})();
</script>
<?php page_footer(); ?>
