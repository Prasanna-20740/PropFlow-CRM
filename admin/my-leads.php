<?php
/* =========================================================
   PROPFlow CRM - Lead Assignment & Sales Workflow
   ========================================================= */

require_once "../includes/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = currentUser();

/* Allow both admin and sales users */
if (!$user || !in_array(strtolower($user['role'] ?? ''), ['admin', 'sales'], true)) {
    header("Location: ../login.php");
    exit;
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

function redirectMessage($type, $message)
{
    header(
        "Location: my-leads.php?" .
        http_build_query([
            'type' => $type,
            'message' => $message
        ])
    );
    exit;
}

$isAdmin = strtolower($user['role'] ?? '') === 'admin';
$currentUserId = (int)($user['id'] ?? 0);

/* =========================================================
   POST ACTIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        redirectMessage('error', 'Invalid security token.');
    }

    $action = $_POST['action'] ?? '';

    /* =====================================================
       ASSIGN LEAD
       ===================================================== */

    if ($action === 'assign_lead') {

        if (!$isAdmin) {
            redirectMessage(
                'error',
                'Only administrators can assign leads.'
            );
        }

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);

        if ($leadId <= 0 || $assignedTo <= 0) {
            redirectMessage(
                'error',
                'Please select a valid lead and sales employee.'
            );
        }

        try {

            $checkEmployee = $pdo->prepare(" SELECT id, name
                FROM users
                WHERE id = ?
                  AND role = 'sales'
                  AND status = 'active'
                LIMIT 1
            ");

            $checkEmployee->execute([$assignedTo]);

            $employee = $checkEmployee->fetch(PDO::FETCH_ASSOC);

            if (!$employee) {
                redirectMessage(
                    'error',
                    'Please select an active sales employee.'
                );
            }

            $checkLead = $pdo->prepare(" SELECT id
                FROM leads
                WHERE id = ?
                LIMIT 1
            ");

            $checkLead->execute([$leadId]);

            if (!$checkLead->fetch()) {
                redirectMessage(
                    'error',
                    'Lead not found.'
                );
            }

            $stmt = $pdo->prepare(" UPDATE leads
                SET assigned_to = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $assignedTo,
                $leadId
            ]);

            redirectMessage(
                'success',
                'Lead assigned to ' . $employee['name'] . ' successfully.'
            );

        } catch (PDOException $e) {

            redirectMessage(
                'error',
                'Unable to assign lead. Please try again.'
            );
        }
    }

    /* =====================================================
       UPDATE LEAD
       ===================================================== */

    if ($action === 'update_lead') {

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $stage = $_POST['stage'] ?? 'New';
        $followUpDate = trim($_POST['follow_up_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $allowedStages = [
            'New',
            'Contacted',
            'Site Visit',
            'Interested',
            'Negotiation',
            'Booked',
            'Lost'
        ];

        if ($leadId <= 0) {
            redirectMessage('error', 'Invalid lead.');
        }

        if (!in_array($stage, $allowedStages, true)) {
            redirectMessage('error', 'Invalid lead stage.');
        }

        if (!$isAdmin) {

            $ownership = $pdo->prepare(" SELECT id
                FROM leads
                WHERE id = ?
                  AND assigned_to = ?
                LIMIT 1
            ");

            $ownership->execute([
                $leadId,
                $currentUserId
            ]);

            if (!$ownership->fetch()) {
                redirectMessage(
                    'error',
                    'You can only update leads assigned to you.'
                );
            }
        }

        if ($followUpDate !== '') {

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $followUpDate
            );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !== $followUpDate
            ) {
                redirectMessage(
                    'error',
                    'Please enter a valid follow-up date.'
                );
            }

        } else {
            $followUpDate = null;
        }

        try {

            $stmt = $pdo->prepare(" UPDATE leads
                SET
                    stage = ?,
                    follow_up_date = ?,
                    notes = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $stage,
                $followUpDate,
                $notes !== '' ? $notes : null,
                $leadId
            ]);

            redirectMessage(
                'success',
                'Lead updated successfully.'
            );

        } catch (PDOException $e) {

            redirectMessage(
                'error',
                'Unable to update lead. Please try again.'
            );
        }
    }
}

/* =========================================================
   SALES EMPLOYEES
   ========================================================= */

$salesStmt = $pdo->query(" SELECT
        id,
        name,
        email
    FROM users
    WHERE role = 'sales'
      AND status = 'active'
    ORDER BY name ASC
");

$salesEmployees = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   LEAD LIST
   ========================================================= */

$leadSql = " SELECT
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

        u.name AS assigned_name,
        u.email AS assigned_email

    FROM leads l

    LEFT JOIN users u
        ON u.id = l.assigned_to
";

$leadParams = [];

if (!$isAdmin) {
    $leadSql .= "
        WHERE l.assigned_to = ?
    ";

    $leadParams[] = $currentUserId;
}

$leadSql .= " ORDER BY
        CASE
            WHEN l.follow_up_date IS NOT NULL
             AND l.follow_up_date < CURDATE()
             AND l.stage NOT IN ('Booked', 'Lost')
            THEN 0
            WHEN l.follow_up_date = CURDATE()
            THEN 1
            ELSE 2
        END,
        l.created_at DESC
";

$leadStmt = $pdo->prepare($leadSql);
$leadStmt->execute($leadParams);

$leads = $leadStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   COUNTS
   ========================================================= */

if ($isAdmin) {

    $totalLeads = (int)$pdo->query(" SELECT COUNT(*)
        FROM leads
    ")->fetchColumn();

    $activeLeads = (int)$pdo->query(" SELECT COUNT(*)
        FROM leads
        WHERE stage NOT IN ('Booked', 'Lost')
    ")->fetchColumn();

    $followUps = (int)$pdo->query(" SELECT COUNT(*)
        FROM leads
        WHERE follow_up_date = CURDATE()
          AND stage NOT IN ('Booked', 'Lost')
    ")->fetchColumn();

    $unassigned = (int)$pdo->query(" SELECT COUNT(*)
        FROM leads
        WHERE assigned_to IS NULL
           OR assigned_to = 0
    ")->fetchColumn();

} else {

    $countStmt = $pdo->prepare(" SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN stage NOT IN ('Booked', 'Lost')
                    THEN 1
                    ELSE 0
                END
            ) AS active,
            SUM(
                CASE
                    WHEN follow_up_date = CURDATE()
                     AND stage NOT IN ('Booked', 'Lost')
                    THEN 1
                    ELSE 0
                END
            ) AS today_followups
        FROM leads
        WHERE assigned_to = ?
    ");

    $countStmt->execute([$currentUserId]);

    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    $totalLeads = (int)($counts['total'] ?? 0);
    $activeLeads = (int)($counts['active'] ?? 0);
    $followUps = (int)($counts['today_followups'] ?? 0);
    $unassigned = 0;
}

$messageType = $_GET['type'] ?? '';
$message = $_GET['message'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    <?= $isAdmin ? 'Lead Assignment' : 'My Leads' ?>
    | PropFlow CRM
</title>

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

button,
input,
select,
textarea {
    font: inherit;
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

    width: 282px;

    background: #0d1628;

    color: #fff;

    padding: 28px 18px;

    z-index: 20;
}

.brand {
    display: flex;

    align-items: center;

    gap: 14px;

    padding: 8px 12px 34px;
}

.brand-icon {
    width: 48px;
    height: 48px;

    border-radius: 14px;

    background: #26344b;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 23px;
}

.brand-name {
    font-size: 25px;
    font-weight: 750;
}

.menu-title {
    color: #73809a;

    font-size: 12px;

    font-weight: 700;

    padding: 10px 12px 14px;

    letter-spacing: .08em;
}

.nav-item {
    display: flex;

    align-items: center;

    gap: 14px;

    height: 48px;

    padding: 0 15px;

    margin-bottom: 7px;

    border-radius: 11px;

    color: #aab6ce;

    font-size: 15px;
}

.nav-item:hover {
    background: #17233b;
    color: #fff;
}

.nav-item.active {
    background: #2864e8;

    color: #fff;

    font-weight: 700;
}

.nav-icon {
    width: 20px;
    text-align: center;
}

.sidebar-bottom {
    position: absolute;

    left: 18px;
    right: 18px;
    bottom: 25px;

    padding-top: 20px;

    border-top: 1px solid #273147;
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

    padding: 10px 14px;

    border-radius: 9px;

    font-size: 13px;

    font-weight: 750;

    cursor: pointer;

    transition: .15s ease;
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
}

.btn-secondary {
    background: #f0f3f7;
    color: #334158;
}

.btn-secondary:hover {
    background: #e5e9ef;
}

.btn-danger {
    background: #fff0f0;
    color: #d73535;
}

.btn-success {
    background: #eafaf1;
    color: #118449;
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

.filter-select {
    width: 165px;
}

.filter-control:focus,
.form-control:focus,
textarea:focus {
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

    min-width: 1100px;
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

.phone {
    color: #4e5c74;
}

.badge {
    display: inline-flex;

    align-items: center;

    padding: 6px 10px;

    border-radius: 999px;

    font-size: 12px;

    font-weight: 700;
}

.stage-new {
    background: #eef3ff;
    color: #2864e8;
}

.stage-contacted {
    background: #f2efff;
    color: #6844c8;
}

.stage-site {
    background: #fff7e8;
    color: #ad6a00;
}

.stage-interested {
    background: #eafaf1;
    color: #118449;
}

.stage-negotiation {
    background: #fff0e8;
    color: #c85a16;
}

.stage-booked {
    background: #e7f8ee;
    color: #08753d;
}

.stage-lost {
    background: #fff0f0;
    color: #d73535;
}

.badge-unassigned {
    background: #fff7e8;
    color: #a76600;
}

.followup-today {
    color: #c65c00;
    font-weight: 750;
}

.followup-overdue {
    color: #d73535;
    font-weight: 750;
}

.followup-normal {
    color: #64728a;
}

.action-group {
    display: flex;

    align-items: center;

    gap: 7px;

    white-space: nowrap;
}

.action-group .btn {
    padding: 9px 11px;
}

.assign-form {
    display: flex;

    gap: 7px;

    align-items: center;
}

.assign-select {
    height: 38px;

    border: 1px solid #dbe1eb;

    border-radius: 8px;

    padding: 0 8px;

    max-width: 145px;

    background: #fff;

    font-size: 12px;
}

.empty {
    padding: 75px 25px;

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

    max-width: 620px;

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

.form-control,
textarea {
    width: 100%;

    border: 1px solid #dbe1eb;

    border-radius: 9px;

    background: #fff;

    color: #1a2436;

    outline: none;

    font-size: 14px;

    padding: 11px 13px;
}

.form-control {
    height: 45px;
}

textarea {
    min-height: 110px;

    resize: vertical;
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

    .filter-select {
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

        <div class="brand-icon">
            🏢
        </div>

        <div class="brand-name">
            PropFlow
        </div>

    </div>

    <div class="menu-title">
        WORKSPACE
    </div>

    <a href="dashboard.php" class="nav-item">
        <span class="nav-icon">▦</span>
        Dashboard
    </a>

    <a href="leads.php" class="nav-item">
        <span class="nav-icon">♟</span>
        Leads
    </a>

    <a href="my-leads.php" class="nav-item active">
        <span class="nav-icon">✓</span>
        <?= $isAdmin ? 'Lead Assignment' : 'My Leads' ?>
    </a>

    <a href="pipeline.php" class="nav-item">
        <span class="nav-icon">◇</span>
        Pipeline
    </a>

    <a href="properties.php" class="nav-item">
        <span class="nav-icon">⌂</span>
        Properties
    </a>

    <a href="bookings.php" class="nav-item">
        <span class="nav-icon">✓</span>
        Bookings
    </a>

    <?php if ($isAdmin): ?>

        <a href="team.php" class="nav-item">
            <span class="nav-icon">♙</span>
            Team
        </a>

         <a href="buildings.php" >
            <span class="nav-icon">🏢</span>
            <span>Buildings</span>
        </a>

         <a href="employees.php">
            <span class="nav-icon">♙</span>
            <span>Employees</span>
        </a>

    <?php endif; ?>

    <div class="sidebar-bottom">

        <?php if ($isAdmin): ?>

            <a href="settings.php" class="nav-item">
                <span class="nav-icon">⚙</span>
                Settings
            </a>

        <?php endif; ?>

        <a href="../logout.php" class="nav-item">
            <span class="nav-icon">↪</span>
            Logout
        </a>

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
                        $user['name'] ?? 'U',
                        0,
                        1
                    )
                )
            ) ?>
        </div>

        <div>

            <div class="admin-name">
                <?= e($user['name'] ?? 'User') ?>
            </div>

            <div class="admin-role">
                <?= e($user['role'] ?? '') ?>
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
                <?= $isAdmin
                    ? 'Lead Assignment'
                    : 'My Leads'
                ?>
            </h1>

            <p class="page-subtitle">

                <?= $isAdmin
                    ? 'Assign leads to sales executives and track follow-ups.'
                    : 'Manage your assigned leads and follow-ups.'
                ?>

            </p>

        </div>

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
                <?= $isAdmin ? 'Total Leads' : 'My Leads' ?>
            </div>

            <div class="stat-value">
                <?= number_format($totalLeads) ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Active Leads
            </div>

            <div class="stat-value">
                <?= number_format($activeLeads) ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Today's Follow-ups
            </div>

            <div class="stat-value">
                <?= number_format($followUps) ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                <?= $isAdmin ? 'Unassigned' : 'Role' ?>
            </div>

            <div class="stat-value"
                 style="font-size: <?= $isAdmin ? '28px' : '22px' ?>;">

                <?= $isAdmin
                    ? number_format($unassigned)
                    : 'Sales'
                ?>

            </div>

        </div>

    </section>


    <!-- ===================================================
         LEADS CARD
         =================================================== -->

    <section class="card">

        <div class="filters">

            <div class="search-box">

                <span class="search-icon">
                    ⌕
                </span>

                <input
                    type="text"
                    id="leadSearch"
                    class="filter-control"
                    placeholder="Search name, phone, email..."
                    oninput="filterLeads()"
                >

            </div>

            <select
                id="stageFilter"
                class="filter-control filter-select"
                onchange="filterLeads()"
            >

                <option value="all">
                    All Stages
                </option>

                <option value="New">
                    New
                </option>

                <option value="Contacted">
                    Contacted
                </option>

                <option value="Site Visit">
                    Site Visit
                </option>

                <option value="Interested">
                    Interested
                </option>

                <option value="Negotiation">
                    Negotiation
                </option>

                <option value="Booked">
                    Booked
                </option>

                <option value="Lost">
                    Lost
                </option>

            </select>

        </div>


        <div class="card-head">

            <h2 class="card-title">
                <?= $isAdmin
                    ? 'All Leads'
                    : 'Assigned Leads'
                ?>
            </h2>

            <div class="card-count">
                <?= count($leads) ?> lead(s)
            </div>

        </div>


        <?php if (empty($leads)): ?>

            <div class="empty">

                <div class="empty-icon">
                    👥
                </div>

                <div class="empty-title">
                    <?= $isAdmin
                        ? 'No leads found'
                        : 'No leads assigned yet'
                    ?>
                </div>

                <div>
                    <?= $isAdmin
                        ? 'Create leads from the Leads module.'
                        : 'Your administrator will assign leads to you.'
                    ?>
                </div>

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Lead
                            </th>

                            <th>
                                Source
                            </th>

                            <th>
                                Stage
                            </th>

                            <?php if ($isAdmin): ?>

                                <th>
                                    Assigned To
                                </th>

                            <?php endif; ?>

                            <th>
                                Follow-up
                            </th>

                            <th>
                                Created
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($leads as $lead): ?>

                        <?php

                        $stage = $lead['stage'] ?? 'New';

                        $stageClass = match ($stage) {

                            'Contacted'
                                => 'stage-contacted',

                            'Site Visit'
                                => 'stage-site',

                            'Interested'
                                => 'stage-interested',

                            'Negotiation'
                                => 'stage-negotiation',

                            'Booked'
                                => 'stage-booked',

                            'Lost'
                                => 'stage-lost',

                            default
                                => 'stage-new'
                        };

                        $searchText = strtolower(
                            ($lead['name'] ?? '') . ' ' .
                            ($lead['phone'] ?? '') . ' ' .
                            ($lead['email'] ?? '') . ' ' .
                            ($lead['source'] ?? '') . ' ' .
                            ($lead['assigned_name'] ?? '')
                        );

                        $followClass = 'followup-normal';
                        $followLabel = '—';

                        if (!empty($lead['follow_up_date'])) {

                            $followDate = $lead['follow_up_date'];

                            if ($followDate < date('Y-m-d')) {

                                $followClass =
                                    'followup-overdue';

                                $followLabel =
                                    'Overdue · ' .
                                    date(
                                        'd M Y',
                                        strtotime($followDate)
                                    );

                            } elseif ($followDate === date('Y-m-d')) {

                                $followClass =
                                    'followup-today';

                                $followLabel =
                                    'Today';

                            } else {

                                $followLabel =
                                    date(
                                        'd M Y',
                                        strtotime($followDate)
                                    );
                            }
                        }

                        ?>

                        <tr
                            class="lead-row"
                            data-search="<?= e($searchText) ?>"
                            data-stage="<?= e($stage) ?>"
                        >

                            <td>

                                <div class="primary-text">
                                    <?= e($lead['name']) ?>
                                </div>

                                <div class="secondary-text">
                                    <?= e($lead['email'] ?: 'No email') ?>
                                </div>

                                <div class="phone">
                                    <?= e($lead['phone']) ?>
                                </div>

                            </td>


                            <td>
                                <?= e($lead['source'] ?: 'Direct') ?>
                            </td>


                            <td>

                                <span class="
                                    badge
                                    <?= e($stageClass) ?>
                                ">
                                    <?= e($stage) ?>
                                </span>

                            </td>


                            <?php if ($isAdmin): ?>

                                <td>

                                    <?php if (
                                        !empty($lead['assigned_to']) &&
                                        !empty($lead['assigned_name'])
                                    ): ?>

                                        <div class="primary-text">
                                            <?= e($lead['assigned_name']) ?>
                                        </div>

                                        <div class="secondary-text">
                                            Sales
                                        </div>

                                    <?php else: ?>

                                        <span class="
                                            badge
                                            badge-unassigned
                                        ">
                                            Unassigned
                                        </span>

                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        class="assign-form"
                                        style="margin-top:8px;"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="assign_lead"
                                        >

                                        <input
                                            type="hidden"
                                            name="lead_id"
                                            value="<?= e($lead['id']) ?>"
                                        >

                                        <select
                                            name="assigned_to"
                                            class="assign-select"
                                            required
                                        >

                                            <option value="">
                                                Assign...
                                            </option>

                                            <?php foreach (
                                                $salesEmployees
                                                as $sales
                                            ): ?>

                                                <option
                                                    value="<?= e($sales['id']) ?>"
                                                    <?= (int)$lead['assigned_to'] ===
                                                        (int)$sales['id']
                                                        ? 'selected'
                                                        : ''
                                                    ?>
                                                >
                                                    <?= e($sales['name']) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                        >
                                            Save
                                        </button>

                                    </form>

                                </td>

                            <?php endif; ?>


                            <td>

                                <div class="<?= e($followClass) ?>">
                                    <?= e($followLabel) ?>
                                </div>

                            </td>


                            <td>

                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $lead['created_at']
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>

                                <div class="action-group">

                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        onclick='openEditLead(
                                            <?= json_encode((int)$lead["id"]) ?>,
                                            <?= json_encode($lead["name"]) ?>,
                                            <?= json_encode($lead["phone"]) ?>,
                                            <?= json_encode($lead["email"]) ?>,
                                            <?= json_encode($lead["stage"]) ?>,
                                            <?= json_encode($lead["follow_up_date"]) ?>,
                                            <?= json_encode($lead["notes"]) ?>
                                        )'
                                    >
                                        Update
                                    </button>

                                    <?php if ($stage === 'Booked'): ?>

                                        <a
                                            href="bookings.php"
                                            class="btn btn-success"
                                        >
                                            Booking
                                        </a>

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
     UPDATE LEAD MODAL
     ======================================================= -->

<div
    class="modal"
    id="leadModal"
>

    <div class="modal-box">

        <div class="modal-head">

            <h2
                class="modal-title"
                id="modalTitle"
            >
                Update Lead
            </h2>

            <button
                type="button"
                class="close"
                onclick="closeLeadModal()"
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
                    value="update_lead"
                >

                <input
                    type="hidden"
                    name="lead_id"
                    id="modalLeadId"
                >


                <div
                    id="leadSummary"
                    style="
                        background:#f7f9fc;
                        border:1px solid #e6ebf2;
                        border-radius:11px;
                        padding:14px;
                        margin-bottom:20px;
                    "
                >

                    <div
                        id="modalLeadName"
                        class="primary-text"
                    >
                    </div>

                    <div
                        id="modalLeadContact"
                        class="secondary-text"
                    >
                    </div>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Lead Stage
                    </label>

                    <select
                        name="stage"
                        id="modalStage"
                        class="form-control"
                        required
                    >

                        <option value="New">
                            New
                        </option>

                        <option value="Contacted">
                            Contacted
                        </option>

                        <option value="Site Visit">
                            Site Visit
                        </option>

                        <option value="Interested">
                            Interested
                        </option>

                        <option value="Negotiation">
                            Negotiation
                        </option>

                        <option value="Booked">
                            Booked
                        </option>

                        <option value="Lost">
                            Lost
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Follow-up Date
                    </label>

                    <input
                        type="date"
                        name="follow_up_date"
                        id="modalFollowUp"
                        class="form-control"
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        id="modalNotes"
                        placeholder="Add follow-up notes..."
                    ></textarea>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeLeadModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================================================
   EDIT LEAD MODAL
   ========================================================= */

function openEditLead(
    id,
    name,
    phone,
    email,
    stage,
    followUp,
    notes
) {

    document.getElementById('modalLeadId').value =
        id;

    document.getElementById('modalLeadName').textContent =
        name || 'Unknown Lead';

    document.getElementById('modalLeadContact').textContent =
        (phone || 'No phone') +
        (email ? ' · ' + email : '');

    document.getElementById('modalStage').value =
        stage || 'New';

    document.getElementById('modalFollowUp').value =
        followUp || '';

    document.getElementById('modalNotes').value =
        notes || '';

    document
        .getElementById('leadModal')
        .classList
        .add('show');
}


function closeLeadModal() {

    document
        .getElementById('leadModal')
        .classList
        .remove('show');
}


/* =========================================================
   SEARCH / FILTER
   ========================================================= */

function filterLeads() {

    const search =
        document
            .getElementById('leadSearch')
            .value
            .trim()
            .toLowerCase();

    const stage =
        document
            .getElementById('stageFilter')
            .value;

    document
        .querySelectorAll('.lead-row')
        .forEach(function(row) {

            const rowSearch =
                row.getAttribute('data-search') || '';

            const rowStage =
                row.getAttribute('data-stage') || '';

            const matchesSearch =
                search === '' ||
                rowSearch.includes(search);

            const matchesStage =
                stage === 'all' ||
                rowStage === stage;

            row.style.display =
                matchesSearch && matchesStage
                    ? ''
                    : 'none';

        });
}


/* =========================================================
   MODAL CLICK / ESCAPE
   ========================================================= */

document
    .getElementById('leadModal')
    .addEventListener(
        'click',
        function(event) {

            if (event.target === this) {
                closeLeadModal();
            }

        }
    );

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {
            closeLeadModal();
        }

    }
);

</script>

</body>
</html>
