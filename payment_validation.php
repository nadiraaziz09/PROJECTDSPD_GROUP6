<?php
/**
 * Validate that a product payment/order is completed before showing paid-only pages.
 * Existing project tables use `product_payments`; the optional `orders` check is kept
 * for compatibility with databases that also have an `orders` table.
 */
function validate_payment($payment_id, $conn, $redirect = 'products.php') {
    $payment_id = (int)$payment_id;
    if ($payment_id <= 0) {
        header("Location: $redirect");
        exit;
    }

    $sql = "SELECT payment_completed FROM product_payments WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $stmt->bind_result($payment_completed);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || (int)$payment_completed !== 1) {
        header("Location: $redirect");
        exit;
    }
}

function validate_order_payment($order_id, $conn, $redirect = 'products.php') {
    $order_id = (int)$order_id;
    if ($order_id <= 0) {
        header("Location: $redirect");
        exit;
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'orders'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        header("Location: $redirect");
        exit;
    }

    $sql = "SELECT payment_completed FROM orders WHERE order_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->bind_result($payment_completed);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || (int)$payment_completed !== 1) {
        header("Location: $redirect");
        exit;
    }
}
?>
