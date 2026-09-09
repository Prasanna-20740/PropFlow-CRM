<?php
/* =========================================================
   PROPFlow CRM - Team / Employee Management
   ========================================================= */

require_once "../includes/auth.php";
requireRole("admin");

$user = currentUser();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
   CSRF
   ========================================================= */

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
        "Location: team.php?" .
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
        redirectWithMessage('error', 'Invalid security token.');
    }

    $action = $_POST['action'] ?? '';

    /* =====================================================
       ADD EMPLOYEE
       ===================================================== */

    if ($action === 'add_employee') {

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'sales';
        $status = $_POST['status'] ?? 'active';

        if ($name === '') {
            redirectWithMessage('error', 'Employee name is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage('error', 'Please enter a valid email address.');
        }

        if (strlen($password) < 6) {
            redirectWithMessage(
                'error',
                'Password must contain at least 6 characters.'
            );
        }

        if (!in_array($role, ['admin', 'sales'], true)) {
            redirectWithMessage('error', 'Invalid employee role.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            redirectWithMessage('error', 'Invalid employee status.');
        }

        try {

            $check = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $check->execute([$email]);

            if ($check->fetch()) {
                redirectWithMessage(
                    'error',
                    'This email address is already registered.'
                );
            }

            $stmt = $pdo->prepare("
                INSERT INTO users
                (
                    name,
                    email,
                    password,
                    role,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $status
            ]);

            redirectWithMessage(
                'success',
                $name . ' added to the team successfully.'
            );

        } catch (PDOException $e) {

            if ((int)$e->errorInfo[1] === 1062) {
                redirectWithMessage(
                    'error',
                    'This email address is already registered.'
                );
            }

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
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'sales';
        $status = $_POST['status'] ?? 'active';

        if ($employeeId <= 0) {
            redirectWithMessage('error', 'Invalid employee.');
        }

        if ($name === '') {
            redirectWithMessage('error', 'Employee name is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage('error', 'Please enter a valid email address.');
        }

        if (!in_array($role, ['admin', 'sales'], true)) {
            redirectWithMessage('error', 'Invalid employee role.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            redirectWithMessage('error', 'Invalid employee status.');
        }

        /* Prevent the currently logged-in admin from locking themselves out. */
        if ($employeeId === (int)($user['id'] ?? 0) && $status !== 'active') {
            redirectWithMessage(
                'error',
                'You cannot deactivate your own admin account.'
            );
        }

        try {

            $check = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                  AND id <> ?
                LIMIT 1
            ");

            $check->execute([
                $email,
                $employeeId
            ]);

            if ($check->fetch()) {
                redirectWithMessage(
                    'error',
                    'Another employee already uses this email address.'
                );
            }

            if ($password !== '') {

                if (strlen($password) < 6) {
                    redirectWithMessage(
                        'error',
                        'New password must contain at least 6 characters.'
                    );
                }

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        name = ?,
                        email = ?,
                        password = ?,
                        role = ?,
                        status = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $role,
                    $status,
                    $employeeId
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE users
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
            }

            redirectWithMessage(
                'success',
                $name . ' updated successfully.'
            );

        } catch (PDOException $e) {

            if ((int)$e->errorInfo[1] === 1062) {
                redirectWithMessage(
                    'error',
                    'That email address is already in use.'
                );
            }

            redirectWithMessage(
                'error',
                'Unable to update employee. Please try again.'
            );
        }
    }

    /* =====================================================
       TOGGLE STATUS
       ===================================================== */

    if ($action === 'toggle_status') {

        $employeeId = (int)($_POST['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            redirectWithMessage('error', 'Invalid employee.');
        }

        if ($employeeId === (int)($user['id'] ?? 0)) {
            redirectWithMessage(
                'error',
                'You cannot deactivate your own admin account.'
            );
        }

        try {

            $stmt = $pdo->prepare("
                SELECT id, name, status
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$employeeId]);

            $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$employee) {
                redirectWithMessage('error', 'Employee not found.');
            }

            $newStatus =
                strtolower($employee['status']) === 'active'
                    ? 'inactive'
                    : 'active';

            $update = $pdo->prepare("
                UPDATE users
                SET status = ?
                WHERE id = ?
            ");

            $update->execute([
                $newStatus,
                $employeeId
            ]);

            redirectWithMessage(
                'success',
                $employee['name'] .
                ' is now ' .
                ucfirst($newStatus) .
                '.'
            );

        } catch (PDOException $e) {

            redirectWithMessage(
                'error',
                'Unable to change employee status.'
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

$totalMembers = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
")->fetchColumn();

$activeMembers = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE status = 'active'
")->fetchColumn();

$salesMembers = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'sales'
")->fetchColumn();

$adminMembers = (int)$pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'admin'
")->fetchColumn();

/* =========================================================
   TEAM LIST
   =========================================================
   The confirmed users schema contains:
   id, name, email, password, role, status,
   created_at, updated_at.

   Lead assignment is counted through leads.assigned_to.
   ========================================================= */

$teamStmt = $pdo->query("
    SELECT
        u.id,
        u.name,
        u.email,
        u.role,
        u.status,
        u.created_at,

        (
            SELECT COUNT(*)
            FROM leads l
            WHERE l.assigned_to = u.id
        ) AS assigned_leads

    FROM users u

    ORDER BY
        CASE
            WHEN u.status = 'active' THEN 0
            ELSE 1
        END,
        CASE
            WHEN u.role = 'admin' THEN 0
            ELSE 1
        END,
        u.name ASC
");

$teamMembers = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Team | PropFlow CRM</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: #f7f9fc;
    color: #172033;
}

a {
    text-decoration: none;
    color: inherit;
}

button,
input,
select {
    font: inherit;
}

/* =========================================================
   SIDEBAR
   ========================================================= */
:root {
    --dark: #0f172a;
}
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

            border-top:
                1px solid rgba(255,255,255,.08);

            padding-top: 15px;
        }


/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {
    position: fixed;

    left: 282px;
    right: 0;
    top: 0;

    height: 84px;

    background: #fff;

    border-bottom: 1px solid #e4e9f1;

    display: flex;
    align-items: center;
    justify-content: flex-end;

    padding: 0 34px;

    z-index: 10;
}

.admin {
    display: flex;
    align-items: center;
    gap: 12px;
}

.avatar {
    width: 44px;
    height: 44px;

    border-radius: 50%;

    background: #e5efff;
    color: #2463dd;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 800;
}

.admin-name {
    font-size: 14px;
    font-weight: 700;
}

.admin-role {
    color: #75819a;
    font-size: 13px;
    margin-top: 2px;
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

    color: #71809a;

    font-size: 15px;
}

.btn {
    border: 0;

    padding: 12px 17px;

    border-radius: 10px;

    font-size: 13px;
    font-weight: 750;

    cursor: pointer;

    transition:
        transform .15s ease,
        background .15s ease,
        box-shadow .15s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

.btn-primary {
    background: #2864e8;
    color: #fff;
}

.btn-primary:hover {
    background: #1f55cb;
    box-shadow: 0 8px 20px rgba(40,100,232,.18);
}

.btn-secondary {
    background: #f1f4f8;
    color: #344158;
}

.btn-secondary:hover {
    background: #e5e9ef;
}

.btn-danger {
    background: #fff0f0;
    color: #d73535;
}

.btn-danger:hover {
    background: #ffe0e0;
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
    background: #fff;

    border: 1px solid #e1e7f0;

    border-radius: 15px;

    padding: 22px 21px;
}

.stat-label {
    color: #71809a;
    font-size: 13px;
}

.stat-value {
    font-size: 28px;
    font-weight: 800;

    margin-top: 11px;
}

/* =========================================================
   CARD
   ========================================================= */

.card {
    background: #fff;

    border: 1px solid #e1e7f0;

    border-radius: 16px;

    overflow: hidden;
}

.card-head {
    display: flex;

    justify-content: space-between;
    align-items: center;

    padding: 21px 23px;

    border-bottom: 1px solid #e8ecf2;
}

.card-title {
    margin: 0;

    font-size: 17px;
    font-weight: 750;
}

.card-count {
    color: #71809a;
    font-size: 13px;
}

/* =========================================================
   FILTERS
   ========================================================= */

.filters {
    display: flex;
    gap: 12px;

    padding: 16px 23px;

    border-bottom: 1px solid #e8ecf2;

    background: #fff;
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

    font-size: 19px;

    pointer-events: none;
}

.filter-control {
    height: 42px;

    border: 1px solid #dbe1eb;

    border-radius: 9px;

    background: #fff;

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
    width: 160px;
}

.filter-control:focus {
    border-color: #2864e8;

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

    min-width: 900px;
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

    border-top: 1px solid #edf0f5;

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

.avatar-small {
    width: 42px;
    height: 42px;

    border-radius: 12px;

    background: #eaf1ff;

    color: #2864e8;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    font-weight: 800;
}

.member-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.badge {
    display: inline-flex;

    align-items: center;

    padding: 6px 10px;

    border-radius: 999px;

    font-size: 12px;

    font-weight: 700;
}

.badge-admin {
    background: #f0ebff;
    color: #6741c7;
}

.badge-sales {
    background: #eaf3ff;
    color: #2463dd;
}

.badge-active {
    background: #eafaf1;
    color: #118449;
}

.badge-inactive {
    background: #f1f3f6;
    color: #6f7b8f;
}

.action-group {
    display: flex;
    align-items: center;
    gap: 7px;
}

.action-group .btn {
    padding: 9px 12px;
}

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

    background: rgba(13,22,40,.55);

    display: none;

    align-items: center;
    justify-content: center;

    padding: 20px;

    z-index: 100;
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;
    max-width: 570px;

    max-height: 92vh;

    overflow-y: auto;

    background: #fff;

    border-radius: 18px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.18);

    overflow: hidden;
}

.modal-head {
    display: flex;

    align-items: center;
    justify-content: space-between;

    padding: 22px 25px;

    border-bottom: 1px solid #e8ecf2;
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

.close:hover {
    background: #e8ecf2;
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

    border: 1px solid #dbe1eb;

    border-radius: 9px;

    background: #fff;

    color: #1a2436;

    outline: none;

    font-size: 14px;
}

.form-control:focus {
    border-color: #2864e8;

    box-shadow:
        0 0 0 3px rgba(40,100,232,.09);
}

.form-help {
    margin-top: 6px;

    color: #8995a8;

    font-size: 12px;
}

.modal-footer {
    display: flex;

    justify-content: flex-end;

    gap: 10px;

    padding: 18px 25px;

    border-top: 1px solid #e8ecf2;
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1100px) {

    .sidebar {
        width: 225px;
    }

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

        padding: 12px;
    }

    .brand {
        padding-bottom: 15px;
    }

    .menu-title {
        display: none;
    }

    .nav-item {
        display: inline-flex;

        width: auto;

        margin-right: 5px;
    }

    .sidebar-bottom {
        position: static;

        margin-top: 15px;
    }

    .topbar {
        position: static;

        left: auto;

        height: 70px;

        padding: 0 18px;
    }

    .main {
        margin-left: 0;

        padding: 28px 18px 40px;
    }

    .page-head {
        flex-direction: column;
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
        max-width: 100%;
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
        <div class="brand-icon">🏢</div>
        <div class="brand-name">PropFlow</div>
    </div>

    <div class="menu-title">Workspace</div>

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

        <a href="bookings.php" >
            <span class="nav-icon">✓</span>
            <span>Bookings</span>
        </a>

        <a href="team.php" class="active">
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

<!-- =======================================================
     TOPBAR
     ======================================================= -->

<header class="topbar">

    <div class="admin">

        <div class="avatar">
            <?= e(
                strtoupper(
                    substr(
                        $user['name'] ?? 'A',
                        0,
                        1
                    )
                )
            ) ?>
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
                Team Management
            </h1>

            <p class="page-subtitle">
                Manage admins and sales team members.
            </p>

        </div>

        <button
            type="button"
            class="btn btn-primary"
            onclick="openEmployeeModal()"
        >
            + Add Employee
        </button>

    </div>


    <!-- ===================================================
         ALERT
         =================================================== -->

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


    <!-- ===================================================
         STATS
         =================================================== -->

    <section class="stats">

        <div class="stat">

            <div class="stat-label">
                Total Members
            </div>

            <div class="stat-value">
                <?= number_format($totalMembers) ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Active Members
            </div>

            <div class="stat-value">
                <?= number_format($activeMembers) ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Sales Team
            </div>

            <div class="stat-value">
                <?= number_format($salesMembers) ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Admins
            </div>

            <div class="stat-value">
                <?= number_format($adminMembers) ?>
            </div>

        </div>

    </section>


    <!-- ===================================================
         TEAM CARD
         =================================================== -->

    <section class="card">

        <div class="filters">

            <div class="search-box">

                <span class="search-icon">⌕</span>

                <input
                    type="text"
                    id="teamSearch"
                    class="filter-control"
                    placeholder="Search name or email..."
                    oninput="filterTeam()"
                >

            </div>

            <select
                id="roleFilter"
                class="filter-control role-filter"
                onchange="filterTeam()"
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
                onchange="filterTeam()"
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
                Team Members
            </h2>

            <div class="card-count">
                <?= count($teamMembers) ?> member(s)
            </div>

        </div>


        <?php if (empty($teamMembers)): ?>

            <div class="empty">

                <div class="empty-icon">
                    👥
                </div>

                <div class="empty-title">
                    No team members found
                </div>

                <div>
                    Add your first employee to get started.
                </div>

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Member
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Assigned Leads
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Joined
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($teamMembers as $member): ?>

                        <?php
                        $memberSearch = strtolower(
                            ($member['name'] ?? '') .
                            ' ' .
                            ($member['email'] ?? '')
                        );

                        $memberRole =
                            strtolower($member['role'] ?? '');

                        $memberStatus =
                            strtolower($member['status'] ?? '');

                        $initial =
                            strtoupper(
                                substr(
                                    $member['name'] ?? 'U',
                                    0,
                                    1
                                )
                            );
                        ?>

                        <tr
                            class="team-row"
                            data-search="<?= e($memberSearch) ?>"
                            data-role="<?= e($memberRole) ?>"
                            data-status="<?= e($memberStatus) ?>"
                        >

                            <td>

                                <div class="member-cell">

                                    <div class="avatar-small">
                                        <?= e($initial) ?>
                                    </div>

                                    <div>

                                        <div class="primary-text">
                                            <?= e($member['name']) ?>

                                            <?php
                                            if (
                                                (int)$member['id'] ===
                                                (int)($user['id'] ?? 0)
                                            ):
                                            ?>

                                                <span
                                                    class="secondary-text"
                                                    style="
                                                        display:inline;
                                                        margin-left:5px;
                                                    "
                                                >
                                                    (You)
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                        <div class="secondary-text">
                                            <?= e($member['email']) ?>
                                        </div>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <?php if ($memberRole === 'admin'): ?>

                                    <span class="
                                        badge
                                        badge-admin
                                    ">
                                        Admin
                                    </span>

                                <?php else: ?>

                                    <span class="
                                        badge
                                        badge-sales
                                    ">
                                        Sales
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="primary-text">
                                    <?= number_format(
                                        (int)$member['assigned_leads']
                                    ) ?>
                                </div>

                                <div class="secondary-text">
                                    Lead(s)
                                </div>

                            </td>


                            <td>

                                <?php if ($memberStatus === 'active'): ?>

                                    <span class="
                                        badge
                                        badge-active
                                    ">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="
                                        badge
                                        badge-inactive
                                    ">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $member['created_at']
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>

                                <div class="action-group">

                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        onclick='editEmployee(
                                            <?= json_encode((int)$member["id"]) ?>,
                                            <?= json_encode($member["name"]) ?>,
                                            <?= json_encode($member["email"]) ?>,
                                            <?= json_encode($member["role"]) ?>,
                                            <?= json_encode($member["status"]) ?>
                                        )'
                                    >
                                        Edit
                                    </button>


                                    <?php
                                    $isCurrentUser =
                                        (int)$member['id'] ===
                                        (int)($user['id'] ?? 0);
                                    ?>

                                    <?php if (!$isCurrentUser): ?>

                                        <form
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="
                                                return confirm(
                                                    'Change this employee status?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="employee_id"
                                                value="<?= e($member['id']) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="
                                                    btn
                                                    <?= $memberStatus === 'active'
                                                        ? 'btn-danger'
                                                        : 'btn-primary'
                                                    ?>
                                                "
                                            >
                                                <?= $memberStatus === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate'
                                                ?>
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
                Add Employee
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


                <!-- NAME -->

                <div class="form-group">

                    <label class="form-label">
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="employeeName"
                        class="form-control"
                        placeholder="Enter employee name"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label class="form-label">
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="employeeEmail"
                        class="form-control"
                        placeholder="employee@example.com"
                        maxlength="150"
                        required
                    >

                </div>


                <!-- ROLE -->

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


                <!-- STATUS -->

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


                <!-- PASSWORD -->

                <div class="form-group">

                    <label class="form-label">
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        id="employeePassword"
                        class="form-control"
                        placeholder="Enter password"
                        minlength="6"
                        autocomplete="new-password"
                    >

                    <div
                        class="form-help"
                        id="passwordHelp"
                    >
                        Minimum 6 characters.
                    </div>

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


<script>

/* =========================================================
   EMPLOYEE MODAL
   ========================================================= */

function openEmployeeModal() {

    document.getElementById('employeeModalTitle').textContent =
        'Add Employee';

    document.getElementById('employeeAction').value =
        'add_employee';

    document.getElementById('employeeId').value =
        '';

    document.getElementById('employeeName').value =
        '';

    document.getElementById('employeeEmail').value =
        '';

    document.getElementById('employeeRole').value =
        'sales';

    document.getElementById('employeeStatus').value =
        'active';

    document.getElementById('employeePassword').value =
        '';

    document.getElementById('employeePassword').required =
        true;

    document.getElementById('employeePassword').placeholder =
        'Enter password';

    document.getElementById('passwordHelp').textContent =
        'Minimum 6 characters.';

    document.getElementById('employeeSubmit').textContent =
        'Add Employee';

    document
        .getElementById('employeeModal')
        .classList
        .add('show');
}


function editEmployee(
    id,
    name,
    email,
    role,
    status
) {

    document.getElementById('employeeModalTitle').textContent =
        'Edit Employee';

    document.getElementById('employeeAction').value =
        'edit_employee';

    document.getElementById('employeeId').value =
        id;

    document.getElementById('employeeName').value =
        name;

    document.getElementById('employeeEmail').value =
        email;

    document.getElementById('employeeRole').value =
        role;

    document.getElementById('employeeStatus').value =
        status;

    document.getElementById('employeePassword').value =
        '';

    document.getElementById('employeePassword').required =
        false;

    document.getElementById('employeePassword').placeholder =
        'Leave blank to keep current password';

    document.getElementById('passwordHelp').textContent =
        'Leave blank if you do not want to change the password.';

    document.getElementById('employeeSubmit').textContent =
        'Save Changes';

    document
        .getElementById('employeeModal')
        .classList
        .add('show');
}


function closeEmployeeModal() {

    document
        .getElementById('employeeModal')
        .classList
        .remove('show');
}


/* =========================================================
   SEARCH / FILTER
   ========================================================= */

function filterTeam() {

    const search =
        document
            .getElementById('teamSearch')
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
        .querySelectorAll('.team-row')
        .forEach(function(row) {

            const rowSearch =
                row.getAttribute('data-search') || '';

            const rowRole =
                row.getAttribute('data-role') || '';

            const rowStatus =
                row.getAttribute('data-status') || '';

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


/* =========================================================
   MODAL OUTSIDE CLICK
   ========================================================= */

document
    .getElementById('employeeModal')
    .addEventListener(
        'click',
        function(event) {

            if (event.target === this) {
                closeEmployeeModal();
            }

        }
    );


/* =========================================================
   ESCAPE KEY
   ========================================================= */

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {
            closeEmployeeModal();
        }

    }
);

</script>

</body>
</html>
