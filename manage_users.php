<?php
include 'layout.php';
require_role(3);
if (isset($_GET['toggle'])) {
    $id=(int)$_GET['toggle'];
    mysqli_query($conn,"UPDATE account SET Status=IF(Status='active','inactive','active') WHERE ID=$id AND Account_Type=1");
    flash('success','Customer status updated.'); header('Location: manage_users.php'); exit();
}
if (isset($_GET['delete'])) {
    $id=(int)$_GET['delete'];
    mysqli_query($conn,"DELETE FROM account WHERE ID=$id AND Account_Type=1");
    flash('success','Customer account deleted.'); header('Location: manage_users.php'); exit();
}
$result=mysqli_query($conn,"SELECT * FROM account WHERE Account_Type=1 ORDER BY ID DESC");
page_header('Manage Customers - PawFect Home'); page_title('Customer Account Management','Admin can monitor, disable or delete customer accounts.');
?>
<div class="container py-5"><div class="table-responsive card-clean"><table class="table mb-0"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php while($u=mysqli_fetch_assoc($result)): ?><tr><td><?php echo h($u['Name']); ?></td><td><?php echo h($u['Email']); ?></td><td><?php echo h($u['Phone'] ?? '-'); ?></td><td><?php echo status_badge($u['Status'] ?? 'active'); ?></td><td><a href="manage_users.php?toggle=<?php echo $u['ID']; ?>" class="btn btn-sm btn-primary">Enable/Disable</a> <a href="manage_users.php?delete=<?php echo $u['ID']; ?>" onclick="return confirm('Delete this customer?')" class="btn btn-sm btn-outline-danger">Delete</a></td></tr><?php endwhile; ?>
</tbody></table></div></div>
<?php page_footer(); ?>
