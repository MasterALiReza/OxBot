<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Fetch settings
try {
    $row = db_fetch($pdo, "SELECT * FROM setting LIMIT 1");
} catch (Exception $e) {
    $row = [];
}

$pay_settings = [];
try {
    $stmt = $pdo->query("SELECT NamePay, ValuePay FROM PaySetting");
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pay_settings[$r['NamePay']] = $r['ValuePay'];
    }
} catch (Exception $e) {}

$shop_settings = [];
try {
    $stmt = $pdo->query("SELECT Namevalue, value FROM shopSetting");
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $shop_settings[$r['Namevalue']] = $r['value'];
    }
} catch (Exception $e) {}

$schema = [
    'general' => [
        'title' => 'تنظیمات عمومی',
        'sections' => [
            'وضعیت و دسترسی' => [
                ['name' => 'set_Bot_Status', 'label' => 'وضعیت ربات', 'type' => 'select', 'options' => ['botstatuson' => 'روشن', 'botstatusoff' => 'خاموش'], 'val' => $row['Bot_Status'] ?? ''],
                ['name' => 'set_NotUser', 'label' => 'قفل برای کاربران ثبت نام نشده', 'type' => 'select', 'options' => ['onnotuser' => 'فعال', 'offnotuser' => 'غیرفعال'], 'val' => $row['NotUser'] ?? ''],
                ['name' => 'set_statusnewuser', 'label' => 'وضعیت کاربران جدید', 'type' => 'select', 'options' => ['onnewuser' => 'آزاد', 'offnewuser' => 'بسته'], 'val' => $row['statusnewuser'] ?? ''],
                ['name' => 'set_verifystart', 'label' => 'تاییدیه شروع کار', 'type' => 'select', 'options' => ['onverify' => 'فعال', 'offverify' => 'غیرفعال'], 'val' => $row['verifystart'] ?? ''],
                ['name' => 'set_get_number', 'label' => 'دریافت شماره تماس', 'type' => 'select', 'options' => ['onAuthenticationphone' => 'اجباری', 'offAuthenticationphone' => 'اختیاری/خاموش'], 'val' => $row['get_number'] ?? ''],
                ['name' => 'set_iran_number', 'label' => 'فقط شماره ایران', 'type' => 'select', 'options' => ['onAuthenticationiran' => 'بله', 'offAuthenticationiran' => 'خیر'], 'val' => $row['iran_number'] ?? ''],
            ],
            'گزارشات و ارتباطات' => [
                ['name' => 'set_Channel_Report', 'label' => 'کانال گزارشات', 'type' => 'text', 'placeholder' => '-100xxxxxxxxx', 'val' => $row['Channel_Report'] ?? ''],
                ['name' => 'set_id_support', 'label' => 'آیدی پشتیبانی', 'type' => 'text', 'placeholder' => '123456789', 'val' => $row['id_support'] ?? ''],
                ['name' => 'set_statussupportpv', 'label' => 'پشتیبانی در ربات', 'type' => 'select', 'options' => ['onpvsupport' => 'فعال', 'offpvsupport' => 'غیرفعال'], 'val' => $row['statussupportpv'] ?? ''],
                ['name' => 'set_categoryhelp', 'label' => 'دکمه راهنما در دسته‌بندی‌ها', 'type' => 'select', 'options' => ['1' => 'نمایش', '0' => 'عدم نمایش'], 'val' => $row['categoryhelp'] ?? ''],
            ],
            'تنظیمات کاربر و سرویس' => [
                ['name' => 'set_limit_usertest_all', 'label' => 'محدودیت تعداد تست برای هر کاربر', 'type' => 'number', 'val' => $row['limit_usertest_all'] ?? ''],
                ['name' => 'set_limitnumber', 'label' => 'محدودیت تعداد کانفیگ کاربر', 'type' => 'text', 'val' => $row['limitnumber'] ?? ''],
                ['name' => 'set_removedayc', 'label' => 'روزهای نگهداری سرویس حذف شده', 'type' => 'number', 'val' => $row['removedayc'] ?? ''],
                ['name' => 'set_daywarn', 'label' => 'هشدار پایان سرویس (روز)', 'type' => 'number', 'val' => $row['daywarn'] ?? ''],
                ['name' => 'set_volumewarn', 'label' => 'هشدار پایان حجم (گیگابایت)', 'type' => 'number', 'val' => $row['volumewarn'] ?? ''],
                ['name' => 'set_cronvolumere', 'label' => 'فرکانس بررسی حجم (کرون جاب)', 'type' => 'number', 'val' => $row['cronvolumere'] ?? ''],
            ]
        ]
    ],
    'financial' => [
        'title' => 'تنظیمات مالی و درگاه‌ها',
        'sections' => [
            'کارت به کارت' => [
                ['name' => 'pay_Cartstatus', 'label' => 'وضعیت کارت به کارت', 'type' => 'select', 'options' => ['oncard' => 'روشن', 'offcard' => 'خاموش'], 'val' => $pay_settings['Cartstatus'] ?? ''],
                ['name' => 'pay_cardnumber', 'label' => 'شماره کارت', 'type' => 'text', 'val' => $pay_settings['cardnumber'] ?? ''],
                ['name' => 'pay_namecard', 'label' => 'نام صاحب کارت', 'type' => 'text', 'val' => $pay_settings['namecard'] ?? ''],
                ['name' => 'pay_statuscardautoconfirm', 'label' => 'تایید خودکار کارت به کارت', 'type' => 'select', 'options' => ['onautoconfirm' => 'روشن', 'offautoconfirm' => 'خاموش'], 'val' => $pay_settings['statuscardautoconfirm'] ?? ''],
            ],
            'درگاه زرین‌پال' => [
                ['name' => 'pay_zarinpalstatus', 'label' => 'وضعیت زرین‌پال', 'type' => 'select', 'options' => ['onzarinpal' => 'روشن', 'offzarinpal' => 'خاموش'], 'val' => $pay_settings['zarinpalstatus'] ?? ''],
                ['name' => 'pay_merchant_zarinpal', 'label' => 'مرچنت زرین‌پال', 'type' => 'text', 'val' => $pay_settings['merchant_zarinpal'] ?? ''],
            ],
            'درگاه NowPayment' => [
                ['name' => 'pay_nowpaymentstatus', 'label' => 'وضعیت NowPayment', 'type' => 'select', 'options' => ['onnowpayment' => 'روشن', 'offnowpayment' => 'خاموش'], 'val' => $pay_settings['nowpaymentstatus'] ?? ''],
                ['name' => 'pay_apinowpayment', 'label' => 'API Key NowPayment', 'type' => 'text', 'val' => $pay_settings['apinowpayment'] ?? ''],
            ],
            'درگاه آقای پرداخت' => [
                ['name' => 'pay_statusaqayepardakht', 'label' => 'وضعیت آقای پرداخت', 'type' => 'select', 'options' => ['onaqayepardakht' => 'روشن', 'offaqayepardakht' => 'خاموش'], 'val' => $pay_settings['statusaqayepardakht'] ?? ''],
                ['name' => 'pay_merchant_id_aqayepardakht', 'label' => 'مرچنت آقای پرداخت', 'type' => 'text', 'val' => $pay_settings['merchant_id_aqayepardakht'] ?? ''],
            ],
            'سایر درگاه‌ها' => [
                ['name' => 'pay_statustarnado', 'label' => 'وضعیت درگاه ترنادو', 'type' => 'select', 'options' => ['onternado' => 'روشن', 'offternado' => 'خاموش'], 'val' => $pay_settings['statustarnado'] ?? ''],
                ['name' => 'pay_apiternado', 'label' => 'API Key ترنادو', 'type' => 'text', 'val' => $pay_settings['apiternado'] ?? ''],
                ['name' => 'pay_statusiranpay3', 'label' => 'وضعیت ایران پی', 'type' => 'select', 'options' => ['oniranpay3' => 'روشن', 'offiranpay3' => 'خاموش'], 'val' => $pay_settings['statusiranpay3'] ?? ''],
                ['name' => 'pay_apiiranpay', 'label' => 'API Key ایران پی', 'type' => 'text', 'val' => $pay_settings['apiiranpay'] ?? ''],
            ],
            'نمایندگان (Agent)' => [
                ['name' => 'set_agentreqprice', 'label' => 'حداقل شارژ برای درخواست نمایندگی', 'type' => 'number', 'val' => $row['agentreqprice'] ?? ''],
                ['name' => 'set_statusagentrequest', 'label' => 'وضعیت درخواست نمایندگی', 'type' => 'select', 'options' => ['onrequestagent' => 'باز', 'offrequestagent' => 'بسته'], 'val' => $row['statusagentrequest'] ?? ''],
            ]
        ]
    ],
    'shop' => [
        'title' => 'تنظیمات فروشگاه',
        'sections' => [
            'تنظیمات عمومی فروشگاه' => [
                ['name' => 'set_bulkbuy', 'label' => 'خرید عمده', 'type' => 'select', 'options' => ['onbulk' => 'مجاز', 'offbulk' => 'غیرمجاز'], 'val' => $row['bulkbuy'] ?? ''],
                ['name' => 'set_statuscategory', 'label' => 'دسته‌بندی در فروشگاه', 'type' => 'select', 'options' => ['oncategory' => 'فعال', 'offcategory' => 'غیرفعال'], 'val' => $row['statuscategory'] ?? ''],
                ['name' => 'set_statuscategorygenral', 'label' => 'دسته‌بندی سراسری', 'type' => 'select', 'options' => ['oncategorys' => 'فعال', 'offcategorys' => 'غیرفعال'], 'val' => $row['statuscategorygenral'] ?? ''],
                ['name' => 'shop_configshow', 'label' => 'نمایش کانفیگ پس از خرید', 'type' => 'select', 'options' => ['onconfig' => 'نمایش', 'offconfig' => 'عدم نمایش'], 'val' => $shop_settings['configshow'] ?? ''],
                ['name' => 'shop_statusshowprice', 'label' => 'نمایش قیمت‌ها', 'type' => 'select', 'options' => ['onshowprice' => 'نمایش', 'offshowprice' => 'مخفی'], 'val' => $shop_settings['statusshowprice'] ?? ''],
                ['name' => 'shop_statuschangeservice', 'label' => 'امکان تغییر سرویس', 'type' => 'select', 'options' => ['onstatus' => 'مجاز', 'offstatus' => 'غیرمجاز'], 'val' => $shop_settings['statuschangeservice'] ?? ''],
            ],
            'کش‌بک (بازگشت وجه)' => [
                ['name' => 'pay_chashbackcart', 'label' => 'درصد کش‌بک کارت به کارت', 'type' => 'number', 'val' => $pay_settings['chashbackcart'] ?? ''],
                ['name' => 'pay_chashbackzarinpal', 'label' => 'درصد کش‌بک زرین‌پال', 'type' => 'number', 'val' => $pay_settings['chashbackzarinpal'] ?? ''],
                ['name' => 'pay_cashbacknowpayment', 'label' => 'درصد کش‌بک NowPayment', 'type' => 'number', 'val' => $pay_settings['cashbacknowpayment'] ?? ''],
                ['name' => 'shop_chashbackextend', 'label' => 'درصد کش‌بک تمدید', 'type' => 'number', 'val' => $shop_settings['chashbackextend'] ?? ''],
            ],
            'حجم و زمان اضافه (اکسترا)' => [
                ['name' => 'shop_statusextra', 'label' => 'فروش حجم اضافه', 'type' => 'select', 'options' => ['onextra' => 'فعال', 'offextra' => 'غیرفعال'], 'val' => $shop_settings['statusextra'] ?? ''],
                ['name' => 'shop_statustimeextra', 'label' => 'فروش زمان اضافه', 'type' => 'select', 'options' => ['ontimeextraa' => 'فعال', 'offtimeextraa' => 'غیرفعال'], 'val' => $shop_settings['statustimeextra'] ?? ''],
                ['name' => 'shop_customvolmef', 'label' => 'قیمت هر گیگ حجم اضافه (تومان)', 'type' => 'number', 'val' => $shop_settings['customvolmef'] ?? ''],
                ['name' => 'shop_customtimepricef', 'label' => 'قیمت هر روز زمان اضافه (تومان)', 'type' => 'number', 'val' => $shop_settings['customtimepricef'] ?? ''],
            ]
        ]
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    
    $updates_setting = [];
    $params_setting = [];
    
    foreach($_POST as $key => $val) {
        if(strpos($key, 'set_') === 0) {
            $field = substr($key, 4);
            $updates_setting[] = "$field = ?";
            $params_setting[] = $val;
        } elseif(strpos($key, 'pay_') === 0) {
            $field = substr($key, 4);
            db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $field]);
        } elseif(strpos($key, 'shop_') === 0) {
            $field = substr($key, 5);
            db_query($pdo, "UPDATE shopSetting SET value = ? WHERE Namevalue = ?", [$val, $field]);
        }
    }
    
    if(!empty($updates_setting)) {
        db_query($pdo, "UPDATE setting SET " . implode(', ', $updates_setting), $params_setting);
    }

    flash('success', $textbotlang['panel']['botSettingsSuccess'] ?? 'تنظیمات با موفقیت ذخیره شد.');
    $redirect_tab = $_POST['current_tab'] ?? 'general';
    header('Location: bot_settings.php?tab=' . $redirect_tab);
    exit;
}

$tab = $_GET['tab'] ?? 'general';
if (!array_key_exists($tab, $schema)) {
    $tab = 'general';
}

$pageTitle = $textbotlang['panel']['layoutPageTitleBotSettings'] ?? 'تنظیمات ربات';
$activeNav = 'bot_settings';
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;gap:4px;margin-bottom:18px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:5px;overflow-x:auto" class="fade-up">
    <?php foreach ($schema as $key => $tab_data): ?>
        <?php 
        $icon = 'settings';
        if($key === 'financial') $icon = 'card';
        if($key === 'shop') $icon = 'package';
        ?>
        <a href="?tab=<?= $key ?>"
            style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:7px;font-size:.82rem;font-weight:600;white-space:nowrap;flex-shrink:0;transition:all .15s;text-decoration:none;
                  <?= $tab === $key ? 'background:var(--ac);color:#fff;box-shadow:0 0 14px var(--acg)' : 'color:var(--mute)' ?>">
            <?= icon($icon, 15) ?> <?= $tab_data['title'] ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card fade-up">
    <div class="card-head">
        <div>
            <div class="card-title"><?= $schema[$tab]['title'] ?></div>
        </div>
    </div>
    
    <form method="POST" class="card-body" style="display:flex; flex-direction:column; gap:20px;">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="current_tab" value="<?= $tab ?>">
        
        <?php foreach($schema[$tab]['sections'] as $section_title => $fields): ?>
            <div style="border: 1px solid var(--bd); border-radius: 10px; overflow: hidden; background: var(--bg);">
                <div style="background: var(--sf); padding: 12px 15px; font-weight: bold; border-bottom: 1px solid var(--bd); color: var(--fg); font-size: 0.9rem;">
                    <?= $section_title ?>
                </div>
                <div style="padding: 15px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                    <?php foreach($fields as $f): ?>
                        <div class="field">
                            <label><?= $f['label'] ?></label>
                            <?php if($f['type'] === 'select'): ?>
                                <select name="<?= $f['name'] ?>" class="select">
                                    <?php foreach($f['options'] as $opt_val => $opt_label): ?>
                                        <option value="<?= $opt_val ?>" <?= ($f['val'] == $opt_val) ? 'selected' : '' ?>><?= $opt_label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif($f['type'] === 'text' || $f['type'] === 'number'): ?>
                                <input type="<?= $f['type'] ?>" name="<?= $f['name'] ?>" class="input" value="<?= htmlspecialchars($f['val'] ?? '') ?>" placeholder="<?= $f['placeholder'] ?? '' ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top:10px;">
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 1rem;">
                <?= icon('check', 18) ?> ذخیره تنظیمات <?= $schema[$tab]['title'] ?>
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
