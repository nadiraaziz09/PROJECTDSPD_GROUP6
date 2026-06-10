<?php
include 'layout.php';
require_login();

function toyyibpay_return_status_label($status) {
    $status = (string)$status;
    if ($status === '1') return 'completed';
    if ($status === '2') return 'pending';
    if ($status === '3') return 'failed';
    return 'pending';
}

function toyyibpay_return_deduct_stock_once($conn, $payment) {
    if (!$payment || strtolower((string)$payment['status']) === 'completed' || (int)($payment['payment_completed'] ?? 0) === 1) return;

    if (!empty($payment['cart_items'])) {
        $items = json_decode($payment['cart_items'], true);
        if (is_array($items)) {
            foreach ($items as $item) {
                $pid = (int)($item['product_id'] ?? 0);
                $qty = max(1, (int)($item['quantity'] ?? 1));
                if ($pid > 0) decrease_product_stock($conn, $pid, $qty);
            }
            return;
        }
    }

    $pid = (int)$payment['product_id'];
    $qty = max(1, (int)$payment['quantity']);
    if ($pid > 0) decrease_product_stock($conn, $pid, $qty);
}

$status    = $_GET['status_id'] ?? $_GET['status'] ?? '';
$orderId   = $_GET['order_id'] ?? $_GET['billExternalReferenceNo'] ?? '';
$billcode  = $_GET['billcode'] ?? $_GET['billCode'] ?? '';
$paymentId = (int)($_GET['payment_id'] ?? 0);
$userId    = current_user_id();

$where = '';
if ($orderId !== '') {
    $where = "transaction_id='" . mysqli_real_escape_string($conn, $orderId) . "'";
} elseif ($billcode !== '') {
    $where = "gateway_bill_code='" . mysqli_real_escape_string($conn, $billcode) . "'";
} elseif ($paymentId > 0) {
    $where = "id=$paymentId";
}

if ($where !== '') {
    $payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM product_payments WHERE $where AND user_id=$userId LIMIT 1"));
    if ($payment) {
        $newStatus = toyyibpay_return_status_label($status);
        if ($newStatus === 'completed') {
            toyyibpay_return_deduct_stock_once($conn, $payment);
        }
        $completedFlag = ($newStatus === 'completed') ? 1 : 0;
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=?, payment_completed=?, paid_amount=CASE WHEN ?=1 AND paid_amount IS NULL THEN amount ELSE paid_amount END, gateway_bill_code=COALESCE(NULLIF(?,''), gateway_bill_code), gateway_provider='ToyyibPay' WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'siisii', $newStatus, $completedFlag, $completedFlag, $billcode, $payment['id'], $userId);
        mysqli_stmt_execute($stmt);

        flash($newStatus === 'completed' ? 'success' : 'error', $newStatus === 'completed' ? 'Payment completed successfully.' : 'Payment status: ' . $newStatus);
        header('Location: receipt.php?id=' . (int)$payment['id']);
        exit();
    }
}

flash('error', 'Payment return could not be matched to an order. Please check Payment History.');
header('Location: payment_history.php');
exit();
?>
