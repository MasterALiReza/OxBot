<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

$pageTitle = $textbotlang['panel']['servicesTitle'];
$pageLede = $textbotlang['panel']['servicesSubtitle'];
$activeNav = 'service_other';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($search !== '') {
  $where[] = "(id_user LIKE ? OR COALESCE(username,'') LIKE ? OR COALESCE(type,'') LIKE ?)";
  $params = ["%$search%", "%$search%", "%$search%"];
}
if ($status !== '') {
  $where[] = "status = ?";
  $params[] = $status;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
  $total = db_count($pdo, "SELECT COUNT(*) FROM service_other $whereSQL", $params);
  $services = db_fetchAll($pdo, "SELECT * FROM service_other $whereSQL ORDER BY id DESC LIMIT $perPage OFFSET $offset", $params);
} catch (Exception $e) {
  $total = 0;
  $services = [];
  error_log('service.php error: ' . $e->getMessage());
}
$totalPages = max(1, (int) ceil($total / $perPage));

// Calculate Global Stats
try {
  $globalTotal = db_count($pdo, "SELECT COUNT(*) FROM service_other");
  $globalPending = db_count($pdo, "SELECT COUNT(*) FROM service_other WHERE status = 'pending'");
  $globalDone = db_count($pdo, "SELECT COUNT(*) FROM service_other WHERE status = 'done'");
  $globalReject = db_count($pdo, "SELECT COUNT(*) FROM service_other WHERE status = 'reject'");
} catch (Exception $e) {
  $globalTotal = 0; $globalPending = 0; $globalDone = 0; $globalReject = 0;
}

$typeMap = [
  'change_location' => $textbotlang['panel']['serviceChangeLocationLabel'] ?? 'تغییر لوکیشن',
  'extra_user' => $textbotlang['panel']['serviceExtraVolumeLabel'] ?? 'افزایش حجم',
  'extra_time_user' => $textbotlang['panel']['serviceExtraTimeLabel'] ?? 'افزایش زمان',
  'extends_not_user' => $textbotlang['panel']['serviceRenewLabel'] ?? 'تمدید سرویس',
  'extend_user' => $textbotlang['panel']['serviceRenewLabel2'] ?? 'تمدید سرویس',
  'transfertouser' => $textbotlang['panel']['serviceTransferOrderLabel'] ?? 'انتقال سرویس',
  'extend_user_by_admin' => 'تمدید سرویس (توسط مدیر)',
  'extra_user_by_admin' => 'افزایش حجم (توسط مدیر)',
  'extra_time_user_by_admin' => 'افزایش زمان (توسط مدیر)'
];

$pageTitle = $textbotlang['panel']['servicesHeading'];
$pageLede = $textbotlang['panel']['servicesSubtitle2'];
$activeNav = 'service';
include __DIR__ . '/inc/layout_head.php';
?>

<!-- Top Statistics Cards -->
<div class="stats fade-up" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;">
    
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-blue">
                <?= icon('activity', 20) ?>
            </div>
            <div class="dash-card-title">کل درخواست‌ها</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill neutral" style="padding: 4px 10px;">تراکنش دستی ثبت شده</span>
            </div>
            <div class="dash-card-value">
                <?= number_format($globalTotal) ?>
            </div>
        </div>
    </div>
    
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-amber">
                <?= icon('clock', 20) ?>
            </div>
            <div class="dash-card-title">در انتظار انجام</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill warning" style="padding: 4px 10px;">نیازمند بررسی</span>
            </div>
            <div class="dash-card-value">
                <?= number_format($globalPending) ?>
            </div>
        </div>
    </div>
    
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-emerald">
                <?= icon('check-circle', 20) ?>
            </div>
            <div class="dash-card-title">انجام شده</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill success" style="padding: 4px 10px;">موفق</span>
            </div>
            <div class="dash-card-value">
                <?= number_format($globalDone) ?>
            </div>
        </div>
    </div>
    
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="icon-glow bg-red">
                <?= icon('x-circle', 20) ?>
            </div>
            <div class="dash-card-title">رد شده</div>
        </div>
        <div class="dash-card-footer">
            <div class="dash-card-pill">
                <span class="status-pill danger" style="padding: 4px 10px;">لغو شده</span>
            </div>
            <div class="dash-card-value">
                <?= number_format($globalReject) ?>
            </div>
        </div>
    </div>

</div>

<div class="card fade-up">
  <div class="toolbar">
    <div class="toolbar-title"><?= $textbotlang['panel']['servicesPageHeading'] ?> <small>(<?= number_format($total) ?>)</small></div>
    <form method="GET" id="srvForm" class="toolbar-end">
      <select name="status" class="select" style="width:auto" onchange="document.getElementById('srvForm').submit()">
        <option value="">همه وضعیت‌ها</option>
        <option value="done" <?= $status === 'done' ? 'selected' : '' ?>>انجام شده</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>در انتظار</option>
        <option value="reject" <?= $status === 'reject' ? 'selected' : '' ?>>رد شده</option>
      </select>
      <div class="search-box" style="min-width:240px">
        <?= icon('search', 14) ?>
        <input type="text" name="q" placeholder="<?= htmlspecialchars($textbotlang['panel']['serviceSearchServicePlaceholder'] ?? 'جستجو') ?>" value="<?= htmlspecialchars($search) ?>"
          autocomplete="off">
        <button type="button" class="search-clear">✕</button>
        <button type="submit" class="search-btn">جستجو</button>
      </div>
      <?php if ($search || $status): ?>
        <a href="service.php" class="btn-link" style="font-size:.78rem"><?= $textbotlang['panel']['serviceColPanel'] ?></a>
      <?php endif; ?>
    </form>
  </div>

  <div class="tbl-wrap dash-services">
    <table class="tbl-lg">
      <thead>
        <tr>
          <th><?= $textbotlang['panel']['serviceDetailUser'] ?? 'کاربر' ?></th>
          <th style="text-align:right;"><?= $textbotlang['panel']['serviceColType'] ?? 'سرویس' ?></th>
          <th style="text-align:right;"><?= $textbotlang['panel']['serviceColPrice'] ?? 'مبلغ' ?></th>
          <th style="text-align:right;"><?= $textbotlang['panel']['serviceColDate'] ?? 'تاریخ ثبت' ?></th>
          <th style="text-align:center;"><?= $textbotlang['panel']['serviceColStatus'] ?? 'وضعیت' ?></th>
          <th style="text-align:center;"><?= $textbotlang['panel']['serviceColActions'] ?? 'عملیات' ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($services)): ?>
          <tr>
            <td colspan="6">
              <div class="empty" style="padding:48px 20px">
                <svg class="ill" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="30" y="30" width="120" height="80" rx="10" fill="var(--sf3)" />
                  <rect x="50" y="50" width="40" height="40" rx="6" fill="var(--bds)" />
                  <rect x="100" y="55" width="35" height="8" rx="4" fill="var(--bd)" />
                  <rect x="100" y="70" width="25" height="8" rx="4" fill="var(--bd)" />
                  <rect x="100" y="85" width="30" height="8" rx="4" fill="var(--bd)" />
                  <path d="M60 65 l10 10 l20-20" stroke="var(--ac)" stroke-width="3" stroke-linecap="round" fill="none" />
                </svg>
                <p><?= $search ? $textbotlang['panel']['serviceNoServiceFound'] : $textbotlang['panel']['serviceNoManualServiceYet'] ?></p>
              </div>
            </td>
          </tr>
        <?php else:
          $i = $offset + 1;
          foreach ($services as $s):
            $stMap = [
              'done' => ['tag-ok', $textbotlang['panel']['serviceStatusDone'] ?? 'انجام شده'],
              'pending' => ['tag-warn', $textbotlang['panel']['serviceStatusWaiting'] ?? 'در انتظار'],
              'reject' => ['tag-no', $textbotlang['panel']['serviceStatusRejected'] ?? 'رد شده'],
              'unpaid' => ['tag-plain', 'پرداخت نشده'],
              'paid' => ['tag-ok', 'پرداخت شده'],
              'active' => ['tag-ok', 'فعال'],
              'disabled' => ['tag-no', 'غیرفعال'],
              '' => ['tag-ok', 'ثبت شده (سیستمی)']
            ];
            $rawStatus = trim($s['status'] ?? '');
            [$cls, $lbl] = $stMap[$rawStatus] ?? ['tag-plain', $rawStatus ?: '—'];
            $typeLabel = $typeMap[$s['type'] ?? ''] ?? ($s['type'] ?? '—');
            
            $rawVal = $s['value'] ?? '';
            $valStr = $rawVal;
            
            if ($s['type'] === 'transfertouser' && !str_starts_with(trim($rawVal), '{') && is_numeric($rawVal)) {
                $valStr = 'کاربر مقصد: ' . $rawVal;
            }

            if (str_starts_with(trim($rawVal), '{')) {
                $decoded = json_decode($rawVal, true);
                if (is_array($decoded)) {
                    $parts = [];
                    
                    // Basic mappings
                    if (isset($decoded['volumebuy'])) $parts[] = $decoded['volumebuy'] . ' گیگابایت';
                    if (isset($decoded['time'])) $parts[] = $decoded['time'] . ' روز';
                    if (isset($decoded['day'])) $parts[] = $decoded['day'] . ' روز';
                    if (isset($decoded['server_id'])) $parts[] = 'سرور ' . $decoded['server_id'];
                    if (isset($decoded['plan_id'])) $parts[] = 'پلن ' . $decoded['plan_id'];
                    if (isset($decoded['server_name'])) $parts[] = $decoded['server_name'];
                    
                    // Detailed mappings for extra volume/time
                    if (isset($decoded['old_volume']) && isset($decoded['new_volume'])) {
                        $parts[] = 'حجم از ' . $decoded['old_volume'] . ' به ' . $decoded['new_volume'] . ' گیگابایت';
                    }
                    if (isset($decoded['old_time']) && isset($decoded['new_time'])) {
                        $parts[] = 'زمان از ' . $decoded['old_time'] . ' به ' . $decoded['new_time'] . ' روز';
                    }
                    
                    // Other mappings
                    if (isset($decoded['target_user'])) $parts[] = 'کاربر مقصد: ' . $decoded['target_user'];
                    
                    if (empty($parts)) {
                        $keyMap = [
                            'price_per_day' => 'قیمت روزانه',
                            'price_per_gb' => 'قیمت هر گیگ',
                            'location_id' => 'لوکیشن',
                            'server_id' => 'سرور'
                        ];
                        foreach ($decoded as $k => $v) {
                            if (is_scalar($v)) {
                                $kLabel = $keyMap[$k] ?? $k;
                                $parts[] = "$kLabel: $v";
                            }
                        }
                    }
                    $valStr = implode(' - ', $parts);
                }
            }
            if ($valStr === '' || $valStr === '[]' || $valStr === '{}') $valStr = '—';
            ?>
            <tr>
              <td data-label="کاربر">
                <div class="user-profile-cell" style="display:flex; align-items:center; gap:10px;">
                    <div style="width:36px; height:36px; border-radius:50%; background:var(--ac-light); color:var(--ac); display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:14px; flex-shrink:0;">
                        <?= mb_strtoupper(mb_substr($s['username'] ?: 'U', 0, 1, 'UTF-8'), 'UTF-8') ?>
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <span class="profile-name" style="font-weight:600; color:var(--text); font-size:0.9rem;"><?= !empty($s['username']) ? htmlspecialchars(trunc($s['username'], 18)) : 'بدون یوزرنیم' ?></span>
                        <div class="profile-id-box" style="display:flex; align-items:center; gap:4px; color:var(--mute); font-size:0.75rem; margin-top:2px;">
                            <?= icon('user', 12) ?> <span><?= htmlspecialchars(eng_num($s['id_user'] ?? '—')) ?></span>
                        </div>
                    </div>
                </div>
              </td>
              <td data-label="سرویس">
                  <div style="display:flex; flex-direction:column; align-items:flex-start; gap:4px; text-align:right;">
                      <div style="display:flex; align-items:center; gap:6px; color:var(--text); font-size: 0.85rem; font-weight:600;">
                          <?= icon('package', 14) ?> <?= htmlspecialchars($typeLabel) ?>
                      </div>
                      <span class="cn" style="font-size:0.8rem; color:var(--mute); display:inline-block; max-width:350px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($valStr) ?>" dir="ltr">
                          <?= htmlspecialchars($valStr) ?>
                      </span>
                  </div>
              </td>
              <td data-label="مبلغ">
                  <div style="font-weight:700; color:var(--text); font-size:0.95rem; text-align:right; display:flex; align-items:center; gap:6px;">
                      <span style="color:var(--ts);"><?= icon('wallet', 14) ?></span>
                      <span class="cn" dir="ltr"><?= number_format((int) ($s['price'] ?? 0)) ?></span> <span class="cf" style="font-size:0.75rem; color:var(--mute); font-weight:normal;"><?= $textbotlang['panel']['dashUnitToman'] ?? 'تومان' ?></span>
                  </div>
              </td>
              <td data-label="تاریخ ثبت">
                  <div style="color:var(--mute); font-size:0.85rem; display:flex; align-items:center; justify-content:flex-start; gap:4px;">
                      <?= icon('calendar', 14) ?> <span class="cf" dir="ltr"><?= safe_date($s['time'] ?? null, 'Y/m/d H:i') ?></span>
                  </div>
              </td>
              <td data-label="وضعیت" style="text-align:center;">
                  <span class="tag <?= $cls ?>"><?= $lbl ?></span>
              </td>
              <td data-label="عملیات" style="text-align:center;" class="<?= (($s['status'] ?? '') !== 'pending') ? 'no-actions' : '' ?>">
                <?php if (($s['status'] ?? '') === 'pending'): ?>
                  <div style="display: flex; gap: 6px; justify-content:center;">
                    <button type="button" class="btn" style="background:rgba(16, 185, 129, 0.1); color:var(--emerald); border:1px solid rgba(16, 185, 129, 0.2); padding:6px 12px; border-radius:8px; font-size:0.8rem; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:4px;"
                            hx-post="ajax/service_action.php"
                            hx-vals='{"action": "done", "id": "<?= $s['id'] ?>", "_csrf": "<?= csrf_token() ?>"}'
                            hx-target="closest tr"
                            hx-swap="outerHTML">
                      <?= icon('check', 14) ?> تایید
                    </button>
                    <button type="button" class="btn" style="background:rgba(239, 68, 68, 0.1); color:var(--red); border:1px solid rgba(239, 68, 68, 0.2); padding:6px 12px; border-radius:8px; font-size:0.8rem; cursor:pointer; font-weight:600; display:flex; align-items:center; gap:4px;"
                            hx-post="ajax/service_action.php"
                            hx-vals='{"action": "reject", "id": "<?= $s['id'] ?>", "_csrf": "<?= csrf_token() ?>"}'
                            hx-target="closest tr"
                            hx-swap="outerHTML">
                      <?= icon('x', 14) ?> رد
                    </button>
                  </div>
                <?php else: ?>
                  <span class="cf" style="color:var(--mute);">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="tbl-foot">
    <span><?= number_format($total) ?> <?= $textbotlang['panel']['serviceDetailPanel'] ?> <?= $page ?> <?= $textbotlang['panel']['serviceCloseBtn'] ?> <?= $totalPages ?></span>
    <div class="pager">
      <?php $qs = fn($p) => '?q=' . urlencode($search) . '&status=' . urlencode($status) . '&page=' . $p; ?>
      <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
      <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
