<?php
include 'layout.php';
include_once 'adoption_discussion_helpers.php';
include_once 'announcement_helpers.php';
require_role([2,3]);
ensure_adoption_discussions_table();
$user = current_user();
$appPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM adoption_applications WHERE status='pending'"))['total'];
$discussionPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM adoption_discussions WHERE status='pending'"))['total'];
$pending = (int)$appPending + (int)$discussionPending;
$appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM appointments WHERE status IN ('booked','rescheduled') AND COALESCE(appointment_type,'general') <> 'adoption'"))['total'];
$cards = [
    ['manage_pets.php','fas fa-paw','Pet Management','Add, edit and update pet records.'],
    ['staff_applications.php','fas fa-clipboard-check','Adoption Applications',$pending . ' pending application/discussion request(s) need review.'],
    ['appointment.php','fas fa-calendar-check','Appointment Management',$appointments . ' active appointment(s).'],
    ['manage_payments.php','fas fa-credit-card','Product Payments','View pet needs product transactions.'],
    ['manage_products.php','fas fa-shopping-bag','Manage Pet Needs','Add, edit and update food, toys, care items and accessories.'],
    ['manage_contact.php','fas fa-envelope','Contact Messages','View messages sent by users through the contact form.'],
    ['products.php','fas fa-store','Pet Needs Store','View the customer product store.'],
    ['profile.php','fas fa-user','My Profile','Update staff profile details.']
];
page_header('Staff Dashboard - PawFect Home', 'home'); page_title('Staff Dashboard', 'Welcome, ' . $user['Name'] . '. Manage shelter operations here.');
?>
<div class="container py-5"><?php render_announcement_notifications(2); ?><div class="row">
<?php foreach ($cards as $c): ?><div class="col-md-4 mb-4"><div class="dashboard-card"><i class="<?php echo $c[1]; ?>"></i><h5><?php echo h($c[2]); ?></h5><p class="text-muted"><?php echo h($c[3]); ?></p><a href="<?php echo h($c[0]); ?>" class="btn btn-primary">Open</a></div></div><?php endforeach; ?>
</div></div>
<?php page_footer(); ?>
