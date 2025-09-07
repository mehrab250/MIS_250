<?php
header('Content-Type: application/json');

$progressFile = 'progress.json';

if (file_exists($progressFile)) {
    // اگر فایل وجود داشت، محتویات آن را برگردانیم
    echo file_get_contents($progressFile);
} else {
    // در صورت عدم وجود فایل، وضعیت پیش‌فرض صفر درصد برای همه شیت‌ها نمایش داده شود
    echo json_encode([
        'a' => 0,
        'b' => 0,
        'c' => 0,
        'd' => 0,
        'e' => 0,
        'f' => 0,
        'g' => 0,
        'h' => 0
    ]);
}
?>
