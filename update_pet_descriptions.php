<?php
include_once 'auth.php';
require_role([2,3]);

$updates = [
    ['comfort pet bed', 'Soft, washable bed for cats and small dogs, offering a cozy resting spot.'],
    ['pet bed', 'Soft, washable bed for cats and small dogs, offering a cozy resting spot.'],
    ['feeding bowl set', 'Simple feeding bowl set for food and water, ideal for daily use and easy cleaning.'],
    ['bowl set', 'Simple feeding bowl set for food and water, ideal for daily use and easy cleaning.'],
    ['toy bundle', 'Safe toys designed to keep pets active, entertained, and happy throughout the day.']
];

$total = 0;
foreach ($updates as $u) {
    $stmt = mysqli_prepare($conn, "UPDATE products SET description=? WHERE LOWER(name)=?");
    mysqli_stmt_bind_param($stmt, 'ss', $u[1], $u[0]);
    mysqli_stmt_execute($stmt);
    $total += mysqli_stmt_affected_rows($stmt);
}

echo "Pet descriptions updated. Rows changed: " . (int)$total;
?>
