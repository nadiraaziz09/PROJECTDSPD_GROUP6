<?php
include 'layout.php';
require_login();
$user = current_user();
if (isset($_POST['save'])) {
    $name=trim($_POST['name']); $phone=trim($_POST['phone']); $address=trim($_POST['address']); $age=(int)($_POST['age']??0); $salary=(int)($_POST['salary']??0); $uid=(int)$user['ID'];
    $stmt=mysqli_prepare($conn,"UPDATE account SET Name=?, Phone=?, Address=?, Age=?, Salary=? WHERE ID=?");
    mysqli_stmt_bind_param($stmt,'sssiii',$name,$phone,$address,$age,$salary,$uid);
    mysqli_stmt_execute($stmt);
    $_SESSION['name']=$name;
    flash('success','Profile updated successfully.'); header('Location: profile.php'); exit();
}
page_header('Edit Profile - PawFect Home'); page_title('Edit Profile', 'Keep your personal information up to date.');
?>
<div class="container py-5">
    <div class="card-clean p-4">
        <form method="post">
            <div class="form-row">
                <div class="col-md-6 form-group"><label>Name</label><input name="name" class="form-control" value="<?php echo h($user['Name']); ?>" required></div>
                <div class="col-md-6 form-group"><label>Email</label><input class="form-control" value="<?php echo h($user['Email']); ?>" disabled></div>
            </div>
            <div class="form-row">
                <div class="col-md-6 form-group"><label>Phone</label><input name="phone" class="form-control" value="<?php echo h($user['Phone']); ?>"></div>
                <div class="col-md-3 form-group"><label>Age</label><input type="number" name="age" class="form-control" value="<?php echo h($user['Age']); ?>"></div>
                <div class="col-md-3 form-group"><label>Salary</label><input type="number" name="salary" class="form-control" value="<?php echo h($user['Salary']); ?>"></div>
            </div>
            <div class="form-group"><label>Address</label><input name="address" class="form-control" value="<?php echo h($user['Address']); ?>"></div>
            <button name="save" class="btn btn-primary">Save Profile</button>
            <a href="profile.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php page_footer(); ?>
