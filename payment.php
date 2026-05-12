<?php
include 'layout.php';
include_once 'payment_gateway_config.php';
require_role(1);

$uid = current_user_id();
$user = current_user();
$productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$product) {
    flash('error', 'Product not found.');
    header('Location: products.php');
    exit();
}

$banks = [
    'Maybank2u','CIMB Clicks','Public Bank PBe','RHB Online Banking','Hong Leong Connect',
    'Bank Islam','BSN Online','Affin Bank Online','Bank Rakyat','Alliance Bank','Bank Muamalat','OCBC Bank'
];

function create_toyyibpay_bill($orderRef, $amount, $product, $user, $qty, &$error) {
    $apiBase = toyyibpay_api_base();
    $data = [
        'userSecretKey' => TOYYIBPAY_USER_SECRET_KEY,
        'categoryCode' => TOYYIBPAY_CATEGORY_CODE,
        'billName' => substr('PawFect Product ' . $product['id'], 0, 30),
        'billDescription' => substr($product['name'] . ' x ' . $qty, 0, 100),
        'billPriceSetting' => 1,
        'billPayorInfo' => 1,
        'billAmount' => (int)round($amount * 100),
        'billReturnUrl' => pawfect_base_url() . '/toyyibpay_return.php',
        'billCallbackUrl' => pawfect_base_url() . '/toyyibpay_callback.php',
        'billExternalReferenceNo' => $orderRef,
        'billTo' => $user['Name'] ?? 'Customer',
        'billEmail' => $user['Email'] ?? '',
        'billPhone' => $user['Phone'] ?? '',
        'billSplitPayment' => 0,
        'billSplitPaymentArgs' => '',
        'billPaymentChannel' => '0',
        'billContentEmail' => 'Thank you for buying pet needs from PawFect Home.',
        'billChargeToCustomer' => 1,
        'billExpiryDays' => 3
    ];

    if (!function_exists('curl_init')) {
        $error = 'PHP cURL extension is not enabled in XAMPP.';
        return false;
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_URL, $apiBase . '/index.php/api/createBill');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    $result = curl_exec($curl);
    if ($result === false) {
        $error = curl_error($curl);
        curl_close($curl);
        return false;
    }
    curl_close($curl);

    $obj = json_decode($result, true);
    if (is_array($obj) && isset($obj[0]['BillCode'])) {
        return $obj[0]['BillCode'];
    }
    $error = 'ToyyibPay did not return a BillCode. Response: ' . $result;
    return false;
}

if (isset($_POST['pay'])) {
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $methodChoice = trim($_POST['method_choice'] ?? 'manual');
    $bank = trim($_POST['bank_name'] ?? '');
    $payer = trim($_POST['payer_name'] ?? '');
    $manualRef = trim($_POST['manual_reference'] ?? '');

    if ($qty > (int)$product['stock']) {
        flash('error', 'Selected quantity is more than available stock.');
        header('Location: payment.php?product_id=' . $productId);
        exit();
    }
    if ($payer === '') {
        flash('error', 'Please enter the payer name.');
        header('Location: payment.php?product_id=' . $productId);
        exit();
    }

    $amount = (float)$product['price'] * $qty;
    $orderRef = 'PFH-' . date('YmdHis') . '-' . rand(1000,9999);

    if ($methodChoice === 'toyyibpay') {
        if (!toyyibpay_is_configured()) {
            flash('error', 'Real FPX gateway is not configured yet. Please fill payment_gateway_config.php or use manual online banking transfer.');
            header('Location: payment.php?product_id=' . $productId);
            exit();
        }

        $method = 'ToyyibPay FPX Online Banking';
        $status = 'pending';
        $stmt = mysqli_prepare($conn, "INSERT INTO product_payments (user_id,product_id,quantity,amount,payment_method,bank_name,bank_reference,payer_name,status,transaction_id,gateway_provider) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $provider = 'ToyyibPay';
        $bankName = 'FPX Malaysia';
        mysqli_stmt_bind_param($stmt, 'iiidsssssss', $uid, $productId, $qty, $amount, $method, $bankName, $orderRef, $payer, $status, $orderRef, $provider);
        mysqli_stmt_execute($stmt);
        $payId = mysqli_insert_id($conn);

        $err = '';
        $billCode = create_toyyibpay_bill($orderRef, $amount, $product, $user, $qty, $err);
        if (!$billCode) {
            mysqli_query($conn, "UPDATE product_payments SET status='failed' WHERE id=$payId");
            flash('error', 'Could not connect to ToyyibPay: ' . $err);
            header('Location: payment.php?product_id=' . $productId);
            exit();
        }
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET gateway_bill_code=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'si', $billCode, $payId);
        mysqli_stmt_execute($stmt);
        header('Location: ' . toyyibpay_api_base() . '/' . urlencode($billCode));
        exit();
    }

    // Manual online banking transfer: do not collect bank username, password or OTP.
    if (!in_array($bank, $banks, true) || $manualRef === '') {
        flash('error', 'Please select a bank and enter your bank transfer reference number.');
        header('Location: payment.php?product_id=' . $productId);
        exit();
    }

    $method = 'Manual Online Banking Transfer';
    $status = 'pending verification';
    $stmt = mysqli_prepare($conn, "INSERT INTO product_payments (user_id,product_id,quantity,amount,payment_method,bank_name,bank_reference,payer_name,status,transaction_id,gateway_provider) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $provider = 'Manual Bank Transfer';
    mysqli_stmt_bind_param($stmt, 'iiidsssssss', $uid, $productId, $qty, $amount, $method, $bank, $manualRef, $payer, $status, $orderRef, $provider);
    mysqli_stmt_execute($stmt);
    $payId = mysqli_insert_id($conn);
    flash('success', 'Payment record submitted. Staff will verify the online banking reference.');
    header('Location: receipt.php?id=' . $payId);
    exit();
}

page_header('Online Banking Payment - PawFect Home', 'payments');
page_title('Malaysia Online Banking Payment', 'Pay for pet needs products through real FPX gateway setup or manual online banking transfer.');
?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card-clean product-card">
                <img src="<?php echo h($product['photo']); ?>" alt="<?php echo h($product['name']); ?>">
                <div class="p-4">
                    <span class="badge badge-light mb-2"><?php echo h($product['category']); ?></span>
                    <h4><?php echo h($product['name']); ?></h4>
                    <h3 class="text-primary">RM <?php echo number_format($product['price'],2); ?></h3>
                    <p class="text-muted mb-1"><?php echo h($product['description']); ?></p>
                    <p class="small mb-0">Available stock: <?php echo (int)$product['stock']; ?></p>
                </div>
            </div>
            <div class="alert alert-light border mt-3 small">
                <strong>Bank Transfer Account</strong><br>
                <?php echo h(BANK_ACCOUNT_NAME); ?><br>
                <?php echo h(BANK_ACCOUNT_BANK); ?> · <?php echo h(BANK_ACCOUNT_NUMBER); ?>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card-clean p-4">
                <h4><i class="fas fa-university text-primary mr-2"></i>Pet Needs Checkout</h4>
                <p class="text-muted">Choose real FPX gateway or manual online banking transfer.</p>
                <form method="post">
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Payer Name</label>
                            <input name="payer_name" class="form-control" value="<?php echo h($user['Name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Quantity</label>
                            <input type="number" min="1" max="<?php echo (int)$product['stock']; ?>" name="quantity" value="1" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="method_choice" id="method_choice" class="custom-select" required>
                            <option value="manual">Manual Online Banking Transfer</option>
                            <option value="toyyibpay" <?php echo toyyibpay_is_configured() ? '' : 'disabled'; ?>>ToyyibPay FPX Real Gateway <?php echo toyyibpay_is_configured() ? '' : '(configure first)'; ?></option>
                        </select>
                    </div>

                    <?php if (!toyyibpay_is_configured()): ?>
                    <div class="alert alert-warning small">
                        Real FPX is prepared in the code, but not active because merchant credentials are empty. Fill <strong>payment_gateway_config.php</strong> with ToyyibPay secret key and category code to redirect customers to a real FPX payment page.
                    </div>
                    <?php endif; ?>

                    <div id="manualBox">
                        <div class="form-group">
                            <label>Bank Used for Transfer</label>
                            <select name="bank_name" class="custom-select">
                                <option value="">-- Choose Bank --</option>
                                <?php foreach($banks as $bank): ?>
                                    <option value="<?php echo h($bank); ?>"><?php echo h($bank); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Bank Transfer Reference Number</label>
                            <input name="manual_reference" class="form-control" placeholder="Example: M2U123456789 / IBG reference">
                            <small class="form-text text-muted">Do not enter your bank username, password or OTP. Only enter the payment reference after you make the bank transfer.</small>
                        </div>
                    </div>

                    <div class="alert alert-info small">
                        For a live payment website, activate ToyyibPay FPX in the config file. Manual transfer mode is included so the project still works on localhost without a payment gateway account.
                    </div>
                    <button name="pay" class="btn btn-primary btn-lg"><i class="fas fa-lock mr-2"></i>Continue Payment</button>
                    <a href="products.php" class="btn btn-outline-secondary btn-lg">Back</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('method_choice').addEventListener('change', function(){
    document.getElementById('manualBox').style.display = this.value === 'manual' ? 'block' : 'none';
});
</script>
<?php page_footer(); ?>
