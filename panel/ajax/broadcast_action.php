<?php
require '../inc/config.php';
require_auth();
csrf_check_post();

global $pdo;

if (is_file('../../cronbot/info') || is_file('../../cronbot/users.json')) {
    echo '<div class="alert alert-warn">یک عملیات ارسال در حال اجراست. ابتدا باید آن را لغو کنید یا تا پایان آن منتظر بمانید.</div>';
    exit;
}

$type = $_POST['type'] ?? 'sendmessage';
$btnmessage = $_POST['btnmessage'] ?? 'none';
$target_users = $_POST['target_users'] ?? 'all';
$target_agent = $_POST['target_agent'] ?? 'all';
$message = trim($_POST['message'] ?? '');
$pingmessage = isset($_POST['pingmessage']) ? 'yes' : 'no';

if ($type === 'sendmessage' && empty($message)) {
    echo '<div class="alert alert-warn">لطفا متن پیام را وارد کنید.</div>';
    exit;
}

// Fetch admin id
$admin = db_fetch($pdo, "SELECT id_admin FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
$id_admin = $admin['id_admin'] ?? 1;

// Build query
$where = [];
$params = [];

if ($target_agent !== 'all') {
    $where[] = "agent = ?";
    $params[] = $target_agent;
}

// target_users filtering (like in admin.php)
if ($target_users === 'customer') {
    $where[] = "id IN (SELECT id_user FROM invoice)";
} elseif ($target_users === 'nonecustomer') {
    $where[] = "id NOT IN (SELECT id_user FROM invoice)";
}

$sql = "SELECT id FROM user";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$users = db_fetchAll($pdo, $sql, $params);
if (empty($users)) {
    echo '<div class="alert alert-warn">هیچ کاربری با این شرایط یافت نشد.</div>';
    exit;
}

// Fetch admin ids & owner to merge into the broadcast target list
$admin_ids = [];
$admin_rows = db_fetchAll($pdo, "SELECT id_admin FROM admin");
foreach ($admin_rows as $row) {
    if (!empty($row['id_admin'])) {
        $admin_ids[] = (string)$row['id_admin'];
    }
}
if (isset($adminnumber) && $adminnumber !== '') {
    $admin_ids[] = (string)$adminnumber;
}

$all_ids = [];
foreach ($users as $u) {
    if (!empty($u['id'])) {
        $all_ids[] = (string)$u['id'];
    }
}

// Merge and deduplicate
$all_ids = array_merge($all_ids, $admin_ids);
$all_ids = array_values(array_unique(array_filter($all_ids)));

// Format exactly as the cron expects: an array of objects/arrays with 'id' property.
$formatted_users = [];
foreach ($all_ids as $id) {
    $formatted_users[] = ['id' => $id];
}

$info = [
    'id_admin' => $id_admin,
    'id_message' => 0, // Panel doesn't have a specific telegram message to edit for progress
    'type' => $type,
    'message' => $message,
    'pingmessage' => $pingmessage,
    'btnmessage' => $btnmessage
];

file_put_contents('../../cronbot/users.json', json_encode($formatted_users));
file_put_contents('../../cronbot/info', json_encode($info));

echo '<div class="alert alert-success">عملیات با موفقیت تنظیم شد و در پس‌زمینه ارسال خواهد شد. ' . count($formatted_users) . ' کاربر هدف‌گذاری شدند.</div>';
echo '<script>setTimeout(() => window.location.reload(), 2500);</script>';
