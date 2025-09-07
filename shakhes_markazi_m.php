<?php
session_start();

// بررسی ورود کاربر
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// بارگذاری کتابخانه‌ها و کلاس‌های مورد نیاز
require 'vendor/autoload.php';
require 'Header.php'; // کلاس هدر
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
    $coordinate = Coordinate::stringFromColumnIndex((int) $col) . (int) $row;
    $sheet->setCellValue($coordinate, $value);
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

$results = [];   // نتایج کلی (Aggregate)
$allItems = [];   // تمام رکوردهای پیدا شده (جزئیات)
$message = "";

// دریافت اطلاعات فرم فیلتر (تاریخ شروع، پایان و بیمارستان)
$start_date_val = $_POST["start_date"] ?? "";
$end_date_val = $_POST["end_date"] ?? "";
$hospital_val = $_POST["hospital"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {

    $start_date = $_POST["start_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";
    $hospital = $_POST["hospital"] ?? "";

    // اعتبارسنجی: بررسی خالی بودن تاریخ‌ها و انتخاب بیمارستان
    if (empty($start_date) || empty($end_date)) {
        $message = "لطفاً هر دو تاریخ شروع و پایان را انتخاب کنید.";
    } elseif ($end_date < $start_date) {
        $message = "تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.";
    } elseif (empty($hospital)) {
        $message = "لطفاً بیمارستان را انتخاب کنید.";
    } else {
        // جداکردن سال و ماه از تاریخ‌های انتخاب شده
        list($start_year, $start_month) = explode("-", $start_date);
        list($end_year, $end_month) = explode("-", $end_date);
        $start_year_int = (int) $start_year;
        $start_month_int = (int) $start_month;
        $end_year_int = (int) $end_year;
        $end_month_int = (int) $end_month;
        $numMonths = (($end_year_int * 12 + $end_month_int) - ($start_year_int * 12 + $start_month_int)) + 1;
        $numMonths = $numMonths > 0 ? $numMonths : 1;

        // پرس و جو Aggregate (جمع شاخص‌های عددی)
        $query = "SELECT 
                    SUM(tedad_morajein) AS morajein_sum,
                    SUM(tedad_khodmat) AS khodmat_sum,
                    SUM(tedad_basti) AS basti_sum,
                    SUM(tedad_amal) AS amal_sum,
                    SUM(tedad_k_amal) AS k_amal_sum,
                    SUM(tedad_k_bihoshi) AS k_bihoshi_sum,
                    SUM(nuskhe_sarpaei) AS nuskhe_sarpaei_sum,
                    SUM(visit_dandanpezeshki) AS visit_dandan_sum,
                    SUM(nuskhe_dandanpezeshki) AS dandan_sum,
                    SUM(mablagh_riali_daramad_bimarestan) AS bimarestan_sum,
                    SUM(mablagh_kasoorat) AS kasoorat_sum,
                    SUM(talab_az_bimeha) AS talab_sum,
                    SUM(daramad_daru_khane) AS daramad_daru_sum,
                    SUM(daramad_pezeshkilinikha) AS daramad_pezeshki_sum,
                    SUM(tedad_visit_sarpai) AS visit_sarpai_sum,
                    SUM(darib_eshghal_takht) AS darib_sum,
                    SUM(fasele_gardesh_takht) AS fasele_sum,
                    SUM(motevaset_moddat_eikhtelaf_bimaran) AS motavaset_sum
                  FROM central_indicators
                  WHERE hospital = ? 
                    AND CONCAT(year, '-', `month`) BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $hospital, $start_date, $end_date);
        $stmt->execute();
        $resAgg = $stmt->get_result();
        if ($resAgg) {
            $results = $resAgg->fetch_assoc();
            // محاسبه میانگین ماهیانه برای ستون‌های میانگین
            if ($numMonths > 0) {
                $results['darib_sum'] = (float) $results['darib_sum'] / $numMonths;
                $results['fasele_sum'] = (float) $results['fasele_sum'] / $numMonths;
                $results['motavaset_sum'] = (float) $results['motavaset_sum'] / $numMonths;
            }
        }
        $stmt->close();

        // دریافت اطلاعات تفصیلی رکوردها
        $queryItems = "SELECT * FROM central_indicators
                       WHERE hospital = ? 
                         AND CONCAT(year, '-', `month`) BETWEEN ? AND ?
                       ORDER BY year, `month`, shakhes_ha";
        $stmtItems = $conn->prepare($queryItems);
        $stmtItems->bind_param("sss", $hospital, $start_date, $end_date);
        $stmtItems->execute();
        $resItems = $stmtItems->get_result();
        while ($row = $resItems->fetch_assoc()) {
            $allItems[] = $row;
        }
        $stmtItems->close();

        // محاسبه Mode شاخص (از ستون shakhes_ha رکوردهای تفصیلی)
        $modeCounts = [];
        foreach ($allItems as $itm) {
            $indicator = $itm['shakhes_ha'];
            if (isset($modeCounts[$indicator])) {
                $modeCounts[$indicator]++;
            } else {
                $modeCounts[$indicator] = 1;
            }
        }
        $modeIndicator = "";
        $maxCount = 0;
        foreach ($modeCounts as $indicator => $count) {
            if ($count > $maxCount) {
                $maxCount = $count;
                $modeIndicator = $indicator;
            }
        }

        // در صورت کلیک روی "export"، خروجی اکسل تولید می‌شود.
        if ($_POST["action"] === "export") {
            $queryMonthly = "SELECT year, `month` AS month_val, hospital,
                    MAX(shakhes_ha) AS shakhes,
                    SUM(tedad_morajein) AS morajein,
                    SUM(tedad_khodmat) AS khodmat,
                    SUM(tedad_basti) AS basti,
                    SUM(tedad_amal) AS amal,
                    SUM(tedad_k_amal) AS k_amal,
                    SUM(tedad_k_bihoshi) AS k_bihoshi,
                    SUM(nuskhe_sarpaei) AS nuskhe_sarpaei,
                    SUM(visit_dandanpezeshki) AS visit_dandan,
                    SUM(nuskhe_dandanpezeshki) AS dandan,
                    SUM(mablagh_riali_daramad_bimarestan) AS bimarestan,
                    SUM(mablagh_kasoorat) AS kasoorat,
                    SUM(talab_az_bimeha) AS talab,
                    SUM(daramad_daru_khane) AS daramad_daru,
                    SUM(daramad_pezeshkilinikha) AS daramad_pezeshki,
                    SUM(tedad_visit_sarpai) AS visit_sarpai,
                    AVG(darib_eshghal_takht) AS darib,
                    AVG(fasele_gardesh_takht) AS fasele,
                    AVG(motevaset_moddat_eikhtelaf_bimaran) AS motavaset
             FROM central_indicators
             WHERE hospital = ? AND CONCAT(year, '-', `month`) BETWEEN ? AND ?
             GROUP BY hospital, year, `month`
             ORDER BY year, `month`";
            $stmtMonthly = $conn->prepare($queryMonthly);
            $stmtMonthly->bind_param("sss", $hospital, $start_date, $end_date);
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

            // تنظیم هدرهای ستون
            $headers = [
                "بیمارستان",
                "سال",
                "ماه",
                "شاخص‌ها",
                "تعداد مراجعه",
                "تعداد خدمت پاراکلینیک",
                "تعداد بستری",
                "تعداد عمل",
                "تعداد K عمل",
                "تعداد K بیهوشی",
                "نسخ سرپایی داروخانه",
                "ویزیت دندانپزشکی",
                "نسخ دندانپزشکی",
                "درآمد بیمارستان",
                "کسورات",
                "طلب بیمه‌ها",
                "درآمد داروخانه",
                "درآمد پاراکلینیک",
                "ویزیت سرپایی",
                "ضریب اشغال تخت",
                "فاصله گردش تخت",
                "متوسط مدت اقامت"
            ];

            $rowNum = 1;
            foreach ($headers as $index => $headerText) {
                setCellValueByColAndRow($sheet, $index + 1, $rowNum, $headerText);
            }

            // درج داده‌های ماهانه
            $rowNum = 2;
            foreach ($monthlyData as $md) {
                $col = 1;
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['hospital']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['year']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['month_val']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['shakhes']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['morajein']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['khodmat']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['basti']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['amal']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['k_amal']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['k_bihoshi']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['nuskhe_sarpaei']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['visit_dandan']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['dandan']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['bimarestan']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['kasoorat']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['talab']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['daramad_daru']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['daramad_pezeshki']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['visit_sarpai']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['darib']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['fasele']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $md['motavaset']);
                $rowNum++;
            }

            // ردیف نهایی (خلاصه)
            $col = 1;
            setCellValueByColAndRow($sheet, $col++, $rowNum, $hospital);
            setCellValueByColAndRow($sheet, $col++, $rowNum, "کل");
            setCellValueByColAndRow($sheet, $col++, $rowNum, "");
            setCellValueByColAndRow($sheet, $col++, $rowNum, $modeIndicator);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['morajein_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['khodmat_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['basti_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['amal_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['k_amal_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['k_bihoshi_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['nuskhe_sarpaei_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['visit_dandan_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['dandan_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['bimarestan_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['kasoorat_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['talab_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['daramad_daru_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['daramad_pezeshki_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['visit_sarpai_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['darib_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['fasele_sum']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $results['motavaset_sum']);
            
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
    <title>گزارش شاخص‌های مرکزی - داشبورد مدیر</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- بارگذاری Bootstrap RTL -->
    <link href="css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- بارگذاری Font Awesome -->
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

        /* استایل اضافی برای جدول با هدر ثابت */
        .table thead {
            position: sticky;
            top: 0;
            background-color: #343a40;
            z-index: 1020;
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
                $header = new Header("گزارش شاخص‌های مرکزی", $_SESSION["username"]);
                echo $header->render();
                ?>
                <div class="main-content">
                    <!-- فرم فیلتر -->
                    <form method="post" action="">
                        <div class="row g-3">
                            <!-- فیلد تاریخ شروع -->
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">تاریخ شروع</label>
                                <input type="text" id="start_date" name="start_date" class="form-control" readonly
                                    placeholder="تاریخ شروع را انتخاب کنید"
                                    value="<?php echo htmlspecialchars($start_date_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="calendarPopupStart" class="calendar-popup"></div>
                            </div>
                            <!-- فیلد تاریخ پایان -->
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">تاریخ پایان</label>
                                <input type="text" id="end_date" name="end_date" class="form-control" readonly
                                    placeholder="تاریخ پایان را انتخاب کنید"
                                    value="<?php echo htmlspecialchars($end_date_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="calendarPopupEnd" class="calendar-popup"></div>
                            </div>
                            <!-- انتخاب بیمارستان (گزینه پیش‌فرض «انتخاب کنید» به جای «همه») -->
                            <div class="col-md-6">
                                <label for="hospital" class="form-label">انتخاب بیمارستان</label>
                                <select name="hospital" id="hospital" class="form-select">
                                    <option value="" <?php echo (empty($hospital_val)) ? "selected" : ""; ?>>انتخاب کنید
                                    </option>
                                    <?php
                                    // حلقه روی لیست بیمارستان‌ها به صورت شی‌گرا
                                    foreach ($hospitals as $hospitalObj) {
                                        // دریافت نام بیمارستان از شیء
                                        $hospName = $hospitalObj->getName();
                                        $selected = ($hospital_val == $hospName) ? "selected" : "";
                                        echo '<option value="' . htmlspecialchars($hospName, ENT_QUOTES, "UTF-8") . '" ' . $selected . '>'
                                            . htmlspecialchars($hospName, ENT_QUOTES, "UTF-8") .
                                            '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mt-3">
                                <button type="submit" name="action" value="filter" class="btn btn-primary">
                                    <i class="fa fa-eye"></i> نمایش گزارش
                                </button>
                                <button type="submit" name="action" value="export" class="btn btn-success">
                                    <i class="fa fa-file-excel-o"></i> خروجی اکسل
                                </button>
                            </div>
                        </div>
                    </form>
                    <hr>
                    <!-- نمایش نتایج Aggregate -->
                    <?php if (!empty($results)): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">تعداد مراجعه:</label>
                                <p class="form-control"><?php echo number_format($results['morajein_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تعداد خدمت پاراکلینیک:</label>
                                <p class="form-control"><?php echo number_format($results['khodmat_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تعداد بستری:</label>
                                <p class="form-control"><?php echo number_format($results['basti_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تعداد عمل:</label>
                                <p class="form-control"><?php echo number_format($results['amal_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تعداد K عمل:</label>
                                <p class="form-control"><?php echo number_format($results['k_amal_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تعداد K بیهوشی:</label>
                                <p class="form-control"><?php echo number_format($results['k_bihoshi_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">نسخ سرپایی داروخانه:</label>
                                <p class="form-control"><?php echo number_format($results['nuskhe_sarpaei_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ویزیت دندانپزشکی:</label>
                                <p class="form-control"><?php echo number_format($results['visit_dandan_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">نسخ دندانپزشکی:</label>
                                <p class="form-control"><?php echo number_format($results['dandan_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">درآمد بیمارستان:</label>
                                <p class="form-control"><?php echo number_format($results['bimarestan_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">کسورات:</label>
                                <p class="form-control"><?php echo number_format($results['kasoorat_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">طلب بیمه‌ها:</label>
                                <p class="form-control"><?php echo number_format($results['talab_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">درآمد داروخانه:</label>
                                <p class="form-control"><?php echo number_format($results['daramad_daru_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">درآمد پاراکلینیک:</label>
                                <p class="form-control"><?php echo number_format($results['daramad_pezeshki_sum'], 2); ?>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ویزیت سرپایی:</label>
                                <p class="form-control"><?php echo number_format($results['visit_sarpai_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ضریب اشغال تخت:</label>
                                <p class="form-control"><?php echo number_format($results['darib_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">فاصله گردش تخت:</label>
                                <p class="form-control"><?php echo number_format($results['fasele_sum'], 2); ?></p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">متوسط مدت اقامت:</label>
                                <p class="form-control"><?php echo number_format($results['motavaset_sum'], 2); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <hr>
                    <!-- نمایش جدول جزئیات رکوردها -->
                    <?php if (!empty($allItems)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>بیمارستان</th>
                                        <th>سال</th>
                                        <th>ماه</th>
                                        <th>شاخص‌ها</th>
                                        <th>تعداد مراجعه</th>
                                        <th>تعداد خدمت پاراکلینیک</th>
                                        <th>تعداد بستری</th>
                                        <th>تعداد عمل</th>
                                        <th>تعداد K عمل</th>
                                        <th>تعداد K بیهوشی</th>
                                        <th>نسخ سرپایی داروخانه</th>
                                        <th>ویزیت دندانپزشکی</th>
                                        <th>نسخ دندانپزشکی</th>
                                        <th>درآمد بیمارستان</th>
                                        <th>کسورات</th>
                                        <th>طلب بیمه‌ها</th>
                                        <th>درآمد داروخانه</th>
                                        <th>درآمد پاراکلینیک</th>
                                        <th>ویزیت سرپایی</th>
                                        <th>ضریب اشغال تخت</th>
                                        <th>فاصله گردش تخت</th>
                                        <th>متوسط مدت اقامت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allItems as $item): ?>
                                        <tr>
                                            <td><?php echo $item['hospital']; ?></td>
                                            <td><?php echo $item['year']; ?></td>
                                            <td><?php echo $item['month']; ?></td>
                                            <td><?php echo $item['shakhes_ha']; ?></td>
                                            <td><?php echo number_format($item['tedad_morajein'], 2); ?></td>
                                            <td><?php echo number_format($item['tedad_khodmat'], 2); ?></td>
                                            <td><?php echo number_format($item['tedad_basti'], 2); ?></td>
                                            <td><?php echo number_format($item['tedad_amal'], 2); ?></td>
                                            <td><?php echo number_format($item['tedad_k_amal'], 2); ?></td>
                                            <td><?php echo number_format($item['tedad_k_bihoshi'], 2); ?></td>
                                            <td><?php echo number_format($item['nuskhe_sarpaei'], 2); ?></td>
                                            <td><?php echo number_format($item['visit_dandanpezeshki'], 2); ?></td>
                                            <td><?php echo number_format($item['nuskhe_dandanpezeshki'], 2); ?></td>
                                            <td><?php echo number_format($item['mablagh_riali_daramad_bimarestan'], 2); ?></td>
                                            <td><?php echo number_format($item['mablagh_kasoorat'], 2); ?></td>
                                            <td><?php echo number_format($item['talab_az_bimeha'], 2); ?></td>
                                            <td><?php echo number_format($item['daramad_daru_khane'], 2); ?></td>
                                            <td><?php echo number_format($item['daramad_pezeshkilinikha'], 2); ?></td>
                                            <td><?php echo number_format($item['tedad_visit_sarpai'], 2); ?></td>
                                            <td><?php echo number_format($item['darib_eshghal_takht'], 2); ?></td>
                                            <td><?php echo number_format($item['fasele_gardesh_takht'], 2); ?></td>
                                            <td><?php echo number_format($item['motevaset_moddat_eikhtelaf_bimaran'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">هیچ رکوردی برای نمایش یافت نشد.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- اسکریپت‌های مورد نیاز -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function () {
            $("form").on("submit", function (e) {
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
        });
    </script>
    <!-- بارگذاری اسکریپت‌های مورد نیاز -->
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="MonthYearPicker.js"></script>
    <script>
        // راه‌اندازی تقویم برای فیلد تاریخ شروع
        new MonthYearPicker("start_date", {
            initialYear: 1404,
            onSelectMonth: function (month, year) {
                let numericMonth = month < 10 ? '0' + month : month;
                this.triggerElement.value = `${year}-${numericMonth}`;
            }
        });

        // راه‌اندازی تقویم برای فیلد تاریخ پایان
        new MonthYearPicker("end_date", {
            initialYear: 1404,
            onSelectMonth: function (month, year) {
                let numericMonth = month < 10 ? '0' + month : month;
                this.triggerElement.value = `${year}-${numericMonth}`;
            }
        });
    </script>
</body>

</html>