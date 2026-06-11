<?php
include_once 'auth.php';
include_once 'payment_gateway_config.php';

function toyyibpay_status_label($status) {
    $status = (string)$status;
    if ($status === '1') return 'completed';
    if ($status === '2') return 'pending';
    if ($status === '3') return 'failed';
    return 'pending';
}

function toyyibpay_deduct_stock_once($conn, $payment) {
    if (!$payment || strtolower((string)$payment['status']) === 'completed' || (int)($payment['payment_completed'] ?? 0) === 1) return;

    if (!empty($payment['cart_items'])) {
        $items = json_decode($payment['cart_items'], true);
        if (is_array($items)) {
            foreach ($items as $item) {
                $pid = (int)($item['product_id'] ?? 0);
                $qty = max(1, (int)($item['quantity'] ?? 1));
                if ($pid > 0) {
                    decrease_product_stock($conn, $pid, $qty);
                }
            }
            return;
        }
    }

    $pid = (int)$payment['product_id'];
    $qty = max(1, (int)$payment['quantity']);
    if ($pid > 0) {
        decrease_product_stock($conn, $pid, $qty);
    }
}

$status       = $_POST['status'] ?? $_POST['status_id'] ?? $_GET['status_id'] ?? '';
$refno        = $_POST['refno'] ?? $_POST['transaction_id'] ?? $_GET['transaction_id'] ?? '';
$orderId      = $_POST['order_id'] ?? $_POST['billExternalReferenceNo'] ?? $_GET['order_id'] ?? '';
$billcode     = $_POST['billcode'] ?? $_POST['billCode'] ?? $_GET['billcode'] ?? '';
$receivedHash = $_POST['hash'] ?? '';

if ($orderId === '' && $billcode === '') {
    http_response_code(400);
    echo 'Missing order reference';
    exit();
}

if (TOYYIBPAY_USER_SECRET_KEY && $receivedHash && $orderId !== '') {
    $expected = md5(TOYYIBPAY_USER_SECRET_KEY . $status . $orderId . $refno . 'ok');
    if (!hash_equals($expected, $receivedHash)) {
        http_response_code(403);
        echo 'Invalid hash';
        exit();
    }
}

$whereSql = $orderId !== ''
    ? "transaction_id='" . mysqli_real_escape_string($conn, $orderId) . "'"
    : "gateway_bill_code='" . mysqli_real_escape_string($conn, $billcode) . "'";

$payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM product_payments WHERE $whereSql LIMIT 1"));
if (!$payment) {
    http_response_code(404);
    echo 'Payment not found';
    exit();
}

$newStatus = toyyibpay_status_label($status);
if ($newStatus === 'completed') {
    toyyibpay_deduct_stock_once($conn, $payment);
}

$completedFlag = ($newStatus === 'completed') ? 1 : 0;
$stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=?, payment_completed=?, paid_amount=CASE WHEN ?=1 AND paid_amount IS NULL THEN amount ELSE paid_amount END, bank_reference=COALESCE(NULLIF(?,''), bank_reference), gateway_bill_code=COALESCE(NULLIF(?,''), gateway_bill_code), gateway_provider='ToyyibPay' WHERE id=?");
mysqli_stmt_bind_param($stmt, 'siissi', $newStatus, $completedFlag, $completedFlag, $refno, $billcode, $payment['id']);
mysqli_stmt_execute($stmt);

echo 'OK';
exit();
?>
