<?php
session_start();

// افزایش زمان اجرای اسکریپت و حافظه
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '2048M');

// بررسی ورود کاربر
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// فراخوانی فایل‌های مورد نیاز
require 'vendor/autoload.php';
require 'Header.php';
require_once 'HospitalRepository.php';
require 'Menu_m.php';
require 'Menu_add.php';
require 'Menu_omor.php';
require 'Menu_pirapezeshk.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// تابع به‌روز رسانی نوار پیشرفت (برای هر شیت یا عملیات حذف)
function updateProgress($sheetKey, $percent)
{
    $progressFile = 'progress.json';
    // مقداردهی پیش‌فرض برای تمامی کلیدها از جمله "delete"
    $defaultSheets = [
        'a' => 0,
        'b' => 0,
        'c' => 0,
        'd' => 0,
        'e' => 0,
        'f' => 0,
        'g' => 0,
        'h' => 0,
        'delete' => 0
    ];
    $progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : [];
    $progress = array_merge($defaultSheets, $progress);
    $progress[$sheetKey] = $percent;
    file_put_contents($progressFile, json_encode($progress));
}

// مقداردهی اولیه نوارهای پیشرفت
foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'delete'] as $sheet) {
    updateProgress($sheet, 0);
}

$performanceMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["upload_all"])) {
    // تنظیمات اتصال به دیتابیس
    $servername  = "localhost";
    $db_username = "root";
    $db_password = "mysql";
    $dbname      = "dashboard_db";

    $conn = new mysqli($servername, $db_username, $db_password, $dbname);
    if ($conn->connect_error) {
        die("خطا در اتصال به دیتابیس: " . $conn->connect_error);
    }

    // بررسی آپلود فایل اکسل
    if (isset($_FILES["excel_file"]) && $_FILES["excel_file"]["error"] == 0) {
        $allowedExt = array("xls", "xlsx");
        $fileName   = $_FILES["excel_file"]["name"];
        $fileTmp    = $_FILES["excel_file"]["tmp_name"];
        $ext        = pathinfo($fileName, PATHINFO_EXTENSION);

        if (in_array($ext, $allowedExt)) {
            if (!file_exists("uploads")) {
                mkdir("uploads", 0777, true);
            }
            $targetPath = "uploads/" . basename($fileName);
            if (move_uploaded_file($fileTmp, $targetPath)) {
                try {
                    // انتخاب خواننده مناسب بر اساس پسوند
                    if ($ext === 'xlsx') {
                        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                    } else {
                        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                    }
                    $reader->setReadDataOnly(true);
                    $spreadsheet = $reader->load($targetPath);

                    // ------------------------------------------------------
                    // مرحله حذف رکوردهای قدیمی قبل از درج اطلاعات جدید
                    // ------------------------------------------------------
                    // تابع حذف رکوردها بر اساس شرط
                    function deleteRecordsByCondition($conn, $tableName, $hospital_code, $value, $conditionType = 'year')
                    {
                        if ($conditionType === 'year') {
                            $sql = "DELETE FROM $tableName WHERE hospital_code = ? AND year = ?";
                        } else if ($conditionType === 'date') {
                            $sql = "DELETE FROM $tableName WHERE hospital_code = ? AND LEFT(date,4) = ?";
                        }
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ss", $hospital_code, $value);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // گروه‌های مبتنی بر "year": (شیت‌های B، E، F، G، H)
                    $deletionByYear = [
                        "central_indicators"    => [],
                        "paraclinic_stats"      => [],
                        "hospitalization_stats" => [],
                        "serv_paraclinic_stats" => [],
                        "procedure_stats"       => []
                    ];
                    // گروه‌های مبتنی بر "date": (شیت‌های A، C، D)
                    $deletionByDate = [
                        "doctor_salaries"    => [],
                        "doctor_performance" => [],
                        "fund_performance"   => []
                    ];

                    $totalDeletionTasks = 0;
                    $completedDeletionTasks = 0;

                    // استخراج مقادیر یکتا برای گروه‌های مبتنی بر year
                    // Sheet B: central_indicators
                    $sheetB = $spreadsheet->getSheetByName('b');
                    if ($sheetB) {
                        $dataB = $sheetB->toArray(null, true, false, false);
                        if (count($dataB) > 0 && count($dataB[0]) > 1) {
                            array_shift($dataB);
                        }
                        foreach ($dataB as $row) {
                            $row = array_pad($row, 4, '');
                            $hospital_code = trim($row[0]);
                            $year = trim($row[2]);
                            if ($hospital_code !== '' && $year !== '') {
                                $deletionByYear["central_indicators"][$hospital_code][$year] = true;
                            }
                        }
                    }
                    // Sheet E: paraclinic_stats
                    $sheetE = $spreadsheet->getSheetByName('e');
                    if ($sheetE) {
                        $dataE = $sheetE->toArray(null, true, false, false);
                        if (count($dataE) > 0 && count($dataE[0]) > 1) {
                            array_shift($dataE);
                        }
                        foreach ($dataE as $row) {
                            $row = array_pad($row, 5, '');
                            $hospital_code = trim($row[0]);
                            $year = trim($row[4]);
                            if ($hospital_code !== '' && $year !== '') {
                                $deletionByYear["paraclinic_stats"][$hospital_code][$year] = true;
                            }
                        }
                    }
                    // Sheet F: hospitalization_stats
                    $sheetF = $spreadsheet->getSheetByName('f');
                    if ($sheetF) {
                        $dataF = $sheetF->toArray(null, true, false, false);
                        if (count($dataF) > 0 && count($dataF[0]) > 1) {
                            array_shift($dataF);
                        }
                        foreach ($dataF as $row) {
                            $row = array_pad($row, 5, '');
                            $hospital_code = trim($row[0]);
                            $year = trim($row[4]);
                            if ($hospital_code !== '' && $year !== '') {
                                $deletionByYear["hospitalization_stats"][$hospital_code][$year] = true;
                            }
                        }
                    }
                    // Sheet G: serv_paraclinic_stats
                    $sheetG = $spreadsheet->getSheetByName('g');
                    if ($sheetG) {
                        $dataG = $sheetG->toArray(null, true, false, false);
                        if (count($dataG) > 0 && count($dataG[0]) > 1) {
                            array_shift($dataG);
                        }
                        foreach ($dataG as $row) {
                            $row = array_pad($row, 7, '');
                            $hospital_code = trim($row[0]);
                            $year = trim($row[6]);
                            if ($hospital_code !== '' && $year !== '') {
                                $deletionByYear["serv_paraclinic_stats"][$hospital_code][$year] = true;
                            }
                        }
                    }
                    // Sheet H: procedure_stats (گروه H)
                    $sheetH = $spreadsheet->getSheetByName('h');
                    if ($sheetH) {
                        $dataH = $sheetH->toArray(null, true, false, false);
                        if (count($dataH) > 0 && count($dataH[0]) > 1) {
                            array_shift($dataH);
                        }
                        foreach ($dataH as $row) {
                            $row = array_pad($row, 5, '');
                            $hospital_code = trim($row[0]);
                            $year = trim($row[4]);
                            if ($hospital_code !== '' && $year !== '') {
                                $deletionByYear["procedure_stats"][$hospital_code][$year] = true;
                            }
                        }
                    }

                    // استخراج مقادیر یکتا برای گروه‌های مبتنی بر date
                    // Sheet A: doctor_salaries
                    $sheetA = $spreadsheet->getSheetByName('a');
                    if ($sheetA) {
                        $dataA = $sheetA->toArray(null, true, false, false);
                        if (count($dataA) > 0 && count($dataA[0]) > 1) {
                            array_shift($dataA);
                        }
                        foreach ($dataA as $row) {
                            $row = array_pad($row, 3, '');
                            $hospital_code = trim($row[0]);
                            $date = trim($row[2]);
                            if ($hospital_code !== '' && $date !== '' && strlen($date) >= 4) {
                                $yearPrefix = substr($date, 0, 4);
                                $deletionByDate["doctor_salaries"][$hospital_code][$yearPrefix] = true;
                            }
                        }
                    }
                    // Sheet C: doctor_performance
                    $sheetC = $spreadsheet->getSheetByName('c');
                    if ($sheetC) {
                        $dataC = $sheetC->toArray(null, true, false, false);
                        if (count($dataC) > 0 && count($dataC[0]) > 1) {
                            array_shift($dataC);
                        }
                        foreach ($dataC as $row) {
                            $row = array_pad($row, 3, '');
                            $hospital_code = trim($row[0]);
                            $date = trim($row[2]);
                            if ($hospital_code !== '' && $date !== '' && strlen($date) >= 4) {
                                $yearPrefix = substr($date, 0, 4);
                                $deletionByDate["doctor_performance"][$hospital_code][$yearPrefix] = true;
                            }
                        }
                    }
                    // Sheet D: fund_performance
                    $sheetD = $spreadsheet->getSheetByName('d');
                    if ($sheetD) {
                        $dataD = $sheetD->toArray(null, true, false, false);
                        if (count($dataD) > 0 && count($dataD[0]) > 1) {
                            array_shift($dataD);
                        }
                        foreach ($dataD as $row) {
                            $row = array_pad($row, 5, '');
                            $hospital_code = trim($row[0]);
                            $date = trim($row[4]);
                            if ($hospital_code !== '' && $date !== '' && strlen($date) >= 4) {
                                $yearPrefix = substr($date, 0, 4);
                                $deletionByDate["fund_performance"][$hospital_code][$yearPrefix] = true;
                            }
                        }
                    }

                    // محاسبه تعداد کل تسک‌های حذف
                    foreach ($deletionByYear as $table => $hospitals) {
                        foreach ($hospitals as $hospital_code => $years) {
                            $totalDeletionTasks += count($years);
                        }
                    }
                    foreach ($deletionByDate as $table => $hospitals) {
                        foreach ($hospitals as $hospital_code => $prefixes) {
                            $totalDeletionTasks += count($prefixes);
                        }
                    }

                    // تابع به‌روزرسانی پیشرفت حذف
                    function updateDeleteProgress($percent)
                    {
                        updateProgress('delete', $percent);
                    }

                    // حذف رکوردهای قدیمی براساس شرط year
                    foreach ($deletionByYear as $table => $hospitals) {
                        foreach ($hospitals as $hospital_code => $years) {
                            foreach ($years as $year => $_) {
                                deleteRecordsByCondition($conn, $table, $hospital_code, $year, 'year');
                                $completedDeletionTasks++;
                                $percent = intval(($completedDeletionTasks / $totalDeletionTasks) * 100);
                                updateDeleteProgress($percent);
                            }
                        }
                    }
                    // حذف رکوردهای قدیمی براساس شرط date (چهار حرف اول تاریخ)
                    foreach ($deletionByDate as $table => $hospitals) {
                        foreach ($hospitals as $hospital_code => $prefixes) {
                            foreach ($prefixes as $yearPrefix => $_) {
                                deleteRecordsByCondition($conn, $table, $hospital_code, $yearPrefix, 'date');
                                $completedDeletionTasks++;
                                $percent = intval(($completedDeletionTasks / $totalDeletionTasks) * 100);
                                updateDeleteProgress($percent);
                            }
                        }
                    }

                    // ------------------------------------------------------
                    // آغاز درج داده‌های جدید پس از حذف رکوردهای قدیمی
                    $overallInsertCount = 0;
                    $messages = [];
                    // ---------------------------
                    /////////////////////////////////
                    // Sheet A: doctor_salaries
                    $sheetA = $spreadsheet->getSheetByName('a');
                    if ($sheetA) {
                        $dataA = $sheetA->toArray(null, true, false, false);
                        if (count($dataA) > 0) {
                            if (count($dataA[0]) > 1) {
                                array_shift($dataA); // حذف سطر سربرگ
                            }
                            $totalRows = count($dataA);
                            $queryA = "INSERT INTO doctor_salaries 
                                (hospital_code, hospital, date, total, gross, tax, parkis, net)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                    hospital_code = VALUES(hospital_code),
                                    hospital = VALUES(hospital),
                                    date = VALUES(date),
                                    total = VALUES(total),
                                    gross = VALUES(gross),
                                    tax = VALUES(tax),
                                    parkis = VALUES(parkis),
                                    net = VALUES(net)";
                            $stmtA = $conn->prepare($queryA);
                            $countA = 0;
                            $currentRow = 0;
                            foreach ($dataA as $row) {
                                $row = array_pad($row, 8, '');
                                $hospital_code = trim($row[0]);
                                $hospital = trim($row[1]);
                                $date = trim($row[2]);
                                if ($date === '') {
                                    continue;
                                }
                                $total  = (trim($row[3]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[3]))) : 0.0;
                                $gross  = (trim($row[4]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[4]))) : 0.0;
                                $tax    = (trim($row[5]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[5]))) : 0.0;
                                $parkis = (trim($row[6]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[6]))) : 0.0;
                                $net    = (trim($row[7]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[7]))) : 0.0;

                                $stmtA->bind_param("sssddddd", $hospital_code, $hospital, $date, $total, $gross, $tax, $parkis, $net);
                                if (!$stmtA->execute()) {
                                    throw new Exception("خطا در درج داده در sheet a: " . $stmtA->error);
                                }
                                $countA++;
                                $currentRow++;
                                if ($currentRow % 10 == 0 || $currentRow == $totalRows) {
                                    $percent = intval(($currentRow / $totalRows) * 100);
                                    updateProgress('a', $percent);
                                }
                            }
                            $stmtA->close();
                            $messages[] = "Sheet A (doctor_salaries): $countA رکورد.";
                            $overallInsertCount += $countA;
                        }
                    }

                    /////////////////////////////////
                    // Sheet B: central_indicators
                    $sheetB = $spreadsheet->getSheetByName('b');
                    if ($sheetB) {
                        $dataB = $sheetB->toArray(null, true, false, false);
                        if (count($dataB) > 0) {
                            if (count($dataB[0]) > 1) {
                                array_shift($dataB);
                            }
                            $totalRows = count($dataB);
                            $queryB = "INSERT INTO central_indicators 
                                (hospital_code, hospital, year, month, shakhes_ha, tedad_morajein, tedad_khodmat, tedad_basti, 
                                 tedad_amal, tedad_k_amal, tedad_k_bihoshi, nuskhe_sarpaei, visit_dandanpezeshki, 
                                 nuskhe_dandanpezeshki, mablagh_riali_daramad_bimarestan, mablagh_kasoorat, talab_az_bimeha, 
                                 daramad_daru_khane, daramad_pezeshkilinikha, tedad_visit_sarpai, darib_eshghal_takht, 
                                 fasele_gardesh_takht, motevaset_moddat_eikhtelaf_bimaran)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                    hospital_code = VALUES(hospital_code),
                                    hospital = VALUES(hospital),
                                    year = VALUES(year),
                                    month = VALUES(month),
                                    shakhes_ha = VALUES(shakhes_ha),
                                    tedad_morajein = VALUES(tedad_morajein),
                                    tedad_khodmat = VALUES(tedad_khodmat),
                                    tedad_basti = VALUES(tedad_basti),
                                    tedad_amal = VALUES(tedad_amal),
                                    tedad_k_amal = VALUES(tedad_k_amal),
                                    tedad_k_bihoshi = VALUES(tedad_k_bihoshi),
                                    nuskhe_sarpaei = VALUES(nuskhe_sarpaei),
                                    visit_dandanpezeshki = VALUES(visit_dandanpezeshki),
                                    nuskhe_dandanpezeshki = VALUES(nuskhe_dandanpezeshki),
                                    mablagh_riali_daramad_bimarestan = VALUES(mablagh_riali_daramad_bimarestan),
                                    mablagh_kasoorat = VALUES(mablagh_kasoorat),
                                    talab_az_bimeha = VALUES(talab_az_bimeha),
                                    daramad_daru_khane = VALUES(daramad_daru_khane),
                                    daramad_pezeshkilinikha = VALUES(daramad_pezeshkilinikha),
                                    tedad_visit_sarpai = VALUES(tedad_visit_sarpai),
                                    darib_eshghal_takht = VALUES(darib_eshghal_takht),
                                    fasele_gardesh_takht = VALUES(fasele_gardesh_takht),
                                    motevaset_moddat_eikhtelaf_bimaran = VALUES(motevaset_moddat_eikhtelaf_bimaran)";
                            $stmtB = $conn->prepare($queryB);
                            $countB = 0;
                            $currentRow = 0;
                            foreach ($dataB as $row) {
                                $row = array_pad($row, 23, '');
                                $hospital_code = trim($row[0]);
                                $hospital = trim($row[1]);
                                $year = trim($row[2]);
                                $month = trim($row[3]);
                                if ($year === '') {
                                    continue;
                                }
                                $shakhes_ha = trim($row[4]);
                                $tedad_morajein = (trim($row[5]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[5]))) : 0.0;
                                $tedad_khodmat = (trim($row[6]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[6]))) : 0.0;
                                $tedad_basti   = (trim($row[7]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[7]))) : 0.0;
                                $tedad_amal    = (trim($row[8]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[8]))) : 0.0;
                                $tedad_k_amal  = (trim($row[9]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[9]))) : 0.0;
                                $tedad_k_bihoshi = (trim($row[10]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[10]))) : 0.0;
                                $nuskhe_sarpaei = (trim($row[11]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[11]))) : 0.0;
                                $visit_dandanpezeshki = (trim($row[12]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[12]))) : 0.0;
                                $nuskhe_dandanpezeshki = (trim($row[13]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[13]))) : 0.0;
                                $mablagh_riali_daramad_bimarestan = (trim($row[14]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[14]))) : 0.0;
                                $mablagh_kasoorat = (trim($row[15]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[15]))) : 0.0;
                                $talab_az_bimeha = (trim($row[16]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[16]))) : 0.0;
                                $daramad_daru_khane = (trim($row[17]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[17]))) : 0.0;
                                $daramad_pezeshkilinikha = (trim($row[18]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[18]))) : 0.0;
                                $tedad_visit_sarpai = (trim($row[19]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[19]))) : 0.0;
                                $darib_eshghal_takht = (trim($row[20]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[20]))) : 0.0;
                                $fasele_gardesh_takht = (trim($row[21]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[21]))) : 0.0;
                                $motevaset_moddat_eikhtelaf_bimaran = (trim($row[22]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[22]))) : 0.0;

                                $stmtB->bind_param(
                                    "sssssdddddddddddddddddd",
                                    $hospital_code,
                                    $hospital,
                                    $year,
                                    $month,
                                    $shakhes_ha,
                                    $tedad_morajein,
                                    $tedad_khodmat,
                                    $tedad_basti,
                                    $tedad_amal,
                                    $tedad_k_amal,
                                    $tedad_k_bihoshi,
                                    $nuskhe_sarpaei,
                                    $visit_dandanpezeshki,
                                    $nuskhe_dandanpezeshki,
                                    $mablagh_riali_daramad_bimarestan,
                                    $mablagh_kasoorat,
                                    $talab_az_bimeha,
                                    $daramad_daru_khane,
                                    $daramad_pezeshkilinikha,
                                    $tedad_visit_sarpai,
                                    $darib_eshghal_takht,
                                    $fasele_gardesh_takht,
                                    $motevaset_moddat_eikhtelaf_bimaran
                                );
                                if (!$stmtB->execute()) {
                                    throw new Exception("خطا در درج داده در sheet b برای ماه $month: " . $stmtB->error);
                                }
                                $countB++;
                                $currentRow++;
                                if ($currentRow % 10 == 0 || $currentRow == $totalRows) {
                                    $percent = intval(($currentRow / $totalRows) * 100);
                                    updateProgress('b', $percent);
                                }
                            }
                            $stmtB->close();
                            $messages[] = "Sheet B (central_indicators): $countB رکورد.";
                            $overallInsertCount += $countB;
                        }
                    }

                    /////////////////////////////////
                    // Sheet C: doctor_performance
                    $sheetC = $spreadsheet->getSheetByName('c');
                    if ($sheetC) {
                        $dataC = $sheetC->toArray(null, true, false, false);
                        if (count($dataC) > 0) {
                            if (count($dataC[0]) > 1) {
                                array_shift($dataC);
                            }
                            $totalRows = count($dataC);
                            $queryC = "INSERT INTO doctor_performance 
                                (hospital_code, hospital, date, doctor_code, doctor_name, maght_code, maght, specialty_code, specialty, 
                                 visit_sakhte, visit_tamin, visit_salamat, visit_azad, total_visits, gross_income, other_visits, 
                                 endoscopy, colonoscopy, bronchoscopy, ecocardio, inpatient, surgery, anesthesia, surgery_K, anesthesia_K)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                    doctor_name = VALUES(doctor_name),
                                    date = VALUES(date),
                                    maght_code = VALUES(maght_code),
                                    maght = VALUES(maght),
                                    specialty_code = VALUES(specialty_code),
                                    specialty = VALUES(specialty),
                                    visit_sakhte = VALUES(visit_sakhte),
                                    visit_tamin = VALUES(visit_tamin),
                                    visit_salamat = VALUES(visit_salamat),
                                    visit_azad = VALUES(visit_azad),
                                    total_visits = VALUES(total_visits),
                                    gross_income = VALUES(gross_income),
                                    other_visits = VALUES(other_visits),
                                    endoscopy = VALUES(endoscopy),
                                    colonoscopy = VALUES(colonoscopy),
                                    bronchoscopy = VALUES(bronchoscopy),
                                    ecocardio = VALUES(ecocardio),
                                    inpatient = VALUES(inpatient),
                                    surgery = VALUES(surgery),
                                    anesthesia = VALUES(anesthesia),
                                    surgery_K = VALUES(surgery_K),
                                    anesthesia_K = VALUES(anesthesia_K)";
                            $stmtC = $conn->prepare($queryC);
                            $countC = 0;
                            $currentRow = 0;
                            foreach ($dataC as $row) {
                                $row = array_pad($row, 25, '');
                                $hospital_code = trim($row[0]);
                                $hospital = trim($row[1]);
                                $date = trim($row[2]);
                                if ($date === '') {
                                    continue;
                                }
                                $doctor_code = trim($row[3]);
                                $doctor_name = trim($row[4]);
                                $maght_code = trim($row[5]);
                                $maght = trim($row[6]);
                                $specialty_code = trim($row[7]);
                                $specialty = trim($row[8]);
                                $visit_sakhte = (trim($row[9]) !== '') ? intval(str_replace([',', ' '], '', trim($row[9]))) : 0;
                                $visit_tamin = (trim($row[10]) !== '') ? intval(str_replace([',', ' '], '', trim($row[10]))) : 0;
                                $visit_salamat = (trim($row[11]) !== '') ? intval(str_replace([',', ' '], '', trim($row[11]))) : 0;
                                $visit_azad = (trim($row[12]) !== '') ? intval(str_replace([',', ' '], '', trim($row[12]))) : 0;
                                $total_visits = (trim($row[13]) !== '') ? intval(str_replace([',', ' '], '', trim($row[13]))) : 0;
                                $gross_income = (trim($row[14]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[14]))) : 0.0;
                                $other_visits = (trim($row[15]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[15]))) : 0.0;
                                $endoscopy = (trim($row[16]) !== '') ? intval(str_replace([',', ' '], '', trim($row[16]))) : 0;
                                $colonoscopy = (trim($row[17]) !== '') ? intval(str_replace([',', ' '], '', trim($row[17]))) : 0;
                                $bronchoscopy = (trim($row[18]) !== '') ? intval(str_replace([',', ' '], '', trim($row[18]))) : 0;
                                $ecocardio = (trim($row[19]) !== '') ? intval(str_replace([',', ' '], '', trim($row[19]))) : 0;
                                $inpatient = (trim($row[20]) !== '') ? intval(str_replace([',', ' '], '', trim($row[20]))) : 0;
                                $surgery = (trim($row[21]) !== '') ? intval(str_replace([',', ' '], '', trim($row[21]))) : 0;
                                $anesthesia = (trim($row[22]) !== '') ? intval(str_replace([',', ' '], '', trim($row[22]))) : 0;
                                $surgery_K = (trim($row[23]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[23]))) : 0.0;
                                $anesthesia_K = (trim($row[24]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[24]))) : 0.0;

                                $stmtC->bind_param(
                                    "sssssssssiiiiidiiiiiiiidd",
                                    $hospital_code,
                                    $hospital,
                                    $date,
                                    $doctor_code,
                                    $doctor_name,
                                    $maght_code,
                                    $maght,
                                    $specialty_code,
                                    $specialty,
                                    $visit_sakhte,
                                    $visit_tamin,
                                    $visit_salamat,
                                    $visit_azad,
                                    $total_visits,
                                    $gross_income,
                                    $other_visits,
                                    $endoscopy,
                                    $colonoscopy,
                                    $bronchoscopy,
                                    $ecocardio,
                                    $inpatient,
                                    $surgery,
                                    $anesthesia,
                                    $surgery_K,
                                    $anesthesia_K
                                );
                                if (!$stmtC->execute()) {
                                    throw new Exception("خطا در درج داده در sheet c: " . $stmtC->error);
                                }
                                $countC++;
                                $currentRow++;
                                if ($currentRow % 10 == 0 || $currentRow == $totalRows) {
                                    $percent = intval(($currentRow / $totalRows) * 100);
                                    updateProgress('c', $percent);
                                }
                            }
                            $stmtC->close();
                            $messages[] = "Sheet C (doctor_performance): $countC رکورد.";
                            $overallInsertCount += $countC;
                        }
                    }

                    /////////////////////////////////
                    // Sheet D: fund_performance
                    $sheetD = $spreadsheet->getSheetByName('d');
                    if ($sheetD) {
                        $dataD = $sheetD->toArray(null, true, false, false);
                        if (count($dataD) > 0) {
                            if (count($dataD[0]) > 1) {
                                array_shift($dataD);
                            }
                            $totalRows = count($dataD);
                            $queryD = "INSERT INTO fund_performance 
                                (hospital_code, hospital, fund_code, fund, date, received, returned, balance)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                    hospital_code = VALUES(hospital_code),
                                    hospital = VALUES(hospital),
                                    fund = VALUES(fund),
                                    date = VALUES(date),
                                    received = VALUES(received),
                                    returned = VALUES(returned),
                                    balance = VALUES(balance)";
                            $stmtD = $conn->prepare($queryD);
                            $countD = 0;
                            $currentRow = 0;
                            foreach ($dataD as $row) {
                                $row = array_pad($row, 8, '');
                                $hospital_code = trim($row[0]);
                                $hospital = trim($row[1]);
                                $fund_code = trim($row[2]);
                                $fund = trim($row[3]);
                                $date = trim($row[4]);
                                if ($date === '') {
                                    continue;
                                }
                                $received = (trim($row[5]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[5]))) : 0.0;
                                $returned = (trim($row[6]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[6]))) : 0.0;
                                $balance = (trim($row[7]) !== '') ? floatval(str_replace([',', ' '], '', trim($row[7]))) : 0.0;
                                $stmtD->bind_param("sssssddd", $hospital_code, $hospital, $fund_code, $fund, $date, $received, $returned, $balance);
                                if (!$stmtD->execute()) {
                                    throw new Exception("خطا در درج داده در sheet d: " . $stmtD->error);
                                }
                                $countD++;
                                $currentRow++;
                                if ($currentRow % 5 == 0 || $currentRow == $totalRows) {
                                    $percent = intval(($currentRow / $totalRows) * 100);
                                    updateProgress('d', $percent);
                                }
                            }
                            $stmtD->close();
                            $messages[] = "Sheet D (fund_performance): $countD رکورد.";
                            $overallInsertCount += $countD;
                        }
                    }

                    /////////////////////////////////
                    // Sheet E: paraclinic_stats
                    $sheetE = $spreadsheet->getSheetByName('e');
                    if ($sheetE) {
                        $dataE = $sheetE->toArray(null, true, false, false);
                        if (count($dataE) > 0) {
                            if (count($dataE[0]) > 1) {
                                array_shift($dataE);
                            }
                            $totalRows = count($dataE);
                            $queryE = "INSERT INTO paraclinic_stats 
                                        (hospital_code, hospital, paraclinic_code, paraclinic, year, month, count_moraje)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE count_moraje = VALUES(count_moraje)";
                            $stmtE = $conn->prepare($queryE);
                            $countE = 0;
                            $currentRow = 0;
                            foreach ($dataE as $row) {
                                $row = array_pad($row, 17, '');
                                $hospital_code   = trim($row[0]);
                                $hospital        = trim($row[1]);
                                $paraclinic_code = trim($row[2]);
                                $paraclinic      = trim($row[3]);
                                $year            = trim($row[4]);
                                if ($year === '') {
                                    continue;
                                }
                                for ($m = 5; $m < 17; $m++) {
                                    $monthNumber = $m - 4;
                                    $monthStr = str_pad($monthNumber, 2, '0', STR_PAD_LEFT);
                                    $countValue = (isset($row[$m]) && trim($row[$m]) !== '')
                                        ? intval(str_replace([',', ' '], '', trim($row[$m])))
                                        : 0;
                                    $stmtE->bind_param("ssssssi", $hospital_code, $hospital, $paraclinic_code, $paraclinic, $year, $monthStr, $countValue);
                                    if (!$stmtE->execute()) {
                                        throw new Exception("خطا در درج داده در sheet e برای ماه $monthStr: " . $stmtE->error);
                                    }
                                    $countE++;
                                }
                                $currentRow++;
                                if ($currentRow % 2 == 0 || $currentRow == $totalRows) {
                                    $percent = intval(($currentRow / $totalRows) * 100);
                                    updateProgress('e', $percent);
                                }
                            }
                            $stmtE->close();
                            $messages[] = "Sheet E (paraclinic_stats): $countE رکورد.";
                            $overallInsertCount += $countE;
                        }
                    }

                    /////////////////////////////////
                    // Sheet F: hospitalization_stats
                    $sheetF = $spreadsheet->getSheetByName('f');
                    if ($sheetF) {
                        $dataF = $sheetF->toArray(null, true, false, false);
                        if (count($dataF) > 0) {
                            if (count($dataF[0]) > 1) {
                                array_shift($dataF);
                            }
                            $totalRows = count($dataF);
                            $queryF = "INSERT INTO hospitalization_stats 
                                        (hospital_code, hospital, section_code, section_name, year, month, count_moraje)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE count_moraje = VALUES(count_moraje)";
                            $stmtF = $conn->prepare($queryF);
                            $countF = 0;
                            $currentRow = 0;
                            foreach ($dataF as $row) {
                                $row = array_pad($row, 17, '');
                                $hospital_code = trim($row[0]);
                                $hospital      = trim($row[1]);
                                $section_code  = trim($row[2]);
                                $section_name  = trim($row[3]);
                                $year          = trim($row[4]);
                                if ($year === '') {
                                    continue;
                                }
                                for ($m = 5; $m < 17; $m++) {
                                    $monthNumber = $m - 4;
                                    $monthStr = str_pad($monthNumber, 2, '0', STR_PAD_LEFT);
                                    $countValue = (isset($row[$m]) && trim($row[$m]) !== '')
                                        ? intval(str_replace([',', ' '], '', trim($row[$m])))
                                        : 0;
                                    $stmtF->bind_param("ssssssi", $hospital_code, $hospital, $section_code, $section_name, $year, $monthStr, $countValue);
                                    if (!$stmtF->execute()) {
                                        throw new Exception("خطا در درج داده در sheet f برای ماه $monthStr: " . $stmtF->error);
                                    }
                                    $countF++;
                                }
                                $currentRow++;
                                if ($currentRow % 2 == 0 || $currentRow == $totalRows) {
                                    $percent = intval(($currentRow / $totalRows) * 100);
                                    updateProgress('f', $percent);
                                }
                            }
                            $stmtF->close();
                            $messages[] = "Sheet F (hospitalization_stats): $countF رکورد.";
                            $overallInsertCount += $countF;
                        }
                    }

                    /////////////////////////////////
                    // Sheet G: serv_paraclinic_stats
                    $sheetG = $spreadsheet->getSheetByName('g');
                    if ($sheetG) {
                        $dataG = $sheetG->toArray(null, true, false, false);
                        if (count($dataG) > 0) {
                            if (count($dataG[0]) > 1) {
                                array_shift($dataG);
                            }
                            $totalRows = count($dataG);
                            $queryG = "INSERT INTO serv_paraclinic_stats 
                                        (hospital_code, hospital, paraclinic_code, paraclinic, service_code, service_name, year, month, count_moraje)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE count_moraje = VALUES(count_moraje)";
                            $stmtG = $conn->prepare($queryG);
                            $countG = 0;
                            $currentRow = 0;
                            foreach ($dataG as $row) {
                                $row = array_pad($row, 19, '');
                                $hospital_code   = trim($row[0]);
                                $hospital        = trim($row[1]);
                                $paraclinic_code = trim($row[2]);
                                $paraclinic      = trim($row[3]);
                                $service_code    = trim($row[4]);
                                $service_name    = trim($row[5]);
                                $year            = trim($row[6]);
                                if ($year === '') {
                                    continue;
                                }
                                for ($m = 7; $m < 19; $m++) {
                                    $monthNumber = $m - 6;
                                    $monthStr = str_pad($monthNumber, 2, '0', STR_PAD_LEFT);
                                    $countValue = (isset($row[$m]) && trim($row[$m]) !== '')
                                        ? intval(str_replace([',', ' '], '', trim($row[$m])))
                                        : 0;
                                    $stmtG->bind_param("ssssssssi", $hospital_code, $hospital, $paraclinic_code, $paraclinic, $service_code, $service_name, $year, $monthStr, $countValue);
                                    if (!$stmtG->execute()) {
                                        throw new Exception("خطا در درج داده در sheet g برای ماه $monthStr: " . $stmtG->error);
                                    }
                                    $countG++;
                                }
                                $currentRow++;
                                if ($currentRow % 2 == 0 || $currentRow == $totalRows) {
                                    $percent = intval(($currentRow / $totalRows) * 100);
                                    updateProgress('g', $percent);
                                }
                            }
                            $stmtG->close();
                            $messages[] = "Sheet G (serv_paraclinic_stats): $countG رکورد.";
                            $overallInsertCount += $countG;
                        }
                    }

                    /////////////////////////////////
                    // Sheet H: procedure_stats
                    $sheetH = $spreadsheet->getSheetByName('h');
                    if ($sheetH) {
                        $dataH = $sheetH->toArray(null, true, false, false);
                        if (count($dataH) > 0) {
                            if (count($dataH[0]) > 1) {
                                array_shift($dataH);
                            }
                            $totalRows = count($dataH);
                            $queryH = "INSERT INTO procedure_stats 
                                        (hospital_code, hospital, procedure_code, procedure_name, year, month, count_moraje)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE count_moraje = VALUES(count_moraje)";
                            $stmtH = $conn->prepare($queryH);
                            $countH = 0;
                            $currentRow = 0;
                            foreach ($dataH as $row) {
                                $row = array_pad($row, 17, '');
                                $hospital_code  = trim($row[0]);
                                $hospital       = trim($row[1]);
                                $procedure_code = trim($row[2]);
                                $procedure_name = trim($row[3]);
                                $year           = trim($row[4]);
                                if ($year === '') {
                                    continue;
                                }
                                for ($m = 5; $m < 17; $m++) {
                                    $monthNumber = $m - 4;
                                    $monthStr = str_pad($monthNumber, 2, '0', STR_PAD_LEFT);
                                    $countValue = (isset($row[$m]) && trim($row[$m]) !== '')
                                        ? intval(str_replace([',', ' '], '', trim($row[$m])))
                                        : 0;
                                    $stmtH->bind_param("ssssssi", $hospital_code, $hospital, $procedure_code, $procedure_name, $year, $monthStr, $countValue);
                                    if (!$stmtH->execute()) {
                                        throw new Exception("خطا در درج داده در sheet h برای ماه $monthStr: " . $stmtH->error);
                                    }
                                    $countH++;
                                }
                                $currentRow++;
                                if ($currentRow % 2 == 0 || $currentRow == $totalRows) {
                                    $percent = intval(($currentRow / $totalRows) * 100);
                                    updateProgress('h', $percent);
                                }
                            }
                            $stmtH->close();
                            $messages[] = "Sheet H (procedure_stats): $countH رکورد.";
                            $overallInsertCount += $countH;
                        }
                    }

                    $conn->close();
                    $performanceMessage = " اطلاعات با موفقيت ثبت شد.      کل رکوردهای ثبت شده: $overallInsertCount  ";
                } catch (Exception $e) {
                    $performanceMessage = "خطا: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>داشبورد متصدی ورود اطلاعات</title>
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
                $header = new Header("بارگذاری فایل اکسل جامع", $_SESSION["username"]);
                echo $header->render();
                ?>
                <div class="main-content">
                    <div class="card shadow border-0 mb-4">
                        <div class="card-header text-white" style="background: linear-gradient(90deg, #343a40 0%, #23272b 100%);">
                            <h4 class="mb-0">بارگذاری اطلاعات</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="" enctype="multipart/form-data" class="row g-3">
                                <div class="col-md-6">
                                    <label for="excel_file" class="form-label">انتخاب فایل اکسل</label>
                                    <input type="file" name="excel_file" id="excel_file" class="form-control" required accept=".xls,.xlsx">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="submit" name="upload_all" class="btn btn-success btn-lg w-100">
                                        بارگذاری و ذخیره اطلاعات
                                    </button>
                                </div>
                            </form>
                            <?php
                            if (!empty($performanceMessage)) {
                                echo "<div class='mt-3 alert alert-info text-center'>$performanceMessage</div>";
                            }
                            ?>

                            <!-- نوارهای پیشرفت -->
                            <div class="progress-container mt-4">
                                <h5> حذف رکوردهای قدیمی</h5>
                                <div class="progress mb-3">
                                    <div id="progress-delete" class="progress-bar bg-danger" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-delete-label">0%</span>
                                    </div>
                                </div>
                                <h5> بارگذاری اطلاعات حقوق پزشکان</h5>
                                <div class="progress mb-3">
                                    <div id="progress-a" class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-a-label">0%</span>
                                    </div>
                                </div>
                                <h5> بارگذاری اطلاعات شاخص های مرکزی</h5>
                                <div class="progress mb-3">
                                    <div id="progress-b" class="progress-bar bg-info" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-b-label">0%</span>
                                    </div>
                                </div>
                                <h5> بارگذاری اطلاعات عملکرد پزشکان</h5>
                                <div class="progress mb-3">
                                    <div id="progress-c" class="progress-bar bg-warning" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-c-label">0%</span>
                                    </div>
                                </div>
                                <h5> بارگذاری اطلاعات کارکرد صندوق </h5>
                                <div class="progress mb-3">
                                    <div id="progress-d" class="progress-bar bg-danger" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-d-label">0%</span>
                                    </div>
                                </div>
                                <h5> بارگذاری اطلاعات آمار پذیرش پاراکلینیک ها</h5>
                                <div class="progress mb-3">
                                    <div id="progress-e" class="progress-bar bg-primary" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-e-label">0%</span>
                                    </div>
                                </div>
                                <h5> بارگذاری اطلاعات آمار پذیرش بیماران بستری</h5>
                                <div class="progress mb-3">
                                    <div id="progress-f" class="progress-bar bg-secondary" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-f-label">0%</span>
                                    </div>
                                </div>
                                <h5> بارگذاری اطلاعات تعداد سرویس های پاراکلینیک ها</h5>
                                <div class="progress mb-3">
                                    <div id="progress-g" class="progress-bar bg-dark" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-g-label">0%</span>
                                    </div>
                                </div>
                                <h5> بارگذاری اطلاعات تعداد هر عمل در ماه</h5>
                                <div class="progress mb-3">
                                    <div id="progress-h" class="progress-bar bg-info" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100">
                                        <span id="progress-h-label">0%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- اسکریپت به‌روز‌رسانی نوارهای پیشرفت -->
                            <script>
                                function fetchProgress() {
                                    fetch('progress.php')
                                        .then(response => response.json())
                                        .then(data => {
                                            const keys = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'delete'];
                                            keys.forEach(function(key) {
                                                const percent = data[key] || 0;
                                                const progressBar = document.getElementById('progress-' + key);
                                                const progressLabel = document.getElementById('progress-' + key + '-label');
                                                if (progressBar && progressLabel) {
                                                    progressBar.style.width = percent + '%';
                                                    progressLabel.textContent = percent + '%';
                                                }
                                            });
                                        })
                                        .catch(error => console.error('خطا در دریافت پیشرفت:', error));
                                }

                                // به‌روزرسانی هر ۱ ثانیه
                                setInterval(fetchProgress, 1000);
                            </script>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/bootstrap.bundle.min.js" defer></script>
</body>

</html>