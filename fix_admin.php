<?php
$content = file_get_contents("admin.php");

// 1. Confirm_pay success
$search1 = "    telegram('editMessageReplyMarkup', [
        'chat_id' => \$chat_id,
        'message_id' => \$message_id,
        'reply_markup' => \$Confirm_pay
    ]);
} elseif (preg_match('/reject_pay_(\w+)/'";

$replace1 = "    telegram('editMessageReplyMarkup', [
        'chat_id' => \$chat_id,
        'message_id' => \$message_id,
        'reply_markup' => \$Confirm_pay
    ]);
    // Sync for all admins
    \$stmt_msg = \$pdo->prepare(\"SELECT admin_id, message_id FROM admin_payment_messages WHERE id_order = ? AND admin_id != ?\");
    \$stmt_msg->execute([\$order_id, \$chat_id]);
    \$admin_msgs = \$stmt_msg->fetchAll(PDO::FETCH_ASSOC);
    foreach (\$admin_msgs as \$am) {
        telegram('editMessageReplyMarkup', [
            'chat_id' => \$am['admin_id'],
            'message_id' => \$am['message_id'],
            'reply_markup' => \$Confirm_pay
        ]);
    }
} elseif (preg_match('/reject_pay_(\w+)/'";

if (strpos($content, $search1) !== false) {
    $content = str_replace($search1, $replace1, $content);
    echo "Replaced 1\n";
} else {
    echo "Not found 1\n";
}

// 2. DirectPayment failure
$search2 = "        Editmessagetext(\$chat_id, \$message_id, \$text_fail, \$Confirm_pay_fail);
        
        update(\"user\", \"Processing_value_one\", \"none\", \"id\", \$Balance_id['id']);";

$replace2 = "        telegram('editMessageCaption', [
            'chat_id' => \$chat_id,
            'message_id' => \$message_id,
            'caption' => \$text_fail,
            'parse_mode' => 'HTML',
            'reply_markup' => \$Confirm_pay_fail
        ]);
        
        // Sync for all admins
        \$stmt_msg = \$pdo->prepare(\"SELECT admin_id, message_id FROM admin_payment_messages WHERE id_order = ? AND admin_id != ?\");
        \$stmt_msg->execute([\$order_id, \$chat_id]);
        \$admin_msgs = \$stmt_msg->fetchAll(PDO::FETCH_ASSOC);
        foreach (\$admin_msgs as \$am) {
            telegram('editMessageCaption', [
                'chat_id' => \$am['admin_id'],
                'message_id' => \$am['message_id'],
                'caption' => \$text_fail,
                'parse_mode' => 'HTML',
                'reply_markup' => \$Confirm_pay_fail
            ]);
        }
        
        update(\"user\", \"Processing_value_one\", \"none\", \"id\", \$Balance_id['id']);";

if (strpos($content, $search2) !== false) {
    $content = str_replace($search2, $replace2, $content);
    echo "Replaced 2\n";
} else {
    echo "Not found 2\n";
}

// 3. Reject pay (change to failed keyboard)
$search3 = "    telegram('editMessageReplyMarkup', [
        'chat_id' => \$chat_id,
        'message_id' => \$message_id,
        'reply_markup' => \$Confirm_pay_fail
    ]);
} elseif (\$user['step'] == \"reject-dec\") {";

$replace3 = "    telegram('editMessageReplyMarkup', [
        'chat_id' => \$chat_id,
        'message_id' => \$message_id,
        'reply_markup' => \$Confirm_pay_fail
    ]);
    
    // Sync for all admins
    \$stmt_msg = \$pdo->prepare(\"SELECT admin_id, message_id FROM admin_payment_messages WHERE id_order = ? AND admin_id != ?\");
    \$stmt_msg->execute([\$id_order, \$chat_id]);
    \$admin_msgs = \$stmt_msg->fetchAll(PDO::FETCH_ASSOC);
    foreach (\$admin_msgs as \$am) {
        telegram('editMessageReplyMarkup', [
            'chat_id' => \$am['admin_id'],
            'message_id' => \$am['message_id'],
            'reply_markup' => \$Confirm_pay_fail
        ]);
    }
} elseif (\$user['step'] == \"reject-dec\") {";

if (strpos($content, $search3) !== false) {
    $content = str_replace($search3, $replace3, $content);
    echo "Replaced 3\n";
} else {
    echo "Not found 3\n";
}

// 4. reject-dec finalization (send reason to other admins)
$search4 = "    sendmessage(\$from_id, \$textbotlang['Admin']['Payment']['rejected'], \$keyboardadmin, 'HTML');
    sendmessage(\$user['Processing_value'], \$text_reject, null, 'HTML');";

$replace4 = "    sendmessage(\$from_id, \$textbotlang['Admin']['Payment']['rejected'], \$keyboardadmin, 'HTML');
    sendmessage(\$user['Processing_value'], \$text_reject, null, 'HTML');
    
    // Notify other admins about the rejection reason
    \$stmt_msg = \$pdo->prepare(\"SELECT admin_id FROM admin_payment_messages WHERE id_order = ? AND admin_id != ? GROUP BY admin_id\");
    \$stmt_msg->execute([\$user['Processing_value_one'], \$from_id]);
    \$admin_msgs = \$stmt_msg->fetchAll(PDO::FETCH_ASSOC);
    \$reason_msg = \"? ???? ????? <code>{\$user['Processing_value_one']}</code> ???? ????? ??? ?? ??.\\n\\n?? ???? ??: {\$text}\";
    foreach (\$admin_msgs as \$am) {
        sendmessage(\$am['admin_id'], \$reason_msg, null, 'HTML');
    }";

if (strpos($content, $search4) !== false) {
    $content = str_replace($search4, $replace4, $content);
    echo "Replaced 4\n";
} else {
    echo "Not found 4\n";
}

file_put_contents("admin.php", $content);
echo "Done.\n";

