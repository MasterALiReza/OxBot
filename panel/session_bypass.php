<?php
require_once __DIR__ . '/inc/config.php';
$stmt = $pdo->query("SELECT id, namecustom, agent FROM user WHERE agent != 'n' LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $_SESSION['agent_id'] = $row['id'];
    echo "Logged in as agent ID: " . $row['id'] . " (" . $row['namecustom'] . ")";
} else {
    echo "No agent found in database!";
}
