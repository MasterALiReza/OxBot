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
        $rule = $_POST['rule'] ?? 'administrator';
        
        if ($id_admin === '' || $username === '' || $password === '') {
            flash('error', 'تمام فیلدها الزامی هستند.');
        } else {
            // check if id_admin or username already exists
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
        $id_admin = trim($_POST['id_admin'] ?? ''); // Read-only PK
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rule = $_POST['rule'] ?? 'administrator';
        
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

// Search
$search = trim($_GET['q'] ?? '');
$whereSQL = '';
$params = [];
if ($search !== '') {
    $whereSQL = "WHERE (id_admin LIKE ? OR username LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

$admins = db_fetchAll($pdo, "SELECT * FROM admin $whereSQL ORDER BY id_admin ASC", $params);

$pageTitle = 'مدیریت ادمین‌ها';
$pageLede = 'مدیریت حساب‌های مدیران، فروشندگان و پشتیبان‌ها';
$activeNav = 'admins';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="card fade-up">
    <div class="toolbar">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="toolbar-title">لیست ادمین‌ها <small>(<?= count($admins) ?>)</small></div>
            <button class="btn btn-primary btn-sm" onclick="openAdminModal()"><?= icon('plus', 14) ?> افزودن ادمین</button>
        </div>

        <form method="GET" class="toolbar-end">
            <div class="search-box" style="min-width:260px">
                <?= icon('search', 15) ?>
                <input type="text" name="q" placeholder="جستجوی شناسه یا نام کاربری..."
                    value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="button" class="search-clear" onclick="window.location='admins.php'">✕</button>
                <button type="submit" class="search-btn"><?= $textbotlang['panel']['usersSearchBtn'] ?></button>
            </div>
        </form>
    </div>

    <div class="tbl-wrap">
        <table class="tbl-xl">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>شناسه عددی (تلگرام)</th>
                    <th>نام کاربری (پنل)</th>
                    <th>سطح دسترسی</th>
                    <th style="width:120px">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty">
                                <svg class="ill" viewBox="0 0 200 160" fill="none">
                                    <circle cx="100" cy="60" r="40" fill="var(--sf3)" />
                                    <circle cx="100" cy="47" r="18" fill="var(--bds)" />
                                    <path d="M62 105 Q100 88 138 105" stroke="var(--bds)" stroke-width="8" stroke-linecap="round" fill="none" />
                                </svg>
                                <p>ادمینی یافت نشد.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: 
                    $i = 1;
                    foreach ($admins as $ad): 
                        $roleLabel = match($ad['rule']) {
                            'administrator' => 'مدیر کل',
                            'Seller' => 'فروشنده',
                            'support' => 'پشتیبان',
                            default => $ad['rule']
                        };
                        $roleTag = match($ad['rule']) {
                            'administrator' => 'tag-ok',
                            'Seller' => 'tag-info',
                            'support' => 'tag-warn',
                            default => 'tag-plain'
                        };
                ?>
                    <tr>
                        <td class="cf" data-label="#"><?= $i++ ?></td>
                        <td class="cm" data-label="شناسه عددی"><?= htmlspecialchars($ad['id_admin']) ?></td>
                        <td class="cm" data-label="نام کاربری"><?= htmlspecialchars($ad['username']) ?></td>
                        <td data-label="سطح دسترسی">
                            <span class="tag <?= $roleTag ?>"><?= $roleLabel ?></span>
                        </td>
                        <td data-label="عملیات">
                            <div style="display:flex;gap:4px">
                                <button class="btn btn-ghost btn-sm btn-icon" title="ویرایش" 
                                    onclick="editAdmin('<?= htmlspecialchars($ad['id_admin']) ?>', '<?= htmlspecialchars($ad['username']) ?>', '<?= htmlspecialchars($ad['rule']) ?>')">
                                    <?= icon('edit', 14) ?>
                                </button>
                                <?php if ($ad['id_admin'] !== $currentUserData['id_admin']): ?>
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

<!-- Admin Modal -->
<div class="confirm-veil" id="admin-modal" style="display:none;align-items:center;justify-content:center;">
    <div class="confirm-box" style="text-align:right;width:100%;max-width:400px;padding:24px;">
        <h3 id="modal-title" style="margin-top:0;margin-bottom:20px;">افزودن ادمین</h3>
        <form method="POST" id="admin-form">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" id="modal-action" value="add">
            
            <div class="field">
                <label>شناسه عددی (تلگرام)</label>
                <input type="text" name="id_admin" id="f-id" class="input" required>
            </div>
            <div class="field">
                <label>نام کاربری پنل</label>
                <input type="text" name="username" id="f-username" class="input" required>
            </div>
            <div class="field">
                <label>رمز عبور <small id="f-pass-help" style="color:var(--txt2);font-weight:normal"></small></label>
                <input type="password" name="password" id="f-password" class="input">
            </div>
            <div class="field">
                <label>سطح دسترسی</label>
                <select name="rule" id="f-rule" class="select" required>
                    <option value="administrator">مدیر کل (Administrator)</option>
                    <option value="Seller">فروشنده (Seller)</option>
                    <option value="support">پشتیبان (Support)</option>
                </select>
            </div>
            
            <div class="confirm-btns" style="margin-top:20px;justify-content:flex-end">
                <button type="button" class="btn btn-ghost" onclick="closeAdminModal()">انصراف</button>
                <button type="submit" class="btn btn-primary" id="modal-btn">ذخیره</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="delete-form" style="display:none">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_admin" id="d-id">
</form>

<script>
const modal = document.getElementById('admin-modal');

function openAdminModal() {
    document.getElementById('modal-title').innerText = 'افزودن ادمین';
    document.getElementById('modal-action').value = 'add';
    document.getElementById('f-id').value = '';
    document.getElementById('f-id').readOnly = false;
    document.getElementById('f-username').value = '';
    document.getElementById('f-password').value = '';
    document.getElementById('f-password').required = true;
    document.getElementById('f-pass-help').innerText = '(الزامی)';
    document.getElementById('f-rule').value = 'administrator';
    document.getElementById('modal-btn').innerText = 'افزودن';
    modal.style.display = 'flex';
}

function editAdmin(id, username, rule) {
    document.getElementById('modal-title').innerText = 'ویرایش ادمین';
    document.getElementById('modal-action').value = 'edit';
    document.getElementById('f-id').value = id;
    document.getElementById('f-id').readOnly = true;
    document.getElementById('f-username').value = username;
    document.getElementById('f-password').value = '';
    document.getElementById('f-password').required = false;
    document.getElementById('f-pass-help').innerText = '(برای عدم تغییر، خالی بگذارید)';
    document.getElementById('f-rule').value = rule;
    document.getElementById('modal-btn').innerText = 'ذخیره تغییرات';
    modal.style.display = 'flex';
}

function closeAdminModal() {
    modal.style.display = 'none';
}

function deleteAdmin(id, name) {
    if (confirm('آیا از حذف ادمین (' + name + ') اطمینان دارید؟')) {
        document.getElementById('d-id').value = id;
        document.getElementById('delete-form').submit();
    }
}
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
