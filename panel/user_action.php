<?php
require_once __DIR__ . '/inc/config.php';
require_auth();
csrf_check_get();

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

$allowed_back = ['users.php', 'user.php'];
$rawBack = $_GET['back'] ?? '';
$back = 'users.php'; 
foreach ($allowed_back as $allowed) {
    if (strpos($rawBack, $allowed) === 0) {
        
        $base = explode('?', $rawBack)[0];
        $back = $base . ($id ? "?id=$id" : '');
        break;
    }
}

if ($rawBack === 'users.php') $back = 'users.php';

if (!$id) {
    flash('error', $textbotlang['panel']['userActionInvalidUserId']);
    header('Location: users.php'); exit;
}

$user = db_fetch($pdo, "SELECT * FROM user WHERE id = ?", [$id]);
if (!$user) {
    flash('error', $textbotlang['panel']['userActionUserNotFound']);
    header('Location: users.php'); exit;
}

switch ($action) {
    case 'block':
        if ($user['User_Status'] === 'block') {
            flash('warning', $textbotlang['panel']['userActionUserAlreadyBlocked']);
        } else {
            db_query($pdo, "UPDATE user SET User_Status = 'block' WHERE id = ?", [$id]);
            flash('success', sprintf($textbotlang['panel']['userActionUserBlockedSuccess'], $id));
        }
        break;

    case 'unblock':
        if ($user['User_Status'] !== 'block') {
            flash('warning', $textbotlang['panel']['userActionUserIsActive']);
        } else {
            db_query($pdo, "UPDATE user SET User_Status = 'active' WHERE id = ?", [$id]);
            flash('success', sprintf($textbotlang['panel']['userActionUserUnblockedSuccess'], $id));
        }
        break;

    case 'zerobalance':
        db_query($pdo, "UPDATE user SET Balance = 0 WHERE id = ?", [$id]);
        flash('success', 'موجودی کاربر صفر شد.');
        break;

    case 'toggle_verify':
        $current = $user['verify'] ?? '1';
        $new = ($current === '1') ? '0' : '1';
        db_query($pdo, "UPDATE user SET verify = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت احراز هویت تغییر کرد.');
        break;

    case 'toggle_card':
        $current = $user['cardpayment'] ?? '1';
        $new = ($current === '1') ? '0' : '1';
        db_query($pdo, "UPDATE user SET cardpayment = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت نمایش شماره کارت تغییر کرد.');
        break;

    case 'verify_channel':
        $current = $user['joinchannel'] ?? '0';
        $new = ($current === '1') ? '0' : '1';
        db_query($pdo, "UPDATE user SET joinchannel = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت جوین اجباری تغییر کرد.');
        break;

    case 'toggle_cron':
        $current = $user['status_cron'] ?? '1';
        $new = ($current === '1') ? '0' : '1';
        db_query($pdo, "UPDATE user SET status_cron = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت کرون‌جاب کاربر تغییر کرد.');
        break;

    case 'removeaffiliates':
        db_query($pdo, "UPDATE user SET affiliates = '0' WHERE affiliates = ?", [$id]);
        flash('success', 'زیرمجموعه‌های این کاربر حذف شدند.');
        break;

    case 'toggle_bot':
        // Clear or set bot type restriction (you might need to adjust based on exact bot logic)
        $current = $user['bottype'] ?? '0';
        $new = ($current === '0') ? '1' : '0';
        db_query($pdo, "UPDATE user SET bottype = ? WHERE id = ?", [$new, $id]);
        flash('success', 'وضعیت ربات کاربر تغییر کرد.');
        break;

    case 'set_vol_price':
        $price = intval($_POST['price'] ?? 0);
        $bot_info_row = db_fetch($pdo, "SELECT setting FROM botsaz WHERE id_user = ?", [$id]);
        if ($bot_info_row) {
            $bot_info = json_decode($bot_info_row['setting'], true) ?: [];
            $bot_info['minpricevolume'] = $price;
            db_query($pdo, "UPDATE botsaz SET setting = ? WHERE id_user = ?", [json_encode($bot_info), $id]);
            flash('success', 'قیمت پایه حجم با موفقیت تغییر کرد.');
        } else {
            flash('error', 'کاربر هنوز ربات فعالی ندارد.');
        }
        break;

    case 'set_time_price':
        $price = intval($_POST['price'] ?? 0);
        $bot_info_row = db_fetch($pdo, "SELECT setting FROM botsaz WHERE id_user = ?", [$id]);
        if ($bot_info_row) {
            $bot_info = json_decode($bot_info_row['setting'], true) ?: [];
            $bot_info['minpricetime'] = $price;
            db_query($pdo, "UPDATE botsaz SET setting = ? WHERE id_user = ?", [json_encode($bot_info), $id]);
            flash('success', 'قیمت پایه زمان با موفقیت تغییر کرد.');
        } else {
            flash('error', 'کاربر هنوز ربات فعالی ندارد.');
        }
        break;

    case 'set_hide_panel':
        $panel_name = trim($_POST['panel_name'] ?? '');
        if (empty($panel_name)) {
            flash('error', 'نام پنل نباید خالی باشد.');
            break;
        }
        $bot_info_row = db_fetch($pdo, "SELECT hide_panel FROM botsaz WHERE id_user = ?", [$id]);
        if ($bot_info_row) {
            $hidden = json_decode($bot_info_row['hide_panel'], true);
            if (!is_array($hidden)) $hidden = [];
            
            $pos = array_search($panel_name, $hidden);
            if ($pos !== false) {
                unset($hidden[$pos]);
                $hidden = array_values($hidden);
                flash('success', 'پنل از حالت مخفی خارج شد.');
            } else {
                $hidden[] = $panel_name;
                flash('success', 'پنل با موفقیت مخفی شد.');
            }
            db_query($pdo, "UPDATE botsaz SET hide_panel = ? WHERE id_user = ?", [json_encode($hidden), $id]);
        } else {
            flash('error', 'کاربر هنوز ربات فعالی ندارد.');
        }
        break;

    default:
        flash('error', $textbotlang['panel']['userActionInvalidOperation']);
}

header("Location: $back"); exit;

