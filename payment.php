<?php
include 'layout.php';
include_once 'payment_gateway_config.php';
require_role(1);

$uid  = current_user_id();
$user = current_user();

function product_payment_photo_src($photo, $name = '') {
    $photo = trim((string)$photo);
    $defaultImages = [
        'Premium Dog Food Pack' => 'img/premium-dog-food-pack.jpg',
        'Cat Food & Treats Set' => 'img/cat-food.jpg',
        'Pet Grooming Kit'      => 'img/pet-grooming-kit.jpg',
        'Comfort Pet Bed'       => 'img/comfort-pet-bed.jpg',
        'Toy Bundle'            => 'img/toy-bundle.jpg',
        'Feeding Bowl Set'      => 'img/feeding-bowl-set.jpg'
    ];
    $oldSeedImages = ['img/blog-3.jpg','img/about-3.jpg','img/blog-1.jpg','img/about-1.jpg','img/blog-2.jpg','img/feature.jpg'];
    if (isset($defaultImages[$name]) && ($photo === '' || in_array($photo, $oldSeedImages, true))) return $defaultImages[$name];
    if ($photo !== '' && preg_match('/^https?:\/\//i', $photo)) return $photo;
    if ($photo !== '' && file_exists(__DIR__ . '/' . ltrim($photo, '/'))) return $photo;
    $basename = basename($photo);
    if ($basename !== '' && file_exists(__DIR__ . '/img/' . $basename)) return 'img/' . $basename;
    return $defaultImages[$name] ?? 'img/feature.jpg';
}

function load_cart_checkout_items($conn) {
    if (empty($_SESSION['pet_needs_cart']) || !is_array($_SESSION['pet_needs_cart'])) return [];
    $cartIds = array_keys($_SESSION['pet_needs_cart']);
    if (empty($cartIds)) return [];
    $safeIds = array_map('intval', $cartIds);
    $result  = mysqli_query($conn, "SELECT * FROM products WHERE id IN (" . implode(',', $safeIds) . ") ORDER BY name");
    $items   = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $qty = min(max(1, (int)($_SESSION['pet_needs_cart'][(int)$row['id']] ?? 1)), max(1, (int)$row['stock']));
        $row['checkout_qty']      = $qty;
        $row['checkout_subtotal'] = (float)$row['price'] * $qty;
        $items[] = $row;
    }
    return $items;
}

$isCartCheckout   = isset($_GET['cart']) || isset($_POST['cart_checkout']);
$productId        = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$checkoutItems    = [];
$product          = null;
$selectedQuantity = 1;

if ($isCartCheckout) {
    $checkoutItems = load_cart_checkout_items($conn);
    if (empty($checkoutItems)) {
        flash('error', 'Your cart is empty. Please add pet needs items first.');
        header('Location: products.php'); exit();
    }
    $product   = $checkoutItems[0];
    $productId = (int)$product['id'];
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    if (!$product) {
        flash('error', 'Product not found.');
        header('Location: products.php'); exit();
    }
    $selectedQuantity             = min(max(1, (int)($_GET['quantity'] ?? 1)), max(1, (int)$product['stock']));
    $product['checkout_qty']      = $selectedQuantity;
    $product['checkout_subtotal'] = (float)$product['price'] * $selectedQuantity;
    $checkoutItems[]              = $product;
}

$checkoutTotal = 0;
$totalQty      = 0;
foreach ($checkoutItems as $item) {
    $checkoutTotal += (float)$item['checkout_subtotal'];
    $totalQty      += (int)$item['checkout_qty'];
}

if (isset($_POST['pay'])) {
    $payer     = trim($_POST['payer_name'] ?? '');
    $payMethod = $_POST['payment_method'] ?? 'qr';

    if ($payer === '') {
        flash('error', 'Please enter the payer name.');
        header('Location: payment.php' . ($isCartCheckout ? '?cart=1' : '?product_id=' . $productId)); exit();
    }

    if (!in_array($payMethod, ['qr', 'manual_bank', 'toyyibpay'], true)) {
        flash('error', 'Invalid payment method.');
        header('Location: payment.php' . ($isCartCheckout ? '?cart=1' : '?product_id=' . $productId)); exit();
    }

    if (!$isCartCheckout) {
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        if ($qty > (int)$product['stock']) {
            flash('error', 'Selected quantity exceeds available stock.');
            header('Location: payment.php?product_id=' . $productId); exit();
        }
        $product['checkout_qty']      = $qty;
        $product['checkout_subtotal'] = (float)$product['price'] * $qty;
        $checkoutItems                = [$product];
        $checkoutTotal                = (float)$product['checkout_subtotal'];
        $totalQty                     = $qty;
    } else {
        foreach ($checkoutItems as $item) {
            if ((int)$item['checkout_qty'] > (int)$item['stock']) {
                flash('error', h($item['name']) . ' quantity exceeds available stock.');
                header('Location: products.php'); exit();
            }
        }
    }

    $orderRef      = 'PFH-' . date('YmdHis') . '-' . rand(1000, 9999);
    $cartItemsJson = null;

    if ($isCartCheckout) {
        $cartForDb = [];
        foreach ($checkoutItems as $item) {
            $cartForDb[] = [
                'product_id' => (int)$item['id'],
                'name'       => $item['name'],
                'category'   => $item['category'],
                'quantity'   => (int)$item['checkout_qty'],
                'price'      => (float)$item['price'],
                'subtotal'   => (float)$item['checkout_subtotal'],
            ];
        }
        $cartItemsJson = json_encode($cartForDb);
    }

    if ($payMethod === 'manual_bank') {
        $method   = 'Manual Bank In';
        $bankName = BANK_ACCOUNT_BANK;
        $bankRef  = $orderRef;
        $provider = 'Manual Bank Transfer';
        $status   = 'pending verification';

        $stmt = mysqli_prepare($conn, "INSERT INTO product_payments (user_id,product_id,quantity,amount,payment_method,bank_name,bank_reference,payer_name,status,transaction_id,gateway_provider,cart_items) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iiidssssssss', $uid, $productId, $totalQty, $checkoutTotal, $method, $bankName, $bankRef, $payer, $status, $orderRef, $provider, $cartItemsJson);
        mysqli_stmt_execute($stmt);
        $payId = mysqli_insert_id($conn);

        if ($isCartCheckout) $_SESSION['pet_needs_cart'] = [];

        header('Location: manual_bank_payment.php?id=' . $payId); exit();
    }

    if ($payMethod === 'toyyibpay') {
        $method   = 'ToyyibPay FPX Online Banking';
        $bankName = 'ToyyibPay';
        $bankRef  = $orderRef;
        $provider = 'ToyyibPay';
        $status   = 'pending';

        $stmt = mysqli_prepare($conn, "INSERT INTO product_payments (user_id,product_id,quantity,amount,payment_method,bank_name,bank_reference,payer_name,status,transaction_id,gateway_provider,cart_items) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iiidssssssss', $uid, $productId, $totalQty, $checkoutTotal, $method, $bankName, $bankRef, $payer, $status, $orderRef, $provider, $cartItemsJson);
        mysqli_stmt_execute($stmt);
        $payId = mysqli_insert_id($conn);

        if ($isCartCheckout) $_SESSION['pet_needs_cart'] = [];

        header('Location: toyyibpay.php?id=' . $payId); exit();
    }

    $method   = 'QR Code Payment';
    $bankName = 'DuitNow QR';
    $bankRef  = $orderRef;
    $provider = 'QR Code';
    $status   = 'pending verification';

    $stmt = mysqli_prepare($conn, "INSERT INTO product_payments (user_id,product_id,quantity,amount,payment_method,bank_name,bank_reference,payer_name,status,transaction_id,gateway_provider,cart_items) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'iiidssssssss', $uid, $productId, $totalQty, $checkoutTotal, $method, $bankName, $bankRef, $payer, $status, $orderRef, $provider, $cartItemsJson);
    mysqli_stmt_execute($stmt);
    $payId = mysqli_insert_id($conn);

    if ($isCartCheckout) $_SESSION['pet_needs_cart'] = [];

    header('Location: qr_payment.php?id=' . $payId); exit();
}

page_header('Checkout - PawFect Home', 'payments');
page_title('Pet Needs Checkout', 'Choose QR payment, manual bank in, or ToyyibPay online banking to complete your order.');
?>
<style>
.method-selector { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:20px; }
.method-card {
    border:2px solid #e9ecef; border-radius:14px; padding:20px 16px;
    cursor:pointer; transition:all .18s; text-align:center; background:#fff; user-select:none;
}
.method-card:hover { border-color:var(--paw-primary); box-shadow:0 4px 16px rgba(175,39,8,.10); }
.method-card.selected { border-color:var(--paw-primary); background:#fff7f3; box-shadow:0 6px 22px rgba(175,39,8,.13); }
.method-card input[type=radio] { display:none; }
.method-card i { font-size:2rem; color:var(--paw-primary); margin-bottom:8px; display:block; }
.method-card strong { display:block; font-size:.97rem; color:#1f2428; }
.method-card span { font-size:.8rem; color:#888; }
.method-panel { display:none; }
.method-panel.active { display:block; }
.payment-info-box { background:#fff7f3; border-left:4px solid var(--paw-primary); border-radius:12px; padding:16px 18px; color:#4b3b36; }
.toyyibpay-box { background:#f8f9f6; border-left:4px solid #89a07e; border-radius:12px; padding:16px 18px; color:#314033; }
@media(max-width:575px){ .method-selector{grid-template-columns:1fr;} }
</style>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card-clean p-4">
                <h4 class="mb-3"><i class="fas fa-shopping-bag text-primary mr-2"></i><?php echo $isCartCheckout ? 'Cart Checkout' : 'Product Checkout'; ?></h4>
                <?php foreach ($checkoutItems as $item): ?>
                    <div class="checkout-summary-item">
                        <img src="<?php echo h(product_payment_photo_src($item['photo'], $item['name'])); ?>" alt="<?php echo h($item['name']); ?>">
                        <div>
                            <strong><?php echo h($item['name']); ?></strong><br>
                            <span class="text-muted small"><?php echo h($item['category']); ?> · Qty: <?php echo (int)$item['checkout_qty']; ?></span><br>
                            <span class="text-primary font-weight-bold">RM <?php echo number_format($item['checkout_subtotal'], 2); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="checkout-total-box mt-3">
                    <span>Total to Pay</span>
                    <strong>RM <?php echo number_format($checkoutTotal, 2); ?></strong>
                </div>
                <div class="alert alert-light border mt-3 small mb-0">
                    <strong><i class="fas fa-shield-alt mr-1"></i> Payment Options</strong><br>
                    QR payment is manual receipt upload. Manual Bank In shows PawFect Home bank account number and allows 3 days to upload the receipt. Online Banking opens ToyyibPay FPX.
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card-clean p-4">
                <h4 class="mb-1"><i class="fas fa-credit-card text-primary mr-2"></i>Payment Method</h4>
                <p class="text-muted small mb-4">Select how you'd like to pay.</p>

                <form method="post" id="paymentForm">
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                    <?php if ($isCartCheckout): ?><input type="hidden" name="cart_checkout" value="1"><?php endif; ?>
                    <input type="hidden" name="payment_method" id="paymentMethodInput" value="qr">

                    <div class="method-selector">
                        <label class="method-card selected" id="cardQr" onclick="selectMethod('qr')">
                            <input type="radio" name="_method_ui" value="qr" checked>
                            <i class="fas fa-qrcode"></i>
                            <strong>QR Code</strong>
                            <span>DuitNow / e-Wallet</span>
                        </label>
                        <label class="method-card" id="cardManualBank" onclick="selectMethod('manual_bank')">
                            <input type="radio" name="_method_ui" value="manual_bank">
                            <i class="fas fa-money-check-alt"></i>
                            <strong>Manual Bank In</strong>
                            <span>Account Number</span>
                        </label>
                        <label class="method-card" id="cardToyyibpay" onclick="selectMethod('toyyibpay')">
                            <input type="radio" name="_method_ui" value="toyyibpay">
                            <i class="fas fa-university"></i>
                            <strong>Online Banking</strong>
                            <span>ToyyibPay FPX</span>
                        </label>
                    </div>

                    <div class="form-row">
                        <div class="col-md-<?php echo $isCartCheckout ? '12' : '6'; ?> form-group">
                            <label>Payer Name</label>
                            <input name="payer_name" class="form-control" value="<?php echo h($user['Name'] ?? ''); ?>" required>
                        </div>
                        <?php if (!$isCartCheckout): ?>
                        <div class="col-md-6 form-group">
                            <label>Quantity</label>
                            <input type="number" min="1" max="<?php echo (int)$product['stock']; ?>" name="quantity" value="<?php echo (int)$selectedQuantity; ?>" class="form-control" required>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="method-panel active" id="panelQr">
                        <div class="payment-info-box mb-3">
                            <i class="fas fa-qrcode mr-2"></i>
                            <strong>QR Code Payment</strong><br>
                            You will be redirected to the QR page. Scan to pay exactly <strong>RM <?php echo number_format($checkoutTotal, 2); ?></strong>, then upload your receipt.
                        </div>
                    </div>

                    <div class="method-panel" id="panelManualBank">
                        <div class="payment-info-box mb-3">
                            <i class="fas fa-money-check-alt mr-2"></i>
                            <strong>Manual Bank In</strong><br>
                            Bank transfer to <strong><?php echo h(BANK_ACCOUNT_NAME); ?></strong><br>
                            <span class="small">Bank: <strong><?php echo h(BANK_ACCOUNT_BANK); ?></strong> · Account No: <strong><?php echo h(BANK_ACCOUNT_NUMBER); ?></strong></span><br>
                            Please pay exactly <strong>RM <?php echo number_format($checkoutTotal, 2); ?></strong> and upload the receipt within <strong>3 days</strong>.
                        </div>
                    </div>

                    <div class="method-panel" id="panelToyyibpay">
                        <div class="toyyibpay-box mb-3">
                            <i class="fas fa-university mr-2"></i>
                            <strong>ToyyibPay Online Banking</strong><br>
                            You will be redirected to ToyyibPay to pay by FPX online banking. No manual bank login page is used in this system.
                            <?php if (!toyyibpay_is_configured()): ?>
                                <div class="small mt-2 text-danger"><strong>Setup needed:</strong> add your ToyyibPay User Secret Key and Category Code in <code>payment_gateway_config.php</code>.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button name="pay" class="btn btn-primary btn-lg" id="proceedBtn">
                            <i class="fas fa-lock mr-2"></i><span id="proceedBtnText">Proceed to QR Payment</span>
                        </button>
                        <a href="products.php" class="btn btn-outline-secondary btn-lg ml-2">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function selectMethod(method) {
    document.getElementById('paymentMethodInput').value = method;
    document.getElementById('cardQr').classList.toggle('selected', method === 'qr');
    document.getElementById('cardManualBank').classList.toggle('selected', method === 'manual_bank');
    document.getElementById('cardToyyibpay').classList.toggle('selected', method === 'toyyibpay');
    document.getElementById('panelQr').classList.toggle('active', method === 'qr');
    document.getElementById('panelManualBank').classList.toggle('active', method === 'manual_bank');
    document.getElementById('panelToyyibpay').classList.toggle('active', method === 'toyyibpay');
    var btnText = document.getElementById('proceedBtnText');
    if (btnText) {
        if (method === 'toyyibpay') btnText.textContent = 'Proceed to ToyyibPay';
        else if (method === 'manual_bank') btnText.textContent = 'Proceed to Manual Bank In';
        else btnText.textContent = 'Proceed to QR Payment';
    }
}
</script>
<?php page_footer(); ?>
