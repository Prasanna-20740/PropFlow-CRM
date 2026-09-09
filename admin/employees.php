<?php
/* =========================================================
   PropFlow CRM - Employee Management
   ========================================================= */

require_once "../includes/auth.php";
requireRole("admin");

require_once "../config/database.php";

$user = currentUser();

/* =========================================================
   SESSION / CSRF
   ========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/* =========================================================
   HELPERS
   ========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($type, $message)
{
    header(
        "Location: employees.php?" .
        http_build_query([
            'type' => $type,
            'message' => $message
        ])
    );
    exit;
}

/* =========================================================
   POST ACTIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        empty($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        redirectWithMessage(
            'error',
            'Invalid security token.'
        );
    }

    $action = $_POST['action'] ?? '';

    /* =====================================================
       ADD EMPLOYEE
       ===================================================== */

    if ($action === 'add_employee') {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? 'sales'));
        $status = strtolower(trim($_POST['status'] ?? 'active'));

        if ($name === '') {
            redirectWithMessage(
                'error',
                'Employee name is required.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage(
                'error',
                'Please enter a valid email address.'
            );
        }

        if (strlen($password) < 6) {
            redirectWithMessage(
                'error',
                'Password must be at least 6 characters.'
            );
        }

        if (!in_array($role, ['admin', 'sales'], true)) {
            redirectWithMessage(
                'error',
                'Invalid employee role.'
            );
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            redirectWithMessage(
                'error',
                'Invalid employee status.'
            );
        }

        try {

            /* Check duplicate email */

            $checkStmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $checkStmt->execute([$email]);

            if ($checkStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'An employee with this email already exists.'
                );
            }

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                INSERT INTO users
                (
                    name,
                    email,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $email,
                $hashedPassword,
                $role,
                $status
            ]);

            redirectWithMessage(
                'success',
                'Employee added successfully.'
            );

        } catch (Throwable $e) {

            redirectWithMessage(
                'error',
                'Unable to add employee. Please try again.'
            );
        }
    }


    /* =====================================================
       EDIT EMPLOYEE
       ===================================================== */

    if ($action === 'edit_employee') {

        $employeeId = (int)($_POST['employee_id'] ?? 0);

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = strtolower(trim($_POST['role'] ?? 'sales'));
        $status = strtolower(trim($_POST['status'] ?? 'active'));

        if ($employeeId <= 0) {
            redirectWithMessage(
                'error',
                'Invalid employee.'
            );
        }

        if ($name === '') {
            redirectWithMessage(
                'error',
                'Employee name is required.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage(
                'error',
                'Please enter a valid email address.'
            );
        }

        if (!in_array($role, ['admin', 'sales'], true)) {
            redirectWithMessage(
                'error',
                'Invalid employee role.'
            );
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            redirectWithMessage(
                'error',
                'Invalid employee status.'
            );
        }

        try {

            /* Check employee */

            $employeeStmt = $pdo->prepare(" SELECT id
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $employeeStmt->execute([$employeeId]);

            if (!$employeeStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'Employee not found.'
                );
            }


            /* Duplicate email */

            $duplicateStmt = $pdo->prepare("  SELECT id
                FROM users
                WHERE email = ?
                  AND id != ?
                LIMIT 1
            ");

            $duplicateStmt->execute([
                $email,
                $employeeId
            ]);

            if ($duplicateStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'Another employee is already using this email.'
                );
            }


            /* Prevent current admin from disabling own account */

            if (
                $employeeId === (int)($user['id'] ?? 0) &&
                $status !== 'active'
            ) {
                redirectWithMessage(
                    'error',
                    'You cannot deactivate your own account.'
                );
            }


            /* Update */

            $stmt = $pdo->prepare("  UPDATE users
                SET
                    name = ?,
                    email = ?,
                    role = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $email,
                $role,
                $status,
                $employeeId
            ]);

            redirectWithMessage(
                'success',
                'Employee updated successfully.'
            );

        } catch (Throwable $e) {

            redirectWithMessage(
                'error',
                'Unable to update employee. Please try again.'
            );
        }
    }


    /* =====================================================
       CHANGE PASSWORD
       ===================================================== */

    if ($action === 'change_password') {

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if ($employeeId <= 0) {
            redirectWithMessage(
                'error',
                'Invalid employee.'
            );
        }

        if (strlen($newPassword) < 6) {
            redirectWithMessage(
                'error',
                'Password must be at least 6 characters.'
            );
        }

        try {

            $checkStmt = $pdo->prepare(" SELECT id
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $checkStmt->execute([$employeeId]);

            if (!$checkStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'Employee not found.'
                );
            }

            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(" UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $hashedPassword,
                $employeeId
            ]);

            redirectWithMessage(
                'success',
                'Password updated successfully.'
            );

        } catch (Throwable $e) {

            redirectWithMessage(
                'error',
                'Unable to update password.'
            );
        }
    }


    /* =====================================================
       DELETE EMPLOYEE
       ===================================================== */

    if ($action === 'delete_employee') {

        $employeeId = (int)($_POST['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            redirectWithMessage(
                'error',
                'Invalid employee.'
            );
        }

        if (
            $employeeId ===
            (int)($user['id'] ?? 0)
        ) {
            redirectWithMessage(
                'error',
                'You cannot delete your own account.'
            );
        }

        try {

            $checkStmt = $pdo->prepare(" SELECT id
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $checkStmt->execute([$employeeId]);

            if (!$checkStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'Employee not found.'
                );
            }


            /*
             * Before deleting, check whether this user
             * is assigned to leads.
             */

            $leadStmt = $pdo->prepare(" SELECT COUNT(*)
                FROM leads
                WHERE assigned_to = ?
            ");

            $leadStmt->execute([$employeeId]);

            $leadCount = (int)$leadStmt->fetchColumn();

            if ($leadCount > 0) {

                redirectWithMessage(
                    'error',
                    'This employee has ' .
                    $leadCount .
                    ' assigned lead(s). Deactivate the employee instead of deleting.'
                );
            }


            $deleteStmt = $pdo->prepare(" DELETE FROM users
                WHERE id = ?
            ");

            $deleteStmt->execute([
                $employeeId
            ]);

            redirectWithMessage(
                'success',
                'Employee deleted successfully.'
            );

        } catch (Throwable $e) {

            redirectWithMessage(
                'error',
                'Unable to delete employee. Please deactivate the account instead.'
            );
        }
    }
}


/* =========================================================
   MESSAGES
   ========================================================= */

$messageType = $_GET['type'] ?? '';
$message = $_GET['message'] ?? '';


/* =========================================================
   STATISTICS
   ========================================================= */

$totalEmployees = (int)$pdo->query(" SELECT COUNT(*)
    FROM users
")->fetchColumn();

$activeEmployees = (int)$pdo->query(" SELECT COUNT(*)
    FROM users
    WHERE status = 'active'
")->fetchColumn();

$salesEmployees = (int)$pdo->query(" SELECT COUNT(*)
    FROM users
    WHERE role = 'sales'
")->fetchColumn();

$adminEmployees = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'admin'
")->fetchColumn();


/* =========================================================
   EMPLOYEES
   ========================================================= */

$employeesStmt = $pdo->query("
    SELECT
        id,
        name,
        email,
        role,
        status
    FROM users
    ORDER BY id DESC
");

$employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Employees | PropFlow CRM</title>

<style>

* {
    box-sizing: border-box;
}

:root {
    --dark: #0b1220;
    --blue: #2864e8;
    --bg: #f7f9fc;
    --border: #e1e7f0;
    --muted: #71809a;
    --text: #172033;
}

body {
    margin: 0;

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: var(--bg);

    color: var(--text);
}

a {
    text-decoration: none;

    color: inherit;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

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

    border-top:
        1px solid rgba(255,255,255,.08);

    padding-top: 15px;
}


/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {
    position: fixed;

    top: 0;

    left: 260px;

    right: 0;

    height: 72px;

    display: flex;

    align-items: center;

    justify-content: flex-end;

    padding: 0 38px;

    background: white;

    border-bottom:
        1px solid #e8ecf2;

    z-index: 900;
}

.admin {
    display: flex;

    align-items: center;

    gap: 12px;
}

.avatar {
    width: 40px;
    height: 40px;

    border-radius: 999px;

    background: #dbe6ff;

    color: #2864e8;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;

    font-size: 15px;
}

.admin-name {
    font-weight: 700;

    font-size: 14px;
}

.admin-role {
    color: #8290a8;

    font-size: 12px;
}


/* =========================================================
   MAIN
   ========================================================= */

.main {
    margin-left: 282px;

    padding: 122px 38px 50px;

    min-height: 100vh;
}

.page-head {
    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 28px;
}

.page-title {
    margin: 0;

    font-size: 31px;

    line-height: 1.2;

    letter-spacing: -.02em;
}

.page-subtitle {
    margin: 7px 0 0;

    color: var(--muted);

    font-size: 15px;
}


/* =========================================================
   BUTTONS
   ========================================================= */

.btn {
    border: 0;

    padding: 13px 20px;

    border-radius: 11px;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s;
}

.btn-primary {
    background: var(--blue);

    color: white;
}

.btn-primary:hover {
    background: #1f55cb;
}

.btn-secondary {
    background: #f1f4f8;

    color: #344158;
}

.btn-secondary:hover {
    background: #e7ebf1;
}

.btn-danger {
    background: #fff0f0;

    color: #d73535;
}

.btn-danger:hover {
    background: #ffe1e1;
}


/* =========================================================
   ALERT
   ========================================================= */

.alert {
    padding: 14px 18px;

    border-radius: 11px;

    margin-bottom: 22px;

    font-size: 14px;
}

.alert-success {
    background: #effcf4;

    color: #118449;

    border: 1px solid #b9f0ce;
}

.alert-error {
    background: #fff2f2;

    color: #c52c2c;

    border: 1px solid #ffc7c7;
}


/* =========================================================
   STATS
   ========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 16px;

    margin-bottom: 25px;
}

.stat {
    background: white;

    border: 1px solid var(--border);

    border-radius: 15px;

    padding: 23px 21px;
}

.stat-label {
    color: var(--muted);

    font-size: 13px;
}

.stat-value {
    font-size: 28px;

    font-weight: 800;

    margin-top: 12px;
}


/* =========================================================
   CARD
   ========================================================= */

.card {
    background: white;

    border: 1px solid var(--border);

    border-radius: 16px;

    overflow: hidden;
}

.card-head {
    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 21px 23px;

    border-bottom:
        1px solid #e8ecf2;
}

.card-title {
    margin: 0;

    font-size: 17px;

    font-weight: 750;
}

.card-count {
    color: var(--muted);

    font-size: 13px;
}


/* =========================================================
   FILTERS
   ========================================================= */

.filters {
    display: flex;

    gap: 12px;

    padding: 16px 23px;

    border-bottom:
        1px solid #e8ecf2;
}

.search-box {
    position: relative;

    flex: 1;
}

.search-icon {
    position: absolute;

    left: 13px;

    top: 50%;

    transform: translateY(-50%);

    color: #8794a9;

    font-size: 20px;

    pointer-events: none;
}

.filter-control {
    height: 42px;

    border:
        1px solid #dbe1eb;

    border-radius: 9px;

    background: white;

    color: #1a2436;

    outline: none;

    font-size: 14px;

    padding: 0 13px;
}

.search-box .filter-control {
    width: 100%;

    padding-left: 38px;
}

.role-filter,
.status-filter {
    width: 150px;
}

.filter-control:focus {
    border-color: var(--blue);

    box-shadow:
        0 0 0 3px rgba(40,100,232,.09);
}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}

th {
    text-align: left;

    padding: 15px 21px;

    background: #fbfcfe;

    color: #8090aa;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .08em;

    font-weight: 750;
}

td {
    padding: 17px 21px;

    border-top:
        1px solid #edf0f5;

    font-size: 14px;

    vertical-align: middle;
}

.primary-text {
    font-weight: 700;
}

.secondary-text {
    margin-top: 4px;

    color: #8290a8;

    font-size: 12px;
}


/* =========================================================
   BADGES
   ========================================================= */

.badge {
    display: inline-flex;

    align-items: center;

    padding: 6px 10px;

    border-radius: 999px;

    font-size: 12px;

    font-weight: 700;
}

.badge-admin {
    background: #f0eaff;

    color: #6d3fd3;
}

.badge-sales {
    background: #edf4ff;

    color: #2864e8;
}

.badge-active {
    background: #eafaf1;

    color: #118449;
}

.badge-inactive {
    background: #fff0f0;

    color: #d03535;
}

.action-group {
    display: flex;

    gap: 7px;

    align-items: center;
}

.action-group .btn {
    padding: 9px 13px;

    font-size: 12px;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {
    padding: 70px 25px;

    text-align: center;

    color: #7a879d;
}

.empty-icon {
    font-size: 38px;

    margin-bottom: 12px;
}

.empty-title {
    color: #1a2436;

    font-weight: 750;

    margin-bottom: 6px;
}


/* =========================================================
   MODAL
   ========================================================= */

.modal {
    position: fixed;

    inset: 0;

    background:
        rgba(13,22,40,.55);

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    z-index: 1100;
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;

    max-width: 570px;

    background: white;

    border-radius: 18px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.18);

    overflow: hidden;

    max-height: 90vh;

    overflow-y: auto;
}

.modal-head {
    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 22px 25px;

    border-bottom:
        1px solid #e8ecf2;
}

.modal-title {
    margin: 0;

    font-size: 19px;
}

.close {
    width: 35px;
    height: 35px;

    border: 0;

    border-radius: 9px;

    background: #f3f5f8;

    cursor: pointer;

    font-size: 19px;
}

.modal-body {
    padding: 25px;
}

.form-group {
    margin-bottom: 18px;
}

.form-label {
    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 700;

    color: #344158;
}

.form-control {
    width: 100%;

    height: 45px;

    padding: 0 13px;

    border:
        1px solid #dbe1eb;

    border-radius: 9px;

    background: white;

    color: #1a2436;

    outline: none;

    font-size: 14px;
}

.form-control:focus {
    border-color: var(--blue);

    box-shadow:
        0 0 0 3px rgba(40,100,232,.09);
}

.modal-footer {
    display: flex;

    justify-content: flex-end;

    gap: 10px;

    padding: 18px 25px;

    border-top:
        1px solid #e8ecf2;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1100px) {

    .topbar {
        left: 225px;
    }

    .main {
        margin-left: 225px;
    }

    .stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {

    .sidebar {
        position: static;

        width: 100%;

        min-height: auto;

        padding: 15px;
    }

    .brand {
        margin-bottom: 18px;
    }

    .nav {
        flex-direction: row;

        flex-wrap: wrap;
    }

    .nav a {
        flex: 1 1 140px;
    }

    .sidebar-bottom {
        position: static;

        margin-top: 15px;
    }

    .topbar {
        position: static;

        height: 70px;

        padding: 0 18px;

        justify-content: flex-end;
    }

    .main {
        margin-left: 0;

        padding: 28px 18px 40px;
    }

    .page-head {
        flex-direction: column;
    }

    .page-head .btn {
        width: 100%;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .filters {
        flex-direction: column;
    }

    .role-filter,
    .status-filter {
        width: 100%;
    }

    .modal-box {
        max-height: 94vh;
    }
}

</style>

</head>

<body>


<!-- =======================================================
     SIDEBAR
     ======================================================= -->

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

        <a href="buildings.php">
            <span class="nav-icon">🏢</span>
            <span>Buildings</span>
        </a>

        <a href="employees.php" class="active">
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


<!-- =======================================================
     TOPBAR
     ======================================================= -->

<header class="topbar">

    <div class="admin">

        <div class="avatar">

            <?php
            echo strtoupper(
                substr(
                    $user['name'] ?? 'A',
                    0,
                    1
                )
            );
            ?>

        </div>

        <div>

            <div class="admin-name">
                <?= e($user['name'] ?? 'System Admin') ?>
            </div>

            <div class="admin-role">
                <?= e($user['role'] ?? 'admin') ?>
            </div>

        </div>

    </div>

</header>


<!-- =======================================================
     MAIN
     ======================================================= -->

<main class="main">

    <div class="page-head">

        <div>

            <h1 class="page-title">
                Employee Management
            </h1>

            <p class="page-subtitle">
                Manage employees, roles and account access.
            </p>

        </div>

        <button
            type="button"
            class="btn btn-primary"
            onclick="openAddModal()"
        >
            + Add Employee
        </button>

    </div>


    <!-- ALERT -->

    <?php if ($message !== ''): ?>

        <div class="
            alert
            <?= $messageType === 'success'
                ? 'alert-success'
                : 'alert-error'
            ?>
        ">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <!-- STATS -->

    <section class="stats">

        <div class="stat">

            <div class="stat-label">
                Total Employees
            </div>

            <div class="stat-value">
                <?= number_format($totalEmployees) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Active Employees
            </div>

            <div class="stat-value">
                <?= number_format($activeEmployees) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Sales Team
            </div>

            <div class="stat-value">
                <?= number_format($salesEmployees) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Admins
            </div>

            <div class="stat-value">
                <?= number_format($adminEmployees) ?>
            </div>

        </div>

    </section>


    <!-- EMPLOYEE TABLE -->

    <section class="card">

        <div class="filters">

            <div class="search-box">

                <span class="search-icon">
                    ⌕
                </span>

                <input
                    type="text"
                    id="employeeSearch"
                    class="filter-control"
                    placeholder="Search employee or email..."
                    oninput="filterEmployees()"
                >

            </div>


            <select
                id="roleFilter"
                class="filter-control role-filter"
                onchange="filterEmployees()"
            >

                <option value="all">
                    All Roles
                </option>

                <option value="admin">
                    Admin
                </option>

                <option value="sales">
                    Sales
                </option>

            </select>


            <select
                id="statusFilter"
                class="filter-control status-filter"
                onchange="filterEmployees()"
            >

                <option value="all">
                    All Status
                </option>

                <option value="active">
                    Active
                </option>

                <option value="inactive">
                    Inactive
                </option>

            </select>

        </div>


        <div class="card-head">

            <h2 class="card-title">
                Employees
            </h2>

            <div class="card-count">
                <?= count($employees) ?> employee(s)
            </div>

        </div>


        <?php if (empty($employees)): ?>

            <div class="empty">

                <div class="empty-icon">
                    👥
                </div>

                <div class="empty-title">
                    No employees yet
                </div>

                <div>
                    Add your first employee.
                </div>

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Employee
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($employees as $employee): ?>

                        <tr
                            class="employee-row"

                            data-search="<?= e(
                                strtolower(
                                    ($employee['name'] ?? '') .
                                    ' ' .
                                    ($employee['email'] ?? '')
                                )
                            ) ?>"

                            data-role="<?= e(
                                strtolower(
                                    $employee['role'] ?? ''
                                )
                            ) ?>"

                            data-status="<?= e(
                                strtolower(
                                    $employee['status'] ?? ''
                                )
                            ) ?>"
                        >

                            <td>

                                <div class="primary-text">
                                    <?= e($employee['name']) ?>
                                </div>

                                <div class="secondary-text">
                                    Employee #<?= e($employee['id']) ?>
                                </div>

                            </td>


                            <td>

                                <?= e($employee['email']) ?>

                            </td>


                            <td>

                                <?php
                                $role =
                                    strtolower(
                                        $employee['role'] ?? ''
                                    );
                                ?>

                                <?php if ($role === 'admin'): ?>

                                    <span class="badge badge-admin">
                                        Admin
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-sales">
                                        Sales
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php
                                $status =
                                    strtolower(
                                        $employee['status'] ?? ''
                                    );
                                ?>

                                <?php if ($status === 'active'): ?>

                                    <span class="badge badge-active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="action-group">

                                    <button
                                        type="button"
                                        class="btn btn-secondary"

                                        onclick='openEditModal(
                                            <?= json_encode(
                                                (int)$employee["id"]
                                            ) ?>,

                                            <?= json_encode(
                                                $employee["name"]
                                            ) ?>,

                                            <?= json_encode(
                                                $employee["email"]
                                            ) ?>,

                                            <?= json_encode(
                                                strtolower(
                                                    $employee["role"]
                                                )
                                            ) ?>,

                                            <?= json_encode(
                                                strtolower(
                                                    $employee["status"]
                                                )
                                            ) ?>
                                        )'
                                    >
                                        Edit
                                    </button>


                                    <button
                                        type="button"
                                        class="btn btn-secondary"

                                        onclick='openPasswordModal(
                                            <?= json_encode(
                                                (int)$employee["id"]
                                            ) ?>,

                                            <?= json_encode(
                                                $employee["name"]
                                            ) ?>
                                        )'
                                    >
                                        Password
                                    </button>


                                    <?php
                                    $isCurrentUser =
                                        (int)$employee['id'] ===
                                        (int)($user['id'] ?? 0);
                                    ?>

                                    <?php if (!$isCurrentUser): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Are you sure you want to delete this employee?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete_employee"
                                            >

                                            <input
                                                type="hidden"
                                                name="employee_id"
                                                value="<?= e(
                                                    $employee['id']
                                                ) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-danger"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>


<!-- =======================================================
     ADD / EDIT EMPLOYEE MODAL
     ======================================================= -->

<div
    class="modal"
    id="employeeModal"
>

    <div class="modal-box">

        <div class="modal-head">

            <h2
                class="modal-title"
                id="employeeModalTitle"
            >
                Add New Employee
            </h2>

            <button
                type="button"
                class="close"
                onclick="closeEmployeeModal()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            id="employeeForm"
        >

            <div class="modal-body">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    id="employeeAction"
                    value="add_employee"
                >

                <input
                    type="hidden"
                    name="employee_id"
                    id="employeeId"
                    value=""
                >


                <div class="form-group">

                    <label class="form-label">
                        Employee Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="employeeName"
                        class="form-control"
                        placeholder="Enter employee name"
                        maxlength="150"
                        required
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="employeeEmail"
                        class="form-control"
                        placeholder="Enter email address"
                        maxlength="255"
                        required
                    >

                </div>


                <div
                    class="form-group"
                    id="passwordGroup"
                >

                    <label class="form-label">
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        id="employeePassword"
                        class="form-control"
                        placeholder="Minimum 6 characters"
                        minlength="6"
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Role
                    </label>

                    <select
                        name="role"
                        id="employeeRole"
                        class="form-control"
                        required
                    >

                        <option value="sales">
                            Sales
                        </option>

                        <option value="admin">
                            Admin
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Status
                    </label>

                    <select
                        name="status"
                        id="employeeStatus"
                        class="form-control"
                        required
                    >

                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>

                    </select>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeEmployeeModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="employeeSubmit"
                >
                    Add Employee
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =======================================================
     PASSWORD MODAL
     ======================================================= -->

<div
    class="modal"
    id="passwordModal"
>

    <div class="modal-box">

        <div class="modal-head">

            <h2 class="modal-title">
                Change Password
            </h2>

            <button
                type="button"
                class="close"
                onclick="closePasswordModal()"
            >
                ×
            </button>

        </div>


        <form method="POST">

            <div class="modal-body">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="change_password"
                >

                <input
                    type="hidden"
                    name="employee_id"
                    id="passwordEmployeeId"
                    value=""
                >


                <div
                    class="form-label"
                    style="margin-bottom:18px;"
                >
                    Update password for
                    <span
                        id="passwordEmployeeName"
                        class="primary-text"
                    ></span>
                </div>


                <div class="form-group">

                    <label class="form-label">
                        New Password
                    </label>

                    <input
                        type="password"
                        name="new_password"
                        class="form-control"
                        placeholder="Minimum 6 characters"
                        minlength="6"
                        required
                    >

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closePasswordModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Password
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================================================
   ADD EMPLOYEE
   ========================================================= */

function openAddModal() {

    document.getElementById(
        'employeeModalTitle'
    ).textContent =
        'Add New Employee';

    document.getElementById(
        'employeeAction'
    ).value =
        'add_employee';

    document.getElementById(
        'employeeId'
    ).value =
        '';

    document.getElementById(
        'employeeName'
    ).value =
        '';

    document.getElementById(
        'employeeEmail'
    ).value =
        '';

    document.getElementById(
        'employeePassword'
    ).value =
        '';

    document.getElementById(
        'employeePassword'
    ).required =
        true;

    document.getElementById(
        'passwordGroup'
    ).style.display =
        '';

    document.getElementById(
        'employeeRole'
    ).value =
        'sales';

    document.getElementById(
        'employeeStatus'
    ).value =
        'active';

    document.getElementById(
        'employeeSubmit'
    ).textContent =
        'Add Employee';

    document.getElementById(
        'employeeModal'
    ).classList.add('show');
}


/* =========================================================
   EDIT EMPLOYEE
   ========================================================= */

function openEditModal(
    id,
    name,
    email,
    role,
    status
) {

    document.getElementById(
        'employeeModalTitle'
    ).textContent =
        'Edit Employee';

    document.getElementById(
        'employeeAction'
    ).value =
        'edit_employee';

    document.getElementById(
        'employeeId'
    ).value =
        id;

    document.getElementById(
        'employeeName'
    ).value =
        name;

    document.getElementById(
        'employeeEmail'
    ).value =
        email;

    document.getElementById(
        'employeePassword'
    ).value =
        '';

    document.getElementById(
        'employeePassword'
    ).required =
        false;

    document.getElementById(
        'passwordGroup'
    ).style.display =
        'none';

    document.getElementById(
        'employeeRole'
    ).value =
        role;

    document.getElementById(
        'employeeStatus'
    ).value =
        status;

    document.getElementById(
        'employeeSubmit'
    ).textContent =
        'Update Employee';

    document.getElementById(
        'employeeModal'
    ).classList.add('show');
}


/* =========================================================
   CLOSE EMPLOYEE MODAL
   ========================================================= */

function closeEmployeeModal() {

    document.getElementById(
        'employeeModal'
    ).classList.remove('show');
}


/* =========================================================
   PASSWORD MODAL
   ========================================================= */

function openPasswordModal(
    id,
    name
) {

    document.getElementById(
        'passwordEmployeeId'
    ).value =
        id;

    document.getElementById(
        'passwordEmployeeName'
    ).textContent =
        name;

    document.getElementById(
        'passwordModal'
    ).classList.add('show');
}


function closePasswordModal() {

    document.getElementById(
        'passwordModal'
    ).classList.remove('show');
}


/* =========================================================
   CLICK OUTSIDE MODALS
   ========================================================= */

document.getElementById(
    'employeeModal'
).addEventListener(
    'click',
    function(event) {

        if (event.target === this) {
            closeEmployeeModal();
        }

    }
);


document.getElementById(
    'passwordModal'
).addEventListener(
    'click',
    function(event) {

        if (event.target === this) {
            closePasswordModal();
        }

    }
);


/* =========================================================
   ESCAPE
   ========================================================= */

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {

            closeEmployeeModal();

            closePasswordModal();

        }

    }
);


/* =========================================================
   SEARCH + FILTER
   ========================================================= */

function filterEmployees() {

    const search =
        document
            .getElementById('employeeSearch')
            .value
            .trim()
            .toLowerCase();

    const role =
        document
            .getElementById('roleFilter')
            .value
            .toLowerCase();

    const status =
        document
            .getElementById('statusFilter')
            .value
            .toLowerCase();


    document
        .querySelectorAll('.employee-row')
        .forEach(function(row) {

            const rowSearch =
                row.getAttribute(
                    'data-search'
                ) || '';

            const rowRole =
                row.getAttribute(
                    'data-role'
                ) || '';

            const rowStatus =
                row.getAttribute(
                    'data-status'
                ) || '';


            const matchesSearch =
                search === '' ||
                rowSearch.includes(search);


            const matchesRole =
                role === 'all' ||
                rowRole === role;


            const matchesStatus =
                status === 'all' ||
                rowStatus === status;


            row.style.display =
                matchesSearch &&
                matchesRole &&
                matchesStatus
                    ? ''
                    : 'none';

        });
}

</script>

</body>

</html>