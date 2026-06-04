<?php
require_once __DIR__ . '/../inc/config.php';
require_auth();
csrf_check_post();

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$id || !in_array($action, ['done', 'reject'])) {
    http_response_code(400);
    die('Invalid request');
}

try {
    // 1. Fetch the service request
    $service = db_fetch($pdo, "SELECT * FROM service_other WHERE id = ?", [$id]);
    if (!$service) {
        http_response_code(404);
        die('Service not found');
    }

    if ($service['status'] !== 'pending') {
        die('<tr class="fade-up"><td colspan="9" class="cf" style="text-align:center; padding:12px; background:rgba(239, 68, 68, 0.1); color:var(--red);">⚠️ این درخواست قبلاً پردازش شده است.</td></tr>');
    }

    $id_user = $service['id_user'];
    
    // 2. Update status in database
    db_query($pdo, "UPDATE service_other SET status = ? WHERE id = ?", [$action, $id]);

    // 3. Notify user via Telegram Bot
    if (!empty($id_user)) {
        if ($action === 'done') {
            $msg = "✅ <b>سفارش شما با موفقیت تایید و انجام شد.</b>\n\n🔹 نوع سفارش: " . htmlspecialchars($service['type']);
        } else {
            $msg = "❌ <b>سفارش شما توسط مدیریت لغو و رد شد.</b>\n\n🔹 نوع سفارش: " . htmlspecialchars($service['type']);
        }
        
        // Use the sendmessage function from function.php (which is included via config.php)
        if (function_exists('sendmessage')) {
            sendmessage($id_user, $msg, null, 'HTML');
        }
    }

    // 4. Return new table row for HTMX swap
    $bgColor = $action === 'done' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
    $textColor = $action === 'done' ? 'var(--emerald)' : 'var(--red)';
    $text = $action === 'done' ? '✅ عملیات با موفقیت انجام شد و پیام به کاربر ارسال گردید.' : '❌ درخواست رد شد و پیام به کاربر ارسال گردید.';
    
    echo '<tr class="fade-up">';
    echo '<td colspan="9" class="cf" style="text-align:center; padding:12px; font-weight:500; background:' . $bgColor . '; color:' . $textColor . ';">' . $text . '</td>';
    echo '</tr>';

} catch (Exception $e) {
    error_log('service_action error: ' . $e->getMessage());
    echo '<tr class="fade-up"><td colspan="9" class="cf" style="text-align:center; padding:12px; background:rgba(239, 68, 68, 0.1); color:var(--red);">خطا در ارتباط با پایگاه داده یا ربات.</td></tr>';
}
