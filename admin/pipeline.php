<?php

require_once "../includes/auth.php";

requireRole("admin");

$user = currentUser();


/* =====================================================
   CSRF TOKEN
===================================================== */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];


/* =====================================================
   LEAD STAGES
===================================================== */

$stages = [
    "New",
    "Contacted",
    "Site Visit",
    "Interested",
    "Negotiation",
    "Booked",
    "Lost"
];


/* =====================================================
   STAGE COLORS / CLASSES
===================================================== */

$stageClasses = [
    "New"         => "new",
    "Contacted"   => "contacted",
    "Site Visit"  => "site-visit",
    "Interested"  => "interested",
    "Negotiation" => "negotiation",
    "Booked"      => "booked",
    "Lost"        => "lost"
];


$message = "";
$messageType = "";


/* =====================================================
   UPDATE LEAD STAGE
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $postedToken)) {

        $message = "Security validation failed.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";

        if ($action === "update_stage") {

            $leadId = (int)($_POST["lead_id"] ?? 0);

            $newStage = $_POST["stage"] ?? "";


            if ($leadId <= 0) {

                $message = "Invalid lead.";
                $messageType = "error";

            } elseif (!in_array($newStage, $stages, true)) {

                $message = "Invalid stage.";
                $messageType = "error";

            } else {

                $stmt = $pdo->prepare("
                    UPDATE leads
                    SET stage = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $newStage,
                    $leadId
                ]);


                $message = "Lead stage updated successfully.";
                $messageType = "success";
            }
        }
    }
}


/* =====================================================
   EMPLOYEE FILTER
===================================================== */

$employeeFilter = $_GET["employee"] ?? "";


/* =====================================================
   FETCH SALES EMPLOYEES
===================================================== */

$stmt = $pdo->query("
    SELECT
        id,
        name,
        email
    FROM users
    WHERE role = 'sales'
    AND status = 'active'
    ORDER BY name ASC
");

$salesEmployees = $stmt->fetchAll();


/* =====================================================
   FETCH PIPELINE LEADS
===================================================== */

$where = "";

$params = [];


if ($employeeFilter !== "") {

    $where = "WHERE l.assigned_to = ?";

    $params[] = (int)$employeeFilter;
}


$sql = "
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
        u.name AS assigned_name
    FROM leads l
    LEFT JOIN users u
        ON l.assigned_to = u.id
    $where
    ORDER BY
        CASE l.stage
            WHEN 'New' THEN 1
            WHEN 'Contacted' THEN 2
            WHEN 'Site Visit' THEN 3
            WHEN 'Interested' THEN 4
            WHEN 'Negotiation' THEN 5
            WHEN 'Booked' THEN 6
            WHEN 'Lost' THEN 7
            ELSE 8
        END,
        l.created_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$allLeads = $stmt->fetchAll();


/* =====================================================
   ORGANIZE LEADS BY STAGE
===================================================== */

$pipeline = [];

foreach ($stages as $stage) {
    $pipeline[$stage] = [];
}


foreach ($allLeads as $lead) {

    if (isset($pipeline[$lead["stage"]])) {

        $pipeline[$lead["stage"]][] = $lead;
    }
}


/* =====================================================
   TOTAL
===================================================== */

$totalLeads = count($allLeads);

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
        Pipeline | PropFlow CRM
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


        /* =================================================
           TOPBAR
        ================================================= */

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


        .page-header {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 24px;
        }


        .page-title h1 {

            color: var(--dark);

            font-size: 26px;

            letter-spacing: -.7px;

            margin-bottom: 5px;
        }


        .page-title p {

            color: var(--muted);

            font-size: 13px;
        }


        .lead-count {

            color: var(--muted);

            font-size: 12px;

            margin-top: 8px;
        }


        /* =================================================
           FILTER
        ================================================= */

        .filter-card {

            background: white;

            border: 1px solid var(--border);

            border-radius: 13px;

            padding: 15px 17px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 22px;
        }


        .filter-left {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .filter-label {

            color: var(--muted);

            font-size: 12px;

            font-weight: 600;
        }


        .filter-select {

            height: 39px;

            min-width: 190px;

            border: 1px solid var(--border);

            border-radius: 8px;

            padding: 0 11px;

            background: white;

            color: var(--text);

            font-size: 12px;

            outline: none;
        }


        .filter-select:focus {

            border-color: var(--primary);

            box-shadow:
                0 0 0 3px rgba(37,99,235,.08);
        }


        .filter-btn {

            height: 39px;

            border: none;

            border-radius: 8px;

            padding: 0 15px;

            background: var(--dark);

            color: white;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;
        }


        .clear-btn {

            height: 39px;

            padding: 0 13px;

            border: 1px solid var(--border);

            border-radius: 8px;

            background: white;

            color: var(--muted);

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            font-size: 12px;
        }


        /* =================================================
           MESSAGE
        ================================================= */

        .message {

            padding: 12px 15px;

            border-radius: 9px;

            margin-bottom: 18px;

            font-size: 12px;

            border: 1px solid;
        }


        .message.success {

            color: var(--success);

            background: #f0fdf4;

            border-color: #bbf7d0;
        }


        .message.error {

            color: var(--danger);

            background: #fef2f2;

            border-color: #fecaca;
        }


        /* =================================================
           PIPELINE
        ================================================= */

        .pipeline-wrapper {

            overflow-x: auto;

            padding-bottom: 10px;
        }


        .pipeline {

            display: grid;

            grid-template-columns:
                repeat(7, minmax(255px, 1fr));

            gap: 13px;

            min-width: 1830px;
        }


        .stage-column {

            background: #f1f5f9;

            border: 1px solid #e2e8f0;

            border-radius: 13px;

            min-height: 500px;

            overflow: hidden;
        }


        .stage-header {

            padding: 14px 14px 12px;

            background: white;

            border-bottom: 1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .stage-name {

            display: flex;

            align-items: center;

            gap: 8px;

            color: var(--dark);

            font-size: 12px;

            font-weight: 700;
        }


        .stage-dot {

            width: 8px;
            height: 8px;

            border-radius: 50%;

            background: var(--primary);
        }


        .stage-count {

            min-width: 24px;
            height: 24px;

            padding: 0 7px;

            border-radius: 20px;

            background: #f1f5f9;

            color: #64748b;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 10px;

            font-weight: 700;
        }


        .stage-body {

            padding: 10px;

            display: flex;

            flex-direction: column;

            gap: 10px;
        }


        /* =================================================
           LEAD CARD
        ================================================= */

        .lead-card {

            background: white;

            border: 1px solid var(--border);

            border-radius: 11px;

            padding: 13px;

            transition: .2s;
        }


        .lead-card:hover {

            transform: translateY(-2px);

            box-shadow:
                0 8px 20px rgba(15,23,42,.07);

            border-color: #cbd5e1;
        }


        .lead-top {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 8px;

            margin-bottom: 11px;
        }


        .lead-avatar {

            width: 35px;
            height: 35px;

            border-radius: 9px;

            background: #eff6ff;

            color: var(--primary);

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 11px;

            font-weight: 700;
        }


        .lead-info {

            flex: 1;

            min-width: 0;
        }


        .lead-name {

            color: var(--dark);

            font-size: 12px;

            font-weight: 700;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;
        }


        .lead-phone {

            color: #94a3b8;

            font-size: 10px;

            margin-top: 3px;
        }


        .view-link {

            color: var(--primary);

            text-decoration: none;

            font-size: 10px;

            font-weight: 600;

            white-space: nowrap;
        }


        .view-link:hover {

            color: var(--primary-dark);

            text-decoration: underline;
        }


        .lead-meta {

            display: grid;

            gap: 8px;

            padding: 10px 0;

            border-top: 1px solid #f1f5f9;

            border-bottom: 1px solid #f1f5f9;

            margin-bottom: 10px;
        }


        .meta-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;
        }


        .meta-label {

            color: #94a3b8;

            font-size: 9px;

            text-transform: uppercase;

            letter-spacing: .3px;

            font-weight: 600;
        }


        .meta-value {

            color: var(--text);

            font-size: 10px;

            font-weight: 600;

            text-align: right;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

            max-width: 150px;
        }


        .follow-today {

            color: #dc2626 !important;
        }


        .follow-overdue {

            color: #dc2626 !important;

            font-weight: 700;
        }


        /* =================================================
           STAGE UPDATE
        ================================================= */

        .stage-update {

            display: flex;

            gap: 6px;
        }


        .stage-update select {

            flex: 1;

            min-width: 0;

            height: 32px;

            border: 1px solid var(--border);

            border-radius: 7px;

            padding: 0 7px;

            background: white;

            color: var(--text);

            font-size: 10px;

            outline: none;
        }


        .stage-update button {

            height: 32px;

            padding: 0 9px;

            border: none;

            border-radius: 7px;

            background: var(--primary);

            color: white;

            cursor: pointer;

            font-size: 10px;

            font-weight: 600;
        }


        .stage-update button:hover {

            background: var(--primary-dark);
        }


        /* =================================================
           EMPTY COLUMN
        ================================================= */

        .empty-column {

            padding: 45px 10px;

            text-align: center;

            color: #94a3b8;

            font-size: 10px;
        }


        .empty-column-icon {

            font-size: 21px;

            opacity: .7;

            margin-bottom: 7px;
        }


        /* =================================================
           STAGE DOT COLORS
        ================================================= */

        .dot-new {
            background: #2563eb;
        }

        .dot-contacted {
            background: #64748b;
        }

        .dot-site-visit {
            background: #ca8a04;
        }

        .dot-interested {
            background: #16a34a;
        }

        .dot-negotiation {
            background: #ea580c;
        }

        .dot-booked {
            background: #059669;
        }

        .dot-lost {
            background: #dc2626;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

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


            .page-header {

                align-items: flex-start;

                flex-direction: column;
            }


            .filter-card {

                align-items: flex-start;

                flex-direction: column;
            }


            .filter-left {

                width: 100%;

                flex-direction: column;

                align-items: flex-start;
            }


            .filter-select {

                width: 100%;
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


        <a href="leads.php">

            <span class="nav-icon">👥</span>

            <span>Leads</span>

        </a>


        <a
            href="pipeline.php"
            class="active"
        >

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


    <!-- CONTENT -->

    <section class="content">


        <!-- =================================================
             HEADER
        ================================================= -->

        <div class="page-header">

            <div class="page-title">

                <h1>
                    Sales Pipeline
                </h1>

                <p>
                    Track every lead from first contact to successful booking.
                </p>

                <div class="lead-count">

                    <?= $totalLeads ?>
                    <?= $totalLeads === 1 ? "lead" : "leads" ?>
                    in pipeline

                </div>

            </div>

        </div>


        <!-- =================================================
             MESSAGE
        ================================================= -->

        <?php if ($message !== ""): ?>

            <div class="message <?= $messageType ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FILTER
        ================================================= -->

        <div class="filter-card">


            <form
                method="GET"
                style="
                    width:100%;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:15px;
                "
            >


                <div class="filter-left">

                    <span class="filter-label">
                        Sales Employee
                    </span>


                    <select
                        name="employee"
                        class="filter-select"
                    >

                        <option value="">
                            All employees
                        </option>


                        <?php foreach (
                            $salesEmployees
                            as $employee
                        ): ?>

                            <option
                                value="<?= (int)$employee["id"] ?>"
                                <?= (string)$employeeFilter === (string)$employee["id"] ? "selected" : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $employee["name"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div
                    style="
                        display:flex;
                        gap:7px;
                    "
                >

                    <button
                        type="submit"
                        class="filter-btn"
                    >
                        Apply Filter
                    </button>


                    <a
                        href="pipeline.php"
                        class="clear-btn"
                    >
                        Clear
                    </a>

                </div>


            </form>

        </div>


        <!-- =================================================
             PIPELINE BOARD
        ================================================= -->

        <div class="pipeline-wrapper">

            <div class="pipeline">


                <?php foreach (
                    $stages
                    as $stage
                ): ?>


                    <?php

                    $stageLeads =
                        $pipeline[$stage];

                    $stageClass =
                        $stageClasses[$stage];

                    ?>


                    <!-- COLUMN -->

                    <div class="stage-column">


                        <!-- HEADER -->

                        <div class="stage-header">

                            <div class="stage-name">

                                <span
                                    class="
                                        stage-dot
                                        dot-<?= htmlspecialchars($stageClass) ?>
                                    "
                                ></span>


                                <?= htmlspecialchars(
                                    $stage
                                ) ?>

                            </div>


                            <div class="stage-count">

                                <?= count($stageLeads) ?>

                            </div>

                        </div>


                        <!-- BODY -->

                        <div class="stage-body">


                            <?php if (
                                empty($stageLeads)
                            ): ?>


                                <div class="empty-column">

                                    <div class="empty-column-icon">
                                        ○
                                    </div>

                                    No leads here yet

                                </div>


                            <?php else: ?>


                                <?php foreach (
                                    $stageLeads
                                    as $lead
                                ): ?>


                                    <?php

                                    $initial =
                                        strtoupper(
                                            substr(
                                                $lead["name"],
                                                0,
                                                1
                                            )
                                        );


                                    $isToday =
                                        $lead["follow_up_date"] === date("Y-m-d");


                                    $isOverdue =
                                        !empty(
                                            $lead["follow_up_date"]
                                        )
                                        &&
                                        $lead["follow_up_date"]
                                            < date("Y-m-d")
                                        &&
                                        !in_array(
                                            $lead["stage"],
                                            ["Booked", "Lost"],
                                            true
                                        );

                                    ?>


                                    <!-- LEAD CARD -->

                                    <div class="lead-card">


                                        <div class="lead-top">


                                            <div
                                                class="lead-avatar"
                                            >

                                                <?= htmlspecialchars(
                                                    $initial
                                                ) ?>

                                            </div>


                                            <div class="lead-info">

                                                <div class="lead-name">

                                                    <?= htmlspecialchars(
                                                        $lead["name"]
                                                    ) ?>

                                                </div>


                                                <div class="lead-phone">

                                                    <?= htmlspecialchars(
                                                        $lead["phone"]
                                                    ) ?>

                                                </div>

                                            </div>


                                            <a
                                                href="lead-view.php?id=<?= (int)$lead["id"] ?>"
                                                class="view-link"
                                            >
                                                View →
                                            </a>

                                        </div>


                                        <!-- META -->

                                        <div class="lead-meta">


                                            <div class="meta-row">

                                                <span class="meta-label">
                                                    Source
                                                </span>

                                                <span class="meta-value">

                                                    <?= $lead["source"]
                                                        ? htmlspecialchars(
                                                            $lead["source"]
                                                        )
                                                        : "—"
                                                    ?>

                                                </span>

                                            </div>


                                            <div class="meta-row">

                                                <span class="meta-label">
                                                    Assigned
                                                </span>

                                                <span class="meta-value">

                                                    <?= $lead["assigned_name"]
                                                        ? htmlspecialchars(
                                                            $lead["assigned_name"]
                                                        )
                                                        : "Unassigned"
                                                    ?>

                                                </span>

                                            </div>


                                            <div class="meta-row">

                                                <span class="meta-label">
                                                    Follow-up
                                                </span>

                                                <span
                                                    class="
                                                        meta-value
                                                        <?= $isToday ? "follow-today" : "" ?>
                                                        <?= $isOverdue ? "follow-overdue" : "" ?>
                                                    "
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


                                                        <?php if (
                                                            $isToday
                                                        ): ?>

                                                            · Today

                                                        <?php elseif (
                                                            $isOverdue
                                                        ): ?>

                                                            · Overdue

                                                        <?php endif; ?>


                                                    <?php else: ?>

                                                        Not scheduled

                                                    <?php endif; ?>

                                                </span>

                                            </div>


                                        </div>


                                        <!-- STAGE UPDATE -->

                                        <form
                                            method="POST"
                                            class="stage-update"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrfToken) ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="action"
                                                value="update_stage"
                                            >


                                            <input
                                                type="hidden"
                                                name="lead_id"
                                                value="<?= (int)$lead["id"] ?>"
                                            >


                                            <select
                                                name="stage"
                                            >

                                                <?php foreach (
                                                    $stages
                                                    as $optionStage
                                                ): ?>

                                                    <option
                                                        value="<?= htmlspecialchars($optionStage) ?>"
                                                        <?= $lead["stage"] === $optionStage ? "selected" : "" ?>
                                                    >

                                                        <?= htmlspecialchars(
                                                            $optionStage
                                                        ) ?>

                                                    </option>

                                                <?php endforeach; ?>

                                            </select>


                                            <button
                                                type="submit"
                                                title="Update stage"
                                            >
                                                Update
                                            </button>

                                        </form>


                                    </div>


                                <?php endforeach; ?>


                            <?php endif; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>

        </div>


    </section>

</main>


</body>

</html>