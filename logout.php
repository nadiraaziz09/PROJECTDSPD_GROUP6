<?php
include_once 'auth.php';

// Fully clear all session values.
$_SESSION = [];

// Remove the PHP session cookie from the browser.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_unset();
session_destroy();
send_no_cache_headers();

// Use JavaScript history.replace so the Logout page is not kept in browser history.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Signing Out - PawFect Home</title>
</head>
<body>
<script>
    try {
        sessionStorage.clear();
        localStorage.removeItem('pawfect_logged_in');
    } catch (e) {}
    window.location.replace('signin.php?logout=1');
</script>
<noscript><meta http-equiv="refresh" content="0;url=signin.php?logout=1"></noscript>
</body>
</html>
<?php exit(); ?>
