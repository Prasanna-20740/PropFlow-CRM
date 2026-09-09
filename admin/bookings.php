<?php
/* =========================================================
   PROPFlow CRM - Booking Management
   ========================================================= */

require_once "../includes/auth.php";
requireRole("admin");

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

function redirectWithMessage($type, $message)
{
    header(
        "Location: bookings.php?" .
        http_build_query([
            'type' => $type,
            'message' => $message
        ])
    );
    exit;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   POST ACTIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* -----------------------------------------------------
       CSRF CHECK
       ----------------------------------------------------- */

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        empty($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        redirectWithMessage('error', 'Invalid security token.');
    }

    $action = $_POST['action'] ?? '';

    /* =====================================================
       CREATE BOOKING
       ===================================================== */

    if ($action === 'create_booking') {

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $bookingDate = trim($_POST['booking_date'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($leadId <= 0) {
            redirectWithMessage('error', 'Please select a lead.');
        }

        if ($unitId <= 0) {
            redirectWithMessage('error', 'Please select a unit.');
        }

        if ($bookingDate === '') {
            redirectWithMessage('error', 'Please select a booking date.');
        }

        if ($amount < 0) {
            redirectWithMessage('error', 'Booking amount cannot be negative.');
        }

        try {

            $pdo->beginTransaction();

            /* -------------------------------------------------
               Verify Lead
               ------------------------------------------------- */

            $leadStmt = $pdo->prepare(" SELECT id
                FROM leads
                WHERE id = ?
                LIMIT 1
            ");

            $leadStmt->execute([$leadId]);

            if (!$leadStmt->fetch()) {
                throw new Exception('Selected lead does not exist.');
            }

            /* -------------------------------------------------
               LOCK UNIT ROW
               This prevents two admins from booking the same
               unit at the same time.
               ------------------------------------------------- */

            $unitStmt = $pdo->prepare(" SELECT
                    u.id,
                    u.unit_number,
                    u.status,
                    u.price,
                    b.name AS building_name,
                    p.name AS project_name
                FROM units u
                INNER JOIN buildings b
                    ON b.id = u.building_id
                INNER JOIN projects p
                    ON p.id = b.project_id
                WHERE u.id = ?
                FOR UPDATE
            ");

            $unitStmt->execute([$unitId]);

            $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                throw new Exception('Selected unit was not found.');
            }

            /* -------------------------------------------------
               Unit status check
               ------------------------------------------------- */

            if (strtolower($unit['status']) !== 'available') {

                $currentStatus = ucfirst($unit['status']);

                throw new Exception(
                    "Unit {$unit['unit_number']} is currently {$currentStatus}."
                );
            }

            /* -------------------------------------------------
               Extra confirmed-booking check
               ------------------------------------------------- */

            $existingStmt = $pdo->prepare(" SELECT id
                FROM bookings
                WHERE unit_id = ?
                  AND status = 'confirmed'
                LIMIT 1
                FOR UPDATE
            ");

            $existingStmt->execute([$unitId]);

            if ($existingStmt->fetch()) {
                throw new Exception(
                    "Unit {$unit['unit_number']} has already been booked."
                );
            }

            /* -------------------------------------------------
               Create booking
               ------------------------------------------------- */

           $insertStmt = $pdo->prepare(" INSERT INTO bookings
    (
        lead_id,
        unit_id,
        booking_date,
        amount,
        status,
        booked_by
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        'confirmed',
        ?
    )
");

$insertStmt->execute([
    $leadId,
    $unitId,
    $bookingDate,
    $amount,
    $user['id']
]);

            /* -------------------------------------------------
               Update unit status
               ------------------------------------------------- */

            $updateUnit = $pdo->prepare(" UPDATE units
                SET status = 'booked'
                WHERE id = ?
            ");

            $updateUnit->execute([$unitId]);

            $pdo->commit();

            redirectWithMessage(
                'success',
                "Unit {$unit['unit_number']} booked successfully."
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            redirectWithMessage(
                'error',
                $e->getMessage()
            );
        }
    }

    /* =====================================================
       CANCEL BOOKING
       ===================================================== */

    if ($action === 'cancel_booking') {

        $bookingId = (int)($_POST['booking_id'] ?? 0);

        if ($bookingId <= 0) {
            redirectWithMessage('error', 'Invalid booking.');
        }

        try {

            $pdo->beginTransaction();

            /* -------------------------------------------------
               Lock booking
               ------------------------------------------------- */

            $bookingStmt = $pdo->prepare(" SELECT
                    id,
                    unit_id,
                    status
                FROM bookings
                WHERE id = ?
                FOR UPDATE
            ");

            $bookingStmt->execute([$bookingId]);

            $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                throw new Exception('Booking not found.');
            }

            if (strtolower($booking['status']) === 'cancelled') {
                throw new Exception('This booking is already cancelled.');
            }

            /* -------------------------------------------------
               Lock unit
               ------------------------------------------------- */

            $unitStmt = $pdo->prepare(" SELECT id, unit_number
                FROM units
                WHERE id = ?
                FOR UPDATE
            ");

            $unitStmt->execute([
                $booking['unit_id']
            ]);

            $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                throw new Exception('Related unit was not found.');
            }

            /* -------------------------------------------------
               Cancel booking
               ------------------------------------------------- */

            $cancelStmt = $pdo->prepare(" UPDATE bookings
                SET status = 'cancelled'
                WHERE id = ?
            ");

            $cancelStmt->execute([
                $bookingId
            ]);

            /* -------------------------------------------------
               Make unit available again
               ------------------------------------------------- */

            $unitUpdate = $pdo->prepare(" UPDATE units
                SET status = 'available'
                WHERE id = ?
            ");

            $unitUpdate->execute([
                $booking['unit_id']
            ]);

            $pdo->commit();

            redirectWithMessage(
                'success',
                "Booking cancelled. Unit {$unit['unit_number']} is available again."
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            redirectWithMessage(
                'error',
                $e->getMessage()
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

$totalBookings = (int)$pdo->query(" SELECT COUNT(*)
    FROM bookings
")->fetchColumn();

$confirmedBookings = (int)$pdo->query(" SELECT COUNT(*)
    FROM bookings
    WHERE status = 'confirmed'
")->fetchColumn();

$cancelledBookings = (int)$pdo->query(" SELECT COUNT(*)
    FROM bookings
    WHERE status = 'cancelled'
")->fetchColumn();

$totalBookingAmount = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0)
    FROM bookings
    WHERE status = 'confirmed'
")->fetchColumn();

/* =========================================================
   LEADS
   Only ID is required, so this works regardless of the
   other lead columns in the existing CRM schema.
   ========================================================= */

$leadsStmt = $pdo->query(" SELECT id
    FROM leads
    ORDER BY id DESC
");

$leads = $leadsStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   AVAILABLE UNITS
   ========================================================= */

$unitsStmt = $pdo->query(" SELECT
        u.id,
        u.unit_number,
        u.type,
        u.floor,
        u.price,
        b.name AS building_name,
        p.name AS project_name
    FROM units u
    INNER JOIN buildings b
        ON b.id = u.building_id
    INNER JOIN projects p
        ON p.id = b.project_id
    WHERE u.status = 'available'
    ORDER BY
        p.name ASC,
        b.name ASC,
        u.unit_number ASC
");

$availableUnits = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   BOOKINGS LIST
   ========================================================= */

$bookingsStmt = $pdo->query(" SELECT
        bk.id,
        bk.lead_id,
        bk.unit_id,
        bk.booking_date,
        bk.amount,
        bk.status,
        bk.created_at,

        /* Lead details */
        l.name AS lead_name,
        l.phone AS lead_phone,
        l.email AS lead_email,
        l.source AS lead_source,
        l.stage AS lead_stage,

        /* Unit details */
        u.unit_number,
        u.type,
        u.floor,
        u.price AS unit_price,

        /* Building */
        b.name AS building_name,

        /* Project */
        p.name AS project_name,
        p.location AS project_location

    FROM bookings bk

    INNER JOIN leads l
        ON l.id = bk.lead_id

    INNER JOIN units u
        ON u.id = bk.unit_id

    INNER JOIN buildings b
        ON b.id = u.building_id

    INNER JOIN projects p
        ON p.id = b.project_id

    ORDER BY bk.id DESC
");

$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Bookings | PropFlow CRM</title>

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

:root {
    --dark: #0b1220;
}

a {
    text-decoration: none;
    color: inherit;
}


/* =================================================
   SIDEBAR (matches Dashboard module sidebar)
================================================= */
/* =================================================
   SIDEBAR (same as Dashboard)
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
    box-shadow: 0 6px 16px rgba(37,99,235,.22);
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

    background: #ffffff;
    border-bottom: 1px solid #e8ecf2;

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

    color: #71809a;

    font-size: 15px;
}

.btn {
    border: 0;

    padding: 13px 20px;

    border-radius: 11px;

    font-size: 14px;
    font-weight: 700;

    cursor: pointer;
}

.btn-primary {
    background: #2864e8;
    color: #fff;
}

.btn-primary:hover {
    background: #1f55cb;
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
    background: #fff;

    border: 1px solid #e1e7f0;

    border-radius: 15px;

    padding: 23px 21px;
}

.stat-label {
    color: #71809a;
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

.amount {
    font-weight: 750;
}

.badge {
    display: inline-flex;

    align-items: center;

    padding: 6px 10px;

    border-radius: 999px;

    font-size: 12px;

    font-weight: 700;
}

.badge-confirmed {
    background: #eafaf1;
    color: #118449;
}

.badge-cancelled {
    background: #fff0f0;
    color: #d03535;
}

.action-form {
    display: inline;
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

    background: rgba(13, 22, 40, .55);

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

    background: #fff;

    border-radius: 18px;

    box-shadow: 0 25px 70px rgba(0,0,0,.18);

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

textarea.form-control {
    height: 95px;

    padding-top: 12px;

    resize: vertical;
}

.form-control:focus {
    border-color: #2864e8;

    box-shadow: 0 0 0 3px rgba(40,100,232,.09);
}

.modal-footer {
    display: flex;

    justify-content: flex-end;

    gap: 10px;

    padding: 18px 25px;

    border-top: 1px solid #e8ecf2;
}

.btn-secondary {
    background: #f1f4f8;
    color: #344158;
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
    font-size: 20px;
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

.status-filter {
    width: 170px;
}

.filter-control:focus {
    border-color: #2864e8;
    box-shadow: 0 0 0 3px rgba(40,100,232,.09);
}

.action-form {
    margin-left: 6px;
}

.btn-secondary:hover {
    background: #e7ebf1;
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

        <a href="bookings.php" class="active">
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
                Booking Management
            </h1>

            <p class="page-subtitle">
                Manage property bookings and unit reservations.
            </p>

        </div>

        <button
            class="btn btn-primary"
            onclick="openModal()"
        >
            + New Booking
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
                Total Bookings
            </div>

            <div class="stat-value">
                <?= number_format($totalBookings) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Confirmed
            </div>

            <div class="stat-value">
                <?= number_format($confirmedBookings) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Cancelled
            </div>

            <div class="stat-value">
                <?= number_format($cancelledBookings) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Confirmed Value
            </div>

            <div class="stat-value">
                ₹<?= number_format($totalBookingAmount, 0) ?>
            </div>

        </div>

    </section>


    <!-- ===================================================
         BOOKINGS
         =================================================== -->

    <section class="card">

        <div class="filters">
            <div class="search-box">
                <span class="search-icon">⌕</span>
                <input
                    type="text"
                    id="bookingSearch"
                    class="filter-control"
                    placeholder="Search customer, phone, unit, project..."
                    oninput="filterBookings()"
                >
            </div>

            <select
                id="statusFilter"
                class="filter-control status-filter"
                onchange="filterBookings()"
            >
                <option value="all">All Status</option>
                <option value="confirmed">Confirmed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div class="card-head">

            <h2 class="card-title">
                Bookings
            </h2>

            <div class="card-count">
                <?= count($bookings) ?> booking(s)
            </div>

        </div>


        <?php if (empty($bookings)): ?>

            <div class="empty">

                <div class="empty-icon">
                    🏠
                </div>

                <div class="empty-title">
                    No bookings yet
                </div>

                <div>
                    Create a booking for an available unit.
                </div>

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>Booking</th>

                            <th>Property</th>

                            <th>Lead</th>

                            <th>Date</th>

                            <th>Amount</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($bookings as $booking): ?>

                        <tr
                            class="booking-row"
                            data-search="<?= e(strtolower(
                                ($booking['lead_name'] ?? '') . ' ' .
                                ($booking['lead_phone'] ?? '') . ' ' .
                                ($booking['unit_number'] ?? '') . ' ' .
                                ($booking['building_name'] ?? '') . ' ' .
                                ($booking['project_name'] ?? '')
                            )) ?>"
                            data-status="<?= e(strtolower($booking['status'] ?? '')) ?>"
                        >

                            <td>

                                <div class="primary-text">
                                    #BK-<?= str_pad(
                                        $booking['id'],
                                        4,
                                        '0',
                                        STR_PAD_LEFT
                                    ) ?>
                                </div>

                                <div class="secondary-text">
                                    <?= e($booking['type']) ?>
                                    · Floor <?= e($booking['floor']) ?>
                                </div>

                            </td>


                            <td>

                                <div class="primary-text">
                                    <?= e($booking['unit_number']) ?>
                                </div>

                                <div class="secondary-text">

                                    <?= e($booking['building_name']) ?>
                                    ·
                                    <?= e($booking['project_name']) ?>

                                </div>

                            </td>


        <td>

    <div class="primary-text">
        <?= e($booking['lead_name']) ?>
    </div>

    <div class="secondary-text">
        <?= e($booking['lead_phone']) ?>
    </div>

</td>


                            <td>

                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $booking['booking_date']
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>

                                <div class="amount">

                                    ₹<?= number_format(
                                        (float)$booking['amount'],
                                        0
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick='viewBooking(
                                        <?= json_encode((int)$booking["id"]) ?>,
                                        <?= json_encode($booking["lead_name"] ?? "Unknown Lead") ?>,
                                        <?= json_encode($booking["lead_phone"] ?? "") ?>,
                                        <?= json_encode($booking["lead_email"] ?? "") ?>,
                                        <?= json_encode($booking["project_name"] ?? "") ?>,
                                        <?= json_encode($booking["project_location"] ?? "") ?>,
                                        <?= json_encode($booking["building_name"] ?? "") ?>,
                                        <?= json_encode($booking["unit_number"] ?? "") ?>,
                                        <?= json_encode($booking["type"] ?? "") ?>,
                                        <?= json_encode((float)$booking["amount"]) ?>,
                                        <?= json_encode($booking["booking_date"] ?? "") ?>,
                                        <?= json_encode($booking["status"] ?? "") ?>
                                    )'
                                >
                                    View
                                </button>

                                <?php if (strtolower($booking['status']) === 'confirmed'): ?>

                                    <span class="
                                        badge
                                        badge-confirmed
                                    ">
                                        Confirmed
                                    </span>

                                <?php else: ?>

                                    <span class="
                                        badge
                                        badge-cancelled
                                    ">
                                        Cancelled
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (strtolower($booking['status']) === 'confirmed'): ?>

                                    <form
                                        method="POST"
                                        class="action-form"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to cancel this booking?'
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
                                            value="cancel_booking"
                                        >

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?= e($booking['id']) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >
                                            Cancel
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="secondary-text">
                                        —
                                    </span>

                                <?php endif; ?>

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
     CREATE BOOKING MODAL
     ======================================================= -->

<div
    class="modal"
    id="bookingModal"
>

    <div class="modal-box">

        <div class="modal-head">

            <h2 class="modal-title">
                Create New Booking
            </h2>

            <button
                type="button"
                class="close"
                onclick="closeModal()"
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
                    value="create_booking"
                >


                <!-- LEAD -->

                <div class="form-group">

                    <label class="form-label">
                        Lead / Customer
                    </label>

                    <select
                        name="lead_id"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select Lead
                        </option>

                    <?php
$leadDetailsStmt = $pdo->query(" SELECT
        id,
        name,
        phone,
        email
    FROM leads
    ORDER BY name ASC
");

$leadDetails = $leadDetailsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php foreach ($leadDetails as $lead): ?>

    <option value="<?= e($lead['id']) ?>">
        <?= e($lead['name']) ?>
        — <?= e($lead['phone']) ?>
    </option>

<?php endforeach; ?>

                    </select>

                </div>


                <!-- UNIT -->

                <div class="form-group">

                    <label class="form-label">
                        Available Unit
                    </label>

                    <select
                        name="unit_id"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select Available Unit
                        </option>

                        <?php foreach ($availableUnits as $unit): ?>

                            <option
                                value="<?= e($unit['id']) ?>"
                            >

                                <?= e($unit['unit_number']) ?>

                                —
                                <?= e($unit['project_name']) ?>

                                /
                                <?= e($unit['building_name']) ?>

                                —
                                <?= e($unit['type']) ?>

                                —
                                ₹<?= number_format(
                                    (float)$unit['price'],
                                    0
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BOOKING DATE -->

                <div class="form-group">

                    <label class="form-label">
                        Booking Date
                    </label>

                    <input
                        type="date"
                        name="booking_date"
                        class="form-control"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <!-- AMOUNT -->

                <div class="form-group">

                    <label class="form-label">
                        Booking Amount
                    </label>

                    <input
                        type="number"
                        name="amount"
                        class="form-control"
                        min="0"
                        step="0.01"
                        placeholder="Enter booking amount"
                        required
                    >

                </div>
</div>


            <div class="modal-footer">
<button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Confirm Booking
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function openModal() {

    document
        .getElementById('bookingModal')
        .classList
        .add('show');

}

function closeModal() {

    document
        .getElementById('bookingModal')
        .classList
        .remove('show');

}


/* Close when clicking outside */

document
    .getElementById('bookingModal')
    .addEventListener('click', function(event) {

        if (event.target === this) {
            closeModal();
        }

    });


/* Escape key */

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {
        closeModal();
    }

});

function viewBooking(
    id,
    name,
    phone,
    email,
    project,
    location,
    building,
    unit,
    type,
    amount,
    date,
    status
) {
    document.getElementById('detailName').textContent =
        name || 'Unknown Lead';

    document.getElementById('detailPhone').textContent =
        phone || 'Not provided';

    document.getElementById('detailEmail').textContent =
        email || 'Not provided';

    document.getElementById('detailProject').textContent =
        project || '—';

    document.getElementById('detailLocation').textContent =
        location || '—';

    document.getElementById('detailBuilding').textContent =
        building || '—';

    document.getElementById('detailUnit').textContent =
        unit || '—';

    document.getElementById('detailType').textContent =
        type || '—';

    document.getElementById('detailAmount').textContent =
        '₹' + Number(amount || 0).toLocaleString('en-IN');

    document.getElementById('detailDate').textContent =
        formatBookingDate(date);

    const statusBox =
        document.getElementById('detailStatus');

    statusBox.innerHTML =
        String(status).toLowerCase() === 'confirmed'
            ? '<span class="badge badge-confirmed">Confirmed</span>'
            : '<span class="badge badge-cancelled">Cancelled</span>';

    document.getElementById('detailsModal').classList.add('show');
}

function formatBookingDate(value) {
    if (!value) return '—';

    const date = new Date(value + 'T00:00:00');

    if (Number.isNaN(date.getTime())) return value;

    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}


function closeDetails() {

    document
        .getElementById('detailsModal')
        .classList
        .remove('show');

}


function filterBookings() {
    const search = document
        .getElementById('bookingSearch')
        .value
        .trim()
        .toLowerCase();

    const status = document
        .getElementById('statusFilter')
        .value
        .toLowerCase();

    document.querySelectorAll('.booking-row').forEach(function(row) {
        const rowSearch = row.getAttribute('data-search') || '';
        const rowStatus = row.getAttribute('data-status') || '';

        const matchesSearch =
            search === '' || rowSearch.includes(search);

        const matchesStatus =
            status === 'all' || rowStatus === status;

        row.style.display =
            matchesSearch && matchesStatus ? '' : 'none';
    });
}

</script>
<div class="modal" id="detailsModal">

    <div class="modal-box">

        <div class="modal-head">

            <h2 class="modal-title">
                Booking Details
            </h2>

            <button
                type="button"
                class="close"
                onclick="closeDetails()"
            >
                ×
            </button>

        </div>

        <div class="modal-body">

            <div style="
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:18px;
            ">

                <div>
                    <div class="form-label">Customer</div>
                    <div id="detailName" class="primary-text"></div>
                </div>

                <div>
                    <div class="form-label">Phone</div>
                    <div id="detailPhone"></div>
                </div>

                <div>
                    <div class="form-label">Email</div>
                    <div id="detailEmail"></div>
                </div>

                <div>
                    <div class="form-label">Booking Date</div>
                    <div id="detailDate"></div>
                </div>

                <div>
                    <div class="form-label">Project</div>
                    <div id="detailProject" class="primary-text"></div>
                </div>

                <div>
                    <div class="form-label">Location</div>
                    <div id="detailLocation"></div>
                </div>

                <div>
                    <div class="form-label">Building</div>
                    <div id="detailBuilding"></div>
                </div>

                <div>
                    <div class="form-label">Unit</div>
                    <div id="detailUnit" class="primary-text"></div>
                </div>

                <div>
                    <div class="form-label">Type</div>
                    <div id="detailType"></div>
                </div>

                <div>
                    <div class="form-label">Booking Amount</div>
                    <div id="detailAmount" class="primary-text"></div>
                </div>

                <div style="grid-column:1/-1;">

                    <div class="form-label">
                        Status
                    </div>

                    <div id="detailStatus"></div>

                </div>

            </div>

        </div>

        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-secondary"
                onclick="closeDetails()"
            >
                Close
            </button>

        </div>

    </div>

</div>

</body>
</html>