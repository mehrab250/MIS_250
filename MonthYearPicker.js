"use strict";

class MonthYearPicker {
  // بررسی تزریق CSS (به صورت ایستا)
  static cssInjected = false;

  /**
   * @param {string} triggerElementId - آی‌دی عنصری که با فوکوس یا کلیک روی آن تقویم نمایش داده می‌شود.
   * @param {object} options - تنظیمات دلخواه مانند initialYear، onSelectMonth و monthNames.
   */
  constructor(triggerElementId, options = {}) {
    // دستیابی به فیلد تریگر
    this.triggerElement = document.getElementById(triggerElementId);
    if (!this.triggerElement) {
      throw new Error(`عنصری با آی‌دی "${triggerElementId}" پیدا نشد.`);
    }

    // تنظیم نام ماه‌ها (پیش‌فرض: ماه‌های شمسی)
    this.monthNames = options.monthNames || [
      "فروردین", "اردیبهشت", "خرداد", "تیر",
      "مرداد", "شهریور", "مهر", "آبان",
      "آذر", "دی", "بهمن", "اسفند"
    ];

    // تنظیم سال اولیه (پیش‌فرض: 1404)
    this.year = options.initialYear || 1404;

    // callback انتخاب ماه؛ به صورت پیش‌فرض فیلد تریگر به "نام ماه سال" تغییر می‌کند.
    this.onSelectMonth = options.onSelectMonth || function(month, year) {
      let numericMonth = month < 10 ? `0${month}` : month;
      this.triggerElement.value = `${this.monthNames[month - 1]} ${year}`;
    }.bind(this);

    // تزریق CSS لازم (فقط یک بار)
    MonthYearPicker.injectCSS();

    // ایجاد المنت تقویم به صورت داینامیک
    this.createPopup();

    // ثبت رویدادهای مربوط به نمایش/پنهان تقویم
    this.bindTriggerEvents();
  }

  /**
   * ایجاد المنت popup جهت نمایش تقویم
   */
  createPopup() {
    this.popupElement = document.createElement("div");
    this.popupElement.id = "MonthYearPicker_popup";

    // تنظیم استایل برای نمایش در بالاترین لایه
    this.popupElement.style.display = "none";
    this.popupElement.style.position = "fixed"; // استفاده از position: fixed
    this.popupElement.style.zIndex = "9999";       // مقدار z-index بالا
    this.popupElement.style.backgroundColor = "#fff";
    this.popupElement.style.boxShadow = "0 4px 8px rgba(0, 0, 0, 0.2)";

    // اضافه کردن تقویم به بدنه صفحه به جای والد تریگر
    document.body.appendChild(this.popupElement);

    // ایجاد ساختار داخلی تقویم (هدر و شبکه ماه‌ها)
    this.initPicker();
  }

  /**
   * ایجاد ساختار داخلی تقویم شامل هدر (تغییر سال) و شبکه ماه‌ها
   */
  initPicker() {
    this.popupElement.innerHTML = `
      <div class="month-year-picker">
        <div class="month-year-picker-header">
          <button class="prev-year" title="سال قبل">&laquo;</button>
          <span class="year-display">${this.year}</span>
          <button class="next-year" title="سال بعد">&raquo;</button>
        </div>
        <div class="month-year-picker-grid"></div>
      </div>
    `;

    // گرفتن مرجع شبکه تقویم جهت رندر دکمه‌های ماه
    this.gridElement = this.popupElement.querySelector(".month-year-picker-grid");

    // افزودن رویداد برای تغییر سال به سمت عقب
    this.popupElement.querySelector(".prev-year").addEventListener("click", (e) => {
      e.preventDefault();
      this.year--;
      this.updateYearDisplay();
    });

    // افزودن رویداد برای تغییر سال به سمت جلو
    this.popupElement.querySelector(".next-year").addEventListener("click", (e) => {
      e.preventDefault();
      this.year++;
      this.updateYearDisplay();
    });

    // رندر شبکه دکمه‌های ماه
    this.renderGrid();
  }

  /**
   * به‌روز رسانی نمایش سال در هدر تقویم
   */
  updateYearDisplay() {
    const display = this.popupElement.querySelector(".year-display");
    display.innerText = this.year;
  }

  /**
   * ایجاد دکمه‌های مربوط به ماه‌ها در داخل شبکه تقویم
   */
  renderGrid() {
    this.gridElement.innerHTML = "";
    this.monthNames.forEach((monthName, index) => {
      const button = document.createElement("button");
      button.innerText = monthName;
      button.addEventListener("click", () => {
        this.onSelectMonth(index + 1, this.year);
        this.hide();
      });
      this.gridElement.appendChild(button);
    });
  }

  /**
   * ثبت رویدادهای لازم بر روی تریگر (نمایش تقویم) و تعامل با خارج از تقویم (پنهان‌سازی آن)
   */
  bindTriggerEvents() {
    // نمایش تقویم هنگام فوکوس یا کلیک روی تریگر
    this.triggerElement.addEventListener("focus", () => this.show());
    this.triggerElement.addEventListener("click", () => this.show());

    // پنهان کردن تقویم در صورت کلیک خارج از تریگر یا پنجره تقویم
    document.addEventListener("click", (e) => {
      if (!this.triggerElement.contains(e.target) && !this.popupElement.contains(e.target)) {
        this.hide();
      }
    });
  }

  /**
   * نمایش تقویم در موقعیت مناسب بر اساس تریگر با در نظر گرفتن محدوده صفحه
   */
  show() {
    this.popupElement.style.display = "block";

    const rect = this.triggerElement.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    
    // تعیین موقعیت به گونه‌ای که تقویم در محدوده صفحه قرار گیرد
    let topPosition = rect.bottom + 5;
    if (topPosition + this.popupElement.offsetHeight > viewportHeight) {
      topPosition = rect.top - this.popupElement.offsetHeight - 5;
    }

    this.popupElement.style.top = `${topPosition}px`;
    this.popupElement.style.left = `${rect.left}px`;
  }

  /**
   * پنهان کردن تقویم
   */
  hide() {
    this.popupElement.style.display = "none";
  }

  /**
   * تزریق CSS لازم برای تقویم (تنها یکبار اجرا می‌شود)
   */
  static injectCSS() {
    if (MonthYearPicker.cssInjected) return;
    const css = `
      .month-year-picker {
        display: inline-block;
        background: #fff !important;
        border: 1px solid #ddd;
        border-radius: 8px;
        width: 300px;
        padding: 10px;
        box-sizing: border-box;
      }
      .month-year-picker-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
      }
      .month-year-picker-header button {
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 16px;
      }
      .year-display {
        font-weight: bold;
        font-size: 16px;
      }
      .month-year-picker-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 5px;
      }
      .month-year-picker-grid button {
        width: 100%;
        padding: 10px 0;
        border: 1px solid #ccc;
        background: #f9f9f9;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.3s;
      }
      .month-year-picker-grid button:hover {
        background: #e6f7ff;
      }
    `;
    const styleElem = document.createElement("style");
    styleElem.type = "text/css";
    styleElem.appendChild(document.createTextNode(css));
    document.head.appendChild(styleElem);
    MonthYearPicker.cssInjected = true;
  }
}
