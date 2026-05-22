<?php
include 'layout.php';
require_role(3);
$from=$_GET['from'] ?? date('Y-m-01'); $to=$_GET['to'] ?? date('Y-m-d');
$fromSafe = mysqli_real_escape_string($conn, $from);
$toSafe = mysqli_real_escape_string($conn, $to);
$summary = [
    'Total Pets' => mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM pets"))['total'],
    'Available Pets' => mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM pets WHERE status='available'"))['total'],
    'Applications' => mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM adoption_applications WHERE DATE(created_at) BETWEEN '$fromSafe' AND '$toSafe'"))['total'],
    'Appointments' => mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM appointments WHERE appointment_date BETWEEN '$fromSafe' AND '$toSafe'"))['total'],
    'Product Payments' => mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) total FROM product_payments WHERE DATE(created_at) BETWEEN '$fromSafe' AND '$toSafe'"))['total'],
    'Product Collection (RM)' => mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) total FROM product_payments WHERE status='completed' AND DATE(created_at) BETWEEN '$fromSafe' AND '$toSafe'"))['total']
];
$appStatus=mysqli_query($conn,"SELECT status, COUNT(*) total FROM adoption_applications GROUP BY status");
$petTypes=mysqli_query($conn,"SELECT type, COUNT(*) total FROM pets GROUP BY type");
$productCats=mysqli_query($conn,"SELECT category, COUNT(*) total FROM products GROUP BY category");
page_header('System Reports - PawFect Home'); page_title('Reports Dashboard','Monitor adoption activity, appointments and product payment statistics.');
?>
<div class="container py-5"><form class="action-bar mb-4"><div class="form-row"><div class="col-md-4"><label>From</label><input type="date" name="from" value="<?php echo h($from); ?>" class="form-control"></div><div class="col-md-4"><label>To</label><input type="date" name="to" value="<?php echo h($to); ?>" class="form-control"></div><div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary btn-block">Generate Report</button></div></div></form><div class="row">
<?php foreach($summary as $label=>$value): ?><div class="col-md-4 mb-4"><div class="stat-card"><h2 class="text-primary"><?php echo is_numeric($value)?number_format((float)$value, strpos($label,'RM')!==false?2:0):h($value); ?></h2><p class="mb-0 text-muted"><?php echo h($label); ?></p></div></div><?php endforeach; ?>
</div><div class="row"><div class="col-md-4 mb-4"><div class="card-clean p-4"><h4>Application Status</h4><table class="table"><tbody><?php while($r=mysqli_fetch_assoc($appStatus)): ?><tr><td><?php echo status_badge($r['status']); ?></td><td><?php echo $r['total']; ?></td></tr><?php endwhile; ?></tbody></table></div></div><div class="col-md-4 mb-4"><div class="card-clean p-4"><h4>Pets by Type</h4><table class="table"><tbody><?php while($r=mysqli_fetch_assoc($petTypes)): ?><tr><td><?php echo h($r['type']); ?></td><td><?php echo $r['total']; ?></td></tr><?php endwhile; ?></tbody></table></div></div><div class="col-md-4 mb-4"><div class="card-clean p-4"><h4>Products by Category</h4><table class="table"><tbody><?php while($r=mysqli_fetch_assoc($productCats)): ?><tr><td><?php echo h($r['category']); ?></td><td><?php echo $r['total']; ?></td></tr><?php endwhile; ?></tbody></table></div></div></div></div>
<?php page_footer(); ?>
