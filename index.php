<?php
// PropFlow CRM - Landing Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>PropFlow CRM | Real Estate CRM</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* =========================
           NAVBAR
        ========================= */

        .navbar {
            width: 100%;
            height: 76px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 7%;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 23px;
            font-weight: 800;
            color: #0f172a;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            font-weight: 800;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-links a {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            transition: 0.2s ease;
        }

        .nav-links a:hover {
            color: #2563eb;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .login-btn {
            padding: 11px 19px;
            border: 1px solid #dbe2ea;
            border-radius: 9px;
            color: #334155;
            font-size: 14px;
            font-weight: 700;
            background: #ffffff;
        }

        .login-btn:hover {
            background: #f8fafc;
        }

        .register-btn {
            padding: 11px 20px;
            border-radius: 9px;
            color: #ffffff;
            background: #2563eb;
            font-size: 14px;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .register-btn:hover {
            background: #1d4ed8;
        }

        /* =========================
           HERO
        ========================= */

        .hero {
            min-height: 650px;
            padding: 90px 7% 80px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            align-items: center;
            gap: 70px;
            background:
                radial-gradient(circle at 85% 20%, #dbeafe 0, transparent 30%),
                radial-gradient(circle at 15% 80%, #e0e7ff 0, transparent 28%),
                #f8fafc;
        }

        .hero-content {
            max-width: 650px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border-radius: 30px;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 22px;
        }

        .badge span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2563eb;
        }

        .hero h1 {
            font-size: clamp(42px, 5vw, 68px);
            line-height: 1.05;
            letter-spacing: -2px;
            margin-bottom: 24px;
        }

        .hero h1 .highlight {
            color: #2563eb;
        }

        .hero p {
            max-width: 570px;
            color: #64748b;
            font-size: 18px;
            line-height: 1.7;
            margin-bottom: 32px;
        }

        .hero-buttons {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .primary-btn {
            padding: 14px 24px;
            border-radius: 10px;
            background: #2563eb;
            color: #ffffff;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);
            transition: 0.2s ease;
        }

        .primary-btn:hover {
            transform: translateY(-2px);
            background: #1d4ed8;
        }

        .secondary-btn {
            padding: 14px 24px;
            border-radius: 10px;
            border: 1px solid #dbe2ea;
            background: #ffffff;
            color: #334155;
            font-weight: 700;
            font-size: 15px;
        }

        .secondary-btn:hover {
            background: #f1f5f9;
        }

        /* =========================
           DASHBOARD PREVIEW
        ========================= */

        .dashboard-preview {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.12);
            transform: rotate(1deg);
        }

        .preview-top {
            height: 34px;
            display: flex;
            align-items: center;
            gap: 7px;
            border-bottom: 1px solid #eef2f7;
            margin-bottom: 18px;
        }

        .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #cbd5e1;
        }

        .preview-layout {
            display: grid;
            grid-template-columns: 90px 1fr;
            gap: 14px;
        }

        .preview-sidebar {
            min-height: 300px;
            border-radius: 12px;
            background: #0f172a;
            padding: 15px 10px;
        }

        .preview-brand {
            width: 35px;
            height: 35px;
            border-radius: 9px;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            margin-bottom: 28px;
        }

        .preview-menu {
            height: 28px;
            border-radius: 7px;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.08);
        }

        .preview-menu.active {
            background: #2563eb;
        }

        .preview-content {
            min-height: 300px;
        }

        .preview-title {
            height: 22px;
            width: 180px;
            background: #e2e8f0;
            border-radius: 5px;
            margin-bottom: 18px;
        }

        .preview-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }

        .stat-box {
            height: 70px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
        }

        .stat-line {
            width: 45%;
            height: 7px;
            background: #cbd5e1;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .stat-number {
            width: 35%;
            height: 14px;
            background: #2563eb;
            border-radius: 4px;
        }

        .preview-table {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .table-row {
            height: 45px;
            border-bottom: 1px solid #eef2f7;
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr;
            align-items: center;
            padding: 0 12px;
            gap: 10px;
        }

        .table-row:last-child {
            border-bottom: 0;
        }

        .small-line {
            height: 7px;
            border-radius: 5px;
            background: #dbe2ea;
        }

        /* =========================
           FEATURES
        ========================= */

        .section {
            padding: 90px 7%;
        }

        .section-header {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 50px;
        }

        .section-header h2 {
            font-size: 38px;
            margin-bottom: 14px;
        }

        .section-header p {
            color: #64748b;
            line-height: 1.7;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .feature-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            transition: 0.25s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 19px;
            margin-bottom: 10px;
        }

        .feature-card p {
            color: #64748b;
            line-height: 1.65;
            font-size: 14px;
        }

        .stage-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .stage-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 20px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .stage-pill.booked {
            background: #dcfce7;
            color: #15803d;
            border-color: #bbf7d0;
        }

        .stage-pill.lost {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fecaca;
        }

        /* =========================
           ROLES
        ========================= */

        .roles {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
            max-width: 900px;
            margin: 0 auto;
        }

        .role-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
        }

        .role-card h3 {
            font-size: 17px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .role-badge {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
        }

        .role-card ul {
            list-style: none;
            color: #64748b;
            font-size: 14px;
            line-height: 2;
        }

        .role-card ul li::before {
            content: "✓";
            color: #16a34a;
            font-weight: 800;
            margin-right: 8px;
        }

        /* =========================
           CTA
        ========================= */

        .cta {
            margin: 0 7% 80px;
            padding: 65px 30px;
            border-radius: 22px;
            text-align: center;
            background: linear-gradient(135deg, #0f172a, #1e3a8a);
            color: #ffffff;
        }

        .cta h2 {
            font-size: 36px;
            margin-bottom: 14px;
        }

        .cta p {
            color: #cbd5e1;
            margin-bottom: 28px;
        }

        .cta .primary-btn {
            display: inline-block;
            background: #ffffff;
            color: #1d4ed8;
            box-shadow: none;
        }

        /* =========================
           FOOTER
        ========================= */

        footer {
            background: #0f172a;
            color: #cbd5e1;
            padding: 28px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        footer .footer-logo {
            color: #ffffff;
            font-weight: 800;
            font-size: 18px;
        }

        footer p {
            font-size: 13px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 950px) {
            .nav-links {
                display: none;
            }

            .hero {
                grid-template-columns: 1fr;
                gap: 45px;
            }

            .dashboard-preview {
                max-width: 700px;
                width: 100%;
                margin: auto;
            }

            .features {
                grid-template-columns: repeat(2, 1fr);
            }

            .roles {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .navbar {
                padding: 0 5%;
            }

            .logo {
                font-size: 19px;
            }

            .logo-icon {
                width: 38px;
                height: 38px;
            }

            .register-btn {
                display: none;
            }

            .hero {
                padding: 65px 5% 60px;
            }

            .hero h1 {
                font-size: 43px;
                letter-spacing: -1px;
            }

            .hero p {
                font-size: 16px;
            }

            .section {
                padding: 65px 5%;
            }

            .section-header h2 {
                font-size: 30px;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .dashboard-preview {
                padding: 10px;
                transform: none;
            }

            .preview-sidebar {
                min-height: 250px;
            }

            .preview-content {
                min-height: 250px;
            }

            .preview-stats {
                grid-template-columns: 1fr;
            }

            .stat-box:nth-child(3) {
                display: none;
            }

            .table-row {
                grid-template-columns: 1fr 1fr;
            }

            .table-row div:last-child {
                display: none;
            }

            .cta {
                margin: 0 5% 60px;
                padding: 50px 20px;
            }

            .cta h2 {
                font-size: 29px;
            }

            footer {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<header class="navbar">

    <a href="index.php" class="logo">
        <span class="logo-icon">P</span>
        PropFlow CRM
    </a>

    <nav class="nav-links">
        <a href="#features">Features</a>
        <a href="#roles">Roles</a>
        <a href="#about">About</a>
    </nav>

    <div class="nav-actions">
        <a href="login.php" class="login-btn">Login</a>
        <a href="register.php" class="register-btn">Get Started</a>
    </div>

</header>


<!-- HERO -->
<section class="hero">

    <div class="hero-content">

        <div class="badge">
            <span></span>
            Real Estate Sales CRM
        </div>

        <h1>
            Manage leads, properties and
            <span class="highlight">bookings</span> in one place.
        </h1>

        <p>
            PropFlow CRM helps real estate sales teams move leads through a
            full pipeline — from first contact to a confirmed unit booking —
            with role-based access for Admins and Sales Employees.
        </p>

        <div class="hero-buttons">
            <a href="login.php" class="primary-btn">
                Login to CRM →
            </a>

            <a href="register.php" class="secondary-btn">
                Create Account
            </a>
        </div>

    </div>


    <!-- CRM PREVIEW -->
    <div class="dashboard-preview">

        <div class="preview-top">
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
        </div>

        <div class="preview-layout">

            <div class="preview-sidebar">

                <div class="preview-brand">P</div>

                <div class="preview-menu active"></div>
                <div class="preview-menu"></div>
                <div class="preview-menu"></div>
                <div class="preview-menu"></div>
                <div class="preview-menu"></div>

            </div>

            <div class="preview-content">

                <div class="preview-title"></div>

                <div class="preview-stats">

                    <div class="stat-box">
                        <div class="stat-line"></div>
                        <div class="stat-number"></div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-line"></div>
                        <div class="stat-number"></div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-line"></div>
                        <div class="stat-number"></div>
                    </div>

                </div>

                <div class="preview-table">

                    <div class="table-row">
                        <div class="small-line"></div>
                        <div class="small-line"></div>
                        <div class="small-line"></div>
                    </div>

                    <div class="table-row">
                        <div class="small-line"></div>
                        <div class="small-line"></div>
                        <div class="small-line"></div>
                    </div>

                    <div class="table-row">
                        <div class="small-line"></div>
                        <div class="small-line"></div>
                        <div class="small-line"></div>
                    </div>

                    <div class="table-row">
                        <div class="small-line"></div>
                        <div class="small-line"></div>
                        <div class="small-line"></div>
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- FEATURES -->
<section class="section" id="features">

    <div class="section-header">

        <h2>Everything you need to manage sales</h2>

        <p>
            Keep your leads, properties and bookings organized
            with a complete real estate CRM workflow.
        </p>

    </div>


    <div class="features">

        <div class="feature-card">

            <div class="feature-icon">L</div>

            <h3>Lead Management</h3>

            <p>
                Create, edit, search and view leads. Assign each
                lead to a sales employee and log notes with
                follow-up dates.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">P</div>

            <h3>Lead Stage Pipeline</h3>

            <p>
                Move leads through a clear 7-stage pipeline so
                every deal's status is always visible.
            </p>

            <div class="stage-pills">
                <span class="stage-pill">New</span>
                <span class="stage-pill">Contacted</span>
                <span class="stage-pill">Site Visit</span>
                <span class="stage-pill">Interested</span>
                <span class="stage-pill">Negotiation</span>
                <span class="stage-pill booked">Booked</span>
                <span class="stage-pill lost">Lost</span>
            </div>

        </div>


        <div class="feature-card">

            <div class="feature-icon">U</div>

            <h3>Property & Unit Management</h3>

            <p>
                Organize projects, buildings and units with
                price, type and real-time availability.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">B</div>

            <h3>Booking Management</h3>

            <p>
                Connect a lead or customer to a property unit,
                with built-in checks so two sales employees can
                never book the same unit.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">F</div>

            <h3>Follow-up Tracking</h3>

            <p>
                Stay on top of today's, upcoming and
                overdue customer follow-ups.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">R</div>

            <h3>Dashboard & Reports</h3>

            <p>
                See leads, follow-ups and bookings at a glance,
                with insight into sales performance and
                conversion.
            </p>

        </div>

    </div>

</section>


<!-- ROLES -->
<section class="section" id="roles">

    <div class="section-header">

        <h2>Built for your whole sales team</h2>

        <p>
            Role-based access keeps every user focused on what
            matters to them.
        </p>

    </div>

    <div class="roles">

        <div class="role-card">

            <h3>
                <span class="role-badge">A</span>
                Admin
            </h3>

            <ul>
                <li>Full visibility into leads, properties and bookings</li>
                <li>Manage projects, buildings and units</li>
                <li>Assign leads to sales employees</li>
                <li>View team-wide dashboard and reports</li>
            </ul>

        </div>

        <div class="role-card">

            <h3>
                <span class="role-badge">S</span>
                Sales Employee
            </h3>

            <ul>
                <li>Manage assigned leads and follow-ups</li>
                <li>Update lead stage as deals progress</li>
                <li>Book available units for a lead</li>
                <li>View personal performance dashboard</li>
            </ul>

        </div>

    </div>

</section>


<!-- ABOUT -->
<section class="section" id="about">

    <div class="section-header">

        <h2>Built for modern real estate teams</h2>

        <p>
            PropFlow CRM brings your sales workflow into one
            centralized platform so your team can focus on
            converting leads and closing more deals.
        </p>

    </div>

</section>


<!-- CTA -->
<section class="cta">

    <h2>Ready to manage your sales better?</h2>

    <p>
        Start using PropFlow CRM and bring your real estate
        workflow into one place.
    </p>

    <a href="login.php" class="primary-btn">
        Access PropFlow CRM →
    </a>

</section>


<!-- FOOTER -->
<footer>

    <div class="footer-logo">
        PropFlow CRM
    </div>

    <p>
        © <?php echo date('Y'); ?> PropFlow CRM. All rights reserved.
    </p>

</footer>

</body>
</html>