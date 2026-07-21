<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$currentTab = $_GET['tab'] ?? 'gifts';
$db_error = null;

// ─── POST: Add / Delete ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check_post();

    $action = $_POST['action'];

    if ($action === 'add_gift') {
        $code = trim($_POST['code'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $limituse = trim($_POST['limituse'] ?? '1');

        if ($code === '' || $price === '' || !is_numeric($price) || !is_numeric($limituse)) {
            flash('error', 'لطفا تمامی فیلدهای کد هدیه را به درستی وارد کنید.');
        } else {
            try {
                $exists = db_fetch($pdo, "SELECT id FROM Discount WHERE code = ?", [$code]);
                if ($exists) {
                    flash('error', 'این کد هدیه از قبل وجود دارد.');
                } else {
                    db_query($pdo, "INSERT INTO Discount (code, price, limituse, limitused) VALUES (?, ?, ?, '0')", [$code, $price, $limituse]);
                    flash('success', 'کد هدیه با موفقیت ایجاد شد.');
                }
            } catch (Exception $e) {
                flash('error', 'خطا در افزودن کد هدیه: ' . $e->getMessage());
            }
        }
        header('Location: discounts.php?tab=gifts');
        exit;
    }

    if ($action === 'add_discount') {
        $codeDiscount = trim($_POST['codeDiscount'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $limitDiscount = trim($_POST['limitDiscount'] ?? '1');
        $agent = trim($_POST['agent'] ?? 'allusers');
        $usefirst = trim($_POST['usefirst'] ?? '0');
        $useuser = trim($_POST['useuser'] ?? '1');
        $code_product = trim($_POST['code_product'] ?? 'all');
        $code_panel = trim($_POST['code_panel'] ?? '/all');
        $time_hours = trim($_POST['time_hours'] ?? '0');
        $type = trim($_POST['type'] ?? 'all');

        if ($codeDiscount === '' || $price === '' || !is_numeric($price) || !is_numeric($limitDiscount)) {
            flash('error', 'لطفا مقادیر ضروری کد تخفیف را به درستی وارد کنید.');
        } else {
            $time_val = (int)$time_hours > 0 ? (time() + ((int)$time_hours * 3600)) : 0;

            try {
                $exists = db_fetch($pdo, "SELECT id FROM DiscountSell WHERE codeDiscount = ?", [$codeDiscount]);
                if ($exists) {
                    flash('error', 'این کد تخفیف از قبل وجود دارد.');
                } else {
                    db_query($pdo, "INSERT INTO DiscountSell (codeDiscount, price, limitDiscount, agent, usefirst, useuser, code_product, code_panel, time, type, usedDiscount) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0')", 
                    [$codeDiscount, $price, $limitDiscount, $agent, $usefirst, $useuser, $code_product, $code_panel, (string)$time_val, $type]);
                    flash('success', 'کد تخفیف با موفقیت ایجاد شد.');
                }
            } catch (Exception $e) {
                flash('error', 'خطا در افزودن کد تخفیف: ' . $e->getMessage());
            }
        }
        header('Location: discounts.php?tab=discounts');
        exit;
    }
}

// ─── GET: Delete ─────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['type'])) {
    csrf_check_get();

    $id = (int)$_GET['id'];
    $delType = $_GET['type'];
    
    if ($id > 0) {
        try {
            if ($delType === 'gift') {
                db_query($pdo, "DELETE FROM Discount WHERE id = ?", [$id]);
                flash('success', 'کد هدیه با موفقیت حذف شد.');
                header('Location: discounts.php?tab=gifts');
                exit;
            } elseif ($delType === 'discount') {
                db_query($pdo, "DELETE FROM DiscountSell WHERE id = ?", [$id]);
                flash('success', 'کد تخفیف با موفقیت حذف شد.');
                header('Location: discounts.php?tab=discounts');
                exit;
            }
        } catch (Exception $e) {
            flash('error', 'خطا در حذف کد.');
        }
    }
    header('Location: discounts.php?tab=' . $currentTab);
    exit;
}

// ─── Fetch lists ─────────────────────────────────────────────────────────────
$gifts = [];
$discounts = [];
$logs = [];

try {
    // 1. Fetch Gifts
    $gifts = db_fetchAll($pdo, "SELECT * FROM Discount ORDER BY id DESC");
    
    // 2. Fetch Discounts
    $discounts = db_fetchAll($pdo, "SELECT * FROM DiscountSell ORDER BY id DESC");
    
    // 3. Fetch Logs
    $filterCode = $_GET['code'] ?? null;
    $logQuery = "
        SELECT g.*, u.username, u.namecustom 
        FROM Giftcodeconsumed g
        LEFT JOIN user u ON u.id COLLATE utf8mb4_unicode_ci = g.id_user COLLATE utf8mb4_unicode_ci
    ";
    
    if ($filterCode) {
        $logs = db_fetchAll($pdo, $logQuery . " WHERE g.code = ? ORDER BY g.id DESC LIMIT 500", [$filterCode]);
    } else {
        $logs = db_fetchAll($pdo, $logQuery . " ORDER BY g.id DESC LIMIT 500");
    }

} catch (Exception $e) {
    $db_error = $e->getMessage();
}

$products = [];
$panels = [];
try {
    $products = db_fetchAll($pdo, "SELECT * FROM product ORDER BY id DESC");
    $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel ORDER BY id DESC");
} catch (Exception $e) {}

$pageTitle = 'مدیریت تخفیف‌ها';
$pageLede = 'مدیریت کدهای هدیه (کیف پول) و تخفیف (سرویس) با رهگیری لحظه‌ای';
$activeNav = 'discounts';
include __DIR__ . '/inc/layout_head.php';

// HELPER FUNCTION: capacity badge
function getCapacityBadge($used, $total) {
    $used = (int)$used;
    $total = (int)$total;
    if ($total <= 0) return '<span class="tag tag-ok">نامحدود</span>';
    
    $pct = ($used / $total) * 100;
    $colorClass = 'tag-ok';
    if ($pct >= 80) $colorClass = 'tag-warn';
    elseif ($pct >= 50) $colorClass = 'tag-info';
    if ($pct >= 100) $colorClass = 'tag-danger';
    
    $pctStr = round($pct) . '%';
    return '<div style="display:flex; align-items:center; gap:8px;">
        <span class="tag ' . $colorClass . '" style="min-width:60px; text-align:center;">' . $used . ' / ' . $total . '</span>
        <div style="flex:1; height:6px; background:var(--bg-lighter); border-radius:3px; overflow:hidden;">
            <div style="height:100%; width:' . min($pct, 100) . '%; background:var(--primary); transition:width 0.3s; ' . ($pct >= 100 ? 'background:var(--danger);' : '') . '"></div>
        </div>
    </div>';
}

function getTimeBadge($timeStr) {
    $t = (int)$timeStr;
    if ($t === 0) return '<span class="tag tag-ok">نامحدود</span>';
    
    $diff = $t - time();
    if ($diff <= 0) return '<span class="tag tag-danger">منقضی شده</span>';
    
    $hours = floor($diff / 3600);
    $days = floor($hours / 24);
    
    if ($days > 0) return '<span class="tag tag-info">⏱ ' . $days . ' روز مانده</span>';
    return '<span class="tag tag-warn">⏱ ' . $hours . ' ساعت مانده</span>';
}
?>

<style>
/* Industrial Utilitarian Wizard Styles */
.wizard-steps {
    display: flex;
    gap: 10px;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border);
    padding-bottom: 1rem;
}
.wizard-step {
    flex: 1;
    padding: 10px;
    background: var(--bg-lighter);
    border-radius: 6px;
    text-align: center;
    color: var(--text-muted);
    font-weight: bold;
    font-size: 0.9em;
    border: 1px solid transparent;
    transition: all 0.2s;
    cursor: default;
}
.wizard-step.active {
    background: rgba(0, 212, 170, 0.1);
    color: var(--primary);
    border-color: var(--primary);
}
.wizard-step.completed {
    background: var(--bg-card);
    color: var(--text);
    border-color: var(--border);
}

.wizard-panel {
    display: none;
    animation: fadeIn 0.3s ease;
}
.wizard-panel.active {
    display: block;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

.code-badge {
    background: var(--bg-lighter);
    padding: 4px 8px;
    border-radius: 4px;
    font-family: monospace;
    letter-spacing: 1px;
    border: 1px dashed var(--border);
    display: inline-block;
}

/* Responsive Premium Tabs */
.tab-container {
    display: flex;
    gap: 0.5rem;
    background: var(--sf2);
    padding: 0.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
    border: 1px solid var(--bd);
}
.tab-link {
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.2s;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--mute);
}
.tab-link:hover {
    color: var(--text);
    background: var(--sf3);
}
.tab-link.active {
    background: var(--ac);
    color: var(--btn-ac-text, #fff) !important;
    box-shadow: var(--sh);
}
.tab-link.log-tab {
    margin-right: auto;
}
@media (max-width: 768px) {
    .tab-container {
        flex-direction: column;
        align-items: stretch;
        gap: 0.25rem;
    }
    .tab-link {
        justify-content: center;
        width: 100%;
    }
    .tab-link.log-tab {
        margin-right: 0;
    }
}
</style>

<div class="card fade-up">
    <?php if ($db_error): ?>
        <div class="alert alert-danger" style="margin-bottom:1rem;">
            خطا در دیتابیس: <?= htmlspecialchars($db_error) ?>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title">
                لیست کدها و لاگ‌ها
                <?php if ($currentTab === 'logs' && $filterCode): ?>
                    <a href="?tab=logs" class="tag tag-info" style="font-size:0.8em; margin-right:10px; text-decoration:none; display:inline-flex; align-items:center; gap:5px; background:var(--primary); color:white; padding:4px 10px; border-radius:12px;">
                        فیلتر: <?= htmlspecialchars($filterCode) ?> <?= icon('x', 14) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="toolbar-end">
            <?php if ($currentTab === 'gifts'): ?>
                <button class="btn btn-primary btn-sm" onclick="openWizard('gift')">
                    <?= icon('plus', 14) ?> افزودن کد هدیه
                </button>
            <?php elseif ($currentTab === 'discounts'): ?>
                <button class="btn btn-primary btn-sm" onclick="openWizard('discount')">
                    <?= icon('plus', 14) ?> افزودن کد تخفیف
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABS -->
    <div class="tab-container">
        <a href="?tab=gifts" class="tab-link <?= $currentTab === 'gifts' ? 'active' : '' ?>">
            <?= icon('gift', 14) ?> کدهای هدیه (کیف پول)
        </a>
        <a href="?tab=discounts" class="tab-link <?= $currentTab === 'discounts' ? 'active' : '' ?>">
            <?= icon('percent', 14) ?> کدهای تخفیف (سرویس)
        </a>
        <a href="?tab=logs" class="tab-link log-tab <?= $currentTab === 'logs' ? 'active' : '' ?>">
            <?= icon('activity', 14) ?> لاگ استفاده
        </a>
    </div>

    <?php if ($currentTab === 'gifts'): ?>
        <!-- GIFTS TABLE -->
        <div class="tbl-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>کد هدیه</th>
                        <th>مبلغ شارژ (تومان)</th>
                        <th style="width: 250px;">وضعیت ظرفیت</th>
                        <th style="width: 120px; text-align:left;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($gifts) > 0): ?>
                        <?php foreach ($gifts as $item): ?>
                            <tr>
                                <td data-label="کد هدیه"><span class="code-badge"><?= htmlspecialchars($item['code']) ?></span></td>
                                <td data-label="مبلغ شارژ"><strong style="color:var(--text);"><?= number_format((float)$item['price']) ?></strong> تومان</td>
                                <td data-label="وضعیت ظرفیت"><?= getCapacityBadge($item['limitused'], $item['limituse']) ?></td>
                                <td data-label="عملیات" style="text-align:left;">
                                    <div style="display:flex; gap:6px; justify-content:flex-end; width:100%; flex-wrap:nowrap;">
                                        <a href="?tab=logs&code=<?= urlencode($item['code']) ?>" class="btn btn-sm btn-ghost" title="مشاهده تاریخچه استفاده و مصرف این کد">
                                            <?= icon('list', 14) ?> تاریخچه مصرف (<?= $item['limitused'] ?>)
                                        </a>
                                        <a href="?action=delete&type=gift&id=<?= (int)$item['id'] ?>&_csrf=<?= csrf_token() ?>" class="btn btn-sm btn-no" data-confirm="حذف کد هدیه «<?= htmlspecialchars($item['code']) ?>»؟">
                                            <?= icon('trash', 14) ?> حذف
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="no-label"><div class="empty">کد هدیه‌ای یافت نشد.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($currentTab === 'discounts'): ?>
        <!-- DISCOUNTS TABLE -->
        <div class="tbl-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>کد تخفیف</th>
                        <th>مقدار تخفیف</th>
                        <th>اعتبار زمانی</th>
                        <th style="width: 200px;">وضعیت ظرفیت</th>
                        <th style="width: 120px; text-align:left;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($discounts) > 0): ?>
                        <?php foreach ($discounts as $item): ?>
                            <tr>
                                <td data-label="کد تخفیف">
                                    <span class="code-badge"><?= htmlspecialchars($item['codeDiscount']) ?></span>
                                    <div style="font-size: 0.8em; color: var(--text-muted); margin-top: 5px;">
                                        <?= $item['type'] === 'all' ? 'خرید/تمدید' : ($item['type'] === 'buy' ? 'فقط خرید' : 'فقط تمدید') ?> | 
                                        پنل: <?= $item['code_panel'] === '/all' ? 'همه' : htmlspecialchars($item['code_panel']) ?>
                                    </div>
                                </td>
                                <td data-label="مقدار تخفیف"><strong style="color:var(--text);"><?= htmlspecialchars($item['price']) ?></strong></td>
                                <td data-label="اعتبار زمانی"><?= getTimeBadge($item['time']) ?></td>
                                <td data-label="وضعیت ظرفیت"><?= getCapacityBadge($item['usedDiscount'], $item['limitDiscount']) ?></td>
                                <td data-label="عملیات" style="text-align:left;">
                                    <div style="display:flex; gap:6px; justify-content:flex-end; width:100%; flex-wrap:nowrap;">
                                        <a href="?tab=logs&code=<?= urlencode($item['codeDiscount']) ?>" class="btn btn-sm btn-ghost" title="مشاهده تاریخچه استفاده و مصرف این کد">
                                            <?= icon('list', 14) ?> تاریخچه مصرف (<?= $item['usedDiscount'] ?>)
                                        </a>
                                        <a href="?action=delete&type=discount&id=<?= (int)$item['id'] ?>&_csrf=<?= csrf_token() ?>" class="btn btn-sm btn-no" data-confirm="حذف کد تخفیف «<?= htmlspecialchars($item['codeDiscount']) ?>»؟">
                                            <?= icon('trash', 14) ?> حذف
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="no-label"><div class="empty">کد تخفیفی یافت نشد.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif ($currentTab === 'logs'): ?>
        <!-- LOGS TABLE -->
        <div class="tbl-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>کاربر</th>
                        <th>کد استفاده شده</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td data-label="#" class="cell-mono"><?= htmlspecialchars($log['id']) ?></td>
                                <td data-label="کاربر">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(13, 110, 253, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center;">
                                            <?= icon('user', 20) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:bold; color:var(--text); display:flex; align-items:center; gap:5px; font-size:1.05em;">
                                                <?= htmlspecialchars($log['namecustom'] ?: ($log['name'] ?? 'کاربر ناشناس')) ?>
                                            </div>
                                            <div style="font-size:0.85em; color:var(--text-muted); display:flex; align-items:center; gap:5px; margin-top:4px;">
                                                <span dir="ltr" style="background:var(--bg-lighter); padding:2px 6px; border-radius:4px;">@<?= htmlspecialchars($log['username'] ?? 'بدون_یوزرنیم') ?></span>
                                                <span style="opacity:0.5;">•</span>
                                                <span style="font-family:monospace; background:var(--bg-lighter); padding:2px 6px; border-radius:4px;">ID: <?= htmlspecialchars($log['id_user']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="کد استفاده شده">
                                    <a href="?tab=logs&code=<?= urlencode($log['code']) ?>" class="code-badge" style="background: var(--acs); border-color: var(--ac); color: var(--ac); text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;" title="برای فیلتر کردن لاگ‌های این کد کلیک کنید">
                                        <?= icon('filter', 12) ?> <?= htmlspecialchars($log['code']) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="no-label"><div class="empty">هیچ استفاده‌ای ثبت نشده است.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Multi-step Wizard for Add Discount / Gift -->
<div class="modal-veil" id="wizardModal">
    <div class="modal" style="max-width: 650px; background: var(--bg-card);">
        <div class="modal-head">
            <h3 id="wizardTitle"><?= icon('layers', 16) ?> ساخت کد جدید</h3>
            <button type="button" class="modal-x" onclick="closeWizard()"><?= icon('x', 14) ?></button>
        </div>
        
        <div class="modal-body">
            <!-- WIZARD PROGRESS -->
            <div class="wizard-steps" id="wizardSteps">
                <div class="wizard-step active" id="stepIndicator1">۱. اطلاعات پایه</div>
                <div class="wizard-step" id="stepIndicator2">۲. محدودیت‌ها</div>
                <div class="wizard-step" id="stepIndicator3">۳. تأیید نهایی</div>
            </div>

            <!-- FORM START -->
            <form id="wizardForm" method="POST" action="discounts.php">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" id="wizardAction" value="">
                
                <!-- STEP 1: Basic Info -->
                <div class="wizard-panel active" id="stepPanel1">
                    <div class="field" style="margin-bottom: 1.2rem;">
                        <label>کد دلخواه <span class="text-danger">*</span></label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" name="wizard_code" id="wizard_code" class="input" style="font-family:monospace; font-size:1.1em; letter-spacing:1px;" required placeholder="مثلاً: SUMMER20">
                            <button type="button" class="btn btn-secondary" onclick="generateRandomCode()"><?= icon('refresh-cw', 14) ?></button>
                        </div>
                    </div>
                    
                    <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.2rem;">
                        <div class="field">
                            <label id="lbl_wizard_price">مبلغ / درصد <span class="text-danger">*</span></label>
                            <input type="number" name="wizard_price" id="wizard_price" class="input" required placeholder="مثال: 50000">
                        </div>
                        <div class="field">
                            <label>ظرفیت کل استفاده <span class="text-danger">*</span></label>
                            <input type="number" name="wizard_limit" id="wizard_limit" class="input" required value="1" min="1">
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Restrictions (Only for Discount) -->
                <div class="wizard-panel" id="stepPanel2">
                    <div class="alert alert-info" style="margin-bottom:1rem; font-size:0.9em;">
                        <?= icon('info', 14) ?> در صورت عدم نیاز به محدودیت، مقادیر پیش‌فرض را رها کنید.
                    </div>
                    
                    <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.2rem;">
                        <div class="field">
                            <label>محدودیت استفاده هر کاربر</label>
                            <input type="number" name="useuser" id="useuser" class="input" value="1" min="1">
                        </div>
                        <div class="field">
                            <label>اعتبار زمانی (ساعت) - 0 برای نامحدود</label>
                            <input type="number" name="time_hours" id="time_hours" class="input" value="0" min="0">
                        </div>
                    </div>

                    <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.2rem;">
                        <div class="field">
                            <label>محدودیت محصول</label>
                            <select name="code_product" id="code_product" class="input">
                                <option value="all">همه محصولات</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= htmlspecialchars($p['code_product']) ?>"><?= htmlspecialchars($p['name_product']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>محدودیت پنل</label>
                            <select name="code_panel" id="code_panel" class="input">
                                <option value="/all">همه پنل‌ها</option>
                                <?php foreach ($panels as $pan): ?>
                                    <option value="<?= htmlspecialchars($pan['code_panel']) ?>"><?= htmlspecialchars($pan['name_panel']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.2rem;">
                        <div class="field">
                            <label>نوع سرویس</label>
                            <select name="type" id="type" class="input">
                                <option value="all">خرید و تمدید</option>
                                <option value="buy">فقط خرید جدید</option>
                                <option value="extend">فقط تمدید</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>فقط برای خرید اول؟</label>
                            <select name="usefirst" id="usefirst" class="input">
                                <option value="0">خیر</option>
                                <option value="1">بله</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label>دسترسی نماینده‌ها</label>
                        <select name="agent" id="agent" class="input">
                            <option value="allusers">همه (عادی و نماینده)</option>
                            <option value="f">فقط کاربران عادی</option>
                            <option value="n">نمایندگان سطح ۱</option>
                            <option value="n2">نمایندگان سطح ۲</option>
                        </select>
                    </div>
                </div>

                <!-- STEP 3: Summary -->
                <div class="wizard-panel" id="stepPanel3">
                    <div style="background:var(--bg-dark); border-radius:8px; padding:1.5rem; border:1px dashed var(--primary);">
                        <h4 style="color:var(--primary); margin-top:0; border-bottom:1px solid rgba(0,212,170,0.2); padding-bottom:10px; margin-bottom:15px;">
                            خلاصه اطلاعات کد
                        </h4>
                        
                        <div style="display:grid; grid-template-columns:120px 1fr; gap:10px; font-size:0.95em;">
                            <div style="color:var(--text-muted);">کد:</div>
                            <div id="sum_code" style="font-weight:bold; font-family:monospace; letter-spacing:1px; color:var(--text);"></div>
                            
                            <div style="color:var(--text-muted);">مقدار:</div>
                            <div id="sum_price" style="font-weight:bold; color:var(--text);"></div>
                            
                            <div style="color:var(--text-muted);">ظرفیت کل:</div>
                            <div id="sum_limit" style="font-weight:bold; color:var(--text);"></div>
                        </div>
                        
                        <div id="sum_discount_details" style="display:none; margin-top:15px; padding-top:15px; border-top:1px solid var(--border);">
                            <div style="display:grid; grid-template-columns:120px 1fr; gap:10px; font-size:0.9em;">
                                <div style="color:var(--text-muted);">محصول / پنل:</div>
                                <div id="sum_prod_panel" style="color:var(--text);"></div>
                                
                                <div style="color:var(--text-muted);">نوع مجاز:</div>
                                <div id="sum_type" style="color:var(--text);"></div>
                                
                                <div style="color:var(--text-muted);">محدودیت‌ها:</div>
                                <div id="sum_limits" style="color:var(--text);"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning" style="margin-top:1.5rem; display:flex; align-items:center; gap:10px;">
                        <?= icon('alert-triangle', 18) ?> از صحت اطلاعات وارد شده اطمینان حاصل کنید.
                    </div>
                </div>

            </form>
        </div>
        
        <div class="modal-foot" style="display:flex; justify-content:space-between;">
            <button type="button" class="btn btn-ghost" id="btnPrev" onclick="prevStep()" style="display:none;"><?= icon('arrow-right', 14) ?> مرحله قبل</button>
            <div style="flex:1"></div>
            <button type="button" class="btn btn-primary" id="btnNext" onclick="nextStep()">مرحله بعد <?= icon('arrow-left', 14) ?></button>
            <button type="button" class="btn btn-primary" id="btnSubmit" onclick="submitWizard()" style="display:none;">ثبت نهایی <?= icon('check', 14) ?></button>
        </div>
    </div>
</div>

<script>
let currentMode = 'gift'; // gift | discount
let currentStep = 1;
const totalSteps = 3;

function openWizard(mode) {
    currentMode = mode;
    currentStep = 1;
    
    // Reset form
    document.getElementById('wizardForm').reset();
    
    if (mode === 'gift') {
        document.getElementById('wizardTitle').innerHTML = `<?= icon("gift", 16) ?> ساخت کد هدیه (کیف پول)`;
        document.getElementById('lbl_wizard_price').innerHTML = `مبلغ شارژ کیف پول (تومان) <span class="text-danger">*</span>`;
        document.getElementById('wizardAction').value = 'add_gift';
        
        // Disable and hide code names explicitly
        document.getElementById('wizard_code').name = 'code';
        document.getElementById('wizard_price').name = 'price';
        document.getElementById('wizard_limit').name = 'limituse';
        
        // Hide Step 2 in indicator
        document.getElementById('stepIndicator2').style.display = 'none';
    } else {
        document.getElementById('wizardTitle').innerHTML = `<?= icon("percent", 16) ?> ساخت کد تخفیف (سرویس)`;
        document.getElementById('lbl_wizard_price').innerHTML = `مقدار تخفیف (مبلغ یا درصد) <span class="text-danger">*</span>`;
        document.getElementById('wizardAction').value = 'add_discount';
        
        document.getElementById('wizard_code').name = 'codeDiscount';
        document.getElementById('wizard_price').name = 'price';
        document.getElementById('wizard_limit').name = 'limitDiscount';
        
        // Show Step 2
        document.getElementById('stepIndicator2').style.display = 'block';
    }
    
    updateWizardUI();
    document.getElementById('wizardModal').classList.add('open');
}

function closeWizard() {
    document.getElementById('wizardModal').classList.remove('open');
}

function generateRandomCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < 8; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('wizard_code').value = result;
}

function updateWizardUI() {
    // Hide all panels
    for (let i = 1; i <= totalSteps; i++) {
        let p = document.getElementById('stepPanel' + i);
        if(p) p.classList.remove('active');
        
        let ind = document.getElementById('stepIndicator' + i);
        if(ind) {
            ind.classList.remove('active', 'completed');
            if (i < currentStep) ind.classList.add('completed');
            else if (i === currentStep) ind.classList.add('active');
        }
    }
    
    document.getElementById('stepPanel' + currentStep).classList.add('active');
    
    // Buttons logic
    document.getElementById('btnPrev').style.display = currentStep > 1 ? 'inline-flex' : 'none';
    
    if (currentStep === totalSteps || (currentMode === 'gift' && currentStep === 2)) {
        document.getElementById('btnNext').style.display = 'none';
        document.getElementById('btnSubmit').style.display = 'inline-flex';
        populateSummary();
    } else {
        document.getElementById('btnNext').style.display = 'inline-flex';
        document.getElementById('btnSubmit').style.display = 'none';
    }
    
    // Special skip for gift mode
    if (currentMode === 'gift' && currentStep === 2) {
        document.getElementById('stepPanel2').classList.remove('active');
        document.getElementById('stepPanel3').classList.add('active');
        
        // Fix indicators for skipped step
        document.getElementById('stepIndicator1').classList.remove('active');
        document.getElementById('stepIndicator1').classList.add('completed');
        document.getElementById('stepIndicator3').classList.add('active');
    }
}

function nextStep() {
    // Basic validation
    if (currentStep === 1) {
        const c = document.getElementById('wizard_code').value.trim();
        const p = document.getElementById('wizard_price').value.trim();
        if (!c || !p) {
            alert('لطفاً کد و مبلغ را وارد کنید.');
            return;
        }
    }
    
    if (currentMode === 'gift' && currentStep === 1) {
        currentStep = 3; // Skip to summary
    } else {
        currentStep++;
    }
    updateWizardUI();
}

function prevStep() {
    if (currentMode === 'gift' && currentStep === 3) {
        currentStep = 1;
    } else {
        currentStep--;
    }
    updateWizardUI();
}

function populateSummary() {
    document.getElementById('sum_code').innerText = document.getElementById('wizard_code').value || '-';
    document.getElementById('sum_price').innerText = document.getElementById('wizard_price').value || '0';
    document.getElementById('sum_limit').innerText = document.getElementById('wizard_limit').value || '1';
    
    const details = document.getElementById('sum_discount_details');
    if (currentMode === 'discount') {
        details.style.display = 'block';
        
        let prod = document.getElementById('code_product').options[document.getElementById('code_product').selectedIndex].text;
        let pan = document.getElementById('code_panel').options[document.getElementById('code_panel').selectedIndex].text;
        document.getElementById('sum_prod_panel').innerText = prod + ' / ' + pan;
        
        let typ = document.getElementById('type').options[document.getElementById('type').selectedIndex].text;
        document.getElementById('sum_type').innerText = typ;
        
        let u_user = document.getElementById('useuser').value;
        let t_hours = document.getElementById('time_hours').value;
        let ag = document.getElementById('agent').options[document.getElementById('agent').selectedIndex].text;
        
        document.getElementById('sum_limits').innerText = 
            `سهمیه هر نفر: ${u_user} | انقضا: ${t_hours == '0' ? 'نامحدود' : t_hours + ' ساعت'} | ${ag}`;
            
    } else {
        details.style.display = 'none';
    }
}

function submitWizard() {
    document.getElementById('wizardForm').submit();
}
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
