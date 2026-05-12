<?php
include 'layout.php';
require_role([2,3]);
$id=(int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pet = ['name'=>'','type'=>'Dog','breed'=>'','age'=>'','gender'=>'Male','health_status'=>'Healthy','description'=>'','photo'=>'','status'=>'available'];
if ($id) { $r=mysqli_query($conn,"SELECT * FROM pets WHERE id=$id"); $pet=mysqli_fetch_assoc($r) ?: $pet; }
if (isset($_POST['save'])) {
    $name=trim($_POST['name']); $type=$_POST['type']; $breed=trim($_POST['breed']); $age=(float)$_POST['age']; $gender=$_POST['gender']; $health=trim($_POST['health_status']); $desc=trim($_POST['description']); $photo=trim($_POST['photo']); $status=$_POST['status'];
    if ($id) {
        $stmt=mysqli_prepare($conn,"UPDATE pets SET name=?,type=?,breed=?,age=?,gender=?,health_status=?,description=?,photo=?,status=? WHERE id=?");
        mysqli_stmt_bind_param($stmt,'sssdsssssi',$name,$type,$breed,$age,$gender,$health,$desc,$photo,$status,$id);
    } else {
        $stmt=mysqli_prepare($conn,"INSERT INTO pets (name,type,breed,age,gender,health_status,description,photo,status) VALUES (?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt,'sssdsssss',$name,$type,$breed,$age,$gender,$health,$desc,$photo,$status);
    }
    mysqli_stmt_execute($stmt); flash('success','Pet saved successfully.'); header('Location: manage_pets.php'); exit();
}
page_header(($id?'Edit':'Add').' Pet - PawFect Home','pets'); page_title($id?'Edit Pet':'Add New Pet','Maintain clear adoption pet information with real photo links.');
?>
<div class="container py-5"><div class="card-clean p-4"><form method="post"><input type="hidden" name="id" value="<?php echo $id; ?>"><div class="form-row"><div class="col-md-6 form-group"><label>Name</label><input name="name" class="form-control" value="<?php echo h($pet['name']); ?>" required></div><div class="col-md-3 form-group"><label>Type</label><select name="type" class="custom-select"><?php foreach(['Dog','Cat','Rabbit','Bird'] as $x): ?><option <?php echo $pet['type']===$x?'selected':''; ?>><?php echo $x; ?></option><?php endforeach; ?></select></div><div class="col-md-3 form-group"><label>Gender</label><select name="gender" class="custom-select"><?php foreach(['Male','Female'] as $x): ?><option <?php echo $pet['gender']===$x?'selected':''; ?>><?php echo $x; ?></option><?php endforeach; ?></select></div></div><div class="form-row"><div class="col-md-4 form-group"><label>Breed</label><input name="breed" class="form-control" value="<?php echo h($pet['breed']); ?>" required></div><div class="col-md-3 form-group"><label>Age</label><input type="number" step="0.1" name="age" class="form-control" value="<?php echo h($pet['age']); ?>" required></div><div class="col-md-5 form-group"><label>Status</label><select name="status" class="custom-select"><?php foreach(['available','adopted','inactive'] as $x): ?><option <?php echo $pet['status']===$x?'selected':''; ?>><?php echo $x; ?></option><?php endforeach; ?></select></div></div><div class="form-group"><label>Health Status</label><input name="health_status" class="form-control" value="<?php echo h($pet['health_status']); ?>" required></div><div class="form-group"><label>Real Photo URL or Local Image Path</label><input name="photo" class="form-control" value="<?php echo h($pet['photo']); ?>" placeholder="Example: img/carousel-2.jpg or a real photo URL" required></div><div class="form-group"><label>Description</label><textarea name="description" rows="5" class="form-control" required><?php echo h($pet['description']); ?></textarea></div><button name="save" class="btn btn-primary">Save Pet</button> <a href="manage_pets.php" class="btn btn-outline-secondary">Cancel</a></form></div></div>
<?php page_footer(); ?>
