<?php
include 'layout.php';
require_role(1);
$uid = current_user_id();
$stmt = mysqli_prepare($conn, "SELECT pay.*, pr.name product_name, pr.photo FROM product_payments pay JOIN products pr ON pay.product_id=pr.id WHERE pay.user_id=? ORDER BY pay.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
page_header('Payment History - PawFect Home', 'payments');
page_title('Product Payment History', 'Track pet needs purchases and online banking receipts.');
?>
<div class="container py-5">
    <div class="table-responsive card-clean p-0">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Product</th><th>Qty</th><th>Amount</th><th>Bank</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="7" class="text-center text-muted p-4">No product payment records yet. <a href="products.php">Shop pet needs</a>.</td></tr>
            <?php endif; ?>
            <?php while($p = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo h(date('d M Y', strtotime($p['created_at']))); ?></td>
                    <td><img src="<?php echo h($p['photo']); ?>" style="width:55px;height:40px;object-fit:cover;border-radius:8px" class="mr-2"><?php echo h($p['product_name']); ?></td>
                    <td><?php echo (int)$p['quantity']; ?></td>
                    <td>RM <?php echo number_format($p['amount'],2); ?></td>
                    <td><?php echo h($p['bank_name'] ?: $p['payment_method']); ?></td>
                    <td><?php echo status_badge($p['status']); ?></td>
                    <td><a href="receipt.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary">View</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_footer(); ?>
