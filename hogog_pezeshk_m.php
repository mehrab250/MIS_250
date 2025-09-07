<?php
session_start();

// بررسی ورود کاربر
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// بارگذاری کتابخانه‌ها و کلاس‌های مورد نیاز
require 'vendor/autoload.php';
require 'Header.php';   // کلاس هدر
require_once 'HospitalRepository.php'; // بارگذاری repository مربوط به بیمارستان‌ها
require 'Menu_m.php';
require 'Menu_add.php';
require 'Menu_omor.php';
require 'Menu_pirapezeshk.php';
$hospitals = HospitalRepository::getHospitals();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// تابع کمکی جهت درج مقدار سلول بر اساس شماره ستون و ردیف
function setCellValueByColAndRow($sheet, $col, $row, $value)
{
    $coordinate = Coordinate::stringFromColumnIndex((int)$col) . (int)$row;
    $sheet->setCellValue($coordinate, $value);
}

function convertDate($date)
{
    return $date; // فرض بر این است که فرمت ورودی "YYYY/MM/DD" می‌باشد.
}

// اتصال به دیتابیس
$dbhost = "localhost";
$dbuser = "root";
$dbpass = "mysql"; // مقدار صحیح پسورد را وارد کنید
$dbname = "dashboard_db";

$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("خطا در اتصال به دیتابیس: " . $conn->connect_error);
}

$results  = [];   // نتایج Aggregate (خلاصه حقوق)
$allItems = [];   // رکوردهای گروه‌بندی شده ماهانه
$message  = "";

// دریافت مقادیر فرم
$start_date_val = $_POST["start_date"] ?? "";
$end_date_val   = $_POST["end_date"] ?? "";
$hospital_val   = $_POST["hospital"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {

    // دریافت ورودی‌های فرم
    $start_date = $_POST["start_date"] ?? "";
    $end_date   = $_POST["end_date"] ?? "";
    $hospital   = $_POST["hospital"] ?? "";

    // اعتبارسنجی ورودی‌ها (بیمارستان نیز به عنوان فیلد اجباری در نظر گرفته شده)
    if (empty($start_date) || empty($end_date)) {
        $message = "فیلدهای تاریخ شروع و پایان باید پر شود.";
    } elseif ($end_date < $start_date) {
        $message = "تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.";
    } elseif (empty($hospital)) {
        $message = "لطفاً بیمارستان را انتخاب کنید.";
    } else {
        // استفاده از همان فرمت تاریخ
        $start_date_conv = $start_date;
        $end_date_conv   = $end_date;

        // کوئری Aggregate
        $queryAgg = "SELECT 
                        SUM(total) AS total_sum, 
                        SUM(gross) AS gross_sum, 
                        SUM(tax) AS tax_sum, 
                        SUM(parkis) AS parkis_sum, 
                        SUM(net) AS net_sum
                     FROM doctor_salaries
                     WHERE hospital = ? 
                       AND date BETWEEN ? AND ?";
        $stmt = $conn->prepare($queryAgg);
        $stmt->bind_param("sss", $hospital, $start_date_conv, $end_date_conv);
        $stmt->execute();
        $resAgg = $stmt->get_result();
        if ($resAgg) {
            $results = $resAgg->fetch_assoc();
        }
        $stmt->close();

        // کوئری جزئیات برای گروه‌بندی ماهانه
        $queryDetails = "SELECT hospital,
                              LEFT(date,7) AS month_year,
                              SUM(total) AS total,
                              SUM(gross) AS gross,
                              SUM(tax) AS tax,
                              SUM(parkis) AS parkis,
                              SUM(net) AS net
                         FROM doctor_salaries
                         WHERE hospital = ? 
                           AND date BETWEEN ? AND ?
                         GROUP BY hospital, LEFT(date,7)
                         ORDER BY LEFT(date,7)";
        $stmtDet = $conn->prepare($queryDetails);
        $stmtDet->bind_param("sss", $hospital, $start_date_conv, $end_date_conv);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();
        while ($row = $resDet->fetch_assoc()) {
            $allItems[] = $row;
        }
        $stmtDet->close();

        // خروجی اکسل با همان منطق گروه‌بندی
        if ($_POST["action"] === "export") {
            $queryMonthly = $queryDetails;
            $stmtMonthly = $conn->prepare($queryMonthly);
            $stmtMonthly->bind_param("sss", $hospital, $start_date_conv, $end_date_conv);
            $stmtMonthly->execute();
            $resultMonthly = $stmtMonthly->get_result();
            $monthlyData = [];
            while ($row = $resultMonthly->fetch_assoc()) {
                $monthlyData[] = $row;
            }
            $stmtMonthly->close();

            // ایجاد خروجی اکسل
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = [
                "بیمارستان",
                "ماه/سال",
                "جمع کل",
                "ناخالص",
                "مالیات",
                "پرکيس",
                "خالص"
            ];
            $rowNum = 1;
            foreach ($headers as $index => $headerText) {
                setCellValueByColAndRow($sheet, $index + 1, $rowNum, $headerText);
            }

            $rowNum = 2;
            foreach ($monthlyData as $md) {
                $col = 1;
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['hospital']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['month_year']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['total']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['gross']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['tax']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['parkis']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['net']);
                $rowNum++;
            }

            $col = 1;
            setCellValueByColAndRow($sheet, $col++, $rowNum, $hospital);
            setCellValueByColAndRow($sheet, $col++, $rowNum, "کل");
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['total_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['gross_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['tax_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['parkis_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['net_sum']);

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
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>گزارش حقوق پزشکان - داشبورد مدیر</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- بارگذاری Bootstrap RTL و Font Awesome -->
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
            <!-- ستون منو (سایدبار) -->
            <div class="col-md-2">
                <?php
                // استفاده از MenuFactory جهت نمایش منوی متناسب با سطح دسترسی
                $username = $_SESSION["username"];
                $user_type = $_SESSION["user_type"];
                $logo = "logo.png";

                class MenuFactory {
                    public static function createMenu($username, $user_type, $logo) {
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
                $header = new Header("گزارش حقوق پزشکان", $_SESSION["username"]);
                echo $header->render();
                ?>
                <div class="main-content">
                    <!-- نمایش پیام خطا -->
                    <?php if(!empty($message)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
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
                            <div class="col-md-6">
                                <label for="hospital" class="form-label">انتخاب بیمارستان</label>
                                <select name="hospital" id="hospital" class="form-select">
                                    <option value="" <?php echo ($hospital_val == "") ? "selected" : ""; ?> disabled>انتخاب کنید</option>
                                    <?php
                                    // نمایش لیست بیمارستان‌ها به صورت شی‌گرا
                                    foreach ($hospitals as $hospitalObj) {
                                        $hospName = $hospitalObj->getName();
                                        $selected = ($hospital_val == $hospName) ? "selected" : "";
                                        echo '<option value="' . htmlspecialchars($hospName, ENT_QUOTES, "UTF-8") . '" ' . $selected . '>'
                                            . htmlspecialchars($hospName, ENT_QUOTES, "UTF-8") .
                                            '</option>';
                                    }
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
                    <hr>
                    <?php if (!empty($results)): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">جمع کل:</label>
                                <p class="form-control"><?php echo number_format($results['total_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ناخالص:</label>
                                <p class="form-control"><?php echo number_format($results['gross_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">مالیات:</label>
                                <p class="form-control"><?php echo number_format($results['tax_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">پرکيس:</label>
                                <p class="form-control"><?php echo number_format($results['parkis_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">خالص:</label>
                                <p class="form-control"><?php echo number_format($results['net_sum'], 2); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <hr>
                    <?php if (!empty($allItems)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>بیمارستان</th>
                                        <th>ماه/سال</th>
                                        <th>جمع کل</th>
                                        <th>ناخالص</th>
                                        <th>مالیات</th>
                                        <th>پرکيس</th>
                                        <th>خالص</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['hospital'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($item['month_year'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo number_format($item['total'], 2); ?></td>
                                            <td><?php echo number_format($item['gross'], 2); ?></td>
                                            <td><?php echo number_format($item['tax'], 2); ?></td>
                                            <td><?php echo number_format($item['parkis'], 2); ?></td>
                                            <td><?php echo number_format($item['net'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">هیچ رکوردی برای نمایش یافت نشد.</p>
                    <?php endif; ?>
                </div> <!-- end main-content -->
            </div> <!-- end col-md-10 -->
        </div> <!-- end row -->
    </div> <!-- end container-fluid -->

    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="persianCalendar.js"></script>
    <script>
        $(document).ready(function(){
            $("form").on("submit", function(e){
                var startDate = $("#start_date").val().trim();
                var endDate = $("#end_date").val().trim();
                var hospital = $("#hospital").val();
                if(startDate === "" || endDate === ""){
                    alert("لطفاً هر دو فیلد تاریخ شروع و پایان را پر کنید.");
                    e.preventDefault();
                    return;
                }
                if(endDate < startDate){
                    alert("تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.");
                    e.preventDefault();
                    return;
                }
                if(!hospital){
                    alert("لطفاً بیمارستان را انتخاب کنید.");
                    e.preventDefault();
                    return;
                }
            });
        });

        const calendarStart = new PersianCalendar("calendarPopupStart", {
            initialYear: 1404,
            initialMonth: 0,
            onSelectDate: function(selectedDate) {
                document.getElementById("start_date").value = selectedDate;
                calendarStart.hide();
            }
        });
        document.getElementById("start_date").addEventListener("click", function(){
            const rect = this.getBoundingClientRect();
            const popup = document.getElementById("calendarPopupStart");
            popup.style.top = (rect.bottom + window.scrollY) + "px";
            popup.style.left = (rect.left + window.scrollX) + "px";
            calendarStart.show();
        });

        const calendarEnd = new PersianCalendar("calendarPopupEnd", {
            initialYear: 1404,
            initialMonth: 0,
            onSelectDate: function(selectedDate) {
                document.getElementById("end_date").value = selectedDate;
                calendarEnd.hide();
            }
        });
        document.getElementById("end_date").addEventListener("click", function(){
            const rect = this.getBoundingClientRect();
            const popup = document.getElementById("calendarPopupEnd");
            popup.style.top = (rect.bottom + window.scrollY) + "px";
            popup.style.left = (rect.left + window.scrollX) + "px";
            calendarEnd.show();
        });
    </script>
</body>
</html>
