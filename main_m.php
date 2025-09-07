<?php
session_start();

// بررسی ورود کاربر
if (!isset($_SESSION["user_id"])) {
  header("Location: login.php");
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


  <!-- استایل‌های سفارشی -->
  <style>
    /* تعریف فونت ایران یکان */
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

    /* در این قسمت تنظیمات مربوط به منو در اندازه‌های کوچک صفحه تعریف شده است */
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <!-- ستون منو (سایدبار) -->
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
        <!-- هدر فقط برای محتوای اصلی -->
        <?php
        $header = new Header("داشبورد مدیر ", $_SESSION["username"]);
        echo $header->render();
        ?>
        <!-- محتوای اصلی -->
        <div class="main-content">
          <div class="text-center">
            <h3>خوش آمدید!</h3>
            <p>به داشبورد مدیریتی مدیر خوش آمدید. از منوهای سمت، بخش‌های مورد نظر را انتخاب کنید.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="js/bootstrap.bundle.min.js"></script>
</body>
