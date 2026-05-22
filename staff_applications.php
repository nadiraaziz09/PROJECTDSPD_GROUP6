<?php
include 'layout.php';
require_role([2,3]);
if (isset($_POST['decision'])) {
    $id = (int)$_POST['application_id'];
    $status = $_POST['decision'];
    $note = trim($_POST['staff_note'] ?? '');
    $stmt = mysqli_prepare($conn, "UPDATE adoption_applications SET status=?, staff_note=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssi', $status, $note, $id);
    mysqli_stmt_execute($stmt);
    if ($status === 'approved') {
        $app = mysqli_fetch_assoc(mysqli_query($conn, "SELECT pet_id FROM adoption_applications WHERE id=$id"));
        if ($app) {
            $petId = (int)$app['pet_id'];
            mysqli_query($conn, "UPDATE pets SET status='adopted' WHERE id=$petId");
        }
    }
    flash('success', 'Application status updated.');
    header('Location: staff_applications.php'); exit();
}
$result = mysqli_query($conn, "SELECT a.*, p.name pet_name, p.photo, acc.Name customer_name, acc.Email customer_email FROM adoption_applications a JOIN pets p ON a.pet_id=p.id JOIN account acc ON a.user_id=acc.ID ORDER BY FIELD(a.status,'pending','approved','rejected'), a.created_at DESC");
page_header('Adoption Application Management - PawFect Home', 'applications'); page_title('Adoption Application Management', 'Review pending applications and approve or reject requests.');
?>
<div class="container py-5">
<div class="table-responsive card-clean"><table class="table mb-0"><thead><tr><th>Pet</th><th>Applicant</th><th>Reason</th><th>Status</th><th>Decision</th></tr></thead><tbody>
<?php while($a=mysqli_fetch_assoc($result)): ?><tr><td><img src="<?php echo h($a['photo']); ?>" style="width:70px;height:50px;object-fit:cover;border-radius:8px" class="mr-2"><?php echo h($a['pet_name']); ?></td><td><?php echo h($a['applicant_name']); ?><br><small><?php echo h($a['customer_email']); ?></small></td><td style="max-width:280px"><?php echo h($a['reason']); ?></td><td><?php echo status_badge($a['status']); ?></td><td><form method="post"><input type="hidden" name="application_id" value="<?php echo $a['id']; ?>"><textarea name="staff_note" class="form-control mb-2" rows="2" placeholder="Staff note"><?php echo h($a['staff_note']); ?></textarea><button name="decision" value="approved" class="btn btn-sm btn-success" onclick="return confirm('Approve this application?')">Approve</button> <button name="decision" value="rejected" class="btn btn-sm btn-danger" onclick="return confirm('Reject this application?')">Reject</button></form></td></tr><?php endwhile; ?>
</tbody></table></div></div>
<?php page_footer(); ?>
