<?php
include 'layout.php';
require_role(1);
$user = current_user();
$cards = [
    ['pets.php','fas fa-paw','Browse Pets','Find pets by type, breed, age and gender.'],
    ['wishlist.php','fas fa-heart','My Wishlist','Revisit pets that you saved.'],
    ['applications.php','fas fa-file-signature','My Applications','Track pending, approved or rejected requests.'],
    ['appointment.php','fas fa-calendar-check','Appointments','Book, edit or cancel shelter visits.'],
    ['products.php','fas fa-shopping-bag','Pet Needs Store','Buy food, toys and care items.'],
    ['payment_history.php','fas fa-receipt','Product Payments','View receipts for pet needs purchases.'],
    ['profile.php','fas fa-user-edit','My Profile','Update personal information.']
];
page_header('Customer Dashboard - PawFect Home', 'home'); page_title('Customer Dashboard', 'Welcome, ' . $user['Name'] . '. Manage your adoption journey here.');
?>
<div class="container py-5">
    <div class="row">
        <?php foreach ($cards as $c): ?>
        <div class="col-md-4 mb-4"><div class="dashboard-card"><i class="<?php echo $c[1]; ?>"></i><h5><?php echo h($c[2]); ?></h5><p class="text-muted"><?php echo h($c[3]); ?></p><a href="<?php echo h($c[0]); ?>" class="btn btn-primary">Open</a></div></div>
        <?php endforeach; ?>
    </div>
</div>
<?php page_footer(); ?>
