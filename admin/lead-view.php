<?php

require_once "../includes/auth.php";

requireRole("admin");

$user = currentUser();

$leadId = (int)($_GET["id"] ?? 0);

if ($leadId <= 0) {
    header("Location: leads.php");
    exit;
}


/* =====================================================
   FETCH LEAD
===================================================== */

$stmt = $pdo->prepare("
    SELECT
        l.id,
        l.name,
        l.phone,
        l.email,
        l.source,
        l.stage,
        l.assigned_to,
        l.follow_up_date,
        l.notes,
        l.created_at,
        l.updated_at,
        u.name AS assigned_name,
        u.email AS assigned_email
    FROM leads l
    LEFT JOIN users u
        ON l.assigned_to = u.id
    WHERE l.id = ?
    LIMIT 1
");

$stmt->execute([$leadId]);

$lead = $stmt->fetch();


if (!$lead) {
    header("Location: leads.php");
    exit;
}


/* =====================================================
   FETCH BOOKING INFORMATION
===================================================== */

$stmt = $pdo->prepare(" SELECT
        b.id,
        b.booking_date,
        b.amount,
        b.status,
        un.unit_number,
        un.type AS unit_type,
        un.floor,
        bu.name AS building_name,
        p.name AS project_name,
        p.location AS project_location,
        usr.name AS booked_by_name
    FROM bookings b

    INNER JOIN units un
        ON b.unit_id = un.id

    INNER JOIN buildings bu
        ON un.building_id = bu.id

    INNER JOIN projects p
        ON bu.project_id = p.id

    INNER JOIN users usr
        ON b.booked_by = usr.id

    WHERE b.lead_id = ?

    ORDER BY b.created_at DESC
    LIMIT 1
");

$stmt->execute([$leadId]);

$booking = $stmt->fetch();


/* =====================================================
   HELPERS
===================================================== */

$initial = strtoupper(
    substr($lead["name"], 0, 1)
);

$stageClass = strtolower(
    str_replace(
        " ",
        "-",
        $lead["stage"]
    )
);

$isToday =
    $lead["follow_up_date"] === date("Y-m-d");

$isOverdue =
    !empty($lead["follow_up_date"]) &&
    $lead["follow_up_date"] < date("Y-m-d") &&
    !in_array(
        $lead["stage"],
        ["Booked", "Lost"],
        true
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($lead["name"]) ?> | PropFlow CRM
    </title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --dark: #0f172a;
            --text: #334155;
            --muted: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --white: #ffffff;
            --success: #15803d;
            --danger: #dc2626;
        }

        body {
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }


        /* =================================================
           SIDEBAR
        ================================================= */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;

            width: 245px;

            background: var(--dark);

            padding: 25px 16px;

            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;

            padding: 8px 12px;

            margin-bottom: 35px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;

            border-radius: 11px;

            background: rgba(255,255,255,.12);

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 19px;
        }

        .brand-name {
            color: white;
            font-size: 19px;
            font-weight: 700;
        }

        .menu-title {
            color: #64748b;

            font-size: 10px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 1px;

            padding: 0 12px;

            margin-bottom: 10px;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav a {
            text-decoration: none;

            color: #94a3b8;

            padding: 12px 13px;

            border-radius: 9px;

            font-size: 13px;
            font-weight: 500;

            display: flex;
            align-items: center;

            gap: 11px;

            transition: .2s;
        }

        .nav a:hover {
            background: rgba(255,255,255,.06);
            color: white;
        }

        .nav a.active {
            background: var(--primary);
            color: white;

            box-shadow:
                0 6px 16px rgba(37,99,235,.22);
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 15px;
        }

        .sidebar-bottom {
            position: absolute;

            left: 16px;
            right: 16px;

            bottom: 20px;

            border-top:
                1px solid rgba(255,255,255,.08);

            padding-top: 15px;
        }


        /* =================================================
           MAIN
        ================================================= */

        .main {
            margin-left: 245px;
            min-height: 100vh;
        }

        .topbar {
            height: 72px;

            background: white;

            border-bottom:
                1px solid var(--border);

            display: flex;
            align-items: center;
            justify-content: flex-end;

            padding: 0 32px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .avatar {
            width: 38px;
            height: 38px;

            border-radius: 50%;

            background: #dbeafe;
            color: #1d4ed8;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 13px;
            font-weight: 700;
        }

        .profile-name {
            font-size: 13px;
            font-weight: 600;

            color: var(--dark);
        }

        .profile-role {
            font-size: 11px;

            color: var(--muted);

            text-transform: capitalize;
        }


        /* =================================================
           CONTENT
        ================================================= */

        .content {
            padding: 32px;
        }

        .back-link {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            text-decoration: none;

            color: var(--muted);

            font-size: 12px;

            margin-bottom: 20px;

            transition: .2s;
        }

        .back-link:hover {
            color: var(--primary);
        }


        /* =================================================
           PROFILE HEADER
        ================================================= */

        .lead-header {
            background: white;

            border: 1px solid var(--border);

            border-radius: 16px;

            padding: 25px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 20px;
        }

        .lead-profile {
            display: flex;

            align-items: center;

            gap: 16px;
        }

        .large-avatar {
            width: 62px;
            height: 62px;

            border-radius: 16px;

            background: #eff6ff;

            color: var(--primary);

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 22px;

            font-weight: 700;
        }

        .lead-title h1 {
            color: var(--dark);

            font-size: 23px;

            letter-spacing: -.5px;

            margin-bottom: 5px;
        }

        .lead-title p {
            color: var(--muted);

            font-size: 12px;
        }

        .header-actions {
            display: flex;

            align-items: center;

            gap: 9px;
        }

        .edit-btn {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            text-decoration: none;

            padding: 10px 14px;

            border-radius: 8px;

            background: var(--primary);

            color: white;

            font-size: 12px;

            font-weight: 600;
        }

        .edit-btn:hover {
            background: var(--primary-dark);
        }


        /* =================================================
           STAGE
        ================================================= */

        .stage-badge {
            display: inline-flex;

            align-items: center;

            padding: 7px 12px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

            background: #eff6ff;

            color: #1d4ed8;
        }

        .stage-badge.new {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .stage-badge.contacted {
            background: #f1f5f9;
            color: #475569;
        }

        .stage-badge.site-visit {
            background: #fefce8;
            color: #a16207;
        }

        .stage-badge.interested {
            background: #f0fdf4;
            color: #15803d;
        }

        .stage-badge.negotiation {
            background: #fff7ed;
            color: #c2410c;
        }

        .stage-badge.booked {
            background: #ecfdf5;
            color: #047857;
        }

        .stage-badge.lost {
            background: #fef2f2;
            color: #b91c1c;
        }


        /* =================================================
           GRID
        ================================================= */

        .details-grid {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;
        }

        .card {
            background: white;

            border: 1px solid var(--border);

            border-radius: 15px;

            overflow: hidden;
        }

        .card-header {
            padding: 18px 20px;

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;
        }

        .card-header h2 {
            color: var(--dark);

            font-size: 14px;

            font-weight: 650;
        }

        .card-body {
            padding: 20px;
        }


        /* =================================================
           INFORMATION
        ================================================= */

        .info-list {
            display: grid;

            gap: 17px;
        }

        .info-item {
            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding-bottom: 15px;

            border-bottom:
                1px solid #f1f5f9;
        }

        .info-item:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            color: #94a3b8;

            font-size: 11px;

            font-weight: 500;
        }

        .info-value {
            color: var(--dark);

            font-size: 12px;

            font-weight: 600;

            text-align: right;

            max-width: 60%;

            word-break: break-word;
        }

        .info-value a {
            color: var(--primary);
            text-decoration: none;
        }


        /* =================================================
           FOLLOW-UP
        ================================================= */

        .followup-box {
            display: flex;

            align-items: center;

            gap: 15px;

            padding: 17px;

            border-radius: 12px;

            background: #f8fafc;

            border: 1px solid var(--border);

            margin-bottom: 18px;
        }

        .calendar-icon {
            width: 43px;
            height: 43px;

            border-radius: 11px;

            background: #eff6ff;

            color: var(--primary);

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 18px;
        }

        .followup-label {
            color: var(--muted);

            font-size: 10px;

            margin-bottom: 3px;
        }

        .followup-date {
            color: var(--dark);

            font-size: 14px;

            font-weight: 700;
        }

        .today {
            color: #dc2626;
        }

        .overdue {
            color: #dc2626;

            font-size: 10px;

            font-weight: 600;

            margin-left: 5px;
        }


        /* =================================================
           NOTES
        ================================================= */

        .notes {
            color: var(--text);

            font-size: 13px;

            line-height: 1.8;

            white-space: pre-wrap;

            background: #f8fafc;

            border: 1px solid var(--border);

            border-radius: 11px;

            padding: 16px;
        }

        .no-data {
            color: #94a3b8;

            font-size: 12px;

            font-style: italic;
        }


        /* =================================================
           BOOKING
        ================================================= */

        .booking-card {
            grid-column: 1 / -1;
        }

        .booking-summary {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 18px;
        }

        .booking-item {
            padding: 15px;

            background: #f8fafc;

            border: 1px solid var(--border);

            border-radius: 10px;
        }

        .booking-label {
            color: #94a3b8;

            font-size: 10px;

            margin-bottom: 6px;
        }

        .booking-value {
            color: var(--dark);

            font-size: 13px;

            font-weight: 650;
        }

        .confirmed {
            color: var(--success);
        }

        .booking-project {
            padding: 15px;

            border-radius: 11px;

            background: #eff6ff;

            border: 1px solid #dbeafe;
        }

        .booking-project strong {
            color: #1e3a8a;

            font-size: 13px;
        }

        .booking-project p {
            color: #64748b;

            font-size: 11px;

            margin-top: 4px;
        }


        /* =================================================
           ACTIVITY
        ================================================= */

        .activity {
            position: relative;

            padding-left: 25px;
        }

        .activity::before {
            content: "";

            position: absolute;

            left: 6px;

            top: 4px;

            bottom: 4px;

            width: 1px;

            background: var(--border);
        }

        .activity-item {
            position: relative;

            margin-bottom: 20px;
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-dot {
            position: absolute;

            left: -23px;

            top: 2px;

            width: 13px;
            height: 13px;

            border-radius: 50%;

            background: #dbeafe;

            border: 3px solid white;

            box-shadow:
                0 0 0 1px #bfdbfe;
        }

        .activity-title {
            color: var(--dark);

            font-size: 12px;

            font-weight: 600;

            margin-bottom: 3px;
        }

        .activity-time {
            color: #94a3b8;

            font-size: 10px;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1000px) {

            .booking-summary {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }

        @media (max-width: 750px) {

            .sidebar {
                width: 70px;

                padding: 20px 10px;
            }

            .brand {
                justify-content: center;

                padding: 5px;

                margin-bottom: 25px;
            }

            .brand-name,
            .menu-title,
            .nav a span {
                display: none;
            }

            .nav a {
                justify-content: center;

                padding: 12px;
            }

            .main {
                margin-left: 70px;
            }

            .topbar {
                padding: 0 18px;
            }

            .content {
                padding: 22px 16px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .booking-card {
                grid-column: auto;
            }

            .lead-header {
                align-items: flex-start;

                flex-direction: column;
            }
        }

        @media (max-width: 500px) {

            .lead-profile {
                align-items: flex-start;
            }

            .large-avatar {
                width: 50px;
                height: 50px;

                font-size: 18px;
            }

            .lead-title h1 {
                font-size: 19px;
            }

            .booking-summary {
                grid-template-columns: 1fr;
            }

            .info-item {
                flex-direction: column;

                gap: 5px;
            }

            .info-value {
                max-width: 100%;

                text-align: left;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            🏢
        </div>

        <div class="brand-name">
            PropFlow
        </div>

    </div>


    <div class="menu-title">
        Workspace
    </div>


    <nav class="nav">

        <a href="dashboard.php">

            <span class="nav-icon">▦</span>

            <span>Dashboard</span>

        </a>


        <a
            href="leads.php"
            class="active"
        >

            <span class="nav-icon">👥</span>

            <span>Leads</span>

        </a>


        <a href="pipeline.php">

            <span class="nav-icon">◈</span>

            <span>Pipeline</span>

        </a>


        <a href="properties.php">

            <span class="nav-icon">⌂</span>

            <span>Properties</span>

        </a>


        <a href="bookings.php">

            <span class="nav-icon">✓</span>

            <span>Bookings</span>

        </a>


        <a href="employees.php">

            <span class="nav-icon">♙</span>

            <span>Team</span>

        </a>

    </nav>


    <div class="sidebar-bottom">

        <nav class="nav">

            <a href="settings.php">

                <span class="nav-icon">⚙</span>

                <span>Settings</span>

            </a>


            <a href="../logout.php">

                <span class="nav-icon">↪</span>

                <span>Logout</span>

            </a>

        </nav>

    </div>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <header class="topbar">

        <div class="profile">

            <div class="avatar">

                <?= strtoupper(
                    substr(
                        $user["name"],
                        0,
                        1
                    )
                ) ?>

            </div>


            <div>

                <div class="profile-name">

                    <?= htmlspecialchars(
                        $user["name"]
                    ) ?>

                </div>

                <div class="profile-role">

                    <?= htmlspecialchars(
                        $user["role"]
                    ) ?>

                </div>

            </div>

        </div>

    </header>


    <section class="content">


        <!-- BACK -->

        <a
            href="leads.php"
            class="back-link"
        >
            ← Back to Leads
        </a>


        <!-- =================================================
             LEAD HEADER
        ================================================= -->

        <div class="lead-header">


            <div class="lead-profile">

                <div class="large-avatar">

                    <?= htmlspecialchars($initial) ?>

                </div>


                <div class="lead-title">

                    <h1>

                        <?= htmlspecialchars(
                            $lead["name"]
                        ) ?>

                    </h1>

                    <p>

                        Lead #<?= (int)$lead["id"] ?>

                        · Created

                        <?= date(
                            "d M Y",
                            strtotime(
                                $lead["created_at"]
                            )
                        ) ?>

                    </p>

                </div>

            </div>


            <div class="header-actions">

                <span
                    class="stage-badge <?= htmlspecialchars($stageClass) ?>"
                >

                    <?= htmlspecialchars(
                        $lead["stage"]
                    ) ?>

                </span>


                <a
                    href="leads.php"
                    class="edit-btn"
                >
                    ✏️ Edit Lead
                </a>

            </div>

        </div>


        <!-- =================================================
             DETAILS
        ================================================= -->

        <div class="details-grid">


            <!-- CONTACT DETAILS -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Contact Information
                    </h2>

                </div>


                <div class="card-body">

                    <div class="info-list">


                        <div class="info-item">

                            <span class="info-label">
                                Full Name
                            </span>

                            <span class="info-value">

                                <?= htmlspecialchars(
                                    $lead["name"]
                                ) ?>

                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Phone
                            </span>

                            <span class="info-value">

                                <a
                                    href="tel:<?= htmlspecialchars($lead["phone"]) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $lead["phone"]
                                    ) ?>

                                </a>

                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Email
                            </span>

                            <span class="info-value">

                                <?php if ($lead["email"]): ?>

                                    <a
                                        href="mailto:<?= htmlspecialchars($lead["email"]) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $lead["email"]
                                        ) ?>

                                    </a>

                                <?php else: ?>

                                    <span class="no-data">
                                        Not provided
                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Lead Source
                            </span>

                            <span class="info-value">

                                <?= $lead["source"]
                                    ? htmlspecialchars($lead["source"])
                                    : "Not specified"
                                ?>

                            </span>

                        </div>


                    </div>

                </div>

            </div>


            <!-- SALES DETAILS -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Sales Information
                    </h2>

                </div>


                <div class="card-body">

                    <div class="info-list">


                        <div class="info-item">

                            <span class="info-label">
                                Current Stage
                            </span>

                            <span class="info-value">

                                <span
                                    class="stage-badge <?= htmlspecialchars($stageClass) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $lead["stage"]
                                    ) ?>

                                </span>

                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Assigned Employee
                            </span>

                            <span class="info-value">

                                <?php if (
                                    $lead["assigned_name"]
                                ): ?>

                                    <?= htmlspecialchars(
                                        $lead["assigned_name"]
                                    ) ?>

                                <?php else: ?>

                                    <span class="no-data">
                                        Unassigned
                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Follow-up Date
                            </span>

                            <span
                                class="info-value <?= ($isToday || $isOverdue) ? "today" : "" ?>"
                            >

                                <?php if (
                                    $lead["follow_up_date"]
                                ): ?>

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $lead["follow_up_date"]
                                        )
                                    ) ?>

                                    <?php if ($isToday): ?>

                                        · Today

                                    <?php elseif ($isOverdue): ?>

                                        · Overdue

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="no-data">
                                        Not scheduled
                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="info-item">

                            <span class="info-label">
                                Last Updated
                            </span>

                            <span class="info-value">

                                <?= date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $lead["updated_at"]
                                    )
                                ) ?>

                            </span>

                        </div>


                    </div>

                </div>

            </div>


            <!-- FOLLOW-UP + NOTES -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Follow-up
                    </h2>

                </div>


                <div class="card-body">


                    <?php if (
                        $lead["follow_up_date"]
                    ): ?>

                        <div class="followup-box">

                            <div class="calendar-icon">
                                📅
                            </div>


                            <div>

                                <div class="followup-label">
                                    Next follow-up
                                </div>

                                <div
                                    class="followup-date <?= ($isToday || $isOverdue) ? "today" : "" ?>"
                                >

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $lead["follow_up_date"]
                                        )
                                    ) ?>

                                    <?php if ($isToday): ?>

                                        <span class="overdue">
                                            TODAY
                                        </span>

                                    <?php elseif ($isOverdue): ?>

                                        <span class="overdue">
                                            OVERDUE
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="followup-box">

                            <div class="calendar-icon">
                                📅
                            </div>

                            <div>

                                <div class="followup-label">
                                    Next follow-up
                                </div>

                                <div class="followup-date">

                                    Not scheduled

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>


                    <div class="info-label"
                         style="margin-bottom:8px;">

                        Sales Notes

                    </div>


                    <?php if (
                        $lead["notes"]
                    ): ?>

                        <div class="notes">

                            <?= htmlspecialchars(
                                $lead["notes"]
                            ) ?>

                        </div>

                    <?php else: ?>

                        <div class="notes">

                            <span class="no-data">
                                No notes have been added yet.
                            </span>

                        </div>

                    <?php endif; ?>


                </div>

            </div>


            <!-- ACTIVITY -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Activity
                    </h2>

                </div>


                <div class="card-body">

                    <div class="activity">


                        <div class="activity-item">

                            <div class="activity-dot"></div>

                            <div class="activity-title">
                                Lead created
                            </div>

                            <div class="activity-time">

                                <?= date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $lead["created_at"]
                                    )
                                ) ?>

                            </div>

                        </div>


                        <div class="activity-item">

                            <div class="activity-dot"></div>

                            <div class="activity-title">

                                Current stage:
                                <?= htmlspecialchars(
                                    $lead["stage"]
                                ) ?>

                            </div>

                            <div class="activity-time">

                                Last updated
                                <?= date(
                                    "d M Y, h:i A",
                                    strtotime(
                                        $lead["updated_at"]
                                    )
                                ) ?>

                            </div>

                        </div>


                        <?php if (
                            $lead["assigned_name"]
                        ): ?>

                            <div class="activity-item">

                                <div class="activity-dot"></div>

                                <div class="activity-title">

                                    Assigned to
                                    <?= htmlspecialchars(
                                        $lead["assigned_name"]
                                    ) ?>

                                </div>

                                <div class="activity-time">
                                    Sales employee
                                </div>

                            </div>

                        <?php endif; ?>


                    </div>

                </div>

            </div>


            <!-- =================================================
                 BOOKING
            ================================================= -->

            <div class="card booking-card">

                <div class="card-header">

                    <h2>
                        Booking Information
                    </h2>

                    <?php if ($booking): ?>

                        <span
                            class="stage-badge booked"
                        >
                            <?= htmlspecialchars(
                                $booking["status"]
                            ) ?>
                        </span>

                    <?php endif; ?>

                </div>


                <div class="card-body">


                    <?php if ($booking): ?>


                        <div class="booking-summary">


                            <div class="booking-item">

                                <div class="booking-label">
                                    Unit
                                </div>

                                <div class="booking-value">

                                    <?= htmlspecialchars(
                                        $booking["unit_number"]
                                    ) ?>

                                </div>

                            </div>


                            <div class="booking-item">

                                <div class="booking-label">
                                    Unit Type
                                </div>

                                <div class="booking-value">

                                    <?= htmlspecialchars(
                                        $booking["unit_type"]
                                    ) ?>

                                </div>

                            </div>


                            <div class="booking-item">

                                <div class="booking-label">
                                    Floor
                                </div>

                                <div class="booking-value">

                                    <?= (int)$booking["floor"] ?>

                                </div>

                            </div>


                            <div class="booking-item">

                                <div class="booking-label">
                                    Booking Amount
                                </div>

                                <div class="booking-value">

                                    ₹<?= number_format(
                                        $booking["amount"],
                                        2
                                    ) ?>

                                </div>

                            </div>


                        </div>


                        <div class="booking-project">

                            <strong>

                                <?= htmlspecialchars(
                                    $booking["project_name"]
                                ) ?>

                                ·

                                <?= htmlspecialchars(
                                    $booking["building_name"]
                                ) ?>

                            </strong>


                            <p>

                                <?= htmlspecialchars(
                                    $booking["project_location"]
                                ) ?>

                                · Booked on

                                <?= date(
                                    "d M Y",
                                    strtotime(
                                        $booking["booking_date"]
                                    )
                                ) ?>

                                · By

                                <?= htmlspecialchars(
                                    $booking["booked_by_name"]
                                ) ?>

                            </p>

                        </div>


                    <?php else: ?>


                        <div
                            style="
                                text-align:center;
                                padding:35px 20px;
                            "
                        >

                            <div
                                style="
                                    font-size:30px;
                                    margin-bottom:10px;
                                "
                            >
                                🏠
                            </div>

                            <div
                                style="
                                    color:#0f172a;
                                    font-size:14px;
                                    font-weight:600;
                                    margin-bottom:5px;
                                "
                            >
                                No booking yet
                            </div>

                            <div
                                style="
                                    color:#94a3b8;
                                    font-size:12px;
                                "
                            >
                                This lead has not been connected
                                to a property unit yet.
                            </div>

                        </div>


                    <?php endif; ?>


                </div>

            </div>


        </div>

    </section>

</main>


</body>

</html>