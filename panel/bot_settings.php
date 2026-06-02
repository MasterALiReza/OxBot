<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Fetch settings
try {
    $row = db_fetch($pdo, "SELECT * FROM setting LIMIT 1");
} catch (Exception $e) {
    $row = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    
    $bot_status = $_POST['Bot_Status'] ?? 'botstatusoff';
    $channel_report = $_POST['Channel_Report'] ?? '';
    $agent_req_price = $_POST['agentreqprice'] ?? '0';

    try {
        db_query(
            $pdo,
            "UPDATE setting SET Bot_Status = ?, Channel_Report = ?, agentreqprice = ?",
            [$bot_status, $channel_report, $agent_req_price]
        );
        flash('success', $textbotlang['panel']['botSettingsSuccess']);
    } catch (Exception $e) {
        flash('error', $textbotlang['panel']['botSettingsError']);
    }
    
    header('Location: bot_settings.php');
    exit;
}

$tab = $_GET['tab'] ?? 'general';

$tabs = [
    'general' => ['icon' => 'settings', 'label' => $textbotlang['panel']['botSettingsTabGeneral']],
    'financial' => ['icon' => 'card', 'label' => $textbotlang['panel']['botSettingsTabFinancial']],
    'shop' => ['icon' => 'package', 'label' => $textbotlang['panel']['botSettingsTabShop']],
];

$pageTitle = $textbotlang['panel']['layoutPageTitleBotSettings'];
$activeNav = 'bot_settings';
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;gap:4px;margin-bottom:18px;background:var(--sf);border:1px solid var(--bd);border-radius:10px;padding:5px;overflow-x:auto" class="fade-up">
    <?php foreach ($tabs as $key => $tab_data): ?>
        <a href="?tab=<?= $key ?>"
            style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:7px;font-size:.82rem;font-weight:600;white-space:nowrap;flex-shrink:0;transition:all .15s;text-decoration:none;
                  <?= $tab === $key ? 'background:var(--ac);color:#fff;box-shadow:0 0 14px var(--acg)' : 'color:var(--mute)' ?>">
            <?= icon($tab_data['icon'], 15) ?> <?= $tab_data['label'] ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card fade-up">
    <div class="card-head">
        <div>
            <div class="card-title"><?= $textbotlang['panel']['botSettingsHeading'] ?></div>
        </div>
    </div>
    
    <form method="POST" class="card-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        
        <?php if ($tab === 'general'): ?>
            <div class="field">
                <label><?= $textbotlang['panel']['botSettingsBotStatus'] ?></label>
                <select name="Bot_Status" class="select">
                    <option value="botstatuson" <?= ($row['Bot_Status'] ?? '') === 'botstatuson' ? 'selected' : '' ?>>روشن</option>
                    <option value="botstatusoff" <?= ($row['Bot_Status'] ?? '') === 'botstatusoff' ? 'selected' : '' ?>>خاموش</option>
                </select>
            </div>
            <div class="field">
                <label><?= $textbotlang['panel']['botSettingsChannelReport'] ?></label>
                <input type="text" name="Channel_Report" class="input" value="<?= htmlspecialchars($row['Channel_Report'] ?? '') ?>" placeholder="-100xxxxxxxxx">
            </div>
            
        <?php elseif ($tab === 'financial'): ?>
            <div class="field">
                <label><?= $textbotlang['panel']['botSettingsAgentReqPrice'] ?></label>
                <input type="number" name="agentreqprice" class="input" value="<?= htmlspecialchars($row['agentreqprice'] ?? '0') ?>">
            </div>
            <div class="field">
                <p style="color:var(--mute); font-size:0.8rem; margin-top:10px;">
                    تنظیمات درگاه‌های پرداخت (زرین پال، کارت به کارت و...) به زودی در این قسمت اضافه خواهد شد. در حال حاضر از طریق ربات تلگرام آن‌ها را مدیریت کنید.
                </p>
            </div>
            
        <?php elseif ($tab === 'shop'): ?>
            <div class="field">
                <p style="color:var(--mute); font-size:0.8rem;">
                    تنظیمات فروشگاه (حداقل خرید، تخفیف‌ها و...) به زودی اضافه می‌شود. لطفاً از طریق ربات تلگرام استفاده کنید.
                </p>
            </div>
        <?php endif; ?>
        
        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> <?= $textbotlang['panel']['botSettingsSaveBtn'] ?></button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
