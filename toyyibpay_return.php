<?php
include 'layout.php';
require_login();

$status = $_GET['status_id'] ?? '';
$orderId = $_GET['order_id'] ?? '';
$billcode = $_GET['billcode'] ?? '';

if ($orderId) {
    $newStatus = 'pending';
    if ((string)$status === '1') $newStatus = 'completed';
    if ((string)$status === '2') $newStatus = 'pending';
    if ((string)$status === '3') $newStatus = 'failed';
    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=?, gateway_bill_code=COALESCE(NULLIF(?,''), gateway_bill_code) WHERE transaction_id=?");
    mysqli_stmt_bind_param($stmt, 'sss', $newStatus, $billcode, $orderId);
    mysqli_stmt_execute($stmt);

    $payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM product_payments WHERE transaction_id='" . mysqli_real_escape_string($conn,$orderId) . "' LIMIT 1"));
    if ($payment) {
        flash($newStatus === 'completed' ? 'success' : 'error', $newStatus === 'completed' ? 'Payment completed successfully.' : 'Payment status: ' . $newStatus);
        header('Location: receipt.php?id=' . (int)$payment['id']);
        exit();
    }
}

flash('error', 'Payment return could not be matched to an order.');
header('Location: payment_history.php');
exit();
?>
