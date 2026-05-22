<?php
include 'layout.php';
if (isset($_GET['logout'])) {
    flash('success', 'You have signed out successfully. Please sign in again to continue.');
}
if (isset($_GET['expired'])) {
    flash('error', 'Your session has ended. Please sign in again.');
}
if (isset($_POST['enter'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = mysqli_prepare($conn, "SELECT * FROM account WHERE Email=? AND Status='active' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    if ($user && password_verify($password, $user['Password'])) {
        session_regenerate_id(true);
        $_SESSION['email'] = $user['Email'];
        $_SESSION['name'] = $user['Name'];
        $_SESSION['role'] = (int)$user['Account_Type'];
        header('Location: ' . role_home());
        exit();
    }
    flash('error', 'Invalid email or password.');
    header('Location: signin.php');
    exit();
}
page_header('Sign In - PawFect Home');
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <h2 class="text-center mb-4">Welcome Back</h2>
        <?php if ($msg = flash('error')): ?><div class="alert alert-danger"><?php echo h($msg); ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
            <button type="submit" name="enter" class="btn btn-primary btn-block">Sign In</button>
        </form>
        <div class="text-center mt-3"><a href="forgot-password.php">Forgot password?</a> · <a href="signup.php">Create account</a></div>
        <div class="alert alert-light border mt-4 small mb-0">
            Demo roles from database: customer / staff / admin are redirected to their own dashboards.
        </div>
    </div>
</div>
<?php page_footer(); ?>
