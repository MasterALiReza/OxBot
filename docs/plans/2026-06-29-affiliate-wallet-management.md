# Affiliate Wallet Management Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable full management (add, deduct, zero, transfer, view balance, action logs, and withdrawal logs) of a user's affiliate (referral) wallet directly from the admin panel user detail screen.

**Architecture:**
- Create `affiliate_log` table via database schema updates in `table.php`.
- Handle new POST actions in `panel/user.php` for `add_affiliate_balance`, `deduct_affiliate_balance`, `transfer_affiliate_to_main`, `approve_withdrawal`, and `reject_withdrawal`.
- Handle GET action in `panel/user_action.php` for `zero_affiliate_balance`.
- Add UI elements in `panel/user.php` to display affiliate balance, operation buttons (modals), and a new Tab for logs (hybrid: manual changes + withdrawal requests).

**Tech Stack:** PHP, MySQL (PDO), HTML, Vanilla CSS, JS

---

### Task 1: Create Database Table for Affiliate Logs

**Files:**
- Modify: [table.php](file:///c:/Users/iWexort/Documents/Github/mirzabot-main/table.php)

**Step 1: Write database schema logic**
Add creation code for `affiliate_log` table inside `table.php`:
```php
try {
    $tableName = 'affiliate_log';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(500) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            amount INT NOT NULL,
            description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    }
} catch (PDOException $e) {
    file_put_contents('error_log_aff_log', $e->getMessage());
}
```

**Step 2: Run schema upgrade script**
Execute `table.php` to run the migration:
Run: `php table.php`
Expected: Execution finishes without errors, table `affiliate_log` is created.

**Step 3: Commit**
```bash
git add table.php
git commit -m "migration: create affiliate_log table"
```

---

### Task 2: Implement Backend Operation Handlers in user.php and user_action.php

**Files:**
- Modify: [panel/user.php](file:///c:/Users/iWexort/Documents/Github/mirzabot-main/panel/user.php)
- Modify: [panel/user_action.php](file:///c:/Users/iWexort/Documents/Github/mirzabot-main/panel/user_action.php)

**Step 1: Implement POST handlers in user.php**
Insert POST handlers at the top of `panel/user.php` under the existing action blocks:
```php
    } elseif ($action === 'add_affiliate_balance') {
        $amount = (int) ($_POST['amount'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        if ($amount > 0) {
            db_query($pdo, "UPDATE user SET affiliate_balance = COALESCE(affiliate_balance, 0) + ? WHERE id = ?", [$amount, $id]);
            db_query($pdo, "INSERT INTO affiliate_log (user_id, action_type, amount, description) VALUES (?, 'deposit', ?, ?)", [$id, $amount, $desc]);
            
            $msg = "🎉 مبلغ " . number_format($amount) . " تومان به کیف پول بازاریابی شما توسط مدیریت اضافه شد.";
            if (!empty($desc)) {
                $msg .= "\n📝 علت: $desc";
            }
            telegram('sendMessage', ['chat_id' => $id, 'text' => $msg, 'parse_mode' => 'HTML']);
            flash('success', "مبلغ " . number_format($amount) . " تومان به کیف پول بازاریابی کاربر اضافه شد.");
        } else {
            flash('error', "مبلغ نامعتبر است.");
        }
    } elseif ($action === 'deduct_affiliate_balance') {
        $amount = (int) ($_POST['amount'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        if ($amount > 0) {
            // Atomic decrement
            $stmt = $pdo->prepare("UPDATE user SET affiliate_balance = affiliate_balance - ? WHERE id = ? AND affiliate_balance >= ?");
            $stmt->execute([$amount, $id, $amount]);
            if ($stmt->rowCount() > 0) {
                db_query($pdo, "INSERT INTO affiliate_log (user_id, action_type, amount, description) VALUES (?, 'deduct', ?, ?)", [$id, $amount, $desc]);
                
                $msg = "❌ مبلغ " . number_format($amount) . " تومان از کیف پول بازاریابی شما توسط مدیریت کسر شد.";
                if (!empty($desc)) {
                    $msg .= "\n📝 علت: $desc";
                }
                telegram('sendMessage', ['chat_id' => $id, 'text' => $msg, 'parse_mode' => 'HTML']);
                flash('success', "مبلغ " . number_format($amount) . " تومان از کیف پول بازاریابی کاربر کسر شد.");
            } else {
                flash('error', "موجودی بازاریابی کاربر کافی نیست.");
            }
        } else {
            flash('error', "مبلغ نامعتبر است.");
        }
    } elseif ($action === 'transfer_affiliate_to_main') {
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount > 0) {
            // Atomic transfer
            $stmt = $pdo->prepare("UPDATE user SET affiliate_balance = affiliate_balance - :amount, Balance = Balance + :amount WHERE id = :id AND affiliate_balance >= :amount");
            $stmt->execute([':amount' => $amount, ':id' => $id]);
            if ($stmt->rowCount() > 0) {
                db_query($pdo, "INSERT INTO affiliate_log (user_id, action_type, amount, description) VALUES (?, 'transfer_to_main', ?, 'انتقال دستی توسط مدیریت')", [$id, $amount]);
                
                $msg = "💼 مبلغ " . number_format($amount) . " تومان از کیف پول بازاریابی به کیف پول اصلی شما در ربات منتقل شد.";
                telegram('sendMessage', ['chat_id' => $id, 'text' => $msg, 'parse_mode' => 'HTML']);
                flash('success', "مبلغ " . number_format($amount) . " تومان با موفقیت به کیف پول اصلی کاربر منتقل شد.");
            } else {
                flash('error', "موجودی بازاریابی کافی برای انتقال وجود ندارد.");
            }
        } else {
            flash('error', "مبلغ نامعتبر است.");
        }
    } elseif ($action === 'approve_withdrawal' || $action === 'reject_withdrawal') {
        $w_id = (int)$_POST['withdrawal_id'];
        $w_req = db_fetch($pdo, "SELECT * FROM withdrawal_requests WHERE id = ?", [$w_id]);
        if ($w_req && $w_req['status'] === 'pending') {
            if ($action === 'approve_withdrawal') {
                db_query($pdo, "UPDATE withdrawal_requests SET status = 'approved' WHERE id = ?", [$w_id]);
                $msg = "✅ درخواست تسویه حساب شما به مبلغ " . number_format($w_req['amount']) . " تومان تایید و پرداخت شد.";
                telegram('sendMessage', ['chat_id' => $w_req['user_id'], 'text' => $msg, 'parse_mode' => 'HTML']);
                flash('success', 'درخواست تسویه تایید و پرداخت شد.');
            } else {
                db_query($pdo, "UPDATE user SET affiliate_balance = COALESCE(affiliate_balance, 0) + ? WHERE id = ?", [$w_req['amount'], $w_req['user_id']]);
                db_query($pdo, "UPDATE withdrawal_requests SET status = 'rejected' WHERE id = ?", [$w_id]);
                $msg = "❌ درخواست تسویه حساب شما به مبلغ " . number_format($w_req['amount']) . " تومان رد شد و مبلغ به کیف پول بازاریابی شما بازگشت داده شد.";
                telegram('sendMessage', ['chat_id' => $w_req['user_id'], 'text' => $msg, 'parse_mode' => 'HTML']);
                flash('success', 'درخواست تسویه رد شد و وجه بازگشت داده شد.');
            }
        } else {
            flash('error', 'درخواست یافت نشد یا دیگر در انتظار بررسی نیست.');
        }
    }
```

**Step 2: Implement GET handler in user_action.php**
Add the `zero_affiliate_balance` action in `panel/user_action.php`:
```php
    case 'zero_affiliate_balance':
        $current_bal = db_fetch($pdo, "SELECT affiliate_balance FROM user WHERE id = ?", [$id])['affiliate_balance'] ?? 0;
        if ($current_bal > 0) {
            db_query($pdo, "UPDATE user SET affiliate_balance = 0 WHERE id = ?", [$id]);
            db_query($pdo, "INSERT INTO affiliate_log (user_id, action_type, amount, description) VALUES (?, 'zero', ?, 'صفر کردن موجودی توسط مدیریت')", [$id, $current_bal]);
            
            telegram('sendMessage', [
                'chat_id' => $id,
                'text' => "❌ <b>کسر موجودی بازاریابی</b>\n\nموجودی کیف پول بازاریابی (همکاری در فروش) شما توسط مدیریت صفر شد.",
                'parse_mode' => 'HTML'
            ]);
            flash('success', 'موجودی همکاری در فروش کاربر صفر شد.');
        } else {
            flash('warning', 'موجودی همکاری در فروش کاربر در حال حاضر صفر است.');
        }
        break;
```

**Step 3: Commit**
```bash
git add panel/user.php panel/user_action.php
git commit -m "feat(panel): add backend handlers for affiliate balance actions"
```

---

### Task 3: Build UI Elements, Forms, Modals & History Tab in user.php

**Files:**
- Modify: [panel/user.php](file:///c:/Users/iWexort/Documents/Github/mirzabot-main/panel/user.php)

**Step 1: Add Affiliate Wallet info to sidebar**
Render the affiliate balance in the user-kv-grid sidebar around line 376:
```php
                <div style="display:flex; align-items:center; justify-content: space-between; padding:10px 12px; background:var(--sf2); border-radius:8px; border:1px solid var(--bd);">
                    <span style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; gap:6px;"><?= icon('award', 14) ?> کیف پول بازاریابی:</span>
                    <span class="cn" style="font-weight:700; font-size:1rem; color:var(--ac);"><?= number_format($user['affiliate_balance'] ?? 0) ?> <span class="cf" style="font-size:0.75rem">ت</span></span>
                </div>
```

**Step 2: Add Affiliate operations section and buttons**
Find the `امور مالی` section in `panel/user.php` and render the new affiliate operation buttons:
```html
                <!-- Affiliate Financial Affairs -->
                <div style="margin-top: 20px;">
                    <div style="font-size:0.85rem;color:var(--mute);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <?= icon('award', 14) ?> <span>امور مالی همکاری در فروش (بازاریابی)</span>
                        <div style="flex:1;height:1px;background:var(--bd);margin-right:10px;"></div>
                    </div>
                    <div class="user-actions-grid" style="padding:0;">
                        <button class="btn btn-ok" onclick="openModal('addAffBalanceModal')">
                            <?= icon('plus', 14) ?> افزایش موجودی بازاریابی
                        </button>
                        <button class="btn btn-warn" onclick="openModal('deductAffBalanceModal')">
                            <?= icon('minus', 14) ?> کسر موجودی بازاریابی
                        </button>
                        <button class="btn btn-ghost" onclick="openModal('transferAffBalanceModal')">
                            <?= icon('refresh-cw', 14) ?> انتقال به کیف پول اصلی
                        </button>
                        <a href="user_action.php?action=zero_affiliate_balance&id=<?= $id ?>&_csrf=<?= csrf_token() ?>&back=user.php"
                            class="btn btn-no" data-confirm="آیا از صفر کردن کیف پول همکاری در فروش کاربر اطمینان دارید؟" hx-boost="false">
                            <?= icon('slash', 14) ?> صفر کردن بازاریابی
                        </a>
                    </div>
                </div>
```

**Step 3: Define Modals for operations**
Append these modals to the end of the file:
```html
<!-- Modal: Add Affiliate Balance -->
<div id="addAffBalanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>افزایش موجودی بازاریابی</h3>
            <button class="close-btn" onclick="closeModal('addAffBalanceModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_affiliate_balance">
            <div class="form-group">
                <label>مبلغ (تومان):</label>
                <input type="number" name="amount" class="form-control" required min="1" placeholder="مثلا 15000">
            </div>
            <div class="form-group">
                <label>توضیحات (علت واریز):</label>
                <input type="text" name="description" class="form-control" placeholder="اختیاری">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:10px; width: 100%;">ثبت و افزایش</button>
        </form>
    </div>
</div>

<!-- Modal: Deduct Affiliate Balance -->
<div id="deductAffBalanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>کسر موجودی بازاریابی</h3>
            <button class="close-btn" onclick="closeModal('deductAffBalanceModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="deduct_affiliate_balance">
            <div class="form-group">
                <label>مبلغ (تومان):</label>
                <input type="number" name="amount" class="form-control" required min="1" placeholder="مثلا 10000">
            </div>
            <div class="form-group">
                <label>توضیحات (علت کسر):</label>
                <input type="text" name="description" class="form-control" placeholder="اختیاری">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:10px; width: 100%;">ثبت و کسر</button>
        </form>
    </div>
</div>

<!-- Modal: Transfer Affiliate Balance to Main -->
<div id="transferAffBalanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>انتقال به کیف پول اصلی</h3>
            <button class="close-btn" onclick="closeModal('transferAffBalanceModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="transfer_affiliate_to_main">
            <div class="form-group">
                <label>مبلغ جهت انتقال به کیف پول اصلی (تومان):</label>
                <input type="number" name="amount" class="form-control" required min="1" placeholder="حداکثر <?= $user['affiliate_balance'] ?? 0 ?>">
            </div>
            <p style="font-size: 0.8rem; color: var(--mute); margin-top: 5px;">مبلغ از کیف پول بازاریابی کاربر کسر شده و به موجودی اصلی او (جهت خرید سرویس) اضافه می‌شود.</p>
            <button type="submit" class="btn btn-primary" style="margin-top:10px; width: 100%;">انتقال موجودی</button>
        </form>
    </div>
</div>
```

**Step 4: Fetch history logs and add tab pane**
Add DB fetch queries around line 138:
```php
$withdrawals = [];
$aff_logs = [];
try {
    $withdrawals = db_fetchAll($pdo, "SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC", [$id]);
    $aff_logs = db_fetchAll($pdo, "SELECT * FROM affiliate_log WHERE user_id = ? ORDER BY created_at DESC", [$id]);
} catch (Exception $e) {}
```
Append the new Tab headers:
```html
                    <button class="btn btn-sm" id="tabAffHistory" onclick="switchTab('affhistory')"
                        style="background:transparent;color:var(--mute);border-radius:5px;font-size:.75rem;border:none">
                        همکاری در فروش (لاگ و تسویه)
                        <span style="background:var(--acs);color:var(--ac);padding:1px 6px;border-radius:99px;font-size:.65rem">
                            <?= count($withdrawals) + count($aff_logs) ?>
                        </span>
                    </button>
```
Append Tab pane content rendering logic:
```html
            <!-- Pane: Affiliate History & Withdrawals -->
            <div id="paneAffHistory" style="display:none;">
                <h4 style="margin: 15px 0 10px 0; color: var(--text);">📥 درخواست‌های تسویه نقدی به کارت</h4>
                <div class="tbl-wrap">
                    <table class="tbl-lg">
                        <thead>
                            <tr>
                                <th style="text-align:right;">مبلغ (تومان)</th>
                                <th style="text-align:right;">شماره کارت</th>
                                <th style="text-align:right;">تاریخ ثبت</th>
                                <th style="text-align:center;">وضعیت</th>
                                <th style="text-align:center;">عملیات تسویه</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($withdrawals)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty" style="padding:20px">
                                            <p>هیچ درخواست تسویه‌ای یافت نشد.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($withdrawals as $w): ?>
                                    <tr>
                                        <td data-label="مبلغ" style="font-weight:bold; color: var(--emerald);"><?= number_format($w['amount']) ?> تومان</td>
                                        <td data-label="شماره کارت" style="font-family:monospace;"><?= htmlspecialchars($w['card_number']) ?></td>
                                        <td data-label="تاریخ" style="color:var(--mute);"><?= htmlspecialchars($w['created_at']) ?></td>
                                        <td data-label="وضعیت" style="text-align:center;">
                                            <?php if ($w['status'] === 'pending'): ?>
                                                <span class="status-pill warn">در انتظار</span>
                                            <?php elseif ($w['status'] === 'approved'): ?>
                                                <span class="status-pill ok">پرداخت شده</span>
                                            <?php else: ?>
                                                <span class="status-pill danger">رد شده</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="عملیات" style="text-align:center;">
                                            <?php if ($w['status'] === 'pending'): ?>
                                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('آیا از تایید این درخواست و واریز مبلغ اطمینان دارید؟');">
                                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="action" value="approve_withdrawal">
                                                    <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                                                    <button type="submit" class="btn btn-sm" style="background: rgba(16,185,129,0.1); color: var(--emerald); padding: 4px 8px; font-size: 0.75rem;">تایید و پرداخت</button>
                                                </form>
                                                <form method="POST" style="display:inline-block; margin-right: 5px;" onsubmit="return confirm('آیا از رد این درخواست اطمینان دارید؟ مبلغ به حساب بازاریابی کاربر برگشت داده می‌شود.');">
                                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="action" value="reject_withdrawal">
                                                    <input type="hidden" name="withdrawal_id" value="<?= $w['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-ghost" style="color: var(--rose); padding: 4px 8px; font-size: 0.75rem;">رد درخواست</button>
                                                </form>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <h4 style="margin: 25px 0 10px 0; color: var(--text);">📝 لاگ تراکنش‌ها و تغییرات دستی ادمین</h4>
                <div class="tbl-wrap">
                    <table class="tbl-lg">
                        <thead>
                            <tr>
                                <th style="text-align:right;">نوع عملیات</th>
                                <th style="text-align:right;">مبلغ (تومان)</th>
                                <th style="text-align:right;">توضیحات / علت</th>
                                <th style="text-align:right;">تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($aff_logs)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty" style="padding:20px">
                                            <p>هیچ لاگی یافت نشد.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($aff_logs as $l): 
                                    $actionMap = [
                                        'deposit' => ['افزایش موجودی دستی', 'var(--emerald)'],
                                        'deduct' => ['کاهش موجودی دستی', 'var(--rose)'],
                                        'zero' => ['صفر کردن موجودی', 'var(--rose)'],
                                        'transfer_to_main' => ['انتقال به کیف پول اصلی', 'var(--ac)']
                                    ];
                                    $typeInfo = $actionMap[$l['action_type']] ?? [$l['action_type'], 'var(--text)'];
                                    ?>
                                    <tr>
                                        <td data-label="نوع" style="font-weight:600; color: <?= $typeInfo[1] ?>;"><?= $typeInfo[0] ?></td>
                                        <td data-label="مبلغ" class="cn"><?= number_format($l['amount']) ?> ت</td>
                                        <td data-label="توضیحات"><?= htmlspecialchars($l['description'] ?? '—') ?></td>
                                        <td data-label="تاریخ" style="color:var(--mute);"><?= htmlspecialchars($l['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
```
And make sure `switchTab` js function handles `affhistory` (hide paneOrders, panePay, paneRefs and show paneAffHistory, and change tab button active classes).

**Step 5: Run UI correctness check**
Verify the code syntax is correct.
Run: `php -l panel/user.php`
Expected: No syntax errors detected in panel/user.php

**Step 6: Commit**
```bash
git add panel/user.php
git commit -m "feat(panel): implement user affiliate management UI, modals and history log tab"
```

---

### Verification Plan

#### Automated Tests
- Run PHP lint check on modified files:
  `php -l panel/user.php`
  `php -l panel/user_action.php`
  `php -l table.php`

#### Manual Verification
1. Access the web panel.
2. Go to **مدیریت کاربران** and click on a user to open their detail page (`user.php`).
3. Verify that the **کیف پول بازاریابی** row is visible in the sidebar.
4. Verify the new tab **همکاری در فروش (لاگ و تسویه)** appears.
5. Trigger **افزایش موجودی بازاریابی** with a description, submit, verify balance changes, verify a log is added under the tab, and confirm user receives a Telegram message.
6. Trigger **انتقال به کیف پول اصلی** with a partial amount, verify it is deducted from affiliate balance and added to the main wallet balance, check logs.
7. Trigger **صفر کردن بازاریابی** and verify balance becomes 0.
8. Request a mock withdrawal request in the bot for this user, then refresh the admin user page. Verify the request shows up under the tab, and try to click **تایید و پرداخت** or **رد درخواست** to verify full integration.
