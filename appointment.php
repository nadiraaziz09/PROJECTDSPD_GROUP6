<?php
include 'layout.php';
require_login();
$role = (int)($_SESSION['role'] ?? 0);
$uid = current_user_id();

if (isset($_POST['book']) && $role === 1) {
    $pet_id = ($_POST['pet_id'] ?? '') !== '' ? (int)$_POST['pet_id'] : null;
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';
    $note = trim($_POST['note'] ?? '');
    if ($date < date('Y-m-d')) {
        flash('error', 'Appointment date cannot be in the past.');
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO appointments (user_id, pet_id, appointment_date, appointment_time, note) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iisss', $uid, $pet_id, $date, $time, $note);
        mysqli_stmt_execute($stmt);
        flash('success', 'Appointment booked successfully.');
    }
    header('Location: appointment.php'); exit();
}

if (isset($_POST['update_customer']) && $role === 1) {
    $id = (int)$_POST['appointment_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    if ($date < date('Y-m-d')) flash('error', 'Appointment date cannot be in the past.');
    else {
        $stmt = mysqli_prepare($conn, "UPDATE appointments SET appointment_date=?, appointment_time=?, status='rescheduled' WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'ssii', $date, $time, $id, $uid);
        mysqli_stmt_execute($stmt);
        flash('success', 'Appointment updated successfully.');
    }
    header('Location: appointment.php'); exit();
}

if (isset($_GET['cancel']) && $role === 1) {
    $id = (int)$_GET['cancel'];
    $stmt = mysqli_prepare($conn, "UPDATE appointments SET status='cancelled' WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
    mysqli_stmt_execute($stmt);
    flash('success', 'Appointment cancelled.');
    header('Location: appointment.php'); exit();
}

if (isset($_POST['staff_update']) && in_array($role, [2,3], true)) {
    $id = (int)$_POST['appointment_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $status = $_POST['status'];
    $staff_note = trim($_POST['staff_note'] ?? '');
    $stmt = mysqli_prepare($conn, "UPDATE appointments SET appointment_date=?, appointment_time=?, status=?, staff_note=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssssi', $date, $time, $status, $staff_note, $id);
    mysqli_stmt_execute($stmt);
    flash('success', 'Appointment updated by staff.');
    header('Location: appointment.php'); exit();
}

$pets = mysqli_query($conn, "SELECT id,name FROM pets WHERE status='available' ORDER BY name");
if ($role === 1) {
    $stmt = mysqli_prepare($conn, "SELECT a.*, p.name pet_name FROM appointments a LEFT JOIN pets p ON a.pet_id=p.id WHERE a.user_id=? ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    mysqli_stmt_bind_param($stmt, 'i', $uid); mysqli_stmt_execute($stmt); $appointments = mysqli_stmt_get_result($stmt);
} else {
    $appointments = mysqli_query($conn, "SELECT a.*, p.name pet_name, acc.Name customer_name FROM appointments a LEFT JOIN pets p ON a.pet_id=p.id JOIN account acc ON a.user_id=acc.ID ORDER BY a.appointment_date DESC, a.appointment_time DESC");
}
page_header('Appointments - PawFect Home', 'appointment'); page_title($role === 1 ? 'Shelter Visit Appointment' : 'Appointment Management', $role === 1 ? 'Book, edit or cancel your shelter visit.' : 'View and reschedule customer shelter visits.');
?>
<div class="container py-5">
<?php if ($role === 1): ?>
    <div class="card-clean p-4 mb-5"><h4 class="mb-4">Book New Appointment</h4><form method="post"><div class="form-row"><div class="col-md-4 mb-3"><label>Pet</label><select name="pet_id" class="custom-select"><option value="">General shelter visit</option><?php $pre=(int)($_GET['pet_id']??0); while($p=mysqli_fetch_assoc($pets)): ?><option value="<?php echo $p['id']; ?>" <?php echo $pre===$p['id']?'selected':''; ?>><?php echo h($p['name']); ?></option><?php endwhile; ?></select></div><div class="col-md-3 mb-3"><label>Date</label><input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" class="form-control" required></div><div class="col-md-3 mb-3"><label>Time</label><input type="time" name="appointment_time" class="form-control" required></div><div class="col-md-2 mb-3 d-flex align-items-end"><button name="book" class="btn btn-primary btn-block">Book</button></div></div><textarea name="note" class="form-control" rows="3" placeholder="Optional note"></textarea></form></div>
<?php endif; ?>
<div class="table-responsive card-clean"><table class="table mb-0"><thead><tr><th>Date</th><th>Time</th><?php if($role!==1): ?><th>Customer</th><?php endif; ?><th>Pet</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php while($a=mysqli_fetch_assoc($appointments)): ?>
<tr><form method="post"><td><input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo h($a['appointment_date']); ?>" class="form-control" <?php echo $a['status']==='cancelled'?'disabled':''; ?>></td><td><input type="time" name="appointment_time" value="<?php echo h($a['appointment_time']); ?>" class="form-control" <?php echo $a['status']==='cancelled'?'disabled':''; ?>></td><?php if($role!==1): ?><td><?php echo h($a['customer_name']); ?></td><?php endif; ?><td><?php echo h($a['pet_name'] ?: 'General Visit'); ?></td><td><?php echo status_badge($a['status']); ?></td><td><input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>"><?php if($role===1): ?><button name="update_customer" class="btn btn-sm btn-primary" <?php echo $a['status']==='cancelled'?'disabled':''; ?>>Save</button> <a href="appointment.php?cancel=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this appointment?')">Cancel</a><?php else: ?><select name="status" class="custom-select custom-select-sm mb-2"><option <?php echo $a['status']==='booked'?'selected':''; ?>>booked</option><option <?php echo $a['status']==='rescheduled'?'selected':''; ?>>rescheduled</option><option <?php echo $a['status']==='completed'?'selected':''; ?>>completed</option><option <?php echo $a['status']==='cancelled'?'selected':''; ?>>cancelled</option></select><input name="staff_note" class="form-control form-control-sm mb-2" placeholder="Staff note" value="<?php echo h($a['staff_note'] ?? ''); ?>"><button name="staff_update" class="btn btn-sm btn-primary">Update</button><?php endif; ?></td></form></tr>
<?php endwhile; ?>
</tbody></table></div></div>
<?php page_footer(); ?>
