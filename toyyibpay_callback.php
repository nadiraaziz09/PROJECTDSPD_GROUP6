<?php
include_once 'auth.php';
include_once 'payment_gateway_config.php';

$status = $_POST['status'] ?? $_POST['status_id'] ?? '';
$refno = $_POST['refno'] ?? '';
$orderId = $_POST['order_id'] ?? '';
$billcode = $_POST['billcode'] ?? '';
$receivedHash = $_POST['hash'] ?? '';

if ($orderId === '') {
    http_response_code(400);
    echo 'Missing order_id';
    exit();
}

if (TOYYIBPAY_USER_SECRET_KEY && $receivedHash) {
    $expected = md5(TOYYIBPAY_USER_SECRET_KEY . $status . $orderId . $refno . 'ok');
    if (!hash_equals($expected, $receivedHash)) {
        http_response_code(403);
        echo 'Invalid hash';
        exit();
    }
}

$newStatus = 'pending';
if ((string)$status === '1') $newStatus = 'completed';
if ((string)$status === '2') $newStatus = 'pending';
if ((string)$status === '3') $newStatus = 'failed';

$stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=?, bank_reference=COALESCE(NULLIF(?,''), bank_reference), gateway_bill_code=COALESCE(NULLIF(?,''), gateway_bill_code) WHERE transaction_id=?");
mysqli_stmt_bind_param($stmt, 'ssss', $newStatus, $refno, $billcode, $orderId);
mysqli_stmt_execute($stmt);

if ($newStatus === 'completed') {
    $payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_id, quantity FROM product_payments WHERE transaction_id='" . mysqli_real_escape_string($conn,$orderId) . "' LIMIT 1"));
    if ($payment) {
        $pid = (int)$payment['product_id'];
        $qty = (int)$payment['quantity'];
        mysqli_query($conn, "UPDATE products SET stock=GREATEST(stock-$qty,0) WHERE id=$pid");
    }
}

echo 'OK';
exit();
?>
