<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_cache_limiter('nocache');
    session_start();
}

// Strong no-cache headers so logged-in pages are not restored after logout.
function send_no_cache_headers() {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
    }
}
send_no_cache_headers();

include_once 'db.php';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function current_user() {
    global $conn;
    if (empty($_SESSION['email'])) {
        return null;
    }
    $stmt = mysqli_prepare($conn, "SELECT * FROM account WHERE Email=? AND Status='active' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $_SESSION['email']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result) ?: null;
    if (!$user) {
        $_SESSION = [];
        return null;
    }
    return $user;
}

function current_user_id() {
    $u = current_user();
    return $u ? (int)$u['ID'] : 0;
}

function role_home() {
    $role = $_SESSION['role'] ?? null;
    if ($role == 2) return 'menu_staff.php';
    if ($role == 3) return 'menu_admin.php';
    if ($role == 1) return 'menu.php';
    return 'index.php';
}

function require_login() {
    $GLOBALS['PAGE_REQUIRES_LOGIN'] = true;
    if (empty($_SESSION['email']) || !current_user()) {
        flash('error', 'Please sign in first to access this page.');
        header('Location: signin.php');
        exit();
    }
}

function require_role($roles) {
    require_login();
    $roles = (array)$roles;
    if (!in_array((int)($_SESSION['role'] ?? 0), $roles, true)) {
        flash('error', 'You do not have permission to access that page.');
        header('Location: ' . role_home());
        exit();
    }
}

function status_badge($status) {
    $s = strtolower((string)$status);
    $class = 'secondary';
    if (in_array($s, ['available','approved','completed','active','booked','paid'])) $class = 'success';
    if (in_array($s, ['pending','processing','rescheduled','pending verification'])) $class = 'warning';
    if (in_array($s, ['rejected','cancelled','failed','inactive','adopted','refunded'])) $class = 'danger';
    return '<span class="badge badge-' . $class . ' text-uppercase">' . h($status) . '</span>';
}
?>
