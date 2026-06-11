<?php
include 'layout.php';
include_once 'adoption_discussion_helpers.php';
require_role(1);
ensure_adoption_discussions_table();
$uid = current_user_id();
$stmt = mysqli_prepare($conn, "SELECT a.*, p.name pet_name, p.photo, p.status pet_status FROM adoption_applications a JOIN pets p ON a.pet_id=p.id WHERE a.user_id=? ORDER BY a.created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$stmt2 = mysqli_prepare($conn, "SELECT * FROM adoption_discussions WHERE user_id=? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt2, 'i', $uid);
mysqli_stmt_execute($stmt2);
$discussions = mysqli_stmt_get_result($stmt2);

page_header('My Applications - PawFect Home', 'applications'); page_title('My Adoption Applications', 'Track adoption applications and adoption discussion requests.');
?>
<div class="container py-5">
<?php if (mysqli_num_rows($result) === 0 && mysqli_num_rows($discussions) === 0): ?><div class="alert alert-info">You have not submitted any adoption application yet. <a href="pets.php">Choose a pet</a> or book an adoption discussion from the Appointment page.</div><?php endif; ?>
<div class="table-responsive card-clean p-0"><table class="table mb-0"><thead><tr><th>Pet / Request</th><th>Submitted</th><th>Status</th><th>Staff Note</th><th>Next Step</th></tr></thead><tbody>
<?php while ($a = mysqli_fetch_assoc($result)): ?>
<tr>
    <td><img src="<?php echo h(pawfect_image_src($a['photo'], 'img/about-1.jpg')); ?>" style="width:60px;height:45px;object-fit:cover;border-radius:8px" class="mr-2"><?php echo h($a['pet_name']); ?><br><small class="text-muted">Adoption application</small></td>
    <td><?php echo h(date('d M Y', strtotime($a['created_at']))); ?></td>
    <td><?php echo status_badge($a['status']); ?></td>
    <td><?php echo h($a['staff_note'] ?: '-'); ?></td>
    <td><?php if ($a['status']==='approved'): ?>
        <a href="appointment.php?application_id=<?php echo (int)$a['id']; ?>" class="btn btn-primary btn-sm">Book Visit</a>
        <br><small class="text-muted">Pet reserved until your visit is completed.</small>
    <?php elseif ($a['status']==='completed'): ?>
        <span class="text-success">Adoption completed</span>
    <?php elseif ($a['status']==='pending'): ?>
        <span class="text-muted">Waiting staff review</span>
    <?php else: ?>
        <span class="text-muted">No action</span>
    <?php endif; ?></td>
</tr>
<?php endwhile; ?>
<?php while ($d = mysqli_fetch_assoc($discussions)): ?>
<tr>
    <td>
        <?php if (!empty($d['pet_photo'])): ?>
            <a href="<?php echo h(pawfect_image_src($d['pet_photo'], 'img/about-1.jpg')); ?>" target="_blank"><img src="<?php echo h(pawfect_image_src($d['pet_photo'], 'img/about-1.jpg')); ?>" style="width:60px;height:45px;object-fit:cover;border-radius:8px" class="mr-2"></a>
        <?php else: ?>
            <span class="d-inline-block bg-light text-muted text-center mr-2" style="width:60px;height:45px;line-height:45px;border-radius:8px">No pic</span>
        <?php endif; ?>
        Adoption discussion<br><small class="text-muted"><?php echo h(format_discussion_datetime($d['appointment_date'], $d['appointment_time'])); ?></small>
    </td>
    <td><?php echo h(date('d M Y', strtotime($d['created_at']))); ?></td>
    <td><?php echo status_badge($d['status']); ?></td>
    <td><?php echo h($d['staff_note'] ?: '-'); ?></td>
    <td><?php if ($d['status']==='approved'): ?><span class="text-muted">Approved for discussion</span><?php elseif ($d['status']==='pending'): ?><span class="text-muted">Waiting staff review</span><?php else: ?><span class="text-muted">No action</span><?php endif; ?></td>
</tr>
<?php endwhile; ?>
</tbody></table></div></div>
<?php page_footer(); ?>
