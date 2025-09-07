<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$error = "";

// ایجاد CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // بررسی CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("خطای امنیتی: درخواست نامعتبر");
    }

    // اتصال به دیتابیس
    $servername   = "localhost";
    $db_username  = "root";
    $db_password  = "mysql";
    $dbname       = "dashboard_db";

    $conn = new mysqli($servername, $db_username, $db_password, $dbname);
    if ($conn->connect_error) {
        die("خطا در اتصال به دیتابیس: " . $conn->connect_error);
    }

    // دریافت و اعتبارسنجی داده‌ها
    $username = $conn->real_escape_string(trim($_POST["username"]));
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $error = "لطفاً تمامی فیلدها را تکمیل کنید.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, user_type FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row["password"])) {
                $_SESSION["user_id"]   = $row["id"];
                $_SESSION["username"]  = $row["username"];
                $_SESSION["user_type"] = $row["user_type"];

                // هدایت بر اساس نوع کاربر
                $redirect = match ($row["user_type"]) {
                    'متصدی' => 'main_add.php',
                    'مدیر' => 'main_m.php',
                    'کاربر اموربیمارستان' => 'main_omor.php',
                    'کاربر پیراپزشکی' => 'main_pirapezeshki.php',
                    default => 'main.php'
                };
                header("Location: $redirect");
                exit;
            } else {
                $error = "اعتبارسنجی ناموفق";
            }
        } else {
            $error = "کاربری با این مشخصات یافت نشد";
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>سامانه مدیریت سلامت - ورود</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">

    <style>
        @font-face {
            font-family: Sahel;
            src: url("fonts/Sahel.woff2") format("woff2"),
                url("fonts/Sahel.woff") format("woff");
        }

        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Sahel';
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .auth-card:hover {
            transform: translateY(-5px);
        }

        .auth-header {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            padding: 3rem;
            position: relative;
        }

        .wave-effect {
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 100px;
            background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1440 320" xmlns="http://www.w3.org/2000/svg"><path fill="%23ffffff" d="M0,96L48,112C96,128,192,160,288,154.7C384,149,480,107,576,112C672,117,768,171,864,181.3C960,192,1056,160,1152,138.7C1248,117,1344,107,1392,101.3L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
        }

        .auth-logo {
            width: 100px;
            height: 100px;
            border: 3px solid #fff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .auth-logo:hover {
            transform: rotate(15deg) scale(1.1);
        }

        .input-group {
            margin: 1.5rem 0;
        }

        .input-group-text {
            background: var(--primary);
            color: white;
            border: none;
        }

        .form-control {
            border-left: none;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.3);
        }

        .btn-auth {
            background: linear-gradient(45deg, var(--secondary), var(--primary));
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }

        @media (min-width: 768px) {
            .p-md-5 {
                padding-top: 0rem !important;
                padding-right: 3rem !important;
                padding-bottom: 3rem !important;
                padding-left: 3rem !important;
            }
        }

        .btn-auth::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            bottom: -50%;
            left: -50%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transform: rotateZ(60deg) translate(-5em, 7.5em);
            animation: sheen 3s infinite;
        }

        @keyframes sheen {
            100% {
                transform: rotateZ(60deg) translate(1em, -9em);
            }
        }

        .alert-error {
            background: #ffe3e6;
            border-left: 4px solid var(--accent);
            padding: 1rem;
            border-radius: 8px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="auth-card">
                    <div class="auth-header text-center text-white">
                        <div class="wave-effect"></div>
                        <img src="logo.png" alt="لوگو" class="auth-logo rounded-circle mb-4">
                        <h2 class="mb-3">سامانه مدیریت بيمارستان ها</h2>
                        <p class="mb-0">&nbsp;</p>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <?php if (!empty($error)): ?>
                            <div class="alert-error mb-4">
                                <i class="bi bi-exclamation-circle-fill me-2"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="نام کاربری" required autofocus>
                            </div>

                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="رمز عبور" required>
                            </div>

                            <button type="submit" class="btn-auth w-100 mt-4 text-white">
                                <i class="bi bi-box-arrow-in-left me-2"></i>
                                ورود به سیستم
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>