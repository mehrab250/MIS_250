<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require 'vendor/autoload.php';
require_once 'HospitalRepository.php'; // بارگذاری repository مربوط به بیمارستان‌ها
require 'Menu_m.php';
require 'Menu_add.php';
require 'Menu_omor.php';
require 'Menu_pirapezeshk.php';
$hospitals = HospitalRepository::getHospitals();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// دریافت مقادیر فرم به صورت امن
$start_date_val = $_POST["start_date"] ?? "";
$end_date_val   = $_POST["end_date"] ?? "";
// تغییر: مقدار پیشفرض بیمارستان به "" به جای "all"
$hospital_val   = $_POST["hospital"] ?? "";
$fund_val       = $_POST["fund"] ?? "all";

$aggregateResults = [];
$allItems         = [];
$message          = "";

// -------------------------
// بخش اختصاصی درخواست‌های AJAX
// -------------------------
if (isset($_GET['action'])) {
    $dbhost = "localhost";
    $dbuser = "root";
    $dbpass = "mysql";
    $dbname = "dashboard_db";
    $conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
    if ($conn->connect_error) {
        die("خطا در اتصال به دیتابیس: " . $conn->connect_error);
    }
    $action = $_GET['action'];

    // دریافت لیست صندوق‌ها بر اساس انتخاب بیمارستان
    if ($action == 'getFunds' && isset($_GET['hospital'])) {
        $hospital = $_GET['hospital'];
        if (empty($hospital)) { // اگر بیمارستان انتخاب نشده باشد
            echo json_encode([]);
            $conn->close();
            exit;
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT fund, fund_code FROM fund_performance WHERE hospital = ? ORDER BY fund");
            $stmt->bind_param("s", $hospital);
            $stmt->execute();
            $result = $stmt->get_result();
            $funds = [];
            while ($row = $result->fetch_assoc()) {
                $funds[] = $row;
            }
            echo json_encode($funds);
            $stmt->close();
        }
        $conn->close();
        exit;
    }
    $conn->close();
    exit;
}

// -------------------------
// پردازش فرم (نمایش گزارش و خروجی اکسل)
// -------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $startDate = $_POST["start_date"] ?? "";
    $endDate   = $_POST["end_date"] ?? "";

    // اعتبارسنجی تاریخ‌ها از نظر پر بودن فیلدها
    if (empty($startDate) || empty($endDate)) {
        echo "<script>alert('فیلدهای تاریخ شروع و پایان باید پر شوند.'); window.history.back();</script>";
        exit;
    }

    // اعتبارسنجی ترتیب تاریخ‌ها: اگر تاریخ پایان قبل از تاریخ شروع باشد
    if ($endDate < $startDate) {
        echo "<script>alert('تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.'); window.history.back();</script>";
        exit;
    }

    // بررسی انتخاب بیمارستان (الزامی بودن انتخاب)
    if (empty($hospital_val)) {
        echo "<script>alert('لطفاً یک بیمارستان را انتخاب کنید.'); window.history.back();</script>";
        exit;
    }

    $conditions = [];
    $params = [];
    $types = "";

    // شرط‌بندی تاریخ
    $conditions[] = "date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";

    // شرط برای انتخاب بیمارستان (الزامی چون انتخاب باید انجام شده باشد)
    if (!empty($hospital_val)) {
        $conditions[] = "hospital = ?";
        $params[] = $hospital_val;
        $types .= "s";
    }

    if ($fund_val !== "all") {
        $conditions[] = "fund = ?";
        $params[] = $fund_val;
        $types .= "s";
    }

    $whereClause = implode(" AND ", $conditions);

    // پرس‌وجوی کلی Aggregate عملکرد صندوق
    $queryAgg = "SELECT 
                    COALESCE(SUM(received),0) AS total_received,
                    COALESCE(SUM(returned),0) AS total_returned,
                    COALESCE(SUM(balance),0) AS total_balance
                 FROM fund_performance
                 WHERE $whereClause";

    $conn = new mysqli("localhost", "root", "mysql", "dashboard_db");
    if ($conn->connect_error) {
        die("خطا در اتصال به دیتابیس: " . $conn->connect_error);
    }
    $stmt = $conn->prepare($queryAgg);
    if ($stmt === false) {
        die("خطا در پرس‌وجو: " . $conn->error);
    }
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resAgg = $stmt->get_result();
    if ($resAgg) {
        $aggregateResults = $resAgg->fetch_assoc();
    }
    $stmt->close();

    // پرس‌وجوی جزئیات عملکرد صندوق برای نمایش در جدول (گروه‌بندی شده بر اساس بیمارستان و صندوق)
    $queryDetails = "SELECT 
                        hospital, fund, 
                        COALESCE(SUM(received),0) AS total_received,
                        COALESCE(SUM(returned),0) AS total_returned,
                        COALESCE(SUM(balance),0) AS total_balance
                     FROM fund_performance
                     WHERE $whereClause
                     GROUP BY hospital, fund
                     ORDER BY hospital, fund";
    $stmtDet = $conn->prepare($queryDetails);
    if ($stmtDet === false) {
        die("خطا در پرس‌وجوی جزئیات: " . $conn->error);
    }
    if (count($params) > 0) {
        $stmtDet->bind_param($types, ...$params);
    }
    $stmtDet->execute();
    $resDet = $stmtDet->get_result();
    while ($row = $resDet->fetch_assoc()) {
        $allItems[] = $row;
    }
    $stmtDet->close();

    // خروجی اکسل: در این حالت گزارش به همراه تاریخ نمایش داده می‌شود
    if ($_POST["action"] === "export") {
        // پرس‌وجوی جزئیات با گروه‌بندی شامل تاریخ، به‌منظور نمایش ریزگزارش در اکسل
        $queryDetailsExcel = "SELECT 
                                  hospital, fund, date, 
                                  COALESCE(SUM(received),0) AS total_received,
                                  COALESCE(SUM(returned),0) AS total_returned,
                                  COALESCE(SUM(balance),0) AS total_balance
                              FROM fund_performance
                              WHERE $whereClause
                              GROUP BY hospital, fund, date
                              ORDER BY hospital, fund, date";
        $stmtExcel = $conn->prepare($queryDetailsExcel);
        if ($stmtExcel === false) {
            die("خطا در پرس‌وجوی اکسل: " . $conn->error);
        }
        if (count($params) > 0) {
            $stmtExcel->bind_param($types, ...$params);
        }
        $stmtExcel->execute();
        $resultExcel = $stmtExcel->get_result();
        $excelData = [];
        while ($row = $resultExcel->fetch_assoc()) {
            $excelData[] = $row;
        }
        $stmtExcel->close();

        // تابع تعیین مکان سلول
        function setCellValueByColAndRow($sheet, $col, $row, $value)
        {
            $coordinate = Coordinate::stringFromColumnIndex((int)$col) . (int)$row;
            $sheet->setCellValue($coordinate, $value);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // تنظیم عنوان‌ها (از جمله تاریخ)
        $headers = ["بیمارستان", "نام صندوق", "تاریخ", "دریافتی", "برگشت", "مانده"];
        $rowNum = 1;
        foreach ($headers as $index => $headerText) {
            setCellValueByColAndRow($sheet, $index + 1, $rowNum, $headerText);
        }
        $rowNum = 2;
        foreach ($excelData as $data) {
            $col = 1;
            setCellValueByColAndRow($sheet, $col++, $rowNum, $data['hospital']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $data['fund']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $data['date']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $data['total_received']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $data['total_returned']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $data['total_balance']);
            $rowNum++;
        }

            if (ob_get_length()) {
                ob_end_clean();
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="doctor_salaries_report.xlsx"');
            header('Cache-Control: max-age=0');

            // 6. خروجی و پایان
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>عملکرد صندوق‌ها - داشبورد مدیر</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- بارگیری CSS مربوط به Bootstrap RTL و Font Awesome -->
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
            padding: 1rem;
            margin-top: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .calendar-popup {
            position: absolute;
            z-index: 9999;
            background: #fff;
            border: 1px solid #ccc;
            display: none;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- ستون سایدبار (منو) -->
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
                require 'Header.php';
                $header = new Header("عملکرد صندوق‌ها", $_SESSION["username"]);
                echo $header->render();
                ?>
                <div class="main-content">
                    <!-- فرم فیلتر -->
                    <form method="post" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">تاریخ شروع</label>
                                <input type="text" id="start_date" name="start_date" class="form-control" readonly
                                    placeholder="تاریخ شروع را انتخاب کنید"
                                    value="<?php echo htmlspecialchars($start_date_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="calendarPopupStart" class="calendar-popup"></div>
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">تاریخ پایان</label>
                                <input type="text" id="end_date" name="end_date" class="form-control" readonly
                                    placeholder="تاریخ پایان را انتخاب کنید"
                                    value="<?php echo htmlspecialchars($end_date_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="calendarPopupEnd" class="calendar-popup"></div>
                            </div>
                            <div class="col-md-3">
                                <label for="hospital" class="form-label">انتخاب بیمارستان</label>
                                <select name="hospital" id="hospital" class="form-select">
                                    <!-- حذف گزینه "همه" و اضافه کردن گزینه پیشفرض -->
                                    <option value="" disabled <?php echo (empty($hospital_val)) ? "selected" : ""; ?>>انتخاب کنید</option>
                                    <?php
                                    // حلقه روی لیست بیمارستان‌ها به صورت شی‌گرا
                                    foreach ($hospitals as $hospital) {
                                        $hospName = $hospital->getName();
                                        $selected = ($hospital_val == $hospName) ? "selected" : "";
                                        echo '<option value="' . htmlspecialchars($hospName, ENT_QUOTES, "UTF-8") . '" ' . $selected . '>'
                                            . htmlspecialchars($hospName, ENT_QUOTES, "UTF-8") .
                                            '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="fund" class="form-label">انتخاب صندوق</label>
                                <select name="fund" id="fund" class="form-select">
                                    <option value="all" <?php echo ($fund_val == "all") ? "selected" : ""; ?>>همه</option>
                                    <?php
                                    $connF = new mysqli("localhost", "root", "mysql", "dashboard_db");
                                    if ($connF->connect_error) {
                                        die("خطا در اتصال: " . $connF->connect_error);
                                    }
                                    $queryF = "SELECT DISTINCT fund FROM fund_performance ORDER BY fund";
                                    $resultF = $connF->query($queryF);
                                    while ($row = $resultF->fetch_assoc()) {
                                        $sel = ($fund_val == $row['fund']) ? "selected" : "";
                                        echo "<option value=\"" . htmlspecialchars($row['fund'], ENT_QUOTES, "UTF-8") . "\" $sel>" . htmlspecialchars($row['fund'], ENT_QUOTES, "UTF-8") . "</option>";
                                    }
                                    $connF->close();
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" name="action" value="filter" class="btn btn-primary">
                                <i class="fa fa-eye"></i> نمایش گزارش
                            </button>
                            <button type="submit" name="action" value="export" class="btn btn-success">
                                <i class="fa fa-file-excel-o"></i> خروجی اکسل
                            </button>
                        </div>
                    </form>

                    <!-- نمایش خلاصه عملکرد صندوق -->
                    <?php if (!empty($aggregateResults)): ?>
                        <hr>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">دریافتی:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['total_received'], 2); ?></p>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">برگشت:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['total_returned'], 2); ?></p>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">مانده:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['total_balance'], 2); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr>
                    <!-- نمایش جدول نهایی عملکرد صندوق (گروه‌بندی شده بر اساس بیمارستان و صندوق) -->
                    <?php if (!empty($allItems)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>بیمارستان</th>
                                        <th>نام صندوق</th>
                                        <th>دریافتی</th>
                                        <th>برگشت</th>
                                        <th>مانده</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['hospital'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($item['fund'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo number_format($item['total_received'], 2); ?></td>
                                            <td><?php echo number_format($item['total_returned'], 2); ?></td>
                                            <td><?php echo number_format($item['total_balance'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">هیچ رکوردی برای نمایش وجود ندارد.</p>
                    <?php endif; ?>
                </div><!-- end main-content -->
            </div><!-- end col-md-10 -->
        </div><!-- end row -->
    </div><!-- end container-fluid -->

    <!-- اسکریپت‌های مورد نیاز -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="persianCalendar.js"></script>

    <script>
        $(document).ready(function() {
            // اعتبارسنجی فرم
            $("form").on("submit", function(e) {
                var startDate = $("#start_date").val().trim();
                var endDate = $("#end_date").val().trim();
                var hospital = $("#hospital").val();

                if (startDate === "" || endDate === "") {
                    alert("لطفاً هر دو فیلد تاریخ شروع و پایان را پر کنید.");
                    e.preventDefault();
                    return;

                }
                if (endDate < startDate) {
                    alert("تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.");
                    e.preventDefault();
                    return;

                }
                if (!hospital) {
                    alert("لطفاً بیمارستان را انتخاب کنید.");
                    e.preventDefault();
                    return;
                }
            });


            // ایجاد نمونه تقویم شمسی برای تاریخ شروع
            const calendarStart = new PersianCalendar("calendarPopupStart", {
                initialYear: 1404,
                initialMonth: 0,
                onSelectDate: function(selectedDate) {
                    document.getElementById("start_date").value = selectedDate;
                    calendarStart.hide();
                }
            });
            document.getElementById("start_date").addEventListener("click", function() {
                const rect = this.getBoundingClientRect();
                const popup = document.getElementById("calendarPopupStart");
                popup.style.top = (rect.bottom + window.scrollY) + "px";
                popup.style.left = (rect.left + window.scrollX) + "px";
                calendarStart.show();
            });

            // ایجاد نمونه تقویم شمسی برای تاریخ پایان
            const calendarEnd = new PersianCalendar("calendarPopupEnd", {
                initialYear: 1404,
                initialMonth: 0,
                onSelectDate: function(selectedDate) {
                    document.getElementById("end_date").value = selectedDate;
                    calendarEnd.hide();
                }
            });
            document.getElementById("end_date").addEventListener("click", function() {
                const rect = this.getBoundingClientRect();
                const popup = document.getElementById("calendarPopupEnd");
                popup.style.top = (rect.bottom + window.scrollY) + "px";
                popup.style.left = (rect.left + window.scrollX) + "px";
                calendarEnd.show();
            });

            // به‌روز‌رسانی صندوق‌ها هنگام تغییر بیمارستان
            $('#hospital').change(function() {
                var hospital = $(this).val();
                $.ajax({
                    url: '?action=getFunds',
                    type: 'GET',
                    data: {
                        hospital: hospital
                    },
                    cache: false,
                    dataType: 'json',
                    success: function(data) {
                        var fundSelect = $('#fund');
                        fundSelect.empty();
                        fundSelect.append($('<option>', {
                            value: 'all',
                            text: 'همه'
                        }));
                        $.each(data, function(i, item) {
                            fundSelect.append($('<option>', {
                                value: item.fund,
                                text: item.fund
                            }));
                        });
                    },
                    error: function() {
                        console.error("خطا در دریافت صندوق‌ها.");
                    }
                });
            });
        });
    </script>
</body>

</html>