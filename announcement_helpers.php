<?php
function ensure_announcement_target_column() {
    global $conn;
    $check = mysqli_query($conn, "SHOW COLUMNS FROM announcements LIKE 'target_audience'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "ALTER TABLE announcements ADD COLUMN target_audience varchar(20) NOT NULL DEFAULT 'both' AFTER content");
    }
}

function clean_announcement_target($target) {
    $target = strtolower(trim((string)$target));
    return in_array($target, ['user', 'staff', 'both'], true) ? $target : 'both';
}

function announcement_target_label($target) {
    $target = clean_announcement_target($target);
    if ($target === 'user') return 'Users Only';
    if ($target === 'staff') return 'Staff Only';
    return 'Users & Staff';
}

function announcement_target_badge($target) {
    $target = clean_announcement_target($target);
    $class = $target === 'user' ? 'info' : ($target === 'staff' ? 'warning' : 'primary');
    return '<span class="badge badge-' . $class . ' text-uppercase">' . h(announcement_target_label($target)) . '</span>';
}

function get_announcements_for_role($role) {
    global $conn;
    ensure_announcement_target_column();

    $role = (int)$role;
    $targets = $role === 1 ? "'user','both'" : "'staff','both'";
    $sql = "SELECT id, title, content, expiry_date, target_audience, created_at
            FROM announcements
            WHERE status='active'
              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
              AND target_audience IN ($targets)
            ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
    $announcements = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $announcements[] = $row;
        }
    }
    return $announcements;
}

function render_announcement_notifications($role) {
    $announcements = get_announcements_for_role($role);
    if (empty($announcements)) {
        return;
    }
    ?>
    <div class="card-clean p-4 mb-4 announcement-notification-box">
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
            <h4 class="mb-2 mb-md-0"><i class="fas fa-bell text-primary mr-2"></i>Announcements</h4>
            <span class="badge badge-danger px-3 py-2"><?php echo count($announcements); ?> notification<?php echo count($announcements) > 1 ? 's' : ''; ?></span>
        </div>
        <?php foreach ($announcements as $a): ?>
            <div class="announcement-alert mb-3">
                <div class="d-flex justify-content-between flex-wrap">
                    <h6 class="mb-1"><?php echo h($a['title']); ?></h6>
                    <?php if (!empty($a['expiry_date'])): ?>
                        <small class="text-muted"><i class="far fa-calendar-alt mr-1"></i>Until <?php echo h(date('d/m/Y', strtotime($a['expiry_date']))); ?></small>
                    <?php endif; ?>
                </div>
                <p class="mb-0 text-muted"><?php echo nl2br(h($a['content'])); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
?>
