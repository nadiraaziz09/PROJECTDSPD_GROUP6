<?php
include 'layout.php';
include_once 'adoption_discussion_helpers.php';
require_login();
ensure_adoption_discussions_table();
$role = (int)($_SESSION['role'] ?? 0);
$uid = current_user_id();

function pawfect_ensure_adoption_visit_column($conn) {
    $exists = false;
    $res = mysqli_query($conn, "SHOW COLUMNS FROM appointments LIKE 'adoption_application_id'");
    if ($res && mysqli_num_rows($res) > 0) $exists = true;
    if (!$exists) {
        mysqli_query($conn, "ALTER TABLE appointments ADD COLUMN adoption_application_id INT NULL DEFAULT NULL AFTER pet_id");
    }
}

function pawfect_repair_approved_adoption_status($conn) {
    // Fix old records where approval immediately changed the pet to adopted.
    // Correct flow: approved = reserved; completed visit = adopted.
    mysqli_query($conn, "UPDATE pets p
        JOIN adoption_applications a ON a.pet_id = p.id AND a.status = 'approved'
        SET p.status = 'reserved'
        WHERE p.status IN ('available','adopted')
        AND NOT EXISTS (
            SELECT 1 FROM appointments ap
            WHERE ap.pet_id = p.id AND LOWER(ap.status) = 'completed'
        )");

    // Repair appointments booked through the old broken dropdown.
    // Old behaviour could create a general appointment with pet_id NULL, so the pet name disappeared.
    // Only auto-link safe cases: the customer has exactly one approved application.
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

function pawfect_complete_adoption_from_appointment($conn, $appointmentId) {
    $appointmentId = (int)$appointmentId;
    $appt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM appointments WHERE id=$appointmentId LIMIT 1"));
    if (!$appt || strtolower((string)$appt['status']) !== 'completed') return;
    $petId = (int)($appt['pet_id'] ?? 0);
    if ($petId <= 0) return;

    $applicationId = (int)($appt['adoption_application_id'] ?? 0);
    if ($applicationId <= 0) {
        $userId = (int)($appt['user_id'] ?? 0);
        $app = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM adoption_applications WHERE user_id=$userId AND pet_id=$petId AND status='approved' ORDER BY updated_at DESC, created_at DESC LIMIT 1"));
        $applicationId = (int)($app['id'] ?? 0);
        if ($applicationId > 0) {
            mysqli_query($conn, "UPDATE appointments SET adoption_application_id=$applicationId WHERE id=$appointmentId");
        }
    }

    if ($applicationId > 0) {
        mysqli_query($conn, "UPDATE adoption_applications SET status='completed', staff_note=IF(staff_note IS NULL OR staff_note='', 'Adoption completed after visit.', staff_note) WHERE id=$applicationId");
        mysqli_query($conn, "UPDATE pets SET status='adopted' WHERE id=$petId");
    }
}

pawfect_ensure_adoption_visit_column($conn);
pawfect_repair_approved_adoption_status($conn);

function appointment_type_options() {
    return [
        'general' => 'General shelter visit',
        'pickup' => 'Pick up pet needs order',
        'adoption' => 'Adoption discussion',
        'other' => 'Other appointment'
    ];
}

function normalise_appointment_type($type) {
    $type = strtolower(trim((string)$type));
    $allowed = array_keys(appointment_type_options());
    $allowed[] = 'pet_viewing';
    return in_array($type, $allowed, true) ? $type : 'general';
}

function appointment_type_label($type) {
    $type = strtolower(trim((string)$type));
    if ($type === 'pet_viewing') return 'Meet specific pet';
    if (in_array($type, ['care', 'donation'], true)) return 'Other appointment';
    $type = normalise_appointment_type($type);
    $options = appointment_type_options();
    return $options[$type] ?? 'General shelter visit';
}

function appointment_display_purpose($appointment) {
    if (!empty($appointment['pet_name'])) {
        return 'Meet ' . $appointment['pet_name'];
    }
    return appointment_type_label($appointment['appointment_type'] ?? 'general');
}

function give_pet_detail_line($label, $value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    return '<div><b>' . h($label) . ':</b> ' . nl2br(h($value)) . '</div>';
}

function valid_appointment_time($time) {
    $time = trim((string)$time);
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $time, $m)) return false;
    $minutes = ((int)$m[1] * 60) + (int)$m[2];
    return $minutes >= (8 * 60) && $minutes <= (21 * 60);
}

function appointment_time_error_message() {
    return 'Appointment time must be between 8:00 AM and 9:00 PM only.';
}

if (isset($_POST['book']) && $role === 1) {
    $choice = $_POST['appointment_choice'] ?? 'type:general';
    $pet_id = null;
    $appointment_type = 'general';
    $adoption_application_id = null;

    if (strpos($choice, 'application:') === 0) {
        $adoption_application_id = (int)substr($choice, 12);
        $appStmt = mysqli_prepare($conn, "SELECT a.*, p.name pet_name FROM adoption_applications a JOIN pets p ON a.pet_id=p.id WHERE a.id=? AND a.user_id=? AND a.status='approved' LIMIT 1");
        mysqli_stmt_bind_param($appStmt, 'ii', $adoption_application_id, $uid);
        mysqli_stmt_execute($appStmt);
        $approvedApp = mysqli_fetch_assoc(mysqli_stmt_get_result($appStmt));
        if (!$approvedApp) {
            flash('error', 'Approved adoption application not found.');
            header('Location: applications.php'); exit();
        }
        $pet_id = (int)$approvedApp['pet_id'];
        $appointment_type = 'pet_viewing';
        pawfect_reserve_pet_for_approved_application($conn, $pet_id);
    } elseif (strpos($choice, 'pet:') === 0) {
        $pet_id = (int)substr($choice, 4);
        $appointment_type = 'pet_viewing';
    } elseif (strpos($choice, 'type:') === 0) {
        $appointment_type = normalise_appointment_type(substr($choice, 5));
    } elseif (($_POST['pet_id'] ?? '') !== '') {
        $pet_id = (int)$_POST['pet_id'];
        $appointment_type = 'pet_viewing';
    }

    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($date === '' || $time === '') {
        flash('error', 'Please choose appointment date and time.');
        header('Location: appointment.php'); exit();
    }
    if ($date < date('Y-m-d')) {
        flash('error', 'Appointment date cannot be in the past.');
        header('Location: appointment.php'); exit();
    }
    if (!valid_appointment_time($time)) {
        flash('error', appointment_time_error_message());
        header('Location: appointment.php'); exit();
    }

    if ($appointment_type === 'adoption') {
        [$photoOk, $photoPath, $photoError] = save_adoption_discussion_photo('pet_photo');
        if (!$photoOk) {
            flash('error', $photoError ?: 'Pet picture upload failed.');
            header('Location: appointment.php?type=adoption'); exit();
        }

        $user = current_user();
        $applicantName = trim($user['Name'] ?? 'Customer');
        $contact = trim($user['Phone'] ?? '');
        if ($contact === '') {
            $contact = trim($user['Email'] ?? '');
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO adoption_discussions (user_id, applicant_name, contact, pet_photo, appointment_date, appointment_time, note) VALUES (?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'issssss', $uid, $applicantName, $contact, $photoPath, $date, $time, $note);
        mysqli_stmt_execute($stmt);
        flash('success', 'Adoption discussion submitted to Adoption Applications for staff/admin review.');
        header('Location: applications.php'); exit();
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO appointments (user_id, pet_id, appointment_type, appointment_date, appointment_time, note) VALUES (?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'iissss', $uid, $pet_id, $appointment_type, $date, $time, $note);
    mysqli_stmt_execute($stmt);
    $newAppointmentId = mysqli_insert_id($conn);
    if (!empty($adoption_application_id) && $newAppointmentId > 0) {
        $appId = (int)$adoption_application_id;
        mysqli_query($conn, "UPDATE appointments SET adoption_application_id=$appId WHERE id=$newAppointmentId");
    }
    flash('success', 'Appointment booked successfully.');
    header('Location: appointment.php'); exit();
}

if (isset($_POST['update_customer']) && $role === 1) {
    $id = (int)$_POST['appointment_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    if ($date < date('Y-m-d')) flash('error', 'Appointment date cannot be in the past.');
    elseif (!valid_appointment_time($time)) flash('error', appointment_time_error_message());
    else {
        $stmt = mysqli_prepare($conn, "UPDATE appointments SET appointment_date=?, appointment_time=?, status='rescheduled' WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'ssii', $date, $time, $id, $uid);
        mysqli_stmt_execute($stmt);
        flash('success', 'Appointment updated successfully.');
    }
    header('Location: appointment.php'); exit();
}

if (isset($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    if ($role === 1) {
        $stmt = mysqli_prepare($conn, "UPDATE appointments SET status='cancelled' WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE appointments SET status='cancelled' WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
    }
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
    if ($date < date('Y-m-d')) flash('error', 'Appointment date cannot be in the past.');
    elseif (!valid_appointment_time($time)) flash('error', appointment_time_error_message());
    else {
        $stmt = mysqli_prepare($conn, "UPDATE appointments SET appointment_date=?, appointment_time=?, status=?, staff_note=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssssi', $date, $time, $status, $staff_note, $id);
        mysqli_stmt_execute($stmt);
        if ($status === 'completed') {
            pawfect_complete_adoption_from_appointment($conn, $id);
        }
        flash('success', 'Appointment updated by staff.');
    }
    header('Location: appointment.php'); exit();
}

/* ── ensure give_pet_requests table ─────────────────────────────────── */
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `give_pet_requests` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT          NOT NULL,
    `applicant_name`   VARCHAR(100) NOT NULL,
    `contact`          VARCHAR(120) NOT NULL,
    `pet_name`         VARCHAR(100) NOT NULL,
    `pet_type`         VARCHAR(80)  NOT NULL,
    `pet_breed`        VARCHAR(100) NOT NULL DEFAULT '',
    `pet_age`          VARCHAR(50)  NOT NULL DEFAULT '',
    `reason`           TEXT         DEFAULT NULL,
    `pet_photo`        VARCHAR(255) DEFAULT NULL,
    `appointment_date` DATE         NOT NULL,
    `appointment_time` TIME         NOT NULL,
    `note`             TEXT         DEFAULT NULL,
    `status`           VARCHAR(30)  NOT NULL DEFAULT 'pending',
    `staff_note`       TEXT         DEFAULT NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `user_id` (`user_id`),
    CONSTRAINT `give_pet_requests_user_fk`
        FOREIGN KEY (`user_id`) REFERENCES `account` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

/* ── cancel give-pet request (customer or staff/admin) ───────────────── */
if (isset($_GET['gp_cancel']) && ($role === 1 || in_array($role, [2,3], true))) {
    $gp_id = (int)$_GET['gp_cancel'];
    if ($role === 1) {
        $stmt = mysqli_prepare($conn, "UPDATE give_pet_requests SET status='cancelled' WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'ii', $gp_id, $uid);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE give_pet_requests SET status='cancelled' WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $gp_id);
    }
    mysqli_stmt_execute($stmt);
    flash('success', 'Give-pet request cancelled.');
    header('Location: appointment.php'); exit();
}

/* ── customer: save/reschedule give-pet request ──────────────────────── */
if (isset($_POST['gp_update_customer']) && $role === 1) {
    $gp_id = (int)$_POST['gp_id'];
    $date  = $_POST['appointment_date'] ?? '';
    $time  = $_POST['appointment_time'] ?? '';
    if ($date < date('Y-m-d')) flash('error', 'Appointment date cannot be in the past.');
    elseif (!valid_appointment_time($time)) flash('error', appointment_time_error_message());
    else {
        $stmt = mysqli_prepare($conn, "UPDATE give_pet_requests SET appointment_date=?, appointment_time=?, status='pending' WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'ssii', $date, $time, $gp_id, $uid);
        mysqli_stmt_execute($stmt);
        flash('success', 'Give-pet request updated.');
    }
    header('Location: appointment.php'); exit();
}

/* ── staff/admin: update give-pet request status ────────────────────── */
if (isset($_POST['gp_staff_update']) && in_array($role, [2,3], true)) {
    $gp_id     = (int)$_POST['gp_id'];
    $gp_status = in_array($_POST['gp_status'], ['pending','approved','rejected','completed'], true) ? $_POST['gp_status'] : 'pending';
    $gp_note   = trim($_POST['gp_staff_note'] ?? '');
    $gp_date   = $_POST['appointment_date'] ?? '';
    $gp_time   = $_POST['appointment_time'] ?? '';
    if ($gp_date !== '' && $gp_time !== '' && !valid_appointment_time($gp_time)) {
        flash('error', appointment_time_error_message());
        header('Location: appointment.php'); exit();
    }

    /* ── Auto-add pet to listing when status set to "completed" ──────── */
    if ($gp_status === 'completed') {
        // Fetch the request to get pet details
        $gpRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM give_pet_requests WHERE id = " . $gp_id));
        if ($gpRow) {
            // Only insert if not already added (check pet_added_to_listing flag or by pet name+source)
            $alreadyAdded = false;
            $chkRes = mysqli_query($conn, "SHOW COLUMNS FROM pets LIKE 'give_pet_request_id'");
            if (mysqli_num_rows($chkRes) === 0) {
                // Add tracking column if it doesn't exist
                mysqli_query($conn, "ALTER TABLE pets ADD COLUMN give_pet_request_id INT DEFAULT NULL");
            }
            $chk = mysqli_prepare($conn, "SELECT id FROM pets WHERE give_pet_request_id = ?");
            mysqli_stmt_bind_param($chk, 'i', $gp_id);
            mysqli_stmt_execute($chk);
            $chkResult = mysqli_stmt_get_result($chk);
            $alreadyAdded = mysqli_num_rows($chkResult) > 0;

            if (!$alreadyAdded) {
                // Build safe age number from string like "2 years", "6 months"
                $rawAge = $gpRow['pet_age'] ?? '';
                $numAge = 0;
                if (preg_match('/(\d+(\.\d+)?)/', $rawAge, $am)) {
                    $numAge = (float)$am[1];
                    if (stripos($rawAge, 'month') !== false) $numAge = round($numAge / 12, 1);
                }

                // Map pet_type to valid types (Dog, Cat, Rabbit, Bird, Fish, Other)
                $typeMap = ['dog'=>'Dog','cat'=>'Cat','rabbit'=>'Rabbit','bird'=>'Bird','other'=>'Other'];
                $petType = $typeMap[strtolower(trim($gpRow['pet_type']))] ?? trim($gpRow['pet_type']);

                $petName   = $gpRow['pet_name'];
                $petBreed  = $gpRow['pet_breed'] ?: $petType;
                $petPhoto  = $gpRow['pet_photo'] ?: 'img/about-1.jpg';
                $petDesc   = 'Surrendered pet. ' . ($gpRow['reason'] ? 'Owner reason: ' . $gpRow['reason'] . '. ' : '') . ($gpRow['note'] ? $gpRow['note'] : '');
                $petGender = 'Male'; // default, staff can edit later
                $petHealth = 'Healthy';
                $petStatus = 'available';
                $gpIdRef   = $gp_id;

                $ins = mysqli_prepare($conn, "INSERT INTO pets (name, type, breed, age, gender, health_status, description, photo, status, give_pet_request_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($ins, 'sssdsssssi', $petName, $petType, $petBreed, $numAge, $petGender, $petHealth, $petDesc, $petPhoto, $petStatus, $gpIdRef);
                mysqli_stmt_execute($ins);
                $newPetId = mysqli_insert_id($conn);
                flash('success', 'Give-pet request marked as completed and "' . htmlspecialchars($petName) . '" has been automatically added to the pet listing.');
            } else {
                flash('success', 'Give-pet request updated. (Pet was already added to the listing.)');
            }
        }
    }

    if ($gp_status !== 'completed') {
        flash('success', 'Give-pet request updated.');
    }

    $stmt = mysqli_prepare($conn, "UPDATE give_pet_requests SET status=?, staff_note=?, appointment_date=?, appointment_time=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssssi', $gp_status, $gp_note, $gp_date, $gp_time, $gp_id);
    mysqli_stmt_execute($stmt);
    header('Location: appointment.php'); exit();
}

/* ── staff/admin: manually add give-pet request pet to listing ───────── */
if (isset($_POST['gp_add_to_listing']) && in_array($role, [2,3], true)) {
    $gp_id = (int)$_POST['gp_id'];
    $gpRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM give_pet_requests WHERE id = " . $gp_id));
    if ($gpRow) {
        $chkRes = mysqli_query($conn, "SHOW COLUMNS FROM pets LIKE 'give_pet_request_id'");
        if (mysqli_num_rows($chkRes) === 0) {
            mysqli_query($conn, "ALTER TABLE pets ADD COLUMN give_pet_request_id INT DEFAULT NULL");
        }
        $chk = mysqli_prepare($conn, "SELECT id, status FROM pets WHERE give_pet_request_id = ?");
        mysqli_stmt_bind_param($chk, 'i', $gp_id);
        mysqli_stmt_execute($chk);
        $chkResult = mysqli_stmt_get_result($chk);
        $existingPet = mysqli_fetch_assoc($chkResult);
        if ($existingPet && $existingPet['status'] !== 'inactive') {
            flash('info', 'This pet has already been added to the pet listing.');
        } elseif ($existingPet && $existingPet['status'] === 'inactive') {
            // Reactivate instead of inserting a duplicate
            mysqli_query($conn, "UPDATE pets SET status='available' WHERE id = " . (int)$existingPet['id']);
            flash('success', '"' . htmlspecialchars($gpRow['pet_name']) . '" has been re-added to the pet listing.');
        } else {
            $rawAge = $gpRow['pet_age'] ?? '';
            $numAge = 0;
            if (preg_match('/(\d+(\.\d+)?)/', $rawAge, $am)) {
                $numAge = (float)$am[1];
                if (stripos($rawAge, 'month') !== false) $numAge = round($numAge / 12, 1);
            }
            $typeMap = ['dog'=>'Dog','cat'=>'Cat','rabbit'=>'Rabbit','bird'=>'Bird','other'=>'Other'];
            $petType   = $typeMap[strtolower(trim($gpRow['pet_type']))] ?? trim($gpRow['pet_type']);
            $petName   = $gpRow['pet_name'];
            $petBreed  = $gpRow['pet_breed'] ?: $petType;
            $petPhoto  = $gpRow['pet_photo'] ?: 'img/about-1.jpg';
            $petDesc   = 'Surrendered pet. ' . ($gpRow['reason'] ? 'Owner reason: ' . $gpRow['reason'] . '. ' : '') . ($gpRow['note'] ? $gpRow['note'] : '');
            $petGender = 'Male';
            $petHealth = 'Healthy';
            $petStatus = 'available';
            $ins = mysqli_prepare($conn, "INSERT INTO pets (name, type, breed, age, gender, health_status, description, photo, status, give_pet_request_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($ins, 'sssdsssssi', $petName, $petType, $petBreed, $numAge, $petGender, $petHealth, $petDesc, $petPhoto, $petStatus, $gp_id);
            mysqli_stmt_execute($ins);
            flash('success', '"' . htmlspecialchars($petName) . '" has been added to the pet listing. You can edit the details in Pet Management.');
        }
    } else {
        flash('error', 'Give-pet request not found.');
    }
    header('Location: appointment.php'); exit();
}

/* ── staff/admin: remove give-pet pet from listing ───────────────────── */
if (isset($_POST['gp_remove_from_listing']) && in_array($role, [2,3], true)) {
    $gp_id = (int)$_POST['gp_id'];
    $chkCol = mysqli_query($conn, "SHOW COLUMNS FROM pets LIKE 'give_pet_request_id'");
    if (mysqli_num_rows($chkCol) > 0) {
        $petRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, name FROM pets WHERE give_pet_request_id = " . $gp_id));
        if ($petRow) {
            $petId = (int)$petRow['id'];
            mysqli_query($conn, "UPDATE pets SET status='inactive' WHERE id = $petId");
            flash('success', '"' . htmlspecialchars($petRow['name']) . '" has been removed from the public pet listing.');
        } else {
            flash('info', 'Pet was not found in the listing.');
        }
    } else {
        flash('info', 'Pet was not found in the listing.');
    }
    header('Location: appointment.php'); exit();
}

$approved_visit_apps = null;
$available_pets = mysqli_query($conn, "SELECT id,name FROM pets WHERE status='available' ORDER BY name");
if ($role === 1) {
    $approvedStmt = mysqli_prepare($conn, "SELECT a.id application_id, a.pet_id, p.name, p.status pet_status
        FROM adoption_applications a
        JOIN pets p ON a.pet_id = p.id
        WHERE a.user_id=? AND a.status='approved'
        ORDER BY a.updated_at DESC, a.created_at DESC");
    mysqli_stmt_bind_param($approvedStmt, 'i', $uid);
    mysqli_stmt_execute($approvedStmt);
    $approved_visit_apps = mysqli_stmt_get_result($approvedStmt);
}
if ($role === 1) {
    $stmt = mysqli_prepare($conn, "SELECT a.*, p.name pet_name FROM appointments a LEFT JOIN pets p ON a.pet_id=p.id WHERE a.user_id=? AND COALESCE(a.appointment_type,'general') <> 'adoption' ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    mysqli_stmt_bind_param($stmt, 'i', $uid); mysqli_stmt_execute($stmt); $appointments = mysqli_stmt_get_result($stmt);
} else {
    $appointments = mysqli_query($conn, "SELECT a.*, p.name pet_name, acc.Name customer_name FROM appointments a LEFT JOIN pets p ON a.pet_id=p.id JOIN account acc ON a.user_id=acc.ID WHERE COALESCE(a.appointment_type,'general') <> 'adoption' ORDER BY a.appointment_date DESC, a.appointment_time DESC");
}

/* ── give-pet requests ───────────────────────────────────────────────── */
/* Ensure give_pet_request_id column exists before joining on it */
$gpColExists = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM pets LIKE 'give_pet_request_id'")) > 0;
if (!$gpColExists) {
    mysqli_query($conn, "ALTER TABLE pets ADD COLUMN give_pet_request_id INT DEFAULT NULL");
}

if ($role === 1) {
    $stmt = mysqli_prepare($conn, "SELECT g.* FROM give_pet_requests g LEFT JOIN pets p ON p.give_pet_request_id = g.id AND p.status != 'inactive' WHERE g.user_id=? AND p.id IS NULL ORDER BY g.created_at DESC");
    mysqli_stmt_bind_param($stmt, 'i', $uid); mysqli_stmt_execute($stmt);
    $give_pet_rows = mysqli_stmt_get_result($stmt);
} else {
    $give_pet_rows = mysqli_query($conn, "SELECT g.*, acc.Name customer_name FROM give_pet_requests g JOIN account acc ON g.user_id=acc.ID LEFT JOIN pets p ON p.give_pet_request_id = g.id AND p.status != 'inactive' WHERE p.id IS NULL ORDER BY g.created_at DESC");
}

/* ── build a unified combined list (appointments + give-pet) for display */
$all_rows = [];

// Appointments
while ($a = mysqli_fetch_assoc($appointments)) {
    $a['_row_type'] = 'appointment';
    $all_rows[] = $a;
}

// Give-pet requests
while ($g = mysqli_fetch_assoc($give_pet_rows)) {
    $g['_row_type'] = 'give_pet';
    $all_rows[] = $g;
}

// Sort combined list by date DESC, time DESC
usort($all_rows, function($a, $b) {
    $da = ($a['appointment_date'] ?? '0000-00-00') . ' ' . ($a['appointment_time'] ?? '00:00:00');
    $db = ($b['appointment_date'] ?? '0000-00-00') . ' ' . ($b['appointment_time'] ?? '00:00:00');
    return strcmp($db, $da);
});

$preType = normalise_appointment_type($_GET['type'] ?? 'general');
$prePet = (int)($_GET['pet_id'] ?? 0);
$preApplication = (int)($_GET['application_id'] ?? 0);
page_header('Appointments - PawFect Home', 'appointment'); page_title($role === 1 ? 'Appointment Booking' : 'Appointment Management', $role === 1 ? 'Book, edit or cancel shelter visits and pet needs pick-up appointments.' : 'View and reschedule customer appointments. Adoption discussion requests are shown in Adoption Applications.');
?>
<style>
/* ── Pet Details Side Panel ─────────────────────────────────────────── */
.gp-detail-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.35);
    z-index: 1040;
    backdrop-filter: blur(2px);
}
.gp-detail-overlay.open { display: block; }
.gp-detail-panel {
    position: fixed;
    top: 0; right: -420px;
    width: 400px;
    max-width: 95vw;
    height: 100vh;
    background: #fff;
    z-index: 1050;
    box-shadow: -8px 0 40px rgba(0,0,0,.18);
    border-radius: 20px 0 0 20px;
    display: flex;
    flex-direction: column;
    transition: right .3s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
}
.gp-detail-panel.open { right: 0; }
.gp-detail-header {
    padding: 22px 24px 16px;
    border-bottom: 1px solid rgba(0,0,0,.08);
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--paw-soft, #fff5f2);
    flex-shrink: 0;
}
.gp-detail-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}
.gp-detail-photo {
    width: 100%;
    border-radius: 14px;
    object-fit: cover;
    max-height: 220px;
    margin-bottom: 20px;
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
}
.gp-detail-photo-placeholder {
    width: 100%;
    height: 140px;
    border-radius: 14px;
    background: var(--paw-soft, #fff5f2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    color: #ccc;
    font-size: 2.5rem;
}
.gp-meta-row {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
    align-items: flex-start;
}
.gp-meta-label {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--paw-primary, #af2708);
    min-width: 80px;
    padding-top: 2px;
}
.gp-meta-value {
    font-size: .9rem;
    color: #333;
    flex: 1;
}
.gp-close-btn {
    margin-left: auto;
    background: none;
    border: none;
    cursor: pointer;
    color: #888;
    font-size: 1.2rem;
    padding: 4px 8px;
    border-radius: 8px;
    transition: background .15s;
}
.gp-close-btn:hover { background: rgba(0,0,0,.07); color: #333; }
.gp-row-link { cursor: pointer; }
.gp-row-link:hover { background: var(--paw-soft, #fff5f2) !important; }

/* Give-pet row indicator */
.give-pet-tag {
    display: inline-block;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--paw-primary, #af2708);
    background: var(--paw-soft, #fff5f2);
    border: 1px solid rgba(175,39,8,.18);
    border-radius: 6px;
    padding: 2px 7px;
    margin-left: 6px;
    vertical-align: middle;
}

.gp-inline-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 330px;
    max-width: 560px;
}
.gp-inline-photo,
.gp-inline-photo-empty {
    width: 96px;
    height: 78px;
    border-radius: 12px;
    flex: 0 0 96px;
    object-fit: cover;
    border: 1px solid rgba(175,39,8,.16);
    box-shadow: 0 4px 14px rgba(0,0,0,.10);
    background: #fff;
}
.gp-inline-photo-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c7c7c7;
    background: var(--paw-soft, #fff5f2);
    font-size: 1.35rem;
}
.gp-inline-name {
    font-weight: 800;
    color: #222;
    margin-top: 3px;
}
.gp-inline-meta {
    font-size: .78rem;
    color: #6c757d;
    line-height: 1.35;
    margin-top: 5px;
}
.gp-inline-meta b { color: #343a40; }
.gp-photo-link:hover .gp-inline-photo { opacity: .9; }
@media (max-width: 767.98px) {
    .gp-inline-card { min-width: 260px; }
    .gp-inline-photo, .gp-inline-photo-empty { width: 78px; height: 64px; flex-basis: 78px; }
}
</style>

<!-- Pet Details Side Panel -->
<div class="gp-detail-overlay" id="gpOverlay" onclick="closeGpPanel()"></div>
<div class="gp-detail-panel" id="gpPanel">
    <div class="gp-detail-header">
        <div style="width:38px;height:38px;border-radius:11px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(175,39,8,.15);flex-shrink:0;">
            <i class="fas fa-paw" style="color:var(--paw-primary);font-size:1rem;"></i>
        </div>
        <div>
            <div id="gpPanelPetName" style="font-weight:800;font-size:1rem;"></div>
            <div id="gpPanelPetType" style="font-size:.8rem;color:#888;"></div>
        </div>
        <button class="gp-close-btn" onclick="closeGpPanel()" title="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="gp-detail-body">
        <div id="gpPanelPhoto"></div>
        <div id="gpPanelMeta"></div>
    </div>
</div>

<div class="container py-5">
<?php if ($role === 1): ?>

    <!-- Give a Pet Banner (above booking form) -->
    <div class="card-clean p-4 mb-4" style="border-left:4px solid var(--paw-primary); background:var(--paw-soft);">
        <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:12px;">
            <div class="d-flex align-items-center" style="gap:14px;">
                <div style="width:42px;height:42px;border-radius:12px;background:#fff;flex-shrink:0;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(175,39,8,.13);">
                    <i class="fas fa-paw text-primary" style="font-size:1.1rem;"></i>
                </div>
                <div>
                    <h6 class="mb-0" style="font-weight:800;">Want to give your pet to our shelter?</h6>
                    <p class="mb-0 text-muted" style="font-size:.85rem;">We accept pet surrenders &mdash; book a drop-off appointment and our team will review it.</p>
                </div>
            </div>
            <a href="give_pet.php" class="btn btn-primary" style="white-space:nowrap;font-weight:700;">
                <i class="fas fa-hand-holding-heart mr-2"></i>Give a Pet
            </a>
        </div>
    </div>

    <div class="card-clean p-4 mb-5">
        <h4 class="mb-4">Book New Appointment</h4>
        <form method="post" enctype="multipart/form-data">
            <div class="form-row">
                <div class="col-md-4 mb-3">
                    <label>Appointment Purpose</label>
                    <select name="appointment_choice" id="appointment_choice" class="custom-select" required>
                        <?php foreach (appointment_type_options() as $value => $label): ?>
                            <option value="type:<?php echo h($value); ?>" <?php echo ($prePet === 0 && $preApplication === 0 && $preType === $value) ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                        <?php if ($approved_visit_apps && mysqli_num_rows($approved_visit_apps) > 0): ?>
                        <optgroup label="Approved adoption visits">
                            <?php while($appPet=mysqli_fetch_assoc($approved_visit_apps)): ?>
                                <?php $selectedApp = ($preApplication === (int)$appPet['application_id']) || ($preApplication === 0 && $prePet === (int)$appPet['pet_id']); ?>
                                <option value="application:<?php echo (int)$appPet['application_id']; ?>" <?php echo $selectedApp ? 'selected' : ''; ?>><?php echo h($appPet['name']); ?> — approved adoption visit</option>
                            <?php endwhile; ?>
                        </optgroup>
                        <?php endif; ?>
                        <optgroup label="Meet available pet">
                            <?php while($p=mysqli_fetch_assoc($available_pets)): ?>
                                <option value="pet:<?php echo (int)$p['id']; ?>" <?php echo ($preApplication === 0 && $prePet === (int)$p['id']) ? 'selected' : ''; ?>><?php echo h($p['name']); ?></option>
                            <?php endwhile; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-3 mb-3"><label>Date</label><input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" class="form-control" required></div>
                <div class="col-md-3 mb-3"><label>Time</label><input type="time" name="appointment_time" min="08:00" max="21:00" class="form-control" required><small class="text-muted">Available time: 8:00 AM - 9:00 PM</small></div>
                <div class="col-md-2 mb-3 d-flex align-items-end"><button name="book" class="btn btn-primary btn-block">Book</button></div>
            </div>
            <div id="adoption_picture_box" class="form-group" style="display:none;">
                <label>Pet Picture for Adoption Discussion</label>
                <input type="file" name="pet_photo" class="form-control-file" accept="image/jpeg,image/png,image/gif,image/webp">
                <small class="text-muted">Upload the pet picture here. This request will appear in staff/admin Adoption Applications, not Appointment Management.</small>
            </div>
            <textarea name="note" class="form-control" rows="3" placeholder="Optional note"></textarea>
        </form>
    </div>
    <script>
        (function () {
            var select = document.getElementById('appointment_choice');
            var box = document.getElementById('adoption_picture_box');
            function toggleAdoptionBox() {
                if (!select || !box) return;
                box.style.display = select.value === 'type:adoption' ? 'block' : 'none';
            }
            if (select) {
                select.addEventListener('change', toggleAdoptionBox);
                toggleAdoptionBox();
            }
        })();
    </script>
<?php endif; ?>

<!-- ── Combined Appointments & Give-Pet Requests Table ─────────────── -->
<div class="table-responsive card-clean">
    <table class="table mb-0">
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <?php if ($role !== 1): ?><th>Customer</th><?php endif; ?>
                <th>Pet / Purpose</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($all_rows as $row):
            $isGivePet  = $row['_row_type'] === 'give_pet';
            $isCancelled = ($row['status'] === 'cancelled');
            $rowDate     = h($row['appointment_date']);
            $rowTime     = h(substr($row['appointment_time'], 0, 5));
            /* Purpose label */
            $gpPhotoSrc = '';
            if ($isGivePet && trim((string)($row['pet_photo'] ?? '')) !== '') {
                $gpPhotoSrc = pawfect_image_src($row['pet_photo'], 'img/about-1.jpg');
            }
            if ($isGivePet) {
                $photoBlock = $gpPhotoSrc !== ''
                    ? '<a class="gp-photo-link" href="' . h($gpPhotoSrc) . '" target="_blank" onclick="event.stopPropagation()" title="Open uploaded pet photo"><img src="' . h($gpPhotoSrc) . '" class="gp-inline-photo" alt="Uploaded pet photo"></a>'
                    : '<div class="gp-inline-photo-empty" title="No pet photo uploaded"><i class="fas fa-camera-retro"></i></div>';

                if ($role !== 1) {
                    $detailHtml = give_pet_detail_line('Breed', $row['pet_breed'] ?? '')
                        . give_pet_detail_line('Age', $row['pet_age'] ?? '')
                        . give_pet_detail_line('Reason', $row['reason'] ?? '')
                        . give_pet_detail_line('Owner Note', $row['note'] ?? '');
                    if ($detailHtml === '') {
                        $detailHtml = '<div class="text-muted">No additional pet details provided.</div>';
                    }
                    $purposeHtml = '<div class="gp-inline-card">' . $photoBlock
                        . '<div><span class="give-pet-tag" style="font-size:.78rem;margin-left:0;"><i class="fas fa-hand-holding-heart mr-1"></i>Give Pet</span>'
                        . '<div class="gp-inline-name">' . h($row['pet_name']) . ($row['pet_type'] ? ' &middot; ' . h($row['pet_type']) : '') . '</div>'
                        . '<div class="gp-inline-meta">' . $detailHtml . '</div>'
                        . '<small class="text-muted d-block mt-1"><i class="fas fa-mouse-pointer mr-1"></i>Click row or View Details to see full request</small>'
                        . '</div></div>';
                } else {
                    $purposeHtml = '<div class="gp-inline-card">' . $photoBlock
                        . '<div><span class="give-pet-tag" style="font-size:.78rem;margin-left:0;"><i class="fas fa-hand-holding-heart mr-1"></i>Give Pet</span>'
                        . '<br><small class="text-muted">' . h($row['pet_name']) . ($row['pet_type'] ? ' &middot; ' . h($row['pet_type']) : '') . '</small>'
                        . '</div></div>';
                }
            } else {
                $purposeHtml = h(appointment_display_purpose($row));
            }
            /* Side-panel data for give-pet click */
            $gpData = $isGivePet ? json_encode([
                'pet_name'   => $row['pet_name'],
                'pet_type'   => $row['pet_type'],
                'pet_breed'  => $row['pet_breed'] ?? '',
                'pet_age'    => $row['pet_age'] ?? '',
                'reason'     => $row['reason'] ?? '',
                'note'       => $row['note'] ?? '',
                'contact'    => $row['contact'] ?? '',
                'customer'   => $row['customer_name'] ?? '',
                'date'       => $row['appointment_date'],
                'time'       => substr($row['appointment_time'], 0, 5),
                'status'     => $row['status'],
                'staff_note' => $row['staff_note'] ?? '',
                'photo'      => $gpPhotoSrc,
            ]) : null;
        ?>
        <tr<?php if ($isGivePet) echo ' class="gp-row-link" onclick="openGpPanel(' . htmlspecialchars($gpData, ENT_QUOTES) . ')" title="Click to view full pet details"'; ?>>
            <form method="post">

            <?php /* ── Date (calendar input for everyone) ── */ ?>
            <td>
                <input type="date" name="appointment_date"
                       min="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo $rowDate; ?>"
                       class="form-control"
                       <?php echo $isCancelled ? 'disabled' : ''; ?>
                       <?php if ($isGivePet) echo 'onclick="event.stopPropagation()"'; ?>>
            </td>

            <?php /* ── Time ── */ ?>
            <td>
                <input type="time" name="appointment_time"
                       min="08:00" max="21:00"
                       value="<?php echo $rowTime; ?>"
                       class="form-control"
                       <?php echo $isCancelled ? 'disabled' : ''; ?>
                       <?php if ($isGivePet) echo 'onclick="event.stopPropagation()"'; ?>>
                <small class="text-muted">8 AM – 9 PM</small>
            </td>

            <?php /* ── Customer column (staff/admin only) ── */ ?>
            <?php if ($role !== 1): ?>
            <td>
                <?php if ($isGivePet): ?>
                    <strong><?php echo h($row['customer_name']); ?></strong>
                    <br><small class="text-muted"><?php echo h($row['contact']); ?></small>
                <?php else: ?>
                    <?php echo h($row['customer_name']); ?>
                <?php endif; ?>
            </td>
            <?php endif; ?>

            <?php /* ── Purpose ── */ ?>
            <td><?php echo $purposeHtml; ?></td>

            <?php /* ── Status ── */ ?>
            <td><?php echo status_badge($row['status']); ?></td>

            <?php /* ── Action ── */ ?>
            <td onclick="<?php echo $isGivePet ? 'event.stopPropagation()' : ''; ?>">

                <?php if ($role === 1): /* Customer */ ?>

                    <?php if ($isGivePet): ?>
                        <input type="hidden" name="gp_id" value="<?php echo (int)$row['id']; ?>">
                        <button type="button" class="btn btn-sm btn-outline-primary mb-1" onclick='openGpPanel(<?php echo htmlspecialchars($gpData, ENT_QUOTES); ?>)'>
                            <i class="fas fa-eye mr-1"></i>View Details
                        </button>
                        <button name="gp_update_customer" class="btn btn-sm btn-primary mb-1" <?php echo $isCancelled ? 'disabled' : ''; ?>>
                            <i class="fas fa-save mr-1"></i>Save
                        </button>
                        <a href="appointment.php?gp_cancel=<?php echo (int)$row['id']; ?>"
                           class="btn btn-sm btn-outline-danger mb-1"
                           onclick="return confirm('Cancel this give-pet request?')"
                           <?php echo $isCancelled ? 'style="pointer-events:none;opacity:.5;"' : ''; ?>>
                            <i class="fas fa-times mr-1"></i>Cancel
                        </a>
                    <?php else: ?>
                        <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                        <button name="update_customer" class="btn btn-sm btn-primary mb-1" <?php echo $isCancelled ? 'disabled' : ''; ?>>
                            <i class="fas fa-save mr-1"></i>Save
                        </button>
                        <a href="appointment.php?cancel=<?php echo $row['id']; ?>"
                           class="btn btn-sm btn-outline-danger mb-1"
                           onclick="return confirm('Cancel this appointment?')"
                           <?php echo $isCancelled ? 'style="pointer-events:none;opacity:.5;"' : ''; ?>>
                            <i class="fas fa-times mr-1"></i>Cancel
                        </a>
                    <?php endif; ?>

                <?php else: /* Staff / Admin */ ?>

                    <?php if ($isGivePet): ?>
                        <input type="hidden" name="gp_id" value="<?php echo (int)$row['id']; ?>">
                        <button type="button" class="btn btn-sm btn-outline-primary mb-1" onclick='openGpPanel(<?php echo htmlspecialchars($gpData, ENT_QUOTES); ?>)'>
                            <i class="fas fa-eye mr-1"></i>View Details
                        </button>
                        <select name="gp_status" class="custom-select custom-select-sm mb-2">
                            <?php foreach (['pending','approved','rejected','completed'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $row['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input name="gp_staff_note" class="form-control form-control-sm mb-2" placeholder="Staff note" value="<?php echo h($row['staff_note'] ?? ''); ?>">
                        <button name="gp_staff_update" class="btn btn-sm btn-primary mb-1">
                            <i class="fas fa-save mr-1"></i>Save
                        </button>
                        <a href="appointment.php?gp_cancel=<?php echo (int)$row['id']; ?>"
                           class="btn btn-sm btn-outline-danger mb-1"
                           onclick="return confirm('Cancel this give-pet request?')"
                           <?php echo $isCancelled ? 'style="pointer-events:none;opacity:.5;"' : ''; ?>>
                            <i class="fas fa-times mr-1"></i>Cancel
                        </a>
                        <?php
                            /* Check if pet already added to listing */
                            $chkCol = mysqli_query($conn, "SHOW COLUMNS FROM pets LIKE 'give_pet_request_id'");
                            $colExists = mysqli_num_rows($chkCol) > 0;
                            $petAlreadyAdded = false;
                            if ($colExists) {
                                $chkAdded = mysqli_prepare($conn, "SELECT id FROM pets WHERE give_pet_request_id = ? AND status != 'inactive'");
                                mysqli_stmt_bind_param($chkAdded, 'i', $row['id']);
                                mysqli_stmt_execute($chkAdded);
                                $petAlreadyAdded = mysqli_num_rows(mysqli_stmt_get_result($chkAdded)) > 0;
                            }
                        ?>
                        <?php if ($petAlreadyAdded): ?>
                            <button name="gp_remove_from_listing" class="btn btn-sm btn-outline-warning mb-1"
                                    onclick="event.stopPropagation(); return confirm('Remove <?php echo h(addslashes($row['pet_name'])); ?> from the pet listing?')">
                                <i class="fas fa-minus-circle mr-1"></i>Remove from Listing
                            </button>
                        <?php else: ?>
                            <button name="gp_add_to_listing" class="btn btn-sm btn-success mb-1"
                                    onclick="event.stopPropagation(); return confirm('Add <?php echo h(addslashes($row['pet_name'])); ?> to the pet listing now?')">
                                <i class="fas fa-plus-circle mr-1"></i>Add Pet
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                        <select name="status" class="custom-select custom-select-sm mb-2">
                            <option value="booked" <?php echo $row['status']==='booked'?'selected':''; ?>>Booked</option>
                            <option value="rescheduled" <?php echo $row['status']==='rescheduled'?'selected':''; ?>>Rescheduled</option>
                            <option value="completed" <?php echo $row['status']==='completed'?'selected':''; ?>>Complete</option>
                            <option value="cancelled" <?php echo $row['status']==='cancelled'?'selected':''; ?>>Canceled</option>
                        </select>
                        <input name="staff_note" class="form-control form-control-sm mb-2" placeholder="Staff note" value="<?php echo h($row['staff_note'] ?? ''); ?>">
                        <button name="staff_update" class="btn btn-sm btn-primary mb-1">
                            <i class="fas fa-save mr-1"></i>Save
                        </button>
                        <a href="appointment.php?cancel=<?php echo $row['id']; ?>"
                           class="btn btn-sm btn-outline-danger mb-1"
                           onclick="return confirm('Cancel this appointment?')"
                           <?php echo $isCancelled ? 'style="pointer-events:none;opacity:.5;"' : ''; ?>>
                            <i class="fas fa-times mr-1"></i>Cancel
                        </a>
                    <?php endif; ?>

                <?php endif; ?>
            </td>

            </form>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($all_rows)): ?>
        <tr><td colspan="<?php echo $role === 1 ? 5 : 6; ?>" class="text-center text-muted py-4">No appointments or requests found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
function openGpPanel(data) {
    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/\n/g, '<br>');
    }

    document.getElementById('gpPanelPetName').textContent = data.pet_name || '—';
    var typeStr = data.pet_type || '';
    if (data.pet_breed) typeStr += ' · ' + data.pet_breed;
    document.getElementById('gpPanelPetType').textContent = typeStr;

    // Photo uploaded by customer
    var photoEl = document.getElementById('gpPanelPhoto');
    if (data.photo) {
        photoEl.innerHTML = '<a href="' + esc(data.photo) + '" target="_blank"><img src="' + esc(data.photo) + '" class="gp-detail-photo" alt="Pet photo"></a>';
    } else {
        photoEl.innerHTML = '<div class="gp-detail-photo-placeholder"><i class="fas fa-camera-retro"></i></div>';
    }

    // Meta rows
    var rows = [];
    function row(label, val, raw) {
        if (!val) return;
        rows.push('<div class="gp-meta-row"><span class="gp-meta-label">' + esc(label) + '</span><span class="gp-meta-value">' + (raw ? val : esc(val)) + '</span></div>');
    }
    row('Pet Name',  data.pet_name);
    row('Type',      data.pet_type);
    row('Breed',     data.pet_breed);
    row('Age',       data.pet_age);
    if (data.customer) row('Customer', esc(data.customer) + (data.contact ? '<br><small class="text-muted">' + esc(data.contact) + '</small>' : ''), true);
    row('Drop-off',  (data.date || '') + ' at ' + (data.time || ''));
    row('Status',    '<span class="badge badge-pill" style="background:var(--paw-soft);color:var(--paw-primary);font-weight:700;padding:5px 10px;">' + esc(data.status || '') + '</span>', true);
    row('Reason', data.reason);
    row('Owner Note', data.note);
    row('Staff Note', data.staff_note);

    document.getElementById('gpPanelMeta').innerHTML = rows.join('');

    document.getElementById('gpOverlay').classList.add('open');
    document.getElementById('gpPanel').classList.add('open');
}
function closeGpPanel() {
    document.getElementById('gpOverlay').classList.remove('open');
    document.getElementById('gpPanel').classList.remove('open');
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeGpPanel(); });
</script>

</div><?php /* close .container */ ?>
<?php page_footer(); ?>
