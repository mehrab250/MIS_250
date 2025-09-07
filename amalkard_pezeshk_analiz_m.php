<?php
// amalkard_pezeshk_analiz_m.php

// -------------------------
// بخش‌های PHP جهت پردازش درخواست‌های AJAX
// -------------------------
if (isset($_GET['action'])) {
    // شروع با بافر خروجی جهت پاکسازی خروجی‌های ناخواسته
    ob_start();

    // تابع اتصال به دیتابیس
    function getDBConnection() {
        $dbhost = "localhost";
        $dbuser = "root";
        $dbpass = "mysql";
        $dbname = "dashboard_db";
        $conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
        if ($conn->connect_error) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(["error" => "خطای اتصال به دیتابیس"]);
            exit;
        }
        return $conn;
    }

    $action = $_GET['action'];

    // -------------------------
    // 1- دریافت داده‌های گزارش (برای نمایش نمودار و کارنامه پزشک)
    // -------------------------
    if ($action === 'filterData') {
        if (
            empty($_GET['year']) ||
            empty($_GET['hospital']) ||
            empty($_GET['maght']) ||
            empty($_GET['specialty']) ||
            empty($_GET['doctor'])
        ) {
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["error" => "پارامترهای لازم ارسال نشده است"]);
            exit;
        }
        $selectedYear      = $_GET['year'];
        $selectedHospital  = $_GET['hospital'];
        $selectedMaght     = $_GET['maght'];
        $selectedSpecialty = $_GET['specialty'];
        $selectedDoctor    = $_GET['doctor'];

        $conn = getDBConnection();
        header('Content-Type: application/json; charset=utf-8');

        // آماده‌سازی آرایه dataByMonth برای ۱۲ ماه
        $dataByMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthKey = str_pad($m, 2, "0", STR_PAD_LEFT);
            $dataByMonth[$monthKey] = [
                'sum_visit_sakhte'  => 0,
                'sum_visit_tamin'   => 0,
                'sum_visit_salamat'  => 0,
                'sum_visit_azad'    => 0,
                'sum_other_visits'  => 0,
                'sum_total_visits'  => 0,
                'sum_gross_income'  => 0,
                'sum_endoscopy'     => 0,
                'sum_colonoscopy'   => 0,
                'sum_bronchoscopy'  => 0,
                'sum_ecocardio'     => 0,
                'sum_inpatient'     => 0,
                'sum_surgery'       => 0,
                'sum_surgery_K'     => 0,
                'sum_anesthesia'    => 0,
                'sum_anesthesia_K'  => 0
            ];
        }

        if ($selectedMaght === "all") {
            // فیلتر مقطع اعمال نمی‌شود
            $query = "SELECT DATE_FORMAT(date, '%m') AS month,
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
                      WHERE YEAR(date) = ? AND hospital_code = ? AND specialty_code = ? AND doctor_code = ?
                      GROUP BY DATE_FORMAT(date, '%m')
                      ORDER BY month ASC";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                ob_end_clean();
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(["error" => "خطا در پرس‌وجوی داده‌ها"]);
                exit;
            }
            $stmt->bind_param("ssss", $selectedYear, $selectedHospital, $selectedSpecialty, $selectedDoctor);
        } else {
            // فیلتر مقطع لحاظ می‌شود.
            $query = "SELECT DATE_FORMAT(date, '%m') AS month,
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
                      WHERE YEAR(date) = ? AND hospital_code = ? AND maght_code = ? AND specialty_code = ? AND doctor_code = ?
                      GROUP BY DATE_FORMAT(date, '%m')
                      ORDER BY month ASC";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                ob_end_clean();
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(["error" => "خطا در پرس‌وجوی داده‌ها"]);
                exit;
            }
            $stmt->bind_param("sssss", $selectedYear, $selectedHospital, $selectedMaght, $selectedSpecialty, $selectedDoctor);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $mth = str_pad($row['month'], 2, "0", STR_PAD_LEFT);
            if (isset($dataByMonth[$mth])) {
                $dataByMonth[$mth]['sum_visit_sakhte']  = $row['sum_visit_sakhte'];
                $dataByMonth[$mth]['sum_visit_tamin']   = $row['sum_visit_tamin'];
                $dataByMonth[$mth]['sum_visit_salamat']  = $row['sum_visit_salamat'];
                $dataByMonth[$mth]['sum_visit_azad']    = $row['sum_visit_azad'];
                $dataByMonth[$mth]['sum_other_visits']  = $row['sum_other_visits'];
                $dataByMonth[$mth]['sum_total_visits']  = $row['sum_total_visits'];
                $dataByMonth[$mth]['sum_gross_income']  = $row['sum_gross_income'];
                $dataByMonth[$mth]['sum_endoscopy']     = $row['sum_endoscopy'];
                $dataByMonth[$mth]['sum_colonoscopy']   = $row['sum_colonoscopy'];
                $dataByMonth[$mth]['sum_bronchoscopy']  = $row['sum_bronchoscopy'];
                $dataByMonth[$mth]['sum_ecocardio']     = $row['sum_ecocardio'];
                $dataByMonth[$mth]['sum_inpatient']     = $row['sum_inpatient'];
                $dataByMonth[$mth]['sum_surgery']       = $row['sum_surgery'];
                $dataByMonth[$mth]['sum_surgery_K']     = $row['sum_surgery_K'];
                $dataByMonth[$mth]['sum_anesthesia']    = $row['sum_anesthesia'];
                $dataByMonth[$mth]['sum_anesthesia_K']  = $row['sum_anesthesia_K'];
            }
        }
        $stmt->close();
        $conn->close();

        // تعیین معیارهای اضافی – فقط در صورتی که مجموع هر ماه بزرگتر از صفر باشد
        $additionalMetrics = [
            'sum_endoscopy'    => 'آندوسکوپی',
            'sum_colonoscopy'  => 'کلونوسکوپی',
            'sum_bronchoscopy' => 'برونکوسکوپی',
            'sum_ecocardio'    => 'اکوکاردیئو',
            'sum_inpatient'    => 'بیمار بستری',
            'sum_surgery'      => 'عمل جراحی',
            'sum_surgery_K'    => 'K عمل جراحی',
            'sum_anesthesia'   => 'بیهوشی',
            'sum_anesthesia_K' => 'K بیهوشی'
        ];
        $includeMetrics = [];
        foreach ($additionalMetrics as $key => $label) {
            $total = 0;
            foreach ($dataByMonth as $mData) {
                $total += $mData[$key];
            }
            if ($total > 0) {
                $includeMetrics[$key] = $label;
            }
        }

        // آرایه ثابت ترجمه ماه‌ها (از فروردین تا اسفند)
        $months = [
            "01" => "فروردین",
            "02" => "اردیبهشت",
            "03" => "خرداد",
            "04" => "تیر",
            "05" => "مرداد",
            "06" => "شهریور",
            "07" => "مهر",
            "08" => "آبان",
            "09" => "آذر",
            "10" => "دی",
            "11" => "بهمن",
            "12" => "اسفند"
        ];

        ob_end_clean();
        echo json_encode([
            "dataByMonth"    => $dataByMonth,
            "includeMetrics" => $includeMetrics,
            "months"         => $months
        ]);
        exit;
    }

    // -------------------------
    // 2- دریافت مقاطع (Maght)
    // -------------------------
    if ($action == 'getMaghts' && isset($_GET['hospital'])) {
        $conn = getDBConnection();
        header('Content-Type: application/json; charset=utf-8');
        $hospital_code = $_GET['hospital'];
        $stmt = $conn->prepare("SELECT DISTINCT maght_code, maght FROM doctor_performance WHERE hospital_code = ? ORDER BY maght");
        if ($stmt === false) {
            ob_end_clean();
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(["error" => "خطا در پرس‌وجوی مقاطع"]);
            exit;
        }
        $stmt->bind_param("s", $hospital_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $maghts = [];
        while ($row = $result->fetch_assoc()) {
            $maghts[] = $row;
        }
        $stmt->close();
        $conn->close();
        ob_end_clean();
        echo json_encode($maghts);
        exit;
    }

    // -------------------------
    // 3- دریافت تخصص‌های مرتبط با یک مقطع در بیمارستان
    // -------------------------
    if ($action == 'getSpecialties' && isset($_GET['hospital'])) {
        $conn = getDBConnection();
        header('Content-Type: application/json; charset=utf-8');
        $hospital_code = $_GET['hospital'];
        $includeMaghtFilter = (isset($_GET['maght']) && $_GET['maght'] !== '' && $_GET['maght'] !== 'all');
        if ($includeMaghtFilter) {
            $maght_code = $_GET['maght'];
            $query = "SELECT DISTINCT specialty_code, specialty FROM doctor_performance WHERE hospital_code = ? AND maght_code = ? ORDER BY specialty";
        } else {
            $query = "SELECT DISTINCT specialty_code, specialty FROM doctor_performance WHERE hospital_code = ? ORDER BY specialty";
        }
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            ob_end_clean();
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(["error" => "خطا در پرس‌وجوی تخصص‌ها"]);
            exit;
        }
        if ($includeMaghtFilter) {
            $stmt->bind_param("ss", $hospital_code, $maght_code);
        } else {
            $stmt->bind_param("s", $hospital_code);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $specialties = [];
        while ($row = $result->fetch_assoc()) {
            $specialties[] = $row;
        }
        $stmt->close();
        $conn->close();
        ob_end_clean();
        echo json_encode($specialties);
        exit;
    }

    // -------------------------
    // 4- دریافت پزشکان بر اساس بیمارستان، مقطع و تخصص
    // -------------------------
    if ($action == 'getDoctors' && isset($_GET['hospital'], $_GET['specialty'])) {
        $conn = getDBConnection();
        header('Content-Type: application/json; charset=utf-8');
        $hospital_code  = $_GET['hospital'];
        $maght = (isset($_GET['maght']) && $_GET['maght'] !== 'all') ? $_GET['maght'] : '';
        $specialty_code = $_GET['specialty'];
        if ($maght !== '') {
            $query = "SELECT DISTINCT doctor_code, doctor_name FROM doctor_performance WHERE hospital_code = ? AND maght_code = ? AND specialty_code = ? ORDER BY doctor_name";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                ob_end_clean();
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(["error" => "خطا در پرس‌وجوی پزشکان"]);
                exit;
            }
            $stmt->bind_param("sss", $hospital_code, $maght, $specialty_code);
        } else {
            $query = "SELECT DISTINCT doctor_code, doctor_name FROM doctor_performance WHERE hospital_code = ? AND specialty_code = ? ORDER BY doctor_name";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                ob_end_clean();
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(["error" => "خطا در پرس‌وجوی پزشکان"]);
                exit;
            }
            $stmt->bind_param("ss", $hospital_code, $specialty_code);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $doctors = [];
        while ($row = $result->fetch_assoc()) {
            $doctors[] = $row;
        }
        $stmt->close();
        $conn->close();
        ob_end_clean();
        echo json_encode($doctors);
        exit;
    }

    // -------------------------
    // 5- جستجوی پیشرفته پزشک
    // -------------------------
    if ($action == 'searchDoctor' && isset($_GET['query'])) {
        $conn = getDBConnection();
        header('Content-Type: application/json; charset=utf-8');
        $queryText = $_GET['query'];
        $normalizedQuery = mb_strtolower($queryText, 'UTF-8');
        $normalizedQuery = str_replace(array('آ', 'ي', 'ك'), array('ا', 'ی', 'ک'), $normalizedQuery);
        $stmt = $conn->prepare("SELECT DISTINCT doctor_name, doctor_code, hospital_code, hospital, maght_code, maght, specialty_code, specialty 
                                FROM doctor_performance 
                                WHERE LOWER(REPLACE(REPLACE(REPLACE(doctor_name, 'آ', 'ا'), 'ي','ی'), 'ك','ک')) LIKE ? 
                                ORDER BY doctor_name");
        if ($stmt === false) {
            ob_end_clean();
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(["error" => "خطا در پرس‌وجوی پزشک"]);
            exit;
        }
        $searchStr = "%" . $normalizedQuery . "%";
        $stmt->bind_param("s", $searchStr);
        $stmt->execute();
        $result = $stmt->get_result();
        $doctors = [];
        while ($row = $result->fetch_assoc()) {
            $doctors[] = $row;
        }
        $stmt->close();
        $conn->close();
        ob_end_clean();
        echo json_encode($doctors);
        exit;
    }
}
// -------------------------
// پایان بخش‌های AJAX
// -------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once 'vendor/autoload.php';
require_once 'TopMenu.php';
require_once 'Header.php';
require_once 'HospitalRepository.php';

// تعیین مقادیر اولیه فرم (در صورت ارسال نشدن توسط POST)
$selectedYear      = isset($_POST["year"]) ? htmlspecialchars($_POST["year"], ENT_QUOTES, 'UTF-8') : "";
$selectedHospital  = isset($_POST["hospital"]) ? htmlspecialchars($_POST["hospital"], ENT_QUOTES, 'UTF-8') : "";
$selectedMaght     = isset($_POST["maght"]) ? htmlspecialchars($_POST["maght"], ENT_QUOTES, 'UTF-8') : "";
$selectedSpecialty = isset($_POST["specialty"]) ? htmlspecialchars($_POST["specialty"], ENT_QUOTES, 'UTF-8') : "";
$selectedDoctor    = isset($_POST["doctor"]) ? htmlspecialchars($_POST["doctor"], ENT_QUOTES, 'UTF-8') : "";
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>عملکرد یکساله پزشک</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- بارگذاری CSS های Bootstrap و Font Awesome -->
    <link href="css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <style>
        body {
            font-family: 'Sahel', sans-serif;
            background: #f8f9fa;
        }
        .floating-modal .modal-dialog {
            max-width: 90%;
        }
        .chart-container {
            max-width: 900px;
            margin: auto;
        }
        .chart-wrapper {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <?php
    $header = new Header("عملکرد یکساله پزشک", $_SESSION["username"]);
    echo $header->render();
    ?>
    <div class="container mt-3">
        <!-- نمایش منوی بالایی -->
        <?php
        $topMenu = new TopMenu();
        echo $topMenu->render();
        ?>
        <div class="card">
            <div class="card-header">فیلتر گزارش</div>
            <div class="card-body">
                <div class="mb-3">
                    <!-- دکمه جستجوی پیشرفته پزشک -->
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#doctorSearchModal">
                        <i class="fa fa-search"></i> جستجوی پزشک پیشرفته
                    </button>
                </div>
                <form id="filterForm">
                    <div class="row g-3">
                        <!-- انتخاب سال -->
                        <div class="col-md-2">
                            <label for="year" class="form-label">سال</label>
                            <select name="year" id="year" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <?php
                                $currentYear = 1410;
                                for ($i = $currentYear; $i >= $currentYear - 10; $i--) {
                                    $sel = ($selectedYear == $i) ? "selected" : "";
                                    echo "<option value=\"$i\" $sel>$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <!-- انتخاب بیمارستان -->
                        <div class="col-md-3">
                            <label for="hospital" class="form-label">بیمارستان</label>
                            <select name="hospital" id="hospital" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <?php
                                $hospitals = HospitalRepository::getHospitals();
                                foreach ($hospitals as $hospital) {
                                    $sel = ($selectedHospital == $hospital->getCode()) ? "selected" : "";
                                    echo "<option value=\"{$hospital->getCode()}\" $sel>{$hospital->getName()}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <!-- انتخاب مقطع -->
                        <div class="col-md-2">
                            <label for="maght" class="form-label">مقطع</label>
                            <select name="maght" id="maght" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <option value="all" <?php echo ($selectedMaght === 'all') ? 'selected' : ''; ?>>همه</option>
                                <?php
                                if (!empty($selectedHospital) && isset($maghts)) {
                                    foreach ($maghts as $m) {
                                        $sel = ($selectedMaght == $m['maght_code']) ? "selected" : "";
                                        echo "<option value=\"{$m['maght_code']}\" $sel>{$m['maght']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <!-- انتخاب تخصص -->
                        <div class="col-md-2">
                            <label for="specialty" class="form-label">نام تخصص</label>
                            <select name="specialty" id="specialty" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <?php
                                if (!empty($selectedHospital) && !empty($selectedMaght) && isset($specialties)) {
                                    foreach ($specialties as $s) {
                                        $sel = ($selectedSpecialty == $s['specialty_code']) ? "selected" : "";
                                        echo "<option value=\"{$s['specialty_code']}\" $sel>{$s['specialty']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <!-- انتخاب پزشک -->
                        <div class="col-md-3">
                            <label for="doctor" class="form-label">نام پزشک</label>
                            <select name="doctor" id="doctor" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <?php
                                if (!empty($selectedHospital) && !empty($selectedMaght) && !empty($selectedSpecialty) && isset($doctors)) {
                                    foreach ($doctors as $d) {
                                        $sel = ($selectedDoctor == $d['doctor_code']) ? "selected" : "";
                                        echo "<option value=\"{$d['doctor_code']}\" $sel>{$d['doctor_name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <!-- دکمه نمایش نتایج (از نوع button برای جلوگیری از ارسال فرم) -->
                        <button type="button" id="filterBtn" class="btn btn-primary">نمایش نتایج نمودار</button>
                        <button type="button" id="doctorReportBtn" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#doctorReportModal">کارنامه پزشک</button>
                        <button type="button" id="exitReportBtn" class="btn btn-danger">خروج</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- بخش نمودار (ابتدا مخفی است) -->
        <div id="chartsSection" style="display:none;">
            <div class="mt-4 chart-container">
                <h4 class="mb-3">نمودار تجمعی تعداد ویزیت‌ها (ماهانه)</h4>
                <canvas id="visitsChart"></canvas>
            </div>
            <div class="mt-4 chart-container">
                <h4 class="mb-3">نمودار درآمد ناخالص (ماهانه)</h4>
                <canvas id="incomeChart"></canvas>
            </div>
            <div id="metricsCharts"></div>
        </div>

        <!-- مدال کارنامه پزشک -->
        <div class="modal fade" id="doctorReportModal" tabindex="-1" aria-labelledby="doctorReportModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg floating-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">کارنامه پزشک</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <!-- هدر جدول کارنامه به صورت داینامیک ایجاد خواهد شد -->
                            <table class="table table-bordered" id="reportTable">
                                <thead class="table-dark">
                                    <!-- هدر در هنگام دریافت داده AJAX جایگذاری می‌شود -->
                                </thead>
                                <tbody>
                                    <!-- ردیف‌های کارنامه از طریق JS به‌روز می‌شوند -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="printReportBtn" class="btn btn-secondary">چاپ کارنامه</button>
                    </div>
                </div>
            </div>
        </div>

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
    </div>

    <!-- اسکریپت‌های مورد نیاز -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/chart.js"></script>
    <link rel="stylesheet" href="css/bootstrap-icons.css">

    <?php
    // ایجاد آرایه‌ای از کد بیمارستان به سطح آن برای استفاده در JavaScript
    $hospitalsList = HospitalRepository::getHospitals();
    $hospitalsMapping = [];
    foreach($hospitalsList as $hName => $hospital) {
        $hospitalsMapping[$hospital->getCode()] = $hospital->getLevel(); // استفاده از alias getLevel()
    }
    ?>
    <script>
        var hospitals = <?php echo json_encode($hospitalsMapping); ?>;
    </script>
    <script>
        $(document).ready(function() {

            // متغیرهای سراسری نمودارها
            var visitsChartInstance = null;
            var incomeChartInstance = null;
            var metricsChartInstances = {};

            // تابع به‌روز‌رسانی select (برای هماهنگ‌سازی انتخاب‌ها)
            function updateSelect(selector, value, callback) {
                $(selector).val(value).trigger('change');
                setTimeout(function() {
                    if (typeof callback === 'function')
                        callback();
                }, 600);
            }

            // تابع پر کردن فیلدهای پزشک از نتایج جستجو
            function fillDoctorFields(doctorData) {
                updateSelect('#hospital', doctorData.hospital_code, function() {
                    updateSelect('#maght', doctorData.maght_code, function() {
                        updateSelect('#specialty', doctorData.specialty_code, function() {
                            $('#doctor').val(doctorData.doctor_code);
                        });
                    });
                });
            }

            // به‌روز‌رسانی مقاطع پس از انتخاب بیمارستان
            $('#hospital').change(function() {
                var hospital = $(this).val();
                $.ajax({
                    url: '?action=getMaghts',
                    type: 'GET',
                    data: { hospital: hospital },
                    dataType: 'json',
                    success: function(data) {
                        var maghtSelect = $('#maght');
                        maghtSelect.empty();
                        maghtSelect.append($('<option>', { value: '', text: 'انتخاب کنید' }));
                        // اضافه کردن گزینه "همه"
                        maghtSelect.append($('<option>', { value: 'all', text: 'همه' }));
                        $.each(data, function(i, item) {
                            maghtSelect.append($('<option>', {
                                value: item.maght_code,
                                text: item.maght
                            }));
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error("خطا در دریافت مقاطع:", error);
                        alert("خطا در دریافت مقاطع.");
                    }
                });
            });

            // به‌روز‌رسانی تخصص‌ها پس از انتخاب مقطع
            $('#maght').change(function() {
                var hospital = $('#hospital').val();
                var maght = $(this).val();
                if (maght === 'all') {
                    maght = '';
                }
                $.ajax({
                    url: '?action=getSpecialties',
                    type: 'GET',
                    data: { hospital: hospital, maght: maght },
                    dataType: 'json',
                    success: function(data) {
                        var specialtySelect = $('#specialty');
                        specialtySelect.empty();
                        specialtySelect.append($('<option>', { value: '', text: 'انتخاب کنید' }));
                        $.each(data, function(i, item) {
                            specialtySelect.append($('<option>', {
                                value: item.specialty_code,
                                text: item.specialty
                            }));
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error("خطا در دریافت تخصص‌ها:", error);
                        alert("خطا در دریافت تخصص‌ها.");
                    }
                });
            });

            // به‌روز‌رسانی پزشکان پس از انتخاب تخصص
            $('#specialty').change(function() {
                var hospital = $('#hospital').val();
                var maght = $('#maght').val();
                if (maght === 'all') { maght = ''; }
                var specialty = $(this).val();
                $.ajax({
                    url: '?action=getDoctors',
                    type: 'GET',
                    data: { hospital: hospital, maght: maght, specialty: specialty },
                    dataType: 'json',
                    success: function(data) {
                        var doctorSelect = $('#doctor');
                        doctorSelect.empty();
                        doctorSelect.append($('<option>', { value: '', text: 'انتخاب کنید' }));
                        $.each(data, function(i, item) {
                            doctorSelect.append($('<option>', {
                                value: item.doctor_code,
                                text: item.doctor_name
                            }));
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error("خطا در دریافت پزشکان:", error);
                        alert("خطا در دریافت پزشکان.");
                    }
                });
            });

            // جستجوی پیشرفته پزشک
            $('#doctor_search_button').click(function() {
                var query = $('#doctor_search_input').val().trim();
                if (!query) {
                    alert("لطفاً نام پزشک را وارد کنید.");
                    return;
                }
                if (!/^[\u0600-\u06FF\s]+$/.test(query)) {
                    alert("نام پزشک باید شامل حروف فارسی باشد.");
                    return;
                }
                $.ajax({
                    url: '?action=searchDoctor',
                    type: 'GET',
                    data: { query: query },
                    dataType: 'json',
                    success: function(data) {
                        var resultsList = $('#doctor_search_results');
                        resultsList.empty();
                        if (data.length === 0) {
                            resultsList.append('<li class="list-group-item">هیچ موردی یافت نشد.</li>');
                        } else {
                            $.each(data, function(i, doctor) {
                                var li = $('<li class="list-group-item list-group-item-action"></li>')
                                    .text(doctor.doctor_name + " - " + doctor.hospital + " - " + doctor.maght + " - " + doctor.specialty)
                                    .data('doctor', doctor);
                                resultsList.append(li);
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        alert("خطا در جستجو: " + error);
                    }
                });
            });

            // انتخاب پزشک از نتایج جستجو
            $('#doctor_search_results').on('click', '.list-group-item', function() {
                var doctorData = $(this).data('doctor');
                fillDoctorFields(doctorData);
                $('#doctorSearchModal').modal('hide');
            });

            // قبل از تعیین وضعیت، وضعیت کلی پزشک را بر اساس تمام ماه‌ها محاسبه کنیم.
            // اگر در هر ماه حداقل یک مقدار k (از k جراحی یا k بیهوشی) موجود باشد، 
            // کل ماه‌ها با استفاده از k ارزیابی شوند.
            var globalK = 0;
            // (این متغیر در زمان دریافت داده‌های گزارش از Ajax محاسبه خواهد شد)

            // تابع تعیین وضعیت پزشک؛ با استفاده از متغیر سراسری rankByK که در ادامه محاسبه می‌شود.
            function determineDoctorStatus(monthData, rankByK) {
                if (rankByK) {
                    // استفاده از رتبه‌بندی بر اساس k
                    var kValue = (parseInt(monthData.sum_surgery_K) || 0) + (parseInt(monthData.sum_anesthesia_K) || 0);
                    // اعمال آستانه‌ها برای هر سطح بیمارستان
                    var hospitalCode = $('#hospital').val();
                    var hospitalLevel = hospitals[hospitalCode] || 'A';
                    if (hospitalLevel === 'A') {
                        if (kValue > 1800) return "ممتاز";
                        else if (kValue > 1000) return "مطلوب";
                        else if (kValue >= 500) return "قابل قبول";
                        else return "ضعیف";
                    } else if (hospitalLevel === 'B') {
                        if (kValue > 1400) return "ممتاز";
                        else if (kValue > 800) return "مطلوب";
                        else if (kValue >= 500) return "قابل قبول";
                        else return "ضعیف";
                    } else if (hospitalLevel === 'C' || hospitalLevel === 'D') {
                        if (kValue > 900) return "ممتاز";
                        else if (kValue > 700) return "مطلوب";
                        else if (kValue >= 400) return "قابل قبول";
                        else return "ضعیف";
                    } else {
                        return "نامشخص";
                    }
                } else {
                    // رتبه‌بندی بر اساس درآمد ناخالص
                    var income = parseInt(monthData.sum_gross_income) || 0;
                    var hospitalCode = $('#hospital').val();
                    var hospitalLevel = hospitals[hospitalCode] || 'A';
                    if (hospitalLevel === 'A') {
                        if (income > 80000000) return "درآمد ممتاز";
                        else if (income >= 50000000) return "درآمد مطلوب";
                        else if (income >= 30000000) return "درآمد قابل قبول";
                        else return "درآمد ضعیف";
                    } else if (hospitalLevel === 'B') {
                        if (income > 60000000) return "درآمد ممتاز";
                        else if (income >= 40000000) return "درآمد مطلوب";
                        else if (income >= 20000000) return "درآمد قابل قبول";
                        else return "درآمد ضعیف";
                    } else if (hospitalLevel === 'C' || hospitalLevel === 'D') {
                        if (income > 50000000) return "درآمد ممتاز";
                        else if (income >= 30000000) return "درآمد مطلوب";
                        else if (income >= 15000000) return "درآمد قابل قبول";
                        else return "درآمد ضعیف";
                    } else {
                        return "نامشخص";
                    }
                }
            }

            // رویداد کلیک دکمه "نمایش نتایج نمودار"
            $('#filterBtn').click(function() {
                var formData = {
                    year: $('#year').val(),
                    hospital: $('#hospital').val(),
                    maght: $('#maght').val(),
                    specialty: $('#specialty').val(),
                    doctor: $('#doctor').val()
                };
                if (Object.values(formData).some(function(v){ return !v; })) {
                    alert("لطفاً تمامی فیلدها را پر کنید.");
                    return;
                }
                $.ajax({
                    url: '?action=filterData',
                    type: 'GET',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            alert("خطا: " + response.error);
                            return;
                        }
                        
                        // به‌روز‌رسانی هدر جدول کارنامه به صورت داینامیک
                        var headerHtml = '<tr>';
                        headerHtml += '<th>ماه</th>';
                        headerHtml += '<th>تعداد کل ویزیت</th>';
                        headerHtml += '<th>درآمد ناخالص</th>';
                        var fixedMetricOrder = ["sum_endoscopy", "sum_colonoscopy", "sum_bronchoscopy", "sum_ecocardio", "sum_inpatient", "sum_surgery", "sum_surgery_K", "sum_anesthesia", "sum_anesthesia_K"];
                        $.each(fixedMetricOrder, function(index, key) {
                            if (response.includeMetrics.hasOwnProperty(key)) {
                                headerHtml += '<th>' + response.includeMetrics[key] + '</th>';
                            }
                        });
                        headerHtml += '<th>وضعیت پزشک</th>';
                        headerHtml += '</tr>';
                        $('#reportTable thead').html(headerHtml);

                        // پاکسازی نمودارهای قبلی
                        if (visitsChartInstance) {
                            visitsChartInstance.destroy();
                            visitsChartInstance = null;
                        }
                        if (incomeChartInstance) {
                            incomeChartInstance.destroy();
                            incomeChartInstance = null;
                        }
                        $.each(metricsChartInstances, function(key, chart) {
                            chart.destroy();
                        });
                        metricsChartInstances = {};

                        // آرایه ثابت برچسب‌های ماه
                        var labels = ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
                        var monthKeys = ["01","02","03","04","05","06","07","08","09","10","11","12"];
                        var dataByMonth = response.dataByMonth;
                        var visitsData = {
                            visitSakhte: [],
                            visitTamin: [],
                            visitSalamat: [],
                            visitAzad: [],
                            otherVisits: []
                        };
                        var incomeData = [];
                        $.each(monthKeys, function(i, key) {
                            var data = dataByMonth[key];
                            visitsData.visitSakhte.push(parseInt(data.sum_visit_sakhte));
                            visitsData.visitTamin.push(parseInt(data.sum_visit_tamin));
                            visitsData.visitSalamat.push(parseInt(data.sum_visit_salamat));
                            visitsData.visitAzad.push(parseInt(data.sum_visit_azad));
                            visitsData.otherVisits.push(parseInt(data.sum_other_visits));
                            incomeData.push(parseInt(data.sum_gross_income));
                        });
                        
                        // محاسبه وضعیت کلی پزشک: اگر در هر ماه بخش k (surgery_K یا anesthesia_K) موجود باشد،
                        // آنگاه تمام ماه‌ها با استفاده از k رتبه‌بندی شوند.
                        var globalK = 0;
                        $.each(monthKeys, function(i, key) {
                            var d = dataByMonth[key];
                            globalK += (parseInt(d.sum_surgery_K) || 0);
                            globalK += (parseInt(d.sum_anesthesia_K) || 0);
                        });
                        var rankByK = globalK > 0;

                        // نمایش بخش نمودارها
                        $('#chartsSection').show();

                        // رسم نمودار تعداد ویزیت‌ها (Stacked Bar)
                        var ctxVisits = document.getElementById('visitsChart').getContext('2d');
                        visitsChartInstance = new Chart(ctxVisits, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                        label: 'ویزیت ساخد',
                                        data: visitsData.visitSakhte,
                                        backgroundColor: 'rgba(75, 192, 192, 0.7)'
                                    },
                                    {
                                        label: 'ویزیت تامین',
                                        data: visitsData.visitTamin,
                                        backgroundColor: 'rgba(153, 102, 255, 0.7)'
                                    },
                                    {
                                        label: 'ویزیت سلامت',
                                        data: visitsData.visitSalamat,
                                        backgroundColor: 'rgba(255, 159, 64, 0.7)'
                                    },
                                    {
                                        label: 'ویزیت آزاد',
                                        data: visitsData.visitAzad,
                                        backgroundColor: 'rgba(255, 99, 132, 0.7)'
                                    },
                                    {
                                        label: 'سایر ویزیت‌ها',
                                        data: visitsData.otherVisits,
                                        backgroundColor: 'rgba(54, 162, 235, 0.7)'
                                    }
                                ]
                            },
                            options: {
                                plugins: {
                                    title: { display: true, text: 'تجمعی تعداد ویزیت‌ها' },
                                    tooltip: {
                                        callbacks: {
                                            label: function(tooltipItem) {
                                                return tooltipItem.dataset.label + ': ' + tooltipItem.raw.toLocaleString();
                                            }
                                        }
                                    }
                                },
                                responsive: true,
                                animation: { duration: 2000, easing: 'easeOutBounce' },
                                scales: {
                                    x: { stacked: true },
                                    y: { stacked: true, beginAtZero: true }
                                }
                            }
                        });

                        // رسم نمودار درآمد ناخالص (Bar Chart)
                        var ctxIncome = document.getElementById('incomeChart').getContext('2d');
                        incomeChartInstance = new Chart(ctxIncome, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'درآمد ناخالص',
                                    data: incomeData,
                                    backgroundColor: 'rgba(255, 206, 86, 0.7)'
                                }]
                            },
                            options: {
                                plugins: {
                                    title: { display: true, text: 'درآمد ناخالص ماهانه' },
                                    tooltip: {
                                        callbacks: {
                                            label: function(tooltipItem) {
                                                return tooltipItem.dataset.label + ': ' + tooltipItem.raw.toLocaleString();
                                            }
                                        }
                                    }
                                },
                                responsive: true,
                                animation: { duration: 2000, easing: 'easeOutBounce' },
                                scales: { y: { beginAtZero: true } }
                            }
                        });

                        // رسم نمودارهای متریک‌های اضافی
                        var metricsChartsDiv = $('#metricsCharts');
                        metricsChartsDiv.empty();
                        $.each(fixedMetricOrder, function(i, key) {
                            if (response.includeMetrics.hasOwnProperty(key)) {
                                var metricData = [];
                                $.each(monthKeys, function(i, mKey) {
                                    metricData.push(parseInt(dataByMonth[mKey][key]));
                                });
                                if (metricData.some(function(value) { return value > 0; })) {
                                    var chartDiv = $('<div class="mt-4 chart-container"><h4 class="mb-3">نمودار ' + response.includeMetrics[key] + ' (ماهانه)</h4><canvas id="' + key + 'Chart"></canvas></div>');
                                    metricsChartsDiv.append(chartDiv);
                                    var ctxMetric = document.getElementById(key + 'Chart').getContext('2d');
                                    metricsChartInstances[key] = new Chart(ctxMetric, {
                                        type: 'bar',
                                        data: {
                                            labels: labels,
                                            datasets: [{
                                                label: response.includeMetrics[key],
                                                data: metricData,
                                                backgroundColor: 'rgba(100, 150, 200, 0.7)'
                                            }]
                                        },
                                        options: {
                                            plugins: {
                                                title: { display: true, text: response.includeMetrics[key] + ' (ماهانه)' },
                                                tooltip: {
                                                    callbacks: {
                                                        label: function(tooltipItem) {
                                                            return tooltipItem.dataset.label + ': ' + tooltipItem.raw.toLocaleString();
                                                        }
                                                    }
                                                }
                                            },
                                            responsive: true,
                                            animation: { duration: 2000, easing: 'easeOutBounce' },
                                            scales: { y: { beginAtZero: true } }
                                        }
                                    });
                                }
                            }
                        });
                        
                        // به‌روز‌رسانی جدول کارنامه پزشک در مدال با ستون‌های داینامیک
                        var reportTableBody = $('#reportTable tbody');
                        reportTableBody.empty();
                        $.each(monthKeys, function(i, key) {
                            var monthData = dataByMonth[key];
                            // اگر globalK (جمع کل k جراحی و k بیهوشی در تمام ماه‌ها) بیشتر از صفر باشد،
                            // یعنی وجود k در کل سال، پس برای هر ماه، وضعیت بر اساس k سنجیده می‌شود.
                            var doctorStatus = determineDoctorStatus(monthData, rankByK);
                            var extraMetricsTD = '';
                            $.each(fixedMetricOrder, function(i, keyMetric) {
                                if (response.includeMetrics.hasOwnProperty(keyMetric)) {
                                    extraMetricsTD += '<td>' + parseInt(monthData[keyMetric]).toLocaleString() + '</td>';
                                }
                            });
                            var row = '<tr>' +
                                '<td>' + response.months[key] + '</td>' +
                                '<td>' + parseInt(monthData.sum_total_visits).toLocaleString() + '</td>' +
                                '<td>' + parseInt(monthData.sum_gross_income).toLocaleString() + '</td>' +
                                extraMetricsTD +
                                '<td>' + doctorStatus + '</td>' +
                                '</tr>';
                            reportTableBody.append(row);
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error("خطای AJAX: ", error);
                        alert("خطا در دریافت داده‌ها: " + error);
                    }
                });
            });
            
            // دکمه چاپ کارنامه پزشک
            $('#printReportBtn').click(function() {
                window.print();
            });
            
            // دکمه خروج
            $('#exitReportBtn').click(function() {
                if (confirm("آیا از خروج مطمئن هستید؟")) {
                    window.location.href = 'amalkard_pezeshk_m.php';
                }
            });
        });
    </script>
</body>
</html>
