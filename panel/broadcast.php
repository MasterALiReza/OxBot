<?php
require 'inc/config.php';
require_auth();

$title = 'ارسال پیام همگانی';
require 'inc/layout_head.php';

?>
<div class="card fade-in">
    <div class="flex-between" style="margin-bottom: 20px;">
        <h2><?= icon('send', 20) ?> ارسال پیام همگانی (Broadcast)</h2>
    </div>

    <?php if (is_file('../cronbot/info') || is_file('../cronbot/users.json')): ?>
        <div class="alert alert-warn" style="margin-bottom: 20px;">
            <?= icon('alert-triangle') ?> 
            در حال حاضر یک عملیات ارسال پیام در ربات در جریان است. لطفاً تا پایان آن صبر کنید یا عملیات را لغو کنید.
        </div>
    <?php endif; ?>

    <form hx-post="ajax/broadcast_action.php" hx-swap="outerHTML" hx-indicator=".loader" id="broadcastForm">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        
        <div class="grid" style="--cols: 2; margin-bottom: 20px;">
            <div>
                <label class="label">نوع ارسال</label>
                <select class="input" name="type" id="messageType" onchange="toggleFields()">
                    <option value="sendmessage">ارسال پیام متنی</option>
                    <option value="unpinmessage">حذف پین پیام‌ها (Unpin)</option>
                </select>
            </div>
            
            <div>
                <label class="label">دکمه شیشه‌ای (فقط پیام متنی)</label>
                <select class="input" name="btnmessage" id="btnmessage">
                    <option value="none">بدون دکمه</option>
                    <option value="buy">دکمه خرید (فروشگاه)</option>
                    <option value="start">دکمه شروع</option>
                    <option value="usertestbtn">دکمه حساب تست</option>
                    <option value="helpbtn">دکمه راهنما</option>
                    <option value="affiliatesbtn">دکمه همکاری در فروش</option>
                    <option value="addbalance">دکمه افزایش موجودی</option>
                </select>
            </div>
        </div>

        <div class="grid" style="--cols: 2; margin-bottom: 20px;">
            <div>
                <label class="label">مخاطبین (وضعیت خرید)</label>
                <select class="input" name="target_users">
                    <option value="all">همه کاربران</option>
                    <option value="customer">کاربران دارای سرویس (خریداران)</option>
                    <option value="nonecustomer">کاربران بدون سرویس</option>
                </select>
            </div>

            <div>
                <label class="label">گروه کاربری (نقش)</label>
                <select class="input" name="target_agent">
                    <option value="all">همه نقش‌ها</option>
                    <option value="f">کاربران عادی</option>
                    <option value="n">نمایندگان</option>
                    <option value="n2">نمایندگان ویژه</option>
                </select>
            </div>
        </div>

        <div class="form-group" id="messageGroup" style="margin-bottom: 20px;">
            <label class="label">متن پیام (پشتیبانی از HTML)</label>
            <textarea class="input" name="message" rows="6" placeholder="متن پیام خود را بنویسید..."></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label class="label" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="pingmessage" value="yes">
                پین شدن پیام در ربات (Pin)
            </label>
            <small class="text-muted block">در صورت فعال بودن، پس از ارسال پیام در چت کاربر پین خواهد شد.</small>
        </div>

        <button type="submit" class="btn btn-primary" <?php if (is_file('../cronbot/info')) echo 'disabled'; ?>>
            <?= icon('send', 16) ?> شروع ارسال همگانی
        </button>
    </form>
</div>

<script>
function toggleFields() {
    var type = document.getElementById('messageType').value;
    var btn = document.getElementById('btnmessage');
    var msg = document.getElementById('messageGroup');
    if (type === 'unpinmessage') {
        btn.disabled = true;
        msg.style.opacity = '0.5';
        msg.querySelector('textarea').disabled = true;
    } else {
        btn.disabled = false;
        msg.style.opacity = '1';
        msg.querySelector('textarea').disabled = false;
    }
}
</script>
<?php require 'inc/layout_foot.php'; ?>
