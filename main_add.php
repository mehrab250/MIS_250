<?php
session_start();

// بررسی ورود کاربر
if (!isset($_SESSION["user_id"])) {
  header("Location: login.php");
  exit;
}

// بررسی اینکه فقط کاربر متصدی به این صفحه دسترسی داشته باشد
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "متصدی") {
  // کاربر غیر متصدی را به صفحه عدم دسترسی هدایت می‌کند
  header("Location: index.php");
  exit;
}

// بارگذاری کلاس‌های مورد نیاز
require 'vendor/autoload.php';
require 'Header.php';
require 'Menu_m.php';
require 'Menu_add.php';
require 'Menu_omor.php';
require 'Menu_pirapezeshk.php';

$activePage = isset($_GET['page']) ? $_GET['page'] : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8">
  <title>صفحه متصدی ورود اطلاعات</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- بارگذاری Bootstrap RTL -->
  <link href="css/bootstrap.rtl.min.css" rel="stylesheet">
  <!-- بارگذاری Font Awesome برای آیکون‌ها -->
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/bootstrap-icons.css">


  <!-- استفاده از فونت ایران یکان -->
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

    /* استایل محتوای اصلی */
    .main-content {
      background-color: rgba(255, 255, 255, 0.95);
      padding: 1rem;
      margin-top: 10px;
      border-radius: 8px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <!-- ستون منو (سایدبار) -->
      <div class="col-md-2">
        <?php
        $menu_add = new Menu_add($_SESSION["username"], $_SESSION["user_type"], 'logo.png');
        echo $menu_add->render();
        ?>
      </div>
      <!-- ستون محتوای اصلی -->
      <div class="col-md-10">
        <!-- هدر برای محتوای اصلی -->
        <?php
        $header = new Header("داشبورد متصدی", $_SESSION["username"]);
        echo $header->render();
        ?>
        <!-- محتوای اصلی -->
        <div class="main-content">
          <div class="text-center">
            <h3>خوش آمدید!</h3>
            <p>به داشبورد مدیریتی متصدی ورود اطلاعات خوش آمدید. از منوهای سمت، بخش‌های مورد نظر را انتخاب کنید.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="js/bootstrap.bundle.min.js"></script>
</body>

</html>