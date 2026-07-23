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
    'shop' => [
        'title' => 'فروشگاه',
        'icon' => 'package',
        'sections' => [
            'تنظیمات عمومی فروشگاه' => [
                ['name' => 'set_bulkbuy', 'label' => 'خرید عمده', 'type' => 'select', 'options' => ['onbulk' => 'مجاز', 'offbulk' => 'غیرمجاز'], 'val' => $row['bulkbuy'] ?? ''],
                ['name' => 'shop_minbalancebuybulk', 'label' => 'حداقل موجودی خرید عمده', 'type' => 'number', 'val' => $shop_settings['minbalancebuybulk'] ?? '0'],
                ['name' => 'set_statuscategory', 'label' => 'دسته‌بندی در فروشگاه', 'type' => 'select', 'options' => ['oncategory' => 'فعال', 'offcategory' => 'غیرفعال'], 'val' => $row['statuscategory'] ?? ''],
                ['name' => 'set_statuscategorygenral', 'label' => 'دسته‌بندی سراسری', 'type' => 'select', 'options' => ['oncategorys' => 'فعال', 'offcategorys' => 'غیرفعال'], 'val' => $row['statuscategorygenral'] ?? ''],
                ['name' => 'set_statusterffh', 'label' => 'نمایش لیست تعرفه‌ها', 'type' => 'select', 'options' => ['onterffh' => 'فعال', 'offterffh' => 'غیرفعال'], 'val' => $row['statusterffh'] ?? ''],
                ['name' => 'shop_statusdirectpabuy', 'label' => 'پرداخت مستقیم (بدون شارژ)', 'type' => 'select', 'options' => ['ondirectbuy' => 'فعال', 'offdirectbuy' => 'غیرفعال'], 'val' => $shop_settings['statusdirectpabuy'] ?? ''],
                ['name' => 'shop_statusdisorder', 'label' => 'وضعیت اختلال فروشگاه', 'type' => 'select', 'options' => ['ondisorder' => 'اختلال', 'offdisorder' => 'عادی'], 'val' => $shop_settings['statusdisorder'] ?? ''],
                ['name' => 'shop_statuschangeservice', 'label' => 'امکان تغییر سرویس', 'type' => 'select', 'options' => ['onstatus' => 'مجاز', 'offstatus' => 'غیرمجاز'], 'val' => $shop_settings['statuschangeservice'] ?? ''],
                ['name' => 'shop_statusshowprice', 'label' => 'نمایش قیمت‌ها', 'type' => 'select', 'options' => ['onshowprice' => 'نمایش', 'offshowprice' => 'مخفی'], 'val' => $shop_settings['statusshowprice'] ?? ''],
                ['name' => 'shop_configshow', 'label' => 'نمایش کانفیگ پس از خرید', 'type' => 'select', 'options' => ['onconfig' => 'نمایش', 'offconfig' => 'عدم نمایش'], 'val' => $shop_settings['configshow'] ?? ''],
                ['name' => 'shop_backserviecstatus', 'label' => 'بازگشت سرویس به فروشگاه', 'type' => 'select', 'options' => ['on' => 'فعال', 'off' => 'غیرفعال'], 'val' => $shop_settings['backserviecstatus'] ?? ''],
                ['name' => 'set_Debtsettlement', 'label' => 'تسویه حساب بدهی', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['Debtsettlement'] ?? ''],
                ['name' => 'set_statuslimitchangeloc', 'label' => 'محدودیت تغییر لوکیشن', 'type' => 'select', 'options' => ['1' => 'فعال', '0' => 'غیرفعال'], 'val' => $row['statuslimitchangeloc'] ?? ''],
            ],
            'حجم و زمان اضافه (اکسترا)' => [
                ['name' => 'shop_chashbackextend', 'label' => 'درصد کش‌بک تمدید', 'type' => 'number', 'val' => $shop_settings['chashbackextend'] ?? '0'],
                ['name' => 'shop_statusextra', 'label' => 'فروش حجم اضافه', 'type' => 'select', 'options' => ['onextra' => 'فعال', 'offextra' => 'غیرفعال'], 'val' => $shop_settings['statusextra'] ?? ''],
                ['name' => 'shop_statustimeextra', 'label' => 'فروش زمان اضافه', 'type' => 'select', 'options' => ['ontimeextraa' => 'فعال', 'offtimeextraa' => 'غیرفعال'], 'val' => $shop_settings['statustimeextra'] ?? ''],
                ['name' => 'shop_customvolmef', 'label' => 'قیمت حجم اضافه (فروشنده عادی)', 'type' => 'number', 'val' => $shop_settings['customvolmef'] ?? ''],
                ['name' => 'shop_customvolmen', 'label' => 'قیمت حجم اضافه (نماینده)', 'type' => 'number', 'val' => $shop_settings['customvolmen'] ?? ''],
                ['name' => 'shop_customvolmen2', 'label' => 'قیمت حجم اضافه (نماینده پیشرفته)', 'type' => 'number', 'val' => $shop_settings['customvolmen2'] ?? ''],
                ['name' => 'shop_customtimepricef', 'label' => 'قیمت زمان اضافه (فروشنده عادی)', 'type' => 'number', 'val' => $shop_settings['customtimepricef'] ?? ''],
                ['name' => 'shop_customtimepricen', 'label' => 'قیمت زمان اضافه (نماینده)', 'type' => 'number', 'val' => $shop_settings['customtimepricen'] ?? ''],
                ['name' => 'shop_customtimepricen2', 'label' => 'قیمت زمان اضافه (نماینده پیشرفته)', 'type' => 'number', 'val' => $shop_settings['customtimepricen2'] ?? ''],
                ['name' => 'shop_chashbackextend_agent_n', 'label' => 'کش‌بک تمدید نماینده', 'type' => 'number', 'val' => $shop_settings['chashbackextend_agent_n'] ?? '0'],
                ['name' => 'shop_chashbackextend_agent_n2', 'label' => 'کش‌بک تمدید نماینده پیشرفته', 'type' => 'number', 'val' => $shop_settings['chashbackextend_agent_n2'] ?? '0'],
                ['name' => 'shop_price_reset_agent', 'label' => 'هزینه ریست ترافیک نماینده (تومان)', 'type' => 'number', 'val' => $shop_settings['price_reset_agent'] ?? '5000'],
            ]
        ]
    ]
];

// Ensure price_reset_agent exists in shopSetting table
try {
    $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue, value) VALUES ('price_reset_agent', '5000')");
} catch (Exception $e) {}

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
    header('Location: settings_shop.php?tab=' . urlencode($redirect_tab) . '&sec=' . urlencode($redirect_sec));
    exit;
}

$tab = $_GET['tab'] ?? 'shop';
if (!array_key_exists($tab, $schema)) {
    $tab = 'shop';
}

$sections = array_keys($schema[$tab]['sections']);
$sec = $_GET['sec'] ?? $sections[0];
if (!in_array($sec, $sections)) {
    $sec = $sections[0];
}

$pageTitle = 'تنظیمات فروشگاه';
$activeNav = 'settings_shop';
include __DIR__ . '/inc/layout_head.php';
?>

<style>
.arvan-layout {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}
.arvan-tab-card {
    display: flex;
    flex-direction: column;
    background: var(--bg-sec);
    border-radius: 20px;
    border: 1px solid var(--bd);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.arvan-sidebar {
    background: var(--bg);
    border-bottom: 1px solid var(--bd);
}

.arvan-sub-tabs {
    display: flex;
    overflow-x: auto;
    padding: 12px 16px;
    gap: 8px;
}
.arvan-sub-tab-btn {
    padding: 12px 20px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    color: var(--ts);
    cursor: pointer;
    transition: all 0.25s ease;
    font-size: 0.9rem;
    font-weight: 600;
    white-space: nowrap;
    outline: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
.arvan-sub-tab-btn:hover:not(.active) {
    background: var(--bg-sec);
    color: var(--fg);
}
.arvan-sub-tab-btn.active {
    background: rgba(var(--ac-rgb, 59,130,246), 0.12);
    color: var(--ac);
    border-color: rgba(var(--ac-rgb, 59,130,246), 0.25);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}

.arvan-content-area {
    flex: 1;
    min-width: 0;
    padding: 28px;
}

@media (min-width: 768px) {
    .arvan-tab-card {
        flex-direction: row;
        min-height: 520px;
    }
    .arvan-sidebar {
        width: 260px;
        flex-shrink: 0;
        border-bottom: none;
        border-left: 1px solid var(--bd);
        padding: 16px 12px;
    }
    .arvan-sub-tabs {
        flex-direction: column;
        padding: 0;
        gap: 6px;
        overflow-x: visible;
    }
    .arvan-sub-tab-btn {
        text-align: right;
        justify-content: flex-start;
        width: 100%;
        border-radius: 12px;
    }
    .arvan-sub-tab-btn.active {
        border-right: 3px solid var(--ac);
    }
}

.arvan-section-title {
    font-size: 1.15rem;
    font-weight: 800;
    margin-bottom: 24px;
    color: var(--fg);
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 1px dashed var(--bd);
}

.arvan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    gap: 16px;
}

.toggle-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px;
    background: var(--bg);
    border-radius: 14px;
    border: 1px solid var(--bd);
    gap: 14px;
    text-align: right;
    transition: all 0.25s ease;
    user-select: none;
}
.toggle-field:hover {
    border-color: rgba(var(--ac-rgb, 59,130,246), 0.35);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
    transform: translateY(-1px);
}
.toggle-texts {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-start;
    flex: 1;
}
.toggle-label {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--fg);
    margin: 0;
    line-height: 1.4;
    cursor: pointer;
}
.toggle-state {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-block;
    transition: all 0.2s ease;
}
.toggle-state.state-on {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.toggle-state.state-off {
    background: rgba(148, 163, 184, 0.12);
    color: #94a3b8;
    border: 1px solid rgba(148, 163, 184, 0.2);
}

.input-field-block {
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: var(--bg);
    border: 1px solid var(--bd);
    border-radius: 14px;
    padding: 16px;
    transition: all 0.25s ease;
}
.input-field-block:hover {
    border-color: rgba(var(--ac-rgb, 59,130,246), 0.35);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
}
.input-field-label {
    font-weight: 700;
    color: var(--fg);
    font-size: 0.88rem;
}
.arvan-input {
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid var(--bd);
    background: var(--bg-sec);
    color: var(--fg);
    font-family: inherit;
    font-size: 0.92rem;
    font-weight: 600;
    transition: all 0.25s ease;
    outline: 0;
    box-sizing: border-box;
}
.arvan-input:focus {
    border-color: var(--ac);
    box-shadow: 0 0 0 3px rgba(var(--ac-rgb, 59,130,246), 0.2);
    background: var(--bg);
}

/* iOS Style Switch */
.arvan-switch {
    position: relative;
    display: inline-block;
    width: 48px;
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
    background-color: rgba(148, 163, 184, 0.2);
    transition: .3s cubic-bezier(0.16, 1, 0.3, 1);
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
    transition: .3s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
input:checked + .arvan-slider {
    background-color: var(--ac);
    border-color: var(--ac);
    box-shadow: 0 0 12px rgba(var(--ac-rgb, 59,130,246), 0.35);
}
input:checked + .arvan-slider:before {
    transform: translateX(22px);
}

@media (max-width: 600px) {
    .arvan-grid {
        grid-template-columns: 1fr !important;
        gap: 12px;
    }
    .arvan-content-area {
        padding: 16px;
    }
    .toggle-field {
        padding: 14px 16px;
    }
}
</style>

<div class="fade-up">
    <!-- Main Tabs -->
    <div class="arvan-main-tabs" style="display: none;">
        <?php foreach ($schema as $key => $tab_data): ?>
            <button type="button" class="arvan-main-tab-btn <?= $tab === $key ? 'active' : '' ?>" data-tab="<?= $key ?>">
                <?= icon($tab_data['icon'] ?? 'settings', 22) ?>
                <span style="font-weight: 600;"><?= $tab_data['title'] ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <form method="POST" id="settingsForm">
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
                                <div class="arvan-section-title">
                                    <?= htmlspecialchars($section_title) ?>
                                </div>
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
                                                    <span class="toggle-state <?= $isChecked ? 'state-on' : 'state-off' ?>"><?= $isChecked ? $f['options'][$val1] : $f['options'][$val2] ?></span>
                                                </div>
                                                <input type="hidden" name="<?= $f['name'] ?>" id="hidden_<?= $f['name'] ?>" value="<?= htmlspecialchars($currentVal) ?>">
                                                <label class="arvan-switch">
                                                    <input type="checkbox" id="chk_<?= $f['name'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="
                                                        document.getElementById('hidden_<?= $f['name'] ?>').value = this.checked ? '<?= $val1 ?>' : '<?= $val2 ?>';
                                                        const stateEl = this.closest('.toggle-field').querySelector('.toggle-state');
                                                        stateEl.innerText = this.checked ? '<?= $f['options'][$val1] ?>' : '<?= $f['options'][$val2] ?>';
                                                        stateEl.className = 'toggle-state ' + (this.checked ? 'state-on' : 'state-off');
                                                    ">
                                                    <span class="arvan-slider"></span>
                                                </label>
                                            </div>
                                        <?php elseif($f['type'] === 'text' || $f['type'] === 'number'): ?>
                                            <div class="input-field-block">
                                                <label class="input-field-label" for="input_<?= $f['name'] ?>"><?= htmlspecialchars($f['label']) ?></label>
                                                <input type="<?= $f['type'] ?>" name="<?= $f['name'] ?>" id="input_<?= $f['name'] ?>" class="arvan-input" value="<?= htmlspecialchars($f['val'] ?? '') ?>" placeholder="<?= htmlspecialchars($f['placeholder'] ?? '0') ?>" style="direction: ltr; text-align: center;">
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php $isFirstSec = false; endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 14px 40px; font-size: 1rem; border-radius: 12px; font-weight: 700; display:flex; align-items:center; gap:10px; box-shadow: 0 6px 20px rgba(var(--ac-rgb, 59,130,246), 0.35);">
                <?= icon('check', 20) ?> ذخیره تغییرات
            </button>
        </div>
    </form>
</div>

<script>
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

