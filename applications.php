<?php
include 'layout.php';
require_role(1);
$uid = current_user_id();
$stmt = mysqli_prepare($conn, "SELECT a.*, p.name pet_name, p.photo FROM adoption_applications a JOIN pets p ON a.pet_id=p.id WHERE a.user_id=? ORDER BY a.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
page_header('My Applications - PawFect Home', 'applications'); page_title('My Adoption Applications', 'Track whether your application is pending, approved or rejected.');
?>
<div class="container py-5">
<?php if (mysqli_num_rows($result) === 0): ?><div class="alert alert-info">You have not submitted any adoption application yet. <a href="pets.php">Choose a pet</a>.</div><?php endif; ?>
<div class="table-responsive card-clean p-0"><table class="table mb-0"><thead><tr><th>Pet</th><th>Submitted</th><th>Status</th><th>Staff Note</th><th>Next Step</th></tr></thead><tbody>
<?php while ($a = mysqli_fetch_assoc($result)): ?>
<tr>
    <td><img src="<?php echo h($a['photo']); ?>" style="width:60px;height:45px;object-fit:cover;border-radius:8px" class="mr-2"><?php echo h($a['pet_name']); ?></td>
    <td><?php echo h(date('d M Y', strtotime($a['created_at']))); ?></td>
    <td><?php echo status_badge($a['status']); ?></td>
    <td><?php echo h($a['staff_note'] ?: '-'); ?></td>
    <td><?php if ($a['status']==='approved'): ?><a href="appointment.php?pet_id=<?php echo (int)$a['pet_id']; ?>" class="btn btn-primary btn-sm">Book Visit</a><?php elseif ($a['status']==='pending'): ?><span class="text-muted">Waiting staff review</span><?php else: ?><span class="text-muted">No action</span><?php endif; ?></td>
</tr>
<?php endwhile; ?>
</tbody></table></div></div>
<?php page_footer(); ?>
