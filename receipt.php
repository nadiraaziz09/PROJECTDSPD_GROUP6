<?php
include 'layout.php';
require_login();
$uid = current_user_id();
$id = (int)($_GET['id'] ?? 0);
$sql = "SELECT pay.*, pr.name product_name, pr.category, acc.Name customer_name FROM product_payments pay JOIN products pr ON pay.product_id=pr.id JOIN account acc ON pay.user_id=acc.ID WHERE pay.id=?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$receipt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$receipt || (($_SESSION['role'] ?? 0) == 1 && (int)$receipt['user_id'] !== $uid)) {
    flash('error', 'Receipt not found.');
    header('Location: payment_history.php');
    exit();
}
page_header('Payment Receipt - PawFect Home', 'payments');
page_title('Online Banking Receipt', 'Printable receipt for pet needs product payment.');
?>
<div class="container py-5">
    <div class="receipt-box mx-auto" style="max-width:760px">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><span class="text-primary">Paw</span>Fect Home</h2>
            <?php echo status_badge($receipt['status']); ?>
        </div>
        <table class="table">
            <tr><th>Receipt No.</th><td>#PFH-<?php echo str_pad($receipt['id'],5,'0',STR_PAD_LEFT); ?></td></tr>
            <tr><th>Transaction ID</th><td><?php echo h($receipt['transaction_id']); ?></td></tr>
            <tr><th>Bank Reference No.</th><td><?php echo h($receipt['bank_reference'] ?: '-'); ?></td></tr>
            <tr><th>Customer</th><td><?php echo h($receipt['customer_name']); ?></td></tr>
            <tr><th>Payer Name</th><td><?php echo h($receipt['payer_name'] ?: $receipt['customer_name']); ?></td></tr>
            <tr><th>Product</th><td><?php echo h($receipt['product_name']); ?></td></tr>
            <tr><th>Category</th><td><?php echo h($receipt['category']); ?></td></tr>
            <tr><th>Quantity</th><td><?php echo (int)$receipt['quantity']; ?></td></tr>
            <tr><th>Payment Method</th><td><?php echo h($receipt['payment_method']); ?></td></tr>
            <tr><th>Bank</th><td><?php echo h($receipt['bank_name'] ?: '-'); ?></td></tr>
            <tr><th>Date</th><td><?php echo h(date('d M Y, h:i A', strtotime($receipt['created_at']))); ?></td></tr>
            <tr><th>Amount Paid</th><td><strong>RM <?php echo number_format($receipt['amount'],2); ?></strong></td></tr>
        </table>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print mr-2"></i>Print / Save PDF</button>
        <a href="payment_history.php" class="btn btn-outline-secondary">Back</a>
    </div>
</div>
<?php page_footer(); ?>
