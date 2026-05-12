<?php
include 'layout.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM pets WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$pet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$pet) { flash('error', 'Pet not found.'); header('Location: pets.php'); exit(); }
if (isset($_POST['wishlist'])) {
    require_role(1);
    $uid = current_user_id();
    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO wishlist (user_id, pet_id) VALUES (?,?)");
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $id);
    mysqli_stmt_execute($stmt);
    flash('success', $pet['name'] . ' has been saved to your wishlist.');
    header('Location: pet_details.php?id=' . $id); exit();
}
$health = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pet_health_records WHERE pet_id=$id ORDER BY updated_at DESC LIMIT 1"));
page_header($pet['name'] . ' - Pet Details', 'pets'); page_title($pet['name'], 'View pet details before submitting your adoption application.');
?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-6 mb-4"><img src="<?php echo h($pet['photo']); ?>" class="img-fluid rounded shadow" alt="<?php echo h($pet['name']); ?>"></div>
        <div class="col-lg-6">
            <div class="card-clean p-4">
                <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="mb-0"><?php echo h($pet['name']); ?></h2><?php echo status_badge($pet['status']); ?></div>
                <table class="table table-borderless">
                    <tr><th>Type</th><td><?php echo h($pet['type']); ?></td></tr>
                    <tr><th>Breed</th><td><?php echo h($pet['breed']); ?></td></tr>
                    <tr><th>Age</th><td><?php echo h($pet['age']); ?> years</td></tr>
                    <tr><th>Gender</th><td><?php echo h($pet['gender']); ?></td></tr>
                    <tr><th>Health Status</th><td><?php echo h($pet['health_status']); ?></td></tr>
                </table>
                <p><?php echo h($pet['description']); ?></p>
                <?php if ($pet['status'] === 'available'): ?>
                <div class="d-flex flex-wrap">
                    <a href="adoption_apply.php?pet_id=<?php echo $id; ?>" class="btn btn-primary mr-2 mb-2">Adopt Now</a>
                    <form method="post" class="mb-2"><button name="wishlist" class="btn btn-outline-primary"><i class="fas fa-heart mr-2"></i>Save to Wishlist</button></form>
                    <a href="appointment.php?pet_id=<?php echo $id; ?>" class="btn btn-outline-secondary mb-2 ml-md-2">Book Visit</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-clean p-4 mt-4">
                <h4>Health Records</h4>
                <?php if ($health): ?>
                    <p><strong>Vaccination:</strong> <?php echo nl2br(h($health['vaccination'])); ?></p>
                    <p><strong>Medical History:</strong> <?php echo nl2br(h($health['medical_history'])); ?></p>
                    <p class="mb-0"><strong>Latest Health Status:</strong> <?php echo h($health['health_status']); ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">No detailed health records have been added yet. Current status: <?php echo h($pet['health_status']); ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>
