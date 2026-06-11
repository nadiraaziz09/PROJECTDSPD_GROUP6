<?php
/*
    ToyyibPay FPX / Malaysia online banking setup
    ---------------------------------------------
    The checkout page now sends Online Banking payments to ToyyibPay.

    To activate real ToyyibPay redirection:
    1. Login to ToyyibPay / dev.toyyibpay.com.
    2. Copy your User Secret Key.
    3. Create a Category and copy the Category Code.
    4. Put both values below and change TOYYIBPAY_ENABLED to true.
    5. Use TOYYIBPAY_SANDBOX=true for testing, false for live production.

    If TOYYIBPAY_ENABLED stays false, the ToyyibPay page will show setup instructions instead of causing a 404 error.
*/
define('TOYYIBPAY_ENABLED', false);
define('TOYYIBPAY_SANDBOX', true);
define('TOYYIBPAY_USER_SECRET_KEY', 'qhicglbk-ouu9-54js-5xn2-vr9i5tx0ezbt');
define('TOYYIBPAY_CATEGORY_CODE', '1z7svuq3');

define('BANK_ACCOUNT_NAME', 'PawFect Home Pet Adoption System');
define('BANK_ACCOUNT_BANK', 'Maybank');
define('BANK_ACCOUNT_NUMBER', '0082 4414 4517');

function pawfect_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/Project')), '/');
    return $scheme . '://' . $host . ($dir ? $dir : '');
}

function toyyibpay_api_base() {
    return TOYYIBPAY_SANDBOX ? 'https://dev.toyyibpay.com' : 'https://toyyibpay.com';
}

function toyyibpay_is_configured() {
    return TOYYIBPAY_ENABLED
        && TOYYIBPAY_USER_SECRET_KEY !== ''
        && TOYYIBPAY_CATEGORY_CODE !== ''
        && strpos(TOYYIBPAY_USER_SECRET_KEY, 'PUT_YOUR') === false
        && strpos(TOYYIBPAY_CATEGORY_CODE, 'PUT_YOUR') === false;
}
?>
