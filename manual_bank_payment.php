<?php
include 'layout.php';
include_once 'payment_gateway_config.php';
include_once 'payment_expiry_helpers.php';
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

if (!$pay || $pay['payment_method'] !== 'Manual Bank In') {
    flash('error', 'Manual bank in payment record not found.');
    header('Location: products.php');
    exit();
}

// Manual bank in expiry: 3 days from checkout creation.
// If MySQL/PHP timezone creates a future timestamp, use current time so the page still shows exactly 3 days.
$createdTs = strtotime($pay['created_at']);
if (!$createdTs || $createdTs > time()) {
    $createdTs = time();
}
$paymentExpiresAt = $createdTs + (3 * 24 * 60 * 60);
$paymentSecondsLeft = max(0, min(3 * 24 * 60 * 60, $paymentExpiresAt - time()));
$paymentUploadOpen = empty($pay['receipt_file']) && in_array(strtolower(trim((string)$pay['status'])), ['pending','pending verification'], true);
$paymentExpired = $paymentUploadOpen && $paymentSecondsLeft <= 0;

if ($paymentExpired && strtolower(trim((string)$pay['status'])) !== 'failed') {
    $failedStatus = 'failed';
    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=?, payment_completed=0 WHERE id=? AND user_id=? AND receipt_file IS NULL");
    mysqli_stmt_bind_param($stmt, 'sii', $failedStatus, $payId, $pay['user_id']);
    mysqli_stmt_execute($stmt);
    $pay['status'] = $failedStatus;
}

function manual_bank_payment_items($pay) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_receipt'])) {
    if ($paymentExpired || ($paymentUploadOpen && $paymentSecondsLeft <= 0)) {
        $failedStatus = 'failed';
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=?, payment_completed=0 WHERE id=? AND user_id=? AND receipt_file IS NULL");
        mysqli_stmt_bind_param($stmt, 'sii', $failedStatus, $payId, $pay['user_id']);
        mysqli_stmt_execute($stmt);
        flash('error', 'Manual bank in payment expired. Please start a new checkout and upload the receipt within 3 days.');
        header('Location: products.php');
        exit();
    }

    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    if ($paidAmount <= 0) {
        flash('error', 'Please enter the amount you transferred.');
        header('Location: manual_bank_payment.php?id=' . $payId);
        exit();
    }

    if (empty($_FILES['receipt_file']['name']) || ($_FILES['receipt_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Please upload your bank transfer receipt image or PDF.');
        header('Location: manual_bank_payment.php?id=' . $payId);
        exit();
    }

    $allowedExt = ['jpg','jpeg','png','webp','pdf'];
    $originalName = $_FILES['receipt_file']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        flash('error', 'Receipt must be JPG, PNG, WEBP, or PDF.');
        header('Location: manual_bank_payment.php?id=' . $payId);
        exit();
    }

    if ((int)$_FILES['receipt_file']['size'] > 5 * 1024 * 1024) {
        flash('error', 'Receipt file is too large. Maximum size is 5MB.');
        header('Location: manual_bank_payment.php?id=' . $payId);
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
        header('Location: manual_bank_payment.php?id=' . $payId);
        exit();
    }

    $status = 'pending verification';
    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET receipt_file=?, paid_amount=?, status=? WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'sdsii', $relativePath, $paidAmount, $status, $payId, $pay['user_id']);
    mysqli_stmt_execute($stmt);

    if ($paidAmount > (float)$pay['amount']) {
        flash('success', 'Receipt uploaded. Overpayment report has been sent to admin for checking.');
    } else {
        flash('success', 'Receipt uploaded. Staff/admin will verify your bank in payment.');
    }
    header('Location: receipt.php?id=' . $payId);
    exit();
}

$paymentItems = manual_bank_payment_items($pay);
$isCart = !empty($pay['cart_items']);
$expiryLabel = date('d/m/Y h:i A', $paymentExpiresAt);

page_header('Manual Bank In - PawFect Home', 'payments');
page_title('Manual Bank In Payment', 'Transfer to PawFect Home bank account and upload your receipt within 3 days.');
?>

<style>
.bank-card {
    max-width: 620px;
    margin: 0 auto;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(31,36,40,.12);
    overflow: hidden;
    background: #fff;
}
.bank-header {
    background: linear-gradient(135deg, rgba(31,36,40,.90), rgba(175,39,8,.85));
    color: #fff;
    padding: 28px 32px 20px;
}
.bank-header h4 { margin: 0; font-size: 1.35rem; font-weight: 700; }
.bank-header p  { margin: 5px 0 0; opacity: .85; font-size: .9rem; }
.bank-body { background: #fff; padding: 30px 28px; text-align: center; }
.bank-box {
    background:#fff7f3;
    border:2px solid rgba(175,39,8,.22);
    border-radius:14px;
    padding:18px;
    margin:18px 0;
    text-align:left;
}
.bank-row {
    display:flex;
    justify-content:space-between;
    gap:16px;
    padding:10px 0;
    border-bottom:1px solid rgba(175,39,8,.12);
}
.bank-row:last-child { border-bottom:0; }
.bank-label { color:#777; font-size:.9rem; }
.bank-value { font-weight:800; color:#1f2428; text-align:right; word-break:break-word; }
.bank-value.amount { color:#af2708; font-size:1.2rem; }
.copy-btn {
    border:1px solid #af2708;
    color:#af2708;
    background:#fff;
    border-radius:6px;
    padding:3px 8px;
    font-size:.75rem;
    margin-left:8px;
    cursor:pointer;
}
.copy-btn:hover { background:#af2708; color:#fff; }
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
.bank-items-list {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 10px 14px;
    margin-top: 10px;
    text-align: left;
}
.bank-items-list .item-line {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid #f0f0f0;
    padding: 7px 0;
}
.bank-items-list .item-line:last-child { border-bottom: 0; }
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
@media(max-width:575px){ .bank-row{display:block;} .bank-value{text-align:left;margin-top:4px;} }
</style>

<div class="container py-5">
    <div class="bank-card">
        <div class="bank-header">
            <h4><i class="fas fa-money-check-alt mr-2"></i>Manual Bank In</h4>
            <p>Transfer using your banking app or ATM, then upload the receipt</p>
        </div>

        <div class="bank-body">
            <p class="text-muted small mb-1">You are paying for:</p>
            <strong style="color:#1f2428;"><?php echo $isCart ? 'Cart Checkout' : h($pay['product_name']); ?></strong>
            <span class="badge badge-light ml-1">Qty: <?php echo (int)$pay['quantity']; ?></span>

            <div class="bank-items-list">
                <?php foreach ($paymentItems as $item): ?>
                    <div class="item-line">
                        <span><?php echo h($item['name']); ?> <small class="text-muted">x<?php echo (int)$item['quantity']; ?></small></span>
                        <strong>RM <?php echo number_format((float)$item['subtotal'], 2); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="bank-box">
                <div class="bank-row">
                    <span class="bank-label">Account Name</span>
                    <span class="bank-value"><?php echo h(BANK_ACCOUNT_NAME); ?></span>
                </div>
                <div class="bank-row">
                    <span class="bank-label">Bank</span>
                    <span class="bank-value"><?php echo h(BANK_ACCOUNT_BANK); ?></span>
                </div>
                <div class="bank-row">
                    <span class="bank-label">Account Number</span>
                    <span class="bank-value">
                        <span id="accountNo"><?php echo h(BANK_ACCOUNT_NUMBER); ?></span>
                        <button type="button" class="copy-btn" onclick="copyAccountNo()">Copy</button>
                    </span>
                </div>
                <div class="bank-row">
                    <span class="bank-label">Amount to Pay</span>
                    <span class="bank-value amount">RM <?php echo h(number_format((float)$pay['amount'], 2)); ?></span>
                </div>
                <div class="bank-row">
                    <span class="bank-label">Payment Reference</span>
                    <span class="bank-value"><?php echo h($pay['transaction_id']); ?></span>
                </div>
                <div class="bank-row">
                    <span class="bank-label">Pay Before</span>
                    <span class="bank-value"><?php echo h($expiryLabel); ?></span>
                </div>
            </div>

            <div class="timer-badge" id="paymentTimer" data-seconds-left="<?php echo (int)$paymentSecondsLeft; ?>">
                <i class="fas fa-clock mr-1"></i>
                <?php if ($paymentExpired): ?>
                    Manual bank in payment expired
                <?php elseif (!$paymentUploadOpen): ?>
                    Receipt already submitted
                <?php else: ?>
                    Upload receipt before <strong><span id="timerText">3 days</span></strong>
                <?php endif; ?>
            </div>

            <div class="steps">
                <strong>How to pay:</strong>
                <ol class="mt-2 mb-0 pl-3">
                    <li>Open your banking app, online banking, CDM, or ATM transfer.</li>
                    <li>Transfer to the PawFect Home account number shown above.</li>
                    <li>Pay exactly <strong>RM <?php echo h(number_format((float)$pay['amount'], 2)); ?></strong>.</li>
                    <li>Use this reference: <strong><?php echo h($pay['transaction_id']); ?></strong>.</li>
                    <li>Upload your receipt here within <strong>3 days</strong>.</li>
                </ol>
            </div>

            <div class="divider"></div>

            <div class="receipt-upload-box">
                <h6 class="mb-2"><i class="fas fa-upload text-primary mr-2"></i>Upload Bank In Receipt</h6>
                <?php if ($paymentExpired): ?>
                    <div class="alert alert-danger mb-0">This manual bank in payment has expired. Please start a new checkout from Pet Needs.</div>
                <?php elseif (!$paymentUploadOpen): ?>
                    <div class="alert alert-success mb-0">Receipt has already been submitted for this payment.</div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" id="receiptUploadForm">
                        <div class="form-group">
                            <label>Amount You Transferred (RM)</label>
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

            <?php if (!empty($pay['receipt_file']) || $pay['paid_amount'] !== null || strtolower(trim((string)$pay['status'])) === 'completed'): ?>
                <a href="receipt.php?id=<?php echo $payId; ?>" class="btn-receipt">
                    <i class="fas fa-receipt mr-2"></i>View Payment Receipt
                </a>
            <?php elseif ($paymentExpired || strtolower(trim((string)$pay['status'])) === 'failed'): ?>
                <div class="alert alert-danger small mt-3 mb-0">This manual bank in payment is failed because no receipt was submitted within 3 days.</div>
            <?php else: ?>
                <div class="alert alert-info small mt-3 mb-0">This payment already appears in your payment history. The receipt will be available after you upload payment proof.</div>
            <?php endif; ?>

            <br>
            <a href="payment_history.php" class="btn-back mr-2">
                <i class="fas fa-history mr-1"></i>Payment History
            </a>
            <a href="products.php" class="btn-back">
                <i class="fas fa-arrow-left mr-1"></i>Back to Products
            </a>
        </div>
    </div>
</div>

<script>
function copyAccountNo() {
    var el = document.getElementById('accountNo');
    if (!el) return;
    var text = el.textContent.trim();
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
        alert('Account number copied: ' + text);
    } else {
        var temp = document.createElement('input');
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        alert('Account number copied: ' + text);
    }
}

(function () {
    var timer = document.getElementById('paymentTimer');
    var timerText = document.getElementById('timerText');
    var form = document.getElementById('receiptUploadForm');
    if (!timer || !timerText) return;
    var secondsLeft = parseInt(timer.getAttribute('data-seconds-left') || '0', 10);
    function renderTimer() {
        if (secondsLeft <= 0) {
            timer.innerHTML = '<i class="fas fa-clock mr-1"></i> Manual bank in payment expired';
            if (form) {
                var button = form.querySelector('button[name="upload_receipt"]');
                if (button) button.disabled = true;
            }
            return;
        }
        var days = Math.floor(secondsLeft / 86400);
        var hours = Math.floor((secondsLeft % 86400) / 3600);
        var minutes = Math.floor((secondsLeft % 3600) / 60);
        var seconds = secondsLeft % 60;
        timerText.textContent = days + 'd ' + String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        secondsLeft -= 1;
        setTimeout(renderTimer, 1000);
    }
    renderTimer();
})();
</script>
<?php page_footer(); ?>
