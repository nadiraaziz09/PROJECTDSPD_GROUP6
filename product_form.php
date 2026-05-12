<?php
include 'layout.php';
require_role([2,3]);

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
    $photo = trim($_POST['photo'] ?? '');

    if ($name === '' || $category === '' || $description === '' || $price <= 0 || $photo === '') {
        flash('error', 'Please complete all fields. Price must be more than RM 0.');
        header('Location: product_form.php' . ($id ? '?id=' . $id : ''));
        exit();
    }

    if ($id) {
        $stmt = mysqli_prepare($conn, "UPDATE products SET name=?, category=?, description=?, price=?, stock=?, photo=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssdisi', $name, $category, $description, $price, $stock, $photo, $id);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO products (name, category, description, price, stock, photo) VALUES (?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'sssdis', $name, $category, $description, $price, $stock, $photo);
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
        <form method="post">
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
                    <label>Photo Path</label>
                    <input name="photo" class="form-control" value="<?php echo h($product['photo']); ?>" placeholder="Example: img/blog-3.jpg" required>
                </div>
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
