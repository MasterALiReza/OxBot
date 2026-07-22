<?php
ignore_user_abort(true);
set_time_limit(0);
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();
$infoFile = __DIR__ . '/info';
$usersFile = __DIR__ . '/users.json';
$cancelFile = __DIR__ . '/cancel_broadcast';
$lockFile = __DIR__ . '/sendmessage.lock';

$lockFp = fopen($lockFile, 'w+');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Another instance is already running, prevent duplicate execution
    exit;
}

function broadcast_cleanup_files(string $infoFile, string $usersFile, string $cancelFile): void
{
    if (is_file($infoFile)) {
        @unlink($infoFile);
    }
    if (is_file($usersFile)) {
        @unlink($usersFile);
    }
    if (is_file($cancelFile)) {
        @unlink($cancelFile);
    }
}

function broadcast_mark_cancelled(PDO $pdo, ?int $historyId = null): void
{
    if ($historyId && $historyId > 0) {
        $stmt = $pdo->prepare("UPDATE broadcast_history SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$historyId]);
    } else {
        $pdo->query("UPDATE broadcast_history SET status = 'cancelled' WHERE status IN ('in_progress', 'pending', 'cancelling')");
    }
}

function broadcast_cancel_requested(string $cancelFile): bool
{
    return is_file($cancelFile);
}

// Load broadcast payload info
$info = is_file($infoFile) ? json_decode(file_get_contents($infoFile), true) : null;
$history_id = (isset($info['history_id']) && is_numeric($info['history_id'])) ? (int)$info['history_id'] : null;

if (broadcast_cancel_requested($cancelFile)) {
    broadcast_mark_cancelled($pdo, $history_id);
    broadcast_cleanup_files($infoFile, $usersFile, $cancelFile);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return;
}

if (!is_file($infoFile) || !is_file($usersFile) || !is_array($info)) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return;
}

function broadcast_user_id($entry): ?string
{
    if (is_array($entry) && isset($entry['id'])) {
        return (string) $entry['id'];
    }
    if (is_object($entry) && isset($entry->id)) {
        return (string) $entry->id;
    }
    if (is_scalar($entry)) {
        return (string) $entry;
    }
    return null;
}

// Load administrative and owner Telegram IDs
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN") ?: [];
global $adminnumber;
if (isset($adminnumber) && $adminnumber !== '') {
    $admin_ids[] = (string)$adminnumber;
}
$admin_ids = array_values(array_unique(array_filter($admin_ids)));

// Intercept new broadcast runs and merge admins/owner
if (!isset($info['admin_appended'])) {
    $raw_userid = json_decode(file_get_contents($usersFile), true) ?: [];

    $existing_ids = [];
    foreach ($raw_userid as $u) {
        $parsed_id = broadcast_user_id($u);
        if ($parsed_id !== null && $parsed_id !== '') {
            $existing_ids[] = $parsed_id;
        }
    }

    $merged_ids = array_merge($existing_ids, $admin_ids);
    $merged_ids = array_values(array_unique(array_filter($merged_ids)));

    $new_userid_list = [];
    foreach ($merged_ids as $id) {
        $new_userid_list[] = ['id' => $id];
    }

    file_put_contents($usersFile, json_encode($new_userid_list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $info['admin_appended'] = true;
    file_put_contents($infoFile, json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

$userid = json_decode(file_get_contents($usersFile), true);
if (!is_array($userid)) {
    $userid = [];
}

// Update broadcast status to in_progress
if ($history_id && $history_id > 0) {
    $stmt = $pdo->prepare("UPDATE broadcast_history SET status = 'in_progress' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$history_id]);
}

if (count($userid) == 0) {
    if (isset($info['id_admin']) && isset($info['id_message']) && intval($info['id_message']) > 0) {
        deletemessage($info['id_admin'], $info['id_message']);
        sendmessage($info['id_admin'], $textbotlang['hardcoded']['bulkMessageDone'], null, 'HTML');
    }
    if ($history_id && $history_id > 0) {
        $stmt = $pdo->prepare("UPDATE broadcast_history SET status = 'completed' WHERE id = ?");
        $stmt->execute([$history_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE broadcast_history SET status = 'completed' WHERE status IN ('in_progress', 'pending')");
        $stmt->execute();
    }
    broadcast_cleanup_files($infoFile, $usersFile, $cancelFile);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return;
}

$count_remein = count($userid);
$textprocces = sprintf($textbotlang['hardcoded']['bulkMessageProgress'], $count_remein);
$cancelmessage = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['keyboard']['cancelOperation'], 'callback_data' => 'cancel_sendmessage'],
        ],
    ]
]);

if (isset($info['id_admin']) && isset($info['id_message']) && intval($info['id_message']) > 0) {
    Editmessagetext($info['id_admin'], $info['id_message'], $textprocces, $cancelmessage);
}

$keyboardbuy = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['textbot']['sell'], 'callback_data' => 'buy_broadcast'],
        ],
    ]
]);
$keyboardstart = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['keyboard']['start'], 'callback_data' => 'start_broadcast'],
        ],
    ]
]);
$keyboardusertest = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['textbot']['userTest'], 'callback_data' => 'usertestbtn_broadcast'],
        ],
    ]
]);
$keyboardhelpbtn = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['textbot']['help'], 'callback_data' => 'helpbtn_broadcast'],
        ],
    ]
]);
$keyboardaffiliates = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['textbot']['affiliates'], 'callback_data' => 'affiliatesbtn_broadcast'],
        ],
    ]
]);
$keyboardaddbalance = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['textbot']['addBalance'], 'callback_data' => 'Add_Balance_broadcast'],
        ],
    ]
]);

$custom_keyboard = null;
if (isset($info['btnmessage']) && $info['btnmessage'] !== "none") {
    if ($info['btnmessage'] == "buy") {
        $custom_keyboard = $keyboardbuy;
    } elseif ($info['btnmessage'] == "start") {
        $custom_keyboard = $keyboardstart;
    } elseif ($info['btnmessage'] == "usertestbtn") {
        $custom_keyboard = $keyboardusertest;
    } elseif ($info['btnmessage'] == "helpbtn") {
        $custom_keyboard = $keyboardhelpbtn;
    } elseif ($info['btnmessage'] == "affiliatesbtn") {
        $custom_keyboard = $keyboardaffiliates;
    } elseif ($info['btnmessage'] == "addbalance") {
        $custom_keyboard = $keyboardaddbalance;
    } elseif ($info['btnmessage'] == "custom_url") {
        $custom_keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $info['custom_btn_text_url'], 'url' => $info['custom_btn_link']],
                ],
            ]
        ]);
    } elseif ($info['btnmessage'] == "custom_url_dynamic") {
        $dynamic_buttons = json_decode($info['custom_btn_dynamic'], true) ?: [];
        $keyboard_rows = [];
        foreach ($dynamic_buttons as $btn) {
            $btn_arr = ['text' => $btn['text'], 'url' => $btn['url']];
            if (isset($btn['color']) && $btn['color'] !== 'default') {
                $btn_arr['color'] = $btn['color'];
            }
            $keyboard_rows[] = [$btn_arr];
        }
        $custom_keyboard = json_encode(['inline_keyboard' => $keyboard_rows]);
    } elseif ($info['btnmessage'] == "custom_product") {
        $custom_keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $info['custom_btn_text_prod'], 'callback_data' => $info['custom_btn_callback'] . '_broadcast'],
                ],
            ]
        ]);
    }
}

$start_time = time();
$last_save_time = microtime(true);
$processed_in_batch = 0;
$max_batch_size = 1000;
$max_execution_duration = 50; // Max 50 seconds per invocation
$html_error_notified = false;

// Prepare forwardlink parameters if needed
$from_chat_id = '';
$message_id = '';
if (isset($info['type']) && $info['type'] == "forwardlink") {
    $link = $info['message'] ?? '';
    if (preg_match('/t\.me\/c\/(\d+)\/(\d+)/', $link, $matches)) {
        $from_chat_id = '-100' . $matches[1];
        $message_id = $matches[2];
    } elseif (preg_match('/t\.me\/([a-zA-Z0-9_]+)\/(\d+)/', $link, $matches)) {
        $from_chat_id = '@' . $matches[1];
        $message_id = $matches[2];
    }
}

$mini_batch_size = 15; // Process 15 users concurrently per multi-cURL batch

while (!empty($userid) && $processed_in_batch < $max_batch_size) {
    if (time() - $start_time >= $max_execution_duration) {
        break;
    }

    if (broadcast_cancel_requested($cancelFile)) {
        broadcast_mark_cancelled($pdo, $history_id);
        broadcast_cleanup_files($infoFile, $usersFile, $cancelFile);
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        return;
    }

    $batchStart = microtime(true);
    $current_chunk = [];

    // Extract next mini-batch of users efficiently (O(N) once per chunk instead of O(N^2))
    $raw_entries = array_splice($userid, 0, $mini_batch_size);
    foreach ($raw_entries as $raw_entry) {
        $iduser = broadcast_user_id($raw_entry);
        if ($iduser !== null && $iduser !== '') {
            $current_chunk[] = $iduser;
        }
    }

    if (empty($current_chunk)) {
        if (microtime(true) - $last_save_time >= 2.5 || empty($userid)) {
            file_put_contents($usersFile, json_encode($userid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            $last_save_time = microtime(true);
        }
        continue;
    }

    // Build multi requests for current mini-batch
    $requests = [];
    foreach ($current_chunk as $iduser) {
        if ($info['type'] == "unpinmessage") {
            $requests[$iduser] = [
                'method' => 'unpinAllChatMessages',
                'datas' => ['chat_id' => $iduser]
            ];
        } elseif ($info['type'] == "sendmessage" || $info['type'] == "xdaynotmessage") {
            $params = [
                'chat_id' => $iduser,
                'text' => $info['message'],
                'parse_mode' => 'HTML'
            ];
            if ($custom_keyboard) {
                $params['reply_markup'] = $custom_keyboard;
            }
            $requests[$iduser] = ['method' => 'sendMessage', 'datas' => $params];
        } elseif ($info['type'] == "forwardmessage") {
            $requests[$iduser] = [
                'method' => 'forwardMessage',
                'datas' => [
                    'from_chat_id' => $info['id_admin'],
                    'message_id' => $info['message'],
                    'chat_id' => $iduser
                ]
            ];
        } elseif ($info['type'] == "forwardlink" && $from_chat_id && $message_id) {
            $copy_params = [
                'chat_id' => $iduser,
                'from_chat_id' => $from_chat_id,
                'message_id' => $message_id
            ];
            if ($custom_keyboard) {
                $copy_params['reply_markup'] = $custom_keyboard;
            }
            $requests[$iduser] = ['method' => 'copyMessage', 'datas' => $copy_params];
        }
    }

    // Execute mini-batch in parallel
    $responses = telegram_multi($requests);
    $processed_in_batch += count($current_chunk);

    // Process responses for rate-limit 429, blocked users, pinmessage, and errors
    $rate_limit_retry = 0;
    foreach ($responses as $iduser => $meesage) {
        if (isset($meesage['ok']) && !$meesage['ok']) {
            $desc = $meesage['description'] ?? '';

            // Handle Rate Limit HTTP 429
            if (strpos($desc, 'Too Many Requests') !== false || (isset($meesage['error_code']) && $meesage['error_code'] == 429)) {
                $retry = 5;
                if (isset($meesage['parameters']['retry_after'])) {
                    $retry = intval($meesage['parameters']['retry_after']);
                }
                $rate_limit_retry = max($rate_limit_retry, min($retry, 10));
            }

            // Handle invalid HTML formatting error
            if (strpos($desc, 'can\'t parse entities') !== false || strpos($desc, 'parse error') !== false) {
                if (!$html_error_notified && isset($info['id_admin']) && intval($info['id_admin']) > 0) {
                    sendmessage($info['id_admin'], "⚠️ <b>خطا در فرمت HTML پیام همگانی:</b><br>" . htmlspecialchars($desc), null, 'HTML');
                    $html_error_notified = true;
                }
            }

            // Handle blocked user
            if (strpos($desc, 'Forbidden: bot was blocked by the user') !== false) {
                try {
                    $invoicecount = select("invoice", "*", "id_user", $iduser, "count");
                    $userinfo = select("user", "Balance", "id", $iduser, "select");
                    if ($invoicecount == 0 && isset($userinfo['Balance']) && $userinfo['Balance'] == 0) {
                        $stmt = $pdo->prepare("DELETE FROM user WHERE id = ?");
                        $stmt->execute([$iduser]);
                    }
                } catch (Throwable $ignored) {}
            }
        }

        // Pin message if requested
        if (isset($meesage['ok']) && $meesage['ok'] && (isset($info['pingmessage']) && $info['pingmessage'] == "yes")) {
            if (isset($meesage['result']['message_id'])) {
                pinmessage($iduser, $meesage['result']['message_id']);
            }
        }
    }

    // Save remaining users to disk only every 2.5 seconds to reduce Disk I/O bottleneck
    if (microtime(true) - $last_save_time >= 2.5 || empty($userid)) {
        file_put_contents($usersFile, json_encode($userid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $last_save_time = microtime(true);
    }

    // If 429 rate limit hit, sleep for retry_after
    if ($rate_limit_retry > 0) {
        sleep($rate_limit_retry);
    } else {
        // Enforce safe 25 msgs/sec rate limit pacing
        $batch_duration = microtime(true) - $batchStart;
        $target_duration = count($current_chunk) / 25.0; // Target seconds for 25 msg/sec
        if ($batch_duration < $target_duration) {
            $sleep_us = (int)(($target_duration - $batch_duration) * 1000000);
            if ($sleep_us > 0) {
                usleep($sleep_us);
            }
        }
    }
}

// Save remaining users to disk at batch completion
file_put_contents($usersFile, json_encode($userid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

// If all users have been processed, mark completed and clean up
if (empty($userid)) {
    if (isset($info['id_admin']) && isset($info['id_message']) && intval($info['id_message']) > 0) {
        deletemessage($info['id_admin'], $info['id_message']);
        sendmessage($info['id_admin'], $textbotlang['hardcoded']['bulkMessageDone'], null, 'HTML');
    }
    if ($history_id && $history_id > 0) {
        $stmt = $pdo->prepare("UPDATE broadcast_history SET status = 'completed' WHERE id = ?");
        $stmt->execute([$history_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE broadcast_history SET status = 'completed' WHERE status IN ('in_progress', 'pending')");
        $stmt->execute();
    }
    broadcast_cleanup_files($infoFile, $usersFile, $cancelFile);
}

flock($lockFp, LOCK_UN);
fclose($lockFp);

// Auto-trigger next invocation immediately if users remain
if (!empty($userid) && !broadcast_cancel_requested($cancelFile)) {
    if (function_exists('trigger_broadcast_async')) {
        trigger_broadcast_async();
    }
}
