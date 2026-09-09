<?php
session_start();

require_once "config/database.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Validation
    if ($name === "" || $email === "" || $phone === "" || $password === "") {
        $message = "Please fill in all fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif (strlen($password) < 6) {
        $message = "Password must contain at least 6 characters.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";

    } else {

        try {

            // Check existing email
            $check = $pdo->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );
            $check->execute([$email]);

            if ($check->fetch()) {

                $message = "An account with this email already exists.";
                $messageType = "error";

            } else {

                // Secure password
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // New users are registered as sales users
                $role = "sales";

              $stmt = $pdo->prepare(" INSERT INTO users
    (name, email, password, role)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $name,
    $email,
    $hashedPassword,
    $role
]);

                $message = "Registration successful! You can now login.";
                $messageType = "success";

                // Clear form
                $name = "";
                $email = "";
                $phone = "";
            }

     } catch (PDOException $e) {

    $message = "Database Error: " . $e->getMessage();
    $messageType = "error";
}
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Account | PropFlow CRM</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 15% 20%, #dbeafe, transparent 30%),
                radial-gradient(circle at 85% 80%, #e0e7ff, transparent 30%),
                #f8fafc;

            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .register-wrapper {
            width: 100%;
            max-width: 1050px;
            min-height: 620px;

            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;

            overflow: hidden;

            display: grid;
            grid-template-columns: 0.9fr 1.1fr;

            box-shadow:
                0 25px 70px rgba(15, 23, 42, 0.12);
        }

        /* LEFT */

        .brand-section {
            background:
                linear-gradient(
                    145deg,
                    #0f172a,
                    #172554
                );

            color: #ffffff;
            padding: 55px 45px;

            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;

            font-size: 23px;
            font-weight: 800;
        }

        .brand-icon {
            width: 44px;
            height: 44px;

            border-radius: 12px;

            background: #2563eb;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 21px;
            font-weight: 800;
        }

        .brand-content {
            margin-top: auto;
            margin-bottom: auto;
        }

        .brand-content h1 {
            font-size: 40px;
            line-height: 1.15;
            margin-bottom: 18px;
        }

        .brand-content p {
            color: #cbd5e1;
            font-size: 15px;
            line-height: 1.7;
        }

        .brand-features {
            margin-top: 28px;
            display: grid;
            gap: 14px;
        }

        .brand-feature {
            display: flex;
            align-items: center;
            gap: 10px;

            color: #e2e8f0;
            font-size: 14px;
        }

        .check {
            width: 22px;
            height: 22px;

            border-radius: 50%;
            background: #2563eb;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 12px;
        }

        /* RIGHT */

        .form-section {
            padding: 55px 55px;

            display: flex;
            align-items: center;
        }

        .form-box {
            width: 100%;
            max-width: 430px;
            margin: auto;
        }

        .form-box h2 {
            font-size: 30px;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .message {
            padding: 13px 15px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .message.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .message.success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .field {
            margin-bottom: 17px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;

            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .field input {
            width: 100%;
            height: 47px;

            border: 1px solid #dbe2ea;
            border-radius: 9px;

            padding: 0 14px;

            outline: none;

            color: #0f172a;
            background: #ffffff;

            font-size: 14px;

            transition: 0.2s ease;
        }

        .field input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .password-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .register-button {
            width: 100%;
            height: 49px;

            border: 0;
            border-radius: 9px;

            background: #2563eb;
            color: #ffffff;

            font-size: 15px;
            font-weight: 700;

            cursor: pointer;

            transition: 0.2s ease;

            margin-top: 5px;
        }

        .register-button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .login-text {
            text-align: center;
            margin-top: 22px;

            color: #64748b;
            font-size: 14px;
        }

        .login-text a {
            color: #2563eb;
            font-weight: 700;
        }

        .login-text a:hover {
            text-decoration: underline;
        }

        /* MOBILE */

        @media (max-width: 800px) {

            .register-wrapper {
                grid-template-columns: 1fr;
                max-width: 520px;
            }

            .brand-section {
                padding: 35px;
                min-height: 300px;
            }

            .brand-content {
                margin-top: 50px;
                margin-bottom: 10px;
            }

            .brand-content h1 {
                font-size: 32px;
            }

            .form-section {
                padding: 40px 30px;
            }
        }

        @media (max-width: 500px) {

            body {
                padding: 15px;
            }

            .register-wrapper {
                border-radius: 18px;
            }

            .brand-section {
                padding: 28px 24px;
            }

            .brand-content h1 {
                font-size: 28px;
            }

            .brand-features {
                display: none;
            }

            .form-section {
                padding: 35px 22px;
            }

            .form-box h2 {
                font-size: 26px;
            }

            .password-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

    </style>

</head>

<body>

<div class="register-wrapper">

    <!-- LEFT SIDE -->

    <div class="brand-section">

        <div class="brand">

            <div class="brand-icon">P</div>

            <span>PropFlow CRM</span>

        </div>


        <div class="brand-content">

            <h1>
                Grow your real estate business.
            </h1>

            <p>
                Manage leads, properties, sales pipelines
                and bookings from one powerful CRM platform.
            </p>


            <div class="brand-features">

                <div class="brand-feature">
                    <span class="check">✓</span>
                    Manage your leads efficiently
                </div>

                <div class="brand-feature">
                    <span class="check">✓</span>
                    Track your sales pipeline
                </div>

                <div class="brand-feature">
                    <span class="check">✓</span>
                    Manage properties and bookings
                </div>

            </div>

        </div>

    </div>


    <!-- RIGHT SIDE -->

    <div class="form-section">

        <div class="form-box">

            <h2>Create Account</h2>

            <p class="subtitle">
                Create your PropFlow CRM sales account.
            </p>


            <?php if ($message !== ""): ?>

                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>

            <?php endif; ?>


            <form method="POST" action="">

                <div class="field">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter your full name"
                        value="<?php echo htmlspecialchars($name ?? ''); ?>"
                        required
                    >

                </div>


                <div class="field">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        required
                    >

                </div>


                <div class="field">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="Enter your phone number"
                        value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                        required
                    >

                </div>


                <div class="password-row">

                    <div class="field">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Minimum 6 characters"
                            required
                        >

                    </div>


                    <div class="field">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm password"
                            required
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    class="register-button"
                >
                    Create Account
                </button>

            </form>


            <div class="login-text">

                Already have an account?

                <a href="login.php">
                    Login
                </a>

            </div>

        </div>

    </div>

</div>

</body>
</html>