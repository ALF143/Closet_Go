<?php
session_start();

// Already logged in → go to portal
if (!empty($_SESSION['staff_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error_message = '';

// Handle Login (AJAX or standard POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_POST['action']) && $_POST['action'] === 'login');

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
            exit;
        }
        $error_message = 'Email and password are required.';
    } else {
        try {
            require_once '../database/db_config.php';
            $db   = new Database();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("SELECT id, name, email, password FROM system_staffs WHERE email = ? AND is_active = 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $staff = $result->fetch_assoc();

                if (password_verify($password, $staff['password'])) {
                    // Set session variables directly without email/pin verification
                    $_SESSION['staff_logged_in']      = true;
                    $_SESSION['staff_id']             = $staff['id'];
                    $_SESSION['staff_name']           = $staff['name'];
                    $_SESSION['staff_email']          = $staff['email'];
                    $_SESSION['staff_email_verified'] = true;

                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'redirect' => 'index.php']);
                        exit;
                    }
                    header('Location: index.php');
                    exit;
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
                exit;
            }
            $error_message = 'Invalid email or password.';
        } catch (\Throwable $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
                exit;
            }
            $error_message = 'An unexpected server error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — ClosetGo</title>
    <link rel="icon" href="../images/icon/JMB.jpg" type="image/jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', Arial, sans-serif; background: #080808; min-height: 100vh; display: flex; align-items: stretch; }
        .login-layout { display: flex; width: 100%; min-height: 100vh; }

        /* Left branding — desktop only */
        .login-left { display: none; }

        /* Right panel */
        .login-right { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px 16px; background: #080808; }

        /* Login box */
        .login-box {
            background: #111;
            border: 1px solid rgba(255,215,0,.2);
            border-radius: 18px;
            padding: 38px 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 48px rgba(0,0,0,.75);
            transition: box-shadow .3s;
        }
        .login-box:hover { box-shadow: 0 14px 64px rgba(255,215,0,.1); }

        /* Staff badge */
        .staff-badge { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 22px; }
        .staff-badge-icon { width: 42px; height: 42px; background: linear-gradient(135deg, #b8960c, #ffd700); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 19px; color: #000; box-shadow: 0 4px 16px rgba(255,215,0,.3); }
        .staff-badge-text { font-size: .68rem; letter-spacing: .2em; text-transform: uppercase; color: #ffd700; font-weight: 700; }

        /* Headings */
        .login-heading { color: #fff; font-size: 1.25rem; font-weight: 700; text-align: center; margin: 0 0 6px; }
        .login-subheading { color: #666; font-size: .82rem; text-align: center; margin: 0 0 24px; }

        /* Inputs */
        .input-group { position: relative; margin-bottom: 18px; }
        .input-group input {
            width: 100%; padding: 12px 15px; background: transparent;
            border: 1px solid #2a2a2a; border-radius: 8px; font-size: 14px;
            color: #fff; outline: none; height: 50px; transition: border-color .25s;
            font-family: 'Poppins', Arial, sans-serif;
        }
        .input-group input:-webkit-autofill { -webkit-text-fill-color: #fff; -webkit-box-shadow: 0 0 0 1000px #111 inset; }
        .input-group input::placeholder { color: #444; }
        .input-group input:focus { border-color: #ffd700; }
        .input-group label {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: #555; font-size: 14px; pointer-events: none; transition: all .3s;
            background: #111; padding: 0 5px;
        }
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label { top: 0; font-size: 12px; color: #ffd700; }
        .password-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; width: 20px; height: 20px; filter: brightness(0) invert(1); }

        /* Button */
        .login-btn {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #7c6300, #b8960c);
            border: none; border-radius: 10px; color: #fff;
            font-size: .95rem; font-weight: 700; cursor: pointer;
            font-family: 'Poppins', Arial, sans-serif;
            transition: opacity .2s, transform .15s, background .2s; opacity: .55;
            display: flex; align-items: center; justify-content: center;
        }
        .login-btn.active { opacity: 1; background: linear-gradient(135deg, #b8960c, #ffd700); color: #000; }
        .login-btn.active:hover { transform: translateY(-1px); opacity: .95; box-shadow: 0 4px 18px rgba(255,215,0,.3); }
        .login-btn:disabled { cursor: not-allowed; opacity: .6; }

        /* Error box */
        .error-box {
            background: rgba(255,80,80,.07); color: #ff7070;
            border: 1px solid rgba(255,80,80,.22); padding: 10px 14px;
            border-radius: 8px; margin-bottom: 18px; font-size: .83rem;
            text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;
        }

        .back-link { display: block; text-align: center; margin-top: 18px; font-size: .82rem; color: #555; text-decoration: none; transition: color .2s; }
        .back-link:hover { color: #ffd700; }

        /* Desktop split */
        @media (min-width: 900px) {
            .login-left {
                display: flex; flex-direction: column; justify-content: center;
                flex: 1 1 55%; padding: 60px 64px;
                background: linear-gradient(135deg, #080808 0%, #0c0c0c 55%, #110d00 100%);
                border-right: 1px solid rgba(255,215,0,.08);
                position: relative; overflow: hidden;
            }
            .login-left::before { content:''; position:absolute; width:560px; height:560px; border-radius:50%; border:1px solid rgba(255,215,0,.07); top:-140px; left:-160px; pointer-events:none; }
            .login-left::after  { content:''; position:absolute; width:380px; height:380px; border-radius:50%; background:radial-gradient(circle,rgba(255,215,0,.04) 0%,transparent 70%); bottom:-100px; right:-100px; pointer-events:none; }
            .brand-shield { width:64px; height:64px; background:linear-gradient(135deg,rgba(255,215,0,.12),rgba(255,215,0,.04)); border:1px solid rgba(255,215,0,.25); border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:28px; color:#ffd700; margin-bottom:28px; }
            .brand-eyebrow { font-size:.7rem; letter-spacing:.24em; text-transform:uppercase; color:#ffd700; font-weight:700; margin-bottom:14px; opacity:.85; }
            .brand-headline { font-size:clamp(2.2rem,3.6vw,3.4rem); font-weight:800; line-height:1.1; color:#fff; margin:0 0 18px; }
            .brand-headline span { background:linear-gradient(90deg,#ffd700,#ffe770,#b8960c); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
            .brand-sub { font-size:.94rem; color:#666; line-height:1.7; max-width:390px; margin-bottom:36px; }
            .brand-badges { display:flex; gap:10px; flex-wrap:wrap; }
            .brand-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(255,215,0,.06); border:1px solid rgba(255,215,0,.18); border-radius:999px; padding:6px 14px; font-size:.75rem; color:#ffd700; font-weight:500; }
            .login-right { flex: 0 0 45%; max-width: 560px; padding: 60px 56px; }
            .login-box { max-width: 420px; padding: 42px 38px; }
        }
        @media (min-width: 1200px) { .login-left { padding: 60px 80px; } .login-right { padding: 60px 64px; } }
    </style>
</head>
<body>
<div class="login-layout">

    <!-- Left: Branding (desktop only) -->
    <div class="login-left">
        <div class="brand-shield"><i class="fas fa-user-tie"></i></div>
        <p class="brand-eyebrow">&#x2726; Restricted Access</p>
        <h1 class="brand-headline">ClosetGo<br><span>Staff Portal</span></h1>
        <p class="brand-sub">Authorised staff only. Scan QR codes, manage reservations, and update order statuses from a single secure interface.</p>
        <div class="brand-badges">
            <span class="brand-badge"><i class="fas fa-shield-alt" style="font-size:.7rem"></i> Secure Access</span>
            <span class="brand-badge">&#x2713; QR Scanner</span>
            <span class="brand-badge">&#x2713; Live Orders</span>
        </div>
    </div>

    <!-- Right: Login Form -->
    <div class="login-right">
        <div class="login-box">

            <div class="staff-badge">
                <div class="staff-badge-icon"><i class="fas fa-user-tie"></i></div>
                <span class="staff-badge-text">Staff Portal</span>
            </div>

            <!-- ── FORM SECTION ── -->
            <div class="login-form-section" id="loginFormSection">
                <h2 class="login-heading">Staff Sign In</h2>
                <p class="login-subheading">Enter your credentials to access the staff portal</p>

                <?php if ($error_message): ?>
                    <div class="error-box"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>

                <div class="error-box" id="ajaxError" style="display:none">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="ajaxErrorMsg"></span>
                </div>

                <form id="staffLoginForm" onsubmit="handleStaffLogin(event)" method="POST" action="login.php">
                    <div class="input-group">
                        <input type="email" id="staffEmail" name="email" autocomplete="off" placeholder=" " required>
                        <label for="staffEmail">Staff Email</label>
                    </div>

                    <div class="input-group">
                        <input type="password" id="staffPassword" name="password" autocomplete="off" placeholder=" " required>
                        <label for="staffPassword">Password</label>
                        <img src="../images/icon/showpassword.png" alt="Show" class="password-toggle" onclick="togglePwd()">
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        <i class="fas fa-sign-in-alt" style="margin-right:7px;font-size:.9rem"></i>Sign In
                    </button>
                </form>

                <a href="../index.php" class="back-link">&#8592; Back to Store</a>
            </div>

        </div>
    </div>

</div>
<script>
function togglePwd() {
    const i = document.getElementById('staffPassword'), t = document.querySelector('.password-toggle');
    if (i.type === 'password') {
        i.type = 'text';
        t.src = '../images/icon/hidepassword.png';
    } else {
        i.type = 'password';
        t.src = '../images/icon/showpassword.png';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const em = document.getElementById('staffEmail'), pw = document.getElementById('staffPassword'), btn = document.getElementById('loginBtn');
    function updateBtnState() {
        btn.classList.toggle('active', !!(em.value.trim() && pw.value));
    }
    [em, pw].forEach(el => {
        el.addEventListener('input', updateBtnState);
        el.addEventListener('change', updateBtnState);
    });
    updateBtnState();
});

function handleStaffLogin(e) {
    e.preventDefault();
    const email = document.getElementById('staffEmail').value.trim();
    const pass = document.getElementById('staffPassword').value;
    const btn = document.getElementById('loginBtn');
    const errBox = document.getElementById('ajaxError');
    const errMsg = document.getElementById('ajaxErrorMsg');

    if (!email || !pass) return;

    errBox.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:7px"></i>Signing in\u2026';

    const fd = new FormData();
    fd.append('action', 'login');
    fd.append('email', email);
    fd.append('password', pass);

    fetch('login.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check" style="margin-right:7px"></i>Success!';
            window.location.href = data.redirect || 'index.php';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt" style="margin-right:7px;font-size:.9rem"></i>Sign In';
            errMsg.textContent = data.message || 'Invalid email or password.';
            errBox.style.display = 'flex';
            btn.classList.add('active');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-sign-in-alt" style="margin-right:7px;font-size:.9rem"></i>Sign In';
        errMsg.textContent = 'Network error. Please try again.';
        errBox.style.display = 'flex';
    });
}
</script>
</body>
</html>
