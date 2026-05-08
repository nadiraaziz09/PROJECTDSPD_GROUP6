<?php
include 'db.php';

if (!isset($_GET['token'])) {
    die("Invalid link");
}

$token = $_GET['token'];

$result = mysqli_query($conn, "SELECT * FROM account WHERE reset_token='$token'");

if (mysqli_num_rows($result) == 0) {
    die("Invalid or expired token");
}
?>

<h2>Reset Password</h2>

<form method="POST">
    <input type="password" name="new_password" placeholder="New Password" required>
    <button type="submit" name="update">Update Password</button>
</form>

<?php
if (isset($_POST['update'])) {

    $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    mysqli_query($conn, "
        UPDATE account 
        SET Password='$newPassword',
            reset_token=NULL
        WHERE reset_token='$token'
    ");

    echo "<p style='color:green'>Password updated successfully!</p>";
    echo "<a href='signin.php'>Go to Login</a>";
}
?>