<?php
session_start();

// بررسی ورود کاربر و نوع کاربر
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}


require 'vendor/autoload.php';
require 'Header.php';
require 'Menu_m.php';
require 'Menu_add.php';
require 'Menu_omor.php';
require 'Menu_pirapezeshk.php';
// تنظیمات اتصال به دیتابیس
$servername  = "localhost";
$db_username = "root";
$db_password = "mysql";
$dbname      = "dashboard_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("خطا در اتصال به دیتابیس: " . $conn->connect_error);
}

// استخراج آخرین تاریخ ثبت شده در بخش کارکرد  برای هر بیمارستان
$query = "SELECT hospital_code, hospital, MAX(date) AS latest_date
          FROM fund_performance
          GROUP BY hospital_code, hospital
          ORDER BY latest_date DESC";
$result = $conn->query($query);

$updates = [];
if ($result !== false && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $updates[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>آخرین بروزرسانی  - داشبورد متصدی ورود اطلاعات</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <style>
        @font-face {
            font-family: Sahel;
            src: url("fonts/Sahel.woff2") format("woff2"),
                 url("fonts/Sahel.woff") format("woff");
        }
        body {
            background: url('p_main.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: Sahel;
            margin: 0;
        }
        .main-content {
            background-color: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            margin-top: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- ستون منوی کناری -->
            <div class="col-md-2">
                <?php
                // استفاده از MenuFactory جهت نمایش منوی متناسب با سطح دسترسی
                $username = $_SESSION["username"];
                $user_type = $_SESSION["user_type"];
                $logo = "logo.png";

                class MenuFactory
                {
                    public static function createMenu($username, $user_type, $logo)
                    {
                        switch ($user_type) {
                            case "متصدی":
                                return new Menu_add($username, $user_type, $logo);
                            case "مدیر":
                                return new Menu_m($username, $user_type, $logo);
                            case "کاربر اموربیمارستان":
                                return new Menu_omor($username, $user_type, $logo);
                            case "کاربر پیراپزشکی":
                                return new Menu_pirapezeshk($username, $user_type, $logo);
                            default:
                                return null;
                        }
                    }
                }
                $menu = MenuFactory::createMenu($username, $user_type, $logo);
                if ($menu === null) {
                    echo "برای شما منوی مناسب تعریف نشده است.";
                } else {
                    echo $menu->render();
                }
                ?>
            </div>
        <!-- ستون محتوای اصلی -->
        <div class="col-md-10">
            <?php
            $header = new Header("آخرین بروزرسانی هر بیمارستان", $_SESSION["username"]);
            echo $header->render();
            ?>
            <div class="main-content">
                <h4 class="mb-4 text-center">جدول آخرین تاریخ به‌روزرسانی برای هر بیمارستان</h4>
                <?php if(count($updates) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>کد بیمارستان</th>
                                <th>نام بیمارستان</th>
                                <th>آخرین تاریخ بروزرسانی </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($updates as $update): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($update['hospital_code']); ?></td>
                                <td><?php echo htmlspecialchars($update['hospital']); ?></td>
                                <td><?php echo htmlspecialchars($update['latest_date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">هیچ داده‌ای یافت نشد.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="js/bootstrap.bundle.min.js" defer></script>
</body>
</html>
