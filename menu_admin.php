<?php
include 'layout.php';
require_role(3);
$user = current_user();
$cards = [
    ['manage_users.php','fas fa-users','Manage Customers','View, edit, disable or delete customer accounts.'],
    ['manage_staff.php','fas fa-user-shield','Manage Staff','Create and maintain staff/admin accounts.'],
    ['manage_pets.php','fas fa-paw','Manage Pets','Add and update pet profiles and health records.'],
    ['staff_applications.php','fas fa-clipboard-check','Applications','Approve or reject adoption applications.'],
    ['manage_payments.php','fas fa-credit-card','Product Payments','Track pet needs product transactions.'],
    ['manage_products.php','fas fa-shopping-bag','Manage Pet Needs','Add, edit and update store products.'],
    ['appointment.php','fas fa-calendar-check','Appointments','Manage customer visit bookings.'],
    ['reports.php','fas fa-chart-line','Reports','Monitor adoption activity and system usage.'],
    ['announcements.php','fas fa-bullhorn','Announcements','Create, edit and delete system notices.'],
    ['products.php','fas fa-store','Pet Needs Store','View food, toys and care items.']
];
page_header('Admin Dashboard - PawFect Home', 'home'); page_title('Admin Dashboard', 'Welcome, ' . $user['Name'] . '. Control the full adoption system here.');
?>
<div class="container py-5"><div class="row">
<?php foreach ($cards as $c): ?><div class="col-md-4 mb-4"><div class="dashboard-card"><i class="<?php echo $c[1]; ?>"></i><h5><?php echo h($c[2]); ?></h5><p class="text-muted"><?php echo h($c[3]); ?></p><a href="<?php echo h($c[0]); ?>" class="btn btn-primary">Open</a></div></div><?php endforeach; ?>
</div></div>
<?php page_footer(); ?>
