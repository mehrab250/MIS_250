<?php
class Hospital {
    // خصوصیات به صورت private برای انکپسوله کردن داده‌ها
    private $code;
    private $name;
    private $category;

    // تابع سازنده برای مقداردهی اولیه
    public function __construct($code, $name, $category) {
        $this->code = $code;
        $this->name = $name;
        $this->category = $category;
    }

    // توابع getter برای دسترسی به خصوصیات
    public function getCode() {
        return $this->code;
    }

    public function getName() {
        return $this->name;
    }

    // از آنجا که در کد اصلی از متد getLevel() استفاده شده است،
    // اینجا متد getLevel() به عنوان alias برای getCategory() تعریف شده است.
    public function getLevel() {
        return $this->getCategory(); // یا به سادگی: return $this->category;
    }

    public function getCategory() {
        return $this->category;
    }

    // توابع setter جهت تغییر مقدار خصوصیات
    public function setCode($code) {
        $this->code = $code;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setCategory($category) {
        $this->category = $category;
    }

    // تابع تبدیل داده‌ها به آرایه برای استفاده‌های مختلف
    public function toArray() {
        return [
            'code'     => $this->code,
            'name'     => $this->name,
            'category' => $this->category
        ];
    }
}
?>
