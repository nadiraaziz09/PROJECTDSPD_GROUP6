<?php
include 'layout.php';
include_once 'payment_gateway_config.php';
require_role(1);

$payId = (int)($_GET['id'] ?? 0);
$currentUserId = current_user_id();

$stmt = mysqli_prepare($conn,
    "SELECT pp.*, p.name AS product_name, p.category AS product_category, p.photo AS product_photo, u.Email, u.Phone, u.Name
     FROM product_payments pp
     JOIN products p ON p.id = pp.product_id
     JOIN account u ON u.ID = pp.user_id
     WHERE pp.id = ? AND pp.user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $payId, $currentUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pay = mysqli_fetch_assoc($result);

if (!$pay) {
    flash('error', 'Payment record not found.');
    header('Location: products.php'); exit();
}

function toyyibpay_items_summary($pay) {
    $items = [];
    if (!empty($pay['cart_items'])) {
        $decoded = json_decode($pay['cart_items'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $items[] = ($item['name'] ?? 'Item') . ' x' . (int)($item['quantity'] ?? 1);
            }
        }
    }
    if (empty($items)) $items[] = $pay['product_name'] . ' x' . (int)$pay['quantity'];
    return implode(', ', $items);
}

function toyyibpay_clean_text($text, $maxLength) {
    $text = preg_replace('/[^A-Za-z0-9 _]/', ' ', (string)$text);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') $text = 'PawFect Payment';
    return substr($text, 0, $maxLength);
}

function redirect_to_existing_toyyibpay_bill($pay) {
    if (!empty($pay['gateway_bill_code'])) {
        header('Location: ' . toyyibpay_api_base() . '/' . rawurlencode($pay['gateway_bill_code']));
        exit();
    }
}

function create_toyyibpay_bill($conn, $pay) {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'PHP cURL extension is not enabled. Enable cURL in XAMPP before using ToyyibPay.'];
    }

    $amountInSen = (int)round(((float)$pay['amount']) * 100);
    if ($amountInSen <= 0) {
        return ['ok' => false, 'message' => 'Invalid payment amount.'];
    }

    $baseUrl = pawfect_base_url();
    $orderRef = $pay['transaction_id'];
    $billName = toyyibpay_clean_text('PawFect Order ' . $pay['id'], 30);
    $billDescription = toyyibpay_clean_text(toyyibpay_items_summary($pay), 100);
    $billTo = trim((string)($pay['payer_name'] ?: $pay['Name'] ?: 'Customer'));
    $billEmail = trim((string)($pay['Email'] ?? ''));
    $billPhone = trim((string)($pay['Phone'] ?? ''));

    $postData = [
        'userSecretKey'          => TOYYIBPAY_USER_SECRET_KEY,
        'categoryCode'           => TOYYIBPAY_CATEGORY_CODE,
        'billName'               => $billName,
        'billDescription'        => $billDescription,
        'billPriceSetting'       => 1,
        'billPayorInfo'          => 1,
        'billAmount'             => $amountInSen,
        'billReturnUrl'          => $baseUrl . '/toyyibpay_return.php?payment_id=' . (int)$pay['id'],
        'billCallbackUrl'        => $baseUrl . '/toyyibpay_callback.php',
        'billExternalReferenceNo'=> $orderRef,
        'billTo'                 => $billTo,
        'billEmail'              => $billEmail,
        'billPhone'              => $billPhone,
        'billSplitPayment'       => 0,
        'billSplitPaymentArgs'   => '',
        'billPaymentChannel'     => 0,
        'billContentEmail'       => 'Thank you for your PawFect Home order.',
        'billChargeToCustomer'   => 1,
        'billExpiryDate'         => date('Y-m-d H:i:s', strtotime('+1 day')),
        'billExpiryDays'         => 1,
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => toyyibpay_api_base() . '/index.php/api/createBill',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $curlError) {
        return ['ok' => false, 'message' => 'ToyyibPay connection failed: ' . $curlError];
    }

    $decoded = json_decode($response, true);
    $billCode = $decoded[0]['BillCode'] ?? $decoded['BillCode'] ?? '';

    if ($billCode === '') {
        return ['ok' => false, 'message' => 'ToyyibPay did not return a BillCode. Response: ' . substr(strip_tags($response), 0, 300)];
    }

    $status = 'pending';
    $provider = 'ToyyibPay';
    $method = 'ToyyibPay FPX Online Banking';
    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET gateway_bill_code=?, gateway_provider=?, payment_method=?, status=?, payment_completed=0 WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssssi', $billCode, $provider, $method, $status, $pay['id']);
    mysqli_stmt_execute($stmt);

    return ['ok' => true, 'bill_code' => $billCode, 'http_code' => $httpCode];
}

$gatewayError = '';

if (toyyibpay_is_configured()) {
    redirect_to_existing_toyyibpay_bill($pay);
    $created = create_toyyibpay_bill($conn, $pay);
    if (!empty($created['ok'])) {
        header('Location: ' . toyyibpay_api_base() . '/' . rawurlencode($created['bill_code']));
        exit();
    }
    $gatewayError = $created['message'] ?? 'Unable to create ToyyibPay bill.';
}

page_header('ToyyibPay Payment - PawFect Home', 'payments');
page_title('ToyyibPay Online Banking', 'Redirect customer to ToyyibPay FPX online banking.');
?>
<style>
.toyyibpay-card { max-width:680px; margin:0 auto; border-radius:18px; background:#fff; box-shadow:0 12px 30px rgba(31,36,40,.12); overflow:hidden; }
.toyyibpay-head { background:linear-gradient(135deg, rgba(31,36,40,.95), rgba(175,39,8,.85)); color:#fff; padding:28px 32px; }
.toyyibpay-body { padding:28px 32px; }
.detail-row { display:flex; justify-content:space-between; gap:16px; border-bottom:1px solid #eee; padding:10px 0; }
.setup-box { background:#fff7f3; border-left:4px solid #af2708; border-radius:10px; padding:16px 18px; color:#5a2515; }
.code-path { background:#f7f7f7; padding:2px 6px; border-radius:4px; }
</style>
<div class="container py-5">
    <div class="toyyibpay-card">
        <div class="toyyibpay-head">
            <h4 class="mb-1"><i class="fas fa-university mr-2"></i>ToyyibPay FPX Online Banking</h4>
            <p class="mb-0 small">Payment record #<?php echo (int)$pay['id']; ?> · Ref: <?php echo h($pay['transaction_id']); ?></p>
        </div>
        <div class="toyyibpay-body">
            <?php if ($gatewayError): ?>
                <div class="alert alert-danger"><?php echo h($gatewayError); ?></div>
            <?php endif; ?>

            <?php if (!toyyibpay_is_configured()): ?>
                <div class="setup-box mb-4">
                    <strong>ToyyibPay setup is not completed yet.</strong><br>
                    This page is working now, but it cannot redirect to ToyyibPay until you add your merchant credentials.
                    <ol class="mt-2 mb-0 pl-3">
                        <li>Open <span class="code-path">payment_gateway_config.php</span>.</li>
                        <li>Change <span class="code-path">TOYYIBPAY_ENABLED</span> to <span class="code-path">true</span>.</li>
                        <li>Replace <span class="code-path">TOYYIBPAY_USER_SECRET_KEY</span> and <span class="code-path">TOYYIBPAY_CATEGORY_CODE</span> with your real ToyyibPay values.</li>
                        <li>Set <span class="code-path">TOYYIBPAY_SANDBOX</span> to <span class="code-path">false</span> when you use the live account.</li>
                    </ol>
                </div>
            <?php endif; ?>

            <div class="detail-row"><span>Item</span><strong><?php echo h(toyyibpay_items_summary($pay)); ?></strong></div>
            <div class="detail-row"><span>Payer</span><strong><?php echo h($pay['payer_name'] ?: $pay['Name']); ?></strong></div>
            <div class="detail-row"><span>Amount</span><strong>RM <?php echo number_format((float)$pay['amount'], 2); ?></strong></div>
            <div class="detail-row"><span>Status</span><strong><?php echo h($pay['status']); ?></strong></div>

            <?php if (toyyibpay_is_configured()): ?>
                <form method="post" class="mt-4">
                    <button class="btn btn-primary btn-lg" name="retry" value="1"><i class="fas fa-redo mr-2"></i>Try Create ToyyibPay Bill Again</button>
                </form>
            <?php endif; ?>
            <div class="mt-4">
                <a href="payment_history.php" class="btn btn-outline-secondary">Back to Payment History</a>
                <a href="products.php" class="btn btn-outline-primary ml-2">Back to Products</a>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>
