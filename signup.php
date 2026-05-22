<?php
include 'layout.php';
if (isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $passwordRaw = $_POST['password'] ?? '';
    if ($name === '' || $email === '' || strlen($passwordRaw) < 6) {
        flash('error', 'Please fill all fields. Password must be at least 6 characters.');
    } else {
        $stmt = mysqli_prepare($conn, "SELECT ID FROM account WHERE Email=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($exists) > 0) {
            flash('error', 'Email already registered.');
        } else {
            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
            $role = 1;
            $stmt = mysqli_prepare($conn, "INSERT INTO account (Name, Email, Password, Account_Type, Phone) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sssis', $name, $email, $password, $role, $phone);
            if (mysqli_stmt_execute($stmt)) flash('success', 'Account created successfully. Please sign in.'); else flash('error', 'Unable to create account.');
        }
    }
    header('Location: signup.php'); exit();
}
page_header('Sign Up - PawFect Home');
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <h2 class="text-center mb-4">Create Customer Account</h2>
        <?php if ($msg = flash('success')): ?><div class="alert alert-success"><?php echo h($msg); ?></div><?php endif; ?>
        <?php if ($msg = flash('error')): ?><div class="alert alert-danger"><?php echo h($msg); ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group"><label>Full Name</label><input type="text" name="name" class="form-control" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="form-group"><label>Contact Number</label><input type="text" name="phone" class="form-control"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" minlength="6" required></div>
            <button type="submit" name="register" class="btn btn-primary btn-block">Sign Up</button>
        </form>
        <div class="text-center mt-3">Already have an account? <a href="signin.php">Sign in</a></div>
    </div>
</div>
<?php page_footer(); ?>
