# گزارش ممیزی عمیق پروژه (Ox Panel & OxBot)

> **تاریخ:** 2026-07-19
> **نوع سند:** ممیزی امنیتی / پایداری / کیفیت کد + نقشه راه رفع اشکال (غیرمخرب)
> **روش:** systematic-debugging (تحلیل علت ریشه‌ای) + php-pro (استانداردهای PHP) + OWASP
> **محدوده:** کل مخزن — ربات تلگرام (`index.php`)، پنل ادمین (`panel/`)، API (`api/`)، درگاه‌های پرداخت (`payment/`)، کرون‌ها (`cronbot/`)، سابسکریپشن (`sub/`)، اسکریپت‌های استقرار
>
> ⚠️ **هشدار محرمانگی:** این سند حاوی نقشه آسیب‌پذیری‌های سیستم است. مقادیر واقعی secretها در این سند **mask** شده‌اند، اما همین سند هم نباید در مخزن عمومی منتشر شود. اگر مخزن public است، این فایل را به `.gitignore` اضافه کنید یا مخزن را private کنید.

---

## ۱. خلاصه اجرایی

ممیزی کامل و read-only روی پروژه انجام شد؛ هیچ فایلی تغییر نکرده است. در مجموع **۲۴ یافته** ثبت شد:

| شدت | تعداد | خلاصه |
|---|---|---|
| 🔴 بحرانی (P0) | 3 | credentialهای production در مخزن، دور زدن کامل احراز هویت، SQL Injection |
| 🟠 پرخطر (P1) | 6 | کرون‌های بدون احراز هویت، OTP ضعیف، شمارش لینک سابسکریپشن، race condition مالی، CSRF لاگین، توکن ادمین API |
| 🟡 متوسط (P2) | 9 | IPN بدون امضا، باگ لاگ، نشت خطای DB، مشکلات webhook، عدم تطابق nginx/Apache، supply-chain |
| ⚪ بهداشتی (P3) | 6+ | فایل‌های اضافی، اسکرین‌شات‌ها، کدهای قدیمی/تکثیرشده، فایل‌های God Object |

**سه خطر فوری که باید امروز رسیدگی شوند:**
1. رمزهای root دو سرور production و کلید API داخل مخزن commit شده‌اند → چرخش فوری.
2. `panel/session_bypass.php` به هر بازدیدکننده‌ای سشن agent می‌دهد → حذف فوری از سرور.
3. SQL Injection در مسیر agent → وصله کوچک ولی فوری.

---

## ۲. یافته‌های بحرانی — P0

### P0-1) اطلاعات حساس (Secrets) داخل مخزن

- **شواهد:** حدود ۱۹ فایل پایتون در ریشه‌ی مخزن — از جمله `ssh_run.py`، `ssh_upload.py`، `ssh_test.py`، `test_wg_api.py`، `check_port.py`، `check_firewall.py`، `check_all_firewall.py`، `check_panel_ip.py`، `check_bot_ips.py`، `check_curl.py`، `check_dns.py`، `check_tcp.py` و `check_nginx.py` تا `check_nginx7.py` — حاوی رمز root هاردکد برای دو سرور production (`65.109.181.134` → `qeEe****`، `94.183.225.232` → `clgW****`). همچنین `test_wg_api.py` کلید API سرویس WG-Dashboard (`xgkB****` برای `cod2.grootvip.eu`) را فاش می‌کند.
- **علت ریشه‌ای:** اسکریپت‌های عیب‌یابی/ادمین موقت با credential هاردکد نوشته و commit شده‌اند؛ هیچ سیاست secrets hygiene وجود ندارد و `.gitignore` هم ناقص است (الگوی `cookie.txt` در حالی‌که فایل واقعی `cookies.txt` است).
- **اثر:** هر کسی که به مخزن دسترسی پیدا کند (fork، leak، اکانت GitHub به‌خطرفتاده، حتی تاریخچه‌ی git پس از حذف فایل‌ها) دسترسی **root کامل** به دو سرور production می‌گیرد: دیتابیس کاربران، پنل‌های VPN، ترافیک کاربران — takeover کامل زیرساخت.
- **اصلاح پیشنهادی (فاز ۰، بدون تغییر کد پروژه):**
  1. **اول** رمزهای root هر دو سرور و کلید WG-Dashboard و توکن بکاپ را rotate کنید (قبل از هر کار دیگری — فرض کنید لو رفته‌اند).
  2. سپس فایل‌ها را از تاریخچه‌ی git پاک کنید (`git filter-repo` یا BFG) — حذف ساده کافی نیست چون در history می‌مانند.
  3. فایل‌های `*.py` ادمین، `cookies.txt`، `debug_log.txt`، `log.txt`، `*.png` تستی و `output.html` را به `.gitignore` اضافه کنید.

### P0-2) دور زدن کامل احراز هویت — `panel/session_bypass.php`

- **شواهد:** `panel/session_bypass.php` — اولین کاربرِ agent را از دیتابیس می‌خواند (`SELECT id, namecustom, agent FROM user WHERE agent != 'n' LIMIT 1`) و **بدون هیچ احراز هویتی** `$_SESSION['agent_id']` و سایر متغیرهای سشن را برای بازدیدکننده ست می‌کند.
- **علت ریشه‌ای:** فایل کمکی/debug مرحله‌ی توسعه که روی سرور production فراموش شده است.
- **اثر:** هر بازدیدکننده‌ی ناشناس با یک GET ساده، سشن agent معتبر می‌گیرد و به کل پنل نمایندگی (کاربران، سرویس‌ها، سفارش‌ها) دسترسی پیدا می‌کند.
- **اصلاح پیشنهادی (فاز ۰):** حذف فایل از سرور و مخزن. هیچ وابستگی معتبری به آن نیست.

### P0-3) SQL Injection در `getProductLocCondition()`

- **شواهد:** در `function.php` (حدود L2311-2318) مقدار `$panel_name` مستقیم و بدون escaping داخل رشته‌ی SQL الحاق می‌شود: `"(Location = '$panel_name' OR Location = '/all'$cat_cond)"`. مسیر بهره‌برداری: `panel/ajax/agent_actions.php` (حدود L60-61) که `$_POST['location']` را به همین تابع می‌دهد و خروجی‌اش (`$loc_cond`) خام داخل کوئری `SELECT * FROM product WHERE id = :id AND (agent = :agent OR agent = 'all') AND $loc_cond LIMIT 1` قرار می‌گیرد.
- **علت ریشه‌ای:** ترکیب «پارامتر bind شده» و «الحاق رشته‌ای» در یک کوئری؛ تابع کمکی به‌جای برگرداندن مقدار برای bind، قطعه‌ی SQL برمی‌گرداند.
- **اثر:** یک agent احرازهویت‌شده (که با یافته‌ی P0-2 هر کسی می‌تواند agent شود!) می‌تواند کوئری دلخواه تزریق کند: استخراج هش رمز ادمین، توکن ربات، اطلاعات کارت‌ها، دستکاری داده‌ها.
- **اصلاح پیشنهادی (فاز ۱ — وصله‌ی کوچک و کم‌ریسک):** مقدار location را با whitelist از مقادیر مجاز (نام پنل‌های موجود در جدول `panels`) تطبیق دهید و/یا کوئری را به `(Location = :loc OR Location = '/all')` با bind parameter تغییر دهید. رفتار فعلی برای ورودی‌های معتبر نباید تغییر کند.

---

## ۳. یافته‌های پرخطر — P1

### P1-1) کرون‌جاب‌های بدون احراز هویت + توکن بکاپ commit‌شده

- **شواهد:** حدود ۱۵ اسکریپت در `cronbot/` (`gift.php`، `lottery.php`، `sendmessage.php`، `expireagent.php`، `disableconfig.php`، `activeconfig.php`، `croncard.php`، `statusday.php`، `uptime_node.php`، `uptime_panel.php`، `on_hold.php`، `payment_expire.php`، `iranpay1.php`، `plisio.php`، `configtest.php`، `NoticationsService.php`) هیچ احراز هویتی ندارند و از روی HTTP قابل فراخوانی‌اند. تنها `backupbot.php` توکن چک می‌کند، اما مقدار آن توکن (`$backup_secure_token`) در `config.php` (L23) commit شده است.
- **علت ریشه‌ای:** فرض «کرون فقط از سرور صدا زده می‌شود» بدون هیچ enforcement؛ اشتراک‌گذاری توکن ثابت بین همه‌ی نصب‌ها.
- **اثر:** هر مهاجم می‌تواند ارسال پیام انبوه، هدیه/قرعه‌کشی، غیرفعال‌سازی سرویس‌ها و پردازش مالی را دلخواه و مکرر trigger کند → spam به کاربران، DoS، double-processing.
- **اصلاح پیشنهادی (فاز ۰ در سطح وب‌سرور، بدون تغییر کد):** در nginx/Apache دسترسی به `cronbot/` را به `127.0.0.1` محدود کنید یا یک shared secret الزامی کنید. توکن بکاپ را rotate و از مخزن خارج کنید.

### P1-2) ضعف‌های OTP نماینده‌ها — `panel/ajax/agent_auth.php`

- **شواهد:** `$otp = rand(10000, 99999);` — غیر CSPRNG با فضای ~۹۰ هزار حالت؛ هیچ rate limit روی `verify_otp` (brute-force شدنی) و روی `send_otp` (spam به آیدی‌های دلخواه) نیست؛ استفاده از `$_REQUEST`؛ عدم `session_regenerate_id()` پس از لاگین (session fixation)؛ مقایسه‌ی OTP غیر constant-time.
- **علت ریشه‌ای:** پیاده‌سازی OTP بدون ملاحظات استاندارد احراز هویت دومرحله‌ای.
- **اثر:** تصاحب حساب agent با brute-force آنلاین کد ۵ رقمی؛ اسپم پیام به کاربران تلگرام.
- **اصلاح پیشنهادی (فاز ۱):** `random_int(10000, 99999)` یا کد ۶ رقمی + شمارنده‌ی تلاش ناموفق (حداکثر ۵ بار + قفل موقت) + throttle روی ارسال + `session_regenerate_id(true)` پس از موفقیت + `hash_equals` برای مقایسه.

### P1-3) شمارش‌پذیری لینک سابسکریپشن و ضعف‌های `sub/index.php`

- **شواهد:** در `panel/ajax/add_order_manual.php` (L98) شناسه‌ی فاکتور با `rand(1000000, 9999999)` ساخته می‌شود (فضای ~۹ میلیون، قابل حدس/بروت‌فورس و مستعد تصادم). در `sub/index.php` مقدار از URL با `explode("/sub/", $url)` و `$parts[1]` خوانده می‌شود (بدون بررسی وجود index → crash/warning با query-string) و سرویس صرفاً با `id_invoice` سرو می‌شود — **بدون چک وضعیت فاکتور** (منقضی/غیرفعال هم سرو می‌شود) و بدون rate limit.
- **علت ریشه‌ای:** استفاده از `rand()` برای توکن امنیتی + نبود authorization check هنگام سرو.
- **اثر:** مهاجم با شمارش شناسه‌ها به کانفیگ‌های سایر مشتریان دسترسی پیدا می‌کند (سرقت سرویس)؛ فاکتورهای باطل همچنان کانفیگ می‌دهند.
- **اصلاح پیشنهادی (فاز ۱):** توکن سابسکریپشن را با `bin2hex(random_bytes(16))` بسازید (ستون جدید یا همان `id_invoice` با فرمت جدید برای رکوردهای تازه — سازگاری با لینک‌های قبلی حفظ شود)؛ هنگام سرو، وضعیت/انقضای سرویس را چک کنید؛ parsing ورودی را ایمن کنید.

### P1-4) Race conditionهای مالی (TOCTOU) در پرداخت و کیف پول

- **شواهد:**
  - `payment/zarinpal.php` و `payment/aqayepardakht.php`: الگوی check-then-act — `if($Payment_report['payment_Status'] != "paid"){ DirectPayment(...); update("Payment_report","payment_Status","paid",...) }`. دو callback همزمان → اعتبار/کانفیگ دوبار صادر می‌شود.
  - `function.php` در `update()` (L348): `SELECT ... FOR UPDATE` داخل autocommit اجرا می‌شود — قفل بلافاصله آزاد می‌شود و هیچ محافظتی ایجاد نمی‌کند.
  - به‌روزرسانی Balance در سراسر کد: خواندن، جمع در PHP، نوشتن (`$Balance = intval($Balance_id['Balance']) + $result; update(...)`) → lost update تحت همزمانی.
  - `payment/aqayepardakht.php` (L62): ترکیب mysqli و PDO در یک جریان (`mysqli_query($connect, "SELECT * FROM user WHERE id = '{$Payment_report['id_user']}' LIMIT 1")`).
  - `DirectPayment()` در `function.php` (L787-802): guard وضعیت paid/reject/expire وجود دارد ولی check و act اتمیک نیستند.
- **علت ریشه‌ای:** نبود تراکنش دیتابیس واقعی (beginTransaction + قفل سطر تا commit) و نبود آپدیت اتمیک `SET Balance = Balance + ?`.
- **اثر:** double-credit کیف پول، صدور چند کانفیگ برای یک پرداخت، نادرست‌شدن موجودی‌ها — آسیب مالی مستقیم.
- **اصلاح پیشنهادی (فاز ۲):** callbackهای پرداخت و تمام تغییرات Balance را در تراکنش واقعی ببرید؛ وضعیت را با `UPDATE Payment_report SET payment_Status='paid' WHERE id=? AND payment_Status!='paid'` و چک `rowCount()` اتمیک کنید؛ Balance را فقط با عبارت اتمیک سمت SQL تغییر دهید. این تغییر رفتار تک‌درخواستی فعلی را نمی‌شکند.

### P1-5) CSRF لاگین ادمین + fallback رمز plaintext

- **شواهد:** `panel/login.php` — فرم لاگین توکن CSRF را رندر می‌کند (L155) اما handler پست هرگز `csrf_check_post()` را صدا نمی‌زند. همچنین fallbackهای مقایسه‌ی plaintext وجود دارد (L34: `$password === $storedHash`، L39: `$password === $admin['password']`).
- **علت ریشه‌ای:** نیمه‌کاره‌ماندن پیاده‌سازی CSRF؛ سازگاری با رمزهای قدیمی هش‌نشده.
- **اثر:** حمله‌ی login CSRF؛ در صورت لو رفتن دیتابیس، رمزهای plaintext ذخیره‌شده بلافاصله قابل استفاده‌اند.
- **اصلاح پیشنهادی (فاز ۱):** یک فراخوانی `csrf_check_post()` در ابتدای handler اضافه کنید (رفتار کاربر عادی تغییر نمی‌کند). فاز ۲: مهاجرت رمزهای قدیمی به bcrypt و حذف fallback.
- **نکته‌ی مثبت موجود:** bcrypt، dummy-hash برای timing، rate limit لاگین (۱۰ تلاش/۱۵ دقیقه) و `session_regenerate_id` در این فایل درست پیاده شده‌اند — حفظ شوند.

### P1-6) توکن ربات به‌عنوان توکن ادمین API — `api/users.php`

- **شواهد:** `api/users.php` (L49-62) در `validateToken()` لیست توکن‌های مجاز را `[$token, $APIKEY]` می‌گیرد — یعنی خودِ توکن ربات تلگرام به‌عنوان bearer token ادمین پذیرفته می‌شود (علاوه بر `hash.txt` ثابت).
- **علت ریشه‌ای:** استفاده‌ی مجدد از یک secret پرانتشار (توکن ربات در لاگ‌ها، پنل‌ها و …) به‌عنوان credential مدیریتی.
- **اثر:** هر کسی که توکن ربات را داشته باشد (از طریق webhook URL، لاگ، یافت P0-1 و …) دسترسی ادمین API می‌گیرد.
- **اصلاح پیشنهادی (فاز ۱/۲):** توکن API جداگانه‌ی تصادفی (`random_bytes`) تعریف و توکن ربات را از لیست حذف کنید.

---

## ۴. یافته‌های متوسط — P2

### P2-1) عدم راستی‌آزمایی امضای IPN در `payment/nowpayment.php`
هدر `x-nowpayments-sig` هرگز با HMAC بررسی نمی‌شود. تا حدی با double-check از API (`StatusPayment()`) و guard تکرار پرداخت پوشش داده شده، ولی IPN جعلی با وضعیت ساختگی همچنان تا مرحله‌ی استعلام پیش می‌رود. **فاز ۲:** بررسی HMAC-SHA512 بدنه‌ی خام با IPN secret + `hash_equals`.

### P2-2) باگ شرط لاگ در `update()` — `function.php` (L388)
عبارت `if ($field != "message_count" || $field != "last_message_time")` همیشه `true` است (باید `&&` باشد). نتیجه: **هر** آپدیت — از جمله مقادیر حساس مثل هش رمز و توکن‌ها — در `log.txt` نوشته می‌شود؛ فایلی که با یافته‌ی P2-5 ممکن است از وب قابل دانلود باشد. **فاز ۲:** اصلاح به `&&` + محدودکردن فیلدهای حساس از لاگ.

### P2-3) نشت جزئیات دیتابیس در `select()` — `function.php` (L490)
در catch، `die("Query failed: " . $e->getMessage())` پیام خطای دیتابیس را به خروجی HTTP/تلگرام می‌فرستد (نشت ساختار schema و مسیرها). **فاز ۲:** `error_log` + پیام عمومی برای کاربر.

### P2-4) PRNG ضعیف در `generateUUID()` — `function.php` (L514-516)
`openssl_random_pseudo_bytes(16)` قدیمی و در برخی نسخه‌ها غیر CSPRNG است. **فاز ۲:** جایگزینی با `random_bytes(16)` — drop-in و بدون تغییر رفتار.

### P2-5) عدم تطابق Apache/nginx → فایل‌های حساس قابل دانلود
`.htaccess` ریشه فایل‌های `*.txt`/`*.json` را بلاک می‌کند ولی فقط تحت Apache کار می‌کند؛ اسکریپت‌های `check_nginx*.py` نشان‌دهنده‌ی استقرار nginx هستند که `.htaccess` را نادیده می‌گیرد. در آن صورت `cookies.txt`، `debug_log.txt`، `conditions.txt`، `text.json`، `api/documents.txt` و `log.txt` عمومی‌اند. **فاز ۰:** معادل‌سازی بلاک‌ها در کانفیگ nginx (`location ~* \.(txt|json|log)$ { deny all; }`).

### P2-6) تله‌های webhook در `index.php`
- L12: `ini_set('memory_limit', '-1')` → یک آپدیت سنگین می‌تواند حافظه‌ی سرور را ببلعد (DoS).
- L14-17: دو `UPDATE` مهاجرت hardcoded روی جدول `invoice` در **هر** hit وب‌هوک اجرا می‌شود — کار اضافی دائمی + ریسک تغییر داده‌ی ناخواسته.
- L63 و L128-142: چند `fetchAll(FETCH_COLUMN)` تمام‌جدول (آیدی همه‌ی کاربران، فاکتورها، کدهای تخفیف، محصولات، قیمت‌ها، کارت‌ها) در هر آپدیت در حافظه لود می‌شود.
- L103: `write_debug_log()` متن همه‌ی پیام‌ها را روی دیسک می‌نویسد (I/O + حریم خصوصی).
- **فاز ۳:** حذف مهاجرت‌های باقی‌مانده، کش/کوئری نقطه‌ای به‌جای لود کامل، سقف memory limit معقول، غیرفعال‌کردن debug log در production.

### P2-7) زنجیره‌ی تأمین در `install.sh` — self-update
تابع `self_update_script()` فایل `install.sh` را از `raw.githubusercontent.com/.../main/install.sh` دانلود و `exec` می‌کند (فقط md5 + `bash -n`). به‌خطراافتادن مخزن/اکانت = اجرای کد دلخواه روی **همه‌ی** سرورهای نصب‌شده. **فاز ۳:** pin به tag/commit امضاشده + بررسی امضا (cosign/GPG) یا حذف self-update.

### P2-8) مدیریت خطای اتصال در `config.php`
هنگام شکست PDO فقط `error_log` می‌شود و `$pdo` تعریف‌نشده می‌ماند → fatal error در ادامه با پیام گیج‌کننده؛ مسیر mysqli نیز `die("error"...)` پیام خطای اتصال (شامل host/user) را لو می‌دهد. **فاز ۲:** شکست کنترل‌شده با پیام عمومی + `error_log` کامل.

### P2-9) پایداری ورودی در `api/miniapp.php`
خواندن `$_GET['actions']` بدون `isset` (L20) و `explode('Bearer ', $headers['Authorization'])[1]` (L45) بدون بررسی کلید/فرمت → warning/crash روی هدر ناموجود یا بدفرمت. ترتیب چک block و اعتبارسنجی توکن هم نیازمند بازبینی است. **فاز ۲/۳:** guard clauseهای `isset` + پاسخ 401/400 تمیز.

---

## ۵. بهداشت مخزن و کیفیت کد — P3

### P3-1) فایل‌های اضافی commit‌شده
- `scratch/` شامل دو کپی کامل قدیمی پروژه (`mirzabot` + `WGDashboard` به‌همراه `vendor`)؛ نسخه‌ی قدیمی `backupbot.php` در آن `shell_exec("zip -r $destination/file.zip ...")` بدون escaping دارد.
- `vpnbot/Default/func.php` و `vpnbot/update/func.php`: سیستم قدیمی فایل‌محور (`data/{id}/{id}.json`).
- `old_index.php`، `old_version.php`، `old_wg.php`، `admin_old.php`، `fix_admin.php`، `panel/opcache_clear.php`، `panel/test_*.php` (۵ فایل)، `test_db.php`، `test_strtotime.php`، `test_wg_api.py`.
- حدود ۴۰ اسکرین‌شات PNG، `output.html`، `cookies.txt`، `debug_log.txt`، `conditions.txt` در ریشه.
- `api/index.php` فایل خالی مرده.
- **ریسک:** سطح حمله‌ی بزرگ‌تر (endpointهای تست/قدیمی روی production)، نشت اطلاعات، سردرگمی نگهداشت. **فاز ۳:** حذف/آرشیو خارج از webroot.

### P3-2) فایل‌های God Object و تکرار منطق
`index.php` (۸۳۸۴ خط)، `function.php` (۲۵۰۰ خط)، `admin.php` (۲۱۰۰+ خط)، `api/miniapp.php` (۹۹۸ خط)، `panel/ajax/agent_actions.php` (۱۰۳۷ خط). منطق callback پرداخت بین چند فایل و کپی‌های `scratch/` تکرار شده است. **فاز ۳ (تدریجی):** استخراج لایه‌ی سرویس/مخزن، یکپارچه‌سازی جریان پرداخت، بدون تغییر رفتار (refactor با معیار regression).

### P3-3) ترکیب PDO و mysqli
هر دو در `config.php` ساخته می‌شوند و در نقاطی (مثل `payment/aqayepardakht.php` L62) مخلوط استفاده می‌شوند. **فاز ۳:** یکسان‌سازی روی PDO.

### P3-4) مسیر سشن داخل webroot
`panel/inc/config.php` مسیر ذخیره‌ی سشن را `panel/sessions` زیر webroot قرار می‌دهد؛ باید بلاک شدن دسترسی وب به آن تضمین شود یا به خارج از webroot منتقل شود. **فاز ۰ (بررسی/بلاک در وب‌سرور)**.

### P3-5) نبود تست خودکار
هیچ تستی در مخزن نیست؛ هر refactor فاز ۳ باید با حداقل تست دود (smoke) روی جریان‌های حیاتی (خرید، پرداخت، سرو سابسکریپشن) همراه شود.

---

## ۶. نقشه راه رفع اشکال (غیرمخرب، فازبندی‌شده)

> اصل راهنما: هیچ فازی رفتار فعلی سیستم برای ورودی‌های معتبر را تغییر نمی‌دهد؛ ابتدا ریسک‌های «بدون تغییر کد» بسته می‌شوند، بعد وصله‌های کوچک، بعد اصلاحات ساختاری.

### فاز ۰ — امروز (بدون هیچ تغییر کدی)
**هدف:** بستن بزرگ‌ترین سطح حمله بدون دست‌زدن به کد.
1. چرخش فوری: رمز root هر دو سرور، کلید WG-Dashboard، توکن بکاپ، و بازبینی توکن ربات.
2. حذف `panel/session_bypass.php` از سرور (و سپس مخزن).
3. حذف/محدودکردن `cookies.txt`، `debug_log.txt`، `log.txt`، `output.html` از webroot.
4. در کانفیگ nginx: بلاک `cronbot/` برای غیر از `127.0.0.1`، بلاک `*.txt`/`*.json`/`*.log`، بلاک `panel/sessions`.
- **پیش‌نیاز:** دسترسی SSH به سرور. **ریسک:** تقریباً صفر (تغییر رفتاری در کد نیست). **Rollback:** برگشت کانفیگ وب‌سرور. **معیار تأیید:** `curl` به فایل‌های فوق → 403/404؛ فراخوانی HTTP کرون‌ها از بیرون → 403.

### فاز ۱ — وصله‌های کوچک امنیتی (کم‌ریسک)
1. اصلاح SQLi: whitelist/bind برای `location` در مسیر agent (P0-3).
2. OTP: `random_int` + rate limit + `session_regenerate_id` + `hash_equals` (P1-2).
3. لاگین: افزودن فراخوانی `csrf_check_post()` (P1-5).
4. توکن سابسکریپشن تصادفی امن + چک وضعیت سرویس هنگام سرو (P1-3) — با حفظ سازگاری لینک‌های قدیمی.
5. توکن اختصاصی API و حذف توکن ربات از لیست مجاز (P1-6).
- **ریسک:** کم؛ هر مورد ۱ تا چند خط. **Rollback:** revert تک‌کامیت. **معیار تأیید:** تست دستی جریان لاگین/OTP/خرید/سرو لینک + تست تزریق `location` با پیلود ساده → رد شود.

### فاز ۲ — یکپارچگی مالی و مدیریت خطا
1. تراکنش واقعی در callbackهای پرداخت + آپدیت اتمیک وضعیت/ Balance (P1-4).
2. بررسی HMAC در NowPayments (P2-1).
3. اصلاح `||`→`&&` در لاگ `update()` و حذف فیلدهای حساس از لاگ (P2-2).
4. حذف `die()` با پیام DB و جایگزینی پیام عمومی (P2-3، P2-8).
5. `random_bytes` در `generateUUID()` (P2-4).
6. مهاجرت رمزهای plaintext ادمین به bcrypt و حذف fallback (P1-5).
- **ریسک:** متوسط (لمس جریان پول) → اجرا روی staging با دیتابیس کپی + تست همزمانی (دو callback موازی). **Rollback:** نگه‌داشت نسخه‌ی قبلی فایل‌های پرداخت؛ تراکنش‌ها خودrollback دارند. **معیار تأیید:** سناریوی double-callback فقط یک بار credit دهد؛ موجودی تحت ۵۰ درخواست همزمان صحیح بماند.

### فاز ۳ — بهداشت و ساختار (تدریجی)
1. پاک‌سازی secrets از تاریخچه‌ی git (`git filter-repo`/BFG) + تکمیل `.gitignore`.
2. حذف/آرشیو `scratch/`، `old_*.php`، `test_*`، اسکرین‌شات‌ها و فایل‌های debug از webroot (P3-1).
3. حذف مهاجرت‌های hardcoded هر-درخواست و سقف `memory_limit` و کش‌کردن لودهای تمام‌جدول در `index.php` (P2-6).
4. pin و امضای منبع self-update یا حذف آن (P2-7).
5. refactor تدریجی God Objectها و یکسان‌سازی روی PDO با تست دود (P3-2، P3-3، P3-5).
- **ریسک:** متوسط تا بالا (ساختاری) → هر اقلام در PR جداگانه با بررسی regression. **معیار تأیید:** رفتار ربات/پنل/پرداخت بدون تغییر؛ حجم repo و سطح exposure کاهش‌یافته.

---

## ۷. Decision Log و الگوهای مثبت

### Decision Log
| تصمیم | گزینه‌ها | دلیل انتخاب |
|---|---|---|
| خروجی فقط مستندات باشد (بدون تغییر کد) | اعمال مستقیم وصله‌ها | دستور صریح کاربر: هیچ ویرایشی که پروژه را بشکند انجام نشود |
| زبان سند: فارسی | فارسی / English / دوزبانه | انتخاب کاربر در Understanding Lock |
| mask کردن secrets در سند | درج کامل / mask کامل | جلوگیری از تکثیر secret در فایل commit‌شده‌ی جدید |
| محل سند: `docs/plans/` | ریشه / docs/plans | تبعیت از پیش‌نمونه‌ی موجود (`2026-06-29-affiliate-wallet-management.md`) |
| نقشه راه فازبندی‌شده P0→P3 | یک فهرست واحد | اولویت‌دهی بر اساس ریسک و غیرمخرب‌بودن هر گام |
| اجرای remediation خارج از scope این تسک | اجرا + مستندسازی | جلوگیری از ریسک شکستن سیستم در حال کار |

### الگوهای مثبت که باید حفظ شوند
- `api/verify.php`: اعتبارسنجی HMAC صحیح initData تلگرام (secret با `WebAppData`، `hash_equals`، `random_bytes(20)`).
- `panel/ajax/service_action.php`: ترکیب درست require_auth + `csrf_check_post()` + prepared statement + `htmlspecialchars`.
- `panel/inc/config.php`: helperهای CSRF و ساختار سشن.
- `checktelegramip()`: allowlist آی‌پی تلگرام برای webhook.
- `panel/login.php`: bcrypt + dummy-hash + rate limit + `session_regenerate_id`.
- `select()`/`update()`: مقدارها همیشه bind می‌شوند (مشکل فقط در identifierهای الحاقی است — P0-3).
- `cronbot/backupbot.php`: escaping آرگومان‌ها، staging در `/tmp`، محدودسازی منابع با nice/ionice و fallbackهای چندلایه.

---

*پایان سند — این گزارش صرفاً مستندسازی است؛ هیچ تغییری در کد پروژه ایجاد نشده است.*
