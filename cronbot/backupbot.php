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
    // 3. Securely Dump Database with throttling and multi-tier fallbacks (php-pro best practices)
    $dbuser_safe = escapeshellarg($usernamedb);
    $dbpass_safe = escapeshellarg($passworddb);
    $dbname_safe = escapeshellarg($dbname);
    $sql_file_safe = escapeshellarg($sql_file);

    $host_param = "";
    if (!empty($dbhost) && $dbhost !== "127.0.0.1" && strtolower($dbhost) !== "localhost") {
        $host_param = "-h " . escapeshellarg($dbhost) . " ";
    }

    $prefix = "";
    if (function_exists('exec')) {
        $checkNice = @exec("command -v nice 2>/dev/null");
        $checkIonice = @exec("command -v ionice 2>/dev/null");
        if (!empty($checkNice) && !empty($checkIonice)) {
            $prefix = "nice -n 19 ionice -c 3 ";
        }
    }

    $output = [];
    $return_var = 0;

    // Attempt 1: Standard unix socket or direct host connection
    $cmd1 = $prefix . "mysqldump {$host_param}-u $dbuser_safe -p$dbpass_safe --no-tablespaces $dbname_safe > $sql_file_safe 2>/dev/null";
    exec($cmd1, $output, $return_var);

    // Attempt 2: If Attempt 1 failed (e.g. MySQL 8 requiring ssl-mode=DISABLED), try with ssl-mode
    if ($return_var !== 0 || !file_exists($sql_file) || filesize($sql_file) === 0) {
        $cmd2 = $prefix . "mysqldump {$host_param}-u $dbuser_safe -p$dbpass_safe --no-tablespaces --ssl-mode=DISABLED $dbname_safe > $sql_file_safe 2>/dev/null";
        exec($cmd2, $output, $return_var);
    }

    // Attempt 3: If still failed, try Docker exec fallback if running in containerized environment
    if ($return_var !== 0 || !file_exists($sql_file) || filesize($sql_file) === 0) {
        $container_check = @exec("docker ps -q --filter 'name=mysql' --no-trunc 2>/dev/null | head -n 1");
        if (!empty($container_check)) {
            $docker_cmd = $prefix . "docker exec " . escapeshellarg(trim($container_check)) . " mysqldump -u $dbuser_safe -p$dbpass_safe --no-tablespaces $dbname_safe > $sql_file_safe 2>/dev/null";
            exec($docker_cmd, $output, $return_var);
        }
    }

    if ($return_var !== 0 || !file_exists($sql_file) || filesize($sql_file) === 0) {
        throw new Exception("Database export failed.");
    }

    // 4. Create Unified ZIP Archive (Prefer throttled system zip to prevent memory spikes, fallback to ZipArchive)
    $hasSysZip = false;
    if (function_exists('exec')) {
        $checkZip = @exec("command -v zip 2>/dev/null");
        if (!empty($checkZip)) {
            $hasSysZip = true;
        }
    }

    if ($hasSysZip) {
        $staging_dir = $tmp_dir . '/mirzabackup_staging_' . $uniq_id;
        mkdir($staging_dir, 0755, true);
        copy($sql_file, $staging_dir . '/database.sql');

        if (file_exists($sourcefir . '/config.php')) {
            copy($sourcefir . '/config.php', $staging_dir . '/config.php');
        }
        if (file_exists($sourcefir . '/text.json')) {
            copy($sourcefir . '/text.json', $staging_dir . '/text.json');
        }
        if (file_exists($sourcefir . '/MHSanaei-3.2.php')) {
            copy($sourcefir . '/MHSanaei-3.2.php', $staging_dir . '/MHSanaei-3.2.php');
        }
        if (is_dir($sourcefir . '/vpnbot')) {
            @exec("cp -r " . escapeshellarg($sourcefir . '/vpnbot') . " " . escapeshellarg($staging_dir . '/vpnbot') . " 2>/dev/null");
        }

        $zip_cmd = $prefix . "zip -r -q " . escapeshellarg($zip_file) . " . 2>/dev/null";
        @exec("cd " . escapeshellarg($staging_dir) . " && " . $zip_cmd);
        @exec("rm -rf " . escapeshellarg($staging_dir) . " 2>/dev/null");
    } else {
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

        // Add vpnbot directory content recursively
        $vpnbot_dir = $sourcefir . '/vpnbot';
        if (is_dir($vpnbot_dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vpnbot_dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $relativePath = 'vpnbot/' . substr($file->getPathname(), strlen($vpnbot_dir) + 1);
                $zip->addFile($file->getPathname(), str_replace('\\', '/', $relativePath));
            }
        }

        $zip->close();
    }

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