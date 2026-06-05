<?php
require 'inc/config.php';
require_auth();

$title = 'ارسال پیام همگانی';
require 'inc/layout_head.php';

?>

<style>
/* Mission Control Aesthetic for Broadcast */
.broadcast-dashboard {
    max-width: 880px;
    margin: 0 auto;
}

.bc-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--bd);
}

.bc-header h2 {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 12px;
}

.bc-header p {
    color: var(--dim);
    font-size: 0.95rem;
    margin-top: 8px;
}

.bc-alert {
    background: var(--warns);
    border: 1px solid var(--warn);
    padding: 20px 24px;
    border-radius: 16px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 20px;
    color: var(--text);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    animation: pulse 2s infinite ease-in-out;
}

.bc-alert-icon {
    width: 54px;
    height: 54px;
    background: var(--warn);
    color: #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.bc-alert-content h4 {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--warn);
    margin-bottom: 6px;
}

.bc-alert-content p {
    font-size: 0.85rem;
    color: var(--text2);
    margin: 0;
}

.bc-section {
    background: var(--sf2);
    border: 1px solid var(--bd);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
    transition: all var(--tf);
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
}

.bc-section:hover {
    border-color: var(--bds);
    box-shadow: 0 8px 32px rgba(0,0,0,0.06);
}

.bc-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.bc-section-title svg {
    color: var(--ac);
}

.bc-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.bc-checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--sf);
    border: 1.5px solid var(--bd);
    border-radius: 14px;
    cursor: pointer;
    transition: all var(--tf);
}

.bc-checkbox-wrapper:hover {
    border-color: var(--ac);
    background: var(--sf3);
    box-shadow: 0 0 16px var(--acs);
}

.bc-checkbox-wrapper input[type="checkbox"] {
    margin: 0;
    accent-color: var(--ac);
    width: 22px;
    height: 22px;
    cursor: pointer;
    flex-shrink: 0;
}

.bc-checkbox-text strong {
    display: block;
    font-size: 0.95rem;
    color: var(--text);
    margin-bottom: 6px;
}

.bc-checkbox-text small {
    color: var(--dim);
    font-size: 0.8rem;
    line-height: 1.6;
    display: block;
}

.bc-submit {
    display: flex;
    justify-content: flex-start;
    margin-top: 36px;
}

.bc-submit .btn {
    padding: 16px 36px;
    font-size: 1.05rem;
    font-weight: 700;
    border-radius: 12px;
    box-shadow: 0 4px 16px var(--acs);
    transition: all var(--tf);
}

.bc-submit .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px var(--acs);
}

/* Mobile Adjustments */
@media (max-width: 768px) {
    .bc-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .bc-section {
        padding: 16px;
    }
    
    .bc-alert {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    
    .bc-submit .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="broadcast-dashboard fade-in">
    <div class="bc-header">
        <h2><?= icon('radio', 24) ?> ایستگاه پیام‌رسانی (Broadcast)</h2>
        <p>در این بخش می‌توانید به صورت همگانی برای گروه‌های مختلف کاربری ربات، پیام متنی ارسال کنید یا پیام‌های قبلی را از حالت پین خارج کنید.</p>
    </div>

    <?php if (is_file('../cronbot/info') || is_file('../cronbot/users.json')): ?>
        <div class="bc-alert">
            <div class="bc-alert-icon">
                <?= icon('alert-triangle', 24) ?>
            </div>
            <div class="bc-alert-content">
                <h4>عملیات در جریان است!</h4>
                <p>در حال حاضر یک عملیات ارسال پیام در سرور ربات در حال انجام است. برای جلوگیری از تداخل، لطفاً تا پایان آن صبر کنید.</p>
            </div>
        </div>
    <?php endif; ?>

    <form hx-post="ajax/broadcast_action.php" hx-swap="outerHTML" hx-indicator=".loader" id="broadcastForm">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        
        <!-- Section 1: Configuration -->
        <div class="bc-section">
            <div class="bc-section-title">
                <?= icon('settings', 18) ?> پیکربندی عملیات
            </div>
            <div class="bc-grid">
                <div class="field">
                    <label class="label">نوع فرمان</label>
                    <select class="input select" name="type" id="messageType" onchange="toggleFields()">
                        <option value="sendmessage">ارسال پیام جدید</option>
                        <option value="unpinmessage">حذف پین تمامی پیام‌ها (Unpin)</option>
                    </select>
                </div>
                
                <div class="field">
                    <label class="label">دکمه شیشه‌ای (فقط پیام متنی)</label>
                    <select class="input select" name="btnmessage" id="btnmessage">
                        <option value="none">بدون دکمه</option>
                        <option value="buy">دکمه خرید سرویس (فروشگاه)</option>
                        <option value="start">دکمه شروع مجدد ربات</option>
                        <option value="usertestbtn">دکمه دریافت حساب تست</option>
                        <option value="helpbtn">دکمه راهنما و پشتیبانی</option>
                        <option value="affiliatesbtn">دکمه سیستم همکاری در فروش</option>
                        <option value="addbalance">دکمه افزایش موجودی کیف پول</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section 2: Targeting -->
        <div class="bc-section">
            <div class="bc-section-title">
                <?= icon('users', 18) ?> جامعه هدف (Targeting)
            </div>
            <div class="bc-grid">
                <div class="field">
                    <label class="label">بر اساس وضعیت اشتراک</label>
                    <select class="input select" name="target_users">
                        <option value="all">همه کاربران ربات</option>
                        <option value="customer">فقط مشتریان (دارای سرویس فعال)</option>
                        <option value="nonecustomer">فقط کاربران عادی (بدون سرویس)</option>
                    </select>
                </div>

                <div class="field">
                    <label class="label">بر اساس سطح دسترسی (نقش)</label>
                    <select class="input select" name="target_agent">
                        <option value="all">تمام نقش‌ها</option>
                        <option value="f">کاربران عادی</option>
                        <option value="n">نمایندگان فروش</option>
                        <option value="n2">نمایندگان ویژه (VIP)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section 3: Content -->
        <div class="bc-section" id="messageGroup">
            <div class="bc-section-title">
                <?= icon('message-square', 18) ?> محتوای پیام
            </div>
            <div class="field" style="margin-bottom: 20px;">
                <label class="label">متن پیام (پشتیبانی کامل از HTML و استایل‌های تلگرام)</label>
                <textarea class="input textarea" name="message" rows="7" placeholder="متن پیام جذاب و اطلاع‌رسانی خود را اینجا بنویسید..."></textarea>
            </div>

            <label class="bc-checkbox-wrapper">
                <input type="checkbox" name="pingmessage" value="yes">
                <div class="bc-checkbox-text">
                    <strong>پین شدن پیام در ربات (Pin Message)</strong>
                    <small>در صورت فعال بودن این گزینه، پیام بلافاصله پس از رسیدن به کاربر در صفحه چت او سنجاق (Pin) خواهد شد که باعث افزایش چشمگیر بازدید می‌شود.</small>
                </div>
            </label>
        </div>

        <div class="bc-submit">
            <button type="submit" class="btn btn-primary" <?php if (is_file('../cronbot/info')) echo 'disabled'; ?>>
                <?= icon('send', 18) ?> آغاز عملیات ارسال
            </button>
        </div>
    </form>
</div>

<script>
function toggleFields() {
    var type = document.getElementById('messageType').value;
    var btn = document.getElementById('btnmessage');
    var msg = document.getElementById('messageGroup');
    
    if (type === 'unpinmessage') {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        msg.style.opacity = '0.5';
        msg.style.pointerEvents = 'none';
        msg.querySelector('textarea').disabled = true;
    } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        msg.style.opacity = '1';
        msg.style.pointerEvents = 'auto';
        msg.querySelector('textarea').disabled = false;
    }
}
</script>
<?php require 'inc/layout_foot.php'; ?>

