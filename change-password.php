<?php
include 'layout.php';
require_login();
$user = current_user();
if (isset($_POST['change'])) {
    $current=$_POST['current_password']??''; $new=$_POST['new_password']??''; $confirm=$_POST['confirm_password']??'';
    if (!password_verify($current,$user['Password'])) flash('error','Current password is incorrect.');
    elseif (strlen($new)<6) flash('error','New password must be at least 6 characters.');
    elseif ($new!==$confirm) flash('error','New passwords do not match.');
    else { $hash=password_hash($new,PASSWORD_DEFAULT); $uid=(int)$user['ID']; $stmt=mysqli_prepare($conn,"UPDATE account SET Password=? WHERE ID=?"); mysqli_stmt_bind_param($stmt,'si',$hash,$uid); mysqli_stmt_execute($stmt); flash('success','Password changed successfully.'); header('Location: profile.php'); exit(); }
}
page_header('Change Password - PawFect Home'); page_title('Change Password', 'Update your account password securely.');
?>
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6"><div class="card-clean p-4"><form method="post"><div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div><div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" minlength="6" required></div><div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" minlength="6" required></div><button name="change" class="btn btn-primary btn-block">Change Password</button></form></div></div></div></div>
<?php page_footer(); ?>
