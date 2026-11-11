<?php
if (session_status() === PHP_SESSION_NONE) {
    if (ini_get('session.use_cookies')) {
        $cp = session_get_cookie_params();
        session_set_cookie_params($cp['lifetime'], '/', $cp['domain'], $cp['secure'], $cp['httponly']);
    }
    session_start();
}

// If admin is already logged in, redirect directly to dashboard
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';

// Handle Direct Login (No verification required)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../database/db_config.php';

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $isAjax   = (!empty($_POST['action']) && in_array($_POST['action'], ['login', 'send_verification']))
             || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (empty($email) || empty($password)) {
        $msg = 'Email and password are required.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        } else {
            $error_message = $msg;
        }
    } else {
        try {
            $db   = new Database();
            $conn = $db->getConnection();

            $sql  = "SELECT id, firstname, lastname, middlename, suffix, email, password FROM admin_users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $admin = $result->fetch_assoc();

                if (password_verify($password, $admin['password'])) {
                    // Set full admin session immediately
                    $_SESSION['admin_logged_in']      = true;
                    $_SESSION['admin_id']             = $admin['id'];
                    $_SESSION['admin_name']           = trim($admin['firstname'] . ' ' . (!empty($admin['middlename']) ? $admin['middlename'] . ' ' : '') . $admin['lastname'] . (!empty($admin['suffix']) ? ' ' . $admin['suffix'] : ''));
                    $_SESSION['admin_email']          = $admin['email'];
                    $_SESSION['admin_verified_at']    = time();
                    $_SESSION['admin_email_verified'] = true;

                    // Clean any old pending session variables
                    unset(
                        $_SESSION['pending_admin_token'],
                        $_SESSION['pending_admin_pin'],
                        $_SESSION['pending_admin_id'],
                        $_SESSION['pending_admin_name'],
                        $_SESSION['pending_admin_email'],
                        $_SESSION['pending_admin_expiry']
                    );

                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'redirect' => 'dashboard.php', 'message' => 'Login successful!']);
                        exit;
                    } else {
                        header('Location: dashboard.php');
                        exit();
                    }
                }
            }

            $msg = 'Invalid email or password.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            } else {
                $error_message = $msg;
            }
        } catch (\Throwable $e) {
            $msg = 'Server error: ' . $e->getMessage();
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            } else {
                $error_message = $msg;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/homepage.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

        /* ── Base ───────────────────────────────────── */
        body.login-page {
            margin: 0;
            padding: 0;
            background: #080808;
            font-family: 'Poppins', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: stretch;
        }

        .login-layout {
            display: flex;
            width: 100%;
            min-height: 100vh;
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
            background: #080808;
        }

        /* ── Admin Badge ────────────────────────────── */
        .admin-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .admin-badge-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #988726, #c9aa2e);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(152,135,38,.35);
        }

        .admin-badge-text {
            font-size: .65rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #988726;
            font-weight: 700;
        }

        /* ── Login Box ──────────────────────────────── */
        .login-heading {
            color: #ffffff;
            font-size: 1.15rem;
            font-weight: 600;
            margin: 0 0 6px 0;
            text-align: center;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .login-subheading {
            color: #555;
            font-size: .8rem;
            text-align: center;
            margin: 0 0 24px 0;
        }

        body.login-page .login-box {
            background: #111111;
            border: 1px solid rgba(152,135,38,.22);
            border-radius: 18px;
            padding: 34px 30px;
            width: 100%;
            max-width: 380px;
            min-width: 0;
            margin: 0;
            box-shadow: 0 8px 48px rgba(0,0,0,.75);
            transition: box-shadow .3s ease;
        }

        body.login-page .login-box:hover {
            box-shadow: 0 14px 64px rgba(152,135,38,.15);
        }

        /* ── Inputs ─────────────────────────────────── */
        .password-toggle {
            filter: brightness(0) saturate(100%) invert(100%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(100%) contrast(100%);
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px;
            background: transparent;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            font-size: 14px;
            color: #ffffff;
            outline: none;
            height: 50px;
            box-sizing: border-box;
            transition: border-color .25s ease;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .input-group input:-webkit-autofill,
        .input-group input:-webkit-autofill:hover,
        .input-group input:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff;
            -webkit-box-shadow: 0 0 0 1000px #111111 inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        .input-group input::placeholder { color: #444; }
        .input-group input:focus { border-color: #988726; }

        .input-group label {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #555;
            font-size: 14px;
            pointer-events: none;
            transition: all .3s ease;
            background: #111111;
            padding: 0 5px;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: 0;
            font-size: 12px;
            color: #988726;
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

        /* ── Error ──────────────────────────────────── */
        .admin-error-message {
            background: rgba(255,80,80,.07);
            color: #ff7070;
            border: 1px solid rgba(255,80,80,.22);
            padding: 11px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: .84rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* ── Login Button ────────────────────────────── */
        .login-btn {
            background: linear-gradient(135deg, #988726, #c9aa2e);
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
            box-shadow: 0 4px 18px rgba(152, 135, 38, 0.25);
            margin-top: 10px;
        }

        .login-btn:hover {
            background: linear-gradient(135deg, #af9b2c, #d8b835);
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(152, 135, 38, 0.4);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        @media (min-width: 900px) {
            body.login-page { background: #080808; }

            .login-left {
                display: flex;
                flex-direction: column;
                justify-content: center;
                flex: 1 1 55%;
                padding: 60px 64px;
                background: linear-gradient(135deg, #080808 0%, #0c0c0c 55%, #100c00 100%);
                border-right: 1px solid rgba(152,135,38,.1);
                position: relative;
                overflow: hidden;
            }

            /* decorative rings */
            .login-left::before {
                content: '';
                position: absolute;
                width: 560px;
                height: 560px;
                border-radius: 50%;
                border: 1px solid rgba(152,135,38,.08);
                top: -140px;
                left: -160px;
                pointer-events: none;
            }

            .login-left::after {
                content: '';
                position: absolute;
                width: 380px;
                height: 380px;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(152,135,38,.05) 0%, transparent 70%);
                bottom: -100px;
                right: -100px;
                pointer-events: none;
            }

            .brand-shield {
                width: 68px;
                height: 68px;
                background: linear-gradient(135deg, rgba(152,135,38,.14), rgba(152,135,38,.04));
                border: 1px solid rgba(152,135,38,.28);
                border-radius: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 30px;
                color: #c9aa2e;
                margin-bottom: 30px;
            }

            .brand-eyebrow {
                font-size: .7rem;
                letter-spacing: .24em;
                text-transform: uppercase;
                color: #988726;
                font-weight: 700;
                margin-bottom: 16px;
                opacity: .85;
            }

            .brand-headline {
                font-size: clamp(2.2rem, 3.6vw, 3.4rem);
                font-weight: 800;
                line-height: 1.1;
                color: #ffffff;
                margin: 0 0 18px 0;
            }

            .brand-headline span {
                background: linear-gradient(90deg, #c9aa2e, #f0d060, #988726);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .brand-sub {
                font-size: .94rem;
                color: #666;
                font-weight: 400;
                line-height: 1.7;
                max-width: 390px;
                margin-bottom: 38px;
            }

            .brand-badges {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .brand-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: rgba(152,135,38,.07);
                border: 1px solid rgba(152,135,38,.2);
                border-radius: 999px;
                padding: 6px 14px;
                font-size: .75rem;
                color: #b89d24;
                font-weight: 500;
                letter-spacing: .02em;
            }

            .login-right {
                flex: 0 0 45%;
                max-width: 560px;
                padding: 60px 56px;
            }

            body.login-page .login-box {
                max-width: 420px;
                padding: 42px 38px;
            }
        }

        @media (min-width: 1200px) {
            .login-left { padding: 60px 80px; }
            .login-right { padding: 60px 64px; }
        }
    </style>
</head>
<body class="login-page">
<div class="login-layout">

    <!-- Left Panel: admin branding (desktop only) -->
    <div class="login-left">
        <div class="brand-shield"><i class="fas fa-shield-alt"></i></div>
        <p class="brand-eyebrow">&#x2726; Restricted Access</p>
        <h1 class="brand-headline">ClosetGo<br><span>Admin Portal</span></h1>
        <p class="brand-sub">Authorised personnel only. Manage products, reservations, users, and platform settings from a single secure dashboard.</p>
        <div class="brand-badges">
            <span class="brand-badge"><i class="fas fa-lock" style="font-size:.7rem"></i> Encrypted Access</span>
            <span class="brand-badge">&#x2713; Role-Based Control</span>
            <span class="brand-badge">&#x2713; Audit Logging</span>
        </div>
    </div>

    <!-- Right Panel: admin login form -->
    <div class="login-right">
        <div class="login-box">

            <div class="admin-badge">
                <div class="admin-badge-icon"><i class="fas fa-shield-alt"></i></div>
                <span class="admin-badge-text">Admin Portal</span>
            </div>

            <!-- ── LOGIN FORM ──────────────────────── -->
            <div class="login-form-section" id="loginFormSection">
                <h2 class="login-heading">Administrator Login</h2>
                <p class="login-subheading">Enter your credentials to access the admin portal</p>

                <?php if ($error_message): ?>
                    <div class="admin-error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <div class="admin-error-message" id="ajaxError" style="display:none">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="ajaxErrorMsg"></span>
                </div>

                <form method="POST" action="adminlogin.php" id="adminLoginForm" onsubmit="handleLoginSubmit(event)">
                    <div class="input-group">
                        <input type="email" id="email" name="email" autocomplete="email" required placeholder=" " value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <label for="email">Admin Email</label>
                    </div>
                    <div class="input-group">
                        <input type="password" id="password" name="password" autocomplete="current-password" required placeholder=" ">
                        <label for="password">Password</label>
                        <img src="../images/icon/showpassword.png" alt="Show Password" class="password-toggle" onclick="togglePassword('password', this)">
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        <i class="fas fa-sign-in-alt" style="margin-right:7px;font-size:.9rem"></i>Sign In
                    </button>
                </form>

                <div class="signup-text" style="margin-top:16px;">
                    <a href="../index.php" class="signup-link">&#8592; Back to Home</a>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
function togglePassword(inputId, toggleIcon) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        toggleIcon.src = '../images/icon/hidepassword.png';
        toggleIcon.alt = 'Hide Password';
    } else {
        input.type = 'password';
        toggleIcon.src = '../images/icon/showpassword.png';
        toggleIcon.alt = 'Show Password';
    }
}

function handleLoginSubmit(event) {
    event.preventDefault();
    const form       = document.getElementById('adminLoginForm');
    const emailInput = document.getElementById('email');
    const passInput  = document.getElementById('password');
    const loginBtn   = document.getElementById('loginBtn');
    const errBox     = document.getElementById('ajaxError');
    const errMsg     = document.getElementById('ajaxErrorMsg');

    const email = emailInput.value.trim();
    const pass  = passInput.value;

    if (!email || !pass) {
        errMsg.textContent = 'Please enter both email and password.';
        errBox.style.display = 'flex';
        return;
    }

    errBox.style.display = 'none';
    loginBtn.disabled = true;
    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:7px"></i>Signing in…';

    const fd = new FormData();
    fd.append('action', 'login');
    fd.append('email', email);
    fd.append('password', pass);

    fetch('adminlogin.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loginBtn.innerHTML = '<i class="fas fa-check" style="margin-right:7px"></i>Success!';
            window.location.href = data.redirect || 'dashboard.php';
        } else {
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="fas fa-sign-in-alt" style="margin-right:7px;font-size:.9rem"></i>Sign In';
            errMsg.textContent = data.message || 'Invalid email or password.';
            errBox.style.display = 'flex';
        }
    })
    .catch(() => {
        // Fallback to standard form submission if fetch encountered an error
        form.submit();
    });
}
</script>
</body>
</html>
