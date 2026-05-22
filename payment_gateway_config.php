<?php
/*
    Real FPX / Malaysia online banking setup
    ---------------------------------------
    This project can redirect customers to a real ToyyibPay FPX bill when the credentials below are filled in.
    1. Register/login at ToyyibPay or dev.toyyibpay.com for sandbox testing.
    2. Create a category and copy the Category Code.
    3. Copy your User Secret Key.
    4. Change TOYYIBPAY_ENABLED to true.

    Without these merchant credentials, the system uses Manual Online Banking Transfer mode.
*/
define('TOYYIBPAY_ENABLED', false);
define('TOYYIBPAY_SANDBOX', true);
define('TOYYIBPAY_USER_SECRET_KEY', 'PUT_YOUR_TOYYIBPAY_USER_SECRET_KEY_HERE');
define('TOYYIBPAY_CATEGORY_CODE', 'PUT_YOUR_TOYYIBPAY_CATEGORY_CODE_HERE');

define('BANK_ACCOUNT_NAME', 'PawFect Home Pet Adoption System');
define('BANK_ACCOUNT_BANK', 'Maybank');
define('BANK_ACCOUNT_NUMBER', '5628 1234 9876');

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
