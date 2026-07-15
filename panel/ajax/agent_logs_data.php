<?php
/**
 * Agent Panel — Financial Logs API
 * Returns JSON for agent's wallet top-ups and purchases
 */

ob_start();
require '../inc/config.php';
ob_end_clean();

$old_cwd = getcwd();
chdir(__DIR__ . '/../../');
require_once 'function.php';
chdir($old_cwd);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['agent_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'نشست منقضی شده است. لطفاً مجدداً وارد شوید.']);
    exit;
}

$agent_id = (int) $_SESSION['agent_id'];
session_write_close();

global $pdo;

$logs = [];

// 1. Fetch wallet charges from Payment_report
$stmt1 = $pdo->prepare("SELECT id, time, price, Payment_Method as method, payment_Status as status, id_order, id_invoice 
                        FROM Payment_report 
                        WHERE id_user = :agent_id");
$stmt1->execute([':agent_id' => $agent_id]);
while ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
    // Normalize time to a timestamp for sorting
    $timestamp = 0;
    if (is_numeric($row['time'])) {
        $timestamp = (int)$row['time'];
    } else {
        $timestamp = strtotime($row['time']);
    }
    
    $logs[] = [
        'type' => 'deposit',
        'id' => $row['id'],
        'time' => $row['time'],
        'timestamp' => $timestamp,
        'amount' => (float) $row['price'],
        'method' => $row['method'],
        'status' => $row['status'],
        'order_id' => $row['id_order'],
        'invoice_id' => $row['id_invoice'],
        'description' => 'شارژ کیف پول'
    ];
}

// 2. Fetch purchases from invoice
$stmt2 = $pdo->prepare("SELECT id_invoice, time_sell, price_product, name_product, Service_location 
                        FROM invoice 
                        WHERE id_user = :agent_id AND price_product > 0");
$stmt2->execute([':agent_id' => $agent_id]);
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    $timestamp = 0;
    if (is_numeric($row['time_sell'])) {
        $timestamp = (int)$row['time_sell'];
    } else {
        $timestamp = strtotime($row['time_sell']);
    }

    $logs[] = [
        'type' => 'purchase',
        'id' => $row['id_invoice'],
        'time' => $row['time_sell'],
        'timestamp' => $timestamp,
        'amount' => -1 * (float) $row['price_product'],
        'method' => 'wallet',
        'status' => 'paid', // Purchases recorded in invoice are usually paid
        'order_id' => $row['id_invoice'],
        'invoice_id' => $row['id_invoice'],
        'description' => 'خرید سرویس ' . $row['name_product'] . ' (' . $row['Service_location'] . ')'
    ];
}

// Sort by timestamp descending
usort($logs, function($a, $b) {
    return $b['timestamp'] <=> $a['timestamp'];
});

// Calculate total deposit and total spent (only considering 'paid' status for deposits)
$total_deposit = 0;
$total_spent = 0;
foreach ($logs as $log) {
    if ($log['type'] === 'deposit' && strtolower($log['status']) === 'paid') {
        $total_deposit += $log['amount'];
    } elseif ($log['type'] === 'purchase') {
        $total_spent += abs($log['amount']); // Amount is negative, so abs() makes it positive for the total spent
    }
}

// Return JSON
echo json_encode([
    'status' => 'success',
    'data' => $logs,
    'stats' => [
        'total_deposit' => $total_deposit,
        'total_spent' => $total_spent
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
