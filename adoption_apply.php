<?php
include 'layout.php';
require_role(1);
$uid = current_user_id();
$user = current_user();
$pet_id = (int)($_GET['pet_id'] ?? $_POST['pet_id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM pets WHERE id=? AND status='available' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $pet_id);
mysqli_stmt_execute($stmt);
$pet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$pet) { flash('error', 'Pet is not available for adoption.'); header('Location: pets.php'); exit(); }
if (isset($_POST['submit_application'])) {
    $name = trim($_POST['applicant_name']);
    $contact = trim($_POST['contact']);
    $reason = trim($_POST['reason']);
    if ($name && $contact && $reason) {
        $stmt = mysqli_prepare($conn, "INSERT INTO adoption_applications (user_id, pet_id, applicant_name, contact, reason) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iisss', $uid, $pet_id, $name, $contact, $reason);
        mysqli_stmt_execute($stmt);
        flash('success', 'Application submitted successfully. You can track the status from My Applications.');
        header('Location: applications.php'); exit();
    } else flash('error', 'Please complete all fields.');
}
page_header('Adoption Application - PawFect Home', 'applications'); page_title('Adoption Application', 'Submit your request to adopt ' . $pet['name'] . '.');
?>
<div class="container py-5"><div class="row">
    <div class="col-lg-5 mb-4"><div class="card-clean pet-card"><img src="<?php echo h($pet['photo']); ?>"><div class="p-4"><h4><?php echo h($pet['name']); ?></h4><p><?php echo h($pet['breed']); ?> · <?php echo h($pet['age']); ?> years · Adoption only</p><p class="text-muted mb-0"><?php echo h($pet['description']); ?></p></div></div></div>
    <div class="col-lg-7"><div class="card-clean p-4"><h4 class="mb-4">Applicant Details</h4><form method="post"><input type="hidden" name="pet_id" value="<?php echo $pet_id; ?>"><div class="form-group"><label>Name</label><input name="applicant_name" class="form-control" value="<?php echo h($user['Name']); ?>" required></div><div class="form-group"><label>Contact Number</label><input name="contact" class="form-control" value="<?php echo h($user['Phone'] ?? ''); ?>" required></div><div class="form-group"><label>Reason for Adoption</label><textarea name="reason" class="form-control" rows="6" required placeholder="Explain why you want to adopt this pet and how you will care for it."></textarea></div><button name="submit_application" class="btn btn-primary">Submit Application</button></form></div></div>
</div></div>
<?php page_footer(); ?>
