<?php

require_once "../includes/auth.php";

requireRole("admin");

$user = currentUser();


/* =====================================================
   CSRF
===================================================== */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];

$message = "";
$messageType = "";


/* =====================================================
   UNIT TYPES
===================================================== */

$unitTypes = [
    "1 BHK",
    "2 BHK",
    "3 BHK",
    "4 BHK",
    "Villa",
    "Commercial"
];


/* =====================================================
   UNIT STATUS
===================================================== */

$unitStatuses = [
    "available",
    "reserved",
    "booked",
    "blocked"
];


/* =====================================================
   HANDLE POST
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $postedToken)) {

        $message = "Security validation failed.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";


        /* =================================================
           ADD PROJECT
        ================================================= */

        if ($action === "add_project") {

            $name = trim($_POST["name"] ?? "");
            $location = trim($_POST["location"] ?? "");
            $description = trim($_POST["description"] ?? "");


            if ($name === "") {

                $message = "Project name is required.";
                $messageType = "error";

            } elseif ($location === "") {

                $message = "Project location is required.";
                $messageType = "error";

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO projects
                    (
                        name,
                        location,
                        description
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $location,
                    $description !== "" ? $description : null
                ]);

                $message = "Project created successfully.";
                $messageType = "success";
            }
        }


        /* =================================================
           EDIT PROJECT
        ================================================= */

        elseif ($action === "edit_project") {

            $id = (int)($_POST["id"] ?? 0);

            $name = trim($_POST["name"] ?? "");
            $location = trim($_POST["location"] ?? "");
            $description = trim($_POST["description"] ?? "");


            if ($id <= 0 || $name === "" || $location === "") {

                $message = "Please provide valid project details.";
                $messageType = "error";

            } else {

                $stmt = $pdo->prepare("
                    UPDATE projects
                    SET
                        name = ?,
                        location = ?,
                        description = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $location,
                    $description !== "" ? $description : null,
                    $id
                ]);

                $message = "Project updated successfully.";
                $messageType = "success";
            }
        }


        /* =================================================
           DELETE PROJECT
        ================================================= */

        elseif ($action === "delete_project") {

            $id = (int)($_POST["id"] ?? 0);


            if ($id <= 0) {

                $message = "Invalid project.";
                $messageType = "error";

            } else {

                try {

                    $stmt = $pdo->prepare("
                        DELETE FROM projects
                        WHERE id = ?
                    ");

                    $stmt->execute([$id]);

                    $message = "Project deleted successfully.";
                    $messageType = "success";

                } catch (PDOException $e) {

                    $message =
                        "Project cannot be deleted.";
                    $messageType = "error";
                }
            }
        }


        /* =================================================
           ADD BUILDING
        ================================================= */

        elseif ($action === "add_building") {

            $projectId = (int)($_POST["project_id"] ?? 0);

            $name = trim($_POST["name"] ?? "");

            $floors = (int)($_POST["floors"] ?? 1);

            $description = trim($_POST["description"] ?? "");


            if ($projectId <= 0) {

                $message = "Please select a project.";
                $messageType = "error";

            } elseif ($name === "") {

                $message = "Building name is required.";
                $messageType = "error";

            } elseif ($floors < 1) {

                $message = "Building must have at least one floor.";
                $messageType = "error";

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO buildings
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
                    $description !== "" ? $description : null
                ]);

                $message = "Building created successfully.";
                $messageType = "success";
            }
        }


        /* =================================================
           EDIT BUILDING
        ================================================= */

        elseif ($action === "edit_building") {

            $id = (int)($_POST["id"] ?? 0);

            $projectId = (int)($_POST["project_id"] ?? 0);

            $name = trim($_POST["name"] ?? "");

            $floors = (int)($_POST["floors"] ?? 1);

            $description = trim($_POST["description"] ?? "");


            if (
                $id <= 0 ||
                $projectId <= 0 ||
                $name === "" ||
                $floors < 1
            ) {

                $message = "Please provide valid building details.";
                $messageType = "error";

            } else {

                $stmt = $pdo->prepare("
                    UPDATE buildings
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
                    $description !== "" ? $description : null,
                    $id
                ]);

                $message = "Building updated successfully.";
                $messageType = "success";
            }
        }


        /* =================================================
           DELETE BUILDING
        ================================================= */

        elseif ($action === "delete_building") {

            $id = (int)($_POST["id"] ?? 0);


            if ($id <= 0) {

                $message = "Invalid building.";
                $messageType = "error";

            } else {

                try {

                    $stmt = $pdo->prepare("
                        DELETE FROM buildings
                        WHERE id = ?
                    ");

                    $stmt->execute([$id]);

                    $message = "Building deleted successfully.";
                    $messageType = "success";

                } catch (PDOException $e) {

                    $message =
                        "Building cannot be deleted.";
                    $messageType = "error";
                }
            }
        }


        /* =================================================
           ADD UNIT
        ================================================= */

        elseif ($action === "add_unit") {

            $buildingId =
                (int)($_POST["building_id"] ?? 0);

            $unitNumber =
                trim($_POST["unit_number"] ?? "");

            $type =
                $_POST["type"] ?? "2 BHK";

            $floor =
                (int)($_POST["floor"] ?? 1);

            $price =
                (float)($_POST["price"] ?? 0);

            $status =
                $_POST["status"] ?? "available";

            $description =
                trim($_POST["description"] ?? "");


            if ($buildingId <= 0) {

                $message = "Please select a building.";
                $messageType = "error";

            } elseif ($unitNumber === "") {

                $message = "Unit number is required.";
                $messageType = "error";

            } elseif (!in_array($type, $unitTypes, true)) {

                $message = "Invalid unit type.";
                $messageType = "error";

            } elseif ($floor < 1) {

                $message = "Invalid floor.";
                $messageType = "error";

            } elseif ($price < 0) {

                $message = "Invalid unit price.";
                $messageType = "error";

            } elseif (!in_array($status, $unitStatuses, true)) {

                $message = "Invalid unit status.";
                $messageType = "error";

            } else {

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO units
                        (
                            building_id,
                            unit_number,
                            type,
                            floor,
                            price,
                            status,
                            description
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $buildingId,
                        $unitNumber,
                        $type,
                        $floor,
                        $price,
                        $status,
                        $description !== "" ? $description : null
                    ]);

                    $message = "Unit added successfully.";
                    $messageType = "success";

                } catch (PDOException $e) {

                    if ($e->getCode() === "23000") {

                        $message =
                            "This unit number already exists in the selected building.";

                    } else {

                        $message =
                            "Unable to create unit.";
                    }

                    $messageType = "error";
                }
            }
        }


        /* =================================================
           EDIT UNIT
        ================================================= */

        elseif ($action === "edit_unit") {

            $id =
                (int)($_POST["id"] ?? 0);

            $buildingId =
                (int)($_POST["building_id"] ?? 0);

            $unitNumber =
                trim($_POST["unit_number"] ?? "");

            $type =
                $_POST["type"] ?? "";

            $floor =
                (int)($_POST["floor"] ?? 1);

            $price =
                (float)($_POST["price"] ?? 0);

            $status =
                $_POST["status"] ?? "";

            $description =
                trim($_POST["description"] ?? "");


            if (
                $id <= 0 ||
                $buildingId <= 0 ||
                $unitNumber === ""
            ) {

                $message = "Invalid unit details.";
                $messageType = "error";

            } elseif (!in_array($type, $unitTypes, true)) {

                $message = "Invalid unit type.";
                $messageType = "error";

            } elseif (!in_array($status, $unitStatuses, true)) {

                $message = "Invalid unit status.";
                $messageType = "error";

            } elseif ($floor < 1 || $price < 0) {

                $message = "Invalid floor or price.";
                $messageType = "error";

            } else {

                try {

                    $stmt = $pdo->prepare("
                        UPDATE units
                        SET
                            building_id = ?,
                            unit_number = ?,
                            type = ?,
                            floor = ?,
                            price = ?,
                            status = ?,
                            description = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $buildingId,
                        $unitNumber,
                        $type,
                        $floor,
                        $price,
                        $status,
                        $description !== "" ? $description : null,
                        $id
                    ]);

                    $message = "Unit updated successfully.";
                    $messageType = "success";

                } catch (PDOException $e) {

                    $message =
                        "Unable to update unit.";

                    $messageType = "error";
                }
            }
        }


        /* =================================================
           DELETE UNIT
        ================================================= */

        elseif ($action === "delete_unit") {

            $id =
                (int)($_POST["id"] ?? 0);


            if ($id <= 0) {

                $message = "Invalid unit.";
                $messageType = "error";

            } else {

                try {

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM bookings
                        WHERE unit_id = ?
                    ");

                    $stmt->execute([$id]);

                    $bookingCount =
                        (int)$stmt->fetchColumn();


                    if ($bookingCount > 0) {

                        $message =
                            "This unit has booking history and cannot be deleted.";

                        $messageType = "error";

                    } else {

                        $stmt = $pdo->prepare("
                            DELETE FROM units
                            WHERE id = ?
                        ");

                        $stmt->execute([$id]);

                        $message =
                            "Unit deleted successfully.";

                        $messageType = "success";
                    }

                } catch (PDOException $e) {

                    $message =
                        "Unable to delete unit.";

                    $messageType = "error";
                }
            }
        }
    }
}


/* =====================================================
   FILTER
===================================================== */

$projectFilter =
    (int)($_GET["project"] ?? 0);


/* =====================================================
   PROJECTS
===================================================== */

$stmt = $pdo->query(" SELECT
        p.id,
        p.name,
        p.location,
        p.description,
        p.status,
        p.created_at,

        COUNT(DISTINCT b.id) AS building_count,

        COUNT(DISTINCT u.id) AS unit_count,

        SUM(
            CASE
                WHEN u.status = 'available'
                THEN 1
                ELSE 0
            END
        ) AS available_count,

        SUM(
            CASE
                WHEN u.status = 'booked'
                THEN 1
                ELSE 0
            END
        ) AS booked_count

    FROM projects p

    LEFT JOIN buildings b
        ON p.id = b.project_id

    LEFT JOIN units u
        ON b.id = u.building_id

    GROUP BY p.id

    ORDER BY p.created_at DESC
");

$projects = $stmt->fetchAll();


/* =====================================================
   BUILDINGS
===================================================== */

$buildingSql = " SELECT
        b.id,
        b.project_id,
        b.name,
        b.floors,
        b.description,
        b.status,
        p.name AS project_name,

        COUNT(u.id) AS unit_count,

        SUM(
            CASE
                WHEN u.status = 'available'
                THEN 1
                ELSE 0
            END
        ) AS available_count,

        SUM(
            CASE
                WHEN u.status = 'booked'
                THEN 1
                ELSE 0
            END
        ) AS booked_count

    FROM buildings b

    INNER JOIN projects p
        ON b.project_id = p.id

    LEFT JOIN units u
        ON b.id = u.building_id
";


if ($projectFilter > 0) {

    $buildingSql .= "
        WHERE b.project_id = ?
    ";

    $buildingSql .= "
        GROUP BY b.id
        ORDER BY b.name ASC
    ";

    $stmt = $pdo->prepare($buildingSql);

    $stmt->execute([
        $projectFilter
    ]);

} else {

    $buildingSql .= "
        GROUP BY b.id
        ORDER BY p.name ASC, b.name ASC
    ";

    $stmt = $pdo->query($buildingSql);
}


$buildings = $stmt->fetchAll();


/* =====================================================
   UNITS
===================================================== */

$unitSql = " SELECT
        u.id,
        u.building_id,
        u.unit_number,
        u.type,
        u.floor,
        u.price,
        u.status,
        u.description,

        b.name AS building_name,
        p.name AS project_name

    FROM units u

    INNER JOIN buildings b
        ON u.building_id = b.id

    INNER JOIN projects p
        ON b.project_id = p.id
";


$unitParams = [];


if ($projectFilter > 0) {

    $unitSql .= " WHERE p.id = ?
    ";

    $unitParams[] = $projectFilter;
}


$unitSql .= " ORDER BY
        p.name ASC,
        b.name ASC,
        u.floor ASC,
        u.unit_number ASC
";


$stmt = $pdo->prepare($unitSql);

$stmt->execute($unitParams);

$units = $stmt->fetchAll();


/* =====================================================
   STATISTICS
===================================================== */

$stmt = $pdo->query(" SELECT
        COUNT(*) AS total_units,

        SUM(
            status = 'available'
        ) AS available_units,

        SUM(
            status = 'reserved'
        ) AS reserved_units,

        SUM(
            status = 'booked'
        ) AS booked_units,

        SUM(
            status = 'blocked'
        ) AS blocked_units

    FROM units
");

$unitStats = $stmt->fetch();


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
    Properties | PropFlow CRM
</title>


<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {

    --primary: #2563eb;
    --primary-dark: #1d4ed8;

    --dark: #0f172a;

    --text: #334155;

    --muted: #64748b;

    --border: #e2e8f0;

    --bg: #f8fafc;

    --white: #fff;

    --success: #15803d;

    --danger: #dc2626;

}

body {

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: var(--bg);

    color: var(--text);

    min-height: 100vh;

}


        /* =================================================
           SIDEBAR
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

            border-top: 1px solid rgba(255,255,255,.08);

            padding-top: 15px;
        }


/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left: 245px;

    min-height: 100vh;

}

.topbar {

    height: 72px;

    background: white;

    border-bottom:
        1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: flex-end;

    padding: 0 32px;

}

.profile {

    display: flex;

    align-items: center;

    gap: 10px;

}

.avatar {

    width: 38px;
    height: 38px;

    border-radius: 50%;

    background: #dbeafe;

    color: var(--primary);

    display: flex;

    align-items: center;
    justify-content: center;

    font-weight: 700;

    font-size: 13px;

}

.profile-name {

    color: var(--dark);

    font-size: 13px;

    font-weight: 600;

}

.profile-role {

    color: var(--muted);

    font-size: 11px;

}


/* =====================================================
   CONTENT
===================================================== */

.content {

    padding: 32px;

}

.page-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    margin-bottom: 24px;

}

.page-title h1 {

    color: var(--dark);

    font-size: 27px;

    margin-bottom: 5px;

}

.page-title p {

    color: var(--muted);

    font-size: 13px;

}

.primary-btn {

    border: none;

    background: var(--primary);

    color: white;

    padding: 12px 17px;

    border-radius: 9px;

    cursor: pointer;

    font-size: 12px;

    font-weight: 600;

}

.primary-btn:hover {

    background: var(--primary-dark);

}


/* =====================================================
   MESSAGE
===================================================== */

.message {

    padding: 13px 16px;

    border-radius: 9px;

    margin-bottom: 20px;

    font-size: 12px;

    border: 1px solid;

}

.message.success {

    background: #f0fdf4;

    color: var(--success);

    border-color: #bbf7d0;

}

.message.error {

    background: #fef2f2;

    color: var(--danger);

    border-color: #fecaca;

}


/* =====================================================
   STATS
===================================================== */

.stats {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 13px;

    margin-bottom: 22px;

}

.stat {

    background: white;

    border: 1px solid var(--border);

    border-radius: 13px;

    padding: 17px;

}

.stat-label {

    color: var(--muted);

    font-size: 10px;

    margin-bottom: 7px;

}

.stat-value {

    color: var(--dark);

    font-size: 23px;

    font-weight: 700;

}


/* =====================================================
   TABS
===================================================== */

.tabs {

    display: flex;

    gap: 5px;

    background: white;

    border: 1px solid var(--border);

    border-radius: 11px;

    padding: 5px;

    width: fit-content;

    margin-bottom: 18px;

}

.tab {

    border: none;

    background: transparent;

    color: var(--muted);

    padding: 9px 15px;

    border-radius: 7px;

    cursor: pointer;

    font-size: 11px;

    font-weight: 600;

}

.tab.active {

    background: var(--dark);

    color: white;

}


/* =====================================================
   TAB CONTENT
===================================================== */

.tab-content {

    display: none;

}

.tab-content.active {

    display: block;

}


/* =====================================================
   SECTION HEADER
===================================================== */

.section-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 13px;

}

.section-header h2 {

    color: var(--dark);

    font-size: 15px;

}

.section-header span {

    color: var(--muted);

    font-size: 11px;

}


/* =====================================================
   TABLE
===================================================== */

.table-card {

    background: white;

    border: 1px solid var(--border);

    border-radius: 13px;

    overflow: hidden;

}

.table-wrapper {

    overflow-x: auto;

}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 850px;

}

th {

    background: #fafafa;

    color: #94a3b8;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: .5px;

    text-align: left;

    padding: 13px 18px;

    border-bottom: 1px solid var(--border);

}

td {

    padding: 14px 18px;

    font-size: 11px;

    border-bottom: 1px solid #f1f5f9;

    color: var(--text);

}

tr:last-child td {

    border-bottom: none;

}

.item-name {

    color: var(--dark);

    font-weight: 700;

    margin-bottom: 3px;

}

.item-sub {

    color: #94a3b8;

    font-size: 10px;

}

.badge {

    display: inline-flex;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 600;

}

.badge.available {

    background: #f0fdf4;

    color: #15803d;

}

.badge.reserved {

    background: #fff7ed;

    color: #c2410c;

}

.badge.booked {

    background: #ecfdf5;

    color: #047857;

}

.badge.blocked {

    background: #fef2f2;

    color: #b91c1c;

}

.badge.active {

    background: #eff6ff;

    color: #1d4ed8;

}

.badge.inactive {

    background: #f1f5f9;

    color: #64748b;

}

.actions {

    display: flex;

    gap: 6px;

}

.icon-btn {

    width: 31px;

    height: 31px;

    border: 1px solid var(--border);

    background: white;

    border-radius: 7px;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

}

.icon-btn.delete {

    color: var(--danger);

}


/* =====================================================
   EMPTY
===================================================== */

.empty {

    text-align: center;

    padding: 55px 20px;

    color: #94a3b8;

}

.empty-icon {

    font-size: 30px;

    margin-bottom: 9px;

}

.empty-title {

    color: var(--dark);

    font-size: 14px;

    font-weight: 650;

    margin-bottom: 5px;

}

.empty-text {

    font-size: 11px;

}


/* =====================================================
   MODAL
===================================================== */

.modal {

    position: fixed;

    inset: 0;

    background: rgba(15,23,42,.55);

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    z-index: 1000;

}

.modal.show {

    display: flex;

}

.modal-box {

    width: 100%;

    max-width: 600px;

    max-height: 90vh;

    overflow-y: auto;

    background: white;

    border-radius: 15px;

}

.modal-header {

    padding: 18px 20px;

    border-bottom: 1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;

}

.modal-header h2 {

    color: var(--dark);

    font-size: 16px;

}

.close {

    width: 31px;

    height: 31px;

    border: none;

    background: #f1f5f9;

    border-radius: 7px;

    cursor: pointer;

    font-size: 18px;

}

.modal-body {

    padding: 20px;

}

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

}

.form-group.full {

    grid-column: 1 / -1;

}

label {

    display: block;

    color: #475569;

    font-size: 10px;

    font-weight: 650;

    margin-bottom: 6px;

}

.input,
.select,
.textarea {

    width: 100%;

    height: 41px;

    border: 1px solid var(--border);

    border-radius: 8px;

    padding: 0 11px;

    outline: none;

    color: var(--text);

    background: white;

    font-size: 11px;

}

.textarea {

    height: 90px;

    padding: 11px;

    resize: vertical;

}

.input:focus,
.select:focus,
.textarea:focus {

    border-color: var(--primary);

    box-shadow:
        0 0 0 3px rgba(37,99,235,.08);

}

.modal-footer {

    padding: 15px 20px;

    border-top: 1px solid var(--border);

    display: flex;

    justify-content: flex-end;

    gap: 8px;

}

.cancel-btn {

    height: 40px;

    padding: 0 15px;

    border: 1px solid var(--border);

    background: white;

    color: var(--muted);

    border-radius: 8px;

    cursor: pointer;

    font-size: 11px;

}

.save-btn {

    height: 40px;

    padding: 0 17px;

    border: none;

    background: var(--primary);

    color: white;

    border-radius: 8px;

    cursor: pointer;

    font-size: 11px;

    font-weight: 600;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:1100px) {

    .stats {

        grid-template-columns:
            repeat(3, 1fr);

    }

}

@media(max-width:750px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;

    }

    .brand {

        justify-content: center;

        padding: 5px;

    }

    .brand-name,
    .menu-title,
    .nav a span {

        display: none;

    }

    .nav a {

        justify-content: center;

    }

    .main {

        margin-left: 70px;

    }

    .content {

        padding: 22px 16px;

    }

    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .page-header {

        align-items: flex-start;

        flex-direction: column;

    }

}

@media(max-width:500px) {

    .stats {

        grid-template-columns: 1fr;

    }

    .form-grid {

        grid-template-columns: 1fr;

    }

    .form-group.full {

        grid-column: auto;

    }

    .tabs {

        width: 100%;

        overflow-x: auto;

    }

    .tab {

        white-space: nowrap;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

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

        <a
            href="properties.php"
            class="active"
        >
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


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<header class="topbar">

    <div class="profile">

        <div class="avatar">

            <?= strtoupper(
                substr(
                    $user["name"],
                    0,
                    1
                )
            ) ?>

        </div>

        <div>

            <div class="profile-name">

                <?= htmlspecialchars(
                    $user["name"]
                ) ?>

            </div>

            <div class="profile-role">

                <?= htmlspecialchars(
                    $user["role"]
                ) ?>

            </div>

        </div>

    </div>

</header>


<section class="content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

    <div class="page-title">

        <h1>
            Property Inventory
        </h1>

        <p>
            Manage projects, buildings and available units.
        </p>

    </div>


    <button
        class="primary-btn"
        onclick="openProjectModal()"
    >
        + Add Project
    </button>

</div>


<!-- MESSAGE -->

<?php if ($message !== ""): ?>

    <div class="message <?= $messageType ?>">

        <?= htmlspecialchars($message) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     STATS
===================================================== -->

<div class="stats">

    <div class="stat">

        <div class="stat-label">
            Projects
        </div>

        <div class="stat-value">
            <?= count($projects) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Buildings
        </div>

        <div class="stat-value">
            <?= count($buildings) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Total Units
        </div>

        <div class="stat-value">
            <?= (int)($unitStats["total_units"] ?? 0) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Available
        </div>

        <div class="stat-value">
            <?= (int)($unitStats["available_units"] ?? 0) ?>
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            Booked
        </div>

        <div class="stat-value">
            <?= (int)($unitStats["booked_units"] ?? 0) ?>
        </div>

    </div>

</div>


<!-- =====================================================
     TABS
===================================================== -->

<div class="tabs">

    <button
        class="tab active"
        onclick="showTab('projects', this)"
    >
        Projects
    </button>

    <button
        class="tab"
        onclick="showTab('buildings', this)"
    >
        Buildings
    </button>

    <button
        class="tab"
        onclick="showTab('units', this)"
    >
        Units
    </button>

</div>


<!-- =====================================================
     PROJECTS
===================================================== -->

<div
    id="projects"
    class="tab-content active"
>


<div class="section-header">

    <h2>
        Projects
    </h2>

    <span>
        <?= count($projects) ?> project(s)
    </span>

</div>


<div class="table-card">

<?php if (empty($projects)): ?>

    <div class="empty">

        <div class="empty-icon">
            🏙️
        </div>

        <div class="empty-title">
            No projects yet
        </div>

        <div class="empty-text">
            Create your first real estate project.
        </div>

    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>Project</th>

<th>Location</th>

<th>Buildings</th>

<th>Units</th>

<th>Available</th>

<th>Booked</th>

<th>Actions</th>

</tr>

</thead>


<tbody>

<?php foreach ($projects as $project): ?>

<tr>

<td>

    <div class="item-name">

        <?= htmlspecialchars(
            $project["name"]
        ) ?>

    </div>

    <div class="item-sub">

        Project #<?= (int)$project["id"] ?>

    </div>

</td>


<td>

    <?= htmlspecialchars(
        $project["location"]
    ) ?>

</td>


<td>

    <?= (int)$project["building_count"] ?>

</td>


<td>

    <?= (int)$project["unit_count"] ?>

</td>


<td>

    <span class="badge available">

        <?= (int)$project["available_count"] ?>

    </span>

</td>


<td>

    <span class="badge booked">

        <?= (int)$project["booked_count"] ?>

    </span>

</td>


<td>

<div class="actions">


<button
    class="icon-btn"
    title="Edit"
    onclick='editProject(
        <?= json_encode(
            $project,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>
    )'
>
    ✏️
</button>


<form
    method="POST"
    onsubmit="return confirm(
        'Delete this project? All related buildings and units will also be deleted.'
    )"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrfToken) ?>"
>

<input
    type="hidden"
    name="action"
    value="delete_project"
>

<input
    type="hidden"
    name="id"
    value="<?= (int)$project["id"] ?>"
>


<button
    class="icon-btn delete"
    type="submit"
    title="Delete"
>
    🗑️
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

</div>

</div>


<!-- =====================================================
     BUILDINGS
===================================================== -->

<div
    id="buildings"
    class="tab-content"
>


<div class="section-header">

    <h2>
        Buildings
    </h2>


    <button
        class="primary-btn"
        onclick="openBuildingModal()"
    >
        + Add Building
    </button>

</div>


<div class="table-card">

<?php if (empty($buildings)): ?>

    <div class="empty">

        <div class="empty-icon">
            🏢
        </div>

        <div class="empty-title">
            No buildings yet
        </div>

        <div class="empty-text">
            Add a building under a project.
        </div>

    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>Building</th>

<th>Project</th>

<th>Floors</th>

<th>Units</th>

<th>Available</th>

<th>Booked</th>

<th>Actions</th>

</tr>

</thead>


<tbody>

<?php foreach ($buildings as $building): ?>

<tr>

<td>

    <div class="item-name">

        <?= htmlspecialchars(
            $building["name"]
        ) ?>

    </div>

    <div class="item-sub">

        Building #<?= (int)$building["id"] ?>

    </div>

</td>


<td>

    <?= htmlspecialchars(
        $building["project_name"]
    ) ?>

</td>


<td>

    <?= (int)$building["floors"] ?>

</td>


<td>

    <?= (int)$building["unit_count"] ?>

</td>


<td>

    <span class="badge available">

        <?= (int)$building["available_count"] ?>

    </span>

</td>


<td>

    <span class="badge booked">

        <?= (int)$building["booked_count"] ?>

    </span>

</td>


<td>

<div class="actions">


<button
    class="icon-btn"
    title="Edit"
    onclick='editBuilding(
        <?= json_encode(
            $building,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>
    )'
>
    ✏️
</button>


<form
    method="POST"
    onsubmit="return confirm(
        'Delete this building and its units?'
    )"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrfToken) ?>"
>

<input
    type="hidden"
    name="action"
    value="delete_building"
>

<input
    type="hidden"
    name="id"
    value="<?= (int)$building["id"] ?>"
>

<button
    class="icon-btn delete"
    type="submit"
>
    🗑️
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

</div>

</div>


<!-- =====================================================
     UNITS
===================================================== -->

<div
    id="units"
    class="tab-content"
>


<div class="section-header">

    <h2>
        Units
    </h2>


    <button
        class="primary-btn"
        onclick="openUnitModal()"
    >
        + Add Unit
    </button>

</div>


<div class="table-card">

<?php if (empty($units)): ?>

    <div class="empty">

        <div class="empty-icon">
            🏠
        </div>

        <div class="empty-title">
            No units yet
        </div>

        <div class="empty-text">
            Add units under your buildings.
        </div>

    </div>

<?php else: ?>

<div class="table-wrapper">

<table>

<thead>

<tr>

<th>Unit</th>

<th>Project</th>

<th>Building</th>

<th>Type</th>

<th>Floor</th>

<th>Price</th>

<th>Status</th>

<th>Actions</th>

</tr>

</thead>


<tbody>

<?php foreach ($units as $unit): ?>

<tr>

<td>

    <div class="item-name">

        <?= htmlspecialchars(
            $unit["unit_number"]
        ) ?>

    </div>

    <div class="item-sub">

        Unit #<?= (int)$unit["id"] ?>

    </div>

</td>


<td>

    <?= htmlspecialchars(
        $unit["project_name"]
    ) ?>

</td>


<td>

    <?= htmlspecialchars(
        $unit["building_name"]
    ) ?>

</td>


<td>

    <?= htmlspecialchars(
        $unit["type"]
    ) ?>

</td>


<td>

    <?= (int)$unit["floor"] ?>

</td>


<td>

    ₹<?= number_format(
        (float)$unit["price"],
        2
    ) ?>

</td>


<td>

    <span
        class="badge <?= htmlspecialchars(
            $unit["status"]
        ) ?>"
    >

        <?= ucfirst(
            htmlspecialchars(
                $unit["status"]
            )
        ) ?>

    </span>

</td>


<td>

<div class="actions">


<button
    class="icon-btn"
    title="Edit"
    onclick='editUnit(
        <?= json_encode(
            $unit,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT
        ) ?>
    )'
>
    ✏️
</button>


<form
    method="POST"
    onsubmit="return confirm(
        'Delete this unit?'
    )"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrfToken) ?>"
>

<input
    type="hidden"
    name="action"
    value="delete_unit"
>

<input
    type="hidden"
    name="id"
    value="<?= (int)$unit["id"] ?>"
>

<button
    class="icon-btn delete"
    type="submit"
>
    🗑️
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

</div>

</div>


</section>

</main>


<!-- =====================================================
     PROJECT MODAL
===================================================== -->

<div
    class="modal"
    id="projectModal"
>

<div class="modal-box">

<div class="modal-header">

<h2 id="projectModalTitle">
    Add Project
</h2>

<button
    class="close"
    onclick="closeModal('projectModal')"
>
    ×
</button>

</div>


<form method="POST">

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrfToken) ?>"
>

<input
    type="hidden"
    name="action"
    id="projectAction"
    value="add_project"
>

<input
    type="hidden"
    name="id"
    id="projectId"
>


<div class="modal-body">

<div class="form-grid">


<div class="form-group">

<label>
    Project Name *
</label>

<input
    class="input"
    type="text"
    name="name"
    id="projectName"
    required
    maxlength="150"
    placeholder="Green Valley Residency"
>

</div>


<div class="form-group">

<label>
    Location *
</label>

<input
    class="input"
    type="text"
    name="location"
    id="projectLocation"
    required
    maxlength="255"
    placeholder="Chennai, Tamil Nadu"
>

</div>


<div class="form-group full">

<label>
    Description
</label>

<textarea
    class="textarea"
    name="description"
    id="projectDescription"
    placeholder="Project description..."
></textarea>

</div>


</div>

</div>


<div class="modal-footer">

<button
    type="button"
    class="cancel-btn"
    onclick="closeModal('projectModal')"
>
    Cancel
</button>

<button
    class="save-btn"
    type="submit"
>
    Save Project
</button>

</div>

</form>

</div>

</div>


<!-- =====================================================
     BUILDING MODAL
===================================================== -->

<div
    class="modal"
    id="buildingModal"
>

<div class="modal-box">

<div class="modal-header">

<h2 id="buildingModalTitle">
    Add Building
</h2>

<button
    class="close"
    onclick="closeModal('buildingModal')"
>
    ×
</button>

</div>


<form method="POST">

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrfToken) ?>"
>

<input
    type="hidden"
    name="action"
    id="buildingAction"
    value="add_building"
>

<input
    type="hidden"
    name="id"
    id="buildingId"
>


<div class="modal-body">

<div class="form-grid">


<div class="form-group">

<label>
    Project *
</label>

<select
    class="select"
    name="project_id"
    id="buildingProject"
    required
>

<option value="">
    Select project
</option>

<?php foreach ($projects as $project): ?>

<option
    value="<?= (int)$project["id"] ?>"
>

    <?= htmlspecialchars(
        $project["name"]
    ) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label>
    Building Name *
</label>

<input
    class="input"
    type="text"
    name="name"
    id="buildingName"
    required
    maxlength="100"
    placeholder="Tower A"
>

</div>


<div class="form-group">

<label>
    Number of Floors *
</label>

<input
    class="input"
    type="number"
    name="floors"
    id="buildingFloors"
    min="1"
    value="10"
    required
>

</div>


<div class="form-group full">

<label>
    Description
</label>

<textarea
    class="textarea"
    name="description"
    id="buildingDescription"
    placeholder="Building description..."
></textarea>

</div>


</div>

</div>


<div class="modal-footer">

<button
    type="button"
    class="cancel-btn"
    onclick="closeModal('buildingModal')"
>
    Cancel
</button>

<button
    class="save-btn"
    type="submit"
>
    Save Building
</button>

</div>

</form>

</div>

</div>


<!-- =====================================================
     UNIT MODAL
===================================================== -->

<div
    class="modal"
    id="unitModal"
>

<div class="modal-box">

<div class="modal-header">

<h2 id="unitModalTitle">
    Add Unit
</h2>

<button
    class="close"
    onclick="closeModal('unitModal')"
>
    ×
</button>

</div>


<form method="POST">

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($csrfToken) ?>"
>

<input
    type="hidden"
    name="action"
    id="unitAction"
    value="add_unit"
>

<input
    type="hidden"
    name="id"
    id="unitId"
>


<div class="modal-body">

<div class="form-grid">


<div class="form-group">

<label>
    Building *
</label>

<select
    class="select"
    name="building_id"
    id="unitBuilding"
    required
>

<option value="">
    Select building
</option>

<?php foreach ($buildings as $building): ?>

<option
    value="<?= (int)$building["id"] ?>"
>

    <?= htmlspecialchars(
        $building["project_name"]
    ) ?>

    ·

    <?= htmlspecialchars(
        $building["name"]
    ) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label>
    Unit Number *
</label>

<input
    class="input"
    type="text"
    name="unit_number"
    id="unitNumber"
    required
    maxlength="50"
    placeholder="A-101"
>

</div>


<div class="form-group">

<label>
    Unit Type *
</label>

<select
    class="select"
    name="type"
    id="unitType"
>

<?php foreach ($unitTypes as $type): ?>

<option
    value="<?= htmlspecialchars($type) ?>"
>

    <?= htmlspecialchars($type) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label>
    Floor *
</label>

<input
    class="input"
    type="number"
    name="floor"
    id="unitFloor"
    min="1"
    value="1"
    required
>

</div>


<div class="form-group">

<label>
    Price *
</label>

<input
    class="input"
    type="number"
    name="price"
    id="unitPrice"
    min="0"
    step="0.01"
    required
    placeholder="6500000"
>

</div>


<div class="form-group">

<label>
    Availability *
</label>

<select
    class="select"
    name="status"
    id="unitStatus"
>

<?php foreach ($unitStatuses as $status): ?>

<option
    value="<?= htmlspecialchars($status) ?>"
>

    <?= ucfirst(
        htmlspecialchars($status)
    ) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group full">

<label>
    Description
</label>

<textarea
    class="textarea"
    name="description"
    id="unitDescription"
    placeholder="Unit details..."
></textarea>

</div>


</div>

</div>


<div class="modal-footer">

<button
    type="button"
    class="cancel-btn"
    onclick="closeModal('unitModal')"
>
    Cancel
</button>

<button
    class="save-btn"
    type="submit"
>
    Save Unit
</button>

</div>

</form>

</div>

</div>


<script>

/* =====================================================
   TABS
===================================================== */

function showTab(tabId, button) {

    document
        .querySelectorAll(".tab-content")
        .forEach(function(tab) {

            tab.classList.remove("active");

        });


    document
        .querySelectorAll(".tab")
        .forEach(function(tab) {

            tab.classList.remove("active");

        });


    document
        .getElementById(tabId)
        .classList.add("active");


    button.classList.add("active");

}


/* =====================================================
   MODAL
===================================================== */

function openModal(id) {

    document
        .getElementById(id)
        .classList.add("show");

}


function closeModal(id) {

    document
        .getElementById(id)
        .classList.remove("show");

}


/* =====================================================
   PROJECT
===================================================== */

function openProjectModal() {

    document
        .getElementById("projectModalTitle")
        .textContent = "Add Project";

    document
        .getElementById("projectAction")
        .value = "add_project";

    document
        .getElementById("projectId")
        .value = "";

    document
        .getElementById("projectName")
        .value = "";

    document
        .getElementById("projectLocation")
        .value = "";

    document
        .getElementById("projectDescription")
        .value = "";

    openModal("projectModal");

}


function editProject(project) {

    document
        .getElementById("projectModalTitle")
        .textContent = "Edit Project";

    document
        .getElementById("projectAction")
        .value = "edit_project";

    document
        .getElementById("projectId")
        .value = project.id;

    document
        .getElementById("projectName")
        .value = project.name || "";

    document
        .getElementById("projectLocation")
        .value = project.location || "";

    document
        .getElementById("projectDescription")
        .value = project.description || "";

    openModal("projectModal");

}


/* =====================================================
   BUILDING
===================================================== */

function openBuildingModal() {

    document
        .getElementById("buildingModalTitle")
        .textContent = "Add Building";

    document
        .getElementById("buildingAction")
        .value = "add_building";

    document
        .getElementById("buildingId")
        .value = "";

    document
        .getElementById("buildingProject")
        .value = "";

    document
        .getElementById("buildingName")
        .value = "";

    document
        .getElementById("buildingFloors")
        .value = "10";

    document
        .getElementById("buildingDescription")
        .value = "";

    openModal("buildingModal");

}


function editBuilding(building) {

    document
        .getElementById("buildingModalTitle")
        .textContent = "Edit Building";

    document
        .getElementById("buildingAction")
        .value = "edit_building";

    document
        .getElementById("buildingId")
        .value = building.id;

    document
        .getElementById("buildingProject")
        .value = building.project_id;

    document
        .getElementById("buildingName")
        .value = building.name || "";

    document
        .getElementById("buildingFloors")
        .value = building.floors || 1;

    document
        .getElementById("buildingDescription")
        .value =
            building.description || "";

    openModal("buildingModal");

}


/* =====================================================
   UNIT
===================================================== */

function openUnitModal() {

    document
        .getElementById("unitModalTitle")
        .textContent = "Add Unit";

    document
        .getElementById("unitAction")
        .value = "add_unit";

    document
        .getElementById("unitId")
        .value = "";

    document
        .getElementById("unitBuilding")
        .value = "";

    document
        .getElementById("unitNumber")
        .value = "";

    document
        .getElementById("unitType")
        .value = "2 BHK";

    document
        .getElementById("unitFloor")
        .value = "1";

    document
        .getElementById("unitPrice")
        .value = "";

    document
        .getElementById("unitStatus")
        .value = "available";

    document
        .getElementById("unitDescription")
        .value = "";

    openModal("unitModal");

}


function editUnit(unit) {

    document
        .getElementById("unitModalTitle")
        .textContent = "Edit Unit";

    document
        .getElementById("unitAction")
        .value = "edit_unit";

    document
        .getElementById("unitId")
        .value = unit.id;

    document
        .getElementById("unitBuilding")
        .value = unit.building_id;

    document
        .getElementById("unitNumber")
        .value = unit.unit_number || "";

    document
        .getElementById("unitType")
        .value = unit.type || "2 BHK";

    document
        .getElementById("unitFloor")
        .value = unit.floor || 1;

    document
        .getElementById("unitPrice")
        .value = unit.price || 0;

    document
        .getElementById("unitStatus")
        .value = unit.status || "available";

    document
        .getElementById("unitDescription")
        .value =
            unit.description || "";

    openModal("unitModal");

}


/* =====================================================
   CLOSE MODAL OUTSIDE
===================================================== */

document
    .querySelectorAll(".modal")
    .forEach(function(modal) {

        modal.addEventListener(
            "click",
            function(event) {

                if (
                    event.target === modal
                ) {

                    modal.classList.remove("show");

                }

            }
        );

    });


/* =====================================================
   ESCAPE
===================================================== */

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            document
                .querySelectorAll(".modal")
                .forEach(function(modal) {

                    modal.classList.remove("show");

                });

        }

    }
);

</script>


</body>

</html>