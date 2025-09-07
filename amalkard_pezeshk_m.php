<?php
require 'vendor/autoload.php';
require_once 'HospitalRepository.php';
require 'Menu_m.php';
require 'Menu_add.php';
require 'Menu_omor.php';
require 'Menu_pirapezeshk.php';
$hospitals = HospitalRepository::getHospitals();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

session_start();

// در صورتی که کاربر وارد نشده باشد، به صفحه ورود هدایت شود
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// تعریف اولیه متغیرها جهت حفظ مقادیر فیلدهای فرم
$start_date_val = $_POST["start_date"] ?? "";
$end_date_val   = $_POST["end_date"] ?? "";
$hospital_val   = $_POST["hospital"] ?? "all";
$maght_val      = $_POST["maght"] ?? "all";
$specialty_val  = $_POST["specialty"] ?? "all";
$doctor_val     = $_POST["doctor"] ?? "";

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

    // جستجوی پیشرفته پزشک
    if ($action == 'searchDoctor' && isset($_GET['query'])) {
        $queryText = $_GET['query'];
        // نرمال‌سازی متن ورودی
        $normalizedQuery = mb_strtolower($queryText, 'UTF-8');
        $normalizedQuery = str_replace(array('آ', 'ي', 'ك'), array('ا', 'ی', 'ک'), $normalizedQuery);
        $stmt = $conn->prepare("SELECT DISTINCT doctor_name, doctor_code, hospital, maght, specialty, specialty_code 
                                FROM doctor_performance 
                                WHERE LOWER(REPLACE(REPLACE(REPLACE(doctor_name, 'آ', 'ا'), 'ي','ی'), 'ك','ک')) LIKE ? 
                                ORDER BY doctor_name");
        $searchStr = "%" . $normalizedQuery . "%";
        $stmt->bind_param("s", $searchStr);
        $stmt->execute();
        $result = $stmt->get_result();
        $doctors = [];
        while ($row = $result->fetch_assoc()) {
            $doctors[] = $row;
        }
        echo json_encode($doctors);
        $stmt->close();
        $conn->close();
        exit;
    }

    // دریافت لیست مقطع‌ها
    if ($action == 'getMaghts' && isset($_GET['hospital'])) {
        $hospital = $_GET['hospital'];
        if ($hospital == 'all') {
            $query = "SELECT DISTINCT maght, maght_code FROM doctor_performance ORDER BY maght";
            $result = $conn->query($query);
            $maghts = [];
            while ($row = $result->fetch_assoc()) {
                $maghts[] = $row;
            }
            echo json_encode($maghts);
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT maght, maght_code FROM doctor_performance WHERE hospital = ? ORDER BY maght");
            $stmt->bind_param("s", $hospital);
            $stmt->execute();
            $result = $stmt->get_result();
            $maghts = [];
            while ($row = $result->fetch_assoc()) {
                $maghts[] = $row;
            }
            echo json_encode($maghts);
            $stmt->close();
        }
        $conn->close();
        exit;
    }

    // دریافت لیست تخصص‌ها بر اساس انتخاب بیمارستان و مقطع
    if ($action == 'getSpecialties' && isset($_GET['hospital'])) {
        $hospital = $_GET['hospital'];
        $maght = $_GET['maght'] ?? "all";
        $conditions = [];
        $params = [];
        $types = "";
        if ($hospital !== "all") {
            $conditions[] = "hospital = ?";
            $params[] = $hospital;
            $types .= "s";
        }
        if ($maght !== "all") {
            $conditions[] = "maght = ?";
            $params[] = $maght;
            $types .= "s";
        }
        $whereClause = count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";
        $query = "SELECT DISTINCT specialty, specialty_code FROM doctor_performance $whereClause ORDER BY specialty";
        if (count($params) > 0) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $specialties = [];
            while ($row = $result->fetch_assoc()) {
                $specialties[] = $row;
            }
            echo json_encode($specialties);
            $stmt->close();
        } else {
            $result = $conn->query($query);
            $specialties = [];
            while ($row = $result->fetch_assoc()) {
                $specialties[] = $row;
            }
            echo json_encode($specialties);
        }
        $conn->close();
        exit;
    }

    // دریافت لیست پزشکان بر اساس انتخاب بیمارستان، مقطع و تخصص
    if ($action == 'getDoctors' && isset($_GET['hospital']) && isset($_GET['specialty'])) {
        $hospital = $_GET['hospital'];
        $specialty = $_GET['specialty'];
        $maght = $_GET['maght'] ?? "all";
        $conditions = [];
        $params = [];
        $types = "";
        if ($hospital !== "all") {
            $conditions[] = "hospital = ?";
            $params[] = $hospital;
            $types .= "s";
        }
        if ($maght !== "all") {
            $conditions[] = "maght = ?";
            $params[] = $maght;
            $types .= "s";
        }
        if ($specialty !== "all") {
            $conditions[] = "specialty = ?";
            $params[] = $specialty;
            $types .= "s";
        }
        $whereClause = count($conditions) > 0 ? " WHERE " . implode(" AND ", $conditions) : "";
        $query = "SELECT DISTINCT doctor_name, doctor_code FROM doctor_performance $whereClause ORDER BY doctor_name";
        if (count($params) > 0) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $doctors = [];
            while ($row = $result->fetch_assoc()) {
                $doctors[] = $row;
            }
            echo json_encode($doctors);
            $stmt->close();
        } else {
            $result = $conn->query($query);
            $doctors = [];
            while ($row = $result->fetch_assoc()) {
                $doctors[] = $row;
            }
            echo json_encode($doctors);
        }
        $conn->close();
        exit;
    }
    $conn->close();
    exit;
}

// -------------------------
// پردازش مقادیر فرم (نمایش گزارش و خروجی اکسل)
// -------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $startDate = $_POST["start_date"] ?? "";
    $endDate   = $_POST["end_date"] ?? "";

    // اعتبارسنجی سمت سرور: در صورتی که فیلدهای تاریخ خالی باشند
    if (empty($startDate) || empty($endDate)) {
        echo "<script>alert('فیلدهای تاریخ شروع و پایان باید پر شوند.'); window.history.back();</script>";
        exit;
    }

    if ($endDate < $startDate) {
        echo "<script>alert('تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.'); window.history.back();</script>";
    } else {
        $conditions = [];
        $params = [];
        $types = "";
        // استفاده از فیلد تاریخ برای فیلتر کردن
        $conditions[] = "date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
        if ($hospital_val !== "all") {
            $conditions[] = "hospital = ?";
            $params[] = $hospital_val;
            $types .= "s";
        }
        if ($maght_val !== "all") {
            $conditions[] = "maght = ?";
            $params[] = $maght_val;
            $types .= "s";
        }
        if ($specialty_val !== "all") {
            $conditions[] = "specialty = ?";
            $params[] = $specialty_val;
            $types .= "s";
        }
        if ($doctor_val !== "all") {
            $conditions[] = "doctor_name = ?";
            $params[] = $doctor_val;
            $types .= "s";
        }
        $whereClause = implode(" AND ", $conditions);

        // پرس‌وجوی Aggregate
        $queryAgg = "SELECT 
            SUM(visit_sakhte) AS sum_visit_sakhte,
            SUM(visit_tamin) AS sum_visit_tamin,
            SUM(visit_salamat) AS sum_visit_salamat,
            SUM(visit_azad) AS sum_visit_azad,
            SUM(other_visits) AS sum_other_visits,
            SUM(total_visits) AS sum_total_visits,
            SUM(gross_income) AS sum_gross_income,
            SUM(endoscopy) AS sum_endoscopy,
            SUM(colonoscopy) AS sum_colonoscopy,
            SUM(bronchoscopy) AS sum_bronchoscopy,
            SUM(ecocardio) AS sum_ecocardio,
            SUM(inpatient) AS sum_inpatient,
            SUM(surgery) AS sum_surgery,
            SUM(surgery_K) AS sum_surgery_K,
            SUM(anesthesia) AS sum_anesthesia,
            SUM(anesthesia_K) AS sum_anesthesia_K
         FROM doctor_performance
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

        // پرس‌وجوی گروه‌بندی جهت نمایش جدول و خروجی اکسل
        $queryDetails = "SELECT 
            hospital, doctor_code, doctor_name, maght, maght_code, specialty, specialty_code,
            SUM(visit_sakhte) AS sum_visit_sakhte,
            SUM(visit_tamin) AS sum_visit_tamin,
            SUM(visit_salamat) AS sum_visit_salamat,
            SUM(visit_azad) AS sum_visit_azad,
            SUM(other_visits) AS sum_other_visits,
            SUM(total_visits) AS sum_total_visits,
            SUM(gross_income) AS sum_gross_income,
            SUM(endoscopy) AS sum_endoscopy,
            SUM(colonoscopy) AS sum_colonoscopy,
            SUM(bronchoscopy) AS sum_bronchoscopy,
            SUM(ecocardio) AS sum_ecocardio,
            SUM(inpatient) AS sum_inpatient,
            SUM(surgery) AS sum_surgery,
            SUM(surgery_K) AS sum_surgery_K,
            SUM(anesthesia) AS sum_anesthesia,
            SUM(anesthesia_K) AS sum_anesthesia_K
         FROM doctor_performance
         WHERE $whereClause
         GROUP BY hospital, doctor_code, doctor_name, maght, maght_code, specialty, specialty_code
         ORDER BY hospital, doctor_name";

        $stmtDet = $conn->prepare($queryDetails);
        if ($stmtDet === false) {
            die("خطا در پرس‌وجوی گروه‌بندی: " . $conn->error);
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

        // خروجی اکسل در صورت انتخاب
        if ($_POST["action"] === "export") {
            $stmtExcel = $conn->prepare($queryDetails);
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

            function setCellValueByColAndRow($sheet, $col, $row, $value)
            {
                $coordinate = Coordinate::stringFromColumnIndex((int)$col) . (int)$row;
                $sheet->setCellValue($coordinate, $value);
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = [
                "بیمارستان",
                "کد پزشک",
                "نام پزشک",
                "مقطع",
                "کد تخصص",
                "تخصص",
                "ویزیت ساخد",
                "ویزیت تامین",
                "ویزیت سلامت",
                "ویزیت آزاد",
                "سایر ویزیت ها",
                "کل ویزیت ها",
                "درآمد ناخالص",
                "اندوسکوپی",
                "کلونوسکوپی",
                "برونکوسکوپی",
                "اکوکاردیئو",
                "بیمار بستری",
                "عمل جراحی",
                "K عمل جراحی",
                "بیهوشی",
                "K بیهوشی"
            ];
            $rowNum = 1;
            foreach ($headers as $index => $headerText) {
                setCellValueByColAndRow($sheet, $index + 1, $rowNum, $headerText);
            }
            $rowNum = 2;
            foreach ($excelData as $data) {
                $col = 1;
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['hospital']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['doctor_code']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['doctor_name']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['maght']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['specialty_code']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['specialty']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_visit_sakhte']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_visit_tamin']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_visit_salamat']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_visit_azad']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_other_visits']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_total_visits']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_gross_income']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_endoscopy']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_colonoscopy']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_bronchoscopy']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_ecocardio']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_inpatient']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_surgery']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_surgery_K']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_anesthesia']);
                setCellValueByColAndRow($sheet, $col++, $rowNum, $data['sum_anesthesia_K']);
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
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>عملکرد پزشکان - داشبورد مدیر</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- بارگذاری CSS مربوط به Bootstrap RTL و Font Awesome -->
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
            <!-- ستون سایدبار -->
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
                $header = new Header("عملکرد پزشکان", $_SESSION["username"]);
                echo $header->render();
                ?>
                <div class="main-content">
                    <!-- دکمه جستجوی پزشک پیشرفته -->
                    <div class="mb-3">
                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#doctorSearchModal">
                            <i class="fa fa-search"></i> جستجوی پزشک پیشرفته
                        </button>
                    </div>
                    <!-- فرم فیلتر -->
                    <form method="post" action="">
                        <div class="row g-3">
                            <!-- استفاده از تقویم برای تاریخ شروع -->
                            <div class="col-md-2">
                                <label for="start_date" class="form-label">تاریخ شروع</label>
                                <input type="text" id="start_date" name="start_date" placeholder="تاریخ شروع را انتخاب کنید" class="form-control" readonly
                                    value="<?php echo htmlspecialchars($start_date_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="calendarPopupStart" class="calendar-popup"></div>
                            </div>
                            <div class="col-md-2">
                                <label for="end_date" class="form-label">تاریخ پایان</label>
                                <input type="text" id="end_date" name="end_date" placeholder="تاریخ پایان را انتخاب کنید" class="form-control" readonly
                                    value="<?php echo htmlspecialchars($end_date_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <div id="calendarPopupEnd" class="calendar-popup"></div>
                            </div>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-md-3">
                                <label for="hospital" class="form-label">انتخاب بیمارستان</label>
                                <select name="hospital" id="hospital" class="form-select">
                                    <option value="all" <?php echo ($hospital_val == "all") ? "selected" : ""; ?>>همه</option>
                                    <?php
                                    // حلقه روی لیست بیمارستان‌ها به صورت شی‌گرا
                                    foreach ($hospitals as $hospital) {
                                        // دریافت نام بیمارستان از شیء
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
                                <label for="maght" class="form-label">انتخاب مقطع</label>
                                <select name="maght" id="maght" class="form-select">
                                    <option value="all" <?php echo ($maght_val == "all") ? "selected" : ""; ?>>همه</option>
                                    <?php
                                    $connMaght = new mysqli("localhost", "root", "mysql", "dashboard_db");
                                    if ($connMaght->connect_error) {
                                        die("خطا در اتصال: " . $connMaght->connect_error);
                                    }
                                    $queryMaght = "SELECT DISTINCT maght, maght_code FROM doctor_performance ORDER BY maght";
                                    $resultMaght = $connMaght->query($queryMaght);
                                    while ($row = $resultMaght->fetch_assoc()) {
                                        $sel = ($maght_val == $row['maght']) ? "selected" : "";
                                        echo "<option value=\"" . htmlspecialchars($row['maght'], ENT_QUOTES, "UTF-8") . "\" $sel>" . htmlspecialchars($row['maght'], ENT_QUOTES, "UTF-8") . "</option>";
                                    }
                                    $connMaght->close();
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="specialty" class="form-label">انتخاب تخصص</label>
                                <select name="specialty" id="specialty" class="form-select">
                                    <option value="all" <?php echo ($specialty_val == "all") ? "selected" : ""; ?>>همه</option>
                                    <?php
                                    if ($hospital_val) {
                                        $connSpec = new mysqli("localhost", "root", "mysql", "dashboard_db");
                                        if ($connSpec->connect_error) {
                                            die("خطا در اتصال: " . $connSpec->connect_error);
                                        }
                                        if ($hospital_val == "all") {
                                            $querySpec = "SELECT DISTINCT specialty, specialty_code FROM doctor_performance ORDER BY specialty";
                                            $resultSpec = $connSpec->query($querySpec);
                                        } else {
                                            $stmtSpec = $connSpec->prepare("SELECT DISTINCT specialty, specialty_code FROM doctor_performance WHERE hospital = ? AND maght " . (($maght_val != "all") ? "= ?" : "LIKE '%'") . " ORDER BY specialty");
                                            if ($maght_val != "all") {
                                                $stmtSpec->bind_param("ss", $hospital_val, $maght_val);
                                            } else {
                                                $stmtSpec->bind_param("s", $hospital_val);
                                            }
                                            $stmtSpec->execute();
                                            $resultSpec = $stmtSpec->get_result();
                                        }
                                        while ($row = $resultSpec->fetch_assoc()) {
                                            $sel = ($specialty_val == $row['specialty']) ? "selected" : "";
                                            echo "<option value=\"" . htmlspecialchars($row['specialty'], ENT_QUOTES, "UTF-8") . "\" $sel>" . htmlspecialchars($row['specialty'], ENT_QUOTES, "UTF-8") . "</option>";
                                        }
                                        if ($hospital_val != "all") {
                                            $stmtSpec->close();
                                        }
                                        $connSpec->close();
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="doctor" class="form-label">انتخاب نام پزشک</label>
                                <select name="doctor" id="doctor" class="form-select">
                                    <option value="all" <?php echo ($doctor_val == "all") ? "selected" : ""; ?>>همه</option>
                                    <?php
                                    if ($hospital_val || $maght_val || $specialty_val) {
                                        $connDoc = new mysqli("localhost", "root", "mysql", "dashboard_db");
                                        if ($connDoc->connect_error) {
                                            die("خطا در اتصال: " . $connDoc->connect_error);
                                        }
                                        if ($hospital_val == "all" && $specialty_val == "all") {
                                            $queryDoc = "SELECT DISTINCT doctor_name, doctor_code FROM doctor_performance ORDER BY doctor_name";
                                            $resultDoc = $connDoc->query($queryDoc);
                                        } elseif ($hospital_val != "all" && $specialty_val == "all") {
                                            $stmtDoc = $connDoc->prepare("SELECT DISTINCT doctor_name, doctor_code FROM doctor_performance WHERE hospital = ? AND maght " . (($maght_val != "all") ? "= ?" : "LIKE '%'") . " ORDER BY doctor_name");
                                            if ($maght_val != "all") {
                                                $stmtDoc->bind_param("ss", $hospital_val, $maght_val);
                                            } else {
                                                $stmtDoc->bind_param("s", $hospital_val);
                                            }
                                            $stmtDoc->execute();
                                            $resultDoc = $stmtDoc->get_result();
                                        } elseif ($hospital_val == "all" && $specialty_val != "all") {
                                            $stmtDoc = $connDoc->prepare("SELECT DISTINCT doctor_name, doctor_code FROM doctor_performance WHERE specialty = ? AND maght " . (($maght_val != "all") ? "= ?" : "LIKE '%'") . " ORDER BY doctor_name");
                                            if ($maght_val != "all") {
                                                $stmtDoc->bind_param("ss", $specialty_val, $maght_val);
                                            } else {
                                                $stmtDoc->bind_param("s", $specialty_val);
                                            }
                                            $stmtDoc->execute();
                                            $resultDoc = $stmtDoc->get_result();
                                        } else {
                                            $stmtDoc = $connDoc->prepare("SELECT DISTINCT doctor_name, doctor_code FROM doctor_performance WHERE hospital = ? AND specialty = ? AND maght = ? ORDER BY doctor_name");
                                            $stmtDoc->bind_param("sss", $hospital_val, $specialty_val, $maght_val);
                                            $stmtDoc->execute();
                                            $resultDoc = $stmtDoc->get_result();
                                        }
                                        while ($row = $resultDoc->fetch_assoc()) {
                                            $sel = ($doctor_val == $row['doctor_name']) ? "selected" : "";
                                            echo "<option value=\"" . htmlspecialchars($row['doctor_name'], ENT_QUOTES, "UTF-8") . "\" $sel>" . htmlspecialchars($row['doctor_name'], ENT_QUOTES, "UTF-8") . "</option>";
                                        }
                                        if ((($hospital_val != "all") && ($specialty_val == "all")) || (($hospital_val == "all") && ($specialty_val != "all")) || (($hospital_val != "all") && ($specialty_val != "all"))) {
                                            $stmtDoc->close();
                                        }
                                        $connDoc->close();
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
                            <button type="button" id="analysisButton" class="btn btn-warning" onclick="window.location.href='amalkard_pezeshk_analiz_m.php'">
                                <i class="fa fa-line-chart"></i> تحلیل
                            </button>
                        </div>
                    </form>
                    <hr>
                    <!-- نمایش خلاصه عملکرد پزشکان -->
                    <?php if (!empty($aggregateResults)): ?>
                        <div class="row">
                            <div class="col-md-2">
                                <label class="form-label">ویزیت ساخد:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_visit_sakhte'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ویزیت تامین:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_visit_tamin'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ویزیت سلامت:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_visit_salamat'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ویزیت آزاد:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_visit_azad'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">سایر ویزیت‌ها:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_other_visits'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">کل ویزیت‌ها:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_total_visits'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">درآمد ناخالص:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_gross_income'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">اندوسکوپی:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_endoscopy'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">کلونوسکوپی:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_colonoscopy'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">برونکوسکوپی:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_bronchoscopy'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">اکوکاردیئو:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_ecocardio'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">بیمار بستری:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_inpatient'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">عمل جراحی:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_surgery'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">K عمل جراحی:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_surgery_K'], 2); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">بیهوشی:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_anesthesia'], 0); ?></p>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">K بیهوشی:</label>
                                <p class="form-control"><?php echo number_format($aggregateResults['sum_anesthesia_K'], 2); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <hr>
                    <!-- جدول نهایی عملکرد پزشکان -->
                    <?php if (!empty($allItems)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>بیمارستان</th>
                                        <th>نام پزشک</th>
                                        <th>مقطع</th>
                                        <th>تخصص</th>
                                        <th>ویزیت ساخد</th>
                                        <th>ویزیت تامین</th>
                                        <th>ویزیت سلامت</th>
                                        <th>ویزیت آزاد</th>
                                        <th>سایر ویزیت‌ها</th>
                                        <th>کل ویزیت‌ها</th>
                                        <th>درآمد ناخالص</th>
                                        <th>اندوسکوپی</th>
                                        <th>کلونوسکوپی</th>
                                        <th>برونکوسکوپی</th>
                                        <th>اکوکاردیئو</th>
                                        <th>بیمار بستری</th>
                                        <th>عمل جراحی</th>
                                        <th>K عمل جراحی</th>
                                        <th>بیهوشی</th>
                                        <th>K بیهوشی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['hospital']); ?></td>
                                            <td><?php echo htmlspecialchars($item['doctor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['maght']); ?></td>
                                            <td><?php echo htmlspecialchars($item['specialty']); ?></td>
                                            <td><?php echo number_format($item['sum_visit_sakhte'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_visit_tamin'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_visit_salamat'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_visit_azad'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_other_visits'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_total_visits'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_gross_income'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_endoscopy'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_colonoscopy'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_bronchoscopy'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_ecocardio'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_inpatient'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_surgery'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_surgery_K'], 2); ?></td>
                                            <td><?php echo number_format($item['sum_anesthesia'], 0); ?></td>
                                            <td><?php echo number_format($item['sum_anesthesia_K'], 2); ?></td>
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

    <!-- مدال جستجوی پیشرفته پزشک -->
    <div class="modal fade" id="doctorSearchModal" tabindex="-1" aria-labelledby="doctorSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="doctorSearchModalLabel">جستجوی پیشرفته پزشک</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="doctor_search_input" class="form-label">نام پزشک</label>
                        <input type="text" id="doctor_search_input" class="form-control" placeholder="نام پزشک را وارد کنید">
                    </div>
                    <button type="button" id="doctor_search_button" class="btn btn-primary mb-3">جستجو</button>
                    <ul class="list-group" id="doctor_search_results"></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>

    <!-- اسکریپت‌های مورد نیاز -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // جلوگیری از ارسال فرم بدون پر بودن تاریخ‌ها و عدم ریست شدن فیلدها
        $(document).ready(function() {
            $("form").on("submit", function(e) {
                var startDate = $("#start_date").val().trim();
                var endDate = $("#end_date").val().trim();
                if (startDate === "" || endDate === "") {
                    alert("لطفاً هر دو فیلد تاریخ شروع و پایان را پر کنید.");
                    e.preventDefault();
                }
            });
        });
        $(document).ready(function() {
            $("form").on("submit", function(e) {
                var startDate = $("#start_date").val().trim();
                var endDate = $("#end_date").val().trim();
                if (endDate < startDate) {
                    alert("تاریخ پایان نمی‌تواند از تاریخ شروع قبل باشد.");
                    e.preventDefault();
                }
            });
        });

        // تابع برای تکمیل فیلد‌های فرم با اطلاعات پزشک انتخاب‌شده
        function fillDoctorFields(doctorData) {
            $('#hospital').val(doctorData.hospital).trigger('change');
            // برای اطمینان از بروزرسانی زنجیره‌ای، از setTimeout استفاده می‌کنیم:
            setTimeout(function() {
                $('#maght').val(doctorData.maght).trigger('change');
                setTimeout(function() {
                    $('#specialty').val(doctorData.specialty).trigger('change');
                    setTimeout(function() {
                        $('#doctor').val(doctorData.doctor_name);
                    }, 500);
                }, 500);
            }, 500);
        }

        // جستجوی پیشرفته پزشک در مدال
        $('#doctor_search_button').click(function() {
            var query = $('#doctor_search_input').val().trim();
            if (query.length === 0) {
                alert("لطفاً نام پزشک را وارد کنید.");
                return;
            }
            $.ajax({
                url: '?action=searchDoctor',
                type: 'GET',
                data: {
                    query: query
                },
                dataType: 'json',
                success: function(data) {
                    var resultsList = $('#doctor_search_results');
                    resultsList.empty();
                    if (data.length === 0) {
                        resultsList.append('<li class="list-group-item">هیچ موردی یافت نشد.</li>');
                    } else {
                        $.each(data, function(i, doctor) {
                            var li = $('<li class="list-group-item list-group-item-action"></li>');
                            li.text(doctor.doctor_name + " - " + doctor.hospital + " - " + doctor.maght + " - " + doctor.specialty);
                            li.data('doctor', doctor);
                            resultsList.append(li);
                        });
                    }
                },
                error: function() {
                    alert("خطا در جستجو");
                }
            });
        });

        // انتخاب پزشک از نتایج جستجو
        $('#doctor_search_results').on('click', '.list-group-item', function() {
            var doctorData = $(this).data('doctor');
            fillDoctorFields(doctorData);
            $('#doctorSearchModal').modal('hide');
        });


        // به‌روز‌رسانی مقطع‌ها هنگام تغییر بیمارستان
        $('#hospital').change(function() {
            var hospital = $(this).val();
            $.ajax({
                url: '?action=getMaghts',
                type: 'GET',
                data: {
                    hospital: hospital
                },
                dataType: 'json',
                success: function(data) {
                    var maghtSelect = $('#maght');
                    maghtSelect.empty();
                    maghtSelect.append($('<option>', {
                        value: 'all',
                        text: 'همه'
                    }));
                    $.each(data, function(i, item) {
                        maghtSelect.append($('<option>', {
                            value: item.maght,
                            text: item.maght
                        }));
                    });
                    // Trigger برای به‌روز‌رسانی تخصص‌ها
                    maghtSelect.trigger('change');
                },
                error: function() {
                    console.error("خطا در دریافت مقطع‌ها.");
                }
            });
        });

        // به‌روز‌رسانی تخصص‌ها هنگام تغییر مقطع
        $('#maght').change(function() {
            var hospital = $('#hospital').val();
            var maght = $(this).val();
            var currentSpecialty = $('#specialty').val();
            var currentDoctor = $('#doctor').val();
            $.ajax({
                url: '?action=getSpecialties',
                type: 'GET',
                data: {
                    hospital: hospital,
                    maght: maght
                },
                dataType: 'json',
                success: function(data) {
                    var specialtySelect = $('#specialty');
                    specialtySelect.empty();
                    specialtySelect.append($('<option>', {
                        value: 'all',
                        text: 'همه'
                    }));
                    var foundSpecialty = false;
                    $.each(data, function(i, item) {
                        specialtySelect.append($('<option>', {
                            value: item.specialty,
                            text: item.specialty
                        }));
                        if (item.specialty == currentSpecialty) {
                            foundSpecialty = true;
                        }
                    });
                    if (foundSpecialty) {
                        specialtySelect.val(currentSpecialty);
                    }
                    // به‌روز‌رسانی پزشکان
                    $.ajax({
                        url: '?action=getDoctors',
                        type: 'GET',
                        data: {
                            hospital: hospital,
                            specialty: (foundSpecialty ? currentSpecialty : 'all'),
                            maght: maght
                        },
                        dataType: 'json',
                        success: function(data) {
                            var doctorSelect = $('#doctor');
                            doctorSelect.empty();
                            doctorSelect.append($('<option>', {
                                value: 'all',
                                text: 'همه'
                            }));
                            var foundDoctor = false;
                            $.each(data, function(i, item) {
                                doctorSelect.append($('<option>', {
                                    value: item.doctor_name,
                                    text: item.doctor_name
                                }));
                                if (item.doctor_name == currentDoctor) {
                                    foundDoctor = true;
                                }
                            });
                            if (foundDoctor) {
                                doctorSelect.val(currentDoctor);
                            }
                        },
                        error: function() {
                            console.error("خطا در دریافت پزشکان.");
                        }
                    });
                },
                error: function() {
                    console.error("خطا در دریافت تخصص‌ها.");
                }
            });
        });

        // به‌روز‌رسانی پزشکان هنگام تغییر تخصص
        $('#specialty').change(function() {
            var hospital = $('#hospital').val();
            var specialty = $(this).val();
            var maght = $('#maght').val();
            var currentDoctor = $('#doctor').val();
            $.ajax({
                url: '?action=getDoctors',
                type: 'GET',
                data: {
                    hospital: hospital,
                    specialty: specialty,
                    maght: maght
                },
                dataType: 'json',
                success: function(data) {
                    var doctorSelect = $('#doctor');
                    doctorSelect.empty();
                    doctorSelect.append($('<option>', {
                        value: 'all',
                        text: 'همه'
                    }));
                    var found = false;
                    $.each(data, function(i, item) {
                        doctorSelect.append($('<option>', {
                            value: item.doctor_name,
                            text: item.doctor_name
                        }));
                        if (item.doctor_name == currentDoctor) {
                            found = true;
                        }
                    });
                    if (found) {
                        doctorSelect.val(currentDoctor);
                    }
                },
                error: function() {
                    console.error("خطا در دریافت پزشکان.");
                }
            });
        });
    </script>
    <!-- اسکریپت تقویم شمسی جهت انتخاب تاریخ -->
    <script src="persianCalendar.js"></script>
    <script>
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
    </script>
</body>

</html>