<?php
ob_start();  // فعال‌سازی بافر خروجی

session_start();
// بررسی ورود کاربر
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// بارگذاری کتابخانه‌ها و کلاس‌های مورد نیاز
require 'vendor/autoload.php';
require 'Header.php';
require_once 'HospitalRepository.php';
require 'Menu_m.php';
require 'Menu_add.php';
require 'Menu_omor.php';
require 'Menu_pirapezeshk.php';

$hospitals = HospitalRepository::getHospitals();

// استفاده از کتابخانه‌های PhpSpreadsheet جهت خروجی اکسل
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// دریافت مقادیر فرم برای حفظ انتخاب‌های قبلی
$start_date_val = $_POST["start_date"] ?? "";
$end_date_val   = $_POST["end_date"] ?? "";
// در انتخاب بیمارستان، مقدار پیش‌فرض به صورت خالی است تا گزینه پیش‌فرض "انتخاب کنید" نمایش داده شود.
$hospital_val   = $_POST["hospital"] ?? "";
$paraclinic_val = $_POST["paraclinic"] ?? "all";
$service_val    = $_POST["service"] ?? "all";

// تنظیم اطلاعات اتصال به دیتابیس
$dbhost = "localhost";
$dbuser = "root";
$dbpass = "mysql";
$dbname = "dashboard_db";

// برقراری اتصال به دیتابیس
$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die("خطا در اتصال به دیتابیس: " . $conn->connect_error);
}

// پردازش درخواست‌های AJAX (برای دریافت پاراکلینیک و سرویس‌ها)
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // دریافت لیست پاراکلینیک‌ها بر اساس بیمارستان
    if ($action == 'getparaclinic' && isset($_GET['hospital'])) {
        header("Content-Type: application/json; charset=utf-8");
        if (ob_get_length()) ob_clean();
        $hospital = $_GET['hospital'];
        // چون بیمارستان همیشه انتخاب شده باشد (گزینه "انتخاب کنید" با مقدار خالی)، در صورتی که مقدار خالی دریافت شود، خروجی خالی برگردانیم.
        if (empty($hospital)) {
            echo json_encode([]);
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT TRIM(paraclinic) AS paraclinic, paraclinic_code 
                                    FROM serv_paraclinic_stats 
                                    WHERE TRIM(hospital)=? 
                                    ORDER BY paraclinic");
            $stmt->bind_param("s", $hospital);
            $stmt->execute();
            $result = $stmt->get_result();
            $paraclinics = [];
            while ($row = $result->fetch_assoc()) {
                $paraclinics[] = $row;
            }
            echo json_encode($paraclinics);
            $stmt->close();
        }
        $conn->close();
        exit;
    }

    // دریافت لیست سرویس‌ها بر اساس بیمارستان و پاراکلینیک انتخاب شده
    if ($action == 'getservice' && isset($_GET['hospital']) && isset($_GET['paraclinic'])) {
        header("Content-Type: application/json; charset=utf-8");
        if (ob_get_length()) ob_clean();
        $hospital   = $_GET['hospital'];
        $paraclinic = $_GET['paraclinic'];

        if (empty($hospital)) {
            echo json_encode([]);
        } elseif ($paraclinic == 'all') {
            $stmt = $conn->prepare("SELECT DISTINCT TRIM(service_name) AS service_name 
                                    FROM serv_paraclinic_stats 
                                    WHERE TRIM(hospital)=? 
                                    ORDER BY service_name");
            $stmt->bind_param("s", $hospital);
            $stmt->execute();
            $result = $stmt->get_result();
            $services = [];
            while ($row = $result->fetch_assoc()) {
                $services[] = $row;
            }
            echo json_encode($services);
            $stmt->close();
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT TRIM(service_name) AS service_name 
                                    FROM serv_paraclinic_stats 
                                    WHERE TRIM(hospital)=? AND TRIM(paraclinic_code)=? 
                                    ORDER BY service_name");
            $stmt->bind_param("ss", $hospital, $paraclinic);
            $stmt->execute();
            $result = $stmt->get_result();
            $services = [];
            while ($row = $result->fetch_assoc()) {
                $services[] = $row;
            }
            echo json_encode($services);
            $stmt->close();
        }
        $conn->close();
        exit;
    }
}

// آرایه جهت نگهداری نتایج پرس‌وجو (Pivot شده)
$details = [];
$aggregate = [
    'farvardin'   => 0,
    'ordibehesht' => 0,
    'khordad'     => 0,
    'tir'         => 0,
    'mordad'      => 0,
    'shahrivar'   => 0,
    'mehr'        => 0,
    'aban'        => 0,
    'azar'        => 0,
    'dey'         => 0,
    'bahman'      => 0,
    'esfand'      => 0,
];
$message = "";

// پردازش فرم در صورت ارسال (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $start_date = $_POST["start_date"] ?? "";
    $end_date   = $_POST["end_date"] ?? "";

    // اگر بیمارستان انتخاب نشده باشد، پیام خطا نشان داده شود
    if (empty($_POST["hospital"])) {
        $message = "لطفاً بیمارستان را انتخاب کنید.";
    } elseif (empty($start_date) || empty($end_date)) {
        $message = "لطفاً هر دو تاریخ شروع و پایان را انتخاب کنید.";
    } elseif ($end_date < $start_date) {
        $message = "تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.";
    } else {
        $hospital   = $_POST["hospital"];  // اکنون مقدار بیمارستان همیشه غیر تهی خواهد بود
        $paraclinic = $_POST["paraclinic"] ?? "all";
        $service    = $_POST["service"] ?? "all";

        $conditions = [];
        $params     = [];
        $types      = "";
        $conditions[] = "TRIM(hospital)=?";
        $params[]     = $hospital;
        $types       .= "s";

        if ($paraclinic !== "all") {
            $conditions[] = "TRIM(paraclinic_code)=?";
            $params[]     = $paraclinic;
            $types       .= "s";
        }
        if ($service !== "all") {
            $conditions[] = "TRIM(service_name)=?";
            $params[]     = $service;
            $types       .= "s";
        }
        $conditions[] = "CONCAT(year, '-', LPAD(month, 2, '0')) BETWEEN ? AND ?";
        $params[]     = $start_date;
        $params[]     = $end_date;
        $types       .= "ss";

        $where = " WHERE " . implode(" AND ", $conditions);

        $query = "SELECT paraclinic, service_name, year,
            SUM(CASE WHEN LPAD(month, 2, '0') = '01' THEN count_moraje ELSE 0 END) AS farvardin,
            SUM(CASE WHEN LPAD(month, 2, '0') = '02' THEN count_moraje ELSE 0 END) AS ordibehesht,
            SUM(CASE WHEN LPAD(month, 2, '0') = '03' THEN count_moraje ELSE 0 END) AS khordad,
            SUM(CASE WHEN LPAD(month, 2, '0') = '04' THEN count_moraje ELSE 0 END) AS tir,
            SUM(CASE WHEN LPAD(month, 2, '0') = '05' THEN count_moraje ELSE 0 END) AS mordad,
            SUM(CASE WHEN LPAD(month, 2, '0') = '06' THEN count_moraje ELSE 0 END) AS shahrivar,
            SUM(CASE WHEN LPAD(month, 2, '0') = '07' THEN count_moraje ELSE 0 END) AS mehr,
            SUM(CASE WHEN LPAD(month, 2, '0') = '08' THEN count_moraje ELSE 0 END) AS aban,
            SUM(CASE WHEN LPAD(month, 2, '0') = '09' THEN count_moraje ELSE 0 END) AS azar,
            SUM(CASE WHEN month = '10' THEN count_moraje ELSE 0 END) AS dey,
            SUM(CASE WHEN month = '11' THEN count_moraje ELSE 0 END) AS bahman,
            SUM(CASE WHEN month = '12' THEN count_moraje ELSE 0 END) AS esfand
            FROM serv_paraclinic_stats $where
            GROUP BY paraclinic, service_name, year
            ORDER BY paraclinic, service_name, year";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            die("خطا در آماده‌سازی کوئری: " . $conn->error);
        }
        if (count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $details[] = $row;
            $aggregate['farvardin']   += $row['farvardin'];
            $aggregate['ordibehesht'] += $row['ordibehesht'];
            $aggregate['khordad']     += $row['khordad'];
            $aggregate['tir']         += $row['tir'];
            $aggregate['mordad']      += $row['mordad'];
            $aggregate['shahrivar']   += $row['shahrivar'];
            $aggregate['mehr']        += $row['mehr'];
            $aggregate['aban']        += $row['aban'];
            $aggregate['azar']        += $row['azar'];
            $aggregate['dey']         += $row['dey'];
            $aggregate['bahman']      += $row['bahman'];
            $aggregate['esfand']      += $row['esfand'];
        }
        $stmt->close();

        if ($_POST["action"] === "export") {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            function setCellValueByColAndRow($sheet, $col, $row, $value)
            {
                $coordinate = Coordinate::stringFromColumnIndex((int)$col) . (int)$row;
                $sheet->setCellValue($coordinate, $value);
            }

            $headers = [
                "پاراکلینیک",
                "نام سرویس",
                "سال",
                "فروردین",
                "اردیبهشت",
                "خرداد",
                "تیر",
                "مرداد",
                "شهریور",
                "مهر",
                "آبان",
                "آذر",
                "دی",
                "بهمن",
                "اسفند",
                "تعداد مراجعین"
            ];

            $rowNum = 1;
            $col = 1;
            foreach ($headers as $headerText) {
                setCellValueByColAndRow($sheet, $col++, $rowNum, $headerText);
            }

            $rowNum = 2;
            foreach ($details as $dataRow) {
                $col = 1;
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["paraclinic"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["service_name"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["year"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["farvardin"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["ordibehesht"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["khordad"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["tir"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["mordad"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["shahrivar"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["mehr"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["aban"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["azar"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["dey"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["bahman"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["esfand"]);
                $total = $dataRow["farvardin"] + $dataRow["ordibehesht"] + $dataRow["khordad"] +
                    $dataRow["tir"] + $dataRow["mordad"] + $dataRow["shahrivar"] +
                    $dataRow["mehr"] + $dataRow["aban"] + $dataRow["azar"] +
                    $dataRow["dey"] + $dataRow["bahman"] + $dataRow["esfand"];
                setCellValueByColAndRow($sheet, $col++, $rowNum, $total);
                $rowNum++;
            }

            $rowNum++;
            $col = 1;
            setCellValueByColAndRow($sheet, $col++, $rowNum, "کل");
            setCellValueByColAndRow($sheet, $col++, $rowNum, "");
            setCellValueByColAndRow($sheet, $col++, $rowNum, "");
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['farvardin']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['ordibehesht']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['khordad']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['tir']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['mordad']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['shahrivar']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['mehr']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['aban']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['azar']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['dey']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['bahman']);
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate['esfand']);
            $aggregate_total = $aggregate['farvardin'] + $aggregate['ordibehesht'] + $aggregate['khordad'] +
                $aggregate['tir'] + $aggregate['mordad'] + $aggregate['shahrivar'] +
                $aggregate['mehr'] + $aggregate['aban'] + $aggregate['azar'] +
                $aggregate['dey'] + $aggregate['bahman'] + $aggregate['esfand'];
            setCellValueByColAndRow($sheet, $col++, $rowNum, $aggregate_total);

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
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>تعداد سرویس‌های پاراکلینیک</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- بارگذاری Bootstrap RTL -->
    <link href="css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- بارگذاری Font Awesome -->
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <!-- بارگذاری Select2 CSS -->
    <link href="css/select2.min.css" rel="stylesheet" />
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
            background-color: #fff;
            border: 1px solid #ccc;
            z-index: 1000;
        }

        /* بهبود ظاهر Select2 و جذاب‌تر کردن قسمت جستجو */
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            box-shadow: none;
            transition: border-color 0.3s, box-shadow 0.3s;
            font-family: Sahel, sans-serif;
            font-size: 1rem;
        }

        .select2-container .select2-selection--single:hover {
            border-color: #80bdff;
        }

        .select2-container .select2-selection--single:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 8px;
            padding-right: 8px;
            line-height: 38px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
            right: 8px;
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
                $header = new Header("تعداد سرویس‌های پاراکلینیک", $_SESSION["username"]);
                echo $header->render();
                ?>
                <div class="main-content">

                    <!-- فرم فیلتر -->
                    <form method="post" action="">
                        <div class="row g-3">
                            <!-- فیلد تاریخ شروع -->
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">تاریخ شروع</label>
                                <input type="text" id="start_date" name="start_date" class="form-control" readonly placeholder="تاریخ شروع را انتخاب کنید" value="<?php echo htmlspecialchars($start_date_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="calendarPopupStart" class="calendar-popup"></div>
                            </div>
                            <!-- فیلد تاریخ پایان -->
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">تاریخ پایان</label>
                                <input type="text" id="end_date" name="end_date" class="form-control" readonly placeholder="تاریخ پایان را انتخاب کنید" value="<?php echo htmlspecialchars($end_date_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="calendarPopupEnd" class="calendar-popup"></div>
                            </div>
                            <!-- انتخاب بیمارستان (گزینه پیش‌فرض "انتخاب کنید") -->
                            <div class="col-md-3">
                                <label for="hospital" class="form-label">انتخاب بیمارستان</label>
                                <select name="hospital" id="hospital" class="form-select">
                                    <option value="">انتخاب کنید</option>
                                    <?php foreach ($hospitals as $hosp):
                                        $hospName = $hosp->getName();
                                        $selected = ($hospital_val == $hospName) ? "selected" : "";
                                    ?>
                                        <option value="<?php echo htmlspecialchars($hospName, ENT_QUOTES, "UTF-8"); ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($hospName, ENT_QUOTES, "UTF-8"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- انتخاب پاراکلینیک -->
                            <div class="col-md-3">
                                <label for="paraclinic" class="form-label">پاراکلینیک</label>
                                <select name="paraclinic" id="paraclinic" class="form-select">
                                    <option value="all">همه</option>
                                </select>
                            </div>
                            <!-- انتخاب نام سرویس (جستجوپذیر با Select2) -->
                            <div class="col-md-6 mt-3">
                                <label for="service" class="form-label">نام سرویس</label>
                                <select name="service" id="service" class="form-select select2">
                                    <option value="all">همه</option>
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
                    <!-- نمایش مجموع کل ستون‌های آماری در بالای جدول -->
                    <?php if (!empty($details)): ?>
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label class="form-label">فروردین:</label>
                                <p class="form-control"><?php echo number_format($aggregate['farvardin'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">اردیبهشت:</label>
                                <p class="form-control"><?php echo number_format($aggregate['ordibehesht'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">خرداد:</label>
                                <p class="form-control"><?php echo number_format($aggregate['khordad'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">تیر:</label>
                                <p class="form-control"><?php echo number_format($aggregate['tir'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">مرداد:</label>
                                <p class="form-control"><?php echo number_format($aggregate['mordad'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">شهریور:</label>
                                <p class="form-control"><?php echo number_format($aggregate['shahrivar'], 0); ?></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label class="form-label">مهر:</label>
                                <p class="form-control"><?php echo number_format($aggregate['mehr'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">آبان:</label>
                                <p class="form-control"><?php echo number_format($aggregate['aban'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">آذر:</label>
                                <p class="form-control"><?php echo number_format($aggregate['azar'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">دی:</label>
                                <p class="form-control"><?php echo number_format($aggregate['dey'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">بهمن:</label>
                                <p class="form-control"><?php echo number_format($aggregate['bahman'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">اسفند:</label>
                                <p class="form-control"><?php echo number_format($aggregate['esfand'], 0); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <!-- نمایش جدول نتایج -->
                    <?php if (!empty($details)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>پاراکلینیک</th>
                                        <th>نام سرویس</th>
                                        <th>سال</th>
                                        <th>فروردین</th>
                                        <th>اردیبهشت</th>
                                        <th>خرداد</th>
                                        <th>تیر</th>
                                        <th>مرداد</th>
                                        <th>شهریور</th>
                                        <th>مهر</th>
                                        <th>آبان</th>
                                        <th>آذر</th>
                                        <th>دی</th>
                                        <th>بهمن</th>
                                        <th>اسفند</th>
                                        <th>تعداد مراجعین</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($details as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row["paraclinic"], ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($row["service_name"], ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($row["year"], ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo number_format($row["farvardin"], 0); ?></td>
                                            <td><?php echo number_format($row["ordibehesht"], 0); ?></td>
                                            <td><?php echo number_format($row["khordad"], 0); ?></td>
                                            <td><?php echo number_format($row["tir"], 0); ?></td>
                                            <td><?php echo number_format($row["mordad"], 0); ?></td>
                                            <td><?php echo number_format($row["shahrivar"], 0); ?></td>
                                            <td><?php echo number_format($row["mehr"], 0); ?></td>
                                            <td><?php echo number_format($row["aban"], 0); ?></td>
                                            <td><?php echo number_format($row["azar"], 0); ?></td>
                                            <td><?php echo number_format($row["dey"], 0); ?></td>
                                            <td><?php echo number_format($row["bahman"], 0); ?></td>
                                            <td><?php echo number_format($row["esfand"], 0); ?></td>
                                            <?php
                                            $total = $row["farvardin"] + $row["ordibehesht"] + $row["khordad"] +
                                                $row["tir"] + $row["mordad"] + $row["shahrivar"] +
                                                $row["mehr"] + $row["aban"] + $row["azar"] +
                                                $row["dey"] + $row["bahman"] + $row["esfand"];
                                            ?>
                                            <td><?php echo number_format($total, 0); ?></td>
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
    <!-- بارگذاری Select2 JS -->
    <script src="js/select2.min.js"></script>
    <script src="MonthYearPicker.js"></script>

    <script>
        // حفظ مقادیر انتخاب‌شده پیشین از طرف سرور
        var savedParaclinic = "<?php echo $paraclinic_val; ?>";
        var savedService = "<?php echo $service_val; ?>";

        // تابع نرمال‌سازی: تبدیل حروف به فرم کوچک و جایگزینی حروف مشابه
        function normalizeTerm(term) {
            return term.toLowerCase()
                .replace(/ي/g, 'ی')
                .replace(/ك/g, 'ک')
                .replace(/آ/g, 'ا');
        }

        // matcher سفارشی جهت بهبود جستجو در Select2
        function customMatcher(params, data) {
            if ($.trim(params.term) === '') {
                return data;
            }
            var normalizedSearch = normalizeTerm(params.term);
            if (data.text) {
                var normalizedText = normalizeTerm(data.text);
                if (normalizedText.indexOf(normalizedSearch) > -1) {
                    return data;
                }
            }
            return null;
        }

        // تابع قالب‌بندی برای نمایش آیتم‌ها در لیست کشویی همراه با ایکون مرتبط
        function formatService(item) {
            // در صورتی که آیتم فاقد شناسه باشد (مثلاً placeholder)
            if (!item.id) {
                return item.text;
            }
            // انتخاب آیکون مرتبط با خدمات بیمارستان (مثلاً آیکون بیمارستان)
            var iconClass = "fa fa-hospital-o";
            // ایجاد ساختار HTML با ایکون در ابتدای متن و فاصله مناسب (margin-right: 5px)
            var $container = $(
                '<span><i class="' + iconClass + '" style="margin-right:5px;"></i>' + " " + item.text + '</span>'
            );
            return $container;
        }

        // تابع قالب‌بندی برای نمایش آیتم انتخاب‌شده (بدون ایکون)
        function formatServiceSelection(item) {
            return item.text;
        }

        // بارگذاری پاراکلینیک‌ها بر اساس بیمارستان انتخاب‌شده
        function loadParaclinics(hospital) {
            $.ajax({
                url: "serve_paraklinik_m.php",
                method: "GET",
                data: {
                    hospital: hospital,
                    action: 'getparaclinic'
                },
                dataType: "json",
                success: function(data) {
                    var options = '<option value="all">همه</option>';
                    $.each(data, function(index, item) {
                        var selected = "";
                        if (savedParaclinic === item.paraclinic_code) {
                            selected = " selected";
                        }
                        options += '<option value="' + item.paraclinic_code + '"' + selected + '>' + item.paraclinic + '</option>';
                    });
                    $("#paraclinic").html(options);
                    var currentHospital = $("#hospital").val();
                    var currentParaclinic = $("#paraclinic").val();
                    loadServices(currentHospital, currentParaclinic);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("خطا در دریافت اطلاعات پاراکلینیک‌ها: " + textStatus, errorThrown);
                    alert("خطا در دریافت اطلاعات پاراکلینیک‌ها.");
                }
            });
        }

        // بارگذاری سرویس‌ها بر اساس بیمارستان و پاراکلینیک انتخاب‌شده
        function loadServices(hospital, paraclinic) {
            $.ajax({
                url: "serve_paraklinik_m.php",
                method: "GET",
                data: {
                    hospital: hospital,
                    paraclinic: paraclinic,
                    action: 'getservice'
                },
                dataType: "json",
                success: function(data) {
                    var options = '<option value="all">همه</option>';
                    $.each(data, function(index, item) {
                        var selected = "";
                        if (savedService === item.service_name) {
                            selected = " selected";
                        }
                        options += '<option value="' + item.service_name + '"' + selected + '>' + item.service_name + '</option>';
                    });
                    $("#service").html(options).trigger("change");
                    // راه‌اندازی مجدد Select2 با استفاده از توابع قالب‌بندی سفارشی
                    $("#service").select2("destroy").select2({
                        placeholder: "جستجو برای نام سرویس...",
                        allowClear: true,
                        language: "fa",
                        matcher: customMatcher,
                        templateResult: formatService, // نمایش ایکون در لیست کشویی
                        templateSelection: formatServiceSelection // نمایش فقط متن در انتخاب شده
                    });
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("خطا در دریافت اطلاعات سرویس‌ها: " + textStatus, errorThrown);
                    alert("خطا در دریافت اطلاعات سرویس‌ها.");
                }
            });
        }

        $(document).ready(function() {
            // راه‌اندازی اولیه Select2 برای فیلد نام سرویس
            $("#service").select2({
                placeholder: "جستجو برای نام سرویس...",
                allowClear: true,
                language: "fa",
                matcher: customMatcher,
                templateResult: formatService,
                templateSelection: formatServiceSelection
            });

            // بارگذاری اولیه پاراکلینیک‌ها بر اساس بیمارستان انتخاب‌شده
            var currentHospital = $("#hospital").val();
            loadParaclinics(currentHospital);

            // تغییر بیمارستان باعث بروزرسانی پاراکلینیک‌ها می‌شود
            $("#hospital").on("change", function() {
                var hospital = $(this).val();
                loadParaclinics(hospital);
            });

            // تغییر در پاراکلینیک، سرویس‌های مرتبط را بروزرسانی می‌کند
            $("#paraclinic").on("change", function() {
                var hospital = $("#hospital").val();
                var paraclinic = $(this).val();
                loadServices(hospital, paraclinic);
            });

            // اعتبارسنجی فرم پیش از ارسال
            $("form").on("submit", function(e) {
                var startDate = $("#start_date").val().trim();
                var endDate = $("#end_date").val().trim();
                var hospital = $("#hospital").val();

                if (startDate === "" || endDate === "") {
                    alert("لطفاً هر دو فیلد تاریخ شروع و پایان را پر کنید.");
                    e.preventDefault();
                    return;

                }
                if (new Date(endDate) < new Date(startDate)) {
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

        // پیکربندی تقویم ماه/سال برای فیلدهای تاریخ با استفاده از MonthYearPicker
        new MonthYearPicker("start_date", {
            initialYear: 1404,
            onSelectMonth: function(month, year) {
                let numericMonth = month < 10 ? '0' + month : month;
                this.triggerElement.value = `${year}-${numericMonth}`;
            }
        });
        new MonthYearPicker("end_date", {
            initialYear: 1404,
            onSelectMonth: function(month, year) {
                let numericMonth = month < 10 ? '0' + month : month;
                this.triggerElement.value = `${year}-${numericMonth}`;
            }
        });
    </script>


</body>

</html>