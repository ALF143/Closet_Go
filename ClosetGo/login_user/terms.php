<?php
// login_user/terms.php - ClosetGo Terms and Conditions
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms &amp; Conditions - ClosetGo</title>
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
        <span class="policy-tag"><i class="fas fa-shield-alt"></i> Legal Agreement</span>
        <h1 class="policy-title">Terms &amp; Conditions</h1>
        <p class="policy-updated">Last Updated: August 2026</p>
    </section>

    <!-- Main Content -->
    <main class="policy-container">
        <div class="policy-card">
            
            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-file-contract"></i> 1. Acceptance of Terms</h2>
                <p class="section-text">
                    By creating an account, accessing, or using the ClosetGo platform, you acknowledge that you have read, understood, and agreed to be bound by these Terms and Conditions and our Privacy Policy. If you do not agree, please discontinue use of our services immediately.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-user-check"></i> 2. User Account &amp; Security</h2>
                <p class="section-text">
                    To reserve or rent wardrobe pieces and gowns, you must register for an account. You agree to:
                </p>
                <ul class="section-list">
                    <li>Provide accurate, current, and complete registration information.</li>
                    <li>Maintain the confidentiality of your password and account credentials.</li>
                    <li>Accept full responsibility for all activities that occur under your account.</li>
                    <li>Notify ClosetGo immediately of any unauthorized access or breach of security.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-calendar-check"></i> 3. Wardrobe Reservations &amp; Rentals</h2>
                <p class="section-text">
                    All reservations for dresses, gowns, and wardrobe items through ClosetGo are subject to item availability and confirmation:
                </p>
                <ul class="section-list">
                    <li>Items must be reserved in advance for specified dates with the required payment or downpayment.</li>
                    <li>Customers must handle all rented garments with proper care and adhere strictly to agreed return schedules.</li>
                    <li>Valid identification is required to confirm wardrobe rentals and safeguard store property.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-ban" style="color: #f87171;"></i> 4. Strict Non-Refundable Policy</h2>
                <p class="section-text" style="color: #fca5a5; font-weight: 500;">
                    All reservation fees, downpayments, and rental payments made through ClosetGo are strictly non-refundable once submitted and confirmed.
                </p>
                <ul class="section-list">
                    <li>Cancellations or failure to pick up the reserved item do not qualify for any refund of reservation, downpayment, or rental fees.</li>
                    <li>Direct item purchases (Buy orders) are final sales and cannot be returned, exchanged, or refunded.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-money-bill-wave"></i> 5. Payments, Security Deposits &amp; Overdue Fees</h2>
                <p class="section-text">
                    Prices for reservations, purchases, and security deposits are clearly indicated at the time of checkout:
                </p>
                <ul class="section-list">
                    <li>Security deposits (when collected) are refundable upon the safe, timely, and undamaged return of the rental items.</li>
                    <li><strong>Overdue Fees:</strong> Returning rented items past the agreed rental return date incurs daily overdue penalty fees until the item is returned to the shop.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> 6. Lost, Stolen &amp; Damaged Items Settlement</h2>
                <p class="section-text">
                    The renter assumes full responsibility for the condition and safekeeping of the garment throughout the rental period:
                </p>
                <ul class="section-list">
                    <li><strong>Lost Item Settlement:</strong> In accordance with shop policy, if a rented item is lost or unreturned, the settlement is calculated based on the item's selling price less the rental already paid plus any accrued overdue fees (<code>Settlement = Selling Price - Rental Paid + Overdue Fees</code>).</li>
                    <li><strong>Damage or Stains:</strong> Significant tears, burns, missing embellishments, or heavy stains will incur professional cleaning or repair fees deducted from the security deposit or billed to the customer.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-shield-virus"></i> 7. Prohibited Activities</h2>
                <p class="section-text">
                    You agree not to use ClosetGo for any unlawful purposes, attempt unauthorized system access, distribute malicious software, or sublease rented items to third parties without prior written consent from ClosetGo.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-sync-alt"></i> 8. Modifications to Service &amp; Terms</h2>
                <p class="section-text">
                    ClosetGo reserves the right to modify or update these terms at any time. Significant changes will be communicated via our website or email. Continued use of the platform after modifications constitutes acceptance of the revised terms.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-headset"></i> 9. Contact &amp; Customer Care</h2>
                <p class="section-text">
                    For inquiries regarding these Terms and Conditions or assistance with your reservations, please contact us at <strong style="color: #ffd700;">support@closetgo.com</strong>.
                </p>
            </div>

            <div class="policy-footer-nav">
                <div class="nav-links">
                    <a href="privacy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
