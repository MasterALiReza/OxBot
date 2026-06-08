<?php
try {
    require_once __DIR__ . '/../inc/config.php';
    require_auth();
    csrf_check_post();

    global $pdo;

    $cron_dir = realpath(__DIR__ . '/../../cronbot');
    if ($cron_dir === false) {
        throw new RuntimeException('Cron directory not found.');
    }

    $info_path = $cron_dir . DIRECTORY_SEPARATOR . 'info';
    $users_path = $cron_dir . DIRECTORY_SEPARATOR . 'users.json';
    $cancel_path = $cron_dir . DIRECTORY_SEPARATOR . 'cancel_broadcast';

    $had_operation = is_file($info_path) || is_file($users_path);
    if (!$had_operation) {
        @unlink($cancel_path);
        echo '<div class="alert alert-warn">عملیات فعالی برای لغو پیدا نشد.</div>';
        exit;
    }

    $payload = json_encode([
        'requested_at' => time(),
        'requested_by' => $_SESSION['admin_user'] ?? 'panel',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false || file_put_contents($cancel_path, $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to register cancel request.');
    }

    try {
        $pdo->exec("UPDATE broadcast_history SET status = 'cancelled' WHERE status IN ('in_progress', 'pending', 'cancelling')");
    } catch (Throwable $ignored) {
    }

    @unlink($info_path);
    @unlink($users_path);

    echo '<div class="alert alert-success">عملیات همگانی با موفقیت لغو شد و قفل ارسال آزاد شد.</div>';
    echo '<script>setTimeout(() => window.location.reload(), 1200);</script>';
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" style="background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; color: #e74c3c; padding: 20px; border-radius: 12px; margin-bottom: 20px;">';
    echo '<strong>خطا در لغو عملیات:</strong><br>';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '</div>';
}
