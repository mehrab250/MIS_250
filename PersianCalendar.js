// persianCalendar.js
class PersianCalendar {
  // بررسی تزریق CSS (به صورت استاتیک)
  static cssInjected = false;

  // متدی برای تزریق CSS لازم به head سند
  static injectCSS() {
    if (PersianCalendar.cssInjected) return;
    const css = `
      /* استایل تقویم شناور با انیمیشن Fade */
      .calendar-popup {
          position: absolute;
          z-index: 1000;
          background: #fff;
          border: none;
          box-shadow: 0 8px 16px rgba(0,0,0,0.2);
          border-radius: 8px;
          opacity: 0;
          transform: scale(0.95);
          transition: opacity 0.3s ease, transform 0.3s ease;
          pointer-events: none;
      }
      .calendar-popup.visible {
          opacity: 1;
          transform: scale(1);
          pointer-events: auto;
      }
      /* استایل ظاهری تقویم */
      .calendar {
          width: 328px;
          padding: 10px;
          background: #fff;
          border-radius: 8px;
      }
      .calendar-header, .calendar-month {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 10px;
      }
      .calendar-weekdays, .calendar-days {
          display: grid;
          grid-template-columns: repeat(7, 1fr);
          gap: 5px;
      }
      .calendar-weekdays {
          margin-bottom: 10px;
      }
      /* تغییر رنگ ایام هفته به rgb(127, 130, 133) */
      .calendar-weekdays div {
          width: 40px;
          height: 40px;
          line-height: 40px;
          border: 1px solid #eee;
          background:rgb(94, 95, 97);
          border-radius: 4px;
          text-align: center;
          font-size: 12px;
          color: rgb(255, 255, 255);
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
      }
      .calendar-days div {
          width: 40px;
          height: 40px;
          line-height: 40px;
          border: 1px solid #eee;
          background: #f9fafb;
          border-radius: 4px;
          text-align: center;
          font-size: 14px;
          cursor: pointer;
          transition: background 0.3s;
      }
      .calendar-days div:hover {
          background: #dceeff;
      }
      button {
          cursor: pointer;
          background: transparent;
          border: none;
          font-size: 16px;
          padding: 5px 10px;
          border-radius: 4px;
          transition: background 0.3s;
      }
      button:hover {
          background: #f0f0f0;
      }
    `;
    const style = document.createElement("style");
    style.type = "text/css";
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);
    PersianCalendar.cssInjected = true;
  }

  constructor(popupElementId, options = {}) {
    // تزریق CSS یکبار اجرا می‌شود
    PersianCalendar.injectCSS();
    // گرفتن المان تقویم شناور از روی آی دی داده شده
    this.popupElement = document.getElementById(popupElementId);
    if (!this.popupElement) {
      throw new Error(`Element with id "${popupElementId}" not found.`);
    }
    // اضافه کردن کلاس تقویم شناور اگر قبلاً اضافه نشده باشد
    if (!this.popupElement.classList.contains("calendar-popup")) {
      this.popupElement.classList.add("calendar-popup");
    }
    // تنظیمات اولیه (سال جاری، ماه جاری و callback انتخاب تاریخ)
    this.currentYear = options.initialYear || 1402;
    this.currentMonth = typeof options.initialMonth === "number" ? options.initialMonth : 0;
    this.onSelectDate = options.onSelectDate || function (selectedDate) {};

    // ایجاد نسخه باند شده متد برای مدیریت کلیک‌های خارج از تقویم
    this.outsideClickHandler = this.handleOutsideClick.bind(this);

    // ایجاد ساختار داخلی تقویم
    this.initCalendar();
  }

  initCalendar() {
    // تعریف ساختار HTML تقویم (بدون دکمه بستن، فقط با قابلیت بسته شدن از طریق کلیک خارج)
    this.popupElement.innerHTML = `
      <div class="calendar">
        <div class="calendar-header">
          <button class="prev-year">&laquo;</button>
          <span class="current-year">${this.currentYear}</span>
          <button class="next-year">&raquo;</button>
        </div>
        <div class="calendar-month">
          <button class="prev-month">&laquo;</button>
          <span class="current-month">${PersianCalendar.monthNames[this.currentMonth]}</span>
          <button class="next-month">&raquo;</button>
        </div>
        <div class="calendar-weekdays">
          <div>شنبه</div>
          <div>یکشنبه</div>
          <div>دوشنبه</div>
          <div>سه‌شنبه</div>
          <div>چهارشنبه</div>
          <div>پنج‌شنبه</div>
          <div>جمعه</div>
        </div>
        <div class="calendar-days"></div>
      </div>
    `;

    // گرفتن مراجع داخلی از المان‌های تاریخ
    this.currentYearSpan = this.popupElement.querySelector(".current-year");
    this.currentMonthSpan = this.popupElement.querySelector(".current-month");
    this.calendarDaysContainer = this.popupElement.querySelector(".calendar-days");

// رویدادهای تغییر سال و ماه
this.popupElement.querySelector(".prev-year")
  .addEventListener("click", (event) => {
    event.preventDefault();
    this.currentYear--;
    this.renderCalendar();
  });
this.popupElement.querySelector(".next-year")
  .addEventListener("click", (event) => {
    event.preventDefault();
    this.currentYear++;
    this.renderCalendar();
  });
this.popupElement.querySelector(".prev-month")
  .addEventListener("click", (event) => {
    event.preventDefault();
    if (this.currentMonth === 0) {
      this.currentMonth = 11;
      this.currentYear--;
    } else {
      this.currentMonth--;
    }
    this.renderCalendar();
  });
this.popupElement.querySelector(".next-month")
  .addEventListener("click", (event) => {
    event.preventDefault();
    if (this.currentMonth === 11) {
      this.currentMonth = 0;
      this.currentYear++;
    } else {
      this.currentMonth++;
    }
    this.renderCalendar();
  });

    // رندر اولیه تقویم
    this.renderCalendar();
  }

  // متد بررسی کلیک خارج از تقویم
  handleOutsideClick(event) {
    if (!this.popupElement.contains(event.target)) {
      this.hide();
    }
  }

  // توابع کمکی به صورت استاتیک
  static isJalaaliLeap(jy) {
    let r = jy - (jy >= 0 ? 474 : 473);
    r = r % 2820;
    if (r < 0) r += 2820;
    return (((r + 38) * 682) % 2816) < 682;
  }

  static jalaliToGregorian(jy, jm, jd) {
    const gy = jy + 621;
    const newYearDay = PersianCalendar.isJalaaliLeap(jy) ? 20 : 21;
    const gregDate = new Date(gy, 2, newYearDay);
    const dayOfYear = (jm <= 7) ? ((jm - 1) * 31 + (jd - 1)) : (6 * 31 + (jm - 8) * 30 + (jd - 1));
    gregDate.setDate(gregDate.getDate() + dayOfYear);
    return { year: gregDate.getFullYear(), month: gregDate.getMonth() + 1, day: gregDate.getDate() };
  }

  static getDaysInMonth(year, month) {
    if (month < 6) return 31;
    if (month < 11) return 30;
    return PersianCalendar.isJalaaliLeap(year) ? 30 : 29;
  }

  static getFirstWeekday(jy, jm) {
    const greg = PersianCalendar.jalaliToGregorian(jy, jm + 1, 1);
    const dateObj = new Date(greg.year, greg.month - 1, greg.day);
    const gDay = dateObj.getDay();
    return gDay === 6 ? 0 : gDay + 1;
  }

  renderCalendar() {
    this.currentYearSpan.innerText = this.currentYear;
    this.currentMonthSpan.innerText = PersianCalendar.monthNames[this.currentMonth];
    this.calendarDaysContainer.innerHTML = "";
    const daysInMonth = PersianCalendar.getDaysInMonth(this.currentYear, this.currentMonth);
    const startOffset = PersianCalendar.getFirstWeekday(this.currentYear, this.currentMonth);

    // ایجاد سلول‌های خالی برای چینش صحیح
    for (let i = 0; i < startOffset; i++) {
      const emptyCell = document.createElement("div");
      this.calendarDaysContainer.appendChild(emptyCell);
    }

    // ایجاد سلول‌های روزهای ماه
    for (let day = 1; day <= daysInMonth; day++) {
      const cell = document.createElement("div");
      cell.innerText = day;
      cell.style.cursor = "pointer";
      cell.addEventListener("click", () => {
        const formattedMonth = (this.currentMonth + 1).toString().padStart(2, "0");
        const formattedDay = day.toString().padStart(2, "0");
        const formattedDate = `${this.currentYear}/${formattedMonth}/${formattedDay}`;
        this.onSelectDate(formattedDate);
        this.hide();
      });
      this.calendarDaysContainer.appendChild(cell);
    }
  }

  show() {
    this.popupElement.style.display = "block";
    // فعال کردن افکت انیمیشن
    setTimeout(() => {
      this.popupElement.classList.add("visible");
    }, 10);
    // افزودن listener کلیک خارج از تقویم با تأخیر تا از رویداد اولیه جلوگیری شود
    setTimeout(() => {
      document.addEventListener("click", this.outsideClickHandler);
    }, 50);
  }

  hide() {
    this.popupElement.classList.remove("visible");
    setTimeout(() => {
      this.popupElement.style.display = "none";
    }, 300);
    document.removeEventListener("click", this.outsideClickHandler);
  }
}

// آرایه نام ماه‌های شمسی به صورت استاتیک
PersianCalendar.monthNames = [
  "فروردین", "اردیبهشت", "خرداد", "تیر",
  "مرداد", "شهریور", "مهر", "آبان",
  "آذر", "دی", "بهمن", "اسفند"
];
