<?php
class TopMenu {
    // آرایه موارد منو شامل عنوان و لینک هر گزینه
    private $menuItems = [];

    public function __construct() {
        // تعریف موارد منو
        $this->menuItems = [
            [
                'label' => 'عملکرد یکساله پزشک',
                'link'  => 'oneYearPerformance.php' // مسیر صفحه مربوط
            ],
            [
                'label' => 'مقایسه پرشکان در هر بیمارستان',
                'link'  => 'hospitalComparison.php'
            ],
            [
                'label' => 'مقایسه پزشکان در هر تخصص',
                'link'  => 'specialtyComparison.php'
            ]
        ];
    }

    // متد render که CSS و HTML مربوط به منو را تولید می‌کند
    public function render() {
        // درج CSS داخلی جهت استایل دهی منوی بالا
        $css = '<style>
            .top-menu {
                margin: 20px 0;
            }
            .top-menu ul {
                list-style: none;
                padding: 0;
                margin: 0;
                display: flex;
                gap: 20px;
            }
            .top-menu ul li {
                margin: 0;
            }
            .top-menu ul li a {
                text-decoration: none;
                padding: 8px 12px;
                background: #007bff;
                color: #fff;
                border-radius: 4px;
                transition: background 0.3s ease;
            }
            .top-menu ul li a:hover,
            .top-menu ul li a.active {
                background: #0056b3;
            }
        </style>';
        
        // تولید HTML منو
        $html = '<div class="top-menu">';
        $html .= '<ul>';
        foreach ($this->menuItems as $item) {
            $html .= '<li><a href="' . $item['link'] . '">' . $item['label'] . '</a></li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        
        return $css . $html;
    }
}
?>
