<?php
require_once __DIR__ . '/inc/config.php';
header('Content-Type: text/plain; charset=utf-8');

$id = '6586333302';
try {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = ?");
    $stmt->execute([$id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "INVOICES COUNT: " . count($invoices) . "\n";
    foreach ($invoices as $inv) {
        echo "ID: {$inv['id_invoice']} | User: {$inv['id_user']} | Product: {$inv['name_product']} | Status: {$inv['Status']} | Time: {$inv['time_sell']}\n";
    }

    echo "\nUSER DETAILS:\n";
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($user);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
