<?php
include 'layout.php';
require_login();
$user = current_user();
page_header('My Profile - PawFect Home'); page_title('My Profile', 'View your account and personal information.');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card-clean p-4">
                <div class="mb-4">
                    <h3 class="mb-1"><?php echo h($user['Name']); ?></h3>
                    <p class="text-muted mb-2"><?php echo h($user['Email']); ?></p>
                    <?php echo status_badge($user['Status']); ?>
                </div>
                <table class="table">
                    <tr><th>Role</th><td><?php echo $user['Account_Type']==1?'Customer':($user['Account_Type']==2?'Staff':'Admin'); ?></td></tr>
                    <tr><th>Phone</th><td><?php echo h($user['Phone'] ?: '-'); ?></td></tr>
                    <tr><th>Address</th><td><?php echo h($user['Address'] ?: '-'); ?></td></tr>
                    <tr><th>Age</th><td><?php echo h($user['Age'] ?: '-'); ?></td></tr>
                    <tr><th>Salary</th><td><?php echo $user['Salary'] ? 'RM '.number_format($user['Salary']) : '-'; ?></td></tr>
                </table>
                <a href="editprofile.php" class="btn btn-primary">Edit Profile</a>
                <a href="change-password.php" class="btn btn-outline-secondary">Change Password</a>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>
