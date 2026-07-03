<?php
set_time_limit(0); // Prevent timeouts for large backups
error_reporting(0); // Prevent error leakage
require_once '../config.php';
require_once '../function.php';
$textbotlang = languagechange();
require_once '../botapi.php';

// 1. Verify Authentication Token (Super Safe)
$provided_token = isset($_GET['token']) ? $_GET['token'] : (isset($argv[1]) ? $argv[1] : null);
if (empty($provided_token) || empty($backup_secure_token) || $provided_token !== $backup_secure_token) {
    http_response_code(403);
    die('Forbidden: Invalid or missing token.');
}

$reportbackup = select("topicid", "idreport", "report", "backupfile", "select")['idreport'];
$setting = select("setting", "*");
$sourcefir = dirname(__DIR__); // Root directory of the bot

// 2. Use /tmp/ for temporary files to prevent public access
$tmp_dir = sys_get_temp_dir();
$uniq_id = uniqid();
$sql_file = $tmp_dir . '/mirzadb_' . $uniq_id . '.sql';
$zip_file = $tmp_dir . '/mirzabackup_' . date("Y-m-d_H-i-s") . '_' . $uniq_id . '.zip';

try {
    // 3. Securely Dump Database
    $dbhost_safe = escapeshellarg(empty($dbhost) ? "127.0.0.1" : $dbhost);
    $dbuser_safe = escapeshellarg($usernamedb);
    $dbpass_safe = escapeshellarg($passworddb);
    $dbname_safe = escapeshellarg($dbname);
    $sql_file_safe = escapeshellarg($sql_file);

    $command = "mysqldump -h $dbhost_safe -u $dbuser_safe -p$dbpass_safe --no-tablespaces --ssl-mode=DISABLED $dbname_safe > $sql_file_safe 2>/dev/null";
    
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);

    if ($return_var !== 0 || !file_exists($sql_file)) {
        throw new Exception("Database export failed.");
    }

    // 4. Create Unified ZIP Archive
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("Failed to create ZIP file.");
    }

    // Add SQL Dump
    $zip->addFile($sql_file, 'database.sql');

    // Add Core config and text.json safely
    if (file_exists($sourcefir . '/config.php')) {
        $zip->addFile($sourcefir . '/config.php', 'config.php');
    }
    if (file_exists($sourcefir . '/text.json')) {
        $zip->addFile($sourcefir . '/text.json', 'text.json');
    }
    if (file_exists($sourcefir . '/MHSanaei-3.2.php')) {
        $zip->addFile($sourcefir . '/MHSanaei-3.2.php', 'MHSanaei-3.2.php');
    }

    // Add vpnbot directory content recursively (ignoring errors if empty or missing some parts)
    $vpnbot_dir = $sourcefir . '/vpnbot';
    if (is_dir($vpnbot_dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vpnbot_dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relativePath = 'vpnbot/' . substr($file->getPathname(), strlen($vpnbot_dir) + 1);
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $relativePath));
        }
    }

    $zip->close();

    // 5. Send to Telegram
    if (file_exists($zip_file)) {
        telegram('sendDocument', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reportbackup,
            'document' => new CURLFile($zip_file),
            'caption' => "📦 Full MirzaBot Backup\n📅 " . date("Y-m-d H:i:s") . "\n✅ Super Safe Backup (Unified)",
        ]);
    } else {
        throw new Exception("ZIP file not found after creation.");
    }

} catch (Exception $e) {
    telegram('sendmessage', [
        'chat_id' => $setting['Channel_Report'],
        'message_thread_id' => $reportbackup,
        'text' => "⚠️ Backup Error: " . $e->getMessage(),
    ]);
} finally {
    // 6. Super Safe Cleanup
    if (file_exists($sql_file)) {
        unlink($sql_file);
    }
    if (file_exists($zip_file)) {
        unlink($zip_file);
    }
}
?>