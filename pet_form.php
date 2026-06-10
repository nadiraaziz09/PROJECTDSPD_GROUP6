<?php
include 'layout.php';
require_role([2,3]);

function save_pet_form_image_upload($field = 'photo_file') {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null, null];
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return [false, null, 'Photo upload failed. Please try again.'];
    }

    if ((int)$_FILES[$field]['size'] > 5 * 1024 * 1024) {
        return [false, null, 'Photo must be 5 MB or smaller.'];
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $imageInfo = @getimagesize($tmp);
    if (!$imageInfo) {
        return [false, null, 'Please upload a valid image file.'];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];
    $mime = $imageInfo['mime'] ?? '';
    if (!isset($allowed[$mime])) {
        return [false, null, 'Only JPG, PNG, GIF or WEBP images are allowed.'];
    }

    $uploadDir = __DIR__ . '/uploads/pets';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'pet_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($tmp, $target)) {
        return [false, null, 'Unable to save uploaded photo. Please check the uploads folder permission.'];
    }

    return [true, 'uploads/pets/' . $fileName, null];
}

$id=(int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pet = ['name'=>'','type'=>'Dog','breed'=>'','age'=>'','gender'=>'Male','health_status'=>'Healthy','description'=>'','photo'=>'','status'=>'available'];
if ($id) { $r=mysqli_query($conn,"SELECT * FROM pets WHERE id=$id"); $pet=mysqli_fetch_assoc($r) ?: $pet; }
if (isset($_POST['save'])) {
    $name=trim($_POST['name'] ?? ''); $type=$_POST['type'] ?? 'Dog'; $breed=trim($_POST['breed'] ?? ''); $age=(float)($_POST['age'] ?? 0); $gender=$_POST['gender'] ?? 'Male'; $health=trim($_POST['health_status'] ?? ''); $desc=trim($_POST['description'] ?? ''); $photoText=trim($_POST['photo'] ?? ''); $status=$_POST['status'] ?? 'available';

    [$photoOk, $uploadedPhoto, $photoError] = save_pet_form_image_upload('photo_file');
    if (!$photoOk) {
        flash('error', $photoError ?: 'Photo upload failed.');
        header('Location: pet_form.php' . ($id ? '?id=' . $id : ''));
        exit();
    }

    $photo = $uploadedPhoto ?: ($photoText !== '' ? $photoText : ($pet['photo'] ?? ''));
    if ($photo === '') {
        flash('error', 'Please enter a photo URL/local image path or upload a pet photo file.');
        header('Location: pet_form.php' . ($id ? '?id=' . $id : ''));
        exit();
    }

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
<div class="container py-5">
    <div class="card-clean p-4">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="form-row">
                <div class="col-md-6 form-group">
                    <label>Name</label>
                    <input name="name" class="form-control" value="<?php echo h($pet['name']); ?>" required>
                </div>
                <div class="col-md-3 form-group">
                    <label>Type</label>
                    <select name="type" class="custom-select">
                        <?php foreach(['Dog','Cat','Rabbit','Bird','Other'] as $x): ?>
                            <option <?php echo $pet['type']===$x?'selected':''; ?>><?php echo $x; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Gender</label>
                    <select name="gender" class="custom-select">
                        <?php foreach(['Male','Female'] as $x): ?>
                            <option <?php echo $pet['gender']===$x?'selected':''; ?>><?php echo $x; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="col-md-4 form-group">
                    <label>Breed</label>
                    <input name="breed" class="form-control" value="<?php echo h($pet['breed']); ?>" required>
                </div>
                <div class="col-md-3 form-group">
                    <label>Age</label>
                    <input type="number" step="0.1" name="age" class="form-control" value="<?php echo h($pet['age']); ?>" required>
                </div>
                <div class="col-md-5 form-group">
                    <label>Status</label>
                    <select name="status" class="custom-select">
                        <?php foreach(['available','reserved','adopted','inactive'] as $x): ?>
                            <option <?php echo $pet['status']===$x?'selected':''; ?>><?php echo $x; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Health Status</label>
                <input name="health_status" class="form-control" value="<?php echo h($pet['health_status']); ?>" required>
            </div>
            <div class="form-group">
                <label>Real Photo URL or Local Image Path <span class="text-muted small">(optional if uploading)</span></label>
                <input name="photo" class="form-control" value="<?php echo h($pet['photo']); ?>" placeholder="Example: img/carousel-2.jpg or a real photo URL">
            </div>
            <div class="form-group">
                <label>Upload Pet Photo <span class="text-muted small">(JPG / PNG / GIF / WEBP, max 5 MB)</span></label>
                <input type="file" name="photo_file" class="form-control-file" accept="image/jpeg,image/png,image/gif,image/webp">
                <small class="form-text text-muted">Choose a photo from your computer. If you upload a file, it will be used instead of the URL/path above.</small>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="5" class="form-control" required><?php echo h($pet['description']); ?></textarea>
            </div>
            <button name="save" class="btn btn-primary">Save Pet</button>
            <a href="manage_pets.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php page_footer(); ?>
