<?php
/* =========================================================
   PROPFlow CRM - Sales Bookings
   Lead Details + Booking + Property Details
   ========================================================= */

require_once "../includes/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = currentUser();

if (!$user || strtolower($user['role'] ?? '') !== 'sales') {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$currentUserId = (int)($user['id'] ?? 0);

/* =========================================================
   HELPERS
   ========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatAmount($amount)
{
    return '₹ ' . number_format(
        (float)$amount,
        2,
        '.',
        ','
    );
}

function formatDateValue($date)
{
    if (empty($date)) {
        return '-';
    }

    $time = strtotime($date);

    if (!$time) {
        return '-';
    }

    return date('d M Y', $time);
}

function getStatusClass($status)
{
    $status = strtolower(trim((string)$status));

    if ($status === 'confirmed') {
        return 'confirmed';
    }

    if ($status === 'cancelled' || $status === 'canceled') {
        return 'cancelled';
    }

    return 'pending';
}

function getInitial($name)
{
    $name = trim((string)$name);

    if ($name === '') {
        return 'U';
    }

    return strtoupper(substr($name, 0, 1));
}


/* =========================================================
   VARIABLES
   ========================================================= */

$bookings = array();

$totalBookings = 0;
$confirmedBookings = 0;
$cancelledBookings = 0;
$totalBookingValue = 0;

$databaseError = '';
$debugInfo = '';


/* =========================================================
   FETCH BOOKINGS FOR USER'S ASSIGNED LEADS
   Single query: bookings → leads → units → buildings → projects
   Filter: leads.assigned_to = current Sales user ID
   ========================================================= */

try {

    $sql = "
        SELECT

            /* ========================= */
            /* BOOKING DETAILS           */
            /* ========================= */

            bk.id           AS booking_id,
            bk.lead_id,
            bk.unit_id,
            bk.booking_date,
            bk.amount,
            bk.status       AS booking_status,
            bk.created_at   AS booking_created_at,

            /* ========================= */
            /* LEAD DETAILS              */
            /* ========================= */

            l.id            AS lead_id,
            l.name          AS lead_name,
            l.phone         AS lead_phone,
            l.email         AS lead_email,
            l.source        AS lead_source,
            l.stage         AS lead_stage,
            l.assigned_to   AS lead_assigned_to,

            /* ========================= */
            /* UNIT DETAILS              */
            /* ========================= */

            u.unit_number,
            u.type          AS unit_type,
            u.floor         AS unit_floor,
            u.price         AS unit_price,
            u.status        AS unit_status,

            /* ========================= */
            /* BUILDING DETAILS          */
            /* ========================= */

            b.id            AS building_id,
            b.name          AS building_name,
            b.floors        AS building_floors,

            /* ========================= */
            /* PROJECT DETAILS           */
            /* ========================= */

            p.id            AS project_id,
            p.name          AS project_name,
            p.location      AS project_location,
            p.status        AS project_status

        FROM bookings AS bk

        INNER JOIN leads AS l
            ON l.id = bk.lead_id

        LEFT JOIN units AS u
            ON u.id = bk.unit_id

        LEFT JOIN buildings AS b
            ON b.id = u.building_id

        LEFT JOIN projects AS p
            ON p.id = b.project_id

        WHERE l.assigned_to = :userId

        ORDER BY
            bk.booking_date DESC,
            bk.id DESC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute(array('userId' => $currentUserId));

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* =====================================================
       CALCULATE STATS
       ===================================================== */

    foreach ($bookings as $booking) {

        $status = strtolower(
            trim(
                (string)($booking['booking_status'] ?? '')
            )
        );

        if ($status === 'confirmed') {

            $confirmedBookings++;

            $totalBookingValue += (float)(
                $booking['amount'] ?? 0
            );

        } elseif (
            $status === 'cancelled' ||
            $status === 'canceled'
        ) {

            $cancelledBookings++;
        }
    }

    $totalBookings = count($bookings);


    /* =====================================================
       DEBUG: If 0 bookings, collect diagnostic info
       ===================================================== */

    if ($totalBookings === 0) {

        /* Check if ANY bookings exist at all */
        $chk1 = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

        /* Check if current user has any leads */
        $chk2Stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE assigned_to = ?");
        $chk2Stmt->execute(array($currentUserId));
        $chk2 = $chk2Stmt->fetchColumn();

        /* Check if any of those leads have bookings */
        $chk3Stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings bk
            INNER JOIN leads l ON l.id = bk.lead_id
            WHERE l.assigned_to = ?
        ");
        $chk3Stmt->execute(array($currentUserId));
        $chk3 = $chk3Stmt->fetchColumn();

        $debugInfo = "Debug: Logged-in User ID = " . $currentUserId
            . " | Total bookings in DB = " . $chk1
            . " | Leads assigned to you = " . $chk2
            . " | Bookings for your leads = " . $chk3;
    }

} catch (PDOException $e) {

    $databaseError =
        "Unable to load booking information. Error: "
        . $e->getMessage();

    $bookings = array();

    $totalBookings = 0;
    $confirmedBookings = 0;
    $cancelledBookings = 0;
    $totalBookingValue = 0;
}

$profileName = trim(
    $user['name'] ?? 'Sales'
);

$profileInitial = getInitial($profileName);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

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

a {
    text-decoration: none;
    color: inherit;
}

/* =========================================================
   SIDEBAR
   ========================================================= */

/* Sidebar CSS is loaded from sidebar.php include */

/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {
    position: fixed;

    left: 272px;
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

.profile {
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

.profile-name {
    font-size: 14px;
    font-weight: 700;
}

.profile-role {
    margin-top: 2px;

    color: #75819a;

    font-size: 13px;
}

/* =========================================================
   MAIN
   ========================================================= */

.main {
    margin-left: 272px;

    padding: 122px 38px 50px;

    min-height: 100vh;
}

/* =========================================================
   PAGE HEAD
   ========================================================= */

.page-head {
    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    margin-bottom: 28px;
}

.page-title {
    margin: 0;

    font-size: 31px;

    letter-spacing: -.02em;
}

.page-subtitle {
    margin: 7px 0 0;

    color: #71809a;

    font-size: 15px;
}

/* =========================================================
   ERRORS / DEBUG
   ========================================================= */

.error-box {
    margin-bottom: 20px;

    padding: 13px 16px;

    border-radius: 10px;

    background: #fff1f2;

    color: #be123c;

    border: 1px solid #fecdd3;

    font-size: 12px;
    font-weight: 600;
}

.debug-box {
    margin-bottom: 20px;

    padding: 13px 16px;

    border-radius: 10px;

    background: #fffbeb;

    color: #92400e;

    border: 1px solid #fde68a;

    font-size: 12px;
    font-weight: 600;
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
    margin-top: 11px;

    font-size: 28px;

    font-weight: 800;
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

    align-items: center;

    justify-content: space-between;

    padding: 20px 22px;

    border-bottom: 1px solid #e8ecf2;
}

.card-title {
    margin: 0;

    font-size: 17px;
}

.booking-count {
    background: #eff6ff;

    color: #2864e8;

    padding: 6px 12px;

    border-radius: 7px;

    font-size: 11px;
    font-weight: 700;
}

/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 1100px;
}

th {
    text-align: left;

    padding: 13px 20px;

    background: #fbfcfe;

    color: #8090aa;

    font-size: 10px;

    letter-spacing: .08em;

    text-transform: uppercase;

    white-space: nowrap;
}

td {
    padding: 15px 20px;

    border-top: 1px solid #edf0f5;

    font-size: 13px;

    vertical-align: top;
}

tr:hover td {
    background: #fafbfc;
}

/* =========================================================
   LEAD CELL
   ========================================================= */

.customer {
    display: flex;
    align-items: flex-start;

    gap: 10px;

    min-width: 200px;
}

.customer-avatar {
    width: 36px;
    height: 36px;

    flex-shrink: 0;

    border-radius: 10px;

    background: #eaf1ff;

    color: #2563eb;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 13px;
    font-weight: 750;
}

.customer-name {
    font-weight: 700;
    color: #172033;
}

.customer-info {
    color: #8a94a6;

    font-size: 11px;

    margin-top: 3px;
}

.customer-email {
    color: #98a2b3;

    font-size: 11px;

    margin-top: 2px;
}

.lead-meta {
    margin-top: 5px;

    font-size: 11px;

    color: #667085;
}

.lead-stage {
    display: inline-flex;

    padding: 4px 8px;

    border-radius: 999px;

    font-size: 10px;
    font-weight: 700;

    margin-top: 4px;
}

.lead-stage.booked {
    background: #e7f8ee;
    color: #08753d;
}

.lead-stage.new {
    background: #eef3ff;
    color: #2864e8;
}

.lead-stage.contacted {
    background: #f2efff;
    color: #6844c8;
}

.lead-stage.interested {
    background: #eafaf1;
    color: #118449;
}

.lead-stage.negotiation {
    background: #fff0e8;
    color: #c85a16;
}

.lead-stage.lost {
    background: #fff0f0;
    color: #d73535;
}

.lead-stage.site {
    background: #fff7e8;
    color: #ad6a00;
}

/* =========================================================
   BOOKING CELL
   ========================================================= */

.booking-detail {
    font-size: 12px;

    line-height: 1.7;
}

.booking-detail strong {
    color: #475467;
}

/* =========================================================
   STATUS BADGE
   ========================================================= */

.status-badge {
    display: inline-flex;

    padding: 4px 9px;

    border-radius: 999px;

    font-size: 10px;
    font-weight: 700;

    margin-top: 4px;
}

.status-badge.confirmed {
    background: #e0f2fe;
    color: #0284c7;
}

.status-badge.cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

.status-badge.pending {
    background: #fef3c7;
    color: #ca8a04;
}

/* =========================================================
   PROJECT / PROPERTY CELL
   ========================================================= */

.project-name {
    font-weight: 650;
    color: #344054;
}

.project-location {
    color: #8a94a6;

    font-size: 11px;

    margin-top: 2px;
}

.property-info {
    margin-top: 5px;

    font-size: 12px;
    line-height: 1.7;

    color: #475467;
}

/* =========================================================
   BTN
   ========================================================= */

.btn {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: 0;

    border-radius: 9px;

    padding: 9px 16px;

    font-size: 12px;

    font-weight: 750;

    background: #2864e8;
    color: #fff;

    cursor: pointer;

    transition: background .2s;
}

.btn:hover {
    background: #1d4ed8;
}

/* =========================================================
   EMPTY STATE
   ========================================================= */

.empty {
    padding: 50px 20px;

    text-align: center;

    color: #7b879b;
}

.empty-icon {
    font-size: 32px;

    margin-bottom: 10px;
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1024px) {

    .topbar {
        left: 0;
        padding-left: 60px;
    }

    .main {
        margin-left: 0;
    }

    .stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {

    .topbar {
        position: static;

        height: 70px;

        padding: 0 18px 0 60px;
    }

    .main {
        margin-left: 0;

        padding: 28px 18px 40px;
    }

    .page-head {
        flex-direction: column;

        gap: 15px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    table {
        min-width: 900px;
    }
}

</style>

</head>

<body>


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<?php $activePage = 'bookings'; include 'sidebar.php'; ?>


<!-- =====================================================
     TOPBAR
     ===================================================== -->

<header class="topbar">

    <div class="profile">

        <div class="avatar">
            <?= e($profileInitial) ?>
        </div>

        <div>

            <div class="profile-name">
                <?= e($profileName) ?>
            </div>

            <div class="profile-role">
                Sales Executive
            </div>

        </div>

    </div>

</header>


<!-- =====================================================
     MAIN CONTENT
     ===================================================== -->

<main class="main">

    <div class="page-head">

        <div>

            <h1 class="page-title">
                Bookings
            </h1>

            <p class="page-subtitle">
                All bookings for leads assigned to you
            </p>

        </div>

    </div>


    <?php if ($databaseError !== ''): ?>

        <div class="error-box">
            <?= e($databaseError) ?>
        </div>

    <?php endif; ?>


    <?php if ($debugInfo !== ''): ?>

        <div class="debug-box">
            <?= e($debugInfo) ?>
        </div>

    <?php endif; ?>


    <!-- =================================================
         STATS
         ================================================= -->

    <section class="stats">

        <div class="stat">
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= (int)$totalBookings ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Confirmed</div>
            <div class="stat-value"><?= (int)$confirmedBookings ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Cancelled</div>
            <div class="stat-value"><?= (int)$cancelledBookings ?></div>
        </div>

        <div class="stat">
            <div class="stat-label">Total Booking Value</div>
            <div class="stat-value"><?= formatAmount($totalBookingValue) ?></div>
        </div>

    </section>


    <!-- =================================================
         BOOKINGS TABLE
         ================================================= -->

    <div class="card">

        <div class="card-head">

            <h2 class="card-title">Booking Details</h2>

            <span class="booking-count">
                <?= (int)$totalBookings ?> Booking<?= $totalBookings !== 1 ? 's' : '' ?>
            </span>

        </div>

        <?php if (empty($bookings)): ?>

            <div class="empty">

                <div class="empty-icon">📋</div>

                <p>No bookings found for your assigned leads.</p>

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Booking</th>
                            <th>Property</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($bookings as $b): ?>

                        <?php
                            $stageSlug = strtolower(
                                str_replace(
                                    ' ',
                                    '',
                                    trim((string)($b['lead_stage'] ?? ''))
                                )
                            );

                            /* Map stage slugs for badge colours */
                            $stageClass = 'new';

                            if ($stageSlug === 'booked')      $stageClass = 'booked';
                            elseif ($stageSlug === 'contacted')  $stageClass = 'contacted';
                            elseif ($stageSlug === 'interested') $stageClass = 'interested';
                            elseif ($stageSlug === 'negotiation') $stageClass = 'negotiation';
                            elseif ($stageSlug === 'sitevisit')  $stageClass = 'site';
                            elseif ($stageSlug === 'lost')       $stageClass = 'lost';
                        ?>

                        <tr>

                            <!-- ===========================
                                 LEAD COLUMN
                                 =========================== -->

                            <td>
                                <div class="customer">

                                    <div class="customer-avatar">
                                        <?= e(getInitial($b['lead_name'])) ?>
                                    </div>

                                    <div>

                                        <div class="customer-name">
                                            <?= e($b['lead_name']) ?>
                                        </div>

                                        <div class="customer-info">
                                            <?= e($b['lead_phone']) ?>
                                        </div>

                                        <div class="customer-email">
                                            <?= e($b['lead_email']) ?>
                                        </div>

                                        <div class="lead-meta">
                                            Source: <?= e($b['lead_source']) ?>
                                            &bull; Lead #<?= e($b['lead_id']) ?>
                                        </div>

                                        <span class="lead-stage <?= e($stageClass) ?>">
                                            <?= e($b['lead_stage']) ?>
                                        </span>

                                    </div>

                                </div>
                            </td>


                            <!-- ===========================
                                 BOOKING COLUMN
                                 =========================== -->

                            <td>
                                <div class="booking-detail">
                                    <strong>ID:</strong> #<?= e($b['booking_id']) ?><br>
                                    <strong>Date:</strong> <?= formatDateValue($b['booking_date']) ?><br>
                                    <strong>Amount:</strong> <?= formatAmount($b['amount']) ?>
                                </div>

                                <span class="status-badge <?= e(getStatusClass($b['booking_status'])) ?>">
                                    <?= e(ucfirst(strtolower((string)($b['booking_status'] ?? '')))) ?>
                                </span>
                            </td>


                            <!-- ===========================
                                 PROPERTY COLUMN
                                 =========================== -->

                            <td>
                                <div class="project-name">
                                    <?= e($b['project_name'] ?? '-') ?>
                                </div>

                                <div class="project-location">
                                    <?= e($b['project_location'] ?? '-') ?>
                                </div>

                                <div class="property-info">
                                    <strong>Building:</strong> <?= e($b['building_name'] ?? '-') ?><br>
                                    <strong>Unit:</strong> <?= e($b['unit_number'] ?? '-') ?><br>
                                    <strong>Type:</strong> <?= e($b['unit_type'] ?? '-') ?>
                                    &bull;
                                    <strong>Floor:</strong> <?= e($b['unit_floor'] ?? '-') ?>
                                </div>
                            </td>


                            <!-- ===========================
                                 ACTION COLUMN
                                 =========================== -->

                            <td>
                                <a
                                    class="btn"
                                    href="lead-view.php?id=<?= (int)$b['lead_id'] ?>"
                                >
                                    View Lead
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</main>


</body>

</html>
