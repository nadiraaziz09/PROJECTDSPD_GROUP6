<?php
include 'layout.php';
require_role([2,3]);

function save_product_form_image_upload($field = 'photo_file') {
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

    $uploadDir = __DIR__ . '/uploads/products';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($tmp, $target)) {
        return [false, null, 'Unable to save uploaded photo. Please check the uploads folder permission.'];
    }

    return [true, 'uploads/products/' . $fileName, null];
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$product = ['name'=>'','category'=>'Food','description'=>'','price'=>'','stock'=>'','photo'=>''];
if ($id) {
    $r = mysqli_query($conn, "SELECT * FROM products WHERE id=$id");
    $product = mysqli_fetch_assoc($r) ?: $product;
}

if (isset($_POST['save'])) {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    $quantity = $stock;
    $photoText = trim($_POST['photo'] ?? '');

    [$photoOk, $uploadedPhoto, $photoError] = save_product_form_image_upload('photo_file');
    if (!$photoOk) {
        flash('error', $photoError ?: 'Photo upload failed.');
        header('Location: product_form.php' . ($id ? '?id=' . $id : ''));
        exit();
    }

    $photo = $uploadedPhoto ?: ($photoText !== '' ? $photoText : ($product['photo'] ?? ''));

    if ($name === '' || $category === '' || $description === '' || $price <= 0 || $photo === '') {
        flash('error', 'Please complete all fields. Price must be more than RM 0. Please enter a photo path/URL or upload a photo file.');
        header('Location: product_form.php' . ($id ? '?id=' . $id : ''));
        exit();
    }

    if ($id) {
        $stmt = mysqli_prepare($conn, "UPDATE products SET name=?, category=?, description=?, price=?, stock=?, quantity=?, photo=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssdiisi', $name, $category, $description, $price, $stock, $quantity, $photo, $id);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO products (name, category, description, price, stock, quantity, photo) VALUES (?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'sssdiis', $name, $category, $description, $price, $stock, $quantity, $photo);
    }
    mysqli_stmt_execute($stmt);
    flash('success', 'Pet needs product saved successfully.');
    header('Location: manage_products.php');
    exit();
}

page_header(($id ? 'Edit' : 'Add') . ' Pet Needs Product - PawFect Home', 'manage_products');
page_title($id ? 'Edit Pet Needs Product' : 'Add Pet Needs Product', 'Update products that customers can buy through online banking payment.');
?>
<div class="container py-5">
    <div class="card-clean p-4">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="form-row">
                <div class="col-md-6 form-group">
                    <label>Product Name</label>
                    <input name="name" class="form-control" value="<?php echo h($product['name']); ?>" required>
                </div>
                <div class="col-md-6 form-group">
                    <label>Category</label>
                    <select name="category" class="custom-select" required>
                        <?php foreach(['Food','Care','Toys','Accessories','Health'] as $x): ?>
                            <option <?php echo $product['category'] === $x ? 'selected' : ''; ?>><?php echo $x; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="col-md-4 form-group">
                    <label>Price (RM)</label>
                    <input type="number" step="0.01" min="0.01" name="price" class="form-control" value="<?php echo h($product['price']); ?>" required>
                </div>
                <div class="col-md-4 form-group">
                    <label>Stock</label>
                    <input type="number" min="0" name="stock" class="form-control" value="<?php echo h($product['stock']); ?>" required>
                </div>
                <div class="col-md-4 form-group">
                    <label>Photo Path <span class="text-muted small">(optional if uploading)</span></label>
                    <input name="photo" class="form-control" value="<?php echo h($product['photo']); ?>" placeholder="Example: img/blog-3.jpg">
                </div>
            </div>
            <div class="form-group">
                <label>Upload Product Photo <span class="text-muted small">(JPG / PNG / GIF / WEBP, max 5 MB)</span></label>
                <input type="file" name="photo_file" class="form-control-file" accept="image/jpeg,image/png,image/gif,image/webp">
                <small class="form-text text-muted">Choose a photo from your computer. If you upload a file, it will be used instead of the path above.</small>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="5" class="form-control" required><?php echo h($product['description']); ?></textarea>
            </div>
            <button name="save" class="btn btn-primary">Save Product</button>
            <a href="manage_products.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
        <div class="alert alert-light border mt-4 mb-0 small">
            Suggested local real-photo paths: img/blog-3.jpg, img/about-3.jpg, img/blog-1.jpg, img/about-1.jpg, img/blog-2.jpg, img/feature.jpg
        </div>
    </div>
</div>
<?php page_footer(); ?>
