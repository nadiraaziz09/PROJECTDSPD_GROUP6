<?php
function ensure_adoption_discussions_table() {
    global $conn;
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `adoption_discussions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `applicant_name` varchar(100) NOT NULL,
        `contact` varchar(120) NOT NULL,
        `pet_photo` varchar(255) DEFAULT NULL,
        `appointment_date` date NOT NULL,
        `appointment_time` time NOT NULL,
        `note` text DEFAULT NULL,
        `status` varchar(30) NOT NULL DEFAULT 'pending',
        `staff_note` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        CONSTRAINT `adoption_discussions_user_fk` FOREIGN KEY (`user_id`) REFERENCES `account` (`ID`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function save_adoption_discussion_photo($field = 'pet_photo') {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return [true, null, null];
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return [false, null, 'Pet picture upload failed. Please try again.'];
    }

    if ((int)$_FILES[$field]['size'] > 5 * 1024 * 1024) {
        return [false, null, 'Pet picture must be 5MB or below.'];
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $imageInfo = @getimagesize($tmp);
    if ($imageInfo === false) {
        return [false, null, 'Please upload a valid pet picture file.'];
    }

    $mime = $imageInfo['mime'] ?? '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    if (!isset($extensions[$mime])) {
        return [false, null, 'Only JPG, PNG, GIF or WEBP pet pictures are allowed.'];
    }

    $uploadDir = __DIR__ . '/uploads/adoption_discussions';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'adoption_discussion_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmp, $target)) {
        return [false, null, 'Unable to save pet picture. Please check the uploads folder permission.'];
    }

    return [true, 'uploads/adoption_discussions/' . $fileName, null];
}

function format_discussion_datetime($date, $time) {
    $timestamp = strtotime($date . ' ' . $time);
    if (!$timestamp) return trim($date . ' ' . $time);
    return date('d M Y, h:i A', $timestamp);
}
?>
