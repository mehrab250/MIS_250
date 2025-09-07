<?php
session_start();

// بررسی ورود کاربر
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// بارگذاری کتابخانه‌ها و کلاس‌های مورد نیاز
require 'vendor/autoload.php';
require 'Header.php';
require 'HospitalRepository.php';
require 'Menu_m.php';
require 'Menu_add.php';
require 'Menu_omor.php';
require 'Menu_pirapezeshk.php';

$hospitals = HospitalRepository::getHospitals();

// استفاده از کلاس‌های PhpSpreadsheet جهت خروجی اکسل
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// دریافت مقادیر فرم جهت حفظ انتخاب‌های قبلی
$start_date_val = $_POST["start_date"] ?? "";
$end_date_val = $_POST["end_date"] ?? "";
$hospital_val = $_POST["hospital"] ?? "";  // به صورت پیش‌فرض مقدار خالی
$paraclinic_val = $_POST["paraclinic"] ?? "all";

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

// بخش پردازش درخواست GET (برای Ajax دریافت پاراکلینیک)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action == 'getparaclinic' && isset($_GET['hospital'])) {
        $hospital = $_GET['hospital'];
        if ($hospital === "") {
            // اگر بیمارستان انتخاب نشده است، آرایه خالی برگردانید.
            echo json_encode([]);
            $conn->close();
            exit;
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT paraclinic, paraclinic_code FROM paraclinic_stats WHERE hospital = ? ORDER BY paraclinic");
            $stmt->bind_param("s", $hospital);
            $stmt->execute();
            $result = $stmt->get_result();
            $paraclinics = [];
            while ($row = $result->fetch_assoc()) {
                $paraclinics[] = $row;
            }
            echo json_encode($paraclinics);
            $stmt->close();
            $conn->close();
            exit;
        }
    }
}

// آرایه جهت نگهداری نتایج پرس و جو (Pivot شده)
$details = [];

// آرایه جهت جمع کل هر ماه
$aggregate = [
    'farvardin' => 0,
    'ordibehesht' => 0,
    'khordad' => 0,
    'tir' => 0,
    'mordad' => 0,
    'shahrivar' => 0,
    'mehr' => 0,
    'aban' => 0,
    'azar' => 0,
    'dey' => 0,
    'bahman' => 0,
    'esfand' => 0,
];

$message = "";

// پردازش فرم در صورت ارسال (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {

    $start_date = $_POST["start_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";

    // اعتبارسنجی تاریخ‌ها
    if (empty($start_date) || empty($end_date)) {
        $message = "لطفاً هر دو تاریخ شروع و پایان را انتخاب کنید.";
    } elseif ($end_date < $start_date) {
        $message = "تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.";
    } else {
        // دریافت مقادیر انتخاب بیمارستان و پاراکلینیک
        $hospital = $_POST["hospital"] ?? "";
        $paraclinic = $_POST["paraclinic"] ?? "all";

        // ساخت شرط‌های دینامیک جهت کوئری
        $conditions = [];
        $params = [];
        $types = "";

        if ($hospital !== "") {
            $conditions[] = "hospital = ?";
            $params[] = $hospital;
            $types .= "s";
        }
        if ($paraclinic !== "all") {
            $conditions[] = "paraclinic_code = ?";
            $params[] = $paraclinic;
            $types .= "s";
        }
        // شرط تاریخ (با استفاده از LPAD برای اشتراک مقایسه صحیح ماه به صورت دو رقمی)
        $conditions[] = "CONCAT(year, '-', LPAD(month, 2, '0')) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";

        $where = "";
        if (count($conditions) > 0) {
            $where = " WHERE " . implode(" AND ", $conditions);
        }

        // کوئری جهت دریافت اطلاعات آماری به صورت Pivot
        $query = "SELECT paraclinic_code, paraclinic, year,
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
                  FROM paraclinic_stats
                  $where
                  GROUP BY paraclinic_code, paraclinic, year
                  ORDER BY paraclinic, year";

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
            // به‌روزرسانی مجموع کل ستون‌ها
            $aggregate['farvardin'] += $row['farvardin'];
            $aggregate['ordibehesht'] += $row['ordibehesht'];
            $aggregate['khordad'] += $row['khordad'];
            $aggregate['tir'] += $row['tir'];
            $aggregate['mordad'] += $row['mordad'];
            $aggregate['shahrivar'] += $row['shahrivar'];
            $aggregate['mehr'] += $row['mehr'];
            $aggregate['aban'] += $row['aban'];
            $aggregate['azar'] += $row['azar'];
            $aggregate['dey'] += $row['dey'];
            $aggregate['bahman'] += $row['bahman'];
            $aggregate['esfand'] += $row['esfand'];
        }
        $stmt->close();

        // در صورت انتخاب دکمه "خروجی اکسل"
        if ($_POST["action"] === "export") {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // تابع کمکی جهت درج مقدار سلول با توجه به شماره ستون و ردیف
            function setCellValueByColAndRow($sheet, $col, $row, $value)
            {
                $coordinate = Coordinate::stringFromColumnIndex((int) $col) . (int) $row;
                $sheet->setCellValue($coordinate, $value);
            }

            // تنظیم هدرهای ستون اکسل
            $headers = [
                "کد پاراکلینیک",
                "پاراکلینیک",
                "سال",
                "فروردين",
                "ارديبهشت",
                "خرداد",
                "تير",
                "مرداد",
                "شهريور",
                "مهر",
                "آبان",
                "آذر",
                "دي",
                "بهمن",
                "اسفند",
                "تعداد مراجعین"
            ];

            $rowNum = 1;
            $col = 1;
            foreach ($headers as $headerText) {
                setCellValueByColAndRow($sheet, $col++, $rowNum, $headerText);
            }

            // درج داده‌های تفصیلی
            $rowNum = 2;
            foreach ($details as $dataRow) {
                $col = 1;
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["paraclinic_code"]);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $dataRow["paraclinic"]);
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

                // محاسبه مجموع ماه‌ها برای این ردیف
                $total =
                    $dataRow["farvardin"] + $dataRow["ordibehesht"] + $dataRow["khordad"] +
                    $dataRow["tir"] + $dataRow["mordad"] + $dataRow["shahrivar"] +
                    $dataRow["mehr"] + $dataRow["aban"] + $dataRow["azar"] +
                    $dataRow["dey"] + $dataRow["bahman"] + $dataRow["esfand"];
                setCellValueByColAndRow($sheet, $col++, $rowNum, $total);

                $rowNum++;
            }

            // درج ردیف خلاصه (آمار کل)
            $col = 1;
            setCellValueByColAndRow($sheet, $col++, $rowNum, "کل");
            setCellValueByColAndRow($sheet, $col++, $rowNum, ""); // ستون پاراکلینیک
            setCellValueByColAndRow($sheet, $col++, $rowNum, ""); // ستون سال
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

            // محاسبه مجموع کلی تعداد مراجعین
            $aggregate_total =
                $aggregate['farvardin'] + $aggregate['ordibehesht'] + $aggregate['khordad'] +
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
    <title>آمار پذیرش پاراکلینیک‌ها - داشبورد مدیر</title>
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

        .calendar-popup {
            position: absolute;
            z-index: 9999;
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
                $header = new Header("آمار پذیرش پاراکلینیک‌ها", $_SESSION["username"]);
                echo $header->render();
                ?>
                <div class="main-content">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-warning"><?php echo $message; ?></div>
                    <?php endif; ?>
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
                            <!-- انتخاب بیمارستان -->
                            <div class="col-md-3">
                                <label for="hospital" class="form-label">انتخاب بیمارستان</label>
                                <select name="hospital" id="hospital" class="form-select">
                                    <option value="" <?php echo ($hospital_val == "") ? "selected" : ""; ?>>انتخاب کنید
                                    </option>
                                    <?php
                                    foreach ($hospitals as $hosp) {
                                        $hospName = $hosp->getName();
                                        $selected = ($hospital_val == $hospName) ? "selected" : "";
                                        echo '<option value="' . htmlspecialchars($hospName, ENT_QUOTES, "UTF-8") . '" ' . $selected . '>' . htmlspecialchars($hospName, ENT_QUOTES, "UTF-8") . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <!-- انتخاب پاراکلینیک (بارگذاری داینامیک با AJAX) -->
                            <div class="col-md-3">
                                <label for="paraclinic" class="form-label">پاراکلینیک</label>
                                <select name="paraclinic" id="paraclinic" class="form-select">
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
                                <label class="form-label">فروردين:</label>
                                <p class="form-control"><?php echo number_format($aggregate['farvardin']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ارديبهشت:</label>
                                <p class="form-control"><?php echo number_format($aggregate['ordibehesht']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">خرداد:</label>
                                <p class="form-control"><?php echo number_format($aggregate['khordad']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">تير:</label>
                                <p class="form-control"><?php echo number_format($aggregate['tir']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">مرداد:</label>
                                <p class="form-control"><?php echo number_format($aggregate['mordad']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">شهريور:</label>
                                <p class="form-control"><?php echo number_format($aggregate['shahrivar']); ?></p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label class="form-label">مهر:</label>
                                <p class="form-control"><?php echo number_format($aggregate['mehr']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">آبان:</label>
                                <p class="form-control"><?php echo number_format($aggregate['aban']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">آذر:</label>
                                <p class="form-control"><?php echo number_format($aggregate['azar']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">دي:</label>
                                <p class="form-control"><?php echo number_format($aggregate['dey']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">بهمن:</label>
                                <p class="form-control"><?php echo number_format($aggregate['bahman']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">اسفند:</label>
                                <p class="form-control"><?php echo number_format($aggregate['esfand']); ?></p>
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
                                        <th>سال</th>
                                        <th>فروردين</th>
                                        <th>ارديبهشت</th>
                                        <th>خرداد</th>
                                        <th>تير</th>
                                        <th>مرداد</th>
                                        <th>شهريور</th>
                                        <th>مهر</th>
                                        <th>آبان</th>
                                        <th>آذر</th>
                                        <th>دي</th>
                                        <th>بهمن</th>
                                        <th>اسفند</th>
                                        <th>تعداد مراجعین</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($details as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row["paraclinic"], ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo htmlspecialchars($row["year"], ENT_QUOTES, "UTF-8"); ?></td>
                                            <td><?php echo number_format($row["farvardin"]); ?></td>
                                            <td><?php echo number_format($row["ordibehesht"]); ?></td>
                                            <td><?php echo number_format($row["khordad"]); ?></td>
                                            <td><?php echo number_format($row["tir"]); ?></td>
                                            <td><?php echo number_format($row["mordad"]); ?></td>
                                            <td><?php echo number_format($row["shahrivar"]); ?></td>
                                            <td><?php echo number_format($row["mehr"]); ?></td>
                                            <td><?php echo number_format($row["aban"]); ?></td>
                                            <td><?php echo number_format($row["azar"]); ?></td>
                                            <td><?php echo number_format($row["dey"]); ?></td>
                                            <td><?php echo number_format($row["bahman"]); ?></td>
                                            <td><?php echo number_format($row["esfand"]); ?></td>
                                            <?php
                                            $total =
                                                $row["farvardin"] + $row["ordibehesht"] + $row["khordad"] +
                                                $row["tir"] + $row["mordad"] + $row["shahrivar"] +
                                                $row["mehr"] + $row["aban"] + $row["azar"] +
                                                $row["dey"] + $row["bahman"] + $row["esfand"];
                                            ?>
                                            <td><?php echo number_format($total); ?></td>
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
        // تابع برای بارگذاری لیست پاراکلینیک‌ها بر اساس بیمارستان انتخابی
        function loadParaclinics(hospital) {
            if (hospital === "") {
                $("#paraclinic").html('<option value="all">همه</option>');
                return;
            }
            $.ajax({
                url: "paraklinik_mah_m.php",
                method: "GET",
                data: {
                    hospital: hospital,
                    action: 'getparaclinic'
                },
                dataType: "json",
                success: function (data) {
                    var options = '<option value="all">همه</option>';
                    options += data.map(function (item) {
                        return `<option value="${item.paraclinic_code}">${item.paraclinic}</option>`;
                    }).join('');
                    $("#paraclinic").html(options);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.error("خطا در دریافت اطلاعات پاراکلینیک‌ها: " + textStatus, errorThrown);
                    alert("خطا در دریافت اطلاعات پاراکلینیک‌ها.");
                }
            });
        }

        $(document).ready(function () {
            var currentHospital = $("#hospital").val();
            if (currentHospital !== "") {
                loadParaclinics(currentHospital);
            }

            $("#hospital").on("change", function () {
                var hospital = $(this).val();
                loadParaclinics(hospital);
            });

            $("form").on("submit", function (e) {
                var startDate = $("#start_date").val().trim();
                var endDate = $("#end_date").val().trim();
                var hospital = $("#hospital").val().trim();
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
                if (hospital === "") {
                    alert("لطفاً یک بیمارستان را انتخاب کنید.");
                    e.preventDefault();
                    return;

                }
            });
        });
    </script>
    <!-- اسکریپت تقویم ماه/سال -->
    <script src="MonthYearPicker.js"></script>
    <script>
        new MonthYearPicker("start_date", {
            initialYear: 1404,
            onSelectMonth: function (month, year) {
                let numericMonth = month < 10 ? '0' + month : month;
                this.triggerElement.value = `${year}-${numericMonth}`;
            }
        });
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
<?php
if (isset($conn) && $conn !== null) {
    $conn->close();
}
?>