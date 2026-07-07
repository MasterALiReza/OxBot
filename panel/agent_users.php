<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';

if (!isset($_SESSION['agent_id'])) {
    header("Location: agent_login.php");
    exit;
}

$agent_id = $_SESSION['agent_id'];

// Get agent name and type
$stmt = $pdo->prepare("SELECT namecustom, agent FROM user WHERE id = :id");
$stmt->execute([':id' => $agent_id]);
$agentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
$agentUsername = !empty($agentUserRow['namecustom']) ? $agentUserRow['namecustom'] : 'نماینده ' . $agent_id;
$agentType = $agentUserRow['agent'] ?? 'n'; // e.g., 'n', 'n2', 'all'

$initials = mb_strtoupper(mb_substr($agentUsername, 0, 1, 'UTF-8'), 'UTF-8');

// Fetch allowed panels
$stmtPanel = $pdo->prepare("SELECT * FROM marzban_panel WHERE agent = :agent OR agent = 'all'");
$stmtPanel->execute([':agent' => $agentType]);
$allowedPanels = $stmtPanel->fetchAll(PDO::FETCH_ASSOC);

// Fetch allowed products
$stmtProduct = $pdo->prepare("SELECT * FROM product WHERE (agent = :agent OR agent = 'all')");
$stmtProduct->execute([':agent' => $agentType]);
$allowedProducts = $stmtProduct->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل نمایندگی - مدیریت کاربران</title>
    <link rel="stylesheet" href="css/agent_users.css">
    
    <!-- QRCode Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="agent-panel-body">

    <!-- Mobile Header & Toggle -->
    <div class="au-mobile-toggle" style="padding: 16px 20px; background: var(--au-surface); border-bottom: 1px solid var(--au-border); align-items: center; justify-content: space-between; position: fixed; top: 0; left: 0; right: 0; z-index: 90;">
        <div style="font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 10px;">
            <button id="au-mobile-toggle" class="au-btn-icon" style="border: none; background: transparent; padding: 0;">
                <?= icon('menu', 24) ?>
            </button>
            <span>پنل نمایندگی</span>
        </div>
        <div>
            <a href="ajax/agent_auth.php?action=logout" class="au-btn-icon" style="border: none; background: transparent; color: var(--au-danger); text-decoration: none; display: flex;" title="خروج">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </div>

    <!-- Sidebar -->
    <aside class="au-sidebar" id="au-sidebar">
        <div class="au-sidebar-header">
            <div class="au-avatar"><?= $initials ?></div>
            <div class="au-sidebar-title">
                <strong>پنل نمایندگی</strong>
                <span><?= $agentUsername ?></span>
            </div>
        </div>
        <nav class="au-nav">
            <a href="agent_users.php" class="au-nav-item">
                <?= icon('dashboard', 18) ?> داشبورد
            </a>
            <a href="agent_users.php" class="au-nav-item active">
                <?= icon('users', 18) ?> مدیریت کاربران
            </a>
            <a href="javascript:void(0)" onclick="alert('این بخش به زودی فعال می‌شود');" class="au-nav-item">
                <?= icon('activity', 18) ?> لاگ عملیات
            </a>
            <a href="javascript:void(0)" onclick="alert('این بخش به زودی فعال می‌شود');" class="au-nav-item">
                <?= icon('database', 18) ?> مستندات API
            </a>
            <a href="javascript:void(0)" onclick="alert('این بخش به زودی فعال می‌شود');" class="au-nav-item">
                <?= icon('settings', 18) ?> تنظیمات
            </a>
        </nav>
        <div class="au-sidebar-footer">
            <a href="#" class="au-nav-item">
                <?= icon('user', 16) ?> حساب نماینده
            </a>
            <a href="#" class="au-nav-item">
                <?= icon('shield', 16) ?> نشست فعال
            </a>
            <a href="ajax/agent_auth.php?action=logout" class="au-nav-item" style="color: var(--au-danger);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> 
                خروج از حساب
            </a>
            <a href="#" class="au-nav-item" style="color: var(--au-text-muted);" onclick="document.getElementById('au-sidebar').classList.remove('open')">
                <?= icon('arrow-left', 16) ?> جمع کردن منو
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="au-main" style="margin-top: calc(var(--au-mobile-offset, 0px));">
        
        <div class="au-header">
            <div class="au-header-text">
                <h1>مدیریت کاربران</h1>
                <p>ساخت، نمایش و حذف کاربران با لوکیشن، حجم و مدت به‌صورت ساده</p>
            </div>
            <div class="au-header-actions">
                <button class="au-btn au-btn-icon" title="بروزرسانی">
                    <?= icon('refresh-cw', 18) ?>
                </button>
                <button class="au-btn au-btn-primary">
                    <?= icon('plus', 16) ?> ساخت کاربر
                </button>
            </div>
        </div>

        <div class="au-toolbar">
            <div class="au-search">
                <?= icon('search', 16) ?>
                <input type="text" id="au-search-input" placeholder="جستجوی کاربر یا لوکیشن...">
            </div>
            <select class="au-select" id="au-filter-status">
                <option value="all">وضعیت (همه)</option>
                <option value="active">فعال</option>
                <option value="expired">منقضی</option>
            </select>
            <select class="au-select" id="au-filter-connection">
                <option value="all">وضعیت اتصال (همه)</option>
                <option value="online">آنلاین</option>
                <option value="offline">آفلاین</option>
            </select>
            <button class="au-btn au-btn-icon" id="au-sort-btn" title="تغییر ترتیب زمانی">
                <?= icon('sliders', 18) ?>
            </button>
        </div>

        <div class="au-users-list" id="au-users-container">
            <div style="text-align:center; padding: 40px; color: var(--au-text-muted);">
                <i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>
                <p style="margin-top: 15px;">در حال بارگذاری کاربران...</p>
            </div>
        </div>

        <div class="au-pagination" id="au-pagination-container">
            <!-- Pagination items will be injected here -->
        </div>

    </main>

    <!-- Create User Modal -->
    <div id="create-modal" class="au-modal">
        <div class="au-modal-content">
            <div class="au-modal-header">
                <h2>ساخت کاربر جدید</h2>
                <button class="au-btn-icon" onclick="closeModal('create-modal')"><?= icon('x', 20) ?></button>
            </div>
            <div class="au-modal-body">
                <div class="au-form-group">
                    <label>سرور (پنل)</label>
                    <select id="create-location" class="au-select" style="width: 100%; margin-bottom: 15px;" onchange="updateProductsList('create-location', 'create-product')">
                        <option value="">-- انتخاب کنید --</option>
                        <?php foreach($allowedPanels as $p): ?>
                            <option value="<?= htmlspecialchars($p['name_panel']) ?>"><?= htmlspecialchars($p['name_panel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="au-form-group">
                    <label>سرویس (پلن)</label>
                    <select id="create-product" class="au-select" style="width: 100%; margin-bottom: 15px;">
                        <option value="">ابتدا سرور را انتخاب کنید</option>
                    </select>
                </div>
                <div class="au-form-group">
                    <label>نام کاربری (اختیاری - انگلیسی)</label>
                    <input type="text" id="create-username" class="au-input" style="width: 100%; margin-bottom: 15px; text-align: right;" placeholder="مثال: ali_123" dir="ltr">
                </div>
                <div id="create-error" style="color: var(--au-danger); font-size: 0.9rem; margin-bottom: 10px; display: none;"></div>
            </div>
            <div class="au-modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--au-border); display: flex; justify-content: flex-end; gap: 10px;">
                <button class="au-btn" onclick="closeModal('create-modal')">انصراف</button>
                <button class="au-btn au-btn-primary" id="btn-submit-create" onclick="submitCreateUser()">ساخت و کسر از حساب</button>
            </div>
        </div>
    </div>

    <!-- Renew User Modal -->
    <div id="renew-modal" class="au-modal">
        <div class="au-modal-content">
            <div class="au-modal-header">
                <h2>تمدید سرویس</h2>
                <button class="au-btn-icon" onclick="closeModal('renew-modal')"><?= icon('x', 20) ?></button>
            </div>
            <div class="au-modal-body">
                <p style="margin-bottom: 15px;">کاربر: <strong id="renew-username-lbl"></strong> (سرور: <span id="renew-location-lbl"></span>)</p>
                <input type="hidden" id="renew-invoice-id">
                <input type="hidden" id="renew-location-val">
                <div class="au-form-group">
                    <label>انتخاب سرویس جدید</label>
                    <select id="renew-product" class="au-select" style="width: 100%; margin-bottom: 15px;">
                    </select>
                </div>
                <div id="renew-error" style="color: var(--au-danger); font-size: 0.9rem; margin-bottom: 10px; display: none;"></div>
            </div>
            <div class="au-modal-footer" style="padding: 15px 20px; border-top: 1px solid var(--au-border); display: flex; justify-content: flex-end; gap: 10px;">
                <button class="au-btn" onclick="closeModal('renew-modal')">انصراف</button>
                <button class="au-btn au-btn-primary" id="btn-submit-renew" onclick="submitRenewUser()">تمدید سرویس</button>
            </div>
        </div>
    </div>

    <!-- User Management Advanced Modal -->
    <div id="manage-modal" class="au-modal">
        <div class="au-modal-content" style="max-width: 600px; background: var(--au-surface); border-radius: 12px; overflow: hidden; padding: 0;">
            <div class="au-modal-header" style="background: rgba(0,0,0,0.2); padding: 15px 20px;">
                <h2 style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-gear"></i> مدیریت سرویس کاربر</h2>
                <button class="au-btn-icon" onclick="closeModal('manage-modal')"><?= icon('x', 20) ?></button>
            </div>
            
            <div class="au-modal-body" style="padding: 20px; display: grid; gap: 15px; grid-template-columns: 1fr 1fr;">
                
                <!-- جزئیات خرید فاکتور -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--au-border); border-radius: 10px; padding: 15px;">
                    <div style="color: var(--au-text-muted); font-size: 0.85rem; margin-bottom: 5px;"><i class="fa-solid fa-file-invoice"></i> جزئیات خرید فاکتور</div>
                    <strong id="man-plan-name" style="font-size: 1rem; color: #fff; display: block; margin-bottom: 5px;">...</strong>
                    <div style="font-size: 0.85rem; color: var(--au-text-muted);">مبلغ پرداختی: <span id="man-price">...</span></div>
                </div>

                <!-- وضعیت و مشخصات عمومی -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--au-border); border-radius: 10px; padding: 15px;">
                    <div style="color: var(--au-text-muted); font-size: 0.85rem; margin-bottom: 5px;"><i class="fa-regular fa-user"></i> وضعیت و مشخصات عمومی</div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
                        <span id="man-location" style="background: rgba(255,255,255,0.1); padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">...</span>
                        <span id="man-status">...</span>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--au-text-muted);">سفارش: <span id="man-invoice-id">...</span></div>
                </div>

                <!-- اعتبار زمانی سرویس -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--au-border); border-radius: 10px; padding: 15px;">
                    <div style="color: var(--au-text-muted); font-size: 0.85rem; margin-bottom: 5px;"><i class="fa-regular fa-clock"></i> اعتبار زمانی سرویس</div>
                    <strong id="man-expired" style="font-size: 1.1rem; color: #fff; display: block; margin-bottom: 5px; direction: ltr; text-align: right;">...</strong>
                    <div style="font-size: 0.8rem; color: var(--au-text-muted);">
                        زمان باقی‌مانده: <span id="man-rem-days">...</span><br>
                        (تاریخ خرید: <span id="man-created">...</span>)
                    </div>
                </div>

                <!-- حجم مصرفی و باقیمانده -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--au-border); border-radius: 10px; padding: 15px;">
                    <div style="color: var(--au-text-muted); font-size: 0.85rem; margin-bottom: 5px;"><i class="fa-solid fa-chart-simple"></i> حجم مصرفی و باقی‌مانده</div>
                    <strong id="man-usage-rem" style="font-size: 1.1rem; color: #fff; display: block; margin-bottom: 5px;">...</strong>
                    <div class="au-progress-track" style="height: 6px; margin: 10px 0;">
                        <div class="au-progress-fill" id="man-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--au-text-muted);">مصرف شده: <span id="man-usage-used">...</span> از <span id="man-usage-limit">...</span> (<span id="man-usage-pct">...</span>)</div>
                </div>

                <!-- جزئیات اتصال -->
                <div style="grid-column: 1 / -1; background: rgba(255,255,255,0.03); border: 1px solid var(--au-border); border-radius: 10px; padding: 15px;">
                    <div style="color: var(--au-text-muted); font-size: 0.85rem; margin-bottom: 10px;"><i class="fa-solid fa-network-wired"></i> جزئیات اتصال و مشخصات</div>
                    <div style="display: flex; justify-content: space-between; text-align: center;">
                        <div style="flex: 1;">
                            <div style="font-size: 0.8rem; color: var(--au-text-muted);">وضعیت اتصال:</div>
                            <div id="man-connection" style="font-size: 0.9rem; margin-top: 5px;">...</div>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 0.8rem; color: var(--au-text-muted);">نام کاربری:</div>
                            <div id="man-username" style="font-size: 0.9rem; margin-top: 5px; color: var(--au-primary);">...</div>
                        </div>
                    </div>
                </div>

                <!-- اشتراک و کانفیگ‌ها -->
                <div style="grid-column: 1 / -1; background: rgba(255,255,255,0.03); border: 1px solid var(--au-border); border-radius: 10px; padding: 15px;">
                    <div style="color: var(--au-text-muted); font-size: 0.85rem; margin-bottom: 10px; display: flex; justify-content: space-between;">
                        <span><i class="fa-solid fa-link"></i> اشتراک و کانفیگ‌ها</span>
                        <span id="man-config-type" style="background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.75rem;">درحال بررسی...</span>
                    </div>
                    
                    <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; position: relative;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <button class="au-btn" style="font-size: 0.8rem; padding: 5px 10px; gap: 5px; background: rgba(255,255,255,0.1); color: #fff;" onclick="copyManConfig()"><i class="fa-solid fa-copy"></i> کپی</button>
                            <button class="au-btn" style="font-size: 0.8rem; padding: 5px 10px; gap: 5px; background: #00d285; color: #000; border: none; font-weight: bold;" onclick="downloadManConfig()"><i class="fa-solid fa-download"></i> دانلود کانفیگ</button>
                        </div>
                        <textarea id="man-config-text" readonly style="width: 100%; height: 80px; background: transparent; border: none; color: var(--au-text-muted); font-family: monospace; font-size: 0.75rem; resize: none; direction: ltr;" placeholder="در حال دریافت کانفیگ..."></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 15px;">
                        <div style="font-size: 0.8rem; color: var(--au-text-muted); margin-bottom: 10px;"><i class="fa-solid fa-qrcode"></i> بارکد کانفیگ جهت اسکن در گوشی</div>
                        <div id="man-qrcode" style="display: inline-block; background: #fff; padding: 10px; border-radius: 8px; min-width: 150px; min-height: 150px;"></div>
                    </div>
                </div>

            </div>
            
            <div class="au-modal-footer" style="padding: 15px 20px; background: rgba(0,0,0,0.2); border-top: 1px solid var(--au-border); display: flex; flex-direction: column; gap: 10px;">
                <button class="au-btn" style="width: 100%; justify-content: center; background: rgba(255,255,255,0.05);" onclick="refreshManStats()"><i class="fa-solid fa-rotate"></i> بروزرسانی اطلاعات لحظه‌ای</button>
                <div style="display: flex; gap: 10px;">
                    <button class="au-btn au-btn-primary" style="flex: 1; justify-content: center;" id="man-btn-renew"><i class="fa-solid fa-plus"></i> تمدید سرویس</button>
                    <button class="au-btn" style="flex: 1; justify-content: center; background: rgba(255,50,50,0.1); color: #ff4d4d; border: 1px solid rgba(255,50,50,0.2);" id="man-btn-delete"><i class="fa-solid fa-trash-can"></i> حذف کامل سرویس</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const RAW_PRODUCTS = <?= json_encode($allowedProducts) ?>;
        
        // Modal functions
        function openModal(id) {
            const m = document.getElementById(id);
            if(m) {
                m.style.display = 'flex';
                setTimeout(() => m.classList.add('show'), 10);
            }
        }
        function closeModal(id) {
            const m = document.getElementById(id);
            if(m) {
                m.classList.remove('show');
                setTimeout(() => m.style.display = 'none', 300);
            }
        }

        // Close modal when clicking on the dark background
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('au-modal')) {
                closeModal(e.target.id);
            }
        });
        // Data Fetching Logic
        let currentPage = 1;
        let currentSearch = '';
        let currentStatus = 'all';
        let currentConnection = 'all';
        let currentSort = 'desc';

        async function loadUsers(page = 1) {
            currentPage = page;
            const container = document.getElementById('au-users-container');
            const pagination = document.getElementById('au-pagination-container');
            
            container.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--au-text-muted);"><i class="fa-solid fa-circle-notch fa-spin fa-2x"></i><p style="margin-top: 15px;">در حال بارگذاری کاربران...</p></div>';

            try {
                const res = await fetch(`ajax/agent_users_data.php?action=get_users&page=${page}&search=${encodeURIComponent(currentSearch)}&status=${currentStatus}&sort=${currentSort}`);
                const json = await res.json();
                
                if (json.status !== 'success') {
                    container.innerHTML = `<div style="text-align:center; padding: 40px; color: var(--au-danger);">${json.message || 'خطا در دریافت اطلاعات'}</div>`;
                    return;
                }

                const users = json.data;
                const pageInfo = json.pagination;

                if (users.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--au-text-muted);">هیچ کاربری یافت نشد.</div>';
                    pagination.innerHTML = '';
                    return;
                }

                let html = '';
                window.usersData = window.usersData || {};
                
                users.forEach(user => {
                    window.usersData[user.id] = user;
                    html += `
                    <div class="au-card" id="user-card-${user.id}" onclick="openManageModal('${user.id}')" style="cursor: pointer;">
                        <!-- ۱. نام کاربری و لوکیشن و تقویم -->
                        <div class="au-col au-col-meta">
                            <div class="au-user-title">
                                <span class="au-username">${user.username}</span>
                                <span class="au-online-indicator offline" id="online-indicator-${user.id}"></span>
                            </div>
                            <div class="au-meta-sub">
                                <span class="au-badge-location">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    ${user.location}
                                </span>
                                <span class="au-meta-date">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    ساخته شده: ${toPersianDigits(user.created_at)}
                                </span>
                            </div>
                        </div>

                        <!-- ۲. وضعیت فعال/غیرفعال و آنلاین/آفلاین و انقضا -->
                        <div class="au-col au-col-status">
                            <div class="au-badges-row">
                                <span class="au-badge-status ${user.status === 'active' ? 'active' : 'inactive'}" id="status-badge-${user.id}">
                                    ${user.status_label}
                                </span>
                                <span class="au-badge-connection offline" id="connection-badge-${user.id}">
                                    <span class="dot">•</span> <span class="label">در حال بررسی...</span>
                                </span>
                            </div>
                            <div class="au-expiry-text" id="expiry-text-${user.id}">
                                پایان: ${user.expires_at} (${toPersianDigits(user.rem_days)} روز)
                            </div>
                        </div>

                        <!-- ۳. میزان مصرف داده و نوار پیشرفت خطی -->
                        <div class="au-col au-col-usage">
                            <div class="au-usage-top">
                                <span class="au-usage-pct" id="usage-pct-${user.id}">...</span>
                                <span class="au-usage-lbl">مصرف <i class="fa-solid fa-circle-info" style="font-size: 0.75rem; opacity: 0.5;"></i></span>
                            </div>
                            <div class="au-progress-track">
                                <div class="au-progress-fill" id="progress-fill-${user.id}">
                                    <div class="au-progress-knob"></div>
                                </div>
                            </div>
                            <div class="au-usage-bottom">
                                <span class="au-usage-limit" id="usage-limit-${user.id}">از ${toPersianDigits(user.total_gb)}</span>
                                <span class="au-usage-used" id="usage-used-${user.id}">...</span>
                            </div>
                        </div>

                        <!-- ۴. دکمه‌های عملیات سریع -->
                        <div class="au-col au-col-actions" onclick="event.stopPropagation();">
                            <button class="au-btn-circle-action" onclick="openManageModal('${user.id}'); event.stopPropagation();" title="تنظیمات اشتراک">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                            </button>
                            
                            <div style="position: relative;">
                                <button class="au-btn-circle-action au-btn-dropdown" data-target="dropdown-${user.id}" title="بیشتر">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                                </button>
                                
                                <div class="au-dropdown" id="dropdown-${user.id}">
                                    <a href="#" class="au-dropdown-item" onclick="openManageModal('${user.id}'); return false;"><i class="fa-solid fa-link"></i> تنظیمات و لینک</a>
                                    <a href="#" class="au-dropdown-item" onclick="openRenewModal('${user.id}', '${user.username}', '${user.location}'); return false;"><i class="fa-solid fa-rotate-right"></i> تمدید سرویس</a>
                                    <a href="#" class="au-dropdown-item danger" onclick="deleteUser('${user.id}'); return false;"><i class="fa-solid fa-trash"></i> حذف کاربر</a>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });

                // Render all users first
                container.innerHTML = html;

                // Re-bind dropdowns
                bindDropdowns();

                // Render pagination
                renderPagination(pageInfo.page, pageInfo.total_pages);

                // Then load live stats sequentially to prevent server overload
                for (const user of users) {
                    await fetchLiveStats(user.id);
                }

            } catch (err) {
                container.innerHTML = '<div style="text-align:center; padding: 40px; color: var(--au-danger);">خطای ارتباط با سرور</div>';
            }
        }

        async function fetchLiveStats(id) {
            try {
                const res = await fetch(`ajax/agent_users_data.php?action=get_user_live&id=${id}`);
                const json = await res.json();
                
                if (json.status !== 'success') {
                    const connBadge = document.getElementById(`connection-badge-${id}`);
                    if (connBadge) {
                        connBadge.innerHTML = `<span class="dot">•</span> <span class="label">خطا</span>`;
                    }
                    return;
                }
                
                const data = json.live;
                window.usersData[id] = { ...window.usersData[id], ...data };
                
                // 1. Update Connection Status Badge
                const connBadge = document.getElementById(`connection-badge-${id}`);
                if (connBadge) {
                    connBadge.className = `au-badge-connection ${data.is_online}`;
                    connBadge.innerHTML = `<span class="dot">•</span> <span class="label">${data.online_label}</span>`;
                }
                
                // Connection Filtering (Frontend side)
                const userCard = document.getElementById(`user-card-${id}`);
                if (userCard && currentConnection !== 'all') {
                    if (data.is_online !== currentConnection) {
                        userCard.style.display = 'none';
                    }
                }
                
                // 2. Update Username Online Indicator Dot
                const onlineInd = document.getElementById(`online-indicator-${id}`);
                if (onlineInd) {
                    onlineInd.className = `au-online-indicator ${data.is_online}`;
                }
                
                // 3. Update Status Badge
                const statusBadge = document.getElementById(`status-badge-${id}`);
                if (statusBadge) {
                    statusBadge.className = `au-badge-status ${data.status}`;
                    statusBadge.textContent = data.status_label;
                }
                
                // 4. Update Expiry Text
                const expiryText = document.getElementById(`expiry-text-${id}`);
                if (expiryText) {
                    expiryText.textContent = `پایان: ${data.expires_at} (${toPersianDigits(data.rem_days)} روز)`;
                }
                
                // 5. Update Usage Top (Percentage)
                const usagePct = document.getElementById(`usage-pct-${id}`);
                if (usagePct) {
                    usagePct.textContent = `٪${toPersianDigits(data.usage_percent)}`;
                }
                
                // 6. Update Usage Limits/Used
                const usageLimit = document.getElementById(`usage-limit-${id}`);
                if (usageLimit) {
                    usageLimit.textContent = `از ${toPersianDigits(data.limit_formatted)}`;
                }
                
                const usageUsed = document.getElementById(`usage-used-${id}`);
                if (usageUsed) {
                    usageUsed.textContent = toPersianDigits(data.used_formatted);
                }
                
                // 7. Update Progress Bar
                const progressFill = document.getElementById(`progress-fill-${id}`);
                if (progressFill) {
                    progressFill.style.width = `${data.usage_percent}%`;
                    if (data.usage_percent >= 90) {
                        progressFill.className = 'au-progress-fill danger';
                    } else if (data.usage_percent >= 75) {
                        progressFill.className = 'au-progress-fill warning';
                    } else {
                        progressFill.className = 'au-progress-fill';
                    }
                }
                
            } catch (err) {
                console.error(err);
            }
        }

        function toPersianDigits(str) {
            if (str === null || str === undefined) return '';
            const id = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return str.toString()
                .replace(/[0-9]/g, function(w) { return id[+w]; })
                .replace(/\./g, '٫');
        }

        function renderPagination(current, total) {
            const pagination = document.getElementById('au-pagination-container');
            if (total <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let html = '';
            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
                    html += `<a href="#" class="au-page-btn ${i === current ? 'active' : ''}" onclick="event.preventDefault(); loadUsers(${i})">${i}</a>`;
                } else if (i === current - 2 || i === current + 2) {
                    html += `<a href="#" class="au-page-btn" style="pointer-events:none;">...</a>`;
                }
            }
            pagination.innerHTML = html;
        }

        function bindDropdowns() {
            const dropdownBtns = document.querySelectorAll('.au-btn-dropdown');
            dropdownBtns.forEach(btn => {
                btn.onclick = (e) => {
                    e.stopPropagation();
                    const targetId = btn.getAttribute('data-target');
                    const dropdown = document.getElementById(targetId);
                    
                    document.querySelectorAll('.au-dropdown.show').forEach(d => {
                        if (d.id !== targetId) d.classList.remove('show');
                    });

                    if (dropdown) dropdown.classList.toggle('show');
                };
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadUsers(1);

            const searchInput = document.getElementById('au-search-input');
            let searchTimeout;
            if(searchInput) {
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        currentSearch = e.target.value;
                        loadUsers(1);
                    }, 500);
                });
            }
            
            const btnCreateOpen = document.querySelector('.au-btn-primary');
            if(btnCreateOpen) {
                btnCreateOpen.addEventListener('click', () => {
                    document.getElementById('create-error').style.display = 'none';
                    document.getElementById('create-username').value = '';
                    openModal('create-modal');
                });
            }
        });

        function updateProductsList(locSelectId, prodSelectId) {
            const loc = document.getElementById(locSelectId).value;
            const prodSelect = document.getElementById(prodSelectId);
            prodSelect.innerHTML = '<option value="">-- انتخاب پلن --</option>';
            if(!loc) return;

            const filtered = RAW_PRODUCTS.filter(p => {
                const pLoc = p.Location || p.location || '';
                return pLoc === loc || pLoc === '/all';
            });
            filtered.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id || p.ID || p.id_product;
                opt.textContent = `${p.name_product} - ${Number(p.price_product).toLocaleString()} تومان`;
                prodSelect.appendChild(opt);
            });
        }

        async function submitCreateUser() {
            const loc = document.getElementById('create-location').value;
            const prodId = document.getElementById('create-product').value;
            const username = document.getElementById('create-username').value;
            const errDiv = document.getElementById('create-error');
            const btn = document.getElementById('btn-submit-create');

            if(!loc || !prodId) {
                errDiv.textContent = 'انتخاب سرور و پلن الزامی است';
                errDiv.style.display = 'block';
                return;
            }

            errDiv.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال پردازش...';

            const formData = new FormData();
            formData.append('action', 'create_user');
            formData.append('location', loc);
            formData.append('product_id', prodId);
            formData.append('username', username);

            try {
                const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.status === 'success') {
                    closeModal('create-modal');
                    loadUsers(1);
                    alert('کاربر با موفقیت ساخته شد!');
                } else {
                    errDiv.textContent = json.message || 'خطا در ساخت کاربر';
                    errDiv.style.display = 'block';
                }
            } catch(e) {
                errDiv.textContent = 'خطای ارتباط با سرور';
                errDiv.style.display = 'block';
            }
            btn.disabled = false;
            btn.textContent = 'ساخت و کسر از حساب';
        }

        function openRenewModal(invoiceId, username, location) {
            document.getElementById('renew-invoice-id').value = invoiceId;
            document.getElementById('renew-username-lbl').textContent = username;
            document.getElementById('renew-location-lbl').textContent = location;
            document.getElementById('renew-location-val').value = location;
            
            document.getElementById('renew-error').style.display = 'none';
            
            const prodSelect = document.getElementById('renew-product');
            prodSelect.innerHTML = '<option value="">-- انتخاب پلن --</option>';
            const filtered = RAW_PRODUCTS.filter(p => {
                const pLoc = p.Location || p.location || '';
                return pLoc === location || pLoc === '/all';
            });
            filtered.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id || p.ID || p.id_product;
                opt.textContent = `${p.name_product} - ${Number(p.price_product).toLocaleString()} تومان`;
                prodSelect.appendChild(opt);
            });
            
            openModal('renew-modal');
        }

        async function submitRenewUser() {
            const invoiceId = document.getElementById('renew-invoice-id').value;
            const prodId = document.getElementById('renew-product').value;
            const errDiv = document.getElementById('renew-error');
            const btn = document.getElementById('btn-submit-renew');

            if(!prodId) {
                errDiv.textContent = 'انتخاب پلن الزامی است';
                errDiv.style.display = 'block';
                return;
            }

            errDiv.style.display = 'none';
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال پردازش...';

            const formData = new FormData();
            formData.append('action', 'renew_user');
            formData.append('invoice_id', invoiceId);
            formData.append('product_id', prodId);

            try {
                const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.status === 'success') {
                    closeModal('renew-modal');
                    loadUsers(currentPage);
                    alert('سرویس با موفقیت تمدید شد!');
                } else {
                    errDiv.textContent = json.message || 'خطا در تمدید سرویس';
                    errDiv.style.display = 'block';
                }
            } catch(e) {
                errDiv.textContent = 'خطای ارتباط با سرور';
                errDiv.style.display = 'block';
            }
            btn.disabled = false;
            btn.textContent = 'تمدید سرویس';
        }

        async function deleteUser(invoiceId) {
            if(!confirm('آیا از حذف این کاربر اطمینان دارید؟ این عملیات قابل بازگشت نیست!')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('invoice_id', invoiceId);

            try {
                const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.status === 'success') {
                    loadUsers(currentPage);
                } else {
                    alert(json.message || 'خطا در حذف کاربر');
                }
            } catch(e) {
                alert('خطای ارتباط با سرور');
            }
        }

        let activeManageModalId = null;
        let qrCodeInstance = null;

        async function openManageModal(id) {
            activeManageModalId = id;
            openModal('manage-modal');
            
            const user = window.usersData[id] || { username: 'نامشخص', plan_name: 'نامشخص', price: '0' };
            
            document.getElementById('man-plan-name').textContent = user.plan_name || 'نامشخص';
            
            // Format price
            let priceFormatted = user.price ? Number(user.price).toLocaleString('fa-IR') : '0';
            document.getElementById('man-price').textContent = priceFormatted + ' تومان';
            document.getElementById('man-invoice-id').textContent = id;
            
            const statusBadgeHTML = document.getElementById(`status-badge-${id}`)?.outerHTML || '—';
            const connectionBadgeHTML = document.getElementById(`connection-badge-${id}`)?.outerHTML || '—';
            const location = document.querySelector(`#user-card-${id} .au-badge-location`)?.textContent.trim() || '—';
            
            document.getElementById('man-location').textContent = location;
            document.getElementById('man-status').innerHTML = statusBadgeHTML;
            document.getElementById('man-connection').innerHTML = connectionBadgeHTML;
            document.getElementById('man-username').textContent = user.username || '—';

            const created = document.querySelector(`#user-card-${id} .au-meta-date`)?.textContent.replace('ساخته شده:', '').trim() || '—';
            const expiryText = document.getElementById(`expiry-text-${id}`)?.textContent || '—';
            let expiredStr = '—', remDaysStr = '—';
            if(expiryText !== '—') {
                const match = expiryText.match(/پایان:\s*(.*?)\s*\((.*?)\)/);
                if(match) {
                    expiredStr = match[1];
                    remDaysStr = match[2];
                } else {
                    expiredStr = expiryText;
                }
            }
            document.getElementById('man-created').textContent = created;
            document.getElementById('man-expired').textContent = expiredStr;
            document.getElementById('man-rem-days').textContent = remDaysStr;

            const usageLimit = document.getElementById(`usage-limit-${id}`)?.textContent.replace('از ', '').trim() || '—';
            const usageUsed = document.getElementById(`usage-used-${id}`)?.textContent.trim() || '—';
            const usagePct = document.getElementById(`usage-pct-${id}`)?.textContent.trim() || '٪۰';
            const pctVal = usagePct.replace('٪', '').replace(/[۰-۹]/g, w => ['0','1','2','3','4','5','6','7','8','9'][w.charCodeAt(0)-1776] || w);
            
            const progressFillClass = document.getElementById(`progress-fill-${id}`)?.className || 'au-progress-fill';
            
            document.getElementById('man-usage-rem').textContent = `حجم کل: ${usageLimit}`;
            document.getElementById('man-usage-used').textContent = usageUsed;
            document.getElementById('man-usage-limit').textContent = usageLimit;
            document.getElementById('man-usage-pct').textContent = usagePct;
            
            const pFill = document.getElementById('man-progress-fill');
            pFill.className = progressFillClass;
            pFill.style.width = pctVal + '%';

            const configText = document.getElementById('man-config-text');
            const configType = document.getElementById('man-config-type');
            const qrContainer = document.getElementById('man-qrcode');
            
            configText.value = '';
            configType.textContent = 'درحال دریافت...';
            qrContainer.innerHTML = '';
            if (qrCodeInstance) {
                qrCodeInstance.clear();
                qrCodeInstance = null;
            }

            document.getElementById('man-btn-renew').onclick = () => { closeModal('manage-modal'); openRenewModal(id, user.username, location); };
            document.getElementById('man-btn-delete').onclick = () => { closeModal('manage-modal'); deleteUser(id); };

            await fetchManConfig(id);
        }

        async function fetchManConfig(invoiceId) {
            const configText = document.getElementById('man-config-text');
            const configType = document.getElementById('man-config-type');
            const qrContainer = document.getElementById('man-qrcode');
            
            const formData = new FormData();
            formData.append('action', 'get_link');
            formData.append('invoice_id', invoiceId);

            try {
                const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.status === 'success') {
                    const content = json.link.trim();
                    configText.value = content;
                    
                    let isWireguard = content.includes('[Interface]') || content.includes('[Peer]');
                    configType.textContent = isWireguard ? 'کانفیگ وایرگارد' : 'لینک سابسکریپشن (Sanaei/X-ui)';
                    configText.setAttribute('data-type', isWireguard ? 'wg' : 'sub');

                    qrContainer.innerHTML = '';
                    qrCodeInstance = new QRCode(qrContainer, {
                        text: content,
                        width: 150,
                        height: 150,
                        colorDark : "#000000",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.L
                    });
                } else {
                    configText.value = json.message || 'خطا در دریافت لینک';
                    configType.textContent = 'خطا';
                }
            } catch(e) {
                configText.value = 'خطای ارتباط با سرور';
                configType.textContent = 'خطا';
            }
        }

        async function refreshManStats() {
            if(!activeManageModalId) return;
            const btn = document.querySelector('#manage-modal .au-modal-footer button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال بروزرسانی...';
            
            await fetchLiveStats([activeManageModalId]);
            openManageModal(activeManageModalId);
            
            btn.innerHTML = originalText;
        }

        function copyManConfig() {
            const content = document.getElementById('man-config-text').value;
            if(!content || content.includes('خطا') || content.includes('درحال دریافت')) return;
            
            navigator.clipboard.writeText(content).then(() => {
                alert('با موفقیت کپی شد!');
            }).catch(err => {
                alert('خطا در کپی کردن متن!');
            });
        }

        function downloadManConfig() {
            const configTextEl = document.getElementById('man-config-text');
            const content = configTextEl.value;
            if(!content || content.includes('خطا') || content.includes('درحال دریافت')) return;

            const type = configTextEl.getAttribute('data-type');
            const user = window.usersData[activeManageModalId];
            const username = (user && user.username) ? user.username : 'config';
            
            const fileName = username + (type === 'wg' ? '.conf' : '.txt');
            
            const blob = new Blob([content], { type: "text/plain;charset=utf-8" });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => {
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }, 0);
        }
        // --- Filters & Sorting Event Listeners ---
        document.getElementById('au-filter-status').addEventListener('change', function() {
            currentStatus = this.value;
            loadUsers(1);
        });
        
        document.getElementById('au-filter-connection').addEventListener('change', function() {
            currentConnection = this.value;
            // Since connection is frontend-filtered, we reload the page users and let fetchLiveStats hide them
            loadUsers(1);
        });
        
        document.getElementById('au-sort-btn').addEventListener('click', function() {
            currentSort = currentSort === 'desc' ? 'asc' : 'desc';
            
            // Toggle icon visual slightly
            if (currentSort === 'asc') {
                this.style.transform = 'scaleY(-1)';
            } else {
                this.style.transform = 'none';
            }
            
            loadUsers(1);
        });
    </script>


    <script src="js/agent_users.js"></script>
    <style>
        /* Mobile padding adjustment */
        @media (max-width: 1024px) {
            body { --au-mobile-offset: 65px; }
        }
    </style>
</body>
</html>
