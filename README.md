# 🤖 Ox Panel & OxBot

<p align="center">
    <a href="https://t.me/OxVpN_Net" target="_blank">
        <img src="https://img.shields.io/badge/Telegram-Group-blue?style=flat-square&logo=telegram" alt="Telegram Channel"/>
    </a>
    <a href="https://github.com/MasterALiReza/OxBot" target="_blank">
        <img src="https://img.shields.io/github/stars/MasterALiReza/OxBot?style=social" alt="GitHub Stars"/>
    </a>
    <a href="https://img.shields.io/github/forks/MasterALiReza/OxBot?style=flat-square" target="_blank">
        <img src="https://img.shields.io/github/forks/MasterALiReza/OxBot?style=flat-square" alt="GitHub Forks"/>
    </a>
    <a href="https://github.com/MasterALiReza/OxBot/issues" target="_blank">
        <img src="https://img.shields.io/github/issues/MasterALiReza/OxBot?style=flat-square" alt="GitHub Issues"/>
    </a>
</p>

---

# 🇬🇧 English Documentation

## ✨ Overview

**OxBot** (together with the powerful **Ox Web Panel**) is an all-in-one solution designed for selling and managing VPN services. It provides complete automation, allowing you to connect panels like **Marzban**, **3x-ui**, **Alireza Panel**, **Pasarguard**, **IBSng**, etc., and offer VPN subscriptions directly to users via a Telegram bot with automatic config building.

---

## ⚙️ Features

### 🖥️ **Ox Web Panel Features**
- **Sleek Admin Dashboard**: Monitor servers, active subscriptions, total revenue, and live bot users.
- **Multi-Server Management**: Add, update, and monitor multiple server backends (Marzban, 3x-ui, etc.) simultaneously.
- **Product & Category Control**: Create custom VPN packages, set pricing, limits, and organize them into categories.
- **Gateway Management**: Seamlessly configure fiat and crypto gateways (NowPayments, AqayePardakht, etc.).
- **User & Admin Management**: Delegate roles, add secondary admins/agents, and manage agent access levels.
- **Dynamic Text Settings**: Customize all Telegram bot messages, menus, and keyboard layouts directly from the web panel.

### 📱 **OxBot Telegram Bot Features**
- **Automated Purchase**: Instant configuration creation and QR code delivery upon payment.
- **Various Payment Gateways**: Automated Crypto (NowPayments), automated local fiat gateways, and manual card-to-card verification.
- **Subscription Management**: Users can retrieve configurations, buy extra volume, renew services, and update connection links.
- **Free Trial Accounts**: Automated test packages for new users to evaluate server speed.
- **Identity Verification**: Smart phone number verification system to prevent bot abuse and multiple trial accounts.
- **Mandatory Join (Sponsor Channel)**: Require users to join specific Telegram channels to unlock bot features.
- **Integrated Support Tickets**: Users can report bugs or open support tickets that admins can reply to directly.

---

## 🚀 Installation & Update

### Prerequisites
- **OS**: Ubuntu Server 22.04+ (Fresh install recommended)
- **Domain**: A registered domain pointing to your server IP.

### 🔧 Direct Installation (Stable)
Run the command below in your server's root terminal:
```bash
curl -o install.sh -L https://raw.githubusercontent.com/MasterALiReza/OxBot/main/install.sh && bash install.sh
```
*Choose **Option 1** when the interactive menu loads.*

### 🔄 Updating the Bot
Run the same command and choose **Option 2 (Update OxBot)** from the menu to pull the latest updates safely:
```bash
curl -o install.sh -L https://raw.githubusercontent.com/MasterALiReza/OxBot/main/install.sh && bash install.sh
```

---

## 🙏 Credits & Open Source
This project is built upon the solid foundation of the open-source community.
- **Original Source**: OxBot is a refactored and rebranded version built on the core of the original **[Mirza Panel](https://github.com/MasterALiReza/mirzabot)** project. Huge thanks to the original creators and contributors of Mirza Panel.

<br>
<p align="center">
  <b>[ Scroll Down for Persian Documentation / برای مشاهده مستندات فارسی به پایین صفحه بروید ]</b>
</p>
<br>

---

# 🇮🇷 مستندات فارسی (Persian)

## ✨ معرفی پروژه

**OxBot** (به همراه **پنل تحت وب Ox**) یک راهکار جامع و تمام‌عیار برای فروش و مدیریت سرویس‌های VPN است. این سیستم به شما امکان می‌دهد تا پنل‌های محبوب خود مانند **مرزبان (Marzban)**، **3x-ui**، **علیرضا پنل**، **پاسارگاد**، **IBSng** و... را متصل کرده و فروش اکانت‌ها را از طریق ربات تلگرام با قابلیت ساخت خودکار کانفیگ، کاملاً اتوماتیک کنید.

---

## ⚙️ ویژگی‌ها و امکانات

### 🖥️ **امکانات پنل وب مدیریت (Ox Web Panel)**
- **داشبورد زیبا و مدرن:** مشاهده آمار زنده سرورها، کاربران فعال ربات، کل درآمد و تراکنش‌ها به صورت نموداری.
- **مدیریت چندسرور همزمان:** امکان اتصال و پایش چندین سرور مختلف (مرزبان، 3x-ui و...) از یک پنل واحد.
- **مدیریت محصولات و دسته‌بندی‌ها:** ایجاد بسته‌های حجمی/زمانی دلخواه، قیمت‌گذاری و گروه‌بندی محصولات.
- **مدیریت درگاه‌های پرداخت:** پیکربندی درگاه‌های ریالی و رمزارز (نوپی‌منتز، آقای پرداخت و...) بدون نیاز به دستکاری کد.
- **مدیریت دسترسی ادمین‌ها و نمایندگان:** تعریف ادمین‌های جدید با سطوح دسترسی مختلف و مدیریت نمایندگان فروش.
- **تغییر پویای متن‌های ربات:** قابلیت شخصی‌سازی کامل پیام‌ها، دکمه‌ها و منوهای ربات تلگرام از داخل پنل وب.

### 📱 **امکانات ربات تلگرام (OxBot)**
- **خرید آنی و خودکار:** تحویل فوری کانفیگ و کد QR بلافاصله پس از پرداخت موفق کاربر.
- **روش‌های متنوع پرداخت:** پرداخت اتوماتیک رمزارز (NowPayments)، درگاه‌های ریالی و ثبت فیش کارت‌به‌کارت با تایید ادمین.
- **مدیریت اشتراک توسط کاربر:** امکان تمدید سرویس، خرید حجم اضافه، دریافت مجدد کانفیگ و بروزرسانی لینک اتصال.
- **تست رایگان:** ارائه‌ اکانت تست خودکار به کاربران جدید برای بررسی کیفیت و سرعت سرورها.
- **تایید هویت هوشمند:** احراز هویت با شماره تلفن جهت جلوگیری از سوءاستفاده و دریافت مکرر اکانت تست.
- **عضویت اجباری کانال:** قفل کردن امکانات ربات تا زمان عضویت کاربر در کانال یا گروه‌های اسپانسر شما.
- **سیستم تیکت و پشتیبانی:** امکان ارسال پیام و گزارش باگ توسط کاربر و پاسخ مستقیم ادمین از پنل.

---

## 🚀 نصب و بروزرسانی

### پیش‌نیازها
- **سیستم‌عامل:** اوبونتو سرور نسخه 22.04 به بالا (ترجیحاً سرور خام)
- **دامنه:** یک دامنه یا ساب‌دامنه ست شده روی IP سرور شما.

### 🔧 دستور نصب مستقیم (نسخه پایدار)
دستور زیر را در ترمینال سرور خود با دسترسی root وارد کنید:
```bash
curl -o install.sh -L https://raw.githubusercontent.com/MasterALiReza/OxBot/main/install.sh && bash install.sh
```
*سپس از منوی باز شده گزینه **۱** را انتخاب کنید.*

### 🔄 بروزرسانی ربات
جهت آپدیت ربات به آخرین نسخه بدون حذف اطلاعات قبلی، دستور زیر را اجرا کرده و گزینه **۲ (Update OxBot)** را انتخاب کنید:
```bash
curl -o install.sh -L https://raw.githubusercontent.com/MasterALiReza/OxBot/main/install.sh && bash install.sh
```

---

## 🙏 تشکر و منابع متن‌باز
این پروژه قدرت خود را از زحمات جامعه متن‌باز به دست آورده است:
- **سورس اصلی:** پروژه OxBot توسعه‌یافته و بازنویسی‌شده بر پایه سورس قدرتمند و متن‌باز **[Mirza Panel](https://github.com/MasterALiReza/mirzabot)** است. از توسعه‌دهندگان اولیه میرزا پنل برای خلق این معماری تشکر و قدردانی می‌کنیم.

### Contributors
![Contributors](https://contrib.rocks/image?repo=MasterALiReza/OxBot)
