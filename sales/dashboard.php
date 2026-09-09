<?php
/* =========================================================
   PROPFlow CRM - Sales Executive Dashboard
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

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userId = (int)($user['id'] ?? 0);

/* =========================================================
   SALES STATS
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_leads,
        SUM(CASE
            WHEN stage NOT IN ('Booked','Lost')
            THEN 1 ELSE 0
        END) AS active_leads,
        SUM(CASE
            WHEN follow_up_date = CURDATE()
             AND stage NOT IN ('Booked','Lost')
            THEN 1 ELSE 0
        END) AS today_followups,
        SUM(CASE
            WHEN stage = 'Booked'
            THEN 1 ELSE 0
        END) AS booked_leads
    FROM leads
    WHERE assigned_to = ?
");

$stmt->execute([$userId]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalLeads = (int)($stats['total_leads'] ?? 0);
$activeLeads = (int)($stats['active_leads'] ?? 0);
$todayFollowups = (int)($stats['today_followups'] ?? 0);
$bookedLeads = (int)($stats['booked_leads'] ?? 0);

/* =========================================================
   RECENT / PRIORITY LEADS
   ========================================================= */

$leadStmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        email,
        source,
        stage,
        follow_up_date,
        created_at
    FROM leads
    WHERE assigned_to = ?
    ORDER BY
        CASE
            WHEN follow_up_date IS NOT NULL
             AND follow_up_date < CURDATE()
             AND stage NOT IN ('Booked','Lost')
            THEN 0

            WHEN follow_up_date = CURDATE()
             AND stage NOT IN ('Booked','Lost')
            THEN 1

            ELSE 2
        END,
        created_at DESC
    LIMIT 8
");

$leadStmt->execute([$userId]);

$leads = $leadStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   RECENT BOOKINGS
   ========================================================= */

$bookingStmt = $pdo->prepare("
    SELECT
        bk.id,
        bk.booking_date,
        bk.amount,
        bk.status,
        l.name AS lead_name,
        u.unit_number,
        p.name AS project_name
    FROM bookings bk

    INNER JOIN leads l
        ON l.id = bk.lead_id

    INNER JOIN units u
        ON u.id = bk.unit_id

    INNER JOIN buildings b
        ON b.id = u.building_id

    INNER JOIN projects p
        ON p.id = b.project_id

    WHERE l.assigned_to = ?

    ORDER BY bk.created_at DESC

    LIMIT 5
");

$bookingStmt->execute([$userId]);

$bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Sales Dashboard | PropFlow CRM</title>

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

/* Sidebar CSS is loaded from sidebar.php include */

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

.main {
    margin-left: 272px;

    padding: 122px 38px 50px;

    min-height: 100vh;
}

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

.btn {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: 0;

    border-radius: 9px;

    padding: 11px 15px;

    font-size: 13px;

    font-weight: 750;
}

.btn-primary {
    background: #2864e8;
    color: #fff;
}

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

.content-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.55fr)
        minmax(300px, .75fr);

    gap: 20px;
}

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

.card-link {
    color: #2864e8;

    font-size: 13px;

    font-weight: 700;
}

table {
    width: 100%;

    border-collapse: collapse;
}

th {
    text-align: left;

    padding: 13px 20px;

    background: #fbfcfe;

    color: #8090aa;

    font-size: 10px;

    letter-spacing: .08em;

    text-transform: uppercase;
}

td {
    padding: 15px 20px;

    border-top: 1px solid #edf0f5;

    font-size: 13px;

    vertical-align: middle;
}

.primary {
    font-weight: 700;
}

.secondary {
    margin-top: 3px;

    color: #8290a8;

    font-size: 11px;
}

.badge {
    display: inline-flex;

    padding: 6px 9px;

    border-radius: 999px;

    font-size: 11px;

    font-weight: 700;
}

.new {
    background: #eef3ff;
    color: #2864e8;
}

.contacted {
    background: #f2efff;
    color: #6844c8;
}

.site {
    background: #fff7e8;
    color: #ad6a00;
}

.interested {
    background: #eafaf1;
    color: #118449;
}

.negotiation {
    background: #fff0e8;
    color: #c85a16;
}

.booked {
    background: #e7f8ee;
    color: #08753d;
}

.lost {
    background: #fff0f0;
    color: #d73535;
}

.followup {
    font-size: 12px;
}

.overdue {
    color: #d73535;

    font-weight: 750;
}

.today {
    color: #c65c00;

    font-weight: 750;
}

.normal {
    color: #64728a;
}

.side-list {
    padding: 0;
}

.booking-item {
    padding: 17px 20px;

    border-bottom: 1px solid #edf0f5;
}

.booking-item:last-child {
    border-bottom: 0;
}

.booking-top {
    display: flex;

    justify-content: space-between;

    gap: 10px;
}

.amount {
    font-weight: 800;
}

.empty {
    padding: 50px 20px;

    text-align: center;

    color: #7b879b;
}

.empty-icon {
    font-size: 32px;

    margin-bottom: 10px;
}

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

    .content-grid {
        grid-template-columns: 1fr;
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
        min-width: 750px;
    }

    .table-wrap {
        overflow-x: auto;
    }
}

</style>

</head>

<body>

<?php $activePage = 'dashboard'; include 'sidebar.php'; ?>


<header class="topbar">

    <div class="profile">

        <div class="avatar">
            <?= e(
                strtoupper(
                    substr(
                        $user['name'] ?? 'S',
                        0,
                        1
                    )
                )
            ) ?>
        </div>

        <div>

            <div class="profile-name">
                <?= e($user['name'] ?? 'Sales Executive') ?>
            </div>

            <div class="profile-role">
                Sales Executive
            </div>

        </div>

    </div>

</header>


<main class="main">

    <div class="page-head">

        <div>

            <h1 class="page-title">
                Welcome, <?= e($user['name'] ?? 'Sales Executive') ?>
            </h1>

            <p class="page-subtitle">
                Manage your leads, follow-ups and sales pipeline.
            </p>

        </div>

        <a
            href="my-leads.php"
            class="btn btn-primary"
        >
            View My Leads
        </a>

    </div>


    <section class="stats">

        <div class="stat">

            <div class="stat-label">
                My Leads
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
                <?= number_format($todayFollowups) ?>
            </div>

        </div>

        <div class="stat">

            <div class="stat-label">
                Booked Leads
            </div>

            <div class="stat-value">
                <?= number_format($bookedLeads) ?>
            </div>

        </div>

    </section>


    <section class="content-grid">

        <div class="card">

            <div class="card-head">

                <h2 class="card-title">
                    Priority Leads
                </h2>

                <a
                    href="my-leads.php"
                    class="card-link"
                >
                    View All →
                </a>

            </div>


            <?php if (empty($leads)): ?>

                <div class="empty">

                    <div class="empty-icon">
                        👥
                    </div>

                    <div>
                        No leads assigned yet.
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
                                    Stage
                                </th>

                                <th>
                                    Follow-up
                                </th>

                                <th>
                                    Source
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($leads as $lead): ?>

                            <?php

                            $stage =
                                $lead['stage'] ?? 'New';

                            $stageClass =
                                match ($stage) {

                                    'Contacted'
                                        => 'contacted',

                                    'Site Visit'
                                        => 'site',

                                    'Interested'
                                        => 'interested',

                                    'Negotiation'
                                        => 'negotiation',

                                    'Booked'
                                        => 'booked',

                                    'Lost'
                                        => 'lost',

                                    default
                                        => 'new'
                                };

                            $followClass =
                                'normal';

                            $followText =
                                '—';

                            if (
                                !empty(
                                    $lead['follow_up_date']
                                )
                            ) {

                                if (
                                    $lead['follow_up_date']
                                    < date('Y-m-d')
                                ) {

                                    $followClass =
                                        'overdue';

                                    $followText =
                                        'Overdue · ' .
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $lead['follow_up_date']
                                            )
                                        );

                                } elseif (
                                    $lead['follow_up_date']
                                    === date('Y-m-d')
                                ) {

                                    $followClass =
                                        'today';

                                    $followText =
                                        'Today';

                                } else {

                                    $followText =
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $lead['follow_up_date']
                                            )
                                        );
                                }
                            }

                            ?>

                            <tr>

                                <td>

                                    <div class="primary">
                                        <?= e($lead['name']) ?>
                                    </div>

                                    <div class="secondary">
                                        <?= e($lead['phone']) ?>
                                    </div>

                                </td>

                                <td>

                                    <span class="
                                        badge
                                        <?= e($stageClass) ?>
                                    ">
                                        <?= e($stage) ?>
                                    </span>

                                </td>

                                <td>

                                    <span class="
                                        followup
                                        <?= e($followClass) ?>
                                    ">
                                        <?= e($followText) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= e(
                                        $lead['source']
                                        ?: 'Direct'
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>


        <div class="card">

            <div class="card-head">

                <h2 class="card-title">
                    Recent Bookings
                </h2>

                <a
                    href="my-leads.php"
                    class="card-link"
                >
                    My Leads
                </a>

            </div>


            <div class="side-list">

                <?php if (empty($bookings)): ?>

                    <div class="empty">

                        <div class="empty-icon">
                            🏠
                        </div>

                        <div>
                            No bookings yet.
                        </div>

                    </div>

                <?php else: ?>

                    <?php foreach ($bookings as $booking): ?>

                        <div class="booking-item">

                            <div class="booking-top">

                                <div>

                                    <div class="primary">
                                        <?= e(
                                            $booking['lead_name']
                                        ) ?>
                                    </div>

                                    <div class="secondary">
                                        <?= e(
                                            $booking['project_name']
                                        ) ?>
                                        · Unit
                                        <?= e(
                                            $booking['unit_number']
                                        ) ?>
                                    </div>

                                </div>

                                <div class="amount">
                                    ₹<?= number_format(
                                        (float)$booking['amount']
                                    ) ?>
                                </div>

                            </div>

                            <div
                                class="secondary"
                                style="margin-top:8px;"
                            >
                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $booking['booking_date']
                                        )
                                    )
                                ) ?>

                                ·

                                <?= e(
                                    ucfirst(
                                        strtolower(
                                            $booking['status']
                                        )
                                    )
                                ) ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>

</body>
</html>
