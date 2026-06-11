<?php
include_once 'auth.php';
header('Content-Type: application/json');
echo json_encode([
    'logged_in' => !empty($_SESSION['email']) && current_user() !== null,
    'role' => (int)($_SESSION['role'] ?? 0)
]);
exit();
?>
