<?php
// Buffer all output
ob_start();
require '../inc/config.php';
require_once '../inc/icons.php';
ob_end_clean();

if (!isset($_SESSION['agent_id'])) {
    http_response_code(403);
    echo '<div style="padding:20px;text-align:center;color:var(--no);">نشست منقضی شده است.</div>';
    exit;
}
$agent_id = (int) $_SESSION['agent_id'];

$old_cwd = getcwd();
chdir(__DIR__ . '/../../');
require_once 'function.php';
require_once 'panels.php';
chdir($old_cwd);

$id_invoice = $_GET['id_invoice'] ?? '';

if (empty($id_invoice)) {
    http_response_code(400);
    echo '<div style="padding:20px;text-align:center;color:var(--no);">درخواست نامعتبر است.</div>';
    exit;
}

try {
    $invoice = db_fetch($pdo, "SELECT * FROM invoice WHERE id_invoice = ? AND (id_user = ? OR refral = ?)", [$id_invoice, $agent_id, $agent_id]);
    if (!$invoice) {
        http_response_code(404);
        echo '<div style="padding:20px;text-align:center;color:var(--no);">سفارش مورد نظر یافت نشد.</div>';
        exit;
    }

    $panelInfo = db_fetch($pdo, "SELECT type FROM marzban_panel WHERE name_panel = ?", [$invoice['Service_location']]);
    $panelType = $panelInfo['type'] ?? '';

    $ManagePanel = new ManagePanel();
    $DataUserOut = $ManagePanel->DataUser($invoice['Service_location'], $invoice['username']);

    $invStatus = strtolower($invoice['Status'] ?? '');
    $isUnprovisioned = in_array($invStatus, ['unpaid', 'paying', 'pending', 'send_on_hold', 'cancled', 'canceled', 'waiting']);

    if (isset($DataUserOut['status']) && $DataUserOut['status'] == "Unsuccessful") {
        $err_msg = !empty($DataUserOut['msg']) ? htmlspecialchars(is_string($DataUserOut['msg']) ? $DataUserOut['msg'] : json_encode($DataUserOut['msg'])) : "نامشخص";
        
        if ($isUnprovisioned) {
            // Unpaid or Pending Orders UI
            echo '<div style="padding: 30px 20px; text-align: center; color: var(--text);">
                    <div style="margin-bottom: 20px;">
                        <span style="font-size: 3rem; color: var(--warn); opacity: 0.9;">' . icon('clock', 48) . '</span>
                    </div>
                    <h3 style="margin-bottom: 12px;">سفارش تکمیل نشده است</h3>
                    <p style="opacity: 0.8; margin-bottom: 24px; font-size: 0.95em;">این سفارش هنوز در وضعیت <strong>«' . htmlspecialchars($invStatus) . '»</strong> قرار دارد، بنابراین اطلاعات آن به سمت سرور ارسال نشده و کانفیگ ساخته نشده است.</p>
                    
                    <div style="background: var(--sf); border: 1px solid var(--bd); border-radius: 12px; padding: 16px; text-align: right; margin-bottom: 24px;">
                        <div style="font-weight: 600; margin-bottom: 10px;">جزئیات سفارش ثبت شده در ربات:</div>
                        <div style="font-size: 0.9em; opacity: 0.8; margin-bottom: 6px;">شناسه فاکتور: ' . htmlspecialchars($invoice['id_invoice']) . '</div>
                        <div style="font-size: 0.9em; opacity: 0.8; margin-bottom: 6px;">محصول: ' . htmlspecialchars($invoice['name_product']) . '</div>
                        <div style="font-size: 0.9em; opacity: 0.8;">مبلغ: ' . number_format((int)$invoice['price_product']) . ' تومان</div>
                        <a href="#" onclick="closeModal(\'manage-modal\'); openRenewModal(\'' . $id_invoice . '\', \'' . htmlspecialchars($invoice['username']) . '\', \'' . htmlspecialchars($invoice['Service_location']) . '\'); return false;" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top:15px;">
                            ' . icon('refresh-cw', 14) . ' تمدید سرویس
                        </a>
                    </div>
                  </div>';
        } else {
            // Created order but deleted from Marzban manually (Error State)
            echo '<div style="padding: 30px 20px; text-align: center; color: var(--text);">
                    <div style="margin-bottom: 20px;">
                        <span style="font-size: 3rem; color: var(--red); opacity: 0.9;">' . icon('alert-triangle', 48) . '</span>
                    </div>
                    <h3 style="margin-bottom: 12px;">خطا در یافتن سرویس</h3>
                    <p style="opacity: 0.8; margin-bottom: 10px; font-size: 0.95em;">کاربر در پنل یافت نشد یا سرور در دسترس نیست.</p>
                    <p style="font-size: 0.8em; opacity: 0.6; word-break: break-all; margin-bottom: 24px;">جزئیات خطا: ' . $err_msg . '</p>
                    
                    <div style="background: var(--sf); border: 1px solid var(--bd); border-radius: 12px; padding: 16px; text-align: right; margin-bottom: 24px;">
                        <div style="font-weight: 600; margin-bottom: 10px;">وضعیت فاکتور در ربات:</div>
                        <div style="font-size: 0.9em; opacity: 0.8;">' . htmlspecialchars($invStatus) . '</div>
                    </div>
                  </div>';
        }
        exit;
    }

    // Prepare status translation
    $statusMap = [
        'active' => ['tag-ok', $textbotlang['users']['status']['active'] ?? 'فعال'],
        'limited' => ['tag-warn', $textbotlang['users']['status']['limited'] ?? 'محدود شده'],
        'disabled' => ['tag-no', $textbotlang['users']['status']['disabled'] ?? 'غیرفعال'],
        'expired' => ['tag-no', $textbotlang['users']['status']['expired'] ?? 'منقضی شده'],
        'on_hold' => ['tag-plain', $textbotlang['users']['status']['on_hold'] ?? 'در انتظار'],
        'Unknown' => ['tag-plain', $textbotlang['users']['status']['unknown'] ?? 'نامشخص'],
        'deactivev' => ['tag-no', $textbotlang['users']['status']['disabled'] ?? 'غیرفعال'],
    ];

    $panelStatus = $DataUserOut['status'] ?? 'Unknown';
    $statusText = $statusMap[$panelStatus][1] ?? $statusMap['Unknown'][1];
    
    // Remove checkmark emoji from status text for cleaner UI in the web panel
    $statusText = trim(str_replace(['✅', '☑️', '✔', '🟢', '🔴'], '', $statusText));

    $statusClass = $statusMap[$panelStatus][0] ?? $statusMap['Unknown'][0];

    // Dates & JDF
    require_once __DIR__ . '/../../jdf.php';
    
    // Initial Purchase Date
    $purchaseDate = !empty($invoice['time_sell']) ? jdate('Y/m/d H:i', $invoice['time_sell']) : 'نامشخص';
    
    // Expiration date
    $expirationDate = !empty($DataUserOut['expire']) ? jdate('Y/m/d', $DataUserOut['expire']) : 'نامحدود';
    $timeDiff = !empty($DataUserOut['expire']) ? $DataUserOut['expire'] - time() : 0;
    $daysRemaining = $timeDiff > 0 ? floor($timeDiff / 86400) . ' روز' : 'منقضی / نامحدود';

    // Last Online
    $lastonline = 'نامشخص';
    if (isset($DataUserOut['online_at'])) {
        if ($DataUserOut['online_at'] == "online") {
            $lastonline = 'متصل 🟢';
        } elseif ($DataUserOut['online_at'] == "offline") {
            $lastonline = 'آفلاین 🔴';
        } elseif ($DataUserOut['online_at'] !== null) {
            $lastonline = jdate('Y/m/d H:i', strtotime($DataUserOut['online_at']));
        }
    }

    // Sub link updates
    $lastupdate = 'بروزرسانی نشده';
    if (!empty($DataUserOut['sub_updated_at'])) {
        $dateTime = new DateTime($DataUserOut['sub_updated_at'], new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
    }

    // Traffic Formatting
    function format_bytes_fa($bytes) {
        if ($bytes <= 0) return '۰ مگابایت';
        $mb = $bytes / pow(1024, 2);
        if ($mb < 1024) {
            return round($mb, 1) . ' مگابایت';
        } else {
            $gb = $bytes / pow(1024, 3);
            return round($gb, 2) . ' گیگابایت';
        }
    }

    $limitValue = (float)($DataUserOut['data_limit'] ?? 0);
    $usedTrafficValue = (float)($DataUserOut['used_traffic'] ?? 0);
    $remainingBytes = $limitValue - $usedTrafficValue;

    $LastTraffic = $limitValue > 0 ? format_bytes_fa($limitValue) : 'نامحدود';
    $RemainingVolume = $limitValue > 0 ? format_bytes_fa($remainingBytes) : 'نامحدود';
    $usedTrafficGb = $usedTrafficValue > 0 ? format_bytes_fa($usedTrafficValue) : 'مصرف نشده';

    // Percentage Calculation
    $remainingPercent = 100;
    if ($limitValue > 0) {
        $remainingPercent = (($limitValue - $usedTrafficValue) * 100) / $limitValue;
        if ($remainingPercent < 0) $remainingPercent = 0;
        $remainingPercent = round($remainingPercent, 2);
    }
    
    // Consumed percentage for progress bar
    $usedPercent = 100 - $remainingPercent;
    if ($usedPercent < 0) $usedPercent = 0;
    if ($usedPercent > 100) $usedPercent = 100;

    $subUrl = $DataUserOut['subscription_url'] ?? '';
    $configLinks = $DataUserOut['links'] ?? [];

    // FIX FOR WGDashboard: Often the "subscription_url" actually contains the raw WireGuard .conf content
    if (!empty($subUrl) && (stripos(trim($subUrl), '[Interface]') === 0 || stripos(trim($subUrl), 'PrivateKey') !== false)) {
        $configLinks[] = $subUrl;
        $subUrl = '';
    }

?>
<style>
    .service-details {
        direction: rtl;
        text-align: right;
        font-family: inherit;
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: var(--text);
    }
    .bento-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    @media (max-width: 480px) {
        .bento-grid {
            grid-template-columns: 1fr;
        }
    }
    .bento-card {
        background: var(--sf);
        border: 1px solid var(--bd);
        border-radius: 12px;
        padding: 14px 18px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.2s ease;
        box-shadow: var(--sh);
    }
    .bento-card:hover {
        border-color: var(--bds);
        transform: translateY(-2px);
    }
    .bento-full {
        grid-column: 1 / -1;
    }
    .card-title {
        font-size: 0.82em;
        color: var(--mute);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
    }
    .card-title svg {
        color: var(--ac);
        opacity: 0.85;
    }
    .card-value {
        font-size: 1.1em;
        font-weight: 600;
        word-break: break-all;
    }
    .card-desc {
        font-size: 0.78em;
        opacity: 0.6;
        margin-top: 6px;
    }
    .config-box {
        background: var(--sf2);
        border: 1px solid var(--bd);
        border-radius: 8px;
        padding: 10px 14px;
        font-family: monospace;
        font-size: 0.85em;
        word-break: break-all;
        white-space: pre-wrap;
        max-height: 150px;
        overflow-y: auto;
        color: var(--text2);
        margin-top: 6px;
        direction: ltr;
        text-align: left;
    }
    /* Collapsible Configurations Dropdown Styles */
    .au-configs-dropdown {
        margin-top: 12px;
        position: relative;
    }
    .au-configs-dropdown-toggle {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--sf2);
        border: 1px solid var(--bd);
        padding: 12px 16px;
        border-radius: 10px;
        color: var(--text);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        outline: none;
        font-size: 0.9em;
    }
    .au-configs-dropdown-toggle:hover {
        border-color: var(--ac);
        background: rgba(255, 255, 255, 0.04);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    .au-configs-dropdown-menu {
        display: none;
        flex-direction: column;
        gap: 12px;
        margin-top: 10px;
        animation: auSlideDown 0.3s ease forwards;
    }
    @keyframes auSlideDown {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .btn-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-top: 8px;
    }
    .btn-grid-full {
        grid-column: span 2;
    }
    .btn-sm-action {
        padding: 11px 16px;
        font-size: 0.85em;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: opacity 0.2s, background-color 0.2s;
        border: none;
    }
    .btn-sm-action:hover {
        opacity: 0.95;
    }
    .progress-bar-container {
        background: var(--sf3);
        height: 6px;
        border-radius: 3px;
        overflow: hidden;
        margin-top: 8px;
    }
    .progress-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        min-width: 3px;
    }
    .progress-bar {
        height: 100%;
        background: var(--ac);
        border-radius: 20px;
        transition: width 0.3s ease;
    }
    /* Collapsible Details Styles */
    details.configs-details {
        margin-top: 10px;
        width: 100%;
    }
    details.configs-details summary {
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 11px 15px;
        background: var(--sf2);
        border: 1px solid var(--bd);
        border-radius: 10px;
        font-size: 0.9em;
        font-weight: 500;
        outline: none;
        user-select: none;
        list-style: none;
        transition: background-color 0.2s;
    }
    details.configs-details summary::-webkit-details-marker {
        display: none;
    }
    details.configs-details summary:hover {
        background: var(--sf3);
    }
    details.configs-details .chevron {
        transition: transform 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    /* default RTL state: arrow-left (pointing left) */
    details.configs-details[open] .chevron {
        transform: rotate(-90deg); /* point down */
    }
</style>

<div class="service-details">
    
    <div class="bento-grid">
        <!-- Status & Location Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">
                    <?= icon('user', 15) ?>
                    <span>وضعیت و مشخصات عمومی</span>
                </div>
                <div class="card-value" style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                    <span class="tag <?= $statusClass ?>"><?= $statusText ?></span>
                    <span style="font-size: 0.85em; opacity: 0.9; font-weight: 500;"><?= htmlspecialchars($invoice['Service_location']) ?></span>
                </div>
            </div>
            <div class="card-desc">سفارش: <?= htmlspecialchars($invoice['id_invoice']) ?></div>
        </div>

        <!-- Purchase Info Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">
                    <?= icon('invoice', 15) ?>
                    <span>جزئیات خرید فاکتور</span>
                </div>
                <div class="card-value" style="font-size: 0.95em;">
                    <?= htmlspecialchars($invoice['name_product']) ?>
                </div>
            </div>
            <div class="card-desc">مبلغ پرداختی: <?= number_format((int)$invoice['price_product']) ?> تومان</div>
        </div>

        <!-- Volume Usage Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">
                    <?= icon('chart', 15) ?>
                    <span>حجم مصرفی و باقی‌مانده</span>
                </div>
                <div class="card-value" style="color: var(--text);"><?= $RemainingVolume ?></div>
                <div class="progress-bar-container">
                    <?php
                        // Direct Hex colors for inline styling reliability
                        $pgColor = $usedPercent > 80 ? '#f43f5e' : ($usedPercent > 50 ? '#f59e0b' : '#22c55e');
                    ?>
                    <div class="progress-bar-fill" style="width: <?= $usedPercent ?>%; background: <?= $pgColor ?>;"></div>
                </div>
            </div>
            <div class="card-desc">مصرف شده: <?= $usedTrafficGb ?> از <?= $LastTraffic ?> (<?= round($usedPercent, 1) ?>٪)</div>
        </div>

        <!-- Expiration Card -->
        <div class="bento-card">
            <div>
                <div class="card-title">
                    <?= icon('clock', 15) ?>
                    <span>اعتبار زمانی سرویس</span>
                </div>
                <div class="card-value"><?= $expirationDate ?></div>
            </div>
            <div class="card-desc">زمان باقی‌مانده: <?= $daysRemaining ?> (تاریخ خرید: <?= $purchaseDate ?>)</div>
        </div>

        <!-- Connection Details Card -->
        <div class="bento-card bento-full">
            <div class="card-title">
                <?= icon('activity', 15) ?>
                <span>جزئیات اتصال و به‌روزرسانی</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 0.85em; margin-top: 4px;">
                <div>
                    <span style="color: var(--mute);">آخرین اتصال:</span>
                    <div style="font-weight: 500; margin-top: 2px; color: <?= $panelType === 'WGDashboard' ? 'var(--dim)' : 'inherit' ?>;"><?= $panelType === 'WGDashboard' ? 'نامشخص (بدون پشتیبانی)' : $lastonline ?></div>
                </div>
                <div>
                    <span style="color: var(--mute);">بروزرسانی لینک:</span>
                    <div style="font-weight: 500; margin-top: 2px; color: <?= ($lastupdate == 'بروزرسانی نشده' || $panelType === 'WGDashboard') ? 'var(--dim)' : 'inherit' ?>;"><?= $panelType === 'WGDashboard' ? 'ندارد' : $lastupdate ?></div>
                </div>
                <div>
                    <span style="color: var(--mute);">سیستم‌عامل/کلاینت:</span>
                    <?php $ua = $DataUserOut['sub_last_user_agent'] ?? ''; ?>
                    <div style="font-weight: 500; margin-top: 2px; word-break: break-all; color: <?= (empty($ua) || $panelType === 'WGDashboard') ? 'var(--dim)' : 'inherit' ?>;" title="<?= htmlspecialchars($ua) ?>">
                        <?= htmlspecialchars($panelType === 'WGDashboard' ? 'نامشخص' : (empty($ua) ? 'نامشخص (در این پنل ثبت نشده)' : trunc($ua, 22))) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscription & Configs Card -->
        <div class="bento-card bento-full">
            <div class="card-title">
                <?= icon('link', 15) ?>
                <span>اشتراک و کانفیگ‌ها</span>
            </div>
            
            <?php if ($subUrl): ?>
            <!-- Subscription QR Code at the beginning -->
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-top: 8px; margin-bottom: 12px; gap: 6px;">
                <span style="font-size: 0.8em; color: var(--mute); font-weight: 500;">کد QR لینک اشتراک</span>
                <div style="padding: 10px; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); display: inline-block;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=<?= urlencode(trim($subUrl)) ?>" alt="Subscription QR Code" style="display: block; width: 130px; height: 130px;" />
                </div>
            </div>

            <!-- Subscription URL below QR -->
            <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px;">
                <span style="font-size: 0.8em; color: var(--mute);">لینک اشتراک:</span>
                <div style="display:flex; justify-content:space-between; align-items:center; background: var(--sf2); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--bd);">
                    <span style="font-size:0.85em; color:var(--ac); cursor:pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 75%; direction: ltr; text-align: left;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>alert('کپی شد!'))">
                        <?= htmlspecialchars($subUrl) ?>
                    </span>
                    <button class="btn btn-ghost btn-sm" style="padding: 4px 8px; display:flex; align-items:center; gap:4px; font-size: 0.8em;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($subUrl) ?>').then(()=>alert('کپی شد!'))">
                        <?= icon('copy', 13) ?> کپی
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($configLinks)): ?>
                <?php
                $v2rayConfigs = [];
                $wgConfigs = [];
                foreach ($configLinks as $index => $link) {
                    if (empty(trim($link))) continue;
                    $linkClean = trim($link);
                    $isWireguard = (stripos($linkClean, '[Interface]') !== false || stripos($linkClean, 'PrivateKey') !== false);
                    if ($isWireguard) {
                        $wgConfigs[] = ['index' => $index, 'link' => $linkClean];
                    } else {
                        $v2rayConfigs[] = ['index' => $index, 'link' => $linkClean];
                    }
                }
                ?>

                <!-- Render WireGuard Configs (if any) -->
                <?php if (!empty($wgConfigs)): ?>
                    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 14px;">
                        <?php foreach ($wgConfigs as $wg): 
                            $index = $wg['index'];
                            $linkClean = $wg['link'];
                        ?>
                            <div style="background: var(--sf2); border: 1px solid var(--bd); border-radius: 10px; padding: 14px; display:flex; flex-direction:column; gap:8px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size: 0.9em; font-weight: 500;">
                                    <span style="display: flex; align-items: center; gap: 6px;">
                                        <?= icon('sliders', 14) ?> کانفیگ وایرگارد
                                    </span>
                                    <div style="display:flex; gap:8px;">
                                        <button class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:4px;" onclick="downloadWGConfig(document.getElementById('wg-conf-<?= $index ?>').textContent, '<?= htmlspecialchars($invoice['username']) ?>_wg_<?= ($index + 1) ?>.conf')">
                                            <?= icon('download', 14) ?> دانلود
                                        </button>
                                        <button class="btn btn-ghost btn-sm" style="display:inline-flex; align-items:center; gap:4px;" onclick="navigator.clipboard.writeText(document.getElementById('wg-conf-<?= $index ?>').textContent).then(()=>alert('کپی شد!'))">
                                            <?= icon('copy', 14) ?> کپی
                                        </button>
                                    </div>
                                </div>
                                <div class="config-box" id="wg-conf-<?= $index ?>" style="margin-top: 4px;"><?= htmlspecialchars($linkClean) ?></div>
                                <div style="margin-top: 10px; border-top: 1px solid var(--bd); padding-top: 10px; text-align: center;">
                                    <span style="font-size: 0.8em; color: var(--mute); display: block; margin-bottom: 8px;"><?= icon('qr-code', 13) ?> بارکد کانفیگ جهت اسکن در گوشی</span>
                                    <div style="padding: 10px; background: #fff; border-radius: 8px; width: fit-content; margin-left: auto; margin-right: auto; box-shadow: var(--sh);">
                                        <div id="qr-wg-<?= $index ?>" style="width: 150px; height: 150px; margin: 0 auto;"></div>
                                    </div>
                                </div>
                                <script>
                                    setTimeout(function() {
                                        if(typeof QRCode !== 'undefined') {
                                            new QRCode(document.getElementById('qr-wg-<?= $index ?>'), {
                                                text: document.getElementById('wg-conf-<?= $index ?>').textContent,
                                                width: 150,
                                                height: 150,
                                                colorDark: "#000000",
                                                colorLight: "#ffffff",
                                                correctLevel: QRCode.CorrectLevel.L
                                            });
                                        }
                                    }, 100);
                                </script>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Render V2Ray Configs (if any) in collapsible dropdown -->
                <?php if (!empty($v2rayConfigs)): ?>
                    <div class="au-configs-dropdown">
                        <button type="button" class="au-configs-dropdown-toggle" onclick="toggleConfigsDropdown()">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <?= icon('sliders', 15) ?>
                                <span>📋 لیست کانفیگ‌های فعال (<?= count($v2rayConfigs) ?> کانفیگ)</span>
                            </span>
                            <svg id="dropdown-chevron-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.3s ease;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        
                        <div id="au-configs-dropdown-menu" class="au-configs-dropdown-menu">
                            <?php foreach ($v2rayConfigs as $v2idx => $v2): 
                                $index = $v2['index'];
                                $linkClean = $v2['link'];
                            ?>
                                <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--bd); border-radius: 8px; padding: 12px; display:flex; flex-direction:column; gap:6px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; font-size: 0.85em; font-weight: 500;">
                                        <span>کانفیگ #<?= ($v2idx + 1) ?></span>
                                        <button class="btn btn-ghost btn-sm" style="padding: 2px 6px; font-size: 0.8em; display:inline-flex; align-items:center; gap:4px;" onclick="navigator.clipboard.writeText(document.getElementById('wg-conf-<?= $index ?>').textContent).then(()=>alert('کپی شد!'))">
                                            <?= icon('copy', 12) ?> کپی کانفیگ
                                        </button>
                                    </div>
                                    <div class="config-box" id="wg-conf-<?= $index ?>" style="font-size: 0.8em; word-break: break-all; max-height: 60px; overflow-y: auto; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 6px; font-family: monospace; direction: ltr; text-align: left; border: 1px solid rgba(255,255,255,0.05); color: var(--mute); margin-top: 4px;"><?= htmlspecialchars($linkClean) ?></div>
                                    
                                    <!-- Config QR Code -->
                                    <details style="margin-top: 4px;" ontoggle="if(this.open && !this.dataset.qrRendered){ new QRCode(document.getElementById('qr-<?= $index ?>'), {text: document.getElementById('wg-conf-<?= $index ?>').textContent, width: 130, height: 130, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.L}); this.dataset.qrRendered = true; }">
                                        <summary style="font-size: 0.8em; color: var(--ac); cursor: pointer; list-style: none; outline: none; display: inline-flex; align-items: center; gap: 4px;">
                                            <?= icon('qr-code', 13) ?> نمایش بارکد (QR Code)
                                        </summary>
                                        <div style="text-align: center; padding: 8px; background: #fff; border-radius: 8px; margin-top: 6px; width: fit-content; margin-left: auto; margin-right: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                                            <div id="qr-<?= $index ?>" style="width: 130px; height: 130px; margin: 0 auto;"></div>
                                        </div>
                                    </details>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <script>
                        function toggleConfigsDropdown() {
                            const menu = document.getElementById('au-configs-dropdown-menu');
                            const chevron = document.getElementById('dropdown-chevron-icon');
                            const toggleBtn = document.querySelector('.au-configs-dropdown-toggle');
                            if (menu.style.display === 'none' || menu.style.display === '') {
                                menu.style.display = 'flex';
                                chevron.style.transform = 'rotate(180deg)';
                                toggleBtn.style.borderColor = 'var(--ac)';
                            } else {
                                menu.style.display = 'none';
                                chevron.style.transform = 'rotate(0deg)';
                                toggleBtn.style.borderColor = 'var(--bd)';
                            }
                        }
                    </script>
                <?php endif; ?>
            <?php else: ?>
                <div style="font-size: 0.8em; color: var(--mute); text-align: center; margin-top: 8px;">هیچ کانفیگی یافت نشد.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="btn-grid">
        <!-- Refresh (AJAX) -->
        <button type="button" class="btn-sm-action btn-grid-full" style="background: var(--sf3); color: var(--text); border: 1px solid var(--bds);" onclick="openManageModal('<?= $id_invoice ?>')">
            <?= icon('refresh-cw', 13) ?> بروزرسانی اطلاعات لحظه‌ای
        </button>

        <!-- Extend Service -->
        <button type="button" class="btn-sm-action btn-grid-full" style="background:#3b82f6; color:#fff;" onclick="closeModal('manage-modal'); openRenewModal('<?= $id_invoice ?>', '<?= htmlspecialchars($invoice['username']) ?>', '<?= htmlspecialchars($invoice['Service_location']) ?>')">
           <?= icon('plus', 13) ?> تمدید سرویس
        </button>
    </div>

</div>

<?php
} catch (Exception $e) {
    http_response_code(500);
    echo '<div style="padding:20px;text-align:center;color:var(--no); direction: rtl;">خطای سرور: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
