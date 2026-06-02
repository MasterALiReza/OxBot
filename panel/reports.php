<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$totalUsers = 0;
$activePanels = 0;
$totalRevenue = 0;
$totalInvoices = 0;

try {
    $totalUsers = db_count($pdo, "SELECT COUNT(*) FROM user");
    $activePanels = db_count($pdo, "SELECT COUNT(*) FROM marzban_panel WHERE status = 'active'");
    $totalRevenue = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn')")->fetchColumn();
    $totalInvoices = db_count($pdo, "SELECT COUNT(*) FROM invoice");
} catch (Exception $e) {
    error_log('reports.php error: ' . $e->getMessage());
}

$pageTitle = $textbotlang['panel']['layoutPageTitleReports'];
$activeNav = 'reports';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="stats fade-up">
    <div class="stat">
        <div class="stat-label">تعداد کل کاربران</div>
        <div class="stat-num"><?= number_format($totalUsers) ?></div>
        <div class="stat-meta">نفر در ربات شما</div>
    </div>
    <div class="stat ok">
        <div class="stat-label">درآمد خالص ربات</div>
        <div class="stat-num">
            <?= $totalRevenue >= 1_000_000
                ? number_format($totalRevenue / 1_000_000, 1) . ' میلیون'
                : number_format($totalRevenue) ?>
        </div>
        <div class="stat-meta">تومان</div>
    </div>
    <div class="stat warn">
        <div class="stat-label">پنل‌های فعال</div>
        <div class="stat-num"><?= number_format($activePanels) ?></div>
        <div class="stat-meta">سرور متصل است</div>
    </div>
    <div class="stat">
        <div class="stat-label">فاکتورهای صادر شده</div>
        <div class="stat-num"><?= number_format($totalInvoices) ?></div>
        <div class="stat-meta">فاکتور</div>
    </div>
</div>

<div class="card fade-up" style="margin-top: 20px;">
    <div class="card-head">
        <div>
            <div class="card-title">نمودار گزارشات دقیق (به زودی)</div>
            <div class="card-subtitle">گزارشات دقیق روزانه و ماهانه به همراه چارت‌های پیشرفته در نسخه‌های بعدی پنل وب اضافه خواهد شد. در حال حاضر از بخش <span style="color:var(--ac)">گزارشات وضعیت</span> در ربات تلگرام استفاده کنید.</div>
        </div>
    </div>
    <div class="card-body" style="text-align: center; padding: 40px;">
        <svg viewBox="0 0 200 160" fill="none" style="width:150px; opacity:0.5; margin-bottom: 20px;">
            <rect x="20" y="100" width="30" height="40" fill="var(--ac)" rx="4"/>
            <rect x="60" y="70" width="30" height="70" fill="var(--ac)" rx="4"/>
            <rect x="100" y="40" width="30" height="100" fill="var(--ac)" rx="4"/>
            <rect x="140" y="90" width="30" height="50" fill="var(--ac)" rx="4"/>
            <path d="M10 150 L190 150" stroke="var(--bds)" stroke-width="4" stroke-linecap="round" />
        </svg>
        <h3 style="color:var(--mute)">داده‌های بیشتر به زودی...</h3>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
