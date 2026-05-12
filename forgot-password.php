<?php
include 'layout.php';
include_once 'mail_config.php';
include_once 'payment_gateway_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_reset_email($toEmail, $toName, $resetLink, &$errorMessage) {
    $subject = 'PawFect Home Password Reset Link';
    $plainBody = "Hello $toName,\n\nPlease reset your PawFect Home password using this link:\n$resetLink\n\nIf you did not request this, please ignore this email.";
    $htmlBody = "<p>Hello <strong>" . h($toName) . "</strong>,</p>"
        . "<p>Please reset your PawFect Home password using the button below:</p>"
        . "<p><a href='" . h($resetLink) . "' style='background:#af2708;color:#ffffff;padding:12px 18px;text-decoration:none;border-radius:8px;'>Reset Password</a></p>"
        . "<p>Or copy this link:<br>" . h($resetLink) . "</p>"
        . "<p>If you did not request this, please ignore this email.</p>";

    if (SMTP_ENABLED) {
        require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
        require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->SMTPOptions = [
                'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
                ]
            ];
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody;
            $mail->SMTPDebug = 0;
            $mail->send();
            return true;
        } catch (Exception $e) {
            $errorMessage = $mail->ErrorInfo ?: $e->getMessage();
            return false;
        }
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: PawFect Home <no-reply@pawfect.local>\r\n";
    if (@mail($toEmail, $subject, $htmlBody, $headers)) {
        return true;
    }
    $errorMessage = 'SMTP is not configured and PHP mail() is not available on this XAMPP setup.';
    return false;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $stmt = mysqli_prepare($conn, "SELECT ID, Name, Email FROM account WHERE Email=? AND Status='active' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600);
        $stmt = mysqli_prepare($conn, "UPDATE account SET reset_token=?, reset_token_expiry=? WHERE ID=?");
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiry, $user['ID']);
        mysqli_stmt_execute($stmt);

        $resetLink = pawfect_base_url() . '/reset-password.php?token=' . urlencode($token);
        $mailError = '';
        $sent = send_reset_email($user['Email'], $user['Name'], $resetLink, $mailError);

        if ($sent) {
            $message = "<div class='alert alert-success text-center'>Password reset email has been sent. Please check your inbox.</div>";
        } else {
            $message = "<div class='alert alert-warning text-center'>Reset link was generated, but email sending is not configured yet.<br><small>" . h($mailError) . "</small><br><a class='btn btn-primary mt-3' href='" . h($resetLink) . "'>Reset Password</a></div>";
        }
    } else {
        $message = "<div class='alert alert-danger text-center'>Email not found or account inactive.</div>";
    }
}

page_header('Forgot Password - PawFect Home');
?>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width:520px">
        <h2 class="text-center mb-3">Forgot Password</h2>
        <p class="text-center text-muted">Enter your registered email. The system will send a password reset link to your email address.</p>
        <?php echo $message; ?>
        <form method="post">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
            </div>
            <button class="btn btn-primary btn-block">Send Reset Link</button>
        </form>
        <div class="alert alert-light border small mt-4 mb-0">
            To send a real email from XAMPP, open <strong>mail_config.php</strong> and add your SMTP details first.
        </div>
        <div class="text-center mt-3"><a href="signin.php">Back to Sign In</a></div>
    </div>
</div>
<?php page_footer(); ?>
