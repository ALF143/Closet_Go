<?php
// login_user/about_us.php - ClosetGo About Us
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - ClosetGo</title>
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
        <span class="policy-tag"><i class="fas fa-heart"></i> Our Story</span>
        <h1 class="policy-title">About ClosetGo</h1>
        <p class="policy-updated">Fashion for Every Moment</p>
    </section>

    <!-- Main Content -->
    <main class="policy-container">
        <div class="policy-card">
            
            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-star"></i> Who We Are</h2>
                <p class="section-text">
                    ClosetGo is a premier wardrobe rental platform dedicated to making fashion accessible, sustainable, and affordable for everyone. We believe that everyone deserves to look and feel their best for every special occasion without the burden of purchasing expensive garments that may only be worn once.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-bullseye"></i> Our Mission</h2>
                <p class="section-text">
                    Our mission is to revolutionize the fashion industry by providing a convenient, eco-friendly alternative to traditional clothing ownership. We strive to:
                </p>
                <ul class="section-list">
                    <li>Offer a curated collection of high-quality gowns, dresses, and wardrobe pieces for all occasions.</li>
                    <li>Promote sustainable fashion by reducing textile waste through rental and reuse.</li>
                    <li>Provide exceptional customer service with seamless reservation and delivery experiences.</li>
                    <li>Make luxury fashion accessible to everyone at affordable prices.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-eye"></i> Our Vision</h2>
                <p class="section-text">
                    We envision a world where fashion is sustainable, inclusive, and stress-free. By building a community of fashion-forward individuals who share our values, we aim to become the leading wardrobe rental platform, setting new standards for quality, convenience, and environmental responsibility in the fashion industry.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-gem"></i> What We Offer</h2>
                <p class="section-text">
                    ClosetGo provides an extensive catalog of wardrobe essentials, including:
                </p>
                <ul class="section-list">
                    <li><strong>Evening Gowns & Formal Dresses:</strong> Perfect for galas, weddings, and red-carpet events.</li>
                    <li><strong>Cocktail & Party Dresses:</strong> Stylish options for nights out and celebrations.</li>
                    <li><strong>Professional Attire:</strong> Elegant suits and dresses for business meetings and conferences.</li>
                    <li><strong>Seasonal Collections:</strong> Trendy pieces updated regularly to keep you in style.</li>
                </ul>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-handshake"></i> Our Commitment to Quality</h2>
                <p class="section-text">
                    Every garment in our collection undergoes rigorous quality inspection and professional cleaning before and after each rental. We ensure that every piece you receive is in pristine condition, so you can step out with confidence and style.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-leaf"></i> Sustainability at Our Core</h2>
                <p class="section-text">
                    At ClosetGo, sustainability is not just a buzzword—it's the foundation of our business model. By choosing rental over purchase, you're helping to reduce the environmental impact of fast fashion, conserving resources, and contributing to a circular economy that benefits our planet.
                </p>
            </div>

            <div class="section-block">
                <h2 class="section-title"><i class="fas fa-envelope"></i> Get in Touch</h2>
                <p class="section-text">
                    We'd love to hear from you! Whether you have questions, feedback, or partnership inquiries, our team is here to help. Contact us at <strong style="color: #ffd700;">hello@closetgo.com</strong> or reach out through our social media channels.
                </p>
            </div>

            <div class="policy-footer-nav">
                <div class="nav-links">
                    <a href="terms.php"><i class="fas fa-file-contract"></i> Terms &amp; Conditions</a>
                    <a href="privacy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Log In</a>
                    <a href="sign_up.php"><i class="fas fa-user-plus"></i> Sign Up</a>
                </div>
                <div class="copy-text">
                    &copy; <?php echo date('Y'); ?> ClosetGo. All rights reserved.
                </div>
            </div>

        </div>
    </main>

</body>
</html>