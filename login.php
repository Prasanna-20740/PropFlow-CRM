<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

$error = "";

/* =========================
   CSRF TOKEN
========================= */

if (empty($_SESSION["login_csrf"])) {
    $_SESSION["login_csrf"] = bin2hex(random_bytes(32));
}

/* =========================
   LOGIN
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $csrf = $_POST["csrf"] ?? "";

    if (!hash_equals($_SESSION["login_csrf"], $csrf)) {

        $error = "Invalid request. Please try again.";

    } elseif ($email === "" || $password === "") {

        $error = "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    name,
                    email,
                    password,
                    role,
                    status
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                $error = "Invalid email or password.";

            } elseif (strtolower($user["status"] ?? "") !== "active") {

                $error = "Your account is inactive. Please contact the administrator.";

            } elseif (!password_verify($password, $user["password"])) {

                $error = "Invalid email or password.";

            } else {

                $role = strtolower(trim($user["role"] ?? ""));

                /*
                 * Only Admin and Sales users
                 * are allowed to access CRM.
                 */
                if (!in_array($role, ["admin", "sales"], true)) {

                    $error = "Your account does not have permission to access the CRM.";

                } else {

                    /* Prevent session fixation */
                    session_regenerate_id(true);

                    $_SESSION["user"] = [
                        "id" => $user["id"],
                        "name" => $user["name"],
                        "email" => $user["email"],
                        "role" => $role
                    ];

                    /* Regenerate CSRF token */
                    $_SESSION["login_csrf"] = bin2hex(random_bytes(32));

                    /* Redirect according to role */
                    if ($role === "admin") {

                        header("Location: admin/dashboard.php");
                        exit;

                    }

                    if ($role === "sales") {

                        header("Location: sales/dashboard.php");
                        exit;
                    }
                }
            }

        } catch (PDOException $e) {

            /*
             * Do not expose database errors
             * to users.
             */
            $error = "Unable to login right now. Please try again later.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | PropFlow CRM</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background:
                radial-gradient(
                    circle at top left,
                    #dbeafe 0,
                    transparent 35%
                ),
                radial-gradient(
                    circle at bottom right,
                    #e0e7ff 0,
                    transparent 35%
                ),
                #f8fafc;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 24px;
        }

        /* =========================
           MAIN CARD
        ========================= */

        .login-wrapper {
            width: 100%;
            max-width: 1050px;
            min-height: 620px;

            background: #ffffff;

            border: 1px solid #e2e8f0;
            border-radius: 24px;

            overflow: hidden;

            display: grid;
            grid-template-columns: 1fr 1fr;

            box-shadow:
                0 25px 70px rgba(15, 23, 42, 0.12);
        }

        /* =========================
           LEFT BRAND AREA
        ========================= */

        .brand-section {
            position: relative;

            padding: 55px;

            background:
                linear-gradient(
                    145deg,
                    #0f172a,
                    #1e3a8a
                );

            color: #ffffff;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            overflow: hidden;
        }

        .brand-section::before {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.06);

            top: -100px;
            right: -100px;
        }

        .brand-section::after {
            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.05);

            bottom: -80px;
            left: -70px;
        }

        .brand {
            position: relative;
            z-index: 2;

            display: flex;
            align-items: center;

            gap: 12px;
        }

        .brand-icon {
            width: 46px;
            height: 46px;

            border-radius: 13px;

            background: rgba(255, 255, 255, 0.14);

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 22px;
            font-weight: 800;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 700;

            letter-spacing: -0.5px;
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .brand-content h1 {
            font-size: 44px;

            line-height: 1.12;

            letter-spacing: -1.5px;

            margin-bottom: 20px;
        }

        .brand-content p {
            max-width: 400px;

            color: #cbd5e1;

            font-size: 16px;

            line-height: 1.7;
        }

        /* =========================
           FEATURES
        ========================= */

        .features {
            position: relative;
            z-index: 2;

            display: grid;

            gap: 14px;
        }

        .feature {
            display: flex;
            align-items: center;

            gap: 12px;

            color: #e2e8f0;

            font-size: 14px;
        }

        .feature-icon {
            width: 30px;
            height: 30px;

            flex-shrink: 0;

            border-radius: 9px;

            background: rgba(255, 255, 255, 0.1);

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 13px;
        }

        /* =========================
           LOGIN AREA
        ========================= */

        .login-section {
            padding: 55px;

            display: flex;
            align-items: center;
        }

        .login-content {
            width: 100%;
            max-width: 400px;

            margin: auto;
        }

        .login-content h2 {
            font-size: 30px;

            color: #0f172a;

            letter-spacing: -0.8px;

            margin-bottom: 8px;
        }

        .subtitle {
            color: #64748b;

            font-size: 14px;

            margin-bottom: 30px;
        }

        /* =========================
           ERROR
        ========================= */

        .alert {
            padding: 13px 15px;

            border-radius: 10px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #b91c1c;

            font-size: 13px;

            line-height: 1.5;

            margin-bottom: 20px;
        }

        /* =========================
           FORM
        ========================= */

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;

            font-size: 13px;

            font-weight: 600;

            color: #334155;

            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;

            left: 14px;
            top: 50%;

            transform: translateY(-50%);

            color: #94a3b8;

            font-size: 15px;

            pointer-events: none;
        }

        input {
            width: 100%;

            height: 48px;

            padding: 0 15px 0 42px;

            border: 1px solid #e2e8f0;

            border-radius: 11px;

            outline: none;

            font-size: 14px;

            color: #0f172a;

            background: #ffffff;

            transition: 0.2s ease;
        }

        input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 4px rgba(37, 99, 235, 0.08);
        }

        input::placeholder {
            color: #94a3b8;
        }

        /* =========================
           PASSWORD
        ========================= */

        .password-toggle {
            position: absolute;

            right: 14px;
            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #64748b;

            cursor: pointer;

            font-size: 12px;

            font-weight: 600;

            padding: 5px;
        }

        .password-toggle:hover {
            color: #2563eb;
        }

        /* =========================
           BUTTON
        ========================= */

        .login-button {
            width: 100%;

            height: 49px;

            border: none;

            border-radius: 11px;

            background: #2563eb;

            color: #ffffff;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.2s ease;

            margin-top: 5px;
        }

        .login-button:hover {
            background: #1d4ed8;

            transform: translateY(-1px);

            box-shadow:
                0 8px 20px rgba(37, 99, 235, 0.2);
        }

        .login-button:active {
            transform: translateY(0);
        }

        /* =========================
           REGISTER LINK
        ========================= */

        .register-text {
            text-align: center;

            margin-top: 22px;

            color: #64748b;

            font-size: 14px;
        }

        .register-text a {
            color: #2563eb;

            font-weight: 700;

            text-decoration: none;
        }

        .register-text a:hover {
            text-decoration: underline;
        }

        /* =========================
           COPYRIGHT
        ========================= */

        .copyright {
            text-align: center;

            color: #94a3b8;

            font-size: 11px;

            margin-top: 24px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 800px) {

            .login-wrapper {
                grid-template-columns: 1fr;

                max-width: 500px;
            }

            .brand-section {
                display: none;
            }

            .login-section {
                padding: 45px 30px;
            }
        }

        @media (max-width: 450px) {

            body {
                padding: 12px;
            }

            .login-wrapper {
                border-radius: 18px;
            }

            .login-section {
                padding: 35px 22px;
            }

            .login-content h2 {
                font-size: 27px;
            }
        }

    </style>

</head>

<body>

<div class="login-wrapper">

    <!-- =========================
         BRAND SECTION
    ========================== -->

    <section class="brand-section">

        <div class="brand">

            <div class="brand-icon">
                P
            </div>

            <div class="brand-name">
                PropFlow CRM
            </div>

        </div>


        <div class="brand-content">

            <h1>
                Manage leads.<br>
                Close deals.
            </h1>

            <p>
                A simple real estate CRM built for
                modern sales teams to manage leads,
                properties and bookings in one place.
            </p>

        </div>


        <div class="features">

            <div class="feature">

                <div class="feature-icon">
                    ✓
                </div>

                <span>
                    Track your complete sales pipeline
                </span>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    ✓
                </div>

                <span>
                    Manage property inventory
                </span>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    ✓
                </div>

                <span>
                    Secure and reliable booking workflow
                </span>

            </div>

        </div>

    </section>


    <!-- =========================
         LOGIN SECTION
    ========================== -->

    <section class="login-section">

        <div class="login-content">

            <h2>
                Welcome back
            </h2>

            <p class="subtitle">
                Sign in to your PropFlow CRM account.
            </p>


            <?php if ($error !== ""): ?>

                <div class="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>


            <form method="POST" action="">

                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf"
                    value="<?php echo htmlspecialchars($_SESSION["login_csrf"]); ?>"
                >


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email address
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            ✉
                        </span>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="you@example.com"
                            value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            🔒
                        </span>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword()"
                            aria-label="Show password"
                        >
                            Show
                        </button>

                    </div>

                </div>


                <!-- LOGIN -->

                <button
                    type="submit"
                    class="login-button"
                >
                    Sign in to CRM
                </button>

            </form>


            <!-- REGISTER -->

            <div class="register-text">

                Don't have an account?

                <a href="register.php">
                    Create account
                </a>

            </div>


            <div class="copyright">

                © <?php echo date("Y"); ?>
                PropFlow CRM. All rights reserved.

            </div>

        </div>

    </section>

</div>


<script>

function togglePassword() {

    const password =
        document.getElementById("password");

    const button =
        document.querySelector(".password-toggle");

    if (password.type === "password") {

        password.type = "text";

        button.textContent = "Hide";

    } else {

        password.type = "password";

        button.textContent = "Show";
    }
}

</script>

</body>

</html>