<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$action = $_POST['action'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    
    if ($action === 'add') {
        $remark = trim($_POST['remark'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $linkjoin = trim($_POST['linkjoin'] ?? '');
        
        if (empty($remark) || empty($link) || empty($linkjoin)) {
            $error = 'تمامی فیلدها الزامی هستند.';
        } elseif (!filter_var($linkjoin, FILTER_VALIDATE_URL)) {
            $error = 'لینک جوین وارد شده نامعتبر است.';
        } else {
            try {
                db_query($pdo, "INSERT INTO channels (link, remark, linkjoin) VALUES (?, ?, ?)", [$link, $remark, $linkjoin]);
                $success = 'کانال با موفقیت اضافه شد.';
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
                    try {
                        ensureTableUtf8mb4('channels');
                        db_query($pdo, "INSERT INTO channels (link, remark, linkjoin) VALUES (?, ?, ?)", [$link, $remark, $linkjoin]);
                        $success = 'کانال با موفقیت اضافه شد.';
                    } catch (Exception $e2) {
                        $error = 'خطا در افزودن کانال: ' . $e2->getMessage();
                    }
                } else {
                    $error = 'خطا در افزودن کانال: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        $channel_link = trim($_POST['channel_link'] ?? '');
        if (!empty($channel_link)) {
            try {
                db_query($pdo, "DELETE FROM channels WHERE link = ?", [$channel_link]);
                $success = 'کانال با موفقیت حذف شد.';
            } catch (Exception $e) {
                $error = 'خطا در حذف کانال: ' . $e->getMessage();
            }
        }
    }
}

$channels = [];
try {
    $channels = db_fetchAll($pdo, "SELECT * FROM channels");
} catch (Exception $e) {
    // If table doesn't exist
}

$pageTitle = 'تنظیمات کانال‌های اجباری';
$activeNav = 'settings_channels';
$showPageHead = false;
include __DIR__ . '/inc/layout_head.php';
?>

<div class="dash-header fade-up">
    <div>
        <h1 class="dash-title"><?= icon('shield', 28) ?> تنظیمات کانال‌های اجباری</h1>
        <p class="dash-subtitle">مدیریت کانال‌هایی که کاربران برای استفاده از ربات باید در آن‌ها عضو شوند</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger fade-up" style="margin-bottom: 20px;">
        <?= icon('alert-circle', 20) ?> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success fade-up" style="margin-bottom: 20px;">
        <?= icon('check-circle', 20) ?> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="card fade-up">
    <div class="card-head">
        <div>
            <div class="card-title">افزودن کانال جدید</div>
            <div class="card-subtitle">اطلاعات کانال جدید را وارد کنید. مطمئن شوید که ربات در این کانال ادمین است.</div>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="settings_channels.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">نام کانال (برای نمایش)</label>
                <input type="text" name="remark" class="input" placeholder="مثلاً: کانال اصلی" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">آیدی کانال (جهت بررسی)</label>
                <input type="text" name="link" class="input" placeholder="مثلاً: @MyChannel یا -100XXXX" required dir="ltr">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">لینک جوین (برای دکمه)</label>
                <input type="url" name="linkjoin" class="input" placeholder="https://t.me/joinchat/..." required dir="ltr">
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 40px;">
                    <?= icon('plus', 18) ?> افزودن کانال
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card fade-up" style="margin-top: 20px;">
    <div class="card-head">
        <div>
            <div class="card-title">لیست کانال‌های ثبت شده</div>
            <div class="card-subtitle">برای حذف یک کانال از دکمه حذف استفاده کنید.</div>
        </div>
        <div class="badge badge-info"><?= count($channels) ?> کانال فعال</div>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="table" style="min-width: 600px;">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>نام کانال</th>
                    <th>آیدی کانال</th>
                    <th>لینک جوین</th>
                    <th width="100" style="text-align: left;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($channels)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 30px; color: var(--mute);">
                            <?= icon('alert-circle', 32, ['style' => 'opacity: 0.5; margin-bottom: 10px;']) ?><br>
                            هیچ کانال اجباری ثبت نشده است.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($channels as $index => $ch): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($ch['remark']) ?></td>
                            <td dir="ltr" style="font-family: monospace; font-size: 0.9em;"><?= htmlspecialchars($ch['link']) ?></td>
                            <td dir="ltr">
                                <a href="<?= htmlspecialchars($ch['linkjoin']) ?>" target="_blank" style="color: var(--ac); text-decoration: none; font-size: 0.85em; display: inline-flex; align-items: center; gap: 4px;">
                                    <?= icon('external-link', 14) ?> <?= htmlspecialchars(strlen($ch['linkjoin']) > 40 ? substr($ch['linkjoin'], 0, 40) . '...' : $ch['linkjoin']) ?>
                                </a>
                            </td>
                            <td style="text-align: left;">
                                <form method="POST" action="settings_channels.php" style="display: inline-block;" onsubmit="return confirm('آیا از حذف این کانال اطمینان دارید؟');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="channel_link" value="<?= htmlspecialchars($ch['link']) ?>">
                                    <button type="submit" class="btn btn-ghost" style="color: var(--danger); padding: 5px; height: 32px; width: 32px; border-radius: 6px;" title="حذف">
                                        <?= icon('trash-2', 16) ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
