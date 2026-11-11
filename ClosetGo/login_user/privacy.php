<?php
// login_user/privacy.php - ClosetGo Privacy Policy
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../images/icon/JMB.png" type="image/png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #0a0a0a;
            color: #e0e0e0;
            font-family: 'Poppins', Arial, sans-serif;
            line-height: 1.7;
            padding-bottom: 60px;
        }

        /* ── Header / Top Nav ── */
        .policy-header {
            background: rgba(18, 18, 18, 0.95);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 16px 24px;
        }

        .header-inner {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #ffffff;
        }

        .brand-logo {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1.5px solid #ffd700;
            object-fit: cover;
        }

        .brand-name {
            font-size: 1.25rem;
            font-weight: 800;
            background: linear-gradient(90deg, #ffd700, #ffe066, #c9aa2e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 0.5px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ffd700;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255, 215, 0, 0.25);
            background: rgba(255, 215, 0, 0.05);
            transition: all 0.25s ease;
        }

        .back-btn:hover {
            background: rgba(255, 215, 0, 0.15);
            border-color: #ffd700;
            transform: translateY(-1px);
        }

        /* ── Hero Banner ── */
        .policy-hero {
            max-width: 1000px;
            margin: 40px auto 30px;
            padding: 0 20px;
            text-align: center;
        }

        .policy-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.25);
            border-radius: 999px;
            padding: 5px 16px;
            font-size: 0.76rem;
            color: #ffd700;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 14px;
        }

        .policy-title {
            font-size: clamp(2rem, 4vw, 2.6rem);
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 10px;
            letter-spacing: 0.3px;
        }

        .policy-updated {
            font-size: 0.85rem;
            color: #888888;
        }

        /* ── Content Card ── */
        .policy-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .policy-card {
            background: #181818;
            border: 1px solid rgba(255, 215, 0, 0.18);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 10px 45px rgba(0, 0, 0, 0.65);
        }

        .section-block {
            margin-bottom: 32px;
        }

        .section-block:last-child {
            margin-bottom: 0;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffd700;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 215, 0, 0.12);
        }

        .section-title i {
            font-size: 1.1rem;
            color: #ffd700;
        }

        .section-text {
            color: #cccccc;
            font-size: 0.94rem;
            margin-bottom: 12px;
        }

        .section-list {
            list-style: none;
            padding-left: 0;
            margin-top: 8px;
        }

        .section-list li {
            position: relative;
            padding-left: 24px;
            margin-bottom: 10px;
            font-size: 0.92rem;
            color: #bbbbbb;
        }

        .section-list li::before {
            content: '✦';
            position: absolute;
            left: 0;
            color: #ffd700;
            font-size: 0.8rem;
            top: 2px;
        }

        .policy-footer-nav {
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .nav-links {
            display: flex;
            gap: 18px;
        }

        .nav-links a {
            color: #ffd700;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .nav-links a:hover {
            color: #ffe033;
            text-decoration: underline;
        }

        .copy-text {
            font-size: 0.82rem;
            color: #666666;
        }

        @media (max-width: 600px) {
            .policy-header {
                padding: 12px 16px;
            }

            .brand-logo {
                width: 32px;
                height: 32px;
            }

            .brand-name {
                font-size: 1.1rem;
            }

            .back-btn {
                padding: 6px 12px;
                font-size: 0.78rem;
                gap: 5px;
            }

            .policy-hero {
                margin: 24px auto 20px;
                padding: 0 16px;
            }

            .policy-tag {
                padding: 4px 12px;
                font-size: 0.7rem;
                margin-bottom: 10px;
            }

            .policy-title {
                font-size: 1.6rem;
                margin-bottom: 6px;
            }

            .policy-updated {
                font-size: 0.78rem;
            }

            .policy-container {
                padding: 0 12px;
            }

            .policy-card {
                padding: 22px 16px;
                border-radius: 14px;
            }

            .section-block {
                margin-bottom: 24px;
            }

            .section-title {
                font-size: 1rem;
                gap: 8px;
                margin-bottom: 10px;
            }

            .section-title i {
                font-size: 0.95rem;
            }

            .section-text {
                font-size: 0.88rem;
                line-height: 1.6;
                margin-bottom: 10px;
            }

            .section-list li {
                font-size: 0.86rem;
                padding-left: 20px;
                margin-bottom: 8px;
                line-height: 1.55;
            }

            .policy-footer-nav {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                margin-top: 28px;
                padding-top: 20px;
            }

            .nav-links {
                gap: 12px;
                flex-wrap: wrap;
            }

            .nav-links a {
                font-size: 0.82rem;
            }

            .copy-text {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 360px) {
            .policy-header {
                padding: 10px 12px;
            }

            .policy-card {
                padding: 18px 12px;
                border-radius: 12px;
            }

            .policy-title {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="policy-header">
        <div class="header-inner">
            <a href="../index.php" class="brand-link">
                <img src="../images/icon/JMB.png" alt="ClosetGo Logo" class="brand-logo">
                <span class="brand-name">ClosetGo</span>
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="policy-hero">
        <span class="policy-tag"><i class="fas fa-user-shield"></i> Data Protection</span>
        <h1 class="policy-title">Privacy Policy</h1>
        <p class="policy-updated">Last Updated: August 2026</p>
    </section>

    <!-- Main Content -->
    <main class="policy-container">
        <div class="policy-card">
            
            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-info-circle"></i> 1. Introduction</h2>
                <p class="section-text">
                    At ClosetGo, we prioritize the protection and confidentiality of your personal information. This Privacy Policy outlines our practices regarding the collection, storage, use, and protection of the information you provide when using our services.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-database"></i> 2. Information We Collect</h2>
                <p class="section-text">
                    When you register an account, upload verification documents, or reserve items on ClosetGo, we may collect:
                </p>
                <ul class="section-list">
                    <li><strong>Account Credentials:</strong> Email address and encrypted password.</li>
                    <li><strong>Contact &amp; Profile Details:</strong> Full name, contact phone number, delivery address, and reservation dates.</li>
                    <li><strong>Verification &amp; ID Documents:</strong> Government or student identification cards uploaded strictly for identity confirmation and wardrobe rental security.</li>
                    <li><strong>Payment Documentation:</strong> Proof of payment receipts (such as GCash transaction screenshots and reference numbers) submitted to validate reservation orders.</li>
                    <li><strong>Authentication Data:</strong> One-Time Passwords (OTP) sent to your registered email for two-step account verification.</li>
                    <li><strong>Technical Data:</strong> IP address, device type, browser information, and session logs for security and performance optimization.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-tasks"></i> 3. How We Use Your Information</h2>
                <p class="section-text">
                    Your personal information is used exclusively to:
                </p>
                <ul class="section-list">
                    <li>Create, authenticate, and manage your user or staff account.</li>
                    <li>Verify customer identity and process, confirm, and fulfill your wardrobe reservations and rentals.</li>
                    <li>Validate payment transactions against administrative order records.</li>
                    <li>Send important security alerts, OTP codes, order confirmations, status notifications, and return reminders.</li>
                    <li>Improve our website performance, catalog selection, and customer experience.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-lock"></i> 4. Password, Document &amp; Data Security</h2>
                <p class="section-text">
                    We employ robust security measures to protect your data against unauthorized access, loss, or alteration:
                </p>
                <ul class="section-list">
                    <li>All user passwords are encrypted using state-of-the-art secure hashing algorithms (<code>BCRYPT / PASSWORD_DEFAULT</code>) and are never stored in plain text.</li>
                    <li>Uploaded ID credentials and payment receipts are stored securely and accessible only to authorized shop administrators and staff for verification purposes.</li>
                    <li>All email verification codes (OTP) expire automatically after 10 minutes.</li>
                    <li>Restricted database access ensures that your information remains confidential.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-share-alt"></i> 5. Zero Commercial Third-Party Sharing</h2>
                <p class="section-text">
                    ClosetGo does <strong>never sell, rent, or trade</strong> your personal information or uploaded documents to third parties. We only share necessary data with trusted service providers (e.g., email delivery systems for OTP dispatch) under strict confidentiality agreements.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-cookie-bite"></i> 6. Cookies &amp; Local Storage</h2>
                <p class="section-text">
                    We use cookies and local storage to keep you securely signed in and preserve your reservation preferences across browser sessions. You can disable cookies in your browser settings, though some features may require cookies to function properly.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-user-edit"></i> 7. Your Rights &amp; Choices</h2>
                <p class="section-text">
                    You have the right to access, update, or request the deletion of your personal data at any time through your ClosetGo account settings or by contacting our support team.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-envelope-open-text"></i> 8. Contact Our Privacy Officer</h2>
                <p class="section-text">
                    If you have any questions or concerns about this Privacy Policy, please contact our Privacy Support team at <strong style="color: #ffd700;">privacy@closetgo.com</strong>.
                </p>
            </div>

            <div class="policy-footer-nav">
                <div class="nav-links">
                    <a href="terms.php"><i class="fas fa-file-contract"></i> Terms &amp; Conditions</a>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
