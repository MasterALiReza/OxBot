<?php
require_once __DIR__ . '/../inc/config.php';
require_auth();
csrf_check_post();

header('Content-Type: application/json; charset=utf-8');

$id_user    = (int)($_POST['id_user'] ?? 0);
$id_invoice = trim($_POST['id_invoice'] ?? '');
$new_days_in = (float)($_POST['new_days'] ?? -1);
$mode       = trim($_POST['mode'] ?? 'absolute'); // 'absolute' or 'add'

if (!$id_user || empty($id_invoice) || $new_days_in < 0 || $new_days_in > 9999) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'مقدار زمان وارد شده معتبر نیست (باید بین ۰ تا ۹۹۹۹ روز باشد).'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $invoice = db_fetch($pdo, "SELECT * FROM invoice WHERE id_invoice = ? AND id_user = ?", [$id_invoice, $id_user]);
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'سرویس مورد نظر یافت نشد.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $old_cwd = getcwd();
    chdir(__DIR__ . '/../../');
    require_once 'function.php';
    require_once 'panels.php';
    require_once 'botapi.php';
    chdir($old_cwd);

    $ManagePanel = new ManagePanel();

    $target_days = $new_days_in;
    if ($mode === 'add') {
        $currentData = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (isset($currentData['status']) && $currentData['status'] === "Unsuccessful") {
            $err_msg = !empty($currentData['msg']) ? (is_string($currentData['msg']) ? $currentData['msg'] : json_encode($currentData['msg'])) : "خطا در برقراری ارتباط با سرور";
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'خطا در دریافت اطلاعات سرویس: ' . $err_msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $current_expire = (float)($currentData['expire'] ?? 0);
        $current_expire = time() - $current_expire > 0 ? time() : $current_expire;
        $current_days = $current_expire > 0 ? ($current_expire - time()) / 86400 : 0;
        if ($current_days < 0) $current_days = 0;
        $target_days = $current_days + $new_days_in;
    }

    $res = $ManagePanel->SetTimeAbsolute($invoice['username'], $invoice['Service_location'], $target_days);

    if (isset($res['status']) && $res['status'] === false) {
        $err_msg = !empty($res['msg']) ? (is_string($res['msg']) ? $res['msg'] : json_encode($res['msg'])) : "خطای نامشخص سرور";
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'خطا در ویرایش زمان: ' . $err_msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Insert log
    try {
        db_query($pdo, "INSERT INTO service_other (id_user, username, value, type, time, price, output) VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $id_user,
            $invoice['username'],
            $target_days . ' Days (' . $mode . ')',
            'set_time_by_admin',
            date('Y-m-d H:i:s'),
            0,
            json_encode($res)
        ]);
    } catch (Exception $e) {
        error_log("Failed to insert service_other log: " . $e->getMessage());
    }

    // Send Telegram Notification to user
    if (!isset($currentData)) {
        $currentData = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
    }

    $old_days = 'نامشخص';
    if (isset($currentData['expire'])) {
        $current_expire = time() - $currentData['expire'] > 0 ? time() : $currentData['expire'];
        $c_days = $current_expire > 0 ? ($current_expire - time()) / 86400 : 0;
        if ($c_days < 0) $c_days = 0;
        $old_days = ($currentData['expire'] == 0) ? 'نامحدود' : floor($c_days) . ' روز';
    }

    $display_days = $target_days == 0 ? 'نامحدود' : floor($target_days) . ' روز';
    $total_days_str = $display_days;
    $mode_display = ($mode === 'add') ? 'افزوده شده' : 'جایگزین';
    
    $rem_gb = 'نامشخص';
    if (isset($currentData['data_limit']) && $currentData['data_limit'] > 0) {
        $used = $currentData['used_traffic'] ?? 0;
        $rem = max(0, $currentData['data_limit'] - $used);
        $rem_gb = number_format($rem / pow(1024, 3), 1) . ' گیگابایت';
    } elseif (isset($currentData['data_limit']) && $currentData['data_limit'] == 0) {
        $rem_gb = 'نامحدود';
    }

    if (function_exists('send_admin_edit_notification')) {
        send_admin_edit_notification($id_user, $invoice, 'time', $old_days, $display_days, $rem_gb, $total_days_str, $mode_display);
    }

    echo json_encode([
        'ok' => true, 
        'msg' => '✅ زمان سرویس با موفقیت به ' . $display_days . ' تغییر یافت.',
        'new_days' => $target_days
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'خطای سرور: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
