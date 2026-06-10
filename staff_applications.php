<?php
include 'layout.php';
include_once 'adoption_discussion_helpers.php';
require_role([2,3]);
ensure_adoption_discussions_table();

function pawfect_repair_approved_adoption_status($conn) {
    // Old behaviour marked a pet as adopted immediately after approval.
    // In the corrected flow, approved = reserved, and adopted only happens after the visit is completed.
    mysqli_query($conn, "UPDATE pets p
        JOIN adoption_applications a ON a.pet_id = p.id AND a.status = 'approved'
        SET p.status = 'reserved'
        WHERE p.status IN ('available','adopted')
        AND NOT EXISTS (
            SELECT 1 FROM appointments ap
            WHERE ap.pet_id = p.id AND LOWER(ap.status) = 'completed'
        )");

    // Repair old booked appointments with missing pet_id when safe.
    mysqli_query($conn, "UPDATE appointments ap
        JOIN (
            SELECT user_id, MAX(id) AS application_id, MAX(pet_id) AS pet_id, COUNT(*) AS approved_count
            FROM adoption_applications
            WHERE status='approved'
            GROUP BY user_id
            HAVING approved_count = 1
        ) aa ON aa.user_id = ap.user_id
        SET ap.pet_id = aa.pet_id,
            ap.appointment_type = 'pet_viewing',
            ap.adoption_application_id = aa.application_id
        WHERE ap.pet_id IS NULL
          AND LOWER(ap.status) IN ('booked','pending','approved','rescheduled')
          AND COALESCE(ap.appointment_type,'general') IN ('general','pet_viewing')");
}

function pawfect_reserve_pet_for_approved_application($conn, $petId) {
    $petId = (int)$petId;
    if ($petId <= 0) return;
    $done = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE pet_id=$petId AND LOWER(status)='completed'"));
    if ((int)($done['total'] ?? 0) === 0) {
        mysqli_query($conn, "UPDATE pets SET status='reserved' WHERE id=$petId");
    }
}

pawfect_repair_approved_adoption_status($conn);

if (isset($_POST['decision'])) {
    $id = (int)$_POST['application_id'];
    $status = in_array($_POST['decision'] ?? '', ['approved','rejected'], true) ? $_POST['decision'] : 'pending';
    $note = trim($_POST['staff_note'] ?? '');

    $app = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM adoption_applications WHERE id=$id LIMIT 1"));
    if ($app) {
        $petId = (int)$app['pet_id'];
        $stmt = mysqli_prepare($conn, "UPDATE adoption_applications SET status=?, staff_note=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssi', $status, $note, $id);
        mysqli_stmt_execute($stmt);

        if ($status === 'approved') {
            // Approval should reserve the pet only. Do NOT mark as adopted yet.
            pawfect_reserve_pet_for_approved_application($conn, $petId);
            // Avoid double-adoption confusion by closing other pending applications for the same pet.
            mysqli_query($conn, "UPDATE adoption_applications
                SET status='rejected', staff_note='Another application for this pet has been approved.'
                WHERE pet_id=$petId AND id<>$id AND status='pending'");
        } elseif ($status === 'rejected') {
            // If no approved/completed application remains, release the pet back to available.
            $stillReserved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM adoption_applications WHERE pet_id=$petId AND status IN ('approved','completed')"));
            if ((int)($stillReserved['total'] ?? 0) === 0) {
                mysqli_query($conn, "UPDATE pets SET status='available' WHERE id=$petId AND status='reserved'");
            }
        }
    }

    flash('success', 'Application status updated.');
    header('Location: staff_applications.php'); exit();
}

if (isset($_POST['discussion_decision'])) {
    $id = (int)$_POST['discussion_id'];
    $status = $_POST['discussion_decision'];
    $note = trim($_POST['staff_note'] ?? '');
    $stmt = mysqli_prepare($conn, "UPDATE adoption_discussions SET status=?, staff_note=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssi', $status, $note, $id);
    mysqli_stmt_execute($stmt);
    flash('success', 'Adoption discussion status updated.');
    header('Location: staff_applications.php'); exit();
}

$applications = mysqli_query($conn, "SELECT a.*, p.name pet_name, p.photo, acc.Name customer_name, acc.Email customer_email FROM adoption_applications a JOIN pets p ON a.pet_id=p.id JOIN account acc ON a.user_id=acc.ID ORDER BY FIELD(a.status,'pending','approved','rejected'), a.created_at DESC");
$discussions = mysqli_query($conn, "SELECT d.*, acc.Name customer_name, acc.Email customer_email FROM adoption_discussions d JOIN account acc ON d.user_id=acc.ID ORDER BY FIELD(d.status,'pending','approved','rejected'), d.created_at DESC");
page_header('Adoption Application Management - PawFect Home', 'applications'); page_title('Adoption Application Management', 'Review adoption applications and adoption discussion requests.');
?>
<div class="container py-5">
<div class="table-responsive card-clean"><table class="table mb-0"><thead><tr><th>Pet / Request</th><th>Applicant</th><th>Reason / Note</th><th>Status</th><th>Decision</th></tr></thead><tbody>
<?php while($a=mysqli_fetch_assoc($applications)): ?>
<tr>
    <td><img src="<?php echo h(pawfect_image_src($a['photo'], 'img/about-1.jpg')); ?>" style="width:70px;height:50px;object-fit:cover;border-radius:8px" class="mr-2"><?php echo h($a['pet_name']); ?><br><small class="text-muted">Adoption application</small></td>
    <td><?php echo h($a['applicant_name']); ?><br><small><?php echo h($a['customer_email']); ?></small></td>
    <td style="max-width:280px"><?php echo h($a['reason']); ?></td>
    <td><?php echo status_badge($a['status']); ?></td>
    <td><form method="post"><input type="hidden" name="application_id" value="<?php echo $a['id']; ?>"><textarea name="staff_note" class="form-control mb-2" rows="2" placeholder="Staff note"><?php echo h($a['staff_note']); ?></textarea><button name="decision" value="approved" class="btn btn-sm btn-success" onclick="return confirm('Approve this application?')">Approve</button> <button name="decision" value="rejected" class="btn btn-sm btn-danger" onclick="return confirm('Reject this application?')">Reject</button></form></td>
</tr>
<?php endwhile; ?>
<?php while($d=mysqli_fetch_assoc($discussions)): ?>
<tr>
    <td>
        <?php if (!empty($d['pet_photo'])): ?>
            <a href="<?php echo h(pawfect_image_src($d['pet_photo'], 'img/about-1.jpg')); ?>" target="_blank"><img src="<?php echo h(pawfect_image_src($d['pet_photo'], 'img/about-1.jpg')); ?>" style="width:70px;height:50px;object-fit:cover;border-radius:8px" class="mr-2"></a>
        <?php else: ?>
            <span class="d-inline-block bg-light text-muted text-center mr-2" style="width:70px;height:50px;line-height:50px;border-radius:8px">No pic</span>
        <?php endif; ?>
        Adoption discussion<br>
        <small class="text-muted"><?php echo h(format_discussion_datetime($d['appointment_date'], $d['appointment_time'])); ?></small>
    </td>
    <td><?php echo h($d['applicant_name']); ?><br><small><?php echo h($d['customer_email']); ?></small></td>
    <td style="max-width:280px"><?php echo h($d['note'] ?: 'No note provided.'); ?></td>
    <td><?php echo status_badge($d['status']); ?></td>
    <td><form method="post"><input type="hidden" name="discussion_id" value="<?php echo $d['id']; ?>"><textarea name="staff_note" class="form-control mb-2" rows="2" placeholder="Staff note"><?php echo h($d['staff_note']); ?></textarea><button name="discussion_decision" value="approved" class="btn btn-sm btn-success" onclick="return confirm('Approve this discussion request?')">Approve</button> <button name="discussion_decision" value="rejected" class="btn btn-sm btn-danger" onclick="return confirm('Reject this discussion request?')">Reject</button></form></td>
</tr>
<?php endwhile; ?>
</tbody></table></div></div>
<?php page_footer(); ?>
