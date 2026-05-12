<?php
include 'layout.php';
require_role([2,3]);
$pet_id = (int)($_GET['pet_id'] ?? $_POST['pet_id'] ?? 0);
$pet = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pets WHERE id=$pet_id"));
if (!$pet) { flash('error','Pet not found.'); header('Location: manage_pets.php'); exit(); }
$record = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pet_health_records WHERE pet_id=$pet_id ORDER BY updated_at DESC LIMIT 1"));
if (isset($_POST['save_health'])) {
    $vacc = trim($_POST['vaccination']); $medical=trim($_POST['medical_history']); $health=trim($_POST['health_status']); $uid=current_user_id();
    $stmt=mysqli_prepare($conn,"INSERT INTO pet_health_records (pet_id,vaccination,medical_history,health_status,updated_by) VALUES (?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt,'isssi',$pet_id,$vacc,$medical,$health,$uid); mysqli_stmt_execute($stmt);
    $stmt=mysqli_prepare($conn,"UPDATE pets SET health_status=? WHERE id=?"); mysqli_stmt_bind_param($stmt,'si',$health,$pet_id); mysqli_stmt_execute($stmt);
    flash('success','Health record updated.'); header('Location: manage_pets.php'); exit();
}
page_header('Pet Health Records - PawFect Home', 'pets'); page_title('Update Health Record', 'Enter vaccination, medical history and current health status for ' . $pet['name'] . '.');
?>
<div class="container py-5"><div class="row"><div class="col-md-4 mb-4"><div class="card-clean pet-card"><img src="<?php echo h($pet['photo']); ?>"><div class="p-4"><h4><?php echo h($pet['name']); ?></h4><p><?php echo h($pet['breed']); ?></p></div></div></div><div class="col-md-8"><div class="card-clean p-4"><form method="post"><input type="hidden" name="pet_id" value="<?php echo $pet_id; ?>"><div class="form-group"><label>Vaccination History</label><textarea name="vaccination" class="form-control" rows="4" required><?php echo h($record['vaccination'] ?? 'Vaccination record pending update.'); ?></textarea></div><div class="form-group"><label>Medical History</label><textarea name="medical_history" class="form-control" rows="4" required><?php echo h($record['medical_history'] ?? 'No serious medical history reported.'); ?></textarea></div><div class="form-group"><label>Health Status</label><input name="health_status" class="form-control" value="<?php echo h($record['health_status'] ?? $pet['health_status']); ?>" required></div><button name="save_health" class="btn btn-primary">Save Health Record</button></form></div></div></div></div>
<?php page_footer(); ?>
