 <?php
 
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

class Header {
    private $title;
    private $username;
    
    public function __construct($title = "داشبورد", $username = "کاربر") {
        $this->title = $title;
        $this->username = $username;
    }
    
    public function render() {
        // استایل‌های هدر با افکت‌های مدرن و انتقال‌های نرم
        $css = '
        <style>
             @font-face {
            font-family: Sahel;
            src: url("fonts/Sahel.woff2") format("woff2"),
            url("fonts/Sahel.woff") format("woff");
            }


            .header-bar {
                background: linear-gradient(90deg, #343a40 0%, #23272b 100%);
                height: 60px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.15);
                font-family: "Sahel";
            }
            .header-bar .header-title {
                color: #fff;
                font-size: 20px;
                font-weight: 700;
                transition: color 0.3s ease;
            }
            .header-bar .header-title:hover {
                color: #ffc107;
            }
            .header-bar .header-right {
                display: flex;
                align-items: center;
            }
            .header-bar .header-right .header-icon {
                color: #fff;
                margin-right: 20px;
                font-size: 18px;
                cursor: pointer;
                transition: color 0.3s ease;
            }
            .header-bar .header-right .header-icon:hover {
                color: #ffc107;
            }
            .header-bar .header-search {
                position: relative;
                margin-right: 20px;
            }
            .header-bar .header-search .input-group {
                display: flex;
                align-items: center;
            }
            .header-bar .header-search input {
                height: 36px;
                padding: 0 10px;
                border: none;
                border-radius: 4px 0 0 4px;
                outline: none;
                font-size: 14px;
            }
            .header-bar .header-search button {
                height: 36px;
                padding: 0 12px;
                border: none;
                background: #ffc107;
                color: #343a40;
                border-radius: 0 4px 4px 0;
                cursor: pointer;
                transition: background 0.3s ease;
            }
            .header-bar .header-search button:hover {
                background: #e0a800;
            }
        </style>
        ';

        // ساختار HTML هدر به همراه استایل‌های داخلی
        $headerHtml = $css . '
        <header class="header-bar">
            <div class="header-left">
                <span class="header-title">' . htmlspecialchars($this->title) . '</span>
            </div>
            <div class="header-right">
                <form class="header-search" action="#" method="GET">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="جستجو...">
                        <button type="submit"><i class="fa fa-search"></i></button>
                    </div>
                </form>
                <a href="#" class="header-icon"><i class="fa fa-bell"></i></a>
                <a href="#" class="header-icon"><i class="fa fa-envelope"></i></a>
                <a href="#" class="header-icon"><i class="fa fa-user"></i></a>
            </div>
        </header>';

        return $headerHtml;
    }
}
?>