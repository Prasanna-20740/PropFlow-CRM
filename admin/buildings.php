<?php
/* =========================================================
   PROPFlow CRM - Building Management
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
        "Location: buildings.php?" .
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
       ADD BUILDING
       ===================================================== */

    if ($action === 'add_building') {

        $projectId = (int)($_POST['project_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $floors = (int)($_POST['floors'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($projectId <= 0) {
            redirectWithMessage('error', 'Please select a project.');
        }

        if ($name === '') {
            redirectWithMessage('error', 'Building name is required.');
        }

        if ($floors <= 0) {
            redirectWithMessage('error', 'Floors must be greater than 0.');
        }

        try {

            /* Check project */
            $projectStmt = $pdo->prepare(" SELECT id
                FROM projects
                WHERE id = ?
                LIMIT 1
            ");

            $projectStmt->execute([$projectId]);

            if (!$projectStmt->fetch()) {
                redirectWithMessage('error', 'Selected project does not exist.');
            }

            /* Duplicate building check */
            $duplicateStmt = $pdo->prepare(" SELECT id
                FROM buildings
                WHERE project_id = ?
                  AND name = ?
                LIMIT 1
            ");

            $duplicateStmt->execute([
                $projectId,
                $name
            ]);

            if ($duplicateStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'A building with this name already exists in the selected project.'
                );
            }

            /* Insert */
            $stmt = $pdo->prepare(" INSERT INTO buildings
                (
                    project_id,
                    name,
                    floors,
                    description
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $projectId,
                $name,
                $floors,
                $description !== '' ? $description : null
            ]);

            redirectWithMessage(
                'success',
                'Building added successfully.'
            );

        } catch (Throwable $e) {

            redirectWithMessage(
                'error',
                'Unable to add building. Please try again.'
            );
        }
    }

    /* =====================================================
       EDIT BUILDING
       ===================================================== */

    if ($action === 'edit_building') {

        $buildingId = (int)($_POST['building_id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $floors = (int)($_POST['floors'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($buildingId <= 0) {
            redirectWithMessage('error', 'Invalid building.');
        }

        if ($projectId <= 0) {
            redirectWithMessage('error', 'Please select a project.');
        }

        if ($name === '') {
            redirectWithMessage('error', 'Building name is required.');
        }

        if ($floors <= 0) {
            redirectWithMessage('error', 'Floors must be greater than 0.');
        }

        try {

            /* Check building */
            $checkStmt = $pdo->prepare(" SELECT id
                FROM buildings
                WHERE id = ?
                LIMIT 1
            ");

            $checkStmt->execute([$buildingId]);

            if (!$checkStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'Building not found.'
                );
            }

            /* Check project */
            $projectStmt = $pdo->prepare(" SELECT id
                FROM projects
                WHERE id = ?
                LIMIT 1
            ");

            $projectStmt->execute([$projectId]);

            if (!$projectStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'Selected project does not exist.'
                );
            }

            /* Duplicate check excluding current building */
            $duplicateStmt = $pdo->prepare(" SELECT id
                FROM buildings
                WHERE project_id = ?
                  AND name = ?
                  AND id != ?
                LIMIT 1
            ");

            $duplicateStmt->execute([
                $projectId,
                $name,
                $buildingId
            ]);

            if ($duplicateStmt->fetch()) {
                redirectWithMessage(
                    'error',
                    'Another building with this name already exists.'
                );
            }

            /* Update */
            $stmt = $pdo->prepare(" UPDATE buildings
                SET
                    project_id = ?,
                    name = ?,
                    floors = ?,
                    description = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $projectId,
                $name,
                $floors,
                $description !== '' ? $description : null,
                $buildingId
            ]);

            redirectWithMessage(
                'success',
                'Building updated successfully.'
            );

        } catch (Throwable $e) {

            redirectWithMessage(
                'error',
                'Unable to update building. Please try again.'
            );
        }
    }

    /* =====================================================
       DELETE BUILDING
       ===================================================== */

    if ($action === 'delete_building') {

        $buildingId = (int)($_POST['building_id'] ?? 0);

        if ($buildingId <= 0) {
            redirectWithMessage(
                'error',
                'Invalid building.'
            );
        }

        try {

            /* Check units */
            $unitStmt = $pdo->prepare(" SELECT COUNT(*)
                FROM units
                WHERE building_id = ?
            ");

            $unitStmt->execute([$buildingId]);

            $unitCount = (int)$unitStmt->fetchColumn();

            if ($unitCount > 0) {

                redirectWithMessage(
                    'error',
                    'This building contains ' .
                    $unitCount .
                    ' unit(s). Remove the units before deleting the building.'
                );
            }

            $deleteStmt = $pdo->prepare(" DELETE FROM buildings
                WHERE id = ?
            ");

            $deleteStmt->execute([$buildingId]);

            if ($deleteStmt->rowCount() === 0) {
                redirectWithMessage(
                    'error',
                    'Building not found.'
                );
            }

            redirectWithMessage(
                'success',
                'Building deleted successfully.'
            );

        } catch (Throwable $e) {

            redirectWithMessage(
                'error',
                'Unable to delete this building.'
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

$totalBuildings = (int)$pdo->query(" SELECT COUNT(*)
    FROM buildings
")->fetchColumn();

$totalProjects = (int)$pdo->query(" SELECT COUNT(*)
    FROM projects
")->fetchColumn();

$totalFloors = (int)$pdo->query(" SELECT COALESCE(SUM(floors), 0)
    FROM buildings
")->fetchColumn();

$totalUnits = (int)$pdo->query(" SELECT COUNT(*)
    FROM units
")->fetchColumn();

/* =========================================================
   PROJECTS
   ========================================================= */

$projectsStmt = $pdo->query(" SELECT
        id,
        name
    FROM projects
    ORDER BY name ASC
");

$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   BUILDINGS
   ========================================================= */

$buildingsStmt = $pdo->query(" SELECT
        b.id,
        b.project_id,
        b.name,
        b.floors,
        b.description,
        p.name AS project_name,
        p.location AS project_location,
        COUNT(u.id) AS unit_count
    FROM buildings b

    INNER JOIN projects p
        ON p.id = b.project_id

    LEFT JOIN units u
        ON u.building_id = b.id

    GROUP BY
        b.id,
        b.project_id,
        b.name,
        b.floors,
        b.description,
        p.name,
        p.location

    ORDER BY
        b.id DESC
");

$buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Buildings | PropFlow CRM</title>

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
   SEARCH
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

.unit-badge {
    display: inline-flex;

    align-items: center;

    padding: 6px 10px;

    border-radius: 999px;

    background: #edf4ff;

    color: #2864e8;

    font-size: 12px;

    font-weight: 700;
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

textarea.form-control {
    height: 100px;

    padding-top: 12px;

    resize: vertical;
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

        <div class="brand-icon">🏢</div>

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

        <a href="buildings.php" class="active">
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
                Building Management
            </h1>

            <p class="page-subtitle">
                Manage buildings and organize them under projects.
            </p>

        </div>

        <button
            class="btn btn-primary"
            type="button"
            onclick="openAddModal()"
        >
            + Add Building
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
                Total Buildings
            </div>

            <div class="stat-value">
                <?= number_format($totalBuildings) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Projects
            </div>

            <div class="stat-value">
                <?= number_format($totalProjects) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Total Floors
            </div>

            <div class="stat-value">
                <?= number_format($totalFloors) ?>
            </div>

        </div>


        <div class="stat">

            <div class="stat-label">
                Total Units
            </div>

            <div class="stat-value">
                <?= number_format($totalUnits) ?>
            </div>

        </div>

    </section>


    <!-- ===================================================
         BUILDINGS
         =================================================== -->

    <section class="card">

        <div class="filters">

            <div class="search-box">

                <span class="search-icon">
                    ⌕
                </span>

                <input
                    type="text"
                    id="buildingSearch"
                    class="filter-control"
                    placeholder="Search building, project, location..."
                    oninput="filterBuildings()"
                >

            </div>

        </div>


        <div class="card-head">

            <h2 class="card-title">
                Buildings
            </h2>

            <div class="card-count">
                <?= count($buildings) ?> building(s)
            </div>

        </div>


        <?php if (empty($buildings)): ?>

            <div class="empty">

                <div class="empty-icon">
                    🏢
                </div>

                <div class="empty-title">
                    No buildings yet
                </div>

                <div>
                    Add your first building to a project.
                </div>

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Building
                            </th>

                            <th>
                                Project
                            </th>

                            <th>
                                Floors
                            </th>

                            <th>
                                Units
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($buildings as $building): ?>

                        <tr
                            class="building-row"
                            data-search="<?= e(
                                strtolower(
                                    ($building['name'] ?? '') . ' ' .
                                    ($building['project_name'] ?? '') . ' ' .
                                    ($building['project_location'] ?? '')
                                )
                            ) ?>"
                        >

                            <td>

                                <div class="primary-text">
                                    <?= e($building['name']) ?>
                                </div>

                                <div class="secondary-text">
                                    Building #<?= e($building['id']) ?>
                                </div>

                            </td>


                            <td>

                                <div class="primary-text">
                                    <?= e($building['project_name']) ?>
                                </div>

                                <div class="secondary-text">
                                    <?= e($building['project_location']) ?>
                                </div>

                            </td>


                            <td>

                                <span class="unit-badge">
                                    <?= number_format(
                                        (int)$building['floors']
                                    ) ?>
                                    Floors
                                </span>

                            </td>


                            <td>

                                <div class="primary-text">
                                    <?= number_format(
                                        (int)$building['unit_count']
                                    ) ?>
                                </div>

                                <div class="secondary-text">
                                    Units
                                </div>

                            </td>


                            <td>

                                <div class="secondary-text">
                                    <?php
                                    $desc = trim(
                                        $building['description'] ?? ''
                                    );

                                    if ($desc === '') {
                                        echo 'No description';
                                    } elseif (strlen($desc) > 55) {
                                        echo e(
                                            substr($desc, 0, 55)
                                        ) . '...';
                                    } else {
                                        echo e($desc);
                                    }
                                    ?>
                                </div>

                            </td>


                            <td>

                                <div class="action-group">

                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        onclick='openEditModal(
                                            <?= json_encode(
                                                (int)$building["id"]
                                            ) ?>,
                                            <?= json_encode(
                                                (int)$building["project_id"]
                                            ) ?>,
                                            <?= json_encode(
                                                $building["name"]
                                            ) ?>,
                                            <?= json_encode(
                                                (int)$building["floors"]
                                            ) ?>,
                                            <?= json_encode(
                                                $building["description"] ?? ""
                                            ) ?>
                                        )'
                                    >
                                        Edit
                                    </button>


                                    <form
                                        method="POST"
                                        onsubmit="return confirm(
                                            'Are you sure you want to delete this building?'
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
                                            value="delete_building"
                                        >

                                        <input
                                            type="hidden"
                                            name="building_id"
                                            value="<?= e(
                                                $building['id']
                                            ) ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >
                                            Delete
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

    </section>

</main>


<!-- =======================================================
     ADD / EDIT MODAL
     ======================================================= -->

<div
    class="modal"
    id="buildingModal"
>

    <div class="modal-box">

        <div class="modal-head">

            <h2
                class="modal-title"
                id="modalTitle"
            >
                Add New Building
            </h2>

            <button
                type="button"
                class="close"
                onclick="closeModal()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            id="buildingForm"
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
                    id="formAction"
                    value="add_building"
                >

                <input
                    type="hidden"
                    name="building_id"
                    id="buildingId"
                    value=""
                >


                <!-- PROJECT -->

                <div class="form-group">

                    <label class="form-label">
                        Project
                    </label>

                    <select
                        name="project_id"
                        id="projectId"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select Project
                        </option>

                        <?php foreach ($projects as $project): ?>

                            <option
                                value="<?= e($project['id']) ?>"
                            >
                                <?= e($project['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BUILDING NAME -->

                <div class="form-group">

                    <label class="form-label">
                        Building Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="buildingName"
                        class="form-control"
                        placeholder="Enter building name"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- FLOORS -->

                <div class="form-group">

                    <label class="form-label">
                        Number of Floors
                    </label>

                    <input
                        type="number"
                        name="floors"
                        id="buildingFloors"
                        class="form-control"
                        placeholder="Enter number of floors"
                        min="1"
                        max="1000"
                        required
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group">

                    <label class="form-label">
                        Description
                    </label>

                    <textarea
                        name="description"
                        id="buildingDescription"
                        class="form-control"
                        placeholder="Enter building description..."
                    ></textarea>

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
                    id="submitButton"
                >
                    Add Building
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/* =========================================================
   ADD MODAL
   ========================================================= */

function openAddModal() {

    document.getElementById('modalTitle').textContent =
        'Add New Building';

    document.getElementById('formAction').value =
        'add_building';

    document.getElementById('buildingId').value =
        '';

    document.getElementById('projectId').value =
        '';

    document.getElementById('buildingName').value =
        '';

    document.getElementById('buildingFloors').value =
        '';

    document.getElementById('buildingDescription').value =
        '';

    document.getElementById('submitButton').textContent =
        'Add Building';

    document
        .getElementById('buildingModal')
        .classList
        .add('show');
}


/* =========================================================
   EDIT MODAL
   ========================================================= */

function openEditModal(
    id,
    projectId,
    name,
    floors,
    description
) {

    document.getElementById('modalTitle').textContent =
        'Edit Building';

    document.getElementById('formAction').value =
        'edit_building';

    document.getElementById('buildingId').value =
        id;

    document.getElementById('projectId').value =
        projectId;

    document.getElementById('buildingName').value =
        name;

    document.getElementById('buildingFloors').value =
        floors;

    document.getElementById('buildingDescription').value =
        description || '';

    document.getElementById('submitButton').textContent =
        'Update Building';

    document
        .getElementById('buildingModal')
        .classList
        .add('show');
}


/* =========================================================
   CLOSE MODAL
   ========================================================= */

function closeModal() {

    document
        .getElementById('buildingModal')
        .classList
        .remove('show');
}


/* =========================================================
   CLICK OUTSIDE
   ========================================================= */

document
    .getElementById('buildingModal')
    .addEventListener(
        'click',
        function(event) {

            if (event.target === this) {
                closeModal();
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
            closeModal();
        }

    }
);


/* =========================================================
   SEARCH
   ========================================================= */

function filterBuildings() {

    const search =
        document
            .getElementById('buildingSearch')
            .value
            .trim()
            .toLowerCase();

    document
        .querySelectorAll('.building-row')
        .forEach(function(row) {

            const rowSearch =
                row.getAttribute('data-search') || '';

            row.style.display =
                search === '' ||
                rowSearch.includes(search)
                    ? ''
                    : 'none';

        });
}

</script>

</body>
</html>