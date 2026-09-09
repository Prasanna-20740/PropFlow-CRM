<?php

require_once "../includes/auth.php";

requireRole("admin");

$user = currentUser();

$message = "";
$messageType = "";

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
   HANDLE POST ACTIONS
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $postedToken)) {

        $message = "Security validation failed. Please try again.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";


        /* =================================================
           ADD LEAD
        ================================================= */

        if ($action === "add") {

            $name = trim($_POST["name"] ?? "");
            $phone = trim($_POST["phone"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $source = trim($_POST["source"] ?? "");
            $stage = $_POST["stage"] ?? "New";
            $assignedTo = $_POST["assigned_to"] ?? "";
            $followUpDate = $_POST["follow_up_date"] ?? "";
            $notes = trim($_POST["notes"] ?? "");


            if ($name === "") {

                $message = "Lead name is required.";
                $messageType = "error";

            } elseif ($phone === "") {

                $message = "Phone number is required.";
                $messageType = "error";

            } elseif (
                $email !== "" &&
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {

                $message = "Please enter a valid email address.";
                $messageType = "error";

            } elseif (!in_array($stage, $stages, true)) {

                $message = "Invalid lead stage.";
                $messageType = "error";

            } else {

                $assignedValue =
                    $assignedTo !== ""
                    ? (int)$assignedTo
                    : null;

                $followUpValue =
                    $followUpDate !== ""
                    ? $followUpDate
                    : null;


                $stmt = $pdo->prepare(" INSERT INTO leads
                    (
                        name,
                        phone,
                        email,
                        source,
                        stage,
                        assigned_to,
                        follow_up_date,
                        notes
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
                ");


                $stmt->execute([
                    $name,
                    $phone,
                    $email !== "" ? $email : null,
                    $source !== "" ? $source : null,
                    $stage,
                    $assignedValue,
                    $followUpValue,
                    $notes !== "" ? $notes : null
                ]);


                $message = "Lead created successfully.";
                $messageType = "success";
            }
        }


        /* =================================================
           UPDATE LEAD
        ================================================= */

        elseif ($action === "update") {

            $id = (int)($_POST["id"] ?? 0);

            $name = trim($_POST["name"] ?? "");
            $phone = trim($_POST["phone"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $source = trim($_POST["source"] ?? "");
            $stage = $_POST["stage"] ?? "New";
            $assignedTo = $_POST["assigned_to"] ?? "";
            $followUpDate = $_POST["follow_up_date"] ?? "";
            $notes = trim($_POST["notes"] ?? "");


            if ($id <= 0) {

                $message = "Invalid lead.";
                $messageType = "error";

            } elseif ($name === "") {

                $message = "Lead name is required.";
                $messageType = "error";

            } elseif ($phone === "") {

                $message = "Phone number is required.";
                $messageType = "error";

            } elseif (
                $email !== "" &&
                !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {

                $message = "Please enter a valid email address.";
                $messageType = "error";

            } elseif (!in_array($stage, $stages, true)) {

                $message = "Invalid lead stage.";
                $messageType = "error";

            } else {

                $assignedValue =
                    $assignedTo !== ""
                    ? (int)$assignedTo
                    : null;

                $followUpValue =
                    $followUpDate !== ""
                    ? $followUpDate
                    : null;


                $stmt = $pdo->prepare(" UPDATE leads
                    SET
                        name = ?,
                        phone = ?,
                        email = ?,
                        source = ?,
                        stage = ?,
                        assigned_to = ?,
                        follow_up_date = ?,
                        notes = ?
                    WHERE id = ?
                ");


                $stmt->execute([
                    $name,
                    $phone,
                    $email !== "" ? $email : null,
                    $source !== "" ? $source : null,
                    $stage,
                    $assignedValue,
                    $followUpValue,
                    $notes !== "" ? $notes : null,
                    $id
                ]);


                $message = "Lead updated successfully.";
                $messageType = "success";
            }
        }


        /* =================================================
           DELETE LEAD
        ================================================= */

        elseif ($action === "delete") {

            $id = (int)($_POST["id"] ?? 0);


            if ($id <= 0) {

                $message = "Invalid lead.";
                $messageType = "error";

            } else {

                try {

                    $stmt = $pdo->prepare(" SELECT COUNT(*)
                        FROM bookings
                        WHERE lead_id = ?
                    ");

                    $stmt->execute([$id]);

                    $bookingCount =
                        (int)$stmt->fetchColumn();


                    if ($bookingCount > 0) {

                        $message =
                            "This lead has a booking and cannot be deleted.";

                        $messageType = "error";

                    } else {

                        $stmt = $pdo->prepare(" DELETE FROM leads
                            WHERE id = ?
                        ");

                        $stmt->execute([$id]);


                        $message =
                            "Lead deleted successfully.";

                        $messageType = "success";
                    }

                } catch (PDOException $e) {

                    $message =
                        "Unable to delete this lead.";

                    $messageType = "error";
                }
            }
        }
    }
}


/* =====================================================
   SEARCH + FILTER
===================================================== */

$search = trim($_GET["search"] ?? "");

$stageFilter = $_GET["stage"] ?? "";

$employeeFilter = $_GET["employee"] ?? "";


$where = [];

$params = [];


/* Search */

if ($search !== "") {

    $where[] = "
        (
            l.name LIKE ?
            OR l.phone LIKE ?
            OR l.email LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/* Stage */

if (
    $stageFilter !== "" &&
    in_array($stageFilter, $stages, true)
) {

    $where[] = "l.stage = ?";

    $params[] = $stageFilter;
}


/* Employee */

if ($employeeFilter !== "") {

    $where[] = "l.assigned_to = ?";

    $params[] = (int)$employeeFilter;
}


$whereSql = "";

if (!empty($where)) {

    $whereSql =
        "WHERE " . implode(" AND ", $where);
}


/* =====================================================
   FETCH LEADS
===================================================== */

$sql = " SELECT
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
        u.name AS assigned_name
    FROM leads l
    LEFT JOIN users u
        ON l.assigned_to = u.id
    $whereSql
    ORDER BY l.created_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$leads = $stmt->fetchAll();


/* =====================================================
   FETCH SALES EMPLOYEES
===================================================== */

$stmt = $pdo->query(" SELECT
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
   LEAD COUNTS
===================================================== */

$stmt = $pdo->query(" SELECT
        COUNT(*) AS total,
        SUM(stage = 'New') AS new_count,
        SUM(stage = 'Contacted') AS contacted_count,
        SUM(stage = 'Site Visit') AS site_visit_count,
        SUM(stage = 'Interested') AS interested_count,
        SUM(stage = 'Negotiation') AS negotiation_count,
        SUM(stage = 'Booked') AS booked_count,
        SUM(stage = 'Lost') AS lost_count
    FROM leads
");

$leadCounts = $stmt->fetch();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Leads | PropFlow CRM</title>


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
            --success-bg: #f0fdf4;

            --danger: #dc2626;
            --danger-bg: #fef2f2;
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

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 25px;
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


        .primary-btn {

            border: none;

            background: var(--primary);

            color: white;

            padding: 12px 17px;

            border-radius: 9px;

            font-size: 13px;

            font-weight: 600;

            cursor: pointer;

            transition: .2s;
        }


        .primary-btn:hover {

            background: var(--primary-dark);

            transform: translateY(-1px);

            box-shadow:
                0 7px 18px rgba(37,99,235,.2);
        }


        /* =================================================
           MESSAGE
        ================================================= */

        .message {

            padding: 13px 16px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 13px;

            border: 1px solid;
        }


        .message.success {

            color: var(--success);

            background: var(--success-bg);

            border-color: #bbf7d0;
        }


        .message.error {

            color: var(--danger);

            background: var(--danger-bg);

            border-color: #fecaca;
        }


        /* =================================================
           SUMMARY
        ================================================= */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 22px;
        }


        .summary-card {

            background: white;

            border: 1px solid var(--border);

            border-radius: 13px;

            padding: 17px;
        }


        .summary-label {

            color: var(--muted);

            font-size: 11px;

            margin-bottom: 8px;
        }


        .summary-value {

            color: var(--dark);

            font-size: 23px;

            font-weight: 700;
        }


        /* =================================================
           FILTER CARD
        ================================================= */

        .filter-card {

            background: white;

            border: 1px solid var(--border);

            border-radius: 14px;

            padding: 18px;

            margin-bottom: 18px;
        }


        .filter-form {

            display: grid;

            grid-template-columns:
                1.5fr 1fr 1fr auto;

            gap: 11px;

            align-items: end;
        }


        .form-group label {

            display: block;

            color: #475569;

            font-size: 11px;

            font-weight: 600;

            margin-bottom: 7px;
        }


        .input,
        .select {

            width: 100%;

            height: 42px;

            border: 1px solid var(--border);

            border-radius: 8px;

            padding: 0 12px;

            outline: none;

            background: white;

            color: var(--text);

            font-size: 12px;
        }


        .input:focus,
        .select:focus {

            border-color: var(--primary);

            box-shadow:
                0 0 0 3px rgba(37,99,235,.08);
        }


        .filter-btn {

            height: 42px;

            padding: 0 18px;

            border: none;

            border-radius: 8px;

            background: var(--dark);

            color: white;

            cursor: pointer;

            font-size: 12px;

            font-weight: 600;
        }


        .clear-btn {

            height: 42px;

            padding: 0 15px;

            border: 1px solid var(--border);

            border-radius: 8px;

            background: white;

            color: var(--muted);

            cursor: pointer;

            font-size: 12px;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;

        }


        /* =================================================
           TABLE
        ================================================= */

        .table-card {

            background: white;

            border: 1px solid var(--border);

            border-radius: 14px;

            overflow: hidden;
        }


        .table-header {

            padding: 17px 20px;

            border-bottom: 1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .table-header h2 {

            font-size: 14px;

            color: var(--dark);
        }


        .result-count {

            color: var(--muted);

            font-size: 11px;
        }


        .table-wrapper {

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 900px;
        }


        th {

            text-align: left;

            color: #94a3b8;

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .5px;

            padding: 13px 20px;

            background: #fafafa;

            border-bottom: 1px solid var(--border);

            white-space: nowrap;
        }


        td {

            padding: 15px 20px;

            border-bottom: 1px solid #f1f5f9;

            font-size: 12px;

            color: var(--text);

            vertical-align: middle;

            white-space: nowrap;
        }


        tr:last-child td {

            border-bottom: none;
        }


        .lead-info {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .lead-avatar {

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


        .lead-name {

            color: var(--dark);

            font-weight: 650;

            font-size: 12px;

            margin-bottom: 3px;
        }


        .lead-email {

            color: #94a3b8;

            font-size: 10px;
        }


        .stage {

            display: inline-flex;

            align-items: center;

            padding: 5px 9px;

            border-radius: 20px;

            background: #eff6ff;

            color: #1d4ed8;

            font-size: 10px;

            font-weight: 600;
        }


        .stage.new {
            background: #eff6ff;
            color: #1d4ed8;
        }


        .stage.contacted {
            background: #f1f5f9;
            color: #475569;
        }


        .stage.site-visit {
            background: #fefce8;
            color: #a16207;
        }


        .stage.interested {
            background: #f0fdf4;
            color: #15803d;
        }


        .stage.negotiation {
            background: #fff7ed;
            color: #c2410c;
        }


        .stage.booked {
            background: #ecfdf5;
            color: #047857;
        }


        .stage.lost {
            background: #fef2f2;
            color: #b91c1c;
        }


        .assigned {

            display: flex;

            align-items: center;

            gap: 7px;
        }


        .small-avatar {

            width: 27px;
            height: 27px;

            border-radius: 50%;

            background: #f1f5f9;

            color: #475569;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 10px;

            font-weight: 700;
        }


        .unassigned {

            color: #94a3b8;

            font-size: 11px;
        }


        .follow-date {

            color: var(--text);

            font-size: 11px;
        }


        .follow-date.today {

            color: #dc2626;

            font-weight: 700;
        }


        .actions {

            display: flex;

            gap: 6px;
        }


        .action-btn {

            width: 31px;
            height: 31px;

            border: 1px solid var(--border);

            border-radius: 7px;

            background: white;

            cursor: pointer;

            font-size: 12px;

            display: flex;

            align-items: center;
            justify-content: center;
        }


        .action-btn:hover {

            background: #f8fafc;
        }


        .delete-btn {

            color: var(--danger);
        }


        /* =================================================
           EMPTY STATE
        ================================================= */

        .empty-state {

            padding: 65px 25px;

            text-align: center;

            color: #94a3b8;
        }


        .empty-icon {

            font-size: 32px;

            margin-bottom: 10px;
        }


        .empty-title {

            color: var(--dark);

            font-size: 14px;

            font-weight: 600;

            margin-bottom: 5px;
        }


        .empty-text {

            font-size: 12px;

            margin-bottom: 18px;
        }


        /* =================================================
           MODAL
        ================================================= */

        .modal {

            position: fixed;

            inset: 0;

            background: rgba(15,23,42,.55);

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

            z-index: 1000;

            backdrop-filter: blur(3px);
        }


        .modal.show {

            display: flex;
        }


        .modal-content {

            width: 100%;

            max-width: 650px;

            max-height: 92vh;

            overflow-y: auto;

            background: white;

            border-radius: 16px;

            box-shadow:
                0 25px 60px rgba(15,23,42,.2);

            animation:
                modalIn .2s ease;
        }


        @keyframes modalIn {

            from {

                opacity: 0;

                transform: translateY(10px)
                    scale(.98);
            }

            to {

                opacity: 1;

                transform: translateY(0)
                    scale(1);
            }
        }


        .modal-header {

            padding: 20px 22px;

            border-bottom: 1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .modal-header h2 {

            color: var(--dark);

            font-size: 17px;
        }


        .close-btn {

            border: none;

            background: #f1f5f9;

            width: 32px;
            height: 32px;

            border-radius: 8px;

            cursor: pointer;

            color: #475569;

            font-size: 18px;
        }


        .modal-body {

            padding: 22px;
        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 17px;
        }


        .form-group.full {

            grid-column: 1 / -1;
        }


        textarea.input {

            height: 100px;

            padding: 12px;

            resize: vertical;
        }


        .required {

            color: #dc2626;
        }


        .modal-footer {

            padding: 17px 22px;

            border-top: 1px solid var(--border);

            display: flex;

            justify-content: flex-end;

            gap: 9px;
        }


        .secondary-btn {

            height: 42px;

            padding: 0 17px;

            border: 1px solid var(--border);

            background: white;

            border-radius: 8px;

            color: var(--muted);

            cursor: pointer;

            font-size: 12px;

            font-weight: 600;
        }


        .submit-btn {

            height: 42px;

            padding: 0 19px;

            border: none;

            background: var(--primary);

            color: white;

            border-radius: 8px;

            cursor: pointer;

            font-size: 12px;

            font-weight: 600;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1100px) {

            .summary-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .filter-form {

                grid-template-columns:
                    1fr 1fr;
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

            .page-header {

                align-items: flex-start;

                flex-direction: column;
            }
        }


        @media (max-width: 550px) {

            .summary-grid {

                grid-template-columns: 1fr;
            }

            .filter-form {

                grid-template-columns: 1fr;
            }

            .form-grid {

                grid-template-columns: 1fr;
            }

            .form-group.full {

                grid-column: auto;
            }

            .modal-body {

                padding: 18px;
            }

            .modal-footer {

                padding: 15px 18px;
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


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div class="page-title">

                <h1>
                    Leads
                </h1>

                <p>
                    Manage and track your sales opportunities.
                </p>

            </div>


            <button
                type="button"
                class="primary-btn"
                onclick="openAddModal()"
            >
                + Add New Lead
            </button>

        </div>


        <!-- MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="message <?= $messageType ?>">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             SUMMARY CARDS
        ================================================= -->

        <div class="summary-grid">


            <div class="summary-card">

                <div class="summary-label">
                    Total Leads
                </div>

                <div class="summary-value">
                    <?= (int)$leadCounts["total"] ?>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    New Leads
                </div>

                <div class="summary-value">
                    <?= (int)$leadCounts["new_count"] ?>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Interested
                </div>

                <div class="summary-value">
                    <?= (int)$leadCounts["interested_count"] ?>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Negotiation
                </div>

                <div class="summary-value">
                    <?= (int)$leadCounts["negotiation_count"] ?>
                </div>

            </div>

        </div>


        <!-- =================================================
             FILTERS
        ================================================= -->

        <div class="filter-card">

            <form
                method="GET"
                class="filter-form"
            >


                <div class="form-group">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        class="input"
                        placeholder="Search name, phone or email..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Stage
                    </label>

                    <select
                        name="stage"
                        class="select"
                    >

                        <option value="">
                            All stages
                        </option>

                        <?php foreach ($stages as $stage): ?>

                            <option
                                value="<?= htmlspecialchars($stage) ?>"
                                <?= $stageFilter === $stage ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars($stage) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Sales Employee
                    </label>

                    <select
                        name="employee"
                        class="select"
                    >

                        <option value="">
                            All employees
                        </option>

                        <?php foreach ($salesEmployees as $employee): ?>

                            <option
                                value="<?= (int)$employee["id"] ?>"
                                <?= (string)$employeeFilter === (string)$employee["id"] ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars($employee["name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div style="
                    display:flex;
                    gap:7px;
                ">

                    <button
                        type="submit"
                        class="filter-btn"
                    >
                        Search
                    </button>


                    <a
                        href="leads.php"
                        class="clear-btn"
                    >
                        Clear
                    </a>

                </div>

            </form>

        </div>


        <!-- =================================================
             LEADS TABLE
        ================================================= -->

        <div class="table-card">


            <div class="table-header">

                <h2>
                    All Leads
                </h2>

                <span class="result-count">

                    <?= count($leads) ?> result(s)

                </span>

            </div>


            <?php if (empty($leads)): ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        👥
                    </div>

                    <div class="empty-title">
                        No leads found
                    </div>

                    <div class="empty-text">
                        Start by adding your first sales lead.
                    </div>


                    <button
                        type="button"
                        class="primary-btn"
                        onclick="openAddModal()"
                    >
                        + Add New Lead
                    </button>

                </div>

            <?php else: ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Lead
                                </th>

                                <th>
                                    Phone
                                </th>

                                <th>
                                    Stage
                                </th>

                                <th>
                                    Assigned To
                                </th>

                                <th>
                                    Follow-up
                                </th>

                                <th>
                                    Source
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($leads as $lead): ?>

                            <?php

                            $stageClass =
                                strtolower(
                                    str_replace(
                                        " ",
                                        "-",
                                        $lead["stage"]
                                    )
                                );


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

                            ?>


                            <tr>


                                <!-- LEAD -->

                                <td>

                                    <div class="lead-info">

                                        <div class="lead-avatar">

                                            <?= htmlspecialchars(
                                                $initial
                                            ) ?>

                                        </div>


                                        <div>

                                            <div class="lead-name">

                                                <?= htmlspecialchars(
                                                    $lead["name"]
                                                ) ?>

                                            </div>


                                            <div class="lead-email">

                                                <?= $lead["email"]
                                                    ? htmlspecialchars($lead["email"])
                                                    : "No email"
                                                ?>

                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <!-- PHONE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $lead["phone"]
                                    ) ?>

                                </td>


                                <!-- STAGE -->

                                <td>

                                    <span
                                        class="stage <?= htmlspecialchars($stageClass) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $lead["stage"]
                                        ) ?>

                                    </span>

                                </td>


                                <!-- ASSIGNED -->

                                <td>

                                    <?php if (
                                        $lead["assigned_name"]
                                    ): ?>

                                        <div class="assigned">

                                            <div class="small-avatar">

                                                <?= strtoupper(
                                                    substr(
                                                        $lead["assigned_name"],
                                                        0,
                                                        1
                                                    )
                                                ) ?>

                                            </div>

                                            <?= htmlspecialchars(
                                                $lead["assigned_name"]
                                            ) ?>

                                        </div>

                                    <?php else: ?>

                                        <span class="unassigned">
                                            Unassigned
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- FOLLOW UP -->

                                <td>

                                    <?php if (
                                        $lead["follow_up_date"]
                                    ): ?>

                                        <span
                                            class="follow-date <?= $isToday ? "today" : "" ?>"
                                        >

                                            <?= date(
                                                "d M Y",
                                                strtotime(
                                                    $lead["follow_up_date"]
                                                )
                                            ) ?>

                                            <?php if ($isToday): ?>
                                                · Today
                                            <?php endif; ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="unassigned">
                                            Not scheduled
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- SOURCE -->

                                <td>

                                    <?= $lead["source"]
                                        ? htmlspecialchars($lead["source"])
                                        : "—"
                                    ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <button
                                            type="button"
                                            class="action-btn"
                                            title="Edit lead"
                                            onclick='openEditModal(
                                                <?= json_encode($lead, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
                                            )'
                                        >
                                            ✏️
                                        </button>


                                        <form
                                            method="POST"
                                            onsubmit="return confirmDelete()"
                                            style="display:inline;"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$lead["id"] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="action-btn delete-btn"
                                                title="Delete lead"
                                            >
                                                🗑️
                                            </button>

                                        </form>

                                    </div>

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


<!-- =====================================================
     ADD / EDIT MODAL
===================================================== -->

<div
    class="modal"
    id="leadModal"
    onclick="closeModalOutside(event)"
>


    <div
        class="modal-content"
        onclick="event.stopPropagation()"
    >


        <div class="modal-header">

            <h2 id="modalTitle">
                Add New Lead
            </h2>


            <button
                type="button"
                class="close-btn"
                onclick="closeModal()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            id="leadForm"
        >


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken) ?>"
            >


            <input
                type="hidden"
                name="action"
                id="formAction"
                value="add"
            >


            <input
                type="hidden"
                name="id"
                id="leadId"
                value=""
            >


            <div class="modal-body">


                <div class="form-grid">


                    <!-- NAME -->

                    <div class="form-group">

                        <label>
                            Full Name
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="leadName"
                            class="input"
                            placeholder="Enter lead name"
                            maxlength="150"
                            required
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="form-group">

                        <label>
                            Phone
                            <span class="required">*</span>
                        </label>

                        <input
                            type="tel"
                            name="phone"
                            id="leadPhone"
                            class="input"
                            placeholder="Enter phone number"
                            maxlength="30"
                            required
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label>
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            id="leadEmail"
                            class="input"
                            placeholder="customer@example.com"
                            maxlength="150"
                        >

                    </div>


                    <!-- SOURCE -->

                    <div class="form-group">

                        <label>
                            Lead Source
                        </label>

                        <select
                            name="source"
                            id="leadSource"
                            class="select"
                        >

                            <option value="">
                                Select source
                            </option>

                            <option value="Website">
                                Website
                            </option>

                            <option value="Facebook">
                                Facebook
                            </option>

                            <option value="Instagram">
                                Instagram
                            </option>

                            <option value="Google">
                                Google
                            </option>

                            <option value="Referral">
                                Referral
                            </option>

                            <option value="Walk-in">
                                Walk-in
                            </option>

                            <option value="Other">
                                Other
                            </option>

                        </select>

                    </div>


                    <!-- STAGE -->

                    <div class="form-group">

                        <label>
                            Lead Stage
                        </label>

                        <select
                            name="stage"
                            id="leadStage"
                            class="select"
                        >

                            <?php foreach ($stages as $stage): ?>

                                <option
                                    value="<?= htmlspecialchars($stage) ?>"
                                >
                                    <?= htmlspecialchars($stage) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- ASSIGN -->

                    <div class="form-group">

                        <label>
                            Assign Sales Employee
                        </label>

                        <select
                            name="assigned_to"
                            id="leadAssigned"
                            class="select"
                        >

                            <option value="">
                                Unassigned
                            </option>

                            <?php foreach ($salesEmployees as $employee): ?>

                                <option
                                    value="<?= (int)$employee["id"] ?>"
                                >
                                    <?= htmlspecialchars(
                                        $employee["name"]
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- FOLLOW UP -->

                    <div class="form-group">

                        <label>
                            Follow-up Date
                        </label>

                        <input
                            type="date"
                            name="follow_up_date"
                            id="leadFollowUp"
                            class="input"
                        >

                    </div>


                    <!-- NOTES -->

                    <div class="form-group full">

                        <label>
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            id="leadNotes"
                            class="input"
                            placeholder="Add notes about this lead..."
                            maxlength="2000"
                        ></textarea>

                    </div>


                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="secondary-btn"
                    onclick="closeModal()"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="submit-btn"
                    id="submitButton"
                >
                    Create Lead
                </button>

            </div>


        </form>

    </div>

</div>


<script>

/* =====================================================
   MODAL
===================================================== */

const modal =
    document.getElementById("leadModal");


const form =
    document.getElementById("leadForm");


function openAddModal() {

    form.reset();

    document.getElementById("modalTitle")
        .textContent = "Add New Lead";

    document.getElementById("formAction")
        .value = "add";

    document.getElementById("leadId")
        .value = "";

    document.getElementById("leadStage")
        .value = "New";

    document.getElementById("submitButton")
        .textContent = "Create Lead";

    modal.classList.add("show");

    document.getElementById("leadName")
        .focus();
}


function openEditModal(lead) {

    document.getElementById("modalTitle")
        .textContent = "Edit Lead";

    document.getElementById("formAction")
        .value = "update";

    document.getElementById("leadId")
        .value = lead.id;

    document.getElementById("leadName")
        .value = lead.name || "";

    document.getElementById("leadPhone")
        .value = lead.phone || "";

    document.getElementById("leadEmail")
        .value = lead.email || "";

    document.getElementById("leadSource")
        .value = lead.source || "";

    document.getElementById("leadStage")
        .value = lead.stage || "New";

    document.getElementById("leadAssigned")
        .value = lead.assigned_to || "";

    document.getElementById("leadFollowUp")
        .value = lead.follow_up_date || "";

    document.getElementById("leadNotes")
        .value = lead.notes || "";

    document.getElementById("submitButton")
        .textContent = "Save Changes";

    modal.classList.add("show");

    document.getElementById("leadName")
        .focus();
}


function closeModal() {

    modal.classList.remove("show");
}


function closeModalOutside(event) {

    if (event.target === modal) {

        closeModal();
    }
}


document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            closeModal();
        }

    }
);


/* =====================================================
   DELETE CONFIRMATION
===================================================== */

function confirmDelete() {

    return confirm(
        "Are you sure you want to delete this lead?\n\nThis action cannot be undone."
    );
}

</script>


</body>

</html>