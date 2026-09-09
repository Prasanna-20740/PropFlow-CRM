<?php
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

function pf_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function stageClass($stage)
{
    switch (strtolower(trim($stage))) {
        case 'new':
            return 'new';

        case 'contacted':
            return 'contacted';

        case 'site visit':
            return 'site-visit';

        case 'interested':
            return 'interested';

        case 'negotiation':
            return 'negotiation';

        case 'booked':
            return 'booked';

        case 'lost':
            return 'lost';

        default:
            return 'new';
    }
}

function normalizeStage($stage)
{
    switch (strtolower(trim($stage))) {
        case 'new':
            return 'New';

        case 'contacted':
            return 'Contacted';

        case 'site visit':
            return 'Site Visit';

        case 'interested':
            return 'Interested';

        case 'negotiation':
            return 'Negotiation';

        case 'booked':
            return 'Booked';

        case 'lost':
            return 'Lost';

        default:
            return '';
    }
}

/* -------------------------------------------------------
   UPDATE LEAD STAGE
------------------------------------------------------- */

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $postedToken)) {
        $message = 'Invalid security token. Please refresh the page.';
        $messageType = 'error';
    } else {

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $newStage = normalizeStage($_POST['stage'] ?? '');

        $allowedStages = array(
            'New',
            'Contacted',
            'Site Visit',
            'Interested',
            'Negotiation',
            'Booked',
            'Lost'
        );

        if ($leadId <= 0 || !in_array($newStage, $allowedStages, true)) {

            $message = 'Invalid lead or stage.';
            $messageType = 'error';

        } else {

            try {

                /*
                 * Important:
                 * Sales user can update ONLY their own leads.
                 */
                $stmt = $pdo->prepare("
                    UPDATE leads
                    SET stage = ?
                    WHERE id = ?
                    AND assigned_to = ?
                ");

                $stmt->execute(array(
                    $newStage,
                    $leadId,
                    $currentUserId
                ));

                if ($stmt->rowCount() > 0) {
                    $message = 'Lead stage updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Lead was not found or is not assigned to you.';
                    $messageType = 'error';
                }

            } catch (PDOException $e) {

                $message = 'Unable to update lead stage.';
                $messageType = 'error';
            }
        }
    }
}

/* -------------------------------------------------------
   FETCH SALES LEADS
------------------------------------------------------- */

$stages = array(
    'New',
    'Contacted',
    'Site Visit',
    'Interested',
    'Negotiation',
    'Booked',
    'Lost'
);

$pipeline = array();

foreach ($stages as $stage) {
    $pipeline[$stage] = array();
}

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            phone,
            email,
            source,
            stage,
            follow_up_date,
            notes,
            created_at
        FROM leads
        WHERE assigned_to = ?
        ORDER BY created_at DESC
    ");

    $stmt->execute(array($currentUserId));

    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($leads as $lead) {

        $leadStage = normalizeStage($lead['stage'] ?? '');

        if ($leadStage === '') {
            $leadStage = 'New';
        }

        if (!isset($pipeline[$leadStage])) {
            $pipeline[$leadStage] = array();
        }

        $pipeline[$leadStage][] = $lead;
    }

} catch (PDOException $e) {

    $leads = array();

    foreach ($stages as $stage) {
        $pipeline[$stage] = array();
    }
}

/* -------------------------------------------------------
   COUNTS
------------------------------------------------------- */

$totalLeads = count($leads);

$activeLeads = 0;
$bookedLeads = 0;
$lostLeads = 0;

foreach ($leads as $lead) {

    $stage = strtolower(trim($lead['stage'] ?? ''));

    if ($stage === 'booked') {
        $bookedLeads++;
    } elseif ($stage === 'lost') {
        $lostLeads++;
    } else {
        $activeLeads++;
    }
}

function formatFollowUp($date)
{
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '';
    }

    return date('d M Y', $timestamp);
}

function followUpStatus($date)
{
    if (empty($date)) {
        return '';
    }

    $today = date('Y-m-d');

    if ($date < $today) {
        return 'overdue';
    }

    if ($date === $today) {
        return 'today';
    }

    return 'upcoming';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pipeline | PropFlow CRM</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: #f5f7fb;
            color: #172033;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* =========================
           LAYOUT
        ========================= */

        .app {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar CSS is loaded from sidebar.php include */

        /* =========================
           MAIN
        ========================= */

        .main {
            margin-left: 272px;
            width: calc(100% - 272px);
            min-width: 0;
        }

        .topbar {
            height: 72px;
            background: #fff;
            border-bottom: 1px solid #e8ebf1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .page-title {
            font-size: 21px;
            font-weight: 750;
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            margin-top: 3px;
            color: #8a94a6;
            font-size: 12px;
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
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
        }

        .profile-name {
            font-size: 13px;
            font-weight: 650;
        }

        .profile-role {
            color: #8a94a6;
            font-size: 11px;
            margin-top: 2px;
        }

        .content {
            padding: 27px 30px 40px;
        }

        /* =========================
           ALERT
        ========================= */

        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .alert.success {
            background: #ecfdf3;
            color: #087443;
            border: 1px solid #c7f0da;
        }

        .alert.error {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        /* =========================
           STATS
        ========================= */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 24px;
        }

        .stat {
            background: #fff;
            border: 1px solid #e8ebf1;
            border-radius: 13px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.025);
        }

        .stat-label {
            color: #8993a5;
            font-size: 11px;
            font-weight: 650;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .stat-value {
            margin-top: 8px;
            font-size: 25px;
            font-weight: 780;
            letter-spacing: -1px;
        }

        /* =========================
           PIPELINE HEADER
        ========================= */

        .pipeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .pipeline-heading {
            font-size: 16px;
            font-weight: 720;
        }

        .pipeline-info {
            color: #8a94a6;
            font-size: 12px;
        }

        /* =========================
           KANBAN
        ========================= */

        .kanban-wrapper {
            overflow-x: auto;
            padding-bottom: 12px;
        }

        .kanban {
            display: grid;
            grid-template-columns: repeat(7, minmax(230px, 1fr));
            gap: 13px;
            min-width: 1660px;
        }

        .column {
            background: #edf1f6;
            border-radius: 13px;
            min-height: 430px;
            overflow: hidden;
        }

        .column-header {
            padding: 14px 13px;
            background: #e8edf4;
            border-bottom: 1px solid #dde3ec;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .column-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 750;
        }

        .stage-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot-new {
            background: #64748b;
        }

        .dot-contacted {
            background: #2563eb;
        }

        .dot-site {
            background: #8b5cf6;
        }

        .dot-interested {
            background: #f59e0b;
        }

        .dot-negotiation {
            background: #ec4899;
        }

        .dot-booked {
            background: #10b981;
        }

        .dot-lost {
            background: #ef4444;
        }

        .count {
            min-width: 23px;
            height: 23px;
            padding: 0 7px;
            background: #fff;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667085;
            font-size: 10px;
            font-weight: 750;
        }

        .cards {
            padding: 10px;
        }

        /* =========================
           LEAD CARD
        ========================= */

        .lead-card {
            background: #fff;
            border: 1px solid #e4e8ef;
            border-radius: 10px;
            padding: 13px;
            margin-bottom: 9px;
            box-shadow: 0 2px 7px rgba(15, 23, 42, .035);
            transition: .2s ease;
        }

        .lead-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(15, 23, 42, .08);
        }

        .lead-name {
            font-size: 13px;
            font-weight: 720;
            margin-bottom: 7px;
            color: #172033;
        }

        .lead-phone {
            color: #657084;
            font-size: 11px;
            margin-bottom: 4px;
        }

        .lead-source {
            display: inline-flex;
            padding: 4px 7px;
            border-radius: 5px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            margin-top: 5px;
        }

        .followup {
            margin-top: 10px;
            padding-top: 9px;
            border-top: 1px solid #eef0f4;
            font-size: 10px;
            color: #697386;
        }

        .followup.overdue {
            color: #dc2626;
            font-weight: 700;
        }

        .followup.today {
            color: #d97706;
            font-weight: 700;
        }

        .followup.upcoming {
            color: #2563eb;
        }

        .card-actions {
            display: flex;
            gap: 6px;
            margin-top: 11px;
        }

        .view-btn {
            flex: 1;
            border: 1px solid #e2e6ed;
            background: #fff;
            border-radius: 6px;
            padding: 7px 5px;
            text-align: center;
            font-size: 10px;
            font-weight: 650;
            color: #475467;
        }

        .view-btn:hover {
            background: #f8fafc;
        }

        .stage-form {
            flex: 1;
        }

        .stage-select {
            width: 100%;
            border: 1px solid #dfe4ec;
            background: #f8fafc;
            border-radius: 6px;
            padding: 6px 5px;
            font-size: 9px;
            color: #475467;
            cursor: pointer;
            outline: none;
        }

        .stage-select:focus {
            border-color: #2563eb;
        }

        /* =========================
           EMPTY
        ========================= */

        .empty {
            padding: 35px 10px;
            text-align: center;
            color: #98a2b3;
            font-size: 10px;
        }

        .empty-icon {
            font-size: 24px;
            margin-bottom: 7px;
            opacity: .5;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1024px) {

            .main {
                margin-left: 0;
                width: 100%;
            }

            .topbar {
                padding-left: 60px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .content {
                padding: 22px 18px;
            }
        }

        @media (max-width: 600px) {

            .topbar {
                padding: 0 16px 0 60px;
            }

            .profile-name,
            .profile-role {
                display: none;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat {
                padding: 14px;
            }

            .stat-value {
                font-size: 21px;
            }
        }

    </style>

</head>

<body>

<div class="app">

    <?php $activePage = 'pipeline'; include 'sidebar.php'; ?>


    <!-- MAIN -->
    <main class="main">

        <!-- TOPBAR -->
        <header class="topbar">

            <div>
                <div class="page-title">Sales Pipeline</div>
                <div class="page-subtitle">
                    Track and manage your leads by stage
                </div>
            </div>

            <div class="profile">

                <div class="avatar">
                    <?php
                    $name = trim($user['name'] ?? 'Sales');
                    echo pf_e(strtoupper(substr($name, 0, 1)));
                    ?>
                </div>

                <div>
                    <div class="profile-name">
                        <?php echo pf_e($name); ?>
                    </div>

                    <div class="profile-role">
                        Sales Executive
                    </div>
                </div>

            </div>

        </header>


        <!-- CONTENT -->
        <section class="content">

            <?php if ($message !== ''): ?>

                <div class="alert <?php echo pf_e($messageType); ?>">
                    <?php echo pf_e($message); ?>
                </div>

            <?php endif; ?>


            <!-- STATS -->
            <div class="stats">

                <div class="stat">
                    <div class="stat-label">Total Leads</div>
                    <div class="stat-value">
                        <?php echo $totalLeads; ?>
                    </div>
                </div>

                <div class="stat">
                    <div class="stat-label">Active Leads</div>
                    <div class="stat-value">
                        <?php echo $activeLeads; ?>
                    </div>
                </div>

                <div class="stat">
                    <div class="stat-label">Booked</div>
                    <div class="stat-value">
                        <?php echo $bookedLeads; ?>
                    </div>
                </div>

                <div class="stat">
                    <div class="stat-label">Lost</div>
                    <div class="stat-value">
                        <?php echo $lostLeads; ?>
                    </div>
                </div>

            </div>


            <!-- PIPELINE TITLE -->
            <div class="pipeline-header">

                <div>
                    <div class="pipeline-heading">
                        Lead Pipeline
                    </div>

                    <div class="pipeline-info">
                        Move leads through each stage as you progress
                    </div>
                </div>

            </div>


            <!-- KANBAN -->
            <div class="kanban-wrapper">

                <div class="kanban">

                    <?php foreach ($stages as $stage): ?>

                        <?php
                        $stageLeads = $pipeline[$stage];
                        $class = stageClass($stage);

                        $dotClass = 'dot-new';

                        switch ($stage) {

                            case 'Contacted':
                                $dotClass = 'dot-contacted';
                                break;

                            case 'Site Visit':
                                $dotClass = 'dot-site';
                                break;

                            case 'Interested':
                                $dotClass = 'dot-interested';
                                break;

                            case 'Negotiation':
                                $dotClass = 'dot-negotiation';
                                break;

                            case 'Booked':
                                $dotClass = 'dot-booked';
                                break;

                            case 'Lost':
                                $dotClass = 'dot-lost';
                                break;
                        }
                        ?>

                        <div class="column">

                            <div class="column-header">

                                <div class="column-title">

                                    <span class="stage-dot <?php echo $dotClass; ?>"></span>

                                    <?php echo pf_e($stage); ?>

                                </div>

                                <span class="count">
                                    <?php echo count($stageLeads); ?>
                                </span>

                            </div>


                            <div class="cards">

                                <?php if (empty($stageLeads)): ?>

                                    <div class="empty">

                                        <div class="empty-icon">
                                            ○
                                        </div>

                                        No leads

                                    </div>

                                <?php else: ?>


                                    <?php foreach ($stageLeads as $lead): ?>

                                        <?php
                                        $followStatus = followUpStatus(
                                            $lead['follow_up_date'] ?? ''
                                        );
                                        ?>

                                        <div class="lead-card">

                                            <div class="lead-name">
                                                <?php echo pf_e($lead['name']); ?>
                                            </div>


                                            <?php if (!empty($lead['phone'])): ?>

                                                <div class="lead-phone">
                                                    ☎
                                                    <?php echo pf_e($lead['phone']); ?>
                                                </div>

                                            <?php endif; ?>


                                            <?php if (!empty($lead['email'])): ?>

                                                <div class="lead-phone">
                                                    ✉
                                                    <?php echo pf_e($lead['email']); ?>
                                                </div>

                                            <?php endif; ?>


                                            <?php if (!empty($lead['source'])): ?>

                                                <span class="lead-source">
                                                    <?php echo pf_e($lead['source']); ?>
                                                </span>

                                            <?php endif; ?>


                                            <?php if (!empty($lead['follow_up_date'])): ?>

                                                <div class="followup <?php echo $followStatus; ?>">

                                                    <?php
                                                    if ($followStatus === 'overdue') {
                                                        echo '⚠ Overdue · ';
                                                    } elseif ($followStatus === 'today') {
                                                        echo '● Today · ';
                                                    } else {
                                                        echo '◷ ';
                                                    }
                                                    ?>

                                                    <?php echo pf_e(
                                                        formatFollowUp($lead['follow_up_date'])
                                                    ); ?>

                                                </div>

                                            <?php endif; ?>


                                            <div class="card-actions">

                                                <a
                                                    href="lead-view.php?id=<?php echo (int)$lead['id']; ?>"
                                                    class="view-btn"
                                                >
                                                    View
                                                </a>


                                                <form
                                                    method="POST"
                                                    class="stage-form"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?php echo pf_e($csrfToken); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="lead_id"
                                                        value="<?php echo (int)$lead['id']; ?>"
                                                    >

                                                    <select
                                                        name="stage"
                                                        class="stage-select"
                                                        onchange="this.form.submit()"
                                                    >

                                                        <?php foreach ($stages as $option): ?>

                                                            <option
                                                                value="<?php echo pf_e($option); ?>"
                                                                <?php
                                                                echo $option === $stage
                                                                    ? 'selected'
                                                                    : '';
                                                                ?>
                                                            >
                                                                <?php echo pf_e($option); ?>
                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>

                                                </form>

                                            </div>

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

</div>

</body>
</html>