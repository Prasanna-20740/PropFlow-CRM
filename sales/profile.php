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

$message = '';
$messageType = '';

/* =========================
   UPDATE PROFILE
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $postedToken)) {

        $message = 'Invalid security token. Please refresh the page.';
        $messageType = 'error';

    } else {

        $action = $_POST['action'] ?? '';

        /* =========================
           PROFILE UPDATE
        ========================= */

        if ($action === 'update_profile') {

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if ($name === '') {

                $message = 'Name is required.';
                $messageType = 'error';

            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $message = 'Please enter a valid email address.';
                $messageType = 'error';

            } else {

                try {

                    /* Check duplicate email */

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM users
                        WHERE email = ?
                        AND id != ?
                        LIMIT 1
                    ");

                    $stmt->execute(array(
                        $email,
                        $currentUserId
                    ));

                    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingUser) {

                        $message = 'This email address is already in use.';
                        $messageType = 'error';

                    } else {

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET name = ?, email = ?
                            WHERE id = ?
                            AND role = 'sales'
                        ");

                        $stmt->execute(array(
                            $name,
                            $email,
                            $currentUserId
                        ));

                        /*
                         * Refresh session user data
                         * if auth.php stores user in session.
                         */
                        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                            $_SESSION['user']['name'] = $name;
                            $_SESSION['user']['email'] = $email;
                        }

                        $user['name'] = $name;
                        $user['email'] = $email;

                        $message = 'Profile updated successfully.';
                        $messageType = 'success';
                    }

                } catch (PDOException $e) {

                    $message = 'Unable to update profile.';
                    $messageType = 'error';
                }
            }
        }


        /* =========================
           CHANGE PASSWORD
        ========================= */

        if ($action === 'change_password') {

            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($currentPassword === '') {

                $message = 'Enter your current password.';
                $messageType = 'error';

            } elseif ($newPassword === '') {

                $message = 'Enter a new password.';
                $messageType = 'error';

            } elseif (strlen($newPassword) < 6) {

                $message = 'New password must contain at least 6 characters.';
                $messageType = 'error';

            } elseif ($newPassword !== $confirmPassword) {

                $message = 'New password and confirmation password do not match.';
                $messageType = 'error';

            } else {

                try {

                    $stmt = $pdo->prepare("
                        SELECT password
                        FROM users
                        WHERE id = ?
                        AND role = 'sales'
                        LIMIT 1
                    ");

                    $stmt->execute(array($currentUserId));

                    $account = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$account) {

                        $message = 'Account not found.';
                        $messageType = 'error';

                    } else {

                        $storedPassword = $account['password'] ?? '';

                        /*
                         * Supports password_hash() passwords.
                         * Also supports existing plain-text passwords
                         * if the current project was created that way.
                         */

                        $passwordValid = false;

                        if (
                            !empty($storedPassword) &&
                            password_verify(
                                $currentPassword,
                                $storedPassword
                            )
                        ) {

                            $passwordValid = true;

                        } elseif (
                            $storedPassword !== '' &&
                            hash_equals(
                                (string)$storedPassword,
                                (string)$currentPassword
                            )
                        ) {

                            $passwordValid = true;
                        }

                        if (!$passwordValid) {

                            $message = 'Current password is incorrect.';
                            $messageType = 'error';

                        } else {

                            $newHash = password_hash(
                                $newPassword,
                                PASSWORD_DEFAULT
                            );

                            $stmt = $pdo->prepare("
                                UPDATE users
                                SET password = ?
                                WHERE id = ?
                                AND role = 'sales'
                            ");

                            $stmt->execute(array(
                                $newHash,
                                $currentUserId
                            ));

                            $message = 'Password changed successfully.';
                            $messageType = 'success';
                        }
                    }

                } catch (PDOException $e) {

                    $message = 'Unable to change password.';
                    $messageType = 'error';
                }
            }
        }
    }
}


/* =========================
   REFRESH USER DATA
========================= */

try {

    $stmt = $pdo->prepare("
        SELECT id, name, email, role, status
        FROM users
        WHERE id = ?
        AND role = 'sales'
        LIMIT 1
    ");

    $stmt->execute(array($currentUserId));

    $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($freshUser) {
        $user = $freshUser;
    }

} catch (PDOException $e) {
    // Keep existing session user data.
}


/* =========================
   USER DETAILS
========================= */

$name = trim($user['name'] ?? 'Sales Executive');
$email = trim($user['email'] ?? '');
$role = ucfirst(strtolower($user['role'] ?? 'sales'));
$status = ucfirst(strtolower($user['status'] ?? 'active'));

$initial = strtoupper(substr($name, 0, 1));

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Profile | PropFlow CRM</title>

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
           APP
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
            letter-spacing: -.5px;
        }

        .page-subtitle {
            margin-top: 3px;
            color: #8a94a6;
            font-size: 12px;
        }

        .profile-mini {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .avatar-small {
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

        /* =========================
           CONTENT
        ========================= */

        .content {
            padding: 28px 30px 45px;
            max-width: 1200px;
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
           PROFILE HERO
        ========================= */

        .profile-hero {
            background: #fff;
            border: 1px solid #e8ebf1;
            border-radius: 15px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
            margin-bottom: 20px;
        }

        .hero-left {
            display: flex;
            align-items: center;
            gap: 17px;
        }

        .avatar-large {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 25px;
            font-weight: 800;
            box-shadow: 0 8px 20px rgba(37, 99, 235, .18);
        }

        .hero-name {
            font-size: 21px;
            font-weight: 760;
            letter-spacing: -.5px;
        }

        .hero-email {
            color: #7b8495;
            font-size: 12px;
            margin-top: 5px;
        }

        .role-badge {
            display: inline-flex;
            margin-top: 9px;
            padding: 5px 9px;
            border-radius: 20px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 9px;
            font-weight: 750;
        }

        .account-status {
            text-align: right;
        }

        .status-label {
            color: #8a94a6;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 7px;
            padding: 6px 10px;
            border-radius: 20px;
            background: #ecfdf3;
            color: #087443;
            font-size: 10px;
            font-weight: 750;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        /* =========================
           GRID
        ========================= */

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: #fff;
            border: 1px solid #e8ebf1;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
        }

        .card-header {
            padding: 18px 20px;
            border-bottom: 1px solid #edf0f4;
        }

        .card-title {
            font-size: 14px;
            font-weight: 720;
        }

        .card-description {
            color: #8a94a6;
            font-size: 11px;
            margin-top: 4px;
        }

        .card-body {
            padding: 20px;
        }

        /* =========================
           FORM
        ========================= */

        .form-group {
            margin-bottom: 17px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        label {
            display: block;
            color: #475467;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            height: 42px;
            border: 1px solid #dfe4ec;
            border-radius: 8px;
            padding: 0 12px;
            background: #fff;
            color: #172033;
            font-size: 12px;
            outline: none;
            transition: .2s ease;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
        }

        input[readonly] {
            background: #f8fafc;
            color: #667085;
        }

        .form-note {
            color: #98a2b3;
            font-size: 10px;
            margin-top: 6px;
            line-height: 1.5;
        }

        /* =========================
           BUTTON
        ========================= */

        .form-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 10px 17px;
            background: #2563eb;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        /* =========================
           ACCOUNT INFO
        ========================= */

        .info-list {
            display: flex;
            flex-direction: column;
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 13px 0;
            border-bottom: 1px solid #eef1f5;
        }

        .info-row:first-child {
            padding-top: 0;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-label {
            color: #8a94a6;
            font-size: 11px;
        }

        .info-value {
            color: #344054;
            font-size: 12px;
            font-weight: 650;
            text-align: right;
        }

        .active-text {
            color: #087443;
        }

        /* =========================
           SECURITY
        ========================= */

        .security-card {
            grid-column: 1 / -1;
        }

        .password-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
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

            .content {
                padding: 22px 18px;
            }
        }

        @media (max-width: 900px) {

            .grid {
                grid-template-columns: 1fr;
            }

            .security-card {
                grid-column: auto;
            }

            .password-grid {
                grid-template-columns: 1fr;
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

            .profile-hero {
                align-items: flex-start;
                flex-direction: column;
            }

            .account-status {
                text-align: left;
            }
        }

    </style>

</head>

<body>

<div class="app">

    <!-- SIDEBAR -->

    <?php $activePage = 'profile'; include 'sidebar.php'; ?>


    <!-- MAIN -->

    <main class="main">

        <!-- TOPBAR -->

        <header class="topbar">

            <div>

                <div class="page-title">
                    My Profile
                </div>

                <div class="page-subtitle">
                    Manage your account and security settings
                </div>

            </div>


            <div class="profile-mini">

                <div class="avatar-small">
                    <?php echo pf_e($initial); ?>
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


            <!-- PROFILE HERO -->

            <div class="profile-hero">

                <div class="hero-left">

                    <div class="avatar-large">
                        <?php echo pf_e($initial); ?>
                    </div>

                    <div>

                        <div class="hero-name">
                            <?php echo pf_e($name); ?>
                        </div>

                        <div class="hero-email">
                            <?php echo pf_e($email); ?>
                        </div>

                        <span class="role-badge">
                            Sales Executive
                        </span>

                    </div>

                </div>


                <div class="account-status">

                    <div class="status-label">
                        Account Status
                    </div>

                    <div class="status-badge">

                        <span class="status-dot"></span>

                        <?php echo pf_e($status); ?>

                    </div>

                </div>

            </div>


            <!-- GRID -->

            <div class="grid">


                <!-- PERSONAL INFORMATION -->

                <div class="card">

                    <div class="card-header">

                        <div class="card-title">
                            Personal Information
                        </div>

                        <div class="card-description">
                            Update your basic account details
                        </div>

                    </div>


                    <div class="card-body">

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo pf_e($csrfToken); ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="update_profile"
                            >


                            <div class="form-group">

                                <label for="name">
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value="<?php echo pf_e($name); ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="email">
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?php echo pf_e($email); ?>"
                                    maxlength="150"
                                    required
                                >

                            </div>


                            <div class="form-actions">

                                <button
                                    type="submit"
                                    class="btn"
                                >
                                    Save Changes
                                </button>

                            </div>

                        </form>

                    </div>

                </div>


                <!-- ACCOUNT INFORMATION -->

                <div class="card">

                    <div class="card-header">

                        <div class="card-title">
                            Account Information
                        </div>

                        <div class="card-description">
                            Your PropFlow CRM account details
                        </div>

                    </div>


                    <div class="card-body">

                        <div class="info-list">


                            <div class="info-row">

                                <span class="info-label">
                                    User ID
                                </span>

                                <span class="info-value">
                                    #<?php echo $currentUserId; ?>
                                </span>

                            </div>


                            <div class="info-row">

                                <span class="info-label">
                                    Role
                                </span>

                                <span class="info-value">
                                    <?php echo pf_e($role); ?>
                                </span>

                            </div>


                            <div class="info-row">

                                <span class="info-label">
                                    Email
                                </span>

                                <span class="info-value">
                                    <?php echo pf_e($email); ?>
                                </span>

                            </div>


                            <div class="info-row">

                                <span class="info-label">
                                    Status
                                </span>

                                <span class="info-value active-text">
                                    <?php echo pf_e($status); ?>
                                </span>

                            </div>


                            <div class="info-row">

                                <span class="info-label">
                                    Access
                                </span>

                                <span class="info-value">
                                    Sales Workspace
                                </span>

                            </div>


                        </div>

                    </div>

                </div>


                <!-- CHANGE PASSWORD -->

                <div class="card security-card">

                    <div class="card-header">

                        <div class="card-title">
                            Security
                        </div>

                        <div class="card-description">
                            Change your account password
                        </div>

                    </div>


                    <div class="card-body">

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo pf_e($csrfToken); ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="change_password"
                            >


                            <div class="password-grid">


                                <div class="form-group">

                                    <label for="current_password">
                                        Current Password
                                    </label>

                                    <input
                                        type="password"
                                        id="current_password"
                                        name="current_password"
                                        autocomplete="current-password"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="new_password">
                                        New Password
                                    </label>

                                    <input
                                        type="password"
                                        id="new_password"
                                        name="new_password"
                                        minlength="6"
                                        autocomplete="new-password"
                                        required
                                    >

                                    <div class="form-note">
                                        Minimum 6 characters.
                                    </div>

                                </div>


                                <div class="form-group">

                                    <label for="confirm_password">
                                        Confirm Password
                                    </label>

                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        minlength="6"
                                        autocomplete="new-password"
                                        required
                                    >

                                </div>

                            </div>


                            <div class="form-actions">

                                <button
                                    type="submit"
                                    class="btn"
                                >
                                    Change Password
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>

</body>

</html>