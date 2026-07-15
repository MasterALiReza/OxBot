<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';

if (!isset($_SESSION['agent_id'])) {
    header("Location: agent_login.php");
    exit;
}

$agent_id = $_SESSION['agent_id'];

// Get agent name, type and balance
$stmt = $pdo->prepare("SELECT namecustom, agent, Balance FROM user WHERE id = :id");
$stmt->execute([':id' => $agent_id]);
$agentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agentUserRow) {
    header("Location: logout.php");
    exit;
}

$agentUsername = !empty($agentUserRow['namecustom']) ? $agentUserRow['namecustom'] : 'نماینده ' . $agent_id;
$agentType = $agentUserRow['agent'] ?? 'n'; // e.g., 'n', 'n2', 'all'

$initials = mb_strtoupper(mb_substr($agentUsername, 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پنل نمایندگی - لاگ مالی و تراکنش‌ها</title>
    <link rel="stylesheet" href="css/agent_users.css?v=<?= time() ?>">
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

        <!-- Wallet Widget -->
        <div class="au-sidebar-wallet">
            <div class="au-wallet-title">
                <?= icon('wallet', 14) ?>
                <span>موجودی حساب شما</span>
            </div>
            <div class="au-wallet-value">
                <?= number_format((float)($agentUserRow['Balance'] ?? 0)) ?> <span>تومان</span>
            </div>
        </div>

        <nav class="au-nav">
            <a href="agent_users.php" class="au-nav-item">
                <?= icon('users', 18) ?> مدیریت کاربران
            </a>
            <a href="agent_logs.php" class="au-nav-item active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                لاگ مالی و تراکنش‌ها
            </a>
        </nav>
        <div class="au-sidebar-footer">
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
                <h1>لاگ مالی و تراکنش‌ها</h1>
                <p>مشاهده سابقه شارژ کیف پول و خریدهای شما</p>
            </div>
            <div class="au-header-actions">
                <button class="au-btn au-btn-icon" title="بروزرسانی" onclick="loadLogs()">
                    <?= icon('refresh-cw', 18) ?>
                </button>
            </div>
        </div>

        <div class="au-table-wrapper" style="margin-top: 20px;">
            <table class="au-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">وضعیت</th>
                        <th>شرح عملیات</th>
                        <th>تاریخ و زمان</th>
                        <th>مبلغ (تومان)</th>
                    </tr>
                </thead>
                <tbody id="au-logs-tbody">
                    <!-- Logs will be loaded here -->
                </tbody>
            </table>
        </div>
        
    </main>

    <!-- Toast Notification -->
    <div id="au-toast" class="au-toast">
        <div class="au-toast-icon"></div>
        <div class="au-toast-message"></div>
    </div>

    <!-- Background Overlay -->
    <div class="au-overlay" id="au-overlay" onclick="document.getElementById('au-sidebar').classList.remove('open'); this.classList.remove('active');"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Mobile Sidebar Toggle
            const mobileToggleBtn = document.getElementById('au-mobile-toggle');
            const sidebar = document.getElementById('au-sidebar');
            const overlay = document.getElementById('au-overlay');
            
            if (mobileToggleBtn && sidebar) {
                // Adjust layout for mobile toggle
                if (window.innerWidth <= 768) {
                    document.documentElement.style.setProperty('--au-mobile-offset', '65px');
                }
                
                window.addEventListener('resize', () => {
                    if (window.innerWidth <= 768) {
                        document.documentElement.style.setProperty('--au-mobile-offset', '65px');
                    } else {
                        document.documentElement.style.setProperty('--au-mobile-offset', '0px');
                    }
                });

                mobileToggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('active');
                });
            }

            // Initial load
            loadLogs();
        });

        // Toast Notification System
        function showToast(message, type = 'success') {
            const toast = document.getElementById('au-toast');
            if (!toast) return;

            const iconContainer = toast.querySelector('.au-toast-icon');
            const msgContainer = toast.querySelector('.au-toast-message');
            
            toast.className = 'au-toast ' + type;
            msgContainer.textContent = message;

            if (type === 'success') {
                iconContainer.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`;
            } else if (type === 'error') {
                iconContainer.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`;
            }

            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        async function loadLogs() {
            const tbody = document.getElementById('au-logs-tbody');
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--au-text-muted);">در حال دریافت اطلاعات...</td></tr>';

            try {
                const response = await fetch('ajax/agent_logs_data.php');
                const data = await response.json();
                
                if (data.status === 'success') {
                    if (!data.data || data.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--au-text-muted);">
                            <div style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            </div>
                            هیچ تراکنشی یافت نشد
                        </td></tr>`;
                        return;
                    }
                    
                    let html = '';
                    data.data.forEach(log => {
                        let amountClass = log.type === 'deposit' ? 'style="color: var(--au-success);"' : 'style="color: var(--au-danger);"';
                        let amountText = (log.type === 'deposit' ? '+' : '') + new Intl.NumberFormat('fa-IR').format(log.amount);
                        let iconSvg = '';
                        
                        if (log.type === 'deposit') {
                            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--au-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>`;
                        } else {
                            iconSvg = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--au-danger)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>`;
                        }
                        
                        let badgeHtml = '';
                        if (log.type === 'deposit') {
                            if (log.status.toLowerCase() === 'paid') {
                                badgeHtml = '<span class="au-badge au-badge-success">موفق</span>';
                            } else {
                                badgeHtml = '<span class="au-badge au-badge-error">ناموفق / در انتظار</span>';
                            }
                        } else {
                            badgeHtml = '<span class="au-badge au-badge-success">پرداخت شده</span>';
                        }
                        
                        html += `
                            <tr>
                                <td style="text-align:center;">${iconSvg}</td>
                                <td>
                                    <div style="font-weight: 500;">${log.description}</div>
                                    <div style="font-size: 0.8rem; color: var(--au-text-muted); margin-top: 4px;">
                                        روش: ${log.method || '-'}
                                    </div>
                                </td>
                                <td>${log.time}</td>
                                <td ${amountClass} style="font-weight: bold; font-family: monospace; font-size: 1.1rem;">
                                    ${amountText}
                                </td>
                            </tr>
                        `;
                    });
                    
                    tbody.innerHTML = html;
                } else {
                    showToast(data.message || 'خطا در دریافت اطلاعات', 'error');
                }
            } catch (e) {
                console.error(e);
                showToast('خطای ارتباط با سرور', 'error');
            }
        }
    </script>
</body>
</html>
