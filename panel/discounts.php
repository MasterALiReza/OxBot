<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$currentTab = $_GET['tab'] ?? 'gifts';

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
                // Check if gift code exists
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
        $price = trim($_POST['price'] ?? ''); // amount or percent
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
                // Check if discount exists
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
try {
    $gifts = db_fetchAll($pdo, "SELECT * FROM Discount ORDER BY id DESC");
    $discounts = db_fetchAll($pdo, "SELECT * FROM DiscountSell ORDER BY id DESC");
} catch (Exception $e) {}

$products = [];
$panels = [];
try {
    $products = db_fetchAll($pdo, "SELECT * FROM product ORDER BY id DESC");
    $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel ORDER BY id DESC");
} catch (Exception $e) {}

$pageTitle = 'مدیریت تخفیف‌ها';
$pageLede = 'مدیریت کدهای هدیه (افزایش موجودی) و کدهای تخفیف (خرید سرویس)';
$activeNav = 'discounts';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title">لیست کدها</div>
        </div>
        <div class="toolbar-end">
            <?php if ($currentTab === 'gifts'): ?>
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('giftModal').classList.add('open')">
                    <?= icon('plus', 14) ?> افزودن کد هدیه
                </button>
            <?php else: ?>
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('discountModal').classList.add('open')">
                    <?= icon('plus', 14) ?> افزودن کد تخفیف
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABS -->
    <div style="display: flex; gap: 1rem; border-bottom: 1px solid var(--border); margin-bottom: 1.5rem;">
        <a href="?tab=gifts" style="padding: 10px 15px; border-bottom: 2px solid <?= $currentTab === 'gifts' ? 'var(--primary)' : 'transparent' ?>; color: <?= $currentTab === 'gifts' ? 'var(--primary)' : 'var(--text-muted)' ?>; font-weight: 600;">
            کدهای هدیه (کیف پول)
        </a>
        <a href="?tab=discounts" style="padding: 10px 15px; border-bottom: 2px solid <?= $currentTab === 'discounts' ? 'var(--primary)' : 'transparent' ?>; color: <?= $currentTab === 'discounts' ? 'var(--primary)' : 'var(--text-muted)' ?>; font-weight: 600;">
            کدهای تخفیف (خرید/تمدید)
        </a>
    </div>

    <?php if ($currentTab === 'gifts'): ?>
        <!-- GIFTS TABLE -->
        <div class="tbl-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">آیدی</th>
                        <th>کد هدیه</th>
                        <th>مبلغ (تومان)</th>
                        <th>استفاده شده / ظرفیت</th>
                        <th style="width: 120px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($gifts) > 0): ?>
                        <?php foreach ($gifts as $item): ?>
                            <tr>
                                <td class="cell-mono"><?= htmlspecialchars((string)$item['id']) ?></td>
                                <td style="font-weight: bold; color: var(--text);"><?= htmlspecialchars($item['code']) ?></td>
                                <td><?= number_format((float)$item['price']) ?></td>
                                <td><?= htmlspecialchars($item['limitused']) ?> / <?= htmlspecialchars($item['limituse']) ?></td>
                                <td>
                                    <a href="?action=delete&type=gift&id=<?= (int)$item['id'] ?>&_csrf=<?= csrf_token() ?>" class="btn btn-sm btn-no" data-confirm="آیا از حذف کد هدیه «<?= htmlspecialchars($item['code']) ?>» اطمینان دارید؟">
                                        <?= icon('trash', 14) ?> حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="no-label"><div class="empty">هیچ کد هدیه‌ای یافت نشد.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <!-- DISCOUNTS TABLE -->
        <div class="tbl-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">آیدی</th>
                        <th>کد تخفیف</th>
                        <th>مقدار تخفیف</th>
                        <th>جزئیات اعمال</th>
                        <th>استفاده شده / ظرفیت</th>
                        <th style="width: 120px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($discounts) > 0): ?>
                        <?php foreach ($discounts as $item): ?>
                            <tr>
                                <td class="cell-mono"><?= htmlspecialchars((string)$item['id']) ?></td>
                                <td style="font-weight: bold; color: var(--text);"><?= htmlspecialchars($item['codeDiscount']) ?></td>
                                <td><?= htmlspecialchars($item['price']) ?></td>
                                <td style="font-size: 0.85em; color: var(--text-muted);">
                                    نوع: <?= htmlspecialchars($item['type']) ?><br>
                                    نماینده: <?= htmlspecialchars($item['agent']) ?><br>
                                    محصول: <?= htmlspecialchars($item['code_product']) ?><br>
                                    پنل: <?= htmlspecialchars($item['code_panel']) ?>
                                </td>
                                <td><?= htmlspecialchars($item['usedDiscount']) ?> / <?= htmlspecialchars($item['limitDiscount']) ?></td>
                                <td>
                                    <a href="?action=delete&type=discount&id=<?= (int)$item['id'] ?>&_csrf=<?= csrf_token() ?>" class="btn btn-sm btn-no" data-confirm="آیا از حذف کد تخفیف «<?= htmlspecialchars($item['codeDiscount']) ?>» اطمینان دارید؟">
                                        <?= icon('trash', 14) ?> حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="no-label"><div class="empty">هیچ کد تخفیفی یافت نشد.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Add Gift -->
<div class="modal-veil" id="giftModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-head">
            <h3><?= icon('plus', 16) ?> افزودن کد هدیه (کیف پول)</h3>
            <button type="button" class="modal-x" onclick="document.getElementById('giftModal').classList.remove('open')"><?= icon('x', 14) ?></button>
        </div>
        <form method="POST" action="discounts.php">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_gift">
            <div class="modal-body">
                <div class="field" style="margin-bottom: 1.2rem;">
                    <label>کد هدیه <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="input" required placeholder="مثلاً: FREE50">
                </div>
                <div class="field" style="margin-bottom: 1.2rem;">
                    <label>مبلغ هدیه (تومان) <span class="text-danger">*</span></label>
                    <input type="number" name="price" class="input" required placeholder="مثلاً: 50000">
                </div>
                <div class="field">
                    <label>ظرفیت استفاده کلی <span class="text-danger">*</span></label>
                    <input type="number" name="limituse" class="input" required value="1" min="1">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('giftModal').classList.remove('open')">انصراف</button>
                <button type="submit" class="btn btn-primary">ثبت کد هدیه</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Discount -->
<div class="modal-veil" id="discountModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-head">
            <h3><?= icon('plus', 16) ?> افزودن کد تخفیف (سرویس)</h3>
            <button type="button" class="modal-x" onclick="document.getElementById('discountModal').classList.remove('open')"><?= icon('x', 14) ?></button>
        </div>
        <form method="POST" action="discounts.php">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_discount">
            <div class="modal-body" style="max-height: 65vh; overflow-y: auto;">
                
                <div class="field" style="margin-bottom: 1.2rem;">
                    <label>کد تخفیف <span class="text-danger">*</span></label>
                    <input type="text" name="codeDiscount" class="input" required placeholder="مثلاً: YALDA">
                </div>
                
                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.2rem;">
                    <div class="field">
                        <label>مقدار تخفیف (درصد یا مبلغ) <span class="text-danger">*</span></label>
                        <input type="number" name="price" class="input" required>
                    </div>
                    <div class="field">
                        <label>ظرفیت استفاده کلی <span class="text-danger">*</span></label>
                        <input type="number" name="limitDiscount" class="input" required value="1" min="1">
                    </div>
                </div>

                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.2rem;">
                    <div class="field">
                        <label>محدودیت استفاده هر کاربر</label>
                        <input type="number" name="useuser" class="input" value="1" min="1">
                    </div>
                    <div class="field">
                        <label>اعتبار زمانی (ساعت) - 0 نامحدود</label>
                        <input type="number" name="time_hours" class="input" value="0" min="0">
                    </div>
                </div>

                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.2rem;">
                    <div class="field">
                        <label>محدودیت محصول</label>
                        <select name="code_product" class="input">
                            <option value="all">همه محصولات</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= htmlspecialchars($p['code_product']) ?>"><?= htmlspecialchars($p['name_product']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>محدودیت پنل</label>
                        <select name="code_panel" class="input">
                            <option value="/all">همه پنل‌ها</option>
                            <?php foreach ($panels as $pan): ?>
                                <option value="<?= htmlspecialchars($pan['code_panel']) ?>"><?= htmlspecialchars($pan['name_panel']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 1.2rem;">
                    <div class="field">
                        <label>نوع سرویس مجاز</label>
                        <select name="type" class="input">
                            <option value="all">خرید و تمدید</option>
                            <option value="buy">فقط خرید جدید</option>
                            <option value="extend">فقط تمدید</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>فقط برای خرید اول کاربر؟</label>
                        <select name="usefirst" class="input">
                            <option value="0">خیر</option>
                            <option value="1">بله</option>
                        </select>
                    </div>
                </div>
                
                <div class="field">
                    <label>نماینده مجاز</label>
                    <select name="agent" class="input">
                        <option value="allusers">همه کاربران (عادی و نماینده)</option>
                        <option value="f">فقط کاربران عادی</option>
                        <option value="n">نمایندگان سطح ۱</option>
                        <option value="n2">نمایندگان سطح ۲</option>
                    </select>
                </div>

            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('discountModal').classList.remove('open')">انصراف</button>
                <button type="submit" class="btn btn-primary">ثبت کد تخفیف</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
