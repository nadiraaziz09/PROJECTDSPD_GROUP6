<?php
include 'layout.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '') {
    flash('error', 'Invalid password reset link.');
    header('Location: forgot-password.php');
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT ID, Name, Email, reset_token_expiry FROM account WHERE reset_token=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $token);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user || (!empty($user['reset_token_expiry']) && strtotime($user['reset_token_expiry']) < time())) {
    flash('error', 'Invalid or expired reset link. Please request a new one.');
    header('Location: forgot-password.php');
    exit();
}

if (isset($_POST['update'])) {
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (strlen($new) < 6) {
        flash('error', 'Password must be at least 6 characters.');
    } elseif ($new !== $confirm) {
        flash('error', 'Password confirmation does not match.');
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE account SET Password=?, reset_token=NULL, reset_token_expiry=NULL WHERE ID=?");
        mysqli_stmt_bind_param($stmt, 'si', $hash, $user['ID']);
        mysqli_stmt_execute($stmt);
        flash('success', 'Password updated successfully. You may sign in now.');
        header('Location: signin.php');
        exit();
    }
}

page_header('Reset Password - PawFect Home');
?>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width:520px">
        <h2 class="text-center mb-3">Reset Password</h2>
        <p class="text-center text-muted">Create a new password for <?php echo h($user['Email']); ?>.</p>
        <form method="post">
            <input type="hidden" name="token" value="<?php echo h($token); ?>">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6">
            </div>
            <button name="update" class="btn btn-primary btn-block">Update Password</button>
        </form>
        <div class="text-center mt-3"><a href="signin.php">Back to Sign In</a></div>
    </div>
</div>
<?php page_footer(); ?>
