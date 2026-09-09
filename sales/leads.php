<?php
/* =========================================================
   PROPFlow CRM - Sales My Leads
   File: sales/leads.php
   ========================================================= */

require_once "../includes/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = currentUser();

/* =========================================================
   SALES ACCESS ONLY
   ========================================================= */

if (
    !$user ||
    strtolower($user['role'] ?? '') !== 'sales'
) {
    header("Location: ../login.php");
    exit;
}

$currentUserId = (int)($user['id'] ?? 0);

/* =========================================================
   CSRF
   ========================================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/* =========================================================
   HELPER
   ========================================================= */

function pf_e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectMessage($type, $message)
{
    header(
        "Location: leads.php?" .
        http_build_query([
            'type' => $type,
            'message' => $message
        ])
    );
    exit;
}

/* =========================================================
   UPDATE LEAD
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            $_POST['csrf_token']
        )
    ) {
        redirectMessage(
            'error',
            'Invalid security token.'
        );
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_lead') {

        $leadId = (int)($_POST['lead_id'] ?? 0);

        $stage = trim(
            $_POST['stage'] ?? 'New'
        );

        $followUpDate = trim(
            $_POST['follow_up_date'] ?? ''
        );

        $notes = trim(
            $_POST['notes'] ?? ''
        );

        /* -------------------------------------------------
           Allowed stages
           ------------------------------------------------- */

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
            redirectMessage(
                'error',
                'Invalid lead.'
            );
        }

        if (
            !in_array(
                $stage,
                $allowedStages,
                true
            )
        ) {
            redirectMessage(
                'error',
                'Invalid lead stage.'
            );
        }

        /* -------------------------------------------------
           Check ownership
           Sales can update ONLY assigned leads
           ------------------------------------------------- */

        $ownership = $pdo->prepare("
            SELECT id
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

        /* -------------------------------------------------
           Validate follow-up date
           ------------------------------------------------- */

        if ($followUpDate !== '') {

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $followUpDate
            );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d')
                    !== $followUpDate
            ) {
                redirectMessage(
                    'error',
                    'Please enter a valid follow-up date.'
                );
            }

        } else {

            $followUpDate = null;
        }

        /* -------------------------------------------------
           Update
           ------------------------------------------------- */

        try {

            $stmt = $pdo->prepare("
                UPDATE leads
                SET
                    stage = ?,
                    follow_up_date = ?,
                    notes = ?
                WHERE id = ?
                  AND assigned_to = ?
            ");

            $stmt->execute([
                $stage,
                $followUpDate,
                $notes !== '' ? $notes : null,
                $leadId,
                $currentUserId
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
   FILTERS
   ========================================================= */

$search = trim(
    $_GET['search'] ?? ''
);

$stageFilter = trim(
    $_GET['stage'] ?? ''
);

$followFilter = trim(
    $_GET['follow'] ?? ''
);

/* =========================================================
   LEAD QUERY
   ========================================================= */

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
        l.created_at
    FROM leads l
    WHERE l.assigned_to = ?
";

$params = [
    $currentUserId
];

/* Search */

if ($search !== '') {

    $sql .= "
        AND (
            l.name LIKE ?
            OR l.phone LIKE ?
            OR l.email LIKE ?
            OR l.source LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

/* Stage */

if ($stageFilter !== '') {

    $allowedFilterStages = [
        'New',
        'Contacted',
        'Site Visit',
        'Interested',
        'Negotiation',
        'Booked',
        'Lost'
    ];

    if (
        in_array(
            $stageFilter,
            $allowedFilterStages,
            true
        )
    ) {
        $sql .= " AND l.stage = ?";
        $params[] = $stageFilter;
    }
}

/* Follow-up */

if ($followFilter === 'today') {

    $sql .= "
        AND l.follow_up_date = CURDATE()
    ";

} elseif ($followFilter === 'overdue') {

    $sql .= "
        AND l.follow_up_date < CURDATE()
        AND l.follow_up_date IS NOT NULL
    ";

} elseif ($followFilter === 'upcoming') {

    $sql .= "
        AND l.follow_up_date > CURDATE()
    ";
}

/* Sorting */

$sql .= "
    ORDER BY
        CASE
            WHEN l.follow_up_date IS NOT NULL
             AND l.follow_up_date < CURDATE()
             AND l.stage NOT IN ('Booked', 'Lost')
            THEN 0

            WHEN l.follow_up_date = CURDATE()
             AND l.stage NOT IN ('Booked', 'Lost')
            THEN 1

            ELSE 2
        END,
        l.created_at DESC
";

try {

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $leads = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $leads = [];
}

/* =========================================================
   COUNTS
   ========================================================= */

$countStmt = $pdo->prepare("
    SELECT
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
        ) AS today_followups,

        SUM(
            CASE
                WHEN stage = 'Booked'
                THEN 1
                ELSE 0
            END
        ) AS booked

    FROM leads
    WHERE assigned_to = ?
");

$countStmt->execute([
    $currentUserId
]);

$counts = $countStmt->fetch(
    PDO::FETCH_ASSOC
);

$totalLeads = (int)(
    $counts['total'] ?? 0
);

$activeLeads = (int)(
    $counts['active'] ?? 0
);

$todayFollowups = (int)(
    $counts['today_followups'] ?? 0
);

$bookedLeads = (int)(
    $counts['booked'] ?? 0
);

/* =========================================================
   ALERT
   ========================================================= */

$messageType = $_GET['type'] ?? '';
$message = $_GET['message'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Leads | PropFlow CRM</title>

<style>

/* =========================================================
   RESET
   ========================================================= */

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
select,
textarea {
    font: inherit;
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

    border-bottom:
        1px solid #e4e9f1;

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
    color: #75819a;
    font-size: 13px;
    margin-top: 2px;
}

/* =========================================================
   MAIN
   ========================================================= */

.main {
    margin-left: 272px;

    padding:
        122px 38px 50px;

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

/* =========================================================
   BUTTON
   ========================================================= */

.btn {
    border: 0;

    padding: 11px 16px;

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
    background: #eef2f7;
    color: #334158;
}

/* =========================================================
   STATS
   ========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 16px;

    margin-bottom: 24px;
}

.stat {
    background: #fff;

    border:
        1px solid #e1e7f0;

    border-radius: 15px;

    padding: 21px;
}

.stat-label {
    color: #71809a;
    font-size: 13px;
}

.stat-value {
    margin-top: 9px;

    font-size: 28px;
    font-weight: 800;
}

/* =========================================================
   ALERT
   ========================================================= */

.alert {
    padding: 14px 18px;

    border-radius: 11px;

    margin-bottom: 20px;

    font-size: 14px;
}

.alert-success {
    background: #effcf4;
    color: #118449;
    border:
        1px solid #b9f0ce;
}

.alert-error {
    background: #fff2f2;
    color: #c52c2c;
    border:
        1px solid #ffc7c7;
}

/* =========================================================
   CARD
   ========================================================= */

.card {
    background: #fff;

    border:
        1px solid #e1e7f0;

    border-radius: 16px;

    overflow: hidden;
}

.card-head {
    display: flex;

    align-items: center;
    justify-content: space-between;

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

    transform:
        translateY(-50%);

    color: #8794a9;

    pointer-events: none;
}

.filter-control {
    height: 42px;

    border:
        1px solid #dbe1eb;

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
    width: 170px;
}

.filter-control:focus {
    border-color: #2864e8;

    box-shadow:
        0 0 0 3px
        rgba(40,100,232,.09);
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

    min-width: 1000px;
}

th {
    text-align: left;

    padding: 14px 21px;

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

tr:hover td {
    background: #fcfdff;
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
    margin-top: 4px;

    color: #4e5c74;

    font-size: 13px;
}

/* =========================================================
   STAGE BADGES
   ========================================================= */

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
    color: #16834b;
}

.stage-lost {
    background: #fff0f0;
    color: #d73535;
}

/* =========================================================
   FOLLOW-UP
   ========================================================= */

.follow-normal {
    color: #65738b;
}

.follow-today {
    color: #c47700;
    font-weight: 700;
}

.follow-overdue {
    color: #d73535;
    font-weight: 700;
}

/* =========================================================
   ACTIONS
   ========================================================= */

.actions {
    display: flex;

    align-items: center;

    gap: 7px;
}

.action-btn {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    height: 34px;

    padding: 0 10px;

    border-radius: 8px;

    border: 1px solid #dfe5ee;

    background: #fff;

    color: #3c4b63;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;
}

.action-btn:hover {
    border-color: #2864e8;
    color: #2864e8;
}

.view-btn {
    background: #eef3ff;
    color: #2864e8;
    border-color: #dbe6ff;
}

/* =========================================================
   EMPTY
   ========================================================= */

.empty {
    padding: 65px 20px;

    text-align: center;

    color: #71809a;
}

.empty-icon {
    font-size: 40px;
    margin-bottom: 12px;
}

.empty-title {
    color: #29364b;

    font-weight: 700;

    font-size: 16px;

    margin-bottom: 5px;
}

/* =========================================================
   MODAL
   ========================================================= */

.modal {
    display: none;

    position: fixed;

    inset: 0;

    background:
        rgba(13,22,40,.52);

    z-index: 100;

    align-items: center;
    justify-content: center;

    padding: 20px;
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;
    max-width: 570px;

    background: #fff;

    border-radius: 17px;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.18);

    overflow: hidden;
}

.modal-head {
    display: flex;

    align-items: center;
    justify-content: space-between;

    padding: 20px 23px;

    border-bottom:
        1px solid #e7ebf1;
}

.modal-title {
    margin: 0;

    font-size: 18px;
}

.close-btn {
    border: 0;

    background: #f1f4f8;

    width: 34px;
    height: 34px;

    border-radius: 50%;

    cursor: pointer;

    font-size: 18px;

    color: #5d6a80;
}

.modal-body {
    padding: 23px;
}

.form-group {
    margin-bottom: 17px;
}

.form-label {
    display: block;

    margin-bottom: 7px;

    color: #45536a;

    font-size: 13px;

    font-weight: 700;
}

.form-control,
textarea.form-control {
    width: 100%;

    border:
        1px solid #dbe1eb;

    border-radius: 9px;

    background: #fff;

    padding: 11px 12px;

    outline: none;

    font-size: 14px;
}

textarea.form-control {
    min-height: 110px;

    resize: vertical;
}

.form-control:focus {
    border-color: #2864e8;

    box-shadow:
        0 0 0 3px
        rgba(40,100,232,.09);
}

.modal-footer {
    display: flex;

    justify-content: flex-end;

    gap: 10px;

    padding: 17px 23px;

    border-top:
        1px solid #e7ebf1;
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
            repeat(2, 1fr);
    }
}

@media (max-width: 760px) {

    .topbar {
        position: static;

        left: auto;

        height: 70px;

        padding: 0 18px 0 60px;
    }

    .main {
        margin-left: 0;

        padding:
            28px 18px 40px;
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
}

</style>

</head>

<body>

<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<?php $activePage = 'leads'; include 'sidebar.php'; ?>

<!-- =====================================================
     TOPBAR
     ===================================================== -->

<header class="topbar">

    <div class="profile">

        <div class="avatar">

            <?= pf_e(
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
                <?= pf_e(
                    $user['name']
                    ?? 'Sales Executive'
                ) ?>
            </div>

            <div class="profile-role">
                Sales Executive
            </div>

        </div>

    </div>

</header>

<!-- =====================================================
     MAIN
     ===================================================== -->

<main class="main">

    <div class="page-head">

        <div>

            <h1 class="page-title">
                My Leads
            </h1>

            <p class="page-subtitle">
                Manage and follow up with your assigned leads.
            </p>

        </div>

    </div>

    <!-- =================================================
         ALERT
         ================================================= -->

    <?php if ($message !== ''): ?>

        <div class="
            alert
            <?= $messageType === 'success'
                ? 'alert-success'
                : 'alert-error'
            ?>
        ">

            <?= pf_e($message) ?>

        </div>

    <?php endif; ?>

    <!-- =================================================
         STATS
         ================================================= -->

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

    <!-- =================================================
         LEADS CARD
         ================================================= -->

    <section class="card">

        <div class="card-head">

            <h2 class="card-title">
                Assigned Leads
            </h2>

            <div class="card-count">
                <?= number_format(count($leads)) ?>
                result(s)
            </div>

        </div>

        <!-- FILTERS -->

        <form
            method="GET"
            class="filters"
        >

            <div class="search-box">

                <span class="search-icon">
                    🔎
                </span>

                <input
                    type="text"
                    name="search"
                    class="filter-control"
                    placeholder="Search name, phone, email or source..."
                    value="<?= pf_e($search) ?>"
                >

            </div>

            <select
                name="stage"
                class="filter-control filter-select"
            >

                <option value="">
                    All Stages
                </option>

                <?php
                $stages = [
                    'New',
                    'Contacted',
                    'Site Visit',
                    'Interested',
                    'Negotiation',
                    'Booked',
                    'Lost'
                ];
                ?>

                <?php foreach ($stages as $stage): ?>

                    <option
                        value="<?= pf_e($stage) ?>"
                        <?= $stageFilter === $stage
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= pf_e($stage) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <select
                name="follow"
                class="filter-control filter-select"
            >

                <option value="">
                    All Follow-ups
                </option>

                <option
                    value="today"
                    <?= $followFilter === 'today'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Today
                </option>

                <option
                    value="overdue"
                    <?= $followFilter === 'overdue'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Overdue
                </option>

                <option
                    value="upcoming"
                    <?= $followFilter === 'upcoming'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Upcoming
                </option>

            </select>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Filter
            </button>

            <a
                href="leads.php"
                class="btn btn-secondary"
            >
                Reset
            </a>

        </form>

        <!-- TABLE -->

        <?php if (empty($leads)): ?>

            <div class="empty">

                <div class="empty-icon">
                    👥
                </div>

                <div class="empty-title">
                    No leads found
                </div>

                <div>
                    No leads are currently assigned to you.
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

                        $stage =
                            $lead['stage'] ?? 'New';

                        switch ($stage) {

                            case 'Contacted':
                                $stageClass =
                                    'stage-contacted';
                                break;

                            case 'Site Visit':
                                $stageClass =
                                    'stage-site';
                                break;

                            case 'Interested':
                                $stageClass =
                                    'stage-interested';
                                break;

                            case 'Negotiation':
                                $stageClass =
                                    'stage-negotiation';
                                break;

                            case 'Booked':
                                $stageClass =
                                    'stage-booked';
                                break;

                            case 'Lost':
                                $stageClass =
                                    'stage-lost';
                                break;

                            default:
                                $stageClass =
                                    'stage-new';
                                break;
                        }

                        $followClass =
                            'follow-normal';

                        $followText =
                            '—';

                        if (
                            !empty(
                                $lead['follow_up_date']
                            )
                        ) {

                            $followDate =
                                $lead['follow_up_date'];

                            if (
                                $followDate
                                < date('Y-m-d')
                            ) {

                                $followClass =
                                    'follow-overdue';

                                $followText =
                                    'Overdue · ' .
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $followDate
                                        )
                                    );

                            } elseif (
                                $followDate
                                === date('Y-m-d')
                            ) {

                                $followClass =
                                    'follow-today';

                                $followText =
                                    'Today';

                            } else {

                                $followText =
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $followDate
                                        )
                                    );
                            }
                        }

                        ?>

                        <tr>

                            <!-- LEAD -->

                            <td>

                                <div class="primary-text">

                                    <?= pf_e(
                                        $lead['name']
                                    ) ?>

                                </div>

                                <div class="secondary-text">

                                    <?= pf_e(
                                        $lead['email']
                                        ?: 'No email'
                                    ) ?>

                                </div>

                                <div class="phone">

                                    <?= pf_e(
                                        $lead['phone']
                                    ) ?>

                                </div>

                            </td>

                            <!-- SOURCE -->

                            <td>

                                <?= pf_e(
                                    $lead['source']
                                    ?: 'Direct'
                                ) ?>

                            </td>

                            <!-- STAGE -->

                            <td>

                                <span class="
                                    badge
                                    <?= pf_e(
                                        $stageClass
                                    ) ?>
                                ">

                                    <?= pf_e(
                                        $stage
                                    ) ?>

                                </span>

                            </td>

                            <!-- FOLLOW UP -->

                            <td>

                                <span class="
                                    <?= pf_e(
                                        $followClass
                                    ) ?>
                                ">

                                    <?= pf_e(
                                        $followText
                                    ) ?>

                                </span>

                            </td>

                            <!-- CREATED -->

                            <td>

                                <?= !empty(
                                    $lead['created_at']
                                )
                                    ? pf_e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $lead['created_at']
                                            )
                                        )
                                    )
                                    : '—'
                                ?>

                            </td>

                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">

                                    <a
                                        href="lead-view.php?id=<?= (int)$lead['id'] ?>"
                                        class="
                                            action-btn
                                            view-btn
                                        "
                                    >
                                        View
                                    </a>

                                    <button
                                        type="button"
                                        class="action-btn"
                                        onclick='openEditModal(
                                            <?= json_encode(
                                                $lead,
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT |
                                                JSON_HEX_AMP
                                            ) ?>
                                        )'
                                    >
                                        Edit
                                    </button>

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

<!-- =====================================================
     EDIT MODAL
     ===================================================== -->

<div
    id="editModal"
    class="modal"
>

    <div class="modal-box">

        <div class="modal-head">

            <h3 class="modal-title">
                Update Lead
            </h3>

            <button
                type="button"
                class="close-btn"
                onclick="closeEditModal()"
            >
                ×
            </button>

        </div>

        <form
            method="POST"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= pf_e($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="update_lead"
            >

            <input
                type="hidden"
                id="editLeadId"
                name="lead_id"
                value=""
            >

            <div class="modal-body">

                <div class="form-group">

                    <label class="form-label">
                        Lead
                    </label>

                    <input
                        type="text"
                        id="editLeadName"
                        class="form-control"
                        readonly
                    >

                </div>

                <div class="form-group">

                    <label class="form-label">
                        Stage
                    </label>

                    <select
                        id="editStage"
                        name="stage"
                        class="form-control"
                    >

                        <?php foreach ($stages as $stage): ?>

                            <option
                                value="<?= pf_e($stage) ?>"
                            >
                                <?= pf_e($stage) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label class="form-label">
                        Follow-up Date
                    </label>

                    <input
                        type="date"
                        id="editFollowDate"
                        name="follow_up_date"
                        class="form-control"
                    >

                </div>

                <div class="form-group">

                    <label class="form-label">
                        Notes
                    </label>

                    <textarea
                        id="editNotes"
                        name="notes"
                        class="form-control"
                        placeholder="Add follow-up notes..."
                    ></textarea>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeEditModal()"
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
   EDIT MODAL
   ========================================================= */

function openEditModal(lead) {

    document.getElementById(
        'editLeadId'
    ).value = lead.id || '';

    document.getElementById(
        'editLeadName'
    ).value = lead.name || '';

    document.getElementById(
        'editStage'
    ).value = lead.stage || 'New';

    document.getElementById(
        'editFollowDate'
    ).value =
        lead.follow_up_date || '';

    document.getElementById(
        'editNotes'
    ).value =
        lead.notes || '';

    document.getElementById(
        'editModal'
    ).classList.add('show');
}

function closeEditModal() {

    document.getElementById(
        'editModal'
    ).classList.remove('show');
}

/* Close when clicking outside */

document.getElementById(
    'editModal'
).addEventListener(
    'click',
    function(event) {

        if (event.target === this) {
            closeEditModal();
        }

    }
);

/* ESC */

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {
            closeEditModal();
        }

    }
);

</script>

</body>
</html>