<?php
/**
 * Payment expiry helpers.
 * Manual Bank In has a 3-day receipt-upload window.
 * Only Manual Bank In records are touched here; QR and ToyyibPay behaviour stays unchanged.
 */

if (!function_exists('mark_expired_manual_bank_payments')) {
    function mark_expired_manual_bank_payments($conn, $userId = null) {
        $whereUser = '';
        if ($userId !== null) {
            $whereUser = ' AND user_id=' . (int)$userId;
        }

        $sql = "UPDATE product_payments
                SET status='failed', payment_completed=0
                WHERE payment_method='Manual Bank In'
                  AND receipt_file IS NULL
                  AND paid_amount IS NULL
                  AND LOWER(status) IN ('pending','pending verification')
                  AND created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
                  $whereUser";
        mysqli_query($conn, $sql);
    }
}

if (!function_exists('manual_bank_expires_at')) {
    function manual_bank_expires_at($createdAt) {
        $createdTs = strtotime($createdAt);
        if (!$createdTs || $createdTs > time()) {
            $createdTs = time();
        }
        return $createdTs + (3 * 24 * 60 * 60);
    }
}

if (!function_exists('manual_bank_seconds_left')) {
    function manual_bank_seconds_left($createdAt) {
        return max(0, manual_bank_expires_at($createdAt) - time());
    }
}
?>
