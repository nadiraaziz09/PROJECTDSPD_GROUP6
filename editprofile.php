```php
<?php
include 'layout.php';
require_login();

$user = current_user();

function save_profile_photo_upload($field = 'profile_photo') {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [true, null, null];
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return [false, null, 'Profile photo upload failed. Please try again.'];
    }

    if ((int)$_FILES[$field]['size'] > 5 * 1024 * 1024) {
        return [false, null, 'Profile photo must be 5 MB or smaller.'];
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

    $uploadDir = __DIR__ . '/uploads/profiles';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'profile_' . current_user_id() . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];

    $target = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($tmp, $target)) {
        return [false, null, 'Unable to save uploaded profile photo. Please check uploads folder permission.'];
    }

    return [true, 'uploads/profiles/' . $fileName, null];
}

if (isset($_POST['save'])) {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // PHONE VALIDATION
    if (!empty($phone) && !preg_match('/^[0-9]+$/', $phone)) {
        echo "<script>
                alert('Phone number can contain numbers only!');
                window.location.href='editprofile.php';
              </script>";
        exit();
    }

    $address = trim($_POST['address'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $salary = (int)($_POST['salary'] ?? 0);
    $uid = (int)$user['ID'];

    [$photoOk, $uploadedPhoto, $photoError] = save_profile_photo_upload('profile_photo');

    if (!$photoOk) {
        flash('error', $photoError ?: 'Profile photo upload failed.');
        header('Location: editprofile.php');
        exit();
    }

    $profilePhoto = $uploadedPhoto ?: ($user['Profile_Photo'] ?? null);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE account
         SET Name=?, Phone=?, Address=?, Age=?, Salary=?, Profile_Photo=?
         WHERE ID=?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'sssiisi',
        $name,
        $phone,
        $address,
        $age,
        $salary,
        $profilePhoto,
        $uid
    );

    mysqli_stmt_execute($stmt);

    $_SESSION['name'] = $name;

    flash('success', 'Profile updated successfully.');

    header('Location: profile.php');
    exit();
}

page_header('Edit Profile - PawFect Home');
page_title('Edit Profile', 'Keep your personal information up to date.');

$currentPhoto = pawfect_image_src(
    $user['Profile_Photo'] ?? '',
    'img/user.jpg'
);
?>

<div class="container py-5">
    <div class="card-clean p-4">

        <form method="post" enctype="multipart/form-data">

            <div class="text-center mb-4">
                <img
                    src="<?php echo h($currentPhoto); ?>"
                    alt="Profile Photo"
                    style="width:120px;height:120px;object-fit:cover;border-radius:50%;box-shadow:0 8px 20px rgba(0,0,0,.12);">

                <div class="form-group mt-3 mb-0">
                    <label class="font-weight-bold d-block">
                        Upload Profile Photo
                    </label>

                    <input
                        type="file"
                        name="profile_photo"
                        class="form-control-file d-inline-block"
                        style="max-width:330px"
                        accept="image/jpeg,image/png,image/gif,image/webp">

                    <small class="form-text text-muted">
                        JPG / PNG / GIF / WEBP, max 5 MB.
                        Leave empty to keep current photo.
                    </small>
                </div>
            </div>

            <div class="form-row">

                <div class="col-md-6 form-group">
                    <label>Name</label>
                    <input
                        name="name"
                        class="form-control"
                        value="<?php echo h($user['Name']); ?>"
                        required>
                </div>

                <div class="col-md-6 form-group">
                    <label>Email</label>
                    <input
                        class="form-control"
                        value="<?php echo h($user['Email']); ?>"
                        disabled>
                </div>

            </div>

            <div class="form-row">

                <div class="col-md-6 form-group">
                    <label>Phone</label>
                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="<?php echo h($user['Phone']); ?>"
                        pattern="[0-9]+"
                        inputmode="numeric"
                        oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                        placeholder="Numbers only">
                </div>

                <div class="col-md-3 form-group">
                    <label>Age</label>
                    <input
                        type="number"
                        name="age"
                        class="form-control"
                        value="<?php echo h($user['Age']); ?>">
                </div>

                <div class="col-md-3 form-group">
                    <label>Salary</label>
                    <input
                        type="number"
                        name="salary"
                        class="form-control"
                        value="<?php echo h($user['Salary']); ?>">
                </div>

            </div>

            <div class="form-group">
                <label>Address</label>
                <input
                    name="address"
                    class="form-control"
                    value="<?php echo h($user['Address']); ?>">
            </div>

            <button name="save" class="btn btn-primary">
                Save Profile
            </button>

            <a href="profile.php" class="btn btn-outline-secondary">
                Cancel
            </a>

        </form>

    </div>
</div>

<?php page_footer(); ?>
```
