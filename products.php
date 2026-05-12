<?php
include 'layout.php';
$category = trim($_GET['category'] ?? '');
$where = $category ? "WHERE category='" . mysqli_real_escape_string($conn,$category) . "'" : '';
$result = mysqli_query($conn, "SELECT * FROM products $where ORDER BY category, name");
$cats = mysqli_query($conn, "SELECT DISTINCT category FROM products ORDER BY category");
page_header('Pet Needs - PawFect Home', 'products'); page_title('Pet Needs Store', 'Buy food, care items, toys and accessories for your adopted pet.');
?>
<div class="container py-5">
    <?php if (!empty($_SESSION['email']) && in_array((int)($_SESSION['role'] ?? 0), [2,3], true)): ?>
        <div class="mb-3 text-right"><a href="manage_products.php" class="btn btn-primary"><i class="fas fa-edit mr-2"></i>Manage Pet Needs Products</a></div>
    <?php endif; ?>
    <form class="action-bar mb-4" method="get">
        <div class="form-row align-items-end">
            <div class="col-md-9 mb-2"><label>Category</label><select name="category" class="custom-select"><option value="">All Pet Needs</option><?php while($c=mysqli_fetch_assoc($cats)): ?><option value="<?php echo h($c['category']); ?>" <?php echo $category===$c['category']?'selected':''; ?>><?php echo h($c['category']); ?></option><?php endwhile; ?></select></div>
            <div class="col-md-3 mb-2"><button class="btn btn-primary btn-block">Filter Products</button></div>
        </div>
    </form>
    <div class="row">
    <?php if (mysqli_num_rows($result) === 0): ?><div class="col-12"><div class="alert alert-info">No products found.</div></div><?php endif; ?>
    <?php while($p=mysqli_fetch_assoc($result)): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card-clean product-card hover-lift h-100">
                <img src="<?php echo h($p['photo']); ?>" alt="<?php echo h($p['name']); ?>">
                <div class="p-4">
                    <span class="badge badge-light mb-2"><?php echo h($p['category']); ?></span>
                    <h5><?php echo h($p['name']); ?></h5>
                    <p class="text-muted small"><?php echo h($p['description']); ?></p>
                    <h4 class="text-primary">RM <?php echo number_format($p['price'],2); ?></h4>
                    <p class="small">Stock: <?php echo (int)$p['stock']; ?></p>
                    <?php if (!empty($_SESSION['email']) && (int)($_SESSION['role'] ?? 0) === 1 && (int)$p['stock'] > 0): ?>
                        <a href="payment.php?product_id=<?php echo (int)$p['id']; ?>" class="btn btn-primary btn-block">Buy with Online Banking</a>
                    <?php elseif ((int)$p['stock'] <= 0): ?>
                        <button class="btn btn-secondary btn-block" disabled>Out of Stock</button>
                    <?php elseif (!empty($_SESSION['email']) && in_array((int)($_SESSION['role'] ?? 0), [2,3], true)): ?>
                        <a href="product_form.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-outline-primary btn-block">Edit Product</a>
                    <?php else: ?>
                        <a href="signin.php" class="btn btn-primary btn-block">Sign In to Buy</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
    </div>
    <div class="alert alert-info">Pets are adoption-only and do not have prices. Payment is only used for Pet Needs products such as food, toys and care items.</div>
</div>
<?php page_footer(); ?>
