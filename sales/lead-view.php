<?php
/* =========================================================
   PROPFlow CRM - Sales Lead Details
   File: sales/lead-view.php
   ========================================================= */

require_once "../includes/auth.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = currentUser();

/* =========================================================
   SALES ACCESS
   ========================================================= */

if (!$user || strtolower($user['role'] ?? '') !== 'sales') {
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
   HELPERS
   ========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectWithMessage($type, $message, $id)
{
    header(
        "Location: lead-view.php?" .
        http_build_query([
            'id' => $id,
            'type' => $type,
            'message' => $message
        ])
    );
    exit;
}

/* =========================================================
   LEAD ID
   ========================================================= */

$leadId = (int)($_GET['id'] ?? $_POST['lead_id'] ?? 0);

if ($leadId <= 0) {
    header("Location: leads.php");
    exit;
}

/* =========================================================
   UPDATE LEAD
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        empty($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        redirectWithMessage(
            'error',
            'Invalid security token.',
            $leadId
        );
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_lead') {

        $stage = trim($_POST['stage'] ?? 'New');

        $followUpDate =
            trim($_POST['follow_up_date'] ?? '');

        $notes =
            trim($_POST['notes'] ?? '');

        $allowedStages = [
            'New',
            'Contacted',
            'Site Visit',
            'Interested',
            'Negotiation',
            'Booked',
            'Lost'
        ];

        if (
            !in_array(
                $stage,
                $allowedStages,
                true
            )
        ) {
            redirectWithMessage(
                'error',
                'Invalid lead stage.',
                $leadId
            );
        }

        /* -----------------------------------------------------
           Verify this lead belongs to logged-in sales employee
           ----------------------------------------------------- */

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
            redirectWithMessage(
                'error',
                'You can only update leads assigned to you.',
                $leadId
            );
        }

        /* -----------------------------------------------------
           Validate date
           ----------------------------------------------------- */

        if ($followUpDate !== '') {

            $dateObject =
                DateTime::createFromFormat(
                    'Y-m-d',
                    $followUpDate
                );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d')
                    !== $followUpDate
            ) {
                redirectWithMessage(
                    'error',
                    'Please enter a valid follow-up date.',
                    $leadId
                );
            }

        } else {

            $followUpDate = null;
        }

        /* -----------------------------------------------------
           Update
           ----------------------------------------------------- */

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

            redirectWithMessage(
                'success',
                'Lead updated successfully.',
                $leadId
            );

        } catch (PDOException $e) {

            redirectWithMessage(
                'error',
                'Unable to update lead. Please try again.',
                $leadId
            );
        }
    }
}

/* =========================================================
   GET LEAD
   ========================================================= */

try {

    $stmt = $pdo->prepare("
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
        WHERE l.id = ?
          AND l.assigned_to = ?
        LIMIT 1
    ");

    $stmt->execute([
        $leadId,
        $currentUserId
    ]);

    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $lead = false;
}

/* =========================================================
   LEAD NOT FOUND
   ========================================================= */

if (!$lead) {

    http_response_code(404);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >
        <title>Lead Not Found | PropFlow CRM</title>

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

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .box {
            width: 420px;
            max-width: 90%;

            background: #fff;

            border: 1px solid #e1e7f0;

            border-radius: 18px;

            padding: 40px;

            text-align: center;
        }

        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        h2 {
            margin: 0 0 8px;
        }

        p {
            color: #71809a;
            margin-bottom: 25px;
        }

        a {
            display: inline-block;

            background: #2864e8;
            color: #fff;

            padding: 11px 18px;

            border-radius: 9px;

            text-decoration: none;

            font-weight: 700;
        }

        </style>
    </head>

    <body>

        <div class="box">

            <div class="icon">
                🔍
            </div>

            <h2>
                Lead Not Found
            </h2>

            <p>
                This lead does not exist or is not assigned to you.
            </p>

            <a href="leads.php">
                ← Back to My Leads
            </a>

        </div>

    </body>
    </html>

    <?php
    exit;
}

/* =========================================================
   MESSAGE
   ========================================================= */

$messageType = $_GET['type'] ?? '';
$message = $_GET['message'] ?? '';

/* =========================================================
   STAGE
   ========================================================= */

$currentStage =
    $lead['stage'] ?: 'New';

switch ($currentStage) {

    case 'Contacted':
        $stageClass = 'contacted';
        break;

    case 'Site Visit':
        $stageClass = 'site';
        break;

    case 'Interested':
        $stageClass = 'interested';
        break;

    case 'Negotiation':
        $stageClass = 'negotiation';
        break;

    case 'Booked':
        $stageClass = 'booked';
        break;

    case 'Lost':
        $stageClass = 'lost';
        break;

    default:
        $stageClass = 'new';
        break;
}

/* =========================================================
   FOLLOW-UP STATUS
   ========================================================= */

$followStatus = 'No follow-up';

if (!empty($lead['follow_up_date'])) {

    if ($lead['follow_up_date'] < date('Y-m-d')) {

        $followStatus = 'Overdue';

    } elseif (
        $lead['follow_up_date'] === date('Y-m-d')
    ) {

        $followStatus = 'Today';

    } else {

        $followStatus = 'Upcoming';
    }
}

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
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= e($lead['name']) ?> |
    PropFlow CRM
</title>

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
        122px 38px 55px;

    min-height: 100vh;
}

.back-link {
    display: inline-flex;

    align-items: center;

    gap: 8px;

    color: #2864e8;

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 18px;
}

.page-head {
    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
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
   BUTTONS
   ========================================================= */

.btn {
    border: 0;

    border-radius: 9px;

    padding: 11px 16px;

    font-size: 13px;

    font-weight: 750;

    cursor: pointer;
}

.btn-primary {
    background: #2864e8;

    color: #fff;
}

.btn-secondary {
    background: #eef2f7;

    color: #334158;
}

/* =========================================================
   ALERT
   ========================================================= */

.alert {
    padding: 14px 18px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;
}

.alert-success {
    background: #effcf4;

    border: 1px solid #b9f0ce;

    color: #118449;
}

.alert-error {
    background: #fff2f2;

    border: 1px solid #ffc7c7;

    color: #c52c2c;
}

/* =========================================================
   GRID
   ========================================================= */

.content-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.6fr)
        minmax(320px, .8fr);

    gap: 22px;
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
    padding: 21px 23px;

    border-bottom:
        1px solid #e8ecf2;
}

.card-title {
    margin: 0;

    font-size: 17px;

    font-weight: 750;
}

.card-body {
    padding: 23px;
}

/* =========================================================
   PROFILE
   ========================================================= */

.lead-profile {
    display: flex;

    align-items: center;

    gap: 16px;

    padding-bottom: 23px;

    border-bottom:
        1px solid #edf0f5;

    margin-bottom: 23px;
}

.lead-avatar {
    width: 62px;
    height: 62px;

    border-radius: 15px;

    background: #eaf1ff;

    color: #2864e8;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 22px;

    font-weight: 800;
}

.lead-name {
    font-size: 22px;

    font-weight: 800;
}

.lead-source {
    margin-top: 5px;

    color: #78869d;

    font-size: 13px;
}

/* =========================================================
   INFO
   ========================================================= */

.info-grid {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 20px;
}

.info-label {
    color: #8490a4;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .07em;

    font-weight: 750;

    margin-bottom: 6px;
}

.info-value {
    color: #263247;

    font-size: 14px;

    font-weight: 650;

    word-break: break-word;
}

.info-value a {
    color: #2864e8;
}

/* =========================================================
   STATUS
   ========================================================= */

.status-box {
    margin-top: 23px;

    padding-top: 23px;

    border-top:
        1px solid #edf0f5;
}

.status-row {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 14px;
}

.status-label {
    color: #71809a;

    font-size: 13px;
}

.badge {
    display: inline-flex;

    padding: 6px 11px;

    border-radius: 999px;

    font-size: 12px;

    font-weight: 750;
}

.badge.new {
    background: #eef3ff;
    color: #2864e8;
}

.badge.contacted {
    background: #f2efff;
    color: #6844c8;
}

.badge.site {
    background: #fff7e8;
    color: #ad6a00;
}

.badge.interested {
    background: #eafaf1;
    color: #118449;
}

.badge.negotiation {
    background: #fff0e8;
    color: #c85a16;
}

.badge.booked {
    background: #e7f8ee;
    color: #16834b;
}

.badge.lost {
    background: #fff0f0;
    color: #d73535;
}

/* =========================================================
   NOTES
   ========================================================= */

.notes-box {
    margin-top: 22px;

    padding: 17px;

    border-radius: 11px;

    background: #f8fafc;

    border:
        1px solid #edf0f5;
}

.notes-label {
    font-size: 12px;

    font-weight: 750;

    color: #637087;

    margin-bottom: 8px;
}

.notes-text {
    color: #46546a;

    font-size: 14px;

    line-height: 1.65;

    white-space: pre-wrap;
}

/* =========================================================
   FORM
   ========================================================= */

.form-group {
    margin-bottom: 18px;
}

.form-label {
    display: block;

    margin-bottom: 7px;

    color: #46536a;

    font-size: 13px;

    font-weight: 700;
}

.form-control {
    width: 100%;

    border:
        1px solid #dbe1eb;

    border-radius: 9px;

    padding: 11px 12px;

    background: #fff;

    outline: none;

    color: #172033;

    font-size: 14px;
}

textarea.form-control {
    min-height: 135px;

    resize: vertical;
}

.form-control:focus {
    border-color: #2864e8;

    box-shadow:
        0 0 0 3px
        rgba(40,100,232,.09);
}

.form-footer {
    display: flex;

    justify-content: flex-end;

    gap: 10px;

    padding-top: 5px;
}

/* =========================================================
   QUICK ACTIONS
   ========================================================= */

.quick-actions {
    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;
}

.quick-action {
    display: flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    min-height: 43px;

    border:
        1px solid #dfe5ee;

    border-radius: 9px;

    color: #3b4a62;

    font-size: 13px;

    font-weight: 700;
}

.quick-action:hover {
    border-color: #2864e8;

    color: #2864e8;
}

/* =========================================================
   TIMELINE
   ========================================================= */

.timeline {
    margin-top: 24px;
}

.timeline-item {
    position: relative;

    padding-left: 22px;

    padding-bottom: 19px;
}

.timeline-item:before {
    content: "";

    position: absolute;

    left: 3px;
    top: 5px;

    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: #2864e8;
}

.timeline-item:after {
    content: "";

    position: absolute;

    left: 6px;
    top: 14px;
    bottom: 0;

    width: 2px;

    background: #e2e8f0;
}

.timeline-item:last-child:after {
    display: none;
}

.timeline-title {
    font-size: 13px;

    font-weight: 700;

    color: #344157;
}

.timeline-date {
    margin-top: 3px;

    color: #8490a4;

    font-size: 12px;
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

    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {

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

    .info-grid {
        grid-template-columns: 1fr;
    }

    .quick-actions {
        grid-template-columns: 1fr;
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

                <?= e(
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

    <a
        href="leads.php"
        class="back-link"
    >
        ← Back to My Leads
    </a>

    <div class="page-head">

        <div>

            <h1 class="page-title">
                <?= e($lead['name']) ?>
            </h1>

            <p class="page-subtitle">
                Lead details and follow-up management
            </p>

        </div>

        <span class="
            badge
            <?= e($stageClass) ?>
        ">
            <?= e($currentStage) ?>
        </span>

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

    <div class="content-grid">

        <!-- =================================================
             LEFT
             ================================================= -->

        <div>

            <!-- Lead Information -->

            <section class="card">

                <div class="card-head">

                    <h2 class="card-title">
                        Lead Information
                    </h2>

                </div>

                <div class="card-body">

                    <div class="lead-profile">

                        <div class="lead-avatar">

                            <?= e(
                                strtoupper(
                                    substr(
                                        $lead['name'],
                                        0,
                                        1
                                    )
                                )
                            ) ?>

                        </div>

                        <div>

                            <div class="lead-name">
                                <?= e(
                                    $lead['name']
                                ) ?>
                            </div>

                            <div class="lead-source">
                                Lead source:
                                <?= e(
                                    $lead['source']
                                    ?: 'Direct'
                                ) ?>
                            </div>

                        </div>

                    </div>

                    <div class="info-grid">

                        <div>

                            <div class="info-label">
                                Phone
                            </div>

                            <div class="info-value">

                                <?php if (
                                    !empty(
                                        $lead['phone']
                                    )
                                ): ?>

                                    <a href="tel:<?= e(
                                        $lead['phone']
                                    ) ?>">

                                        <?= e(
                                            $lead['phone']
                                        ) ?>

                                    </a>

                                <?php else: ?>

                                    —
                                    
                                <?php endif; ?>

                            </div>

                        </div>

                        <div>

                            <div class="info-label">
                                Email
                            </div>

                            <div class="info-value">

                                <?php if (
                                    !empty(
                                        $lead['email']
                                    )
                                ): ?>

                                    <a href="mailto:<?= e(
                                        $lead['email']
                                    ) ?>">

                                        <?= e(
                                            $lead['email']
                                        ) ?>

                                    </a>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </div>

                        </div>

                        <div>

                            <div class="info-label">
                                Source
                            </div>

                            <div class="info-value">

                                <?= e(
                                    $lead['source']
                                    ?: 'Direct'
                                ) ?>

                            </div>

                        </div>

                        <div>

                            <div class="info-label">
                                Created
                            </div>

                            <div class="info-value">

                                <?= !empty(
                                    $lead['created_at']
                                )
                                    ? e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $lead['created_at']
                                            )
                                        )
                                    )
                                    : '—'
                                ?>

                            </div>

                        </div>

                    </div>

                    <div class="status-box">

                        <div class="status-row">

                            <span class="status-label">
                                Current Stage
                            </span>

                            <span class="
                                badge
                                <?= e(
                                    $stageClass
                                ) ?>
                            ">

                                <?= e(
                                    $currentStage
                                ) ?>

                            </span>

                        </div>

                        <div class="status-row">

                            <span class="status-label">
                                Follow-up
                            </span>

                            <strong>

                                <?php if (
                                    $followStatus === 'Overdue'
                                ): ?>

                                    <span style="color:#d73535;">
                                        Overdue
                                    </span>

                                <?php elseif (
                                    $followStatus === 'Today'
                                ): ?>

                                    <span style="color:#ad6a00;">
                                        Today
                                    </span>

                                <?php else: ?>

                                    <?= e(
                                        $followStatus
                                    ) ?>

                                <?php endif; ?>

                            </strong>

                        </div>

                        <?php if (
                            !empty(
                                $lead['follow_up_date']
                            )
                        ): ?>

                            <div class="status-row">

                                <span class="status-label">
                                    Follow-up Date
                                </span>

                                <strong>

                                    <?= e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $lead[
                                                    'follow_up_date'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </strong>

                            </div>

                        <?php endif; ?>

                    </div>

                    <?php if (
                        !empty(
                            $lead['notes']
                        )
                    ): ?>

                        <div class="notes-box">

                            <div class="notes-label">
                                Current Notes
                            </div>

                            <div class="notes-text">

                                <?= e(
                                    $lead['notes']
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <!-- Update Lead -->

            <section
                class="card"
                style="margin-top:22px;"
            >

                <div class="card-head">

                    <h2 class="card-title">
                        Update Lead
                    </h2>

                </div>

                <form
                    method="POST"
                    class="card-body"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(
                            $csrfToken
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="update_lead"
                    >

                    <input
                        type="hidden"
                        name="lead_id"
                        value="<?= (int)$lead['id'] ?>"
                    >

                    <div class="form-group">

                        <label class="form-label">
                            Lead Stage
                        </label>

                        <select
                            name="stage"
                            class="form-control"
                        >

                            <?php foreach (
                                $stages
                                as $stage
                            ): ?>

                                <option
                                    value="<?= e($stage) ?>"
                                    <?= $currentStage === $stage
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= e($stage) ?>
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
                            name="follow_up_date"
                            class="form-control"
                            value="<?= e(
                                $lead[
                                    'follow_up_date'
                                ] ?? ''
                            ) ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label class="form-label">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            class="form-control"
                            placeholder="Add notes about this lead..."
                        ><?= e(
                            $lead['notes'] ?? ''
                        ) ?></textarea>

                    </div>

                    <div class="form-footer">

                        <a
                            href="leads.php"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Save Changes
                        </button>

                    </div>

                </form>

            </section>

        </div>

        <!-- =================================================
             RIGHT
             ================================================= -->

        <div>

            <!-- Quick Actions -->

            <section class="card">

                <div class="card-head">

                    <h2 class="card-title">
                        Quick Actions
                    </h2>

                </div>

                <div class="card-body">

                    <div class="quick-actions">

                        <?php if (
                            !empty(
                                $lead['phone']
                            )
                        ): ?>

                            <a
                                class="quick-action"
                                href="tel:<?= e(
                                    $lead['phone']
                                ) ?>"
                            >
                                📞 Call
                            </a>

                        <?php endif; ?>

                        <?php if (
                            !empty(
                                $lead['email']
                            )
                        ): ?>

                            <a
                                class="quick-action"
                                href="mailto:<?= e(
                                    $lead['email']
                                ) ?>"
                            >
                                ✉ Email
                            </a>

                        <?php endif; ?>

                        <a
                            class="quick-action"
                            href="#update-lead"
                            onclick="
                                document
                                .querySelector(
                                    'input[name=follow_up_date]'
                                )
                                .focus();
                            "
                        >
                            📅 Follow-up
                        </a>

                        <a
                            class="quick-action"
                            href="pipeline.php"
                        >
                            ◇ Pipeline
                        </a>

                    </div>

                </div>

            </section>

            <!-- Lead Summary -->

            <section
                class="card"
                style="margin-top:22px;"
            >

                <div class="card-head">

                    <h2 class="card-title">
                        Lead Summary
                    </h2>

                </div>

                <div class="card-body">

                    <div class="status-row">

                        <span class="status-label">
                            Lead ID
                        </span>

                        <strong>
                            #<?= (int)$lead['id'] ?>
                        </strong>

                    </div>

                    <div class="status-row">

                        <span class="status-label">
                            Source
                        </span>

                        <strong>
                            <?= e(
                                $lead['source']
                                ?: 'Direct'
                            ) ?>
                        </strong>

                    </div>

                    <div class="status-row">

                        <span class="status-label">
                            Stage
                        </span>

                        <span class="
                            badge
                            <?= e($stageClass) ?>
                        ">
                            <?= e(
                                $currentStage
                            ) ?>
                        </span>

                    </div>

                    <div class="status-row">

                        <span class="status-label">
                            Created
                        </span>

                        <strong>
                            <?= !empty(
                                $lead['created_at']
                            )
                                ? e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $lead['created_at']
                                        )
                                    )
                                )
                                : '—'
                            ?>
                        </strong>

                    </div>

                </div>

            </section>

            <!-- Activity -->

            <section
                class="card"
                style="margin-top:22px;"
            >

                <div class="card-head">

                    <h2 class="card-title">
                        Activity
                    </h2>

                </div>

                <div class="card-body">

                    <div class="timeline">

                        <div class="timeline-item">

                            <div class="timeline-title">
                                Lead Created
                            </div>

                            <div class="timeline-date">

                                <?= !empty(
                                    $lead['created_at']
                                )
                                    ? e(
                                        date(
                                            'd M Y, h:i A',
                                            strtotime(
                                                $lead['created_at']
                                            )
                                        )
                                    )
                                    : '—'
                                ?>

                            </div>

                        </div>

                        <div class="timeline-item">

                            <div class="timeline-title">
                                Currently <?= e(
                                    $currentStage
                                ) ?>
                            </div>

                            <div class="timeline-date">
                                Current lead stage
                            </div>

                        </div>

                        <?php if (
                            !empty(
                                $lead['follow_up_date']
                            )
                        ): ?>

                            <div class="timeline-item">

                                <div class="timeline-title">
                                    Follow-up Scheduled
                                </div>

                                <div class="timeline-date">

                                    <?= e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $lead[
                                                    'follow_up_date'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </section>

        </div>

    </div>

</main>

</body>
</html>