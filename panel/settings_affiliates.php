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
        if ($r['NamePay'] === 'minbalance' || $r['NamePay'] === 'maxbalance') {
            $decoded = json_decode($r['ValuePay'], true);
            if (is_array($decoded)) {
                $pay_settings[$r['NamePay']] = $decoded['n'] ?? ($decoded['allusers'] ?? '');
                $pay_settings[$r['NamePay'] . 'paynotverify_from_json'] = $decoded['f'] ?? '';
            } else {
                $pay_settings[$r['NamePay']] = $r['ValuePay'];
            }
        } else {
            $pay_settings[$r['NamePay']] = $r['ValuePay'];
        }
    }
    if (isset($pay_settings['minbalancepaynotverify_from_json']) && $pay_settings['minbalancepaynotverify_from_json'] !== '') {
        $pay_settings['minbalancepaynotverify'] = $pay_settings['minbalancepaynotverify_from_json'];
    }
    if (isset($pay_settings['maxbalancepaynotverify_from_json']) && $pay_settings['maxbalancepaynotverify_from_json'] !== '') {
        $pay_settings['maxbalancepaynotverify'] = $pay_settings['maxbalancepaynotverify_from_json'];
    }
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT cardnumber, namecard FROM card_number LIMIT 1");
    if($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pay_settings['cardnumber'] = $r['cardnumber'];
        $pay_settings['namecard'] = $r['namecard'];
    }
} catch (Exception $e) {}

$shop_settings = [];
try {
    $stmt = $pdo->query("SELECT Namevalue, value FROM shopSetting");
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($r['Namevalue'] === 'chashbackextend_agent') {
            $decoded = json_decode($r['value'], true);
            if (is_array($decoded)) {
                $shop_settings['chashbackextend_agent_n'] = $decoded['n'] ?? 0;
                $shop_settings['chashbackextend_agent_n2'] = $decoded['n2'] ?? 0;
            }
        } else {
            $shop_settings[$r['Namevalue']] = $r['value'];
        }
    }
} catch (Exception $e) {}

$affiliate_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM affiliates LIMIT 1");
    if($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $affiliate_settings = $r;
    }
} catch (Exception $e) {}

$cron_status = json_decode($row['cron_status'] ?? '{}', true);
$limitnumber = json_decode($row['limitnumber'] ?? '{}', true);
$lottery_prize = json_decode($row['Lottery_prize'] ?? '{}', true);

$schema = [
    'affiliates' => [
        'title' => 'تنظیمات پورسانت',
        'icon' => 'percent',
        'sections' => [
            'سیستم پورسانت' => [
                ['name' => 'set_affiliatesstatus', 'label' => 'وضعیت کلی سیستم همکاری در فروش', 'type' => 'select', 'options' => ['onaffiliates' => 'فعال', 'offaffiliates' => 'غیرفعال'], 'val' => $row['affiliatesstatus'] ?? ''],
                ['name' => 'aff_status_commission', 'label' => 'وضعیت پورسانت‌دهی با خرید کاربر', 'type' => 'select', 'options' => ['oncommission' => 'فعال', 'offcommission' => 'غیرفعال'], 'val' => $affiliate_settings['status_commission'] ?? ''],
                ['name' => 'aff_withdrawal_status', 'label' => 'وضعیت درخواست تسویه (برداشت کیف پول)', 'type' => 'select', 'options' => ['onwithdraw' => 'فعال', 'offwithdraw' => 'غیرفعال'], 'val' => $affiliate_settings['withdrawal_status'] ?? 'onwithdraw'],
                ['name' => 'aff_porsant_one_buy', 'label' => 'نحوه محاسبه پورسانت خرید', 'type' => 'select', 'options' => ['off_buy_porsant' => 'پورسانت برای همه خریدها', 'on_buy_porsant' => 'پورسانت فقط برای خرید اول'], 'val' => $affiliate_settings['porsant_one_buy'] ?? 'off_buy_porsant'],
            ],
            'پاداش اولین خرید زیرمجموعه' => [
                ['name' => 'aff_first_buy_reward', 'label' => 'مبلغ پاداش اولین خرید (تومان - ۰ برای غیرفعال)', 'type' => 'number', 'val' => $affiliate_settings['first_buy_reward'] ?? '0'],
            ],
            'پاداش عضویت زیرمجموعه (بدون خرید)' => [
                ['name' => 'aff_invite_reward', 'label' => 'مبلغ پاداش به ازای هر نفری که ثبت نام کند (تومان - ۰ برای غیرفعال)', 'type' => 'number', 'val' => $affiliate_settings['invite_reward'] ?? '0'],
            ],
            'سطوح بازاریابی' => [
                ['name' => 'set_affiliatespercentage', 'label' => 'درصد پورسانت سطح برنزی (پیش‌فرض)', 'type' => 'number', 'val' => $row['affiliatespercentage'] ?? '0'],
                ['name' => 'aff_silver_threshold', 'label' => 'حداقل خرید زیرمجموعه برای سطح نقره‌ای', 'type' => 'number', 'val' => $affiliate_settings['silver_threshold'] ?? '10'],
                ['name' => 'aff_silver_percentage', 'label' => 'درصد پورسانت سطح نقره‌ای', 'type' => 'number', 'val' => $affiliate_settings['silver_percentage'] ?? '15'],
                ['name' => 'aff_gold_threshold', 'label' => 'حداقل خرید زیرمجموعه برای سطح طلایی', 'type' => 'number', 'val' => $affiliate_settings['gold_threshold'] ?? '50'],
                ['name' => 'aff_gold_percentage', 'label' => 'درصد پورسانت سطح طلایی', 'type' => 'number', 'val' => $affiliate_settings['gold_percentage'] ?? '25'],
            ],
            'بنر اختصاصی زیرمجموعه‌گیری' => [
                ['name' => 'banner_base_file', 'label' => 'آپلود تصویر پایه بنر (jpg)', 'type' => 'file', 'description' => 'یک تصویر JPG آپلود کنید تا ربات لینک زیرمجموعه‌گیری و کیوآر کد را روی آن درج کند.'],
            ],
            'تخفیف و رسانه' => [
                ['name' => 'aff_Discount', 'label' => 'کد تخفیف به معرف', 'type' => 'select', 'options' => ['onDiscountaffiliates' => 'فعال', 'offDiscountaffiliates' => 'غیرفعال'], 'val' => $affiliate_settings['Discount'] ?? ''],
                ['name' => 'aff_price_Discount', 'label' => 'مبلغ/درصد تخفیف', 'type' => 'number', 'val' => $affiliate_settings['price_Discount'] ?? '0'],
                ['name' => 'aff_media_type', 'label' => 'نوع رسانه راهنما (تصویر / ویدیو)', 'type' => 'select', 'options' => ['photo' => 'تصویر', 'video' => 'ویدیو'], 'val' => $affiliate_settings['media_type'] ?? 'photo'],
                ['name' => 'aff_id_media', 'label' => 'شناسه مدیا راهنما', 'type' => 'text', 'val' => $affiliate_settings['id_media'] ?? ''],
                ['name' => 'aff_media_file', 'label' => 'آپلود رسانه جدید (جایگزین شناسه بالا می‌شود)', 'type' => 'file'],
                ['name' => 'aff_description', 'label' => 'متن توضیحات راهنما', 'type' => 'textarea', 'val' => $affiliate_settings['description'] ?? ''],
            ]
        ]
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    
    $updates_setting = [];
    $params_setting = [];
    $new_cardnumber = null;
    $new_namecard = null;
    
    $new_cron_status = json_decode($row['cron_status'] ?? '{}', true);
    if (!is_array($new_cron_status)) $new_cron_status = [];
    
    $new_limitnumber = json_decode($row['limitnumber'] ?? '{}', true);
    if (!is_array($new_limitnumber)) $new_limitnumber = [];
    
    $new_lottery_prize = json_decode($row['Lottery_prize'] ?? '{}', true);
    if (!is_array($new_lottery_prize)) $new_lottery_prize = [];
    
    $new_chashbackextend_agent = ['n' => 0, 'n2' => 0];
    $stmt = $pdo->query("SELECT value FROM shopSetting WHERE Namevalue = 'chashbackextend_agent'");
    if($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dec = json_decode($r['value'], true);
        if(is_array($dec)) $new_chashbackextend_agent = $dec;
    }
    
    $updates_affiliates = [];
    $params_affiliates = [];
    
    if (isset($_FILES['aff_media_file']) && $_FILES['aff_media_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['aff_media_file']['tmp_name'];
        $media_type = $_POST['aff_media_type'] ?? 'photo';
        $telegramMethod = ($media_type === 'video') ? 'sendVideo' : 'sendPhoto';
        $mediaField = ($media_type === 'video') ? 'video' : 'photo';
        
        $cFile = new CURLFile($fileTmpPath);
        $postData = [
            'chat_id' => $adminnumber,
            $mediaField => $cFile,
            'caption' => 'فایل رسانه راهنما با موفقیت در ربات آپلود شد.'
        ];
        
        $ch = curl_init("https://api.telegram.org/bot{$APIKEY}/{$telegramMethod}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $res = json_decode($response, true);
        if ($res && $res['ok']) {
            if ($media_type === 'video' && isset($res['result']['video']['file_id'])) {
                $_POST['aff_id_media'] = $res['result']['video']['file_id'];
            } elseif ($media_type === 'photo' && isset($res['result']['photo'])) {
                $photos = $res['result']['photo'];
                $_POST['aff_id_media'] = end($photos)['file_id'];
            }
        }
    }

    if (isset($_FILES['banner_base_file']) && $_FILES['banner_base_file']['error'] === UPLOAD_ERR_OK) {
        $assets_dir = __DIR__ . '/../assets';
        if (!is_dir($assets_dir)) {
            mkdir($assets_dir, 0777, true);
        }
        $dest_path = $assets_dir . '/banner_base.jpg';
        // Basic validation for image
        $mime = mime_content_type($_FILES['banner_base_file']['tmp_name']);
        if (strpos($mime, 'image/') === 0) {
            move_uploaded_file($_FILES['banner_base_file']['tmp_name'], $dest_path);
        }
    }

    foreach($_POST as $key => $val) {
        if(strpos($key, 'set_cron_') === 0) {
            $field = substr($key, 9);
            $new_cron_status[$field] = ($val === '1');
        } elseif(strpos($key, 'set_limitnumber_') === 0) {
            $field = substr($key, 16);
            $new_limitnumber[$field] = intval($val);
        } elseif(strpos($key, 'set_prize_') === 0) {
            $field = substr($key, 10);
            $new_lottery_prize[$field] = strval($val);
        } elseif(strpos($key, 'set_') === 0) {
            $field = substr($key, 4);
            $updates_setting[] = "$field = ?";
            $params_setting[] = $val;
        } elseif(strpos($key, 'pay_') === 0) {
            $field = substr($key, 4);
            if ($field === 'cardnumber') {
                $new_cardnumber = $val;
            } elseif ($field === 'namecard') {
                $new_namecard = $val;
            } elseif ($field === 'minbalance' || $field === 'maxbalance') {
                $old_json = db_fetch($pdo, "SELECT ValuePay FROM PaySetting WHERE NamePay = ?", [$field])['ValuePay'] ?? '';
                $decoded = json_decode($old_json, true);
                if (!is_array($decoded)) $decoded = [];
                $decoded['n'] = $val;
                $decoded['n2'] = $val;
                $decoded['allusers'] = $val;
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [json_encode($decoded), $field]);
            } elseif ($field === 'minbalancepaynotverify') {
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $field]);
                $old_json = db_fetch($pdo, "SELECT ValuePay FROM PaySetting WHERE NamePay = ?", ['minbalance'])['ValuePay'] ?? '';
                $decoded = json_decode($old_json, true);
                if (!is_array($decoded)) $decoded = [];
                $decoded['f'] = $val;
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [json_encode($decoded), 'minbalance']);
            } elseif ($field === 'maxbalancepaynotverify') {
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $field]);
                $old_json = db_fetch($pdo, "SELECT ValuePay FROM PaySetting WHERE NamePay = ?", ['maxbalance'])['ValuePay'] ?? '';
                $decoded = json_decode($old_json, true);
                if (!is_array($decoded)) $decoded = [];
                $decoded['f'] = $val;
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [json_encode($decoded), 'maxbalance']);
            } else {
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $field]);
            }
        } elseif(strpos($key, 'shop_chashbackextend_agent_') === 0) {
            $field = substr($key, 27);
            $new_chashbackextend_agent[$field] = intval($val);
        } elseif(strpos($key, 'shop_') === 0) {
            $field = substr($key, 5);
            db_query($pdo, "UPDATE shopSetting SET value = ? WHERE Namevalue = ?", [$val, $field]);
        } elseif(strpos($key, 'aff_') === 0) {
            $field = substr($key, 4);
            $updates_affiliates[] = "$field = ?";
            $params_affiliates[] = $val;
        }
    }
    
    $updates_setting[] = "cron_status = ?";
    $params_setting[] = json_encode($new_cron_status);
    
    $updates_setting[] = "limitnumber = ?";
    $params_setting[] = json_encode($new_limitnumber);
    
    $updates_setting[] = "Lottery_prize = ?";
    $params_setting[] = json_encode($new_lottery_prize);
    
    db_query($pdo, "UPDATE shopSetting SET value = ? WHERE Namevalue = ?", [json_encode($new_chashbackextend_agent), 'chashbackextend_agent']);
    
    if(!empty($updates_affiliates)) {
        db_query($pdo, "UPDATE affiliates SET " . implode(', ', $updates_affiliates), $params_affiliates);
    }
    
    if ($new_cardnumber !== null && $new_namecard !== null) {
        $old = db_fetch($pdo, "SELECT cardnumber, namecard FROM card_number LIMIT 1");
        $old_card = $old ? $old['cardnumber'] : null;
        if ($old_card !== $new_cardnumber || ($old && $old['namecard'] !== $new_namecard) || !$old) {
            db_query($pdo, "DELETE FROM card_number");
            if ($new_cardnumber) {
                db_query($pdo, "INSERT IGNORE INTO card_number (cardnumber, namecard) VALUES (?, ?)", [$new_cardnumber, $new_namecard]);
            }
        }
    }
    
    if(!empty($updates_setting)) {
        db_query($pdo, "UPDATE setting SET " . implode(', ', $updates_setting), $params_setting);
    }

    flash('success', $textbotlang['panel']['botSettingsSuccess'] ?? 'تنظیمات با موفقیت ذخیره شد.');
    $redirect_tab = $_POST['current_tab'] ?? 'general';
    $redirect_sec = $_POST['current_sec'] ?? '';
    header('Location: settings_affiliates.php?tab=' . urlencode($redirect_tab) . '&sec=' . urlencode($redirect_sec));
    exit;
}

$tab = $_GET['tab'] ?? 'affiliates';
if (!array_key_exists($tab, $schema)) {
    $tab = 'affiliates';
}

$sections = array_keys($schema[$tab]['sections']);
$sec = $_GET['sec'] ?? $sections[0];
if (!in_array($sec, $sections)) {
    $sec = $sections[0];
}

$pageTitle = $schema[$tab]['title'] ?? 'تنظیمات همکاری در فروش';
$activeNav = 'settings_affiliates';
include __DIR__ . '/inc/layout_head.php';
?>

<style>
.arvan-layout {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}
.arvan-main-tabs {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.arvan-main-tab-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background: var(--sf);
    border: 1px solid var(--bd);
    border-radius: 12px;
    color: var(--text2);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.95rem;
    white-space: nowrap;
    outline: none;
}
.arvan-main-tab-btn.active {
    background: var(--ac);
    color: var(--btn-ac-text, #fff);
    border-color: var(--ac);
    box-shadow: 0 4px 15px var(--acs);
}
.arvan-main-tab-btn:hover:not(.active) {
    background: var(--bg);
}

.arvan-tab-card {
    display: flex;
    flex-direction: column;
    background: var(--sf);
    border-radius: 16px;
    border: 1px solid var(--bd);
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    overflow: hidden;
}

.arvan-sidebar {
    background: var(--sf2);
    border-bottom: 1px solid var(--bd);
}

.arvan-sub-tabs {
    display: flex;
    overflow-x: auto;
    padding: 10px 15px;
    gap: 5px;
}
.arvan-sub-tab-btn {
    padding: 10px 18px;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: var(--text2);
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    white-space: nowrap;
    outline: none;
}
.arvan-sub-tab-btn.active {
    background: var(--ac);
    color: var(--btn-ac-text, #fff);
    font-weight: 600;
}
.arvan-sub-tab-btn:hover:not(.active) {
    background: var(--sf3);
    color: var(--text);
}

.arvan-content-area {
    flex: 1;
    min-width: 0;
    padding: 25px;
}

@media (min-width: 768px) {
    .arvan-tab-card {
        flex-direction: row;
        min-height: 500px;
    }
    .arvan-sidebar {
        width: 240px;
        flex-shrink: 0;
        border-bottom: none;
        border-left: 1px solid var(--bd);
    }
    .arvan-sub-tabs {
        flex-direction: column;
        padding: 20px 10px;
        gap: 8px;
        overflow-x: visible;
    }
    .arvan-sub-tab-btn {
        text-align: right;
        padding: 12px 15px;
    }
}

.arvan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}
.toggle-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: var(--sf);
    border-radius: 12px;
    border: 1px solid var(--bd);
    gap: 12px;
    text-align: right;
    transition: all 0.2s ease;
}
.toggle-field:hover {
    border-color: var(--ac);
    background: var(--sf2);
    box-shadow: 0 0 0 2px var(--acs);
}
.toggle-texts {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
}
.toggle-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
    line-height: 1.4;
    cursor: pointer;
}
.toggle-state {
    font-size: 0.72rem;
    color: var(--ac);
    font-weight: 500;
}

.arvan-select {
    width: 100%;
    padding: 10px 12px;
    padding-left: 35px;
    border-radius: 8px;
    border: 1.5px solid var(--bd);
    background: var(--sf2);
    color: var(--text);
    font-family: var(--font);
    font-size: 0.85rem;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    color-scheme: inherit;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: left 10px center;
    background-size: 15px;
    transition: all 0.2s ease;
    outline: 0;
}
.arvan-select:hover {
    border-color: var(--bds);
}
.arvan-select:focus {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px var(--acs);
}
.arvan-input {
    width: 100%;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1.5px solid var(--bd);
    background: var(--sf2);
    color: var(--text);
    font-family: var(--font);
    font-size: 0.85rem;
    transition: all 0.2s ease;
    outline: 0;
}
.arvan-input::placeholder {
    color: var(--dim);
}
.arvan-input:hover {
    border-color: var(--bds);
}
.arvan-input:focus {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px var(--acs);
}
/* Mobile Optimization & Responsive Rules */
.responsive-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}
.responsive-grid-sm {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
}

@media (max-width: 768px) {
    .arvan-content-area {
        padding: 16px;
    }
    
    /* Horizontal scrollable main tabs on mobile */
    .arvan-main-tabs {
        display: flex;
        flex-direction: row;
        overflow-x: auto;
        white-space: nowrap;
        gap: 8px;
        padding-bottom: 8px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .arvan-main-tabs::-webkit-scrollbar {
        display: none;
    }
    .arvan-main-tab-btn {
        flex: 0 0 auto;
        padding: 10px 16px;
        font-size: 0.85rem;
    }
    
    /* Responsive sub tabs grid on mobile */
    .arvan-sub-tabs {
        display: flex;
        flex-wrap: wrap;
        padding: 15px 12px;
        gap: 8px;
        border-bottom: 1px solid var(--bd);
        background: var(--sf2);
    }
    .arvan-sub-tab-btn {
        flex: 1 1 calc(50% - 8px);
        padding: 10px 12px;
        font-size: 0.8rem;
        border-radius: 8px;
        border: 1px solid var(--bd) !important;
        background: var(--sf);
        color: var(--text2);
        margin: 0;
        text-align: center;
        white-space: normal;
        line-height: 1.4;
    }
    .arvan-sub-tab-btn:last-child:nth-child(odd) {
        flex: 1 1 100%;
    }
    .arvan-sub-tab-btn.active {
        background: var(--ac);
        color: var(--btn-ac-text, #fff) !important;
        border-color: var(--ac) !important;
        font-weight: 600;
    }
    
    /* Grid adjustments */
    .arvan-grid, .responsive-grid, .responsive-grid-sm {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .field:not(.toggle-field) {
        grid-column: auto;
    }
    
    .tier-cards-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .toggle-field {
        padding: 12px 14px;
        gap: 8px;
    }
    .toggle-label {
        font-size: 0.82rem;
    }
    .toggle-state {
        font-size: 0.7rem;
    }
    
    .setting-group-box {
        padding: 14px;
        margin-bottom: 15px;
    }
    
    .tier-card {
        padding: 15px;
    }
}

/* Toggle Switch Styles */
.arvan-switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 26px;
    flex-shrink: 0;
    direction: ltr;
}
.arvan-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.arvan-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--sf3);
    transition: .3s ease;
    border-radius: 26px;
    border: 1px solid var(--bd);
}
.arvan-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background-color: #fff;
    transition: .3s ease;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
input:checked + .arvan-slider {
    background-color: var(--ac);
    border-color: var(--ac);
}
input:checked + .arvan-slider:before {
    transform: translateX(20px);
}
.section-desc-card {
    background: var(--sf2);
    border: 1px solid var(--bd);
    border-right: 4px solid var(--ac);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.section-desc-icon {
    color: var(--ac);
    flex-shrink: 0;
    margin-top: 2px;
    display: flex;
    align-items: center;
}
.section-desc-text {
    font-size: 0.85rem;
    color: var(--text2);
    line-height: 1.6;
    margin: 0;
    font-weight: 500;
}

/* Custom layout and component improvements */
.input-max-width {
    max-width: 480px;
    width: 100%;
}

.input-group-custom {
    display: flex;
    align-items: stretch;
    background: var(--sf2);
    border: 1.5px solid var(--bd);
    border-radius: 8px;
    transition: all 0.2s ease;
    overflow: hidden;
}
.input-group-custom:focus-within {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px var(--acs);
}
.input-group-custom .arvan-input {
    border: none !important;
    background: transparent !important;
    border-radius: 0 !important;
    padding: 10px 12px;
    width: 100%;
    margin: 0;
}
.input-group-custom .arvan-input:focus {
    box-shadow: none !important;
}
.input-group-badge {
    display: flex;
    align-items: center;
    background: var(--sf3);
    padding: 0 15px;
    font-size: 0.8rem;
    color: var(--text2);
    font-weight: 600;
    border-right: 1px solid var(--bd);
    white-space: nowrap;
}

/* Tier Cards styling */
.tier-cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 10px;
}
.tier-card {
    background: var(--sf2);
    border: 1px solid var(--bd);
    border-radius: 14px;
    padding: 20px;
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.tier-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
}
.tier-card.bronze {
    border-top: 4px solid #b45309;
}
.tier-card.silver {
    border-top: 4px solid #94a3b8;
}
.tier-card.gold {
    border-top: 4px solid #eab308;
}
.tier-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 1px dashed var(--bd);
    margin-bottom: 5px;
}
.tier-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.tier-card-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
}
.bronze .tier-card-badge {
    background: rgba(180, 83, 9, 0.15);
    color: #b45309;
}
.silver .tier-card-badge {
    background: rgba(148, 163, 184, 0.15);
    color: #94a3b8;
}
.gold .tier-card-badge {
    background: rgba(234, 179, 8, 0.15);
    color: #eab308;
}

/* Custom Upload Zone */
.custom-upload-zone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    min-height: 180px;
    background: var(--sf);
    border: 2px dashed var(--bd);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    text-align: center;
    gap: 12px;
}
.custom-upload-zone:hover {
    border-color: var(--ac);
    background: var(--sf2);
}
.custom-upload-zone input[type="file"] {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}
.upload-icon {
    color: var(--ac);
    opacity: 0.8;
    transition: transform 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.custom-upload-zone:hover .upload-icon {
    transform: translateY(-3px);
    opacity: 1;
}
.upload-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}
.upload-subtitle {
    font-size: 0.72rem;
    color: var(--dim);
    margin: 0;
}

/* Nested section groups */
.setting-group-box {
    background: var(--sf);
    border: 1px solid var(--bd);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}
.setting-group-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text2);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid var(--bd);
    padding-bottom: 8px;
}
</style>

<div class="fade-up">
    <!-- Main Tabs -->
    <div class="arvan-main-tabs" style="<?= count($schema) > 1 ? 'display: flex;' : 'display: none;' ?>">
        <?php foreach ($schema as $key => $tab_data): ?>
            <button type="button" class="arvan-main-tab-btn <?= $tab === $key ? 'active' : '' ?>" data-tab="<?= $key ?>">
                <?= icon($tab_data['icon'] ?? 'settings', 22) ?>
                <span style="font-weight: 600;"><?= $tab_data['title'] ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <form method="POST" id="settingsForm" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="current_tab" id="current_tab_input" value="<?= htmlspecialchars($tab) ?>">
        <input type="hidden" name="current_sec" id="current_sec_input" value="<?= htmlspecialchars($sec) ?>">
        
        <?php foreach ($schema as $key => $tab_data): ?>
            <div class="arvan-tab-content" id="tab-content-<?= $key ?>" style="display: <?= $tab === $key ? 'block' : 'none' ?>;">
                
                <div class="arvan-tab-card">
                    <!-- Sidebar Sub Tabs -->
                    <div class="arvan-sidebar">
                        <div class="arvan-sub-tabs">
                            <?php $isFirstSec = true; foreach ($tab_data['sections'] as $section_title => $fields): ?>
                                <?php 
                                    $isActiveSec = false;
                                    if ($tab === $key && $sec === $section_title) $isActiveSec = true;
                                    elseif ($tab !== $key && $isFirstSec && $sec === '') $isActiveSec = true;
                                    elseif ($tab !== $key && $isFirstSec && !isset($schema[$key]['sections'][$sec])) $isActiveSec = true;
                                ?>
                                <button type="button" class="arvan-sub-tab-btn <?= $isActiveSec ? 'active' : '' ?>" data-tab="<?= $key ?>" data-sec="<?= htmlspecialchars($section_title) ?>">
                                    <?= htmlspecialchars($section_title) ?>
                                </button>
                            <?php $isFirstSec = false; endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="arvan-content-area">
                        <?php $isFirstSec = true; foreach ($tab_data['sections'] as $section_title => $fields): ?>
                            <?php 
                                $isActiveSec = false;
                                if ($tab === $key && $sec === $section_title) $isActiveSec = true;
                                elseif ($tab !== $key && $isFirstSec && $sec === '') $isActiveSec = true;
                                elseif ($tab !== $key && $isFirstSec && !isset($schema[$key]['sections'][$sec])) $isActiveSec = true;
                            ?>
                            <div class="arvan-section-content" data-tab="<?= $key ?>" data-sec="<?= htmlspecialchars($section_title) ?>" style="display: <?= $isActiveSec ? 'block' : 'none' ?>;">
                                <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 20px; color: var(--text);"><?= htmlspecialchars($section_title) ?></h3>
                                <?php
                                $section_descriptions = [
                                    'سیستم پورسانت' => 'تنظیمات پایه سیستم همکاری در فروش. در این بخش می‌توانید کل سیستم بازاریابی و همچنین وضعیت پورسانت‌دهی با خرید کاربر را فعال یا غیرفعال کنید.',
                                    'پاداش اولین خرید زیرمجموعه' => 'مبلغ پاداش اولین خرید: هنگامی که زیرمجموعه برای اولین بار از ربات خرید می‌کند، این مبلغ ثابت به صورت نقدی به کیف پول معرف او واریز می‌شود (مثلاً ۵۰,۰۰۰ تومان). برای خاموش کردن، مقدار را روی ۰ قرار دهید.',
                                    'سطوح بازاریابی' => 'تنظیمات مربوط به درصدهای پورسانت بر اساس سه سطح برنزی (پیش‌فرض)، نقره‌ای و طلایی. کاربران به صورت خودکار با رسیدن به تعداد خریدهای موفق مشخص، به سطوح بالاتر ارتقا می‌یابند.',
                                    'تخفیف و رسانه' => 'تنظیمات مربوط به بنر تبلیغاتی آماده و هدیه خوش‌آمدگویی. با فعال‌سازی این بخش، کاربر جدید و معرفش بابت عضویت هدیه نقدی دریافت می‌کنند. همچنین بنر و رسانه راهنما در زمان دریافت لینک بازاریابی به کاربر نمایش داده می‌شود. (نکته: برای دریافت شناسه رسانه، فایل عکس یا ویدیو خود را ابتدا به ربات ارسال کرده و File ID خروجی آن را در کادر زیر وارد کنید.)'
                                ];
                                $desc = $section_descriptions[$section_title] ?? '';
                                ?>
                                <?php if ($desc): ?>
                                    <div class="section-desc-card">
                                        <div class="section-desc-icon">
                                            <?= icon('info', 18) ?>
                                        </div>
                                        <p class="section-desc-text"><?= htmlspecialchars($desc) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($section_title === 'سیستم پورسانت'): ?>
                                    <div class="responsive-grid">
                                        <?php foreach($fields as $f): ?>
                                            <?php 
                                            $keys = array_keys($f['options']);
                                            $val1 = $keys[0]; 
                                            $val2 = $keys[1]; 
                                            $currentVal = (strval($f['val']) === strval($val1)) ? $val1 : $val2;
                                            $isChecked = ($currentVal === $val1);
                                            ?>
                                            <div class="field toggle-field">
                                                <div class="toggle-texts">
                                                    <label class="toggle-label" for="chk_<?= $f['name'] ?>"><?= $f['label'] ?></label>
                                                    <span class="toggle-state"><?= $isChecked ? $f['options'][$val1] : $f['options'][$val2] ?></span>
                                                </div>
                                                <input type="hidden" name="<?= $f['name'] ?>" id="hidden_<?= $f['name'] ?>" value="<?= htmlspecialchars($currentVal) ?>">
                                                <label class="arvan-switch">
                                                    <input type="checkbox" id="chk_<?= $f['name'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="document.getElementById('hidden_<?= $f['name'] ?>').value = this.checked ? '<?= $val1 ?>' : '<?= $val2 ?>'; this.closest('.toggle-field').querySelector('.toggle-state').innerText = this.checked ? '<?= $f['options'][$val1] ?>' : '<?= $f['options'][$val2] ?>';">
                                                    <span class="arvan-slider"></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                <?php elseif ($section_title === 'پاداش اولین خرید زیرمجموعه'): ?>
                                    <?php $f = $fields[0]; ?>
                                    <div class="field input-max-width" style="display: flex; flex-direction: column; gap: 8px;">
                                        <label style="font-weight: 600; color: var(--text2); font-size: 0.85rem;"><?= htmlspecialchars($f['label']) ?></label>
                                        <div class="input-group-custom">
                                            <input type="number" name="<?= $f['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($f['val'] ?? '') ?>" placeholder="<?= htmlspecialchars($f['placeholder'] ?? '') ?>">
                                            <span class="input-group-badge">تومان</span>
                                        </div>
                                    </div>

                                <?php elseif ($section_title === 'سطوح بازاریابی'): ?>
                                    <div class="tier-cards-container">
                                        <!-- Bronze Tier Card -->
                                        <div class="tier-card bronze">
                                            <div class="tier-card-header">
                                                <span class="tier-card-title">
                                                    <?= icon('award', 18) ?> سطح برنزی (Bronze Tier)
                                                </span>
                                                <span class="tier-card-badge">برنزی (پیش‌فرض)</span>
                                            </div>
                                            
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;">حداقل خرید زیرمجموعه برای سطح برنزی</label>
                                                <div class="input-group-custom" style="opacity: 0.7;">
                                                    <input type="text" class="arvan-input" value="0 (پیش‌فرض)" readonly disabled>
                                                    <span class="input-group-badge">عدد خرید</span>
                                                </div>
                                            </div>
                                            
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;"><?= htmlspecialchars($fields[0]['label']) ?></label>
                                                <div class="input-group-custom">
                                                    <input type="number" name="<?= $fields[0]['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($fields[0]['val'] ?? '') ?>">
                                                    <span class="input-group-badge">% درصد</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Silver Tier Card -->
                                        <div class="tier-card silver">
                                            <div class="tier-card-header">
                                                <span class="tier-card-title">
                                                    <?= icon('award', 18) ?> سطح نقره‌ای (Silver Tier)
                                                </span>
                                                <span class="tier-card-badge">نقره‌ای</span>
                                            </div>
                                            
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;"><?= htmlspecialchars($fields[1]['label']) ?></label>
                                                <div class="input-group-custom">
                                                    <input type="number" name="<?= $fields[1]['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($fields[1]['val'] ?? '') ?>">
                                                    <span class="input-group-badge">عدد خرید</span>
                                                </div>
                                            </div>
                                            
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;"><?= htmlspecialchars($fields[2]['label']) ?></label>
                                                <div class="input-group-custom">
                                                    <input type="number" name="<?= $fields[2]['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($fields[2]['val'] ?? '') ?>">
                                                    <span class="input-group-badge">% درصد</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Gold Tier Card -->
                                        <div class="tier-card gold">
                                            <div class="tier-card-header">
                                                <span class="tier-card-title">
                                                    <?= icon('award', 18) ?> سطح طلایی (Gold Tier)
                                                </span>
                                                <span class="tier-card-badge">طلایی</span>
                                            </div>
                                            
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;"><?= htmlspecialchars($fields[3]['label']) ?></label>
                                                <div class="input-group-custom">
                                                    <input type="number" name="<?= $fields[3]['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($fields[3]['val'] ?? '') ?>">
                                                    <span class="input-group-badge">عدد خرید</span>
                                                </div>
                                            </div>
                                            
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;"><?= htmlspecialchars($fields[4]['label']) ?></label>
                                                <div class="input-group-custom">
                                                    <input type="number" name="<?= $fields[4]['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($fields[4]['val'] ?? '') ?>">
                                                    <span class="input-group-badge">% درصد</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php elseif ($section_title === 'بنر اختصاصی زیرمجموعه‌گیری'): ?>
                                    <?php $f = $fields[0]; ?>
                                    <div class="field input-max-width" style="display: flex; flex-direction: column; gap: 10px;">
                                        <label style="font-weight: 600; color: var(--text2); font-size: 0.85rem;"><?= htmlspecialchars($f['label']) ?></label>
                                        
                                        <div class="custom-upload-zone" onclick="document.getElementById('<?= $f['name'] ?>_input').click();">
                                            <div class="upload-icon">
                                                <?= icon('upload-cloud', 32) ?>
                                            </div>
                                            <p class="upload-title">برای تغییر تصویر کلیک کنید یا فایل را بکشید اینجا</p>
                                            <p class="upload-subtitle">فرمت مورد تایید: JPG / JPEG</p>
                                            <input type="file" id="<?= $f['name'] ?>_input" name="<?= $f['name'] ?>" accept="image/jpeg,image/jpg" style="display: none;" onchange="updateUploadZoneText(this)">
                                        </div>
                                        
                                        <?php if (file_exists(__DIR__ . '/../assets/banner_base.jpg')): ?>
                                            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 8px; font-size: 0.8rem; color: var(--emerald);">
                                                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                                    <span>✅ تصویر پایه بنر با موفقیت آپلود شده و فعال است.</span>
                                                    <a href="../assets/banner_base.jpg" target="_blank" style="color: var(--blue); text-decoration: underline; font-weight: 600;">مشاهده اندازه اصلی</a>
                                                </div>
                                                <div style="display: flex; justify-content: center; width: 100%;">
                                                    <img src="../assets/banner_base.jpg?t=<?= time() ?>" style="max-width: 100%; max-height: 140px; border-radius: 6px; border: 1px solid var(--bd); object-fit: contain;">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                <?php elseif ($section_title === 'تخفیف و رسانه'): ?>
                                    <!-- Group 1: Discount Settings -->
                                    <div class="setting-group-box">
                                        <div class="setting-group-title">
                                            <?= icon('percent', 16) ?> تنظیمات کد تخفیف معرف
                                        </div>
                                        <div class="responsive-grid-sm" style="align-items: start;">
                                            <!-- aff_Discount -->
                                            <?php 
                                            $disc_field = $fields[0]; 
                                            $keys = array_keys($disc_field['options']);
                                            $val1 = $keys[0]; 
                                            $val2 = $keys[1]; 
                                            $currentVal = (strval($disc_field['val']) === strval($val1)) ? $val1 : $val2;
                                            $isChecked = ($currentVal === $val1);
                                            ?>
                                            <div class="field toggle-field" style="margin-top: 25px;">
                                                <div class="toggle-texts">
                                                    <label class="toggle-label" for="chk_<?= $disc_field['name'] ?>"><?= $disc_field['label'] ?></label>
                                                    <span class="toggle-state"><?= $isChecked ? $disc_field['options'][$val1] : $disc_field['options'][$val2] ?></span>
                                                </div>
                                                <input type="hidden" name="<?= $disc_field['name'] ?>" id="hidden_<?= $disc_field['name'] ?>" value="<?= htmlspecialchars($currentVal) ?>">
                                                <label class="arvan-switch">
                                                    <input type="checkbox" id="chk_<?= $disc_field['name'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="document.getElementById('hidden_<?= $disc_field['name'] ?>').value = this.checked ? '<?= $val1 ?>' : '<?= $val2 ?>'; this.closest('.toggle-field').querySelector('.toggle-state').innerText = this.checked ? '<?= $disc_field['options'][$val1] ?>' : '<?= $disc_field['options'][$val2] ?>';">
                                                    <span class="arvan-slider"></span>
                                                </label>
                                            </div>

                                            <!-- aff_price_Discount -->
                                            <?php $price_field = $fields[1]; ?>
                                            <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                                                <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;"><?= htmlspecialchars($price_field['label']) ?></label>
                                                <div class="input-group-custom">
                                                    <input type="number" name="<?= $price_field['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($price_field['val'] ?? '') ?>">
                                                    <span class="input-group-badge">تومان / %</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Group 2: Help Media -->
                                    <div class="setting-group-box">
                                        <div class="setting-group-title">
                                            <?= icon('image', 16) ?> رسانه راهنما
                                        </div>
                                        <div class="responsive-grid" style="align-items: start;">
                                            <div style="display: flex; flex-direction: column; gap: 15px;">
                                                <!-- aff_media_type -->
                                                <?php 
                                                $mtype_field = $fields[2]; 
                                                $keys = array_keys($mtype_field['options']);
                                                $val1 = $keys[0]; 
                                                $val2 = $keys[1]; 
                                                $currentVal = (strval($mtype_field['val']) === strval($val1)) ? $val1 : $val2;
                                                $isChecked = ($currentVal === $val1);
                                                ?>
                                                <div class="field toggle-field">
                                                    <div class="toggle-texts">
                                                        <label class="toggle-label" for="chk_<?= $mtype_field['name'] ?>"><?= $mtype_field['label'] ?></label>
                                                        <span class="toggle-state"><?= $isChecked ? $mtype_field['options'][$val1] : $mtype_field['options'][$val2] ?></span>
                                                    </div>
                                                    <input type="hidden" name="<?= $mtype_field['name'] ?>" id="hidden_<?= $mtype_field['name'] ?>" value="<?= htmlspecialchars($currentVal) ?>">
                                                    <label class="arvan-switch">
                                                        <input type="checkbox" id="chk_<?= $mtype_field['name'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="document.getElementById('hidden_<?= $mtype_field['name'] ?>').value = this.checked ? '<?= $val1 ?>' : '<?= $val2 ?>'; this.closest('.toggle-field').querySelector('.toggle-state').innerText = this.checked ? '<?= $mtype_field['options'][$val1] ?>' : '<?= $mtype_field['options'][$val2] ?>';">
                                                        <span class="arvan-slider"></span>
                                                    </label>
                                                </div>

                                                <!-- aff_id_media -->
                                                <?php $mid_field = $fields[3]; ?>
                                                <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                                                    <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;"><?= htmlspecialchars($mid_field['label']) ?></label>
                                                    <input type="text" name="<?= $mid_field['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($mid_field['val'] ?? '') ?>" placeholder="<?= htmlspecialchars($mid_field['placeholder'] ?? '') ?>">
                                                </div>
                                            </div>

                                            <div>
                                                <!-- aff_media_file -->
                                                <?php $mfile_field = $fields[4]; ?>
                                                <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                                                    <label style="font-weight: 600; color: var(--text2); font-size: 0.8rem;"><?= htmlspecialchars($mfile_field['label']) ?></label>
                                                    <div class="custom-upload-zone" onclick="document.getElementById('<?= $mfile_field['name'] ?>_input').click();">
                                                        <div class="upload-icon">
                                                            <?= icon('upload-cloud', 24) ?>
                                                        </div>
                                                        <p class="upload-title" style="font-size:0.8rem;">آپلود مستقیم ویدیو / عکس</p>
                                                        <p class="upload-subtitle" style="font-size:0.68rem;">برای آپلود کلیک کنید</p>
                                                        <input type="file" id="<?= $mfile_field['name'] ?>_input" name="<?= $mfile_field['name'] ?>" accept="image/*,video/*" style="display: none;" onchange="updateUploadZoneText(this)">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Group 3: Help Description -->
                                    <div class="setting-group-box">
                                        <div class="setting-group-title">
                                            <?= icon('file-text', 16) ?> متن توضیحات راهنما
                                        </div>
                                        <?php $desc_field = $fields[5]; ?>
                                        <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                                            <textarea name="<?= $desc_field['name'] ?>" class="arvan-input" style="min-height: 380px; font-size: 0.95rem; line-height: 1.6; resize: vertical;" placeholder="<?= htmlspecialchars($desc_field['placeholder'] ?? '') ?>"><?= htmlspecialchars($desc_field['val'] ?? '') ?></textarea>
                                        </div>
                                    </div>

                                <?php else: ?>
                                    <div class="arvan-grid">
                                        <?php foreach($fields as $f): ?>
                                            <?php if($f['type'] === 'select'): 
                                                $keys = array_keys($f['options']);
                                                $val1 = $keys[0]; 
                                                $val2 = $keys[1]; 
                                                $currentVal = (strval($f['val']) === strval($val1)) ? $val1 : $val2;
                                                $isChecked = ($currentVal === $val1);
                                            ?>
                                                <div class="field toggle-field">
                                                    <div class="toggle-texts">
                                                        <label class="toggle-label" for="chk_<?= $f['name'] ?>"><?= $f['label'] ?></label>
                                                        <span class="toggle-state"><?= $isChecked ? $f['options'][$val1] : $f['options'][$val2] ?></span>
                                                    </div>
                                                    <input type="hidden" name="<?= $f['name'] ?>" id="hidden_<?= $f['name'] ?>" value="<?= htmlspecialchars($currentVal) ?>">
                                                    <label class="arvan-switch">
                                                        <input type="checkbox" id="chk_<?= $f['name'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="document.getElementById('hidden_<?= $f['name'] ?>').value = this.checked ? '<?= $val1 ?>' : '<?= $val2 ?>'; this.closest('.toggle-field').querySelector('.toggle-state').innerText = this.checked ? '<?= $f['options'][$val1] ?>' : '<?= $f['options'][$val2] ?>';">
                                                        <span class="arvan-slider"></span>
                                                    </label>
                                                </div>
                                            <?php elseif($f['type'] === 'text' || $f['type'] === 'number'): ?>
                                                <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                                                    <label class="field" style="font-weight: 600; color: var(--text2); font-size: 0.78rem;"><?= htmlspecialchars($f['label']) ?></label>
                                                    <input type="<?= $f['type'] ?>" name="<?= $f['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($f['val'] ?? '') ?>" placeholder="<?= htmlspecialchars($f['placeholder'] ?? '') ?>">
                                                </div>
                                            <?php elseif($f['type'] === 'textarea'): ?>
                                                <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                                                    <label class="field" style="font-weight: 600; color: var(--text2); font-size: 0.78rem;"><?= htmlspecialchars($f['label']) ?></label>
                                                    <textarea name="<?= $f['name'] ?>" class="arvan-input" style="min-height: 100px; resize: vertical;" placeholder="<?= htmlspecialchars($f['placeholder'] ?? '') ?>"><?= htmlspecialchars($f['val'] ?? '') ?></textarea>
                                                </div>
                                            <?php elseif($f['type'] === 'file'): ?>
                                                <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                                                    <label class="field" style="font-weight: 600; color: var(--text2); font-size: 0.78rem;"><?= htmlspecialchars($f['label']) ?></label>
                                                    <input type="<?= $f['type'] ?>" name="<?= $f['name'] ?>" class="arvan-input" accept="image/*,video/*">
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php $isFirstSec = false; endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 35px; font-size: 1rem; border-radius: 8px; display:flex; align-items:center; gap:8px;">
                <?= icon('check', 18) ?> ذخیره تغییرات
            </button>
        </div>
    </form>
</div>

<script>
window.updateUploadZoneText = function(input) {
    if (input.files && input.files[0]) {
        const zone = input.closest('.field').querySelector('.custom-upload-zone');
        const title = zone.querySelector('.upload-title');
        title.innerHTML = "📝 انتخاب شد: " + input.files[0].name;
        title.style.color = "var(--ac)";
        
        // Remove existing preview if any
        const oldPreview = zone.querySelector('.preview-thumb');
        if (oldPreview) {
            oldPreview.remove();
        }
        
        // Add visual preview if it is an image
        if (input.files[0].type.startsWith('image/')) {
            const img = document.createElement('img');
            img.className = 'preview-thumb';
            img.src = URL.createObjectURL(input.files[0]);
            img.style.maxWidth = '100%';
            img.style.maxHeight = '100px';
            img.style.borderRadius = '6px';
            img.style.marginTop = '10px';
            img.style.border = '1px solid var(--bd)';
            img.style.objectFit = 'contain';
            zone.appendChild(img);
        }
    }
};
(function() {
    const mainTabs = document.querySelectorAll('.arvan-main-tab-btn');
    const subTabs = document.querySelectorAll('.arvan-sub-tab-btn');
    const tabContents = document.querySelectorAll('.arvan-tab-content');
    const secContents = document.querySelectorAll('.arvan-section-content');
    
    mainTabs.forEach(btn => {
        btn.onclick = function() {
            mainTabs.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const targetTab = this.getAttribute('data-tab');
            document.getElementById('current_tab_input').value = targetTab;
            
            tabContents.forEach(c => c.style.display = 'none');
            const targetContent = document.getElementById('tab-content-' + targetTab);
            if(targetContent) targetContent.style.display = 'block';
            
            const targetSubTabs = document.querySelectorAll(`.arvan-sub-tab-btn[data-tab="${targetTab}"]`);
            if (targetSubTabs.length > 0) {
                const activeSubTab = Array.from(targetSubTabs).find(st => st.classList.contains('active'));
                if (!activeSubTab) {
                    targetSubTabs[0].click();
                } else {
                    document.getElementById('current_sec_input').value = activeSubTab.getAttribute('data-sec');
                }
            }
        };
    });
    
    subTabs.forEach(btn => {
        btn.onclick = function() {
            const targetTab = this.getAttribute('data-tab');
            const targetSec = this.getAttribute('data-sec');
            
            const siblingTabs = document.querySelectorAll(`.arvan-sub-tab-btn[data-tab="${targetTab}"]`);
            siblingTabs.forEach(b => b.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById('current_sec_input').value = targetSec;
            
            const siblingContents = document.querySelectorAll(`.arvan-section-content[data-tab="${targetTab}"]`);
            siblingContents.forEach(c => c.style.display = 'none');
            
            const targetContent = Array.from(siblingContents).find(c => c.getAttribute('data-sec') === targetSec);
            if (targetContent) {
                targetContent.style.display = 'block';
            }
        };
    });
})();
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>

