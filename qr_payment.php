<?php
/*
 * qr_payment.php
 * Displays a DuitNow QR code for the customer to scan, then lets them
 * click the Receipt button after scanning.
 *
 * URL parameters (passed from payment.php redirect):
 *   id     – product_payments.id
 *   amount – formatted amount string, e.g. "12.50"
 *   ref    – order reference, e.g. "PFH-20260519-1234"
 *   payer  – payer name
 */
include 'layout.php';
include_once 'payment_gateway_config.php';
require_role(1);

$payId  = (int)($_GET['id']     ?? 0);
$amount = htmlspecialchars(strip_tags($_GET['amount'] ?? '0.00'));
$ref    = htmlspecialchars(strip_tags($_GET['ref']    ?? ''));
$payer  = htmlspecialchars(strip_tags($_GET['payer']  ?? ''));

// Load the payment record so we can show product details
$stmt = mysqli_prepare($conn,
    "SELECT pp.*, p.name AS product_name, p.photo AS product_photo
     FROM product_payments pp
     JOIN products p ON p.id = pp.product_id
     WHERE pp.id = ? AND pp.user_id = ?
     LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $payId, current_user_id());
mysqli_stmt_execute($stmt);
$pay = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$pay) {
    flash('error', 'Payment record not found.');
    header('Location: products.php');
    exit();
}

// Build the QR code content – a DuitNow-style string.
// In a real deployment replace this with your merchant's actual DuitNow payload.
$qrContent = 'DUITNOW:' . BANK_ACCOUNT_NUMBER . ':RM' . $pay['amount'] . ':' . $pay['transaction_id'];

page_header('QR Code Payment - PawFect Home', 'payments');
page_title('Scan & Pay via QR Code', 'Use any Malaysian banking app or e-wallet to scan the DuitNow QR below.');
?>

<style>
/* ── QR page — PawFect colour palette ───────────────────────
   --paw-primary  : #af2708  (deep red)
   --paw-secondary: #89a07e  (sage green)
   --paw-dark     : #1f2428
   --paw-soft     : #fff7f3
──────────────────────────────────────────────────────────── */
.qr-card {
    max-width: 520px;
    margin: 0 auto;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(31,36,40,.12);
    overflow: hidden;
    background: #fff;
}

/* Header — mirrors .page-hero gradient */
.qr-header {
    background: linear-gradient(135deg, rgba(31,36,40,.90), rgba(175,39,8,.85));
    color: #fff;
    padding: 28px 32px 20px;
}
.qr-header h4 { margin: 0; font-size: 1.35rem; font-weight: 700; }
.qr-header p  { margin: 5px 0 0; opacity: .85; font-size: .9rem; }

.qr-body { background: #fff; padding: 30px 28px; text-align: center; }

/* QR frame — primary red border */
#qrcode {
    display: inline-block;
    padding: 14px;
    background: #fff;
    border: 3px solid #af2708;
    border-radius: 12px;
    margin-top: 6px;
}

/* Amount */
.amount-badge {
    font-size: 2.1rem;
    font-weight: 800;
    color: #af2708;
    margin: 14px 0 4px;
}
.ref-text {
    font-size: .78rem;
    color: #888;
    letter-spacing: .04em;
}

/* Timer pill — soft warm background (--paw-soft) */
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

/* Steps — sage green left-border accent */
.steps {
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

/* Merchant info box — soft warm tint */
.merchant-box {
    background: #fff7f3;
    border-radius: 10px;
    padding: 12px 18px;
    margin-top: 4px;
    font-size: .88rem;
}

.divider { border-top: 1px dashed #e0d6d2; margin: 22px 0; }

/* Receipt button — sage green */
.btn-receipt {
    background: #89a07e;
    border: none;
    color: #fff;
    padding: 14px 38px;
    font-size: 1.05rem;
    font-weight: 700;
    border-radius: 10px;
    margin-top: 22px;
    display: inline-block;
    transition: background .2s, transform .15s;
    text-decoration: none;
}
.btn-receipt:hover {
    background: #6e8563;
    color: #fff;
    text-decoration: none;
    transform: translateY(-2px);
}

/* Back link — outline red */
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
.btn-back:hover {
    background: #af2708;
    color: #fff;
    text-decoration: none;
}
</style>

<div class="container py-5">
    <div class="qr-card">

        <!-- Header -->
        <div class="qr-header">
            <h4><i class="fas fa-qrcode mr-2"></i>DuitNow QR Payment</h4>
            <p>Scan with any Malaysian banking app or e-wallet</p>
        </div>

        <!-- Body -->
        <div class="qr-body">

            <!-- Product summary -->
            <p class="text-muted small mb-1">You are paying for:</p>
            <strong style="color:#1f2428;"><?php echo h($pay['product_name']); ?></strong>
            <span class="badge badge-light ml-1">Qty: <?php echo (int)$pay['quantity']; ?></span>

            <!-- QR code -->
            <div class="mt-3">
                <div id="qrcode"></div>
            </div>

            <!-- Amount -->
            <div class="amount-badge">RM <?php echo h(number_format((float)$pay['amount'], 2)); ?></div>
            <div class="ref-text">Ref: <?php echo h($pay['transaction_id']); ?></div>

            <!-- Timer -->
            <div class="timer-badge">
                <i class="fas fa-clock mr-1"></i>
                Complete payment within <strong>15 minutes</strong>
            </div>

            <!-- Steps -->
            <div class="steps">
                <strong>How to pay:</strong>
                <ol class="mt-2 mb-0 pl-3">
                    <li>Open your banking app or e-wallet (Maybank2u, Touch 'n Go, etc.)</li>
                    <li>Tap <strong>Scan &amp; Pay</strong> or <strong>DuitNow QR</strong></li>
                    <li>Scan the QR code above</li>
                    <li>Confirm the amount is <strong>RM <?php echo h(number_format((float)$pay['amount'], 2)); ?></strong></li>
                    <li>Complete the payment in your app</li>
                </ol>
            </div>

            <div class="divider"></div>

            <!-- Merchant info -->
            <p class="small text-muted mb-1">Payment to:</p>
            <div class="merchant-box">
                <strong style="color:#1f2428;"><?php echo h(BANK_ACCOUNT_NAME); ?></strong><br>
                <span class="text-muted small"><?php echo h(BANK_ACCOUNT_BANK); ?> · <?php echo h(BANK_ACCOUNT_NUMBER); ?></span>
            </div>

            <div class="divider"></div>

            <!-- Receipt button -->
            <p class="text-muted small mb-0">
                After scanning and completing payment, click below to get your receipt.
            </p>
            <a href="receipt.php?id=<?php echo $payId; ?>" class="btn-receipt">
                <i class="fas fa-receipt mr-2"></i>Get My Receipt
            </a>

            <br>
            <a href="products.php" class="btn-back">
                <i class="fas fa-arrow-left mr-1"></i>Back to Products
            </a>

        </div><!-- /qr-body -->
    </div>
</div>

<!-- qrcode.js from CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qrcode'), {
    text: <?php echo json_encode($qrContent); ?>,
    width:  220,
    height: 220,
    colorDark:  '#af2708',   /* --paw-primary */
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H
});
</script>
<?php page_footer(); ?>