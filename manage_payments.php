<?php
include 'layout.php';
require_role([2,3]);

if (isset($_POST['update_status'])) {
    $id = (int)($_POST['payment_id'] ?? 0);
    $statusNew = trim($_POST['status'] ?? '');
    if (in_array($statusNew, ['pending','pending verification','completed','failed','refunded'], true)) {
        $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'si', $statusNew, $id);
        mysqli_stmt_execute($stmt);
        if ($statusNew === 'completed') {
            $payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_id, quantity FROM product_payments WHERE id=$id LIMIT 1"));
            if ($payment) {
                $pid = (int)$payment['product_id'];
                $qty = (int)$payment['quantity'];
                mysqli_query($conn, "UPDATE products SET stock=GREATEST(stock-$qty,0) WHERE id=$pid");
            }
        }
        flash('success', 'Payment status updated.');
    }
    header('Location: manage_payments.php');
    exit();
}

$status = trim($_GET['status'] ?? '');
$where = $status ? "WHERE pay.status='" . mysqli_real_escape_string($conn,$status) . "'" : '';
$result = mysqli_query($conn, "SELECT pay.*, pr.name product_name, acc.Name customer_name FROM product_payments pay JOIN products pr ON pay.product_id=pr.id JOIN account acc ON pay.user_id=acc.ID $where ORDER BY pay.created_at DESC");
page_header('Payment Management - PawFect Home', 'payments');
page_title('Product Payment Transactions', 'Staff and admin can track and verify pet needs online banking payments.');
?>
<div class="container py-5">
    <form class="action-bar mb-4">
        <div class="form-row">
            <div class="col-md-9">
                <select name="status" class="custom-select">
                    <option value="">All Status</option>
                    <?php foreach(['pending','pending verification','completed','failed','refunded'] as $s): ?>
                        <option <?php echo $status===$s?'selected':''; ?>><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary btn-block">Filter</button></div>
        </div>
    </form>
    <div class="table-responsive card-clean">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Customer</th><th>Product</th><th>Qty</th><th>Amount</th><th>Method / Bank</th><th>Ref.</th><th>Status</th><th>Update</th><th>Receipt</th></tr></thead>
            <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="10" class="text-center text-muted p-4">No product payment transactions found.</td></tr>
            <?php endif; ?>
            <?php while($p=mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo h(date('d M Y', strtotime($p['created_at']))); ?></td>
                    <td><?php echo h($p['customer_name']); ?></td>
                    <td><?php echo h($p['product_name']); ?></td>
                    <td><?php echo (int)$p['quantity']; ?></td>
                    <td>RM <?php echo number_format($p['amount'],2); ?></td>
                    <td><?php echo h($p['bank_name'] ?: $p['payment_method']); ?><br><small class="text-muted"><?php echo h($p['gateway_provider'] ?: '-'); ?></small></td>
                    <td><?php echo h($p['bank_reference'] ?: '-'); ?></td>
                    <td><?php echo status_badge($p['status']); ?></td>
                    <td>
                        <form method="post" class="d-flex">
                            <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                            <select name="status" class="custom-select custom-select-sm mr-1" style="min-width:145px">
                                <?php foreach(['pending','pending verification','completed','failed','refunded'] as $s): ?>
                                    <option <?php echo $p['status']===$s?'selected':''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button name="update_status" class="btn btn-sm btn-primary">Save</button>
                        </form>
                    </td>
                    <td><a href="receipt.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_footer(); ?>
