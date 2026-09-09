<?php

require_once "../includes/auth.php";

requireRole("admin");

$user = currentUser();

/* =====================================================
   DASHBOARD STATISTICS
===================================================== */

/* Total Leads */
$stmt = $pdo->query(" SELECT COUNT(*) 
    FROM leads
");

$totalLeads = (int) $stmt->fetchColumn();


/* New Leads */
$stmt = $pdo->query(" SELECT COUNT(*) 
    FROM leads
    WHERE stage = 'New'
");

$newLeads = (int) $stmt->fetchColumn();


/* Site Visits */
$stmt = $pdo->query(" SELECT COUNT(*) 
    FROM leads
    WHERE stage = 'Site Visit'
");

$siteVisits = (int) $stmt->fetchColumn();


/* Interested Leads */
$stmt = $pdo->query(" SELECT COUNT(*) 
    FROM leads
    WHERE stage = 'Interested'
");

$interestedLeads = (int) $stmt->fetchColumn();


/* Negotiation Leads */
$stmt = $pdo->query("  SELECT COUNT(*) 
    FROM leads
    WHERE stage = 'Negotiation'
");

$negotiationLeads = (int) $stmt->fetchColumn();


/* Total Bookings */
$stmt = $pdo->query(" SELECT COUNT(*)
    FROM bookings
    WHERE status = 'Confirmed'
");

$totalBookings = (int) $stmt->fetchColumn();


/* Available Units */
$stmt = $pdo->query(" SELECT COUNT(*)
    FROM units
    WHERE status = 'Available'
");

$availableUnits = (int) $stmt->fetchColumn();


/* Today's Follow-ups */
$stmt = $pdo->query(" SELECT COUNT(*)
    FROM leads
    WHERE follow_up_date = CURDATE()
    AND stage NOT IN ('Booked', 'Lost')
");

$todayFollowups = (int) $stmt->fetchColumn();


/* Upcoming Follow-ups */
$stmt = $pdo->query(" SELECT COUNT(*)
    FROM leads
    WHERE follow_up_date > CURDATE()
    AND stage NOT IN ('Booked', 'Lost')
");

$upcomingFollowups = (int) $stmt->fetchColumn();


/* =====================================================
   LEAD STAGE COUNTS
===================================================== */

$stageCounts = [];

$stages = [
    "New",
    "Contacted",
    "Site Visit",
    "Interested",
    "Negotiation",
    "Booked",
    "Lost"
];

foreach ($stages as $stage) {

    $stmt = $pdo->prepare(" SELECT COUNT(*)
        FROM leads
        WHERE stage = ?
    ");

    $stmt->execute([$stage]);

    $stageCounts[$stage] = (int) $stmt->fetchColumn();
}


/* =====================================================
   TODAY'S FOLLOW-UPS LIST
===================================================== */

$stmt = $pdo->query(" SELECT
        l.id,
        l.name,
        l.phone,
        l.stage,
        l.follow_up_date,
        u.name AS assigned_name
    FROM leads l
    LEFT JOIN users u
        ON l.assigned_to = u.id
    WHERE l.follow_up_date = CURDATE()
    AND l.stage NOT IN ('Booked', 'Lost')
    ORDER BY l.id DESC
    LIMIT 5
");

$followups = $stmt->fetchAll();


/* =====================================================
   RECENT BOOKINGS
===================================================== */

$stmt = $pdo->query(" SELECT
        b.id,
        b.booking_date,
        b.amount,
        l.name AS lead_name,
        u.unit_number,
        u.type AS unit_type,
        bu.name AS building_name,
        p.name AS project_name,
        usr.name AS booked_by_name
    FROM bookings b

    INNER JOIN leads l
        ON b.lead_id = l.id

    INNER JOIN units u
        ON b.unit_id = u.id

    INNER JOIN buildings bu
        ON u.building_id = bu.id

    INNER JOIN projects p
        ON bu.project_id = p.id

    INNER JOIN users usr
        ON b.booked_by = usr.id

    WHERE b.status = 'Confirmed'

    ORDER BY b.created_at DESC

    LIMIT 5
");

$recentBookings = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | PropFlow CRM</title>

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

            background: #2563eb;

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

            border-top: 1px solid rgba(255,255,255,.08);

            padding-top: 15px;
        }


        /* =================================================
           MAIN
        ================================================= */

        .main {

            margin-left: 245px;

            min-height: 100vh;
        }


        /* =================================================
           TOPBAR
        ================================================= */

        .topbar {

            height: 72px;

            background: white;

            border-bottom: 1px solid var(--border);

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


        .profile-info {

            line-height: 1.3;
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


        .welcome {

            margin-bottom: 28px;
        }


        .welcome h1 {

            font-size: 25px;

            color: var(--dark);

            letter-spacing: -.6px;

            margin-bottom: 5px;
        }


        .welcome p {

            color: var(--muted);

            font-size: 13px;
        }


        /* =================================================
           KPI CARDS
        ================================================= */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 24px;
        }


        .stat-card {

            background: white;

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 20px;

            transition: .2s;
        }


        .stat-card:hover {

            transform: translateY(-2px);

            box-shadow:
                0 10px 25px rgba(15,23,42,.06);
        }


        .stat-top {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 18px;
        }


        .stat-label {

            color: var(--muted);

            font-size: 12px;

            font-weight: 500;
        }


        .stat-icon {

            width: 36px;
            height: 36px;

            border-radius: 10px;

            background: #eff6ff;

            display: flex;

            align-items: center;
            justify-content: center;

            color: var(--primary);

            font-size: 16px;
        }


        .stat-value {

            color: var(--dark);

            font-size: 28px;

            font-weight: 700;

            letter-spacing: -1px;
        }


        .stat-footer {

            margin-top: 5px;

            color: #94a3b8;

            font-size: 11px;
        }


        /* =================================================
           GRID SECTIONS
        ================================================= */

        .dashboard-grid {

            display: grid;

            grid-template-columns:
                1.2fr .8fr;

            gap: 20px;

            margin-bottom: 20px;
        }


        .card {

            background: white;

            border: 1px solid var(--border);

            border-radius: 14px;

            overflow: hidden;
        }


        .card-header {

            padding: 19px 20px;

            border-bottom: 1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .card-header h2 {

            font-size: 14px;

            color: var(--dark);

            font-weight: 650;
        }


        .card-link {

            text-decoration: none;

            color: var(--primary);

            font-size: 11px;

            font-weight: 600;
        }


        .card-body {

            padding: 20px;
        }


        /* =================================================
           LEAD PIPELINE
        ================================================= */

        .pipeline {

            display: flex;

            flex-direction: column;

            gap: 14px;
        }


        .pipeline-row {

            display: grid;

            grid-template-columns: 110px 1fr 35px;

            align-items: center;

            gap: 12px;
        }


        .pipeline-name {

            font-size: 12px;

            color: var(--text);
        }


        .progress {

            height: 7px;

            background: #f1f5f9;

            border-radius: 10px;

            overflow: hidden;
        }


        .progress-bar {

            height: 100%;

            background: var(--primary);

            border-radius: 10px;
        }


        .pipeline-count {

            text-align: right;

            font-size: 12px;

            font-weight: 600;

            color: var(--dark);
        }


        /* =================================================
           FOLLOWUPS
        ================================================= */

        .followup-list {

            display: flex;

            flex-direction: column;

            gap: 4px;
        }


        .followup-item {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 11px 8px;

            border-radius: 9px;
        }


        .followup-item:hover {

            background: #f8fafc;
        }


        .followup-avatar {

            width: 34px;
            height: 34px;

            border-radius: 9px;

            background: #eff6ff;

            color: #2563eb;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 12px;

            font-weight: 700;
        }


        .followup-info {

            flex: 1;
        }


        .followup-name {

            color: var(--dark);

            font-size: 12px;

            font-weight: 600;
        }


        .followup-meta {

            color: var(--muted);

            font-size: 10px;

            margin-top: 2px;
        }


        .stage-badge {

            font-size: 9px;

            padding: 5px 8px;

            border-radius: 20px;

            background: #eff6ff;

            color: #1d4ed8;

            white-space: nowrap;
        }


        /* =================================================
           RECENT BOOKINGS
        ================================================= */

        .table-wrapper {

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;
        }


        th {

            text-align: left;

            color: #94a3b8;

            font-size: 10px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: .4px;

            padding: 12px 20px;

            border-bottom: 1px solid var(--border);

            white-space: nowrap;
        }


        td {

            padding: 14px 20px;

            border-bottom: 1px solid #f1f5f9;

            color: var(--text);

            font-size: 12px;

            white-space: nowrap;
        }


        tr:last-child td {

            border-bottom: none;
        }


        .lead-cell {

            font-weight: 600;

            color: var(--dark);
        }


        .unit-badge {

            display: inline-block;

            background: #f1f5f9;

            padding: 5px 8px;

            border-radius: 6px;

            font-size: 10px;

            color: #475569;
        }


        .amount {

            font-weight: 600;

            color: #0f172a;
        }


        .empty-state {

            text-align: center;

            padding: 40px 20px;

            color: #94a3b8;

            font-size: 12px;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1100px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .dashboard-grid {

                grid-template-columns: 1fr;
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
            .nav a span,
            .sidebar-bottom .nav a span {

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
        }


        @media (max-width: 500px) {

            .stats-grid {

                grid-template-columns: 1fr;
            }

            .welcome h1 {

                font-size: 22px;
            }

            .pipeline-row {

                grid-template-columns:
                    85px 1fr 25px;

                gap: 7px;
            }

            .profile-info {

                display: none;
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

        <a
            href="dashboard.php"
            class="active"
        >
            <span class="nav-icon">▦</span>
            <span>Dashboard</span>
        </a>


        <a href="leads.php">

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


        <a href="team.php">

            <span class="nav-icon">♙</span>

            <span>Team</span>

        </a>
         <a href="buildings.php" >
            <span class="nav-icon">🏢</span>
            <span>Buildings</span>
        </a>

         <a href="employees.php">
            <span class="nav-icon">♙</span>
            <span>Employees</span>
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


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="profile">

            <div class="avatar">

                <?= strtoupper(
                    substr($user["name"], 0, 1)
                ) ?>

            </div>


            <div class="profile-info">

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


    <!-- CONTENT -->

    <section class="content">


        <div class="welcome">

            <h1>
                Good morning, <?= htmlspecialchars($user["name"]) ?> 👋
            </h1>

            <p>
                Here's what's happening with your sales team today.
            </p>

        </div>


        <!-- =================================================
             STAT CARDS
        ================================================= -->

        <div class="stats-grid">


            <div class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">
                        Total Leads
                    </span>

                    <div class="stat-icon">
                        👥
                    </div>

                </div>

                <div class="stat-value">
                    <?= $totalLeads ?>
                </div>

                <div class="stat-footer">
                    All leads in CRM
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">
                        Site Visits
                    </span>

                    <div class="stat-icon">
                        📍
                    </div>

                </div>

                <div class="stat-value">
                    <?= $siteVisits ?>
                </div>

                <div class="stat-footer">
                    Leads at site visit stage
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">
                        Follow-ups Today
                    </span>

                    <div class="stat-icon">
                        📅
                    </div>

                </div>

                <div class="stat-value">
                    <?= $todayFollowups ?>
                </div>

                <div class="stat-footer">
                    Requires attention today
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">
                        Confirmed Bookings
                    </span>

                    <div class="stat-icon">
                        ✓
                    </div>

                </div>

                <div class="stat-value">
                    <?= $totalBookings ?>
                </div>

                <div class="stat-footer">
                    Successful bookings
                </div>

            </div>

        </div>


        <!-- =================================================
             PIPELINE + FOLLOWUPS
        ================================================= -->

        <div class="dashboard-grid">


            <!-- LEAD PIPELINE -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Lead Pipeline
                    </h2>

                    <a
                        href="leads.php"
                        class="card-link"
                    >
                        View leads →
                    </a>

                </div>


                <div class="card-body">

                    <div class="pipeline">

                        <?php

                        $maxStageCount =
                            max(
                                max($stageCounts),
                                1
                            );

                        foreach ($stages as $stage):

                            $count =
                                $stageCounts[$stage];

                            $percentage =
                                ($count / $maxStageCount) * 100;

                        ?>

                        <div class="pipeline-row">

                            <div class="pipeline-name">

                                <?= htmlspecialchars($stage) ?>

                            </div>


                            <div class="progress">

                                <div
                                    class="progress-bar"
                                    style="width: <?= $percentage ?>%"
                                ></div>

                            </div>


                            <div class="pipeline-count">

                                <?= $count ?>

                            </div>

                        </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>


            <!-- FOLLOW UPS -->

            <div class="card">

                <div class="card-header">

                    <h2>
                        Today's Follow-ups
                    </h2>

                    <span class="card-link">
                        <?= $todayFollowups ?> today
                    </span>

                </div>


                <div class="card-body">

                    <?php if (empty($followups)): ?>

                        <div class="empty-state">

                            🎉 No follow-ups scheduled for today.

                        </div>

                    <?php else: ?>

                        <div class="followup-list">

                            <?php foreach ($followups as $lead): ?>

                                <div class="followup-item">

                                    <div class="followup-avatar">

                                        <?= strtoupper(
                                            substr(
                                                $lead["name"],
                                                0,
                                                1
                                            )
                                        ) ?>

                                    </div>


                                    <div class="followup-info">

                                        <div class="followup-name">

                                            <?= htmlspecialchars(
                                                $lead["name"]
                                            ) ?>

                                        </div>

                                        <div class="followup-meta">

                                            <?= htmlspecialchars(
                                                $lead["phone"]
                                            ) ?>

                                            <?php if ($lead["assigned_name"]): ?>

                                                · <?= htmlspecialchars(
                                                    $lead["assigned_name"]
                                                ) ?>

                                            <?php endif; ?>

                                        </div>

                                    </div>


                                    <span class="stage-badge">

                                        <?= htmlspecialchars(
                                            $lead["stage"]
                                        ) ?>

                                    </span>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- =================================================
             QUICK OVERVIEW
        ================================================= -->

        <div class="dashboard-grid">


            <div class="card">

                <div class="card-header">

                    <h2>
                        Inventory Overview
                    </h2>

                    <a
                        href="properties.php"
                        class="card-link"
                    >
                        Manage →
                    </a>

                </div>


                <div class="card-body">

                    <div class="stats-grid"
                         style="grid-template-columns: repeat(3, 1fr); margin:0;">

                        <div>

                            <div class="stat-label">
                                Available Units
                            </div>

                            <div
                                class="stat-value"
                                style="font-size:23px;margin-top:7px;"
                            >
                                <?= $availableUnits ?>
                            </div>

                        </div>


                        <div>

                            <div class="stat-label">
                                New Leads
                            </div>

                            <div
                                class="stat-value"
                                style="font-size:23px;margin-top:7px;"
                            >
                                <?= $newLeads ?>
                            </div>

                        </div>


                        <div>

                            <div class="stat-label">
                                Upcoming Follow-ups
                            </div>

                            <div
                                class="stat-value"
                                style="font-size:23px;margin-top:7px;"
                            >
                                <?= $upcomingFollowups ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="card">

                <div class="card-header">

                    <h2>
                        Sales Focus
                    </h2>

                </div>


                <div class="card-body">

                    <div style="
                        display:flex;
                        align-items:center;
                        justify-content:space-between;
                        padding:5px 0 12px;
                    ">

                        <div>

                            <div class="stat-label">
                                Interested Leads
                            </div>

                            <div style="
                                font-size:22px;
                                font-weight:700;
                                color:#0f172a;
                                margin-top:4px;
                            ">
                                <?= $interestedLeads ?>
                            </div>

                        </div>


                        <div>

                            <div class="stat-label">
                                Negotiations
                            </div>

                            <div style="
                                font-size:22px;
                                font-weight:700;
                                color:#0f172a;
                                margin-top:4px;
                            ">
                                <?= $negotiationLeads ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             RECENT BOOKINGS
        ================================================= -->

        <div class="card">

            <div class="card-header">

                <h2>
                    Recent Bookings
                </h2>

                <a
                    href="bookings.php"
                    class="card-link"
                >
                    View all →
                </a>

            </div>


            <?php if (empty($recentBookings)): ?>

                <div class="empty-state">

                    📋 No confirmed bookings yet.

                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Property
                                </th>

                                <th>
                                    Unit
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Booking Date
                                </th>

                                <th>
                                    Sales Employee
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach (
                                $recentBookings
                                as $booking
                            ): ?>

                                <tr>

                                    <td class="lead-cell">

                                        <?= htmlspecialchars(
                                            $booking["lead_name"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $booking["project_name"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <span class="unit-badge">

                                            <?= htmlspecialchars(
                                                $booking["unit_number"]
                                            ) ?>

                                            ·

                                            <?= htmlspecialchars(
                                                $booking["unit_type"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <td class="amount">

                                        ₹<?= number_format(
                                            $booking["amount"],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= date(
                                            "d M Y",
                                            strtotime(
                                                $booking["booking_date"]
                                            )
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $booking["booked_by_name"]
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>


    </section>

</main>


</body>

</html>