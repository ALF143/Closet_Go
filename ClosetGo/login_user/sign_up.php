<?php
error_reporting(0);
ini_set('display_errors', '0');

// Handle AJAX requests for email validation, OTP sending, and user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Include database configuration
    require_once __DIR__ . '/../database/db_config.php';
    
    $action = $_POST['action'];
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again.']);
            exit;
        }
        
        switch ($action) {
            case 'check_email':
                $email = trim($_POST['email'] ?? '');
                
                if (empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'Email is required']);
                    exit;
                }
                
                // Check if email already exists in system_users table
                $checkSql = "SELECT id FROM system_users WHERE email = ? LIMIT 1";
                $checkStmt = $conn->prepare($checkSql);
                if (!$checkStmt) {
                    echo json_encode(['success' => false, 'message' => 'Query preparation error: ' . $conn->error]);
                    exit;
                }
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists. Please login instead.']);
                } else {
                    echo json_encode(['success' => true, 'message' => 'Email is available']);
                }
                $checkStmt->close();
                exit;
                break;
                
            case 'send_signup_otp':
                $email = trim($_POST['email'] ?? '');
                
                if (empty($email)) {
                    echo json_encode(['success' => false, 'message' => 'Email is required']);
                    exit;
                }
                
                // Generate 6-digit OTP
                $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Send OTP email
                $sent = sendSignupOtpEmail($email, $otp, $conn);
                
                if ($sent) {
                    echo json_encode(['success' => true, 'message' => 'OTP sent successfully', 'otp' => $otp]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
                }
                exit;
                break;

            case 'save_email':
            case 'create_account':
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($email) || empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
                    exit;
                }

                if (strlen($password) < 8) {
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
                    exit;
                }

                if (!preg_match('/^[A-Z]/', $password)) {
                    echo json_encode(['success' => false, 'message' => 'Password must start with an uppercase letter']);
                    exit;
                }

                if (!preg_match('/[!@#$%^&*]/', $password)) {
                    echo json_encode(['success' => false, 'message' => 'Password must contain at least 1 symbol (!@#$%^&*)']);
                    exit;
                }

                // Double check if email already exists
                $chkSql = "SELECT id FROM system_users WHERE email = ? LIMIT 1";
                $chkStmt = $conn->prepare($chkSql);
                if ($chkStmt) {
                    $chkStmt->bind_param("s", $email);
                    $chkStmt->execute();
                    $chkRes = $chkStmt->get_result();
                    if ($chkRes && $chkRes->num_rows > 0) {
                        $chkStmt->close();
                        echo json_encode(['success' => false, 'message' => 'Email already registered. Please login.']);
                        exit;
                    }
                    $chkStmt->close();
                }

                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert new account
                $insSql = "INSERT INTO system_users (email, password) VALUES (?, ?)";
                $insStmt = $conn->prepare($insSql);
                if (!$insStmt) {
                    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
                    exit;
                }

                $insStmt->bind_param("ss", $email, $hashedPassword);
                if ($insStmt->execute()) {
                    $newId = $insStmt->insert_id;
                    $insStmt->close();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Sign up successful! Welcome to ClosetGo.',
                        'user_id' => $newId
                    ]);
                } else {
                    $err = $insStmt->error;
                    $insStmt->close();
                    echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $err]);
                }
                exit;
                break;
        }
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Helper: Send Sign-up OTP Email
function sendSignupOtpEmail($email, $otp, $conn) {
    $smtpUser = 'clos3tgo@gmail.com';
    $smtpPass = 'komm kdvz mjvs rysr';
    $smtpHost = 'smtp.gmail.com';
    $smtpPort = 587;

    try {
        if ($conn) {
            $smRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_user','smtp_pass','smtp_host','smtp_port')");
            if ($smRes) {
                while ($row = $smRes->fetch_assoc()) {
                    if ($row['setting_key'] === 'smtp_user' && !empty($row['setting_value'])) $smtpUser = $row['setting_value'];
                    if ($row['setting_key'] === 'smtp_pass' && !empty($row['setting_value'])) $smtpPass = $row['setting_value'];
                    if ($row['setting_key'] === 'smtp_host' && !empty($row['setting_value'])) $smtpHost = $row['setting_value'];
                    if ($row['setting_key'] === 'smtp_port' && !empty($row['setting_value'])) $smtpPort = intval($row['setting_value']);
                }
            }
        }
    } catch (\Throwable $ex) {}

    require_once __DIR__ . '/../PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

    $subject = "ClosetGo - Email Verification Code: $otp";
    $year = date('Y');
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; background-color: #0d0d0d; color: #ffffff; padding: 24px 12px; margin: 0; }
            .container { max-width: 480px; margin: 0 auto; background: #181818; border: 1px solid rgba(255, 215, 0, 0.3); border-radius: 16px; padding: 32px 24px; text-align: center; }
            .logo { font-size: 26px; font-weight: 800; color: #ffd700; letter-spacing: 1px; margin-bottom: 6px; }
            .subtitle { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #b8960c; margin-bottom: 24px; }
            .icon-box { font-size: 42px; margin-bottom: 16px; }
            .title { font-size: 20px; font-weight: 700; color: #ffffff; margin: 0 0 10px; }
            .desc { font-size: 13.5px; color: #aaaaaa; line-height: 1.6; margin: 0 0 20px; }
            .otp-box { background: #121212; border: 2px dashed #ffd700; border-radius: 12px; padding: 16px; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #ffd700; margin: 20px 0; font-family: monospace; }
            .expiry-note { font-size: 12px; color: #888888; margin: 16px 0 0; }
            .expiry-note strong { color: #ffd700; }
            .footer { font-size: 11px; color: #555555; margin-top: 26px; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">ClosetGo</div>
            <div class="subtitle">Email Verification</div>
            <div class="icon-box">&#x2709;</div>
            <h2 class="title">Verify Your Email Address</h2>
            <p class="desc">Hello,<br>Thank you for signing up for ClosetGo! Use the verification code below to complete your registration:</p>
            <div class="otp-box">' . htmlspecialchars($otp) . '</div>
            <p class="expiry-note">&#x23F1; This code will expire in <strong>10 minutes</strong>. If you did not create an account, you can safely ignore this email.</p>
            <div class="footer">&copy; ' . $year . ' ClosetGo. All rights reserved.</div>
        </div>
    </body>
    </html>';

    $altBody = "Your ClosetGo verification code is: $otp\nThis code expires in 10 minutes.\nIf you did not create an account, please ignore this email.";

    // Try Port 587 (TLS) first, then fallback to Port 465 (SMTPS)
    $configs = [
        ['port' => $smtpPort, 'secure' => ($smtpPort == 465 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS)],
        ['port' => ($smtpPort == 465 ? 587 : 465), 'secure' => ($smtpPort == 465 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS)]
    ];

    foreach ($configs as $cfg) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->SMTPSecure = $cfg['secure'];
            $mail->Port       = $cfg['port'];
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 8;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $mail->setFrom($smtpUser, 'ClosetGo Support');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $altBody;
            
            $mail->send();
            return true;
        } catch (\Exception $e) {
            // Try next config
            continue;
        }
    }
    
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/homepage.css">
    <link rel="icon" href="../images/icon/JMB.png" type="image/png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

        /* ── Brand Logo (JMB.png) ───────────────────── */
        .login-logo-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            text-align: center;
        }

        .login-brand-logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffd700;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.5), 0 0 16px rgba(255, 215, 0, 0.25);
            transition: all 0.3s ease;
            display: block;
            margin: 0 auto;
            background: #121212;
        }

        .login-brand-logo:hover {
            transform: scale(1.06);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.65), 0 0 24px rgba(255, 215, 0, 0.45);
            border-color: #ffe066;
        }

        .login-left-logo-wrap {
            margin-bottom: 24px;
        }

        .brand-panel-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffd700;
            box-shadow: 0 4px 22px rgba(0, 0, 0, 0.6), 0 0 20px rgba(255, 215, 0, 0.3);
            transition: all 0.3s ease;
            display: block;
            background: #121212;
        }

        .brand-panel-logo:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 28px rgba(0, 0, 0, 0.7), 0 0 28px rgba(255, 215, 0, 0.5);
            border-color: #ffe066;
        }

        /* ── Layout ─────────────────────────────────── */
        body.login-page {
            margin: 0;
            padding: 0;
            background: #0a0a0a;
            font-family: 'Poppins', Arial, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
        }

        .login-layout {
            display: flex;
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* ── Left Panel (desktop only) ──────────────── */
        .login-left {
            display: none;
        }

        /* ── Right Panel ────────────────────────────── */
        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: #0a0a0a;
            box-sizing: border-box;
            overflow-x: hidden;
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* ── Login Box ──────────────────────────────── */
        .login-heading {
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 22px 0;
            text-align: center;
            font-family: 'Poppins', Arial, sans-serif;
            letter-spacing: 0.3px;
        }

        body.login-page .login-box {
            background: #181818;
            border: 1px solid rgba(255, 215, 0, 0.22);
            border-radius: 20px;
            padding: 36px 30px;
            width: 100%;
            max-width: 410px;
            min-width: 0;
            margin: 0;
            box-shadow: 0 10px 45px rgba(0, 0, 0, 0.65), 0 0 30px rgba(184, 150, 12, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        body.login-page .login-box:hover {
            box-shadow: 0 12px 55px rgba(0, 0, 0, 0.75), 0 0 40px rgba(255, 215, 0, 0.15);
            border-color: rgba(255, 215, 0, 0.35);
        }

        .login-form-inner {
            display: block;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .login-form-inner.hidden {
            display: none;
        }

        /* ── Inputs ─────────────────────────────────── */
        .password-toggle {
            filter: brightness(0) saturate(100%) invert(100%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(100%) contrast(100%);
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group input[type="email"],
        .input-group input[type="password"],
        .input-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            background: #121212;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            font-size: 14px;
            color: #ffffff;
            outline: none;
            height: 52px;
            box-sizing: border-box;
            transition: all 0.25s ease;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .input-group input:-webkit-autofill,
        .input-group input:-webkit-autofill:hover,
        .input-group input:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff;
            -webkit-box-shadow: 0 0 0 1000px #121212 inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        .input-group input::placeholder { color: transparent; }
        .input-group input:focus { 
            border-color: #ffd700; 
            box-shadow: 0 0 12px rgba(255, 215, 0, 0.2);
            background: #161616;
        }

        .input-group label {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #888888;
            font-size: 14px;
            pointer-events: none;
            transition: all 0.25s ease;
            background: #181818;
            padding: 0 6px;
            border-radius: 4px;
        }

        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: 0;
            font-size: 11.5px;
            color: #ffd700;
            font-weight: 500;
        }

        .input-group .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 20px;
            height: 20px;
        }

        /* ── Password Guide & Requirements ─────────── */
        .pwd-guide-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 215, 0, 0.15);
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 0;
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: all 0.25s ease;
        }

        .pwd-strength-bar-wrap {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 3px;
        }

        .pwd-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .pwd-strength-bar.weak { width: 33%; background: #ff6b6b; }
        .pwd-strength-bar.medium { width: 66%; background: #ffd700; }
        .pwd-strength-bar.strong { width: 100%; background: #22c55e; }

        .pwd-requirement {
            font-size: 11.5px;
            color: #888888;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: color 0.2s ease;
        }

        .pwd-requirement i {
            font-size: 10px;
            width: 12px;
            text-align: center;
            color: #555555;
            transition: all 0.2s ease;
        }

        .pwd-requirement.valid {
            color: #22c55e;
            font-weight: 500;
        }

        .pwd-requirement.valid i {
            color: #22c55e;
        }

        .pwd-requirement.invalid {
            color: #ff6b6b;
        }

        .pwd-requirement.invalid i {
            color: #ff6b6b;
        }

        /* ── Terms Checkbox ─────────────────────────── */
        .terms-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 22px;
        }

        .terms-group input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            min-width: 18px;
            border: 1.5px solid rgba(255, 215, 0, 0.4);
            border-radius: 5px;
            background: #121212;
            cursor: pointer;
            margin-top: 2px;
            transition: all 0.2s ease;
            position: relative;
        }

        .terms-group input[type="checkbox"]:checked {
            background: linear-gradient(135deg, #b8960c, #ffd700);
            border-color: #ffd700;
        }

        .terms-group input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 2px;
            width: 5px;
            height: 9px;
            border: 2px solid #000;
            border-top: none;
            border-left: none;
            transform: rotate(45deg);
        }

        .terms-group label {
            font-size: 12.5px;
            color: #aaaaaa;
            line-height: 1.55;
            cursor: pointer;
        }

        .terms-group label a {
            color: #ffd700;
            text-decoration: none;
            font-weight: 600;
        }

        .terms-group label a:hover {
            text-decoration: underline;
        }

        .login-btn {
            background: linear-gradient(135deg, #b8960c, #ffd700);
            color: #000000;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.25s ease;
            font-family: 'Poppins', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.2);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.35);
            background: linear-gradient(135deg, #c9aa2e, #ffe033);
        }

        .signup-text {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #888888;
        }

        .signup-link {
            color: #ffd700;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .signup-link:hover {
            text-decoration: underline;
            color: #ffe033;
        }

        /* ── Desktop Split Layout ────────────────────── */
        @media (min-width: 900px) {
            body.login-page { background: #0a0a0a; }

            .login-left {
                display: flex;
                flex-direction: column;
                justify-content: center;
                flex: 1 1 55%;
                padding: 60px 64px;
                background: linear-gradient(135deg, #0a0a0a 0%, #121212 60%, #1f1a00 100%);
                border-right: 1px solid rgba(255, 215, 0, 0.15);
                position: relative;
                overflow: hidden;
            }

            .login-left::before {
                content: '';
                position: absolute;
                width: 520px;
                height: 520px;
                border-radius: 50%;
                border: 1.5px solid rgba(255, 215, 0, 0.12);
                top: -120px;
                left: -140px;
                pointer-events: none;
            }

            .login-left::after {
                content: '';
                position: absolute;
                width: 340px;
                height: 340px;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(255, 215, 0, 0.08) 0%, transparent 70%);
                bottom: -80px;
                right: -80px;
                pointer-events: none;
            }

            .brand-eyebrow {
                font-size: .75rem;
                letter-spacing: .22em;
                text-transform: uppercase;
                color: #ffd700;
                font-weight: 600;
                margin-bottom: 18px;
                opacity: .9;
            }

            .brand-headline {
                font-size: clamp(2.4rem, 4vw, 3.6rem);
                font-weight: 800;
                line-height: 1.12;
                color: #ffffff;
                margin: 0 0 20px 0;
            }

            .brand-headline span {
                background: linear-gradient(90deg, #ffd700, #ffe066, #c9aa2e);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .brand-sub {
                font-size: 1rem;
                color: #888;
                font-weight: 400;
                line-height: 1.7;
                max-width: 400px;
                margin-bottom: 40px;
            }

            .brand-badges {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .brand-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: rgba(255, 215, 0, 0.1);
                border: 1px solid rgba(255, 215, 0, 0.25);
                border-radius: 999px;
                padding: 6px 16px;
                font-size: .78rem;
                color: #ffd700;
                font-weight: 500;
                letter-spacing: .03em;
            }

            .login-right {
                flex: 0 0 45%;
                max-width: 560px;
                padding: 60px 56px;
            }

            body.login-page .login-box {
                max-width: 420px;
                padding: 40px 36px;
            }
        }

        @media (min-width: 1200px) {
            .login-left { padding: 60px 80px; }
            .login-right { padding: 60px 64px; }
        }

        /* ── Modern Dark & Gold OTP Verification Screen ── */
        .otp-verify-screen {
            display: none;
            flex-direction: column;
            align-items: center;
            text-align: center;
            animation: fadeInScreen 0.35s ease;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }

        .otp-verify-screen.active {
            display: flex;
        }

        @keyframes fadeInScreen {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Pulse rings */
        .sv-anim {
            position: relative;
            width: 76px;
            height: 76px;
            margin: 0 auto 18px;
        }

        .sv-anim .sv-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid rgba(255, 215, 0, 0.35);
            animation: sv-pulse 2.2s ease-out infinite;
        }

        .sv-anim .sv-ring:nth-child(2) { animation-delay: 0.7s; }
        .sv-anim .sv-ring:nth-child(3) { animation-delay: 1.4s; }

        @keyframes sv-pulse {
            0% { transform: scale(0.7); opacity: 0.9; }
            100% { transform: scale(1.65); opacity: 0; }
        }

        .sv-anim .sv-icon {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 215, 0, 0.05));
            border: 2px solid rgba(255, 215, 0, 0.45);
            border-radius: 50%;
            font-size: 26px;
            color: #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.25);
            animation: sv-beat 1.8s ease-in-out infinite;
        }

        @keyframes sv-beat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        .sv-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 6px;
            letter-spacing: 0.3px;
        }

        .sv-sub {
            font-size: 0.8rem;
            color: #888888;
            line-height: 1.5;
            margin: 0 0 10px;
            max-width: 290px;
        }

        .sv-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 999px;
            padding: 5px 14px;
            font-size: 0.76rem;
            color: #ffd700;
            margin-bottom: 16px;
            word-break: break-all;
            font-weight: 500;
        }

        /* 6-box OTP input row */
        .otp-boxes-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 10px 0 18px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .otp-box-digit {
            width: 44px;
            height: 52px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #ffd700;
            background: #121212;
            border: 1.5px solid rgba(255, 215, 0, 0.3);
            border-radius: 10px;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s ease;
            font-family: 'Poppins', monospace;
            flex: 1 1 44px;
            max-width: 48px;
            min-width: 32px;
        }

        .otp-box-digit:focus {
            border-color: #ffd700;
            box-shadow: 0 0 14px rgba(255, 215, 0, 0.4);
            background: #1a1a1a;
            transform: translateY(-2px);
        }

        .otp-box-digit.filled {
            border-color: #ffd700;
            background: rgba(255, 215, 0, 0.08);
        }

        .otp-box-digit.error {
            border-color: #ff6b6b !important;
            box-shadow: 0 0 12px rgba(255, 107, 107, 0.35) !important;
            color: #ff6b6b !important;
            animation: shake 0.35s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        /* Verify Button */
        .verify-otp-btn {
            background: linear-gradient(135deg, #b8960c, #ffd700);
            color: #000000;
            border: none;
            padding: 13px 20px;
            border-radius: 10px;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.25s ease;
            font-family: 'Poppins', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 18px rgba(255, 215, 0, 0.25);
            margin-bottom: 14px;
        }

        .verify-otp-btn:hover {
            background: linear-gradient(135deg, #c9aa2e, #ffe033);
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(255, 215, 0, 0.4);
        }

        .verify-otp-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Progress Bar & Countdown */
        .sv-bar-wrap {
            width: 100%;
            height: 3px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 3px;
            margin-bottom: 10px;
            overflow: hidden;
        }

        .sv-bar {
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #b8960c, #ffd700);
            border-radius: 3px;
            transition: width 1s linear;
        }

        .sv-expire {
            font-size: 0.74rem;
            color: #777777;
            margin-bottom: 14px;
        }

        .sv-expire strong {
            color: #ffd700;
            font-weight: 600;
        }

        /* Resend and Back actions */
        .resend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            font-size: 0.78rem;
            color: #888888;
            margin-top: 4px;
            gap: 10px;
        }

        .resend-btn, .back-btn {
            background: none;
            border: none;
            color: #ffd700;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            font-size: 0.78rem;
            font-family: 'Poppins', Arial, sans-serif;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .resend-btn:hover, .back-btn:hover {
            color: #ffe033;
            text-decoration: underline;
        }

        .resend-btn:disabled {
            color: #555555;
            cursor: not-allowed;
            text-decoration: none;
        }

        /* ── Mobile Responsiveness ── */
        @media (max-width: 480px) {
            body.login-page {
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                min-height: 100dvh;
            }

            .login-layout {
                min-height: 100vh;
                min-height: 100dvh;
                align-items: center;
                justify-content: center;
            }

            .login-right {
                padding: 20px 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                min-height: 100dvh;
                box-sizing: border-box;
            }

            body.login-page .login-box {
                padding: 24px 16px;
                border-radius: 16px;
                max-width: 100%;
                width: 100%;
                margin: auto 0;
            }

            .login-heading {
                font-size: 1.1rem;
                margin-bottom: 16px;
            }

            .input-group {
                margin-bottom: 14px;
            }

            .input-group input {
                height: 48px;
                font-size: 13.5px;
            }

            .pwd-guide-box {
                padding: 8px 12px;
                margin-bottom: 14px;
            }

            .pwd-requirement {
                font-size: 11px;
            }

            /* OTP Verification Screen — fully visible & centered on mobile */
            .otp-verify-screen {
                width: 100%;
                padding: 0;
                overflow: hidden;
            }

            .sv-anim {
                width: 62px;
                height: 62px;
                margin-bottom: 12px;
            }

            .sv-anim .sv-icon {
                font-size: 20px;
            }

            .sv-title {
                font-size: 1.05rem;
                margin-bottom: 4px;
            }

            .sv-sub {
                font-size: 0.75rem;
                margin-bottom: 8px;
                max-width: 100%;
                padding: 0 4px;
            }

            .sv-chip {
                font-size: 0.7rem;
                padding: 4px 10px;
                margin-bottom: 12px;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .otp-boxes-row {
                gap: 5px;
                margin: 8px 0 14px;
                padding: 0 2px;
                width: 100%;
            }

            .otp-box-digit {
                height: 44px;
                font-size: 18px;
                border-radius: 8px;
                min-width: 0;
            }

            .sv-bar-wrap {
                width: 100%;
            }

            .verify-otp-btn {
                padding: 12px 14px;
                font-size: 13px;
                margin-bottom: 12px;
                width: 100%;
            }

            .resend-row {
                font-size: 0.7rem;
                flex-wrap: wrap;
                justify-content: center;
                gap: 6px;
            }

            .resend-btn, .back-btn {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 360px) {
            body.login-page .login-box {
                padding: 20px 12px;
                border-radius: 14px;
            }

            .otp-boxes-row {
                gap: 4px;
            }

            .otp-box-digit {
                height: 40px;
                font-size: 15px;
                border-radius: 6px;
            }

            .sv-chip {
                font-size: 0.65rem;
            }
        }

        /* ── Top Custom Notification / Confirmation Card (Matches index.php Clear Chat Modal) ── */
        .clear-chat-confirm-modal {
            position: fixed;
            top: 20px;
            right: 20px;
            left: auto;
            z-index: 100005;
            pointer-events: none;
            opacity: 0;
            transform: translate(50px, -50px) scale(0.9);
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
        }

        .clear-chat-confirm-modal.show {
            opacity: 1;
            pointer-events: auto;
            transform: translate(0, 0) scale(1);
        }

        @media (max-width: 768px) {
            .clear-chat-confirm-modal {
                top: 20px;
                left: 50%;
                right: auto;
                transform: translate(-50%, -50px) scale(0.9);
                width: calc(100% - 32px);
                max-width: 360px;
                display: flex;
                justify-content: center;
            }

            .clear-chat-confirm-modal.show {
                transform: translate(-50%, 0) scale(1);
            }
        }

        .clear-chat-confirm-card {
            background: linear-gradient(145deg, #242424, #161616);
            border: 1.5px solid rgba(255, 215, 0, 0.45);
            border-radius: 14px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(255, 215, 0, 0.15);
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            width: 100%;
            max-width: 350px;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            color: #fff;
            box-sizing: border-box;
        }

        .clear-chat-confirm-card.error-card {
            border-color: rgba(239, 68, 68, 0.55);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(239, 68, 68, 0.25);
        }

        .clear-chat-confirm-card.warning-card {
            border-color: rgba(245, 158, 11, 0.55);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(245, 158, 11, 0.25);
        }

        .clear-chat-confirm-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.15);
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .clear-chat-confirm-card.error-card .clear-chat-confirm-icon {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(239, 68, 68, 0.5);
            color: #ef4444;
        }

        .clear-chat-confirm-card.warning-card .clear-chat-confirm-icon {
            background: rgba(245, 158, 11, 0.18);
            border-color: rgba(245, 158, 11, 0.5);
            color: #f59e0b;
        }

        .clear-chat-confirm-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .clear-chat-confirm-title {
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.3;
        }

        .clear-chat-confirm-desc {
            color: #bbbbbb;
            font-size: 11.5px;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.4;
            word-break: break-word;
        }

        /* ── Legal Terms & Privacy Modal ────────────── */
        .legal-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.82);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            padding: 20px 16px;
            animation: modalFadeIn 0.25s ease;
        }

        .legal-modal-overlay.active {
            display: flex;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .legal-modal-card {
            background: #181818;
            border: 1px solid rgba(255, 215, 0, 0.35);
            border-radius: 18px;
            width: 100%;
            max-width: 580px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.9), 0 0 30px rgba(255, 215, 0, 0.15);
            animation: modalSlideUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
        }

        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .legal-modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(255, 215, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 215, 0, 0.03);
        }

        .legal-modal-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .legal-modal-header h3 i {
            color: #ffd700;
        }

        .legal-modal-close {
            background: none;
            border: none;
            color: #888888;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
            transition: color 0.2s ease;
        }

        .legal-modal-close:hover {
            color: #ffd700;
        }

        .legal-modal-body {
            padding: 20px 24px;
            overflow-y: auto;
            color: #cccccc;
            font-size: 0.9rem;
            line-height: 1.65;
        }

        .legal-modal-body h4 {
            color: #ffd700;
            font-size: 0.96rem;
            margin: 16px 0 6px;
            font-weight: 600;
        }

        .legal-modal-body h4:first-child {
            margin-top: 0;
        }

        .legal-modal-body p {
            margin-bottom: 12px;
        }

        .legal-modal-body ul {
            padding-left: 20px;
            margin-bottom: 14px;
        }

        .legal-modal-body ul li {
            margin-bottom: 6px;
        }

        .legal-modal-footer {
            padding: 14px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.2);
            gap: 10px;
        }

        .legal-modal-footer a {
            color: #ffd700;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
        }

        .legal-modal-footer a:hover {
            text-decoration: underline;
        }

        .legal-agree-btn {
            background: linear-gradient(135deg, #b8960c, #ffd700);
            color: #000;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s ease;
        }

        .legal-agree-btn:hover {
            background: linear-gradient(135deg, #c9aa2e, #ffe033);
            transform: translateY(-1px);
        }

        @media (max-width: 480px) {
            .legal-modal-overlay {
                padding: 12px;
            }

            .legal-modal-card {
                max-height: 90vh;
                max-height: 90dvh;
                border-radius: 14px;
            }

            .legal-modal-header {
                padding: 14px 18px;
            }

            .legal-modal-header h3 {
                font-size: 1.02rem;
            }

            .legal-modal-body {
                padding: 14px 18px;
                font-size: 0.84rem;
                line-height: 1.6;
            }

            .legal-modal-body h4 {
                font-size: 0.88rem;
                margin: 12px 0 4px;
            }

            .legal-modal-body p {
                margin-bottom: 8px;
            }

            .legal-modal-body ul {
                padding-left: 16px;
                margin-bottom: 10px;
            }

            .legal-modal-footer {
                padding: 12px 18px;
                gap: 8px;
            }

            .legal-modal-footer a {
                font-size: 0.78rem;
            }

            .legal-agree-btn {
                padding: 8px 14px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 360px) {
            .legal-modal-header {
                padding: 12px 14px;
            }

            .legal-modal-body {
                padding: 12px 14px;
                font-size: 0.8rem;
            }

            .legal-modal-footer {
                padding: 10px 14px;
            }
        }
    </style>
</head>
<body class="login-page">
<div class="login-layout">

    <!-- Left Panel: branding (desktop only) -->
    <div class="login-left">
        <div class="login-left-logo-wrap">
            <a href="../index.php" title="ClosetGo">
                <img src="../images/icon/JMB.png" alt="ClosetGo Logo" class="brand-panel-logo" onerror="this.src='../images/icon/profile_logo.png'">
            </a>
        </div>
        <p class="brand-eyebrow">✦ Join the Community</p>
        <h1 class="brand-headline">Create your<br><span>ClosetGo</span><br>account</h1>
        <p class="brand-sub">Sign up for free and start managing your wardrobe, discover curated fashion, and reserve your favourite pieces today.</p>
        <div class="brand-badges">
            <span class="brand-badge"><i class="fas fa-check"></i> Free to Join</span>
            <span class="brand-badge"><i class="fas fa-bolt"></i> Instant Access</span>
            <span class="brand-badge"><i class="fas fa-shield-alt"></i> Secure Sign-up</span>
        </div>
    </div>

    <!-- Right Panel: sign-up form & OTP verification -->
    <div class="login-right">
        <div class="login-box">
            <div class="login-logo-wrap">
                <a href="../index.php" title="ClosetGo">
                    <img src="../images/icon/JMB.png" alt="ClosetGo Logo" class="login-brand-logo" onerror="this.src='../images/icon/profile_logo.png'">
                </a>
            </div>

            <!-- ── Step 1: Sign Up Form ── -->
            <div class="login-form-inner" id="signUpFormInner">
                <h2 class="login-heading">Create an account</h2>
                <form id="signUpForm" onsubmit="handleNext(event)">
                    <div id="emailPasswordSection">
                        <div class="input-group">
                            <input type="email" id="emailInput" placeholder=" " required autocomplete="email" oninput="validatePasswordLive()">
                            <label for="emailInput">Email Address</label>
                        </div>
                        <div class="input-group">
                            <input type="password" id="passwordInput" placeholder=" " required autocomplete="new-password" minlength="8" oninput="validatePasswordLive()">
                            <label for="passwordInput">Password</label>
                            <img src="../images/icon/showpassword.png" alt="Show Password" class="password-toggle" onclick="togglePassword('passwordInput', this)">
                        </div>

                        <div class="input-group" style="margin-bottom: 14px;">
                            <input type="password" id="confirmPasswordInput" placeholder=" " required autocomplete="new-password" minlength="8" oninput="validatePasswordLive()">
                            <label for="confirmPasswordInput">Confirm Password</label>
                            <img src="../images/icon/showpassword.png" alt="Show Password" class="password-toggle" onclick="togglePassword('confirmPasswordInput', this)">
                        </div>

                        <!-- Password Requirement Guide Under Confirm Password -->
                        <div class="pwd-guide-box" id="pwdGuideBox">
                            <div class="pwd-strength-bar-wrap">
                                <div class="pwd-strength-bar" id="pwdStrengthBar"></div>
                            </div>
                            <div class="pwd-requirement" id="reqLength">
                                <i class="fas fa-circle"></i> Must be at least 8 characters
                            </div>
                            <div class="pwd-requirement" id="reqCapital">
                                <i class="fas fa-circle"></i> First letter must be uppercase
                            </div>
                            <div class="pwd-requirement" id="reqSymbol">
                                <i class="fas fa-circle"></i> Must contain at least 1 symbol (!@#$%^&*)
                            </div>
                            <div class="pwd-requirement" id="reqMatch">
                                <i class="fas fa-circle"></i> Passwords must match
                            </div>
                        </div>
                    </div>

                    <!-- Terms & Privacy Checkbox -->
                    <div class="terms-group">
                        <input type="checkbox" id="termsCheckbox" required onchange="validatePasswordLive()">
                        <label for="termsCheckbox">
                            I agree to the <a href="terms.php" onclick="openLegalModal('termsModal', event)">Terms &amp; Conditions</a>
                            and <a href="privacy.php" onclick="openLegalModal('privacyModal', event)">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="login-btn" id="nextBtn" disabled style="opacity: 0.5; cursor: not-allowed;">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </button>
                    <div class="signup-text">
                        Already have an account? <a href="login.php" class="signup-link">Login</a>
                    </div>
                </form>
            </div>

            <!-- ── Step 2: OTP Verification Screen ── -->
            <div class="otp-verify-screen" id="otpVerifyScreen">
                <div class="sv-anim">
                    <div class="sv-ring"></div>
                    <div class="sv-ring"></div>
                    <div class="sv-ring"></div>
                    <div class="sv-icon"><i class="fas fa-envelope-open-text"></i></div>
                </div>

                <h3 class="sv-title">Verify Your Email</h3>
                <p class="sv-sub">We've sent a 6-digit verification code to</p>
                <div class="sv-chip">
                    <i class="fas fa-envelope" style="font-size:0.72rem"></i>
                    <span id="otpEmailDisplay"></span>
                </div>

                <!-- Generated OTP Display (shown when email sending is disabled) -->
                <div id="generatedOTPDisplay" style="display:none; background: rgba(255,215,0,0.1); border: 1px solid rgba(255,215,0,0.3); border-radius: 8px; padding: 10px; margin: 10px 0; text-align: center; color: #ffd700; font-size: 0.85rem; font-weight: 600;"></div>

                <!-- 6 Digit Input Row -->
                <div class="otp-boxes-row" id="otpBoxesContainer">
                    <input type="text" class="otp-box-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                </div>

                <div class="sv-bar-wrap">
                    <div class="sv-bar" id="otpProgressBar"></div>
                </div>
                <div class="sv-expire">Code expires in: <strong id="otpTimerDisplay">10:00</strong></div>

                <!-- Verify Button -->
                <button type="button" class="verify-otp-btn" id="verifyOtpBtn" onclick="verifyOTP()">
                    <i class="fas fa-check-circle"></i> Verify &amp; Create Account
                </button>

                <!-- Actions: Back & Resend -->
                <div class="resend-row">
                    <button type="button" class="back-btn" onclick="backToSignUp()">
                        <i class="fas fa-arrow-left"></i> Change email
                    </button>
                    <button type="button" class="resend-btn" id="resendOtpBtn" onclick="resendOTP()">
                        <i class="fas fa-redo"></i> Resend code
                    </button>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- Top Custom Notification / Confirmation Card (Matches index.php Clear Chat Confirmation Card) -->
<div id="notificationToast" class="clear-chat-confirm-modal">
    <div class="clear-chat-confirm-card" id="notificationToastCard">
        <div class="clear-chat-confirm-icon" id="notificationIcon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="clear-chat-confirm-body">
            <div class="clear-chat-confirm-title" id="notificationTitle">Success</div>
            <div class="clear-chat-confirm-desc" id="notificationMessage">Your action completed successfully.</div>
        </div>
    </div>
</div>

<script>
// Audio feedback
const notifAudio = new Audio('../ringtone/notification.MP3');
notifAudio.volume = 0.5;
const clickAudio = new Audio('../ringtone/button_click.MP3');
clickAudio.volume = 0.3;

function playSound(audio) {
    audio.currentTime = 0;
    audio.play().catch(e => console.log('Audio error:', e));
}

// Show notification toast (Matches index.php Clear Chat Modal Card)
function showNotification(message, type = 'success', duration = 3500) {
    if (typeof type === 'number') {
        duration = type;
        type = 'success';
    }
    const modal = document.getElementById('notificationToast');
    const card = document.getElementById('notificationToastCard');
    const titleEl = document.getElementById('notificationTitle');
    const msgEl = document.getElementById('notificationMessage');
    const iconEl = document.getElementById('notificationIcon');

    if (!modal || !msgEl) return;

    msgEl.textContent = message;

    const isError = type === 'error' || /fail|error|incorrect|invalid|not found|please enter|missing|already exists|expired|must be|do not match|connection error/i.test(message);
    const isWarning = type === 'warning' || /agree|wait|checking|terms/i.test(message);

    if (card) card.className = 'clear-chat-confirm-card';

    if (isError) {
        if (card) card.classList.add('error-card');
        if (titleEl) titleEl.textContent = 'Action Required';
        if (iconEl) iconEl.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
    } else if (isWarning) {
        if (card) card.classList.add('warning-card');
        if (titleEl) titleEl.textContent = 'Notice';
        if (iconEl) iconEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
    } else {
        if (titleEl) titleEl.textContent = 'Success';
        if (iconEl) iconEl.innerHTML = '<i class="fas fa-check-circle"></i>';
    }

    playSound(notifAudio);

    modal.classList.add('show');

    clearTimeout(modal.hideTimeout);
    modal.hideTimeout = setTimeout(() => {
        modal.classList.remove('show');
    }, duration);
}

// Toggle password visibility
function togglePassword(inputId, toggleIcon) {
    const passwordInput = document.getElementById(inputId);
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.src = '../images/icon/hidepassword.png';
        toggleIcon.alt = 'Hide Password';
    } else {
        passwordInput.type = 'password';
        toggleIcon.src = '../images/icon/showpassword.png';
        toggleIcon.alt = 'Show Password';
    }
}

// Real-time live password requirement validation & strength meter
function validatePasswordLive() {
    const password = document.getElementById('passwordInput').value;
    const confirmPassword = document.getElementById('confirmPasswordInput').value;
    const reqLength = document.getElementById('reqLength');
    const reqCapital = document.getElementById('reqCapital');
    const reqSymbol = document.getElementById('reqSymbol');
    const reqMatch = document.getElementById('reqMatch');
    const bar = document.getElementById('pwdStrengthBar');
    const nextBtn = document.getElementById('nextBtn');

    // Track if all requirements are met
    let lengthValid = false;
    let capitalValid = false;
    let symbolValid = false;
    let matchValid = false;

    // 1. Length check (8+ characters)
    if (password.length >= 8) {
        reqLength.className = 'pwd-requirement valid';
        reqLength.innerHTML = '<i class="fas fa-check-circle"></i> Must be at least 8 characters';
        lengthValid = true;
    } else if (password.length > 0) {
        reqLength.className = 'pwd-requirement invalid';
        reqLength.innerHTML = '<i class="fas fa-times-circle"></i> Must be at least 8 characters (' + password.length + '/8)';
    } else {
        reqLength.className = 'pwd-requirement';
        reqLength.innerHTML = '<i class="fas fa-circle"></i> Must be at least 8 characters';
    }

    // 2. First letter uppercase check
    if (password.length > 0 && /^[A-Z]/.test(password.charAt(0))) {
        reqCapital.className = 'pwd-requirement valid';
        reqCapital.innerHTML = '<i class="fas fa-check-circle"></i> First letter must be uppercase';
        capitalValid = true;
    } else if (password.length > 0) {
        reqCapital.className = 'pwd-requirement invalid';
        reqCapital.innerHTML = '<i class="fas fa-times-circle"></i> First letter must be uppercase';
    } else {
        reqCapital.className = 'pwd-requirement';
        reqCapital.innerHTML = '<i class="fas fa-circle"></i> First letter must be uppercase';
    }

    // 3. Symbol check
    if (/[!@#$%^&*]/.test(password)) {
        reqSymbol.className = 'pwd-requirement valid';
        reqSymbol.innerHTML = '<i class="fas fa-check-circle"></i> Must contain at least 1 symbol (!@#$%^&*)';
        symbolValid = true;
    } else if (password.length > 0) {
        reqSymbol.className = 'pwd-requirement invalid';
        reqSymbol.innerHTML = '<i class="fas fa-times-circle"></i> Must contain at least 1 symbol (!@#$%^&*)';
    } else {
        reqSymbol.className = 'pwd-requirement';
        reqSymbol.innerHTML = '<i class="fas fa-circle"></i> Must contain at least 1 symbol (!@#$%^&*)';
    }

    // 4. Strength bar calculation
    let strengthScore = 0;
    if (password.length >= 8) strengthScore += 1;
    if (password.length >= 10) strengthScore += 1;
    if (/[A-Z]/.test(password)) strengthScore += 1;
    if (/[0-9]/.test(password)) strengthScore += 1;
    if (/[^A-Za-z0-9]/.test(password)) strengthScore += 1;

    if (password.length === 0) {
        bar.className = 'pwd-strength-bar';
        bar.style.width = '0%';
    } else if (strengthScore <= 2) {
        bar.className = 'pwd-strength-bar weak';
        bar.style.width = '33%';
    } else if (strengthScore <= 3) {
        bar.className = 'pwd-strength-bar medium';
        bar.style.width = '66%';
    } else {
        bar.className = 'pwd-strength-bar strong';
        bar.style.width = '100%';
    }

    // 5. Confirm password match check
    if (confirmPassword.length > 0) {
        if (password === confirmPassword && password.length >= 8 && /^[A-Z]/.test(password.charAt(0)) && /[!@#$%^&*]/.test(password)) {
            reqMatch.className = 'pwd-requirement valid';
            reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            matchValid = true;
        } else {
            reqMatch.className = 'pwd-requirement invalid';
            reqMatch.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
        }
    } else {
        reqMatch.className = 'pwd-requirement';
        reqMatch.innerHTML = '<i class="fas fa-circle"></i> Passwords must match';
    }

    // Enable/disable submit button based on all requirements
    if (nextBtn) {
        const emailInput = document.getElementById('emailInput');
        const termsCheckbox = document.getElementById('termsCheckbox');
        let emailValid = false;
        if (emailInput && emailInput.value.trim() !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            emailValid = emailRegex.test(emailInput.value.trim());
        }
        const termsChecked = termsCheckbox ? termsCheckbox.checked : false;
        const allValid = lengthValid && capitalValid && symbolValid && matchValid && termsChecked && emailValid;
        nextBtn.disabled = !allValid;
        if (allValid) {
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        } else {
            nextBtn.style.opacity = '0.5';
            nextBtn.style.cursor = 'not-allowed';
        }
    }
}

// Handle Form Submission -> Checks email availability -> Sends OTP
function handleNext(event) {
    event.preventDefault();
    event.stopPropagation();
    playSound(clickAudio);

    const emailInput = document.getElementById('emailInput');
    const passwordInput = document.getElementById('passwordInput');
    const confirmPasswordInput = document.getElementById('confirmPasswordInput');
    const termsCheckbox = document.getElementById('termsCheckbox');
    const nextBtn = document.getElementById('nextBtn');

    const email = emailInput.value.trim();
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!termsCheckbox.checked) {
        showNotification('Please agree to the Terms & Conditions and Privacy Policy', 'error');
        return false;
    }

    if (!email || !password || !confirmPassword) {
        showNotification('Please fill in all fields', 'error');
        return false;
    }

    if (!emailRegex.test(email)) {
        showNotification('Please enter a valid email address', 'error');
        return false;
    }

    if (password.length < 8) {
        showNotification('Password must be at least 8 characters long', 'error');
        passwordInput.focus();
        return false;
    }

    if (!/^[A-Z]/.test(password.charAt(0))) {
        showNotification('Password must start with an uppercase letter', 'error');
        passwordInput.focus();
        return false;
    }

    if (!/[!@#$%^&*]/.test(password)) {
        showNotification('Password must contain at least 1 symbol (!@#$%^&*)', 'error');
        passwordInput.focus();
        return false;
    }

    if (password !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        confirmPasswordInput.focus();
        return false;
    }

    // Disable button while checking
    nextBtn.disabled = true;
    nextBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking email...';

    // Step 1: Check if email already exists
    fetch('sign_up.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=check_email&email=${encodeURIComponent(email)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Save state in localStorage
            localStorage.setItem('signupEmail', email);
            localStorage.setItem('signupPassword', password);
            // Send OTP
            sendOTP(email);
        } else {
            nextBtn.disabled = false;
            nextBtn.innerHTML = '<i class="fas fa-user-plus"></i> Sign Up';
            showNotification(data.message || 'Email already exists', 'error');
        }
    })
    .catch(err => {
        console.error('Check email error:', err);
        nextBtn.disabled = false;
        nextBtn.innerHTML = '<i class="fas fa-user-plus"></i> Sign Up';
        showNotification('Connection error. Please try again.', 'error');
    });
}

let countdownTimer = null;
const OTP_EXPIRY_SECONDS = 600; // 10 minutes

// Send OTP function - sends OTP via email
function sendOTP(email) {
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';
    }

    // Send OTP via server
    const formData = new FormData();
    formData.append('action', 'send_signup_otp');
    formData.append('email', email);

    fetch('sign_up.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Store OTP from server response
            const otp = data.otp;
            localStorage.setItem('generatedOTP', otp);
            localStorage.setItem('otpEmail', email);
            localStorage.setItem('otpTimestamp', Date.now());

            if (nextBtn) {
                nextBtn.disabled = false;
                nextBtn.innerHTML = '<i class="fas fa-user-plus"></i> Sign Up';
            }

            // Show OTP screen
            showOTPScreen(email);
            showNotification('Verification code sent to your email. Please check your inbox.');
        } else {
            if (nextBtn) {
                nextBtn.disabled = false;
                nextBtn.innerHTML = '<i class="fas fa-user-plus"></i> Sign Up';
            }
            showNotification(data.message || 'Failed to send OTP. Please try again.', 'error');
        }
    })
    .catch(err => {
        console.error('Send OTP error:', err);
        if (nextBtn) {
            nextBtn.disabled = false;
            nextBtn.innerHTML = '<i class="fas fa-user-plus"></i> Sign Up';
        }
        showNotification('Connection error. Please try again.', 'error');
    });
}

// Transition to OTP verification screen
function showOTPScreen(email) {
    document.getElementById('signUpFormInner').classList.add('hidden');
    const otpScreen = document.getElementById('otpVerifyScreen');
    otpScreen.classList.add('active');
    document.getElementById('otpEmailDisplay').textContent = email;

    // Hide the generated OTP display since email is now being sent
    const otpDisplayElement = document.getElementById('generatedOTPDisplay');
    if (otpDisplayElement) {
        otpDisplayElement.style.display = 'none';
    }

    // Reset and focus OTP input boxes
    const inputs = document.querySelectorAll('.otp-box-digit');
    inputs.forEach(input => {
        input.value = '';
        input.classList.remove('filled', 'error');
    });
    if (inputs[0]) inputs[0].focus();

    // Start expiry timer
    startOTPTimer();
}

// Return back to editing signup details
function backToSignUp() {
    playSound(clickAudio);
    clearInterval(countdownTimer);
    document.getElementById('otpVerifyScreen').classList.remove('active');
    document.getElementById('signUpFormInner').classList.remove('hidden');
}

// Timer countdown logic
function startOTPTimer() {
    clearInterval(countdownTimer);
    let remaining = OTP_EXPIRY_SECONDS;
    const timerDisplay = document.getElementById('otpTimerDisplay');
    const progressBar = document.getElementById('otpProgressBar');

    function update() {
        const mins = String(Math.floor(remaining / 60)).padStart(2, '0');
        const secs = String(remaining % 60).padStart(2, '0');
        timerDisplay.textContent = `${mins}:${secs}`;
        const pct = (remaining / OTP_EXPIRY_SECONDS) * 100;
        progressBar.style.width = `${pct}%`;

        if (remaining <= 0) {
            clearInterval(countdownTimer);
            timerDisplay.textContent = 'Expired';
            progressBar.style.width = '0%';
            showNotification('Verification code expired. Please request a new code.', 'error');
        }
        remaining--;
    }

    update();
    countdownTimer = setInterval(update, 1000);
}

// Setup 6-Box OTP input events
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('.otp-box-digit');

    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const val = e.target.value.replace(/\D/g, '');
            e.target.value = val ? val[val.length - 1] : '';

            if (e.target.value) {
                e.target.classList.add('filled');
                e.target.classList.remove('error');
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                } else {
                    // Auto-verify if all filled
                    checkAndAutoVerify();
                }
            } else {
                e.target.classList.remove('filled');
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
                inputs[index - 1].value = '';
                inputs[index - 1].classList.remove('filled');
            } else if (e.key === 'Enter') {
                e.preventDefault();
                verifyOTP();
            }
        });

        // Handle Paste of complete 6-digit OTP
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').trim().replace(/\D/g, '');
            if (pasteData.length > 0) {
                const digits = pasteData.slice(0, 6).split('');
                digits.forEach((digit, i) => {
                    if (inputs[i]) {
                        inputs[i].value = digit;
                        inputs[i].classList.add('filled');
                        inputs[i].classList.remove('error');
                    }
                });
                const nextFocus = Math.min(digits.length, inputs.length - 1);
                inputs[nextFocus].focus();
                if (digits.length === 6) {
                    verifyOTP();
                }
            }
        });
    });
});

function checkAndAutoVerify() {
    const inputs = document.querySelectorAll('.otp-box-digit');
    let fullCode = '';
    inputs.forEach(input => fullCode += input.value);
    if (fullCode.length === 6) {
        verifyOTP();
    }
}

// Verify OTP
function verifyOTP() {
    playSound(clickAudio);
    const inputs = document.querySelectorAll('.otp-box-digit');
    let enteredOTP = '';
    inputs.forEach(input => enteredOTP += input.value.trim());

    if (enteredOTP.length < 6) {
        showNotification('Please enter all 6 digits of your verification code', 'error');
        inputs.forEach(inp => { if (!inp.value) inp.classList.add('error'); });
        return;
    }

    const storedOTP = localStorage.getItem('generatedOTP');
    const otpTimestamp = localStorage.getItem('otpTimestamp');
    const currentTime = Date.now();

    // Check if OTP is expired (10 minutes)
    if (currentTime - parseInt(otpTimestamp || '0') > OTP_EXPIRY_SECONDS * 1000) {
        showNotification('OTP has expired. Please click Resend Code.', 'error');
        inputs.forEach(inp => inp.classList.add('error'));
        return;
    }

    const verifyBtn = document.getElementById('verifyOtpBtn');

    if (enteredOTP === storedOTP) {
        // OTP matches!
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';

        const email = localStorage.getItem('signupEmail');
        const password = localStorage.getItem('signupPassword');

        if (email && password) {
            saveEmailPasswordToDatabase(email, password);
        } else {
            showNotification('Session missing. Please start sign up again.', 'error');
            setTimeout(backToSignUp, 1500);
        }
    } else {
        showNotification('Incorrect verification code. Please try again.', 'error');
        inputs.forEach(inp => {
            inp.classList.add('error');
            inp.value = '';
            inp.classList.remove('filled');
        });
        if (inputs[0]) inputs[0].focus();
    }
}

// Save verified user in Database via sign_up.php action handler
function saveEmailPasswordToDatabase(email, password) {
    const formData = new FormData();
    formData.append('action', 'save_email');
    formData.append('email', email);
    formData.append('password', password);

    fetch('sign_up.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Save non-JSON response:', text);
            const cleanErr = text ? text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 100) : 'Server response error';
            data = { success: false, message: cleanErr };
        }

        if (data.success) {
            // Save credentials in sessionStorage for auto-fill in login.php
            sessionStorage.setItem('prefill_email', email);
            sessionStorage.setItem('prefill_password', password);
            sessionStorage.setItem('from_signup', 'true');

            // Clean localStorage sensitive temporary data
            localStorage.removeItem('generatedOTP');
            localStorage.removeItem('signupPassword');
            localStorage.removeItem('signupEmail');
            localStorage.removeItem('otpEmail');
            localStorage.removeItem('otpTimestamp');

            showNotification('Account verified & created! Redirecting to login...');
            
            // Redirect to login page
            setTimeout(() => {
                window.location.href = 'login.php?signup=success';
            }, 1800);
        } else {
            const verifyBtn = document.getElementById('verifyOtpBtn');
            if (verifyBtn) {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify &amp; Create Account';
            }
            showNotification(data.message || 'Failed to create account. Please try again.', 'error');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        const verifyBtn = document.getElementById('verifyOtpBtn');
        if (verifyBtn) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify &amp; Create Account';
        }
        showNotification(err && err.message ? err.message : 'Database error. Please try again.', 'error');
    });
}

// Resend OTP with cooldown
function resendOTP() {
    playSound(clickAudio);
    const email = localStorage.getItem('otpEmail');
    const resendBtn = document.getElementById('resendOtpBtn');

    if (!email) {
        showNotification('Email not found. Please re-enter your details.', 'error');
        backToSignUp();
        return;
    }

    resendBtn.disabled = true;
    let cooldown = 30;
    resendBtn.innerHTML = `<i class="fas fa-clock"></i> Wait ${cooldown}s`;

    const interval = setInterval(() => {
        cooldown--;
        if (cooldown > 0) {
            resendBtn.innerHTML = `<i class="fas fa-clock"></i> Wait ${cooldown}s`;
        } else {
            clearInterval(interval);
            resendBtn.disabled = false;
            resendBtn.innerHTML = '<i class="fas fa-redo"></i> Resend code';
        }
    }, 1000);

    sendOTP(email);
}

// Open Legal Modal (Terms or Privacy)
function openLegalModal(modalId, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// Close Legal Modal
function closeLegalModal(modalId, agree = false) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    if (agree) {
        const checkbox = document.getElementById('termsCheckbox');
        if (checkbox) checkbox.checked = true;
    }
}

// Close modal when clicking outside of the card
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('legal-modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});
</script>

<!-- Terms & Conditions Modal -->
<div class="legal-modal-overlay" id="termsModal">
    <div class="legal-modal-card">
        <div class="legal-modal-header">
            <h3><i class="fas fa-file-contract"></i> Terms &amp; Conditions</h3>
            <button type="button" class="legal-modal-close" onclick="closeLegalModal('termsModal')">&times;</button>
        </div>
        <div class="legal-modal-body">
            <h4>1. Acceptance of Terms</h4>
            <p>By creating an account, reserving, renting, or purchasing items on the ClosetGo platform, you acknowledge that you have read, understood, and agreed to be bound by these Terms and Conditions and our Privacy Policy.</p>
            
            <h4>2. User Account &amp; Identity Verification</h4>
            <p>To reserve or rent wardrobe pieces and gowns, you must register for an account and provide accurate contact details and a valid ID for identity verification.</p>

            <h4>3. Strict Non-Refundable Policy</h4>
            <p style="color: #f87171; font-weight: 600;">All reservation fees, downpayments, and rental payments are strictly non-refundable once submitted and confirmed.</p>
            <ul>
                <li>Cancellations or no-shows do not qualify for any refund of reservation or rental fees.</li>
                <li>Purchased items (Buy orders) are final sales and cannot be returned, exchanged, or refunded.</li>
            </ul>

            <h4>4. Security Deposits &amp; Overdue Fees</h4>
            <ul>
                <li>Security deposits (when collected) are refundable upon the safe, timely, and undamaged return of the rental items.</li>
                <li>Late returns incur daily overdue penalty fees past the agreed return schedule.</li>
            </ul>

            <h4>5. Lost or Damaged Item Settlement</h4>
            <p>Borrowers are responsible for the garment during the rental period. As per policy, if an item is reported lost, settlement is calculated from the item selling price less rental already paid plus overdue fees. Tears, stains, or unauthorized alterations incur repair or replacement charges.</p>
        </div>
        <div class="legal-modal-footer" style="justify-content: flex-end;">
            <button type="button" class="legal-agree-btn" onclick="closeLegalModal('termsModal', true)">
                <i class="fas fa-check"></i> I Agree
            </button>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal -->
<div class="legal-modal-overlay" id="privacyModal">
    <div class="legal-modal-card">
        <div class="legal-modal-header">
            <h3><i class="fas fa-user-shield"></i> Privacy Policy</h3>
            <button type="button" class="legal-modal-close" onclick="closeLegalModal('privacyModal')">&times;</button>
        </div>
        <div class="legal-modal-body">
            <h4>1. Information We Collect</h4>
            <p>We collect your name, email address, contact phone number, valid ID upload (strictly for rental security and identity verification), proof of payment receipts (e.g. GCash transaction screenshots), and reservation dates.</p>

            <h4>2. How We Use Your Data</h4>
            <ul>
                <li>Account authentication and two-step OTP email verification.</li>
                <li>Verifying customer identity and confirming wardrobe reservations.</li>
                <li>Validating payment transaction receipts and order status.</li>
                <li>Sending order confirmations, return reminders, and security notices.</li>
            </ul>

            <h4>3. Password &amp; Document Security</h4>
            <p>Passwords are encrypted using secure BCRYPT hashing and never stored in plain text. Verification OTP codes expire after 10 minutes. Uploaded IDs and payment proofs are strictly stored and accessed only by authorized shop administrators and staff.</p>

            <h4>4. Zero Commercial Third-Party Sharing</h4>
            <p>We strictly do not sell, rent, or trade your personal data. Information is only processed for internal shop operations and essential transaction services.</p>

            <h4>5. Contact Privacy Team</h4>
            <p>For privacy inquiries or account data requests, please contact our support team at support@closetgo.com.</p>
        </div>
        <div class="legal-modal-footer" style="justify-content: flex-end;">
            <button type="button" class="legal-agree-btn" onclick="closeLegalModal('privacyModal', true)">
                <i class="fas fa-check"></i> I Understand
            </button>
        </div>
    </div>
</div>

</body>
</html>
