<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../botapi.php';
require_auth();

function normalizeChannelId($link) {
    $link = trim($link);
    if (empty($link)) return '';
    if (preg_match('#(?:t\.me|telegram\.me)/+@?([a-zA-Z0-9_]+)#i', $link, $matches)) {
        return '@' . $matches[1];
    }
    if (!str_starts_with($link, '@') && !str_starts_with($link, '-') && !str_starts_with($link, 'http')) {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $link)) {
            return '@' . $link;
        }
    }
    return $link;
}

function getBotChatMemberStatus($chat_id) {
    global $APIKEY;
    if (empty($APIKEY)) return ['ok' => false, 'error' => 'توکن ربات تنظیم نشده است'];
    
    $botId = explode(':', $APIKEY)[0];
    $response = telegram('getChatMember', [
        'chat_id' => $chat_id,
        'user_id' => $botId
    ]);
    
    if (isset($response['ok']) && $response['ok'] === true) {
        $status = $response['result']['status'] ?? '';
        $isAdmin = in_array($status, ['administrator', 'creator']);
        return [
            'ok' => true,
            'is_admin' => $isAdmin,
            'status' => $status
        ];
    }
    
    $error = $response['description'] ?? 'خطا در ارتباط با تلگرام';
    return [
        'ok' => false,
        'is_admin' => false,
        'error' => $error
    ];
}

function isBotAdminInChat($chat_id) {
    $res = getBotChatMemberStatus($chat_id);
    return $res['is_admin'] === true;
}

function updateChannelChangeTime($pdo) {
    try {
        db_query($pdo, "UPDATE setting SET last_channel_update = ?", [time()]);
    } catch (Exception $e) {
        try {
            db_query($pdo, "ALTER TABLE setting ADD COLUMN last_channel_update VARCHAR(100) DEFAULT '0'");
            db_query($pdo, "UPDATE setting SET last_channel_update = ?", [time()]);
        } catch (Exception $e2) {
            // Ignore
        }
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$error = '';
$success = '';

if ($action === 'check_status') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $channel_link = normalizeChannelId($_GET['channel_link'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    if (empty($channel_link)) {
        echo json_encode(['ok' => false, 'error' => 'آیدی کانال خالی است']);
        exit;
    }
    $res = getBotChatMemberStatus($channel_link);
    echo json_encode($res);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    
    if ($action === 'add') {
        $remark = trim($_POST['remark'] ?? '');
        $link = normalizeChannelId($_POST['link'] ?? '');
        $linkjoin = trim($_POST['linkjoin'] ?? '');
        
        if (empty($remark) || empty($link) || empty($linkjoin)) {
            $error = 'تمامی فیلدها الزامی هستند.';
        } elseif (!filter_var($linkjoin, FILTER_VALIDATE_URL)) {
            $error = 'لینک جوین وارد شده نامعتبر است.';
        } elseif (!isBotAdminInChat($link)) {
            $error = 'ربات در این کانال ادمین نیست! لطفاً ابتدا ربات را در کانال ادمین کنید.';
        } else {
            try {
                db_query($pdo, "INSERT INTO channels (link, remark, linkjoin) VALUES (?, ?, ?)", [$link, $remark, $linkjoin]);
                updateChannelChangeTime($pdo);
                $success = 'کانال با موفقیت اضافه شد.';
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
                    try {
                        ensureTableUtf8mb4('channels');
                        db_query($pdo, "INSERT INTO channels (link, remark, linkjoin) VALUES (?, ?, ?)", [$link, $remark, $linkjoin]);
                        updateChannelChangeTime($pdo);
                        $success = 'کانال با موفقیت اضافه شد.';
                    } catch (Exception $e2) {
                        $error = 'خطا در افزودن کانال: ' . $e2->getMessage();
                    }
                } else {
                    $error = 'خطا در افزودن کانال: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'edit') {
        $old_link = normalizeChannelId($_POST['old_link'] ?? '');
        $remark = trim($_POST['remark'] ?? '');
        $link = normalizeChannelId($_POST['link'] ?? '');
        $linkjoin = trim($_POST['linkjoin'] ?? '');
        
        if (empty($old_link) || empty($remark) || empty($link) || empty($linkjoin)) {
            $error = 'تمامی فیلدها الزامی هستند.';
        } elseif (!filter_var($linkjoin, FILTER_VALIDATE_URL)) {
            $error = 'لینک جوین وارد شده نامعتبر است.';
        } elseif (!isBotAdminInChat($link)) {
            $error = 'ربات در این کانال ادمین نیست! لطفاً ابتدا ربات را در کانال ادمین کنید.';
        } else {
            try {
                db_query($pdo, "UPDATE channels SET link = ?, remark = ?, linkjoin = ? WHERE link = ?", [$link, $remark, $linkjoin, $old_link]);
                updateChannelChangeTime($pdo);
                $success = 'کانال با موفقیت ویرایش شد.';
            } catch (Exception $e) {
                $error = 'خطا در ویرایش کانال: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'save_settings') {
        $ttl = intval($_POST['channel_cache_ttl'] ?? 300);
        try {
            db_query($pdo, "UPDATE setting SET channel_cache_ttl = ?", [$ttl]);
            updateChannelChangeTime($pdo);
            $success = 'تنظیمات سخت‌گیری عضویت با موفقیت ذخیره شد.';
        } catch (Exception $e) {
            try {
                db_query($pdo, "ALTER TABLE setting ADD COLUMN channel_cache_ttl VARCHAR(100) DEFAULT '300'");
                db_query($pdo, "UPDATE setting SET channel_cache_ttl = ?", [$ttl]);
                updateChannelChangeTime($pdo);
                $success = 'تنظیمات سخت‌گیری عضویت با موفقیت ذخیره شد.';
            } catch (Exception $e2) {
                $error = 'خطا در ذخیره تنظیمات: ' . $e2->getMessage();
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'delete') {
        csrf_check_get();
        $channel_link = normalizeChannelId($_GET['channel_link'] ?? '');
        if (!empty($channel_link)) {
            try {
                db_query($pdo, "DELETE FROM channels WHERE link = ?", [$channel_link]);
                updateChannelChangeTime($pdo);
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

$setting = [];
try {
    $setting = db_fetch($pdo, "SELECT * FROM setting LIMIT 1") ?: [];
} catch (Exception $e) {}

$current_ttl = intval($setting['channel_cache_ttl'] ?? 300);

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
        <?= icon('alert-triangle', 20) ?> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success fade-up" style="margin-bottom: 20px;">
        <?= icon('check-circle', 20) ?> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!-- Quick Stats Bar -->
<div class="stats fade-up" style="margin-top: 20px;">
    <div class="stat ok">
        <div class="stat-label">کانال‌های ثبت شده</div>
        <div class="stat-num"><?= count($channels) ?> <small>کانال</small></div>
        <div class="stat-meta">بررسی خودکار عضویت</div>
    </div>
    <div class="stat">
        <div class="stat-label">فرکانس اعتبارسنجی</div>
        <div class="stat-num" style="font-size: 1.3rem; font-weight: 700;">
            <?php
            if ($current_ttl === 0) echo '🔥 بررسی زنده (۰s)';
            elseif ($current_ttl === 60) echo '⚡ ۱ دقیقه';
            elseif ($current_ttl === 300) echo '✅ ۵ دقیقه';
            elseif ($current_ttl === 1800) echo '⏱ ۳۰ دقیقه';
            elseif ($current_ttl === 3600) echo '⏳ ۱ ساعت';
            else echo '📅 ۲۴ ساعت';
            ?>
        </div>
        <div class="stat-meta">سطح سخت‌گیری سیستم</div>
    </div>
    <div class="stat ok">
        <div class="stat-label">وضعیت سیستم</div>
        <div class="stat-num" style="font-size: 1.3rem; font-weight: 700; color: var(--ok);">فعال و ایمن</div>
        <div class="stat-meta"><span class="up">●</span> سیستم ضد دور زدن فعال</div>
    </div>
</div>

<!-- Config Cards Row (2-Column) -->
<div class="two-col fade-up" style="margin-bottom: 24px;">
    <!-- Add Channel Card -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><?= icon('plus', 16) ?> افزودن کانال جدید</div>
        </div>
        <div class="card-body">
            <form method="POST" action="settings_channels.php" style="display: flex; flex-direction: column; gap: 15px;">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="form-grid">
                    <div class="field">
                        <label>نام کانال (برای نمایش)</label>
                        <input type="text" name="remark" class="input" placeholder="مثلاً: کانال اصلی" required>
                    </div>
                    
                    <div class="field">
                        <label>آیدی کانال (جهت بررسی)</label>
                        <input type="text" name="link" class="input" placeholder="مثلاً: @MyChannel یا -100XXXX" required dir="ltr">
                    </div>
                </div>
                
                <div class="field">
                    <label>لینک جوین (برای دکمه)</label>
                    <input type="url" name="linkjoin" class="input" placeholder="https://t.me/joinchat/..." required dir="ltr">
                </div>
                
                <button type="submit" class="btn btn-primary" style="justify-content: center; width: 100%;">
                    <?= icon('plus', 16) ?> افزودن کانال به لیست
                </button>
            </form>
        </div>
    </div>

    <!-- Strictness Setting Card -->
    <div class="card">
        <div class="card-head">
            <div class="card-title"><?= icon('shield', 16) ?> تنظیم سخت‌گیری اعتبارسنجی عضویت</div>
        </div>
        <div class="card-body" style="display: flex; flex-direction: column; justify-content: space-between; height: calc(100% - 57px);">
            <form method="POST" action="settings_channels.php" style="display: flex; flex-direction: column; gap: 15px; height: 100%;">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="field">
                    <label>فرکانس بررسی مجدد عضویت کاربران</label>
                    <select name="channel_cache_ttl" class="select">
                        <option value="0" <?= $current_ttl === 0 ? 'selected' : '' ?>>🔥 بررسی زنده در هر کلیک (سختگیرانه‌ترین)</option>
                        <option value="60" <?= $current_ttl === 60 ? 'selected' : '' ?>>⚡ هر ۱ دقیقه یکبار</option>
                        <option value="300" <?= $current_ttl === 300 ? 'selected' : '' ?>>✅ هر ۵ دقیقه یکبار (پیش‌فرض پیشنهادی)</option>
                        <option value="1800" <?= $current_ttl === 1800 ? 'selected' : '' ?>>⏱ هر ۳۰ دقیقه یکبار</option>
                        <option value="3600" <?= $current_ttl === 3600 ? 'selected' : '' ?>>⏳ هر ۱ ساعت یکبار</option>
                        <option value="86400" <?= $current_ttl === 86400 ? 'selected' : '' ?>>📅 هر ۲۴ ساعت یکبار</option>
                    </select>
                    <div class="field-hint" style="margin-top: 6px; line-height: 1.5;">در حالت بررسی زنده (۰ ثانیه)، اگر کاربر از کانالی لفت بدهد، در کلیک بعدی فوراً شناسایی و مسدود می‌شود.</div>
                </div>
                
                <button type="submit" class="btn btn-ghost" style="justify-content: center; width: 100%; margin-top: auto;">
                    <?= icon('check', 14) ?> ذخیره تنظیمات سخت‌گیری
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Full-Width Channels List Card -->
<div class="card fade-up">
    <div class="card-head">
        <div>
            <div class="card-title"><?= icon('layers', 16) ?> لیست کانال‌های ثبت شده</div>
            <div class="card-subtitle">مدیریت کانال‌های فعال و کنترل عضویت اجباری کاربران.</div>
        </div>
        <div class="tag tag-info"><?= count($channels) ?> کانال فعال</div>
    </div>
    
    <div class="tbl-wrap dash-channels">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>نام کانال</th>
                    <th>آیدی کانال</th>
                    <th>لینک جوین</th>
                    <th>وضعیت ربات</th>
                    <th style="width: 150px; text-align: left;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($channels)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 48px; color: var(--mute);">
                            <div class="empty" style="padding: 0;">
                                <span style="opacity: 0.3; display: inline-block; margin-bottom: 1rem;"><?= icon('inbox', 48) ?></span><br>
                                هیچ کانال اجباری ثبت نشده است.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($channels as $index => $ch): ?>
                        <tr>
                            <td data-label="#" class="no-label"><?= $index + 1 ?></td>
                            <td data-label="نام کانال" style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($ch['remark']) ?></td>
                            <td data-label="آیدی کانال">
                                <span class="tag tag-plain" style="font-family: monospace; font-size: 0.85rem;" dir="ltr"><?= htmlspecialchars($ch['link']) ?></span>
                            </td>
                            <td data-label="لینک جوین">
                                <a href="<?= htmlspecialchars($ch['linkjoin']) ?>" target="_blank" class="btn-link" style="display: inline-flex; align-items: center; gap: 4px;" dir="ltr">
                                    <?= icon('link', 14) ?> <?= htmlspecialchars(strlen($ch['linkjoin']) > 30 ? substr($ch['linkjoin'], 0, 30) . '...' : $ch['linkjoin']) ?>
                                </a>
                            </td>
                            <td data-label="وضعیت ربات">
                                <span class="tag tag-warning channel-status-cell" data-chatid="<?= htmlspecialchars($ch['link']) ?>" style="font-size: 0.82rem;">
                                    در حال بررسی...
                                </span>
                            </td>
                            <td data-label="عملیات" style="text-align: left;">
                                <div style="display: inline-flex; gap: 8px;">
                                    <button type="button" class="btn btn-sm btn-ghost btn-icon" title="ویرایش" 
                                            onclick='window.openEditModal(<?= json_encode($ch, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'>
                                        <?= icon('edit', 14) ?>
                                    </button>
                                    <a href="settings_channels.php?action=delete&channel_link=<?= urlencode($ch['link']) ?>&_csrf=<?= csrf_token() ?>" 
                                       class="btn btn-sm btn-no btn-icon" title="حذف" 
                                       onclick="return confirm('آیا از حذف این کانال اطمینان دارید؟');">
                                        <?= icon('trash', 14) ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-veil" id="editChannelModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-head">
            <h3><?= icon('edit', 16) ?> ویرایش کانال</h3>
            <button type="button" class="modal-x" onclick="window.closeEditModal()"><?= icon('x', 14) ?></button>
        </div>
        <form method="POST" action="settings_channels.php">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="old_link" id="editOldLink" value="">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 15px;">
                <div class="field">
                    <label>نام کانال (برای نمایش)</label>
                    <input type="text" name="remark" id="editRemark" class="input" required>
                </div>
                
                <div class="field">
                    <label>آیدی کانال (جهت بررسی)</label>
                    <input type="text" name="link" id="editLink" class="input" required dir="ltr">
                </div>
                
                <div class="field">
                    <label>لینک جوین (برای دکمه)</label>
                    <input type="url" name="linkjoin" id="editLinkjoin" class="input" required dir="ltr">
                </div>
            </div>
            
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> ذخیره تغییرات</button>
                <button type="button" class="btn btn-ghost" onclick="window.closeEditModal()">انصراف</button>
            </div>
        </form>
    </div>
</div>

<script>
window.openEditModal = function(ch) {
    document.getElementById('editOldLink').value = ch.link;
    document.getElementById('editRemark').value = ch.remark;
    document.getElementById('editLink').value = ch.link;
    document.getElementById('editLinkjoin').value = ch.linkjoin;
    document.getElementById('editChannelModal').classList.add('open');
};

window.closeEditModal = function() {
    document.getElementById('editChannelModal').classList.remove('open');
};

(function() {
    const statusCells = document.querySelectorAll('.channel-status-cell');
    statusCells.forEach(cell => {
        const chatId = cell.getAttribute('data-chatid');
        if (!chatId) return;
        fetch(`settings_channels.php?action=check_status&channel_link=${encodeURIComponent(chatId)}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.ok) {
                    if (data.is_admin) {
                        cell.className = 'tag tag-ok';
                        cell.textContent = 'ربات ادمین است';
                    } else {
                        cell.className = 'tag tag-no';
                        cell.textContent = 'ربات ادمین نیست';
                    }
                } else {
                    cell.className = 'tag tag-no';
                    cell.textContent = (data && data.error) ? (`خطا: ${data.error}`) : 'ربات ادمین نیست';
                }
            })
            .catch(err => {
                cell.className = 'tag tag-warning';
                cell.textContent = 'خطای شبکه';
            });
    });
})();
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
