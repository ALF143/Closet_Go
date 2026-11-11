<?php
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminlogin.php');
    exit();
}

// Include database connection
require_once '../database/db_config.php';

$errors = [];
$success_message = '';
$currentAdminId = $_SESSION['admin_id'] ?? 0;
$accountType = $_POST['account_type'] ?? 'admin';
$firstname = $lastname = $middlename = $suffix = $email = $password = '';

// Handle delete action (Admin or Staff)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $deleteType = trim($_POST['delete_type'] ?? 'admin');
    $deleteId = intval($_POST['delete_id'] ?? 0);

    if ($deleteId <= 0) {
        $errors[] = "Invalid account ID.";
    } elseif ($deleteType === 'admin' && $deleteId === $currentAdminId) {
        $errors[] = "You cannot delete your own active administrator account.";
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            if ($deleteType === 'staff') {
                $delStmt = $conn->prepare("DELETE FROM system_staffs WHERE id = ?");
                $delStmt->bind_param("i", $deleteId);
                if ($delStmt->execute() && $delStmt->affected_rows > 0) {
                    $success_message = "Staff account successfully removed.";
                } else {
                    $errors[] = "Could not delete staff account. It may have already been removed.";
                }
                $delStmt->close();
            } else {
                $delStmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
                $delStmt->bind_param("i", $deleteId);
                if ($delStmt->execute() && $delStmt->affected_rows > 0) {
                    $success_message = "Administrator account successfully removed.";
                } else {
                    $errors[] = "Could not delete administrator account. It may have already been removed.";
                }
                $delStmt->close();
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Handle add account form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_account') {
    $accountType = trim($_POST['account_type'] ?? 'admin');
    $firstname   = trim($_POST['firstname'] ?? '');
    $lastname    = trim($_POST['lastname'] ?? '');
    $middlename  = trim($_POST['middlename'] ?? '');
    $suffix      = trim($_POST['suffix'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    // Validate common inputs
    if (empty($firstname)) {
        $errors[] = "First name is required.";
    }
    if (empty($lastname)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            // Setup base URLs
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            $protocol  = $isHttps ? 'https' : 'http';
            $host      = $_SERVER['HTTP_HOST'];
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            if ($scriptDir === '/' || $scriptDir === '.') {
                $scriptDir = '';
            }
            $rootUrl = $protocol . '://' . $host . dirname($scriptDir);
            $adminUrl = $protocol . '://' . $host . rtrim($scriptDir, '/');

            // Default SMTP Settings
            $smtpHost = 'smtp.gmail.com';
            $smtpUser = 'clos3tgo@gmail.com';
            $smtpPass = 'komm kdvz mjvs rysr';
            $smtpPort = 587;

            try {
                $res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('smtp_user','smtp_pass','smtp_host','smtp_port')");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        if ($row['setting_key'] === 'smtp_user' && !empty($row['setting_value'])) $smtpUser = $row['setting_value'];
                        if ($row['setting_key'] === 'smtp_pass' && !empty($row['setting_value'])) $smtpPass = $row['setting_value'];
                        if ($row['setting_key'] === 'smtp_host' && !empty($row['setting_value'])) $smtpHost = $row['setting_value'];
                        if ($row['setting_key'] === 'smtp_port' && !empty($row['setting_value'])) $smtpPort = intval($row['setting_value']);
                    }
                }
            } catch (Exception $ex) {}

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));
            $token_expiry = time() + 86400 * 7; // 7 days
            $fullName = trim($firstname . ' ' . (!empty($middlename) ? $middlename . ' ' : '') . $lastname . (!empty($suffix) ? ' ' . $suffix : ''));
            $fn = htmlspecialchars($firstname);
            $ln = htmlspecialchars($lastname);
            $sf = htmlspecialchars($suffix);
            $displayName = htmlspecialchars(trim($firstname . ' ' . $lastname . (!empty($suffix) ? ' ' . $suffix : '')));
            $em = htmlspecialchars($email);
            $pwd = htmlspecialchars($password);
            $yr = date('Y');

            if ($accountType === 'staff') {
                // Ensure system_staffs table and columns exist with separate name fields
                @$conn->query("CREATE TABLE IF NOT EXISTS system_staffs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    firstname VARCHAR(50) DEFAULT NULL,
                    lastname VARCHAR(50) DEFAULT NULL,
                    middlename VARCHAR(50) DEFAULT NULL,
                    suffix VARCHAR(20) DEFAULT NULL,
                    name VARCHAR(150) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    verification_token VARCHAR(255) DEFAULT NULL,
                    token_expiry INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS firstname VARCHAR(50) DEFAULT NULL");
                @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS lastname VARCHAR(50) DEFAULT NULL");
                @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS middlename VARCHAR(50) DEFAULT NULL");
                @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS suffix VARCHAR(20) DEFAULT NULL AFTER lastname");
                @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
                @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS token_expiry INT DEFAULT NULL");
                @$conn->query("ALTER TABLE system_staffs ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");

                // Check duplicate email in system_staffs (one staff account only per email)
                $checkStmt = $conn->prepare("SELECT id FROM system_staffs WHERE email = ?");
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $errors[] = "A staff account with this email address already exists.";
                } else {
                    // Check if email already exists in admin_users or system_users and ensure password is not the same
                    $hasPasswordConflict = false;

                    // 1. Check against Administrator password
                    $adminCheck = $conn->prepare("SELECT password FROM admin_users WHERE email = ?");
                    if ($adminCheck) {
                        $adminCheck->bind_param("s", $email);
                        $adminCheck->execute();
                        $adminRes = $adminCheck->get_result();
                        if ($adminRow = $adminRes->fetch_assoc()) {
                            if (password_verify($password, $adminRow['password'])) {
                                $errors[] = "This email is already registered as an Administrator. The Staff password must NOT be the same as the Administrator account password.";
                                $hasPasswordConflict = true;
                            }
                        }
                        $adminCheck->close();
                    }

                    // 2. Check against Customer password
                    if (!$hasPasswordConflict) {
                        $userCheck = $conn->prepare("SELECT password FROM system_users WHERE email = ?");
                        if ($userCheck) {
                            $userCheck->bind_param("s", $email);
                            $userCheck->execute();
                            $userRes = $userCheck->get_result();
                            if ($userRow = $userRes->fetch_assoc()) {
                                if (password_verify($password, $userRow['password'])) {
                                    $errors[] = "This email is already registered as a Customer. The Staff password cannot be the same as the Customer account password.";
                                    $hasPasswordConflict = true;
                                }
                            }
                            $userCheck->close();
                        }
                    }

                    if (!$hasPasswordConflict) {
                        $insStmt = $conn->prepare("INSERT INTO system_staffs (firstname, lastname, middlename, suffix, name, email, password, is_active, verification_token, token_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
                        $insStmt->bind_param("ssssssssi", $firstname, $lastname, $middlename, $suffix, $fullName, $email, $hashed_password, $token, $token_expiry);
                        if ($insStmt->execute()) {
                            $newStaffId = $conn->insert_id;
                            $verifyLink = $rootUrl . '/staff/staff_verify.php?token=' . urlencode($token) . '&staff_id=' . $newStaffId;
                            $loginLink  = $rootUrl . '/staff/login.php';

                            // Compose Staff Email
                            $emailBodyHtml = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;background:#0d0d0d;color:#ffffff;padding:32px 16px}
.wrap{max-width:540px;margin:0 auto;background:#161616;border:1px solid rgba(78,205,196,0.35);border-radius:18px;padding:36px 32px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,0.8)}
.brand{font-size:24px;font-weight:900;letter-spacing:2px;color:#ffffff;margin-bottom:2px}
.portal{font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#4ecdc4;font-weight:700;margin-bottom:24px}
.shield{font-size:44px;margin-bottom:16px;color:#4ecdc4}
h2{color:#ffffff;font-size:20px;margin-bottom:12px}
.greeting{color:#aaaaaa;font-size:14px;line-height:1.6;margin-bottom:24px}
.creds-box{background:#1f1f1f;border:1px dashed rgba(78,205,196,0.5);border-radius:12px;padding:20px;margin:20px 0;text-align:left}
.creds-title{font-size:12px;font-weight:700;text-transform:uppercase;color:#4ecdc4;margin-bottom:12px;letter-spacing:1px;text-align:center}
.creds-row{display:flex;justify-content:space-between;margin:8px 0;font-size:14px;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:6px}
.creds-label{color:#888888;font-weight:600}
.creds-val{color:#ffffff;font-family:monospace;font-size:14px;font-weight:700;word-break:break-all}
.btn{display:inline-block;background:linear-gradient(135deg,#1f4068,#4ecdc4);color:#ffffff;font-weight:800;font-size:15px;text-decoration:none;padding:15px 36px;border-radius:10px;margin:22px 0 16px 0;letter-spacing:0.5px;box-shadow:0 6px 20px rgba(78,205,196,0.35)}
.divider{height:1px;background:rgba(255,255,255,0.08);margin:24px 0}
.note{font-size:12px;color:#777777;line-height:1.5;margin-top:14px}
.footer{margin-top:24px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);font-size:11px;color:#444444}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">ClosetGo</div>
  <div class="portal">Staff Portal</div>
  <div class="shield">&#x1F455;</div>
  <h2>Welcome to ClosetGo Staff Team!</h2>
  <p class="greeting">Hello <strong>' . $displayName . '</strong>,<br>Your staff member account has been created. Here are your credentials to log in:</p>
  <div class="creds-box">
    <div class="creds-title">Your Staff Credentials</div>
    <div class="creds-row"><span class="creds-label">Email:</span><span class="creds-val">' . $em . '</span></div>
    <div class="creds-row" style="border-bottom:none;padding-bottom:0;"><span class="creds-label">Password:</span><span class="creds-val" style="color:#4ecdc4;">' . $pwd . '</span></div>
  </div>
  <p class="note" style="color:#bbb;">Click below to verify and directly sign in to the Staff portal:</p>
  <a href="' . $verifyLink . '" class="btn">&#x2713; Verify &amp; Sign In as Staff</a>
  <div class="divider"></div>
  <p class="note">Staff Login Page: <a href="' . $loginLink . '" style="color:#4ecdc4;text-decoration:none;">' . $loginLink . '</a></p>
  <div class="footer">&copy; ' . $yr . ' ClosetGo. All rights reserved.</div>
</div>
</body></html>';

                            // Send email
                            $emailSent = false;
                            try {
                                $mail = new PHPMailer(true);
                                $mail->isSMTP();
                                $mail->Host       = $smtpHost;
                                $mail->SMTPAuth   = true;
                                $mail->Username   = $smtpUser;
                                $mail->Password   = $smtpPass;
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port       = $smtpPort;
                                $mail->CharSet    = 'UTF-8';
                                $mail->Timeout    = 7;
                                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

                                $mail->setFrom($smtpUser, 'ClosetGo Staff Management');
                                $mail->addAddress($email, $fullName);
                                $mail->isHTML(true);
                                $mail->Subject = 'Your ClosetGo Staff Account Credentials';
                                $mail->Body    = $emailBodyHtml;
                                $mail->AltBody = "Welcome to ClosetGo Staff!\n\nEmail: {$email}\nPassword: {$password}\n\nLogin URL: {$loginLink}\nVerify URL: {$verifyLink}";
                                $mail->send();
                                $emailSent = true;
                            } catch (\Throwable $e) {
                                try {
                                    $mail->Port = 465;
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                    $mail->send();
                                    $emailSent = true;
                                } catch (\Throwable $e2) {}
                            }

                            if ($emailSent) {
                                $success_message = "Staff account created successfully! Login credentials (password) have been emailed to " . htmlspecialchars($email) . ".";
                            } else {
                                $success_message = "Staff account created successfully! (Password: <strong>" . htmlspecialchars($password) . "</strong>).";
                            }
                            $firstname = $lastname = $middlename = $suffix = $email = $password = '';
                        } else {
                            $errors[] = "Failed to create staff account.";
                        }
                        $insStmt->close();
                    }
                }
                $checkStmt->close();
            } else {
                // Ensure admin_users columns exist
                @$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS suffix VARCHAR(20) DEFAULT NULL AFTER lastname");
                @$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS middlename VARCHAR(50) DEFAULT NULL");
                @$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
                @$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS token_expiry INT DEFAULT NULL");

                // Check duplicate email in admin_users (one admin account only per email)
                $checkStmt = $conn->prepare("SELECT id FROM admin_users WHERE email = ?");
                $checkStmt->bind_param("s", $email);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $errors[] = "An administrator account with this email address already exists.";
                } else {
                    // Check if email already exists in system_staffs or system_users and ensure password is not the same
                    $hasPasswordConflict = false;

                    // 1. Check against Staff password
                    $staffCheck = $conn->prepare("SELECT password FROM system_staffs WHERE email = ?");
                    if ($staffCheck) {
                        $staffCheck->bind_param("s", $email);
                        $staffCheck->execute();
                        $staffRes = $staffCheck->get_result();
                        if ($staffRow = $staffRes->fetch_assoc()) {
                            if (password_verify($password, $staffRow['password'])) {
                                $errors[] = "This email is already registered as a Staff member. The Administrator password must NOT be the same as the Staff account password.";
                                $hasPasswordConflict = true;
                            }
                        }
                        $staffCheck->close();
                    }

                    // 2. Check against Customer password
                    if (!$hasPasswordConflict) {
                        $userCheck = $conn->prepare("SELECT password FROM system_users WHERE email = ?");
                        if ($userCheck) {
                            $userCheck->bind_param("s", $email);
                            $userCheck->execute();
                            $userRes = $userCheck->get_result();
                            if ($userRow = $userRes->fetch_assoc()) {
                                if (password_verify($password, $userRow['password'])) {
                                    $errors[] = "This email is already registered as a Customer. The Administrator password cannot be the same as the Customer account password.";
                                    $hasPasswordConflict = true;
                                }
                            }
                            $userCheck->close();
                        }
                    }

                    if (!$hasPasswordConflict) {
                        $insStmt = $conn->prepare("INSERT INTO admin_users (firstname, lastname, middlename, suffix, email, password, verification_token, token_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $insStmt->bind_param("sssssssi", $firstname, $lastname, $middlename, $suffix, $email, $hashed_password, $token, $token_expiry);
                        if ($insStmt->execute()) {
                            $newAdminId = $conn->insert_id;
                            $verifyLink = $adminUrl . '/admin_verify.php?token=' . urlencode($token) . '&admin_id=' . $newAdminId;
                            $loginLink  = $adminUrl . '/adminlogin.php';

                            // Compose Admin Email
                            $emailBodyHtml = '<!DOCTYPE html>
<html>
<head><meta charset="utf-8">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;background:#0d0d0d;color:#ffffff;padding:32px 16px}
.wrap{max-width:540px;margin:0 auto;background:#161616;border:1px solid rgba(201,170,46,0.35);border-radius:18px;padding:36px 32px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,0.8)}
.brand{font-size:24px;font-weight:900;letter-spacing:2px;color:#ffffff;margin-bottom:2px}
.portal{font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#c9aa2e;font-weight:700;margin-bottom:24px}
.shield{font-size:44px;margin-bottom:16px;color:#c9aa2e}
h2{color:#ffffff;font-size:20px;margin-bottom:12px}
.greeting{color:#aaaaaa;font-size:14px;line-height:1.6;margin-bottom:24px}
.creds-box{background:#1f1f1f;border:1px dashed rgba(201,170,46,0.5);border-radius:12px;padding:20px;margin:20px 0;text-align:left}
.creds-title{font-size:12px;font-weight:700;text-transform:uppercase;color:#c9aa2e;margin-bottom:12px;letter-spacing:1px;text-align:center}
.creds-row{display:flex;justify-content:space-between;margin:8px 0;font-size:14px;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:6px}
.creds-label{color:#888888;font-weight:600}
.creds-val{color:#ffffff;font-family:monospace;font-size:14px;font-weight:700;word-break:break-all}
.btn{display:inline-block;background:linear-gradient(135deg,#7c6300,#c9aa2e);color:#000000;font-weight:800;font-size:15px;text-decoration:none;padding:15px 36px;border-radius:10px;margin:22px 0 16px 0;letter-spacing:0.5px;box-shadow:0 6px 20px rgba(201,170,46,0.35)}
.divider{height:1px;background:rgba(255,255,255,0.08);margin:24px 0}
.note{font-size:12px;color:#777777;line-height:1.5;margin-top:14px}
.footer{margin-top:24px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);font-size:11px;color:#444444}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">ClosetGo</div>
  <div class="portal">Admin Portal</div>
  <div class="shield">&#x1F6E1;</div>
  <h2>Welcome to the Admin Team!</h2>
  <p class="greeting">Hello <strong>' . $displayName . '</strong>,<br>Your administrator account has been created. Here are your credentials to log in:</p>
  <div class="creds-box">
    <div class="creds-title">Your Admin Credentials</div>
    <div class="creds-row"><span class="creds-label">Email:</span><span class="creds-val">' . $em . '</span></div>
    <div class="creds-row" style="border-bottom:none;padding-bottom:0;"><span class="creds-label">Password:</span><span class="creds-val" style="color:#c9aa2e;">' . $pwd . '</span></div>
  </div>
  <p class="note" style="color:#bbb;">Click below to verify and directly sign in to the Admin dashboard:</p>
  <a href="' . $verifyLink . '" class="btn">&#x2713; Verify &amp; Sign In as Admin</a>
  <div class="divider"></div>
  <p class="note">Direct Login Page: <a href="' . $loginLink . '" style="color:#c9aa2e;text-decoration:none;">' . $loginLink . '</a></p>
  <div class="footer">&copy; ' . $yr . ' ClosetGo. All rights reserved.</div>
</div>
</body></html>';

                            // Send email
                            $emailSent = false;
                            try {
                                $mail = new PHPMailer(true);
                                $mail->isSMTP();
                                $mail->Host       = $smtpHost;
                                $mail->SMTPAuth   = true;
                                $mail->Username   = $smtpUser;
                                $mail->Password   = $smtpPass;
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port       = $smtpPort;
                                $mail->CharSet    = 'UTF-8';
                                $mail->Timeout    = 7;
                                $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

                                $mail->setFrom($smtpUser, 'ClosetGo Admin Portal');
                                $mail->addAddress($email, $fullName);
                                $mail->isHTML(true);
                                $mail->Subject = 'Your ClosetGo Admin Account Credentials';
                                $mail->Body    = $emailBodyHtml;
                                $mail->AltBody = "Welcome to ClosetGo Admin Team!\n\nEmail: {$email}\nPassword: {$password}\n\nLogin URL: {$loginLink}\nVerify URL: {$verifyLink}";
                                $mail->send();
                                $emailSent = true;
                            } catch (\Throwable $e) {
                                try {
                                    $mail->Port = 465;
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                    $mail->send();
                                    $emailSent = true;
                                } catch (\Throwable $e2) {}
                            }

                            if ($emailSent) {
                                $success_message = "Admin user created successfully! Login credentials (password) have been emailed to " . htmlspecialchars($email) . ".";
                            } else {
                                $success_message = "Admin user created successfully! (Password: <strong>" . htmlspecialchars($password) . "</strong>).";
                            }
                            $firstname = $lastname = $middlename = $suffix = $email = $password = '';
                        } else {
                            $errors[] = "Failed to create administrator account.";
                        }
                        $insStmt->close();
                    }
                }
                $checkStmt->close();
            }
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch all existing admins
$adminList = [];
try {
    $database = new Database();
    $conn = $database->getConnection();
    $res = $conn->query("SELECT id, firstname, lastname, middlename, suffix, email, created_at FROM admin_users ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['type'] = 'admin';
            $adminList[] = $row;
        }
    }
} catch (Exception $e) {}

// Fetch all existing staff
$staffList = [];
try {
    $database = new Database();
    $conn = $database->getConnection();
    $res2 = $conn->query("SELECT id, firstname, lastname, middlename, suffix, name, email, is_active, created_at FROM system_staffs ORDER BY id DESC");
    if ($res2) {
        while ($row2 = $res2->fetch_assoc()) {
            $row2['type'] = 'staff';
            $staffList[] = $row2;
        }
    }
} catch (Exception $e) {}

$totalAccounts = count($adminList) + count($staffList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin &amp; Staff - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin-modern.css">
    <link rel="stylesheet" href="../css/admin_mobile_css.css">
    <script src="../js/dark-mode.js"></script>
    <script>
    const buttonClickAudio = new Audio('../ringtone/button_click.MP3');
    buttonClickAudio.volume = 0.3;
    buttonClickAudio.preload = 'auto';
    
    function playButtonClickSound() {
        buttonClickAudio.currentTime = 0;
        buttonClickAudio.play().catch(e => {});
    }

    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('button, .btn, .btn-submit, a[href]');
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                if (!this.closest('.sidebar')) {
                    playButtonClickSound();
                }
            });
        });
    });

    function selectAccountType(type) {
        document.getElementById('accountTypeInput').value = type;
        const adminBtn = document.getElementById('typeBtnAdmin');
        const staffBtn = document.getElementById('typeBtnStaff');
        const titleSpan = document.getElementById('formTitleType');
        const submitBtnText = document.getElementById('submitBtnText');

        if (type === 'staff') {
            adminBtn.classList.remove('active');
            staffBtn.classList.add('active');
            titleSpan.textContent = 'Staff Member';
            submitBtnText.textContent = 'Create & Send Staff Credentials';
        } else {
            staffBtn.classList.remove('active');
            adminBtn.classList.add('active');
            titleSpan.textContent = 'Administrator';
            submitBtnText.textContent = 'Create & Send Admin Credentials';
        }
    }

    function togglePasswordVisibility() {
        const pwd = document.getElementById('passwordInput');
        const icon = document.getElementById('pwdToggleIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            pwd.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    function generateRandomPassword() {
        const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%&*';
        let newPass = '';
        for (let i = 0; i < 10; i++) {
            newPass += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        const pwdInput = document.getElementById('passwordInput');
        pwdInput.value = newPass;
        pwdInput.type = 'text';
        document.getElementById('pwdToggleIcon').className = 'fas fa-eye-slash';
    }

    let pendingDeleteType = null;
    let pendingDeleteId = null;

    function confirmDeleteAccount(type, id, name) {
        pendingDeleteType = type;
        pendingDeleteId = id;

        const roleLabel = type === 'staff' ? 'Staff Member' : 'Administrator';
        const titleEl = document.getElementById('deleteConfirmTitle');
        const descEl = document.getElementById('deleteConfirmDesc');
        const modal = document.getElementById('deleteAccountConfirmModal');

        if (titleEl) {
            titleEl.textContent = 'Remove ' + roleLabel + '?';
        }
        if (descEl) {
            descEl.innerHTML = 'Are you sure you want to remove <strong>' + escapeHtml(name) + '</strong>? This action cannot be undone.';
        }
        if (modal) {
            modal.classList.add('show');
        }
    }

    function closeDeleteAccountModal() {
        const modal = document.getElementById('deleteAccountConfirmModal');
        if (modal) {
            modal.classList.remove('show');
        }
        pendingDeleteType = null;
        pendingDeleteId = null;
    }

    function executeDeleteAccount() {
        if (!pendingDeleteType || !pendingDeleteId) return;
        document.getElementById('deleteTypeInput').value = pendingDeleteType;
        document.getElementById('deleteIdInput').value = pendingDeleteId;
        document.getElementById('deleteAccountForm').submit();
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteAccountModal();
        }
    });

    document.addEventListener('click', function(e) {
        const modal = document.getElementById('deleteAccountConfirmModal');
        if (modal && modal.classList.contains('show')) {
            if (!modal.contains(e.target) && !e.target.closest('.admin-action-btn-danger')) {
                closeDeleteAccountModal();
            }
        }
    });

    function filterDirectory(filter) {
        document.querySelectorAll('.filter-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('tab-' + filter).classList.add('active');

        const cards = document.querySelectorAll('.admin-user-card');
        cards.forEach(card => {
            const cardType = card.getAttribute('data-type');
            if (filter === 'all' || cardType === filter) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function showNotification(message, type = 'success', duration = 4000) {
        const modal = document.getElementById('notificationToast');
        if (!modal) return;
        const card = document.getElementById('notificationToastCard');
        const titleEl = document.getElementById('notificationTitle');
        const msgEl = document.getElementById('notificationMessage');
        const iconEl = document.getElementById('notificationIcon');

        if (card) {
            card.classList.remove('success-card', 'error-card');
            card.classList.add(type === 'error' ? 'error-card' : 'success-card');
        }
        if (titleEl) {
            titleEl.textContent = (type === 'error' ? 'Action Required' : 'Success');
        }
        if (iconEl) {
            iconEl.innerHTML = (type === 'error' 
                ? '<i class="fas fa-exclamation-triangle"></i>' 
                : '<i class="fas fa-check-circle"></i>');
        }
        if (msgEl) {
            msgEl.innerHTML = message;
        }

        modal.classList.add('show');
        clearTimeout(modal.hideTimeout);
        modal.hideTimeout = setTimeout(() => {
            modal.classList.remove('show');
        }, duration);
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($success_message)): ?>
            showNotification(<?php echo json_encode($success_message); ?>, 'success', 4500);
        <?php elseif (!empty($errors)): ?>
            showNotification(<?php echo json_encode(implode('<br>', array_map('htmlspecialchars', $errors))); ?>, 'error', 5500);
        <?php endif; ?>
    });
    </script>
    <style>
    /* Custom Confirmation Modal (Styled exactly like index.php Clear Chat Confirmation) */
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
            top: 25px;
            left: 50%;
            right: auto;
            transform: translate(-50%, -50px) scale(0.9);
            width: calc(100% - 32px);
            max-width: 340px;
            display: flex;
            justify-content: center;
        }

        .clear-chat-confirm-modal.show {
            transform: translate(-50%, 0) scale(1);
        }
    }

    .clear-chat-confirm-card {
        background: linear-gradient(145deg, #242424, #161616);
        border: 1px solid rgba(255, 215, 0, 0.45);
        border-radius: 14px;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.8), 0 0 20px rgba(255, 215, 0, 0.12);
        padding: 14px 16px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        max-width: 360px;
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        color: #fff;
        box-sizing: border-box;
    }

    .clear-chat-confirm-card.success-card {
        border-color: rgba(34, 197, 94, 0.55);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(34, 197, 94, 0.25);
    }

    .clear-chat-confirm-card.success-card .clear-chat-confirm-icon {
        background: rgba(34, 197, 94, 0.18);
        border: 1px solid rgba(34, 197, 94, 0.5);
        color: #22c55e;
    }

    .clear-chat-confirm-card.error-card {
        border-color: rgba(239, 68, 68, 0.55);
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.85), 0 0 20px rgba(239, 68, 68, 0.25);
    }

    .clear-chat-confirm-card.error-card .clear-chat-confirm-icon {
        background: rgba(239, 68, 68, 0.18);
        border: 1px solid rgba(239, 68, 68, 0.5);
        color: #ef4444;
    }

    .clear-chat-confirm-icon {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: rgba(255, 68, 68, 0.15);
        border: 1px solid rgba(255, 68, 68, 0.4);
        color: #ff5252;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .clear-chat-confirm-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .clear-chat-confirm-title {
        color: #ffffff;
        font-size: 13px;
        font-weight: 700;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.3;
    }

    .clear-chat-confirm-desc {
        color: #aaaaaa;
        font-size: 11px;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.35;
        margin-bottom: 8px;
    }

    .clear-chat-confirm-desc strong {
        color: #ffffff;
        font-weight: 600;
    }

    .clear-chat-confirm-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .clear-chat-btn-cancel {
        background: #2f2f2f;
        border: 1px solid #444;
        color: #ccc;
        font-size: 11px;
        font-weight: 600;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        padding: 5px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        outline: none;
    }

    .clear-chat-btn-cancel:hover {
        background: #3c3c3c;
        color: #fff;
    }

    .clear-chat-btn-delete {
        background: linear-gradient(135deg, #d32f2f, #b71c1c);
        border: none;
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        padding: 5px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        outline: none;
        box-shadow: 0 2px 8px rgba(211, 47, 47, 0.4);
    }

    .clear-chat-btn-delete:hover {
        background: linear-gradient(135deg, #e53935, #c62828);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(211, 47, 47, 0.6);
    }
    <?php if (isset($_GET['embed']) || isset($_GET['modal'])): ?>
    html, body {
        background: #14151a !important;
        height: 100% !important;
        overflow-x: hidden !important;
    }
    .shell {
        margin-left: 0 !important;
        margin-right: 0 !important;
        margin-top: 0 !important;
        padding: 20px 24px 40px 24px !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .sidebar, #mobileMenuToggle, #sidebarOverlay, #mobilePageTitle {
        display: none !important;
    }
    @media (max-width: 768px) {
        .shell {
            padding: 14px 12px 30px 12px !important;
        }
    }
    <?php endif; ?>
    </style>
</head>
<body>
    <!-- Top-Right Custom Notification Toast (Matches index.php Notification Toast) -->
    <div id="notificationToast" class="clear-chat-confirm-modal" onclick="this.classList.remove('show')" style="cursor: pointer;">
        <div class="clear-chat-confirm-card success-card" id="notificationToastCard">
            <div class="clear-chat-confirm-icon" id="notificationIcon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="clear-chat-confirm-body">
                <div class="clear-chat-confirm-title" id="notificationTitle">Success</div>
                <div class="clear-chat-confirm-desc" id="notificationMessage" style="margin-bottom: 0;">Your action completed successfully.</div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal (Styled like index.php Clear Chat Confirmation) -->
    <div id="deleteAccountConfirmModal" class="clear-chat-confirm-modal">
        <div class="clear-chat-confirm-card">
            <div class="clear-chat-confirm-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div class="clear-chat-confirm-body">
                <div class="clear-chat-confirm-title" id="deleteConfirmTitle">Remove Account?</div>
                <div class="clear-chat-confirm-desc" id="deleteConfirmDesc">Are you sure you want to remove this account? This action cannot be undone.</div>
                <div class="clear-chat-confirm-actions">
                    <button type="button" class="clear-chat-btn-cancel" onclick="closeDeleteAccountModal()">Cancel</button>
                    <button type="button" class="clear-chat-btn-delete" onclick="executeDeleteAccount()">Remove</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!isset($_GET['embed']) && !isset($_GET['modal'])): ?>
        <?php include 'sidebar.php'; ?>
    <?php endif; ?>

    <div class="shell">

        <!-- Two Column Management Grid -->
        <div class="admin-management-grid">
            <!-- Left Panel: Create Account Form with Floating Labels -->
            <div class="admin-card-panel">
                <div class="admin-panel-header">
                    <h2 class="admin-panel-title">
                        <i class="fas fa-user-plus"></i>
                        <span>Create <span id="formTitleType"><?php echo ($accountType === 'staff' ? 'Staff Member' : 'Administrator'); ?></span></span>
                    </h2>
                </div>

                <!-- Account Type Switcher -->
                <div class="account-type-selector">
                    <button type="button" class="account-type-btn <?php echo ($accountType !== 'staff' ? 'active' : ''); ?>" id="typeBtnAdmin" onclick="selectAccountType('admin')">
                        <i class="fas fa-user-shield"></i> Administrator
                    </button>
                    <button type="button" class="account-type-btn <?php echo ($accountType === 'staff' ? 'active' : ''); ?>" id="typeBtnStaff" onclick="selectAccountType('staff')">
                        <i class="fas fa-id-badge"></i> Staff Member
                    </button>
                </div>

                <form method="POST" action="add_admin.php<?php echo (isset($_GET['embed']) ? '?embed=1' : (isset($_GET['modal']) ? '?modal=1' : '')); ?>" id="addAccountForm" autocomplete="off">
                    <input type="hidden" name="action" value="add_account">
                    <input type="hidden" name="account_type" id="accountTypeInput" value="<?php echo htmlspecialchars($accountType); ?>">

                    <div class="admin-form-row">
                        <div class="floating-group">
                            <input type="text" id="firstname" name="firstname" class="floating-input" required 
                                   autocomplete="off" placeholder=" " value="<?php echo (!empty($errors) ? htmlspecialchars($firstname ?? '') : ''); ?>">
                            <label class="floating-label" for="firstname">First Name</label>
                        </div>

                        <div class="floating-group" id="middleNameGroup">
                            <input type="text" id="middlename" name="middlename" class="floating-input" 
                                   autocomplete="off" placeholder=" " value="<?php echo (!empty($errors) ? htmlspecialchars($middlename ?? '') : ''); ?>">
                            <label class="floating-label" for="middlename">Middle Name (Optional)</label>
                        </div>
                    </div>

                    <div class="admin-form-row">
                        <div class="floating-group">
                            <input type="text" id="lastname" name="lastname" class="floating-input" required 
                                   autocomplete="off" placeholder=" " value="<?php echo (!empty($errors) ? htmlspecialchars($lastname ?? '') : ''); ?>">
                            <label class="floating-label" for="lastname">Last Name</label>
                        </div>

                        <div class="floating-group" id="suffixGroup">
                            <input type="text" id="suffix" name="suffix" class="floating-input" 
                                   autocomplete="off" placeholder=" " list="suffixList" value="<?php echo (!empty($errors) ? htmlspecialchars($suffix ?? '') : ''); ?>">
                            <label class="floating-label" for="suffix">Suffix (Optional)</label>
                            <datalist id="suffixList">
                                <option value="Jr.">
                                <option value="Sr.">
                                <option value="II">
                                <option value="III">
                                <option value="IV">
                                <option value="V">
                            </datalist>
                        </div>
                    </div>

                    <div class="floating-group">
                        <input type="email" id="email" name="email" class="floating-input" required 
                               autocomplete="off" placeholder=" " value="<?php echo (!empty($errors) ? htmlspecialchars($email ?? '') : ''); ?>">
                        <label class="floating-label" for="email">Email Address</label>
                    </div>

                    <div class="floating-group" style="margin-bottom: 8px;">
                        <input type="password" id="passwordInput" name="password" class="floating-input" required 
                               autocomplete="new-password" placeholder=" " minlength="6" value="">
                        <label class="floating-label" for="passwordInput">Password (min 6 characters)</label>
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility()" title="Toggle password visibility">
                            <i class="fas fa-eye" id="pwdToggleIcon"></i>
                        </button>
                    </div>
                    <div style="font-size: 11px; color: #8a99ad; margin: -2px 0 16px 4px; line-height: 1.4;">
                        <i class="fas fa-info-circle" style="color: #4ecdc4; margin-right: 4px;"></i>
                        <span>An email can have both an Admin and Staff account, but each role must use a <strong>different password</strong>.</span>
                    </div>

                    <div>
                        <button type="button" class="admin-generate-btn" onclick="generateRandomPassword()">
                            <i class="fas fa-magic"></i> Generate Random Password
                        </button>
                    </div>

                    <div class="admin-btn-group">
                        <button type="submit" class="admin-btn-primary">
                            <i class="fas fa-paper-plane"></i> <span id="submitBtnText"><?php echo ($accountType === 'staff' ? 'Create & Send Staff Credentials' : 'Create & Send Admin Credentials'); ?></span>
                        </button>
                        <button type="button" class="admin-btn-secondary" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Panel: Accounts Directory with Filter Tabs -->
            <div class="admin-card-panel">
                <div class="admin-panel-header">
                    <h2 class="admin-panel-title">
                        <i class="fas fa-users-cog"></i>
                        <span>Directory</span>
                    </h2>
                    <div class="directory-filter-tabs">
                        <button type="button" class="filter-tab-btn active" id="tab-all" onclick="filterDirectory('all')">All (<?php echo $totalAccounts; ?>)</button>
                        <button type="button" class="filter-tab-btn" id="tab-admin" onclick="filterDirectory('admin')">Admins (<?php echo count($adminList); ?>)</button>
                        <button type="button" class="filter-tab-btn" id="tab-staff" onclick="filterDirectory('staff')">Staff (<?php echo count($staffList); ?>)</button>
                    </div>
                </div>

                <div class="admin-cards-list">
                    <?php if (empty($adminList) && empty($staffList)): ?>
                        <div class="admin-empty-state">
                            <i class="fas fa-user-shield"></i>
                            <p>No accounts found.</p>
                        </div>
                    <?php else: ?>
                        <!-- Admin Cards -->
                        <?php foreach ($adminList as $adm): ?>
                            <?php 
                                $isSelf = ($adm['id'] == $currentAdminId);
                                $initials = strtoupper(substr($adm['firstname'], 0, 1) . substr($adm['lastname'], 0, 1));
                                if (empty($initials)) $initials = 'AD';
                                $fullName = trim($adm['firstname'] . ' ' . (!empty($adm['middlename']) ? $adm['middlename'] . ' ' : '') . $adm['lastname'] . (!empty($adm['suffix']) ? ' ' . $adm['suffix'] : ''));
                            ?>
                            <div class="admin-user-card" data-type="admin">
                                <div class="admin-user-info-wrap">
                                    <div class="admin-avatar-circle"><?php echo htmlspecialchars($initials); ?></div>
                                    <div class="admin-details">
                                        <h4>
                                            <?php echo htmlspecialchars($fullName); ?>
                                            <?php if ($isSelf): ?>
                                                <span class="admin-badge-current">You</span>
                                            <?php else: ?>
                                                <span class="admin-badge-active">Admin</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($adm['email']); ?></p>
                                        <p><i class="fas fa-calendar-alt"></i> Added <?php echo date('M j, Y', strtotime($adm['created_at'])); ?></p>
                                    </div>
                                </div>

                                <div class="admin-actions">
                                    <?php if (!$isSelf): ?>
                                        <button type="button" class="admin-action-btn-danger" 
                                                title="Delete Administrator" 
                                                onclick="confirmDeleteAccount('admin', <?php echo $adm['id']; ?>, '<?php echo htmlspecialchars(addslashes($fullName)); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Staff Cards -->
                        <?php foreach ($staffList as $stf): ?>
                            <?php 
                                $stfFullName = trim(($stf['firstname'] ?? '') . ' ' . (!empty($stf['middlename']) ? $stf['middlename'] . ' ' : '') . ($stf['lastname'] ?? '') . (!empty($stf['suffix']) ? ' ' . $stf['suffix'] : ''));
                                if (empty($stfFullName)) $stfFullName = $stf['name'] ?? 'Staff Member';
                                $sInitials = strtoupper(substr($stf['firstname'] ?? '', 0, 1) . substr($stf['lastname'] ?? '', 0, 1));
                                if (empty($sInitials)) {
                                    $nameParts = explode(' ', trim($stfFullName));
                                    $sInitials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
                                }
                                if (empty($sInitials)) $sInitials = 'ST';
                            ?>
                            <div class="admin-user-card" data-type="staff">
                                <div class="admin-user-info-wrap">
                                    <div class="admin-avatar-circle staff-avatar"><?php echo htmlspecialchars($sInitials); ?></div>
                                    <div class="admin-details">
                                        <h4>
                                            <?php echo htmlspecialchars($stfFullName); ?>
                                            <span class="admin-badge-role-staff">Staff</span>
                                        </h4>
                                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($stf['email']); ?></p>
                                        <p><i class="fas fa-calendar-alt"></i> Added <?php echo date('M j, Y', strtotime($stf['created_at'])); ?></p>
                                    </div>
                                </div>

                                <div class="admin-actions">
                                    <button type="button" class="admin-action-btn-danger" 
                                            title="Delete Staff Member" 
                                            onclick="confirmDeleteAccount('staff', <?php echo $stf['id']; ?>, '<?php echo htmlspecialchars(addslashes($stfFullName)); ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Delete Account Action -->
    <form id="deleteAccountForm" method="POST" action="add_admin.php<?php echo (isset($_GET['embed']) ? '?embed=1' : (isset($_GET['modal']) ? '?modal=1' : '')); ?>" style="display:none;">
        <input type="hidden" name="action" value="delete_account">
        <input type="hidden" name="delete_type" id="deleteTypeInput" value="admin">
        <input type="hidden" name="delete_id" id="deleteIdInput" value="">
    </form>
</body>
</html>
