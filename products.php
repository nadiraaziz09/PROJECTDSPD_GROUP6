<?php
include 'layout.php';

function product_photo_src($photo, $name = '') {
    $photo = trim((string)$photo);
    $defaultImages = [
        'Premium Dog Food Pack' => 'img/premium-dog-food-pack.jpg',
        'Cat Food & Treats Set' => 'img/cat-food.jpg',
        'Pet Grooming Kit' => 'img/pet-grooming-kit.jpg',
        'Comfort Pet Bed' => 'img/comfort-pet-bed.jpg',
        'Toy Bundle' => 'img/toy-bundle.jpg',
        'Feeding Bowl Set' => 'img/feeding-bowl-set.jpg'
    ];
    $oldSeedImages = ['img/blog-3.jpg', 'img/about-3.jpg', 'img/blog-1.jpg', 'img/about-1.jpg', 'img/blog-2.jpg', 'img/feature.jpg'];

    if (isset($defaultImages[$name]) && ($photo === '' || in_array($photo, $oldSeedImages, true))) {
        return $defaultImages[$name];
    }
    if ($photo !== '' && preg_match('/^https?:\/\//i', $photo)) {
        return $photo;
    }
    return pawfect_image_src($photo, $defaultImages[$name] ?? 'img/feature.jpg');
}

function product_cart_count() {
    return array_sum(array_map('intval', $_SESSION['pet_needs_cart'] ?? []));
}

function redirect_products($category = '', $page = 1) {
    $url = 'products.php';
    $query = [];
    if ($category !== '') {
        $query['category'] = $category;
    }
    if ($page > 1) {
        $query['page'] = $page;
    }
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    header('Location: ' . $url);
    exit();
}

if (!isset($_SESSION['pet_needs_cart']) || !is_array($_SESSION['pet_needs_cart'])) {
    $_SESSION['pet_needs_cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action'])) {
    $action = $_POST['cart_action'];
    $returnCategory = trim($_POST['return_category'] ?? '');
    $returnPage = max(1, (int)($_POST['return_page'] ?? 1));
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'clear') {
        $_SESSION['pet_needs_cart'] = [];
        flash('success', 'Cart cleared.');
        redirect_products($returnCategory, $returnPage);
    }

    $stmt = mysqli_prepare($conn, "SELECT id, name, stock FROM products WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $productResult = mysqli_stmt_get_result($stmt);
    $productForCart = mysqli_fetch_assoc($productResult);

    if (!$productForCart) {
        flash('error', 'Product not found.');
        redirect_products($returnCategory, $returnPage);
    }

    if ($action === 'remove') {
        unset($_SESSION['pet_needs_cart'][$productId]);
        flash('success', 'Product removed from cart.');
        redirect_products($returnCategory, $returnPage);
    }

    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $stock = (int)$productForCart['stock'];
    if ($stock <= 0) {
        flash('error', 'This product is out of stock.');
        redirect_products($returnCategory, $returnPage);
    }
    $qty = min($qty, $stock);

    if ($action === 'add') {
        $currentQty = (int)($_SESSION['pet_needs_cart'][$productId] ?? 0);
        $_SESSION['pet_needs_cart'][$productId] = min($stock, $currentQty + $qty);
        flash('success', $productForCart['name'] . ' added to cart.');
        redirect_products($returnCategory, $returnPage);
    }

    if ($action === 'update') {
        $_SESSION['pet_needs_cart'][$productId] = $qty;
        flash('success', 'Cart updated.');
        redirect_products($returnCategory, $returnPage);
    }
}

$category = trim($_GET['category'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = 6;
$where = $category ? "WHERE category='" . mysqli_real_escape_string($conn,$category) . "'" : '';
$countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products $where");
$totalProducts = (int)(mysqli_fetch_assoc($countResult)['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalProducts / $itemsPerPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $itemsPerPage;
$result = mysqli_query($conn, "SELECT * FROM products $where ORDER BY category, name LIMIT $itemsPerPage OFFSET $offset");
$cats = mysqli_query($conn, "SELECT DISTINCT category FROM products ORDER BY category");
$paginationFilters = [];
if ($category !== '') $paginationFilters['category'] = $category;

$cartItems = [];
$cartTotal = 0;
$cartIds = array_keys($_SESSION['pet_needs_cart']);
if (!empty($cartIds)) {
    $safeIds = array_map('intval', $cartIds);
    $cartResult = mysqli_query($conn, "SELECT * FROM products WHERE id IN (" . implode(',', $safeIds) . ") ORDER BY name");
    while ($cartProduct = mysqli_fetch_assoc($cartResult)) {
        $cartQty = min((int)($_SESSION['pet_needs_cart'][(int)$cartProduct['id']] ?? 1), max(1, (int)$cartProduct['stock']));
        $_SESSION['pet_needs_cart'][(int)$cartProduct['id']] = $cartQty;
        $cartProduct['cart_qty'] = $cartQty;
        $cartProduct['cart_subtotal'] = (float)$cartProduct['price'] * $cartQty;
        $cartTotal += $cartProduct['cart_subtotal'];
        $cartItems[] = $cartProduct;
    }
}

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
                <img src="<?php echo h(product_photo_src($p['photo'], $p['name'])); ?>" alt="<?php echo h($p['name']); ?>">
                <div class="p-4">
                    <span class="badge badge-light mb-2"><?php echo h($p['category']); ?></span>
                    <h5><?php echo h($p['name']); ?></h5>
                    <p class="text-muted small"><?php echo h($p['description']); ?></p>
                    <h4 class="text-primary">RM <?php echo number_format($p['price'],2); ?></h4>
                    <p class="small">Stock: <?php echo (int)$p['stock']; ?></p>
                    <?php if (!empty($_SESSION['email']) && (int)($_SESSION['role'] ?? 0) === 1 && (int)$p['stock'] > 0): ?>
                        <a href="payment.php?product_id=<?php echo (int)$p['id']; ?>" class="btn btn-primary btn-block">Buy Now</a>
                        <form method="post" class="mt-2">
                            <input type="hidden" name="cart_action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <input type="hidden" name="return_category" value="<?php echo h($category); ?>">
                            <input type="hidden" name="return_page" value="<?php echo (int)$page; ?>">
                            <button class="btn btn-outline-primary btn-block"><i class="fas fa-cart-plus mr-2"></i>Add to Cart</button>
                        </form>
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
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Products page navigation" class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo h(http_build_query(array_merge($paginationFilters, ['page' => max(1, $page - 1)]))); ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo h(http_build_query(array_merge($paginationFilters, ['page' => $i]))); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo h(http_build_query(array_merge($paginationFilters, ['page' => min($totalPages, $page + 1)]))); ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
    <div class="alert alert-info">Pets are adoption-only and do not have prices. Payment is only used for Pet Needs products such as food, toys and care items.</div>
</div>

<?php if (!empty($_SESSION['email']) && (int)($_SESSION['role'] ?? 0) === 1): ?>
<button type="button" class="floating-cart-btn" id="floatingCartBtn" aria-label="Open pet needs cart">
    <i class="fas fa-shopping-cart"></i>
    <span class="floating-cart-count"><?php echo product_cart_count(); ?></span>
</button>
<div class="floating-cart-panel" id="floatingCartPanel">
    <div class="floating-cart-header">
        <strong><i class="fas fa-shopping-cart mr-2"></i>Pet Needs Cart</strong>
        <button type="button" class="floating-cart-close" id="floatingCartClose" aria-label="Close cart">&times;</button>
    </div>
    <div class="floating-cart-body">
        <?php if (empty($cartItems)): ?>
            <p class="text-muted small mb-0">Your cart is empty.</p>
        <?php else: ?>
            <?php foreach ($cartItems as $item): ?>
                <div class="floating-cart-item">
                    <img src="<?php echo h(product_photo_src($item['photo'], $item['name'])); ?>" alt="<?php echo h($item['name']); ?>">
                    <div class="floating-cart-info">
                        <strong><?php echo h($item['name']); ?></strong>
                        <span>RM <?php echo number_format($item['price'], 2); ?> each</span>
                        <form method="post" class="floating-cart-qty">
                            <input type="hidden" name="cart_action" value="update">
                            <input type="hidden" name="product_id" value="<?php echo (int)$item['id']; ?>">
                            <input type="hidden" name="return_category" value="<?php echo h($category); ?>">
                            <input type="hidden" name="return_page" value="<?php echo (int)$page; ?>">
                            <input type="number" name="quantity" min="1" max="<?php echo (int)$item['stock']; ?>" value="<?php echo (int)$item['cart_qty']; ?>">
                            <button class="btn btn-sm btn-outline-primary">Update</button>
                        </form>
                        <div class="floating-cart-actions">
                            <form method="post">
                                <input type="hidden" name="cart_action" value="remove">
                                <input type="hidden" name="product_id" value="<?php echo (int)$item['id']; ?>">
                                <input type="hidden" name="return_category" value="<?php echo h($category); ?>">
                                <input type="hidden" name="return_page" value="<?php echo (int)$page; ?>">
                                <button class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if (!empty($cartItems)): ?>
        <div class="floating-cart-footer">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Total</strong>
                <strong class="text-primary">RM <?php echo number_format($cartTotal, 2); ?></strong>
            </div>
            <a href="payment.php?cart=1" class="btn btn-primary btn-block mb-2">
                <i class="fas fa-credit-card mr-2"></i>Checkout All Items
            </a>
            <form method="post">
                <input type="hidden" name="cart_action" value="clear">
                <input type="hidden" name="return_category" value="<?php echo h($category); ?>">
                <input type="hidden" name="return_page" value="<?php echo (int)$page; ?>">
                <button class="btn btn-outline-secondary btn-block btn-sm">Clear Cart</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<script>
(function () {
    var btn = document.getElementById('floatingCartBtn');
    var panel = document.getElementById('floatingCartPanel');
    var closeBtn = document.getElementById('floatingCartClose');
    if (!btn || !panel) return;
    btn.addEventListener('click', function () {
        panel.classList.toggle('show');
    });
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            panel.classList.remove('show');
        });
    }
})();
</script>
<?php endif; ?>
<?php page_footer(); ?>
