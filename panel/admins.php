<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Restrict to administrator role only
$currentUserData = db_fetch($pdo, "SELECT * FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
if (!$currentUserData || $currentUserData['rule'] !== 'administrator') {
    flash('error', 'شما دسترسی لازم برای مدیریت ادمین‌ها را ندارید.');
    header('Location: index.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id_admin = trim($_POST['id_admin'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rule     = $_POST['rule'] ?? 'administrator';

        if ($id_admin === '' || $username === '' || $password === '') {
            flash('error', 'تمام فیلدها الزامی هستند.');
        } else {
            $exists = db_fetch($pdo, "SELECT * FROM admin WHERE id_admin = ? OR username = ?", [$id_admin, $username]);
            if ($exists) {
                flash('error', 'شناسه کاربری یا نام کاربری تکراری است.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                try {
                    db_query($pdo, "INSERT INTO admin (id_admin, username, password, rule) VALUES (?, ?, ?, ?)", [
                        $id_admin, $username, $hash, $rule
                    ]);
                    flash('success', 'ادمین با موفقیت اضافه شد.');
                } catch (Exception $e) {
                    flash('error', 'خطا در افزودن ادمین.');
                }
            }
        }
        header('Location: admins.php');
        exit;
    }

    if ($action === 'edit') {
        $id_admin = trim($_POST['id_admin'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rule     = $_POST['rule'] ?? 'administrator';

        if ($id_admin === '' || $username === '') {
            flash('error', 'شناسه و نام کاربری الزامی هستند.');
        } else {
            $existing = db_fetch($pdo, "SELECT * FROM admin WHERE id_admin = ?", [$id_admin]);
            if (!$existing) {
                flash('error', 'ادمین پیدا نشد.');
            } else {
                $checkUser = db_fetch($pdo, "SELECT * FROM admin WHERE username = ? AND id_admin != ?", [$username, $id_admin]);
                if ($checkUser) {
                    flash('error', 'نام کاربری تکراری است.');
                } else {
                    $hash = $password ? password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]) : $existing['password'];
                    try {
                        db_query($pdo, "UPDATE admin SET username = ?, password = ?, rule = ? WHERE id_admin = ?", [
                            $username, $hash, $rule, $id_admin
                        ]);
                        flash('success', 'ادمین با موفقیت ویرایش شد.');
                    } catch (Exception $e) {
                        flash('error', 'خطا در ویرایش ادمین.');
                    }
                }
            }
        }
        header('Location: admins.php');
        exit;
    }

    if ($action === 'delete') {
        $id_admin = trim($_POST['id_admin'] ?? '');
        if ($id_admin === $currentUserData['id_admin']) {
            flash('error', 'شما نمی‌توانید حساب کاربری خودتان را حذف کنید.');
        } else {
            try {
                db_query($pdo, "DELETE FROM admin WHERE id_admin = ?", [$id_admin]);
                flash('success', 'ادمین با موفقیت حذف شد.');
            } catch (Exception $e) {
                flash('error', 'خطا در حذف ادمین.');
            }
        }
        header('Location: admins.php');
        exit;
    }
}

// Stats
$totalAdmins   = (int) db_count($pdo, "SELECT COUNT(*) FROM admin");
$totalAdminsRole = db_fetchAll($pdo, "SELECT rule, COUNT(*) as cnt FROM admin GROUP BY rule");
$roleCount = [];
foreach ($totalAdminsRole as $r) $roleCount[$r['rule']] = (int)$r['cnt'];

// Search
$search   = trim($_GET['q'] ?? '');
$whereSQL = '';
$params   = [];
if ($search !== '') {
    $whereSQL = "WHERE (id_admin LIKE ? OR username LIKE ? OR rule LIKE ?)";
    $params   = ["%$search%", "%$search%", "%$search%"];
}
$admins = db_fetchAll($pdo, "SELECT * FROM admin $whereSQL ORDER BY id_admin ASC", $params);

$pageTitle = 'مدیریت ادمین‌ها';
$pageLede  = 'مدیریت حساب‌های مدیران، فروشندگان و پشتیبان‌ها';
$activeNav = 'admins';
include __DIR__ . '/inc/layout_head.php';
?>

<?php
// Role config map
$roleConfig = [
    'administrator' => ['label' => 'مدیر کل',  'tag' => 'tag-ok',   'icon' => 'settings',      'color' => '#22c55e'],
    'Seller'        => ['label' => 'فروشنده',   'tag' => 'tag-info', 'icon' => 'wallet',        'color' => '#3b82f6'],
    'support'       => ['label' => 'پشتیبان',   'tag' => 'tag-warn', 'icon' => 'message-square','color' => '#f59e0b'],
];
$getRoleConf = fn($rule) => $roleConfig[$rule] ?? ['label' => $rule, 'tag' => 'tag-plain', 'icon' => 'user', 'color' => '#6b7280'];
?>

<!-- Stats Row -->
<div class="stats fade-up" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;">
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue"><?= icon('users', 20) ?></div>
            <div class="dash-card-title">کل ادمین‌ها</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill neutral">حساب ثبت‌شده</span></div>
            <div class="dash-card-value"><?= $totalAdmins ?></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-emerald"><?= icon('settings', 20) ?></div>
            <div class="dash-card-title">مدیران کل</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill success">Administrator</span></div>
            <div class="dash-card-value"><?= $roleCount['administrator'] ?? 0 ?></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue"><?= icon('wallet', 20) ?></div>
            <div class="dash-card-title">فروشندگان</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill info">Seller</span></div>
            <div class="dash-card-value"><?= $roleCount['Seller'] ?? 0 ?></div>
        </div>
    </div>
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-amber"><?= icon('message-square', 20) ?></div>
            <div class="dash-card-title">پشتیبان‌ها</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill"><span class="status-pill warning">Support</span></div>
            <div class="dash-card-value"><?= $roleCount['support'] ?? 0 ?></div>
        </div>
    </div>
</div>

<!-- Admin Table Card -->
<div class="card fade-up d1">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title">لیست ادمین‌ها <small>(<?= count($admins) ?>)</small></div>
            <button class="btn btn-primary btn-sm" onclick="openAdminModal()">
                <?= icon('plus', 14) ?> افزودن ادمین
            </button>
        </div>
        <form method="GET" class="toolbar-end">
            <div class="search-box" style="min-width:260px">
                <?= icon('search', 15) ?>
                <input type="text" name="q" placeholder="جستجو در شناسه، نام، سطح..."
                    value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="button" class="search-clear" onclick="window.location='admins.php'">✕</button>
                <button type="submit" class="search-btn">جستجو</button>
            </div>
        </form>
    </div>

    <div class="tbl-wrap">
        <table class="tbl-xl" id="adminsTbl">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>شناسه تلگرام</th>
                    <th>نام کاربری پنل</th>
                    <th>سطح دسترسی</th>
                    <th>وضعیت</th>
                    <th style="width:110px">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty" style="padding:48px 20px">
                                <svg class="ill" viewBox="0 0 200 160" fill="none">
                                    <circle cx="100" cy="60" r="40" fill="var(--surface-3)" />
                                    <circle cx="100" cy="47" r="18" fill="var(--border-strong)" />
                                    <path d="M62 105 Q100 88 138 105" stroke="var(--border-strong)" stroke-width="8" stroke-linecap="round" fill="none" />
                                </svg>
                                <p>ادمینی یافت نشد.</p>
                                <button class="btn btn-primary" style="margin-top:12px" onclick="openAdminModal()">
                                    <?= icon('plus', 14) ?> افزودن اولین ادمین
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else:
                    $i = 1;
                    foreach ($admins as $ad):
                        $rc  = $getRoleConf($ad['rule']);
                        $isMe = ($ad['id_admin'] === $currentUserData['id_admin']);
                ?>
                    <tr>
                        <td class="cf" data-label="#"><?= $i++ ?></td>
                        <td data-label="شناسه تلگرام">
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,<?= $rc['color'] ?>33,<?= $rc['color'] ?>11);border:1px solid <?= $rc['color'] ?>44;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $rc['color'] ?>">
                                    <?= icon($rc['icon'], 15) ?>
                                </div>
                                <div>
                                    <div class="cm" style="font-size:.82rem;font-weight:600"><?= htmlspecialchars($ad['id_admin']) ?></div>
                                    <?php if ($isMe): ?>
                                    <div style="font-size:.68rem;color:var(--accent);margin-top:1px">● حساب جاری</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td data-label="نام کاربری">
                            <span class="cm" style="font-weight:500"><?= htmlspecialchars($ad['username']) ?></span>
                        </td>
                        <td data-label="سطح دسترسی">
                            <span class="tag <?= $rc['tag'] ?>"><?= $rc['label'] ?></span>
                        </td>
                        <td data-label="وضعیت">
                            <?php if ($isMe): ?>
                                <span class="status-pill success">آنلاین</span>
                            <?php else: ?>
                                <span class="status-pill neutral">فعال</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="عملیات">
                            <div style="display:flex;gap:4px">
                                <button class="btn btn-ghost btn-sm btn-icon" title="ویرایش"
                                    onclick='openEditModal(<?= htmlspecialchars(json_encode([
                                        "id"       => $ad['id_admin'],
                                        "username" => $ad['username'],
                                        "rule"     => $ad['rule'],
                                    ]), ENT_QUOTES) ?>)'>
                                    <?= icon('edit', 14) ?>
                                </button>
                                <?php if (!$isMe): ?>
                                <button class="btn btn-no btn-sm btn-icon" title="حذف"
                                    onclick="deleteAdmin('<?= htmlspecialchars($ad['id_admin']) ?>', '<?= htmlspecialchars($ad['username']) ?>')">
                                    <?= icon('trash', 14) ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- =================== ADD MODAL =================== -->
<div class="modal-veil" id="addModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-head">
            <h3><?= icon('plus', 16) ?> افزودن ادمین جدید</h3>
            <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="field full">
                        <label>شناسه عددی تلگرام <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="id_admin" id="add-id" class="input"
                            placeholder="مثال: 123456789" required
                            pattern="\d+" title="فقط عدد مجاز است">
                        <small style="color:var(--mute);margin-top:4px;display:block">شناسه عددی کاربر در تلگرام</small>
                    </div>
                    <div class="field">
                        <label>نام کاربری پنل <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="username" id="add-username" class="input"
                            placeholder="مثال: admin1" required>
                    </div>
                    <div class="field">
                        <label>رمز عبور <span style="color:var(--accent)">*</span></label>
                        <input type="password" name="password" id="add-password" class="input"
                            placeholder="حداقل ۶ کاراکتر" required minlength="6">
                    </div>
                    <div class="field full">
                        <label>سطح دسترسی</label>
                        <select name="rule" id="add-rule" class="select" required onchange="updateRoleDesc('add')">
                            <option value="administrator">مدیر کل (Administrator)</option>
                            <option value="Seller">فروشنده (Seller)</option>
                            <option value="support">پشتیبان (Support)</option>
                        </select>
                        <div id="add-role-desc" style="margin-top:8px;padding:10px 12px;border-radius:8px;background:var(--surface-2);border:1px solid var(--border);font-size:.78rem;color:var(--mute)">
                            دسترسی کامل به تمام بخش‌های سیستم
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> افزودن ادمین</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<!-- =================== EDIT MODAL =================== -->
<div class="modal-veil" id="editModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-head">
            <h3><?= icon('edit', 16) ?> ویرایش ادمین</h3>
            <button class="modal-x" onclick="closeModal('editModal')"><?= icon('close', 14) ?></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_admin" id="edit-id">

                <!-- Admin Info Card -->
                <div id="edit-info-card" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);margin-bottom:16px">
                    <div id="edit-avatar" style="width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;flex-shrink:0;color:#fff;background:linear-gradient(135deg,#3b82f6,#6366f1)">A</div>
                    <div>
                        <div id="edit-display-name" style="font-weight:600;font-size:.9rem"></div>
                        <div id="edit-display-id" style="font-size:.75rem;color:var(--mute);margin-top:2px"></div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field full">
                        <label>شناسه تلگرام</label>
                        <input type="text" id="edit-id-show" class="input" disabled
                            style="opacity:.6;cursor:not-allowed;background:var(--surface-2)">
                    </div>
                    <div class="field">
                        <label>نام کاربری پنل <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="username" id="edit-username" class="input" required>
                    </div>
                    <div class="field">
                        <label>رمز عبور جدید</label>
                        <input type="password" name="password" id="edit-password" class="input"
                            placeholder="خالی = بدون تغییر" minlength="6">
                        <small style="color:var(--mute);margin-top:4px;display:block">برای تغییر رمز پر کنید</small>
                    </div>
                    <div class="field full">
                        <label>سطح دسترسی</label>
                        <select name="rule" id="edit-rule" class="select" onchange="updateRoleDesc('edit')">
                            <option value="administrator">مدیر کل (Administrator)</option>
                            <option value="Seller">فروشنده (Seller)</option>
                            <option value="support">پشتیبان (Support)</option>
                        </select>
                        <div id="edit-role-desc" style="margin-top:8px;padding:10px 12px;border-radius:8px;background:var(--surface-2);border:1px solid var(--border);font-size:.78rem;color:var(--mute)"></div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> ذخیره تغییرات</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">انصراف</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete hidden form -->
<form method="POST" id="delete-form" style="display:none">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_admin" id="d-id">
</form>

<script>
const roleDescriptions = {
    administrator: 'دسترسی کامل به تمام بخش‌های سیستم، مدیریت کاربران، تنظیمات و پنل‌ها',
    Seller:        'دسترسی به بخش فروش، مدیریت سفارشات و صدور فاکتور — بدون دسترسی به تنظیمات کلی',
    support:       'مشاهده اطلاعات کاربران و ارسال پیام — بدون دسترسی به مالی یا تنظیمات'
};

const avatarColors = {
    administrator: 'linear-gradient(135deg,#22c55e,#16a34a)',
    Seller:        'linear-gradient(135deg,#3b82f6,#6366f1)',
    support:       'linear-gradient(135deg,#f59e0b,#d97706)'
};

function updateRoleDesc(prefix) {
    const rule = document.getElementById(prefix + '-rule').value;
    const desc = document.getElementById(prefix + '-role-desc');
    if (desc) desc.innerText = roleDescriptions[rule] || '';
}

function openAdminModal() {
    document.getElementById('add-id').value       = '';
    document.getElementById('add-username').value  = '';
    document.getElementById('add-password').value  = '';
    document.getElementById('add-rule').value      = 'administrator';
    updateRoleDesc('add');
    openModal('addModal');
}

function openEditModal(data) {
    document.getElementById('edit-id').value       = data.id;
    document.getElementById('edit-id-show').value  = data.id;
    document.getElementById('edit-username').value = data.username;
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-rule').value     = data.rule;

    // Update info card
    const initials = (data.username || '?').charAt(0).toUpperCase();
    document.getElementById('edit-avatar').innerText   = initials;
    document.getElementById('edit-avatar').style.background = avatarColors[data.rule] || 'linear-gradient(135deg,#6b7280,#4b5563)';
    document.getElementById('edit-display-name').innerText  = data.username;
    document.getElementById('edit-display-id').innerText    = 'ID: ' + data.id;

    updateRoleDesc('edit');
    openModal('editModal');
}

function deleteAdmin(id, name) {
    if (confirm('آیا از حذف ادمین «' + name + '» اطمینان دارید؟\nاین عمل قابل بازگشت نیست.')) {
        document.getElementById('d-id').value = id;
        document.getElementById('delete-form').submit();
    }
}

// Init role desc on page load
updateRoleDesc('add');
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
