<?php
include 'layout.php';
include_once 'payment_gateway_config.php';
include_once 'mail_config.php';
require_role(1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$payId = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn,
    "SELECT pp.*, p.name AS product_name, p.category AS product_category, p.photo AS product_photo
     FROM product_payments pp
     JOIN products p ON p.id = pp.product_id
     WHERE pp.id = ? AND pp.user_id = ? LIMIT 1");
$currentUserId = current_user_id();
mysqli_stmt_bind_param($stmt, 'ii', $payId, $currentUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pay = mysqli_fetch_assoc($result);

if (!$pay || $pay['payment_method'] !== 'Online Banking Transfer') {
    flash('error', 'Payment record not found.');
    header('Location: products.php'); exit();
}

// Parse bank info
$bankRefData = [];
if (!empty($pay['bank_reference']) && $pay['bank_reference'][0] === '{') {
    $bankRefData = json_decode($pay['bank_reference'], true) ?? [];
}
$selectedBank = $bankRefData['bank'] ?? $pay['bank_name'] ?? 'Your Bank';

// Session keys for this payment's portal state
$sessKey      = 'bank_portal_' . $payId;
$tacSessKey   = 'bank_tac_' . $payId;
$doneSessKey  = 'bank_done_' . $payId;

// Expiry: 30 min from payment creation
$paymentExpiresAt   = strtotime($pay['created_at']) + (30 * 60);
$paymentSecondsLeft = max(0, $paymentExpiresAt - time());
$paymentUploadOpen  = empty($pay['receipt_file']) && in_array(strtolower(trim((string)$pay['status'])), ['pending','pending verification'], true);
$paymentExpired     = $paymentUploadOpen && $paymentSecondsLeft <= 0;

if ($paymentExpired && strtolower(trim((string)$pay['status'])) !== 'failed') {
    $failedStatus = 'failed';
    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=? WHERE id=? AND user_id=? AND receipt_file IS NULL");
    mysqli_stmt_bind_param($stmt, 'sii', $failedStatus, $payId, $pay['user_id']);
    mysqli_stmt_execute($stmt);
    $pay['status'] = $failedStatus;
}

// Determine current portal step
// Steps: login → tac → confirm → upload_receipt
$portalStep = $_SESSION[$sessKey . '_step'] ?? 'login';

// ── Bank colours & portal name per bank ──────────────────────────────────────
$PAW_PRIMARY   = '#af2708';
$PAW_SECONDARY = '#89a07e';
$PAW_DARK      = '#1f2428';

$bankThemes = [
    'Maybank'            => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'Maybank2u','accent'=>$PAW_PRIMARY,'icon'=>'M'],
    'CIMB Bank'          => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'CIMB Clicks','accent'=>$PAW_PRIMARY,'icon'=>'C'],
    'Public Bank'        => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'PBe','accent'=>$PAW_PRIMARY,'icon'=>'P'],
    'RHB Bank'           => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'RHB Now','accent'=>$PAW_PRIMARY,'icon'=>'R'],
    'Hong Leong Bank'    => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'HLB Connect','accent'=>$PAW_PRIMARY,'icon'=>'H'],
    'AmBank'             => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'AmOnline','accent'=>$PAW_PRIMARY,'icon'=>'A'],
    'Bank Islam'         => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'Bank Islam','accent'=>$PAW_SECONDARY,'icon'=>'BI'],
    'Bank Rakyat'        => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'i-Rakyat','accent'=>$PAW_PRIMARY,'icon'=>'BR'],
    'BSN'                => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'MyBSN','accent'=>$PAW_PRIMARY,'icon'=>'B'],
    'Alliance Bank'      => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'Alliance One','accent'=>$PAW_PRIMARY,'icon'=>'A'],
    'Affin Bank'         => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'AffinOnline','accent'=>$PAW_PRIMARY,'icon'=>'AF'],
    'Bank Muamalat'      => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'Muamalat Online','accent'=>$PAW_SECONDARY,'icon'=>'BM'],
    'UOB Malaysia'       => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'UOB Personal Banking','accent'=>$PAW_PRIMARY,'icon'=>'U'],
    'OCBC Malaysia'      => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'OCBC Online','accent'=>$PAW_PRIMARY,'icon'=>'O'],
    'Standard Chartered' => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'SC Online Banking','accent'=>$PAW_SECONDARY,'icon'=>'SC'],
    'Other'              => ['color'=>$PAW_DARK,'dark'=>'#fff','logo_text'=>'Online Banking','accent'=>$PAW_PRIMARY,'icon'=>'$'],
];
$theme = $bankThemes[$selectedBank] ?? $bankThemes['Other'];
$isDark = true;

// ── Helpers ──────────────────────────────────────────────────────────────────
function bank_payment_items($pay) {
    $items = [];
    if (!empty($pay['cart_items'])) {
        $decoded = json_decode($pay['cart_items'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $items[] = [
                    'name'     => $item['name'] ?? 'Pet Needs Item',
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'subtotal' => (float)($item['subtotal'] ?? 0)
                ];
            }
            return $items;
        }
    }
    return [['name' => $pay['product_name'], 'quantity' => (int)$pay['quantity'], 'subtotal' => (float)$pay['amount']]];
}

function send_tac_email($toEmail, $toName, $tac, $bankName, $amount, $ref) {
    $subject  = "[$bankName] TAC for Fund Transfer – RM " . number_format($amount, 2);
    $htmlBody = "
<div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #ddd;border-radius:8px;overflow:hidden'>
  <div style='background:#1f2428;padding:20px 24px;text-align:center'>
    <h2 style='color:#fff;margin:0;font-size:1.2rem'>$bankName — Transaction Authorisation Code</h2>
  </div>
  <div style='padding:28px 24px'>
    <p>Dear <strong>" . htmlspecialchars($toName) . "</strong>,</p>
    <p>You have initiated a fund transfer of <strong>RM " . number_format($amount, 2) . "</strong> (Ref: <code>$ref</code>).</p>
    <p>Your TAC is:</p>
    <div style='text-align:center;margin:20px 0'>
      <span style='font-size:2.5rem;font-weight:900;letter-spacing:10px;color:#1f2428;background:#fff7f3;padding:14px 28px;border-radius:10px;border:2px dashed #af2708;display:inline-block'>$tac</span>
    </div>
    <p style='color:#c0392b;font-size:.88rem'><strong>⚠ Do NOT share this TAC with anyone.</strong> This code expires in 5 minutes.</p>
    <p style='color:#666;font-size:.82rem'>If you did not initiate this transaction, please contact your bank immediately.</p>
  </div>
  <div style='background:#f7f7f7;padding:12px 24px;font-size:.78rem;color:#888;text-align:center'>
    This is an automated email from $bankName Online Banking. Do not reply.
  </div>
</div>";
    $plainBody = "TAC for RM " . number_format($amount, 2) . " transfer: $tac\nRef: $ref\nDo NOT share this code.";

    if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
        require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
        require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT;
            $mail->SMTPOptions = ['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
            $mail->setFrom(SMTP_FROM_EMAIL, $bankName . ' Online Banking');
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody;
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}

// ── POST handlers ─────────────────────────────────────────────────────────────
$portalError = '';
$user        = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Step 1: Login
    if ($action === 'portal_login') {
        $username = trim($_POST['portal_username'] ?? '');
        $password = trim($_POST['portal_password'] ?? '');
        if ($username === '' || $password === '') {
            $portalError = 'Please enter your username and password.';
        } elseif (strlen($username) < 4 || strlen($password) < 6) {
            $portalError = 'Invalid username or password. Please try again.';
        } else {
            // Generate TAC and send email
            $tac = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION[$tacSessKey]        = $tac;
            $_SESSION[$tacSessKey . '_t'] = time();
            $_SESSION[$sessKey . '_step'] = 'tac';
            $_SESSION[$sessKey . '_user'] = $username;

            $emailSent = send_tac_email(
                $user['Email'], $user['Name'],
                $tac, $selectedBank,
                (float)$pay['amount'], $pay['transaction_id']
            );
            $_SESSION[$sessKey . '_tac_sent'] = $emailSent;
            $portalStep = 'tac';
        }
    }

    // Step 2: TAC verification
    elseif ($action === 'portal_tac') {
        $enteredTac  = trim(str_replace(' ', '', $_POST['portal_tac'] ?? ''));
        $storedTac   = $_SESSION[$tacSessKey] ?? '';
        $tacTime     = $_SESSION[$tacSessKey . '_t'] ?? 0;

        if ($enteredTac === '') {
            $portalError = 'Please enter the TAC sent to your email.';
            $portalStep  = 'tac';
        } elseif (time() - $tacTime > 300) {
            $portalError = 'TAC has expired. Please go back and try again.';
            unset($_SESSION[$tacSessKey], $_SESSION[$sessKey . '_step']);
            $portalStep = 'login';
        } elseif ($enteredTac !== $storedTac) {
            $portalError = 'Incorrect TAC. Please check your email and try again.';
            $portalStep  = 'tac';
        } else {
            // TAC correct → move to confirm step
            $_SESSION[$sessKey . '_step'] = 'confirm';
            $portalStep = 'confirm';
        }
    }

    // Step 3: Final confirm
    elseif ($action === 'portal_confirm') {
        // Mark transfer as "done" in session — redirect to receipt upload
        $_SESSION[$doneSessKey]       = true;
        $_SESSION[$sessKey . '_step'] = 'upload';
        header('Location: bank_payment.php?id=' . $payId . '&step=upload'); exit();
    }

    // Step 4: Upload receipt
    elseif ($action === 'upload_receipt') {
        if ($paymentExpired) {
            $failedStatus = 'failed';
            $stmt = mysqli_prepare($conn, "UPDATE product_payments SET status=? WHERE id=? AND user_id=? AND receipt_file IS NULL");
            mysqli_stmt_bind_param($stmt, 'sii', $failedStatus, $payId, $pay['user_id']);
            mysqli_stmt_execute($stmt);
            flash('error', 'Payment time expired. Please start a new order.');
            header('Location: products.php'); exit();
        }

        $paidAmount = (float)($_POST['paid_amount'] ?? 0);
        if ($paidAmount <= 0) {
            $portalError = 'Please enter the amount you transferred.';
            $portalStep  = 'upload';
        } elseif (empty($_FILES['receipt_file']['name']) || ($_FILES['receipt_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $portalError = 'Please upload your transfer receipt.';
            $portalStep  = 'upload';
        } else {
            $ext = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','pdf'], true)) {
                $portalError = 'Receipt must be JPG, PNG, WEBP, or PDF.';
                $portalStep  = 'upload';
            } elseif ((int)$_FILES['receipt_file']['size'] > 5 * 1024 * 1024) {
                $portalError = 'File too large. Maximum 5MB.';
                $portalStep  = 'upload';
            } else {
                $uploadDir = __DIR__ . '/uploads/receipts';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $safeFile = 'receipt_' . $payId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $uploadDir . '/' . $safeFile)) {
                    $relPath   = 'uploads/receipts/' . $safeFile;
                    $newStatus = 'pending verification';
                    $stmt = mysqli_prepare($conn, "UPDATE product_payments SET receipt_file=?, paid_amount=?, status=? WHERE id=? AND user_id=?");
                    mysqli_stmt_bind_param($stmt, 'sdsii', $relPath, $paidAmount, $newStatus, $payId, $pay['user_id']);
                    mysqli_stmt_execute($stmt);
                    unset($_SESSION[$sessKey.'_step'], $_SESSION[$tacSessKey], $_SESSION[$doneSessKey]);
                    flash('success', 'Transfer receipt submitted. Admin will verify your payment shortly.');
                    header('Location: receipt.php?id=' . $payId); exit();
                } else {
                    $portalError = 'Upload failed. Please try again.';
                    $portalStep  = 'upload';
                }
            }
        }
    }
} else {
    // GET: honour ?step=upload shortcut after confirm
    if (isset($_GET['step']) && $_GET['step'] === 'upload' && !empty($_SESSION[$doneSessKey])) {
        $portalStep = 'upload';
        $_SESSION[$sessKey . '_step'] = 'upload';
    }
}

$paymentItems = bank_payment_items($pay);

page_header($theme['logo_text'] . ' — Secure Payment', 'payments');
?>

<style>
/* ── Matches site card style (qr_payment.php / receipt.php) ── */
.bp-card {
    max-width: 560px;
    margin: 0 auto;
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(31,36,40,.12);
    overflow: hidden;
    background: #fff;
}
.bp-card-header {
    background: linear-gradient(135deg, rgba(31,36,40,.90), rgba(175,39,8,.85));
    color: #fff;
    padding: 28px 32px 20px;
}
.bp-card-header h5 { margin:0; font-size:1.35rem; font-weight:700; }
.bp-card-header p  { margin:5px 0 0; opacity:.85; font-size:.9rem; }
.bp-card-body { padding: 30px 28px; }

/* Steps indicator */
.bp-steps { display:flex; justify-content:center; gap:0; margin-bottom:20px; }
.bp-step {
    flex:1; text-align:center; font-size:.72rem; font-weight:700;
    color:#bbb; padding-bottom:8px; border-bottom:3px solid #e0e0e0;
    transition:color .2s, border-color .2s;
}
.bp-step.done   { color:#af2708; border-color:#af2708; }
.bp-step.active { color:#af2708; border-color:#af2708; }

/* Form elements */
.bp-input-group { position:relative; margin-bottom:18px; }
.bp-input-group label { display:block; font-size:.8rem; font-weight:700; color:#555; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.bp-input-group input {
    width:100%; padding:12px 16px; border:1.5px solid #d0d7de;
    border-radius:10px; font-size:.95rem; outline:none;
    transition:border-color .15s, box-shadow .15s;
}
.bp-input-group input:focus { border-color:#af2708; box-shadow:0 0 0 3px rgba(175,39,8,.12); }
.bp-input-group .bp-eye { position:absolute; right:14px; top:38px; cursor:pointer; color:#999; font-size:1rem; }

/* Buttons — mirrors .btn-primary from pawfect.css */
.bp-btn {
    width:100%; padding:13px; border:none; border-radius:10px; font-size:1rem;
    font-weight:700; cursor:pointer; transition:background .2s, transform .15s;
    background:#af2708; color:#fff; margin-top:6px; display:block; text-align:center; text-decoration:none;
}
.bp-btn:hover { background:#8f1e06; transform:translateY(-1px); color:#fff; text-decoration:none; }
.bp-btn:active { transform:translateY(0); }
.bp-btn-outline {
    background:transparent; border:2px solid #af2708; color:#af2708;
}
.bp-btn-outline:hover { background:#af2708; color:#fff; }

/* Transfer summary — matches .qr-items-list */
.bp-transfer-box {
    background:#fff;
    border:1px solid #eee;
    border-radius:10px;
    padding:10px 14px;
    margin-bottom:20px;
    font-size:.88rem;
}
.bp-transfer-box .bp-row { display:flex; justify-content:space-between; gap:12px; padding:7px 0; border-bottom:1px solid #f0f0f0; }
.bp-transfer-box .bp-row:last-child { border-bottom:0; }
.bp-transfer-box .bp-label { color:#777; }
.bp-transfer-box .bp-val   { font-weight:700; color:#1f2428; text-align:right; max-width:200px; word-break:break-word; }
.bp-transfer-box .bp-val.amount { color:#af2708; font-size:1.1rem; }

/* TAC notice — matches .qr-setup-note */
.bp-tac-sent {
    background:#fff7f3; border:1px dashed #af2708;
    border-radius:10px; padding:12px 14px; margin-bottom:18px;
    font-size:.84rem; color:#7a2a18;
}
.bp-tac-input-wrap { display:flex; gap:8px; justify-content:center; margin:16px 0; }
.bp-tac-digit {
    width:44px; height:52px; border:2px solid #d0d7de; border-radius:8px;
    text-align:center; font-size:1.4rem; font-weight:700; outline:none;
    transition:border-color .15s;
}
.bp-tac-digit:focus { border-color:#af2708; box-shadow:0 0 0 3px rgba(175,39,8,.12); }

/* Timer — matches .timer-badge */
.bp-timer-badge {
    background:#fff7f3; border:1px solid #af2708;
    border-radius:8px; padding:7px 16px; font-size:.85rem;
    color:#af2708; display:inline-block; margin-top:12px; font-weight:600;
}

/* Success icon */
.bp-success-icon {
    width:72px; height:72px; border-radius:50%;
    background:rgba(175,39,8,.1);
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 16px; font-size:2.2rem; color:#af2708;
}

/* Alert */
.bp-alert { background:#fef0f0; border:1px solid #f5c6c6; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:.85rem; color:#c0392b; }

/* Upload hint — matches .receipt-upload-box / steps style */
.bp-upload-hint {
    background:#f8f9f6; border-left:4px solid #89a07e;
    border-radius:10px; padding:14px 18px; text-align:center;
    font-size:.83rem; color:#444; cursor:pointer; transition:border-left-color .15s;
    display:block;
}
.bp-upload-hint:hover { border-left-color:#af2708; }

/* Security footer */
.bp-footer {
    margin-top:18px; text-align:center; font-size:.75rem;
    color:#aaa; display:flex; align-items:center; justify-content:center; gap:8px;
}

/* Back link — matches .btn-back */
.bp-back-link {
    color:#af2708; border:1px solid #af2708;
    border-radius:8px; padding:7px 20px; font-size:.88rem;
    display:inline-block; margin-top:14px; text-decoration:none;
    transition:background .2s, color .2s;
}
.bp-back-link:hover { background:#af2708; color:#fff; text-decoration:none; }

</style>

<?php
// Steps active state
$stepState = ['login'=>'','tac'=>'','confirm'=>'','upload'=>''];
$stepsDone = [];
$stepsFlow = ['login','tac','confirm','upload'];
foreach ($stepsFlow as $s) {
    if ($s === $portalStep) break;
    $stepsDone[] = $s;
}
foreach ($stepsFlow as $s) {
    if (in_array($s,$stepsDone)) $stepState[$s]='done';
    elseif ($s===$portalStep) $stepState[$s]='active';
}
?>

<div class="container py-5">
    <!-- Bank name sub-heading -->
    <p class="text-center text-muted mb-2" style="font-size:.88rem;">
        <i class="fas fa-university mr-1"></i>
        <?php echo h($theme['logo_text']); ?> &mdash; Secure Online Banking Portal
    </p>

    <!-- Steps bar -->
    <div class="bp-steps" style="max-width:560px;margin:0 auto 20px;">
        <div class="bp-step <?php echo $stepState['login']; ?>">1. Login</div>
        <div class="bp-step <?php echo $stepState['tac']; ?>">2. Verify TAC</div>
        <div class="bp-step <?php echo $stepState['confirm']; ?>">3. Confirm</div>
        <div class="bp-step <?php echo $stepState['upload']; ?>">4. Receipt</div>
    </div>

    <?php if ($portalError): ?>
        <div class="bp-alert" style="max-width:560px;margin:0 auto 16px;"><?php echo h($portalError); ?></div>
    <?php endif; ?>

    <?php if ($paymentExpired && $portalStep !== 'upload'): ?>
        <div class="bp-card">
            <div class="bp-card-header"><h5><i class="fas fa-clock mr-2"></i>Session Expired</h5></div>
            <div class="bp-card-body text-center">
                <p class="text-muted">Your payment session has expired (30 minutes limit).</p>
                <a href="products.php" class="bp-btn" style="display:inline-block;text-decoration:none;padding:12px 28px;width:auto;">Back to Products</a>
            </div>
        </div>
    <?php elseif ($portalStep === 'login'): ?>
    <!-- ── STEP 1: LOGIN ── -->
    <div class="bp-card">
        <div class="bp-card-header">
            <h5><i class="fas fa-sign-in-alt mr-2"></i>Online Banking Login</h5>
            <p>Sign in to your <?php echo h($selectedBank); ?> online banking account</p>
        </div>
        <div class="bp-card-body">
            <div class="bp-transfer-box">
                <div class="bp-row"><span class="bp-label">Paying to</span><span class="bp-val"><?php echo h(BANK_ACCOUNT_NAME); ?></span></div>
                <div class="bp-row"><span class="bp-label">Amount</span><span class="bp-val amount">RM <?php echo number_format((float)$pay['amount'], 2); ?></span></div>
                <div class="bp-row"><span class="bp-label">Reference</span><span class="bp-val"><?php echo h($pay['transaction_id']); ?></span></div>
            </div>

            <form method="post">
                <input type="hidden" name="action" value="portal_login">
                <div class="bp-input-group">
                    <label>Username / User ID</label>
                    <input type="text" name="portal_username" placeholder="e.g. ahmad_ali" autocomplete="username" required>
                </div>
                <div class="bp-input-group">
                    <label>Password</label>
                    <input type="password" name="portal_password" id="portalPwd" placeholder="Enter your password" autocomplete="current-password" required>
                    <span class="bp-eye" onclick="togglePwd()"><i class="fas fa-eye" id="eyeIcon"></i></span>
                </div>
                <button type="submit" class="bp-btn">Login &amp; Continue</button>
            </form>

            <div class="bp-footer">
                <i class="fas fa-shield-alt"></i> 256-bit SSL encrypted &nbsp;|&nbsp;
                <i class="fas fa-lock"></i> This is a simulated portal for PawFect Home
            </div>
            <div class="mt-3 text-center">
                <a href="payment.php?<?php echo $isCartCheckout ? 'cart=1' : 'product_id='.$productId; ?>" style="font-size:.8rem;color:#999;">← Cancel &amp; go back</a>
            </div>
        </div>
    </div>

    <?php elseif ($portalStep === 'tac'): ?>
    <!-- ── STEP 2: TAC ── -->
    <?php $tacSent = $_SESSION[$sessKey . '_tac_sent'] ?? false; ?>
    <div class="bp-card">
        <div class="bp-card-header">
            <h5><i class="fas fa-envelope-open-text mr-2"></i>Transaction Authorisation Code (TAC)</h5>
            <p>A 6-digit TAC has been sent to your registered email</p>
        </div>
        <div class="bp-card-body">
            <?php if ($tacSent): ?>
                <div class="bp-tac-sent">
                    <i class="fas fa-check-circle mr-1"></i>
                    TAC sent to <strong><?php echo h($user['Email']); ?></strong>. Check your inbox (and spam folder).
                </div>
            <?php else: ?>
                <div class="bp-alert">
                    Email could not be sent (SMTP may not be configured). Please use the TAC shown below for testing:<br>
                    <strong style="font-size:1.4rem;letter-spacing:4px;"><?php echo $_SESSION[$tacSessKey] ?? '------'; ?></strong>
                </div>
            <?php endif; ?>

            <div class="bp-transfer-box">
                <div class="bp-row"><span class="bp-label">Transfer Amount</span><span class="bp-val amount">RM <?php echo number_format((float)$pay['amount'], 2); ?></span></div>
                <div class="bp-row"><span class="bp-label">Recipient</span><span class="bp-val"><?php echo h(BANK_ACCOUNT_NAME); ?></span></div>
                <div class="bp-row"><span class="bp-label">Account</span><span class="bp-val"><?php echo h(BANK_ACCOUNT_NUMBER); ?> (<?php echo h(BANK_ACCOUNT_BANK); ?>)</span></div>
            </div>

            <form method="post" id="tacForm">
                <input type="hidden" name="action" value="portal_tac">
                <label style="display:block;font-size:.8rem;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.05em;text-align:center;margin-bottom:4px;">Enter 6-Digit TAC</label>
                <div class="bp-tac-input-wrap" id="tacDigits">
                    <input class="bp-tac-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bp-tac-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bp-tac-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bp-tac-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bp-tac-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                    <input class="bp-tac-digit" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]">
                </div>
                <input type="hidden" name="portal_tac" id="tacHidden">
                <button type="submit" class="bp-btn" id="tacBtn">Verify TAC</button>
            </form>

            <div style="text-align:center;margin-top:12px;">
                <span class="bp-timer-badge"><i class="fas fa-clock mr-1"></i> TAC expires in <strong><span id="tacTimer">05:00</span></strong></span>
            </div>
            <div class="bp-footer mt-3">
                <i class="fas fa-exclamation-triangle"></i> Never share your TAC with anyone
            </div>
        </div>
    </div>

    <?php elseif ($portalStep === 'confirm'): ?>
    <!-- ── STEP 3: CONFIRM TRANSFER ── -->
    <div class="bp-card">
        <div class="bp-card-header">
            <h5><i class="fas fa-check-double mr-2"></i>Review &amp; Confirm Transfer</h5>
            <p>Please verify the details before confirming</p>
        </div>
        <div class="bp-card-body">
            <div class="bp-transfer-box">
                <div class="bp-row"><span class="bp-label">From Bank</span><span class="bp-val"><?php echo h($selectedBank); ?></span></div>
                <div class="bp-row"><span class="bp-label">To Account Name</span><span class="bp-val"><?php echo h(BANK_ACCOUNT_NAME); ?></span></div>
                <div class="bp-row"><span class="bp-label">To Bank</span><span class="bp-val"><?php echo h(BANK_ACCOUNT_BANK); ?></span></div>
                <div class="bp-row"><span class="bp-label">To Account No.</span><span class="bp-val"><?php echo h(BANK_ACCOUNT_NUMBER); ?></span></div>
                <div class="bp-row"><span class="bp-label">Transfer Amount</span><span class="bp-val amount">RM <?php echo number_format((float)$pay['amount'], 2); ?></span></div>
                <div class="bp-row"><span class="bp-label">Reference</span><span class="bp-val"><?php echo h($pay['transaction_id']); ?></span></div>
                <div class="bp-row"><span class="bp-label">Items</span>
                    <span class="bp-val">
                        <?php foreach ($paymentItems as $item): ?>
                            <?php echo h($item['name']); ?> x<?php echo $item['quantity']; ?><br>
                        <?php endforeach; ?>
                    </span>
                </div>
            </div>

            <p style="font-size:.82rem;color:#c0392b;text-align:center;"><i class="fas fa-exclamation-circle mr-1"></i>By confirming, you authorise this transfer. This action cannot be undone.</p>

            <form method="post">
                <input type="hidden" name="action" value="portal_confirm">
                <button type="submit" class="bp-btn">Confirm Transfer</button>
            </form>
            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="action" value="cancel_back">
                <button type="button" class="bp-btn bp-btn-outline" onclick="history.back()">← Back</button>
            </form>

            <div class="bp-footer mt-3">
                <i class="fas fa-shield-alt"></i> 256-bit SSL encrypted · Verified by <?php echo h($selectedBank); ?>
            </div>
        </div>
    </div>

    <?php elseif ($portalStep === 'upload'): ?>
    <!-- ── STEP 4: TRANSFER SUCCESS + RECEIPT UPLOAD ── -->
    <div class="bp-card">
        <div class="bp-card-header">
            <h5><i class="fas fa-check-circle mr-2"></i>Transfer Submitted!</h5>
            <p>Upload your receipt for PawFect Home to verify</p>
        </div>
        <div class="bp-card-body">
            <div class="bp-success-icon"><i class="fas fa-check"></i></div>
            <h5 style="text-align:center;font-weight:800;color:#1f2428;margin-bottom:4px;">Transfer Initiated</h5>
            <p style="text-align:center;font-size:.88rem;color:#666;margin-bottom:20px;">
                Your transfer of <strong>RM <?php echo number_format((float)$pay['amount'], 2); ?></strong> to <strong><?php echo h(BANK_ACCOUNT_NAME); ?></strong> has been submitted.<br>
                Please screenshot or download your bank confirmation page, then upload it below.
            </p>

            <div class="bp-transfer-box">
                <div class="bp-row"><span class="bp-label">Reference No.</span><span class="bp-val"><?php echo h($pay['transaction_id']); ?></span></div>
                <div class="bp-row"><span class="bp-label">Amount</span><span class="bp-val amount">RM <?php echo number_format((float)$pay['amount'], 2); ?></span></div>
                <div class="bp-row"><span class="bp-label">Status</span><span class="bp-val" style="color:green;">Submitted to Bank</span></div>
            </div>

            <?php if ($portalError): ?>
                <div class="bp-alert"><?php echo h($portalError); ?></div>
            <?php endif; ?>

            <?php if (!empty($pay['receipt_file'])): ?>
                <div style="background:#fff7f3;border:1px solid rgba(175,39,8,.2);border-radius:8px;padding:12px 16px;font-size:.85rem;color:#af2708;margin-bottom:14px;">
                    <i class="fas fa-check-circle mr-1"></i> Receipt already uploaded. Admin will verify shortly.
                </div>
                <a href="receipt.php?id=<?php echo $payId; ?>" class="bp-btn" style="display:block;text-align:center;text-decoration:none;">View Payment Receipt</a>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload_receipt">
                    <div class="bp-input-group">
                        <label>Amount You Transferred (RM)</label>
                        <input type="number" name="paid_amount" step="0.01" min="0.01"
                               value="<?php echo number_format((float)$pay['amount'], 2, '.', ''); ?>" required>
                    </div>
                    <div class="bp-input-group">
                        <label>Upload Transfer Receipt / Screenshot</label>
                        <label class="bp-upload-hint" for="receiptFile">
                            <i class="fas fa-cloud-upload-alt" style="font-size:1.4rem;display:block;margin-bottom:6px;"></i>
                            Click to select your receipt (JPG, PNG, PDF · max 5MB)
                        </label>
                        <input type="file" name="receipt_file" id="receiptFile" accept="image/*,.pdf" required style="display:none;" onchange="showFileName(this)">
                        <div id="fileNameDisplay" style="text-align:center;font-size:.8rem;color:#555;margin-top:6px;"></div>
                    </div>
                    <button type="submit" class="bp-btn"><i class="fas fa-paper-plane mr-2"></i>Submit Receipt for Verification</button>
                </form>
            <?php endif; ?>

            <div class="mt-3 text-center">
                <a href="products.php" style="font-size:.8rem;color:#999;">← Back to Products</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div><!-- /.container -->

<script>
// Password toggle
function togglePwd() {
    var f = document.getElementById('portalPwd');
    var i = document.getElementById('eyeIcon');
    if (!f) return;
    if (f.type === 'password') { f.type='text'; i.className='fas fa-eye-slash'; }
    else { f.type='password'; i.className='fas fa-eye'; }
}

// TAC digit boxes → auto-advance
document.addEventListener('DOMContentLoaded', function() {
    var digits = document.querySelectorAll('.bp-tac-digit');
    if (!digits.length) return;
    digits.forEach(function(d, i) {
        d.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g,'').slice(-1);
            if (this.value && i < digits.length - 1) digits[i+1].focus();
            updateTacHidden();
        });
        d.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && i > 0) digits[i-1].focus();
        });
        d.addEventListener('paste', function(e) {
            e.preventDefault();
            var pasted = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
            digits.forEach(function(dd, j) { dd.value = pasted[j] || ''; });
            updateTacHidden();
            if (pasted.length >= digits.length) digits[digits.length-1].focus();
        });
    });
    function updateTacHidden() {
        var h = document.getElementById('tacHidden');
        if (h) h.value = Array.from(digits).map(function(d){return d.value;}).join('');
    }

    // TAC countdown 5 min
    var tacTimerEl = document.getElementById('tacTimer');
    if (tacTimerEl) {
        var secs = 300;
        var iv = setInterval(function() {
            secs--;
            if (secs <= 0) { clearInterval(iv); tacTimerEl.textContent='00:00'; return; }
            var m = Math.floor(secs/60), s = secs%60;
            tacTimerEl.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
        }, 1000);
    }

    // File name display
    window.showFileName = function(input) {
        var d = document.getElementById('fileNameDisplay');
        if (d && input.files[0]) d.textContent = '📎 ' + input.files[0].name;
    };
});
</script>
<?php page_footer(); ?>
