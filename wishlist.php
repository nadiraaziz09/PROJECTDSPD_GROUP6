<?php
include 'layout.php';
require_role(1);
$uid = current_user_id();
if (isset($_GET['remove'])) {
    $wid = (int)$_GET['remove'];
    $stmt = mysqli_prepare($conn, "DELETE FROM wishlist WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $wid, $uid);
    mysqli_stmt_execute($stmt);
    flash('success', 'Pet removed from wishlist.');
    header('Location: wishlist.php'); exit();
}
$stmt = mysqli_prepare($conn, "SELECT w.id AS wishlist_id, p.* FROM wishlist w JOIN pets p ON w.pet_id=p.id WHERE w.user_id=? ORDER BY w.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
page_header('My Wishlist - PawFect Home', 'wishlist'); page_title('My Wishlist', 'Saved pets you are interested in adopting.');
?>
<div class="container py-5"><div class="row">
<?php if (mysqli_num_rows($result) === 0): ?><div class="col-12"><div class="alert alert-info">Your wishlist is empty. <a href="pets.php">Browse pets now</a>.</div></div><?php endif; ?>
<?php while ($pet = mysqli_fetch_assoc($result)): ?>
<div class="col-md-4 mb-4"><div class="card-clean pet-card hover-lift h-100"><img src="<?php echo h($pet['photo']); ?>"><div class="p-4"><h4><?php echo h($pet['name']); ?></h4><p class="text-muted"><?php echo h($pet['type']); ?> · <?php echo h($pet['breed']); ?></p><a href="pet_details.php?id=<?php echo $pet['id']; ?>" class="btn btn-primary btn-sm">Details</a> <a href="wishlist.php?remove=<?php echo $pet['wishlist_id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this pet?')">Remove</a></div></div></div>
<?php endwhile; ?>
</div></div>
<?php page_footer(); ?>
