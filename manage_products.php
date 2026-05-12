<?php
include 'layout.php';
require_role([2,3]);

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM products WHERE id=$id");
    flash('success', 'Pet needs product deleted successfully.');
    header('Location: manage_products.php');
    exit();
}

$category = trim($_GET['category'] ?? '');
$where = $category ? "WHERE category='" . mysqli_real_escape_string($conn, $category) . "'" : '';
$result = mysqli_query($conn, "SELECT * FROM products $where ORDER BY category, name");
$cats = mysqli_query($conn, "SELECT DISTINCT category FROM products ORDER BY category");

page_header('Manage Pet Needs - PawFect Home', 'manage_products');
page_title('Manage Pet Needs Products', 'Staff can add, edit, delete and update food, toys, care items and accessories.');
?>
<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <a href="product_form.php" class="btn btn-primary mb-2"><i class="fas fa-plus mr-2"></i>Add New Product</a>
        <a href="products.php" class="btn btn-outline-secondary mb-2">View Store Page</a>
    </div>

    <form class="action-bar mb-4" method="get">
        <div class="form-row align-items-end">
            <div class="col-md-9 mb-2">
                <label>Filter by Category</label>
                <select name="category" class="custom-select">
                    <option value="">All Categories</option>
                    <?php while($c = mysqli_fetch_assoc($cats)): ?>
                        <option value="<?php echo h($c['category']); ?>" <?php echo $category === $c['category'] ? 'selected' : ''; ?>><?php echo h($c['category']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3 mb-2"><button class="btn btn-primary btn-block">Filter</button></div>
        </div>
    </form>

    <div class="table-responsive card-clean">
        <table class="table mb-0">
            <thead>
                <tr><th>Photo</th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Description</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) === 0): ?>
                <tr><td colspan="7" class="text-center text-muted p-4">No products found.</td></tr>
            <?php endif; ?>
            <?php while($p = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><img src="<?php echo h($p['photo']); ?>" style="width:75px;height:55px;object-fit:cover;border-radius:8px" alt="<?php echo h($p['name']); ?>"></td>
                    <td><strong><?php echo h($p['name']); ?></strong></td>
                    <td><?php echo h($p['category']); ?></td>
                    <td>RM <?php echo number_format($p['price'], 2); ?></td>
                    <td><?php echo (int)$p['stock']; ?></td>
                    <td class="small text-muted" style="max-width:260px"><?php echo h($p['description']); ?></td>
                    <td>
                        <a href="product_form.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="manage_products.php?delete=<?php echo (int)$p['id']; ?>" onclick="return confirm('Delete this product?')" class="btn btn-sm btn-outline-danger">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_footer(); ?>
