<?php
include 'layout.php';
require_login();
$role = (int)($_SESSION['role'] ?? 0);
$uid  = current_user_id();

if ($role !== 1) {
    flash('error', 'Only customers can submit a give-pet request.');
    header('Location: appointment.php'); exit();
}

/* ── ensure table ────────────────────────────────────────────────────── */
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

/* ── helpers ─────────────────────────────────────────────────────────── */
function save_give_pet_photo($field = 'pet_photo') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return [true, null, null];
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return [false, null, 'Photo upload failed. Please try again.'];
    if ((int)$_FILES[$field]['size'] > 5 * 1024 * 1024) return [false, null, 'Photo must be 5 MB or smaller.'];
    $imageInfo = @getimagesize($_FILES[$field]['tmp_name']);
    if (!$imageInfo) return [false, null, 'Please upload a valid image (JPG, PNG, GIF or WEBP).'];
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $mime = $imageInfo['mime'] ?? '';
    if (!isset($extensions[$mime])) return [false, null, 'Only JPG, PNG, GIF or WEBP images are allowed.'];
    $dir = __DIR__ . '/uploads/give_pet';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $filename = 'give_pet_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $filename)) return [false, null, 'Could not save the photo. Please check upload folder permissions.'];
    return [true, 'uploads/give_pet/' . $filename, null];
}

function gp_valid_time($time) {
    $time = trim((string)$time);
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $time, $m)) return false;
    $minutes = ((int)$m[1] * 60) + (int)$m[2];
    return $minutes >= (8 * 60) && $minutes <= (21 * 60);
}

/* ── form submission ─────────────────────────────────────────────────── */
if (isset($_POST['submit_give_pet'])) {
    $date      = trim($_POST['appointment_date'] ?? '');
    $time      = trim($_POST['appointment_time'] ?? '');
    $pet_name  = trim($_POST['pet_name']  ?? '');
    $pet_type  = trim($_POST['pet_type']  ?? '');
    $pet_breed = trim($_POST['pet_breed'] ?? '');
    $pet_age   = trim($_POST['pet_age']   ?? '');
    $reason    = trim($_POST['reason']    ?? '');
    $note      = trim($_POST['note']      ?? '');

    $user    = current_user();
    $appName = trim($user['Name'] ?? 'Customer');
    $contact = trim($user['Phone'] ?? '');
    if ($contact === '') $contact = trim($user['Email'] ?? '');

    $error = null;
    if ($pet_name === '')     $error = "Please enter your pet's name.";
    elseif ($pet_type === '') $error = 'Please select the pet type.';
    elseif ($date === '' || $time === '') $error = 'Please choose a drop-off appointment date and time.';
    elseif ($date < date('Y-m-d')) $error = 'Appointment date cannot be in the past.';
    elseif (!gp_valid_time($time)) $error = 'Appointment time must be between 8:00 AM and 9:00 PM.';

    if ($error) { flash('error', $error); header('Location: give_pet.php'); exit(); }

    [$photoOk, $photoPath, $photoError] = save_give_pet_photo('pet_photo');
    if (!$photoOk) { flash('error', $photoError ?: 'Photo upload failed.'); header('Location: give_pet.php'); exit(); }

    $stmt = mysqli_prepare($conn, "INSERT INTO give_pet_requests
        (user_id, applicant_name, contact, pet_name, pet_type, pet_breed, pet_age, reason, pet_photo, appointment_date, appointment_time, note)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'isssssssssss', $uid, $appName, $contact, $pet_name, $pet_type, $pet_breed, $pet_age, $reason, $photoPath, $date, $time, $note);
    mysqli_stmt_execute($stmt);

    flash('success', 'Your request has been submitted. Our team will review it and confirm your drop-off appointment.');
    header('Location: appointment.php'); exit();
}

page_header('Give a Pet - PawFect Home', 'appointment');
page_title('Give Your Pet to Us', 'We care for every pet. Fill in the form below and our team will be in touch to confirm your drop-off.');
?>

<style>
.gp-form-section-label {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--paw-primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 6px;
}
.gp-form-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(175,39,8,.12);
    margin-left: 6px;
}
.gp-divider {
    border: 0;
    border-top: 1px solid rgba(0,0,0,.07);
    margin: 1.75rem 0;
}
.gp-info-box {
    background: var(--paw-soft);
    border-left: 4px solid var(--paw-primary);
    border-radius: 12px;
    padding: 14px 18px;
    font-size: .88rem;
    color: #4b3b36;
}
.gp-label {
    font-size: .82rem;
    font-weight: 700;
    color: #444;
    margin-bottom: .35rem;
    display: block;
}
.gp-optional {
    font-weight: 400;
    color: #999;
    font-size: .78rem;
}
.required-star { color: var(--paw-primary); }
.gp-photo-box {
    border: 2px dashed #ddd;
    border-radius: 12px;
    padding: 18px 16px;
    background: #fafafa;
    transition: border-color .2s;
}
.gp-photo-box:hover { border-color: var(--paw-primary); }
.gp-submit-btn {
    min-height: 50px;
    font-weight: 700;
    font-size: 1rem;
    border-radius: 12px;
    letter-spacing: .02em;
}
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">

            <!-- Back link -->
            <a href="appointment.php" class="d-inline-flex align-items-center text-muted mb-4" style="font-size:.88rem;text-decoration:none;">
                <i class="fas fa-chevron-left mr-2" style="font-size:.7rem;"></i> Back to Appointments
            </a>

            <div class="card-clean">
                <!-- Card header banner -->
                <div style="background:linear-gradient(135deg,var(--paw-primary),#d4390f);padding:28px 32px 24px;">
                    <div class="d-flex align-items-center" style="gap:16px;">
                        <div style="width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-hand-holding-heart" style="color:#fff;font-size:1.4rem;"></i>
                        </div>
                        <div>
                            <h4 class="mb-1 text-white" style="font-weight:800;">Give Your Pet to Our Shelter</h4>
                            <p class="mb-0" style="color:rgba(255,255,255,.8);font-size:.88rem;">We'll review your request and confirm the drop-off appointment.</p>
                        </div>
                    </div>
                </div>

                <!-- Form body -->
                <div class="p-4 p-md-5">
                    <form method="post" enctype="multipart/form-data">

                        <!-- ── Pet Information ── -->
                        <p class="gp-form-section-label"><i class="fas fa-paw"></i> Pet Information</p>

                        <div class="form-row">
                            <div class="col-md-6 mb-3">
                                <label class="gp-label">Pet Name <span class="required-star">*</span></label>
                                <input type="text" name="pet_name" class="form-control" placeholder="e.g. Buddy" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="gp-label">Pet Type <span class="required-star">*</span></label>
                                <select name="pet_type" class="custom-select" required>
                                    <option value="">— select type —</option>
                                    <option>Dog</option>
                                    <option>Cat</option>
                                    <option>Rabbit</option>
                                    <option>Bird</option>
                                    <option>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-md-6 mb-3">
                                <label class="gp-label">Breed <span class="gp-optional">(optional)</span></label>
                                <input type="text" name="pet_breed" class="form-control" placeholder="e.g. Golden Retriever">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="gp-label">Approximate Age <span class="gp-optional">(optional)</span></label>
                                <input type="text" name="pet_age" class="form-control" placeholder="e.g. 2 years, 6 months">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="gp-label">Pet Photo <span class="gp-optional">(optional — JPG / PNG / GIF / WEBP, max 5 MB)</span></label>
                            <div class="gp-photo-box">
                                <input type="file" name="pet_photo" class="form-control-file" accept="image/jpeg,image/png,image/gif,image/webp">
                                <p class="mb-0 mt-2 text-muted" style="font-size:.78rem;"><i class="fas fa-camera mr-1"></i>A clear photo helps our staff prepare for the visit.</p>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="gp-label">Reason for Giving <span class="gp-optional">(optional)</span></label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="e.g. Moving abroad, unable to provide proper care…"></textarea>
                        </div>

                        <hr class="gp-divider">

                        <!-- ── Drop-off Appointment ── -->
                        <p class="gp-form-section-label"><i class="fas fa-calendar-alt"></i> Drop-off Appointment</p>

                        <div class="form-row">
                            <div class="col-md-6 mb-3">
                                <label class="gp-label">Preferred Date <span class="required-star">*</span></label>
                                <input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="gp-label">Preferred Time <span class="required-star">*</span></label>
                                <input type="time" name="appointment_time" min="08:00" max="21:00" class="form-control" required>
                                <small class="text-muted"><i class="fas fa-clock mr-1"></i>8:00 AM – 9:00 PM</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="gp-label">Additional Notes <span class="gp-optional">(optional)</span></label>
                            <textarea name="note" class="form-control" rows="2" placeholder="Anything else you'd like us to know…"></textarea>
                        </div>

                        <!-- Info notice -->
                        <div class="gp-info-box mb-4">
                            <i class="fas fa-info-circle mr-2" style="color:var(--paw-primary);"></i>
                            After submission, staff or admin will review your request and update its status.
                            You can track it on your <a href="appointment.php" style="color:var(--paw-primary);font-weight:700;">Appointments page</a>.
                        </div>

                        <button type="submit" name="submit_give_pet" class="btn btn-primary btn-block gp-submit-btn">
                            <i class="fas fa-paw mr-2"></i> Submit Request
                        </button>

                    </form>
                </div><!-- /form body -->
            </div><!-- /card-clean -->

        </div>
    </div>
</div>

<?php page_footer(); ?>
