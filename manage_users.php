<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


// بررسی ورود کاربر
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// بررسی اینکه فقط کاربر متصدی به این صفحه دسترسی داشته باشد
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "متصدی") {
    header("Location: index.php");
    exit;
}


// تنظیمات اتصال به دیتابیس
$servername  = "localhost";
$db_username = "root";
$db_password = "mysql"; // در صورت نیاز مقدار صحیح پسورد را وارد کنید
$dbname      = "dashboard_db";

// ایجاد اتصال به دیتابیس
$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("خطا در اتصال به دیتابیس: " . $conn->connect_error);
}

$message = "";

// تولید CSRF Token در صورت عدم وجود
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // اعتبارسنجی CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("درخواست نامعتبر");
    }
    
    // حذف کاربر
    if (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = "کاربر با موفقیت حذف شد.";
        } else {
            $message = "خطا در حذف کاربر: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // به‌روزرسانی اطلاعات کاربر
    if (isset($_POST['action']) && $_POST['action'] == 'update_user') {
        $user_id           = intval($_POST['user_id']);
        $personnel_number  = trim($_POST['personnel_number']);
        $grade             = trim($_POST['grade']);
        $first_name        = trim($_POST['first_name']);
        $last_name         = trim($_POST['last_name']);
        $username_field    = trim($_POST['username']);
        $user_type         = trim($_POST['user_type']);
        
        $stmt = $conn->prepare("UPDATE users SET personnel_number = ?, grade = ?, first_name = ?, last_name = ?, username = ?, user_type = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $personnel_number, $grade, $first_name, $last_name, $username_field, $user_type, $user_id);
        if ($stmt->execute()) {
            $message = "اطلاعات کاربر به‌روزرسانی شد.";
        } else {
            $message = "خطا در به‌روزرسانی: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // تغییر رمز عبور کاربر
    if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        $user_id          = intval($_POST['user_id']);
        $password         = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        if ($password === "" || $password !== $confirm_password) {
            $message = "رمز عبور و تکرار آن یکسان نیستند یا خالی هستند.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            if ($stmt->execute()) {
                $message = "رمز عبور تغییر یافت.";
            } else {
                $message = "خطا در تغییر رمز: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    // افزودن کاربر جدید
    if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
        $personnel_number = trim($_POST['personnel_number']);
        $grade            = trim($_POST['grade']);
        $first_name       = trim($_POST['first_name']);
        $last_name        = trim($_POST['last_name']);
        $username_field   = trim($_POST['username']);
        $password_field   = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $user_type        = trim($_POST['user_type']);
        
        if (
            $personnel_number == "" || $grade == "" || $first_name == "" ||
            $last_name == "" || $username_field == "" || $password_field == "" ||
            $confirm_password == "" || $user_type == ""
        ) {
            $message = "لطفاً تمامی فیلدها را پر کنید.";
        } elseif ($password_field !== $confirm_password) {
            $message = "رمز عبور و تکرار آن یکسان نیستند.";
        } else {
            // بررسی وجود نام کاربری از قبل
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username_field);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $message = "کاربر با این نام کاربری قبلاً ثبت شده است.";
            } else {
                $stmt->close();
                $hashed_password = password_hash($password_field, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (personnel_number, grade, first_name, last_name, username, password, user_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $personnel_number, $grade, $first_name, $last_name, $username_field, $hashed_password, $user_type);
                if ($stmt->execute()) {
                    $message = "کاربر با موفقیت افزوده شد.";
                } else {
                    $message = "خطا در افزودن کاربر: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
}

// دریافت تمامی کاربران
$users = [];
$result = $conn->query("SELECT * FROM users ORDER BY id ASC");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مدیریت کاربران - سامانه داشبورد</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- بارگذاری نسخه RTL بوت‌استرپ -->
    <link rel="stylesheet" href="css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="css/bootstrap-icons.css">

    <style>
            @font-face {
      font-family: Sahel;
      src: url("fonts/Sahel.woff2") format("woff2"),
        url("fonts/Sahel.woff") format("woff");
    }
        body {
            background: linear-gradient(90deg, rgb(171, 180, 189) 0%, rgb(141, 149, 157) 100%);
            min-height: 100vh;
            font-family: Sahel;
        }
        .container {
            margin-top: 2rem;
        }
        .table-container {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .action-btn {
            margin-right: 5px;
        }
        .card-header, .modal-header {
            background-color: #5578F7;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="container">
    <!-- سربرگ شامل عنوان و دکمه‌های "افزودن کاربر" و "خروج" -->
    <div class="d-flex justify-content-between mb-4">
        <h2>مدیریت کاربران</h2>
        <div>
            <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#addUserModal">افزودن کاربر</button>
            <a href="main_add.php" class="btn btn-danger">خروج</a>
        </div>
    </div>
    
    <?php if ($message != ""): ?>
        <div class="alert alert-info text-center"><?= $message ?></div>
    <?php endif; ?>
    
    <div class="table-container">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>شماره پرسنلی</th>
                    <th>درجه</th>
                    <th>نام</th>
                    <th>نام خانوادگی</th>
                    <th>نام کاربری</th>
                    <th>نوع کاربر</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['personnel_number']) ?></td>
                        <td><?= htmlspecialchars($user['grade']) ?></td>
                        <td><?= htmlspecialchars($user['first_name']) ?></td>
                        <td><?= htmlspecialchars($user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['user_type']) ?></td>
                        <td>
                            <!-- دکمه ویرایش -->
                            <button class="btn btn-sm btn-primary action-btn" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editUserModal"
                                data-id="<?= $user['id'] ?>"
                                data-personnel="<?= htmlspecialchars($user['personnel_number']) ?>"
                                data-grade="<?= htmlspecialchars($user['grade']) ?>"
                                data-firstname="<?= htmlspecialchars($user['first_name']) ?>"
                                data-lastname="<?= htmlspecialchars($user['last_name']) ?>"
                                data-username="<?= htmlspecialchars($user['username']) ?>"
                                data-usertype="<?= htmlspecialchars($user['user_type']) ?>"
                            >ویرایش</button>
                            
                            <!-- دکمه تغییر رمز -->
                            <button class="btn btn-sm btn-warning action-btn" 
                                data-bs-toggle="modal"
                                data-bs-target="#changePasswordModal"
                                data-id="<?= $user['id'] ?>"
                            >تغییر رمز</button>
                            
                            <!-- دکمه حذف -->
                            <button class="btn btn-sm btn-danger" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteUserModal"
                                data-id="<?= $user['id'] ?>"
                                data-username="<?= htmlspecialchars($user['username']) ?>"
                            >حذف</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="text-center">هیچ کاربری وجود ندارد</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- مودال افزودن کاربر (طرح دو ستونه) -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post">
         <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
         <input type="hidden" name="action" value="add_user">
         <div class="modal-content">
           <div class="modal-header">
              <h5 class="modal-title" id="addUserModalLabel">فزودن کاربر جدید داشبورد مدیریتی</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
           </div>
           <div class="modal-body">
                <div class="row g-3">
                    <!-- ستون سمت چپ: اطلاعات شخصی -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="add_personnel_number" class="form-label">شماره پرسنلی</label>
                            <input type="text" class="form-control" id="add_personnel_number" name="personnel_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_grade" class="form-label">درجه</label>
                            <input type="text" class="form-control" id="add_grade" name="grade" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_first_name" class="form-label">نام</label>
                            <input type="text" class="form-control" id="add_first_name" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_last_name" class="form-label">نام خانوادگی</label>
                            <input type="text" class="form-control" id="add_last_name" name="last_name" required>
                        </div>
                    </div>
                    <!-- ستون سمت راست: اطلاعات حساب کاربری -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="add_username" class="form-label">نام کاربری</label>
                            <input type="text" class="form-control" id="add_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_password" class="form-label">رمز عبور</label>
                            <input type="password" class="form-control" id="add_password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_confirm_password" class="form-label">تکرار رمز عبور</label>
                            <input type="password" class="form-control" id="add_confirm_password" name="confirm_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_user_type" class="form-label">نوع کاربر</label>
                            <select class="form-select" id="add_user_type" name="user_type" required>
                                <option value="">انتخاب کنید</option>
                                <option value="مدیر">مدیر</option>
                                <option value="متصدی">متصدی</option>
                                <option value="کاربر اموربیمارستان">کاربر اموربیمارستان</option>
                                <option value="کاربر پیراپزشکی">کاربر پیراپزشکی</option>
                                <option value="کاربر آماد">کاربر آماد</option>
                                <option value="کاربر کنت">کاربر کنت</option>
                            </select>
                        </div>
                    </div>
                </div>
           </div>
           <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
              <button type="submit" class="btn btn-success">ثبت کاربر</button>
           </div>
         </div>
    </form>
  </div>
</div>

<!-- مودال ویرایش کاربر -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" id="edit_user_id">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">ویرایش اطلاعات کاربر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- ستون سمت چپ: اطلاعات شخصی -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_personnel_number" class="form-label">شماره پرسنلی</label>
                            <input type="text" class="form-control" id="edit_personnel_number" name="personnel_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_grade" class="form-label">درجه</label>
                            <input type="text" class="form-control" id="edit_grade" name="grade" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_first_name" class="form-label">نام</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_last_name" class="form-label">نام خانوادگی</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                    </div>
                    <!-- ستون سمت راست: اطلاعات حساب کاربری -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">نام کاربری</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_user_type" class="form-label">نوع کاربر</label>
                            <select class="form-select" id="edit_user_type" name="user_type" required>
                                <option value="">انتخاب کنید</option>
                                <option value="مدیر">مدیر</option>
                                <option value="متصدی">متصدی</option>
                                <option value="کاربر اموربیمارستان">کاربر اموربیمارستان</option>
                                <option value="کاربر پیراپزشکی">کاربر پیراپزشکی</option>
                                <option value="کاربر آماد">کاربر آماد</option>
                                <option value="کاربر کنت">کاربر کنت</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
            </div>
        </div>
    </form>
  </div>
</div>

<!-- مودال تغییر رمز عبور -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
         <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
         <input type="hidden" name="action" value="change_password">
         <input type="hidden" name="user_id" id="change_password_user_id">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">تغییر رمز عبور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="new_password" class="form-label">رمز عبور جدید</label>
                    <input type="password" class="form-control" id="new_password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_new_password" class="form-label">تکرار رمز عبور</label>
                    <input type="password" class="form-control" id="confirm_new_password" name="confirm_password" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="submit" class="btn btn-warning">تغییر رمز</button>
            </div>
        </div>
    </form>
  </div>
</div>

<!-- مودال حذف کاربر -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post">
         <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
         <input type="hidden" name="action" value="delete_user">
         <input type="hidden" name="user_id" id="delete_user_id">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">تایید حذف کاربر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                آیا از حذف کاربر <strong id="delete_username"></strong> مطمئن هستید؟
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="submit" class="btn btn-danger">حذف کاربر</button>
            </div>
        </div>
    </form>
  </div>
</div>

<!-- انتقال داده به مودال‌ها با جاوا اسکریپت -->
<script src="js/bootstrap.bundle.min.js"></script>
<script>
    // ارسال داده به مودال ویرایش کاربر
    var editUserModal = document.getElementById('editUserModal');
    editUserModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_user_id').value = button.getAttribute('data-id');
        document.getElementById('edit_personnel_number').value = button.getAttribute('data-personnel');
        document.getElementById('edit_grade').value = button.getAttribute('data-grade');
        document.getElementById('edit_first_name').value = button.getAttribute('data-firstname');
        document.getElementById('edit_last_name').value = button.getAttribute('data-lastname');
        document.getElementById('edit_username').value = button.getAttribute('data-username');
        document.getElementById('edit_user_type').value = button.getAttribute('data-usertype');
    });
    
    // ارسال شناسه به مودال تغییر رمز
    var changePasswordModal = document.getElementById('changePasswordModal');
    changePasswordModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('change_password_user_id').value = button.getAttribute('data-id');
    });
    
    // ارسال داده به مودال حذف کاربر
    var deleteUserModal = document.getElementById('deleteUserModal');
    deleteUserModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('delete_user_id').value = button.getAttribute('data-id');
        document.getElementById('delete_username').textContent = button.getAttribute('data-username');
    });
</script>
</body>
</html>
