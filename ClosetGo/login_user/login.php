<?php
session_start();

// ─── AJAX: PIN verification (Staff or Admin) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'staff_verify_pin' || $_POST['action'] === 'verify_pin')) {
    header('Content-Type: application/json');
    $enteredPin = trim($_POST['pin'] ?? '');

    // 1. Check if pending Admin verification
    $adminId = intval($_SESSION['pending_admin_id'] ?? 0);
    if ($adminId > 0 && !empty($_SESSION['pending_admin_pin'])) {
        if (empty($enteredPin)) { echo json_encode(['success'=>false,'message'=>'Invalid PIN']); exit; }
        try {
            require_once '../database/db_config.php';
            $db = new Database(); $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT id, firstname, lastname, middlename, suffix, email, token_expiry FROM admin_users WHERE id = ?");
            $stmt->bind_param('i', $adminId); $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $r = $res->fetch_assoc();
                $storedPin = $_SESSION['pending_admin_pin'] ?? '';
                $dbExpiry  = intval($r['token_expiry'] ?? 0);
                if (!empty($storedPin) && hash_equals($storedPin, $enteredPin) && (time() <= $dbExpiry || $dbExpiry == 0)) {
                    $cl = $conn->prepare("UPDATE admin_users SET verification_token=NULL, token_expiry=NULL WHERE id=?");
                    if ($cl) { $cl->bind_param('i', $adminId); $cl->execute(); }

                    $adminName = trim($r['firstname'] . ' ' . (!empty($r['middlename']) ? $r['middlename'] . ' ' : '') . ($r['lastname'] ?? '') . (!empty($r['suffix']) ? ' ' . $r['suffix'] : ''));
                    $_SESSION['admin_logged_in']      = true;
                    $_SESSION['admin_id']             = $r['id'];
                    $_SESSION['admin_name']           = $adminName;
                    $_SESSION['admin_email']          = $r['email'];
                    $_SESSION['admin_verified_at']    = time();
                    $_SESSION['admin_email_verified'] = true;

                    // Clear pending session to prevent re-verification
                    unset(
                        $_SESSION['pending_admin_token'],
                        $_SESSION['pending_admin_pin'],
                        $_SESSION['pending_admin_id'],
                        $_SESSION['pending_admin_name'],
                        $_SESSION['pending_admin_email'],
                        $_SESSION['pending_admin_expiry']
                    );
                    echo json_encode(['success'=>true, 'is_admin'=>true, 'redirect'=>'../admin/dashboard.php']);
                    exit;
                }
            }
            echo json_encode(['success'=>false,'message'=>'Incorrect or expired PIN.']);
        } catch(\Throwable $e) { echo json_encode(['success'=>false,'message'=>'Server error.']); }
        exit;
    }

    // 2. Check if pending Staff verification
    $staffId = intval($_SESSION['pending_staff_id'] ?? 0);
    if ($staffId > 0 && !empty($_SESSION['pending_staff_pin'])) {
        if (empty($enteredPin)) { echo json_encode(['success'=>false,'message'=>'Invalid PIN']); exit; }
        try {
            require_once '../database/db_config.php';
            $db = new Database(); $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT id, name, email, token_expiry FROM system_staffs WHERE id = ?");
            $stmt->bind_param('i', $staffId); $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $r = $res->fetch_assoc();
                $storedPin = $_SESSION['pending_staff_pin'] ?? '';
                if (!empty($storedPin) && hash_equals($storedPin, $enteredPin) && (time() <= intval($r['token_expiry']) || intval($r['token_expiry']) == 0)) {
                    $conn->prepare("UPDATE system_staffs SET verification_token=NULL,token_expiry=NULL WHERE id=?")->bind_param('i',$staffId);
                    $conn->query("UPDATE system_staffs SET verification_token=NULL,token_expiry=NULL WHERE id=$staffId");
                    $_SESSION['staff_logged_in']=$_SESSION['staff_email_verified']=true;
                    $_SESSION['staff_id']=$r['id']; $_SESSION['staff_name']=$r['name']; $_SESSION['staff_email']=$r['email'];
                    // Clear pending session to prevent re-verification
                    unset($_SESSION['pending_staff_token'],$_SESSION['pending_staff_pin'],$_SESSION['pending_staff_id'],
                          $_SESSION['pending_staff_name'],$_SESSION['pending_staff_email'],$_SESSION['pending_staff_expiry']);
                    echo json_encode(['success'=>true, 'is_staff'=>true, 'redirect'=>'../staff/index.php']);
                    exit;
                }
            }
            echo json_encode(['success'=>false,'message'=>'Incorrect or expired PIN.']);
        } catch(\Throwable $e) { echo json_encode(['success'=>false,'message'=>'Server error.']); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'No pending verification session found.']);
    exit;
}

// ─── AJAX: poll email-link verification (Staff or Admin) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'staff_check_verified' || ($_GET['action'] ?? '') === 'check_verified')) {
    header('Content-Type: application/json');
    $verified = false;
    $isAdmin = false;
    $redirect = '';
    $pendingPin = false;

    // Check Admin
    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_email_verified'])) {
        $verified = true;
        $isAdmin = true;
        $redirect = '../admin/dashboard.php';
    } elseif (!empty($_SESSION['pending_admin_id'])) {
        try {
            require_once '../database/db_config.php';
            $db = new Database(); $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT id, firstname, lastname, middlename, suffix, email, verification_token FROM admin_users WHERE id = ?");
            $stmt->bind_param('i', $_SESSION['pending_admin_id']); $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $r = $res->fetch_assoc();
                if (empty($r['verification_token'])) {
                    // Email link was clicked - auto-verify
                    $adminName = trim($r['firstname'] . ' ' . (!empty($r['middlename']) ? $r['middlename'] . ' ' : '') . ($r['lastname'] ?? '') . (!empty($r['suffix']) ? ' ' . $r['suffix'] : ''));
                    $_SESSION['admin_logged_in']      = true;
                    $_SESSION['admin_id']             = $r['id'];
                    $_SESSION['admin_name']           = $adminName;
                    $_SESSION['admin_email']          = $r['email'];
                    $_SESSION['admin_verified_at']    = time();
                    $_SESSION['admin_email_verified'] = true;
                    unset(
                        $_SESSION['pending_admin_token'],
                        $_SESSION['pending_admin_pin'],
                        $_SESSION['pending_admin_id'],
                        $_SESSION['pending_admin_name'],
                        $_SESSION['pending_admin_email'],
                        $_SESSION['pending_admin_expiry']
                    );
                    $verified = true;
                    $isAdmin = true;
                    $redirect = '../admin/dashboard.php';
                } else {
                    // Still has verification_token - waiting for email link click
                    // Check if there's a pending PIN that hasn't been verified
                    $pendingPin = !empty($_SESSION['pending_admin_pin']);
                }
            }
        } catch(\Throwable $e) {}
    }

    // Check Staff (if not admin)
    if (!$verified) {
        if (!empty($_SESSION['staff_logged_in']) && !empty($_SESSION['staff_email_verified'])) {
            $verified = true;
            $redirect = '../staff/index.php';
        } elseif (!empty($_SESSION['pending_staff_id'])) {
            try {
                require_once '../database/db_config.php';
                $db = new Database(); $conn = $db->getConnection();
                $stmt = $conn->prepare("SELECT id, name, email, verification_token FROM system_staffs WHERE id = ?");
                $stmt->bind_param('i', $_SESSION['pending_staff_id']); $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows === 1) {
                    $r = $res->fetch_assoc();
                    if (empty($r['verification_token'])) {
                        // Email link was clicked - auto-verify
                        $_SESSION['staff_logged_in']      = true;
                        $_SESSION['staff_email_verified'] = true;
                        $_SESSION['staff_id']             = $r['id'];
                        $_SESSION['staff_name']           = $r['name'];
                        $_SESSION['staff_email']          = $r['email'];
                        unset(
                            $_SESSION['pending_staff_token'],
                            $_SESSION['pending_staff_pin'],
                            $_SESSION['pending_staff_id'],
                            $_SESSION['pending_staff_name'],
                            $_SESSION['pending_staff_email'],
                            $_SESSION['pending_staff_expiry']
                        );
                        $verified = true;
                        $redirect = '../staff/index.php';
                    } else {
                        // Still has verification_token - waiting for email link click
                        // Check if there's a pending PIN that hasn't been verified
                        $pendingPin = !empty($_SESSION['pending_staff_pin']);
                    }
                }
            } catch(\Throwable $e) {}
        }
    }

    echo json_encode(['verified' => $verified, 'is_admin' => $isAdmin, 'redirect' => $redirect, 'pending_pin' => $pendingPin]);
    exit;
}

// ─── AJAX: authenticate (Admin, Staff, or User) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'authenticate') {
    header('Content-Type: application/json');
    require_once '../database/db_config.php';
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($email) || empty($password)) {
        echo json_encode(['success'=>false,'message'=>'Email and password are required']); exit;
    }
    
    // Clear any existing login sessions to prevent cross-role issues
    unset(
        $_SESSION['admin_logged_in'],
        $_SESSION['admin_id'],
        $_SESSION['admin_name'],
        $_SESSION['admin_email'],
        $_SESSION['admin_email_verified'],
        $_SESSION['staff_logged_in'],
        $_SESSION['staff_id'],
        $_SESSION['staff_name'],
        $_SESSION['staff_email'],
        $_SESSION['staff_email_verified'],
        $_SESSION['user_id'],
        $_SESSION['email']
    );
    
    try {
        $db = new Database(); $conn = $db->getConnection();

        // ── 1. Check admin_users first (Admin has verification) ──
        @$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
        @$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS token_expiry INT DEFAULT NULL");
        $adStmt = $conn->prepare("SELECT id, firstname, lastname, middlename, suffix, email, password FROM admin_users WHERE email=?");
        $adStmt->bind_param('s', $email);
        $adStmt->execute();
        $adResult = $adStmt->get_result();
        $adminEmailFound = false;
        if ($adResult && $adResult->num_rows > 0) {
            $adminEmailFound = true;
            while ($admin = $adResult->fetch_assoc()) {
                if (password_verify($password, $admin['password'])) {
                    // Admin password matched — proceed with admin verification
                    $token  = bin2hex(random_bytes(32));
                    $pin    = sprintf('%06d', mt_rand(100000, 999999));
                    $expiry = time() + 600;

                    $_SESSION['pending_admin_token']  = $token;
                    $_SESSION['pending_admin_pin']    = $pin;
                    $_SESSION['pending_admin_id']     = $admin['id'];
                    $_SESSION['pending_admin_name']   = trim($admin['firstname'] . ' ' . $admin['lastname']);
                    $_SESSION['pending_admin_email']  = $admin['email'];
                    $_SESSION['pending_admin_expiry'] = $expiry;
                    $_SESSION['admin_email_verified'] = false;

                    $up = $conn->prepare("UPDATE admin_users SET verification_token=?, token_expiry=? WHERE id=?");
                    if ($up) { $up->bind_param('sii', $token, $expiry, $admin['id']); $up->execute(); }

                    // Build verify link to admin_verify.php
                    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $dir   = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                    if ($dir === '/' || $dir === '.') $dir = '';
                    $rootDir = dirname($dir);
                    if ($rootDir === '/' || $rootDir === '.') $rootDir = '';
                    $link  = $proto . '://' . $_SERVER['HTTP_HOST'] . rtrim($rootDir, '/') . '/admin/admin_verify.php?token=' . urlencode($token) . '&admin_id=' . $admin['id'];

                    // Send email via PHPMailer
                    $sent = false; $smtpUser = 'clos3tgo@gmail.com'; $smtpPass = 'komm kdvz mjvs rysr'; $smtpHost = 'smtp.gmail.com'; $smtpPort = 587;
                    try {
                        $smRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_user','smtp_pass','smtp_host','smtp_port')");
                        if ($smRes) { while ($row = $smRes->fetch_assoc()) {
                            if ($row['setting_key'] === 'smtp_user' && $row['setting_value']) $smtpUser = $row['setting_value'];
                            if ($row['setting_key'] === 'smtp_pass' && $row['setting_value']) $smtpPass = $row['setting_value'];
                            if ($row['setting_key'] === 'smtp_host' && $row['setting_value']) $smtpHost = $row['setting_value'];
                            if ($row['setting_key'] === 'smtp_port' && $row['setting_value']) $smtpPort = intval($row['setting_value']);
                        }}
                    } catch (\Throwable $ex) {}

                    try {
                        require_once __DIR__ . '/../PHPMailer/src/Exception.php';
                        require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
                        require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP(); $mail->Host = $smtpHost; $mail->SMTPAuth = true; $mail->Username = $smtpUser; $mail->Password = $smtpPass;
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = $smtpPort;
                        $mail->CharSet = 'UTF-8'; $mail->Timeout = 7;
                        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                        $mail->setFrom($smtpUser, 'ClosetGo Admin');
                        $mail->addAddress($email, $admin['firstname']);
                        $mail->isHTML(true);
                        $mail->Subject = 'ClosetGo - Admin Login Verification';
                        $fn = htmlspecialchars($admin['firstname']); $yr = date('Y');
                        $mail->Body = '<div style="font-family:Arial,sans-serif;background:#080808;padding:32px 16px"><div style="max-width:500px;margin:0 auto;background:#111;border:1px solid rgba(255,215,0,.2);border-radius:18px;padding:36px;text-align:center"><div style="font-size:22px;font-weight:900;color:#ffd700;margin-bottom:6px">ClosetGo</div><div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:#b8960c;margin-bottom:26px">Admin Portal</div><div style="font-size:40px;margin-bottom:18px">&#x1F6E1;</div><h2 style="color:#fff;font-size:18px;margin:0 0 10px">Admin Login Verification</h2><p style="color:#888;font-size:14px;line-height:1.7;margin-bottom:22px">Hello <strong style="color:#fff">'.$fn.'</strong>,<br>Click the button below to verify and sign in to the Admin Portal:</p><a href="'.$link.'" style="display:inline-block;background:linear-gradient(135deg,#b8960c,#ffd700);color:#000;font-weight:700;font-size:15px;text-decoration:none;padding:14px 38px;border-radius:10px;margin-bottom:20px">&#x2713; Verify &amp; Sign In</a><hr style="border:none;border-top:1px solid rgba(255,255,255,.07);margin:20px 0"><p style="color:#888;font-size:13px;margin-bottom:6px">Or enter this 6-digit PIN on the login screen:</p><div style="background:#1a1a1a;border:1px dashed #ffd700;border-radius:8px;padding:12px;font-size:24px;font-weight:bold;letter-spacing:6px;color:#ffd700;margin:14px 0">'.$pin.'</div><p style="font-size:11px;color:#555;margin-top:16px">&#x23F1; Link and PIN expire in <strong style="color:#ffd700">10 minutes</strong>.</p><div style="margin-top:24px;font-size:11px;color:#333">&copy; '.$yr.' ClosetGo</div></div></div>';
                        $mail->AltBody = "Admin Verification\nHello {$admin['firstname']},\nPIN: {$pin}\nVerify: {$link}\nExpires in 10 min.";
                        $mail->send(); $sent = true;
                    } catch (\Throwable $mailEx) {
                        try { $mail->Port = 465; $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; $mail->send(); $sent = true; } catch (\Throwable $e2) {}
                    }

                    if ($sent) {
                        echo json_encode(['success' => true, 'is_admin' => true, 'redirect' => '../admin/dashboard.php', 'message' => 'Verification email sent to your admin email!']);
                    } else {
                        echo json_encode(['success' => true, 'is_admin' => true, 'fallback' => true, 'link' => $link, 'pin' => $pin, 'redirect' => '../admin/dashboard.php', 'message' => 'Email unavailable — use the PIN or link below.']);
                    }
                    exit;
                }
            }
        }

        // ── 2. Check system_staffs second (Staff has verification) ──
        @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
        @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS token_expiry INT DEFAULT NULL");
        $stStmt = $conn->prepare("SELECT id,name,email,password FROM system_staffs WHERE email=? AND is_active=1");
        $stStmt->bind_param('s',$email); $stStmt->execute();
        $stResult = $stStmt->get_result();
        $staffEmailFound = false;
        if ($stResult && $stResult->num_rows > 0) {
            $staffEmailFound = true;
            while ($staff = $stResult->fetch_assoc()) {
                if (password_verify($password, $staff['password'])) {
                    // Staff password matched — proceed with staff verification
                    $token  = bin2hex(random_bytes(32));
                    $pin    = sprintf('%06d', mt_rand(100000,999999));
                    $expiry = time() + 600;
                    $_SESSION['pending_staff_token']  = $token;
                    $_SESSION['pending_staff_pin']    = $pin;
                    $_SESSION['pending_staff_id']     = $staff['id'];
                    $_SESSION['pending_staff_name']   = $staff['name'];
                    $_SESSION['pending_staff_email']  = $staff['email'];
                    $_SESSION['pending_staff_expiry'] = $expiry;
                    $_SESSION['staff_email_verified'] = false;
                    $up = $conn->prepare("UPDATE system_staffs SET verification_token=?,token_expiry=? WHERE id=?");
                    if ($up) { $up->bind_param('sii',$token,$expiry,$staff['id']); $up->execute(); }

                    // Build verify link
                    $proto = (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
                    $dir   = str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']));
                    if($dir==='/'||$dir==='.')$dir='';
                    $link  = $proto.'://'.$_SERVER['HTTP_HOST'].rtrim($dir,'/').'/staff_verify_from_user.php?token='.urlencode($token).'&staff_id='.$staff['id'];

                    // Send email via PHPMailer
                    $sent=false; $smtpUser='clos3tgo@gmail.com'; $smtpPass='komm kdvz mjvs rysr'; $smtpHost='smtp.gmail.com'; $smtpPort=587;
                    try {
                        $smRes=$conn->query("SELECT setting_key,setting_value FROM system_settings WHERE setting_key IN ('smtp_user','smtp_pass','smtp_host','smtp_port')");
                        if($smRes){while($row=$smRes->fetch_assoc()){if($row['setting_key']==='smtp_user'&&$row['setting_value'])$smtpUser=$row['setting_value'];if($row['setting_key']==='smtp_pass'&&$row['setting_value'])$smtpPass=$row['setting_value'];}}
                    } catch(\Throwable $ex){}
                    try {
                        require_once __DIR__.'/../PHPMailer/src/Exception.php';
                        require_once __DIR__.'/../PHPMailer/src/PHPMailer.php';
                        require_once __DIR__.'/../PHPMailer/src/SMTP.php';
                        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();$mail->Host=$smtpHost;$mail->SMTPAuth=true;$mail->Username=$smtpUser;$mail->Password=$smtpPass;
                        $mail->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;$mail->Port=$smtpPort;
                        $mail->CharSet='UTF-8';$mail->Timeout=7;
                        $mail->SMTPOptions=['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
                        $mail->setFrom($smtpUser,'ClosetGo Staff');
                        $mail->addAddress($email,$staff['name']);
                        $mail->isHTML(true);
                        $mail->Subject='ClosetGo - Staff Login Verification';
                        $fn=htmlspecialchars($staff['name']); $yr=date('Y');
                        $mail->Body='<div style="font-family:Arial,sans-serif;background:#080808;padding:32px 16px"><div style="max-width:500px;margin:0 auto;background:#111;border:1px solid rgba(255,215,0,.2);border-radius:18px;padding:36px;text-align:center"><div style="font-size:22px;font-weight:900;color:#ffd700;margin-bottom:6px">ClosetGo</div><div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:#b8960c;margin-bottom:26px">Staff Portal</div><div style="font-size:40px;margin-bottom:18px">&#x1F6E1;</div><h2 style="color:#fff;font-size:18px;margin:0 0 10px">Staff Login Verification</h2><p style="color:#888;font-size:14px;line-height:1.7;margin-bottom:22px">Hello <strong style="color:#fff">'.$fn.'</strong>,<br>Click the button below to verify and sign in to the Staff Portal:</p><a href="'.$link.'" style="display:inline-block;background:linear-gradient(135deg,#b8960c,#ffd700);color:#000;font-weight:700;font-size:15px;text-decoration:none;padding:14px 38px;border-radius:10px;margin-bottom:20px">&#x2713; Verify &amp; Sign In</a><hr style="border:none;border-top:1px solid rgba(255,255,255,.07);margin:20px 0"><p style="color:#888;font-size:13px;margin-bottom:6px">Or enter this 6-digit PIN on the login screen:</p><div style="background:#1a1a1a;border:1px dashed #ffd700;border-radius:8px;padding:12px;font-size:24px;font-weight:bold;letter-spacing:6px;color:#ffd700;margin:14px 0">'.$pin.'</div><p style="font-size:11px;color:#555;margin-top:16px">&#x23F1; Link and PIN expire in <strong style="color:#ffd700">10 minutes</strong>.</p><div style="margin-top:24px;font-size:11px;color:#333">&copy; '.$yr.' ClosetGo</div></div></div>';
                        $mail->AltBody="Staff Verification\nHello {$staff['name']},\nPIN: {$pin}\nVerify: {$link}\nExpires in 10 min.";
                        $mail->send(); $sent=true;
                    } catch(\Throwable $mailEx){
                        try{$mail->Port=465;$mail->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;$mail->send();$sent=true;}catch(\Throwable $e2){}
                    }
                    if ($sent) {
                        echo json_encode(['success'=>true,'is_staff'=>true,'redirect'=>'../staff/index.php','message'=>'Verification email sent to your staff email!']);
                    } else {
                        echo json_encode(['success'=>true,'is_staff'=>true,'fallback'=>true,'link'=>$link,'pin'=>$pin,'redirect'=>'../staff/index.php','message'=>'Email unavailable — use the PIN or link below.']);
                    }
                    exit;
                }
            }
        }

        // ── 3. Fallback: check regular system_users ──
        $checkStmt = $conn->prepare("SELECT id,password FROM system_users WHERE email=?");
        $checkStmt->bind_param('s',$email); $checkStmt->execute();
        $result = $checkStmt->get_result();
        $userEmailFound = false;
        if ($result && $result->num_rows > 0) {
            $userEmailFound = true;
            while ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    // Regular user password matched
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email']   = $email;
                    echo json_encode(['success'=>true,'message'=>'Login successful!','user_id'=>$user['id'],'email'=>$email]);
                    exit;
                }
            }
        }

        // ── 4. Neither admin, staff, nor user password matched ──
        if ($adminEmailFound || $staffEmailFound || $userEmailFound) {
            echo json_encode(['success'=>false,'message'=>'Incorrect password. Please try again.']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Email not found. Please check your email or sign up.']);
        }
    } catch(\Exception $e) {
        echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
    }
    exit;
}

// ─── Helper: Send Password Reset OTP Email ─────────────────────────────────
function sendPasswordResetOtpEmail($email, $otp, $conn) {
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

    $subject = "ClosetGo - Password Reset Verification Code: $otp";
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
            <div class="subtitle">Password Reset</div>
            <div class="icon-box">&#x1F511;</div>
            <h2 class="title">Password Reset Verification</h2>
            <p class="desc">Hello,<br>We received a request to reset the password for your ClosetGo account. Use the verification code below to set a new password:</p>
            <div class="otp-box">' . htmlspecialchars($otp) . '</div>
            <p class="expiry-note">&#x23F1; This code will expire in <strong>10 minutes</strong>. If you did not request a password reset, you can safely ignore this email.</p>
            <div class="footer">&copy; ' . $year . ' ClosetGo. All rights reserved.</div>
        </div>
    </body>
    </html>';

    $altBody = "Your ClosetGo password reset code is: $otp\nThis code expires in 10 minutes.\nIf you did not request this, please ignore this email.";

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

            if ($mail->send()) {
                return true;
            }
        } catch (\Throwable $e) {
            error_log("Password reset email attempt failed on port {$cfg['port']}: " . $e->getMessage());
        }
    }

    return false;
}

// ─── AJAX: forgot password send OTP ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot_send_otp') {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    try {
        require_once '../database/db_config.php';
        $db = new Database();
        $conn = $db->getConnection();

        // Check if email exists in system_users
        $stmt = $conn->prepare("SELECT id, email FROM system_users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No account found with this email address.']);
            exit;
        }

        // Generate 6 digit OTP and 10 min expiration
        $otp    = sprintf('%06d', mt_rand(100000, 999999));
        $expiry = time() + 600;

        // Ensure columns exist in system_users
        @$conn->query("ALTER TABLE system_users ADD COLUMN IF NOT EXISTS reset_otp VARCHAR(10) DEFAULT NULL");
        @$conn->query("ALTER TABLE system_users ADD COLUMN IF NOT EXISTS reset_expiry INT DEFAULT NULL");

        $up = $conn->prepare("UPDATE system_users SET reset_otp = ?, reset_expiry = ? WHERE email = ?");
        if ($up) {
            $up->bind_param('sis', $otp, $expiry, $email);
            $up->execute();
        }

        $_SESSION['forgot_email']    = $email;
        $_SESSION['forgot_otp']      = $otp;
        $_SESSION['forgot_expiry']   = $expiry;
        $_SESSION['forgot_verified'] = false;

        $sent = sendPasswordResetOtpEmail($email, $otp, $conn);

        if ($sent) {
            echo json_encode([
                'success' => true,
                'message' => 'Verification code sent to ' . htmlspecialchars($email)
            ]);
        } else {
            // If mail server failed, provide friendly fallback
            echo json_encode([
                'success' => true,
                'fallback' => true,
                'otp' => $otp,
                'message' => 'Verification code generated: ' . $otp
            ]);
        }
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── AJAX: forgot password verify OTP ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot_verify_otp') {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    $otp   = trim($_POST['otp']   ?? '');

    if (empty($email) || empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Email and 6-digit code are required.']);
        exit;
    }

    try {
        require_once '../database/db_config.php';
        $db = new Database();
        $conn = $db->getConnection();

        $sessionMatch = (!empty($_SESSION['forgot_email']) && 
                         strcasecmp($_SESSION['forgot_email'], $email) === 0 && 
                         !empty($_SESSION['forgot_otp']) && 
                         hash_equals($_SESSION['forgot_otp'], $otp) && 
                         time() <= intval($_SESSION['forgot_expiry'] ?? 0));

        $dbMatch = false;
        $stmt = $conn->prepare("SELECT id, reset_otp, reset_expiry FROM system_users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            if ($r && !empty($r['reset_otp']) && hash_equals($r['reset_otp'], $otp) && (time() <= intval($r['reset_expiry']) || intval($r['reset_expiry']) == 0)) {
                $dbMatch = true;
            }
        }

        if ($sessionMatch || $dbMatch) {
            $_SESSION['forgot_verified']       = true;
            $_SESSION['forgot_verified_email'] = $email;
            echo json_encode(['success' => true, 'message' => 'Verification code accepted!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        }
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── AJAX: forgot password reset (new password) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'forgot_reset_password') {
    header('Content-Type: application/json');
    $email       = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $otp         = trim($_POST['otp'] ?? '');

    if (empty($email) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Email and new password are required.']);
        exit;
    }

    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    $isVerified = (!empty($_SESSION['forgot_verified']) && 
                   !empty($_SESSION['forgot_verified_email']) && 
                   strcasecmp($_SESSION['forgot_verified_email'], $email) === 0);

    try {
        require_once '../database/db_config.php';
        $db = new Database();
        $conn = $db->getConnection();

        if (!$isVerified && !empty($otp)) {
            $stmt = $conn->prepare("SELECT id, reset_otp, reset_expiry FROM system_users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $r = $stmt->get_result()->fetch_assoc();
                if ($r && !empty($r['reset_otp']) && hash_equals($r['reset_otp'], $otp) && (time() <= intval($r['reset_expiry']) || intval($r['reset_expiry']) == 0)) {
                    $isVerified = true;
                }
            }
        }

        if (!$isVerified) {
            echo json_encode(['success' => false, 'message' => 'Verification session expired. Please request a new code.']);
            exit;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $up = $conn->prepare("UPDATE system_users SET password = ?, reset_otp = NULL, reset_expiry = NULL WHERE email = ?");
        $up->bind_param('ss', $hashedPassword, $email);
        $up->execute();

        // Clear forgot session
        unset($_SESSION['forgot_email'], $_SESSION['forgot_otp'], $_SESSION['forgot_expiry'], $_SESSION['forgot_verified'], $_SESSION['forgot_verified_email']);

        echo json_encode([
            'success' => true, 
            'message' => 'Your password has been reset successfully! You can now log in.'
        ]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - ClosetGo</title>
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
            margin-top: 6px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.35);
            background: linear-gradient(135deg, #c9aa2e, #ffe033);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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

        /* ── Modern Dark & Gold Staff Verification Screen ── */
        .staff-verify-screen {
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

        .staff-verify-screen.active {
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

        .sv-dots {
            display: flex;
            gap: 6px;
            justify-content: center;
            margin-bottom: 14px;
        }

        .sv-dots span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #ffd700;
            animation: sv-dot 1.4s ease-in-out infinite;
        }

        .sv-dots span:nth-child(2) { animation-delay: 0.2s; }
        .sv-dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes sv-dot {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
            40% { transform: translateY(-6px); opacity: 1; }
        }

        /* 6-box OTP input row */
        .otp-boxes-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 10px 0 16px;
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

        /* Verify PIN Button */
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

        #svPinError {
            color: #ff6b6b;
            font-size: 0.78rem;
            margin: -6px 0 12px;
            display: none;
            text-align: center;
        }

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

        .sv-resend {
            font-size: 0.78rem;
            color: #ffd700;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
            font-family: 'Poppins', Arial, sans-serif;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .sv-resend:hover {
            color: #ffe033;
            text-decoration: underline;
        }

        .sv-resend:disabled {
            color: #555555;
            cursor: not-allowed;
            text-decoration: none;
        }

        /* ── Forgot Password Screen ── */
        .forgot-pwd-screen {
            display: none;
            width: 100%;
            animation: fpFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fpFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .fp-icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.1);
            border: 1.5px solid rgba(255, 215, 0, 0.35);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px;
            box-shadow: 0 0 24px rgba(255, 215, 0, 0.18);
        }

        .fp-title {
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 6px;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .fp-desc {
            color: #888888;
            font-size: 0.82rem;
            line-height: 1.5;
            margin: 0 0 20px;
            text-align: center;
            padding: 0 4px;
        }

        .forgot-pass-wrap {
            display: flex;
            justify-content: center;
            text-align: center;
            margin: -6px 0 16px;
        }

        .forgot-pass-link {
            color: #c9aa2e;
            font-size: 0.82rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .forgot-pass-link:hover {
            color: #ffd700;
            text-decoration: underline;
        }

        .fp-back-btn {
            background: transparent;
            border: none;
            color: #888888;
            font-size: 0.82rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            font-family: inherit;
            transition: color 0.2s ease;
            margin-top: 14px;
            width: 100%;
            justify-content: center;
        }

        .fp-back-btn:hover {
            color: #ffd700;
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

            .login-btn {
                padding: 13px;
                font-size: 14px;
            }

            /* Staff Verification Screen Mobile */
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
        }

        @media (max-width: 360px) {
            body.login-page .login-box {
                padding: 20px 12px;
                border-radius: 14px;
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

        .clear-chat-confirm-card.success-card {
            border-color: rgba(34, 197, 94, 0.55);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(34, 197, 94, 0.25);
        }

        .clear-chat-confirm-card.info-card {
            border-color: rgba(59, 130, 246, 0.55);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(59, 130, 246, 0.25);
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

        .clear-chat-confirm-card.success-card .clear-chat-confirm-icon {
            background: rgba(34, 197, 94, 0.18);
            border-color: rgba(34, 197, 94, 0.5);
            color: #22c55e;
        }

        .clear-chat-confirm-card.info-card .clear-chat-confirm-icon {
            background: rgba(59, 130, 246, 0.18);
            border-color: rgba(59, 130, 246, 0.5);
            color: #3b82f6;
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
        <p class="brand-eyebrow">✦ Your Style, Your Rules</p>
        <h1 class="brand-headline">Welcome to<br><span>ClosetGo</span></h1>
        <p class="brand-sub">Discover, reserve, and manage your wardrobe — all in one elegant platform designed for the modern fashion-forward individual.</p>
        <div class="brand-badges">
            <span class="brand-badge"><i class="fas fa-check"></i> Easy Reservations</span>
            <span class="brand-badge"><i class="fas fa-tshirt"></i> Curated Collections</span>
            <span class="brand-badge"><i class="fas fa-shield-alt"></i> Secure &amp; Fast</span>
        </div>
    </div>

    <!-- Right Panel: login form -->
    <div class="login-right">
        <div class="login-box">
            <div class="login-logo-wrap">
                <a href="../index.php" title="ClosetGo">
                    <img src="../images/icon/JMB.png" alt="ClosetGo Logo" class="login-brand-logo" onerror="this.src='../images/icon/profile_logo.png'">
                </a>
            </div>

            <!-- ── Regular login form ── -->
            <div class="login-form-inner" id="loginFormInner">
                <h2 class="login-heading">Log In to your account</h2>
                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div id="emailPasswordSection" class="email-login-section">
                        <div class="input-group">
                            <input type="email" id="emailInput" placeholder=" " required autocomplete="email">
                            <label for="emailInput">Email Address</label>
                        </div>
                        <div class="input-group">
                            <input type="password" id="passwordInput" placeholder=" " required autocomplete="current-password">
                            <label for="passwordInput">Password</label>
                            <img src="../images/icon/showpassword.png" alt="Show Password" class="password-toggle" onclick="togglePassword('passwordInput', this)">
                        </div>
                        <div class="forgot-pass-wrap">
                            <a href="javascript:void(0)" onclick="openForgotPassword()" class="forgot-pass-link">Forgot Password?</a>
                        </div>
                    </div>
                    <button type="submit" class="login-btn" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i> Log In
                    </button>
                    <div class="signup-text">
                        Don't have an account? <a href="sign_up.php" class="signup-link">Sign up</a>
                    </div>
                </form>
            </div>

            <!-- ── Forgot Password Screen ── -->
            <div class="forgot-pwd-screen" id="forgotPwdScreen">
                <!-- Step 1: Request OTP -->
                <div id="forgotStep1" class="fp-step">
                    <div class="fp-icon-wrap">
                        <i class="fas fa-key"></i>
                    </div>
                    <h2 class="fp-title">Forgot Password</h2>
                    <p class="fp-desc">Enter your registered email address to receive a 6-digit verification code.</p>
                    <form onsubmit="forgotSubmitEmail(event)">
                        <div class="input-group">
                            <input type="email" id="forgotEmailInput" placeholder=" " required autocomplete="email">
                            <label for="forgotEmailInput">Your Registered Email</label>
                        </div>
                        <button type="submit" class="login-btn" id="forgotSendBtn">
                            <i class="fas fa-paper-plane"></i> Send Verification Code
                        </button>
                    </form>
                    <div>
                        <button type="button" class="fp-back-btn" onclick="closeForgotPassword()">
                            <i class="fas fa-arrow-left"></i> Back to Log In
                        </button>
                    </div>
                </div>

                <!-- Step 2: Verify OTP -->
                <div id="forgotStep2" class="fp-step" style="display: none;">
                    <div class="fp-icon-wrap">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <h2 class="fp-title">Enter Verification Code</h2>
                    <p class="fp-desc">We sent a 6-digit code to <strong id="forgotEmailBadge" style="color: #ffd700; word-break: break-all;"></strong></p>
                    
                    <div class="otp-boxes-row" id="forgotOtpBoxesContainer">
                        <input type="text" class="otp-box-digit fp-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                        <input type="text" class="otp-box-digit fp-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                        <input type="text" class="otp-box-digit fp-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                        <input type="text" class="otp-box-digit fp-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                        <input type="text" class="otp-box-digit fp-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                        <input type="text" class="otp-box-digit fp-otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    </div>
                    <p id="forgotOtpError" style="color: #ff6b6b; font-size: 0.78rem; margin: -6px 0 12px; display: none; text-align: center;"></p>

                    <button type="button" class="verify-otp-btn" id="forgotVerifyBtn" onclick="forgotSubmitOtp()">
                        <i class="fas fa-check-circle"></i> Verify Code
                    </button>

                    <div class="sv-bar-wrap"><div class="sv-bar" id="forgotBar"></div></div>
                    <p class="sv-expire">Code expires in <strong id="forgotCountdown">10:00</strong></p>
                    <button type="button" class="sv-resend" id="forgotResendBtn" onclick="forgotResendOtp()" disabled>
                        Resend code (available in <span id="forgotResendCd">30</span>s)
                    </button>

                    <div>
                        <button type="button" class="fp-back-btn" onclick="forgotGoToStep(1)">
                            <i class="fas fa-arrow-left"></i> Change Email
                        </button>
                    </div>
                </div>

                <!-- Step 3: Create New Password -->
                <div id="forgotStep3" class="fp-step" style="display: none;">
                    <div class="fp-icon-wrap">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h2 class="fp-title">Create New Password</h2>
                    <p class="fp-desc">Enter and confirm a new secure password for your account.</p>
                    <form onsubmit="forgotSubmitNewPassword(event)">
                        <div class="input-group">
                            <input type="password" id="forgotNewPass" placeholder=" " required minlength="6" autocomplete="new-password">
                            <label for="forgotNewPass">New Password</label>
                            <img src="../images/icon/showpassword.png" alt="Show Password" class="password-toggle" onclick="togglePassword('forgotNewPass', this)">
                        </div>
                        <div class="input-group">
                            <input type="password" id="forgotConfirmPass" placeholder=" " required minlength="6" autocomplete="new-password">
                            <label for="forgotConfirmPass">Confirm New Password</label>
                            <img src="../images/icon/showpassword.png" alt="Show Password" class="password-toggle" onclick="togglePassword('forgotConfirmPass', this)">
                        </div>
                        <button type="submit" class="login-btn" id="forgotSavePassBtn">
                            <i class="fas fa-save"></i> Save New Password
                        </button>
                    </form>
                    <div>
                        <button type="button" class="fp-back-btn" onclick="closeForgotPassword()">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Staff verification waiting screen ── -->
            <div class="staff-verify-screen" id="staffVerifyScreen">
                <div class="sv-anim">
                    <div class="sv-ring"></div><div class="sv-ring"></div><div class="sv-ring"></div>
                    <div class="sv-icon" id="svIcon"><i class="fas fa-envelope-open-text"></i></div>
                </div>
                <h3 class="sv-title" id="svTitle">Check your inbox</h3>
                <p class="sv-sub" id="svSub">We sent a staff verification link &amp; PIN to</p>
                <div class="sv-chip"><i class="fas fa-envelope" style="font-size:.72rem"></i><span id="svEmailDisplay"></span></div>
                <div class="sv-dots"><span></span><span></span><span></span></div>
                <p style="font-size:.74rem;color:#888888;margin:0 0 10px">Click the link in your email OR enter the 6-digit PIN:</p>
                <!-- 6 Digit Input Row -->
                <div class="otp-boxes-row" id="staffOtpBoxesContainer">
                    <input type="text" class="otp-box-digit staff-pin-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit staff-pin-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit staff-pin-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit staff-pin-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit staff-pin-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <input type="text" class="otp-box-digit staff-pin-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                </div>
                <p id="svPinError"></p>
                <button type="button" class="verify-otp-btn" id="verifyStaffPinBtn" onclick="svVerifyPin()">
                    <i class="fas fa-check-circle"></i> Verify PIN
                </button>
                <div class="sv-bar-wrap"><div class="sv-bar" id="svBar"></div></div>
                <p class="sv-expire">Expires in <strong id="svCountdown">10:00</strong></p>
                <button class="sv-resend" id="svResendBtn" onclick="svResend()" disabled>
                    Resend email (available in <span id="svResendCd">30</span>s)
                </button>
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

    // Play notification sound
    function playNotificationSound() {
        const audio = new Audio('../ringtone/notification.MP3');
        audio.volume = 0.5;
        audio.play().catch(error => { console.log('Audio play failed:', error); });
    }
    
    // Show notification toast with dynamic icon, color, and title based on action intent
    function showNotification(message, type = 'success', duration = 3000) {
        if (typeof type === 'number') {
            duration = type;
            type = null;
        }
        const modal = document.getElementById('notificationToast');
        const card = document.getElementById('notificationToastCard');
        const titleEl = document.getElementById('notificationTitle');
        const msgEl = document.getElementById('notificationMessage');
        const iconEl = document.getElementById('notificationIcon');

        if (!modal || !msgEl) return;

        msgEl.textContent = message;

        const msgLower = (message || '').toLowerCase();

        // Determine type: Error, Warning/Action-Required, Info, or Success
        let detectedType = type;
        if (!detectedType) {
            if (/fail|error|incorrect|invalid|not found|please enter|missing|already exists/i.test(msgLower)) {
                detectedType = 'error';
            } else if (/complete.*profile|profile.*first|profile|login required|please select|please fill|required|wait|checking|terms|policy/i.test(msgLower)) {
                detectedType = 'warning';
            } else if (/info|reset/i.test(msgLower)) {
                detectedType = 'info';
            } else {
                detectedType = 'success';
            }
        }

        let cardClass = 'success-card';
        let title = 'Success';
        let iconHtml = '<i class="fas fa-check-circle"></i>';

        if (detectedType === 'error') {
            cardClass = 'error-card';
            title = 'Action Required';
            iconHtml = '<i class="fas fa-exclamation-circle"></i>';
        } else if (detectedType === 'warning') {
            cardClass = 'warning-card';
            title = 'Notice';
            iconHtml = '<i class="fas fa-exclamation-triangle"></i>';

            if (/profile/i.test(msgLower)) {
                iconHtml = '<i class="fas fa-user-edit"></i>';
                title = 'Profile Notice';
            } else if (/login/i.test(msgLower)) {
                iconHtml = '<i class="fas fa-sign-in-alt"></i>';
                title = 'Login Required';
            }
        } else if (detectedType === 'info') {
            cardClass = 'info-card';
            title = 'Notice';
            iconHtml = '<i class="fas fa-info-circle"></i>';
        } else {
            cardClass = 'success-card';
            title = 'Success';
            iconHtml = '<i class="fas fa-check-circle"></i>';
        }

        if (card) card.className = 'clear-chat-confirm-card ' + cardClass;
        if (titleEl) titleEl.textContent = title;
        if (iconEl) iconEl.innerHTML = iconHtml;

        playNotificationSound();

        modal.classList.add('show');

        clearTimeout(modal.hideTimeout);
        modal.hideTimeout = setTimeout(() => {
            modal.classList.remove('show');
        }, duration);
    }

    function handleLogin(event) {
        event.preventDefault();
        event.stopPropagation();
        const emailInput    = document.getElementById('emailInput');
        const passwordInput = document.getElementById('passwordInput');
        const email         = emailInput.value.trim();
        const password      = passwordInput.value;
        if (email && password) {
            authenticateEmailPassword(email, password);
        } else {
            showNotification('Please enter both email and password', 'error', 3000);
            return false;
        }
    }

    function authenticateEmailPassword(email, password) {
        const loginBtn = document.getElementById('loginBtn');
        const originalBtnHtml = loginBtn.innerHTML;
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';

        const formData = new FormData();
        formData.append('email', email);
        formData.append('password', password);
        formData.append('action', 'authenticate');
        fetch('login.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            loginBtn.disabled = false;
            loginBtn.innerHTML = originalBtnHtml;
            if (data.success && (data.is_staff || data.is_admin)) {
                // ── Staff or Admin login: show verification screen ──
                if (data.fallback && data.link) {
                    console.warn('SMTP fallback:', data.link);
                    window.open(data.link, '_blank');
                }
                // Explicitly set role based on response
                const role = data.is_admin ? 'admin' : (data.is_staff ? 'staff' : 'staff');
                svShowScreen(email, role, data.redirect);
            } else if (data.success) {
                // ── Regular user login ──
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('userId', data.user_id);
                localStorage.setItem('username', data.email);
                showNotification('Welcome back!', 'success', 2000);
                setTimeout(() => { window.location.href = '../index.php'; }, 2000);
            } else {
                showNotification(data.message || 'Login Failed!', 'error', 3000);
            }
        })
        .catch(error => {
            loginBtn.disabled = false;
            loginBtn.innerHTML = originalBtnHtml;
            console.error('Error:', error);
            showNotification('Error connecting to server. Please try again.', 'error', 3000);
        });
    }

    // Phone login functionality removed - save_phone.php does not exist
    // This function was placeholder for planned phone-based authentication

    // ── Input validity & OTP Setup ──────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        const emailInput    = document.getElementById('emailInput');
        const passwordInput = document.getElementById('passwordInput');
        const loginBtn      = document.getElementById('loginBtn');
        if (emailInput && passwordInput && loginBtn) {
            emailInput.addEventListener('input', checkFormValidity);
            passwordInput.addEventListener('input', checkFormValidity);
            function checkFormValidity() {
                if (emailInput.value && passwordInput.value) loginBtn.classList.add('active');
                else loginBtn.classList.remove('active');
            }
        }
        setupStaffOtpInputs();
    });

    function setupStaffOtpInputs() {
        const inputs = document.querySelectorAll('.staff-pin-digit');

        inputs.forEach((input, index) => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace') {
                    if (!e.target.value && index > 0) {
                        inputs[index - 1].focus();
                        inputs[index - 1].value = '';
                        inputs[index - 1].classList.remove('filled');
                    }
                } else if (e.key === 'ArrowLeft' && index > 0) {
                    inputs[index - 1].focus();
                } else if (e.key === 'ArrowRight' && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    svVerifyPin();
                }
            });

            input.addEventListener('input', (e) => {
                // Enforce numbers only
                const val = e.target.value.replace(/\D/g, '');
                e.target.value = val ? val[val.length - 1] : '';

                if (e.target.value) {
                    e.target.classList.add('filled');
                    e.target.classList.remove('error');
                    const err = document.getElementById('svPinError');
                    if (err) err.style.display = 'none';

                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    } else {
                        checkAndAutoVerifyStaff();
                    }
                } else {
                    e.target.classList.remove('filled');
                }
            });

            // Handle Paste of complete 6-digit OTP (numbers only)
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
                        svVerifyPin();
                    }
                }
            });
        });
    }

    function checkAndAutoVerifyStaff() {
        const inputs = document.querySelectorAll('.staff-pin-digit');
        let fullCode = '';
        inputs.forEach(input => fullCode += input.value.trim());
        if (fullCode.length === 6) {
            svVerifyPin();
        }
    }

    // ── Staff / Admin verification screen logic ───────────────────────────
    let svPoll=null, svCountInt=null, svResendInt=null, svTotalSec=600, svResendSec=30;
    let svCurrentRole = 'staff';
    let svCurrentRedirect = '../staff/index.php';

    function svShowScreen(email, role, redirectUrl) {
        svCurrentRole = role || 'staff';
        svCurrentRedirect = redirectUrl || (svCurrentRole === 'admin' ? '../admin/dashboard.php' : '../staff/index.php');

        document.getElementById('loginFormInner').classList.add('hidden');
        document.getElementById('staffVerifyScreen').classList.add('active');
        document.getElementById('svEmailDisplay').textContent = email;

        const subTitle = document.getElementById('svSub');
        if (subTitle) {
            subTitle.textContent = (svCurrentRole === 'admin')
                ? 'We sent an admin verification link & PIN to'
                : 'We sent a staff verification link & PIN to';
        }

        // Reset and focus OTP input boxes
        const inputs = document.querySelectorAll('.staff-pin-digit');
        inputs.forEach(input => {
            input.value = '';
            input.classList.remove('filled', 'error');
        });
        const err = document.getElementById('svPinError');
        if (err) err.style.display = 'none';
        if (inputs[0]) setTimeout(() => inputs[0].focus(), 50);

        svTotalSec=600; svResendSec=30;
        const bar=document.getElementById('svBar');
        bar.style.transition='none'; bar.style.width='100%'; void bar.offsetWidth; bar.style.transition='width 1s linear';
        clearInterval(svCountInt);
        svCountInt = setInterval(() => {
            svTotalSec--;
            if (svTotalSec <= 0) { clearInterval(svCountInt); clearInterval(svPoll); document.getElementById('svCountdown').textContent='Expired'; bar.style.width='0%'; return; }
            const m=Math.floor(svTotalSec/60), s=svTotalSec%60;
            document.getElementById('svCountdown').textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
            bar.style.width=((svTotalSec/600)*100)+'%';
        }, 1000);
        const rb=document.getElementById('svResendBtn');
        rb.disabled=true; rb.innerHTML='Resend email (available in <span id="svResendCd">30</span>s)';
        clearInterval(svResendInt);
        svResendInt=setInterval(()=>{
            svResendSec--; const cd=document.getElementById('svResendCd');
            if(svResendSec<=0){clearInterval(svResendInt);rb.disabled=false;rb.textContent='Resend verification email';return;}
            if(cd)cd.textContent=svResendSec;
        },1000);
        clearInterval(svPoll);
        svPoll = setInterval(svCheckVerified, 3000);
    }

    function svVerifyPin() {
        const inputs = document.querySelectorAll('.staff-pin-digit');
        let pin = '';
        inputs.forEach(input => pin += input.value.trim());
        const err = document.getElementById('svPinError');
        const verifyBtn = document.getElementById('verifyStaffPinBtn');

        if (!pin || pin.length < 6) {
            if (err) {
                err.textContent = 'Please enter all 6 digits of your PIN.';
                err.style.display = 'block';
            }
            inputs.forEach(inp => { if (!inp.value) inp.classList.add('error'); });
            return;
        }
        if (err) err.style.display = 'none';

        if (verifyBtn) {
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…';
        }

        const fd = new FormData();
        fd.append('action', 'staff_verify_pin');
        fd.append('pin', pin);
        fetch('login.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (verifyBtn) {
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify PIN';
                }
                if (data.success) {
                    svOnVerified(data);
                } else {
                    if (err) {
                        err.textContent = data.message || 'Invalid PIN';
                        err.style.display = 'block';
                    }
                    inputs.forEach(inp => {
                        inp.classList.add('error');
                        inp.value = '';
                        inp.classList.remove('filled');
                    });
                    if (inputs[0]) inputs[0].focus();
                }
            })
            .catch(() => {
                if (verifyBtn) {
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify PIN';
                }
                if (err) {
                    err.textContent = 'Network error.';
                    err.style.display = 'block';
                }
            });
    }

    function svCheckVerified() {
        fetch('login.php?action=staff_check_verified').then(r=>r.json()).then(data=>{
            // Only auto-redirect if user clicked the email link (verification_token cleared in DB)
            // Do NOT auto-redirect just because PIN was entered - that requires explicit verification
            if(data.verified && !data.pending_pin) {
                svOnVerified(data);
            }
        }).catch(()=>{});
    }

    function svOnVerified(data) {
        clearInterval(svPoll); clearInterval(svCountInt); clearInterval(svResendInt);
        const icon=document.getElementById('svIcon');
        if(icon){icon.innerHTML='<i class="fas fa-check" style="color:#4ade80"></i>';icon.style.borderColor='rgba(74,222,128,.5)';icon.style.background='rgba(74,222,128,.1)';icon.style.animation='none';}
        document.getElementById('svTitle').textContent='Verified!';

        // Use server-provided redirect URL and is_admin flag to determine destination
        const targetUrl = (data && data.redirect) ? data.redirect : svCurrentRedirect;
        const isAdmin = (data && data.is_admin) || (svCurrentRole === 'admin');
        const targetName = isAdmin ? 'Admin Dashboard…' : 'Staff Portal…';
        document.getElementById('svSub').textContent = 'Redirecting to ' + targetName;

        document.querySelectorAll('.sv-dots span').forEach(s=>s.style.animation='none');
        setTimeout(()=>{ window.location.href = targetUrl; }, 1200);
    }

    function svResend() {
        clearInterval(svPoll); clearInterval(svCountInt); clearInterval(svResendInt);
        document.getElementById('staffVerifyScreen').classList.remove('active');
        document.getElementById('loginFormInner').classList.remove('hidden');
        const icon=document.getElementById('svIcon');
        icon.innerHTML='<i class="fas fa-envelope-open-text"></i>';
        icon.style.borderColor=''; icon.style.background=''; icon.style.animation='';
        document.getElementById('svTitle').textContent='Check your inbox';
        document.getElementById('svSub').textContent = (svCurrentRole === 'admin')
            ? 'We sent an admin verification link & PIN to'
            : 'We sent a staff verification link & PIN to';
        svResendSec=30;
        // Re-trigger submit with same credentials
        const email=document.getElementById('emailInput').value;
        const pass=document.getElementById('passwordInput').value;
        if(email&&pass) authenticateEmailPassword(email,pass);
    }

    // ─── Forgot Password Workflow ─────────────────────────────────────────────
    let forgotActiveEmail = '';
    let forgotOtpCode = '';
    let forgotCountInt = null;
    let forgotResendInt = null;
    let forgotExpireSec = 600;
    let forgotResendSec = 30;

    function openForgotPassword() {
        const emailVal = document.getElementById('emailInput') ? document.getElementById('emailInput').value.trim() : '';
        if (emailVal) {
            document.getElementById('forgotEmailInput').value = emailVal;
        }
        document.getElementById('loginFormInner').style.display = 'none';
        const staffSc = document.getElementById('staffVerifyScreen');
        if (staffSc) staffSc.style.display = 'none';

        const fp = document.getElementById('forgotPwdScreen');
        fp.style.display = 'block';
        forgotGoToStep(1);
    }

    function closeForgotPassword() {
        clearInterval(forgotCountInt);
        clearInterval(forgotResendInt);
        const fp = document.getElementById('forgotPwdScreen');
        if (fp) fp.style.display = 'none';
        const lf = document.getElementById('loginFormInner');
        if (lf) lf.style.display = 'block';
    }

    function forgotGoToStep(stepNum) {
        document.getElementById('forgotStep1').style.display = (stepNum === 1) ? 'block' : 'none';
        document.getElementById('forgotStep2').style.display = (stepNum === 2) ? 'block' : 'none';
        document.getElementById('forgotStep3').style.display = (stepNum === 3) ? 'block' : 'none';

        if (stepNum === 1) {
            setTimeout(() => {
                const el = document.getElementById('forgotEmailInput');
                if (el) el.focus();
            }, 100);
        } else if (stepNum === 2) {
            const badge = document.getElementById('forgotEmailBadge');
            if (badge) badge.textContent = forgotActiveEmail;
            initForgotOtpInputs();
            clearForgotOtpInputs();
        } else if (stepNum === 3) {
            setTimeout(() => {
                const el = document.getElementById('forgotNewPass');
                if (el) el.focus();
            }, 100);
        }
    }

    function forgotSubmitEmail(e) {
        if (e) e.preventDefault();
        const emailInput = document.getElementById('forgotEmailInput');
        const email = emailInput ? emailInput.value.trim() : '';
        if (!email) {
            showNotification('Please enter your email address.', 'error', 3000);
            return;
        }

        const btn = document.getElementById('forgotSendBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending code...';

        const fd = new FormData();
        fd.append('action', 'forgot_send_otp');
        fd.append('email', email);

        fetch('login.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;

                if (data.success) {
                    forgotActiveEmail = email;
                    showNotification(data.message || 'Verification code sent to your email!', 'success', 3500);
                    startForgotTimers();
                    forgotGoToStep(2);
                } else {
                    showNotification(data.message || 'Failed to send verification code.', 'error', 3500);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showNotification('Network error. Please try again.', 'error', 3000);
            });
    }

    function startForgotTimers() {
        clearInterval(forgotCountInt);
        clearInterval(forgotResendInt);

        forgotExpireSec = 600;
        forgotResendSec = 30;

        const countEl = document.getElementById('forgotCountdown');
        const barEl = document.getElementById('forgotBar');
        const resendBtn = document.getElementById('forgotResendBtn');
        const resendCd = document.getElementById('forgotResendCd');

        if (resendBtn) resendBtn.disabled = true;
        if (resendCd) resendCd.textContent = forgotResendSec;

        forgotCountInt = setInterval(() => {
            forgotExpireSec--;
            if (forgotExpireSec <= 0) {
                clearInterval(forgotCountInt);
                if (countEl) countEl.textContent = 'Expired';
                if (barEl) barEl.style.width = '0%';
                const err = document.getElementById('forgotOtpError');
                if (err) {
                    err.textContent = 'Verification code has expired. Please request a new one.';
                    err.style.display = 'block';
                }
                return;
            }
            const m = Math.floor(forgotExpireSec / 60);
            const s = forgotExpireSec % 60;
            if (countEl) countEl.textContent = `${m}:${s < 10 ? '0' : ''}${s}`;
            if (barEl) barEl.style.width = `${(forgotExpireSec / 600) * 100}%`;
        }, 1000);

        forgotResendInt = setInterval(() => {
            forgotResendSec--;
            if (resendCd) resendCd.textContent = forgotResendSec;
            if (forgotResendSec <= 0) {
                clearInterval(forgotResendInt);
                if (resendBtn) {
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = 'Resend verification code';
                }
            }
        }, 1000);
    }

    function forgotResendOtp() {
        if (!forgotActiveEmail) return;
        const resendBtn = document.getElementById('forgotResendBtn');
        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resending...';
        }

        const fd = new FormData();
        fd.append('action', 'forgot_send_otp');
        fd.append('email', forgotActiveEmail);

        fetch('login.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('A new verification code has been sent!', 'success', 3000);
                    clearForgotOtpInputs();
                    startForgotTimers();
                } else {
                    showNotification(data.message || 'Failed to resend code.', 'error', 3000);
                    if (resendBtn) resendBtn.disabled = false;
                }
            })
            .catch(() => {
                showNotification('Network error while resending.', 'error', 3000);
                if (resendBtn) resendBtn.disabled = false;
            });
    }

    function initForgotOtpInputs() {
        const container = document.getElementById('forgotOtpBoxesContainer');
        if (!container || container.dataset.initialized) return;
        container.dataset.initialized = 'true';

        const digits = container.querySelectorAll('.fp-otp-digit');
        digits.forEach((digit, idx) => {
            digit.addEventListener('input', (e) => {
                digit.value = digit.value.replace(/[^0-9]/g, '');
                if (digit.value) {
                    digit.classList.add('filled');
                    if (idx < digits.length - 1) {
                        digits[idx + 1].focus();
                    } else {
                        // Check if all filled
                        const allFilled = Array.from(digits).every(d => d.value.length === 1);
                        if (allFilled) forgotSubmitOtp();
                    }
                } else {
                    digit.classList.remove('filled');
                }
            });

            digit.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !digit.value && idx > 0) {
                    digits[idx - 1].focus();
                    digits[idx - 1].value = '';
                    digits[idx - 1].classList.remove('filled');
                } else if (e.key === 'ArrowLeft' && idx > 0) {
                    digits[idx - 1].focus();
                } else if (e.key === 'ArrowRight' && idx < digits.length - 1) {
                    digits[idx + 1].focus();
                } else if (e.key === 'Enter') {
                    forgotSubmitOtp();
                }
            });

            digit.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                const cleanDigits = pasteData.replace(/\D/g, '').slice(0, 6);
                if (cleanDigits.length > 0) {
                    cleanDigits.split('').forEach((char, i) => {
                        if (digits[i]) {
                            digits[i].value = char;
                            digits[i].classList.add('filled');
                        }
                    });
                    const lastIdx = Math.min(cleanDigits.length, digits.length) - 1;
                    if (digits[lastIdx]) digits[lastIdx].focus();
                    if (cleanDigits.length === 6) {
                        forgotSubmitOtp();
                    }
                }
            });
        });
    }

    function clearForgotOtpInputs() {
        const container = document.getElementById('forgotOtpBoxesContainer');
        if (!container) return;
        const digits = container.querySelectorAll('.fp-otp-digit');
        digits.forEach((d, i) => {
            d.value = '';
            d.classList.remove('filled', 'error');
            if (i === 0) setTimeout(() => d.focus(), 150);
        });
        const err = document.getElementById('forgotOtpError');
        if (err) err.style.display = 'none';
    }

    function forgotSubmitOtp() {
        const container = document.getElementById('forgotOtpBoxesContainer');
        if (!container) return;
        const digits = container.querySelectorAll('.fp-otp-digit');
        let code = '';
        digits.forEach(d => { code += d.value.trim(); });

        const err = document.getElementById('forgotOtpError');
        if (code.length < 6) {
            if (err) {
                err.textContent = 'Please enter all 6 digits of the code.';
                err.style.display = 'block';
            }
            return;
        }
        if (err) err.style.display = 'none';

        const btn = document.getElementById('forgotVerifyBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

        const fd = new FormData();
        fd.append('action', 'forgot_verify_otp');
        fd.append('email', forgotActiveEmail);
        fd.append('otp', code);

        fetch('login.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;

                if (data.success) {
                    forgotOtpCode = code;
                    clearInterval(forgotCountInt);
                    clearInterval(forgotResendInt);
                    showNotification('Code verified! Set your new password.', 'success', 2500);
                    forgotGoToStep(3);
                } else {
                    digits.forEach(d => d.classList.add('error'));
                    if (err) {
                        err.textContent = data.message || 'Incorrect verification code.';
                        err.style.display = 'block';
                    }
                    setTimeout(() => {
                        digits.forEach(d => d.classList.remove('error'));
                    }, 1000);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showNotification('Network error while verifying.', 'error', 3000);
            });
    }

    function forgotSubmitNewPassword(e) {
        if (e) e.preventDefault();
        const newPass = document.getElementById('forgotNewPass') ? document.getElementById('forgotNewPass').value : '';
        const confirmPass = document.getElementById('forgotConfirmPass') ? document.getElementById('forgotConfirmPass').value : '';

        if (!newPass || newPass.length < 6) {
            showNotification('Password must be at least 6 characters long.', 'error', 3000);
            return;
        }

        if (newPass !== confirmPass) {
            showNotification('Passwords do not match. Please re-enter.', 'error', 3000);
            return;
        }

        const btn = document.getElementById('forgotSavePassBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating password...';

        const fd = new FormData();
        fd.append('action', 'forgot_reset_password');
        fd.append('email', forgotActiveEmail);
        fd.append('new_password', newPass);
        fd.append('otp', forgotOtpCode);

        fetch('login.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;

                if (data.success) {
                    showNotification('Password updated successfully! Please log in.', 'success', 3500);
                    const emailInput = document.getElementById('emailInput');
                    if (emailInput) emailInput.value = forgotActiveEmail;
                    const passInput = document.getElementById('passwordInput');
                    if (passInput) {
                        passInput.value = '';
                        passInput.focus();
                    }
                    closeForgotPassword();
                } else {
                    showNotification(data.message || 'Failed to reset password.', 'error', 3500);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showNotification('Network error while updating password.', 'error', 3000);
            });
    }

    // ─── Auto-fill Email & Password after successful Sign Up ───────────────
    function checkAutoFillFromSignup() {
        const prefillEmail = sessionStorage.getItem('prefill_email');
        const prefillPass  = sessionStorage.getItem('prefill_password');
        const urlParams    = new URLSearchParams(window.location.search);
        const fromSignup   = sessionStorage.getItem('from_signup') === 'true' || urlParams.get('signup') === 'success';

        if (prefillEmail || prefillPass) {
            const emailInput = document.getElementById('emailInput');
            const passInput  = document.getElementById('passwordInput');

            if (emailInput && prefillEmail) {
                emailInput.value = prefillEmail;
                emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                emailInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (passInput && prefillPass) {
                passInput.value = prefillPass;
                passInput.dispatchEvent(new Event('input', { bubbles: true }));
                passInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Clean up sessionStorage so it doesn't linger indefinitely
            sessionStorage.removeItem('prefill_email');
            sessionStorage.removeItem('prefill_password');
            sessionStorage.removeItem('from_signup');

            // Clean query parameter from URL address bar without reloading
            if (urlParams.get('signup')) {
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);
            }

            if (fromSignup) {
                setTimeout(() => {
                    showNotification('Account created successfully! Your Email and Password have been auto-filled.', 'success', 4000);
                }, 350);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAutoFillFromSignup);
    } else {
        checkAutoFillFromSignup();
    }
    </script>
</body>
</html>
