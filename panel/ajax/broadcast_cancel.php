<?php
try {
    require_once __DIR__ . '/../inc/config.php';
    require_auth();
    csrf_check_post();

    global $pdo;

    $cron_dir = realpath(__DIR__ . '/../../cronbot');
    if ($cron_dir === false) {
        $cron_dir = __DIR__ . '/../../cronbot';
    }

    $info_path = $cron_dir . DIRECTORY_SEPARATOR . 'info';
    $users_path = $cron_dir . DIRECTORY_SEPARATOR . 'users.json';
    $cancel_path = $cron_dir . DIRECTORY_SEPARATOR . 'cancel_broadcast';
    $lockFile = $cron_dir . DIRECTORY_SEPARATOR . 'sendmessage.lock';

    $lockFp = @fopen($lockFile, 'w+');
    $lockAcquired = false;
    if ($lockFp && flock($lockFp, LOCK_EX | LOCK_NB)) {
        $lockAcquired = true;
    }

    if ($lockAcquired) {
        @unlink($info_path);
        @unlink($users_path);
        @unlink($cancel_path);
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        try {
            $pdo->exec("UPDATE broadcast_history SET status = 'cancelled' WHERE status IN ('in_progress', 'pending', 'cancelling')");
        } catch (Throwable $ignored) {}
    } else {
        $payload = json_encode([
            'requested_at' => time(),
            'requested_by' => $_SESSION['admin_user'] ?? 'panel',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        file_put_contents($cancel_path, $payload, LOCK_EX);
        
        try {
            $pdo->exec("UPDATE broadcast_history SET status = 'cancelling' WHERE status IN ('in_progress', 'pending')");
        } catch (Throwable $ignored) {}
    }

    header("Location: ../broadcast.php");
    echo '<script>window.location.href = "../broadcast.php";</script>';
    exit;
} catch (Throwable $e) {
    header("Location: ../broadcast.php");
    echo '<script>window.location.href = "../broadcast.php";</script>';
    exit;
}

