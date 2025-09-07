<?php

class Menu_omor
{
    private $username;
    private $user_type;
    private $logoPath;

    public function __construct($username, $user_type, $logoPath = 'logo.png')
    {
        $this->username = htmlspecialchars($username);
        $this->user_type = htmlspecialchars($user_type);
        $this->logoPath = $logoPath;
    }

    public function render()
    {
        $style = '
        <style>
             @font-face {
            font-family: Sahel;
            src: url("fonts/Sahel.woff2") format("woff2"),
            url("fonts/Sahel.woff") format("woff");
            }
            .sidebar {
                background: linear-gradient(90deg, #343a40 0%, #23272b 100%);
                color: #fff;
                height: 100vh;
                top: 0;
                right: 0;
                padding: 1px;
                overflow-y: auto;
                box-shadow: -4px 0 10px rgba(0, 0, 0, 0.3);
                font-family: Sahel;
                font-size: 12px;
            }
            /* سفارشی‌سازی نوار اسکرول */
            .sidebar::-webkit-scrollbar {
                width: 8px;
            }
            .sidebar::-webkit-scrollbar-track {
                background: #212529;ّ
            }
            .sidebar::-webkit-scrollbar-thumb {
                background-color: #495057;
                border-radius: 4px;
            }
                        .sidebar a {
                color: #adb5bd;
                text-decoration: none;
                display: block;
                padding: 10px 15px;
                margin-bottom: 8px;
                border-radius: 6px;
                transition: background-color 0.3s ease, color 0.3s ease;
                position: relative; /* برای قرارگیری مطلق آیکن فلش */
            }
            .sidebar a:hover {
                background-color: #495057;
                color: #fff;
            }
            .user-info, .logo {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 1px solid rgba(255,255,255,0.2);
            }
            .logo img {
                max-height: 80px;
                border-radius: 50%;
                border: 2px solid #fff;
            }
            .nav.flex-column li {
                margin-bottom: 8px;
            }
            /* تنظیمات لینک‌های منو */
            .nav-link {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                position: relative;
            }
            /* آیکن باز/بسته فلش به صورت مطلق در سمت چپ */
            .toggle-arrow {
                position: absolute;
                left: 15px;
                transition: transform 0.3s ease;
            }
            /* فاصله دادن متن لینک از آیکن */
            .nav-link .link-text {
                margin-left: 35px;
            }
            .nav-link.collapsed .toggle-arrow {
                transform: rotate(0deg);
            }
            .nav-link:not(.collapsed) .toggle-arrow {
                transform: rotate(180deg);
            }
            .collapse ul {
                padding-left: 15px;
                margin-top: 8px;
            }
                    


        </style>
        ';

        $Menu_omorHtml = $style . '
        <div class="sidebar">
            <div class="logo">
                <img src="' . $this->logoPath . '" alt="Logo" class="img-fluid">
            </div>
            <div class="user-info">
                <h5>کاربر: ' . $this->username . '</h5>
                <p>دسترسی: ' . $this->user_type . '</p>
            </div>
            <nav>
                <ul class="nav flex-column">
                    <!-- منوی امور بیمارستان -->
                    <li class="nav-item">
                        <a class="nav-link fw-bold collapsed" data-bs-toggle="collapse" href="#hospitalAffairs" role="button" aria-expanded="false" aria-controls="hospitalAffairs">
                            <i class="fa fa-chevron-down toggle-arrow"></i>
                            <span class="link-text"><i class="fa fa-hospital-o me-2"></i> امور بیمارستان</span>
                        </a>
                        <div class="collapse" id="hospitalAffairs">
                            <ul class="nav flex-column">
                                <li class="nav-item"><a class="nav-link" href="hogog_pezeshk_m.php"><i class="fa fa-money me-2"></i> حقوق پزشکان</a></li>
                                <li class="nav-item"><a class="nav-link" href="shakhes_markazi_m.php"><i class="fa fa-bar-chart me-2"></i> شاخص هاي مرکز</a></li>
                                <li class="nav-item"><a class="nav-link" href="amalkard_pezeshk_m.php"><i class="fa fa-user-md me-2"></i> عملكرد پزشكان</a></li>
                                <li class="nav-item"><a class="nav-link" href="karkard_sandog_m.php"><i class="fa fa-cubes me-2"></i> كاركرد كل صندوق</a></li>
                            </ul>
                        </div>
                    </li>
                                       <!-- منوی بارگذاری اطلاعات بیمارستان -->
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="upload_hospital_info.php">
                            <i class="fa fa-upload me-2"></i> بارگذاری اطلاعات بیمارستان
                        </a>
                    </li>
                    <!-- منوی آخرین بروزرسانی بیمارستان -->
                    <li class="nav-item">
                        <a class="nav-link fw-bold" href="last_update_hospital.php">
                            <i class="fa fa-refresh me-2"></i> آخرین بروزرسانی هر بیمارستان
                        </a>
                    </li>
                </ul>
            </nav>
        </div>';
        return $Menu_omorHtml;
    }
}
