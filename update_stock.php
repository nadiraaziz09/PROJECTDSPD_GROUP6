<?php
// Stock helper for Pet Needs products.
// The project keeps both `stock` (used by existing pages) and `quantity` (required by the updated database) in sync.
function decrease_stock($product_id, $qty, $conn) {
    $product_id = (int)$product_id;
    $qty = max(1, (int)$qty);
    if ($product_id <= 0) return false;

    $sql = "UPDATE products
            SET stock = GREATEST(stock - ?, 0),
                quantity = GREATEST(quantity - ?, 0)
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $qty, $qty, $product_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function update_stock($product_id, $qty, $conn) {
    $product_id = (int)$product_id;
    $qty = max(0, (int)$qty);
    if ($product_id <= 0) return false;

    $sql = "UPDATE products SET stock = ?, quantity = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $qty, $qty, $product_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
?>
