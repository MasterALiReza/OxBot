<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// 1. Basic Stats
$totalUsers = 0;
$newToday = 0;
$totalRevenue = 0;
$todayRevenue = 0;
$activeNow = 0;
$activePanels = 0;
$pendingPay = 0;
$txToday = 0;

try {
    $totalUsers = db_count($pdo, "SELECT COUNT(*) FROM user");
    $newToday = db_count($pdo, "SELECT COUNT(*) FROM user WHERE register > ?", [strtotime('today')]);
} catch (Exception $e) {}

try {
    $totalRevenue = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold')")->fetchColumn();
    $todayRevenue = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND time_sell > ?", [strtotime('today')])->fetchColumn();
    $activeNow = db_count($pdo, "SELECT COUNT(*) FROM invoice WHERE Status='active'");
} catch (Exception $e) {}

try {
    $activePanels = db_count($pdo, "SELECT COUNT(*) FROM marzban_panel WHERE status = 'active'");
} catch (Exception $e) {}

try {
    $pendingPay = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE payment_Status='waiting'");
    $txToday = db_count($pdo, "SELECT COUNT(*) FROM Payment_report WHERE time > ?", [strtotime('today')]);
} catch (Exception $e) {}

// 2. Chart Data (Last 7 Days)
$chartLabels = [];
$chartData = [];
$persianDays = [
    'Saturday' => 'شنبه', 'Sunday' => 'یکشنبه', 'Monday' => 'دوشنبه',
    'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه', 'Thursday' => 'پنجشنبه', 'Friday' => 'جمعه'
];

try {
    for ($i = 6; $i >= 0; $i--) {
        $startOfDay = strtotime("-$i days 00:00:00");
        $endOfDay = strtotime("-$i days 23:59:59");
        $dayRev = (int) db_query($pdo, "SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') AND time_sell BETWEEN ? AND ?", [$startOfDay, $endOfDay])->fetchColumn();
        
        $chartLabels[] = $persianDays[date('l', $startOfDay)];
        $chartData[] = $dayRev;
    }
} catch (Exception $e) {}

// 3. Best Selling Products
$bestSelling = [];
try {
    $bestSelling = db_fetchAll($pdo, "
        SELECT name_product, COUNT(*) as sales_count, SUM(price_product) as total_earned 
        FROM invoice 
        WHERE Status IN ('active','end_of_time','end_of_volume','sendedwarn','send_on_hold') 
        GROUP BY name_product 
        ORDER BY sales_count DESC 
        LIMIT 5
    ");
} catch (Exception $e) {}

// 4. Recent Items
$recentInvoices = [];
$recentUsers = [];
try {
    $recentInvoices = db_fetchAll($pdo, "SELECT * FROM invoice ORDER BY time_sell DESC LIMIT 6");
    $recentUsers = db_fetchAll($pdo, "SELECT * FROM user ORDER BY register DESC LIMIT 6");
} catch (Exception $e) {}

$pageTitle = $textbotlang['panel']['dashboardTitle'];
$activeNav = 'dashboard';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Top Statistics Cards -->
<div class="stats fade-up" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
    <div class="stat">
        <div class="stat-label"><?= $textbotlang['panel']['dashTotalUsers'] ?></div>
        <div class="stat-num"><?= number_format($totalUsers) ?></div>
        <div class="stat-meta"><?= $newToday > 0 ? '<span class="up">+' . $newToday . $textbotlang['panel']['dashTodaySpan'] : $textbotlang['panel']['dashNoChange'] ?></div>
    </div>
    
    <div class="stat ok">
        <div class="stat-label"><?= $textbotlang['panel']['dashTotalRevenue'] ?></div>
        <div class="stat-num">
            <?= $totalRevenue >= 1_000_000
                ? number_format($totalRevenue / 1_000_000, 1) . $textbotlang['panel']['dashUnitMillionToman']
                : number_format($totalRevenue) . $textbotlang['panel']['dashUnitToman'] ?>
        </div>
        <div class="stat-meta"><?= $textbotlang['panel']['dashTotalSales'] ?></div>
    </div>
    
    <div class="stat" style="border-left: 3px solid var(--ac);">
        <div class="stat-label"><?= $textbotlang['panel']['dashTodayRevenue'] ?></div>
        <div class="stat-num" style="color:var(--ac)">
            <?= $todayRevenue >= 1_000_000
                ? number_format($todayRevenue / 1_000_000, 1) . $textbotlang['panel']['dashUnitMillionToman']
                : number_format($todayRevenue) . $textbotlang['panel']['dashUnitToman'] ?>
        </div>
        <div class="stat-meta">درآمد خالص امروز</div>
    </div>

    <div class="stat warn">
        <div class="stat-label"><?= $textbotlang['panel']['dashActiveService'] ?></div>
        <div class="stat-num"><?= number_format($activeNow) ?></div>
    </div>
    
    <div class="stat" style="border-left: 3px solid #6366f1;">
        <div class="stat-label"><?= $textbotlang['panel']['dashActivePanels'] ?></div>
        <div class="stat-num" style="color:#6366f1;"><?= number_format($activePanels) ?></div>
    </div>

    <div class="stat <?= $pendingPay > 0 ? 'no' : '' ?>">
        <div class="stat-label"><?= $pendingPay > 0 ? $textbotlang['panel']['dashPendingPayment'] : $textbotlang['panel']['dashTodayTransaction'] ?></div>
        <div class="stat-num" style="<?= $pendingPay > 0 ? 'color:var(--no)' : '' ?>">
            <?= number_format($pendingPay > 0 ? $pendingPay : $txToday) ?>
        </div>
        <div class="stat-meta">
            <?= $pendingPay > 0 ? $textbotlang['panel']['dashReviewLink'] : $textbotlang['panel']['dashStatusRegistered'] ?>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
    
    <!-- Left Column: Chart & Recent Orders -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        
        <!-- Sales Chart -->
        <div class="card fade-up">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= $textbotlang['panel']['dashSalesChartTitle'] ?></div>
                    <div class="card-subtitle">مجموع فروش در هر روز (تومان)</div>
                </div>
            </div>
            <div class="card-body" style="position: relative; height: 250px; width: 100%;">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="card fade-up">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= $textbotlang['panel']['dashRecentOrders'] ?></div>
                    <div class="card-subtitle"><?= count($recentInvoices) ?> <?= $textbotlang['panel']['dashRecentItem'] ?></div>
                </div>
                <a href="invoice.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['dashViewAll'] ?></a>
            </div>
            <div class="tbl-wrap">
                <table class="tbl-sm">
                    <thead>
                        <tr>
                            <th><?= $textbotlang['panel']['dashColUser'] ?></th>
                            <th><?= $textbotlang['panel']['dashColProduct'] ?></th>
                            <th><?= $textbotlang['panel']['dashColAmount'] ?></th>
                            <th><?= $textbotlang['panel']['dashColStatus'] ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentInvoices)): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty" style="padding:24px">
                                        <p><?= $textbotlang['panel']['dashNoOrdersYet'] ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php else:
                            $statusMap = [
                                'active' => ['tag-ok', $textbotlang['panel']['dashStatusActive']],
                                'end_of_time' => ['tag-warn', $textbotlang['panel']['dashStatusExpired']],
                                'end_of_volume' => ['tag-no', $textbotlang['panel']['dashStatusVolumeFinished']],
                                'sendedwarn' => ['tag-warn', $textbotlang['panel']['dashStatusWarning']],
                                'send_on_hold' => ['tag-plain', $textbotlang['panel']['dashStatusWaiting']],
                            ];
                            foreach ($recentInvoices as $inv):
                                [$tagClass, $label] = $statusMap[$inv['Status'] ?? ''] ?? ['tag-plain', $inv['Status'] ?? '—'];
                                ?>
                                <tr>
                                    <td class="cm cf"><?= htmlspecialchars($inv['id_user'] ?? '—') ?></td>
                                    <td class="cs" style="max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        <?= htmlspecialchars(trunc($inv['name_product'] ?? '—', 20)) ?>
                                    </td>
                                    <td class="cn" style="white-space:nowrap">
                                        <?= number_format((int) ($inv['price_product'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['dashTomanShort'] ?></span>
                                    </td>
                                    <td><span class="tag <?= $tagClass ?>"><?= $label ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    
    <!-- Right Column: Best Selling & Recent Users -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        
        <!-- Best Selling Products -->
        <div class="card fade-up">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= $textbotlang['panel']['dashBestSellingTitle'] ?></div>
                    <div class="card-subtitle">برترین محصولات ربات</div>
                </div>
            </div>
            <div class="tbl-wrap">
                <table class="tbl-sm">
                    <thead>
                        <tr>
                            <th><?= $textbotlang['panel']['dashColProduct'] ?></th>
                            <th><?= $textbotlang['panel']['dashColSalesCount'] ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bestSelling)): ?>
                            <tr>
                                <td colspan="2">
                                    <div class="empty" style="padding:24px"><p>رکوردی یافت نشد</p></div>
                                </td>
                            </tr>
                        <?php else:
                            foreach ($bestSelling as $prod): ?>
                                <tr>
                                    <td class="cs" style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;">
                                        <?= htmlspecialchars(trunc($prod['name_product'] ?? '—', 20)) ?>
                                    </td>
                                    <td class="cn">
                                        <span class="tag tag-ok" style="background:var(--sf);border:1px solid var(--bd)"><?= number_format((int)$prod['sales_count']) ?> فروش</span>
                                    </td>
                                </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="card fade-up">
            <div class="card-head">
                <div>
                    <div class="card-title"><?= $textbotlang['panel']['dashRecentUsers'] ?></div>
                    <div class="card-subtitle"><?= count($recentUsers) ?> <?= $textbotlang['panel']['dashRecentItem2'] ?></div>
                </div>
                <a href="users.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['dashViewAll2'] ?></a>
            </div>
            <div class="tbl-wrap">
                <table class="tbl-sm">
                    <thead>
                        <tr>
                            <th><?= $textbotlang['panel']['dashColName'] ?></th>
                            <th><?= $textbotlang['panel']['dashColBalance'] ?></th>
                            <th><?= $textbotlang['panel']['dashColGroup'] ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentUsers)): ?>
                            <tr>
                                <td colspan="3">
                                    <div class="empty" style="padding:24px"><p><?= $textbotlang['panel']['dashNoUsersYet'] ?></p></div>
                                </td>
                            </tr>
                        <?php else:
                            foreach ($recentUsers as $u):
                                $agent = $u['agent'] ?? 'f';
                                $isBlocked = ($u['User_Status'] ?? '') === 'block';
                                $name = $u['namecustom'] ?? '';
                                if ($name === 'none') $name = '';
                                $uname = $u['username'] ?? '';
                                if ($uname === 'none') $uname = '';
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($name): ?>
                                            <span class="cs"><?= htmlspecialchars(trunc($name, 14)) ?></span>
                                        <?php elseif ($uname): ?>
                                            <span class="cm" style="color:var(--ac)">@<?= htmlspecialchars(trunc($uname, 12)) ?></span>
                                        <?php else: ?>
                                            <span class="cf"><?= htmlspecialchars($u['id']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cn" style="white-space:nowrap">
                                        <?= number_format((int) ($u['Balance'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['dashTomanShort2'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($isBlocked): ?>
                                            <span class="tag tag-no" style="font-size:.65rem"><?= $textbotlang['panel']['dashLabelBlocked'] ?></span>
                                        <?php else: ?>
                                            <span class="tag <?= user_role_tag($agent) ?>" style="font-size:.65rem">
                                                <?= user_role_label($agent) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    // Check if the theme is dark or light from CSS variables
    const isDark = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim() === '#0f172a' || document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? '#1e293b' : '#e2e8f0';

    const labels = <?= json_encode($chartLabels) ?>;
    const data = <?= json_encode($chartData) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'درآمد (تومان)',
                data: data,
                borderColor: '#14b8a6', // matching var(--ac)
                backgroundColor: 'rgba(20, 184, 166, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#14b8a6',
                pointBorderColor: isDark ? '#1e293b' : '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                    titleColor: isDark ? '#f8fafc' : '#0f172a',
                    bodyColor: isDark ? '#cbd5e1' : '#475569',
                    borderColor: isDark ? '#334155' : '#cbd5e1',
                    borderWidth: 1,
                    padding: 10,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return new Intl.NumberFormat('fa-IR').format(context.parsed.y) + ' تومان';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: textColor, font: { family: 'Vazirmatn, sans-serif' } }
                },
                y: {
                    grid: { color: gridColor, drawBorder: false },
                    ticks: { 
                        color: textColor,
                        font: { family: 'Vazirmatn, sans-serif' },
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000) + 'M';
                            if (value >= 1000) return (value / 1000) + 'K';
                            return value;
                        }
                    },
                    beginAtZero: true
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });
});
</script>

<style>
/* Responsive tweaks for the dashboard grid */
@media (max-width: 992px) {
    div[style*="grid-template-columns: 2fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>