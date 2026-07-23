<?php
require_once __DIR__ . '/../inc/config.php';
require_auth();
csrf_check_post();

header('Content-Type: application/json; charset=utf-8');

$id_user    = (int)($_POST['id_user'] ?? 0);
$id_invoice = trim($_POST['id_invoice'] ?? '');
$new_gb_in  = (float)($_POST['new_gb'] ?? -1);
$mode       = trim($_POST['mode'] ?? 'absolute'); // 'absolute' or 'add'

if (!$id_user || empty($id_invoice) || $new_gb_in < 0 || $new_gb_in > 999) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'مقدار حجم وارد شده معتبر نیست (باید بین ۰ تا ۹۹۹ گیگابایت باشد).'], JSON_UNESCAPED_UNICODE);
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

    $target_gb = $new_gb_in;
    if ($mode === 'add') {
        $currentData = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);
        if (isset($currentData['status']) && $currentData['status'] === "Unsuccessful") {
            $err_msg = !empty($currentData['msg']) ? (is_string($currentData['msg']) ? $currentData['msg'] : json_encode($currentData['msg'])) : "خطا در برقراری ارتباط با سرور";
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'خطا در دریافت اطلاعات سرویس: ' . $err_msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $current_bytes = (float)($currentData['data_limit'] ?? 0);
        $current_gb = $current_bytes / pow(1024, 3);
        $target_gb = $current_gb + $new_gb_in;
    }

    $res = $ManagePanel->SetVolumeAbsolute($invoice['username'], $invoice['Service_location'], $target_gb);

    if (isset($res['status']) && $res['status'] === false) {
        $err_msg = !empty($res['msg']) ? (is_string($res['msg']) ? $res['msg'] : json_encode($res['msg'])) : "خطای نامشخص سرور";
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'خطا در ویرایش حجم: ' . $err_msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Insert log
    try {
        db_query($pdo, "INSERT INTO service_other (id_user, username, value, type, time, price, output) VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $id_user,
            $invoice['username'],
            $target_gb . ' GB (' . $mode . ')',
            'set_volume_by_admin',
            date('Y-m-d H:i:s'),
            0,
            json_encode($res)
        ]);
    } catch (Exception $e) {
        error_log("Failed to insert service_other log: " . $e->getMessage());
    }

    // Send Telegram Notification to user
    $display_gb = $target_gb == 0 ? 'نامحدود' : number_format($target_gb, 1) . ' گیگابایت';
    $msg = "🛡 <b>تغییر حجم سرویس</b>\n\n"
         . "✏️ مدیریت حجم سرویس شما را ویرایش کرد.\n"
         . "📦 حجم جدید: <b>" . $display_gb . "</b>\n"
         . "🔑 شناسه سرویس: <code>" . htmlspecialchars($invoice['username']) . "</code>";

    if (function_exists('telegram')) {
        telegram('sendMessage', [
            'chat_id'    => $id_user,
            'text'       => $msg,
            'parse_mode' => 'HTML'
        ]);
    }

    echo json_encode([
        'ok' => true, 
        'msg' => '✅ حجم سرویس با موفقیت به ' . $display_gb . ' تغییر یافت.',
        'new_gb' => $target_gb
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'خطای سرور: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
